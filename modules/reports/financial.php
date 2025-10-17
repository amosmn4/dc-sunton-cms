<?php
/**
 * Financial Reports
 * Deliverance Church Management System
 *
 * Generates financial summary, income, expense, comparison, donor and category reports.
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

requireLogin();
if (!hasPermission('reports') && !hasPermission('finance')) {
    header('Location: ' . BASE_URL . 'modules/dashboard/?error=access_denied');
    exit();
}

/* ----------------------------
 * Page configuration
 * ---------------------------- */
$page_title = 'Financial Reports';
$page_icon  = 'fas fa-money-bill-wave';
$breadcrumb = [
    ['title' => 'Reports', 'url' => 'index.php'],
    ['title' => 'Financial Reports']
];

$db = Database::getInstance();

/* ----------------------------
 * Read & sanitize inputs
 * ---------------------------- */
$reportType = sanitizeInput($_GET['type']   ?? 'summary');
$export     = sanitizeInput($_GET['export'] ?? '');   // '1' when exporting
$format     = sanitizeInput($_GET['format'] ?? 'html'); // csv|excel|pdf|html
$period     = sanitizeInput($_GET['period'] ?? 'this_month');

$filters = [
    'period'         => $period,
    'date_from'      => sanitizeInput($_GET['date_from'] ?? ''),
    'date_to'        => sanitizeInput($_GET['date_to'] ?? ''),
    'category'       => sanitizeInput($_GET['category'] ?? ''),
    'payment_method' => sanitizeInput($_GET['payment_method'] ?? ''),
    'min_amount'     => sanitizeInput($_GET['min_amount'] ?? ''),
    'max_amount'     => sanitizeInput($_GET['max_amount'] ?? ''),
    'search'         => sanitizeInput($_GET['search'] ?? ''),
];

/* ----------------------------
 * Date helpers
 * ---------------------------- */
function getDateRange(string $period, string $customFrom = '', string $customTo = ''): array {
    $now = new DateTime();

    switch ($period) {
        case 'today':
            return [$now->format('Y-m-d'), $now->format('Y-m-d')];

        case 'this_week':
            $start = (clone $now)->modify('monday this week');
            return [$start->format('Y-m-d'), $now->format('Y-m-d')];

        case 'this_month':
            return [$now->format('Y-m-01'), $now->format('Y-m-d')];

        case 'this_quarter':
            $q = (int)ceil(((int)$now->format('n')) / 3);
            $start = new DateTime($now->format('Y') . '-' . (((($q - 1) * 3) + 1)) . '-01');
            return [$start->format('Y-m-d'), $now->format('Y-m-d')];

        case 'this_year':
            return [$now->format('Y-01-01'), $now->format('Y-m-d')];

        case 'last_month':
            $d = (clone $now)->modify('first day of last month');
            $from = $d->format('Y-m-01');
            $to   = $d->format('Y-m-t');
            return [$from, $to];

        case 'last_quarter':
            $currentQ = (int)ceil(((int)$now->format('n')) / 3);
            $year = (int)$now->format('Y');
            $lastQ = $currentQ - 1;
            if ($lastQ <= 0) { $lastQ = 4; $year -= 1; }
            $start = new DateTime($year . '-' . (((($lastQ - 1) * 3) + 1)) . '-01');
            $end   = (clone $start)->modify('+3 months -1 day');
            return [$start->format('Y-m-d'), $end->format('Y-m-d')];

        case 'last_year':
            $y = (int)$now->format('Y') - 1;
            return ["$y-01-01", "$y-12-31"];

        case 'custom':
            $from = DateTime::createFromFormat('Y-m-d', $customFrom);
            $to   = DateTime::createFromFormat('Y-m-d', $customTo);
            if ($from && $to && $from <= $to) {
                return [$from->format('Y-m-d'), $to->format('Y-m-d')];
            }
            // fallthrough → default

        default:
            // default to this month so far
            return [$now->format('Y-m-01'), $now->format('Y-m-d')];
    }
}

list($dateFrom, $dateTo) = getDateRange($period, $filters['date_from'], $filters['date_to']);

/* ----------------------------
 * Report data helpers
 * ---------------------------- */

/** Financial Summary (grouped by category + totals + trends) */
function getFinancialSummaryData($db, string $dateFrom, string $dateTo, array $filters): array {
    // Income by category
    $incomeQuery = "
        SELECT ic.name AS category,
               COUNT(i.id) AS transaction_count,
               SUM(i.amount) AS total_amount,
               AVG(i.amount) AS avg_amount
        FROM income i
        JOIN income_categories ic ON i.category_id = ic.id
        WHERE i.transaction_date BETWEEN ? AND ?
          AND i.status = 'verified'
        GROUP BY ic.id, ic.name
        ORDER BY total_amount DESC
    ";
    $income = $db->executeQuery($incomeQuery, [$dateFrom, $dateTo])->fetchAll() ?: [];

    // Expenses by category
    $expenseQuery = "
        SELECT ec.name AS category,
               COUNT(e.id) AS transaction_count,
               SUM(e.amount) AS total_amount,
               AVG(e.amount) AS avg_amount
        FROM expenses e
        JOIN expense_categories ec ON e.category_id = ec.id
        WHERE e.expense_date BETWEEN ? AND ?
          AND e.status = 'paid'
        GROUP BY ec.id, ec.name
        ORDER BY total_amount DESC
    ";
    $expenses = $db->executeQuery($expenseQuery, [$dateFrom, $dateTo])->fetchAll() ?: [];

    // Totals
    $totalIncome   = (float)array_sum(array_map(static fn($r) => (float)$r['total_amount'], $income));
    $totalExpenses = (float)array_sum(array_map(static fn($r) => (float)$r['total_amount'], $expenses));
    $netIncome     = $totalIncome - $totalExpenses;

    // Monthly trends (income & expenses per month)
    $trendsQuery = "
        SELECT DATE_FORMAT(date_field, '%Y-%m') AS month, 'income' AS type, SUM(amount) AS total
        FROM (
            SELECT transaction_date AS date_field, amount
            FROM income
            WHERE transaction_date BETWEEN ? AND ? AND status = 'verified'
        ) t
        GROUP BY DATE_FORMAT(date_field, '%Y-%m')

        UNION ALL

        SELECT DATE_FORMAT(date_field, '%Y-%m') AS month, 'expenses' AS type, SUM(amount) AS total
        FROM (
            SELECT expense_date AS date_field, amount
            FROM expenses
            WHERE expense_date BETWEEN ? AND ? AND status = 'paid'
        ) t
        GROUP BY DATE_FORMAT(date_field, '%Y-%m')
        ORDER BY month ASC
    ";
    $trends = $db->executeQuery($trendsQuery, [$dateFrom, $dateTo, $dateFrom, $dateTo])->fetchAll() ?: [];

    return [
        'income'   => $income,
        'expenses' => $expenses,
        'totals'   => ['income' => $totalIncome, 'expenses' => $totalExpenses, 'net' => $netIncome],
        'trends'   => $trends,
    ];
}

/** Income Report (with filters & payment method breakdown) */
function getIncomeReportData($db, string $dateFrom, string $dateTo, array $filters): array {
    $where = ["i.transaction_date BETWEEN ? AND ?", "i.status = 'verified'"];
    $params = [$dateFrom, $dateTo];

    if (!empty($filters['category'])) {
        $where[] = "i.category_id = ?";
        $params[] = $filters['category'];
    }
    if (!empty($filters['payment_method'])) {
        $where[] = "i.payment_method = ?";
        $params[] = $filters['payment_method'];
    }
    if ((string)$filters['min_amount'] !== '') {
        $where[] = "i.amount >= ?";
        $params[] = $filters['min_amount'];
    }
    if ((string)$filters['max_amount'] !== '') {
        $where[] = "i.amount <= ?";
        $params[] = $filters['max_amount'];
    }
    if (!empty($filters['search'])) {
        $where[] = "(i.donor_name LIKE ? OR i.description LIKE ? OR i.reference_number LIKE ?)";
        $s = '%' . $filters['search'] . '%';
        array_push($params, $s, $s, $s);
    }

    $sql = "
        SELECT i.*, ic.name AS category_name, u.first_name, u.last_name
        FROM income i
        JOIN income_categories ic ON i.category_id = ic.id
        LEFT JOIN users u ON u.id = i.recorded_by
        WHERE ".implode(' AND ', $where)."
        ORDER BY i.transaction_date DESC, i.created_at DESC
    ";
    $rows = $db->executeQuery($sql, $params)->fetchAll() ?: [];

    $totalAmount = (float)array_sum(array_map(static fn($r)=> (float)$r['amount'], $rows));
    $count = count($rows);
    $avgAmount = $count ? $totalAmount / $count : 0.0;

    $paymentMethods = [];
    foreach ($rows as $r) {
        $m = $r['payment_method'] ?? 'unknown';
        if (!isset($paymentMethods[$m])) $paymentMethods[$m] = ['count'=>0,'amount'=>0.0];
        $paymentMethods[$m]['count']++;
        $paymentMethods[$m]['amount'] += (float)$r['amount'];
    }

    return [
        'transactions' => $rows,
        'summary' => [
            'total_amount' => $totalAmount,
            'transaction_count' => $count,
            'average_amount' => $avgAmount,
        ],
        'payment_methods' => $paymentMethods
    ];
}

/** Expense Report (with filters) */
function getExpenseReportData($db, string $dateFrom, string $dateTo, array $filters): array {
    $where = ["e.expense_date BETWEEN ? AND ?", "e.status = 'paid'"];
    $params = [$dateFrom, $dateTo];

    if (!empty($filters['category'])) {
        $where[] = "e.category_id = ?";
        $params[] = $filters['category'];
    }
    if (!empty($filters['payment_method'])) {
        $where[] = "e.payment_method = ?";
        $params[] = $filters['payment_method'];
    }
    if ((string)$filters['min_amount'] !== '') {
        $where[] = "e.amount >= ?";
        $params[] = $filters['min_amount'];
    }
    if ((string)$filters['max_amount'] !== '') {
        $where[] = "e.amount <= ?";
        $params[] = $filters['max_amount'];
    }
    if (!empty($filters['search'])) {
        $where[] = "(e.vendor_name LIKE ? OR e.description LIKE ? OR e.reference_number LIKE ?)";
        $s = '%' . $filters['search'] . '%';
        array_push($params, $s, $s, $s);
    }

    $sql = "
        SELECT e.*,
               ec.name AS category_name,
               u1.first_name AS requested_by_first, u1.last_name AS requested_by_last,
               u2.first_name AS approved_by_first,  u2.last_name AS approved_by_last
        FROM expenses e
        JOIN expense_categories ec ON e.category_id = ec.id
        LEFT JOIN users u1 ON u1.id = e.requested_by
        LEFT JOIN users u2 ON u2.id = e.approved_by
        WHERE ".implode(' AND ', $where)."
        ORDER BY e.expense_date DESC, e.created_at DESC
    ";
    $rows = $db->executeQuery($sql, $params)->fetchAll() ?: [];

    $totalAmount = (float)array_sum(array_map(static fn($r)=> (float)$r['amount'], $rows));
    $count = count($rows);
    $avgAmount = $count ? $totalAmount / $count : 0.0;

    // Category breakdown
    $categories = [];
    foreach ($rows as $r) {
        $c = $r['category_name'] ?? 'Uncategorized';
        if (!isset($categories[$c])) $categories[$c] = ['count'=>0,'amount'=>0.0];
        $categories[$c]['count']++;
        $categories[$c]['amount'] += (float)$r['amount'];
    }

    return [
        'transactions' => $rows,
        'summary' => [
            'total_amount' => $totalAmount,
            'transaction_count' => $count,
            'average_amount' => $avgAmount,
        ],
        'categories' => $categories,
    ];
}

/** Income vs Expenses (monthly + categories) */
function getComparisonReportData($db, string $dateFrom, string $dateTo, array $filters): array {
    $sql = "
        SELECT
            DATE_FORMAT(m.month_date, '%Y-%m')   AS month,
            DATE_FORMAT(m.month_date, '%M %Y')   AS month_name,
            COALESCE(i.income_total, 0)          AS income,
            COALESCE(x.expense_total, 0)         AS expenses,
            COALESCE(i.income_total,0)-COALESCE(x.expense_total,0) AS net
        FROM (
            SELECT DISTINCT DATE_FORMAT(transaction_date, '%Y-%m-01') AS month_date
            FROM income
            WHERE transaction_date BETWEEN ? AND ?
            UNION
            SELECT DISTINCT DATE_FORMAT(expense_date, '%Y-%m-01') AS month_date
            FROM expenses
            WHERE expense_date BETWEEN ? AND ?
        ) m
        LEFT JOIN (
            SELECT DATE_FORMAT(transaction_date, '%Y-%m-01') AS md, SUM(amount) AS income_total
            FROM income
            WHERE transaction_date BETWEEN ? AND ? AND status='verified'
            GROUP BY DATE_FORMAT(transaction_date, '%Y-%m-01')
        ) i ON i.md = m.month_date
        LEFT JOIN (
            SELECT DATE_FORMAT(expense_date, '%Y-%m-01') AS md, SUM(amount) AS expense_total
            FROM expenses
            WHERE expense_date BETWEEN ? AND ? AND status='paid'
            GROUP BY DATE_FORMAT(expense_date, '%Y-%m-01')
        ) x ON x.md = m.month_date
        ORDER BY m.month_date ASC
    ";
    $monthly = $db->executeQuery($sql, [$dateFrom,$dateTo,$dateFrom,$dateTo,$dateFrom,$dateTo,$dateFrom,$dateTo])->fetchAll() ?: [];

    $catSql = "
        SELECT 'income'  AS type, ic.name AS category, SUM(i.amount) AS total
        FROM income i JOIN income_categories ic ON ic.id = i.category_id
        WHERE i.transaction_date BETWEEN ? AND ? AND i.status='verified'
        GROUP BY ic.id, ic.name

        UNION ALL

        SELECT 'expense' AS type, ec.name AS category, SUM(e.amount) AS total
        FROM expenses e JOIN expense_categories ec ON ec.id = e.category_id
        WHERE e.expense_date BETWEEN ? AND ? AND e.status='paid'
        GROUP BY ec.id, ec.name

        ORDER BY type ASC, total DESC
    ";
    $categories = $db->executeQuery($catSql, [$dateFrom,$dateTo,$dateFrom,$dateTo])->fetchAll() ?: [];

    return ['monthly' => $monthly, 'categories' => $categories];
}

/** Donor Report */
function getDonorReportData($db, string $dateFrom, string $dateTo, array $filters): array {
    $sql = "
        SELECT
            COALESCE(i.donor_name, 'Anonymous') AS donor_name,
            i.donor_phone,
            i.donor_email,
            COUNT(i.id)   AS donation_count,
            SUM(i.amount) AS total_donated,
            AVG(i.amount) AS average_donation,
            MIN(i.transaction_date) AS first_donation,
            MAX(i.transaction_date) AS last_donation,
            GROUP_CONCAT(DISTINCT ic.name ORDER BY ic.name SEPARATOR ', ') AS categories
        FROM income i
        JOIN income_categories ic ON ic.id = i.category_id
        WHERE i.transaction_date BETWEEN ? AND ?
          AND i.status='verified'
          AND i.is_anonymous = 0
        GROUP BY COALESCE(i.donor_name, 'Anonymous'), i.donor_phone, i.donor_email
        HAVING total_donated > 0
        ORDER BY total_donated DESC
    ";
    $donors = $db->executeQuery($sql, [$dateFrom, $dateTo])->fetchAll() ?: [];

    $totalDonors   = count($donors);
    $totalDonated  = (float)array_sum(array_map(static fn($r)=>(float)$r['total_donated'], $donors));
    $avgPerDonor   = $totalDonors ? $totalDonated / $totalDonors : 0.0;

    $freq = ['one_time'=>0,'occasional'=>0,'regular'=>0,'frequent'=>0];
    foreach ($donors as $d) {
        $c = (int)$d['donation_count'];
        if ($c === 1) $freq['one_time']++;
        elseif ($c <= 3) $freq['occasional']++;
        elseif ($c <= 10) $freq['regular']++;
        else $freq['frequent']++;
    }

    return [
        'donors' => $donors,
        'summary' => [
            'total_donors' => $totalDonors,
            'total_donated' => $totalDonated,
            'average_per_donor' => $avgPerDonor,
        ],
        'frequency_analysis' => $freq,
    ];
}

/** Category Report */
function getCategoryReportData($db, string $dateFrom, string $dateTo, array $filters): array {
    $incomeSql = "
        SELECT ic.name AS category, ic.description,
               COUNT(i.id) AS transaction_count,
               SUM(i.amount) AS total_amount,
               AVG(i.amount) AS average_amount,
               MIN(i.amount) AS min_amount,
               MAX(i.amount) AS max_amount
        FROM income_categories ic
        LEFT JOIN income i
          ON i.category_id = ic.id
         AND i.transaction_date BETWEEN ? AND ?
         AND i.status='verified'
        WHERE ic.is_active = 1
        GROUP BY ic.id, ic.name, ic.description
        ORDER BY total_amount DESC
    ";
    $incomeCats = $db->executeQuery($incomeSql, [$dateFrom,$dateTo])->fetchAll() ?: [];

    $expenseSql = "
        SELECT ec.name AS category, ec.description, ec.budget_limit,
               COUNT(e.id) AS transaction_count,
               SUM(e.amount) AS total_amount,
               AVG(e.amount) AS average_amount,
               MIN(e.amount) AS min_amount,
               MAX(e.amount) AS max_amount,
               CASE WHEN ec.budget_limit > 0 THEN (SUM(e.amount)/ec.budget_limit)*100 ELSE NULL END AS budget_usage_percent
        FROM expense_categories ec
        LEFT JOIN expenses e
          ON e.category_id = ec.id
         AND e.expense_date BETWEEN ? AND ?
         AND e.status='paid'
        WHERE ec.is_active = 1
        GROUP BY ec.id, ec.name, ec.description, ec.budget_limit
        ORDER BY total_amount DESC
    ";
    $expenseCats = $db->executeQuery($expenseSql, [$dateFrom,$dateTo])->fetchAll() ?: [];

    return ['income_categories'=>$incomeCats, 'expense_categories'=>$expenseCats];
}

/* ----------------------------
 * Run the selected report
 * ---------------------------- */
try {
    switch ($reportType) {
        case 'summary':
            $reportTitle = 'Financial Summary Report';
            $reportData  = getFinancialSummaryData($db, $dateFrom, $dateTo, $filters);
            break;

        case 'income':
            $reportTitle = 'Income Report';
            $reportData  = getIncomeReportData($db, $dateFrom, $dateTo, $filters);
            break;

        case 'expenses':
            $reportTitle = 'Expense Report';
            $reportData  = getExpenseReportData($db, $dateFrom, $dateTo, $filters);
            break;

        case 'comparison':
            $reportTitle = 'Income vs Expenses Comparison';
            $reportData  = getComparisonReportData($db, $dateFrom, $dateTo, $filters);
            break;

        case 'donors':
            $reportTitle = 'Donor Analysis Report';
            $reportData  = getDonorReportData($db, $dateFrom, $dateTo, $filters);
            break;

        case 'categories':
            $reportTitle = 'Category Analysis Report';
            $reportData  = getCategoryReportData($db, $dateFrom, $dateTo, $filters);
            break;

        default:
            $reportTitle = 'Financial Summary Report';
            $reportData  = getFinancialSummaryData($db, $dateFrom, $dateTo, $filters);
            break;
    }

    // Populate categories for filters
    $incomeCategories  = $db->executeQuery("SELECT id, name FROM income_categories  WHERE is_active = 1 ORDER BY name")->fetchAll() ?: [];
    $expenseCategories = $db->executeQuery("SELECT id, name FROM expense_categories WHERE is_active = 1 ORDER BY name")->fetchAll() ?: [];

} catch (Exception $e) {
    error_log("Error generating financial report: " . $e->getMessage());
    setFlashMessage('error', 'Error generating report: ' . $e->getMessage());
    $reportData = [];
    $incomeCategories = $expenseCategories = [];
}

/* ----------------------------
 * Export (CSV/Excel/PDF)
 * ---------------------------- */
if (!empty($export)) {
    // Flatten the $reportData to rows/headers based on report type
    $rows = [];
    $headers = [];
    $filename = 'financial_report_' . $reportType . '_' . date('Ymd_His');

    switch ($reportType) {
        case 'income':
            $headers = ['Date','Transaction ID','Category','Donor','Amount','Payment Method','Reference','Recorded By'];
            foreach (($reportData['transactions'] ?? []) as $t) {
                $rows[] = [
                    formatDisplayDate($t['transaction_date']),
                    $t['transaction_id'],
                    $t['category_name'],
                    $t['donor_name'] ?: 'Anonymous',
                    $t['amount'],
                    ucwords(str_replace('_',' ',$t['payment_method'] ?? '')),
                    $t['reference_number'] ?: '',
                    trim(($t['first_name'] ?? '').' '.($t['last_name'] ?? '')),
                ];
            }
            break;

        case 'expenses':
            $headers = ['Date','Transaction ID','Category','Vendor','Description','Amount','Payment Method','Requested By','Approved By'];
            foreach (($reportData['transactions'] ?? []) as $t) {
                $rows[] = [
                    formatDisplayDate($t['expense_date']),
                    $t['transaction_id'],
                    $t['category_name'],
                    $t['vendor_name'] ?: '',
                    $t['description'] ?: '',
                    $t['amount'],
                    ucwords(str_replace('_',' ',$t['payment_method'] ?? '')),
                    trim(($t['requested_by_first'] ?? '').' '.($t['requested_by_last'] ?? '')),
                    trim(($t['approved_by_first']  ?? '').' '.($t['approved_by_last']  ?? '')),
                ];
            }
            break;

        case 'summary':
            $headers = ['Type','Category','Transactions','Total Amount','Average Amount'];
            foreach (($reportData['income'] ?? []) as $r) {
                $rows[] = ['Income',$r['category'],$r['transaction_count'],$r['total_amount'],$r['avg_amount']];
            }
            foreach (($reportData['expenses'] ?? []) as $r) {
                $rows[] = ['Expense',$r['category'],$r['transaction_count'],$r['total_amount'],$r['avg_amount']];
            }
            break;

        case 'comparison':
            $headers = ['Month','Income','Expenses','Net'];
            foreach (($reportData['monthly'] ?? []) as $m) {
                $rows[] = [$m['month_name'],$m['income'],$m['expenses'],$m['net']];
            }
            break;

        case 'donors':
            $headers = ['Donor','Phone','Email','Donations','Total Donated','Average','First Donation','Last Donation','Categories'];
            foreach (($reportData['donors'] ?? []) as $d) {
                $rows[] = [
                    $d['donor_name'],
                    $d['donor_phone'],
                    $d['donor_email'],
                    $d['donation_count'],
                    $d['total_donated'],
                    $d['average_donation'],
                    formatDisplayDate($d['first_donation']),
                    formatDisplayDate($d['last_donation']),
                    $d['categories']
                ];
            }
            break;

        case 'categories':
            $headers = ['Type','Category','Description','Budget Limit','Transactions','Total Amount','Average','Min','Max','Budget Usage %'];
            foreach (($reportData['income_categories'] ?? []) as $c) {
                $rows[] = ['Income',$c['category'],$c['description'],'',
                    $c['transaction_count'],$c['total_amount'],$c['average_amount'],$c['min_amount'],$c['max_amount'],''];
            }
            foreach (($reportData['expense_categories'] ?? []) as $c) {
                $rows[] = ['Expense',$c['category'],$c['description'],$c['budget_limit'],
                    $c['transaction_count'],$c['total_amount'],$c['average_amount'],$c['min_amount'],$c['max_amount'], $c['budget_usage_percent']];
            }
            break;
    }

    if ($format === 'csv') {
        exportToCSV($rows, $headers, $filename . '.csv');
        exit;
    } elseif ($format === 'excel') {
        $filepath = generateExcelFile($rows, $headers, $filename . '.xlsx');
        header('Content-Type: application/json');
        echo json_encode(['success'=>true,'filename'=>basename($filepath),'path'=>$filepath]);
        exit;
    } elseif ($format === 'pdf' && function_exists('generatePDFFile')) {
        $filepath = generatePDFFile($rows, $headers, $filename . '.pdf');
        header('Content-Type: application/json');
        echo json_encode(['success'=>true,'filename'=>basename($filepath),'path'=>$filepath]);
        exit;
    } else {
        // Fallback to CSV if requested format unsupported
        exportToCSV($rows, $headers, $filename . '.csv');
        exit;
    }
}

/* ----------------------------
 * Render page (HTML)
 * ---------------------------- */
include_once '../../includes/header.php';
?>
<?php if (empty($export)): ?>
<div class="row">
    <!-- Filters -->
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="fas fa-filter me-2"></i>Report Filters
                </h6>
                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </div>
            <div class="collapse show" id="filterCollapse">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Report Type</label>
                            <select name="type" class="form-select" onchange="this.form.submit()">
                                <option value="summary"    <?= $reportType==='summary'    ? 'selected':''; ?>>Financial Summary</option>
                                <option value="income"     <?= $reportType==='income'     ? 'selected':''; ?>>Income Report</option>
                                <option value="expenses"   <?= $reportType==='expenses'   ? 'selected':''; ?>>Expense Report</option>
                                <option value="comparison" <?= $reportType==='comparison' ? 'selected':''; ?>>Income vs Expenses</option>
                                <option value="donors"     <?= $reportType==='donors'     ? 'selected':''; ?>>Donor Analysis</option>
                                <option value="categories" <?= $reportType==='categories' ? 'selected':''; ?>>Category Analysis</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Period</label>
                            <select name="period" class="form-select" onchange="toggleCustomDates()">
                                <option value="today"         <?= $period==='today'         ? 'selected':''; ?>>Today</option>
                                <option value="this_week"     <?= $period==='this_week'     ? 'selected':''; ?>>This Week</option>
                                <option value="this_month"    <?= $period==='this_month'    ? 'selected':''; ?>>This Month</option>
                                <option value="this_quarter"  <?= $period==='this_quarter'  ? 'selected':''; ?>>This Quarter</option>
                                <option value="this_year"     <?= $period==='this_year'     ? 'selected':''; ?>>This Year</option>
                                <option value="last_month"    <?= $period==='last_month'    ? 'selected':''; ?>>Last Month</option>
                                <option value="last_quarter"  <?= $period==='last_quarter'  ? 'selected':''; ?>>Last Quarter</option>
                                <option value="last_year"     <?= $period==='last_year'     ? 'selected':''; ?>>Last Year</option>
                                <option value="custom"        <?= $period==='custom'        ? 'selected':''; ?>>Custom Range</option>
                            </select>
                        </div>

                        <div class="col-md-3" id="customDates" style="<?= $period==='custom' ? '' : 'display:none'; ?>">
                            <label class="form-label">From Date</label>
                            <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($filters['date_from']); ?>">
                        </div>
                        <div class="col-md-3" id="customDatesTo" style="<?= $period==='custom' ? '' : 'display:none'; ?>">
                            <label class="form-label">To Date</label>
                            <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($filters['date_to']); ?>">
                        </div>

                        <?php if (in_array($reportType, ['income','expenses'], true)): ?>
                            <div class="col-md-3">
                                <label class="form-label">Category</label>
                                <select name="category" class="form-select">
                                    <option value="">All Categories</option>
                                    <?php $cats = $reportType==='income' ? $incomeCategories : $expenseCategories; ?>
                                    <?php foreach ($cats as $c): ?>
                                        <option value="<?= $c['id']; ?>" <?= $filters['category']==$c['id'] ? 'selected':''; ?>>
                                            <?= htmlspecialchars($c['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Payment Method</label>
                                <select name="payment_method" class="form-select">
                                    <option value="">All Methods</option>
                                    <?php foreach (PAYMENT_METHODS as $key => $name): ?>
                                        <option value="<?= $key; ?>" <?= $filters['payment_method']===$key ? 'selected':''; ?>>
                                            <?= $name; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">Min Amount</label>
                                <input type="number" name="min_amount" class="form-control" step="0.01" value="<?= htmlspecialchars($filters['min_amount']); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Max Amount</label>
                                <input type="number" name="max_amount" class="form-control" step="0.01" value="<?= htmlspecialchars($filters['max_amount']); ?>">
                            </div>
                        <?php endif; ?>

                        <?php if (in_array($reportType, ['income','expenses','donors'], true)): ?>
                            <div class="col-md-4">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" class="form-control" placeholder="Search..." value="<?= htmlspecialchars($filters['search']); ?>">
                            </div>
                        <?php endif; ?>

                        <div class="col-12">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-church-primary"><i class="fas fa-search me-2"></i>Generate Report</button>
                                <a href="?" class="btn btn-outline-secondary"><i class="fas fa-undo me-2"></i>Reset Filters</a>
                                <button type="button" class="btn btn-outline-info" onclick="printReport()"><i class="fas fa-print me-2"></i>Print</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Body -->
    <div class="col-12">
        <?php if ($reportType === 'summary'): ?>
            <div class="row">
                <!-- Summary cards -->
                <div class="col-md-4 mb-4">
                    <div class="card bg-success text-white">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">Total Income</h6>
                                <h4 class="mb-0"><?= formatCurrency($reportData['totals']['income'] ?? 0); ?></h4>
                            </div>
                            <i class="fas fa-arrow-up fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card bg-danger text-white">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">Total Expenses</h6>
                                <h4 class="mb-0"><?= formatCurrency($reportData['totals']['expenses'] ?? 0); ?></h4>
                            </div>
                            <i class="fas fa-arrow-down fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <?php $net = $reportData['totals']['net'] ?? 0; ?>
                    <div class="card <?= $net>=0 ? 'bg-primary' : 'bg-warning' ?> text-white">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">Net Income</h6>
                                <h4 class="mb-0"><?= formatCurrency($net); ?></h4>
                            </div>
                            <i class="fas fa-balance-scale fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Income & Expense by Category -->
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header"><h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Income by Category</h6></div>
                        <div class="card-body">
                            <?php if (!empty($reportData['income'])): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead><tr><th>Category</th><th class="text-end">Count</th><th class="text-end">Amount</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($reportData['income'] as $r): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($r['category']); ?></td>
                                                <td class="text-end"><?= number_format($r['transaction_count']); ?></td>
                                                <td class="text-end"><?= formatCurrency($r['total_amount']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-muted">No income data</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header"><h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Expenses by Category</h6></div>
                        <div class="card-body">
                            <?php if (!empty($reportData['expenses'])): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead><tr><th>Category</th><th class="text-end">Count</th><th class="text-end">Amount</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($reportData['expenses'] as $r): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($r['category']); ?></td>
                                                <td class="text-end"><?= number_format($r['transaction_count']); ?></td>
                                                <td class="text-end"><?= formatCurrency($r['total_amount']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-muted">No expense data</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($reportType === 'income'): ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-coins me-2"></i>Income Report
                        <span class="badge bg-success ms-2"><?= formatCurrency($reportData['summary']['total_amount'] ?? 0); ?></span>
                    </h5>
                    <div class="btn-group">
                        <button class="btn btn-sm btn-outline-success dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fas fa-download me-2"></i>Export
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET,['export'=>'1','format'=>'csv'])); ?>"><i class="fas fa-file-csv me-2"></i>CSV</a></li>
                            <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET,['export'=>'1','format'=>'excel'])); ?>"><i class="fas fa-file-excel me-2"></i>Excel</a></li>
                            <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET,['export'=>'1','format'=>'pdf'])); ?>"><i class="fas fa-file-pdf me-2"></i>PDF</a></li>
                        </ul>
                    </div>
                </div>
                <div class="card-body">
                    <!-- quick stats -->
                    <div class="row mb-4 text-center">
                        <div class="col-md-3">
                            <div class="h4 text-success mb-1"><?= formatCurrency($reportData['summary']['total_amount'] ?? 0); ?></div>
                            <div class="text-muted small">Total Amount</div>
                        </div>
                        <div class="col-md-3">
                            <div class="h4 text-primary mb-1"><?= number_format($reportData['summary']['transaction_count'] ?? 0); ?></div>
                            <div class="text-muted small">Transactions</div>
                        </div>
                        <div class="col-md-3">
                            <div class="h4 text-info mb-1"><?= formatCurrency($reportData['summary']['average_amount'] ?? 0); ?></div>
                            <div class="text-muted small">Average</div>
                        </div>
                        <div class="col-md-3">
                            <div class="h4 text-warning mb-1"><?= count($reportData['payment_methods'] ?? []); ?></div>
                            <div class="text-muted small">Payment Methods</div>
                        </div>
                    </div>

                    <?php if (!empty($reportData['transactions'])): ?>
                        <div class="table-responsive">
                            <table class="table table-hover data-table">
                                <thead>
                                    <tr>
                                        <th>Date</th><th>Transaction ID</th><th>Category</th><th>Donor</th>
                                        <th>Amount</th><th>Payment Method</th><th>Reference</th><th>Recorded By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($reportData['transactions'] as $t): ?>
                                    <tr>
                                        <td><?= formatDisplayDate($t['transaction_date']); ?></td>
                                        <td><code><?= htmlspecialchars($t['transaction_id']); ?></code></td>
                                        <td><span class="badge bg-primary"><?= htmlspecialchars($t['category_name']); ?></span></td>
                                        <td><?= htmlspecialchars($t['donor_name'] ?: 'Anonymous'); ?></td>
                                        <td><strong class="text-success"><?= formatCurrency($t['amount']); ?></strong></td>
                                        <td><?= ucwords(str_replace('_',' ', $t['payment_method'] ?? '')); ?></td>
                                        <td><?= htmlspecialchars($t['reference_number'] ?: '-'); ?></td>
                                        <td><?= htmlspecialchars(trim(($t['first_name'] ?? '').' '.($t['last_name'] ?? ''))); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4 text-muted">No income records match your filters.</div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($reportType === 'expenses'): ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-receipt me-2"></i>Expense Report
                        <span class="badge bg-danger ms-2"><?= formatCurrency($reportData['summary']['total_amount'] ?? 0); ?></span>
                    </h5>
                    <div class="btn-group">
                        <button class="btn btn-sm btn-outline-success dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fas fa-download me-2"></i>Export
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET,['export'=>'1','format'=>'csv'])); ?>"><i class="fas fa-file-csv me-2"></i>CSV</a></li>
                            <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET,['export'=>'1','format'=>'excel'])); ?>"><i class="fas fa-file-excel me-2"></i>Excel</a></li>
                            <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET,['export'=>'1','format'=>'pdf'])); ?>"><i class="fas fa-file-pdf me-2"></i>PDF</a></li>
                        </ul>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($reportData['transactions'])): ?>
                        <div class="table-responsive">
                            <table class="table table-hover data-table">
                                <thead>
                                    <tr>
                                        <th>Date</th><th>Transaction ID</th><th>Category</th><th>Vendor</th><th>Description</th>
                                        <th>Amount</th><th>Payment Method</th><th>Requested By</th><th>Approved By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($reportData['transactions'] as $t): ?>
                                    <tr>
                                        <td><?= formatDisplayDate($t['expense_date']); ?></td>
                                        <td><code><?= htmlspecialchars($t['transaction_id']); ?></code></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($t['category_name']); ?></span></td>
                                        <td><?= htmlspecialchars($t['vendor_name'] ?: '-'); ?></td>
                                        <td><?= htmlspecialchars(truncateText($t['description'], 50)); ?></td>
                                        <td><strong class="text-danger"><?= formatCurrency($t['amount']); ?></strong></td>
                                        <td><?= ucwords(str_replace('_',' ', $t['payment_method'] ?? '')); ?></td>
                                        <td><?= htmlspecialchars(trim(($t['requested_by_first'] ?? '').' '.($t['requested_by_last'] ?? ''))); ?></td>
                                        <td><?= htmlspecialchars(trim(($t['approved_by_first']  ?? '').' '.($t['approved_by_last']  ?? ''))); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4 text-muted">No expense records match your filters.</div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($reportType === 'comparison'): ?>
            <div class="row">
                <div class="col-12 mb-4">
                    <div class="card">
                        <div class="card-header"><h6 class="mb-0"><i class="fas fa-chart-line me-2"></i>Monthly Income vs Expenses</h6></div>
                        <div class="card-body"><canvas id="comparisonChart" height="400"></canvas></div>
                    </div>
                </div>

                <div class="col-md-8 mb-4">
                    <div class="card">
                        <div class="card-header"><h6 class="mb-0"><i class="fas fa-table me-2"></i>Monthly Breakdown</h6></div>
                        <div class="card-body">
                            <?php if (!empty($reportData['monthly'])): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead><tr><th>Month</th><th class="text-end">Income</th><th class="text-end">Expenses</th><th class="text-end">Net</th><th class="text-end">Difference</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($reportData['monthly'] as $m): ?>
                                            <tr>
                                                <td><strong><?= $m['month_name']; ?></strong></td>
                                                <td class="text-end text-success"><?= formatCurrency($m['income']); ?></td>
                                                <td class="text-end text-danger"><?= formatCurrency($m['expenses']); ?></td>
                                                <?php $net = $m['net']; ?>
                                                <td class="text-end <?= $net>=0?'text-success':'text-danger'; ?>"><?= formatCurrency($net); ?></td>
                                                <td class="text-end">
                                                    <?php if ($m['income'] > 0): ?>
                                                        <?php $pct = ($net / $m['income'])*100; ?>
                                                        <span class="badge <?= $pct>=0?'bg-success':'bg-danger'; ?>"><?= number_format($pct,1); ?>%</span>
                                                    <?php else: ?>-<?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-muted py-3">No comparison data</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-4">
                    <div class="card">
                        <div class="card-header"><h6 class="mb-0"><i class="fas fa-calculator me-2"></i>Period Summary</h6></div>
                        <div class="card-body">
                            <?php
                            $incomeSum = array_sum(array_column($reportData['monthly'] ?? [], 'income'));
                            $expenseSum= array_sum(array_column($reportData['monthly'] ?? [], 'expenses'));
                            $netTotal  = $incomeSum - $expenseSum;
                            $monthsCnt = max(count($reportData['monthly'] ?? []), 1);
                            ?>
                            <div class="d-flex justify-content-between mb-2"><span>Total Income:</span><strong class="text-success"><?= formatCurrency($incomeSum); ?></strong></div>
                            <div class="d-flex justify-content-between mb-2"><span>Total Expenses:</span><strong class="text-danger"><?= formatCurrency($expenseSum); ?></strong></div>
                            <div class="d-flex justify-content-between mb-3 border-bottom pb-2"><span><strong>Net Income:</strong></span><strong class="<?= $netTotal>=0?'text-success':'text-danger'; ?>"><?= formatCurrency($netTotal); ?></strong></div>
                            <div class="d-flex justify-content-between mb-2"><span>Avg Monthly Income:</span><span class="text-success"><?= formatCurrency($incomeSum/$monthsCnt); ?></span></div>
                            <div class="d-flex justify-content-between"><span>Avg Monthly Expenses:</span><span class="text-danger"><?= formatCurrency($expenseSum/$monthsCnt); ?></span></div>
                            <?php if ($incomeSum>0): ?>
                                <div class="mt-3">
                                    <div class="d-flex justify-content-between"><span>Expense Ratio:</span><span><?= number_format(($expenseSum/$incomeSum)*100,1); ?>%</span></div>
                                    <div class="progress mt-2" style="height:6px;"><div class="progress-bar bg-warning" style="width: <?= min(($expenseSum/$incomeSum)*100,100); ?>%"></div></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($reportType === 'donors'): ?>
            <div class="row">
                <div class="col-12 mb-4">
                    <div class="row">
                        <div class="col-md-3"><div class="card text-center"><div class="card-body"><h4 class="text-primary"><?= number_format($reportData['summary']['total_donors'] ?? 0); ?></h4><p class="text-muted mb-0">Total Donors</p></div></div></div>
                        <div class="col-md-3"><div class="card text-center"><div class="card-body"><h4 class="text-success"><?= formatCurrency($reportData['summary']['total_donated'] ?? 0); ?></h4><p class="text-muted mb-0">Total Donated</p></div></div></div>
                        <div class="col-md-3"><div class="card text-center"><div class="card-body"><h4 class="text-info"><?= formatCurrency($reportData['summary']['average_per_donor'] ?? 0); ?></h4><p class="text-muted mb-0">Average per Donor</p></div></div></div>
                        <div class="col-md-3"><div class="card text-center"><div class="card-body"><h4 class="text-warning"><?= $reportData['frequency_analysis']['frequent'] ?? 0; ?></h4><p class="text-muted mb-0">Regular Donors</p></div></div></div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="card">
                        <div class="card-header"><h6 class="mb-0"><i class="fas fa-users me-2"></i>Donor Details</h6></div>
                        <div class="card-body">
                            <?php if (!empty($reportData['donors'])): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover data-table">
                                        <thead>
                                            <tr>
                                                <th>Donor Name</th><th>Contact</th><th>Donations</th><th>Total Donated</th>
                                                <th>Average</th><th>First Donation</th><th>Last Donation</th><th>Categories</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($reportData['donors'] as $d): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($d['donor_name']); ?></strong></td>
                                                <td>
                                                    <?php if (!empty($d['donor_phone'])): ?><div><i class="fas fa-phone me-1"></i><?= htmlspecialchars($d['donor_phone']); ?></div><?php endif; ?>
                                                    <?php if (!empty($d['donor_email'])): ?><div><i class="fas fa-envelope me-1"></i><?= htmlspecialchars($d['donor_email']); ?></div><?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary"><?= number_format($d['donation_count']); ?></span>
                                                    <?php
                                                        $c=(int)$d['donation_count']; $f='one-time';
                                                        if ($c>10) $f='frequent'; elseif ($c>3) $f='regular'; elseif ($c>1) $f='occasional';
                                                    ?>
                                                    <br><small class="text-muted"><?= ucfirst($f); ?></small>
                                                </td>
                                                <td><strong class="text-success"><?= formatCurrency($d['total_donated']); ?></strong></td>
                                                <td><?= formatCurrency($d['average_donation']); ?></td>
                                                <td><?= formatDisplayDate($d['first_donation']); ?></td>
                                                <td><?= formatDisplayDate($d['last_donation']); ?></td>
                                                <td>
                                                    <?php foreach (explode(', ', $d['categories'] ?? '') as $c): if ($c==='') continue; ?>
                                                        <span class="badge bg-light text-dark me-1"><?= htmlspecialchars($c); ?></span>
                                                    <?php endforeach; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4 text-muted">No donor data.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($reportType === 'categories'): ?>
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header"><h6 class="mb-0"><i class="fas fa-tags me-2"></i>Income Categories</h6></div>
                        <div class="card-body">
                            <?php if (!empty($reportData['income_categories'])): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead><tr><th>Category</th><th class="text-end">Txns</th><th class="text-end">Total</th><th class="text-end">Avg</th><th class="text-end">Min</th><th class="text-end">Max</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($reportData['income_categories'] as $c): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($c['category']); ?></td>
                                                <td class="text-end"><?= number_format($c['transaction_count']); ?></td>
                                                <td class="text-end"><?= formatCurrency($c['total_amount']); ?></td>
                                                <td class="text-end"><?= formatCurrency($c['average_amount']); ?></td>
                                                <td class="text-end"><?= formatCurrency($c['min_amount']); ?></td>
                                                <td class="text-end"><?= formatCurrency($c['max_amount']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?><div class="text-muted text-center">No data</div><?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header"><h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Expense Categories</h6></div>
                        <div class="card-body">
                            <?php if (!empty($reportData['expense_categories'])): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead><tr><th>Category</th><th class="text-end">Txns</th><th class="text-end">Total</th><th class="text-end">Avg</th><th class="text-end">Budget</th><th class="text-end">Usage %</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($reportData['expense_categories'] as $c): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($c['category']); ?></td>
                                                <td class="text-end"><?= number_format($c['transaction_count']); ?></td>
                                                <td class="text-end"><?= formatCurrency($c['total_amount']); ?></td>
                                                <td class="text-end"><?= formatCurrency($c['average_amount']); ?></td>
                                                <td class="text-end"><?= $c['budget_limit']!==null ? formatCurrency($c['budget_limit']) : '-'; ?></td>
                                                <td class="text-end"><?= $c['budget_usage_percent']!==null ? number_format($c['budget_usage_percent'],1).'%' : '-'; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?><div class="text-muted text-center">No data</div><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php // Scripts (only when not exporting) ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    toggleCustomDates();
    <?php if ($reportType === 'comparison' && !empty($reportData['monthly'])): ?>
    initializeComparisonChart();
    <?php endif; ?>
});

function toggleCustomDates() {
    const period = document.querySelector('select[name="period"]').value;
    document.getElementById('customDates').style.display   = (period === 'custom') ? 'block' : 'none';
    document.getElementById('customDatesTo').style.display = (period === 'custom') ? 'block' : 'none';
}

<?php if ($reportType === 'comparison' && !empty($reportData['monthly'])): ?>
function initializeComparisonChart() {
    const ctx = document.getElementById('comparisonChart').getContext('2d');
    const months  = <?= json_encode(array_column($reportData['monthly'], 'month_name')); ?>;
    const income  = <?= json_encode(array_map('floatval', array_column($reportData['monthly'], 'income'))); ?>;
    const expense = <?= json_encode(array_map('floatval', array_column($reportData['monthly'], 'expenses'))); ?>;

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: months,
            datasets: [
                { label: 'Income',   data: income,  borderColor: '#28a745', backgroundColor: 'rgba(40,167,69,.1)',  borderWidth: 3, fill:false, tension: .4 },
                { label: 'Expenses', data: expense, borderColor: '#dc3545', backgroundColor: 'rgba(220,53,69,.1)', borderWidth: 3, fill:false, tension: .4 },
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { position:'top' },
                tooltip: { callbacks: {
                    label: function(ctx){ return ctx.dataset.label + ': <?= CURRENCY_SYMBOL; ?> ' + Number(ctx.parsed.y).toLocaleString(); }
                }}
            },
            scales: {
                y: { beginAtZero: true, ticks: { callback: function(v){ return '<?= CURRENCY_SYMBOL; ?> ' + Number(v).toLocaleString(); } } }
            }
        }
    });
}
<?php endif; ?>

function printReport(){ window.print(); }
</script>
<?php endif; ?>

<?php include_once '../../includes/footer.php'; ?>
