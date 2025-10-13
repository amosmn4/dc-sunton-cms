<?php
/**
 * SMS Templates Management Page
 * Deliverance Church Management System
 * 
 * Manage SMS/Communication templates
 */

// Include necessary files
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Check authentication and permissions
requireLogin();
if (!hasPermission('sms') && !in_array($_SESSION['user_role'], ['administrator', 'pastor', 'secretary'])) {
    setFlashMessage('error', 'You do not have permission to manage templates.');
    redirect(BASE_URL . 'modules/dashboard/');
}

// Page settings
$page_title = 'SMS Templates';
$page_icon = 'fas fa-clipboard-list';
$breadcrumb = [
    ['title' => 'Communication Center', 'url' => BASE_URL . 'modules/sms/'],
    ['title' => 'SMS Templates']
];

$db = Database::getInstance();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_template'])) {
            // Add new template
            $name = sanitizeInput($_POST['name']);
            $category = sanitizeInput($_POST['category']);
            $message = trim($_POST['message']);
            
            // Validation
            if (empty($name)) throw new Exception('Template name is required');
            if (empty($category)) throw new Exception('Category is required');
            if (empty($message)) throw new Exception('Message is required');
            if (strlen($message) > 160) throw new Exception('Message cannot exceed 160 characters');
            
            $templateData = [
                'name' => $name,
                'category' => $category,
                'message' => $message,
                'is_active' => 1,
                'created_by' => $_SESSION['user_id']
            ];
            
            $templateId = insertRecord('sms_templates', $templateData);
            
            if ($templateId) {
                logActivity('SMS template created', 'sms_templates', $templateId, null, $templateData);
                setFlashMessage('success', 'Template created successfully!');
            } else {
                throw new Exception('Failed to create template');
            }
            
        } elseif (isset($_POST['edit_template'])) {
            // Edit existing template
            $templateId = (int)$_POST['template_id'];
            $name = sanitizeInput($_POST['name']);
            $category = sanitizeInput($_POST['category']);
            $message = trim($_POST['message']);
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            // Validation
            if (empty($name)) throw new Exception('Template name is required');
            if (empty($category)) throw new Exception('Category is required');
            if (empty($message)) throw new Exception('Message is required');
            if (strlen($message) > 160) throw new Exception('Message cannot exceed 160 characters');
            
            // Get old data for logging
            $oldTemplate = getRecord('sms_templates', 'id', $templateId);
            
            $templateData = [
                'name' => $name,
                'category' => $category,
                'message' => $message,
                'is_active' => $isActive
            ];
            
            if (updateRecord('sms_templates', $templateData, ['id' => $templateId])) {
                logActivity('SMS template updated', 'sms_templates', $templateId, $oldTemplate, $templateData);
                setFlashMessage('success', 'Template updated successfully!');
            } else {
                throw new Exception('Failed to update template');
            }
            
        } elseif (isset($_POST['delete_template'])) {
            // Delete template
            $templateId = (int)$_POST['template_id'];
            
            // Get template data for logging
            $template = getRecord('sms_templates', 'id', $templateId);
            
            if (deleteRecord('sms_templates', ['id' => $templateId])) {
                logActivity('SMS template deleted', 'sms_templates', $templateId, $template, null);
                setFlashMessage('success', 'Template deleted successfully!');
            } else {
                throw new Exception('Failed to delete template');
            }
            
        } elseif (isset($_POST['duplicate_template'])) {
            // Duplicate template
            $templateId = (int)$_POST['template_id'];
            $template = getRecord('sms_templates', 'id', $templateId);
            
            if ($template) {
                $newTemplateData = [
                    'name' => $template['name'] . ' (Copy)',
                    'category' => $template['category'],
                    'message' => $template['message'],
                    'is_active' => 1,
                    'created_by' => $_SESSION['user_id']
                ];
                
                $newTemplateId = insertRecord('sms_templates', $newTemplateData);
                
                if ($newTemplateId) {
                    logActivity('SMS template duplicated', 'sms_templates', $newTemplateId, null, $newTemplateData);
                    setFlashMessage('success', 'Template duplicated successfully!');
                } else {
                    throw new Exception('Failed to duplicate template');
                }
            } else {
                throw new Exception('Template not found');
            }
        }
        
    } catch (Exception $e) {
        setFlashMessage('error', 'Error: ' . $e->getMessage());
        error_log("Template management error: " . $e->getMessage());
    }
    
    redirect($_SERVER['PHP_SELF']);
}

// Get templates with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? sanitizeInput($_GET['category']) : '';
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';

$recordsPerPage = 25;
$offset = ($page - 1) * $recordsPerPage;

// Build query conditions
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(name LIKE ? OR message LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($category_filter)) {
    $conditions[] = "category = ?";
    $params[] = $category_filter;
}

if ($status_filter !== '') {
    $conditions[] = "is_active = ?";
    $params[] = $status_filter;
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Get total count
$totalQuery = "SELECT COUNT(*) as total FROM sms_templates $whereClause";
$totalResult = $db->executeQuery($totalQuery, $params);
$totalRecords = $totalResult->fetchColumn();

// Get templates
$templatesQuery = "
    SELECT st.*, CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
           st.created_at
    FROM sms_templates st
    LEFT JOIN users u ON st.created_by = u.id
    $whereClause
    ORDER BY st.category, st.name
    LIMIT $recordsPerPage OFFSET $offset
";

$templates = $db->executeQuery($templatesQuery, $params)->fetchAll();

// Get categories for filter
$categories = $db->executeQuery("
    SELECT DISTINCT category 
    FROM sms_templates 
    WHERE category IS NOT NULL 
    ORDER BY category
")->fetchAll();

// Generate pagination
$pagination = generatePagination($totalRecords, $page, $recordsPerPage);

// Include header
include_once '../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-8">
        <div class="d-flex align-items-center">
            <div class="me-3">
                <div class="h4 mb-0"><?php echo number_format($totalRecords); ?></div>
                <small class="text-muted">Total Templates</small>
            </div>
            <div class="me-3">
                <div class="h4 mb-0 text-success">
                    <?php echo $db->executeQuery("SELECT COUNT(*) FROM sms_templates WHERE is_active = 1")->fetchColumn(); ?>
                </div>
                <small class="text-muted">Active</small>
            </div>
            <div>
                <div class="h4 mb-0 text-muted">
                    <?php echo $db->executeQuery("SELECT COUNT(*) FROM sms_templates WHERE is_active = 0")->fetchColumn(); ?>
                </div>
                <small class="text-muted">Inactive</small>
            </div>
        </div>
    </div>
    <div class="col-md-4 text-end">
        <button type="button" class="btn btn-church-primary" data-bs-toggle="modal" data-bs-target="#addTemplateModal">
            <i class="fas fa-plus me-1"></i>Add New Template
        </button>
    </div>
</div>

<!-- Filters -->
<div class="card shadow mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-4">
                <label for="search" class="form-label">Search Templates</label>
                <input type="text" class="form-control" id="search" name="search" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Search by name or message...">
            </div>
            <div class="col-md-3">
                <label for="category" class="form-label">Category</label>
                <select class="form-select" id="category" name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['category']; ?>" 
                                <?php echo $category_filter === $cat['category'] ? 'selected' : ''; ?>>
                            <?php echo ucfirst(str_replace('_', ' ', $cat['category'])); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All Status</option>
                    <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>Active</option>
                    <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="fas fa-search"></i>
                </button>
                <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-times"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Templates List -->
<div class="card shadow">
    <div class="card-header bg-church-blue text-white">
        <h6 class="mb-0">
            <i class="fas fa-clipboard-list me-2"></i>SMS Templates
            <?php if (!empty($search) || !empty($category_filter) || $status_filter !== ''): ?>
                <span class="badge bg-light text-dark ms-2">Filtered</span>
            <?php endif; ?>
        </h6>
    </div>
    <div class="card-body">
        <?php if (empty($templates)): ?>
            <div class="text-center py-5">
                <i class="fas fa-clipboard-list fa-4x text-muted mb-3"></i>
                <h4 class="text-muted">No Templates Found</h4>
                <?php if (!empty($search) || !empty($category_filter) || $status_filter !== ''): ?>
                    <p class="text-muted">Try adjusting your search criteria or <a href="<?php echo $_SERVER['PHP_SELF']; ?>">clear filters</a></p>
                <?php else: ?>
                    <p class="text-muted">Create your first SMS template to get started</p>
                    <button type="button" class="btn btn-church-primary" data-bs-toggle="modal" data-bs-target="#addTemplateModal">
                        <i class="fas fa-plus me-1"></i>Create First Template
                    </button>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Template Name</th>
                            <th>Category</th>
                            <th>Message Preview</th>
                            <th>Length</th>
                            <th>Status</th>
                            <th>Created By</th>
                            <th>Created Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($templates as $template): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($template['name']); ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-info">
                                        <?php echo ucfirst(str_replace('_', ' ', $template['category'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="text-truncate" style="max-width: 250px;" title="<?php echo htmlspecialchars($template['message']); ?>">
                                        <?php echo htmlspecialchars($template['message']); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?php echo strlen($template['message']) > 140 ? 'bg-warning' : 'bg-success'; ?>">
                                        <?php echo strlen($template['message']); ?>/160
                                    </span>
                                </td>
                                <td>
                                    <?php if ($template['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($template['created_by_name'] ?? 'System'); ?></td>
                                <td>
                                    <small><?php echo formatDisplayDateTime($template['created_at']); ?></small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-info" 
                                                onclick="viewTemplate(<?php echo $template['id']; ?>)" title="View">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-primary" 
                                                onclick="editTemplate(<?php echo $template['id']; ?>)" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" 
                                                onclick="duplicateTemplate(<?php echo $template['id']; ?>)" title="Duplicate">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger" 
                                                onclick="deleteTemplate(<?php echo $template['id']; ?>, '<?php echo addslashes($template['name']); ?>')" 
                                                title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($pagination['total_pages'] > 1): ?>
                <div class="d-flex justify-content-between align-items-center mt-4">
                    <div class="text-muted">
                        Showing <?php echo number_format($offset + 1); ?> to 
                        <?php echo number_format(min($offset + $recordsPerPage, $totalRecords)); ?> of 
                        <?php echo number_format($totalRecords); ?> templates
                    </div>
                    <?php echo generatePaginationHTML($pagination, $_SERVER['PHP_SELF'] . '?' . http_build_query($_GET)); ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Add Template Modal -->
<div class="modal fade" id="addTemplateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-church-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-plus-circle me-2"></i>Add New SMS Template
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" id="addTemplateForm" class="needs-validation" novalidate>
                <div class="modal-body">
                    <input type="hidden" name="add_template" value="1">
                    
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="add_name" class="form-label">Template Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="add_name" name="name" required maxlength="100"
                                   placeholder="e.g., Sunday Service Reminder">
                            <div class="invalid-feedback">Please provide a template name</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="add_category" class="form-label">Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="add_category" name="category" required>
                                <option value="">Choose category...</option>
                                <?php foreach (SMS_TEMPLATE_CATEGORIES as $key => $label): ?>
                                    <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select a category</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="add_message" class="form-label">
                            Message Content <span class="text-danger">*</span>
                            <small class="text-muted">(<span id="add_char_count">0</span>/160 characters)</small>
                        </label>
                        <textarea class="form-control" id="add_message" name="message" rows="4" 
                                  maxlength="160" required 
                                  placeholder="Type your SMS message here..."></textarea>
                        <div class="invalid-feedback">Please provide the message content</div>
                        <div class="form-text">
                            Available placeholders: {first_name}, {last_name}, {full_name}, {church_name}
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Message Preview</label>
                        <div class="border rounded p-3 bg-light" id="add_preview">
                            <div class="text-muted">Preview will appear here...</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-church-primary">
                        <i class="fas fa-save me-1"></i>Create Template
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Template Modal -->
<div class="modal fade" id="editTemplateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-church-blue text-white">
                <h5 class="modal-title">
                    <i class="fas fa-edit me-2"></i>Edit SMS Template
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" id="editTemplateForm" class="needs-validation" novalidate>
                <div class="modal-body">
                    <input type="hidden" name="edit_template" value="1">
                    <input type="hidden" name="template_id" id="edit_template_id">
                    
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="edit_name" class="form-label">Template Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_name" name="name" required maxlength="100">
                            <div class="invalid-feedback">Please provide a template name</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="edit_category" class="form-label">Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_category" name="category" required>
                                <option value="">Choose category...</option>
                                <?php foreach (SMS_TEMPLATE_CATEGORIES as $key => $label): ?>
                                    <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select a category</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_message" class="form-label">
                            Message Content <span class="text-danger">*</span>
                            <small class="text-muted">(<span id="edit_char_count">0</span>/160 characters)</small>
                        </label>
                        <textarea class="form-control" id="edit_message" name="message" rows="4" 
                                  maxlength="160" required></textarea>
                        <div class="invalid-feedback">Please provide the message content</div>
                        <div class="form-text">
                            Available placeholders: {first_name}, {last_name}, {full_name}, {church_name}
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Message Preview</label>
                        <div class="border rounded p-3 bg-light" id="edit_preview">
                            <div class="text-muted">Preview will appear here...</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active" checked>
                            <label class="form-check-label" for="edit_is_active">
                                Active (available for use)
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-church-primary">
                        <i class="fas fa-save me-1"></i>Update Template
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Template Modal -->
<div class="modal fade" id="viewTemplateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="fas fa-eye me-2"></i>View SMS Template
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Template Information</h6>
                        <table class="table table-sm">
                            <tr><td><strong>Name:</strong></td><td id="view_name">-</td></tr>
                            <tr><td><strong>Category:</strong></td><td id="view_category">-</td></tr>
                            <tr><td><strong>Status:</strong></td><td id="view_status">-</td></tr>
                            <tr><td><strong>Length:</strong></td><td id="view_length">-</td></tr>
                            <tr><td><strong>Created By:</strong></td><td id="view_created_by">-</td></tr>
                            <tr><td><strong>Created Date:</strong></td><td id="view_created_date">-</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Message Content</h6>
                        <div class="border rounded p-3 bg-light mb-3" id="view_message">-</div>
                        
                        <h6>Preview with Sample Data</h6>
                        <div class="border rounded p-3 bg-primary text-white" id="view_message_preview">-</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="use_template_btn">
                    <i class="fas fa-paper-plane me-1"></i>Use This Template
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Character count and preview for add form
    const addMessageTextarea = document.getElementById('add_message');
    const addCharCount = document.getElementById('add_char_count');
    const addPreview = document.getElementById('add_preview');
    
    addMessageTextarea.addEventListener('input', function() {
        updateCharacterCount(this, addCharCount);
        updateMessagePreview(this.value, addPreview);
    });
    
    // Character count and preview for edit form
    const editMessageTextarea = document.getElementById('edit_message');
    const editCharCount = document.getElementById('edit_char_count');
    const editPreview = document.getElementById('edit_preview');
    
    editMessageTextarea.addEventListener('input', function() {
        updateCharacterCount(this, editCharCount);
        updateMessagePreview(this.value, editPreview);
    });
    
    function updateCharacterCount(textarea, countElement) {
        const count = textarea.value.length;
        countElement.textContent = count;
        
        const parent = countElement.parentElement;
        parent.classList.remove('text-danger', 'text-warning');
        
        if (count > 160) {
            parent.classList.add('text-danger');
        } else if (count > 140) {
            parent.classList.add('text-warning');
        }
    }
    
    function updateMessagePreview(message, previewElement) {
        if (message.trim()) {
            let preview = message
                .replace(/{first_name}/g, 'John')
                .replace(/{last_name}/g, 'Doe')
                .replace(/{full_name}/g, 'John Doe')
                .replace(/{church_name}/g, 'Deliverance Church');
            
            previewElement.innerHTML = `<div class="text-dark">${preview}</div>`;
        } else {
            previewElement.innerHTML = '<div class="text-muted">Preview will appear here...</div>';
        }
    }
    
    // Form validation
    const forms = document.querySelectorAll('.needs-validation');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });
});

// Template management functions
function viewTemplate(templateId) {
    fetch(`ajax/get_template.php?id=${templateId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const template = data.template;
                
                document.getElementById('view_name').textContent = template.name;
                document.getElementById('view_category').innerHTML = 
                    `<span class="badge bg-info">${template.category_display}</span>`;
                document.getElementById('view_status').innerHTML = 
                    template.is_active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>';
                document.getElementById('view_length').innerHTML = 
                    `<span class="badge ${template.message.length > 140 ? 'bg-warning' : 'bg-success'}">${template.message.length}/160</span>`;
                document.getElementById('view_created_by').textContent = template.created_by_name || 'System';
                document.getElementById('view_created_date').textContent = template.created_at_formatted;
                document.getElementById('view_message').textContent = template.message;
                
                // Preview with sample data
                let preview = template.message
                    .replace(/{first_name}/g, 'John')
                    .replace(/{last_name}/g, 'Doe')
                    .replace(/{full_name}/g, 'John Doe')
                    .replace(/{church_name}/g, 'Deliverance Church');
                
                document.getElementById('view_message_preview').textContent = preview;
                
                // Set up "Use Template" button
                document.getElementById('use_template_btn').onclick = function() {
                    window.location.href = `send.php?template_id=${templateId}`;
                };
                
                new bootstrap.Modal(document.getElementById('viewTemplateModal')).show();
            } else {
                ChurchCMS.showToast('Error loading template details', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            ChurchCMS.showToast('Error loading template details', 'error');
        });
}

function editTemplate(templateId) {
    fetch(`ajax/get_template.php?id=${templateId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const template = data.template;
                
                document.getElementById('edit_template_id').value = templateId;
                document.getElementById('edit_name').value = template.name;
                document.getElementById('edit_category').value = template.category;
                document.getElementById('edit_message').value = template.message;
                document.getElementById('edit_is_active').checked = template.is_active == 1;
                
                // Update character count and preview
                document.getElementById('edit_char_count').textContent = template.message.length;
                
                let preview = template.message
                    .replace(/{first_name}/g, 'John')
                    .replace(/{last_name}/g, 'Doe')
                    .replace(/{full_name}/g, 'John Doe')
                    .replace(/{church_name}/g, 'Deliverance Church');
                
                document.getElementById('edit_preview').innerHTML = `<div class="text-dark">${preview}</div>`;
                
                new bootstrap.Modal(document.getElementById('editTemplateModal')).show();
            } else {
                ChurchCMS.showToast('Error loading template for editing', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            ChurchCMS.showToast('Error loading template for editing', 'error');
        });
}

function duplicateTemplate(templateId) {
    ChurchCMS.showConfirm(
        'Are you sure you want to duplicate this template?',
        function() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const templateIdInput = document.createElement('input');
            templateIdInput.name = 'template_id';
            templateIdInput.value = templateId;
            
            const actionInput = document.createElement('input');
            actionInput.name = 'duplicate_template';
            actionInput.value = '1';
            
            form.appendChild(templateIdInput);
            form.appendChild(actionInput);
            document.body.appendChild(form);
            form.submit();
        }
    );
}

function deleteTemplate(templateId, templateName) {
    ChurchCMS.showConfirm(
        `Are you sure you want to delete the template "${templateName}"? This action cannot be undone.`,
        function() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const templateIdInput = document.createElement('input');
            templateIdInput.name = 'template_id';
            templateIdInput.value = templateId;
            
            const actionInput = document.createElement('input');
            actionInput.name = 'delete_template';
            actionInput.value = '1';
            
            form.appendChild(templateIdInput);
            form.appendChild(actionInput);
            document.body.appendChild(form);
            form.submit();
        },
        null,
        'Delete Template'
    );
}
</script>

<?php include_once '../../includes/footer.php'; ?>