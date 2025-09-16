} catch (Exception $e) {
    error_log("SMS form data error: " . $e->getMessage());
    $departments = [];
    $templates = [];
    $sms_stats = ['balance' => 0, 'sent_today' => 0, 'sent_this_month' => 0];
}

/**
 * Get SMS recipients based on selection criteria
 */
function getSMSRecipients($form_data) {
    $db = Database::getInstance();
    $recipients = [];
    
    switch ($form_data['recipient_type']) {
        case 'all_members':
            $recipients = $db->executeQuery(
                "SELECT id, first_name, last_name, phone FROM members 
                 WHERE membership_status = 'active' AND phone IS NOT NULL AND phone != ''"
            )->fetchAll();
            break;
            
        case 'department':
            if (!empty($form_data['department_id'])) {
                $recipients = $db->executeQuery(
                    "SELECT DISTINCT m.id, m.first_name, m.last_name, m.phone 
                     FROM members m
                     JOIN member_departments md ON m.id = md.member_id
                     WHERE m.membership_status = 'active' 
                     AND m.phone IS NOT NULL AND m.phone != ''
                     AND md.department_id = ? AND md.is_active = 1",
                    [$form_data['department_id']]
                )->fetchAll();
            }
            break;
            
        case 'age_group':
            if (!empty($form_data['age_group'])) {
                $age_conditions = getAgeGroupConditions($form_data['age_group']);
                if ($age_conditions) {
                    $recipients = $db->executeQuery(
                        "SELECT id, first_name, last_name, phone FROM members 
                         WHERE membership_status = 'active' 
                         AND phone IS NOT NULL AND phone != ''
                         AND {$age_conditions['condition']}",
                        $age_conditions['params']
                    )->fetchAll();
                }
            }
            break;
            
        case 'individual':
        case 'custom_list':
            if (!empty($form_data['member_ids'])) {
                $member_ids = is_array($form_data['member_ids']) 
                    ? $form_data['member_ids'] 
                    : explode(',', $form_data['member_ids']);
                
                $placeholders = str_repeat('?,', count($member_ids) - 1) . '?';
                $recipients = $db->executeQuery(
                    "SELECT id, first_name, last_name, phone FROM members 
                     WHERE membership_status = 'active' 
                     AND phone IS NOT NULL AND phone != ''
                     AND id IN ($placeholders)",
                    $member_ids
                )->fetchAll();
            }
            break;
    }
    
    // Filter out invalid phone numbers
    return array_filter($recipients, function($recipient) {
        return validatePhoneNumber($recipient['phone']);
    });
}

/**
 * Get age group SQL conditions
 */
function getAgeGroupConditions($age_group) {
    switch ($age_group) {
        case 'child':
            return [
                'condition' => 'YEAR(CURDATE()) - YEAR(date_of_birth) <= ?',
                'params' => [CHILD_MAX_AGE]
            ];
        case 'teen':
            return [
                'condition' => 'YEAR(CURDATE()) - YEAR(date_of_birth) BETWEEN ? AND ?',
                'params' => [CHILD_MAX_AGE + 1, TEEN_MAX_AGE]
            ];
        case 'youth':
            return [
                'condition' => 'YEAR(CURDATE()) - YEAR(date_of_birth) BETWEEN ? AND ?',
                'params' => [TEEN_MAX_AGE + 1, YOUTH_MAX_AGE]
            ];
        case 'adult':
            return [
                'condition' => 'YEAR(CURDATE()) - YEAR(date_of_birth) BETWEEN ? AND ?',
                'params' => [YOUTH_MAX_AGE + 1, SENIOR_MIN_AGE - 1]
            ];
        case 'senior':
            return [
                'condition' => 'YEAR(CURDATE()) - YEAR(date_of_birth) >= ?',
                'params' => [SENIOR_MIN_AGE]
            ];
        default:
            return null;
    }
}

/**
 * Personalize SMS message with member data
 */
function personalizeMessage($message, $recipient) {
    $placeholders = [
        '{first_name}' => $recipient['first_name'],
        '{last_name}' => $recipient['last_name'],
        '{full_name}' => $recipient['first_name'] . ' ' . $recipient['last_name']
    ];
    
    return str_replace(array_keys($placeholders), array_values($placeholders), $message);
}

/**
 * Send SMS batch
 */
function sendSMSBatch($batch_id) {
    // This would integrate with your SMS provider API
    // For now, we'll simulate the sending process
    
    try {
        $db = Database::getInstance();
        
        // Get all pending SMS in this batch
        $sms_list = $db->executeQuery(
            "SELECT * FROM sms_individual WHERE batch_id = ? AND status = 'pending'",
            [$batch_id]
        )->fetchAll();
        
        $sent_count = 0;
        $failed_count = 0;
        
        foreach ($sms_list as $sms) {
            // Simulate SMS sending (replace with actual SMS API call)
            $send_result = sendSingleSMS($sms['recipient_phone'], $sms['message']);
            
            if ($send_result['success']) {
                // Update SMS record as sent
                updateRecord('sms_individual', [
                    'status' => 'sent',
                    'sent_at' => date('Y-m-d H:i:s'),
                    'provider_message_id' => $send_result['message_id'] ?? null
                ], ['id' => $sms['id']]);
                
                $sent_count++;
            } else {
                // Update SMS record as failed
                updateRecord('sms_individual', [
                    'status' => 'failed',
                    'error_message' => $send_result['error']
                ], ['id' => $sms['id']]);
                
                $failed_count++;
            }
        }
        
        // Update batch record
        updateRecord('sms_history', [
            'status' => 'completed',
            'sent_count' => $sent_count,
            'failed_count' => $failed_count,
            'completed_at' => date('Y-m-d H:i:s')
        ], ['batch_id' => $batch_id]);
        
        return [
            'success' => true,
            'sent_count' => $sent_count,
            'failed_count' => $failed_count
        ];
        
    } catch (Exception $e) {
        error_log("SMS batch send error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Send single SMS (integrate with SMS provider)
 */
function sendSingleSMS($phone, $message) {
    // This is where you would integrate with your SMS provider
    // For example: Africa's Talking, Twilio, etc.
    
    // Simulate success/failure
    $success_rate = 0.95; // 95% success rate simulation
    
    if (rand(1, 100) <= ($success_rate * 100)) {
        return [
            'success' => true,
            'message_id' => 'MSG' . time() . rand(1000, 9999)
        ];
    } else {
        return [
            'success' => false,
            'error' => 'Network error or invalid phone number'
        ];
    }
}

// Include header
include '../../includes/header.php';
?>

<!-- Send SMS Content -->
<div class="row">
    <div class="col-lg-8">
        <!-- SMS Form -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-paper-plane me-2"></i>Compose SMS Message
                </h6>
            </div>
            <div class="card-body">
                <?php if (isset($errors['general'])): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($errors['general']); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" id="smsForm" class="needs-validation" novalidate>
                    <!-- Recipients Selection -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">
                                <i class="fas fa-users me-2"></i>Select Recipients
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Recipient Type</label>
                                    <select class="form-select <?php echo isset($errors['recipient_type']) ? 'is-invalid' : ''; ?>" 
                                            name="recipient_type" id="recipient_type" required>
                                        <option value="all_members" <?php echo $form_data['recipient_type'] === 'all_members' ? 'selected' : ''; ?>>
                                            All Active Members
                                        </option>
                                        <option value="department" <?php echo $form_data['recipient_type'] === 'department' ? 'selected' : ''; ?>>
                                            Department Members
                                        </option>
                                        <option value="age_group" <?php echo $form_data['recipient_type'] === 'age_group' ? 'selected' : ''; ?>>
                                            Age Group
                                        </option>
                                        <option value="individual" <?php echo $form_data['recipient_type'] === 'individual' ? 'selected' : ''; ?>>
                                            Individual Members
                                        </option>
                                        <option value="custom_list" <?php echo $form_data['recipient_type'] === 'custom_list' ? 'selected' : ''; ?>>
                                            Custom List
                                        </option>
                                    </select>
                                    <?php if (isset($errors['recipient_type'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['recipient_type']; ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Department Selection -->
                                <div class="col-md-6 mb-3" id="department_selection" style="display: none;">
                                    <label class="form-label">Select Department</label>
                                    <select class="form-select <?php echo isset($errors['department_id']) ? 'is-invalid' : ''; ?>" 
                                            name="department_id" id="department_id">
                                        <option value="">Choose Department</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo $dept['id']; ?>" 
                                                    <?php echo $form_data['department_id'] == $dept['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($dept['name']); ?> 
                                                (<?php echo ucfirst(str_replace('_', ' ', $dept['department_type'])); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($errors['department_id'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['department_id']; ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Age Group Selection -->
                                <div class="col-md-6 mb-3" id="age_group_selection" style="display: none;">
                                    <label class="form-label">Select Age Group</label>
                                    <select class="form-select" name="age_group" id="age_group">
                                        <option value="">Choose Age Group</option>
                                        <option value="child" <?php echo $form_data['age_group'] === 'child' ? 'selected' : ''; ?>>
                                            Children (0-<?php echo CHILD_MAX_AGE; ?>)
                                        </option>
                                        <option value="teen" <?php echo $form_data['age_group'] === 'teen' ? 'selected' : ''; ?>>
                                            Teens (<?php echo CHILD_MAX_AGE + 1; ?>-<?php echo TEEN_MAX_AGE; ?>)
                                        </option>
                                        <option value="youth" <?php echo $form_data['age_group'] === 'youth' ? 'selected' : ''; ?>>
                                            Youth (<?php echo TEEN_MAX_AGE + 1; ?>-<?php echo YOUTH_MAX_AGE; ?>)
                                        </option>
                                        <option value="adult" <?php echo $form_data['age_group'] === 'adult' ? 'selected' : ''; ?>>
                                            Adults (<?php echo YOUTH_MAX_AGE + 1; ?>-<?php echo SENIOR_MIN_AGE - 1; ?>)
                                        </option>
                                        <option value="senior" <?php echo $form_data['age_group'] === 'senior' ? 'selected' : ''; ?>>
                                            Seniors (<?php echo SENIOR_MIN_AGE; ?>+)
                                        </option>
                                    </select>
                                </div>
                                
                                <!-- Individual Member Selection -->
                                <div class="col-md-12 mb-3" id="member_selection" style="display: none;">
                                    <label class="form-label">Select Members</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control <?php echo isset($errors['member_ids']) ? 'is-invalid' : ''; ?>" 
                                               id="member_search" placeholder="Search and select members...">
                                        <button type="button" class="btn btn-outline-secondary" id="browse_members">
                                            <i class="fas fa-search"></i> Browse
                                        </button>
                                    </div>
                                    <input type="hidden" name="member_ids" id="member_ids" 
                                           value="<?php echo htmlspecialchars($form_data['member_ids']); ?>">
                                    <div id="selected_members" class="mt-2"></div>
                                    <?php if (isset($errors['member_ids'])): ?>
                                        <div class="invalid-feedback d-block"><?php echo $errors['member_ids']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Preview Recipients Button -->
                            <div class="text-center">
                                <button type="button" class="btn btn-outline-info" onclick="previewRecipients()">
                                    <i class="fas fa-eye me-1"></i>Preview Recipients
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Message Composition -->
                    <div class="card mb-4">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <i class="fas fa-edit me-2"></i>Message Content
                            </h6>
                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                    data-bs-toggle="modal" data-bs-target="#templatesModal">
                                <i class="fas fa-file-alt me-1"></i>Use Template
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="message" class="form-label">SMS Message</label>
                                <textarea class="form-control <?php echo isset($errors['message']) ? 'is-invalid' : ''; ?>" 
                                          id="message" name="message" rows="4" 
                                          placeholder="Type your message here..." 
                                          maxlength="160" required><?php echo htmlspecialchars($form_data['message']); ?></textarea>
                                <div class="d-flex justify-content-between mt-1">
                                    <small class="text-muted">
                                        Use {first_name} for personalization
                                    </small>
                                    <small class="text-muted">
                                        <span id="char_count">0</span>/160 characters
                                        <span id="sms_count" class="ms-2">1 SMS</span>
                                    </small>
                                </div>
                                <?php if (isset($errors['message'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['message']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Scheduling Options -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="schedule_send" 
                                       name="schedule_send" <?php echo $form_data['schedule_send'] ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-bold" for="schedule_send">
                                    <i class="fas fa-clock me-2"></i>Schedule Message
                                </label>
                            </div>
                        </div>
                        <div class="card-body" id="schedule_options" style="display: none;">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="schedule_date" class="form-label">Date</label>
                                    <input type="date" class="form-control" id="schedule_date" name="schedule_date" 
                                           value="<?php echo htmlspecialchars($form_data['schedule_date']); ?>"
                                           min="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="schedule_time" class="form-label">Time</label>
                                    <input type="time" class="form-control" id="schedule_time" name="schedule_time" 
                                           value="<?php echo htmlspecialchars($form_data['schedule_time']); ?>">
                                </div>
                            </div>
                            <?php if (isset($errors['schedule'])): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <?php echo htmlspecialchars($errors['schedule']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="d-flex justify-content-between">
                        <a href="<?php echo BASE_URL; ?>modules/sms/" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Back
                        </a>
                        
                        <div>
                            <button type="button" class="btn btn-outline-primary me-2" onclick="previewMessage()">
                                <i class="fas fa-eye me-1"></i>Preview
                            </button>
                            <button type="submit" class="btn btn-church-primary" id="send_btn">
                                <i class="fas fa-paper-plane me-1"></i>
                                <span id="send_text">Send SMS</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- SMS Statistics Sidebar -->
    <div class="col-lg-4">
        <!-- SMS Balance -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-wallet me-2"></i>SMS Balance & Usage
                </h6>
            </div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <div class="h2 text-success mb-0"><?php echo number_format($sms_stats['balance']); ?></div>
                    <small class="text-muted">SMS Credits Available</small>
                </div>
                
                <div class="row text-center">
                    <div class="col-6">
                        <div class="border-end">
                            <div class="h5 text-info mb-0"><?php echo number_format($sms_stats['sent_today']); ?></div>
                            <small class="text-muted">Sent Today</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="h5 text-warning mb-0"><?php echo number_format($sms_stats['sent_this_month']); ?></div>
                        <small class="text-muted">This Month</small>
                    </div>
                </div>
                
                <hr>
                
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted">Cost per SMS:</small>
                    <strong class="text-success"><?php echo formatCurrency(SMS_COST_PER_SMS); ?></strong>
                </div>
            </div>
        </div>
        
        <!-- SMS Tips -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-lightbulb me-2"></i>SMS Tips
                </h6>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <div class="list-group-item border-0 px-0 py-2">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        <small>Keep messages under 160 characters</small>
                    </div>
                    <div class="list-group-item border-0 px-0 py-2">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        <small>Use {first_name} for personalization</small>
                    </div>
                    <div class="list-group-item border-0 px-0 py-2">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        <small>Preview before sending</small>
                    </div>
                    <div class="list-group-item border-0 px-0 py-2">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        <small>Use templates for consistency</small>
                    </div>
                    <div class="list-group-item border-0 px-0 py-2">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        <small>Schedule messages for optimal timing</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SMS Templates Modal -->
<div class="modal fade" id="templatesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-church-blue text-white">
                <h5 class="modal-title">
                    <i class="fas fa-file-alt me-2"></i>SMS Templates
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if (!empty($templates)): ?>
                    <div class="row">
                        <?php 
                        $grouped_templates = [];
                        foreach ($templates as $template) {
                            $grouped_templates[$template['category']][] = $template;
                        }
                        ?>
                        
                        <?php foreach ($grouped_templates as $category => $category_templates): ?>
                            <div class="col-md-6 mb-4">
                                <h6 class="text-uppercase text-muted mb-3">
                                    <?php echo ucfirst(str_replace('_', ' ', $category)); ?>
                                </h6>
                                <?php foreach ($category_templates as $template): ?>
                                    <div class="card mb-2 template-card" style="cursor: pointer;" 
                                         onclick="selectTemplate(<?php echo htmlspecialchars(json_encode($template)); ?>)">
                                        <div class="card-body p-3">
                                            <h6 class="card-title mb-2"><?php echo htmlspecialchars($template['name']); ?></h6>
                                            <p class="card-text small text-muted mb-0">
                                                <?php echo htmlspecialchars(truncateText($template['message'], 80)); ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                        <h6>No templates available</h6>
                        <p class="text-muted">Create templates to reuse common messages.</p>
                        <a href="<?php echo BASE_URL; ?>modules/sms/templates.php" class="btn btn-church-primary">
                            <i class="fas fa-plus me-1"></i>Create Template
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Member Browser Modal -->
<div class="modal fade" id="memberBrowserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-church-blue text-white">
                <h5 class="modal-title">
                    <i class="fas fa-users me-2"></i>Select Members
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="memberBrowserContent">
                    <div class="text-center py-4">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading members...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-church-primary" onclick="selectBrowsedMembers()">
                    <i class="fas fa-check me-1"></i>Select Members
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let selectedMembers = [];

document.addEventListener('DOMContentLoaded', function() {
    // Initialize form interactions
    initializeSMSForm();
    
    // Load pre-selected members if any
    const memberIds = document.getElementById('member_ids').value;
    if (memberIds) {
        loadSelectedMembers(memberIds.split(','));
    }
});

function initializeSMSForm() {
    const recipientType = document.getElementById('recipient_type');
    const scheduleCheckbox = document.getElementById('schedule_send');
    const messageTextarea = document.getElementById('message');
    
    // Handle recipient type change
    recipientType.addEventListener('change', function() {
        toggleRecipientOptions(this.value);
        updateSendButtonText();
    });
    
    // Handle schedule checkbox
    scheduleCheckbox.addEventListener('change', function() {
        toggleScheduleOptions(this.checked);
        updateSendButtonText();
    });
    
    // Handle message character count
    messageTextarea.addEventListener('input', function() {
        updateCharacterCount();
    });
    
    // Initial setup
    toggleRecipientOptions(recipientType.value);
    toggleScheduleOptions(scheduleCheckbox.checked);
    updateCharacterCount();
}

function toggleRecipientOptions(type) {
    const departmentSelection = document.getElementById('department_selection');
    const ageGroupSelection = document.getElementById('age_group_selection');
    const memberSelection = document.getElementById('member_selection');
    
    // Hide all options
    departmentSelection.style.display = 'none';
    ageGroupSelection.style.display = 'none';
    memberSelection.style.display = 'none';
    
    // Show relevant option
    switch (type) {
        case 'department':
            departmentSelection.style.display = 'block';
            break;
        case 'age_group':
            ageGroupSelection.style.display = 'block';
            break;
        case 'individual':
        case 'custom_list':
            memberSelection.style.display = 'block';
            break;
    }
}

function toggleScheduleOptions(isScheduled) {
    const scheduleOptions = document.getElementById('schedule_options');
    scheduleOptions.style.display = isScheduled ? 'block' : 'none';
}

function updateCharacterCount() {
    const messageTextarea = document.getElementById('message');
    const charCount = document.getElementById('char_count');
    const smsCount = document.getElementById('sms_count');
    
    const length = messageTextarea.value.length;
    charCount.textContent = length;
    
    const smsNumber = Math.ceil(length / 160) || 1;
    smsCount.textContent = smsNumber + ' SMS' + (smsNumber > 1 ? 's' : '');
    
    // Change color based on length
    if (length > 160) {
        charCount.className = 'text-danger';
    } else if (length > 140) {
        charCount.className = 'text-warning';
    } else {
        charCount.className = 'text-muted';
    }
}

function updateSendButtonText() {
    const scheduleCheckbox = document.getElementById('schedule_send');
    const sendText = document.getElementById('send_text');
    
    sendText.textContent = scheduleCheckbox.checked ? 'Schedule SMS' : 'Send SMS';
}

function selectTemplate(template) {
    document.getElementById('message').value = template.message;
    updateCharacterCount();
    bootstrap.Modal.getInstance(document.getElementById('templatesModal')).hide();
    ChurchCMS.showToast('Template selected: ' + template.name, 'success');
}

function previewRecipients() {
    const formData = new FormData(document.getElementById('smsForm'));
    formData.append('action', 'preview');
    
    ChurchCMS.showLoading('Loading recipients...');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(html => {
        // Parse response and show recipients
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const recipients = doc.querySelectorAll('.recipient-item');
        
        ChurchCMS.hideLoading();
        
        if (recipients.length > 0) {
            showRecipientsModal(Array.from(recipients).map(item => ({
                name: item.dataset.name,
                phone: item.dataset.phone
            })));
        } else {
            ChurchCMS.showToast('No valid recipients found', 'warning');
        }
    })
    .catch(error => {
        ChurchCMS.hideLoading();
        console.error('Error previewing recipients:', error);
        ChurchCMS.showToast('Error loading recipients', 'error');
    });
}

function showRecipientsModal(recipients) {
    const modalHtml = `
        <div class="modal fade" id="recipientsModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-church-blue text-white">
                        <h5 class="modal-title">
                            <i class="fas fa-users me-2"></i>SMS Recipients Preview
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>${recipients.length}</strong> recipients will receive this SMS
                        </div>
                        <div class="row">
                            ${recipients.map(recipient => `
                                <div class="col-md-6 mb-2">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-user text-muted me-2"></i>
                                        <div>
                                            <div class="fw-bold">${recipient.name}</div>
                                            <small class="text-muted">${recipient.phone}</small>
                                        </div>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('recipientsModal'));
    modal.show();
    
    document.getElementById('recipientsModal').addEventListener('hidden.bs.modal', function() {
        this.remove();
    });
}

function previewMessage() {
    const message = document.getElementById('message').value;
    if (!message.trim()) {
        ChurchCMS.showToast('Please enter a message first', 'warning');
        return;
    }
    
    const previewHtml = `
        <div class="modal fade" id="messagePreviewModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-church-blue text-white">
                        <h5 class="modal-title">
                            <i class="fas fa-eye me-2"></i>Message Preview
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="card bg-light">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-mobile-alt text-primary me-2"></i>
                                    <strong>SMS Preview</strong>
                                </div>
                                <div class="p-3 bg-white rounded border">
                                    <div class="small text-muted mb-1">From: ${SMS_SENDER_ID}</div>
                                    <div>${message.replace('{first_name}', '<strong class="text-primary">[Member Name]</strong>')}</div>
                                </div>
                                <div class="mt-2 small text-muted">
                                    Characters: ${message.length}/160 | 
                                    SMS Count: ${Math.ceil(message.length / 160) || 1}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', previewHtml);
    const modal = new bootstrap.Modal(document.getElementById('messagePreviewModal'));
    modal.show();
    
    document.getElementById('messagePreviewModal').addEventListener('hidden.bs.modal', function() {
        this.remove();
    });
}

// Member browser functionality
document.getElementById('browse_members').addEventListener('click', function() {
    loadMemberBrowser();
    const modal = new bootstrap.Modal(document.getElementById('memberBrowserModal'));
    modal.show();
});

function loadMemberBrowser() {
    const content = document.getElementById('memberBrowserContent');
    
    fetch(`${BASE_URL}api/members.php?action=browse_for_sms`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayMemberBrowser(data.members);
            } else {
                content.innerHTML = `
                    <div class="text-center py-4">
                        <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                        <h6>Error Loading Members</h6>
                        <p class="text-muted">${data.message}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error loading members:', error);
            content.innerHTML = `
                <div class="text-center py-4">
                    <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                    <h6>Error Loading Members</h6>
                    <p class="text-muted">Please try again later.</p>
                </div>
            `;
        });
}

function displayMemberBrowser(members) {
    const content = document.getElementById('memberBrowserContent');
    
    let html = `
        <div class="mb-3">
            <input type="text" class="form-control" id="member_browser_search" 
                   placeholder="Search members..." onkeyup="filterMemberBrowser(this.value)">
        </div>
        <div class="row" id="member_browser_list">
    `;
    
    members.forEach(member => {
        const isSelected = selectedMembers.some(m => m.id === member.id);
        html += `
            <div class="col-md-6 mb-2 member-browser-item" data-name="${member.first_name} ${member.last_name}">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="${member.id}" 
                           id="member_${member.id}" ${isSelected ? 'checked' : ''}
                           onchange="toggleMemberSelection(${JSON.stringify(member).replace(/"/g, '&quot;')})">
                    <label class="form-check-label w-100" for="member_${member.id}">
                        <div class="d-flex align-items-center">
                            <div class="avatar bg-church-blue text-white rounded-circle me-2" style="width: 30px; height: 30px;">
                                <i class="fas fa-user small"></i>
                            </div>
                            <div>
                                <div class="fw-bold">${member.first_name} ${member.last_name}</div>
                                <small class="text-muted">${member.phone}</small>
                            </div>
                        </div>
                    </label>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    content.innerHTML = html;
}

function filterMemberBrowser(searchTerm) {
    const items = document.querySelectorAll('.member-browser-item');
    const term = searchTerm.toLowerCase();
    
    items.forEach(item => {
        const name = item.dataset.name.toLowerCase();
        item.style.display = name.includes(term) ? 'block' : 'none';
    });
}

function toggleMemberSelection(member) {
    const index = selectedMembers.findIndex(m => m.id === member.id);
    
    if (index === -1) {
        selectedMembers.push(member);
    } else {
        selectedMembers.splice(index, 1);
    }
}

function selectBrowsedMembers() {
    updateSelectedMembersDisplay();
    bootstrap.Modal.getInstance(document.getElementById('memberBrowserModal')).hide();
    
    if (selectedMembers.length > 0) {
        ChurchCMS.showToast(`${selectedMembers.length} members selected`, 'success');
    }
}

function updateSelectedMembersDisplay() {
    const memberIds = selectedMembers.map(m => m.id).join(',');
    document.getElementById('member_ids').value = memberIds;
    
    const display = document.getElementById('selected_members');
    
    if (selectedMembers.length === 0) {
        display.innerHTML = '<small class="text-muted">No members selected</small>';
    } else {
        let html = '<div class="d-flex flex-wrap gap-1">';
        selectedMembers.forEach((member, index) => {
            html += `
                <span class="badge bg-church-blue">
                    ${member.first_name} ${member.last_name}
                    <button type="button" class="btn-close btn-close-white ms-1 small" 
                            onclick="removeSelectedMember(${index})" aria-label="Remove"></button>
                </span>
            `;
        });
        html += '</div>';
        html += `<small class="text-muted mt-1 d-block">${selectedMembers.length} member(s) selected</small>`;
        display.innerHTML = html;
    }
}

function removeSelectedMember(index) {
    selectedMembers.splice(index, 1);
    updateSelectedMembersDisplay();
}

function loadSelectedMembers(memberIds) {
    if (memberIds.length === 0) return;
    
    fetch(`${BASE_URL}api/members.php?action=get_by_ids`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ member_ids: memberIds })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            selectedMembers = data.members;
            updateSelectedMembersDisplay();
        }
    })
    .catch(error => {
        console.error('Error loading selected members:', error);
    });
}

// Form submission with confirmation
document.getElementById('smsForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const message = document.getElementById('message').value;
    const recipientType = document.getElementById('recipient_type').value;
    const isScheduled = document.getElementById('schedule_send').checked;
    
    let confirmMessage = `Are you sure you want to ${isScheduled ? 'schedule' : 'send'} this SMS?\n\n`;
    confirmMessage += `Message: "${message}"\n`;
    confirmMessage += `Recipients: ${getRecipientDescription(recipientType)}`;
    
    if (isScheduled) {
        const scheduleDate = document.getElementById('schedule_date').value;
        const scheduleTime = document.getElementById('schedule_time').value;
        confirmMessage += `\nScheduled for: ${scheduleDate} at ${scheduleTime}`;
    }
    
    if (confirm(confirmMessage)) {
        const sendBtn = document.getElementById('send_btn');
        const originalText = sendBtn.innerHTML;
        
        sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Processing...';
        sendBtn.disabled = true;
        
        // Submit form
        this.submit();
    }
});

function getRecipientDescription(type) {
    switch (type) {
        case 'all_members':
            return 'All active members';
        case 'department':
            const deptSelect = document.getElementById('department_id');
            return 'Members in ' + deptSelect.options[deptSelect.selectedIndex].text;
        case 'age_group':
            const ageSelect = document.getElementById('age_group');
            return ageSelect.options[ageSelect.selectedIndex].text;
        case 'individual':
        case 'custom_list':
            return `${selectedMembers.length} selected members`;
        default:
            return 'Selected recipients';
    }
}
</script>

<?php
// Include footer
include '../../includes/footer.php';
?><?php
/**
 * Send SMS
 * Deliverance Church Management System
 * 
 * Send SMS messages to members, groups, or individuals
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Check authentication and permissions
requireLogin();
if (!hasPermission('sms')) {
    setFlashMessage('error', 'You do not have permission to access this page.');
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit();
}

// Check if SMS module is enabled
if (!isFeatureEnabled('sms')) {
    setFlashMessage('error', 'SMS module is not enabled.');
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit();
}

// Set page variables
$page_title = 'Send SMS';
$page_icon = 'fas fa-sms';
$page_description = 'Send SMS messages to church members';

$breadcrumb = [
    ['title' => 'SMS', 'url' => BASE_URL . 'modules/sms/'],
    ['title' => 'Send SMS']
];

$additional_js = ['assets/js/sms.js'];

// Initialize form data
$form_data = [
    'recipient_type' => 'all_members',
    'department_id' => '',
    'age_group' => '',
    'member_ids' => '',
    'message' => '',
    'template_id' => '',
    'schedule_send' => false,
    'schedule_date' => '',
    'schedule_time' => ''
];

$errors = [];
$preview_recipients = [];

// Handle pre-filled member IDs from URL
if (isset($_GET['member_id'])) {
    $form_data['recipient_type'] = 'individual';
    $form_data['member_ids'] = sanitizeInput($_GET['member_id']);
} elseif (isset($_GET['member_ids'])) {
    $form_data['recipient_type'] = 'custom_list';
    $form_data['member_ids'] = sanitizeInput($_GET['member_ids']);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    foreach ($form_data as $key => $default) {
        if (is_bool($default)) {
            $form_data[$key] = isset($_POST[$key]);
        } else {
            $form_data[$key] = sanitizeInput($_POST[$key] ?? '');
        }
    }
    
    // Validation
    if (empty($form_data['message'])) {
        $errors['message'] = 'Message is required';
    } elseif (strlen($form_data['message']) > 160) {
        $errors['message'] = 'Message too long (maximum 160 characters)';
    }
    
    if ($form_data['recipient_type'] === 'individual' && empty($form_data['member_ids'])) {
        $errors['member_ids'] = 'Please select at least one member';
    }
    
    if ($form_data['recipient_type'] === 'department' && empty($form_data['department_id'])) {
        $errors['department_id'] = 'Please select a department';
    }
    
    if ($form_data['schedule_send']) {
        if (empty($form_data['schedule_date']) || empty($form_data['schedule_time'])) {
            $errors['schedule'] = 'Please provide both date and time for scheduled messages';
        } else {
            $schedule_datetime = $form_data['schedule_date'] . ' ' . $form_data['schedule_time'];
            if (strtotime($schedule_datetime) <= time()) {
                $errors['schedule'] = 'Scheduled time must be in the future';
            }
        }
    }
    
    if (empty($errors)) {
        try {
            $db = Database::getInstance();
            
            // Get recipients based on selection
            $recipients = getSMSRecipients($form_data);
            
            if (empty($recipients)) {
                $errors['general'] = 'No valid recipients found';
            } else {
                // Check SMS balance/limits
                $sms_cost = count($recipients) * SMS_COST_PER_SMS;
                
                // Generate batch ID
                $batch_id = 'SMS' . date('YmdHis') . rand(1000, 9999);
                
                // Save SMS batch record
                $batch_data = [
                    'batch_id' => $batch_id,
                    'recipient_type' => $form_data['recipient_type'],
                    'recipient_filter' => json_encode([
                        'department_id' => $form_data['department_id'],
                        'age_group' => $form_data['age_group'],
                        'member_ids' => $form_data['member_ids']
                    ]),
                    'message' => $form_data['message'],
                    'total_recipients' => count($recipients),
                    'cost' => $sms_cost,
                    'status' => $form_data['schedule_send'] ? 'scheduled' : 'pending',
                    'sent_by' => $_SESSION['user_id'],
                    'sent_at' => $form_data['schedule_send'] ? ($form_data['schedule_date'] . ' ' . $form_data['schedule_time']) : date('Y-m-d H:i:s')
                ];
                
                $batch_result = insertRecord('sms_history', $batch_data);
                
                if ($batch_result) {
                    // Save individual SMS records
                    foreach ($recipients as $recipient) {
                        $personalized_message = personalizeMessage($form_data['message'], $recipient);
                        
                        $sms_data = [
                            'batch_id' => $batch_id,
                            'recipient_phone' => $recipient['phone'],
                            'recipient_name' => $recipient['first_name'] . ' ' . $recipient['last_name'],
                            'member_id' => $recipient['id'],
                            'message' => $personalized_message,
                            'status' => $form_data['schedule_send'] ? 'scheduled' : 'pending',
                            'cost' => SMS_COST_PER_SMS
                        ];
                        
                        insertRecord('sms_individual', $sms_data);
                    }
                    
                    // If not scheduled, send immediately
                    if (!$form_data['schedule_send']) {
                        $send_result = sendSMSBatch($batch_id);
                        
                        if ($send_result['success']) {
                            $message = "SMS sent successfully to {$send_result['sent_count']} recipients.";
                            if ($send_result['failed_count'] > 0) {
                                $message .= " {$send_result['failed_count']} messages failed.";
                            }
                            setFlashMessage('success', $message);
                        } else {
                            setFlashMessage('error', 'Error sending SMS: ' . $send_result['message']);
                        }
                    } else {
                        setFlashMessage('success', "SMS scheduled successfully for {$form_data['schedule_date']} at {$form_data['schedule_time']}");
                    }
                    
                    // Log activity
                    logActivity(
                        'SMS ' . ($form_data['schedule_send'] ? 'scheduled' : 'sent') . ' to ' . count($recipients) . ' recipients',
                        'sms_history',
                        $batch_result
                    );
                    
                    header('Location: ' . BASE_URL . 'modules/sms/history.php?batch_id=' . $batch_id);
                    exit();
                } else {
                    $errors['general'] = 'Failed to save SMS batch';
                }
            }
            
        } catch (Exception $e) {
            error_log("SMS send error: " . $e->getMessage());
            $errors['general'] = 'An error occurred while processing SMS. Please try again.';
        }
    }
}

// Get data for form options
try {
    $db = Database::getInstance();
    
    // Get departments
    $departments = $db->executeQuery(
        "SELECT id, name, department_type FROM departments WHERE is_active = 1 ORDER BY name"
    )->fetchAll();
    
    // Get SMS templates
    $templates = $db->executeQuery(
        "SELECT id, name, category, message FROM sms_templates WHERE is_active = 1 ORDER BY category, name"
    )->fetchAll();
    
    // Get preview recipients if form is submitted for preview
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'preview') {
        $preview_recipients = getSMSRecipients($form_data);
    }
    
    // Get SMS balance/stats
    $sms_stats = [
        'balance' => 1000, // This would come from SMS provider API
        'sent_today' => $db->executeQuery(
            "SELECT COUNT(*) FROM sms_individual WHERE DATE(sent_at) = CURDATE() AND status = 'sent'"
        )->fetchColumn(),
        'sent_this_month' => $db->executeQuery(
            "SELECT COUNT(*) FROM sms_individual WHERE MONTH(sent_at) = MONTH(CURDATE()) AND YEAR(sent_at) = YEAR(CURDATE()) AND status = 'sent'"
        )->fetchColumn()
    ];
    
} catch (Exception $e) {
    error_log("SMS form data error: " . $e->getMessage());
    $departments = [];
    $templates = [];
    $sms_stats = ['balance' =>  0, 'sent_today' => 0, 'sent_this_month' => 0];
    $errors['general'] = 'An error occurred while loading form data. Please try again later.';
}
