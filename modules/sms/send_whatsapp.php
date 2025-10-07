<?php
/**
 * Send WhatsApp Messages Page
 * Deliverance Church Management System
 * 
 * Send WhatsApp messages to individuals, groups, and custom numbers
 */

// Include necessary files
require_once '../../config/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/WhatsAppSender.php';

// Check authentication and permissions
requireLogin();
if (!hasPermission('sms') && !in_array($_SESSION['user_role'], ['administrator', 'pastor', 'secretary'])) {
    setFlashMessage('error', 'You do not have permission to send WhatsApp messages.');
    redirect(BASE_URL . 'modules/dashboard/');
}

// Page settings
$page_title = 'Send WhatsApp Message';
$page_icon = 'fab fa-whatsapp';
$breadcrumb = [
    ['title' => 'Communication Center', 'url' => BASE_URL . 'modules/sms/'],
    ['title' => 'Send WhatsApp']
];

$db = Database::getInstance();
$whatsappSender = new WhatsAppSender();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_whatsapp'])) {
    try {
        $recipientType = sanitizeInput($_POST['recipient_type'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $customNumbers = trim($_POST['custom_numbers'] ?? '');
        $selectedMembers = $_POST['selected_members'] ?? [];
        $groupId = sanitizeInput($_POST['group_id'] ?? '');
        $sendToGroup = isset($_POST['send_to_group']);
        
        // Validation
        if (empty($message)) {
            throw new Exception('Message cannot be empty');
        }
        
        $recipients = [];
        
        // Handle group sending
        if ($sendToGroup && !empty($groupId)) {
            $result = $whatsappSender->sendGroupMessage($groupId, $message);
            
            if ($result['success']) {
                setFlashMessage('success', 'WhatsApp message sent to group successfully!');
                logActivity('WhatsApp message sent to group', 'whatsapp_groups', $groupId);
            } else {
                throw new Exception('Failed to send to group: ' . ($result['error'] ?? 'Unknown error'));
            }
            
            redirect(BASE_URL . 'modules/sms/whatsapp_history.php');
        }
        
        // Get recipients from members
        if (!empty($selectedMembers)) {
            $placeholders = str_repeat('?,', count($selectedMembers) - 1) . '?';
            $stmt = $db->executeQuery("
                SELECT id, first_name, last_name, phone 
                FROM members 
                WHERE id IN ($placeholders)
                AND (phone IS NOT NULL AND phone != '')
            ", $selectedMembers);
            
            while ($member = $stmt->fetch()) {
                $recipients[] = [
                    'id' => $member['id'],
                    'name' => $member['first_name'] . ' ' . $member['last_name'],
                    'first_name' => $member['first_name'],
                    'last_name' => $member['last_name'],
                    'phone' => $member['phone']
                ];
            }
        }
        
        // Add custom numbers
        if (!empty($customNumbers)) {
            $numbers = array_map('trim', explode(',', $customNumbers));
            foreach ($numbers as $number) {
                if (!empty($number)) {
                    $validation = $whatsappSender->validateNumber($number);
                    if ($validation['valid']) {
                        $recipients[] = [
                            'id' => null,
                            'name' => 'Custom: ' . $number,
                            'first_name' => 'Member',
                            'last_name' => '',
                            'phone' => $validation['formatted']
                        ];
                    }
                }
            }
        }
        
        if (empty($recipients)) {
            throw new Exception('No valid recipients found. Please select members or add phone numbers.');
        }
        
        // Send WhatsApp messages
        $result = $whatsappSender->sendMessage($recipients, $message);
        
        // Save to history
        $batchId = 'WA_' . date('YmdHis') . '_' . rand(1000, 9999);
        
        $historyData = [
            'batch_id' => $batchId,
            'communication_type' => 'whatsapp',
            'recipient_type' => $recipientType ?: 'custom',
            'message' => $message,
            'total_recipients' => count($recipients),
            'sent_count' => $result['sent_count'],
            'failed_count' => $result['failed_count'],
            'status' => $result['success'] ? 'completed' : 'failed',
            'sent_by' => $_SESSION['user_id'],
            'sent_at' => date('Y-m-d H:i:s')
        ];
        
        $historyId = insertRecord('whatsapp_history', $historyData);
        
        // Save individual results
        foreach ($result['results'] as $individualResult) {
            $individualData = [
                'batch_id' => $batchId,
                'recipient_phone' => $individualResult['phone'],
                'recipient_name' => $individualResult['name'],
                'member_id' => $recipients[array_search($individualResult['phone'], array_column($recipients, 'phone'))]['id'] ?? null,
                'message' => $message,
                'status' => $individualResult['success'] ? 'sent' : 'failed',
                'provider_message_id' => $individualResult['message_id'] ?? null,
                'error_message' => $individualResult['error'] ?? null,
                'sent_at' => $individualResult['success'] ? date('Y-m-d H:i:s') : null
            ];
            
            insertRecord('whatsapp_individual', $individualData);
        }
        
        // Log activity
        logActivity(
            'WhatsApp messages sent',
            'whatsapp_history',
            $historyId,
            null,
            [
                'recipients' => count($recipients),
                'sent' => $result['sent_count'],
                'failed' => $result['failed_count']
            ]
        );
        
        if ($result['success']) {
            setFlashMessage('success', "WhatsApp messages sent successfully! {$result['sent_count']} sent, {$result['failed_count']} failed.");
        } else {
            setFlashMessage('warning', "Some messages failed to send. {$result['sent_count']} sent, {$result['failed_count']} failed.");
        }
        
        redirect(BASE_URL . 'modules/sms/whatsapp_history.php');
        
    } catch (Exception $e) {
        setFlashMessage('error', 'Error sending WhatsApp: ' . $e->getMessage());
        error_log("WhatsApp sending error: " . $e->getMessage());
    }
}

// Get data for form
$departments = $db->executeQuery("
    SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name
")->fetchAll();

$whatsappGroups = $whatsappSender->getGroups();

// Include header
include_once '../../includes/header.php';
?>

<div class="card shadow">
    <div class="card-header bg-success text-white">
        <h5 class="mb-0">
            <i class="fab fa-whatsapp me-2"></i>Send WhatsApp Message
        </h5>
    </div>
    <div class="card-body">
        <form method="POST" action="" id="whatsappForm" class="needs-validation" novalidate>
            <input type="hidden" name="send_whatsapp" value="1">
            
            <div class="row">
                <!-- Recipient Selection -->
                <div class="col-md-6">
                    <div class="card border-success h-100">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">
                                <i class="fas fa-users me-2"></i>Select Recipients
                            </h6>
                        </div>
                        <div class="card-body">
                            <!-- Send to Group Option -->
                            <div class="mb-4">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="send_to_group" name="send_to_group">
                                    <label class="form-check-label fw-bold" for="send_to_group">
                                        <i class="fas fa-users me-1"></i>Send to WhatsApp Group
                                    </label>
                                </div>
                            </div>
                            
                            <!-- WhatsApp Group Selection -->
                            <div id="group_selection" class="mb-3 d-none">
                                <label for="group_id" class="form-label">Select WhatsApp Group</label>
                                <select class="form-select" id="group_id" name="group_id">
                                    <option value="">Choose group...</option>
                                    <?php foreach ($whatsappGroups as $group): ?>
                                        <option value="<?php echo $group['group_id']; ?>">
                                            <?php echo htmlspecialchars($group['name']); ?>
                                            (<?php echo htmlspecialchars($group['member_count'] ?? 0); ?> members)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="mt-2">
                                    <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#addGroupModal">
                                        <i class="fas fa-plus me-1"></i>Add New Group
                                    </button>
                                </div>
                            </div>
                            
                            <div id="individual_selection">
                                <!-- Select from Members -->
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-user-check me-1"></i>Select from Members
                                    </label>
                                    <div class="input-group mb-2">
                                        <input type="text" class="form-control" id="member_search" 
                                               placeholder="Search members by name or phone...">
                                        <button class="btn btn-outline-secondary" type="button" id="load_members_btn">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                    <div class="border rounded p-2" style="max-height: 200px; overflow-y: auto;" id="members_list">
                                        <div class="text-muted text-center py-3">
                                            Click search to load members
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="text-center mb-3">
                                    <strong>OR</strong>
                                </div>
                                
                                <!-- Add Custom Phone Numbers -->
                                <div class="mb-3">
                                    <label for="custom_numbers" class="form-label">
                                        <i class="fas fa-phone me-1"></i>Add Custom Phone Numbers
                                    </label>
                                    <textarea class="form-control" id="custom_numbers" name="custom_numbers" rows="3"
                                              placeholder="Enter phone numbers separated by commas&#10;Example: 0745600377, 0712345678, +254798765432"></textarea>
                                    <div class="form-text">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Enter Kenyan numbers (0712..., 0745...) or international format (+254...)
                                    </div>
                                </div>
                                
                                <!-- Selected Recipients Display -->
                                <div class="alert alert-info" id="recipient_summary">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <span id="recipient_count_text">No recipients selected</span>
                                </div>
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
                            <!-- WhatsApp Business Number -->
                            <div class="alert alert-success mb-3">
                                <i class="fab fa-whatsapp me-2"></i>
                                <strong>Sending from:</strong> 0745 600 377
                            </div>
                            
                            <!-- Message Text -->
                            <div class="mb-3">
                                <label for="message" class="form-label">
                                    Message <span class="text-danger">*</span>
                                    <small class="text-muted">(<span id="char_count">0</span> characters)</small>
                                </label>
                                <textarea class="form-control" id="message" name="message" rows="8" 
                                          required placeholder="Type your WhatsApp message here..."></textarea>
                                <div class="invalid-feedback">Please enter a message</div>
                                <div class="form-text">
                                    <i class="fas fa-lightbulb me-1"></i>
                                    Use placeholders: {first_name}, {last_name}, {church_name}
                                    <br>
                                    <i class="fas fa-check-circle text-success me-1"></i>
                                    WhatsApp supports emojis and longer messages!
                                </div>
                            </div>
                            
                            <!-- Media Attachment (Future) -->
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-paperclip me-1"></i>Attach Media (Coming Soon)
                                </label>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary disabled">
                                        <i class="fas fa-image me-1"></i>Image
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary disabled">
                                        <i class="fas fa-video me-1"></i>Video
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary disabled">
                                        <i class="fas fa-file-pdf me-1"></i>Document
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Message Preview -->
                            <div class="mb-3">
                                <label class="form-label">WhatsApp Preview</label>
                                <div class="whatsapp-preview">
                                    <div class="whatsapp-message">
                                        <div class="message-bubble" id="message_preview">
                                            <div class="text-muted">Preview will appear here...</div>
                                        </div>
                                        <div class="message-time">
                                            <i class="fas fa-check-double text-primary"></i>
                                            <span><?php echo date('H:i'); ?></span>
                                        </div>
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
                            <i class="fas fa-arrow-left me-1"></i>Back
                        </a>
                        <div>
                            <button type="button" class="btn btn-info me-2" id="test_send_btn">
                                <i class="fas fa-vial me-1"></i>Send Test to My Number
                            </button>
                            <button type="submit" class="btn btn-success" id="send_btn" disabled>
                                <i class="fab fa-whatsapp me-1"></i>Send WhatsApp Messages
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Add WhatsApp Group Modal -->
<div class="modal fade" id="addGroupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="fas fa-users me-2"></i>Add WhatsApp Group
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addGroupForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="group_name" class="form-label">Group Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="group_name" required
                               placeholder="e.g., Church Elders">
                    </div>
                    <div class="mb-3">
                        <label for="group_whatsapp_id" class="form-label">WhatsApp Group ID</label>
                        <input type="text" class="form-control" id="group_whatsapp_id"
                               placeholder="Group invite link or ID">
                        <div class="form-text">
                            Optional: If you have the group ID from WhatsApp Business API
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="group_description" class="form-label">Description</label>
                        <textarea class="form-control" id="group_description" rows="2"
                                  placeholder="Brief description of this group"></textarea>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <small>Note: For direct API group messaging, you'll need WhatsApp Business API with group permissions.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-1"></i>Save Group
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* WhatsApp Preview Styling */
.whatsapp-preview {
    background: linear-gradient(135deg, #075e54 0%, #128c7e 100%);
    padding: 20px;
    border-radius: 10px;
    min-height: 150px;
}

.whatsapp-message {
    max-width: 70%;
    margin-left: auto;
}

.message-bubble {
    background: #dcf8c6;
    padding: 10px 15px;
    border-radius: 10px;
    position: relative;
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
    word-wrap: break-word;
    color: #000;
}

.message-bubble:after {
    content: '';
    position: absolute;
    right: -10px;
    top: 10px;
    width: 0;
    height: 0;
    border-style: solid;
    border-width: 0 0 10px 10px;
    border-color: transparent transparent #dcf8c6 transparent;
}

.message-time {
    text-align: right;
    font-size: 0.75rem;
    color: rgba(255,255,255,0.7);
    margin-top: 5px;
}

.message-time i {
    font-size: 0.7rem;
}

/* Member Checkbox Styling */
.member-checkbox-item {
    padding: 8px;
    border-bottom: 1px solid #f0f0f0;
    transition: background 0.2s;
}

.member-checkbox-item:hover {
    background: #f8f9fa;
}

.member-checkbox-item:last-child {
    border-bottom: none;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const messageTextarea = document.getElementById('message');
    const charCount = document.getElementById('char_count');
    const previewDiv = document.getElementById('message_preview');
    const sendToGroupCheckbox = document.getElementById('send_to_group');
    const groupSelection = document.getElementById('group_selection');
    const individualSelection = document.getElementById('individual_selection');
    const sendBtn = document.getElementById('send_btn');
    const loadMembersBtn = document.getElementById('load_members_btn');
    const memberSearch = document.getElementById('member_search');
    const customNumbers = document.getElementById('custom_numbers');
    
    let selectedMembers = [];
    
    // Toggle group/individual selection
    sendToGroupCheckbox.addEventListener('change', function() {
        if (this.checked) {
            groupSelection.classList.remove('d-none');
            individualSelection.classList.add('d-none');
        } else {
            groupSelection.classList.add('d-none');
            individualSelection.classList.remove('d-none');
        }
        validateForm();
    });
    
    // Message input handling
    messageTextarea.addEventListener('input', function() {
        updateCharCount();
        updatePreview();
        validateForm();
    });
    
    function updateCharCount() {
        const count = messageTextarea.value.length;
        charCount.textContent = count;
    }
    
    function updatePreview() {
        let preview = messageTextarea.value;
        if (preview.trim()) {
            // Replace placeholders with sample data
            preview = preview.replace(/{first_name}/g, 'John')
                           .replace(/{last_name}/g, 'Doe')
                           .replace(/{full_name}/g, 'John Doe')
                           .replace(/{church_name}/g, 'Deliverance Church');
            
            // Convert line breaks to HTML
            preview = preview.replace(/\n/g, '<br>');
            
            previewDiv.innerHTML = preview;
        } else {
            previewDiv.innerHTML = '<div class="text-muted">Preview will appear here...</div>';
        }
    }
    
    // Load members
    loadMembersBtn.addEventListener('click', function() {
        loadMembers();
    });
    
    memberSearch.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            loadMembers();
        }
    });
    
    function loadMembers() {
        const search = memberSearch.value.trim();
        const membersList = document.getElementById('members_list');
        
        membersList.innerHTML = '<div class="text-center py-3"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
        
        fetch(`ajax/get_members.php?search=${encodeURIComponent(search)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.members.length > 0) {
                    membersList.innerHTML = '';
                    
                    data.members.forEach(member => {
                        const checked = selectedMembers.includes(member.id) ? 'checked' : '';
                        const item = `
                            <div class="member-checkbox-item">
                                <div class="form-check">
                                    <input class="form-check-input member-checkbox" type="checkbox" 
                                           value="${member.id}" id="member_${member.id}" ${checked}
                                           data-phone="${member.phone}" data-name="${member.full_name}">
                                    <label class="form-check-label" for="member_${member.id}">
                                        <div class="fw-bold">${member.full_name}</div>
                                        <small class="text-muted">
                                            <i class="fab fa-whatsapp text-success"></i> ${member.phone}
                                        </small>
                                    </label>
                                </div>
                            </div>
                        `;
                        membersList.insertAdjacentHTML('beforeend', item);
                    });
                    
                    // Add event listeners to checkboxes
                    document.querySelectorAll('.member-checkbox').forEach(checkbox => {
                        checkbox.addEventListener('change', function() {
                            const memberId = parseInt(this.value);
                            if (this.checked) {
                                if (!selectedMembers.includes(memberId)) {
                                    selectedMembers.push(memberId);
                                }
                            } else {
                                selectedMembers = selectedMembers.filter(id => id !== memberId);
                            }
                            updateRecipientCount();
                            validateForm();
                        });
                    });
                    
                    updateRecipientCount();
                } else {
                    membersList.innerHTML = '<div class="text-muted text-center py-3">No members found</div>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                membersList.innerHTML = '<div class="text-danger text-center py-3">Error loading members</div>';
            });
    }
    
    // Custom numbers input handling
    customNumbers.addEventListener('input', function() {
        updateRecipientCount();
        validateForm();
    });
    
    function updateRecipientCount() {
        const customNumbersValue = customNumbers.value.trim();
        const customCount = customNumbersValue ? customNumbersValue.split(',').filter(n => n.trim()).length : 0;
        const totalCount = selectedMembers.length + customCount;
        
        const countText = document.getElementById('recipient_count_text');
        if (totalCount > 0) {
            countText.innerHTML = `
                <strong>${totalCount}</strong> recipient${totalCount !== 1 ? 's' : ''} selected
                ${selectedMembers.length > 0 ? `<br><small>${selectedMembers.length} member${selectedMembers.length !== 1 ? 's' : ''}</small>` : ''}
                ${customCount > 0 ? `<br><small>${customCount} custom number${customCount !== 1 ? 's' : ''}</small>` : ''}
            `;
        } else {
            countText.textContent = 'No recipients selected';
        }
    }
    
    // Form validation
    function validateForm() {
        const hasMessage = messageTextarea.value.trim() !== '';
        let hasRecipients = false;
        
        if (sendToGroupCheckbox.checked) {
            const groupId = document.getElementById('group_id').value;
            hasRecipients = groupId !== '';
        } else {
            const customNumbersValue = customNumbers.value.trim();
            const customCount = customNumbersValue ? customNumbersValue.split(',').filter(n => n.trim()).length : 0;
            hasRecipients = selectedMembers.length > 0 || customCount > 0;
        }
        
        sendBtn.disabled = !(hasMessage && hasRecipients);
    }
    
    // Test send functionality
    document.getElementById('test_send_btn').addEventListener('click', function() {
        const message = messageTextarea.value.trim();
        
        if (!message) {
            ChurchCMS.showToast('Please enter a message first', 'warning');
            return;
        }
        
        ChurchCMS.showConfirm(
            'Send a test message to your WhatsApp number (0745600377)?',
            function() {
                ChurchCMS.showLoading('Sending test message...');
                
                fetch('ajax/send_test_whatsapp.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        message: message
                    })
                })
                .then(response => response.json())
                .then(data => {
                    ChurchCMS.hideLoading();
                    
                    if (data.success) {
                        ChurchCMS.showToast('Test message sent successfully!', 'success');
                    } else {
                        ChurchCMS.showToast(data.message || 'Failed to send test message', 'error');
                    }
                })
                .catch(error => {
                    ChurchCMS.hideLoading();
                    console.error('Error:', error);
                    ChurchCMS.showToast('Error sending test message', 'error');
                });
            }
        );
    });
    
    // Add WhatsApp group form
    document.getElementById('addGroupForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const groupName = document.getElementById('group_name').value.trim();
        const groupWhatsappId = document.getElementById('group_whatsapp_id').value.trim();
        const groupDescription = document.getElementById('group_description').value.trim();
        
        if (!groupName) {
            ChurchCMS.showToast('Please enter group name', 'warning');
            return;
        }
        
        ChurchCMS.showLoading('Saving group...');
        
        fetch('ajax/save_whatsapp_group.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                name: groupName,
                group_id: groupWhatsappId,
                description: groupDescription
            })
        })
        .then(response => response.json())
        .then(data => {
            ChurchCMS.hideLoading();
            
            if (data.success) {
                ChurchCMS.showToast('Group added successfully!', 'success');
                bootstrap.Modal.getInstance(document.getElementById('addGroupModal')).hide();
                
                // Add to dropdown
                const groupSelect = document.getElementById('group_id');
                const option = document.createElement('option');
                option.value = data.group.id;
                option.textContent = groupName;
                groupSelect.appendChild(option);
                groupSelect.value = data.group.id;
                
                // Reset form
                this.reset();
            } else {
                ChurchCMS.showToast(data.message || 'Failed to save group', 'error');
            }
        })
        .catch(error => {
            ChurchCMS.hideLoading();
            console.error('Error:', error);
            ChurchCMS.showToast('Error saving group', 'error');
        });
    });
    
    // Form submission
    document.getElementById('whatsappForm').addEventListener('submit', function(e) {
        if (!this.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        } else {
            // Add selected members as hidden inputs
            selectedMembers.forEach(memberId => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_members[]';
                input.value = memberId;
                this.appendChild(input);
            });
            
            ChurchCMS.showLoading('Sending WhatsApp messages...');
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