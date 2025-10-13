<?php
/**
 * AJAX: Send Test WhatsApp Message
 * Deliverance Church Management System
 */

require_once '../../../config/config.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/WhatsAppSender.php';

// Check authentication
if (!isLoggedIn()) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

// Check permissions
if (!hasPermission('sms') && !in_array($_SESSION['user_role'], ['administrator', 'pastor', 'secretary'])) {
    sendJSONResponse(['success' => false, 'message' => 'Insufficient permissions'], 403);
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    $message = trim($input['message'] ?? '');
    
    if (empty($message)) {
        throw new Exception('Message is required');
    }
    
    // Send to the business WhatsApp number
    $whatsappSender = new WhatsAppSender();
    
    $testRecipient = [
        [
            'id' => null,
            'name' => 'Test User',
            'first_name' => $_SESSION['first_name'],
            'last_name' => $_SESSION['last_name'],
            'phone' => '0745600377' // Your WhatsApp number
        ]
    ];
    
    $result = $whatsappSender->sendMessage($testRecipient, $message);
    
    if ($result['success'] && $result['sent_count'] > 0) {
        // Log test send
        logActivity(
            'Test WhatsApp message sent',
            null,
            null,
            null,
            ['message' => substr($message, 0, 100)]
        );
        
        sendJSONResponse([
            'success' => true,
            'message' => 'Test message sent successfully to 0745600377!'
        ]);
    } else {
        sendJSONResponse([
            'success' => false,
            'message' => 'Failed to send test message: ' . ($result['results'][0]['error'] ?? 'Unknown error')
        ]);
    }
    
} catch (Exception $e) {
    error_log("Test WhatsApp send error: " . $e->getMessage());
    sendJSONResponse([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ], 500);
}
?>