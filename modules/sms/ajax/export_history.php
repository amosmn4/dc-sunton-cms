<?php
/**
 * Export SMS History via AJAX
 * Deliverance Church Management System
 */

require_once '../../../config/config.php';
require_once '../../../includes/functions.php';

requireLogin();

if (!hasPermission('sms')) {
    sendJSONResponse(['success' => false, 'message' => 'Permission denied'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONResponse(['success' => false, 'message' => 'Invalid request method'], 400);
}

$action = isset($_POST['action']) ? sanitizeInput($_POST['action']) : '';
$date_from = isset($_POST['date_from']) ? sanitizeInput($_POST['date_from']) : date('Y-m-01');
$date_to = isset($_POST['date_to']) ? sanitizeInput($_POST['date_to']) : date('Y-m-d');
$status = isset($_POST['status']) ? sanitizeInput($_POST['status']) : '';
$export_format = isset($_POST['format']) ? sanitizeInput($_POST['format']) : 'csv';

$db = Database::getInstance();

try {
    if ($action === 'batch_export') {
        // Export SMS batches/campaigns
        $headers = ['Batch ID', 'Recipient Type', 'Total Recipients', 'Sent', 'Failed', 'Cost', 'Status', 'Sent At', 'Completed At'];
        
        $sql = "SELECT 
                    batch_id,
                    recipient_type,
                    total_recipients,
                    sent_count,
                    failed_count,
                    cost,
                    status,
                    sent_at,
                    completed_at
                FROM sms_history
                WHERE sent_at BETWEEN ? AND ?";
        
        $params = [$date_from . ' 00:00:00', $date_to . ' 23:59:59'];
        
        if (!empty($status)) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY sent_at DESC";
        
        $stmt = $db->executeQuery($sql, $params);
        $records = $stmt->fetchAll();
        
        $data = [];
        foreach ($records as $record) {
            $data[] = [
                $record['batch_id'],
                ucfirst(str_replace('_', ' ', $record['recipient_type'])),
                $record['total_recipients'],
                $record['sent_count'],
                $record['failed_count'],
                formatCurrency($record['cost']),
                ucfirst($record['status']),
                formatDisplayDateTime($record['sent_at']),
                formatDisplayDateTime($record['completed_at'])
            ];
        }
        
        $filename = 'sms_batches_' . date('Ymd_His');
        
    } elseif ($action === 'detailed_export') {
        // Export detailed SMS records
        $headers = ['Recipient Phone', 'Recipient Name', 'Message', 'Status', 'Sent At', 'Delivered At', 'Cost'];
        
        $sql = "SELECT 
                    recipient_phone,
                    recipient_name,
                    message,
                    status,
                    sent_at,
                    delivered_at,
                    cost
                FROM sms_individual
                WHERE sent_at BETWEEN ? AND ?";
        
        $params = [$date_from . ' 00:00:00', $date_to . ' 23:59:59'];
        
        if (!empty($status)) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY sent_at DESC";
        
        $stmt = $db->executeQuery($sql, $params);
        $records = $stmt->fetchAll();
        
        $data = [];
        foreach ($records as $record) {
            $data[] = [
                $record['recipient_phone'],
                $record['recipient_name'] ?? '-',
                $record['message'],
                ucfirst($record['status']),
                formatDisplayDateTime($record['sent_at']),
                formatDisplayDateTime($record['delivered_at']),
                formatCurrency($record['cost'])
            ];
        }
        
        $filename = 'sms_detailed_' . date('Ymd_His');
    }
    
    // Export
    if ($export_format === 'csv') {
        exportToCSV($data, $headers, $filename . '.csv');
    } elseif ($export_format === 'excel') {
        $filepath = generateExcelFile($data, $headers, $filename . '.xlsx');
        // Return file path for download
        sendJSONResponse([
            'success' => true,
            'message' => 'Export successful',
            'filename' => basename($filepath),
            'path' => $filepath
        ]);
    }
    
    logActivity('Export SMS data', 'sms_history', null, null, ['action' => $action, 'format' => $export_format]);
    
} catch (Exception $e) {
    error_log("Error exporting SMS data: " . $e->getMessage());
    sendJSONResponse(['success' => false, 'message' => 'Error exporting data: ' . $e->getMessage()], 500);
}

?>