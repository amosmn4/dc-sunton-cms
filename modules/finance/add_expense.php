<?php
/**
 * Add Expense Form
 * Deliverance Church Management System
 * 
 * Form to record new expense transactions
 */

// Include configuration and functions
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Check authentication and permissions
requireLogin();
if (!hasPermission('finance')) {
    setFlashMessage('error', 'Access denied. You do not have permission to add expense records.');
    redirect(BASE_URL . 'modules/dashboard/');
}

// Page configuration
$page_title = 'Add Expense Record';
$page_icon = 'fas fa-minus-circle';
$page_description = 'Record a new expense transaction';

$breadcrumb = [
    ['title' => 'Finance', 'url' => 'index.php'],
    ['title' => 'Expenses', 'url' => 'expenses.php'],
    ['title' => 'Add Expense']
];

// Initialize variables
$errors = [];
$formData = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = Database::getInstance();
        
        // Validation rules
        $validationRules = [
            'category_id' => ['required', 'numeric'],
            'amount' => ['required', 'numeric'],
            'payment_method' => ['required'],
            'expense_date' => ['required', 'date'],
            'description' => ['required', 'min:5']
        ];
        
        // Validate input
        $validation = validateInput($_POST, $validationRules);
        
        if (!$validation['valid']) {
            $errors = $validation['errors'];
        } else {
            $formData = $validation['data'];
            
            // Additional validations
            if ($formData['amount'] <= 0) {
                $errors['amount'] = 'Amount must be greater than zero';
            }
            
            if (strtotime($formData['expense_date']) > time()) {
                $errors['expense_date'] = 'Expense date cannot be in the future';
            }
            
            // Validate vendor email if provided
            if (!empty($formData['vendor_email']) && !isValidEmail($formData['vendor_email'])) {
                $errors['vendor_email'] = 'Invalid email address';
            }
            
            // Validate vendor phone if provided
            if (!empty($formData['vendor_phone']) && !isValidPhoneNumber($formData['vendor_phone'])) {
                $errors['vendor_phone'] = 'Invalid phone number format';
            }
            
            // Check if reference number already exists
            if (!empty($formData['reference_number'])) {
                $existing = getRecord('expenses', 'reference_number', $formData['reference_number']);
                if ($existing) {
                    $errors['reference_number'] = 'Reference number already exists';
                }
            }
            
            // Check category budget limits
            $stmt = $db->executeQuery("SELECT budget_limit, requires_approval FROM expense_categories WHERE id = ?", [$formData['category_id']]);
            $category = $stmt->fetch();
            
            if ($category && $category['budget_limit'] > 0) {
                // Check if expense exceeds category budget for the month
                $stmt = $db->executeQuery("
                    SELECT COALESCE(SUM(amount), 0) as spent_this_month
                    FROM expenses 
                    WHERE category_id = ? 
                    AND YEAR(expense_date) = YEAR(?)
                    AND MONTH(expense_date) = MONTH(?)
                    AND status IN ('approved', 'paid')
                ", [$formData['category_id'], $formData['expense_date'], $formData['expense_date']]);
                
                $spentThisMonth = $stmt->fetchColumn();
                $remainingBudget = $category['budget_limit'] - $spentThisMonth;
                
                if ($formData['amount'] > $remainingBudget) {
                    $errors['amount'] = "Amount exceeds remaining budget for this category. Remaining: " . formatCurrency($remainingBudget);
                }
            }
        }
        
        // If no errors, proceed with saving
        if (empty($errors)) {
            $db->beginTransaction();
            
            try {
                // Generate transaction ID
                $transactionId = generateTransactionId('EXP');
                
                // Handle file upload (receipt)
                $receiptPath = '';
                if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
                    $uploadResult = handleFileUpload(
                        $_FILES['receipt'],
                        ASSETS_PATH . RECEIPTS_PATH,
                        array_merge(ALLOWED_IMAGE_TYPES, ALLOWED_DOCUMENT_TYPES),
                        MAX_DOCUMENT_SIZE
                    );
                    
                    if ($uploadResult['success']) {
                        $receiptPath = RECEIPTS_PATH . $uploadResult['filename'];
                    } else {
                        $errors['receipt'] = $uploadResult['message'];
                    }
                }
                
                if (empty($errors)) {
                    // Determine initial status based on user role and category requirements
                    $initialStatus = 'pending';
                    if ($_SESSION['user_role'] === 'administrator' || $_SESSION['user_role'] === 'pastor') {
                        $initialStatus = 'approved';
                    } elseif ($category && !$category['requires_approval'] && $formData['amount'] <= 5000) {
                        // Auto-approve small amounts for non-approval categories
                        $initialStatus = 'approved';
                    }
                    
                    // Prepare expense data
                    $expenseData = [
                        'transaction_id' => $transactionId,
                        'category_id' => $formData['category_id'],
                        'amount' => $formData['amount'],
                        'currency' => DEFAULT_CURRENCY,
                        'vendor_name' => $formData['vendor_name'] ?? '',
                        'vendor_contact' => $formData['vendor_contact'] ?? '',
                        'payment_method' => $formData['payment_method'],
                        'reference_number' => $formData['reference_number'] ?? '',
                        'description' => $formData['description'],
                        'expense_date' => $formData['expense_date'],
                        'event_id' => !empty($formData['event_id']) ? $formData['event_id'] : null,
                        'receipt_number' => $formData['receipt_number'] ?? '',
                        'receipt_path' => $receiptPath,
                        'requested_by' => $_SESSION['user_id'],
                        'status' => $initialStatus
                    ];
                    
                    // If auto-approved, set approval details
                    if ($initialStatus === 'approved') {
                        $expenseData['approved_by'] = $_SESSION['user_id'];
                        $expenseData['approval_date'] = date('Y-m-d H:i:s');
                    }
                    
                    // Insert expense record
                    $expenseId = insertRecord('expenses', $expenseData);
                    
                    if ($expenseId) {
                        // Log activity
                        logActivity('Added expense record', 'expenses', $expenseId, null, $expenseData);
                        
                        $db->commit();
                        
                        $message = 'Expense record added successfully! Transaction ID: ' . $transactionId;
                        if ($initialStatus === 'approved') {
                            $message .= ' (Auto-approved)';
                        } else {
                            $message .= ' (Pending approval)';
                        }
                        
                        setFlashMessage('success', $message);
                        
                        // Redirect based on user preference
                        if (isset($_POST['save_and_new'])) {
                            redirect('add_expense.php');
                        } else {
                            redirect('expenses.php');
                        }
                    } else {
                        throw new Exception('Failed to save expense record');
                    }
                }
                
            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
        }
        
    } catch (Exception $e) {
        error_log("Add expense error: " . $e->getMessage());
        $errors['general'] = 'Error saving expense record. Please try again.';
    }
}

// Get expense categories
try {
    $db = Database::getInstance();
    $stmt = $db->executeQuery("SELECT * FROM expense_categories WHERE is_active = 1 ORDER BY name");
    $expenseCategories = $stmt->fetchAll();
    
    // Get recent events for event selection
    $stmt = $db->executeQuery("
        SELECT id, name, event_date, event_type 
        FROM events 
        WHERE event_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ORDER BY event_date DESC
        LIMIT 20
    ");
    $recentEvents = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Error loading form data: " . $e->getMessage());
    $expenseCategories = [];
    $recentEvents = [];
}

// Include header
include '../../includes/header.php';
?>

<!-- Add Expense Form -->
<div class="add-expense-form">
    <div class="row justify-content-center">
        <div class="col-xl-8 col-lg-10">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-minus-circle me-2"></i>Add New Expense Record
                    </h5>
                </div>
                
                <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <div class="card-body">
                        <!-- Display general errors -->
                        <?php if (isset($errors['general'])): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?php echo htmlspecialchars($errors['general']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="row">
                            <!-- Left Column -->
                            <div class="col-md-6">
                                <!-- Category -->
                                <div class="mb-3">
                                    <label for="category_id" class="form-label required">Expense Category</label>
                                    <select class="form-select <?php echo isset($errors['category_id']) ? 'is-invalid' : ''; ?>" 
                                            id="category_id" name="category_id" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($expenseCategories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>" 
                                                    data-budget="<?php echo $category['budget_limit']; ?>"
                                                    data-requires-approval="<?php echo $category['requires_approval']; ?>"
                                                    <?php echo (isset($formData['category_id']) && $formData['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($category['name']); ?>
                                                <?php if ($category['budget_limit'] > 0): ?>
                                                    (Budget: <?php echo formatCurrency($category['budget_limit']); ?>)
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div id="categoryInfo" class="form-text"></div>
                                    <?php if (isset($errors['category_id'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['category_id']; ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Amount -->
                                <div class="mb-3">
                                    <label for="amount" class="form-label required">Amount (<?php echo CURRENCY_SYMBOL; ?>)</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><?php echo CURRENCY_SYMBOL; ?></span>
                                        <input type="number" class="form-control <?php echo isset($errors['amount']) ? 'is-invalid' : ''; ?>" 
                                               id="amount" name="amount" step="0.01" min="0.01" 
                                               value="<?php echo htmlspecialchars($formData['amount'] ?? ''); ?>" 
                                               required>
                                        <?php if (isset($errors['amount'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['amount']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div id="budgetWarning" class="form-text text-warning d-none">
                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                        <span id="budgetMessage"></span>
                                    </div>
                                </div>
                                
                                <!-- Payment Method -->
                                <div class="mb-3">
                                    <label for="payment_method" class="form-label required">Payment Method</label>
                                    <select class="form-select <?php echo isset($errors['payment_method']) ? 'is-invalid' : ''; ?>" 
                                            id="payment_method" name="payment_method" required>
                                        <option value="">Select Payment Method</option>
                                        <?php foreach (PAYMENT_METHODS as $key => $label): ?>
                                            <option value="<?php echo $key; ?>" 
                                                    <?php echo (isset($formData['payment_method']) && $formData['payment_method'] === $key) ? 'selected' : ''; ?>>
                                                <?php echo $label; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($errors['payment_method'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['payment_method']; ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Expense Date -->
                                <div class="mb-3">
                                    <label for="expense_date" class="form-label required">Expense Date</label>
                                    <input type="date" class="form-control <?php echo isset($errors['expense_date']) ? 'is-invalid' : ''; ?>" 
                                           id="expense_date" name="expense_date" 
                                           value="<?php echo htmlspecialchars($formData['expense_date'] ?? date('Y-m-d')); ?>" 
                                           max="<?php echo date('Y-m-d'); ?>" required>
                                    <?php if (isset($errors['expense_date'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['expense_date']; ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Reference Number -->
                                <div class="mb-3">
                                    <label for="reference_number" class="form-label">Reference Number</label>
                                    <input type="text" class="form-control <?php echo isset($errors['reference_number']) ? 'is-invalid' : ''; ?>" 
                                           id="reference_number" name="reference_number" 
                                           placeholder="Payment reference, invoice number, etc."
                                           value="<?php echo htmlspecialchars($formData['reference_number'] ?? ''); ?>">
                                    <?php if (isset($errors['reference_number'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['reference_number']; ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Receipt Number -->
                                <div class="mb-3">
                                    <label for="receipt_number" class="form-label">Receipt/Invoice Number</label>
                                    <input type="text" class="form-control" 
                                           id="receipt_number" name="receipt_number" 
                                           placeholder="Official receipt or invoice number"
                                           value="<?php echo htmlspecialchars($formData['receipt_number'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <!-- Right Column -->
                            <div class="col-md-6">
                                <!-- Vendor Information -->
                                <div class="card border-light mb-3">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0 text-church-blue">
                                            <i class="fas fa-building me-2"></i>Vendor/Payee Information
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <!-- Vendor Name -->
                                        <div class="mb-3">
                                            <label for="vendor_name" class="form-label">Vendor/Payee Name</label>
                                            <input type="text" class="form-control" 
                                                   id="vendor_name" name="vendor_name" 
                                                   placeholder="Name of vendor or person paid"
                                                   value="<?php echo htmlspecialchars($formData['vendor_name'] ?? ''); ?>">
                                        </div>
                                        
                                        <!-- Vendor Contact -->
                                        <div class="mb-3">
                                            <label for="vendor_contact" class="form-label">Vendor Contact</label>
                                            <input type="text" class="form-control <?php echo isset($errors['vendor_contact']) ? 'is-invalid' : ''; ?>" 
                                                   id="vendor_contact" name="vendor_contact" 
                                                   placeholder="Phone number or email"
                                                   value="<?php echo htmlspecialchars($formData['vendor_contact'] ?? ''); ?>">
                                            <?php if (isset($errors['vendor_contact'])): ?>
                                                <div class="invalid-feedback"><?php echo $errors['vendor_contact']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Additional Information -->
                                <div class="card border-light mb-3">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0 text-church-blue">
                                            <i class="fas fa-info-circle me-2"></i>Additional Information
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <!-- Related Event -->
                                        <div class="mb-3">
                                            <label for="event_id" class="form-label">Related Event (Optional)</label>
                                            <select class="form-select" id="event_id" name="event_id">
                                                <option value="">No specific event</option>
                                                <?php foreach ($recentEvents as $event): ?>
                                                    <option value="<?php echo $event['id']; ?>" 
                                                            <?php echo (isset($formData['event_id']) && $formData['event_id'] == $event['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($event['name']); ?> 
                                                        (<?php echo formatDisplayDate($event['event_date']); ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <!-- Receipt Upload -->
                                        <div class="mb-3">
                                            <label for="receipt" class="form-label">Upload Receipt/Invoice</label>
                                            <input type="file" class="form-control <?php echo isset($errors['receipt']) ? 'is-invalid' : ''; ?>" 
                                                   id="receipt" name="receipt" 
                                                   accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                            <div class="form-text">
                                                Accepted formats: PDF, JPG, PNG, DOC, DOCX. Max size: <?php echo formatFileSize(MAX_DOCUMENT_SIZE); ?>
                                            </div>
                                            <?php if (isset($errors['receipt'])): ?>
                                                <div class="invalid-feedback"><?php echo $errors['receipt']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Urgency Level -->
                                        <div class="mb-3">
                                            <label for="urgency" class="form-label">Urgency Level</label>
                                            <select class="form-select" id="urgency" name="urgency">
                                                <option value="normal" <?php echo (isset($formData['urgency']) && $formData['urgency'] === 'normal') ? 'selected' : ''; ?>>Normal</option>
                                                <option value="urgent" <?php echo (isset($formData['urgency']) && $formData['urgency'] === 'urgent') ? 'selected' : ''; ?>>Urgent</option>
                                                <option value="emergency" <?php echo (isset($formData['urgency']) && $formData['urgency'] === 'emergency') ? 'selected' : ''; ?>>Emergency</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Description -->
                        <div class="mb-3">
                            <label for="description" class="form-label required">Description</label>
                            <textarea class="form-control <?php echo isset($errors['description']) ? 'is-invalid' : ''; ?>" 
                                      id="description" name="description" rows="3" 
                                      placeholder="Detailed description of the expense (what was purchased, why, etc.)" 
                                      required><?php echo htmlspecialchars($formData['description'] ?? ''); ?></textarea>
                            <div class="form-text">
                                Minimum 5 characters. Be specific about what this expense is for and its business purpose.
                            </div>
                            <?php if (isset($errors['description'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['description']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Justification (for larger amounts) -->
                        <div class="mb-3 d-none" id="justificationField">
                            <label for="justification" class="form-label">Justification</label>
                            <textarea class="form-control" id="justification" name="justification" rows="2" 
                                      placeholder="Please provide justification for this large expense"><?php echo htmlspecialchars($formData['justification'] ?? ''); ?></textarea>
                            <div class="form-text text-warning">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                Large expenses require additional justification for approval.
                            </div>
                        </div>
                        
                        <!-- Additional Notes -->
                        <div class="mb-3">
                            <label for="notes" class="form-label">Additional Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2" 
                                      placeholder="Any additional notes for the finance team"><?php echo htmlspecialchars($formData['notes'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="card-footer bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <a href="expenses.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-1"></i>Back to Expense List
                                </a>
                            </div>
                            <div class="btn-group">
                                <button type="submit" class="btn btn-church-primary">
                                    <i class="fas fa-save me-1"></i>Save Expense Record
                                </button>
                                <button type="submit" name="save_and_new" value="1" class="btn btn-danger">
                                    <i class="fas fa-plus me-1"></i>Save & Add Another
                                </button>
                            </div>
                        </div>
                        
                        <!-- Form Help Text -->
                        <div class="mt-3">
                            <div class="row">
                                <div class="col-md-6">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        <strong>Approval Process:</strong><br>
                                        <?php if ($_SESSION['user_role'] === 'administrator' || $_SESSION['user_role'] === 'pastor'): ?>
                                            Your expense records will be automatically approved.
                                        <?php else: ?>
                                            Expense records require approval before payment can be processed.
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted">
                                        <i class="fas fa-shield-alt me-1"></i>
                                        <strong>Budget Control:</strong><br>
                                        System will check category budget limits and alert if exceeded.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.needs-validation');
    const categorySelect = document.getElementById('category_id');
    const amountInput = document.getElementById('amount');
    const paymentMethodSelect = document.getElementById('payment_method');
    const justificationField = document.getElementById('justificationField');
    const budgetWarning = document.getElementById('budgetWarning');
    const categoryInfo = document.getElementById('categoryInfo');
    
    // Category selection handler
    categorySelect?.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const budget = parseFloat(selectedOption.dataset.budget) || 0;
        const requiresApproval = selectedOption.dataset.requiresApproval === '1';
        
        // Update category info
        let infoText = '';
        if (budget > 0) {
            infoText += `Monthly Budget: ${ChurchCMS.formatCurrency(budget)}. `;
        }
        if (requiresApproval) {
            infoText += 'This category requires approval for all expenses.';
        }
        
        categoryInfo.textContent = infoText;
        
        // Check budget when amount changes
        checkBudgetLimit();
    });
    
    // Amount input handler
    amountInput?.addEventListener('input', function() {
        // Format amount
        let value = this.value.replace(/[^\d.]/g, '');
        const parts = value.split('.');
        if (parts.length > 2) {
            value = parts[0] + '.' + parts.slice(1).join('');
        }
        if (parts[1] && parts[1].length > 2) {
            value = parts[0] + '.' + parts[1].substr(0, 2);
        }
        this.value = value;
        
        // Update amount display
        updateAmountDisplay();
        
        // Check budget limit
        checkBudgetLimit();
        
        // Show justification field for large amounts
        const amount = parseFloat(value) || 0;
        if (amount > 50000) { // 50,000 KES
            justificationField.classList.remove('d-none');
            document.getElementById('justification').setAttribute('required', 'required');
        } else {
            justificationField.classList.add('d-none');
            document.getElementById('justification').removeAttribute('required');
        }
    });
    
    // Check budget limit function
    function checkBudgetLimit() {
        const categorySelect = document.getElementById('category_id');
        const selectedOption = categorySelect?.options[categorySelect.selectedIndex];
        const budget = parseFloat(selectedOption?.dataset.budget) || 0;
        const amount = parseFloat(amountInput?.value) || 0;
        
        if (budget > 0 && amount > 0) {
            // Get current month spending for this category via AJAX
            fetch(`ajax/get_category_spending.php?category_id=${categorySelect.value}&month=${document.getElementById('expense_date').value}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const spent = parseFloat(data.spent);
                        const remaining = budget - spent;
                        const afterExpense = remaining - amount;
                        
                        const budgetMessage = document.getElementById('budgetMessage');
                        
                        if (amount > remaining) {
                            budgetWarning.classList.remove('d-none');
                            budgetWarning.className = 'form-text text-danger';
                            budgetMessage.textContent = `Exceeds budget! Spent: ${ChurchCMS.formatCurrency(spent)}, Remaining: ${ChurchCMS.formatCurrency(remaining)}`;
                        } else if (afterExpense < (budget * 0.1)) { // Less than 10% remaining
                            budgetWarning.classList.remove('d-none');
                            budgetWarning.className = 'form-text text-warning';
                            budgetMessage.textContent = `Budget Alert: After this expense, only ${ChurchCMS.formatCurrency(afterExpense)} will remain`;
                        } else {
                            budgetWarning.classList.add('d-none');
                        }
                    }
                })
                .catch(error => {
                    console.error('Error checking budget:', error);
                });
        } else {
            budgetWarning.classList.add('d-none');
        }
    }
    
    // Update amount display
    function updateAmountDisplay() {
        const amount = parseFloat(amountInput?.value) || 0;
        const display = document.getElementById('amountDisplay');
        
        if (display) {
            display.textContent = ChurchCMS.formatCurrency(amount);
        }
    }
    
    // Add amount display element
    if (amountInput) {
        const displayElement = document.createElement('div');
        displayElement.id = 'amountDisplay';
        displayElement.className = 'mt-2 fw-bold text-danger';
        displayElement.textContent = ChurchCMS.formatCurrency(0);
        amountInput.parentNode.appendChild(displayElement);
        updateAmountDisplay();
    }
    
    // Payment method specific fields
    paymentMethodSelect?.addEventListener('change', function() {
        const referenceField = document.getElementById('reference_number');
        const referenceLabel = referenceField?.previousElementSibling;
        
        if (referenceLabel) {
            switch (this.value) {
                case 'mpesa':
                    referenceLabel.textContent = 'M-Pesa Transaction Code';
                    referenceField.placeholder = 'e.g., QH72KXMZI8';
                    referenceField.setAttribute('required', 'required');
                    break;
                case 'bank_transfer':
                    referenceLabel.textContent = 'Bank Reference Number';
                    referenceField.placeholder = 'Bank transaction reference';
                    break;
                case 'cheque':
                    referenceLabel.textContent = 'Cheque Number';
                    referenceField.placeholder = 'Cheque number';
                    referenceField.setAttribute('required', 'required');
                    break;
                case 'cash':
                    referenceLabel.textContent = 'Reference Number (Optional)';
                    referenceField.placeholder = 'Internal reference';
                    referenceField.removeAttribute('required');
                    break;
                default:
                    referenceLabel.textContent = 'Reference Number';
                    referenceField.placeholder = 'Payment reference';
                    referenceField.removeAttribute('required');
            }
        }
    });
    
    // Trigger payment method change on page load
    if (paymentMethodSelect?.value) {
        paymentMethodSelect.dispatchEvent(new Event('change'));
    }
    
    // Auto-suggest vendors based on name
    const vendorNameInput = document.getElementById('vendor_name');
    const vendorContactInput = document.getElementById('vendor_contact');
    
    function suggestVendor(searchTerm) {
        if (searchTerm.length < 3) return;
        
        fetch(`ajax/search_vendors.php?term=${encodeURIComponent(searchTerm)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.vendors.length > 0) {
                    showVendorSuggestions(data.vendors);
                }
            })
            .catch(error => {
                console.error('Error searching vendors:', error);
            });
    }
    
    function showVendorSuggestions(vendors) {
        // Remove existing suggestions
        const existingSuggestions = document.querySelector('.vendor-suggestions');
        if (existingSuggestions) {
            existingSuggestions.remove();
        }
        
        const suggestionsDiv = document.createElement('div');
        suggestionsDiv.className = 'vendor-suggestions position-absolute bg-white border rounded shadow-sm';
        suggestionsDiv.style.cssText = 'top: 100%; left: 0; right: 0; z-index: 1000; max-height: 200px; overflow-y: auto;';
        
        vendors.forEach(vendor => {
            const item = document.createElement('div');
            item.className = 'px-3 py-2 border-bottom cursor-pointer';
            item.style.cursor = 'pointer';
            item.innerHTML = `
                <div class="fw-medium">${vendor.vendor_name}</div>
                <small class="text-muted">${vendor.vendor_contact || ''}</small>
            `;
            
            item.addEventListener('click', function() {
                vendorNameInput.value = vendor.vendor_name || '';
                vendorContactInput.value = vendor.vendor_contact || '';
                suggestionsDiv.remove();
            });
            
            suggestionsDiv.appendChild(item);
        });
        
        vendorNameInput.parentNode.style.position = 'relative';
        vendorNameInput.parentNode.appendChild(suggestionsDiv);
        
        // Close suggestions when clicking outside
        document.addEventListener('click', function(e) {
            if (!vendorNameInput.contains(e.target) && !suggestionsDiv.contains(e.target)) {
                suggestionsDiv.remove();
            }
        }, { once: true });
    }
    
    // Add vendor search with debounce
    vendorNameInput?.addEventListener('input', ChurchCMS.debounce(function() {
        suggestVendor(this.value);
    }, 500));
    
    // Form submission handling
    form?.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (!this.checkValidity()) {
            e.stopPropagation();
            this.classList.add('was-validated');
            
            // Focus first invalid field
            const firstInvalid = this.querySelector('.form-control:invalid');
            if (firstInvalid) {
                firstInvalid.focus();
                ChurchCMS.showToast('Please correct the errors in the form', 'warning');
            }
            return;
        }
        
        // Show confirmation for large amounts
        const amount = parseFloat(amountInput.value) || 0;
        if (amount > 50000) { // 50,000 KES
            ChurchCMS.showConfirm(
                `You are recording a large expense of ${ChurchCMS.formatCurrency(amount)}. Please confirm this is correct and necessary.`,
                () => {
                    submitForm();
                }
            );
        } else {
            submitForm();
        }
    });
    
    function submitForm() {
        ChurchCMS.showLoading('Saving expense record...');
        form.submit();
    }
    
    // Quick amount buttons for common expenses
    const commonExpenses = [500, 1000, 2000, 5000, 10000];
    const quickAmountContainer = document.createElement('div');
    quickAmountContainer.className = 'mt-2';
    quickAmountContainer.innerHTML = '<small class="text-muted d-block mb-2">Common amounts:</small>';
    
    commonExpenses.forEach(amount => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-outline-secondary btn-sm me-1 mb-1';
        btn.textContent = ChurchCMS.formatCurrency(amount);
        btn.addEventListener('click', function() {
            amountInput.value = amount;
            amountInput.dispatchEvent(new Event('input'));
        });
        quickAmountContainer.appendChild(btn);
    });
    
    amountInput?.parentNode.appendChild(quickAmountContainer);
    
    // Auto-save draft every 30 seconds
    setInterval(function() {
        if (form && form.checkValidity()) {
            const formData = new FormData(form);
            const draftData = {};
            
            for (let [key, value] of formData.entries()) {
                draftData[key] = value;
            }
            
            localStorage.setItem('expense_draft', JSON.stringify({
                data: draftData,
                timestamp: Date.now()
            }));
        }
    }, ChurchCMS.config.autoSaveInterval);
    
    // Load draft on page load
    const draft = localStorage.getItem('expense_draft');
    if (draft && !<?php echo !empty($_POST) ? 'true' : 'false'; ?>) {
        try {
            const draftData = JSON.parse(draft);
            const timeDiff = Date.now() - draftData.timestamp;
            
            if (timeDiff < 3600000) { // Less than 1 hour old
                if (confirm('A draft of this form was found. Would you like to restore it?')) {
                    Object.keys(draftData.data).forEach(key => {
                        const field = form.querySelector(`[name="${key}"]`);
                        if (field && field.type !== 'file') {
                            if (field.type === 'checkbox') {
                                field.checked = draftData.data[key] === 'on';
                            } else {
                                field.value = draftData.data[key];
                            }
                            field.dispatchEvent(new Event('change'));
                        }
                    });
                }
            }
        } catch (e) {
            console.error('Error loading draft:', e);
        }
    }
    
    // Clear draft when form is submitted
    form?.addEventListener('submit', function() {
        localStorage.removeItem('expense_draft');
    });
    
    // Receipt file preview
    const receiptInput = document.getElementById('receipt');
    receiptInput?.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            // Show file info
            const fileInfo = document.createElement('div');
            fileInfo.className = 'mt-2 small text-muted';
            fileInfo.innerHTML = `
                <i class="fas fa-file me-1"></i>
                Selected: ${file.name} (${ChurchCMS.formatFileSize ? ChurchCMS.formatFileSize(file.size) : file.size + ' bytes'})
            `;
            
            // Remove existing file info
            const existingInfo = this.parentNode.querySelector('.file-info');
            if (existingInfo) {
                existingInfo.remove();
            }
            
            fileInfo.className += ' file-info';
            this.parentNode.appendChild(fileInfo);
        }
    });
    
    // Expense category templates
    const expenseTemplates = {
        'utilities': {
            description: 'Monthly utility payment for church facilities',
            vendor_name: 'Kenya Power / Nairobi Water'
        },
        'maintenance': {
            description: 'Building and equipment maintenance costs',
            vendor_name: 'Maintenance Contractor'
        },
        'office_supplies': {
            description: 'Office stationery and supplies purchase',
            vendor_name: 'Office Supplies Store'
        },
        'transport': {
            description: 'Transportation and travel expenses',
            vendor_name: 'Transport Provider'
        }
    };
    
    // Auto-fill based on category selection
    categorySelect?.addEventListener('change', function() {
        const categoryName = this.options[this.selectedIndex].text.toLowerCase();
        
        Object.keys(expenseTemplates).forEach(key => {
            if (categoryName.includes(key)) {
                const template = expenseTemplates[key];
                
                if (!document.getElementById('description').value) {
                    document.getElementById('description').value = template.description;
                }
                
                if (!document.getElementById('vendor_name').value) {
                    document.getElementById('vendor_name').value = template.vendor_name;
                }
            }
        });
    });
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + S = Save form
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        document.querySelector('form').dispatchEvent(new Event('submit'));
    }
    
    // Ctrl/Cmd + Shift + N = Save and new
    if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'N') {
        e.preventDefault();
        const saveAndNewBtn = document.querySelector('button[name="save_and_new"]');
        if (saveAndNewBtn) {
            saveAndNewBtn.click();
        }
    }
});

// Utility function to format file size
ChurchCMS.formatFileSize = function(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};
</script>

<?php
// Include footer
include '../../includes/footer.php';
?>