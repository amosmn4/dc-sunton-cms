<?php
/**
 * Export Attendance Records
 * Deliverance Church Management System
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

requireLogin();

if (!hasPermission('attendance')) {
    setFlashMessage('error', 'You do not have permission to access this page');
    redirect(BASE_URL . 'modules/dashboard/');
}

$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
$date_from = isset($_GET['date_from']) ? sanitizeInput($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitizeInput($_GET['date_to']) : '';
$export_format = isset($_GET['format']) ? sanitizeInput($_GET['format']) : 'csv';

$db = Database::getInstance();

try {
    // Get event details if event_id is provided
    $eventDetails = null;
    if ($event_id > 0) {
        $stmt = $db->executeQuery("SELECT * FROM events WHERE id = ?", [$event_id]);
        $eventDetails = $stmt->fetch();
    }
    
    // Build attendance query
    $sql = "SELECT 
                ar.id,
                m.member_number,
                m.first_name,
                m.last_name,
                m.phone,
                e.name as event_name,
                e.event_date,
                ar.check_in_time,
                ar.check_in_method,
                ar.is_present,
                ar.notes
            FROM attendance_records ar
            JOIN members m ON ar.member_id = m.id
            JOIN events e ON ar.event_id = e.id
            WHERE 1=1";
    
    $params = [];
    
    if ($event_id > 0) {
        $sql .= " AND ar.event_id = ?";
        $params[] = $event_id;
    }
    
    if (!empty($date_from)) {
        $sql .= " AND DATE(ar.check_in_time) >= ?";
        $params[] = $date_from;
    }
    
    if (!empty($date_to)) {
        $sql .= " AND DATE(ar.check_in_time) <= ?";
        $params[] = $date_to;
    }
    
    $sql .= " ORDER BY ar.check_in_time DESC";
    
    $stmt = $db->executeQuery($sql, $params);
    $records = $stmt->fetchAll();
    
    $headers = [
        'Member #',
        'First Name',
        'Last Name',
        'Phone',
        'Event',
        'Event Date',
        'Check-in Time',
        'Check-in Method',
        'Present',
        'Notes'
    ];
    
    $data = [];
    foreach ($records as $record) {
        $data[] = [
            $record['member_number'],
            $record['first_name'],
            $record['last_name'],
            $record['phone'] ?? '-',
            $record['event_name'],
            formatDisplayDate($record['event_date']),
            formatDisplayDateTime($record['check_in_time']),
            ucfirst(str_replace('_', ' ', $record['check_in_method'])),
            $record['is_present'] ? 'Yes' : 'No',
            $record['notes'] ?? '-'
        ];
    }
    
    if ($export_format === 'csv') {
        exportToCSV($data, $headers, 'attendance_report_' . date('Ymd_His') . '.csv');
    } elseif ($export_format === 'excel') {
        $filepath = generateExcelFile($data, $headers, 'attendance_report_' . date('Ymd_His') . '.xlsx');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
        readfile($filepath);
        unlink($filepath);
    }
    
    logActivity('Export attendance report', 'attendance_records', null, null, ['event_id' => $event_id, 'date_range' => $date_from . ' to ' . $date_to]);
    exit;
    
} catch (Exception $e) {
    error_log("Error exporting attendance: " . $e->getMessage());
    setFlashMessage('error', 'Error exporting attendance: ' . $e->getMessage());
    redirect(BASE_URL . 'modules/attendance/reports.php');
}

?>