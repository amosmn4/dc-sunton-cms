<?php
/**
 * Bulk Attendance Recording
 * Deliverance Church Management System
 * 
 * Record attendance without individual member selection
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Set page info
$page_title = 'Record Bulk Attendance';
$page_icon = 'fas fa-users-check';
$page_description = 'Record attendance by category counts';

requireLogin();

if (!hasPermission('attendance')) {
    setFlashMessage('error', 'You do not have permission to access this page');
    redirect(BASE_URL . 'modules/dashboard/');
}

$db = Database::getInstance();
$churchInfo = getRecord('church_info', 'id', 1);

// Get events for today and upcoming
$today = date('Y-m-d');
$stmt = $db->executeQuery(
    "SELECT * FROM events WHERE DATE(event_date) >= ? ORDER BY event_date DESC, start_time DESC LIMIT 10",
    [$today]
);
$events = $stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validation = validateInput($_POST, [
        'event_id' => ['required', 'numeric'],
        'men_count' => ['numeric'],
        'women_count' => ['numeric'],
        'youth_count' => ['numeric'],
        'children_count' => ['numeric'],
        'visitors_count' => ['numeric']
    ]);
    
    if (!$validation['valid']) {
        setFlashMessage('error', 'Please fill in all required fields correctly');
    } else {
        try {
            $db->beginTransaction();
            
            $event_id = (int)$_POST['event_id'];
            
            // Define categories
            $categories = [
                'men' => isset($_POST['men_count']) ? (int)$_POST['men_count'] : 0,
                'women' => isset($_POST['women_count']) ? (int)$_POST['women_count'] : 0,
                'youth' => isset($_POST['youth_count']) ? (int)$_POST['youth_count'] : 0,
                'children' => isset($_POST['children_count']) ? (int)$_POST['children_count'] : 0,
                'visitors' => isset($_POST['visitors_count']) ? (int)$_POST['visitors_count'] : 0
            ];
            
            // Insert attendance counts
            $inserted = 0;
            foreach ($categories as $category => $count) {
                if ($count > 0) {
                    $stmt = $db->executeQuery(
                        "INSERT INTO attendance_counts (event_id, attendance_category, count_number, recorded_by, created_at)
                         VALUES (?, ?, ?, ?, NOW())",
                        [$event_id, $category, $count, $_SESSION['user_id']]
                    );
                    
                    if ($stmt->rowCount() > 0) {
                        $inserted++;
                    }
                }
            }
            
            // Calculate total
            $total = array_sum($categories);
            
            // Insert total count
            $db->executeQuery(
                "INSERT INTO attendance_counts (event_id, attendance_category, count_number, recorded_by, created_at)
                 VALUES (?, ?, ?, ?, NOW())",
                [$event_id, 'total', $total, $_SESSION['user_id']]
            );
            
            $db->commit();
            
            logActivity('Record bulk attendance', 'attendance_counts', $event_id, null, $categories);
            
            setFlashMessage('success', 'Attendance recorded successfully! Total: ' . $total . ' people');
            redirect(BASE_URL . 'modules/attendance/');
            
        } catch (Exception $e) {
            $db->rollback();
            error_log("Error recording bulk attendance: " . $e->getMessage());
            setFlashMessage('error', 'Error recording attendance: ' . $e->getMessage());
        }
    }
}

// Include header
include '../../includes/header.php';
?>

<div class="row">
    <div class="col-lg-8 mx-auto">
        <!-- Form Card -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-gradient-church text-white">
                <h5 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>Record Attendance by Count
                </h5>
            </div>
            <div class="card-body p-4">
                <!-- Info Alert -->
                <div class="alert alert-info border-0 mb-4" role="alert">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Bulk Recording:</strong> Use this form to record attendance counts by category instead of individual member check-ins. This is useful for services where you're counting attendees by category.
                </div>

                <form method="POST" action="" class="needs-validation" novalidate>
                    <!-- Select Event -->
                    <div class="mb-4">
                        <label for="event_id" class="form-label fw-bold">
                            <i class="fas fa-calendar me-1 text-church-blue"></i>Select Event
                            <span class="text-danger">*</span>
                        </label>
                        <select class="form-select form-select-lg" id="event_id" name="event_id" required>
                            <option value="">-- Choose an event --</option>
                            <?php foreach ($events as $event): ?>
                                <option value="<?php echo $event['id']; ?>">
                                    <?php echo htmlspecialchars($event['name']); ?> 
                                    (<?php echo formatDisplayDate($event['event_date']); ?> at <?php echo date('H:i', strtotime($event['start_time'])); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Please select an event</div>
                    </div>

                    <!-- Attendance Counts -->
                    <div class="row">
                        <!-- Men -->
                        <div class="col-md-6 mb-3">
                            <label for="men_count" class="form-label fw-bold">
                                <i class="fas fa-mars text-primary me-1"></i>Men
                            </label>
                            <input type="number" class="form-control form-control-lg" id="men_count" name="men_count" 
                                   min="0" value="0" placeholder="0" required>
                            <small class="text-muted">Number of men present</small>
                        </div>

                        <!-- Women -->
                        <div class="col-md-6 mb-3">
                            <label for="women_count" class="form-label fw-bold">
                                <i class="fas fa-venus text-danger me-1"></i>Women
                            </label>
                            <input type="number" class="form-control form-control-lg" id="women_count" name="women_count" 
                                   min="0" value="0" placeholder="0" required>
                            <small class="text-muted">Number of women present</small>
                        </div>

                        <!-- Youth -->
                        <div class="col-md-6 mb-3">
                            <label for="youth_count" class="form-label fw-bold">
                                <i class="fas fa-child text-success me-1"></i>Youth (13-35)
                            </label>
                            <input type="number" class="form-control form-control-lg" id="youth_count" name="youth_count" 
                                   min="0" value="0" placeholder="0" required>
                            <small class="text-muted">Young people aged 13-35</small>
                        </div>

                        <!-- Children -->
                        <div class="col-md-6 mb-3">
                            <label for="children_count" class="form-label fw-bold">
                                <i class="fas fa-babies text-warning me-1"></i>Children (0-12)
                            </label>
                            <input type="number" class="form-control form-control-lg" id="children_count" name="children_count" 
                                   min="0" value="0" placeholder="0" required>
                            <small class="text-muted">Children aged 0-12</small>
                        </div>

                        <!-- Visitors -->
                        <div class="col-md-6 mb-3">
                            <label for="visitors_count" class="form-label fw-bold">
                                <i class="fas fa-user-friends text-info me-1"></i>Visitors
                            </label>
                            <input type="number" class="form-control form-control-lg" id="visitors_count" name="visitors_count" 
                                   min="0" value="0" placeholder="0" required>
                            <small class="text-muted">First time or visiting guests</small>
                        </div>

                        <!-- Total Display -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">
                                <i class="fas fa-users text-church-blue me-1"></i>Total Attendance
                            </label>
                            <div class="input-group input-group-lg">
                                <input type="text" class="form-control fw-bold fs-5 bg-light" id="total_display" 
                                       value="0" disabled>
                                <span class="input-group-text bg-church-blue text-white fw-bold">People</span>
                            </div>
                            <small class="text-muted">Auto-calculated total</small>
                        </div>
                    </div>

                    <!-- Summary Card -->
                    <div class="card bg-light border-0 mb-4">
                        <div class="card-body">
                            <h6 class="card-title text-church-blue">
                                <i class="fas fa-check-circle me-2"></i>Summary
                            </h6>
                            <div id="summary" class="small">
                                <p class="mb-1"><span class="fw-bold">Men:</span> <span id="summary_men">0</span></p>
                                <p class="mb-1"><span class="fw-bold">Women:</span> <span id="summary_women">0</span></p>
                                <p class="mb-1"><span class="fw-bold">Youth:</span> <span id="summary_youth">0</span></p>
                                <p class="mb-1"><span class="fw-bold">Children:</span> <span id="summary_children">0</span></p>
                                <p class="mb-0"><span class="fw-bold">Visitors:</span> <span id="summary_visitors">0</span></p>
                            </div>
                            <hr>
                            <p class="mb-0"><strong class="text-church-blue">Total: <span id="summary_total">0</span> people</strong></p>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="<?php echo BASE_URL; ?>modules/attendance/" class="btn btn-secondary btn-lg">
                            <i class="fas fa-arrow-left me-2"></i>Cancel
                        </a>
                        <button type="submit" class="btn btn-church-primary btn-lg">
                            <i class="fas fa-save me-2"></i>Record Attendance
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const inputs = ['men_count', 'women_count', 'youth_count', 'children_count', 'visitors_count'];
    const totalDisplay = document.getElementById('total_display');
    const summaryElement = document.getElementById('summary');
    
    function updateTotal() {
        let total = 0;
        
        inputs.forEach(inputId => {
            const value = parseInt(document.getElementById(inputId).value) || 0;
            total += value;
            document.getElementById('summary_' + inputId.replace('_count', '')) = value;
        });
        
        totalDisplay.value = total;
        document.getElementById('summary_total').textContent = total;
        
        // Update summary with actual values
        document.getElementById('summary_men').textContent = parseInt(document.getElementById('men_count').value) || 0;
        document.getElementById('summary_women').textContent = parseInt(document.getElementById('women_count').value) || 0;
        document.getElementById('summary_youth').textContent = parseInt(document.getElementById('youth_count').value) || 0;
        document.getElementById('summary_children').textContent = parseInt(document.getElementById('children_count').value) || 0;
        document.getElementById('summary_visitors').textContent = parseInt(document.getElementById('visitors_count').value) || 0;
    }
    
    // Add event listeners to all input fields
    inputs.forEach(inputId => {
        document.getElementById(inputId).addEventListener('input', updateTotal);
    });
    
    // Initialize on load
    updateTotal();
});
</script>

<?php include '../../includes/footer.php'; ?>