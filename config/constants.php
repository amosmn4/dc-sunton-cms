<?php
/**
 * System Constants
 * Deliverance Church Management System
 * 
 * Contains all system constants and enums
 */

// =====================================================
// USER ROLES AND PERMISSIONS
// =====================================================

define('USER_ROLES', [
    'administrator' => 'System Administrator',
    'pastor' => 'Pastor/Priest',
    'finance_officer' => 'Finance Officer',
    'secretary' => 'Church Secretary',
    'department_head' => 'Department Head',
    'editor' => 'Content Editor',
    'member' => 'Church Member',
    'guest' => 'Guest User'
]);

// Permission levels
define('PERMISSION_LEVELS', [
    'read' => 'View Only',
    'write' => 'Add/Edit',
    'delete' => 'Delete',
    'admin' => 'Full Admin'
]);

// =====================================================
// MEMBER RELATED CONSTANTS
// =====================================================

define('GENDER_OPTIONS', [
    'male' => 'Male',
    'female' => 'Female'
]);

define('MARITAL_STATUS_OPTIONS', [
    'single' => 'Single',
    'married' => 'Married',
    'divorced' => 'Divorced',
    'widowed' => 'Widowed'
]);

define('MEMBER_STATUS_OPTIONS', [
    'active' => 'Active Member',
    'inactive' => 'Inactive Member',
    'transferred' => 'Transferred Out',
    'deceased' => 'Deceased'
]);

define('EDUCATION_LEVELS', [
    'primary' => 'Primary Education',
    'secondary' => 'Secondary Education',
    'certificate' => 'Certificate',
    'diploma' => 'Diploma',
    'degree' => 'Bachelor\'s Degree',
    'masters' => 'Master\'s Degree',
    'phd' => 'PhD/Doctorate',
    'other' => 'Other'
]);

// =====================================================
// DEPARTMENT TYPES
// =====================================================

define('DEPARTMENT_TYPES', [
    'age_group' => 'Age Group',
    'ministry' => 'Ministry',
    'fellowship' => 'Fellowship',
    'leadership' => 'Leadership',
    'committee' => 'Committee'
]);

define('DEFAULT_DEPARTMENTS', [
    'age_groups' => [
        'Children Ministry' => 'Kids aged 0-12 years',
        'Teens Ministry' => 'Teenagers aged 13-17',
        'Youth Ministry' => 'Young people aged 18-35',
        'Adults Ministry' => 'Adults aged 36-59',
        'Seniors Ministry' => 'Seniors aged 60 and above'
    ],
    'ministries' => [
        'Choir' => 'Praise and worship ministry',
        'Ushers' => 'Church service coordination',
        'Sunday School' => 'Bible study classes',
        'Prayer Team' => 'Intercessory prayer ministry',
        'Welfare Committee' => 'Community outreach and support',
        'Media Team' => 'Audio/visual and social media',
        'Security Team' => 'Church security and safety'
    ],
    'fellowships' => [
        'Men\'s Fellowship' => 'Brotherhood and men\'s ministry',
        'Women\'s Fellowship' => 'Sisterhood and women\'s ministry'
    ]
]);

// =====================================================
// EVENT AND ATTENDANCE CONSTANTS
// =====================================================

define('EVENT_TYPES', [
    'sunday_service' => 'Sunday Service',
    'prayer_meeting' => 'Prayer Meeting',
    'bible_study' => 'Bible Study',
    'youth_service' => 'Youth Service',
    'special_event' => 'Special Event',
    'conference' => 'Conference',
    'revival' => 'Revival Meeting',
    'outreach' => 'Outreach Program',
    'fundraiser' => 'Fundraising Event',
    'wedding' => 'Wedding Ceremony',
    'funeral' => 'Funeral Service',
    'baptism' => 'Baptism Service',
    'other' => 'Other Event'
]);

define('EVENT_STATUS', [
    'planned' => 'Planned',
    'ongoing' => 'Ongoing',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled'
]);

define('ATTENDANCE_METHODS', [
    'manual' => 'Manual Entry',
    'qr_code' => 'QR Code Check-in',
    'mobile_app' => 'Mobile App'
]);

define('ATTENDANCE_CATEGORIES', [
    'men' => 'Men',
    'women' => 'Women',
    'youth' => 'Youth',
    'children' => 'Children',
    'visitors' => 'Visitors',
    'total' => 'Total Attendance'
]);

// =====================================================
// FINANCIAL CONSTANTS
// =====================================================

define('PAYMENT_METHODS', [
    'cash' => 'Cash',
    'mpesa' => 'M-Pesa',
    'bank_transfer' => 'Bank Transfer',
    'cheque' => 'Cheque',
    'card' => 'Debit/Credit Card',
    'other' => 'Other'
]);

define('INCOME_CATEGORIES_DEFAULT', [
    'tithes' => 'Tithes',
    'offerings' => 'Offerings',
    'seeds' => 'Seed Offerings',
    'pledges' => 'Pledges',
    'welfare_tithe' => 'Welfare Tithe',
    'project_donations' => 'Project Donations',
    'special_collections' => 'Special Collections',
    'children_offerings' => 'Children\'s Offerings',
    'fundraising' => 'Fundraising Events'
]);

define('EXPENSE_CATEGORIES_DEFAULT', [
    'utilities' => 'Utilities',
    'salaries' => 'Salaries & Allowances',
    'maintenance' => 'Maintenance',
    'equipment' => 'Equipment Purchase',
    'events' => 'Event Expenses',
    'outreach' => 'Outreach Programs',
    'office_supplies' => 'Office Supplies',
    'transport' => 'Transport & Travel',
    'communication' => 'Communication',
    'welfare' => 'Welfare Support'
]);

define('TRANSACTION_STATUS', [
    'pending' => 'Pending',
    'verified' => 'Verified',
    'approved' => 'Approved',
    'paid' => 'Paid',
    'rejected' => 'Rejected'
]);

// =====================================================
// EQUIPMENT CONSTANTS
// =====================================================

define('EQUIPMENT_STATUS', [
    'good' => 'Good Condition',
    'needs_attention' => 'Needs Attention',
    'damaged' => 'Damaged',
    'operational' => 'Operational',
    'under_repair' => 'Under Repair',
    'retired' => 'Retired/Disposed'
]);

define('EQUIPMENT_CATEGORIES_DEFAULT', [
    'audio_equipment' => 'Audio Equipment',
    'visual_equipment' => 'Visual Equipment',
    'musical_instruments' => 'Musical Instruments',
    'furniture' => 'Furniture & Fixtures',
    'office_equipment' => 'Office Equipment',
    'maintenance_tools' => 'Maintenance Tools',
    'kitchen_equipment' => 'Kitchen Equipment',
    'cleaning_equipment' => 'Cleaning Equipment'
]);

define('MAINTENANCE_TYPES', [
    'preventive' => 'Preventive Maintenance',
    'corrective' => 'Corrective Maintenance',
    'emergency' => 'Emergency Repair',
    'inspection' => 'Inspection'
]);

define('MAINTENANCE_STATUS', [
    'completed' => 'Completed',
    'in_progress' => 'In Progress',
    'scheduled' => 'Scheduled'
]);

// =====================================================
// SMS AND COMMUNICATION CONSTANTS
// =====================================================

define('SMS_TEMPLATE_CATEGORIES', [
    'service_reminder' => 'Service Reminder',
    'event_announcement' => 'Event Announcement',
    'birthday_wish' => 'Birthday Wish',
    'prayer_request' => 'Prayer Request',
    'emergency' => 'Emergency Notification',
    'follow_up' => 'Follow-up Message',
    'general' => 'General Message'
]);

define('SMS_STATUS', [
    'pending' => 'Pending',
    'sending' => 'Sending',
    'sent' => 'Sent',
    'delivered' => 'Delivered',
    'failed' => 'Failed',
    'completed' => 'Completed'
]);

define('SMS_RECIPIENT_TYPES', [
    'all_members' => 'All Members',
    'department' => 'Department Members',
    'age_group' => 'Age Group',
    'individual' => 'Individual Member',
    'custom_list' => 'Custom List'
]);

// =====================================================
// VISITOR MANAGEMENT CONSTANTS
// =====================================================

define('VISITOR_AGE_GROUPS', [
    'child' => 'Child (0-12)',
    'youth' => 'Youth (13-35)',
    'adult' => 'Adult (36-59)',
    'senior' => 'Senior (60+)'
]);

define('VISITOR_STATUS', [
    'new_visitor' => 'New Visitor',
    'follow_up' => 'In Follow-up',
    'regular_attender' => 'Regular Attender',
    'converted_member' => 'Converted to Member'
]);

define('FOLLOWUP_TYPES', [
    'phone_call' => 'Phone Call',
    'visit' => 'Home/Office Visit',
    'sms' => 'SMS Message',
    'email' => 'Email',
    'letter' => 'Letter/Card'
]);

define('FOLLOWUP_STATUS', [
    'completed' => 'Completed',
    'scheduled' => 'Scheduled',
    'missed' => 'Missed'
]);

// =====================================================
// REPORT TYPES
// =====================================================

define('REPORT_TYPES', [
    'members' => 'Member Reports',
    'attendance' => 'Attendance Reports',
    'finance' => 'Financial Reports',
    'visitors' => 'Visitor Reports',
    'equipment' => 'Equipment Reports',
    'sms' => 'SMS Reports',
    'custom' => 'Custom Reports'
]);

define('EXPORT_FORMATS', [
    'pdf' => 'PDF Document',
    'excel' => 'Excel Spreadsheet',
    'csv' => 'CSV File',
    'html' => 'HTML Page'
]);

// =====================================================
// SYSTEM SETTINGS KEYS
// =====================================================

define('SYSTEM_SETTINGS_KEYS', [
    'site_name' => 'Website Name',
    'church_name' => 'Church Name',
    'church_address' => 'Church Address',
    'church_phone' => 'Church Phone',
    'church_email' => 'Church Email',
    'pastor_name' => 'Pastor Name',
    'default_currency' => 'Default Currency',
    'timezone' => 'Timezone',
    'date_format' => 'Date Format',
    'sms_sender_id' => 'SMS Sender ID',
    'backup_frequency' => 'Backup Frequency (Days)',
    'session_timeout' => 'Session Timeout (Minutes)',
    'max_login_attempts' => 'Maximum Login Attempts',
    'account_lockout_duration' => 'Account Lockout Duration (Minutes)'
]);

// =====================================================
// FILE TYPE CONSTANTS
// =====================================================

define('IMAGE_MIME_TYPES', [
    'image/jpeg',
    'image/jpg',
    'image/png',
    'image/gif'
]);

define('DOCUMENT_MIME_TYPES', [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
]);

// =====================================================
// ERROR CODES
// =====================================================

define('ERROR_CODES', [
    1001 => 'Invalid login credentials',
    1002 => 'Account locked due to multiple failed attempts',
    1003 => 'Account is inactive',
    1004 => 'Session expired',
    1005 => 'Insufficient permissions',
    2001 => 'Member not found',
    2002 => 'Duplicate member number',
    2003 => 'Invalid member data',
    3001 => 'Financial transaction failed',
    3002 => 'Insufficient funds',
    3003 => 'Invalid transaction amount',
    4001 => 'SMS sending failed',
    4002 => 'SMS balance insufficient',
    4003 => 'Invalid phone number',
    5001 => 'File upload failed',
    5002 => 'Invalid file type',
    5003 => 'File size exceeded limit',
    6001 => 'Database connection failed',
    6002 => 'Database query failed',
    6003 => 'Data validation failed',
    7001 => 'Email sending failed',
    7002 => 'Invalid email address',
    9999 => 'Unknown system error'
]);

// =====================================================
// SUCCESS CODES
// =====================================================

define('SUCCESS_CODES', [
    1001 => 'Login successful',
    1002 => 'Logout successful',
    1003 => 'Password updated successfully',
    2001 => 'Member added successfully',
    2002 => 'Member updated successfully',
    2003 => 'Member deleted successfully',
    3001 => 'Financial transaction recorded successfully',
    3002 => 'Transaction approved successfully',
    3003 => 'Payment processed successfully',
    4001 => 'SMS sent successfully',
    4002 => 'SMS batch queued successfully',
    5001 => 'File uploaded successfully',
    5002 => 'Data exported successfully',
    5003 => 'Backup created successfully',
    6001 => 'Data saved successfully',
    6002 => 'Data updated successfully',
    6003 => 'Data deleted successfully'
]);

// =====================================================
// VALIDATION RULES
// =====================================================

define('VALIDATION_RULES', [
    'required_fields' => [
        'member' => ['first_name', 'last_name', 'gender', 'join_date'],
        'visitor' => ['first_name', 'last_name', 'phone', 'visit_date'],
        'income' => ['category_id', 'amount', 'payment_method', 'transaction_date'],
        'expense' => ['category_id', 'amount', 'description', 'expense_date'],
        'event' => ['name', 'event_type', 'event_date', 'start_time'],
        'equipment' => ['name', 'category_id', 'status']
    ],
    'email_validation' => '/^[^\s@]+@[^\s@]+\.[^\s@]+$/',
    'phone_validation' => '/^(\+254|0)[7-9]\d{8}$/', // Kenyan phone format
    'member_number_format' => '/^MEM\d{6}$/', // Format: MEM000001
    'visitor_number_format' => '/^VIS\d{6}$/', // Format: VIS000001
    'equipment_code_format' => '/^EQP\d{6}$/', // Format: EQP000001
    'transaction_id_format' => '/^TXN\d{8}\d{4}$/' // Format: TXN20250101XXXX
]);

// =====================================================
// DEFAULT TEMPLATES
// =====================================================

define('DEFAULT_SMS_TEMPLATES', [
    'welcome_new_member' => [
        'name' => 'Welcome New Member',
        'category' => 'general',
        'message' => 'Welcome to {church_name} family {first_name}! We are excited to have you join us on this journey of faith. God bless you!'
    ],
    'service_reminder' => [
        'name' => 'Sunday Service Reminder',
        'category' => 'service_reminder',
        'message' => 'Dear {first_name}, reminder about Sunday service tomorrow at {service_time}. Theme: {service_theme}. See you there! - {church_name}'
    ],
    'birthday_wish' => [
        'name' => 'Birthday Wishes',
        'category' => 'birthday_wish',
        'message' => 'Happy Birthday {first_name}! May God bless you with another year of His grace, favor and abundant blessings. Enjoy your special day! - {church_name}'
    ],
    'tithe_reminder' => [
        'name' => 'Tithe Reminder',
        'category' => 'general',
        'message' => 'Dear {first_name}, remember to honor God with your tithes and offerings. "Bring the whole tithe into the storehouse..." Malachi 3:10. - {church_name}'
    ],
    'prayer_meeting' => [
        'name' => 'Prayer Meeting Reminder',
        'category' => 'service_reminder',
        'message' => 'Join us for prayer meeting today at {time}. "Where two or three gather in my name, there am I with them." Matthew 18:20. - {church_name}'
    ],
    'event_announcement' => [
        'name' => 'Event Announcement',
        'category' => 'event_announcement',
        'message' => 'Dear {first_name}, join us for {event_name} on {event_date} at {event_time}. Venue: {venue}. Don\'t miss out! - {church_name}'
    ],
    'visitor_followup' => [
        'name' => 'Visitor Follow-up',
        'category' => 'follow_up',
        'message' => 'Thank you {first_name} for visiting {church_name}. We hope you felt God\'s presence. We would love to see you again next Sunday!'
    ],
    'equipment_maintenance' => [
        'name' => 'Equipment Maintenance Reminder',
        'category' => 'general',
        'message' => 'Reminder: {equipment_name} is due for maintenance on {due_date}. Please coordinate with the maintenance team.'
    ]
]);

// =====================================================
// MENU STRUCTURE
// =====================================================

define('MAIN_MENU', [
    'dashboard' => [
        'title' => 'Dashboard',
        'icon' => 'fas fa-tachometer-alt',
        'url' => 'modules/dashboard/',
        'permissions' => ['all']
    ],
    'members' => [
        'title' => 'Members',
        'icon' => 'fas fa-users',
        'url' => 'modules/members/',
        'permissions' => ['administrator', 'pastor', 'secretary'],
        'submenu' => [
            'all_members' => ['title' => 'All Members', 'url' => 'modules/members/'],
            'add_member' => ['title' => 'Add Member', 'url' => 'modules/members/add.php'],
            'departments' => ['title' => 'Departments', 'url' => 'modules/members/departments.php'],
            'member_reports' => ['title' => 'Member Reports', 'url' => 'modules/members/reports.php']
        ]
    ],
    'attendance' => [
        'title' => 'Attendance',
        'icon' => 'fas fa-calendar-check',
        'url' => 'modules/attendance/',
        'permissions' => ['administrator', 'pastor', 'secretary', 'department_head'],
        'submenu' => [
            'attendance_overview' => ['title' => 'Attendance Overview', 'url' => 'modules/attendance/'],
            'record_attendance' => ['title' => 'Record Attendance', 'url' => 'modules/attendance/record.php'],
            'bulk_attendance' => ['title' => 'Bulk Attendance Record', 'url' => 'modules/attendance/bulk_record.php'],
            'events' => ['title' => 'Events', 'url' => 'modules/attendance/events.php'],            
            'attendance_reports' => ['title' => 'Attendance Reports', 'url' => 'modules/attendance/reports.php']
            
        ]
    ],
    'finance' => [
        'title' => 'Finance',
        'icon' => 'fas fa-money-bill-wave',
        'url' => 'modules/finance/',
        'permissions' => ['administrator', 'pastor', 'finance_officer'],
        'submenu' => [
            'finance_overview' => ['title' => 'Finance Overview', 'url' => 'modules/finance/'],
            'income' => ['title' => 'Income', 'url' => 'modules/finance/income.php'],
            'expenses' => ['title' => 'Expenses', 'url' => 'modules/finance/expenses.php'],            
            'categories' => ['title' => 'Categories', 'url' => 'modules/finance/categories.php'],
            'reports' => ['title' => 'Financial Reports', 'url' => 'modules/finance/reports.php'],
        ]
    ],
    'equipment' => [
        'title' => 'Equipment',
        'icon' => 'fas fa-tools',
        'url' => 'modules/equipment/',
        'permissions' => ['administrator', 'pastor', 'secretary'],
        'submenu' => [
            'inventory' => ['title' => 'Inventory', 'url' => 'modules/equipment/'],
            'maintenance' => ['title' => 'Maintenance', 'url' => 'modules/equipment/maintenance.php'],
            'add_equipment' => ['title' => 'Add Equipment', 'url' => 'modules/equipment/add.php'],
            'reports' => ['title' => 'Equipment Reports', 'url' => 'modules/equipment/reports.php']
        ]
    ],
    'sms' => [
        'title' => 'SMS',
        'icon' => 'fas fa-sms',
        'url' => 'modules/sms/',
        'permissions' => ['administrator', 'pastor', 'secretary'],
        'submenu' => [
            'sms_dashboard' => ['title' => 'SMS Dashboard', 'url' => 'modules/sms/'],
            'send_sms' => ['title' => 'Send SMS', 'url' => 'modules/sms/send.php'],
            'templates' => ['title' => 'Templates', 'url' => 'modules/sms/templates.php'],
            'history' => ['title' => 'SMS History', 'url' => 'modules/sms/history.php']
        ]
    ],
    'visitors' => [
        'title' => 'Visitors',
        'icon' => 'fas fa-user-friends',
        'url' => 'modules/visitors/',
        'permissions' => ['administrator', 'pastor', 'secretary', 'guest'],
        'submenu' => [
            'all_visitors' => ['title' => 'All Visitors', 'url' => 'modules/visitors/'],
            'add_visitor' => ['title' => 'Add Visitor', 'url' => 'modules/visitors/add.php'],
            'followup' => ['title' => 'Follow-up', 'url' => 'modules/visitors/followup.php'],
            'reports' => ['title' => 'Visitor Reports', 'url' => 'modules/visitors/reports.php']
        ]
    ],
    'events' => [
        'title' => 'Events',
        'icon' => 'fas fa-calendar-alt',
        'url' => 'modules/events/',
        'permissions' => ['administrator', 'pastor', 'secretary', 'department_head', 'editor'],
        'submenu' => [
            'calendar' => ['title' => 'Event Calendar', 'url' => 'modules/events/'],
            'add_event' => ['title' => 'Add Event', 'url' => 'modules/events/add.php'],
            'programs' => ['title' => 'Programs', 'url' => 'modules/events/programs.php']
        ]
    ],
    'reports' => [
        'title' => 'Reports',
        'icon' => 'fas fa-chart-bar',
        'url' => 'modules/reports/',
        'permissions' => ['administrator', 'pastor', 'finance_officer'],
        'submenu' => [
            'dashboard' => ['title' => 'Report Dashboard', 'url' => 'modules/reports/'],
            'custom' => ['title' => 'Custom Reports', 'url' => 'modules/reports/custom.php'],
            'analytics' => ['title' => 'Analytics', 'url' => 'modules/reports/analytics.php']
        ]
    ],
    'admin' => [
        'title' => 'Administration',
        'icon' => 'fas fa-cogs',
        'url' => 'modules/admin/',
        'permissions' => ['administrator'],
        'submenu' => [
            'users' => ['title' => 'User Management', 'url' => 'modules/admin/users.php'],
            'settings' => ['title' => 'System Settings', 'url' => 'modules/admin/settings.php'],
            'backup' => ['title' => 'Backup & Restore', 'url' => 'modules/admin/backup.php'],
            'logs' => ['title' => 'Activity Logs', 'url' => 'modules/admin/logs.php']
        ]
    ]
]);

// =====================================================
// QUICK ACTIONS (Dashboard)
// =====================================================

define('QUICK_ACTIONS', [
    'send_sms' => [
        'title' => 'Send SMS',
        'icon' => 'fas fa-sms',
        'url' => 'modules/sms/send.php',
        'color' => 'success',
        'permissions' => ['administrator', 'pastor', 'secretary']
    ],
    'add_member' => [
        'title' => 'Add Member',
        'icon' => 'fas fa-user-plus',
        'url' => 'modules/members/add.php',
        'color' => 'primary',
        'permissions' => ['administrator', 'pastor', 'secretary']
    ],
    'record_attendance' => [
        'title' => 'Record Attendance',
        'icon' => 'fas fa-calendar-check',
        'url' => 'modules/attendance/record.php',
        'color' => 'info',
        'permissions' => ['administrator', 'pastor', 'secretary', 'department_head']
    ],
    'log_income' => [
        'title' => 'Log Income',
        'icon' => 'fas fa-plus-circle',
        'url' => 'modules/finance/add_income.php',
        'color' => 'success',
        'permissions' => ['administrator', 'pastor', 'finance_officer']
    ],
    'add_visitor' => [
        'title' => 'Add Visitor',
        'icon' => 'fas fa-user-friends',
        'url' => 'modules/visitors/add.php',
        'color' => 'warning',
        'permissions' => ['administrator', 'pastor', 'secretary', 'guest']
    ],
    'view_reports' => [
        'title' => 'View Reports',
        'icon' => 'fas fa-chart-line',
        'url' => 'modules/reports/',
        'color' => 'secondary',
        'permissions' => ['administrator', 'pastor', 'finance_officer']
    ]
]);

// =====================================================
// DASHBOARD WIDGETS
// =====================================================

define('DASHBOARD_WIDGETS', [
    'stats_cards' => [
        'total_members' => ['title' => 'Total Members', 'icon' => 'fas fa-users', 'color' => 'primary'],
        'new_visitors' => ['title' => 'New Visitors', 'icon' => 'fas fa-user-friends', 'color' => 'success'],
        'attendance_today' => ['title' => 'Today\'s Attendance', 'icon' => 'fas fa-calendar-check', 'color' => 'info'],
        'monthly_income' => ['title' => 'Monthly Income', 'icon' => 'fas fa-money-bill-wave', 'color' => 'warning']
    ],
    'charts' => [
        'attendance_trend' => ['title' => 'Attendance Trends', 'type' => 'line'],
        'income_vs_expenses' => ['title' => 'Income vs Expenses', 'type' => 'bar'],
        'member_growth' => ['title' => 'Member Growth', 'type' => 'area'],
        'department_distribution' => ['title' => 'Department Distribution', 'type' => 'pie']
    ]
]);

// =====================================================
// NOTIFICATION TYPES
// =====================================================

define('NOTIFICATION_TYPES', [
    'birthday' => 'Birthday Reminder',
    'anniversary' => 'Anniversary Reminder',
    'maintenance' => 'Equipment Maintenance Due',
    'followup' => 'Visitor Follow-up Due',
    'low_balance' => 'Low SMS Balance',
    'system' => 'System Notification',
    'backup' => 'Backup Reminder'
]);

// =====================================================
// API ENDPOINTS
// =====================================================

define('API_ENDPOINTS', [
    'members' => [
        'get_all' => '/api/members.php?action=get_all',
        'get_by_id' => '/api/members.php?action=get_by_id',
        'create' => '/api/members.php?action=create',
        'update' => '/api/members.php?action=update',
        'delete' => '/api/members.php?action=delete'
    ],
    'attendance' => [
        'record' => '/api/attendance.php?action=record',
        'get_stats' => '/api/attendance.php?action=get_stats'
    ],
    'sms' => [
        'send' => '/api/sms.php?action=send',
        'get_balance' => '/api/sms.php?action=get_balance'
    ]
]);

// =====================================================
// UTILITY FUNCTIONS FOR CONSTANTS
// =====================================================

/**
 * Get options array for HTML select elements
 * @param string $constantName
 * @return array
 */
function getSelectOptions($constantName) {
    if (defined($constantName)) {
        return constant($constantName);
    }
    return [];
}

/**
 * Get user role display name
 * @param string $role
 * @return string
 */
function getUserRoleDisplay($role) {
    $roles = USER_ROLES;
    return isset($roles[$role]) ? $roles[$role] : ucfirst($role);
}

/**
 * Get event type display name
 * @param string $type
 * @return string
 */
function getEventTypeDisplay($type) {
    $types = EVENT_TYPES;
    return isset($types[$type]) ? $types[$type] : ucfirst(str_replace('_', ' ', $type));
}

/**
 * Get payment method display name
 * @param string $method
 * @return string
 */
function getPaymentMethodDisplay($method) {
    $methods = PAYMENT_METHODS;
    return isset($methods[$method]) ? $methods[$method] : ucfirst(str_replace('_', ' ', $method));
}

/**
 * Check if feature is enabled
 * @param string $feature
 * @return bool
 */
function isFeatureEnabled($feature) {
    $featureName = 'ENABLE_' . strtoupper($feature) . '_MODULE';
    return defined($featureName) && constant($featureName) === true;
}

/**
 * Generate member number
 * @return string
 */
function generateMemberNumber() {
    // Get next number from database or use timestamp-based approach
    $timestamp = time();
    $random = rand(100, 999);
    return 'MEM' . date('y') . sprintf('%04d', $random);
}

/**
 * Generate visitor number
 * @return string
 */
function generateVisitorNumber() {
    $timestamp = time();
    $random = rand(100, 999);
    return 'VIS' . date('y') . sprintf('%04d', $random);
}

/**
 * Generate equipment code
 * @return string
 */
function generateEquipmentCode() {
    $timestamp = time();
    $random = rand(100, 999);
    return 'EQP' . date('y') . sprintf('%04d', $random);
}

/**
 * Validate phone number format
 * @param string $phone
 * @return bool
 */
function isValidPhoneNumber($phone) {
    return preg_match(VALIDATION_RULES['phone_validation'], $phone);
}

/**
 * Validate email format
 * @param string $email
 * @return bool
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Format phone number to standard format
 * @param string $phone
 * @return string
 */
function formatPhoneNumber($phone) {
    // Remove all non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Convert to Kenyan format
    if (substr($phone, 0, 3) === '254') {
        return '+' . $phone;
    } elseif (substr($phone, 0, 1) === '0') {
        return '+254' . substr($phone, 1);
    } elseif (strlen($phone) === 9) {
        return '+254' . $phone;
    }
    
    return $phone;
}

?>