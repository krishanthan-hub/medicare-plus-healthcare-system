<?php
// Safe session start — prevents "session already active" notice
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Database configuration ─────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'medicare_plus');

// ── Database connection ────────────────────────────────────────
function getDB() {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
        $conn->set_charset("utf8");
    }
    return $conn;
}

// Also make $conn available globally for files that use it directly
$conn = getDB();

// ── Sanitize input ─────────────────────────────────────────────
function validate($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// ── Redirect with optional flash message ──────────────────────
function redirect($url, $message = '', $type = 'success') {
    if (!empty($message)) {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type']    = $type;
    }
    header("Location: $url");
    exit();
}

// ── Display and clear flash message ───────────────────────────
function flash() {
    if (isset($_SESSION['flash_message'])) {
        $type = $_SESSION['flash_type'] ?? 'success';
        $msg  = $_SESSION['flash_message'];
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        echo "<div class='alert alert-{$type}'><i class='fas fa-check-circle'></i> "
             . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . "</div>";
    }
}

// ── Generate CSRF token ────────────────────────────────────────
function generateCSRF() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// ── Validate CSRF token ────────────────────────────────────────
function validateCSRF() {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Security check failed. Please go back and try again.");
    }
}