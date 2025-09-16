<?php
/**
 * Add New Member
 * Deliverance Church Management System
 * 
 * Form to add a new church member
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
$page_title = 'Add New Member';
$page_icon = 'fas fa-user-plus';
$page_description = 'Add a new member to the church database';

$breadcrumb = [
    ['title' => 'Members', 'url' => BASE_URL . 'modules/members/'],
    ['title' => 'Add Member']
];

// Initialize form data
$form_data = [
    'first_name' => '',
    'last_name' => '',
    'middle_name' => '',
    'date_of_birth' => '',
    'gender' => '',
    'marital_status' => 'single',
    'phone' => '',
    'email' => '',
    'address' => '',
    'emergency_contact_name' => '',
    'emergency_contact_phone' => '',
    'emergency_contact_relationship' => '',
    'join_date' => date('Y-m-d'),
    'baptism_date' => '',
    'confirmation_date' => '',
    'membership_status' => 'active',
    'occupation' => '',
    'education_level' => '',
    'skills' => '',
    'spiritual_gifts' => '',
    'leadership_roles' => '',
    'notes' => '',
    'departments' => []
];

$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    foreach ($form_data as $key => $default) {
        if ($key === 'departments') {
            $form_data[$key] = $_POST[$key] ?? [];
        } else {
            $form_data[$key] = sanitizeInput($_POST[$key] ?? '');
        }
    }
    
    // Validation rules
    $validation_rules = [
        'first_name' => ['required', 'max:50'],
        'last_name' => ['required', 'max:50'],
        'gender' => ['required'],
        'join_date' => ['required', 'date'],
        'phone' => ['phone'],
        'email' => ['email']
    ];
    
    $validation = validateInput($_POST, $validation_rules);
    
    if (!$validation['valid']) {
        $errors = $validation['errors'];
    }
    
    // Check for duplicate member number if provided
    if (!empty($form_data['member_number'])) {
        $existing = getRecord('members', 'member_number', $form_data['member_number']);
        if ($existing) {
            $errors['member_number'] = 'Member number already exists';
        }
    }
    
    // Additional business logic validation
    if (!empty($form_data['date_of_birth'])) {
        $dob = new DateTime($form_data['date_of_birth']);
        $today = new DateTime();
        if ($dob > $today) {
            $errors['date_of_birth'] = 'Date of birth cannot be in the future';
        }
    }
    
    if (!empty($form_data['baptism_date']) && !empty($form_data['join_date'])) {
        if (strtotime($form_data['baptism_date']) < strtotime($form_data['join_date'])) {
            $errors['baptism_date'] = 'Baptism date cannot be before join date';
        }
    }
    
    // If validation passes, save the member
    if (empty($errors)) {
        try {
            $db = Database::getInstance();
            $db->beginTransaction();
            
            // Generate member number if not provided
            if (empty($form_data['member_number'])) {
                $year = date('y');
                $stmt = $db->executeQuery(
                    "SELECT MAX(CAST(SUBSTRING(member_number, 4) AS UNSIGNED)) as max_num 
                     FROM members 
                     WHERE member_number LIKE ?",
                    ["MEM{$year}%"]
                );
                $max_num = $stmt->fetchColumn() ?: 0;
                $form_data['member_number'] = sprintf("MEM%s%04d", $year, $max_num + 1);
            }
            
            // Handle photo upload
            $photo_path = '';
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $upload_result = handleFileUpload(
                    $_FILES['photo'],
                    ASSETS_PATH . 'uploads/members/',
                    ALLOWED_IMAGE_TYPES,
                    MAX_PHOTO_SIZE
                );
                
                if ($upload_result['success']) {
                    $photo_path = 'assets/uploads/members/' . $upload_result['filename'];
                } else {
                    $errors['photo'] = $upload_result['message'];
                }
            }
            
            if (empty($errors)) {
                // Prepare member data
                $member_data = [
                    'member_number' => $form_data['member_number'],
                    'first_name' => $form_data['first_name'],
                    'last_name' => $form_data['last_name'],
                    'middle_name' => $form_data['middle_name'],
                    'photo' => $photo_path,
                    'date_of_birth' => !empty($form_data['date_of_birth']) ? $form_data['date_of_birth'] : null,
                    'gender' => $form_data['gender'],
                    'marital_status' => $form_data['marital_status'],
                    'phone' => $form_data['phone'],
                    'email' => $form_data['email'],
                    'address' => $form_data['address'],
                    'emergency_contact_name' => $form_data['emergency_contact_name'],
                    'emergency_contact_phone' => $form_data['emergency_contact_phone'],
                    'emergency_contact_relationship' => $form_data['emergency_contact_relationship'],
                    'join_date' => $form_data['join_date'],
                    'baptism_date' => !empty($form_data['baptism_date']) ? $form_data['baptism_date'] : null,
                    'confirmation_date' => !empty($form_data['confirmation_date']) ? $form_data['confirmation_date'] : null,
                    'membership_status' => $form_data['membership_status'],
                    'occupation' => $form_data['occupation'],
                    'education_level' => $form_data['education_level'],
                    'skills' => $form_data['skills'],
                    'spiritual_gifts' => $form_data['spiritual_gifts'],
                    'leadership_roles' => $form_data['leadership_roles'],
                    'notes' => $form_data['notes'],
                    'created_by' => $_SESSION['user_id']
                ];
                
                // Insert member
                $member_id = insertRecord('members', $member_data);
                
                if ($member_id) {
                    // Add department assignments
                    if (!empty($form_data['departments'])) {
                        foreach ($form_data['departments'] as $dept_data) {
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
                        'New member added: ' . $form_data['first_name'] . ' ' . $form_data['last_name'],
                        'members',
                        $member_id,
                        null,
                        $member_data
                    );
                    
                    setFlashMessage('success', 'Member added successfully!');
                    header('Location: ' . BASE_URL . 'modules/members/view.php?id=' . $member_id);
                    exit();
                } else {
                    throw new Exception('Failed to create member record');
                }
            }
            
        } catch (Exception $e) {
            $db->rollback();
            error_log("Add member error: " . $e->getMessage());
            $errors['general'] = 'An error occurred while saving the member. Please try again.';
        }
    }
}

// Get departments for form
try {
    $db = Database::getInstance();
    $departments = $db->executeQuery(
        "SELECT id, name, department_type FROM departments WHERE is_active = 1 ORDER BY name"
    )->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching departments: " . $e->getMessage());
    $departments = [];
}

// Include header
include '../../includes/header.php';
?>

<!-- Add Member Form -->
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
                            <div class="file-upload-wrapper">
                                <input type="file" name="photo" id="photo" accept="image/*" class="d-none">
                                <div class="file-upload-display text-center" onclick="document.getElementById('photo').click()">
                                    <div id="photo-preview" class="mb-2">
                                        <i class="fas fa-camera fa-3x text-muted"></i>
                                    </div>
                                    <p class="mb-0 small text-muted">
                                        Click to upload photo<br>
                                        (Max 2MB, JPG/PNG)
                                    </p>
                                </div>
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
                                    <label for="join_date" class="form-label">Join Date *</label>
                                    <input type="date" class="form-control <?php echo isset($errors['join_date']) ? 'is-invalid' : ''; ?>" 
                                           id="join_date" name="join_date" 
                                           value="<?php echo htmlspecialchars($form_data['join_date']); ?>" 
                                           required max="<?php echo date('Y-m-d'); ?>">
                                    <?php if (isset($errors['join_date'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['join_date']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
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
            
            <!-- Church Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-church me-2"></i>Church Information
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="baptism_date" class="form-label">Baptism Date</label>
                            <input type="date" class="form-control <?php echo isset($errors['baptism_date']) ? 'is-invalid' : ''; ?>" 
                                   id="baptism_date" name="baptism_date" 
                                   value="<?php echo htmlspecialchars($form_data['baptism_date']); ?>">
                            <?php if (isset($errors['baptism_date'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['baptism_date']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="confirmation_date" class="form-label">Confirmation Date</label>
                            <input type="date" class="form-control" id="confirmation_date" name="confirmation_date" 
                                   value="<?php echo htmlspecialchars($form_data['confirmation_date']); ?>">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="occupation" class="form-label">Occupation</label>
                            <input type="text" class="form-control" id="occupation" name="occupation" 
                                   value="<?php echo htmlspecialchars($form_data['occupation']); ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="skills" class="form-label">Skills & Talents</label>
                            <textarea class="form-control" id="skills" name="skills" rows="2" 
                                      placeholder="List member's skills and talents"><?php echo htmlspecialchars($form_data['skills']); ?></textarea>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="spiritual_gifts" class="form-label">Spiritual Gifts</label>
                            <textarea class="form-control" id="spiritual_gifts" name="spiritual_gifts" rows="2" 
                                      placeholder="List spiritual gifts identified"><?php echo htmlspecialchars($form_data['spiritual_gifts']); ?></textarea>
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
                        <!-- Department rows will be added here -->
                    </div>
                    <div class="text-muted small">
                        <i class="fas fa-info-circle me-1"></i>
                        Assign member to church departments and ministries.
                    </div>
                </div>
            </div>
            
            <!-- Additional Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-sticky-note me-2"></i>Additional Information
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
                        <a href="<?php echo BASE_URL; ?>modules/members/" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Cancel
                        </a>
                        
                        <div>
                            <button type="button" class="btn btn-outline-primary me-2" onclick="saveDraft()">
                                <i class="fas fa-save me-1"></i>Save Draft
                            </button>
                            <button type="submit" class="btn btn-church-primary">
                                <i class="fas fa-user-plus me-1"></i>Add Member
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
let departmentRowIndex = 0;
const departments = <?php echo json_encode($departments); ?>;

document.addEventListener('DOMContentLoaded', function() {
    // Photo preview
    document.getElementById('photo').addEventListener('change', function(e) {
        const file = e.target.files[0];
        const preview = document.getElementById('photo-preview');
        
        if (file) {
            if (file.size > <?php echo MAX_PHOTO_SIZE; ?>) {
                alert('File size must be less than 2MB');
                this.value = '';
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.innerHTML = `<img src="${e.target.result}" class="img-fluid rounded" style="max-height: 150px;">`;
            };
            reader.readAsDataURL(file);
        }
    });
    
    // Phone number formatting
    document.querySelectorAll('input[type="tel"]').forEach(input => {
        input.addEventListener('blur', function() {
            if (this.value) {
                this.value = ChurchCMS.formatPhone(this.value);
            }
        });
    });
    
    // Auto-generate member number based on name
    const firstNameInput = document.getElementById('first_name');
    const lastNameInput = document.getElementById('last_name');
    
    function updateMemberNumber() {
        // This is just for preview - actual generation happens server-side
        const firstName = firstNameInput.value;
        const lastName = lastNameInput.value;
        
        if (firstName && lastName) {
            // Show preview of what the member number will be
            console.log('Member number will be auto-generated');
        }
    }
    
    firstNameInput.addEventListener('blur', updateMemberNumber);
    lastNameInput.addEventListener('blur', updateMemberNumber);
    
    // Form validation
    const form = document.querySelector('.needs-validation');
    form.addEventListener('submit', function(e) {
        if (!form.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        }
        form.classList.add('was-validated');
    });
    
    // Initialize with one department row
    addDepartmentRow();
});

function addDepartmentRow() {
    const container = document.getElementById('department-assignments');
    const row = document.createElement('div');
    row.className = 'row mb-3 department-row';
    row.id = `dept-row-${departmentRowIndex}`;
    
    let departmentOptions = '<option value="">Select Department</option>';
    departments.forEach(dept => {
        departmentOptions += `<option value="${dept.id}">${dept.name} (${dept.department_type})</option>`;
    });
    
    row.innerHTML = `
        <div class="col-md-6">
            <select class="form-select" name="departments[${departmentRowIndex}][department_id]">
                ${departmentOptions}
            </select>
        </div>
        <div class="col-md-4">
            <input type="text" class="form-control" name="departments[${departmentRowIndex}][role]" 
                   placeholder="Role (e.g., Member, Leader)" value="member">
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

function saveDraft() {
    const formData = new FormData(document.querySelector('form'));
    const data = {};
    
    for (let [key, value] of formData.entries()) {
        data[key] = value;
    }
    
    localStorage.setItem('member_draft', JSON.stringify(data));
    ChurchCMS.showToast('Draft saved successfully', 'success');
}

function loadDraft() {
    const draft = localStorage.getItem('member_draft');
    if (draft) {
        try {
            const data = JSON.parse(draft);
            Object.keys(data).forEach(key => {
                const input = document.querySelector(`[name="${key}"]`);
                if (input && input.type !== 'file') {
                    input.value = data[key];
                }
            });
            ChurchCMS.showToast('Draft loaded successfully', 'info');
        } catch (e) {
            console.error('Error loading draft:', e);
        }
    }
}

// Auto-save every 30 seconds
setInterval(saveDraft, 30000);

// Load draft on page load if exists
window.addEventListener('load', function() {
    const draft = localStorage.getItem('member_draft');
    if (draft) {
        const loadDraftBtn = document.createElement('button');
        loadDraftBtn.type = 'button';
        loadDraftBtn.className = 'btn btn-info btn-sm me-2';
        loadDraftBtn.innerHTML = '<i class="fas fa-download me-1"></i>Load Draft';
        loadDraftBtn.onclick = loadDraft;
        
        const cancelBtn = document.querySelector('.btn-outline-secondary');
        cancelBtn.parentNode.insertBefore(loadDraftBtn, cancelBtn.nextSibling);
    }
});
</script>

<?php
// Include footer
include '../../includes/footer.php';
?>