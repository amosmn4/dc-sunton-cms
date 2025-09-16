<?php
/**
 * View Member Profile
 * Deliverance Church Management System
 * 
 * Display detailed member information and history
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Check authentication and permissions
requireLogin();
if (!hasPermission('members')) {
    setFlashMessage('error', 'You do not have permission to access this page.');
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit();
}

// Get member ID
$member_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($member_id <= 0) {
    setFlashMessage('error', 'Invalid member ID.');
    header('Location: ' . BASE_URL . 'modules/members/');
    exit();
}

try {
    $db = Database::getInstance();
    
    // Get member details with departments
    $member = $db->executeQuery(
        "SELECT m.*, 
                GROUP_CONCAT(DISTINCT CONCAT(d.name, ':', md.role, ':', md.assigned_date) SEPARATOR '|') as departments_info,
                creator.first_name as created_by_name, creator.last_name as created_by_surname
         FROM members m 
         LEFT JOIN member_departments md ON m.id = md.member_id AND md.is_active = 1
         LEFT JOIN departments d ON md.department_id = d.id
         LEFT JOIN users creator ON m.created_by = creator.id
         WHERE m.id = ?
         GROUP BY m.id",
        [$member_id]
    )->fetch();
    
    if (!$member) {
        setFlashMessage('error', 'Member not found.');
        header('Location: ' . BASE_URL . 'modules/members/');
        exit();
    }
    
    // Parse department information
    $departments = [];
    if (!empty($member['departments_info'])) {
        $dept_info = explode('|', $member['departments_info']);
        foreach ($dept_info as $info) {
            $parts = explode(':', $info, 3);
            if (count($parts) === 3) {
                $departments[] = [
                    'name' => $parts[0],
                    'role' => $parts[1],
                    'assigned_date' => $parts[2]
                ];
            }
        }
    }
    
    // Get member statistics
    $member_stats = [
        'attendance_count' => $db->executeQuery(
            "SELECT COUNT(*) FROM attendance_records WHERE member_id = ? AND is_present = 1",
            [$member_id]
        )->fetchColumn(),
        
        'total_donations' => $db->executeQuery(
            "SELECT COALESCE(SUM(amount), 0) FROM income WHERE donor_name = ? OR donor_phone = ?",
            [$member['first_name'] . ' ' . $member['last_name'], $member['phone']]
        )->fetchColumn(),
        
        'last_attendance' => $db->executeQuery(
            "SELECT MAX(ar.check_in_time) FROM attendance_records ar 
             JOIN events e ON ar.event_id = e.id 
             WHERE ar.member_id = ? AND ar.is_present = 1",
            [$member_id]
        )->fetchColumn(),
        
        'years_as_member' => !empty($member['join_date']) ? 
            floor((time() - strtotime($member['join_date'])) / (365 * 24 * 3600)) : 0
    ];
    
    // Get recent attendance history
    $recent_attendance = $db->executeQuery(
        "SELECT e.name as event_name, e.event_type, e.event_date, ar.check_in_time
         FROM attendance_records ar
         JOIN events e ON ar.event_id = e.id
         WHERE ar.member_id = ? AND ar.is_present = 1
         ORDER BY e.event_date DESC, ar.check_in_time DESC
         LIMIT 10",
        [$member_id]
    )->fetchAll();
    
    // Get family relationships
    $family_members = $db->executeQuery(
        "SELECT id, first_name, last_name, 
                CASE 
                    WHEN id = ? THEN 'spouse'
                    WHEN id = ? THEN 'father'
                    WHEN id = ? THEN 'mother'
                    ELSE 'other'
                END as relationship
         FROM members 
         WHERE id IN (?, ?, ?) AND id != ?",
        [
            $member['spouse_member_id'], $member['father_member_id'], $member['mother_member_id'],
            $member['spouse_member_id'], $member['father_member_id'], $member['mother_member_id'],
            $member_id
        ]
    )->fetchAll();
    
    // Get children (members who have this member as parent)
    $children = $db->executeQuery(
        "SELECT id, first_name, last_name, date_of_birth
         FROM members 
         WHERE (father_member_id = ? OR mother_member_id = ?) AND membership_status = 'active'",
        [$member_id, $member_id]
    )->fetchAll();
    
    // Get recent activities
    $recent_activities = $db->executeQuery(
        "SELECT action, table_name, created_at, 
                u.first_name as user_first_name, u.last_name as user_last_name
         FROM activity_logs al
         JOIN users u ON al.user_id = u.id
         WHERE al.record_id = ? AND al.table_name = 'members'
         ORDER BY al.created_at DESC
         LIMIT 10",
        [$member_id]
    )->fetchAll();
    
} catch (Exception $e) {
    error_log("Member view error: " . $e->getMessage());
    setFlashMessage('error', 'Error loading member information.');
    header('Location: ' . BASE_URL . 'modules/members/');
    exit();
}

// Set page variables
$page_title = $member['first_name'] . ' ' . $member['last_name'];
$page_icon = 'fas fa-user';
$page_description = 'Member profile and information';

$breadcrumb = [
    ['title' => 'Members', 'url' => BASE_URL . 'modules/members/'],
    ['title' => $member['first_name'] . ' ' . $member['last_name']]
];

$page_actions = [
    [
        'title' => 'Edit Member',
        'url' => BASE_URL . 'modules/members/edit.php?id=' . $member_id,
        'icon' => 'fas fa-edit',
        'class' => 'primary'
    ],
    [
        'title' => 'Send SMS',
        'url' => BASE_URL . 'modules/sms/send.php?member_id=' . $member_id,
        'icon' => 'fas fa-sms',
        'class' => 'success'
    ],
    [
        'title' => 'Print Profile',
        'url' => BASE_URL . 'modules/members/print.php?id=' . $member_id,
        'icon' => 'fas fa-print',
        'class' => 'info',
        'target' => '_blank'
    ]
];

$additional_js = ['assets/js/members.js'];

// Include header
include '../../includes/header.php';
?>

<!-- Member Profile Content -->
<div class="row">
    <!-- Main Profile Column -->
    <div class="col-lg-8">
        <!-- Basic Information Card -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <!-- Photo Column -->
                    <div class="col-md-3 text-center mb-4 mb-md-0">
                        <div class="position-relative d-inline-block">
                            <?php if (!empty($member['photo'])): ?>
                                <img src="<?php echo BASE_URL . $member['photo']; ?>" 
                                     alt="<?php echo htmlspecialchars($member['first_name']); ?>" 
                                     class="img-fluid rounded-circle shadow"
                                     style="width: 150px; height: 150px; object-fit: cover;">
                            <?php else: ?>
                                <div class="bg-church-blue text-white rounded-circle d-flex align-items-center justify-content-center shadow"
                                     style="width: 150px; height: 150px;">
                                    <i class="fas fa-user fa-4x"></i>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Status Badge -->
                            <span class="position-absolute bottom-0 end-0 translate-middle badge rounded-pill 
                                         bg-<?php echo $member['membership_status'] === 'active' ? 'success' : 'secondary'; ?>">
                                <?php echo ucfirst($member['membership_status']); ?>
                            </span>
                        </div>
                        
                        <!-- Quick Actions -->
                        <div class="mt-3">
                            <?php if (!empty($member['phone'])): ?>
                                <a href="tel:<?php echo $member['phone']; ?>" class="btn btn-sm btn-outline-success me-1">
                                    <i class="fas fa-phone"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php if (!empty($member['email'])): ?>
                                <a href="mailto:<?php echo $member['email']; ?>" class="btn btn-sm btn-outline-info me-1">
                                    <i class="fas fa-envelope"></i>
                                </a>
                            <?php endif; ?>
                            
                            <button class="btn btn-sm btn-outline-primary" onclick="copyMemberInfo()">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Details Column -->
                    <div class="col-md-9">
                        <div class="row">
                            <div class="col-md-6">
                                <h3 class="text-church-blue mb-1">
                                    <?php echo htmlspecialchars($member['first_name']); ?>
                                    <?php if (!empty($member['middle_name'])): ?>
                                        <?php echo htmlspecialchars($member['middle_name']); ?>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($member['last_name']); ?>
                                </h3>
                                <p class="text-muted mb-3">
                                    Member #<?php echo htmlspecialchars($member['member_number']); ?>
                                </p>
                                
                                <!-- Basic Info List -->
                                <div class="info-list">
                                    <?php if (!empty($member['date_of_birth'])): ?>
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="fas fa-birthday-cake text-muted me-3" style="width: 20px;"></i>
                                            <div>
                                                <strong><?php echo formatDisplayDate($member['date_of_birth']); ?></strong>
                                                <small class="text-muted ms-2">(<?php echo calculateAge($member['date_of_birth']); ?> years old)</small>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-<?php echo $member['gender'] === 'male' ? 'mars' : 'venus'; ?> text-muted me-3" style="width: 20px;"></i>
                                        <strong><?php echo ucfirst($member['gender']); ?></strong>
                                        <small class="text-muted ms-2"><?php echo ucfirst($member['marital_status']); ?></small>
                                    </div>
                                    
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-calendar-plus text-muted me-3" style="width: 20px;"></i>
                                        <div>
                                            <strong>Joined <?php echo formatDisplayDate($member['join_date']); ?></strong>
                                            <small class="text-muted ms-2">(<?php echo $member_stats['years_as_member']; ?> years ago)</small>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($member['baptism_date'])): ?>
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="fas fa-water text-muted me-3" style="width: 20px;"></i>
                                            <strong>Baptized <?php echo formatDisplayDate($member['baptism_date']); ?></strong>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Contact & Location -->
                            <div class="col-md-6">
                                <h6 class="text-muted text-uppercase mb-3">Contact Information</h6>
                                
                                <?php if (!empty($member['phone'])): ?>
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-phone text-success me-3" style="width: 20px;"></i>
                                        <a href="tel:<?php echo $member['phone']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($member['phone']); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($member['email'])): ?>
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-envelope text-info me-3" style="width: 20px;"></i>
                                        <a href="mailto:<?php echo $member['email']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($member['email']); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($member['address'])): ?>
                                    <div class="d-flex align-items-start mb-2">
                                        <i class="fas fa-map-marker-alt text-warning me-3 mt-1" style="width: 20px;"></i>
                                        <div><?php echo nl2br(htmlspecialchars($member['address'])); ?></div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($member['occupation'])): ?>
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-briefcase text-muted me-3" style="width: 20px;"></i>
                                        <div><?php echo htmlspecialchars($member['occupation']); ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="fas fa-calendar-check fa-2x text-info mb-2"></i>
                        <h4 class="text-info"><?php echo number_format($member_stats['attendance_count']); ?></h4>
                        <small class="text-muted">Total Attendance</small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="fas fa-hand-holding-heart fa-2x text-success mb-2"></i>
                        <h4 class="text-success"><?php echo formatCurrency($member_stats['total_donations']); ?></h4>
                        <small class="text-muted">Total Donations</small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                        <h4 class="text-warning"><?php echo $member_stats['years_as_member']; ?></h4>
                        <small class="text-muted">Years as Member</small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="fas fa-users fa-2x text-church-blue mb-2"></i>
                        <h4 class="text-church-blue"><?php echo count($departments); ?></h4>
                        <small class="text-muted">Department(s)</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Departments & Roles -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="fas fa-users-cog me-2"></i>Department Assignments
                </h6>
                <?php if (hasPermission('members')): ?>
                    <button class="btn btn-sm btn-outline-primary" onclick="manageDepartments()">
                        <i class="fas fa-edit me-1"></i>Manage
                    </button>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!empty($departments)): ?>
                    <div class="row">
                        <?php foreach ($departments as $dept): ?>
                            <div class="col-md-6 mb-3">
                                <div class="d-flex align-items-center p-3 bg-light rounded">
                                    <div class="avatar bg-church-blue text-white rounded-circle me-3 d-flex align-items-center justify-content-center" 
                                         style="width: 40px; height: 40px;">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($dept['name']); ?></div>
                                        <small class="text-muted">
                                            <?php echo ucfirst($dept['role']); ?> • 
                                            Since <?php echo formatDisplayDate($dept['assigned_date']); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-users fa-2x mb-2 opacity-50"></i>
                        <p class="mb-0">Not assigned to any departments yet.</p>
                        <?php if (hasPermission('members')): ?>
                            <button class="btn btn-sm btn-church-primary mt-2" onclick="manageDepartments()">
                                <i class="fas fa-plus me-1"></i>Assign to Department
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Attendance -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="fas fa-history me-2"></i>Recent Attendance
                </h6>
                <small class="text-muted">
                    Last attended: 
                    <?php echo $member_stats['last_attendance'] ? formatDisplayDateTime($member_stats['last_attendance']) : 'Never'; ?>
                </small>
            </div>
            <div class="card-body">
                <?php if (!empty($recent_attendance)): ?>
                    <div class="timeline">
                        <?php foreach ($recent_attendance as $attendance): ?>
                            <div class="timeline-item">
                                <div class="timeline-marker bg-success"></div>
                                <div class="timeline-content">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($attendance['event_name']); ?></h6>
                                            <small class="text-muted">
                                                <?php echo ucfirst(str_replace('_', ' ', $attendance['event_type'])); ?>
                                            </small>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo formatDisplayDate($attendance['event_date']); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-3 text-muted">
                        <i class="fas fa-calendar fa-2x mb-2 opacity-50"></i>
                        <p class="mb-0">No attendance records found.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Sidebar Column -->
    <div class="col-lg-4">
        <!-- Emergency Contact -->
        <?php if (!empty($member['emergency_contact_name']) || !empty($member['emergency_contact_phone'])): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>Emergency Contact
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($member['emergency_contact_name'])): ?>
                        <div class="d-flex align-items-center mb-2">
                            <i class="fas fa-user text-muted me-3" style="width: 20px;"></i>
                            <div>
                                <strong><?php echo htmlspecialchars($member['emergency_contact_name']); ?></strong>
                                <?php if (!empty($member['emergency_contact_relationship'])): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($member['emergency_contact_relationship']); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($member['emergency_contact_phone'])): ?>
                        <div class="d-flex align-items-center">
                            <i class="fas fa-phone text-success me-3" style="width: 20px;"></i>
                            <a href="tel:<?php echo $member['emergency_contact_phone']; ?>" class="text-decoration-none">
                                <?php echo htmlspecialchars($member['emergency_contact_phone']); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Family Relationships -->
        <?php if (!empty($family_members) || !empty($children)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-home me-2"></i>Family Relationships
                    </h6>
                </div>
                <div class="card-body">
                    <?php foreach ($family_members as $family): ?>
                        <div class="d-flex align-items-center mb-2">
                            <i class="fas fa-user-friends text-muted me-3" style="width: 20px;"></i>
                            <div>
                                <a href="<?php echo BASE_URL; ?>modules/members/view.php?id=<?php echo $family['id']; ?>" 
                                   class="text-decoration-none fw-bold">
                                    <?php echo htmlspecialchars($family['first_name'] . ' ' . $family['last_name']); ?>
                                </a>
                                <br><small class="text-muted"><?php echo ucfirst($family['relationship']); ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php foreach ($children as $child): ?>
                        <div class="d-flex align-items-center mb-2">
                            <i class="fas fa-child text-muted me-3" style="width: 20px;"></i>
                            <div>
                                <a href="<?php echo BASE_URL; ?>modules/members/view.php?id=<?php echo $child['id']; ?>" 
                                   class="text-decoration-none fw-bold">
                                    <?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?>
                                </a>
                                <br><small class="text-muted">
                                    Child<?php echo !empty($child['date_of_birth']) ? ' • ' . calculateAge($child['date_of_birth']) . ' years old' : ''; ?>
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Skills & Talents -->
        <?php if (!empty($member['skills']) || !empty($member['spiritual_gifts'])): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-star me-2"></i>Skills & Gifts
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($member['skills'])): ?>
                        <div class="mb-3">
                            <h6 class="text-muted small text-uppercase">Skills & Talents</h6>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($member['skills'])); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($member['spiritual_gifts'])): ?>
                        <div>
                            <h6 class="text-muted small text-uppercase">Spiritual Gifts</h6>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($member['spiritual_gifts'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Leadership Roles -->
        <?php if (!empty($member['leadership_roles'])): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-crown me-2"></i>Leadership Roles
                    </h6>
                </div>
                <div class="card-body">
                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($member['leadership_roles'])); ?></p>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Recent Activities -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-history me-2"></i>Recent Activities
                </h6>
            </div>
            <div class="card-body">
                <?php if (!empty($recent_activities)): ?>
                    <div class="timeline-sm">
                        <?php foreach (array_slice($recent_activities, 0, 5) as $activity): ?>
                            <div class="timeline-item-sm">
                                <div class="timeline-marker-sm bg-info"></div>
                                <div class="timeline-content-sm">
                                    <div class="small">
                                        <strong><?php echo htmlspecialchars($activity['action']); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            by <?php echo htmlspecialchars($activity['user_first_name'] . ' ' . $activity['user_last_name']); ?> • 
                                            <?php echo timeAgo($activity['created_at']); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-3 text-muted">
                        <i class="fas fa-history fa-2x mb-2 opacity-50"></i>
                        <p class="mb-0">No recent activities.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Member Info -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>Member Information
                </h6>
            </div>
            <div class="card-body">
                <?php if (!empty($member['education_level'])): ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Education:</span>
                        <span><?php echo htmlspecialchars(EDUCATION_LEVELS[$member['education_level']] ?? ucfirst($member['education_level'])); ?></span>
                    </div>
                <?php endif; ?>
                
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Member Since:</span>
                    <span><?php echo formatDisplayDate($member['join_date']); ?></span>
                </div>
                
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Status:</span>
                    <span class="badge status-<?php echo $member['membership_status']; ?>">
                        <?php echo MEMBER_STATUS_OPTIONS[$member['membership_status']] ?? ucfirst($member['membership_status']); ?>
                    </span>
                </div>
                
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Added by:</span>
                    <span><?php echo htmlspecialchars(($member['created_by_name'] ?? 'System') . ' ' . ($member['created_by_surname'] ?? '')); ?></span>
                </div>
                
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Record Created:</span>
                    <span><?php echo formatDisplayDateTime($member['created_at']); ?></span>
                </div>
            </div>
        </div>
        
        <!-- Notes -->
        <?php if (!empty($member['notes'])): ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-sticky-note me-2"></i>Notes
                    </h6>
                </div>
                <div class="card-body">
                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($member['notes'])); ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Department Management Modal -->
<div class="modal fade" id="departmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-church-blue text-white">
                <h5 class="modal-title">
                    <i class="fas fa-users-cog me-2"></i>Manage Department Assignments
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="departmentForm">
                    <input type="hidden" name="member_id" value="<?php echo $member_id; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Current Assignments</label>
                        <div id="current-departments">
                            <?php foreach ($departments as $dept): ?>
                                <div class="d-flex align-items-center justify-content-between p-2 border rounded mb-2">
                                    <div>
                                        <strong><?php echo htmlspecialchars($dept['name']); ?></strong>
                                        <small class="text-muted ms-2"><?php echo ucfirst($dept['role']); ?></small>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                            onclick="removeDepartment('<?php echo $dept['name']; ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Add New Assignment</label>
                        <div class="row">
                            <div class="col-8">
                                <select class="form-select" name="new_department_id" id="new_department_id">
                                    <option value="">Select Department</option>
                                    <!-- Will be populated by JavaScript -->
                                </select>
                            </div>
                            <div class="col-4">
                                <input type="text" class="form-control" name="new_role" 
                                       placeholder="Role" value="member">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-church-primary" onclick="saveDepartmentAssignments()">
                    <i class="fas fa-save me-1"></i>Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Member data for JavaScript
const memberData = {
    id: <?php echo $member_id; ?>,
    first_name: '<?php echo addslashes($member['first_name']); ?>',
    last_name: '<?php echo addslashes($member['last_name']); ?>',
    phone: '<?php echo addslashes($member['phone']); ?>',
    email: '<?php echo addslashes($member['email']); ?>',
    member_number: '<?php echo addslashes($member['member_number']); ?>'
};

function manageDepartments() {
    // Load available departments
    fetch(`${BASE_URL}api/members.php?action=get_departments`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('new_department_id');
                select.innerHTML = '<option value="">Select Department</option>';
                
                data.departments.forEach(dept => {
                    select.innerHTML += `<option value="${dept.id}">${dept.name} (${dept.department_type})</option>`;
                });
                
                const modal = new bootstrap.Modal(document.getElementById('departmentModal'));
                modal.show();
            }
        })
        .catch(error => {
            console.error('Error loading departments:', error);
            ChurchCMS.showToast('Error loading departments', 'error');
        });
}

function removeDepartment(departmentName) {
    if (confirm(`Remove ${departmentName} assignment?`)) {
        // This would call API to remove department assignment
        ChurchCMS.showToast(`${departmentName} assignment will be removed`, 'info');
    }
}

function saveDepartmentAssignments() {
    const formData = new FormData(document.getElementById('departmentForm'));
    
    if (!formData.get('new_department_id')) {
        ChurchCMS.showToast('Please select a department to add', 'warning');
        return;
    }
    
    const assignmentData = {
        member_id: memberData.id,
        department_id: formData.get('new_department_id'),
        role: formData.get('new_role') || 'member'
    };
    
    fetch(`${BASE_URL}api/members.php?action=assign_department`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(assignmentData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            ChurchCMS.showToast('Department assignment saved successfully', 'success');
            bootstrap.Modal.getInstance(document.getElementById('departmentModal')).hide();
            setTimeout(() => location.reload(), 1000);
        } else {
            ChurchCMS.showToast(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error saving department assignment:', error);
        ChurchCMS.showToast('Error saving assignment', 'error');
    });
}

function copyMemberInfo() {
    const memberInfo = `
Name: ${memberData.first_name} ${memberData.last_name}
Member #: ${memberData.member_number}
Phone: ${memberData.phone}
Email: ${memberData.email}
    `.trim();
    
    ChurchCMS.copyToClipboard(memberInfo, 'Member information copied to clipboard!');
}

// Timeline styles
document.addEventListener('DOMContentLoaded', function() {
    // Add timeline styles
    const timelineStyles = `
        <style>
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        .timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e9ecef;
        }
        .timeline-item {
            position: relative;
            margin-bottom: 20px;
        }
        .timeline-marker {
            position: absolute;
            left: -25px;
            top: 5px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }
        .timeline-content {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }
        .timeline-sm {
            position: relative;
            padding-left: 20px;
        }
        .timeline-item-sm {
            position: relative;
            margin-bottom: 15px;
        }
        .timeline-marker-sm {
            position: absolute;
            left: -25px;
            top: 3px;
            width: 6px;
            height: 6px;
            border-radius: 50%;
        }
        </style>
    `;
    document.head.insertAdjacentHTML('beforeend', timelineStyles);
});
</script>

<?php
// Include footer
include '../../includes/footer.php';
?>