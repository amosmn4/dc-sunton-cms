<?php
/**
 * Attendance API Endpoints
 * Deliverance Church Management System
 * 
 * Handles AJAX requests for attendance management
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Check authentication
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$db = Database::getInstance();

try {
    switch ($action) {
        case 'get_stats':
            handleGetStats();
            break;
            
        case 'get_event_details':
            handleGetEventDetails();
            break;
            
        case 'get_attendance':
            handleGetAttendance();
            break;
            
        case 'preview_import':
            handlePreviewImport();
            break;
            
        case 'export_attendance':
            handleExportAttendance();
            break;
            
        case 'get_history':
            handleGetHistory();
            break;
            
        case 'update_attendance_status':
            handleUpdateAttendanceStatus();
            break;
            
        case 'get_member_attendance':
            handleGetMemberAttendance();
            break;
            
        case 'bulk_update_attendance':
            handleBulkUpdateAttendance();
            break;
            
        case 'generate_qr_code':
            handleGenerateQRCode();
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    error_log("Attendance API error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Get attendance statistics
 */
function handleGetStats() {
    global $db;
    
    // Today's attendance
    $todayStats = $db->executeQuery("
        SELECT 
            COALESCE(SUM(ac.count_number), 0) as total_attendance,
            COUNT(DISTINCT ar.member_id) as members_present
        FROM events e
        LEFT JOIN attendance_counts ac ON e.id = ac.event_id AND ac.attendance_category = 'total'
        LEFT JOIN attendance_records ar ON e.id = ar.event_id AND ar.is_present = 1
        WHERE DATE(e.event_date) = CURDATE()
    ")->fetch();
    
    // Weekly average
    $weeklyAvg = $db->executeQuery("
        SELECT ROUND(AVG(daily_total), 0) as weekly_average
        FROM (
            SELECT 
                e.event_date,
                COALESCE(SUM(ac.count_number), COUNT(ar.member_id)) as daily_total
            FROM events e
            LEFT JOIN attendance_counts ac ON e.id = ac.event_id AND ac.attendance_category = 'total'
            LEFT JOIN attendance_records ar ON e.id = ar.event_id AND ar.is_present = 1
            WHERE e.event_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY e.event_date
        ) daily_stats
    ")->fetch();
    
    // Monthly events
    $monthlyEvents = $db->executeQuery("
        SELECT COUNT(*) as event_count
        FROM events 
        WHERE MONTH(event_date) = MONTH(CURDATE()) 
        AND YEAR(event_date) = YEAR(CURDATE())
    ")->fetch();
    
    // Regular attendees
    $regularAttendees = $db->executeQuery("
        SELECT COUNT(DISTINCT m.id) as regular_count
        FROM members m
        WHERE (
            SELECT COUNT(*)
            FROM attendance_records ar
            JOIN events e ON ar.event_id = e.id
            WHERE ar.member_id = m.id 
            AND ar.is_present = 1
            AND e.event_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ) >= 3
        AND m.membership_status = 'active'
    ")->fetch();
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'today_attendance' => $todayStats['total_attendance'] ?: 0,
            'members_present' => $todayStats['members_present'] ?: 0,
            'weekly_average' => $weeklyAvg['weekly_average'] ?: 0,
            'monthly_events' => $monthlyEvents['event_count'] ?: 0,
            'regular_attendees' => $regularAttendees['regular_count'] ?: 0
        ]
    ]);
}

/**
 * Get event details for modal display
 */
function handleGetEventDetails() {
    global $db;
    
    $eventId = (int) ($_GET['id'] ?? 0);
    if (!$eventId) {
        throw new Exception('Event ID required');
    }
    
    $event = $db->executeQuery("
        SELECT 
            e.*,
            d.name as department_name,
            u.first_name as created_by_name,
            u.last_name as created_by_lastname,
            COALESCE(
                (SELECT SUM(count_number) FROM attendance_counts ac WHERE ac.event_id = e.id AND ac.attendance_category = 'total'),
                (SELECT COUNT(*) FROM attendance_records ar WHERE ar.event_id = e.id AND ar.is_present = 1)
            ) as attendance_count
        FROM events e
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN users u ON e.created_by = u.id
        WHERE e.id = ?
    ", [$eventId])->fetch();
    
    if (!$event) {
        throw new Exception('Event not found');
    }
    
    // Generate HTML for modal content
    $html = '
        <div class="row">
            <div class="col-md-8">
                <h5 class="text-church-blue">' . htmlspecialchars($event['name']) . '</h5>
                <p class="text-muted mb-3">' . htmlspecialchars($event['description'] ?: 'No description provided') . '</p>
                
                <table class="table table-borderless table-sm">
                    <tr>
                        <td width="120" class="text-muted">Date:</td>
                        <td><strong>' . formatDisplayDate($event['event_date']) . '</strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Time:</td>
                        <td><strong>' . date('H:i', strtotime($event['start_time'])) . '</strong>';
    
    if ($event['end_time']) {
        $html .= ' - ' . date('H:i', strtotime($event['end_time']));
    }
    
    $html .= '</td>
                    </tr>';
    
    if ($event['location']) {
        $html .= '<tr>
                        <td class="text-muted">Location:</td>
                        <td>' . htmlspecialchars($event['location']) . '</td>
                    </tr>';
    }
    
    if ($event['department_name']) {
        $html .= '<tr>
                        <td class="text-muted">Department:</td>
                        <td><span class="badge bg-light text-dark">' . htmlspecialchars($event['department_name']) . '</span></td>
                    </tr>';
    }
    
    $html .= '</table>
            </div>
            <div class="col-md-4 text-center">
                <div class="display-6 text-church-red">' . number_format($event['attendance_count']) . '</div>
                <div class="text-muted">Total Attendance</div>
                
                <div class="mt-3">
                    <a href="' . BASE_URL . 'modules/attendance/view_event.php?id=' . $event['id'] . '" 
                       class="btn btn-church-primary btn-sm">
                        <i class="fas fa-eye me-1"></i>View Details
                    </a>
                </div>
            </div>
        </div>';
    
    echo json_encode([
        'success' => true,
        'html' => $html
    ]);
}

/**
 * Get attendance records for an event
 */
function handleGetAttendance() {
    global $db;
    
    $eventId = (int) ($_GET['event_id'] ?? 0);
    if (!$eventId) {
        throw new Exception('Event ID required');
    }
    
    // Get individual attendance
    $individualAttendance = $db->executeQuery("
        SELECT ar.member_id, ar.is_present
        FROM attendance_records ar
        WHERE ar.event_id = ?
    ", [$eventId])->fetchAll();
    
    // Get bulk counts
    $bulkCounts = $db->executeQuery("
        SELECT attendance_category, count_number
        FROM attendance_counts
        WHERE event_id = ?
    ", [$eventId])->fetchAll();
    
    echo json_encode([
        'success' => true,
        'individual_attendance' => $individualAttendance,
        'bulk_counts' => $bulkCounts
    ]);
}

/**
 * Preview import file data
 */
function handlePreviewImport() {
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error');
    }
    
    $file = $_FILES['file'];
    $importType = $_POST['import_type'] ?? 'individual';
    
    // Validate file type
    $allowedTypes = ['csv'];
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($fileExtension, $allowedTypes)) {
        throw new Exception('Invalid file type. Only CSV files are supported for preview.');
    }
    
    // Parse CSV data
    $data = [];
    if (($handle = fopen($file['tmp_name'], "r")) !== FALSE) {
        $headers = fgetcsv($handle, 1000, ",");
        $headers = array_map('trim', $headers);
        $headers = array_map('strtolower', $headers);
        
        $rowCount = 0;
        while (($row = fgetcsv($handle, 1000, ",")) !== FALSE && $rowCount < 10) { // Preview first 10 rows
            if (count($row) === count($headers)) {
                $data[] = array_combine($headers, $row);
                $rowCount++;
            }
        }
        fclose($handle);
    }
    
    if (empty($data)) {
        throw new Exception('No valid data found in file');
    }
    
    // Generate preview HTML
    $html = '<div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead class="table-light">
                        <tr>';
    
    foreach (array_keys($data[0]) as $header) {
        $html .= '<th>' . htmlspecialchars(ucfirst(str_replace('_', ' ', $header))) . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    
    foreach ($data as $row) {
        $html .= '<tr>';
        foreach ($row as $cell) {
            $html .= '<td>' . htmlspecialchars($cell) . '</td>';
        }
        $html .= '</tr>';
    }
    
    $html .= '</tbody></table></div>';
    
    if (count($data) >= 10) {
        $html .= '<div class="alert alert-info mt-2">
                    <i class="fas fa-info-circle me-2"></i>
                    Showing first 10 rows. Full file will be processed during import.
                  </div>';
    }
    
    echo json_encode([
        'success' => true,
        'preview_html' => $html,
        'total_rows' => count($data),
        'valid_rows' => count($data),
        'headers' => array_keys($data[0])
    ]);
}

/**
 * Export attendance data
 */
function handleExportAttendance() {
    global $db;
    
    $eventId = (int) ($_GET['event_id'] ?? 0);
    $format = $_GET['format'] ?? 'csv';
    
    if (!$eventId) {
        throw new Exception('Event ID required');
    }
    
    // Get event details
    $event = getRecord('events', 'id', $eventId);
    if (!$event) {
        throw new Exception('Event not found');
    }
    
    // Get attendance data
    $attendanceData = $db->executeQuery("
        SELECT 
            m.member_number,
            m.first_name,
            m.last_name,
            m.phone,
            m.email,
            d.name as department,
            CASE WHEN ar.is_present = 1 THEN 'Present' ELSE 'Absent' END as status,
            ar.check_in_time,
            ar.check_in_method
        FROM attendance_records ar
        JOIN members m ON ar.member_id = m.id
        LEFT JOIN member_departments md ON m.id = md.member_id AND md.is_active = 1
        LEFT JOIN departments d ON md.department_id = d.id
        WHERE ar.event_id = ?
        ORDER BY m.first_name, m.last_name
    ", [$eventId])->fetchAll();
    
    $filename = 'attendance_' . sanitizeFilename($event['name']) . '_' . $event['event_date'];
    
    switch ($format) {
        case 'csv':
            exportCSV($attendanceData, $filename);
            break;
        case 'excel':
            exportExcel($attendanceData, $filename);
            break;
        case 'pdf':
            exportPDF($attendanceData, $event, $filename);
            break;
        default:
            throw new Exception('Invalid export format');
    }
}

/**
 * Get event history/activity logs
 */
function handleGetHistory() {
    global $db;
    
    $eventId = (int) ($_GET['id'] ?? 0);
    if (!$eventId) {
        throw new Exception('Event ID required');
    }
    
    $history = $db->executeQuery("
        SELECT 
            al.*,
            u.first_name,
            u.last_name,
            CONCAT(u.first_name, ' ', u.last_name) as user_name
        FROM activity_logs al
        JOIN users u ON al.user_id = u.id
        WHERE al.table_name = 'events' AND al.record_id = ?
        ORDER BY al.created_at DESC
        LIMIT 20
    ", [$eventId])->fetchAll();
    
    echo json_encode([
        'success' => true,
        'history' => $history
    ]);
}

/**
 * Update individual attendance status
 */
function handleUpdateAttendanceStatus() {
    global $db;
    
    if (!hasPermission('attendance')) {
        throw new Exception('Insufficient permissions');
    }
    
    $eventId = (int) ($_POST['event_id'] ?? 0);
    $memberId = (int) ($_POST['member_id'] ?? 0);
    $isPresent = (int) ($_POST['is_present'] ?? 0);
    
    if (!$eventId || !$memberId) {
        throw new Exception('Event ID and Member ID required');
    }
    
    // Check if record exists
    $existing = $db->executeQuery("
        SELECT id FROM attendance_records 
        WHERE event_id = ? AND member_id = ?
    ", [$eventId, $memberId])->fetch();
    
    if ($existing) {
        // Update existing record
        $updated = updateRecord('attendance_records', [
            'is_present' => $isPresent,
            'check_in_time' => $isPresent ? getCurrentTimestamp() : null
        ], [
            'event_id' => $eventId,
            'member_id' => $memberId
        ]);
    } else {
        // Insert new record
        $updated = insertRecord('attendance_records', [
            'event_id' => $eventId,
            'member_id' => $memberId,
            'attendance_type' => 'member_checkin',
            'is_present' => $isPresent,
            'check_in_time' => $isPresent ? getCurrentTimestamp() : null,
            'check_in_method' => 'manual',
            'recorded_by' => $_SESSION['user_id']
        ]);
    }
    
    if ($updated) {
        logActivity('Updated attendance status', 'attendance_records', $eventId);
        echo json_encode(['success' => true, 'message' => 'Attendance updated successfully']);
    } else {
        throw new Exception('Failed to update attendance');
    }
}

/**
 * Get member attendance history
 */
function handleGetMemberAttendance() {
    global $db;
    
    $memberId = (int) ($_GET['member_id'] ?? 0);
    $limit = (int) ($_GET['limit'] ?? 10);
    
    if (!$memberId) {
        throw new Exception('Member ID required');
    }
    
    $attendance = $db->executeQuery("
        SELECT 
            e.name as event_name,
            e.event_date,
            e.start_time,
            e.event_type,
            ar.is_present,
            ar.check_in_time
        FROM attendance_records ar
        JOIN events e ON ar.event_id = e.id
        WHERE ar.member_id = ?
        ORDER BY e.event_date DESC, e.start_time DESC
        LIMIT ?
    ", [$memberId, $limit])->fetchAll();
    
    echo json_encode([
        'success' => true,
        'attendance' => $attendance
    ]);
}

/**
 * Bulk update attendance records
 */
function handleBulkUpdateAttendance() {
    global $db;
    
    if (!hasPermission('attendance')) {
        throw new Exception('Insufficient permissions');
    }
    
    $eventId = (int) ($_POST['event_id'] ?? 0);
    $updates = json_decode($_POST['updates'] ?? '[]', true);
    
    if (!$eventId || empty($updates)) {
        throw new Exception('Event ID and updates required');
    }
    
    $db->beginTransaction();
    
    try {
        $updatedCount = 0;
        
        foreach ($updates as $update) {
            $memberId = (int) $update['member_id'];
            $isPresent = (int) $update['is_present'];
            
            // Check if record exists
            $existing = $db->executeQuery("
                SELECT id FROM attendance_records 
                WHERE event_id = ? AND member_id = ?
            ", [$eventId, $memberId])->fetch();
            
            if ($existing) {
                // Update existing
                updateRecord('attendance_records', [
                    'is_present' => $isPresent,
                    'check_in_time' => $isPresent ? getCurrentTimestamp() : null
                ], [
                    'event_id' => $eventId,
                    'member_id' => $memberId
                ]);
            } else {
                // Insert new
                insertRecord('attendance_records', [
                    'event_id' => $eventId,
                    'member_id' => $memberId,
                    'attendance_type' => 'member_checkin',
                    'is_present' => $isPresent,
                    'check_in_time' => $isPresent ? getCurrentTimestamp() : null,
                    'check_in_method' => 'bulk_update',
                    'recorded_by' => $_SESSION['user_id']
                ]);
            }
            
            $updatedCount++;
        }
        
        $db->commit();
        
        logActivity('Bulk updated attendance', 'attendance_records', $eventId, null, [
            'updated_count' => $updatedCount
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => "Successfully updated {$updatedCount} attendance records"
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

/**
 * Generate QR code for event check-in
 */
function handleGenerateQRCode() {
    $eventId = (int) ($_GET['event_id'] ?? 0);
    
    if (!$eventId) {
        throw new Exception('Event ID required');
    }
    
    // Verify event exists
    $event = getRecord('events', 'id', $eventId);
    if (!$event) {
        throw new Exception('Event not found');
    }
    
    // Generate check-in URL
    $checkinUrl = BASE_URL . 'modules/attendance/checkin.php?event=' . $eventId . '&token=' . generateSecureToken(16);
    
    // In a real implementation, you would use a QR code library like endroid/qr-code
    // For now, we'll return the URL and suggest using a QR code generator
    
    echo json_encode([
        'success' => true,
        'checkin_url' => $checkinUrl,
        'qr_code_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($checkinUrl),
        'event_name' => $event['name']
    ]);
}

// Helper functions

function exportCSV($data, $filename) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    if (!empty($data)) {
        // Write headers
        fputcsv($output, array_keys($data[0]));
        
        // Write data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit;
}

function exportExcel($data, $filename) {
    // Basic Excel export - in production, use PhpSpreadsheet for full Excel support
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    
    echo '<table border="1">';
    
    if (!empty($data)) {
        // Headers
        echo '<tr>';
        foreach (array_keys($data[0]) as $header) {
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
    }
    
    echo '</table>';
    exit;
}

function exportPDF($data, $event, $filename) {
    // Basic PDF export - in production, use libraries like TCPDF or FPDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
    
    // For now, return HTML that can be converted to PDF
    $html = '
    <html>
    <head>
        <title>Attendance Report - ' . htmlspecialchars($event['name']) . '</title>
        <style>
            body { font-family: Arial, sans-serif; }
            h1 { color: #03045e; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
        </style>
    </head>
    <body>
        <h1>Attendance Report</h1>
        <h2>' . htmlspecialchars($event['name']) . '</h2>
        <p><strong>Date:</strong> ' . formatDisplayDate($event['event_date']) . '</p>
        <p><strong>Time:</strong> ' . date('H:i', strtotime($event['start_time'])) . '</p>
        
        <table>';
    
    if (!empty($data)) {
        $html .= '<tr>';
        foreach (array_keys($data[0]) as $header) {
            $html .= '<th>' . htmlspecialchars(ucfirst(str_replace('_', ' ', $header))) . '</th>';
        }
        $html .= '</tr>';
        
        foreach ($data as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>' . htmlspecialchars($cell) . '</td>';
            }
            $html .= '</tr>';
        }
    }
    
    $html .= '</table></body></html>';
    
    echo $html;
    exit;
}

function sanitizeFilename($filename) {
    return preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
}
?>