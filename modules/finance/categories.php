<?php
/**
 * Financial Categories Management
 * Deliverance Church Management System
 * 
 * Manage income and expense categories
 */

// Include configuration and functions
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Check authentication and permissions
requireLogin();
if (!hasPermission('finance')) {
    setFlashMessage('error', 'Access denied. You do not have permission to manage financial categories.');
    redirect(BASE_URL . 'modules/dashboard/');
}

// Page configuration
$page_title = 'Financial Categories';
$page_icon = 'fas fa-tags';
$page_description = 'Manage income and expense categories';

$breadcrumb = [
    ['title' => 'Finance', 'url' => 'index.php'],
    ['title' => 'Categories Management']
];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = Database::getInstance();
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'add_income_category':
                $data = [
                    'name' => sanitizeInput($_POST['name']),
                    'description' => sanitizeInput($_POST['description']),
                    'is_active' => 1
                ];
                
                if (insertRecord('income_categories', $data)) {
                    logActivity('Added income category', 'income_categories', null, null, $data);
                    setFlashMessage('success', 'Income category added successfully!');
                } else {
                    setFlashMessage('error', 'Failed to add income category.');
                }
                break;
                
            case 'add_expense_category':
                $data = [
                    'name' => sanitizeInput($_POST['name']),
                    'description' => sanitizeInput($_POST['description']),
                    'budget_limit' => !empty($_POST['budget_limit']) ? (float) $_POST['budget_limit'] : 0,
                    'requires_approval' => isset($_POST['requires_approval']) ? 1 : 0,
                    'is_active' => 1
                ];
                
                if (insertRecord('expense_categories', $data)) {
                    logActivity('Added expense category', 'expense_categories', null, null, $data);
                    setFlashMessage('success', 'Expense category added successfully!');
                } else {
                    setFlashMessage('error', 'Failed to add expense category.');
                }
                break;
                
            case 'update_income_category':
                $id = (int) $_POST['id'];
                $data = [
                    'name' => sanitizeInput($_POST['name']),
                    'description' => sanitizeInput($_POST['description']),
                    'is_active' => isset($_POST['is_active']) ? 1 : 0
                ];
                
                if (updateRecord('income_categories', $data, ['id' => $id])) {
                    logActivity('Updated income category', 'income_categories', $id);
                    setFlashMessage('success', 'Income category updated successfully!');
                } else {
                    setFlashMessage('error', 'Failed to update income category.');
                }
                break;
                
            case 'update_expense_category':
                $id = (int) $_POST['id'];
                $data = [
                    'name' => sanitizeInput($_POST['name']),
                    'description' => sanitizeInput($_POST['description']),
                    'budget_limit' => !empty($_POST['budget_limit']) ? (float) $_POST['budget_limit'] : 0,
                    'requires_approval' => isset($_POST['requires_approval']) ? 1 : 0,
                    'is_active' => isset($_POST['is_active']) ? 1 : 0
                ];
                
                if (updateRecord('expense_categories', $data, ['id' => $id])) {
                    logActivity('Updated expense category', 'expense_categories', $id);
                    setFlashMessage('success', 'Expense category updated successfully!');
                } else {
                    setFlashMessage('error', 'Failed to update expense category.');
                }
                break;
                
            case 'delete_income_category':
                $id = (int) $_POST['id'];
                
                // Check if category is being used
                $usageCount = getRecordCount('income', ['category_id' => $id]);
                if ($usageCount > 0) {
                    setFlashMessage('error', 'Cannot delete category. It is being used by ' . $usageCount . ' income record(s).');
                } else {
                    if (deleteRecord('income_categories', ['id' => $id])) {
                        logActivity('Deleted income category', 'income_categories', $id);
                        setFlashMessage('success', 'Income category deleted successfully!');
                    } else {
                        setFlashMessage('error', 'Failed to delete income category.');
                    }
                }
                break;
                
            case 'delete_expense_category':
                $id = (int) $_POST['id'];
                
                // Check if category is being used
                $usageCount = getRecordCount('expenses', ['category_id' => $id]);
                if ($usageCount > 0) {
                    setFlashMessage('error', 'Cannot delete category. It is being used by ' . $usageCount . ' expense record(s).');
                } else {
                    if (deleteRecord('expense_categories', ['id' => $id])) {
                        logActivity('Deleted expense category', 'expense_categories', $id);
                        setFlashMessage('success', 'Expense category deleted successfully!');
                    } else {
                        setFlashMessage('error', 'Failed to delete expense category.');
                    }
                }
                break;
        }
        
        // Redirect to prevent resubmission
        redirect('categories.php');
        
    } catch (Exception $e) {
        error_log("Categories management error: " . $e->getMessage());
        setFlashMessage('error', 'Error processing request. Please try again.');
    }
}

// Get categories data
try {
    $db = Database::getInstance();
    
    // Get income categories with usage statistics
    $stmt = $db->executeQuery("
        SELECT ic.*, 
               COALESCE(income_count.count, 0) as usage_count,
               COALESCE(income_count.total_amount, 0) as total_income
        FROM income_categories ic
        LEFT JOIN (
            SELECT category_id, COUNT(*) as count, SUM(amount) as total_amount
            FROM income 
            WHERE status = 'verified'
            GROUP BY category_id
        ) income_count ON ic.id = income_count.category_id
        ORDER BY ic.name
    ");
    $incomeCategories = $stmt->fetchAll();
    
    // Get expense categories with usage statistics
    $stmt = $db->executeQuery("
        SELECT ec.*, 
               COALESCE(expense_count.count, 0) as usage_count,
               COALESCE(expense_count.total_amount, 0) as total_expenses,
               COALESCE(monthly_spent.amount, 0) as current_month_spent
        FROM expense_categories ec
        LEFT JOIN (
            SELECT category_id, COUNT(*) as count, SUM(amount) as total_amount
            FROM expenses 
            WHERE status IN ('approved', 'paid')
            GROUP BY category_id
        ) expense_count ON ec.id = expense_count.category_id
        LEFT JOIN (
            SELECT category_id, SUM(amount) as amount
            FROM expenses 
            WHERE status IN ('approved', 'paid')
            AND YEAR(expense_date) = YEAR(NOW())
            AND MONTH(expense_date) = MONTH(NOW())
            GROUP BY category_id
        ) monthly_spent ON ec.id = monthly_spent.category_id
        ORDER BY ec.name
    ");
    $expenseCategories = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Error loading categories: " . $e->getMessage());
    $incomeCategories = [];
    $expenseCategories = [];
}

// Include header
include '../../includes/header.php';
?>

<!-- Categories Management Content -->
<div class="categories-management">
    <div class="row">
        <!-- Income Categories -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-plus-circle text-success me-2"></i>Income Categories
                    </h5>
                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addIncomeCategoryModal">
                        <i class="fas fa-plus me-1"></i>Add Category
                    </button>
                </div>
                <div class="card-body">
                    <?php if (!empty($incomeCategories)): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($incomeCategories as $category): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h6 class="mb-1 <?php echo $category['is_active'] ? 'text-dark' : 'text-muted'; ?>">
                                                <?php echo htmlspecialchars($category['name']); ?>
                                                <?php if (!$category['is_active']): ?>
                                                    <span class="badge bg-secondary ms-2">Inactive</span>
                                                <?php endif; ?>
                                            </h6>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary btn-sm" 
                                                        onclick="editIncomeCategory(<?php echo $category['id']; ?>)" 
                                                        title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if ($category['usage_count'] == 0): ?>
                                                    <button type="button" class="btn btn-outline-danger btn-sm" 
                                                            onclick="deleteIncomeCategory(<?php echo $category['id']; ?>)" 
                                                            title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <?php if (!empty($category['description'])): ?>
                                            <p class="mb-1 text-muted small"><?php echo htmlspecialchars($category['description']); ?></p>
                                        <?php endif; ?>
                                        
                                        <div class="row text-center mt-2">
                                            <div class="col-6">
                                                <small class="text-muted">Usage Count</small>
                                                <div class="fw-bold text-info"><?php echo number_format($category['usage_count']); ?></div>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted">Total Income</small>
                                                <div class="fw-bold text-success"><?php echo formatCurrency($category['total_income']); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-tags fa-3x mb-3"></i>
                            <h6>No Income Categories</h6>
                            <p class="small">Create your first income category to start tracking income.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Expense Categories -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-minus-circle text-danger me-2"></i>Expense Categories
                    </h5>
                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#addExpenseCategoryModal">
                        <i class="fas fa-plus me-1"></i>Add Category
                    </button>
                </div>
                <div class="card-body">
                    <?php if (!empty($expenseCategories)): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($expenseCategories as $category): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h6 class="mb-1 <?php echo $category['is_active'] ? 'text-dark' : 'text-muted'; ?>">
                                                    <?php echo htmlspecialchars($category['name']); ?>
                                                    <?php if (!$category['is_active']): ?>
                                                        <span class="badge bg-secondary ms-2">Inactive</span>
                                                    <?php endif; ?>
                                                    <?php if ($category['requires_approval']): ?>
                                                        <span class="badge bg-warning ms-2">Requires Approval</span>
                                                    <?php endif; ?>
                                                </h6>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-primary btn-sm" 
                                                            onclick="editExpenseCategory(<?php echo $category['id']; ?>)" 
                                                            title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php if ($category['usage_count'] == 0): ?>
                                                        <button type="button" class="btn btn-outline-danger btn-sm" 
                                                                onclick="deleteExpenseCategory(<?php echo $category['id']; ?>)" 
                                                                title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <?php if (!empty($category['description'])): ?>
                                                <p class="mb-2 text-muted small"><?php echo htmlspecialchars($category['description']); ?></p>
                                            <?php endif; ?>
                                            
                                            <!-- Budget Information -->
                                            <?php if ($category['budget_limit'] > 0): ?>
                                                <div class="mb-2">
                                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                                        <small class="text-muted">Monthly Budget</small>
                                                        <small class="fw-bold"><?php echo formatCurrency($category['budget_limit']); ?></small>
                                                    </div>
                                                    
                                                    <?php 
                                                    $spentPercentage = $category['budget_limit'] > 0 ? 
                                                        ($category['current_month_spent'] / $category['budget_limit']) * 100 : 0;
                                                    $remaining = $category['budget_limit'] - $category['current_month_spent'];
                                                    
                                                    $progressColor = 'success';
                                                    if ($spentPercentage > 90) $progressColor = 'danger';
                                                    elseif ($spentPercentage > 70) $progressColor = 'warning';
                                                    ?>
                                                    
                                                    <div class="progress mb-1" style="height: 6px;">
                                                        <div class="progress-bar bg-<?php echo $progressColor; ?>" 
                                                             style="width: <?php echo min(100, $spentPercentage); ?>%"></div>
                                                    </div>
                                                    
                                                    <div class="d-flex justify-content-between">
                                                        <small class="text-muted">Spent: <?php echo formatCurrency($category['current_month_spent']); ?></small>
                                                        <small class="text-<?php echo $remaining >= 0 ? 'success' : 'danger'; ?>">
                                                            Remaining: <?php echo formatCurrency($remaining); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="row text-center">
                                                <div class="col-6">
                                                    <small class="text-muted">Usage Count</small>
                                                    <div class="fw-bold text-info"><?php echo number_format($category['usage_count']); ?></div>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted">Total Expenses</small>
                                                    <div class="fw-bold text-danger"><?php echo formatCurrency($category['total_expenses']); ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-tags fa-3x mb-3"></i>
                            <h6>No Expense Categories</h6>
                            <p class="small">Create your first expense category to start tracking expenses.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <a href="add_category.php" class="btn btn-primary">Add Category</a>
        </div>
                        
        