<?php
/**
 * Add New Equipment Page
 * Deliverance Church Management System
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Check authentication and permissions
requireLogin();
if (!hasPermission('equipment')) {
    setFlashMessage('error', 'You do not have permission to access this page.');
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit;
}

// Page variables
$page_title = 'Add New Equipment';
$page_icon = 'fas fa-plus';

// Breadcrumb
$breadcrumb = [
    ['title' => 'Equipment', 'url' => BASE_URL . 'modules/equipment/'],
    ['title' => 'Add Equipment']
];

// Initialize variables
$equipment_code = '';
$errors = [];

try {
    $db = Database::getInstance();
    
    // Get categories for dropdown
    $categoriesStmt = $db->executeQuery("SELECT * FROM equipment_categories ORDER BY name");
    $categories = $categoriesStmt->fetchAll();
    
    // Get members for responsible person dropdown
    $membersStmt = $db->executeQuery("SELECT id, first_name, last_name FROM members WHERE membership_status = 'active' ORDER BY first_name, last_name");
    $members = $membersStmt->fetchAll();
    
    // Generate next equipment code
    $codeStmt = $db->executeQuery("SELECT equipment_code FROM equipment ORDER BY equipment_code DESC LIMIT 1");
    $lastCode = $codeStmt->fetch();
    
    if ($lastCode && preg_match('/EQP(\d+)/', $lastCode['equipment_code'], $matches)) {
        $nextNumber = intval($matches[1]) + 1;
        $equipment_code = 'EQP' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    } else {
        $equipment_code = 'EQP000001';
    }
    
} catch (Exception $e) {
    error_log("Add equipment error: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred while loading the form data.');
    $categories = [];
    $members = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate input
        $validation = validateInput($_POST, [
            'equipment_code' => ['required', 'max:50'],
            'name' => ['required', 'max:100'],
            'category_id' => ['required', 'numeric'],
            'status' => ['required'],
            'brand' => ['max:50'],
            'model' => ['max:50'],
            'serial_number' => ['max:100'],
            'purchase_date' => ['date'],
            'purchase_price' => ['numeric'],
            'warranty_expiry' => ['date'],
            'supplier_name' => ['max:100'],
            'supplier_contact' => ['max:100'],
            'location' => ['max:100'],
            'responsible_person_id' => ['numeric'],
            'maintenance_interval_days' => ['numeric'],
            'description' => ['max:1000'],
            'condition_notes' => ['max:1000']
        ]);
        
        if (!$validation['valid']) {
            $errors = $validation['errors'];
        } else {
            $data = $validation['data'];
            
            // Check if equipment code already exists
            $existingStmt = $db->executeQuery("SELECT id FROM equipment WHERE equipment_code = ?", [$data['equipment_code']]);
            if ($existingStmt->fetch()) {
                $errors['equipment_code'] = 'Equipment code already exists';
            }
            
            if (empty($errors)) {
                // Calculate next maintenance date if interval is provided
                $nextMaintenanceDate = null;
                if (!empty($data['maintenance_interval_days']) && $data['maintenance_interval_days'] > 0) {
                    $intervalDays = (int)$data['maintenance_interval_days'];
                    $baseDate = !empty($data['purchase_date']) ? $data['purchase_date'] : date('Y-m-d');
                    $nextMaintenanceDate = date('Y-m-d', strtotime($baseDate . " + {$intervalDays} days"));
                }
                
                // Prepare data for insertion
                $insertData = [
                    'equipment_code' => $data['equipment_code'],
                    'name' => $data['name'],
                    'category_id' => $data['category_id'],
                    'description' => $data['description'] ?? '',
                    'brand' => $data['brand'] ?? '',
                    'model' => $data['model'] ?? '',
                    'serial_number' => $data['serial_number'] ?? '',
                    'purchase_date' => !empty($data['purchase_date']) ? $data['purchase_date'] : null,
                    'purchase_price' => !empty($data['purchase_price']) ? $data['purchase_price'] : null,
                    'warranty_expiry' => !empty($data['warranty_expiry']) ? $data['warranty_expiry'] : null,
                    'supplier_name' => $data['supplier_name'] ?? '',
                    'supplier_contact' => $data['supplier_contact'] ?? '',
                    'location' => $data['location'] ?? '',
                    'status' => $data['status'],
                    'condition_notes' => $data['condition_notes'] ?? '',
                    'maintenance_interval_days' => !empty($data['maintenance_interval_days']) ? $data['maintenance_interval_days'] : 365,
                    'next_maintenance_date' => $nextMaintenanceDate,
                    'responsible_person_id' => !empty($data['responsible_person_id']) ? $data['responsible_person_id'] : null,
                    'created_by' => $_SESSION['user_id']
                ];
                
                // Insert equipment
                $equipmentId = insertRecord('equipment', $insertData);
                
                if ($equipmentId) {
                    // Log activity
                    logActivity('Equipment added', 'equipment', $equipmentId, null, $insertData);
                    
                    setFlashMessage('success', 'Equipment added successfully!');
                    header('Location: ' . BASE_URL . 'modules/equipment/view.php?id=' . $equipmentId);
                    exit;
                } else {
                    $errors['general'] = 'Failed to add equipment. Please try again.';
                }
            }
        }
    } catch (Exception $e) {
        error_log("Add equipment error: " . $e->getMessage());
        $errors['general'] = 'An error occurred while adding equipment.';
    }
}

include '../../includes/header.php';
?>

<div class="container-fluid px-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <form method="POST" class="needs-validation" novalidate enctype="multipart/form-data">
                <!-- General Information Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>General Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($errors['general'])): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo $errors['general']; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="equipment_code" class="form-label">Equipment Code <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control <?php echo isset($errors['equipment_code']) ? 'is-invalid' : ''; ?>" 
                                           id="equipment_code" name="equipment_code" 
                                           value="<?php echo htmlspecialchars($_POST['equipment_code'] ?? $equipment_code); ?>" 
                                           required>
                                    <?php if (isset($errors['equipment_code'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['equipment_code']; ?></div>
                                    <?php endif; ?>
                                    <div class="form-text">Unique identifier for this equipment (auto-generated)</div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Equipment Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" 
                                           id="name" name="name" 
                                           value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" 
                                           required>
                                    <?php if (isset($errors['name'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['name']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="category_id" class="form-label">Category <span class="text-danger">*</span></label>
                                    <select class="form-select <?php echo isset($errors['category_id']) ? 'is-invalid' : ''; ?>" 
                                            id="category_id" name="category_id" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>" 
                                                    <?php echo ($_POST['category_id'] ?? '') == $category['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($errors['category_id'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['category_id']; ?></div>
                                    <?php endif; ?>
                                    <div class="form-text">
                                        <a href="<?php echo BASE_URL; ?>modules/equipment/categories.php" target="_blank">
                                            Manage Categories
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                    <select class="form-select <?php echo isset($errors['status']) ? 'is-invalid' : ''; ?>" 
                                            id="status" name="status" required>
                                        <option value="">Select Status</option>
                                        <?php foreach (EQUIPMENT_STATUS as $key => $label): ?>
                                            <option value="<?php echo $key; ?>" 
                                                    <?php echo ($_POST['status'] ?? 'good') === $key ? 'selected' : ''; ?>>
                                                <?php echo $label; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($errors['status'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['status']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control <?php echo isset($errors['description']) ? 'is-invalid' : ''; ?>" 
                                      id="description" name="description" rows="3" 
                                      placeholder="Describe the equipment and its purpose"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                            <?php if (isset($errors['description'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['description']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Product Details Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-tag me-2"></i>Product Details
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="brand" class="form-label">Brand</label>
                                    <input type="text" class="form-control <?php echo isset($errors['brand']) ? 'is-invalid' : ''; ?>" 
                                           id="brand" name="brand" 
                                           value="<?php echo htmlspecialchars($_POST['brand'] ?? ''); ?>">
                                    <?php if (isset($errors['brand'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['brand']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="model" class="form-label">Model</label>
                                    <input type="text" class="form-control <?php echo isset($errors['model']) ? 'is-invalid' : ''; ?>" 
                                           id="model" name="model" 
                                           value="<?php echo htmlspecialchars($_POST['model'] ?? ''); ?>">
                                    <?php if (isset($errors['model'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['model']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="serial_number" class="form-label">Serial Number</label>
                                    <input type="text" class="form-control <?php echo isset($errors['serial_number']) ? 'is-invalid' : ''; ?>" 
                                           id="serial_number" name="serial_number" 
                                           value="<?php echo htmlspecialchars($_POST['serial_number'] ?? ''); ?>">
                                    <?php if (isset($errors['serial_number'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['serial_number']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="location" class="form-label">Location</label>
                                    <input type="text" class="form-control <?php echo isset($errors['location']) ? 'is-invalid' : ''; ?>" 
                                           id="location" name="location" 
                                           value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>" 
                                           placeholder="e.g., Main Hall, Office, Storage Room">
                                    <?php if (isset($errors['location'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['location']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Purchase Information Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-shopping-cart me-2"></i>Purchase Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="purchase_date" class="form-label">Purchase Date</label>
                                    <input type="date" class="form-control <?php echo isset($errors['purchase_date']) ? 'is-invalid' : ''; ?>" 
                                           id="purchase_date" name="purchase_date" 
                                           value="<?php echo htmlspecialchars($_POST['purchase_date'] ?? ''); ?>">
                                    <?php if (isset($errors['purchase_date'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['purchase_date']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="purchase_price" class="form-label">Purchase Price (<?php echo CURRENCY_SYMBOL; ?>)</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><?php echo CURRENCY_SYMBOL; ?></span>
                                        <input type="number" class="form-control <?php echo isset($errors['purchase_price']) ? 'is-invalid' : ''; ?>" 
                                               id="purchase_price" name="purchase_price" step="0.01" min="0"
                                               value="<?php echo htmlspecialchars($_POST['purchase_price'] ?? ''); ?>">
                                        <?php if (isset($errors['purchase_price'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['purchase_price']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="warranty_expiry" class="form-label">Warranty Expiry Date</label>
                                    <input type="date" class="form-control <?php echo isset($errors['warranty_expiry']) ? 'is-invalid' : ''; ?>" 
                                           id="warranty_expiry" name="warranty_expiry" 
                                           value="<?php echo htmlspecialchars($_POST['warranty_expiry'] ?? ''); ?>">
                                    <?php if (isset($errors['warranty_expiry'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['warranty_expiry']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="supplier_name" class="form-label">Supplier Name</label>
                                    <input type="text" class="form-control <?php echo isset($errors['supplier_name']) ? 'is-invalid' : ''; ?>" 
                                           id="supplier_name" name="supplier_name" 
                                           value="<?php echo htmlspecialchars($_POST['supplier_name'] ?? ''); ?>">
                                    <?php if (isset($errors['supplier_name'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['supplier_name']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="supplier_contact" class="form-label">Supplier Contact</label>
                            <input type="text" class="form-control <?php echo isset($errors['supplier_contact']) ? 'is-invalid' : ''; ?>" 
                                   id="supplier_contact" name="supplier_contact" 
                                   value="<?php echo htmlspecialchars($_POST['supplier_contact'] ?? ''); ?>" 
                                   placeholder="Phone number or email">
                            <?php if (isset($errors['supplier_contact'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['supplier_contact']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Maintenance & Responsibility Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-wrench me-2"></i>Maintenance & Responsibility
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="responsible_person_id" class="form-label">Responsible Person</label>
                                    <select class="form-select <?php echo isset($errors['responsible_person_id']) ? 'is-invalid' : ''; ?>" 
                                            id="responsible_person_id" name="responsible_person_id">
                                        <option value="">Select Responsible Person</option>
                                        <?php foreach ($members as $member): ?>
                                            <option value="<?php echo $member['id']; ?>" 
                                                    <?php echo ($_POST['responsible_person_id'] ?? '') == $member['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($errors['responsible_person_id'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['responsible_person_id']; ?></div>
                                    <?php endif; ?>
                                    <div class="form-text">Person responsible for this equipment</div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="maintenance_interval_days" class="form-label">Maintenance Interval (Days)</label>
                                    <input type="number" class="form-control <?php echo isset($errors['maintenance_interval_days']) ? 'is-invalid' : ''; ?>" 
                                           id="maintenance_interval_days" name="maintenance_interval_days" 
                                           value="<?php echo htmlspecialchars($_POST['maintenance_interval_days'] ?? '365'); ?>" 
                                           min="1" max="3650">
                                    <?php if (isset($errors['maintenance_interval_days'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['maintenance_interval_days']; ?></div>
                                    <?php endif; ?>
                                    <div class="form-text">How often this equipment needs maintenance (default: 365 days)</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="condition_notes" class="form-label">Condition Notes</label>
                            <textarea class="form-control <?php echo isset($errors['condition_notes']) ? 'is-invalid' : ''; ?>" 
                                      id="condition_notes" name="condition_notes" rows="3" 
                                      placeholder="Any notes about the current condition of the equipment"><?php echo htmlspecialchars($_POST['condition_notes'] ?? ''); ?></textarea>
                            <?php if (isset($errors['condition_notes'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['condition_notes']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <a href="<?php echo BASE_URL; ?>modules/equipment/" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Cancel
                            </a>
                            
                            <div>
                                <button type="reset" class="btn btn-outline-secondary me-2">
                                    <i class="fas fa-undo me-2"></i>Reset Form
                                </button>
                                <button type="submit" class="btn btn-church-primary">
                                    <i class="fas fa-save me-2"></i>Add Equipment
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-calculate next maintenance date
    const purchaseDateInput = document.getElementById('purchase_date');
    const maintenanceIntervalInput = document.getElementById('maintenance_interval_days');
    
    function updateMaintenancePreview() {
        const purchaseDate = purchaseDateInput.value;
        const interval = parseInt(maintenanceIntervalInput.value) || 365;
        
        if (purchaseDate) {
            const date = new Date(purchaseDate);
            date.setDate(date.getDate() + interval);
            
            const nextMaintenance = date.toISOString().split('T')[0];
            const previewElement = document.getElementById('maintenance-preview');
            
            if (!previewElement) {
                const preview = document.createElement('div');
                preview.id = 'maintenance-preview';
                preview.className = 'form-text text-info';
                preview.innerHTML = '<i class="fas fa-info-circle me-1"></i>Next maintenance due: ' + ChurchCMS.formatDate(nextMaintenance);
                maintenanceIntervalInput.parentNode.appendChild(preview);
            } else {
                previewElement.innerHTML = '<i class="fas fa-info-circle me-1"></i>Next maintenance due: ' + ChurchCMS.formatDate(nextMaintenance);
            }
        }
    }
    
    purchaseDateInput.addEventListener('change', updateMaintenancePreview);
    maintenanceIntervalInput.addEventListener('input', updateMaintenancePreview);
    
    // Auto-format price input
    const priceInput = document.getElementById('purchase_price');
    priceInput.addEventListener('blur', function() {
        if (this.value) {
            this.value = parseFloat(this.value).toFixed(2);
        }
    });
    
    // Validate form on submit
    const form = document.querySelector('.needs-validation');
    form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        form.classList.add('was-validated');
    });
    
    // Auto-generate equipment code if empty
    const equipmentCodeInput = document.getElementById('equipment_code');
    const nameInput = document.getElementById('name');
    
    nameInput.addEventListener('blur', function() {
        if (!equipmentCodeInput.value && this.value) {
            // Generate code based on name if equipment code is empty
            const baseName = this.value.substring(0, 3).toUpperCase();
            const randomNum = Math.floor(Math.random() * 1000).toString().padStart(3, '0');
            equipmentCodeInput.value = 'EQP' + baseName + randomNum;
        }
    });
    
    // Calculate warranty expiry based on purchase date
    purchaseDateInput.addEventListener('change', function() {
        const warrantyInput = document.getElementById('warranty_expiry');
        if (this.value && !warrantyInput.value) {
            const purchaseDate = new Date(this.value);
            purchaseDate.setFullYear(purchaseDate.getFullYear() + 1); // Default 1 year warranty
            warrantyInput.value = purchaseDate.toISOString().split('T')[0];
        }
    });
});

// Auto-save form data
let autoSaveTimer;
function autoSave() {
    clearTimeout(autoSaveTimer);
    autoSaveTimer = setTimeout(() => {
        const formData = new FormData(document.querySelector('form'));
        const data = {};
        for (let [key, value] of formData.entries()) {
            data[key] = value;
        }
        localStorage.setItem('equipment_form_data', JSON.stringify(data));
    }, 2000);
}

// Load auto-saved data
window.addEventListener('load', function() {
    const savedData = localStorage.getItem('equipment_form_data');
    if (savedData) {
        try {
            const data = JSON.parse(savedData);
            Object.keys(data).forEach(key => {
                const input = document.querySelector(`[name="${key}"]`);
                if (input && !input.value) {
                    input.value = data[key];
                }
            });
        } catch (e) {
            console.error('Error loading saved data:', e);
        }
    }
});

// Add event listeners for auto-save
document.querySelectorAll('input, select, textarea').forEach(element => {
    element.addEventListener('input', autoSave);
    element.addEventListener('change', autoSave);
});

// Clear auto-saved data on successful submit
window.addEventListener('beforeunload', function() {
    if (event.target.closest('form').querySelector('.was-validated')) {
        localStorage.removeItem('equipment_form_data');
    }
});
</script>

<?php include '../../includes/footer.php'; ?>