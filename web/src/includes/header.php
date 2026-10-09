<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
$user = current_user();
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title ?? 'POS Quản Lý Bán Hàng') ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php if (is_logged_in()): ?>
<nav class="navbar">
    <a href="pos.php" class="brand">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
        POS Cửa Hàng
    </a>
    <ul class="nav-links">
        <li><a href="pos.php" class="<?= $current_page === 'pos.php' ? 'active' : '' ?>">Bán hàng tại quầy</a></li>
        <li><a href="products.php" class="<?= $current_page === 'products.php' ? 'active' : '' ?>">Sản phẩm & Tồn kho</a></li>
        <?php if (is_manager()): ?>
            <li><a href="users.php" class="<?= $current_page === 'users.php' ? 'active' : '' ?>">Quản lý nhân viên</a></li>
            <li><a href="reports.php" class="<?= $current_page === 'reports.php' ? 'active' : '' ?>">Báo cáo doanh thu</a></li>
        <?php endif; ?>
    </ul>
    <div class="nav-user">
        <span><?= e($user['full_name']) ?></span>
        <span class="badge <?= $user['role'] === 'manager' ? 'badge-manager' : 'badge-staff' ?>">
            <?= $user['role'] === 'manager' ? 'Quản lý' : 'Nhân viên' ?>
        </span>
        <a href="logout.php" class="btn btn-danger btn-sm">Đăng xuất</a>
    </div>
</nav>
<?php endif; ?>

<div class="container">
    <?php $flash = get_flash(); if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>">
            <?= e($flash['message']) ?>
        </div>
    <?php endif; ?>
