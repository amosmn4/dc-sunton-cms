<?php
/**
 * Attendance Reports
 * Deliverance Church Management System
 *
 * Dedicated Attendance report page (separate from analytics.php)
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

requireLogin();

if (!hasPermission('reports')) {
    setFlashMessage('error', 'You do not have permission to access this page');
    redirect(BASE_URL . 'modules/dashboard/');
    exit();
}

/* -------------------------------------------------
 * Filters
 * ------------------------------------------------- */
$date_from     = sanitizeInput($_GET['date_from'] ?? date('Y-m-01'));
$date_to       = sanitizeInput($_GET['date_to']   ?? date('Y-m-d'));
$department_id = (int)($_GET['department'] ?? 0);
$event_type    = sanitizeInput($_GET['event_type'] ?? ''); // optional, if you want to filter by event type
$export        = sanitizeInput($_GET['export'] ?? '');     // optional export toggle: '1' or 'true'

$db = Database::getInstance();
$churchInfo = getRecord('church_info', 'id', 1);

/* -------------------------------------------------
 * Basic data for filters
 * ------------------------------------------------- */
$departments = $db->executeQuery(
    "SELECT id, name FROM departments WHERE is_active = TRUE ORDER BY name"
)->fetchAll() ?: [];

$event_types = defined('EVENT_TYPES') ? EVENT_TYPES : []; // array like ['sunday_service' => 'Sunday Service', ...]

/* -------------------------------------------------
 * Attendance Data
 * ------------------------------------------------- */

// 1) Attendance by service type (event_type)
$sql = "
    SELECT 
        e.event_type,
        COUNT(DISTINCT ar.event_id)           AS services_held,
        COUNT(DISTINCT ar.member_id)          AS total_attendees,
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
$sql .= " GROUP BY e.event_type
          ORDER BY total_attendees DESC, services_held DESC, e.event_type ASC";

$attendance_by_type = $db->executeQuery($sql, $params)->fetchAll() ?: [];

// 2) Daily trend (by calendar date)
$sql = "
    SELECT 
        DATE(e.event_date) AS date,
        COUNT(DISTINCT ar.member_id) AS attendees,
        COUNT(DISTINCT e.id)         AS events
    FROM attendance_records ar
    JOIN events e ON ar.event_id = e.id
    WHERE DATE(ar.check_in_time) BETWEEN ? AND ? 
      AND ar.is_present = TRUE
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
$sql .= " GROUP BY DATE(e.event_date)
          ORDER BY date DESC";
$daily_trend = $db->executeQuery($sql, $params)->fetchAll() ?: [];

// 3) Top attendees (by count within period)
$sql = "
    SELECT 
        m.member_number,
        CONCAT(m.first_name, ' ', m.last_name) AS name,
        COUNT(ar.id) AS attendance_count
    FROM attendance_records ar
    JOIN members m ON m.id = ar.member_id
    JOIN events e  ON e.id = ar.event_id
    WHERE DATE(ar.check_in_time) BETWEEN ? AND ?
      AND ar.is_present = TRUE
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
$sql .= " GROUP BY m.id
          ORDER BY attendance_count DESC, name ASC
          LIMIT 10";
$top_attendees = $db->executeQuery($sql, $params)->fetchAll() ?: [];

/* -------------------------------------------------
 * Quick Stats (service days, latest attendance, avg of top 10)
 * ------------------------------------------------- */

// Service days = number of distinct calendar days that had at least one present record in range
$sql = "
    SELECT COUNT(*) AS service_days FROM (
        SELECT DATE(e.event_date) AS d
        FROM attendance_records ar
        JOIN events e ON e.id = ar.event_id
        WHERE DATE(ar.check_in_time) BETWEEN ? AND ?
          AND ar.is_present = TRUE
";
$params = [$date_from, $date_to];
if ($department_id > 0) { $sql .= " AND e.department_id = ?"; $params[] = $department_id; }
if (!empty($event_type)) { $sql .= " AND e.event_type = ?";   $params[] = $event_type;     }
$sql .= " GROUP BY DATE(e.event_date)
    ) t";
$service_days = (int)($db->executeQuery($sql, $params)->fetch()['service_days'] ?? 0);

// Latest attendance = attendees on the most recent day in $daily_trend
$latest_attendance = 0;
if (!empty($daily_trend)) {
    // daily_trend is DESC by date, so first row is latest
    $latest_attendance = (int)($daily_trend[0]['attendees'] ?? 0);
}

// Average of top-10 attendees
$avg_top10_attendance = 0;
if (!empty($top_attendees)) {
    $sum = 0;
    foreach ($top_attendees as $t) { $sum += (int)$t['attendance_count']; }
    $avg_top10_attendance = (int)round($sum / max(count($top_attendees), 1), 0);
}

/* -------------------------------------------------
 * Export (optional lightweight CSV)
 * ------------------------------------------------- */
if ($export === '1' || strtolower($export) === 'true') {
    // Simple export for the "Attendance by Service Type" section
    $headers = ['Service Type', 'Services Held', 'Total Attendees', 'Attendance Rate (%)'];
    $rows = [];
    foreach ($attendance_by_type as $r) {
        $typeLabel = isset($event_types[$r['event_type']]) ? $event_types[$r['event_type']] : ucwords(str_replace('_', ' ', (string)$r['event_type']));
        $rows[] = [
            $typeLabel,
            (int)$r['services_held'],
            (int)$r['total_attendees'],
            number_format(((float)$r['attendance_rate'] ?? 0) * 100, 1)
        ];
    }
    $filename = 'attendance_by_type_' . date('Ymd_His') . '.csv';
    exportToCSV($rows, $headers, $filename);
    exit();
}

/* -------------------------------------------------
 * Render
 * ------------------------------------------------- */

$page_title       = 'Attendance Reports';
$page_icon        = 'fas fa-chart-line';
$page_description = 'View attendance trends and patterns';

include '../../includes/header.php';
?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body p-3">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="date_from" class="form-label">Date From</label>
                        <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $date_from; ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="date_to" class="form-label">Date To</label>
                        <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $date_to; ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="department" class="form-label">Department</label>
                        <select class="form-select" id="department" name="department">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>" <?php echo ($department_id === (int)$dept['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="event_type" class="form-label">Event Type</label>
                        <select class="form-select" id="event_type" name="event_type">
                            <option value="">All Types</option>
                            <?php foreach ($event_types as $key => $label): ?>
                                <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $event_type === $key ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-church-primary w-100">
                            <i class="fas fa-filter me-2"></i>Filter
                        </button>
                    </div>
                    <div class="col-md-3">
                        <label>&nbsp;</label>
                        <a href="?<?php
                            // keep current filters but add export
                            $q = $_GET; $q['export'] = '1'; echo http_build_query($q);
                        ?>" class="btn btn-outline-success w-100">
                            <i class="fas fa-file-csv me-2"></i>Export by Type (CSV)
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Quick Stats -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card stats-card border-0 shadow-sm">
            <div class="card-body">
                <div class="stats-icon" style="background: linear-gradient(135deg, #3b82f6, #2563eb);">
                    <i class="fas fa-calendar-check text-white"></i>
                </div>
                <div class="stats-number"><?php echo (int)$service_days; ?></div>
                <div class="stats-label">Service Days</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stats-card border-0 shadow-sm">
            <div class="card-body">
                <div class="stats-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                    <i class="fas fa-users text-white"></i>
                </div>
                <div class="stats-number"><?php echo (int)$latest_attendance; ?></div>
                <div class="stats-label">Latest Attendance</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stats-card border-0 shadow-sm">
            <div class="card-body">
                <div class="stats-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                    <i class="fas fa-chart-line text-white"></i>
                </div>
                <div class="stats-number"><?php echo (int)$avg_top10_attendance; ?></div>
                <div class="stats-label">Avg Top 10</div>
            </div>
        </div>
    </div>
</div>

<!-- Attendance by Service Type -->
<div class="row mb-4">
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-gradient-church text-white">
                <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Attendance by Service Type</h5>
            </div>
            <div class="card-body">
                <?php if (empty($attendance_by_type)): ?>
                    <div class="text-center text-muted py-3">
                        <i class="fas fa-info-circle me-2"></i>No attendance data for the selected filters.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Service Type</th>
                                    <th class="text-end">Services</th>
                                    <th class="text-end">Total Attendees</th>
                                    <th class="text-end">Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attendance_by_type as $row): 
                                    $label = isset($event_types[$row['event_type']]) 
                                        ? $event_types[$row['event_type']] 
                                        : ucwords(str_replace('_', ' ', (string)$row['event_type']));
                                    $rate = (float)($row['attendance_rate'] ?? 0) * 100;
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($label); ?></td>
                                    <td class="text-end"><?php echo (int)$row['services_held']; ?></td>
                                    <td class="text-end"><?php echo (int)$row['total_attendees']; ?></td>
                                    <td class="text-end">
                                        <span class="badge bg-success"><?php echo number_format($rate, 1); ?>%</span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Top Attendees -->
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-gradient-church text-white">
                <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Top Attendees</h5>
            </div>
            <div class="card-body">
                <?php if (empty($top_attendees)): ?>
                    <div class="text-center text-muted py-3">
                        <i class="fas fa-info-circle me-2"></i>No top attendees in the selected period.
                    </div>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($top_attendees as $k => $att): ?>
                            <div class="list-group-item border-0 py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-0">
                                            <span class="badge bg-church-blue me-2"><?php echo $k + 1; ?></span>
                                            <?php echo htmlspecialchars($att['name']); ?>
                                        </h6>
                                        <small class="text-muted"><?php echo htmlspecialchars($att['member_number']); ?></small>
                                    </div>
                                    <div class="text-end">
                                        <strong class="text-church-red"><?php echo (int)$att['attendance_count']; ?></strong>
                                        <small class="text-muted d-block">attendances</small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Daily Trend Chart -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-gradient-church text-white">
                <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Daily Attendance Trend</h5>
            </div>
            <div class="card-body">
                <?php if (empty($daily_trend)): ?>
                    <div class="text-center text-muted py-3">
                        <i class="fas fa-info-circle me-2"></i>No daily attendance trend available.
                    </div>
                <?php else: ?>
                    <div style="position: relative; height: 320px;">
                        <canvas id="attendanceChart"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Back -->
<div class="row mt-3">
    <div class="col-12">
        <a href="<?php echo BASE_URL; ?>modules/reports/" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Reports
        </a>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<?php if (!empty($daily_trend)): ?>
<script>
(function(){
    const ctx = document.getElementById('attendanceChart').getContext('2d');
    const labels = <?php echo json_encode(array_map(fn($d) => formatDisplayDate($d['date']), $daily_trend)); ?>.reverse();
    const dataAtt = <?php echo json_encode(array_map(fn($d) => (int)$d['attendees'], $daily_trend)); ?>.reverse();

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Attendance',
                data: dataAtt,
                borderColor: '#ff2400',
                backgroundColor: 'rgba(255,36,0,0.12)',
                borderWidth: 3,
                fill: true,
                tension: 0.35,
                pointRadius: 4,
                pointBackgroundColor: '#ff2400'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: true } },
            scales: { y: { beginAtZero: true } }
        }
    });
})();
</script>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
