<?php
/**
 * Attendance & Analytics Reports
 * Deliverance Church Management System
 * 
 * Unified structure for attendance.php and analytics.php
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

requireLogin();

if (!hasPermission('reports')) {
    setFlashMessage('error', 'You do not have permission to access this page');
    redirect(BASE_URL . 'modules/dashboard/');
}

// Determine report type dynamically
$report_type = isset($_GET['type']) ? sanitizeInput($_GET['type']) : 'attendance';
if (basename($_SERVER['PHP_SELF']) === 'analytics.php') {
    $report_type = 'analytics';
}

// Filters
$date_from = sanitizeInput($_GET['date_from'] ?? date('Y-m-01'));
$date_to = sanitizeInput($_GET['date_to'] ?? date('Y-m-d'));
$department_id = (int)($_GET['department'] ?? 0);
$event_type = sanitizeInput($_GET['event_type'] ?? '');

$db = Database::getInstance();
$churchInfo = getRecord('church_info', 'id', 1);

// Fetch departments & event types
$departments = $db->executeQuery("SELECT * FROM departments WHERE is_active = TRUE ORDER BY name")->fetchAll();
$event_types = EVENT_TYPES;

/**
 * -----------------------------------
 * ATTENDANCE REPORT
 * -----------------------------------
 */
if ($report_type === 'attendance') {
    // Attendance by type
    $sql = "
        SELECT 
            e.event_type,
            COUNT(DISTINCT ar.event_id) AS services_held,
            COUNT(DISTINCT ar.member_id) AS total_attendees,
            AVG(CASE WHEN ar.is_present THEN 1 ELSE 0 END) AS attendance_rate
        FROM attendance_records ar
        JOIN events e ON ar.event_id = e.id
        WHERE DATE(ar.check_in_time) BETWEEN ? AND ?
    ";
    $params = [$date_from, $date_to];

    if ($department_id > 0) {
        $sql .= " AND e.department_id = ?";
        $params[] = $department_id;
    }
    if (!empty($event_type)) {
        $sql .= " AND e.event_type = ?";
        $params[] = $event_type;
    }

    $sql .= " GROUP BY e.event_type";
    $attendance_by_type = $db->executeQuery($sql, $params)->fetchAll() ?: [];

    // Daily attendance trend
    $sql = "
        SELECT 
            DATE(e.event_date) AS date,
            COUNT(DISTINCT ar.member_id) AS attendees,
            COUNT(DISTINCT e.id) AS events
        FROM attendance_records ar
        JOIN events e ON ar.event_id = e.id
        WHERE DATE(ar.check_in_time) BETWEEN ? AND ? AND ar.is_present = TRUE
    ";
    $params = [$date_from, $date_to];
    if ($department_id > 0) {
        $sql .= " AND e.department_id = ?";
        $params[] = $department_id;
    }
    $sql .= " GROUP BY DATE(e.event_date) ORDER BY date DESC";
    $daily_trend = $db->executeQuery($sql, $params)->fetchAll() ?: [];

    // Top attendees
    $sql = "
        SELECT 
            m.member_number,
            CONCAT(m.first_name, ' ', m.last_name) AS name,
            COUNT(ar.id) AS attendance_count
        FROM attendance_records ar
        JOIN members m ON ar.member_id = m.id
        WHERE DATE(ar.check_in_time) BETWEEN ? AND ? AND ar.is_present = TRUE
        GROUP BY m.id
        ORDER BY attendance_count DESC
        LIMIT 10
    ";
    $top_attendees = $db->executeQuery($sql, [$date_from, $date_to])->fetchAll() ?: [];
}

/**
 * -----------------------------------
 * ANALYTICS REPORT
 * -----------------------------------
 */
if ($report_type === 'analytics') {
    $total_members = (int)($db->executeQuery("SELECT COUNT(*) AS total FROM members WHERE membership_status='active'")->fetch()['total'] ?? 0);
    $total_events = (int)($db->executeQuery("SELECT COUNT(*) AS total FROM events WHERE DATE(event_date) BETWEEN ? AND ?", [$date_from, $date_to])->fetch()['total'] ?? 0);
    $unique_attendees = (int)($db->executeQuery("SELECT COUNT(DISTINCT member_id) AS total FROM attendance_records WHERE DATE(check_in_time) BETWEEN ? AND ?", [$date_from, $date_to])->fetch()['total'] ?? 0);

    $avg_attendance = ($total_events > 0) ? (int)round($unique_attendees / $total_events, 0) : 0;

    // Engagement Scores
    $sql = "
        SELECT 
            m.id,
            CONCAT(m.first_name, ' ', m.last_name) AS name,
            COUNT(ar.id) AS attendance_count,
            CASE WHEN ? > 0 THEN (COUNT(ar.id) * 100.0 / ?) ELSE 0 END AS engagement_score
        FROM members m
        LEFT JOIN attendance_records ar ON m.id = ar.member_id AND DATE(ar.check_in_time) BETWEEN ? AND ?
        WHERE m.membership_status = 'active'
        GROUP BY m.id
        ORDER BY engagement_score DESC, attendance_count DESC
        LIMIT 15
    ";
    $engagement_scores = $db->executeQuery($sql, [$total_events, $total_events, $date_from, $date_to])->fetchAll() ?: [];

    // Department Performance
    $sql = "
        SELECT 
            d.name AS department,
            COUNT(DISTINCT md.member_id) AS members,
            COUNT(DISTINCT ar.member_id) AS active_members,
            CASE WHEN COUNT(DISTINCT md.member_id) > 0 
                THEN ROUND(COUNT(DISTINCT ar.member_id) * 100.0 / COUNT(DISTINCT md.member_id), 1)
                ELSE 0 END AS participation_rate
        FROM departments d
        LEFT JOIN member_departments md ON d.id = md.department_id AND md.is_active = TRUE
        LEFT JOIN attendance_records ar ON md.member_id = ar.member_id AND DATE(ar.check_in_time) BETWEEN ? AND ?
        WHERE d.is_active = TRUE
        GROUP BY d.id, d.name
        ORDER BY participation_rate DESC
    ";
    $department_performance = $db->executeQuery($sql, [$date_from, $date_to])->fetchAll() ?: [];
}

// Include header
include '../../includes/header.php';
?>

<!-- =========================================
     PAGE CONTENT
========================================= -->

<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body p-3">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Date From</label>
                        <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date To</label>
                        <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Department</label>
                        <select class="form-select" name="department">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>" <?php echo $department_id == $dept['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 align-self-end">
                        <button type="submit" class="btn btn-church-primary w-100">
                            <i class="fas fa-filter me-2"></i>Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if ($report_type === 'attendance'): ?>
    <?php include 'partials/attendance_view.php'; ?>
<?php else: ?>
    <!-- ================= ANALYTICS DASHBOARD ================= -->
    <div class="row mb-4">
        <div class="col-md-3"><div class="card stats-card"><div class="card-body"><h5>Total Members</h5><h3><?php echo $total_members; ?></h3></div></div></div>
        <div class="col-md-3"><div class="card stats-card"><div class="card-body"><h5>Events Held</h5><h3><?php echo $total_events; ?></h3></div></div></div>
        <div class="col-md-3"><div class="card stats-card"><div class="card-body"><h5>Unique Attendees</h5><h3><?php echo $unique_attendees; ?></h3></div></div></div>
        <div class="col-md-3"><div class="card stats-card"><div class="card-body"><h5>Avg Per Event</h5><h3><?php echo $avg_attendance; ?></h3></div></div></div>
    </div>

    <!-- Top Engaged Members -->
    <div class="card mb-4 shadow-sm border-0">
        <div class="card-header bg-gradient-church text-white">
            <h5 class="mb-0"><i class="fas fa-fire me-2"></i>Top Engaged Members</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($engagement_scores)): ?>
                <div class="p-4 text-center text-muted">
                    <i class="fas fa-info-circle me-2"></i>No engagement data for the selected period.
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($engagement_scores as $key => $member): 
                        $score = (float)($member['engagement_score'] ?? 0);
                        $score = max(0, min($score, 100)); ?>
                        <div class="list-group-item py-3 px-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0">
                                    <span class="badge bg-church-red me-2"><?php echo $key + 1; ?></span>
                                    <?php echo htmlspecialchars($member['name']); ?>
                                </h6>
                                <strong><?php echo number_format($score, 1); ?>%</strong>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-church-red" style="width: <?php echo $score; ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Department Performance -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-gradient-church text-white">
            <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Department Performance</h5>
        </div>
        <div class="card-body">
            <?php if (empty($department_performance)): ?>
                <p class="text-center text-muted mb-0"><i class="fas fa-info-circle me-2"></i>No department data found.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead><tr><th>Department</th><th>Members</th><th>Active</th><th>Rate</th></tr></thead>
                        <tbody>
                            <?php foreach ($department_performance as $dept): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($dept['department']); ?></td>
                                <td><?php echo (int)$dept['members']; ?></td>
                                <td><?php echo (int)$dept['active_members']; ?></td>
                                <td><span class="badge bg-info"><?php echo number_format($dept['participation_rate'], 1); ?>%</span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Export Button -->
<div class="mt-4">
    <a href="<?php echo BASE_URL; ?>modules/reports/" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Back</a>
    <a href="<?php echo $_SERVER['PHP_SELF']; ?>?export=true&type=<?php echo $report_type; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="btn btn-church-primary"><i class="fas fa-download me-2"></i>Export</a>
</div>

<?php include '../../includes/footer.php'; ?>
