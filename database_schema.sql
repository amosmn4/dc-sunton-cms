-- =====================================================
-- CHURCH MANAGEMENT SYSTEM - COMPLETE DATABASE SCHEMA
-- =====================================================

CREATE DATABASE IF NOT EXISTS church_cms;
USE church_cms;

-- Drop existing tables (for clean setup)
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS sms_history;
DROP TABLE IF EXISTS event_attendance;
DROP TABLE IF EXISTS visitor_followups;
DROP TABLE IF EXISTS visitors;
DROP TABLE IF EXISTS equipment_maintenance;
DROP TABLE IF EXISTS equipment;
DROP TABLE IF EXISTS expenses;
DROP TABLE IF EXISTS income;
DROP TABLE IF EXISTS attendance_records;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS member_departments;
DROP TABLE IF EXISTS departments;
DROP TABLE IF EXISTS members;
DROP TABLE IF EXISTS church_info;
DROP TABLE IF EXISTS users;

-- =====================================================
-- CORE TABLES
-- =====================================================

-- Users table for authentication and roles
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('administrator', 'pastor', 'finance_officer', 'secretary', 'department_head', 'editor', 'member', 'guest') DEFAULT 'member',
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    phone VARCHAR(20),
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    login_attempts INT DEFAULT 0,
    locked_until TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Church information table
CREATE TABLE church_info (
    id INT PRIMARY KEY AUTO_INCREMENT,
    church_name VARCHAR(100) NOT NULL DEFAULT 'Deliverance Church',
    `address` TEXT,
    phone VARCHAR(20),
    email VARCHAR(100),
    website VARCHAR(100),
    logo VARCHAR(255),
    mission_statement TEXT,
    vision_statement TEXT,
    `values` TEXT,
    yearly_theme VARCHAR(255),
    pastor_name VARCHAR(100),
    founded_date DATE,
    service_times TEXT,
    currency VARCHAR(10) DEFAULT 'KES',
    sms_balance DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =====================================================
-- MEMBER MANAGEMENT TABLES
-- =====================================================

-- Members table
CREATE TABLE members (
    id INT PRIMARY KEY AUTO_INCREMENT,
    member_number VARCHAR(20) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50),
    photo VARCHAR(255),
    date_of_birth DATE,
    gender ENUM('male', 'female') NOT NULL,
    marital_status ENUM('single', 'married', 'divorced', 'widowed') DEFAULT 'single',
    phone VARCHAR(20),
    email VARCHAR(100),
    `address`TEXT,
    emergency_contact_name VARCHAR(100),
    emergency_contact_phone VARCHAR(20),
    emergency_contact_relationship VARCHAR(50),
    join_date DATE NOT NULL,
    baptism_date DATE,
    confirmation_date DATE,
    membership_status ENUM('active', 'inactive', 'transferred', 'deceased') DEFAULT 'active',
    occupation VARCHAR(100),
    education_level VARCHAR(50),
    skills TEXT,
    spiritual_gifts TEXT,
    leadership_roles TEXT,
    family_id INT,
    spouse_member_id INT,
    father_member_id INT,
    mother_member_id INT,
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (spouse_member_id) REFERENCES members(id),
    FOREIGN KEY (father_member_id) REFERENCES members(id),
    FOREIGN KEY (mother_member_id) REFERENCES members(id)
);

-- Departments and Groups
CREATE TABLE departments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    department_type ENUM('age_group', 'ministry', 'fellowship', 'leadership', 'committee') NOT NULL,
    head_member_id INT,
    assistant_head_id INT,
    parent_department_id INT,
    meeting_day VARCHAR(20),
    meeting_time TIME,
    meeting_location VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    target_size INT,
    budget_allocation DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (head_member_id) REFERENCES members(id),
    FOREIGN KEY (assistant_head_id) REFERENCES members(id),
    FOREIGN KEY (parent_department_id) REFERENCES departments(id)
);

-- Member department assignments
CREATE TABLE member_departments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    member_id INT NOT NULL,
    department_id INT NOT NULL,
    role VARCHAR(50) DEFAULT 'member',
    assigned_date DATE NOT NULL,
    end_date DATE NULL,
    is_active BOOLEAN DEFAULT TRUE,
    responsibilities TEXT,
    UNIQUE KEY unique_active_assignment (member_id, department_id, is_active),
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
);

-- =====================================================
-- ATTENDANCE MANAGEMENT TABLES
-- =====================================================

-- Events table (for attendance tracking)
CREATE TABLE events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    event_type ENUM('sunday_service', 'prayer_meeting', 'bible_study', 'youth_service', 'special_event', 'conference', 'revival', 'outreach', 'fundraiser', 'wedding', 'funeral', 'baptism', 'other') NOT NULL,
    description TEXT,
    event_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME,
    location VARCHAR(100),
    expected_attendance INT,
    department_id INT,
    created_by INT,
    is_recurring BOOLEAN DEFAULT FALSE,
    recurrence_pattern VARCHAR(50),
    status ENUM('planned', 'ongoing', 'completed', 'cancelled') DEFAULT 'planned',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Attendance records
CREATE TABLE attendance_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_id INT NOT NULL,
    member_id INT NULL,
    attendance_type ENUM('member_checkin', 'bulk_count') NOT NULL DEFAULT 'member_checkin',
    is_present BOOLEAN DEFAULT TRUE,
    check_in_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    check_in_method ENUM('manual', 'qr_code', 'mobile_app') DEFAULT 'manual',
    notes TEXT,
    recorded_by INT,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id),
    UNIQUE KEY unique_member_event (member_id, event_id)
);

-- Bulk attendance counts (when not tracking individual members)
CREATE TABLE attendance_counts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_id INT NOT NULL,
    attendance_category ENUM('men', 'women', 'youth', 'children', 'visitors', 'total') NOT NULL,
    count_number INT NOT NULL DEFAULT 0,
    recorded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id)
);

-- =====================================================
-- FINANCIAL MANAGEMENT TABLES
-- =====================================================

-- Income categories
CREATE TABLE income_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Income records
CREATE TABLE income (
    id INT PRIMARY KEY AUTO_INCREMENT,
    transaction_id VARCHAR(50) UNIQUE NOT NULL,
    category_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'KES',
    source VARCHAR(100),
    donor_name VARCHAR(100),
    donor_phone VARCHAR(20),
    donor_email VARCHAR(100),
    payment_method ENUM('cash', 'mpesa', 'bank_transfer', 'cheque', 'card', 'other') NOT NULL,
    reference_number VARCHAR(100),
    description TEXT,
    transaction_date DATE NOT NULL,
    event_id INT,
    receipt_number VARCHAR(50),
    receipt_path VARCHAR(255),
    is_anonymous BOOLEAN DEFAULT FALSE,
    is_pledge BOOLEAN DEFAULT FALSE,
    pledge_period VARCHAR(50),
    recorded_by INT,
    verified_by INT,
    verification_date TIMESTAMP NULL,
    status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES income_categories(id),
    FOREIGN KEY (event_id) REFERENCES events(id),
    FOREIGN KEY (recorded_by) REFERENCES users(id),
    FOREIGN KEY (verified_by) REFERENCES users(id)
);

-- Expense categories
CREATE TABLE expense_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    budget_limit DECIMAL(12,2),
    is_active BOOLEAN DEFAULT TRUE,
    requires_approval BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Expense records
CREATE TABLE expenses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    transaction_id VARCHAR(50) UNIQUE NOT NULL,
    category_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'KES',
    vendor_name VARCHAR(100),
    vendor_contact VARCHAR(100),
    payment_method ENUM('cash', 'bank_transfer', 'cheque', 'card', 'mpesa', 'other') NOT NULL,
    reference_number VARCHAR(100),
    description TEXT NOT NULL,
    expense_date DATE NOT NULL,
    event_id INT,
    receipt_number VARCHAR(50),
    receipt_path VARCHAR(255),
    requested_by INT,
    approved_by INT,
    approval_date TIMESTAMP NULL,
    paid_by INT,
    payment_date TIMESTAMP NULL,
    status ENUM('pending', 'approved', 'paid', 'rejected') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES expense_categories(id),
    FOREIGN KEY (event_id) REFERENCES events(id),
    FOREIGN KEY (requested_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    FOREIGN KEY (paid_by) REFERENCES users(id)
);

-- =====================================================
-- EQUIPMENT MANAGEMENT TABLES
-- =====================================================

-- Equipment categories
CREATE TABLE equipment_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Equipment inventory
CREATE TABLE equipment (
    id INT PRIMARY KEY AUTO_INCREMENT,
    equipment_code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    category_id INT NOT NULL,
    description TEXT,
    brand VARCHAR(50),
    model VARCHAR(50),
    serial_number VARCHAR(100),
    purchase_date DATE,
    purchase_price DECIMAL(10,2),
    warranty_expiry DATE,
    supplier_name VARCHAR(100),
    supplier_contact VARCHAR(100),
    location VARCHAR(100),
    status ENUM('good', 'needs_attention', 'damaged', 'operational', 'under_repair', 'retired') DEFAULT 'good',
    condition_notes TEXT,
    last_maintenance_date DATE,
    next_maintenance_date DATE,
    maintenance_interval_days INT DEFAULT 365,
    responsible_person_id INT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES equipment_categories(id),
    FOREIGN KEY (responsible_person_id) REFERENCES members(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Equipment maintenance records
CREATE TABLE equipment_maintenance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    equipment_id INT NOT NULL,
    maintenance_type ENUM('preventive', 'corrective', 'emergency', 'inspection') NOT NULL,
    description TEXT NOT NULL,
    maintenance_date DATE NOT NULL,
    performed_by VARCHAR(100),
    cost DECIMAL(10,2) DEFAULT 0.00,
    parts_replaced TEXT,
    next_maintenance_date DATE,
    status ENUM('completed', 'in_progress', 'scheduled') DEFAULT 'completed',
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- =====================================================
-- VISITOR MANAGEMENT TABLES
-- =====================================================

-- Visitors table
CREATE TABLE visitors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    visitor_number VARCHAR(20) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    age_group ENUM('child', 'youth', 'adult', 'senior') NOT NULL,
    gender ENUM('male', 'female') NOT NULL,
    visit_date DATE NOT NULL,
    service_attended VARCHAR(100),
    how_heard_about_us VARCHAR(100),
    purpose_of_visit TEXT,
    areas_of_interest TEXT,
    previous_church VARCHAR(100),
    status ENUM('new_visitor', 'follow_up', 'regular_attender', 'converted_member') DEFAULT 'new_visitor',
    assigned_followup_person_id INT,
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_followup_person_id) REFERENCES members(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Visitor follow-up records
CREATE TABLE visitor_followups (
    id INT PRIMARY KEY AUTO_INCREMENT,
    visitor_id INT NOT NULL,
    followup_type ENUM('phone_call', 'visit', 'sms', 'email', 'letter') NOT NULL,
    followup_date DATE NOT NULL,
    description TEXT NOT NULL,
    outcome TEXT,
    next_followup_date DATE,
    performed_by INT,
    status ENUM('completed', 'scheduled', 'missed') DEFAULT 'completed',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (visitor_id) REFERENCES visitors(id) ON DELETE CASCADE,
    FOREIGN KEY (performed_by) REFERENCES users(id)
);

-- =====================================================
-- SMS AND COMMUNICATION TABLES
-- =====================================================

-- SMS templates
CREATE TABLE sms_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    category ENUM('service_reminder', 'event_announcement', 'birthday_wish', 'prayer_request', 'emergency', 'follow_up', 'general') NOT NULL,
    message TEXT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- SMS history
CREATE TABLE sms_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    batch_id VARCHAR(50) NOT NULL,
    recipient_type ENUM('all_members', 'department', 'age_group', 'individual', 'custom_list') NOT NULL,
    recipient_filter TEXT,
    message TEXT NOT NULL,
    total_recipients INT NOT NULL,
    sent_count INT DEFAULT 0,
    failed_count INT DEFAULT 0,
    cost DECIMAL(8,2) DEFAULT 0.00,
    status ENUM('pending', 'sending', 'completed', 'failed') DEFAULT 'pending',
    sent_by INT,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (sent_by) REFERENCES users(id)
);

-- Individual SMS records
CREATE TABLE sms_individual (
    id INT PRIMARY KEY AUTO_INCREMENT,
    batch_id VARCHAR(50) NOT NULL,
    recipient_phone VARCHAR(20) NOT NULL,
    recipient_name VARCHAR(100),
    member_id INT NULL,
    message TEXT NOT NULL,
    status ENUM('pending', 'sent', 'delivered', 'failed') DEFAULT 'pending',
    provider_message_id VARCHAR(100),
    error_message TEXT,
    sent_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    cost DECIMAL(5,2) DEFAULT 0.00,
    FOREIGN KEY (member_id) REFERENCES members(id)
);

-- =====================================================
-- REPORTS AND ANALYTICS TABLES
-- =====================================================

-- Custom reports
CREATE TABLE custom_reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    report_type ENUM('members', 'attendance', 'finance', 'visitors', 'equipment', 'custom') NOT NULL,
    sql_query TEXT,
    parameters JSON,
    created_by INT,
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- =====================================================
-- SYSTEM TABLES
-- =====================================================

-- Activity logs for audit trail
CREATE TABLE activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- System settings
CREATE TABLE system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    data_type ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =====================================================
-- INSERT DEFAULT DATA
-- =====================================================

-- Insert default admin user (password: admin123)
INSERT INTO users (username, email, password, role, first_name, last_name, phone) VALUES
('admin', 'admin@deliverancechurch.org', '0192023a7bbd73250516f069df18b500', 'administrator', 'System', 'Administrator', '+254700000000');

-- Insert church information
INSERT INTO church_info (church_name, address, phone, email, mission_statement, vision_statement, yearly_theme, pastor_name) VALUES
('Deliverance Church', 'Nairobi, Kenya', '+254700000001', 'info@deliverancechurch.org', 
'To make disciples of all nations through the power of God', 
'A church that transforms communities through Christ', 
'Year of Divine Breakthrough 2025', 'Pastor John Doe');

-- Insert default departments
INSERT INTO departments (name, description, department_type) VALUES
('Sunday School', 'Children and adult Bible study classes', 'ministry'),
('Youth Ministry', 'Young people aged 18-35', 'age_group'),
('Men\'s Fellowship', 'Brotherhood and men\'s ministry', 'fellowship'),
('Women\'s Fellowship', 'Sisterhood and women\'s ministry', 'fellowship'),
('Choir', 'Praise and worship ministry', 'ministry'),
('Ushers', 'Church service coordination', 'ministry'),
('Prayer Team', 'Intercessory prayer ministry', 'ministry'),
('Welfare Committee', 'Community outreach and support', 'ministry'),
('Children Ministry', 'Kids aged 0-12 years', 'age_group'),
('Teens Ministry', 'Teenagers aged 13-17', 'age_group');

-- Insert income categories
INSERT INTO income_categories (name, description) VALUES
('Tithes', 'Ten percent offering from members'),
('Offerings', 'Freewill offerings during services'),
('Seeds', 'Special seed offerings'),
('Pledges', 'Committed donations for specific purposes'),
('Welfare Tithe', 'Special tithe for community welfare'),
('Project Donations', 'Donations for specific church projects'),
('Special Collections', 'Special purpose collections'),
('Children Offerings', 'Offerings from children services'),
('Fundraising Events', 'Income from fundraising activities');

-- Insert expense categories
INSERT INTO expense_categories (name, description, requires_approval) VALUES
('Utilities', 'Electricity, water, internet bills', FALSE),
('Salaries', 'Staff salaries and allowances', TRUE),
('Maintenance', 'Building and equipment maintenance', FALSE),
('Equipment', 'Purchase of new equipment', TRUE),
('Events', 'Event organization costs', FALSE),
('Outreach', 'Community outreach expenses', FALSE),
('Office Supplies', 'Stationery and office materials', FALSE),
('Transport', 'Travel and transport costs', FALSE),
('Communication', 'Phone, SMS, internet costs', FALSE),
('Welfare', 'Community welfare support', TRUE);

-- Insert equipment categories
INSERT INTO equipment_categories (name, description) VALUES
('Audio Equipment', 'Microphones, speakers, mixers'),
('Visual Equipment', 'Projectors, screens, cameras'),
('Musical Instruments', 'Keyboards, guitars, drums'),
('Furniture', 'Chairs, tables, podiums'),
('Office Equipment', 'Computers, printers, phones'),
('Maintenance Tools', 'Tools for building maintenance'),
('Kitchen Equipment', 'Cooking and serving equipment'),
('Cleaning Equipment', 'Cleaning supplies and tools');

-- Insert SMS templates
INSERT INTO sms_templates (name, category, message) VALUES
('Service Reminder', 'service_reminder', 'Dear {first_name}, reminder about Sunday service at 9:00 AM. See you there! - Deliverance Church'),
('Birthday Wish', 'birthday_wish', 'Happy Birthday {first_name}! May God bless you with another year of His grace and favor. - Deliverance Church'),
('Event Announcement', 'event_announcement', 'Dear {first_name}, join us for {event_name} on {event_date} at {event_time}. - Deliverance Church'),
('Welcome New Member', 'general', 'Welcome to Deliverance Church family {first_name}! We are excited to have you join us on this journey of faith.'),
('Prayer Request Follow-up', 'prayer_request', 'Dear {first_name}, we continue to pray for your request. God hears and answers prayers. Stay blessed!');

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value, description, data_type) VALUES
('site_name', 'Deliverance Church CMS', 'Website/System name', 'string'),
('default_currency', 'KES', 'Default currency for financial transactions', 'string'),
('sms_sender_id', 'CHURCH', 'SMS sender ID', 'string'),
('backup_frequency', '7', 'Database backup frequency in days', 'integer'),
('session_timeout', '3600', 'User session timeout in seconds', 'integer'),
('max_login_attempts', '3', 'Maximum login attempts before account lock', 'integer'),
('account_lockout_duration', '1800', 'Account lockout duration in seconds', 'integer');

-- =====================================================
-- CREATE INDEXES FOR PERFORMANCE
-- =====================================================

-- Members indexes
CREATE INDEX idx_member_number ON members(member_number);
CREATE INDEX idx_member_status ON members(membership_status);
CREATE INDEX idx_member_join_date ON members(join_date);
CREATE INDEX idx_member_name ON members(first_name, last_name);
CREATE INDEX idx_member_phone ON members(phone);

-- Attendance indexes
CREATE INDEX idx_attendance_event ON attendance_records(event_id);
CREATE INDEX idx_attendance_member ON attendance_records(member_id);
CREATE INDEX idx_attendance_date ON attendance_records(check_in_time);

-- Financial indexes
CREATE INDEX idx_income_date ON income(transaction_date);
CREATE INDEX idx_income_category ON income(category_id);
CREATE INDEX idx_expense_date ON expenses(expense_date);
CREATE INDEX idx_expense_category ON expenses(category_id);

-- SMS indexes
CREATE INDEX idx_sms_batch ON sms_individual(batch_id);
CREATE INDEX idx_sms_status ON sms_individual(status);

-- Activity log indexes
CREATE INDEX idx_activity_user ON activity_logs(user_id);
CREATE INDEX idx_activity_date ON activity_logs(created_at);

-- =====================================================
-- CREATE VIEWS FOR COMMON QUERIES
-- =====================================================

-- Active members view
CREATE VIEW active_members AS
SELECT m.*, d.name as department_name, d.department_type
FROM members m
LEFT JOIN member_departments md ON m.id = md.member_id AND md.is_active = TRUE
LEFT JOIN departments d ON md.department_id = d.id
WHERE m.membership_status = 'active';

-- Financial summary view
CREATE VIEW financial_summary AS
SELECT 
    'income' as transaction_type,
    ic.name as category_name,
    SUM(i.amount) as total_amount,
    COUNT(*) as transaction_count,
    YEAR(i.transaction_date) as year,
    MONTH(i.transaction_date) as month
FROM income i
JOIN income_categories ic ON i.category_id = ic.id
WHERE i.status = 'verified'
GROUP BY ic.id, YEAR(i.transaction_date), MONTH(i.transaction_date)

UNION ALL

SELECT 
    'expense' as transaction_type,
    ec.name as category_name,
    SUM(e.amount) as total_amount,
    COUNT(*) as transaction_count,
    YEAR(e.expense_date) as year,
    MONTH(e.expense_date) as month
FROM expenses e
JOIN expense_categories ec ON e.category_id = ec.id
WHERE e.status = 'paid'
GROUP BY ec.id, YEAR(e.expense_date), MONTH(e.expense_date);

-- =====================================================
-- DATABASE SCHEMA COMPLETE
-- =====================================================