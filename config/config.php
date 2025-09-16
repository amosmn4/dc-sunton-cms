<?php
/**
 * Main Configuration File
 * Deliverance Church Management System
 * 
 * Contains all system constants, settings, and configurations
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Environment setting (development, staging, production)
define('ENVIRONMENT', 'development'); // Change to 'production' for live site

// =====================================================
// SYSTEM PATHS AND URLs
// =====================================================

// Base URL - Change this to your domain
define('BASE_URL', 'http://localhost/church-cms/');
define('SITE_URL', BASE_URL);

// System paths
define('ROOT_PATH', dirname(__DIR__) . '/');
define('CONFIG_PATH', ROOT_PATH . 'config/');
define('INCLUDES_PATH', ROOT_PATH . 'includes/');
define('MODULES_PATH', ROOT_PATH . 'modules/');
define('ASSETS_PATH', ROOT_PATH . 'assets/');

// Upload paths
define('UPLOAD_PATH', 'assets/uploads/');
define('MEMBER_PHOTOS_PATH', UPLOAD_PATH . 'members/');
define('RECEIPTS_PATH', UPLOAD_PATH . 'receipts/');
define('DOCUMENTS_PATH', UPLOAD_PATH . 'documents/');
define('EQUIPMENT_PHOTOS_PATH', UPLOAD_PATH . 'equipment/');

// Backup path
define('BACKUP_PATH', ROOT_PATH . 'backup/');

// =====================================================
// CHURCH BRANDING AND COLORS
// =====================================================

// Church colors (from requirements)
define('CHURCH_RED', '#ff2400');
define('CHURCH_BLUE', '#03045e');
define('CHURCH_WHITE', '#ffffff');

// Additional color variations
define('CHURCH_RED_LIGHT', '#ff4d33');
define('CHURCH_BLUE_LIGHT', '#1e3c72');
define('CHURCH_GRAY', '#6c757d');
define('CHURCH_SUCCESS', '#28a745');
define('CHURCH_WARNING', '#ffc107');
define('CHURCH_DANGER', '#dc3545');

// =====================================================
// FILE UPLOAD SETTINGS
// =====================================================

// File size limits (in bytes)
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('MAX_PHOTO_SIZE', 2 * 1024 * 1024); // 2MB for photos
define('MAX_DOCUMENT_SIZE', 10 * 1024 * 1024); // 10MB for documents

// Allowed file types
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('ALLOWED_DOCUMENT_TYPES', ['pdf', 'doc', 'docx', 'xls', 'xlsx']);

// =====================================================
// CURRENCY AND LOCALIZATION
// =====================================================

// Default currency (Kenya Shilling as per requirements)
define('DEFAULT_CURRENCY', 'KES');
define('CURRENCY_SYMBOL', 'Ksh');
define('CURRENCY_FORMAT', 'Ksh %s');

// Timezone
define('DEFAULT_TIMEZONE', 'Africa/Nairobi');
date_default_timezone_set(DEFAULT_TIMEZONE);

// Date formats
define('DATE_FORMAT', 'Y-m-d');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');
define('DISPLAY_DATE_FORMAT', 'd/m/Y');
define('DISPLAY_DATETIME_FORMAT', 'd/m/Y H:i');

// =====================================================
// SMS CONFIGURATION
// =====================================================

// SMS API Configuration (Replace with your SMS provider details)
define('SMS_API_URL', 'https://api.africastalking.com/version1/messaging');
define('SMS_API_KEY', 'your_sms_api_key_here');
define('SMS_USERNAME', 'your_sms_username_here');
define('SMS_SENDER_ID', 'CHURCH');
define('SMS_COST_PER_SMS', 1.00); // Cost in your currency

// SMS limits
define('SMS_DAILY_LIMIT', 1000);
define('SMS_BATCH_SIZE', 100);

// =====================================================
// EMAIL CONFIGURATION
// =====================================================

// SMTP Settings
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'your_church_email@gmail.com');
define('MAIL_PASSWORD', 'your_email_password');
define('MAIL_FROM_NAME', 'Deliverance Church');
define('MAIL_FROM_EMAIL', 'noreply@deliverancechurch.org');

// =====================================================
// SECURITY SETTINGS
// =====================================================

// Password requirements
define('MIN_PASSWORD_LENGTH', 8);
define('PASSWORD_REQUIRE_UPPERCASE', true);
define('PASSWORD_REQUIRE_LOWERCASE', true);
define('PASSWORD_REQUIRE_NUMBER', true);
define('PASSWORD_REQUIRE_SPECIAL', true);

// Session settings
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds
define('REMEMBER_ME_DURATION', 30 * 24 * 3600); // 30 days

// Login security
define('MAX_LOGIN_ATTEMPTS', 3);
define('LOCKOUT_DURATION', 1800); // 30 minutes in seconds

// Encryption
define('ENCRYPTION_KEY', 'your-32-character-secret-key-here'); // Change this!

// =====================================================
// PAGINATION AND LIMITS
// =====================================================

define('DEFAULT_PAGE_SIZE', 25);
define('MAX_PAGE_SIZE', 100);
define('PAGINATION_RANGE', 5);

// =====================================================
// BACKUP SETTINGS
// =====================================================

define('AUTO_BACKUP_ENABLED', true);
define('BACKUP_FREQUENCY_DAYS', 7);
define('MAX_BACKUP_FILES', 10);

// =====================================================
// NOTIFICATION SETTINGS
// =====================================================

// Birthday reminders
define('BIRTHDAY_REMINDER_DAYS', 7); // Days before birthday to send reminder
define('ANNIVERSARY_REMINDER_DAYS', 7);

// Equipment maintenance reminders
define('MAINTENANCE_REMINDER_DAYS', 30); // Days before maintenance due

// =====================================================
// SYSTEM FEATURES FLAGS
// =====================================================

define('ENABLE_SMS_MODULE', true);
define('ENABLE_EMAIL_MODULE', true);
define('ENABLE_VISITOR_MODULE', true);
define('ENABLE_EQUIPMENT_MODULE', true);
define('ENABLE_FINANCE_MODULE', true);
define('ENABLE_REPORTS_MODULE', true);
define('ENABLE_AI_FEATURES', false); // Will be enabled later
define('ENABLE_API_ACCESS', true);

// =====================================================
// ERROR HANDLING
// =====================================================

// Error reporting based on environment
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', ROOT_PATH . 'logs/error.log');
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', ROOT_PATH . 'logs/error.log');
}

// =====================================================
// CHURCH SPECIFIC SETTINGS
// =====================================================

// Default service times
define('SUNDAY_SERVICE_TIME', '09:00:00');
define('PRAYER_MEETING_TIME', '18:00:00');
define('BIBLE_STUDY_TIME', '19:00:00');

// Age group definitions
define('CHILD_MAX_AGE', 12);
define('TEEN_MAX_AGE', 17);
define('YOUTH_MAX_AGE', 35);
define('SENIOR_MIN_AGE', 60);

// Membership statuses
define('MEMBERSHIP_STATUSES', [
    'active' => 'Active Member',
    'inactive' => 'Inactive Member',
    'transferred' => 'Transferred',
    'deceased' => 'Deceased'
]);

// =====================================================
// UTILITY FUNCTIONS
// =====================================================

/**
 * Get current timestamp in system timezone
 * @return string
 */
function getCurrentTimestamp() {
    return date(DATETIME_FORMAT);
}

/**
 * Format currency amount
 * @param float $amount
 * @return string
 */
function formatCurrency($amount) {
    return sprintf(CURRENCY_FORMAT, number_format($amount, 2));
}

/**
 * Format date for display
 * @param string $date
 * @return string
 */
function formatDisplayDate($date) {
    if (empty($date) || $date === '0000-00-00') {
        return '-';
    }
    return date(DISPLAY_DATE_FORMAT, strtotime($date));
}

/**
 * Format datetime for display
 * @param string $datetime
 * @return string
 */
function formatDisplayDateTime($datetime) {
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
        return '-';
    }
    return date(DISPLAY_DATETIME_FORMAT, strtotime($datetime));
}

/**
 * Calculate age from date of birth
 * @param string $dob
 * @return int
 */
function calculateAge($dob) {
    if (empty($dob) || $dob === '0000-00-00') {
        return 0;
    }
    return date_diff(date_create($dob), date_create('today'))->y;
}

/**
 * Get age group from age
 * @param int $age
 * @return string
 */
function getAgeGroup($age) {
    if ($age <= CHILD_MAX_AGE) return 'child';
    if ($age <= TEEN_MAX_AGE) return 'teen';
    if ($age <= YOUTH_MAX_AGE) return 'youth';
    if ($age >= SENIOR_MIN_AGE) return 'senior';
    return 'adult';
}

/**
 * Generate unique transaction ID
 * @param string $prefix
 * @return string
 */
function generateTransactionId($prefix = 'TXN') {
    return $prefix . date('Ymd') . rand(1000, 9999);
}

/**
 * Sanitize input data
 * @param mixed $data
 * @return mixed
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Check if user has permission
 * @param string $permission
 * @return bool
 */
function hasPermission($permission) {
    if (!isset($_SESSION['user_role'])) {
        return false;
    }
    
    // Administrator has all permissions
    if ($_SESSION['user_role'] === 'administrator') {
        return true;
    }
    
    // Define role permissions (this will be expanded later)
    $rolePermissions = [
        'pastor' => ['members', 'attendance', 'finance', 'equipment', 'sms', 'visitors', 'events', 'reports'],
        'finance_officer' => ['finance', 'reports'],
        'secretary' => ['members', 'attendance', 'visitors', 'events'],
        'department_head' => ['attendance', 'members', 'events'],
        'editor' => ['events'],
        'member' => [],
        'guest' => ['visitors']
    ];
    
    $userRole = $_SESSION['user_role'];
    return isset($rolePermissions[$userRole]) && in_array($permission, $rolePermissions[$userRole]);
}

/**
 * Redirect to login if not authenticated
 */
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . 'auth/login.php');
        exit();
    }
}

/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Log user activity
 * @param string $action
 * @param string $tableName
 * @param int $recordId
 * @param array $oldValues
 * @param array $newValues
 */
function logActivity($action, $tableName = null, $recordId = null, $oldValues = null, $newValues = null) {
    if (!isLoggedIn()) return;
    
    try {
        $db = Database::getInstance();
        $stmt = $db->executeQuery("
            INSERT INTO activity_logs (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $_SESSION['user_id'],
            $action,
            $tableName,
            $recordId,
            $oldValues ? json_encode($oldValues) : null,
            $newValues ? json_encode($newValues) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

// =====================================================
// AUTOLOAD CONFIGURATION
// =====================================================

// Simple autoloader for classes
spl_autoload_register(function ($class) {
    $classFile = INCLUDES_PATH . $class . '.php';
    if (file_exists($classFile)) {
        require_once $classFile;
    }
});

// Include database connection
require_once CONFIG_PATH . 'database.php';

// Include constants
require_once CONFIG_PATH . 'constants.php';

// =====================================================
// INITIALIZE SYSTEM
// =====================================================

// Create upload directories if they don't exist
$uploadDirs = [
    ASSETS_PATH . 'uploads/',
    ASSETS_PATH . 'uploads/members/',
    ASSETS_PATH . 'uploads/receipts/',
    ASSETS_PATH . 'uploads/documents/',
    ASSETS_PATH . 'uploads/equipment/',
    ROOT_PATH . 'logs/',
    BACKUP_PATH
];

foreach ($uploadDirs as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
        // Create .htaccess to prevent direct access to upload folders
        if (strpos($dir, 'uploads') !== false) {
            file_put_contents($dir . '.htaccess', "Options -Indexes\nDeny from all");
        }
    }
}

?>