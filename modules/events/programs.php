<?php
/**
 * Programs & Recurring Activities
 * Manage church programs, recurring activities and ministries
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

requireLogin();
if (!hasPermission('events')) {
    setFlashMessage('error', 'You do not have permission to access this page');
    redirect(BASE_URL . 'modules/dashboard/');
}

$db = Database::getInstance();

// Handle AJAX requests
if (isAjaxRequest()) {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'delete_program') {
        $id = $_POST['id'] ?? 0;
        $result = deleteRecord('events', ['id' => $id, 'is_recurring' => 1]);
        
        if ($result) {
            logActivity('Deleted program', 'events', $id);
            sendJSONResponse(['success' => true, 'message' => 'Program deleted successfully']);
        } else {
            sendJSONResponse(['success' => false, 'message' => 'Failed to delete program']);
        }
    }
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isAjaxRequest()) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_program') {
        $data = [
            'name' => sanitizeInput($_POST['name']),
            'event_type' => sanitizeInput($_POST['event_type']),
            'description' => sanitizeInput($_POST['description']),
            'event_date' => sanitizeInput($_POST['start_date']),
            'start_time' => sanitizeInput($_POST['start_time']),
            'end_time' => !empty($_POST['end_time']) ? sanitizeInput($_POST['end_time']) : null,
            'location' => sanitizeInput($_POST['location']),
            'department_id' => !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null,
            'is_recurring' => 1,
            'recurrence_pattern' => sanitizeInput($_POST['recurrence_pattern']),
            'status' => 'planned',
            'created_by' => $_SESSION['user_id']
        ];
        
        $result = insertRecord('events', $data);
        
        if ($result) {
            logActivity('Created new program: ' . $data['name'], 'events', $result);
            setFlashMessage('success', 'Program created successfully');
        } else {
            setFlashMessage('error', 'Failed to create program');
        }
        
        redirect($_SERVER['PHP_SELF']);
    }
}

// Get all recurring programs
$stmt = $db->executeQuery("
    SELECT 
        e.*,
        d.name as department_name,
        CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
        COUNT(DISTINCT ar.id) as total_attendance
    FROM events e
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN users u ON e.created_by = u.id
    LEFT JOIN attendance_records ar ON e.id = ar.event_id
    WHERE e.is_recurring = 1
    GROUP BY e.id
    ORDER BY e.name
");
$programs = $stmt->fetchAll();

// Get departments
$departments = getRecords('departments', ['is_active' => 1], 'name');

$page_title = 'Programs & Activities';
$page_icon = 'fas fa-calendar-week';
$breadcrumb = [
    ['title' => 'Events', 'url' => BASE_URL . 'modules/events/'],
    ['title' => 'Programs']
];

include '../../includes/header.php';
?>

<!-- Action Bar -->
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <p class="text-muted mb-0">Manage recurring programs and ministry activities</p>
            </div>
            <button class="btn btn-church-primary" data-bs-toggle="modal" data-bs-target="#addProgramModal">
                <i class="fas fa-plus me-2"></i>Add Program
            </button>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="stats-icon bg-primary bg-opacity-10 text-primary">
                            <i class="fas fa-calendar-week"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="stats-number"><?php echo count($programs); ?></div>
                        <div class="stats-label">Total Programs</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="stats-icon bg-success bg-opacity-10 text-success">
                            <i class="fas fa-sync"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="stats-number"><?php echo count(array_filter($programs, fn($p) => $p['recurrence_pattern'] === 'weekly')); ?></div>
                        <div class="stats-label">Weekly Programs</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="stats-icon bg-info bg-opacity-10 text-info">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="stats-number"><?php echo count(array_filter($programs, fn($p) => $p['recurrence_pattern'] === 'monthly')); ?></div>
                        <div class="stats-label">Monthly Programs</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Programs Grid -->
<div class="row">
    <?php foreach ($programs as $program): ?>
    <div class="col-md-6 col-lg-4 mb-4">
        <div class="card border-0 shadow-sm h-100 hover-lift">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <span class="badge bg-<?php echo $program['recurrence_pattern'] === 'weekly' ? 'success' : 'primary'; ?> mb-2">
                            <?php echo ucfirst($program['recurrence_pattern']); ?>
                        </span>
                        <h5 class="card-title mb-1"><?php echo htmlspecialchars($program['name']); ?></h5>
                        <small class="text-muted">
                            <?php echo EVENT_TYPES[$program['event_type']] ?? ucwords(str_replace('_', ' ', $program['event_type'])); ?>
                        </small>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-light" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#"><i class="fas fa-edit me-2"></i>Edit</a></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-copy me-2"></i>Duplicate</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="#" onclick="deleteProgram(<?php echo $program['id']; ?>)"><i class="fas fa-trash me-2"></i>Delete</a></li>
                        </ul>
                    </div>
                </div>
                
                <?php if ($program['description']): ?>
                <p class="card-text text-muted small">
                    <?php echo htmlspecialchars(truncateText($program['description'], 100)); ?>
                </p>
                <?php endif; ?>
                
                <div class="mb-3">
                    <?php if ($program['department_name']): ?>
                    <div class="mb-2">
                        <i class="fas fa-users text-primary me-2"></i>
                        <small><?php echo htmlspecialchars($program['department_name']); ?></small>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($program['location']): ?>
                    <div class="mb-2">
                        <i class="fas fa-map-marker-alt text-danger me-2"></i>
                        <small><?php echo htmlspecialchars($program['location']); ?></small>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mb-2">
                        <i class="fas fa-clock text-info me-2"></i>
                        <small><?php echo date('h:i A', strtotime($program['start_time'])); ?></small>
                    </div>
                    
                    <?php if ($program['total_attendance'] > 0): ?>
                    <div>
                        <i class="fas fa-users text-success me-2"></i>
                        <small><?php echo $program['total_attendance']; ?> total attendance</small>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="d-grid">
                    <a href="<?php echo BASE_URL; ?>modules/attendance/events.php?program=<?php echo $program['id']; ?>" 
                       class="btn btn-outline-church-primary btn-sm">
                        <i class="fas fa-calendar-plus me-2"></i>Schedule Next Session
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    
    <?php if (empty($programs)): ?>
    <div class="col-12">
        <div class="text-center py-5">
            <i class="fas fa-calendar-week fa-4x text-muted mb-3"></i>
            <h5 class="text-muted">No Programs Found</h5>
            <p class="text-muted">Click "Add Program" to create your first recurring program</p>
            <button class="btn btn-church-primary" data-bs-toggle="modal" data-bs-target="#addProgramModal">
                <i class="fas fa-plus me-2"></i>Add First Program
            </button>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Program Templates -->
<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white">
        <h5 class="mb-0 text-church-blue">
            <i class="fas fa-lightbulb me-2"></i>Suggested Programs
        </h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3">
                <div class="card bg-light border-0">
                    <div class="card-body">
                        <h6><i class="fas fa-pray me-2 text-primary"></i>Prayer Meeting</h6>
                        <small class="text-muted">Weekly prayer service</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-light border-0">
                    <div class="card-body">
                        <h6><i class="fas fa-book-open me-2 text-success"></i>Bible Study</h6>
                        <small class="text-muted">Weekly scripture study</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-light border-0">
                    <div class="card-body">
                        <h6><i class="fas fa-child me-2 text-warning"></i>Sunday School</h6>
                        <small class="text-muted">Weekly children's program</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-light border-0">
                    <div class="card-body">
                        <h6><i class="fas fa-users me-2 text-info"></i>Youth Fellowship</h6>
                        <small class="text-muted">Weekly youth meetings</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Program Modal -->
<div class="modal fade" id="addProgramModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_program">
                <div class="modal-header bg-church-blue text-white">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add New Program</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Program Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" required placeholder="e.g., Weekly Prayer Meeting">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="event_type" required>
                                <?php foreach (EVENT_TYPES as $key => $label): ?>
                                <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="2"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Recurrence <span class="text-danger">*</span></label>
                            <select class="form-select" name="recurrence_pattern" required>
                                <option value="weekly">Weekly</option>
                                <option value="biweekly">Bi-weekly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Start Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="start_date" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Start Time <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" name="start_time" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Time</label>
                            <input type="time" class="form-control" name="end_time">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" class="form-control" name="location" placeholder="e.g., Main Hall">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Department</label>
                        <select class="form-select" name="department_id">
                            <option value="">General (All Church)</option>
                            <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>">
                                <?php echo htmlspecialchars($dept['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        This will create a recurring program. You can schedule individual sessions from this program template.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-church-primary">
                        <i class="fas fa-save me-2"></i>Create Program
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function deleteProgram(id) {
    ChurchCMS.showConfirm(
        'Delete this program? All associated scheduled events will remain.',
        function() {
            $.ajax({
                url: '?action=delete_program',
                method: 'POST',
                data: { id: id },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        ChurchCMS.showToast(response.message, 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        ChurchCMS.showToast(response.message, 'error');
                    }
                }
            });
        }
    );
}
</script>

<?php include '../../includes/footer.php'; ?>