<?php
/**
 * Visitors Management - Main List Page
 * Deliverance Church Management System
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Check authentication and permissions
requireLogin();
if (!hasPermission('visitors')) {
    setFlashMessage('error', 'You do not have permission to access this page.');
    redirect(BASE_URL . 'modules/dashboard/');
}

// Page configuration
$page_title = 'Visitors Management';
$page_icon = 'fas fa-user-friends';
$page_description = 'Manage church visitors and follow-up activities';

// Breadcrumb
$breadcrumb = [
    ['title' => 'Visitors']
];

// Page actions
$page_actions = [
    [
        'title' => 'Add New Visitor',
        'url' => 'add.php',
        'icon' => 'fas fa-user-plus',
        'class' => 'church-primary'
    ],
    [
        'title' => 'Export List',
        'url' => '#',
        'icon' => 'fas fa-download',
        'class' => 'outline-secondary',
        'onclick' => 'exportVisitors()'
    ]
];

// Additional CSS and JS
$additional_css = ['assets/css/datatables.css'];
$additional_js = ['assets/js/datatables.js', 'modules/visitors/js/visitors.js'];

// Pagination and filtering
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : DEFAULT_PAGE_SIZE;
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$date_from = isset($_GET['date_from']) ? sanitizeInput($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitizeInput($_GET['date_to']) : '';

// Build query conditions
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(first_name LIKE ? OR last_name LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

if (!empty($status_filter)) {
    $conditions[] = "status = ?";
    $params[] = $status_filter;
}

if (!empty($date_from)) {
    $conditions[] = "visit_date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $conditions[] = "visit_date <= ?";
    $params[] = $date_to;
}

// Get visitors with pagination
try {
    $db = Database::getInstance();
    $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
    
    // Count total records
    $countSql = "SELECT COUNT(*) as total FROM visitors v $whereClause";
    $countStmt = $db->executeQuery($countSql, $params);
    $totalRecords = $countStmt->fetch()['total'];
    
    // Get paginated records with follow-up person info
    $offset = ($page - 1) * $limit;
    $sql = "SELECT v.*, 
                   CONCAT(m.first_name, ' ', m.last_name) as followup_person_name,
                   u.first_name as created_by_name
            FROM visitors v 
            LEFT JOIN members m ON v.assigned_followup_person_id = m.id 
            LEFT JOIN users u ON v.created_by = u.id
            $whereClause 
            ORDER BY v.visit_date DESC, v.created_at DESC 
            LIMIT $limit OFFSET $offset";
    
    $stmt = $db->executeQuery($sql, $params);
    $visitors = $stmt->fetchAll();
    
    // Generate pagination
    $pagination = generatePagination($totalRecords, $page, $limit);
    
} catch (Exception $e) {
    error_log("Error fetching visitors: " . $e->getMessage());
    $visitors = [];
    $totalRecords = 0;
    $pagination = null;
    setFlashMessage('error', 'An error occurred while fetching visitors data.');
}

// Get statistics for dashboard cards
try {
    $stats = [
        'total_visitors' => getRecordCount('visitors'),
        'new_visitors' => getRecordCount('visitors', ['status' => 'new_visitor']),
        'in_followup' => getRecordCount('visitors', ['status' => 'follow_up']),
        'converted' => getRecordCount('visitors', ['status' => 'converted_member']),
        'this_month' => 0
    ];
    
    // Get this month's visitors
    $thisMonth = date('Y-m-01');
    $nextMonth = date('Y-m-01', strtotime('+1 month'));
    $monthlyStmt = $db->executeQuery(
        "SELECT COUNT(*) as count FROM visitors WHERE visit_date >= ? AND visit_date < ?",
        [$thisMonth, $nextMonth]
    );
    $stats['this_month'] = $monthlyStmt->fetch()['count'];
    
} catch (Exception $e) {
    error_log("Error fetching visitor statistics: " . $e->getMessage());
    $stats = array_fill_keys(['total_visitors', 'new_visitors', 'in_followup', 'converted', 'this_month'], 0);
}

include '../../includes/header.php';
?>

<!-- Main Content -->
<div class="row">
    <!-- Statistics Cards -->
    <div class="col-12 mb-4">
        <div class="row">
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="stats-card text-center">
                    <div class="stats-icon bg-church-blue text-white mx-auto">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stats-number"><?php echo number_format($stats['total_visitors']); ?></div>
                    <div class="stats-label">Total Visitors</div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="stats-card text-center">
                    <div class="stats-icon bg-success text-white mx-auto">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="stats-number"><?php echo number_format($stats['new_visitors']); ?></div>
                    <div class="stats-label">New Visitors</div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="stats-card text-center">
                    <div class="stats-icon bg-warning text-white mx-auto">
                        <i class="fas fa-phone"></i>
                    </div>
                    <div class="stats-number"><?php echo number_format($stats['in_followup']); ?></div>
                    <div class="stats-label">In Follow-up</div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="stats-card text-center">
                    <div class="stats-icon bg-church-red text-white mx-auto">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stats-number"><?php echo number_format($stats['converted']); ?></div>
                    <div class="stats-label">Converted</div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="stats-card text-center">
                    <div class="stats-icon bg-info text-white mx-auto">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <div class="stats-number"><?php echo number_format($stats['this_month']); ?></div>
                    <div class="stats-label">This Month</div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="stats-card text-center">
                    <div class="stats-icon bg-secondary text-white mx-auto">
                        <i class="fas fa-percentage"></i>
                    </div>
                    <div class="stats-number"><?php echo $stats['total_visitors'] > 0 ? round(($stats['converted'] / $stats['total_visitors']) * 100, 1) : 0; ?>%</div>
                    <div class="stats-label">Conversion Rate</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters and Search -->
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Visitors</h6>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Name, phone, or email...">
                    </div>
                    <div class="col-md-2">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All Statuses</option>
                            <?php foreach (VISITOR_STATUS as $key => $label): ?>
                                <option value="<?php echo $key; ?>" <?php echo $status_filter === $key ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="date_from" class="form-label">From Date</label>
                        <input type="date" class="form-control" id="date_from" name="date_from" 
                               value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="date_to" class="form-label">To Date</label>
                        <input type="date" class="form-control" id="date_to" name="date_to" 
                               value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="limit" class="form-label">Per Page</label>
                        <select class="form-select" id="limit" name="limit">
                            <option value="25" <?php echo $limit == 25 ? 'selected' : ''; ?>>25</option>
                            <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-church-primary">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Visitors Table -->
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Visitors List (<?php echo number_format($totalRecords); ?> total)
                </h6>
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary" onclick="refreshTable()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                    <button type="button" class="btn btn-outline-success" onclick="exportVisitors('excel')">
                        <i class="fas fa-file-excel"></i> Excel
                    </button>
                    <button type="button" class="btn btn-outline-danger" onclick="exportVisitors('pdf')">
                        <i class="fas fa-file-pdf"></i> PDF
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($visitors)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="visitorsTable">
                            <thead>
                                <tr>
                                    <th width="5%">#</th>
                                    <th width="15%">Visitor</th>
                                    <th width="12%">Contact</th>
                                    <th width="10%">Visit Date</th>
                                    <th width="12%">Service</th>
                                    <th width="10%">Age Group</th>
                                    <th width="10%">Status</th>
                                    <th width="12%">Follow-up Person</th>
                                    <th width="14%" class="no-print">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $counter = ($page - 1) * $limit + 1;
                                foreach ($visitors as $visitor): 
                                ?>
                                    <tr>
                                        <td><?php echo $counter++; ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar bg-church-blue text-white rounded-circle me-2 d-flex align-items-center justify-content-center" style="width: 35px; height: 35px;">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($visitor['first_name'] . ' ' . $visitor['last_name']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($visitor['visitor_number']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($visitor['phone'])): ?>
                                                <div><i class="fas fa-phone text-muted me-1"></i><?php echo htmlspecialchars($visitor['phone']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($visitor['email'])): ?>
                                                <div><i class="fas fa-envelope text-muted me-1"></i><?php echo htmlspecialchars($visitor['email']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo formatDisplayDate($visitor['visit_date']); ?></strong><br>
                                            <small class="text-muted"><?php echo timeAgo($visitor['visit_date']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($visitor['service_attended'] ?: '-'); ?></td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo ucfirst($visitor['age_group']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $statusClasses = [
                                                'new_visitor' => 'bg-info',
                                                'follow_up' => 'bg-warning',
                                                'regular_attender' => 'bg-success',
                                                'converted_member' => 'bg-church-red'
                                            ];
                                            $statusClass = $statusClasses[$visitor['status']] ?? 'bg-secondary';
                                            $statusLabel = VISITOR_STATUS[$visitor['status']] ?? ucfirst($visitor['status']);
                                            ?>
                                            <span class="badge <?php echo $statusClass; ?>">
                                                <?php echo $statusLabel; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($visitor['followup_person_name']): ?>
                                                <i class="fas fa-user-check text-success me-1"></i>
                                                <?php echo htmlspecialchars($visitor['followup_person_name']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">
                                                    <i class="fas fa-user-times me-1"></i>Not assigned
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="no-print">
                                            <div class="btn-group btn-group-sm">
                                                <a href="view.php?id=<?php echo $visitor['id']; ?>" 
                                                   class="btn btn-outline-info" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="edit.php?id=<?php echo $visitor['id']; ?>" 
                                                   class="btn btn-outline-primary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="followup.php?visitor_id=<?php echo $visitor['id']; ?>" 
                                                   class="btn btn-outline-success" title="Follow-up">
                                                    <i class="fas fa-phone"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-danger confirm-delete" 
                                                        title="Delete" onclick="deleteVisitor(<?php echo $visitor['id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($pagination && $pagination['total_pages'] > 1): ?>
                        <div class="card-footer">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="text-muted">
                                    Showing <?php echo (($page - 1) * $limit) + 1; ?> to 
                                    <?php echo min($page * $limit, $totalRecords); ?> of 
                                    <?php echo number_format($totalRecords); ?> entries
                                </div>
                                <?php echo generatePaginationHTML($pagination, '?search=' . urlencode($search) . '&status=' . urlencode($status_filter) . '&date_from=' . urlencode($date_from) . '&date_to=' . urlencode($date_to) . '&limit=' . $limit); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-user-friends fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No Visitors Found</h5>
                        <p class="text-muted">
                            <?php if (!empty($search) || !empty($status_filter) || !empty($date_from) || !empty($date_to)): ?>
                                No visitors match your current filters. Try adjusting your search criteria.
                            <?php else: ?>
                                No visitors have been recorded yet. Add your first visitor to get started.
                            <?php endif; ?>
                        </p>
                        <a href="add.php" class="btn btn-church-primary">
                            <i class="fas fa-user-plus me-2"></i>Add First Visitor
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle me-2"></i>Delete Visitor
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this visitor?</p>
                <div class="alert alert-warning">
                    <i class="fas fa-warning me-2"></i>
                    <strong>Warning:</strong> This action cannot be undone. All follow-up records associated with this visitor will also be deleted.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDelete">
                    <i class="fas fa-trash me-2"></i>Delete Visitor
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Visitor management functions
let deleteVisitorId = null;

function deleteVisitor(id) {
    deleteVisitorId = id;
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}

document.getElementById('confirmDelete').addEventListener('click', function() {
    if (deleteVisitorId) {
        ChurchCMS.showLoading('Deleting visitor...');
        
        fetch(`delete.php?id=${deleteVisitorId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            ChurchCMS.hideLoading();
            if (data.success) {
                ChurchCMS.showToast('Visitor deleted successfully', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                ChurchCMS.showToast(data.message || 'Failed to delete visitor', 'error');
            }
        })
        .catch(error => {
            ChurchCMS.hideLoading();
            ChurchCMS.showToast('An error occurred while deleting visitor', 'error');
            console.error('Error:', error);
        });
        
        const modal = bootstrap.Modal.getInstance(document.getElementById('deleteModal'));
        modal.hide();
    }
});

function refreshTable() {
    location.reload();
}

function exportVisitors(format = 'excel') {
    const currentUrl = new URL(window.location);
    currentUrl.pathname = currentUrl.pathname.replace('index.php', 'export.php');
    currentUrl.searchParams.set('format', format);
    
    ChurchCMS.showLoading(`Generating ${format.toUpperCase()} export...`);
    
    // Create hidden form to submit export request
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = currentUrl.toString();
    form.target = '_blank';
    
    // Add current filters as hidden inputs
    const params = new URLSearchParams(window.location.search);
    for (const [key, value] of params) {
        if (key !== 'page') {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = value;
            form.appendChild(input);
        }
    }
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
    
    setTimeout(() => ChurchCMS.hideLoading(), 2000);
}

// Initialize DataTable if available
document.addEventListener('DOMContentLoaded', function() {
    if (typeof $ !== 'undefined' && $.fn.DataTable && document.getElementById('visitorsTable')) {
        $('#visitorsTable').DataTable({
            responsive: true,
            pageLength: <?php echo $limit; ?>,
            ordering: false,
            searching: false,
            lengthChange: false,
            info: false,
            paging: false,
            dom: 'rt'
        });
    }
});
</script>

<?php include '../../includes/footer.php'; ?>