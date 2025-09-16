<?php
/**
 * Members API Endpoint
 * Deliverance Church Management System
 * 
 * Handles API requests for member data
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

// Set JSON response header
header('Content-Type: application/json');

// Check authentication
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Get action from request
$action = isset($_GET['action']) ? sanitizeInput($_GET['action']) : 
          (isset($_POST['action']) ? sanitizeInput($_POST['action']) : '');

if (empty($action)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Action parameter required']);
    exit;
}

try {
    $db = Database::getInstance();
    
    switch ($action) {
        case 'get_all':
            handleGetAllMembers();
            break;
            
        case 'get_by_id':
            handleGetMemberById();
            break;
            
        case 'get_by_ids':
            handleGetMembersByIds();
            break;
            
        case 'search':
            handleSearchMembers();
            break;
            
        case 'browse_for_sms':
            handleBrowseForSMS();
            break;
            
        case 'create':
            handleCreateMember();
            break;
            
        case 'update':
            handleUpdateMember();
            break;
            
        case 'delete':
            handleDeleteMember();
            break;
            
        case 'get_departments':
            handleGetDepartments();
            break;
            
        case 'assign_department':
            handleAssignDepartment();
            break;
            
        case 'get_stats':
            handleGetMemberStats();
            break;
            
        case 'get_birthdays':
            handleGetBirthdays();
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    error_log("Members API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}

/**
 * Get all members with pagination and filters
 */
function handleGetAllMembers() {
    global $db;
    
    if (!hasPermission('members')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
        return;
    }
    
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $per_page = isset($_GET['per_page']) ? min((int)$_GET['per_page'], MAX_PAGE_SIZE) : DEFAULT_PAGE_SIZE;
    $search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
    $status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
    $department_id = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
    
    // Build query conditions
    $conditions = [];
    $params = [];
    
    if (!empty($search)) {
        $conditions[] = "(CONCAT(first_name, ' ', last_name) LIKE ? OR phone LIKE ? OR email LIKE ? OR member_number LIKE ?)";
        $search_term = "%{$search}%";
        $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
    }
    
    if (!empty($status)) {
        $conditions[] = "m.membership_status = ?";
        $params[] = $status;
    }
    
    if ($department_id > 0) {
        $conditions[] = "md.department_id = ?";
        $params[] = $department_id;
    }
    
    $where_clause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
    
    // Get total count
    $count_query = "SELECT COUNT(DISTINCT m.id) as total 
                    FROM members m 
                    LEFT JOIN member_departments md ON m.id = md.member_id AND md.is_active = 1
                    {$where_clause}";
    $total_records = $db->executeQuery($count_query, $params)->fetchColumn();
    
    // Generate pagination
    $pagination = generatePagination($total_records, $page, $per_page);
    
    // Get members data
    $offset = $pagination['offset'];
    $members_query = "SELECT DISTINCT m.*, 
                             GROUP_CONCAT(DISTINCT d.name SEPARATOR ', ') as departments
                      FROM members m 
                      LEFT JOIN member_departments md ON m.id = md.member_id AND md.is_active = 1
                      LEFT JOIN departments d ON md.department_id = d.id
                      {$where_clause}
                      GROUP BY m.id 
                      ORDER BY m.first_name, m.last_name 
                      LIMIT {$per_page} OFFSET {$offset}";
    
    $members = $db->executeQuery($members_query, $params)->fetchAll();
    
    // Format members data
    foreach ($members as &$member) {
        $member['age'] = calculateAge($member['date_of_birth']);
        $member['age_group'] = getAgeGroup($member['age']);
        $member['full_name'] = $member['first_name'] . ' ' . $member['last_name'];
        $member['photo_url'] = !empty($member['photo']) ? BASE_URL . $member['photo'] : null;
        $member['join_date_formatted'] = formatDisplayDate($member['join_date']);
        $member['phone_formatted'] = formatPhoneNumber($member['phone']);
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'members' => $members,
            'pagination' => $pagination,
            'total' => $total_records
        ]
    ]);
}

/**
 * Get member by ID
 */
function handleGetMemberById() {
    global $db;
    
    if (!hasPermission('members')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
        return;
    }
    
    $member_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($member_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid member ID']);
        return;
    }
    
    // Get member with departments
    $member = $db->executeQuery(
        "SELECT m.*, 
                GROUP_CONCAT(DISTINCT CONCAT(d.name, ':', md.role) SEPARATOR '|') as departments_with_roles
         FROM members m 
         LEFT JOIN member_departments md ON m.id = md.member_id AND md.is_active = 1
         LEFT JOIN departments d ON md.department_id = d.id
         WHERE m.id = ?
         GROUP BY m.id",
        [$member_id]
    )->fetch();
    
    if (!$member) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Member not found']);
        return;
    }
    
    // Format member data
    $member['age'] = calculateAge($member['date_of_birth']);
    $member['age_group'] = getAgeGroup($member['age']);
    $member['full_name'] = $member['first_name'] . ' ' . $member['last_name'];
    $member['photo_url'] = !empty($member['photo']) ? BASE_URL . $member['photo'] : null;
    $member['phone_formatted'] = formatPhoneNumber($member['phone']);
    
    // Parse departments
    $member['departments'] = [];
    if (!empty($member['departments_with_roles'])) {
        $dept_roles = explode('|', $member['departments_with_roles']);
        foreach ($dept_roles as $dept_role) {
            if (strpos($dept_role, ':') !== false) {
                list($dept_name, $role) = explode(':', $dept_role, 2);
                $member['departments'][] = [
                    'name' => $dept_name,
                    'role' => $role
                ];
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $member
    ]);
}

/**
 * Get multiple members by IDs
 */
function handleGetMembersByIds() {
    global $db;
    
    if (!hasPermission('members')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $member_ids = isset($input['member_ids']) ? $input['member_ids'] : [];
    
    if (empty($member_ids) || !is_array($member_ids)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid member IDs']);
        return;
    }
    
    $placeholders = str_repeat('?,', count($member_ids) - 1) . '?';
    $members = $db->executeQuery(
        "SELECT id, first_name, last_name, phone FROM members 
         WHERE id IN ({$placeholders}) AND membership_status = 'active'
         ORDER BY first_name, last_name",
        $member_ids
    )->fetchAll();
    
    echo json_encode([
        'success' => true,
        'members' => $members
    ]);
}

/**
 * Search members
 */
function handleSearchMembers() {
    global $db;
    
    if (!hasPermission('members')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
        return;
    }
    
    $query = isset($_GET['q']) ? sanitizeInput($_GET['q']) : '';
    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 50) : 10;
    
    if (strlen($query) < 2) {
        echo json_encode(['success' => false, 'message' => 'Query too short']);
        return;
    }
    
    $search_term = "%{$query}%";
    $members = $db->executeQuery(
        "SELECT id, first_name, last_name, phone, member_number, photo
         FROM members 
         WHERE (CONCAT(first_name, ' ', last_name) LIKE ? 
                OR phone LIKE ? 
                OR email LIKE ? 
                OR member_number LIKE ?)
         AND membership_status = 'active'
         ORDER BY first_name, last_name
         LIMIT ?",
        [$search_term, $search_term, $search_term, $search_term, $limit]
    )->fetchAll();
    
    // Format results
    foreach ($members as &$member) {
        $member['full_name'] = $member['first_name'] . ' ' . $member['last_name'];
        $member['photo_url'] = !empty($member['photo']) ? BASE_URL . $member['photo'] : null;
    }
    
    echo json_encode([
        'success' => true,
        'members' => $members
    ]);
}

/**
 * Browse members for SMS selection
 */
function handleBrowseForSMS() {
    global $db;
    
    if (!hasPermission('sms')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
        return;
    }
    
    $members = $db->executeQuery(
        "SELECT id, first_name, last_name, phone 
         FROM members 
         WHERE membership_status = 'active' 
         AND phone IS NOT NULL 
         AND phone != ''
         ORDER BY first_name, last_name"
    )->fetchAll();
    
    // Filter valid phone numbers
    $valid_members = array_filter($members, function($member) {
        return validatePhoneNumber($member['phone']);
    });
    
    echo json_encode([
        'success' => true,
        'members' => array_values($valid_members)
    ]);
}

/**
 * Create new member
 */
function handleCreateMember() {
    global $db;
    
    if (!hasPermission('members')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required_fields = ['first_name', 'last_name', 'gender', 'join_date'];
    foreach ($required_fields as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required']);
            return;
        }
    }
    
    // Generate member number if not provided
    if (empty($input['member_number'])) {
        $year = date('y');
        $stmt = $db->executeQuery(
            "SELECT MAX(CAST(SUBSTRING(member_number, 4) AS UNSIGNED)) as max_num 
             FROM members 
             WHERE member_number LIKE ?",
            ["MEM{$year}%"]
        );
        $max_num = $stmt->fetchColumn() ?: 0;
        $input['member_number'] = sprintf("MEM%s%04d", $year, $max_num + 1);
    }
    
    // Prepare member data
    $member_data = [
        'member_number' => $input['member_number'],
        'first_name' => sanitizeInput($input['first_name']),
        'last_name' => sanitizeInput($input['last_name']),
        'middle_name' => sanitizeInput($input['middle_name'] ?? ''),
        'date_of_birth' => !empty($input['date_of_birth']) ? $input['date_of_birth'] : null,
        'gender' => sanitizeInput($input['gender']),
        'marital_status' => sanitizeInput($input['marital_status'] ?? 'single'),
        'phone' => sanitizeInput($input['phone'] ?? ''),
        'email' => sanitizeInput($input['email'] ?? ''),
        'address' => sanitizeInput($input['address'] ?? ''),
        'emergency_contact_name' => sanitizeInput($input['emergency_contact_name'] ?? ''),
        'emergency_contact_phone' => sanitizeInput($input['emergency_contact_phone'] ?? ''),
        'emergency_contact_relationship' => sanitizeInput($input['emergency_contact_relationship'] ?? ''),
        'join_date' => $input['join_date'],
        'baptism_date' => !empty($input['baptism_date']) ? $input['baptism_date'] : null,
        'confirmation_date' => !empty($input['confirmation_date']) ? $input['confirmation_date'] : null,
        'membership_status' => sanitizeInput($input['membership_status'] ?? 'active'),
        'occupation' => sanitizeInput($input['occupation'] ?? ''),
        'education_level' => sanitizeInput($input['education_level'] ?? ''),
        'skills' => sanitizeInput($input['skills'] ?? ''),
        'spiritual_gifts' => sanitizeInput($input['spiritual_gifts'] ?? ''),
        'leadership_roles' => sanitizeInput($input['leadership_roles'] ?? ''),
        'notes' => sanitizeInput($input['notes'] ?? ''),
        'created_by' => $_SESSION['user_id']
    ];
    
    $db->beginTransaction();
    
    try {
        $member_id = insertRecord('members', $member_data);
        
        if ($member_id) {
            // Add department assignments if provided
            if (!empty($input['departments'])) {
                foreach ($input['departments'] as $dept) {
                    if (!empty($dept['department_id'])) {
                        $dept_assignment = [
                            'member_id' => $member_id,
                            'department_id' => $dept['department_id'],
                            'role' => $dept['role'] ?? 'member',
                            'assigned_date' => date('Y-m-d'),
                            'is_active' => 1
                        ];
                        insertRecord('member_departments', $dept_assignment);
                    }
                }
            }
            
            $db->commit();
            
            // Log activity
            logActivity(
                'New member created: ' . $member_data['first_name'] . ' ' . $member_data['last_name'],
                'members',
                $member_id,
                null,
                $member_data
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Member created successfully',
                'member_id' => $member_id
            ]);
        } else {
            throw new Exception('Failed to create member record');
        }
        
    } catch (Exception $e) {
        $db->rollback();
        error_log("Create member error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create member']);
    }
}

/**
 * Update member
 */
function handleUpdateMember() {
    global $db;
    
    if (!hasPermission('members')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
        return;
    }
    
    $member_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($member_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid member ID']);
        return;
    }
    
    // Get existing member for logging
    $existing_member = getRecord('members', 'id', $member_id);
    if (!$existing_member) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Member not found']);
        return;
    }
    
    // Prepare update data
    $update_data = [];
    $allowed_fields = [
        'first_name', 'last_name', 'middle_name', 'date_of_birth', 'gender',
        'marital_status', 'phone', 'email', 'address', 'emergency_contact_name',
        'emergency_contact_phone', 'emergency_contact_relationship', 'baptism_date',
        'confirmation_date', 'membership_status', 'occupation', 'education_level',
        'skills', 'spiritual_gifts', 'leadership_roles', 'notes'
    ];
    
    foreach ($allowed_fields as $field) {
        if (array_key_exists($field, $input)) {
            $update_data[$field] = sanitizeInput($input[$field]);
        }
    }
    
    if (empty($update_data)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No valid fields to update']);
        return;
    }
    
    $db->beginTransaction();
    
    try {
        $result = updateRecord('members', $update_data, ['id' => $member_id]);
        
        if ($result) {
            // Update department assignments if provided
            if (isset($input['departments'])) {
                // Remove existing assignments
                $db->executeQuery(
                    "UPDATE member_departments SET is_active = 0 WHERE member_id = ?",
                    [$member_id]
                );
                
                // Add new assignments
                foreach ($input['departments'] as $dept) {
                    if (!empty($dept['department_id'])) {
                        $dept_assignment = [
                            'member_id' => $member_id,
                            'department_id' => $dept['department_id'],
                            'role' => $dept['role'] ?? 'member',
                            'assigned_date' => date('Y-m-d'),
                            'is_active' => 1
                        ];
                        insertRecord('member_departments', $dept_assignment);
                    }
                }
            }
            
            $db->commit();
            
            // Log activity
            logActivity(
                'Member updated: ' . $existing_member['first_name'] . ' ' . $existing_member['last_name'],
                'members',
                $member_id,
                $existing_member,
                $update_data
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Member updated successfully'
            ]);
        } else {
            throw new Exception('Failed to update member record');
        }
        
    } catch (Exception $e) {
        $db->rollback();
        error_log("Update member error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update member']);
    }
}

/**
 * Delete member
 */
function handleDeleteMember() {
    global $db;
    
    if (!hasPermission('members') || $_SESSION['user_role'] !== 'administrator') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
        return;
    }
    
    $member_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($member_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid member ID']);
        return;
    }
    
    // Get existing member for logging
    $existing_member = getRecord('members', 'id', $member_id);
    if (!$existing_member) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Member not found']);
        return;
    }
    
    $db->beginTransaction();
    
    try {
        // Instead of deleting, mark as inactive
        $result = updateRecord('members', [
            'membership_status' => 'inactive',
            'notes' => ($existing_member['notes'] ? $existing_member['notes'] . "\n" : '') . 
                      'Account deactivated on ' . date('Y-m-d H:i:s') . ' by ' . $_SESSION['username']
        ], ['id' => $member_id]);
        
        if ($result) {
            // Deactivate department assignments
            $db->executeQuery(
                "UPDATE member_departments SET is_active = 0 WHERE member_id = ?",
                [$member_id]
            );
            
            $db->commit();
            
            // Log activity
            logActivity(
                'Member deactivated: ' . $existing_member['first_name'] . ' ' . $existing_member['last_name'],
                'members',
                $member_id,
                $existing_member,
                ['membership_status' => 'inactive']
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Member deactivated successfully'
            ]);
        } else {
            throw new Exception('Failed to deactivate member');
        }
        
    } catch (Exception $e) {
        $db->rollback();
        error_log("Delete member error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to deactivate member']);
    }
}

/**
 * Get departments
 */
function handleGetDepartments() {
    global $db;
    
    $departments = $db->executeQuery(
        "SELECT id, name, description, department_type, head_member_id, 
                (SELECT CONCAT(first_name, ' ', last_name) FROM members WHERE id = head_member_id) as head_name,
                (SELECT COUNT(*) FROM member_departments WHERE department_id = departments.id AND is_active = 1) as member_count
         FROM departments 
         WHERE is_active = 1 
         ORDER BY department_type, name"
    )->fetchAll();
    
    echo json_encode([
        'success' => true,
        'departments' => $departments
    ]);
}

/**
 * Assign member to department
 */
function handleAssignDepartment() {
    global $db;
    
    if (!hasPermission('members')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $member_id = isset($input['member_id']) ? (int)$input['member_id'] : 0;
    $department_id = isset($input['department_id']) ? (int)$input['department_id'] : 0;
    $role = isset($input['role']) ? sanitizeInput($input['role']) : 'member';
    
    if ($member_id <= 0 || $department_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid member or department ID']);
        return;
    }
    
    // Check if assignment already exists
    $existing = $db->executeQuery(
        "SELECT id FROM member_departments 
         WHERE member_id = ? AND department_id = ? AND is_active = 1",
        [$member_id, $department_id]
    )->fetch();
    
    if ($existing) {
        echo json_encode(['success' => false, 'message' => 'Member already assigned to this department']);
        return;
    }
    
    $assignment_data = [
        'member_id' => $member_id,
        'department_id' => $department_id,
        'role' => $role,
        'assigned_date' => date('Y-m-d'),
        'is_active' => 1
    ];
    
    $result = insertRecord('member_departments', $assignment_data);
    
    if ($result) {
        // Log activity
        $member = getRecord('members', 'id', $member_id);
        $department = getRecord('departments', 'id', $department_id);
        
        logActivity(
            'Member assigned to department: ' . $member['first_name'] . ' ' . $member['last_name'] . 
            ' assigned to ' . $department['name'],
            'member_departments',
            $result
        );
        
        echo json_encode([
            'success' => true,
            'message' => 'Member assigned to department successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to assign member to department']);
    }
}

/**
 * Get member statistics
 */
function handleGetMemberStats() {
    global $db;
    
    if (!hasPermission('members')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
        return;
    }
    
    // Total members by status
    $status_stats = $db->executeQuery(
        "SELECT membership_status, COUNT(*) as count 
         FROM members 
         GROUP BY membership_status"
    )->fetchAll();
    
    // Members by gender
    $gender_stats = $db->executeQuery(
        "SELECT gender, COUNT(*) as count 
         FROM members 
         WHERE membership_status = 'active'
         GROUP BY gender"
    )->fetchAll();
    
    // Members by age group
    $age_stats = $db->executeQuery(
        "SELECT 
            CASE 
                WHEN YEAR(CURDATE()) - YEAR(date_of_birth) <= " . CHILD_MAX_AGE . " THEN 'child'
                WHEN YEAR(CURDATE()) - YEAR(date_of_birth) <= " . TEEN_MAX_AGE . " THEN 'teen'
                WHEN YEAR(CURDATE()) - YEAR(date_of_birth) <= " . YOUTH_MAX_AGE . " THEN 'youth'
                WHEN YEAR(CURDATE()) - YEAR(date_of_birth) >= " . SENIOR_MIN_AGE . " THEN 'senior'
                ELSE 'adult'
            END as age_group,
            COUNT(*) as count
         FROM members 
         WHERE membership_status = 'active' AND date_of_birth IS NOT NULL
         GROUP BY age_group"
    )->fetchAll();
    
    // Members by department
    $department_stats = $db->executeQuery(
        "SELECT d.name, COUNT(md.member_id) as count 
         FROM departments d
         LEFT JOIN member_departments md ON d.id = md.department_id AND md.is_active = 1
         JOIN members m ON md.member_id = m.id AND m.membership_status = 'active'
         WHERE d.is_active = 1
         GROUP BY d.id, d.name
         ORDER BY count DESC"
    )->fetchAll();
    
    // Growth statistics (last 12 months)
    $growth_stats = $db->executeQuery(
        "SELECT 
            DATE_FORMAT(join_date, '%Y-%m') as month,
            COUNT(*) as new_members
         FROM members 
         WHERE join_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
         AND membership_status = 'active'
         GROUP BY DATE_FORMAT(join_date, '%Y-%m')
         ORDER BY month DESC"
    )->fetchAll();
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'status' => $status_stats,
            'gender' => $gender_stats,
            'age_groups' => $age_stats,
            'departments' => $department_stats,
            'growth' => $growth_stats
        ]
    ]);
}

/**
 * Get upcoming birthdays
 */
function handleGetBirthdays() {
    global $db;
    
    $days_ahead = isset($_GET['days']) ? (int)$_GET['days'] : 30;
    
    $birthdays = $db->executeQuery(
        "SELECT id, first_name, last_name, date_of_birth, phone,
                DAYOFYEAR(STR_TO_DATE(CONCAT(YEAR(NOW()), '-', MONTH(date_of_birth), '-', DAY(date_of_birth)), '%Y-%m-%d')) as birthday_day,
                DAYOFYEAR(NOW()) as today_day,
                YEAR(NOW()) - YEAR(date_of_birth) as age
         FROM members 
         WHERE membership_status = 'active' 
         AND date_of_birth IS NOT NULL
         HAVING (birthday_day >= today_day AND birthday_day <= today_day + ?)
         OR (birthday_day < today_day AND birthday_day <= ? - 365 + today_day)
         ORDER BY 
            CASE 
                WHEN birthday_day >= today_day THEN birthday_day - today_day
                ELSE (365 - today_day) + birthday_day
            END",
        [$days_ahead, $days_ahead]
    )->fetchAll();
    
    // Format birthdays
    foreach ($birthdays as &$birthday) {
        $birthday_date = new DateTime($birthday['date_of_birth']);
        $today = new DateTime();
        $birthday_this_year = new DateTime($today->format('Y') . '-' . $birthday_date->format('m-d'));
        
        if ($birthday_this_year < $today) {
            $birthday_this_year->add(new DateInterval('P1Y'));
        }
        
        $birthday['days_until'] = $today->diff($birthday_this_year)->days;
        $birthday['birthday_date_formatted'] = $birthday_date->format('M j');
        $birthday['full_name'] = $birthday['first_name'] . ' ' . $birthday['last_name'];
    }
    
    echo json_encode([
        'success' => true,
        'birthdays' => $birthdays
    ]);
}
?>