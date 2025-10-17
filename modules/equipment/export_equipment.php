<?php
/**
 * Equipment Export Files
 * Deliverance Church Management System
 * 
 * This file serves as export_equipment.php
 * For export_maintenance.php, use the same structure with maintenance queries
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

requireLogin();

if (!hasPermission('equipment')) {
    setFlashMessage('error', 'You do not have permission to access this page');
    redirect(BASE_URL . 'modules/dashboard/');
}

$export_type = isset($_GET['type']) ? sanitizeInput($_GET['type']) : 'equipment';
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$export_format = isset($_GET['format']) ? sanitizeInput($_GET['format']) : 'csv';

$db = Database::getInstance();

try {
    if ($export_type === 'equipment') {
        // Export Equipment Inventory
        $headers = [
            'Equipment Code',
            'Name',
            'Category',
            'Brand',
            'Model',
            'Serial Number',
            'Status',
            'Location',
            'Purchase Date',
            'Purchase Price',
            'Warranty Expiry',
            'Last Maintenance',
            'Next Maintenance',
            'Responsible Person'
        ];
        
        $sql = "SELECT 
                    e.equipment_code,
                    e.name,
                    ec.name as category,
                    e.brand,
                    e.model,
                    e.serial_number,
                    e.status,
                    e.location,
                    e.purchase_date,
                    e.purchase_price,
                    e.warranty_expiry,
                    e.last_maintenance_date,
                    e.next_maintenance_date,
                    CONCAT(m.first_name, ' ', m.last_name) as responsible_person
                FROM equipment e
                JOIN equipment_categories ec ON e.category_id = ec.id
                LEFT JOIN members m ON e.responsible_person_id = m.id
                WHERE 1=1";
        
        $params = [];
        
        if (!empty($status_filter)) {
            $sql .= " AND e.status = ?";
            $params[] = $status_filter;
        }
        
        $sql .= " ORDER BY e.name";
        
        $stmt = $db->executeQuery($sql, $params);
        $records = $stmt->fetchAll();
        
        $data = [];
        foreach ($records as $record) {
            $data[] = [
                $record['equipment_code'],
                $record['name'],
                $record['category'],
                $record['brand'] ?? '-',
                $record['model'] ?? '-',
                $record['serial_number'] ?? '-',
                ucfirst($record['status']),
                $record['location'] ?? '-',
                formatDisplayDate($record['purchase_date']),
                formatCurrency($record['purchase_price']),
                formatDisplayDate($record['warranty_expiry']),
                formatDisplayDate($record['last_maintenance_date']),
                formatDisplayDate($record['next_maintenance_date']),
                $record['responsible_person'] ?? '-'
            ];
        }
        
        $filename = 'equipment_inventory_' . date('Ymd_His');
        
    } elseif ($export_type === 'maintenance') {
        // Export Maintenance Records
        $headers = [
            'Equipment',
            'Equipment Code',
            'Maintenance Type',
            'Description',
            'Maintenance Date',
            'Performed By',
            'Cost',
            'Parts Replaced',
            'Next Date',
            'Status'
        ];
        
        $sql = "SELECT 
                    e.name as equipment,
                    e.equipment_code,
                    em.maintenance_type,
                    em.description,
                    em.maintenance_date,
                    em.performed_by,
                    em.cost,
                    em.parts_replaced,
                    em.next_maintenance_date,
                    em.status
                FROM equipment_maintenance em
                JOIN equipment e ON em.equipment_id = e.id
                ORDER BY em.maintenance_date DESC";
        
        $stmt = $db->executeQuery($sql, []);
        $records = $stmt->fetchAll();
        
        $data = [];
        foreach ($records as $record) {
            $data[] = [
                $record['equipment'],
                $record['equipment_code'],
                ucfirst(str_replace('_', ' ', $record['maintenance_type'])),
                $record['description'],
                formatDisplayDate($record['maintenance_date']),
                $record['performed_by'] ?? '-',
                formatCurrency($record['cost']),
                $record['parts_replaced'] ?? '-',
                formatDisplayDate($record['next_maintenance_date']),
                ucfirst($record['status'])
            ];
        }
        
        $filename = 'equipment_maintenance_' . date('Ymd_His');
    }
    
    // Export
    if ($export_format === 'csv') {
        exportToCSV($data, $headers, $filename . '.csv');
    } elseif ($export_format === 'excel') {
        $filepath = generateExcelFile($data, $headers, $filename . '.xlsx');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
        readfile($filepath);
        unlink($filepath);
    }
    
    logActivity('Export equipment data', 'equipment', null, null, ['type' => $export_type, 'format' => $export_format]);
    exit;
    
} catch (Exception $e) {
    error_log("Error exporting equipment data: " . $e->getMessage());
    setFlashMessage('error', 'Error exporting data: ' . $e->getMessage());
    redirect(BASE_URL . 'modules/equipment/');
}

?>