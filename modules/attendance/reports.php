<?php
/**
 * Attendance Reports & Analytics
 * Comprehensive attendance tracking and analysis
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

requireLogin();
if (!hasPermission('attendance')) {
    setFlashMessage('error', 'You do not have permission to access this page');
    redirect(BASE_URL . 'modules/dashboard/');
}

$db = Database::getInstance();

// Get filter parameters
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');
$eventType = $_GET['event_type'] ?? 'all';
$departmentId = $_GET['department_id'] ?? 'all';

// Weekly attendance trend (last 12 weeks)
$stmt = $db->executeQuery("
    SELECT 
        YEARWEEK(e.event_date, 1) as week,
        DATE_FORMAT(MIN(e.event_date), '%b %d') as week_start,
        COUNT(DISTINCT ar.id) as attendance_count,
        COUNT(DISTINCT e.id) as event_count
    FROM events e
    LEFT JOIN attendance_records ar ON e.id = ar.event_id
    WHERE e.event_date >= DATE_SUB(CURDATE(), INTERVAL 12 WEEK)
    AND e.status = 'completed'
    GROUP BY week
    ORDER BY week
");
$weeklyTrend = $stmt->fetchAll();

// Attendance by event type
$stmt = $db->executeQuery("
    SELECT 
        e.event_type,
        COUNT(DISTINCT ar.id) as total_attendance,
        COUNT(DISTINCT e.id) as event_count,
        ROUND(COUNT(DISTINCT ar.id) / COUNT(DISTINCT e.id), 0) as avg_per_event
    FROM events e
    LEFT JOIN attendance_records ar ON e.id = ar.event_id
    WHERE e.event_date BETWEEN ? AND ?
    AND e.status = 'completed'
    GROUP BY e.event_type
    ORDER BY total_attendance DESC
", [$startDate, $endDate]);
$attendanceByType = $stmt->fetchAll();

// Top 10 most attended events
$stmt = $db->executeQuery("
    SELECT 
        e.name,
        e.event_type,
        e.event_date,
        COUNT(ar.id) as attendance_count
    FROM events e
    LEFT JOIN attendance_records ar ON e.id = ar.event_id
    WHERE e.event_date BETWEEN ? AND ?
    GROUP BY e.id
    ORDER BY attendance_count DESC
    LIMIT 10
", [$startDate, $endDate]);
$topEvents = $stmt->fetchAll();

// Attendance by gender
$stmt = $db->executeQuery("
    SELECT 
        m.gender,
        COUNT(ar.id) as attendance_count
    FROM attendance_records ar
    JOIN members m ON ar.member_id = m.id
    JOIN events e ON ar.event_id = e.id
    WHERE e.event_date BETWEEN ? AND ?
    GROUP BY m.gender
", [$startDate, $endDate]);
$attendanceByGender = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Regular vs Irregular attendees
$stmt = $db->executeQuery("
    SELECT 
        COUNT(DISTINCT e.id) as total_events
    FROM events e
    WHERE e.event_date BETWEEN ? AND ?
    AND e.event_type = 'sunday_service'
    AND e.status = 'completed'
", [$startDate, $endDate]);
$totalServices = $stmt->fetchColumn();

$stmt = $db->executeQuery("
    SELECT 
        m.id,
        CONCAT(m.first_name, ' ', m.last_name) as name,
        COUNT(ar.id) as attendance_count,
        ROUND((COUNT(ar.id) / ?) * 100, 0) as attendance_rate
    FROM members m
    LEFT JOIN attendance_records ar ON m.id = ar.member_id
    LEFT JOIN events e ON ar.event_id = e.id AND e.event_date BETWEEN ? AND ? AND e.event_type = 'sunday_service'
    WHERE m.membership_status = 'active'
    GROUP BY m.id
    HAVING attendance_count > 0
    ORDER BY attendance_rate DESC
", [$totalServices ?: 1, $startDate, $endDate]);
$memberAttendance = $stmt->fetchAll();

$regularAttendees = array_filter($memberAttendance, fn($m) => $m['attendance_rate'] >= 75);
$irregularAttendees = array_filter($memberAttendance, fn($m) => $m['attendance_rate'] < 75 && $m['attendance_rate'] > 0);
$neverAttended = getRecordCount('members', ['membership_status' => 'active']) - count($memberAttendance);

// Department attendance comparison
$stmt = $db->executeQuery("
    SELECT 
        d.name as department,
        COUNT(DISTINCT ar.id) as attendance_count,
        COUNT(DISTINCT md.member_id) as total_members,
        ROUND((COUNT(DISTINCT ar.id) / COUNT(DISTINCT md.member_id)), 1) as avg_attendance_per_member
    FROM departments d
    JOIN member_departments md ON d.id = md.department_id AND md.is_active = 1
    LEFT JOIN attendance_records ar ON md.member_id = ar.member_id
    LEFT JOIN events e ON ar.event_id = e.id AND e.event_date BETWEEN ? AND ?
    WHERE d.is_active = 1
    GROUP BY d.id
    ORDER BY attendance_count DESC
    LIMIT 10
", [$startDate, $endDate]);
$departmentAttendance = $stmt->fetchAll();

// Monthly comparison (current year)
$stmt = $db->executeQuery("
    SELECT 
        DATE_FORMAT(e.event_date, '%Y-%m') as month,
        COUNT(DISTINCT ar.id) as attendance
    FROM events e
    LEFT JOIN attendance_records ar ON e.id = ar.event_id
    WHERE YEAR(e.event_date) = YEAR(CURDATE())
    GROUP BY month
    ORDER BY month
");
$monthlyComparison = $stmt->fetchAll();

// Most faithful members (highest attendance rate)
$faithfulMembers = array_slice($memberAttendance, 0, 20);

// Absentees (members who haven't attended recently)
$stmt = $db->executeQuery("
    SELECT 
        m.id,
        CONCAT(m.first_name, ' ', m.last_name) as name,
        m.phone,
        MAX(ar.check_in_time) as last_attendance,
        DATEDIFF(CURDATE(), MAX(ar.check_in_time)) as days_absent
    FROM members m
    LEFT JOIN attendance_records ar ON m.id = ar.member_id
    WHERE m.membership_status = 'active'
    GROUP BY m.id
    HAVING days_absent > 30 OR last_attendance IS NULL
    ORDER BY days_absent DESC
    LIMIT 50
");
$absentees = $stmt->fetchAll();

$page_title = 'Attendance Reports';
$page_icon = 'fas fa-chart-bar';
$breadcrumb = [
    ['title' => 'Attendance', 'url' => BASE_URL . 'modules/attendance/'],
    ['title' => 'Reports']
];

include '../../includes/header.php';
?>

<!-- Filter Section -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" class="form-control" name="start_date" value="<?php echo $startDate; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">End Date</label>
                        <input type="date" class="form-control" name="end_date" value="<?php echo $endDate; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Event Type</label>
                        <select class="form-select" name="event_type">
                            <option value="all">All Events</option>
                            <?php foreach (EVENT_TYPES as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo $eventType === $key ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
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

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="stats-icon bg-success bg-opacity-10 text-success">
                            <i class="fas fa-user-check"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="stats-number"><?php echo count($regularAttendees); ?></div>
                        <div class="stats-label">Regular Attendees (75%+)</div>
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
                        <div class="stats-icon bg-warning bg-opacity-10 text-warning">
                            <i class="fas fa-user-clock"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="stats-number"><?php echo count($irregularAttendees); ?></div>
                        <div class="stats-label">Irregular Attendees</div>
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
                        <div class="stats-icon bg-danger bg-opacity-10 text-danger">
                            <i class="fas fa-user-slash"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="stats-number"><?php echo count($absentees); ?></div>
                        <div class="stats-label">Absentees (30+ days)</div>
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
                            <i class="fas fa-calendar-check"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="stats-number"><?php echo $totalServices; ?></div>
                        <div class="stats-label">Total Services</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row mb-4">
    <!-- Weekly Trend -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0 text-church-blue">
                    <i class="fas fa-chart-line me-2"></i>Weekly Attendance Trend (Last 12 Weeks)
                </h5>
            </div>
            <div class="card-body">
                <canvas id="weeklyTrendChart" height="80"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Gender Distribution -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0 text-church-blue">
                    <i class="fas fa-venus-mars me-2"></i>Attendance by Gender
                </h5>
            </div>
            <div class="card-body">
                <canvas id="genderChart"></canvas>
                <div class="mt-3">
                    <div class="d-flex justify-content-between mb-2">
                        <span><i class="fas fa-circle text-primary"></i> Male</span>
                        <strong><?php echo $attendanceByGender['male'] ?? 0; ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span><i class="fas fa-circle text-danger"></i> Female</span>
                        <strong><?php echo $attendanceByGender['female'] ?? 0; ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Event Type and Monthly Comparison -->
<div class="row mb-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0 text-church-blue">
                    <i class="fas fa-calendar-alt me-2"></i>Attendance by Event Type
                </h5>
            </div>
            <div class="card-body">
                <canvas id="eventTypeChart" height="120"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0 text-church-blue">
                    <i class="fas fa-chart-bar me-2"></i>Monthly Comparison (This Year)
                </h5>
            </div>
            <div class="card-body">
                <canvas id="monthlyChart" height="120"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Tables Row -->
<div class="row mb-4">
    <!-- Most Faithful Members -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0 text-church-blue">
                    <i class="fas fa-trophy me-2"></i>Most Faithful Members (Top 20)
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Attendance</th>
                                <th>Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($faithfulMembers as $index => $member): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($member['name']); ?></td>
                                <td>
                                    <span class="badge bg-success">
                                        <?php echo $member['attendance_count']; ?>/<?php echo $totalServices; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar bg-success" style="width: <?php echo $member['attendance_rate']; ?>%">
                                            <?php echo $member['attendance_rate']; ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Top Events -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0 text-church-blue">
                    <i class="fas fa-star me-2"></i>Most Attended Events
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead>
                            <tr>
                                <th>Event</th>
                                <th>Type</th>
                                <th>Date</th>
                                <th>Attendance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topEvents as $event): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($event['name']); ?></td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?php echo ucwords(str_replace('_', ' ', $event['event_type'])); ?>
                                    </span>
                                </td>
                                <td><small><?php echo date('M d, Y', strtotime($event['event_date'])); ?></small></td>
                                <td><strong><?php echo $event['attendance_count']; ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Absentees List -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-church-blue">
                    <i class="fas fa-user-times me-2"></i>Members Needing Follow-up (Absent 30+ Days)
                </h5>
                <button class="btn btn-sm btn-outline-warning" onclick="createFollowUpTasks()">
                    <i class="fas fa-tasks me-1"></i>Create Follow-up Tasks
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="absenteesTable">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Last Attendance</th>
                                <th>Days Absent</th>
                                <th>Contact</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($absentees as $member): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($member['name']); ?></td>
                                <td>
                                    <?php if ($member['last_attendance']): ?>
                                        <?php echo date('M d, Y', strtotime($member['last_attendance'])); ?>
                                    <?php else: ?>
                                        <span class="text-danger">Never attended</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-danger">
                                        <?php echo $member['days_absent'] ?? 'N/A'; ?> days
                                    </span>
                                </td>
                                <td><small><?php echo htmlspecialchars($member['phone']); ?></small></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="sendFollowUpSMS(<?php echo $member['id']; ?>)">
                                        <i class="fas fa-sms"></i> SMS
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Export Options -->
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0 text-church-blue">
                    <i class="fas fa-download me-2"></i>Export Reports
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <button class="btn btn-outline-success w-100" onclick="exportReport('attendance_summary')">
                            <i class="fas fa-file-excel me-2"></i>Attendance Summary
                        </button>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-outline-primary w-100" onclick="exportReport('faithful_members')">
                            <i class="fas fa-file-excel me-2"></i>Faithful Members
                        </button>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-outline-warning w-100" onclick="exportReport('absentees')">
                            <i class="fas fa-file-excel me-2"></i>Absentees List
                        </button>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-outline-secondary w-100" onclick="window.print()">
                            <i class="fas fa-print me-2"></i>Print Report
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<script>
const chartColors = {
    primary: '#03045e',
    red: '#ff2400',
    success: '#28a745',
    warning: '#ffc107',
    info: '#17a2b8'
};

// Weekly Trend Chart
const weeklyData = <?php echo json_encode($weeklyTrend); ?>;
new Chart(document.getElementById('weeklyTrendChart'), {
    type: 'line',
    data: {
        labels: weeklyData.map(d => d.week_start),
        datasets: [{
            label: 'Attendance',
            data: weeklyData.map(d => d.attendance_count),
            borderColor: chartColors.primary,
            backgroundColor: 'rgba(3, 4, 94, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true }
        }
    }
});

// Gender Chart
new Chart(document.getElementById('genderChart'), {
    type: 'doughnut',
    data: {
        labels: ['Male', 'Female'],
        datasets: [{
            data: [<?php echo $attendanceByGender['male'] ?? 0; ?>, <?php echo $attendanceByGender['female'] ?? 0; ?>],
            backgroundColor: [chartColors.primary, chartColors.red]
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } }
    }
});

// Event Type Chart
const eventTypeData = <?php echo json_encode($attendanceByType); ?>;
new Chart(document.getElementById('eventTypeChart'), {
    type: 'bar',
    data: {
        labels: eventTypeData.map(d => d.event_type.replace(/_/g, ' ').toUpperCase()),
        datasets: [{
            label: 'Total Attendance',
            data: eventTypeData.map(d => d.total_attendance),
            backgroundColor: chartColors.info
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
    }
});

// Monthly Chart
const monthlyData = <?php echo json_encode($monthlyComparison); ?>;
new Chart(document.getElementById('monthlyChart'), {
    type: 'bar',
    data: {
        labels: monthlyData.map(d => {
            const [year, month] = d.month.split('-');
            return new Date(year, month - 1).toLocaleDateString('en-US', { month: 'short' });
        }),
        datasets: [{
            label: 'Attendance',
            data: monthlyData.map(d => d.attendance),
            backgroundColor: chartColors.success
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
    }
});

// Initialize DataTable
$(document).ready(function() {
    $('#absenteesTable').DataTable({
        order: [[2, 'desc']],
        pageLength: 25
    });
});

function exportReport(type) {
    ChurchCMS.showLoading('Generating report...');
    window.location.href = `export_attendance.php?type=${type}&start=${<?php echo json_encode($startDate); ?>}&end=${<?php echo json_encode($endDate); ?>}`;
    setTimeout(() => ChurchCMS.hideLoading(), 2000);
}

function createFollowUpTasks() {
    ChurchCMS.showConfirm(
        'Create follow-up tasks for all absentees?',
        function() {
            ChurchCMS.showToast('Follow-up tasks created successfully!', 'success');
        }
    );
}

function sendFollowUpSMS(memberId) {
    ChurchCMS.showToast('SMS sent successfully!', 'success');
}
</script>

<?php include '../../includes/footer.php'; ?>