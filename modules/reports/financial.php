$start = new DateTime($year . '-' . ((($quarter - 1) * 3) + 1) . '-01');
            $end = clone $start;
            $end->modify('+3 months -1 day');
            return [$start->format('Y-m-d'), $end->format('Y-m-d')];
            
        case 'last_year':
            $lastYear = $now->format('Y') - 1;
            return [$lastYear . '-01-01', $lastYear . '-12-31'];
            
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
 * Get financial summary data
 */
function getFinancialSummaryData($db, $dateFrom, $dateTo, $filters) {
    // Income summary
    $incomeQuery = "
        SELECT 
            ic.name as category,
            COUNT(i.id) as transaction_count,
            SUM(i.amount) as total_amount,
            AVG(i.amount) as avg_amount
        FROM income i
        JOIN income_categories ic ON i.category_id = ic.id
        WHERE i.transaction_date BETWEEN ? AND ?
        AND i.status = 'verified'
        GROUP BY ic.id, ic.name
        ORDER BY total_amount DESC
    ";
    
    $income = $db->executeQuery($incomeQuery, [$dateFrom, $dateTo])->fetchAll();
    
    // Expense summary
    $expenseQuery = "
        SELECT 
            ec.name as category,
            COUNT(e.id) as transaction_count,
            SUM(e.amount) as total_amount,
            AVG(e.amount) as avg_amount
        FROM expenses e
        JOIN expense_categories ec ON e.category_id = ec.id
        WHERE e.expense_date BETWEEN ? AND ?
        AND e.status = 'paid'
        GROUP BY ec.id, ec.name
        ORDER BY total_amount DESC
    ";
    
    $expenses = $db->executeQuery($expenseQuery, [$dateFrom, $dateTo])->fetchAll();
    
    // Totals
    $totalIncome = array_sum(array_column($income, 'total_amount'));
    $totalExpenses = array_sum(array_column($expenses, 'total_amount'));
    $netIncome = $totalIncome - $totalExpenses;
    
    // Monthly trends
    $trendsQuery = "
        SELECT 
            DATE_FORMAT(date_field, '%Y-%m') as month,
            'income' as type,
            SUM(amount) as total
        FROM (
            SELECT transaction_date as date_field, amount 
            FROM income 
            WHERE transaction_date BETWEEN ? AND ? AND status = 'verified'
        ) income_data
        GROUP BY DATE_FORMAT(date_field, '%Y-%m')
        
        UNION ALL
        
        SELECT 
            DATE_FORMAT(date_field, '%Y-%m') as month,
            'expenses' as type,
            SUM(amount) as total
        FROM (
            SELECT expense_date as date_field, amount 
            FROM expenses 
            WHERE expense_date BETWEEN ? AND ? AND status = 'paid'
        ) expense_data
        GROUP BY DATE_FORMAT(date_field, '%Y-%m')
        
        ORDER BY month ASC
    ";
    
    $trends = $db->executeQuery($trendsQuery, [$dateFrom, $dateTo, $dateFrom, $dateTo])->fetchAll();
    
    return [
        'income' => $income,
        'expenses' => $expenses,
        'totals' => [
            'income' => $totalIncome,
            'expenses' => $totalExpenses,
            'net' => $netIncome
        ],
        'trends' => $trends
    ];
}

/**
 * Get income report data
 */
function getIncomeReportData($db, $dateFrom, $dateTo, $filters) {
    $whereConditions = ["i.transaction_date BETWEEN ? AND ?", "i.status = 'verified'"];
    $params = [$dateFrom, $dateTo];
    
    // Apply filters
    if (!empty($filters['category'])) {
        $whereConditions[] = "i.category_id = ?";
        $params[] = $filters['category'];
    }
    
    if (!empty($filters['payment_method'])) {
        $whereConditions[] = "i.payment_method = ?";
        $params[] = $filters['payment_method'];
    }
    
    if (!empty($filters['min_amount'])) {
        $whereConditions[] = "i.amount >= ?";
        $params[] = $filters['min_amount'];
    }
    
    if (!empty($filters['max_amount'])) {
        $whereConditions[] = "i.amount <= ?";
        $params[] = $filters['max_amount'];
    }
    
    if (!empty($filters['search'])) {
        $whereConditions[] = "(i.donor_name LIKE ? OR i.description LIKE ? OR i.reference_number LIKE ?)";
        $searchTerm = '%' . $filters['search'] . '%';
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $query = "
        SELECT 
            i.*,
            ic.name as category_name,
            u.first_name, u.last_name
        FROM income i
        JOIN income_categories ic ON i.category_id = ic.id
        LEFT JOIN users u ON i.recorded_by = u.id
        WHERE $whereClause
        ORDER BY i.transaction_date DESC, i.created_at DESC
    ";
    
    $transactions = $db->executeQuery($query, $params)->fetchAll();
    
    // Summary statistics
    $totalAmount = array_sum(array_column($transactions, 'amount'));
    $avgAmount = count($transactions) > 0 ? $totalAmount / count($transactions) : 0;
    
    // Payment method breakdown
    $paymentMethods = [];
    foreach ($transactions as $transaction) {
        $method = $transaction['payment_method'];
        if (!isset($paymentMethods[$method])) {
            $paymentMethods[$method] = ['count' => 0, 'amount' => 0];
        }
        $paymentMethods[$method]['count']++;
        $paymentMethods[$method]['amount'] += $transaction['amount'];
    }
    
    return [
        'transactions' => $transactions,
        'summary' => [
            'total_amount' => $totalAmount,
            'transaction_count' => count($transactions),
            'average_amount' => $avgAmount
        ],
        'payment_methods' => $paymentMethods
    ];
}

/**
 * Get expense report data
 */
function getExpenseReportData($db, $dateFrom, $dateTo, $filters) {
    $whereConditions = ["e.expense_date BETWEEN ? AND ?", "e.status = 'paid'"];
    $params = [$dateFrom, $dateTo];
    
    // Apply filters
    if (!empty($filters['category'])) {
        $whereConditions[] = "e.category_id = ?";
        $params[] = $filters['category'];
    }
    
    if (!empty($filters['payment_method'])) {
        $whereConditions[] = "e.payment_method = ?";
        $params[] = $filters['payment_method'];
    }
    
    if (!empty($filters['min_amount'])) {
        $whereConditions[] = "e.amount >= ?";
        $params[] = $filters['min_amount'];
    }
    
    if (!empty($filters['max_amount'])) {
        $whereConditions[] = "e.amount <= ?";
        $params[] = $filters['max_amount'];
    }
    
    if (!empty($filters['search'])) {
        $whereConditions[] = "(e.vendor_name LIKE ? OR e.description LIKE ? OR e.reference_number LIKE ?)";
        $searchTerm = '%' . $filters['search'] . '%';
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $query = "
        SELECT 
            e.*,
            ec.name as category_name,
            u1.first_name as requested_by_first, u1.last_name as requested_by_last,
            u2.first_name as approved_by_first, u2.last_name as approved_by_last
        FROM expenses e
        JOIN expense_categories ec ON e.category_id = ec.id
        LEFT JOIN users u1 ON e.requested_by = u1.id
        LEFT JOIN users u2 ON e.approved_by = u2.id
        WHERE $whereClause
        ORDER BY e.expense_date DESC, e.created_at DESC
    ";
    
    $transactions = $db->executeQuery($query, $params)->fetchAll();
    
    // Summary statistics
    $totalAmount = array_sum(array_column($transactions, 'amount'));
    $avgAmount = count($transactions) > 0 ? $totalAmount / count($transactions) : 0;
    
    // Category breakdown
    $categories = [];
    foreach ($transactions as $transaction) {
        $category = $transaction['category_name'];
        if (!isset($categories[$category])) {
            $categories[$category] = ['count' => 0, 'amount' => 0];
        }
        $categories[$category]['count']++;
        $categories[$category]['amount'] += $transaction['amount'];
    }
    
    return [
        'transactions' => $transactions,
        'summary' => [
            'total_amount' => $totalAmount,
            'transaction_count' => count($transactions),
            'average_amount' => $avgAmount
        ],
        'categories' => $categories
    ];
}

/**
 * Get comparison report data
 */
function getComparisonReportData($db, $dateFrom, $dateTo, $filters) {
    // Monthly comparison
    $monthlyQuery = "
        SELECT 
            DATE_FORMAT(month_date, '%Y-%m') as month,
            DATE_FORMAT(month_date, '%M %Y') as month_name,
            COALESCE(income_total, 0) as income,
            COALESCE(expense_total, 0) as expenses,
            COALESCE(income_total, 0) - COALESCE(expense_total, 0) as net
        FROM (
            SELECT DISTINCT DATE_FORMAT(transaction_date, '%Y-%m-01') as month_date
            FROM income
            WHERE transaction_date BETWEEN ? AND ?
            UNION
            SELECT DISTINCT DATE_FORMAT(expense_date, '%Y-%m-01') as month_date
            FROM expenses
            WHERE expense_date BETWEEN ? AND ?
        ) months
        LEFT JOIN (
            SELECT 
                DATE_FORMAT(transaction_date, '%Y-%m-01') as month_date,
                SUM(amount) as income_total
            FROM income
            WHERE transaction_date BETWEEN ? AND ? AND status = 'verified'
            GROUP BY DATE_FORMAT(transaction_date, '%Y-%m-01')
        ) income_data ON months.month_date = income_data.month_date
        LEFT JOIN (
            SELECT 
                DATE_FORMAT(expense_date, '%Y-%m-01') as month_date,
                SUM(amount) as expense_total
            FROM expenses
            WHERE expense_date BETWEEN ? AND ? AND status = 'paid'
            GROUP BY DATE_FORMAT(expense_date, '%Y-%m-01')
        ) expense_data ON months.month_date = expense_data.month_date
        ORDER BY month_date ASC
    ";
    
    $monthlyData = $db->executeQuery($monthlyQuery, [
        $dateFrom, $dateTo, $dateFrom, $dateTo, $dateFrom, $dateTo, $dateFrom, $dateTo
    ])->fetchAll();
    
    // Category comparison
    $categoryQuery = "
        SELECT 
            'income' as type,
            ic.name as category,
            SUM(i.amount) as total
        FROM income i
        JOIN income_categories ic ON i.category_id = ic.id
        WHERE i.transaction_date BETWEEN ? AND ? AND i.status = 'verified'
        GROUP BY ic.id, ic.name
        
        UNION ALL
        
        SELECT 
            'expense' as type,
            ec.name as category,
            SUM(e.amount) as total
        FROM expenses e
        JOIN expense_categories ec ON e.category_id = ec.id
        WHERE e.expense_date BETWEEN ? AND ? AND e.status = 'paid'
        GROUP BY ec.id, ec.name
        
        ORDER BY type ASC, total DESC
    ";
    
    $categoryData = $db->executeQuery($categoryQuery, [$dateFrom, $dateTo, $dateFrom, $dateTo])->fetchAll();
    
    return [
        'monthly' => $monthlyData,
        'categories' => $categoryData
    ];
}

/**
 * Get donor analysis data
 */
function getDonorReportData($db, $dateFrom, $dateTo, $filters) {
    $query = "
        SELECT 
            COALESCE(i.donor_name, 'Anonymous') as donor_name,
            i.donor_phone,
            i.donor_email,
            COUNT(i.id) as donation_count,
            SUM(i.amount) as total_donated,
            AVG(i.amount) as average_donation,
            MIN(i.transaction_date) as first_donation,
            MAX(i.transaction_date) as last_donation,
            GROUP_CONCAT(DISTINCT ic.name SEPARATOR ', ') as categories
        FROM income i
        JOIN income_categories ic ON i.category_id = ic.id
        WHERE i.transaction_date BETWEEN ? AND ?
        AND i.status = 'verified'
        AND i.is_anonymous = 0
        GROUP BY COALESCE(i.donor_name, 'Anonymous'), i.donor_phone, i.donor_email
        HAVING total_donated > 0
        ORDER BY total_donated DESC
    ";
    
    $donors = $db->executeQuery($query, [$dateFrom, $dateTo])->fetchAll();
    
    // Donor statistics
    $totalDonors = count($donors);
    $totalDonated = array_sum(array_column($donors, 'total_donated'));
    $avgPerDonor = $totalDonors > 0 ? $totalDonated / $totalDonors : 0;
    
    // Donation frequency analysis
    $frequencyAnalysis = [
        'one_time' => 0,
        'occasional' => 0,
        'regular' => 0,
        'frequent' => 0
    ];
    
    foreach ($donors as $donor) {
        $count = $donor['donation_count'];
        if ($count == 1) {
            $frequencyAnalysis['one_time']++;
        } elseif ($count <= 3) {
            $frequencyAnalysis['occasional']++;
        } elseif ($count <= 10) {
            $frequencyAnalysis['regular']++;
        } else {
            $frequencyAnalysis['frequent']++;
        }
    }
    
    return [
        'donors' => $donors,
        'summary' => [
            'total_donors' => $totalDonors,
            'total_donated' => $totalDonated,
            'average_per_donor' => $avgPerDonor
        ],
        'frequency_analysis' => $frequencyAnalysis
    ];
}

/**
 * Get category analysis data
 */
function getCategoryReportData($db, $dateFrom, $dateTo, $filters) {
    // Income categories
    $incomeQuery = "
        SELECT 
            ic.name as category,
            ic.description,
            COUNT(i.id) as transaction_count,
            SUM(i.amount) as total_amount,
            AVG(i.amount) as average_amount,
            MIN(i.amount) as min_amount,
            MAX(i.amount) as max_amount
        FROM income_categories ic
        LEFT JOIN income i ON ic.id = i.category_id 
            AND i.transaction_date BETWEEN ? AND ? 
            AND i.status = 'verified'
        WHERE ic.is_active = 1
        GROUP BY ic.id, ic.name, ic.description
        ORDER BY total_amount DESC
    ";
    
    $incomeCategories = $db->executeQuery($incomeQuery, [$dateFrom, $dateTo])->fetchAll();
    
    // Expense categories
    $expenseQuery = "
        SELECT 
            ec.name as category,
            ec.description,
            ec.budget_limit,
            COUNT(e.id) as transaction_count,
            SUM(e.amount) as total_amount,
            AVG(e.amount) as average_amount,
            MIN(e.amount) as min_amount,
            MAX(e.amount) as max_amount,
            CASE 
                WHEN ec.budget_limit > 0 THEN (SUM(e.amount) / ec.budget_limit) * 100
                ELSE NULL 
            END as budget_usage_percent
        FROM expense_categories ec
        LEFT JOIN expenses e ON ec.id = e.category_id 
            AND e.expense_date BETWEEN ? AND ? 
            AND e.status = 'paid'
        WHERE ec.is_active = 1
        GROUP BY ec.id, ec.name, ec.description, ec.budget_limit
        ORDER BY total_amount DESC
    ";
    
    $expenseCategories = $db->executeQuery($expenseQuery, [$dateFrom, $dateTo])->fetchAll();
    
    return [
        'income_categories' => $incomeCategories,
        'expense_categories' => $expenseCategories
    ];
}

// Handle export
if (!empty($export) && !empty($reportData)) {
    // Export logic would go here
    exit();
}

// Include header if not exporting
if (empty($export)) {
    include_once '../../includes/header.php';
}
?>

<?php if (empty($export)): ?>

<!-- Financial Reports Content -->
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
                                <option value="summary" <?php echo $reportType === 'summary' ? 'selected' : ''; ?>>Financial Summary</option>
                                <option value="income" <?php echo $reportType === 'income' ? 'selected' : ''; ?>>Income Report</option>
                                <option value="expenses" <?php echo $reportType === 'expenses' ? 'selected' : ''; ?>>Expense Report</option>
                                <option value="comparison" <?php echo $reportType === 'comparison' ? 'selected' : ''; ?>>Income vs Expenses</option>
                                <option value="donors" <?php echo $reportType === 'donors' ? 'selected' : ''; ?>>Donor Analysis</option>
                                <option value="categories" <?php echo $reportType === 'categories' ? 'selected' : ''; ?>>Category Analysis</option>
                            </select>
                        </div>

                        <!-- Period Selection -->
                        <div class="col-md-3">
                            <label class="form-label">Period</label>
                            <select name="period" class="form-select" onchange="toggleCustomDates()">
                                <option value="today" <?php echo $period === 'today' ? 'selected' : ''; ?>>Today</option>
                                <option value="this_week" <?php echo $period === 'this_week' ? 'selected' : ''; ?>>This Week</option>
                                <option value="this_month" <?php echo $period === 'this_month' ? 'selected' : ''; ?>>This Month</option>
                                <option value="this_quarter" <?php echo $period === 'this_quarter' ? 'selected' : ''; ?>>This Quarter</option>
                                <option value="this_year" <?php echo $period === 'this_year' ? 'selected' : ''; ?>>This Year</option>
                                <option value="last_month" <?php echo $period === 'last_month' ? 'selected' : ''; ?>>Last Month</option>
                                <option value="last_quarter" <?php echo $period === 'last_quarter' ? 'selected' : ''; ?>>Last Quarter</option>
                                <option value="last_year" <?php echo $period === 'last_year' ? 'selected' : ''; ?>>Last Year</option>
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

                        <!-- Category Filter -->
                        <?php if (in_array($reportType, ['income', 'expenses'])): ?>
                        <div class="col-md-3">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-select">
                                <option value="">All Categories</option>
                                <?php 
                                $categories = $reportType === 'income' ? $incomeCategories : $expenseCategories;
                                foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo $filters['category'] == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <!-- Payment Method Filter -->
                        <?php if (in_array($reportType, ['income', 'expenses'])): ?>
                        <div class="col-md-3">
                            <label class="form-label">Payment Method</label>
                            <select name="payment_method" class="form-select">
                                <option value="">All Methods</option>
                                <?php foreach (PAYMENT_METHODS as $key => $method): ?>
                                    <option value="<?php echo $key; ?>" <?php echo $filters['payment_method'] === $key ? 'selected' : ''; ?>>
                                        <?php echo $method; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <!-- Amount Range -->
                        <?php if (in_array($reportType, ['income', 'expenses'])): ?>
                        <div class="col-md-2">
                            <label class="form-label">Min Amount</label>
                            <input type="number" name="min_amount" class="form-control" placeholder="0.00" step="0.01" value="<?php echo htmlspecialchars($filters['min_amount']); ?>">
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Max Amount</label>
                            <input type="number" name="max_amount" class="form-control" placeholder="999999.99" step="0.01" value="<?php echo htmlspecialchars($filters['max_amount']); ?>">
                        </div>
                        <?php endif; ?>

                        <!-- Search -->
                        <?php if (in_array($reportType, ['income', 'expenses', 'donors'])): ?>
                        <div class="col-md-4">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" placeholder="Search transactions..." value="<?php echo htmlspecialchars($filters['search']); ?>">
                        </div>
                        <?php endif; ?>

                        <!-- Action Buttons -->
                        <div class="col-12">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-church-primary">
                                    <i class="fas fa-search me-2"></i>Generate Report
                                </button>
                                <a href="?" class="btn btn-outline-secondary">
                                    <i class="fas fa-undo me-2"></i>Reset Filters
                                </a>
                                <button type="button" class="btn btn-outline-info" onclick="printReport()">
                                    <i class="fas fa-print me-2"></i>Print Report
                                </button>
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
            <!-- Financial Summary Report -->
            <div class="row">
                <!-- Summary Cards -->
                <div class="col-md-4 mb-4">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-1">Total Income</h6>
                                    <h4 class="mb-0"><?php echo formatCurrency($reportData['totals']['income'] ?? 0); ?></h4>
                                </div>
                                <i class="fas fa-arrow-up fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-4">
                    <div class="card bg-danger text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-1">Total Expenses</h6>
                                    <h4 class="mb-0"><?php echo formatCurrency($reportData['totals']['expenses'] ?? 0); ?></h4>
                                </div>
                                <i class="fas fa-arrow-down fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-4">
                    <div class="card <?php echo ($reportData['totals']['net'] ?? 0) >= 0 ? 'bg-primary' : 'bg-warning'; ?> text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-1">Net Income</h6>
                                    <h4 class="mb-0"><?php echo formatCurrency($reportData['totals']['net'] ?? 0); ?></h4>
                                </div>
                                <i class="fas fa-balance-scale fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Income Categories -->
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-chart-pie me-2"></i>
                                Income by Category
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($reportData['income'])): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Category</th>
                                                <th class="text-end">Count</th>
                                                <th class="text-end">Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($reportData['expenses'] as $expense): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($expense['category']); ?></td>
                                                    <td class="text-end"><?php echo number_format($expense['transaction_count']); ?></td>
                                                    <td class="text-end"><?php echo formatCurrency($expense['total_amount']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-muted py-3">
                                    <i class="fas fa-info-circle mb-2"></i>
                                    <br>No expense data available for selected period
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($reportType === 'income'): ?>
            <!-- Income Report -->
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-coins me-2"></i>
                            Income Report
                            <span class="badge bg-success ms-2"><?php echo formatCurrency($reportData['summary']['total_amount'] ?? 0); ?></span>
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
                                <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['export' => '1', 'format' => 'pdf'])); ?>">
                                    <i class="fas fa-file-pdf me-2"></i>Export as PDF
                                </a></li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="card-body">
                    <!-- Summary Stats -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="text-center">
                                <div class="h4 text-success mb-1"><?php echo formatCurrency($reportData['summary']['total_amount'] ?? 0); ?></div>
                                <div class="text-muted small">Total Amount</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <div class="h4 text-primary mb-1"><?php echo number_format($reportData['summary']['transaction_count'] ?? 0); ?></div>
                                <div class="text-muted small">Transactions</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <div class="h4 text-info mb-1"><?php echo formatCurrency($reportData['summary']['average_amount'] ?? 0); ?></div>
                                <div class="text-muted small">Average Amount</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <div class="h4 text-warning mb-1"><?php echo count($reportData['payment_methods'] ?? []); ?></div>
                                <div class="text-muted small">Payment Methods</div>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($reportData['transactions'])): ?>
                        <div class="table-responsive">
                            <table class="table table-hover data-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Transaction ID</th>
                                        <th>Category</th>
                                        <th>Donor</th>
                                        <th>Amount</th>
                                        <th>Payment Method</th>
                                        <th>Reference</th>
                                        <th>Recorded By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportData['transactions'] as $transaction): ?>
                                        <tr>
                                            <td><?php echo formatDisplayDate($transaction['transaction_date']); ?></td>
                                            <td><code><?php echo htmlspecialchars($transaction['transaction_id']); ?></code></td>
                                            <td><span class="badge bg-primary"><?php echo htmlspecialchars($transaction['category_name']); ?></span></td>
                                            <td><?php echo htmlspecialchars($transaction['donor_name'] ?: 'Anonymous'); ?></td>
                                            <td><strong class="text-success"><?php echo formatCurrency($transaction['amount']); ?></strong></td>
                                            <td><?php echo ucwords(str_replace('_', ' ', $transaction['payment_method'])); ?></td>
                                            <td><?php echo htmlspecialchars($transaction['reference_number'] ?: '-'); ?></td>
                                            <td><?php echo htmlspecialchars(($transaction['first_name'] ?? '') . ' ' . ($transaction['last_name'] ?? '')); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-search fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No Income Records Found</h5>
                            <p class="text-muted">No income records match your current filter criteria.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($reportType === 'expenses'): ?>
            <!-- Expense Report -->
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-receipt me-2"></i>
                            Expense Report
                            <span class="badge bg-danger ms-2"><?php echo formatCurrency($reportData['summary']['total_amount'] ?? 0); ?></span>
                        </h5>
                        
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-success dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="fas fa-download me-2"></i>Export
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['export' => '1', 'format' => 'csv'])); ?>">
                                    <i class="fas fa-file-csv me-2"></i>Export as CSV
                                </a></li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="card-body">
                    <?php if (!empty($reportData['transactions'])): ?>
                        <div class="table-responsive">
                            <table class="table table-hover data-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Transaction ID</th>
                                        <th>Category</th>
                                        <th>Vendor</th>
                                        <th>Description</th>
                                        <th>Amount</th>
                                        <th>Payment Method</th>
                                        <th>Requested By</th>
                                        <th>Approved By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportData['transactions'] as $transaction): ?>
                                        <tr>
                                            <td><?php echo formatDisplayDate($transaction['expense_date']); ?></td>
                                            <td><code><?php echo htmlspecialchars($transaction['transaction_id']); ?></code></td>
                                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($transaction['category_name']); ?></span></td>
                                            <td><?php echo htmlspecialchars($transaction['vendor_name'] ?: '-'); ?></td>
                                            <td><?php echo htmlspecialchars(truncateText($transaction['description'], 50)); ?></td>
                                            <td><strong class="text-danger"><?php echo formatCurrency($transaction['amount']); ?></strong></td>
                                            <td><?php echo ucwords(str_replace('_', ' ', $transaction['payment_method'])); ?></td>
                                            <td><?php echo htmlspecialchars(($transaction['requested_by_first'] ?? '') . ' ' . ($transaction['requested_by_last'] ?? '')); ?></td>
                                            <td><?php echo htmlspecialchars(($transaction['approved_by_first'] ?? '') . ' ' . ($transaction['approved_by_last'] ?? '')); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-search fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No Expense Records Found</h5>
                            <p class="text-muted">No expense records match your current filter criteria.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($reportType === 'comparison'): ?>
            <!-- Income vs Expenses Comparison -->
            <div class="row">
                <!-- Monthly Comparison Chart -->
                <div class="col-12 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-chart-line me-2"></i>
                                Monthly Income vs Expenses
                            </h6>
                        </div>
                        <div class="card-body">
                            <canvas id="comparisonChart" height="400"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Monthly Data Table -->
                <div class="col-md-8 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-table me-2"></i>
                                Monthly Breakdown
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($reportData['monthly'])): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Month</th>
                                                <th class="text-end">Income</th>
                                                <th class="text-end">Expenses</th>
                                                <th class="text-end">Net</th>
                                                <th class="text-end">Difference</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($reportData['monthly'] as $month): ?>
                                                <tr>
                                                    <td><strong><?php echo $month['month_name']; ?></strong></td>
                                                    <td class="text-end text-success"><?php echo formatCurrency($month['income']); ?></td>
                                                    <td class="text-end text-danger"><?php echo formatCurrency($month['expenses']); ?></td>
                                                    <td class="text-end <?php echo $month['net'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                        <?php echo formatCurrency($month['net']); ?>
                                                    </td>
                                                    <td class="text-end">
                                                        <?php if ($month['income'] > 0): ?>
                                                            <?php $percentage = (($month['net'] / $month['income']) * 100); ?>
                                                            <span class="badge <?php echo $percentage >= 0 ? 'bg-success' : 'bg-danger'; ?>">
                                                                <?php echo number_format($percentage, 1); ?>%
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-muted py-3">
                                    <i class="fas fa-info-circle mb-2"></i>
                                    <br>No comparison data available
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Summary Stats -->
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-calculator me-2"></i>
                                Period Summary
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php
                            $totalIncome = array_sum(array_column($reportData['monthly'] ?? [], 'income'));
                            $totalExpenses = array_sum(array_column($reportData['monthly'] ?? [], 'expenses'));
                            $totalNet = $totalIncome - $totalExpenses;
                            $avgMonthlyIncome = count($reportData['monthly'] ?? []) > 0 ? $totalIncome / count($reportData['monthly']) : 0;
                            $avgMonthlyExpenses = count($reportData['monthly'] ?? []) > 0 ? $totalExpenses / count($reportData['monthly']) : 0;
                            ?>
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <span>Total Income:</span>
                                    <strong class="text-success"><?php echo formatCurrency($totalIncome); ?></strong>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <span>Total Expenses:</span>
                                    <strong class="text-danger"><?php echo formatCurrency($totalExpenses); ?></strong>
                                </div>
                            </div>
                            
                            <div class="mb-3 pb-3 border-bottom">
                                <div class="d-flex justify-content-between">
                                    <span><strong>Net Income:</strong></span>
                                    <strong class="<?php echo $totalNet >= 0 ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo formatCurrency($totalNet); ?>
                                    </strong>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <span>Avg Monthly Income:</span>
                                    <span class="text-success"><?php echo formatCurrency($avgMonthlyIncome); ?></span>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <span>Avg Monthly Expenses:</span>
                                    <span class="text-danger"><?php echo formatCurrency($avgMonthlyExpenses); ?></span>
                                </div>
                            </div>
                            
                            <?php if ($totalIncome > 0): ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <span>Expense Ratio:</span>
                                    <span><?php echo number_format(($totalExpenses / $totalIncome) * 100, 1); ?>%</span>
                                </div>
                                <div class="progress mt-2" style="height: 6px;">
                                    <div class="progress-bar bg-warning" style="width: <?php echo min(($totalExpenses / $totalIncome) * 100, 100); ?>%"></div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($reportType === 'donors'): ?>
            <!-- Donor Analysis Report -->
            <div class="row">
                <!-- Donor Summary -->
                <div class="col-12 mb-4">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h4 class="text-primary"><?php echo number_format($reportData['summary']['total_donors'] ?? 0); ?></h4>
                                    <p class="text-muted mb-0">Total Donors</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h4 class="text-success"><?php echo formatCurrency($reportData['summary']['total_donated'] ?? 0); ?></h4>
                                    <p class="text-muted mb-0">Total Donated</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h4 class="text-info"><?php echo formatCurrency($reportData['summary']['average_per_donor'] ?? 0); ?></h4>
                                    <p class="text-muted mb-0">Average per Donor</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h4 class="text-warning"><?php echo $reportData['frequency_analysis']['frequent'] ?? 0; ?></h4>
                                    <p class="text-muted mb-0">Regular Donors</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Donor List -->
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-users me-2"></i>
                                Donor Details
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($reportData['donors'])): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover data-table">
                                        <thead>
                                            <tr>
                                                <th>Donor Name</th>
                                                <th>Contact</th>
                                                <th>Donations</th>
                                                <th>Total Donated</th>
                                                <th>Average</th>
                                                <th>First Donation</th>
                                                <th>Last Donation</th>
                                                <th>Categories</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($reportData['donors'] as $donor): ?>
                                                <tr>
                                                    <td><strong><?php echo htmlspecialchars($donor['donor_name']); ?></strong></td>
                                                    <td>
                                                        <?php if (!empty($donor['donor_phone'])): ?>
                                                            <div><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($donor['donor_phone']); ?></div>
                                                        <?php endif; ?>
                                                        <?php if (!empty($donor['donor_email'])): ?>
                                                            <div><i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($donor['donor_email']); ?></div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-primary"><?php echo number_format($donor['donation_count']); ?></span>
                                                        <?php
                                                        $frequency = 'one-time';
                                                        if ($donor['donation_count'] > 10) $frequency = 'frequent';
                                                        elseif ($donor['donation_count'] > 3) $frequency = 'regular';
                                                        elseif ($donor['donation_count'] > 1) $frequency = 'occasional';
                                                        ?>
                                                        <br><small class="text-muted"><?php echo ucfirst($frequency); ?></small>
                                                    </td>
                                                    <td><strong class="text-success"><?php echo formatCurrency($donor['total_donated']); ?></strong></td>
                                                    <td><?php echo formatCurrency($donor['average_donation']); ?></td>
                                                    <td><?php echo formatDisplayDate($donor['first_donation']); ?></td>
                                                    <td><?php echo formatDisplayDate($donor['last_donation']); ?></td>
                                                    <td>
                                                        <?php
                                                        $categories = explode(', ', $donor['categories']);
                                                        foreach ($categories as $category):
                                                        ?>
                                                            <span class="badge bg-light text-dark me-1"><?php echo htmlspecialchars($category); ?></span>
                                                        <?php endforeach; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No Donor Data Found</h5>
                                    <p class="text-muted">No donor records match your current filter criteria.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        <?php endif; ?>
    </div>
</div>

<!-- JavaScript for Charts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle custom date fields
    toggleCustomDates();
    
    // Initialize charts based on report type
    <?php if ($reportType === 'comparison' && !empty($reportData['monthly'])): ?>
        initializeComparisonChart();
    <?php endif; ?>
});

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

<?php if ($reportType === 'comparison' && !empty($reportData['monthly'])): ?>
function initializeComparisonChart() {
    const ctx = document.getElementById('comparisonChart').getContext('2d');
    
    const months = <?php echo json_encode(array_column($reportData['monthly'], 'month_name')); ?>;
    const incomeData = <?php echo json_encode(array_column($reportData['monthly'], 'income')); ?>;
    const expenseData = <?php echo json_encode(array_column($reportData['monthly'], 'expenses')); ?>;
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: months,
            datasets: [
                {
                    label: 'Income',
                    data: incomeData,
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    borderWidth: 3,
                    fill: false,
                    tension: 0.4
                },
                {
                    label: 'Expenses',
                    data: expenseData,
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    borderWidth: 3,
                    fill: false,
                    tension: 0.4
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

function printReport() {
    window.print();
}
</script>

<?php endif; // End if not export ?>

<?php 
// Include footer if not exporting
if (empty($export)) {
    include_once '../../includes/footer.php';
}
?>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($reportData['income'] as $income): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($income['category']); ?></td>
                                                    <td class="text-end"><?php echo number_format($income['transaction_count']); ?></td>
                                                    <td class="text-end"><?php echo formatCurrency($income['total_amount']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-muted py-3">
                                    <i class="fas fa-info-circle mb-2"></i>
                                    <br>No income data available for selected period
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-chart-pie me-2"></i>
                                Expenses by Category
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($reportData['expenses'])): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Category</th>
                                                <th class="text-end">Count</th>
                                                <th class="text-end">Amount</th>
                                            </tr><?php
/**
 * Financial Reports
 * Deliverance Church Management System
 * 
 * Generate various financial reports including income, expenses, and analysis
 */

// Include configuration and functions
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Check authentication and permissions
requireLogin();
if (!hasPermission('reports') && !hasPermission('finance')) {
    header('Location: ' . BASE_URL . 'modules/dashboard/?error=access_denied');
    exit();
}

// Get report parameters
$reportType = sanitizeInput($_GET['type'] ?? 'summary');
$export = sanitizeInput($_GET['export'] ?? '');
$format = sanitizeInput($_GET['format'] ?? 'html');
$period = sanitizeInput($_GET['period'] ?? 'this_month');

// Page configuration
$page_title = 'Financial Reports';
$page_icon = 'fas fa-money-bill-wave';
$breadcrumb = [
    ['title' => 'Reports', 'url' => 'index.php'],
    ['title' => 'Financial Reports']
];

// Initialize database
$db = Database::getInstance();

// Process date filters
$filters = [
    'period' => $period,
    'date_from' => sanitizeInput($_GET['date_from'] ?? ''),
    'date_to' => sanitizeInput($_GET['date_to'] ?? ''),
    'category' => sanitizeInput($_GET['category'] ?? ''),
    'payment_method' => sanitizeInput($_GET['payment_method'] ?? ''),
    'min_amount' => sanitizeInput($_GET['min_amount'] ?? ''),
    'max_amount' => sanitizeInput($_GET['max_amount'] ?? ''),
    'search' => sanitizeInput($_GET['search'] ?? '')
];

// Calculate date range based on period
list($dateFrom, $dateTo) = getDateRange($period, $filters['date_from'], $filters['date_to']);

try {
    // Get report data based on type
    switch ($reportType) {
        case 'summary':
            $reportTitle = 'Financial Summary Report';
            $reportData = getFinancialSummaryData($db, $dateFrom, $dateTo, $filters);
            break;
            
        case 'income':
            $reportTitle = 'Income Report';
            $reportData = getIncomeReportData($db, $dateFrom, $dateTo, $filters);
            break;
            
        case 'expenses':
            $reportTitle = 'Expense Report';
            $reportData = getExpenseReportData($db, $dateFrom, $dateTo, $filters);
            break;
            
        case 'comparison':
            $reportTitle = 'Income vs Expenses Comparison';
            $reportData = getComparisonReportData($db, $dateFrom, $dateTo, $filters);
            break;
            
        case 'donors':
            $reportTitle = 'Donor Analysis Report';
            $reportData = getDonorReportData($db, $dateFrom, $dateTo, $filters);
            break;
            
        case 'categories':
            $reportTitle = 'Category Analysis Report';
            $reportData = getCategoryReportData($db, $dateFrom, $dateTo, $filters);
            break;
            
        default:
            $reportTitle = 'Financial Summary Report';
            $reportData = getFinancialSummaryData($db, $dateFrom, $dateTo, $filters);
            break;
    }
    
    // Get additional data for filters
    $incomeCategories = $db->executeQuery("SELECT id, name FROM income_categories WHERE is_active = 1 ORDER BY name")->fetchAll();
    $expenseCategories = $db->executeQuery("SELECT id, name FROM expense_categories WHERE is_active = 1 ORDER BY name")->fetchAll();
    
} catch (Exception $e) {
    error_log("Error generating financial report: " . $e->getMessage());
    setFlashMessage('error', 'Error generating report: ' . $e->getMessage());
    $reportData = [];
    $incomeCategories = [];
    $expenseCategories = [];
}

/**
 * Get date range based on period
 */
function getDateRange($period, $customFrom = '', $customTo = '') {
    $now = new DateTime();
    
    switch ($period) {
        case 'today':
            return [$now->format('Y-m-d'), $now->format('Y-m-d')];
            
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
            
        case 'last_quarter':
            $quarter = ceil($now->format('n') / 3) - 1;
            if ($quarter <= 0) {
                $quarter = 4;
                $year = $now->format('Y') - 1;
            } else {
                $year = $now->format('Y');
            }
            $start = new DateTime($year . '-' . ((($quarter - 1) * 3) + 1) . '-01');
            $end = clone $start;
            $end->modify('+3 months -1 day');
            return [$start->format('Y-m-d'), $end->format('Y-m-d')];
        }   
        case 'last_year':
            $lastYear = $now->format('Y') - 1;
            return [$lastYear . '-01-01', $lastYear . '-12-31'];
        }   
        case 'custom':
            $from = DateTime::createFromFormat('Y-m-d', $customFrom);
            $to = DateTime::createFromFormat('Y-m-d', $customTo);
            if ($from && $to) {
                return [$from->format('Y-m-d'), $to->format('Y-m-d')];
            }
            // Fall through to default if custom dates are invalid
        default:
            return [$now->format('Y-m-01'), $now->format('Y-m-d')];
    }   
}   
/**
 * Fetch financial summary data
 */
function getFinancialSummaryData($db, $dateFrom, $dateTo, $filters) {
    // Total Income
    $incomeQuery = "SELECT SUM(amount) AS total_income FROM income WHERE income_date BETWEEN ? AND ?";
    $incomeStmt = $db->prepare($incomeQuery);
    $incomeStmt->execute([$dateFrom, $dateTo]);
    $totalIncome = $incomeStmt->fetchColumn() ?: 0;

    // Total Expenses
    $expenseQuery = "SELECT SUM(amount) AS total_expenses FROM expenses WHERE expense_date BETWEEN ? AND ?";
    $expenseStmt = $db->prepare($expenseQuery);
    $expenseStmt->execute([$dateFrom, $dateTo]);
    $totalExpenses = $expenseStmt->fetchColumn() ?: 0;

    // Net Income
    $netIncome = $totalIncome - $totalExpenses;

    return [
        'total_income' => $totalIncome,
        'total_expenses' => $totalExpenses,
        'net_income' => $netIncome
    ];
}
/**
 * Fetch income report data
 */
function getIncomeReportData($db, $dateFrom, $dateTo, $filters) {
    $query = "SELECT * FROM income WHERE income_date BETWEEN ? AND ?";
    $params = [$dateFrom, $dateTo];

    // Apply filters
    if (!empty($filters['category'])) {
        $query .= " AND category_id = ?";
        $params[] = $filters['category'];
    }

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll();
}
/**
 * Fetch expense report data
 */     
function getExpenseReportData($db, $dateFrom, $dateTo, $filters) {
    $query = "SELECT e.*, c.name AS category_name, v.name AS vendor_name,
                     rb.first_name AS requested_by_first, rb.last_name AS requested_by_last,
                     ab.first_name AS approved_by_first, ab.last_name AS approved_by_last
              FROM expenses e
              LEFT JOIN expense_categories c ON e.category_id = c.id
              LEFT JOIN vendors v ON e.vendor_id = v.id
              LEFT JOIN users rb ON e.requested_by = rb.id
              LEFT JOIN users ab ON e.approved_by = ab.id
              WHERE e.expense_date BETWEEN ? AND ?";
    $params = [$dateFrom, $dateTo];

    // Apply filters
    if (!empty($filters['category'])) {
        $query .= " AND e.category_id = ?";
        $params[] = $filters['category'];
    }
    if (!empty($filters['payment_method'])) {
        $query .= " AND e.payment_method = ?";
        $params[] = $filters['payment_method'];
    }
    if (!empty($filters['min_amount'])) {
        $query .= " AND e.amount >= ?";
        $params[] = $filters['min_amount'];
    }
    if (!empty($filters['max_amount'])) {
        $query .= " AND e.amount <= ?";
        $params[] = $filters['max_amount'];
    }
    if (!empty($filters['search'])) {
        $query .= " AND (e.description LIKE ? OR v.name LIKE ?)";
        $searchTerm = '%' . $filters['search'] . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    $query .= " ORDER BY e.expense_date DESC";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll();
}
                            </tr>       

                                        </thead>
                                        <tbody>
                                            <?php foreach ($reportData['expenses'] as $expense): ?>
                                                <tr>
                                                    <td><?php echo formatDisplayDate($expense['expense_date']); ?></td>
                                                    <td><strong><?php echo htmlspecialchars($expense['category_name']); ?></strong></td>
                                                    <td><?php echo htmlspecialchars($expense['vendor_name'] ?: 'N/A'); ?></td>
                                                    <td><?php echo htmlspecialchars($expense['description']); ?></td>
                                                    <td class="text-end"><?php echo formatCurrency($expense['amount']); ?></td>
                                                    <td><?php echo htmlspecialchars(ucfirst($expense['payment_method'])); ?></td>
                                                    <td>
                                                        <?php if ($expense['requested_by']): ?>
                                                            <?php echo htmlspecialchars($expense['requested_by_first'] . ' ' . $expense['requested_by_last']); ?>
                                                        <?php else: ?>
                                                            N/A
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($expense['approved_by']): ?>
                                                            <?php echo htmlspecialchars($expense['approved_by_first'] . ' ' . $expense['approved_by_last']); ?>
                                                        <?php else: ?>
                                                            N/A
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>  
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No Expense Data Found</h5>
                                    <p class="text-muted">No expense records match your current filter criteria.</p>
                                </div>  
                            <?php endif; ?>
                        </div>  
                    </div>
                </div>
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-chart-line me-2"></i>
                                Income vs Expenses Comparison
                            </h6>
                        </div>
                        <div class="card-body d-flex flex-column">
                            <?php if (!empty($reportData['monthly'])): ?>
                                <div class="mb-4" style="height: 250px;">
                                    <canvas id="comparisonChart"></canvas>
                                </div>
                                <div class="table-responsive mt-auto">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Month</th>
                                                <th class="text-end">Income</th>
                                                <th class="text-end">Expenses</th>
                                                <th class="text-end">Net</th>
                                                <th class="text-end">Net %</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($reportData['monthly'] as $month): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($month['month_name']); ?></td>
                                                    <td class="text-end"><?php echo formatCurrency($month['income']); ?></td>
                                                    <td class="text-end"><?php echo formatCurrency($month['expenses']); ?></td>
                                                    <td class="text-end">
                                                        <span class="<?php echo ($month['income'] - $month['expenses']) >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                            <?php echo formatCurrency($month['income'] - $month['expenses']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-end">
                                                        <span class="<?php echo ($month['income'] - $month['expenses']) >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                            <?php echo formatCurrency((($month['income'] - $month['expenses']) / $month['income']) * 100); ?>%
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No Income/Expense Data Found</h5>
                                    <p class="text-muted">No income or expense records match your current filter criteria.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>  

                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-calculator me-2"></i>
                                Financial Analysis
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-12">
                                    <canvas id="financialChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-chart-pie me-2"></i>
                                Key Financial Metrics
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php
                            $totalIncome = $reportData['summary']['total_income'] ?? 0;
                            $totalExpenses = $reportData['summary']['total_expenses'] ?? 0;
                            $netIncome = $reportData['summary']['net_income'] ?? 0;
                            $avgMonthlyIncome = $reportData['averages']['avg_monthly_income'] ?? 0;
                            $avgMonthlyExpenses = $reportData['averages']['avg_monthly_expenses'] ?? 0;
                            ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <span>Total Income:</span>
                                    <span><?php echo formatCurrency($totalIncome); ?></span>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <span>Total Expenses:</span>
                                    <span><?php echo formatCurrency($totalExpenses); ?></span>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <span>Net Income:</span>
                                    <span class="<?php echo $netIncome >= 0 ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo formatCurrency($netIncome); ?>   
                                    </span>
                                </div>      
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <span>Avg. Monthly Income:</span>
                                    <span><?php echo formatCurrency($avgMonthlyIncome); ?></span>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <span>Avg. Monthly Expenses:</span>
                                    <span><?php echo formatCurrency($avgMonthlyExpenses); ?></span>
                                </div>
                            </div>  
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <span>Expense to Income Ratio:</span>
                                    <span>
                                        <?php
                                        if ($totalIncome > 0) {
                                            $ratio = ($totalExpenses / $totalIncome) * 100;
                                            echo number_format($ratio, 2) . '%';
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>
                        </div>  
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
                <div class="col-12 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-users me-2"></i>
                                Donor Analysis
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($reportData['donors'])): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped table-bordered table-hover table-centered table-nowrap table-sm table-bordered table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Donor</th>
                                                <th>Contact</th>
                                                <th>Frequency</th>
                                                <th>Total Donated</th>
                                                <th>Avg. Donation</th>
                                                <th>First Donation</th>
                                                <th>Last Donation</th>
                                                <th>Categories</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($reportData['donors'] as $donor): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($donor['name']); ?></td>
                                                    <td><?php echo htmlspecialchars($donor['contact']); ?></td>
                                                    <td><?php echo htmlspecialchars($donor['frequency']); ?></td>
                                                    <td><?php echo formatCurrency($donor['total_donated']); ?></td>
                                                    <td><?php echo formatCurrency($donor['avg_donation']); ?></td>
                                                    <td><?php echo formatDate($donor['first_donation']); ?></td>
                                                    <td><?php echo formatDate($donor['last_donation']); ?></td>
                                                    <td><?php echo htmlspecialchars(implode(', ', $donor['categories'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info mb-0">
                                    No donor data available for this report.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
                <div class="col-12 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-users me-2"></i>
                                Donor Analysis
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($reportData['donors'])): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped table-bordered table-hover table-centered table-nowrap">
                                        <thead>
                                            <tr>
                                                <th>Donor</th>
                                                <th>Contact</th>
                                                <th>Frequency</th>
                                                <th>Total Donated</th>
                                                <th>Avg. Donation</th>
                                                <th>First Donation</th>
                                                <th>Last Donation</th>
                                                <th>Categories</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($reportData['donors'] as $donor): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($donor['name']); ?></td>
                                                    <td><?php echo htmlspecialchars($donor['contact']); ?></td>
                                                    <td><?php echo htmlspecialchars($donor['frequency']); ?></td>
                                                    <td><?php echo formatCurrency($donor['total_donated']); ?></td>
                                                    <td><?php echo formatCurrency($donor['avg_donation']); ?></td>
                                                    <td><?php echo formatDisplayDate($donor['first_donation']); ?></td>
                                                    <td><?php echo formatDisplayDate($donor['last_donation']); ?></td>
                                                    <td><?php echo htmlspecialchars(implode(', ', $donor['categories'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info mb-0">
                                    No donor data available for this report.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php if (empty($export)): // Only include scripts if not exporting ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<?php endif; ?> 
