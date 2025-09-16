<?php
/**
 * Financial Reports
 * Deliverance Church Management System
 * 
 * Generate comprehensive financial reports and analytics
 */

// Include configuration and functions
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Check authentication and permissions
requireLogin();
if (!hasPermission('finance')) {
    setFlashMessage('error', 'Access denied. You do not have permission to view financial reports.');
    redirect(BASE_URL . 'modules/dashboard/');
}

// Page configuration
$page_title = 'Financial Reports';
$page_icon = 'fas fa-chart-line';
$page_description = 'Comprehensive financial reports and analytics';

$breadcrumb = [
    ['title' => 'Finance', 'url' => 'index.php'],
    ['title' => 'Financial Reports']
];

// Get report parameters
$reportType = $_GET['report'] ?? 'summary';
$startDate = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$endDate = $_GET['end_date'] ?? date('Y-m-t'); // Last day of current month
$category = $_GET['category'] ?? '';
$format = $_GET['format'] ?? 'html';

try {
    $db = Database::getInstance();
    
    // Generate report data based on type
    $reportData = [];
    
    switch ($reportType) {
        case 'summary':
            $reportData = generateSummaryReport($db, $startDate, $endDate);
            break;
        case 'income_analysis':
            $reportData = generateIncomeAnalysisReport($db, $startDate, $endDate, $category);
            break;
        case 'expense_analysis':
            $reportData = generateExpenseAnalysisReport($db, $startDate, $endDate, $category);
            break;
        case 'budget_performance':
            $reportData = generateBudgetPerformanceReport($db, $startDate, $endDate);
            break;
        case 'cash_flow':
            $reportData = generateCashFlowReport($db, $startDate, $endDate);
            break;
        case 'yearly_comparison':
            $reportData = generateYearlyComparisonReport($db, date('Y', strtotime($startDate)));
            break;
        default:
            $reportData = generateSummaryReport($db, $startDate, $endDate);
    }
    
    // Handle export formats
    if ($format !== 'html') {
        handleReportExport($reportData, $reportType, $format, $startDate, $endDate);
        exit;
    }
    
    // Get categories for filters
    $stmt = $db->executeQuery("SELECT * FROM income_categories WHERE is_active = 1 ORDER BY name");
    $incomeCategories = $stmt->fetchAll();
    
    $stmt = $db->executeQuery("SELECT * FROM expense_categories WHERE is_active = 1 ORDER BY name");
    $expenseCategories = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Financial reports error: " . $e->getMessage());
    $reportData = [];
    $incomeCategories = [];
    $expenseCategories = [];
    setFlashMessage('error', 'Error generating financial reports.');
}

/**
 * Generate Summary Report
 */
function generateSummaryReport($db, $startDate, $endDate) {
    $report = [
        'title' => 'Financial Summary Report',
        'period' => formatDisplayDate($startDate) . ' to ' . formatDisplayDate($endDate),
        'generated_at' => date('Y-m-d H:i:s'),
        'sections' => []
    ];
    
    // Income Summary
    $stmt = $db->executeQuery("
        SELECT 
            ic.name,
            COUNT(i.id) as transaction_count,
            SUM(i.amount) as total_amount
        FROM income i
        JOIN income_categories ic ON i.category_id = ic.id
        WHERE i.transaction_date BETWEEN ? AND ?
        AND i.status = 'verified'
        GROUP BY ic.id, ic.name
        ORDER BY total_amount DESC
    ", [$startDate, $endDate]);
    $incomeByCategory = $stmt->fetchAll();
    
    $totalIncome = array_sum(array_column($incomeByCategory, 'total_amount'));
    
    // Expense Summary
    $stmt = $db->executeQuery("
        SELECT 
            ec.name,
            COUNT(e.id) as transaction_count,
            SUM(e.amount) as total_amount
        FROM expenses e
        JOIN expense_categories ec ON e.category_id = ec.id
        WHERE e.expense_date BETWEEN ? AND ?
        AND e.status IN ('approved', 'paid')
        GROUP BY ec.id, ec.name
        ORDER BY total_amount DESC
    ", [$startDate, $endDate]);
    $expensesByCategory = $stmt->fetchAll();
    
    $totalExpenses = array_sum(array_column($expensesByCategory, 'total_amount'));
    
    // Summary metrics
    $report['sections']['summary'] = [
        'title' => 'Summary Metrics',
        'data' => [
            'total_income' => $totalIncome,
            'total_expenses' => $totalExpenses,
            'net_position' => $totalIncome - $totalExpenses,
            'income_transactions' => array_sum(array_column($incomeByCategory, 'transaction_count')),
            'expense_transactions' => array_sum(array_column($expensesByCategory, 'transaction_count'))
        ]
    ];
    
    $report['sections']['income'] = [
        'title' => 'Income by Category',
        'data' => $incomeByCategory
    ];
    
    $report['sections']['expenses'] = [
        'title' => 'Expenses by Category',
        'data' => $expensesByCategory
    ];
    
    return $report;
}

/**
 * Generate Income Analysis Report
 */
function generateIncomeAnalysisReport($db, $startDate, $endDate, $category = null) {
    $report = [
        'title' => 'Income Analysis Report',
        'period' => formatDisplayDate($startDate) . ' to ' . formatDisplayDate($endDate),
        'generated_at' => date('Y-m-d H:i:s'),
        'sections' => []
    ];
    
    // Build where clause for category filter
    $whereClause = "";
    $params = [$startDate, $endDate];
    
    if (!empty($category)) {
        $whereClause = " AND ic.id = ?";
        $params[] = $category;
    }
    
    // Monthly income trend
    $stmt = $db->executeQuery("
        SELECT 
            DATE_FORMAT(i.transaction_date, '%Y-%m') as month,
            SUM(i.amount) as total_amount,
            COUNT(i.id) as transaction_count,
            AVG(i.amount) as average_amount
        FROM income i
        JOIN income_categories ic ON i.category_id = ic.id
        WHERE i.transaction_date BETWEEN ? AND ?
        AND i.status = 'verified'
        $whereClause
        GROUP BY DATE_FORMAT(i.transaction_date, '%Y-%m')
        ORDER BY month
    ", $params);
    $monthlyTrend = $stmt->fetchAll();
    
    // Payment method analysis
    $stmt = $db->executeQuery("
        SELECT 
            i.payment_method,
            COUNT(i.id) as transaction_count,
            SUM(i.amount) as total_amount,
            AVG(i.amount) as average_amount
        FROM income i
        JOIN income_categories ic ON i.category_id = ic.id
        WHERE i.transaction_date BETWEEN ? AND ?
        AND i.status = 'verified'
        $whereClause
        GROUP BY i.payment_method
        ORDER BY total_amount DESC
    ", $params);
    $paymentMethods = $stmt->fetchAll();
    
    // Top donors
    $stmt = $db->executeQuery("
        SELECT 
            i.donor_name,
            COUNT(i.id) as donation_count,
            SUM(i.amount) as total_donated,
            AVG(i.amount) as average_donation
        FROM income i
        JOIN income_categories ic ON i.category_id = ic.id
        WHERE i.transaction_date BETWEEN ? AND ?
        AND i.status = 'verified'
        AND i.donor_name IS NOT NULL
        AND i.donor_name != ''
        $whereClause
        GROUP BY i.donor_name
        ORDER BY total_donated DESC
        LIMIT 20
    ", $params);
    $topDonors = $stmt->fetchAll();
    
    $report['sections']['monthly_trend'] = [
        'title' => 'Monthly Income Trend',
        'data' => $monthlyTrend
    ];
    
    $report['sections']['payment_methods'] = [
        'title' => 'Income by Payment Method',
        'data' => $paymentMethods
    ];
    
    $report['sections']['top_donors'] = [
        'title' => 'Top Donors',
        'data' => $topDonors
    ];
    
    return $report;
}

/**
 * Generate Expense Analysis Report
 */
function generateExpenseAnalysisReport($db, $startDate, $endDate, $category = null) {
    $report = [
        'title' => 'Expense Analysis Report',
        'period' => formatDisplayDate($startDate) . ' to ' . formatDisplayDate($endDate),
        'generated_at' => date('Y-m-d H:i:s'),
        'sections' => []
    ];
    
    $whereClause = "";
    $params = [$startDate, $endDate];
    
    if (!empty($category)) {
        $whereClause = " AND ec.id = ?";
        $params[] = $category;
    }
    
    // Monthly expense trend
    $stmt = $db->executeQuery("
        SELECT 
            DATE_FORMAT(e.expense_date, '%Y-%m') as month,
            SUM(e.amount) as total_amount,
            COUNT(e.id) as transaction_count,
            AVG(e.amount) as average_amount
        FROM expenses e
        JOIN expense_categories ec ON e.category_id = ec.id
        WHERE e.expense_date BETWEEN ? AND ?
        AND e.status IN ('approved', 'paid')
        $whereClause
        GROUP BY DATE_FORMAT(e.expense_date, '%Y-%m')
        ORDER BY month
    ", $params);
    $monthlyTrend = $stmt->fetchAll();
    
    // Top vendors
    $stmt = $db->executeQuery("
        SELECT 
            e.vendor_name,
            COUNT(e.id) as transaction_count,
            SUM(e.amount) as total_paid,
            AVG(e.amount) as average_payment
        FROM expenses e
        JOIN expense_categories ec ON e.category_id = ec.id
        WHERE e.expense_date BETWEEN ? AND ?
        AND e.status IN ('approved', 'paid')
        AND e.vendor_name IS NOT NULL
        AND e.vendor_name != ''
        $whereClause
        GROUP BY e.vendor_name
        ORDER BY total_paid DESC
        LIMIT 20
    ", $params);
    $topVendors = $stmt->fetchAll();
    
    // Approval status analysis
    $stmt = $db->executeQuery("
        SELECT 
            e.status,
            COUNT(e.id) as transaction_count,
            SUM(e.amount) as total_amount
        FROM expenses e
        JOIN expense_categories ec ON e.category_id = ec.id
        WHERE e.expense_date BETWEEN ? AND ?
        $whereClause
        GROUP BY e.status
        ORDER BY total_amount DESC
    ", $params);
    $statusAnalysis = $stmt->fetchAll();
    
    $report['sections']['monthly_trend'] = [
        'title' => 'Monthly Expense Trend',
        'data' => $monthlyTrend
    ];
    
    $report['sections']['top_vendors'] = [
        'title' => 'Top Vendors/Payees',
        'data' => $topVendors
    ];
    
    $report['sections']['status_analysis'] = [
        'title' => 'Expense Status Analysis',
        'data' => $statusAnalysis
    ];
    
    return $report;
}

/**
 * Generate Budget Performance Report
 */
function generateBudgetPerformanceReport($db, $startDate, $endDate) {
    $report = [
        'title' => 'Budget Performance Report',
        'period' => formatDisplayDate($startDate) . ' to ' . formatDisplayDate($endDate),
        'generated_at' => date('Y-m-d H:i:s'),
        'sections' => []
    ];
    
    // Get categories with budgets and actual spending
    $stmt = $db->executeQuery("
        SELECT 
            ec.name,
            ec.budget_limit,
            COALESCE(SUM(e.amount), 0) as actual_spent,
            COUNT(e.id) as transaction_count
        FROM expense_categories ec
        LEFT JOIN expenses e ON ec.id = e.category_id 
            AND e.expense_date BETWEEN ? AND ?
            AND e.status IN ('approved', 'paid')
        WHERE ec.budget_limit > 0
        GROUP BY ec.id, ec.name, ec.budget_limit
        ORDER BY ec.name
    ", [$startDate, $endDate]);
    $budgetPerformance = $stmt->fetchAll();
    
    // Calculate performance metrics
    foreach ($budgetPerformance as &$item) {
        $item['budget_remaining'] = $item['budget_limit'] - $item['actual_spent'];
        $item['budget_used_percentage'] = $item['budget_limit'] > 0 ? 
            ($item['actual_spent'] / $item['budget_limit']) * 100 : 0;
        $item['status'] = $item['budget_used_percentage'] > 100 ? 'over_budget' :
                         ($item['budget_used_percentage'] > 90 ? 'near_limit' : 'within_budget');
    }
    
    $report['sections']['budget_performance'] = [
        'title' => 'Budget vs Actual Performance',
        'data' => $budgetPerformance
    ];
    
    return $report;
}

/**
 * Generate Cash Flow Report
 */
function generateCashFlowReport($db, $startDate, $endDate) {
    $report = [
        'title' => 'Cash Flow Report',
        'period' => formatDisplayDate($startDate) . ' to ' . formatDisplayDate($endDate),
        'generated_at' => date('Y-m-d H:i:s'),
        'sections' => []
    ];
    
    // Daily cash flow
    $stmt = $db->executeQuery("
        SELECT 
            date_val as date,
            COALESCE(income_amount, 0) as income,
            COALESCE(expense_amount, 0) as expenses,
            (COALESCE(income_amount, 0) - COALESCE(expense_amount, 0)) as net_flow
        FROM (
            SELECT DATE(transaction_date) as date_val, SUM(amount) as income_amount
            FROM income 
            WHERE transaction_date BETWEEN ? AND ? AND status = 'verified'
            GROUP BY DATE(transaction_date)
        ) income_data
        RIGHT JOIN (
            SELECT generate_series(?, ?, '1 day'::interval)::date as date_val
        ) date_series USING (date_val)
        LEFT JOIN (
            SELECT DATE(expense_date) as date_val, SUM(amount) as expense_amount
            FROM expenses 
            WHERE expense_date BETWEEN ? AND ? AND status IN ('approved', 'paid')
            GROUP BY DATE(expense_date)
        ) expense_data USING (date_val)
        ORDER BY date_val
    ", [$startDate, $endDate, $startDate, $endDate, $startDate, $endDate, $startDate, $endDate]);
    $dailyCashFlow = $stmt->fetchAll();
    
    $report['sections']['cash_flow'] = [
        'title' => 'Daily Cash Flow',
        'data' => $dailyCashFlow
    ];
    
    return $report;
}

/**
 * Generate Yearly Comparison Report
 */
function generateYearlyComparisonReport($db, $year) {
    $report = [
        'title' => 'Yearly Comparison Report',
        'period' => "Year $year vs Previous Years",
        'generated_at' => date('Y-m-d H:i:s'),
        'sections' => []
    ];
    
    // Yearly comparison data
    $stmt = $db->executeQuery("
        SELECT 
            YEAR(transaction_date) as year,
            'income' as type,
            SUM(amount) as total_amount,
            COUNT(*) as transaction_count
        FROM income 
        WHERE YEAR(transaction_date) BETWEEN ? AND ?
        AND status = 'verified'
        GROUP BY YEAR(transaction_date)
        
        UNION ALL
        
        SELECT 
            YEAR(expense_date) as year,
            'expense' as type,
            SUM(amount) as total_amount,
            COUNT(*) as transaction_count
        FROM expenses 
        WHERE YEAR(expense_date) BETWEEN ? AND ?
        AND status IN ('approved', 'paid')
        GROUP BY YEAR(expense_date)
        
        ORDER BY year DESC, type
    ", [$year - 2, $year, $year - 2, $year]);
    $yearlyComparison = $stmt->fetchAll();
    
    $report['sections']['yearly_comparison'] = [
        'title' => 'Three Year Comparison',
        'data' => $yearlyComparison
    ];
    
    return $report;
}

/**
 * Handle report export
 */
function handleReportExport($reportData, $reportType, $format, $startDate, $endDate) {
    $filename = "financial_report_{$reportType}_" . date('Y-m-d');
    
    switch ($format) {
        case 'pdf':
            exportReportToPDF($reportData, $filename);
            break;
        case 'excel':
            exportReportToExcel($reportData, $filename);
            break;
        case 'csv':
            exportReportToCSV($reportData, $filename);
            break;
    }
}

// Include header
include '../../includes/header.php';
?>

<!-- Financial Reports Content -->
<div class="financial-reports">
    <!-- Report Controls -->
    <div class="card mb-4">
        <div class="card-header">
            <h6 class="mb-0">
                <i class="fas fa-filter me-2"></i>Report Parameters
            </h6>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <!-- Report Type -->
                <div class="col-md-3">
                    <label for="report" class="form-label">Report Type</label>
                    <select class="form-select" id="report" name="report">
                        <option value="summary" <?php echo $reportType === 'summary' ? 'selected' : ''; ?>>Summary Report</option>
                        <option value="income_analysis" <?php echo $reportType === 'income_analysis' ? 'selected' : ''; ?>>Income Analysis</option>
                        <option value="expense_analysis" <?php echo $reportType === 'expense_analysis' ? 'selected' : ''; ?>>Expense Analysis</option>
                        <option value="budget_performance" <?php echo $reportType === 'budget_performance' ? 'selected' : ''; ?>>Budget Performance</option>
                        <option value="cash_flow" <?php echo $reportType === 'cash_flow' ? 'selected' : ''; ?>>Cash Flow</option>
                        <option value="yearly_comparison" <?php echo $reportType === 'yearly_comparison' ? 'selected' : ''; ?>>Yearly Comparison</option>
                    </select>
                </div>
                
                <!-- Date Range -->
                <div class="col-md-2">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" 
                           value="<?php echo htmlspecialchars($startDate); ?>">
                </div>
                
                <div class="col-md-2">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" 
                           value="<?php echo htmlspecialchars($endDate); ?>">
                </div>
                
                <!-- Category Filter -->
                <div class="col-md-2">
                    <label for="category" class="form-label">Category</label>
                    <select class="form-select" id="category" name="category">
                        <option value="">All Categories</option>
                        <optgroup label="Income Categories">
                            <?php foreach ($incomeCategories as $cat): ?>
                                <option value="inc_<?php echo $cat['id']; ?>" <?php echo $category === "inc_{$cat['id']}" ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Expense Categories">
                            <?php foreach ($expenseCategories as $cat): ?>
                                <option value="exp_<?php echo $cat['id']; ?>" <?php echo $category === "exp_{$cat['id']}" ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>
                
                <!-- Actions -->
                <div class="col-md-3 d-flex align-items-end">
                    <div class="btn-group w-100">
                        <button type="submit" class="btn btn-church-primary">
                            <i class="fas fa-chart-line me-1"></i>Generate Report
                        </button>
                        <button type="button" class="btn btn-outline-secondary dropdown-toggle dropdown-toggle-split" 
                                data-bs-toggle="dropdown">
                            <span class="visually-hidden">Export options</span>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" onclick="exportReport('pdf')">
                                <i class="fas fa-file-pdf me-2 text-danger"></i>Export as PDF
                            </a></li>
                            <li><a class="dropdown-item" href="#" onclick="exportReport('excel')">
                                <i class="fas fa-file-excel me-2 text-success"></i>Export as Excel
                            </a></li>
                            <li><a class="dropdown-item" href="#" onclick="exportReport('csv')">
                                <i class="fas fa-file-csv me-2 text-info"></i>Export as CSV
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#" onclick="window.print()">
                                <i class="fas fa-print me-2"></i>Print Report
                            </a></li>
                        </ul>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Report Content -->
    <?php if (!empty($reportData)): ?>
        <div class="report-content">
            <!-- Report Header -->
            <div class="card mb-4 no-print">
                <div class="card-body text-center">
                    <h3 class="text-church-blue"><?php echo htmlspecialchars($reportData['title']); ?></h3>
                    <p class="text-muted mb-0">
                        <i class="fas fa-calendar-alt me-1"></i>Period: <?php echo $reportData['period']; ?> |
                        <i class="fas fa-clock me-1"></i>Generated: <?php echo formatDisplayDateTime($reportData['generated_at']); ?>
                    </p>
                </div>
            </div>

            <!-- Summary Metrics (for summary report) -->
            <?php if (isset($reportData['sections']['summary'])): ?>
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card text-center">
                            <div class="stats-icon bg-success text-white mx-auto">
                                <i class="fas fa-arrow-up"></i>
                            </div>
                            <div class="stats-number text-success">
                                <?php echo formatCurrency($reportData['sections']['summary']['data']['total_income']); ?>
                            </div>
                            <div class="stats-label">Total Income</div>
                            <small class="text-muted">
                                <?php echo number_format($reportData['sections']['summary']['data']['income_transactions']); ?> transactions
                            </small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card text-center">
                            <div class="stats-icon bg-danger text-white mx-auto">
                                <i class="fas fa-arrow-down"></i>
                            </div>
                            <div class="stats-number text-danger">
                                <?php echo formatCurrency($reportData['sections']['summary']['data']['total_expenses']); ?>
                            </div>
                            <div class="stats-label">Total Expenses</div>
                            <small class="text-muted">
                                <?php echo number_format($reportData['sections']['summary']['data']['expense_transactions']); ?> transactions
                            </small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card text-center">
                            <?php $netPosition = $reportData['sections']['summary']['data']['net_position']; ?>
                            <div class="stats-icon <?php echo $netPosition >= 0 ? 'bg-primary' : 'bg-warning'; ?> text-white mx-auto">
                                <i class="fas fa-balance-scale"></i>
                            </div>
                            <div class="stats-number <?php echo $netPosition >= 0 ? 'text-primary' : 'text-warning'; ?>">
                                <?php echo formatCurrency(abs($netPosition)); ?>
                            </div>
                            <div class="stats-label">Net Position</div>
                            <small class="<?php echo $netPosition >= 0 ? 'text-success' : 'text-warning'; ?>">
                                <?php echo $netPosition >= 0 ? 'Surplus' : 'Deficit'; ?>
                            </small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card text-center">
                            <div class="stats-icon bg-info text-white mx-auto">
                                <i class="fas fa-percentage"></i>
                            </div>
                            <div class="stats-number text-info">
                                <?php 
                                $expenseRatio = $reportData['sections']['summary']['data']['total_income'] > 0 ? 
                                    ($reportData['sections']['summary']['data']['total_expenses'] / $reportData['sections']['summary']['data']['total_income']) * 100 : 0;
                                echo number_format($expenseRatio, 1) . '%';
                                ?>
                            </div>
                            <div class="stats-label">Expense Ratio</div>
                            <small class="text-muted">Expenses to Income</small>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Report Sections -->
            <?php foreach ($reportData['sections'] as $sectionKey => $section): ?>
                <?php if ($sectionKey === 'summary') continue; // Already displayed above ?>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-bar me-2"></i><?php echo htmlspecialchars($section['title']); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($section['data'])): ?>
                            <?php if ($sectionKey === 'budget_performance'): ?>
                                <!-- Budget Performance Table -->
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Category</th>
                                                <th>Budget</th>
                                                <th>Actual Spent</th>
                                                <th>Remaining</th>
                                                <th>Usage %</th>
                                                <th>Transactions</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($section['data'] as $item): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                                                    <td class="fw-bold"><?php echo formatCurrency($item['budget_limit']); ?></td>
                                                    <td class="text-danger"><?php echo formatCurrency($item['actual_spent']); ?></td>
                                                    <td class="<?php echo $item['budget_remaining'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                        <?php echo formatCurrency($item['budget_remaining']); ?>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="progress flex-grow-1 me-2" style="height: 15px;">
                                                                <?php 
                                                                $percentage = min(100, $item['budget_used_percentage']);
                                                                $progressColor = $item['status'] === 'over_budget' ? 'danger' : 
                                                                                ($item['status'] === 'near_limit' ? 'warning' : 'success');
                                                                ?>
                                                                <div class="progress-bar bg-<?php echo $progressColor; ?>" 
                                                                     style="width: <?php echo $percentage; ?>%">
                                                                </div>
                                                            </div>
                                                            <span class="small fw-bold"><?php echo number_format($item['budget_used_percentage'], 1); ?>%</span>
                                                        </div>
                                                    </td>
                                                    <td><?php echo number_format($item['transaction_count']); ?></td>
                                                    <td>
                                                        <?php if ($item['status'] === 'over_budget'): ?>
                                                            <span class="badge bg-danger">Over Budget</span>
                                                        <?php elseif ($item['status'] === 'near_limit'): ?>
                                                            <span class="badge bg-warning">Near Limit</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-success">Within Budget</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <!-- Generic data table -->
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <?php if (!empty($section['data'])): ?>
                                                    <?php foreach (array_keys($section['data'][0]) as $key): ?>
                                                        <th><?php echo ucwords(str_replace('_', ' ', $key)); ?></th>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($section['data'] as $row): ?>
                                                <tr>
                                                    <?php foreach ($row as $key => $value): ?>
                                                        <td>
                                                            <?php if (strpos($key, 'amount') !== false || strpos($key, 'total') !== false): ?>
                                                                <span class="fw-bold <?php echo strpos($key, 'income') !== false ? 'text-success' : 'text-danger'; ?>">
                                                                    <?php echo formatCurrency($value); ?>
                                                                </span>
                                                            <?php elseif (strpos($key, 'count') !== false): ?>
                                                                <span class="badge bg-info"><?php echo number_format($value); ?></span>
                                                            <?php elseif (strpos($key, 'date') !== false || strpos($key, 'month') !== false): ?>
                                                                <?php echo htmlspecialchars($value); ?>
                                                            <?php else: ?>
                                                                <?php echo htmlspecialchars($value); ?>
                                                            <?php endif; ?>
                                                        </td>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-chart-line fa-3x mb-3"></i>
                                <h6>No Data Available</h6>
                                <p>No data found for the selected criteria and period.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <!-- No Report Generated -->
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-chart-line fa-4x text-muted mb-4"></i>
                <h4 class="text-muted">Financial Reports</h4>
                <p class="text-muted">Select report parameters above and click "Generate Report" to view financial data and analytics.</p>
                <div class="mt-4">
                    <button type="button" class="btn btn-church-primary me-2" onclick="generateQuickReport('current_month')">
                        <i class="fas fa-calendar-alt me-1"></i>Current Month Report
                    </button>
                    <button type="button" class="btn btn-outline-primary me-2" onclick="generateQuickReport('last_month')">
                        <i class="fas fa-calendar-minus me-1"></i>Last Month Report
                    </button>
                    <button type="button" class="btn btn-outline-primary" onclick="generateQuickReport('ytd')">
                        <i class="fas fa-calendar-year me-1"></i>Year to Date Report
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-submit form when parameters change
    const reportForm = document.querySelector('form[method="GET"]');
    const formInputs = reportForm?.querySelectorAll('select, input[type="date"]');
    
    formInputs?.forEach(input => {
        input.addEventListener('change', ChurchCMS.debounce(function() {
            reportForm.submit();
        }, 500));
    });
    
    // Initialize charts if report data is available
    <?php if (!empty($reportData) && isset($reportData['sections'])): ?>
        initializeReportCharts();
    <?php endif; ?>
});

function generateQuickReport(period) {
    let startDate, endDate;
    const today = new Date();
    
    switch (period) {
        case 'current_month':
            startDate = new Date(today.getFullYear(), today.getMonth(), 1);
            endDate = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            break;
        case 'last_month':
            startDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            endDate = new Date(today.getFullYear(), today.getMonth(), 0);
            break;
        case 'ytd':
            startDate = new Date(today.getFullYear(), 0, 1);
            endDate = today;
            break;
    }
    
    const url = new URL(window.location);
    url.searchParams.set('report', 'summary');
    url.searchParams.set('start_date', startDate.toISOString().split('T')[0]);
    url.searchParams.set('end_date', endDate.toISOString().split('T')[0]);
    
    window.location.href = url.toString();
}

function exportReport(format) {
    const url = new URL(window.location);
    url.searchParams.set('format', format);
    
    ChurchCMS.showLoading('Generating export...');
    
    const link = document.createElement('a');
    link.href = url.toString();
    link.target = '_blank';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    setTimeout(() => {
        ChurchCMS.hideLoading();
    }, 3000);
}

function initializeReportCharts() {
    // This function will initialize charts based on report data
    // Implementation depends on the specific chart library and data structure
}
</script>

<?php
// Include footer
include '../../includes/footer.php';
?>
            SELECT DATE(expense_date) as date_val, SUM(amount) as expense_amount
            FROM expenses 
            WHERE expense_date BETWEEN ? AND ? AND status IN ('approved', 'paid')
            GROUP BY DATE(expense_date)
        ) expense_data USING (date_val)
        ORDER BY date_val
    ", [$startDate, $endDate, $startDate, $endDate, $startDate, $endDate]);
    
    // For MySQL compatibility (doesn't have generate_series)
    $stmt = $db->executeQuery("
        SELECT 
            date_val as date,
            COALESCE(income_amount, 0) as income,
            COALESCE(expense_amount, 0) as expenses,
            (COALESCE(income_amount, 0) - COALESCE(expense_amount, 0)) as net_flow
        FROM (
            SELECT DISTINCT DATE(transaction_date) as date_val FROM income WHERE transaction_date BETWEEN ? AND ?
            UNION
            SELECT DISTINCT DATE(expense_date) as date_val FROM expenses WHERE expense_date BETWEEN ? AND ?
        ) all_dates
        LEFT JOIN (
            SELECT DATE(transaction_date) as date_val, SUM(amount) as income_amount
            FROM income 
            WHERE transaction_date BETWEEN ? AND ? AND status = 'verified'
            GROUP BY DATE(transaction_date)
        ) income_data USING (date_val)
        LEFT JOIN (
            SELECT DATE(expense_date) as date_val, SUM(amount) as expense_amount
            FROM expenses 
            WHERE expense_date BETWEEN ? AND ? AND status IN ('approved', 'paid')
            GROUP BY DATE(expense_date)
        ) expense_data USING (date_val)
        ORDER BY date_val
    ", [$startDate, $endDate, $startDate, $endDate, $startDate, $endDate]);

    // For MySQL compatibility (doesn't have generate_series)
    $stmt = $db->executeQuery("
        SELECT 
            date_val as date,
            COALESCE(income_amount, 0) as income,
            COALESCE(expense_amount, 0) as expenses,
            (COALESCE(income_amount, 0) - COALESCE(expense_amount, 0)) as net_flow
        FROM (
            SELECT DISTINCT DATE(transaction_date) as date_val FROM income WHERE transaction_date BETWEEN ? AND ?
            UNION
            SELECT DISTINCT DATE(expense_date) as date_val FROM expenses WHERE expense_date BETWEEN ? AND ?
        ) all_dates
        LEFT JOIN (
            SELECT DATE(transaction_date) as date_val, SUM(amount) as income_amount
            FROM income 
            WHERE transaction_date BETWEEN ? AND ? AND status = 'verified'
            GROUP BY DATE(transaction_date)
        ) income_data USING (date_val)
        LEFT JOIN (
            SELECT DATE(expense_date) as date_val, SUM(amount) as expense_amount
            FROM expenses 
            WHERE expense_date BETWEEN ? AND ? AND status IN ('approved', 'paid')
            GROUP BY DATE(expense_date)
        ) expense_data USING (date_val)
        ORDER BY date_val
    ", [$startDate, $endDate, $startDate, $endDate, $startDate, $endDate]);

    // For MySQL compatibility (doesn't have generate_series)
    $stmt = $db->executeQuery("
        SELECT 
            date_val as date,
            COALESCE(income_amount, 0) as income,
            COALESCE(expense_amount, 0) as expenses,
            (COALESCE(income_amount, 0) - COALESCE(expense_amount, 0)) as net_flow
        FROM (
            SELECT DISTINCT DATE(transaction_date) as date_val FROM income WHERE transaction_date BETWEEN ? AND ?
            UNION
            SELECT DISTINCT DATE(expense_date) as date_val FROM expenses WHERE expense_date BETWEEN ? AND ?
        ) all_dates
        LEFT JOIN (
            SELECT DATE(transaction_date) as date_val, SUM(amount) as income_amount
            FROM income 
            WHERE transaction_date BETWEEN ? AND ? AND status = 'verified'
            GROUP BY DATE(transaction_date)
        ) income_data USING (date_val)
        LEFT JOIN (
            SELECT DATE(expense_date) as date_val, SUM(amount) as expense_amount
            FROM expenses 
            WHERE expense_date BETWEEN ? AND ? AND status IN ('approved', 'paid')
            GROUP BY DATE(expense_date)
        ) expense_data USING (date_val)
        ORDER BY date_val
    ", [$startDate, $endDate, $startDate, $endDate, $startDate, $endDate]);

    // For MySQL compatibility (doesn't have generate_series)
    $stmt = $db->executeQuery("
        SELECT 
            date_val as date,
            COALESCE(income_amount, 0) as income,
            COALESCE(expense_amount, 0) as expenses,
            (COALESCE(income_amount, 0) - COALESCE(expense_amount, 0)) as net_flow
        FROM (
            SELECT DISTINCT DATE(transaction_date) as date_val FROM income WHERE transaction_date BETWEEN ? AND ?
            UNION
            SELECT DISTINCT DATE(expense_date) as date_val FROM expenses WHERE expense_date BETWEEN ? AND ?
        ) all_dates
        LEFT JOIN (
            SELECT DATE(transaction_date) as date_val, SUM(amount) as income_amount
            FROM income 
            WHERE transaction_date BETWEEN ? AND ? AND status = 'verified'
            GROUP BY DATE(transaction_date)
        ) income_data USING (date_val)
        LEFT JOIN (
            SELECT DATE(expense_date) as date_val, SUM(amount) as expense_amount
            FROM expenses 
            WHERE expense_date BETWEEN ? AND ? AND status IN ('approved', 'paid')
            GROUP BY DATE(expense_date)
        ) expense_data USING (date_val)
        ORDER BY date_val
    ", [$startDate, $endDate, $startDate, $endDate, $startDate, $endDate]);

    // For MySQL compatibility (doesn't have generate_series)
    $stmt = $db->executeQuery("
        SELECT 
            date_val as date,
            COALESCE(income_amount, 0) as income,
            COALESCE(expense_amount, 0) as expenses,
            (COALESCE(income_amount, 0) - COALESCE(expense_amount, 0)) as net_flow
        FROM (
            SELECT DISTINCT DATE(transaction_date) as date_val FROM income WHERE transaction_date BETWEEN ? AND ?
            UNION
            SELECT DISTINCT DATE(expense_date) as date_val FROM expenses WHERE expense_date BETWEEN ? AND ?
        ) all_dates
        LEFT JOIN (
            SELECT DATE(transaction_date) as date_val, SUM(amount) as income_amount
            FROM income 
            WHERE transaction_date BETWEEN ? AND ? AND status = 'verified'
            GROUP BY DATE(transaction_date)
        ) income_data USING (date_val)
        LEFT JOIN (
            SELECT DATE(expense_date) as date_val, SUM(amount) as expense_amount
            FROM expenses 
            WHERE expense_date BETWEEN ? AND ? AND status IN ('approved', 'paid')
            GROUP BY DATE(expense_date)
        ) expense_data USING (date_val)
        ORDER BY date_val
    ", [$startDate, $endDate, $startDate, $endDate, $startDate, $endDate]);

    // For MySQL compatibility (doesn't have generate_series)
    $stmt = $db->executeQuery("
        SELECT 
            date_val as date,
            COALESCE(income_amount, 0) as income,
            COALESCE(expense_amount, 0) as expenses,
            (COALESCE(income_amount, 0) - COALESCE(expense_amount, 0)) as net_flow
        FROM (
            SELECT DISTINCT DATE(transaction_date) as date_val FROM income WHERE transaction_date BETWEEN ? AND ?
            UNION
            SELECT DISTINCT DATE(expense_date) as date_val FROM expenses WHERE expense_date BETWEEN ? AND ?
        ) all_dates
        LEFT JOIN (
            SELECT DATE(transaction_date) as date_val, SUM(amount) as income_amount
            FROM income 
            WHERE transaction_date BETWEEN ? AND ? AND status = 'verified'
            GROUP BY DATE(transaction_date)