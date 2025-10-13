<?php
/**
 * System Settings
 * Configure church information and system preferences
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

requireLogin();
if ($_SESSION['user_role'] !== 'administrator') {
    setFlashMessage('error', 'Only administrators can access system settings');
    redirect(BASE_URL . 'modules/dashboard/');
}

$db = Database::getInstance();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_church_info') {
        // Handle logo upload
        $logoPath = null;
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = handleFileUpload(
                $_FILES['logo'],
                ASSETS_PATH . 'images/',
                ALLOWED_IMAGE_TYPES,
                MAX_PHOTO_SIZE
            );
            
            if ($uploadResult['success']) {
                $logoPath = 'assets/images/' . $uploadResult['filename'];
            }
        }
        
        $data = [
            'church_name' => sanitizeInput($_POST['church_name']),
            'address' => sanitizeInput($_POST['address']),
            'phone' => sanitizeInput($_POST['phone']),
            'email' => sanitizeInput($_POST['email']),
            'website' => sanitizeInput($_POST['website']),
            'mission_statement' => sanitizeInput($_POST['mission_statement']),
            'vision_statement' => sanitizeInput($_POST['vision_statement']),
            'values' => sanitizeInput($_POST['values']),
            'yearly_theme' => sanitizeInput($_POST['yearly_theme']),
            'pastor_name' => sanitizeInput($_POST['pastor_name']),
            'founded_date' => sanitizeInput($_POST['founded_date']),
            'service_times' => sanitizeInput($_POST['service_times'])
        ];
        
        if ($logoPath) {
            $data['logo'] = $logoPath;
        }
        
        // Check if church info exists
        $existing = getRecord('church_info', 'id', 1);
        
        if ($existing) {
            $result = updateRecord('church_info', $data, ['id' => 1]);
        } else {
            $result = insertRecord('church_info', $data);
        }
        
        if ($result) {
            logActivity('Updated church information');
            setFlashMessage('success', 'Church information updated successfully');
        } else {
            setFlashMessage('error', 'Failed to update church information');
        }
        
        redirect($_SERVER['PHP_SELF']);
    }
    
    if ($action === 'update_system_settings') {
        $settings = [
            'sms_sender_id' => sanitizeInput($_POST['sms_sender_id']),
            'backup_frequency' => (int)$_POST['backup_frequency'],
            'session_timeout' => (int)$_POST['session_timeout'],
            'max_login_attempts' => (int)$_POST['max_login_attempts'],
            'account_lockout_duration' => (int)$_POST['account_lockout_duration']
        ];
        
        foreach ($settings as $key => $value) {
            $existing = getRecord('system_settings', 'setting_key', $key);
            if ($existing) {
                updateRecord('system_settings', ['setting_value' => $value], ['setting_key' => $key]);
            } else {
                insertRecord('system_settings', [
                    'setting_key' => $key,
                    'setting_value' => $value
                ]);
            }
        }
        
        logActivity('Updated system settings');
        setFlashMessage('success', 'System settings updated successfully');
        redirect($_SERVER['PHP_SELF']);
    }
    
    if ($action === 'update_email_settings') {
        $settings = [
            'mail_host' => sanitizeInput($_POST['mail_host']),
            'mail_port' => (int)$_POST['mail_port'],
            'mail_username' => sanitizeInput($_POST['mail_username']),
            'mail_from_name' => sanitizeInput($_POST['mail_from_name'])
        ];
        
        if (!empty($_POST['mail_password'])) {
            $settings['mail_password'] = sanitizeInput($_POST['mail_password']);
        }
        
        foreach ($settings as $key => $value) {
            $existing = getRecord('system_settings', 'setting_key', $key);
            if ($existing) {
                updateRecord('system_settings', ['setting_value' => $value], ['setting_key' => $key]);
            } else {
                insertRecord('system_settings', [
                    'setting_key' => $key,
                    'setting_value' => $value
                ]);
            }
        }
        
        logActivity('Updated email settings');
        setFlashMessage('success', 'Email settings updated successfully');
        redirect($_SERVER['PHP_SELF']);
    }
}

// Get church info
$churchInfo = getRecord('church_info', 'id', 1) ?: [];

// Get system settings
$stmt = $db->executeQuery("SELECT setting_key, setting_value FROM system_settings");
$settingsData = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$page_title = 'System Settings';
$page_icon = 'fas fa-cogs';
$breadcrumb = [
    ['title' => 'Administration', 'url' => BASE_URL . 'modules/admin/'],
    ['title' => 'Settings']
];

include '../../includes/header.php';
?>

<!-- Settings Navigation Tabs -->
<ul class="nav nav-tabs mb-4" id="settingsTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="church-tab" data-bs-toggle="tab" data-bs-target="#church" type="button">
            <i class="fas fa-church me-2"></i>Church Information
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="system-tab" data-bs-toggle="tab" data-bs-target="#system" type="button">
            <i class="fas fa-cog me-2"></i>System Settings
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="email-tab" data-bs-toggle="tab" data-bs-target="#email" type="button">
            <i class="fas fa-envelope me-2"></i>Email Settings
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="sms-tab" data-bs-toggle="tab" data-bs-target="#sms" type="button">
            <i class="fas fa-sms me-2"></i>SMS Settings
        </button>
    </li>
</ul>

<!-- Tab Content -->
<div class="tab-content" id="settingsTabContent">
    
    <!-- Church Information Tab -->
    <div class="tab-pane fade show active" id="church" role="tabpanel">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0 text-church-blue">
                    <i class="fas fa-church me-2"></i>Church Information
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update_church_info">
                    
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Church Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="church_name" 
                                   value="<?php echo htmlspecialchars($churchInfo['church_name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Founded Date</label>
                            <input type="date" class="form-control" name="founded_date" 
                                   value="<?php echo $churchInfo['founded_date'] ?? ''; ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" name="phone" 
                                   value="<?php echo htmlspecialchars($churchInfo['phone'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" 
                                   value="<?php echo htmlspecialchars($churchInfo['email'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Website</label>
                            <input type="url" class="form-control" name="website" 
                                   value="<?php echo htmlspecialchars($churchInfo['website'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Pastor Name</label>
                            <input type="text" class="form-control" name="pastor_name" 
                                   value="<?php echo htmlspecialchars($churchInfo['pastor_name'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" name="address" rows="2"><?php echo htmlspecialchars($churchInfo['address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Service Times</label>
                        <textarea class="form-control" name="service_times" rows="2" 
                                  placeholder="e.g., Sunday: 9:00 AM, Wednesday: 6:00 PM"><?php echo htmlspecialchars($churchInfo['service_times'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Mission Statement</label>
                        <textarea class="form-control" name="mission_statement" rows="3"><?php echo htmlspecialchars($churchInfo['mission_statement'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Vision Statement</label>
                        <textarea class="form-control" name="vision_statement" rows="3"><?php echo htmlspecialchars($churchInfo['vision_statement'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Core Values</label>
                        <textarea class="form-control" name="values" rows="3"><?php echo htmlspecialchars($churchInfo['values'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Yearly Theme</label>
                        <input type="text" class="form-control" name="yearly_theme" 
                               value="<?php echo htmlspecialchars($churchInfo['yearly_theme'] ?? ''); ?>"
                               placeholder="e.g., Year of Divine Breakthrough 2025">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Church Logo</label>
                        <?php if (!empty($churchInfo['logo'])): ?>
                            <div class="mb-2">
                                <img src="<?php echo BASE_URL . $churchInfo['logo']; ?>" 
                                     alt="Current Logo" style="max-height: 100px;">
                            </div>
                        <?php endif; ?>
                        <input type="file" class="form-control" name="logo" accept="image/*">
                        <small class="text-muted">Recommended size: 200x200px, Max 2MB</small>
                    </div>
                    
                    <button type="submit" class="btn btn-church-primary">
                        <i class="fas fa-save me-2"></i>Save Church Information
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- System Settings Tab -->
    <div class="tab-pane fade" id="system" role="tabpanel">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0 text-church-blue">
                    <i class="fas fa-cog me-2"></i>System Settings
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_system_settings">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Session Timeout (minutes)</label>
                            <input type="number" class="form-control" name="session_timeout" 
                                   value="<?php echo ($settingsData['session_timeout'] ?? 60) / 60; ?>" min="10" max="480">
                            <small class="text-muted">How long until users are automatically logged out</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Backup Frequency (days)</label>
                            <input type="number" class="form-control" name="backup_frequency" 
                                   value="<?php echo $settingsData['backup_frequency'] ?? 7; ?>" min="1" max="30">
                            <small class="text-muted">How often to create automatic backups</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Max Login Attempts</label>
                            <input type="number" class="form-control" name="max_login_attempts" 
                                   value="<?php echo $settingsData['max_login_attempts'] ?? 3; ?>" min="3" max="10">
                            <small class="text-muted">Number of failed attempts before account lockout</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Account Lockout Duration (minutes)</label>
                            <input type="number" class="form-control" name="account_lockout_duration" 
                                   value="<?php echo ($settingsData['account_lockout_duration'] ?? 1800) / 60; ?>" min="5" max="120">
                            <small class="text-muted">How long accounts remain locked after max attempts</small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">SMS Sender ID</label>
                        <input type="text" class="form-control" name="sms_sender_id" 
                               value="<?php echo htmlspecialchars($settingsData['sms_sender_id'] ?? 'CHURCH'); ?>" 
                               maxlength="11">
                        <small class="text-muted">Name that appears as SMS sender (max 11 characters)</small>
                    </div>
                    
                    <button type="submit" class="btn btn-church-primary">
                        <i class="fas fa-save me-2"></i>Save System Settings
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Email Settings Tab -->
    <div class="tab-pane fade" id="email" role="tabpanel">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0 text-church-blue">
                    <i class="fas fa-envelope me-2"></i>Email Settings
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_email_settings">
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Configure SMTP settings for sending emails from the system
                    </div>
                    
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">SMTP Host</label>
                            <input type="text" class="form-control" name="mail_host" 
                                   value="<?php echo htmlspecialchars($settingsData['mail_host'] ?? 'smtp.gmail.com'); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">SMTP Port</label>
                            <input type="number" class="form-control" name="mail_port" 
                                   value="<?php echo $settingsData['mail_port'] ?? 587; ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Username/Email</label>
                        <input type="email" class="form-control" name="mail_username" 
                               value="<?php echo htmlspecialchars($settingsData['mail_username'] ?? ''); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" name="mail_password" 
                               placeholder="Leave blank to keep current password">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">From Name</label>
                        <input type="text" class="form-control" name="mail_from_name" 
                               value="<?php echo htmlspecialchars($settingsData['mail_from_name'] ?? 'Deliverance Church'); ?>">
                    </div>
                    
                    <button type="submit" class="btn btn-church-primary">
                        <i class="fas fa-save me-2"></i>Save Email Settings
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="testEmail()">
                        <i class="fas fa-paper-plane me-2"></i>Send Test Email
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- SMS Settings Tab -->
    <div class="tab-pane fade" id="sms" role="tabpanel">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0 text-church-blue">
                    <i class="fas fa-sms me-2"></i>SMS Settings
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Note:</strong> SMS configuration requires integration with an SMS provider (e.g., Africa's Talking). 
                    Contact your system administrator for setup.
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Current SMS Balance</label>
                        <div class="input-group">
                            <span class="input-group-text">Ksh</span>
                            <input type="number" class="form-control" value="<?php echo $churchInfo['sms_balance'] ?? 0; ?>" readonly>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Cost Per SMS</label>
                        <div class="input-group">
                            <span class="input-group-text">Ksh</span>
                            <input type="number" class="form-control" value="<?php echo SMS_COST_PER_SMS; ?>" readonly>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <h6>SMS Statistics (Last 30 Days)</h6>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card bg-success bg-opacity-10 border-0">
                                <div class="card-body text-center">
                                    <h3 class="text-success mb-0">
                                        <?php 
                                        $stmt = $db->executeQuery("
                                            SELECT COUNT(*) FROM sms_individual 
                                            WHERE sent_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) 
                                            AND status IN ('sent', 'delivered')
                                        ");
                                        echo $stmt->fetchColumn();
                                        ?>
                                    </h3>
                                    <small class="text-muted">SMS Sent</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-danger bg-opacity-10 border-0">
                                <div class="card-body text-center">
                                    <h3 class="text-danger mb-0">
                                        <?php 
                                        $stmt = $db->executeQuery("
                                            SELECT COUNT(*) FROM sms_individual 
                                            WHERE sent_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) 
                                            AND status = 'failed'
                                        ");
                                        echo $stmt->fetchColumn();
                                        ?>
                                    </h3>
                                    <small class="text-muted">Failed</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-primary bg-opacity-10 border-0">
                                <div class="card-body text-center">
                                    <h3 class="text-primary mb-0">
                                        <?php 
                                        $stmt = $db->executeQuery("
                                            SELECT SUM(cost) FROM sms_individual 
                                            WHERE sent_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                                        ");
                                        echo formatCurrency($stmt->fetchColumn() ?? 0);
                                        ?>
                                    </h3>
                                    <small class="text-muted">Total Cost</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
</div>

<script>
function testEmail() {
    ChurchCMS.showLoading('Sending test email...');
    
    setTimeout(() => {
        ChurchCMS.hideLoading();
        ChurchCMS.showToast('Test email sent! Check your inbox.', 'success');
    }, 2000);
}
</script>

<?php include '../../includes/footer.php'; ?>