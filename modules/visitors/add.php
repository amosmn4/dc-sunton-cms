<?php
/**
 * Add New Visitor
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
$page_title = 'Add New Visitor';
$page_icon = 'fas fa-user-plus';
$page_description = 'Register a new church visitor';

// Breadcrumb
$breadcrumb = [
    ['title' => 'Visitors', 'url' => 'index.php'],
    ['title' => 'Add New Visitor']
];

// Page actions
$page_actions = [
    [
        'title' => 'Back to Visitors',
        'url' => 'index.php',
        'icon' => 'fas fa-arrow-left',
        'class' => 'outline-secondary'
    ]
];

// Get follow-up persons (members who can be assigned for follow-up)
try {
    $db = Database::getInstance();
    $followupPersonsStmt = $db->executeQuery("
        SELECT m.id, CONCAT(m.first_name, ' ', m.last_name) as name, d.name as department_name
        FROM members m 
        LEFT JOIN member_departments md ON m.id = md.member_id AND md.is_active = 1
        LEFT JOIN departments d ON md.department_id = d.id
        WHERE m.membership_status = 'active' 
        ORDER BY m.first_name, m.last_name
    ");
    $followupPersons = $followupPersonsStmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching follow-up persons: " . $e->getMessage());
    $followupPersons = [];
}

// Form processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = Database::getInstance();
        
        // Validation rules
        $rules = [
            'first_name' => ['required', 'max:50'],
            'last_name' => ['required', 'max:50'],
            'phone' => ['phone'],
            'email' => ['email'],
            'age_group' => ['required'],
            'gender' => ['required'],
            'visit_date' => ['required', 'date'],
            'service_attended' => ['max:100'],
            'how_heard_about_us' => ['max:100'],
            'purpose_of_visit' => ['max:500'],
            'areas_of_interest' => ['max:500'],
            'previous_church' => ['max:100'],
            'address' => ['max:500'],
            'notes' => ['max:1000']
        ];
        
        // Validate input
        $validation = validateInput($_POST, $rules);
        
        if (!$validation['valid']) {
            throw new Exception('Validation failed: ' . implode(', ', $validation['errors']));
        }
        
        $data = $validation['data'];
        
        // Generate unique visitor number
        do {
            $visitorNumber = 'VIS' . date('y') . sprintf('%04d', rand(1000, 9999));
            $existingStmt = $db->executeQuery("SELECT id FROM visitors WHERE visitor_number = ?", [$visitorNumber]);
        } while ($existingStmt->fetch());
        
        // Prepare visitor data
        $visitorData = [
            'visitor_number' => $visitorNumber,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'phone' => !empty($data['phone']) ? formatPhoneNumber($data['phone']) : null,
            'email' => !empty($data['email']) ? strtolower($data['email']) : null,
            'address' => $data['address'] ?? null,
            'age_group' => $data['age_group'],
            'gender' => $data['gender'],
            'visit_date' => $data['visit_date'],
            'service_attended' => $data['service_attended'] ?? null,
            'how_heard_about_us' => $data['how_heard_about_us'] ?? null,
            'purpose_of_visit' => $data['purpose_of_visit'] ?? null,
            'areas_of_interest' => $data['areas_of_interest'] ?? null,
            'previous_church' => $data['previous_church'] ?? null,
            'status' => 'new_visitor',
            'assigned_followup_person_id' => !empty($data['assigned_followup_person_id']) ? $data['assigned_followup_person_id'] : null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $_SESSION['user_id']
        ];
        
        $db->beginTransaction();
        
        // Insert visitor
        $visitorId = insertRecord('visitors', $visitorData);
        
        if (!$visitorId) {
            throw new Exception('Failed to create visitor record');
        }
        
        // Create initial follow-up record if follow-up person is assigned
        if (!empty($data['assigned_followup_person_id'])) {
            $followupData = [
                'visitor_id' => $visitorId,
                'followup_type' => 'visit',
                'followup_date' => date('Y-m-d'),
                'description' => 'Initial contact - visitor registered',
                'outcome' => 'Visitor information collected and follow-up person assigned',
                'next_followup_date' => date('Y-m-d', strtotime('+7 days')), // Schedule follow-up in a week
                'performed_by' => $_SESSION['user_id'],
                'status' => 'completed'
            ];
            
            $followupId = insertRecord('visitor_followups', $followupData);
            if (!$followupId) {
                throw new Exception('Failed to create initial follow-up record');
            }
        }
        
        $db->commit();
        
        // Log activity
        logActivity('Visitor created', 'visitors', $visitorId, null, $visitorData);
        
        setFlashMessage('success', 'Visitor "' . $data['first_name'] . ' ' . $data['last_name'] . '" has been added successfully.');
        
        // Redirect based on user choice
        if (isset($_POST['save_and_new'])) {
            redirect('add.php');
        } elseif (isset($_POST['save_and_followup'])) {
            redirect('followup.php?visitor_id=' . $visitorId);
        } else {
            redirect('view.php?id=' . $visitorId);
        }
        
    } catch (Exception $e) {
        if (isset($db) && $db->getConnection()->inTransaction()) {
            $db->rollback();
        }
        
        error_log("Error adding visitor: " . $e->getMessage());
        setFlashMessage('error', 'Failed to add visitor: ' . $e->getMessage());
    }
}

include '../../includes/header.php';
?>

<!-- Main Content -->
<div class="row">
    <div class="col-lg-8 col-xl-6 mx-auto">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-user-plus me-2"></i>
                    Visitor Information
                </h6>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <!-- Personal Information -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="first_name" class="form-label">
                                    First Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                       value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                                <div class="invalid-feedback">Please provide a first name.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="last_name" class="form-label">
                                    Last Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                       value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                                <div class="invalid-feedback">Please provide a last name.</div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                                       placeholder="+254 700 000 000">
                                <div class="form-text">Kenyan format: +254XXXXXXXXX or 07XXXXXXXX</div>
                                <div class="invalid-feedback">Please provide a valid phone number.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                       placeholder="visitor@example.com">
                                <div class="invalid-feedback">Please provide a valid email address.</div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="age_group" class="form-label">
                                    Age Group <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="age_group" name="age_group" required>
                                    <option value="">Select Age Group</option>
                                    <?php foreach (VISITOR_AGE_GROUPS as $key => $label): ?>
                                        <option value="<?php echo $key; ?>" 
                                                <?php echo ($_POST['age_group'] ?? '') === $key ? 'selected' : ''; ?>>
                                            <?php echo $label; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select an age group.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="gender" class="form-label">
                                    Gender <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="gender" name="gender" required>
                                    <option value="">Select Gender</option>
                                    <?php foreach (GENDER_OPTIONS as $key => $label): ?>
                                        <option value="<?php echo $key; ?>" 
                                                <?php echo ($_POST['gender'] ?? '') === $key ? 'selected' : ''; ?>>
                                            <?php echo $label; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select a gender.</div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="2"
                                  placeholder="Enter full address..."><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                    </div>

                    <!-- Visit Information -->
                    <h6 class="mt-4 mb-3 text-church-blue">
                        <i class="fas fa-calendar-check me-2"></i>Visit Information
                    </h6>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="visit_date" class="form-label">
                                    Visit Date <span class="text-danger">*</span>
                                </label>
                                <input type="date" class="form-control" id="visit_date" name="visit_date" 
                                       value="<?php echo htmlspecialchars($_POST['visit_date'] ?? date('Y-m-d')); ?>" required>
                                <div class="invalid-feedback">Please provide the visit date.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="service_attended" class="form-label">Service Attended</label>
                                <input type="text" class="form-control" id="service_attended" name="service_attended" 
                                       value="<?php echo htmlspecialchars($_POST['service_attended'] ?? ''); ?>"
                                       placeholder="e.g., Sunday Service, Prayer Meeting">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="how_heard_about_us" class="form-label">How did you hear about us?</label>
                                <select class="form-select" id="how_heard_about_us" name="how_heard_about_us">
                                    <option value="">Select option</option>
                                    <option value="friend_family" <?php echo ($_POST['how_heard_about_us'] ?? '') === 'friend_family' ? 'selected' : ''; ?>>Friend/Family</option>
                                    <option value="social_media" <?php echo ($_POST['how_heard_about_us'] ?? '') === 'social_media' ? 'selected' : ''; ?>>Social Media</option>
                                    <option value="website" <?php echo ($_POST['how_heard_about_us'] ?? '') === 'website' ? 'selected' : ''; ?>>Website</option>
                                    <option value="advertisement" <?php echo ($_POST['how_heard_about_us'] ?? '') === 'advertisement' ? 'selected' : ''; ?>>Advertisement</option>
                                    <option value="walked_by" <?php echo ($_POST['how_heard_about_us'] ?? '') === 'walked_by' ? 'selected' : ''; ?>>Walked by</option>
                                    <option value="event" <?php echo ($_POST['how_heard_about_us'] ?? '') === 'event' ? 'selected' : ''; ?>>Church Event</option>
                                    <option value="other" <?php echo ($_POST['how_heard_about_us'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="previous_church" class="form-label">Previous Church (if any)</label>
                                <input type="text" class="form-control" id="previous_church" name="previous_church" 
                                       value="<?php echo htmlspecialchars($_POST['previous_church'] ?? ''); ?>"
                                       placeholder="Name of previous church">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="purpose_of_visit" class="form-label">Purpose of Visit</label>
                        <textarea class="form-control" id="purpose_of_visit" name="purpose_of_visit" rows="2"
                                  placeholder="What brought you to our church today?"><?php echo htmlspecialchars($_POST['purpose_of_visit'] ?? ''); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="areas_of_interest" class="form-label">Areas of Interest</label>
                        <textarea class="form-control" id="areas_of_interest" name="areas_of_interest" rows="2"
                                  placeholder="Which church activities or ministries interest you?"><?php echo htmlspecialchars($_POST['areas_of_interest'] ?? ''); ?></textarea>
                    </div>

                    <!-- Follow-up Assignment -->
                    <h6 class="mt-4 mb-3 text-church-blue">
                        <i class="fas fa-user-check me-2"></i>Follow-up Assignment
                    </h6>

                    <div class="mb-3">
                        <label for="assigned_followup_person_id" class="form-label">Assign Follow-up Person</label>
                        <select class="form-select" id="assigned_followup_person_id" name="assigned_followup_person_id">
                            <option value="">Select follow-up person (optional)</option>
                            <?php foreach ($followupPersons as $person): ?>
                                <option value="<?php echo $person['id']; ?>" 
                                        <?php echo ($_POST['assigned_followup_person_id'] ?? '') == $person['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($person['name']); ?>
                                    <?php if ($person['department_name']): ?>
                                        (<?php echo htmlspecialchars($person['department_name']); ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Assign a member to follow up with this visitor</div>
                    </div>

                    <div class="mb-3">
                        <label for="notes" class="form-label">Additional Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"
                                  placeholder="Any additional information about the visitor..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                    </div>

                    <!-- Form Actions -->
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-church-primary">
                            <i class="fas fa-save me-2"></i>Save & View Visitor
                        </button>
                        <button type="submit" name="save_and_new" class="btn btn-outline-success">
                            <i class="fas fa-plus me-2"></i>Save & Add Another
                        </button>
                        <button type="submit" name="save_and_followup" class="btn btn-outline-info">
                            <i class="fas fa-phone me-2"></i>Save & Create Follow-up
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    const form = document.querySelector('.needs-validation');
    
    form.addEventListener('submit', function(e) {
        if (!form.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        form.classList.add('was-validated');
    });
    
    // Phone number formatting
    const phoneInput = document.getElementById('phone');
    phoneInput.addEventListener('blur', function() {
        if (this.value.trim()) {
            // Basic phone number formatting for Kenyan numbers
            let phone = this.value.replace(/[\s-]/g, '');
            if (phone.startsWith('07') || phone.startsWith('01')) {
                phone = '+254' + phone.substring(1);
            } else if (phone.startsWith('7') || phone.startsWith('1')) {
                phone = '+254' + phone;
            }
            this.value = phone;
        }
    });
    
    // Email formatting
    const emailInput = document.getElementById('email');
    emailInput.addEventListener('blur', function() {
        if (this.value.trim()) {
            this.value = this.value.toLowerCase().trim();
        }
    });
    
    // Auto-set visit date to today if empty
    const visitDateInput = document.getElementById('visit_date');
    if (!visitDateInput.value) {
        visitDateInput.value = new Date().toISOString().split('T')[0];
    }
    
    // Prevent form submission with Enter key except in textareas
    form.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
            e.preventDefault();
        }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>