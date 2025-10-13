<?php
/**
 * Event Management for Attendance
 * Manage church events and services
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

requireLogin();
if (!hasPermission('attendance')) {
    setFlashMessage('error', 'You do not have permission to access this page');
    redirect(BASE_URL . 'modules/dashboard/');
}

$db = Database::getInstance();

// Handle AJAX requests
if (isAjaxRequest()) {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'get_event') {
        $id = $_GET['id'] ?? 0;
        $event = getRecord('events', 'id', $id);
        sendJSONResponse(['success' => true, 'data' => $event]);
    }
    
    if ($action === 'delete_event') {
        $id = $_POST['id'] ?? 0;
        
        // Check if event has attendance records
        $hasAttendance = getRecordCount('attendance_records', ['event_id' => $id]);
        
        if ($hasAttendance > 0) {
            sendJSONResponse(['success' => false, 'message' => 'Cannot delete event with attendance records. Mark as cancelled instead.']);
        }
        
        $result = deleteRecord('events', ['id' => $id]);
        
        if ($result) {
            logActivity('Deleted event', 'events', $id);
            sendJSONResponse(['success' => true, 'message' => 'Event deleted successfully']);
        } else {
            sendJSONResponse(['success' => false, 'message' => 'Failed to delete event']);
        }
    }
    
    if ($action === 'update_status') {
        $id = $_POST['id'] ?? 0;
        $status = $_POST['status'] ?? '';
        
        $result = updateRecord('events', ['status' => $status], ['id' => $id]);
        
        if ($result) {
            logActivity("Updated event status to: $status", 'events', $id);
            sendJSONResponse(['success' => true, 'message' => 'Status updated successfully']);
        } else {
            sendJSONResponse(['success' => false, 'message' => 'Failed to update status']);
        }
    }
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isAjaxRequest()) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_event') {
        $data = [
            'name' => sanitizeInput($_POST['name']),
            'event_type' => sanitizeInput($_POST['event_type']),
            'description' => sanitizeInput($_POST['description']),
            'event_date' => sanitizeInput($_POST['event_date']),
            'start_time' => sanitizeInput($_POST['start_time']),
            'end_time' => !empty($_POST['end_time']) ? sanitizeInput($_POST['end_time']) : null,
            'location' => sanitizeInput($_POST['location']),
            'expected_attendance' => !empty($_POST['expected_attendance']) ? (int)$_POST['expected_attendance'] : null,
            'department_id' => !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null,
            'is_recurring' => isset($_POST['is_recurring']) ? 1 : 0,
            'recurrence_pattern' => sanitizeInput($_POST['recurrence_pattern'] ?? ''),
            'status' => 'planned',
            'created_by' => $_SESSION['user_id']
        ];
        
        $result = insertRecord('events', $data);
        
        if ($result) {
            logActivity('Created new event: ' . $data['name'], 'events', $result);
            setFlashMessage('success', 'Event created successfully');
        } else {
            setFlashMessage('error', 'Failed to create event');
        }
        
        redirect($_SERVER['PHP_SELF']);
    }
    
    if ($action === 'edit_event') {
        $id = (int)$_POST['event_id'];
        
        $data = [
            'name' => sanitizeInput($_POST['name']),
            'event_type' => sanitizeInput($_POST['event_type']),
            'description' => sanitizeInput($_POST['description']),
            'event_date' => sanitizeInput($_POST['event_date']),
            'start_time' => sanitizeInput($_POST['start_time']),
            'end_time' => !empty($_POST['end_time']) ? sanitizeInput($_POST['end_time']) : null,
            'location' => sanitizeInput($_POST['location']),
            'expected_attendance' => !empty($_POST['expected_attendance']) ? (int)$_POST['expected_attendance'] : null,
            'department_id' => !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null,
            'status' => sanitizeInput($_POST['status'])
        ];
        
        $result = updateRecord('events', $data, ['id' => $id]);
        
        if ($result) {
            logActivity('Updated event: ' . $data['name'], 'events', $id);
            setFlashMessage('success', 'Event updated successfully');
        } else {
            setFlashMessage('error', 'Failed to update event');
        }
        
        redirect($_SERVER['PHP_SELF']);
    }
}

// Get all events
$filter = $_GET['filter'] ?? 'upcoming';
$eventType = $_GET['type'] ?? 'all';

$whereConditions = [];
$params = [];

if ($filter === 'upcoming') {
    $whereConditions[] = "e.event_date >= CURDATE()";
} elseif ($filter === 'past') {
    $whereConditions[] = "e.event_date < CURDATE()";
} elseif ($filter === 'today') {
    $whereConditions[] = "e.event_date = CURDATE()";
} elseif ($filter === 'this_week') {
    $whereConditions[] = "YEARWEEK(e.event_date, 1) = YEARWEEK(CURDATE(), 1)";
} elseif ($filter === 'this_month') {
    $whereConditions[] = "YEAR(e.event_date) = YEAR(CURDATE()) AND MONTH(e.event_date) = MONTH(CURDATE())";
}

if ($eventType !== 'all') {
    $whereConditions[] = "e.event_type = ?";
    $params[] = $eventType;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

$stmt = $db->executeQuery("
    SELECT 
        e.*,
        d.name as department_name,
        CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
        COUNT(DISTINCT ar.id) as attendance_count
    FROM events e
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN users u ON e.created_by = u.id
    LEFT JOIN attendance_records ar ON e.id = ar.event_id
    $whereClause
    GROUP BY e.id
    ORDER BY e.event_date DESC, e.start_time DESC
", $params);
$events = $stmt->fetchAll();

// Get departments for dropdown
$departments = getRecords('departments', ['is_active' => 1], 'name');

// Statistics
$upcomingCount = getRecordCount('events', ['status' => 'planned']);
$todayEvents = $db->executeQuery("SELECT COUNT(*) FROM events WHERE event_date = CURDATE()")->fetchColumn();
$thisWeekEvents = $db->executeQuery("SELECT COUNT(*) FROM events WHERE YEARWEEK(event_date, 1) = YEARWEEK(CURDATE(), 1)")->fetchColumn();

$page_title = 'Event Management';
$page_icon = 'fas fa-calendar-alt';
$breadcrumb = [
    ['title' => 'Attendance', 'url' => BASE_URL . 'modules/attendance/'],
    ['title' => 'Events']
];

include '../../includes/header.php';
?>

<!-- Action Bar -->
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <p class="text-muted mb-0">Manage church events, services, and special programs</p>
            </div>
            <button class="btn btn-church-primary" data-bs-toggle="modal" data-bs-target="#addEventModal">
                <i class="fas fa-plus me-2"></i>Add Event
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
                            <i class="fas fa-calendar-check"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="stats-number"><?php echo $upcomingCount; ?></div>
                        <div class="stats-label">Upcoming Events</div>
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
                            <i class="fas fa-calendar-day"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="stats-number"><?php echo $todayEvents; ?></div>
                        <div class="stats-label">Events Today</div>
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
                            <i class="fas fa-calendar-week"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="stats-number"><?php echo $thisWeekEvents; ?></div>
                        <div class="stats-label">Events This Week</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Bar -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Filter by Date</label>
                        <select class="form-select" id="filterSelect" onchange="applyFilter()">
                            <option value="upcoming" <?php echo $filter === 'upcoming' ? 'selected' : ''; ?>>Upcoming Events</option>
                            <option value="today" <?php echo $filter === 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="this_week" <?php echo $filter === 'this_week' ? 'selected' : ''; ?>>This Week</option>
                            <option value="this_month" <?php echo $filter === 'this_month' ? 'selected' : ''; ?>>This Month</option>
                            <option value="past" <?php echo $filter === 'past' ? 'selected' : ''; ?>>Past Events</option>
                            <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Events</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Filter by Type</label>
                        <select class="form-select" id="typeSelect" onchange="applyFilter()">
                            <option value="all" <?php echo $eventType === 'all' ? 'selected' : ''; ?>>All Types</option>
                            <?php foreach (EVENT_TYPES as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo $eventType === $key ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">&nbsp;</label>
                        <button class="btn btn-outline-secondary w-100" onclick="clearFilters()">
                            <i class="fas fa-times me-2"></i>Clear Filters
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Events Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 text-church-blue">
            <i class="fas fa-list me-2"></i>Events List
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle" id="eventsTable">
                <thead>
                    <tr>
                        <th>Event Name</th>
                        <th>Type</th>
                        <th>Date & Time</th>
                        <th>Location</th>
                        <th>Department</th>
                        <th>Attendance</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($events as $event): ?>
                    <?php
                    $statusColors = [
                        'planned' => 'primary',
                        'ongoing' => 'info',
                        'completed' => 'success',
                        'cancelled' => 'danger'
                    ];
                    $statusColor = $statusColors[$event['status']] ?? 'secondary';
                    
                    $isPast = strtotime($event['event_date']) < strtotime('today');
                    $isToday = $event['event_date'] === date('Y-m-d');
                    ?>
                    <tr class="<?php echo $isToday ? 'table-info' : ''; ?>">
                        <td>
                            <div>
                                <strong><?php echo htmlspecialchars($event['name']); ?></strong>
                                <?php if ($isToday): ?>
                                <span class="badge bg-warning text-dark ms-2">TODAY</span>
                                <?php endif; ?>
                                <?php if ($event['is_recurring']): ?>
                                <br><small class="text-muted"><i class="fas fa-sync-alt"></i> Recurring</small>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-secondary">
                                <?php echo EVENT_TYPES[$event['event_type']] ?? ucwords(str_replace('_', ' ', $event['event_type'])); ?>
                            </span>
                        </td>
                        <td>
                            <div>
                                <i class="fas fa-calendar text-muted"></i> 
                                <?php echo date('M d, Y', strtotime($event['event_date'])); ?>
                                <br>
                                <i class="fas fa-clock text-muted"></i> 
                                <?php echo date('h:i A', strtotime($event['start_time'])); ?>
                                <?php if ($event['end_time']): ?>
                                - <?php echo date('h:i A', strtotime($event['end_time'])); ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <small><?php echo htmlspecialchars($event['location']) ?: '-'; ?></small>
                        </td>
                        <td>
                            <small><?php echo $event['department_name'] ? htmlspecialchars($event['department_name']) : 'General'; ?></small>
                        </td>
                        <td>
                            <?php if ($event['attendance_count'] > 0): ?>
                            <span class="badge bg-success">
                                <?php echo $event['attendance_count']; ?> attended
                            </span>
                            <?php else: ?>
                            <span class="text-muted">No records</span>
                            <?php endif; ?>
                            <?php if ($event['expected_attendance']): ?>
                            <br><small class="text-muted">Expected: <?php echo $event['expected_attendance']; ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="dropdown">
                                <span class="badge bg-<?php echo $statusColor; ?> dropdown-toggle" role="button" data-bs-toggle="dropdown">
                                    <?php echo ucfirst($event['status']); ?>
                                </span>
                                <ul class="dropdown-menu">
                                    <?php foreach (['planned', 'ongoing', 'completed', 'cancelled'] as $status): ?>
                                    <li>
                                        <a class="dropdown-item" href="#" onclick="updateEventStatus(<?php echo $event['id']; ?>, '<?php echo $status; ?>')">
                                            <?php echo ucfirst($status); ?>
                                        </a>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <?php if ($event['status'] !== 'completed'): ?>
                                <a href="<?php echo BASE_URL; ?>modules/attendance/record.php?event_id=<?php echo $event['id']; ?>" 
                                   class="btn btn-outline-success" title="Record Attendance">
                                    <i class="fas fa-check"></i>
                                </a>
                                <?php else: ?>
                                <a href="<?php echo BASE_URL; ?>modules/attendance/view.php?event_id=<?php echo $event['id']; ?>" 
                                   class="btn btn-outline-info" title="View Attendance">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php endif; ?>
                                <button class="btn btn-outline-warning" onclick="editEvent(<?php echo $event['id']; ?>)" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-outline-danger" onclick="deleteEvent(<?php echo $event['id']; ?>, '<?php echo htmlspecialchars($event['name']); ?>')" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Event Modal -->
<div class="modal fade" id="addEventModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_event">
                <div class="modal-header bg-church-blue text-white">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add New Event</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Event Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" required placeholder="e.g., Sunday Morning Service">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Event Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="event_type" required>
                                <option value="">Select Type</option>
                                <?php foreach (EVENT_TYPES as $key => $label): ?>
                                <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="2" placeholder="Event details and information"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="event_date" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Start Time <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" name="start_time" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">End Time</label>
                            <input type="time" class="form-control" name="end_time">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" class="form-control" name="location" placeholder="e.g., Main Hall">
                        </div>
                        <div class="col-md-6 mb-3">
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
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Expected Attendance</label>
                            <input type="number" class="form-control" name="expected_attendance" min="0" placeholder="Optional">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Recurrence</label>
                            <select class="form-select" name="recurrence_pattern">
                                <option value="">One-time Event</option>
                                <option value="weekly">Weekly</option>
                                <option value="biweekly">Bi-weekly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_recurring" id="is_recurring">
                        <label class="form-check-label" for="is_recurring">
                            This is a recurring event
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-church-primary">
                        <i class="fas fa-save me-2"></i>Create Event
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Event Modal -->
<div class="modal fade" id="editEventModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit_event">
                <input type="hidden" name="event_id" id="edit_event_id">
                <div class="modal-header bg-church-blue text-white">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Event</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="editEventContent">
                    <!-- Content loaded via AJAX -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-church-primary">
                        <i class="fas fa-save me-2"></i>Update Event
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Initialize DataTable
$(document).ready(function() {
    $('#eventsTable').DataTable({
        order: [[2, 'desc']],
        pageLength: 25,
        responsive: true
    });
});

function applyFilter() {
    const filter = document.getElementById('filterSelect').value;
    const type = document.getElementById('typeSelect').value;
    window.location.href = `?filter=${filter}&type=${type}`;
}

function clearFilters() {
    window.location.href = window.location.pathname;
}

function editEvent(id) {
    $.ajax({
        url: '?action=get_event&id=' + id,
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const event = response.data;
                $('#edit_event_id').val(event.id);
                
                const content = `
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Event Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" value="${event.name}" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Event Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="event_type" required>
                                <?php foreach (EVENT_TYPES as $key => $label): ?>
                                <option value="<?php echo $key; ?>" ${event.event_type === '<?php echo $key; ?>' ? 'selected' : ''}><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="2">${event.description || ''}</textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="event_date" value="${event.event_date}" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Start Time <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" name="start_time" value="${event.start_time}" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">End Time</label>
                            <input type="time" class="form-control" name="end_time" value="${event.end_time || ''}">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" class="form-control" name="location" value="${event.location || ''}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Expected Attendance</label>
                            <input type="number" class="form-control" name="expected_attendance" value="${event.expected_attendance || ''}" min="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="planned" ${event.status === 'planned' ? 'selected' : ''}>Planned</option>
                            <option value="ongoing" ${event.status === 'ongoing' ? 'selected' : ''}>Ongoing</option>
                            <option value="completed" ${event.status === 'completed' ? 'selected' : ''}>Completed</option>
                            <option value="cancelled" ${event.status === 'cancelled' ? 'selected' : ''}>Cancelled</option>
                        </select>
                    </div>
                `;
                
                $('#editEventContent').html(content);
                $('#editEventModal').modal('show');
            }
        },
        error: function() {
            ChurchCMS.showToast('Failed to load event details', 'error');
        }
    });
}

function deleteEvent(id, name) {
    ChurchCMS.showConfirm(
        `Are you sure you want to delete the event "${name}"?`,
        function() {
            $.ajax({
                url: '?action=delete_event',
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
                },
                error: function() {
                    ChurchCMS.showToast('Failed to delete event', 'error');
                }
            });
        }
    );
}

function updateEventStatus(id, status) {
    $.ajax({
        url: '?action=update_status',
        method: 'POST',
        data: { id: id, status: status },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                ChurchCMS.showToast(response.message, 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                ChurchCMS.showToast(response.message, 'error');
            }
        },
        error: function() {
            ChurchCMS.showToast('Failed to update status', 'error');
        }
    });
}
</script>

<?php include '../../includes/footer.php'; ?>