<?php
/**
 * Edit Event Page
 * Deliverance Church Management System
 * 
 * Form to edit existing events
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Check authentication and permissions
requireLogin();
if (!hasPermission('attendance')) {
    header('Location: ' . BASE_URL . 'modules/dashboard/?error=no_permission');
    exit();
}

// Get event ID
$eventId = (int) ($_GET['id'] ?? 0);
if (!$eventId) {
    setFlashMessage('error', 'Event not found');
    header('Location: ' . BASE_URL . 'modules/attendance/events.php');
    exit();
}

$db = Database::getInstance();

// Get event details
$event = getRecord('events', 'id', $eventId);
if (!$event) {
    setFlashMessage('error', 'Event not found');
    header('Location: ' . BASE_URL . 'modules/attendance/events.php');
    exit();
}

// Check permissions to edit this event
if (!hasPermission('admin') && $_SESSION['user_id'] != $event['created_by'] && $_SESSION['user_role'] !== 'administrator') {
    setFlashMessage('error', 'You do not have permission to edit this event');
    header('Location: ' . BASE_URL . 'modules/attendance/view_event.php?id=' . $eventId);
    exit();
}

// Check if event has attendance records
$hasAttendance = getRecordCount('attendance_records', ['event_id' => $eventId]) > 0 ||
                getRecordCount('attendance_counts', ['event_id' => $eventId]) > 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $rules = [
            'name' => ['required', 'max:100'],
            'event_type' => ['required'],
            'event_date' => ['required', 'date'],
            'start_time' => ['required'],
            'location' => ['max:100'],
            'description' => ['max:500'],
            'expected_attendance' => ['numeric'],
            'department_id' => ['numeric']
        ];
        
        $validation = validateInput($_POST, $rules);
        
        if (!$validation['valid']) {
            throw new Exception('Validation failed: ' . implode(', ', $validation['errors']));
        }
        
        $data = $validation['data'];
        
        // Additional validations
        if (!array_key_exists($data['event_type'], EVENT_TYPES)) {
            throw new Exception('Invalid event type selected');
        }
        
        // Check for duplicate events (excluding current event)
        $existing = $db->executeQuery("
            SELECT id FROM events 
            WHERE name = ? AND event_date = ? AND start_time = ? AND id != ?
        ", [$data['name'], $data['event_date'], $data['start_time'], $eventId])->fetch();
        
        if ($existing) {
            throw new Exception('An event with the same name, date and time already exists');
        }
        
        // Prepare data for update
        $updateData = [
            'name' => $data['name'],
            'event_type' => $data['event_type'],
            'description' => $data['description'] ?: null,
            'event_date' => $data['event_date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'] ?: null,
            'location' => $data['location'] ?: null,
            'expected_attendance' => $data['expected_attendance'] ?: null,
            'department_id' => $data['department_id'] ?: null
        ];
        
        // Update status based on date if not manually set
        if (!isset($_POST['status'])) {
            if ($data['event_date'] < date('Y-m-d')) {
                $updateData['status'] = $hasAttendance ? 'completed' : 'planned';
            } elseif ($data['event_date'] === date('Y-m-d')) {
                $updateData['status'] = 'ongoing';
            } else {
                $updateData['status'] = 'planned';
            }
        }
        
        // Handle recurring updates
        $updateSeries = !empty($_POST['update_series']) && $_POST['update_series'] === '1';
        
        if ($updateSeries && $event['is_recurring']) {
            // Update all events in the series
            $updated = updateRecord('events', $updateData, [
                'name' => $event['name'],
                'event_type' => $event['event_type'],
                'recurrence_pattern' => $event['recurrence_pattern']
            ]);
            
            if ($updated) {
                logActivity('Updated recurring event series', 'events', $eventId, $event, $updateData);
                setFlashMessage('success', 'Recurring event series updated successfully!');
            } else {
                throw new Exception('Failed to update event series');
            }
        } else {
            // Update only this event
            if (updateRecord('events', $updateData, ['id' => $eventId])) {
                logActivity('Updated event', 'events', $eventId, $event, $updateData);
                setFlashMessage('success', 'Event updated successfully!');
            } else {
                throw new Exception('Failed to update event');
            }
        }
        
        header('Location: ' . BASE_URL . 'modules/attendance/view_event.php?id=' . $eventId);
        exit();
        
    } catch (Exception $e) {
        setFlashMessage('error', $e->getMessage());
    }
}

// Get departments for dropdown
$departments = getRecords('departments', ['is_active' => 1], 'name ASC');

// Page configuration
$page_title = 'Edit Event: ' . htmlspecialchars($event['name']);
$page_icon = 'fas fa-edit';
$breadcrumb = [
    ['title' => 'Attendance Management', 'url' => BASE_URL . 'modules/attendance/'],
    ['title' => 'Events', 'url' => BASE_URL . 'modules/attendance/events.php'],
    ['title' => $event['name'], 'url' => BASE_URL . 'modules/attendance/view_event.php?id=' . $eventId],
    ['title' => 'Edit']
];

include '../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <?php if ($hasAttendance): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Warning:</strong> This event has attendance records. Changing the date or time may affect attendance data integrity.
        </div>
        <?php endif; ?>
        
        <?php if ($event['is_recurring']): ?>
        <div class="alert alert-info">
            <i class="fas fa-repeat me-2"></i>
            <strong>Recurring Event:</strong> You can choose to update only this event or all events in the series.
        </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-edit me-2"></i>Edit Event Details
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <!-- Basic Event Information -->
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="name" class="form-label">Event Name *</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="name" 
                                       name="name" 
                                       value="<?php echo htmlspecialchars($event['name']); ?>"
                                       required>
                                <div class="invalid-feedback">Please enter an event name</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="event_type" class="form-label">Event Type *</label>
                                <select class="form-select" id="event_type" name="event_type" required>
                                    <?php foreach (EVENT_TYPES as $key => $type): ?>
                                    <option value="<?php echo $key; ?>" 
                                            <?php echo ($event['event_type'] === $key) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select an event type</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Date and Time -->
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="event_date" class="form-label">Event Date *</label>
                                <input type="date" 
                                       class="form-control" 
                                       id="event_date" 
                                       name="event_date" 
                                       value="<?php echo $event['event_date']; ?>"
                                       required
                                       <?php echo $hasAttendance ? 'readonly' : ''; ?>>
                                <div class="invalid-feedback">Please select a valid date</div>
                                <?php if ($hasAttendance): ?>
                                <div class="form-text text-warning">
                                    <i class="fas fa-lock me-1"></i>Date locked - event has attendance records
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="start_time" class="form-label">Start Time *</label>
                                <input type="time" 
                                       class="form-control" 
                                       id="start_time" 
                                       name="start_time" 
                                       value="<?php echo $event['start_time']; ?>"
                                       required>
                                <div class="invalid-feedback">Please enter start time</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="end_time" class="form-label">End Time</label>
                                <input type="time" 
                                       class="form-control" 
                                       id="end_time" 
                                       name="end_time" 
                                       value="<?php echo $event['end_time']; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Location and Department -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="location" class="form-label">Location</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="location" 
                                       name="location" 
                                       value="<?php echo htmlspecialchars($event['location'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="department_id" class="form-label">Responsible Department</label>
                                <select class="form-select" id="department_id" name="department_id">
                                    <option value="">All Departments</option>
                                    <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>"
                                            <?php echo ($event['department_id'] == $dept['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Description and Expected Attendance -->
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" 
                                          id="description" 
                                          name="description" 
                                          rows="3"><?php echo htmlspecialchars($event['description'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="expected_attendance" class="form-label">Expected Attendance</label>
                                <input type="number" 
                                       class="form-control" 
                                       id="expected_attendance" 
                                       name="expected_attendance" 
                                       value="<?php echo $event['expected_attendance']; ?>"
                                       min="1" 
                                       max="9999">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Event Status (for admin users) -->
                    <?php if (hasPermission('admin') || $_SESSION['user_role'] === 'administrator'): ?>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="status" class="form-label">Event Status</label>
                                <select class="form-select" id="status" name="status">
                                    <?php foreach (EVENT_STATUS as $key => $status): ?>
                                    <option value="<?php echo $key; ?>" 
                                            <?php echo ($event['status'] === $key) ? 'selected' : ''; ?>>
                                        <?php echo $status; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Recurring Event Options -->
                    <?php if ($event['is_recurring']): ?>
                    <div class="card bg-light border-warning mb-4">
                        <div class="card-header bg-transparent border-0">
                            <h6 class="mb-0 text-warning">
                                <i class="fas fa-repeat me-2"></i>Recurring Event Options
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="update_scope" id="update_single" value="single" checked>
                                <label class="form-check-label" for="update_single">
                                    <strong>Update only this event</strong><br>
                                    <small class="text-muted">Changes will apply only to this specific occurrence</small>
                                </label>
                            </div>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="radio" name="update_scope" id="update_series" value="series">
                                <label class="form-check-label" for="update_series">
                                    <strong>Update entire series</strong><br>
                                    <small class="text-muted">Changes will apply to all events in this recurring series</small>
                                </label>
                            </div>
                            
                            <input type="hidden" name="update_series" id="update_series_hidden" value="0">
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Danger Zone (for events with attendance) -->
                    <?php if ($hasAttendance): ?>
                    <div class="card border-danger mb-4">
                        <div class="card-header bg-danger text-white">
                            <h6 class="mb-0">
                                <i class="fas fa-exclamation-triangle me-2"></i>Danger Zone
                            </h6>
                        </div>
                        <div class="card-body">
                            <p class="text-danger mb-3">
                                <strong>This event has attendance records.</strong> 
                                Changing critical details may affect data integrity.
                            </p>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="confirm_changes" name="confirm_changes" required>
                                <label class="form-check-label" for="confirm_changes">
                                    I understand that changes may affect existing attendance data
                                </label>
                                <div class="invalid-feedback">
                                    You must acknowledge the risks before saving changes
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Form Actions -->
                    <div class="d-flex justify-content-between">
                        <div>
                            <a href="<?php echo BASE_URL; ?>modules/attendance/view_event.php?id=<?php echo $eventId; ?>" 
                               class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Event
                            </a>
                        </div>
                        <div>
                            <button type="button" class="btn btn-warning me-2" onclick="resetForm()">
                                <i class="fas fa-undo me-2"></i>Reset Changes
                            </button>
                            <button type="submit" class="btn btn-church-primary">
                                <i class="fas fa-save me-2"></i>Save Changes
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Event History -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-history me-2"></i>Event History
                </h6>
            </div>
            <div class="card-body">
                <div id="event_history">
                    <div class="text-center py-3">
                        <div class="spinner-border spinner-border-sm text-church-blue" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <span class="ms-2">Loading history...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const updateSeriesRadio = document.getElementById('update_series');
    const updateSeriesHidden = document.getElementById('update_series_hidden');
    
    // Handle recurring event update options
    if (updateSeriesRadio) {
        document.querySelectorAll('input[name="update_scope"]').forEach(radio => {
            radio.addEventListener('change', function() {
                updateSeriesHidden.value = this.value === 'series' ? '1' : '0';
                
                if (this.value === 'series') {
                    ChurchCMS.showToast('Changes will affect all events in this series', 'warning', 3000);
                }
            });
        });
    }
    
    // Form validation
    form.addEventListener('submit', function(e) {
        if (!form.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        form.classList.add('was-validated');
        
        // Additional confirmation for series updates
        const updateSeries = updateSeriesHidden && updateSeriesHidden.value === '1';
        if (updateSeries) {
            e.preventDefault();
            
            ChurchCMS.showConfirm(
                'Are you sure you want to update ALL events in this recurring series? This action will affect multiple events.',
                function() {
                    form.submit();
                }
            );
        }
    });
    
    // Load event history
    loadEventHistory();
    
    // Validate end time
    document.getElementById('end_time').addEventListener('change', function() {
        const startTime = document.getElementById('start_time').value;
        const endTime = this.value;
        
        if (startTime && endTime && endTime <= startTime) {
            this.setCustomValidity('End time must be after start time');
        } else {
            this.setCustomValidity('');
        }
    });
    
    // Track form changes
    let originalFormData = new FormData(form);
    let hasChanges = false;
    
    form.addEventListener('input', function() {
        hasChanges = true;
    });
    
    // Warn before leaving with unsaved changes
    window.addEventListener('beforeunload', function(e) {
        if (hasChanges) {
            e.preventDefault();
            e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            return e.returnValue;
        }
    });
    
    // Reset changes when form is submitted
    form.addEventListener('submit', function() {
        hasChanges = false;
    });
});

function resetForm() {
    if (confirm('Are you sure you want to reset all changes? This will restore the original event details.')) {
        location.reload();
    }
}

function loadEventHistory() {
    fetch(`<?php echo BASE_URL; ?>api/events.php?action=get_history&id=<?php echo $eventId; ?>`)
        .then(response => response.json())
        .then(data => {
            const historyContainer = document.getElementById('event_history');
            
            if (data.success && data.history.length > 0) {
                let historyHtml = '<div class="timeline">';
                
                data.history.forEach(entry => {
                    historyHtml += `
                        <div class="timeline-item mb-3">
                            <div class="d-flex">
                                <div class="flex-shrink-0">
                                    <div class="avatar bg-church-blue text-white rounded-circle d-flex align-items-center justify-content-center" 
                                         style="width: 35px; height: 35px;">
                                        <i class="fas fa-${getActionIcon(entry.action)} fa-sm"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <div class="small">
                                        <strong>${entry.user_name}</strong> ${entry.action}
                                        <span class="text-muted">${ChurchCMS.timeAgo(entry.created_at)}</span>
                                    </div>
                                    ${entry.details ? `<div class="text-muted small">${entry.details}</div>` : ''}
                                </div>
                            </div>
                        </div>
                    `;
                });
                
                historyHtml += '</div>';
                historyContainer.innerHTML = historyHtml;
            } else {
                historyContainer.innerHTML = `
                    <div class="text-center text-muted">
                        <i class="fas fa-history fa-2x mb-2"></i><br>
                        No history available for this event
                    </div>
                `;
            }
        })
        .catch(error => {
            document.getElementById('event_history').innerHTML = `
                <div class="text-center text-danger">
                    <i class="fas fa-exclamation-triangle fa-2x mb-2"></i><br>
                    Error loading event history
                </div>
            `;
        });
}

function getActionIcon(action) {
    const icons = {
        'created': 'plus',
        'updated': 'edit',
        'cancelled': 'times',
        'completed': 'check',
        'attendance_recorded': 'users',
        'deleted': 'trash'
    };
    
    return icons[action] || 'info';
}

function duplicateEvent() {
    ChurchCMS.showConfirm(
        'Create a duplicate of this event? You can modify the details after creation.',
        function() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?php echo BASE_URL; ?>modules/attendance/add_event.php';
            
            // Copy current form values
            const currentForm = document.querySelector('form');
            const formData = new FormData(currentForm);
            
            for (let [key, value] of formData.entries()) {
                if (key !== 'update_series' && key !== 'confirm_changes') {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key;
                    input.value = value;
                    form.appendChild(input);
                }
            }
            
            // Add duplicate flag
            const duplicateFlag = document.createElement('input');
            duplicateFlag.type = 'hidden';
            duplicateFlag.name = 'is_duplicate';
            duplicateFlag.value = '1';
            form.appendChild(duplicateFlag);
            
            document.body.appendChild(form);
            form.submit();
        }
    );
}

function deleteEvent() {
    const hasAttendance = <?php echo $hasAttendance ? 'true' : 'false'; ?>;
    const isRecurring = <?php echo $event['is_recurring'] ? 'true' : 'false'; ?>;
    
    let message = 'Are you sure you want to delete this event?';
    
    if (hasAttendance) {
        message += ' This will also delete all associated attendance records.';
    }
    
    if (isRecurring) {
        message += ' This will only delete this specific occurrence, not the entire series.';
    }
    
    message += ' This action cannot be undone.';
    
    ChurchCMS.showConfirm(
        message,
        function() {
            window.location = `<?php echo BASE_URL; ?>modules/attendance/events.php?action=delete&id=<?php echo $eventId; ?>`;
        }
    );
}

// Auto-save form data
setInterval(function() {
    const formData = new FormData(document.querySelector('form'));
    const data = {};
    
    for (let [key, value] of formData.entries()) {
        data[key] = value;
    }
    
    localStorage.setItem('edit_event_form_<?php echo $eventId; ?>', JSON.stringify(data));
}, 30000);
</script>

<?php include '../../includes/footer.php'; ?>