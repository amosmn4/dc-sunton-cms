'previous' => [
            'new_members' => $db->executeQuery("SELECT COUNT(*) as count FROM members WHERE join_date BETWEEN ? AND ?", [$prevDateFrom, $prevDateTo])->fetch()['count']
        ]
    ];

    // FINANCIAL OVERVIEW
    $financialData = [
        'current' => [
            'total_income' => $db->executeQuery("
                SELECT COALESCE(SUM(amount), 0) as total 
                FROM income 
                WHERE transaction_date BETWEEN ? AND ? AND status = 'verified'
            ", [$dateFrom, $dateTo])->fetch()['total'],
            'total_expenses' => $db->executeQuery("
                SELECT COALESCE(SUM(amount), 0) as total 
                FROM expenses 
                WHERE expense_date BETWEEN ? AND ? AND status = 'paid'
            ", [$dateFrom, $dateTo])->fetch()['total'],
            'tithes_offerings' => $db->executeQuery("
                SELECT COALESCE(SUM(i.amount), 0) as total 
                FROM income i 
                JOIN income_categories ic ON i.category_id = ic.id 
                WHERE i.transaction_date BETWEEN ? AND ? 
                AND i.status = 'verified' 
                AND ic.name IN ('Tithes', 'Offerings')
            ", [$dateFrom, $dateTo])->fetch()['total']
        ],
        'previous' => [
            'total_income' => $db->executeQuery("
                SELECT COALESCE(SUM(amount), 0) as total 
                FROM income 
                WHERE transaction_date BETWEEN ? AND ? AND status = 'verified'
            ", [$prevDateFrom, $prevDateTo])->fetch()['total'],
            'total_expenses' => $db->executeQuery("
                SELECT COALESCE(SUM(amount), 0) as total 
                FROM expenses 
                WHERE expense_date BETWEEN ? AND ? AND status = 'paid'
            ", [$prevDateFrom, $prevDateTo])->fetch()['total']
        ]
    ];

    // Calculate net income
    $financialData['current']['net_income'] = $financialData['current']['total_income'] - $financialData['current']['total_expenses'];
    $financialData['previous']['net_income'] = $financialData['previous']['total_income'] - $financialData['previous']['total_expenses'];

    // ATTENDANCE OVERVIEW
    $attendanceData = [
        'current' => [
            'total_events' => $db->executeQuery("
                SELECT COUNT(*) as count 
                FROM events 
                WHERE event_date BETWEEN ? AND ?
            ", [$dateFrom, $dateTo])->fetch()['count'],
            'total_attendance' => $db->executeQuery("
                SELECT COUNT(*) as count 
                FROM attendance_records ar 
                JOIN events e ON ar.event_id = e.id 
                WHERE e.event_date BETWEEN ? AND ?
            ", [$dateFrom, $dateTo])->fetch()['count'],
            'average_attendance' => $db->executeQuery("
                SELECT ROUND(AVG(attendance_count), 0) as avg_attendance
                FROM (
                    SELECT COUNT(ar.id) as attendance_count
                    FROM events e
                    LEFT JOIN attendance_records ar ON e.id = ar.event_id
                    WHERE e.event_date BETWEEN ? AND ?
                    AND e.status = 'completed'
                    GROUP BY e.id
                ) event_attendance
            ", [$dateFrom, $dateTo])->fetch()['avg_attendance']
        ],
        'previous' => [
            'total_attendance' => $db->executeQuery("
                SELECT COUNT(*) as count 
                FROM attendance_records ar 
                JOIN events e ON ar.event_id = e.id 
                WHERE e.event_date BETWEEN ? AND ?
            ", [$prevDateFrom, $prevDateTo])->fetch()['count']
        ]
    ];

    // VISITOR OVERVIEW
    $visitorData = [
        'current' => [
            'new_visitors' => $db->executeQuery("
                SELECT COUNT(*) as count 
                FROM visitors 
                WHERE visit_date BETWEEN ? AND ?
            ", [$dateFrom, $dateTo])->fetch()['count'],
            'returning_visitors' => $db->executeQuery("
                SELECT COUNT(*) as count 
                FROM visitors 
                WHERE visit_date BETWEEN ? AND ? 
                AND status = 'regular_attender'
            ", [$dateFrom, $dateTo])->fetch()['count'],
            'converted_members' => $db->executeQuery("
                SELECT COUNT(*) as count 
                FROM visitors 
                WHERE visit_date BETWEEN ? AND ? 
                AND status = 'converted_member'
            ", [$dateFrom, $dateTo])->fetch()['count']
        ],
        'previous' => [
            'new_visitors' => $db->executeQuery("
                SELECT COUNT(*) as count 
                FROM visitors 
                WHERE visit_date BETWEEN ? AND ?
            ", [$prevDateFrom, $prevDateTo])->fetch()['count']
        ]
    ];

    // SMS OVERVIEW
    $smsData = [
        'current' => [
            'messages_sent' => $db->executeQuery("
                SELECT COUNT(*) as count 
                FROM sms_individual 
                WHERE status = 'sent' AND sent_at BETWEEN ? AND ?
            ", [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])->fetch()['count'],
            'total_cost' => $db->executeQuery("
                SELECT COALESCE(SUM(cost), 0) as total 
                FROM sms_individual 
                WHERE status = 'sent' AND sent_at BETWEEN ? AND ?
            ", [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])->fetch()['total'],
            'delivery_rate' => $db->executeQuery("
                SELECT ROUND((SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as rate
                FROM sms_individual 
                WHERE sent_at BETWEEN ? AND ?
            ", [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])->fetch()['rate']
        ]
    ];

    // DEPARTMENT PERFORMANCE
    $departmentData = $db->executeQuery("
        SELECT 
            d.name as department_name,
            COUNT(DISTINCT md.member_id) as member_count,
            COUNT(DISTINCT e.id) as events_held,
            AVG(attendance_counts.attendance_count) as avg_event_attendance
        FROM departments d
        LEFT JOIN member_departments md ON d.id = md.department_id AND md.is_active = 1
        LEFT JOIN events e ON d.id = e.department_id AND e.event_date BETWEEN ? AND ?
        LEFT JOIN (
            SELECT event_id, COUNT(*) as attendance_count
            FROM attendance_records
            GROUP BY event_id
        ) attendance_counts ON e.id = attendance_counts.event_id
        WHERE d.is_active = 1
        GROUP BY d.id, d.name
        ORDER BY member_count DESC
        LIMIT 10
    ", [$dateFrom, $dateTo])->fetchAll();

    // TOP INCOME CATEGORIES
    $topIncomeCategories = $db->executeQuery("
        SELECT 
            ic.name as category_name,
            SUM(i.amount) as total_amount,
            COUNT(i.id) as transaction_count
        FROM income i
        JOIN income_categories ic ON i.category_id = ic.id
        WHERE i.transaction_date BETWEEN ? AND ?
        AND i.status = 'verified'
        GROUP BY ic.id, ic.name
        ORDER BY total_amount DESC
        LIMIT 5
    ", [$dateFrom, $dateTo])->fetchAll();

    // TOP EXPENSE CATEGORIES
    $topExpenseCategories = $db->executeQuery("
        SELECT 
            ec.name as category_name,
            SUM(e.amount) as total_amount,
            COUNT(e.id) as transaction_count
        FROM expenses e
        JOIN expense_categories ec ON e.category_id = ec.id
        WHERE e.expense_date BETWEEN ? AND ?
        AND e.status = 'paid'
        GROUP BY ec.id, ec.name
        ORDER BY total_amount DESC
        LIMIT 5
    ", [$dateFrom, $dateTo])->fetchAll();

    // GROWTH TRENDS (Last 6 months)
    $growthTrends = $db->executeQuery("
        SELECT 
            DATE_FORMAT(month_date, '%Y-%m') as month,
            DATE_FORMAT(month_date, '%M %Y') as month_name,
            COALESCE(member_growth.new_members, 0) as new_members,
            COALESCE(income_data.total_income, 0) as monthly_income,
            COALESCE(attendance_data.total_attendance, 0) as monthly_attendance,
            COALESCE(visitor_data.new_visitors, 0) as new_visitors
        FROM (
            SELECT DATE_FORMAT(CURDATE() - INTERVAL n MONTH, '%Y-%m-01') as month_date
            FROM (SELECT 0 as n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5) months
        ) calendar
        LEFT JOIN (
            SELECT 
                DATE_FORMAT(join_date, '%Y-%m-01') as month_date,
                COUNT(*) as new_members
            FROM members
            WHERE join_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(join_date, '%Y-%m-01')
        ) member_growth ON calendar.month_date = member_growth.month_date
        LEFT JOIN (
            SELECT 
                DATE_FORMAT(transaction_date, '%Y-%m-01') as month_date,
                SUM(amount) as total_income
            FROM income
            WHERE transaction_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            AND status = 'verified'
            GROUP BY DATE_FORMAT(transaction_date, '%Y-%m-01')
        ) income_data ON calendar.month_date = income_data.month_date
        LEFT JOIN (
            SELECT 
                DATE_FORMAT(e.event_date, '%Y-%m-01') as month_date,
                COUNT(ar.id) as total_attendance
            FROM events e
            JOIN attendance_records ar ON e.id = ar.event_id
            WHERE e.event_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(e.event_date, '%Y-%m-01')
        ) attendance_data ON calendar.month_date = attendance_data.month_date
        LEFT JOIN (
            SELECT 
                DATE_FORMAT(visit_date, '%Y-%m-01') as month_date,
                COUNT(*) as new_visitors
            FROM visitors
            WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(visit_date, '%Y-%m-01')
        ) visitor_data ON calendar.month_date = visitor_data.month_date
        ORDER BY month ASC
    ")->fetchAll();

    // KEY PERFORMANCE INDICATORS
    $kpis = [
        'member_growth_rate' => calculateGrowthRate($membershipData['current']['new_members'], $membershipData['previous']['new_members']),
        'income_growth_rate' => calculateGrowthRate($financialData['current']['total_income'], $financialData['previous']['total_income']),
        'attendance_growth_rate' => calculateGrowthRate($attendanceData['current']['total_attendance'], $attendanceData['previous']['total_attendance']),
        'visitor_growth_rate' => calculateGrowthRate($visitorData['current']['new_visitors'], $visitorData['previous']['new_visitors']),
        'giving_per_member' => $membershipData['current']['total_active'] > 0 ? 
            $financialData['current']['tithes_offerings'] / $membershipData['current']['total_active'] : 0,
        'attendance_rate' => $membershipData['current']['total_active'] > 0 && $attendanceData['current']['average_attendance'] > 0 ?
            ($attendanceData['current']['average_attendance'] / $membershipData['current']['total_active']) * 100 : 0
    ];

} catch (Exception $e) {
    error_log("Error generating summary report: " . $e->getMessage());
    setFlashMessage('error', 'Error generating report: ' . $e->getMessage());
    
    // Initialize empty arrays
    $membershipData = ['current' => [], 'previous' => []];
    $financialData = ['current' => [], 'previous' => []];
    $attendanceData = ['current' => [], 'previous' => []];
    $visitorData = ['current' => [], 'previous' => []];
    $smsData = ['current' => []];
    $departmentData = [];
    $topIncomeCategories = [];
    $topExpenseCategories = [];
    $growthTrends = [];
    $kpis = [];
}

/**
 * Calculate growth rate percentage
 */
function calculateGrowthRate($current, $previous) {
    if ($previous == 0) {
        return $current > 0 ? 100 : 0;
    }
    return round((($current - $previous) / $previous) * 100, 1);
}

// Handle export
if ($format !== 'html') {
    // Export logic would go here
    exit();
}

include_once '../../includes/header.php';
?>

<!-- Executive Summary Content -->
<div class="row">
    <!-- Header with Period Selection -->
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-1">Executive Summary Report</h4>
                        <p class="text-muted mb-0">
                            Period: <?php echo formatDisplayDate($dateFrom); ?> to <?php echo formatDisplayDate($dateTo); ?>
                            <span class="badge bg-info ms-2"><?php echo ucwords(str_replace('_', ' ', $period)); ?></span>
                        </p>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fas fa-calendar me-2"></i>Change Period
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="?period=this_week">This Week</a></li>
                            <li><a class="dropdown-item" href="?period=this_month">This Month</a></li>
                            <li><a class="dropdown-item" href="?period=last_month">Last Month</a></li>
                            <li><a class="dropdown-item" href="?period=this_quarter">This Quarter</a></li>
                            <li><a class="dropdown-item" href="?period=last_quarter">Last Quarter</a></li>
                            <li><a class="dropdown-item" href="?period=this_year">This Year</a></li>
                            <li><a class="dropdown-item" href="?period=last_year">Last Year</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="?period=<?php echo $period; ?>&format=pdf">
                                <i class="fas fa-file-pdf me-2"></i>Export as PDF
                            </a></li>
                            <li><a class="dropdown-item" href="?period=<?php echo $period; ?>&format=excel">
                                <i class="fas fa-file-excel me-2"></i>Export as Excel
                            </a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Key Performance Indicators -->
    <div class="col-12 mb-4">
        <div class="row g-3">
            <div class="col-md-2">
                <div class="stats-card">
                    <div class="stats-icon bg-primary text-white">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stats-number"><?php echo number_format($membershipData['current']['total_active'] ?? 0); ?></div>
                    <div class="stats-label">Active Members</div>
                    <div class="stats-change <?php echo ($kpis['member_growth_rate'] ?? 0) >= 0 ? 'text-success' : 'text-danger'; ?>">
                        <i class="fas fa-arrow-<?php echo ($kpis['member_growth_rate'] ?? 0) >= 0 ? 'up' : 'down'; ?>"></i>
                        <?php echo abs($kpis['member_growth_rate'] ?? 0); ?>% vs previous period
                    </div>
                </div>
            </div>

            <div class="col-md-2">
                <div class="stats-card">
                    <div class="stats-icon bg-success text-white">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stats-number"><?php echo formatCurrency($financialData['current']['total_income'] ?? 0); ?></div>
                    <div class="stats-label">Total Income</div>
                    <div class="stats-change <?php echo ($kpis['income_growth_rate'] ?? 0) >= 0 ? 'text-success' : 'text-danger'; ?>">
                        <i class="fas fa-arrow-<?php echo ($kpis['income_growth_rate'] ?? 0) >= 0 ? 'up' : 'down'; ?>"></i>
                        <?php echo abs($kpis['income_growth_rate'] ?? 0); ?>% vs previous period
                    </div>
                </div>
            </div>

            <div class="col-md-2">
                <div class="stats-card">
                    <div class="stats-icon bg-info text-white">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stats-number"><?php echo number_format($attendanceData['current']['total_attendance'] ?? 0); ?></div>
                    <div class="stats-label">Total Attendance</div>
                    <div class="stats-change <?php echo ($kpis['attendance_growth_rate'] ?? 0) >= 0 ? 'text-success' : 'text-danger'; ?>">
                        <i class="fas fa-arrow-<?php echo ($kpis['attendance_growth_rate'] ?? 0) >= 0 ? 'up' : 'down'; ?>"></i>
                        <?php echo abs($kpis['attendance_growth_rate'] ?? 0); ?>% vs previous period
                    </div>
                </div>
            </div>

            <div class="col-md-2">
                <div class="stats-card">
                    <div class="stats-icon bg-warning text-white">
                        <i class="fas fa-user-friends"></i>
                    </div>
                    <div class="stats-number"><?php echo number_format($visitorData['current']['new_visitors'] ?? 0); ?></div>
                    <div class="stats-label">New Visitors</div>
                    <div class="stats-change <?php echo ($kpis['visitor_growth_rate'] ?? 0) >= 0 ? 'text-success' : 'text-danger'; ?>">
                        <i class="fas fa-arrow-<?php echo ($kpis['visitor_growth_rate'] ?? 0) >= 0 ? 'up' : 'down'; ?>"></i>
                        <?php echo abs($kpis['visitor_growth_rate'] ?? 0); ?>% vs previous period
                    </div>
                </div>
            </div>

            <div class="col-md-2">
                <div class="stats-card">
                    <div class="stats-icon bg-secondary text-white">
                        <i class="fas fa-hand-holding-heart"></i>
                    </div>
                    <div class="stats-number"><?php echo formatCurrency($kpis['giving_per_member'] ?? 0); ?></div>
                    <div class="stats-label">Giving Per Member</div>
                    <div class="stats-change text-info">
                        <i class="fas fa-info-circle"></i>
                        Tithes & Offerings only
                    </div>
                </div>
            </div>

            <div class="col-md-2">
                <div class="stats-card">
                    <div class="stats-icon bg-church-red text-white">
                        <i class="fas fa-percentage"></i>
                    </div>
                    <div class="stats-number"><?php echo round($kpis['attendance_rate'] ?? 0, 1); ?>%</div>
                    <div class="stats-label">Attendance Rate</div>
                    <div class="stats-change text-info">
                        <i class="fas fa-info-circle"></i>
                        Avg attendance vs members
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Growth Trends Chart -->
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-chart-line me-2"></i>
                    Growth Trends (Last 6 Months)
                </h6>
            </div>
            <div class="card-body">
                <canvas id="growthTrendsChart" height="300"></canvas>
            </div>
        </div>
    </div>

    <!-- Financial Overview -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    Financial Overview
                </h6>
            </div>
            <div class="card-body">
                <!-- Financial Summary -->
                <div class="row mb-4">
                    <div class="col-4 text-center">
                        <h5 class="text-success"><?php echo formatCurrency($financialData['current']['total_income'] ?? 0); ?></h5>
                        <small class="text-muted">Total Income</small>
                    </div>
                    <div class="col-4 text-center">
                        <h5 class="text-danger"><?php echo formatCurrency($financialData['current']['total_expenses'] ?? 0); ?></h5>
                        <small class="text-muted">Total Expenses</small>
                    </div>
                    <div class="col-4 text-center">
                        <h5 class="<?php echo ($financialData['current']['net_income'] ?? 0) >= 0 ? 'text-primary' : 'text-warning'; ?>">
                            <?php echo formatCurrency($financialData['current']['net_income'] ?? 0); ?>
                        </h5>
                        <small class="text-muted">Net Income</small>
                    </div>
                </div>

                <!-- Top Income Categories -->
                <h6 class="fw-bold mb-3">Top Income Categories</h6>
                <?php if (!empty($topIncomeCategories)): ?>
                    <?php foreach ($topIncomeCategories as $category): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><?php echo htmlspecialchars($category['category_name']); ?></span>
                            <span class="fw-bold"><?php echo formatCurrency($category['total_amount']); ?></span>
                        </div>
                        <div class="progress mb-3" style="height: 6px;">
                            <div class="progress-bar bg-success" style="width: <?php echo ($category['total_amount'] / $financialData['current']['total_income']) * 100; ?>%"></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted">No income data available</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Department Performance -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-sitemap me-2"></i>
                    Department Performance
                </h6>
            </div>
            <div class="card-body">
                <?php if (!empty($departmentData)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Department</th>
                                    <th class="text-end">Members</th>
                                    <th class="text-end">Events</th>
                                    <th class="text-end">Avg Attendance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($departmentData, 0, 8) as $dept): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($dept['department_name']); ?></strong></td>
                                        <td class="text-end"><?php echo number_format($dept['member_count']); ?></td>
                                        <td class="text-end"><?php echo number_format($dept['events_held']); ?></td>
                                        <td class="text-end"><?php echo number_format($dept['avg_event_attendance'] ?? 0); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center py-3">No department data available</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Additional Metrics -->
    <div class="col-12 mb-4">
        <div class="row g-3">
            <!-- Membership Metrics -->
            <div class="col-md-3">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0">Membership</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <h5><?php echo number_format($membershipData['current']['new_members'] ?? 0); ?></h5>
                                <small class="text-muted">New Members</small>
                            </div>
                            <div class="col-6">
                                <h5><?php echo number_format($membershipData['current']['inactive_members'] ?? 0); ?></h5>
                                <small class="text-muted">Inactive</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Visitor Metrics -->
            <div class="col-md-3">
                <div class="card">
                    <div class="card-header bg-warning text-white">
                        <h6 class="mb-0">Visitors</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <h5><?php echo number_format($visitorData['current']['returning_visitors'] ?? 0); ?></h5>
                                <small class="text-muted">Returning</small>
                            </div>
                            <div class="col-6">
                                <h5><?php echo number_format($visitorData['current']['converted_members'] ?? 0); ?></h5>
                                <small class="text-muted">Converted</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Event Metrics -->
            <div class="col-md-3">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0">Events</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <h5><?php echo number_format($attendanceData['current']['total_events'] ?? 0); ?></h5>
                                <small class="text-muted">Total Events</small>
                            </div>
                            <div class="col-6">
                                <h5><?php echo number_format($attendanceData['current']['average_attendance'] ?? 0); ?></h5>
                                <small class="text-muted">Avg Attendance</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Communication Metrics -->
            <div class="col-md-3">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0">Communication</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <h5><?php echo number_format($smsData['current']['messages_sent'] ?? 0); ?></h5>
                                <small class="text-muted">SMS Sent</small>
                            </div>
                            <div class="col-6">
                                <h5><?php echo round($smsData['current']['delivery_rate'] ?? 0, 1); ?>%</h5>
                                <small class="text-muted">Delivery Rate</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Items & Recommendations -->
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-lightbulb me-2"></i>
                    Insights & Recommendations
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-success">Positive Trends</h6>
                        <ul class="list-unstyled">
                            <?php if (($kpis['member_growth_rate'] ?? 0) > 0): ?>
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Income growth increased by <?php echo $kpis['income_growth_rate']; ?>%
                                </li>
                            <?php endif; ?>
                            
                            <?php if (($kpis['attendance_growth_rate'] ?? 0) > 0): ?>
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Attendance growth increased by <?php echo $kpis['attendance_growth_rate']; ?>%
                                </li>
                            <?php endif; ?>
                            
                            <?php if (($kpis['visitor_growth_rate'] ?? 0) > 0): ?>
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Visitor growth increased by <?php echo $kpis['visitor_growth_rate']; ?>%
                                </li>
                            <?php endif; ?>
                            
                            <?php if (($smsData['current']['delivery_rate'] ?? 0) >= 90): ?>
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Excellent SMS delivery rate (<?php echo round($smsData['current']['delivery_rate'], 1); ?>%)
                                </li>
                            <?php endif; ?>
                            
                            <?php if (($financialData['current']['net_income'] ?? 0) > 0): ?>
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Positive net income of <?php echo formatCurrency($financialData['current']['net_income']); ?>
                                </li>
                            <?php endif; ?>
                        </ul>
                        
                        <?php if (empty(array_filter([$kpis['member_growth_rate'] ?? 0, $kpis['income_growth_rate'] ?? 0, $kpis['attendance_growth_rate'] ?? 0], function($v) { return $v > 0; }))): ?>
                            <p class="text-muted"><i class="fas fa-info-circle me-2"></i>Focus on growth opportunities in the areas of concern.</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="text-warning">Areas for Attention</h6>
                        <ul class="list-unstyled">
                            <?php if (($kpis['member_growth_rate'] ?? 0) < 0): ?>
                                <li class="mb-2">
                                    <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                    Member growth declined by <?php echo abs($kpis['member_growth_rate']); ?>% - consider outreach programs
                                </li>
                            <?php endif; ?>
                            
                            <?php if (($kpis['income_growth_rate'] ?? 0) < 0): ?>
                                <li class="mb-2">
                                    <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                    Income declined by <?php echo abs($kpis['income_growth_rate']); ?>% - review giving patterns
                                </li>
                            <?php endif; ?>
                            
                            <?php if (($kpis['attendance_rate'] ?? 0) < 50): ?>
                                <li class="mb-2">
                                    <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                    Low attendance rate (<?php echo round($kpis['attendance_rate'], 1); ?>%) - engage inactive members
                                </li>
                            <?php endif; ?>
                            
                            <?php if (($membershipData['current']['inactive_members'] ?? 0) > ($membershipData['current']['total_active'] ?? 1) * 0.2): ?>
                                <li class="mb-2">
                                    <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                    High number of inactive members - implement retention strategies
                                </li>
                            <?php endif; ?>
                            
                            <?php if (($smsData['current']['delivery_rate'] ?? 0) < 80): ?>
                                <li class="mb-2">
                                    <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                    Low SMS delivery rate - update member phone numbers
                                </li>
                            <?php endif; ?>
                            
                            <?php if (($financialData['current']['net_income'] ?? 0) < 0): ?>
                                <li class="mb-2">
                                    <i class="fas fa-exclamation-triangle text-danger me-2"></i>
                                    Negative net income - review expenses and increase income
                                </li>
                            <?php endif; ?>
                        </ul>
                        
                        <?php if (($visitorData['current']['converted_members'] ?? 0) == 0 && ($visitorData['current']['new_visitors'] ?? 0) > 0): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Visitor Follow-up Opportunity:</strong> 
                                You had <?php echo $visitorData['current']['new_visitors']; ?> new visitors but no conversions. 
                                Consider improving your visitor follow-up process.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for Charts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Growth Trends Chart
    const growthCtx = document.getElementById('growthTrendsChart').getContext('2d');
    
    const growthData = {
        labels: [<?php echo implode(',', array_map(function($item) { return '"' . $item['month_name'] . '"'; }, $growthTrends)); ?>],
        datasets: [
            {
                label: 'New Members',
                data: [<?php echo implode(',', array_map(function($item) { return $item['new_members']; }, $growthTrends)); ?>],
                borderColor: '#03045e',
                backgroundColor: 'rgba(3, 4, 94, 0.1)',
                yAxisID: 'y',
                tension: 0.4
            },
            {
                label: 'Monthly Income',
                data: [<?php echo implode(',', array_map(function($item) { return $item['monthly_income']; }, $growthTrends)); ?>],
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                yAxisID: 'y1',
                tension: 0.4
            },
            {
                label: 'Attendance',
                data: [<?php echo implode(',', array_map(function($item) { return $item['monthly_attendance']; }, $growthTrends)); ?>],
                borderColor: '#ff2400',
                backgroundColor: 'rgba(255, 36, 0, 0.1)',
                yAxisID: 'y',
                tension: 0.4
            },
            {
                label: 'New Visitors',
                data: [<?php echo implode(',', array_map(function($item) { return $item['new_visitors']; }, $growthTrends)); ?>],
                borderColor: '#ffc107',
                backgroundColor: 'rgba(255, 193, 7, 0.1)',
                yAxisID: 'y',
                tension: 0.4
            }
        ]
    };
    
    new Chart(growthCtx, {
        type: 'line',
        data: growthData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    position: 'top',
                },
                title: {
                    display: false
                }
            },
            scales: {
                x: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Month'
                    }
                },
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Count'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Amount (<?php echo CURRENCY_SYMBOL; ?>)'
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                    ticks: {
                        callback: function(value) {
                            return '<?php echo CURRENCY_SYMBOL; ?>' + value.toLocaleString();
                        }
                    }
                }
            },
            tooltips: {
                callbacks: {
                    label: function(context) {
                        let label = context.dataset.label || '';
                        if (label) {
                            label += ': ';
                        }
                        if (context.datasetIndex === 1) { // Income dataset
                            label += '<?php echo CURRENCY_SYMBOL; ?>' + context.parsed.y.toLocaleString();
                        } else {
                            label += context.parsed.y.toLocaleString();
                        }
                        return label;
                    }
                }
            }
        }
    });
});

// Print functionality
function printSummary() {
    window.print();
}

// Export functionality
function exportSummary(format) {
    window.open(`?period=<?php echo $period; ?>&format=${format}`, '_blank');
}

// Auto-refresh data every 10 minutes
setInterval(function() {
    if (document.visibilityState === 'visible') {
        fetch(window.location.href + '&ajax=1')
            .then(response => {
                if (response.ok) {
                    console.log('Summary data refreshed');
                }
            })
            .catch(error => {
                console.error('Error refreshing summary:', error);
            });
    }
}, 600000); // 10 minutes

// Add smooth animations to stats cards
document.addEventListener('DOMContentLoaded', function() {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-fade-in');
            }
        });
    });

    document.querySelectorAll('.stats-card').forEach(card => {
        observer.observe(card);
    });
});
</script>

<!-- Print Styles -->
<style media="print">
    .dropdown, .no-print {
        display: none !important;
    }
    
    .card {
        border: 1px solid #000 !important;
        box-shadow: none !important;
        break-inside: avoid;
    }
    
    .stats-card {
        border: 1px solid #000 !important;
        margin-bottom: 1rem !important;
    }
    
    .stats-number {
        color: #000 !important;
    }
    
    .chart-container {
        page-break-inside: avoid;
    }
    
    h1, h2, h3, h4, h5, h6 {
        color: #000 !important;
        break-after: avoid;
    }
    
    .text-success, .text-danger, .text-warning, .text-info {
        color: #000 !important;
    }
    
    .bg-primary, .bg-success, .bg-danger, .bg-warning, .bg-info {
        background: #f0f0f0 !important;
        color: #000 !important;
    }
</style>

<?php include_once '../../includes/footer.php'; ?> me-2"></i>
                                    Member growth increased by <?php echo $kpis['member_growth_rate']; ?>%
                                </li>
                            <?php endif; ?>
                            
                            <?php if (($kpis['income_growth_rate'] ?? 0) > 0): ?>
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success<?php
/**
 * Executive Summary Reports
 * Deliverance Church Management System
 * 
 * High-level overview reports for church leadership
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

// Get report parameters
$period = sanitizeInput($_GET['period'] ?? 'this_month');
$format = sanitizeInput($_GET['format'] ?? 'html');

// Page configuration
$page_title = 'Executive Summary Reports';
$page_icon = 'fas fa-chart-pie';
$breadcrumb = [
    ['title' => 'Reports', 'url' => 'index.php'],
    ['title' => 'Executive Summary']
];

// Initialize database
$db = Database::getInstance();

// Calculate date ranges
$dateRanges = [
    'this_week' => [date('Y-m-d', strtotime('monday this week')), date('Y-m-d')],
    'this_month' => [date('Y-m-01'), date('Y-m-d')],
    'last_month' => [date('Y-m-01', strtotime('-1 month')), date('Y-m-t', strtotime('-1 month'))],
    'this_quarter' => [date('Y-m-01', strtotime('first day of this quarter')), date('Y-m-d')],
    'last_quarter' => [date('Y-m-01', strtotime('first day of last quarter')), date('Y-m-t', strtotime('last day of last quarter'))],
    'this_year' => [date('Y-01-01'), date('Y-m-d')],
    'last_year' => [date('Y-01-01', strtotime('-1 year')), date('Y-12-31', strtotime('-1 year'))]
];

list($dateFrom, $dateTo) = $dateRanges[$period] ?? $dateRanges['this_month'];

// Get previous period for comparison
$dateDiff = (new DateTime($dateTo))->diff(new DateTime($dateFrom))->days;
$prevDateTo = (new DateTime($dateFrom))->sub(new DateInterval('P1D'))->format('Y-m-d');
$prevDateFrom = (new DateTime($prevDateTo))->sub(new DateInterval("P{$dateDiff}D"))->format('Y-m-d');

try {
    // MEMBERSHIP OVERVIEW
    $membershipData = [
        'current' => [
            'total_active' => $db->executeQuery("SELECT COUNT(*) as count FROM members WHERE membership_status = 'active'")->fetch()['count'],
            'new_members' => $db->executeQuery("SELECT COUNT(*) as count FROM members WHERE join_date BETWEEN ? AND ?", [$dateFrom, $dateTo])->fetch()['count'],
            'inactive_members' => $db->executeQuery("SELECT COUNT(*) as count FROM members WHERE membership_status = 'inactive'")->fetch()['count']
        ],
        'previous' => [
            'new_members' => $db->executeQuery("SELECT COUNT(*) as count FROM members WHERE join_date BETWEEN ? AND ?", [$prevDateFrom, $prevDateTo])->fetch()['count']
        ]
    ];
    $membershipData['current']['growth_rate'] = $membershipData['previous']['new_members'] > 0 ? 
        (($membershipData['current']['new_members'] - $membershipData['previous']['new_members']) / $membershipData['previous']['new_members']) * 100 : 
        ($membershipData['current']['new_members'] > 0 ? 100 : 0);

    // FINANCIAL OVERVIEW
    $financialData = [
        'current' => [
            'total_income' => $db->executeQuery("SELECT SUM(amount) as total FROM financial_transactions WHERE type = 'income' AND date BETWEEN ? AND ?", [$dateFrom, $dateTo])->fetch()['total'] ?? 0,
            'total_expenses' => $db->executeQuery("SELECT SUM(amount) as total FROM financial_transactions WHERE type = 'expense' AND date BETWEEN ? AND ?", [$dateFrom, $dateTo])->fetch()['total'] ?? 0
        ],
        'previous' => [
            'total_income' => $db->executeQuery("SELECT SUM(amount) as total FROM financial_transactions WHERE type = 'income' AND date BETWEEN ? AND ?", [$prevDateFrom, $prevDateTo])->fetch()['total'] ?? 0
        ]
    ];      

    $financialData['current']['net_income'] = $financialData['current']['total_income'] - $financialData['current']['total_expenses'];
    $financialData['current']['income_per_member'] = $membershipData['current']['total_active'] > 0 ? $financialData['current']['total_income'] / $membershipData['current']['total_active'] : 0;
    $financialData['previous']['net_income'] = $financialData['previous']['total_income'] - ($db->executeQuery("SELECT SUM(amount) as total FROM financial_transactions WHERE type = 'expense' AND date BETWEEN ? AND ?", [$prevDateFrom, $prevDateTo])->fetch()['total'] ?? 0);
    $financialData['previous']['income_per_member'] = $membershipData['previous']['new_members'] > 0 ? $financialData['previous']['total_income'] / $membershipData['previous']['new_members'] : 0;
    $financialData['current']['income_growth_rate'] = $financialData['previous']['total_income'] > 0 ? 
        (($financialData['current']['total_income'] - $financialData['previous']['total_income']) / $financialData['previous']['total_income']) * 100 : 
        ($financialData['current']['total_income'] > 0 ? 100 : 0);
    $financialData['current']['expense_growth_rate'] = ($db->executeQuery("SELECT SUM(amount) as total FROM financial_transactions WHERE type = 'expense' AND date BETWEEN ? AND ?", [$prevDateFrom, $prevDateTo])->fetch()['total'] ?? 0) > 0 ? 
        (($financialData['current']['total_expenses'] - ($db->executeQuery("SELECT SUM(amount) as total FROM financial_transactions WHERE type = 'expense' AND date BETWEEN ? AND ?", [$prevDateFrom, $prevDateTo])->fetch()['total'] ?? 0)) / ($db->executeQuery("SELECT SUM(amount) as total FROM financial_transactions WHERE type = 'expense' AND date BETWEEN ? AND ?", [$prevDateFrom, $prevDateTo])->fetch()['total'] ?? 0)) * 100 : 
        ($financialData['current']['total_expenses'] > 0 ? 100 : 0);    
    $financialData['current']['giving_per_member'] = $membershipData['current']['total_active'] > 0 ? 
        ($db->executeQuery("SELECT SUM(amount) as total FROM financial_transactions WHERE type = 'income' AND category IN ('tithe', 'offering') AND date BETWEEN ? AND ?", [$dateFrom, $dateTo])->fetch()['total'] ?? 0) / $membershipData['current']['total_active'] : 0;          
    $financialData['previous']['giving_per_member'] = $membershipData['previous']['new_members'] > 0 ? 
        ($db->executeQuery("SELECT SUM(amount) as total FROM financial_transactions WHERE type = 'income' AND category IN ('tithe', 'offering') AND date BETWEEN ? AND ?", [$prevDateFrom, $prevDateTo])->fetch()['total'] ?? 0) / $membershipData['previous']['new_members'] : 0;  
    $financialData['current']['giving_growth_rate'] = ($db->executeQuery("SELECT SUM(amount) as total FROM financial_transactions WHERE type = 'income' AND category IN ('tithe', 'offering') AND date BETWEEN ? AND ?", [$prevDateFrom, $prevDateTo])->fetch()['total'] ?? 0) > 0 ? 
        ((($db->executeQuery("SELECT SUM(amount) as total FROM financial_transactions WHERE type = 'income' AND category IN ('tithe', 'offering') AND date BETWEEN ? AND ?", [$dateFrom, $dateTo])->fetch()['total'] ?? 0) - ($db->executeQuery("SELECT SUM(amount) as total FROM financial_transactions WHERE type = 'income' AND category IN ('tithe', 'offering') AND date BETWEEN ? AND ?", [$prevDateFrom, $prevDateTo])->fetch()['total'] ?? 0)) / ($db->executeQuery("SELECT SUM(amount) as total FROM financial_transactions WHERE type = 'income' AND category IN ('tithe', 'offering') AND date BETWEEN ? AND ?", [$prevDateFrom, $prevDateTo])->fetch()['total'] ?? 0)) * 100 : 
        (($db->executeQuery("SELECT SUM(amount) as total FROM financial_transactions WHERE type = 'income' AND category IN ('tithe', 'offering') AND date BETWEEN ? AND ?", [$dateFrom, $dateTo])->fetch()['total'] ?? 0) > 0 ? 100 : 0);       
    // Top Income Categories
    $topIncomeCategories = $db->executeQuery("SELECT category, SUM(amount) as total_amount FROM financial_transactions WHERE type = 'income' AND date BETWEEN ? AND ? GROUP BY category ORDER BY total_amount DESC LIMIT 5", [$dateFrom, $dateTo])->fetchAll();     
    // VISITOR OVERVIEW
    $visitorData = [    
        'current' => [
            'new_visitors' => $db->executeQuery("SELECT COUNT(*) as count FROM visitors WHERE visit_date BETWEEN ? AND ?", [$dateFrom, $dateTo])->fetch()['count'],
            'returning_visitors' => $db->executeQuery("SELECT COUNT(DISTINCT v.id) as count FROM visitors v JOIN members m ON v.email = m.email WHERE v.visit_date BETWEEN ? AND ?", [$dateFrom, $dateTo])->fetch()['count'],
            'converted_members' => $db->executeQuery("SELECT COUNT(*) as count FROM members WHERE join_date BETWEEN ? AND ? AND email IN (SELECT email FROM visitors WHERE visit_date BETWEEN ? AND ?)", [$dateFrom, $dateTo, $dateFrom, $dateTo])->fetch()['count']
        ],
        'previous' => [
            'new_visitors' => $db->executeQuery("SELECT COUNT(*) as count FROM visitors WHERE visit_date BETWEEN ? AND ?", [$prevDateFrom, $prevDateTo])->fetch()['count']
        ]
    ];  
    $visitorData['current']['growth_rate'] = $visitorData['previous']['new_visitors'] > 0 ? 
        (($visitorData['current']['new_visitors'] - $visitorData['previous']['new_visitors']) / $visitorData['previous']['new_visitors']) * 100 : 
        ($visitorData['current']['new_visitors'] > 0 ? 100 : 0);
    $visitorData['current']['conversion_rate'] = $visitorData['current']['new_visitors'] > 0 ? 
        ($visitorData['current']['converted_members'] / $visitorData['current']['new_visitors']) * 100 : 0;
    $visitorData['previous']['conversion_rate'] = $visitorData['previous']['new_visitors'] > 0 ? 
        ($db->executeQuery("SELECT COUNT(*) as count FROM members WHERE join_date BETWEEN ? AND ? AND email IN (SELECT email FROM visitors WHERE visit_date BETWEEN ? AND ?)", [$prevDateFrom, $prevDateTo, $prevDateFrom, $prevDateTo])->fetch()['count'] / $visitorData['previous']['new_visitors']) * 100 : 0;
    // ATTENDANCE OVERVIEW
    $attendanceData = [
        'current' => [
            'total_events' => $db->executeQuery("SELECT COUNT(*) as count FROM events WHERE event_date BETWEEN ? AND ?", [$dateFrom, $dateTo])->fetch()['count'],
            'total_attendance' => $db->executeQuery("SELECT SUM(attendance_count) as total FROM events WHERE event_date BETWEEN ? AND ?", [$dateFrom, $dateTo])->fetch()['total'] ?? 0
        ],
        'previous' => [
            'total_events' => $db->executeQuery("SELECT COUNT(*) as count FROM events WHERE event_date BETWEEN ? AND ?", [$prevDateFrom, $prevDateTo])->fetch()['count'],
            'total_attendance' => $db->executeQuery("SELECT SUM(attendance_count) as total FROM events WHERE event_date BETWEEN ? AND ?", [$prevDateFrom, $prevDateTo])->fetch()['total'] ?? 0
        ]
    ];
    $attendanceData['current']['average_attendance'] = $attendanceData['current']['total_events'] > 0 ? $attendanceData['current']['total_attendance'] / $attendanceData['current']['total_events'] : 0;
    $attendanceData['previous']['average_attendance'] = $attendanceData['previous']['total_events'] > 0 ? $attendanceData['previous']['total_attendance'] / $attendanceData['previous']['total_events'] : 0;
    $attendanceData['current']['attendance_rate'] = $membershipData['current']['total_active'] > 0 ? ($attendanceData['current']['total_attendance'] / ($membershipData['current']['total_active'] * $attendanceData['current']['total_events'])) * 100 : 0;
    $attendanceData['previous']['attendance_rate'] = $membershipData['previous']['new_members'] > 0 ? ($attendanceData['previous']['total_attendance'] / ($membershipData['previous']['new_members'] * $attendanceData['previous']['total_events'])) * 100 : 0;
    $attendanceData['current']['attendance_growth_rate'] = $attendanceData['previous']['total_attendance'] > 0 ? 
        (($attendanceData['current']['total_attendance'] - $attendanceData['previous']['total_attendance']) / $attendanceData['previous']['total_attendance']) * 100 : 
        ($attendanceData['current']['total_attendance'] > 0 ? 100 : 0); 
    // SMS COMMUNICATION OVERVIEW
    $smsData = [    
        'current' => [
            'messages_sent' => $db->executeQuery("SELECT COUNT(*) as count FROM sms_logs WHERE date_sent BETWEEN ? AND ?", [$dateFrom, $dateTo])->fetch()['count'],
            'delivery_rate' => $db->executeQuery("SELECT AVG(delivery_status) as avg_status FROM sms_logs WHERE date_sent BETWEEN ? AND ?", [$dateFrom, $dateTo])->fetch()['avg_status'] * 100
        ],
        'previous' => [
            'messages_sent' => $db->executeQuery("SELECT COUNT(*) as count FROM sms_logs WHERE date_sent BETWEEN ? AND ?", [$prevDateFrom, $prevDateTo])->fetch()['count'],
            'delivery_rate' => $db->executeQuery("SELECT AVG(delivery_status) as avg_status FROM sms_logs WHERE date_sent BETWEEN ? AND ?", [$prevDateFrom, $prevDateTo])->fetch()['avg_status'] * 100
        ]
    ];      
    $smsData['current']['growth_rate'] = $smsData['previous']['messages_sent'] > 0 ? 
        (($smsData['current']['messages_sent'] - $smsData['previous']['messages_sent']) / $smsData['previous']['messages_sent']) * 100 : 
        ($smsData['current']['messages_sent'] > 0 ? 100 : 0);
    $smsData['current']['delivery_rate'] = $smsData['current']['delivery_rate'] ?? 0;
    $smsData['previous']['delivery_rate'] = $smsData['previous']['delivery_rate'] ?? 0;
    $smsData['current']['delivery_rate_change'] = $smsData['previous']['delivery_rate'] > 0 ? 
        $smsData['current']['delivery_rate'] - $smsData['previous']['delivery_rate'] : 
        ($smsData['current']['delivery_rate'] > 0 ? $smsData['current']['delivery_rate'] : 0);
    // KEY PERFORMANCE INDICATORS
    $kpis = [
        'member_growth_rate' => $membershipData['current']['growth_rate'],
        'income_growth_rate' => $financialData['current']['income_growth_rate'],
        'expense_growth_rate' => $financialData['current']['expense_growth_rate'],
        'giving_growth_rate' => $financialData['current']['giving_growth_rate'],
        'attendance_rate' => $attendanceData['current']['attendance_rate'],
        'attendance_growth_rate' => $attendanceData['current']['attendance_growth_rate'],
        'visitor_growth_rate' => $visitorData['current']['growth_rate'],
        'visitor_conversion_rate' => $visitorData['current']['conversion_rate']
    ];
    // GROWTH TRENDS (Last 6 months)
    $growthTrends = []; 
    for ($i = 5; $i >= 0; $i--) {
        $monthStart = date('Y-m-01', strtotime("-$i months"));
        $monthEnd = date('Y-m-t', strtotime("-$i months"));
        $growthTrends[] = [
            'month' => date('Y-m', strtotime($monthStart)),
            'month_name' => date('M Y', strtotime($monthStart)),
            'new_members' => $db->executeQuery("SELECT COUNT(*) as count FROM members WHERE join_date BETWEEN ? AND ?", [$monthStart, $monthEnd])->fetch()['count'],
            'monthly_income' => $db->executeQuery("SELECT SUM(amount) as total FROM financial_transactions WHERE type = 'income' AND date BETWEEN ? AND ?", [$monthStart, $monthEnd])->fetch()['total'] ?? 0,
            'monthly_attendance' => $db->executeQuery("SELECT SUM(attendance_count) as total FROM events WHERE event_date BETWEEN ? AND ?", [$monthStart, $monthEnd])->fetch()['total'] ?? 0,
            'new_visitors' => $db->executeQuery("SELECT COUNT(*) as count FROM visitors WHERE visit_date BETWEEN ? AND ?", [$monthStart, $monthEnd])->fetch()['count']
        ];
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    die('Error fetching report data. Please try again later.');
}   
// Handle export formats
if ($format === 'pdf') {    
    require_once '../../includes/tcpdf/tcpdf.php';
    ob_start();
    include 'summary_report_pdf.php';
    $html = ob_get_clean();
    
    $pdf = new TCPDF();
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Deliverance CMS');
    $pdf->SetTitle('Executive Summary Report');
    $pdf->SetHeaderData('', 0, 'Executive Summary Report', "Period: " . ucfirst(str_replace('_', ' ', $period)) . "\nGenerated on: " . date('Y-m-d H:i:s'));
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    $pdf->SetMargins(15, 27, 15);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(10);
    $pdf->SetAutoPageBreak(TRUE, 25);
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    $pdf->AddPage();
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('executive_summary_report.pdf', 'I');
    exit();
} elseif ($format === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="executive_summary_report.csv"');
    $output = fopen('php://output', 'w');
    
    fputcsv($output, ['Metric', 'Current Period', 'Previous Period', 'Change (%)']);
    
    // Membership
    fputcsv($output, ['Total Active Members', $membershipData['current']['total_active'], '', '']);
    fputcsv($output, ['New Members', $membershipData['current']['new_members'], $membershipData['previous']['new_members'], round($membershipData['current']['growth_rate'], 2) . '%']);
    
    // Financial
    fputcsv($output, ['Total Income', formatCurrency($financialData['current']['total_income']), formatCurrency($financialData['previous']['total_income']), round($financialData['current']['income_growth_rate'], 2) . '%']);
    fputcsv($output, ['Total Expenses', formatCurrency($financialData['current']['total_expenses']), formatCurrency($financialData['previous']['total_expenses']), round($financialData['current']['expense_growth_rate'], 2) . '%']);
    fputcsv($output, ['Net Income', formatCurrency($financialData['current']['net_income']), formatCurrency($financialData['previous']['net_income']), '']);
    fputcsv($output, ['Income per Member', formatCurrency($financialData['current']['income_per_member']), formatCurrency($financialData['previous']['income_per_member']), '']);
    fputcsv($output, ['Giving per Member', formatCurrency($financialData['current']['giving_per_member']), formatCurrency($financialData['previous']['giving_per_member']), round($financialData['current']['giving_growth_rate'], 2) . '%']);      
    // Visitors
    fputcsv($output, ['New Visitors', $visitorData['current']['new_visitors'], $visitorData['previous']['new_visitors'], round($visitorData['current']['growth_rate'], 2) . '%']);
    fputcsv($output, ['Returning Visitors', $visitorData['current']['returning_visitors'], '', '']);
    fputcsv($output, ['Converted Members', $visitorData['current']['converted_members'], '', '']);
    fputcsv($output, ['Visitor Conversion Rate', round($visitorData['current']['conversion_rate'], 2) . '%', round($visitorData['previous']['conversion_rate'], 2) . '%', '']);
    // Attendance   
    fputcsv($output, ['Total Events', $attendanceData['current']['total_events'], $attendanceData['previous']['total_events'], '']);
    fputcsv($output, ['Total Attendance', $attendanceData['current']['total_attendance'], $attendanceData['previous']['total_attendance'], round($attendanceData['current']['attendance_growth_rate'], 2) . '%']);
    fputcsv($output, ['Average Attendance', round($attendanceData['current']['average_attendance'], 2), round($attendanceData['previous']['average_attendance'], 2), '']);
    fputcsv($output, ['Attendance Rate', round($attendanceData['current']['attendance_rate'], 2) . '%', round($attendanceData['previous']['attendance_rate'], 2) . '%', '']);
    // SMS
    fputcsv($output, ['SMS Messages Sent', $smsData['current']['messages_sent'], $smsData['previous']['messages_sent'], round($smsData['current']['growth_rate'], 2) . '%']);
    fputcsv($output, ['SMS Delivery Rate', round($smsData['current']['delivery_rate'], 2) . '%', round($smsData['previous']['delivery_rate'], 2) . '%', round($smsData['current']['delivery_rate_change'], 2) . '%']);  
    fclose($output);
    exit();
} elseif ($format !== 'html') {
    die('Invalid format specified.');
}

// Include header
include_once '../../includes/header.php';