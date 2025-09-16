<?php
/**
 * Edit Event Page
 * Deliverance Church Management System
 * 
 * Form to edit existing events
 */

// Include required files
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Check authentication
requireLogin();

// Check permissions
if (!hasPermission('events') && !in_array($_SESSION['user_role'], ['administrator', 'pastor', 'secretary', 'department_head', 'editor'])) {
    setFlashMessage('error', 'You do not have permission to edit events.');
    redirect(BASE_URL . 'modules/events/');
}

// Get event ID
$event_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$event_id) {
    setFlashMessage('error', 'Invalid event ID.');
    redirect(BASE_URL . 'modules/events/');
}

try {
    $db = Database::getInstance();
    
    // Get event details
    $stmt = $db->executeQuery("
        SELECT e.*, d.name as department_name,
               u.first_name as created_by_name, u.last_name as created_by_lastname
        FROM events e
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN users u ON e.created_by = u.id
        WHERE e.id = ?
    ", [$event_id]);
    
    $event = $stmt->fetch();
    
    if (!$event) {
        setFlashMessage('error', 'Event not found.');
        redirect(BASE_URL . 'modules/events/');
    }
    
    // Get departments for dropdown
    $dept_stmt = $db->executeQuery("
        SELECT id, name, department_type 
        FROM departments 
        WHERE is_active = TRUE 
        ORDER BY name ASC
    ");
    $departments = $dept_stmt->fetchAll();
    
    // Get attendance count for this event
    $attendance_stmt = $db->executeQuery("
        SELECT COUNT(*) as count,
               SUM(CASE WHEN ar.is_present = 1 THEN 1 ELSE 0 END) as present_count
        FROM attendance_records ar 
        WHERE ar.event_id = ?
    ", [$event_id]);
    $attendance_data = $attendance_stmt->fetch();
    
} catch (Exception $e) {
    error_log("Error fetching event: " . $e->getMessage());
    setFlashMessage('error', 'Error loading event data.');
    redirect(BASE_URL . 'modules/events/');
}

// Page variables
$page_title = 'Edit Event: ' . htmlspecialchars($event['name']);
$page_icon = 'fas fa-edit';

// Breadcrumb
$breadcrumb = [
    ['title' => 'Events', 'url' => BASE_URL . 'modules/events/'],
    ['title' => htmlspecialchars($event['name']), 'url' => BASE_URL . 'modules/events/view.php?id=' . $event_id],
    ['title' => 'Edit']
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate input
        $validation_rules = [
            'name' => ['required', 'max:100'],
            'event_type' => ['required'],
            'event_date' => ['required', 'date'],
            'start_time' => ['required'],
            'location' => ['max:100'],
            'description' => ['max:1000'],
            'expected_attendance' => ['numeric'],
            'status' => ['required']
        ];
        
        $validation_result = validateInput($_POST, $validation_rules);
        
        if ($validation_result['valid']) {
            $data = $validation_result['data'];
            
            // Additional validations
            $errors = [];
            
            // Validate time format
            if (!empty($data['end_time']) && $data['end_time'] <= $data['start_time']) {
                $errors[] = 'End time must be after start time';
            }
            
            // Check if event is completed and trying to change date to future
            if ($event['status'] === 'completed' && $data['event_date'] > date('Y-m-d')) {
                $errors[] = 'Cannot change date of completed event to future date';
            }
            
            if (empty($errors)) {
                $db->beginTransaction();
                
                // Store old data for logging
                $old_data = $event;
                
                // Prepare updated data
                $update_data = [
                    'name' => $data['name'],
                    'event_type' => $data['event_type'],
                    'description' => $data['description'] ?: null,
                    'event_date' => $data['event_date'],
                    'start_time' => $data['start_time'],
                    'end_time' => !empty($data['end_time']) ? $data['end_time'] : null,
                    'location' => $data['location'] ?: null,
                    'expected_attendance' => !empty($data['expected_attendance']) ? (int)$data['expected_attendance'] : null,
                    'department_id' => !empty($data['department_id']) ? (int)$data['department_id'] : null,
                    'status' => $data['status'],
                    'notes' => $data['notes'] ?: null,
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                $success = updateRecord('events', $update_data, ['id' => $event_id]);
                
                if ($success) {
                    $db->commit();
                    
                    // Log activity
                    logActivity('Event updated', 'events', $event_id, $old_data, $update_data);
                    
                    setFlashMessage('success', 'Event updated successfully!');
                    redirect(BASE_URL . 'modules/events/view.php?id=' . $event_id);
                } else {
                    $db->rollback();
                    setFlashMessage('error', 'Failed to update event. Please try again.');
                }
            } else {
                setFlashMessage('error', implode('<br>', $errors));
            }
        } else {
            setFlashMessage('error', implode('<br>', $validation_result['errors']));
        }
    } catch (Exception $e) {
        if (isset($db)) $db->rollback();
        error_log("Error updating event: " . $e->getMessage());
        setFlashMessage('error', 'An error occurred while updating the event.');
    }
}

// Include header
include_once '../../includes/header.php';
?>

<!-- Main Content -->
<div class="row justify-content-center">
    <div class="col-lg-8">
        <!-- Event Info Alert -->
        <div class="alert alert-info">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Event ID:</strong> <?php echo $event['id']; ?> | 
                    <strong>Created:</strong> <?php echo formatDisplayDateTime($event['created_at']); ?> |
                    <strong>Created by:</strong> <?php echo htmlspecialchars($event['created_by_name'] . ' ' . $event['created_by_lastname']); ?>
                </div>
                <div>
                    <?php if ($attendance_data['count'] > 0): ?>
                        <span class="badge bg-success">
                            <i class="fas fa-users me-1"></i><?php echo $attendance_data['present_count']; ?> attended
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-edit me-2"></i>Edit Event
                    </h5>
                    <div class="btn-group btn-group-sm">
                        <a href="<?php echo BASE_URL; ?>modules/events/view.php?id=<?php echo $event_id; ?>" 
                           class="btn btn-outline-info">
                            <i class="fas fa-eye me-1"></i>View Event
                        </a>
                        <?php if ($event['status'] !== 'completed'): ?>
                        <a href="<?php echo BASE_URL; ?>modules/attendance/record.php?event_id=<?php echo $event_id; ?>" 
                           class="btn btn-outline-success">
                            <i class="fas fa-calendar-check me-1"></i>Record Attendance
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <div class="row">
                        <!-- Event Name -->
                        <div class="col-md-8 mb-3">
                            <label for="name" class="form-label">Event Name *</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($_POST['name'] ?? $event['name']); ?>" 
                                   required maxlength="100">
                            <div class="invalid-feedback">Please provide an event name.</div>
                        </div>
                        
                        <!-- Event Type -->
                        <div class="col-md-4 mb-3">
                            <label for="event_type" class="form-label">Event Type *</label>
                            <select class="form-select" id="event_type" name="event_type" required>
                                <option value="">Select Type</option>
                                <?php foreach (EVENT_TYPES as $type => $display): ?>
                                    <option value="<?php echo $type; ?>" 
                                            <?php echo (($_POST['event_type'] ?? $event['event_type']) === $type) ? 'selected' : ''; ?>>
                                        <?php echo $display; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select an event type.</div>
                        </div>
                        
                        <!-- Event Date -->
                        <div class="col-md-3 mb-3">
                            <label for="event_date" class="form-label">Event Date *</label>
                            <input type="date" class="form-control" id="event_date" name="event_date" 
                                   value="<?php echo $_POST['event_date'] ?? $event['event_date']; ?>" required>
                            <div class="invalid-feedback">Please select an event date.</div>
                        </div>
                        
                        <!-- Start Time -->
                        <div class="col-md-3 mb-3">
                            <label for="start_time" class="form-label">Start Time *</label>
                            <input type="time" class="form-control" id="start_time" name="start_time" 
                                   value="<?php echo $_POST['start_time'] ?? $event['start_time']; ?>" required>
                            <div class="invalid-feedback">Please select a start time.</div>
                        </div>
                        
                        <!-- End Time -->
                        <div class="col-md-3 mb-3">
                            <label for="end_time" class="form-label">End Time</label>
                            <input type="time" class="form-control" id="end_time" name="end_time" 
                                   value="<?php echo $_POST['end_time'] ?? $event['end_time']; ?>">
                            <small class="form-text text-muted">Optional</small>
                        </div>
                        
                        <!-- Status -->
                        <div class="col-md-3 mb-3">
                            <label for="status" class="form-label">Status *</label>
                            <select class="form-select" id="status" name="status" required>
                                <?php foreach (EVENT_STATUS as $status => $display): ?>
                                    <option value="<?php echo $status; ?>" 
                                            <?php echo (($_POST['status'] ?? $event['status']) === $status) ? 'selected' : ''; ?>>
                                        <?php echo $display; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select a status.</div>
                        </div>
                        
                        <!-- Location -->
                        <div class="col-md-8 mb-3">
                            <label for="location" class="form-label">Location</label>
                            <input type="text" class="form-control" id="location" name="location" 
                                   value="<?php echo htmlspecialchars($_POST['location'] ?? $event['location']); ?>" 
                                   maxlength="100" placeholder="e.g., Main Sanctuary, Fellowship Hall">
                        </div>
                        
                        <!-- Expected Attendance -->
                        <div class="col-md-4 mb-3">
                            <label for="expected_attendance" class="form-label">Expected Attendance</label>
                            <input type="number" class="form-control" id="expected_attendance" name="expected_attendance" 
                                   value="<?php echo $_POST['expected_attendance'] ?? $event['expected_attendance']; ?>" 
                                   min="1" max="10000">
                            <?php if ($attendance_data['present_count'] > 0): ?>
                                <small class="text-success">
                                    <i class="fas fa-check me-1"></i>Actual: <?php echo $attendance_data['present_count']; ?> attendees
                                </small>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Department -->
                        <div class="col-md-6 mb-3">
                            <label for="department_id" class="form-label">Department</label>
                            <select class="form-select" id="department_id" name="department_id">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>" 
                                            <?php echo (($_POST['department_id'] ?? $event['department_id']) == $dept['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['name']); ?> 
                                        <small>(<?php echo ucfirst(str_replace('_', ' ', $dept['department_type'])); ?>)</small>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">Leave empty if event is for all departments</small>
                        </div>
                        
                        <!-- Recurring Info -->
                        <div class="col-md-6 mb-3">
                            <?php if ($event['is_recurring']): ?>
                                <div class="alert alert-warning mb-0">
                                    <i class="fas fa-repeat me-2"></i>
                                    <strong>Recurring Event</strong><br>
                                    <small>Pattern: <?php echo ucfirst($event['recurrence_pattern']); ?></small>
                                </div>
                            <?php else: ?>
                                <div class="form-text mt-4">
                                    <i class="fas fa-info-circle me-1"></i>
                                    This is a single event (non-recurring)
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Description -->
                        <div class="col-12 mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3" 
                                      maxlength="1000" placeholder="Event description, agenda, or other details..."><?php echo htmlspecialchars($_POST['description'] ?? $event['description']); ?></textarea>
                            <div class="form-text">
                                <span id="description_count">0</span> / 1000 characters
                            </div>
                        </div>
                        
                        <!-- Notes -->
                        <div class="col-12 mb-3">
                            <label for="notes" class="form-label">Internal Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2" 
                                      maxlength="500" placeholder="Internal notes for organizers..."><?php echo htmlspecialchars($_POST['notes'] ?? $event['notes']); ?></textarea>
                            <small class="form-text text-muted">These notes are only visible to authorized users</small>
                        </div>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="d-flex justify-content-between">
                        <a href="<?php echo BASE_URL; ?>modules/events/view.php?id=<?php echo $event_id; ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Cancel
                        </a>
                        <div>
                            <button type="submit" class="btn btn-church-primary">
                                <i class="fas fa-save me-1"></i>Update Event
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Event History -->
        <?php if ($event['updated_at']): ?>
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-history me-2"></i>Event History
                </h6>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-marker bg-success"></div>
                        <div class="timeline-content">
                            <h6 class="mb-1">Event Created</h6>
                            <p class="mb-0 text-muted"><?php echo formatDisplayDateTime($event['created_at']); ?></p>
                            <small class="text-muted">by <?php echo htmlspecialchars($event['created_by_name'] . ' ' . $event['created_by_lastname']); ?></small>
                        </div>
                    </div>
                    
                    <?php if ($event['updated_at'] !== $event['created_at']): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker bg-primary"></div>
                        <div class="timeline-content">
                            <h6 class="mb-1">Last Updated</h6>
                            <p class="mb-0 text-muted"><?php echo formatDisplayDateTime($event['updated_at']); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($attendance_data['count'] > 0): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker bg-info"></div>
                        <div class="timeline-content">
                            <h6 class="mb-1">Attendance Recorded</h6>
                            <p class="mb-0"><?php echo $attendance_data['present_count']; ?> out of <?php echo $attendance_data['count']; ?> registered attendees</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Quick Actions -->
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>Quick Actions
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <a href="<?php echo BASE_URL; ?>modules/events/duplicate.php?id=<?php echo $event_id; ?>" 
                           class="btn btn-outline-secondary btn-sm w-100">
                            <i class="fas fa-copy me-1"></i>Duplicate Event
                        </a>
                    </div>
                    <div class="col-md-6 mb-2">
                        <?php if ($event['status'] !== 'completed'): ?>
                        <button type="button" class="btn btn-outline-success btn-sm w-100" onclick="markAsCompleted()">
                            <i class="fas fa-check me-1"></i>Mark as Completed
                        </button>
                        <?php else: ?>
                        <span class="btn btn-success btn-sm w-100 disabled">
                            <i class="fas fa-check me-1"></i>Event Completed
                        </span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($event['status'] !== 'cancelled'): ?>
                    <div class="col-md-6 mb-2">
                        <button type="button" class="btn btn-outline-warning btn-sm w-100" onclick="cancelEvent()">
                            <i class="fas fa-ban me-1"></i>Cancel Event
                        </button>
                    </div>
                    <?php endif; ?>
                    
                    <div class="col-md-6 mb-2">
                        <button type="button" class="btn btn-outline-danger btn-sm w-100" onclick="deleteEvent()">
                            <i class="fas fa-trash me-1"></i>Delete Event
                        </button>
                    </div>
                </div>
            </div>
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
    
    // Character count for description
    const descriptionField = document.getElementById('description');
    const descriptionCount = document.getElementById('description_count');
    
    function updateDescriptionCount() {
        const count = descriptionField.value.length;
        descriptionCount.textContent = count;
        
        if (count > 900) {
            descriptionCount.classList.add('text-warning');
        } else {
            descriptionCount.classList.remove('text-warning');
        }
        
        if (count >= 1000) {
            descriptionCount.classList.add('text-danger');
        } else {
            descriptionCount.classList.remove('text-danger');
        }
    }
    
    descriptionField.addEventListener('input', updateDescriptionCount);
    updateDescriptionCount(); // Initial count
    
    // Validate end time is after start time
    const startTimeField = document.getElementById('start_time');
    const endTimeField = document.getElementById('end_time');
    
    function validateTimes() {
        if (startTimeField.value && endTimeField.value) {
            if (endTimeField.value <= startTimeField.value) {
                endTimeField.setCustomValidity('End time must be after start time');
            } else {
                endTimeField.setCustomValidity('');
            }
        }
    }
    
    startTimeField.addEventListener('change', validateTimes);
    endTimeField.addEventListener('change', validateTimes);
    
    // Status change warnings
    const statusField = document.getElementById('status');
    const originalStatus = '<?php echo $event['status']; ?>';
    
    statusField.addEventListener('change', function() {
        if (originalStatus === 'completed' && this.value !== 'completed') {
            if (!confirm('Are you sure you want to change the status of a completed event?')) {
                this.value = originalStatus;
            }
        } else if (this.value === 'cancelled' && originalStatus !== 'cancelled') {
            if (!confirm('Are you sure you want to cancel this event? This will affect any recorded attendance.')) {
                this.value = originalStatus;
            }
        }
    });
});

function markAsCompleted() {
    if (confirm('Mark this event as completed? This action cannot be easily undone.')) {
        document.getElementById('status').value = 'completed';
        document.querySelector('form').submit();
    }
}

function cancelEvent() {
    const reason = prompt('Please provide a reason for cancelling this event:');
    if (reason !== null && reason.trim() !== '') {
        document.getElementById('status').value = 'cancelled';
        document.getElementById('notes').value = 'Event cancelled: ' + reason.trim();
        document.querySelector('form').submit();
    }
}

function deleteEvent() {
    const eventName = '<?php echo addslashes($event['name']); ?>';
    
    if (confirm(`Are you sure you want to delete the event "${eventName}"? This action cannot be undone and will also delete all related attendance records.`)) {
        if (confirm('This is your final warning. The event and all its data will be permanently deleted. Continue?')) {
            fetch('<?php echo BASE_URL; ?>api/events.php?action=delete_event', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: <?php echo $event_id; ?> })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = '<?php echo BASE_URL; ?>modules/events/';
                } else {
                    alert(data.message || 'Error deleting event');
                }
            })
            .catch(error => {
                alert('Error deleting event');
            });
        }
    }
}
</script>

<style>
.timeline {
    position: relative;
    padding-left: 2rem;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 0.5rem;
    top: 0;
    bottom: 0;
    width: 2px;
    background: var(--church-gray);
}

.timeline-item {
    position: relative;
    margin-bottom: 2rem;
}

.timeline-marker {
    position: absolute;
    left: -2rem;
    top: 0.25rem;
    width: 1rem;
    height: 1rem;
    border-radius: 50%;
    border: 3px solid white;
    box-shadow: 0 0 0 2px var(--church-gray);
}

.timeline-content h6 {
    margin-bottom: 0.25rem;
    color: var(--church-blue);
}
</style>

<?php include_once '../../includes/footer.php'; ?>
