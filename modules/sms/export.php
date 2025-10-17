<?php
/**
 * Export Visitor Records
 * Deliverance Church Management System
 * 
 * Handles both export_visitors.php and export.php with the same logic
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

requireLogin();

if (!hasPermission('visitors')) {
    setFlashMessage('error', 'You do not have permission to access this page');
    redirect(BASE_URL . 'modules/dashboard/');
}

$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$date_from = isset($_GET['date_from']) ? sanitizeInput($_GET['date_from']) : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? sanitizeInput($_GET['date_to']) : date('Y-m-d');
$export_type = isset($_GET['export_type']) ? sanitizeInput($_GET['export_type']) : 'basic';
$export_format = isset($_GET['format']) ? sanitizeInput($_GET['format']) : 'csv';

$db = Database::getInstance();
$churchInfo = getRecord('church_info', 'id', 1);

try {
    if ($export_type === 'basic') {
        // Basic visitor export
        $headers = [
            'Visitor #',
            'First Name',
            'Last Name',
            'Phone',
            'Email',
            'Age Group',
            'Gender',
            'Visit Date',
            'Service Attended',
            'Status',
            'How They Heard'
        ];
        
        $sql = "SELECT 
                    visitor_number,
                    first_name,
                    last_name,
                    phone,
                    email,
                    age_group,
                    gender,
                    visit_date,
                    service_attended,
                    status,
                    how_heard_about_us
                FROM visitors
                WHERE visit_date BETWEEN ? AND ?";
        
        $params = [$date_from, $date_to];
        
        if (!empty($status_filter)) {
            $sql .= " AND status = ?";
            $params[] = $status_filter;
        }
        
        $sql .= " ORDER BY visit_date DESC";
        
    } elseif ($export_type === 'detailed') {
        // Detailed visitor export with follow-up info
        $headers = [
            'Visitor #',
            'Name',
            'Phone',
            'Email',
            'Address',
            'Visit Date',
            'Age Group',
            'Purpose',
            'Areas of Interest',
            'Previous Church',
            'Status',
            'Assigned To',
            'Follow-ups',
            'Last Follow-up'
        ];
        
        $sql = "SELECT 
                    v.visitor_number,
                    CONCAT(v.first_name, ' ', v.last_name) as name,
                    v.phone,
                    v.email,
                    v.address,
                    v.visit_date,
                    v.age_group,
                    v.purpose_of_visit,
                    v.areas_of_interest,
                    v.previous_church,
                    v.status,
                    CONCAT(m.first_name, ' ', m.last_name) as assigned_person,
                    (SELECT COUNT(*) FROM visitor_followups WHERE visitor_id = v.id) as followup_count,
                    (SELECT MAX(followup_date) FROM visitor_followups WHERE visitor_id = v.id) as last_followup
                FROM visitors v
                LEFT JOIN members m ON v.assigned_followup_person_id = m.id
                WHERE v.visit_date BETWEEN ? AND ?";
        
        $params = [$date_from, $date_to];
        
        if (!empty($status_filter)) {
            $sql .= " AND v.status = ?";
            $params[] = $status_filter;
        }
        
        $sql .= " ORDER BY v.visit_date DESC";
    }
    
    $stmt = $db->executeQuery($sql, $params);
    $records = $stmt->fetchAll();
    
    $data = [];
    
    if ($export_type === 'basic') {
        foreach ($records as $record) {
            $data[] = [
                $record['visitor_number'],
                $record['first_name'],
                $record['last_name'],
                $record['phone'] ?? '-',
                $record['email'] ?? '-',
                ucfirst($record['age_group']),
                ucfirst($record['gender']),
                formatDisplayDate($record['visit_date']),
                $record['service_attended'] ?? '-',
                ucfirst(str_replace('_', ' ', $record['status'])),
                $record['how_heard_about_us'] ?? '-'
            ];
        }
    } elseif ($export_type === 'detailed') {
        foreach ($records as $record) {
            $data[] = [
                $record['visitor_number'],
                $record['name'],
                $record['phone'] ?? '-',
                $record['email'] ?? '-',
                $record['address'] ?? '-',
                formatDisplayDate($record['visit_date']),
                ucfirst($record['age_group']),
                $record['purpose_of_visit'] ?? '-',
                $record['areas_of_interest'] ?? '-',
                $record['previous_church'] ?? '-',
                ucfirst(str_replace('_', ' ', $record['status'])),
                $record['assigned_person'] ?? '-',
                $record['followup_count'],
                formatDisplayDate($record['last_followup'])
            ];
        }
    }
    
    // Export
    $filename = 'visitors_report_' . $export_type . '_' . date('Ymd_His');
    
    if ($export_format === 'csv') {
        exportToCSV($data, $headers, $filename . '.csv');
    } elseif ($export_format === 'excel') {
        $filepath = generateExcelFile($data, $headers, $filename . '.xlsx');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
        readfile($filepath);
        unlink($filepath);
    } elseif ($export_format === 'pdf') {
        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Visitor Report</title>
            <style>
                body { font-family: Arial; margin: 20px; }
                h2 { color: #03045e; border-bottom: 2px solid #ff2400; padding-bottom: 10px; }
                .meta { color: #666; font-size: 12px; margin-bottom: 20px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th { background: #03045e; color: white; padding: 10px; text-align: left; font-size: 10px; font-weight: bold; }
                td { padding: 8px; border-bottom: 1px solid #ddd; font-size: 9px; }
                tr:nth-child(even) { background: #f9f9f9; }
            </style>
        </head>
        <body>
            <h2>Visitor Report</h2>
            <div class="meta">
                <p><strong>Period:</strong> ' . formatDisplayDate($date_from) . ' to ' . formatDisplayDate($date_to) . '</p>
                <p><strong>Total Visitors:</strong> ' . count($records) . '</p>
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
                Generated by ' . htmlspecialchars($churchInfo['church_name']) . ' - Church Management System
            </p>
        </body>
        </html>';
        
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.html"');
        echo $html;
    }
    
    logActivity('Export visitor report', 'visitors', null, null, ['type' => $export_type, 'format' => $export_format, 'status' => $status_filter]);
    exit;
    
} catch (Exception $e) {
    error_log("Error exporting visitor report: " . $e->getMessage());
    setFlashMessage('error', 'Error exporting report: ' . $e->getMessage());
    redirect(BASE_URL . 'modules/visitors/');
}

?>