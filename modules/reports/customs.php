<?php
/**
 * Custom Report Builder
 * Deliverance Church Management System
 * 
 * Advanced report builder with custom queries and saved reports
 */

// Include configuration and functions
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Check authentication and permissions
requireLogin();
if (!hasPermission('reports')) {
    header('Location: ' . BASE_URL . 'modules/dashboard/?error=access_denied');
    exit();
}

// Page configuration
$page_title = 'Custom Report Builder';
$page_icon = 'fas fa-tools';
$breadcrumb = [
    ['title' => 'Reports', 'url' => 'index.php'],
    ['title' => 'Custom Reports']
];

// Initialize database
$db = Database::getInstance();

// Process actions
$action = sanitizeInput($_GET['action'] ?? $_POST['action'] ?? '');
$reportId = sanitizeInput($_GET['id'] ?? $_POST['id'] ?? '');

try {
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($action === 'save_report') {
            $reportName = sanitizeInput($_POST['report_name']);
            $description = sanitizeInput($_POST['description']);
            $reportConfig = json_encode([
                'tables' => $_POST['tables'] ?? [],
                'fields' => $_POST['fields'] ?? [],
                'conditions' => $_POST['conditions'] ?? [],
                'grouping' => $_POST['grouping'] ?? [],
                'sorting' => $_POST['sorting'] ?? [],
                'limit' => sanitizeInput($_POST['limit'] ?? '')
            ]);
            
            $data = [
                'name' => $reportName,
                'description' => $description,
                'report_type' => 'custom',
                'parameters' => $reportConfig,
                'created_by' => $_SESSION['user_id'],
                'is_public' => isset($_POST['is_public']) ? 1 : 0
            ];
            
            if (!empty($reportId)) {
                // Update existing report
                updateRecord('custom_reports', $data, ['id' => $reportId]);
                setFlashMessage('success', 'Report updated successfully');
            } else {
                // Create new report
                insertRecord('custom_reports', $data);
                setFlashMessage('success', 'Report saved successfully');
            }
            
            logActivity('Saved custom report', 'custom_reports', $reportId);
        }
        
        if ($action === 'delete_report') {
            deleteRecord('custom_reports', ['id' => $reportId]);
            setFlashMessage('success', 'Report deleted successfully');
            logActivity('Deleted custom report', 'custom_reports', $reportId);
        }
    }
    
    // Get saved reports
    $savedReports = $db->executeQuery("
        SELECT cr.*, u.first_name, u.last_name
        FROM custom_reports cr
        LEFT JOIN users u ON cr.created_by = u.id
        WHERE cr.created_by = ? OR cr.is_public = 1
        ORDER BY cr.created_at DESC
    ", [$_SESSION['user_id']])->fetchAll();
    
    // Get current report data if editing
    $currentReport = null;
    if (!empty($reportId)) {
        $currentReport = getRecord('custom_reports', 'id', $reportId);
        if ($currentReport) {
            $currentReport['parameters'] = json_decode($currentReport['parameters'], true);
        }
    }
    
    // Execute custom query if requested
    $queryResults = [];
    $queryError = '';
    if (isset($_POST['execute_query']) && !empty($_POST['custom_sql'])) {
        $customSQL = $_POST['custom_sql'];
        
        // Basic security check - only allow SELECT statements
        if (stripos(trim($customSQL), 'SELECT') === 0) {
            try {
                $stmt = $db->getConnection()->prepare($customSQL);
                $stmt->execute();
                $queryResults = $stmt->fetchAll();
                
                logActivity('Executed custom query', null, null, null, ['query' => substr($customSQL, 0, 200)]);
            } catch (Exception $e) {
                $queryError = $e->getMessage();
            }
        } else {
            $queryError = 'Only SELECT statements are allowed for security reasons.';
        }
    }
    
    // Available tables and fields for report builder
    $availableTables = [
        'members' => [
            'name' => 'Members',
            'fields' => [
                'id' => 'Member ID',
                'member_number' => 'Member Number',
                'first_name' => 'First Name',
                'last_name' => 'Last Name',
                'gender' => 'Gender',
                'age' => 'Age',
                'phone' => 'Phone',
                'email' => 'Email',
                'join_date' => 'Join Date',
                'membership_status' => 'Status'
            ]
        ],
        'departments' => [
            'name' => 'Departments',
            'fields' => [
                'id' => 'Department ID',
                'name' => 'Department Name',
                'department_type' => 'Type',
                'is_active' => 'Active Status'
            ]
        ],
        'attendance_records' => [
            'name' => 'Attendance Records',
            'fields' => [
                'id' => 'Record ID',
                'member_id' => 'Member ID',
                'event_id' => 'Event ID',
                'check_in_time' => 'Check-in Time',
                'check_in_method' => 'Check-in Method'
            ]
        ],
        'events' => [
            'name' => 'Events',
            'fields' => [
                'id' => 'Event ID',
                'name' => 'Event Name',
                'event_type' => 'Event Type',
                'event_date' => 'Date',
                'start_time' => 'Start Time',
                'expected_attendance' => 'Expected Attendance'
            ]
        ],
        'income' => [
            'name' => 'Income',
            'fields' => [
                'id' => 'Transaction ID',
                'category_id' => 'Category ID',
                'amount' => 'Amount',
                'donor_name' => 'Donor Name',
                'payment_method' => 'Payment Method',
                'transaction_date' => 'Transaction Date',
                'status' => 'Status'
            ]
        ],
        'expenses' => [
            'name' => 'Expenses',
            'fields' => [
                'id' => 'Transaction ID',
                'category_id' => 'Category ID',
                'amount' => 'Amount',
                'vendor_name' => 'Vendor Name',
                'payment_method' => 'Payment Method',
                'expense_date' => 'Expense Date',
                'status' => 'Status'
            ]
        ],
        'visitors' => [
            'name' => 'Visitors',
            'fields' => [
                'id' => 'Visitor ID',
                'first_name' => 'First Name',
                'last_name' => 'Last Name',
                'phone' => 'Phone',
                'visit_date' => 'Visit Date',
                'status' => 'Status'
            ]
        ]
    ];
    
} catch (Exception $e) {
    error_log("Error in custom reports: " . $e->getMessage());
    setFlashMessage('error', 'Error: ' . $e->getMessage());
    $savedReports = [];
}

include_once '../../includes/header.php';
?>

<!-- Custom Reports Content -->
<div class="row">
    <!-- Report Builder Panel -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#visual-builder" role="tab">
                            <i class="fas fa-mouse-pointer me-2"></i>Visual Builder
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#sql-builder" role="tab">
                            <i class="fas fa-code me-2"></i>SQL Builder
                        </a>
                    </li>
                </ul>
            </div>
            
            <div class="card-body">
                <div class="tab-content">
                    <!-- Visual Builder Tab -->
                    <div class="tab-pane fade show active" id="visual-builder" role="tabpanel">
                        <form method="POST" action="" id="visualBuilderForm">
                            <input type="hidden" name="action" value="save_report">
                            <?php if (!empty($reportId)): ?>
                                <input type="hidden" name="id" value="<?php echo $reportId; ?>">
                            <?php endif; ?>
                            
                            <!-- Report Basic Info -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">Report Name *</label>
                                    <input type="text" name="report_name" class="form-control" required
                                           value="<?php echo htmlspecialchars($currentReport['name'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Description</label>
                                    <input type="text" name="description" class="form-control" 
                                           value="<?php echo htmlspecialchars($currentReport['description'] ?? ''); ?>">
                                </div>
                            </div>

                            <!-- Table Selection -->
                            <div class="mb-4">
                                <h6 class="fw-bold">1. Select Tables</h6>
                                <div class="row">
                                    <?php foreach ($availableTables as $tableKey => $table): ?>
                                        <div class="col-md-4 mb-2">
                                            <div class="form-check">
                                                <input class="form-check-input table-checkbox" type="checkbox" 
                                                       name="tables[]" value="<?php echo $tableKey; ?>" 
                                                       id="table_<?php echo $tableKey; ?>"
                                                       onchange="updateFieldOptions()"
                                                       <?php echo (isset($currentReport['parameters']['tables']) && in_array($tableKey, $currentReport['parameters']['tables'])) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="table_<?php echo $tableKey; ?>">
                                                    <?php echo htmlspecialchars($table['name']); ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Field Selection -->
                            <div class="mb-4">
                                <h6 class="fw-bold">2. Select Fields</h6>
                                <div id="fieldSelection" class="row">
                                    <div class="col-12 text-muted">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Please select tables first to see available fields.
                                    </div>
                                </div>
                            </div>

                            <!-- Conditions -->
                            <div class="mb-4">
                                <h6 class="fw-bold">3. Add Conditions (Optional)</h6>
                                <div id="conditionsContainer">
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="addCondition()">
                                        <i class="fas fa-plus me-2"></i>Add Condition
                                    </button>
                                </div>
                            </div>

                            <!-- Grouping and Sorting -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h6 class="fw-bold">4. Group By (Optional)</h6>
                                    <select name="grouping[]" class="form-select" multiple id="groupingSelect">
                                        <option value="">Select fields to group by</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="fw-bold">5. Sort By</h6>
                                    <div class="row">
                                        <div class="col-8">
                                            <select name="sorting[field]" class="form-select" id="sortingSelect">
                                                <option value="">Select field to sort by</option>
                                            </select>
                                        </div>
                                        <div class="col-4">
                                            <select name="sorting[direction]" class="form-select">
                                                <option value="ASC">Ascending</option>
                                                <option value="DESC">Descending</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Limit -->
                            <div class="mb-4">
                                <h6 class="fw-bold">6. Limit Results (Optional)</h6>
                                <input type="number" name="limit" class="form-control" style="max-width: 200px;" 
                                       placeholder="e.g., 100" min="1" max="10000"
                                       value="<?php echo htmlspecialchars($currentReport['parameters']['limit'] ?? ''); ?>">
                            </div>

                            <!-- Public Report Option -->
                            <div class="mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_public" id="isPublic"
                                           <?php echo (!empty($currentReport['is_public'])) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="isPublic">
                                        Make this report public (visible to all users)
                                    </label>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-church-primary">
                                    <i class="fas fa-save me-2"></i>Save Report
                                </button>
                                <button type="button" class="btn btn-outline-success" onclick="previewReport()">
                                    <i class="fas fa-eye me-2"></i>Preview
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="clearForm()">
                                    <i class="fas fa-undo me-2"></i>Clear
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- SQL Builder Tab -->
                    <div class="tab-pane fade" id="sql-builder" role="tabpanel">
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label class="form-label">Custom SQL Query</label>
                                <textarea name="custom_sql" class="form-control" rows="10" 
                                          placeholder="SELECT * FROM members WHERE membership_status = 'active' ORDER BY join_date DESC LIMIT 50"><?php echo htmlspecialchars($_POST['custom_sql'] ?? ''); ?></textarea>
                                <div class="form-text">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Only SELECT statements are allowed. Use table names: members, departments, attendance_records, events, income, expenses, visitors, etc.
                                </div>
                            </div>
                            
                            <?php if (!empty($queryError)): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    Error: <?php echo htmlspecialchars($queryError); ?>
                                </div>
                            <?php endif; ?>
                            
                            <button type="submit" name="execute_query" class="btn btn-church-primary">
                                <i class="fas fa-play me-2"></i>Execute Query
                            </button>
                        </form>
                        
                        <!-- Query Results -->
                        <?php if (!empty($queryResults)): ?>
                            <div class="mt-4">
                                <h6>Query Results (<?php echo count($queryResults); ?> records)</h6>
                                <div class="table-responsive" style="max-height: 400px;">
                                    <table class="table table-sm table-striped">
                                        <?php if (!empty($queryResults)): ?>
                                            <thead class="table-dark sticky-top">
                                                <tr>
                                                    <?php foreach (array_keys($queryResults[0]) as $column): ?>
                                                        <th><?php echo htmlspecialchars($column); ?></th>
                                                    <?php endforeach; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($queryResults as $row): ?>
                                                    <tr>
                                                        <?php foreach ($row as $value): ?>
                                                            <td><?php echo htmlspecialchars($value ?? '-'); ?></td>
                                                        <?php endforeach; ?>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        <?php endif; ?>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Saved Reports Panel -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-bookmark me-2"></i>
                    Saved Reports
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($savedReports)): ?>
                    <div class="text-center text-muted py-3">
                        <i class="fas fa-folder-open fa-2x mb-3"></i>
                        <p>No saved reports yet.<br>Create your first custom report!</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($savedReports as $report): ?>
                            <div class="list-group-item border-0 px-0">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1">
                                            <a href="?action=edit&id=<?php echo $report['id']; ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($report['name']); ?>
                                            </a>
                                            <?php if ($report['is_public']): ?>
                                                <span class="badge bg-info ms-1">Public</span>
                                            <?php endif; ?>
                                        </h6>
                                        <p class="mb-1 small text-muted"><?php echo htmlspecialchars($report['description'] ?: 'No description'); ?></p>
                                        <small class="text-muted">
                                            By <?php echo htmlspecialchars(($report['first_name'] ?? '') . ' ' . ($report['last_name'] ?? '')); ?> • 
                                            <?php echo timeAgo($report['created_at']); ?>
                                        </small>
                                    </div>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="?action=run&id=<?php echo $report['id']; ?>">
                                                <i class="fas fa-play me-2"></i>Run Report
                                            </a></li>
                                            <li><a class="dropdown-item" href="?action=edit&id=<?php echo $report['id']; ?>">
                                                <i class="fas fa-edit me-2"></i>Edit
                                            </a></li>
                                            <li><a class="dropdown-item" href="?action=duplicate&id=<?php echo $report['id']; ?>">
                                                <i class="fas fa-copy me-2"></i>Duplicate
                                            </a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-danger confirm-delete" 
                                                   href="?action=delete_report&id=<?php echo $report['id']; ?>">
                                                <i class="fas fa-trash me-2"></i>Delete
                                            </a></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Templates -->
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-magic me-2"></i>
                    Quick Templates
                </h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button class="btn btn-outline-primary btn-sm" onclick="loadTemplate('member_directory')">
                        <i class="fas fa-users me-2"></i>Member Directory
                    </button>
                    <button class="btn btn-outline-success btn-sm" onclick="loadTemplate('financial_summary')">
                        <i class="fas fa-chart-line me-2"></i>Financial Summary
                    </button>
                    <button class="btn btn-outline-info btn-sm" onclick="loadTemplate('attendance_report')">
                        <i class="fas fa-calendar-check me-2"></i>Attendance Report
                    </button>
                    <button class="btn btn-outline-warning btn-sm" onclick="loadTemplate('visitor_analysis')">
                        <i class="fas fa-user-friends me-2"></i>Visitor Analysis
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for Custom Report Builder -->
<script>
const availableTables = <?php echo json_encode($availableTables); ?>;
let conditionCounter = 0;

function updateFieldOptions() {
    const selectedTables = Array.from(document.querySelectorAll('.table-checkbox:checked')).map(cb => cb.value);
    const fieldSelection = document.getElementById('fieldSelection');
    const groupingSelect = document.getElementById('groupingSelect');
    const sortingSelect = document.getElementById('sortingSelect');
    
    // Clear existing options
    fieldSelection.innerHTML = '';
    groupingSelect.innerHTML = '<option value="">Select fields to group by</option>';
    sortingSelect.innerHTML = '<option value="">Select field to sort by</option>';
    
    if (selectedTables.length === 0) {
        fieldSelection.innerHTML = '<div class="col-12 text-muted"><i class="fas fa-info-circle me-2"></i>Please select tables first.</div>';
        return;
    }
    
    // Build field selection
    selectedTables.forEach(tableKey => {
        if (availableTables[tableKey]) {
            const table = availableTables[tableKey];
            const tableDiv = document.createElement('div');
            tableDiv.className = 'col-md-6 mb-3';
            
            let fieldsHTML = `<h6 class="text-primary">${table.name}</h6>`;
            Object.entries(table.fields).forEach(([fieldKey, fieldName]) => {
                const fullFieldKey = `${tableKey}.${fieldKey}`;
                fieldsHTML += `
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="fields[]" 
                               value="${fullFieldKey}" id="field_${fullFieldKey.replace('.', '_')}">
                        <label class="form-check-label" for="field_${fullFieldKey.replace('.', '_')}">
                            ${fieldName}
                        </label>
                    </div>
                `;
                
                // Add to grouping and sorting options
                groupingSelect.innerHTML += `<option value="${fullFieldKey}">${table.name} - ${fieldName}</option>`;
                sortingSelect.innerHTML += `<option value="${fullFieldKey}">${table.name} - ${fieldName}</option>`;
            });
            
            tableDiv.innerHTML = fieldsHTML;
            fieldSelection.appendChild(tableDiv);
        }
    });
}

function addCondition() {
    conditionCounter++;
    const container = document.getElementById('conditionsContainer');
    const conditionDiv = document.createElement('div');
    conditionDiv.className = 'row mb-2 condition-row';
    conditionDiv.id = `condition_${conditionCounter}`;
    
    conditionDiv.innerHTML = `
        <div class="col-md-3">
            <select name="conditions[${conditionCounter}][field]" class="form-select form-select-sm">
                <option value="">Select Field</option>
            </select>
        </div>
        <div class="col-md-2">
            <select name="conditions[${conditionCounter}][operator]" class="form-select form-select-sm">
                <option value="=">=</option>
                <option value="!=">!=</option>
                <option value=">">></option>
                <option value="<"><</option>
                <option value=">=">>=</option>
                <option value="<="><=</option>
                <option value="LIKE">LIKE</option>
                <option value="NOT LIKE">NOT LIKE</option>
                <option value="IS NULL">IS NULL</option>
                <option value="IS NOT NULL">IS NOT NULL</option>
            </select>
        </div>
        <div class="col-md-3">
            <input type="text" name="conditions[${conditionCounter}][value]" class="form-control form-control-sm" placeholder="Value">
        </div>
        <div class="col-md-2">
            <select name="conditions[${conditionCounter}][logic]" class="form-select form-select-sm">
                <option value="AND">AND</option>
                <option value="OR">OR</option>
            </select>
        </div>
        <div class="col-md-2">
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeCondition(${conditionCounter})">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    container.appendChild(conditionDiv);
    
    // Populate field options for this condition
    const fieldSelect = conditionDiv.querySelector('select[name*="[field]"]');
    const selectedTables = Array.from(document.querySelectorAll('.table-checkbox:checked')).map(cb => cb.value);
    
    selectedTables.forEach(tableKey => {
        if (availableTables[tableKey]) {
            const table = availableTables[tableKey];
            Object.entries(table.fields).forEach(([fieldKey, fieldName]) => {
                const option = document.createElement('option');
                option.value = `${tableKey}.${fieldKey}`;
                option.textContent = `${table.name} - ${fieldName}`;
                fieldSelect.appendChild(option);
            });
        }
    });
}

function removeCondition(conditionId) {
    const conditionDiv = document.getElementById(`condition_${conditionId}`);
    if (conditionDiv) {
        conditionDiv.remove();
    }
}

function previewReport() {
    const form = document.getElementById('visualBuilderForm');
    const formData = new FormData(form);
    
    // Show loading
    ChurchCMS.showLoading('Generating preview...');
    
    // Here you would send the form data to generate a preview
    // For now, we'll show a placeholder
    setTimeout(() => {
        ChurchCMS.hideLoading();
        ChurchCMS.showToast('Preview functionality coming soon!', 'info');
    }, 1000);
}

function clearForm() {
    if (confirm('Are you sure you want to clear the form? All unsaved changes will be lost.')) {
        document.getElementById('visualBuilderForm').reset();
        document.getElementById('fieldSelection').innerHTML = '<div class="col-12 text-muted"><i class="fas fa-info-circle me-2"></i>Please select tables first.</div>';
        document.querySelectorAll('.condition-row').forEach(row => row.remove());
        conditionCounter = 0;
    }
}

function loadTemplate(templateName) {
    const templates = {
        member_directory: {
            tables: ['members', 'departments'],
            fields: ['members.member_number', 'members.first_name', 'members.last_name', 'members.phone', 'members.email', 'members.membership_status'],
            conditions: [],
            sorting: { field: 'members.last_name', direction: 'ASC' }
        },
        financial_summary: {
            tables: ['income', 'income_categories'],
            fields: ['income_categories.name', 'income.amount', 'income.transaction_date', 'income.payment_method'],
            conditions: [{ field: 'income.status', operator: '=', value: 'verified' }],
            sorting: { field: 'income.transaction_date', direction: 'DESC' }
        },
        attendance_report: {
            tables: ['attendance_records', 'members', 'events'],
            fields: ['members.first_name', 'members.last_name', 'events.name', 'attendance_records.check_in_time'],
            conditions: [],
            sorting: { field: 'attendance_records.check_in_time', direction: 'DESC' }
        },
        visitor_analysis: {
            tables: ['visitors'],
            fields: ['visitors.first_name', 'visitors.last_name', 'visitors.phone', 'visitors.visit_date', 'visitors.status'],
            conditions: [],
            sorting: { field: 'visitors.visit_date', direction: 'DESC' }
        }
    };
    
    const template = templates[templateName];
    if (!template) return;
    
    // Clear form first
    clearForm();
    
    // Set tables
    template.tables.forEach(table => {
        const checkbox = document.querySelector(`input[value="${table}"]`);
        if (checkbox) checkbox.checked = true;
    });
    
    // Update field options
    updateFieldOptions();
    
    // Set fields after a short delay to ensure options are populated
    setTimeout(() => {
        template.fields.forEach(field => {
            const checkbox = document.querySelector(`input[value="${field}"]`);
            if (checkbox) checkbox.checked = true;
        });
        
        // Set sorting
        if (template.sorting) {
            document.querySelector('select[name="sorting[field]"]').value = template.sorting.field;
            document.querySelector('select[name="sorting[direction]"]').value = template.sorting.direction;
        }
        
        // Add conditions
        template.conditions.forEach(condition => {
            addCondition();
            const lastCondition = document.querySelector('.condition-row:last-child');
            if (lastCondition) {
                lastCondition.querySelector('select[name*="[field]"]').value = condition.field;
                lastCondition.querySelector('select[name*="[operator]"]').value = condition.operator;
                lastCondition.querySelector('input[name*="[value]"]').value = condition.value;
            }
        });
        
        ChurchCMS.showToast(`${templateName.replace('_', ' ').toUpperCase()} template loaded!`, 'success');
    }, 500);
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // If editing existing report, populate form
    <?php if (!empty($currentReport)): ?>
        const reportData = <?php echo json_encode($currentReport['parameters']); ?>;
        
        // Set tables
        if (reportData.tables) {
            reportData.tables.forEach(table => {
                const checkbox = document.querySelector(`input[value="${table}"]`);
                if (checkbox) checkbox.checked = true;
            });
            updateFieldOptions();
            
            // Set fields after options are populated
            setTimeout(() => {
                if (reportData.fields) {
                    reportData.fields.forEach(field => {
                        const checkbox = document.querySelector(`input[value="${field}"]`);
                        if (checkbox) checkbox.checked = true;
                    });
                }
                
                // Set sorting
                if (reportData.sorting) {
                    document.querySelector('select[name="sorting[field]"]').value = reportData.sorting.field || '';
                    document.querySelector('select[name="sorting[direction]"]').value = reportData.sorting.direction || 'ASC';
                }
                
                // Set grouping
                if (reportData.grouping) {
                    const groupingSelect = document.querySelector('select[name="grouping[]"]');
                    reportData.grouping.forEach(field => {
                        const option = groupingSelect.querySelector(`option[value="${field}"]`);
                        if (option) option.selected = true;
                    });
                }
            }, 500);
        }
    <?php endif; ?>
});
</script>

<?php include_once '../../includes/footer.php'; ?>