<?php
/**
 * Equipment Maintenance Management Page
 * Deliverance Church Management System
 */

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
$page_title = 'Equipment Maintenance';
$page_icon = 'fas fa-wrench';

// Breadcrumb
$breadcrumb = [
    ['title' => 'Equipment', 'url' => BASE_URL . 'modules/equipment/'],
    ['title' => 'Maintenance']
];

// Page actions
$page_actions = [
    [
        'title' => 'Add Maintenance',
        'url' => BASE_URL . 'modules/equipment/add_maintenance.php',
        'icon' => 'fas fa-plus',
        'class' => 'church-primary'
    ],
    [
        'title' => 'Maintenance Schedule',
        'url' => BASE_URL . 'modules/equipment/maintenance_schedule.php',
        'icon' => 'fas fa-calendar-alt',
        'class' => 'info'
    ]
];

// Get filter parameters
$equipment_filter = isset($_GET['equipment_id']) ? (int)$_GET['equipment_id'] : 0;
$type_filter = isset($_GET['type']) ? sanitizeInput($_GET['type']) : '';
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$date_from = isset($_GET['date_from']) ? sanitizeInput($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitizeInput($_GET['date_to']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 25;
$offset = ($page - 1) * $limit;

try {
    $db = Database::getInstance();
    
    // Build query with filters
    $whereConditions = [];
    $params = [];
    
    if ($equipment_filter > 0) {
        $whereConditions[] = "em.equipment_id = ?";
        $params[] = $equipment_filter;
    }
    
    if (!empty($type_filter)) {
        $whereConditions[] = "em.maintenance_type = ?";
        $params[] = $type_filter;
    }
    
    if (!empty($status_filter)) {
        $whereConditions[] = "em.status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($date_from)) {
        $whereConditions[] = "em.maintenance_date >= ?";
        $params[] = $date_from;
    }
    
    if (!empty($date_to)) {
        $whereConditions[] = "em.maintenance_date <= ?";
        $params[] = $date_to;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total 
                   FROM equipment_maintenance em 
                   LEFT JOIN equipment e ON em.equipment_id = e.id
                   {$whereClause}";
    $countStmt = $db->executeQuery($countQuery, $params);
    $totalRecords = $countStmt->fetch()['total'];
    
    // Get maintenance records
    $query = "SELECT em.*, e.name as equipment_name, e.equipment_code, e.location,
                     u.first_name as recorded_by_first_name, u.last_name as recorded_by_last_name
              FROM equipment_maintenance em
              LEFT JOIN equipment e ON em.equipment_id = e.id
              LEFT JOIN users u ON em.created_by = u.id
              {$whereClause}
              ORDER BY em.maintenance_date DESC
              LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->executeQuery($query, $params);
    $maintenanceRecords = $stmt->fetchAll();
    
    // Get equipment list for filter dropdown
    $equipmentStmt = $db->executeQuery("SELECT id, name, equipment_code FROM equipment ORDER BY name");
    $equipmentList = $equipmentStmt->fetchAll();
    
    // Calculate pagination
    $totalPages = ceil($totalRecords / $limit);
    
    // Get summary statistics
    $statsQuery = "SELECT 
                    COUNT(*) as total_maintenance,
                    SUM(CASE WHEN maintenance_type = 'preventive' THEN 1 ELSE 0 END) as preventive_count,
                    SUM(CASE WHEN maintenance_type = 'corrective' THEN 1 ELSE 0 END) as corrective_count,
                    SUM(CASE WHEN maintenance_type = 'emergency' THEN 1 ELSE 0 END) as emergency_count,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count,
                    SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled_count,
                    SUM(cost) as total_cost,
                    AVG(cost) as average_cost
                   FROM equipment_maintenance
                   WHERE maintenance_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)";
    $statsStmt = $db->executeQuery($statsQuery);
    $stats = $statsStmt->fetch();
    
    // Get overdue maintenance count
    $overdueStmt = $db->executeQuery("
        SELECT COUNT(*) as overdue_count 
        FROM equipment 
        WHERE next_maintenance_date < CURDATE() 
        AND next_maintenance_date IS NOT NULL
    ");
    $overdueCount = $overdueStmt->fetch()['overdue_count'];
    
    // Get due soon maintenance count (next 30 days)
    $dueSoonStmt = $db->executeQuery("
        SELECT COUNT(*) as due_soon_count 
        FROM equipment 
        WHERE next_maintenance_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ");
    $dueSoonCount = $dueSoonStmt->fetch()['due_soon_count'];
    
} catch (Exception $e) {
    error_log("Equipment maintenance error: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred while loading maintenance data.');
    $maintenanceRecords = [];
    $equipmentList = [];
    $stats = [];
    $totalRecords = 0;
    $totalPages = 0;
    $overdueCount = 0;
    $dueSoonCount = 0;
}

include '../../includes/header.php';
?>

<div class="container-fluid px-4">
    <!-- Alert Cards -->
    <?php if ($overdueCount > 0 || $dueSoonCount > 0): ?>
    <div class="row mb-4">
        <?php if ($overdueCount > 0): ?>
        <div class="col-md-6">
            <div class="alert alert-danger d-flex align-items-center">
                <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                <div>
                    <h5 class="alert-heading mb-1">Overdue Maintenance</h5>
                    <p class="mb-0">
                        <strong><?php echo $overdueCount; ?></strong> equipment item(s) have overdue maintenance.
                        <a href="<?php echo BASE_URL; ?>modules/equipment/maintenance_schedule.php?filter=overdue" 
                           class="alert-link">View Details</a>
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($dueSoonCount > 0): ?>
        <div class="col-md-6">
            <div class="alert alert-warning d-flex align-items-center">
                <i class="fas fa-clock fa-2x me-3"></i>
                <div>
                    <h5 class="alert-heading mb-1">Maintenance Due Soon</h5>
                    <p class="mb-0">
                        <strong><?php echo $dueSoonCount; ?></strong> equipment item(s) need maintenance within 30 days.
                        <a href="<?php echo BASE_URL; ?>modules/equipment/maintenance_schedule.php?filter=due_soon" 
                           class="alert-link">View Schedule</a>
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Summary Statistics -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="stats-card bg-white">
                <div class="d-flex align-items-center">
                    <div class="stats-icon bg-primary">
                        <i class="fas fa-wrench"></i>
                    </div>
                    <div>
                        <div class="stats-number"><?php echo $stats['total_maintenance'] ?? 0; ?></div>
                        <div class="stats-label">Total Maintenance (12mo)</div>
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
                        <div class="stats-number"><?php echo $stats['completed_count'] ?? 0; ?></div>
                        <div class="stats-label">Completed</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6">
            <div class="stats-card bg-white">
                <div class="d-flex align-items-center">
                    <div class="stats-icon bg-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div>
                        <div class="stats-number"><?php echo $stats['emergency_count'] ?? 0; ?></div>
                        <div class="stats-label">Emergency Repairs</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6">
            <div class="stats-card bg-white">
                <div class="d-flex align-items-center">
                    <div class="stats-icon bg-warning">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div>
                        <div class="stats-number"><?php echo formatCurrency($stats['total_cost'] ?? 0); ?></div>
                        <div class="stats-label">Total Cost (12mo)</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="equipment_id" class="form-label">Equipment</label>
                    <select name="equipment_id" id="equipment_id" class="form-select">
                        <option value="">All Equipment</option>
                        <?php foreach ($equipmentList as $equipment): ?>
                            <option value="<?php echo $equipment['id']; ?>" <?php echo $equipment_filter == $equipment['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($equipment['equipment_code'] . ' - ' . $equipment['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="type" class="form-label">Type</label>
                    <select name="type" id="type" class="form-select">
                        <option value="">All Types</option>
                        <?php foreach (MAINTENANCE_TYPES as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo $type_filter === $key ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="status" class="form-label">Status</label>
                    <select name="status" id="status" class="form-select">
                        <option value="">All Status</option>
                        <?php foreach (MAINTENANCE_STATUS as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo $status_filter === $key ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="date_from" class="form-label">Date From</label>
                    <input type="date" name="date_from" id="date_from" class="form-control" 
                           value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                
                <div class="col-md-2">
                    <label for="date_to" class="form-label">Date To</label>
                    <input type="date" name="date_to" id="date_to" class="form-control" 
                           value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                
                <div class="col-md-1 d-flex align-items-end">
                    <div class="btn-group w-100">
                        <button type="submit" class="btn btn-church-primary">
                            <i class="fas fa-search"></i>
                        </button>
                        <a href="<?php echo BASE_URL; ?>modules/equipment/maintenance.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Maintenance Records Table -->
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>Maintenance Records
                    <?php if ($totalRecords > 0): ?>
                        <small class="text-white-50">(<?php echo number_format($totalRecords); ?> records)</small>
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
            <?php if (!empty($maintenanceRecords)): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Equipment</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th>Performed By</th>
                                <th>Status</th>
                                <th>Cost</th>
                                <th class="text-center no-print">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($maintenanceRecords as $record): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?php echo formatDisplayDate($record['maintenance_date']); ?></strong>
                                            <br><small class="text-muted"><?php echo timeAgo($record['maintenance_date']); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($record['equipment_name']); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($record['equipment_code']); ?></small>
                                            <?php if (!empty($record['location'])): ?>
                                                <br><small class="text-muted">
                                                    <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($record['location']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $typeClass = [
                                            'preventive' => 'success',
                                            'corrective' => 'info',
                                            'emergency' => 'danger',
                                            'inspection' => 'secondary'
                                        ];
                                        $class = $typeClass[$record['maintenance_type']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $class; ?>">
                                            <?php echo MAINTENANCE_TYPES[$record['maintenance_type']] ?? ucfirst($record['maintenance_type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div>
                                            <?php echo htmlspecialchars(truncateText($record['description'], 80)); ?>
                                            <?php if (!empty($record['parts_replaced'])): ?>
                                                <br><small class="text-muted">
                                                    <strong>Parts:</strong> <?php echo htmlspecialchars(truncateText($record['parts_replaced'], 50)); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <?php echo htmlspecialchars($record['performed_by'] ?: 'Not specified'); ?>
                                            <?php if (!empty($record['recorded_by_first_name'])): ?>
                                                <br><small class="text-muted">
                                                    Recorded by: <?php echo htmlspecialchars($record['recorded_by_first_name'] . ' ' . $record['recorded_by_last_name']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $statusClass = [
                                            'completed' => 'success',
                                            'in_progress' => 'warning',
                                            'scheduled' => 'info'
                                        ];
                                        $class = $statusClass[$record['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $class; ?>">
                                            <?php echo MAINTENANCE_STATUS[$record['status']] ?? ucfirst($record['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($record['cost'] > 0): ?>
                                            <strong><?php echo formatCurrency($record['cost']); ?></strong>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center no-print">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary" 
                                                    onclick="viewMaintenance(<?php echo $record['id']; ?>)" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <a href="edit_maintenance.php?id=<?php echo $record['id']; ?>" 
                                               class="btn btn-outline-secondary" title="Edit Maintenance">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="<?php echo BASE_URL; ?>modules/equipment/view.php?id=<?php echo $record['equipment_id']; ?>" 
                                               class="btn btn-outline-info" title="View Equipment">
                                                <i class="fas fa-tools"></i>
                                            </a>
                                            <?php if ($_SESSION['user_role'] === 'administrator'): ?>
                                            <button type="button" class="btn btn-outline-danger confirm-delete" 
                                                    onclick="deleteMaintenance(<?php echo $record['id']; ?>)" title="Delete Record">
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
                    <i class="fas fa-wrench fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Maintenance Records Found</h5>
                    <p class="text-muted">
                        <?php if (!empty($_GET) && (array_filter($_GET))): ?>
                            No maintenance records match your search criteria. <a href="<?php echo BASE_URL; ?>modules/equipment/maintenance.php">Clear filters</a> to see all records.
                        <?php else: ?>
                            Start tracking equipment maintenance by adding your first maintenance record.
                        <?php endif; ?>
                    </p>
                    <a href="<?php echo BASE_URL; ?>modules/equipment/add_maintenance.php" class="btn btn-church-primary">
                        <i class="fas fa-plus me-2"></i>Add Maintenance Record
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Maintenance Details Modal -->
<div class="modal fade" id="maintenanceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-church-blue text-white">
                <h5 class="modal-title">
                    <i class="fas fa-wrench me-2"></i>Maintenance Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="maintenance-details">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-church-primary" id="edit-maintenance-btn">
                    <i class="fas fa-edit me-1"></i>Edit Maintenance
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// View maintenance details
function viewMaintenance(id) {
    const modal = new bootstrap.Modal(document.getElementById('maintenanceModal'));
    const detailsDiv = document.getElementById('maintenance-details');
    const editBtn = document.getElementById('edit-maintenance-btn');
    
    // Show loading state
    detailsDiv.innerHTML = `
        <div class="text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    `;
    
    modal.show();
    
    // Fetch maintenance details
    fetch(`maintenance_details.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const maintenance = data.maintenance;
                detailsDiv.innerHTML = `
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr><td class="fw-bold">Equipment:</td><td>${maintenance.equipment_name} (${maintenance.equipment_code})</td></tr>
                                <tr><td class="fw-bold">Date:</td><td>${ChurchCMS.formatDate(maintenance.maintenance_date)}</td></tr>
                                <tr><td class="fw-bold">Type:</td><td><span class="badge bg-info">${maintenance.maintenance_type}</span></td></tr>
                                <tr><td class="fw-bold">Status:</td><td><span class="badge bg-success">${maintenance.status}</span></td></tr>
                                <tr><td class="fw-bold">Performed By:</td><td>${maintenance.performed_by || 'Not specified'}</td></tr>
                                <tr><td class="fw-bold">Cost:</td><td>${maintenance.cost > 0 ? ChurchCMS.formatCurrency(maintenance.cost) : 'No cost'}</td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>Description</h6>
                            <p>${maintenance.description}</p>
                            ${maintenance.parts_replaced ? `<h6>Parts Replaced</h6><p>${maintenance.parts_replaced}</p>` : ''}
                            ${maintenance.notes ? `<h6>Notes</h6><p>${maintenance.notes}</p>` : ''}
                            ${maintenance.next_maintenance_date ? `<h6>Next Maintenance</h6><p>${ChurchCMS.formatDate(maintenance.next_maintenance_date)}</p>` : ''}
                        </div>
                    </div>
                `;
                
                editBtn.onclick = () => {
                    window.location.href = `edit_maintenance.php?id=${id}`;
                };
            } else {
                detailsDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        Failed to load maintenance details.
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            detailsDiv.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    An error occurred while loading maintenance details.
                </div>
            `;
        });
}

// Delete maintenance record
function deleteMaintenance(id) {
    ChurchCMS.showConfirm(
        'Are you sure you want to delete this maintenance record? This action cannot be undone.',
        function() {
            ChurchCMS.showLoading('Deleting maintenance record...');
            
            fetch(`delete_maintenance.php?id=${id}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                ChurchCMS.hideLoading();
                
                if (data.success) {
                    ChurchCMS.showToast('Maintenance record deleted successfully', 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    ChurchCMS.showToast(data.message || 'Failed to delete maintenance record', 'error');
                }
            })
            .catch(error => {
                ChurchCMS.hideLoading();
                ChurchCMS.showToast('An error occurred while deleting maintenance record', 'error');
                console.error('Error:', error);
            });
        }
    );
}

// Export data function
function exportData(format) {
    const params = new URLSearchParams(window.location.search);
    params.set('export', format);
    window.open(`export_maintenance.php?${params.toString()}`, '_blank');
}

// Auto-set date range to last 30 days if no filters are set
document.addEventListener('DOMContentLoaded', function() {
    const dateFrom = document.getElementById('date_from');
    const dateTo = document.getElementById('date_to');
    
    if (!dateFrom.value && !dateTo.value && !window.location.search.includes('date')) {
        const today = new Date();
        const thirtyDaysAgo = new Date(today.getTime() - (30 * 24 * 60 * 60 * 1000));
        
        dateFrom.value = thirtyDaysAgo.toISOString().split('T')[0];
        dateTo.value = today.toISOString().split('T')[0];
    }
    
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Auto-refresh maintenance alerts every 5 minutes
setInterval(() => {
    fetch('maintenance_alerts.php')
        .then(response => response.json())
        .then(data => {
            if (data.alerts && data.alerts.length > 0) {
                data.alerts.forEach(alert => {
                    if (alert.type === 'overdue') {
                        ChurchCMS.showToast(alert.message, 'error', 10000);
                    } else if (alert.type === 'due_soon') {
                        ChurchCMS.showToast(alert.message, 'warning', 8000);
                    }
                });
            }
        })
        .catch(error => {
            console.error('Error checking maintenance alerts:', error);
        });
}, 300000); // 5 minutes
</script>

<?php include '../../includes/footer.php'; ?>