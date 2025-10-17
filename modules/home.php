<?php
/**
 * Module Index Template
 * Deliverance Church Management System
 * 
 * Use this template for: modules/[module]/index.php
 * Adapt based on module (members, attendance, finance, etc.)
 * 
 * EXAMPLE: modules/members/index.php
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Set page info
$page_title = 'Members';  // Change per module
$page_icon = 'fas fa-users';  // Change per module
$page_description = 'Manage church members and memberships';  // Change per module

requireLogin();

// Check permissions
if (!hasPermission('members')) {  // Change permission
    setFlashMessage('error', 'You do not have permission to access this page');
    redirect(BASE_URL . 'modules/dashboard/');
}

// Get filter and pagination parameters
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : 'active';
$department_filter = isset($_GET['department']) ? (int)$_GET['department'] : 0;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = DEFAULT_PAGE_SIZE;

$db = Database::getInstance();
$churchInfo = getRecord('church_info', 'id', 1);

// Build query
$sql = "SELECT m.*, d.name as department_name 
        FROM members m
        LEFT JOIN member_departments md ON m.id = md.member_id AND md.is_active = TRUE
        LEFT JOIN departments d ON md.department_id = d.id
        WHERE 1=1";

$count_sql = "SELECT COUNT(DISTINCT m.id) as count FROM members m
            LEFT JOIN member_departments md ON m.id = md.member_id AND md.is_active = TRUE
            LEFT JOIN departments d ON md.department_id = d.id
            WHERE 1=1";

$params = [];

// Apply filters
if ($status_filter) {
    $sql .= " AND m.membership_status = ?";
    $count_sql .= " AND m.membership_status = ?";
    $params[] = $status_filter;
}

if ($department_filter > 0) {
    $sql .= " AND md.department_id = ?";
    $count_sql .= " AND md.department_id = ?";
    $params[] = $department_filter;
}

if (!empty($search)) {
    $search_term = '%' . $search . '%';
    $sql .= " AND (m.first_name LIKE ? OR m.last_name LIKE ? OR m.member_number LIKE ? OR m.phone LIKE ?)";
    $count_sql .= " AND (m.first_name LIKE ? OR m.last_name LIKE ? OR m.member_number LIKE ? OR m.phone LIKE ?)";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}

$sql .= " ORDER BY m.first_name, m.last_name LIMIT ? OFFSET ?";

try {
    // Get total count
    $stmt = $db->executeQuery($count_sql, $params);
    $total_records = $stmt->fetch()['count'];
    
    // Generate pagination
    $pagination = generatePagination($total_records, $page, $per_page);
    
    // Get records
    $query_params = array_merge($params, [(int)$per_page, (int)$pagination['offset']]);
    $stmt = $db->executeQuery($sql, $query_params);
    $members = $stmt->fetchAll();
    
    // Get departments for filter
    $stmt = $db->executeQuery("SELECT * FROM departments WHERE is_active = TRUE ORDER BY name");
    $departments = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Error fetching members: " . $e->getMessage());
    setFlashMessage('error', 'Error loading members');
    $members = [];
    $departments = [];
    $pagination = ['total_records' => 0, 'total_pages' => 0];
}

// Include header
include '../../includes/header.php';

// Define breadcrumb
$breadcrumb = [
    ['title' => 'Members', 'url' => BASE_URL . 'modules/members/']
];
?>

<div class="row mb-4">
    <!-- Filter Section -->
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body p-3">
                <form method="GET" class="row g-2">
                    <!-- Search Bar -->
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0">
                                <i class="fas fa-search text-church-blue"></i>
                            </span>
                            <input type="text" class="form-control border-start-0" name="search" 
                                   placeholder="Search by name, number, or phone..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>

                    <!-- Status Filter -->
                    <div class="col-md-3">
                        <select class="form-select" name="status">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active Members</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive Members</option>
                            <option value="transferred" <?php echo $status_filter === 'transferred' ? 'selected' : ''; ?>>Transferred</option>
                            <option value="deceased" <?php echo $status_filter === 'deceased' ? 'selected' : ''; ?>>Deceased</option>
                        </select>
                    </div>

                    <!-- Department Filter -->
                    <div class="col-md-3">
                        <select class="form-select" name="department">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>" <?php echo $department_filter == $dept['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Filter Button -->
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-church-primary w-100">
                            <i class="fas fa-filter me-1"></i>Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Stats Row -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card stats-card border-0 shadow-sm">
            <div class="card-body">
                <div class="stats-icon" style="background: linear-gradient(135deg, #03045e, #1e3c72);">
                    <i class="fas fa-users text-white"></i>
                </div>
                <div class="stats-number"><?php echo $total_records; ?></div>
                <div class="stats-label">Total Members</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card border-0 shadow-sm">
            <div class="card-body">
                <div class="stats-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                    <i class="fas fa-user-check text-white"></i>
                </div>
                <div class="stats-number">
                    <?php 
                    $stmt = $db->executeQuery("SELECT COUNT(*) as count FROM members WHERE membership_status = 'active'");
                    echo $stmt->fetch()['count']; 
                    ?>
                </div>
                <div class="stats-label">Active Members</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card border-0 shadow-sm">
            <div class="card-body">
                <div class="stats-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                    <i class="fas fa-calendar-plus text-white"></i>
                </div>
                <div class="stats-number">
                    <?php 
                    $stmt = $db->executeQuery("SELECT COUNT(*) as count FROM members WHERE MONTH(join_date) = MONTH(CURDATE()) AND YEAR(join_date) = YEAR(CURDATE())");
                    echo $stmt->fetch()['count']; 
                    ?>
                </div>
                <div class="stats-label">New This Month</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card border-0 shadow-sm">
            <div class="card-body">
                <div class="stats-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
                    <i class="fas fa-birthday-cake text-white"></i>
                </div>
                <div class="stats-number">
                    <?php 
                    $stmt = $db->executeQuery("SELECT COUNT(*) as count FROM members WHERE DAYOFYEAR(date_of_birth) = DAYOFYEAR(CURDATE())");
                    echo $stmt->fetch()['count']; 
                    ?>
                </div>
                <div class="stats-label">Birthdays Today</div>
            </div>
        </div>
    </div>
</div>

<!-- Action Buttons -->
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?php echo BASE_URL; ?>modules/members/add.php" class="btn btn-church-primary">
                <i class="fas fa-user-plus me-2"></i>Add New Member
            </a>
            <a href="<?php echo BASE_URL; ?>modules/members/export_report.php?format=csv&status=<?php echo $status_filter; ?>" 
               class="btn btn-outline-primary">
                <i class="fas fa-download me-2"></i>Export CSV
            </a>
            <a href="<?php echo BASE_URL; ?>modules/members/export_report.php?format=excel&status=<?php echo $status_filter; ?>" 
               class="btn btn-outline-success">
                <i class="fas fa-file-excel me-2"></i>Export Excel
            </a>
            <a href="<?php echo BASE_URL; ?>modules/members/reports.php" class="btn btn-outline-info">
                <i class="fas fa-chart-bar me-2"></i>Reports
            </a>
        </div>
    </div>
</div>

<!-- Members Table -->
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-gradient-church text-white">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>Members List
                    <span class="badge bg-white text-church-blue float-end">
                        <?php echo $pagination['total_records']; ?> total
                    </span>
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($members)): ?>
                    <div class="alert alert-info m-3 mb-0" role="alert">
                        <i class="fas fa-info-circle me-2"></i>
                        No members found. Try adjusting your filters or <a href="<?php echo BASE_URL; ?>modules/members/add.php">add a new member</a>.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Member #</th>
                                    <th>Name</th>
                                    <th>Phone</th>
                                    <th>Email</th>
                                    <th>Join Date</th>
                                    <th>Department</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($members as $member): ?>
                                <tr>
                                    <td>
                                        <strong class="text-church-blue"><?php echo htmlspecialchars($member['member_number']); ?></strong>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if (!empty($member['photo'])): ?>
                                                <img src="<?php echo BASE_URL . $member['photo']; ?>" alt="<?php echo htmlspecialchars($member['first_name']); ?>" 
                                                     class="rounded-circle me-2" style="width: 32px; height: 32px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="avatar rounded-circle me-2 d-flex align-items-center justify-content-center" 
                                                     style="width: 32px; height: 32px; background: linear-gradient(135deg, #03045e, #ff2400); color: white; font-weight: bold;">
                                                    <?php echo strtoupper(substr($member['first_name'], 0, 1) . substr($member['last_name'], 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <strong><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo ucfirst($member['gender']); ?> • <?php echo $member['age'] ?? 'N/A'; ?> yrs</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="tel:<?php echo htmlspecialchars($member['phone']); ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($member['phone'] ?? '-'); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <a href="mailto:<?php echo htmlspecialchars($member['email']); ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($member['email'] ?? '-'); ?>
                                        </a>
                                    </td>
                                    <td><?php echo formatDisplayDate($member['join_date']); ?></td>
                                    <td>
                                        <?php if ($member['department_name']): ?>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($member['department_name']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $member['membership_status']; ?>">
                                            <?php echo ucfirst($member['membership_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="<?php echo BASE_URL; ?>modules/members/view.php?id=<?php echo $member['id']; ?>" 
                                               class="btn btn-outline-primary" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="<?php echo BASE_URL; ?>modules/members/edit.php?id=<?php echo $member['id']; ?>" 
                                               class="btn btn-outline-warning" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="<?php echo BASE_URL; ?>modules/members/delete.php?id=<?php echo $member['id']; ?>" 
                                               class="btn btn-outline-danger confirm-delete" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($pagination['total_pages'] > 1): ?>
                    <nav aria-label="Page navigation" class="d-flex justify-content-center p-3 border-top">
                        <ul class="pagination mb-0">
                            <?php if ($pagination['has_previous']): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=1&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>">
                                        First
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $pagination['previous_page']; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>">
                                        Previous
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php 
                            $start = max(1, $page - 2);
                            $end = min($pagination['total_pages'], $page + 2);
                            
                            for ($i = $start; $i <= $end; $i++): 
                            ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($pagination['has_next']): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $pagination['next_page']; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>">
                                        Next
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $pagination['total_pages']; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>">
                                        Last
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
    .stats-card {
        transition: all 0.3s ease;
    }
    
    .stats-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15) !important;
    }
    
    .stats-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.75rem;
        margin-bottom: 1rem;
    }
    
    .stats-number {
        font-size: 2rem;
        font-weight: 700;
        color: #03045e;
        margin-bottom: 0.5rem;
    }
    
    .stats-label {
        color: #6c757d;
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .status-badge {
        display: inline-block;
        padding: 0.35rem 0.65rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .status-active {
        background: rgba(16, 185, 129, 0.1);
        color: #10b981;
        border: 1px solid rgba(16, 185, 129, 0.2);
    }
    
    .status-inactive {
        background: rgba(107, 114, 128, 0.1);
        color: #6b7280;
        border: 1px solid rgba(107, 114, 128, 0.2);
    }
    
    .status-transferred {
        background: rgba(59, 130, 246, 0.1);
        color: #3b82f6;
        border: 1px solid rgba(59, 130, 246, 0.2);
    }
    
    .status-deceased {
        background: rgba(107, 114, 128, 0.1);
        color: #6b7280;
        border: 1px solid rgba(107, 114, 128, 0.2);
    }
    
    .table th {
        font-weight: 600;
        color: #03045e;
        border-bottom: 2px solid #f0f0f0;
    }
    
    .table td {
        vertical-align: middle;
    }
</style>

<?php include '../../includes/footer.php'; ?>