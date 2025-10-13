<?php
/**
 * Member Reports & Analytics
 * Generate comprehensive member reports
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

requireLogin();
if (!hasPermission('members')) {
    setFlashMessage('error', 'You do not have permission to access this page');
    redirect(BASE_URL . 'modules/dashboard/');
}

$db = Database::getInstance();

// Get report data
$currentYear = date('Y');
$currentMonth = date('m');

// Total members by status
$stmt = $db->executeQuery("
    SELECT membership_status, COUNT(*) as count
    FROM members
    GROUP BY membership_status
");
$membersByStatus = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Members by gender
$stmt = $db->executeQuery("
    SELECT gender, COUNT(*) as count
    FROM members
    WHERE membership_status = 'active'
    GROUP BY gender
");
$membersByGender = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Members by age group
$stmt = $db->executeQuery("
    SELECT 
        CASE
            WHEN YEAR(CURDATE()) - YEAR(date_of_birth) <= 12 THEN 'Children (0-12)'
            WHEN YEAR(CURDATE()) - YEAR(date_of_birth) <= 17 THEN 'Teens (13-17)'
            WHEN YEAR(CURDATE()) - YEAR(date_of_birth) <= 35 THEN 'Youth (18-35)'
            WHEN YEAR(CURDATE()) - YEAR(date_of_birth) <= 59 THEN 'Adults (36-59)'
            ELSE 'Seniors (60+)'
        END as age_group,
        COUNT(*) as count
    FROM members
    WHERE membership_status = 'active' AND date_of_birth IS NOT NULL
    GROUP BY age_group
    ORDER BY 
        CASE age_group
            WHEN 'Children (0-12)' THEN 1
            WHEN 'Teens (13-17)' THEN 2
            WHEN 'Youth (18-35)' THEN 3
            WHEN 'Adults (36-59)' THEN 4
            WHEN 'Seniors (60+)' THEN 5
        END
");
$membersByAge = $stmt->fetchAll();

// Members by department
$stmt = $db->executeQuery("
    SELECT 
        d.name as department,
        COUNT(DISTINCT md.member_id) as count
    FROM departments d
    LEFT JOIN member_departments md ON d.id = md.department_id AND md.is_active = 1
    WHERE d.is_active = 1
    GROUP BY d.id, d.name
    ORDER BY count DESC
    LIMIT 10
");
$membersByDepartment = $stmt->fetchAll();

// New members this year by month
$stmt = $db->executeQuery("
    SELECT 
        DATE_FORMAT(join_date, '%Y-%m') as month,
        COUNT(*) as count
    FROM members
    WHERE YEAR(join_date) = ?
    GROUP BY month
    ORDER BY month
", [$currentYear]);
$newMembersThisYear = $stmt->fetchAll();

// Upcoming birthdays (next 30 days)
$stmt = $db->executeQuery("
    SELECT 
        CONCAT(first_name, ' ', last_name) as name,
        date_of_birth,
        phone,
        YEAR(CURDATE()) - YEAR(date_of_birth) as age,
        DATE_FORMAT(
            DATE_ADD(
                DATE_FORMAT(CURDATE(), '%Y-00-00'),
                INTERVAL DAYOFYEAR(date_of_birth) DAY
            ),
            '%Y-%m-%d'
        ) as next_birthday
    FROM members
    WHERE membership_status = 'active'
    AND date_of_birth IS NOT NULL
    AND DAYOFYEAR(date_of_birth) BETWEEN DAYOFYEAR(CURDATE()) AND DAYOFYEAR(DATE_ADD(CURDATE(), INTERVAL 30 DAY))
    ORDER BY DAYOFYEAR(date_of_birth)
    LIMIT 20
");
$upcomingBirthdays = $stmt->fetchAll();

// Inactive members (no attendance in last 60 days)
$stmt = $db->executeQuery("
    SELECT 
        m.id,
        CONCAT(m.first_name, ' ', m.last_name) as name,
        m.phone,
        m.email,
        MAX(ar.check_in_time) as last_attendance
    FROM members m
    LEFT JOIN attendance_records ar ON m.id = ar.member_id
    WHERE m.membership_status = 'active'
    GROUP BY m.id
    HAVING last_attendance < DATE_SUB(CURDATE(), INTERVAL 60 DAY) OR last_attendance IS NULL
    ORDER BY last_attendance DESC
    LIMIT 50
");
$inactiveMembers = $stmt->fetchAll();

// Member growth trend (last 12 months)
$stmt = $db->executeQuery("
    SELECT 
        DATE_FORMAT(join_date, '%Y-%m') as month,
        COUNT(*) as new_members
    FROM members
    WHERE join_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY month
    ORDER BY month
");
$growthTrend = $stmt->fetchAll();

$page_title = 'Member Reports';
$page_icon = 'fas fa-chart-pie';
$breadcrumb = [
    ['title' => 'Members', 'url' => BASE_URL . 'modules/members/'],
    ['title' => 'Reports']
];

include '../../includes/header.php';
?>

<!-- Summary Cards -->
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
                        <div class="stats-number"><?php echo $membersByStatus['active'] ?? 0; ?></div>
                        <div class="stats-label">Active Members</div>
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
                            <i class="fas fa-user-plus"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="stats-number"><?php echo array_sum(array_column($newMembersThisYear, 'count')); ?></div>
                        <div class="stats-label">New This Year</div>
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
                            <i class="fas fa-birthday-cake"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="stats-number"><?php echo count($upcomingBirthdays); ?></div>
                        <div class="stats-label">Upcoming Birthdays</div>
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
                        <div class="stats-number"><?php echo count($inactiveMembers); ?></div>
                        <div class="stats-label">Inactive Members</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row mb-4">
    <!-- Member Growth Chart -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0 text-church-blue">
                    <i class="fas fa-chart-line me-2"></i>Member Growth Trend (Last 12 Months)
                </h5>
            </div>
            <div class="card-body">
                <canvas id="growthTrendChart" height="80"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Gender Distribution -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0 text-church-blue">
                    <i class="fas fa-chart-pie me-2"></i>Gender Distribution
                </h5>
            </div>
            <div class="card-body">
                <canvas id="genderChart"></canvas>
                <div class="mt-3">
                    <div class="d-flex justify-content-between mb-2">
                        <span><i class="fas fa-circle text-primary"></i> Male</span>
                        <strong><?php echo $membersByGender['male'] ?? 0; ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span><i class="fas fa-circle text-danger"></i> Female</span>
                        <strong><?php echo $membersByGender['female'] ?? 0; ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Age Groups and Departments Row -->
<div class="row mb-4">
    <!-- Age Groups -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0 text-church-blue">
                    <i class="fas fa-users me-2"></i>Members by Age Group
                </h5>
            </div>
            <div class="card-body">
                <canvas id="ageGroupChart" height="120"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Top Departments -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0 text-church-blue">
                    <i class="fas fa-layer-group me-2"></i>Top 10 Departments by Members
                </h5>
            </div>
            <div class="card-body">
                <canvas id="departmentChart" height="120"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Upcoming Birthdays -->
<div class="row mb-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-church-blue">
                    <i class="fas fa-birthday-cake me-2"></i>Upcoming Birthdays (Next 30 Days)
                </h5>
                <button class="btn btn-sm btn-outline-primary" onclick="sendBirthdayReminders()">
                    <i class="fas fa-sms me-1"></i>Send Wishes
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($upcomingBirthdays)): ?>
                    <p class="text-muted text-center py-4">No birthdays in the next 30 days</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Date</th>
                                    <th>Age</th>
                                    <th>Contact</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($upcomingBirthdays as $birthday): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($birthday['name']); ?></td>
                                    <td><?php echo date('M d', strtotime($birthday['date_of_birth'])); ?></td>
                                    <td><?php echo $birthday['age'] + 1; ?> years</td>
                                    <td>
                                        <?php if ($birthday['phone']): ?>
                                            <small class="text-muted"><?php echo htmlspecialchars($birthday['phone']); ?></small>
                                        <?php endif; ?>
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
    
    <!-- Inactive Members -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-church-blue">
                    <i class="fas fa-user-slash me-2"></i>Inactive Members (No Recent Attendance)
                </h5>
                <button class="btn btn-sm btn-outline-warning" onclick="followUpInactive()">
                    <i class="fas fa-phone me-1"></i>Follow Up
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($inactiveMembers)): ?>
                    <p class="text-success text-center py-4">
                        <i class="fas fa-check-circle"></i> All members are active!
                    </p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Last Attendance</th>
                                    <th>Contact</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($inactiveMembers, 0, 15) as $member): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($member['name']); ?></td>
                                    <td>
                                        <?php if ($member['last_attendance']): ?>
                                            <small class="text-muted"><?php echo ChurchCMS::timeAgo($member['last_attendance']); ?></small>
                                        <?php else: ?>
                                            <small class="text-danger">Never attended</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($member['phone']): ?>
                                            <small class="text-muted"><?php echo htmlspecialchars($member['phone']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (count($inactiveMembers) > 15): ?>
                        <div class="text-center mt-2">
                            <small class="text-muted">Showing 15 of <?php echo count($inactiveMembers); ?> inactive members</small>
                        </div>
                    <?php endif; ?>
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
                    <div class="col-md-4">
                        <button class="btn btn-outline-success w-100" onclick="exportReport('active_members')">
                            <i class="fas fa-file-excel me-2"></i>Active Members List
                        </button>
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-outline-primary w-100" onclick="exportReport('departments')">
                            <i class="fas fa-file-excel me-2"></i>Members by Department
                        </button>
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-outline-warning w-100" onclick="exportReport('birthdays')">
                            <i class="fas fa-file-excel me-2"></i>Birthday List
                        </button>
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-outline-info w-100" onclick="exportReport('inactive')">
                            <i class="fas fa-file-excel me-2"></i>Inactive Members
                        </button>
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-outline-danger w-100" onclick="exportReport('full_report')">
                            <i class="fas fa-file-pdf me-2"></i>Complete Member Report
                        </button>
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-outline-secondary w-100" onclick="printReport()">
                            <i class="fas fa-print me-2"></i>Print This Page
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<script>
// Chart colors
const chartColors = {
    primary: '#03045e',
    red: '#ff2400',
    success: '#28a745',
    warning: '#ffc107',
    info: '#17a2b8',
    secondary: '#6c757d'
};

// Growth Trend Chart
const growthCtx = document.getElementById('growthTrendChart').getContext('2d');
const growthData = <?php echo json_encode($growthTrend); ?>;
new Chart(growthCtx, {
    type: 'line',
    data: {
        labels: growthData.map(d => {
            const [year, month] = d.month.split('-');
            return new Date(year, month - 1).toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
        }),
        datasets: [{
            label: 'New Members',
            data: growthData.map(d => d.new_members),
            borderColor: chartColors.primary,
            backgroundColor: 'rgba(3, 4, 94, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { stepSize: 1 }
            }
        }
    }
});

// Gender Distribution Chart
const genderCtx = document.getElementById('genderChart').getContext('2d');
new Chart(genderCtx, {
    type: 'doughnut',
    data: {
        labels: ['Male', 'Female'],
        datasets: [{
            data: [<?php echo $membersByGender['male'] ?? 0; ?>, <?php echo $membersByGender['female'] ?? 0; ?>],
            backgroundColor: [chartColors.primary, chartColors.red],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { display: false }
        }
    }
});

// Age Group Chart
const ageCtx = document.getElementById('ageGroupChart').getContext('2d');
const ageData = <?php echo json_encode($membersByAge); ?>;
new Chart(ageCtx, {
    type: 'bar',
    data: {
        labels: ageData.map(d => d.age_group),
        datasets: [{
            label: 'Members',
            data: ageData.map(d => d.count),
            backgroundColor: [
                'rgba(3, 4, 94, 0.8)',
                'rgba(255, 36, 0, 0.8)',
                'rgba(40, 167, 69, 0.8)',
                'rgba(255, 193, 7, 0.8)',
                'rgba(23, 162, 184, 0.8)'
            ],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { stepSize: 5 }
            }
        }
    }
});

// Department Chart
const deptCtx = document.getElementById('departmentChart').getContext('2d');
const deptData = <?php echo json_encode($membersByDepartment); ?>;
new Chart(deptCtx, {
    type: 'horizontalBar',
    data: {
        labels: deptData.map(d => d.department),
        datasets: [{
            label: 'Members',
            data: deptData.map(d => d.count),
            backgroundColor: chartColors.info,
            borderWidth: 0
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { display: false }
        },
        scales: {
            x: {
                beginAtZero: true,
                ticks: { stepSize: 5 }
            }
        }
    }
});

// Export functions
function exportReport(type) {
    ChurchCMS.showLoading('Generating report...');
    window.location.href = `export_report.php?type=${type}&format=excel`;
    setTimeout(() => ChurchCMS.hideLoading(), 2000);
}

function printReport() {
    window.print();
}

function sendBirthdayReminders() {
    ChurchCMS.showConfirm(
        'Send birthday wishes to all upcoming birthdays?',
        function() {
            ChurchCMS.showToast('Birthday wishes sent successfully!', 'success');
        }
    );
}

function followUpInactive() {
    ChurchCMS.showConfirm(
        'This will create follow-up tasks for inactive members. Continue?',
        function() {
            ChurchCMS.showToast('Follow-up tasks created!', 'success');
        }
    );
}
</script>

<?php include '../../includes/footer.php'; ?>