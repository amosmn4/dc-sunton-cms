<?php
/**
 * Login Page
 * Deliverance Church Management System
 * 
 * Handles user authentication and login
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit();
}

// Initialize variables
$error_message = '';
$login_attempts = 0;
$lockout_time = 0;

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']);
    
    // Validate input
    if (empty($username) || empty($password)) {
        $error_message = 'Please enter both username and password.';
    } else {
        try {
            $db = Database::getInstance();
            
            // Get user by username or email
            $stmt = $db->executeQuery(
                "SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1 LIMIT 1",
                [$username, $username]
            );
            $user = $stmt->fetch();
            
            if ($user) {
                // Check if account is locked
                if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                    $lockout_time = strtotime($user['locked_until']) - time();
                    $error_message = 'Account is temporarily locked due to multiple failed login attempts. Please try again in ' . ceil($lockout_time / 60) . ' minutes.';
                } else {
                    // Verify password
                    if (verifyPassword($password, $user['password'])) {
                        // Successful login
                        
                        // Clear any account locks
                        $db->executeQuery(
                            "UPDATE users SET login_attempts = 0, locked_until = NULL WHERE id = ?",
                            [$user['id']]
                        );
                        
                        // Initialize session
                        initializeUserSession($user);
                        
                        // Handle remember me
                        if ($remember_me) {
                            $token = generateSecureToken();
                            setcookie('remember_token', $token, time() + REMEMBER_ME_DURATION, '/', '', true, true);
                            
                            // Store token in database (you may want to create a separate table for this)
                            $db->executeQuery(
                                "UPDATE users SET remember_token = ? WHERE id = ?",
                                [hashPassword($token), $user['id']]
                            );
                        }
                        
                        // Log successful login
                        logActivity('User logged in successfully', 'users', $user['id']);
                        
                        // Redirect to dashboard or intended page
                        $redirect_url = $_GET['redirect'] ?? BASE_URL . 'modules/dashboard/';
                        header('Location: ' . $redirect_url);
                        exit();
                        
                    } else {
                        // Failed login - increment attempts
                        $login_attempts = $user['login_attempts'] + 1;
                        
                        if ($login_attempts >= MAX_LOGIN_ATTEMPTS) {
                            // Lock account
                            $lock_until = date('Y-m-d H:i:s', time() + LOCKOUT_DURATION);
                            $db->executeQuery(
                                "UPDATE users SET login_attempts = ?, locked_until = ? WHERE id = ?",
                                [$login_attempts, $lock_until, $user['id']]
                            );
                            
                            $error_message = 'Account locked due to multiple failed login attempts. Please try again later.';
                            
                            // Log security event
                            logActivity('Account locked due to failed login attempts', 'users', $user['id']);
                        } else {
                            // Update login attempts
                            $db->executeQuery(
                                "UPDATE users SET login_attempts = ? WHERE id = ?",
                                [$login_attempts, $user['id']]
                            );
                            
                            $remaining_attempts = MAX_LOGIN_ATTEMPTS - $login_attempts;
                            $error_message = "Invalid credentials. You have {$remaining_attempts} attempt(s) remaining before your account is locked.";
                        }
                        
                        // Log failed login attempt
                        logActivity('Failed login attempt', 'users', $user['id']);
                    }
                }
            } else {
                $error_message = 'Invalid username or password.';
                // Log failed login attempt with unknown user
                error_log("Failed login attempt with username: " . $username . " from IP: " . getClientIP());
            }
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            $error_message = 'An error occurred during login. Please try again.';
        }
    }
}

// Handle remember me token
if (!isLoggedIn() && isset($_COOKIE['remember_token'])) {
    try {
        $db = Database::getInstance();
        $stmt = $db->executeQuery(
            "SELECT * FROM users WHERE remember_token IS NOT NULL AND is_active = 1"
        );
        
        while ($user = $stmt->fetch()) {
            if (verifyPassword($_COOKIE['remember_token'], $user['remember_token'])) {
                initializeUserSession($user);
                header('Location: ' . BASE_URL . 'modules/dashboard/');
                exit();
            }
        }
    } catch (Exception $e) {
        error_log("Remember me token error: " . $e->getMessage());
    }
}

// Get church information for branding
$church_info = getRecord('church_info', 'id', 1) ?: [
    'church_name' => 'Deliverance Church',
    'logo' => '',
    'mission_statement' => 'Welcome to our Church Management System'
];

$page_title = 'Login';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title . ' - ' . $church_info['church_name']); ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>assets/images/favicon.png">
    
    <!-- CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>assets/css/style.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, var(--church-blue) 0%, var(--church-blue-light) 50%, var(--church-red) 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 420px;
            width: 100%;
            animation: slideUp 0.6s ease-out;
        }
        
        .login-header {
            background: linear-gradient(135deg, var(--church-blue) 0%, var(--church-blue-light) 100%);
            color: white;
            padding: 2.5rem 2rem 2rem;
            text-align: center;
        }
        
        .login-header i {
            font-size: 3.5rem;
            margin-bottom: 1rem;
            opacity: 0.9;
        }
        
        .login-body {
            padding: 2rem;
        }
        
        .login-footer {
            background: #f8f9fa;
            padding: 1.5rem 2rem;
            text-align: center;
            border-top: 1px solid #e9ecef;
        }
        
        .form-control {
            border-radius: 8px;
            padding: 0.75rem 1rem;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--church-blue);
            box-shadow: 0 0 0 0.2rem rgba(3, 4, 94, 0.25);
        }
        
        .btn-login {
            background: linear-gradient(135deg, var(--church-red) 0%, var(--church-blue) 100%);
            border: none;
            border-radius: 8px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(3, 4, 94, 0.3);
        }
        
        .alert {
            border-radius: 8px;
            border: none;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .floating-shapes {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: -1;
        }
        
        .floating-shapes::before,
        .floating-shapes::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            animation: float 6s ease-in-out infinite;
        }
        
        .floating-shapes::before {
            width: 100px;
            height: 100px;
            top: 10%;
            left: 10%;
            animation-delay: 0s;
        }
        
        .floating-shapes::after {
            width: 150px;
            height: 150px;
            top: 70%;
            right: 10%;
            animation-delay: 3s;
        }
        
        @keyframes float {
            0%, 100% {
                transform: translateY(0px);
                opacity: 0.7;
            }
            50% {
                transform: translateY(-20px);
                opacity: 1;
            }
        }
    </style>
</head>
<body>
    <div class="floating-shapes"></div>
    
    <div class="login-container">
        <div class="login-card">
            <!-- Login Header -->
            <div class="login-header">
                <?php if (!empty($church_info['logo'])): ?>
                    <img src="<?php echo BASE_URL . $church_info['logo']; ?>" alt="Church Logo" class="img-fluid mb-3" style="max-height: 60px;">
                <?php else: ?>
                    <i class="fas fa-church"></i>
                <?php endif; ?>
                <h4 class="mb-1"><?php echo htmlspecialchars($church_info['church_name']); ?></h4>
                <p class="mb-0 opacity-75">Deliverance Church Management System</p>
            </div>
            
            <!-- Login Form -->
            <div class="login-body">
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger d-flex align-items-center mb-3" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <div><?php echo htmlspecialchars($error_message); ?></div>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['expired'])): ?>
                    <div class="alert alert-warning d-flex align-items-center mb-3" role="alert">
                        <i class="fas fa-clock me-2"></i>
                        <div>Your session has expired. Please log in again.</div>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['logout'])): ?>
                    <div class="alert alert-success d-flex align-items-center mb-3" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <div>You have been logged out successfully.</div>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label for="username" class="form-label">
                            <i class="fas fa-user me-2"></i>Username or Email
                        </label>
                        <input type="text" 
                               class="form-control" 
                               id="username" 
                               name="username" 
                               value="<?php echo htmlspecialchars($username ?? ''); ?>"
                               required
                               autocomplete="username"
                               placeholder="Enter your username or email">
                        <div class="invalid-feedback">
                            Please enter your username or email.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">
                            <i class="fas fa-lock me-2"></i>Password
                        </label>
                        <div class="input-group">
                            <input type="password" 
                                   class="form-control" 
                                   id="password" 
                                   name="password" 
                                   required
                                   autocomplete="current-password"
                                   placeholder="Enter your password">
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback">
                            Please enter your password.
                        </div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me">
                        <label class="form-check-label" for="remember_me">
                            Remember me for 30 days
                        </label>
                    </div>
                    
                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-login text-white">
                            <i class="fas fa-sign-in-alt me-2"></i>Sign In
                        </button>
                    </div>
                    
                    <div class="text-center">
                        <a href="forgot_password.php" class="text-decoration-none text-muted">
                            <i class="fas fa-question-circle me-1"></i>Forgot your password?
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Login Footer -->
            <div class="login-footer">
                <small class="text-muted">
                    <i class="fas fa-shield-alt me-1"></i>
                    Secure login powered by Church CMS
                </small>
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        (function() {
            'use strict';
            const form = document.querySelector('.needs-validation');
            
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                
                form.classList.add('was-validated');
            }, false);
        })();
        
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordField = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        
        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                if (alert.classList.contains('alert-success') || alert.classList.contains('alert-warning')) {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }
            });
        }, 5000);
        
        // Focus on username field
        document.getElementById('username').focus();
    </script>
</body>
</html>