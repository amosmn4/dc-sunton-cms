<?php
/**
 * Equipment Inventory Main Page
 * Deliverance Church Management System
 */

// Include necessary files
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Check authentication and permissions
requireLogin();
if (!hasPermission('equipment')) {
    setFlashMessage('error', 'You do not have permission to access this page.');
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit;
}

// Page variables
$page_title = 'Equipment Inventory';
$page_icon = 'fas fa-tools';
$page_description = 'Manage church equipment inventory and maintenance records';

// Breadcrumb
$breadcrumb = [
    ['title' => 'Equipment Inventory']
];

// Page actions
$page_actions = [
    [
        'title' => 'Add Equipment',
        'url' => BASE_URL . 'modules/equipment/add.php',
        'icon' => 'fas fa-plus',
        'class' => 'church-primary'
    ],
    [
        'title' => 'Categories',
        'url' => BASE_URL . 'modules/equipment/categories.php',
        'icon' => 'fas fa-tags',
        'class' => 'secondary'
    ],
    [
        'title' => 'Maintenance Schedule',
        'url' => BASE_URL . 'modules/equipment/maintenance.php',
        'icon' => 'fas fa-calendar-alt',
        'class' => 'info'
    ]
];

// Get filter parameters
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 25;
$offset = ($page - 1) * $limit;

try {
    $db = Database::getInstance();
    
    // Build query with filters
    $whereConditions = [];
    $params = [];
    
    if ($category_filter > 0) {
        $whereConditions[] = "e.category_id = ?";
        $params[] = $category_filter;
    }
    
    if (!empty($status_filter)) {
        $whereConditions[] = "e.status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($search)) {
        $whereConditions[] = "(e.name LIKE ? OR e.equipment_code LIKE ? OR e.brand LIKE ? OR e.model LIKE ?)";
        $searchTerm = "%{$search}%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total 
                   FROM equipment e 
                   LEFT JOIN equipment_categories ec ON e.category_id = ec.id 
                   {$whereClause}";
    $countStmt = $db->executeQuery($countQuery, $params);
    $totalRecords = $countStmt->fetch()['total'];
    
    // Get equipment records
    $query = "SELECT e.*, ec.name as category_name, 
                     m.first_name as responsible_first_name, 
                     m.last_name as responsible_last_name,
                     CASE 
                        WHEN e.next_maintenance_date < CURDATE() THEN 'overdue'
                        WHEN e.next_maintenance_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'due_soon'
                        ELSE 'ok'
                     END as maintenance_status
              FROM equipment e
              LEFT JOIN equipment_categories ec ON e.category_id = ec.id
              LEFT JOIN members m ON e.responsible_person_id = m.id
              {$whereClause}
              ORDER BY e.name ASC
              LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->executeQuery($query, $params);
    $equipment = $stmt->fetchAll();
    
    // Get categories for filter dropdown
    $categoriesStmt = $db->executeQuery("SELECT * FROM equipment_categories ORDER BY name");
    $categories = $categoriesStmt->fetchAll();
    
    // Calculate pagination
    $totalPages = ceil($totalRecords / $limit);
    
    // Get summary statistics
    $statsQuery = "SELECT 
                    COUNT(*) as total_equipment,
                    SUM(CASE WHEN status = 'good' THEN 1 ELSE 0 END) as good_condition,
                    SUM(CASE WHEN status = 'needs_attention' THEN 1 ELSE 0 END) as needs_attention,
                    SUM(CASE WHEN status = 'damaged' THEN 1 ELSE 0 END) as damaged,
                    SUM(CASE WHEN next_maintenance_date < CURDATE() THEN 1 ELSE 0 END) as maintenance_overdue,
                    SUM(CASE WHEN next_maintenance_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND next_maintenance_date >= CURDATE() THEN 1 ELSE 0 END) as maintenance_due_soon
                   FROM equipment";
    $statsStmt = $db->executeQuery($statsQuery);
    $stats = $statsStmt->fetch();
    
} catch (Exception $e) {
    error_log("Equipment inventory error: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred while loading equipment data.');
    $equipment = [];
    $stats = [];
    $totalRecords = 0;
    $totalPages = 0;
}

// Include header
include '../../includes/header.php';
?>

<div class="container-fluid px-4">
    <!-- Summary Statistics -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="stats-card bg-white">
                <div class="d-flex align-items-center">
                    <div class="stats-icon bg-primary">
                        <i class="fas fa-tools"></i>
                    </div>
                    <div>
                        <div class="stats-number"><?php echo $stats['total_equipment'] ?? 0; ?></div>
                        <div class="stats-label">Total Equipment</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6">
            <div class="stats-card bg-white">
                <div class="d-flex align-items-center">
                    <div class="stats-icon bg-success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div>
                        <div class="stats-number"><?php echo $stats['good_condition'] ?? 0; ?></div>
                        <div class="stats-label">Good Condition</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6">
            <div class="stats-card bg-white">
                <div class="d-flex align-items-center">
                    <div class="stats-icon bg-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div>
                        <div class="stats-number"><?php echo $stats['needs_attention'] ?? 0; ?></div>
                        <div class="stats-label">Needs Attention</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6">
            <div class="stats-card bg-white">
                <div class="d-flex align-items-center">
                    <div class="stats-icon bg-danger">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div>
                        <div class="stats-number"><?php echo ($stats['maintenance_overdue'] ?? 0) + ($stats['maintenance_due_soon'] ?? 0); ?></div>
                        <div class="stats-label">Maintenance Due</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters and Search -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="category" class="form-label">Category</label>
                    <select name="category" id="category" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select name="status" id="status" class="form-select">
                        <option value="">All Status</option>
                        <?php foreach (EQUIPMENT_STATUS as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo $status_filter === $key ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" name="search" id="search" class="form-control" 
                           placeholder="Search by name, code, brand, or model" 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="col-md-2 d-flex align-items-end">
                    <div class="btn-group w-100">
                        <button type="submit" class="btn btn-church-primary">
                            <i class="fas fa-search me-1"></i>Search
                        </button>
                        <a href="<?php echo BASE_URL; ?>modules/equipment/" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Equipment Table -->
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>Equipment Inventory
                    <?php if ($totalRecords > 0): ?>
                        <small class="text-white-50">(<?php echo number_format($totalRecords); ?> items)</small>
                    <?php endif; ?>
                </h5>
                
                <div class="btn-group">
                    <button class="btn btn-sm btn-outline-light" onclick="exportData('excel')">
                        <i class="fas fa-file-excel me-1"></i>Excel
                    </button>
                    <button class="btn btn-sm btn-outline-light" onclick="exportData('pdf')">
                        <i class="fas fa-file-pdf me-1"></i>PDF
                    </button>
                    <button class="btn btn-sm btn-outline-light" onclick="window.print()">
                        <i class="fas fa-print me-1"></i>Print
                    </button>
                </div>
            </div>
        </div>
        
        <div class="card-body p-0">
            <?php if (!empty($equipment)): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Equipment Code</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Brand/Model</th>
                                <th>Status</th>
                                <th>Responsible Person</th>
                                <th>Next Maintenance</th>
                                <th class="text-center no-print">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($equipment as $item): ?>
                                <tr>
                                    <td>
                                        <strong class="text-church-blue"><?php echo htmlspecialchars($item['equipment_code']); ?></strong>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                                            <?php if (!empty($item['location'])): ?>
                                                <br><small class="text-muted">
                                                    <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($item['location']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($item['category_name'] ?? 'No Category'); ?></td>
                                    <td>
                                        <?php if (!empty($item['brand']) || !empty($item['model'])): ?>
                                            <div><?php echo htmlspecialchars($item['brand']); ?></div>
                                            <?php if (!empty($item['model'])): ?>
                                                <small class="text-muted"><?php echo htmlspecialchars($item['model']); ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $statusClass = [
                                            'good' => 'success',
                                            'needs_attention' => 'warning',
                                            'damaged' => 'danger',
                                            'operational' => 'info',
                                            'under_repair' => 'warning',
                                            'retired' => 'secondary'
                                        ];
                                        $class = $statusClass[$item['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $class; ?>">
                                            <?php echo EQUIPMENT_STATUS[$item['status']] ?? ucfirst($item['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($item['responsible_first_name'])): ?>
                                            <?php echo htmlspecialchars($item['responsible_first_name'] . ' ' . $item['responsible_last_name']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($item['next_maintenance_date']) && $item['next_maintenance_date'] !== '0000-00-00'): ?>
                                            <?php
                                            $maintenanceClass = [
                                                'overdue' => 'danger',
                                                'due_soon' => 'warning',
                                                'ok' => 'muted'
                                            ];
                                            $class = $maintenanceClass[$item['maintenance_status']] ?? 'muted';
                                            ?>
                                            <span class="text-<?php echo $class; ?>">
                                                <?php echo formatDisplayDate($item['next_maintenance_date']); ?>
                                                <?php if ($item['maintenance_status'] === 'overdue'): ?>
                                                    <i class="fas fa-exclamation-triangle ms-1"></i>
                                                <?php elseif ($item['maintenance_status'] === 'due_soon'): ?>
                                                    <i class="fas fa-clock ms-1"></i>
                                                <?php endif; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">Not scheduled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center no-print">
                                        <div class="btn-group btn-group-sm">
                                            <a href="view.php?id=<?php echo $item['id']; ?>" 
                                               class="btn btn-outline-primary" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo $item['id']; ?>" 
                                               class="btn btn-outline-secondary" title="Edit Equipment">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="maintenance.php?equipment_id=<?php echo $item['id']; ?>" 
                                               class="btn btn-outline-info" title="Maintenance History">
                                                <i class="fas fa-wrench"></i>
                                            </a>
                                            <?php if ($_SESSION['user_role'] === 'administrator'): ?>
                                            <button type="button" class="btn btn-outline-danger confirm-delete" 
                                                    onclick="deleteEquipment(<?php echo $item['id']; ?>)" title="Delete Equipment">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="card-footer">
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                Showing <?php echo (($page - 1) * $limit) + 1; ?> to <?php echo min($page * $limit, $totalRecords); ?> 
                                of <?php echo number_format($totalRecords); ?> entries
                            </small>
                            
                            <nav>
                                <ul class="pagination pagination-sm mb-0">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                                <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-tools fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Equipment Found</h5>
                    <p class="text-muted">
                        <?php if (!empty($search) || !empty($status_filter) || !empty($category_filter)): ?>
                            No equipment matches your search criteria. <a href="<?php echo BASE_URL; ?>modules/equipment/">Clear filters</a> to see all equipment.
                        <?php else: ?>
                            Start building your equipment inventory by adding your first piece of equipment.
                        <?php endif; ?>
                    </p>
                    <a href="<?php echo BASE_URL; ?>modules/equipment/add.php" class="btn btn-church-primary">
                        <i class="fas fa-plus me-2"></i>Add First Equipment
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Delete equipment function
function deleteEquipment(id) {
    ChurchCMS.showConfirm(
        'Are you sure you want to delete this equipment? This action cannot be undone and will also delete all maintenance records.',
        function() {
            ChurchCMS.showLoading('Deleting equipment...');
            
            fetch(`delete.php?id=${id}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                ChurchCMS.hideLoading();
                
                if (data.success) {
                    ChurchCMS.showToast('Equipment deleted successfully', 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    ChurchCMS.showToast(data.message || 'Failed to delete equipment', 'error');
                }
            })
            .catch(error => {
                ChurchCMS.hideLoading();
                ChurchCMS.showToast('An error occurred while deleting equipment', 'error');
                console.error('Error:', error);
            });
        }
    );
}

// Export data function
function exportData(format) {
    const params = new URLSearchParams(window.location.search);
    params.set('export', format);
    window.open(`export.php?${params.toString()}`, '_blank');
}

// Auto-refresh data every 5 minutes
setInterval(() => {
    // Check for maintenance alerts
    fetch('maintenance_alerts.php')
        .then(response => response.json())
        .then(data => {
            if (data.alerts && data.alerts.length > 0) {
                data.alerts.forEach(alert => {
                    ChurchCMS.showToast(alert.message, 'warning', 10000);
                });
            }
        })
        .catch(error => {
            console.error('Error checking maintenance alerts:', error);
        });
}, 300000); // 5 minutes

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<?php include '../../includes/footer.php'; ?>