<?php
/**
 * Export Visitors Data
 * Deliverance Church Management System
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Check authentication and permissions
requireLogin();
if (!hasPermission('visitors')) {
    setFlashMessage('error', 'You do not have permission to access this page.');
    redirect(BASE_URL . 'modules/dashboard/');
}

// Get export parameters
$format = isset($_GET['format']) ? strtolower($_GET['format']) : 'excel';
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$date_from = isset($_GET['date_from']) ? sanitizeInput($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitizeInput($_GET['date_to']) : '';

// Validate format
$allowedFormats = ['excel', 'csv', 'pdf'];
if (!in_array($format, $allowedFormats)) {
    $format = 'excel';
}

try {
    $db = Database::getInstance();
    
    // Build query conditions (same as index.php)
    $conditions = [];
    $params = [];

    if (!empty($search)) {
        $conditions[] = "(first_name LIKE ? OR last_name LIKE ? OR phone LIKE ? OR email LIKE ?)";
        $searchTerm = "%$search%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }

    if (!empty($status_filter)) {
        $conditions[] = "status = ?";
        $params[] = $status_filter;
    }

    if (!empty($date_from)) {
        $conditions[] = "visit_date >= ?";
        $params[] = $date_from;
    }

    if (!empty($date_to)) {
        $conditions[] = "visit_date <= ?";
        $params[] = $date_to;
    }

    $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
    
    // Get visitors data with follow-up person info
    $sql = "SELECT v.visitor_number,
                   v.first_name,
                   v.last_name,
                   v.phone,
                   v.email,
                   v.address,
                   v.age_group,
                   v.gender,
                   v.visit_date,
                   v.service_attended,
                   v.how_heard_about_us,
                   v.purpose_of_visit,
                   v.areas_of_interest,
                   v.previous_church,
                   v.status,
                   CONCAT(m.first_name, ' ', m.last_name) as followup_person_name,
                   v.notes,
                   v.created_at
            FROM visitors v 
            LEFT JOIN members m ON v.assigned_followup_person_id = m.id 
            $whereClause 
            ORDER BY v.visit_date DESC, v.created_at DESC";
    
    $stmt = $db->executeQuery($sql, $params);
    $visitors = $stmt->fetchAll();
    
    if (empty($visitors)) {
        setFlashMessage('error', 'No visitors found matching your criteria.');
        redirect('index.php');
    }
    
    // Prepare headers for export
    $headers = [
        'Visitor Number',
        'First Name',
        'Last Name', 
        'Phone',
        'Email',
        'Address',
        'Age Group',
        'Gender',
        'Visit Date',
        'Service Attended',
        'How They Heard',
        'Purpose of Visit',
        'Areas of Interest',
        'Previous Church',
        'Status',
        'Follow-up Person',
        'Notes',
        'Date Added'
    ];
    
    // Prepare data for export
    $exportData = [];
    foreach ($visitors as $visitor) {
        $exportData[] = [
            $visitor['visitor_number'],
            $visitor['first_name'],
            $visitor['last_name'],
            $visitor['phone'] ?: '',
            $visitor['email'] ?: '',
            $visitor['address'] ?: '',
            VISITOR_AGE_GROUPS[$visitor['age_group']] ?? ucfirst($visitor['age_group']),
            GENDER_OPTIONS[$visitor['gender']] ?? ucfirst($visitor['gender']),
            formatDisplayDate($visitor['visit_date']),
            $visitor['service_attended'] ?: '',
            ucwords(str_replace('_', ' ', $visitor['how_heard_about_us'] ?: '')),
            $visitor['purpose_of_visit'] ?: '',
            $visitor['areas_of_interest'] ?: '',
            $visitor['previous_church'] ?: '',
            VISITOR_STATUS[$visitor['status']] ?? ucfirst($visitor['status']),
            $visitor['followup_person_name'] ?: 'Not assigned',
            $visitor['notes'] ?: '',
            formatDisplayDateTime($visitor['created_at'])
        ];
    }
    
    // Generate filename
    $timestamp = date('Y-m-d_H-i-s');
    $filterSuffix = '';
    if (!empty($search) || !empty($status_filter) || !empty($date_from) || !empty($date_to)) {
        $filterSuffix = '_filtered';
    }
    
    switch ($format) {
        case 'csv':
            exportToCSV($exportData, $headers, "visitors_export{$filterSuffix}_{$timestamp}.csv");
            break;
            
        case 'excel':
            exportToExcel($exportData, $headers, "visitors_export{$filterSuffix}_{$timestamp}.xlsx");
            break;
            
        case 'pdf':
            exportToPDF($exportData, $headers, "visitors_export{$filterSuffix}_{$timestamp}.pdf");
            break;
    }
    
} catch (Exception $e) {
    error_log("Error exporting visitors: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred while exporting data: ' . $e->getMessage());
    redirect('index.php');
}

/**
 * Export data to CSV format
 */
function exportToCSV($data, $headers, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Write headers
    fputcsv($output, $headers);
    
    // Write data
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

/**
 * Export data to Excel format (basic CSV with Excel headers)
 */
function exportToExcel($data, $headers, $filename) {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    // Create a simple HTML table that Excel can interpret
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"><title>Visitors Export</title></head>';
    echo '<body>';
    
    echo '<h2>Visitors Export - ' . date('Y-m-d H:i:s') . '</h2>';
    echo '<table border="1">';
    
    // Headers
    echo '<tr style="background-color: #03045e; color: white; font-weight: bold;">';
    foreach ($headers as $header) {
        echo '<th>' . htmlspecialchars($header) . '</th>';
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
    
    exit;
}

/**
 * Export data to PDF format (basic HTML to PDF)
 */
function exportToPDF($data, $headers, $filename) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    // For a basic implementation, we'll create an HTML page optimized for PDF printing
    // In a production environment, you might want to use a library like mPDF or TCPDF
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Visitors Export</title>
        <style>
            body { 
                font-family: Arial, sans-serif; 
                font-size: 12px; 
                margin: 20px;
            }
            table { 
                width: 100%; 
                border-collapse: collapse; 
                margin-top: 20px;
            }
            th, td { 
                border: 1px solid #333; 
                padding: 5px; 
                text-align: left;
                word-wrap: break-word;
            }
            th { 
                background-color: #03045e; 
                color: white; 
                font-weight: bold;
            }
            tr:nth-child(even) { 
                background-color: #f2f2f2; 
            }
            .header {
                text-align: center;
                margin-bottom: 20px;
            }
            .export-info {
                margin-bottom: 10px;
                font-size: 10px;
                color: #666;
            }
            @media print {
                body { margin: 0; }
                table { font-size: 10px; }
                th, td { padding: 3px; }
            }
        </style>
        <script>
            window.onload = function() {
                window.print();
            }
        </script>
    </head>
    <body>
        <div class="header">
            <h1>Deliverance Church - Visitors Export</h1>
            <div class="export-info">
                Generated on: <?php echo date('F j, Y \a\t g:i A'); ?><br>
                Total Records: <?php echo count($data); ?><br>
                <?php if (!empty($_GET['search']) || !empty($_GET['status']) || !empty($_GET['date_from']) || !empty($_GET['date_to'])): ?>
                    Filters Applied: 
                    <?php 
                    $filters = [];
                    if (!empty($_GET['search'])) $filters[] = "Search: " . htmlspecialchars($_GET['search']);
                    if (!empty($_GET['status'])) $filters[] = "Status: " . htmlspecialchars($_GET['status']);
                    if (!empty($_GET['date_from'])) $filters[] = "From: " . htmlspecialchars($_GET['date_from']);
                    if (!empty($_GET['date_to'])) $filters[] = "To: " . htmlspecialchars($_GET['date_to']);
                    echo implode(', ', $filters);
                    ?>
                <?php endif; ?>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <?php foreach ($headers as $header): ?>
                        <th><?php echo htmlspecialchars($header); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $row): ?>
                    <tr>
                        <?php foreach ($row as $cell): ?>
                            <td><?php echo htmlspecialchars($cell); ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div style="margin-top: 30px; font-size: 10px; color: #666; text-align: center;">
            <p>This report was generated by the Deliverance Church Management System</p>
            <p>For questions about this data, please contact the church administration</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>