<?php
/**
 * Finance Dashboard
 * Deliverance Church Management System
 * 
 * Financial overview and management
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Check authentication and permissions
requireLogin();
if (!hasPermission('finance')) {
    setFlashMessage('error', 'You do not have permission to access this page.');
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit();
}

// Set page variables
$page_title = 'Finance Dashboard';
$page_icon = 'fas fa-money-bill-wave';
$page_description = 'Financial overview and management';

$breadcrumb = [
    ['title' => 'Finance']
];

$page_actions = [
    [
        'title' => 'Add Income',
        'url' => BASE_URL . 'modules/finance/add_income.php',
        'icon' => 'fas fa-plus-circle',
        'class' => 'success'
    ],
    [
        'title' => 'Add Expense',
        'url' => BASE_URL . 'modules/finance/add_expense.php',
        'icon' => 'fas fa-minus-circle',
        'class' => 'danger'
    ],
    [
        'title' => 'Financial Report',
        'url' => BASE_URL . 'modules/finance/reports.php',
        'icon' => 'fas fa-chart-line',
        'class' => 'info'
    ]
];

$additional_js = ['assets/js/finance.js'];

// Get financial data
try {
    $db = Database::getInstance();
    
    // Current month financial summary
    $current_month = date('Y-m');
    
    // Monthly income
    $monthly_income = $db->executeQuery(
        "SELECT COALESCE(SUM(amount), 0) as total FROM income 
         WHERE status = 'verified' AND DATE_FORMAT(transaction_date, '%Y-%m') = ?",
        [$current_month]
    )->fetchColumn();
    
    // Monthly expenses
    $monthly_expenses = $db->executeQuery(
        "SELECT COALESCE(SUM(amount), 0) as total FROM expenses 
         WHERE status = 'paid' AND DATE_FORMAT(expense_date, '%Y-%m') = ?",
        [$current_month]
    )->fetchColumn();
    
    // Yearly income
    $yearly_income = $db->executeQuery(
        "SELECT COALESCE(SUM(amount), 0) as total FROM income 
         WHERE status = 'verified' AND YEAR(transaction_date) = YEAR(CURDATE())"
    )->fetchColumn();
    
    // Yearly expenses
    $yearly_expenses = $db->executeQuery(
        "SELECT COALESCE(SUM(amount), 0) as total FROM expenses 
         WHERE status = 'paid' AND YEAR(expense_date) = YEAR(CURDATE())"
    )->fetchColumn();
    
    // Pending transactions
    $pending_income = $db->executeQuery(
        "SELECT COUNT(*) as count FROM income WHERE status = 'pending'"
    )->fetchColumn();
    
    $pending_expenses = $db->executeQuery(
        "SELECT COUNT(*) as count FROM expenses WHERE status IN ('pending', 'approved')"
    )->fetchColumn();
    
    // Income by category (current month)
    $income_by_category = $db->executeQuery(
        "SELECT ic.name, COALESCE(SUM(i.amount), 0) as total, COUNT(*) as transactions
         FROM income_categories ic
         LEFT JOIN income i ON ic.id = i.category_id 
         AND i.status = 'verified' 
         AND DATE_FORMAT(i.transaction_date, '%Y-%m') = ?
         WHERE ic.is_active = 1
         GROUP BY ic.id, ic.name
         ORDER BY total DESC",
        [$current_month]
    )->fetchAll();
    
    // Expenses by category (current month)
    $expenses_by_category = $db->executeQuery(
        "SELECT ec.name, COALESCE(SUM(e.amount), 0) as total, COUNT(*) as transactions
         FROM expense_categories ec
         LEFT JOIN expenses e ON ec.id = e.category_id 
         AND e.status = 'paid' 
         AND DATE_FORMAT(e.expense_date, '%Y-%m') = ?
         WHERE ec.is_active = 1
         GROUP BY ec.id, ec.name
         ORDER BY total DESC",
        [$current_month]
    )->fetchAll();
    
    // Recent transactions
    $recent_income = $db->executeQuery(
        "SELECT i.*, ic.name as category_name, u.first_name, u.last_name
         FROM income i 
         JOIN income_categories ic ON i.category_id = ic.id
         LEFT JOIN users u ON i.recorded_by = u.id
         ORDER BY i.created_at DESC 
         LIMIT 10"
    )->fetchAll();
    
    $recent_expenses = $db->executeQuery(
        "SELECT e.*, ec.name as category_name, u.first_name, u.last_name
         FROM expenses e 
         JOIN expense_categories ec ON e.category_id = ec.id
         LEFT JOIN users u ON e.requested_by = u.id
         ORDER BY e.created_at DESC 
         LIMIT 10"
    )->fetchAll();
    
    // Monthly trends (last 6 months)
    $monthly_trends = $db->executeQuery(
        "SELECT 
            DATE_FORMAT(month_date, '%Y-%m') as month,
            DATE_FORMAT(month_date, '%M %Y') as month_name,
            income_total,
            expense_total,
            (income_total - expense_total) as net
         FROM (
            SELECT 
                DATE(CONCAT(YEAR(CURDATE()), '-', MONTH(CURDATE()), '-01')) - INTERVAL n MONTH as month_date,
                COALESCE((SELECT SUM(amount) FROM income WHERE status = 'verified' 
                         AND DATE_FORMAT(transaction_date, '%Y-%m') = DATE_FORMAT(DATE(CONCAT(YEAR(CURDATE()), '-', MONTH(CURDATE()), '-01')) - INTERVAL n MONTH, '%Y-%m')), 0) as income_total,
                COALESCE((SELECT SUM(amount) FROM expenses WHERE status = 'paid' 
                         AND DATE_FORMAT(expense_date, '%Y-%m') = DATE_FORMAT(DATE(CONCAT(YEAR(CURDATE()), '-', MONTH(CURDATE()), '-01')) - INTERVAL n MONTH, '%Y-%m')), 0) as expense_total
            FROM (SELECT 0 as n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5) as months
         ) as monthly_data
         ORDER BY month_date DESC"
    )->fetchAll();
    
} catch (Exception $e) {
    error_log("Finance dashboard error: " . $e->getMessage());
    $monthly_income = $monthly_expenses = $yearly_income = $yearly_expenses = 0;
    $pending_income = $pending_expenses = 0;
    $income_by_category = $expenses_by_category = [];
    $recent_income = $recent_expenses = [];
    $monthly_trends = [];
}

// Calculate financial health indicators
$net_monthly = $monthly_income - $monthly_expenses;
$net_yearly = $yearly_income - $yearly_expenses;
$monthly_health = $monthly_income > 0 ? ($net_monthly / $monthly_income) * 100 : 0;

// Include header
include '../../includes/header.php';
?>

<!-- Finance Dashboard Content -->
<div class="row">
    <!-- Financial Summary Cards -->
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="stats-card">
            <div class="d-flex align-items-center">
                <div class="stats-icon bg-success text-white">
                    <i class="fas fa-arrow-up"></i>
                </div>
                <div class="flex-grow-1 ms-3">
                    <div class="stats-number" data-value="<?php echo $monthly_income; ?>">
                        <?php echo formatCurrency($monthly_income); ?>
                    </div>
                    <div class="stats-label">Monthly Income</div>
                    <small class="text-muted">
                        <i class="fas fa-calendar me-1"></i>
                        <?php echo date('F Y'); ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="stats-card">
            <div class="d-flex align-items-center">
                <div class="stats-icon bg-danger text-white">
                    <i class="fas fa-arrow-down"></i>
                </div>
                <div class="flex-grow-1 ms-3">
                    <div class="stats-number" data-value="<?php echo $monthly_expenses; ?>">
                        <?php echo formatCurrency($monthly_expenses); ?>
                    </div>
                    <div class="stats-label">Monthly Expenses</div>
                    <small class="text-muted">
                        <i class="fas fa-calendar me-1"></i>
                        <?php echo date('F Y'); ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="stats-card">
            <div class="d-flex align-items-center">
                <div class="stats-icon bg-<?php echo $net_monthly >= 0 ? 'success' : 'danger'; ?> text-white">
                    <i class="fas fa-<?php echo $net_monthly >= 0 ? 'plus' : 'minus'; ?>"></i>
                </div>
                <div class="flex-grow-1 ms-3">
                    <div class="stats-number text-<?php echo $net_monthly >= 0 ? 'success' : 'danger'; ?>">
                        <?php echo formatCurrency(abs($net_monthly)); ?>
                    </div>
                    <div class="stats-label">Net Monthly</div>
                    <small class="text-<?php echo $net_monthly >= 0 ? 'success' : 'danger'; ?>">
                        <?php echo $net_monthly >= 0 ? 'Surplus' : 'Deficit'; ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="stats-card">
            <div class="d-flex align-items-center">
                <div class="stats-icon bg-warning text-white">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="flex-grow-1 ms-3">
                    <div class="stats-number"><?php echo $pending_income + $pending_expenses; ?></div>
                    <div class="stats-label">Pending Transactions</div>
                    <small class="text-muted">
                        <?php echo $pending_income; ?> income, <?php echo $pending_expenses; ?> expenses
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Financial Charts -->
<div class="row mb-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="fas fa-chart-line me-2"></i>Financial Trends (Last 6 Months)
                </h6>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-primary active" onclick="toggleChartView('monthly')">Monthly</button>
                    <button type="button" class="btn btn-outline-primary" onclick="toggleChartView('weekly')">Weekly</button>
                </div>
            </div>
            <div class="card-body">
                <canvas id="financialTrendsChart" height="300"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>Income Distribution
                </h6>
            </div>
            <div class="card-body">
                <canvas id="incomeDistributionChart" height="300"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions and Category Breakdown -->
<div class="row mb-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-tachometer-alt me-2"></i>Quick Actions
                </h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6">
                        <a href="<?php echo BASE_URL; ?>modules/finance/add_income.php" class="btn btn-success w-100">
                            <i class="fas fa-plus-circle mb-2 d-block"></i>
                            Record Income
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="<?php echo BASE_URL; ?>modules/finance/add_expense.php" class="btn btn-danger w-100">
                            <i class="fas fa-minus-circle mb-2 d-block"></i>
                            Record Expense
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="<?php echo BASE_URL; ?>modules/finance/categories.php" class="btn btn-info w-100">
                            <i class="fas fa-tags mb-2 d-block"></i>
                            Manage Categories
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="<?php echo BASE_URL; ?>modules/finance/reports.php" class="btn btn-warning w-100">
                            <i class="fas fa-chart-bar mb-2 d-block"></i>
                            Generate Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-heartbeat me-2"></i>Financial Health
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small">Monthly Health Score</span>
                        <span class="small fw-bold"><?php echo number_format($monthly_health, 1); ?>%</span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-<?php echo $monthly_health >= 80 ? 'success' : ($monthly_health >= 60 ? 'warning' : 'danger'); ?>" 
                             style="width: <?php echo min(100, max(0, $monthly_health)); ?>%"></div>
                    </div>
                </div>
                
                <div class="row text-center">
                    <div class="col-4">
                        <div class="border-end">
                            <div class="h5 mb-0 text-success"><?php echo formatCurrency($yearly_income); ?></div>
                            <small class="text-muted">Yearly Income</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="border-end">
                            <div class="h5 mb-0 text-danger"><?php echo formatCurrency($yearly_expenses); ?></div>
                            <small class="text-muted">Yearly Expenses</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="h5 mb-0 text-<?php echo $net_yearly >= 0 ? 'success' : 'danger'; ?>">
                            <?php echo formatCurrency(abs($net_yearly)); ?>
                        </div>
                        <small class="text-muted">Net Yearly</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Income and Expense Categories -->
<div class="row mb-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="fas fa-arrow-up text-success me-2"></i>Income by Category (This Month)
                </h6>
                <a href="<?php echo BASE_URL; ?>modules/finance/income.php" class="btn btn-sm btn-outline-success">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($income_by_category)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($income_by_category as $category): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold"><?php echo htmlspecialchars($category['name']); ?></div>
                                    <small class="text-muted"><?php echo $category['transactions']; ?> transactions</small>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold text-success"><?php echo formatCurrency($category['total']); ?></div>
                                    <small class="text-muted">
                                        <?php 
                                        $percentage = $monthly_income > 0 ? ($category['total'] / $monthly_income) * 100 : 0;
                                        echo number_format($percentage, 1) . '%';
                                        ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-chart-pie fa-2x mb-2 opacity-50"></i>
                        <p class="mb-0">No income recorded this month</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="fas fa-arrow-down text-danger me-2"></i>Expenses by Category (This Month)
                </h6>
                <a href="<?php echo BASE_URL; ?>modules/finance/expenses.php" class="btn btn-sm btn-outline-danger">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($expenses_by_category)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($expenses_by_category as $category): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold"><?php echo htmlspecialchars($category['name']); ?></div>
                                    <small class="text-muted"><?php echo $category['transactions']; ?> transactions</small>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold text-danger"><?php echo formatCurrency($category['total']); ?></div>
                                    <small class="text-muted">
                                        <?php 
                                        $percentage = $monthly_expenses > 0 ? ($category['total'] / $monthly_expenses) * 100 : 0;
                                        echo number_format($percentage, 1) . '%';
                                        ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-chart-pie fa-2x mb-2 opacity-50"></i>
                        <p class="mb-0">No expenses recorded this month</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent Transactions -->
<div class="row">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="fas fa-history me-2"></i>Recent Income
                </h6>
                <a href="<?php echo BASE_URL; ?>modules/finance/income.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($recent_income)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach (array_slice($recent_income, 0, 5) as $income): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($income['category_name']); ?></h6>
                                            <span class="badge status-<?php echo $income['status']; ?>"><?php echo ucfirst($income['status']); ?></span>
                                        </div>
                                        <p class="mb-1 text-success fw-bold"><?php echo formatCurrency($income['amount']); ?></p>
                                        <small class="text-muted">
                                            <?php if (!empty($income['donor_name'])): ?>
                                                From: <?php echo htmlspecialchars($income['donor_name']); ?> | 
                                            <?php endif; ?>
                                            <?php echo formatDisplayDate($income['transaction_date']); ?>
                                            <?php if (!empty($income['first_name'])): ?>
                                                | By: <?php echo htmlspecialchars($income['first_name'] . ' ' . $income['last_name']); ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </div>
                                <?php if (!empty($income['description'])): ?>
                                    <small class="text-muted d-block mt-1">
                                        <?php echo htmlspecialchars(truncateText($income['description'], 80)); ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-money-bill fa-2x mb-2 opacity-50"></i>
                        <p class="mb-0">No recent income transactions</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="fas fa-history me-2"></i>Recent Expenses
                </h6>
                <a href="<?php echo BASE_URL; ?>modules/finance/expenses.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($recent_expenses)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach (array_slice($recent_expenses, 0, 5) as $expense): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($expense['category_name']); ?></h6>
                                            <span class="badge status-<?php echo $expense['status']; ?>"><?php echo ucfirst($expense['status']); ?></span>
                                        </div>
                                        <p class="mb-1 text-danger fw-bold"><?php echo formatCurrency($expense['amount']); ?></p>
                                        <small class="text-muted">
                                            <?php if (!empty($expense['vendor_name'])): ?>
                                                To: <?php echo htmlspecialchars($expense['vendor_name']); ?> | 
                                            <?php endif; ?>
                                            <?php echo formatDisplayDate($expense['expense_date']); ?>
                                            <?php if (!empty($expense['first_name'])): ?>
                                                | By: <?php echo htmlspecialchars($expense['first_name'] . ' ' . $expense['last_name']); ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </div>
                                <?php if (!empty($expense['description'])): ?>
                                    <small class="text-muted d-block mt-1">
                                        <?php echo htmlspecialchars(truncateText($expense['description'], 80)); ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-receipt fa-2x mb-2 opacity-50"></i>
                        <p class="mb-0">No recent expense transactions</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Export Financial Report Modal -->
<div class="modal fade" id="exportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-church-blue text-white">
                <h5 class="modal-title">
                    <i class="fas fa-download me-2"></i>Export Financial Report
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="exportForm">
                    <div class="mb-3">
                        <label class="form-label">Report Type</label>
                        <select class="form-select" name="report_type" required>
                            <option value="summary">Financial Summary</option>
                            <option value="income">Income Report</option>
                            <option value="expenses">Expense Report</option>
                            <option value="categories">Category Breakdown</option>
                            <option value="trends">Monthly Trends</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Date Range</label>
                        <div class="row">
                            <div class="col-6">
                                <input type="date" class="form-control" name="start_date" 
                                       value="<?php echo date('Y-m-01'); ?>" required>
                            </div>
                            <div class="col-6">
                                <input type="date" class="form-control" name="end_date" 
                                       value="<?php echo date('Y-m-t'); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Format</label>
                        <div class="row">
                            <div class="col-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="format" value="pdf" checked>
                                    <label class="form-check-label">PDF</label>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="format" value="excel">
                                    <label class="form-check-label">Excel</label>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="format" value="csv">
                                    <label class="form-check-label">CSV</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-church-primary" onclick="exportFinancialReport()">
                    <i class="fas fa-download me-1"></i>Export Report
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Financial data for charts
const financialData = {
    trends: <?php echo json_encode($monthly_trends); ?>,
    income_categories: <?php echo json_encode($income_by_category); ?>,
    expense_categories: <?php echo json_encode($expenses_by_category); ?>
};

// Initialize financial charts
document.addEventListener('DOMContentLoaded', function() {
    initializeFinancialCharts();
});

function initializeFinancialCharts() {
    // Financial Trends Chart
    const trendsCtx = document.getElementById('financialTrendsChart');
    if (trendsCtx && financialData.trends.length > 0) {
        new Chart(trendsCtx, {
            type: 'line',
            data: {
                labels: financialData.trends.map(item => item.month_name),
                datasets: [{
                    label: 'Income',
                    data: financialData.trends.map(item => item.income_total),
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4,
                    fill: false
                }, {
                    label: 'Expenses',
                    data: financialData.trends.map(item => item.expense_total),
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    tension: 0.4,
                    fill: false
                }, {
                    label: 'Net',
                    data: financialData.trends.map(item => item.net),
                    borderColor: '#03045e',
                    backgroundColor: 'rgba(3, 4, 94, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + ChurchCMS.formatCurrency(context.parsed.y);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Ksh ' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Income Distribution Chart
    const incomeCtx = document.getElementById('incomeDistributionChart');
    if (incomeCtx && financialData.income_categories.length > 0) {
        const validCategories = financialData.income_categories.filter(cat => cat.total > 0);
        
        if (validCategories.length > 0) {
            new Chart(incomeCtx, {
                type: 'doughnut',
                data: {
                    labels: validCategories.map(cat => cat.name),
                    datasets: [{
                        data: validCategories.map(cat => cat.total),
                        backgroundColor: [
                            '#28a745', '#17a2b8', '#ffc107', '#dc3545', 
                            '#6f42c1', '#20c997', '#fd7e14', '#e83e8c'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((context.parsed / total) * 100).toFixed(1);
                                    return context.label + ': ' + ChurchCMS.formatCurrency(context.parsed) + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        }
    }
}

function toggleChartView(period) {
    // Update chart view between monthly and weekly
    const buttons = document.querySelectorAll('.btn-group .btn');
    buttons.forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    // Here you would typically reload chart data for the selected period
    ChurchCMS.showToast(`Switched to ${period} view`, 'info');
}

function exportFinancialReport() {
    const formData = new FormData(document.getElementById('exportForm'));
    const reportData = {
        report_type: formData.get('report_type'),
        start_date: formData.get('start_date'),
        end_date: formData.get('end_date'),
        format: formData.get('format')
    };
    
    ChurchCMS.showLoading('Generating financial report...');
    
    fetch(`${BASE_URL}modules/finance/export.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(reportData)
    })
    .then(response => response.blob())
    .then(blob => {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `financial-report-${reportData.start_date}-to-${reportData.end_date}.${reportData.format}`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        
        ChurchCMS.hideLoading();
        ChurchCMS.showToast('Financial report downloaded successfully!', 'success');
        bootstrap.Modal.getInstance(document.getElementById('exportModal')).hide();
    })
    .catch(error => {
        console.error('Error exporting report:', error);
        ChurchCMS.hideLoading();
        ChurchCMS.showToast('Error generating report', 'error');
    });
}

// Add export button to page actions
document.addEventListener('DOMContentLoaded', function() {
    const pageActions = document.querySelector('.btn-group');
    if (pageActions) {
        const exportBtn = document.createElement('button');
        exportBtn.className = 'btn btn-secondary';
        exportBtn.innerHTML = '<i class="fas fa-download me-1"></i>Export';
        exportBtn.setAttribute('data-bs-toggle', 'modal');
        exportBtn.setAttribute('data-bs-target', '#exportModal');
        pageActions.appendChild(exportBtn);
    }
});
</script>

<?php
// Include footer
include '../../includes/footer.php';
?>