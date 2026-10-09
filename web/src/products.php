<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_login();

$pdo = get_db_connection();
$is_mgr = is_manager();

// Xử lý Quản lý thêm / sửa / nhập tồn sản phẩm
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_mgr) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        set_flash('danger', 'CSRF token không hợp lệ.');
        header('Location: products.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    // Thêm sản phẩm mới
    if ($action === 'create_product') {
        $code = trim($_POST['code'] ?? '');
        $barcode = trim($_POST['barcode'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $stock = max(0, (int)($_POST['stock_qty'] ?? 0));
        $status = $stock > 0 ? 'in_stock' : 'out_of_stock';

        if ($code === '' || $barcode === '' || $name === '' || $price <= 0) {
            set_flash('danger', 'Vui lòng điền đầy đủ thông tin sản phẩm và giá hợp lệ (> 0).');
        } else {
            // Check trùng mã hoặc barcode
            $chk = $pdo->prepare("SELECT id FROM products WHERE code = ? OR barcode = ?");
            $chk->execute([$code, $barcode]);
            if ($chk->fetch()) {
                set_flash('danger', "Mã sản phẩm ($code) hoặc Mã vạch ($barcode) đã tồn tại!");
            } else {
                $ins = $pdo->prepare("INSERT INTO products (code, barcode, name, price, stock_qty, status) VALUES (?, ?, ?, ?, ?, ?)");
                $ins->execute([$code, $barcode, $name, $price, $stock, $status]);
                set_flash('success', "Đã thêm sản phẩm '$name' thành công.");
            }
        }
        header('Location: products.php');
        exit;
    }

    // Sửa giá & thông tin sản phẩm
    if ($action === 'update_product') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $barcode = trim($_POST['barcode'] ?? '');

        if ($name === '' || $price <= 0 || $barcode === '') {
            set_flash('danger', 'Thông tin cập nhật không hợp lệ.');
        } else {
            // Check trùng barcode với sp khác
            $chk = $pdo->prepare("SELECT id FROM products WHERE barcode = ? AND id != ?");
            $chk->execute([$barcode, $id]);
            if ($chk->fetch()) {
                set_flash('danger', "Mã vạch ($barcode) đã thuộc về sản phẩm khác!");
            } else {
                $upd = $pdo->prepare("UPDATE products SET name = ?, price = ?, barcode = ? WHERE id = ?");
                $upd->execute([$name, $price, $barcode, $id]);
                set_flash('success', "Cập nhật sản phẩm thành công.");
            }
        }
        header('Location: products.php');
        exit;
    }

    // Nhập bổ sung tồn kho ra quầy (chỉ số nguyên dương)
    if ($action === 'restock') {
        $id = (int)($_POST['id'] ?? 0);
        $add_qty = (int)($_POST['add_quantity'] ?? 0);

        if ($add_qty <= 0) {
            set_flash('danger', 'Số lượng nhập bổ sung phải là số nguyên dương (> 0).');
        } else {
            $upd = $pdo->prepare("UPDATE products SET stock_qty = stock_qty + ?, status = 'in_stock' WHERE id = ?");
            $upd->execute([$add_qty, $id]);
            set_flash('success', "Đã bổ sung {$add_qty} sản phẩm ra quầy thành công.");
        }
        header('Location: products.php');
        exit;
    }
}

// Tìm kiếm & Danh sách
$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE name LIKE ? OR code LIKE ? OR barcode LIKE ? ORDER BY id DESC");
    $stmt->execute(["%$q%", "%$q%", "%$q%"]);
    $products = $stmt->fetchAll();
} else {
    $products = $pdo->query("SELECT * FROM products ORDER BY id DESC")->fetchAll();
}

$page_title = "Quản lý Sản phẩm & Tồn kho";
require_once __DIR__ . '/includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
    <h2>Danh Mục Sản Phẩm & Tồn Kho Quầy</h2>
    <div style="display: flex; gap: 0.5rem;">
        <form method="GET" action="products.php" style="display: flex; gap: 4px;">
            <input type="text" name="q" value="<?= e($q) ?>" class="form-control" placeholder="Tìm kiếm sản phẩm...">
            <button type="submit" class="btn btn-secondary">Tìm</button>
            <?php if ($q !== ''): ?><a href="products.php" class="btn btn-outline">Hủy</a><?php endif; ?>
        </form>
        <?php if ($is_mgr): ?>
            <button onclick="document.getElementById('add-modal').style.display='block'" class="btn btn-primary">+ Thêm Sản Phẩm Mới</button>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Mã SP</th>
                    <th>Mã vạch (Barcode)</th>
                    <th>Tên sản phẩm</th>
                    <th>Đơn giá</th>
                    <th style="text-align: center;">Tồn quầy</th>
                    <th style="text-align: center;">Trạng thái</th>
                    <?php if ($is_mgr): ?><th style="text-align: center;">Thao tác</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr><td colspan="<?= $is_mgr ? 7 : 6 ?>" style="text-align: center; color: var(--text-muted); padding: 1.5rem;">Không tìm thấy sản phẩm nào.</td></tr>
                <?php else: ?>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td><code><?= e($p['code']) ?></code></td>
                            <td><code><?= e($p['barcode']) ?></code></td>
                            <td><strong><?= e($p['name']) ?></strong></td>
                            <td style="color: var(--primary); font-weight: 600;"><?= format_money($p['price']) ?></td>
                            <td style="text-align: center; font-weight: 700;"><?= (int)$p['stock_qty'] ?></td>
                            <td style="text-align: center;">
                                <span class="badge <?= $p['status'] === 'in_stock' ? 'badge-success' : 'badge-danger' ?>">
                                    <?= $p['status'] === 'in_stock' ? 'Còn hàng' : 'Hết hàng' ?>
                                </span>
                            </td>
                            <?php if ($is_mgr): ?>
                                <td style="text-align: center;">
                                    <button class="btn btn-outline btn-sm" onclick='openEdit(<?= json_encode($p) ?>)'>Sửa</button>
                                    <button class="btn btn-success btn-sm" onclick='openRestock(<?= $p["id"] ?>, <?= json_encode($p["name"]) ?>)'>+ Nhập kho</button>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($is_mgr): ?>
<!-- Modal Thêm Sản Phẩm -->
<div id="add-modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 100;">
    <div style="background: white; max-width: 500px; margin: 5rem auto; padding: 2rem; border-radius: 8px; position: relative;">
        <h3 style="margin-bottom: 1rem;">Thêm Sản Phẩm Mới</h3>
        <form method="POST" action="products.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_product">
            <div class="form-group" style="margin-bottom: 0.75rem;">
                <label>Mã sản phẩm (duy nhất):</label>
                <input type="text" name="code" class="form-control" required placeholder="VD: SP016">
            </div>
            <div class="form-group" style="margin-bottom: 0.75rem;">
                <label>Mã vạch Barcode (duy nhất):</label>
                <input type="text" name="barcode" class="form-control" required placeholder="VD: 893500110016">
            </div>
            <div class="form-group" style="margin-bottom: 0.75rem;">
                <label>Tên sản phẩm:</label>
                <input type="text" name="name" class="form-control" required placeholder="VD: Nước tăng lực Red Bull 250ml">
            </div>
            <div class="form-group" style="margin-bottom: 0.75rem;">
                <label>Giá bán (VNĐ):</label>
                <input type="number" name="price" class="form-control" required min="1000" step="500" placeholder="VD: 15000">
            </div>
            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label>Tồn kho ban đầu:</label>
                <input type="number" name="stock_qty" class="form-control" required min="0" value="50">
            </div>
            <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('add-modal').style.display='none'">Hủy</button>
                <button type="submit" class="btn btn-primary">Lưu Sản Phẩm</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Sửa Sản Phẩm -->
<div id="edit-modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 100;">
    <div style="background: white; max-width: 500px; margin: 5rem auto; padding: 2rem; border-radius: 8px;">
        <h3 style="margin-bottom: 1rem;">Sửa Thông Tin & Giá Sản Phẩm</h3>
        <form method="POST" action="products.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_product">
            <input type="hidden" name="id" id="edit-id">
            <div class="form-group" style="margin-bottom: 0.75rem;">
                <label>Tên sản phẩm:</label>
                <input type="text" name="name" id="edit-name" class="form-control" required>
            </div>
            <div class="form-group" style="margin-bottom: 0.75rem;">
                <label>Mã vạch (Barcode):</label>
                <input type="text" name="barcode" id="edit-barcode" class="form-control" required>
            </div>
            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label>Giá bán (VNĐ):</label>
                <input type="number" name="price" id="edit-price" class="form-control" required min="1000" step="500">
            </div>
            <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('edit-modal').style.display='none'">Hủy</button>
                <button type="submit" class="btn btn-primary">Lưu Thay Đổi</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Nhập Hàng Bổ Sung -->
<div id="restock-modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 100;">
    <div style="background: white; max-width: 450px; margin: 6rem auto; padding: 2rem; border-radius: 8px;">
        <h3 style="margin-bottom: 0.5rem;">Nhập Bổ Sung Tồn Kho Ra Quầy</h3>
        <p id="restock-product-name" style="color: var(--primary); font-weight: 600; margin-bottom: 1rem;"></p>
        <form method="POST" action="products.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="restock">
            <input type="hidden" name="id" id="restock-id">
            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label>Số lượng nhập thêm (Số nguyên dương):</label>
                <input type="number" name="add_quantity" class="form-control" required min="1" value="20">
            </div>
            <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('restock-modal').style.display='none'">Hủy</button>
                <button type="submit" class="btn btn-success">+ Nhập Hàng</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEdit(prod) {
    document.getElementById('edit-id').value = prod.id;
    document.getElementById('edit-name').value = prod.name;
    document.getElementById('edit-barcode').value = prod.barcode;
    document.getElementById('edit-price').value = prod.price;
    document.getElementById('edit-modal').style.display = 'block';
}

function openRestock(id, name) {
    document.getElementById('restock-id').value = id;
    document.getElementById('restock-product-name').innerText = name;
    document.getElementById('restock-modal').style.display = 'block';
}
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
