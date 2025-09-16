<?php
/**
 * Get Equipment Details
 * Deliverance Church Management System
 * 
 * AJAX endpoint for retrieving equipment details
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check authentication
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if (!hasPermission('equipment')) {
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit;
}

// Get equipment ID
$equipment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$equipment_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid equipment ID']);
    exit;
}

try {
    $db = Database::getInstance();
    
    // Get equipment details with responsible person info
    $query = "SELECT e.*, 
                     ec.name as category_name,
                     CONCAT(m.first_name, ' ', m.last_name) as responsible_person,
                     CASE 
                        WHEN e.next_maintenance_date < CURDATE() THEN 'overdue'
                        WHEN e.next_maintenance_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'due_soon'
                        ELSE 'ok'
                     END as maintenance_status,
                     DATEDIFF(CURDATE(), e.last_maintenance_date) as days_since_maintenance,
                     DATEDIFF(e.next_maintenance_date, CURDATE()) as days_until_maintenance
              FROM equipment e
              LEFT JOIN equipment_categories ec ON e.category_id = ec.id
              LEFT JOIN members m ON e.responsible_person_id = m.id
              WHERE e.id = ?";
    
    $stmt = $db->executeQuery($query, [$equipment_id]);
    $equipment = $stmt->fetch();
    
    if (!$equipment) {
        echo json_encode(['success' => false, 'message' => 'Equipment not found']);
        exit;
    }
    
    // Get maintenance history count
    $maintenanceCountStmt = $db->executeQuery(
        "SELECT COUNT(*) as total, 
                SUM(CASE WHEN maintenance_type = 'emergency' THEN 1 ELSE 0 END) as emergency_count,
                SUM(cost) as total_cost
         FROM equipment_maintenance 
         WHERE equipment_id = ?", 
        [$equipment_id]
    );
    $maintenanceStats = $maintenanceCountStmt->fetch();
    
    // Get last maintenance details
    $lastMaintenanceStmt = $db->executeQuery(
        "SELECT maintenance_date, maintenance_type, performed_by 
         FROM equipment_maintenance 
         WHERE equipment_id = ? 
         ORDER BY maintenance_date DESC 
         LIMIT 1", 
        [$equipment_id]
    );
    $lastMaintenance = $lastMaintenanceStmt->fetch();
    
    // Format dates for display
    $lastMaintenanceFormatted = 'Never';
    if ($lastMaintenance) {
        $lastMaintenanceFormatted = formatDisplayDate($lastMaintenance['maintenance_date']) . 
                                  ' (' . ($lastMaintenance['maintenance_type'] ?? 'Unknown') . ')';
    } elseif (!empty($equipment['last_maintenance_date']) && $equipment['last_maintenance_date'] !== '0000-00-00') {
        $lastMaintenanceFormatted = formatDisplayDate($equipment['last_maintenance_date']);
    }
    
    $nextMaintenanceFormatted = 'Not scheduled';
    if (!empty($equipment['next_maintenance_date']) && $equipment['next_maintenance_date'] !== '0000-00-00') {
        $nextMaintenanceFormatted = formatDisplayDate($equipment['next_maintenance_date']);
        
        if ($equipment['maintenance_status'] === 'overdue') {
            $nextMaintenanceFormatted .= ' <span class="badge bg-danger ms-1">Overdue</span>';
        } elseif ($equipment['maintenance_status'] === 'due_soon') {
            $nextMaintenanceFormatted .= ' <span class="badge bg-warning ms-1">Due Soon</span>';
        }
    }
    
    // Prepare response data
    $response = [
        'success' => true,
        'equipment' => [
            'id' => $equipment['id'],
            'name' => $equipment['name'],
            'equipment_code' => $equipment['equipment_code'],
            'category_name' => $equipment['category_name'],
            'status' => $equipment['status'],
            'location' => $equipment['location'],
            'brand' => $equipment['brand'],
            'model' => $equipment['model'],
            'serial_number' => $equipment['serial_number'],
            'responsible_person' => $equipment['responsible_person'],
            'maintenance_status' => $equipment['maintenance_status'],
            'maintenance_interval_days' => $equipment['maintenance_interval_days'],
            'last_maintenance_date' => $equipment['last_maintenance_date'],
            'next_maintenance_date' => $equipment['next_maintenance_date'],
            'last_maintenance_formatted' => $lastMaintenanceFormatted,
            'next_maintenance_formatted' => $nextMaintenanceFormatted,
            'days_since_maintenance' => $equipment['days_since_maintenance'],
            'days_until_maintenance' => $equipment['days_until_maintenance'],
            'purchase_date' => $equipment['purchase_date'],
            'purchase_price' => $equipment['purchase_price'],
            'warranty_expiry' => $equipment['warranty_expiry'],
            'condition_notes' => $equipment['condition_notes'],
            'created_at' => $equipment['created_at']
        ],
        'maintenance_stats' => [
            'total_maintenance' => (int)($maintenanceStats['total'] ?? 0),
            'emergency_count' => (int)($maintenanceStats['emergency_count'] ?? 0),
            'total_cost' => (float)($maintenanceStats['total_cost'] ?? 0.00),
            'total_cost_formatted' => formatCurrency($maintenanceStats['total_cost'] ?? 0.00)
        ],
        'last_maintenance' => $lastMaintenance
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Get equipment details error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while retrieving equipment details'
    ]);
}
?>