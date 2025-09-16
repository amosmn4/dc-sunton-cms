<?php
/**
 * Add New Event Page
 * Deliverance Church Management System
 * 
 * Form to create new events for attendance tracking
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Check authentication and permissions
requireLogin();
if (!hasPermission('attendance')) {
    header('Location: ' . BASE_URL . 'modules/dashboard/?error=no_permission');
    exit();
}

// Page configuration
$page_title = 'Add New Event';
$page_icon = 'fas fa-calendar-plus';
$breadcrumb = [
    ['title' => 'Attendance Management', 'url' => BASE_URL . 'modules/attendance/'],
    ['title' => 'Add New Event']
];

$db = Database::getInstance();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validation rules
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
        
        if ($data['event_date'] < date('Y-m-d') && !hasPermission('admin')) {
            throw new Exception('Cannot create events for past dates');
        }
        
        // Check for duplicate events on same date/time
        $existing = $db->executeQuery("
            SELECT id FROM events 
            WHERE name = ? AND event_date = ? AND start_time = ?
        ", [$data['name'], $data['event_date'], $data['start_time']])->fetch();
        
        if ($existing) {
            throw new Exception('An event with the same name, date and time already exists');
        }
        
        // Prepare data for insertion
        $eventData = [
            'name' => $data['name'],
            'event_type' => $data['event_type'],
            'description' => $data['description'] ?: null,
            'event_date' => $data['event_date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'] ?: null,
            'location' => $data['location'] ?: null,
            'expected_attendance' => $data['expected_attendance'] ?: null,
            'department_id' => $data['department_id'] ?: null,
            'created_by' => $_SESSION['user_id'],
            'status' => $data['event_date'] < date('Y-m-d') ? 'completed' : 'planned'
        ];
        
        // Handle recurring events
        if (!empty($_POST['is_recurring']) && $_POST['is_recurring'] === '1') {
            $eventData['is_recurring'] = 1;
            $eventData['recurrence_pattern'] = $_POST['recurrence_pattern'] ?: null;
        }
        
        $eventId = insertRecord('events', $eventData);
        
        if ($eventId) {
            logActivity('Created new event', 'events', $eventId, null, $eventData);
            
            // Handle recurring event creation
            if (!empty($_POST['is_recurring']) && $_POST['is_recurring'] === '1') {
                createRecurringEvents($eventId, $data, $_POST['recurrence_pattern'], $_POST['recurrence_end_date']);
            }
            
            setFlashMessage('success', 'Event created successfully!');
            
            // Redirect based on user choice
            if (isset($_POST['redirect_to']) && $_POST['redirect_to'] === 'record_attendance') {
                header('Location: ' . BASE_URL . 'modules/attendance/record.php?event_id=' . $eventId);
            } else {
                header('Location: ' . BASE_URL . 'modules/attendance/events.php');
            }
            exit();
        } else {
            throw new Exception('Failed to create event');
        }
        
    } catch (Exception $e) {
        setFlashMessage('error', $e->getMessage());
    }
}

// Get departments for dropdown
$departments = getRecords('departments', ['is_active' => 1], 'name ASC');

// Form data for pre-filling
$formData = $_POST ?? [];

include '../../includes/header.php';
?>

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
                    <!-- Basic Event Information -->
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="name" class="form-label">Event Name *</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="name" 
                                       name="name" 
                                       value="<?php echo htmlspecialchars($formData['name'] ?? ''); ?>"
                                       placeholder="e.g., Sunday Morning Service"
                                       required>
                                <div class="invalid-feedback">Please enter an event name</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="event_type" class="form-label">Event Type *</label>
                                <select class="form-select" id="event_type" name="event_type" required>
                                    <option value="">Select Type...</option>
                                    <?php foreach (EVENT_TYPES as $key => $type): ?>
                                    <option value="<?php echo $key; ?>" 
                                            <?php echo (($formData['event_type'] ?? '') === $key) ? 'selected' : ''; ?>>
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
                                       value="<?php echo $formData['event_date'] ?? date('Y-m-d'); ?>"
                                       min="<?php echo date('Y-m-d', strtotime('-30 days')); ?>"
                                       required>
                                <div class="invalid-feedback">Please select a valid date</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="start_time" class="form-label">Start Time *</label>
                                <input type="time" 
                                       class="form-control" 
                                       id="start_time" 
                                       name="start_time" 
                                       value="<?php echo $formData['start_time'] ?? '09:00'; ?>"
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
                                       value="<?php echo $formData['end_time'] ?? ''; ?>">
                                <div class="form-text">Optional - for planning purposes</div>
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
                                       value="<?php echo htmlspecialchars($formData['location'] ?? ''); ?>"
                                       placeholder="e.g., Main Sanctuary, Youth Hall">
                                <div class="form-text">Where the event will take place</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="department_id" class="form-label">Responsible Department</label>
                                <select class="form-select" id="department_id" name="department_id">
                                    <option value="">All Departments</option>
                                    <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>"
                                            <?php echo (($formData['department_id'] ?? '') == $dept['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Department organizing this event</div>
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
                                          rows="3"
                                          placeholder="Brief description of the event..."><?php echo htmlspecialchars($formData['description'] ?? ''); ?></textarea>
                                <div class="form-text">Optional event description</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="expected_attendance" class="form-label">Expected Attendance</label>
                                <input type="number" 
                                       class="form-control" 
                                       id="expected_attendance" 
                                       name="expected_attendance" 
                                       value="<?php echo $formData['expected_attendance'] ?? ''; ?>"
                                       min="1" 
                                       max="9999"
                                       placeholder="100">
                                <div class="form-text">Estimated number of attendees</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recurring Event Options -->
                    <div class="card bg-light border-0 mb-4">
                        <div class="card-header bg-transparent border-0 pb-0">
                            <div class="form-check">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       id="is_recurring" 
                                       name="is_recurring" 
                                       value="1"
                                       <?php echo (!empty($formData['is_recurring'])) ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-bold" for="is_recurring">
                                    <i class="fas fa-repeat me-2"></i>Make this a recurring event
                                </label>
                            </div>
                        </div>
                        <div class="card-body pt-2" id="recurring_options" style="display: none;">
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="recurrence_pattern" class="form-label">Repeat Pattern</label>
                                    <select class="form-select" id="recurrence_pattern" name="recurrence_pattern">
                                        <option value="weekly">Every Week</option>
                                        <option value="biweekly">Every 2 Weeks</option>
                                        <option value="monthly">Every Month</option>
                                        <option value="yearly">Every Year</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="recurrence_end_date" class="form-label">End Date</label>
                                    <input type="date" 
                                           class="form-control" 
                                           id="recurrence_end_date" 
                                           name="recurrence_end_date"
                                           min="<?php echo date('Y-m-d'); ?>">
                                    <div class="form-text">Leave empty for no end date</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Event Templates (Quick Fill) -->
                    <div class="card bg-light border-0 mb-4">
                        <div class="card-header bg-transparent border-0">
                            <h6 class="mb-0">
                                <i class="fas fa-magic me-2"></i>Quick Templates
                            </h6>
                        </div>
                        <div class="card-body pt-2">
                            <div class="btn-group-toggle d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="fillTemplate('sunday_service')">
                                    <i class="fas fa-church me-1"></i>Sunday Service
                                </button>
                                <button type="button" class="btn btn-outline-success btn-sm" onclick="fillTemplate('prayer_meeting')">
                                    <i class="fas fa-praying-hands me-1"></i>Prayer Meeting
                                </button>
                                <button type="button" class="btn btn-outline-info btn-sm" onclick="fillTemplate('bible_study')">
                                    <i class="fas fa-book-open me-1"></i>Bible Study
                                </button>
                                <button type="button" class="btn btn-outline-warning btn-sm" onclick="fillTemplate('youth_service')">
                                    <i class="fas fa-running me-1"></i>Youth Service
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="fillTemplate('special_event')">
                                    <i class="fas fa-star me-1"></i>Special Event
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="redirect_to" id="redirect_events" value="events" checked>
                                <label class="form-check-label" for="redirect_events">
                                    Return to Events List
                                </label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="redirect_to" id="redirect_attendance" value="record_attendance">
                                <label class="form-check-label" for="redirect_attendance">
                                    Record Attendance Now
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6 text-end">
                            <button type="button" class="btn btn-secondary me-2" onclick="history.back()">
                                <i class="fas fa-arrow-left me-2"></i>Cancel
                            </button>
                            <button type="submit" class="btn btn-church-primary">
                                <i class="fas fa-save me-2"></i>Create Event
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const recurringCheckbox = document.getElementById('is_recurring');
    const recurringOptions = document.getElementById('recurring_options');
    const eventTypeSelect = document.getElementById('event_type');
    const eventDateInput = document.getElementById('event_date');
    const startTimeInput = document.getElementById('start_time');
    
    // Toggle recurring options
    recurringCheckbox.addEventListener('change', function() {
        if (this.checked) {
            recurringOptions.style.display = 'block';
            recurringOptions.classList.add('animate-slide-up');
        } else {
            recurringOptions.style.display = 'none';
        }
    });
    
    // Initialize recurring options display
    if (recurringCheckbox.checked) {
        recurringOptions.style.display = 'block';
    }
    
    // Auto-suggest based on event type
    eventTypeSelect.addEventListener('change', function() {
        const eventType = this.value;
        suggestEventDetails(eventType);
    });
    
    // Validate end time is after start time
    document.getElementById('end_time').addEventListener('change', function() {
        const startTime = startTimeInput.value;
        const endTime = this.value;
        
        if (startTime && endTime && endTime <= startTime) {
            this.setCustomValidity('End time must be after start time');
        } else {
            this.setCustomValidity('');
        }
    });
    
    // Form validation
    const form = document.querySelector('.needs-validation');
    form.addEventListener('submit', function(e) {
        if (!form.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        form.classList.add('was-validated');
    });
});

// Event templates
const eventTemplates = {
    sunday_service: {
        name: 'Sunday Morning Service',
        start_time: '09:00',
        end_time: '11:30',
        location: 'Main Sanctuary',
        expected_attendance: 150,
        description: 'Weekly Sunday worship service',
        is_recurring: true,
        recurrence_pattern: 'weekly'
    },
    prayer_meeting: {
        name: 'Wednesday Prayer Meeting',
        start_time: '18:00',
        end_time: '19:30',
        location: 'Prayer Room',
        expected_attendance: 50,
        description: 'Weekly prayer and intercession meeting',
        is_recurring: true,
        recurrence_pattern: 'weekly'
    },
    bible_study: {
        name: 'Thursday Bible Study',
        start_time: '19:00',
        end_time: '20:30',
        location: 'Fellowship Hall',
        expected_attendance: 40,
        description: 'Weekly Bible study and discussion',
        is_recurring: true,
        recurrence_pattern: 'weekly'
    },
    youth_service: {
        name: 'Youth Service',
        start_time: '15:00',
        end_time: '17:00',
        location: 'Youth Hall',
        expected_attendance: 60,
        description: 'Youth fellowship and worship',
        is_recurring: true,
        recurrence_pattern: 'weekly'
    },
    special_event: {
        name: 'Special Event',
        start_time: '10:00',
        end_time: '16:00',
        location: 'Main Sanctuary',
        expected_attendance: 200,
        description: 'Special church event',
        is_recurring: false
    }
};

function fillTemplate(templateKey) {
    const template = eventTemplates[templateKey];
    if (!template) return;
    
    // Fill form fields
    Object.keys(template).forEach(key => {
        const input = document.querySelector(`[name="${key}"]`);
        if (input) {
            if (input.type === 'checkbox') {
                input.checked = template[key];
                input.dispatchEvent(new Event('change'));
            } else {
                input.value = template[key];
            }
        }
    });
    
    // Update event date to next occurrence for recurring events
    if (template.is_recurring && templateKey !== 'special_event') {
        const nextDate = getNextOccurrence(templateKey);
        if (nextDate) {
            document.getElementById('event_date').value = nextDate;
        }
    }
    
    ChurchCMS.showToast(`Template "${template.name}" applied!`, 'success', 2000);
}

function getNextOccurrence(eventType) {
    const today = new Date();
    const dayOfWeek = today.getDay();
    
    // Calculate next occurrence based on event type
    switch (eventType) {
        case 'sunday_service':
            const daysUntilSunday = (7 - dayOfWeek) % 7;
            const nextSunday = new Date(today);
            nextSunday.setDate(today.getDate() + (daysUntilSunday || 7));
            return nextSunday.toISOString().split('T')[0];
            
        case 'prayer_meeting':
            const daysUntilWednesday = (3 - dayOfWeek + 7) % 7;
            const nextWednesday = new Date(today);
            nextWednesday.setDate(today.getDate() + (daysUntilWednesday || 7));
            return nextWednesday.toISOString().split('T')[0];
            
        case 'bible_study':
            const daysUntilThursday = (4 - dayOfWeek + 7) % 7;
            const nextThursday = new Date(today);
            nextThursday.setDate(today.getDate() + (daysUntilThursday || 7));
            return nextThursday.toISOString().split('T')[0];
            
        case 'youth_service':
            const daysUntilSaturday = (6 - dayOfWeek + 7) % 7;
            const nextSaturday = new Date(today);
            nextSaturday.setDate(today.getDate() + (daysUntilSaturday || 7));
            return nextSaturday.toISOString().split('T')[0];
            
        default:
            return null;
    }
}

function suggestEventDetails(eventType) {
    const suggestions = {
        sunday_service: {
            start_time: '09:00',
            location: 'Main Sanctuary',
            expected_attendance: 150
        },
        prayer_meeting: {
            start_time: '18:00',
            location: 'Prayer Room',
            expected_attendance: 50
        },
        bible_study: {
            start_time: '19:00',
            location: 'Fellowship Hall',
            expected_attendance: 40
        },
        youth_service: {
            start_time: '15:00',
            location: 'Youth Hall',
            expected_attendance: 60
        },
        wedding: {
            start_time: '14:00',
            location: 'Main Sanctuary',
            expected_attendance: 100
        },
        funeral: {
            start_time: '10:00',
            location: 'Main Sanctuary',
            expected_attendance: 80
        },
        baptism: {
            start_time: '11:00',
            location: 'Baptistry',
            expected_attendance: 120
        }
    };
    
    const suggestion = suggestions[eventType];
    if (suggestion) {
        // Only fill if fields are empty
        Object.keys(suggestion).forEach(key => {
            const input = document.querySelector(`[name="${key}"]`);
            if (input && !input.value) {
                input.value = suggestion[key];
            }
        });
    }
}

// Auto-save form data
setInterval(function() {
    const formData = new FormData(document.querySelector('form'));
    const data = {};
    
    for (let [key, value] of formData.entries()) {
        data[key] = value;
    }
    
    localStorage.setItem('event_form_draft', JSON.stringify(data));
}, 30000);

// Load saved form data on page load
window.addEventListener('load', function() {
    const savedData = localStorage.getItem('event_form_draft');
    if (savedData) {
        try {
            const data = JSON.parse(savedData);
            const confirmed = confirm('You have unsaved event data. Would you like to restore it?');
            
            if (confirmed) {
                Object.keys(data).forEach(key => {
                    const input = document.querySelector(`[name="${key}"]`);
                    if (input) {
                        if (input.type === 'checkbox') {
                            input.checked = data[key] === '1';
                        } else {
                            input.value = data[key];
                        }
                    }
                });
            }
        } catch (e) {
            console.error('Error loading saved data:', e);
        }
    }
});
</script>

<?php include '../../includes/footer.php'; ?>

<?php
/**
 * Helper function to create recurring events
 * @param int $originalEventId
 * @param array $eventData
 * @param string $pattern
 * @param string $endDate
 */
function createRecurringEvents($originalEventId, $eventData, $pattern, $endDate = null) {
    $db = Database::getInstance();
    
    $startDate = new DateTime($eventData['event_date']);
    $endDateObj = $endDate ? new DateTime($endDate) : new DateTime('+1 year');
    
    $currentDate = clone $startDate;
    $eventsCreated = 0;
    $maxEvents = 52; // Limit to prevent infinite loops
    
    while ($currentDate <= $endDateObj && $eventsCreated < $maxEvents) {
        // Calculate next occurrence
        switch ($pattern) {
            case 'weekly':
                $currentDate->add(new DateInterval('P7D'));
                break;
            case 'biweekly':
                $currentDate->add(new DateInterval('P14D'));
                break;
            case 'monthly':
                $currentDate->add(new DateInterval('P1M'));
                break;
            case 'yearly':
                $currentDate->add(new DateInterval('P1Y'));
                break;
            default:
                break 2; // Exit the loop for unknown patterns
        }
        
        if ($currentDate <= $endDateObj) {
            $recurringEventData = [
                'name' => $eventData['name'],
                'event_type' => $eventData['event_type'],
                'description' => $eventData['description'],
                'event_date' => $currentDate->format('Y-m-d'),
                'start_time' => $eventData['start_time'],
                'end_time' => $eventData['end_time'],
                'location' => $eventData['location'],
                'expected_attendance' => $eventData['expected_attendance'],
                'department_id' => $eventData['department_id'],
                'created_by' => $_SESSION['user_id'],
                'is_recurring' => 1,
                'recurrence_pattern' => $pattern,
                'status' => 'planned'
            ];
            
            insertRecord('events', $recurringEventData);
            $eventsCreated++;
        }
    }
    
    logActivity('Created recurring events', 'events', $originalEventId, null, [
        'pattern' => $pattern,
        'events_created' => $eventsCreated,
        'end_date' => $endDate
    ]);
}
?>