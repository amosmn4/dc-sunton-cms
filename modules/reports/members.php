<?php
/**
 * Member Reports
 * Deliverance Church Management System
 * 
 * Generate various member-related reports
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

// Get report type
$reportType = sanitizeInput($_GET['type'] ?? 'directory');
$export = sanitizeInput($_GET['export'] ?? '');
$format = sanitizeInput($_GET['format'] ?? 'html');

// Page configuration
$page_title = 'Member Reports';
$page_icon = 'fas fa-users';
$breadcrumb = [
    ['title' => 'Reports', 'url' => 'index.php'],
    ['title' => 'Member Reports']
];

// Initialize database
$db = Database::getInstance();

// Process filters
$filters = [
    'department' => sanitizeInput($_GET['department'] ?? ''),
    'gender' => sanitizeInput($_GET['gender'] ?? ''),
    'age_group' => sanitizeInput($_GET['age_group'] ?? ''),
    'status' => sanitizeInput($_GET['status'] ?? 'active'),
    'join_date_from' => sanitizeInput($_GET['join_date_from'] ?? ''),
    'join_date_to' => sanitizeInput($_GET['join_date_to'] ?? ''),
    'search' => sanitizeInput($_GET['search'] ?? '')
];

// Build WHERE clause
$whereConditions = [];
$params = [];

if (!empty($filters['department'])) {
    $whereConditions[] = "md.department_id = ?";
    $params[] = $filters['department'];
}

if (!empty($filters['gender'])) {
    $whereConditions[] = "m.gender = ?";
    $params[] = $filters['gender'];
}

if (!empty($filters['status'])) {
    $whereConditions[] = "m.membership_status = ?";
    $params[] = $filters['status'];
}

if (!empty($filters['join_date_from'])) {
    $whereConditions[] = "m.join_date >= ?";
    $params[] = $filters['join_date_from'];
}

if (!empty($filters['join_date_to'])) {
    $whereConditions[] = "m.join_date <= ?";
    $params[] = $filters['join_date_to'];
}

if (!empty($filters['search'])) {
    $whereConditions[] = "(m.first_name LIKE ? OR m.last_name LIKE ? OR m.phone LIKE ? OR m.email LIKE ?)";
    $searchTerm = '%' . $filters['search'] . '%';
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

// Add age group filter
if (!empty($filters['age_group'])) {
    switch ($filters['age_group']) {
        case 'children':
            $whereConditions[] = "m.age <= 12";
            break;
        case 'teens':
            $whereConditions[] = "m.age BETWEEN 13 AND 17";
            break;
        case 'youth':
            $whereConditions[] = "m.age BETWEEN 18 AND 35";
            break;
        case 'adults':
            $whereConditions[] = "m.age BETWEEN 36 AND 59";
            break;
        case 'seniors':
            $whereConditions[] = "m.age >= 60";
            break;
    }
}

$whereClause = !empty($whereConditions) ? ' WHERE ' . implode(' AND ', $whereConditions) : '';

try {
    // Get data based on report type
    switch ($reportType) {
        case 'directory':
            $reportTitle = 'Member Directory';
            $sql = "
                SELECT DISTINCT
                    m.*,
                    GROUP_CONCAT(DISTINCT d.name SEPARATOR ', ') as departments
                FROM members m
                LEFT JOIN member_departments md ON m.id = md.member_id AND md.is_active = 1
                LEFT JOIN departments d ON md.department_id = d.id
                $whereClause
                GROUP BY m.id
                ORDER BY m.last_name, m.first_name
            ";
            break;
            
        case 'new':
            $reportTitle = 'New Members Report';
            $sql = "
                SELECT DISTINCT
                    m.*,
                    GROUP_CONCAT(DISTINCT d.name SEPARATOR ', ') as departments,
                    DATEDIFF(NOW(), m.join_date) as days_since_joined
                FROM members m
                LEFT JOIN member_departments md ON m.id = md.member_id AND md.is_active = 1
                LEFT JOIN departments d ON md.department_id = d.id
                WHERE m.join_date >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
                " . (!empty($whereConditions) ? ' AND ' . implode(' AND ', $whereConditions) : '') . "
                GROUP BY m.id
                ORDER BY m.join_date DESC
            ";
            break;
            
        case 'birthdays':
            $reportTitle = 'Birthday Report';
            $month = sanitizeInput($_GET['month'] ?? date('m'));
            $sql = "
                SELECT DISTINCT
                    m.*,
                    GROUP_CONCAT(DISTINCT d.name SEPARATOR ', ') as departments,
                    DAY(m.date_of_birth) as birthday_day,
                    DAYNAME(m.date_of_birth) as birthday_weekday
                FROM members m
                LEFT JOIN member_departments md ON m.id = md.member_id AND md.is_active = 1
                LEFT JOIN departments d ON md.department_id = d.id
                WHERE MONTH(m.date_of_birth) = ?
                " . (!empty($whereConditions) ? ' AND ' . implode(' AND ', $whereConditions) : '') . "
                GROUP BY m.id
                ORDER BY DAY(m.date_of_birth)
            ";
            $params = array_merge([$month], $params);
            break;
            
        case 'departments':
            $reportTitle = 'Department Analysis';
            $sql = "
                SELECT 
                    d.name as department_name,
                    d.department_type,
                    COUNT(DISTINCT m.id) as total_members,
                    SUM(CASE WHEN m.gender = 'male' THEN 1 ELSE 0 END) as male_count,
                    SUM(CASE WHEN m.gender = 'female' THEN 1 ELSE 0 END) as female_count,
                    AVG(m.age) as average_age,
                    CONCAT(u.first_name, ' ', u.last_name) as department_head
                FROM departments d
                LEFT JOIN member_departments md ON d.id = md.department_id AND md.is_active = 1
                LEFT JOIN members m ON md.member_id = m.id AND m.membership_status = 'active'
                LEFT JOIN members u ON d.head_member_id = u.id
                WHERE d.is_active = 1
                GROUP BY d.id, d.name, d.department_type
                ORDER BY total_members DESC
            ";
            $params = []; // Reset params for this query
            break;
            
        case 'inactive':
            $reportTitle = 'Inactive Members Report';
            $sql = "
                SELECT DISTINCT
                    m.*,
                    GROUP_CONCAT(DISTINCT d.name SEPARATOR ', ') as departments,
                    DATEDIFF(NOW(), m.updated_at) as days_since_update,
                    (SELECT MAX(ar.check_in_time) 
                     FROM attendance_records ar 
                     WHERE ar.member_id = m.id) as last_attendance
                FROM members m
                LEFT JOIN member_departments md ON m.id = md.member_id AND md.is_active = 1
                LEFT JOIN departments d ON md.department_id = d.id
                WHERE m.membership_status = 'inactive'
                " . (!empty($whereConditions) ? ' AND ' . implode(' AND ', $whereConditions) : '') . "
                GROUP BY m.id
                ORDER BY m.updated_at DESC
            ";
            break;
            
        default:
            $reportTitle = 'Member Directory';
            $sql = "
                SELECT DISTINCT
                    m.*,
                    GROUP_CONCAT(DISTINCT d.name SEPARATOR ', ') as departments
                FROM members m
                LEFT JOIN member_departments md ON m.id = md.member_id AND md.is_active = 1
                LEFT JOIN departments d ON md.department_id = d.id
                $whereClause
                GROUP BY m.id
                ORDER BY m.last_name, m.first_name
            ";
            break;
    }
    
    // Execute query
    $stmt = $db->executeQuery($sql, $params);
    $reportData = $stmt->fetchAll();
    
    // Get additional data for filters
    $departments = $db->executeQuery("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name")->fetchAll();
    
} catch (Exception $e) {
    error_log("Error generating member report: " . $e->getMessage());
    setFlashMessage('error', 'Error generating report: ' . $e->getMessage());
    $reportData = [];
    $departments = [];
}

// Handle export
if (!empty($export) && !empty($reportData)) {
    switch ($format) {
        case 'csv':
            exportMemberReportCSV($reportData, $reportTitle, $reportType);
            break;
        case 'excel':
            exportMemberReportExcel($reportData, $reportTitle, $reportType);
            break;
        case 'pdf':
            exportMemberReportPDF($reportData, $reportTitle, $reportType);
            break;
    }
    exit();
}

/**
 * Export member report to CSV
 */
function exportMemberReportCSV($data, $title, $type) {
    $filename = sanitizeFilename($title) . '_' . date('Y-m-d') . '.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Headers based on report type
    if ($type === 'departments') {
        fputcsv($output, ['Department', 'Type', 'Total Members', 'Male', 'Female', 'Average Age', 'Department Head']);
        foreach ($data as $row) {
            fputcsv($output, [
                $row['department_name'],
                ucwords(str_replace('_', ' ', $row['department_type'])),
                $row['total_members'],
                $row['male_count'],
                $row['female_count'],
                round($row['average_age'], 1),
                $row['department_head']
            ]);
        }
    } else {
        fputcsv($output, ['Member #', 'Full Name', 'Gender', 'Age', 'Phone', 'Email', 'Join Date', 'Status', 'Departments']);
        foreach ($data as $row) {
            fputcsv($output, [
                $row['member_number'],
                $row['first_name'] . ' ' . $row['last_name'],
                ucfirst($row['gender']),
                $row['age'],
                $row['phone'],
                $row['email'],
                formatDisplayDate($row['join_date']),
                ucwords(str_replace('_', ' ', $row['membership_status'])),
                $row['departments']
            ]);
        }
    }
    
    fclose($output);
    logActivity('Exported member report', 'members', null, null, ['type' => $type, 'format' => 'csv']);
}

/**
 * Sanitize filename
 */
function sanitizeFilename($filename) {
    return preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
}

// Include header if not exporting
if (empty($export)) {
    include_once '../../includes/header.php';
}
?>

<?php if (empty($export)): ?>

<!-- Member Reports Content -->
<div class="row">
    <!-- Report Filters -->
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="fas fa-filter me-2"></i>
                        Report Filters
                    </h6>
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                        <i class="fas fa-chevron-down"></i>
                    </button>
                </div>
            </div>
            <div class="collapse show" id="filterCollapse">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3">
                        <input type="hidden" name="type" value="<?php echo htmlspecialchars($reportType); ?>">
                        
                        <!-- Report Type Selection -->
                        <div class="col-md-3">
                            <label class="form-label">Report Type</label>
                            <select name="type" class="form-select" onchange="this.form.submit()">
                                <option value="directory" <?php echo $reportType === 'directory' ? 'selected' : ''; ?>>Member Directory</option>
                                <option value="new" <?php echo $reportType === 'new' ? 'selected' : ''; ?>>New Members</option>
                                <option value="birthdays" <?php echo $reportType === 'birthdays' ? 'selected' : ''; ?>>Birthdays</option>
                                <option value="departments" <?php echo $reportType === 'departments' ? 'selected' : ''; ?>>Department Analysis</option>
                                <option value="inactive" <?php echo $reportType === 'inactive' ? 'selected' : ''; ?>>Inactive Members</option>
                            </select>
                        </div>

                        <?php if ($reportType === 'birthdays'): ?>
                        <!-- Month Selection for Birthdays -->
                        <div class="col-md-3">
                            <label class="form-label">Month</label>
                            <select name="month" class="form-select">
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?php echo sprintf('%02d', $i); ?>" <?php echo (sanitizeInput($_GET['month'] ?? date('m')) == sprintf('%02d', $i)) ? 'selected' : ''; ?>>
                                        <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <?php if ($reportType !== 'departments'): ?>
                        <!-- Department Filter -->
                        <div class="col-md-3">
                            <label class="form-label">Department</label>
                            <select name="department" class="form-select">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>" <?php echo $filters['department'] == $dept['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Gender Filter -->
                        <div class="col-md-3">
                            <label class="form-label">Gender</label>
                            <select name="gender" class="form-select">
                                <option value="">All Genders</option>
                                <option value="male" <?php echo $filters['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo $filters['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>

                        <!-- Age Group Filter -->
                        <div class="col-md-3">
                            <label class="form-label">Age Group</label>
                            <select name="age_group" class="form-select">
                                <option value="">All Ages</option>
                                <option value="children" <?php echo $filters['age_group'] === 'children' ? 'selected' : ''; ?>>Children (0-12)</option>
                                <option value="teens" <?php echo $filters['age_group'] === 'teens' ? 'selected' : ''; ?>>Teens (13-17)</option>
                                <option value="youth" <?php echo $filters['age_group'] === 'youth' ? 'selected' : ''; ?>>Youth (18-35)</option>
                                <option value="adults" <?php echo $filters['age_group'] === 'adults' ? 'selected' : ''; ?>>Adults (36-59)</option>
                                <option value="seniors" <?php echo $filters['age_group'] === 'seniors' ? 'selected' : ''; ?>>Seniors (60+)</option>
                            </select>
                        </div>

                        <!-- Status Filter -->
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $filters['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="transferred" <?php echo $filters['status'] === 'transferred' ? 'selected' : ''; ?>>Transferred</option>
                                <option value="deceased" <?php echo $filters['status'] === 'deceased' ? 'selected' : ''; ?>>Deceased</option>
                                <option value="" <?php echo $filters['status'] === '' ? 'selected' : ''; ?>>All Statuses</option>
                            </select>
                        </div>

                        <!-- Date Range -->
                        <div class="col-md-3">
                            <label class="form-label">Join Date From</label>
                            <input type="date" name="join_date_from" class="form-control" value="<?php echo htmlspecialchars($filters['join_date_from']); ?>">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Join Date To</label>
                            <input type="date" name="join_date_to" class="form-control" value="<?php echo htmlspecialchars($filters['join_date_to']); ?>">
                        </div>
                        <?php endif; ?>

                        <!-- Search -->
                        <div class="col-md-6">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" placeholder="Search by name, phone, or email" value="<?php echo htmlspecialchars($filters['search']); ?>">
                        </div>

                        <!-- Action Buttons -->
                        <div class="col-12">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-church-primary">
                                    <i class="fas fa-search me-2"></i>Generate Report
                                </button>
                                <a href="?" class="btn btn-outline-secondary">
                                    <i class="fas fa-undo me-2"></i>Reset Filters
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Results -->
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-table me-2"></i>
                        <?php echo htmlspecialchars($reportTitle); ?>
                        <span class="badge bg-primary ms-2"><?php echo count($reportData); ?> records</span>
                    </h5>
                    
                    <?php if (!empty($reportData)): ?>
                    <div class="btn-group">
                        <button class="btn btn-sm btn-outline-success dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fas fa-download me-2"></i>Export
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['export' => '1', 'format' => 'csv'])); ?>">
                                <i class="fas fa-file-csv me-2"></i>Export as CSV
                            </a></li>
                            <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['export' => '1', 'format' => 'excel'])); ?>">
                                <i class="fas fa-file-excel me-2"></i>Export as Excel
                            </a></li>
                            <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['export' => '1', 'format' => 'pdf'])); ?>">
                                <i class="fas fa-file-pdf me-2"></i>Export as PDF
                            </a></li>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card-body">
                <?php if (empty($reportData)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-search fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No Data Found</h5>
                        <p class="text-muted">No records match your current filter criteria.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <?php if ($reportType === 'departments'): ?>
                            <!-- Department Analysis Table -->
                            <table class="table table-hover data-table">
                                <thead>
                                    <tr>
                                        <th>Department</th>
                                        <th>Type</th>
                                        <th>Total Members</th>
                                        <th>Male</th>
                                        <th>Female</th>
                                        <th>Avg Age</th>
                                        <th>Department Head</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportData as $row): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($row['department_name']); ?></strong></td>
                                            <td><span class="badge bg-secondary"><?php echo ucwords(str_replace('_', ' ', $row['department_type'])); ?></span></td>
                                            <td><span class="fw-bold text-church-blue"><?php echo number_format($row['total_members']); ?></span></td>
                                            <td><?php echo number_format($row['male_count']); ?></td>
                                            <td><?php echo number_format($row['female_count']); ?></td>
                                            <td><?php echo $row['average_age'] ? round($row['average_age'], 1) : '-'; ?></td>
                                            <td><?php echo htmlspecialchars($row['department_head'] ?: '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <!-- Member Details Table -->
                            <table class="table table-hover data-table">
                                <thead>
                                    <tr>
                                        <th>Photo</th>
                                        <th>Member #</th>
                                        <th>Full Name</th>
                                        <th>Gender</th>
                                        <th>Age</th>
                                        <th>Contact</th>
                                        <?php if ($reportType === 'birthdays'): ?>
                                            <th>Birthday</th>
                                        <?php endif; ?>
                                        <th>Join Date</th>
                                        <th>Status</th>
                                        <th>Departments</th>
                                        <?php if ($reportType === 'inactive'): ?>
                                            <th>Last Seen</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportData as $member): ?>
                                        <tr>
                                            <td>
                                                <?php if (!empty($member['photo'])): ?>
                                                    <img src="<?php echo BASE_URL . $member['photo']; ?>" alt="Photo" class="rounded-circle" width="40" height="40">
                                                <?php else: ?>
                                                    <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                        <i class="fas fa-user text-white"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td><code><?php echo htmlspecialchars($member['member_number']); ?></code></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></strong>
                                                <?php if (!empty($member['middle_name'])): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($member['middle_name']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $member['gender'] === 'male' ? 'bg-primary' : 'bg-pink'; ?>">
                                                    <?php echo ucfirst($member['gender']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $member['age'] ?: '-'; ?></td>
                                            <td>
                                                <?php if (!empty($member['phone'])): ?>
                                                    <div><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($member['phone']); ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($member['email'])): ?>
                                                    <div><i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($member['email']); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <?php if ($reportType === 'birthdays'): ?>
                                                <td>
                                                    <strong><?php echo date('M j', strtotime($member['date_of_birth'])); ?></strong>
                                                    <br><small class="text-muted"><?php echo $member['birthday_weekday']; ?></small>
                                                </td>
                                            <?php endif; ?>
                                            <td><?php echo formatDisplayDate($member['join_date']); ?></td>
                                            <td>
                                                <?php
                                                $statusClass = [
                                                    'active' => 'success',
                                                    'inactive' => 'secondary',
                                                    'transferred' => 'warning',
                                                    'deceased' => 'dark'
                                                ];
                                                $class = $statusClass[$member['membership_status']] ?? 'secondary';
                                                ?>
                                                <span class="status-badge status-<?php echo $member['membership_status']; ?>">
                                                    <?php echo ucwords(str_replace('_', ' ', $member['membership_status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (!empty($member['departments'])): ?>
                                                    <?php
                                                    $departments = explode(', ', $member['departments']);
                                                    foreach ($departments as $dept):
                                                    ?>
                                                        <span class="badge bg-light text-dark me-1"><?php echo htmlspecialchars($dept); ?></span>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">No departments</span>
                                                <?php endif; ?>
                                            </td>
                                            <?php if ($reportType === 'inactive'): ?>
                                                <td>
                                                    <?php if (!empty($member['last_attendance'])): ?>
                                                        <?php echo formatDisplayDate($member['last_attendance']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Never</span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Custom CSS for member reports -->
<style>
.bg-pink {
    background-color: #e91e63 !important;
}

.status-badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-active {
    background-color: rgba(40, 167, 69, 0.1);
    color: #28a745;
    border: 1px solid rgba(40, 167, 69, 0.2);
}

.status-inactive {
    background-color: rgba(108, 117, 125, 0.1);
    color: #6c757d;
    border: 1px solid rgba(108, 117, 125, 0.2);
}

.status-transferred {
    background-color: rgba(255, 193, 7, 0.1);
    color: #856404;
    border: 1px solid rgba(255, 193, 7, 0.2);
}

.status-deceased {
    background-color: rgba(52, 58, 64, 0.1);
    color: #343a40;
    border: 1px solid rgba(52, 58, 64, 0.2);
}

@media (max-width: 768px) {
    .table-responsive {
        font-size: 0.875rem;
    }
    
    .badge {
        font-size: 0.6rem;
    }
}
</style>

<!-- JavaScript for enhanced functionality -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable if available
    if (typeof $ !== 'undefined' && $.fn.DataTable && $('.data-table').length) {
        $('.data-table').DataTable({
            responsive: true,
            pageLength: 25,
            order: [[2, 'asc']], // Sort by name
            columnDefs: [
                { orderable: false, targets: [0] } // Disable sorting for photo column
            ],
            buttons: [
                'copy', 'excel', 'pdf', 'print'
            ],
            dom: 'Bfrtip'
        });
    }
    
    // Auto-submit form when report type changes
    const reportTypeSelect = document.querySelector('select[name="type"]');
    if (reportTypeSelect) {
        reportTypeSelect.addEventListener('change', function() {
            // Clear other filters when changing report type
            const form = this.closest('form');
            const inputs = form.querySelectorAll('input[type="text"], input[type="date"], select:not([name="type"])');
            inputs.forEach(input => {
                if (input.name !== 'type') {
                    input.value = '';
                }
            });
        });
    }
});

// Print report function
function printReport() {
    window.print();
}

// Email report function
function emailReport() {
    const reportData = {
        type: '<?php echo $reportType; ?>',
        title: '<?php echo $reportTitle; ?>',
        filters: <?php echo json_encode($filters); ?>,
        recordCount: <?php echo count($reportData); ?>
    };
    
    ChurchCMS.showConfirm(
        'Send this report via email?',
        function() {
            // Implementation for email functionality
            ChurchCMS.showToast('Email functionality will be implemented soon', 'info');
        }
    );
}
</script>

<?php endif; // End if not export ?>

<?php 
// Include footer if not exporting
if (empty($export)) {
    include_once '../../includes/footer.php';
}
?>