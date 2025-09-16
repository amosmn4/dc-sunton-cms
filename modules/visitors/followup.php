<?php
/**
 * Add Follow-up Record
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
$visitorId = isset($_GET['visitor_id']) ? (int)$_GET['visitor_id'] : 0;
if (!$visitorId) {
    setFlashMessage('error', 'Invalid visitor ID provided.');
    redirect('index.php');
}

// Fetch visitor details
try {
    $db = Database::getInstance();
    
    $visitorStmt = $db->executeQuery("
        SELECT v.*, CONCAT(m.first_name, ' ', m.last_name) as followup_person_name
        FROM visitors v 
        LEFT JOIN members m ON v.assigned_followup_person_id = m.id
        WHERE v.id = ?
    ", [$visitorId]);
    
    $visitor = $visitorStmt->fetch();
    
    if (!$visitor) {
        setFlashMessage('error', 'Visitor not found.');
        redirect('index.php');
    }
    
} catch (Exception $e) {
    error_log("Error fetching visitor: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred while fetching visitor details.');
    redirect('index.php');
}

// Page configuration
$page_title = 'Add Follow-up - ' . $visitor['first_name'] . ' ' . $visitor['last_name'];
$page_icon = 'fas fa-phone';
$page_description = 'Record follow-up activity for visitor';

// Breadcrumb
$breadcrumb = [
    ['title' => 'Visitors', 'url' => 'index.php'],
    ['title' => $visitor['first_name'] . ' ' . $visitor['last_name'], 'url' => 'view.php?id=' . $visitorId],
    ['title' => 'Add Follow-up']
];

// Page actions
$page_actions = [
    [
        'title' => 'Back to Visitor',
        'url' => 'view.php?id=' . $visitorId,
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
            'followup_type' => ['required'],
            'followup_date' => ['required', 'date'],
            'description' => ['required', 'max:1000'],
            'outcome' => ['max:1000'],
            'next_followup_date' => ['date'],
            'status' => ['required'],
            'notes' => ['max:1000']
        ];
        
        // Validate input
        $validation = validateInput($_POST, $rules);
        
        if (!$validation['valid']) {
            throw new Exception('Validation failed: ' . implode(', ', $validation['errors']));
        }
        
        $data = $validation['data'];
        
        // Prepare follow-up data
        $followupData = [
            'visitor_id' => $visitorId,
            'followup_type' => $data['followup_type'],
            'followup_date' => $data['followup_date'],
            'description' => $data['description'],
            'outcome' => $data['outcome'] ?? null,
            'next_followup_date' => !empty($data['next_followup_date']) ? $data['next_followup_date'] : null,
            'performed_by' => $_SESSION['user_id'],
            'status' => $data['status'],
            'notes' => $data['notes'] ?? null
        ];
        
        $db->beginTransaction();
        
        // Insert follow-up record
        $followupId = insertRecord('visitor_followups', $followupData);
        
        if (!$followupId) {
            throw new Exception('Failed to create follow-up record');
        }
        
        // Update visitor status if specified
        if (isset($_POST['update_visitor_status']) && !empty($_POST['new_visitor_status'])) {
            $newStatus = sanitizeInput($_POST['new_visitor_status']);
            $statusUpdate = updateRecord('visitors', 
                ['status' => $newStatus, 'updated_at' => date('Y-m-d H:i:s')], 
                ['id' => $visitorId]
            );
            
            if (!$statusUpdate) {
                throw new Exception('Failed to update visitor status');
            }
        }
        
        $db->commit();
        
        // Log activity
        logActivity('Follow-up record created', 'visitor_followups', $followupId, null, $followupData);
        
        setFlashMessage('success', 'Follow-up record has been added successfully.');
        
        // Redirect based on user choice
        if (isset($_POST['save_and_new'])) {
            redirect('followup.php?visitor_id=' . $visitorId);
        } else {
            redirect('view.php?id=' . $visitorId);
        }
        
    } catch (Exception $e) {
        if (isset($db) && $db->getConnection()->inTransaction()) {
            $db->rollback();
        }
        
        error_log("Error adding follow-up: " . $e->getMessage());
        setFlashMessage('error', 'Failed to add follow-up record: ' . $e->getMessage());
    }
}

include '../../includes/header.php';
?>

<!-- Main Content -->
<div class="row">
    <div class="col-lg-8 mx-auto">
        <!-- Visitor Info Card -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-2 text-center">
                        <div class="visitor-avatar bg-church-blue text-white rounded-circle mx-auto d-flex align-items-center justify-content-center" 
                             style="width: 60px; height: 60px; font-size: 1.5rem;">
                            <i class="fas fa-user"></i>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h5 class="mb-1"><?php echo htmlspecialchars($visitor['first_name'] . ' ' . $visitor['last_name']); ?></h5>
                        <p class="text-muted mb-1"><?php echo htmlspecialchars($visitor['visitor_number']); ?></p>
                        <?php if (!empty($visitor['phone'])): ?>
                        <p class="mb-0"><i class="fas fa-phone text-muted me-1"></i><?php echo htmlspecialchars($visitor['phone']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4 text-end">
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
                        <span class="badge <?php echo $statusClass; ?> mb-2">
                            <?php echo $statusLabel; ?>
                        </span><br>
                        <small class="text-muted">
                            First Visit: <?php echo formatDisplayDate($visitor['visit_date']); ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Follow-up Form -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-phone me-2"></i>
                    Follow-up Information
                </h6>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="followup_type" class="form-label">
                                    Follow-up Type <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="followup_type" name="followup_type" required>
                                    <option value="">Select follow-up type</option>
                                    <?php foreach (FOLLOWUP_TYPES as $key => $label): ?>
                                        <option value="<?php echo $key; ?>" 
                                                <?php echo ($_POST['followup_type'] ?? '') === $key ? 'selected' : ''; ?>>
                                            <?php echo $label; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select a follow-up type.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="followup_date" class="form-label">
                                    Follow-up Date <span class="text-danger">*</span>
                                </label>
                                <input type="date" class="form-control" id="followup_date" name="followup_date" 
                                       value="<?php echo htmlspecialchars($_POST['followup_date'] ?? date('Y-m-d')); ?>" required>
                                <div class="invalid-feedback">Please provide the follow-up date.</div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">
                            Description <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" id="description" name="description" rows="3" 
                                  placeholder="Describe the follow-up activity..." required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        <div class="invalid-feedback">Please provide a description of the follow-up activity.</div>
                        <div class="form-text">Describe what was done during this follow-up contact.</div>
                    </div>

                    <div class="mb-3">
                        <label for="outcome" class="form-label">Outcome/Response</label>
                        <textarea class="form-control" id="outcome" name="outcome" rows="3"
                                  placeholder="What was the visitor's response or outcome?"><?php echo htmlspecialchars($_POST['outcome'] ?? ''); ?></textarea>
                        <div class="form-text">Record the visitor's response and any outcomes from this contact.</div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="status" class="form-label">
                                    Follow-up Status <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="">Select status</option>
                                    <?php foreach (FOLLOWUP_STATUS as $key => $label): ?>
                                        <option value="<?php echo $key; ?>" 
                                                <?php echo ($_POST['status'] ?? 'completed') === $key ? 'selected' : ''; ?>>
                                            <?php echo $label; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select the follow-up status.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="next_followup_date" class="form-label">Next Follow-up Date</label>
                                <input type="date" class="form-control" id="next_followup_date" name="next_followup_date" 
                                       value="<?php echo htmlspecialchars($_POST['next_followup_date'] ?? ''); ?>">
                                <div class="form-text">Schedule the next follow-up contact (optional).</div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="notes" class="form-label">Additional Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2"
                                  placeholder="Any additional notes or observations..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                    </div>

                    <!-- Visitor Status Update -->
                    <div class="card bg-light border-0 mb-4">
                        <div class="card-body">
                            <h6 class="card-title">
                                <i class="fas fa-user-edit me-2"></i>Update Visitor Status
                            </h6>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="update_visitor_status" name="update_visitor_status"
                                       <?php echo isset($_POST['update_visitor_status']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="update_visitor_status">
                                    Update visitor status based on this follow-up
                                </label>
                            </div>
                            
                            <div id="visitor_status_section" style="display: <?php echo isset($_POST['update_visitor_status']) ? 'block' : 'none'; ?>;">
                                <label for="new_visitor_status" class="form-label">New Visitor Status</label>
                                <select class="form-select" id="new_visitor_status" name="new_visitor_status">
                                    <option value="">Keep current status (<?php echo VISITOR_STATUS[$visitor['status']] ?? ucfirst($visitor['status']); ?>)</option>
                                    <?php foreach (VISITOR_STATUS as $key => $label): ?>
                                        <?php if ($key !== $visitor['status']): ?>
                                            <option value="<?php echo $key; ?>" 
                                                    <?php echo ($_POST['new_visitor_status'] ?? '') === $key ? 'selected' : ''; ?>>
                                                <?php echo $label; ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">
                                    <strong>Current Status:</strong> <?php echo VISITOR_STATUS[$visitor['status']] ?? ucfirst($visitor['status']); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-church-primary">
                            <i class="fas fa-save me-2"></i>Save Follow-up
                        </button>
                        <button type="submit" name="save_and_new" class="btn btn-outline-success">
                            <i class="fas fa-plus me-2"></i>Save & Add Another
                        </button>
                        <a href="view.php?id=<?php echo $visitorId; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Quick Follow-up Templates -->
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-lightning-bolt me-2"></i>Quick Templates
                </h6>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">Click on a template to quickly fill the form:</p>
                <div class="row g-2">
                    <div class="col-md-6">
                        <button type="button" class="btn btn-outline-primary btn-sm w-100" onclick="fillTemplate('welcome_call')">
                            <i class="fas fa-phone me-2"></i>Welcome Phone Call
                        </button>
                    </div>
                    <div class="col-md-6">
                        <button type="button" class="btn btn-outline-success btn-sm w-100" onclick="fillTemplate('home_visit')">
                            <i class="fas fa-home me-2"></i>Home Visit
                        </button>
                    </div>
                    <div class="col-md-6">
                        <button type="button" class="btn btn-outline-info btn-sm w-100" onclick="fillTemplate('invitation_call')">
                            <i class="fas fa-phone me-2"></i>Service Invitation
                        </button>
                    </div>
                    <div class="col-md-6">
                        <button type="button" class="btn btn-outline-warning btn-sm w-100" onclick="fillTemplate('check_up')">
                            <i class="fas fa-heart me-2"></i>Welfare Check-up
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Follow-up templates
const templates = {
    welcome_call: {
        followup_type: 'phone_call',
        description: 'Made welcome phone call to thank visitor for attending our service and answer any questions they might have.',
        outcome: 'Visitor was appreciative of the call and expressed interest in attending again.',
        next_followup_date: getDateDaysFromNow(7),
        status: 'completed'
    },
    home_visit: {
        followup_type: 'visit',
        description: 'Conducted home visit to personally welcome visitor to our church family and provide information about our programs.',
        outcome: 'Had a meaningful conversation about faith and church involvement. Visitor seemed receptive.',
        next_followup_date: getDateDaysFromNow(14),
        status: 'completed'
    },
    invitation_call: {
        followup_type: 'phone_call',
        description: 'Called to personally invite visitor to upcoming Sunday service and special programs.',
        outcome: 'Visitor confirmed attendance for next Sunday service.',
        next_followup_date: getDateDaysFromNow(3),
        status: 'completed'
    },
    check_up: {
        followup_type: 'phone_call',
        description: 'Called to check on visitor\'s wellbeing and offer prayer and support.',
        outcome: 'Visitor appreciated the care and requested prayer for specific needs.',
        next_followup_date: getDateDaysFromNow(30),
        status: 'completed'
    }
};

function fillTemplate(templateKey) {
    const template = templates[templateKey];
    if (!template) return;
    
    // Confirm before filling
    if (confirm('This will replace the current form content. Continue?')) {
        // Fill form fields
        Object.keys(template).forEach(key => {
            const field = document.getElementById(key);
            if (field) {
                field.value = template[key];
            }
        });
        
        // Show success message
        ChurchCMS.showToast('Template applied successfully', 'success');
    }
}

function getDateDaysFromNow(days) {
    const date = new Date();
    date.setDate(date.getDate() + days);
    return date.toISOString().split('T')[0];
}

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
    
    // Toggle visitor status update section
    const updateStatusCheckbox = document.getElementById('update_visitor_status');
    const statusSection = document.getElementById('visitor_status_section');
    
    updateStatusCheckbox.addEventListener('change', function() {
        statusSection.style.display = this.checked ? 'block' : 'none';
        
        if (!this.checked) {
            document.getElementById('new_visitor_status').value = '';
        }
    });
    
    // Auto-suggest next follow-up date based on follow-up type
    const followupTypeSelect = document.getElementById('followup_type');
    const nextFollowupDate = document.getElementById('next_followup_date');
    
    followupTypeSelect.addEventListener('change', function() {
        const type = this.value;
        let daysFromNow = 0;
        
        switch(type) {
            case 'phone_call':
                daysFromNow = 7; // 1 week
                break;
            case 'visit':
                daysFromNow = 14; // 2 weeks
                break;
            case 'sms':
                daysFromNow = 3; // 3 days
                break;
            case 'email':
                daysFromNow = 5; // 5 days
                break;
            case 'letter':
                daysFromNow = 30; // 1 month
                break;
        }
        
        if (daysFromNow > 0 && !nextFollowupDate.value) {
            nextFollowupDate.value = getDateDaysFromNow(daysFromNow);
        }
    });
    
    // Character count for text areas
    const textareas = document.querySelectorAll('textarea');
    textareas.forEach(textarea => {
        const maxLength = textarea.getAttribute('maxlength');
        if (maxLength) {
            const countDiv = document.createElement('div');
            countDiv.className = 'form-text text-end';
            countDiv.style.fontSize = '0.8rem';
            textarea.parentNode.appendChild(countDiv);
            
            function updateCount() {
                const remaining = maxLength - textarea.value.length;
                countDiv.textContent = `${textarea.value.length}/${maxLength} characters`;
                countDiv.className = `form-text text-end ${remaining < 50 ? 'text-warning' : remaining < 20 ? 'text-danger' : ''}`;
            }
            
            textarea.addEventListener('input', updateCount);
            updateCount();
        }
    });
    
    // Auto-resize textareas
    textareas.forEach(textarea => {
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
    });
    
    // Prevent form submission with Enter key except in textareas
    form.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
            e.preventDefault();
        }
    });
    
    // Auto-save form data
    const autoSaveFields = form.querySelectorAll('input, select, textarea');
    const formId = 'followup_form_<?php echo $visitorId; ?>';
    
    // Load saved data
    const savedData = localStorage.getItem(formId);
    if (savedData) {
        try {
            const data = JSON.parse(savedData);
            Object.keys(data).forEach(key => {
                const field = form.querySelector(`[name="${key}"]`);
                if (field && field.type !== 'checkbox') {
                    field.value = data[key];
                } else if (field && field.type === 'checkbox') {
                    field.checked = data[key];
                }
            });
        } catch (e) {
            console.error('Error loading saved data:', e);
        }
    }
    
    // Save data on input
    autoSaveFields.forEach(field => {
        field.addEventListener('input', function() {
            const formData = new FormData(form);
            const data = {};
            
            for (let [key, value] of formData.entries()) {
                data[key] = value;
            }
            
            // Handle checkboxes
            const checkboxes = form.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(checkbox => {
                data[checkbox.name] = checkbox.checked;
            });
            
            localStorage.setItem(formId, JSON.stringify(data));
        });
    });
    
    // Clear saved data on successful submit
    form.addEventListener('submit', function() {
        localStorage.removeItem(formId);
    });
    
    // Show unsaved changes warning
    let hasUnsavedChanges = false;
    
    autoSaveFields.forEach(field => {
        field.addEventListener('input', function() {
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
                if (confirm('Cancel and return to visitor details?')) {
                    window.location.href = 'view.php?id=<?php echo $visitorId; ?>';
                }
                break;
        }
    }
});
</script>

<?php include '../../includes/footer.php'; ?>