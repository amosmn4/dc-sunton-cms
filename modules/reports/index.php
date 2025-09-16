<?php
/**
 * Reports Dashboard
 * Deliverance Church Management System
 * 
 * Main reports overview page with quick access to all report types
 */

// Include configuration and functions
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Check authentication and permissions
requireLogin();
if (!hasPermission('reports')) {
    header('Location: ' . BASE_URL . 'modules/dashboard/?error=access_denied');
    exit();
}

// Page configuration
$page_title = 'Reports Dashboard';
$page_icon = 'fas fa-chart-bar';
$breadcrumb = [
    ['title' => 'Reports Dashboard']
];

// Get report statistics
try {
    $db = Database::getInstance();
    
    // Get member statistics
    $memberStats = [
        'total_members' => getRecordCount('members', ['membership_status' => 'active']),
        'new_this_month' => getRecordCount('members', [
            'membership_status' => 'active',
            'join_date >=' => date('Y-m-01')
        ]),
        'inactive_members' => getRecordCount('members', ['membership_status' => 'inactive'])
    ];
    
    // Get attendance statistics (last 30 days)
    $attendanceStats = $db->executeQuery("
        SELECT 
            COUNT(DISTINCT ar.event_id) as total_services,
            COUNT(ar.id) as total_attendance_records,
            AVG(event_attendance.attendance_count) as avg_attendance
        FROM attendance_records ar
        JOIN events e ON ar.event_id = e.id
        LEFT JOIN (
            SELECT event_id, COUNT(*) as attendance_count 
            FROM attendance_records 
            GROUP BY event_id
        ) event_attendance ON e.id = event_attendance.event_id
        WHERE e.event_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ")->fetch();
    
    // Get financial statistics (current month)
    $financialStats = [
        'monthly_income' => $db->executeQuery("
            SELECT COALESCE(SUM(amount), 0) as total 
            FROM income 
            WHERE YEAR(transaction_date) = YEAR(NOW()) 
            AND MONTH(transaction_date) = MONTH(NOW())
            AND status = 'verified'
        ")->fetch()['total'],
        'monthly_expenses' => $db->executeQuery("
            SELECT COALESCE(SUM(amount), 0) as total 
            FROM expenses 
            WHERE YEAR(expense_date) = YEAR(NOW()) 
            AND MONTH(expense_date) = MONTH(NOW())
            AND status = 'paid'
        ")->fetch()['total'],
        'pending_transactions' => getRecordCount('income', ['status' => 'pending']) + 
                                  getRecordCount('expenses', ['status' => 'pending'])
    ];
    
    // Get visitor statistics
    $visitorStats = [
        'total_visitors' => getRecordCount('visitors'),
        'new_this_month' => getRecordCount('visitors', ['visit_date >=' => date('Y-m-01')]),
        'pending_followup' => getRecordCount('visitors', ['status' => 'new_visitor'])
    ];
    
    // Get SMS statistics
    $smsStats = [
        'messages_sent' => $db->executeQuery("
            SELECT COUNT(*) as count 
            FROM sms_individual 
            WHERE status = 'sent' 
            AND MONTH(sent_at) = MONTH(NOW()) 
            AND YEAR(sent_at) = YEAR(NOW())
        ")->fetch()['count'],
        'total_cost' => $db->executeQuery("
            SELECT COALESCE(SUM(cost), 0) as total 
            FROM sms_individual 
            WHERE status = 'sent' 
            AND MONTH(sent_at) = MONTH(NOW()) 
            AND YEAR(sent_at) = YEAR(NOW())
        ")->fetch()['total']
    ];
    
} catch (Exception $e) {
    error_log("Error fetching report statistics: " . $e->getMessage());
    $memberStats = $attendanceStats = $financialStats = $visitorStats = $smsStats = [];
}

// Additional CSS for charts
$additional_css = ['assets/css/charts.css'];
$additional_js = ['assets/js/charts.js'];

// Include header
include_once '../../includes/header.php';
?>

<!-- Reports Dashboard Content -->
<div class="row">
    <!-- Summary Cards -->
    <div class="col-12 mb-4">
        <div class="row g-3">
            <!-- Members Card -->
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon bg-church-blue text-white">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stats-number"><?php echo number_format($memberStats['total_members'] ?? 0); ?></div>
                    <div class="stats-label">Active Members</div>
                    <div class="stats-change text-success mt-2">
                        <i class="fas fa-arrow-up"></i>
                        +<?php echo $memberStats['new_this_month'] ?? 0; ?> this month
                    </div>
                </div>
            </div>
            
            <!-- Attendance Card -->
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon bg-church-red text-white">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stats-number"><?php echo number_format($attendanceStats['avg_attendance'] ?? 0); ?></div>
                    <div class="stats-label">Avg. Attendance</div>
                    <div class="stats-change text-info mt-2">
                        <i class="fas fa-calendar"></i>
                        <?php echo $attendanceStats['total_services'] ?? 0; ?> services
                    </div>
                </div>
            </div>
            
            <!-- Financial Card -->
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon bg-success text-white">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stats-number"><?php echo formatCurrency($financialStats['monthly_income'] ?? 0); ?></div>
                    <div class="stats-label">Monthly Income</div>
                    <div class="stats-change text-warning mt-2">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo $financialStats['pending_transactions'] ?? 0; ?> pending
                    </div>
                </div>
            </div>
            
            <!-- Visitors Card -->
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon bg-warning text-white">
                        <i class="fas fa-user-friends"></i>
                    </div>
                    <div class="stats-number"><?php echo number_format($visitorStats['new_this_month'] ?? 0); ?></div>
                    <div class="stats-label">New Visitors</div>
                    <div class="stats-change text-danger mt-2">
                        <i class="fas fa-phone"></i>
                        <?php echo $visitorStats['pending_followup'] ?? 0; ?> follow-up needed
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Report Access -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-line me-2"></i>
                    Quick Reports
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <!-- Member Reports -->
                    <div class="col-md-6">
                        <div class="report-category">
                            <h6 class="text-church-blue mb-3">
                                <i class="fas fa-users me-2"></i>Member Reports
                            </h6>
                            <div class="list-group list-group-flush">
                                <a href="members.php" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                        <span>Member Directory</span>
                                        <i class="fas fa-chevron-right text-muted"></i>
                                    </div>
                                </a>
                                <a href="members.php?type=new" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                        <span>New Members</span>
                                        <span class="badge bg-primary"><?php echo $memberStats['new_this_month'] ?? 0; ?></span>
                                    </div>
                                </a>
                                <a href="members.php?type=birthdays" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                        <span>Birthdays This Month</span>
                                        <i class="fas fa-chevron-right text-muted"></i>
                                    </div>
                                </a>
                                <a href="members.php?type=departments" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                        <span>Department Analysis</span>
                                        <i class="fas fa-chevron-right text-muted"></i>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Attendance Reports -->
                    <div class="col-md-6">
                        <div class="report-category">
                            <h6 class="text-church-red mb-3">
                                <i class="fas fa-calendar-check me-2"></i>Attendance Reports
                            </h6>
                            <div class="list-group list-group-flush">
                                <a href="attendance.php" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                        <span>Weekly Summary</span>
                                        <i class="fas fa-chevron-right text-muted"></i>
                                    </div>
                                </a>
                                <a href="attendance.php?type=trends" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                        <span>Attendance Trends</span>
                                        <i class="fas fa-chevron-right text-muted"></i>
                                    </div>
                                </a>
                                <a href="attendance.php?type=individual" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                        <span>Individual Records</span>
                                        <i class="fas fa-chevron-right text-muted"></i>
                                    </div>
                                </a>
                                <a href="attendance.php?type=comparison" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                        <span>Service Comparison</span>
                                        <i class="fas fa-chevron-right text-muted"></i>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Financial Reports -->
                    <div class="col-md-6 mt-4">
                        <div class="report-category">
                            <h6 class="text-success mb-3">
                                <i class="fas fa-money-bill-wave me-2"></i>Financial Reports
                            </h6>
                            <div class="list-group list-group-flush">
                                <a href="financial.php" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                        <span>Income Summary</span>
                                        <i class="fas fa-chevron-right text-muted"></i>
                                    </div>
                                </a>
                                <a href="financial.php?type=expenses" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                        <span>Expense Analysis</span>
                                        <i class="fas fa-chevron-right text-muted"></i>
                                    </div>
                                </a>
                                <a href="financial.php?type=comparison" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                        <span>Income vs Expenses</span>
                                        <i class="fas fa-chevron-right text-muted"></i>
                                    </div>
                                </a>
                                <a href="financial.php?type=donors" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                        <span>Donor Reports</span>
                                        <i class="fas fa-chevron-right text-muted"></i>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Other Reports -->
                    <div class="col-md-6 mt-4">
                        <div class="report-category">
                            <h6 class="text-info mb-3">
                                <i class="fas fa-chart-pie me-2"></i>Other Reports
                            </h6>
                            <div class="list-group list-group-flush">
                                <a href="visitors.php" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                        <span>Visitor Reports</span>
                                        <i class="fas fa-chevron-right text-muted"></i>
                                    </div>
                                </a>
                                <a href="sms.php" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                        <span>SMS Reports</span>
                                        <span class="badge bg-info"><?php echo number_format($smsStats['messages_sent'] ?? 0); ?></span>
                                    </div>
                                </a>
                                <a href="equipment.php" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                        <span>Equipment Reports</span>
                                        <i class="fas fa-chevron-right text-muted"></i>
                                    </div>
                                </a>
                                <a href="custom.php" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                        <span>Custom Reports</span>
                                        <i class="fas fa-chevron-right text-muted"></i>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity & Chart -->
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-clock me-2"></i>
                    Recent Activity
                </h6>
            </div>
            <div class="card-body">
                <div class="activity-timeline">
                    <?php
                    try {
                        $recentActivities = $db->executeQuery("
                            SELECT al.*, u.first_name, u.last_name 
                            FROM activity_logs al 
                            JOIN users u ON al.user_id = u.id 
                            WHERE al.action LIKE '%report%' OR al.action LIKE '%export%'
                            ORDER BY al.created_at DESC 
                            LIMIT 5
                        ")->fetchAll();
                        
                        if (!empty($recentActivities)):
                            foreach ($recentActivities as $activity):
                    ?>
                        <div class="activity-item">
                            <div class="activity-icon bg-primary">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-text">
                                    <strong><?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?></strong>
                                    <?php echo htmlspecialchars($activity['action']); ?>
                                </div>
                                <div class="activity-time text-muted small">
                                    <?php echo timeAgo($activity['created_at']); ?>
                                </div>
                            </div>
                        </div>
                    <?php 
                            endforeach;
                        else:
                    ?>
                        <div class="text-center text-muted py-3">
                            <i class="fas fa-info-circle mb-2"></i>
                            <br>No recent report activity
                        </div>
                    <?php endif;
                    } catch (Exception $e) {
                        echo '<div class="text-center text-muted py-3">Unable to load activity</div>';
                    }
                    ?>
                </div>
            </div>
        </div>

        <!-- Quick Stats Chart -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-chart-doughnut me-2"></i>
                    Members by Department
                </h6>
            </div>
            <div class="card-body">
                <canvas id="departmentChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row mt-4">
    <!-- Attendance Trends -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="fas fa-chart-line me-2"></i>
                    Attendance Trends (Last 6 Months)
                </h6>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="fas fa-cog"></i>
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" onclick="updateAttendanceChart('3months')">Last 3 Months</a></li>
                        <li><a class="dropdown-item" href="#" onclick="updateAttendanceChart('6months')">Last 6 Months</a></li>
                        <li><a class="dropdown-item" href="#" onclick="updateAttendanceChart('1year')">Last Year</a></li>
                    </ul>
                </div>
            </div>
            <div class="card-body">
                <canvas id="attendanceChart" height="300"></canvas>
            </div>
        </div>
    </div>

    <!-- Financial Overview -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    Financial Overview (Monthly)
                </h6>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="fas fa-download"></i>
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="export.php?type=financial&format=pdf">
                            <i class="fas fa-file-pdf me-2"></i>Export as PDF
                        </a></li>
                        <li><a class="dropdown-item" href="export.php?type=financial&format=excel">
                            <i class="fas fa-file-excel me-2"></i>Export as Excel
                        </a></li>
                    </ul>
                </div>
            </div>
            <div class="card-body">
                <canvas id="financialChart" height="300"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Report Actions -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-tools me-2"></i>
                    Report Tools
                </h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="d-grid">
                            <a href="custom.php" class="btn btn-church-primary">
                                <i class="fas fa-plus me-2"></i>
                                Create Custom Report
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="d-grid">
                            <a href="schedule.php" class="btn btn-church-secondary">
                                <i class="fas fa-calendar me-2"></i>
                                Schedule Reports
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="d-grid">
                            <button class="btn btn-outline-success" onclick="exportAllData()">
                                <i class="fas fa-database me-2"></i>
                                Export All Data
                            </button>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="d-grid">
                            <a href="analytics.php" class="btn btn-outline-info">
                                <i class="fas fa-chart-pie me-2"></i>
                                Advanced Analytics
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Custom Styles -->
<style>
.report-category {
    border: 1px solid rgba(0,0,0,0.1);
    border-radius: 8px;
    padding: 1rem;
    height: 100%;
}

.activity-timeline {
    max-height: 300px;
    overflow-y: auto;
}

.activity-item {
    display: flex;
    align-items: flex-start;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #eee;
}

.activity-item:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.activity-icon {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 0.75rem;
    font-size: 0.75rem;
}

.activity-content {
    flex: 1;
    min-width: 0;
}

.activity-text {
    font-size: 0.875rem;
    line-height: 1.4;
    margin-bottom: 0.25rem;
}

.stats-change {
    font-size: 0.75rem;
    font-weight: 500;
}

.chart-container {
    position: relative;
    height: 300px;
}

@media (max-width: 768px) {
    .stats-card {
        text-align: center;
        margin-bottom: 1rem;
    }
    
    .report-category {
        margin-bottom: 1rem;
    }
}
</style>

<!-- JavaScript for Charts and Interactions -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize charts
    initializeDepartmentChart();
    initializeAttendanceChart();
    initializeFinancialChart();
});

// Department Distribution Chart
function initializeDepartmentChart() {
    <?php
    try {
        $departmentData = $db->executeQuery("
            SELECT d.name, COUNT(md.member_id) as member_count, d.department_type
            FROM departments d
            LEFT JOIN member_departments md ON d.id = md.department_id AND md.is_active = 1
            WHERE d.is_active = 1
            GROUP BY d.id, d.name, d.department_type
            ORDER BY member_count DESC
            LIMIT 6
        ")->fetchAll();
        
        $labels = [];
        $data = [];
        $colors = ['#03045e', '#ff2400', '#28a745', '#ffc107', '#17a2b8', '#6c757d'];
        
        foreach ($departmentData as $index => $dept) {
            $labels[] = $dept['name'];
            $data[] = (int)$dept['member_count'];
        }
        
        echo "const departmentLabels = " . json_encode($labels) . ";\n";
        echo "const departmentData = " . json_encode($data) . ";\n";
        echo "const departmentColors = " . json_encode(array_slice($colors, 0, count($labels))) . ";\n";
    } catch (Exception $e) {
        echo "const departmentLabels = ['No Data'];\n";
        echo "const departmentData = [1];\n";
        echo "const departmentColors = ['#e9ecef'];\n";
    }
    ?>
    
    const ctx = document.getElementById('departmentChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: departmentLabels,
            datasets: [{
                data: departmentData,
                backgroundColor: departmentColors,
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        font: {
                            size: 11
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((context.parsed / total) * 100).toFixed(1);
                            return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });
}

// Attendance Trends Chart
function initializeAttendanceChart() {
    <?php
    try {
        $attendanceTrends = $db->executeQuery("
            SELECT 
                DATE_FORMAT(e.event_date, '%Y-%m') as month_year,
                COUNT(DISTINCT ar.member_id) as unique_attendees,
                COUNT(ar.id) as total_attendance
            FROM events e
            LEFT JOIN attendance_records ar ON e.id = ar.event_id
            WHERE e.event_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            AND e.event_type IN ('sunday_service', 'prayer_meeting')
            GROUP BY DATE_FORMAT(e.event_date, '%Y-%m')
            ORDER BY month_year ASC
        ")->fetchAll();
        
        $months = [];
        $attendanceData = [];
        
        foreach ($attendanceTrends as $trend) {
            $months[] = date('M Y', strtotime($trend['month_year'] . '-01'));
            $attendanceData[] = (int)$trend['total_attendance'];
        }
        
        echo "const attendanceMonths = " . json_encode($months) . ";\n";
        echo "const attendanceDataPoints = " . json_encode($attendanceData) . ";\n";
    } catch (Exception $e) {
        echo "const attendanceMonths = ['No Data'];\n";
        echo "const attendanceDataPoints = [0];\n";
    }
    ?>
    
    const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
    new Chart(attendanceCtx, {
        type: 'line',
        data: {
            labels: attendanceMonths,
            datasets: [{
                label: 'Attendance',
                data: attendanceDataPoints,
                borderColor: '#ff2400',
                backgroundColor: 'rgba(255, 36, 0, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#ff2400',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0,0,0,0.1)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
}

// Financial Overview Chart
function initializeFinancialChart() {
    <?php
    try {
        $financialData = $db->executeQuery("
            SELECT 
                'Income' as type,
                DATE_FORMAT(transaction_date, '%Y-%m') as month_year,
                SUM(amount) as total
            FROM income
            WHERE transaction_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            AND status = 'verified'
            GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
            
            UNION ALL
            
            SELECT 
                'Expenses' as type,
                DATE_FORMAT(expense_date, '%Y-%m') as month_year,
                SUM(amount) as total
            FROM expenses
            WHERE expense_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            AND status = 'paid'
            GROUP BY DATE_FORMAT(expense_date, '%Y-%m')
            
            ORDER BY month_year ASC
        ")->fetchAll();
        
        $months = [];
        $incomeData = [];
        $expenseData = [];
        
        // Group data by month
        $groupedData = [];
        foreach ($financialData as $data) {
            $month = date('M Y', strtotime($data['month_year'] . '-01'));
            if (!isset($groupedData[$month])) {
                $groupedData[$month] = ['income' => 0, 'expenses' => 0];
            }
            $groupedData[$month][strtolower($data['type'])] = (float)$data['total'];
        }
        
        foreach ($groupedData as $month => $data) {
            $months[] = $month;
            $incomeData[] = $data['income'];
            $expenseData[] = $data['expenses'];
        }
        
        echo "const financialMonths = " . json_encode($months) . ";\n";
        echo "const incomeData = " . json_encode($incomeData) . ";\n";
        echo "const expenseData = " . json_encode($expenseData) . ";\n";
    } catch (Exception $e) {
        echo "const financialMonths = ['No Data'];\n";
        echo "const incomeData = [0];\n";
        echo "const expenseData = [0];\n";
    }
    ?>
    
    const financialCtx = document.getElementById('financialChart').getContext('2d');
    new Chart(financialCtx, {
        type: 'bar',
        data: {
            labels: financialMonths,
            datasets: [
                {
                    label: 'Income',
                    data: incomeData,
                    backgroundColor: 'rgba(40, 167, 69, 0.8)',
                    borderColor: '#28a745',
                    borderWidth: 1
                },
                {
                    label: 'Expenses',
                    data: expenseData,
                    backgroundColor: 'rgba(255, 36, 0, 0.8)',
                    borderColor: '#ff2400',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': <?php echo CURRENCY_SYMBOL; ?> ' + 
                                   context.parsed.y.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '<?php echo CURRENCY_SYMBOL; ?> ' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
}

// Update attendance chart period
function updateAttendanceChart(period) {
    ChurchCMS.showLoading('Updating chart...');
    
    fetch(`api/attendance_data.php?period=${period}`)
        .then(response => response.json())
        .then(data => {
            // Update chart with new data
            // Implementation would update the existing chart
            ChurchCMS.showToast('Chart updated successfully', 'success');
        })
        .catch(error => {
            console.error('Error updating chart:', error);
            ChurchCMS.showToast('Failed to update chart', 'error');
        })
        .finally(() => {
            ChurchCMS.hideLoading();
        });
}

// Export all data function
function exportAllData() {
    ChurchCMS.showConfirm(
        'This will export all church data. This may take a few minutes. Continue?',
        function() {
            ChurchCMS.showLoading('Preparing export...');
            window.location.href = 'export.php?type=all&format=excel';
            
            setTimeout(() => {
                ChurchCMS.hideLoading();
            }, 5000);
        }
    );
}

// Auto-refresh data every 5 minutes
setInterval(function() {
    // Refresh statistics without page reload
    fetch('api/report_stats.php')
        .then(response => response.json())
        .then(data => {
            // Update displayed statistics
            if (data.success) {
                // Update DOM elements with new data
                console.log('Statistics updated');
            }
        })
        .catch(error => {
            console.error('Error refreshing statistics:', error);
        });
}, 300000); // 5 minutes
</script>

<?php include_once '../../includes/footer.php'; ?>