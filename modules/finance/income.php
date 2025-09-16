<?php
/**
 * Income Management
 * Deliverance Church Management System
 * 
 * View and manage all income transactions
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Check authentication and permissions
requireLogin();
if (!hasPermission('finance')) {
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit();
}

$page_title = 'Income Management';
$page_icon = 'fas fa-plus-circle';

// Get filter parameters
$category_filter = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$per_page = 25;
$offset = ($page - 1) * $per_page;

// Handle actions
if ($_POST['action'] ?? '' === 'verify_income') {
    $income_id = (int)$_POST['income_id'];
    $status = $_POST['status'] === 'verified' ? 'verified' : 'rejected';
    
    try {
        $db = Database::getInstance();
        $result = updateRecord('income', 
            [
                'status' => $status,
                'verified_by' => $_SESSION['user_id'],
                'verification_date' => getCurrentTimestamp()
            ],
            ['id' => $income_id]
        );
        
        if ($result) {
            logActivity('Income transaction ' . $status, 'income', $income_id);
            setFlashMessage('success', 'Income transaction ' . $status . ' successfully');
        } else {
            setFlashMessage('error', 'Failed to update income status');
        }
    } catch (Exception $e) {
        error_log("Error updating income status: " . $e->getMessage());
        setFlashMessage('error', 'An error occurred while updating income status');
    }
    
    header('Location: income.php');
    exit();
}

if ($_POST['action'] ?? '' === 'delete_income') {
    $income_id = (int)$_POST['income_id'];
    
    try {
        $db = Database::getInstance();
        
        // Get income details for logging
        $income = getRecord('income', 'id', $income_id);
        
        if ($income && deleteRecord('income', ['id' => $income_id])) {
            logActivity('Income transaction deleted', 'income', $income_id, $income);
            setFlashMessage('success', 'Income transaction deleted successfully');
        } else {
            setFlashMessage('error', 'Failed to delete income transaction');
        }
    } catch (Exception $e) {
        error_log("Error deleting income: " . $e->getMessage());
        setFlashMessage('error', 'An error occurred while deleting income transaction');
    }
    
    header('Location: income.php');
    exit();
}

try {
    $db = Database::getInstance();
    
    // Build query conditions
    $where_conditions = [];
    $params = [];
    
    if (!empty($category_filter)) {
        $where_conditions[] = "i.category_id = ?";
        $params[] = $category_filter;
    }
    
    if (!empty($status_filter)) {
        $where_conditions[] = "i.status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($date_from)) {
        $where_conditions[] = "i.transaction_date >= ?";
        $params[] = $date_from;
    }
    
    if (!empty($date_to)) {
        $where_conditions[] = "i.transaction_date <= ?";
        $params[] = $date_to;
    }
    
    if (!empty($search)) {
        $where_conditions[] = "(i.transaction_id LIKE ? OR i.source LIKE ? OR i.donor_name LIKE ? OR i.description LIKE ?)";
        $search_term = "%{$search}%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    // Get total count for pagination
    $count_sql = "
        SELECT COUNT(*) as total
        FROM income i
        JOIN income_categories ic ON i.category_id = ic.id
        {$where_clause}
    ";
    $count_stmt = $db->executeQuery($count_sql, $params);
    $total_records = $count_stmt->fetch()['total'];
    
    // Get income data
    $income_sql = "
        SELECT 
            i.*,
            ic.name as category_name,
            u1.first_name as recorded_by_name,
            u1.last_name as recorded_by_lastname,
            u2.first_name as verified_by_name,
            u2.last_name as verified_by_lastname
        FROM income i
        JOIN income_categories ic ON i.category_id = ic.id
        LEFT JOIN users u1 ON i.recorded_by = u1.id
        LEFT JOIN users u2 ON i.verified_by = u2.id
        {$where_clause}
        ORDER BY i.created_at DESC
        LIMIT {$per_page} OFFSET {$offset}
    ";
    
    $income_stmt = $db->executeQuery($income_sql, $params);
    $income_records = $income_stmt->fetchAll();
    
    // Get categories for filter
    $categories = getRecords('income_categories', ['is_active' => 1], 'name ASC');
    
    // Calculate pagination
    $pagination = generatePagination($total_records, $page, $per_page);
    
    // Get summary statistics
    $summary_params = $params; // Copy params for summary query
    $summary_sql = "
        SELECT 
            COUNT(*) as total_transactions,
            COALESCE(SUM(i.amount), 0) as total_amount,
            COUNT(CASE WHEN i.status = 'pending' THEN 1 END) as pending_count,
            COUNT(CASE WHEN i.status = 'verified' THEN 1 END) as verified_count,
            COUNT(CASE WHEN i.status = 'rejected' THEN 1 END) as rejected_count
        FROM income i
        JOIN income_categories ic ON i.category_id = ic.id
        {$where_clause}
    ";
    
    $summary_stmt = $db->executeQuery($summary_sql, $summary_params);
    $summary = $summary_stmt->fetch();
    
} catch (Exception $e) {
    error_log("Income management error: " . $e->getMessage());
    $income_records = [];
    $categories = [];
    $total_records = 0;
    $pagination = generatePagination(0, 1, $per_page);
    $summary = [
        'total_transactions' => 0,
        'total_amount' => 0,
        'pending_count' => 0,
        'verified_count' => 0,
        'rejected_count' => 0
    ];
}

$breadcrumb = [
    ['title' => 'Finance', 'url' => 'index.php'],
    ['title' => 'Income Management']
];

$page_actions = [
    [
        'title' => 'Add Income',
        'url' => 'add_income.php',
        'icon' => 'fas fa-plus',
        'class' => 'success'
    ],
    [
        'title' => 'Export Data',
        'url' => 'export_income.php?' . http_build_query($_GET),
        'icon' => 'fas fa-download',
        'class' => 'info'
    ]
];

$additional_js = ['assets/js/datatables.min.js'];

include '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Summary Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stats-card">
                <div class="d-flex align-items-center">
                    <div class="stats-icon bg-success text-white">
                        <i class="fas fa-list"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="stats-number"><?php echo number_format($summary['total_transactions']); ?></div>
                        <div class="stats-label">Total Transactions</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stats-card">
                <div class="d-flex align-items-center">
                    <div class="stats-icon bg-primary text-white">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="stats-number"><?php echo formatCurrency($summary['total_amount']); ?></div>
                        <div class="stats-label">Total Amount</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stats-card">
                <div class="d-flex align-items-center">
                    <div class="stats-icon bg-warning text-white">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="stats-number"><?php echo number_format($summary['pending_count']); ?></div>
                        <div class="stats-label">Pending Verification</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stats-card">
                <div class="d-flex align-items-center">
                    <div class="stats-icon bg-info text-white">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="stats-number"><?php echo number_format($summary['verified_count']); ?></div>
                        <div class="stats-label">Verified</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="category" class="form-label">Category</label>
                    <select class="form-select" id="category" name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" 
                                    <?php echo ($category_filter == $category['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo ($status_filter === 'pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="verified" <?php echo ($status_filter === 'verified') ? 'selected' : ''; ?>>Verified</option>
                        <option value="rejected" <?php echo ($status_filter === 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="date_from" class="form-label">From Date</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                
                <div class="col-md-2">
                    <label for="date_to" class="form-label">To Date</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                
                <div class="col-md-3">
                    <label for="search" class="form-label">Search</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="search" name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Transaction ID, Source, Donor...">
                        <button class="btn btn-church-primary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                
                <div class="col-12">
                    <a href="income.php" class="btn btn-secondary">
                        <i class="fas fa-times me-2"></i>Clear Filters
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Income Records Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-list me-2"></i>Income Records
                <?php if ($total_records > 0): ?>
                    <span class="badge bg-primary ms-2"><?php echo number_format($total_records); ?></span>
                <?php endif; ?>
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Transaction ID</th>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Source/Donor</th>
                            <th class="text-end">Amount</th>
                            <th>Payment Method</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($income_records)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="fas fa-info-circle fa-2x mb-3"></i><br>
                                    No income records found matching your criteria.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($income_records as $record): ?>
                                <tr>
                                    <td>
                                        <code class="copy-to-clipboard" data-copy="<?php echo $record['transaction_id']; ?>">
                                            <?php echo htmlspecialchars($record['transaction_id']); ?>
                                        </code>
                                    </td>
                                    <td>
                                        <strong><?php echo formatDisplayDate($record['transaction_date']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo timeAgo($record['created_at']); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo htmlspecialchars($record['category_name']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($record['donor_name']): ?>
                                            <strong><?php echo htmlspecialchars($record['donor_name']); ?></strong><br>
                                        <?php endif; ?>
                                        <span class="text-muted">
                                            <?php echo htmlspecialchars($record['source'] ?: 'Not specified'); ?>
                                        </span>
                                        <?php if ($record['donor_phone']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($record['donor_phone']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <strong class="text-success fs-6">
                                            <?php echo formatCurrency($record['amount']); ?>
                                        </strong>
                                        <?php if ($record['is_pledge']): ?>
                                            <br><small class="badge bg-warning">Pledge</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php echo getPaymentMethodDisplay($record['payment_method']); ?>
                                        </span>
                                        <?php if ($record['reference_number']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($record['reference_number']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_config = [
                                            'pending' => ['class' => 'warning', 'icon' => 'clock'],
                                            'verified' => ['class' => 'success', 'icon' => 'check-circle'],
                                            'rejected' => ['class' => 'danger', 'icon' => 'times-circle']
                                        ];
                                        $config = $status_config[$record['status']] ?? ['class' => 'secondary', 'icon' => 'question'];
                                        ?>
                                        <span class="badge bg-<?php echo $config['class']; ?>">
                                            <i class="fas fa-<?php echo $config['icon']; ?> me-1"></i>
                                            <?php echo ucfirst($record['status']); ?>
                                        </span>
                                        <?php if ($record['verified_by_name']): ?>
                                            <br><small class="text-muted">
                                                by <?php echo htmlspecialchars($record['verified_by_name'] . ' ' . $record['verified_by_lastname']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <!-- View Details -->
                                            <button type="button" class="btn btn-outline-info" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#viewIncomeModal"
                                                    onclick="loadIncomeDetails(<?php echo $record['id']; ?>)"
                                                    title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            <!-- Edit -->
                                            <?php if ($record['status'] === 'pending' || $_SESSION['user_role'] === 'administrator'): ?>
                                                <a href="edit_income.php?id=<?php echo $record['id']; ?>" 
                                                   class="btn btn-outline-primary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <!-- Verify/Reject (for pending transactions) -->
                                            <?php if ($record['status'] === 'pending' && ($_SESSION['user_role'] === 'administrator' || $_SESSION['user_role'] === 'pastor' || $_SESSION['user_role'] === 'finance_officer')): ?>
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-outline-success" 
                                                            onclick="verifyIncome(<?php echo $record['id']; ?>, 'verified')"
                                                            title="Verify">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger" 
                                                            onclick="verifyIncome(<?php echo $record['id']; ?>, 'rejected')"
                                                            title="Reject">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <!-- Delete -->
                                            <?php if ($_SESSION['user_role'] === 'administrator'): ?>
                                                <button type="button" class="btn btn-outline-danger" 
                                                        onclick="deleteIncome(<?php echo $record['id']; ?>, '<?php echo htmlspecialchars($record['transaction_id']); ?>')"
                                                        title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($pagination['total_pages'] > 1): ?>
                <div class="card-footer">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-muted">
                            Showing <?php echo number_format($offset + 1); ?> to 
                            <?php echo number_format(min($offset + $per_page, $total_records)); ?> of 
                            <?php echo number_format($total_records); ?> records
                        </div>
                        <?php echo generatePaginationHTML($pagination, '?'); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- View Income Details Modal -->
<div class="modal fade" id="viewIncomeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-church-blue text-white">
                <h5 class="modal-title">
                    <i class="fas fa-eye me-2"></i>Income Transaction Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="incomeDetailsContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden forms for actions -->
<form id="verifyIncomeForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="verify_income">
    <input type="hidden" name="income_id" id="verify_income_id">
    <input type="hidden" name="status" id="verify_status">
</form>

<form id="deleteIncomeForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete_income">
    <input type="hidden" name="income_id" id="delete_income_id">
</form>

<script>
// Load income details in modal
function loadIncomeDetails(incomeId) {
    const content = document.getElementById('incomeDetailsContent');
    
    // Show loading
    content.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    `;
    
    // Fetch income details
    fetch(BASE_URL + 'api/finance.php?action=get_income&id=' + incomeId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const income = data.data;
                content.innerHTML = `
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="fw-bold text-church-blue">Transaction Information</h6>
                            <table class="table table-sm">
                                <tr><td><strong>Transaction ID:</strong></td><td>${income.transaction_id}</td></tr>
                                <tr><td><strong>Date:</strong></td><td>${ChurchCMS.formatDate(income.transaction_date)}</td></tr>
                                <tr><td><strong>Category:</strong></td><td>${income.category_name}</td></tr>
                                <tr><td><strong>Amount:</strong></td><td class="text-success fw-bold">${ChurchCMS.formatCurrency(income.amount)}</td></tr>
                                <tr><td><strong>Payment Method:</strong></td><td>${income.payment_method}</td></tr>
                                <tr><td><strong>Reference:</strong></td><td>${income.reference_number || '-'}</td></tr>
                                <tr><td><strong>Status:</strong></td><td><span class="badge bg-${income.status === 'verified' ? 'success' : income.status === 'rejected' ? 'danger' : 'warning'}">${income.status}</span></td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-bold text-church-blue">Donor Information</h6>
                            <table class="table table-sm">
                                <tr><td><strong>Name:</strong></td><td>${income.donor_name || 'Anonymous'}</td></tr>
                                <tr><td><strong>Phone:</strong></td><td>${income.donor_phone || '-'}</td></tr>
                                <tr><td><strong>Email:</strong></td><td>${income.donor_email || '-'}</td></tr>
                                <tr><td><strong>Source:</strong></td><td>${income.source || '-'}</td></tr>
                                <tr><td><strong>Is Pledge:</strong></td><td>${income.is_pledge ? 'Yes' : 'No'}</td></tr>
                                ${income.pledge_period ? `<tr><td><strong>Pledge Period:</strong></td><td>${income.pledge_period}</td></tr>` : ''}
                            </table>
                        </div>
                    </div>
                    ${income.description ? `
                        <div class="mt-3">
                            <h6 class="fw-bold text-church-blue">Description</h6>
                            <p class="mb-0">${income.description}</p>
                        </div>
                    ` : ''}
                    ${income.notes ? `
                        <div class="mt-3">
                            <h6 class="fw-bold text-church-blue">Notes</h6>
                            <p class="mb-0">${income.notes}</p>
                        </div>
                    ` : ''}
                    <div class="mt-3">
                        <h6 class="fw-bold text-church-blue">System Information</h6>
                        <table class="table table-sm">
                            <tr><td><strong>Recorded by:</strong></td><td>${income.recorded_by_name || '-'}</td></tr>
                            <tr><td><strong>Recorded at:</strong></td><td>${ChurchCMS.formatDate(income.created_at, true)}</td></tr>
                            ${income.verified_by_name ? `<tr><td><strong>Verified by:</strong></td><td>${income.verified_by_name}</td></tr>` : ''}
                            ${income.verification_date ? `<tr><td><strong>Verified at:</strong></td><td>${ChurchCMS.formatDate(income.verification_date, true)}</td></tr>` : ''}
                        </table>
                    </div>
                `;
            } else {
                content.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        Error loading income details: ${data.message || 'Unknown error'}
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            content.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    Failed to load income details. Please try again.
                </div>
            `;
        });
}

// Verify income transaction
function verifyIncome(incomeId, status) {
    const action = status === 'verified' ? 'verify' : 'reject';
    const message = `Are you sure you want to ${action} this income transaction?`;
    
    ChurchCMS.showConfirm(message, function() {
        document.getElementById('verify_income_id').value = incomeId;
        document.getElementById('verify_status').value = status;
        document.getElementById('verifyIncomeForm').submit();
    }, null, `${action.charAt(0).toUpperCase() + action.slice(1)} Income Transaction`);
}

// Delete income transaction
function deleteIncome(incomeId, transactionId) {
    const message = `Are you sure you want to delete income transaction ${transactionId}? This action cannot be undone.`;
    
    ChurchCMS.showConfirm(message, function() {
        document.getElementById('delete_income_id').value = incomeId;
        document.getElementById('deleteIncomeForm').submit();
    }, null, 'Delete Income Transaction');
}

// Auto-submit form when filters change
document.addEventListener('DOMContentLoaded', function() {
    const filterInputs = document.querySelectorAll('#category, #status, #date_from, #date_to');
    
    filterInputs.forEach(input => {
        input.addEventListener('change', ChurchCMS.debounce(function() {
            this.form.submit();
        }, 500));
    });
    
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<?php include '../../includes/footer.php'; ?>