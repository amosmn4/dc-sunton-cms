<?php
/**
 * Event Calendar
 * Visual calendar display of all church events
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

requireLogin();

$db = Database::getInstance();

// Get all events for calendar
$stmt = $db->executeQuery("
    SELECT 
        e.*,
        d.name as department_name
    FROM events e
    LEFT JOIN departments d ON e.department_id = d.id
    WHERE e.event_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
    ORDER BY e.event_date, e.start_time
");
$events = $stmt->fetchAll();

// Format events for FullCalendar
$calendarEvents = [];
foreach ($events as $event) {
    $statusColors = [
        'planned' => '#03045e',
        'ongoing' => '#17a2b8',
        'completed' => '#28a745',
        'cancelled' => '#dc3545'
    ];
    
    $calendarEvents[] = [
        'id' => $event['id'],
        'title' => $event['name'],
        'start' => $event['event_date'] . 'T' . $event['start_time'],
        'end' => $event['end_time'] ? $event['event_date'] . 'T' . $event['end_time'] : null,
        'backgroundColor' => $statusColors[$event['status']] ?? '#6c757d',
        'borderColor' => $statusColors[$event['status']] ?? '#6c757d',
        'extendedProps' => [
            'type' => $event['event_type'],
            'location' => $event['location'],
            'department' => $event['department_name'],
            'status' => $event['status'],
            'description' => $event['description']
        ]
    ];
}

$page_title = 'Event Calendar';
$page_icon = 'fas fa-calendar-alt';

include '../../includes/header.php';
?>

<style>
.fc {
    max-width: 100%;
}
.fc-event {
    cursor: pointer;
}
.fc-daygrid-event {
    white-space: normal !important;
}
</style>

<!-- Calendar Controls -->
<div class="row mb-4">
    <div class="col-md-8">
        <div class="btn-group">
            <button class="btn btn-outline-primary" id="prev-btn">
                <i class="fas fa-chevron-left"></i> Previous
            </button>
            <button class="btn btn-outline-primary" id="today-btn">
                <i class="fas fa-calendar-day"></i> Today
            </button>
            <button class="btn btn-outline-primary" id="next-btn">
                Next <i class="fas fa-chevron-right"></i>
            </button>
        </div>
        <div class="btn-group ms-3">
            <button class="btn btn-outline-secondary" data-view="dayGridMonth">Month</button>
            <button class="btn btn-outline-secondary" data-view="timeGridWeek">Week</button>
            <button class="btn btn-outline-secondary" data-view="timeGridDay">Day</button>
            <button class="btn btn-outline-secondary" data-view="listWeek">List</button>
        </div>
    </div>
    <div class="col-md-4 text-end">
        <a href="<?php echo BASE_URL; ?>modules/attendance/events.php" class="btn btn-church-primary">
            <i class="fas fa-plus me-2"></i>Add Event
        </a>
    </div>
</div>

<!-- Legend -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body py-2">
                <div class="d-flex justify-content-center gap-4">
                    <span><i class="fas fa-circle" style="color: #03045e;"></i> Planned</span>
                    <span><i class="fas fa-circle" style="color: #17a2b8;"></i> Ongoing</span>
                    <span><i class="fas fa-circle" style="color: #28a745;"></i> Completed</span>
                    <span><i class="fas fa-circle" style="color: #dc3545;"></i> Cancelled</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Calendar -->
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div id="calendar"></div>
    </div>
</div>

<!-- Event Details Modal -->
<div class="modal fade" id="eventModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-church-blue text-white">
                <h5 class="modal-title" id="eventModalTitle"></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="eventModalBody">
                <!-- Content loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="#" id="eventEditBtn" class="btn btn-church-primary">
                    <i class="fas fa-edit me-2"></i>Edit Event
                </a>
            </div>
        </div>
    </div>
</div>

<link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar');
    const events = <?php echo json_encode($calendarEvents); ?>;
    
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: false,
        height: 'auto',
        events: events,
        eventClick: function(info) {
            showEventDetails(info.event);
        },
        dateClick: function(info) {
            // Option to create new event on date click
            window.location.href = BASE_URL + 'modules/attendance/events.php?date=' + info.dateStr;
        }
    });
    
    calendar.render();
    
    // Navigation buttons
    document.getElementById('prev-btn').addEventListener('click', () => {
        calendar.prev();
    });
    
    document.getElementById('today-btn').addEventListener('click', () => {
        calendar.today();
    });
    
    document.getElementById('next-btn').addEventListener('click', () => {
        calendar.next();
    });
    
    // View buttons
    document.querySelectorAll('[data-view]').forEach(btn => {
        btn.addEventListener('click', function() {
            calendar.changeView(this.dataset.view);
            document.querySelectorAll('[data-view]').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
        });
    });
    
    // Set initial active view button
    document.querySelector('[data-view="dayGridMonth"]').classList.add('active');
});

function showEventDetails(event) {
    const props = event.extendedProps;
    
    const statusBadges = {
        'planned': 'primary',
        'ongoing': 'info',
        'completed': 'success',
        'cancelled': 'danger'
    };
    
    const eventTypesDisplay = {
        'sunday_service': 'Sunday Service',
        'prayer_meeting': 'Prayer Meeting',
        'bible_study': 'Bible Study',
        'youth_service': 'Youth Service',
        'special_event': 'Special Event',
        'conference': 'Conference',
        'revival': 'Revival Meeting',
        'outreach': 'Outreach Program',
        'fundraiser': 'Fundraising Event',
        'wedding': 'Wedding Ceremony',
        'funeral': 'Funeral Service',
        'baptism': 'Baptism Service',
        'other': 'Other Event'
    };
    
    document.getElementById('eventModalTitle').textContent = event.title;
    
    const html = `
        <div class="row">
            <div class="col-md-6 mb-3">
                <strong>Date:</strong><br>
                ${new Date(event.start).toLocaleDateString('en-US', { 
                    weekday: 'long', 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                })}
            </div>
            <div class="col-md-6 mb-3">
                <strong>Time:</strong><br>
                ${new Date(event.start).toLocaleTimeString('en-US', { 
                    hour: '2-digit', 
                    minute: '2-digit' 
                })}
                ${event.end ? ' - ' + new Date(event.end).toLocaleTimeString('en-US', { 
                    hour: '2-digit', 
                    minute: '2-digit' 
                }) : ''}
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <strong>Type:</strong><br>
                <span class="badge bg-secondary">${eventTypesDisplay[props.type] || props.type}</span>
            </div>
            <div class="col-md-6 mb-3">
                <strong>Status:</strong><br>
                <span class="badge bg-${statusBadges[props.status] || 'secondary'}">${props.status.toUpperCase()}</span>
            </div>
        </div>
        ${props.location ? `
        <div class="mb-3">
            <strong>Location:</strong><br>
            <i class="fas fa-map-marker-alt text-danger me-2"></i>${props.location}
        </div>
        ` : ''}
        ${props.department ? `
        <div class="mb-3">
            <strong>Department:</strong><br>
            <i class="fas fa-users text-primary me-2"></i>${props.department}
        </div>
        ` : ''}
        ${props.description ? `
        <div class="mb-3">
            <strong>Description:</strong><br>
            <p class="mb-0">${props.description}</p>
        </div>
        ` : ''}
        <div class="d-flex gap-2 mt-4">
            ${props.status !== 'completed' ? `
            <a href="${BASE_URL}modules/attendance/record.php?event_id=${event.id}" class="btn btn-success btn-sm">
                <i class="fas fa-check me-2"></i>Record Attendance
            </a>
            ` : `
            <a href="${BASE_URL}modules/attendance/view.php?event_id=${event.id}" class="btn btn-info btn-sm">
                <i class="fas fa-eye me-2"></i>View Attendance
            </a>
            `}
        </div>
    `;
    
    document.getElementById('eventModalBody').innerHTML = html;
    document.getElementById('eventEditBtn').href = BASE_URL + 'modules/attendance/events.php?edit=' + event.id;
    
    new bootstrap.Modal(document.getElementById('eventModal')).show();
}
</script>

<?php include '../../includes/footer.php'; ?>