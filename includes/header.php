<?php
/**
 * Main Header Template - Improved Version
 * Deliverance Church Management System
 * 
 * Contains HTML head section and improved navigation with better UX
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
    
    <!-- Inline Styles for Church Colors and Improved Layout -->
    <style>
        :root {
            --church-red: <?php echo CHURCH_RED; ?>;
            --church-blue: <?php echo CHURCH_BLUE; ?>;
            --church-white: <?php echo CHURCH_WHITE; ?>;
            --church-red-light: <?php echo CHURCH_RED_LIGHT; ?>;
            --church-blue-light: <?php echo CHURCH_BLUE_LIGHT; ?>;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: #f5f7fa;
            overflow-x: hidden;
        }
        
        /* Improved Sidebar Styles */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            height: 100vh;
            background: linear-gradient(180deg, var(--church-blue) 0%, #020338 100%);
            z-index: 1040;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 4px 0 24px rgba(0, 0, 0, 0.12);
            display: flex;
            flex-direction: column;
        }
        
        .sidebar.collapsed {
            width: 80px;
        }
        
        /* Sidebar Header */
        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
            background: rgba(0, 0, 0, 0.2);
            flex-shrink: 0;
        }
        
        .sidebar-header .church-logo {
            width: 50px;
            height: 50px;
            margin: 0 auto 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        
        .sidebar-header .church-logo i {
            font-size: 1.75rem;
            color: white;
        }
        
        .sidebar-header h6 {
            color: white;
            font-weight: 600;
            margin-bottom: 0.25rem;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        
        .sidebar-header small {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.75rem;
            transition: all 0.3s ease;
        }
        
        .sidebar.collapsed .sidebar-header h6,
        .sidebar.collapsed .sidebar-header small {
            opacity: 0;
            height: 0;
            overflow: hidden;
        }
        
        /* Sidebar Navigation - Scrollable */
        .sidebar-nav {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 1rem 0.5rem;
        }
        
        .sidebar-nav::-webkit-scrollbar {
            width: 6px;
        }
        
        .sidebar-nav::-webkit-scrollbar-track {
            background: transparent;
        }
        
        .sidebar-nav::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 3px;
        }
        
        .sidebar-nav::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        /* Navigation Items */
        .nav-item {
            margin-bottom: 0.25rem;
        }
        
        .nav-link {
            color: rgba(255, 255, 255, 0.75) !important;
            padding: 0.85rem 1rem;
            border-radius: 10px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            position: relative;
        }
        
        .nav-link i {
            width: 24px;
            margin-right: 12px;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }
        
        .nav-link:hover {
            background: rgba(255, 255, 255, 0.08);
            color: white !important;
            transform: translateX(4px);
        }
        
        .nav-link.active {
            background: linear-gradient(135deg, var(--church-red) 0%, #cc1d00 100%);
            color: white !important;
            box-shadow: 0 4px 12px rgba(255, 36, 0, 0.3);
        }
        
        .nav-link.active i {
            transform: scale(1.1);
        }
        
        /* Submenu Styles */
        .submenu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            padding-left: 0;
        }
        
        .submenu.show {
            max-height: 500px;
        }
        
        .submenu .nav-link {
            padding: 0.65rem 1rem 0.65rem 3rem;
            font-size: 0.85rem;
            font-weight: 400;
            color: rgba(255, 255, 255, 0.7) !important;
        }
        
        .submenu .nav-link::before {
            content: '';
            position: absolute;
            left: 2rem;
            width: 4px;
            height: 4px;
            background: rgba(255, 255, 255, 0.4);
            border-radius: 50%;
            transition: all 0.3s ease;
        }
        
        .submenu .nav-link:hover::before,
        .submenu .nav-link.active::before {
            background: white;
            transform: scale(1.5);
        }
        
        .submenu .nav-link.active {
            background: rgba(255, 255, 255, 0.15) !important;
            color: white !important;
            border-left: 3px solid var(--church-red);
            padding-left: calc(3rem - 3px);
        }
        
        .nav-link .chevron {
            margin-left: auto;
            font-size: 0.75rem;
            transition: transform 0.3s ease;
        }
        
        .nav-link[aria-expanded="true"] .chevron {
            transform: rotate(180deg);
        }
        
        .sidebar.collapsed .menu-text,
        .sidebar.collapsed .chevron {
            opacity: 0;
            width: 0;
        }
        
        .sidebar.collapsed .nav-link {
            justify-content: center;
            padding: 0.85rem 0.5rem;
        }
        
        .sidebar.collapsed .nav-link i {
            margin-right: 0;
        }
        
        .sidebar.collapsed .submenu {
            display: none;
        }
        
        /* Sidebar Footer */
        .sidebar-footer {
            padding: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(0, 0, 0, 0.2);
            flex-shrink: 0;
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .user-profile:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--church-red), var(--church-blue));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            margin-right: 12px;
            flex-shrink: 0;
        }
        
        .user-info {
            flex: 1;
            min-width: 0;
        }
        
        .user-name {
            color: white;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 0.125rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .user-role {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.75rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .sidebar.collapsed .user-info {
            display: none;
        }
        
        /* Main Content Area */
        .main-content {
            margin-left: 280px;
            min-height: 100vh;
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
        }
        
        .main-content.expanded {
            margin-left: 80px;
        }
        
        /* Top Navigation Bar */
        .top-navbar {
            background: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            position: sticky;
            top: 0;
            z-index: 1030;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.5rem;
        }
        
        .navbar-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        #sidebar-toggle {
            background: white;
            border: 1px solid #e5e7eb;
            color: var(--church-blue);
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        #sidebar-toggle:hover {
            background: var(--church-blue);
            color: white;
            border-color: var(--church-blue);
        }
        
        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--church-blue);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .navbar-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        /* Quick Action Button */
        .quick-action-btn {
            background: linear-gradient(135deg, var(--church-red), #cc1d00);
            border: none;
            color: white;
            padding: 0.65rem 1.25rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.875rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(255, 36, 0, 0.2);
        }
        
        .quick-action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(255, 36, 0, 0.3);
        }
        
        /* Notification Icon */
        .notification-icon {
            position: relative;
            width: 40px;
            height: 40px;
            background: #f3f4f6;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .notification-icon:hover {
            background: var(--church-blue);
            color: white;
        }
        
        .notification-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background: var(--church-red);
            color: white;
            font-size: 0.7rem;
            padding: 0.15rem 0.4rem;
            border-radius: 10px;
            font-weight: 700;
            min-width: 20px;
            text-align: center;
        }
        
        /* Content Area */
        .content-wrapper {
            flex: 1;
            padding: 2rem;
        }
        
        /* Mobile Overlay */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1035;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .sidebar-overlay.show {
            display: block;
            opacity: 1;
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            
            .sidebar.mobile-show {
                transform: translateX(0);
            }
            
            .sidebar.collapsed {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .main-content.expanded {
                margin-left: 0;
            }
            
            .top-navbar {
                padding: 1rem;
            }
            
            .page-title {
                font-size: 1.25rem;
            }
            
            .content-wrapper {
                padding: 1rem;
            }
            
            .navbar-right .dropdown-menu {
                right: 0;
                left: auto;
            }
        }
        
        @media (max-width: 576px) {
            .quick-action-btn span {
                display: none;
            }
            
            .quick-action-btn {
                padding: 0.65rem;
                width: 40px;
                height: 40px;
            }
        }
    </style>
</head>
<body>

<?php if (isLoggedIn()): ?>
    <!-- Mobile Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar Navigation -->
    <nav class="sidebar" id="sidebar">
        <!-- Sidebar Header -->
        <div class="sidebar-header">
            <div class="church-logo">
                <?php if (!empty($churchInfo['logo'])): ?>
                    <img src="<?php echo BASE_URL . $churchInfo['logo']; ?>" alt="Logo" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                <?php else: ?>
                    <i class="fas fa-church"></i>
                <?php endif; ?>
            </div>
            <h6><?php echo htmlspecialchars($churchInfo['church_name']); ?></h6>
            <small><?php echo htmlspecialchars($churchInfo['yearly_theme']); ?></small>
        </div>

        <!-- Sidebar Navigation - Scrollable -->
        <div class="sidebar-nav">
            <?php foreach (MAIN_MENU as $key => $menu_item): ?>
                <?php
                // Check permissions
                if (!in_array('all', $menu_item['permissions']) && 
                    !array_intersect($menu_item['permissions'], [$_SESSION['user_role']])) {
                    continue;
                }
                
                $active = ($currentModule === $key) ? 'active' : '';
                $hasSubmenu = isset($menu_item['submenu']);
                $menuId = 'menu-' . $key;
                ?>
                
                <div class="nav-item">
                    <?php if ($hasSubmenu): ?>
                        <a href="#<?php echo $menuId; ?>" class="nav-link <?php echo $active; ?>" 
                           data-bs-toggle="collapse" role="button" 
                           aria-expanded="<?php echo $active ? 'true' : 'false'; ?>" 
                           data-menu-parent>
                            <i class="<?php echo $menu_item['icon']; ?>"></i>
                            <span class="menu-text"><?php echo $menu_item['title']; ?></span>
                            <i class="fas fa-chevron-down chevron"></i>
                        </a>
                        <div class="submenu collapse <?php echo $active ? 'show' : ''; ?>" id="<?php echo $menuId; ?>" data-bs-parent=".sidebar-nav">
                            <?php foreach ($menu_item['submenu'] as $sub_key => $sub_item): ?>
                                <a href="<?php echo BASE_URL . $sub_item['url']; ?>" class="nav-link">
                                    <span class="menu-text"><?php echo $sub_item['title']; ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <a href="<?php echo BASE_URL . $menu_item['url']; ?>" class="nav-link <?php echo $active; ?>">
                            <i class="<?php echo $menu_item['icon']; ?>"></i>
                            <span class="menu-text"><?php echo $menu_item['title']; ?></span>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Sidebar Footer -->
        <div class="sidebar-footer">
            <div class="user-profile">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1) . substr($_SESSION['last_name'], 0, 1)); ?>
                </div>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></div>
                    <div class="user-role"><?php echo getUserRoleDisplay($_SESSION['user_role']); ?></div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content Area -->
    <div class="main-content" id="mainContent">
        <!-- Top Navigation Bar -->
        <div class="top-navbar">
            <div class="navbar-left">
                <button class="btn" id="sidebarToggle" type="button" title="Toggle Sidebar">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="page-title">
                    <?php if (isset($page_icon)): ?>
                        <i class="<?php echo $page_icon; ?>"></i>
                    <?php endif; ?>
                    <?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Dashboard'; ?>
                </h1>
            </div>
            
            <div class="navbar-right">
                <!-- Quick Actions Dropdown -->
                <div class="dropdown">
                    <button class="quick-action-btn dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-plus me-1"></i><span>Quick Actions</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" style="min-width: 250px;">
                        <?php foreach (QUICK_ACTIONS as $action_key => $action): ?>
                            <?php if (in_array($_SESSION['user_role'], $action['permissions'])): ?>
                                <li>
                                    <a class="dropdown-item py-2" href="<?php echo BASE_URL . $action['url']; ?>">
                                        <i class="<?php echo $action['icon']; ?> me-2 text-<?php echo $action['color']; ?>"></i>
                                        <?php echo $action['title']; ?>
                                    </a>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- Notifications -->
                <div class="dropdown">
                    <div class="notification-icon" data-bs-toggle="dropdown" role="button">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge">3</span>
                    </div>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" style="width: 320px; max-height: 400px; overflow-y: auto;">
                        <li><h6 class="dropdown-header">Notifications</h6></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item py-2" href="#">
                                <div class="d-flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-birthday-cake text-warning"></i>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <strong class="d-block">3 Birthdays Today</strong>
                                        <small class="text-muted">John, Mary, Peter</small>
                                    </div>
                                </div>
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-center small text-primary" href="#">View All Notifications</a></li>
                    </ul>
                </div>

                <!-- User Profile Dropdown -->
                <div class="dropdown">
                    <div class="user-avatar" data-bs-toggle="dropdown" role="button" style="cursor: pointer;">
                        <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1) . substr($_SESSION['last_name'], 0, 1)); ?>
                    </div>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0">
                        <li><h6 class="dropdown-header">Hi, <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</h6></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>auth/profile.php"><i class="fas fa-user me-2"></i>My Profile</a></li>
                        <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>auth/change_password.php"><i class="fas fa-key me-2"></i>Change Password</a></li>
                        <?php if ($_SESSION['user_role'] === 'administrator'): ?>
                        <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>modules/admin/settings.php"><i class="fas fa-cogs me-2"></i>Settings</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Page Content Container -->
        <div class="content-wrapper">
            <!-- Display Flash Messages -->
            <?php echo displayFlashMessage(); ?>

            <!-- Breadcrumb Navigation -->
            <?php if (isset($breadcrumb) && !empty($breadcrumb)): ?>
                <nav aria-label="breadcrumb" class="mb-4">
                    <ol class="breadcrumb bg-white px-3 py-2 rounded shadow-sm">
                        <li class="breadcrumb-item">
                            <a href="<?php echo BASE_URL; ?>modules/dashboard/"><i class="fas fa-home me-1"></i>Dashboard</a>
                        </li>
                        <?php foreach ($breadcrumb as $item): ?>
                            <?php if (isset($item['url'])): ?>
                                <li class="breadcrumb-item"><a href="<?php echo $item['url']; ?>"><?php echo htmlspecialchars($item['title']); ?></a></li>
                            <?php else: ?>
                                <li class="breadcrumb-item active"><?php echo htmlspecialchars($item['title']); ?></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ol>
                </nav>
            <?php endif; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="<?php echo BASE_URL; ?>assets/js/main.js"></script>

<script>
// Sidebar functionality
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    
    // Toggle sidebar
    sidebarToggle.addEventListener('click', function() {
        if (window.innerWidth > 768) {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        } else {
            sidebar.classList.toggle('mobile-show');
            sidebarOverlay.classList.toggle('show');
        }
    });
    
    // Close sidebar when clicking overlay
    sidebarOverlay.addEventListener('click', function() {
        sidebar.classList.remove('mobile-show');
        sidebarOverlay.classList.remove('show');
    });
    
    // Restore sidebar state
    const savedState = localStorage.getItem('sidebarCollapsed');
    if (savedState === 'true' && window.innerWidth > 768) {
        sidebar.classList.add('collapsed');
        mainContent.classList.add('expanded');
    }
    
    // Auto-close other menus when opening a new one
    const menuLinks = document.querySelectorAll('[data-menu-parent]');
    menuLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const targetId = this.getAttribute('href');
            const targetMenu = document.querySelector(targetId);
            
            // Close all other open menus
            document.querySelectorAll('.submenu.show').forEach(menu => {
                if (menu !== targetMenu) {
                    const collapse = new bootstrap.Collapse(menu, { toggle: false });
                    collapse.hide();
                }
            });
        });
    });
    
    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            sidebar.classList.remove('mobile-show');
            sidebarOverlay.classList.remove('show');
        }
    });
});
</script>

<?php endif; // End isLoggedIn check ?>