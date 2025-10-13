<?php
/**
 * Database Backup & Restore
 * Create and manage database backups
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

requireLogin();
if ($_SESSION['user_role'] !== 'administrator') {
    setFlashMessage('error', 'Only administrators can access backup management');
    redirect(BASE_URL . 'modules/dashboard/');
}

$db = Database::getInstance();

/**
 * Resolve settings safely (fallback to empty array if not available)
 * If your project has a settings helper, you can adapt this.
 */
$settingsData = [];
if (function_exists('getSystemSettings')) {
    $settingsData = getSystemSettings();
} elseif (function_exists('getSettings')) {
    $settingsData = getSettings();
}
if (!is_array($settingsData)) $settingsData = [];

/**
 * Get DB connection details from config if available.
 * Adjust these constant names to your app’s config scheme.
 */
$dbHost = defined('DB_HOST') ? DB_HOST : 'localhost';
$dbUser = defined('DB_USER') ? DB_USER : 'root';
$dbPass = defined('DB_PASS') ? DB_PASS : '';
$dbName = defined('DB_NAME') ? DB_NAME : 'church_cms';

/**
 * Helper: build safe shell commands
 */
function safe_exec($cmd, &$output = null, &$returnVar = null)
{
    $output = [];
    $returnVar = 1;
    exec($cmd, $output, $returnVar);
    return $returnVar === 0;
}

/**
 * Ensure BACKUP_PATH is defined in your config, e.g.:
 * define('BACKUP_PATH', __DIR__ . '/../../backup/');
 */
if (!defined('BACKUP_PATH')) {
    define('BACKUP_PATH', realpath(__DIR__ . '/../../') . '/backup/');
}

// Handle AJAX requests
if (isAjaxRequest()) {
    $action = $_GET['action'] ?? '';

    if ($action === 'create_backup') {
        try {
            $backupFile = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
            $backupPath = rtrim(BACKUP_PATH, '/\\') . DIRECTORY_SEPARATOR . $backupFile;

            // Ensure backup directory exists
            if (!is_dir(BACKUP_PATH)) {
                if (!mkdir(BACKUP_PATH, 0755, true) && !is_dir(BACKUP_PATH)) {
                    throw new RuntimeException('Failed to create backup directory');
                }
            }

            // Build mysqldump command safely
            $cmd = sprintf(
                'mysqldump --user=%s --password=%s --host=%s --single-transaction --routines --triggers %s > %s 2>&1',
                escapeshellarg($dbUser),
                escapeshellarg($dbPass),
                escapeshellarg($dbHost),
                escapeshellarg($dbName),
                escapeshellarg($backupPath)
            );

            $output = [];
            $returnVar = 1;
            exec($cmd, $output, $returnVar);

            if ($returnVar === 0 && file_exists($backupPath) && filesize($backupPath) > 0) {
                @chmod($backupPath, 0640);
                logActivity('Created database backup: ' . $backupFile);
                sendJSONResponse([
                    'success'  => true,
                    'message'  => 'Backup created successfully',
                    'filename' => $backupFile,
                    'size'     => formatFileSize(filesize($backupPath))
                ]);
            } else {
                error_log('mysqldump failed: ' . implode("\n", $output));
                // Clean up empty file if created
                if (file_exists($backupPath) && filesize($backupPath) === 0) {
                    @unlink($backupPath);
                }
                sendJSONResponse(['success' => false, 'message' => 'Failed to create backup']);
            }
        } catch (Exception $e) {
            error_log('Backup error: ' . $e->getMessage());
            sendJSONResponse(['success' => false, 'message' => 'Backup failed: ' . $e->getMessage()]);
        }
    }

    if ($action === 'delete_backup') {
        $filename = $_POST['filename'] ?? '';

        // Strictly enforce .sql files and path confinement
        $basename = basename($filename);
        if (!preg_match('/^[A-Za-z0-9._-]+\.sql$/', $basename)) {
            sendJSONResponse(['success' => false, 'message' => 'Invalid filename']);
            exit;
        }

        $filepath = realpath(rtrim(BACKUP_PATH, '/\\') . DIRECTORY_SEPARATOR . $basename);
        $backupDir = realpath(BACKUP_PATH);

        // Ensure file is inside backup directory
        if (!$filepath || strpos($filepath, $backupDir) !== 0) {
            sendJSONResponse(['success' => false, 'message' => 'Invalid path']);
            exit;
        }

        if (file_exists($filepath) && is_file($filepath) && @unlink($filepath)) {
            logActivity('Deleted backup: ' . $basename);
            sendJSONResponse(['success' => true, 'message' => 'Backup deleted successfully']);
        } else {
            sendJSONResponse(['success' => false, 'message' => 'Failed to delete backup']);
        }
    }
    exit;
}

// Handle restore (non-AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isAjaxRequest()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'restore_backup') {
        $filename = $_POST['filename'] ?? '';
        $basename = basename($filename);

        if (!preg_match('/^[A-Za-z0-9._-]+\.sql$/', $basename)) {
            setFlashMessage('error', 'Invalid backup file');
            redirect($_SERVER['PHP_SELF']);
        }

        $filepath = realpath(rtrim(BACKUP_PATH, '/\\') . DIRECTORY_SEPARATOR . $basename);
        $backupDir = realpath(BACKUP_PATH);

        if (!$filepath || !file_exists($filepath) || strpos($filepath, $backupDir) !== 0) {
            setFlashMessage('error', 'Backup file not found');
            redirect($_SERVER['PHP_SELF']);
        }

        try {
            // Build mysql restore command safely
            $cmd = sprintf(
                'mysql --user=%s --password=%s --host=%s %s < %s 2>&1',
                escapeshellarg($dbUser),
                escapeshellarg($dbPass),
                escapeshellarg($dbHost),
                escapeshellarg($dbName),
                escapeshellarg($filepath)
            );

            $output = [];
            $returnVar = 1;
            exec($cmd, $output, $returnVar);

            if ($returnVar === 0) {
                logActivity('Restored database from backup: ' . $basename);
                setFlashMessage('success', 'Database restored successfully');
            } else {
                error_log('mysql restore failed: ' . implode("\n", $output));
                setFlashMessage('error', 'Failed to restore database');
            }
        } catch (Exception $e) {
            setFlashMessage('error', 'Restore failed: ' . $e->getMessage());
        }

        redirect($_SERVER['PHP_SELF']);
    }
}

// Get list of backups
$backups = [];
$totalBackupBytes = 0;

if (is_dir(BACKUP_PATH)) {
    $files = glob(rtrim(BACKUP_PATH, '/\\') . DIRECTORY_SEPARATOR . '*.sql') ?: [];
    foreach ($files as $file) {
        $size = filesize($file);
        $backups[] = [
            'filename' => basename($file),
            'size'     => $size,
            'date'     => filemtime($file)
        ];
        $totalBackupBytes += $size;
    }
    // Sort by date, newest first
    usort($backups, fn($a, $b) => $b['date'] <=> $a['date']);
}

// Get database stats (if available from your Database class)
$stats = [];
if (method_exists($db, 'getDatabaseStats')) {
    $stats = $db->getDatabaseStats();
}

// Fallbacks for stats display
$tableCount = isset($stats['table_count']) ? (int)$stats['table_count'] : 0;
$dbSizeMb   = isset($stats['size_mb']) ? (float)$stats['size_mb'] : 0.0;

$page_title = 'Backup & Restore';
$page_icon = 'fas fa-database';
$breadcrumb = [
    ['title' => 'Administration', 'url' => BASE_URL . 'modules/admin/'],
    ['title' => 'Backup']
];

include '../../includes/header.php';
?>

<!-- Action Bar -->
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <p class="text-muted mb-0">Create and manage database backups</p>
            </div>
            <button class="btn btn-church-primary" onclick="createBackup()">
                <i class="fas fa-plus me-2"></i>Create Backup Now
            </button>
        </div>
    </div>
</div>

<!-- Database Statistics -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="stats-icon bg-success bg-opacity-10 text-success">
                            <i class="fas fa-hdd"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="stats-number"><?php echo count($backups); ?></div>
                        <div class="stats-label">Total Backups</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="stats-icon bg-info bg-opacity-10 text-info">
                            <i class="fas fa-table"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="stats-number"><?php echo $tableCount; ?></div>
                        <div class="stats-label">Database Tables</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Completed Database Size card -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="stats-icon bg-primary bg-opacity-10 text-primary">
                            <i class="fas fa-database"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="stats-number"><?php echo number_format((float)$dbSizeMb, 2); ?> MB</div>
                        <div class="stats-label">Database Size</div>
                    </div>
                </div>
                <?php if ($totalBackupBytes > 0): ?>
                    <div class="mt-3 small text-muted">
                        <i class="fas fa-archive me-1"></i>
                        Backups occupy <?php echo htmlspecialchars(formatFileSize($totalBackupBytes)); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Backup Instructions -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h5 class="text-church-blue mb-3">
                    <i class="fas fa-info-circle me-2"></i>Important Information
                </h5>
                <div class="row">
                    <div class="col-md-6">
                        <h6>Backup Guidelines:</h6>
                        <ul class="mb-0">
                            <li>Create backups regularly (recommended: daily or weekly)</li>
                            <li>Store backups in a secure location</li>
                            <li>Test restore process periodically</li>
                            <li>Keep multiple backup versions</li>
                            <li>Download backups to external storage</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Restore Guidelines:</h6>
                        <ul class="mb-0">
                            <li>Create a backup before restoring</li>
                            <li>Ensure all users are logged out</li>
                            <li>Restoration will overwrite current data</li>
                            <li>Process may take several minutes</li>
                            <li>Contact support if issues occur</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Backups List -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 text-church-blue">
            <i class="fas fa-list me-2"></i>Available Backups
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($backups)): ?>
            <div class="text-center py-5">
                <i class="fas fa-database fa-4x text-muted mb-3"></i>
                <h5 class="text-muted">No Backups Found</h5>
                <p class="text-muted">Click "Create Backup Now" to create your first backup</p>
                <button class="btn btn-church-primary" onclick="createBackup()">
                    <i class="fas fa-plus me-2"></i>Create First Backup
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="backupsTable">
                    <thead>
                        <tr>
                            <th>Backup File</th>
                            <th>Created Date</th>
                            <th>Size</th>
                            <th>Age</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($backups as $backup): ?>
                        <tr>
                            <td>
                                <i class="fas fa-file-archive text-primary me-2"></i>
                                <strong><?php echo htmlspecialchars($backup['filename']); ?></strong>
                            </td>
                            <td><?php echo date('M d, Y H:i', $backup['date']); ?></td>
                            <td><?php echo formatFileSize($backup['size']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo (time() - $backup['date']) > (7 * 86400) ? 'warning' : 'success'; ?>">
                                    <?php echo timeAgo(date('Y-m-d H:i:s', $backup['date'])); ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="<?php echo BASE_URL . 'backup/' . rawurlencode($backup['filename']); ?>"
                                       class="btn btn-outline-primary"
                                       download
                                       title="Download">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                    <button class="btn btn-outline-success"
                                            onclick="restoreBackup('<?php echo htmlspecialchars($backup['filename'], ENT_QUOTES); ?>')"
                                            title="Restore">
                                        <i class="fas fa-undo"></i> Restore
                                    </button>
                                    <button class="btn btn-outline-danger"
                                            onclick="deleteBackup('<?php echo htmlspecialchars($backup['filename'], ENT_QUOTES); ?>')"
                                            title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Automatic Backup Schedule -->
<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white">
        <h5 class="mb-0 text-church-blue">
            <i class="fas fa-calendar-check me-2"></i>Automatic Backup Schedule
        </h5>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            Automatic backups are scheduled to run every <strong><?php echo (int)($settingsData['backup_frequency'] ?? 7); ?> days</strong>.
            You can change this in <a href="settings.php">System Settings</a>.
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="d-flex align-items-center">
                    <i class="fas fa-clock text-primary fa-2x me-3"></i>
                    <div>
                        <strong>Last Automatic Backup:</strong><br>
                        <small class="text-muted">
                            <?php
                            if (!empty($backups)) {
                                echo date('M d, Y H:i', $backups[0]['date']);
                            } else {
                                echo 'No backups yet';
                            }
                            ?>
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mt-3 mt-md-0">
                <div class="d-flex align-items-center">
                    <i class="fas fa-calendar-day text-success fa-2x me-3"></i>
                    <div>
                        <strong>Next Scheduled Backup:</strong><br>
                        <small class="text-muted">
                            <?php
                            if (!empty($backups)) {
                                $freqDays = (int)($settingsData['backup_frequency'] ?? 7);
                                $nextBackup = $backups[0]['date'] + ($freqDays * 86400);
                                echo date('M d, Y', $nextBackup);
                            } else {
                                echo 'Not scheduled';
                            }
                            ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    if ($('#backupsTable').length && $.fn.DataTable) {
        $('#backupsTable').DataTable({
            order: [[1, 'desc']],
            pageLength: 25
        });
    }
});

function createBackup() {
    ChurchCMS.showConfirm(
        'Create a new database backup? This may take a few moments.',
        function() {
            ChurchCMS.showLoading('Creating backup...');

            $.ajax({
                url: '?action=create_backup',
                method: 'GET',
                dataType: 'json',
                cache: false,
                success: function(response) {
                    ChurchCMS.hideLoading();
                    if (response.success) {
                        ChurchCMS.showToast(`Backup created successfully: ${response.filename} (${response.size})`, 'success');
                        setTimeout(() => location.reload(), 1200);
                    } else {
                        ChurchCMS.showToast(response.message || 'Failed to create backup', 'error');
                    }
                },
                error: function(xhr) {
                    ChurchCMS.hideLoading();
                    ChurchCMS.showToast('Failed to create backup', 'error');
                }
            });
        }
    );
}

function restoreBackup(filename) {
    ChurchCMS.showConfirm(
        `<div class="text-start">
            <h6 class="text-danger"><i class="fas fa-exclamation-triangle me-2"></i>WARNING</h6>
            <p>Restoring from backup will:</p>
            <ul>
                <li>Overwrite all current data</li>
                <li>Cannot be undone</li>
                <li>May take several minutes</li>
            </ul>
            <p><strong>Are you absolutely sure you want to restore from:<br>"${filename}"?</strong></p>
        </div>`,
        function() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="restore_backup">
                <input type="hidden" name="filename" value="${$('<div/>').text(filename).html()}">
            `;
            document.body.appendChild(form);

            ChurchCMS.showLoading('Restoring database... Please wait...');
            form.submit();
        },
        null,
        'Confirm Database Restore'
    );
}

function deleteBackup(filename) {
    ChurchCMS.showConfirm(
        `Delete backup file "${filename}"? This action cannot be undone.`,
        function() {
            $.ajax({
                url: '?action=delete_backup',
                method: 'POST',
                data: { filename: filename },
                dataType: 'json',
                cache: false,
                success: function(response) {
                    if (response.success) {
                        ChurchCMS.showToast(response.message, 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        ChurchCMS.showToast(response.message || 'Failed to delete backup', 'error');
                    }
                },
                error: function() {
                    ChurchCMS.showToast('Failed to delete backup', 'error');
                }
            });
        }
    );
}
</script>

<?php include '../../includes/footer.php'; ?>
