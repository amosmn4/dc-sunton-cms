<?php
/**
 * View Visitor Details
 * Deliverance Church Management System
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Check authentication and permissions
requireLogin();
if (!hasPermission('visitors')) {
    setFlashMessage('error', 'You do not have permission to access this page.');
    redirect(BASE_URL . 'modules/dashboard/');
}

// Get visitor ID
$visitorId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$visitorId) {
    setFlashMessage('error', 'Invalid visitor ID provided.');
    redirect('index.php');
}

// Fetch visitor details
try {
    $db = Database::getInstance();
    
    // Get visitor with follow-up person and creator info
    $visitorStmt = $db->executeQuery("
        SELECT v.*, 
               CONCAT(m.first_name, ' ', m.last_name) as followup_person_name,
               m.phone as followup_person_phone,
               CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
               u.username as created_by_username
        FROM visitors v 
        LEFT JOIN members m ON v.assigned_followup_person_id = m.id 
        LEFT JOIN users u ON v.created_by = u.id
        WHERE v.id = ?
    ", [$visitorId]);
    
    $visitor = $visitorStmt->fetch();
    
    if (!$visitor) {
        setFlashMessage('error', 'Visitor not found.');
        redirect('index.php');
    }
    
    // Get follow-up history
    $followupStmt = $db->executeQuery("
        SELECT vf.*, 
               CONCAT(u.first_name, ' ', u.last_name) as performed_by_name,
               u.username as performed_by_username
        FROM visitor_followups vf 
        LEFT JOIN users u ON vf.performed_by = u.id
        WHERE vf.visitor_id = ? 
        ORDER BY vf.followup_date DESC, vf.created_at DESC
    ", [$visitorId]);
    
    $followups = $followupStmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Error fetching visitor details: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred while fetching visitor details.');
    redirect('index.php');
}

// Page configuration
$page_title = 'Visitor Details - ' . $visitor['first_name'] . ' ' . $visitor['last_name'];
$page_icon = 'fas fa-user';
$page_description = 'View and manage visitor information and follow-up activities';

// Breadcrumb
$breadcrumb = [
    ['title' => 'Visitors', 'url' => 'index.php'],
    ['title' => $visitor['first_name'] . ' ' . $visitor['last_name']]
];

// Page actions
$page_actions = [
    [
        'title' => 'Edit Visitor',
        'url' => 'edit.php?id=' . $visitorId,
        'icon' => 'fas fa-edit',
        'class' => 'church-primary'
    ],
    [
        'title' => 'Add Follow-up',
        'url' => 'followup.php?visitor_id=' . $visitorId,
        'icon' => 'fas fa-phone',
        'class' => 'success'
    ],
    [
        'title' => 'Send SMS',
        'url' => '../sms/send.php?visitor_id=' . $visitorId,
        'icon' => 'fas fa-sms',
        'class' => 'info'
    ],
    [
        'title' => 'Convert to Member',
        'url' => 'convert_to_member.php?id=' . $visitorId,
        'icon' => 'fas fa-user-check',
        'class' => 'warning'
    ]
];

include '../../includes/header.php';
?>

<!-- Main Content -->
<div class="row">
    <!-- Visitor Information Card -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="fas fa-user me-2"></i>
                    Visitor Information
                </h6>
                <div>
                    <?php
                    $statusClasses = [
                        'new_visitor' => 'bg-info',
                        'follow_up' => 'bg-warning',
                        'regular_attender' => 'bg-success',
                        'converted_member' => 'bg-church-red'
                    ];
                    $statusClass = $statusClasses[$visitor['status']] ?? 'bg-secondary';
                    $statusLabel = VISITOR_STATUS[$visitor['status']] ?? ucfirst($visitor['status']);
                    ?>
                    <span class="badge <?php echo $statusClass; ?>">
                        <?php echo $statusLabel; ?>
                    </span>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Visitor Number:</strong></div>
                            <div class="col-sm-8">
                                <span class="badge bg-secondary"><?php echo htmlspecialchars($visitor['visitor_number']); ?></span>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Full Name:</strong></div>
                            <div class="col-sm-8">
                                <?php echo htmlspecialchars($visitor['first_name'] . ' ' . $visitor['last_name']); ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($visitor['phone'])): ?>
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Phone:</strong></div>
                            <div class="col-sm-8">
                                <i class="fas fa-phone text-muted me-1"></i>
                                <a href="tel:<?php echo htmlspecialchars($visitor['phone']); ?>" class="text-decoration-none">
                                    <?php echo htmlspecialchars($visitor['phone']); ?>
                                </a>
                                <button type="button" class="btn btn-link btn-sm p-0 ms-2" 
                                        onclick="ChurchCMS.copyToClipboard('<?php echo htmlspecialchars($visitor['phone']); ?>')">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($visitor['email'])): ?>
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Email:</strong></div>
                            <div class="col-sm-8">
                                <i class="fas fa-envelope text-muted me-1"></i>
                                <a href="mailto:<?php echo htmlspecialchars($visitor['email']); ?>" class="text-decoration-none">
                                    <?php echo htmlspecialchars($visitor['email']); ?>
                                </a>
                                <button type="button" class="btn btn-link btn-sm p-0 ms-2" 
                                        onclick="ChurchCMS.copyToClipboard('<?php echo htmlspecialchars($visitor['email']); ?>')">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Age Group:</strong></div>
                            <div class="col-sm-8">
                                <span class="badge bg-secondary">
                                    <?php echo VISITOR_AGE_GROUPS[$visitor['age_group']] ?? ucfirst($visitor['age_group']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Gender:</strong></div>
                            <div class="col-sm-8">
                                <?php echo GENDER_OPTIONS[$visitor['gender']] ?? ucfirst($visitor['gender']); ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($visitor['address'])): ?>
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Address:</strong></div>
                            <div class="col-sm-8">
                                <?php echo nl2br(htmlspecialchars($visitor['address'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-4 text-center">
                        <div class="visitor-avatar bg-church-blue text-white rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" 
                             style="width: 100px; height: 100px; font-size: 2.5rem;">
                            <i class="fas fa-user"></i>
                        </div>
                        <h5><?php echo htmlspecialchars($visitor['first_name'] . ' ' . $visitor['last_name']); ?></h5>
                        <p class="text-muted"><?php echo htmlspecialchars($visitor['visitor_number']); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Stats Card -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-chart-line me-2"></i>
                    Visitor Stats
                </h6>
            </div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <div class="stats-number text-church-blue">
                        <?php echo count($followups); ?>
                    </div>
                    <div class="stats-label">Follow-up Contacts</div>
                </div>
                
                <div class="row text-center">
                    <div class="col-6">
                        <div class="border-end">
                            <div class="fw-bold text-success">
                                <?php echo formatDisplayDate($visitor['visit_date']); ?>
                            </div>
                            <small class="text-muted">First Visit</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="fw-bold text-info">
                            <?php 
                            if (!empty($followups)) {
                                $lastFollowup = $followups[0];
                                echo formatDisplayDate($lastFollowup['followup_date']);
                            } else {
                                echo 'No contact';
                            }
                            ?>
                        </div>
                        <small class="text-muted">Last Contact</small>
                    </div>
                </div>
                
                <?php if ($visitor['assigned_followup_person_id']): ?>
                <hr>
                <div class="text-center">
                    <div class="mb-2">
                        <i class="fas fa-user-check text-success me-2"></i>
                        <strong>Follow-up Person</strong>
                    </div>
                    <div><?php echo htmlspecialchars($visitor['followup_person_name']); ?></div>
                    <?php if ($visitor['followup_person_phone']): ?>
                        <small class="text-muted">
                            <a href="tel:<?php echo htmlspecialchars($visitor['followup_person_phone']); ?>" class="text-decoration-none">
                                <?php echo htmlspecialchars($visitor['followup_person_phone']); ?>
                            </a>
                        </small>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Visit Information -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-calendar-check me-2"></i>
                    Visit Information
                </h6>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-sm-5"><strong>Visit Date:</strong></div>
                    <div class="col-sm-7">
                        <?php echo formatDisplayDate($visitor['visit_date']); ?>
                        <small class="text-muted">(<?php echo timeAgo($visitor['visit_date']); ?>)</small>
                    </div>
                </div>
                
                <?php if (!empty($visitor['service_attended'])): ?>
                <div class="row mb-3">
                    <div class="col-sm-5"><strong>Service Attended:</strong></div>
                    <div class="col-sm-7"><?php echo htmlspecialchars($visitor['service_attended']); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($visitor['previous_church'])): ?>
                <div class="row mb-3">
                    <div class="col-sm-5"><strong>Previous Church:</strong></div>
                    <div class="col-sm-7"><?php echo htmlspecialchars($visitor['previous_church']); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($visitor['purpose_of_visit'])): ?>
                <div class="row mb-3">
                    <div class="col-sm-5"><strong>Purpose of Visit:</strong></div>
                    <div class="col-sm-7"><?php echo nl2br(htmlspecialchars($visitor['purpose_of_visit'])); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($visitor['areas_of_interest'])): ?>
                <div class="row mb-3">
                    <div class="col-sm-5"><strong>Areas of Interest:</strong></div>
                    <div class="col-sm-7"><?php echo nl2br(htmlspecialchars($visitor['areas_of_interest'])); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- System Information -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    System Information
                </h6>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-sm-5"><strong>Status:</strong></div>
                    <div class="col-sm-7">
                        <span class="badge <?php echo $statusClass; ?>">
                            <?php echo $statusLabel; ?>
                        </span>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-sm-5"><strong>Created By:</strong></div>
                    <div class="col-sm-7">
                        <?php echo htmlspecialchars($visitor['created_by_name'] ?: 'Unknown'); ?>
                        <?php if ($visitor['created_by_username']): ?>
                            <small class="text-muted">(<?php echo htmlspecialchars($visitor['created_by_username']); ?>)</small>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-sm-5"><strong>Date Added:</strong></div>
                    <div class="col-sm-7">
                        <?php echo formatDisplayDateTime($visitor['created_at']); ?>
                        <small class="text-muted">(<?php echo timeAgo($visitor['created_at']); ?>)</small>
                    </div>
                </div>
                
                <?php if ($visitor['updated_at'] !== $visitor['created_at']): ?>
                <div class="row mb-3">
                    <div class="col-sm-5"><strong>Last Updated:</strong></div>
                    <div class="col-sm-7">
                        <?php echo formatDisplayDateTime($visitor['updated_at']); ?>
                        <small class="text-muted">(<?php echo timeAgo($visitor['updated_at']); ?>)</small>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($visitor['notes'])): ?>
                <div class="row mb-3">
                    <div class="col-sm-5"><strong>Notes:</strong></div>
                    <div class="col-sm-7">
                        <div class="bg-light p-2 rounded">
                            <?php echo nl2br(htmlspecialchars($visitor['notes'])); ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Follow-up History -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="fas fa-history me-2"></i>
                    Follow-up History (<?php echo count($followups); ?> records)
                </h6>
                <a href="followup.php?visitor_id=<?php echo $visitorId; ?>" class="btn btn-success btn-sm">
                    <i class="fas fa-plus me-1"></i>Add Follow-up
                </a>
            </div>
            <div class="card-body">
                <?php if (!empty($followups)): ?>
                    <div class="timeline">
                        <?php foreach ($followups as $index => $followup): ?>
                            <div class="timeline-item">
                                <div class="timeline-marker">
                                    <?php
                                    $followupIcons = [
                                        'phone_call' => 'fas fa-phone',
                                        'visit' => 'fas fa-home',
                                        'sms' => 'fas fa-sms',
                                        'email' => 'fas fa-envelope',
                                        'letter' => 'fas fa-mail-bulk'
                                    ];
                                    $icon = $followupIcons[$followup['followup_type']] ?? 'fas fa-circle';
                                    ?>
                                    <i class="<?php echo $icon; ?> text-church-blue"></i>
                                </div>
                                <div class="timeline-content">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h6 class="mb-1">
                                                <?php echo FOLLOWUP_TYPES[$followup['followup_type']] ?? ucfirst(str_replace('_', ' ', $followup['followup_type'])); ?>
                                                <?php
                                                $followupStatusClasses = [
                                                    'completed' => 'bg-success',
                                                    'scheduled' => 'bg-info',
                                                    'missed' => 'bg-danger'
                                                ];
                                                $followupStatusClass = $followupStatusClasses[$followup['status']] ?? 'bg-secondary';
                                                ?>
                                                <span class="badge <?php echo $followupStatusClass; ?> ms-2">
                                                    <?php echo FOLLOWUP_STATUS[$followup['status']] ?? ucfirst($followup['status']); ?>
                                                </span>
                                            </h6>
                                            <small class="text-muted">
                                                <i class="fas fa-calendar me-1"></i><?php echo formatDisplayDate($followup['followup_date']); ?>
                                                <?php if ($followup['performed_by_name']): ?>
                                                    | <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($followup['performed_by_name']); ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li><a class="dropdown-item" href="edit_followup.php?id=<?php echo $followup['id']; ?>">
                                                    <i class="fas fa-edit me-2"></i>Edit
                                                </a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item text-danger" href="#" onclick="deleteFollowup(<?php echo $followup['id']; ?>)">
                                                    <i class="fas fa-trash me-2"></i>Delete
                                                </a></li>
                                            </ul>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-2">
                                        <strong>Description:</strong><br>
                                        <?php echo nl2br(htmlspecialchars($followup['description'])); ?>
                                    </div>
                                    
                                    <?php if (!empty($followup['outcome'])): ?>
                                    <div class="mb-2">
                                        <strong>Outcome:</strong><br>
                                        <div class="bg-light p-2 rounded">
                                            <?php echo nl2br(htmlspecialchars($followup['outcome'])); ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($followup['next_followup_date']) && $followup['next_followup_date'] !== '0000-00-00'): ?>
                                    <div class="mb-2">
                                        <strong>Next Follow-up:</strong>
                                        <span class="badge bg-warning text-dark">
                                            <?php echo formatDisplayDate($followup['next_followup_date']); ?>
                                        </span>
                                        <?php if ($followup['next_followup_date'] < date('Y-m-d')): ?>
                                            <small class="text-danger">
                                                <i class="fas fa-exclamation-triangle me-1"></i>Overdue
                                            </small>
                                        <?php elseif ($followup['next_followup_date'] === date('Y-m-d')): ?>
                                            <small class="text-warning">
                                                <i class="fas fa-clock me-1"></i>Due today
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($followup['notes'])): ?>
                                    <div class="mb-2">
                                        <strong>Notes:</strong><br>
                                        <small class="text-muted"><?php echo nl2br(htmlspecialchars($followup['notes'])); ?></small>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-phone fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No Follow-up Records</h5>
                        <p class="text-muted">No follow-up activities have been recorded for this visitor yet.</p>
                        <a href="followup.php?visitor_id=<?php echo $visitorId; ?>" class="btn btn-success">
                            <i class="fas fa-plus me-2"></i>Add First Follow-up
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Action Buttons -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body text-center">
                <h6 class="mb-3">Quick Actions</h6>
                <div class="btn-group-vertical btn-group-lg d-md-none">
                    <a href="edit.php?id=<?php echo $visitorId; ?>" class="btn btn-church-primary mb-2">
                        <i class="fas fa-edit me-2"></i>Edit Visitor Information
                    </a>
                    <a href="followup.php?visitor_id=<?php echo $visitorId; ?>" class="btn btn-success mb-2">
                        <i class="fas fa-phone me-2"></i>Add Follow-up Record
                    </a>
                    <?php if (isFeatureEnabled('sms') && !empty($visitor['phone'])): ?>
                    <a href="../sms/send.php?visitor_id=<?php echo $visitorId; ?>" class="btn btn-info mb-2">
                        <i class="fas fa-sms me-2"></i>Send SMS
                    </a>
                    <?php endif; ?>
                    <?php if ($visitor['status'] !== 'converted_member'): ?>
                    <a href="convert_to_member.php?id=<?php echo $visitorId; ?>" class="btn btn-warning mb-2">
                        <i class="fas fa-user-check me-2"></i>Convert to Member
                    </a>
                    <?php endif; ?>
                </div>
                
                <div class="d-none d-md-block">
                    <a href="edit.php?id=<?php echo $visitorId; ?>" class="btn btn-church-primary me-2">
                        <i class="fas fa-edit me-2"></i>Edit Information
                    </a>
                    <a href="followup.php?visitor_id=<?php echo $visitorId; ?>" class="btn btn-success me-2">
                        <i class="fas fa-phone me-2"></i>Add Follow-up
                    </a>
                    <?php if (isFeatureEnabled('sms') && !empty($visitor['phone'])): ?>
                    <a href="../sms/send.php?visitor_id=<?php echo $visitorId; ?>" class="btn btn-info me-2">
                        <i class="fas fa-sms me-2"></i>Send SMS
                    </a>
                    <?php endif; ?>
                    <?php if ($visitor['status'] !== 'converted_member'): ?>
                    <a href="convert_to_member.php?id=<?php echo $visitorId; ?>" class="btn btn-warning me-2">
                        <i class="fas fa-user-check me-2"></i>Convert to Member
                    </a>
                    <?php endif; ?>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to List
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #dee2e6;
}

.timeline-item {
    position: relative;
    margin-bottom: 2rem;
}

.timeline-marker {
    position: absolute;
    left: -23px;
    top: 5px;
    width: 16px;
    height: 16px;
    background: white;
    border: 2px solid var(--church-blue);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
}

.timeline-content {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 1rem;
    margin-left: 20px;
}

.visitor-avatar {
    box-shadow: var(--shadow);
}
</style>

<script>
function deleteFollowup(followupId) {
    if (confirm('Are you sure you want to delete this follow-up record? This action cannot be undone.')) {
        ChurchCMS.showLoading('Deleting follow-up record...');
        
        fetch(`delete_followup.php?id=${followupId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            ChurchCMS.hideLoading();
            if (data.success) {
                ChurchCMS.showToast('Follow-up record deleted successfully', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                ChurchCMS.showToast(data.message || 'Failed to delete follow-up record', 'error');
            }
        })
        .catch(error => {
            ChurchCMS.hideLoading();
            ChurchCMS.showToast('An error occurred while deleting follow-up record', 'error');
            console.error('Error:', error);
        });
    }
}

// Print functionality
function printVisitorDetails() {
    window.print();
}

// Add keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey || e.metaKey) {
        switch(e.key) {
            case 'e':
                e.preventDefault();
                window.location.href = 'edit.php?id=<?php echo $visitorId; ?>';
                break;
            case 'f':
                e.preventDefault();
                window.location.href = 'followup.php?visitor_id=<?php echo $visitorId; ?>';
                break;
            case 'p':
                e.preventDefault();
                printVisitorDetails();
                break;
        }
    }
});

// Show keyboard shortcuts help
document.addEventListener('DOMContentLoaded', function() {
    // Add help tooltip
    const helpText = 'Keyboard shortcuts:\nCtrl+E: Edit visitor\nCtrl+F: Add follow-up\nCtrl+P: Print details';
    document.body.setAttribute('title', helpText);
});
</script>

<?php include '../../includes/footer.php'; ?>