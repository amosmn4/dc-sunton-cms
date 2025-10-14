<?php
/**
 * User Management
 * Manage system users and permissions
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

requireLogin();
if ($_SESSION['user_role'] !== 'administrator') {
    setFlashMessage('error', 'Only administrators can access user management');
    redirect(BASE_URL . 'modules/dashboard/');
}

$db = Database::getInstance();

// Handle AJAX requests
if (isAjaxRequest()) {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'get_user') {
        $id = $_GET['id'] ?? 0;
        $user = getRecord('users', 'id', $id);
        unset($user['password']); // Don't send password
        sendJSONResponse(['success' => true, 'data' => $user]);
    }
    
    if ($action === 'toggle_status') {
        $id = $_POST['id'] ?? 0;
        $user = getRecord('users', 'id', $id);
        
        if ($id == $_SESSION['user_id']) {
            sendJSONResponse(['success' => false, 'message' => 'You cannot deactivate your own account']);
        }
        
        $newStatus = $user['is_active'] ? 0 : 1;
        $result = updateRecord('users', ['is_active' => $newStatus], ['id' => $id]);
        
        if ($result) {
            logActivity('Toggled user status', 'users', $id);
            sendJSONResponse(['success' => true, 'message' => 'User status updated']);
        } else {
            sendJSONResponse(['success' => false, 'message' => 'Failed to update status']);
        }
    }
    
    if ($action === 'reset_password') {
        $id = $_POST['id'] ?? 0;
        $newPassword = generateRandomString(12);
        $hashedPassword = hashPassword($newPassword);
        
        $result = updateRecord('users', ['password' => $hashedPassword], ['id' => $id]);
        
        if ($result) {
            logActivity('Reset user password', 'users', $id);
            sendJSONResponse(['success' => true, 'message' => 'Password reset successfully', 'new_password' => $newPassword]);
        } else {
            sendJSONResponse(['success' => false, 'message' => 'Failed to reset password']);
        }
    }
    
    if ($action === 'delete_user') {
        $id = $_POST['id'] ?? 0;
        
        if ($id == $_SESSION['user_id']) {
            sendJSONResponse(['success' => false, 'message' => 'You cannot delete your own account']);
        }
        
        $result = deleteRecord('users', ['id' => $id]);
        
        if ($result) {
            logActivity('Deleted user', 'users', $id);
            sendJSONResponse(['success' => true, 'message' => 'User deleted successfully']);
        } else {
            sendJSONResponse(['success' => false, 'message' => 'Failed to delete user']);
        }
    }
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isAjaxRequest()) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_user') {
        // Validate email uniqueness
        $existingUser = getRecord('users', 'email', $_POST['email']);
        if ($existingUser) {
            setFlashMessage('error', 'Email already exists');
            redirect($_SERVER['PHP_SELF']);
        }
        
        // Validate username uniqueness
        $existingUser = getRecord('users', 'username', $_POST['username']);
        if ($existingUser) {
            setFlashMessage('error', 'Username already exists');
            redirect($_SERVER['PHP_SELF']);
        }
        
        $data = [
            'username' => sanitizeInput($_POST['username']),
            'email' => sanitizeInput($_POST['email']),
            'password' => hashPassword($_POST['password']),
            'role' => sanitizeInput($_POST['role']),
            'first_name' => sanitizeInput($_POST['first_name']),
            'last_name' => sanitizeInput($_POST['last_name']),
            'phone' => sanitizeInput($_POST['phone']),
            'is_active' => 1
        ];
        
        $result = insertRecord('users', $data);
        
        if ($result) {
            logActivity('Created new user: ' . $data['username'], 'users', $result);
            setFlashMessage('success', 'User created successfully');
        } else {
            setFlashMessage('error', 'Failed to create user');
        }
        
        redirect($_SERVER['PHP_SELF']);
    }
    
    if ($action === 'edit_user') {
        $id = (int)$_POST['user_id'];
        
        $data = [
            'email' => sanitizeInput($_POST['email']),
            'role' => sanitizeInput($_POST['role']),
            'first_name' => sanitizeInput($_POST['first_name']),
            'last_name' => sanitizeInput($_POST['last_name']),
            'phone' => sanitizeInput($_POST['phone']),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        // Only update password if provided
        if (!empty($_POST['password'])) {
            $data['password'] = hashPassword($_POST['password']);
        }
        
        $result = updateRecord('users', $data, ['id' => $id]);
        
        if ($result) {
            logActivity('Updated user', 'users', $id);
            setFlashMessage('success', 'User updated successfully');
        } else {
            setFlashMessage('error', 'Failed to update user');
        }
        
        redirect($_SERVER['PHP_SELF']);
    }
}

// Get all users
$stmt = $db->executeQuery("
    SELECT 
        u.*,
        COUNT(DISTINCT al.id) as activity_count
    FROM users u
    LEFT JOIN activity_logs al ON u.id = al.user_id AND al.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY u.id
    ORDER BY u.created_at DESC
");
$users = $stmt->fetchAll();

// Statistics
$totalUsers = count($users);
$activeUsers = count(array_filter($users, fn($u) => $u['is_active']));
$adminCount = count(array_filter($users, fn($u) => $u['role'] === 'administrator'));

$page_title = 'User Management';
$page_icon = 'fas fa-users-cog';
$breadcrumb = [
    ['title' => 'Administration', 'url' => BASE_URL . 'modules/admin/'],
    ['title' => 'Users']
];

include '../../includes/header.php';
?>

<!-- Action Bar -->
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <p class="text-muted mb-0">Manage system users, roles, and permissions</p>
            </div>
            <button class="btn btn-church-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="fas fa-user-plus me-2"></i>Add User
            </button>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="stats-icon bg-primary bg-opacity-10 text-primary">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="stats-number"><?php echo $totalUsers; ?></div>
                        <div class="stats-label">Total Users</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="stats-icon bg-success bg-opacity-10 text-success">
                            <i class="fas fa-user-check"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="stats-number"><?php echo $activeUsers; ?></div>
                        <div class="stats-label">Active Users</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="stats-icon bg-danger bg-opacity-10 text-danger">
                            <i class="fas fa-user-shield"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="stats-number"><?php echo $adminCount; ?></div>
                        <div class="stats-label">Administrators</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Users Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 text-church-blue">
            <i class="fas fa-list me-2"></i>System Users
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle" id="usersTable">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Last Login</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar bg-church-blue text-white rounded-circle me-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                    <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>
                                    <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                    <span class="badge bg-info ms-2">You</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td><code><?php echo htmlspecialchars($user['username']); ?></code></td>
                        <td><small><?php echo htmlspecialchars($user['email']); ?></small></td>
                        <td><small><?php echo htmlspecialchars($user['phone']); ?></small></td>
                        <td>
                            <span class="badge bg-<?php echo $user['role'] === 'administrator' ? 'danger' : 'secondary'; ?>">
                                <?php echo getUserRoleDisplay($user['role']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($user['last_login']): ?>
                                <small><?php echo timeAgo($user['last_login']); ?></small>
                            <?php else: ?>
                                <small class="text-muted">Never</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($user['is_active']): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-warning" onclick="editUser(<?php echo $user['id']; ?>)" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-outline-<?php echo $user['is_active'] ? 'secondary' : 'success'; ?>" 
                                        onclick="toggleStatus(<?php echo $user['id']; ?>)" 
                                        title="<?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>"
                                        <?php echo $user['id'] == $_SESSION['user_id'] ? 'disabled' : ''; ?>>
                                    <i class="fas fa-power-off"></i>
                                </button>
                                <button class="btn btn-outline-info" onclick="resetPassword(<?php echo $user['id']; ?>)" title="Reset Password">
                                    <i class="fas fa-key"></i>
                                </button>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <button class="btn btn-outline-danger" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" title="Delete">
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
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_user">
                <div class="modal-header bg-church-blue text-white">
                    <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Add New User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="first_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="last_name" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="username" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" name="phone" placeholder="+254...">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role <span class="text-danger">*</span></label>
                            <select class="form-select" name="role" required>
                                <option value="">Select Role</option>
                                <?php foreach (USER_ROLES as $key => $label): ?>
                                <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="password" required minlength="8">
                        <small class="text-muted">Minimum 8 characters</small>
                    </div>
                    
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        The user will be able to change their password after first login.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-church-primary">
                        <i class="fas fa-save me-2"></i>Create User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="modal-header bg-church-blue text-white">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="editUserContent">
                    <!-- Content loaded via AJAX -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-church-primary">
                        <i class="fas fa-save me-2"></i>Update User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#usersTable').DataTable({
        order: [[0, 'asc']],
        pageLength: 25
    });
});

function editUser(id) {
    $.ajax({
        url: '?action=get_user&id=' + id,
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const user = response.data;
                $('#edit_user_id').val(user.id);
                
                const content = `
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="first_name" value="${user.first_name}" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="last_name" value="${user.last_name}" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" value="${user.email}" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" name="phone" value="${user.phone || ''}">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role <span class="text-danger">*</span></label>
                        <select class="form-select" name="role" required>
                            <?php foreach (USER_ROLES as $key => $label): ?>
                            <option value="<?php echo $key; ?>" ${user.role === '<?php echo $key; ?>' ? 'selected' : ''}><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" class="form-control" name="password" minlength="8">
                        <small class="text-muted">Leave blank to keep current password</small>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" ${user.is_active ? 'checked' : ''} id="edit_is_active">
                        <label class="form-check-label" for="edit_is_active">
                            Active User
                        </label>
                    </div>
                `;
                
                $('#editUserContent').html(content);
                $('#editUserModal').modal('show');
            }
        }
    });
}

function toggleStatus(id) {
    $.ajax({
        url: '?action=toggle_status',
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
        }
    });
}

function resetPassword(id) {
    ChurchCMS.showConfirm(
        'Reset password for this user? A new random password will be generated.',
        function() {
            $.ajax({
                url: '?action=reset_password',
                method: 'POST',
                data: { id: id },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        ChurchCMS.showConfirm(
                            `New password: <strong>${response.new_password}</strong><br><br>Please save this and share with the user.`,
                            function() {
                                ChurchCMS.copyToClipboard(response.new_password, 'Password copied to clipboard!');
                            },
                            null,
                            'Password Reset'
                        );
                    } else {
                        ChurchCMS.showToast(response.message, 'error');
                    }
                }
            });
        }
    );
}

function deleteUser(id, username) {
    ChurchCMS.showConfirm(
        `Are you sure you want to delete user "${username}"? This action cannot be undone.`,
        function() {
            $.ajax({
                url: '?action=delete_user',
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
                }
            });
        }
    );
}
</script>

<?php include '../../includes/footer.php'; ?>