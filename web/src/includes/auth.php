<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function get_client_ip() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

function log_auth_event($success, $username, $reason = null) {
    $ip = get_client_ip();
    if ($success) {
        $msg = "LOGIN_SUCCESS ip={$ip} user={$username}";
    } else {
        $msg = "LOGIN_FAILED ip={$ip} user={$username} reason={$reason}";
    }
    error_log($msg);
    $stderr = fopen('php://stderr', 'w');
    if ($stderr) {
        fwrite($stderr, $msg . "\n");
        fclose($stderr);
    }
}

function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function current_user() {
    return $_SESSION['user'] ?? null;
}

function is_manager() {
    return is_logged_in() && (current_user()['role'] ?? '') === 'manager';
}

function is_staff() {
    return is_logged_in() && (current_user()['role'] ?? '') === 'staff';
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function require_manager() {
    require_login();
    if (!is_manager()) {
        http_response_code(403);
        die("Bạn không có quyền truy cập chức năng này (Chỉ Quản lý được phép). <a href='pos.php'>Quay lại</a>");
    }
}

function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

function verify_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}
