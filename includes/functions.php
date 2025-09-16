<?php
/**
 * Core System Functions
 * Deliverance Church Management System
 * 
 * Contains all utility functions used throughout the system
 */

// Include configuration files
require_once dirname(__DIR__) . '/config/config.php';

// =====================================================
// AUTHENTICATION FUNCTIONS
// =====================================================

/**
 * Hash password using PHP's password_hash
 * @param string $password
 * @return string
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify password
 * @param string $password
 * @param string $hashedPassword
 * @return bool
 */
function verifyPassword($password, $hashedPassword) {
    return password_verify($password, $hashedPassword);
}

/**
 * Generate secure random token
 * @param int $length
 * @return string
 */
function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Check if password meets requirements
 * @param string $password
 * @return array
 */
function validatePassword($password) {
    $errors = [];
    
    if (strlen($password) < MIN_PASSWORD_LENGTH) {
        $errors[] = "Password must be at least " . MIN_PASSWORD_LENGTH . " characters long";
    }
    
    if (PASSWORD_REQUIRE_UPPERCASE && !preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }
    
    if (PASSWORD_REQUIRE_LOWERCASE && !preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    }
    
    if (PASSWORD_REQUIRE_NUMBER && !preg_match('/\d/', $password)) {
        $errors[] = "Password must contain at least one number";
    }
    
    if (PASSWORD_REQUIRE_SPECIAL && !preg_match('/[^a-zA-Z\d]/', $password)) {
        $errors[] = "Password must contain at least one special character";
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * Generate JWT token
 * @param array $payload
 * @return string
 */
function generateJWT($payload) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload = json_encode($payload);
    
    $headerEncoded = base64UrlEncode($header);
    $payloadEncoded = base64UrlEncode($payload);
    
    $signature = hash_hmac('sha256', $headerEncoded . "." . $payloadEncoded, ENCRYPTION_KEY, true);
    $signatureEncoded = base64UrlEncode($signature);
    
    return $headerEncoded . "." . $payloadEncoded . "." . $signatureEncoded;
}

/**
 * Base64 URL encode
 * @param string $data
 * @return string
 */
function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

// =====================================================
// SESSION MANAGEMENT FUNCTIONS
// =====================================================

/**
 * Initialize user session
 * @param array $userData
 */
function initializeUserSession($userData) {
    $_SESSION['user_id'] = $userData['id'];
    $_SESSION['username'] = $userData['username'];
    $_SESSION['user_role'] = $userData['role'];
    $_SESSION['first_name'] = $userData['first_name'];
    $_SESSION['last_name'] = $userData['last_name'];
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
    
    // Update last login in database
    updateLastLogin($userData['id']);
}

/**
 * Update last login timestamp
 * @param int $userId
 */
function updateLastLogin($userId) {
    try {
        $db = Database::getInstance();
        $db->executeQuery(
            "UPDATE users SET last_login = NOW(), login_attempts = 0, locked_until = NULL WHERE id = ?",
            [$userId]
        );
    } catch (Exception $e) {
        error_log("Failed to update last login: " . $e->getMessage());
    }
}

/**
 * Check if session is valid and not expired
 * @return bool
 */
function isValidSession() {
    if (!isLoggedIn()) {
        return false;
    }
    
    // Check session timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_destroy();
        return false;
    }
    
    // Update last activity
    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * Destroy user session and logout
 */
function destroyUserSession() {
    if (isLoggedIn()) {
        logActivity('User logged out');
    }
    
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

// =====================================================
// DATABASE UTILITY FUNCTIONS
// =====================================================

/**
 * Get single record from database
 * @param string $table
 * @param string $field
 * @param mixed $value
 * @return array|null
 */
function getRecord($table, $field, $value) {
    try {
        $db = Database::getInstance();
        $stmt = $db->executeQuery("SELECT * FROM {$table} WHERE {$field} = ? LIMIT 1", [$value]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error getting record: " . $e->getMessage());
        return null;
    }
}

/**
 * Get multiple records from database
 * @param string $table
 * @param array $conditions
 * @param string $orderBy
 * @param int $limit
 * @return array
 */
function getRecords($table, $conditions = [], $orderBy = 'id DESC', $limit = null) {
    try {
        $db = Database::getInstance();
        
        $sql = "SELECT * FROM {$table}";
        $params = [];
        
        if (!empty($conditions)) {
            $whereClause = [];
            foreach ($conditions as $field => $value) {
                $whereClause[] = "{$field} = ?";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(" AND ", $whereClause);
        }
        
        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }
        
        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }
        
        $stmt = $db->executeQuery($sql, $params);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error getting records: " . $e->getMessage());
        return [];
    }
}

/**
 * Insert record into database
 * @param string $table
 * @param array $data
 * @return int|false
 */
function insertRecord($table, $data) {
    try {
        $db = Database::getInstance();
        
        $fields = array_keys($data);
        $placeholders = array_fill(0, count($fields), '?');
        
        $sql = "INSERT INTO {$table} (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        $stmt = $db->executeQuery($sql, array_values($data));
        
        if ($stmt->rowCount() > 0) {
            return $db->getLastInsertId();
        }
        return false;
    } catch (Exception $e) {
        error_log("Error inserting record: " . $e->getMessage());
        return false;
    }
}

/**
 * Update record in database
 * @param string $table
 * @param array $data
 * @param array $conditions
 * @return bool
 */
function updateRecord($table, $data, $conditions) {
    try {
        $db = Database::getInstance();
        
        $setClause = [];
        $params = [];
        
        foreach ($data as $field => $value) {
            $setClause[] = "{$field} = ?";
            $params[] = $value;
        }
        
        $whereClause = [];
        foreach ($conditions as $field => $value) {
            $whereClause[] = "{$field} = ?";
            $params[] = $value;
        }
        
        $sql = "UPDATE {$table} SET " . implode(', ', $setClause) . " WHERE " . implode(' AND ', $whereClause);
        
        $stmt = $db->executeQuery($sql, $params);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("Error updating record: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete record from database
 * @param string $table
 * @param array $conditions
 * @return bool
 */
function deleteRecord($table, $conditions) {
    try {
        $db = Database::getInstance();
        
        $whereClause = [];
        $params = [];
        
        foreach ($conditions as $field => $value) {
            $whereClause[] = "{$field} = ?";
            $params[] = $value;
        }
        
        $sql = "DELETE FROM {$table} WHERE " . implode(' AND ', $whereClause);
        
        $stmt = $db->executeQuery($sql, $params);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("Error deleting record: " . $e->getMessage());
        return false;
    }
}

/**
 * Get count of records
 * @param string $table
 * @param array $conditions
 * @return int
 */
function getRecordCount($table, $conditions = []) {
    try {
        $db = Database::getInstance();
        
        $sql = "SELECT COUNT(*) as count FROM {$table}";
        $params = [];
        
        if (!empty($conditions)) {
            $whereClause = [];
            foreach ($conditions as $field => $value) {
                $whereClause[] = "{$field} = ?";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(" AND ", $whereClause);
        }
        
        $stmt = $db->executeQuery($sql, $params);
        $result = $stmt->fetch();
        return (int) $result['count'];
    } catch (Exception $e) {
        error_log("Error getting record count: " . $e->getMessage());
        return 0;
    }
}

// =====================================================
// FILE UPLOAD FUNCTIONS
// =====================================================

/**
 * Handle file upload
 * @param array $file $_FILES array element
 * @param string $uploadPath Upload directory path
 * @param array $allowedTypes Allowed file extensions
 * @param int $maxSize Maximum file size in bytes
 * @return array Result array with success/error info
 */
function handleFileUpload($file, $uploadPath, $allowedTypes = [], $maxSize = MAX_FILE_SIZE) {
    $result = [
        'success' => false,
        'message' => '',
        'filename' => '',
        'filepath' => ''
    ];
    
    // Check if file was uploaded
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        $result['message'] = 'No file was uploaded';
        return $result;
    }
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $result['message'] = 'File upload error: ' . $file['error'];
        return $result;
    }
    
    // Check file size
    if ($file['size'] > $maxSize) {
        $result['message'] = 'File size exceeds maximum allowed size of ' . formatFileSize($maxSize);
        return $result;
    }
    
    // Get file extension
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Check allowed file types
    if (!empty($allowedTypes) && !in_array($fileExtension, $allowedTypes)) {
        $result['message'] = 'File type not allowed. Allowed types: ' . implode(', ', $allowedTypes);
        return $result;
    }
    
    // Generate unique filename
    $filename = generateUniqueFilename($file['name']);
    $filepath = $uploadPath . $filename;
    
    // Create upload directory if it doesn't exist
    if (!is_dir($uploadPath)) {
        mkdir($uploadPath, 0755, true);
    }
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        $result['success'] = true;
        $result['message'] = 'File uploaded successfully';
        $result['filename'] = $filename;
        $result['filepath'] = $filepath;
    } else {
        $result['message'] = 'Failed to move uploaded file';
    }
    
    return $result;
}

/**
 * Generate unique filename
 * @param string $originalName
 * @return string
 */
function generateUniqueFilename($originalName) {
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    $name = pathinfo($originalName, PATHINFO_FILENAME);
    $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
    return $name . '_' . time() . '_' . rand(1000, 9999) . '.' . $extension;
}

/**
 * Format file size in human readable format
 * @param int $bytes
 * @return string
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Delete file from filesystem
 * @param string $filepath
 * @return bool
 */
function deleteFile($filepath) {
    if (file_exists($filepath) && is_file($filepath)) {
        return unlink($filepath);
    }
    return false;
}

// =====================================================
// VALIDATION FUNCTIONS
// =====================================================

/**
 * Validate required fields
 * @param array $data
 * @param array $requiredFields
 * @return array
 */
function validateRequiredFields($data, $requiredFields) {
    $errors = [];
    
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * Validate email address
 * @param string $email
 * @return bool
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number (Kenyan format)
 * @param string $phone
 * @return bool
 */
function validatePhoneNumber($phone) {
    return preg_match('/^(\+254|0)[7-9]\d{8}$/', $phone);
}

/**
 * Validate date format
 * @param string $date
 * @param string $format
 * @return bool
 */
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * Sanitize and validate input data
 * @param array $data
 * @param array $rules
 * @return array
 */
function validateInput($data, $rules) {
    $errors = [];
    $cleanData = [];
    
    foreach ($rules as $field => $fieldRules) {
        $value = isset($data[$field]) ? trim($data[$field]) : '';
        
        // Required validation
        if (in_array('required', $fieldRules) && empty($value)) {
            $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
            continue;
        }
        
        // Skip other validations if field is empty and not required
        if (empty($value)) {
            $cleanData[$field] = $value;
            continue;
        }
        
        // Email validation
        if (in_array('email', $fieldRules) && !validateEmail($value)) {
            $errors[$field] = 'Invalid email address';
        }
        
        // Phone validation
        if (in_array('phone', $fieldRules) && !validatePhoneNumber($value)) {
            $errors[$field] = 'Invalid phone number format';
        }
        
        // Date validation
        if (in_array('date', $fieldRules) && !validateDate($value)) {
            $errors[$field] = 'Invalid date format';
        }
        
        // Numeric validation
        if (in_array('numeric', $fieldRules) && !is_numeric($value)) {
            $errors[$field] = 'Must be a valid number';
        }
        
        // Minimum length validation
        foreach ($fieldRules as $rule) {
            if (strpos($rule, 'min:') === 0) {
                $minLength = (int) substr($rule, 4);
                if (strlen($value) < $minLength) {
                    $errors[$field] = 'Must be at least ' . $minLength . ' characters';
                }
            }
        }
        
        // Maximum length validation
        foreach ($fieldRules as $rule) {
            if (strpos($rule, 'max:') === 0) {
                $maxLength = (int) substr($rule, 4);
                if (strlen($value) > $maxLength) {
                    $errors[$field] = 'Must not exceed ' . $maxLength . ' characters';
                }
            }
        }
        
        $cleanData[$field] = sanitizeInput($value);
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'data' => $cleanData
    ];
}

// =====================================================
// NOTIFICATION FUNCTIONS
// =====================================================

/**
 * Set flash message
 * @param string $type success, error, warning, info
 * @param string $message
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Get and clear flash message
 * @return array|null
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

/**
 * Display flash message HTML
 * @return string
 */
function displayFlashMessage() {
    $message = getFlashMessage();
    if (!$message) return '';
    
    $alertClass = [
        'success' => 'alert-success',
        'error' => 'alert-danger',
        'warning' => 'alert-warning',
        'info' => 'alert-info'
    ];
    
    $class = isset($alertClass[$message['type']]) ? $alertClass[$message['type']] : 'alert-info';
    
    return '<div class="alert ' . $class . ' alert-dismissible fade show" role="alert">
                ' . htmlspecialchars($message['message']) . '
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>';
}

// =====================================================
// PAGINATION FUNCTIONS
// =====================================================

/**
 * Generate pagination data
 * @param int $totalRecords
 * @param int $currentPage
 * @param int $recordsPerPage
 * @return array
 */
function generatePagination($totalRecords, $currentPage = 1, $recordsPerPage = DEFAULT_PAGE_SIZE) {
    $totalPages = ceil($totalRecords / $recordsPerPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $recordsPerPage;
    
    return [
        'total_records' => $totalRecords,
        'total_pages' => $totalPages,
        'current_page' => $currentPage,
        'records_per_page' => $recordsPerPage,
        'offset' => $offset,
        'has_previous' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages,
        'previous_page' => $currentPage - 1,
        'next_page' => $currentPage + 1
    ];
}

/**
 * Generate pagination HTML
 * @param array $pagination
 * @param string $baseUrl
 * @return string
 */
function generatePaginationHTML($pagination, $baseUrl) {
    if ($pagination['total_pages'] <= 1) return '';
    
    $html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';
    
    // Previous button
    if ($pagination['has_previous']) {
        $html .= '<li class="page-item">
                    <a class="page-link" href="' . $baseUrl . '&page=' . $pagination['previous_page'] . '">Previous</a>
                  </li>';
    }
    
    // Page numbers
    $start = max(1, $pagination['current_page'] - PAGINATION_RANGE);
    $end = min($pagination['total_pages'], $pagination['current_page'] + PAGINATION_RANGE);
    
    for ($i = $start; $i <= $end; $i++) {
        $active = ($i == $pagination['current_page']) ? ' active' : '';
        $html .= '<li class="page-item' . $active . '">
                    <a class="page-link" href="' . $baseUrl . '&page=' . $i . '">' . $i . '</a>
                  </li>';
    }
    
    // Next button
    if ($pagination['has_next']) {
        $html .= '<li class="page-item">
                    <a class="page-link" href="' . $baseUrl . '&page=' . $pagination['next_page'] . '">Next</a>
                  </li>';
    }
    
    $html .= '</ul></nav>';
    
    return $html;
}

// =====================================================
// EXPORT FUNCTIONS
// =====================================================

/**
 * Export data to CSV
 * @param array $data
 * @param array $headers
 * @param string $filename
 */
function exportToCSV($data, $headers, $filename = 'export.csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Write headers
    fputcsv($output, $headers);
    
    // Write data
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

/**
 * Generate Excel file
 * @param array $data
 * @param array $headers
 * @param string $filename
 * @return string File path
 */
function generateExcelFile($data, $headers, $filename = 'export.xlsx') {
    // This is a basic implementation. For full Excel support, consider using PhpSpreadsheet
    $csvContent = '';
    
    // Add headers
    $csvContent .= implode(',', $headers) . "\n";
    
    // Add data
    foreach ($data as $row) {
        $csvContent .= implode(',', array_map(function($field) {
            return '"' . str_replace('"', '""', $field) . '"';
        }, $row)) . "\n";
    }
    
    $filepath = BACKUP_PATH . $filename;
    file_put_contents($filepath, $csvContent);
    
    return $filepath;
}

// =====================================================
// UTILITY FUNCTIONS
// =====================================================

/**
 * Generate random string
 * @param int $length
 * @return string
 */
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    
    return $randomString;
}

/**
 * Truncate text to specified length
 * @param string $text
 * @param int $length
 * @param string $suffix
 * @return string
 */
function truncateText($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) return $text;
    return substr($text, 0, $length) . $suffix;
}

/**
 * Time ago format
 * @param string $datetime
 * @return string
 */
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time / 60) . ' minutes ago';
    if ($time < 86400) return floor($time / 3600) . ' hours ago';
    if ($time < 2592000) return floor($time / 86400) . ' days ago';
    if ($time < 31104000) return floor($time / 2592000) . ' months ago';
    
    return floor($time / 31104000) . ' years ago';
}

/**
 * Debug variable (only in development)
 * @param mixed $var
 * @param bool $die
 */
function debug($var, $die = false) {
    if (ENVIRONMENT === 'development') {
        echo '<pre>';
        print_r($var);
        echo '</pre>';
        
        if ($die) die();
    }
}

/**
 * Log custom message
 * @param string $message
 * @param string $level
 */
function logMessage($message, $level = 'INFO') {
    $logFile = ROOT_PATH . 'logs/app.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$level}: {$message}" . PHP_EOL;
    
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

/**
 * Send simple email
 * @param string $to
 * @param string $subject
 * @param string $message
 * @param array $headers
 * @return bool
 */
function sendEmail($to, $subject, $message, $headers = []) {
    $defaultHeaders = [
        'From' => MAIL_FROM_EMAIL,
        'Reply-To' => MAIL_FROM_EMAIL,
        'Content-Type' => 'text/html; charset=UTF-8'
    ];
    
    $headers = array_merge($defaultHeaders, $headers);
    $headerString = '';
    
    foreach ($headers as $key => $value) {
        $headerString .= $key . ': ' . $value . "\r\n";
    }
    
    return mail($to, $subject, $message, $headerString);
}

/**
 * Get client IP address
 * @return string
 */
function getClientIP() {
    $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    
    foreach ($ipKeys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Convert array to XML
 * @param array $array
 * @param string $rootElement
 * @return string
 */
function arrayToXML($array, $rootElement = 'root') {
    $xml = new SimpleXMLElement('<' . $rootElement . '/>');
    
    function arrayToXMLRecursive($array, &$xml) {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $subnode = $xml->addChild($key);
                arrayToXMLRecursive($value, $subnode);
            } else {
                $xml->addChild($key, htmlspecialchars($value));
            }
        }
    }
    
    arrayToXMLRecursive($array, $xml);
    
    return $xml->asXML();
}

/**
 * Check if current request is AJAX
 * @return bool
 */
function isAjaxRequest() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Send JSON response
 * @param array $data
 * @param int $httpCode
 */
function sendJSONResponse($data, $httpCode = 200) {
    http_response_code($httpCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Redirect to URL
 * @param string $url
 * @param int $delay
 */
function redirect($url, $delay = 0) {
    if ($delay > 0) {
        header("refresh:{$delay};url={$url}");
    } else {
        header("Location: {$url}");
        exit;
    }
}

/**
 * Get current URL
 * @return string
 */
function getCurrentURL() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

/**
 * Clean URL parameters
 * @param string $url
 * @param array $removeParams
 * @return string
 */
function cleanURL($url, $removeParams = []) {
    $parts = parse_url($url);
    
    if (isset($parts['query'])) {
        parse_str($parts['query'], $params);
        
        foreach ($removeParams as $param) {
            unset($params[$param]);
        }
        
        $parts['query'] = http_build_query($params);
    }
    
    return http_build_url($parts);
}

// =====================================================
// SYSTEM INITIALIZATION
// =====================================================

/**
 * Initialize system on every page load
 */
function initializeSystem() {
    // Check if session is valid
    if (isLoggedIn() && !isValidSession()) {
        destroyUserSession();
        if (!isAjaxRequest()) {
            redirect(BASE_URL . 'auth/login.php?expired=1');
        }
    }
    
    // Set default timezone
    date_default_timezone_set(DEFAULT_TIMEZONE);
}

// Auto-initialize system
initializeSystem();

?>