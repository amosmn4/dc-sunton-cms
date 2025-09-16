<?php
/**
 * Members List
 * Deliverance Church Management System
 * 
 * Display and manage all church members
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Check authentication and permissions
requireLogin();
if (!hasPermission('members')) {
    setFlashMessage('error', 'You do not have permission to access this page.');
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit();
}

// Set page variables
$page_title = 'Members';
$page_icon = 'fas fa-users';
$page_description = 'Manage church members and their information';

$breadcrumb = [
    ['title' => 'Members']
];

$page_actions = [
    [
        'title' => 'Add Member',
        'url' => BASE_URL . 'modules/members/add.php',
        'icon' => 'fas fa-plus',
        'class' => 'church-primary'
    ],
    [
        'title' => 'Import Members',
        'url' => BASE_URL . 'modules/members/import.php',
        'icon' => 'fas fa-file-import',
        'class' => 'success'
    ],
    [
        'title' => 'Export Members',
        'url' => BASE_URL . 'modules/members/export.php',
        'icon' => 'fas fa-file-export',
        'class' => 'info'
    ]
];

// Handle search and filters
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$department_filter = isset($_GET['department']) ? (int)$_GET['department'] : 0;
$age_group_filter = isset($_GET['age_group']) ? sanitizeInput($_GET['age_group']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : DEFAULT_PAGE_SIZE;

// Build query conditions
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(CONCAT(first_name, ' ', last_name) LIKE ? OR phone LIKE ? OR email LIKE ? OR member_number LIKE ?)";
    $search_term = "%{$search}%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}

if (!empty($status_filter)) {
    $conditions[] = "membership_status = ?";
    $params[] = $status_filter;
}

if (!empty($age_group_filter)) {
    switch ($age_group_filter) {
        case 'child':
            $conditions[] = "YEAR(CURDATE()) - YEAR(date_of_birth) <= ?";
            $params[] = CHILD_MAX_AGE;
            break;
        case 'teen':
            $conditions[] = "YEAR(CURDATE()) - YEAR(date_of_birth) BETWEEN ? AND ?";
            $params = array_merge($params, [CHILD_MAX_AGE + 1, TEEN_MAX_AGE]);
            break;
        case 'youth':
            $conditions[] = "YEAR(CURDATE()) - YEAR(date_of_birth) BETWEEN ? AND ?";
            $params = array_merge($params, [TEEN_MAX_AGE + 1, YOUTH_MAX_AGE]);
            break;
        case 'adult':
            $conditions[] = "YEAR(CURDATE()) - YEAR(date_of_birth) BETWEEN ? AND ?";
            $params = array_merge($params, [YOUTH_MAX_AGE + 1, SENIOR_MIN_AGE - 1]);
            break;
        case 'senior':
            $conditions[] = "YEAR(CURDATE()) - YEAR(date_of_birth) >= ?";
            $params[] = SENIOR_MIN_AGE;
            break;
    }
}

try {
    $db = Database::getInstance();
    
    // Get departments for filter dropdown
    $departments = $db->executeQuery(
        "SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name"
    )->fetchAll();
    
    // Build main query
    $base_query = "FROM members m 
                   LEFT JOIN member_departments md ON m.id = md.member_id AND md.is_active = 1
                   LEFT JOIN departments d ON md.department_id = d.id";
    
    if ($department_filter > 0) {
        $conditions[] = "md.department_id = ?";
        $params[] = $department_filter;
    }
    
    $where_clause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
    
    // Get total count
    $count_query = "SELECT COUNT(DISTINCT m.id) as total " . $base_query . " " . $where_clause;
    $total_records = $db->executeQuery($count_query, $params)->fetchColumn();
    
    // Generate pagination
    $pagination = generatePagination($total_records, $page, $per_page);
    
    // Get members data
    $offset = $pagination['offset'];
    $members_query = "SELECT DISTINCT m.*, 
                             GROUP_CONCAT(DISTINCT d.name SEPARATOR ', ') as departments
                      " . $base_query . " " . $where_clause . "
                      GROUP BY m.id 
                      ORDER BY m.first_name, m.last_name 
                      LIMIT {$per_page} OFFSET {$offset}";
    
    $members = $db->executeQuery($members_query, $params)->fetchAll();
    
} catch (Exception $e) {
    error_log("Members list error: " . $e->getMessage());
    setFlashMessage('error', 'Error loading members data. Please try again.');
    $members = [];
    $departments = [];
    $total_records = 0;
    $pagination = generatePagination(0, 1, $per_page);
}

// Include header
include '../../includes/header.php';
?>

<!-- Members List Content -->
<div class="row">
    <div class="col-12">
        <!-- Filters Card -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-3">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Name, phone, email, member #">
                    </div>
                    
                    <div class="col-md-2">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All Status</option>
                            <?php foreach (MEMBER_STATUS_OPTIONS as $value => $label): ?>
                                <option value="<?php echo $value; ?>" <?php echo ($status_filter === $value) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="department" class="form-label">Department</label>
                        <select class="form-select" id="department" name="department">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>" <?php echo ($department_filter == $dept['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="age_group" class="form-label">Age Group</label>
                        <select class="form-select" id="age_group" name="age_group">
                            <option value="">All Ages</option>
                            <option value="child" <?php echo ($age_group_filter === 'child') ? 'selected' : ''; ?>>
                                Children (0-<?php echo CHILD_MAX_AGE; ?>)
                            </option>
                            <option value="teen" <?php echo ($age_group_filter === 'teen') ? 'selected' : ''; ?>>
                                Teens (<?php echo CHILD_MAX_AGE + 1; ?>-<?php echo TEEN_MAX_AGE; ?>)
                            </option>
                            <option value="youth" <?php echo ($age_group_filter === 'youth') ? 'selected' : ''; ?>>
                                Youth (<?php echo TEEN_MAX_AGE + 1; ?>-<?php echo YOUTH_MAX_AGE; ?>)
                            </option>
                            <option value="adult" <?php echo ($age_group_filter === 'adult') ? 'selected' : ''; ?>>
                                Adults (<?php echo YOUTH_MAX_AGE + 1; ?>-<?php echo SENIOR_MIN_AGE - 1; ?>)
                            </option>
                            <option value="senior" <?php echo ($age_group_filter === 'senior') ? 'selected' : ''; ?>>
                                Seniors (<?php echo SENIOR_MIN_AGE; ?>+)
                            </option>
                        </select>
                    </div>
                    
                    <div class="col-md-1">
                        <label for="per_page" class="form-label">Show</label>
                        <select class="form-select" id="per_page" name="per_page">
                            <option value="25" <?php echo ($per_page == 25) ? 'selected' : ''; ?>>25</option>
                            <option value="50" <?php echo ($per_page == 50) ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo ($per_page == 100) ? 'selected' : ''; ?>>100</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-church-primary">
                            <i class="fas fa-search"></i> Filter
                        </button>
                        <a href="<?php echo BASE_URL; ?>modules/members/" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Results Card -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0">
                        Members List
                        <span class="badge bg-church-blue ms-2"><?php echo number_format($total_records); ?> total</span>
                    </h6>
                    <?php if (!empty($search) || !empty($status_filter) || !empty($department_filter) || !empty($age_group_filter)): ?>
                        <small class="text-muted">
                            Showing <?php echo number_format(count($members)); ?> filtered results
                        </small>
                    <?php endif; ?>
                </div>
                
                <!-- Bulk Actions -->
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-ellipsis-v"></i> Bulk Actions
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" onclick="exportSelected()">
                            <i class="fas fa-file-export me-2"></i>Export Selected
                        </a></li>
                        <li><a class="dropdown-item" href="#" onclick="sendSMSToSelected()">
                            <i class="fas fa-sms me-2"></i>Send SMS
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="#" onclick="bulkStatusChange()">
                            <i class="fas fa-user-slash me-2"></i>Change Status
                        </a></li>
                    </ul>
                </div>
            </div>
            
            <div class="card-body p-0">
                <?php if (!empty($members)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th width="50">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="selectAll">
                                        </div>
                                    </th>
                                    <th>Member</th>
                                    <th>Contact</th>
                                    <th>Age/Gender</th>
                                    <th>Department(s)</th>
                                    <th>Join Date</th>
                                    <th>Status</th>
                                    <th width="120">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($members as $member): ?>
                                    <tr>
                                        <td>
                                            <div class="form-check">
                                                <input class="form-check-input member-checkbox" type="checkbox" 
                                                       value="<?php echo $member['id']; ?>">
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar bg-church-blue text-white rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                    <?php if (!empty($member['photo'])): ?>
                                                        <img src="<?php echo BASE_URL . $member['photo']; ?>" 
                                                             alt="<?php echo htmlspecialchars($member['first_name']); ?>" 
                                                             class="img-fluid rounded-circle">
                                                    <?php else: ?>
                                                        <i class="fas fa-user"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <div class="fw-bold">
                                                        <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                                                    </div>
                                                    <small class="text-muted">
                                                        #<?php echo htmlspecialchars($member['member_number']); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($member['phone'])): ?>
                                                <div>
                                                    <i class="fas fa-phone me-1 text-muted"></i>
                                                    <a href="tel:<?php echo $member['phone']; ?>" class="text-decoration-none">
                                                        <?php echo htmlspecialchars($member['phone']); ?>
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($member['email'])): ?>
                                                <div>
                                                    <i class="fas fa-envelope me-1 text-muted"></i>
                                                    <a href="mailto:<?php echo $member['email']; ?>" class="text-decoration-none">
                                                        <?php echo htmlspecialchars($member['email']); ?>
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div>
                                                <?php 
                                                $age = calculateAge($member['date_of_birth']);
                                                echo $age > 0 ? $age . ' years' : 'N/A';
                                                ?>
                                            </div>
                                            <small class="text-muted">
                                                <i class="fas fa-<?php echo ($member['gender'] === 'male') ? 'mars' : 'venus'; ?> me-1"></i>
                                                <?php echo ucfirst($member['gender']); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php if (!empty($member['departments'])): ?>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($member['departments']); ?>
                                                </small>
                                            <?php else: ?>
                                                <small class="text-muted">No department assigned</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div><?php echo formatDisplayDate($member['join_date']); ?></div>
                                            <small class="text-muted">
                                                <?php echo timeAgo($member['join_date']); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $member['membership_status']; ?>">
                                                <?php echo MEMBER_STATUS_OPTIONS[$member['membership_status']] ?? ucfirst($member['membership_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="table-actions">
                                                <a href="<?php echo BASE_URL; ?>modules/members/view.php?id=<?php echo $member['id']; ?>" 
                                                   class="btn btn-sm btn-outline-info" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="<?php echo BASE_URL; ?>modules/members/edit.php?id=<?php echo $member['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary" title="Edit Member">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <div class="dropdown d-inline">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        <li>
                                                            <a class="dropdown-item" href="<?php echo BASE_URL; ?>modules/sms/send.php?member_id=<?php echo $member['id']; ?>">
                                                                <i class="fas fa-sms me-2"></i>Send SMS
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item" href="<?php echo BASE_URL; ?>modules/members/print.php?id=<?php echo $member['id']; ?>" target="_blank">
                                                                <i class="fas fa-print me-2"></i>Print Profile
                                                            </a>
                                                        </li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            <a class="dropdown-item text-danger confirm-delete" 
                                                               href="<?php echo BASE_URL; ?>modules/members/delete.php?id=<?php echo $member['id']; ?>"
                                                               data-message="Are you sure you want to delete this member? This action cannot be undone.">
                                                                <i class="fas fa-trash me-2"></i>Delete Member
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <div class="mb-3">
                            <i class="fas fa-users fa-4x text-muted opacity-50"></i>
                        </div>
                        <h5 class="text-muted mb-3">No Members Found</h5>
                        <?php if (!empty($search) || !empty($status_filter) || !empty($department_filter) || !empty($age_group_filter)): ?>
                            <p class="text-muted mb-3">
                                No members match your current filters. Try adjusting your search criteria.
                            </p>
                            <a href="<?php echo BASE_URL; ?>modules/members/" class="btn btn-outline-primary">
                                <i class="fas fa-times me-1"></i>Clear Filters
                            </a>
                        <?php else: ?>
                            <p class="text-muted mb-3">
                                Get started by adding your first church member.
                            </p>
                            <a href="<?php echo BASE_URL; ?>modules/members/add.php" class="btn btn-church-primary">
                                <i class="fas fa-plus me-1"></i>Add First Member
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Pagination Footer -->
            <?php if ($pagination['total_pages'] > 1): ?>
                <div class="card-footer bg-transparent">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-muted small">
                            Showing <?php echo number_format($pagination['offset'] + 1); ?> to 
                            <?php echo number_format(min($pagination['offset'] + $per_page, $total_records)); ?> of 
                            <?php echo number_format($total_records); ?> entries
                        </div>
                        
                        <?php 
                        $base_url = '?search=' . urlencode($search) . 
                                   '&status=' . urlencode($status_filter) . 
                                   '&department=' . $department_filter . 
                                   '&age_group=' . urlencode($age_group_filter) . 
                                   '&per_page=' . $per_page;
                        echo generatePaginationHTML($pagination, $base_url); 
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Bulk Actions Modal -->
<div class="modal fade" id="bulkActionsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-church-blue text-white">
                <h5 class="modal-title">
                    <i class="fas fa-users me-2"></i>Bulk Actions
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="bulkActionContent">
                    <p>Select an action to perform on the selected members:</p>
                    <div class="list-group">
                        <button type="button" class="list-group-item list-group-item-action" onclick="exportSelectedMembers()">
                            <i class="fas fa-file-export me-2 text-success"></i>
                            Export Selected Members
                        </button>
                        <button type="button" class="list-group-item list-group-item-action" onclick="sendBulkSMS()">
                            <i class="fas fa-sms me-2 text-info"></i>
                            Send SMS to Selected
                        </button>
                        <button type="button" class="list-group-item list-group-item-action" onclick="changeBulkStatus()">
                            <i class="fas fa-user-edit me-2 text-warning"></i>
                            Change Member Status
                        </button>
                    </div>
                </div>
                
                <!-- Status Change Form -->
                <div id="statusChangeForm" class="d-none">
                    <form method="POST" action="<?php echo BASE_URL; ?>modules/members/bulk_actions.php">
                        <input type="hidden" name="action" value="change_status">
                        <input type="hidden" name="member_ids" id="bulkMemberIds">
                        
                        <div class="mb-3">
                            <label for="new_status" class="form-label">New Status</label>
                            <select class="form-select" name="new_status" id="new_status" required>
                                <?php foreach (MEMBER_STATUS_OPTIONS as $value => $label): ?>
                                    <option value="<?php echo $value; ?>"><?php echo htmlspecialchars($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="status_reason" class="form-label">Reason (Optional)</label>
                            <textarea class="form-control" name="reason" id="status_reason" rows="3" 
                                      placeholder="Enter reason for status change..."></textarea>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-secondary" onclick="showBulkActions()">
                                <i class="fas fa-arrow-left me-1"></i>Back
                            </button>
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-save me-1"></i>Update Status
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Select all functionality
    const selectAllCheckbox = document.getElementById('selectAll');
    const memberCheckboxes = document.querySelectorAll('.member-checkbox');
    
    selectAllCheckbox.addEventListener('change', function() {
        memberCheckboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updateBulkActionState();
    });
    
    memberCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const checkedCount = document.querySelectorAll('.member-checkbox:checked').length;
            selectAllCheckbox.checked = checkedCount === memberCheckboxes.length;
            selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < memberCheckboxes.length;
            updateBulkActionState();
        });
    });
    
    // Auto-submit filter form on select change
    document.querySelectorAll('#status, #department, #age_group, #per_page').forEach(select => {
        select.addEventListener('change', function() {
            document.querySelector('form').submit();
        });
    });
    
    // Search with debounce
    const searchInput = document.getElementById('search');
    let searchTimeout;
    
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            if (this.value.length >= 3 || this.value.length === 0) {
                document.querySelector('form').submit();
            }
        }, 500);
    });
});

function updateBulkActionState() {
    const checkedBoxes = document.querySelectorAll('.member-checkbox:checked');
    const bulkActionButton = document.querySelector('[onclick="showBulkActions()"]');
    
    if (bulkActionButton) {
        bulkActionButton.disabled = checkedBoxes.length === 0;
    }
}

function getSelectedMemberIds() {
    const checkedBoxes = document.querySelectorAll('.member-checkbox:checked');
    return Array.from(checkedBoxes).map(cb => cb.value);
}

function exportSelected() {
    const selectedIds = getSelectedMemberIds();
    if (selectedIds.length === 0) {
        ChurchCMS.showToast('Please select members to export', 'warning');
        return;
    }
    
    showBulkActions();
}

function sendSMSToSelected() {
    const selectedIds = getSelectedMemberIds();
    if (selectedIds.length === 0) {
        ChurchCMS.showToast('Please select members to send SMS to', 'warning');
        return;
    }
    
    showBulkActions();
}

function bulkStatusChange() {
    const selectedIds = getSelectedMemberIds();
    if (selectedIds.length === 0) {
        ChurchCMS.showToast('Please select members to change status', 'warning');
        return;
    }
    
    showBulkActions();
}

function showBulkActions() {
    const selectedIds = getSelectedMemberIds();
    if (selectedIds.length === 0) {
        ChurchCMS.showToast('Please select at least one member', 'warning');
        return;
    }
    
    document.getElementById('bulkActionContent').classList.remove('d-none');
    document.getElementById('statusChangeForm').classList.add('d-none');
    
    const modal = new bootstrap.Modal(document.getElementById('bulkActionsModal'));
    modal.show();
}

function exportSelectedMembers() {
    const selectedIds = getSelectedMemberIds();
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?php echo BASE_URL; ?>modules/members/export.php';
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'export_selected';
    form.appendChild(actionInput);
    
    const idsInput = document.createElement('input');
    idsInput.type = 'hidden';
    idsInput.name = 'member_ids';
    idsInput.value = JSON.stringify(selectedIds);
    form.appendChild(idsInput);
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
    
    bootstrap.Modal.getInstance(document.getElementById('bulkActionsModal')).hide();
    ChurchCMS.showToast('Export started for selected members', 'success');
}

function sendBulkSMS() {
    const selectedIds = getSelectedMemberIds();
    const url = '<?php echo BASE_URL; ?>modules/sms/send.php?member_ids=' + selectedIds.join(',');
    window.location.href = url;
}

function changeBulkStatus() {
    const selectedIds = getSelectedMemberIds();
    document.getElementById('bulkMemberIds').value = JSON.stringify(selectedIds);
    
    document.getElementById('bulkActionContent').classList.add('d-none');
    document.getElementById('statusChangeForm').classList.remove('d-none');
}

// Print member profile
function printMemberProfile(memberId) {
    const printUrl = '<?php echo BASE_URL; ?>modules/members/print.php?id=' + memberId;
    window.open(printUrl, '_blank', 'width=800,height=600');
}

// Quick status change
function quickStatusChange(memberId, currentStatus) {
    const statuses = <?php echo json_encode(MEMBER_STATUS_OPTIONS); ?>;
    let options = '';
    
    Object.entries(statuses).forEach(([value, label]) => {
        const selected = value === currentStatus ? 'selected' : '';
        options += `<option value="${value}" ${selected}>${label}</option>`;
    });
    
    const form = `
        <form method="POST" action="<?php echo BASE_URL; ?>modules/members/update_status.php">
            <input type="hidden" name="member_id" value="${memberId}">
            <div class="mb-3">
                <label class="form-label">New Status</label>
                <select name="new_status" class="form-select" required>
                    ${options}
                </select>
            </div>
            <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning">Update Status</button>
            </div>
        </form>
    `;
    
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.innerHTML = `
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Change Member Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    ${form}
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
    
    modal.addEventListener('hidden.bs.modal', function() {
        document.body.removeChild(modal);
    });
}
</script>

<?php
// Include footer
include '../../includes/footer.php';
?>