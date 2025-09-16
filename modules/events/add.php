<?php
/**
 * Add New Event Page
 * Deliverance Church Management System
 * 
 * Form to add new events to the system
 */

// Include required files
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Check authentication
requireLogin();

// Check permissions
if (!hasPermission('events') && !in_array($_SESSION['user_role'], ['administrator', 'pastor', 'secretary', 'department_head', 'editor'])) {
    setFlashMessage('error', 'You do not have permission to add events.');
    redirect(BASE_URL . 'modules/events/');
}

// Page variables
$page_title = 'Add New Event';
$page_icon = 'fas fa-plus';
$pre_filled_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Breadcrumb
$breadcrumb = [
    ['title' => 'Events', 'url' => BASE_URL . 'modules/events/'],
    ['title' => 'Add Event']
];

try {
    $db = Database::getInstance();
    
    // Get departments for dropdown
    $dept_stmt = $db->executeQuery("
        SELECT id, name, department_type 
        FROM departments 
        WHERE is_active = TRUE 
        ORDER BY name ASC
    ");
    $departments = $dept_stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Error fetching departments: " . $e->getMessage());
    setFlashMessage('error', 'Error loading form data.');
    $departments = [];
}

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
            'expected_attendance' => ['numeric']
        ];
        
        $validation_result = validateInput($_POST, $validation_rules);
        
        if ($validation_result['valid']) {
            $data = $validation_result['data'];
            
            // Additional validations
            $errors = [];
            
            // Check if event date is in the past (except for today)
            if ($data['event_date'] < date('Y-m-d')) {
                $errors[] = 'Event date cannot be in the past';
            }
            
            // Validate time format
            if (!empty($data['end_time']) && $data['end_time'] <= $data['start_time']) {
                $errors[] = 'End time must be after start time';
            }
            
            if (empty($errors)) {
                $db->beginTransaction();
                
                // Prepare event data
                $event_data = [
                    'name' => $data['name'],
                    'event_type' => $data['event_type'],
                    'description' => $data['description'] ?: null,
                    'event_date' => $data['event_date'],
                    'start_time' => $data['start_time'],
                    'end_time' => !empty($data['end_time']) ? $data['end_time'] : null,
                    'location' => $data['location'] ?: null,
                    'expected_attendance' => !empty($data['expected_attendance']) ? (int)$data['expected_attendance'] : null,
                    'department_id' => !empty($data['department_id']) ? (int)$data['department_id'] : null,
                    'is_recurring' => isset($data['is_recurring']) ? 1 : 0,
                    'recurrence_pattern' => isset($data['is_recurring']) ? ($data['recurrence_pattern'] ?: null) : null,
                    'status' => 'planned',
                    'notes' => $data['notes'] ?: null,
                    'created_by' => $_SESSION['user_id'],
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                $event_id = insertRecord('events', $event_data);
                
                if ($event_id) {
                    // Handle recurring events
                    if (isset($data['is_recurring']) && !empty($data['recurrence_pattern'])) {
                        createRecurringEvents($event_id, $event_data, $data['recurrence_pattern'], $data['recurrence_count'] ?? 4);
                    }
                    
                    $db->commit();
                    
                    // Log activity
                    logActivity('Event created', 'events', $event_id, null, $event_data);
                    
                    setFlashMessage('success', 'Event created successfully!');
                    redirect(BASE_URL . 'modules/events/view.php?id=' . $event_id);
                } else {
                    $db->rollback();
                    setFlashMessage('error', 'Failed to create event. Please try again.');
                }
            } else {
                setFlashMessage('error', implode('<br>', $errors));
            }
        } else {
            setFlashMessage('error', implode('<br>', $validation_result['errors']));
        }
    } catch (Exception $e) {
        if (isset($db)) $db->rollback();
        error_log("Error creating event: " . $e->getMessage());
        setFlashMessage('error', 'An error occurred while creating the event.');
    }
}

/**
 * Create recurring events
 */
function createRecurringEvents($parent_event_id, $event_data, $pattern, $count) {
    global $db;
    
    $base_date = new DateTime($event_data['event_date']);
    
    for ($i = 1; $i < $count; $i++) {
        switch ($pattern) {
            case 'daily':
                $base_date->add(new DateInterval('P1D'));
                break;
            case 'weekly':
                $base_date->add(new DateInterval('P1W'));
                break;
            case 'monthly':
                $base_date->add(new DateInterval('P1M'));
                break;
            case 'yearly':
                $base_date->add(new DateInterval('P1Y'));
                break;
        }
        
        $recurring_data = $event_data;
        $recurring_data['event_date'] = $base_date->format('Y-m-d');
        $recurring_data['name'] = $event_data['name'] . ' (Recurring)';
        unset($recurring_data['created_at']); // Let database set timestamp
        
        insertRecord('events', $recurring_data);
    }
}

// Include header
include_once '../../includes/header.php';
?>

<!-- Main Content -->
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-calendar-plus me-2"></i>Create New Event
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <div class="row">
                        <!-- Event Name -->
                        <div class="col-md-8 mb-3">
                            <label for="name" class="form-label">Event Name *</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" 
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
                                            <?php echo (($_POST['event_type'] ?? '') === $type) ? 'selected' : ''; ?>>
                                        <?php echo $display; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select an event type.</div>
                        </div>
                        
                        <!-- Event Date -->
                        <div class="col-md-4 mb-3">
                            <label for="event_date" class="form-label">Event Date *</label>
                            <input type="date" class="form-control" id="event_date" name="event_date" 
                                   value="<?php echo $_POST['event_date'] ?? $pre_filled_date; ?>" 
                                   min="<?php echo date('Y-m-d'); ?>" required>
                            <div class="invalid-feedback">Please select an event date.</div>
                        </div>
                        
                        <!-- Start Time -->
                        <div class="col-md-4 mb-3">
                            <label for="start_time" class="form-label">Start Time *</label>
                            <input type="time" class="form-control" id="start_time" name="start_time" 
                                   value="<?php echo $_POST['start_time'] ?? '09:00'; ?>" required>
                            <div class="invalid-feedback">Please select a start time.</div>
                        </div>
                        
                        <!-- End Time -->
                        <div class="col-md-4 mb-3">
                            <label for="end_time" class="form-label">End Time</label>
                            <input type="time" class="form-control" id="end_time" name="end_time" 
                                   value="<?php echo $_POST['end_time'] ?? ''; ?>">
                            <small class="form-text text-muted">Optional</small>
                        </div>
                        
                        <!-- Location -->
                        <div class="col-md-8 mb-3">
                            <label for="location" class="form-label">Location</label>
                            <input type="text" class="form-control" id="location" name="location" 
                                   value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>" 
                                   maxlength="100" placeholder="e.g., Main Sanctuary, Fellowship Hall">
                        </div>
                        
                        <!-- Expected Attendance -->
                        <div class="col-md-4 mb-3">
                            <label for="expected_attendance" class="form-label">Expected Attendance</label>
                            <input type="number" class="form-control" id="expected_attendance" name="expected_attendance" 
                                   value="<?php echo $_POST['expected_attendance'] ?? ''; ?>" 
                                   min="1" max="10000">
                        </div>
                        
                        <!-- Department -->
                        <div class="col-md-6 mb-3">
                            <label for="department_id" class="form-label">Department</label>
                            <select class="form-select" id="department_id" name="department_id">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>" 
                                            <?php echo (($_POST['department_id'] ?? '') == $dept['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['name']); ?> 
                                        <small>(<?php echo ucfirst(str_replace('_', ' ', $dept['department_type'])); ?>)</small>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">Leave empty if event is for all departments</small>
                        </div>
                        
                        <!-- Recurring Event -->
                        <div class="col-md-6 mb-3">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="is_recurring" name="is_recurring" 
                                       <?php echo isset($_POST['is_recurring']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_recurring">
                                    <i class="fas fa-repeat me-1"></i>Recurring Event
                                </label>
                            </div>
                        </div>
                        
                        <!-- Recurring Options -->
                        <div id="recurring_options" class="col-12 mb-3" style="display: none;">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title">Recurring Event Settings</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label for="recurrence_pattern" class="form-label">Repeat Pattern</label>
                                            <select class="form-select" id="recurrence_pattern" name="recurrence_pattern">
                                                <option value="weekly" <?php echo (($_POST['recurrence_pattern'] ?? 'weekly') === 'weekly') ? 'selected' : ''; ?>>Weekly</option>
                                                <option value="monthly" <?php echo (($_POST['recurrence_pattern'] ?? '') === 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                                                <option value="yearly" <?php echo (($_POST['recurrence_pattern'] ?? '') === 'yearly') ? 'selected' : ''; ?>>Yearly</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="recurrence_count" class="form-label">Number of Occurrences</label>
                                            <select class="form-select" id="recurrence_count" name="recurrence_count">
                                                <option value="4" <?php echo (($_POST['recurrence_count'] ?? '4') === '4') ? 'selected' : ''; ?>>4 times</option>
                                                <option value="8" <?php echo (($_POST['recurrence_count'] ?? '') === '8') ? 'selected' : ''; ?>>8 times</option>
                                                <option value="12" <?php echo (($_POST['recurrence_count'] ?? '') === '12') ? 'selected' : ''; ?>>12 times</option>
                                                <option value="24" <?php echo (($_POST['recurrence_count'] ?? '') === '24') ? 'selected' : ''; ?>>24 times</option>
                                                <option value="52" <?php echo (($_POST['recurrence_count'] ?? '') === '52') ? 'selected' : ''; ?>>52 times (1 year)</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Description -->
                        <div class="col-12 mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3" 
                                      maxlength="1000" placeholder="Event description, agenda, or other details..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                            <div class="form-text">
                                <span id="description_count">0</span> / 1000 characters
                            </div>
                        </div>
                        
                        <!-- Notes -->
                        <div class="col-12 mb-3">
                            <label for="notes" class="form-label">Internal Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2" 
                                      maxlength="500" placeholder="Internal notes for organizers..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                            <small class="form-text text-muted">These notes are only visible to authorized users</small>
                        </div>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="d-flex justify-content-between">
                        <a href="<?php echo BASE_URL; ?>modules/events/" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Cancel
                        </a>
                        <div>
                            <button type="submit" class="btn btn-church-primary">
                                <i class="fas fa-save me-1"></i>Create Event
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Quick Event Templates -->
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-templates me-2"></i>Quick Event Templates
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-2">
                        <button type="button" class="btn btn-outline-primary btn-sm w-100 event-template" 
                                data-name="Sunday Service" 
                                data-type="sunday_service" 
                                data-time="09:00"
                                data-location="Main Sanctuary"
                                data-description="Weekly Sunday worship service">
                            <i class="fas fa-church me-1"></i>Sunday Service
                        </button>
                    </div>
                    <div class="col-md-3 mb-2">
                        <button type="button" class="btn btn-outline-success btn-sm w-100 event-template" 
                                data-name="Prayer Meeting" 
                                data-type="prayer_meeting" 
                                data-time="18:00"
                                data-location="Prayer Room"
                                data-description="Weekly prayer and intercession meeting">
                            <i class="fas fa-praying-hands me-1"></i>Prayer Meeting
                        </button>
                    </div>
                    <div class="col-md-3 mb-2">
                        <button type="button" class="btn btn-outline-info btn-sm w-100 event-template" 
                                data-name="Bible Study" 
                                data-type="bible_study" 
                                data-time="19:00"
                                data-location="Fellowship Hall"
                                data-description="Weekly Bible study and discussion">
                            <i class="fas fa-book me-1"></i>Bible Study
                        </button>
                    </div>
                    <div class="col-md-3 mb-2">
                        <button type="button" class="btn btn-outline-warning btn-sm w-100 event-template" 
                                data-name="Youth Service" 
                                data-type="youth_service" 
                                data-time="17:00"
                                data-location="Youth Center"
                                data-description="Special service for young people">
                            <i class="fas fa-users me-1"></i>Youth Service
                        </button>
                    </div>
                </div>
                <small class="text-muted">Click a template to quickly fill the form with common event details</small>
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
    
    // Show/hide recurring options
    const isRecurringCheckbox = document.getElementById('is_recurring');
    const recurringOptions = document.getElementById('recurring_options');
    
    function toggleRecurringOptions() {
        if (isRecurringCheckbox.checked) {
            recurringOptions.style.display = 'block';
        } else {
            recurringOptions.style.display = 'none';
        }
    }
    
    isRecurringCheckbox.addEventListener('change', toggleRecurringOptions);
    toggleRecurringOptions(); // Initial state
    
    // Event templates
    document.querySelectorAll('.event-template').forEach(button => {
        button.addEventListener('click', function() {
            const name = this.getAttribute('data-name');
            const type = this.getAttribute('data-type');
            const time = this.getAttribute('data-time');
            const location = this.getAttribute('data-location');
            const description = this.getAttribute('data-description');
            
            document.getElementById('name').value = name;
            document.getElementById('event_type').value = type;
            document.getElementById('start_time').value = time;
            document.getElementById('location').value = location;
            document.getElementById('description').value = description;
            
            updateDescriptionCount();
            
            ChurchCMS.showToast('Template applied successfully!', 'success');
        });
    });
    
    // Auto-set end time when start time changes
    const startTimeField = document.getElementById('start_time');
    const endTimeField = document.getElementById('end_time');
    
    startTimeField.addEventListener('change', function() {
        if (!endTimeField.value && startTimeField.value) {
            const startTime = new Date('1970-01-01T' + startTimeField.value + ':00');
            const endTime = new Date(startTime.getTime() + (2 * 60 * 60 * 1000)); // Add 2 hours
            
            const hours = String(endTime.getHours()).padStart(2, '0');
            const minutes = String(endTime.getMinutes()).padStart(2, '0');
            endTimeField.value = hours + ':' + minutes;
        }
    });
    
    // Department selection based on event type
    const eventTypeField = document.getElementById('event_type');
    const departmentField = document.getElementById('department_id');
    
    eventTypeField.addEventListener('change', function() {
        const eventType = this.value;
        
        // Suggest department based on event type
        if (eventType === 'youth_service') {
            // Find youth department and select it
            Array.from(departmentField.options).forEach(option => {
                if (option.text.toLowerCase().includes('youth')) {
                    option.selected = true;
                }
            });
        } else if (eventType === 'prayer_meeting') {
            // Find prayer team and select it
            Array.from(departmentField.options).forEach(option => {
                if (option.text.toLowerCase().includes('prayer')) {
                    option.selected = true;
                }
            });
        }
    });
    
    // Validate end time is after start time
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
});
</script>

<?php include_once '../../includes/footer.php'; ?>