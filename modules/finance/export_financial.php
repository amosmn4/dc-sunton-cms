<?php
/**
 * Export Financial Reports
 * Deliverance Church Management System
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

requireLogin();

if (!hasPermission('finance')) {
    setFlashMessage('error', 'You do not have permission to access this page');
    redirect(BASE_URL . 'modules/dashboard/');
}

$report_type = isset($_GET['type']) ? sanitizeInput($_GET['type']) : 'income_expense';
$date_from = isset($_GET['date_from']) ? sanitizeInput($_GET['date_from']) : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? sanitizeInput($_GET['date_to']) : date('Y-m-d');
$export_format = isset($_GET['format']) ? sanitizeInput($_GET['format']) : 'csv';
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;

$db = Database::getInstance();
$churchInfo = getRecord('church_info', 'id', 1);

try {
    $data = [];
    $headers = [];
    
    if ($report_type === 'income_expense') {
        // Income and Expense Summary
        $headers = ['Category', 'Type', 'Amount', 'Count', 'Date'];
        
        // Get income
        $stmt = $db->executeQuery(
            "SELECT ic.name, SUM(i.amount) as total, COUNT(i.id) as count, YEAR(i.transaction_date) as year, MONTH(i.transaction_date) as month
             FROM income i
             JOIN income_categories ic ON i.category_id = ic.id
             WHERE i.transaction_date BETWEEN ? AND ? AND i.status = 'verified'
             GROUP BY ic.id, YEAR(i.transaction_date), MONTH(i.transaction_date)
             ORDER BY i.transaction_date DESC",
            [$date_from, $date_to]
        );
        
        foreach ($stmt->fetchAll() as $row) {
            $data[] = [
                $row['name'],
                'Income',
                formatCurrency($row['total']),
                $row['count'],
                $row['month'] . '/' . $row['year']
            ];
        }
        
        // Get expenses
        $stmt = $db->executeQuery(
            "SELECT ec.name, SUM(e.amount) as total, COUNT(e.id) as count, YEAR(e.expense_date) as year, MONTH(e.expense_date) as month
             FROM expenses e
             JOIN expense_categories ec ON e.category_id = ec.id
             WHERE e.expense_date BETWEEN ? AND ? AND e.status = 'paid'
             GROUP BY ec.id, YEAR(e.expense_date), MONTH(e.expense_date)
             ORDER BY e.expense_date DESC",
            [$date_from, $date_to]
        );
        
        foreach ($stmt->fetchAll() as $row) {
            $data[] = [
                $row['name'],
                'Expense',
                formatCurrency($row['total']),
                $row['count'],
                $row['month'] . '/' . $row['year']
            ];
        }
        
    } elseif ($report_type === 'monthly_summary') {
        // Monthly income vs expenses
        $headers = ['Month', 'Total Income', 'Total Expenses', 'Net', 'Income Count', 'Expense Count'];
        
        $stmt = $db->executeQuery(
            "SELECT 
                YEAR(transaction_date) as year,
                MONTH(transaction_date) as month,
                SUM(amount) as total_income,
                COUNT(*) as income_count
             FROM income
             WHERE transaction_date BETWEEN ? AND ? AND status = 'verified'
             GROUP BY YEAR(transaction_date), MONTH(transaction_date)
             ORDER BY year DESC, month DESC",
            [$date_from, $date_to]
        );
        
        $income_by_month = [];
        foreach ($stmt->fetchAll() as $row) {
            $key = $row['year'] . '-' . str_pad($row['month'], 2, '0', STR_PAD_LEFT);
            $income_by_month[$key] = $row;
        }
        
        $stmt = $db->executeQuery(
            "SELECT 
                YEAR(expense_date) as year,
                MONTH(expense_date) as month,
                SUM(amount) as total_expenses,
                COUNT(*) as expense_count
             FROM expenses
             WHERE expense_date BETWEEN ? AND ? AND status = 'paid'
             GROUP BY YEAR(expense_date), MONTH(expense_date)
             ORDER BY year DESC, month DESC",
            [$date_from, $date_to]
        );
        
        $expense_by_month = [];
        foreach ($stmt->fetchAll() as $row) {
            $key = $row['year'] . '-' . str_pad($row['month'], 2, '0', STR_PAD_LEFT);
            $expense_by_month[$key] = $row;
        }
        
        // Merge and format
        $all_months = array_unique(array_merge(array_keys($income_by_month), array_keys($expense_by_month)));
        sort($all_months);
        
        foreach ($all_months as $key) {
            $income = $income_by_month[$key] ?? ['total_income' => 0, 'income_count' => 0];
            $expense = $expense_by_month[$key] ?? ['total_expenses' => 0, 'expense_count' => 0];
            $net = $income['total_income'] - $expense['total_expenses'];
            
            $data[] = [
                $key,
                formatCurrency($income['total_income']),
                formatCurrency($expense['total_expenses']),
                formatCurrency($net),
                $income['income_count'],
                $expense['expense_count']
            ];
        }
    }
    
    // Export based on format
    if ($export_format === 'csv') {
        exportToCSV($data, $headers, 'financial_report_' . date('Ymd_His') . '.csv');
    } elseif ($export_format === 'excel') {
        $filepath = generateExcelFile($data, $headers, 'financial_report_' . date('Ymd_His') . '.xlsx');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
        readfile($filepath);
        unlink($filepath);
    } elseif ($export_format === 'pdf') {
        $html = generatePDFHTML($data, $headers, 'Financial Report - ' . $churchInfo['church_name'], $date_from, $date_to);
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="financial_report_' . date('Ymd_His') . '.html"');
        echo $html;
    }
    
    logActivity('Export financial report', 'income,expenses', null, null, ['type' => $report_type, 'date_range' => $date_from . ' to ' . $date_to, 'format' => $export_format]);
    exit;
    
} catch (Exception $e) {
    error_log("Error exporting financial report: " . $e->getMessage());
    setFlashMessage('error', 'Error exporting report: ' . $e->getMessage());
    redirect(BASE_URL . 'modules/finance/reports.php');
}

/**
 * Generate PDF HTML template
 */
function generatePDFHTML($data, $headers, $title, $date_from, $date_to) {
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>' . htmlspecialchars($title) . '</title>
        <style>
            body { font-family: Arial; margin: 20px; }
            h2 { color: #03045e; border-bottom: 2px solid #ff2400; padding-bottom: 10px; }
            .meta { color: #666; font-size: 12px; margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th { background: #03045e; color: white; padding: 10px; text-align: left; font-size: 11px; font-weight: bold; }
            td { padding: 8px; border-bottom: 1px solid #ddd; font-size: 10px; }
            tr:nth-child(even) { background: #f9f9f9; }
            .total-row { background: #e8e8e8; font-weight: bold; }
        </style>
    </head>
    <body>
        <h2>' . htmlspecialchars($title) . '</h2>
        <div class="meta">
            <p><strong>Period:</strong> ' . formatDisplayDate($date_from) . ' to ' . formatDisplayDate($date_to) . '</p>
            <p><strong>Generated:</strong> ' . date('d/m/Y H:i') . '</p>
        </div>
        <table>
            <thead>
                <tr>';
    
    foreach ($headers as $header) {
        $html .= '<th>' . htmlspecialchars($header) . '</th>';
    }
    
    $html .= '</tr></thead><tbody>';
    
    foreach ($data as $row) {
        $html .= '<tr>';
        foreach ($row as $cell) {
            $html .= '<td>' . htmlspecialchars($cell) . '</td>';
        }
        $html .= '</tr>';
    }
    
    $html .= '</tbody></table>
        <hr style="margin-top: 30px;">
        <p style="font-size: 9px; color: #999;">
            Generated by Deliverance Church Management System
        </p>
    </body>
    </html>';
    
    return $html;
}

?> === 'donors') {
        // Donor giving report
        $headers = ['Donor Name', 'Category', 'Total Giving', 'Donations', 'Last Donation', 'Contact'];
        
        $stmt = $db->executeQuery(
            "SELECT 
                i.donor_name,
                ic.name as category,
                SUM(i.amount) as total,
                COUNT(i.id) as count,
                MAX(i.transaction_date) as last_donation,
                i.donor_phone
             FROM income i
             JOIN income_categories ic ON i.category_id = ic.id
             WHERE i.transaction_date BETWEEN ? AND ? AND i.status = 'verified' AND i.donor_name IS NOT NULL
             GROUP BY i.donor_name, i.donor_phone
             ORDER BY total DESC",
            [$date_from, $date_to]
        );
        
        foreach ($stmt->fetchAll() as $row) {
            $data[] = [
                $row['donor_name'],
                $row['category'],
                formatCurrency($row['total']),
                $row['count'],
                formatDisplayDate($row['last_donation']),
                $row['donor_phone'] ?? '-'
            ];
        }
        
    } elseif ($report_type 