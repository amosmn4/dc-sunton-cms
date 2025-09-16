<?php
// index.php

// Start session
session_start();

// Load configuration and database
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) && !in_array($_GET['page'] ?? 'login', ['login', 'register'])) {
    header("Location: auth/login.php");
    exit();
}

// Default page
$page = $_GET['page'] ?? 'dashboard';

// Define allowed modules
$allowedModules = [
    'dashboard',
    'members',
    'attendance',
    'finance',
    'equipment',
    'sms',
    'visitors',
    'events',
    'reports',
    'admin'
];

// Security check – prevent path traversal
if (!in_array($page, $allowedModules)) {
    $page = 'dashboard';
}

// Include header
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';

// Load requested module
$modulePath = __DIR__ . "/modules/$page/index.php";
if (file_exists($modulePath)) {
    include $modulePath;
} else {
    echo "<h2>404 - Page not found</h2>";
}

// Include footer
include __DIR__ . '/includes/footer.php';
