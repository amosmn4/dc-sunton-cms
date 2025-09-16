<?php
/**
 * Edit Member
 * Deliverance Church Management System
 * 
 * Edit existing member information
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

// Get member ID
$member_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($member_id <= 0) {
    setFlashMessage('error', 'Invalid member ID.');
    header('Location: ' . BASE_URL . 'modules/members/');
    exit();
}

try {
    $db = Database::getInstance();
    
    // Get existing member data
    $member = $db->executeQuery(
        "SELECT * FROM members WHERE id = ?",
        [$member_id]
    )->fetch();
    
    if (!$member) {
        setFlashMessage('error', 'Member not found.');
        header('Location: ' . BASE_URL . 'modules/members/');
        exit();
    }
    
    // Get member departments
    $member_departments = $db->executeQuery(
        "SELECT md.department_id, md.role, d.name as department_name
         FROM member_departments md
         JOIN departments d ON md.department_id = d.id
         WHERE md.member_id = ? AND md.is_active = 1",
        [$member_id]
    )->fetchAll();
    
} catch (Exception $e) {
    error_log("Edit member error: " . $e->getMessage());
    setFlashMessage('error', 'Error loading member information.');
    header('Location: ' . BASE_URL . 'modules/members/');
    exit();
}

// Initialize form data with existing member data
$form_data = $member;
$form_data['departments'] = [];
foreach ($member_departments as $dept) {
    $form_data['departments'][] = [
        'department_id' => $dept['department_id'],
        'role' => $dept['role'],
        'department_name' => $dept['department_name']
    ];
}

$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $update_data = [];
    $allowed_fields = [
        'first_name', 'last_name', 'middle_name', 'date_of_birth', 'gender',
        'marital_status', 'phone', 'email', 'address', 'emergency_contact_name',
        'emergency_contact_phone', 'emergency_contact_relationship', 'baptism_date',
        'confirmation_date', 'membership_status', 'occupation', 'education_level',
        'skills', 'spiritual_gifts', 'leadership_roles', 'notes'
    ];
    
    foreach ($allowed_fields as $field) {
        if (isset($_POST[$field])) {
            $update_data[$field] = sanitizeInput($_POST[$field]);
            $form_data[$field] = $update_data[$field];
        }
    }
    
    $form_data['departments'] = $_POST['departments'] ?? [];
    
    // Validation rules
    $validation_rules = [
        'first_name' => ['required', 'max:50'],
        'last_name' => ['required', 'max:50'],
        'gender' => ['required'],
        'phone' => ['phone'],
        'email' => ['email']
    ];
    
    $validation = validateInput($_POST, $validation_rules);
    
    if (!$validation['valid']) {
        $errors = $validation['errors'];
    }
    
    // Additional validation
    if (!empty($update_data['date_of_birth'])) {
        $dob = new DateTime($update_data['date_of_birth']);
        $today = new DateTime();
        if ($dob > $today) {
            $errors['date_of_birth'] = 'Date of birth cannot be in the future';
        }
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Handle photo upload if provided
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $upload_result = handleFileUpload(
                    $_FILES['photo'],
                    ASSETS_PATH . 'uploads/members/',
                    ALLOWED_IMAGE_TYPES,
                    MAX_PHOTO_SIZE
                );
                
                if ($upload_result['success']) {
                    // Delete old photo if exists
                    if (!empty($member['photo']) && file_exists(ROOT_PATH . $member['photo'])) {
                        unlink(ROOT_PATH . $member['photo']);
                    }
                    $update_data['photo'] = 'assets/uploads/members/' . $upload_result['filename'];
                } else {
                    $errors['photo'] = $upload_result['message'];
                }
            }
            
            if (empty($errors)) {
                // Update member record
                $result = updateRecord('members', $update_data, ['id' => $member_id]);
                
                if ($result) {
                    // Update department assignments
                    if (isset($_POST['departments'])) {
                        // Deactivate existing assignments
                        $db->executeQuery(
                            "UPDATE member_departments SET is_active = 0 WHERE member_id = ?",
                            [$member_id]
                        );
                        
                        // Add new assignments
                        foreach ($_POST['departments'] as $dept_data) {
                            if (!empty($dept_data['department_id'])) {
                                $dept_assignment = [
                                    'member_id' => $member_id,
                                    'department_id' => $dept_data['department_id'],
                                    'role' => $dept_data['role'] ?? 'member',
                                    'assigned_date' => date('Y-m-d'),
                                    'is_active' => 1
                                ];
                                insertRecord('member_departments', $dept_assignment);
                            }
                        }
                    }
                    
                    $db->commit();
                    
                    // Log activity
                    logActivity(
                        'Member updated: ' . $update_data['first_name'] . ' ' . $update_data['last_name'],
                        'members',
                        $member_id,
                        $member,
                        $update_data
                    );
                    
                    setFlashMessage('success', 'Member updated successfully!');
                    header('Location: ' . BASE_URL . 'modules/members/view.php?id=' . $member_id);
                    exit();
                } else {
                    throw new Exception('Failed to update member record');
                }
            }
            
        } catch (Exception $e) {
            $db->rollback();
            error_log("Update member error: " . $e->getMessage());
            $errors['general'] = 'An error occurred while updating the member. Please try again.';
        }
    }
}

// Get departments for form
try {
    $departments = $db->executeQuery(
        "SELECT id, name, department_type FROM departments WHERE is_active = 1 ORDER BY name"
    )->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching departments: " . $e->getMessage());
    $departments = [];
}

// Set page variables
$page_title = 'Edit Member: ' . $member['first_name'] . ' ' . $member['last_name'];
$page_icon = 'fas fa-user-edit';
$page_description = 'Edit member information and details';

$breadcrumb = [
    ['title' => 'Members', 'url' => BASE_URL . 'modules/members/'],
    ['title' => $member['first_name'] . ' ' . $member['last_name'], 'url' => BASE_URL . 'modules/members/view.php?id=' . $member_id],
    ['title' => 'Edit']
];

// Include header
include '../../includes/header.php';
?>

<!-- Edit Member Form -->
<div class="row">
    <div class="col-12">
        <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
            <!-- Personal Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-user me-2"></i>Personal Information
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (isset($errors['general'])): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo htmlspecialchars($errors['general']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="row">
                        <!-- Photo Upload -->
                        <div class="col-md-3 mb-4">
                            <label class="form-label">Member Photo</label>
                            <div class="text-center mb-3">
                                <?php if (!empty($member['photo'])): ?>
                                    <img src="<?php echo BASE_URL . $member['photo']; ?>" 
                                         alt="Current Photo" 
                                         class="img-fluid rounded-circle"
                                         style="width: 150px; height: 150px; object-fit: cover;"
                                         id="current-photo">
                                <?php else: ?>
                                    <div class="bg-church-blue text-white rounded-circle d-inline-flex align-items-center justify-content-center"
                                         style="width: 150px; height: 150px;" id="current-photo">
                                        <i class="fas fa-user fa-4x"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="file-upload-wrapper">
                                <input type="file" name="photo" id="photo" accept="image/*" class="d-none">
                                <button type="button" class="btn btn-outline-primary w-100" 
                                        onclick="document.getElementById('photo').click()">
                                    <i class="fas fa-camera me-1"></i>Change Photo
                                </button>
                            </div>
                            <?php if (isset($errors['photo'])): ?>
                                <div class="text-danger small mt-1"><?php echo $errors['photo']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Basic Info -->
                        <div class="col-md-9">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="first_name" class="form-label">First Name *</label>
                                    <input type="text" class="form-control <?php echo isset($errors['first_name']) ? 'is-invalid' : ''; ?>" 
                                           id="first_name" name="first_name" 
                                           value="<?php echo htmlspecialchars($form_data['first_name']); ?>" 
                                           required maxlength="50">
                                    <?php if (isset($errors['first_name'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['first_name']; ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="middle_name" class="form-label">Middle Name</label>
                                    <input type="text" class="form-control" id="middle_name" name="middle_name" 
                                           value="<?php echo htmlspecialchars($form_data['middle_name']); ?>" 
                                           maxlength="50">
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="last_name" class="form-label">Last Name *</label>
                                    <input type="text" class="form-control <?php echo isset($errors['last_name']) ? 'is-invalid' : ''; ?>" 
                                           id="last_name" name="last_name" 
                                           value="<?php echo htmlspecialchars($form_data['last_name']); ?>" 
                                           required maxlength="50">
                                    <?php if (isset($errors['last_name'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['last_name']; ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-md-3 mb-3">
                                    <label for="date_of_birth" class="form-label">Date of Birth</label>
                                    <input type="date" class="form-control <?php echo isset($errors['date_of_birth']) ? 'is-invalid' : ''; ?>" 
                                           id="date_of_birth" name="date_of_birth" 
                                           value="<?php echo htmlspecialchars($form_data['date_of_birth']); ?>"
                                           max="<?php echo date('Y-m-d'); ?>">
                                    <?php if (isset($errors['date_of_birth'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['date_of_birth']; ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-md-3 mb-3">
                                    <label for="gender" class="form-label">Gender *</label>
                                    <select class="form-select <?php echo isset($errors['gender']) ? 'is-invalid' : ''; ?>" 
                                            id="gender" name="gender" required>
                                        <option value="">Select Gender</option>
                                        <?php foreach (GENDER_OPTIONS as $value => $label): ?>
                                            <option value="<?php echo $value; ?>" 
                                                    <?php echo ($form_data['gender'] === $value) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($errors['gender'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['gender']; ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-md-3 mb-3">
                                    <label for="marital_status" class="form-label">Marital Status</label>
                                    <select class="form-select" id="marital_status" name="marital_status">
                                        <?php foreach (MARITAL_STATUS_OPTIONS as $value => $label): ?>
                                            <option value="<?php echo $value; ?>" 
                                                    <?php echo ($form_data['marital_status'] === $value) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-3 mb-3">
                                    <label for="membership_status" class="form-label">Membership Status</label>
                                    <select class="form-select" id="membership_status" name="membership_status">
                                        <?php foreach (MEMBER_STATUS_OPTIONS as $value => $label): ?>
                                            <option value="<?php echo $value; ?>" 
                                                    <?php echo ($form_data['membership_status'] === $value) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Member Number (Read-only) -->
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Member Number</label>
                            <input type="text" class="form-control" 
                                   value="<?php echo htmlspecialchars($member['member_number']); ?>" 
                                   readonly>
                            <small class="text-muted">Member number cannot be changed</small>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Join Date</label>
                            <input type="text" class="form-control" 
                                   value="<?php echo formatDisplayDate($member['join_date']); ?>" 
                                   readonly>
                            <small class="text-muted">Join date cannot be changed</small>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="baptism_date" class="form-label">Baptism Date</label>
                            <input type="date" class="form-control" id="baptism_date" name="baptism_date" 
                                   value="<?php echo htmlspecialchars($form_data['baptism_date']); ?>">
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="confirmation_date" class="form-label">Confirmation Date</label>
                            <input type="date" class="form-control" id="confirmation_date" name="confirmation_date" 
                                   value="<?php echo htmlspecialchars($form_data['confirmation_date']); ?>">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Contact Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-phone me-2"></i>Contact Information
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control <?php echo isset($errors['phone']) ? 'is-invalid' : ''; ?>" 
                                   id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($form_data['phone']); ?>"
                                   placeholder="+254700000000">
                            <?php if (isset($errors['phone'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['phone']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                                   id="email" name="email" 
                                   value="<?php echo htmlspecialchars($form_data['email']); ?>">
                            <?php if (isset($errors['email'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="occupation" class="form-label">Occupation</label>
                            <input type="text" class="form-control" id="occupation" name="occupation" 
                                   value="<?php echo htmlspecialchars($form_data['occupation']); ?>">
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="2" 
                                      placeholder="Enter full address"><?php echo htmlspecialchars($form_data['address']); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Emergency Contact -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-user-shield me-2"></i>Emergency Contact
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="emergency_contact_name" class="form-label">Contact Name</label>
                            <input type="text" class="form-control" id="emergency_contact_name" name="emergency_contact_name" 
                                   value="<?php echo htmlspecialchars($form_data['emergency_contact_name']); ?>">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="emergency_contact_phone" class="form-label">Contact Phone</label>
                            <input type="tel" class="form-control" id="emergency_contact_phone" name="emergency_contact_phone" 
                                   value="<?php echo htmlspecialchars($form_data['emergency_contact_phone']); ?>"
                                   placeholder="+254700000000">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="emergency_contact_relationship" class="form-label">Relationship</label>
                            <input type="text" class="form-control" id="emergency_contact_relationship" name="emergency_contact_relationship" 
                                   value="<?php echo htmlspecialchars($form_data['emergency_contact_relationship']); ?>"
                                   placeholder="e.g., Spouse, Parent, Sibling">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Department Assignments -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="fas fa-users-cog me-2"></i>Department Assignments
                    </h6>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addDepartmentRow()">
                        <i class="fas fa-plus me-1"></i>Add Department
                    </button>
                </div>
                <div class="card-body">
                    <div id="department-assignments">
                        <!-- Existing departments will be loaded here -->
                    </div>
                </div>
            </div>
            
            <!-- Additional Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-star me-2"></i>Skills & Additional Information
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="education_level" class="form-label">Education Level</label>
                            <select class="form-select" id="education_level" name="education_level">
                                <option value="">Select Education Level</option>
                                <?php foreach (EDUCATION_LEVELS as $value => $label): ?>
                                    <option value="<?php echo $value; ?>" 
                                            <?php echo ($form_data['education_level'] === $value) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="leadership_roles" class="form-label">Leadership Roles</label>
                            <input type="text" class="form-control" id="leadership_roles" name="leadership_roles" 
                                   value="<?php echo htmlspecialchars($form_data['leadership_roles']); ?>"
                                   placeholder="Current or past leadership positions">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="skills" class="form-label">Skills & Talents</label>
                            <textarea class="form-control" id="skills" name="skills" rows="3" 
                                      placeholder="List member's skills and talents"><?php echo htmlspecialchars($form_data['skills']); ?></textarea>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="spiritual_gifts" class="form-label">Spiritual Gifts</label>
                            <textarea class="form-control" id="spiritual_gifts" name="spiritual_gifts" rows="3" 
                                      placeholder="List spiritual gifts identified"><?php echo htmlspecialchars($form_data['spiritual_gifts']); ?></textarea>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" 
                                      placeholder="Additional notes about the member"><?php echo htmlspecialchars($form_data['notes']); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <a href="<?php echo BASE_URL; ?>modules/members/view.php?id=<?php echo $member_id; ?>" 
                               class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i>Back to Profile
                            </a>
                        </div>
                        
                        <div>
                            <button type="button" class="btn btn-outline-danger me-2" onclick="confirmDelete()">
                                <i class="fas fa-trash me-1"></i>Delete Member
                            </button>
                            <button type="submit" class="btn btn-church-primary">
                                <i class="fas fa-save me-1"></i>Update Member
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle me-2"></i>Confirm Delete
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this member?</p>
                <p class="text-danger"><strong>This action cannot be undone.</strong></p>
                <p>Member: <strong><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></strong></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="deleteMember()">
                    <i class="fas fa-trash me-1"></i>Delete Member
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let departmentRowIndex = 0;
const departments = <?php echo json_encode($departments); ?>;
const existingDepartments = <?php echo json_encode($form_data['departments']); ?>;

document.addEventListener('DOMContentLoaded', function() {
    // Photo preview
    document.getElementById('photo').addEventListener('change', function(e) {
        const file = e.target.files[0];
        const currentPhoto = document.getElementById('current-photo');
        
        if (file) {
            if (file.size > <?php echo MAX_PHOTO_SIZE; ?>) {
                alert('File size must be less than 2MB');
                this.value = '';
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                currentPhoto.outerHTML = `<img src="${e.target.result}" alt="New Photo" class="img-fluid rounded-circle" style="width: 150px; height: 150px; object-fit: cover;" id="current-photo">`;
            };
            reader.readAsDataURL(file);
        }
    });
    
    // Load existing departments
    existingDepartments.forEach(dept => {
        addDepartmentRow(dept);
    });
    
    // If no departments, add one empty row
    if (existingDepartments.length === 0) {
        addDepartmentRow();
    }
    
    // Form validation
    const form = document.querySelector('.needs-validation');
    form.addEventListener('submit', function(e) {
        if (!form.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        }
        form.classList.add('was-validated');
    });
});

function addDepartmentRow(existingDept = null) {
    const container = document.getElementById('department-assignments');
    const row = document.createElement('div');
    row.className = 'row mb-3 department-row';
    row.id = `dept-row-${departmentRowIndex}`;
    
    let departmentOptions = '<option value="">Select Department</option>';
    departments.forEach(dept => {
        const selected = existingDept && dept.id == existingDept.department_id ? 'selected' : '';
        departmentOptions += `<option value="${dept.id}" ${selected}>${dept.name} (${dept.department_type})</option>`;
    });
    
    const roleValue = existingDept ? existingDept.role : 'member';
    
    row.innerHTML = `
        <div class="col-md-6">
            <select class="form-select" name="departments[${departmentRowIndex}][department_id]">
                ${departmentOptions}
            </select>
        </div>
        <div class="col-md-4">
            <input type="text" class="form-control" name="departments[${departmentRowIndex}][role]" 
                   placeholder="Role (e.g., Member, Leader)" value="${roleValue}">
        </div>
        <div class="col-md-2">
            <button type="button" class="btn btn-outline-danger w-100" onclick="removeDepartmentRow(${departmentRowIndex})">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    `;
    
    container.appendChild(row);
    departmentRowIndex++;
}

function removeDepartmentRow(index) {
    const row = document.getElementById(`dept-row-${index}`);
    if (row) {
        row.remove();
    }
    
    // Ensure at least one row exists
    const container = document.getElementById('department-assignments');
    if (container.children.length === 0) {
        addDepartmentRow();
    }
}

function confirmDelete() {
    const modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
    modal.show();
}

function deleteMember() {
    const memberId = <?php echo $member_id; ?>;
    
    fetch(`${BASE_URL}api/members.php?action=delete&id=${memberId}`, {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            ChurchCMS.showToast('Member deleted successfully', 'success');
            setTimeout(() => {
                window.location.href = `${BASE_URL}modules/members/`;
            }, 1000);
        } else {
            ChurchCMS.showToast(data.message || 'Error deleting member', 'error');
        }
    })
    .catch(error => {
        console.error('Error deleting member:', error);
        ChurchCMS.showToast('Error deleting member', 'error');
    });
    
    bootstrap.Modal.getInstance(document.getElementById('deleteConfirmModal')).hide();
}

// Phone number formatting
document.querySelectorAll('input[type="tel"]').forEach(input => {
    input.addEventListener('blur', function() {
        if (this.value) {
            this.value = ChurchCMS.formatPhone(this.value);
        }
    });
});

// Show confirmation before leaving if form is dirty
let formChanged = false;
document.addEventListener('input', function(e) {
    if (e.target.form && e.target.name) {
        formChanged = true;
    }
});

window.addEventListener('beforeunload', function(e) {
    if (formChanged) {
        e.preventDefault();
        e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
        return e.returnValue;
    }
});

// Clear flag on form submit
document.querySelector('form').addEventListener('submit', function() {
    formChanged = false;
});
</script>

<?php
// Include footer
include '../../includes/footer.php';
?>