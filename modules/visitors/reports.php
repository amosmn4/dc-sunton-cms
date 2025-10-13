<?php
/**
 * Visitor Reports & Analytics
 * Track visitor trends and conversion rates
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

requireLogin();
if (!hasPermission('visitors')) {
    setFlashMessage('error', 'You do not have permission to access this page');
    redirect(BASE_URL . 'modules/dashboard/');
}

$db = Database::getInstance();

// Date filter
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

// Visitors by status
$stmt = $db->executeQuery("
    SELECT status, COUNT(*) as count
    FROM visitors
    WHERE visit_date BETWEEN ? AND ?
    GROUP BY status
", [$startDate, $endDate]);
$visitorsByStatus = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Visitors by source (how they heard about us)
$stmt = $db->executeQuery("
    SELECT how_heard_about_us, COUNT(*) as count
    FROM visitors
    WHERE visit_date BETWEEN ? AND ?
    AND how_heard_about_us IS NOT NULL
    GROUP BY how_heard_about_us
    ORDER BY count DESC
", [$startDate, $endDate]);
$visitorsBySource = $stmt->fetchAll();

// Weekly visitor trend (last 12 weeks)
$stmt = $db->executeQuery("
    SELECT 
        YEARWEEK(visit_date, 1) as week,
        DATE_FORMAT(MIN(visit_date), '%b %d') as week_start,
        COUNT(*) as visitor_count
    FROM visitors
    WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 12 WEEK)
    GROUP BY week
    ORDER BY week
");
$weeklyTrend = $stmt->fetchAll();

// Conversion rate (visitors to members)
$totalVisitors = array_sum($visitorsByStatus);
$convertedMembers = $visitorsByStatus['converted_member'] ?? 0;
$conversionRate = $totalVisitors > 0 ? ($convertedMembers / $totalVisitors) * 100 : 0;

// Follow-up effectiveness
$stmt = $db->executeQuery("
    SELECT 
        v.status,
        COUNT(DISTINCT v.id) as visitor_count,
        COUNT(DISTINCT vf.id) as followup_count,
        ROUND(COUNT(DISTINCT vf.id) / COUNT(DISTINCT v.id), 2) as followups_per_visitor
    FROM visitors v
    LEFT JOIN visitor_followups vf ON v.id = vf.visitor_id
    WHERE v.visit_date BETWEEN ? AND ?
    GROUP BY v.status
", [$startDate, $endDate]);
$followupStats = $stmt->fetchAll();

// Visitors needing follow-up
$stmt = $db->executeQuery("
    SELECT 
        v.*,
        MAX(vf.followup_date) as last_followup,
        DATEDIFF(CURDATE(), MAX(vf.followup_date)) as days_since_followup
    FROM visitors v
    LEFT JOIN visitor_followups vf ON v.id = vf.visitor_id
    WHERE v.status IN ('new_visitor', 'follow_up')
    GROUP BY v.id
    HAVING last_followup IS NULL OR days_since_followup > 14
    ORDER BY v.visit_date DESC
    LIMIT 50
");
$needsFollowup = $stmt->fetchAll();

// Top services attracting visitors
$stmt = $db->executeQuery("
    SELECT 
        service_attended,
        COUNT(*) as visitor_count
    FROM visitors
    WHERE visit_date BETWEEN ? AND ?
    AND service_attended IS NOT NULL
    GROUP BY service_attended
    ORDER BY visitor_count DESC
    LIMIT 10
", [$startDate, $endDate]);
$topServices = $stmt->fetchAll();

// Visitors by age group and gender
$stmt = $db->executeQuery("
    SELECT 
        age_group,
        gender,
        COUNT(*) as count
    FROM visitors
    WHERE visit_date BETWEEN ? AND ?
    GROUP BY age_group, gender
", [$startDate, $endDate]);
$demographics = $stmt->fetchAll();

$page_title = 'Visitor Reports';
$page_icon = 'fas fa-user-friends';
$breadcrumb = [
    ['title' => 'Visitors', 'url' => BASE_URL . 'modules/visitors/'],
    ['title' => 'Reports']
];

include '../../includes/header.php';
?>

<!-- Filter Bar -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Start Date</label>
                        <input type="date" class="form-control" name="start_date" value="<?php echo $startDate; ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">End Date</label>
                        <input type="date" class="form-control" name="end_date" value="<?php echo $endDate; ?>">
                    </div>
                    <div class="col-md-4">
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

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="stats-icon bg-primary bg-opacity-10 text-primary">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="stats-number"><?php echo $totalVisitors; ?></div>
                        <div class="stats-label">Total Visitors</div>
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
                            <i class="fas fa-user-check"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="stats-number"><?php echo $convertedMembers; ?></div>
                        <div class="stats-label">Converted to Members</div>
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
                            <i class="fas fa-percentage"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="stats-number"><?php echo number_format($conversionRate, 1); ?>%</div>
                        <div class="stats-label">Conversion Rate</div>
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
                            <i class="fas fa-phone"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="stats-number"><?php echo count($needsFollowup); ?></div>
                        <div class="stats-label">Needs Follow-up</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row mb-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0 text-church-blue">
                    <i class="fas fa-chart-line me-2"></i>Weekly Visitor Trend (Last 12 Weeks)
                </h5>
            </div>
            <div class="card-body">
                <canvas id="weeklyTrendChart" height="80"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0 text-church-blue">
                    <i class="fas fa-chart-pie me-2"></i>Visitor Status
                </h5>
            </div>
            <div class="card-body">
                <canvas id="statusChart"></canvas>
                <div class="mt-3">
                    <?php foreach ($visitorsByStatus as $status => $count): ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span><i class="fas fa-circle text-primary"></i> <?php echo ucwords(str_replace('_', ' ', $status)); ?></span>
                        <strong><?php echo $count; ?></strong>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Source and Services -->
<div class="row mb-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0 text-church-blue">
                    <i class="fas fa-bullhorn me-2"></i>How Visitors Found Us
                </h5>
            </div>
            <div class="card-body">
                <canvas id="sourceChart" height="120"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0 text-church-blue">
                    <i class="fas fa-church me-2"></i>Top Services Attracting Visitors
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>Visitors</th>
                                <th>Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topServices as $service): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($service['service_attended']); ?></td>
                                <td><strong><?php echo $service['visitor_count']; ?></strong></td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar bg-primary" style="width: <?php echo ($service['visitor_count'] / $totalVisitors) * 100; ?>%">
                                            <?php echo number_format(($service['visitor_count'] / $totalVisitors) * 100, 1); ?>%
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
</div>

<!-- Follow-up Effectiveness -->
<div class="row mb-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0 text-church-blue">
                    <i class="fas fa-tasks me-2"></i>Follow-up Effectiveness
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Visitors</th>
                                <th>Follow-ups</th>
                                <th>Avg/Visitor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($followupStats as $stat): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?php echo ucwords(str_replace('_', ' ', $stat['status'])); ?>
                                    </span>
                                </td>
                                <td><strong><?php echo $stat['visitor_count']; ?></strong></td>
                                <td><?php echo $stat['followup_count']; ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $stat['followups_per_visitor'] >= 2 ? 'success' : 'warning'; ?>">
                                        <?php echo number_format($stat['followups_per_visitor'], 1); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0 text-church-blue">
                    <i class="fas fa-users-cog me-2"></i>Visitor Demographics
                </h5>
            </div>
            <div class="card-body">
                <canvas id="demographicsChart" height="120"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Visitors Needing Follow-up -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-church-blue">
                    <i class="fas fa-exclamation-circle me-2"></i>Visitors Needing Follow-up
                </h5>
                <button class="btn btn-sm btn-outline-primary" onclick="assignFollowUpTasks()">
                    <i class="fas fa-tasks me-1"></i>Assign Follow-up Tasks
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($needsFollowup)): ?>
                    <p class="text-success text-center py-4">
                        <i class="fas fa-check-circle fa-3x mb-3"></i><br>
                        All visitors have been followed up!
                    </p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover" id="followupTable">
                            <thead>
                                <tr>
                                    <th>Visitor</th>
                                    <th>Visit Date</th>
                                    <th>Status</th>
                                    <th>Last Follow-up</th>
                                    <th>Contact</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($needsFollowup as $visitor): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($visitor['first_name'] . ' ' . $visitor['last_name']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($visitor['visitor_number']); ?></small>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($visitor['visit_date'])); ?></td>
                                    <td>
                                        <span class="badge bg-warning">
                                            <?php echo ucwords(str_replace('_', ' ', $visitor['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($visitor['last_followup']): ?>
                                            <?php echo date('M d, Y', strtotime($visitor['last_followup'])); ?>
                                            <br><small class="text-muted"><?php echo $visitor['days_since_followup']; ?> days ago</small>
                                        <?php else: ?>
                                            <span class="text-danger">Never</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small>
                                            <?php if ($visitor['phone']): ?>
                                            <i class="fas fa-phone text-muted"></i> <?php echo htmlspecialchars($visitor['phone']); ?><br>
                                            <?php endif; ?>
                                            <?php if ($visitor['email']): ?>
                                            <i class="fas fa-envelope text-muted"></i> <?php echo htmlspecialchars($visitor['email']); ?>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" onclick="scheduleFollowup(<?php echo $visitor['id']; ?>)">
                                                <i class="fas fa-phone"></i> Call
                                            </button>
                                            <button class="btn btn-outline-success" onclick="sendFollowupSMS(<?php echo $visitor['id']; ?>)">
                                                <i class="fas fa-sms"></i> SMS
                                            </button>
                                        </div>
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
                        <button class="btn btn-outline-success w-100" onclick="exportReport('visitor_list')">
                            <i class="fas fa-file-excel me-2"></i>Visitor List
                        </button>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-outline-primary w-100" onclick="exportReport('conversion_report')">
                            <i class="fas fa-file-excel me-2"></i>Conversion Report
                        </button>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-outline-warning w-100" onclick="exportReport('followup_list')">
                            <i class="fas fa-file-excel me-2"></i>Follow-up List
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
            label: 'Visitors',
            data: weeklyData.map(d => d.visitor_count),
            borderColor: chartColors.primary,
            backgroundColor: 'rgba(3, 4, 94, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
    }
});

// Status Chart
const statusData = <?php echo json_encode(array_values($visitorsByStatus)); ?>;
new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_map(function($s) { return ucwords(str_replace('_', ' ', $s)); }, array_keys($visitorsByStatus))); ?>,
        datasets: [{
            data: statusData,
            backgroundColor: [chartColors.primary, chartColors.warning, chartColors.success, chartColors.info]
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } }
    }
});

// Source Chart
const sourceData = <?php echo json_encode($visitorsBySource); ?>;
new Chart(document.getElementById('sourceChart'), {
    type: 'bar',
    data: {
        labels: sourceData.map(d => d.how_heard_about_us),
        datasets: [{
            label: 'Visitors',
            data: sourceData.map(d => d.count),
            backgroundColor: chartColors.info
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
    }
});

// Demographics Chart
const demoData = <?php echo json_encode($demographics); ?>;
const ageGroups = [...new Set(demoData.map(d => d.age_group))];
const maleData = ageGroups.map(age => {
    const found = demoData.find(d => d.age_group === age && d.gender === 'male');
    return found ? found.count : 0;
});
const femaleData = ageGroups.map(age => {
    const found = demoData.find(d => d.age_group === age && d.gender === 'female');
    return found ? found.count : 0;
});

new Chart(document.getElementById('demographicsChart'), {
    type: 'bar',
    data: {
        labels: ageGroups.map(a => a.charAt(0).toUpperCase() + a.slice(1)),
        datasets: [{
            label: 'Male',
            data: maleData,
            backgroundColor: chartColors.primary
        }, {
            label: 'Female',
            data: femaleData,
            backgroundColor: chartColors.red
        }]
    },
    options: {
        responsive: true,
        scales: { 
            x: { stacked: true },
            y: { stacked: true, beginAtZero: true }
        }
    }
});

// Initialize DataTable
$(document).ready(function() {
    $('#followupTable').DataTable({
        order: [[1, 'desc']],
        pageLength: 25
    });
});

function exportReport(type) {
    ChurchCMS.showLoading('Generating report...');
    window.location.href = `export_visitors.php?type=${type}&start=${<?php echo json_encode($startDate); ?>}&end=${<?php echo json_encode($endDate); ?>}`;
    setTimeout(() => ChurchCMS.hideLoading(), 2000);
}

function scheduleFollowup(id) {
    ChurchCMS.showToast('Follow-up scheduled successfully!', 'success');
}

function sendFollowupSMS(id) {
    ChurchCMS.showToast('SMS sent successfully!', 'success');
}

function assignFollowUpTasks() {
    ChurchCMS.showConfirm(
        'Assign follow-up tasks for all visitors needing attention?',
        function() {
            ChurchCMS.showToast('Follow-up tasks assigned!', 'success');
        }
    );
}
</script>

<?php include '../../includes/footer.php'; ?>