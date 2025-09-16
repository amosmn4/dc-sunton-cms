<?php
/**
 * Edit Equipment Page
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

// Get equipment ID
$equipment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$equipment_id) {
    setFlashMessage('error', 'Invalid equipment ID.');
    header('Location: ' . BASE_URL . 'modules/equipment/');
    exit;
}

$errors = [];
$equipment = null;

try {
    $db = Database::getInstance();
    
    // Get equipment details
    $equipmentStmt = $db->executeQuery("SELECT * FROM equipment WHERE id = ?", [$equipment_id]);
    $equipment = $equipmentStmt->fetch();
    
    if (!$equipment) {
        setFlashMessage('error', 'Equipment not found.');
        header('Location: ' . BASE_URL . 'modules/equipment/');
        exit;
    }
    
    // Get categories for dropdown
    $categoriesStmt = $db->executeQuery("SELECT * FROM equipment_categories ORDER BY name");
    $categories = $categoriesStmt->fetchAll();
    
    // Get members for responsible person dropdown
    $membersStmt = $db->executeQuery("SELECT id, first_name, last_name FROM members WHERE membership_status = 'active' ORDER BY first_name, last_name");
    $members = $membersStmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Edit equipment error: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred while loading equipment data.');
    header('Location: ' . BASE_URL . 'modules/equipment/');
    exit;
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
            
            // Check if equipment code already exists (excluding current equipment)
            $existingStmt = $db->executeQuery("SELECT id FROM equipment WHERE equipment_code = ? AND id != ?", [$data['equipment_code'], $equipment_id]);
            if ($existingStmt->fetch()) {
                $errors['equipment_code'] = 'Equipment code already exists';
            }
            
            if (empty($errors)) {
                // Calculate next maintenance date if interval changed
                $nextMaintenanceDate = $equipment['next_maintenance_date'];
                if (!empty($data['maintenance_interval_days']) && $data['maintenance_interval_days'] != $equipment['maintenance_interval_days']) {
                    $intervalDays = (int)$data['maintenance_interval_days'];
                    $lastMaintenanceDate = $equipment['last_maintenance_date'] ?: $equipment['purchase_date'] ?: date('Y-m-d');
                    if ($lastMaintenanceDate && $lastMaintenanceDate !== '0000-00-00') {
                        $nextMaintenanceDate = date('Y-m-d', strtotime($lastMaintenanceDate . " + {$intervalDays} days"));
                    }
                }
                
                // Prepare data for update
                $updateData = [
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
                    'responsible_person_id' => !empty($data['responsible_person_id']) ? $data['responsible_person_id'] : null
                ];
                
                // Update equipment
                $updated = updateRecord('equipment', $updateData, ['id' => $equipment_id]);
                
                if ($updated) {
                    // Log activity
                    logActivity('Equipment updated', 'equipment', $equipment_id, $equipment, $updateData);
                    
                    setFlashMessage('success', 'Equipment updated successfully!');
                    header('Location: ' . BASE_URL . 'modules/equipment/view.php?id=' . $equipment_id);
                    exit;
                } else {
                    $errors['general'] = 'Failed to update equipment. Please try again.';
                }
            }
        }
    } catch (Exception $e) {
        error_log("Update equipment error: " . $e->getMessage());
        $errors['general'] = 'An error occurred while updating equipment.';
    }
}

// Page variables
$page_title = 'Edit Equipment: ' . $equipment['name'];
$page_icon = 'fas fa-edit';

// Breadcrumb
$breadcrumb = [
    ['title' => 'Equipment', 'url' => BASE_URL . 'modules/equipment/'],
    ['title' => $equipment['name'], 'url' => BASE_URL . 'modules/equipment/view.php?id=' . $equipment_id],
    ['title' => 'Edit']
];

include '../../includes/header.php';
?>

<div class="container-fluid px-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <form method="POST" class="needs-validation" novalidate>
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
                                           value="<?php echo htmlspecialchars($_POST['equipment_code'] ?? $equipment['equipment_code']); ?>" 
                                           required>
                                    <?php if (isset($errors['equipment_code'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['equipment_code']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Equipment Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" 
                                           id="name" name="name" 
                                           value="<?php echo htmlspecialchars($_POST['name'] ?? $equipment['name']); ?>" 
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
                                                    <?php echo ($_POST['category_id'] ?? $equipment['category_id']) == $category['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($errors['category_id'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['category_id']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                    <select class="form-select <?php echo isset($errors['status']) ? 'is-invalid' : ''; ?>" 
                                            id="status" name="status" required>
                                        <?php foreach (EQUIPMENT_STATUS as $key => $label): ?>
                                            <option value="<?php echo $key; ?>" 
                                                    <?php echo ($_POST['status'] ?? $equipment['status']) === $key ? 'selected' : ''; ?>>
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
                                      id="description" name="description" rows="3"><?php echo htmlspecialchars($_POST['description'] ?? $equipment['description']); ?></textarea>
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
                                    <input type="text" class="form-control" 
                                           id="brand" name="brand" 
                                           value="<?php echo htmlspecialchars($_POST['brand'] ?? $equipment['brand']); ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="model" class="form-label">Model</label>
                                    <input type="text" class="form-control" 
                                           id="model" name="model" 
                                           value="<?php echo htmlspecialchars($_POST['model'] ?? $equipment['model']); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="serial_number" class="form-label">Serial Number</label>
                                    <input type="text" class="form-control" 
                                           id="serial_number" name="serial_number" 
                                           value="<?php echo htmlspecialchars($_POST['serial_number'] ?? $equipment['serial_number']); ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="location" class="form-label">Location</label>
                                    <input type="text" class="form-control" 
                                           id="location" name="location" 
                                           value="<?php echo htmlspecialchars($_POST['location'] ?? $equipment['location']); ?>">
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
                                    <input type="date" class="form-control" 
                                           id="purchase_date" name="purchase_date" 
                                           value="<?php echo htmlspecialchars($_POST['purchase_date'] ?? $equipment['purchase_date']); ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="purchase_price" class="form-label">Purchase Price (<?php echo CURRENCY_SYMBOL; ?>)</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><?php echo CURRENCY_SYMBOL; ?></span>
                                        <input type="number" class="form-control" 
                                               id="purchase_price" name="purchase_price" step="0.01" min="0"
                                               value="<?php echo htmlspecialchars($_POST['purchase_price'] ?? $equipment['purchase_price']); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="warranty_expiry" class="form-label">Warranty Expiry Date</label>
                                    <input type="date" class="form-control" 
                                           id="warranty_expiry" name="warranty_expiry" 
                                           value="<?php echo htmlspecialchars($_POST['warranty_expiry'] ?? $equipment['warranty_expiry']); ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="supplier_name" class="form-label">Supplier Name</label>
                                    <input type="text" class="form-control" 
                                           id="supplier_name" name="supplier_name" 
                                           value="<?php echo htmlspecialchars($_POST['supplier_name'] ?? $equipment['supplier_name']); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="supplier_contact" class="form-label">Supplier Contact</label>
                            <input type="text" class="form-control" 
                                   id="supplier_contact" name="supplier_contact" 
                                   value="<?php echo htmlspecialchars($_POST['supplier_contact'] ?? $equipment['supplier_contact']); ?>">
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
                                    <select class="form-select" 
                                            id="responsible_person_id" name="responsible_person_id">
                                        <option value="">Select Responsible Person</option>
                                        <?php foreach ($members as $member): ?>
                                            <option value="<?php echo $member['id']; ?>" 
                                                    <?php echo ($_POST['responsible_person_id'] ?? $equipment['responsible_person_id']) == $member['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="maintenance_interval_days" class="form-label">Maintenance Interval (Days)</label>
                                    <input type="number" class="form-control" 
                                           id="maintenance_interval_days" name="maintenance_interval_days" 
                                           value="<?php echo htmlspecialchars($_POST['maintenance_interval_days'] ?? $equipment['maintenance_interval_days']); ?>" 
                                           min="1" max="3650">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="condition_notes" class="form-label">Condition Notes</label>
                            <textarea class="form-control" 
                                      id="condition_notes" name="condition_notes" rows="3"><?php echo htmlspecialchars($_POST['condition_notes'] ?? $equipment['condition_notes']); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <a href="<?php echo BASE_URL; ?>modules/equipment/view.php?id=<?php echo $equipment_id; ?>" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Details
                                </a>
                            </div>
                            
                            <div>
                                <a href="<?php echo BASE_URL; ?>modules/equipment/view.php?id=<?php echo $equipment_id; ?>" class="btn btn-outline-secondary me-2">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-church-primary">
                                    <i class="fas fa-save me-2"></i>Update Equipment
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
    // Form validation
    const form = document.querySelector('.needs-validation');
    form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        form.classList.add('was-validated');
    });
    
    // Auto-format price input
    const priceInput = document.getElementById('purchase_price');
    priceInput.addEventListener('blur', function() {
        if (this.value) {
            this.value = parseFloat(this.value).toFixed(2);
        }
    });
    
    // Status change warnings
    const statusSelect = document.getElementById('status');
    const originalStatus = '<?php echo $equipment['status']; ?>';
    
    statusSelect.addEventListener('change', function() {
        if (originalStatus !== this.value) {
            if (this.value === 'damaged' || this.value === 'retired') {
                if (!confirm('Changing status to "' + this.options[this.selectedIndex].text + '" will affect maintenance scheduling. Continue?')) {
                    this.value = originalStatus;
                }
            }
        }
    });
    
    // Maintenance interval change notification
    const maintenanceIntervalInput = document.getElementById('maintenance_interval_days');
    const originalInterval = <?php echo $equipment['maintenance_interval_days'] ?: 365; ?>;
    
    maintenanceIntervalInput.addEventListener('change', function() {
        if (originalInterval != this.value && this.value > 0) {
            ChurchCMS.showToast('Maintenance interval changed. Next maintenance date will be recalculated.', 'info');
        }
    });
    
    // Auto-save functionality
    let hasUnsavedChanges = false;
    const inputs = document.querySelectorAll('input, select, textarea');
    
    inputs.forEach(input => {
        input.addEventListener('input', function() {
            hasUnsavedChanges = true;
        });
    });
    
    // Warn before leaving with unsaved changes
    window.addEventListener('beforeunload', function(e) {
        if (hasUnsavedChanges) {
            e.preventDefault();
            e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            return e.returnValue;
        }
    });
    
    // Clear unsaved changes flag on form submit
    form.addEventListener('submit', function() {
        hasUnsavedChanges = false;
    });
});
</script>

<?php include '../../includes/footer.php'; ?>