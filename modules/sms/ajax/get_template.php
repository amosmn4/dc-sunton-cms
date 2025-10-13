<?php
/**
 * AJAX: Get SMS Template Details
 * Deliverance Church Management System
 */

require_once '../../../config/config.php';
require_once '../../../includes/functions.php';

// Check authentication
if (!isLoggedIn()) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

// Check permissions
if (!hasPermission('sms') && !in_array($_SESSION['user_role'], ['administrator', 'pastor', 'secretary'])) {
    sendJSONResponse(['success' => false, 'message' => 'Insufficient permissions'], 403);
}

try {
    $templateId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($templateId <= 0) {
        throw new Exception('Invalid template ID');
    }
    
    $db = Database::getInstance();
    
    $template = $db->executeQuery("
        SELECT 
            st.*,
            CONCAT(u.first_name, ' ', u.last_name) as created_by_name
        FROM sms_templates st
        LEFT JOIN users u ON st.created_by = u.id
        WHERE st.id = ?
    ", [$templateId])->fetch();
    
    if (!$template) {
        throw new Exception('Template not found');
    }
    
    // Format the response
    $response = [
        'id' => $template['id'],
        'name' => $template['name'],
        'category' => $template['category'],
        'category_display' => ucfirst(str_replace('_', ' ', $template['category'])),
        'message' => $template['message'],
        'is_active' => (int)$template['is_active'],
        'created_by_name' => $template['created_by_name'] ?: 'System',
        'created_at' => $template['created_at'],
        'created_at_formatted' => formatDisplayDateTime($template['created_at'])
    ];
    
    sendJSONResponse([
        'success' => true,
        'template' => $response
    ]);
    
} catch (Exception $e) {
    error_log("Error getting template: " . $e->getMessage());
    sendJSONResponse([
        'success' => false,
        'message' => 'Error loading template: ' . $e->getMessage()
    ], 500);
}
?>