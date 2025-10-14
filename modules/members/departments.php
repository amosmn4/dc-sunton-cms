<?php
/**
 * Department Management
 * Manage church departments, groups, and ministries
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Check authentication and permissions
requireLogin();
if (!hasPermission('members')) {
    setFlashMessage('error', 'You do not have permission to access this page');
    redirect(BASE_URL . 'modules/dashboard/');
}

// Handle AJAX requests
if (isAjaxRequest()) {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'get_department') {
        $id = $_GET['id'] ?? 0;
        $department = getRecord('departments', 'id', $id);
        sendJSONResponse(['success' => true, 'data' => $department]);
    }
    
    if ($action === 'delete_department') {
        $id = $_POST['id'] ?? 0;
        
        // Check if department has members
        $memberCount = getRecordCount('member_departments', ['department_id' => $id, 'is_active' => 1]);
        
        if ($memberCount > 0) {
            sendJSONResponse(['success' => false, 'message' => 'Cannot delete department with active members']);
        }
        
        $result = deleteRecord('departments', ['id' => $id]);
        
        if ($result) {
            logActivity('Deleted department', 'departments', $id);
            sendJSONResponse(['success' => true, 'message' => 'Department deleted successfully']);
        } else {
            sendJSONResponse(['success' => false, 'message' => 'Failed to delete department']);
        }
    }
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isAjaxRequest()) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_department') {
        $data = [
            'name' => sanitizeInput($_POST['name']),
            'description' => sanitizeInput($_POST['description']),
            'department_type' => sanitizeInput($_POST['department_type']),
            'head_member_id' => !empty($_POST['head_member_id']) ? (int)$_POST['head_member_id'] : null,
            'parent_department_id' => !empty($_POST['parent_department_id']) ? (int)$_POST['parent_department_id'] : null,
            'meeting_day' => sanitizeInput($_POST['meeting_day'] ?? ''),
            'meeting_time' => sanitizeInput($_POST['meeting_time'] ?? ''),
            'meeting_location' => sanitizeInput($_POST['meeting_location'] ?? ''),
            'target_size' => !empty($_POST['target_size']) ? (int)$_POST['target_size'] : null,
            'is_active' => 1
        ];
        
        $result = insertRecord('departments', $data);
        
        if ($result) {
            logActivity('Added new department: ' . $data['name'], 'departments', $result);
            setFlashMessage('success', 'Department added successfully');
        } else {
            setFlashMessage('error', 'Failed to add department');
        }
        
        redirect($_SERVER['PHP_SELF']);
    }
    
    if ($action === 'edit_department') {
        $id = (int)$_POST['department_id'];
        
        $data = [
            'name' => sanitizeInput($_POST['name']),
            'description' => sanitizeInput($_POST['description']),
            'department_type' => sanitizeInput($_POST['department_type']),
            'head_member_id' => !empty($_POST['head_member_id']) ? (int)$_POST['head_member_id'] : null,
            'parent_department_id' => !empty($_POST['parent_department_id']) ? (int)$_POST['parent_department_id'] : null,
            'meeting_day' => sanitizeInput($_POST['meeting_day'] ?? ''),
            'meeting_time' => sanitizeInput($_POST['meeting_time'] ?? ''),
            'meeting_location' => sanitizeInput($_POST['meeting_location'] ?? ''),
            'target_size' => !empty($_POST['target_size']) ? (int)$_POST['target_size'] : null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        $result = updateRecord('departments', $data, ['id' => $id]);
        
        if ($result) {
            logActivity('Updated department: ' . $data['name'], 'departments', $id);
            setFlashMessage('success', 'Department updated successfully');
        } else {
            setFlashMessage('error', 'Failed to update department');
        }
        
        redirect($_SERVER['PHP_SELF']);
    }
}

// Get all departments with member counts
$db = Database::getInstance();
$stmt = $db->executeQuery("
    SELECT 
        d.*,
        CONCAT(m.first_name, ' ', m.last_name) as head_name,
        COUNT(DISTINCT md.member_id) as member_count,
        p.name as parent_name
    FROM departments d
    LEFT JOIN members m ON d.head_member_id = m.id
    LEFT JOIN member_departments md ON d.id = md.department_id AND md.is_active = 1
    LEFT JOIN departments p ON d.parent_department_id = p.id
    GROUP BY d.id
    ORDER BY d.department_type, d.name
");
$departments = $stmt->fetchAll();

// Get active members for dropdown
$activeMembers = getRecords('members', ['membership_status' => 'active'], 'first_name, last_name');

// Page settings
$page_title = 'Departments & Groups';
$page_icon = 'fas fa-users-cog';
$breadcrumb = [
    ['title' => 'Members', 'url' => BASE_URL . 'modules/members/'],
    ['title' => 'Departments']
];

include '../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <p class="text-muted mb-0">Manage church departments, ministries, and groups</p>
            </div>
            <button class="btn btn-church-primary" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
                <i class="fas fa-plus me-2"></i>Add Department
            </button>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="stats-icon bg-primary bg-opacity-10 text-primary">
                            <i class="fas fa-layer-group"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="stats-number"><?php echo count($departments); ?></div>
                        <div class="stats-label">Total Departments</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="stats-icon bg-success bg-opacity-10 text-success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="stats-number"><?php echo count(array_filter($departments, fn($d) => $d['is_active'])); ?></div>
                        <div class="stats-label">Active Departments</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="stats-icon bg-info bg-opacity-10 text-info">
                            <i class="fas fa-praying-hands"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="stats-number"><?php echo count(array_filter($departments, fn($d) => $d['department_type'] === 'ministry')); ?></div>
                        <div class="stats-label">Ministries</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="stats-icon bg-warning bg-opacity-10 text-warning">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="stats-number"><?php echo array_sum(array_column($departments, 'member_count')); ?></div>
                        <div class="stats-label">Total Members</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Departments Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 text-church-blue">
            <i class="fas fa-list me-2"></i>All Departments
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle" id="departmentsTable">
                <thead>
                    <tr>
                        <th>Department Name</th>
                        <th>Type</th>
                        <th>Head</th>
                        <th>Members</th>
                        <th>Meeting Schedule</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($departments as $dept): ?>
                    <tr>
                        <td>
                            <div>
                                <strong><?php echo htmlspecialchars($dept['name']); ?></strong>
                                <?php if ($dept['parent_name']): ?>
                                <br><small class="text-muted">Under: <?php echo htmlspecialchars($dept['parent_name']); ?></small>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-secondary">
                                <?php echo ucwords(str_replace('_', ' ', $dept['department_type'])); ?>
                            </span>
                        </td>
                        <td><?php echo $dept['head_name'] ? htmlspecialchars($dept['head_name']) : '<span class="text-muted">Not assigned</span>'; ?></td>
                        <td>
                            <span class="badge bg-info"><?php echo $dept['member_count']; ?> members</span>
                            <?php if ($dept['target_size']): ?>
                            <br><small class="text-muted">Target: <?php echo $dept['target_size']; ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($dept['meeting_day'] && $dept['meeting_time']): ?>
                            <small>
                                <i class="fas fa-calendar-alt text-muted"></i> 
                                <?php echo htmlspecialchars($dept['meeting_day']); ?><br>
                                <i class="fas fa-clock text-muted"></i> 
                                <?php echo date('h:i A', strtotime($dept['meeting_time'])); ?>
                            </small>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($dept['is_active']): ?>
                            <span class="badge bg-success">Active</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary" onclick="viewDepartment(<?php echo $dept['id']; ?>)" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-outline-warning" onclick="editDepartment(<?php echo $dept['id']; ?>)" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-outline-danger" onclick="deleteDepartment(<?php echo $dept['id']; ?>, '<?php echo htmlspecialchars($dept['name']); ?>')" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Department Modal -->
<div class="modal fade" id="addDepartmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_department">
                <div class="modal-header bg-church-blue text-white">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add New Department</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Department Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="department_type" required>
                                <option value="">Select Type</option>
                                <?php foreach (DEPARTMENT_TYPES as $key => $value): ?>
                                <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Department Head</label>
                            <select class="form-select" name="head_member_id">
                                <option value="">Select Member</option>
                                <?php foreach ($activeMembers as $member): ?>
                                <option value="<?php echo $member['id']; ?>">
                                    <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Parent Department</label>
                            <select class="form-select" name="parent_department_id">
                                <option value="">None (Top Level)</option>
                                <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>">
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Meeting Day</label>
                            <select class="form-select" name="meeting_day">
                                <option value="">Select Day</option>
                                <option value="Sunday">Sunday</option>
                                <option value="Monday">Monday</option>
                                <option value="Tuesday">Tuesday</option>
                                <option value="Wednesday">Wednesday</option>
                                <option value="Thursday">Thursday</option>
                                <option value="Friday">Friday</option>
                                <option value="Saturday">Saturday</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Meeting Time</label>
                            <input type="time" class="form-control" name="meeting_time">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Target Size</label>
                            <input type="number" class="form-control" name="target_size" min="0">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Meeting Location</label>
                        <input type="text" class="form-control" name="meeting_location" placeholder="e.g., Main Hall, Room 101">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-church-primary">
                        <i class="fas fa-save me-2"></i>Save Department
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Department Modal -->
<div class="modal fade" id="editDepartmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit_department">
                <input type="hidden" name="department_id" id="edit_department_id">
                <div class="modal-header bg-church-blue text-white">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Department</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="editDepartmentContent">
                    <!-- Content loaded via AJAX -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-church-primary">
                        <i class="fas fa-save me-2"></i>Update Department
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Initialize DataTable
$(document).ready(function() {
    $('#departmentsTable').DataTable({
        order: [[0, 'asc']],
        pageLength: 25,
        responsive: true
    });
});

function viewDepartment(id) {
    window.location.href = 'department_details.php?id=' + id;
}

function editDepartment(id) {
    $.ajax({
        url: '?action=get_department&id=' + id,
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const dept = response.data;
                $('#edit_department_id').val(dept.id);
                
                const content = `
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Department Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" value="${dept.name}" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="department_type" required>
                                <?php foreach (DEPARTMENT_TYPES as $key => $value): ?>
                                <option value="<?php echo $key; ?>" ${dept.department_type === '<?php echo $key; ?>' ? 'selected' : ''}><?php echo $value; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3">${dept.description || ''}</textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Department Head</label>
                            <select class="form-select" name="head_member_id">
                                <option value="">Select Member</option>
                                <?php foreach ($activeMembers as $member): ?>
                                <option value="<?php echo $member['id']; ?>" ${dept.head_member_id == <?php echo $member['id']; ?> ? 'selected' : ''}>
                                    <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Target Size</label>
                            <input type="number" class="form-control" name="target_size" value="${dept.target_size || ''}" min="0">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Meeting Day</label>
                            <select class="form-select" name="meeting_day">
                                <option value="">Select Day</option>
                                <option value="Sunday" ${dept.meeting_day === 'Sunday' ? 'selected' : ''}>Sunday</option>
                                <option value="Monday" ${dept.meeting_day === 'Monday' ? 'selected' : ''}>Monday</option>
                                <option value="Tuesday" ${dept.meeting_day === 'Tuesday' ? 'selected' : ''}>Tuesday</option>
                                <option value="Wednesday" ${dept.meeting_day === 'Wednesday' ? 'selected' : ''}>Wednesday</option>
                                <option value="Thursday" ${dept.meeting_day === 'Thursday' ? 'selected' : ''}>Thursday</option>
                                <option value="Friday" ${dept.meeting_day === 'Friday' ? 'selected' : ''}>Friday</option>
                                <option value="Saturday" ${dept.meeting_day === 'Saturday' ? 'selected' : ''}>Saturday</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Meeting Time</label>
                            <input type="time" class="form-control" name="meeting_time" value="${dept.meeting_time || ''}">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Meeting Location</label>
                            <input type="text" class="form-control" name="meeting_location" value="${dept.meeting_location || ''}">
                        </div>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" ${dept.is_active ? 'checked' : ''} id="edit_is_active">
                        <label class="form-check-label" for="edit_is_active">
                            Active Department
                        </label>
                    </div>
                `;
                
                $('#editDepartmentContent').html(content);
                $('#editDepartmentModal').modal('show');
            }
        },
        error: function() {
            ChurchCMS.showToast('Failed to load department details', 'error');
        }
    });
}

function deleteDepartment(id, name) {
    ChurchCMS.showConfirm(
        `Are you sure you want to delete the department "${name}"? This action cannot be undone.`,
        function() {
            $.ajax({
                url: '?action=delete_department',
                method: 'POST',
                data: { id: id },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        ChurchCMS.showToast(response.message, 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        ChurchCMS.showToast(response.message, 'error');
                    }
                },
                error: function() {
                    ChurchCMS.showToast('Failed to delete department', 'error');
                }
            });
        }
    );
}
</script>

<?php include '../../includes/footer.php'; ?>