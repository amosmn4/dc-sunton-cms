<?php
/**
 * Attendance Dashboard Widget
 * Deliverance Church Management System
 * 
 * Widget component for displaying attendance summary on dashboard
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

// This widget can be included in the main dashboard
if (!isLoggedIn() || !hasPermission('attendance')) {
    return;
}

$db = Database::getInstance();

try {
    // Get today's attendance
    $todayAttendance = $db->executeQuery("
        SELECT 
            COUNT(DISTINCT e.id) as events_today,
            COALESCE(SUM(ac.count_number), 0) as total_attendance,
            COUNT(DISTINCT ar.member_id) as members_checked_in
        FROM events e
        LEFT JOIN attendance_counts ac ON e.id = ac.event_id AND ac.attendance_category = 'total'
        LEFT JOIN attendance_records ar ON e.id = ar.event_id AND ar.is_present = 1
        WHERE DATE(e.event_date) = CURDATE()
    ")->fetch();
    
    // Get this week's comparison
    $weekComparison = $db->executeQuery("
        SELECT 
            COALESCE(AVG(
                COALESCE(
                    (SELECT SUM(count_number) FROM attendance_counts ac WHERE ac.event_id = e.id AND ac.attendance_category = 'total'),
                    (SELECT COUNT(*) FROM attendance_records ar WHERE ar.event_id = e.id AND ar.is_present = 1)
                )
            ), 0) as this_week_avg,
            COALESCE((
                SELECT AVG(
                    COALESCE(
                        (SELECT SUM(count_number) FROM attendance_counts ac WHERE ac.event_id = prev_e.id AND ac.attendance_category = 'total'),
                        (SELECT COUNT(*) FROM attendance_records ar WHERE ar.event_id = prev_e.id AND ar.is_present = 1)
                    )
                )
                FROM events prev_e
                WHERE prev_e.event_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            ), 0) as last_week_avg
        FROM events e
        WHERE e.event_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ")->fetch();
    
    // Calculate percentage change
    $percentageChange = 0;
    if ($weekComparison['last_week_avg'] > 0) {
        $percentageChange = (($weekComparison['this_week_avg'] - $weekComparison['last_week_avg']) / $weekComparison['last_week_avg']) * 100;
    }
    
    // Get upcoming events
    $upcomingEvents = $db->executeQuery("
        SELECT 
            id,
            name,
            event_date,
            start_time,
            expected_attendance
        FROM events 
        WHERE event_date >= CURDATE()
        AND status = 'planned'
        ORDER BY event_date ASC, start_time ASC
        LIMIT 3
    ")->fetchAll();
    
    // Get recent attendance trends (last 7 days)
    $attendanceTrends = $db->executeQuery("
        SELECT 
            e.event_date,
            COALESCE(
                (SELECT SUM(count_number) FROM attendance_counts ac WHERE ac.event_id = e.id AND ac.attendance_category = 'total'),
                (SELECT COUNT(*) FROM attendance_records ar WHERE ar.event_id = e.id AND ar.is_present = 1)
            ) as attendance_count
        FROM events e
        WHERE e.event_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        AND e.event_date <= CURDATE()
        GROUP BY e.event_date
        ORDER BY e.event_date ASC
    ")->fetchAll();
    
} catch (Exception $e) {
    error_log("Attendance widget error: " . $e->getMessage());
    // Set default values on error
    $todayAttendance = ['events_today' => 0, 'total_attendance' => 0, 'members_checked_in' => 0];
    $percentageChange = 0;
    $upcomingEvents = [];
    $attendanceTrends = [];
}
?>

<div class="row">
    <!-- Today's Attendance Summary -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-calendar-check text-church-blue me-2"></i>
                    Today's Attendance
                </h5>
                <a href="<?php echo BASE_URL; ?>modules/attendance/" class="btn btn-outline-primary btn-sm">
                    View All <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
            <div class="card-body">
                <?php if ($todayAttendance['events_today'] > 0): ?>
                    <div class="row text-center">
                        <div class="col-md-4">
                            <div class="h2 text-church-red mb-1">
                                <?php echo number_format($todayAttendance['total_attendance']); ?>
                            </div>
                            <div class="text-muted small">Total Attendance</div>
                        </div>
                        <div class="col-md-4">
                            <div class="h2 text-church-blue mb-1">
                                <?php echo number_format($todayAttendance['members_checked_in']); ?>
                            </div>
                            <div class="text-muted small">Members Present</div>
                        </div>
                        <div class="col-md-4">
                            <div class="h2 text-success mb-1">
                                <?php echo number_format($todayAttendance['events_today']); ?>
                            </div>
                            <div class="text-muted small">Events Today</div>
                        </div>
                    </div>
                    
                    <!-- Weekly Comparison -->
                    <div class="mt-3 p-3 bg-light rounded">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="small text-muted">This Week vs Last Week</span>
                            <span class="badge bg-<?php echo ($percentageChange >= 0) ? 'success' : 'warning'; ?>">
                                <i class="fas fa-<?php echo ($percentageChange >= 0) ? 'arrow-up' : 'arrow-down'; ?> me-1"></i>
                                <?php echo abs(round($percentageChange, 1)); ?>%
                            </span>
                        </div>
                        <div class="small text-muted mt-1">
                            Average: <?php echo round($weekComparison['this_week_avg']); ?> 
                            (Last week: <?php echo round($weekComparison['last_week_avg']); ?>)
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                        <h6 class="text-muted">No Events Today</h6>
                        <p class="text-muted mb-3">There are no scheduled events for today.</p>
                        <a href="<?php echo BASE_URL; ?>modules/attendance/add_event.php" class="btn btn-church-primary btn-sm">
                            <i class="fas fa-plus me-2"></i>Add Event
                        </a>
                    </div>
                <?php endif; ?>
                
                <!-- Mini Chart for Trends -->
                <?php if (!empty($attendanceTrends)): ?>
                <div class="mt-4">
                    <h6 class="text-muted mb-2">7-Day Attendance Trend</h6>
                    <canvas id="mini-attendance-chart" height="60"></canvas>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Upcoming Events & Quick Actions -->
    <div class="col-lg-4 mb-4">
        <!-- Quick Actions -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-bolt text-warning me-2"></i>Quick Actions
                </h6>
            </div>
            <div class="card-body p-3">
                <div class="d-grid gap-2">
                    <a href="<?php echo BASE_URL; ?>modules/attendance/record.php" class="btn btn-church-primary btn-sm">
                        <i class="fas fa-users me-2"></i>Record Attendance
                    </a>
                    <a href="<?php echo BASE_URL; ?>modules/attendance/add_event.php" class="btn btn-success btn-sm">
                        <i class="fas fa-calendar-plus me-2"></i>Add Event
                    </a>
                    <a href="<?php echo BASE_URL; ?>modules/attendance/reports.php" class="btn btn-info btn-sm">
                        <i class="fas fa-chart-bar me-2"></i>View Reports
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Upcoming Events -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="fas fa-calendar-week text-info me-2"></i>Upcoming Events
                </h6>
                <a href="<?php echo BASE_URL; ?>modules/attendance/events.php" class="text-decoration-none small">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($upcomingEvents)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($upcomingEvents as $event): ?>
                        <div class="list-group-item border-0 py-2">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <h6 class="mb-1 small">
                                        <a href="<?php echo BASE_URL; ?>modules/attendance/view_event.php?id=<?php echo $event['id']; ?>" 
                                           class="text-decoration-none text-church-blue">
                                            <?php echo htmlspecialchars(truncateText($event['name'], 25)); ?>
                                        </a>
                                    </h6>
                                    <div class="small text-muted">
                                        <i class="fas fa-calendar me-1"></i><?php echo formatDisplayDate($event['event_date']); ?>
                                        <br>
                                        <i class="fas fa-clock me-1"></i><?php echo date('H:i', strtotime($event['start_time'])); ?>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <?php if ($event['expected_attendance']): ?>
                                        <small class="text-muted">
                                            Expected: <?php echo number_format($event['expected_attendance']); ?>
                                        </small>
                                    <?php endif; ?>
                                    <br>
                                    <a href="<?php echo BASE_URL; ?>modules/attendance/record.php?event_id=<?php echo $event['id']; ?>" 
                                       class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-users"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-calendar-plus fa-2x text-muted mb-2"></i>
                        <p class="text-muted mb-0 small">No upcoming events scheduled</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Attendance Insights (if user has permission) -->
<?php if (hasPermission('admin') || $_SESSION['user_role'] === 'pastor'): ?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-lightbulb text-warning me-2"></i>Attendance Insights
                </h6>
            </div>
            <div class="card-body">
                <div id="attendance-insights">
                    <div class="text-center py-2">
                        <div class="spinner-border spinner-border-sm text-church-blue" role="status">
                            <span class="visually-hidden">Loading insights...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize mini attendance chart
    <?php if (!empty($attendanceTrends)): ?>
    const miniChartCtx = document.getElementById('mini-attendance-chart');
    if (miniChartCtx) {
        const trendsData = <?php echo json_encode($attendanceTrends); ?>;
        
        new Chart(miniChartCtx, {
            type: 'line',
            data: {
                labels: trendsData.map(item => {
                    const date = new Date(item.event_date);
                    return date.toLocaleDateString('en-US', { weekday: 'short' });
                }),
                datasets: [{
                    data: trendsData.map(item => parseInt(item.attendance_count) || 0),
                    borderColor: '<?php echo CHURCH_BLUE; ?>',
                    backgroundColor: '<?php echo CHURCH_BLUE; ?>20',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 3,
                    pointBackgroundColor: '<?php echo CHURCH_RED; ?>',
                    pointBorderColor: '<?php echo CHURCH_BLUE; ?>'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: { 
                        display: false 
                    },
                    y: { 
                        display: false,
                        beginAtZero: true
                    }
                },
                elements: {
                    point: { radius: 2 }
                }
            }
        });
    }
    <?php endif; ?>
    
    // Load attendance insights
    <?php if (hasPermission('admin') || $_SESSION['user_role'] === 'pastor'): ?>
    loadAttendanceInsights();
    <?php endif; ?>
    
    // Auto-refresh widget data every 5 minutes
    setInterval(function() {
        refreshWidgetData();
    }, 300000);
});

function loadAttendanceInsights() {
    fetch(`<?php echo BASE_URL; ?>api/attendance.php?action=get_insights`)
        .then(response => response.json())
        .then(data => {
            const insightsContainer = document.getElementById('attendance-insights');
            
            if (data.success && data.insights) {
                let insightsHtml = '<div class="row small">';
                
                data.insights.forEach(insight => {
                    const iconClass = getInsightIcon(insight.type);
                    const colorClass = getInsightColor(insight.priority);
                    
                    insightsHtml += `
                        <div class="col-md-6 mb-2">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-${iconClass} text-${colorClass} me-2"></i>
                                <span>${insight.message}</span>
                            </div>
                        </div>
                    `;
                });
                
                insightsHtml += '</div>';
                insightsContainer.innerHTML = insightsHtml;
            } else {
                insightsContainer.innerHTML = `
                    <div class="text-center text-muted small">
                        <i class="fas fa-info-circle me-1"></i>
                        No insights available at this time
                    </div>
                `;
            }
        })
        .catch(error => {
            document.getElementById('attendance-insights').innerHTML = `
                <div class="text-center text-muted small">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    Unable to load insights
                </div>
            `;
        });
}

function getInsightIcon(type) {
    const icons = {
        'trend_up': 'arrow-up',
        'trend_down': 'arrow-down',
        'peak_day': 'calendar-star',
        'low_attendance': 'exclamation-triangle',
        'regular_attendee': 'star',
        'new_visitor': 'user-plus'
    };
    return icons[type] || 'info';
}

function getInsightColor(priority) {
    const colors = {
        'high': 'danger',
        'medium': 'warning',
        'low': 'info',
        'positive': 'success'
    };
    return colors[priority] || 'muted';
}

function refreshWidgetData() {
    // Refresh today's attendance stats
    fetch(`<?php echo BASE_URL; ?>api/attendance.php?action=get_today_stats`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update the attendance numbers
                const totalEl = document.querySelector('.h2.text-church-red');
                const membersEl = document.querySelector('.h2.text-church-blue');
                const eventsEl = document.querySelector('.h2.text-success');
                
                if (totalEl) totalEl.textContent = parseInt(data.stats.total_attendance).toLocaleString();
                if (membersEl) membersEl.textContent = parseInt(data.stats.members_present).toLocaleString();
                if (eventsEl) eventsEl.textContent = parseInt(data.stats.events_today).toLocaleString();
            }
        })
        .catch(error => {
            console.log('Auto-refresh failed:', error);
        });
}

// Quick attendance recording
function quickRecordAttendance(eventId) {
    window.location.href = `<?php echo BASE_URL; ?>modules/attendance/record.php?event_id=${eventId}`;
}

// Show attendance summary modal
function showAttendanceSummary() {
    fetch(`<?php echo BASE_URL; ?>api/attendance.php?action=get_summary&period=week`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Create and show modal with summary data
                const modal = new bootstrap.Modal(document.getElementById('attendanceSummaryModal'));
                document.getElementById('summaryModalContent').innerHTML = data.html;
                modal.show();
            }
        });
}
</script>

<!-- Attendance Summary Modal -->
<div class="modal fade" id="attendanceSummaryModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-church-blue text-white">
                <h5 class="modal-title">
                    <i class="fas fa-chart-line me-2"></i>Weekly Attendance Summary
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="summaryModalContent">
                <!-- Content loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="<?php echo BASE_URL; ?>modules/attendance/reports.php" class="btn btn-church-primary">
                    View Full Reports
                </a>
            </div>
        </div>
    </div>
</div>