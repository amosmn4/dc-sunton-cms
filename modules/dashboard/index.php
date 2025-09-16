<?php
/**
 * Main Dashboard
 * Deliverance Church Management System
 * 
 * Overview dashboard with statistics and quick actions
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Check authentication
requireLogin();

// Set page variables
$page_title = 'Dashboard';
$page_icon = 'fas fa-tachometer-alt';
$additional_css = [];
$additional_js = ['assets/js/dashboard.js'];

// Get church information
$church_info = getRecord('church_info', 'id', 1) ?: [
    'church_name' => 'Deliverance Church',
    'yearly_theme' => 'Year of Divine Breakthrough 2025',
    'pastor_name' => 'Pastor John Doe'
];

// Get dashboard statistics
try {
    $db = Database::getInstance();
    
    // Total members
    $total_members = getRecordCount('members', ['membership_status' => 'active']);
    
    // New members this month
    $new_members_this_month = $db->executeQuery(
        "SELECT COUNT(*) as count FROM members WHERE membership_status = 'active' AND DATE(join_date) >= DATE_FORMAT(NOW(), '%Y-%m-01')"
    )->fetchColumn();
    
    // Total visitors this month
    $visitors_this_month = $db->executeQuery(
        "SELECT COUNT(*) as count FROM visitors WHERE DATE(visit_date) >= DATE_FORMAT(NOW(), '%Y-%m-01')"
    )->fetchColumn();
    
    // Today's attendance (if any events today)
    $attendance_today = $db->executeQuery(
        "SELECT COALESCE(SUM(ac.count_number), 0) as total 
         FROM events e 
         LEFT JOIN attendance_counts ac ON e.id = ac.event_id AND ac.attendance_category = 'total'
         WHERE DATE(e.event_date) = CURDATE() AND e.status = 'completed'"
    )->fetchColumn();
    
    // Monthly income
    $monthly_income = $db->executeQuery(
        "SELECT COALESCE(SUM(amount), 0) as total FROM income 
         WHERE status = 'verified' AND DATE(transaction_date) >= DATE_FORMAT(NOW(), '%Y-%m-01')"
    )->fetchColumn();
    
    // Monthly expenses
    $monthly_expenses = $db->executeQuery(
        "SELECT COALESCE(SUM(amount), 0) as total FROM expenses 
         WHERE status = 'paid' AND DATE(expense_date) >= DATE_FORMAT(NOW(), '%Y-%m-01')"
    )->fetchColumn();
    
    // Upcoming birthdays (next 7 days)
    $upcoming_birthdays = $db->executeQuery(
        "SELECT first_name, last_name, date_of_birth, phone
         FROM members 
         WHERE membership_status = 'active'
         AND DAYOFYEAR(STR_TO_DATE(CONCAT(YEAR(NOW()), '-', MONTH(date_of_birth), '-', DAY(date_of_birth)), '%Y-%m-%d')) 
         BETWEEN DAYOFYEAR(NOW()) AND DAYOFYEAR(DATE_ADD(NOW(), INTERVAL 7 DAY))
         ORDER BY DAYOFYEAR(STR_TO_DATE(CONCAT(YEAR(NOW()), '-', MONTH(date_of_birth), '-', DAY(date_of_birth)), '%Y-%m-%d'))"
    )->fetchAll();
    
    // Recent visitors needing follow-up
    $recent_visitors = $db->executeQuery(
        "SELECT v.*, u.first_name as recorded_by_name
         FROM visitors v 
         LEFT JOIN users u ON v.created_by = u.id
         WHERE v.status = 'new_visitor' OR v.status = 'follow_up'
         ORDER BY v.visit_date DESC 
         LIMIT 5"
    )->fetchAll();
    
    // Recent activities (last 10)
    $recent_activities = $db->executeQuery(
        "SELECT al.*, u.first_name, u.last_name
         FROM activity_logs al 
         JOIN users u ON al.user_id = u.id
         ORDER BY al.created_at DESC 
         LIMIT 10"
    )->fetchAll();
    
    // Equipment needing maintenance
    $equipment_maintenance = $db->executeQuery(
        "SELECT e.*, ec.name as category_name
         FROM equipment e
         JOIN equipment_categories ec ON e.category_id = ec.id
         WHERE e.next_maintenance_date <= DATE_ADD(NOW(), INTERVAL 30 DAY)
         AND e.status IN ('good', 'operational')
         ORDER BY e.next_maintenance_date ASC
         LIMIT 5"
    )->fetchAll();
    
} catch (Exception $e) {
    error_log("Dashboard query error: " . $e->getMessage());
    $total_members = 0;
    $new_members_this_month = 0;
    $visitors_this_month = 0;
    $attendance_today = 0;
    $monthly_income = 0;
    $monthly_expenses = 0;
    $upcoming_birthdays = [];
    $recent_visitors = [];
    $recent_activities = [];
    $equipment_maintenance = [];
}

// Include header
include '../../includes/header.php';
?>

<!-- Dashboard Content -->
<div class="row">
    <div class="col-12">
        <!-- Welcome Section -->
        <div class="dashboard-welcome mb-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 class="mb-2">
                        <i class="fas fa-sun me-2"></i>
                        Good <?php echo (date('H') < 12) ? 'Morning' : ((date('H') < 18) ? 'Afternoon' : 'Evening'); ?>, 
                        <?php echo htmlspecialchars($_SESSION['first_name']); ?>!
                    </h2>
                    <p class="mb-1 opacity-90">Welcome back to <?php echo htmlspecialchars($church_info['church_name']); ?></p>
                    <p class="mb-0 small opacity-75">
                        <i class="fas fa-calendar me-1"></i>
                        <?php echo date('l, F j, Y'); ?> | 
                        <i class="fas fa-quote-left me-1"></i>
                        <?php echo htmlspecialchars($church_info['yearly_theme']); ?>
                    </p>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <div class="d-flex flex-column align-items-md-end">
                        <div class="badge bg-white text-church-blue px-3 py-2 mb-2">
                            <i class="fas fa-crown me-1"></i>
                            <?php echo getUserRoleDisplay($_SESSION['user_role']); ?>
                        </div>
                        <small class="opacity-75">
                            Last login: <?php echo isset($_SESSION['last_login']) ? formatDisplayDateTime($_SESSION['last_login']) : 'N/A'; ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stats-card">
            <div class="d-flex align-items-center">
                <div class="stats-icon bg-church-blue text-white">
                    <i class="fas fa-users"></i>
                </div>
                <div class="flex-grow-1 ms-3">
                    <div class="stats-number"><?php echo number_format($total_members); ?></div>
                    <div class="stats-label">Total Members</div>
                    <?php if ($new_members_this_month > 0): ?>
                        <small class="text-success">
                            <i class="fas fa-arrow-up me-1"></i>
                            +<?php echo $new_members_this_month; ?> this month
                        </small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stats-card">
            <div class="d-flex align-items-center">
                <div class="stats-icon bg-success text-white">
                    <i class="fas fa-user-friends"></i>
                </div>
                <div class="flex-grow-1 ms-3">
                    <div class="stats-number"><?php echo number_format($visitors_this_month); ?></div>
                    <div class="stats-label">Visitors This Month</div>
                    <small class="text-muted">
                        <i class="fas fa-calendar me-1"></i>
                        <?php echo date('F Y'); ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stats-card">
            <div class="d-flex align-items-center">
                <div class="stats-icon bg-info text-white">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="flex-grow-1 ms-3">
                    <div class="stats-number"><?php echo number_format($attendance_today); ?></div>
                    <div class="stats-label">Today's Attendance</div>
                    <small class="text-muted">
                        <i class="fas fa-clock me-1"></i>
                        <?php echo date('M j, Y'); ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stats-card">
            <div class="d-flex align-items-center">
                <div class="stats-icon bg-church-red text-white">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="flex-grow-1 ms-3">
                    <div class="stats-number"><?php echo formatCurrency($monthly_income); ?></div>
                    <div class="stats-label">Monthly Income</div>
                    <?php 
                    $net_income = $monthly_income - $monthly_expenses;
                    $net_class = $net_income >= 0 ? 'text-success' : 'text-danger';
                    ?>
                    <small class="<?php echo $net_class; ?>">
                        <i class="fas fa-<?php echo $net_income >= 0 ? 'arrow-up' : 'arrow-down'; ?> me-1"></i>
                        Net: <?php echo formatCurrency($net_income); ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<?php if (hasPermission('members') || hasPermission('finance') || hasPermission('sms')): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>Quick Actions
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach (QUICK_ACTIONS as $action_key => $action): ?>
                        <?php if (in_array($_SESSION['user_role'], $action['permissions']) || hasPermission($action['permissions'][0])): ?>
                            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                                <a href="<?php echo BASE_URL . $action['url']; ?>" 
                                   class="quick-action-card action-<?php echo $action['color']; ?> text-decoration-none">
                                    <div class="quick-action-icon text-<?php echo $action['color']; ?>">
                                        <i class="<?php echo $action['icon']; ?>"></i>
                                    </div>
                                    <div class="fw-bold"><?php echo $action['title']; ?></div>
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Main Content Row -->
<div class="row">
    <!-- Left Column -->
    <div class="col-lg-8">
        <!-- Financial Overview Chart -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-chart-line me-2"></i>Financial Overview - Last 6 Months
                </h6>
            </div>
            <div class="card-body">
                <canvas id="financialChart" height="300"></canvas>
            </div>
        </div>
        
        <!-- Attendance Trends -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="fas fa-chart-area me-2"></i>Attendance Trends
                </h6>
                <small class="text-muted">Last 12 weeks</small>
            </div>
            <div class="card-body">
                <canvas id="attendanceChart" height="250"></canvas>
            </div>
        </div>
        
        <!-- Recent Activities -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="fas fa-history me-2"></i>Recent Activities
                </h6>
                <a href="<?php echo BASE_URL; ?>modules/admin/logs.php" class="btn btn-sm btn-outline-primary">
                    View All
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($recent_activities)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_activities as $activity): ?>
                            <div class="list-group-item border-0 py-3">
                                <div class="d-flex align-items-start">
                                    <div class="avatar bg-light rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                        <i class="fas fa-user text-muted"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="fw-bold mb-1">
                                            <?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?>
                                        </div>
                                        <div class="text-muted mb-1">
                                            <?php echo htmlspecialchars($activity['action']); ?>
                                            <?php if ($activity['table_name']): ?>
                                                <span class="badge bg-light text-dark ms-1">
                                                    <?php echo htmlspecialchars(ucfirst($activity['table_name'])); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo timeAgo($activity['created_at']); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-history fa-2x mb-2 opacity-50"></i>
                        <p class="mb-0">No recent activities found.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Right Column -->
    <div class="col-lg-4">
        <!-- Upcoming Birthdays -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="fas fa-birthday-cake me-2"></i>Upcoming Birthdays
                </h6>
                <span class="badge bg-church-red"><?php echo count($upcoming_birthdays); ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($upcoming_birthdays)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($upcoming_birthdays as $birthday): ?>
                            <div class="list-group-item border-0 py-3">
                                <div class="d-flex align-items-center">
                                    <div class="avatar bg-warning text-white rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 35px; height: 35px;">
                                        <i class="fas fa-gift"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="fw-bold">
                                            <?php echo htmlspecialchars($birthday['first_name'] . ' ' . $birthday['last_name']); ?>
                                        </div>
                                        <small class="text-muted">
                                            <?php 
                                            $birthday_date = new DateTime($birthday['date_of_birth']);
                                            $today = new DateTime();
                                            $birthday_this_year = new DateTime($today->format('Y') . '-' . $birthday_date->format('m-d'));
                                            
                                            if ($birthday_this_year < $today) {
                                                $birthday_this_year->add(new DateInterval('P1Y'));
                                            }
                                            
                                            $days_until = $today->diff($birthday_this_year)->days;
                                            
                                            if ($days_until == 0) {
                                                echo '<span class="text-warning fw-bold">Today!</span>';
                                            } elseif ($days_until == 1) {
                                                echo '<span class="text-info fw-bold">Tomorrow</span>';
                                            } else {
                                                echo "In {$days_until} days";
                                            }
                                            ?>
                                            <span class="ms-2">
                                                <?php echo $birthday_date->format('M j'); ?>
                                            </span>
                                        </small>
                                    </div>
                                    <?php if (!empty($birthday['phone'])): ?>
                                        <a href="tel:<?php echo $birthday['phone']; ?>" 
                                           class="btn btn-sm btn-outline-success">
                                            <i class="fas fa-phone"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-birthday-cake fa-2x mb-2 opacity-50"></i>
                        <p class="mb-0">No upcoming birthdays in the next 7 days.</p>
                    </div>
                <?php endif; ?>
            </div>
            <?php if (!empty($upcoming_birthdays)): ?>
                <div class="card-footer bg-transparent">
                    <a href="<?php echo BASE_URL; ?>modules/members/?filter=birthdays" class="btn btn-sm btn-church-primary w-100">
                        <i class="fas fa-eye me-1"></i>View All Birthdays
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Recent Visitors -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="fas fa-user-friends me-2"></i>Recent Visitors
                </h6>
                <span class="badge bg-success"><?php echo count($recent_visitors); ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($recent_visitors)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_visitors as $visitor): ?>
                            <div class="list-group-item border-0 py-3">
                                <div class="d-flex align-items-center">
                                    <div class="avatar bg-info text-white rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 35px; height: 35px;">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="fw-bold">
                                            <?php echo htmlspecialchars($visitor['first_name'] . ' ' . $visitor['last_name']); ?>
                                        </div>
                                        <small class="text-muted d-block">
                                            Visited: <?php echo formatDisplayDate($visitor['visit_date']); ?>
                                        </small>
                                        <span class="badge badge-sm status-<?php echo $visitor['status'] === 'new_visitor' ? 'pending' : 'active'; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $visitor['status'])); ?>
                                        </span>
                                    </div>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>modules/visitors/view.php?id=<?php echo $visitor['id']; ?>">
                                                    <i class="fas fa-eye me-2"></i>View Details
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>modules/visitors/followup.php?visitor_id=<?php echo $visitor['id']; ?>">
                                                    <i class="fas fa-phone me-2"></i>Follow Up
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-user-friends fa-2x mb-2 opacity-50"></i>
                        <p class="mb-0">No recent visitors needing follow-up.</p>
                    </div>
                <?php endif; ?>
            </div>
            <?php if (!empty($recent_visitors)): ?>
                <div class="card-footer bg-transparent">
                    <a href="<?php echo BASE_URL; ?>modules/visitors/" class="btn btn-sm btn-success w-100">
                        <i class="fas fa-eye me-1"></i>View All Visitors
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Equipment Maintenance -->
        <?php if (hasPermission('equipment') && !empty($equipment_maintenance)): ?>
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="fas fa-tools me-2"></i>Maintenance Due
                </h6>
                <span class="badge bg-warning text-dark"><?php echo count($equipment_maintenance); ?></span>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach ($equipment_maintenance as $equipment): ?>
                        <div class="list-group-item border-0 py-3">
                            <div class="d-flex align-items-center">
                                <div class="avatar bg-warning text-white rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 35px; height: 35px;">
                                    <i class="fas fa-wrench"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-bold">
                                        <?php echo htmlspecialchars($equipment['name']); ?>
                                    </div>
                                    <small class="text-muted d-block">
                                        <?php echo htmlspecialchars($equipment['category_name']); ?>
                                    </small>
                                    <small class="text-<?php echo (strtotime($equipment['next_maintenance_date']) <= time()) ? 'danger' : 'warning'; ?>">
                                        Due: <?php echo formatDisplayDate($equipment['next_maintenance_date']); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="card-footer bg-transparent">
                <a href="<?php echo BASE_URL; ?>modules/equipment/maintenance.php" class="btn btn-sm btn-warning w-100">
                    <i class="fas fa-calendar-alt me-1"></i>Schedule Maintenance
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- System Status -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-server me-2"></i>System Status
                </h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6">
                        <div class="mb-2">
                            <i class="fas fa-database fa-2x text-success"></i>
                        </div>
                        <div class="small">
                            <div class="fw-bold text-success">Database</div>
                            <div class="text-muted">Online</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="mb-2">
                            <?php if (isFeatureEnabled('sms')): ?>
                                <i class="fas fa-sms fa-2x text-info"></i>
                            <?php else: ?>
                                <i class="fas fa-sms fa-2x text-muted"></i>
                            <?php endif; ?>
                        </div>
                        <div class="small">
                            <div class="fw-bold <?php echo isFeatureEnabled('sms') ? 'text-info' : 'text-muted'; ?>">SMS Service</div>
                            <div class="text-muted"><?php echo isFeatureEnabled('sms') ? 'Active' : 'Inactive'; ?></div>
                        </div>
                    </div>
                </div>
                
                <hr class="my-3">
                
                <div class="small text-muted">
                    <div class="d-flex justify-content-between mb-1">
                        <span>Server Time:</span>
                        <span class="fw-bold"><?php echo date('H:i:s'); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span>PHP Version:</span>
                        <span class="fw-bold"><?php echo PHP_VERSION; ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>System Version:</span>
                        <span class="fw-bold">v1.0.0</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Chart data and initialization will be handled by dashboard.js
const dashboardData = {
    financial: {
        income: <?php echo json_encode($monthly_income); ?>,
        expenses: <?php echo json_encode($monthly_expenses); ?>
    },
    stats: {
        totalMembers: <?php echo $total_members; ?>,
        newMembers: <?php echo $new_members_this_month; ?>,
        visitors: <?php echo $visitors_this_month; ?>,
        attendance: <?php echo $attendance_today; ?>
    }
};
</script>

<?php
// Include footer
include '../../includes/footer.php';
?>