<?php
/**
 * Delete Visitor
 * Deliverance Church Management System
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Check authentication and permissions
requireLogin();
if (!hasPermission('visitors')) {
    sendJSONResponse(['success' => false, 'message' => 'You do not have permission to perform this action.'], 403);
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONResponse(['success' => false, 'message' => 'Invalid request method.'], 405);
}

// Get visitor ID
$visitorId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$visitorId) {
    sendJSONResponse(['success' => false, 'message' => 'Invalid visitor ID provided.'], 400);
}

try {
    $db = Database::getInstance();
    
    // Check if visitor exists
    $visitorStmt = $db->executeQuery("SELECT * FROM visitors WHERE id = ?", [$visitorId]);
    $visitor = $visitorStmt->fetch();
    
    if (!$visitor) {
        sendJSONResponse(['success' => false, 'message' => 'Visitor not found.'], 404);
    }
    
    // Check if visitor has been converted to member
    if ($visitor['status'] === 'converted_member') {
        // Additional check - see if there's a member with matching details
        $memberCheckStmt = $db->executeQuery("
            SELECT COUNT(*) as count 
            FROM members 
            WHERE (phone = ? OR email = ?) 
            AND first_name = ? AND last_name = ?
        ", [
            $visitor['phone'], 
            $visitor['email'], 
            $visitor['first_name'], 
            $visitor['last_name']
        ]);
        $memberCount = $memberCheckStmt->fetch()['count'];
        
        if ($memberCount > 0) {
            sendJSONResponse([
                'success' => false, 
                'message' => 'Cannot delete visitor who has been converted to a member. Please contact system administrator.'
            ], 400);
        }
    }
    
    $db->beginTransaction();
    
    // Store visitor data for logging
    $visitorData = [
        'visitor_number' => $visitor['visitor_number'],
        'name' => $visitor['first_name'] . ' ' . $visitor['last_name'],
        'phone' => $visitor['phone'],
        'email' => $visitor['email'],
        'visit_date' => $visitor['visit_date'],
        'status' => $visitor['status']
    ];
    
    // Delete related follow-up records first (due to foreign key constraints)
    $deleteFollowupsStmt = $db->executeQuery("DELETE FROM visitor_followups WHERE visitor_id = ?", [$visitorId]);
    $deletedFollowups = $deleteFollowupsStmt->rowCount();
    
    // Delete the visitor record
    $deleteVisitorStmt = $db->executeQuery("DELETE FROM visitors WHERE id = ?", [$visitorId]);
    
    if ($deleteVisitorStmt->rowCount() === 0) {
        throw new Exception('Failed to delete visitor record');
    }
    
    $db->commit();
    
    // Log the deletion activity
    logActivity(
        'Visitor deleted', 
        'visitors', 
        $visitorId, 
        $visitorData, 
        null
    );
    
    // Also log follow-up deletions if any
    if ($deletedFollowups > 0) {
        logActivity(
            "Deleted $deletedFollowups follow-up records for visitor", 
            'visitor_followups', 
            null, 
            ['visitor_id' => $visitorId, 'count' => $deletedFollowups], 
            null
        );
    }
    
    sendJSONResponse([
        'success' => true, 
        'message' => "Visitor \"{$visitorData['name']}\" and {$deletedFollowups} related follow-up records have been successfully deleted.",
        'deleted_followups' => $deletedFollowups
    ]);
    
} catch (Exception $e) {
    // Rollback transaction if it was started
    if (isset($db) && $db->getConnection()->inTransaction()) {
        $db->rollback();
    }
    
    error_log("Error deleting visitor (ID: $visitorId): " . $e->getMessage());
    
    // Different error messages based on the error type
    $errorMessage = 'An error occurred while deleting the visitor.';
    
    if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
        $errorMessage = 'Cannot delete visitor due to related records. Please contact system administrator.';
    } elseif (strpos($e->getMessage(), 'doesn\'t exist') !== false) {
        $errorMessage = 'Visitor record not found or already deleted.';
    }
    
    sendJSONResponse([
        'success' => false, 
        'message' => $errorMessage,
        'debug' => ENVIRONMENT === 'development' ? $e->getMessage() : null
    ], 500);
}
?>