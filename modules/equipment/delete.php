<?php
/**
 * Equipment Deletion Handler
 * Deliverance Church Management System
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Set JSON content type
header('Content-Type: application/json');

// Check authentication and permissions
if (!isLoggedIn()) {
    sendJSONResponse(['success' => false, 'message' => 'Authentication required'], 401);
}

if (!hasPermission('equipment') || $_SESSION['user_role'] !== 'administrator') {
    sendJSONResponse(['success' => false, 'message' => 'Insufficient permissions'], 403);
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONResponse(['success' => false, 'message' => 'Invalid request method'], 405);
}

// Get equipment ID
$equipment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$equipment_id) {
    sendJSONResponse(['success' => false, 'message' => 'Invalid equipment ID'], 400);
}

try {
    $db = Database::getInstance();
    
    // Start transaction
    $db->beginTransaction();
    
    // Get equipment details for logging
    $equipmentStmt = $db->executeQuery("SELECT * FROM equipment WHERE id = ?", [$equipment_id]);
    $equipment = $equipmentStmt->fetch();
    
    if (!$equipment) {
        $db->rollback();
        sendJSONResponse(['success' => false, 'message' => 'Equipment not found'], 404);
    }
    
    // Check if equipment has maintenance records
    $maintenanceStmt = $db->executeQuery("SELECT COUNT(*) as count FROM equipment_maintenance WHERE equipment_id = ?", [$equipment_id]);
    $maintenanceCount = $maintenanceStmt->fetch()['count'];
    
    // Delete maintenance records first (cascade delete)
    if ($maintenanceCount > 0) {
        $deleteMaintenanceStmt = $db->executeQuery("DELETE FROM equipment_maintenance WHERE equipment_id = ?", [$equipment_id]);
    }
    
    // Delete equipment photos/files if they exist
    $photoPath = ASSETS_PATH . 'uploads/equipment/';
    $equipmentCode = $equipment['equipment_code'];
    
    // Look for equipment photos
    if (is_dir($photoPath)) {
        $files = glob($photoPath . $equipmentCode . '_*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
    
    // Delete the equipment record
    $deleteEquipmentStmt = $db->executeQuery("DELETE FROM equipment WHERE id = ?", [$equipment_id]);
    
    if ($deleteEquipmentStmt->rowCount() === 0) {
        $db->rollback();
        sendJSONResponse(['success' => false, 'message' => 'Failed to delete equipment'], 500);
    }
    
    // Log the activity
    logActivity('Equipment deleted', 'equipment', $equipment_id, $equipment, null);
    
    // Commit transaction
    $db->commit();
    
    // Send success response
    sendJSONResponse([
        'success' => true, 
        'message' => 'Equipment deleted successfully',
        'data' => [
            'equipment_name' => $equipment['name'],
            'equipment_code' => $equipment['equipment_code'],
            'maintenance_records_deleted' => $maintenanceCount
        ]
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($db && $db->getConnection()->inTransaction()) {
        $db->rollback();
    }
    
    error_log("Equipment deletion error: " . $e->getMessage());
    sendJSONResponse(['success' => false, 'message' => 'An error occurred while deleting equipment'], 500);
}
?>