<?php
/**
 * Export Equipment Data
 * Deliverance Church Management System
 * 
 * Exports equipment data in various formats
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Check authentication and permissions
requireLogin();
if (!hasPermission('equipment')) {
    setFlashMessage('error', 'You do not have permission to export equipment data.');
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit;
}

// Get export format
$format = isset($_GET['export']) ? strtolower($_GET['export']) : 'csv';

// Get filter parameters (same as index page)
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

try {
    $db = Database::getInstance();
    
    // Build query with filters
    $whereConditions = [];
    $params = [];
    
    if ($category_filter > 0) {
        $whereConditions[] = "e.category_id = ?";
        $params[] = $category_filter;
    }
    
    if (!empty($status_filter)) {
        $whereConditions[] = "e.status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($search)) {
        $whereConditions[] = "(e.name LIKE ? OR e.equipment_code LIKE ? OR e.brand LIKE ? OR e.model LIKE ?)";
        $searchTerm = "%{$search}%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Get equipment data
    $query = "SELECT e.equipment_code,
                     e.name,
                     ec.name as category,
                     e.brand,
                     e.model,
                     e.serial_number,
                     e.status,
                     e.location,
                     CONCAT(m.first_name, ' ', m.last_name) as responsible_person,
                     e.purchase_date,
                     e.purchase_price,
                     e.warranty_expiry,
                     e.supplier_name,
                     e.supplier_contact,
                     e.last_maintenance_date,
                     e.next_maintenance_date,
                     e.maintenance_interval_days,
                     e.condition_notes,
                     e.created_at,
                     CASE 
                        WHEN e.next_maintenance_date < CURDATE() THEN 'Overdue'
                        WHEN e.next_maintenance_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'Due Soon'
                        ELSE 'OK'
                     END as maintenance_status
              FROM equipment e
              LEFT JOIN equipment_categories ec ON e.category_id = ec.id
              LEFT JOIN members m ON e.responsible_person_id = m.id
              {$whereClause}
              ORDER BY e.name";
    
    $stmt = $db->executeQuery($query, $params);
    $equipment = $stmt->fetchAll();
    
    if (empty($equipment)) {
        setFlashMessage('warning', 'No equipment data to export with current filters.');
        header('Location: ' . BASE_URL . 'modules/equipment/');
        exit;
    }
    
    // Prepare headers
    $headers = [
        'Equipment Code',
        'Name',
        'Category',
        'Brand',
        'Model',
        'Serial Number',
        'Status',
        'Location',
        'Responsible Person',
        'Purchase Date',
        'Purchase Price',
        'Warranty Expiry',
        'Supplier Name',
        'Supplier Contact',
        'Last Maintenance',
        'Next Maintenance',
        'Maintenance Interval (Days)',
        'Maintenance Status',
        'Condition Notes',
        'Date Added'
    ];
    
    // Prepare data rows
    $data = [];
    foreach ($equipment as $item) {
        $data[] = [
            $item['equipment_code'],
            $item['name'],
            $item['category'] ?: 'No Category',
            $item['brand'] ?: '',
            $item['model'] ?: '',
            $item['serial_number'] ?: '',
            EQUIPMENT_STATUS[$item['status']] ?? ucfirst($item['status']),
            $item['location'] ?: '',
            $item['responsible_person'] ?: 'Not Assigned',
            $item['purchase_date'] && $item['purchase_date'] !== '0000-00-00' ? formatDisplayDate($item['purchase_date']) : '',
            $item['purchase_price'] ? formatCurrency($item['purchase_price']) : '',
            $item['warranty_expiry'] && $item['warranty_expiry'] !== '0000-00-00' ? formatDisplayDate($item['warranty_expiry']) : '',
            $item['supplier_name'] ?: '',
            $item['supplier_contact'] ?: '',
            $item['last_maintenance_date'] && $item['last_maintenance_date'] !== '0000-00-00' ? formatDisplayDate($item['last_maintenance_date']) : 'Never',
            $item['next_maintenance_date'] && $item['next_maintenance_date'] !== '0000-00-00' ? formatDisplayDate($item['next_maintenance_date']) : 'Not Scheduled',
            $item['maintenance_interval_days'] ?: '365',
            $item['maintenance_status'],
            $item['condition_notes'] ?: '',
            formatDisplayDateTime($item['created_at'])
        ];
    }
    
    $filename = 'equipment_export_' . date('Y-m-d_H-i-s');
    
    switch ($format) {
        case 'excel':
        case 'xlsx':
            exportToExcel($data, $headers, $filename);
            break;
            
        case 'pdf':
            exportToPDF($data, $headers, $filename);
            break;
            
        case 'csv':
        default:
            exportToCSV($data, $headers, $filename . '.csv');
            break;
    }
    
} catch (Exception $e) {
    error_log("Equipment export error: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred while exporting equipment data.');
    header('Location: ' . BASE_URL . 'modules/equipment/');
    exit;
}

/**
 * Export to Excel format
 */
function exportToExcel($data, $headers, $filename) {
    // Create a simple HTML table that Excel can read
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    
    echo '<html>';
    echo '<head><meta charset="UTF-8"></head>';
    echo '<body>';
    echo '<table border="1">';
    
    // Headers
    echo '<tr style="background-color: #03045e; color: white; font-weight: bold;">';
    foreach ($headers as $header) {
        echo '<td>' . htmlspecialchars($header) . '</td>';
    }
    echo '</tr>';
    
    // Data
    foreach ($data as $row) {
        echo '<tr>';
        foreach ($row as $cell) {
            echo '<td>' . htmlspecialchars($cell) . '</td>';
        }
        echo '</tr>';
    }
    
    echo '</table>';
    echo '</body></html>';
}

/**
 * Export to PDF format
 */
function exportToPDF($data, $headers, $filename) {
    // Simple HTML to PDF conversion
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
    
    // For a full PDF implementation, you would use a library like TCPDF or mPDF
    // This is a simplified version that creates an HTML document
    
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Equipment Export</title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 12px; }
            table { border-collapse: collapse; width: 100%; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #03045e; color: white; }
            .header { text-align: center; margin-bottom: 20px; }
            .footer { margin-top: 20px; font-size: 10px; color: #666; }
        </style>
    </head>
    <body>
        <div class="header">
            <h2>Equipment Inventory Report</h2>
            <p>Generated on ' . date('Y-m-d H:i:s') . '</p>
            <p>Total Items: ' . count($data) . '</p>
        </div>
        
        <table>
            <thead>
                <tr>';
    
    foreach ($headers as $header) {
        $html .= '<th>' . htmlspecialchars($header) . '</th>';
    }
    
    $html .= '      </tr>
            </thead>
            <tbody>';
    
    foreach ($data as $row) {
        $html .= '<tr>';
        foreach ($row as $cell) {
            $html .= '<td>' . htmlspecialchars($cell) . '</td>';
        }
        $html .= '</tr>';
    }
    
    $html .= '      </tbody>
        </table>
        
        <div class="footer">
            <p>Exported from Deliverance Church Management System</p>
        </div>
    </body>
    </html>';
    
    // In a real implementation, you would convert this HTML to PDF
    // For now, we'll output HTML with PDF headers
    echo $html;
}
?>