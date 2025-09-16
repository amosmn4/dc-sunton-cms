FROM visitors v
        LEFT JOIN members m ON v.assigned_followup_person_id = m.id
        LEFT JOIN visitor_followups vf ON v.id = vf.visitor_id
        WHERE v.visit_date BETWEEN ? AND ?
        AND v.status IN ('new_visitor', 'follow_up', 'regular_attender')
        GROUP BY v.id
        ORDER BY 
            CASE 
                WHEN MAX(vf.followup_date) IS NULL THEN 1
                WHEN DATEDIFF(CURDATE(), MAX(vf.followup_date)) > 14 THEN 2
                WHEN DATEDIFF(CURDATE(), MAX(vf.followup_date)) > 7 THEN 3
                ELSE 4
            END,
            v.visit_date DESC
    ";
    
    $followupData = $db->executeQuery($query, [$dateFrom, $dateTo])->fetchAll();
    
    // Summary by follow-up status
    $statusSummary = [
        'no_followup' => 0,
        'overdue' => 0,
        'due_soon' => 0,
        'up_to_date' => 0
    ];
    
    foreach ($followupData as $visitor) {
        switch ($visitor['followup_status']) {
            case 'No follow-up':
                $statusSummary['no_followup']++;
                break;
            case 'Overdue':
                $statusSummary['overdue']++;
                break;
            case 'Due soon':
                $statusSummary['due_soon']++;
                break;
            case 'Up to date':
                $statusSummary['up_to_date']++;
                break;
        }
    }
    
    return [
        'followup_data' => $followupData,
        'status_summary' => $statusSummary,
        'total_visitors' => count($followupData)
    ];
}

/**
 * Get conversion analysis data
 */
function getConversionAnalysisData($db, $dateFrom, $dateTo, $filters) {
    // Conversion funnel analysis
    $conversionQuery = "
        SELECT 
            'New Visitors' as stage,
            COUNT(*) as count,
            100.0 as percentage
        FROM visitors v
        WHERE v.visit_date BETWEEN ? AND ?
        
        UNION ALL
        
        SELECT 
            'In Follow-up' as stage,
            COUNT(*) as count,
            (COUNT(*) * 100.0 / (SELECT COUNT(*) FROM visitors WHERE visit_date BETWEEN ? AND ?)) as percentage
        FROM visitors v
        WHERE v.visit_date BETWEEN ? AND ?
        AND v.status IN ('follow_up', 'regular_attender', 'converted_member')
        
        UNION ALL
        
        SELECT 
            'Regular Attenders' as stage,
            COUNT(*) as count,
            (COUNT(*) * 100.0 / (SELECT COUNT(*) FROM visitors WHERE visit_date BETWEEN ? AND ?)) as percentage
        FROM visitors v
        WHERE v.visit_date BETWEEN ? AND ?
        AND v.status IN ('regular_attender', 'converted_member')
        
        UNION ALL
        
        SELECT 
            'Converted Members' as stage,
            COUNT(*) as count,
            (COUNT(*) * 100.0 / (SELECT COUNT(*) FROM visitors WHERE visit_date BETWEEN ? AND ?)) as percentage
        FROM visitors v
        WHERE v.visit_date BETWEEN ? AND ?
        AND v.status = 'converted_member'
    ";
    
    $params = array_fill(0, 12, $dateFrom);
    $params = array_merge($params, array_fill(0, 12, $dateTo));
    
    $conversionFunnel = $db->executeQuery($conversionQuery, $params)->fetchAll();
    
    // Monthly conversion rates
    $monthlyQuery = "
        SELECT 
            DATE_FORMAT(v.visit_date, '%Y-%m') as month,
            DATE_FORMAT(v.visit_date, '%M %Y') as month_name,
            COUNT(*) as total_visitors,
            COUNT(CASE WHEN v.status = 'converted_member' THEN 1 END) as converted,
            ROUND(
                (COUNT(CASE WHEN v.status = 'converted_member' THEN 1 END) * 100.0 / COUNT(*)), 
                2
            ) as conversion_rate
        FROM visitors v
        WHERE v.visit_date BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(v.visit_date, '%Y-%m')
        ORDER BY month ASC
    ";
    
    $monthlyConversions = $db->executeQuery($monthlyQuery, [$dateFrom, $dateTo])->fetchAll();
    
    // Conversion by source
    $sourceConversionQuery = "
        SELECT 
            COALESCE(v.how_heard_about_us, 'Not specified') as source,
            COUNT(*) as total_visitors,
            COUNT(CASE WHEN v.status = 'converted_member' THEN 1 END) as converted,
            ROUND(
                (COUNT(CASE WHEN v.status = 'converted_member' THEN 1 END) * 100.0 / COUNT(*)), 
                2
            ) as conversion_rate
        FROM visitors v
        WHERE v.visit_date BETWEEN ? AND ?
        GROUP BY v.how_heard_about_us
        HAVING COUNT(*) >= 3
        ORDER BY conversion_rate DESC
    ";
    
    $sourceConversions = $db->executeQuery($sourceConversionQuery, [$dateFrom, $dateTo])->fetchAll();
    
    return [
        'conversion_funnel' => $conversionFunnel,
        'monthly_conversions' => $monthlyConversions,
        'source_conversions' => $sourceConversions
    ];
}

/**
 * Get visitor trends data
 */
function getVisitorTrendsData($db, $dateFrom, $dateTo, $filters) {
    // Daily visitor counts
    $dailyQuery = "
        SELECT 
            v.visit_date as date,
            COUNT(*) as visitor_count,
            COUNT(CASE WHEN v.gender = 'male' THEN 1 END) as male_count,
            COUNT(CASE WHEN v.gender = 'female' THEN 1 END) as female_count
        FROM visitors v
        WHERE v.visit_date BETWEEN ? AND ?
        GROUP BY v.visit_date
        ORDER BY v.visit_date ASC
    ";
    
    $dailyTrends = $db->executeQuery($dailyQuery, [$dateFrom, $dateTo])->fetchAll();
    
    // Day of week analysis
    $dayOfWeekQuery = "
        SELECT 
            DAYNAME(v.visit_date) as day_name,
            DAYOFWEEK(v.visit_date) as day_number,
            COUNT(*) as visitor_count,
            AVG(COUNT(*)) OVER() as average_count
        FROM visitors v
        WHERE v.visit_date BETWEEN ? AND ?
        GROUP BY DAYOFWEEK(v.visit_date), DAYNAME(v.visit_date)
        ORDER BY day_number
    ";
    
    $dayOfWeekTrends = $db->executeQuery($dayOfWeekQuery, [$dateFrom, $dateTo])->fetchAll();
    
    // Age group trends
    $ageGroupQuery = "
        SELECT 
            v.age_group,
            COUNT(*) as visitor_count,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM visitors WHERE visit_date BETWEEN ? AND ?), 1) as percentage
        FROM visitors v
        WHERE v.visit_date BETWEEN ? AND ?
        GROUP BY v.age_group
        ORDER BY visitor_count DESC
    ";
    
    $ageGroupTrends = $db->executeQuery($ageGroupQuery, [$dateFrom, $dateTo, $dateFrom, $dateTo])->fetchAll();
    
    return [
        'daily_trends' => $dailyTrends,
        'day_of_week_trends' => $dayOfWeekTrends,
        'age_group_trends' => $ageGroupTrends
    ];
}

include_once '../../includes/header.php';
?>

<!-- Visitor Reports Content -->
<div class="row">
    <!-- Report Filters -->
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="fas fa-filter me-2"></i>
                        Report Filters
                    </h6>
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                        <i class="fas fa-chevron-down"></i>
                    </button>
                </div>
            </div>
            <div class="collapse show" id="filterCollapse">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3">
                        <!-- Report Type -->
                        <div class="col-md-3">
                            <label class="form-label">Report Type</label>
                            <select name="type" class="form-select" onchange="this.form.submit()">
                                <option value="summary" <?php echo $reportType === 'summary' ? 'selected' : ''; ?>>Visitor Summary</option>
                                <option value="detailed" <?php echo $reportType === 'detailed' ? 'selected' : ''; ?>>Detailed Report</option>
                                <option value="followup" <?php echo $reportType === 'followup' ? 'selected' : ''; ?>>Follow-up Report</option>
                                <option value="conversion" <?php echo $reportType === 'conversion' ? 'selected' : ''; ?>>Conversion Analysis</option>
                                <option value="trends" <?php echo $reportType === 'trends' ? 'selected' : ''; ?>>Trends Analysis</option>
                            </select>
                        </div>

                        <!-- Period Selection -->
                        <div class="col-md-3">
                            <label class="form-label">Period</label>
                            <select name="period" class="form-select" onchange="toggleCustomDates()">
                                <option value="this_week" <?php echo $period === 'this_week' ? 'selected' : ''; ?>>This Week</option>
                                <option value="this_month" <?php echo $period === 'this_month' ? 'selected' : ''; ?>>This Month</option>
                                <option value="this_quarter" <?php echo $period === 'this_quarter' ? 'selected' : ''; ?>>This Quarter</option>
                                <option value="this_year" <?php echo $period === 'this_year' ? 'selected' : ''; ?>>This Year</option>
                                <option value="last_month" <?php echo $period === 'last_month' ? 'selected' : ''; ?>>Last Month</option>
                                <option value="custom" <?php echo $period === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                            </select>
                        </div>

                        <!-- Custom Date Range -->
                        <div class="col-md-3" id="customDates" style="<?php echo $period === 'custom' ? '' : 'display: none;'; ?>">
                            <label class="form-label">From Date</label>
                            <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($filters['date_from']); ?>">
                        </div>

                        <div class="col-md-3" id="customDatesTo" style="<?php echo $period === 'custom' ? '' : 'display: none;'; ?>">
                            <label class="form-label">To Date</label>
                            <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($filters['date_to']); ?>">
                        </div>

                        <!-- Status Filter -->
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="">All Statuses</option>
                                <option value="new_visitor" <?php echo $filters['status'] === 'new_visitor' ? 'selected' : ''; ?>>New Visitor</option>
                                <option value="follow_up" <?php echo $filters['status'] === 'follow_up' ? 'selected' : ''; ?>>In Follow-up</option>
                                <option value="regular_attender" <?php echo $filters['status'] === 'regular_attender' ? 'selected' : ''; ?>>Regular Attender</option>
                                <option value="converted_member" <?php echo $filters['status'] === 'converted_member' ? 'selected' : ''; ?>>Converted Member</option>
                            </select>
                        </div>

                        <!-- Gender Filter -->
                        <div class="col-md-3">
                            <label class="form-label">Gender</label>
                            <select name="gender" class="form-select">
                                <option value="">All Genders</option>
                                <option value="male" <?php echo $filters['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo $filters['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>

                        <!-- Age Group Filter -->
                        <div class="col-md-3">
                            <label class="form-label">Age Group</label>
                            <select name="age_group" class="form-select">
                                <option value="">All Ages</option>
                                <option value="child" <?php echo $filters['age_group'] === 'child' ? 'selected' : ''; ?>>Child</option>
                                <option value="youth" <?php echo $filters['age_group'] === 'youth' ? 'selected' : ''; ?>>Youth</option>
                                <option value="adult" <?php echo $filters['age_group'] === 'adult' ? 'selected' : ''; ?>>Adult</option>
                                <option value="senior" <?php echo $filters['age_group'] === 'senior' ? 'selected' : ''; ?>>Senior</option>
                            </select>
                        </div>

                        <!-- Search -->
                        <div class="col-md-3">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" placeholder="Name or phone" value="<?php echo htmlspecialchars($filters['search']); ?>">
                        </div>

                        <!-- Action Buttons -->
                        <div class="col-12">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-church-primary">
                                    <i class="fas fa-search me-2"></i>Generate Report
                                </button>
                                <a href="?" class="btn btn-outline-secondary">
                                    <i class="fas fa-undo me-2"></i>Reset Filters
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Results -->
    <div class="col-12">
        <?php if ($reportType === 'summary'): ?>
            <!-- Summary Report -->
            <div class="row">
                <!-- Summary Cards -->
                <div class="col-md-3 mb-4">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-1">Total Visitors</h6>
                                    <h4 class="mb-0"><?php echo number_format($reportData['summary']['total_visitors'] ?? 0); ?></h4>
                                </div>
                                <i class="fas fa-users fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 mb-4">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-1">Converted</h6>
                                    <h4 class="mb-0"><?php echo number_format($reportData['summary']['converted_members'] ?? 0); ?></h4>
                                </div>
                                <i class="fas fa-user-check fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 mb-4">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-1">Need Follow-up</h6>
                                    <h4 class="mb-0"><?php echo number_format(count($reportData['pending_followup'] ?? [])); ?></h4>
                                </div>
                                <i class="fas fa-phone fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 mb-4">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-1">Regular Attenders</h6>
                                    <h4 class="mb-0"><?php echo number_format($reportData['summary']['regular_attenders'] ?? 0); ?></h4>
                                </div>
                                <i class="fas fa-calendar-check fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts and Analysis -->
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-chart-pie me-2"></i>
                                Visitors by Source
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($reportData['sources'])): ?>
                                <canvas id="sourceChart" height="300"></canvas>
                            <?php else: ?>
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-chart-pie fa-3x mb-3"></i>
                                    <p>No data available for the selected period</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-chart-line me-2"></i>
                                Visitor Trends
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($reportData['trends'])): ?>
                                <canvas id="trendsChart" height="300"></canvas>
                            <?php else: ?>
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-chart-line fa-3x mb-3"></i>
                                    <p>No trend data available</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Follow-up -->
            <?php if (!empty($reportData['pending_followup'])): ?>
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-phone me-2"></i>
                            Visitors Needing Follow-up
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Visit Date</th>
                                        <th>Status</th>
                                        <th>Follow-ups</th>
                                        <th>Last Follow-up</th>
                                        <th>Days Since Visit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportData['pending_followup'] as $visitor): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($visitor['first_name'] . ' ' . $visitor['last_name']); ?></strong>
                                            </td>
                                            <td><?php echo formatDisplayDate($visitor['visit_date']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $visitor['status'] === 'new_visitor' ? 'warning' : 'info'; ?>">
                                                    <?php echo ucwords(str_replace('_', ' ', $visitor['status'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $visitor['followup_count']; ?></td>
                                            <td><?php echo $visitor['last_followup'] ? formatDisplayDate($visitor['last_followup']) : 'Never'; ?></td>
                                            <td>
                                                <?php 
                                                $daysSinceVisit = (new DateTime())->diff(new DateTime($visitor['visit_date']))->days;
                                                echo $daysSinceVisit . ' days';
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        <?php elseif ($reportType === 'detailed'): ?>
            <!-- Detailed Report -->
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-table me-2"></i>
                            <?php echo htmlspecialchars($reportTitle); ?>
                            <span class="badge bg-primary ms-2"><?php echo $reportData['total_count'] ?? 0; ?> records</span>
                        </h5>
                        
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-success dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="fas fa-download me-2"></i>Export
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['export' => '1', 'format' => 'csv'])); ?>">
                                    <i class="fas fa-file-csv me-2"></i>Export as CSV
                                </a></li>
                                <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['export' => '1', 'format' => 'excel'])); ?>">
                                    <i class="fas fa-file-excel me-2"></i>Export as Excel
                                </a></li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="card-body">
                    <?php if (empty($reportData['visitors'] ?? [])): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-search fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No Visitors Found</h5>
                            <p class="text-muted">No visitors match your current filter criteria.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover data-table">
                                <thead>
                                    <tr>
                                        <th>Visitor #</th>
                                        <th>Name</th>
                                        <th>Contact</th>
                                        <th>Age/Gender</th>
                                        <th>Visit Date</th>
                                        <th>Status</th>
                                        <th>Source</th>
                                        <th>Follow-ups</th>
                                        <th>Assigned To</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportData['visitors'] as $visitor): ?>
                                        <tr>
                                            <td><code><?php echo htmlspecialchars($visitor['visitor_number']); ?></code></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($visitor['first_name'] . ' ' . $visitor['last_name']); ?></strong>
                                            </td>
                                            <td>
                                                <?php if (!empty($visitor['phone'])): ?>
                                                    <div><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($visitor['phone']); ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($visitor['email'])): ?>
                                                    <div><i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($visitor['email']); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $visitor['gender'] === 'male' ? 'primary' : 'pink'; ?>">
                                                    <?php echo ucfirst($visitor['gender']); ?>
                                                </span>
                                                <br>
                                                <small class="text-muted"><?php echo ucfirst($visitor['age_group']); ?></small>
                                            </td>
                                            <td><?php echo formatDisplayDate($visitor['visit_date']); ?></td>
                                            <td>
                                                <?php
                                                $statusClass = [
                                                    'new_visitor' => 'warning',
                                                    'follow_up' => 'info',
                                                    'regular_attender' => 'success',
                                                    'converted_member' => 'primary'
                                                ];
                                                $class = $statusClass[$visitor['status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $class; ?>">
                                                    <?php echo ucwords(str_replace('_', ' ', $visitor['status'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($visitor['how_heard_about_us'] ?: '-'); ?></td>
                                            <td>
                                                <span class="badge bg-light text-dark"><?php echo $visitor['followup_count']; ?></span>
                                                <?php if ($visitor['last_followup_date']): ?>
                                                    <br><small class="text-muted">Last: <?php echo formatDisplayDate($visitor['last_followup_date']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($visitor['followup_person_first'])): ?>
                                                    <?php echo htmlspecialchars($visitor['followup_person_first'] . ' ' . $visitor['followup_person_last']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Not assigned</span>
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
        <?php endif; ?>
    </div>
</div>

<!-- JavaScript for Charts -->
<script>
function toggleCustomDates() {
    const period = document.querySelector('select[name="period"]').value;
    const customDates = document.getElementById('customDates');
    const customDatesTo = document.getElementById('customDatesTo');
    
    if (period === 'custom') {
        customDates.style.display = 'block';
        customDatesTo.style.display = 'block';
    } else {
        customDates.style.display = 'none';
        customDatesTo.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTables
    if (typeof $ !== 'undefined' && $.fn.DataTable && $('.data-table').length) {
        $('.data-table').DataTable({
            responsive: true,
            pageLength: 25,
            order: [[4, 'desc']], // Sort by visit date
            buttons: ['copy', 'excel', 'pdf', 'print'],
            dom: 'Bfrtip'
        });
    }
    
    <?php if ($reportType === 'summary' && !empty($reportData['sources'])): ?>
    // Source Chart
    const sourceCtx = document.getElementById('sourceChart');
    if (sourceCtx) {
        const sourceData = <?php echo json_encode(array_column($reportData['sources'], 'visitor_count')); ?>;
        const sourceLabels = <?php echo json_encode(array_column($reportData['sources'], 'source')); ?>;
        
        new Chart(sourceCtx, {
            type: 'doughnut',
            data: {
                labels: sourceLabels,
                datasets: [{
                    data: sourceData,
                    backgroundColor: ['#03045e', '#ff2400', '#28a745', '#ffc107', '#17a2b8', '#6c757d'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
    
    // Trends Chart
    const trendsCtx = document.getElementById('trendsChart');
    if (trendsCtx) {
        const trendsData = <?php echo json_encode(array_column($reportData['trends'], 'visitor_count')); ?>;
        const trendsLabels = <?php echo json_encode(array_map(function($item) { 
            return date('M j', strtotime($item['week_start'])); 
        }, $reportData['trends'])); ?>;
        
        new Chart(trendsCtx, {
            type: 'line',
            data: {
                labels: trendsLabels,
                datasets: [{
                    label: 'Visitors',
                    data: trendsData,
                    borderColor: '#ff2400',
                    backgroundColor: 'rgba(255, 36, 0, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
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
                        beginAtZero: true
                    }
                }
            }
        });
    }
    <?php endif; ?>
});
</script>

<style>
.bg-pink {
    background-color: #e91e63 !important;
}
</style>

<?php include_once '../../includes/footer.php'; ?><?php
/**
 * Visitor Reports
 * Deliverance Church Management System
 * 
 * Generate various visitor-related reports and analytics
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
$reportType = sanitizeInput($_GET['type'] ?? 'summary');
$export = sanitizeInput($_GET['export'] ?? '');
$format = sanitizeInput($_GET['format'] ?? 'html');
$period = sanitizeInput($_GET['period'] ?? 'this_month');

// Page configuration
$page_title = 'Visitor Reports';
$page_icon = 'fas fa-user-friends';
$breadcrumb = [
    ['title' => 'Reports', 'url' => 'index.php'],
    ['title' => 'Visitor Reports']
];

// Initialize database
$db = Database::getInstance();

// Process date filters
$filters = [
    'period' => $period,
    'date_from' => sanitizeInput($_GET['date_from'] ?? ''),
    'date_to' => sanitizeInput($_GET['date_to'] ?? ''),
    'status' => sanitizeInput($_GET['status'] ?? ''),
    'age_group' => sanitizeInput($_GET['age_group'] ?? ''),
    'gender' => sanitizeInput($_GET['gender'] ?? ''),
    'source' => sanitizeInput($_GET['source'] ?? ''),
    'search' => sanitizeInput($_GET['search'] ?? '')
];

// Calculate date range
list($dateFrom, $dateTo) = getDateRangeForPeriod($period, $filters['date_from'], $filters['date_to']);

try {
    // Get report data based on type
    switch ($reportType) {
        case 'summary':
            $reportTitle = 'Visitor Summary Report';
            $reportData = getVisitorSummaryData($db, $dateFrom, $dateTo, $filters);
            break;
            
        case 'detailed':
            $reportTitle = 'Detailed Visitor Report';
            $reportData = getDetailedVisitorData($db, $dateFrom, $dateTo, $filters);
            break;
            
        case 'followup':
            $reportTitle = 'Follow-up Report';
            $reportData = getFollowupReportData($db, $dateFrom, $dateTo, $filters);
            break;
            
        case 'conversion':
            $reportTitle = 'Conversion Analysis';
            $reportData = getConversionAnalysisData($db, $dateFrom, $dateTo, $filters);
            break;
            
        case 'trends':
            $reportTitle = 'Visitor Trends Analysis';
            $reportData = getVisitorTrendsData($db, $dateFrom, $dateTo, $filters);
            break;
            
        default:
            $reportTitle = 'Visitor Summary Report';
            $reportData = getVisitorSummaryData($db, $dateFrom, $dateTo, $filters);
            break;
    }
    
} catch (Exception $e) {
    error_log("Error generating visitor report: " . $e->getMessage());
    setFlashMessage('error', 'Error generating report: ' . $e->getMessage());
    $reportData = [];
}

/**
 * Get date range for period
 */
function getDateRangeForPeriod($period, $customFrom = '', $customTo = '') {
    $now = new DateTime();
    
    switch ($period) {
        case 'this_week':
            $start = clone $now;
            $start->modify('monday this week');
            return [$start->format('Y-m-d'), $now->format('Y-m-d')];
            
        case 'this_month':
            return [$now->format('Y-m-01'), $now->format('Y-m-d')];
            
        case 'this_quarter':
            $quarter = ceil($now->format('n') / 3);
            $start = new DateTime($now->format('Y') . '-' . ((($quarter - 1) * 3) + 1) . '-01');
            return [$start->format('Y-m-d'), $now->format('Y-m-d')];
            
        case 'this_year':
            return [$now->format('Y-01-01'), $now->format('Y-m-d')];
            
        case 'last_month':
            $lastMonth = clone $now;
            $lastMonth->modify('-1 month');
            return [$lastMonth->format('Y-m-01'), $lastMonth->format('Y-m-t')];
            
        case 'custom':
            return [
                !empty($customFrom) ? $customFrom : $now->modify('-30 days')->format('Y-m-d'),
                !empty($customTo) ? $customTo : $now->format('Y-m-d')
            ];
            
        default:
            return [$now->format('Y-m-01'), $now->format('Y-m-d')];
    }
}

/**
 * Get visitor summary data
 */
function getVisitorSummaryData($db, $dateFrom, $dateTo, $filters) {
    // Build WHERE clause
    $whereConditions = ["v.visit_date BETWEEN ? AND ?"];
    $params = [$dateFrom, $dateTo];
    
    if (!empty($filters['status'])) {
        $whereConditions[] = "v.status = ?";
        $params[] = $filters['status'];
    }
    
    if (!empty($filters['gender'])) {
        $whereConditions[] = "v.gender = ?";
        $params[] = $filters['gender'];
    }
    
    if (!empty($filters['age_group'])) {
        $whereConditions[] = "v.age_group = ?";
        $params[] = $filters['age_group'];
    }
    
    if (!empty($filters['source'])) {
        $whereConditions[] = "v.how_heard_about_us = ?";
        $params[] = $filters['source'];
    }
    
    if (!empty($filters['search'])) {
        $whereConditions[] = "(v.first_name LIKE ? OR v.last_name LIKE ? OR v.phone LIKE ?)";
        $searchTerm = '%' . $filters['search'] . '%';
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Summary statistics
    $summaryQuery = "
        SELECT 
            COUNT(*) as total_visitors,
            COUNT(CASE WHEN v.gender = 'male' THEN 1 END) as male_visitors,
            COUNT(CASE WHEN v.gender = 'female' THEN 1 END) as female_visitors,
            COUNT(CASE WHEN v.status = 'new_visitor' THEN 1 END) as new_visitors,
            COUNT(CASE WHEN v.status = 'follow_up' THEN 1 END) as in_followup,
            COUNT(CASE WHEN v.status = 'regular_attender' THEN 1 END) as regular_attenders,
            COUNT(CASE WHEN v.status = 'converted_member' THEN 1 END) as converted_members,
            COUNT(CASE WHEN v.age_group = 'child' THEN 1 END) as children,
            COUNT(CASE WHEN v.age_group = 'youth' THEN 1 END) as youth,
            COUNT(CASE WHEN v.age_group = 'adult' THEN 1 END) as adults,
            COUNT(CASE WHEN v.age_group = 'senior' THEN 1 END) as seniors
        FROM visitors v
        WHERE $whereClause
    ";
    
    $summary = $db->executeQuery($summaryQuery, $params)->fetch();
    
    // Visitors by source
    $sourceQuery = "
        SELECT 
            COALESCE(v.how_heard_about_us, 'Not specified') as source,
            COUNT(*) as visitor_count
        FROM visitors v
        WHERE $whereClause
        GROUP BY v.how_heard_about_us
        ORDER BY visitor_count DESC
    ";
    
    $sources = $db->executeQuery($sourceQuery, $params)->fetchAll();
    
    // Weekly trends
    $trendsQuery = "
        SELECT 
            YEARWEEK(v.visit_date) as year_week,
            DATE(DATE_SUB(v.visit_date, INTERVAL WEEKDAY(v.visit_date) DAY)) as week_start,
            COUNT(*) as visitor_count
        FROM visitors v
        WHERE $whereClause
        GROUP BY YEARWEEK(v.visit_date)
        ORDER BY year_week ASC
    ";
    
    $trends = $db->executeQuery($trendsQuery, $params)->fetchAll();
    
    // Follow-up status
    $followupQuery = "
        SELECT 
            v.id,
            v.first_name,
            v.last_name,
            v.visit_date,
            v.status,
            COUNT(vf.id) as followup_count,
            MAX(vf.followup_date) as last_followup
        FROM visitors v
        LEFT JOIN visitor_followups vf ON v.id = vf.visitor_id
        WHERE $whereClause
        AND v.status IN ('new_visitor', 'follow_up')
        GROUP BY v.id
        ORDER BY v.visit_date DESC
        LIMIT 20
    ";
    
    $pendingFollowup = $db->executeQuery($followupQuery, $params)->fetchAll();
    
    return [
        'summary' => $summary,
        'sources' => $sources,
        'trends' => $trends,
        'pending_followup' => $pendingFollowup
    ];
}

/**
 * Get detailed visitor data
 */
function getDetailedVisitorData($db, $dateFrom, $dateTo, $filters) {
    // Build WHERE clause
    $whereConditions = ["v.visit_date BETWEEN ? AND ?"];
    $params = [$dateFrom, $dateTo];
    
    // Apply filters (same as summary)
    if (!empty($filters['status'])) {
        $whereConditions[] = "v.status = ?";
        $params[] = $filters['status'];
    }
    
    if (!empty($filters['gender'])) {
        $whereConditions[] = "v.gender = ?";
        $params[] = $filters['gender'];
    }
    
    if (!empty($filters['age_group'])) {
        $whereConditions[] = "v.age_group = ?";
        $params[] = $filters['age_group'];
    }
    
    if (!empty($filters['source'])) {
        $whereConditions[] = "v.how_heard_about_us = ?";
        $params[] = $filters['source'];
    }
    
    if (!empty($filters['search'])) {
        $whereConditions[] = "(v.first_name LIKE ? OR v.last_name LIKE ? OR v.phone LIKE ?)";
        $searchTerm = '%' . $filters['search'] . '%';
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $query = "
        SELECT 
            v.*,
            u.first_name as recorded_by_first,
            u.last_name as recorded_by_last,
            m.first_name as followup_person_first,
            m.last_name as followup_person_last,
            COUNT(vf.id) as followup_count,
            MAX(vf.followup_date) as last_followup_date
        FROM visitors v
        LEFT JOIN users u ON v.created_by = u.id
        LEFT JOIN members m ON v.assigned_followup_person_id = m.id
        LEFT JOIN visitor_followups vf ON v.id = vf.visitor_id
        WHERE $whereClause
        GROUP BY v.id
        ORDER BY v.visit_date DESC, v.created_at DESC
    ";
    
    $visitors = $db->executeQuery($query, $params)->fetchAll();
    
    return [
        'visitors' => $visitors,
        'total_count' => count($visitors)
    ];
}

/**
 * Get follow-up report data
 */
function getFollowupReportData($db, $dateFrom, $dateTo, $filters) {
    $query = "
        SELECT 
            v.id as visitor_id,
            v.visitor_number,
            v.first_name,
            v.last_name,
            v.phone,
            v.visit_date,
            v.status,
            v.assigned_followup_person_id,
            m.first_name as followup_person_first,
            m.last_name as followup_person_last,
            COUNT(vf.id) as total_followups,
            MAX(vf.followup_date) as last_followup_date,
            MIN(vf.followup_date) as first_followup_date,
            GROUP_CONCAT(
                CONCAT(vf.followup_type, ':', vf.followup_date) 
                ORDER BY vf.followup_date DESC 
                SEPARATOR '; '
            ) as followup_history,
            DATEDIFF(CURDATE(), v.visit_date) as days_since_visit,
            CASE 
                WHEN MAX(vf.followup_date) IS NULL THEN 'No follow-up'
                WHEN DATEDIFF(CURDATE(), MAX(vf.followup_date)) > 14 THEN 'Overdue'
                WHEN DATEDIFF(CURDATE(), MAX(vf.followup_date)) > 7 THEN 'Due soon'
                ELSE 'Up to date'
            END as followup_status
                FROM visitors v
                LEFT JOIN members m ON v.assigned_followup_person_id = m.id
                LEFT JOIN visitor_followups vf ON v.id = vf.visitor_id
                WHERE v.visit_date BETWEEN ? AND ?
                AND v.status IN ('new_visitor', 'follow_up', 'regular_attender')
                GROUP BY v.id
                ORDER BY 
                    CASE 
                        WHEN MAX(vf.followup_date) IS NULL THEN 1
                        WHEN DATEDIFF(CURDATE(), MAX(vf.followup_date)) > 14 THEN 2
                        WHEN DATEDIFF(CURDATE(), MAX(vf.followup_date)) > 7 THEN 3
                        ELSE 4
                    END,
                    v.visit_date DESC
            ";
        
            $followupData = $db->executeQuery($query, [$dateFrom, $dateTo])->fetchAll();
        
            // Summary by follow-up status
            $statusSummary = [
                'no_followup' => 0,
                'overdue' => 0,
                'due_soon' => 0,
                'up_to_date' => 0
            ];
        
            foreach ($followupData as $visitor) {
                switch ($visitor['followup_status']) {
                    case 'No follow-up':
                        $statusSummary['no_followup']++;
                        break;
                    case 'Overdue':
                        $statusSummary['overdue']++;
                        break;
                    case 'Due soon':
                        $statusSummary['due_soon']++;
                        break;
                    case 'Up to date':
                        $statusSummary['up_to_date']++;
                        break;
                }
            }
        
            return [
                'followup_data' => $followupData,
                'status_summary' => $statusSummary,
                'total_visitors' => count($followupData)
            ];
        }   
/**
 * Get conversion analysis data
 */
function getConversionAnalysisData($db, $dateFrom, $dateTo) {
    $query = "
        SELECT 
            v.id as visitor_id,
            v.first_name,
            v.last_name,
            v.phone,
            v.visit_date,
            v.status,
            v.assigned_followup_person_id,
            m.first_name as followup_person_first,
            m.last_name as followup_person_last,
            COUNT(vf.id) as total_followups,
            MAX(vf.followup_date) as last_followup_date,
            MIN(vf.followup_date) as first_followup_date,
            GROUP_CONCAT(
                CONCAT(vf.followup_type, ':', vf.followup_date) 
                ORDER BY vf.followup_date DESC 
                SEPARATOR '; '
            ) as followup_history,
            DATEDIFF(CURDATE(), v.visit_date) as days_since_visit,
            CASE 
                WHEN MAX(vf.followup_date) IS NULL THEN 'No follow-up'
                WHEN DATEDIFF(CURDATE(), MAX(vf.followup_date)) > 14 THEN 'Overdue'
                WHEN DATEDIFF(CURDATE(), MAX(vf.followup_date)) > 7 THEN 'Due soon'
                ELSE 'Up to date'
            END as followup_status
        FROM visitors v
        LEFT JOIN members m ON v.assigned_followup_person_id = m.id
        LEFT JOIN visitor_followups vf ON v.id = vf.visitor_id
        WHERE v.visit_date BETWEEN ? AND ?
        AND v.status IN ('new_visitor', 'follow_up', 'regular_attender')
        GROUP BY v.id
        ORDER BY 
            CASE 
                WHEN MAX(vf.followup_date) IS NULL THEN 1
                WHEN DATEDIFF(CURDATE(), MAX(vf.followup_date)) > 14 THEN 2
                WHEN DATEDIFF(CURDATE(), MAX(vf.followup_date)) > 7 THEN 3
                ELSE 4
            END,
            v.visit_date DESC
    ";

    $conversionData = $db->executeQuery($query, [$dateFrom, $dateTo])->fetchAll();

    return [
        'conversion_data' => $conversionData,
        'total_visitors' => count($conversionData)
    ];
}
            $member['skills'],
            $member['departments']
        ]);
    }
    
    fclose($file);
    
    // Output file for download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
    readfile($filename);
    unlink($filename); // Delete temp file
    exit();
}
/**
 * Export members data to Excel
 */ 
function exportMembersToExcel($db, $filename) {
    require_once '../../vendor/phpoffice/phpspreadsheet/src/Bootstrap.php';
    
    use PhpOffice\PhpSpreadsheet\Spreadsheet;
    use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
    
    $query = "
        SELECT 
            m.member_number,
            m.first_name,
            m.last_name,
            m.middle_name,
            m.date_of_birth,
            m.age,
            m.gender,
            m.email,
            m.phone,
            m.address,
            m.join_date,
            m.membership_status
        FROM members m
    ";

    $members = $db->executeQuery($query)->fetchAll();

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Set header
    $sheet->setCellValue('A1', 'Member Number');
    $sheet->setCellValue('B1', 'First Name');
    $sheet->setCellValue('C1', 'Last Name');
    $sheet->setCellValue('D1', 'Middle Name');
    $sheet->setCellValue('E1', 'Date of Birth');
    $sheet->setCellValue('F1', 'Age');
    $sheet->setCellValue('G1', 'Gender');
    $sheet->setCellValue('H1', 'Email');
    $sheet->setCellValue('I1', 'Phone');
    $sheet->setCellValue('J1', 'Address');
    $sheet->setCellValue('K1', 'Join Date');
    $sheet->setCellValue('L1', 'Membership Status');

    // Populate data
    $row = 2;
    foreach ($members as $member) {
        $sheet->setCellValue('A' . $row, $member['member_number']);
        $sheet->setCellValue('B' . $row, $member['first_name']);
        $sheet->setCellValue('C' . $row, $member['last_name']);
        $sheet->setCellValue('D' . $row, $member['middle_name']);
        $sheet->setCellValue('E' . $row, $member['date_of_birth']);
        $sheet->setCellValue('F' . $row, $member['age']);
        $sheet->setCellValue('G' . $row, $member['gender']);
        $sheet->setCellValue('H' . $row, $member['email']);
        $sheet->setCellValue('I' . $row, $member['phone']);
        $sheet->setCellValue('J' . $row, $member['address']);
        $sheet->setCellValue('K' . $row, $member['join_date']);
        $sheet->setCellValue('L' . $row, $member['membership_status']);
        $row++;
    }

    $writer = new Xlsx($spreadsheet);
    $writer->save($filename);
}
            $member['occupation'],
            $member['skills'],
            $member['departments']
        ]);
    }

    fclose($file);

    // Output file for download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
    readfile($filename);
    unlink($filename); // Delete temp file
    exit();
}

