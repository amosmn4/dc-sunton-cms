<?php
/**
 * Attendance Dashboard
 * Deliverance Church Management System
 * 
 * Main attendance overview page with stats and recent activities
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
$page_title = 'Attendance Management';
$page_icon = 'fas fa-calendar-check';
$breadcrumb = [
    ['title' => 'Attendance Management']
];

// Get attendance statistics
try {
    $db = Database::getInstance();
    
    // Today's attendance
    $todayAttendance = $db->executeQuery("
        SELECT 
            COALESCE(SUM(ac.count_number), 0) as total_attendance,
            COUNT(DISTINCT ar.member_id) as members_present
        FROM events e
        LEFT JOIN attendance_counts ac ON e.id = ac.event_id AND ac.attendance_category = 'total'
        LEFT JOIN attendance_records ar ON e.id = ar.event_id AND ar.is_present = 1
        WHERE DATE(e.event_date) = CURDATE()
    ")->fetch();
    
    // This week's average attendance
    $weeklyAverage = $db->executeQuery("
        SELECT 
            ROUND(AVG(total_count), 0) as avg_attendance
        FROM (
            SELECT 
                e.event_date,
                COALESCE(SUM(ac.count_number), COUNT(ar.member_id)) as total_count
            FROM events e
            LEFT JOIN attendance_counts ac ON e.id = ac.event_id AND ac.attendance_category = 'total'
            LEFT JOIN attendance_records ar ON e.id = ar.event_id AND ar.is_present = 1
            WHERE e.event_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY e.event_date
        ) as daily_counts
    ")->fetch();
    
    // This month's total events
    $monthlyEvents = $db->executeQuery("
        SELECT COUNT(*) as event_count
        FROM events 
        WHERE MONTH(event_date) = MONTH(CURDATE()) 
        AND YEAR(event_date) = YEAR(CURDATE())
    ")->fetch();
    
    // Regular attendees (attended 80% of services in last month)
    $regularAttendees = $db->executeQuery("
        SELECT COUNT(DISTINCT m.id) as regular_count
        FROM members m
        WHERE (
            SELECT COUNT(*)
            FROM attendance_records ar
            JOIN events e ON ar.event_id = e.id
            WHERE ar.member_id = m.id 
            AND ar.is_present = 1
            AND e.event_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ) >= (
            SELECT COUNT(*) * 0.8
            FROM events
            WHERE event_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        )
        AND m.membership_status = 'active'
    ")->fetch();
    
    // Recent attendance records
    $recentAttendance = $db->executeQuery("
        SELECT 
            e.name as event_name,
            e.event_date,
            e.start_time,
            e.event_type,
            COALESCE(SUM(ac.count_number), COUNT(ar.member_id)) as attendance_count
        FROM events e
        LEFT JOIN attendance_counts ac ON e.id = ac.event_id AND ac.attendance_category = 'total'
        LEFT JOIN attendance_records ar ON e.id = ar.event_id AND ar.is_present = 1
        WHERE e.event_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY e.id
        ORDER BY e.event_date DESC, e.start_time DESC
        LIMIT 10
    ")->fetchAll();
    
    // Upcoming events
    $upcomingEvents = $db->executeQuery("
        SELECT 
            id,
            name,
            event_type,
            event_date,
            start_time,
            location,
            expected_attendance
        FROM events 
        WHERE event_date >= CURDATE()
        ORDER BY event_date ASC, start_time ASC
        LIMIT 5
    ")->fetchAll();
    
    // Attendance trends (last 7 days)
    $attendanceTrends = $db->executeQuery("
        SELECT 
            e.event_date,
            e.name,
            COALESCE(SUM(ac.count_number), COUNT(ar.member_id)) as attendance_count
        FROM events e
        LEFT JOIN attendance_counts ac ON e.id = ac.event_id AND ac.attendance_category = 'total'
        LEFT JOIN attendance_records ar ON e.id = ar.event_id AND ar.is_present = 1
        WHERE e.event_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY e.id
        ORDER BY e.event_date ASC
    ")->fetchAll();
    
} catch (Exception $e) {
    error_log("Error fetching attendance data: " . $e->getMessage());
    setFlashMessage('error', 'Error loading attendance data');
    $todayAttendance = ['total_attendance' => 0, 'members_present' => 0];
    $weeklyAverage = ['avg_attendance' => 0];
    $monthlyEvents = ['event_count' => 0];
    $regularAttendees = ['regular_count' => 0];
    $recentAttendance = [];
    $upcomingEvents = [];
    $attendanceTrends = [];
}

// Page actions
$page_actions = [
    [
        'title' => 'Record Attendance',
        'url' => BASE_URL . 'modules/attendance/record.php',
        'icon' => 'fas fa-plus',
        'class' => 'church-primary'
    ],
    [
        'title' => 'Add Event',
        'url' => BASE_URL . 'modules/attendance/add_event.php',
        'icon' => 'fas fa-calendar-plus',
        'class' => 'success'
    ],
    [
        'title' => 'View Reports',
        'url' => BASE_URL . 'modules/attendance/reports.php',
        'icon' => 'fas fa-chart-bar',
        'class' => 'info'
    ]
];

include '../../includes/header.php';
?>

<div class="row">
    <!-- Statistics Cards -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stats-card hover-lift">
            <div class="d-flex justify-content-between">
                <div>
                    <div class="stats-number text-church-blue">
                        <?php echo number_format($todayAttendance['total_attendance'] ?: 0); ?>
                    </div>
                    <div class="stats-label">Today's Attendance</div>
                    <small class="text-muted">
                        <?php echo number_format($todayAttendance['members_present'] ?: 0); ?> members checked in
                    </small>
                </div>
                <div class="stats-icon bg-church-blue">
                    <i class="fas fa-users text-white"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stats-card hover-lift">
            <div class="d-flex justify-content-between">
                <div>
                    <div class="stats-number text-church-red">
                        <?php echo number_format($weeklyAverage['avg_attendance'] ?: 0); ?>
                    </div>
                    <div class="stats-label">Weekly Average</div>
                    <small class="text-muted">Last 7 days average</small>
                </div>
                <div class="stats-icon bg-church-red">
                    <i class="fas fa-chart-line text-white"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stats-card hover-lift">
            <div class="d-flex justify-content-between">
                <div>
                    <div class="stats-number text-success">
                        <?php echo number_format($monthlyEvents['event_count'] ?: 0); ?>
                    </div>
                    <div class="stats-label">Monthly Events</div>
                    <small class="text-muted">Events this month</small>
                </div>
                <div class="stats-icon bg-success">
                    <i class="fas fa-calendar-alt text-white"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stats-card hover-lift">
            <div class="d-flex justify-content-between">
                <div>
                    <div class="stats-number text-warning">
                        <?php echo number_format($regularAttendees['regular_count'] ?: 0); ?>
                    </div>
                    <div class="stats-label">Regular Attendees</div>
                    <small class="text-muted">80%+ attendance rate</small>
                </div>
                <div class="stats-icon bg-warning">
                    <i class="fas fa-star text-white"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Attendance Trends Chart -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-line me-2"></i>Attendance Trends (Last 7 Days)
                </h5>
            </div>
            <div class="card-body">
                <canvas id="attendanceTrendsChart" height="300"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>Quick Actions
                </h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-3">
                    <a href="<?php echo BASE_URL; ?>modules/attendance/record.php" class="btn btn-church-primary">
                        <i class="fas fa-plus me-2"></i>Record Attendance
                    </a>
                    <a href="<?php echo BASE_URL; ?>modules/attendance/add_event.php" class="btn btn-success">
                        <i class="fas fa-calendar-plus me-2"></i>Add New Event
                    </a>
                    <a href="<?php echo BASE_URL; ?>modules/attendance/bulk_record.php" class="btn btn-info">
                        <i class="fas fa-upload me-2"></i>Bulk Upload
                    </a>
                    <a href="<?php echo BASE_URL; ?>modules/attendance/reports.php" class="btn btn-warning">
                        <i class="fas fa-chart-bar me-2"></i>View Reports
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Recent Attendance Records -->
    <div class="col-lg-7 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-history me-2"></i>Recent Attendance Records
                </h5>
                <a href="<?php echo BASE_URL; ?>modules/attendance/all_records.php" class="btn btn-sm btn-outline-light">
                    View All
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentAttendance)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No Recent Attendance Records</h5>
                        <p class="text-muted">Start by recording attendance for an event</p>
                        <a href="<?php echo BASE_URL; ?>modules/attendance/record.php" class="btn btn-church-primary">
                            <i class="fas fa-plus me-2"></i>Record Attendance
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Event</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Type</th>
                                    <th>Attendance</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentAttendance as $record): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($record['event_name']); ?></strong>
                                    </td>
                                    <td><?php echo formatDisplayDate($record['event_date']); ?></td>
                                    <td><?php echo date('H:i', strtotime($record['start_time'])); ?></td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php echo getEventTypeDisplay($record['event_type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong class="text-church-blue">
                                            <?php echo number_format($record['attendance_count']); ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?php echo BASE_URL; ?>modules/attendance/view_event.php?id=<?php echo $record['id']; ?>" 
                                               class="btn btn-outline-primary" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="<?php echo BASE_URL; ?>modules/attendance/edit_attendance.php?event_id=<?php echo $record['id']; ?>" 
                                               class="btn btn-outline-success" title="Edit Attendance">
                                                <i class="fas fa-edit"></i>
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
    </div>
    
    <!-- Upcoming Events -->
    <div class="col-lg-5 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-calendar-week me-2"></i>Upcoming Events
                </h5>
                <a href="<?php echo BASE_URL; ?>modules/events/" class="btn btn-sm btn-outline-light">
                    Manage Events
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($upcomingEvents)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-calendar-plus fa-2x text-muted mb-3"></i>
                        <h6 class="text-muted">No Upcoming Events</h6>
                        <p class="text-muted small">Add events to track attendance</p>
                        <a href="<?php echo BASE_URL; ?>modules/attendance/add_event.php" class="btn btn-church-primary btn-sm">
                            <i class="fas fa-plus me-2"></i>Add Event
                        </a>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($upcomingEvents as $event): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-start border-0 px-0">
                            <div class="ms-2 me-auto">
                                <div class="fw-bold text-church-blue">
                                    <?php echo htmlspecialchars($event['name']); ?>
                                </div>
                                <small class="text-muted">
                                    <i class="fas fa-calendar me-1"></i><?php echo formatDisplayDate($event['event_date']); ?>
                                    <i class="fas fa-clock ms-2 me-1"></i><?php echo date('H:i', strtotime($event['start_time'])); ?>
                                </small>
                                <?php if ($event['location']): ?>
                                <br><small class="text-muted">
                                    <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($event['location']); ?>
                                </small>
                                <?php endif; ?>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-secondary">
                                    <?php echo getEventTypeDisplay($event['event_type']); ?>
                                </span>
                                <?php if ($event['expected_attendance']): ?>
                                <br><small class="text-muted">
                                    Expected: <?php echo number_format($event['expected_attendance']); ?>
                                </small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Department Attendance Overview -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-users-cog me-2"></i>Department Attendance This Month
                </h5>
            </div>
            <div class="card-body">
                <canvas id="departmentAttendanceChart" height="250"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Attendance by Service Type -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>Attendance by Service Type
                </h5>
            </div>
            <div class="card-body">
                <canvas id="serviceTypeChart" height="250"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Event Details Modal -->
<div class="modal fade" id="eventDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-church-blue text-white">
                <h5 class="modal-title">
                    <i class="fas fa-calendar-alt me-2"></i>Event Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="eventDetailsContent">
                <!-- Content will be loaded via AJAX -->
                <div class="text-center py-4">
                    <div class="spinner-border text-church-blue" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize attendance trends chart
    const trendsCtx = document.getElementById('attendanceTrendsChart').getContext('2d');
    const trendsData = <?php echo json_encode($attendanceTrends); ?>;
    
    new Chart(trendsCtx, {
        type: 'line',
        data: {
            labels: trendsData.map(item => ChurchCMS.formatDate(item.event_date)),
            datasets: [{
                label: 'Attendance',
                data: trendsData.map(item => parseInt(item.attendance_count)),
                borderColor: '<?php echo CHURCH_BLUE; ?>',
                backgroundColor: '<?php echo CHURCH_BLUE; ?>20',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '<?php echo CHURCH_RED; ?>',
                pointBorderColor: '<?php echo CHURCH_BLUE; ?>',
                pointBorderWidth: 2,
                pointRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(3, 4, 94, 0.1)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
    
    // Initialize department attendance chart (placeholder data)
    const deptCtx = document.getElementById('departmentAttendanceChart').getContext('2d');
    new Chart(deptCtx, {
        type: 'bar',
        data: {
            labels: ['Youth', 'Adults', 'Seniors', 'Children', 'Choir'],
            datasets: [{
                label: 'Average Attendance',
                data: [45, 78, 23, 34, 25],
                backgroundColor: [
                    '<?php echo CHURCH_BLUE; ?>',
                    '<?php echo CHURCH_RED; ?>',
                    '<?php echo CHURCH_SUCCESS; ?>',
                    '<?php echo CHURCH_WARNING; ?>',
                    '<?php echo CHURCH_INFO; ?>'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    
    // Initialize service type chart
    const serviceCtx = document.getElementById('serviceTypeChart').getContext('2d');
    new Chart(serviceCtx, {
        type: 'doughnut',
        data: {
            labels: ['Sunday Service', 'Prayer Meeting', 'Bible Study', 'Youth Service', 'Special Events'],
            datasets: [{
                data: [65, 15, 10, 8, 2],
                backgroundColor: [
                    '<?php echo CHURCH_BLUE; ?>',
                    '<?php echo CHURCH_RED; ?>',
                    '<?php echo CHURCH_SUCCESS; ?>',
                    '<?php echo CHURCH_WARNING; ?>',
                    '<?php echo CHURCH_INFO; ?>'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
    
    // Load event details modal
    window.loadEventDetails = function(eventId) {
        const modal = new bootstrap.Modal(document.getElementById('eventDetailsModal'));
        const content = document.getElementById('eventDetailsContent');
        
        // Show loading
        content.innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-church-blue" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        `;
        
        modal.show();
        
        // Load event details via AJAX
        fetch(`${BASE_URL}api/attendance.php?action=get_event_details&id=${eventId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    content.innerHTML = data.html;
                } else {
                    content.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Error loading event details: ${data.message}
                        </div>
                    `;
                }
            })
            .catch(error => {
                content.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Error loading event details. Please try again.
                    </div>
                `;
            });
    };
    
    // Auto-refresh stats every 5 minutes
    setInterval(function() {
        fetch(`${BASE_URL}api/attendance.php?action=get_stats`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update stats cards
                    document.querySelector('.stats-card:nth-child(1) .stats-number').textContent = 
                        parseInt(data.stats.today_attendance).toLocaleString();
                    document.querySelector('.stats-card:nth-child(2) .stats-number').textContent = 
                        parseInt(data.stats.weekly_average).toLocaleString();
                    document.querySelector('.stats-card:nth-child(3) .stats-number').textContent = 
                        parseInt(data.stats.monthly_events).toLocaleString();
                    document.querySelector('.stats-card:nth-child(4) .stats-number').textContent = 
                        parseInt(data.stats.regular_attendees).toLocaleString();
                }
            })
            .catch(error => {
                console.log('Stats refresh failed:', error);
            });
    }, 300000); // 5 minutes
});
</script>

<?php include '../../includes/footer.php'; ?>