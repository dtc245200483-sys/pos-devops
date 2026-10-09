<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

if (is_logged_in()) {
    header('Location: pos.php');
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $error = "Phiên làm việc đã hết hạn (CSRF mismatch). Vui lòng thử lại.";
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = "Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu.";
        } else {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if (!$user) {
                log_auth_event(false, $username, 'unknown_user');
                $error = "Tài khoản hoặc mật khẩu không chính xác.";
            } elseif ((int)$user['is_active'] !== 1) {
                log_auth_event(false, $username, 'inactive');
                $error = "Tài khoản đã bị khóa. Vui lòng liên hệ Quản lý.";
            } elseif (!password_verify($password, $user['password_hash'])) {
                log_auth_event(false, $username, 'wrong_password');
                $error = "Tài khoản hoặc mật khẩu không chính xác.";
            } else {
                log_auth_event(true, $username);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user'] = $user;
                set_flash('success', "Chào mừng " . $user['full_name'] . " đăng nhập thành công!");
                header('Location: pos.php');
                exit;
            }
        }
    }
}
$page_title = "Đăng nhập POS";
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?></title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .login-wrap {
            max-width: 420px;
            margin: 5rem auto;
            background: white;
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
            border: 1px solid var(--border);
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-header h2 { color: var(--primary); font-size: 1.6rem; }
        .form-group { margin-bottom: 1.25rem; }
        .form-group label { display: block; margin-bottom: 0.4rem; font-weight: 600; font-size: 0.9rem; }
    </style>
</head>
<body>
<div class="login-wrap">
    <div class="login-header">
        <h2>ĐĂNG NHẬP POS</h2>
        <p style="color: var(--text-muted); font-size: 0.9rem;">Hệ thống Quản lý Bán lẻ tại Quầy</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="username">Tên đăng nhập:</label>
            <input type="text" id="username" name="username" class="form-control" required autofocus placeholder="Tên đăng nhập">
        </div>
        <div class="form-group">
            <label for="password">Mật khẩu:</label>
            <input type="password" id="password" name="password" class="form-control" required placeholder="Nhập mật khẩu">
        </div>
        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem;">Đăng Nhập</button>
    </form>
</div>
</body>
</html>
