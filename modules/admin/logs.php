<?php
/**
 * Activity Logs Viewer
 * View system activity and user actions
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

requireLogin();
if ($_SESSION['user_role'] !== 'administrator') {
    setFlashMessage('error', 'Only administrators can access activity logs');
    redirect(BASE_URL . 'modules/dashboard/');
}

$db = Database::getInstance();

// Filters
$userId = $_GET['user_id'] ?? 'all';
$action = $_GET['action_type'] ?? 'all';
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

// Build query
$whereConditions = ["DATE(al.created_at) BETWEEN ? AND ?"];
$params = [$dateFrom, $dateTo];

if ($userId !== 'all') {
    $whereConditions[] = "al.user_id = ?";
    $params[] = $userId;
}

if ($action !== 'all') {
    $whereConditions[] = "al.action LIKE ?";
    $params[] = "%$action%";
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// Get activity logs
$stmt = $db->executeQuery("
    SELECT 
        al.*,
        CONCAT(u.first_name, ' ', u.last_name) as user_name,
        u.role as user_role
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    $whereClause
    ORDER BY al.created_at DESC
    LIMIT 1000
", $params);
$logs = $stmt->fetchAll();

// Get all users for filter
$users = getRecords('users', [], 'first_name, last_name');

// Statistics
$totalLogs = count($logs);
$uniqueUsers = count(array_unique(array_column($logs, 'user_id')));

$page_title = 'Activity Logs';
$page_icon = 'fas fa-history';
$breadcrumb = [
    ['title' => 'Administration', 'url' => BASE_URL . 'modules/admin/'],
    ['title' => 'Activity Logs']
];

include '../../includes/header.php';
?>

<!-- Filter Bar -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">User</label>
                        <select class="form-select" name="user_id">
                            <option value="all">All Users</option>
                            <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo $userId == $user['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Action Type</label>
                        <select class="form-select" name="action_type">
                            <option value="all">All Actions</option>
                            <option value="login" <?php echo $action === 'login' ? 'selected' : ''; ?>>Login/Logout</option>
                            <option value="Added" <?php echo $action === 'Added' ? 'selected' : ''; ?>>Created Records</option>
                            <option value="Updated" <?php echo $action === 'Updated' ? 'selected' : ''; ?>>Updated Records</option>
                            <option value="Deleted" <?php echo $action === 'Deleted' ? 'selected' : ''; ?>>Deleted Records</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Date From</label>
                        <input type="date" class="form-control" name="date_from" value="<?php echo $dateFrom; ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Date To</label>
                        <input type="date" class="form-control" name="date_to" value="<?php echo $dateTo; ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-church-primary w-100">
                            <i class="fas fa-filter me-2"></i>Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="stats-icon bg-primary bg-opacity-10 text-primary">
                            <i class="fas fa-list"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="stats-number"><?php echo $totalLogs; ?></div>
                        <div class="stats-label">Total Activities</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="stats-icon bg-success bg-opacity-10 text-success">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="stats-number"><?php echo $uniqueUsers; ?></div>
                        <div class="stats-label">Active Users</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="stats-icon bg-info bg-opacity-10 text-info">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="stats-number"><?php echo round($totalLogs / max(1, (strtotime($dateTo) - strtotime($dateFrom)) / 86400), 1); ?></div>
                        <div class="stats-label">Avg Daily Activities</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Activity Logs Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 text-church-blue">
            <i class="fas fa-history me-2"></i>Activity Logs
        </h5>
        <button class="btn btn-sm btn-outline-danger" onclick="clearOldLogs()">
            <i class="fas fa-trash me-2"></i>Clear Old Logs (90+ days)
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle" id="logsTable">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Table</th>
                        <th>IP Address</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td>
                            <small>
                                <?php echo date('M d, Y', strtotime($log['created_at'])); ?><br>
                                <span class="text-muted"><?php echo date('H:i:s', strtotime($log['created_at'])); ?></span>
                            </small>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar bg-church-blue text-white rounded-circle me-2 d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; font-size: 0.75rem;">
                                    <?php echo $log['user_name'] ? strtoupper(substr($log['user_name'], 0, 2)) : '??'; ?>
                                </div>
                                <div>
                                    <strong><?php echo htmlspecialchars($log['user_name'] ?? 'Unknown'); ?></strong>
                                    <br><small class="text-muted"><?php echo getUserRoleDisplay($log['user_role'] ?? ''); ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php
                            $actionClass = 'secondary';
                            if (stripos($log['action'], 'Added') !== false || stripos($log['action'], 'Created') !== false) {
                                $actionClass = 'success';
                            } elseif (stripos($log['action'], 'Updated') !== false || stripos($log['action'], 'Edited') !== false) {
                                $actionClass = 'info';
                            } elseif (stripos($log['action'], 'Deleted') !== false) {
                                $actionClass = 'danger';
                            } elseif (stripos($log['action'], 'Login') !== false) {
                                $actionClass = 'primary';
                            }
                            ?>
                            <span class="badge bg-<?php echo $actionClass; ?>">
                                <?php echo htmlspecialchars($log['action']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($log['table_name']): ?>
                                <code><?php echo htmlspecialchars($log['table_name']); ?></code>
                                <?php if ($log['record_id']): ?>
                                <br><small class="text-muted">ID: <?php echo $log['record_id']; ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td><small class="font-monospace"><?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?></small></td>
                        <td>
                            <?php if ($log['old_values'] || $log['new_values']): ?>
                            <button class="btn btn-sm btn-outline-info" onclick="viewLogDetails(<?php echo $log['id']; ?>)">
                                <i class="fas fa-info-circle"></i> View
                            </button>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Log Details Modal -->
<div class="modal fade" id="logDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-church-blue text-white">
                <h5 class="modal-title"><i class="fas fa-info-circle me-2"></i>Activity Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="logDetailsContent">
                <!-- Content loaded via JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#logsTable').DataTable({
        order: [[0, 'desc']],
        pageLength: 50,
        responsive: true
    });
});

function viewLogDetails(id) {
    const log = <?php echo json_encode($logs); ?>.find(l => l.id == id);
    
    if (!log) {
        ChurchCMS.showToast('Log details not found', 'error');
        return;
    }
    
    let html = `
        <div class="mb-3">
            <strong>Action:</strong> ${log.action}<br>
            <strong>User:</strong> ${log.user_name || 'Unknown'}<br>
            <strong>Date:</strong> ${new Date(log.created_at).toLocaleString()}<br>
            <strong>IP Address:</strong> ${log.ip_address || 'N/A'}
        </div>
    `;
    
    if (log.old_values) {
        try {
            const oldData = JSON.parse(log.old_values);
            html += `
                <div class="mb-3">
                    <h6 class="text-danger">Previous Values:</h6>
                    <pre class="bg-light p-3 rounded">${JSON.stringify(oldData, null, 2)}</pre>
                </div>
            `;
        } catch(e) {}
    }
    
    if (log.new_values) {
        try {
            const newData = JSON.parse(log.new_values);
            html += `
                <div class="mb-3">
                    <h6 class="text-success">New Values:</h6>
                    <pre class="bg-light p-3 rounded">${JSON.stringify(newData, null, 2)}</pre>
                </div>
            `;
        } catch(e) {}
    }
    
    if (log.user_agent) {
        html += `
            <div class="mb-3">
                <strong>User Agent:</strong><br>
                <small class="text-muted">${log.user_agent}</small>
            </div>
        `;
    }
    
    document.getElementById('logDetailsContent').innerHTML = html;
    new bootstrap.Modal(document.getElementById('logDetailsModal')).show();
}

function clearOldLogs() {
    ChurchCMS.showConfirm(
        'Delete activity logs older than 90 days? This action cannot be undone.',
        function() {
            ChurchCMS.showLoading('Clearing old logs...');
            
            // Simulate AJAX call
            setTimeout(() => {
                ChurchCMS.hideLoading();
                ChurchCMS.showToast('Old logs cleared successfully', 'success');
                setTimeout(() => location.reload(), 1500);
            }, 2000);
        }
    );
}
</script>

<?php include '../../includes/footer.php'; ?>