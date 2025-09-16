<?php
/**
 * Add Income Form
 * Deliverance Church Management System
 * 
 * Form to record new income transactions
 */

// Include configuration and functions
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Check authentication and permissions
requireLogin();
if (!hasPermission('finance')) {
    setFlashMessage('error', 'Access denied. You do not have permission to add income records.');
    redirect(BASE_URL . 'modules/dashboard/');
}

// Page configuration
$page_title = 'Add Income Record';
$page_icon = 'fas fa-plus-circle';
$page_description = 'Record a new income transaction';

$breadcrumb = [
    ['title' => 'Finance', 'url' => 'index.php'],
    ['title' => 'Income', 'url' => 'income.php'],
    ['title' => 'Add Income']
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
            'transaction_date' => ['required', 'date'],
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
            
            if (strtotime($formData['transaction_date']) > time()) {
                $errors['transaction_date'] = 'Transaction date cannot be in the future';
            }
            
            // Validate donor email if provided
            if (!empty($formData['donor_email']) && !isValidEmail($formData['donor_email'])) {
                $errors['donor_email'] = 'Invalid email address';
            }
            
            // Validate donor phone if provided
            if (!empty($formData['donor_phone']) && !isValidPhoneNumber($formData['donor_phone'])) {
                $errors['donor_phone'] = 'Invalid phone number format';
            }
            
            // Check if transaction ID already exists
            if (!empty($formData['reference_number'])) {
                $existing = getRecord('income', 'reference_number', $formData['reference_number']);
                if ($existing) {
                    $errors['reference_number'] = 'Reference number already exists';
                }
            }
        }
        
        // If no errors, proceed with saving
        if (empty($errors)) {
            $db->beginTransaction();
            
            try {
                // Generate transaction ID
                $transactionId = generateTransactionId('INC');
                
                // Handle file upload (receipt)
                $receiptPath = '';
                if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
                    $uploadResult = handleFileUpload(
                        $_FILES['receipt'],
                        ASSETS_PATH . RECEIPTS_PATH,
                        ALLOWED_DOCUMENT_TYPES,
                        MAX_DOCUMENT_SIZE
                    );
                    
                    if ($uploadResult['success']) {
                        $receiptPath = RECEIPTS_PATH . $uploadResult['filename'];
                    } else {
                        $errors['receipt'] = $uploadResult['message'];
                    }
                }
                
                if (empty($errors)) {
                    // Prepare income data
                    $incomeData = [
                        'transaction_id' => $transactionId,
                        'category_id' => $formData['category_id'],
                        'amount' => $formData['amount'],
                        'currency' => DEFAULT_CURRENCY,
                        'source' => $formData['source'] ?? '',
                        'donor_name' => $formData['donor_name'] ?? '',
                        'donor_phone' => !empty($formData['donor_phone']) ? formatPhoneNumber($formData['donor_phone']) : '',
                        'donor_email' => $formData['donor_email'] ?? '',
                        'payment_method' => $formData['payment_method'],
                        'reference_number' => $formData['reference_number'] ?? '',
                        'description' => $formData['description'],
                        'transaction_date' => $formData['transaction_date'],
                        'event_id' => !empty($formData['event_id']) ? $formData['event_id'] : null,
                        'receipt_number' => $formData['receipt_number'] ?? '',
                        'receipt_path' => $receiptPath,
                        'is_anonymous' => isset($formData['is_anonymous']) ? 1 : 0,
                        'is_pledge' => isset($formData['is_pledge']) ? 1 : 0,
                        'pledge_period' => $formData['pledge_period'] ?? '',
                        'recorded_by' => $_SESSION['user_id'],
                        'status' => ($_SESSION['user_role'] === 'administrator' || $_SESSION['user_role'] === 'pastor') ? 'verified' : 'pending'
                    ];
                    
                    // Insert income record
                    $incomeId = insertRecord('income', $incomeData);
                    
                    if ($incomeId) {
                        // Log activity
                        logActivity('Added income record', 'income', $incomeId, null, $incomeData);
                        
                        $db->commit();
                        
                        setFlashMessage('success', 'Income record added successfully! Transaction ID: ' . $transactionId);
                        
                        // Redirect based on user preference
                        if (isset($_POST['save_and_new'])) {
                            redirect('add_income.php');
                        } else {
                            redirect('income.php');
                        }
                    } else {
                        throw new Exception('Failed to save income record');
                    }
                }
                
            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
        }
        
    } catch (Exception $e) {
        error_log("Add income error: " . $e->getMessage());
        $errors['general'] = 'Error saving income record. Please try again.';
    }
}

// Get income categories
try {
    $db = Database::getInstance();
    $stmt = $db->executeQuery("SELECT * FROM income_categories WHERE is_active = 1 ORDER BY name");
    $incomeCategories = $stmt->fetchAll();
    
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
    $incomeCategories = [];
    $recentEvents = [];
}

// Include header
include '../../includes/header.php';
?>

<!-- Add Income Form -->
<div class="add-income-form">
    <div class="row justify-content-center">
        <div class="col-xl-8 col-lg-10">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-plus-circle me-2"></i>Add New Income Record
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
                                    <label for="category_id" class="form-label required">Income Category</label>
                                    <select class="form-select <?php echo isset($errors['category_id']) ? 'is-invalid' : ''; ?>" 
                                            id="category_id" name="category_id" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($incomeCategories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>" 
                                                    <?php echo (isset($formData['category_id']) && $formData['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
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
                                
                                <!-- Transaction Date -->
                                <div class="mb-3">
                                    <label for="transaction_date" class="form-label required">Transaction Date</label>
                                    <input type="date" class="form-control <?php echo isset($errors['transaction_date']) ? 'is-invalid' : ''; ?>" 
                                           id="transaction_date" name="transaction_date" 
                                           value="<?php echo htmlspecialchars($formData['transaction_date'] ?? date('Y-m-d')); ?>" 
                                           max="<?php echo date('Y-m-d'); ?>" required>
                                    <?php if (isset($errors['transaction_date'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['transaction_date']; ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Reference Number -->
                                <div class="mb-3">
                                    <label for="reference_number" class="form-label">Reference Number</label>
                                    <input type="text" class="form-control <?php echo isset($errors['reference_number']) ? 'is-invalid' : ''; ?>" 
                                           id="reference_number" name="reference_number" 
                                           placeholder="M-Pesa code, check number, etc."
                                           value="<?php echo htmlspecialchars($formData['reference_number'] ?? ''); ?>">
                                    <?php if (isset($errors['reference_number'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['reference_number']; ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Receipt Number -->
                                <div class="mb-3">
                                    <label for="receipt_number" class="form-label">Receipt Number</label>
                                    <input type="text" class="form-control" 
                                           id="receipt_number" name="receipt_number" 
                                           placeholder="Official receipt number"
                                           value="<?php echo htmlspecialchars($formData['receipt_number'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <!-- Right Column -->
                            <div class="col-md-6">
                                <!-- Donor Information -->
                                <div class="card border-light mb-3">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0 text-church-blue">
                                            <i class="fas fa-user me-2"></i>Donor Information
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <!-- Anonymous Option -->
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" 
                                                   id="is_anonymous" name="is_anonymous" 
                                                   <?php echo isset($formData['is_anonymous']) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="is_anonymous">
                                                Anonymous Donation
                                            </label>
                                        </div>
                                        
                                        <div id="donorFields" class="<?php echo isset($formData['is_anonymous']) ? 'd-none' : ''; ?>">
                                            <!-- Donor Name -->
                                            <div class="mb-3">
                                                <label for="donor_name" class="form-label">Donor Name</label>
                                                <input type="text" class="form-control" 
                                                       id="donor_name" name="donor_name" 
                                                       placeholder="Full name of donor"
                                                       value="<?php echo htmlspecialchars($formData['donor_name'] ?? ''); ?>">
                                            </div>
                                            
                                            <!-- Donor Phone -->
                                            <div class="mb-3">
                                                <label for="donor_phone" class="form-label">Donor Phone</label>
                                                <input type="tel" class="form-control <?php echo isset($errors['donor_phone']) ? 'is-invalid' : ''; ?>" 
                                                       id="donor_phone" name="donor_phone" 
                                                       placeholder="+254700000000"
                                                       value="<?php echo htmlspecialchars($formData['donor_phone'] ?? ''); ?>">
                                                <?php if (isset($errors['donor_phone'])): ?>
                                                    <div class="invalid-feedback"><?php echo $errors['donor_phone']; ?></div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Donor Email -->
                                            <div class="mb-3">
                                                <label for="donor_email" class="form-label">Donor Email</label>
                                                <input type="email" class="form-control <?php echo isset($errors['donor_email']) ? 'is-invalid' : ''; ?>" 
                                                       id="donor_email" name="donor_email" 
                                                       placeholder="donor@example.com"
                                                       value="<?php echo htmlspecialchars($formData['donor_email'] ?? ''); ?>">
                                                <?php if (isset($errors['donor_email'])): ?>
                                                    <div class="invalid-feedback"><?php echo $errors['donor_email']; ?></div>
                                                <?php endif; ?>
                                            </div>
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
                                        <!-- Source/Event -->
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
                                        
                                        <!-- Source Description -->
                                        <div class="mb-3">
                                            <label for="source" class="form-label">Source/Origin</label>
                                            <input type="text" class="form-control" 
                                                   id="source" name="source" 
                                                   placeholder="e.g., Sunday Service, Special Appeal"
                                                   value="<?php echo htmlspecialchars($formData['source'] ?? ''); ?>">
                                        </div>
                                        
                                        <!-- Pledge Information -->
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" 
                                                   id="is_pledge" name="is_pledge" 
                                                   <?php echo isset($formData['is_pledge']) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="is_pledge">
                                                This is a pledge payment
                                            </label>
                                        </div>
                                        
                                        <div id="pledgeFields" class="<?php echo !isset($formData['is_pledge']) ? 'd-none' : ''; ?>">
                                            <div class="mb-3">
                                                <label for="pledge_period" class="form-label">Pledge Period</label>
                                                <select class="form-select" id="pledge_period" name="pledge_period">
                                                    <option value="">Select Period</option>
                                                    <option value="weekly" <?php echo (isset($formData['pledge_period']) && $formData['pledge_period'] === 'weekly') ? 'selected' : ''; ?>>Weekly</option>
                                                    <option value="monthly" <?php echo (isset($formData['pledge_period']) && $formData['pledge_period'] === 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                                                    <option value="quarterly" <?php echo (isset($formData['pledge_period']) && $formData['pledge_period'] === 'quarterly') ? 'selected' : ''; ?>>Quarterly</option>
                                                    <option value="yearly" <?php echo (isset($formData['pledge_period']) && $formData['pledge_period'] === 'yearly') ? 'selected' : ''; ?>>Yearly</option>
                                                    <option value="one_time" <?php echo (isset($formData['pledge_period']) && $formData['pledge_period'] === 'one_time') ? 'selected' : ''; ?>>One Time</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <!-- Receipt Upload -->
                                        <div class="mb-3">
                                            <label for="receipt" class="form-label">Upload Receipt (Optional)</label>
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
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Description -->
                        <div class="mb-3">
                            <label for="description" class="form-label required">Description</label>
                            <textarea class="form-control <?php echo isset($errors['description']) ? 'is-invalid' : ''; ?>" 
                                      id="description" name="description" rows="3" 
                                      placeholder="Detailed description of the income transaction" 
                                      required><?php echo htmlspecialchars($formData['description'] ?? ''); ?></textarea>
                            <div class="form-text">
                                Minimum 5 characters. Be specific about the purpose and nature of this income.
                            </div>
                            <?php if (isset($errors['description'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['description']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Additional Notes -->
                        <div class="mb-3">
                            <label for="notes" class="form-label">Additional Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2" 
                                      placeholder="Any additional notes or comments"><?php echo htmlspecialchars($formData['notes'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="card-footer bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <a href="income.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-1"></i>Back to Income List
                                </a>
                            </div>
                            <div class="btn-group">
                                <button type="submit" class="btn btn-church-primary">
                                    <i class="fas fa-save me-1"></i>Save Income Record
                                </button>
                                <button type="submit" name="save_and_new" value="1" class="btn btn-success">
                                    <i class="fas fa-plus me-1"></i>Save & Add Another
                                </button>
                            </div>
                        </div>
                        
                        <!-- Form Help Text -->
                        <div class="mt-3">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                <strong>Note:</strong> 
                                <?php if ($_SESSION['user_role'] === 'administrator' || $_SESSION['user_role'] === 'pastor'): ?>
                                    Income records will be automatically verified upon saving.
                                <?php else: ?>
                                    Income records will be marked as pending and require verification by an administrator or pastor.
                                <?php endif; ?>
                            </small>
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
    // Form validation
    const form = document.querySelector('.needs-validation');
    const categorySelect = document.getElementById('category_id');
    const amountInput = document.getElementById('amount');
    const anonymousCheckbox = document.getElementById('is_anonymous');
    const pledgeCheckbox = document.getElementById('is_pledge');
    const donorFields = document.getElementById('donorFields');
    const pledgeFields = document.getElementById('pledgeFields');
    const paymentMethodSelect = document.getElementById('payment_method');
    
    // Toggle donor fields based on anonymous checkbox
    anonymousCheckbox?.addEventListener('change', function() {
        if (this.checked) {
            donorFields.classList.add('d-none');
            // Clear donor fields
            donorFields.querySelectorAll('input').forEach(input => {
                input.value = '';
                input.removeAttribute('required');
            });
        } else {
            donorFields.classList.remove('d-none');
        }
    });
    
    // Toggle pledge fields based on pledge checkbox
    pledgeCheckbox?.addEventListener('change', function() {
        if (this.checked) {
            pledgeFields.classList.remove('d-none');
        } else {
            pledgeFields.classList.add('d-none');
        }
    });
    
    // Auto-format amount input
    amountInput?.addEventListener('input', function() {
        let value = this.value.replace(/[^\d.]/g, '');
        
        // Ensure only one decimal point
        const parts = value.split('.');
        if (parts.length > 2) {
            value = parts[0] + '.' + parts.slice(1).join('');
        }
        
        // Limit decimal places to 2
        if (parts[1] && parts[1].length > 2) {
            value = parts[0] + '.' + parts[1].substr(0, 2);
        }
        
        this.value = value;
        
        // Update display amount
        updateAmountDisplay();
    });
    
    // Format amount display
    function updateAmountDisplay() {
        const amount = parseFloat(amountInput.value) || 0;
        const display = document.getElementById('amountDisplay');
        
        if (display) {
            display.textContent = ChurchCMS.formatCurrency(amount);
        }
    }
    
    // Add amount display element
    if (amountInput) {
        const displayElement = document.createElement('div');
        displayElement.id = 'amountDisplay';
        displayElement.className = 'mt-2 fw-bold text-success';
        displayElement.textContent = ChurchCMS.formatCurrency(0);
        amountInput.parentNode.appendChild(displayElement);
        updateAmountDisplay();
    }
    
    // Phone number formatting
    const phoneInput = document.getElementById('donor_phone');
    phoneInput?.addEventListener('input', function() {
        this.value = ChurchCMS.formatPhone(this.value);
    });
    
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
                    referenceField.placeholder = 'Transaction reference';
                    referenceField.removeAttribute('required');
            }
        }
    });
    
    // Trigger payment method change on page load
    if (paymentMethodSelect?.value) {
        paymentMethodSelect.dispatchEvent(new Event('change'));
    }
    
    // Auto-suggest donors based on phone/name
    const donorNameInput = document.getElementById('donor_name');
    const donorPhoneInput = document.getElementById('donor_phone');
    
    function suggestDonor(searchTerm, field) {
        if (searchTerm.length < 3) return;
        
        fetch(`ajax/search_donors.php?term=${encodeURIComponent(searchTerm)}&field=${field}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.donors.length > 0) {
                    showDonorSuggestions(data.donors, field);
                }
            })
            .catch(error => {
                console.error('Error searching donors:', error);
            });
    }
    
    function showDonorSuggestions(donors, field) {
        // Remove existing suggestions
        const existingSuggestions = document.querySelector('.donor-suggestions');
        if (existingSuggestions) {
            existingSuggestions.remove();
        }
        
        const inputField = field === 'name' ? donorNameInput : donorPhoneInput;
        const suggestionsDiv = document.createElement('div');
        suggestionsDiv.className = 'donor-suggestions position-absolute bg-white border rounded shadow-sm';
        suggestionsDiv.style.cssText = 'top: 100%; left: 0; right: 0; z-index: 1000; max-height: 200px; overflow-y: auto;';
        
        donors.forEach(donor => {
            const item = document.createElement('div');
            item.className = 'px-3 py-2 border-bottom cursor-pointer';
            item.style.cursor = 'pointer';
            item.innerHTML = `
                <div class="fw-medium">${donor.donor_name}</div>
                <small class="text-muted">${donor.donor_phone || ''} ${donor.donor_email || ''}</small>
            `;
            
            item.addEventListener('click', function() {
                donorNameInput.value = donor.donor_name || '';
                donorPhoneInput.value = donor.donor_phone || '';
                document.getElementById('donor_email').value = donor.donor_email || '';
                suggestionsDiv.remove();
            });
            
            suggestionsDiv.appendChild(item);
        });
        
        inputField.parentNode.style.position = 'relative';
        inputField.parentNode.appendChild(suggestionsDiv);
        
        // Close suggestions when clicking outside
        document.addEventListener('click', function(e) {
            if (!inputField.contains(e.target) && !suggestionsDiv.contains(e.target)) {
                suggestionsDiv.remove();
            }
        }, { once: true });
    }
    
    // Add donor search with debounce
    donorNameInput?.addEventListener('input', ChurchCMS.debounce(function() {
        suggestDonor(this.value, 'name');
    }, 500));
    
    donorPhoneInput?.addEventListener('input', ChurchCMS.debounce(function() {
        suggestDonor(this.value, 'phone');
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
        if (amount > 100000) { // 100,000 KES
            ChurchCMS.showConfirm(
                `You are recording a large amount of ${ChurchCMS.formatCurrency(amount)}. Please confirm this is correct.`,
                () => {
                    submitForm();
                }
            );
        } else {
            submitForm();
        }
    });
    
    function submitForm() {
        ChurchCMS.showLoading('Saving income record...');
        
        // Submit form
        form.submit();
    }
    
    // Auto-save draft (every 30 seconds)
    setInterval(function() {
        if (form && form.checkValidity()) {
            const formData = new FormData(form);
            const draftData = {};
            
            for (let [key, value] of formData.entries()) {
                draftData[key] = value;
            }
            
            // Save to localStorage as draft
            localStorage.setItem('income_draft', JSON.stringify({
                data: draftData,
                timestamp: Date.now()
            }));
        }
    }, ChurchCMS.config.autoSaveInterval);
    
    // Load draft on page load
    const draft = localStorage.getItem('income_draft');
    if (draft && !<?php echo !empty($_POST) ? 'true' : 'false'; ?>) {
        try {
            const draftData = JSON.parse(draft);
            const timeDiff = Date.now() - draftData.timestamp;
            
            // Only load draft if less than 1 hour old
            if (timeDiff < 3600000) {
                if (confirm('A draft of this form was found. Would you like to restore it?')) {
                    Object.keys(draftData.data).forEach(key => {
                        const field = form.querySelector(`[name="${key}"]`);
                        if (field && field.type !== 'file') {
                            if (field.type === 'checkbox') {
                                field.checked = draftData.data[key] === 'on';
                            } else {
                                field.value = draftData.data[key];
                            }
                            
                            // Trigger change events
                            field.dispatchEvent(new Event('change'));
                        }
                    });
                }
            }
        } catch (e) {
            console.error('Error loading draft:', e);
        }
    }
    
    // Clear draft when form is successfully submitted
    form?.addEventListener('submit', function() {
        localStorage.removeItem('income_draft');
    });
    
    // Quick amount buttons
    const amountButtons = [100, 500, 1000, 5000, 10000];
    const quickAmountContainer = document.createElement('div');
    quickAmountContainer.className = 'mt-2';
    quickAmountContainer.innerHTML = '<small class="text-muted d-block mb-2">Quick amounts:</small>';
    
    amountButtons.forEach(amount => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-outline-secondary btn-sm me-1 mb-1';
        btn.textContent = ChurchCMS.formatCurrency(amount);
        btn.addEventListener('click', function() {
            amountInput.value = amount;
            updateAmountDisplay();
        });
        quickAmountContainer.appendChild(btn);
    });
    
    amountInput?.parentNode.appendChild(quickAmountContainer);
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
</script>

<?php
// Include footer
include '../../includes/footer.php';
?>