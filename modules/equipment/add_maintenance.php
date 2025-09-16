<?php
/**
 * Add Maintenance Record Page
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

// Get equipment ID if provided
$equipment_id = isset($_GET['equipment_id']) ? (int)$_GET['equipment_id'] : 0;
$errors = [];
$selectedEquipment = null;

try {
    $db = Database::getInstance();
    
    // Get equipment list for dropdown
    $equipmentStmt = $db->executeQuery("SELECT id, name, equipment_code, status FROM equipment ORDER BY name");
    $equipmentList = $equipmentStmt->fetchAll();
    
    // If equipment_id is provided, get equipment details
    if ($equipment_id) {
        $selectedEquipmentStmt = $db->executeQuery("SELECT * FROM equipment WHERE id = ?", [$equipment_id]);
        $selectedEquipment = $selectedEquipmentStmt->fetch();
        
        if (!$selectedEquipment) {
            $equipment_id = 0;
            $selectedEquipment = null;
        }
    }
    
} catch (Exception $e) {
    error_log("Add maintenance error: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred while loading form data.');
    $equipmentList = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate input
        $validation = validateInput($_POST, [
            'equipment_id' => ['required', 'numeric'],
            'maintenance_type' => ['required'],
            'description' => ['required', 'max:1000'],
            'maintenance_date' => ['required', 'date'],
            'performed_by' => ['required', 'max:100'],
            'cost' => ['numeric'],
            'parts_replaced' => ['max:500'],
            'next_maintenance_date' => ['date'],
            'status' => ['required'],
            'notes' => ['max:1000']
        ]);
        
        if (!$validation['valid']) {
            $errors = $validation['errors'];
        } else {
            $data = $validation['data'];
            
            // Verify equipment exists
            $equipmentStmt = $db->executeQuery("SELECT id, maintenance_interval_days FROM equipment WHERE id = ?", [$data['equipment_id']]);
            $equipment = $equipmentStmt->fetch();
            
            if (!$equipment) {
                $errors['equipment_id'] = 'Invalid equipment selected';
            }
            
            // Validate maintenance date is not in future if status is completed
            if ($data['status'] === 'completed' && strtotime($data['maintenance_date']) > time()) {
                $errors['maintenance_date'] = 'Maintenance date cannot be in the future for completed maintenance';
            }
            
            if (empty($errors)) {
                $db->beginTransaction();
                
                try {
                    // Prepare maintenance data
                    $maintenanceData = [
                        'equipment_id' => $data['equipment_id'],
                        'maintenance_type' => $data['maintenance_type'],
                        'description' => $data['description'],
                        'maintenance_date' => $data['maintenance_date'],
                        'performed_by' => $data['performed_by'],
                        'cost' => !empty($data['cost']) ? $data['cost'] : 0.00,
                        'parts_replaced' => $data['parts_replaced'] ?? '',
                        'next_maintenance_date' => !empty($data['next_maintenance_date']) ? $data['next_maintenance_date'] : null,
                        'status' => $data['status'],
                        'notes' => $data['notes'] ?? '',
                        'created_by' => $_SESSION['user_id']
                    ];
                    
                    // If next maintenance date is not provided and status is completed, calculate it
                    if (empty($maintenanceData['next_maintenance_date']) && $data['status'] === 'completed') {
                        $intervalDays = $equipment['maintenance_interval_days'] ?: 365;
                        $maintenanceData['next_maintenance_date'] = date('Y-m-d', strtotime($data['maintenance_date'] . " + {$intervalDays} days"));
                    }
                    
                    // Insert maintenance record
                    $maintenanceId = insertRecord('equipment_maintenance', $maintenanceData);
                    
                    if (!$maintenanceId) {
                        throw new Exception('Failed to insert maintenance record');
                    }
                    
                    // Update equipment's last maintenance date and next maintenance date if completed
                    if ($data['status'] === 'completed') {
                        $equipmentUpdateData = [
                            'last_maintenance_date' => $data['maintenance_date'],
                            'next_maintenance_date' => $maintenanceData['next_maintenance_date']
                        ];
                        
                        updateRecord('equipment', $equipmentUpdateData, ['id' => $data['equipment_id']]);
                    }
                    
                    $db->commit();
                    
                    // Log activity
                    logActivity('Maintenance record added', 'equipment_maintenance', $maintenanceId, null, $maintenanceData);
                    
                    setFlashMessage('success', 'Maintenance record added successfully!');
                    header('Location: ' . BASE_URL . 'modules/equipment/view.php?id=' . $data['equipment_id']);
                    exit;
                    
                } catch (Exception $e) {
                    $db->rollback();
                    throw $e;
                }
            }
        }
    } catch (Exception $e) {
        error_log("Add maintenance error: " . $e->getMessage());
        $errors['general'] = 'An error occurred while adding maintenance record.';
    }
}

// Page variables
$page_title = 'Add Maintenance Record';
$page_icon = 'fas fa-wrench';

// Breadcrumb
$breadcrumb = [
    ['title' => 'Equipment', 'url' => BASE_URL . 'modules/equipment/'],
    ['title' => 'Maintenance', 'url' => BASE_URL . 'modules/equipment/maintenance.php'],
    ['title' => 'Add Record']
];

include '../../includes/header.php';
?>

<div class="container-fluid px-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <form method="POST" class="needs-validation" novalidate>
                <!-- Equipment Selection Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-tools me-2"></i>Equipment Selection
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($errors['general'])): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo $errors['general']; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="equipment_id" class="form-label">Select Equipment <span class="text-danger">*</span></label>
                                    <select class="form-select <?php echo isset($errors['equipment_id']) ? 'is-invalid' : ''; ?>" 
                                            id="equipment_id" name="equipment_id" required onchange="loadEquipmentDetails()">
                                        <option value="">Choose equipment...</option>
                                        <?php foreach ($equipmentList as $equipment): ?>
                                            <option value="<?php echo $equipment['id']; ?>" 
                                                    data-code="<?php echo htmlspecialchars($equipment['equipment_code']); ?>"
                                                    data-status="<?php echo $equipment['status']; ?>"
                                                    <?php echo ($_POST['equipment_id'] ?? $equipment_id) == $equipment['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($equipment['name'] . ' (' . $equipment['equipment_code'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($errors['equipment_id'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['equipment_id']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Equipment Status</label>
                                    <div id="equipment-status" class="form-control-plaintext">
                                        <?php if ($selectedEquipment): ?>
                                            <span class="badge bg-info"><?php echo EQUIPMENT_STATUS[$selectedEquipment['status']] ?? ucfirst($selectedEquipment['status']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">Select equipment first</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div id="equipment-details" class="<?php echo $selectedEquipment ? '' : 'd-none'; ?>">
                            <div class="row">
                                <div class="col-md-4">
                                    <small class="text-muted">Location:</small><br>
                                    <span id="equipment-location"><?php echo htmlspecialchars($selectedEquipment['location'] ?? 'Not specified'); ?></span>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Last Maintenance:</small><br>
                                    <span id="equipment-last-maintenance">
                                        <?php 
                                        if ($selectedEquipment && !empty($selectedEquipment['last_maintenance_date']) && $selectedEquipment['last_maintenance_date'] !== '0000-00-00') {
                                            echo formatDisplayDate($selectedEquipment['last_maintenance_date']);
                                        } else {
                                            echo 'Never';
                                        }
                                        ?>
                                    </span>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Next Due:</small><br>
                                    <span id="equipment-next-maintenance">
                                        <?php 
                                        if ($selectedEquipment && !empty($selectedEquipment['next_maintenance_date']) && $selectedEquipment['next_maintenance_date'] !== '0000-00-00') {
                                            echo formatDisplayDate($selectedEquipment['next_maintenance_date']);
                                            if (strtotime($selectedEquipment['next_maintenance_date']) < time()) {
                                                echo ' <span class="badge bg-danger ms-1">Overdue</span>';
                                            }
                                        } else {
                                            echo 'Not scheduled';
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Maintenance Details Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-wrench me-2"></i>Maintenance Details
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="maintenance_type" class="form-label">Maintenance Type <span class="text-danger">*</span></label>
                                    <select class="form-select <?php echo isset($errors['maintenance_type']) ? 'is-invalid' : ''; ?>" 
                                            id="maintenance_type" name="maintenance_type" required>
                                        <option value="">Select type...</option>
                                        <?php foreach (MAINTENANCE_TYPES as $key => $label): ?>
                                            <option value="<?php echo $key; ?>" 
                                                    <?php echo ($_POST['maintenance_type'] ?? '') === $key ? 'selected' : ''; ?>>
                                                <?php echo $label; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($errors['maintenance_type'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['maintenance_type']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                    <select class="form-select <?php echo isset($errors['status']) ? 'is-invalid' : ''; ?>" 
                                            id="status" name="status" required onchange="toggleMaintenanceDate()">
                                        <?php foreach (MAINTENANCE_STATUS as $key => $label): ?>
                                            <option value="<?php echo $key; ?>" 
                                                    <?php echo ($_POST['status'] ?? 'completed') === $key ? 'selected' : ''; ?>>
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
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="maintenance_date" class="form-label">Maintenance Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control <?php echo isset($errors['maintenance_date']) ? 'is-invalid' : ''; ?>" 
                                           id="maintenance_date" name="maintenance_date" 
                                           value="<?php echo htmlspecialchars($_POST['maintenance_date'] ?? date('Y-m-d')); ?>" 
                                           required>
                                    <?php if (isset($errors['maintenance_date'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['maintenance_date']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="performed_by" class="form-label">Performed By <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control <?php echo isset($errors['performed_by']) ? 'is-invalid' : ''; ?>" 
                                           id="performed_by" name="performed_by" 
                                           value="<?php echo htmlspecialchars($_POST['performed_by'] ?? ''); ?>" 
                                           placeholder="Name of person who performed maintenance"
                                           required>
                                    <?php if (isset($errors['performed_by'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['performed_by']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea class="form-control <?php echo isset($errors['description']) ? 'is-invalid' : ''; ?>" 
                                      id="description" name="description" rows="3" 
                                      placeholder="Describe what maintenance was performed or needs to be done"
                                      required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                            <?php if (isset($errors['description'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['description']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Additional Details Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>Additional Details
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="cost" class="form-label">Cost (<?php echo CURRENCY_SYMBOL; ?>)</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><?php echo CURRENCY_SYMBOL; ?></span>
                                        <input type="number" class="form-control <?php echo isset($errors['cost']) ? 'is-invalid' : ''; ?>" 
                                               id="cost" name="cost" step="0.01" min="0"
                                               value="<?php echo htmlspecialchars($_POST['cost'] ?? ''); ?>" 
                                               placeholder="0.00">
                                        <?php if (isset($errors['cost'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['cost']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="next_maintenance_date" class="form-label">Next Maintenance Date</label>
                                    <input type="date" class="form-control <?php echo isset($errors['next_maintenance_date']) ? 'is-invalid' : ''; ?>" 
                                           id="next_maintenance_date" name="next_maintenance_date" 
                                           value="<?php echo htmlspecialchars($_POST['next_maintenance_date'] ?? ''); ?>">
                                    <?php if (isset($errors['next_maintenance_date'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['next_maintenance_date']; ?></div>
                                    <?php endif; ?>
                                    <div class="form-text">Leave empty to auto-calculate based on equipment maintenance interval</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="parts_replaced" class="form-label">Parts/Components Replaced</label>
                            <textarea class="form-control <?php echo isset($errors['parts_replaced']) ? 'is-invalid' : ''; ?>" 
                                      id="parts_replaced" name="parts_replaced" rows="2" 
                                      placeholder="List any parts or components that were replaced"><?php echo htmlspecialchars($_POST['parts_replaced'] ?? ''); ?></textarea>
                            <?php if (isset($errors['parts_replaced'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['parts_replaced']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Additional Notes</label>
                            <textarea class="form-control <?php echo isset($errors['notes']) ? 'is-invalid' : ''; ?>" 
                                      id="notes" name="notes" rows="3" 
                                      placeholder="Any additional notes about the maintenance"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                            <?php if (isset($errors['notes'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['notes']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions for Common Maintenance -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-lightning-bolt me-2"></i>Quick Actions
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">Click a button to quickly fill common maintenance scenarios:</p>
                        <div class="row">
                            <div class="col-md-3">
                                <button type="button" class="btn btn-outline-primary w-100 mb-2" onclick="fillCleaningMaintenance()">
                                    <i class="fas fa-broom me-1"></i>Cleaning
                                </button>
                            </div>
                            <div class="col-md-3">
                                <button type="button" class="btn btn-outline-success w-100 mb-2" onclick="fillInspectionMaintenance()">
                                    <i class="fas fa-search me-1"></i>Inspection
                                </button>
                            </div>
                            <div class="col-md-3">
                                <button type="button" class="btn btn-outline-warning w-100 mb-2" onclick="fillRepairMaintenance()">
                                    <i class="fas fa-tools me-1"></i>Repair
                                </button>
                            </div>
                            <div class="col-md-3">
                                <button type="button" class="btn btn-outline-info w-100 mb-2" onclick="fillCalibrationMaintenance()">
                                    <i class="fas fa-cog me-1"></i>Calibration
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <a href="<?php echo BASE_URL; ?>modules/equipment/maintenance.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Maintenance
                            </a>
                            
                            <div>
                                <button type="reset" class="btn btn-outline-secondary me-2" onclick="resetForm()">
                                    <i class="fas fa-undo me-2"></i>Reset Form
                                </button>
                                <button type="submit" class="btn btn-church-primary">
                                    <i class="fas fa-save me-2"></i>Add Maintenance Record
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
// Equipment data for JavaScript
const equipmentData = <?php echo json_encode($equipmentList); ?>;
const equipmentStatuses = <?php echo json_encode(EQUIPMENT_STATUS); ?>;

function loadEquipmentDetails() {
    const select = document.getElementById('equipment_id');
    const selectedId = select.value;
    const detailsDiv = document.getElementById('equipment-details');
    const statusDiv = document.getElementById('equipment-status');
    
    if (selectedId) {
        // Show loading
        ChurchCMS.showLoading('Loading equipment details...');
        
        // Find equipment data
        const equipment = equipmentData.find(e => e.id == selectedId);
        
        if (equipment) {
            // Update status display
            const statusLabel = equipmentStatuses[equipment.status] || equipment.status;
            const statusClass = getStatusClass(equipment.status);
            statusDiv.innerHTML = `<span class="badge bg-${statusClass}">${statusLabel}</span>`;
            
            // Load additional details via AJAX
            fetch(`get_equipment_details.php?id=${selectedId}`)
                .then(response => response.json())
                .then(data => {
                    ChurchCMS.hideLoading();
                    
                    if (data.success) {
                        document.getElementById('equipment-location').textContent = data.equipment.location || 'Not specified';
                        document.getElementById('equipment-last-maintenance').textContent = data.equipment.last_maintenance_formatted || 'Never';
                        document.getElementById('equipment-next-maintenance').innerHTML = data.equipment.next_maintenance_formatted || 'Not scheduled';
                        
                        detailsDiv.classList.remove('d-none');
                        
                        // Auto-fill performed by if responsible person exists
                        if (data.equipment.responsible_person && !document.getElementById('performed_by').value) {
                            document.getElementById('performed_by').value = data.equipment.responsible_person;
                        }
                    } else {
                        ChurchCMS.hideLoading();
                        detailsDiv.classList.add('d-none');
                    }
                })
                .catch(error => {
                    ChurchCMS.hideLoading();
                    console.error('Error loading equipment details:', error);
                    detailsDiv.classList.add('d-none');
                });
        } else {
            ChurchCMS.hideLoading();
            detailsDiv.classList.add('d-none');
        }
    } else {
        statusDiv.innerHTML = '<span class="text-muted">Select equipment first</span>';
        detailsDiv.classList.add('d-none');
    }
}

function getStatusClass(status) {
    const statusClasses = {
        'good': 'success',
        'needs_attention': 'warning',
        'damaged': 'danger',
        'operational': 'info',
        'under_repair': 'warning',
        'retired': 'secondary'
    };
    return statusClasses[status] || 'secondary';
}

function toggleMaintenanceDate() {
    const status = document.getElementById('status').value;
    const maintenanceDateInput = document.getElementById('maintenance_date');
    const today = new Date().toISOString().split('T')[0];
    
    if (status === 'scheduled') {
        maintenanceDateInput.min = today;
        if (maintenanceDateInput.value < today) {
            maintenanceDateInput.value = today;
        }
    } else if (status === 'completed') {
        maintenanceDateInput.max = today;
        if (!maintenanceDateInput.value) {
            maintenanceDateInput.value = today;
        }
    } else {
        maintenanceDateInput.removeAttribute('min');
        maintenanceDateInput.removeAttribute('max');
    }
}

// Quick action functions
function fillCleaningMaintenance() {
    if (confirm('This will fill the form with cleaning maintenance details. Continue?')) {
        document.getElementById('maintenance_type').value = 'preventive';
        document.getElementById('description').value = 'Regular cleaning and dusting of equipment to ensure optimal performance';
        document.getElementById('status').value = 'completed';
        document.getElementById('cost').value = '0.00';
    }
}

function fillInspectionMaintenance() {
    if (confirm('This will fill the form with inspection details. Continue?')) {
        document.getElementById('maintenance_type').value = 'inspection';
        document.getElementById('description').value = 'Routine inspection to check equipment condition and identify potential issues';
        document.getElementById('status').value = 'completed';
        document.getElementById('cost').value = '0.00';
    }
}

function fillRepairMaintenance() {
    if (confirm('This will fill the form with repair details. Continue?')) {
        document.getElementById('maintenance_type').value = 'corrective';
        document.getElementById('description').value = 'Repair work to fix identified issues and restore equipment functionality';
        document.getElementById('status').value = 'completed';
    }
}

function fillCalibrationMaintenance() {
    if (confirm('This will fill the form with calibration details. Continue?')) {
        document.getElementById('maintenance_type').value = 'preventive';
        document.getElementById('description').value = 'Equipment calibration to ensure accurate performance and output';
        document.getElementById('status').value = 'completed';
    }
}

function resetForm() {
    if (confirm('This will clear all form data. Continue?')) {
        document.querySelector('form').reset();
        document.getElementById('equipment-details').classList.add('d-none');
        document.getElementById('equipment-status').innerHTML = '<span class="text-muted">Select equipment first</span>';
    }
}

// Auto-calculate next maintenance date
document.getElementById('maintenance_date').addEventListener('change', function() {
    const maintenanceDate = this.value;
    const nextMaintenanceDateInput = document.getElementById('next_maintenance_date');
    
    if (maintenanceDate && !nextMaintenanceDateInput.value) {
        // Default to 365 days from maintenance date
        const date = new Date(maintenanceDate);
        date.setDate(date.getDate() + 365);
        nextMaintenanceDateInput.value = date.toISOString().split('T')[0];
    }
});

// Form validation
document.addEventListener('DOMContentLoaded', function() {
    // Initialize equipment details if equipment is pre-selected
    if (document.getElementById('equipment_id').value) {
        loadEquipmentDetails();
    }
    
    // Set initial maintenance date constraints
    toggleMaintenanceDate();
    
    // Form validation
    const form = document.querySelector('.needs-validation');
    form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        form.classList.add('was-validated');
    });
    
    // Auto-format cost input
    const costInput = document.getElementById('cost');
    costInput.addEventListener('blur', function() {
        if (this.value) {
            this.value = parseFloat(this.value).toFixed(2);
        }
    });
    
    // Auto-suggest common performers
    const performedByInput = document.getElementById('performed_by');
    const commonPerformers = ['Church Staff', 'External Technician', 'Maintenance Team', 'Volunteer'];
    
    // You could implement autocomplete here
    performedByInput.addEventListener('focus', function() {
        // Show suggestions or recent performers
    });
});
</script>

<?php include '../../includes/footer.php'; ?>