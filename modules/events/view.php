<?php
/**
 * View Event Details Page
 * Deliverance Church Management System
 * 
 * Display complete event information and related data
 */

// Include required files
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Check authentication
requireLogin();

// Check permissions
if (!hasPermission('events') && !in_array($_SESSION['user_role'], ['administrator', 'pastor', 'secretary', 'department_head', 'editor', 'member'])) {
    setFlashMessage('error', 'You do not have permission to view events.');
    redirect(BASE_URL . 'modules/dashboard/');
}

// Get event ID
$event_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$event_id) {
    setFlashMessage('error', 'Invalid event ID.');
    redirect(BASE_URL . 'modules/events/');
}

try {
    $db = Database::getInstance();
    
    // Get event details with related information
    $stmt = $db->executeQuery("
        SELECT e.*, d.name as department_name, d.department_type,
               u.first_name as created_by_name, u.last_name as created_by_lastname,
               u.email as created_by_email
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
    
    // Get attendance statistics
    $attendance_stmt = $db->executeQuery("
        SELECT 
            COUNT(*) as total_registered,
            SUM(CASE WHEN ar.is_present = 1 THEN 1 ELSE 0 END) as present_count,
            SUM(CASE WHEN ar.is_present = 0 THEN 1 ELSE 0 END) as absent_count
        FROM attendance_records ar 
        WHERE ar.event_id = ?
    ", [$event_id]);
    $attendance_stats = $attendance_stmt->fetch();
    
    // Get attendance records with member details
    $attendees_stmt = $db->executeQuery("
        SELECT ar.*, m.first_name, m.last_name, m.phone, d.name as member_department
        FROM attendance_records ar
        LEFT JOIN members m ON ar.member_id = m.id
        LEFT JOIN member_departments md ON m.id = md.member_id AND md.is_active = 1
        LEFT JOIN departments d ON md.department_id = d.id
        WHERE ar.event_id = ? AND ar.is_present = 1
        ORDER BY ar.check_in_time ASC
        LIMIT 50
    ", [$event_id]);
    $attendees = $attendees_stmt->fetchAll();
    
    // Get bulk attendance counts if any
    $bulk_attendance_stmt = $db->executeQuery("
        SELECT * FROM attendance_counts 
        WHERE event_id = ?
        ORDER BY attendance_category
    ", [$event_id]);
    $bulk_attendance = $bulk_attendance_stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Error fetching event details: " . $e->getMessage());
    setFlashMessage('error', 'Error loading event data.');
    redirect(BASE_URL . 'modules/events/');
}

// Page variables
$page_title = htmlspecialchars($event['name']);
$page_icon = 'fas fa-calendar-alt';

// Breadcrumb
$breadcrumb = [
    ['title' => 'Events', 'url' => BASE_URL . 'modules/events/'],
    ['title' => htmlspecialchars($event['name'])]
];

// Page actions
$page_actions = [];
if (in_array($_SESSION['user_role'], ['administrator', 'pastor', 'secretary', 'department_head', 'editor'])) {
    $page_actions[] = [
        'title' => 'Edit Event',
        'url' => BASE_URL . 'modules/events/edit.php?id=' . $event_id,
        'icon' => 'fas fa-edit',
        'class' => 'church-primary'
    ];
    
    if ($event['status'] !== 'completed') {
        $page_actions[] = [
            'title' => 'Record Attendance',
            'url' => BASE_URL . 'modules/attendance/record.php?event_id=' . $event_id,
            'icon' => 'fas fa-calendar-check',
            'class' => 'success'
        ];
    }
    
    $page_actions[] = [
        'title' => 'Duplicate Event',
        'url' => BASE_URL . 'modules/events/duplicate.php?id=' . $event_id,
        'icon' => 'fas fa-copy',
        'class' => 'secondary'
    ];
}

// Include header
include_once '../../includes/header.php';
?>

<!-- Main Content -->
<div class="row">
    <!-- Event Details -->
    <div class="col-lg-8">
        <!-- Main Event Card -->
        <div class="card mb-4">
            <div class="card-header bg-gradient-church">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-1 text-white">
                            <i class="fas fa-calendar-alt me-2"></i><?php echo htmlspecialchars($event['name']); ?>
                        </h4>
                        <div class="text-white-75">
                            <?php
                            $status_icons = [
                                'planned' => 'fas fa-clock',
                                'ongoing' => 'fas fa-play',
                                'completed' => 'fas fa-check-circle',
                                'cancelled' => 'fas fa-ban'
                            ];
                            $status_colors = [
                                'planned' => 'warning',
                                'ongoing' => 'info',
                                'completed' => 'success',
                                'cancelled' => 'danger'
                            ];
                            ?>
                            <span class="badge bg-<?php echo $status_colors[$event['status']] ?? 'secondary'; ?> me-2">
                                <i class="<?php echo $status_icons[$event['status']] ?? 'fas fa-question'; ?> me-1"></i>
                                <?php echo ucfirst($event['status']); ?>
                            </span>
                            <span class="badge bg-white bg-opacity-20">
                                <?php echo getEventTypeDisplay($event['event_type']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="text-white h5 mb-0">
                            <?php echo formatDisplayDate($event['event_date']); ?>
                        </div>
                        <div class="text-white-75">
                            <?php echo date('H:i', strtotime($event['start_time'])); ?>
                            <?php if ($event['end_time']): ?>
                                - <?php echo date('H:i', strtotime($event['end_time'])); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php if ($event['description']): ?>
                    <div class="mb-4">
                        <h6 class="text-church-blue">Description</h6>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-church-blue">Event Details</h6>
                        <table class="table table-borderless table-sm">
                            <tr>
                                <td><strong>Date:</strong></td>
                                <td><?php echo formatDisplayDate($event['event_date']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Time:</strong></td>
                                <td>
                                    <?php echo date('H:i', strtotime($event['start_time'])); ?>
                                    <?php if ($event['end_time']): ?>
                                        - <?php echo date('H:i', strtotime($event['end_time'])); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Location:</strong></td>
                                <td><?php echo htmlspecialchars($event['location']) ?: '<span class="text-muted">Not specified</span>'; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Department:</strong></td>
                                <td>
                                    <?php if ($event['department_name']): ?>
                                        <span class="badge bg-church-blue">
                                            <?php echo htmlspecialchars($event['department_name']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">All Departments</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Expected Attendance:</strong></td>
                                <td><?php echo $event['expected_attendance'] ? number_format($event['expected_attendance']) : '<span class="text-muted">Not set</span>'; ?></td>
                            </tr>
                            <?php if ($event['is_recurring']): ?>
                            <tr>
                                <td><strong>Recurring:</strong></td>
                                <td>
                                    <i class="fas fa-repeat text-church-red me-1"></i>
                                    <?php echo ucfirst($event['recurrence_pattern']); ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="text-church-blue">Administrative Info</h6>
                        <table class="table table-borderless table-sm">
                            <tr>
                                <td><strong>Created By:</strong></td>
                                <td><?php echo htmlspecialchars($event['created_by_name'] . ' ' . $event['created_by_lastname']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Created On:</strong></td>
                                <td><?php echo formatDisplayDateTime($event['created_at']); ?></td>
                            </tr>
                            <?php if ($event['updated_at'] && $event['updated_at'] !== $event['created_at']): ?>
                            <tr>
                                <td><strong>Last Updated:</strong></td>
                                <td><?php echo formatDisplayDateTime($event['updated_at']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <td><strong>Event ID:</strong></td>
                                <td><code>#<?php echo $event['id']; ?></code></td>
                            </tr>
                        </table>
                        
                        <?php if ($event['notes'] && in_array($_SESSION['user_role'], ['administrator', 'pastor', 'secretary'])): ?>
                        <div class="alert alert-info mt-3">
                            <h6 class="alert-heading">
                                <i class="fas fa-sticky-note me-1"></i>Internal Notes
                            </h6>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($event['notes'])); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendance Section -->
        <?php if ($attendance_stats['total_registered'] > 0 || !empty($bulk_attendance)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-users me-2"></i>Attendance Information
                    </h5>
                    <?php if (in_array($_SESSION['user_role'], ['administrator', 'pastor', 'secretary', 'department_head'])): ?>
                    <a href="<?php echo BASE_URL; ?>modules/attendance/record.php?event_id=<?php echo $event_id; ?>" 
                       class="btn btn-church-primary btn-sm">
                        <i class="fas fa-plus me-1"></i>Record Attendance
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <!-- Attendance Statistics -->
                <?php if ($attendance_stats['total_registered'] > 0): ?>
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="text-center p-3 bg-light rounded">
                            <div class="h4 text-church-blue mb-1"><?php echo number_format($attendance_stats['total_registered']); ?></div>
                            <div class="small text-muted">Total Registered</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3 bg-light rounded">
                            <div class="h4 text-success mb-1"><?php echo number_format($attendance_stats['present_count']); ?></div>
                            <div class="small text-muted">Present</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3 bg-light rounded">
                            <div class="h4 text-warning mb-1"><?php echo number_format($attendance_stats['absent_count']); ?></div>
                            <div class="small text-muted">Absent</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3 bg-light rounded">
                            <div class="h4 text-church-red mb-1">
                                <?php echo $attendance_stats['total_registered'] > 0 ? round(($attendance_stats['present_count'] / $attendance_stats['total_registered']) * 100, 1) : 0; ?>%
                            </div>
                            <div class="small text-muted">Attendance Rate</div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Bulk Attendance Data -->
                <?php if (!empty($bulk_attendance)): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <h6 class="text-church-blue">Attendance Summary</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Count</th>
                                        <th>Recorded By</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bulk_attendance as $attendance): ?>
                                    <tr>
                                        <td><?php echo ucfirst($attendance['attendance_category']); ?></td>
                                        <td><span class="badge bg-info"><?php echo number_format($attendance['count_number']); ?></span></td>
                                        <td>Staff</td>
                                        <td><?php echo formatDisplayDateTime($attendance['created_at']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Individual Attendees -->
                <?php if (!empty($attendees)): ?>
                <div class="row">
                    <div class="col-12">
                        <h6 class="text-church-blue">Attendees List</h6>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Department</th>
                                        <th>Phone</th>
                                        <th>Check-in Time</th>
                                        <th>Method</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendees as $attendee): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar bg-church-blue text-white rounded-circle me-2 d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; font-size: 12px;">
                                                    <?php echo strtoupper(substr($attendee['first_name'], 0, 1) . substr($attendee['last_name'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <?php echo htmlspecialchars($attendee['first_name'] . ' ' . $attendee['last_name']); ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($attendee['member_department']): ?>
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($attendee['member_department']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($attendee['phone']) ?: '-'; ?></td>
                                        <td><?php echo formatDisplayDateTime($attendee['check_in_time']); ?></td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo ucfirst(str_replace('_', ' ', $attendee['check_in_method'])); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if (count($attendees) >= 50): ?>
                        <div class="text-center mt-3">
                            <a href="<?php echo BASE_URL; ?>modules/attendance/report.php?event_id=<?php echo $event_id; ?>" 
                               class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-list me-1"></i>View Full Attendance Report
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($attendance_stats['total_registered'] == 0 && empty($bulk_attendance)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                    <h6 class="text-muted">No Attendance Recorded</h6>
                    <p class="text-muted">No attendance has been recorded for this event yet.</p>
                    <?php if (in_array($_SESSION['user_role'], ['administrator', 'pastor', 'secretary', 'department_head']) && $event['status'] !== 'cancelled'): ?>
                    <a href="<?php echo BASE_URL; ?>modules/attendance/record.php?event_id=<?php echo $event_id; ?>" 
                       class="btn btn-church-primary">
                        <i class="fas fa-plus me-1"></i>Record Attendance
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar -->
    <div class="col-lg-4">
        <!-- Quick Actions -->
        <?php if (in_array($_SESSION['user_role'], ['administrator', 'pastor', 'secretary', 'department_head', 'editor'])): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>Quick Actions
                </h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="<?php echo BASE_URL; ?>modules/events/edit.php?id=<?php echo $event_id; ?>" 
                       class="btn btn-church-primary btn-sm">
                        <i class="fas fa-edit me-1"></i>Edit Event
                    </a>
                    
                    <?php if ($event['status'] !== 'completed' && $event['status'] !== 'cancelled'): ?>
                    <a href="<?php echo BASE_URL; ?>modules/attendance/record.php?event_id=<?php echo $event_id; ?>" 
                       class="btn btn-success btn-sm">
                        <i class="fas fa-calendar-check me-1"></i>Record Attendance
                    </a>
                    <?php endif; ?>
                    
                    <a href="<?php echo BASE_URL; ?>modules/events/duplicate.php?id=<?php echo $event_id; ?>" 
                       class="btn btn-secondary btn-sm">
                        <i class="fas fa-copy me-1"></i>Duplicate Event
                    </a>
                    
                    <?php if ($attendance_stats['total_registered'] > 0): ?>
                    <a href="<?php echo BASE_URL; ?>modules/reports/attendance.php?event_id=<?php echo $event_id; ?>" 
                       class="btn btn-info btn-sm">
                        <i class="fas fa-chart-bar me-1"></i>Attendance Report
                    </a>
                    <?php endif; ?>
                    
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="shareEvent()">
                        <i class="fas fa-share me-1"></i>Share Event
                    </button>
                    
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="exportEvent()">
                        <i class="fas fa-download me-1"></i>Export Details
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Event Timeline -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-clock me-2"></i>Event Timeline
                </h6>
            </div>
            <div class="card-body">
                <?php
                $now = new DateTime();
                $event_datetime = new DateTime($event['event_date'] . ' ' . $event['start_time']);
                $time_diff = $now->diff($event_datetime);
                ?>
                
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-marker bg-success"></div>
                        <div class="timeline-content">
                            <h6 class="mb-1">Event Created</h6>
                            <p class="mb-0 text-muted small"><?php echo formatDisplayDateTime($event['created_at']); ?></p>
                        </div>
                    </div>
                    
                    <?php if ($event['updated_at'] && $event['updated_at'] !== $event['created_at']): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker bg-primary"></div>
                        <div class="timeline-content">
                            <h6 class="mb-1">Last Updated</h6>
                            <p class="mb-0 text-muted small"><?php echo formatDisplayDateTime($event['updated_at']); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="timeline-item">
                        <div class="timeline-marker <?php echo $event_datetime > $now ? 'bg-warning' : ($event['status'] === 'completed' ? 'bg-success' : 'bg-info'); ?>"></div>
                        <div class="timeline-content">
                            <h6 class="mb-1">
                                <?php if ($event_datetime > $now): ?>
                                    Event Scheduled
                                <?php elseif ($event['status'] === 'completed'): ?>
                                    Event Completed
                                <?php else: ?>
                                    Event Date
                                <?php endif; ?>
                            </h6>
                            <p class="mb-1"><?php echo formatDisplayDateTime($event['event_date'] . ' ' . $event['start_time']); ?></p>
                            <?php if ($event_datetime > $now): ?>
                                <p class="mb-0 text-muted small">
                                    <?php
                                    if ($time_diff->days > 0) {
                                        echo $time_diff->days . ' days, ' . $time_diff->h . ' hours remaining';
                                    } elseif ($time_diff->h > 0) {
                                        echo $time_diff->h . ' hours, ' . $time_diff->i . ' minutes remaining';
                                    } else {
                                        echo $time_diff->i . ' minutes remaining';
                                    }
                                    ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($attendance_stats['total_registered'] > 0): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker bg-info"></div>
                        <div class="timeline-content">
                            <h6 class="mb-1">Attendance Recorded</h6>
                            <p class="mb-0"><?php echo $attendance_stats['present_count']; ?> attendees</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Related Events -->
        <?php
        try {
            // Get related events (same type or department)
            $related_stmt = $db->executeQuery("
                SELECT id, name, event_date, start_time, status
                FROM events 
                WHERE id != ? 
                AND (event_type = ? OR department_id = ? OR (department_id IS NULL AND ? IS NULL))
                AND event_date >= CURDATE()
                ORDER BY event_date ASC
                LIMIT 5
            ", [$event_id, $event['event_type'], $event['department_id'], $event['department_id']]);
            $related_events = $related_stmt->fetchAll();
            
            if (!empty($related_events)):
        ?>
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-calendar me-2"></i>Related Events
                </h6>
            </div>
            <div class="card-body">
                <?php foreach ($related_events as $related): ?>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <div class="fw-bold small">
                            <a href="<?php echo BASE_URL; ?>modules/events/view.php?id=<?php echo $related['id']; ?>" 
                               class="text-decoration-none">
                                <?php echo htmlspecialchars(truncateText($related['name'], 25)); ?>
                            </a>
                        </div>
                        <div class="text-muted small">
                            <?php echo formatDisplayDate($related['event_date']); ?> | 
                            <?php echo date('H:i', strtotime($related['start_time'])); ?>
                        </div>
                    </div>
                    <span class="badge bg-<?php echo $status_colors[$related['status']] ?? 'secondary'; ?> small">
                        <?php echo ucfirst($related['status']); ?>
                    </span>
                </div>
                <?php endforeach; ?>
                
                <div class="text-center mt-3">
                    <a href="<?php echo BASE_URL; ?>modules/events/?view=list" 
                       class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-list me-1"></i>View All Events
                    </a>
                </div>
            </div>
        </div>
        <?php
            endif;
        } catch (Exception $e) {
            // Silently fail for related events
        }
        ?>

        <!-- Contact Information -->
        <?php if ($event['created_by_email']): ?>
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-envelope me-2"></i>Contact Information
                </h6>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="avatar bg-church-blue text-white rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                        <i class="fas fa-user"></i>
                    </div>
                    <div>
                        <div class="fw-bold"><?php echo htmlspecialchars($event['created_by_name'] . ' ' . $event['created_by_lastname']); ?></div>
                        <div class="small text-muted">Event Organizer</div>
                        <div class="small">
                            <a href="mailto:<?php echo htmlspecialchars($event['created_by_email']); ?>" 
                               class="text-decoration-none">
                                <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($event['created_by_email']); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function shareEvent() {
    const eventName = '<?php echo addslashes($event['name']); ?>';
    const eventDate = '<?php echo formatDisplayDate($event['event_date']); ?>';
    const eventTime = '<?php echo date('H:i', strtotime($event['start_time'])); ?>';
    const eventLocation = '<?php echo addslashes($event['location'] ?: 'TBA'); ?>';
    
    const shareText = `Join us for ${eventName} on ${eventDate} at ${eventTime}. Location: ${eventLocation}`;
    const shareUrl = window.location.href;
    
    if (navigator.share) {
        navigator.share({
            title: eventName,
            text: shareText,
            url: shareUrl
        }).catch(err => console.error('Error sharing:', err));
    } else {
        // Fallback - copy to clipboard
        const fullText = `${shareText}\nMore info: ${shareUrl}`;
        ChurchCMS.copyToClipboard(fullText, 'Event details copied to clipboard!');
    }
}

function exportEvent() {
    const eventId = <?php echo $event_id; ?>;
    window.open(`<?php echo BASE_URL; ?>api/events.php?action=export_event&id=${eventId}`, '_blank');
}

// Update timeline if event is upcoming
<?php if ($event_datetime > $now && $event['status'] !== 'cancelled'): ?>
setInterval(function() {
    // You could implement a real-time countdown here
    // For now, we'll just refresh the page every hour for upcoming events
}, 3600000);
<?php endif; ?>
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
    margin-bottom: 1.5rem;
}

.timeline-item:last-child {
    margin-bottom: 0;
}

.timeline-marker {
    position: absolute;
    left: -1.75rem;
    top: 0.25rem;
    width: 0.75rem;
    height: 0.75rem;
    border-radius: 50%;
    border: 2px solid white;
    box-shadow: 0 0 0 2px var(--church-gray);
}

.timeline-content h6 {
    margin-bottom: 0.25rem;
    color: var(--church-blue);
}

.avatar {
    flex-shrink: 0;
}
</style>

<?php include_once '../../includes/footer.php'; ?>