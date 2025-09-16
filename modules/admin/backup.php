<?php
/**
 * Backup & Restore Management
 * Deliverance Church Management System
 * 
 * Create, manage, and restore database backups
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
$page_title = 'Backup & Restore';
$page_icon = 'fas fa-database';
$breadcrumb = [
    ['title' => 'Administration', 'url' => BASE_URL . 'modules/admin/'],
    ['title' => 'Backup & Restore']
];

$additional_css = ['assets/css/admin.css'];
$additional_js = ['assets/js/admin.js'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'create_backup') {
            // Create database backup
            $backupName = $_POST['backup_name'] ?? '';
            $includeStructure = isset($_POST['include_structure']);
            $includeData = isset($_POST['include_data']);
            $compressBackup = isset($_POST['compress_backup']);
            
            if (empty($backupName)) {
                $backupName = 'backup_' . date('Y-m-d_H-i-s');
            } else {
                $backupName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $backupName);
            }
            
            $result = createDatabaseBackup($backupName, $includeStructure, $includeData, $compressBackup);
            
            if ($result['success']) {
                logActivity('Database backup created', null, null, null, ['filename' => $result['filename']]);
                setFlashMessage('success', 'Backup created successfully: ' . $result['filename']);
            } else {
                setFlashMessage('error', 'Failed to create backup: ' . $result['message']);
            }
            
        } elseif ($action === 'restore_backup') {
            $backupFile = $_POST['backup_file'] ?? '';
            
            if (empty($backupFile)) {
                setFlashMessage('error', 'Please select a backup file to restore');
            } else {
                $result = restoreDatabaseBackup($backupFile);
                
                if ($result['success']) {
                    logActivity('Database restored from backup', null, null, null, ['filename' => $backupFile]);
                    setFlashMessage('success', 'Database restored successfully from: ' . $backupFile);
                } else {
                    setFlashMessage('error', 'Failed to restore backup: ' . $result['message']);
                }
            }
            
        } elseif ($action === 'delete_backup') {
            $backupFile = $_POST['backup_file'] ?? '';
            
            if (empty($backupFile)) {
                setFlashMessage('error', 'Please select a backup file to delete');
            } else {
                $backupPath = BACKUP_PATH . $backupFile;
                
                if (file_exists($backupPath)) {
                    if (unlink($backupPath)) {
                        logActivity('Backup file deleted', null, null, null, ['filename' => $backupFile]);
                        setFlashMessage('success', 'Backup file deleted: ' . $backupFile);
                    } else {
                        setFlashMessage('error', 'Failed to delete backup file');
                    }
                } else {
                    setFlashMessage('error', 'Backup file not found');
                }
            }
            
        } elseif ($action === 'upload_backup') {
            if (isset($_FILES['backup_upload']) && $_FILES['backup_upload']['error'] === UPLOAD_ERR_OK) {
                $uploadResult = handleFileUpload(
                    $_FILES['backup_upload'], 
                    BACKUP_PATH, 
                    ['sql', 'gz'], 
                    50 * 1024 * 1024 // 50MB max
                );
                
                if ($uploadResult['success']) {
                    logActivity('Backup file uploaded', null, null, null, ['filename' => $uploadResult['filename']]);
                    setFlashMessage('success', 'Backup file uploaded: ' . $uploadResult['filename']);
                } else {
                    setFlashMessage('error', 'Failed to upload backup: ' . $uploadResult['message']);
                }
            } else {
                setFlashMessage('error', 'Please select a valid backup file to upload');
            }
        }
        
    } catch (Exception $e) {
        error_log("Backup management error: " . $e->getMessage());
        setFlashMessage('error', 'An error occurred: ' . $e->getMessage());
    }
    
    // Redirect to prevent form resubmission
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Get available backup files
$backupFiles = [];
if (is_dir(BACKUP_PATH)) {
    $files = glob(BACKUP_PATH . '*.{sql,gz}', GLOB_BRACE);
    foreach ($files as $file) {
        $filename = basename($file);
        $backupFiles[] = [
            'filename' => $filename,
            'size' => filesize($file),
            'created' => filemtime($file),
            'type' => pathinfo($file, PATHINFO_EXTENSION)
        ];
    }
    
    // Sort by creation date (newest first)
    usort($backupFiles, function($a, $b) {
        return $b['created'] - $a['created'];
    });
}

// Get database information
try {
    $db = Database::getInstance();
    
    // Get database size
    $dbStats = $db->executeQuery("
        SELECT 
            ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb,
            COUNT(*) as table_count
        FROM information_schema.tables 
        WHERE table_schema = DATABASE()
    ")->fetch();
    
    // Get table information
    $tables = $db->executeQuery("
        SELECT 
            table_name,
            ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb,
            table_rows
        FROM information_schema.TABLES 
        WHERE table_schema = DATABASE()
        ORDER BY (data_length + index_length) DESC
    ")->fetchAll();
    
} catch (Exception $e) {
    error_log("Error getting database info: " . $e->getMessage());
    $dbStats = ['size_mb' => 0, 'table_count' => 0];
    $tables = [];
}

/**
 * Create database backup
 */
function createDatabaseBackup($name, $includeStructure = true, $includeData = true, $compress = false) {
    try {
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        
        $filename = $name . '.sql';
        $filepath = BACKUP_PATH . $filename;
        
        // Ensure backup directory exists
        if (!is_dir(BACKUP_PATH)) {
            mkdir(BACKUP_PATH, 0755, true);
        }
        
        $output = "-- Church Management System Database Backup\n";
        $output .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
        $output .= "-- Database: " . $pdo->query('SELECT DATABASE()')->fetchColumn() . "\n\n";
        $output .= "SET FOREIGN_KEY_CHECKS=0;\n";
        $output .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n\n";
        
        // Get all tables
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tables as $table) {
            $output .= "-- Table structure for table `{$table}`\n";
            
            if ($includeStructure) {
                $output .= "DROP TABLE IF EXISTS `{$table}`;\n";
                $createTable = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch();
                $output .= $createTable['Create Table'] . ";\n\n";
            }
            
            if ($includeData) {
                $output .= "-- Dumping data for table `{$table}`\n";
                
                $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($rows)) {
                    $columns = array_keys($rows[0]);
                    $columnsList = '`' . implode('`, `', $columns) . '`';
                    
                    foreach ($rows as $row) {
                        $values = [];
                        foreach ($row as $value) {
                            if ($value === null) {
                                $values[] = 'NULL';
                            } else {
                                $values[] = "'" . addslashes($value) . "'";
                            }
                        }
                        $output .= "INSERT INTO `{$table}` ({$columnsList}) VALUES (" . implode(', ', $values) . ");\n";
                    }
                }
                $output .= "\n";
            }
        }
        
        $output .= "SET FOREIGN_KEY_CHECKS=1;\n";
        
        // Write to file
        file_put_contents($filepath, $output);
        
        // Compress if requested
        if ($compress && function_exists('gzencode')) {
            $compressedContent = gzencode($output);
            $compressedFilename = $name . '.sql.gz';
            $compressedFilepath = BACKUP_PATH . $compressedFilename;
            
            file_put_contents($compressedFilepath, $compressedContent);
            unlink($filepath); // Remove uncompressed version
            
            $filename = $compressedFilename;
        }
        
        return [
            'success' => true,
            'filename' => $filename,
            'size' => filesize(BACKUP_PATH . $filename)
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

// Include header
include_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h1><?php echo $page_title; ?></h1>
            <p class="text-muted">Create, manage, and restore database backups</p>
        </div>
        <div class="col-md-4 text-end">
            <button type="button" class="btn btn-church-primary" data-bs-toggle="modal" data-bs-target="#createBackupModal">
                <i class="fas fa-plus me-2"></i>Create Backup
            </button>
        </div>
    </div>

    <!-- Database Information -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="stats-card">
                <div class="d-flex align-items-center">
                    <div class="stats-icon bg-gradient-info">
                        <i class="fas fa-database"></i>
                    </div>
                    <div class="ms-3">
                        <div class="stats-number"><?php echo $dbStats['size_mb']; ?> MB</div>
                        <div class="stats-label">Database Size</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="stats-card">
                <div class="d-flex align-items-center">
                    <div class="stats-icon bg-gradient-primary">
                        <i class="fas fa-table"></i>
                    </div>
                    <div class="ms-3">
                        <div class="stats-number"><?php echo $dbStats['table_count']; ?></div>
                        <div class="stats-label">Tables</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="stats-card">
                <div class="d-flex align-items-center">
                    <div class="stats-icon bg-gradient-success">
                        <i class="fas fa-archive"></i>
                    </div>
                    <div class="ms-3">
                        <div class="stats-number"><?php echo count($backupFiles); ?></div>
                        <div class="stats-label">Backup Files</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="stats-card">
                <div class="d-flex align-items-center">
                    <div class="stats-icon bg-gradient-warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="ms-3">
                        <div class="stats-number">
                            <?php 
                            if (!empty($backupFiles)) {
                                echo timeAgo(date('Y-m-d H:i:s', $backupFiles[0]['created']));
                            } else {
                                echo 'Never';
                            }
                            ?>
                        </div>
                        <div class="stats-label">Last Backup</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Backup Files List -->
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-list me-2"></i>Backup Files
                        </h6>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-info" onclick="refreshBackupList()">
                                <i class="fas fa-sync-alt me-1"></i>Refresh
                            </button>
                            <button type="button" class="btn btn-outline-warning" onclick="cleanOldBackups()">
                                <i class="fas fa-broom me-1"></i>Clean Old
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($backupFiles)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Filename</th>
                                    <th>Size</th>
                                    <th>Created</th>
                                    <th>Type</th>
                                    <th class="no-print">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($backupFiles as $backup): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-<?php echo $backup['type'] === 'gz' ? 'file-archive' : 'database'; ?> me-2 text-primary"></i>
                                            <div>
                                                <div class="fw-semibold"><?php echo htmlspecialchars($backup['filename']); ?></div>
                                                <small class="text-muted"><?php echo $backup['type'] === 'gz' ? 'Compressed' : 'SQL File'; ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo formatFileSize($backup['size']); ?></span>
                                    </td>
                                    <td>
                                        <div>
                                            <div><?php echo date('Y-m-d H:i:s', $backup['created']); ?></div>
                                            <small class="text-muted"><?php echo timeAgo(date('Y-m-d H:i:s', $backup['created'])); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($backup['type'] === 'gz'): ?>
                                            <span class="badge bg-info">Compressed</span>
                                        <?php else: ?>
                                            <span class="badge bg-primary">SQL</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="no-print">
                                        <div class="table-actions">
                                            <button type="button" class="btn btn-sm btn-outline-success" 
                                                    onclick="downloadBackup('<?php echo htmlspecialchars($backup['filename']); ?>')">
                                                <i class="fas fa-download"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-warning" 
                                                    onclick="restoreBackup('<?php echo htmlspecialchars($backup['filename']); ?>')">
                                                <i class="fas fa-undo"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-info" 
                                                    onclick="viewBackupInfo('<?php echo htmlspecialchars($backup['filename']); ?>')">
                                                <i class="fas fa-info"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger confirm-delete" 
                                                    onclick="deleteBackup('<?php echo htmlspecialchars($backup['filename']); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-archive fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No Backup Files Found</h5>
                        <p class="text-muted">Create your first backup to get started.</p>
                        <button type="button" class="btn btn-church-primary" data-bs-toggle="modal" data-bs-target="#createBackupModal">
                            <i class="fas fa-plus me-1"></i>Create First Backup
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions & Database Tables -->
        <div class="col-lg-4 mb-4">
            <!-- Quick Actions -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-bolt me-2"></i>Quick Actions
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-church-primary" data-bs-toggle="modal" data-bs-target="#createBackupModal">
                            <i class="fas fa-plus me-2"></i>Create New Backup
                        </button>
                        <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#uploadBackupModal">
                            <i class="fas fa-upload me-2"></i>Upload Backup File
                        </button>
                        <button type="button" class="btn btn-outline-warning" onclick="scheduleAutoBackup()">
                            <i class="fas fa-calendar me-2"></i>Schedule Auto Backup
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="exportBackupList()">
                            <i class="fas fa-file-export me-2"></i>Export Backup List
                        </button>
                    </div>
                </div>
            </div>

            <!-- Database Tables -->
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-table me-2"></i>Database Tables
                    </h6>
                </div>
                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                    <?php if (!empty($tables)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($tables as $table): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <div>
                                <div class="fw-semibold"><?php echo htmlspecialchars($table['table_name']); ?></div>
                                <small class="text-muted">
                                    <?php echo number_format($table['table_rows']); ?> rows
                                </small>
                            </div>
                            <span class="badge bg-secondary"><?php echo $table['size_mb']; ?> MB</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-3">
                        <i class="fas fa-table fa-2x text-muted mb-2"></i>
                        <p class="text-muted mb-0">No table information available</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Backup Modal -->
<div class="modal fade" id="createBackupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="create_backup">
                
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>Create Database Backup
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="backup_name" class="form-label">Backup Name</label>
                        <input type="text" class="form-control" id="backup_name" name="backup_name" 
                               placeholder="backup_<?php echo date('Y-m-d_H-i'); ?>">
                        <div class="form-text">Leave empty for auto-generated name</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Backup Options</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="include_structure" name="include_structure" checked>
                            <label class="form-check-label" for="include_structure">
                                Include table structure (CREATE TABLE statements)
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="include_data" name="include_data" checked>
                            <label class="form-check-label" for="include_data">
                                Include table data (INSERT statements)
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="compress_backup" name="compress_backup">
                            <label class="form-check-label" for="compress_backup">
                                Compress backup file (GZIP)
                            </label>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Estimated backup size:</strong> ~<?php echo $dbStats['size_mb']; ?> MB
                        <br>
                        <small>Compression can reduce size by 60-80%</small>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-church-primary">
                        <i class="fas fa-database me-1"></i>Create Backup
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Upload Backup Modal -->
<div class="modal fade" id="uploadBackupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="upload_backup">
                
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-upload me-2"></i>Upload Backup File
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="backup_upload" class="form-label">Select Backup File</label>
                        <input type="file" class="form-control" id="backup_upload" name="backup_upload" 
                               accept=".sql,.gz" required>
                        <div class="form-text">Supported formats: .sql, .sql.gz (Max size: 50MB)</div>
                        <div class="invalid-feedback">Please select a valid backup file.</div>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Warning:</strong> Only upload backup files from trusted sources. 
                        Malicious SQL files can damage your database.
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-church-primary">
                        <i class="fas fa-upload me-1"></i>Upload Backup
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Restore Confirmation Modal -->
<div class="modal fade" id="restoreModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="restoreForm">
                <input type="hidden" name="action" value="restore_backup">
                <input type="hidden" name="backup_file" id="restore_filename">
                
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>Confirm Database Restore
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <h6 class="alert-heading">
                            <i class="fas fa-skull-crossbones me-2"></i>DANGER: Data Loss Warning
                        </h6>
                        <p class="mb-0">
                            Restoring a backup will <strong>permanently delete</strong> all current data 
                            and replace it with the data from the backup file. This action cannot be undone!
                        </p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Backup file to restore:</label>
                        <div class="form-control-plaintext fw-bold" id="restore_filename_display"></div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="confirm_restore" required>
                            <label class="form-check-label" for="confirm_restore">
                                I understand that this will permanently replace all current data
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="backup_before_restore">
                            <label class="form-check-label" for="backup_before_restore">
                                Create a backup of current data before restoring
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="confirmRestoreBtn" disabled>
                        <i class="fas fa-undo me-1"></i>Restore Database
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Download backup file
function downloadBackup(filename) {
    window.open(`${BASE_URL}api/admin.php?action=download_backup&filename=${encodeURIComponent(filename)}`, '_blank');
}

// Restore backup with confirmation
function restoreBackup(filename) {
    document.getElementById('restore_filename').value = filename;
    document.getElementById('restore_filename_display').textContent = filename;
    document.getElementById('confirm_restore').checked = false;
    document.getElementById('backup_before_restore').checked = true;
    document.getElementById('confirmRestoreBtn').disabled = true;
    
    new bootstrap.Modal(document.getElementById('restoreModal')).show();
}

// Delete backup
function deleteBackup(filename) {
    ChurchCMS.showConfirm(`Delete backup file "${filename}"? This action cannot be undone.`, function() {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_backup">
            <input type="hidden" name="backup_file" value="${filename}">
        `;
        document.body.appendChild(form);
        form.submit();
    });
}

// View backup information
function viewBackupInfo(filename) {
    ChurchCMS.showLoading('Loading backup information...');
    
    fetch(`${BASE_URL}api/admin.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ 
            action: 'get_backup_info', 
            filename: filename 
        })
    })
    .then(response => response.json())
    .then(data => {
        ChurchCMS.hideLoading();
        
        if (data.success) {
            const modalHtml = `
                <div class="modal fade" id="backupInfoModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="fas fa-info-circle me-2"></i>Backup Information
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="fw-bold">File Details</h6>
                                        <table class="table table-sm">
                                            <tr><th>Filename:</th><td>${data.info.filename}</td></tr>
                                            <tr><th>Size:</th><td>${data.info.size}</td></tr>
                                            <tr><th>Created:</th><td>${data.info.created}</td></tr>
                                            <tr><th>Type:</th><td>${data.info.type}</td></tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="fw-bold">Content Summary</h6>
                                        <table class="table table-sm">
                                            <tr><th>Tables:</th><td>${data.info.tables || 'N/A'}</td></tr>
                                            <tr><th>Records:</th><td>${data.info.records || 'N/A'}</td></tr>
                                            <tr><th>Structure:</th><td>${data.info.has_structure ? 'Yes' : 'No'}</td></tr>
                                            <tr><th>Data:</th><td>${data.info.has_data ? 'Yes' : 'No'}</td></tr>
                                        </table>
                                    </div>
                                </div>
                                ${data.info.preview ? `
                                    <div class="mt-3">
                                        <h6 class="fw-bold">Preview</h6>
                                        <pre class="bg-light p-2 border rounded small" style="max-height: 200px; overflow-y: auto;">${data.info.preview}</pre>
                                    </div>
                                ` : ''}
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <button type="button" class="btn btn-success" onclick="downloadBackup('${filename}')">
                                    <i class="fas fa-download me-1"></i>Download
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove existing modal
            const existingModal = document.getElementById('backupInfoModal');
            if (existingModal) existingModal.remove();
            
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            new bootstrap.Modal(document.getElementById('backupInfoModal')).show();
        } else {
            ChurchCMS.showToast(data.message || 'Failed to load backup information', 'error');
        }
    })
    .catch(error => {
        ChurchCMS.hideLoading();
        ChurchCMS.showToast('Error loading backup information', 'error');
    });
}

// Refresh backup list
function refreshBackupList() {
    location.reload();
}

// Clean old backups
function cleanOldBackups() {
    ChurchCMS.showConfirm('Remove backup files older than 30 days?', function() {
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
                ChurchCMS.showToast(`Removed ${data.removed} old backup files`, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                ChurchCMS.showToast(data.message || 'Failed to clean backups', 'error');
            }
        })
        .catch(error => {
            ChurchCMS.hideLoading();
            ChurchCMS.showToast('Error cleaning backups', 'error');
        });
    });
}

// Schedule automatic backup
function scheduleAutoBackup() {
    ChurchCMS.showToast('Automatic backup scheduling feature coming soon!', 'info');
}

// Export backup list
function exportBackupList() {
    const backupData = [];
    const rows = document.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        if (cells.length >= 4) {
            backupData.push({
                filename: cells[0].textContent.trim(),
                size: cells[1].textContent.trim(),
                created: cells[2].textContent.trim(),
                type: cells[3].textContent.trim()
            });
        }
    });
    
    const csv = 'Filename,Size,Created,Type\n' + 
                backupData.map(row => `"${row.filename}","${row.size}","${row.created}","${row.type}"`).join('\n');
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `backup_list_${new Date().toISOString().split('T')[0]}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
    
    ChurchCMS.showToast('Backup list exported successfully', 'success');
}

// Form validation and enhancements
document.addEventListener('DOMContentLoaded', function() {
    // Restore confirmation checkbox
    const confirmCheckbox = document.getElementById('confirm_restore');
    const restoreBtn = document.getElementById('confirmRestoreBtn');
    
    if (confirmCheckbox && restoreBtn) {
        confirmCheckbox.addEventListener('change', function() {
            restoreBtn.disabled = !this.checked;
        });
    }
    
    // File upload validation
    const fileInput = document.getElementById('backup_upload');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const maxSize = 50 * 1024 * 1024; // 50MB
                if (file.size > maxSize) {
                    this.setCustomValidity('File size must not exceed 50MB');
                } else {
                    this.setCustomValidity('');
                }
            }
        });
    }
    
    // Auto-refresh backup list every 30 seconds
    setInterval(function() {
        // Only refresh if no modals are open
        if (!document.querySelector('.modal.show')) {
            const currentCount = document.querySelectorAll('tbody tr').length;
            
            fetch(`${BASE_URL}api/admin.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ action: 'get_backup_count' })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.count !== currentCount) {
                    // Show notification and refresh
                    ChurchCMS.showToast('Backup list updated', 'info');
                    setTimeout(() => location.reload(), 2000);
                }
            })
            .catch(error => {
                // Silently fail for auto-refresh
            });
        }
    }, 30000);
    
    // Progress tracking for backup creation
    let backupInProgress = false;
    
    document.querySelector('#createBackupModal form').addEventListener('submit', function(e) {
        if (backupInProgress) {
            e.preventDefault();
            ChurchCMS.showToast('Backup creation already in progress', 'warning');
            return;
        }
        
        backupInProgress = true;
        ChurchCMS.showLoading('Creating backup... This may take a few minutes.');
        
        // Re-enable after 2 minutes (timeout)
        setTimeout(() => {
            backupInProgress = false;
            ChurchCMS.hideLoading();
        }, 120000);
    });
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + B for backup
        if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
            e.preventDefault();
            document.querySelector('[data-bs-target="#createBackupModal"]').click();
        }
        
        // Ctrl/Cmd + R for refresh (override default)
        if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
            e.preventDefault();
            refreshBackupList();
        }
    });
});
</script>

<?php include_once '../../includes/footer.php'; ?>
        ];
    }
}

/**
 * Restore database backup
 */
function restoreDatabaseBackup($filename) {
    try {
        $filepath = BACKUP_PATH . $filename;
        
        if (!file_exists($filepath)) {
            throw new Exception("Backup file not found: {$filename}");
        }
        
        // Read backup file
        $sql = '';
        if (pathinfo($filename, PATHINFO_EXTENSION) === 'gz') {
            $sql = gzdecode(file_get_contents($filepath));
        } else {
            $sql = file_get_contents($filepath);
        }
        
        if (empty($sql)) {
            throw new Exception("Backup file is empty or corrupted");
        }
        
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        
        // Split SQL into individual statements
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            function($stmt) {
                return !empty($stmt) && !preg_match('/^--/', $stmt);
            }
        );
        
        // Execute statements
        $pdo->beginTransaction();
        
        foreach ($statements as $statement) {
            if (!empty(trim($statement))) {
                $pdo->exec($statement);
            }
        }
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Database restored successfully'
        ];
        
    } catch (Exception $e) {
        if (isset($pdo)) {
            $pdo->rollBack();
        }
        
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

