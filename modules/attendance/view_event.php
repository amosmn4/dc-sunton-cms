<?php
/**
 * View Event Details Page
 * Deliverance Church Management System
 * 
 * Displays detailed information about an event and its attendance records
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
$event = $db->executeQuery("
    SELECT 
        e.*,
        d.name as department_name,
        d.description as department_description,
        u.first_name as created_by_name,
        u.last_name as created_by_lastname,
        u.email as created_by_email
    FROM events e
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN users u ON e.created_by = u.id
    WHERE e.id = ?
", [$eventId])->fetch();

if (!$event) {
    setFlashMessage('error', 'Event not found');
    header('Location: ' . BASE_URL . 'modules/attendance/events.php');
    exit();
}

// Get attendance statistics
$attendanceStats = $db->executeQuery("
    SELECT 
        -- Individual attendance count
        (SELECT COUNT(*) FROM attendance_records WHERE event_id = ? AND is_present = 1) as individual_present,
        (SELECT COUNT(*) FROM attendance_records WHERE event_id = ? AND is_present = 0) as individual_absent,
        -- Bulk attendance counts
        (SELECT COALESCE(SUM(count_number), 0) FROM attendance_counts WHERE event_id = ? AND attendance_category = 'total') as bulk_total,
        (SELECT COALESCE(SUM(count_number), 0) FROM attendance_counts WHERE event_id = ? AND attendance_category = 'men') as bulk_men,
        (SELECT COALESCE(SUM(count_number), 0) FROM attendance_counts WHERE event_id = ? AND attendance_category = 'women') as bulk_women,
        (SELECT COALESCE(SUM(count_number), 0) FROM attendance_counts WHERE event_id = ? AND attendance_category = 'youth') as bulk_youth,
        (SELECT COALESCE(SUM(count_number), 0) FROM attendance_counts WHERE event_id = ? AND attendance_category = 'children') as bulk_children,
        (SELECT COALESCE(SUM(count_number), 0) FROM attendance_counts WHERE event_id = ? AND attendance_category = 'visitors') as bulk_visitors
", [$eventId, $eventId, $eventId, $eventId, $eventId, $eventId, $eventId, $eventId])->fetch();

// Determine which attendance method was used
$hasIndividualAttendance = $attendanceStats['individual_present'] > 0 || $attendanceStats['individual_absent'] > 0;
$hasBulkAttendance = $attendanceStats['bulk_total'] > 0;

$totalAttendance = $hasIndividualAttendance ? 
    $attendanceStats['individual_present'] : 
    $attendanceStats['bulk_total'];

// Get individual attendance records if available
$individualAttendance = [];
if ($hasIndividualAttendance) {
    $individualAttendance = $db->executeQuery("
        SELECT 
            ar.*,
            m.first_name,
            m.last_name,
            m.member_number,
            m.phone,
            d.name as department_name
        FROM attendance_records ar
        JOIN members m ON ar.member_id = m.id
        LEFT JOIN member_departments md ON m.id = md.member_id AND md.is_active = 1
        LEFT JOIN departments d ON md.department_id = d.id
        WHERE ar.event_id = ?
        ORDER BY ar.is_present DESC, m.first_name, m.last_name
    ", [$eventId])->fetchAll();
}

// Get related events (same series if recurring)
$relatedEvents = [];
if ($event['is_recurring']) {
    $relatedEvents = $db->executeQuery("
        SELECT 
            e.id,
            e.name,
            e.event_date,
            e.start_time,
            e.status,
            COALESCE(
                (SELECT SUM(count_number) FROM attendance_counts ac WHERE ac.event_id = e.id AND ac.attendance_category = 'total'),
                (SELECT COUNT(*) FROM attendance_records ar WHERE ar.event_id = e.id AND ar.is_present = 1)
            ) as attendance_count
        FROM events e
        WHERE e.name = ? AND e.event_type = ? AND e.id != ?
        ORDER BY e.event_date DESC
        LIMIT 10
    ", [$event['name'], $event['event_type'], $eventId])->fetchAll();
}

// Page configuration
$page_title = htmlspecialchars($event['name']);
$page_icon = 'fas fa-calendar-alt';
$breadcrumb = [
    ['title' => 'Attendance Management', 'url' => BASE_URL . 'modules/attendance/'],
    ['title' => 'Events', 'url' => BASE_URL . 'modules/attendance/events.php'],
    ['title' => $event['name']]
];

// Page actions
$page_actions = [];

if ($event['status'] !== 'cancelled') {
    $page_actions[] = [
        'title' => 'Record Attendance',
        'url' => BASE_URL . 'modules/attendance/record.php?event_id=' . $eventId,
        'icon' => 'fas fa-users',
        'class' => 'church-primary'
    ];
}

if (hasPermission('admin') || $_SESSION['user_id'] == $event['created_by']) {
    $page_actions[] = [
        'title' => 'Edit Event',
        'url' => BASE_URL . 'modules/attendance/edit_event.php?id=' . $eventId,
        'icon' => 'fas fa-edit',
        'class' => 'success'
    ];
}

$page_actions[] = [
    'title' => 'Export Attendance',
    'url' => '#',
    'icon' => 'fas fa-download',
    'class' => 'info',
    'onclick' => 'exportAttendance()'
];

include '../../includes/header.php';
?>

<div class="row">
    <!-- Event Details -->
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h5 class="mb-1">
                            <i class="fas fa-calendar-alt me-2 text-church-blue"></i>
                            <?php echo htmlspecialchars($event['name']); ?>
                            <?php if ($event['is_recurring']): ?>
                                <i class="fas fa-repeat text-muted ms-2" title="Recurring Event"></i>
                            <?php endif; ?>
                        </h5>
                        <div class="text-muted">
                            <?php 
                            $statusClass = [
                                'planned' => 'bg-primary',
                                'ongoing' => 'bg-warning',
                                'completed' => 'bg-success',
                                'cancelled' => 'bg-danger'
                            ];
                            $displayStatus = ($event['event_date'] < date('Y-m-d') && $event['status'] === 'planned') ? 'overdue' : $event['status'];
                            $statusColors = array_merge($statusClass, ['overdue' => 'bg-warning']);
                            ?>
                            <span class="badge <?php echo $statusColors[$displayStatus] ?? 'bg-secondary'; ?> me-2">
                                <?php echo ucfirst(str_replace('_', ' ', $displayStatus)); ?>
                            </span>
                            <span class="badge bg-secondary">
                                <?php echo getEventTypeDisplay($event['event_type']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="text-end">
                        <?php if ($totalAttendance > 0): ?>
                            <div class="h3 text-church-red mb-0"><?php echo number_format($totalAttendance); ?></div>
                            <small class="text-muted">Total Attendance</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Basic Information -->
                    <div class="col-md-6">
                        <h6 class="text-church-blue mb-3">Event Information</h6>
                        <table class="table table-borderless table-sm">
                            <tr>
                                <td class="text-muted" style="width: 120px;"><i class="fas fa-calendar me-2"></i>Date:</td>
                                <td><strong><?php echo formatDisplayDate($event['event_date']); ?></strong></td>
                            </tr>
                            <tr>
                                <td class="text-muted"><i class="fas fa-clock me-2"></i>Time:</td>
                                <td>
                                    <strong><?php echo date('H:i', strtotime($event['start_time'])); ?></strong>
                                    <?php if ($event['end_time']): ?>
                                        - <?php echo date('H:i', strtotime($event['end_time'])); ?>
                                        <small class="text-muted">
                                            (<?php 
                                            $duration = (strtotime($event['end_time']) - strtotime($event['start_time'])) / 3600;
                                            echo $duration . ' hours';
                                            ?>)
                                        </small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if ($event['location']): ?>
                            <tr>
                                <td class="text-muted"><i class="fas fa-map-marker-alt me-2"></i>Location:</td>
                                <td><?php echo htmlspecialchars($event['location']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($event['department_name']): ?>
                            <tr>
                                <td class="text-muted"><i class="fas fa-users-cog me-2"></i>Department:</td>
                                <td>
                                    <span class="badge bg-light text-dark">
                                        <?php echo htmlspecialchars($event['department_name']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($event['expected_attendance']): ?>
                            <tr>
                                <td class="text-muted"><i class="fas fa-bullseye me-2"></i>Expected:</td>
                                <td>
                                    <?php echo number_format($event['expected_attendance']); ?> attendees
                                    <?php if ($totalAttendance > 0): ?>
                                        <small class="ms-2">
                                            (<?php echo round(($totalAttendance / $event['expected_attendance']) * 100, 1); ?>% achieved)
                                        </small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                    
                    <!-- Creator and Dates -->
                    <div class="col-md-6">
                        <h6 class="text-church-blue mb-3">Event Details</h6>
                        <table class="table table-borderless table-sm">
                            <tr>
                                <td class="text-muted" style="width: 120px;"><i class="fas fa-user me-2"></i>Created by:</td>
                                <td><?php echo htmlspecialchars($event['created_by_name'] . ' ' . $event['created_by_lastname']); ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted"><i class="fas fa-clock me-2"></i>Created:</td>
                                <td><?php echo formatDisplayDateTime($event['created_at']); ?></td>
                            </tr>
                            <?php if ($event['updated_at'] !== $event['created_at']): ?>
                            <tr>
                                <td class="text-muted"><i class="fas fa-edit me-2"></i>Updated:</td>
                                <td><?php echo formatDisplayDateTime($event['updated_at']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($event['is_recurring']): ?>
                            <tr>
                                <td class="text-muted"><i class="fas fa-repeat me-2"></i>Recurring:</td>
                                <td>
                                    <span class="badge bg-info">
                                        <?php echo ucfirst(str_replace('_', ' ', $event['recurrence_pattern'])); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
                
                <!-- Description -->
                <?php if ($event['description']): ?>
                <div class="mt-3">
                    <h6 class="text-church-blue mb-2">Description</h6>
                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Attendance Records -->
        <?php if ($hasIndividualAttendance || $hasBulkAttendance): ?>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-users me-2"></i>Attendance Records
                </h5>
                <div class="btn-group btn-group-sm">
                    <?php if ($hasIndividualAttendance): ?>
                    <button class="btn btn-outline-primary active" data-attendance-view="individual">
                        <i class="fas fa-list me-1"></i>Individual
                    </button>
                    <?php endif; ?>
                    <?php if ($hasBulkAttendance): ?>
                    <button class="btn btn-outline-primary <?php echo !$hasIndividualAttendance ? 'active' : ''; ?>" data-attendance-view="summary">
                        <i class="fas fa-chart-pie me-1"></i>Summary
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body p-0">
                <!-- Individual Attendance View -->
                <?php if ($hasIndividualAttendance): ?>
                <div id="individual_attendance_view" class="attendance-view">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Member</th>
                                    <th>Member #</th>
                                    <th>Department</th>
                                    <th>Phone</th>
                                    <th>Status</th>
                                    <th>Check-in Time</th>
                                    <th>Method</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($individualAttendance as $record): ?>
                                <tr class="<?php echo $record['is_present'] ? 'table-success' : 'table-light'; ?>">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar bg-<?php echo $record['is_present'] ? 'success' : 'secondary'; ?> text-white rounded-circle me-2" 
                                                 style="width: 32px; height: 32px; font-size: 0.75rem;">
                                                <?php echo strtoupper(substr($record['first_name'], 0, 1) . substr($record['last_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold">
                                                    <?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <code class="small"><?php echo htmlspecialchars($record['member_number']); ?></code>
                                    </td>
                                    <td>
                                        <?php if ($record['department_name']): ?>
                                            <span class="badge bg-light text-dark small">
                                                <?php echo htmlspecialchars($record['department_name']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted">
                                        <?php echo htmlspecialchars($record['phone'] ?: '-'); ?>
                                    </td>
                                    <td>
                                        <?php if ($record['is_present']): ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check me-1"></i>Present
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">
                                                <i class="fas fa-times me-1"></i>Absent
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small">
                                        <?php echo $record['is_present'] ? formatDisplayDateTime($record['check_in_time']) : '-'; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark">
                                            <?php echo ucfirst(str_replace('_', ' ', $record['check_in_method'])); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Summary Stats for Individual -->
                    <div class="border-top p-3">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <div class="h5 text-success mb-1"><?php echo number_format($attendanceStats['individual_present']); ?></div>
                                <small class="text-muted">Present</small>
                            </div>
                            <div class="col-md-3">
                                <div class="h5 text-secondary mb-1"><?php echo number_format($attendanceStats['individual_absent']); ?></div>
                                <small class="text-muted">Absent</small>
                            </div>
                            <div class="col-md-3">
                                <div class="h5 text-primary mb-1"><?php echo number_format($attendanceStats['individual_present'] + $attendanceStats['individual_absent']); ?></div>
                                <small class="text-muted">Total Tracked</small>
                            </div>
                            <div class="col-md-3">
                                <div class="h5 text-church-blue mb-1">
                                    <?php 
                                    $total = $attendanceStats['individual_present'] + $attendanceStats['individual_absent'];
                                    echo $total > 0 ? round(($attendanceStats['individual_present'] / $total) * 100, 1) . '%' : '0%';
                                    ?>
                                </div>
                                <small class="text-muted">Attendance Rate</small>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Bulk Attendance Summary -->
                <?php if ($hasBulkAttendance): ?>
                <div id="summary_attendance_view" class="attendance-view <?php echo $hasIndividualAttendance ? 'd-none' : ''; ?>">
                    <div class="p-4">
                        <div class="row">
                            <?php 
                            $categories = [
                                'total' => ['label' => 'Total Attendance', 'icon' => 'fas fa-users', 'color' => 'primary', 'value' => $attendanceStats['bulk_total']],
                                'men' => ['label' => 'Men', 'icon' => 'fas fa-male', 'color' => 'info', 'value' => $attendanceStats['bulk_men']],
                                'women' => ['label' => 'Women', 'icon' => 'fas fa-female', 'color' => 'danger', 'value' => $attendanceStats['bulk_women']],
                                'youth' => ['label' => 'Youth', 'icon' => 'fas fa-running', 'color' => 'success', 'value' => $attendanceStats['bulk_youth']],
                                'children' => ['label' => 'Children', 'icon' => 'fas fa-child', 'color' => 'warning', 'value' => $attendanceStats['bulk_children']],
                                'visitors' => ['label' => 'Visitors', 'icon' => 'fas fa-user-friends', 'color' => 'secondary', 'value' => $attendanceStats['bulk_visitors']]
                            ];
                            
                            foreach ($categories as $key => $category):
                                if ($category['value'] > 0):
                            ?>
                            <div class="col-lg-4 col-md-6 mb-3">
                                <div class="card border-<?php echo $category['color']; ?> h-100">
                                    <div class="card-body text-center">
                                        <i class="<?php echo $category['icon']; ?> fa-2x text-<?php echo $category['color']; ?> mb-2"></i>
                                        <div class="h4 text-<?php echo $category['color']; ?> mb-1">
                                            <?php echo number_format($category['value']); ?>
                                        </div>
                                        <div class="small text-muted"><?php echo $category['label']; ?></div>
                                    </div>
                                </div>
                            </div>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-users-slash fa-4x text-muted mb-4"></i>
                <h4 class="text-muted">No Attendance Recorded</h4>
                <p class="text-muted mb-4">This event doesn't have any attendance records yet.</p>
                <?php if ($event['status'] !== 'cancelled'): ?>
                    <a href="<?php echo BASE_URL; ?>modules/attendance/record.php?event_id=<?php echo $eventId; ?>" 
                       class="btn btn-church-primary">
                        <i class="fas fa-users me-2"></i>Record Attendance Now
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Sidebar Information -->
    <div class="col-lg-4">
        <!-- Event Actions -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-cogs me-2"></i>Event Actions
                </h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <?php if ($event['status'] !== 'cancelled'): ?>
                        <a href="<?php echo BASE_URL; ?>modules/attendance/record.php?event_id=<?php echo $eventId; ?>" 
                           class="btn btn-church-primary">
                            <i class="fas fa-users me-2"></i>Record Attendance
                        </a>
                        
                        <?php if ($totalAttendance > 0): ?>
                        <a href="<?php echo BASE_URL; ?>modules/attendance/edit_attendance.php?event_id=<?php echo $eventId; ?>" 
                           class="btn btn-warning">
                            <i class="fas fa-edit me-2"></i>Edit Attendance
                        </a>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php if (hasPermission('admin') || $_SESSION['user_id'] == $event['created_by']): ?>
                        <a href="<?php echo BASE_URL; ?>modules/attendance/edit_event.php?id=<?php echo $eventId; ?>" 
                           class="btn btn-success">
                            <i class="fas fa-edit me-2"></i>Edit Event
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($totalAttendance > 0): ?>
                        <button type="button" class="btn btn-info" onclick="exportAttendance()">
                            <i class="fas fa-download me-2"></i>Export Attendance
                        </button>
                    <?php endif; ?>
                    
                    <button type="button" class="btn btn-secondary" onclick="printEventDetails()">
                        <i class="fas fa-print me-2"></i>Print Details
                    </button>
                    
                    <?php if ($event['status'] === 'planned'): ?>
                        <a href="?action=cancel&id=<?php echo $eventId; ?>" 
                           class="btn btn-outline-danger"
                           onclick="return confirm('Are you sure you want to cancel this event?')">
                            <i class="fas fa-times me-2"></i>Cancel Event
                        </a>
                    <?php elseif ($event['status'] === 'cancelled'): ?>
                        <a href="?action=activate&id=<?php echo $eventId; ?>" 
                           class="btn btn-outline-success">
                            <i class="fas fa-redo me-2"></i>Reactivate Event
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Related Events (for recurring events) -->
        <?php if (!empty($relatedEvents)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-repeat me-2"></i>Related Events in Series
                </h6>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach ($relatedEvents as $related): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-bold small">
                                <a href="<?php echo BASE_URL; ?>modules/attendance/view_event.php?id=<?php echo $related['id']; ?>" 
                                   class="text-decoration-none">
                                    <?php echo formatDisplayDate($related['event_date']); ?>
                                </a>
                            </div>
                            <small class="text-muted">
                                <?php echo date('H:i', strtotime($related['start_time'])); ?>
                            </small>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-<?php echo ($related['status'] === 'completed') ? 'success' : 'secondary'; ?> small">
                                <?php echo ucfirst($related['status']); ?>
                            </span>
                            <?php if ($related['attendance_count']): ?>
                                <br><small class="text-muted">
                                    <?php echo number_format($related['attendance_count']); ?> attended
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Event Statistics -->
        <?php if ($totalAttendance > 0): ?>
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>Attendance Statistics
                </h6>
            </div>
            <div class="card-body">
                <?php if ($event['expected_attendance']): ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="small">Attendance Goal</span>
                            <span class="small">
                                <?php echo number_format($totalAttendance); ?> / <?php echo number_format($event['expected_attendance']); ?>
                            </span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar bg-church-blue" 
                                 style="width: <?php echo min(100, ($totalAttendance / $event['expected_attendance']) * 100); ?>%">
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="small">
                    <?php if ($hasIndividualAttendance): ?>
                        <div class="d-flex justify-content-between mb-1">
                            <span>Attendance Rate:</span>
                            <strong class="text-church-blue">
                                <?php 
                                $total = $attendanceStats['individual_present'] + $attendanceStats['individual_absent'];
                                echo $total > 0 ? round(($attendanceStats['individual_present'] / $total) * 100, 1) . '%' : '0%';
                                ?>
                            </strong>
                        </div>
                    <?php endif; ?>
                    
                    <div class="d-flex justify-content-between mb-1">
                        <span>Event Day:</span>
                        <strong><?php echo date('l', strtotime($event['event_date'])); ?></strong>
                    </div>
                    
                    <?php if ($event['end_time']): ?>
                    <div class="d-flex justify-content-between mb-1">
                        <span>Duration:</span>
                        <strong>
                            <?php 
                            $duration = (strtotime($event['end_time']) - strtotime($event['start_time'])) / 3600;
                            echo $duration . ' hours';
                            ?>
                        </strong>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Attendance view toggle
    const attendanceViewButtons = document.querySelectorAll('[data-attendance-view]');
    const attendanceViews = document.querySelectorAll('.attendance-view');
    
    attendanceViewButtons.forEach(button => {
        button.addEventListener('click', function() {
            const viewType = this.dataset.attendanceView;
            
            // Update button states
            attendanceViewButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            
            // Show/hide views
            attendanceViews.forEach(view => {
                if (view.id === `${viewType}_attendance_view`) {
                    view.classList.remove('d-none');
                } else {
                    view.classList.add('d-none');
                }
            });
        });
    });
});

function exportAttendance() {
    const format = prompt('Export format:\n1. Excel (xlsx)\n2. CSV\n3. PDF\n\nEnter 1, 2, or 3:', '1');
    
    if (!format || !['1', '2', '3'].includes(format)) {
        return;
    }
    
    const formatMap = { '1': 'excel', '2': 'csv', '3': 'pdf' };
    const selectedFormat = formatMap[format];
    
    ChurchCMS.showLoading('Preparing export...');
    
    const exportUrl = `<?php echo BASE_URL; ?>api/attendance.php?action=export_attendance&event_id=<?php echo $eventId; ?>&format=${selectedFormat}`;
    
    fetch(exportUrl)
        .then(response => {
            if (!response.ok) throw new Error('Export failed');
            return response.blob();
        })
        .then(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `attendance_<?php echo $event['name']; ?>_<?php echo $event['event_date']; ?>.${selectedFormat === 'excel' ? 'xlsx' : selectedFormat}`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
            
            ChurchCMS.hideLoading();
            ChurchCMS.showToast('Attendance exported successfully!', 'success');
        })
        .catch(error => {
            ChurchCMS.hideLoading();
            ChurchCMS.showToast('Export failed. Please try again.', 'error');
        });
}

function printEventDetails() {
    window.print();
}

// Send SMS to attendees
function sendSMSToAttendees() {
    <?php if ($hasIndividualAttendance): ?>
    const attendeeIds = [<?php echo implode(',', array_column(array_filter($individualAttendance, function($r) { return $r['is_present']; }), 'member_id')); ?>];
    
    if (attendeeIds.length === 0) {
        ChurchCMS.showToast('No attendees to send SMS to', 'warning');
        return;
    }
    
    const message = prompt(`Send SMS to ${attendeeIds.length} attendees.\n\nEnter your message:`, 
        'Thank you for attending <?php echo htmlspecialchars($event['name']); ?> today. God bless you!');
    
    if (message) {
        ChurchCMS.showLoading('Sending SMS...');
        
        fetch(`<?php echo BASE_URL; ?>api/sms.php?action=send_to_members`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                member_ids: attendeeIds,
                message: message,
                event_id: <?php echo $eventId; ?>
            })
        })
        .then(response => response.json())
        .then(data => {
            ChurchCMS.hideLoading();
            if (data.success) {
                ChurchCMS.showToast(`SMS sent to ${data.sent_count} attendees`, 'success');
            } else {
                ChurchCMS.showToast('Failed to send SMS: ' + data.message, 'error');
            }
        })
        .catch(error => {
            ChurchCMS.hideLoading();
            ChurchCMS.showToast('Error sending SMS', 'error');
        });
    }
    <?php else: ?>
    ChurchCMS.showToast('SMS can only be sent when individual attendance is recorded', 'info');
    <?php endif; ?>
}
</script>

<?php include '../../includes/footer.php'; ?>