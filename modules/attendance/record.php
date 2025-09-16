<?php
/**
 * Record Attendance Page
 * Deliverance Church Management System
 * 
 * Allows recording attendance for events - both individual member check-ins and bulk counts
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
$page_title = 'Record Attendance';
$page_icon = 'fas fa-calendar-check';
$breadcrumb = [
    ['title' => 'Attendance Management', 'url' => BASE_URL . 'modules/attendance/'],
    ['title' => 'Record Attendance']
];

$db = Database::getInstance();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'record_individual') {
            // Record individual member attendance
            $eventId = (int) $_POST['event_id'];
            $memberAttendance = $_POST['attendance'] ?? [];
            
            if (empty($eventId)) {
                throw new Exception('Please select an event');
            }
            
            $db->beginTransaction();
            
            // Clear existing attendance for this event
            $db->executeQuery("DELETE FROM attendance_records WHERE event_id = ?", [$eventId]);
            
            $attendanceCount = 0;
            foreach ($memberAttendance as $memberId => $isPresent) {
                if ($isPresent) {
                    $db->executeQuery("
                        INSERT INTO attendance_records (event_id, member_id, is_present, recorded_by, check_in_method)
                        VALUES (?, ?, 1, ?, 'manual')
                    ", [$eventId, $memberId, $_SESSION['user_id']]);
                    $attendanceCount++;
                }
            }
            
            // Update event status to completed if it's past
            $event = getRecord('events', 'id', $eventId);
            if ($event && $event['event_date'] <= date('Y-m-d')) {
                updateRecord('events', ['status' => 'completed'], ['id' => $eventId]);
            }
            
            $db->commit();
            
            logActivity('Recorded individual attendance', 'attendance_records', $eventId, null, [
                'event_id' => $eventId,
                'attendance_count' => $attendanceCount
            ]);
            
            setFlashMessage('success', "Attendance recorded successfully for {$attendanceCount} members");
            header('Location: ' . BASE_URL . 'modules/attendance/view_event.php?id=' . $eventId);
            exit();
            
        } elseif ($action === 'record_bulk') {
            // Record bulk attendance counts
            $eventId = (int) $_POST['event_id'];
            $attendanceCounts = $_POST['counts'] ?? [];
            
            if (empty($eventId)) {
                throw new Exception('Please select an event');
            }
            
            $db->beginTransaction();
            
            // Clear existing bulk counts for this event
            $db->executeQuery("DELETE FROM attendance_counts WHERE event_id = ?", [$eventId]);
            
            $totalAttendance = 0;
            foreach ($attendanceCounts as $category => $count) {
                $count = (int) $count;
                if ($count > 0) {
                    $db->executeQuery("
                        INSERT INTO attendance_counts (event_id, attendance_category, count_number, recorded_by)
                        VALUES (?, ?, ?, ?)
                    ", [$eventId, $category, $count, $_SESSION['user_id']]);
                    
                    if ($category === 'total') {
                        $totalAttendance = $count;
                    }
                }
            }
            
            // Update event status to completed if it's past
            $event = getRecord('events', 'id', $eventId);
            if ($event && $event['event_date'] <= date('Y-m-d')) {
                updateRecord('events', ['status' => 'completed'], ['id' => $eventId]);
            }
            
            $db->commit();
            
            logActivity('Recorded bulk attendance', 'attendance_counts', $eventId, null, [
                'event_id' => $eventId,
                'total_attendance' => $totalAttendance,
                'counts' => $attendanceCounts
            ]);
            
            setFlashMessage('success', "Bulk attendance recorded successfully. Total: {$totalAttendance}");
            header('Location: ' . BASE_URL . 'modules/attendance/view_event.php?id=' . $eventId);
            exit();
        }
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollback();
        }
        setFlashMessage('error', 'Error recording attendance: ' . $e->getMessage());
    }
}

// Get events for selection
$events = $db->executeQuery("
    SELECT 
        id,
        name,
        event_type,
        event_date,
        start_time,
        location,
        status
    FROM events 
    WHERE event_date >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
    ORDER BY event_date DESC, start_time DESC
")->fetchAll();

// Get active members for individual attendance
$members = $db->executeQuery("
    SELECT 
        m.id,
        m.member_number,
        m.first_name,
        m.last_name,
        m.phone,
        d.name as department_name
    FROM members m
    LEFT JOIN member_departments md ON m.id = md.member_id AND md.is_active = 1
    LEFT JOIN departments d ON md.department_id = d.id
    WHERE m.membership_status = 'active'
    ORDER BY m.first_name, m.last_name
")->fetchAll();

include '../../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-calendar-check me-2"></i>Record Attendance
                    </h5>
                    <div class="btn-group" role="group">
                        <input type="radio" class="btn-check" name="attendance_method" id="method_individual" value="individual" checked>
                        <label class="btn btn-outline-primary" for="method_individual">
                            <i class="fas fa-user-check me-1"></i>Individual Check-in
                        </label>
                        
                        <input type="radio" class="btn-check" name="attendance_method" id="method_bulk" value="bulk">
                        <label class="btn btn-outline-primary" for="method_bulk">
                            <i class="fas fa-users me-1"></i>Bulk Count
                        </label>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <!-- Event Selection -->
                <div class="row mb-4">
                    <div class="col-lg-6">
                        <label for="selected_event" class="form-label">Select Event *</label>
                        <select class="form-select" id="selected_event" name="event_id" required>
                            <option value="">Choose an event...</option>
                            <?php foreach ($events as $event): ?>
                            <option value="<?php echo $event['id']; ?>" 
                                    data-type="<?php echo $event['event_type']; ?>"
                                    data-date="<?php echo $event['event_date']; ?>"
                                    data-time="<?php echo $event['start_time']; ?>">
                                <?php echo htmlspecialchars($event['name']); ?> - 
                                <?php echo formatDisplayDate($event['event_date']); ?> 
                                <?php echo date('H:i', strtotime($event['start_time'])); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($events)): ?>
                        <div class="form-text text-warning">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            No events available. <a href="<?php echo BASE_URL; ?>modules/attendance/add_event.php">Create an event first</a>.
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-lg-6">
                        <div id="event_details" class="mt-3 p-3 bg-light rounded d-none">
                            <h6 class="text-church-blue mb-2">Event Details</h6>
                            <div id="event_info"></div>
                        </div>
                    </div>
                </div>

                <!-- Individual Attendance Form -->
                <div id="individual_attendance_form" class="attendance-method-form">
                    <form method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="action" value="record_individual">
                        <input type="hidden" name="event_id" id="individual_event_id">
                        
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">Member Check-in</h6>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-success" onclick="selectAllMembers(true)">
                                    <i class="fas fa-check-double me-1"></i>Select All
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="selectAllMembers(false)">
                                    <i class="fas fa-times me-1"></i>Clear All
                                </button>
                            </div>
                        </div>
                        
                        <!-- Search and Filter -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-search"></i>
                                    </span>
                                    <input type="text" class="form-control" id="member_search" placeholder="Search members...">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <select class="form-select" id="department_filter">
                                    <option value="">All Departments</option>
                                    <?php
                                    $departments = getRecords('departments', ['is_active' => 1], 'name ASC');
                                    foreach ($departments as $dept):
                                    ?>
                                    <option value="<?php echo $dept['name']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Members List -->
                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-striped table-hover">
                                <thead class="sticky-top">
                                    <tr>
                                        <th style="width: 50px;">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="select_all_toggle">
                                            </div>
                                        </th>
                                        <th>Member</th>
                                        <th>Member #</th>
                                        <th>Department</th>
                                        <th>Phone</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="members_list">
                                    <?php foreach ($members as $member): ?>
                                    <tr class="member-row" 
                                        data-name="<?php echo strtolower($member['first_name'] . ' ' . $member['last_name']); ?>"
                                        data-department="<?php echo htmlspecialchars($member['department_name'] ?? ''); ?>">
                                        <td>
                                            <div class="form-check">
                                                <input class="form-check-input member-checkbox" 
                                                       type="checkbox" 
                                                       name="attendance[<?php echo $member['id']; ?>]" 
                                                       value="1"
                                                       id="member_<?php echo $member['id']; ?>">
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar bg-church-blue text-white rounded-circle me-2 d-flex align-items-center justify-content-center" 
                                                     style="width: 35px; height: 35px; font-size: 0.8rem;">
                                                    <?php echo strtoupper(substr($member['first_name'], 0, 1) . substr($member['last_name'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></strong>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <code><?php echo htmlspecialchars($member['member_number']); ?></code>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo htmlspecialchars($member['department_name'] ?: 'No Department'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?php echo htmlspecialchars($member['phone'] ?: '-'); ?></small>
                                        </td>
                                        <td>
                                            <span class="attendance-status badge bg-light text-dark">Not Marked</span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-4">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body p-3">
                                            <h6 class="card-title mb-2">Attendance Summary</h6>
                                            <div class="row text-center">
                                                <div class="col-4">
                                                    <div class="h5 text-success mb-1" id="present_count">0</div>
                                                    <small class="text-muted">Present</small>
                                                </div>
                                                <div class="col-4">
                                                    <div class="h5 text-danger mb-1" id="absent_count"><?php echo count($members); ?></div>
                                                    <small class="text-muted">Absent</small>
                                                </div>
                                                <div class="col-4">
                                                    <div class="h5 text-primary mb-1"><?php echo count($members); ?></div>
                                                    <small class="text-muted">Total</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 text-end">
                                    <button type="submit" class="btn btn-church-primary btn-lg">
                                        <i class="fas fa-save me-2"></i>Save Attendance
                                    </button>
                                    <a href="<?php echo BASE_URL; ?>modules/attendance/" class="btn btn-secondary btn-lg ms-2">
                                        <i class="fas fa-arrow-left me-2"></i>Cancel
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Bulk Attendance Form -->
                <div id="bulk_attendance_form" class="attendance-method-form d-none">
                    <form method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="action" value="record_bulk">
                        <input type="hidden" name="event_id" id="bulk_event_id">
                        
                        <h6 class="mb-3">Attendance Count by Category</h6>
                        
                        <div class="row">
                            <?php 
                            $categories = [
                                'men' => ['label' => 'Men', 'icon' => 'fas fa-male', 'color' => 'primary'],
                                'women' => ['label' => 'Women', 'icon' => 'fas fa-female', 'color' => 'danger'],
                                'youth' => ['label' => 'Youth', 'icon' => 'fas fa-running', 'color' => 'success'],
                                'children' => ['label' => 'Children', 'icon' => 'fas fa-child', 'color' => 'warning'],
                                'visitors' => ['label' => 'Visitors', 'icon' => 'fas fa-user-friends', 'color' => 'info'],
                                'total' => ['label' => 'Total Attendance', 'icon' => 'fas fa-users', 'color' => 'dark']
                            ];
                            
                            foreach ($categories as $key => $category):
                            ?>
                            <div class="col-lg-4 col-md-6 mb-3">
                                <div class="card border-<?php echo $category['color']; ?>">
                                    <div class="card-body text-center">
                                        <i class="<?php echo $category['icon']; ?> fa-2x text-<?php echo $category['color']; ?> mb-2"></i>
                                        <h6 class="card-title"><?php echo $category['label']; ?></h6>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-hashtag"></i>
                                            </span>
                                            <input type="number" 
                                                   class="form-control text-center attendance-count-input" 
                                                   name="counts[<?php echo $key; ?>]" 
                                                   value="0" 
                                                   min="0" 
                                                   max="9999"
                                                   data-category="<?php echo $key; ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="mt-4">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Note:</strong> Enter the number of attendees for each category. 
                                        The total should reflect the actual count of people present.
                                    </div>
                                </div>
                                <div class="col-md-6 text-end">
                                    <button type="submit" class="btn btn-church-primary btn-lg">
                                        <i class="fas fa-save me-2"></i>Save Attendance
                                    </button>
                                    <a href="<?php echo BASE_URL; ?>modules/attendance/" class="btn btn-secondary btn-lg ms-2">
                                        <i class="fas fa-arrow-left me-2"></i>Cancel
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Event Creation Modal -->
<div class="modal fade" id="quickEventModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-church-blue text-white">
                <h5 class="modal-title">
                    <i class="fas fa-calendar-plus me-2"></i>Quick Add Event
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="quick_event_form">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="quick_event_name" class="form-label">Event Name *</label>
                        <input type="text" class="form-control" id="quick_event_name" name="name" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <label for="quick_event_type" class="form-label">Event Type *</label>
                            <select class="form-select" id="quick_event_type" name="event_type" required>
                                <?php foreach (EVENT_TYPES as $key => $type): ?>
                                <option value="<?php echo $key; ?>"><?php echo $type; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="quick_event_date" class="form-label">Date *</label>
                            <input type="date" class="form-control" id="quick_event_date" name="event_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <label for="quick_start_time" class="form-label">Start Time *</label>
                            <input type="time" class="form-control" id="quick_start_time" name="start_time" required>
                        </div>
                        <div class="col-md-6">
                            <label for="quick_location" class="form-label">Location</label>
                            <input type="text" class="form-control" id="quick_location" name="location" placeholder="Main Sanctuary">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-church-primary">
                        <i class="fas fa-save me-2"></i>Create & Use Event
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const methodRadios = document.querySelectorAll('input[name="attendance_method"]');
    const individualForm = document.getElementById('individual_attendance_form');
    const bulkForm = document.getElementById('bulk_attendance_form');
    const eventSelect = document.getElementById('selected_event');
    const eventDetails = document.getElementById('event_details');
    const eventInfo = document.getElementById('event_info');
    
    // Method toggle functionality
    methodRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'individual') {
                individualForm.classList.remove('d-none');
                bulkForm.classList.add('d-none');
            } else {
                individualForm.classList.add('d-none');
                bulkForm.classList.remove('d-none');
            }
        });
    });
    
    // Event selection handler
    eventSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        
        if (this.value) {
            // Update hidden event IDs
            document.getElementById('individual_event_id').value = this.value;
            document.getElementById('bulk_event_id').value = this.value;
            
            // Show event details
            const eventDate = selectedOption.dataset.date;
            const eventTime = selectedOption.dataset.time;
            const eventType = selectedOption.dataset.type;
            
            eventInfo.innerHTML = `
                <div class="row small">
                    <div class="col-6">
                        <strong>Date:</strong><br>
                        <i class="fas fa-calendar me-1"></i>${ChurchCMS.formatDate(eventDate)}
                    </div>
                    <div class="col-6">
                        <strong>Time:</strong><br>
                        <i class="fas fa-clock me-1"></i>${eventTime}
                    </div>
                </div>
                <div class="mt-2">
                    <strong>Type:</strong>
                    <span class="badge bg-secondary ms-1">${eventType.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}</span>
                </div>
            `;
            
            eventDetails.classList.remove('d-none');
            
            // Load existing attendance if any
            loadExistingAttendance(this.value);
        } else {
            eventDetails.classList.add('d-none');
            document.getElementById('individual_event_id').value = '';
            document.getElementById('bulk_event_id').value = '';
        }
    });
    
    // Member search functionality
    const memberSearch = document.getElementById('member_search');
    const departmentFilter = document.getElementById('department_filter');
    
    function filterMembers() {
        const searchTerm = memberSearch.value.toLowerCase();
        const selectedDepartment = departmentFilter.value.toLowerCase();
        const memberRows = document.querySelectorAll('.member-row');
        
        memberRows.forEach(row => {
            const name = row.dataset.name;
            const department = row.dataset.department.toLowerCase();
            
            const matchesSearch = searchTerm === '' || name.includes(searchTerm);
            const matchesDepartment = selectedDepartment === '' || department.includes(selectedDepartment);
            
            if (matchesSearch && matchesDepartment) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
        
        updateAttendanceCount();
    }
    
    memberSearch.addEventListener('input', ChurchCMS.debounce(filterMembers, 300));
    departmentFilter.addEventListener('change', filterMembers);
    
    // Select all toggle
    document.getElementById('select_all_toggle').addEventListener('change', function() {
        selectAllMembers(this.checked);
    });
    
    // Member checkbox change handler
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('member-checkbox')) {
            const row = e.target.closest('tr');
            const statusSpan = row.querySelector('.attendance-status');
            
            if (e.target.checked) {
                statusSpan.textContent = 'Present';
                statusSpan.className = 'attendance-status badge bg-success';
                row.classList.add('table-success');
            } else {
                statusSpan.textContent = 'Absent';
                statusSpan.className = 'attendance-status badge bg-danger';
                row.classList.remove('table-success');
            }
            
            updateAttendanceCount();
        }
        
        // Handle bulk count inputs
        if (e.target.classList.contains('attendance-count-input')) {
            updateBulkTotal();
        }
    });
    
    // Update attendance count display
    function updateAttendanceCount() {
        const visibleCheckboxes = document.querySelectorAll('.member-checkbox:not([style*="display: none"])');
        const checkedBoxes = document.querySelectorAll('.member-checkbox:checked');
        const visibleChecked = Array.from(checkedBoxes).filter(cb => 
            !cb.closest('tr').style.display || cb.closest('tr').style.display !== 'none'
        );
        
        const presentCount = visibleChecked.length;
        const totalVisible = visibleCheckboxes.length;
        const absentCount = totalVisible - presentCount;
        
        document.getElementById('present_count').textContent = presentCount;
        document.getElementById('absent_count').textContent = absentCount;
    }
    
    // Update bulk attendance total
    function updateBulkTotal() {
        const inputs = document.querySelectorAll('.attendance-count-input:not([data-category="total"])');
        let total = 0;
        
        inputs.forEach(input => {
            total += parseInt(input.value) || 0;
        });
        
        const totalInput = document.querySelector('.attendance-count-input[data-category="total"]');
        if (totalInput) {
            totalInput.value = total;
        }
    }
    
    // Load existing attendance for selected event
    function loadExistingAttendance(eventId) {
        fetch(`${BASE_URL}api/attendance.php?action=get_attendance&event_id=${eventId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Load individual attendance
                    if (data.individual_attendance) {
                        data.individual_attendance.forEach(record => {
                            const checkbox = document.querySelector(`input[name="attendance[${record.member_id}]"]`);
                            if (checkbox) {
                                checkbox.checked = record.is_present;
                                checkbox.dispatchEvent(new Event('change'));
                            }
                        });
                    }
                    
                    // Load bulk counts
                    if (data.bulk_counts) {
                        data.bulk_counts.forEach(count => {
                            const input = document.querySelector(`input[data-category="${count.attendance_category}"]`);
                            if (input) {
                                input.value = count.count_number;
                            }
                        });
                    }
                }
            })
            .catch(error => {
                console.error('Error loading existing attendance:', error);
            });
    }
    
    // Quick event creation
    document.getElementById('quick_event_form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('created_by', '<?php echo $_SESSION['user_id']; ?>');
        
        ChurchCMS.showLoading('Creating event...');
        
        fetch(`${BASE_URL}api/events.php?action=create`, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            ChurchCMS.hideLoading();
            
            if (data.success) {
                // Add new event to select dropdown
                const option = new Option(
                    `${formData.get('name')} - ${ChurchCMS.formatDate(formData.get('event_date'))} ${formData.get('start_time')}`,
                    data.event_id
                );
                option.dataset.type = formData.get('event_type');
                option.dataset.date = formData.get('event_date');
                option.dataset.time = formData.get('start_time');
                
                eventSelect.add(option);
                eventSelect.value = data.event_id;
                eventSelect.dispatchEvent(new Event('change'));
                
                // Close modal
                bootstrap.Modal.getInstance(document.getElementById('quickEventModal')).hide();
                
                ChurchCMS.showToast('Event created successfully!', 'success');
            } else {
                ChurchCMS.showToast('Error creating event: ' + data.message, 'error');
            }
        })
        .catch(error => {
            ChurchCMS.hideLoading();
            ChurchCMS.showToast('Error creating event. Please try again.', 'error');
        });
    });
});

// Global functions
function selectAllMembers(select) {
    const visibleCheckboxes = document.querySelectorAll('.member-checkbox');
    visibleCheckboxes.forEach(checkbox => {
        const row = checkbox.closest('tr');
        if (!row.style.display || row.style.display !== 'none') {
            checkbox.checked = select;
            checkbox.dispatchEvent(new Event('change'));
        }
    });
    
    document.getElementById('select_all_toggle').checked = select;
}

function showQuickEventModal() {
    const modal = new bootstrap.Modal(document.getElementById('quickEventModal'));
    modal.show();
}

// Auto-save functionality (save draft every 30 seconds)
setInterval(function() {
    if (document.getElementById('selected_event').value) {
        const attendanceData = {};
        document.querySelectorAll('.member-checkbox:checked').forEach(checkbox => {
            const memberId = checkbox.name.match(/\[(\d+)\]/)[1];
            attendanceData[memberId] = 1;
        });
        
        localStorage.setItem('attendance_draft_' + document.getElementById('selected_event').value, 
                           JSON.stringify(attendanceData));
    }
}, 30000);
</script>

<?php include '../../includes/footer.php'; ?>