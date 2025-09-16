<?php
/**
 * Scheduled Reports
 * Deliverance Church Management System
 * 
 * Schedule reports for automatic generation and delivery
 */

// Include configuration and functions
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Check authentication and permissions
requireLogin();
if (!hasPermission('reports')) {
    header('Location: ' . BASE_URL . 'modules/dashboard/?error=access_denied');
    exit();
}

// Page configuration
$page_title = 'Scheduled Reports';
$page_icon = 'fas fa-calendar-alt';
$breadcrumb = [
    ['title' => 'Reports', 'url' => 'index.php'],
    ['title' => 'Scheduled Reports']
];

// Initialize database
$db = Database::getInstance();

// Process actions
$action = sanitizeInput($_GET['action'] ?? $_POST['action'] ?? '');
$scheduleId = sanitizeInput($_GET['id'] ?? $_POST['id'] ?? '');

try {
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($action === 'create_schedule') {
            $data = [
                'report_name' => sanitizeInput($_POST['report_name']),
                'report_type' => sanitizeInput($_POST['report_type']),
                'report_config' => json_encode([
                    'filters' => $_POST['filters'] ?? [],
                    'format' => sanitizeInput($_POST['format']),
                    'include_charts' => isset($_POST['include_charts']) ? 1 : 0
                ]),
                'frequency' => sanitizeInput($_POST['frequency']),
                'schedule_time' => sanitizeInput($_POST['schedule_time']),
                'recipients' => json_encode(array_filter(explode(',', sanitizeInput($_POST['recipients'])))),
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'created_by' => $_SESSION['user_id'],
                'next_run' => calculateNextRun(sanitizeInput($_POST['frequency']), sanitizeInput($_POST['schedule_time']))
            ];
            
            if (!empty($scheduleId)) {
                // Update existing schedule
                updateRecord('report_schedules', $data, ['id' => $scheduleId]);
                setFlashMessage('success', 'Schedule updated successfully');
            } else {
                // Create new schedule
                insertRecord('report_schedules', $data);
                setFlashMessage('success', 'Schedule created successfully');
            }
            
            logActivity('Created/Updated report schedule');
        }
        
        if ($action === 'delete_schedule') {
            deleteRecord('report_schedules', ['id' => $scheduleId]);
            setFlashMessage('success', 'Schedule deleted successfully');
            logActivity('Deleted report schedule', 'report_schedules', $scheduleId);
        }
        
        if ($action === 'toggle_status') {
            $currentStatus = getRecord('report_schedules', 'id', $scheduleId)['is_active'];
            $newStatus = $currentStatus ? 0 : 1;
            updateRecord('report_schedules', ['is_active' => $newStatus], ['id' => $scheduleId]);
            setFlashMessage('success', 'Schedule status updated');
        }
    }
    
    // Get scheduled reports
    $scheduledReports = $db->executeQuery("
        SELECT rs.*, u.first_name, u.last_name,
               (SELECT COUNT(*) FROM report_executions re WHERE re.schedule_id = rs.id) as execution_count,
               (SELECT MAX(re.executed_at) FROM report_executions re WHERE re.schedule_id = rs.id) as last_execution
        FROM report_schedules rs
        LEFT JOIN users u ON rs.created_by = u.id
        ORDER BY rs.created_at DESC
    ")->fetchAll();
    
    // Get current schedule for editing
    $currentSchedule = null;
    if (!empty($scheduleId) && $action === 'edit') {
        $currentSchedule = getRecord('report_schedules', 'id', $scheduleId);
        if ($currentSchedule) {
            $currentSchedule['report_config'] = json_decode($currentSchedule['report_config'], true);
            $currentSchedule['recipients'] = json_decode($currentSchedule['recipients'], true);
        }
    }
    
    // Get recent executions
    $recentExecutions = $db->executeQuery("
        SELECT re.*, rs.report_name, rs.report_type
        FROM report_executions re
        JOIN report_schedules rs ON re.schedule_id = rs.id
        ORDER BY re.executed_at DESC
        LIMIT 20
    ")->fetchAll();
    
} catch (Exception $e) {
    error_log("Error in scheduled reports: " . $e->getMessage());
    setFlashMessage('error', 'Error: ' . $e->getMessage());
    $scheduledReports = [];
    $recentExecutions = [];
}

/**
 * Calculate next run time
 */
function calculateNextRun($frequency, $scheduleTime) {
    $now = new DateTime();
    $time = new DateTime($scheduleTime);
    
    switch ($frequency) {
        case 'daily':
            $nextRun = clone $now;
            $nextRun->setTime($time->format('H'), $time->format('i'));
            if ($nextRun <= $now) {
                $nextRun->add(new DateInterval('P1D'));
            }
            return $nextRun->format('Y-m-d H:i:s');
            
        case 'weekly':
            $nextRun = clone $now;
            $nextRun->setTime($time->format('H'), $time->format('i'));
            $nextRun->modify('next monday');
            return $nextRun->format('Y-m-d H:i:s');
            
        case 'monthly':
            $nextRun = clone $now;
            $nextRun->setTime($time->format('H'), $time->format('i'));
            $nextRun->modify('first day of next month');
            return $nextRun->format('Y-m-d H:i:s');
            
        case 'quarterly':
            $nextRun = clone $now;
            $nextRun->setTime($time->format('H'), $time->format('i'));
            $nextRun->add(new DateInterval('P3M'));
            return $nextRun->format('Y-m-d H:i:s');
            
        default:
            return $now->add(new DateInterval('P1D'))->format('Y-m-d H:i:s');
    }
}

include_once '../../includes/header.php';
?>

<!-- Scheduled Reports Content -->
<div class="row">
    <!-- Schedule Form -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-plus me-2"></i>
                    <?php echo !empty($currentSchedule) ? 'Edit Schedule' : 'New Schedule'; ?>
                </h6>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="create_schedule">
                    <?php if (!empty($scheduleId)): ?>
                        <input type="hidden" name="id" value="<?php echo $scheduleId; ?>">
                    <?php endif; ?>
                    
                    <!-- Report Name -->
                    <div class="mb-3">
                        <label class="form-label">Report Name *</label>
                        <input type="text" name="report_name" class="form-control" required
                               value="<?php echo htmlspecialchars($currentSchedule['report_name'] ?? ''); ?>"
                               placeholder="e.g., Monthly Financial Summary">
                    </div>

                    <!-- Report Type -->
                    <div class="mb-3">
                        <label class="form-label">Report Type *</label>
                        <select name="report_type" class="form-select" required onchange="updateReportOptions()">
                            <option value="">Select Report Type</option>
                            <option value="members" <?php echo ($currentSchedule['report_type'] ?? '') === 'members' ? 'selected' : ''; ?>>Member Reports</option>
                            <option value="financial" <?php echo ($currentSchedule['report_type'] ?? '') === 'financial' ? 'selected' : ''; ?>>Financial Reports</option>
                            <option value="attendance" <?php echo ($currentSchedule['report_type'] ?? '') === 'attendance' ? 'selected' : ''; ?>>Attendance Reports</option>
                            <option value="visitors" <?php echo ($currentSchedule['report_type'] ?? '') === 'visitors' ? 'selected' : ''; ?>>Visitor Reports</option>
                            <option value="sms" <?php echo ($currentSchedule['report_type'] ?? '') === 'sms' ? 'selected' : ''; ?>>SMS Reports</option>
                            <option value="events" <?php echo ($currentSchedule['report_type'] ?? '') === 'events' ? 'selected' : ''; ?>>Events Reports</option>
                        </select>
                    </div>

                    <!-- Report Subtype -->
                    <div class="mb-3" id="reportSubtype">
                        <label class="form-label">Report Subtype</label>
                        <select name="filters[subtype]" class="form-select">
                            <option value="">Select subtype...</option>
                        </select>
                    </div>

                    <!-- Frequency -->
                    <div class="mb-3">
                        <label class="form-label">Frequency *</label>
                        <select name="frequency" class="form-select" required>
                            <option value="daily" <?php echo ($currentSchedule['frequency'] ?? '') === 'daily' ? 'selected' : ''; ?>>Daily</option>
                            <option value="weekly" <?php echo ($currentSchedule['frequency'] ?? '') === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                            <option value="monthly" <?php echo ($currentSchedule['frequency'] ?? '') === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                            <option value="quarterly" <?php echo ($currentSchedule['frequency'] ?? '') === 'quarterly' ? 'selected' : ''; ?>>Quarterly</option>
                        </select>
                    </div>

                    <!-- Schedule Time -->
                    <div class="mb-3">
                        <label class="form-label">Schedule Time *</label>
                        <input type="time" name="schedule_time" class="form-control" required
                               value="<?php echo htmlspecialchars($currentSchedule['schedule_time'] ?? '09:00'); ?>">
                        <small class="form-text text-muted">Time when the report should be generated</small>
                    </div>

                    <!-- Format -->
                    <div class="mb-3">
                        <label class="form-label">Output Format</label>
                        <select name="format" class="form-select">
                            <option value="pdf" <?php echo ($currentSchedule['report_config']['format'] ?? '') === 'pdf' ? 'selected' : ''; ?>>PDF</option>
                            <option value="excel" <?php echo ($currentSchedule['report_config']['format'] ?? '') === 'excel' ? 'selected' : ''; ?>>Excel</option>
                            <option value="csv" <?php echo ($currentSchedule['report_config']['format'] ?? '') === 'csv' ? 'selected' : ''; ?>>CSV</option>
                        </select>
                    </div>

                    <!-- Recipients -->
                    <div class="mb-3">
                        <label class="form-label">Email Recipients *</label>
                        <textarea name="recipients" class="form-control" rows="3" required
                                  placeholder="email1@example.com, email2@example.com"><?php echo htmlspecialchars(implode(', ', $currentSchedule['recipients'] ?? [])); ?></textarea>
                        <small class="form-text text-muted">Comma-separated email addresses</small>
                    </div>

                    <!-- Additional Options -->
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="include_charts" class="form-check-input" id="includeCharts"
                                   <?php echo !empty($currentSchedule['report_config']['include_charts']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="includeCharts">
                                Include Charts and Graphics
                            </label>
                        </div>
                    </div>

                    <!-- Active Status -->
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" class="form-check-input" id="isActive"
                                   <?php echo ($currentSchedule['is_active'] ?? 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="isActive">
                                Active (schedule will run automatically)
                            </label>
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-church-primary">
                            <i class="fas fa-save me-2"></i>
                            <?php echo !empty($currentSchedule) ? 'Update Schedule' : 'Create Schedule'; ?>
                        </button>
                        <?php if (!empty($currentSchedule)): ?>
                            <a href="schedule.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-2"></i>Cancel Edit
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scheduled Reports List -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="fas fa-list me-2"></i>
                        Scheduled Reports
                    </h6>
                    <button class="btn btn-sm btn-outline-success" onclick="testScheduler()">
                        <i class="fas fa-play me-2"></i>Test Scheduler
                    </button>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($scheduledReports)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-calendar-plus fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No Scheduled Reports</h5>
                        <p class="text-muted">Create your first scheduled report to get started.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Report Name</th>
                                    <th>Type</th>
                                    <th>Frequency</th>
                                    <th>Next Run</th>
                                    <th>Executions</th>
                                    <th>Last Run</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($scheduledReports as $report): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($report['report_name']); ?></strong>
                                            <br><small class="text-muted">
                                                By <?php echo htmlspecialchars(($report['first_name'] ?? '') . ' ' . ($report['last_name'] ?? '')); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo ucfirst($report['report_type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo ucfirst($report['frequency']); ?></td>
                                        <td>
                                            <small><?php echo formatDisplayDateTime($report['next_run']); ?></small>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-info"><?php echo number_format($report['execution_count']); ?></span>
                                        </td>
                                        <td>
                                            <?php if ($report['last_execution']): ?>
                                                <small><?php echo timeAgo($report['last_execution']); ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">Never</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($report['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="?action=edit&id=<?php echo $report['id']; ?>" class="btn btn-outline-primary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button class="btn btn-outline-success" onclick="runNow(<?php echo $report['id']; ?>)" title="Run Now">
                                                    <i class="fas fa-play"></i>
                                                </button>
                                                <a href="?action=toggle_status&id=<?php echo $report['id']; ?>" 
                                                   class="btn btn-outline-<?php echo $report['is_active'] ? 'warning' : 'success'; ?>" 
                                                   title="<?php echo $report['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                                    <i class="fas fa-<?php echo $report['is_active'] ? 'pause' : 'play'; ?>"></i>
                                                </a>
                                                <a href="?action=delete_schedule&id=<?php echo $report['id']; ?>" 
                                                   class="btn btn-outline-danger confirm-delete" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Executions -->
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-history me-2"></i>
                    Recent Executions
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($recentExecutions)): ?>
                    <div class="text-center py-3">
                        <i class="fas fa-clock fa-2x text-muted mb-2"></i>
                        <p class="text-muted">No executions yet</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Report</th>
                                    <th>Executed</th>
                                    <th>Duration</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentExecutions as $execution): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($execution['report_name']); ?></strong>
                                            <br><small class="text-muted"><?php echo ucfirst($execution['report_type']); ?></small>
                                        </td>
                                        <td><small><?php echo formatDisplayDateTime($execution['executed_at']); ?></small></td>
                                        <td>
                                            <?php if ($execution['execution_time']): ?>
                                                <small><?php echo number_format($execution['execution_time'], 2); ?>s</small>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $statusClass = [
                                                'success' => 'success',
                                                'failed' => 'danger',
                                                'running' => 'info'
                                            ];
                                            $class = $statusClass[$execution['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $class; ?>">
                                                <?php echo ucfirst($execution['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($execution['output_file']): ?>
                                                <a href="<?php echo BASE_URL . $execution['output_file']; ?>" 
                                                   class="btn btn-sm btn-outline-primary" target="_blank">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
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

<!-- JavaScript -->
<script>
const reportSubtypes = {
    members: {
        'directory': 'Member Directory',
        'new': 'New Members',
        'birthdays': 'Birthday Report',
        'departments': 'Department Analysis',
        'inactive': 'Inactive Members'
    },
    financial: {
        'summary': 'Financial Summary',
        'income': 'Income Report',
        'expenses': 'Expense Report',
        'comparison': 'Income vs Expenses',
        'donors': 'Donor Analysis'
    },
    attendance: {
        'summary': 'Attendance Summary',
        'trends': 'Attendance Trends',
        'individual': 'Individual Records',
        'events': 'Event Attendance'
    },
    visitors: {
        'summary': 'Visitor Summary',
        'followup': 'Follow-up Report',
        'conversion': 'Conversion Analysis'
    },
    sms: {
        'summary': 'SMS Summary',
        'detailed': 'Detailed History',
        'costs': 'Cost Analysis',
        'performance': 'Performance Report'
    },
    events: {
        'summary': 'Events Summary',
        'attendance': 'Event Attendance',
        'departments': 'Department Events'
    }
};

function updateReportOptions() {
    const reportType = document.querySelector('select[name="report_type"]').value;
    const subtypeContainer = document.getElementById('reportSubtype');
    const subtypeSelect = subtypeContainer.querySelector('select');
    
    // Clear existing options
    subtypeSelect.innerHTML = '<option value="">Select subtype...</option>';
    
    if (reportType && reportSubtypes[reportType]) {
        Object.entries(reportSubtypes[reportType]).forEach(([key, value]) => {
            const option = document.createElement('option');
            option.value = key;
            option.textContent = value;
            subtypeSelect.appendChild(option);
        });
        subtypeContainer.style.display = 'block';
    } else {
        subtypeContainer.style.display = 'none';
    }
}

function runNow(scheduleId) {
    if (confirm('Run this scheduled report now?')) {
        ChurchCMS.showLoading('Running report...');
        
        fetch('api/run_scheduled_report.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ schedule_id: scheduleId })
        })
        .then(response => response.json())
        .then(data => {
            ChurchCMS.hideLoading();
            if (data.success) {
                ChurchCMS.showToast('Report executed successfully!', 'success');
                setTimeout(() => location.reload(), 2000);
            } else {
                ChurchCMS.showToast('Error running report: ' + (data.message || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            ChurchCMS.hideLoading();
            ChurchCMS.showToast('Network error occurred', 'error');
        });
    }
}

function testScheduler() {
    ChurchCMS.showLoading('Testing scheduler...');
    
    fetch('api/test_scheduler.php', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        ChurchCMS.hideLoading();
        if (data.success) {
            ChurchCMS.showToast('Scheduler is working correctly!', 'success');
        } else {
            ChurchCMS.showToast('Scheduler test failed: ' + (data.message || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        ChurchCMS.hideLoading();
        ChurchCMS.showToast('Network error occurred', 'error');
    });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    updateReportOptions();
});
</script>

<?php include_once '../../includes/footer.php'; ?>