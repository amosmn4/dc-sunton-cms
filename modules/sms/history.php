<?php
/**
 * Communication History Page
 * Deliverance Church Management System
 * 
 * View history of all SMS/Communication sent
 */

// Include necessary files
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Check authentication and permissions
requireLogin();
if (!hasPermission('sms') && !in_array($_SESSION['user_role'], ['administrator', 'pastor', 'secretary'])) {
    setFlashMessage('error', 'You do not have permission to view communication history.');
    redirect(BASE_URL . 'modules/dashboard/');
}

// Page settings
$page_title = 'Communication History';
$page_icon = 'fas fa-history';
$breadcrumb = [
    ['title' => 'Communication Center', 'url' => BASE_URL . 'modules/sms/'],
    ['title' => 'Communication History']
];

$db = Database::getInstance();

// Get filters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$sent_by_filter = isset($_GET['sent_by']) ? (int)$_GET['sent_by'] : 0;

$recordsPerPage = 25;
$offset = ($page - 1) * $recordsPerPage;

// Build query conditions
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(sh.message LIKE ? OR sh.batch_id LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($status_filter)) {
    $conditions[] = "sh.status = ?";
    $params[] = $status_filter;
}

if (!empty($date_from)) {
    $conditions[] = "DATE(sh.sent_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $conditions[] = "DATE(sh.sent_at) <= ?";
    $params[] = $date_to;
}

if ($sent_by_filter > 0) {
    $conditions[] = "sh.sent_by = ?";
    $params[] = $sent_by_filter;
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Get total count
$totalQuery = "SELECT COUNT(*) as total FROM sms_history sh $whereClause";
$totalResult = $db->executeQuery($totalQuery, $params);
$totalRecords = $totalResult->fetchColumn();

// Get communication history
$historyQuery = "
    SELECT 
        sh.*,
        CONCAT(u.first_name, ' ', u.last_name) as sent_by_name,
        u.role as sent_by_role
    FROM sms_history sh
    LEFT JOIN users u ON sh.sent_by = u.id
    $whereClause
    ORDER BY sh.sent_at DESC
    LIMIT $recordsPerPage OFFSET $offset
";

$communications = $db->executeQuery($historyQuery, $params)->fetchAll();

// Get users for filter
$users = $db->executeQuery("
    SELECT id, CONCAT(first_name, ' ', last_name) as full_name 
    FROM users 
    WHERE id IN (SELECT DISTINCT sent_by FROM sms_history WHERE sent_by IS NOT NULL)
    ORDER BY first_name, last_name
")->fetchAll();

// Get communication statistics
$stats = [
    'total_sent' => $db->executeQuery("SELECT SUM(sent_count) FROM sms_history")->fetchColumn() ?: 0,
    'total_failed' => $db->executeQuery("SELECT SUM(failed_count) FROM sms_history")->fetchColumn() ?: 0,
    'total_cost' => $db->executeQuery("SELECT SUM(cost) FROM sms_history")->fetchColumn() ?: 0,
    'this_month' => $db->executeQuery("SELECT COUNT(*) FROM sms_history WHERE MONTH(sent_at) = MONTH(NOW()) AND YEAR(sent_at) = YEAR(NOW())")->fetchColumn() ?: 0
];

// Generate pagination
$pagination = generatePagination($totalRecords, $page, $recordsPerPage);

// Include header
include_once '../../includes/header.php';
?>

<div class="row mb-4">
    <!-- Statistics Cards -->
    <div class="col-md-3 mb-3">
        <div class="card bg-primary text-white shadow">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-uppercase mb-1">Total Sent</div>
                        <div class="h5 mb-0 font-weight-bold"><?php echo number_format($stats['total_sent']); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-paper-plane fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card bg-danger text-white shadow">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-uppercase mb-1">Failed</div>
                        <div class="h5 mb-0 font-weight-bold"><?php echo number_format($stats['total_failed']); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-exclamation-triangle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card bg-success text-white shadow">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-uppercase mb-1">Total Cost</div>
                        <div class="h5 mb-0 font-weight-bold"><?php echo formatCurrency($stats['total_cost']); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-money-bill-wave fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card bg-info text-white shadow">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-uppercase mb-1">This Month</div>
                        <div class="h5 mb-0 font-weight-bold"><?php echo number_format($stats['this_month']); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-calendar-alt fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card shadow mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-3">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Search messages or batch ID...">
            </div>
            <div class="col-md-2">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All Status</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="sending" <?php echo $status_filter === 'sending' ? 'selected' : ''; ?>>Sending</option>
                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="failed" <?php echo $status_filter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                    <option value="scheduled" <?php echo $status_filter === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="date_from" class="form-label">Date From</label>
                <input type="date" class="form-control" id="date_from" name="date_from" 
                       value="<?php echo htmlspecialchars($date_from); ?>">
            </div>
            <div class="col-md-2">
                <label for="date_to" class="form-label">Date To</label>
                <input type="date" class="form-control" id="date_to" name="date_to" 
                       value="<?php echo htmlspecialchars($date_to); ?>">
            </div>
            <div class="col-md-2">
                <label for="sent_by" class="form-label">Sent By</label>
                <select class="form-select" id="sent_by" name="sent_by">
                    <option value="">All Users</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>" 
                                <?php echo $sent_by_filter === $user['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                    </button>
                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Communication History -->
<div class="card shadow">
    <div class="card-header bg-church-blue text-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0">
            <i class="fas fa-history me-2"></i>Communication History
            <?php if (!empty($search) || !empty($status_filter) || !empty($date_from) || !empty($date_to) || $sent_by_filter > 0): ?>
                <span class="badge bg-light text-dark ms-2">Filtered</span>
            <?php endif; ?>
        </h6>
        <div class="btn-group">
            <button type="button" class="btn btn-sm btn-outline-light" onclick="exportHistory('csv')">
                <i class="fas fa-file-csv me-1"></i>Export CSV
            </button>
            <button type="button" class="btn btn-sm btn-outline-light" onclick="exportHistory('excel')">
                <i class="fas fa-file-excel me-1"></i>Export Excel
            </button>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($communications)): ?>
            <div class="text-center py-5">
                <i class="fas fa-history fa-4x text-muted mb-3"></i>
                <h4 class="text-muted">No Communication History</h4>
                <?php if (!empty($search) || !empty($status_filter) || !empty($date_from) || !empty($date_to) || $sent_by_filter > 0): ?>
                    <p class="text-muted">Try adjusting your search criteria or <a href="<?php echo $_SERVER['PHP_SELF']; ?>">clear filters</a></p>
                <?php else: ?>
                    <p class="text-muted">No communications have been sent yet</p>
                    <a href="send.php" class="btn btn-church-primary">
                        <i class="fas fa-paper-plane me-1"></i>Send First Communication
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Batch ID</th>
                            <th>Recipients</th>
                            <th>Message</th>
                            <th>Status</th>
                            <th>Success Rate</th>
                            <th>Cost</th>
                            <th>Sent By</th>
                            <th>Date/Time</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($communications as $comm): ?>
                            <?php
                            $successRate = $comm['total_recipients'] > 0 ? 
                                round(($comm['sent_count'] / $comm['total_recipients']) * 100, 1) : 0;
                            ?>
                            <tr>
                                <td>
                                    <code class="small"><?php echo htmlspecialchars($comm['batch_id']); ?></code>
                                </td>
                                <td>
                                    <div class="fw-bold"><?php echo ucfirst(str_replace('_', ' ', $comm['recipient_type'])); ?></div>
                                    <small class="text-muted">
                                        <?php echo number_format($comm['total_recipients']); ?> recipients
                                    </small>
                                </td>
                                <td>
                                    <div class="text-truncate" style="max-width: 200px;" 
                                         title="<?php echo htmlspecialchars($comm['message']); ?>">
                                        <?php echo htmlspecialchars($comm['message']); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $statusClass = '';
                                    $statusIcon = '';
                                    switch ($comm['status']) {
                                        case 'completed':
                                            $statusClass = 'bg-success';
                                            $statusIcon = 'fa-check-circle';
                                            break;
                                        case 'sending':
                                            $statusClass = 'bg-warning';
                                            $statusIcon = 'fa-clock';
                                            break;
                                        case 'failed':
                                            $statusClass = 'bg-danger';
                                            $statusIcon = 'fa-times-circle';
                                            break;
                                        case 'scheduled':
                                            $statusClass = 'bg-info';
                                            $statusIcon = 'fa-calendar-alt';
                                            break;
                                        case 'pending':
                                            $statusClass = 'bg-secondary';
                                            $statusIcon = 'fa-hourglass-half';
                                            break;
                                        default:
                                            $statusClass = 'bg-secondary';
                                            $statusIcon = 'fa-question';
                                    }
                                    ?>
                                    <span class="badge <?php echo $statusClass; ?>">
                                        <i class="fas <?php echo $statusIcon; ?> me-1"></i>
                                        <?php echo ucfirst($comm['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="me-2">
                                            <div class="progress" style="width: 60px; height: 6px;">
                                                <div class="progress-bar bg-<?php echo $successRate >= 90 ? 'success' : ($successRate >= 70 ? 'warning' : 'danger'); ?>" 
                                                     style="width: <?php echo $successRate; ?>%"></div>
                                            </div>
                                        </div>
                                        <small class="text-muted"><?php echo $successRate; ?>%</small>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo number_format($comm['sent_count']); ?> sent, 
                                        <?php echo number_format($comm['failed_count']); ?> failed
                                    </small>
                                </td>
                                <td>
                                    <div class="fw-bold"><?php echo formatCurrency($comm['cost']); ?></div>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($comm['sent_by_name']); ?></div>
                                    <small class="text-muted"><?php echo getUserRoleDisplay($comm['sent_by_role']); ?></small>
                                </td>
                                <td>
                                    <div><?php echo formatDisplayDate($comm['sent_at']); ?></div>
                                    <small class="text-muted"><?php echo date('H:i', strtotime($comm['sent_at'])); ?></small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="view_communication.php?id=<?php echo $comm['id']; ?>" 
                                           class="btn btn-outline-primary" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($comm['status'] === 'failed' || ($comm['sent_count'] < $comm['total_recipients'] && $comm['status'] === 'completed')): ?>
                                            <button type="button" class="btn btn-outline-warning" 
                                                    onclick="resendCommunication(<?php echo $comm['id']; ?>)" 
                                                    title="Resend Failed">
                                                <i class="fas fa-redo"></i>
                                            </button>
                                        <?php endif; ?>
                                        <div class="dropdown">
                                            <button class="btn btn-outline-secondary dropdown-toggle" type="button" 
                                                    data-bs-toggle="dropdown" title="More Actions">
                                                <i class="fas fa-ellipsis-h"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a class="dropdown-item" 
                                                       href="send.php?duplicate=<?php echo $comm['id']; ?>">
                                                        <i class="fas fa-copy me-2"></i>Duplicate
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" 
                                                       href="javascript:void(0)" 
                                                       onclick="downloadRecipients(<?php echo $comm['id']; ?>)">
                                                        <i class="fas fa-download me-2"></i>Download Recipients
                                                    </a>
                                                </li>
                                                <?php if ($_SESSION['user_role'] === 'administrator'): ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item text-danger" 
                                                       href="javascript:void(0)" 
                                                       onclick="deleteCommunication(<?php echo $comm['id']; ?>)">
                                                        <i class="fas fa-trash me-2"></i>Delete
                                                    </a>
                                                </li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
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
                        <?php echo number_format($totalRecords); ?> communications
                    </div>
                    <?php echo generatePaginationHTML($pagination, $_SERVER['PHP_SELF'] . '?' . http_build_query($_GET)); ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set max date for date inputs to today
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('date_from').max = today;
    document.getElementById('date_to').max = today;
    
    // Auto-refresh status for pending/sending communications
    const pendingRows = document.querySelectorAll('tbody tr');
    let hasPendingOrSending = false;
    
    pendingRows.forEach(row => {
        const statusBadge = row.querySelector('.badge');
        if (statusBadge && (statusBadge.textContent.includes('Pending') || statusBadge.textContent.includes('Sending'))) {
            hasPendingOrSending = true;
        }
    });
    
    if (hasPendingOrSending) {
        // Refresh page every 30 seconds if there are pending/sending communications
        setTimeout(() => {
            if (!document.hidden) {
                location.reload();
            }
        }, 30000);
    }
});

function resendCommunication(communicationId) {
    ChurchCMS.showConfirm(
        'Are you sure you want to resend failed messages from this communication?',
        function() {
            ChurchCMS.showLoading('Resending failed messages...');
            
            fetch('ajax/resend_communication.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    communication_id: communicationId
                })
            })
            .then(response => response.json())
            .then(data => {
                ChurchCMS.hideLoading();
                
                if (data.success) {
                    ChurchCMS.showToast(data.message, 'success');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    ChurchCMS.showToast(data.message || 'Error resending messages', 'error');
                }
            })
            .catch(error => {
                ChurchCMS.hideLoading();
                console.error('Error:', error);
                ChurchCMS.showToast('Error resending messages', 'error');
            });
        }
    );
}

function deleteCommunication(communicationId) {
    ChurchCMS.showConfirm(
        'Are you sure you want to delete this communication record? This action cannot be undone.',
        function() {
            ChurchCMS.showLoading('Deleting communication...');
            
            fetch('ajax/delete_communication.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    communication_id: communicationId
                })
            })
            .then(response => response.json())
            .then(data => {
                ChurchCMS.hideLoading();
                
                if (data.success) {
                    ChurchCMS.showToast('Communication deleted successfully', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    ChurchCMS.showToast(data.message || 'Error deleting communication', 'error');
                }
            })
            .catch(error => {
                ChurchCMS.hideLoading();
                console.error('Error:', error);
                ChurchCMS.showToast('Error deleting communication', 'error');
            });
        },
        null,
        'Delete Communication'
    );
}

function downloadRecipients(communicationId) {
    window.location.href = `ajax/download_recipients.php?id=${communicationId}`;
}

function exportHistory(format) {
    const params = new URLSearchParams(window.location.search);
    params.set('export', format);
    
    window.location.href = `ajax/export_history.php?${params.toString()}`;
}
</script>

<?php include_once '../../includes/footer.php'; ?>