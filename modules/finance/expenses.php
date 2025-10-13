<?php
/**
 * Expense Management
 * Track and manage church expenses with approval workflow
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

requireLogin();
if (!hasPermission('finance')) {
    setFlashMessage('error', 'You do not have permission to access this page');
    redirect(BASE_URL . 'modules/dashboard/');
}

$db = Database::getInstance();
$userRole = $_SESSION['user_role'];
$userId   = $_SESSION['user_id'];

// -----------------------------
// Handle AJAX requests
// -----------------------------
if (isAjaxRequest()) {
    // Action can come in via GET (e.g. get_expense) or POST (e.g. approve/reject)
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    // Normalize / guard common inputs
    $id = isset($_GET['id']) ? (int)$_GET['id'] : ((isset($_POST['id']) ? (int)$_POST['id'] : 0));

    if ($action === 'get_expense') {
        if ($id <= 0) {
            sendJSONResponse(['success' => false, 'message' => 'Invalid expense ID']);
        }

        // You can expand this to JOIN for names if needed
        $expense = getRecord('expenses', 'id', $id);
        if (!$expense) {
            sendJSONResponse(['success' => false, 'message' => 'Expense not found']);
        }
        sendJSONResponse(['success' => true, 'data' => $expense]);
    }

    if ($action === 'approve_expense') {
        if (!in_array($userRole, ['administrator', 'pastor', 'finance_officer'], true)) {
            sendJSONResponse(['success' => false, 'message' => 'Insufficient permissions to approve expenses']);
        }
        if ($id <= 0) {
            sendJSONResponse(['success' => false, 'message' => 'Invalid expense ID']);
        }

        $data = [
            'status'        => 'approved',
            'approved_by'   => $userId,
            'approval_date' => date('Y-m-d H:i:s'),
        ];

        $result = updateRecord('expenses', $data, ['id' => $id]);

        if ($result) {
            logActivity('Approved expense', 'expenses', $id);
            sendJSONResponse(['success' => true, 'message' => 'Expense approved successfully']);
        } else {
            sendJSONResponse(['success' => false, 'message' => 'Failed to approve expense']);
        }
    }

    if ($action === 'reject_expense') {
        if (!in_array($userRole, ['administrator', 'pastor', 'finance_officer'], true)) {
            sendJSONResponse(['success' => false, 'message' => 'Insufficient permissions']);
        }
        if ($id <= 0) {
            sendJSONResponse(['success' => false, 'message' => 'Invalid expense ID']);
        }

        $result = updateRecord('expenses', ['status' => 'rejected'], ['id' => $id]);

        if ($result) {
            logActivity('Rejected expense', 'expenses', $id);
            sendJSONResponse(['success' => true, 'message' => 'Expense rejected']);
        } else {
            sendJSONResponse(['success' => false, 'message' => 'Failed to reject expense']);
        }
    }

    if ($action === 'mark_paid') {
        if ($id <= 0) {
            sendJSONResponse(['success' => false, 'message' => 'Invalid expense ID']);
        }

        $data = [
            'status'       => 'paid',
            'paid_by'      => $userId,
            'payment_date' => date('Y-m-d H:i:s'),
        ];

        $result = updateRecord('expenses', $data, ['id' => $id]);

        if ($result) {
            logActivity('Marked expense as paid', 'expenses', $id);
            sendJSONResponse(['success' => true, 'message' => 'Expense marked as paid']);
        } else {
            sendJSONResponse(['success' => false, 'message' => 'Failed to update expense']);
        }
    }

    if ($action === 'delete_expense') {
        if ($userRole !== 'administrator') {
            sendJSONResponse(['success' => false, 'message' => 'Only administrators can delete expenses']);
        }
        if ($id <= 0) {
            sendJSONResponse(['success' => false, 'message' => 'Invalid expense ID']);
        }

        $result = deleteRecord('expenses', ['id' => $id]);

        if ($result) {
            logActivity('Deleted expense', 'expenses', $id);
            sendJSONResponse(['success' => true, 'message' => 'Expense deleted successfully']);
        } else {
            sendJSONResponse(['success' => false, 'message' => 'Failed to delete expense']);
        }
    }

    exit;
}

// -----------------------------
// Handle form submissions (non-AJAX)
// -----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isAjaxRequest()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_expense') {
        // Handle receipt upload
        $receiptPath = null;
        if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = handleFileUpload(
                $_FILES['receipt'],
                ASSETS_PATH . 'uploads/receipts/',
                ALLOWED_DOCUMENT_TYPES + ALLOWED_IMAGE_TYPES, // assuming arrays with compatible keys
                MAX_DOCUMENT_SIZE
            );

            if (!empty($uploadResult['success'])) {
                $receiptPath = 'assets/uploads/receipts/' . $uploadResult['filename'];
            } else {
                // Optional: surface upload error to user
                // setFlashMessage('error', $uploadResult['message'] ?? 'Receipt upload failed');
            }
        }

        $data = [
            'transaction_id'  => generateTransactionId('EXP'),
            'category_id'     => (int)($_POST['category_id'] ?? 0),
            'amount'          => (float)($_POST['amount'] ?? 0),
            'currency'        => DEFAULT_CURRENCY,
            'vendor_name'     => sanitizeInput($_POST['vendor_name'] ?? ''),
            'vendor_contact'  => sanitizeInput($_POST['vendor_contact'] ?? ''),
            'payment_method'  => sanitizeInput($_POST['payment_method'] ?? ''),
            'reference_number'=> sanitizeInput($_POST['reference_number'] ?? ''),
            'description'     => sanitizeInput($_POST['description'] ?? ''),
            'expense_date'    => sanitizeInput($_POST['expense_date'] ?? date('Y-m-d')),
            'receipt_number'  => sanitizeInput($_POST['receipt_number'] ?? ''),
            'receipt_path'    => $receiptPath,
            'requested_by'    => $userId,
            'status'          => 'pending',
            'notes'           => sanitizeInput($_POST['notes'] ?? ''),
        ];

        $result = insertRecord('expenses', $data);

        if ($result) {
            logActivity('Added new expense: ' . $data['description'], 'expenses', $result);
            setFlashMessage('success', 'Expense recorded successfully. Awaiting approval.');
        } else {
            setFlashMessage('error', 'Failed to record expense');
        }

        redirect($_SERVER['PHP_SELF']);
    }
}

// -----------------------------
// Filters & fetch data
// -----------------------------
$status     = $_GET['status']  ?? 'all';
$month      = $_GET['month']   ?? date('Y-m');
$categoryId = $_GET['category']?? 'all';

$whereConditions = [];
$params = [];

if ($status !== 'all') {
    $whereConditions[] = "e.status = ?";
    $params[] = $status;
}

if (!empty($month)) {
    $whereConditions[] = "DATE_FORMAT(e.expense_date, '%Y-%m') = ?";
    $params[] = $month;
}

if ($categoryId !== 'all') {
    $whereConditions[] = "e.category_id = ?";
    $params[] = (int)$categoryId;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

$stmt = $db->executeQuery("
    SELECT 
        e.*,
        ec.name AS category_name,
        CONCAT(u1.first_name, ' ', u1.last_name) AS requested_by_name,
        CONCAT(u2.first_name, ' ', u2.last_name) AS approved_by_name,
        CONCAT(u3.first_name, ' ', u3.last_name) AS paid_by_name
    FROM expenses e
    JOIN expense_categories ec ON e.category_id = ec.id
    LEFT JOIN users u1 ON e.requested_by = u1.id
    LEFT JOIN users u2 ON e.approved_by = u2.id
    LEFT JOIN users u3 ON e.paid_by = u3.id
    $whereClause
    ORDER BY e.expense_date DESC, e.created_at DESC
", $params);
$expenses = $stmt->fetchAll();

// Categories for filters/forms
$categories = getRecords('expense_categories', ['is_active' => 1], 'name');

// Totals (based on current filtered list)
$totalPending  = 0;
$totalApproved = 0;
$totalPaid     = 0;
$totalRejected = 0;

foreach ($expenses as $expense) {
    switch ($expense['status']) {
        case 'pending':
            $totalPending += (float)$expense['amount'];
            break;
        case 'approved':
            $totalApproved += (float)$expense['amount'];
            break;
        case 'paid':
            $totalPaid += (float)$expense['amount'];
            break;
        case 'rejected':
            $totalRejected += (float)$expense['amount'];
            break;
    }
}

// Monthly summary (paid this month)
$stmt = $db->executeQuery("
    SELECT 
        SUM(amount) AS total_amount,
        COUNT(*)    AS count
    FROM expenses
    WHERE DATE_FORMAT(expense_date, '%Y-%m') = ?
      AND status = 'paid'
", [date('Y-m')]);
$monthlyTotal = $stmt->fetch();

$page_title = 'Expense Management';
$page_icon  = 'fas fa-money-bill-wave';
$breadcrumb = [
    ['title' => 'Finance', 'url' => BASE_URL . 'modules/finance/'],
    ['title' => 'Expenses'],
];

include '../../includes/header.php';
?>
<!-- Action Bar -->
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <p class="text-muted mb-0">Track and manage church expenses with approval workflow</p>
            </div>
            <button class="btn btn-church-primary" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                <i class="fas fa-plus me-2"></i>Record Expense
            </button>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="stats-icon bg-warning bg-opacity-10 text-warning">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="stats-number"><?php echo formatCurrency($totalPending); ?></div>
                        <div class="stats-label">Pending Approval</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="stats-icon bg-info bg-opacity-10 text-info">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="stats-number"><?php echo formatCurrency($totalApproved); ?></div>
                        <div class="stats-label">Approved</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="stats-icon bg-success bg-opacity-10 text-success">
                            <i class="fas fa-money-check-alt"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="stats-number"><?php echo formatCurrency($totalPaid); ?></div>
                        <div class="stats-label">Paid</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="stats-icon bg-primary bg-opacity-10 text-primary">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="stats-number"><?php echo formatCurrency($monthlyTotal['total_amount'] ?? 0); ?></div>
                        <div class="stats-label">This Month</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Bar -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="all"      <?php echo $status === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="pending"  <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="paid"     <?php echo $status === 'paid' ? 'selected' : ''; ?>>Paid</option>
                            <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Month</label>
                        <input type="month" class="form-control" name="month" value="<?php echo htmlspecialchars($month); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Category</label>
                        <select class="form-select" name="category">
                            <option value="all">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo (int)$cat['id']; ?>" <?php echo ((string)$categoryId === (string)$cat['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-church-primary w-100">
                            <i class="fas fa-filter me-2"></i>Apply Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Expenses Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 text-church-blue">
            <i class="fas fa-list me-2"></i>Expense Records
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle" id="expensesTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Transaction ID</th>
                        <th>Description</th>
                        <th>Category</th>
                        <th>Vendor</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($expenses as $expense): ?>
                    <?php
                        $statusColors = [
                            'pending'  => 'warning',
                            'approved' => 'info',
                            'paid'     => 'success',
                            'rejected' => 'danger',
                        ];
                        $statusColor = $statusColors[$expense['status']] ?? 'secondary';
                    ?>
                    <tr>
                        <td><?php echo date('M d, Y', strtotime($expense['expense_date'])); ?></td>
                        <td><small class="font-monospace"><?php echo htmlspecialchars($expense['transaction_id']); ?></small></td>
                        <td>
                            <div>
                                <strong><?php echo htmlspecialchars($expense['description']); ?></strong>
                                <?php if (!empty($expense['reference_number'])): ?>
                                    <br><small class="text-muted">Ref: <?php echo htmlspecialchars($expense['reference_number']); ?></small>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-secondary"><?php echo htmlspecialchars($expense['category_name']); ?></span>
                        </td>
                        <td>
                            <small>
                                <?php echo htmlspecialchars($expense['vendor_name']); ?>
                                <?php if (!empty($expense['vendor_contact'])): ?>
                                    <br><span class="text-muted"><?php echo htmlspecialchars($expense['vendor_contact']); ?></span>
                                <?php endif; ?>
                            </small>
                        </td>
                        <td>
                            <strong><?php echo formatCurrency($expense['amount']); ?></strong>
                            <br><small class="text-muted"><?php echo ucfirst($expense['payment_method']); ?></small>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $statusColor; ?>">
                                <?php echo ucfirst($expense['status']); ?>
                            </span>
                            <?php if ($expense['status'] === 'approved' && !empty($expense['approved_by_name'])): ?>
                                <br><small class="text-muted">By: <?php echo htmlspecialchars($expense['approved_by_name']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-info" onclick="viewExpense(<?php echo (int)$expense['id']; ?>)" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>

                                <?php if ($expense['status'] === 'pending' && in_array($userRole, ['administrator', 'pastor', 'finance_officer'], true)): ?>
                                    <button class="btn btn-outline-success" onclick="approveExpense(<?php echo (int)$expense['id']; ?>)" title="Approve">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button class="btn btn-outline-danger" onclick="rejectExpense(<?php echo (int)$expense['id']; ?>)" title="Reject">
                                        <i class="fas fa-times"></i>
                                    </button>
                                <?php endif; ?>

                                <?php if ($expense['status'] === 'approved'): ?>
                                    <button class="btn btn-outline-success" onclick="markPaid(<?php echo (int)$expense['id']; ?>)" title="Mark as Paid">
                                        <i class="fas fa-dollar-sign"></i>
                                    </button>
                                <?php endif; ?>

                                <?php if (!empty($expense['receipt_path'])): ?>
                                    <a href="<?php echo BASE_URL . $expense['receipt_path']; ?>" class="btn btn-outline-secondary" target="_blank" title="View Receipt">
                                        <i class="fas fa-file"></i>
                                    </a>
                                <?php endif; ?>

                                <?php if ($userRole === 'administrator'): ?>
                                    <button class="btn btn-outline-danger" onclick="deleteExpense(<?php echo (int)$expense['id']; ?>)" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Expense Modal -->
<div class="modal fade" id="addExpenseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_expense">
                <div class="modal-header bg-church-blue text-white">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Record New Expense</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            <select class="form-select" name="category_id" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo (int)$cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Amount (<?php echo CURRENCY_SYMBOL; ?>) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="amount" step="0.01" min="0" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="description" rows="2" required placeholder="What is this expense for?"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Vendor Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="vendor_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Vendor Contact</label>
                            <input type="text" class="form-control" name="vendor_contact" placeholder="Phone or Email">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Expense Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="expense_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                            <select class="form-select" name="payment_method" required>
                                <option value="">Select Method</option>
                                <?php foreach (PAYMENT_METHODS as $key => $label): ?>
                                    <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Reference Number</label>
                            <input type="text" class="form-control" name="reference_number" placeholder="Cheque #, Ref #">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Receipt Number</label>
                            <input type="text" class="form-control" name="receipt_number">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Upload Receipt</label>
                            <input type="file" class="form-control" name="receipt" accept=".pdf,.jpg,.jpeg,.png">
                            <small class="text-muted">Max 5MB (PDF, JPG, PNG)</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Additional Notes</label>
                        <textarea class="form-control" name="notes" rows="2"></textarea>
                    </div>

                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> This expense will require approval before it can be marked as paid.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-church-primary">
                        <i class="fas fa-save me-2"></i>Record Expense
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Expense Modal -->
<div class="modal fade" id="viewExpenseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-church-blue text-white">
                <h5 class="modal-title"><i class="fas fa-file-invoice-dollar me-2"></i>Expense Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewExpenseContent">
                <!-- Content loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Expose BASE_URL for JS if not already present globally
window.BASE_URL = window.BASE_URL || '<?php echo BASE_URL; ?>';

$(document).ready(function() {
    $('#expensesTable').DataTable({
        order: [[0, 'desc']],
        pageLength: 25,
        responsive: true
    });
});

function viewExpense(id) {
    $.ajax({
        url: '?action=get_expense&id=' + encodeURIComponent(id),
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const exp = response.data;
                const statusClass = exp.status === 'paid' ? 'success'
                                  : exp.status === 'approved' ? 'info'
                                  : exp.status === 'rejected' ? 'danger'
                                  : 'warning';

                const content = `
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <strong>Transaction ID:</strong><br>
                            <span class="font-monospace">${exp.transaction_id}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Status:</strong><br>
                            <span class="badge bg-${statusClass}">${String(exp.status || '').toUpperCase()}</span>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <strong>Amount:</strong><br>
                            <h4 class="text-church-red">${typeof formatCurrency === 'function' ? formatCurrency(exp.amount) : (exp.amount ?? 0)}</h4>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Expense Date:</strong><br>
                            ${exp.expense_date ? new Date(exp.expense_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : ''}
                        </div>
                    </div>
                    <div class="mb-3">
                        <strong>Description:</strong><br>
                        ${exp.description ?? ''}
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <strong>Vendor:</strong><br>
                            ${exp.vendor_name ?? ''}<br>
                            ${exp.vendor_contact ? '<small class="text-muted">' + exp.vendor_contact + '</small>' : ''}
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Payment Method:</strong><br>
                            ${(exp.payment_method || '').replace('_',' ').toUpperCase()}
                            ${exp.reference_number ? '<br><small>Ref: ' + exp.reference_number + '</small>' : ''}
                        </div>
                    </div>
                    ${exp.notes ? '<div class="mb-3"><strong>Notes:</strong><br>' + exp.notes + '</div>' : ''}
                    ${exp.receipt_path ? '<div class="mb-3"><strong>Receipt:</strong><br><a href="' + BASE_URL + exp.receipt_path + '" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-file me-2"></i>View Receipt</a></div>' : ''}
                `;
                $('#viewExpenseContent').html(content);
                $('#viewExpenseModal').modal('show');
            } else {
                ChurchCMS.showToast(response.message || 'Failed to load expense', 'error');
            }
        },
        error: function() {
            ChurchCMS.showToast('Failed to load expense', 'error');
        }
    });
}

function approveExpense(id) {
    ChurchCMS.showConfirm('Approve this expense?', function() {
        $.ajax({
            url: '?action=approve_expense',
            method: 'POST',
            data: { id: id },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    ChurchCMS.showToast(response.message, 'success');
                    setTimeout(() => location.reload(), 1200);
                } else {
                    ChurchCMS.showToast(response.message, 'error');
                }
            },
            error: function() {
                ChurchCMS.showToast('Request failed', 'error');
            }
        });
    });
}

function rejectExpense(id) {
    ChurchCMS.showConfirm('Reject this expense? This action cannot be undone.', function() {
        $.ajax({
            url: '?action=reject_expense',
            method: 'POST',
            data: { id: id },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    ChurchCMS.showToast(response.message, 'success');
                    setTimeout(() => location.reload(), 1200);
                } else {
                    ChurchCMS.showToast(response.message, 'error');
                }
            },
            error: function() {
                ChurchCMS.showToast('Request failed', 'error');
            }
        });
    });
}

function markPaid(id) {
    ChurchCMS.showConfirm('Mark this expense as paid?', function() {
        $.ajax({
            url: '?action=mark_paid',
            method: 'POST',
            data: { id: id },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    ChurchCMS.showToast(response.message, 'success');
                    setTimeout(() => location.reload(), 1200);
                } else {
                    ChurchCMS.showToast(response.message, 'error');
                }
            },
            error: function() {
                ChurchCMS.showToast('Request failed', 'error');
            }
        });
    });
}

function deleteExpense(id) {
    ChurchCMS.showConfirm('Are you sure you want to delete this expense? This action cannot be undone.', function() {
        $.ajax({
            url: '?action=delete_expense',
            method: 'POST',
            data: { id: id },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    ChurchCMS.showToast(response.message, 'success');
                    setTimeout(() => location.reload(), 1200);
                } else {
                    ChurchCMS.showToast(response.message, 'error');
                }
            },
            error: function() {
                ChurchCMS.showToast('Request failed', 'error');
            }
        });
    });
}
</script>

<?php include '../../includes/footer.php'; ?>
