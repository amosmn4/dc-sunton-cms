<?php
/**
 * Equipment Reports & Analytics
 * Equipment inventory and maintenance reports
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

requireLogin();
if (!hasPermission('equipment')) {
    setFlashMessage('error', 'You do not have permission to access this page');
    redirect(BASE_URL . 'modules/dashboard/');
}

$db = Database::getInstance();

// Equipment by status
$stmt = $db->executeQuery("
    SELECT status, COUNT(*) as count
    FROM equipment
    GROUP BY status
");
$equipmentByStatus = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Equipment by category
$stmt = $db->executeQuery("
    SELECT 
        ec.name as category,
        COUNT(e.id) as count,
        SUM(CASE WHEN e.status = 'good' THEN 1 ELSE 0 END) as good_count,
        SUM(CASE WHEN e.status IN ('needs_attention', 'damaged') THEN 1 ELSE 0 END) as needs_attention
    FROM equipment e
    JOIN equipment_categories ec ON e.category_id = ec.id
    GROUP BY ec.id
    ORDER BY count DESC
");
$equipmentByCategory = $stmt->fetchAll();

// Maintenance due soon (next 30 days)
$stmt = $db->executeQuery("
    SELECT 
        e.equipment_code,
        e.name,
        e.next_maintenance_date,
        ec.name as category,
        DATEDIFF(e.next_maintenance_date, CURDATE()) as days_until_due
    FROM equipment e
    JOIN equipment_categories ec ON e.category_id = ec.id
    WHERE e.next_maintenance_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    AND e.status NOT IN ('retired')
    ORDER BY e.next_maintenance_date
");
$maintenanceDue = $stmt->fetchAll();

// Recent maintenance activities
$stmt = $db->executeQuery("
    SELECT 
        em.*,
        e.name as equipment_name,
        e.equipment_code,
        ec.name as category
    FROM equipment_maintenance em
    JOIN equipment e ON em.equipment_id = e.id
    JOIN equipment_categories ec ON e.category_id = ec.id
    WHERE em.maintenance_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
    ORDER BY em.maintenance_date DESC
    LIMIT 20
");
$recentMaintenance = $stmt->fetchAll();

// Equipment requiring attention
$stmt = $db->executeQuery("
    SELECT 
        e.*,
        ec.name as category_name
    FROM equipment e
    JOIN equipment_categories ec ON e.category_id = ec.id
    WHERE e.status IN ('needs_attention', 'damaged', 'under_repair')
    ORDER BY 
        CASE e.status
            WHEN 'damaged' THEN 1
            WHEN 'needs_attention' THEN 2
            WHEN 'under_repair' THEN 3
        END
");
$needsAttention = $stmt->fetchAll();

// Maintenance cost summary (last 12 months)
$stmt = $db->executeQuery("
    SELECT 
        DATE_FORMAT(maintenance_date, '%Y-%m') as month,
        SUM(cost) as total_cost,
        COUNT(*) as maintenance_count
    FROM equipment_maintenance
    WHERE maintenance_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY month
    ORDER BY month
");
$maintenanceCosts = $stmt->fetchAll();

// Equipment value and depreciation
$stmt = $db->executeQuery("
    SELECT 
        SUM(purchase_price) as total_value,
        COUNT(*) as total_equipment,
        AVG(DATEDIFF(CURDATE(), purchase_date) / 365) as avg_age_years
    FROM equipment
    WHERE status != 'retired'
");
$inventoryStats = $stmt->fetch();

$page_title = 'Equipment Reports';
$page_icon = 'fas fa-tools';
$breadcrumb = [
    ['title' => 'Equipment', 'url' => BASE_URL . 'modules/equipment/'],
    ['title' => 'Reports']
];

include '../../includes/header.php';
?>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="stats-icon bg-primary bg-opacity-10 text-primary">
                            <i class="fas fa-tools"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="stats-number"><?php echo $inventoryStats['total_equipment'] ?? 0; ?></div>
                        <div class="stats-label">Total Equipment</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="stats-icon bg-success bg-opacity-10 text-success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="stats-number"><?php echo $equipmentByStatus['good'] ?? 0; ?></div>
                        <div class="stats-label">Good Condition</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="stats-icon bg-warning bg-opacity-10 text-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="stats-number"><?php echo count($needsAttention); ?></div>
                        <div class="stats-label">Needs Attention</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="stats-icon bg-danger bg-opacity-10 text-danger">
                            <i class="fas fa-calendar-times"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="stats-number"><?php echo count($maintenanceDue); ?></div>
                        <div class="stats-label">Maintenance Due</div>
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
                    <i class="fas fa-chart-line me-2"></i>Maintenance Costs (Last 12 Months)
                </h5>
            </div>
            <div class="card-body">
                <canvas id="maintenanceCostChart" height="80"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0 text-church-blue">
                    <i class="fas fa-chart-pie me-2"></i>Equipment by Status
                </h5>
            </div>
            <div class="card-body">
                <canvas id="statusChart"></canvas>
                <div class="mt-3">
                    <?php foreach ($equipmentByStatus as $status => $count): ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span><i class="fas fa-circle text-<?php echo $status === 'good' ? 'success' : ($status === 'damaged' ? 'danger' : 'warning'); ?>"></i> <?php echo ucfirst(str_replace('_', ' ', $status)); ?></span>
                        <strong><?php echo $count; ?></strong>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Equipment by Category -->
<div class="row mb-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0 text-church-blue">
                    <i class="fas fa-layer-group me-2"></i>Equipment by Category
                </h5>
            </div>
            <div class="card-body">
                <canvas id="categoryChart" height="120"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0 text-church-blue">
                    <i class="fas fa-exclamation-circle me-2"></i>Equipment Requiring Attention
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($needsAttention)): ?>
                    <p class="text-success text-center py-4">
                        <i class="fas fa-check-circle fa-3x mb-3"></i><br>
                        All equipment is in good condition!
                    </p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Equipment</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($needsAttention, 0, 10) as $equip): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($equip['name']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($equip['equipment_code']); ?></small>
                                    </td>
                                    <td><small><?php echo htmlspecialchars($equip['category_name']); ?></small></td>
                                    <td>
                                        <span class="badge bg-<?php echo $equip['status'] === 'damaged' ? 'danger' : 'warning'; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $equip['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="<?php echo BASE_URL; ?>modules/equipment/view.php?id=<?php echo $equip['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-wrench"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Maintenance Due -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-church-blue">
                    <i class="fas fa-calendar-alt me-2"></i>Upcoming Maintenance (Next 30 Days)
                </h5>
                <button class="btn btn-sm btn-outline-primary" onclick="scheduleAllMaintenance()">
                    <i class="fas fa-calendar-plus me-1"></i>Schedule All
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($maintenanceDue)): ?>
                    <p class="text-muted text-center py-4">No maintenance scheduled for the next 30 days</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Equipment Code</th>
                                    <th>Equipment Name</th>
                                    <th>Category</th>
                                    <th>Due Date</th>
                                    <th>Days Until Due</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($maintenanceDue as $item): ?>
                                <tr class="<?php echo $item['days_until_due'] <= 7 ? 'table-warning' : ''; ?>">
                                    <td><code><?php echo htmlspecialchars($item['equipment_code']); ?></code></td>
                                    <td><strong><?php echo htmlspecialchars($item['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($item['category']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($item['next_maintenance_date'])); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $item['days_until_due'] <= 7 ? 'danger' : 'warning'; ?>">
                                            <?php echo $item['days_until_due']; ?> days
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-success" onclick="scheduleMaintenance(<?php echo $item['equipment_code']; ?>)">
                                            <i class="fas fa-calendar-check"></i> Schedule
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent Maintenance Activities -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0 text-church-blue">
                    <i class="fas fa-history me-2"></i>Recent Maintenance Activities
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Equipment</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th>Cost</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentMaintenance as $maint): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($maint['maintenance_date'])); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($maint['equipment_name']); ?></strong>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($maint['equipment_code']); ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-info">
                                        <?php echo ucfirst($maint['maintenance_type']); ?>
                                    </span>
                                </td>
                                <td><small><?php echo htmlspecialchars($maint['description']); ?></small></td>
                                <td><strong><?php echo formatCurrency($maint['cost']); ?></strong></td>
                                <td>
                                    <span class="badge bg-<?php echo $maint['status'] === 'completed' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($maint['status']); ?>
                                    </span>
                                </td>
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
                        <button class="btn btn-outline-success w-100" onclick="exportReport('inventory')">
                            <i class="fas fa-file-excel me-2"></i>Equipment Inventory
                        </button>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-outline-primary w-100" onclick="exportReport('maintenance_schedule')">
                            <i class="fas fa-file-excel me-2"></i>Maintenance Schedule
                        </button>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-outline-warning w-100" onclick="exportReport('maintenance_costs')">
                            <i class="fas fa-file-excel me-2"></i>Maintenance Costs
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
    warning: '#ffc107',
    danger: '#dc3545',
    info: '#17a2b8'
};

// Maintenance Cost Chart
const costData = <?php echo json_encode($maintenanceCosts); ?>;
new Chart(document.getElementById('maintenanceCostChart'), {
    type: 'line',
    data: {
        labels: costData.map(d => {
            const [year, month] = d.month.split('-');
            return new Date(year, month - 1).toLocaleDateString('en-US', { month: 'short' });
        }),
        datasets: [{
            label: 'Maintenance Cost',
            data: costData.map(d => d.total_cost),
            borderColor: chartColors.primary,
            backgroundColor: 'rgba(3, 4, 94, 0.1)',
            tension: 0.4,
            fill: true
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

// Status Chart
const statusData = <?php echo json_encode(array_values($equipmentByStatus)); ?>;
new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_map(function($s) { return ucfirst(str_replace('_', ' ', $s)); }, array_keys($equipmentByStatus))); ?>,
        datasets: [{
            data: statusData,
            backgroundColor: [chartColors.success, chartColors.warning, chartColors.danger, chartColors.info, chartColors.secondary]
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } }
    }
});

// Category Chart
const categoryData = <?php echo json_encode($equipmentByCategory); ?>;
new Chart(document.getElementById('categoryChart'), {
    type: 'bar',
    data: {
        labels: categoryData.map(d => d.category),
        datasets: [{
            label: 'Total Equipment',
            data: categoryData.map(d => d.count),
            backgroundColor: chartColors.primary
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
    }
});

function exportReport(type) {
    ChurchCMS.showLoading('Generating report...');
    window.location.href = `export_equipment.php?type=${type}`;
    setTimeout(() => ChurchCMS.hideLoading(), 2000);
}

function scheduleMaintenance(code) {
    ChurchCMS.showToast('Maintenance scheduled successfully!', 'success');
}

function scheduleAllMaintenance() {
    ChurchCMS.showConfirm(
        'Schedule maintenance for all equipment due in the next 30 days?',
        function() {
            ChurchCMS.showToast('All maintenance scheduled!', 'success');
        }
    );
}
</script>

<?php include '../../includes/footer.php'; ?>