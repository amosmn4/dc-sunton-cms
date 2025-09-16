<?php
/**
 * Export Expense Data
 * Deliverance Church Management System
 * 
 * Export expense records in various formats (Excel, CSV, PDF)
 */

// Include configuration and functions
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Check authentication and permissions
requireLogin();
if (!hasPermission('finance')) {
    die('Access denied');
}

// Get export parameters
$format = $_GET['export'] ?? 'csv';
$status = $_GET['status'] ?? '';
$category = $_GET['category'] ?? '';
$payment_method = $_GET['payment_method'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$search = $_GET['search'] ?? '';

try {
    $db = Database::getInstance();
    
    // Build query conditions (same as expenses.php)
    $conditions = [];
    $params = [];
    
    if (!empty($status)) {
        $conditions[] = "e.status = ?";
        $params[] = $status;
    }
    
    if (!empty($category)) {
        $conditions[] = "e.category_id = ?";
        $params[] = $category;
    }
    
    if (!empty($payment_method)) {
        $conditions[] = "e.payment_method = ?";
        $params[] = $payment_method;
    }
    
    if (!empty($start_date)) {
        $conditions[] = "e.expense_date >= ?";
        $params[] = $start_date;
    }
    
    if (!empty($end_date)) {
        $conditions[] = "e.expense_date <= ?";
        $params[] = $end_date;
    }
    
    if (!empty($search)) {
        $conditions[] = "(e.transaction_id LIKE ? OR e.vendor_name LIKE ? OR e.description LIKE ? OR e.reference_number LIKE ?)";
        $searchTerm = "%{$search}%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    
    $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
    
    // Get expense records for export
    $query = "
        SELECT 
            e.expense_date as 'Date',
            e.transaction_id as 'Transaction ID',
            ec.name as 'Category',
            e.amount as 'Amount (KES)',
            e.vendor_name as 'Vendor/Payee',
            e.vendor_contact as 'Vendor Contact',
            e.payment_method as 'Payment Method',
            e.reference_number as 'Reference Number',
            e.description as 'Description',
            e.receipt_number as 'Receipt/Invoice Number',
            e.status as 'Status',
            CONCAT(u1.first_name, ' ', u1.last_name) as 'Requested By',
            e.created_at as 'Request Date',
            CONCAT(u2.first_name, ' ', u2.last_name) as 'Approved By',
            e.approval_date as 'Approval Date',
            CONCAT(u3.first_name, ' ', u3.last_name) as 'Paid By',
            e.payment_date as 'Payment Date',
            ev.name as 'Related Event'
        FROM expenses e
        JOIN expense_categories ec ON e.category_id = ec.id
        LEFT JOIN users u1 ON e.requested_by = u1.id
        LEFT JOIN users u2 ON e.approved_by = u2.id
        LEFT JOIN users u3 ON e.paid_by = u3.id
        LEFT JOIN events ev ON e.event_id = ev.id
        {$whereClause}
        ORDER BY e.expense_date DESC, e.created_at DESC
    ";
    
    $stmt = $db->executeQuery($query, $params);
    $data = $stmt->fetchAll();
    
    // Get headers
    $headers = [];
    if (!empty($data)) {
        $headers = array_keys($data[0]);
    }
    
    // Generate filename
    $dateRange = '';
    if (!empty($start_date) || !empty($end_date)) {
        $dateRange = '_' . ($start_date ?: 'start') . '_to_' . ($end_date ?: 'end');
    }
    $filename = 'expense_records' . $dateRange . '_' . date('Y-m-d');
    
    // Export based on format
    switch ($format) {
        case 'excel':
            exportExpensesToExcel($data, $headers, $filename . '.xlsx');
            break;
        case 'pdf':
            exportExpensesToPDF($data, $headers, $filename . '.pdf');
            break;
        case 'csv':
        default:
            exportToCSV($data, $headers, $filename . '.csv');
            break;
    }
    
} catch (Exception $e) {
    error_log("Export expenses error: " . $e->getMessage());
    die('Export failed: ' . $e->getMessage());
}

/**
 * Export expenses to Excel format
 */
function exportExpensesToExcel($data, $headers, $filename) {
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $output = fopen('php://output', 'w');
    
    // Write BOM for UTF-8
    fputs($output, "\xEF\xBB\xBF");
    
    // Write church header
    $churchInfo = getRecord('church_info', 'id', 1);
    fputcsv($output, [$churchInfo['church_name'] ?? 'Deliverance Church']);
    fputcsv($output, ['Expense Records Report']);
    fputcsv($output, ['Generated on: ' . date('d/m/Y H:i:s')]);
    fputcsv($output, ['']); // Empty row
    
    // Write headers
    fputcsv($output, $headers);
    
    // Write data with formatting
    $totalAmount = 0;
    foreach ($data as $row) {
        $formattedRow = [];
        foreach ($row as $key => $value) {
            if (strpos($key, 'Amount') !== false) {
                $totalAmount += (float) $value;
                $formattedRow[] = number_format($value, 2);
            } elseif (strpos($key, 'Date') !== false && !empty($value) && $value !== '0000-00-00 00:00:00') {
                $formattedRow[] = date('d/m/Y', strtotime($value));
            } else {
                $formattedRow[] = $value;
            }
        }
        fputcsv($output, $formattedRow);
    }
    
    // Add summary row
    fputcsv($output, ['']); // Empty row
    $summaryRow = array_fill(0, count($headers), '');
    $summaryRow[0] = 'TOTAL EXPENSES';
    foreach ($headers as $index => $header) {
        if (strpos($header, 'Amount') !== false) {
            $summaryRow[$index] = number_format($totalAmount, 2);
            break;
        }
    }
    fputcsv($output, $summaryRow);
    
    fclose($output);
    exit;
}

/**
 * Export expenses to PDF format
 */
function exportExpensesToPDF($data, $headers, $filename) {
    $churchInfo = getRecord('church_info', 'id', 1);
    
    header('Content-Type: text/html');
    
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Expense Records Report</title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 11px; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #03045e; padding-bottom: 20px; }
            .church-name { color: #03045e; font-size: 18px; font-weight: bold; margin-bottom: 5px; }
            .report-title { color: #ff2400; font-size: 14px; margin-bottom: 10px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 9px; }
            th, td { border: 1px solid #ddd; padding: 4px; text-align: left; }
            th { background-color: #03045e; color: white; font-weight: bold; }
            .amount { text-align: right; font-weight: bold; }
            .total-row { background-color: #f8f9fa; font-weight: bold; }
            .footer { margin-top: 30px; text-align: center; font-size: 9px; color: #666; }
            @media print {
                body { margin: 0; }
                table { page-break-inside: auto; }
                tr { page-break-inside: avoid; page-break-after: auto; }
            }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="church-name">' . htmlspecialchars($churchInfo['church_name'] ?? 'Deliverance Church') . '</div>
            <div class="report-title">Expense Records Report</div>
            <div>Period: ' . (!empty($_GET['start_date']) ? formatDisplayDate($_GET['start_date']) : 'All Time') . 
            (!empty($_GET['end_date']) ? ' to ' . formatDisplayDate($_GET['end_date']) : '') . '</div>
            <div>Generated on: ' . date('d/m/Y H:i:s') . ' by ' . htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']) . '</div>
        </div>
        
        <table>
            <thead>
                <tr>';
    
    foreach ($headers as $header) {
        echo '<th>' . htmlspecialchars($header) . '</th>';
    }
    
    echo '</tr>
            </thead>
            <tbody>';
    
    $totalAmount = 0;
    foreach ($data as $row) {
        echo '<tr>';
        foreach ($row as $key => $value) {
            $class = '';
            if (strpos($key, 'Amount') !== false) {
                $class = ' class="amount"';
                $totalAmount += (float) $value;
                $value = number_format($value, 2);
            } elseif (strpos($key, 'Date') !== false && !empty($value) && $value !== '0000-00-00 00:00:00') {
                $value = date('d/m/Y', strtotime($value));
            }
            echo '<td' . $class . '>' . htmlspecialchars($value) . '</td>';
        }
        echo '</tr>';
    }
    
    // Add total row
    echo '<tr class="total-row">';
    foreach ($headers as $header) {
        if (strpos($header, 'Amount') !== false) {
            echo '<td class="amount">' . number_format($totalAmount, 2) . '</td>';
        } elseif ($header === array_values($headers)[0]) {
            echo '<td><strong>TOTAL EXPENSES</strong></td>';
        } else {
            echo '<td></td>';
        }
    }
    echo '</tr>';
    
    echo '</tbody>
        </table>
        
        <div class="footer">
            <p>Report Summary: ' . count($data) . ' expense records | Total Amount: KES ' . number_format($totalAmount, 2) . '</p>
            <p>' . htmlspecialchars($churchInfo['church_name'] ?? 'Deliverance Church') . ' - Financial Management System</p>
        </div>
        
        <script>
            window.onload = function() {
                setTimeout(function() {
                    window.print();
                }, 1000);
            };
        </script>
    </body>
    </html>';
    
    exit;
}
?>