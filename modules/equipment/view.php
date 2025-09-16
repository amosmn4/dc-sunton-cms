<?php
/**
 * Equipment Details Page
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

try {
    $db = Database::getInstance();
    
    // Get equipment details
    $query = "SELECT e.*, ec.name as category_name, 
                     m.first_name as responsible_first_name, 
                     m.last_name as responsible_last_name,
                     m.phone as responsible_phone,
                     u.first_name as created_by_first_name,
                     u.last_name as created_by_last_name,
                     CASE 
                        WHEN e.next_maintenance_date < CURDATE() THEN 'overdue'
                        WHEN e.next_maintenance_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'due_soon'
                        ELSE 'ok'
                     END as maintenance_status,
                     DATEDIFF(CURDATE(), e.purchase_date) as age_days,
                     DATEDIFF(e.warranty_expiry, CURDATE()) as warranty_days_left
              FROM equipment e
              LEFT JOIN equipment_categories ec ON e.category_id = ec.id
              LEFT JOIN members m ON e.responsible_person_id = m.id
              LEFT JOIN users u ON e.created_by = u.id
              WHERE e.id = ?";
    
    $stmt = $db->executeQuery($query, [$equipment_id]);
    $equipment = $stmt->fetch();
    
    if (!$equipment) {
        setFlashMessage('error', 'Equipment not found.');
        header('Location: ' . BASE_URL . 'modules/equipment/');
        exit;
    }
    
    // Get maintenance history
    $maintenanceQuery = "SELECT em.*, u.first_name as performed_by_first_name, u.last_name as performed_by_last_name
                         FROM equipment_maintenance em
                         LEFT JOIN users u ON em.created_by = u.id
                         WHERE em.equipment_id = ?
                         ORDER BY em.maintenance_date DESC
                         LIMIT 10";
    
    $maintenanceStmt = $db->executeQuery($maintenanceQuery, [$equipment_id]);
    $maintenanceHistory = $maintenanceStmt->fetchAll();
    
    // Calculate depreciation (simple straight-line over 5 years)
    $depreciation_value = 0;
    if ($equipment['purchase_price'] && $equipment['age_days']) {
        $depreciation_years = 5;
        $daily_depreciation = ($equipment['purchase_price'] / ($depreciation_years * 365));
        $depreciation_value = max(0, $equipment['purchase_price'] - ($daily_depreciation * $equipment['age_days']));
    }
    
} catch (Exception $e) {
    error_log("Equipment view error: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred while loading equipment details.');
    header('Location: ' . BASE_URL . 'modules/equipment/');
    exit;
}

// Page variables
$page_title = $equipment['name'];
$page_icon = 'fas fa-tools';

// Breadcrumb
$breadcrumb = [
    ['title' => 'Equipment', 'url' => BASE_URL . 'modules/equipment/'],
    ['title' => $equipment['name']]
];

// Page actions
$page_actions = [
    [
        'title' => 'Edit Equipment',
        'url' => BASE_URL . 'modules/equipment/edit.php?id=' . $equipment_id,
        'icon' => 'fas fa-edit',
        'class' => 'church-primary'
    ],
    [
        'title' => 'Add Maintenance',
        'url' => BASE_URL . 'modules/equipment/add_maintenance.php?equipment_id=' . $equipment_id,
        'icon' => 'fas fa-wrench',
        'class' => 'success'
    ],
    [
        'title' => 'Print Details',
        'url' => 'javascript:window.print()',
        'icon' => 'fas fa-print',
        'class' => 'secondary'
    ]
];

include '../../includes/header.php';
?>

<div class="container-fluid px-4">
    <div class="row">
        <!-- Equipment Details -->
        <div class="col-lg-8">
            <!-- Basic Information -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>Equipment Details
                    </h5>
                    <?php
                    $statusClass = [
                        'good' => 'success',
                        'needs_attention' => 'warning',
                        'damaged' => 'danger',
                        'operational' => 'info',
                        'under_repair' => 'warning',
                        'retired' => 'secondary'
                    ];
                    $class = $statusClass[$equipment['status']] ?? 'secondary';
                    ?>
                    <span class="badge bg-<?php echo $class; ?> fs-6">
                        <?php echo EQUIPMENT_STATUS[$equipment['status']] ?? ucfirst($equipment['status']); ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td class="fw-bold text-church-blue">Equipment Code:</td>
                                    <td>
                                        <?php echo htmlspecialchars($equipment['equipment_code']); ?>
                                        <button class="btn btn-sm btn-outline-secondary ms-2" 
                                                onclick="ChurchCMS.copyToClipboard('<?php echo $equipment['equipment_code']; ?>')" 
                                                title="Copy Code">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-bold text-church-blue">Name:</td>
                                    <td><?php echo htmlspecialchars($equipment['name']); ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold text-church-blue">Category:</td>
                                    <td><?php echo htmlspecialchars($equipment['category_name'] ?? 'No Category'); ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold text-church-blue">Brand:</td>
                                    <td><?php echo htmlspecialchars($equipment['brand']) ?: '-'; ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold text-church-blue">Model:</td>
                                    <td><?php echo htmlspecialchars($equipment['model']) ?: '-'; ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold text-church-blue">Serial Number:</td>
                                    <td><?php echo htmlspecialchars($equipment['serial_number']) ?: '-'; ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold text-church-blue">Location:</td>
                                    <td>
                                        <?php if (!empty($equipment['location'])): ?>
                                            <i class="fas fa-map-marker-alt text-church-red me-1"></i>
                                            <?php echo htmlspecialchars($equipment['location']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not specified</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td class="fw-bold text-church-blue">Purchase Date:</td>
                                    <td>
                                        <?php if (!empty($equipment['purchase_date']) && $equipment['purchase_date'] !== '0000-00-00'): ?>
                                            <?php echo formatDisplayDate($equipment['purchase_date']); ?>
                                            <?php if ($equipment['age_days']): ?>
                                                <small class="text-muted">(<?php echo floor($equipment['age_days'] / 365); ?> years old)</small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not specified</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-bold text-church-blue">Purchase Price:</td>
                                    <td>
                                        <?php if (!empty($equipment['purchase_price'])): ?>
                                            <?php echo formatCurrency($equipment['purchase_price']); ?>
                                            <?php if ($depreciation_value > 0): ?>
                                                <br><small class="text-muted">Current Value: <?php echo formatCurrency($depreciation_value); ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not specified</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-bold text-church-blue">Warranty:</td>
                                    <td>
                                        <?php if (!empty($equipment['warranty_expiry']) && $equipment['warranty_expiry'] !== '0000-00-00'): ?>
                                            <?php echo formatDisplayDate($equipment['warranty_expiry']); ?>
                                            <?php if ($equipment['warranty_days_left'] !== null): ?>
                                                <?php if ($equipment['warranty_days_left'] > 0): ?>
                                                    <span class="badge bg-success ms-1"><?php echo $equipment['warranty_days_left']; ?> days left</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger ms-1">Expired</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not specified</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-bold text-church-blue">Supplier:</td>
                                    <td>
                                        <?php if (!empty($equipment['supplier_name'])): ?>
                                            <?php echo htmlspecialchars($equipment['supplier_name']); ?>
                                            <?php if (!empty($equipment['supplier_contact'])): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($equipment['supplier_contact']); ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not specified</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-bold text-church-blue">Responsible Person:</td>
                                    <td>
                                        <?php if (!empty($equipment['responsible_first_name'])): ?>
                                            <div>
                                                <i class="fas fa-user text-church-blue me-1"></i>
                                                <?php echo htmlspecialchars($equipment['responsible_first_name'] . ' ' . $equipment['responsible_last_name']); ?>
                                            </div>
                                            <?php if (!empty($equipment['responsible_phone'])): ?>
                                                <small class="text-muted">
                                                    <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($equipment['responsible_phone']); ?>
                                                </small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not assigned</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <?php if (!empty($equipment['description'])): ?>
                    <div class="mt-3 pt-3 border-top">
                        <h6 class="text-church-blue">Description</h6>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($equipment['description'])); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($equipment['condition_notes'])): ?>
                    <div class="mt-3 pt-3 border-top">
                        <h6 class="text-church-blue">Condition Notes</h6>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($equipment['condition_notes'])); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="card-footer bg-light">
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        Added <?php echo timeAgo($equipment['created_at']); ?>
                        <?php if (!empty($equipment['created_by_first_name'])): ?>
                            by <?php echo htmlspecialchars($equipment['created_by_first_name'] . ' ' . $equipment['created_by_last_name']); ?>
                        <?php endif; ?>
                        
                        <?php if ($equipment['updated_at'] !== $equipment['created_at']): ?>
                            | Last updated <?php echo timeAgo($equipment['updated_at']); ?>
                        <?php endif; ?>
                    </small>
                </div>
            </div>
            
            <!-- Maintenance History -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-history me-2"></i>Maintenance History
                    </h5>
                    <a href="<?php echo BASE_URL; ?>modules/equipment/add_maintenance.php?equipment_id=<?php echo $equipment_id; ?>" 
                       class="btn btn-sm btn-success">
                        <i class="fas fa-plus me-1"></i>Add Maintenance
                    </a>
                </div>
                <div class="card-body">
                    <?php if (!empty($maintenanceHistory)): ?>
                        <div class="timeline">
                            <?php foreach ($maintenanceHistory as $maintenance): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker bg-<?php echo $maintenance['maintenance_type'] === 'emergency' ? 'danger' : ($maintenance['maintenance_type'] === 'preventive' ? 'success' : 'info'); ?>">
                                        <i class="fas fa-<?php echo $maintenance['maintenance_type'] === 'emergency' ? 'exclamation' : 'wrench'; ?>"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1">
                                                    <?php echo MAINTENANCE_TYPES[$maintenance['maintenance_type']] ?? ucfirst($maintenance['maintenance_type']); ?>
                                                    <span class="badge bg-<?php echo $maintenance['status'] === 'completed' ? 'success' : ($maintenance['status'] === 'in_progress' ? 'warning' : 'info'); ?> ms-2">
                                                        <?php echo MAINTENANCE_STATUS[$maintenance['status']] ?? ucfirst($maintenance['status']); ?>
                                                    </span>
                                                </h6>
                                                <p class="mb-2"><?php echo htmlspecialchars($maintenance['description']); ?></p>
                                                
                                                <?php if (!empty($maintenance['parts_replaced'])): ?>
                                                    <p class="mb-2">
                                                        <strong>Parts Replaced:</strong> <?php echo htmlspecialchars($maintenance['parts_replaced']); ?>
                                                    </p>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($maintenance['cost']) && $maintenance['cost'] > 0): ?>
                                                    <p class="mb-2">
                                                        <strong>Cost:</strong> <?php echo formatCurrency($maintenance['cost']); ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo formatDisplayDate($maintenance['maintenance_date']); ?>
                                            </small>
                                        </div>
                                        
                                        <div class="mt-2 text-muted small">
                                            <i class="fas fa-user me-1"></i>
                                            Performed by: <?php echo htmlspecialchars($maintenance['performed_by']) ?: 'Not specified'; ?>
                                            <?php if (!empty($maintenance['performed_by_first_name'])): ?>
                                                | Recorded by: <?php echo htmlspecialchars($maintenance['performed_by_first_name'] . ' ' . $maintenance['performed_by_last_name']); ?>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if (!empty($maintenance['notes'])): ?>
                                            <div class="mt-2 p-2 bg-light rounded">
                                                <small><?php echo nl2br(htmlspecialchars($maintenance['notes'])); ?></small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="text-center mt-3">
                            <a href="<?php echo BASE_URL; ?>modules/equipment/maintenance.php?equipment_id=<?php echo $equipment_id; ?>" 
                               class="btn btn-outline-primary">
                                <i class="fas fa-list me-1"></i>View All Maintenance Records
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <i class="fas fa-wrench fa-2x text-muted mb-3"></i>
                            <h6 class="text-muted">No Maintenance History</h6>
                            <p class="text-muted mb-3">This equipment has no recorded maintenance history.</p>
                            <a href="<?php echo BASE_URL; ?>modules/equipment/add_maintenance.php?equipment_id=<?php echo $equipment_id; ?>" 
                               class="btn btn-success">
                                <i class="fas fa-plus me-1"></i>Add First Maintenance Record
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Maintenance Status -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-calendar-alt me-2"></i>Maintenance Status
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($equipment['next_maintenance_date']) && $equipment['next_maintenance_date'] !== '0000-00-00'): ?>
                        <div class="text-center">
                            <?php
                            $maintenanceClass = [
                                'overdue' => 'danger',
                                'due_soon' => 'warning',
                                'ok' => 'success'
                            ];
                            $class = $maintenanceClass[$equipment['maintenance_status']] ?? 'info';
                            $icon = $equipment['maintenance_status'] === 'overdue' ? 'exclamation-triangle' : 
                                   ($equipment['maintenance_status'] === 'due_soon' ? 'clock' : 'check-circle');
                            ?>
                            <i class="fas fa-<?php echo $icon; ?> fa-3x text-<?php echo $class; ?> mb-3"></i>
                            <h5 class="text-<?php echo $class; ?>">
                                <?php
                                if ($equipment['maintenance_status'] === 'overdue') {
                                    echo 'Maintenance Overdue';
                                } elseif ($equipment['maintenance_status'] === 'due_soon') {
                                    echo 'Maintenance Due Soon';
                                } else {
                                    echo 'Maintenance Up to Date';
                                }
                                ?>
                            </h5>
                            <p class="mb-0">
                                Next maintenance: <strong><?php echo formatDisplayDate($equipment['next_maintenance_date']); ?></strong>
                            </p>
                            
                            <?php
                            $days_until = floor((strtotime($equipment['next_maintenance_date']) - time()) / (60 * 60 * 24));
                            if ($days_until < 0) {
                                echo '<p class="text-danger mb-0"><strong>' . abs($days_until) . ' days overdue</strong></p>';
                            } elseif ($days_until <= 30) {
                                echo '<p class="text-warning mb-0"><strong>' . $days_until . ' days remaining</strong></p>';
                            }
                            ?>
                        </div>
                        
                        <?php if ($equipment['maintenance_status'] !== 'ok'): ?>
                        <div class="mt-3 d-grid">
                            <a href="<?php echo BASE_URL; ?>modules/equipment/add_maintenance.php?equipment_id=<?php echo $equipment_id; ?>" 
                               class="btn btn-<?php echo $class; ?>">
                                <i class="fas fa-wrench me-1"></i>Schedule Maintenance
                            </a>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center">
                            <i class="fas fa-question-circle fa-3x text-muted mb-3"></i>
                            <h6 class="text-muted">No Maintenance Schedule</h6>
                            <p class="text-muted mb-3">This equipment has no scheduled maintenance.</p>
                            <a href="<?php echo BASE_URL; ?>modules/equipment/edit.php?id=<?php echo $equipment_id; ?>" 
                               class="btn btn-outline-primary">
                                <i class="fas fa-edit me-1"></i>Set Schedule
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-bolt me-2"></i>Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="<?php echo BASE_URL; ?>modules/equipment/edit.php?id=<?php echo $equipment_id; ?>" 
                           class="btn btn-church-primary">
                            <i class="fas fa-edit me-2"></i>Edit Equipment
                        </a>
                        
                        <a href="<?php echo BASE_URL; ?>modules/equipment/add_maintenance.php?equipment_id=<?php echo $equipment_id; ?>" 
                           class="btn btn-success">
                            <i class="fas fa-wrench me-2"></i>Add Maintenance
                        </a>
                        
                        <button type="button" class="btn btn-info" onclick="generateQRCode()">
                            <i class="fas fa-qrcode me-2"></i>Generate QR Code
                        </button>
                        
                        <button type="button" class="btn btn-secondary" onclick="window.print()">
                            <i class="fas fa-print me-2"></i>Print Details
                        </button>
                        
                        <?php if ($_SESSION['user_role'] === 'administrator'): ?>
                        <hr>
                        <button type="button" class="btn btn-danger confirm-delete" onclick="deleteEquipment()">
                            <i class="fas fa-trash me-2"></i>Delete Equipment
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Equipment Statistics -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-bar me-2"></i>Statistics
                    </h5>
                </div>
                <div class="card-body">
                    <?php
                    $totalMaintenanceStmt = $db->executeQuery("SELECT COUNT(*) as count, SUM(cost) as total_cost FROM equipment_maintenance WHERE equipment_id = ?", [$equipment_id]);
                    $maintenanceStats = $totalMaintenanceStmt->fetch();
                    
                    $lastMaintenanceStmt = $db->executeQuery("SELECT maintenance_date FROM equipment_maintenance WHERE equipment_id = ? ORDER BY maintenance_date DESC LIMIT 1", [$equipment_id]);
                    $lastMaintenance = $lastMaintenanceStmt->fetch();
                    ?>
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="border-end">
                                <h4 class="text-church-blue"><?php echo $maintenanceStats['count'] ?? 0; ?></h4>
                                <small class="text-muted">Total Maintenance</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <h4 class="text-church-red">
                                <?php echo formatCurrency($maintenanceStats['total_cost'] ?? 0); ?>
                            </h4>
                            <small class="text-muted">Total Cost</small>
                        </div>
                    </div>
                    
                    <?php if ($lastMaintenance): ?>
                    <hr>
                    <div class="text-center">
                        <small class="text-muted">Last Maintenance:</small><br>
                        <strong><?php echo formatDisplayDate($lastMaintenance['maintenance_date']); ?></strong>
                        <small class="text-muted">(<?php echo timeAgo($lastMaintenance['maintenance_date']); ?>)</small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- QR Code Modal -->
<div class="modal fade" id="qrCodeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-qrcode me-2"></i>Equipment QR Code
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <div id="qrcode" class="mb-3"></div>
                <p class="text-muted">
                    Scan this QR code to quickly access equipment details<br>
                    <strong><?php echo htmlspecialchars($equipment['name']); ?></strong>
                </p>
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-primary" onclick="downloadQRCode()">
                        <i class="fas fa-download me-1"></i>Download QR Code
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="printQRCode()">
                        <i class="fas fa-print me-1"></i>Print QR Code
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline-item {
    position: relative;
    padding-bottom: 20px;
}

.timeline-item:not(:last-child):before {
    content: '';
    position: absolute;
    left: -20px;
    top: 30px;
    height: calc(100% - 10px);
    width: 2px;
    background: #dee2e6;
}

.timeline-marker {
    position: absolute;
    left: -28px;
    top: 5px;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 8px;
    color: white;
}

.timeline-content {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

@media print {
    .no-print {
        display: none !important;
    }
    
    .card {
        border: 1px solid #000 !important;
        break-inside: avoid;
    }
    
    .timeline-content {
        border: 1px solid #000 !important;
        box-shadow: none !important;
    }
}
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode/1.5.3/qrcode.min.js"></script>
<script>
function generateQRCode() {
    const qrCodeElement = document.getElementById('qrcode');
    qrCodeElement.innerHTML = '';
    
    const equipmentUrl = window.location.href;
    
    QRCode.toCanvas(qrCodeElement, equipmentUrl, {
        width: 256,
        margin: 2,
        color: {
            dark: '#03045e',
            light: '#ffffff'
        }
    }, function (error) {
        if (error) {
            console.error(error);
            ChurchCMS.showToast('Failed to generate QR code', 'error');
        } else {
            const modal = new bootstrap.Modal(document.getElementById('qrCodeModal'));
            modal.show();
        }
    });
}

function downloadQRCode() {
    const canvas = document.querySelector('#qrcode canvas');
    if (canvas) {
        const link = document.createElement('a');
        link.download = 'equipment_<?php echo $equipment['equipment_code']; ?>_qrcode.png';
        link.href = canvas.toDataURL();
        link.click();
    }
}

function printQRCode() {
    const canvas = document.querySelector('#qrcode canvas');
    if (canvas) {
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
                <head>
                    <title>Equipment QR Code - <?php echo htmlspecialchars($equipment['name']); ?></title>
                    <style>
                        body { 
                            font-family: Arial, sans-serif; 
                            text-align: center; 
                            padding: 20px; 
                        }
                        .qr-container { 
                            border: 2px solid #000; 
                            padding: 20px; 
                            display: inline-block; 
                            margin: 20px;
                        }
                    </style>
                </head>
                <body>
                    <div class="qr-container">
                        <h3><?php echo htmlspecialchars($equipment['name']); ?></h3>
                        <p><strong><?php echo htmlspecialchars($equipment['equipment_code']); ?></strong></p>
                        <img src="${canvas.toDataURL()}" alt="QR Code">
                        <p>Scan to access equipment details</p>
                    </div>
                </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.print();
    }
}

function deleteEquipment() {
    ChurchCMS.showConfirm(
        'Are you sure you want to delete this equipment? This action cannot be undone and will also delete all maintenance records.',
        function() {
            ChurchCMS.showLoading('Deleting equipment...');
            
            fetch('delete.php?id=<?php echo $equipment_id; ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                ChurchCMS.hideLoading();
                
                if (data.success) {
                    ChurchCMS.showToast('Equipment deleted successfully', 'success');
                    setTimeout(() => {
                        window.location.href = '<?php echo BASE_URL; ?>modules/equipment/';
                    }, 1500);
                } else {
                    ChurchCMS.showToast(data.message || 'Failed to delete equipment', 'error');
                }
            })
            .catch(error => {
                ChurchCMS.hideLoading();
                ChurchCMS.showToast('An error occurred while deleting equipment', 'error');
                console.error('Error:', error);
            });
        }
    );
}

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<?php include '../../includes/footer.php'; ?>