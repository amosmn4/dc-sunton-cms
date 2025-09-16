<?php
/**
 * Events Calendar Main Page
 * Deliverance Church Management System
 * 
 * Displays events in calendar view and list view
 */

// Include required files
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Check authentication
requireLogin();

// Check permissions
if (!hasPermission('events') && !in_array($_SESSION['user_role'], ['administrator', 'pastor', 'secretary', 'department_head', 'editor'])) {
    setFlashMessage('error', 'You do not have permission to access this page.');
    redirect(BASE_URL . 'modules/dashboard/');
}

// Page variables
$page_title = 'Events Calendar';
$page_icon = 'fas fa-calendar-alt';
$current_date = date('Y-m-d');
$current_month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$current_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$view_type = isset($_GET['view']) ? $_GET['view'] : 'calendar';

// Breadcrumb
$breadcrumb = [
    ['title' => 'Events', 'url' => BASE_URL . 'modules/events/'],
    ['title' => 'Calendar']
];

// Page actions
$page_actions = [
    [
        'title' => 'Add Event',
        'url' => BASE_URL . 'modules/events/add.php',
        'icon' => 'fas fa-plus',
        'class' => 'church-primary'
    ],
    [
        'title' => 'List View',
        'url' => BASE_URL . 'modules/events/?view=list',
        'icon' => 'fas fa-list',
        'class' => 'secondary'
    ]
];

if ($view_type === 'list') {
    $page_actions[1] = [
        'title' => 'Calendar View',
        'url' => BASE_URL . 'modules/events/?view=calendar',
        'icon' => 'fas fa-calendar',
        'class' => 'secondary'
    ];
}

try {
    $db = Database::getInstance();
    
    // Get events for current month or all events for list view
    if ($view_type === 'calendar') {
        $start_date = date('Y-m-01', mktime(0, 0, 0, $current_month, 1, $current_year));
        $end_date = date('Y-m-t', mktime(0, 0, 0, $current_month, 1, $current_year));
        
        $stmt = $db->executeQuery("
            SELECT e.*, d.name as department_name, u.first_name as created_by_name, u.last_name as created_by_lastname
            FROM events e
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN users u ON e.created_by = u.id
            WHERE e.event_date BETWEEN ? AND ?
            ORDER BY e.event_date ASC, e.start_time ASC
        ", [$start_date, $end_date]);
    } else {
        // List view - get upcoming events
        $stmt = $db->executeQuery("
            SELECT e.*, d.name as department_name, u.first_name as created_by_name, u.last_name as created_by_lastname,
                   (SELECT COUNT(*) FROM attendance_records ar WHERE ar.event_id = e.id) as attendance_count
            FROM events e
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN users u ON e.created_by = u.id
            WHERE e.event_date >= CURDATE()
            ORDER BY e.event_date ASC, e.start_time ASC
            LIMIT 50
        ");
    }
    
    $events = $stmt->fetchAll();
    
    // Get event statistics
    $stats_stmt = $db->executeQuery("
        SELECT 
            COUNT(*) as total_events,
            SUM(CASE WHEN event_date = CURDATE() THEN 1 ELSE 0 END) as today_events,
            SUM(CASE WHEN event_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as this_week_events,
            SUM(CASE WHEN event_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND CURDATE() THEN 1 ELSE 0 END) as last_month_events
        FROM events 
        WHERE status != 'cancelled'
    ");
    $stats = $stats_stmt->fetch();
    
} catch (Exception $e) {
    error_log("Error fetching events: " . $e->getMessage());
    setFlashMessage('error', 'Error loading events data.');
    $events = [];
    $stats = ['total_events' => 0, 'today_events' => 0, 'this_week_events' => 0, 'last_month_events' => 0];
}

// Additional CSS for calendar
$additional_css = ['assets/css/calendar.css'];
$additional_js = ['assets/js/calendar.js'];

// Include header
include_once '../../includes/header.php';
?>

<!-- Main Content -->
<div class="row">
    <!-- Statistics Cards -->
    <div class="col-12 mb-4">
        <div class="row">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stats-card bg-gradient-church">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon bg-white bg-opacity-20 text-white me-3">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="stats-number text-white"><?php echo number_format($stats['total_events']); ?></div>
                            <div class="stats-label text-white-75">Total Events</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon bg-church-red text-white me-3">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="stats-number"><?php echo number_format($stats['today_events']); ?></div>
                            <div class="stats-label">Today's Events</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon bg-church-blue text-white me-3">
                            <i class="fas fa-calendar-week"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="stats-number"><?php echo number_format($stats['this_week_events']); ?></div>
                            <div class="stats-label">This Week</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon bg-success text-white me-3">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="stats-number"><?php echo number_format($stats['last_month_events']); ?></div>
                            <div class="stats-label">Last 30 Days</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Calendar/List View -->
    <div class="col-12">
        <?php if ($view_type === 'calendar'): ?>
            <!-- Calendar View -->
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-calendar me-2"></i>
                            <?php echo date('F Y', mktime(0, 0, 0, $current_month, 1, $current_year)); ?>
                        </h5>
                        <div class="btn-group">
                            <?php
                            $prev_month = $current_month - 1;
                            $prev_year = $current_year;
                            if ($prev_month < 1) {
                                $prev_month = 12;
                                $prev_year--;
                            }
                            
                            $next_month = $current_month + 1;
                            $next_year = $current_year;
                            if ($next_month > 12) {
                                $next_month = 1;
                                $next_year++;
                            }
                            ?>
                            <a href="?view=calendar&month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>" 
                               class="btn btn-outline-light btn-sm">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                            <a href="?view=calendar&month=<?php echo date('n'); ?>&year=<?php echo date('Y'); ?>" 
                               class="btn btn-outline-light btn-sm">Today</a>
                            <a href="?view=calendar&month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>" 
                               class="btn btn-outline-light btn-sm">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php
                    // Generate calendar
                    $first_day = date('w', mktime(0, 0, 0, $current_month, 1, $current_year));
                    $days_in_month = date('t', mktime(0, 0, 0, $current_month, 1, $current_year));
                    
                    // Group events by date
                    $events_by_date = [];
                    foreach ($events as $event) {
                        $event_date = date('j', strtotime($event['event_date']));
                        if (!isset($events_by_date[$event_date])) {
                            $events_by_date[$event_date] = [];
                        }
                        $events_by_date[$event_date][] = $event;
                    }
                    ?>
                    
                    <div class="calendar-grid">
                        <!-- Calendar Header -->
                        <div class="calendar-header">
                            <div class="calendar-day-header">Sun</div>
                            <div class="calendar-day-header">Mon</div>
                            <div class="calendar-day-header">Tue</div>
                            <div class="calendar-day-header">Wed</div>
                            <div class="calendar-day-header">Thu</div>
                            <div class="calendar-day-header">Fri</div>
                            <div class="calendar-day-header">Sat</div>
                        </div>
                        
                        <!-- Calendar Body -->
                        <div class="calendar-body">
                            <?php
                            // Empty cells for days before month starts
                            for ($i = 0; $i < $first_day; $i++) {
                                echo '<div class="calendar-day calendar-day-empty"></div>';
                            }
                            
                            // Days of the month
                            for ($day = 1; $day <= $days_in_month; $day++) {
                                $is_today = ($day == date('j') && $current_month == date('n') && $current_year == date('Y'));
                                $day_classes = ['calendar-day'];
                                if ($is_today) $day_classes[] = 'calendar-day-today';
                                if (isset($events_by_date[$day])) $day_classes[] = 'calendar-day-has-events';
                                
                                echo '<div class="' . implode(' ', $day_classes) . '" data-date="' . sprintf('%04d-%02d-%02d', $current_year, $current_month, $day) . '">';
                                echo '<div class="calendar-day-number">' . $day . '</div>';
                                
                                if (isset($events_by_date[$day])) {
                                    echo '<div class="calendar-events">';
                                    $event_count = 0;
                                    foreach ($events_by_date[$day] as $event) {
                                        if ($event_count >= 3) {
                                            echo '<div class="calendar-event calendar-event-more">+' . (count($events_by_date[$day]) - 3) . ' more</div>';
                                            break;
                                        }
                                        
                                        $event_class = 'calendar-event';
                                        switch ($event['event_type']) {
                                            case 'sunday_service':
                                                $event_class .= ' event-service';
                                                break;
                                            case 'prayer_meeting':
                                                $event_class .= ' event-prayer';
                                                break;
                                            case 'special_event':
                                                $event_class .= ' event-special';
                                                break;
                                            default:
                                                $event_class .= ' event-general';
                                        }
                                        
                                        echo '<div class="' . $event_class . '" data-event-id="' . $event['id'] . '">';
                                        echo '<div class="event-time">' . date('H:i', strtotime($event['start_time'])) . '</div>';
                                        echo '<div class="event-title">' . htmlspecialchars(truncateText($event['name'], 20)) . '</div>';
                                        echo '</div>';
                                        
                                        $event_count++;
                                    }
                                    echo '</div>';
                                }
                                
                                echo '</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            
        <?php else: ?>
            <!-- List View -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-list me-2"></i>Upcoming Events
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($events)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-calendar-plus fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No Upcoming Events</h5>
                            <p class="text-muted">There are no upcoming events scheduled.</p>
                            <a href="<?php echo BASE_URL; ?>modules/events/add.php" class="btn btn-church-primary">
                                <i class="fas fa-plus me-1"></i>Add Event
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Event</th>
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
                                        <tr>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($event['name']); ?></div>
                                                <?php if (!empty($event['description'])): ?>
                                                    <small class="text-muted"><?php echo htmlspecialchars(truncateText($event['description'], 50)); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-church-blue">
                                                    <?php echo getEventTypeDisplay($event['event_type']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div><?php echo formatDisplayDate($event['event_date']); ?></div>
                                                <small class="text-muted">
                                                    <?php echo date('H:i', strtotime($event['start_time'])); ?>
                                                    <?php if ($event['end_time']): ?>
                                                        - <?php echo date('H:i', strtotime($event['end_time'])); ?>
                                                    <?php endif; ?>
                                                </small>
                                            </td>
                                            <td><?php echo htmlspecialchars($event['location'] ?: '-'); ?></td>
                                            <td>
                                                <?php if ($event['department_name']): ?>
                                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($event['department_name']); ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">All</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (isset($event['attendance_count'])): ?>
                                                    <span class="badge bg-info"><?php echo $event['attendance_count']; ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $status_class = [
                                                    'planned' => 'bg-warning',
                                                    'ongoing' => 'bg-info',
                                                    'completed' => 'bg-success',
                                                    'cancelled' => 'bg-danger'
                                                ][$event['status']] ?? 'bg-secondary';
                                                ?>
                                                <span class="badge <?php echo $status_class; ?>">
                                                    <?php echo ucfirst($event['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="<?php echo BASE_URL; ?>modules/events/view.php?id=<?php echo $event['id']; ?>" 
                                                       class="btn btn-outline-info" data-bs-toggle="tooltip" title="View Event">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="<?php echo BASE_URL; ?>modules/events/edit.php?id=<?php echo $event['id']; ?>" 
                                                       class="btn btn-outline-primary" data-bs-toggle="tooltip" title="Edit Event">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="<?php echo BASE_URL; ?>modules/attendance/record.php?event_id=<?php echo $event['id']; ?>" 
                                                       class="btn btn-outline-success" data-bs-toggle="tooltip" title="Record Attendance">
                                                        <i class="fas fa-calendar-check"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-outline-danger confirm-delete" 
                                                            data-id="<?php echo $event['id']; ?>" data-bs-toggle="tooltip" title="Delete Event">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
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
        <?php endif; ?>
    </div>
</div>

<!-- Event Details Modal -->
<div class="modal fade" id="eventModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-church-blue text-white">
                <h5 class="modal-title">
                    <i class="fas fa-calendar-alt me-2"></i>Event Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="eventModalBody">
                <!-- Event details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="#" class="btn btn-church-primary" id="editEventBtn">
                    <i class="fas fa-edit me-1"></i>Edit Event
                </a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Calendar event click handlers
    document.querySelectorAll('.calendar-event').forEach(event => {
        event.addEventListener('click', function() {
            const eventId = this.getAttribute('data-event-id');
            showEventDetails(eventId);
        });
    });
    
    // Calendar day click handler
    document.querySelectorAll('.calendar-day:not(.calendar-day-empty)').forEach(day => {
        day.addEventListener('click', function() {
            const date = this.getAttribute('data-date');
            const hasEvents = this.classList.contains('calendar-day-has-events');
            
            if (!hasEvents) {
                // Redirect to add event with pre-filled date
                window.location.href = `<?php echo BASE_URL; ?>modules/events/add.php?date=${date}`;
            }
        });
    });
    
    // Delete event handler
    document.querySelectorAll('.confirm-delete').forEach(btn => {
        btn.addEventListener('click', function() {
            const eventId = this.getAttribute('data-id');
            
            if (confirm('Are you sure you want to delete this event? This action cannot be undone.')) {
                deleteEvent(eventId);
            }
        });
    });
});

function showEventDetails(eventId) {
    // Show loading
    document.getElementById('eventModalBody').innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';
    
    // Load event details
    fetch(`<?php echo BASE_URL; ?>api/events.php?action=get_event&id=${eventId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayEventDetails(data.event);
                document.getElementById('editEventBtn').href = `<?php echo BASE_URL; ?>modules/events/edit.php?id=${eventId}`;
            } else {
                document.getElementById('eventModalBody').innerHTML = '<div class="alert alert-danger">Error loading event details.</div>';
            }
        })
        .catch(error => {
            document.getElementById('eventModalBody').innerHTML = '<div class="alert alert-danger">Error loading event details.</div>';
        });
    
    // Show modal
    new bootstrap.Modal(document.getElementById('eventModal')).show();
}

function displayEventDetails(event) {
    const html = `
        <div class="row">
            <div class="col-md-8">
                <h4>${event.name}</h4>
                <p class="text-muted">${event.description || 'No description provided'}</p>
                
                <div class="row">
                    <div class="col-sm-6">
                        <strong>Type:</strong> ${event.event_type_display}
                    </div>
                    <div class="col-sm-6">
                        <strong>Status:</strong> <span class="badge bg-info">${event.status}</span>
                    </div>
                    <div class="col-sm-6">
                        <strong>Date:</strong> ${event.event_date_formatted}
                    </div>
                    <div class="col-sm-6">
                        <strong>Time:</strong> ${event.start_time}${event.end_time ? ' - ' + event.end_time : ''}
                    </div>
                    <div class="col-sm-6">
                        <strong>Location:</strong> ${event.location || '-'}
                    </div>
                    <div class="col-sm-6">
                        <strong>Department:</strong> ${event.department_name || 'All Departments'}
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6>Event Statistics</h6>
                        <div class="mb-2">
                            <strong>Expected Attendance:</strong> ${event.expected_attendance || '-'}
                        </div>
                        <div class="mb-2">
                            <strong>Actual Attendance:</strong> ${event.actual_attendance || '-'}
                        </div>
                        <div class="mb-2">
                            <strong>Created By:</strong> ${event.created_by_name}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('eventModalBody').innerHTML = html;
}

function deleteEvent(eventId) {
    fetch(`<?php echo BASE_URL; ?>api/events.php?action=delete_event`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ id: eventId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Error deleting event');
        }
    })
    .catch(error => {
        alert('Error deleting event');
    });
}
</script>

<style>
.calendar-grid {
    display: flex;
    flex-direction: column;
}

.calendar-header {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    background: var(--church-blue);
}

.calendar-day-header {
    padding: 1rem;
    text-align: center;
    color: white;
    font-weight: bold;
    border-right: 1px solid rgba(255, 255, 255, 0.2);
}

.calendar-body {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    min-height: 600px;
}

.calendar-day {
    border-right: 1px solid #e9ecef;
    border-bottom: 1px solid #e9ecef;
    min-height: 120px;
    padding: 0.5rem;
    cursor: pointer;
    transition: background-color 0.2s;
}

.calendar-day:hover {
    background-color: rgba(3, 4, 94, 0.05);
}

.calendar-day-empty {
    background-color: #f8f9fa;
    cursor: default;
}

.calendar-day-today {
    background-color: rgba(255, 36, 0, 0.1);
    border: 2px solid var(--church-red);
}

.calendar-day-has-events {
    background-color: rgba(3, 4, 94, 0.05);
}

.calendar-day-number {
    font-weight: bold;
    margin-bottom: 0.5rem;
    color: var(--church-blue);
}

.calendar-events {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.calendar-event {
    font-size: 0.75rem;
    padding: 2px 4px;
    border-radius: 3px;
    cursor: pointer;
    transition: all 0.2s;
}

.calendar-event:hover {
    transform: scale(1.05);
}

.event-service { background-color: var(--church-red); color: white; }
.event-prayer { background-color: var(--church-blue); color: white; }
.event-special { background-color: #ffc107; color: #212529; }
.event-general { background-color: #6c757d; color: white; }

.event-time {
    font-weight: bold;
}

.event-title {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.calendar-event-more {
    background-color: #17a2b8;
    color: white;
    text-align: center;
}
</style>

<?php include_once '../../includes/footer.php'; ?>