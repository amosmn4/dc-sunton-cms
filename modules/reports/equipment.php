<?php
/**
 * Equipment Reports
 * Deliverance Church Management System
 * 
 * Generate equipment inventory and maintenance reports
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

// Get report parameters
$reportType = sanitizeInput($_GET['type'] ?? 'inventory');
$export = sanitizeInput($_GET['export'] ?? '');
$format = sanitizeInput($_GET['format'] ?? 'html');

// Page configuration
$page_title = 'Equipment Reports';
$page_icon = 'fas fa-tools';
$breadcrumb = [
    ['title' => 'Reports', 'url' => 'index.php'],
    ['title' => 'Equipment Reports']
];

// Initialize database
$db = Database::getInstance();

// Process filters
$filters = [
    'category' => sanitizeInput($_GET['category'] ?? ''),
    'status' => sanitizeInput($_GET['status'] ?? ''),
    'condition' => sanitizeInput($_GET['condition'] ?? ''),
    'location' => sanitizeInput($_GET['location'] ?? ''),
    'maintenance_due' => sanitizeInput($_GET['maintenance_due'] ?? ''),
    'search' => sanitizeInput($_GET['search'] ?? '')
];

try {
    // Get report data based on type
    switch ($reportType) {
        case 'inventory':
            $reportTitle = 'Equipment Inventory Report';
            $reportData = getEquipmentInventoryData($db, $filters);
            break;
            
        case 'maintenance':
            $reportTitle = 'Maintenance Report';
            $reportData = getMaintenanceReportData($db, $filters);
            break;
            
        case 'condition':
            $reportTitle = 'Equipment Condition Report';
            $reportData = getConditionReportData($db, $filters);
            break;
            
        case 'value':
            $reportTitle = 'Equipment Value Report';
            $reportData = getValueReportData($db, $filters);
            break;
            
        case 'usage':
            $reportTitle = 'Equipment Usage Report';
            $reportData = getUsageReportData($db, $filters);
            break;
            
        default:
            $reportTitle = 'Equipment Inventory Report';
            $reportData = getEquipmentInventoryData($db, $filters);
            break;
    }
    
    // Get additional data for filters
    $categories = $db->executeQuery("SELECT id, name FROM equipment_categories ORDER BY name")->fetchAll();
    $locations = $db->executeQuery("SELECT DISTINCT location FROM equipment WHERE location IS NOT NULL AND location != '' ORDER BY location")->fetchAll();
    
} catch (Exception $e) {
    error_log("Error generating equipment report: " . $e->getMessage());
    setFlashMessage('error', 'Error generating report: ' . $e->getMessage());
    $reportData = [];
    $categories = [];
    $locations = [];
}

/**
 * Get equipment inventory data
 */
function getEquipmentInventoryData($db, $filters) {
    // Build WHERE clause
    $whereConditions = ["1=1"];
    $params = [];
    
    if (!empty($filters['category'])) {
        $whereConditions[] = "e.category_id = ?";
        $params[] = $filters['category'];
    }
    
    if (!empty($filters['status'])) {
        $whereConditions[] = "e.status = ?";
        $params[] = $filters['status'];
    }
    
    if (!empty($filters['location'])) {
        $whereConditions[] = "e.location = ?";
        $params[] = $filters['location'];
    }
    
    if (!empty($filters['search'])) {
        $whereConditions[] = "(e.name LIKE ? OR e.description LIKE ? OR e.equipment_code LIKE ? OR e.serial_number LIKE ?)";
        $searchTerm = '%' . $filters['search'] . '%';
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    
    if (!empty($filters['maintenance_due'])) {
        if ($filters['maintenance_due'] === 'overdue') {
            $whereConditions[] = "e.next_maintenance_date < CURDATE()";
        } elseif ($filters['maintenance_due'] === 'due_soon') {
            $whereConditions[] = "e.next_maintenance_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
        }
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Get equipment list
    $equipmentQuery = "
        SELECT 
            e.*,
            ec.name as category_name,
            m.first_name as responsible_first_name,
            m.last_name as responsible_last_name,
            u.first_name as created_by_first_name,
            u.last_name as created_by_last_name,
            DATEDIFF(e.next_maintenance_date, CURDATE()) as days_to_maintenance,
            CASE 
                WHEN e.next_maintenance_date < CURDATE() THEN 'Overdue'
                WHEN e.next_maintenance_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'Due This Week'
                WHEN e.next_maintenance_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'Due This Month'
                ELSE 'Up to Date'
            END as maintenance_status
        FROM equipment e
        LEFT JOIN equipment_categories ec ON e.category_id = ec.id
        LEFT JOIN members m ON e.responsible_person_id = m.id
        LEFT JOIN users u ON e.created_by = u.id
        WHERE $whereClause
        ORDER BY e.name ASC
    ";
    
    $equipment = $db->executeQuery($equipmentQuery, $params)->fetchAll();
    
    // Summary statistics
    $summaryQuery = "
        SELECT 
            COUNT(*) as total_equipment,
            COUNT(CASE WHEN e.status = 'good' THEN 1 END) as good_condition,
            COUNT(CASE WHEN e.status = 'needs_attention' THEN 1 END) as needs_attention,
            COUNT(CASE WHEN e.status = 'damaged' THEN 1 END) as damaged,
            COUNT(CASE WHEN e.status = 'under_repair' THEN 1 END) as under_repair,
            COUNT(CASE WHEN e.status = 'retired' THEN 1 END) as retired,
            SUM(CASE WHEN e.purchase_price IS NOT NULL THEN e.purchase_price ELSE 0 END) as total_value,
            COUNT(CASE WHEN e.next_maintenance_date < CURDATE() THEN 1 END) as overdue_maintenance,
            COUNT(CASE WHEN e.next_maintenance_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as due_soon_maintenance
        FROM equipment e
        LEFT JOIN equipment_categories ec ON e.category_id = ec.id
        WHERE $whereClause
    ";
    
    $summary = $db->executeQuery($summaryQuery, $params)->fetch();
    
    // Category breakdown
    $categoryQuery = "
        SELECT 
            ec.name as category_name,
            COUNT(e.id) as equipment_count,
            SUM(CASE WHEN e.purchase_price IS NOT NULL THEN e.purchase_price ELSE 0 END) as category_value,
            COUNT(CASE WHEN e.status = 'good' THEN 1 END) as good_count,
            COUNT(CASE WHEN e.status IN ('needs_attention', 'damaged') THEN 1 END) as problem_count
        FROM equipment_categories ec
        LEFT JOIN equipment e ON ec.id = e.category_id
        WHERE ec.id IN (SELECT DISTINCT category_id FROM equipment WHERE $whereClause)
        GROUP BY ec.id, ec.name
        ORDER BY equipment_count DESC
    ";
    
    $categories = $db->executeQuery($categoryQuery, $params)->fetchAll();
    
    return [
        'equipment' => $equipment,
        'summary' => $summary,
        'categories' => $categories
    ];
}

/**
 * Get maintenance report data
 */
function getMaintenanceReportData($db, $filters) {
    // Maintenance history
    $historyQuery = "
        SELECT 
            em.*,
            e.name as equipment_name,
            e.equipment_code,
            ec.name as category_name,
            u.first_name as created_by_first_name,
            u.last_name as created_by_last_name
        FROM equipment_maintenance em
        JOIN equipment e ON em.equipment_id = e.id
        LEFT JOIN equipment_categories ec ON e.category_id = ec.id
        LEFT JOIN users u ON em.created_by = u.id
        WHERE 1=1
        " . (!empty($filters['category']) ? " AND e.category_id = " . intval($filters['category']) : "") . "
        ORDER BY em.maintenance_date DESC
        LIMIT 100
    ";
    
    $maintenanceHistory = $db->executeQuery($historyQuery)->fetchAll();
    
    // Upcoming maintenance
    $upcomingQuery = "
        SELECT 
            e.*,
            ec.name as category_name,
            DATEDIFF(e.next_maintenance_date, CURDATE()) as days_until_due,
            CASE 
                WHEN e.next_maintenance_date < CURDATE() THEN 'Overdue'
                WHEN e.next_maintenance_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'Due This Week'
                WHEN e.next_maintenance_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'Due This Month'
                ELSE 'Future'
            END as urgency
        FROM equipment e
        LEFT JOIN equipment_categories ec ON e.category_id = ec.id
        WHERE e.next_maintenance_date IS NOT NULL
        " . (!empty($filters['category']) ? " AND e.category_id = " . intval($filters['category']) : "") . "
        ORDER BY 
            CASE 
                WHEN e.next_maintenance_date < CURDATE() THEN 1
                WHEN e.next_maintenance_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 2
                WHEN e.next_maintenance_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 3
                ELSE 4
            END,
            e.next_maintenance_date ASC
    ";
    
    $upcomingMaintenance = $db->executeQuery($upcomingQuery)->fetchAll();
    
    // Maintenance costs by month
    $costsQuery = "
        SELECT 
            DATE_FORMAT(em.maintenance_date, '%Y-%m') as month,
            DATE_FORMAT(em.maintenance_date, '%M %Y') as month_name,
            COUNT(em.id) as maintenance_count,
            SUM(em.cost) as total_cost,
            AVG(em.cost) as average_cost
        FROM equipment_maintenance em
        WHERE em.maintenance_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(em.maintenance_date, '%Y-%m')
        ORDER BY month ASC
    ";
    
    $monthlyCosts = $db->executeQuery($costsQuery)->fetchAll();
    
    return [
        'maintenance_history' => $maintenanceHistory,
        'upcoming_maintenance' => $upcomingMaintenance,
        'monthly_costs' => $monthlyCosts
    ];
}

/**
 * Get condition report data
 */
function getConditionReportData($db, $filters) {
    $query = "
        SELECT 
            e.*,
            ec.name as category_name,
            TIMESTAMPDIFF(YEAR, e.purchase_date, CURDATE()) as age_years,
            CASE 
                WHEN e.warranty_expiry > CURDATE() THEN 'Under Warranty'
                ELSE 'Out of Warranty'
            END as warranty_status,
            DATEDIFF(e.warranty_expiry, CURDATE()) as warranty_days_remaining
        FROM equipment e
        LEFT JOIN equipment_categories ec ON e.category_id = ec.id
        WHERE 1=1
        " . (!empty($filters['category']) ? " AND e.category_id = " . intval($filters['category']) : "") . "
        " . (!empty($filters['status']) ? " AND e.status = '" . $filters['status'] . "'" : "") . "
        ORDER BY 
            CASE e.status
                WHEN 'damaged' THEN 1
                WHEN 'needs_attention' THEN 2
                WHEN 'under_repair' THEN 3
                WHEN 'good' THEN 4
                WHEN 'retired' THEN 5
            END,
            e.name ASC
    ";
    
    $equipment = $db->executeQuery($query)->fetchAll();
    
    // Status summary
    $statusSummary = [
        'good' => 0,
        'needs_attention' => 0,
        'damaged' => 0,
        'under_repair' => 0,
        'retired' => 0
    ];
    
    $warrantySummary = [
        'under_warranty' => 0,
        'out_of_warranty' => 0,
        'expiring_soon' => 0
    ];
    
    foreach ($equipment as $item) {
        $statusSummary[$item['status']]++;
        
        if ($item['warranty_expiry'] && $item['warranty_expiry'] > date('Y-m-d')) {
            $warrantySummary['under_warranty']++;
            if ($item['warranty_days_remaining'] <= 90) {
                $warrantySummary['expiring_soon']++;
            }
        } else {
            $warrantySummary['out_of_warranty']++;
        }
    }
    
    return [
        'equipment' => $equipment,
        'status_summary' => $statusSummary,
        'warranty_summary' => $warrantySummary,
        'total_count' => count($equipment)
    ];
}

/**
 * Get value report data
 */
function getValueReportData($db, $filters) {
    $query = "
        SELECT 
            e.*,
            ec.name as category_name,
            TIMESTAMPDIFF(YEAR, e.purchase_date, CURDATE()) as age_years,
            CASE 
                WHEN TIMESTAMPDIFF(YEAR, e.purchase_date, CURDATE()) <= 1 THEN e.purchase_price * 0.8
                WHEN TIMESTAMPDIFF(YEAR, e.purchase_date, CURDATE()) <= 3 THEN e.purchase_price * 0.6
                WHEN TIMESTAMPDIFF(YEAR, e.purchase_date, CURDATE()) <= 5 THEN e.purchase_price * 0.4
                WHEN TIMESTAMPDIFF(YEAR, e.purchase_date, CURDATE()) <= 10 THEN e.purchase_price * 0.2
                ELSE e.purchase_price * 0.1
            END as estimated_current_value
        FROM equipment e
        LEFT JOIN equipment_categories ec ON e.category_id = ec.id
        WHERE e.purchase_price IS NOT NULL
        " . (!empty($filters['category']) ? " AND e.category_id = " . intval($filters['category']) : "") . "
        ORDER BY e.purchase_price DESC
    ";
    
    $equipment = $db->executeQuery($query)->fetchAll();
    
    // Calculate totals
    $totalPurchaseValue = array_sum(array_column($equipment, 'purchase_price'));
    $totalCurrentValue = array_sum(array_column($equipment, 'estimated_current_value'));
    $totalDepreciation = $totalPurchaseValue - $totalCurrentValue;
    
    // Category value breakdown
    $categoryValues = [];
    foreach ($equipment as $item) {
        $category = $item['category_name'] ?: 'Uncategorized';
        if (!isset($categoryValues[$category])) {
            $categoryValues[$category] = [
                'count' => 0,
                'purchase_value' => 0,
                'current_value' => 0
            ];
        }
        $categoryValues[$category]['count']++;
        $categoryValues[$category]['purchase_value'] += $item['purchase_price'];
        $categoryValues[$category]['current_value'] += $item['estimated_current_value'];
    }
    
    return [
        'equipment' => $equipment,
        'summary' => [
            'total_purchase_value' => $totalPurchaseValue,
            'total_current_value' => $totalCurrentValue,
            'total_depreciation' => $totalDepreciation,
            'depreciation_percentage' => $totalPurchaseValue > 0 ? ($totalDepreciation / $totalPurchaseValue) * 100 : 0
        ],
        'category_values' => $categoryValues
    ];
}

/**
 * Get usage report data
 */
function getUsageReportData($db, $filters) {
    // This would require usage tracking data
    // For now, we'll return basic information about equipment usage based on maintenance frequency
    $query = "
        SELECT 
            e.*,
            ec.name as category_name,
            COUNT(em.id) as maintenance_count,
            MAX(em.maintenance_date) as last_maintenance,
            AVG(em.cost) as average_maintenance_cost,
            SUM(em.cost) as total_maintenance_cost,
            TIMESTAMPDIFF(DAY, e.purchase_date, CURDATE()) as days_owned,
            CASE 
                WHEN COUNT(em.id) = 0 THEN 'No maintenance recorded'
                WHEN COUNT(em.id) <= 2 THEN 'Light usage'
                WHEN COUNT(em.id) <= 5 THEN 'Moderate usage'
                ELSE 'Heavy usage'
            END as usage_category
        FROM equipment e
        LEFT JOIN equipment_categories ec ON e.category_id = ec.id
        LEFT JOIN equipment_maintenance em ON e.id = em.equipment_id
        WHERE 1=1
        " . (!empty($filters['category']) ? " AND e.category_id = " . intval($filters['category']) : "") . "
        GROUP BY e.id
        ORDER BY maintenance_count DESC, total_maintenance_cost DESC
    ";
    
    $equipment = $db->executeQuery($query)->fetchAll();
    
    // Usage summary
    $usageSummary = [
        'no_maintenance' => 0,
        'light_usage' => 0,
        'moderate_usage' => 0,
        'heavy_usage' => 0
    ];
    
    foreach ($equipment as $item) {
        switch ($item['usage_category']) {
            case 'No maintenance recorded':
                $usageSummary['no_maintenance']++;
                break;
            case 'Light usage':
                $usageSummary['light_usage']++;
                break;
            case 'Moderate usage':
                $usageSummary['moderate_usage']++;
                break;
            case 'Heavy usage':
                $usageSummary['heavy_usage']++;
                break;
        }
    }
    
    return [
        'equipment' => $equipment,
        'usage_summary' => $usageSummary,
        'total_count' => count($equipment)
    ];
}

include_once '../../includes/header.php';
?>

<!-- Equipment Reports Content -->
<div class="row">
    <!-- Report Filters -->
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="fas fa-filter me-2"></i>
                        Report Filters
                    </h6>
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                        <i class="fas fa-chevron-down"></i>
                    </button>
                </div>
            </div>
            <div class="collapse show" id="filterCollapse">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3">
                        <!-- Report Type -->
                        <div class="col-md-3">
                            <label class="form-label">Report Type</label>
                            <select name="type" class="form-select" onchange="this.form.submit()">
                                <option value="inventory" <?php echo $reportType === 'inventory' ? 'selected' : ''; ?>>Equipment Inventory</option>
                                <option value="maintenance" <?php echo $reportType === 'maintenance' ? 'selected' : ''; ?>>Maintenance Report</option>
                                <option value="condition" <?php echo $reportType === 'condition' ? 'selected' : ''; ?>>Condition Report</option>
                                <option value="value" <?php echo $reportType === 'value' ? 'selected' : ''; ?>>Value Report</option>
                                <option value="usage" <?php echo $reportType === 'usage' ? 'selected' : ''; ?>>Usage Report</option>
                            </select>
                        </div>

                        <!-- Category Filter -->
                        <div class="col-md-3">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-select">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" <?php echo $filters['category'] == $category['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Status Filter -->
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="">All Statuses</option>
                                <?php foreach (EQUIPMENT_STATUS as $key => $status): ?>
                                    <option value="<?php echo $key; ?>" <?php echo $filters['status'] === $key ? 'selected' : ''; ?>>
                                        <?php echo $status; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Location Filter -->
                        <div class="col-md-3">
                            <label class="form-label">Location</label>
                            <select name="location" class="form-select">
                                <option value="">All Locations</option>
                                <?php foreach ($locations as $location): ?>
                                    <option value="<?php echo htmlspecialchars($location['location']); ?>" <?php echo $filters['location'] === $location['location'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($location['location']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Maintenance Due Filter -->
                        <?php if ($reportType === 'inventory'): ?>
                        <div class="col-md-3">
                            <label class="form-label">Maintenance Status</label>
                            <select name="maintenance_due" class="form-select">
                                <option value="">All Equipment</option>
                                <option value="overdue" <?php echo $filters['maintenance_due'] === 'overdue' ? 'selected' : ''; ?>>Overdue Maintenance</option>
                                <option value="due_soon" <?php echo $filters['maintenance_due'] === 'due_soon' ? 'selected' : ''; ?>>Due Soon (30 days)</option>
                            </select>
                        </div>
                        <?php endif; ?>

                        <!-- Search -->
                        <div class="col-md-6">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" placeholder="Equipment name, code, or serial number" value="<?php echo htmlspecialchars($filters['search']); ?>">
                        </div>

                        <!-- Action Buttons -->
                        <div class="col-12">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-church-primary">
                                    <i class="fas fa-search me-2"></i>Generate Report
                                </button>
                                <a href="?" class="btn btn-outline-secondary">
                                    <i class="fas fa-undo me-2"></i>Reset Filters
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Results -->
    <div class="col-12">
        <?php if ($reportType === 'inventory'): ?>
            <!-- Inventory Report -->
            <div class="row mb-4">
                <!-- Summary Cards -->
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-1">Total Equipment</h6>
                                    <h4 class="mb-0"><?php echo number_format($reportData['summary']['total_equipment'] ?? 0); ?></h4>
                                </div>
                                <i class="fas fa-tools fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-1">Good Condition</h6>
                                    <h4 class="mb-0"><?php echo number_format($reportData['summary']['good_condition'] ?? 0); ?></h4>
                                </div>
                                <i class="fas fa-check-circle fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-1">Need Attention</h6>
                                    <h4 class="mb-0"><?php echo number_format($reportData['summary']['needs_attention'] ?? 0); ?></h4>
                                </div>
                                <i class="fas fa-exclamation-triangle fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card bg-danger text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-1">Total Value</h6>
                                    <h4 class="mb-0"><?php echo formatCurrency($reportData['summary']['total_value'] ?? 0); ?></h4>
                                </div>
                                <i class="fas fa-dollar-sign fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Equipment List -->
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>
                            Equipment Inventory
                            <span class="badge bg-primary ms-2"><?php echo count($reportData['equipment'] ?? []); ?> items</span>
                        </h5>
                        
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-success dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="fas fa-download me-2"></i>Export
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['export' => '1', 'format' => 'csv'])); ?>">
                                    <i class="fas fa-file-csv me-2"></i>Export as CSV
                                </a></li>
                                <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['export' => '1', 'format' => 'excel'])); ?>">
                                    <i class="fas fa-file-excel me-2"></i>Export as Excel
                                </a></li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="card-body">
                    <?php if (empty($reportData['equipment'] ?? [])): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-search fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No Equipment Found</h5>
                            <p class="text-muted">No equipment matches your current filter criteria.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover data-table">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Name</th>
                                        <th>Category</th>
                                        <th>Status</th>
                                        <th>Location</th>
                                        <th>Purchase Date</th>
                                        <th>Value</th>
                                        <th>Maintenance</th>
                                        <th>Responsible</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportData['equipment'] as $equipment): ?>
                                        <tr>
                                            <td><code><?php echo htmlspecialchars($equipment['equipment_code']); ?></code></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($equipment['name']); ?></strong>
                                                <?php if (!empty($equipment['brand']) || !empty($equipment['model'])): ?>
                                                    <br><small class="text-muted">
                                                        <?php echo htmlspecialchars(trim($equipment['brand'] . ' ' . $equipment['model'])); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($equipment['category_name'] ?? 'Uncategorized'); ?></span>
                                            </td>
                                            <td>
                                                <?php
                                                $statusClass = [
                                                    'good' => 'success',
                                                    'needs_attention' => 'warning',
                                                    'damaged' => 'danger',
                                                    'under_repair' => 'info',
                                                    'retired' => 'dark'
                                                ];
                                                $class = $statusClass[$equipment['status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $class; ?>">
                                                    <?php echo ucwords(str_replace('_', ' ', $equipment['status'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($equipment['location'] ?: '-'); ?></td>
                                            <td><?php echo formatDisplayDate($equipment['purchase_date']); ?></td>
                                            <td><?php echo $equipment['purchase_price'] ? formatCurrency($equipment['purchase_price']) : '-'; ?></td>
                                            <td>
                                                <?php if (!empty($equipment['next_maintenance_date'])): ?>
                                                    <?php
                                                    $maintenanceClass = [
                                                        'Overdue' => 'danger',
                                                        'Due This Week' => 'warning',
                                                        'Due This Month' => 'info',
                                                        'Up to Date' => 'success'
                                                    ];
                                                    $class = $maintenanceClass[$equipment['maintenance_status']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $class; ?>"><?php echo $equipment['maintenance_status']; ?></span>
                                                    <br><small class="text-muted"><?php echo formatDisplayDate($equipment['next_maintenance_date']); ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">Not scheduled</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($equipment['responsible_first_name'])): ?>
                                                    <?php echo htmlspecialchars($equipment['responsible_first_name'] . ' ' . $equipment['responsible_last_name']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Not assigned</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($reportType === 'maintenance'): ?>
            <!-- Maintenance Report -->
            <div class="row">
                <!-- Upcoming Maintenance -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-calendar-alt me-2"></i>
                                Upcoming Maintenance
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($reportData['upcoming_maintenance'])): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Equipment</th>
                                                <th>Due Date</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (array_slice($reportData['upcoming_maintenance'], 0, 10) as $equipment): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($equipment['name']); ?></strong>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($equipment['category_name']); ?></small>
                                                    </td>
                                                    <td><?php echo formatDisplayDate($equipment['next_maintenance_date']); ?></td>
                                                    <td>
                                                        <?php
                                                        $urgencyClass = [
                                                            'Overdue' => 'danger',
                                                            'Due This Week' => 'warning',
                                                            'Due This Month' => 'info',
                                                            'Future' => 'secondary'
                                                        ];
                                                        $class = $urgencyClass[$equipment['urgency']] ?? 'secondary';
                                                        ?>
                                                        <span class="badge bg-<?php echo $class; ?>"><?php echo $equipment['urgency']; ?></span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-muted py-3">
                                    <i class="fas fa-calendar-check fa-2x mb-2"></i>
                                    <p>No upcoming maintenance scheduled</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Maintenance History -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-history me-2"></i>
                                Recent Maintenance
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($reportData['maintenance_history'])): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Equipment</th>
                                                <th>Date</th>
                                                <th>Type</th>
                                                <th>Cost</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (array_slice($reportData['maintenance_history'], 0, 10) as $maintenance): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($maintenance['equipment_name']); ?></strong>
                                                    </td>
                                                    <td><?php echo formatDisplayDate($maintenance['maintenance_date']); ?></td>
                                                    <td>
                                                        <span class="badge bg-info"><?php echo ucwords(str_replace('_', ' ', $maintenance['maintenance_type'])); ?></span>
                                                    </td>
                                                    <td><?php echo $maintenance['cost'] ? formatCurrency($maintenance['cost']) : '-'; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-muted py-3">
                                    <i class="fas fa-tools fa-2x mb-2"></i>
                                    <p>No maintenance history found</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Monthly Costs Chart -->
            <?php if (!empty($reportData['monthly_costs'])): ?>
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-chart-bar me-2"></i>
                            Monthly Maintenance Costs
                        </h6>
                    </div>
                    <div class="card-body">
                        <canvas id="maintenanceCostsChart" height="100"></canvas>
                    </div>
                </div>
            <?php endif; ?>

        <?php elseif ($reportType === 'condition'): ?>
            <!-- Condition Report -->
            <div class="row mb-4">
                <!-- Status Summary -->
                <div class="col-md-8">
                    <div class="row">
                        <?php foreach ($reportData['status_summary'] as $status => $count): ?>
                            <div class="col-md-4 mb-3">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-body text-center">
                                        <h4 class="text-<?php echo $status === 'good' ? 'success' : ($status === 'damaged' ? 'danger' : 'warning'); ?>">
                                            <?php echo number_format($count); ?>
                                        </h4>
                                        <p class="mb-0 text-muted"><?php echo ucwords(str_replace('_', ' ', $status)); ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Status Chart -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Equipment Status</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="statusChart" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Equipment Details -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-clipboard-check me-2"></i>
                        Equipment Condition Details
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover data-table">
                            <thead>
                                <tr>
                                    <th>Equipment</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Age</th>
                                    <th>Warranty</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportData['equipment'] as $equipment): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($equipment['name']); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($equipment['equipment_code']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($equipment['category_name'] ?? '-'); ?></td>
                                        <td>
                                            <?php
                                            $statusClass = [
                                                'good' => 'success',
                                                'needs_attention' => 'warning',
                                                'damaged' => 'danger',
                                                'under_repair' => 'info',
                                                'retired' => 'dark'
                                            ];
                                            $class = $statusClass[$equipment['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $class; ?>">
                                                <?php echo ucwords(str_replace('_', ' ', $equipment['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($equipment['age_years']): ?>
                                                <?php echo $equipment['age_years']; ?> years
                                            <?php else: ?>
                                                <span class="text-muted">Unknown</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($equipment['warranty_expiry']): ?>
                                                <?php if ($equipment['warranty_status'] === 'Under Warranty'): ?>
                                                    <span class="badge bg-success">Under Warranty</span>
                                                    <?php if ($equipment['warranty_days_remaining'] <= 90): ?>
                                                        <br><small class="text-warning">Expires in <?php echo $equipment['warranty_days_remaining']; ?> days</small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Expired</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">No warranty</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($equipment['condition_notes'])): ?>
                                                <?php echo htmlspecialchars(truncateText($equipment['condition_notes'], 100)); ?>
                                            <?php else: ?>
                                                <span class="text-muted">No notes</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php elseif ($reportType === 'value'): ?>
            <!-- Value Report -->
            <div class="row mb-4">
                <!-- Value Summary -->
                <div class="col-md-6">
                    <div class="card bg-gradient-church text-white">
                        <div class="card-body">
                            <h5 class="card-title">Equipment Value Summary</h5>
                            <div class="row text-center">
                                <div class="col-6">
                                    <h3><?php echo formatCurrency($reportData['summary']['total_purchase_value']); ?></h3>
                                    <p class="mb-0">Purchase Value</p>
                                </div>
                                <div class="col-6">
                                    <h3><?php echo formatCurrency($reportData['summary']['total_current_value']); ?></h3>
                                    <p class="mb-0">Current Value</p>
                                </div>
                            </div>
                            <hr class="my-3">
                            <div class="text-center">
                                <p class="mb-1">Total Depreciation</p>
                                <h4><?php echo formatCurrency($reportData['summary']['total_depreciation']); ?></h4>
                                <small>(<?php echo number_format($reportData['summary']['depreciation_percentage'], 1); ?>% depreciated)</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Category Values -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Value by Category</h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($reportData['category_values'])): ?>
                                <?php foreach ($reportData['category_values'] as $category => $data): ?>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <strong><?php echo htmlspecialchars($category); ?></strong>
                                            <span><?php echo formatCurrency($data['purchase_value']); ?></span>
                                        </div>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-church-blue" role="progressbar" 
                                                 style="width: <?php echo ($data['purchase_value'] / $reportData['summary']['total_purchase_value']) * 100; ?>%">
                                                <?php echo $data['count']; ?> items
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- JavaScript for Charts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTables
    if (typeof $ !== 'undefined' && $.fn.DataTable && $('.data-table').length) {
        $('.data-table').DataTable({
            responsive: true,
            pageLength: 25,
            order: [[0, 'asc']], // Sort by equipment code
            buttons: ['copy', 'excel', 'pdf', 'print'],
            dom: 'Bfrtip'
        });
    }
    
    <?php if ($reportType === 'maintenance' && !empty($reportData['monthly_costs'])): ?>
    // Maintenance Costs Chart
    const costsCtx = document.getElementById('maintenanceCostsChart');
    if (costsCtx) {
        const costsData = <?php echo json_encode(array_column($reportData['monthly_costs'], 'total_cost')); ?>;
        const costsLabels = <?php echo json_encode(array_column($reportData['monthly_costs'], 'month_name')); ?>;
        
        new Chart(costsCtx, {
            type: 'bar',
            data: {
                labels: costsLabels,
                datasets: [{
                    label: 'Maintenance Cost',
                    data: costsData,
                    backgroundColor: 'rgba(255, 36, 0, 0.8)',
                    borderColor: '#ff2400',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '<?php echo CURRENCY_SYMBOL; ?> ' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }
    <?php endif; ?>
    
    <?php if ($reportType === 'condition'): ?>
    // Status Chart
    const statusCtx = document.getElementById('statusChart');
    if (statusCtx) {
        const statusData = <?php echo json_encode(array_values($reportData['status_summary'])); ?>;
        const statusLabels = <?php echo json_encode(array_map(function($key) { 
            return ucwords(str_replace('_', ' ', $key)); 
        }, array_keys($reportData['status_summary']))); ?>;
        
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusData,
                    backgroundColor: ['#28a745', '#ffc107', '#dc3545', '#17a2b8', '#343a40'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: {
                                size: 10
                            }
                        }
                    }
                }
            }
        });
    }
    <?php endif; ?>
});
</script>

<?php include_once '../../includes/footer.php'; ?>