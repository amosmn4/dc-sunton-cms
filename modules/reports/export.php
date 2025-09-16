<?php
/**
 * Report Export Handler
 * Deliverance Church Management System
 * 
 * Handles export of reports in various formats (CSV, Excel, PDF)
 */

// Include configuration and functions
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Check authentication and permissions
requireLogin();
if (!hasPermission('reports')) {
    header('Location: ' . BASE_URL . 'modules/dashboard/?error=access_denied');
    exit();
}

// Get export parameters
$type = sanitizeInput($_GET['type'] ?? '');
$format = sanitizeInput($_GET['format'] ?? 'csv');
$reportModule = sanitizeInput($_GET['module'] ?? '');

// Initialize database
$db = Database::getInstance();

try {
    switch ($type) {
        case 'all':
            exportAllData($db, $format);
            break;
        case 'financial':
            exportFinancialData($db, $format);
            break;
        case 'members':
            exportMembersData($db, $format);
            break;
        case 'attendance':
            exportAttendanceData($db, $format);
            break;
        case 'visitors':
            exportVisitorsData($db, $format);
            break;
        case 'equipment':
            exportEquipmentData($db, $format);
            break;
        case 'sms':
            exportSMSData($db, $format);
            break;
        default:
            setFlashMessage('error', 'Invalid export type specified');
            header('Location: index.php');
            exit();
    }
} catch (Exception $e) {
    error_log("Export error: " . $e->getMessage());
    setFlashMessage('error', 'Export failed: ' . $e->getMessage());
    header('Location: index.php');
    exit();
}

/**
 * Export all system data
 */
function exportAllData($db, $format) {
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "church_cms_full_export_{$timestamp}";
    
    if ($format === 'excel') {
        exportAllDataExcel($db, $filename);
    } else {
        exportAllDataCSV($db, $filename);
    }
    
    logActivity('Exported all system data', null, null, null, ['format' => $format]);
}

/**
 * Export all data as multiple CSV files in a ZIP
 */
function exportAllDataCSV($db, $filename) {
    // Create temporary directory
    $tempDir = sys_get_temp_dir() . '/church_export_' . uniqid();
    mkdir($tempDir, 0755, true);
    
    try {
        // Export each module
        exportMembersToCSV($db, $tempDir . '/members.csv');
        exportAttendanceToCSV($db, $tempDir . '/attendance.csv');
        exportFinancialToCSV($db, $tempDir . '/financial.csv');
        exportVisitorsToCSV($db, $tempDir . '/visitors.csv');
        exportEquipmentToCSV($db, $tempDir . '/equipment.csv');
        exportSMSToCSV($db, $tempDir . '/sms_history.csv');
        exportDepartmentsToCSV($db, $tempDir . '/departments.csv');
        
        // Create ZIP file
        $zipFile = $tempDir . '.zip';
        $zip = new ZipArchive();
        
        if ($zip->open($zipFile, ZipArchive::CREATE) === TRUE) {
            $files = glob($tempDir . '/*.csv');
            foreach ($files as $file) {
                $zip->addFile($file, basename($file));
            }
            $zip->close();
            
            // Send ZIP file
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $filename . '.zip"');
            header('Content-Length: ' . filesize($zipFile));
            
            readfile($zipFile);
            
            // Clean up
            unlink($zipFile);
            array_map('unlink', $files);
            rmdir($tempDir);
        } else {
            throw new Exception('Could not create ZIP file');
        }
    } catch (Exception $e) {
        // Clean up on error
        if (is_dir($tempDir)) {
            $files = glob($tempDir . '/*');
            array_map('unlink', $files);
            rmdir($tempDir);
        }
        throw $e;
    }
}

/**
 * Export members data to CSV
 */
function exportMembersToCSV($db, $filename) {
    $query = "
        SELECT 
            m.member_number,
            m.first_name,
            m.last_name,
            m.middle_name,
            m.date_of_birth,
            m.age,
            m.gender,
            m.marital_status,
            m.phone,
            m.email,
            m.address,
            m.emergency_contact_name,
            m.emergency_contact_phone,
            m.join_date,
            m.baptism_date,
            m.membership_status,
            m.occupation,
            m.skills,
            GROUP_CONCAT(DISTINCT d.name SEPARATOR '; ') as departments
        FROM members m
        LEFT JOIN member_departments md ON m.id = md.member_id AND md.is_active = 1
        LEFT JOIN departments d ON md.department_id = d.id
        GROUP BY m.id
        ORDER BY m.last_name, m.first_name
    ";
    
    $members = $db->executeQuery($query)->fetchAll();
    
    $file = fopen($filename, 'w');
    
    // Write headers
    fputcsv($file, [
        'Member Number', 'First Name', 'Last Name', 'Middle Name', 'Date of Birth', 'Age',
        'Gender', 'Marital Status', 'Phone', 'Email', 'Address', 'Emergency Contact Name',
        'Emergency Contact Phone', 'Join Date', 'Baptism Date', 'Status', 'Occupation',
        'Skills', 'Departments'
    ]);
    
    // Write data
    foreach ($members as $member) {
        fputcsv($file, [
            $member['member_number'],
            $member['first_name'],
            $member['last_name'],
            $member['middle_name'],
            $member['date_of_birth'],
            $member['age'],
            $member['gender'],
            $member['marital_status'],
            $member['phone'],
            $member['email'],
            $member['address'],
            $member['emergency_contact_name'],
            $member['emergency_contact_phone'],
            $member['join_date'],
            $member['baptism_date'],
            $member['membership_status'],
            $member['occupation'],
            $member['skills'],
            $member['departments']
        ]);
    }
    
    fclose($file);
}

/**
 * Export attendance data to CSV
 */
function exportAttendanceToCSV($db, $filename) {
    $query = "
        SELECT 
            e.name as event_name,
            e.event_type,
            e.event_date,
            e.start_time,
            m.member_number,
            m.first_name,
            m.last_name,
            ar.check_in_time,
            ar.check_in_method
        FROM attendance_records ar
        JOIN events e ON ar.event_id = e.id
        LEFT JOIN members m ON ar.member_id = m.id
        ORDER BY e.event_date DESC, ar.check_in_time DESC
    ";
    
    $attendance = $db->executeQuery($query)->fetchAll();
    
    $file = fopen($filename, 'w');
    
    // Write headers
    fputcsv($file, [
        'Event Name', 'Event Type', 'Event Date', 'Start Time', 'Member Number',
        'First Name', 'Last Name', 'Check-in Time', 'Check-in Method'
    ]);
    
    // Write data
    foreach ($attendance as $record) {
        fputcsv($file, [
            $record['event_name'],
            $record['event_type'],
            $record['event_date'],
            $record['start_time'],
            $record['member_number'],
            $record['first_name'],
            $record['last_name'],