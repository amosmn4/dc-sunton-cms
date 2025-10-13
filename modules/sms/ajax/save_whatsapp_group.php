<?php
/**
 * AJAX: Save WhatsApp Group
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
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    $name = sanitizeInput($input['name'] ?? '');
    $groupId = sanitizeInput($input['group_id'] ?? '');
    $description = sanitizeInput($input['description'] ?? '');
    
    // Validation
    if (empty($name)) {
        throw new Exception('Group name is required');
    }
    
    $db = Database::getInstance();
    
    // Check if group name already exists
    $existing = $db->executeQuery("
        SELECT id FROM whatsapp_groups 
        WHERE name = ? AND is_active = 1
    ", [$name])->fetch();
    
    if ($existing) {
        throw new Exception('A group with this name already exists');
    }
    
    // Insert group
    $groupData = [
        'name' => $name,
        'group_id' => $groupId,
        'description' => $description,
        'is_active' => 1,
        'created_by' => $_SESSION['user_id']
    ];
    
    $newGroupId = insertRecord('whatsapp_groups', $groupData);
    
    if (!$newGroupId) {
        throw new Exception('Failed to save group');
    }
    
    // Log activity
    logActivity(
        'WhatsApp group created',
        'whatsapp_groups',
        $newGroupId,
        null,
        $groupData
    );
    
    sendJSONResponse([
        'success' => true,
        'message' => 'WhatsApp group saved successfully',
        'group' => [
            'id' => $newGroupId,
            'name' => $name,
            'group_id' => $groupId,
            'description' => $description
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Save WhatsApp group error: " . $e->getMessage());
    sendJSONResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 500);
}
?>