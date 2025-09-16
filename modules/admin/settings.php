<div class="mb-3">
                                    <label for="address" class="form-label">Church Address</label>
                                    <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($churchInfo['address']); ?></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="phone" class="form-label">Phone Number</label>
                                            <input type="text" class="form-control" id="phone" name="phone" 
                                                   value="<?php echo htmlspecialchars($churchInfo['phone']); ?>" placeholder="+254700000000">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="email" class="form-label">Email Address</label>
                                            <input type="email" class="form-control" id="email" name="email" 
                                                   value="<?php echo htmlspecialchars($churchInfo['email']); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="website" class="form-label">Website URL</label>
                                            <input type="url" class="form-control" id="website" name="website" 
                                                   value="<?php echo htmlspecialchars($churchInfo['website']); ?>" placeholder="https://www.example.com">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="founded_date" class="form-label">Founded Date</label>
                                            <input type="date" class="form-control" id="founded_date" name="founded_date" 
                                                   value="<?php echo $churchInfo['founded_date']; ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="yearly_theme" class="form-label">Yearly Theme</label>
                                            <input type="text" class="form-control" id="yearly_theme" name="yearly_theme" 
                                                   value="<?php echo htmlspecialchars($churchInfo['yearly_theme']); ?>" 
                                                   placeholder="e.g., Year of Divine Breakthrough 2025">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="currency" class="form-label">Currency</label>
                                            <select class="form-select" id="currency" name="currency">
                                                <option value="KES" <?php echo $churchInfo['currency'] === 'KES' ? 'selected' : ''; ?>>Kenyan Shilling (KES)</option>
                                                <option value="USD" <?php echo $churchInfo['currency'] === 'USD' ? 'selected' : ''; ?>>US Dollar (USD)</option>
                                                <option value="EUR" <?php echo $churchInfo['currency'] === 'EUR' ? 'selected' : ''; ?>>Euro (EUR)</option>
                                                <option value="GBP" <?php echo $churchInfo['currency'] === 'GBP' ? 'selected' : ''; ?>>British Pound (GBP)</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="service_times" class="form-label">Service Times</label>
                                    <textarea class="form-control" id="service_times" name="service_times" rows="2" 
                                              placeholder="e.g., Sunday Service: 9:00 AM, Prayer Meeting: Wednesday 7:00 PM"><?php echo htmlspecialchars($churchInfo['service_times']); ?></textarea>
                                </div>
                            </div>
                            
                            <!-- Church Logo -->
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="logo" class="form-label">Church Logo</label>
                                    <div class="text-center">
                                        <?php if (!empty($churchInfo['logo']) && file_exists($churchInfo['logo'])): ?>
                                            <img src="<?php echo BASE_URL . $churchInfo['logo']; ?>" alt="Church Logo" 
                                                 class="img-fluid mb-3 border rounded" style="max-height: 150px;" id="logoPreview">
                                        <?php else: ?>
                                            <div class="border rounded d-flex align-items-center justify-content-center bg-light mb-3" 
                                                 style="height: 150px;" id="logoPreview">
                                                <i class="fas fa-church fa-3x text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <input type="file" class="form-control" id="logo" name="logo" accept="image/*" onchange="previewLogo(this)">
                                        <div class="form-text">Upload PNG, JPG, or GIF. Max size: 2MB</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Mission, Vision, Values -->
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="mission_statement" class="form-label">Mission Statement</label>
                                    <textarea class="form-control" id="mission_statement" name="mission_statement" rows="4"><?php echo htmlspecialchars($churchInfo['mission_statement']); ?></textarea>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="vision_statement" class="form-label">Vision Statement</label>
                                    <textarea class="form-control" id="vision_statement" name="vision_statement" rows="4"><?php echo htmlspecialchars($churchInfo['vision_statement']); ?></textarea>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="values" class="form-label">Core Values</label>
                                    <textarea class="form-control" id="values" name="values" rows="4"><?php echo htmlspecialchars($churchInfo['values']); ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-end">
                            <button type="submit" class="btn btn-church-primary">
                                <i class="fas fa-save me-1"></i>Save Church Information
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- System Settings Tab -->
                <div class="tab-pane fade" id="system-settings" role="tabpanel">
                    <form method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="action" value="update_system_settings">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="fw-bold mb-3">General Settings</h6>
                                
                                <div class="mb-3">
                                    <label for="site_name" class="form-label">Site Name</label>
                                    <input type="text" class="form-control" id="site_name" name="settings[site_name]" 
                                           value="<?php echo htmlspecialchars($systemSettings['site_name']); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="timezone" class="form-label">Timezone</label>
                                    <select class="form-select" id="timezone" name="settings[timezone]">
                                        <option value="Africa/Nairobi" <?php echo $systemSettings['timezone'] === 'Africa/Nairobi' ? 'selected' : ''; ?>>Africa/Nairobi</option>
                                        <option value="UTC" <?php echo $systemSettings['timezone'] === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                        <option value="America/New_York" <?php echo $systemSettings['timezone'] === 'America/New_York' ? 'selected' : ''; ?>>America/New_York</option>
                                        <option value="Europe/London" <?php echo $systemSettings['timezone'] === 'Europe/London' ? 'selected' : ''; ?>>Europe/London</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="date_format" class="form-label">Date Format</label>
                                    <select class="form-select" id="date_format" name="settings[date_format]">
                                        <option value="Y-m-d" <?php echo $systemSettings['date_format'] === 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD</option>
                                        <option value="d/m/Y" <?php echo $systemSettings['date_format'] === 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY</option>
                                        <option value="m/d/Y" <?php echo $systemSettings['date_format'] === 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY</option>
                                        <option value="d-M-Y" <?php echo $systemSettings['date_format'] === 'd-M-Y' ? 'selected' : ''; ?>>DD-MMM-YYYY</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="default_currency" class="form-label">Default Currency</label>
                                    <input type="text" class="form-control" id="default_currency" name="settings[default_currency]" 
                                           value="<?php echo htmlspecialchars($systemSettings['default_currency']); ?>" maxlength="3">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h6 class="fw-bold mb-3">Security Settings</h6>
                                
                                <div class="mb-3">
                                    <label for="session_timeout" class="form-label">Session Timeout (seconds)</label>
                                    <input type="number" class="form-control" id="session_timeout" name="settings[session_timeout]" 
                                           value="<?php echo htmlspecialchars($systemSettings['session_timeout']); ?>" min="300">
                                    <div class="form-text">Minimum 300 seconds (5 minutes)</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="max_login_attempts" class="form-label">Max Login Attempts</label>
                                    <input type="number" class="form-control" id="max_login_attempts" name="settings[max_login_attempts]" 
                                           value="<?php echo htmlspecialchars($systemSettings['max_login_attempts']); ?>" min="1" max="10">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="account_lockout_duration" class="form-label">Account Lockout Duration (seconds)</label>
                                    <input type="number" class="form-control" id="account_lockout_duration" name="settings[account_lockout_duration]" 
                                           value="<?php echo htmlspecialchars($systemSettings['account_lockout_duration']); ?>" min="60">
                                    <div class="form-text">Time to lock account after max failed attempts</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="backup_frequency" class="form-label">Backup Frequency (days)</label>
                                    <input type="number" class="form-control" id="backup_frequency" name="settings[backup_frequency]" 
                                           value="<?php echo htmlspecialchars($systemSettings['backup_frequency']); ?>" min="1">
                                    <div class="form-text">How often to remind about backups</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-end">
                            <button type="submit" class="btn btn-church-primary">
                                <i class="fas fa-save me-1"></i>Save System Settings
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- SMS Settings Tab -->
                <div class="tab-pane fade" id="sms-settings" role="tabpanel">
                    <form method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="action" value="update_sms_settings">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="fw-bold mb-3">SMS API Configuration</h6>
                                
                                <div class="mb-3">
                                    <label for="sms_api_key" class="form-label">API Key *</label>
                                    <input type="password" class="form-control" id="sms_api_key" name="sms_api_key" 
                                           value="<?php echo htmlspecialchars($systemSettings['sms_api_key']); ?>" required>
                                    <div class="invalid-feedback">Please provide SMS API key.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="sms_username" class="form-label">Username *</label>
                                    <input type="text" class="form-control" id="sms_username" name="sms_username" 
                                           value="<?php echo htmlspecialchars($systemSettings['sms_username']); ?>" required>
                                    <div class="invalid-feedback">Please provide SMS username.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="sms_sender_id" class="form-label">Sender ID *</label>
                                    <input type="text" class="form-control" id="sms_sender_id" name="sms_sender_id" 
                                           value="<?php echo htmlspecialchars($systemSettings['sms_sender_id']); ?>" 
                                           maxlength="11" required>
                                    <div class="form-text">Maximum 11 characters</div>
                                    <div class="invalid-feedback">Please provide SMS sender ID.</div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h6 class="fw-bold mb-3">SMS Limits & Costs</h6>
                                
                                <div class="mb-3">
                                    <label for="sms_cost_per_message" class="form-label">Cost per SMS (KES)</label>
                                    <input type="number" class="form-control" id="sms_cost_per_message" name="sms_cost_per_message" 
                                           value="<?php echo htmlspecialchars($systemSettings['sms_cost_per_message']); ?>" 
                                           step="0.01" min="0" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="sms_daily_limit" class="form-label">Daily SMS Limit</label>
                                    <input type="number" class="form-control" id="sms_daily_limit" name="sms_daily_limit" 
                                           value="<?php echo htmlspecialchars($systemSettings['sms_daily_limit']); ?>" 
                                           min="1" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="sms_batch_size" class="form-label">Batch Size</label>
                                    <input type="number" class="form-control" id="sms_batch_size" name="sms_batch_size" 
                                           value="<?php echo htmlspecialchars($systemSettings['sms_batch_size']); ?>" 
                                           min="1" max="1000" required>
                                    <div class="form-text">Number of SMS to send at once</div>
                                </div>
                                
                                <!-- SMS Test -->
                                <div class="border-top pt-3 mt-4">
                                    <h6 class="fw-bold mb-3">Test SMS</h6>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="test_phone" placeholder="+254700000000" 
                                               pattern="^\+254[0-9]{9}$">
                                        <button type="button" class="btn btn-outline-info" onclick="testSMS()">
                                            <i class="fas fa-paper-plane me-1"></i>Send Test
                                        </button>
                                    </div>
                                    <div class="form-text">Send a test SMS to verify configuration</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-end">
                            <button type="submit" class="btn btn-church-primary">
                                <i class="fas fa-save me-1"></i>Save SMS Settings
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Email Settings Tab -->
                <div class="tab-pane fade" id="email-settings" role="tabpanel">
                    <form method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="action" value="update_email_settings">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="fw-bold mb-3">SMTP Configuration</h6>
                                
                                <div class="mb-3">
                                    <label for="mail_host" class="form-label">SMTP Host *</label>
                                    <input type="text" class="form-control" id="mail_host" name="mail_host" 
                                           value="<?php echo htmlspecialchars($systemSettings['mail_host']); ?>" required>
                                    <div class="invalid-feedback">Please provide SMTP host.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="mail_port" class="form-label">SMTP Port *</label>
                                    <input type="number" class="form-control" id="mail_port" name="mail_port" 
                                           value="<?php echo htmlspecialchars($systemSettings['mail_port']); ?>" required>
                                    <div class="form-text">Common ports: 25, 465 (SSL), 587 (TLS)</div>
                                    <div class="invalid-feedback">Please provide SMTP port.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="mail_username" class="form-label">Email Username *</label>
                                    <input type="email" class="form-control" id="mail_username" name="mail_username" 
                                           value="<?php echo htmlspecialchars($systemSettings['mail_username']); ?>" required>
                                    <div class="invalid-feedback">Please provide valid email username.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="mail_password" class="form-label">Email Password *</label>
                                    <input type="password" class="form-control" id="mail_password" name="mail_password" 
                                           value="<?php echo htmlspecialchars($systemSettings['mail_password']); ?>" required>
                                    <div class="invalid-feedback">Please provide email password.</div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h6 class="fw-bold mb-3">Sender Information</h6>
                                
                                <div class="mb-3">
                                    <label for="mail_from_name" class="form-label">From Name *</label>
                                    <input type="text" class="form-control" id="mail_from_name" name="mail_from_name" 
                                           value="<?php echo htmlspecialchars($systemSettings['mail_from_name']); ?>" required>
                                    <div class="invalid-feedback">Please provide sender name.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="mail_from_email" class="form-label">From Email *</label>
                                    <input type="email" class="form-control" id="mail_from_email" name="mail_from_email" 
                                           value="<?php echo htmlspecialchars($systemSettings['mail_from_email']); ?>" required>
                                    <div class="invalid-feedback">Please provide valid sender email.</div>
                                </div>
                                
                                <!-- Email Test -->
                                <div class="border-top pt-3 mt-4">
                                    <h6 class="fw-bold mb-3">Test Email</h6>
                                    <div class="input-group">
                                        <input type="email" class="form-control" id="test_email" placeholder="test@example.com">
                                        <button type="button" class="btn btn-outline-info" onclick="testEmail()">
                                            <i class="fas fa-envelope me-1"></i>Send Test
                                        </button>
                                    </div>
                                    <div class="form-text">Send a test email to verify configuration</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-end">
                            <button type="submit" class="btn btn-church-primary">
                                <i class="fas fa-save me-1"></i>Save Email Settings
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Backup Settings Tab -->
                <div class="tab-pane fade" id="backup-settings" role="tabpanel">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="fw-bold mb-3">Backup Configuration</h6>
                            
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title">Automatic Backups</h6>
                                    <p class="card-text">Configure automatic database backups to ensure data safety.</p>
                                    
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="enable_auto_backup" 
                                                   <?php echo AUTO_BACKUP_ENABLED ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="enable_auto_backup">
                                                Enable Automatic Backups
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="backup_frequency_setting" class="form-label">Backup Frequency</label>
                                        <select class="form-select" id="backup_frequency_setting">
                                            <option value="daily">Daily</option>
                                            <option value="weekly" selected>Weekly</option>
                                            <option value="monthly">Monthly</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="backup_retention" class="form-label">Keep Backups</label>
                                        <select class="form-select" id="backup_retention">
                                            <option value="5">5 backups</option>
                                            <option value="10" selected>10 backups</option>
                                            <option value="20">20 backups</option>
                                            <option value="50">50 backups</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <h6 class="fw-bold mb-3">Backup Actions</h6>
                            
                            <div class="d-grid gap-2">
                                <a href="<?php echo BASE_URL; ?>modules/admin/backup.php" class="btn btn-church-primary">
                                    <i class="fas fa-database me-2"></i>Create Backup Now
                                </a>
                                
                                <button type="button" class="btn btn-outline-info" onclick="viewBackupHistory()">
                                    <i class="fas fa-history me-2"></i>View Backup History
                                </button>
                                
                                <button type="button" class="btn btn-outline-warning" onclick="validateBackups()">
                                    <i class="fas fa-check-circle me-2"></i>Validate Backups
                                </button>
                                
                                <button type="button" class="btn btn-outline-danger" onclick="cleanOldBackups()">
                                    <i class="fas fa-trash me-2"></i>Clean Old Backups
                                </button>
                            </div>
                            
                            <!-- Backup Status -->
                            <div class="mt-4">
                                <h6 class="fw-bold">Backup Status</h6>
                                <div class="small">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span>Last Backup:</span>
                                        <span class="fw-semibold">
                                            <?php 
                                            $backupFiles = glob(BACKUP_PATH . '*.sql');
                                            if (!empty($backupFiles)) {
                                                $lastBackup = max($backupFiles);
                                                echo date('Y-m-d H:i', filemtime($lastBackup));
                                            } else {
                                                echo 'Never';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-1">
                                        <span>Total Backups:</span>
                                        <span class="fw-semibold"><?php echo count(glob(BACKUP_PATH . '*.sql')); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>Storage Used:</span>
                                        <span class="fw-semibold">
                                            <?php
                                            $totalSize = 0;
                                            foreach (glob(BACKUP_PATH . '*.sql') as $file) {
                                                $totalSize += filesize($file);
                                            }
                                            echo formatFileSize($totalSize);
                                            ?>
                                        </span>
                                    </div>
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
// Logo preview function
function previewLogo(input) {
    const preview = document.getElementById('logoPreview');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            preview.innerHTML = `<img src="${e.target.result}" alt="Logo Preview" class="img-fluid border rounded" style="max-height: 150px;">`;
        };
        
        reader.readAsDataURL(input.files[0]);
    }
}

// Test SMS function
function testSMS() {
    const phoneInput = document.getElementById('test_phone');
    const phone = phoneInput.value.trim();
    
    if (!phone) {
        ChurchCMS.showToast('Please enter a phone number', 'warning');
        phoneInput.focus();
        return;
    }
    
    if (!ChurchCMS.isValidPhone(phone)) {
        ChurchCMS.showToast('Please enter a valid phone number', 'error');
        phoneInput.focus();
        return;
    }
    
    ChurchCMS.showLoading('Sending test SMS...');
    
    const formData = new FormData();
    formData.append('action', 'test_sms');
    formData.append('test_phone', phone);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(() => {
        ChurchCMS.hideLoading();
        ChurchCMS.showToast('Test SMS sent successfully', 'success');
    })
    .catch(error => {
        ChurchCMS.hideLoading();
        ChurchCMS.showToast('Failed to send test SMS', 'error');
    });
}

// Test email function
function testEmail() {
    const emailInput = document.getElementById('test_email');
    const email = emailInput.value.trim();
    
    if (!email) {
        ChurchCMS.showToast('Please enter an email address', 'warning');
        emailInput.focus();
        return;
    }
    
    if (!ChurchCMS.isValidEmail(email)) {
        ChurchCMS.showToast('Please enter a valid email address', 'error');
        emailInput.focus();
        return;
    }
    
    ChurchCMS.showLoading('Sending test email...');
    
    const formData = new FormData();
    formData.append('action', 'test_email');
    formData.append('test_email', email);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(() => {
        ChurchCMS.hideLoading();
        ChurchCMS.showToast('Test email sent successfully', 'success');
    })
    .catch(error => {
        ChurchCMS.hideLoading();
        ChurchCMS.showToast('Failed to send test email', 'error');
    });
}

// Export settings
function exportSettings() {
    ChurchCMS.showLoading('Exporting settings...');
    
    fetch(`${BASE_URL}api/admin.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ action: 'export_settings' })
    })
    .then(response => response.blob())
    .then(blob => {
        ChurchCMS.hideLoading();
        
        // Create download link
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `church_settings_${new Date().toISOString().split('T')[0]}.json`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        
        ChurchCMS.showToast('Settings exported successfully', 'success');
    })
    .catch(error => {
        ChurchCMS.hideLoading();
        ChurchCMS.showToast('Failed to export settings', 'error');
    });
}

// Import settings
function importSettings() {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = '.json';
    
    input.onchange = function(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        const reader = new FileReader();
        reader.onload = function(e) {
            try {
                const settings = JSON.parse(e.target.result);
                
                ChurchCMS.showConfirm('This will overwrite current settings. Continue?', function() {
                    ChurchCMS.showLoading('Importing settings...');
                    
                    fetch(`${BASE_URL}api/admin.php`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ 
                            action: 'import_settings', 
                            settings: settings 
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        ChurchCMS.hideLoading();
                        if (data.success) {
                            ChurchCMS.showToast('Settings imported successfully. Refreshing page...', 'success');
                            setTimeout(() => location.reload(), 2000);
                        } else {
                            ChurchCMS.showToast(data.message || 'Failed to import settings', 'error');
                        }
                    })
                    .catch(error => {
                        ChurchCMS.hideLoading();
                        ChurchCMS.showToast('Failed to import settings', 'error');
                    });
                });
                
            } catch (error) {
                ChurchCMS.showToast('Invalid settings file format', 'error');
            }
        };
        reader.readAsText(file);
    };
    
    input.click();
}

// Reset to defaults
function resetToDefaults() {
    ChurchCMS.showConfirm('This will reset all settings to default values. This action cannot be undone. Continue?', function() {
        ChurchCMS.showLoading('Resetting settings...');
        
        fetch(`${BASE_URL}api/admin.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ action: 'reset_settings' })
        })
        .then(response => response.json())
        .then(data => {
            ChurchCMS.hideLoading();
            if (data.success) {
                ChurchCMS.showToast('Settings reset successfully. Refreshing page...', 'success');
                setTimeout(() => location.reload(), 2000);
            } else {
                ChurchCMS.showToast(data.message || 'Failed to reset settings', 'error');
            }
        })
        .catch(error => {
            ChurchCMS.hideLoading();
            ChurchCMS.showToast('Failed to reset settings', 'error');
        });
    });
}

// View backup history
function viewBackupHistory() {
    ChurchCMS.showLoading('Loading backup history...');
    
    fetch(`${BASE_URL}api/admin.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ action: 'get_backup_history' })
    })
    .then(response => response.json())
    .then(data => {
        ChurchCMS.hideLoading();
        
        if (data.success) {
            let historyHtml = '<div class="table-responsive"><table class="table table-sm">';
            historyHtml += '<thead><tr><th>Date</th><th>Size</th><th>Actions</th></tr></thead><tbody>';
            
            if (data.backups.length > 0) {
                data.backups.forEach(backup => {
                    historyHtml += `<tr>
                        <td>${ChurchCMS.formatDate(backup.date, true)}</td>
                        <td>${backup.size}</td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="downloadBackup('${backup.filename}')">
                                <i class="fas fa-download"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteBackup('${backup.filename}')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>`;
                });
            } else {
                historyHtml += '<tr><td colspan="3" class="text-center">No backups found</td></tr>';
            }
            
            historyHtml += '</tbody></table></div>';
            
            // Create modal
            const modalHtml = `
                <div class="modal fade" id="backupHistoryModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="fas fa-history me-2"></i>Backup History
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                ${historyHtml}
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove existing modal if any
            const existingModal = document.getElementById('backupHistoryModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            new bootstrap.Modal(document.getElementById('backupHistoryModal')).show();
        } else {
            ChurchCMS.showToast(data.message || 'Failed to load backup history', 'error');
        }
    })
    .catch(error => {
        ChurchCMS.hideLoading();
        ChurchCMS.showToast('Failed to load backup history', 'error');
    });
}

// Validate backups
function validateBackups() {
    ChurchCMS.showLoading('Validating backups...');
    
    fetch(`${BASE_URL}api/admin.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ action: 'validate_backups' })
    })
    .then(response => response.json())
    .then(data => {
        ChurchCMS.hideLoading();
        
        if (data.success) {
            let message = 'Backup Validation Results:\n\n';
            message += `Total backups: ${data.total}\n`;
            message += `Valid backups: ${data.valid}\n`;
            message += `Invalid backups: ${data.invalid}\n`;
            
            if (data.details.length > 0) {
                message += '\nDetails:\n';
                data.details.forEach(detail => {
                    message += `- ${detail}\n`;
                });
            }
            
            alert(message);
        } else {
            ChurchCMS.showToast(data.message || 'Failed to validate backups', 'error');
        }
    })
    .catch(error => {
        ChurchCMS.hideLoading();
        ChurchCMS.showToast('Failed to validate backups', 'error');
    });
}

// Clean old backups
function cleanOldBackups() {
    ChurchCMS.showConfirm('This will remove old backup files to free up space. Continue?', function() {
        ChurchCMS.showLoading('Cleaning old backups...');
        
        fetch(`${BASE_URL}api/admin.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ action: 'clean_old_backups' })
        })
        .then(response => response.json())
        .then(data => {
            ChurchCMS.hideLoading();
            
            if (data.success) {
                ChurchCMS.showToast(`Cleaned ${data.removed} old backup files`, 'success');
            } else {
                ChurchCMS.showToast(data.message || 'Failed to clean backups', 'error');
            }
        })
        .catch(error => {
            ChurchCMS.hideLoading();
            ChurchCMS.showToast('Failed to clean backups', 'error');
        });
    });
}

// Download backup
function downloadBackup(filename) {
    window.open(`${BASE_URL}api/admin.php?action=download_backup&filename=${encodeURIComponent(filename)}`, '_blank');
}

// Delete backup
function deleteBackup(filename) {
    ChurchCMS.showConfirm(`Delete backup file "${filename}"? This action cannot be undone.`, function() {
        ChurchCMS.showLoading('Deleting backup...');
        
        fetch(`${BASE_URL}api/admin.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ 
                action: 'delete_backup', 
                filename: filename 
            })
        })
        .then(response => response.json())
        .then(data => {
            ChurchCMS.hideLoading();
            
            if (data.success) {
                ChurchCMS.showToast('Backup deleted successfully', 'success');
                // Refresh the backup history modal if open
                if (document.getElementById('backupHistoryModal')) {
                    viewBackupHistory();
                }
            } else {
                ChurchCMS.showToast(data.message || 'Failed to delete backup', 'error');
            }
        })
        .catch(error => {
            ChurchCMS.hideLoading();
            ChurchCMS.showToast('Failed to delete backup', 'error');
        });
    });
}

// Form validation and enhancements
document.addEventListener('DOMContentLoaded', function() {
    // Phone number formatting
    const phoneInputs = document.querySelectorAll('input[type="text"][id*="phone"]');
    phoneInputs.forEach(input => {
        input.addEventListener('input', function() {
            let value = this.value.replace(/[^\d+]/g, '');
            if (value.startsWith('0') && value.length === 10) {
                value = '+254' + value.substr(1);
            } else if (value.startsWith('254') && value.length === 12) {
                value = '+' + value;
            }
            this.value = value;
        });
    });
    
    // URL validation
    const urlInputs = document.querySelectorAll('input[type="url"]');
    urlInputs.forEach(input => {
        input.addEventListener('blur', function() {
            if (this.value && !this.value.startsWith('http://') && !this.value.startsWith('https://')) {
                this.value = 'https://' + this.value;
            }
        });
    });
    
    // Auto-save draft (every 30 seconds)
    let autoSaveTimer;
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        const inputs = form.querySelectorAll('input, textarea, select');
        
        inputs.forEach(input => {
            if (input.type !== 'password' && input.type !== 'file') {
                input.addEventListener('input', function() {
                    clearTimeout(autoSaveTimer);
                    autoSaveTimer = setTimeout(() => {
                        saveDraft(form);
                    }, 30000);
                });
            }
        });
    });
    
    // Load saved drafts
    loadDrafts();
    
    // Password strength indicator
    const passwordInputs = document.querySelectorAll('input[type="password"]');
    passwordInputs.forEach(input => {
        if (input.name && input.name.includes('password') && !input.name.includes('confirm')) {
            const strengthIndicator = createPasswordStrengthIndicator();
            input.parentNode.insertBefore(strengthIndicator, input.nextSibling);
            
            input.addEventListener('input', function() {
                updatePasswordStrength(this.value, strengthIndicator);
            });
        }
    });
    
    // Tab persistence
    const tabs = document.querySelectorAll('#settingsTabs button[data-bs-toggle="tab"]');
    tabs.forEach(tab => {
        tab.addEventListener('shown.bs.tab', function(e) {
            localStorage.setItem('activeSettingsTab', e.target.getAttribute('data-bs-target'));
        });
    });
    
    // Restore active tab
    const activeTab = localStorage.getItem('activeSettingsTab');
    if (activeTab) {
        const tabButton = document.querySelector(`#settingsTabs button[data-bs-target="${activeTab}"]`);
        if (tabButton) {
            new bootstrap.Tab(tabButton).show();
        }
    }
});

// Save form draft
function saveDraft(form) {
    const formId = form.id || form.querySelector('input[name="action"]')?.value || 'settings_form';
    const formData = new FormData(form);
    const data = {};
    
    for (let [key, value] of formData.entries()) {
        if (key !== 'action' && !key.includes('password')) {
            data[key] = value;
        }
    }
    
    localStorage.setItem(`draft_${formId}`, JSON.stringify(data));
}

// Load saved drafts
function loadDrafts() {
    Object.keys(localStorage).forEach(key => {
        if (key.startsWith('draft_')) {
            try {
                const data = JSON.parse(localStorage.getItem(key));
                Object.keys(data).forEach(fieldName => {
                    const field = document.querySelector(`[name="${fieldName}"]`);
                    if (field && field.type !== 'password' && field.type !== 'file') {
                        if (field.type === 'checkbox') {
                            field.checked = data[fieldName] === 'on';
                        } else {
                            field.value = data[fieldName];
                        }
                    }
                });
            } catch (e) {
                // Invalid draft data, remove it
                localStorage.removeItem(key);
            }
        }
    });
}

// Create password strength indicator
function createPasswordStrengthIndicator() {
    const indicator = document.createElement('div');
    indicator.className = 'password-strength mt-1';
    indicator.innerHTML = `
        <div class="progress" style="height: 3px;">
            <div class="progress-bar" role="progressbar" style="width: 0%"></div>
        </div>
        <small class="text-muted strength-text">Password strength</small>
    `;
    return indicator;
}

// Update password strength
function updatePasswordStrength(password, indicator) {
    const progressBar = indicator.querySelector('.progress-bar');
    const strengthText = indicator.querySelector('.strength-text');
    
    let strength = 0;
    let feedback = '';
    
    if (password.length >= 8) strength += 1;
    if (password.match(/[a-z]/)) strength += 1;
    if (password.match(/[A-Z]/)) strength += 1;
    if (password.match(/[0-9]/)) strength += 1;
    if (password.match(/[^a-zA-Z0-9]/)) strength += 1;
    
    const strengthPercent = (strength / 5) * 100;
    
    switch (strength) {
        case 0:
        case 1:
            progressBar.className = 'progress-bar bg-danger';
            feedback = 'Very weak';
            break;
        case 2:
            progressBar.className = 'progress-bar bg-warning';
            feedback = 'Weak';
            break;
        case 3:
            progressBar.className = 'progress-bar bg-info';
            feedback = 'Fair';
            break;
        case 4:
            progressBar.className = 'progress-bar bg-success';
            feedback = 'Good';
            break;
        case 5:
            progressBar.className = 'progress-bar bg-success';
            feedback = 'Strong';
            break;
    }
    
    progressBar.style.width = strengthPercent + '%';
    strengthText.textContent = password.length > 0 ? feedback : 'Password strength';
}

// Clear drafts on successful form submission
window.addEventListener('beforeunload', function() {
    // Check if form was submitted successfully (you might want to implement a flag for this)
    const successMessage = document.querySelector('.alert-success');
    if (successMessage) {
        Object.keys(localStorage).forEach(key => {
            if (key.startsWith('draft_')) {
                localStorage.removeItem(key);
            }
        });
    }
});
</script>

<?php include_once '../../includes/footer.php'; ?><?php
/**
 * System Settings
 * Deliverance Church Management System
 * 
 * Manage all system settings and configurations
 */

// Include configuration and functions
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Check authentication and admin role
requireLogin();
if (!hasPermission('admin') && $_SESSION['user_role'] !== 'administrator') {
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit();
}

// Page configuration
$page_title = 'System Settings';
$page_icon = 'fas fa-cogs';
$breadcrumb = [
    ['title' => 'Administration', 'url' => BASE_URL . 'modules/admin/'],
    ['title' => 'System Settings']
];

$additional_css = ['assets/css/admin.css'];
$additional_js = ['assets/js/admin.js'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        $db = Database::getInstance();
        
        if ($action === 'update_church_info') {
            // Handle church information update
            $validation = validateInput($_POST, [
                'church_name' => ['required', 'max:100'],
                'address' => ['max:500'],
                'phone' => ['max:20'],
                'email' => ['email', 'max:100'],
                'website' => ['max:100'],
                'mission_statement' => ['max:1000'],
                'vision_statement' => ['max:1000'],
                'values' => ['max:1000'],
                'yearly_theme' => ['max:255'],
                'pastor_name' => ['max:100'],
                'founded_date' => ['date'],
                'service_times' => ['max:500'],
                'currency' => ['max:10']
            ]);
            
            if (!$validation['valid']) {
                setFlashMessage('error', implode(', ', $validation['errors']));
            } else {
                // Handle logo upload
                $logoPath = '';
                if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                    $uploadResult = handleFileUpload($_FILES['logo'], ASSETS_PATH . 'images/', ALLOWED_IMAGE_TYPES, MAX_PHOTO_SIZE);
                    
                    if ($uploadResult['success']) {
                        $logoPath = 'assets/images/' . $uploadResult['filename'];
                    } else {
                        setFlashMessage('error', 'Logo upload failed: ' . $uploadResult['message']);
                    }
                }
                
                if (!isset($_SESSION['flash_message'])) {
                    $updateData = $validation['data'];
                    if (!empty($logoPath)) {
                        $updateData['logo'] = $logoPath;
                    }
                    
                    // Check if church info exists
                    $churchInfo = getRecord('church_info', 'id', 1);
                    
                    if ($churchInfo) {
                        $updated = updateRecord('church_info', $updateData, ['id' => 1]);
                    } else {
                        $updateData['id'] = 1;
                        $updated = insertRecord('church_info', $updateData);
                    }
                    
                    if ($updated) {
                        logActivity('Church information updated', 'church_info', 1);
                        setFlashMessage('success', 'Church information updated successfully');
                    } else {
                        setFlashMessage('error', 'Failed to update church information');
                    }
                }
            }
            
        } elseif ($action === 'update_system_settings') {
            // Handle system settings update
            $settings = $_POST['settings'] ?? [];
            $updatedCount = 0;
            
            foreach ($settings as $key => $value) {
                // Validate specific settings
                if ($key === 'session_timeout' && (!is_numeric($value) || $value < 300)) {
                    setFlashMessage('error', 'Session timeout must be at least 300 seconds (5 minutes)');
                    continue;
                }
                
                if ($key === 'max_login_attempts' && (!is_numeric($value) || $value < 1)) {
                    setFlashMessage('error', 'Maximum login attempts must be at least 1');
                    continue;
                }
                
                // Check if setting exists
                $existingSetting = getRecord('system_settings', 'setting_key', $key);
                
                if ($existingSetting) {
                    $updated = updateRecord('system_settings', ['setting_value' => $value], ['setting_key' => $key]);
                } else {
                    $updated = insertRecord('system_settings', [
                        'setting_key' => $key,
                        'setting_value' => $value,
                        'description' => SYSTEM_SETTINGS_KEYS[$key] ?? $key,
                        'data_type' => 'string'
                    ]);
                }
                
                if ($updated) {
                    $updatedCount++;
                }
            }
            
            if ($updatedCount > 0) {
                logActivity('System settings updated', 'system_settings', null, null, $settings);
                setFlashMessage('success', "Updated {$updatedCount} system settings successfully");
            } else {
                setFlashMessage('error', 'Failed to update system settings');
            }
            
        } elseif ($action === 'update_sms_settings') {
            // Handle SMS settings update
            $validation = validateInput($_POST, [
                'sms_api_key' => ['required', 'max:255'],
                'sms_username' => ['required', 'max:100'],
                'sms_sender_id' => ['required', 'max:11'],
                'sms_cost_per_message' => ['required', 'numeric'],
                'sms_daily_limit' => ['required', 'numeric'],
                'sms_batch_size' => ['required', 'numeric']
            ]);
            
            if (!$validation['valid']) {
                setFlashMessage('error', implode(', ', $validation['errors']));
            } else {
                $smsSettings = [
                    'sms_api_key' => $validation['data']['sms_api_key'],
                    'sms_username' => $validation['data']['sms_username'],
                    'sms_sender_id' => $validation['data']['sms_sender_id'],
                    'sms_cost_per_message' => $validation['data']['sms_cost_per_message'],
                    'sms_daily_limit' => $validation['data']['sms_daily_limit'],
                    'sms_batch_size' => $validation['data']['sms_batch_size']
                ];
                
                $updatedCount = 0;
                foreach ($smsSettings as $key => $value) {
                    $existingSetting = getRecord('system_settings', 'setting_key', $key);
                    
                    if ($existingSetting) {
                        $updated = updateRecord('system_settings', ['setting_value' => $value], ['setting_key' => $key]);
                    } else {
                        $updated = insertRecord('system_settings', [
                            'setting_key' => $key,
                            'setting_value' => $value,
                            'description' => ucfirst(str_replace('_', ' ', $key)),
                            'data_type' => is_numeric($value) ? 'numeric' : 'string'
                        ]);
                    }
                    
                    if ($updated) $updatedCount++;
                }
                
                if ($updatedCount > 0) {
                    logActivity('SMS settings updated', 'system_settings', null);
                    setFlashMessage('success', 'SMS settings updated successfully');
                } else {
                    setFlashMessage('error', 'Failed to update SMS settings');
                }
            }
            
        } elseif ($action === 'test_sms') {
            // Test SMS functionality
            $testPhone = $_POST['test_phone'] ?? '';
            
            if (empty($testPhone)) {
                setFlashMessage('error', 'Please provide a phone number for testing');
            } else {
                // Here you would implement actual SMS testing
                // For now, we'll simulate the test
                if (validatePhoneNumber($testPhone)) {
                    logActivity('SMS test performed', null, null, null, ['phone' => $testPhone]);
                    setFlashMessage('success', 'SMS test message sent to ' . $testPhone . ' (simulated)');
                } else {
                    setFlashMessage('error', 'Invalid phone number format');
                }
            }
            
        } elseif ($action === 'update_email_settings') {
            // Handle email settings update
            $validation = validateInput($_POST, [
                'mail_host' => ['required', 'max:255'],
                'mail_port' => ['required', 'numeric'],
                'mail_username' => ['required', 'email'],
                'mail_password' => ['required', 'max:255'],
                'mail_from_name' => ['required', 'max:100'],
                'mail_from_email' => ['required', 'email']
            ]);
            
            if (!$validation['valid']) {
                setFlashMessage('error', implode(', ', $validation['errors']));
            } else {
                $emailSettings = [
                    'mail_host' => $validation['data']['mail_host'],
                    'mail_port' => $validation['data']['mail_port'],
                    'mail_username' => $validation['data']['mail_username'],
                    'mail_password' => $validation['data']['mail_password'],
                    'mail_from_name' => $validation['data']['mail_from_name'],
                    'mail_from_email' => $validation['data']['mail_from_email']
                ];
                
                $updatedCount = 0;
                foreach ($emailSettings as $key => $value) {
                    $existingSetting = getRecord('system_settings', 'setting_key', $key);
                    
                    if ($existingSetting) {
                        $updated = updateRecord('system_settings', ['setting_value' => $value], ['setting_key' => $key]);
                    } else {
                        $updated = insertRecord('system_settings', [
                            'setting_key' => $key,
                            'setting_value' => $value,
                            'description' => ucfirst(str_replace('_', ' ', $key)),
                            'data_type' => 'string'
                        ]);
                    }
                    
                    if ($updated) $updatedCount++;
                }
                
                if ($updatedCount > 0) {
                    logActivity('Email settings updated', 'system_settings', null);
                    setFlashMessage('success', 'Email settings updated successfully');
                } else {
                    setFlashMessage('error', 'Failed to update email settings');
                }
            }
            
        } elseif ($action === 'test_email') {
            // Test email functionality
            $testEmail = $_POST['test_email'] ?? '';
            
            if (empty($testEmail)) {
                setFlashMessage('error', 'Please provide an email address for testing');
            } elseif (!validateEmail($testEmail)) {
                setFlashMessage('error', 'Please provide a valid email address');
            } else {
                // Here you would implement actual email testing
                // For now, we'll simulate the test
                logActivity('Email test performed', null, null, null, ['email' => $testEmail]);
                setFlashMessage('success', 'Test email sent to ' . $testEmail . ' (simulated)');
            }
        }
        
    } catch (Exception $e) {
        error_log("Settings error: " . $e->getMessage());
        setFlashMessage('error', 'An error occurred while updating settings');
    }
    
    // Redirect to prevent form resubmission
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Load current settings
try {
    $db = Database::getInstance();
    
    // Get church information
    $churchInfo = getRecord('church_info', 'id', 1) ?: [
        'church_name' => 'Deliverance Church',
        'address' => '',
        'phone' => '',
        'email' => '',
        'website' => '',
        'logo' => '',
        'mission_statement' => '',
        'vision_statement' => '',
        'values' => '',
        'yearly_theme' => '',
        'pastor_name' => '',
        'founded_date' => null,
        'service_times' => '',
        'currency' => 'KES'
    ];
    
    // Get system settings
    $systemSettingsResult = $db->executeQuery("SELECT setting_key, setting_value FROM system_settings")->fetchAll();
    $systemSettings = [];
    foreach ($systemSettingsResult as $setting) {
        $systemSettings[$setting['setting_key']] = $setting['setting_value'];
    }
    
    // Set default values for missing settings
    $defaultSettings = [
        'site_name' => 'Deliverance Church CMS',
        'default_currency' => 'KES',
        'timezone' => 'Africa/Nairobi',
        'date_format' => 'Y-m-d',
        'session_timeout' => '3600',
        'max_login_attempts' => '3',
        'account_lockout_duration' => '1800',
        'backup_frequency' => '7',
        'sms_sender_id' => 'CHURCH',
        'sms_api_key' => '',
        'sms_username' => '',
        'sms_cost_per_message' => '1.00',
        'sms_daily_limit' => '1000',
        'sms_batch_size' => '100',
        'mail_host' => 'smtp.gmail.com',
        'mail_port' => '587',
        'mail_username' => '',
        'mail_password' => '',
        'mail_from_name' => 'Deliverance Church',
        'mail_from_email' => 'noreply@deliverancechurch.org'
    ];
    
    foreach ($defaultSettings as $key => $value) {
        if (!isset($systemSettings[$key])) {
            $systemSettings[$key] = $value;
        }
    }
    
} catch (Exception $e) {
    error_log("Error loading settings: " . $e->getMessage());
    setFlashMessage('error', 'Error loading settings');
    $churchInfo = [];
    $systemSettings = [];
}

// Include header
include_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h1><?php echo $page_title; ?></h1>
            <p class="text-muted">Configure system settings and preferences</p>
        </div>
        <div class="col-md-4 text-end">
            <div class="btn-group">
                <button type="button" class="btn btn-outline-info" onclick="exportSettings()">
                    <i class="fas fa-download me-1"></i>Export Settings
                </button>
                <button type="button" class="btn btn-outline-warning" onclick="importSettings()">
                    <i class="fas fa-upload me-1"></i>Import Settings
                </button>
                <button type="button" class="btn btn-outline-secondary" onclick="resetToDefaults()">
                    <i class="fas fa-undo me-1"></i>Reset to Defaults
                </button>
            </div>
        </div>
    </div>

    <!-- Settings Navigation Tabs -->
    <div class="card">
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs" id="settingsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="church-tab" data-bs-toggle="tab" data-bs-target="#church-info" type="button">
                        <i class="fas fa-church me-2"></i>Church Information
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="system-tab" data-bs-toggle="tab" data-bs-target="#system-settings" type="button">
                        <i class="fas fa-cogs me-2"></i>System Settings
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="sms-tab" data-bs-toggle="tab" data-bs-target="#sms-settings" type="button">
                        <i class="fas fa-sms me-2"></i>SMS Configuration
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="email-tab" data-bs-toggle="tab" data-bs-target="#email-settings" type="button">
                        <i class="fas fa-envelope me-2"></i>Email Configuration
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="backup-tab" data-bs-toggle="tab" data-bs-target="#backup-settings" type="button">
                        <i class="fas fa-database me-2"></i>Backup Settings
                    </button>
                </li>
            </ul>
        </div>

        <div class="card-body">
            <div class="tab-content" id="settingsTabContent">
                <!-- Church Information Tab -->
                <div class="tab-pane fade show active" id="church-info" role="tabpanel">
                    <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <input type="hidden" name="action" value="update_church_info">
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="church_name" class="form-label">Church Name *</label>
                                            <input type="text" class="form-control" id="church_name" name="church_name" 
                                                   value="<?php echo htmlspecialchars($churchInfo['church_name']); ?>" required>
                                            <div class="invalid-feedback">Please provide church name.</div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="pastor_name" class="form-label">Pastor Name</label>
                                            <input type="text" class="form-control" id="pastor_name" name="pastor_name" 
                                                   value="<?php echo htmlspecialchars($churchInfo['pastor_name']); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="address" class="form-label">Church Address</label>
                                    <textarea class="form-control" id="address" name="address" rows="2"><?php echo htmlspecialchars($churchInfo['address']); ?></textarea>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="phone" class="form-label">Phone</label>
                                            <input type="text" class="form-control" id="phone" name="phone" 
                                                   value="<?php echo htmlspecialchars($churchInfo['phone']); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="email" class="form-label">Email</label>
                                            <input type="email" class="form-control" id="email" name="email" 
                                                   value="<?php echo htmlspecialchars($churchInfo['email']); ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="website" class="form-label">Website</label>
                                            <input type="url" class="form-control" id="website" name="website" 
                                                   value="<?php echo htmlspecialchars($churchInfo['website']); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="founded_date" class="form-label">Founded Date</label>
                                            <input type="date" class="form-control" id="founded_date" name="founded_date" 
                                                   value="<?php echo htmlspecialchars($churchInfo['founded_date']); ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="service_times" class="form-label">Service Times</label>
                                    <input type="text" class="form-control" id="service_times" name="service_times" 
                                           value="<?php echo htmlspecialchars($churchInfo['service_times']); ?>">
                                    <div class="form-text">E.g., Sundays 9 AM & 11 AM, Wednesdays 7 PM</div>
                                </div>
                                <div class="mb-3">
                                    <label for="mission_statement" class="form-label">Mission Statement</label>
                                    <textarea class="form-control" id="mission_statement" name="mission_statement" rows="2"><?php echo htmlspecialchars($churchInfo['mission_statement']); ?></textarea>
                                </div>
                                <div class="mb-3">
                                    <label for="vision_statement" class="form-label">Vision Statement</label>
                                    <textarea class="form-control" id="vision_statement" name="vision_statement" rows="2"><?php echo htmlspecialchars($churchInfo['vision_statement']); ?></textarea>
                                </div>
                                <div class="mb-3">
                                    <label for="values" class="form-label">Core Values</label>
                                    <textarea class="form-control" id="values" name="values" rows="2"><?php echo htmlspecialchars($churchInfo['values']); ?></textarea>
                                </div>
                                <div class="mb-3">
                                    <label for="yearly_theme" class="form-label">Yearly Theme</label>
                                    <input type="text" class="form-control" id="yearly_theme" name="yearly_theme" 
                                           value="<?php echo htmlspecialchars($churchInfo['yearly_theme']); ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="currency" class="form-label">Default Currency</label>
                                    <input type="text" class="form-control" id="currency" name="currency" 
                                           value="<?php echo htmlspecialchars($churchInfo['currency']); ?>">
                                    <div class="form-text">E.g., KES, USD, EUR</div>
                                </div>
                            </div>
                            <div class="col-md-4 text-center">
                                <label for="logo" class="form-label">Church Logo</label>
                                <div class="mb-3">
                                    <?php if (!empty($churchInfo['logo']) && file_exists(ASSETS_PATH . 'images/' . basename($churchInfo['logo']))): ?>
                                        <img src="<?php echo BASE_URL . $churchInfo['logo']; ?>" alt="Church Logo" class="img-fluid mb-2" style="max-height: 150px;">
                                    <?php else: ?>
                                        <img src="<?php echo BASE_URL; ?>assets/images/default_logo.png" alt="Default Logo" class="img-fluid mb-2" style="max-height: 150px;">
                                    <?php endif; ?>
                                    <input class="form-control" type="file" id="logo" name="logo" accept="image/*">
                                    <div class="form-text">Max size: 2MB. Allowed types: JPG, PNG, GIF.</div>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </form>
                </div>
                <!-- System Settings Tab -->
                <div class="tab-pane fade" id="system-settings" role="tabpanel">
                    <form method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="action" value="update_system_settings">
                        
                        <div class="mb-3">
                            <label for="site_name" class="form-label">Site Name</label>
                            <input type="text" class="form-control" id="site_name" name="settings[site_name]" 
                                   value="<?php echo htmlspecialchars($systemSettings['site_name']); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="default_currency" class="form-label">Default Currency</label>
                            <input type="text" class="form-control" id="default_currency" name="settings[default_currency]" 
                                   value="<?php echo htmlspecialchars($systemSettings['default_currency']); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="timezone" class="form-label">Timezone</label>
                            <input type="text" class="form-control" id="timezone" name="settings[timezone]" 
                                   value="<?php echo htmlspecialchars($systemSettings['timezone']); ?>">
                            <div class="form-text">E.g., Africa/Nairobi, America/New_York</div>
                        </div>
                        <div class="mb-3">
                            <label for="date_format" class="form-label">Date Format</label>
                            <input type="text" class="form-control" id="date_format" name="settings[date_format]" 
                                   value="<?php echo htmlspecialchars($systemSettings['date_format']); ?>">
                            <div class="form-text">PHP date format, e.g., Y-m-d, d/m/Y</div>
                        </div>
                        <div class="mb-3">
                            <label for="session_timeout" class="form-label">Session Timeout (seconds)</label>
                            <input type="number" class="form-control" id="session_timeout" name="settings[session_timeout]" 
                                   value="<?php echo htmlspecialchars($systemSettings['session_timeout']); ?>" min="300">
                            <div class="form-text">Minimum 300 seconds (5 minutes)</div>
                        </div>
                        <div class="mb-3">
                            <label for="max_login_attempts" class="form-label">Maximum Login Attempts</label>
                            <input type="number" class="form-control" id="max_login_attempts" name="settings[max_login_attempts]" 
                                   value="<?php echo htmlspecialchars($systemSettings['max_login_attempts']); ?>" min="1">
                            <div class="form-text">Number of failed attempts before account lockout</div>
                        </div>
                        <div class="mb-3">
                            <label for="account_lockout_duration" class="form-label">Account Lockout Duration (seconds)</label>
                            <input type="number" class="form-control" id="account_lockout_duration" name="settings[account_lockout_duration]" 
                                   value="<?php echo htmlspecialchars($systemSettings['account_lockout_duration']); ?>" min="300">
                            <div class="form-text">Duration of account lockout after maximum login attempts</div>

                        </div>
                        <div class="mb-3">
                            <label for="backup_frequency" class="form-label">Database Backup Frequency (days)</label>
                            <input type="number" class="form-control" id="backup_frequency" name="settings[backup_frequency]" 
                                   value="<?php echo htmlspecialchars($systemSettings['backup_frequency']); ?>" min="1">
                            <div class="form-text">How often to perform automatic database backups</div>
                        </div>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </form>
                </div>
                <!-- SMS Settings Tab -->
                <div class="tab-pane fade" id="sms-settings" role="tabpanel">
                    <form method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="action" value="update_sms_settings">
                        
                        <div class="mb-3">
                            <label for="sms_api_key" class="form-label">SMS API Key *</label>
                            <input type="text" class="form-control" id="sms_api_key" name="sms_api_key" 
                                   value="<?php echo htmlspecialchars($systemSettings['sms_api_key']); ?>" required>
                            <div class="invalid-feedback">Please provide the SMS API key.</div>
                        </div>
                        <div class="mb-3">
                            <label for="sms_username" class="form-label">SMS Username *</label>
                            <input type="text" class="form-control" id="sms_username" name="sms_username" 
                                   value="<?php echo htmlspecialchars($systemSettings['sms_username']); ?>" required>
                            <div class="invalid-feedback">Please provide the SMS username.</div>    
                        </div>
                        <div class="mb-3">
                            <label for="sms_sender_id" class="form-label">SMS Sender ID *</label>
                            <input type="text" class="form-control" id="sms_sender_id" name="sms_sender_id" 
                                   value="<?php echo htmlspecialchars($systemSettings['sms_sender_id']); ?>" required>
                            <div class="invalid-feedback">Please provide the SMS Sender ID.</div>
                            <div class="form-text">Typically 6-11 alphanumeric characters</div>
                        </div>
                        <div class="mb-3">
                            <label for="sms_cost_per_message" class="form-label">Cost Per Message *</label>
                            <input type="number" step="0.01" class="form-control" id="sms_cost_per_message" name="sms_cost_per_message" 
                                   value="<?php echo htmlspecialchars($systemSettings['sms_cost_per_message']); ?>" required>
                            <div class="invalid-feedback">Please provide the cost per SMS message.</div>
                            <div class="form-text">E.g., 1.00 for $1.00 per message</div>
                        </div>
                        <div class="mb-3">
                            <label for="sms_daily_limit" class="form-label">Daily SMS Limit *</label>
                            <input type="number" class="form-control" id="sms_daily_limit" name="sms_daily_limit" 
                                   value="<?php echo htmlspecialchars($systemSettings['sms_daily_limit']); ?>" required>
                            <div class="invalid-feedback">Please provide the daily SMS limit.</div>
                        </div>  
                        <div class="mb-3">
                            <label for="sms_batch_size" class="form-label">SMS Batch Size *</label>
                            <input type="number" class="form-control" id="sms_batch_size" name="sms_batch_size" 
                                   value="<?php echo htmlspecialchars($systemSettings['sms_batch_size']); ?>" required>
                            <div class="invalid-feedback">Please provide the SMS batch size.</div>
                            <div class="form-text">Number of SMS messages to send in one batch</div>
                        </div>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </form>
                    <hr>
                    <form method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="action" value="test_sms">
                        <div class="mb-3">
                            <label for="test_phone" class="form-label">Test Phone Number</label>
                            <input type="text" class="form-control" id="test_phone" name="test_phone" placeholder="+1234567890" required>
                            <div class="invalid-feedback">Please provide a phone number to test SMS.</div>
                        </div>
                        <button type="submit" class="btn btn-secondary">Send Test SMS</button>
                    </form>
                </div>
                <!-- Email Settings Tab -->
                <div class="tab-pane fade" id="email-settings" role="tabpanel"> 
                    <form method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="action" value="update_email_settings">
                        
                        <div class="mb-3">
                            <label for="mail_host" class="form-label">Mail Host *</label>
                            <input type="text" class="form-control" id="mail_host" name="mail_host" 
                                   value="<?php echo htmlspecialchars($systemSettings['mail_host']); ?>" required>
                            <div class="invalid-feedback">Please provide the mail host.</div>
                        </div>
                        <div class="mb-3">
                            <label for="mail_port" class="form-label">Mail Port *</label>
                            <input type="number" class="form-control" id="mail_port" name="mail_port" 
                                   value="<?php echo htmlspecialchars($systemSettings['mail_port']); ?>" required>
                            <div class="invalid-feedback">Please provide the mail port.</div>
                        </div>
                        <div class="mb-3">
                            <label for="mail_username" class="form-label">Mail Username (Email) *</label>
                            <input type="email" class="form-control" id="mail_username" name="mail_username" 
                                   value="<?php echo htmlspecialchars($systemSettings['mail_username']); ?>" required>
                            <div class="invalid-feedback">Please provide the mail username.</div>
                        </div>
                        <div class="mb-3">
                            <label for="mail_password" class="form-label">Mail Password *</label>
                            <input type="password" class="form-control" id="mail_password" name="mail_password" 
                                   value="<?php echo htmlspecialchars($systemSettings['mail_password']); ?>" required>
                            <div class="invalid-feedback">Please provide the mail password.</div>
                        </div>
                        <div class="mb-3">
                            <label for="mail_from_name" class="form-label">Mail From Name *</label>
                            <input type="text" class="form-control" id="mail_from_name" name="mail_from_name" 
                                   value="<?php echo htmlspecialchars($systemSettings['mail_from_name']); ?>" required>
                            <div class="invalid-feedback">Please provide the mail from name.</div>
                        </div>
                        <div class="mb-3">
                            <label for="mail_from_email" class="form-label">Mail From Email *</label>
                            <input type="email" class="form-control" id="mail_from_email" name="mail_from_email" 
                                   value="<?php echo htmlspecialchars($systemSettings['mail_from_email']); ?>" required>
                            <div class="invalid-feedback">Please provide the mail from email.</div>     
                        </div>
                        <button type="submit" class="btn btn-primary">Save Changes</button> 
                    </form>
                    <hr>    
                    <form method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="action" value="test_email">
                        <div class="mb-3">
                            <label for="test_email" class="form-label">Test Email Address</label>
                            <input type="email" class="form-control" id="test_email" name="test_email" placeholder="Enter email address" required>
                            <div class="invalid-feedback">Please provide a valid email address.</div>
                        </div>
                        <button type="submit" class="btn btn-secondary">Send Test Email</button>    
                    </form>
                </div>
                <!-- Backup Settings Tab -->
                <div class="tab-pane fade" id="backup-settings" role="tabpanel">
                    <form method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="action" value="update_backup_settings">
                        
                        <div class="mb-3">
                            <label for="backup_frequency" class="form-label">Database Backup Frequency (days)</label>
                            <input type="number" class="form-control" id="backup_frequency" name="settings[backup_frequency]" 
                                   value="<?php echo htmlspecialchars($systemSettings['backup_frequency']); ?>" min="1" required>
                            <div class="invalid-feedback">Please provide the backup frequency in days.</div>
                            <div class="form-text">How often to perform automatic database backups</div>
                        </div>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

