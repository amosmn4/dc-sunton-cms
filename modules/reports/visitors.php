<?php
/**
 * Visitor Reports
 * Deliverance Church Management System
 *
 * Reports:
 *  - summary: KPIs + sources + weekly trends + pending follow-up preview
 *  - detailed: full visitor list with filters + export (CSV/Excel)
 *  - followup: follow-up status dashboard + export (CSV/Excel)
 *  - conversion: conversion funnel + monthly + source analysis
 *  - trends: daily counts, day-of-week, age groups
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

requireLogin();
if (!hasPermission('reports')) {
    header('Location: ' . BASE_URL . 'modules/dashboard/?error=access_denied');
    exit();
}

/* ---------------------------------------------
 * Input / Filters
 * --------------------------------------------- */
$reportType = sanitizeInput($_GET['type']   ?? 'summary');       // summary | detailed | followup | conversion | trends
$export     = sanitizeInput($_GET['export'] ?? '');              // '' | '1'
$format     = sanitizeInput($_GET['format'] ?? 'html');          // 'html' | 'csv' | 'excel'
$period     = sanitizeInput($_GET['period'] ?? 'this_month');    // this_week | this_month | this_quarter | this_year | last_month | custom

$filters = [
    'period'     => $period,
    'date_from'  => sanitizeInput($_GET['date_from'] ?? ''),
    'date_to'    => sanitizeInput($_GET['date_to']   ?? ''),
    'status'     => sanitizeInput($_GET['status']    ?? ''),
    'age_group'  => sanitizeInput($_GET['age_group'] ?? ''),
    'gender'     => sanitizeInput($_GET['gender']    ?? ''),
    'source'     => sanitizeInput($_GET['source']    ?? ''),
    'search'     => sanitizeInput($_GET['search']    ?? '')
];

$db = Database::getInstance();

/* ---------------------------------------------
 * Date Range
 * --------------------------------------------- */
list($dateFrom, $dateTo) = getDateRangeForPeriod($period, $filters['date_from'], $filters['date_to']);

/* ---------------------------------------------
 * Data Retrieval
 * --------------------------------------------- */
try {
    switch ($reportType) {
        case 'summary':
            $page_title  = 'Visitor Summary';
            $reportTitle = 'Visitor Summary Report';
            $reportData  = getVisitorSummaryData($db, $dateFrom, $dateTo, $filters);
            break;

        case 'detailed':
            $page_title  = 'Detailed Visitor Report';
            $reportTitle = 'Detailed Visitor Report';
            $reportData  = getDetailedVisitorData($db, $dateFrom, $dateTo, $filters);
            break;

        case 'followup':
            $page_title  = 'Follow-up Report';
            $reportTitle = 'Follow-up Report';
            $reportData  = getFollowupReportData($db, $dateFrom, $dateTo, $filters);
            break;

        case 'conversion':
            $page_title  = 'Conversion Analysis';
            $reportTitle = 'Conversion Analysis';
            $reportData  = getConversionAnalysisData($db, $dateFrom, $dateTo, $filters);
            break;

        case 'trends':
            $page_title  = 'Visitor Trends';
            $reportTitle = 'Visitor Trends Analysis';
            $reportData  = getVisitorTrendsData($db, $dateFrom, $dateTo, $filters);
            break;

        default:
            $page_title  = 'Visitor Summary';
            $reportTitle = 'Visitor Summary Report';
            $reportData  = getVisitorSummaryData($db, $dateFrom, $dateTo, $filters);
            $reportType  = 'summary';
            break;
    }
} catch (Throwable $e) {
    error_log("Error generating visitor report: " . $e->getMessage());
    setFlashMessage('error', 'Error generating report: ' . $e->getMessage());
    $reportData = [];
}

/* ---------------------------------------------
 * Optional Exports
 * --------------------------------------------- */
if ($export === '1') {
    if ($reportType === 'detailed') {
        $headers = ['Visitor #', 'Name', 'Phone', 'Email', 'Gender', 'Age Group', 'Visit Date', 'Status', 'Source', 'Follow-ups', 'Last Follow-up', 'Assigned To'];
        $rows = [];
        foreach (($reportData['visitors'] ?? []) as $v) {
            $rows[] = [
                $v['visitor_number'] ?? '',
                trim(($v['first_name'] ?? '') . ' ' . ($v['last_name'] ?? '')),
                $v['phone'] ?? '',
                $v['email'] ?? '',
                ucfirst($v['gender'] ?? ''),
                ucfirst($v['age_group'] ?? ''),
                $v['visit_date'] ?? '',
                ucwords(str_replace('_', ' ', $v['status'] ?? '')),
                $v['how_heard_about_us'] ?? '',
                (int)($v['followup_count'] ?? 0),
                $v['last_followup_date'] ?? '',
                trim(($v['followup_person_first'] ?? '') . ' ' . ($v['followup_person_last'] ?? ''))
            ];
        }
        if ($format === 'excel') {
            $filepath = generateExcelFile($rows, $headers, 'visitors_detailed_' . date('Ymd_His') . '.xlsx');
            sendJSONResponse(['success' => true, 'filename' => basename($filepath), 'path' => $filepath]);
        } else {
            exportToCSV($rows, $headers, 'visitors_detailed_' . date('Ymd_His') . '.csv');
        }
        exit();
    }

    if ($reportType === 'followup') {
        $headers = ['Visitor #', 'Name', 'Phone', 'Visit Date', 'Status', 'Assigned To', 'Total Follow-ups', 'First Follow-up', 'Last Follow-up', 'Days Since Visit', 'Follow-up Status'];
        $rows = [];
        foreach (($reportData['followup_data'] ?? []) as $v) {
            $rows[] = [
                $v['visitor_number'] ?? '',
                trim(($v['first_name'] ?? '') . ' ' . ($v['last_name'] ?? '')),
                $v['phone'] ?? '',
                $v['visit_date'] ?? '',
                ucwords(str_replace('_', ' ', $v['status'] ?? '')),
                trim(($v['followup_person_first'] ?? '') . ' ' . ($v['followup_person_last'] ?? '')),
                (int)($v['total_followups'] ?? 0),
                $v['first_followup_date'] ?? '',
                $v['last_followup_date'] ?? '',
                (int)($v['days_since_visit'] ?? 0),
                $v['followup_status'] ?? ''
            ];
        }
        if ($format === 'excel') {
            $filepath = generateExcelFile($rows, $headers, 'visitors_followup_' . date('Ymd_His') . '.xlsx');
            sendJSONResponse(['success' => true, 'filename' => basename($filepath), 'path' => $filepath]);
        } else {
            exportToCSV($rows, $headers, 'visitors_followup_' . date('Ymd_His') . '.csv');
        }
        exit();
    }

    // Default simple CSV for summary sources
    if ($reportType === 'summary') {
        $headers = ['Source', 'Visitor Count'];
        $rows = [];
        foreach (($reportData['sources'] ?? []) as $r) {
            $rows[] = [$r['source'], (int)$r['visitor_count']];
        }
        exportToCSV($rows, $headers, 'visitors_sources_' . date('Ymd_His') . '.csv');
        exit();
    }
}

/* ---------------------------------------------
 * View
 * --------------------------------------------- */
$page_icon = 'fas fa-user-friends';
$breadcrumb = [
    ['title' => 'Reports', 'url' => 'index.php'],
    ['title' => 'Visitor Reports']
];

include_once '../../includes/header.php';
?>

<div class="row">
    <!-- Filters -->
    <div class="col-12 mb-4">
        <div class="card shadow-sm">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-filter me-2"></i>Report Filters</h6>
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                        <i class="fas fa-chevron-down"></i>
                    </button>
                </div>
            </div>
            <div class="collapse show" id="filterCollapse">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Report Type</label>
                            <select name="type" class="form-select" onchange="this.form.submit()">
                                <option value="summary"   <?php echo $reportType === 'summary'   ? 'selected' : ''; ?>>Visitor Summary</option>
                                <option value="detailed"  <?php echo $reportType === 'detailed'  ? 'selected' : ''; ?>>Detailed Report</option>
                                <option value="followup"  <?php echo $reportType === 'followup'  ? 'selected' : ''; ?>>Follow-up Report</option>
                                <option value="conversion"<?php echo $reportType === 'conversion'? 'selected' : ''; ?>>Conversion Analysis</option>
                                <option value="trends"    <?php echo $reportType === 'trends'    ? 'selected' : ''; ?>>Trends Analysis</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Period</label>
                            <select name="period" class="form-select" onchange="toggleCustomDates()">
                                <option value="this_week"   <?php echo $period === 'this_week'   ? 'selected' : ''; ?>>This Week</option>
                                <option value="this_month"  <?php echo $period === 'this_month'  ? 'selected' : ''; ?>>This Month</option>
                                <option value="this_quarter"<?php echo $period === 'this_quarter'? 'selected' : ''; ?>>This Quarter</option>
                                <option value="this_year"   <?php echo $period === 'this_year'   ? 'selected' : ''; ?>>This Year</option>
                                <option value="last_month"  <?php echo $period === 'last_month'  ? 'selected' : ''; ?>>Last Month</option>
                                <option value="custom"      <?php echo $period === 'custom'      ? 'selected' : ''; ?>>Custom Range</option>
                            </select>
                        </div>

                        <div class="col-md-3" id="customDates" style="<?php echo $period === 'custom' ? '' : 'display:none;'; ?>">
                            <label class="form-label">From Date</label>
                            <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($filters['date_from']); ?>">
                        </div>
                        <div class="col-md-3" id="customDatesTo" style="<?php echo $period === 'custom' ? '' : 'display:none;'; ?>">
                            <label class="form-label">To Date</label>
                            <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($filters['date_to']); ?>">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="">All Statuses</option>
                                <option value="new_visitor"      <?php echo $filters['status'] === 'new_visitor' ? 'selected' : ''; ?>>New Visitor</option>
                                <option value="follow_up"         <?php echo $filters['status'] === 'follow_up' ? 'selected' : ''; ?>>In Follow-up</option>
                                <option value="regular_attender"  <?php echo $filters['status'] === 'regular_attender' ? 'selected' : ''; ?>>Regular Attender</option>
                                <option value="converted_member"  <?php echo $filters['status'] === 'converted_member' ? 'selected' : ''; ?>>Converted Member</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Gender</label>
                            <select name="gender" class="form-select">
                                <option value="">All Genders</option>
                                <option value="male"   <?php echo $filters['gender'] === 'male'   ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo $filters['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Age Group</label>
                            <select name="age_group" class="form-select">
                                <option value="">All Ages</option>
                                <option value="child"  <?php echo $filters['age_group'] === 'child'  ? 'selected' : ''; ?>>Child</option>
                                <option value="youth"  <?php echo $filters['age_group'] === 'youth'  ? 'selected' : ''; ?>>Youth</option>
                                <option value="adult"  <?php echo $filters['age_group'] === 'adult'  ? 'selected' : ''; ?>>Adult</option>
                                <option value="senior" <?php echo $filters['age_group'] === 'senior' ? 'selected' : ''; ?>>Senior</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" placeholder="Name or phone" value="<?php echo htmlspecialchars($filters['search']); ?>">
                        </div>

                        <div class="col-12">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-church-primary"><i class="fas fa-search me-2"></i>Generate Report</button>
                                <a href="?" class="btn btn-outline-secondary"><i class="fas fa-undo me-2"></i>Reset Filters</a>
                                <?php if (in_array($reportType, ['detailed','followup','summary'])): ?>
                                    <a class="btn btn-outline-success" href="?<?php echo http_build_query(array_merge($_GET, ['export' => '1', 'format' => 'csv'])); ?>">
                                        <i class="fas fa-file-csv me-2"></i>Export CSV
                                    </a>
                                    <?php if ($reportType !== 'summary'): ?>
                                        <a class="btn btn-outline-success" href="?<?php echo http_build_query(array_merge($_GET, ['export' => '1', 'format' => 'excel'])); ?>">
                                            <i class="fas fa-file-excel me-2"></i>Export Excel
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Results -->
    <div class="col-12">
        <?php if ($reportType === 'summary'): ?>
            <?php
            $summary = $reportData['summary'] ?? [];
            $total_visitors     = (int)($summary['total_visitors']     ?? 0);
            $converted_members  = (int)($summary['converted_members']  ?? 0);
            $regular_attenders  = (int)($summary['regular_attenders']  ?? 0);
            $pending_follow_cnt = count($reportData['pending_followup'] ?? []);
            ?>
            <div class="row">
                <div class="col-md-3 mb-4"><div class="card bg-primary text-white"><div class="card-body d-flex justify-content-between"><div><h6>Total Visitors</h6><h4 class="mb-0"><?php echo number_format($total_visitors); ?></h4></div><i class="fas fa-users fa-2x opacity-75"></i></div></div></div>
                <div class="col-md-3 mb-4"><div class="card bg-success text-white"><div class="card-body d-flex justify-content-between"><div><h6>Converted</h6><h4 class="mb-0"><?php echo number_format($converted_members); ?></h4></div><i class="fas fa-user-check fa-2x opacity-75"></i></div></div></div>
                <div class="col-md-3 mb-4"><div class="card bg-warning text-white"><div class="card-body d-flex justify-content-between"><div><h6>Need Follow-up</h6><h4 class="mb-0"><?php echo number_format($pending_follow_cnt); ?></h4></div><i class="fas fa-phone fa-2x opacity-75"></i></div></div></div>
                <div class="col-md-3 mb-4"><div class="card bg-info text-white"><div class="card-body d-flex justify-content-between"><div><h6>Regular Attenders</h6><h4 class="mb-0"><?php echo number_format($regular_attenders); ?></h4></div><i class="fas fa-calendar-check fa-2x opacity-75"></i></div></div></div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header"><h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Visitors by Source</h6></div>
                        <div class="card-body">
                            <?php if (!empty($reportData['sources'])): ?>
                                <div style="height:300px"><canvas id="sourceChart"></canvas></div>
                            <?php else: ?>
                                <div class="text-center text-muted py-4"><i class="fas fa-chart-pie fa-3x mb-3"></i><p>No data available for the selected period</p></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header"><h6 class="mb-0"><i class="fas fa-chart-line me-2"></i>Weekly Visitor Trends</h6></div>
                        <div class="card-body">
                            <?php if (!empty($reportData['trends'])): ?>
                                <div style="height:300px"><canvas id="trendsChart"></canvas></div>
                            <?php else: ?>
                                <div class="text-center text-muted py-4"><i class="fas fa-chart-line fa-3x mb-3"></i><p>No trend data available</p></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($reportData['pending_followup'])): ?>
                <div class="card">
                    <div class="card-header"><h6 class="mb-0"><i class="fas fa-phone me-2"></i>Visitors Needing Follow-up</h6></div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead><tr><th>Name</th><th>Visit Date</th><th>Status</th><th>Follow-ups</th><th>Last Follow-up</th><th>Days Since Visit</th></tr></thead>
                                <tbody>
                                <?php foreach ($reportData['pending_followup'] as $v): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars(trim(($v['first_name'] ?? '').' '.($v['last_name'] ?? ''))); ?></strong></td>
                                        <td><?php echo formatDisplayDate($v['visit_date']); ?></td>
                                        <td><span class="badge bg-<?php echo ($v['status']==='new_visitor'?'warning':'info'); ?>"><?php echo ucwords(str_replace('_',' ',$v['status'])); ?></span></td>
                                        <td><?php echo (int)$v['followup_count']; ?></td>
                                        <td><?php echo !empty($v['last_followup']) ? formatDisplayDate($v['last_followup']) : 'Never'; ?></td>
                                        <td><?php $days=(new DateTime())->diff(new DateTime($v['visit_date']))->days; echo (int)$days.' days'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        <?php elseif ($reportType === 'detailed'): ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-table me-2"></i><?php echo htmlspecialchars($reportTitle); ?>
                        <span class="badge bg-primary ms-2"><?php echo (int)($reportData['total_count'] ?? 0); ?> records</span>
                    </h5>
                    <div>
                        <a class="btn btn-sm btn-outline-success" href="?<?php echo http_build_query(array_merge($_GET,['export'=>'1','format'=>'csv'])); ?>"><i class="fas fa-file-csv me-2"></i>CSV</a>
                        <a class="btn btn-sm btn-outline-success" href="?<?php echo http_build_query(array_merge($_GET,['export'=>'1','format'=>'excel'])); ?>"><i class="fas fa-file-excel me-2"></i>Excel</a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($reportData['visitors'])): ?>
                        <div class="text-center py-4 text-muted"><i class="fas fa-search fa-3x mb-3"></i><h5>No Visitors Found</h5><p>No visitors match your current filter criteria.</p></div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover data-table">
                                <thead>
                                    <tr>
                                        <th>Visitor #</th><th>Name</th><th>Contact</th><th>Age/Gender</th>
                                        <th>Visit Date</th><th>Status</th><th>Source</th><th>Follow-ups</th><th>Assigned To</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportData['visitors'] as $v): ?>
                                        <tr>
                                            <td><code><?php echo htmlspecialchars($v['visitor_number']); ?></code></td>
                                            <td><strong><?php echo htmlspecialchars(trim(($v['first_name'] ?? '').' '.($v['last_name'] ?? ''))); ?></strong></td>
                                            <td>
                                                <?php if (!empty($v['phone'])): ?><div><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($v['phone']); ?></div><?php endif; ?>
                                                <?php if (!empty($v['email'])): ?><div><i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($v['email']); ?></div><?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo ($v['gender']==='male'?'primary':'pink'); ?>"><?php echo ucfirst($v['gender'] ?? ''); ?></span><br>
                                                <small class="text-muted"><?php echo ucfirst($v['age_group'] ?? ''); ?></small>
                                            </td>
                                            <td><?php echo formatDisplayDate($v['visit_date']); ?></td>
                                            <td>
                                                <?php
                                                $map = ['new_visitor'=>'warning','follow_up'=>'info','regular_attender'=>'success','converted_member'=>'primary'];
                                                $cls = $map[$v['status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $cls; ?>"><?php echo ucwords(str_replace('_',' ',$v['status'])); ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($v['how_heard_about_us'] ?: '-'); ?></td>
                                            <td>
                                                <span class="badge bg-light text-dark"><?php echo (int)$v['followup_count']; ?></span>
                                                <?php if (!empty($v['last_followup_date'])): ?><br><small class="text-muted">Last: <?php echo formatDisplayDate($v['last_followup_date']); ?></small><?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($v['followup_person_first'])): ?>
                                                    <?php echo htmlspecialchars(trim(($v['followup_person_first'] ?? '').' '.($v['followup_person_last'] ?? ''))); ?>
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

        <?php elseif ($reportType === 'followup'): ?>
            <?php
            $sum = $reportData['status_summary'] ?? ['no_followup'=>0,'overdue'=>0,'due_soon'=>0,'up_to_date'=>0];
            ?>
            <div class="row mb-4">
                <div class="col-md-3"><div class="card bg-secondary text-white"><div class="card-body"><h6>No Follow-up</h6><h4 class="mb-0"><?php echo (int)$sum['no_followup']; ?></h4></div></div></div>
                <div class="col-md-3"><div class="card bg-danger text-white"><div class="card-body"><h6>Overdue</h6><h4 class="mb-0"><?php echo (int)$sum['overdue']; ?></h4></div></div></div>
                <div class="col-md-3"><div class="card bg-warning text-white"><div class="card-body"><h6>Due Soon</h6><h4 class="mb-0"><?php echo (int)$sum['due_soon']; ?></h4></div></div></div>
                <div class="col-md-3"><div class="card bg-success text-white"><div class="card-body"><h6>Up to Date</h6><h4 class="mb-0"><?php echo (int)$sum['up_to_date']; ?></h4></div></div></div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-phone me-2"></i><?php echo htmlspecialchars($reportTitle); ?>
                        <span class="badge bg-primary ms-2"><?php echo (int)($reportData['total_visitors'] ?? 0); ?> visitors</span>
                    </h5>
                    <div>
                        <a class="btn btn-sm btn-outline-success" href="?<?php echo http_build_query(array_merge($_GET,['export'=>'1','format'=>'csv'])); ?>"><i class="fas fa-file-csv me-2"></i>CSV</a>
                        <a class="btn btn-sm btn-outline-success" href="?<?php echo http_build_query(array_merge($_GET,['export'=>'1','format'=>'excel'])); ?>"><i class="fas fa-file-excel me-2"></i>Excel</a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($reportData['followup_data'])): ?>
                        <div class="text-center py-4 text-muted"><i class="fas fa-inbox fa-3x mb-3"></i><h5>No Follow-up Items</h5><p>No visitors match the follow-up criteria for the selected period.</p></div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Visitor #</th><th>Name</th><th>Phone</th><th>Visit Date</th><th>Status</th>
                                        <th>Assigned To</th><th>Total Follow-ups</th><th>First</th><th>Last</th><th>Days Since Visit</th><th>Follow-up Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportData['followup_data'] as $v): ?>
                                        <tr>
                                            <td><code><?php echo htmlspecialchars($v['visitor_number'] ?? ''); ?></code></td>
                                            <td><strong><?php echo htmlspecialchars(trim(($v['first_name'] ?? '').' '.($v['last_name'] ?? ''))); ?></strong></td>
                                            <td><?php echo htmlspecialchars($v['phone'] ?? '-'); ?></td>
                                            <td><?php echo formatDisplayDate($v['visit_date']); ?></td>
                                            <td><?php echo ucwords(str_replace('_',' ', $v['status'])); ?></td>
                                            <td><?php echo htmlspecialchars(trim(($v['followup_person_first'] ?? '').' '.($v['followup_person_last'] ?? '')) ?: '—'); ?></td>
                                            <td><?php echo (int)($v['total_followups'] ?? 0); ?></td>
                                            <td><?php echo !empty($v['first_followup_date']) ? formatDisplayDate($v['first_followup_date']) : '—'; ?></td>
                                            <td><?php echo !empty($v['last_followup_date'])  ? formatDisplayDate($v['last_followup_date'])  : '—'; ?></td>
                                            <td><?php echo (int)($v['days_since_visit'] ?? 0); ?></td>
                                            <td>
                                                <?php
                                                $statusMap = ['No follow-up'=>'secondary','Overdue'=>'danger','Due soon'=>'warning','Up to date'=>'success'];
                                                $b = $statusMap[$v['followup_status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $b; ?>"><?php echo $v['followup_status']; ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($reportType === 'conversion'): ?>
            <div class="row">
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header"><h6 class="mb-0"><i class="fas fa-filter me-2"></i>Conversion Funnel</h6></div>
                        <div class="card-body">
                            <?php if (!empty($reportData['conversion_funnel'])): ?>
                                <div style="height:320px"><canvas id="funnelChart"></canvas></div>
                            <?php else: ?>
                                <p class="text-center text-muted mb-0">No data for the selected period.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header"><h6 class="mb-0"><i class="fas fa-chart-line me-2"></i>Monthly Conversion Rate</h6></div>
                        <div class="card-body">
                            <?php if (!empty($reportData['monthly_conversions'])): ?>
                                <div style="height:320px"><canvas id="monthlyConvChart"></canvas></div>
                            <?php else: ?>
                                <p class="text-center text-muted mb-0">No monthly data available.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-12 mb-4">
                    <div class="card">
                        <div class="card-header"><h6 class="mb-0"><i class="fas fa-bullhorn me-2"></i>Conversion by Source</h6></div>
                        <div class="card-body">
                            <?php if (!empty($reportData['source_conversions'])): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead><tr><th>Source</th><th class="text-end">Visitors</th><th class="text-end">Converted</th><th class="text-end">Rate</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($reportData['source_conversions'] as $r): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($r['source']); ?></td>
                                                    <td class="text-end"><?php echo (int)$r['total_visitors']; ?></td>
                                                    <td class="text-end"><?php echo (int)$r['converted']; ?></td>
                                                    <td class="text-end"><span class="badge bg-info"><?php echo number_format((float)$r['conversion_rate'], 1); ?>%</span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-center text-muted mb-0">No source conversion data.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($reportType === 'trends'): ?>
            <div class="row">
                <div class="col-lg-8 mb-4">
                    <div class="card">
                        <div class="card-header"><h6 class="mb-0"><i class="fas fa-calendar-day me-2"></i>Daily Visitors</h6></div>
                        <div class="card-body">
                            <?php if (!empty($reportData['daily_trends'])): ?>
                                <div style="height:320px"><canvas id="dailyChart"></canvas></div>
                            <?php else: ?>
                                <p class="text-center text-muted mb-0">No daily data available.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 mb-4">
                    <div class="card">
                        <div class="card-header"><h6 class="mb-0"><i class="fas fa-calendar-week me-2"></i>Day of Week</h6></div>
                        <div class="card-body">
                            <?php if (!empty($reportData['day_of_week_trends'])): ?>
                                <div style="height:320px"><canvas id="dowChart"></canvas></div>
                            <?php else: ?>
                                <p class="text-center text-muted mb-0">No day-of-week data.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-12 mb-4">
                    <div class="card">
                        <div class="card-header"><h6 class="mb-0"><i class="fas fa-user-friends me-2"></i>Age Group Breakdown</h6></div>
                        <div class="card-body">
                            <?php if (!empty($reportData['age_group_trends'])): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead><tr><th>Age Group</th><th class="text-end">Visitors</th><th class="text-end">Percentage</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($reportData['age_group_trends'] as $r): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars(ucfirst($r['age_group'] ?? 'Not specified')); ?></td>
                                                    <td class="text-end"><?php echo (int)$r['visitor_count']; ?></td>
                                                    <td class="text-end"><span class="badge bg-secondary"><?php echo number_format((float)$r['percentage'], 1); ?>%</span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-center text-muted mb-0">No age-group data.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Scripts -->
<script>
function toggleCustomDates() {
    const p = document.querySelector('select[name="period"]').value;
    document.getElementById('customDates').style.display   = (p === 'custom' ? 'block' : 'none');
    document.getElementById('customDatesTo').style.display = (p === 'custom' ? 'block' : 'none');
}
document.addEventListener('DOMContentLoaded', toggleCustomDates);
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
<?php if ($reportType === 'summary'): ?>
    <?php if (!empty($reportData['sources'])): ?>
    (function(){
        const ctx = document.getElementById('sourceChart');
        const data = <?php echo json_encode(array_map(fn($x)=>(int)$x['visitor_count'], $reportData['sources'])); ?>;
        const labels = <?php echo json_encode(array_map(fn($x)=>$x['source'], $reportData['sources'])); ?>;
        if (ctx) new Chart(ctx, {
            type: 'doughnut',
            data: { labels, datasets: [{ data, borderWidth: 2 }]},
            options: { responsive:true, maintainAspectRatio:false, plugins:{ legend:{ position:'bottom' } } }
        });
    })();
    <?php endif; ?>

    <?php if (!empty($reportData['trends'])): ?>
    (function(){
        const ctx = document.getElementById('trendsChart');
        const labels = <?php echo json_encode(array_map(fn($r)=>date('M j', strtotime($r['week_start'])), $reportData['trends'])); ?>;
        const data   = <?php echo json_encode(array_map(fn($r)=>(int)$r['visitor_count'], $reportData['trends'])); ?>;
        if (ctx) new Chart(ctx, {
            type: 'line',
            data: { labels, datasets: [{ label:'Visitors', data, borderWidth:3, fill:true, tension:0.35 }]},
            options: { responsive:true, maintainAspectRatio:false, scales:{ y:{ beginAtZero:true } } }
        });
    })();
    <?php endif; ?>
<?php endif; ?>

<?php if ($reportType === 'conversion'): ?>
    <?php if (!empty($reportData['conversion_funnel'])): ?>
    (function(){
        const ctx = document.getElementById('funnelChart');
        const labels = <?php echo json_encode(array_map(fn($r)=>$r['stage'], $reportData['conversion_funnel'])); ?>;
        const data   = <?php echo json_encode(array_map(fn($r)=>(int)$r['count'],  $reportData['conversion_funnel'])); ?>;
        if (ctx) new Chart(ctx, {
            type: 'bar',
            data: { labels, datasets: [{ label:'Count', data, borderWidth:2 }]},
            options: { indexAxis:'y', responsive:true, maintainAspectRatio:false, scales:{ x:{ beginAtZero:true } } }
        });
    })();
    <?php endif; ?>

    <?php if (!empty($reportData['monthly_conversions'])): ?>
    (function(){
        const ctx = document.getElementById('monthlyConvChart');
        const labels = <?php echo json_encode(array_map(fn($r)=>$r['month_name'], $reportData['monthly_conversions'])); ?>;
        const data   = <?php echo json_encode(array_map(fn($r)=>(float)$r['conversion_rate'], $reportData['monthly_conversions'])); ?>;
        if (ctx) new Chart(ctx, {
            type: 'line',
            data: { labels, datasets: [{ label:'Conversion %', data, borderWidth:3, fill:false, tension:0.2 }]},
            options: { responsive:true, maintainAspectRatio:false, scales:{ y:{ beginAtZero:true, max: 100 } } }
        });
    })();
    <?php endif; ?>
<?php endif; ?>

<?php if ($reportType === 'trends'): ?>
    <?php if (!empty($reportData['daily_trends'])): ?>
    (function(){
        const ctx = document.getElementById('dailyChart');
        const labels = <?php echo json_encode(array_map(fn($r)=>formatDisplayDate($r['date']), $reportData['daily_trends'])); ?>;
        const data   = <?php echo json_encode(array_map(fn($r)=>(int)$r['visitor_count'], $reportData['daily_trends'])); ?>;
        if (ctx) new Chart(ctx, {
            type: 'line',
            data: { labels, datasets: [{ label:'Visitors', data, borderWidth:3, fill:true, tension:0.3 }]},
            options: { responsive:true, maintainAspectRatio:false, scales:{ y:{ beginAtZero:true } } }
        });
    })();
    <?php endif; ?>

    <?php if (!empty($reportData['day_of_week_trends'])): ?>
    (function(){
        const ctx = document.getElementById('dowChart');
        const rows = <?php echo json_encode($reportData['day_of_week_trends']); ?>;
        rows.sort((a,b)=> (parseInt(a.day_number) || 0) - (parseInt(b.day_number) || 0));
        const labels = rows.map(r=>r.day_name);
        const data   = rows.map(r=>parseInt(r.visitor_count)||0);
        if (ctx) new Chart(ctx, {
            type: 'bar',
            data: { labels, datasets: [{ label:'Visitors', data, borderWidth:2 }]},
            options: { responsive:true, maintainAspectRatio:false, scales:{ y:{ beginAtZero:true } } }
        });
    })();
    <?php endif; ?>
<?php endif; ?>
});
</script>

<style>
.bg-pink { background-color: #e91e63 !important; }
</style>

<?php include_once '../../includes/footer.php'; ?>

<?php
/* ============================================================
 * Helper Functions (Data)
 * ============================================================ */

function getDateRangeForPeriod($period, $customFrom = '', $customTo = '') {
    $now = new DateTime();
    switch ($period) {
        case 'this_week':
            $start = clone $now; $start->modify('monday this week');
            return [$start->format('Y-m-d'), $now->format('Y-m-d')];
        case 'this_month':
            return [$now->format('Y-m-01'), $now->format('Y-m-d')];
        case 'this_quarter':
            $q = ceil($now->format('n') / 3);
            $start = new DateTime($now->format('Y') . '-' . ((($q - 1) * 3) + 1) . '-01');
            return [$start->format('Y-m-d'), $now->format('Y-m-d')];
        case 'this_year':
            return [$now->format('Y-01-01'), $now->format('Y-m-d')];
        case 'last_month':
            $lm = clone $now; $lm->modify('-1 month');
            return [$lm->format('Y-m-01'), $lm->format('Y-m-t')];
        case 'custom':
            $from = !empty($customFrom) ? $customFrom : (clone $now)->modify('-30 days')->format('Y-m-d');
            $to   = !empty($customTo)   ? $customTo   : $now->format('Y-m-d');
            return [$from, $to];
        default:
            return [$now->format('Y-m-01'), $now->format('Y-m-d')];
    }
}

function buildVisitorWhere(&$params, $dateFrom, $dateTo, $filters) {
    $w = ["v.visit_date BETWEEN ? AND ?"]; $params = [$dateFrom, $dateTo];
    if (!empty($filters['status']))    { $w[] = "v.status = ?";               $params[] = $filters['status']; }
    if (!empty($filters['gender']))    { $w[] = "v.gender = ?";               $params[] = $filters['gender']; }
    if (!empty($filters['age_group'])) { $w[] = "v.age_group = ?";            $params[] = $filters['age_group']; }
    if (!empty($filters['source']))    { $w[] = "v.how_heard_about_us = ?";   $params[] = $filters['source']; }
    if (!empty($filters['search'])) {
        $w[] = "(v.first_name LIKE ? OR v.last_name LIKE ? OR v.phone LIKE ?)";
        $term = '%' . $filters['search'] . '%';
        array_push($params, $term, $term, $term);
    }
    return implode(' AND ', $w);
}

/* -------- Summary -------- */
function getVisitorSummaryData($db, $dateFrom, $dateTo, $filters) {
    $params = [];
    $where = buildVisitorWhere($params, $dateFrom, $dateTo, $filters);

    $summary = $db->executeQuery("
        SELECT 
            COUNT(*) AS total_visitors,
            COUNT(CASE WHEN v.gender='male' THEN 1 END)   AS male_visitors,
            COUNT(CASE WHEN v.gender='female' THEN 1 END) AS female_visitors,
            COUNT(CASE WHEN v.status='new_visitor' THEN 1 END)      AS new_visitors,
            COUNT(CASE WHEN v.status='follow_up' THEN 1 END)         AS in_followup,
            COUNT(CASE WHEN v.status='regular_attender' THEN 1 END)  AS regular_attenders,
            COUNT(CASE WHEN v.status='converted_member' THEN 1 END)  AS converted_members,
            COUNT(CASE WHEN v.age_group='child' THEN 1 END)  AS children,
            COUNT(CASE WHEN v.age_group='youth' THEN 1 END)  AS youth,
            COUNT(CASE WHEN v.age_group='adult' THEN 1 END)  AS adults,
            COUNT(CASE WHEN v.age_group='senior' THEN 1 END) AS seniors
        FROM visitors v
        WHERE $where
    ", $params)->fetch() ?: [];

    $sources = $db->executeQuery("
        SELECT COALESCE(v.how_heard_about_us,'Not specified') AS source, COUNT(*) AS visitor_count
        FROM visitors v
        WHERE $where
        GROUP BY v.how_heard_about_us
        ORDER BY visitor_count DESC
    ", $params)->fetchAll() ?: [];

    $trends = $db->executeQuery("
        SELECT 
            YEARWEEK(v.visit_date) AS year_week,
            DATE(DATE_SUB(v.visit_date, INTERVAL WEEKDAY(v.visit_date) DAY)) AS week_start,
            COUNT(*) AS visitor_count
        FROM visitors v
        WHERE $where
        GROUP BY YEARWEEK(v.visit_date)
        ORDER BY year_week ASC
    ", $params)->fetchAll() ?: [];

    $pending = $db->executeQuery("
        SELECT 
            v.id, v.first_name, v.last_name, v.visit_date, v.status,
            COUNT(vf.id) AS followup_count, MAX(vf.followup_date) AS last_followup
        FROM visitors v
        LEFT JOIN visitor_followups vf ON v.id = vf.visitor_id
        WHERE $where AND v.status IN ('new_visitor','follow_up')
        GROUP BY v.id
        ORDER BY v.visit_date DESC
        LIMIT 20
    ", $params)->fetchAll() ?: [];

    return [
        'summary'         => $summary,
        'sources'         => $sources,
        'trends'          => $trends,
        'pending_followup'=> $pending
    ];
}

/* -------- Detailed -------- */
function getDetailedVisitorData($db, $dateFrom, $dateTo, $filters) {
    $params = [];
    $where = buildVisitorWhere($params, $dateFrom, $dateTo, $filters);

    $rows = $db->executeQuery("
        SELECT 
            v.*,
            u.first_name AS recorded_by_first, u.last_name AS recorded_by_last,
            m.first_name AS followup_person_first, m.last_name AS followup_person_last,
            COUNT(vf.id) AS followup_count, MAX(vf.followup_date) AS last_followup_date
        FROM visitors v
        LEFT JOIN users u   ON v.created_by = u.id
        LEFT JOIN members m ON v.assigned_followup_person_id = m.id
        LEFT JOIN visitor_followups vf ON v.id = vf.visitor_id
        WHERE $where
        GROUP BY v.id
        ORDER BY v.visit_date DESC, v.created_at DESC
    ", $params)->fetchAll() ?: [];

    return ['visitors' => $rows, 'total_count' => count($rows)];
}

/* -------- Follow-up -------- */
function getFollowupReportData($db, $dateFrom, $dateTo, $filters) {
    $query = "
        SELECT 
            v.id AS visitor_id,
            v.visitor_number,
            v.first_name, v.last_name, v.phone, v.visit_date, v.status,
            v.assigned_followup_person_id,
            m.first_name AS followup_person_first, m.last_name AS followup_person_last,
            COUNT(vf.id) AS total_followups,
            MAX(vf.followup_date) AS last_followup_date,
            MIN(vf.followup_date) AS first_followup_date,
            DATEDIFF(CURDATE(), v.visit_date) AS days_since_visit,
            CASE 
                WHEN MAX(vf.followup_date) IS NULL THEN 'No follow-up'
                WHEN DATEDIFF(CURDATE(), MAX(vf.followup_date)) > 14 THEN 'Overdue'
                WHEN DATEDIFF(CURDATE(), MAX(vf.followup_date)) > 7  THEN 'Due soon'
                ELSE 'Up to date'
            END AS followup_status
        FROM visitors v
        LEFT JOIN members m ON v.assigned_followup_person_id = m.id
        LEFT JOIN visitor_followups vf ON v.id = vf.visitor_id
        WHERE v.visit_date BETWEEN ? AND ?
          AND v.status IN ('new_visitor','follow_up','regular_attender')
        GROUP BY v.id
        ORDER BY 
            CASE 
                WHEN MAX(vf.followup_date) IS NULL THEN 1
                WHEN DATEDIFF(CURDATE(), MAX(vf.followup_date)) > 14 THEN 2
                WHEN DATEDIFF(CURDATE(), MAX(vf.followup_date)) > 7  THEN 3
                ELSE 4
            END,
            v.visit_date DESC
    ";
    $followupData = $db->executeQuery($query, [$dateFrom, $dateTo])->fetchAll() ?: [];

    $statusSummary = ['no_followup'=>0,'overdue'=>0,'due_soon'=>0,'up_to_date'=>0];
    foreach ($followupData as $v) {
        switch ($v['followup_status']) {
            case 'No follow-up': $statusSummary['no_followup']++; break;
            case 'Overdue':      $statusSummary['overdue']++;     break;
            case 'Due soon':     $statusSummary['due_soon']++;    break;
            case 'Up to date':   $statusSummary['up_to_date']++;  break;
        }
    }

    return [
        'followup_data'  => $followupData,
        'status_summary' => $statusSummary,
        'total_visitors' => count($followupData)
    ];
}

/* -------- Conversion Analysis -------- */
function getConversionAnalysisData($db, $dateFrom, $dateTo, $filters) {
    // Funnel (single UNION query with correct param count: 14)
    $conversionQuery = "
        SELECT 'New Visitors' AS stage, COUNT(*) AS count, 100.0 AS percentage
        FROM visitors v
        WHERE v.visit_date BETWEEN ? AND ?

        UNION ALL
        SELECT 'In Follow-up' AS stage, COUNT(*) AS count,
               (COUNT(*) * 100.0 / (SELECT COUNT(*) FROM visitors WHERE visit_date BETWEEN ? AND ?)) AS percentage
        FROM visitors v
        WHERE v.visit_date BETWEEN ? AND ?
          AND v.status IN ('follow_up','regular_attender','converted_member')

        UNION ALL
        SELECT 'Regular Attenders' AS stage, COUNT(*) AS count,
               (COUNT(*) * 100.0 / (SELECT COUNT(*) FROM visitors WHERE visit_date BETWEEN ? AND ?)) AS percentage
        FROM visitors v
        WHERE v.visit_date BETWEEN ? AND ?
          AND v.status IN ('regular_attender','converted_member')

        UNION ALL
        SELECT 'Converted Members' AS stage, COUNT(*) AS count,
               (COUNT(*) * 100.0 / (SELECT COUNT(*) FROM visitors WHERE visit_date BETWEEN ? AND ?)) AS percentage
        FROM visitors v
        WHERE v.visit_date BETWEEN ? AND ?
          AND v.status = 'converted_member'
    ";
    $params = [
        $dateFrom, $dateTo,        // New Visitors
        $dateFrom, $dateTo, $dateFrom, $dateTo,   // In Follow-up
        $dateFrom, $dateTo, $dateFrom, $dateTo,   // Regular Attenders
        $dateFrom, $dateTo, $dateFrom, $dateTo    // Converted Members
    ];
    $conversionFunnel = $db->executeQuery($conversionQuery, $params)->fetchAll() ?: [];

    // Monthly conversion rates
    $monthlyConversions = $db->executeQuery("
        SELECT 
            DATE_FORMAT(v.visit_date, '%Y-%m') AS month,
            DATE_FORMAT(v.visit_date, '%M %Y') AS month_name,
            COUNT(*) AS total_visitors,
            COUNT(CASE WHEN v.status='converted_member' THEN 1 END) AS converted,
            ROUND((COUNT(CASE WHEN v.status='converted_member' THEN 1 END) * 100.0 / COUNT(*)), 2) AS conversion_rate
        FROM visitors v
        WHERE v.visit_date BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(v.visit_date, '%Y-%m')
        ORDER BY month ASC
    ", [$dateFrom, $dateTo])->fetchAll() ?: [];

    // Conversion by source (min volume filter)
    $sourceConversions = $db->executeQuery("
        SELECT 
            COALESCE(v.how_heard_about_us,'Not specified') AS source,
            COUNT(*) AS total_visitors,
            COUNT(CASE WHEN v.status='converted_member' THEN 1 END) AS converted,
            ROUND((COUNT(CASE WHEN v.status='converted_member' THEN 1 END) * 100.0 / COUNT(*)), 2) AS conversion_rate
        FROM visitors v
        WHERE v.visit_date BETWEEN ? AND ?
        GROUP BY v.how_heard_about_us
        HAVING COUNT(*) >= 3
        ORDER BY conversion_rate DESC
    ", [$dateFrom, $dateTo])->fetchAll() ?: [];

    return [
        'conversion_funnel'     => $conversionFunnel,
        'monthly_conversions'   => $monthlyConversions,
        'source_conversions'    => $sourceConversions
    ];
}

/* -------- Trends -------- */
function getVisitorTrendsData($db, $dateFrom, $dateTo, $filters) {
    $dailyTrends = $db->executeQuery("
        SELECT v.visit_date AS date,
               COUNT(*) AS visitor_count,
               COUNT(CASE WHEN v.gender='male' THEN 1 END) AS male_count,
               COUNT(CASE WHEN v.gender='female' THEN 1 END) AS female_count
        FROM visitors v
        WHERE v.visit_date BETWEEN ? AND ?
        GROUP BY v.visit_date
        ORDER BY v.visit_date ASC
    ", [$dateFrom, $dateTo])->fetchAll() ?: [];

    $dayOfWeekTrends = $db->executeQuery("
        SELECT DAYNAME(v.visit_date) AS day_name,
               DAYOFWEEK(v.visit_date) AS day_number,
               COUNT(*) AS visitor_count
        FROM visitors v
        WHERE v.visit_date BETWEEN ? AND ?
        GROUP BY DAYOFWEEK(v.visit_date), DAYNAME(v.visit_date)
        ORDER BY day_number
    ", [$dateFrom, $dateTo])->fetchAll() ?: [];

    $ageGroupTrends = $db->executeQuery("
        SELECT v.age_group,
               COUNT(*) AS visitor_count,
               ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM visitors WHERE visit_date BETWEEN ? AND ?), 1) AS percentage
        FROM visitors v
        WHERE v.visit_date BETWEEN ? AND ?
        GROUP BY v.age_group
        ORDER BY visitor_count DESC
    ", [$dateFrom, $dateTo, $dateFrom, $dateTo])->fetchAll() ?: [];

    return [
        'daily_trends'       => $dailyTrends,
        'day_of_week_trends' => $dayOfWeekTrends,
        'age_group_trends'   => $ageGroupTrends
    ];
}

