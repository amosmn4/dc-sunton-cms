<?php
/**
 * Attendance Reports Page
 * Deliverance Church Management System
 * 
 * Comprehensive attendance analytics and reports
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
$page_title = 'Attendance Reports';
$page_icon = 'fas fa-chart-bar';
$breadcrumb = [
    ['title' => 'Attendance Management', 'url' => BASE_URL . 'modules/attendance/'],
    ['title' => 'Reports & Analytics']
];

$db = Database::getInstance();

// Get report parameters
$reportType = $_GET['report'] ?? 'summary';
$dateFrom = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$dateTo = $_GET['date_to'] ?? date('Y-m-d'); // Today
$department = $_GET['department'] ?? '';
$eventType = $_GET['event_type'] ?? '';

// Validate date range
if (strtotime($dateFrom) > strtotime($dateTo)) {
    $temp = $dateFrom;
    $dateFrom = $dateTo;
    $dateTo = $temp;
}

// Build base query conditions
$whereConditions = [];
$params = [];

$whereConditions[] = "e.event_date BETWEEN ? AND ?";
$params[] = $dateFrom;
$params[] = $dateTo;

if (!empty($department)) {
    $whereConditions[] = "d.name = ?";
    $params[] = $department;
}

if (!empty($eventType)) {
    $whereConditions[] = "e.event_type = ?";
    $params[] = $eventType;
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// Generate reports based on type
$reportData = [];

try {
    switch ($reportType) {
        case 'summary':
            $reportData = generateSummaryReport($db, $whereClause, $params);
            break;
        case 'trends':
            $reportData = generateTrendsReport($db, $whereClause, $params);
            break;
        case 'individual':
            $reportData = generateIndividualReport($db, $whereClause, $params);
            break;
        case 'department':
            $reportData = generateDepartmentReport($db, $whereClause, $params);
            break;
        case 'service_comparison':
            $reportData = generateServiceComparisonReport($db, $whereClause, $params);
            break;
        default:
            $reportData = generateSummaryReport($db, $whereClause, $params);
    }
} catch (Exception $e) {
    error_log("Error generating report: " . $e->getMessage());
    setFlashMessage('error', 'Error generating report: ' . $e->getMessage());
    $reportData = [];
}

// Get departments for filter
$departments = getRecords('departments', ['is_active' => 1], 'name ASC');

include '../../includes/header.php';
?>

<div class="row mb-4">
    <!-- Report Filters -->
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-filter me-2"></i>Report Filters
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <input type="hidden" name="report" value="<?php echo htmlspecialchars($reportType); ?>">
                    
                    <div class="col-md-2">
                        <label for="report_type" class="form-label">Report Type</label>
                        <select class="form-select" id="report_type" name="report" onchange="this.form.submit()">
                            <option value="summary" <?php echo ($reportType === 'summary') ? 'selected' : ''; ?>>Summary</option>
                            <option value="trends" <?php echo ($reportType === 'trends') ? 'selected' : ''; ?>>Trends</option>
                            <option value="individual" <?php echo ($reportType === 'individual') ? 'selected' : ''; ?>>Individual</option>
                            <option value="department" <?php echo ($reportType === 'department') ? 'selected' : ''; ?>>Department</option>
                            <option value="service_comparison" <?php echo ($reportType === 'service_comparison') ? 'selected' : ''; ?>>Service Comparison</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="date_from" class="form-label">Date From</label>
                        <input type="date" class="form-control" id="date_from" name="date_from" 
                               value="<?php echo $dateFrom; ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label for="date_to" class="form-label">Date To</label>
                        <input type="date" class="form-control" id="date_to" name="date_to" 
                               value="<?php echo $dateTo; ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label for="department" class="form-label">Department</label>
                        <select class="form-select" id="department" name="department">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept['name']); ?>" 
                                    <?php echo ($department === $dept['name']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="event_type" class="form-label">Event Type</label>
                        <select class="form-select" id="event_type" name="event_type">
                            <option value="">All Types</option>
                            <?php foreach (EVENT_TYPES as $key => $type): ?>
                            <option value="<?php echo $key; ?>" 
                                    <?php echo ($eventType === $key) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label d-block">&nbsp;</label>
                        <button type="submit" class="btn btn-church-primary">
                            <i class="fas fa-chart-bar me-1"></i>Generate
                        </button>
                        <button type="button" class="btn btn-outline-success ms-1" onclick="exportReport()">
                            <i class="fas fa-download"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Report Content -->
<div class="row">
    <div class="col-12">
        <?php if (empty($reportData)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-chart-line fa-4x text-muted mb-4"></i>
                    <h4 class="text-muted">No Data Available</h4>
                    <p class="text-muted">No attendance data found for the selected criteria.</p>
                    <a href="<?php echo BASE_URL; ?>modules/attendance/record.php" class="btn btn-church-primary">
                        <i class="fas fa-plus me-2"></i>Record Attendance
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Render specific report based on type -->
            <?php
            switch ($reportType) {
                case 'summary':
                    include 'reports/summary_report.php';
                    break;
                case 'trends':
                    include 'reports/trends_report.php';
                    break;
                case 'individual':
                    include 'reports/individual_report.php';
                    break;
                case 'department':
                    include 'reports/department_report.php';
                    break;
                case 'service_comparison':
                    include 'reports/service_comparison_report.php';
                    break;
                default:
                    include 'reports/summary_report.php';
            }
            ?>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Quick date range buttons
    const dateRangeButtons = document.querySelectorAll('.date-range-btn');
    dateRangeButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const range = this.dataset.range;
            setDateRange(range);
        });
    });
    
    // Auto-submit form when date range changes
    const dateInputs = document.querySelectorAll('#date_from, #date_to');
    dateInputs.forEach(input => {
        input.addEventListener('change', function() {
            // Auto-submit after a short delay
            setTimeout(() => {
                document.querySelector('form').submit();
            }, 500);
        });
    });
});

function setDateRange(range) {
    const dateFromInput = document.getElementById('date_from');
    const dateToInput = document.getElementById('date_to');
    const today = new Date();
    
    let startDate, endDate;
    
    switch (range) {
        case 'today':
            startDate = endDate = today;
            break;
        case 'yesterday':
            startDate = endDate = new Date(today.setDate(today.getDate() - 1));
            break;
        case 'this_week':
            startDate = new Date(today.setDate(today.getDate() - today.getDay()));
            endDate = new Date(startDate);
            endDate.setDate(startDate.getDate() + 6);
            break;
        case 'last_week':
            endDate = new Date(today.setDate(today.getDate() - today.getDay() - 1));
            startDate = new Date(endDate);
            startDate.setDate(endDate.getDate() - 6);
            break;
        case 'this_month':
            startDate = new Date(today.getFullYear(), today.getMonth(), 1);
            endDate = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            break;
        case 'last_month':
            startDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            endDate = new Date(today.getFullYear(), today.getMonth(), 0);
            break;
        case 'this_year':
            startDate = new Date(today.getFullYear(), 0, 1);
            endDate = today;
            break;
        case 'last_30_days':
            endDate = today;
            startDate = new Date(today.setDate(today.getDate() - 30));
            break;
        default:
            return;
    }
    
    dateFromInput.value = startDate.toISOString().split('T')[0];
    dateToInput.value = endDate.toISOString().split('T')[0];
    
    // Submit form
    document.querySelector('form').submit();
}

function exportReport() {
    const params = new URLSearchParams(window.location.search);
    params.set('action', 'export');
    params.set('format', 'excel'); // Default to Excel
    
    const format = prompt('Export format:\n1. Excel (.xlsx)\n2. CSV\n3. PDF\n\nEnter 1, 2, or 3:', '1');
    
    if (!format || !['1', '2', '3'].includes(format)) {
        return;
    }
    
    const formatMap = { '1': 'excel', '2': 'csv', '3': 'pdf' };
    params.set('format', formatMap[format]);
    
    const exportUrl = `<?php echo BASE_URL; ?>api/attendance.php?${params.toString()}`;
    
    ChurchCMS.showLoading('Preparing report export...');
    
    fetch(exportUrl)
        .then(response => {
            if (!response.ok) throw new Error('Export failed');
            return response.blob();
        })
        .then(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `attendance_report_${formatMap[format]}_${new Date().toISOString().split('T')[0]}.${formatMap[format] === 'excel' ? 'xlsx' : formatMap[format]}`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
            
            ChurchCMS.hideLoading();
            ChurchCMS.showToast('Report exported successfully!', 'success');
        })
        .catch(error => {
            ChurchCMS.hideLoading();
            ChurchCMS.showToast('Export failed. Please try again.', 'error');
        });
}

function printReport() {
    // Hide elements that shouldn't be printed
    const noPrintElements = document.querySelectorAll('.no-print, .card-header, .btn');
    noPrintElements.forEach(el => el.style.display = 'none');
    
    window.print();
    
    // Restore elements after printing
    setTimeout(() => {
        noPrintElements.forEach(el => el.style.display = '');
    }, 1000);
}
</script>

<?php include '../../includes/footer.php'; ?>

<?php
/**
 * Generate summary report data
 * @param Database $db
 * @param string $whereClause
 * @param array $params
 * @return array
 */
function generateSummaryReport($db, $whereClause, $params) {
    return [
        'overview' => $db->executeQuery("
            SELECT 
                COUNT(DISTINCT e.id) as total_events,
                COUNT(DISTINCT CASE WHEN e.status = 'completed' THEN e.id END) as completed_events,
                ROUND(AVG(
                    COALESCE(
                        (SELECT SUM(count_number) FROM attendance_counts ac WHERE ac.event_id = e.id AND ac.attendance_category = 'total'),
                        (SELECT COUNT(*) FROM attendance_records ar WHERE ar.event_id = e.id AND ar.is_present = 1)
                    )
                ), 0) as avg_attendance,
                SUM(
                    COALESCE(
                        (SELECT SUM(count_number) FROM attendance_counts ac WHERE ac.event_id = e.id AND ac.attendance_category = 'total'),
                        (SELECT COUNT(*) FROM attendance_records ar WHERE ar.event_id = e.id AND ar.is_present = 1)
                    )
                ) as total_attendance
            FROM events e
            LEFT JOIN departments d ON e.department_id = d.id
            {$whereClause}
        ", $params)->fetch(),
        
        'by_type' => $db->executeQuery("
            SELECT 
                e.event_type,
                COUNT(*) as event_count,
                ROUND(AVG(
                    COALESCE(
                        (SELECT SUM(count_number) FROM attendance_counts ac WHERE ac.event_id = e.id AND ac.attendance_category = 'total'),
                        (SELECT COUNT(*) FROM attendance_records ar WHERE ar.event_id = e.id AND ar.is_present = 1)
                    )
                ), 0) as avg_attendance,
                SUM(
                    COALESCE(
                        (SELECT SUM(count_number) FROM attendance_counts ac WHERE ac.event_id = e.id AND ac.attendance_category = 'total'),
                        (SELECT COUNT(*) FROM attendance_records ar WHERE ar.event_id = e.id AND ar.is_present = 1)
                    )
                ) as total_attendance
            FROM events e
            LEFT JOIN departments d ON e.department_id = d.id
            {$whereClause}
            GROUP BY e.event_type
            ORDER BY total_attendance DESC
        ", $params)->fetchAll(),
        
        'by_day' => $db->executeQuery("
            SELECT 
                DAYNAME(e.event_date) as day_name,
                DAYOFWEEK(e.event_date) as day_number,
                COUNT(*) as event_count,
                ROUND(AVG(
                    COALESCE(
                        (SELECT SUM(count_number) FROM attendance_counts ac WHERE ac.event_id = e.id AND ac.attendance_category = 'total'),
                        (SELECT COUNT(*) FROM attendance_records ar WHERE ar.event_id = e.id AND ar.is_present = 1)
                    )
                ), 0) as avg_attendance
            FROM events e
            LEFT JOIN departments d ON e.department_id = d.id
            {$whereClause}
            GROUP BY DAYOFWEEK(e.event_date), DAYNAME(e.event_date)
            ORDER BY day_number
        ", $params)->fetchAll()
    ];
}

/**
 * Generate trends report data
 * @param Database $db
 * @param string $whereClause
 * @param array $params
 * @return array
 */
function generateTrendsReport($db, $whereClause, $params) {
    return [
        'daily_trends' => $db->executeQuery("
            SELECT 
                e.event_date,
                COUNT(*) as event_count,
                SUM(
                    COALESCE(
                        (SELECT SUM(count_number) FROM attendance_counts ac WHERE ac.event_id = e.id AND ac.attendance_category = 'total'),
                        (SELECT COUNT(*) FROM attendance_records ar WHERE ar.event_id = e.id AND ar.is_present = 1)
                    )
                ) as total_attendance,
                ROUND(AVG(
                    COALESCE(
                        (SELECT SUM(count_number) FROM attendance_counts ac WHERE ac.event_id = e.id AND ac.attendance_category = 'total'),
                        (SELECT COUNT(*) FROM attendance_records ar WHERE ar.event_id = e.id AND ar.is_present = 1)
                    )
                ), 0) as avg_attendance
            FROM events e
            LEFT JOIN departments d ON e.department_id = d.id
            {$whereClause}
            GROUP BY e.event_date
            ORDER BY e.event_date ASC
        ", $params)->fetchAll(),
        
        'growth_rate' => $db->executeQuery("
            SELECT 
                DATE_FORMAT(e.event_date, '%Y-%m') as month_year,
                COUNT(*) as event_count,
                ROUND(AVG(
                    COALESCE(
                        (SELECT SUM(count_number) FROM attendance_counts ac WHERE ac.event_id = e.id AND ac.attendance_category = 'total'),
                        (SELECT COUNT(*) FROM attendance_records ar WHERE ar.event_id = e.id AND ar.is_present = 1)
                    )
                ), 0) as avg_attendance
            FROM events e
            LEFT JOIN departments d ON e.department_id = d.id
            {$whereClause}
            GROUP BY DATE_FORMAT(e.event_date, '%Y-%m')
            ORDER BY month_year ASC
        ", $params)->fetchAll()
    ];
}

/**
 * Generate individual attendance report
 * @param Database $db
 * @param string $whereClause
 * @param array $params
 * @return array
 */
function generateIndividualReport($db, $whereClause, $params) {
    return $db->executeQuery("
        SELECT 
            m.id,
            m.member_number,
            m.first_name,
            m.last_name,
            m.phone,
            dept.name as department_name,
            COUNT(ar.id) as events_attended,
            COUNT(e.id) as total_events_in_period,
            ROUND((COUNT(ar.id) / COUNT(e.id)) * 100, 1) as attendance_percentage,
            MAX(ar.check_in_time) as last_attendance
        FROM members m
        CROSS JOIN events e
        LEFT JOIN departments dept ON dept.id = (
            SELECT md.department_id FROM member_departments md 
            WHERE md.member_id = m.id AND md.is_active = 1 LIMIT 1
        )
        LEFT JOIN attendance_records ar ON m.id = ar.member_id AND e.id = ar.event_id AND ar.is_present = 1
        LEFT JOIN departments d ON e.department_id = d.id
        {$whereClause} AND m.membership_status = 'active'
        GROUP BY m.id, m.member_number, m.first_name, m.last_name, m.phone, dept.name
        HAVING total_events_in_period > 0
        ORDER BY attendance_percentage DESC, m.first_name, m.last_name
    ", $params)->fetchAll();
}

/**
 * Generate department report data
 * @param Database $db
 * @param string $whereClause
 * @param array $params
 * @return array
 */
function generateDepartmentReport($db, $whereClause, $params) {
    return $db->executeQuery("
        SELECT 
            COALESCE(dept.name, 'No Department') as department_name,
            COUNT(DISTINCT m.id) as total_members,
            COUNT(DISTINCT ar.member_id) as attending_members,
            COUNT(ar.id) as total_attendances,
            COUNT(DISTINCT e.id) as total_events,
            ROUND(AVG(
                CASE WHEN ar.is_present = 1 THEN 1 ELSE 0 END
            ) * 100, 1) as attendance_rate,
            ROUND(COUNT(ar.id) / COUNT(DISTINCT e.id), 1) as avg_attendance_per_event
        FROM events e
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN attendance_records ar ON e.id = ar.event_id
        LEFT JOIN members m ON ar.member_id = m.id
        LEFT JOIN member_departments md ON m.id = md.member_id AND md.is_active = 1
        LEFT JOIN departments dept ON md.department_id = dept.id
        {$whereClause}
        GROUP BY dept.id, dept.name
        ORDER BY attendance_rate DESC
    ", $params)->fetchAll();
}

/**
 * Generate service comparison report
 * @param Database $db
 * @param string $whereClause
 * @param array $params
 * @return array
 */
function generateServiceComparisonReport($db, $whereClause, $params) {
    return [
        'comparison' => $db->executeQuery("
            SELECT 
                e.event_type,
                COUNT(*) as event_count,
                MIN(
                    COALESCE(
                        (SELECT SUM(count_number) FROM attendance_counts ac WHERE ac.event_id = e.id AND ac.attendance_category = 'total'),
                        (SELECT COUNT(*) FROM attendance_records ar WHERE ar.event_id = e.id AND ar.is_present = 1)
                    )
                ) as min_attendance,
                MAX(
                    COALESCE(
                        (SELECT SUM(count_number) FROM attendance_counts ac WHERE ac.event_id = e.id AND ac.attendance_category = 'total'),
                        (SELECT COUNT(*) FROM attendance_records ar WHERE ar.event_id = e.id AND ar.is_present = 1)
                    )
                ) as max_attendance,
                ROUND(AVG(
                    COALESCE(
                        (SELECT SUM(count_number) FROM attendance_counts ac WHERE ac.event_id = e.id AND ac.attendance_category = 'total'),
                        (SELECT COUNT(*) FROM attendance_records ar WHERE ar.event_id = e.id AND ar.is_present = 1)
                    )
                ), 1) as avg_attendance,
                SUM(
                    COALESCE(
                        (SELECT SUM(count_number) FROM attendance_counts ac WHERE ac.event_id = e.id AND ac.attendance_category = 'total'),
                        (SELECT COUNT(*) FROM attendance_records ar WHERE ar.event_id = e.id AND ar.is_present = 1)
                    )
                ) as total_attendance
            FROM events e
            LEFT JOIN departments d ON e.department_id = d.id
            {$whereClause}
            GROUP BY e.event_type
            ORDER BY avg_attendance DESC
        ", $params)->fetchAll(),
        
        'performance_trend' => $db->executeQuery("
            SELECT 
                e.event_type,
                DATE_FORMAT(e.event_date, '%Y-%m') as month_year,
                ROUND(AVG(
                    COALESCE(
                        (SELECT SUM(count_number) FROM attendance_counts ac WHERE ac.event_id = e.id AND ac.attendance_category = 'total'),
                        (SELECT COUNT(*) FROM attendance_records ar WHERE ar.event_id = e.id AND ar.is_present = 1)
                    )
                ), 1) as avg_attendance
            FROM events e
            LEFT JOIN departments d ON e.department_id = d.id
            {$whereClause}
            GROUP BY e.event_type, DATE_FORMAT(e.event_date, '%Y-%m')
            ORDER BY month_year ASC, e.event_type
        ", $params)->fetchAll()
    ];
}
?>