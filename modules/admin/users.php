// Create user
                    $userData = [
                        'username' => $validation['data']['username'],
                        'email' => $validation['data']['email'],
                        'password' => hashPassword($_POST['password']),
                        'role' => $validation['data']['role'],
                        'first_name' => $validation['data']['first_name'],
                        'last_name' => $validation['data']['last_name'],
                        'phone' => $validation['data']['phone'],
                        'is_active' => 1,
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    
                    $userId = insertRecord('users', $userData);
                    
                    if ($userId) {
                        logActivity('User created', 'users', $userId, null, $userData);
                        setFlashMessage('success', 'User created successfully');
                    } else {
                        setFlashMessage('error', 'Failed to create user');
                    }
                }
            }
            
        } elseif ($action === 'update_user') {
            $userId = (int) $_POST['user_id'];
            
            // Don't allow editing the main admin user if current user is not admin
            if ($userId === 1 && $_SESSION['user_id'] !== 1) {
                setFlashMessage('error', 'Cannot edit main administrator account');
            } else {
                $validation = validateInput($_POST, [
                    'username' => ['required', 'min:3', 'max:50'],
                    'email' => ['required', 'email'],
                    'role' => ['required'],
                    'first_name' => ['required', 'max:50'],
                    'last_name' => ['required', 'max:50'],
                    'phone' => ['max:20'],
                    'is_active' => []
                ]);
                
                if (!$validation['valid']) {
                    setFlashMessage('error', implode(', ', $validation['errors']));
                } else {
                    // Check for duplicate username/email (excluding current user)
                    $existingUser = $db->executeQuery(
                        "SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?",
                        [$validation['data']['username'], $validation['data']['email'], $userId]
                    )->fetch();
                    
                    if ($existingUser) {
                        setFlashMessage('error', 'Username or email already exists');
                    } else {
                        // Get old data for logging
                        $oldData = getRecord('users', 'id', $userId);
                        
                        $updateData = [
                            'username' => $validation['data']['username'],
                            'email' => $validation['data']['email'],
                            'role' => $validation['data']['role'],
                            'first_name' => $validation['data']['first_name'],
                            'last_name' => $validation['data']['last_name'],
                            'phone' => $validation['data']['phone'],
                            'is_active' => isset($_POST['is_active']) ? 1 : 0,
                            'updated_at' => date('Y-m-d H:i:s')
                        ];
                        
                        // Update password if provided
                        if (!empty($_POST['password'])) {
                            if ($_POST['password'] !== $_POST['confirm_password']) {
                                setFlashMessage('error', 'Passwords do not match');
                            } else {
                                $passwordValidation = validatePassword($_POST['password']);
                                if (!$passwordValidation['valid']) {
                                    setFlashMessage('error', implode(', ', $passwordValidation['errors']));
                                } else {
                                    $updateData['password'] = hashPassword($_POST['password']);
                                }
                            }
                        }
                        
                        if (!isset($_SESSION['flash_message'])) {
                            $updated = updateRecord('users', $updateData, ['id' => $userId]);
                            
                            if ($updated) {
                                logActivity('User updated', 'users', $userId, $oldData, $updateData);
                                setFlashMessage('success', 'User updated successfully');
                            } else {
                                setFlashMessage('error', 'Failed to update user');
                            }
                        }
                    }
                }
            }
            
        } elseif ($action === 'delete_user') {
            $userId = (int) $_POST['user_id'];
            
            // Don't allow deleting the main admin or current user
            if ($userId === 1) {
                setFlashMessage('error', 'Cannot delete main administrator account');
            } elseif ($userId === $_SESSION['user_id']) {
                setFlashMessage('error', 'Cannot delete your own account');
            } else {
                $userData = getRecord('users', 'id', $userId);
                
                if ($userData) {
                    $deleted = deleteRecord('users', ['id' => $userId]);
                    
                    if ($deleted) {
                        logActivity('User deleted', 'users', $userId, $userData, null);
                        setFlashMessage('success', 'User deleted successfully');
                    } else {
                        setFlashMessage('error', 'Failed to delete user');
                    }
                } else {
                    setFlashMessage('error', 'User not found');
                }
            }
            
        } elseif ($action === 'toggle_status') {
            $userId = (int) $_POST['user_id'];
            $newStatus = (int) $_POST['status'];
            
            if ($userId === 1 && $newStatus === 0) {
                setFlashMessage('error', 'Cannot deactivate main administrator account');
            } elseif ($userId === $_SESSION['user_id'] && $newStatus === 0) {
                setFlashMessage('error', 'Cannot deactivate your own account');
            } else {
                $oldData = getRecord('users', 'id', $userId);
                $updated = updateRecord('users', ['is_active' => $newStatus], ['id' => $userId]);
                
                if ($updated) {
                    $statusText = $newStatus ? 'activated' : 'deactivated';
                    logActivity("User {$statusText}", 'users', $userId, $oldData, ['is_active' => $newStatus]);
                    setFlashMessage('success', "User {$statusText} successfully");
                } else {
                    setFlashMessage('error', 'Failed to update user status');
                }
            }
            
        } elseif ($action === 'reset_password') {
            $userId = (int) $_POST['user_id'];
            $tempPassword = generateRandomString(12);
            
            $userData = getRecord('users', 'id', $userId);
            if ($userData) {
                $updated = updateRecord('users', [
                    'password' => hashPassword($tempPassword),
                    'updated_at' => date('Y-m-d H:i:s')
                ], ['id' => $userId]);
                
                if ($updated) {
                    logActivity('Password reset', 'users', $userId);
                    
                    // In a real system, you would send this via email
                    setFlashMessage('success', "Password reset for {$userData['username']}. Temporary password: {$tempPassword}");
                } else {
                    setFlashMessage('error', 'Failed to reset password');
                }
            } else {
                setFlashMessage('error', 'User not found');
            }
        }
        
    } catch (Exception $e) {
        error_log("User management error: " . $e->getMessage());
        setFlashMessage('error', 'An error occurred while processing your request');
    }
    
    // Redirect to prevent form resubmission
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Get filter parameters
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build query conditions
$conditions = [];
$params = [];

if ($filter === 'active') {
    $conditions[] = "is_active = 1";
} elseif ($filter === 'inactive') {
    $conditions[] = "is_active = 0";
} elseif ($filter === 'recent') {
    $conditions[] = "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
}

if (!empty($search)) {
    $conditions[] = "(username LIKE ? OR email LIKE ? OR CONCAT(first_name, ' ', last_name) LIKE ?)";
    $searchParam = "%{$search}%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
}

if (!empty($role_filter)) {
    $conditions[] = "role = ?";
    $params[] = $role_filter;
}

if (!empty($status_filter)) {
    $conditions[] = "is_active = ?";
    $params[] = $status_filter;
}

// Get users with pagination
$page = (int) ($_GET['page'] ?? 1);
$limit = 25;
$offset = ($page - 1) * $limit;

try {
    $db = Database::getInstance();
    
    // Count total users
    $countSql = "SELECT COUNT(*) FROM users" . 
                (!empty($conditions) ? " WHERE " . implode(" AND ", $conditions) : "");
    $totalUsers = $db->executeQuery($countSql, $params)->fetchColumn();
    
    // Get users
    $sql = "SELECT * FROM users" . 
           (!empty($conditions) ? " WHERE " . implode(" AND ", $conditions) : "") .
           " ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}";
    $users = $db->executeQuery($sql, $params)->fetchAll();
    
    // Generate pagination
    $pagination = generatePagination($totalUsers, $page, $limit);
    
} catch (Exception $e) {
    error_log("Error fetching users: " . $e->getMessage());
    $users = [];
    $totalUsers = 0;
    $pagination = generatePagination(0, 1, $limit);
    setFlashMessage('error', 'Error loading users');
}

// Get role statistics
try {
    $roleStats = $db->executeQuery("
        SELECT role, COUNT(*) as count 
        FROM users 
        WHERE is_active = 1 
        GROUP BY role
    ")->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {
    $roleStats = [];
}

// Include header
include_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h1><?php echo $page_title; ?></h1>
            <p class="text-muted">Manage system users, roles, and permissions</p>
        </div>
        <div class="col-md-4 text-end">
            <button type="button" class="btn btn-church-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="fas fa-user-plus me-2"></i>Add New User
            </button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stats-card">
                <div class="d-flex align-items-center">
                    <div class="stats-icon bg-gradient-primary">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="ms-3">
                        <div class="stats-number"><?php echo number_format($totalUsers); ?></div>
                        <div class="stats-label">Total Users</div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php foreach (['administrator', 'pastor', 'secretary'] as $role): ?>
        <div class="col-md-3 mb-3">
            <div class="stats-card">
                <div class="d-flex align-items-center">
                    <div class="stats-icon bg-gradient-<?php echo $role === 'administrator' ? 'danger' : ($role === 'pastor' ? 'success' : 'info'); ?>">
                        <i class="fas fa-<?php echo $role === 'administrator' ? 'shield-alt' : ($role === 'pastor' ? 'cross' : 'user'); ?>"></i>
                    </div>
                    <div class="ms-3">
                        <div class="stats-number"><?php echo $roleStats[$role] ?? 0; ?></div>
                        <div class="stats-label"><?php echo getUserRoleDisplay($role); ?></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="filter" class="form-label">Filter</label>
                    <select class="form-select" id="filter" name="filter">
                        <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Users</option>
                        <option value="active" <?php echo $filter === 'active' ? 'selected' : ''; ?>>Active Users</option>
                        <option value="inactive" <?php echo $filter === 'inactive' ? 'selected' : ''; ?>>Inactive Users</option>
                        <option value="recent" <?php echo $filter === 'recent' ? 'selected' : ''; ?>>Recent (30 days)</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="role" class="form-label">Role</label>
                    <select class="form-select" id="role" name="role">
                        <option value="">All Roles</option>
                        <?php foreach (USER_ROLES as $role_key => $role_name): ?>
                        <option value="<?php echo $role_key; ?>" <?php echo $role_filter === $role_key ? 'selected' : ''; ?>>
                            <?php echo $role_name; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Search username, email, or name...">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i>Search
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card">
        <div class="card-header">
            <h6 class="card-title mb-0">
                <i class="fas fa-table me-2"></i>User List
                <span class="badge bg-secondary ms-2"><?php echo number_format($totalUsers); ?> total</span>
            </h6>
        </div>
        <div class="card-body">
            <?php if (!empty($users)): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Created</th>
                            <th class="no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar bg-church-blue text-white rounded-circle me-3 d-flex align-items-center justify-content-center" 
                                         style="width: 40px; height: 40px;">
                                        <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($user['username']); ?></div>
                                        <div class="text-muted small"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                                        <div class="text-muted small"><?php echo htmlspecialchars($user['email']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $user['role'] === 'administrator' ? 'danger' : ($user['role'] === 'pastor' ? 'success' : 'secondary'); ?>">
                                    <?php echo getUserRoleDisplay($user['role']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($user['is_active']): ?>
                                    <span class="status-badge status-active">Active</span>
                                <?php else: ?>
                                    <span class="status-badge status-inactive">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($user['last_login']): ?>
                                    <span title="<?php echo formatDisplayDateTime($user['last_login']); ?>">
                                        <?php echo timeAgo($user['last_login']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">Never</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span title="<?php echo formatDisplayDateTime($user['created_at']); ?>">
                                    <?php echo formatDisplayDate($user['created_at']); ?>
                                </span>
                            </td>
                            <td class="no-print">
                                <div class="table-actions">
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <?php if ($user['id'] != $_SESSION['user_id'] && $user['id'] != 1): ?>
                                    <button type="button" class="btn btn-sm btn-outline-warning" 
                                            onclick="toggleUserStatus(<?php echo $user['id']; ?>, <?php echo $user['is_active'] ? 0 : 1; ?>)">
                                        <i class="fas fa-<?php echo $user['is_active'] ? 'ban' : 'check'; ?>"></i>
                                    </button>
                                    
                                    <button type="button" class="btn btn-sm btn-outline-info" 
                                            onclick="resetPassword(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                        <i class="fas fa-key"></i>
                                    </button>
                                    
                                    <button type="button" class="btn btn-sm btn-outline-danger confirm-delete" 
                                            onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
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
            <?php echo generatePaginationHTML($pagination, $_SERVER['PHP_SELF'] . '?' . http_build_query(array_filter($_GET, function($key) { return $key !== 'page'; }, ARRAY_FILTER_USE_KEY))); ?>
            
            <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No Users Found</h5>
                <p class="text-muted">No users match your current filter criteria.</p>
                <?php if (!empty($search) || !empty($role_filter) || $filter !== 'all'): ?>
                <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-outline-primary">
                    <i class="fas fa-times me-1"></i>Clear Filters
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="create_user">
                
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus me-2"></i>Add New User
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username *</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                                <div class="invalid-feedback">Please provide a valid username.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                                <div class="invalid-feedback">Please provide a valid email.</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password" class="form-label">Password *</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <div class="invalid-feedback">Please provide a password.</div>
                                <div class="form-text">Minimum 8 characters with uppercase, lowercase, number, and special character.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password *</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                <div class="invalid-feedback">Please confirm your password.</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="first_name" class="form-label">First Name *</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required>
                                <div class="invalid-feedback">Please provide first name.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="last_name" class="form-label">Last Name *</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required>
                                <div class="invalid-feedback">Please provide last name.</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="role" class="form-label">Role *</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="">Select Role</option>
                                    <?php foreach (USER_ROLES as $role_key => $role_name): ?>
                                    <?php if ($role_key !== 'administrator' || $_SESSION['user_role'] === 'administrator'): ?>
                                    <option value="<?php echo $role_key; ?>"><?php echo $role_name; ?></option>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select a role.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="text" class="form-control" id="phone" name="phone" placeholder="+254700000000">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-church-primary">
                        <i class="fas fa-save me-1"></i>Create User
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
            <form method="POST" id="editUserForm" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="update_user">
                <input type="hidden" name="user_id" id="edit_user_id">
                
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-edit me-2"></i>Edit User
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_username" class="form-label">Username *</label>
                                <input type="text" class="form-control" id="edit_username" name="username" required>
                                <div class="invalid-feedback">Please provide a valid username.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="edit_email" name="email" required>
                                <div class="invalid-feedback">Please provide a valid email.</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="edit_password" name="password">
                                <div class="form-text">Leave blank to keep current password</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="edit_confirm_password" name="confirm_password">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_first_name" class="form-label">First Name *</label>
                                <input type="text" class="form-control" id="edit_first_name" name="first_name" required>
                                <div class="invalid-feedback">Please provide first name.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_last_name" class="form-label">Last Name *</label>
                                <input type="text" class="form-control" id="edit_last_name" name="last_name" required>
                                <div class="invalid-feedback">Please provide last name.</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_role" class="form-label">Role *</label>
                                <select class="form-select" id="edit_role" name="role" required>
                                    <?php foreach (USER_ROLES as $role_key => $role_name): ?>
                                    <?php if ($role_key !== 'administrator' || $_SESSION['user_role'] === 'administrator'): ?>
                                    <option value="<?php echo $role_key; ?>"><?php echo $role_name; ?></option>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select a role.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_phone" class="form-label">Phone</label>
                                <input type="text" class="form-control" id="edit_phone" name="phone" placeholder="+254700000000">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active" checked>
                                <label class="form-check-label" for="edit_is_active">
                                    User is active
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-church-primary">
                        <i class="fas fa-save me-1"></i>Update User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Edit user function
function editUser(user) {
    document.getElementById('edit_user_id').value = user.id;
    document.getElementById('edit_username').value = user.username;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_first_name').value = user.first_name;
    document.getElementById('edit_last_name').value = user.last_name;
    document.getElementById('edit_role').value = user.role;
    document.getElementById('edit_phone').value = user.phone || '';
    document.getElementById('edit_is_active').checked = user.is_active == 1;
    
    // Clear password fields
    document.getElementById('edit_password').value = '';
    document.getElementById('edit_confirm_password').value = '';
    
    // Show modal
    new bootstrap.Modal(document.getElementById('editUserModal')).show();
}

// Toggle user status
function toggleUserStatus(userId, newStatus) {
    const action = newStatus ? 'activate' : 'deactivate';
    const message = `Are you sure you want to ${action} this user?`;
    
    ChurchCMS.showConfirm(message, function() {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="toggle_status">
            <input type="hidden" name="user_id" value="${userId}">
            <input type="hidden" name="status" value="${newStatus}">
        `;
        document.body.appendChild(form);
        form.submit();
    });
}

// Reset password
function resetPassword(userId, username) {
    const message = `Are you sure you want to reset the password for user "${username}"? A temporary password will be generated.`;
    
    ChurchCMS.showConfirm(message, function() {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" value="${userId}">
        `;
        document.body.appendChild(form);
        form.submit();
    });
}

// Delete user
function deleteUser(userId, username) {
    const message = `Are you sure you want to delete user "${username}"? This action cannot be undone.`;
    
    ChurchCMS.showConfirm(message, function() {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_user">
            <input type="hidden" name="user_id" value="${userId}">
        `;
        document.body.appendChild(form);
        form.submit();
    });
}

// Password validation
document.addEventListener('DOMContentLoaded', function() {
    // Add user form validation
    const addForm = document.querySelector('#addUserModal form');
    const editForm = document.querySelector('#editUserForm');
    
    function validatePasswords(form) {
        const password = form.querySelector('input[name="password"]');
        const confirmPassword = form.querySelector('input[name="confirm_password"]');
        
        if (password && confirmPassword) {
            if (password.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('Passwords do not match');
                return false;
            } else {
                confirmPassword.setCustomValidity('');
                return true;
            }
        }
        return true;
    }
    
    // Validate on input
    [addForm, editForm].forEach(form => {
        if (form) {
            const passwordInput = form.querySelector('input[name="password"]');
            const confirmInput = form.querySelector('input[name="confirm_password"]');
            
            if (passwordInput && confirmInput) {
                [passwordInput, confirmInput].forEach(input => {
                    input.addEventListener('input', function() {
                        validatePasswords(form);
                    });
                });
            }
            
            // Validate on submit
            form.addEventListener('submit', function(e) {
                if (!validatePasswords(form)) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                
                form.classList.add('was-validated');
            });
        }
    });
    
    // Phone number formatting
    document.querySelectorAll('input[name="phone"]').forEach(input => {
        input.addEventListener('input', function() {
            let value = this.value.replace(/[^\d+]/g, '');
            
            // Format Kenyan phone numbers
            if (value.startsWith('0') && value.length === 10) {
                value = '+254' + value.substr(1);
            } else if (value.startsWith('254') && value.length === 12) {
                value = '+' + value;
            }
            
            this.value = value;
        });
        
        input.addEventListener('blur', function() {
            if (this.value && !ChurchCMS.isValidPhone(this.value)) {
                this.setCustomValidity('Please enter a valid Kenyan phone number');
            } else {
                this.setCustomValidity('');
            }
        });
    });
    
    // Username validation
    document.querySelectorAll('input[name="username"]').forEach(input => {
        input.addEventListener('input', function() {
            const value = this.value.toLowerCase().replace(/[^a-z0-9_]/g, '');
            this.value = value;
            
            if (value.length < 3) {
                this.setCustomValidity('Username must be at least 3 characters long');
            } else {
                this.setCustomValidity('');
            }
        });
    });
    
    // Real-time search
    const searchInput = document.getElementById('search');
    if (searchInput) {
        const debouncedSearch = ChurchCMS.debounce(function() {
            if (searchInput.value.length >= 3 || searchInput.value.length === 0) {
                searchInput.closest('form').submit();
            }
        }, 500);
        
        searchInput.addEventListener('input', debouncedSearch);
    }
    
    // Auto-hide success messages after form submission
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('success')) {
        setTimeout(() => {
            window.history.replaceState({}, document.title, window.location.pathname + window.location.search.replace(/[?&]success=[^&]*/, '').replace(/^&/, '?'));
        }, 3000);
    }
});

// Export users function
function exportUsers() {
    ChurchCMS.showLoading('Preparing export...');
    
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'excel');
    
    window.location.href = window.location.pathname + '?' + params.toString();
    
    setTimeout(() => {
        ChurchCMS.hideLoading();
    }, 2000);
}

// Bulk actions
let selectedUsers = [];

function toggleUserSelection(userId, checkbox) {
    if (checkbox.checked) {
        selectedUsers.push(userId);
    } else {
        selectedUsers = selectedUsers.filter(id => id !== userId);
    }
    
    updateBulkActionButtons();
}

function selectAllUsers(selectAllCheckbox) {
    const checkboxes = document.querySelectorAll('input[name="selected_users[]"]');
    selectedUsers = [];
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
        if (selectAllCheckbox.checked) {
            selectedUsers.push(parseInt(checkbox.value));
        }
    });
    
    updateBulkActionButtons();
}

function updateBulkActionButtons() {
    const bulkActionButtons = document.querySelectorAll('.bulk-action-btn');
    const selectedCount = selectedUsers.length;
    
    bulkActionButtons.forEach(btn => {
        btn.disabled = selectedCount === 0;
        btn.querySelector('.selected-count').textContent = selectedCount;
    });
}

function bulkActivateUsers() {
    if (selectedUsers.length === 0) return;
    
    ChurchCMS.showConfirm(`Activate ${selectedUsers.length} selected users?`, function() {
        bulkUserAction('bulk_activate');
    });
}

function bulkDeactivateUsers() {
    if (selectedUsers.length === 0) return;
    
    ChurchCMS.showConfirm(`Deactivate ${selectedUsers.length} selected users?`, function() {
        bulkUserAction('bulk_deactivate');
    });
}

function bulkDeleteUsers() {
    if (selectedUsers.length === 0) return;
    
    ChurchCMS.showConfirm(`Delete ${selectedUsers.length} selected users? This action cannot be undone.`, function() {
        bulkUserAction('bulk_delete');
    });
}

function bulkUserAction(action) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `<input type="hidden" name="action" value="${action}">`;
    
    selectedUsers.forEach(userId => {
        form.innerHTML += `<input type="hidden" name="selected_users[]" value="${userId}">`;
    });
    
    document.body.appendChild(form);
    form.submit();
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + N for new user
    if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
        e.preventDefault();
        document.querySelector('[data-bs-target="#addUserModal"]').click();
    }
    
    // Escape to close modals
    if (e.key === 'Escape') {
        const openModals = document.querySelectorAll('.modal.show');
        openModals.forEach(modal => {
            bootstrap.Modal.getInstance(modal)?.hide();
        });
    }
});

// Activity monitoring
let activityTimer;
function resetActivityTimer() {
    clearTimeout(activityTimer);
    activityTimer = setTimeout(() => {
        if (confirm('You have been inactive for a while. Would you like to refresh the page to get the latest data?')) {
            window.location.reload();
        }
    }, 10 * 60 * 1000); // 10 minutes
}

// Reset timer on any user activity
['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(event => {
    document.addEventListener(event, resetActivityTimer, { passive: true });
});

resetActivityTimer();
</script>

<?php include_once '../../includes/footer.php'; ?><?php
/**
 * User Management
 * Deliverance Church Management System
 * 
 * Manage system users, roles, and permissions
 */

// Include configuration and functions
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Check authentication and admin role
requireLogin();
if (!hasPermission('admin') && $_SESSION['user_role'] !== 'administrator') {
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit();
}

// Page configuration
$page_title = 'User Management';
$page_icon = 'fas fa-users';
$breadcrumb = [
    ['title' => 'Administration', 'url' => BASE_URL . 'modules/admin/'],
    ['title' => 'User Management']
];

$additional_css = ['assets/css/admin.css'];
$additional_js = ['assets/js/admin.js'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        $db = Database::getInstance();
        
        if ($action === 'create_user') {
            // Validate input
            $validation = validateInput($_POST, [
                'username' => ['required', 'min:3', 'max:50'],
                'email' => ['required', 'email'],
                'password' => ['required', 'min:8'],
                'confirm_password' => ['required'],
                'role' => ['required'],
                'first_name' => ['required', 'max:50'],
                'last_name' => ['required', 'max:50'],
                'phone' => ['max:20']
            ]);
            
            if (!$validation['valid']) {
                setFlashMessage('error', implode(', ', $validation['errors']));
            } elseif ($_POST['password'] !== $_POST['confirm_password']) {
                setFlashMessage('error', 'Passwords do not match');
            } else {
                // Check for duplicate username/email
                $existingUser = $db->executeQuery(
                    "SELECT id FROM users WHERE username = ? OR email = ?",
                    [$validation['data']['username'], $validation['data']['email']]
                )->fetch();
                
                if ($existingUser) {
                    setFlashMessage('error', 'Username or email already exists');
                } else {
                    // Create user
                    $userData = [
                        'username' => $validation['data']['username'],
                        'email' => $validation['data']['email'],
                        'password' => password_hash($validation['data']['password'], PASSWORD_BCRYPT),
                        'role' => $validation['data']['role'],
                        'first_name' => $validation['data']['first_name'],
                        'last_name' => $validation['data']['last_name'],
                        'phone' => $validation['data']['phone']
                    ];
                    
                    $db->executeQuery(
                        "INSERT INTO users (username, email, password, role, first_name, last_name, phone) VALUES (?, ?, ?, ?, ?, ?, ?)",
                        array_values($userData)
                    );
                    
                    setFlashMessage('success', 'User created successfully');
                }
            }
        } elseif ($action === 'update_user') {
            // Validate input
            $validation = validateInput($_POST, [
                'user_id' => ['required'],
                'username' => ['required', 'min:3', 'max:50'],
                'email' => ['required', 'email'],
                'role' => ['required'],
                'first_name' => ['required', 'max:50'],
                'last_name' => ['required', 'max:50'],
                'phone' => ['max:20']
            ]);
            if (!$validation['valid']) {
                setFlashMessage('error', implode(', ', $validation['errors']));
            } else {
                $userId = (int)$validation['data']['user_id'];
                
                // Check if user exists
                $existingUser = $db->executeQuery(
                    "SELECT id FROM users WHERE id = ?",
                    [$userId]
                )->fetch();
                
                if (!$existingUser) {
                    setFlashMessage('error', 'User not found');
                } else {
                    // Check for duplicate username/email
                    $duplicateUser = $db->executeQuery(
                        "SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?",
                        [$validation['data']['username'], $validation['data']['email'], $userId]
                    )->fetch();
                    
                    if ($duplicateUser) {
                        setFlashMessage('error', 'Username or email already exists');
                    } else {
                        // Update user
                        $updateData = [
                            'username' => $validation['data']['username'],
                            'email' => $validation['data']['email'],
                            'role' => $validation['data']['role'],
                            'first_name' => $validation['data']['first_name'],
                            'last_name' => $validation['data']['last_name'],
                            'phone' => $validation['data']['phone'],
                            'is_active' => isset($_POST['is_active']) ? 1 : 0
                        ];
                        
                        // If password is provided, hash and update it
                        if (!empty($_POST['password'])) {
                            if (strlen($_POST['password']) < 8) {
                                setFlashMessage('error', 'Password must be at least 8 characters long');
                                header('Location: ' . $_SERVER['PHP_SELF']);
                                exit();
                            }
                            if ($_POST['password'] !== $_POST['confirm_password']) {
                                setFlashMessage('error', 'Passwords do not match');
                                header('Location: ' . $_SERVER['PHP_SELF']);
                                exit();
                            }
                            $updateData['password'] = password_hash($_POST['password'], PASSWORD_BCRYPT);
                        }
                        
                        $setClause = implode(", ", array_map(fn($key) => "$key = ?", array_keys($updateData)));
                        $db->executeQuery(
                            "UPDATE users SET $setClause WHERE id = ?",
                            array_merge(array_values($updateData), [$userId])
                        );
                        setFlashMessage('success', 'User updated successfully');
                    }
                }
            }
        } elseif ($action === 'toggle_status') {    
            $userId = (int)($_POST['user_id'] ?? 0);
            $newStatus = isset($_POST['status']) ? (int)$_POST['status'] : 0;
            
            if ($userId > 0) {
                $db->executeQuery(
                    "UPDATE users SET is_active = ? WHERE id = ?",
                    [$newStatus, $userId]
                );
                setFlashMessage('success', 'User status updated successfully');
            } else {
                setFlashMessage('error', 'Invalid user ID');
            }
        } elseif ($action === 'reset_password') {
            $userId = (int)($_POST['user_id'] ?? 0);
            
            if ($userId > 0) {
                // Generate temporary password
                $tempPassword = generateRandomPassword(12);
                $hashedPassword = password_hash($tempPassword, PASSWORD_BCRYPT);
                
                $db->executeQuery(
                    "UPDATE users SET password = ? WHERE id = ?",
                    [$hashedPassword, $userId]
                );
                
                // Send email with temporary password
                $user = $db->executeQuery(
                    "SELECT email, username FROM users WHERE id = ?",
                    [$userId]
                )->fetch();
                
                if ($user) {
                    sendEmail($user['email'], 'Password Reset', "Your temporary password is: $tempPassword");
                    setFlashMessage('success', 'Password reset successfully. A temporary password has been sent to the user\'s email.');
                } else {
                    setFlashMessage('error', 'User not found');
                }
            } else {
                setFlashMessage('error', 'Invalid user ID');
            }
        } elseif ($action === 'delete_user') {
            $userId = (int)($_POST['user_id'] ?? 0);
            
            if ($userId > 0) {
                // Prevent deleting self
                if ($userId === $_SESSION['user_id']) {
                    setFlashMessage('error', 'You cannot delete your own account');
                } else {
                    $db->executeQuery(
                        "DELETE FROM users WHERE id = ?",
                        [$userId]
                    );
                    setFlashMessage('success', 'User deleted successfully');
                }
            } else {
                setFlashMessage('error', 'Invalid user ID');
            }
        } elseif (in_array($action, ['bulk_activate', 'bulk_deactivate', 'bulk_delete'])) {
            $selectedUsers = $_POST['selected_users'] ?? [];
            $selectedUsers = array_map('intval', $selectedUsers);
            
            if (empty($selectedUsers)) {
                setFlashMessage('error', 'No users selected for bulk action');
            } else {
                // Prevent self-deletion or deactivation
                if (in_array($_SESSION['user_id'], $selectedUsers) && $action === 'bulk_delete') {
                    setFlashMessage('error', 'You cannot delete your own account');
                } else {
                    if ($action === 'bulk_activate') {
                        $db->executeQuery(
                            "UPDATE users SET is_active = 1 WHERE id IN (" . implode(',', array_fill(0, count($selectedUsers), '?')) . ")",
                            $selectedUsers
                        );
                        setFlashMessage('success', count($selectedUsers) . ' users activated successfully');
                    } elseif ($action === 'bulk_deactivate') {
                        // Prevent deactivating self
                        $usersToDeactivate = array_filter($selectedUsers, fn($id) => $id !== $_SESSION['user_id']);
                        if (!empty($usersToDeactivate)) {
                            $db->executeQuery(
                                "UPDATE users SET is_active = 0 WHERE id IN (" . implode(',', array_fill(0, count($usersToDeactivate), '?')) . ")",
                                $usersToDeactivate
                            );
                            setFlashMessage('success', count($usersToDeactivate) . ' users deactivated successfully');
                        } else {
                            setFlashMessage('error', 'You cannot deactivate your own account');
                        }
                    } elseif ($action === 'bulk_delete') {
                        $usersToDelete = array_filter($selectedUsers, fn($id) => $id !== $_SESSION['user_id']);
                        if (!empty($usersToDelete)) {
                            $db->executeQuery(
                                "DELETE FROM users WHERE id IN (" . implode(',', array_fill(0, count($usersToDelete), '?')) . ")",
                                $usersToDelete
                            );
                            setFlashMessage('success', count($usersToDelete) . ' users deleted successfully');
                        } else {
                            setFlashMessage('error', 'You cannot delete your own account');
                        }
                    }
                }
            }
        }
    } catch (Exception $e) {
        setFlashMessage('error', 'An error occurred: ' . $e->getMessage());
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Fetch users with pagination and search
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;

$offset = ($page - 1) * $perPage;

// Fetch users from the database
$query = "SELECT * FROM users WHERE 1";
$params = [];

// Add search filter
if ($search !== '') {
    $query .= " AND (username LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Count total users
$totalUsers = $db->fetchColumn("SELECT COUNT(*) FROM users WHERE 1" . ($search !== '' ? " AND (username LIKE ? OR email LIKE ?)" : ""), $params);

// Fetch users with pagination
$query .= " LIMIT $offset, $perPage";
$users = $db->fetchAll($query, $params);
$totalPages = ceil($totalUsers / $perPage);
?>
<?php include_once '../../includes/header.php'; ?>