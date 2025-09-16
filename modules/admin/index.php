<?php
/**
 * Admin Dashboard
 * Deliverance Church Management System
 * 
 * Main admin dashboard with system overview and management tools
 */

// Include configuration and functions
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Check authentication and admin role
requireLogin();
if (!hasPermission('admin') && $_SESSION['user_role'] !== 'administrator') {
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit();
}

// Page configuration
$page_title = 'System Administration';
$page_icon = 'fas fa-cogs';
$breadcrumb = [
    ['title' => 'Administration']
];

$additional_css = ['assets/css/admin.css'];
$additional_js = ['assets/js/admin.js', 'assets/js/charts.js'];

// Get system statistics
try {
    $db = Database::getInstance();
    
    // System overview stats
    $stats = [
        'total_users' => getRecordCount('users'),
        'active_users' => getRecordCount('users', ['is_active' => 1]),
        'total_members' => getRecordCount('members'),
        'active_members' => getRecordCount('members', ['membership_status' => 'active']),
        'total_departments' => getRecordCount('departments', ['is_active' => 1]),
        'total_events_this_month' => getRecordCount('events', [
            'event_date >=' => date('Y-m-01'),
            'event_date <=' => date('Y-m-t')
        ]),
        'total_income_this_month' => 0,
        'total_expenses_this_month' => 0,
        'sms_sent_this_month' => getRecordCount('sms_individual', [
            'sent_at >=' => date('Y-m-01'),
            'status' => 'sent'
        ]),
        'database_size' => 0,
        'backup_count' => 0
    ];
    
    // Get financial stats
    $incomeStmt = $db->executeQuery("
        SELECT COALESCE(SUM(amount), 0) as total 
        FROM income 
        WHERE transaction_date >= ? AND transaction_date <= ? AND status = 'verified'
    ", [date('Y-m-01'), date('Y-m-t')]);
    $stats['total_income_this_month'] = $incomeStmt->fetchColumn();
    
    $expenseStmt = $db->executeQuery("
        SELECT COALESCE(SUM(amount), 0) as total 
        FROM expenses 
        WHERE expense_date >= ? AND expense_date <= ? AND status = 'paid'
    ", [date('Y-m-01'), date('Y-m-t')]);
    $stats['total_expenses_this_month'] = $expenseStmt->fetchColumn();
    
    // Get database size
    $sizeStmt = $db->executeQuery("
        SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
        FROM information_schema.tables 
        WHERE table_schema = ?
    ", [Database::getInstance()->getConnection()->query('SELECT DATABASE()')->fetchColumn()]);
    $stats['database_size'] = $sizeStmt->fetchColumn() ?: 0;
    
    // Count backup files
    $backupDir = BACKUP_PATH;
    if (is_dir($backupDir)) {
        $backupFiles = glob($backupDir . '*.sql');
        $stats['backup_count'] = count($backupFiles);
    }
    
    // Get recent activity
    $recentActivity = $db->executeQuery("
        SELECT al.*, u.username, u.first_name, u.last_name
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC
        LIMIT 10
    ")->fetchAll();
    
    // Get system alerts
    $systemAlerts = [];
    
    // Check for overdue backups
    if (AUTO_BACKUP_ENABLED) {
        $lastBackup = glob($backupDir . '*.sql');
        if (!empty($lastBackup)) {
            $lastBackupTime = filemtime(max($lastBackup));
            $daysSinceBackup = (time() - $lastBackupTime) / (24 * 3600);
            
            if ($daysSinceBackup > BACKUP_FREQUENCY_DAYS) {
                $systemAlerts[] = [
                    'type' => 'warning',
                    'icon' => 'fas fa-database',
                    'title' => 'Backup Overdue',
                    'message' => 'Last backup was ' . round($daysSinceBackup) . ' days ago. Consider running a backup.',
                    'action_url' => BASE_URL . 'modules/admin/backup.php',
                    'action_text' => 'Run Backup Now'
                ];
            }
        }
    }
    
    // Check for inactive users
    $inactiveUsers = $db->executeQuery("
        SELECT COUNT(*) as count 
        FROM users 
        WHERE is_active = 1 AND (last_login IS NULL OR last_login < DATE_SUB(NOW(), INTERVAL 30 DAY))
    ")->fetchColumn();
    
    if ($inactiveUsers > 0) {
        $systemAlerts[] = [
            'type' => 'info',
            'icon' => 'fas fa-users',
            'title' => 'Inactive Users',
            'message' => $inactiveUsers . ' users haven\'t logged in for over 30 days.',
            'action_url' => BASE_URL . 'modules/admin/users.php?filter=inactive',
            'action_text' => 'View Users'
        ];
    }
    
    // Check disk space (if function available)
    if (function_exists('disk_free_space')) {
        $freeBytes = disk_free_space('.');
        $totalBytes = disk_total_space('.');
        $usedPercent = (($totalBytes - $freeBytes) / $totalBytes) * 100;
        
        if ($usedPercent > 90) {
            $systemAlerts[] = [
                'type' => 'danger',
                'icon' => 'fas fa-hdd',
                'title' => 'Low Disk Space',
                'message' => 'Disk usage is at ' . round($usedPercent, 1) . '%. Consider cleaning up old files.',
                'action_url' => '#',
                'action_text' => 'View Details'
            ];
        }
    }
    
    // Get user login statistics for chart
    $loginStats = $db->executeQuery("
        SELECT DATE(last_login) as login_date, COUNT(*) as login_count
        FROM users 
        WHERE last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(last_login)
        ORDER BY login_date DESC
        LIMIT 30
    ")->fetchAll();
    
} catch (Exception $e) {
    error_log("Admin dashboard error: " . $e->getMessage());
    setFlashMessage('error', 'Error loading dashboard data');
    $stats = array_fill_keys(['total_users', 'active_users', 'total_members', 'active_members', 'total_departments', 'total_events_this_month', 'total_income_this_month', 'total_expenses_this_month', 'sms_sent_this_month', 'database_size', 'backup_count'], 0);
    $recentActivity = [];
    $systemAlerts = [];
    $loginStats = [];
}

// Include header
include_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Welcome Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="dashboard-welcome">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2><i class="fas fa-shield-alt me-3"></i>System Administration</h2>
                        <p class="mb-0">Welcome back, <?php echo htmlspecialchars($_SESSION['first_name']); ?>! Here's your system overview and management tools.</p>
                        <small class="opacity-75">
                            <i class="fas fa-clock me-1"></i>Last login: <?php echo formatDisplayDateTime($_SESSION['last_login'] ?? date('Y-m-d H:i:s')); ?>
                        </small>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="d-flex justify-content-end gap-2">
                            <a href="<?php echo BASE_URL; ?>modules/admin/backup.php" class="btn btn-light btn-sm">
                                <i class="fas fa-database me-1"></i>Backup System
                            </a>
                            <a href="<?php echo BASE_URL; ?>modules/admin/settings.php" class="btn btn-light btn-sm">
                                <i class="fas fa-cog me-1"></i>Settings
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- System Alerts -->
    <?php if (!empty($systemAlerts)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-warning">
                <div class="card-header bg-warning text-dark">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>System Alerts
                    </h6>
                </div>
                <div class="card-body">
                    <?php foreach ($systemAlerts as $alert): ?>
                    <div class="alert alert-<?php echo $alert['type']; ?> alert-dismissible fade show" role="alert">
                        <i class="<?php echo $alert['icon']; ?> me-2"></i>
                        <strong><?php echo htmlspecialchars($alert['title']); ?>:</strong>
                        <?php echo htmlspecialchars($alert['message']); ?>
                        <?php if (isset($alert['action_url']) && !empty($alert['action_url'])): ?>
                            <a href="<?php echo $alert['action_url']; ?>" class="btn btn-sm btn-outline-<?php echo $alert['type']; ?> ms-3">
                                <?php echo htmlspecialchars($alert['action_text']); ?>
                            </a>
                        <?php endif; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- System Statistics -->
    <div class="row mb-4">
        <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
            <div class="stats-card">
                <div class="d-flex align-items-center">
                    <div class="stats-icon bg-gradient-primary">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="ms-3">
                        <div class="stats-number"><?php echo number_format($stats['total_users']); ?></div>
                        <div class="stats-label">Total Users</div>
                        <small class="text-success">
                            <i class="fas fa-check-circle me-1"></i>
                            <?php echo $stats['active_users']; ?> Active
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
            <div class="stats-card">
                <div class="d-flex align-items-center">
                    <div class="stats-icon bg-gradient-success">
                        <i class="fas fa-user-friends"></i>
                    </div>
                    <div class="ms-3">
                        <div class="stats-number"><?php echo number_format($stats['total_members']); ?></div>
                        <div class="stats-label">Total Members</div>
                        <small class="text-success">
                            <i class="fas fa-heart me-1"></i>
                            <?php echo $stats['active_members']; ?> Active
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
            <div class="stats-card">
                <div class="d-flex align-items-center">
                    <div class="stats-icon bg-gradient-info">
                        <i class="fas fa-sitemap"></i>
                    </div>
                    <div class="ms-3">
                        <div class="stats-number"><?php echo number_format($stats['total_departments']); ?></div>
                        <div class="stats-label">Departments</div>
                        <small class="text-info">
                            <i class="fas fa-calendar me-1"></i>
                            <?php echo $stats['total_events_this_month']; ?> Events This Month
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
            <div class="stats-card">
                <div class="d-flex align-items-center">
                    <div class="stats-icon bg-gradient-warning">
                        <i class="fas fa-database"></i>
                    </div>
                    <div class="ms-3">
                        <div class="stats-number"><?php echo $stats['database_size']; ?> MB</div>
                        <div class="stats-label">Database Size</div>
                        <small class="text-warning">
                            <i class="fas fa-archive me-1"></i>
                            <?php echo $stats['backup_count']; ?> Backups
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Financial Overview -->
    <div class="row mb-4">
        <div class="col-md-6 mb-4">
            <div class="stats-card">
                <div class="d-flex align-items-center">
                    <div class="stats-icon bg-gradient-success">
                        <i class="fas fa-arrow-up"></i>
                    </div>
                    <div class="ms-3">
                        <div class="stats-number text-success"><?php echo formatCurrency($stats['total_income_this_month']); ?></div>
                        <div class="stats-label">Income This Month</div>
                        <small class="text-muted">
                            <i class="fas fa-calendar me-1"></i>
                            <?php echo date('F Y'); ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-4">
            <div class="stats-card">
                <div class="d-flex align-items-center">
                    <div class="stats-icon bg-gradient-danger">
                        <i class="fas fa-arrow-down"></i>
                    </div>
                    <div class="ms-3">
                        <div class="stats-number text-danger"><?php echo formatCurrency($stats['total_expenses_this_month']); ?></div>
                        <div class="stats-label">Expenses This Month</div>
                        <small class="text-muted">
                            <i class="fas fa-sms me-1"></i>
                            <?php echo number_format($stats['sms_sent_this_month']); ?> SMS Sent
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Recent Activity -->
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-history me-2"></i>Recent System Activity
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($recentActivity)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Table</th>
                                    <th>Time</th>
                                    <th>IP</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentActivity as $activity): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm bg-church-blue text-white rounded-circle me-2">
                                                <?php echo strtoupper(substr($activity['first_name'] ?? 'U', 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div class="fw-semibold"><?php echo htmlspecialchars($activity['username'] ?? 'Unknown'); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars(($activity['first_name'] ?? '') . ' ' . ($activity['last_name'] ?? '')); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($activity['action']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($activity['table_name'] ?? '-'); ?></td>
                                    <td>
                                        <span title="<?php echo formatDisplayDateTime($activity['created_at']); ?>">
                                            <?php echo timeAgo($activity['created_at']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted font-monospace"><?php echo htmlspecialchars($activity['ip_address'] ?? '-'); ?></small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center mt-3">
                        <a href="<?php echo BASE_URL; ?>modules/admin/logs.php" class="btn btn-outline-primary btn-sm">
                            View All Activity Logs
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-history fa-2x mb-3"></i>
                        <p>No recent activity to display</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Admin Actions -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-tools me-2"></i>Quick Admin Actions
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="<?php echo BASE_URL; ?>modules/admin/users.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-users me-2"></i>Manage Users
                        </a>
                        <a href="<?php echo BASE_URL; ?>modules/admin/backup.php" class="btn btn-outline-success btn-sm">
                            <i class="fas fa-database me-2"></i>Backup & Restore
                        </a>
                        <a href="<?php echo BASE_URL; ?>modules/admin/settings.php" class="btn btn-outline-info btn-sm">
                            <i class="fas fa-cogs me-2"></i>System Settings
                        </a>
                        <a href="<?php echo BASE_URL; ?>modules/admin/logs.php" class="btn btn-outline-warning btn-sm">
                            <i class="fas fa-clipboard-list me-2"></i>Activity Logs
                        </a>
                        <hr>
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="clearCache()">
                            <i class="fas fa-trash me-2"></i>Clear Cache
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="checkSystemHealth()">
                            <i class="fas fa-stethoscope me-2"></i>System Health Check
                        </button>
                    </div>
                </div>
            </div>

            <!-- System Information -->
            <div class="card mt-4">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-info-circle me-2"></i>System Information
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row g-0">
                        <div class="col-6">
                            <div class="text-center p-2 border-end">
                                <div class="fw-bold text-church-blue">PHP</div>
                                <small class="text-muted"><?php echo PHP_VERSION; ?></small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-2">
                                <div class="fw-bold text-church-blue">MySQL</div>
                                <small class="text-muted"><?php echo Database::getInstance()->getConnection()->getAttribute(PDO::ATTR_SERVER_VERSION); ?></small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-2 border-end border-top">
                                <div class="fw-bold text-church-blue">Server</div>
                                <small class="text-muted"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-2 border-top">
                                <div class="fw-bold text-church-blue">Timezone</div>
                                <small class="text-muted"><?php echo date_default_timezone_get(); ?></small>
                            </div>
                        </div>
                    </div>

                    <hr class="my-3">

                    <div class="small">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Memory Usage:</span>
                            <span><?php echo round(memory_get_usage(true) / 1024 / 1024, 1); ?> MB</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span>Peak Memory:</span>
                            <span><?php echo round(memory_get_peak_usage(true) / 1024 / 1024, 1); ?> MB</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Uptime:</span>
                            <span><?php echo gmdate('H:i:s', time() - $_SERVER['REQUEST_TIME']); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- User Login Chart -->
    <?php if (!empty($loginStats)): ?>
    <div class="row">
        <div class="col-12">
            <div class="chart-container">
                <h6 class="mb-3">
                    <i class="fas fa-chart-line me-2"></i>User Login Activity (Last 30 Days)
                </h6>
                <canvas id="loginChart" style="max-height: 300px;"></canvas>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// Chart data
const loginData = <?php echo json_encode($loginStats); ?>;

// Admin specific functions
function clearCache() {
    ChurchCMS.showConfirm('Are you sure you want to clear the system cache?', function() {
        ChurchCMS.showLoading('Clearing cache...');
        
        fetch('<?php echo BASE_URL; ?>api/admin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ action: 'clear_cache' })
        })
        .then(response => response.json())
        .then(data => {
            ChurchCMS.hideLoading();
            if (data.success) {
                ChurchCMS.showToast('Cache cleared successfully', 'success');
            } else {
                ChurchCMS.showToast(data.message || 'Failed to clear cache', 'error');
            }
        })
        .catch(error => {
            ChurchCMS.hideLoading();
            ChurchCMS.showToast('Error clearing cache', 'error');
        });
    });
}

function checkSystemHealth() {
    ChurchCMS.showLoading('Checking system health...');
    
    fetch('<?php echo BASE_URL; ?>api/admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ action: 'health_check' })
    })
    .then(response => response.json())
    .then(data => {
        ChurchCMS.hideLoading();
        
        let message = 'System Health Check Results:\n\n';
        if (data.checks) {
            Object.entries(data.checks).forEach(([check, result]) => {
                message += `${check}: ${result.status}\n`;
                if (result.message) {
                    message += `  - ${result.message}\n`;
                }
            });
        }
        
        alert(message);
    })
    .catch(error => {
        ChurchCMS.hideLoading();
        ChurchCMS.showToast('Error checking system health', 'error');
    });
}

// Initialize login chart if data exists
if (loginData.length > 0) {
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('loginChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: loginData.map(item => ChurchCMS.formatDate(item.login_date)),
                datasets: [{
                    label: 'Daily Logins',
                    data: loginData.map(item => item.login_count),
                    borderColor: 'rgb(3, 4, 94)',
                    backgroundColor: 'rgba(3, 4, 94, 0.1)',
                    tension: 0.1,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    });
}
</script>

<?php include_once '../../includes/footer.php'; ?>