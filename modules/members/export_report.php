<?php
/**
 * Export Member Reports
 * Deliverance Church Management System
 * 
 * Exports member data to PDF, Excel, or CSV format
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Ensure user is logged in
requireLogin();

// Check permissions
if (!hasPermission('members')) {
    setFlashMessage('error', 'You do not have permission to access this page');
    redirect(BASE_URL . 'modules/dashboard/');
}

// Get filter parameters
$filter_status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : 'active';
$filter_department = isset($_GET['department']) ? (int)$_GET['department'] : 0;
$filter_date_from = isset($_GET['date_from']) ? sanitizeInput($_GET['date_from']) : '';
$filter_date_to = isset($_GET['date_to']) ? sanitizeInput($_GET['date_to']) : '';
$export_format = isset($_GET['format']) ? sanitizeInput($_GET['format']) : 'pdf';

// Build query
$db = Database::getInstance();
$sql = "SELECT m.*, d.name as department_name 
        FROM members m
        LEFT JOIN member_departments md ON m.id = md.member_id AND md.is_active = TRUE
        LEFT JOIN departments d ON md.department_id = d.id
        WHERE 1=1";

$params = [];

// Apply filters
if ($filter_status) {
    $sql .= " AND m.membership_status = ?";
    $params[] = $filter_status;
}

if ($filter_department > 0) {
    $sql .= " AND md.department_id = ?";
    $params[] = $filter_department;
}

if (!empty($filter_date_from)) {
    $sql .= " AND m.join_date >= ?";
    $params[] = $filter_date_from;
}

if (!empty($filter_date_to)) {
    $sql .= " AND m.join_date <= ?";
    $params[] = $filter_date_to;
}

$sql .= " ORDER BY m.first_name, m.last_name";

try {
    $stmt = $db->executeQuery($sql, $params);
    $members = $stmt->fetchAll();
    
    // Prepare data for export
    $headers = [
        'Member #',
        'First Name',
        'Last Name',
        'Phone',
        'Email',
        'Gender',
        'Join Date',
        'Baptism Date',
        'Membership Status',
        'Department',
        'Occupation',
        'Marital Status'
    ];
    
    $data = [];
    foreach ($members as $member) {
        $data[] = [
            $member['member_number'],
            $member['first_name'],
            $member['last_name'],
            $member['phone'] ?? '-',
            $member['email'] ?? '-',
            ucfirst($member['gender']),
            formatDisplayDate($member['join_date']),
            formatDisplayDate($member['baptism_date']),
            $member['membership_status'],
            $member['department_name'] ?? '-',
            $member['occupation'] ?? '-',
            $member['marital_status'] ?? '-'
        ];
    }
    
    // Generate file based on format
    if ($export_format === 'csv') {
        exportToCSV($data, $headers, 'members_report_' . date('Ymd_His') . '.csv');
    } elseif ($export_format === 'excel') {
        $filepath = generateExcelFile($data, $headers, 'members_report_' . date('Ymd_His') . '.xlsx');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
        readfile($filepath);
        unlink($filepath);
    } elseif ($export_format === 'pdf') {
        // Generate PDF (requires external library - using simple HTML to PDF concept)
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Member Report</title>
            <style>
                body { font-family: Arial; margin: 20px; }
                h2 { color: #03045e; border-bottom: 2px solid #ff2400; padding-bottom: 10px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th { background: #03045e; color: white; padding: 10px; text-align: left; font-size: 10px; }
                td { padding: 8px; border-bottom: 1px solid #ddd; font-size: 9px; }
                tr:nth-child(even) { background: #f5f5f5; }
                .summary { margin-bottom: 20px; }
                .summary p { margin: 5px 0; }
            </style>
        </head>
        <body>
            <h2>Member Report</h2>
            <div class="summary">
                <p><strong>Generated:</strong> ' . date('d/m/Y H:i') . '</p>
                <p><strong>Total Members:</strong> ' . count($members) . '</p>
                <p><strong>Status Filter:</strong> ' . ucfirst($filter_status) . '</p>
            </div>
            <table>
                <thead>
                    <tr>';
        
        foreach ($headers as $header) {
            $html .= '<th>' . $header . '</th>';
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
                Generated by Deliverance Church Management System - ' . $churchInfo['church_name'] . '
            </p>
        </body>
        </html>';
        
        // Output as HTML for printing (can be converted to PDF via browser)
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="members_report_' . date('Ymd_His') . '.html"');
        echo $html;
    }
    
    logActivity('Export member report', 'members', null, null, ['format' => $export_format, 'filters' => [$filter_status, $filter_department, $filter_date_from, $filter_date_to]]);
    exit;
    
} catch (Exception $e) {
    error_log("Error exporting member report: " . $e->getMessage());
    setFlashMessage('error', 'Error exporting report: ' . $e->getMessage());
    redirect(BASE_URL . 'modules/members/reports.php');
}

?>