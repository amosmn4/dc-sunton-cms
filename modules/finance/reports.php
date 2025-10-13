<?php
/**
 * Financial Reports & Analytics
 * Comprehensive financial reporting and analysis
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

requireLogin();
if (!hasPermission('finance')) {
    setFlashMessage('error', 'You do not have permission to access this page');
    redirect(BASE_URL . 'modules/dashboard/');
}

$db = Database::getInstance();

// Get date range
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

// Income Summary
$stmt = $db->executeQuery("
    SELECT 
        ic.name as category,
        SUM(i.amount) as total,
        COUNT(*) as count
    FROM income i
    JOIN income_categories ic ON i.category_id = ic.id
    WHERE i.transaction_date BETWEEN ? AND ?
    AND i.status = 'verified'
    GROUP BY ic.id
    ORDER BY total DESC
", [$startDate, $endDate]);
$incomeSummary = $stmt->fetchAll();

// Expense Summary
$stmt = $db->executeQuery("
    SELECT 
        ec.name as category,
        SUM(e.amount) as total,
        COUNT(*) as count
    FROM expenses e
    JOIN expense_categories ec ON e.category_id = ec.id
    WHERE e.expense_date BETWEEN ? AND ?
    AND e.status = 'paid'
    GROUP BY ec.id
    ORDER BY total DESC
", [$startDate, $endDate]);
$expenseSummary = $stmt->fetchAll();

// Monthly Trend (Last 12 months)
$stmt = $db->executeQuery("
    SELECT 
        DATE_FORMAT(transaction_date, '%Y-%m') as month,
        SUM(amount) as income
    FROM income
    WHERE transaction_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    AND status = 'verified'
    GROUP BY month
    ORDER BY month
");
$monthlyIncome = $stmt->fetchAll();

$stmt = $db->executeQuery("
    SELECT 
        DATE_FORMAT(expense_date, '%Y-%m') as month,
        SUM(amount) as expense
    FROM expenses
    WHERE expense_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    AND status = 'paid'
    GROUP BY month
    ORDER BY month
");
$monthlyExpenses = $stmt->fetchAll();

// Combine income and expenses for comparison
$monthlyData = [];
foreach ($monthlyIncome as $row) {
    $monthlyData[$row['month']]['income'] = $row['income'];
}
foreach ($monthlyExpenses as $row) {
    if (!isset($monthlyData[$row['month']])) {
        $monthlyData[$row['month']] = ['income' => 0];
    }
    $monthlyData[$row['month']]['expense'] = $row['expense'];
}
ksort($monthlyData);

// Calculate totals
$totalIncome = array_sum(array_column($incomeSummary, 'total'));
$totalExpenses = array_sum(array_column($expenseSummary, 'total'));
$netBalance = $totalIncome - $totalExpenses;

// Top donors
$stmt = $db->executeQuery("
    SELECT 
        donor_name,
        SUM(amount) as total_donated,
        COUNT(*) as donation_count
    FROM income
    WHERE transaction_date BETWEEN ? AND ?
    AND status = 'verified'
    AND donor_name IS NOT NULL
    AND donor_name != ''
    GROUP BY donor_name
    ORDER BY total_donated DESC
    LIMIT 10
", [$startDate, $endDate]);
$topDonors = $stmt->fetchAll();

$page_title = 'Financial Reports';
$page_icon = 'fas fa-chart-line';
$breadcrumb = [
    ['title' => 'Finance', 'url' => BASE_URL . 'modules/finance/'],
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

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="stats-icon bg-success bg-opacity-10 text-success">
                            <i class="fas fa-arrow-down"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="stats-number text-success"><?php echo formatCurrency($totalIncome); ?></div>
                        <div class="stats-label">Total Income</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="stats-icon bg-danger bg-opacity-10 text-danger">
                            <i class="fas fa-arrow-up"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="stats-number text-danger"><?php echo formatCurrency($totalExpenses); ?></div>
                        <div class="stats-label">Total Expenses</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="stats-icon bg-primary bg-opacity-10 text-primary">
                            <i class="fas fa-balance-scale"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="stats-number <?php echo $netBalance >= 0 ? 'text-success' : 'text-danger'; ?>">
                            <?php echo formatCurrency(abs($netBalance)); ?>
                        </div>
                        <div class="stats-label">Net Balance <?php echo $netBalance >= 0 ? '(Surplus)' : '(Deficit)'; ?></div>
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
                    <i class="fas fa-chart-line me-2"></i>Income vs Expenses (Last 12 Months)
                </h5>
            </div>
            <div class="card-body">
                <canvas id="trendChart" height="80"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0 text-church-blue">
                    <i class="fas fa-chart-pie me-2"></i>Financial Breakdown
                </h5>
            </div>
            <div class="card-body">
                <canvas id="breakdownChart"></canvas>
                <div class="mt-3">
                    <div class="d-flex justify-content-between mb-2">
                        <span><i class="fas fa-circle text-success"></i> Income</span>
                        <strong><?php echo formatCurrency($totalIncome); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span><i class="fas fa-circle text-danger"></i> Expenses</span>
                        <strong><?php echo formatCurrency($totalExpenses); ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Income and Expense Summary -->
<div class="row mb-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0 text-church-blue">
                    <i class="fas fa-arrow-down text-success me-2"></i>Income by Category
                </h5>
            </div>
            <div class="card-body">
                <canvas id="incomeChart" height="120"></canvas>
                <div class="table-responsive mt-3">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Transactions</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($incomeSummary as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['category']); ?></td>
                                <td><span class="badge bg-info"><?php echo $row['count']; ?></span></td>
                                <td class="text-end"><strong><?php echo formatCurrency($row['total']); ?></strong></td>
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
                    <i class="fas fa-arrow-up text-danger me-2"></i>Expenses by Category
                </h5>
            </div>
            <div class="card-body">
                <canvas id="expenseChart" height="120"></canvas>
                <div class="table-responsive mt-3">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Transactions</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expenseSummary as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['category']); ?></td>
                                <td><span class="badge bg-info"><?php echo $row['count']; ?></span></td>
                                <td class="text-end"><strong><?php echo formatCurrency($row['total']); ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Top Donors -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0 text-church-blue">
                    <i class="fas fa-trophy me-2"></i>Top 10 Donors (Selected Period)
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Donor Name</th>
                                <th>Total Donations</th>
                                <th>Number of Donations</th>
                                <th>Average Donation</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topDonors as $index => $donor): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><strong><?php echo htmlspecialchars($donor['donor_name']); ?></strong></td>
                                <td><strong class="text-success"><?php echo formatCurrency($donor['total_donated']); ?></strong></td>
                                <td><span class="badge bg-info"><?php echo $donor['donation_count']; ?></span></td>
                                <td><?php echo formatCurrency($donor['total_donated'] / $donor['donation_count']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
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
                        <button class="btn btn-outline-success w-100" onclick="exportReport('income_summary')">
                            <i class="fas fa-file-excel me-2"></i>Income Summary
                        </button>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-outline-danger w-100" onclick="exportReport('expense_summary')">
                            <i class="fas fa-file-excel me-2"></i>Expense Summary
                        </button>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-outline-primary w-100" onclick="exportReport('financial_statement')">
                            <i class="fas fa-file-pdf me-2"></i>Financial Statement
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
    danger: '#dc3545',
    info: '#17a2b8'
};

// Monthly Trend Chart
const monthlyData = <?php echo json_encode(array_values($monthlyData)); ?>;
const monthLabels = <?php echo json_encode(array_keys($monthlyData)); ?>;

new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: monthLabels.map(m => {
            const [year, month] = m.split('-');
            return new Date(year, month - 1).toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
        }),
        datasets: [{
            label: 'Income',
            data: monthlyData.map(d => d.income || 0),
            borderColor: chartColors.success,
            backgroundColor: 'rgba(40, 167, 69, 0.1)',
            tension: 0.4,
            fill: true
        }, {
            label: 'Expenses',
            data: monthlyData.map(d => d.expense || 0),
            borderColor: chartColors.danger,
            backgroundColor: 'rgba(220, 53, 69, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        interaction: {
            mode: 'index',
            intersect: false,
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

// Breakdown Pie Chart
new Chart(document.getElementById('breakdownChart'), {
    type: 'doughnut',
    data: {
        labels: ['Income', 'Expenses'],
        datasets: [{
            data: [<?php echo $totalIncome; ?>, <?php echo $totalExpenses; ?>],
            backgroundColor: [chartColors.success, chartColors.danger]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false }
        }
    }
});

// Income Chart
const incomeData = <?php echo json_encode($incomeSummary); ?>;
new Chart(document.getElementById('incomeChart'), {
    type: 'bar',
    data: {
        labels: incomeData.map(d => d.category),
        datasets: [{
            label: 'Income',
            data: incomeData.map(d => d.total),
            backgroundColor: chartColors.success
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: value => 'Ksh ' + value.toLocaleString()
                }
            }
        }
    }
});

// Expense Chart
const expenseData = <?php echo json_encode($expenseSummary); ?>;
new Chart(document.getElementById('expenseChart'), {
    type: 'bar',
    data: {
        labels: expenseData.map(d => d.category),
        datasets: [{
            label: 'Expenses',
            data: expenseData.map(d => d.total),
            backgroundColor: chartColors.danger
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: value => 'Ksh ' + value.toLocaleString()
                }
            }
        }
    }
});

function exportReport(type) {
    ChurchCMS.showLoading('Generating report...');
    window.location.href = `export_financial.php?type=${type}&start=${<?php echo json_encode($startDate); ?>}&end=${<?php echo json_encode($endDate); ?>}`;
    setTimeout(() => ChurchCMS.hideLoading(), 2000);
}
</script>

<?php include '../../includes/footer.php'; ?>