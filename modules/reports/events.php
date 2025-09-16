// Event attendance analysis
            $attendanceAnalysis = $db->executeQuery("
                SELECT 
                    e.name as event_name,
                    e.event_type,
                    e.event_date,
                    e.expected_attendance,
                    COALESCE(actual_attendance.attendance_count, 0) as actual_attendance,
                    CASE 
                        WHEN e.expected_attendance > 0 THEN 
                            ROUND((actual_attendance.attendance_count / e.expected_attendance) * 100, 1)
                        ELSE NULL 
                    END as attendance_percentage,
                    CASE 
                        WHEN actual_attendance.attendance_count >= e.expected_attendance THEN 'exceeded'
                        WHEN actual_attendance.attendance_count >= (e.expected_attendance * 0.8) THEN 'good'
                        WHEN actual_attendance.attendance_count >= (e.expected_attendance * 0.6) THEN 'average'
                        ELSE 'poor'
                    END as performance_rating
                FROM events e
                LEFT JOIN (
                    SELECT event_id, COUNT(*) as attendance_count
                    FROM attendance_records
                    WHERE is_present = 1
                    GROUP BY event_id
                ) actual_attendance ON e.id = actual_attendance.event_id
                WHERE e.event_date BETWEEN ? AND ?
                    AND e.status = 'completed'
                    AND e.expected_attendance > 0
                ORDER BY e.event_date DESC
            ", [$dateFrom, $dateTo])->fetchAll();
            
            // Top performing events
            $topEvents = $db->executeQuery("
                SELECT 
                    e.name as event_name,
                    e.event_type,
                    e.event_date,
                    actual_attendance.attendance_count as actual_attendance,
                    e.expected_attendance,
                    ROUND((actual_attendance.attendance_count / e.expected_attendance) * 100, 1) as attendance_percentage
                FROM events e
                JOIN (
                    SELECT event_id, COUNT(*) as attendance_count
                    FROM attendance_records
                    WHERE is_present = 1
                    GROUP BY event_id
                ) actual_attendance ON e.id = actual_attendance.event_id
                WHERE e.event_date BETWEEN ? AND ?
                    AND e.status = 'completed'
                    AND e.expected_attendance > 0
                    AND (actual_attendance.attendance_count / e.expected_attendance) >= 0.8
                ORDER BY attendance_percentage DESC, actual_attendance.attendance_count DESC
                LIMIT 10
            ", [$dateFrom, $dateTo])->fetchAll();
            
            $reportData = [
                'attendance_analysis' => $attendanceAnalysis,
                'top_events' => $topEvents
            ];
            break;
            
        case 'departments':
            // Events by department
            $departmentEvents = $db->executeQuery("
                SELECT 
                    COALESCE(d.name, 'No Department') as department_name,
                    d.department_type,
                    COUNT(e.id) as total_events,
                    SUM(CASE WHEN e.status = 'completed' THEN 1 ELSE 0 END) as completed_events,
                    SUM(CASE WHEN e.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_events,
                    AVG(actual_attendance.attendance_count) as avg_attendance,
                    SUM(actual_attendance.attendance_count) as total_attendance
                FROM events e
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN (
                    SELECT event_id, COUNT(*) as attendance_count
                    FROM attendance_records
                    WHERE is_present = 1
                    GROUP BY event_id
                ) actual_attendance ON e.id = actual_attendance.event_id
                WHERE e.event_date BETWEEN ? AND ?
                GROUP BY COALESCE(d.id, 0), COALESCE(d.name, 'No Department')
                ORDER BY total_events DESC
            ", [$dateFrom, $dateTo])->fetchAll();
            
            $reportData = ['department_events' => $departmentEvents];
            break;
            
        case 'calendar':
            // Calendar view data
            $calendarEvents = $db->executeQuery("
                SELECT 
                    e.*,
                    d.name as department_name,
                    COALESCE(actual_attendance.attendance_count, 0) as actual_attendance
                FROM events e
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN (
                    SELECT event_id, COUNT(*) as attendance_count
                    FROM attendance_records
                    WHERE is_present = 1
                    GROUP BY event_id
                ) actual_attendance ON e.id = actual_attendance.event_id
                WHERE e.event_date BETWEEN ? AND ?
                ORDER BY e.event_date ASC, e.start_time ASC
            ", [$dateFrom, $dateTo])->fetchAll();
            
            $reportData = ['calendar_events' => $calendarEvents];
            break;
            
        default:
            $reportData = [];
            break;
    }
    
    // Get event types for filter
    $eventTypes = $db->executeQuery("
        SELECT DISTINCT event_type 
        FROM events 
        WHERE event_date >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
        ORDER BY event_type
    ")->fetchAll();
    
} catch (Exception $e) {
    error_log("Error generating events report: " . $e->getMessage());
    setFlashMessage('error', 'Error generating report: ' . $e->getMessage());
    $eventStats = [];
    $reportData = [];
    $eventTypes = [];
}

include_once '../../includes/header.php';
?>

<!-- Events Reports Content -->
<div class="row">
    <!-- Filter Controls -->
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Report Type</label>
                        <select name="type" class="form-select" onchange="this.form.submit()">
                            <option value="summary" <?php echo $reportType === 'summary' ? 'selected' : ''; ?>>Summary Overview</option>
                            <option value="detailed" <?php echo $reportType === 'detailed' ? 'selected' : ''; ?>>Detailed Events</option>
                            <option value="attendance" <?php echo $reportType === 'attendance' ? 'selected' : ''; ?>>Attendance Analysis</option>
                            <option value="departments" <?php echo $reportType === 'departments' ? 'selected' : ''; ?>>Department Events</option>
                            <option value="calendar" <?php echo $reportType === 'calendar' ? 'selected' : ''; ?>>Calendar View</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Period</label>
                        <select name="period" class="form-select" onchange="this.form.submit()">
                            <option value="this_week" <?php echo $period === 'this_week' ? 'selected' : ''; ?>>This Week</option>
                            <option value="this_month" <?php echo $period === 'this_month' ? 'selected' : ''; ?>>This Month</option>
                            <option value="last_month" <?php echo $period === 'last_month' ? 'selected' : ''; ?>>Last Month</option>
                            <option value="this_quarter" <?php echo $period === 'this_quarter' ? 'selected' : ''; ?>>This Quarter</option>
                            <option value="this_year" <?php echo $period === 'this_year' ? 'selected' : ''; ?>>This Year</option>
                            <option value="last_year" <?php echo $period === 'last_year' ? 'selected' : ''; ?>>Last Year</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Event Type</label>
                        <select name="event_type" class="form-select" onchange="this.form.submit()">
                            <option value="">All Event Types</option>
                            <?php foreach ($eventTypes as $type): ?>
                                <option value="<?php echo $type['event_type']; ?>" <?php echo $eventType === $type['event_type'] ? 'selected' : ''; ?>>
                                    <?php echo getEventTypeDisplay($type['event_type']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="button" class="btn btn-outline-primary me-2" onclick="exportReport()">
                            <i class="fas fa-download me-2"></i>Export
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="printReport()">
                            <i class="fas fa-print me-2"></i>Print
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Summary Statistics -->
    <div class="col-12 mb-4">
        <div class="row g-3">
            <div class="col-md-2">
                <div class="stats-card">
                    <div class="stats-icon bg-primary text-white">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stats-number"><?php echo number_format($eventStats['total_events'] ?? 0); ?></div>
                    <div class="stats-label">Total Events</div>
                </div>
            </div>
            
            <div class="col-md-2">
                <div class="stats-card">
                    <div class="stats-icon bg-success text-white">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stats-number"><?php echo number_format($eventStats['completed_events'] ?? 0); ?></div>
                    <div class="stats-label">Completed</div>
                </div>
            </div>
            
            <div class="col-md-2">
                <div class="stats-card">
                    <div class="stats-icon bg-danger text-white">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stats-number"><?php echo number_format($eventStats['cancelled_events'] ?? 0); ?></div>
                    <div class="stats-label">Cancelled</div>
                </div>
            </div>
            
            <div class="col-md-2">
                <div class="stats-card">
                    <div class="stats-icon bg-info text-white">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stats-number"><?php echo number_format($eventStats['total_attendance'] ?? 0); ?></div>
                    <div class="stats-label">Total Attendance</div>
                </div>
            </div>
            
            <div class="col-md-2">
                <div class="stats-card">
                    <div class="stats-icon bg-warning text-white">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stats-number"><?php echo number_format($eventStats['avg_attendance'] ?? 0); ?></div>
                    <div class="stats-label">Avg Attendance</div>
                </div>
            </div>
            
            <div class="col-md-2">
                <div class="stats-card">
                    <div class="stats-icon bg-secondary text-white">
                        <i class="fas fa-percentage"></i>
                    </div>
                    <div class="stats-number">
                        <?php 
                        $completionRate = 0;
                        if ($eventStats['total_events'] > 0) {
                            $completionRate = round(($eventStats['completed_events'] / $eventStats['total_events']) * 100, 1);
                        }
                        echo $completionRate . '%';
                        ?>
                    </div>
                    <div class="stats-label">Completion Rate</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Content -->
    <div class="col-12">
        <?php if ($reportType === 'summary'): ?>
            <!-- Summary Report -->
            <div class="row">
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-chart-pie me-2"></i>
                                Events by Type
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($reportData['events_by_type'])): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Event Type</th>
                                                <th class="text-end">Total</th>
                                                <th class="text-end">Completed</th>
                                                <th class="text-end">Cancelled</th>
                                                <th class="text-end">Avg Attendance</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($reportData['events_by_type'] as $type): ?>
                                                <tr>
                                                    <td>
                                                        <span class="badge bg-secondary">
                                                            <?php echo getEventTypeDisplay($type['event_type']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-end"><strong><?php echo number_format($type['event_count']); ?></strong></td>
                                                    <td class="text-end text-success"><?php echo number_format($type['completed_count']); ?></td>
                                                    <td class="text-end text-danger"><?php echo number_format($type['cancelled_count']); ?></td>
                                                    <td class="text-end"><?php echo number_format($type['avg_actual'] ?? 0); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-muted py-3">
                                    <i class="fas fa-calendar-times fa-2x mb-3"></i>
                                    <p>No events data available</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-chart-line me-2"></i>
                                Monthly Trends
                            </h6>
                        </div>
                        <div class="card-body">
                            <canvas id="monthlyTrendsChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($reportType === 'detailed'): ?>
            <!-- Detailed Events -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-list me-2"></i>
                        Detailed Events List
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($reportData['detailed_events'])): ?>
                        <div class="table-responsive">
                            <table class="table table-hover data-table">
                                <thead>
                                    <tr>
                                        <th>Event Name</th>
                                        <th>Type</th>
                                        <th>Date & Time</th>
                                        <th>Department</th>
                                        <th>Expected</th>
                                        <th>Actual</th>
                                        <th>Performance</th>
                                        <th>Status</th>
                                        <th>Created By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportData['detailed_events'] as $event): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($event['name']); ?></strong>
                                                <?php if (!empty($event['location'])): ?>
                                                    <br><small class="text-muted">
                                                        <i class="fas fa-map-marker-alt me-1"></i>
                                                        <?php echo htmlspecialchars($event['location']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?php echo getEventTypeDisplay($event['event_type']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo formatDisplayDate($event['event_date']); ?>
                                                <br><small class="text-muted">
                                                    <?php echo date('H:i', strtotime($event['start_time'])); ?>
                                                    <?php if (!empty($event['end_time'])): ?>
                                                        - <?php echo date('H:i', strtotime($event['end_time'])); ?>
                                                    <?php endif; ?>
                                                </small>
                                            </td>
                                            <td><?php echo htmlspecialchars($event['department_name'] ?: '-'); ?></td>
                                            <td class="text-end"><?php echo number_format($event['expected_attendance'] ?? 0); ?></td>
                                            <td class="text-end"><strong><?php echo number_format($event['actual_attendance']); ?></strong></td>
                                            <td class="text-end">
                                                <?php if (!empty($event['attendance_percentage'])): ?>
                                                    <?php
                                                    $percentage = $event['attendance_percentage'];
                                                    $class = $percentage >= 100 ? 'success' : ($percentage >= 80 ? 'warning' : 'danger');
                                                    ?>
                                                    <span class="badge bg-<?php echo $class; ?>">
                                                        <?php echo $percentage; ?>%
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $statusClass = [
                                                    'planned' => 'secondary',
                                                    'ongoing' => 'info',
                                                    'completed' => 'success',
                                                    'cancelled' => 'danger'
                                                ];
                                                $class = $statusClass[$event['status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $class; ?>">
                                                    <?php echo ucfirst($event['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars(($event['first_name'] ?? '') . ' ' . ($event['last_name'] ?? '')); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No Events Found</h5>
                            <p class="text-muted">No events match your current criteria.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($reportType === 'attendance'): ?>
            <!-- Attendance Analysis -->
            <div class="row">
                <div class="col-lg-8 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-chart-bar me-2"></i>
                                Attendance Performance
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($reportData['attendance_analysis'])): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Event</th>
                                                <th>Date</th>
                                                <th class="text-end">Expected</th>
                                                <th class="text-end">Actual</th>
                                                <th class="text-end">Performance</th>
                                                <th>Rating</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($reportData['attendance_analysis'] as $event): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($event['event_name']); ?></strong>
                                                        <br><small class="text-muted"><?php echo getEventTypeDisplay($event['event_type']); ?></small>
                                                    </td>
                                                    <td><?php echo formatDisplayDate($event['event_date']); ?></td>
                                                    <td class="text-end"><?php echo number_format($event['expected_attendance']); ?></td>
                                                    <td class="text-end"><strong><?php echo number_format($event['actual_attendance']); ?></strong></td>
                                                    <td class="text-end">
                                                        <?php if ($event['attendance_percentage']): ?>
                                                            <?php
                                                            $percentage = $event['attendance_percentage'];
                                                            $class = $percentage >= 100 ? 'success' : ($percentage >= 80 ? 'warning' : 'danger');
                                                            ?>
                                                            <span class="badge bg-<?php echo $class; ?>">
                                                                <?php echo $percentage; ?>%
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $ratingClass = [
                                                            'exceeded' => 'success',
                                                            'good' => 'info',
                                                            'average' => 'warning',
                                                            'poor' => 'danger'
                                                        ];
                                                        $class = $ratingClass[$event['performance_rating']] ?? 'secondary';
                                                        ?>
                                                        <span class="badge bg-<?php echo $class; ?>">
                                                            <?php echo ucfirst($event['performance_rating']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No Attendance Data</h5>
                                    <p class="text-muted">No completed events with attendance records found.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-trophy me-2"></i>
                                Top Performing Events
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($reportData['top_events'])): ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach (array_slice($reportData['top_events'], 0, 5) as $index => $event): ?>
                                        <div class="list-group-item border-0 px-0">
                                            <div class="d-flex align-items-center">
                                                <div class="me-3">
                                                    <span class="badge bg-primary rounded-pill"><?php echo $index + 1; ?></span>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($event['event_name']); ?></h6>
                                                    <small class="text-muted">
                                                        <?php echo formatDisplayDate($event['event_date']); ?> • 
                                                        <?php echo number_format($event['actual_attendance']); ?> attendees
                                                    </small>
                                                </div>
                                                <div class="text-end">
                                                    <span class="badge bg-success"><?php echo $event['attendance_percentage']; ?>%</span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-muted py-3">
                                    <i class="fas fa-trophy fa-2x mb-2"></i>
                                    <p>No top performing events found</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($reportType === 'departments'): ?>
            <!-- Department Events -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-sitemap me-2"></i>
                        Events by Department
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($reportData['department_events'])): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Department</th>
                                        <th>Type</th>
                                        <th class="text-end">Total Events</th>
                                        <th class="text-end">Completed</th>
                                        <th class="text-end">Cancelled</th>
                                        <th class="text-end">Total Attendance</th>
                                        <th class="text-end">Avg Attendance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportData['department_events'] as $dept): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($dept['department_name']); ?></strong></td>
                                            <td>
                                                <?php if ($dept['department_type']): ?>
                                                    <span class="badge bg-secondary">
                                                        <?php echo ucwords(str_replace('_', ' ', $dept['department_type'])); ?>
                                                    </span>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end"><strong><?php echo number_format($dept['total_events']); ?></strong></td>
                                            <td class="text-end text-success"><?php echo number_format($dept['completed_events']); ?></td>
                                            <td class="text-end text-danger"><?php echo number_format($dept['cancelled_events']); ?></td>
                                            <td class="text-end"><?php echo number_format($dept['total_attendance'] ?? 0); ?></td>
                                            <td class="text-end"><?php echo number_format($dept['avg_attendance'] ?? 0, 1); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-sitemap fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No Department Data</h5>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($reportType === 'calendar'): ?>
            <!-- Calendar View -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-calendar me-2"></i>
                        Calendar View
                    </h6>
                </div>
                <div class="card-body">
                    <div id="eventsCalendar"></div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($reportType === 'summary' && !empty($reportData['monthly_trends'])): ?>
        // Monthly Trends Chart
        const monthlyTrendsCtx = document.getElementById('monthlyTrendsChart').getContext('2d');
        new Chart(monthlyTrendsCtx, {
            type: 'line',
            data: {
                labels: [<?php echo implode(',', array_map(function($item) { return '"' . date('M Y', strtotime($item['month'] . '-01')) . '"'; }, $reportData['monthly_trends'])); ?>],
                datasets: [
                    {
                        label: 'Total Events',
                        data: [<?php echo implode(',', array_map(function($item) { return $item['total_events']; }, $reportData['monthly_trends'])); ?>],
                        borderColor: '#03045e',
                        backgroundColor: 'rgba(3, 4, 94, 0.1)',
                        tension: 0.4
                    },
                    {
                        label: 'Completed Events',
                        data: [<?php echo implode(',', array_map(function($item) { return $item['completed_events']; }, $reportData['monthly_trends'])); ?>],
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    <?php endif; ?>
});

function exportReport() {
    const reportType = '<?php echo $reportType; ?>';
    const period = '<?php echo $period; ?>';
    const eventType = '<?php echo $eventType; ?>';
    window.open(`export.php?type=events&report_type=${reportType}&period=${period}&event_type=${eventType}&format=excel`, '_blank');
}

function printReport() {
    window.print();
}
</script>

<?php include_once '../../includes/footer.php'; ?><?php
/**
 * Events & Activities Reports
 * Deliverance Church Management System
 * 
 * Generate reports for events, activities, and programs
 */

// Include configuration and functions
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Check authentication and permissions
requireLogin();
if (!hasPermission('reports')) {
    header('Location: ' . BASE_URL . 'modules/dashboard/?error=access_denied');
    exit();
}

// Get report parameters
$reportType = sanitizeInput($_GET['type'] ?? 'summary');
$period = sanitizeInput($_GET['period'] ?? 'this_month');
$eventType = sanitizeInput($_GET['event_type'] ?? '');

// Page configuration
$page_title = 'Events & Activities Reports';
$page_icon = 'fas fa-calendar-alt';
$breadcrumb = [
    ['title' => 'Reports', 'url' => 'index.php'],
    ['title' => 'Events Reports']
];

// Initialize database
$db = Database::getInstance();

// Calculate date range
$dateRanges = [
    'today' => [date('Y-m-d'), date('Y-m-d')],
    'this_week' => [date('Y-m-d', strtotime('monday this week')), date('Y-m-d')],
    'this_month' => [date('Y-m-01'), date('Y-m-d')],
    'last_month' => [date('Y-m-01', strtotime('-1 month')), date('Y-m-t', strtotime('-1 month'))],
    'this_quarter' => [date('Y-m-01', strtotime('first day of this quarter')), date('Y-m-d')],
    'this_year' => [date('Y-01-01'), date('Y-m-d')],
    'last_year' => [date('Y-01-01', strtotime('-1 year')), date('Y-12-31', strtotime('-1 year'))]
];

list($dateFrom, $dateTo) = $dateRanges[$period] ?? $dateRanges['this_month'];

try {
    // Get event statistics
    $eventStats = [
        'total_events' => $db->executeQuery("
            SELECT COUNT(*) as count 
            FROM events 
            WHERE event_date BETWEEN ? AND ?
        ", [$dateFrom, $dateTo])->fetch()['count'],
        
        'completed_events' => $db->executeQuery("
            SELECT COUNT(*) as count 
            FROM events 
            WHERE event_date BETWEEN ? AND ?
            AND status = 'completed'
        ", [$dateFrom, $dateTo])->fetch()['count'],
        
        'cancelled_events' => $db->executeQuery("
            SELECT COUNT(*) as count 
            FROM events 
            WHERE event_date BETWEEN ? AND ?
            AND status = 'cancelled'
        ", [$dateFrom, $dateTo])->fetch()['count'],
        
        'total_attendance' => $db->executeQuery("
            SELECT COUNT(*) as count 
            FROM attendance_records ar
            JOIN events e ON ar.event_id = e.id
            WHERE e.event_date BETWEEN ? AND ?
        ", [$dateFrom, $dateTo])->fetch()['count'],
        
        'avg_attendance' => $db->executeQuery("
            SELECT ROUND(AVG(attendance_count), 0) as avg_attendance
            FROM (
                SELECT COUNT(ar.id) as attendance_count
                FROM events e
                LEFT JOIN attendance_records ar ON e.id = ar.event_id
                WHERE e.event_date BETWEEN ? AND ?
                AND e.status = 'completed'
                GROUP BY e.id
            ) event_attendance
        ", [$dateFrom, $dateTo])->fetch()['avg_attendance']
    ];
    
    // Get report data based on type
    switch ($reportType) {
        case 'summary':
            // Event summary by type
            $eventsByType = $db->executeQuery("
                SELECT 
                    e.event_type,
                    COUNT(*) as event_count,
                    SUM(CASE WHEN e.status = 'completed' THEN 1 ELSE 0 END) as completed_count,
                    SUM(CASE WHEN e.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count,
                    AVG(e.expected_attendance) as avg_expected,
                    AVG(actual_attendance.attendance_count) as avg_actual
                FROM events e
                LEFT JOIN (
                    SELECT event_id, COUNT(*) as attendance_count
                    FROM attendance_records
                    GROUP BY event_id
                ) actual_attendance ON e.id = actual_attendance.event_id
                WHERE e.event_date BETWEEN ? AND ?
                GROUP BY e.event_type
                ORDER BY event_count DESC
            ", [$dateFrom, $dateTo])->fetchAll();
            
            // Monthly event trends
            $monthlyTrends = $db->executeQuery("
                SELECT 
                    DATE_FORMAT(e.event_date, '%Y-%m') as month,
                    COUNT(*) as total_events,
                    SUM(CASE WHEN e.status = 'completed' THEN 1 ELSE 0 END) as completed_events,
                    AVG(actual_attendance.attendance_count) as avg_attendance
                FROM events e
                LEFT JOIN (
                    SELECT event_id, COUNT(*) as attendance_count
                    FROM attendance_records
                    GROUP BY event_id
                ) actual_attendance ON e.id = actual_attendance.event_id
                WHERE e.event_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(e.event_date, '%Y-%m')
                ORDER BY month ASC
            ")->fetchAll();
            
            $reportData = [
                'events_by_type' => $eventsByType,
                'monthly_trends' => $monthlyTrends
            ];
            break;
            
        case 'detailed':
            // Detailed event list with attendance
            $whereConditions = ["e.event_date BETWEEN ? AND ?"];
            $params = [$dateFrom, $dateTo];
            
            if (!empty($eventType)) {
                $whereConditions[] = "e.event_type = ?";
                $params[] = $eventType;
            }
            
            $whereClause = implode(' AND ', $whereConditions);
            
            $detailedEvents = $db->executeQuery("
                SELECT 
                    e.*,
                    d.name as department_name,
                    u.first_name, u.last_name,
                    COALESCE(actual_attendance.attendance_count, 0) as actual_attendance,
                    CASE 
                        WHEN e.expected_attendance > 0 THEN 
                            ROUND((actual_attendance.attendance_count / e.expected_attendance) * 100, 1)
                        ELSE NULL 
                    END as attendance_percentage
                FROM events e
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN users u ON e.created_by = u.id
                LEFT JOIN (
                    SELECT event_id, COUNT(*) as attendance_count
                    FROM attendance_records
                    GROUP BY event_id
                ) actual_attendance ON e.id = actual_attendance.event_id
                WHERE $whereClause
                ORDER BY e.event_date DESC, e.start_time DESC
            ", $params)->fetchAll();
            
            $reportData = ['detailed_events' => $detailedEvents];
            break;
            
        case 'attendance':
            // Event attendance analysis
            $attendanceAnalysis = $db->executeQuery("
                SELECT 
                    e.name as event_name,
                    e.event_type,
                    e.event_date,
                    e.expected_attendance,
                    COALESCE(actual_attendance.attendance_count, 0) as actual_attendance,
                    CASE 
                        WHEN e.expected_attendance > 0 THEN 
                            ROUND((actual_attendance.attendance_count / e