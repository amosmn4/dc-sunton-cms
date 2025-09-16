<?php
/**
 * Sidebar Navigation Component
 * Deliverance Church Management System
 * 
 * Standalone sidebar component that can be included in any page
 */

// Ensure this file is not accessed directly
if (!defined('BASE_URL')) {
    die('Direct access not allowed');
}

// Get current page and module for active menu highlighting
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$currentModule = basename(dirname($_SERVER['PHP_SELF']));
$currentPath = $_SERVER['REQUEST_URI'];

// Get church information for sidebar branding
$churchInfo = getRecord('church_info', 'id', 1) ?: [
    'church_name' => 'Deliverance Church',
    'logo' => '',
    'yearly_theme' => 'Year of Divine Breakthrough 2025'
];

// Get user notification counts for badges
$notificationCounts = [
    'birthdays_today' => 0,
    'maintenance_due' => 0,
    'pending_followups' => 0
];

try {
    $db = Database::getInstance();
    
    // Count today's birthdays
    $stmt = $db->executeQuery("
        SELECT COUNT(*) as count 
        FROM members 
        WHERE DATE_FORMAT(date_of_birth, '%m-%d') = DATE_FORMAT(NOW(), '%m-%d')
        AND membership_status = 'active'
    ");
    $result = $stmt->fetch();
    $notificationCounts['birthdays_today'] = $result['count'] ?? 0;
    
    // Count equipment maintenance due (within 30 days)
    $stmt = $db->executeQuery("
        SELECT COUNT(*) as count 
        FROM equipment 
        WHERE next_maintenance_date <= DATE_ADD(NOW(), INTERVAL 30 DAY)
        AND status IN ('good', 'operational', 'needs_attention')
    ");
    $result = $stmt->fetch();
    $notificationCounts['maintenance_due'] = $result['count'] ?? 0;
    
    // Count pending visitor follow-ups
    $stmt = $db->executeQuery("
        SELECT COUNT(*) as count 
        FROM visitors 
        WHERE status = 'follow_up'
        AND DATE(created_at) <= DATE_SUB(NOW(), INTERVAL 3 DAY)
    ");
    $result = $stmt->fetch();
    $notificationCounts['pending_followups'] = $result['count'] ?? 0;
    
} catch (Exception $e) {
    error_log("Error getting notification counts: " . $e->getMessage());
}

// Calculate total notifications
$totalNotifications = array_sum($notificationCounts);

/**
 * Check if menu item is active
 * @param string $menuKey
 * @param array $menuItem
 * @return bool
 */
function isMenuActive($menuKey, $menuItem) {
    global $currentModule, $currentPage, $currentPath;
    
    // Check if current module matches menu key
    if ($currentModule === $menuKey) return true;
    
    // Check if current page matches menu key
    if ($currentPage === $menuKey) return true;
    
    // Check submenu items
    if (isset($menuItem['submenu'])) {
        foreach ($menuItem['submenu'] as $subKey => $subItem) {
            if (strpos($currentPath, $subItem['url']) !== false) {
                return true;
            }
        }
    }
    
    return false;
}

/**
 * Check if user has access to menu item
 * @param array $permissions
 * @return bool
 */
function hasMenuAccess($permissions) {
    if (in_array('all', $permissions)) return true;
    if (!isset($_SESSION['user_role'])) return false;
    
    return in_array($_SESSION['user_role'], $permissions);
}
?>

<!-- Sidebar Navigation -->
<div class="sidebar" id="sidebar">
    <!-- Church Header -->
    <div class="sidebar-header p-3 text-center border-bottom border-light border-opacity-25">
        <!-- Church Logo -->
        <div class="church-logo mb-2">
            <?php if (!empty($churchInfo['logo']) && file_exists(ROOT_PATH . $churchInfo['logo'])): ?>
                <img src="<?php echo BASE_URL . $churchInfo['logo']; ?>" 
                     alt="<?php echo htmlspecialchars($churchInfo['church_name']); ?>" 
                     class="img-fluid" 
                     style="max-height: 50px;">
            <?php else: ?>
                <div class="church-icon bg-white bg-opacity-20 rounded-circle d-inline-flex align-items-center justify-content-center" 
                     style="width: 50px; height: 50px;">
                    <i class="fas fa-church fa-lg text-white"></i>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Church Name and Theme -->
        <div class="church-info">
            <h6 class="text-white mb-1 sidebar-title fw-bold">
                <?php echo htmlspecialchars($churchInfo['church_name']); ?>
            </h6>
            <small class="text-white-50 sidebar-subtitle d-block">
                <?php echo htmlspecialchars($churchInfo['yearly_theme']); ?>
            </small>
        </div>
    </div>

    <!-- Navigation Menu -->
    <nav class="sidebar-nav flex-grow-1 p-2">
        <ul class="nav flex-column">
            <?php foreach (MAIN_MENU as $menuKey => $menuItem): ?>
                <?php if (!hasMenuAccess($menuItem['permissions'])) continue; ?>
                
                <?php 
                $isActive = isMenuActive($menuKey, $menuItem);
                $activeClass = $isActive ? 'active' : '';
                ?>
                
                <li class="nav-item">
                    <?php if (isset($menuItem['submenu'])): ?>
                        <!-- Menu with Submenu -->
                        <a class="nav-link <?php echo $activeClass; ?> d-flex align-items-center justify-content-between" 
                           data-bs-toggle="collapse" 
                           href="#submenu-<?php echo $menuKey; ?>" 
                           role="button"
                           aria-expanded="<?php echo $isActive ? 'true' : 'false'; ?>">
                            <div class="d-flex align-items-center">
                                <i class="<?php echo $menuItem['icon']; ?> me-2"></i>
                                <span class="menu-text"><?php echo $menuItem['title']; ?></span>
                            </div>
                            <i class="fas fa-chevron-down submenu-arrow transition-transform"></i>
                        </a>
                        
                        <!-- Submenu -->
                        <div class="collapse <?php echo $isActive ? 'show' : ''; ?>" id="submenu-<?php echo $menuKey; ?>">
                            <ul class="nav flex-column ps-4">
                                <?php foreach ($menuItem['submenu'] as $subKey => $subItem): ?>
                                    <?php 
                                    $subActive = strpos($currentPath, $subItem['url']) !== false ? 'active' : '';
                                    ?>
                                    <li class="nav-item">
                                        <a href="<?php echo BASE_URL . $subItem['url']; ?>" 
                                           class="nav-link <?php echo $subActive; ?> py-2">
                                            <i class="fas fa-dot-circle me-2 small"></i>
                                            <span class="menu-text"><?php echo $subItem['title']; ?></span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php else: ?>
                        <!-- Simple Menu Item -->
                        <a href="<?php echo BASE_URL . $menuItem['url']; ?>" 
                           class="nav-link <?php echo $activeClass; ?>">
                            <i class="<?php echo $menuItem['icon']; ?> me-2"></i>
                            <span class="menu-text"><?php echo $menuItem['title']; ?></span>
                            
                            <!-- Notification badges for specific menu items -->
                            <?php if ($menuKey === 'dashboard' && $totalNotifications > 0): ?>
                                <span class="badge bg-danger rounded-pill ms-auto"><?php echo $totalNotifications; ?></span>
                            <?php elseif ($menuKey === 'visitors' && $notificationCounts['pending_followups'] > 0): ?>
                                <span class="badge bg-warning rounded-pill ms-auto"><?php echo $notificationCounts['pending_followups']; ?></span>
                            <?php elseif ($menuKey === 'equipment' && $notificationCounts['maintenance_due'] > 0): ?>
                                <span class="badge bg-danger rounded-pill ms-auto"><?php echo $notificationCounts['maintenance_due']; ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        
        <!-- Sidebar Widgets -->
        <div class="sidebar-widgets mt-4">
            <!-- Today's Birthdays Widget -->
            <?php if ($notificationCounts['birthdays_today'] > 0): ?>
                <div class="widget-card bg-white bg-opacity-10 rounded m-2 p-3">
                    <div class="d-flex align-items-center text-white">
                        <i class="fas fa-birthday-cake fa-2x me-3"></i>
                        <div>
                            <h6 class="mb-0">🎉 <?php echo $notificationCounts['birthdays_today']; ?> Birthday<?php echo $notificationCounts['birthdays_today'] > 1 ? 's' : ''; ?></h6>
                            <small class="text-white-50">Today</small>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Quick Stats Widget -->
            <div class="widget-card bg-white bg-opacity-10 rounded m-2 p-3">
                <h6 class="text-white mb-2">
                    <i class="fas fa-chart-line me-2"></i>Quick Stats
                </h6>
                <div class="small text-white-50">
                    <div class="d-flex justify-content-between mb-1">
                        <span>Active Members:</span>
                        <span class="text-white fw-bold"><?php echo getRecordCount('members', ['membership_status' => 'active']); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span>This Month Visitors:</span>
                        <span class="text-white fw-bold">
                            <?php 
                            echo getRecordCount('visitors', [
                                'visit_date >=' => date('Y-m-01'),
                                'visit_date <=' => date('Y-m-t')
                            ]); 
                            ?>
                        </span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Active Equipment:</span>
                        <span class="text-white fw-bold">
                            <?php echo getRecordCount('equipment', ['status' => 'good']) + getRecordCount('equipment', ['status' => 'operational']); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer mt-auto p-3 border-top border-light border-opacity-25">
        <!-- User Profile -->
        <div class="user-profile d-flex align-items-center">
            <div class="user-avatar bg-white text-dark rounded-circle me-2 d-flex align-items-center justify-content-center flex-shrink-0" 
                 style="width: 35px; height: 35px;">
                <i class="fas fa-user"></i>
            </div>
            <div class="user-info flex-grow-1 sidebar-user-info">
                <div class="text-white small fw-bold">
                    <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>
                </div>
                <div class="text-white-50 small">
                    <?php echo getUserRoleDisplay($_SESSION['user_role']); ?>
                </div>
            </div>
            
            <!-- Quick Actions Dropdown in Sidebar -->
            <div class="dropdown">
                <button class="btn btn-sm text-white border-0 p-1" 
                        type="button" 
                        data-bs-toggle="dropdown" 
                        title="Quick Actions">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><h6 class="dropdown-header">Quick Actions</h6></li>
                    <li><hr class="dropdown-divider"></li>
                    <?php foreach (QUICK_ACTIONS as $actionKey => $action): ?>
                        <?php if (hasMenuAccess($action['permissions'])): ?>
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
        </div>
        
        <!-- System Status Indicators -->
        <div class="system-status mt-2 sidebar-system-status">
            <div class="d-flex justify-content-between align-items-center text-white-50 small">
                <div class="d-flex align-items-center">
                    <div class="status-dot bg-success rounded-circle me-1" style="width: 6px; height: 6px;" title="System Online"></div>
                    <span>Online</span>
                </div>
                <div class="text-end">
                    <div><?php echo date('H:i'); ?></div>
                    <div style="font-size: 0.6rem;"><?php echo date('d/m/Y'); ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay d-lg-none" id="sidebar-overlay"></div>

<!-- Sidebar JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebarOverlay = document.getElementById('sidebar-overlay');
    const mainContent = document.getElementById('main-content');
    
    // Sidebar toggle functionality
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            if (window.innerWidth <= 992) {
                // Mobile: Show/hide sidebar with overlay
                sidebar.classList.toggle('show');
                sidebarOverlay.classList.toggle('show');
                document.body.classList.toggle('sidebar-open');
            } else {
                // Desktop: Collapse/expand sidebar
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
                
                // Save state
                localStorage.setItem('sidebar-collapsed', sidebar.classList.contains('collapsed'));
            }
        });
    }
    
    // Close sidebar when clicking overlay (mobile)
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
            document.body.classList.remove('sidebar-open');
        });
    }
    
    // Restore sidebar state on desktop
    if (window.innerWidth > 992) {
        const isCollapsed = localStorage.getItem('sidebar-collapsed') === 'true';
        if (isCollapsed) {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('expanded');
        }
    }
    
    // Handle window resize
    window.addEventListener('resize', ChurchCMS.debounce(function() {
        if (window.innerWidth > 992) {
            // Desktop: Remove mobile classes
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
            document.body.classList.remove('sidebar-open');
        } else {
            // Mobile: Remove desktop classes
            sidebar.classList.remove('collapsed');
            mainContent.classList.remove('expanded');
        }
    }, 250));
    
    // Animate submenu arrows
    const submenuToggles = document.querySelectorAll('[data-bs-toggle="collapse"]');
    submenuToggles.forEach(toggle => {
        const arrow = toggle.querySelector('.submenu-arrow');
        const target = toggle.getAttribute('href');
        const submenu = document.querySelector(target);
        
        if (submenu && arrow) {
            submenu.addEventListener('show.bs.collapse', function() {
                arrow.style.transform = 'rotate(180deg)';
            });
            
            submenu.addEventListener('hide.bs.collapse', function() {
                arrow.style.transform = 'rotate(0deg)';
            });
            
            // Set initial state
            if (submenu.classList.contains('show')) {
                arrow.style.transform = 'rotate(180deg)';
            }
        }
    });
    
    // Update time in sidebar footer every minute
    function updateSidebarTime() {
        const timeElements = document.querySelectorAll('.sidebar-footer .text-end div:first-child');
        timeElements.forEach(element => {
            element.textContent = new Date().toLocaleTimeString('en-GB', {
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            });
        });
    }
    
    setInterval(updateSidebarTime, 60000);
    
    // Highlight active menu items with smooth animation
    const activeLinks = document.querySelectorAll('.sidebar .nav-link.active');
    activeLinks.forEach(link => {
        link.style.background = 'rgba(255, 255, 255, 0.15)';
        link.style.transform = 'translateX(5px)';
        link.style.boxShadow = '0 4px 15px rgba(255, 255, 255, 0.1)';
    });
    
    // Add hover effects to menu items
    const navLinks = document.querySelectorAll('.sidebar .nav-link');
    navLinks.forEach(link => {
        if (!link.classList.contains('active')) {
            link.addEventListener('mouseenter', function() {
                this.style.background = 'rgba(255, 255, 255, 0.1)';
                this.style.transform = 'translateX(3px)';
            });
            
            link.addEventListener('mouseleave', function() {
                this.style.background = '';
                this.style.transform = '';
            });
        }
    });
    
    // Tooltip for collapsed sidebar
    if (sidebar.classList.contains('collapsed')) {
        initializeCollapsedTooltips();
    }
    
    // Watch for sidebar collapse changes
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                if (sidebar.classList.contains('collapsed')) {
                    initializeCollapsedTooltips();
                } else {
                    removeCollapsedTooltips();
                }
            }
        });
    });
    
    observer.observe(sidebar, { attributes: true });
    
    function initializeCollapsedTooltips() {
        const menuItems = sidebar.querySelectorAll('.nav-link:not([data-bs-toggle="collapse"])');
        menuItems.forEach(item => {
            const text = item.querySelector('.menu-text')?.textContent;
            if (text) {
                item.setAttribute('data-bs-toggle', 'tooltip');
                item.setAttribute('data-bs-placement', 'right');
                item.setAttribute('title', text);
                new bootstrap.Tooltip(item);
            }
        });
    }
    
    function removeCollapsedTooltips() {
        const tooltips = sidebar.querySelectorAll('[data-bs-toggle="tooltip"]');
        tooltips.forEach(item => {
            const tooltip = bootstrap.Tooltip.getInstance(item);
            if (tooltip) {
                tooltip.dispose();
            }
            item.removeAttribute('data-bs-toggle');
            item.removeAttribute('data-bs-placement');
            item.removeAttribute('title');
        });
    }
});

// Auto-collapse sidebar on mobile after navigation
document.addEventListener('click', function(e) {
    if (window.innerWidth <= 992 && e.target.closest('.sidebar .nav-link') && !e.target.closest('[data-bs-toggle="collapse"]')) {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        
        setTimeout(() => {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
            document.body.classList.remove('sidebar-open');
        }, 300);
    }
});
</script>

<!-- Additional Sidebar Styles -->
<style>
.sidebar-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 999;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.sidebar-overlay.show {
    opacity: 1;
    visibility: visible;
}

@media (max-width: 992px) {
    .sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
    }
    
    .sidebar.show {
        transform: translateX(0);
    }
    
    body.sidebar-open {
        overflow: hidden;
    }
}

.submenu-arrow {
    transition: transform 0.3s ease;
}

.widget-card {
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.status-dot {
    box-shadow: 0 0 6px currentColor;
}

/* Collapsed sidebar specific styles */
.sidebar.collapsed .church-info {
    display: none;
}

.sidebar.collapsed .sidebar-widgets {
    display: none;
}

.sidebar.collapsed .system-status {
    display: none;
}

.sidebar.collapsed .user-profile .user-info {
    display: none;
}

.sidebar.collapsed .nav-link {
    justify-content: center;
    padding: 0.75rem 0.5rem;
}

.sidebar.collapsed .submenu-arrow {
    display: none;
}

.sidebar.collapsed .badge {
    position: absolute;
    top: 5px;
    right: 5px;
    transform: scale(0.8);
}
</style>