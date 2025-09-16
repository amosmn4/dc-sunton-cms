case 'activate':
                $event = getRecord('events', 'id', $eventId);
                if (!$event) {
                    throw new Exception('Event not found');
                }
                
                $newStatus = ($event['event_date'] < date('Y-m-d')) ? 'completed' : 'planned';
                
                if (updateRecord('events', ['status' => $newStatus], ['id' => $eventId])) {
                    logActivity('Reactivated event', 'events', $eventId, ['status' => $event['status']], ['status' => $newStatus]);
                    setFlashMessage('success', 'Event reactivated successfully');
                } else {
                    throw new Exception('Failed to reactivate event');
                }
                break;
                
            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        setFlashMessage('error', $e->getMessage());
    }
    
    header('Location: ' . BASE_URL . 'modules/attendance/events.php');
    exit();
}

// Pagination and filtering
$page = (int) ($_GET['page'] ?? 1);
$limit = (int) ($_GET['limit'] ?? DEFAULT_PAGE_SIZE);
$search = $_GET['search'] ?? '';
$eventType = $_GET['event_type'] ?? '';
$status = $_GET['status'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Build WHERE conditions
$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(e.name LIKE ? OR e.description LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if (!empty($eventType)) {
    $whereConditions[] = "e.event_type = ?";
    $params[] = $eventType;
}

if (!empty($status)) {
    $whereConditions[] = "e.status = ?";
    $params[] = $status;
}

if (!empty($dateFrom)) {
    $whereConditions[] = "e.event_date >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $whereConditions[] = "e.event_date <= ?";
    $params[] = $dateTo;
}

$whereClause = empty($whereConditions) ? '' : 'WHERE ' . implode(' AND ', $whereConditions);

// Get total count for pagination
$totalQuery = "SELECT COUNT(*) as total FROM events e {$whereClause}";
$totalResult = $db->executeQuery($totalQuery, $params)->fetch();
$totalRecords = $totalResult['total'];

// Calculate pagination
$pagination = generatePagination($totalRecords, $page, $limit);

// Get events with attendance counts
$offset = $pagination['offset'];
$eventsQuery = "
    SELECT 
        e.*,
        d.name as department_name,
        u.first_name as created_by_name,
        u.last_name as created_by_lastname,
        COALESCE(
            (SELECT SUM(count_number) FROM attendance_counts ac WHERE ac.event_id = e.id AND ac.attendance_category = 'total'),
            (SELECT COUNT(*) FROM attendance_records ar WHERE ar.event_id = e.id AND ar.is_present = 1)
        ) as attendance_count,
        CASE 
            WHEN e.event_date < CURDATE() AND e.status = 'planned' THEN 'overdue'
            ELSE e.status 
        END as display_status
    FROM events e
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN users u ON e.created_by = u.id
    {$whereClause}
    ORDER BY e.event_date DESC, e.start_time DESC
    LIMIT {$limit} OFFSET {$offset}
";

$events = $db->executeQuery($eventsQuery, $params)->fetchAll();

// Get event statistics
$stats = $db->executeQuery("
    SELECT 
        COUNT(*) as total_events,
        SUM(CASE WHEN event_date >= CURDATE() THEN 1 ELSE 0 END) as upcoming_events,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_events,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_events,
        SUM(CASE WHEN event_date < CURDATE() AND status = 'planned' THEN 1 ELSE 0 END) as overdue_events
    FROM events
")->fetch();

// Page actions
$page_actions = [
    [
        'title' => 'Add New Event',
        'url' => BASE_URL . 'modules/attendance/add_event.php',
        'icon' => 'fas fa-plus',
        'class' => 'church-primary'
    ],
    [
        'title' => 'Bulk Import',
        'url' => BASE_URL . 'modules/attendance/import_events.php',
        'icon' => 'fas fa-upload',
        'class' => 'success'
    ],
    [
        'title' => 'Export Events',
        'url' => '#',
        'icon' => 'fas fa-download',
        'class' => 'info',
        'onclick' => 'exportEvents()'
    ]
];

include '../../includes/header.php';
?>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card border-left-primary h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <div class="h5 font-weight-bold text-primary mb-1"><?php echo number_format($stats['total_events']); ?></div>
                        <div class="small text-muted">Total Events</div>
                    </div>
                    <i class="fas fa-calendar-alt fa-2x text-primary"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card border-left-success h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <div class="h5 font-weight-bold text-success mb-1"><?php echo number_format($stats['upcoming_events']); ?></div>
                        <div class="small text-muted">Upcoming</div>
                    </div>
                    <i class="fas fa-calendar-week fa-2x text-success"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card border-left-info h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <div class="h5 font-weight-bold text-info mb-1"><?php echo number_format($stats['completed_events']); ?></div>
                        <div class="small text-muted">Completed</div>
                    </div>
                    <i class="fas fa-calendar-check fa-2x text-info"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card border-left-secondary h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <div class="h5 font-weight-bold text-secondary mb-1"><?php echo number_format($stats['cancelled_events']); ?></div>
                        <div class="small text-muted">Cancelled</div>
                    </div>
                    <i class="fas fa-calendar-times fa-2x text-secondary"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card border-left-warning h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <div class="h5 font-weight-bold text-warning mb-1"><?php echo number_format($stats['overdue_events']); ?></div>
                        <div class="small text-muted">Overdue</div>
                    </div>
                    <i class="fas fa-exclamation-triangle fa-2x text-warning"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters and Search -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="search" class="form-label">Search Events</label>
                <input type="text" class="form-control" id="search" name="search" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Search by name or description...">
            </div>
            
            <div class="col-md-2">
                <label for="event_type" class="form-label">Event Type</label>
                <select class="form-select" id="event_type" name="event_type">
                    <option value="">All Types</option>
                    <?php foreach (EVENT_TYPES as $key => $type): ?>
                    <option value="<?php echo $key; ?>" <?php echo ($eventType === $key) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($type); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All Statuses</option>
                    <option value="planned" <?php echo ($status === 'planned') ? 'selected' : ''; ?>>Planned</option>
                    <option value="ongoing" <?php echo ($status === 'ongoing') ? 'selected' : ''; ?>>Ongoing</option>
                    <option value="completed" <?php echo ($status === 'completed') ? 'selected' : ''; ?>>Completed</option>
                    <option value="cancelled" <?php echo ($status === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="date_from" class="form-label">Date From</label>
                <input type="date" class="form-control" id="date_from" name="date_from" 
                       value="<?php echo htmlspecialchars($dateFrom); ?>">
            </div>
            
            <div class="col-md-2">
                <label for="date_to" class="form-label">Date To</label>
                <input type="date" class="form-control" id="date_to" name="date_to" 
                       value="<?php echo htmlspecialchars($dateTo); ?>">
            </div>
            
            <div class="col-md-1">
                <label class="form-label d-block">&nbsp;</label>
                <button type="submit" class="btn btn-church-primary">
                    <i class="fas fa-search"></i>
                </button>
                <a href="<?php echo BASE_URL; ?>modules/attendance/events.php" class="btn btn-outline-secondary ms-1">
                    <i class="fas fa-times"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Events Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-calendar-alt me-2"></i>Events List
        </h5>
        <div class="d-flex align-items-center">
            <span class="me-3 small text-muted">
                Showing <?php echo number_format($pagination['offset'] + 1); ?> - 
                <?php echo number_format(min($pagination['offset'] + $limit, $totalRecords)); ?> 
                of <?php echo number_format($totalRecords); ?> events
            </span>
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle btn-sm" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-cog me-1"></i>Actions
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="#" onclick="bulkMarkCompleted()">
                        <i class="fas fa-check me-2"></i>Mark Selected as Completed
                    </a></li>
                    <li><a class="dropdown-item" href="#" onclick="bulkCancel()">
                        <i class="fas fa-times me-2"></i>Cancel Selected
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="#" onclick="bulkDelete()">
                        <i class="fas fa-trash me-2"></i>Delete Selected
                    </a></li>
                </ul>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($events)): ?>
            <div class="text-center py-5">
                <i class="fas fa-calendar-times fa-4x text-muted mb-4"></i>
                <h4 class="text-muted">No Events Found</h4>
                <p class="text-muted mb-4">
                    <?php if (!empty($search) || !empty($eventType) || !empty($status)): ?>
                        No events match your search criteria. Try adjusting your filters.
                    <?php else: ?>
                        You haven't created any events yet. Start by adding your first event.
                    <?php endif; ?>
                </p>
                <a href="<?php echo BASE_URL; ?>modules/attendance/add_event.php" class="btn btn-church-primary">
                    <i class="fas fa-plus me-2"></i>Add First Event
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 40px;">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="select_all_events">
                                </div>
                            </th>
                            <th>Event Details</th>
                            <th>Date & Time</th>
                            <th>Type</th>
                            <th>Department</th>
                            <th>Status</th>
                            <th>Attendance</th>
                            <th>Created By</th>
                            <th style="width: 150px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $event): ?>
                        <tr class="<?php echo ($event['display_status'] === 'overdue') ? 'table-warning' : ''; ?>">
                            <td>
                                <div class="form-check">
                                    <input class="form-check-input event-checkbox" type="checkbox" 
                                           value="<?php echo $event['id']; ?>" name="selected_events[]">
                                </div>
                            </td>
                            <td>
                                <div>
                                    <h6 class="mb-1 text-church-blue">
                                        <a href="<?php echo BASE_URL; ?>modules/attendance/view_event.php?id=<?php echo $event['id']; ?>" 
                                           class="text-decoration-none">
                                            <?php echo htmlspecialchars($event['name']); ?>
                                        </a>
                                        <?php if ($event['is_recurring']): ?>
                                            <i class="fas fa-repeat text-muted ms-1" title="Recurring Event"></i>
                                        <?php endif; ?>
                                    </h6>
                                    <?php if ($event['description']): ?>
                                        <p class="mb-1 small text-muted">
                                            <?php echo htmlspecialchars(truncateText($event['description'], 50)); ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if ($event['location']): ?>
                                        <small class="text-muted">
                                            <i class="fas fa-map-marker-alt me-1"></i>
                                            <?php echo htmlspecialchars($event['location']); ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="small">
                                    <div><strong><?php echo formatDisplayDate($event['event_date']); ?></strong></div>
                                    <div class="text-muted">
                                        <?php echo date('H:i', strtotime($event['start_time'])); ?>
                                        <?php if ($event['end_time']): ?>
                                            - <?php echo date('H:i', strtotime($event['end_time'])); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-secondary">
                                    <?php echo getEventTypeDisplay($event['event_type']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($event['department_name']): ?>
                                    <span class="badge bg-light text-dark">
                                        <?php echo htmlspecialchars($event['department_name']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $statusClass = [
                                    'planned' => 'bg-primary',
                                    'ongoing' => 'bg-warning',
                                    'completed' => 'bg-success',
                                    'cancelled' => 'bg-danger',
                                    'overdue' => 'bg-warning'
                                ];
                                $statusText = [
                                    'planned' => 'Planned',
                                    'ongoing' => 'Ongoing',
                                    'completed' => 'Completed',
                                    'cancelled' => 'Cancelled',
                                    'overdue' => 'Overdue'
                                ];
                                ?>
                                <span class="badge <?php echo $statusClass[$event['display_status']] ?? 'bg-secondary'; ?>">
                                    <?php echo $statusText[$event['display_status']] ?? ucfirst($event['display_status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($event['attendance_count']): ?>
                                    <strong class="text-success">
                                        <?php echo number_format($event['attendance_count']); ?>
                                    </strong>
                                    <?php if ($event['expected_attendance']): ?>
                                        <small class="text-muted">
                                            / <?php echo number_format($event['expected_attendance']); ?>
                                        </small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="small">
                                    <div><?php echo htmlspecialchars($event['created_by_name'] . ' ' . $event['created_by_lastname']); ?></div>
                                    <div class="text-muted"><?php echo timeAgo($event['created_at']); ?></div>
                                </div>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="<?php echo BASE_URL; ?>modules/attendance/view_event.php?id=<?php echo $event['id']; ?>" 
                                       class="btn btn-outline-primary" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    
                                    <?php if ($event['status'] !== 'cancelled'): ?>
                                        <a href="<?php echo BASE_URL; ?>modules/attendance/record.php?event_id=<?php echo $event['id']; ?>" 
                                           class="btn btn-outline-success" title="Record Attendance">
                                            <i class="fas fa-users"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" 
                                                data-bs-toggle="dropdown" title="More Actions">
                                            <i class="fas fa-ellipsis-h"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <a class="dropdown-item" 
                                                   href="<?php echo BASE_URL; ?>modules/attendance/edit_event.php?id=<?php echo $event['id']; ?>">
                                                    <i class="fas fa-edit me-2"></i>Edit Event
                                                </a>
                                            </li>
                                            <?php if ($event['status'] === 'planned'): ?>
                                                <li>
                                                    <a class="dropdown-item" 
                                                       href="?action=cancel&id=<?php echo $event['id']; ?>"
                                                       onclick="return confirm('Are you sure you want to cancel this event?')">
                                                        <i class="fas fa-times me-2"></i>Cancel Event
                                                    </a>
                                                </li>
                                            <?php elseif ($event['status'] === 'cancelled'): ?>
                                                <li>
                                                    <a class="dropdown-item" 
                                                       href="?action=activate&id=<?php echo $event['id']; ?>">
                                                        <i class="fas fa-redo me-2"></i>Reactivate Event
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                            
                                            <?php if ($event['is_recurring']): ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item" 
                                                       href="<?php echo BASE_URL; ?>modules/attendance/manage_recurring.php?id=<?php echo $event['id']; ?>">
                                                        <i class="fas fa-repeat me-2"></i>Manage Series
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                            
                                            <?php if (hasPermission('admin')): ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item text-danger" 
                                                       href="?action=delete&id=<?php echo $event['id']; ?>"
                                                       onclick="return confirm('Are you sure you want to delete this event? This action cannot be undone.')">
                                                        <i class="fas fa-trash me-2"></i>Delete Event
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="card-footer">
        <?php echo generatePaginationHTML($pagination, '?search=' . urlencode($search) . '&event_type=' . urlencode($eventType) . '&status=' . urlencode($status) . '&date_from=' . urlencode($dateFrom) . '&date_to=' . urlencode($dateTo)); ?>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Select all functionality
    document.getElementById('select_all_events').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.event-checkbox');
        checkboxes.forEach(cb => cb.checked = this.checked);
    });
    
    // Update select all when individual checkboxes change
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('event-checkbox')) {
            const allCheckboxes = document.querySelectorAll('.event-checkbox');
            const checkedCheckboxes = document.querySelectorAll('.event-checkbox:checked');
            document.getElementById('select_all_events').checked = 
                allCheckboxes.length === checkedCheckboxes.length;
        }
    });
});

function getSelectedEvents() {
    const selected = [];
    document.querySelectorAll('.event-checkbox:checked').forEach(cb => {
        selected.push(cb.value);
    });
    return selected;
}

function bulkMarkCompleted() {
    const selected = getSelectedEvents();
    if (selected.length === 0) {
        ChurchCMS.showToast('Please select events to mark as completed', 'warning');
        return;
    }
    
    ChurchCMS.showConfirm(
        `Mark ${selected.length} selected event(s) as completed?`,
        function() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?php echo BASE_URL; ?>api/events.php?action=bulk_update_status';
            
            const statusInput = document.createElement('input');
            statusInput.type = 'hidden';
            statusInput.name = 'status';
            statusInput.value = 'completed';
            form.appendChild(statusInput);
            
            selected.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'event_ids[]';
                input.value = id;
                form.appendChild(input);
            });
            
            document.body.appendChild(form);
            form.submit();
        }
    );
}

function bulkCancel() {
    const selected = getSelectedEvents();
    if (selected.length === 0) {
        ChurchCMS.showToast('Please select events to cancel', 'warning');
        return;
    }
    
    ChurchCMS.showConfirm(
        `Cancel ${selected.length} selected event(s)?`,
        function() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?php echo BASE_URL; ?>api/events.php?action=bulk_update_status';
            
            const statusInput = document.createElement('input');
            statusInput.type = 'hidden';
            statusInput.name = 'status';
            statusInput.value = 'cancelled';
            form.appendChild(statusInput);
            
            selected.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'event_ids[]';
                input.value = id;
                form.appendChild(input);
            });
            
            document.body.appendChild(form);
            form.submit();
        }
    );
}

function bulkDelete() {
    const selected = getSelectedEvents();
    if (selected.length === 0) {
        ChurchCMS.showToast('Please select events to delete', 'warning');
        return;
    }
    
    ChurchCMS.showConfirm(
        `Delete ${selected.length} selected event(s)? This action cannot be undone and will remove all associated attendance records.`,
        function() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?php echo BASE_URL; ?>api/events.php?action=bulk_delete';
            
            selected.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'event_ids[]';
                input.value = id;
                form.appendChild(input);
            });
            
            document.body.appendChild(form);
            form.submit();
        }
    );
}

function exportEvents() {
    const params = new URLSearchParams(window.location.search);
    params.set('action', 'export');
    
    const exportUrl = `<?php echo BASE_URL; ?>api/events.php?${params.toString()}`;
    
    ChurchCMS.showLoading('Preparing export...');
    
    fetch(exportUrl)
        .then(response => response.blob())
        .then(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `events_export_${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
            ChurchCMS.hideLoading();
            ChurchCMS.showToast('Events exported successfully!', 'success');
        })
        .catch(error => {
            ChurchCMS.hideLoading();
            ChurchCMS.showToast('Export failed. Please try again.', 'error');
        });
}

// Auto-refresh overdue events check every 5 minutes
setInterval(function() {
    const now = new Date();
    const today = now.toISOString().split('T')[0];
    
    document.querySelectorAll('tbody tr').forEach(row => {
        const eventDate = row.querySelector('td:nth-child(3)').textContent.trim();
        // Simple date comparison - in production, you'd want more robust date parsing
        if (eventDate < today && !row.classList.contains('table-warning')) {
            row.classList.add('table-warning');
            const statusBadge = row.querySelector('.badge');
            if (statusBadge && statusBadge.textContent === 'Planned') {
                statusBadge.className = 'badge bg-warning';
                statusBadge.textContent = 'Overdue';
            }
        }
    });
}, 300000); // 5 minutes

// Quick filters
function quickFilter(filterType) {
    const currentUrl = new URL(window.location);
    
    switch(filterType) {
        case 'upcoming':
            currentUrl.searchParams.set('date_from', new Date().toISOString().split('T')[0]);
            currentUrl.searchParams.set('status', 'planned');
            break;
        case 'overdue':
            currentUrl.searchParams.set('date_to', new Date(Date.now() - 86400000).toISOString().split('T')[0]);
            currentUrl.searchParams.set('status', 'planned');
            break;
        case 'this_week':
            const startOfWeek = new Date();
            startOfWeek.setDate(startOfWeek.getDate() - startOfWeek.getDay());
            const endOfWeek = new Date(startOfWeek);
            endOfWeek.setDate(startOfWeek.getDate() + 6);
            
            currentUrl.searchParams.set('date_from', startOfWeek.toISOString().split('T')[0]);
            currentUrl.searchParams.set('date_to', endOfWeek.toISOString().split('T')[0]);
            break;
        case 'clear':
            currentUrl.searchParams.delete('search');
            currentUrl.searchParams.delete('event_type');
            currentUrl.searchParams.delete('status');
            currentUrl.searchParams.delete('date_from');
            currentUrl.searchParams.delete('date_to');
            break;
    }
    
    window.location = currentUrl.toString();
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey || e.metaKey) {
        switch(e.key) {
            case 'a': // Select all
                e.preventDefault();
                const selectAll = document.getElementById('select_all_events');
                selectAll.checked = !selectAll.checked;
                selectAll.dispatchEvent(new Event('change'));
                break;
            case 'n': // New event
                e.preventDefault();
                window.location = '<?php echo BASE_URL; ?>modules/attendance/add_event.php';
                break;
            case 'f': // Focus search
                e.preventDefault();
                document.getElementById('search').focus();
                break;
        }
    }
});
</script>

<!-- Quick Action Buttons -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1000;">
    <div class="btn-group-vertical">
        <button type="button" class="btn btn-church-primary rounded-circle mb-2" 
                style="width: 56px; height: 56px;"
                onclick="window.location='<?php echo BASE_URL; ?>modules/attendance/add_event.php'"
                title="Add New Event">
            <i class="fas fa-plus fa-lg"></i>
        </button>
        <div class="btn-group dropup">
            <button type="button" class="btn btn-secondary rounded-circle dropdown-toggle"
                    style="width: 48px; height: 48px;"
                    data-bs-toggle="dropdown" 
                    title="Quick Filters">
                <i class="fas fa-filter"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><h6 class="dropdown-header">Quick Filters</h6></li>
                <li><a class="dropdown-item" href="#" onclick="quickFilter('upcoming')">
                    <i class="fas fa-calendar-week me-2"></i>Upcoming Events
                </a></li>
                <li><a class="dropdown-item" href="#" onclick="quickFilter('overdue')">
                    <i class="fas fa-exclamation-triangle me-2 text-warning"></i>Overdue Events
                </a></li>
                <li><a class="dropdown-item" href="#" onclick="quickFilter('this_week')">
                    <i class="fas fa-calendar me-2"></i>This Week
                </a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#" onclick="quickFilter('clear')">
                    <i class="fas fa-times me-2"></i>Clear Filters
                </a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Help Modal -->
<div class="modal fade" id="helpModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-church-blue text-white">
                <h5 class="modal-title">
                    <i class="fas fa-question-circle me-2"></i>Events Help
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6>Event Statuses</h6>
                <div class="mb-3">
                    <span class="badge bg-primary me-2">Planned</span> Event is scheduled for the future<br>
                    <span class="badge bg-warning me-2">Ongoing</span> Event is currently happening<br>
                    <span class="badge bg-success me-2">Completed</span> Event has finished with recorded attendance<br>
                    <span class="badge bg-danger me-2">Cancelled</span> Event has been cancelled<br>
                    <span class="badge bg-warning me-2">Overdue</span> Past event without recorded attendance
                </div>
                
                <h6>Keyboard Shortcuts</h6>
                <div class="small">
                    <kbd>Ctrl + N</kbd> - Add new event<br>
                    <kbd>Ctrl + A</kbd> - Select all events<br>
                    <kbd>Ctrl + F</kbd> - Focus search box
                </div>
                
                <h6 class="mt-3">Bulk Actions</h6>
                <p class="small">Select multiple events using checkboxes to perform bulk operations like marking as completed, cancelling, or deleting.</p>
                
                <h6>Recurring Events</h6>
                <p class="small">Events marked with <i class="fas fa-repeat"></i> are part of a recurring series. Changes to one event won't affect others in the series unless you choose to update the entire series.</p>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?><?php
/**
 * Events Management Page
 * Deliverance Church Management System
 * 
 * List and manage all events for attendance tracking
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
$page_title = 'Events Management';
$page_icon = 'fas fa-calendar-alt';
$breadcrumb = [
    ['title' => 'Attendance Management', 'url' => BASE_URL . 'modules/attendance/'],
    ['title' => 'Events Management']
];

$db = Database::getInstance();

// Handle actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $eventId = (int) ($_GET['id'] ?? 0);
    
    try {
        switch ($action) {
            case 'delete':
                if (!hasPermission('admin') && $_SESSION['user_role'] !== 'administrator') {
                    throw new Exception('Insufficient permissions to delete events');
                }
                
                $event = getRecord('events', 'id', $eventId);
                if (!$event) {
                    throw new Exception('Event not found');
                }
                
                // Check if event has attendance records
                $hasAttendance = getRecordCount('attendance_records', ['event_id' => $eventId]) > 0 ||
                               getRecordCount('attendance_counts', ['event_id' => $eventId]) > 0;
                
                if ($hasAttendance && !isset($_GET['confirm'])) {
                    setFlashMessage('warning', 'This event has attendance records. <a href="?action=delete&id=' . $eventId . '&confirm=1" class="alert-link">Click here to confirm deletion</a>.');
                } else {
                    // Delete attendance records first
                    deleteRecord('attendance_records', ['event_id' => $eventId]);
                    deleteRecord('attendance_counts', ['event_id' => $eventId]);
                    
                    // Delete event
                    if (deleteRecord('events', ['id' => $eventId])) {
                        logActivity('Deleted event', 'events', $eventId, $event);
                        setFlashMessage('success', 'Event deleted successfully');
                    } else {
                        throw new Exception('Failed to delete event');
                    }
                }
                break;
                
            case 'cancel':
                $event = getRecord('events', 'id', $eventId);
                if (!$event) {
                    throw new Exception('Event not found');
                }
                
                if (updateRecord('events', ['status' => 'cancelled'], ['id' => $eventId])) {
                    logActivity('Cancelled event', 'events', $eventId, ['status' => $event['status']], ['status' => 'cancelled']);
                    setFlashMessage('success', 'Event cancelled successfully');
                } else {
                    throw new Exception('Failed to cancel event');
                }
                break;
                
            case 'activate':
                $event = getRecord('events', 'id', $eventId);
                if (!$event) {
                    throw new Exception('Event not found');
                }

                if (updateRecord('events', ['status' => 'active'], ['id' => $eventId])) {
                    logActivity('Activated event', 'events', $eventId, ['status' => $event['status']], ['status' => 'active']);
                    setFlashMessage('success', 'Event activated successfully');
                } else {
                    throw new Exception('Failed to activate event');
                }
                break;      

            default:
                throw new Exception('Invalid action');

                break;

            }
            exit();

        }
        exit();

    }

    exit();

}

    


