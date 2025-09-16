if ($result) {
                setFlashMessage('success', 'Custom report saved successfully!');
                header('Location: custom.php');
                exit();
            } else {
                $error = 'Failed to save custom report';
            }
        }
        
    } catch (Exception $e) {
        error_log("Error in custom report builder: " . $e->getMessage());
        $error = 'Error processing request: ' . $e->getMessage();
    }
}

// Get saved reports
$savedReports = [];
try {
    $savedReports = $db->executeQuery("
        SELECT cr.*, u.first_name, u.last_name 
        FROM custom_reports cr
        LEFT JOIN users u ON cr.created_by = u.id
        WHERE cr.is_public = 1 OR cr.created_by = ?
        ORDER BY cr.created_at DESC
    ", [$_SESSION['user_id']])->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching saved reports: " . $e->getMessage());
}

/**
 * Build custom SQL query based on user selections
 */
function buildCustomQuery($type, $fields, $tables, $conditions, $groupBy, $orderBy, $limit) {
    if (empty($fields) || empty($tables)) {
        return '';
    }
    
    // Validate and sanitize fields
    $validFields = [];
    foreach ($fields as $field) {
        if (preg_match('/^[a-zA-Z0-9_.]+$/', $field)) {
            $validFields[] = $field;
        }
    }
    
    if (empty($validFields)) {
        return '';
    }
    
    // Build SELECT clause
    $selectClause = implode(', ', $validFields);
    
    // Build FROM clause
    $fromClause = implode(', ', array_map('sanitizeTableName', $tables));
    
    // Start building query
    $query = "SELECT {$selectClause} FROM {$fromClause}";
    
    // Add JOIN conditions (simplified - in production you'd want more sophisticated join building)
    if (count($tables) > 1) {
        // Add basic joins based on common patterns
        $query = addJoins($query, $tables);
    }
    
    // Add WHERE conditions
    if (!empty($conditions)) {
        $whereClause = buildWhereClause($conditions);
        if ($whereClause) {
            $query .= " WHERE {$whereClause}";
        }
    }
    
    // Add GROUP BY
    if (!empty($groupBy) && preg_match('/^[a-zA-Z0-9_.]+$/', $groupBy)) {
        $query .= " GROUP BY {$groupBy}";
    }
    
    // Add ORDER BY
    if (!empty($orderBy) && preg_match('/^[a-zA-Z0-9_. ]+$/', $orderBy)) {
        $query .= " ORDER BY {$orderBy}";
    }
    
    // Add LIMIT
    if ($limit > 0 && $limit <= 1000) {
        $query .= " LIMIT {$limit}";
    }
    
    return $query;
}

/**
 * Add JOIN clauses based on table relationships
 */
function addJoins($query, $tables) {
    // Define common relationships
    $relationships = [
        'members' => [
            'member_departments' => 'members.id = member_departments.member_id',
            'attendance_records' => 'members.id = attendance_records.member_id'
        ],
        'departments' => [
            'member_departments' => 'departments.id = member_departments.department_id'
        ],
        'income' => [
            'income_categories' => 'income.category_id = income_categories.id'
        ],
        'expenses' => [
            'expense_categories' => 'expenses.category_id = expense_categories.id'
        ],
        'events' => [
            'attendance_records' => 'events.id = attendance_records.event_id'
        ]
    ];
    
    // Simple join logic - in production, this would be more sophisticated
    if (in_array('members', $tables) && in_array('member_departments', $tables)) {
        $query = str_replace('FROM members, member_departments', 
            'FROM members LEFT JOIN member_departments ON members.id = member_departments.member_id', $query);
    }
    
    return $query;
}

/**
 * Build WHERE clause from conditions
 */
function buildWhereClause($conditions) {
    $clauses = [];
    
    foreach ($conditions as $condition) {
        if (empty($condition['field']) || empty($condition['operator']) || !isset($condition['value'])) {
            continue;
        }
        
        $field = sanitizeInput($condition['field']);
        $operator = sanitizeInput($condition['operator']);
        $value = sanitizeInput($condition['value']);
        
        // Validate field and operator
        if (!preg_match('/^[a-zA-Z0-9_.]+$/', $field)) {
            continue;
        }
        
        $validOperators = ['=', '!=', '>', '<', '>=', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN'];
        if (!in_array($operator, $validOperators)) {
            continue;
        }
        
        // Build condition
        if ($operator === 'LIKE' || $operator === 'NOT LIKE') {
            $clauses[] = "{$field} {$operator} '%{$value}%'";
        } elseif ($operator === 'IN' || $operator === 'NOT IN') {
            $values = explode(',', $value);
            $values = array_map(function($v) { return "'" . trim($v) . "'"; }, $values);
            $clauses[] = "{$field} {$operator} (" . implode(',', $values) . ")";
        } else {
            $clauses[] = "{$field} {$operator} '{$value}'";
        }
    }
    
    return implode(' AND ', $clauses);
}

/**
 * Sanitize table name
 */
function sanitizeTableName($tableName) {
    // Only allow specific tables
    $allowedTables = [
        'members', 'departments', 'member_departments', 'attendance_records', 'events',
        'income', 'income_categories', 'expenses', 'expense_categories',
        'visitors', 'equipment', 'sms_history', 'users'
    ];
    
    return in_array($tableName, $allowedTables) ? $tableName : '';
}

// Include header
include_once '../../includes/header.php';
?>

<!-- Custom Report Builder Content -->
<div class="row">
    <!-- Report Builder Form -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-tools me-2"></i>
                    Custom Report Builder
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" id="reportBuilderForm">
                    <input type="hidden" name="action" value="build_report">
                    
                    <!-- Report Basic Info -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Report Name</label>
                            <input type="text" name="report_name" class="form-control" placeholder="Enter report name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Report Type</label>
                            <select name="report_type" class="form-select" required>
                                <option value="">Select Type</option>
                                <option value="members">Member Report</option>
                                <option value="financial">Financial Report</option>
                                <option value="attendance">Attendance Report</option>
                                <option value="visitors">Visitor Report</option>
                                <option value="equipment">Equipment Report</option>
                                <option value="custom">Custom Query</option>
                            </select>
                        </div>
                    </div>

                    <!-- Table Selection -->
                    <div class="mb-3">
                        <label class="form-label">Select Tables</label>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input table-checkbox" type="checkbox" name="tables[]" value="members" id="table_members">
                                    <label class="form-check-label" for="table_members">Members</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input table-checkbox" type="checkbox" name="tables[]" value="departments" id="table_departments">
                                    <label class="form-check-label" for="table_departments">Departments</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input table-checkbox" type="checkbox" name="tables[]" value="member_departments" id="table_member_departments">
                                    <label class="form-check-label" for="table_member_departments">Member Departments</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input table-checkbox" type="checkbox" name="tables[]" value="attendance_records" id="table_attendance">
                                    <label class="form-check-label" for="table_attendance">Attendance Records</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input table-checkbox" type="checkbox" name="tables[]" value="events" id="table_events">
                                    <label class="form-check-label" for="table_events">Events</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input table-checkbox" type="checkbox" name="tables[]" value="visitors" id="table_visitors">
                                    <label class="form-check-label" for="table_visitors">Visitors</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input table-checkbox" type="checkbox" name="tables[]" value="income" id="table_income">
                                    <label class="form-check-label" for="table_income">Income</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input table-checkbox" type="checkbox" name="tables[]" value="expenses" id="table_expenses">
                                    <label class="form-check-label" for="table_expenses">Expenses</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input table-checkbox" type="checkbox" name="tables[]" value="equipment" id="table_equipment">
                                    <label class="form-check-label" for="table_equipment">Equipment</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Field Selection -->
                    <div class="mb-3">
                        <label class="form-label">Select Fields</label>
                        <div id="fieldSelection" class="border rounded p-3 bg-light">
                            <p class="text-muted mb-0">Select tables first to see available fields</p>
                        </div>
                    </div>

                    <!-- Conditions -->
                    <div class="mb-3">
                        <label class="form-label">Conditions (WHERE)</label>
                        <div id="conditionsContainer">
                            <div class="condition-row row mb-2">
                                <div class="col-md-3">
                                    <select name="conditions[0][field]" class="form-select condition-field">
                                        <option value="">Select Field</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select name="conditions[0][operator]" class="form-select">
                                        <option value="=">=</option>
                                        <option value="!=">!=</option>
                                        <option value=">">&gt;</option>
                                        <option value="<">&lt;</option>
                                        <option value=">=">&gt;=</option>
                                        <option value="<=">&lt;=</option>
                                        <option value="LIKE">LIKE</option>
                                        <option value="NOT LIKE">NOT LIKE</option>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <input type="text" name="conditions[0][value]" class="form-control" placeholder="Value">
                                </div>
                                <div class="col-md-2">
                                    <button type="button" class="btn btn-outline-secondary" onclick="addCondition()">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Group By and Order By -->
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Group By</label>
                            <select name="group_by" class="form-select" id="groupBySelect">
                                <option value="">None</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Order By</label>
                            <select name="order_by" class="form-select" id="orderBySelect">
                                <option value="">None</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Limit</label>
                            <select name="limit" class="form-select">
                                <option value="25">25 records</option>
                                <option value="50">50 records</option>
                                <option value="100" selected>100 records</option>
                                <option value="250">250 records</option>
                                <option value="500">500 records</option>
                                <option value="1000">1000 records</option>
                            </select>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-flex gap-2 mb-3">
                        <button type="submit" class="btn btn-church-primary">
                            <i class="fas fa-play me-2"></i>Run Report
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="previewQuery()">
                            <i class="fas fa-eye me-2"></i>Preview Query
                        </button>
                        <button type="button" class="btn btn-outline-success" onclick="showSaveModal()">
                            <i class="fas fa-save me-2"></i>Save Report
                        </button>
                    </div>

                    <!-- Query Preview -->
                    <div id="queryPreview" class="mb-3" style="display: none;">
                        <label class="form-label">Generated Query</label>
                        <pre class="bg-dark text-light p-3 rounded"><code id="queryCode"></code></pre>
                    </div>
                </form>
            </div>
        </div>

        <!-- Report Results -->
        <?php if (!empty($reportData)): ?>
        <div class="card mt-4">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="fas fa-table me-2"></i>
                        Report Results
                        <span class="badge bg-primary ms-2"><?php echo count($reportData); ?> records</span>
                    </h6>
                    <div class="btn-group">
                        <button class="btn btn-sm btn-outline-success dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fas fa-download me-2"></i>Export
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" onclick="exportResults('csv')">
                                <i class="fas fa-file-csv me-2"></i>Export as CSV
                            </a></li>
                            <li><a class="dropdown-item" href="#" onclick="exportResults('excel')">
                                <i class="fas fa-file-excel me-2"></i>Export as Excel
                            </a></li>
                            <li><a class="dropdown-item" href="#" onclick="exportResults('pdf')">
                                <i class="fas fa-file-pdf me-2"></i>Export as PDF
                            </a></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-sm data-table">
                        <thead>
                            <tr>
                                <?php if (!empty($reportData)): ?>
                                    <?php foreach (array_keys($reportData[0]) as $column): ?>
                                        <th><?php echo ucwords(str_replace('_', ' ', $column)); ?></th>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportData as $row): ?>
                                <tr>
                                    <?php foreach ($row as $value): ?>
                                        <td>
                                            <?php 
                                            if (is_numeric($value) && strpos($value, '.') !== false) {
                                                echo number_format($value, 2);
                                            } elseif (is_numeric($value)) {
                                                echo number_format($value);
                                            } elseif (DateTime::createFromFormat('Y-m-d', $value) !== false) {
                                                echo formatDisplayDate($value);
                                            } elseif (DateTime::createFromFormat('Y-m-d H:i:s', $value) !== false) {
                                                echo formatDisplayDateTime($value);
                                            } else {
                                                echo htmlspecialchars($value ?: '-');
                                            }
                                            ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Generated Query Display -->
        <?php if (!empty($query)): ?>
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-code me-2"></i>
                    Generated SQL Query
                </h6>
            </div>
            <div class="card-body">
                <pre class="bg-dark text-light p-3 rounded"><code><?php echo htmlspecialchars($query); ?></code></pre>
                <button class="btn btn-sm btn-outline-primary mt-2" onclick="ChurchCMS.copyToClipboard('<?php echo addslashes($query); ?>', 'Query copied to clipboard!')">
                    <i class="fas fa-copy me-2"></i>Copy Query
                </button>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Saved Reports Sidebar -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-bookmark me-2"></i>
                    Saved Reports
                </h6>
            </div>
            <div class="card-body">
                <?php if (!empty($savedReports)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($savedReports as $report): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-start">
                                <div class="ms-2 me-auto">
                                    <div class="fw-bold"><?php echo htmlspecialchars($report['name']); ?></div>
                                    <small class="text-muted">
                                        <?php echo ucwords(str_replace('_', ' ', $report['report_type'])); ?> | 
                                        by <?php echo htmlspecialchars($report['first_name'] . ' ' . $report['last_name']); ?>
                                    </small>
                                    <?php if (!empty($report['description'])): ?>
                                        <div class="small text-muted mt-1"><?php echo htmlspecialchars($report['description']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="btn-group-vertical btn-group-sm">
                                    <button class="btn btn-outline-primary btn-sm" onclick="loadSavedReport(<?php echo $report['id']; ?>)">
                                        <i class="fas fa-play"></i>
                                    </button>
                                    <?php if ($report['created_by'] == $_SESSION['user_id'] || $_SESSION['user_role'] === 'administrator'): ?>
                                        <button class="btn btn-outline-danger btn-sm" onclick="deleteSavedReport(<?php echo $report['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-3">
                        <i class="fas fa-bookmark fa-2x mb-2"></i>
                        <p>No saved reports yet</p>
                        <small>Create and save your first custom report!</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Templates -->
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-layer-group me-2"></i>
                    Quick Templates
                </h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button class="btn btn-outline-primary btn-sm" onclick="loadTemplate('member_list')">
                        <i class="fas fa-users me-2"></i>Member List
                    </button>
                    <button class="btn btn-outline-success btn-sm" onclick="loadTemplate('financial_summary')">
                        <i class="fas fa-money-bill me-2"></i>Financial Summary
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

        <!-- Help Guide -->
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-question-circle me-2"></i>
                    Quick Guide
                </h6>
            </div>
            <div class="card-body">
                <div class="small">
                    <h6>Steps to create a report:</h6>
                    <ol>
                        <li>Give your report a name</li>
                        <li>Select the data tables you need</li>
                        <li>Choose which fields to include</li>
                        <li>Add conditions to filter data</li>
                        <li>Set grouping and sorting options</li>
                        <li>Run the report</li>
                    </ol>
                    
                    <h6 class="mt-3">Tips:</h6>
                    <ul>
                        <li>Start with templates for common reports</li>
                        <li>Preview your query before running</li>
                        <li>Save frequently used reports</li>
                        <li>Use conditions to filter large datasets</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Save Report Modal -->
<div class="modal fade" id="saveReportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Save Custom Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="saveReportForm">
                <input type="hidden" name="action" value="save_report">
                <input type="hidden" name="sql_query" id="saveQuery">
                <input type="hidden" name="parameters" id="saveParameters">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Report Name</label>
                        <input type="text" name="report_name" class="form-control" id="saveReportName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Optional description"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Report Type</label>
                        <select name="report_type" class="form-select" id="saveReportType">
                            <option value="members">Members</option>
                            <option value="financial">Financial</option>
                            <option value="attendance">Attendance</option>
                            <option value="visitors">Visitors</option>
                            <option value="equipment">Equipment</option>
                            <option value="custom">Custom</option>
                        </select>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_public" id="isPublic">
                        <label class="form-check-label" for="isPublic">
                            Make this report available to other users
                        </label>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-church-primary">Save Report</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script>
// Field definitions for each table
const tableFields = {
    'members': [
        'members.id', 'members.member_number', 'members.first_name', 'members.last_name',
        'members.gender', 'members.age', 'members.phone', 'members.email', 
        'members.join_date', 'members.membership_status'
    ],
    'departments': [
        'departments.id', 'departments.name', 'departments.department_type',
        'departments.is_active'
    ],
    'member_departments': [
        'member_departments.role', 'member_departments.assigned_date', 'member_departments.is_active'
    ],
    'attendance_records': [
        'attendance_records.check_in_time', 'attendance_records.is_present',
        'attendance_records.check_in_method'
    ],
    'events': [
        'events.name', 'events.event_type', 'events.event_date',
        'events.start_time', 'events.location'
    ],
    'income': [
        'income.transaction_id', 'income.amount', 'income.donor_name',
        'income.transaction_date', 'income.payment_method', 'income.status'
    ],
    'expenses': [
        'expenses.transaction_id', 'expenses.amount', 'expenses.vendor_name',
        'expenses.expense_date', 'expenses.payment_method', 'expenses.status'
    ],
    'visitors': [
        'visitors.visitor_number', 'visitors.first_name', 'visitors.last_name',
        'visitors.phone', 'visitors.visit_date', 'visitors.status'
    ],
    'equipment': [
        'equipment.equipment_code', 'equipment.name', 'equipment.status',
        'equipment.purchase_date', 'equipment.location'
    ]
};

let conditionCount = 1;

document.addEventListener('DOMContentLoaded', function() {
    // Handle table selection changes
    document.querySelectorAll('.table-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateFieldSelection);
    });
    
    updateFieldSelection();
});

function updateFieldSelection() {
    const selectedTables = Array.from(document.querySelectorAll('.table-checkbox:checked'))
                               .map(cb => cb.value);
    
    const fieldContainer = document.getElementById('fieldSelection');
    const conditionFields = document.querySelectorAll('.condition-field');
    const groupBySelect = document.getElementById('groupBySelect');
    const orderBySelect = document.getElementById('orderBySelect');
    
    if (selectedTables.length === 0) {
        fieldContainer.innerHTML = '<p class="text-muted mb-0">Select tables first to see available fields</p>';
        return;
    }
    
    // Build available fields
    let availableFields = [];
    selectedTables.forEach(table => {
        if (tableFields[table]) {
            availableFields = availableFields.concat(tableFields[table]);
        }
    });
    
    // Update field selection checkboxes
    let fieldsHtml = '<div class="row">';
    availableFields.forEach((field, index) => {
        if (index % 3 === 0 && index > 0) fieldsHtml += '</div><div class="row">';
        fieldsHtml += `
            <div class="col-md-4 mb-2">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="fields[]" value="${field}" id="field_${index}">
                    <label class="form-check-label small" for="field_${index}">
                        ${field.split('.')[1] || field}
                    </label>
                </div>
            </div>
        `;
    });
    fieldsHtml += '</div>';
    fieldContainer.innerHTML = fieldsHtml;
    
    // Update condition field selects
    const fieldOptions = availableFields.map(field => 
        `<option value="${field}">${field}</option>`
    ).join('');
    
    conditionFields.forEach(select => {
        select.innerHTML = '<option value="">Select Field</option>' + fieldOptions;
    });
    
    // Update group by and order by selects
    groupBySelect.innerHTML = '<option value="">None</option>' + fieldOptions;
    orderBySelect.innerHTML = '<option value="">None</option>' + fieldOptions;
}

function addCondition() {
    const container = document.getElementById('conditionsContainer');
    const newRow = document.createElement('div');
    newRow.className = 'condition-row row mb-2';
    newRow.innerHTML = `
        <div class="col-md-3">
            <select name="conditions[${conditionCount}][field]" class="form-select condition-field">
                <option value="">Select Field</option>
            </select>
        </div>
        <div class="col-md-2">
            <select name="conditions[${conditionCount}][operator]" class="form-select">
                <option value="=">=</option>
                <option value="!=">!=</option>
                <option value=">">&gt;</option>
                <option value="<">&lt;</option>
                <option value=">=">&gt;=</option>
                <option value="<=">&lt;=</option>
                <option value="LIKE">LIKE</option>
                <option value="NOT LIKE">NOT LIKE</option>
            </select>
        </div>
        <div class="col-md-5">
            <input type="text" name="conditions[${conditionCount}][value]" class="form-control" placeholder="Value">
        </div>
        <div class="col-md-2">
            <button type="button" class="btn btn-outline-danger" onclick="removeCondition(this)">
                <i class="fas fa-minus"></i>
            </button>
        </div>
    `;
    
    container.appendChild(newRow);
    conditionCount++;
    
    // Update the new field select with available fields
    updateFieldSelection();
}

function removeCondition(button) {
    button.closest('.condition-row').remove();
}

function previewQuery() {
    const formData = new FormData(document.getElementById('reportBuilderForm'));
    const queryPreview = document.getElementById('queryPreview');
    const queryCode = document.getElementById('queryCode');
    
    // Build preview query (simplified version)
    const tables = formData.getAll('tables[]');
    const fields = formData.getAll('fields[]');
    
    if (tables.length === 0 || fields.length === 0) {
        ChurchCMS.showToast('Please select tables and fields first', 'warning');
        return;
    }
    
    let query = `SELECT ${fields.join(', ')}\nFROM ${tables.join(', ')}`;
    
    // Add conditions if any
    const conditions = [];
    for (let i = 0; i < conditionCount; i++) {
        const field = formData.get(`conditions[${i}][field]`);
        const operator = formData.get(`conditions[${i}][operator]`);
        const value = formData.get(`conditions[${i}][value]`);
        
        if (field && operator && value) {
            conditions.push(`${field} ${operator} '${value}'`);
        }
    }
    
    if (conditions.length > 0) {
        query += `\nWHERE ${conditions.join(' AND ')}`;
    }
    
    const groupBy = formData.get('group_by');
    if (groupBy) {
        query += `\nGROUP BY ${groupBy}`;
    }
    
    const orderBy = formData.get('order_by');
    if (orderBy) {
        query += `\nORDER BY ${orderBy}`;
    }
    
    const limit = formData.get('limit');
    if (limit) {
        query += `\nLIMIT ${limit}`;
    }
    
    queryCode.textContent = query;
    queryPreview.style.display = 'block';
}

function showSaveModal() {
    const reportName = document.querySelector('input[name="report_name"]').value;
    const reportType = document.querySelector('select[name="report_type"]').value;
    
    if (!reportName) {
        ChurchCMS.showToast('Please enter a report name first', 'warning');
        return;
    }
    
    if (!reportType) {
        ChurchCMS.showToast('Please select a report type first', 'warning');
        return;
    }
    
    document.getElementById('saveReportName').value = reportName;
    document.getElementById('saveReportType').value = reportType;
    
    // Get current query
    previewQuery();
    const query = document.getElementById('queryCode').textContent;
    document.getElementById('saveQuery').value = query;
    
    // Get parameters
    const formData = new FormData(document.getElementById('reportBuilderForm'));
    const parameters = {};
    for (let [key, value] of formData.entries()) {
        parameters[key] = value;
    }
    document.getElementById('saveParameters').value = JSON.stringify(parameters);
    
    const modal = new bootstrap.Modal(document.getElementById('saveReportModal'));
    modal.show();
}

function loadSavedReport(reportId) {
    ChurchCMS.showLoading('Loading saved report...');
    
    fetch(`api/load_custom_report.php?id=${reportId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Load report parameters back into form
                const parameters = JSON.parse(data.report.parameters || '{}');
                
                // Fill form fields
                Object.keys(parameters).forEach(key => {
                    const element = document.querySelector(`[name="${key}"]`);
                    if (element) {
                        if (element.type === 'checkbox') {
                            element.checked = parameters[key];
                        } else {
                            element.value = parameters[key];
                        }
                    }
                });
                
                // Trigger table selection update
                updateFieldSelection();
                
                // Submit form to run the report
                document.getElementById('reportBuilderForm').submit();
            } else {
                ChurchCMS.showToast('Failed to load saved report', 'error');
            }
        })
        .catch(error => {
            console.error('Error loading saved report:', error);
            ChurchCMS.showToast('Error loading saved report', 'error');
        })
        .finally(() => {
            ChurchCMS.hideLoading();
        });
}

function deleteSavedReport(reportId) {
    ChurchCMS.showConfirm(
        'Are you sure you want to delete this saved report?',
        function() {
            fetch(`api/delete_custom_report.php?id=${reportId}`, {
                method: 'DELETE'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    ChurchCMS.showToast('Report deleted successfully', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    ChurchCMS.showToast('Failed to delete report', 'error');
                }
            })
            .catch(error => {
                console.error('Error deleting report:', error);
                ChurchCMS.showToast('Error deleting report', 'error');
            });
        }
    );
}

function loadTemplate(templateName) {
    const templates = {
        'member_list': {
            tables: ['members', 'member_departments', 'departments'],
            fields: ['members.member_number', 'members.first_name', 'members.last_name', 
                    'members.phone', 'members.email', 'members.join_date', 'departments.name'],
            report_type: 'members',
            report_name: 'Member List with Departments'
        },
        'financial_summary': {
            tables: ['income', 'income_categories'],
            fields: ['income_categories.name', 'COUNT(income.id)', 'SUM(income.amount)'],
            report_type: 'financial',
            report_name: 'Financial Summary by Category',
            group_by: 'income_categories.name'
        },
        'attendance_report': {
            tables: ['attendance_records', 'events', 'members'],
            fields: ['events.name', 'events.event_date', 'COUNT(attendance_records.id)', 
                    'members.first_name', 'members.last_name'],
            report_type: 'attendance',
            report_name: 'Event Attendance Report'
        },
        'visitor_analysis': {
            tables: ['visitors'],
            fields: ['visitors.first_name', 'visitors.last_name', 'visitors.phone', 
                    'visitors.visit_date', 'visitors.status', 'visitors.how_heard_about_us'],
            report_type: 'visitors',
            report_name: 'Visitor Analysis Report'
        }
    };
    
    const template = templates[templateName];
    if (!template) {
        ChurchCMS.showToast('Template not found', 'error');
        return;
    }
    
    // Clear form
    document.getElementById('reportBuilderForm').reset();
    
    // Apply template
    document.querySelector('input[name="report_name"]').value = template.report_name;
    document.querySelector('select[name="report_type"]').value = template.report_type;
    
    // Select tables
    template.tables.forEach(table => {
        const checkbox = document.querySelector(`input[value="${table}"]`);
        if (checkbox) checkbox.checked = true;
    });
    
    // Update field selection
    updateFieldSelection();
    
    // Select fields after a short delay to ensure field selection is updated
    setTimeout(() => {
        template.fields.forEach(field => {
            const fieldCheckbox = document.querySelector(`input[value="${field}"]`);
            if (fieldCheckbox) fieldCheckbox.checked = true;
        });
        
        // Set group by if specified
        if (template.group_by) {
            document.querySelector('select[name="group_by"]').value = template.group_by;
        }
    }, 100);
    
    ChurchCMS.showToast(`${template.report_name} template loaded`, 'success');
}

function exportResults(format) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'export_custom_report.php';
    
    const formatInput = document.createElement('input');
    formatInput.type = 'hidden';
    formatInput.name = 'format';
    formatInput.value = format;
    form.appendChild(formatInput);
    
    const queryInput = document.createElement('input');
    queryInput.type = 'hidden';
    queryInput.name = 'query';
    queryInput.value = document.getElementById('queryCode').textContent;
    form.appendChild(queryInput);
    
    const nameInput = document.createElement('input');
    nameInput.type = 'hidden';
    nameInput.name = 'report_name';
    nameInput.value = document.querySelector('input[name="report_name"]').value || 'Custom Report';
    form.appendChild(nameInput);
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
    
    ChurchCMS.showToast(`Exporting report as ${format.toUpperCase()}...`, 'info');
}

// Form validation
document.getElementById('reportBuilderForm').addEventListener('submit', function(e) {
    const tables = document.querySelectorAll('input[name="tables[]"]:checked');
    const fields = document.querySelectorAll('input[name="fields[]"]:checked');
    
    if (tables.length === 0) {
        e.preventDefault();
        ChurchCMS.showToast('Please select at least one table', 'warning');
        return;
    }
    
    if (fields.length === 0) {
        e.preventDefault();
        ChurchCMS.showToast('Please select at least one field', 'warning');
        return;
    }
    
    ChurchCMS.showLoading('Generating report...');
});

// Auto-save form data
document.addEventListener('input', function(e) {
    if (e.target.form && e.target.form.id === 'reportBuilderForm') {
        const formData = new FormData(e.target.form);
        const data = {};
        for (let [key, value] of formData.entries()) {
            data[key] = value;
        }
        localStorage.setItem('customReportBuilder', JSON.stringify(data));
    }
});

// Restore form data on page load
window.addEventListener('load', function() {
    const savedData = localStorage.getItem('customReportBuilder');
    if (savedData) {
        try {
            const data = JSON.parse(savedData);
            Object.keys(data).forEach(key => {
                const element = document.querySelector(`[name="${key}"]`);
                if (element) {
                    if (element.type === 'checkbox') {
                        element.checked = data[key];
                    } else {
                        element.value = data[key];
                    }
                }
            });
        } catch (error) {
            console.error('Error restoring form data:', error);
        }
    }
});
</script>

<?php include_once '../../includes/footer.php'; ?><?php
/**
 * Custom Reports Builder
 * Deliverance Church Management System
 * 
 * Allow users to create custom reports with dynamic queries
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
$page_icon = 'fas fa-chart-line';
$breadcrumb = [
    ['title' => 'Reports', 'url' => 'index.php'],
    ['title' => 'Custom Reports']
];

// Initialize database
$db = Database::getInstance();

// Handle form submission
$reportData = [];
$query = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = sanitizeInput($_POST['action'] ?? '');
        
        if ($action === 'build_report') {
            // Build custom report
            $reportName = sanitizeInput($_POST['report_name'] ?? '');
            $reportType = sanitizeInput($_POST['report_type'] ?? '');
            $fields = $_POST['fields'] ?? [];
            $tables = $_POST['tables'] ?? [];
            $conditions = $_POST['conditions'] ?? [];
            $groupBy = sanitizeInput($_POST['group_by'] ?? '');
            $orderBy = sanitizeInput($_POST['order_by'] ?? '');
            $limit = (int)($_POST['limit'] ?? 100);
            
            // Build SQL query
            $query = buildCustomQuery($reportType, $fields, $tables, $conditions, $groupBy, $orderBy, $limit);
            
            if ($query) {
                // Execute query
                $stmt = $db->executeQuery($query);
                $reportData = $stmt->fetchAll();
                
                // Log activity
                logActivity('Generated custom report', 'custom_reports', null, null, [
                    'report_name' => $reportName,
                    'query' => $query
                ]);
            }
            
        } elseif ($action === 'save_report') {
            // Save custom report
            $reportName = sanitizeInput($_POST['report_name'] ?? '');
            $description = sanitizeInput($_POST['description'] ?? '');
            $reportType = sanitizeInput($_POST['report_type'] ?? '');
            $sqlQuery = sanitizeInput($_POST['sql_query'] ?? '');
            $parameters = json_encode($_POST['parameters'] ?? []);
            $isPublic = isset($_POST['is_public']) ? 1 : 0;
            
            $result = insertRecord('custom_reports', [
                'name' => $reportName,
                'description' => $description,
                'report_type' => $reportType,
                'sql_query' => $sqlQuery,
                'parameters' => $parameters,
                'created_by' => $_SESSION['user_id'],
                'is_public' => $isPublic
            ]);
            
            if ($result) {
                setFlashMessage('success', 'Report saved successfully');
                header('Location: ' . BASE_URL . 'modules/reports/custom.php');
                exit();
            } else {
                $error = 'Failed to save report. Please try again.';
            }   
        }
    } catch (Exception $e) {
        $error = 'An error occurred: ' . $e->getMessage();
    }
}
// Fetch saved reports
$savedReports = $db->fetchAll('SELECT cr.*, u.first_name, u.last_name FROM custom_reports cr JOIN users u ON cr.created_by = u.id ORDER BY cr.created_at DESC');
// Function to build custom SQL query
function buildCustomQuery($reportType, $fields, $tables, $conditions, $groupBy, $orderBy, $limit) {
    if (empty($fields) || empty($tables)) {
        return '';
    }
    
    $selectFields = implode(', ', array_map('sanitizeInput', $fields));
    $fromTables = implode(', ', array_map('sanitizeInput', $tables));
    
    $query = "SELECT $selectFields FROM $fromTables";
    
    // Add conditions
    if (!empty($conditions)) {
        $whereClauses = [];
        foreach ($conditions as $cond) {
            $field = sanitizeInput($cond['field'] ?? '');
            $operator = sanitizeInput($cond['operator'] ?? '=');
            $value = sanitizeInput($cond['value'] ?? '');
            
            if ($field && $operator && $value !== '') {
                if (in_array($operator, ['LIKE', 'NOT LIKE'])) {
                    $value = "'%" . str_replace('%', '', $value) . "%'";
                } else {
                    $value = "'" . $value . "'";
                }
                $whereClauses[] = "$field $operator $value";
            }
        }
        
        if (!empty($whereClauses)) {
            $query .= ' WHERE ' . implode(' AND ', $whereClauses);
        }
    }
    
    // Add group by
    if ($groupBy) {
        $query .= ' GROUP BY ' . sanitizeInput($groupBy);
    }
    
    // Add order by
    if ($orderBy) {
        $query .= ' ORDER BY ' . sanitizeInput($orderBy);
    }
    
    // Add limit
    if ($limit > 0) {
        $query .= ' LIMIT ' . (int)$limit;
    } else {
        $query .= ' LIMIT 100'; // Default limit
    }
    
    return $query;
}   
// Function to get date range for quarters
function getQuarterDateRange($year, $quarter) {
    $start = new DateTime($year . '-' . ((($quarter - 1) * 3) + 1) . '-01');
    $end = clone $start;
    $end->modify('+3 months -1 day');
    return [$start->format('Y-m-d'), $end->format('Y-m-d')];
}   
// Function to get date range for quarters
function getQuarterDateRange($year, $quarter) {
    $start = new DateTime($year . '-' . ((($quarter - 1) * 3) + 1) . '-01');
    $end = clone $start;
    $end->modify('+3 months -1 day');
    return [$start->format('Y-m-d'), $end->format('Y-m-d')];
}   




