<?php
/**
 * Main Header Template
 * Deliverance Church Management System
 * 
 * Contains HTML head section and navigation
 */

// Ensure user is logged in for protected pages
if (basename($_SERVER['PHP_SELF']) !== 'login.php' && !isLoggedIn()) {
    header('Location: ' . BASE_URL . 'auth/login.php');
    exit();
}

// Get current page info
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$currentModule = basename(dirname($_SERVER['PHP_SELF']));

// Get church information
$churchInfo = getRecord('church_info', 'id', 1) ?: [
    'church_name' => 'Deliverance Church',
    'logo' => '',
    'yearly_theme' => 'Year of Divine Breakthrough 2025'
];

// Page title logic
$pageTitle = isset($page_title) ? $page_title . ' - ' . $churchInfo['church_name'] : $churchInfo['church_name'] . ' CMS';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Church Management System for <?php echo htmlspecialchars($churchInfo['church_name']); ?>">
    <meta name="author" content="Deliverance Church CMS">
    
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>assets/images/favicon.png">
    
    <!-- CSS Libraries -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="<?php echo BASE_URL; ?>assets/css/style.css" rel="stylesheet">
    <?php if (isset($additional_css)): ?>
        <?php foreach ($additional_css as $css_file): ?>
            <link href="<?php echo BASE_URL . $css_file; ?>" rel="stylesheet">
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- CSS Variables for Church Colors -->
    <style>
        :root {
            --church-red: <?php echo CHURCH_RED; ?>;
            --church-blue: <?php echo CHURCH_BLUE; ?>;
            --church-white: <?php echo CHURCH_WHITE; ?>;
            --church-red-light: <?php echo CHURCH_RED_LIGHT; ?>;
            --church-blue-light: <?php echo CHURCH_BLUE_LIGHT; ?>;
            --church-gray: <?php echo CHURCH_GRAY; ?>;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        
        .navbar-brand {
            font-weight: bold;
            color: var(--church-blue) !important;
        }
        
        .sidebar {
            background: linear-gradient(135deg, var(--church-blue) 0%, var(--church-blue-light) 100%);
            min-height: 100vh;
            width: 250px;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
            transition: all 0.3s ease;
        }
        
        .sidebar.collapsed {
            width: 60px;
        }
        
        .main-content {
            margin-left: 250px;
            transition: margin-left 0.3s ease;
            min-height: 100vh;
        }
        
        .main-content.expanded {
            margin-left: 60px;
        }
        
        .nav-link {
            color: rgba(255, 255, 255, 0.8) !important;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin: 2px 8px;
            transition: all 0.3s ease;
        }
        
        .nav-link:hover, .nav-link.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: white !important;
            transform: translateX(5px);
        }
        
        .btn-church-primary {
            background-color: var(--church-red);
            border-color: var(--church-red);
            color: white;
        }
        
        .btn-church-primary:hover {
            background-color: var(--church-red-light);
            border-color: var(--church-red-light);
            color: white;
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--church-blue) 0%, var(--church-blue-light) 100%);
            color: white;
            border: none;
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(3, 4, 94, 0.05);
        }
        
        .text-church-red { color: var(--church-red) !important; }
        .text-church-blue { color: var(--church-blue) !important; }
        .bg-church-red { background-color: var(--church-red) !important; }
        .bg-church-blue { background-color: var(--church-blue) !important; }
    </style>
</head>
<body>

<?php if (isLoggedIn()): ?>
    <!-- Sidebar Navigation -->
    <div class="sidebar" id="sidebar">
        <!-- Church Logo and Name -->
        <div class="p-3 text-center border-bottom border-light border-opacity-25">
            <?php if (!empty($churchInfo['logo'])): ?>
                <img src="<?php echo BASE_URL . $churchInfo['logo']; ?>" alt="Church Logo" class="img-fluid mb-2" style="max-height: 50px;">
            <?php else: ?>
                <i class="fas fa-church fa-2x text-white mb-2"></i>
            <?php endif; ?>
            <h6 class="text-white mb-0 sidebar-title"><?php echo htmlspecialchars($churchInfo['church_name']); ?></h6>
            <small class="text-white-50 sidebar-subtitle"><?php echo htmlspecialchars($churchInfo['yearly_theme']); ?></small>
        </div>

        <!-- Navigation Menu -->
        <nav class="nav flex-column p-2">
            <?php
            // Generate navigation menu
            foreach (MAIN_MENU as $key => $menu_item) {
                // Check permissions
                if (!in_array('all', $menu_item['permissions']) && 
                    !array_intersect($menu_item['permissions'], [$_SESSION['user_role']])) {
                    continue;
                }
                
                $active = ($currentModule === $key || $currentPage === $key) ? 'active' : '';
                $menuUrl = BASE_URL . $menu_item['url'];
            ?>
                <div class="nav-item">
                    <?php if (isset($menu_item['submenu'])): ?>
                        <!-- Menu with submenu -->
                        <a class="nav-link <?php echo $active; ?>" data-bs-toggle="collapse" href="#submenu-<?php echo $key; ?>" role="button">
                            <i class="<?php echo $menu_item['icon']; ?> me-2"></i>
                            <span class="menu-text"><?php echo $menu_item['title']; ?></span>
                            <i class="fas fa-chevron-down ms-auto"></i>
                        </a>
                        <div class="collapse <?php echo $active ? 'show' : ''; ?>" id="submenu-<?php echo $key; ?>">
                            <div class="ps-4">
                                <?php foreach ($menu_item['submenu'] as $sub_key => $sub_item): ?>
                                    <a href="<?php echo BASE_URL . $sub_item['url']; ?>" class="nav-link py-2">
                                        <i class="fas fa-dot-circle me-2 small"></i>
                                        <span class="menu-text"><?php echo $sub_item['title']; ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Simple menu item -->
                        <a href="<?php echo $menuUrl; ?>" class="nav-link <?php echo $active; ?>">
                            <i class="<?php echo $menu_item['icon']; ?> me-2"></i>
                            <span class="menu-text"><?php echo $menu_item['title']; ?></span>
                        </a>
                    <?php endif; ?>
                </div>
            <?php } ?>
        </nav>

        <!-- Sidebar Footer -->
        <div class="mt-auto p-3 border-top border-light border-opacity-25">
            <div class="d-flex align-items-center">
                <div class="avatar bg-white text-dark rounded-circle me-2 d-flex align-items-center justify-content-center" style="width: 35px; height: 35px;">
                    <i class="fas fa-user"></i>
                </div>
                <div class="flex-grow-1 sidebar-user-info">
                    <div class="text-white small fw-bold"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></div>
                    <div class="text-white-50 small"><?php echo getUserRoleDisplay($_SESSION['user_role']); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="main-content" id="main-content">
        <!-- Top Navigation Bar -->
        <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4">
            <div class="container-fluid">
                <!-- Sidebar Toggle Button -->
                <button class="btn btn-outline-secondary me-3" id="sidebar-toggle" type="button">
                    <i class="fas fa-bars"></i>
                </button>

                <!-- Page Title -->
                <h1 class="navbar-brand mb-0 h4">
                    <?php if (isset($page_icon)): ?>
                        <i class="<?php echo $page_icon; ?> me-2 text-church-blue"></i>
                    <?php endif; ?>
                    <?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Dashboard'; ?>
                </h1>

                <!-- Top Navigation Items -->
                <div class="navbar-nav ms-auto d-flex flex-row">
                    <!-- Quick Actions Dropdown -->
                    <div class="nav-item dropdown me-3">
                        <a class="nav-link dropdown-toggle btn btn-church-primary btn-sm text-white" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-plus me-1"></i> Quick Actions
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php foreach (QUICK_ACTIONS as $action_key => $action): ?>
                                <?php if (hasPermission($action['permissions'][0]) || in_array($_SESSION['user_role'], $action['permissions'])): ?>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo BASE_URL . $action['url']; ?>">
                                            <i class="<?php echo $action['icon']; ?> me-2 text-<?php echo $action['color']; ?>"></i>
                                            <?php echo $action['title']; ?>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <!-- Notifications Dropdown -->
                    <div class="nav-item dropdown me-3">
                        <a class="nav-link position-relative" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-bell fa-lg"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                3
                                <span class="visually-hidden">unread notifications</span>
                            </span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" style="width: 300px;">
                            <li><h6 class="dropdown-header">Recent Notifications</h6></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="#">
                                    <i class="fas fa-birthday-cake text-warning me-2"></i>
                                    <div class="d-inline-block">
                                        <strong>3 Birthdays Today</strong><br>
                                        <small class="text-muted">John Doe, Mary Smith, Peter Johnson</small>
                                    </div>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="#">
                                    <i class="fas fa-tools text-danger me-2"></i>
                                    <div class="d-inline-block">
                                        <strong>Equipment Maintenance Due</strong><br>
                                        <small class="text-muted">Sound system needs servicing</small>
                                    </div>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="#">
                                    <i class="fas fa-user-friends text-info me-2"></i>
                                    <div class="d-inline-block">
                                        <strong>New Visitor Follow-up</strong><br>
                                        <small class="text-muted">Jane Wilson visited last Sunday</small>
                                    </div>
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-center" href="<?php echo BASE_URL; ?>modules/admin/notifications.php">View All Notifications</a></li>
                        </ul>
                    </div>

                    <!-- User Profile Dropdown -->
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                            <div class="avatar bg-church-blue text-white rounded-circle me-2 d-flex align-items-center justify-content-center" style="width: 35px; height: 35px;">
                                <i class="fas fa-user"></i>
                            </div>
                            <span class="d-none d-md-inline"><?php echo htmlspecialchars($_SESSION['first_name']); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">Hi, <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</h6></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>auth/profile.php">
                                    <i class="fas fa-user-circle me-2"></i> My Profile
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>auth/change_password.php">
                                    <i class="fas fa-key me-2"></i> Change Password
                                </a>
                            </li>
                            <?php if ($_SESSION['user_role'] === 'administrator'): ?>
                            <li>
                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>modules/admin/settings.php">
                                    <i class="fas fa-cogs me-2"></i> System Settings
                                </a>
                            </li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>auth/logout.php" onclick="return confirm('Are you sure you want to logout?')">
                                    <i class="fas fa-sign-out-alt me-2"></i> Logout
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Page Content Container -->
        <div class="container-fluid px-4">
            <!-- Display Flash Messages -->
            <?php echo displayFlashMessage(); ?>

            <!-- Breadcrumb Navigation -->
            <?php if (isset($breadcrumb) && !empty($breadcrumb)): ?>
                <nav aria-label="breadcrumb" class="mb-4">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="<?php echo BASE_URL; ?>modules/dashboard/" class="text-decoration-none">
                                <i class="fas fa-home me-1"></i>Dashboard
                            </a>
                        </li>
                        <?php foreach ($breadcrumb as $item): ?>
                            <?php if (isset($item['url'])): ?>
                                <li class="breadcrumb-item">
                                    <a href="<?php echo $item['url']; ?>" class="text-decoration-none"><?php echo htmlspecialchars($item['title']); ?></a>
                                </li>
                            <?php else: ?>
                                <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($item['title']); ?></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ol>
                </nav>
            <?php endif; ?>

            <!-- Page Actions Bar -->
            <?php if (isset($page_actions) && !empty($page_actions)): ?>
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <?php if (isset($page_description)): ?>
                            <p class="text-muted mb-0"><?php echo htmlspecialchars($page_description); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="btn-group">
                        <?php foreach ($page_actions as $action): ?>
                            <a href="<?php echo $action['url']; ?>" 
                               class="btn btn-<?php echo $action['class'] ?? 'primary'; ?> <?php echo $action['size'] ?? ''; ?>">
                                <?php if (isset($action['icon'])): ?>
                                    <i class="<?php echo $action['icon']; ?> me-1"></i>
                                <?php endif; ?>
                                <?php echo $action['title']; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

<?php endif; // End isLoggedIn check ?>

<!-- JavaScript Libraries -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>

<!-- Custom JavaScript -->
<script src="<?php echo BASE_URL; ?>assets/js/main.js"></script>

<script>
// Global JavaScript configurations
const BASE_URL = '<?php echo BASE_URL; ?>';
const USER_ROLE = '<?php echo $_SESSION['user_role'] ?? ''; ?>';
const USER_NAME = '<?php echo htmlspecialchars($_SESSION['first_name'] ?? '') . ' ' . htmlspecialchars($_SESSION['last_name'] ?? ''); ?>';

// Sidebar toggle functionality
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    
    if (sidebarToggle && sidebar && mainContent) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            
            // Save state to localStorage
            const isCollapsed = sidebar.classList.contains('collapsed');
            localStorage.setItem('sidebar-collapsed', isCollapsed);
        });
        
        // Restore sidebar state from localStorage
        const isCollapsed = localStorage.getItem('sidebar-collapsed') === 'true';
        if (isCollapsed) {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('expanded');
        }
    }
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert-dismissible');
        alerts.forEach(alert => {
            if (alert.classList.contains('show')) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }
        });
    }, 5000);
    
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize popovers
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
});

// Utility function to format currency
function formatCurrency(amount) {
    return '<?php echo CURRENCY_SYMBOL; ?> ' + parseFloat(amount).toLocaleString('en-KE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// Utility function to format date
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
}

// Utility function to show loading spinner
function showLoading(element) {
    element.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Loading...';
    element.disabled = true;
}

// Utility function to hide loading spinner
function hideLoading(element, originalText) {
    element.innerHTML = originalText;
    element.disabled = false;
}

// Session timeout warning
let sessionTimeout = <?php echo SESSION_TIMEOUT; ?> * 1000; // Convert to milliseconds
let warningShown = false;

setInterval(function() {
    sessionTimeout -= 60000; // Decrease by 1 minute
    
    // Show warning when 5 minutes remaining
    if (sessionTimeout <= 300000 && !warningShown) {
        warningShown = true;
        if (confirm('Your session will expire in 5 minutes. Click OK to extend your session.')) {
            // Make AJAX call to extend session
            fetch(BASE_URL + 'auth/extend_session.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    sessionTimeout = <?php echo SESSION_TIMEOUT; ?> * 1000;
                    warningShown = false;
                }
            });
        }
    }
    
    // Auto-logout when session expires
    if (sessionTimeout <= 0) {
        alert('Your session has expired. You will be redirected to the login page.');
        window.location.href = BASE_URL + 'auth/logout.php';
    }
}, 60000); // Check every minute
</script>

<?php
// Include additional JavaScript files if specified
if (isset($additional_js) && !empty($additional_js)):
    foreach ($additional_js as $js_file):
?>
    <script src="<?php echo BASE_URL . $js_file; ?>"></script>
<?php
    endforeach;
endif;
?>