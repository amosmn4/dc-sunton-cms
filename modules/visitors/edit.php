<?php
/**
 * Edit Visitor
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

// Get visitor ID
$visitorId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$visitorId) {
    setFlashMessage('error', 'Invalid visitor ID provided.');
    redirect('index.php');
}

// Fetch visitor details
try {
    $db = Database::getInstance();
    
    $visitorStmt = $db->executeQuery("SELECT * FROM visitors WHERE id = ?", [$visitorId]);
    $visitor = $visitorStmt->fetch();
    
    if (!$visitor) {
        setFlashMessage('error', 'Visitor not found.');
        redirect('index.php');
    }
    
    // Get follow-up persons (members who can be assigned for follow-up)
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
    error_log("Error fetching visitor: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred while fetching visitor details.');
    redirect('index.php');
}

// Page configuration
$page_title = 'Edit Visitor - ' . $visitor['first_name'] . ' ' . $visitor['last_name'];
$page_icon = 'fas fa-user-edit';
$page_description = 'Edit visitor information and details';

// Breadcrumb
$breadcrumb = [
    ['title' => 'Visitors', 'url' => 'index.php'],
    ['title' => $visitor['first_name'] . ' ' . $visitor['last_name'], 'url' => 'view.php?id=' . $visitorId],
    ['title' => 'Edit']
];

// Page actions
$page_actions = [
    [
        'title' => 'View Visitor',
        'url' => 'view.php?id=' . $visitorId,
        'icon' => 'fas fa-eye',
        'class' => 'outline-info'
    ],
    [
        'title' => 'Back to List',
        'url' => 'index.php',
        'icon' => 'fas fa-arrow-left',
        'class' => 'outline-secondary'
    ]
];

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
            'status' => ['required'],
            'notes' => ['max:1000']
        ];
        
        // Validate input
        $validation = validateInput($_POST, $rules);
        
        if (!$validation['valid']) {
            throw new Exception('Validation failed: ' . implode(', ', $validation['errors']));
        }
        
        $data = $validation['data'];
        
        // Store old data for logging
        $oldData = $visitor;
        
        // Prepare updated visitor data
        $updateData = [
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
            'status' => $data['status'],
            'assigned_followup_person_id' => !empty($data['assigned_followup_person_id']) ? $data['assigned_followup_person_id'] : null,
            'notes' => $data['notes'] ?? null,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // Check for duplicate phone/email if changed
        if ($updateData['phone'] !== $oldData['phone'] && !empty($updateData['phone'])) {
            $phoneCheckStmt = $db->executeQuery("SELECT id FROM visitors WHERE phone = ? AND id != ?", [$updateData['phone'], $visitorId]);
            if ($phoneCheckStmt->fetch()) {
                throw new Exception('Phone number already exists for another visitor.');
            }
        }
        
        if ($updateData['email'] !== $oldData['email'] && !empty($updateData['email'])) {
            $emailCheckStmt = $db->executeQuery("SELECT id FROM visitors WHERE email = ? AND id != ?", [$updateData['email'], $visitorId]);
            if ($emailCheckStmt->fetch()) {
                throw new Exception('Email address already exists for another visitor.');
            }
        }
        
        $db->beginTransaction();
        
        // Update visitor
        $updated = updateRecord('visitors', $updateData, ['id' => $visitorId]);
        
        if (!$updated) {
            throw new Exception('Failed to update visitor record');
        }
        
        $db->commit();
        
        // Log activity
        logActivity('Visitor updated', 'visitors', $visitorId, $oldData, $updateData);
        
        setFlashMessage('success', 'Visitor "' . $data['first_name'] . ' ' . $data['last_name'] . '" has been updated successfully.');
        
        // Redirect to view page
        redirect('view.php?id=' . $visitorId);
        
    } catch (Exception $e) {
        if (isset($db) && $db->getConnection()->inTransaction()) {
            $db->rollback();
        }
        
        error_log("Error updating visitor: " . $e->getMessage());
        setFlashMessage('error', 'Failed to update visitor: ' . $e->getMessage());
    }
}

include '../../includes/header.php';
?>

<!-- Main Content -->
<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="fas fa-user-edit me-2"></i>
                    Edit Visitor Information
                </h6>
                <div>
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
                        Current: <?php echo $statusLabel; ?>
                    </span>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <!-- Personal Information -->
                    <h6 class="mb-3 text-church-blue">
                        <i class="fas fa-user me-2"></i>Personal Information
                    </h6>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="first_name" class="form-label">
                                    First Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                       value="<?php echo htmlspecialchars($visitor['first_name']); ?>" required>
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
                                                <?php echo $visitor['age_group'] === $key ? 'selected' : ''; ?>>
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
                                                <?php echo $visitor['gender'] === $key ? 'selected' : ''; ?>>
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
                                  placeholder="Enter full address..."><?php echo htmlspecialchars($visitor['address']); ?></textarea>
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
                                       value="<?php echo htmlspecialchars($visitor['visit_date']); ?>" required>
                                <div class="invalid-feedback">Please provide the visit date.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="service_attended" class="form-label">Service Attended</label>
                                <input type="text" class="form-control" id="service_attended" name="service_attended" 
                                       value="<?php echo htmlspecialchars($visitor['service_attended']); ?>"
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
                                    <option value="friend_family" <?php echo $visitor['how_heard_about_us'] === 'friend_family' ? 'selected' : ''; ?>>Friend/Family</option>
                                    <option value="social_media" <?php echo $visitor['how_heard_about_us'] === 'social_media' ? 'selected' : ''; ?>>Social Media</option>
                                    <option value="website" <?php echo $visitor['how_heard_about_us'] === 'website' ? 'selected' : ''; ?>>Website</option>
                                    <option value="advertisement" <?php echo $visitor['how_heard_about_us'] === 'advertisement' ? 'selected' : ''; ?>>Advertisement</option>
                                    <option value="walked_by" <?php echo $visitor['how_heard_about_us'] === 'walked_by' ? 'selected' : ''; ?>>Walked by</option>
                                    <option value="event" <?php echo $visitor['how_heard_about_us'] === 'event' ? 'selected' : ''; ?>>Church Event</option>
                                    <option value="other" <?php echo $visitor['how_heard_about_us'] === 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="previous_church" class="form-label">Previous Church (if any)</label>
                                <input type="text" class="form-control" id="previous_church" name="previous_church" 
                                       value="<?php echo htmlspecialchars($visitor['previous_church']); ?>"
                                       placeholder="Name of previous church">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="purpose_of_visit" class="form-label">Purpose of Visit</label>
                        <textarea class="form-control" id="purpose_of_visit" name="purpose_of_visit" rows="2"
                                  placeholder="What brought you to our church today?"><?php echo htmlspecialchars($visitor['purpose_of_visit']); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="areas_of_interest" class="form-label">Areas of Interest</label>
                        <textarea class="form-control" id="areas_of_interest" name="areas_of_interest" rows="2"
                                  placeholder="Which church activities or ministries interest you?"><?php echo htmlspecialchars($visitor['areas_of_interest']); ?></textarea>
                    </div>

                    <!-- Status and Follow-up -->
                    <h6 class="mt-4 mb-3 text-church-blue">
                        <i class="fas fa-user-check me-2"></i>Status and Follow-up
                    </h6>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="status" class="form-label">
                                    Visitor Status <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="">Select status</option>
                                    <?php foreach (VISITOR_STATUS as $key => $label): ?>
                                        <option value="<?php echo $key; ?>" 
                                                <?php echo $visitor['status'] === $key ? 'selected' : ''; ?>>
                                            <?php echo $label; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select a visitor status.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="assigned_followup_person_id" class="form-label">Follow-up Person</label>
                                <select class="form-select" id="assigned_followup_person_id" name="assigned_followup_person_id">
                                    <option value="">No follow-up person assigned</option>
                                    <?php foreach ($followupPersons as $person): ?>
                                        <option value="<?php echo $person['id']; ?>" 
                                                <?php echo $visitor['assigned_followup_person_id'] == $person['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($person['name']); ?>
                                            <?php if ($person['department_name']): ?>
                                                (<?php echo htmlspecialchars($person['department_name']); ?>)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Assign a member to follow up with this visitor</div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="notes" class="form-label">Additional Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"
                                  placeholder="Any additional information about the visitor..."><?php echo htmlspecialchars($visitor['notes']); ?></textarea>
                    </div>

                    <!-- System Information (Read-only) -->
                    <h6 class="mt-4 mb-3 text-church-blue">
                        <i class="fas fa-info-circle me-2"></i>System Information
                    </h6>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Visitor Number</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($visitor['visitor_number']); ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Date Added</label>
                                <input type="text" class="form-control" value="<?php echo formatDisplayDateTime($visitor['created_at']); ?>" readonly>
                            </div>
                        </div>
                    </div>

                    <?php if ($visitor['updated_at'] !== $visitor['created_at']): ?>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Last Updated</label>
                                <input type="text" class="form-control" value="<?php echo formatDisplayDateTime($visitor['updated_at']); ?>" readonly>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Form Actions -->
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-church-primary">
                            <i class="fas fa-save me-2"></i>Update Visitor
                        </button>
                        <a href="view.php?id=<?php echo $visitorId; ?>" class="btn btn-outline-info">
                            <i class="fas fa-eye me-2"></i>View Details
                        </a>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to List
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Change History -->
        <?php
        try {
            // Get recent activity logs for this visitor
            $logsStmt = $db->executeQuery("
                SELECT al.*, CONCAT(u.first_name, ' ', u.last_name) as user_name
                FROM activity_logs al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE al.table_name = 'visitors' AND al.record_id = ?
                ORDER BY al.created_at DESC
                LIMIT 10
            ", [$visitorId]);
            $logs = $logsStmt->fetchAll();
            
            if (!empty($logs)):
        ?>
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-history me-2"></i>Recent Changes
                </h6>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <?php foreach ($logs as $log): ?>
                        <div class="timeline-item">
                            <div class="timeline-marker">
                                <i class="fas fa-edit text-church-blue"></i>
                            </div>
                            <div class="timeline-content">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($log['action']); ?></h6>
                                        <small class="text-muted">
                                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($log['user_name'] ?: 'System'); ?>
                                            | <i class="fas fa-calendar me-1"></i><?php echo formatDisplayDateTime($log['created_at']); ?>
                                        </small>
                                    </div>
                                </div>
                                <?php if (!empty($log['old_values']) || !empty($log['new_values'])): ?>
                                <div class="small">
                                    <button type="button" class="btn btn-link btn-sm p-0" data-bs-toggle="collapse" data-bs-target="#log-<?php echo $log['id']; ?>">
                                        <i class="fas fa-eye me-1"></i>View Changes
                                    </button>
                                    <div class="collapse mt-2" id="log-<?php echo $log['id']; ?>">
                                        <div class="bg-light p-2 rounded small">
                                            <?php if (!empty($log['old_values'])): ?>
                                                <strong>Before:</strong><br>
                                                <pre class="mb-2"><?php echo htmlspecialchars(json_encode(json_decode($log['old_values']), JSON_PRETTY_PRINT)); ?></pre>
                                            <?php endif; ?>
                                            <?php if (!empty($log['new_values'])): ?>
                                                <strong>After:</strong><br>
                                                <pre class="mb-0"><?php echo htmlspecialchars(json_encode(json_decode($log['new_values']), JSON_PRETTY_PRINT)); ?></pre>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php 
            endif;
        } catch (Exception $e) {
            // Silently fail for activity logs
            error_log("Error fetching activity logs: " . $e->getMessage());
        }
        ?>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #dee2e6;
}

.timeline-item {
    position: relative;
    margin-bottom: 1.5rem;
}

.timeline-marker {
    position: absolute;
    left: -23px;
    top: 5px;
    width: 16px;
    height: 16px;
    background: white;
    border: 2px solid var(--church-blue);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
}

.timeline-content {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 1rem;
    margin-left: 20px;
}
</style>

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
    
    // Status change warning
    const statusSelect = document.getElementById('status');
    const originalStatus = '<?php echo $visitor['status']; ?>';
    
    statusSelect.addEventListener('change', function() {
        const newStatus = this.value;
        
        if (originalStatus === 'converted_member' && newStatus !== 'converted_member') {
            if (!confirm('Warning: This visitor is currently marked as "Converted to Member". Are you sure you want to change their status?')) {
                this.value = originalStatus;
            }
        }
        
        if (newStatus === 'converted_member' && originalStatus !== 'converted_member') {
            if (!confirm('Are you sure you want to mark this visitor as "Converted to Member"? This indicates they have become a church member.')) {
                this.value = originalStatus;
            }
        }
    });
    
    // Prevent form submission with Enter key except in textareas
    form.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
            e.preventDefault();
        }
    });
    
    // Show unsaved changes warning
    let hasUnsavedChanges = false;
    const formInputs = form.querySelectorAll('input, select, textarea');
    
    formInputs.forEach(input => {
        input.addEventListener('input', function() {
            hasUnsavedChanges = true;
        });
    });
    
    form.addEventListener('submit', function() {
        hasUnsavedChanges = false;
    });
    
    window.addEventListener('beforeunload', function(e) {
        if (hasUnsavedChanges) {
            e.preventDefault();
            e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            return e.returnValue;
        }
    });
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey || e.metaKey) {
        switch(e.key) {
            case 's':
                e.preventDefault();
                document.querySelector('form').submit();
                break;
            case 'Escape':
                e.preventDefault();
                if (confirm('Cancel changes and return to visitor details?')) {
                    window.location.href = 'view.php?id=<?php echo $visitorId; ?>';
                }
                break;
        }
    }
});
</script>

<?php include '../../includes/footer.php'; ?>d-feedback">Please provide a first name.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="last_name" class="form-label">
                                    Last Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                       value="<?php echo htmlspecialchars($visitor['last_name']); ?>" required>
                                <div class="invalid-feedback">Please provide a last name.</div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($visitor['phone']); ?>"
                                       placeholder="+254 700 000 000">
                                <div class="form-text">Kenyan format: +254XXXXXXXXX or 07XXXXXXXX</div>
                                <div class="invalid-feedback">Please provide a valid phone number.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($visitor['email']); ?>"
                                       placeholder="visitor@example.com">
                                <div class="invalid-feedback">Please provide a valid email address.</div>
                            </div>
                        </div>  
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="2"
                                  placeholder="Enter full address..."><?php echo htmlspecialchars($visitor['address']); ?></textarea>
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
                                       value="<?php echo htmlspecialchars($visitor['visit_date']); ?>" required>
                                <div class="invalid-feedback">Please provide the visit date.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="service_attended" class="form-label">Service Attended</label>
                                <input type="text" class="form-control" id="service_attended" name="service_attended" 
                                       value="<?php echo htmlspecialchars($visitor['service_attended']); ?>"
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
                                    <option value="friend_family" <?php echo $visitor['how_heard_about_us'] === 'friend_family' ? 'selected' : ''; ?>>Friend/Family</option>
                                    <option value="social_media" <?php echo $visitor['how_heard_about_us'] === 'social_media' ? 'selected' : ''; ?>>Social Media</option>
                                    <option value="website" <?php echo $visitor['how_heard_about_us'] === 'website' ? 'selected' : ''; ?>>Website</option>
                                    <option value="advertisement" <?php echo $visitor['how_heard_about_us'] === 'advertisement' ? 'selected' : ''; ?>>Advertisement</option>
                                    <option value="walked_by" <?php echo $visitor['how_heard_about_us'] === 'walked_by' ? 'selected' : ''; ?>>Walked by</option>
                                    <option value="event" <?php echo $visitor['how_heard_about_us'] === 'event' ? 'selected' : ''; ?>>Church Event</option>
                                    <option value="other" <?php echo $visitor['how_heard_about_us'] === 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="previous_church" class="form-label">Previous Church (if any)</label>
                                <input type="text" class="form-control" id="previous_church" name="previous_church" 
                                       value="<?php echo htmlspecialchars($visitor['previous_church']); ?>"
                                       placeholder="Name of previous church">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="purpose_of_visit" class="form-label">Purpose of Visit</label>
                        <textarea class="form-control" id="purpose_of_visit" name="purpose_of_visit" rows="2"
                                  placeholder="What brought you to our church today?"><?php echo htmlspecialchars($visitor['purpose_of_visit']); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="areas_of_interest" class="form-label">Areas of Interest</label>
                        <textarea class="form-control" id="areas_of_interest" name="areas_of_interest" rows="2"
                                  placeholder="Which church activities or ministries interest you?"><?php echo htmlspecialchars($visitor['areas_of_interest']); ?></textarea>
                    </div>
                    <!-- Status and Follow-up -->
                    <h6 class="mt-4 mb-3 text-church-blue">
                        <i class="fas fa-user-check me-2"></i>Status and Follow-up
                    </h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="status" class="form-label">
                                    Visitor Status <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="">Select status</option>
                                    <?php foreach (VISITOR_STATUS as $key => $label): ?>
                                        <option value="<?php echo $key; ?>" 
                                                <?php echo $visitor['status'] === $key ? 'selected' : ''; ?>>
                                            <?php echo $label; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select a visitor status.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="assigned_followup_person_id" class="form-label">Follow-up Person</label>
                                <select class="form-select" id="assigned_followup_person_id" name="assigned_followup_person_id">
                                    <option value="">No follow-up person assigned</option>
                                    <?php foreach ($followupPersons as $person): ?>
                                        <option value="<?php echo $person['id']; ?>" 
                                                <?php echo $visitor['assigned_followup_person_id'] == $person['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($person['name']); ?>
                                            <?php if ($person['department_name']): ?>
                                                (<?php echo htmlspecialchars($person['department_name']); ?>)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Assign a member to follow up with this visitor</div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="notes" class="form-label">Additional Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"
                                  placeholder="Any additional information about the visitor..."><?php echo htmlspecialchars($visitor['notes']); ?></textarea>
                    </div>  
                    <!-- System Information (Read-only) -->
                    <h6 class="mt-4 mb-3 text-church-blue">
                        <i class="fas fa-info-circle me-2"></i>System Information
                    </h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="created_at" class="form-label">Created At</label>
                                <input type="text" class="form-control" id="created_at" name="created_at"
                                       value="<?php echo htmlspecialchars($visitor['created_at']); ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="updated_at" class="form-label">Updated At</label>
                                <input type="text" class="form-control" id="updated_at" name="updated_at"
                                       value="<?php echo htmlspecialchars($visitor['updated_at']); ?>" readonly>
                            </div>
                        </div>
                    </div>
                    <!-- Form Actions -->
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-church-primary">
                            <i class="fas fa-save me-2"></i>Update Visitor
                        </button>
                        <a href="view.php?id=<?php echo $visitorId; ?>" class="btn btn-outline-info">
                            <i class="fas fa-eye me-2"></i>View Details 
                        </a>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to List 
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Change History -->
        <?php
        // Fetch change history from the database
        $changeHistory = []; // Replace with actual data fetching logic

        if (!empty($changeHistory)): ?>
            <div class="mt-4">
                <h6 class="text-church-blue">
                    <i class="fas fa-history me-2"></i>Change History
                </h6>
                <ul class="list-unstyled">
                    <?php foreach ($changeHistory as $change): ?>
                        <li>
                            <strong><?php echo htmlspecialchars($change['field']); ?>:</strong>
                            <?php echo htmlspecialchars($change['old_value']); ?> &rarr; <?php echo htmlspecialchars($change['new_value']); ?>
                            <br>
                            <small class="text-muted">Changed on <?php echo htmlspecialchars($change['changed_at']); ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php include '../../includes/footer.php'; ?><?php
