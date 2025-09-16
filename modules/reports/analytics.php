FROM (
            SELECT member_id
            FROM attendance_records ar
            JOIN events e ON ar.event_id = e.id
            WHERE e.event_date >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
            GROUP BY member_id
            HAVING COUNT(*) BETWEEN 3 AND 7
        ) occasional_attenders
        
        UNION ALL
        
        SELECT 
            'Infrequent Attenders' as category,
            COUNT(*) as count
        FROM (
            SELECT member_id
            FROM attendance_records ar
            JOIN events e ON ar.event_id = e.id
            WHERE e.event_date >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
            GROUP BY member_id
            HAVING COUNT(*) <= 2
        ) infrequent_attenders
    ";
    
    $individualPatterns = $db->executeQuery($individualPatternsQuery)->fetchAll();
    
    return [
        'weekly_patterns' => $weeklyPatterns,
        'monthly_trends' => $monthlyTrends,
        'individual_patterns' => $individualPatterns
    ];
}

/**
 * Get financial insights
 */
function getFinancialInsights($db) {
    // Income vs expenses trend
    $trendsQuery = "
        SELECT 
            DATE_FORMAT(date_field, '%Y-%m') as month,
            DATE_FORMAT(date_field, '%M %Y') as month_name,
            'Income' as type,
            SUM(amount) as total
        FROM (
            SELECT transaction_date as date_field, amount 
            FROM income 
            WHERE transaction_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            AND status = 'verified'
        ) income_data
        GROUP BY DATE_FORMAT(date_field, '%Y-%m')
        
        UNION ALL
        
        SELECT 
            DATE_FORMAT(date_field, '%Y-%m') as month,
            DATE_FORMAT(date_field, '%M %Y') as month_name,
            'Expenses' as type,
            SUM(amount) as total
        FROM (
            SELECT expense_date as date_field, amount 
            FROM expenses 
            WHERE expense_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            AND status = 'paid'
        ) expense_data
        GROUP BY DATE_FORMAT(date_field, '%Y-%m')
        
        ORDER BY month ASC, type ASC
    ";
    
    $trends = $db->executeQuery($trendsQuery)->fetchAll();
    
    // Giving patterns analysis
    $givingPatternsQuery = "
        SELECT 
            CASE 
                WHEN COUNT(*) = 1 THEN 'One-time Givers'
                WHEN COUNT(*) <= 4 THEN 'Occasional Givers'
                WHEN COUNT(*) <= 12 THEN 'Regular Givers'
                ELSE 'Frequent Givers'
            END as giver_category,
            COUNT(DISTINCT donor_name) as donor_count,
            AVG(total_given) as avg_giving,
            SUM(total_given) as category_total
        FROM (
            SELECT 
                donor_name,
                COUNT(*) as donation_count,
                SUM(amount) as total_given
            FROM income
            WHERE transaction_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            AND status = 'verified'
            AND donor_name IS NOT NULL
            AND donor_name != ''
            GROUP BY donor_name
        ) donor_analysis
        GROUP BY giver_category
        ORDER BY category_total DESC
    ";
    
    $givingPatterns = $db->executeQuery($givingPatternsQuery)->fetchAll();
    
    // Category performance
    $categoryPerformanceQuery = "
        SELECT 
            'Income' as type,
            ic.name as category,
            COUNT(*) as transaction_count,
            SUM(i.amount) as total_amount,
            AVG(i.amount) as avg_amount
        FROM income i
        JOIN income_categories ic ON i.category_id = ic.id
        WHERE i.transaction_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        AND i.status = 'verified'
        GROUP BY ic.id, ic.name
        
        UNION ALL
        
        SELECT 
            'Expense' as type,
            ec.name as category,
            COUNT(*) as transaction_count,
            SUM(e.amount) as total_amount,
            AVG(e.amount) as avg_amount
        FROM expenses e
        JOIN expense_categories ec ON e.category_id = ec.id
        WHERE e.expense_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        AND e.status = 'paid'
        GROUP BY ec.id, ec.name
        
        ORDER BY type ASC, total_amount DESC
    ";
    
    $categoryPerformance = $db->executeQuery($categoryPerformanceQuery)->fetchAll();
    
    return [
        'trends' => $trends,
        'giving_patterns' => $givingPatterns,
        'category_performance' => $categoryPerformance
    ];
}

/**
 * Get engagement metrics
 */
function getEngagementMetrics($db) {
    // Member engagement score
    $engagementQuery = "
        SELECT 
            m.id,
            m.first_name,
            m.last_name,
            m.join_date,
            COALESCE(attendance_score, 0) as attendance_score,
            COALESCE(giving_score, 0) as giving_score,
            COALESCE(involvement_score, 0) as involvement_score,
            COALESCE(attendance_score, 0) + COALESCE(giving_score, 0) + COALESCE(involvement_score, 0) as total_engagement_score
        FROM members m
        LEFT JOIN (
            SELECT 
                ar.member_id,
                (COUNT(*) * 25.0 / (SELECT COUNT(*) FROM events WHERE event_date >= DATE_SUB(NOW(), INTERVAL 3 MONTH))) as attendance_score
            FROM attendance_records ar
            JOIN events e ON ar.event_id = e.id
            WHERE e.event_date >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
            GROUP BY ar.member_id
        ) attendance_metrics ON m.id = attendance_metrics.member_id
        LEFT JOIN (
            SELECT 
                member_id,
                CASE 
                    WHEN total_given > 0 THEN LEAST(25.0, (donation_count * 5.0))
                    ELSE 0 
                END as giving_score
            FROM (
                SELECT 
                    (SELECT id FROM members WHERE CONCAT(first_name, ' ', last_name) = i.donor_name LIMIT 1) as member_id,
                    COUNT(*) as donation_count,
                    SUM(amount) as total_given
                FROM income i
                WHERE i.transaction_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                AND i.status = 'verified'
                AND i.donor_name IS NOT NULL
                GROUP BY i.donor_name
            ) giving_data
            WHERE member_id IS NOT NULL
        ) giving_metrics ON m.id = giving_metrics.member_id
        LEFT JOIN (
            SELECT 
                member_id,
                COUNT(*) * 12.5 as involvement_score
            FROM member_departments
            WHERE is_active = 1
            GROUP BY member_id
        ) involvement_metrics ON m.id = involvement_metrics.member_id
        WHERE m.membership_status = 'active'
        ORDER BY total_engagement_score DESC
        LIMIT 50
    ";
    
    $engagement = $db->executeQuery($engagementQuery)->fetchAll();
    
    // Department engagement
    $departmentEngagementQuery = "
        SELECT 
            d.name as department_name,
            d.department_type,
            COUNT(md.member_id) as member_count,
            AVG(COALESCE(attendance_freq.freq, 0)) as avg_attendance_frequency,
            COUNT(CASE WHEN last_active.days_since <= 30 THEN 1 END) as active_members
        FROM departments d
        LEFT JOIN member_departments md ON d.id = md.department_id AND md.is_active = 1
        LEFT JOIN members m ON md.member_id = m.id
        LEFT JOIN (
            SELECT 
                ar.member_id,
                COUNT(*) as freq
            FROM attendance_records ar
            JOIN events e ON ar.event_id = e.id
            WHERE e.event_date >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
            GROUP BY ar.member_id
        ) attendance_freq ON m.id = attendance_freq.member_id
        LEFT JOIN (
            SELECT 
                ar.member_id,
                DATEDIFF(NOW(), MAX(ar.check_in_time)) as days_since
            FROM attendance_records ar
            GROUP BY ar.member_id
        ) last_active ON m.id = last_active.member_id
        WHERE d.is_active = 1
        GROUP BY d.id, d.name, d.department_type
        ORDER BY member_count DESC
    ";
    
    $departmentEngagement = $db->executeQuery($departmentEngagementQuery)->fetchAll();
    
    return [
        'member_engagement' => $engagement,
        'department_engagement' => $departmentEngagement
    ];
}

/**
 * Get visitor conversion funnel
 */
function getVisitorConversionFunnel($db) {
    $funnelQuery = "
        SELECT 
            'Total Visitors' as stage,
            COUNT(*) as count,
            100.0 as percentage,
            1 as stage_order
        FROM visitors
        WHERE visit_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        
        UNION ALL
        
        SELECT 
            'Contacted for Follow-up' as stage,
            COUNT(*) as count,
            (COUNT(*) * 100.0 / (SELECT COUNT(*) FROM visitors WHERE visit_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH))) as percentage,
            2 as stage_order
        FROM visitors
        WHERE visit_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        AND status IN ('follow_up', 'regular_attender', 'converted_member')
        
        UNION ALL
        
        SELECT 
            'Regular Attenders' as stage,
            COUNT(*) as count,
            (COUNT(*) * 100.0 / (SELECT COUNT(*) FROM visitors WHERE visit_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH))) as percentage,
            3 as stage_order
        FROM visitors
        WHERE visit_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        AND status IN ('regular_attender', 'converted_member')
        
        UNION ALL
        
        SELECT 
            'Converted to Members' as stage,
            COUNT(*) as count,
            (COUNT(*) * 100.0 / (SELECT COUNT(*) FROM visitors WHERE visit_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH))) as percentage,
            4 as stage_order
        FROM visitors
        WHERE visit_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        AND status = 'converted_member'
        
        ORDER BY stage_order
    ";
    
    $funnel = $db->executeQuery($funnelQuery)->fetchAll();
    
    // Conversion rate by source
    $sourceConversionQuery = "
        SELECT 
            COALESCE(how_heard_about_us, 'Not specified') as source,
            COUNT(*) as total_visitors,
            COUNT(CASE WHEN status = 'converted_member' THEN 1 END) as converted,
            ROUND((COUNT(CASE WHEN status = 'converted_member' THEN 1 END) * 100.0 / COUNT(*)), 1) as conversion_rate
        FROM visitors
        WHERE visit_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY how_heard_about_us
        HAVING COUNT(*) >= 5
        ORDER BY conversion_rate DESC, total_visitors DESC
    ";
    
    $sourceConversion = $db->executeQuery($sourceConversionQuery)->fetchAll();
    
    return [
        'funnel_stages' => $funnel,
        'source_conversion' => $sourceConversion
    ];
}

/**
 * Get department performance metrics
 */
function getDepartmentPerformance($db) {
    $performanceQuery = "
        SELECT 
            d.name as department_name,
            d.department_type,
            d.target_size,
            COUNT(md.member_id) as current_size,
            CASE 
                WHEN d.target_size > 0 THEN ROUND((COUNT(md.member_id) * 100.0 / d.target_size), 1)
                ELSE NULL 
            END as target_achievement,
            COUNT(CASE WHEN m.join_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH) THEN 1 END) as new_members_6m,
            AVG(DATEDIFF(NOW(), m.join_date)) as avg_member_tenure_days
        FROM departments d
        LEFT JOIN member_departments md ON d.id = md.department_id AND md.is_active = 1
        LEFT JOIN members m ON md.member_id = m.id AND m.membership_status = 'active'
        WHERE d.is_active = 1
        GROUP BY d.id, d.name, d.department_type, d.target_size
        ORDER BY current_size DESC
    ";
    
    $performance = $db->executeQuery($performanceQuery)->fetchAll();
    
    return $performance;
}

/**
 * Get seasonal trends
 */
function getSeasonalTrends($db) {
    $seasonalQuery = "
        SELECT 
            MONTH(date_field) as month,
            MONTHNAME(STR_TO_DATE(MONTH(date_field), '%m')) as month_name,
            'Attendance' as metric,
            AVG(value) as avg_value
        FROM (
            SELECT 
                e.event_date as date_field,
                COUNT(ar.id) as value
            FROM events e
            LEFT JOIN attendance_records ar ON e.id = ar.event_id
            WHERE e.event_date >= DATE_SUB(NOW(), INTERVAL 24 MONTH)
            GROUP BY e.id, e.event_date
        ) attendance_data
        GROUP BY MONTH(date_field)
        
        UNION ALL
        
        SELECT 
            MONTH(transaction_date) as month,
            MONTHNAME(STR_TO_DATE(MONTH(transaction_date), '%m')) as month_name,
            'Income' as metric,
            AVG(amount) as avg_value
        FROM income
        WHERE transaction_date >= DATE_SUB(NOW(), INTERVAL 24 MONTH)
        AND status = 'verified'
        GROUP BY MONTH(transaction_date)
        
        UNION ALL
        
        SELECT 
            MONTH(visit_date) as month,
            MONTHNAME(STR_TO_DATE(MONTH(visit_date), '%m')) as month_name,
            'Visitors' as metric,
            COUNT(*) / 2 as avg_value
        FROM visitors
        WHERE visit_date >= DATE_SUB(NOW(), INTERVAL 24 MONTH)
        GROUP BY MONTH(visit_date)
        
        ORDER BY month, metric
    ";
    
    $seasonal = $db->executeQuery($seasonalQuery)->fetchAll();
    
    return $seasonal;
}

/**
 * Get predictive insights
 */
function getPredictiveInsights($db) {
    // Simple trend-based predictions
    $predictions = [];
    
    // Member growth prediction
    $memberGrowthQuery = "
        SELECT 
            COUNT(*) as new_members,
            DATE_FORMAT(join_date, '%Y-%m') as month
        FROM members
        WHERE join_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        AND membership_status = 'active'
        GROUP BY DATE_FORMAT(join_date, '%Y-%m')
        ORDER BY month DESC
        LIMIT 6
    ";
    
    $memberGrowth = $db->executeQuery($memberGrowthQuery)->fetchAll();
    
    if (count($memberGrowth) >= 3) {
        $avgGrowth = array_sum(array_column($memberGrowth, 'new_members')) / count($memberGrowth);
        $currentTotal = getRecordCount('members', ['membership_status' => 'active']);
        
        $predictions['member_growth'] = [
            'current_total' => $currentTotal,
            'avg_monthly_growth' => round($avgGrowth, 1),
            'predicted_6_months' => $currentTotal + ($avgGrowth * 6),
            'predicted_12_months' => $currentTotal + ($avgGrowth * 12)
        ];
    }
    
    // Financial trend prediction
    $financialTrendQuery = "
        SELECT 
            DATE_FORMAT(transaction_date, '%Y-%m') as month,
            SUM(amount) as monthly_income
        FROM income
        WHERE transaction_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        AND status = 'verified'
        GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
        ORDER BY month DESC
        LIMIT 6
    ";
    
    $financialTrend = $db->executeQuery($financialTrendQuery)->fetchAll();
    
    if (count($financialTrend) >= 3) {
        $avgIncome = array_sum(array_column($financialTrend, 'monthly_income')) / count($financialTrend);
        
        $predictions['financial_trend'] = [
            'avg_monthly_income' => $avgIncome,
            'predicted_quarterly' => $avgIncome * 3,
            'predicted_annual' => $avgIncome * 12
        ];
    }
    
    return $predictions;
}

include_once '../../includes/header.php';
?>

<!-- Analytics Dashboard -->
<div class="row">
    <!-- Analytics Overview Cards -->
    <div class="col-12 mb-4">
        <div class="row g-3">
            <div class="col-md-3">
                <div class="card bg-gradient-church text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title mb-1">Active Members</h6>
                                <h4 class="mb-0"><?php echo number_format(getRecordCount('members', ['membership_status' => 'active'])); ?></h4>
                            </div>
                            <i class="fas fa-users fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title mb-1">Engagement Score</h6>
                                <h4 class="mb-0"><?php echo !empty($analyticsData['engagement_metrics']['member_engagement']) ? round(array_sum(array_column($analyticsData['engagement_metrics']['member_engagement'], 'total_engagement_score')) / count($analyticsData['engagement_metrics']['member_engagement']), 1) : '0'; ?></h4>
                            </div>
                            <i class="fas fa-chart-line fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title mb-1">Conversion Rate</h6>
                                <h4 class="mb-0">
                                    <?php 
                                    if (!empty($analyticsData['visitor_funnel']['funnel_stages'])) {
                                        $stages = $analyticsData['visitor_funnel']['funnel_stages'];
                                        $converted = array_filter($stages, function($stage) { return $stage['stage'] === 'Converted to Members'; });
                                        echo !empty($converted) ? round(array_values($converted)[0]['percentage'], 1) . '%' : '0%';
                                    } else {
                                        echo '0%';
                                    }
                                    ?>
                                </h4>
                            </div>
                            <i class="fas fa-funnel-dollar fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title mb-1">Growth Trend</h6>
                                <h4 class="mb-0">
                                    <?php 
                                    if (!empty($analyticsData['predictions']['member_growth']['avg_monthly_growth'])) {
                                        echo '+' . $analyticsData['predictions']['member_growth']['avg_monthly_growth'] . '/mo';
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </h4>
                            </div>
                            <i class="fas fa-trending-up fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Member Growth Chart -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-chart-area me-2"></i>
                    Member Growth Trend
                </h6>
            </div>
            <div class="card-body">
                <?php if (!empty($analyticsData['member_growth']['growth_trend'])): ?>
                    <canvas id="memberGrowthChart" height="100"></canvas>
                <?php else: ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-chart-area fa-3x mb-3"></i>
                        <p>No growth data available</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Engagement Metrics -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-users-cog me-2"></i>
                    Top Engaged Members
                </h6>
            </div>
            <div class="card-body">
                <?php if (!empty($analyticsData['engagement_metrics']['member_engagement'])): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach (array_slice($analyticsData['engagement_metrics']['member_engagement'], 0, 8) as $member): ?>
                            <div class="list-group-item border-0 px-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></strong>
                                        <br><small class="text-muted">Joined <?php echo date('M Y', strtotime($member['join_date'])); ?></small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-primary"><?php echo round($member['total_engagement_score'], 1); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-3">
                        <i class="fas fa-users fa-2x mb-2"></i>
                        <p>No engagement data available</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Financial Insights -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    Financial Trends (12 Months)
                </h6>
            </div>
            <div class="card-body">
                <?php if (!empty($analyticsData['financial_insights']['trends'])): ?>
                    <canvas id="financialTrendsChart" height="100"></canvas>
                <?php else: ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-chart-bar fa-3x mb-3"></i>
                        <p>No financial data available</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Visitor Conversion Funnel -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-filter me-2"></i>
                    Visitor Conversion Funnel
                </h6>
            </div>
            <div class="card-body">
                <?php if (!empty($analyticsData['visitor_funnel']['funnel_stages'])): ?>
                    <div class="funnel-chart">
                        <?php foreach ($analyticsData['visitor_funnel']['funnel_stages'] as $stage): ?>
                            <div class="funnel-stage mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <strong><?php echo htmlspecialchars($stage['stage']); ?></strong>
                                    <span><?php echo number_format($stage['count']); ?> (<?php echo round($stage['percentage'], 1); ?>%)</span>
                                </div>
                                <div class="progress" style="height: 25px;">
                                    <div class="progress-bar bg-church-blue" role="progressbar" 
                                         style="width: <?php echo $stage['percentage']; ?>%">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-filter fa-3x mb-3"></i>
                        <p>No visitor data available</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Department Performance -->
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-sitemap me-2"></i>
                    Department Performance Analysis
                </h6>
            </div>
            <div class="card-body">
                <?php if (!empty($analyticsData['department_performance'])): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Department</th>
                                    <th>Type</th>
                                    <th>Members</th>
                                    <th>Target</th>
                                    <th>Achievement</th>
                                    <th>New Members (6m)</th>
                                    <th>Avg Tenure</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($analyticsData['department_performance'] as $dept): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($dept['department_name']); ?></strong></td>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo ucwords(str_replace('_', ' ', $dept['department_type'])); ?></span>
                                        </td>
                                        <td><?php echo number_format($dept['current_size']); ?></td>
                                        <td><?php echo $dept['target_size'] ? number_format($dept['target_size']) : '-'; ?></td>
                                        <td>
                                            <?php if ($dept['target_achievement']): ?>
                                                <div class="progress" style="width: 100px; height: 20px;">
                                                    <div class="progress-bar <?php echo $dept['target_achievement'] >= 100 ? 'bg-success' : ($dept['target_achievement'] >= 75 ? 'bg-warning' : 'bg-danger'); ?>" 
                                                         style="width: <?php echo min(100, $dept['target_achievement']); ?>%">
                                                        <?php echo round($dept['target_achievement'], 0); ?>%
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo number_format($dept['new_members_6m']); ?></span>
                                        </td>
                                        <td>
                                            <?php echo $dept['avg_member_tenure_days'] ? round($dept['avg_member_tenure_days'] / 365, 1) . ' years' : '-'; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-sitemap fa-3x mb-3"></i>
                        <p>No department data available</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Predictions Panel -->
    <?php if (!empty($analyticsData['predictions'])): ?>
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-crystal-ball me-2"></i>
                    Predictive Insights
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php if (!empty($analyticsData['predictions']['member_growth'])): ?>
                    <div class="col-md-6">
                        <div class="prediction-card p-3 border rounded">
                            <h6 class="text-church-blue">Member Growth Projection</h6>
                            <div class="row text-center">
                                <div class="col-6">
                                    <h4><?php echo number_format($analyticsData['predictions']['member_growth']['predicted_6_months']); ?></h4>
                                    <small class="text-muted">6 Months</small>
                                </div>
                                <div class="col-6">
                                    <h4><?php echo number_format($analyticsData['predictions']['member_growth']['predicted_12_months']); ?></h4>
                                    <small class="text-muted">12 Months</small>
                                </div>
                            </div>
                            <p class="mb-0 mt-2 small text-muted">
                                Based on average monthly growth of <?php echo $analyticsData['predictions']['member_growth']['avg_monthly_growth']; ?> members
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($analyticsData['predictions']['financial_trend'])): ?>
                    <div class="col-md-6">
                        <div class="prediction-card p-3 border rounded">
                            <h6 class="text-church-blue">Financial Projection</h6>
                            <div class="row text-center">
                                <div class="col-6">
                                    <h4><?php echo formatCurrency($analyticsData['predictions']['financial_trend']['predicted_quarterly']); ?></h4>
                                    <small class="text-muted">Quarterly</small>
                                </div>
                                <div class="col-6">
                                    <h4><?php echo formatCurrency($analyticsData['predictions']['financial_trend']['predicted_annual']); ?></h4>
                                    <small class="text-muted">Annual</small>
                                </div>
                            </div>
                            <p class="mb-0 mt-2 small text-muted">
                                Based on average monthly income of <?php echo formatCurrency($analyticsData['predictions']['financial_trend']['avg_monthly_income']); ?>
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- JavaScript for Charts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    <?php if (!empty($analyticsData['member_growth']['growth_trend'])): ?>
    // Member Growth Chart
    const memberGrowthCtx = document.getElementById('memberGrowthChart');
    if (memberGrowthCtx) {
        const growthData = <?php echo json_encode(array_column($analyticsData['member_growth']['growth_trend'], 'new_members')); ?>;
        const growthLabels = <?php echo json_encode(array_column($analyticsData['member_growth']['growth_trend'], 'month_name')); ?>;
        const cumulativeData = <?php echo json_encode(array_column($analyticsData['member_growth']['growth_trend'], 'cumulative_members')); ?>;
        
        new Chart(memberGrowthCtx, {
            type: 'line',
            data: {
                labels: growthLabels,
                datasets: [
                    {
                        label: 'New Members',
                        data: growthData,
                        borderColor: '#ff2400',
                        backgroundColor: 'rgba(255, 36, 0, 0.1)',
                        borderWidth: 3,
                        fill: false,
                        tension: 0.4,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Total Members',
                        data: cumulativeData,
                        borderColor: '#03045e',
                        backgroundColor: 'rgba(3, 4, 94, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'New Members'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Total Members'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });
    }
    <?php endif; ?>

    <?php if (!empty($analyticsData['financial_insights']['trends'])): ?>
    // Financial Trends Chart
    const financialCtx = document.getElementById('financialTrendsChart');
    if (financialCtx) {
        // Process data for chart
        const processedData = {};
        <?php foreach ($analyticsData['financial_insights']['trends'] as $trend): ?>
            if (!processedData['<?php echo $trend['month']; ?>']) {
                processedData['<?php echo $trend['month']; ?>'] = {
                    month: '<?php echo $trend['month_name']; ?>',
                    income: 0,
                    expenses: 0
                };
            }
            processedData['<?php echo $trend['month']; ?>']['<?php echo strtolower($trend['type']); ?>'] = <?php echo $trend['total']; ?>;
        <?php endforeach; ?>
        
        const months = Object.values(processedData).map(item => item.month);
        const incomeData = Object.values(processedData).map(item => item.income);
        const expenseData = Object.values(processedData).map(item => item.expenses);
        
        new Chart(financialCtx, {
            type: 'bar',
            data: {
                labels: months,
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
    <?php endif; ?>
});

// Auto-refresh analytics data every 5 minutes
setInterval(function() {
    // Check if user is still on the page
    if (document.visibilityState === 'visible') {
        fetch('ajax/refresh_analytics.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update key metrics without full page reload
                    console.log('Analytics data refreshed');
                }
            })
            .catch(error => {
                console.error('Error refreshing analytics:', error);
            });
    }
}, 300000); // 5 minutes

// Export analytics data
function exportAnalytics(format) {
    ChurchCMS.showConfirm(
        `Export analytics data as ${format.toUpperCase()}?`,
        function() {
            window.location.href = `export.php?type=analytics&format=${format}`;
        }
    );
}

// Print analytics report
function printAnalytics() {
    window.print();
}
</script>

<!-- Custom Styles for Analytics -->
<style>
.prediction-card {
    background: linear-gradient(135deg, rgba(3, 4, 94, 0.05) 0%, rgba(255, 36, 0, 0.05) 100%);
    border: 1px solid rgba(3, 4, 94, 0.1) !important;
}

.funnel-stage {
    position: relative;
}

.funnel-chart {
    max-height: 350px;
    overflow-y: auto;
}

.engagement-score {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    background: linear-gradient(45deg, var(--church-blue), var(--church-red));
    color: white;
    border-radius: 1rem;
    font-size: 0.75rem;
    font-weight: 500;
}

@media (max-width: 768px) {
    .analytics-card {
        margin-bottom: 1rem;
    }
    
    .prediction-card {
        margin-bottom: 1rem;
    }
    
    .table-responsive {
        font-size: 0.875rem;
    }
}

/* Print styles for analytics */
@media print {
    .no-print {
        display: none !important;
    }
    
    .card {
        border: 1px solid #000 !important;
        box-shadow: none !important;
        break-inside: avoid;
        margin-bottom: 1rem;
    }
    
    .bg-gradient-church,
    .bg-success,
    .bg-warning,
    .bg-info {
        background: #f0f0f0 !important;
        color: #000 !important;
    }
    
    canvas {
        max-height: 200px !important;
    }
}
</style>

<?php include_once '../../includes/footer.php'; ?><?php
/**
 * Advanced Analytics Dashboard
 * Deliverance Church Management System
 * 
 * Provides advanced analytics and insights across all church data
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
$page_title = 'Advanced Analytics';
$page_icon = 'fas fa-chart-pie';
$breadcrumb = [
    ['title' => 'Reports', 'url' => 'index.php'],
    ['title' => 'Advanced Analytics']
];

// Initialize database
$db = Database::getInstance();

try {
    // Get comprehensive analytics data
    $analyticsData = getAdvancedAnalytics($db);
    
} catch (Exception $e) {
    error_log("Error loading analytics: " . $e->getMessage());
    setFlashMessage('error', 'Error loading analytics: ' . $e->getMessage());
    $analyticsData = [];
}

/**
 * Get advanced analytics data
 */
function getAdvancedAnalytics($db) {
    $data = [];
    
    // Member growth analysis
    $data['member_growth'] = getMemberGrowthAnalytics($db);
    
    // Attendance patterns
    $data['attendance_patterns'] = getAttendancePatterns($db);
    
    // Financial insights
    $data['financial_insights'] = getFinancialInsights($db);
    
    // Engagement metrics
    $data['engagement_metrics'] = getEngagementMetrics($db);
    
    // Visitor conversion funnel
    $data['visitor_funnel'] = getVisitorConversionFunnel($db);
    
    // Department performance
    $data['department_performance'] = getDepartmentPerformance($db);
    
    // Seasonal trends
    $data['seasonal_trends'] = getSeasonalTrends($db);
    
    // Predictive insights
    $data['predictions'] = getPredictiveInsights($db);
    
    return $data;
}

/**
 * Get member growth analytics
 */
function getMemberGrowthAnalytics($db) {
    // Monthly member growth for the last 24 months
    $growthQuery = "
        SELECT 
            DATE_FORMAT(join_date, '%Y-%m') as month,
            DATE_FORMAT(join_date, '%M %Y') as month_name,
            COUNT(*) as new_members,
            SUM(COUNT(*)) OVER (ORDER BY DATE_FORMAT(join_date, '%Y-%m')) as cumulative_members
        FROM members
        WHERE join_date >= DATE_SUB(NOW(), INTERVAL 24 MONTH)
        AND membership_status = 'active'
        GROUP BY DATE_FORMAT(join_date, '%Y-%m')
        ORDER BY month ASC
    ";
    
    $growth = $db->executeQuery($growthQuery)->fetchAll();
    
    // Member retention analysis
    $retentionQuery = "
        SELECT 
            YEAR(join_date) as join_year,
            COUNT(*) as joined,
            COUNT(CASE WHEN membership_status = 'active' THEN 1 END) as still_active,
            ROUND((COUNT(CASE WHEN membership_status = 'active' THEN 1 END) / COUNT(*)) * 100, 1) as retention_rate
        FROM members
        WHERE join_date >= DATE_SUB(NOW(), INTERVAL 5 YEAR)
        GROUP BY YEAR(join_date)
        ORDER BY join_year DESC
    ";
    
    $retention = $db->executeQuery($retentionQuery)->fetchAll();
    
    // Age distribution trends
    $ageDistributionQuery = "
        SELECT 
            CASE 
                WHEN age <= 12 THEN 'Children (0-12)'
                WHEN age <= 17 THEN 'Teens (13-17)'
                WHEN age <= 35 THEN 'Youth (18-35)'
                WHEN age <= 59 THEN 'Adults (36-59)'
                ELSE 'Seniors (60+)'
            END as age_group,
            COUNT(*) as count,
            ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM members WHERE membership_status = 'active')), 1) as percentage
        FROM members
        WHERE membership_status = 'active'
        AND age IS NOT NULL
        GROUP BY age_group
        ORDER BY 
            CASE 
                WHEN age <= 12 THEN 1
                WHEN age <= 17 THEN 2
                WHEN age <= 35 THEN 3
                WHEN age <= 59 THEN 4
                ELSE 5
            END
    ";
    
    $ageDistribution = $db->executeQuery($ageDistributionQuery)->fetchAll();
    
    return [
        'growth_trend' => $growth,
        'retention_rates' => $retention,
        'age_distribution' => $ageDistribution
    ];
}

/**
 * Get attendance patterns
 */
function getAttendancePatterns($db) {
    // Weekly attendance patterns
    $weeklyPatternsQuery = "
        SELECT 
            DAYOFWEEK(e.event_date) as day_of_week,
            DAYNAME(e.event_date) as day_name,
            e.event_type,
            AVG(attendance_count.count) as avg_attendance,
            COUNT(DISTINCT e.id) as event_count
        FROM events e
        JOIN (
            SELECT event_id, COUNT(*) as count
            FROM attendance_records
            GROUP BY event_id
        ) attendance_count ON e.id = attendance_count.event_id
        WHERE e.event_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DAYOFWEEK(e.event_date), e.event_type
        ORDER BY day_of_week, e.event_type
    ";
    
    $weeklyPatterns = $db->executeQuery($weeklyPatternsQuery)->fetchAll();
    
    // Monthly attendance trends
    $monthlyTrendsQuery = "
        SELECT 
            DATE_FORMAT(e.event_date, '%Y-%m') as month,
            DATE_FORMAT(e.event_date, '%M %Y') as month_name,
            e.event_type,
            AVG(attendance_count.count) as avg_attendance,
            COUNT(DISTINCT e.id) as event_count
        FROM events e
        JOIN (
            SELECT event_id, COUNT(*) as count
            FROM attendance_records
            GROUP BY event_id
        ) attendance_count ON e.id = attendance_count.event_id
        WHERE e.event_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(e.event_date, '%Y-%m'), e.event_type
        ORDER BY month, e.event_type
    ";
    
    $monthlyTrends = $db->executeQuery($monthlyTrendsQuery)->fetchAll();
    
    // Individual attendance patterns
    $individualPatternsQuery = "
        SELECT 
            'Regular Attenders' as category,
            COUNT(*) as count
        FROM (
            SELECT member_id
            FROM attendance_records ar
            JOIN events e ON ar.event_id = e.id
            WHERE e.event_date >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
            GROUP BY member_id
            HAVING COUNT(*) >= 8
        ) regular_attenders
        
        UNION ALL
        
        SELECT 
            'Occasional Attenders' as category,
            COUNT(*) as count
        FROM (
            SELECT member_id
            FROM attendance_records ar
            JOIN events e ON ar.event_id = e.id
            WHERE e.event_date >= DATE_SUB(NOW(), INTERVAL 3 MONTH)     
            GROUP BY member_id
            HAVING COUNT(*) BETWEEN 3 AND 7
        ) occasional_attenders
    ";
    
    $individualPatterns = $db->executeQuery($individualPatternsQuery)->fetchAll();
    
    return [
        'weekly_patterns' => $weeklyPatterns,
        'monthly_trends' => $monthlyTrends,
        'individual_patterns' => $individualPatterns
    ];
}
/**
 * Get financial insights
 */
function getFinancialInsights($db) {
    // Revenue trends
    $revenueTrendsQuery = "
        SELECT 
            DATE_FORMAT(donation_date, '%Y-%m') as month,
            SUM(amount) as total_revenue
        FROM donations
        WHERE donation_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY month
        ORDER BY month
    ";
    $revenueTrends = $db->executeQuery($revenueTrendsQuery)->fetchAll();

    // Top donors
    $topDonorsQuery = "
        SELECT 
            d.id,
            d.name,
            SUM(dr.amount) as total_donated
        FROM donors d
        JOIN donation_records dr ON d.id = dr.donor_id
        GROUP BY d.id
        ORDER BY total_donated DESC
        LIMIT 10
    ";
    $topDonors = $db->executeQuery($topDonorsQuery)->fetchAll();

    // Donation categories
    $donationCategoriesQuery = "
        SELECT 
            c.name as category,
            SUM(dr.amount) as total_donated
        FROM donation_categories c
        JOIN donation_records dr ON c.id = dr.category_id
        GROUP BY c.id
        ORDER BY total_donated DESC
    ";
    $donationCategories = $db->executeQuery($donationCategoriesQuery)->fetchAll();

    return [
        'revenue_trends' => $revenueTrends,
        'top_donors' => $topDonors,
        'donation_categories' => $donationCategories
    ];
}
/**
 * Get engagement metrics
 */
function getEngagementMetrics($db) {
    // Event participation
    $eventParticipationQuery = "
        SELECT 
            e.id,
            e.name,
            COUNT(ar.id) as total_attendance
        FROM events e
        LEFT JOIN attendance_records ar ON e.id = ar.event_id
        GROUP BY e.id
        ORDER BY total_attendance DESC
    ";
    $eventParticipation = $db->executeQuery($eventParticipationQuery)->fetchAll();

    // Member engagement
    $memberEngagementQuery = "
        SELECT 
            m.id,
            m.first_name,
            m.last_name,
            COUNT(ar.id) as total_attendance
        FROM members m
        LEFT JOIN attendance_records ar ON m.id = ar.member_id
        GROUP BY m.id
        ORDER BY total_attendance DESC
    ";
    $memberEngagement = $db->executeQuery($memberEngagementQuery)->fetchAll();

    return [
        'event_participation' => $eventParticipation,
        'member_engagement' => $memberEngagement
    ];
}   
/**
 * Export member data to CSV
 */
function exportMembersToCSV($db, $filename) {
    $query = "SELECT * FROM members ORDER BY last_name, first_name";
    $members = $db->executeQuery($query)->fetchAll();
    
    $file = fopen($filename, 'w');
    
    // Write headers
    fputcsv($file, [
        'Member Number', 'First Name', 'Last Name', 'Middle Name', 'Date of Birth', 'Age',
        'Gender', 'Marital Status', 'Phone', 'Email', 'Address', 'Emergency Contact Name',
        'Emergency Contact Phone', 'Join Date', 'Baptism Date', 'Status', 'Occupation',
        'Skills', 'Departments'
    ]);
    
    // Write data
    foreach ($members as $member) {
        fputcsv($file, [
            $member['member_number'],
            $member['first_name'],
            $member['last_name'],
            $member['middle_name'],
            $member['date_of_birth'],
            $member['age'],
            $member['gender'],
            $member['marital_status'],
            $member['phone'],
            $member['email'],
            $member['address'],
            $member['emergency_contact_name'],
            $member['emergency_contact_phone'],
            $member['join_date'],
            $member['baptism_date'],
            $member['membership_status'],
            $member['occupation'],
            $member['skills'],
            $member['departments']
        ]);
    }
    
    fclose($file);
}
/**
 * Export attendance data to CSV
 */ function exportAttendanceToCSV($db, $filename) {
    $query = "
        SELECT 
            e.name as event_name,
            e.event_type,
            e.event_date,
            e.start_time,
            m.member_number,
            m.first_name,
            m.last_name,
            ar.check_in_time,
            ar.check_in_method
        FROM attendance_records ar
        JOIN events e ON ar.event_id = e.id
        LEFT JOIN members m ON ar.member_id = m.id
        ORDER BY e.event_date DESC, ar.check_in_time DESC
    ";
    
    $attendanceRecords = $db->executeQuery($query)->fetchAll();
    
    $file = fopen($filename, 'w');
    
    // Write headers
    fputcsv($file, [
        'Event Name', 'Event Type', 'Event Date', 'Start Time',
        'Member Number', 'First Name', 'Last Name',
        'Check-in Time', 'Check-in Method'
    ]);
    
    // Write data
    foreach ($attendanceRecords as $record) {
        fputcsv($file, [
            $record['event_name'],
            $record['event_type'],
            $record['event_date'],
            $record['start_time'],
            $record['member_number'],
            $record['first_name'],
            $record['last_name'],
            $record['check_in_time'],
            $record['check_in_method']
        ]);
    }
    
    fclose($file);  
}
/** Export financial data to CSV
 */function exportFinancialDataToCSV($db, $filename) {
    $query = "SELECT * FROM financial_records ORDER BY date DESC";
    $financialRecords = $db->executeQuery($query)->fetchAll();

    $file = fopen($filename, 'w');

    // Write headers
    fputcsv($file, [
        'Date', 'Description', 'Amount', 'Category', 'Payment Method'
    ]);

    // Write data
    foreach ($financialRecords as $record) {
        fputcsv($file, [
            $record['date'],
            $record['description'],
            $record['amount'],
            $record['category'],
            $record['payment_method']
        ]);
    }

    fclose($file);
}
