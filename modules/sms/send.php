<?php
/**
 * Send SMS/Communication Page
 * Deliverance Church Management System
 * 
 * Send SMS, WhatsApp, and Email communications to members
 */

// Include necessary files
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Check authentication and permissions
requireLogin();
if (!hasPermission('sms') && !in_array($_SESSION['user_role'], ['administrator', 'pastor', 'secretary'])) {
    setFlashMessage('error', 'You do not have permission to send communications.');
    redirect(BASE_URL . 'modules/dashboard/');
}

// Page settings
$page_title = 'Send Communication';
$page_icon = 'fas fa-paper-plane';
$breadcrumb = [
    ['title' => 'Communication Center', 'url' => BASE_URL . 'modules/sms/'],
    ['title' => 'Send Communication']
];

$db = Database::getInstance();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_communication'])) {
    try {
        // Validate input
        $communicationType = sanitizeInput($_POST['communication_type'] ?? 'sms');
        $recipientType = sanitizeInput($_POST['recipient_type'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $templateId = !empty($_POST['template_id']) ? (int)$_POST['template_id'] : null;
        $scheduledTime = !empty($_POST['scheduled_time']) ? $_POST['scheduled_time'] : null;
        
        // Validation
        if (empty($recipientType)) {
            throw new Exception('Please select recipients');
        }
        
        if (empty($message)) {
            throw new Exception('Message cannot be empty');
        }
        
        if (strlen($message) > 160 && $communicationType === 'sms') {
            throw new Exception('SMS message cannot exceed 160 characters');
        }
        
        // Get recipients based on type
        $recipients = [];
        $recipientFilter = [];
        
        switch ($recipientType) {
            case 'all_members':
                $stmt = $db->executeQuery("
                    SELECT id, first_name, last_name, phone, email 
                    FROM members 
                    WHERE membership_status = 'active' 
                    AND (phone IS NOT NULL AND phone != '')
                ");
                $recipients = $stmt->fetchAll();
                $recipientFilter['type'] = 'all_members';
                break;
                
            case 'department':
                $departmentId = (int)($_POST['department_id'] ?? 0);
                if ($departmentId) {
                    $stmt = $db->executeQuery("
                        SELECT m.id, m.first_name, m.last_name, m.phone, m.email 
                        FROM members m
                        INNER JOIN member_departments md ON m.id = md.member_id
                        WHERE md.department_id = ? AND md.is_active = 1 
                        AND m.membership_status = 'active'
                        AND (m.phone IS NOT NULL AND m.phone != '')
                    ", [$departmentId]);
                    $recipients = $stmt->fetchAll();
                    $recipientFilter['type'] = 'department';
                    $recipientFilter['department_id'] = $departmentId;
                }
                break;
                
            case 'age_group':
                $ageGroup = sanitizeInput($_POST['age_group'] ?? '');
                $ageCondition = '';
                switch ($ageGroup) {
                    case 'children':
                        $ageCondition = 'AND m.age <= 12';
                        break;
                    case 'teens':
                        $ageCondition = 'AND m.age BETWEEN 13 AND 17';
                        break;
                    case 'youth':
                        $ageCondition = 'AND m.age BETWEEN 18 AND 35';
                        break;
                    case 'adults':
                        $ageCondition = 'AND m.age BETWEEN 36 AND 59';
                        break;
                    case 'seniors':
                        $ageCondition = 'AND m.age >= 60';
                        break;
                }
                
                if ($ageCondition) {
                    $stmt = $db->executeQuery("
                        SELECT id, first_name, last_name, phone, email 
                        FROM members m
                        WHERE membership_status = 'active' 
                        AND (phone IS NOT NULL AND phone != '')
                        {$ageCondition}
                    ");
                    $recipients = $stmt->fetchAll();
                    $recipientFilter['type'] = 'age_group';
                    $recipientFilter['age_group'] = $ageGroup;
                }
                break;
                
            case 'custom_list':
                $memberIds = array_map('intval', $_POST['member_ids'] ?? []);
                if (!empty($memberIds)) {
                    $placeholders = str_repeat('?,', count($memberIds) - 1) . '?';
                    $stmt = $db->executeQuery("
                        SELECT id, first_name, last_name, phone, email 
                        FROM members 
                        WHERE id IN ({$placeholders})
                        AND membership_status = 'active'
                        AND (phone IS NOT NULL AND phone != '')
                    ", $memberIds);
                    $recipients = $stmt->fetchAll();
                    $recipientFilter['type'] = 'custom_list';
                    $recipientFilter['member_ids'] = $memberIds;
                }
                break;
        }
        
        if (empty($recipients)) {
            throw new Exception('No valid recipients found for the selected criteria');
        }
        
        // Check SMS balance if sending SMS
        if ($communicationType === 'sms') {
            $churchInfo = $db->executeQuery("SELECT sms_balance FROM church_info WHERE id = 1")->fetch();
            $smsBalance = $churchInfo['sms_balance'] ?? 0;
            $totalCost = count($recipients) * SMS_COST_PER_SMS;
            
            if ($smsBalance < $totalCost) {
                throw new Exception('Insufficient SMS balance. Required: ' . formatCurrency($totalCost) . ', Available: ' . formatCurrency($smsBalance));
            }
        }
        
        // Personalize message function
        function personalizeMessage($template, $member) {
            $replacements = [
                '{first_name}' => $member['first_name'],
                '{last_name}' => $member['last_name'],
                '{full_name}' => $member['first_name'] . ' ' . $member['last_name'],
                '{church_name}' => 'Deliverance Church' // Get from church_info
            ];
            
            return str_replace(array_keys($replacements), array_values($replacements), $template);
        }
        
        // Generate batch ID
        $batchId = 'BATCH_' . date('YmdHis') . '_' . rand(1000, 9999);
        
        // Insert into communication history
        $historyData = [
            'batch_id' => $batchId,
            'recipient_type' => $recipientType,
            'recipient_filter' => json_encode($recipientFilter),
            'message' => $message,
            'total_recipients' => count($recipients),
            'sent_count' => 0,
            'failed_count' => 0,
            'cost' => $communicationType === 'sms' ? count($recipients) * SMS_COST_PER_SMS : 0,
            'status' => $scheduledTime ? 'scheduled' : 'pending',
            'sent_by' => $_SESSION['user_id']
        ];
        
        if ($scheduledTime) {
            $historyData['scheduled_at'] = $scheduledTime;
        }
        
        $historyId = insertRecord('sms_history', $historyData);
        
        if (!$historyId) {
            throw new Exception('Failed to create communication record');
        }
        
        // Insert individual recipient records
        $successCount = 0;
        foreach ($recipients as $recipient) {
            $personalizedMessage = personalizeMessage($message, $recipient);
            
            $individualData = [
                'batch_id' => $batchId,
                'recipient_phone' => formatPhoneNumber($recipient['phone']),
                'recipient_name' => $recipient['first_name'] . ' ' . $recipient['last_name'],
                'member_id' => $recipient['id'],
                'message' => $personalizedMessage,
                'status' => $scheduledTime ? 'scheduled' : 'pending',
                'cost' => $communicationType === 'sms' ? SMS_COST_PER_SMS : 0
            ];
            
            if (insertRecord('sms_individual', $individualData)) {
                $successCount++;
            }
        }
        
        if ($successCount === 0) {
            throw new Exception('Failed to queue messages for sending');
        }
        
        // If not scheduled, process immediately
        if (!$scheduledTime) {
            // Include SMS sending class (will be created separately)
            require_once '../../includes/SMSSender.php';
            
            $smsSender = new SMSSender();
            $result = $smsSender->processBatch($batchId);
            
            if ($result['success']) {
                setFlashMessage('success', "Communication sent successfully! {$result['sent_count']} messages sent, {$result['failed_count']} failed.");
            } else {
                setFlashMessage('warning', "Communication queued but some messages failed to send. Check communication history for details.");
            }
        } else {
            setFlashMessage('success', "Communication scheduled successfully for " . formatDisplayDateTime($scheduledTime) . ". {$successCount} recipients will receive the message.");
        }
        
        // Log activity
        logActivity(
            'Communication sent',
            'sms_history',
            $historyId,
            null,
            [
                'type' => $communicationType,
                'recipients' => $successCount,
                'batch_id' => $batchId
            ]
        );
        
        redirect(BASE_URL . 'modules/sms/view_communication.php?id=' . $historyId);
        
    } catch (Exception $e) {
        setFlashMessage('error', 'Error sending communication: ' . $e->getMessage());
        error_log("Communication sending error: " . $e->getMessage());
    }
}

// Get data for form
try {
    // Get departments
    $departments = $db->executeQuery("
        SELECT id, name, description 
        FROM departments 
        WHERE is_active = 1 
        ORDER BY name
    ")->fetchAll();
    
    // Get SMS templates
    $templates = $db->executeQuery("
        SELECT id, name, category, message 
        FROM sms_templates 
        WHERE is_active = 1 
        ORDER BY category, name
    ")->fetchAll();
    
    // Get member count for estimation
    $totalMembers = $db->executeQuery("
        SELECT COUNT(*) as count 
        FROM members 
        WHERE membership_status = 'active' 
        AND (phone IS NOT NULL AND phone != '')
    ")->fetchColumn();
    
    // Get SMS balance
    $churchInfo = $db->executeQuery("SELECT sms_balance FROM church_info WHERE id = 1")->fetch();
    $smsBalance = $churchInfo['sms_balance'] ?? 0;
    
} catch (Exception $e) {
    error_log("Error loading send communication data: " . $e->getMessage());
}

// Include header
include_once '../../includes/header.php';
?>

<!-- Communication Type Selection -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-header bg-church-blue text-white">
                <h6 class="mb-0">
                    <i class="fas fa-paper-plane me-2"></i>Send Communication
                </h6>
            </div>
            <div class="card-body">
                <!-- Communication Type Tabs -->
                <ul class="nav nav-pills mb-4" id="communicationTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="sms-tab" data-bs-toggle="pill" data-bs-target="#sms-panel" 
                                type="button" role="tab">
                            <i class="fas fa-sms me-2"></i>SMS
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link disabled" id="email-tab" data-bs-toggle="pill" data-bs-target="#email-panel" 
                                type="button" role="tab" title="Coming Soon">
                            <i class="fas fa-envelope me-2"></i>Email <small>(Soon)</small>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link disabled" id="whatsapp-tab" data-bs-toggle="pill" data-bs-target="#whatsapp-panel" 
                                type="button" role="tab" title="Coming Soon">
                            <i class="fab fa-whatsapp me-2"></i>WhatsApp <small>(Soon)</small>
                        </button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="communicationTabContent">
                    <!-- SMS Panel -->
                    <div class="tab-pane fade show active" id="sms-panel" role="tabpanel">
                        <form method="POST" action="" id="smsForm" class="needs-validation" novalidate>
                            <input type="hidden" name="communication_type" value="sms">
                            <input type="hidden" name="send_communication" value="1">
                            
                            <div class="row">
                                <!-- Recipients Selection -->
                                <div class="col-md-6">
                                    <div class="card border-primary h-100">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0">
                                                <i class="fas fa-users me-2"></i>Select Recipients
                                            </h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label for="recipient_type" class="form-label">Recipient Type <span class="text-danger">*</span></label>
                                                <select class="form-select" id="recipient_type" name="recipient_type" required>
                                                    <option value="">Choose recipients...</option>
                                                    <option value="all_members">All Active Members (<?php echo number_format($totalMembers); ?>)</option>
                                                    <option value="department">By Department</option>
                                                    <option value="age_group">By Age Group</option>
                                                    <option value="custom_list">Custom Selection</option>
                                                </select>
                                                <div class="invalid-feedback">Please select recipient type</div>
                                            </div>

                                            <!-- Department Selection (hidden by default) -->
                                            <div class="mb-3 d-none" id="department_selection">
                                                <label for="department_id" class="form-label">Select Department</label>
                                                <select class="form-select" id="department_id" name="department_id">
                                                    <option value="">Choose department...</option>
                                                    <?php foreach ($departments as $dept): ?>
                                                        <option value="<?php echo $dept['id']; ?>">
                                                            <?php echo htmlspecialchars($dept['name']); ?>
                                                            <?php if ($dept['description']): ?>
                                                                - <?php echo htmlspecialchars($dept['description']); ?>
                                                            <?php endif; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <!-- Age Group Selection (hidden by default) -->
                                            <div class="mb-3 d-none" id="age_group_selection">
                                                <label for="age_group" class="form-label">Select Age Group</label>
                                                <select class="form-select" id="age_group" name="age_group">
                                                    <option value="">Choose age group...</option>
                                                    <option value="children">Children (0-12 years)</option>
                                                    <option value="teens">Teenagers (13-17 years)</option>
                                                    <option value="youth">Youth (18-35 years)</option>
                                                    <option value="adults">Adults (36-59 years)</option>
                                                    <option value="seniors">Seniors (60+ years)</option>
                                                </select>
                                            </div>

                                            <!-- Custom Member Selection (hidden by default) -->
                                            <div class="mb-3 d-none" id="custom_selection">
                                                <label class="form-label">Select Members</label>
                                                <div class="border rounded p-2" style="max-height: 200px; overflow-y: auto;">
                                                    <div class="mb-2">
                                                        <input type="text" class="form-control form-control-sm" 
                                                               placeholder="Search members..." id="member_search">
                                                    </div>
                                                    <div id="member_list">
                                                        <!-- Members will be loaded here -->
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Recipient Count -->
                                            <div class="alert alert-info" id="recipient_count">
                                                <i class="fas fa-info-circle me-2"></i>
                                                <span id="count_text">Select recipients to see count and cost estimate</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Message Composition -->
                                <div class="col-md-6">
                                    <div class="card border-success h-100">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0">
                                                <i class="fas fa-edit me-2"></i>Compose Message
                                            </h6>
                                        </div>
                                        <div class="card-body">
                                            <!-- Template Selection -->
                                            <div class="mb-3">
                                                <label for="template_id" class="form-label">Use Template (Optional)</label>
                                                <select class="form-select" id="template_id" name="template_id">
                                                    <option value="">Start with blank message...</option>
                                                    <?php 
                                                    $currentCategory = '';
                                                    foreach ($templates as $template): 
                                                        if ($currentCategory !== $template['category']):
                                                            if ($currentCategory !== '') echo '</optgroup>';
                                                            echo '<optgroup label="' . htmlspecialchars(ucfirst(str_replace('_', ' ', $template['category']))) . '">';
                                                            $currentCategory = $template['category'];
                                                        endif;
                                                    ?>
                                                        <option value="<?php echo $template['id']; ?>" 
                                                                data-message="<?php echo htmlspecialchars($template['message']); ?>">
                                                            <?php echo htmlspecialchars($template['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                    <?php if ($currentCategory !== '') echo '</optgroup>'; ?>
                                                </select>
                                            </div>

                                            <!-- Message Text -->
                                            <div class="mb-3">
                                                <label for="message" class="form-label">
                                                    Message <span class="text-danger">*</span>
                                                    <small class="text-muted">(<span id="char_count">0</span>/160 characters)</small>
                                                </label>
                                                <textarea class="form-control" id="message" name="message" rows="6" 
                                                          maxlength="160" required 
                                                          placeholder="Type your message here..."></textarea>
                                                <div class="invalid-feedback">Please enter a message</div>
                                                <div class="form-text">
                                                    Use placeholders: {first_name}, {last_name}, {church_name}
                                                </div>
                                            </div>

                                            <!-- Scheduling -->
                                            <div class="mb-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="schedule_message">
                                                    <label class="form-check-label" for="schedule_message">
                                                        Schedule for later
                                                    </label>
                                                </div>
                                            </div>

                                            <div class="mb-3 d-none" id="schedule_time_group">
                                                <label for="scheduled_time" class="form-label">Schedule Time</label>
                                                <input type="datetime-local" class="form-control" id="scheduled_time" 
                                                       name="scheduled_time" min="<?php echo date('Y-m-d\TH:i'); ?>">
                                            </div>

                                            <!-- Preview -->
                                            <div class="mb-3">
                                                <label class="form-label">Message Preview</label>
                                                <div class="border rounded p-3 bg-light" id="message_preview">
                                                    <div class="text-muted">Preview will appear here...</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SMS Balance & Cost -->
                            <div class="row mt-4">
                                <div class="col-12">
                                    <div class="card border-warning">
                                        <div class="card-body">
                                            <div class="row align-items-center">
                                                <div class="col-md-3">
                                                    <div class="text-center">
                                                        <div class="h5 mb-1 text-warning">
                                                            <?php echo formatCurrency($smsBalance); ?>
                                                        </div>
                                                        <div class="small text-muted">Available Balance</div>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="text-center">
                                                        <div class="h5 mb-1 text-info">
                                                            <?php echo formatCurrency(SMS_COST_PER_SMS); ?>
                                                        </div>
                                                        <div class="small text-muted">Cost per SMS</div>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="text-center">
                                                        <div class="h5 mb-1 text-church-red" id="total_cost">
                                                            <?php echo formatCurrency(0); ?>
                                                        </div>
                                                        <div class="small text-muted">Total Cost</div>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="text-center">
                                                        <div class="h5 mb-1 text-success" id="remaining_balance">
                                                            <?php echo formatCurrency($smsBalance); ?>
                                                        </div>
                                                        <div class="small text-muted">Remaining Balance</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="row mt-4">
                                <div class="col-12">
                                    <div class="d-flex justify-content-between">
                                        <a href="index.php" class="btn btn-secondary">
                                            <i class="fas fa-arrow-left me-1"></i>Back to Communication Center
                                        </a>
                                        <div>
                                            <button type="button" class="btn btn-info me-2" id="preview_btn" disabled>
                                                <i class="fas fa-eye me-1"></i>Preview
                                            </button>
                                            <button type="submit" class="btn btn-church-primary" id="send_btn" disabled>
                                                <i class="fas fa-paper-plane me-1"></i>Send Communication
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Email Panel (Placeholder) -->
                    <div class="tab-pane fade" id="email-panel" role="tabpanel">
                        <div class="text-center py-5">
                            <i class="fas fa-envelope fa-4x text-muted mb-3"></i>
                            <h4 class="text-muted">Email Integration Coming Soon</h4>
                            <p class="text-muted">Email communication feature will be available in a future update.</p>
                        </div>
                    </div>

                    <!-- WhatsApp Panel (Placeholder) -->
                    <div class="tab-pane fade" id="whatsapp-panel" role="tabpanel">
                        <div class="text-center py-5">
                            <i class="fab fa-whatsapp fa-4x text-success mb-3"></i>
                            <h4 class="text-muted">WhatsApp Integration Coming Soon</h4>
                            <p class="text-muted">WhatsApp communication feature will be available in a future update.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const recipientTypeSelect = document.getElementById('recipient_type');
    const messageTextarea = document.getElementById('message');
    const templateSelect = document.getElementById('template_id');
    const charCountSpan = document.getElementById('char_count');
    const previewDiv = document.getElementById('message_preview');
    const scheduleCheckbox = document.getElementById('schedule_message');
    const scheduleTimeGroup = document.getElementById('schedule_time_group');
    const sendBtn = document.getElementById('send_btn');
    const previewBtn = document.getElementById('preview_btn');
    
    let selectedRecipients = [];
    const smsBalance = <?php echo $smsBalance; ?>;
    const costPerSMS = <?php echo SMS_COST_PER_SMS; ?>;

    // Handle recipient type change
    recipientTypeSelect.addEventListener('change', function() {
        const type = this.value;
        
        // Hide all selection groups
        document.getElementById('department_selection').classList.add('d-none');
        document.getElementById('age_group_selection').classList.add('d-none');
        document.getElementById('custom_selection').classList.add('d-none');
        
        // Show relevant selection group
        if (type === 'department') {
            document.getElementById('department_selection').classList.remove('d-none');
        } else if (type === 'age_group') {
            document.getElementById('age_group_selection').classList.remove('d-none');
        } else if (type === 'custom_list') {
            document.getElementById('custom_selection').classList.remove('d-none');
            loadMembers();
        }
        
        updateRecipientCount();
        validateForm();
    });

    // Handle template selection
    templateSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption.dataset.message) {
            messageTextarea.value = selectedOption.dataset.message;
            updateCharCount();
            updatePreview();
        }
    });

    // Handle message input
    messageTextarea.addEventListener('input', function() {
        updateCharCount();
        updatePreview();
        validateForm();
    });

    // Handle scheduling
    scheduleCheckbox.addEventListener('change', function() {
        if (this.checked) {
            scheduleTimeGroup.classList.remove('d-none');
            document.getElementById('scheduled_time').required = true;
        } else {
            scheduleTimeGroup.classList.add('d-none');
            document.getElementById('scheduled_time').required = false;
        }
    });

    // Update character count
    function updateCharCount() {
        const count = messageTextarea.value.length;
        charCountSpan.textContent = count;
        
        if (count > 160) {
            charCountSpan.parentElement.classList.add('text-danger');
        } else if (count > 140) {
            charCountSpan.parentElement.classList.add('text-warning');
            charCountSpan.parentElement.classList.remove('text-danger');
        } else {
            charCountSpan.parentElement.classList.remove('text-danger', 'text-warning');
        }
    }

    // Update message preview
    function updatePreview() {
        let preview = messageTextarea.value;
        if (preview) {
            // Replace placeholders with sample data
            preview = preview.replace(/{first_name}/g, 'John')
                           .replace(/{last_name}/g, 'Doe')
                           .replace(/{full_name}/g, 'John Doe')
                           .replace(/{church_name}/g, 'Deliverance Church');
            previewDiv.innerHTML = `<div class="text-dark">${preview}</div>`;
        } else {
            previewDiv.innerHTML = '<div class="text-muted">Preview will appear here...</div>';
        }
    }

    // Load members for custom selection
    function loadMembers() {
        fetch('ajax/get_members.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const memberList = document.getElementById('member_list');
                    memberList.innerHTML = '';
                    
                    data.members.forEach(member => {
                        const checkbox = `
                            <div class="form-check">
                                <input class="form-check-input member-checkbox" type="checkbox" 
                                       value="${member.id}" id="member_${member.id}">
                                <label class="form-check-label" for="member_${member.id}">
                                    ${member.first_name} ${member.last_name}
                                    <small class="text-muted">(${member.phone})</small>
                                </label>
                            </div>
                        `;
                        memberList.insertAdjacentHTML('beforeend', checkbox);
                    });
                    
                    // Add event listeners to checkboxes
                    document.querySelectorAll('.member-checkbox').forEach(checkbox => {
                        checkbox.addEventListener('change', updateRecipientCount);
                    });
                }
            })
            .catch(error => {
                console.error('Error loading members:', error);
                ChurchCMS.showToast('Error loading members list', 'error');
            });
    }

    // Update recipient count and cost
    function updateRecipientCount() {
        const type = recipientTypeSelect.value;
        let count = 0;
        
        if (type === 'all_members') {
            count = <?php echo $totalMembers; ?>;
        } else if (type === 'custom_list') {
            count = document.querySelectorAll('.member-checkbox:checked').length;
        } else {
            // For department and age_group, we'll need to make an AJAX call
            if (type && (type === 'department' || type === 'age_group')) {
                // This would be implemented to get actual count
                count = 0; // Placeholder
            }
        }
        
        const totalCost = count * costPerSMS;
        const remainingBalance = smsBalance - totalCost;
        
        document.getElementById('count_text').textContent = 
            count > 0 ? `${count} recipients selected` : 'Select recipients to see count and cost estimate';
        
        document.getElementById('total_cost').textContent = ChurchCMS.formatCurrency(totalCost);
        document.getElementById('remaining_balance').textContent = ChurchCMS.formatCurrency(remainingBalance);
        
        // Check if sufficient balance
        if (remainingBalance < 0) {
            document.getElementById('remaining_balance').className = 'h5 mb-1 text-danger';
            sendBtn.disabled = true;
            sendBtn.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i>Insufficient Balance';
        } else {
            document.getElementById('remaining_balance').className = 'h5 mb-1 text-success';
            validateForm();
        }
    }

    // Validate form
    function validateForm() {
        const hasRecipients = recipientTypeSelect.value !== '';
        const hasMessage = messageTextarea.value.trim() !== '';
        const validLength = messageTextarea.value.length <= 160;
        const hasBalance = (smsBalance >= (selectedRecipients.length * costPerSMS));
        
        const isValid = hasRecipients && hasMessage && validLength && hasBalance;
        
        sendBtn.disabled = !isValid;
        previewBtn.disabled = !hasMessage;
        
        if (isValid) {
            sendBtn.innerHTML = scheduleCheckbox.checked ? 
                '<i class="fas fa-clock me-1"></i>Schedule Communication' : 
                '<i class="fas fa-paper-plane me-1"></i>Send Communication';
        } else {
            sendBtn.innerHTML = '<i class="fas fa-paper-plane me-1"></i>Send Communication';
        }
    }

    // Member search functionality
    const memberSearch = document.getElementById('member_search');
    if (memberSearch) {
        memberSearch.addEventListener('input', ChurchCMS.debounce(function() {
            const searchTerm = this.value.toLowerCase();
            const memberCheckboxes = document.querySelectorAll('#member_list .form-check');
            
            memberCheckboxes.forEach(checkbox => {
                const label = checkbox.querySelector('label').textContent.toLowerCase();
                if (label.includes(searchTerm)) {
                    checkbox.style.display = '';
                } else {
                    checkbox.style.display = 'none';
                }
            });
        }, 300));
    }

    // Form submission
    document.getElementById('smsForm').addEventListener('submit', function(e) {
        if (!this.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        } else {
            ChurchCMS.showLoading('Sending communication...');
        }
        this.classList.add('was-validated');
    });

    // Initialize
    updateCharCount();
    updatePreview();
    validateForm();
});
</script>

<?php include_once '../../includes/footer.php'; ?>