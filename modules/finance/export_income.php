<?php
/**
 * Export Income Data
 * Deliverance Church Management System
 * 
 * Export income records in various formats (Excel, CSV, PDF)
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
    
    // Build query conditions (same as income.php)
    $conditions = [];
    $params = [];
    
    if (!empty($status)) {
        $conditions[] = "i.status = ?";
        $params[] = $status;
    }
    
    if (!empty($category)) {
        $conditions[] = "i.category_id = ?";
        $params[] = $category;
    }
    
    if (!empty($payment_method)) {
        $conditions[] = "i.payment_method = ?";
        $params[] = $payment_method;
    }
    
    if (!empty($start_date)) {
        $conditions[] = "i.transaction_date >= ?";
        $params[] = $start_date;
    }
    
    if (!empty($end_date)) {
        $conditions[] = "i.transaction_date <= ?";
        $params[] = $end_date;
    }
    
    if (!empty($search)) {
        $conditions[] = "(i.transaction_id LIKE ? OR i.donor_name LIKE ? OR i.description LIKE ? OR i.reference_number LIKE ?)";
        $searchTerm = "%{$search}%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    
    $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
    
    // Get income records for export
    $query = "
        SELECT 
            i.transaction_date as 'Date',
            i.transaction_id as 'Transaction ID',
            ic.name as 'Category',
            i.amount as 'Amount (KES)',
            i.donor_name as 'Donor Name',
            i.donor_phone as 'Donor Phone',
            i.donor_email as 'Donor Email',
            i.payment_method as 'Payment Method',
            i.reference_number as 'Reference Number',
            i.description as 'Description',
            i.source as 'Source',
            i.receipt_number as 'Receipt Number',
            i.status as 'Status',
            CASE WHEN i.is_anonymous = 1 THEN 'Yes' ELSE 'No' END as 'Anonymous',
            CASE WHEN i.is_pledge = 1 THEN 'Yes' ELSE 'No' END as 'Pledge',
            i.pledge_period as 'Pledge Period',
            CONCAT(u.first_name, ' ', u.last_name) as 'Recorded By',
            i.created_at as 'Date Created'
        FROM income i
        JOIN income_categories ic ON i.category_id = ic.id
        LEFT JOIN users u ON i.recorded_by = u.id
        {$whereClause}
        ORDER BY i.transaction_date DESC, i.created_at DESC
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
    $filename = 'income_records' . $dateRange . '_' . date('Y-m-d');
    
    // Export based on format
    switch ($format) {
        case 'excel':
            exportToExcel($data, $headers, $filename . '.xlsx');
            break;
        case 'pdf':
            exportToPDF($data, $headers, $filename . '.pdf', 'Income Records Report');
            break;
        case 'csv':
        default:
            exportToCSV($data, $headers, $filename . '.csv');
            break;
    }
    
} catch (Exception $e) {
    error_log("Export income error: " . $e->getMessage());
    die('Export failed: ' . $e->getMessage());
}

/**
 * Export data to Excel format
 */
function exportToExcel($data, $headers, $filename) {
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    // Create simple Excel-like CSV with proper formatting
    $output = fopen('php://output', 'w');
    
    // Write BOM for UTF-8
    fputs($output, "\xEF\xBB\xBF");
    
    // Write headers
    fputcsv($output, $headers);
    
    // Write data with proper formatting
    foreach ($data as $row) {
        $formattedRow = [];
        foreach ($row as $key => $value) {
            if (strpos($key, 'Amount') !== false) {
                $formattedRow[] = number_format($value, 2);
            } elseif (strpos($key, 'Date') !== false) {
                $formattedRow[] = date('d/m/Y', strtotime($value));
            } else {
                $formattedRow[] = $value;
            }
        }
        fputcsv($output, $formattedRow);
    }
    
    fclose($output);
    exit;
}

/**
 * Export data to PDF format
 */
function exportToPDF($data, $headers, $filename, $title) {
    // For now, we'll create a simple HTML-to-PDF export
    // In production, consider using libraries like mPDF or TCPDF
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Simple HTML to PDF conversion (basic implementation)
    $html = generatePDFHTML($data, $headers, $title);
    
    // This is a basic implementation - consider using proper PDF libraries
    echo $html;
    exit;
}

/**
 * Generate HTML for PDF export
 */
function generatePDFHTML($data, $headers, $title) {
    $churchInfo = getRecord('church_info', 'id', 1);
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>' . htmlspecialchars($title) . '</title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 12px; }
            .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #03045e; padding-bottom: 20px; }
            .church-name { color: #03045e; font-size: 20px; font-weight: bold; margin-bottom: 5px; }
            .report-title { color: #ff2400; font-size: 16px; margin-bottom: 10px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #03045e; color: white; font-weight: bold; }
            .amount { text-align: right; font-weight: bold; }
            .total-row { background-color: #f8f9fa; font-weight: bold; }
            .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #666; }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="church-name">' . htmlspecialchars($churchInfo['church_name'] ?? 'Deliverance Church') . '</div>
            <div class="report-title">' . htmlspecialchars($title) . '</div>
            <div>Generated on: ' . date('d/m/Y H:i:s') . '</div>
        </div>
        
        <table>
            <thead>
                <tr>';
    
    foreach ($headers as $header) {
        $html .= '<th>' . htmlspecialchars($header) . '</th>';
    }
    
    $html .= '</tr>
            </thead>
            <tbody>';
    
    $totalAmount = 0;
    foreach ($data as $row) {
        $html .= '<tr>';
        foreach ($row as $key => $value) {
            $class = '';
            if (strpos($key, 'Amount') !== false) {
                $class = ' class="amount"';
                $totalAmount += (float) $value;
                $value = number_format($value, 2);
            } elseif (strpos($key, 'Date') !== false) {
                $value = date('d/m/Y', strtotime($value));
            }
            $html .= '<td' . $class . '>' . htmlspecialchars($value) . '</td>';
        }
        $html .= '</tr>';
    }
    
    // Add total row
    $html .= '<tr class="total-row">';
    foreach ($headers as $header) {
        if (strpos($header, 'Amount') !== false) {
            $html .= '<td class="amount">' . number_format($totalAmount, 2) . '</td>';
        } elseif ($header === array_values($headers)[0]) {
            $html .= '<td><strong>TOTAL</strong></td>';
        } else {
            $html .= '<td></td>';
        }
    }
    $html .= '</tr>';
    
    $html .= '</tbody>
        </table>
        
        <div class="footer">
            <p>This report was generated by ' . htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']) . ' on ' . date('d/m/Y H:i:s') . '</p>
            <p>' . htmlspecialchars($churchInfo['church_name'] ?? 'Deliverance Church') . ' - Church Management System</p>
        </div>
    </body>
    </html>';
    
    return $html;
}
?>