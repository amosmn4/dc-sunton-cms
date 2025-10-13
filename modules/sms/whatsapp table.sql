-- =====================================================
-- WhatsApp Communication Tables
-- Deliverance Church Management System
-- =====================================================

-- WhatsApp Groups Table
CREATE TABLE IF NOT EXISTS whatsapp_groups (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    group_id VARCHAR(100),
    description TEXT,
    member_count INT DEFAULT 0,
    admin_phone VARCHAR(20),
    invite_link TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- WhatsApp History Table
CREATE TABLE IF NOT EXISTS whatsapp_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    batch_id VARCHAR(50) UNIQUE NOT NULL,
    communication_type VARCHAR(20) DEFAULT 'whatsapp',
    recipient_type VARCHAR(50),
    recipient_filter TEXT,
    message TEXT NOT NULL,
    media_type VARCHAR(20),
    media_url TEXT,
    total_recipients INT NOT NULL,
    sent_count INT DEFAULT 0,
    failed_count INT DEFAULT 0,
    status ENUM('pending', 'sending', 'completed', 'failed', 'scheduled') DEFAULT 'pending',
    sent_by INT,
    sent_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    scheduled_at TIMESTAMP NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sent_by) REFERENCES users(id)
);

-- WhatsApp Individual Messages Table
CREATE TABLE IF NOT EXISTS whatsapp_individual (
    id INT PRIMARY KEY AUTO_INCREMENT,
    batch_id VARCHAR(50) NOT NULL,
    recipient_phone VARCHAR(20) NOT NULL,
    recipient_name VARCHAR(100),
    member_id INT NULL,
    message TEXT NOT NULL,
    media_type VARCHAR(20),
    media_url TEXT,
    status ENUM('pending', 'sent', 'delivered', 'read', 'failed') DEFAULT 'pending',
    provider_message_id VARCHAR(100),
    error_message TEXT,
    sent_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(id),
    INDEX idx_batch (batch_id),
    INDEX idx_status (status),
    INDEX idx_member (member_id)
);

-- WhatsApp Templates Table
CREATE TABLE IF NOT EXISTS whatsapp_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    template_id VARCHAR(100),
    category VARCHAR(50),
    language_code VARCHAR(10) DEFAULT 'en',
    header_text TEXT,
    body_text TEXT NOT NULL,
    footer_text TEXT,
    buttons JSON,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- WhatsApp Webhooks Log Table
CREATE TABLE IF NOT EXISTS whatsapp_webhooks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_type VARCHAR(50),
    message_id VARCHAR(100),
    status VARCHAR(50),
    recipient_phone VARCHAR(20),
    timestamp_received TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    payload JSON,
    processed BOOLEAN DEFAULT FALSE,
    processed_at TIMESTAMP NULL,
    error_message TEXT
);

-- Insert system settings for WhatsApp
INSERT INTO system_settings (setting_key, setting_value, description, data_type) VALUES
('whatsapp_enabled', 'true', 'Enable WhatsApp communication', 'boolean'),
('whatsapp_business_phone', '254745600377', 'WhatsApp Business phone number', 'string'),
('whatsapp_api_url', 'https://graph.facebook.com/v18.0', 'WhatsApp Business API URL', 'string'),
('whatsapp_access_token', '', 'WhatsApp Business API access token', 'string'),
('whatsapp_phone_number_id', '', 'WhatsApp Business phone number ID', 'string'),
('whatsapp_business_account_id', '', 'WhatsApp Business account ID', 'string'),
('whatsapp_webhook_verify_token', '', 'WhatsApp webhook verification token', 'string')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- Create indexes for better performance
CREATE INDEX idx_whatsapp_history_batch ON whatsapp_history(batch_id);
CREATE INDEX idx_whatsapp_history_status ON whatsapp_history(status);
CREATE INDEX idx_whatsapp_history_sent_by ON whatsapp_history(sent_by);
CREATE INDEX idx_whatsapp_individual_phone ON whatsapp_individual(recipient_phone);
CREATE INDEX idx_whatsapp_webhooks_message ON whatsapp_webhooks(message_id);
CREATE INDEX idx_whatsapp_webhooks_processed ON whatsapp_webhooks(processed);