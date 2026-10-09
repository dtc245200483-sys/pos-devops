<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_manager();

$pdo = get_db_connection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        set_flash('danger', 'CSRF token không hợp lệ.');
        header('Location: users.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    // Tạo nhân viên mới
    if ($action === 'create_user') {
        $username = trim($_POST['username'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'staff';

        if (!in_array($role, ['manager', 'staff'])) {
            $role = 'staff';
        }

        if ($username === '' || $full_name === '' || strlen($password) < 6) {
            set_flash('danger', 'Vui lòng nhập đầy đủ thông tin và mật khẩu từ 6 ký tự trở lên.');
        } else {
            $chk = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $chk->execute([$username]);
            if ($chk->fetch()) {
                set_flash('danger', "Tên đăng nhập '$username' đã tồn tại!");
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $ins = $pdo->prepare("INSERT INTO users (username, password_hash, full_name, role, is_active) VALUES (?, ?, ?, ?, 1)");
                $ins->execute([$username, $hash, $full_name, $role]);
                set_flash('success', "Đã tạo tài khoản '$username' thành công.");
            }
        }
        header('Location: users.php');
        exit;
    }

    // Khóa / Mở khóa tài khoản
    if ($action === 'toggle_active') {
        $uid = (int)($_POST['id'] ?? 0);
        if ($uid === (int)$_SESSION['user_id']) {
            set_flash('danger', 'Không thể tự khóa tài khoản của chính mình!');
        } else {
            $upd = $pdo->prepare("UPDATE users SET is_active = 1 - is_active WHERE id = ?");
            $upd->execute([$uid]);
            set_flash('success', "Đã thay đổi trạng thái tài khoản thành công.");
        }
        header('Location: users.php');
        exit;
    }
}

$users = $pdo->query("SELECT id, username, full_name, role, is_active, created_at FROM users ORDER BY id ASC")->fetchAll();

$page_title = "Quản lý Nhân Viên";
require_once __DIR__ . '/includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
    <h2>Quản Lý Danh Sách Nhân Viên & Phân Quyền</h2>
    <button onclick="document.getElementById('user-modal').style.display='block'" class="btn btn-primary">+ Thêm Nhân Viên</button>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tên đăng nhập</th>
                    <th>Họ và tên</th>
                    <th>Vai trò</th>
                    <th style="text-align: center;">Trạng thái</th>
                    <th>Ngày tạo</th>
                    <th style="text-align: center;">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td>#<?= $u['id'] ?></td>
                        <td><code><?= e($u['username']) ?></code></td>
                        <td><strong><?= e($u['full_name']) ?></strong></td>
                        <td>
                            <span class="badge <?= $u['role'] === 'manager' ? 'badge-manager' : 'badge-staff' ?>">
                                <?= $u['role'] === 'manager' ? 'Quản lý' : 'Nhân viên' ?>
                            </span>
                        </td>
                        <td style="text-align: center;">
                            <span class="badge <?= $u['is_active'] ? 'badge-success' : 'badge-danger' ?>">
                                <?= $u['is_active'] ? 'Hoạt động' : 'Đã khóa' ?>
                            </span>
                        </td>
                        <td><?= date('d/m/Y H:i', strtotime($u['created_at'])) ?></td>
                        <td style="text-align: center;">
                            <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                                <form method="POST" action="users.php" style="display: inline;" onsubmit="return confirm('Xác nhận đổi trạng thái khóa/mở tài khoản này?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_active">
                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn <?= $u['is_active'] ? 'btn-danger' : 'btn-success' ?> btn-sm">
                                        <?= $u['is_active'] ? 'Khóa tài khoản' : 'Mở khóa' ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <span style="font-size: 0.8rem; color: var(--text-muted);">(Tài khoản hiện tại)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Thêm Nhân Viên -->
<div id="user-modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 100;">
    <div style="background: white; max-width: 450px; margin: 5rem auto; padding: 2rem; border-radius: 8px;">
        <h3 style="margin-bottom: 1rem;">Thêm Tài Khoản Mới</h3>
        <form method="POST" action="users.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_user">
            <div class="form-group" style="margin-bottom: 0.75rem;">
                <label>Tên đăng nhập:</label>
                <input type="text" name="username" class="form-control" required placeholder="VD: nhanvien02">
            </div>
            <div class="form-group" style="margin-bottom: 0.75rem;">
                <label>Họ và tên:</label>
                <input type="text" name="name" class="form-control" name="full_name" required placeholder="VD: Trần Văn A">
            </div>
            <div class="form-group" style="margin-bottom: 0.75rem;">
                <label>Mật khẩu khởi tạo:</label>
                <input type="password" name="password" class="form-control" required minlength="6" placeholder="Tối thiểu 6 ký tự">
            </div>
            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label>Vai trò:</label>
                <select name="role" class="form-control">
                    <option value="staff">Nhân viên bán hàng (Staff)</option>
                    <option value="manager">Quản lý (Manager)</option>
                </select>
            </div>
            <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('user-modal').style.display='none'">Hủy</button>
                <button type="submit" class="btn btn-primary">Tạo Tài Khoản</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
