<?php
/**
 * WhatsApp Message History Page
 * Deliverance Church Management System
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Check authentication and permissions
requireLogin();
if (!hasPermission('sms') && !in_array($_SESSION['user_role'], ['administrator', 'pastor', 'secretary'])) {
    setFlashMessage('error', 'You do not have permission to view WhatsApp history.');
    redirect(BASE_URL . 'modules/dashboard/');
}

// Page settings
$page_title = 'WhatsApp History';
$page_icon = 'fab fa-whatsapp';
$breadcrumb = [
    ['title' => 'Communication Center', 'url' => BASE_URL . 'modules/sms/'],
    ['title' => 'WhatsApp History']
];

$db = Database::getInstance();

// Get filters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

$recordsPerPage = 25;
$offset = ($page - 1) * $recordsPerPage;

// Build query conditions
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(wh.message LIKE ? OR wh.batch_id LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($status_filter)) {
    $conditions[] = "wh.status = ?";
    $params[] = $status_filter;
}

if (!empty($date_from)) {
    $conditions[] = "DATE(wh.sent_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $conditions[] = "DATE(wh.sent_at) <= ?";
    $params[] = $date_to;
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Get total count
$totalQuery = "SELECT COUNT(*) as total FROM whatsapp_history wh $whereClause";
$totalResult = $db->executeQuery($totalQuery, $params);
$totalRecords = $totalResult->fetchColumn();

// Get WhatsApp history
$historyQuery = "
    SELECT 
        wh.*,
        CONCAT(u.first_name, ' ', u.last_name) as sent_by_name
    FROM whatsapp_history wh
    LEFT JOIN users u ON wh.sent_by = u.id
    $whereClause
    ORDER BY wh.sent_at DESC
    LIMIT $recordsPerPage OFFSET $offset
";

$communications = $db->executeQuery($historyQuery, $params)->fetchAll();

// Get statistics
$stats = [
    'total_sent' => $db->executeQuery("SELECT SUM(sent_count) FROM whatsapp_history")->fetchColumn() ?: 0,
    'total_failed' => $db->executeQuery("SELECT SUM(failed_count) FROM whatsapp_history")->fetchColumn() ?: 0,
    'this_month' => $db->executeQuery("
        SELECT COUNT(*) FROM whatsapp_history 
        WHERE MONTH(sent_at) = MONTH(NOW()) 
        AND YEAR(sent_at) = YEAR(NOW())
    ")->fetchColumn() ?: 0,
    'today' => $db->executeQuery("
        SELECT COUNT(*) FROM whatsapp_history 
        WHERE DATE(sent_at) = CURDATE()
    ")->fetchColumn() ?: 0
];

// Generate pagination
$pagination = generatePagination($totalRecords, $page, $recordsPerPage);

include_once '../../includes/header.php';
?>

<div class="row mb-4">
    <!-- Statistics Cards -->
    <div class="col-md-3 mb-3">
        <div class="card bg-success text-white shadow">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-uppercase mb-1">Total Sent</div>
                        <div class="h5 mb-0 font-weight-bold"><?php echo number_format($stats['total_sent']); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fab fa-whatsapp fa-2x"></i>
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
    
    <div class="col-md-3 mb-3">
        <div class="card bg-warning text-white shadow">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-uppercase mb-1">Today</div>
                        <div class="h5 mb-0 font-weight-bold"><?php echo number_format($stats['today']); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-chart-line fa-2x"></i>
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
            <div class="col-md-4">
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
            <div class="col-md-2 d-flex align-items-end">
                <div class="btn-group w-100">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- WhatsApp History -->
<div class="card shadow">
    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0">
            <i class="fab fa-whatsapp me-2"></i>WhatsApp Message History
        </h6>
        <a href="send_whatsapp.php" class="btn btn-sm btn-light">
            <i class="fab fa-whatsapp me-1"></i>Send New Message
        </a>
    </div>
    <div class="card-body">
        <?php if (empty($communications)): ?>
            <div class="text-center py-5">
                <i class="fab fa-whatsapp fa-4x text-muted mb-3"></i>
                <h4 class="text-muted">No WhatsApp Messages</h4>
                <p class="text-muted">No WhatsApp communications have been sent yet</p>
                <a href="send_whatsapp.php" class="btn btn-success">
                    <i class="fab fa-whatsapp me-1"></i>Send First Message
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Batch ID</th>
                            <th>Message</th>
                            <th>Recipients</th>
                            <th>Status</th>
                            <th>Success Rate</th>
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
                                    <div class="text-truncate" style="max-width: 250px;" 
                                         title="<?php echo htmlspecialchars($comm['message']); ?>">
                                        <?php echo htmlspecialchars($comm['message']); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-bold"><?php echo number_format($comm['total_recipients']); ?> recipients</div>
                                    <small class="text-muted">
                                        <?php echo number_format($comm['sent_count']); ?> sent, 
                                        <?php echo number_format($comm['failed_count']); ?> failed
                                    </small>
                                </td>
                                <td>
                                    <?php
                                    $statusClass = '';
                                    switch ($comm['status']) {
                                        case 'completed':
                                            $statusClass = 'bg-success';
                                            break;
                                        case 'sending':
                                            $statusClass = 'bg-warning';
                                            break;
                                        case 'failed':
                                            $statusClass = 'bg-danger';
                                            break;
                                        default:
                                            $statusClass = 'bg-secondary';
                                    }
                                    ?>
                                    <span class="badge <?php echo $statusClass; ?>">
                                        <?php echo ucfirst($comm['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="progress me-2" style="width: 60px; height: 6px;">
                                            <div class="progress-bar bg-success" 
                                                 style="width: <?php echo $successRate; ?>%"></div>
                                        </div>
                                        <small><?php echo $successRate; ?>%</small>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($comm['sent_by_name']); ?></td>
                                <td>
                                    <div><?php echo formatDisplayDate($comm['sent_at']); ?></div>
                                    <small class="text-muted"><?php echo date('H:i', strtotime($comm['sent_at'])); ?></small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-success" 
                                                onclick="viewDetails(<?php echo $comm['id']; ?>)" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($comm['status'] === 'failed' || $comm['failed_count'] > 0): ?>
                                            <button type="button" class="btn btn-outline-warning" 
                                                    onclick="resendFailed(<?php echo $comm['id']; ?>)" title="Resend Failed">
                                                <i class="fas fa-redo"></i>
                                            </button>
                                        <?php endif; ?>
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
function viewDetails(historyId) {
    window.location.href = `view_whatsapp.php?id=${historyId}`;
}

function resendFailed(historyId) {
    ChurchCMS.showConfirm(
        'Are you sure you want to resend failed WhatsApp messages?',
        function() {
            ChurchCMS.showLoading('Resending messages...');
            
            fetch('ajax/resend_whatsapp.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    history_id: historyId
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
</script>

<?php include_once '../../includes/footer.php'; ?>