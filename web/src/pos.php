<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_login();

$pdo = get_db_connection();

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Xử lý giỏ hàng và thanh toán
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        set_flash('danger', 'Lỗi xác thực CSRF. Vui lòng thử lại.');
        header('Location: pos.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    // Thêm sản phẩm vào giỏ
    if ($action === 'add_item') {
        $product_id = (int)($_POST['product_id'] ?? 0);
        $qty = max(1, (int)($_POST['quantity'] ?? 1));

        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $prod = $stmt->fetch();

        if ($prod) {
            $current_in_cart = $_SESSION['cart'][$product_id]['quantity'] ?? 0;
            if ($current_in_cart + $qty > $prod['stock_qty']) {
                set_flash('danger', "Không đủ tồn kho cho '{$prod['name']}'! Hiện còn: {$prod['stock_qty']}, trong giỏ: {$current_in_cart}.");
            } else {
                if (isset($_SESSION['cart'][$product_id])) {
                    $_SESSION['cart'][$product_id]['quantity'] += $qty;
                } else {
                    $_SESSION['cart'][$product_id] = [
                        'id' => $prod['id'],
                        'code' => $prod['code'],
                        'barcode' => $prod['barcode'],
                        'name' => $prod['name'],
                        'price' => (float)$prod['price'],
                        'stock_qty' => (int)$prod['stock_qty'],
                        'quantity' => $qty
                    ];
                }
                set_flash('success', "Đã thêm '{$prod['name']}' vào giỏ hàng.");
            }
        } else {
            set_flash('danger', 'Không tìm thấy sản phẩm.');
        }
        header('Location: pos.php');
        exit;
    }

    // Quét / Nhập mã vạch nhanh
    if ($action === 'quick_barcode') {
        $barcode = trim($_POST['barcode'] ?? '');
        if ($barcode !== '') {
            $stmt = $pdo->prepare("SELECT * FROM products WHERE barcode = ? OR code = ?");
            $stmt->execute([$barcode, $barcode]);
            $prod = $stmt->fetch();

            if ($prod) {
                $pid = $prod['id'];
                $current_in_cart = $_SESSION['cart'][$pid]['quantity'] ?? 0;
                if ($current_in_cart + 1 > $prod['stock_qty']) {
                    set_flash('danger', "Sản phẩm '{$prod['name']}' đã hết hoặc không đủ tồn kho (Còn: {$prod['stock_qty']}).");
                } else {
                    if (isset($_SESSION['cart'][$pid])) {
                        $_SESSION['cart'][$pid]['quantity'] += 1;
                    } else {
                        $_SESSION['cart'][$pid] = [
                            'id' => $prod['id'],
                            'code' => $prod['code'],
                            'barcode' => $prod['barcode'],
                            'name' => $prod['name'],
                            'price' => (float)$prod['price'],
                            'stock_qty' => (int)$prod['stock_qty'],
                            'quantity' => 1
                        ];
                    }
                    set_flash('success', "Đã quét: '{$prod['name']}'");
                }
            } else {
                set_flash('danger', "Không tìm thấy sản phẩm với mã / barcode: {$barcode}");
            }
        }
        header('Location: pos.php');
        exit;
    }

    // Cập nhật số lượng
    if ($action === 'update_qty') {
        $pid = (int)($_POST['product_id'] ?? 0);
        $new_qty = (int)($_POST['quantity'] ?? 1);

        if (isset($_SESSION['cart'][$pid])) {
            $stmt = $pdo->prepare("SELECT stock_qty, name FROM products WHERE id = ?");
            $stmt->execute([$pid]);
            $prod = $stmt->fetch();

            if ($new_qty <= 0) {
                unset($_SESSION['cart'][$pid]);
                set_flash('info', 'Đã xóa sản phẩm khỏi giỏ hàng.');
            } elseif ($prod && $new_qty > $prod['stock_qty']) {
                set_flash('danger', "Vượt quá tồn kho cho '{$prod['name']}'! Tối đa có thể bán: {$prod['stock_qty']}.");
            } else {
                $_SESSION['cart'][$pid]['quantity'] = $new_qty;
                set_flash('success', 'Đã cập nhật số lượng.');
            }
        }
        header('Location: pos.php');
        exit;
    }

    // Xóa khỏi giỏ
    if ($action === 'remove_item') {
        $pid = (int)($_POST['product_id'] ?? 0);
        unset($_SESSION['cart'][$pid]);
        set_flash('info', 'Đã xóa sản phẩm khỏi giỏ.');
        header('Location: pos.php');
        exit;
    }

    // Xóa toàn bộ giỏ
    if ($action === 'clear_cart') {
        $_SESSION['cart'] = [];
        set_flash('info', 'Đã làm mới giỏ hàng.');
        header('Location: pos.php');
        exit;
    }

    // Thanh toán đơn hàng
    if ($action === 'checkout') {
        if (empty($_SESSION['cart'])) {
            set_flash('danger', 'Giỏ hàng đang trống! Vui lòng chọn ít nhất 1 sản phẩm.');
            header('Location: pos.php');
            exit;
        }

        $payment_method = $_POST['payment_method'] ?? 'cash';
        if (!in_array($payment_method, ['cash', 'card', 'qr'])) {
            $payment_method = 'cash';
        }

        $total_amount = 0;
        foreach ($_SESSION['cart'] as $item) {
            $total_amount += $item['price'] * $item['quantity'];
        }

        $amount_paid = (float)($_POST['amount_paid'] ?? 0);
        if ($payment_method !== 'cash') {
            $amount_paid = $total_amount;
        }

        if ($amount_paid < $total_amount) {
            set_flash('danger', "Tiền khách đưa (" . format_money($amount_paid) . ") chưa đủ so với tổng tiền (" . format_money($total_amount) . ")!");
            header('Location: pos.php');
            exit;
        }

        $change_amount = $amount_paid - $total_amount;
        $order_code = 'HD' . date('YmdHis') . rand(100, 999);
        $user_id = (int)$_SESSION['user_id'];

        // BẮT ĐẦU TRANSACTION ĐẢM BẢO AN TOÀN TỒN KHO
        $pdo->beginTransaction();
        try {
            // Khóa dòng và kiểm tra tồn kho từng mặt hàng
            foreach ($_SESSION['cart'] as $pid => $item) {
                $check_stmt = $pdo->prepare("SELECT id, name, price, stock_qty FROM products WHERE id = ? FOR UPDATE");
                $check_stmt->execute([$pid]);
                $p_db = $check_stmt->fetch();

                if (!$p_db) {
                    throw new Exception("Sản phẩm '{$item['name']}' không tồn tại trong hệ thống!");
                }
                if ($p_db['stock_qty'] < $item['quantity']) {
                    throw new Exception("Sản phẩm '{$p_db['name']}' không đủ tồn kho tại quầy! (Hiện còn: {$p_db['stock_qty']}, yêu cầu bán: {$item['quantity']})");
                }
            }

            // Tạo hóa đơn
            $ins_order = $pdo->prepare("INSERT INTO orders (order_code, user_id, total_amount, payment_method, amount_paid, change_amount) VALUES (?, ?, ?, ?, ?, ?)");
            $ins_order->execute([$order_code, $user_id, $total_amount, $payment_method, $amount_paid, $change_amount]);
            $order_id = $pdo->lastInsertId();

            // Tạo chi tiết hóa đơn & trừ tồn kho
            $ins_detail = $pdo->prepare("INSERT INTO order_details (order_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
            $upd_stock = $pdo->prepare("UPDATE products SET stock_qty = stock_qty - ?, status = IF(stock_qty - ? = 0, 'out_of_stock', 'in_stock') WHERE id = ?");

            foreach ($_SESSION['cart'] as $pid => $item) {
                $qty = $item['quantity'];
                $unit_price = $item['price'];
                $ins_detail->execute([$order_id, $pid, $qty, $unit_price]);
                $upd_stock->execute([$qty, $qty, $pid]);
            }

            $pdo->commit();
            $_SESSION['cart'] = [];
            set_flash('success', "Thanh toán thành công hóa đơn {$order_code}!");
            header("Location: invoice.php?order_code={$order_code}");
            exit;

        } catch (Exception $ex) {
            $pdo->rollBack();
            set_flash('danger', "Lỗi thanh toán: " . $ex->getMessage());
            header('Location: pos.php');
            exit;
        }
    }
}

// Tìm kiếm sản phẩm
$keyword = trim($_GET['q'] ?? '');
if ($keyword !== '') {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE (name LIKE ? OR barcode LIKE ? OR code LIKE ?) AND status = 'in_stock' ORDER BY name LIMIT 10");
    $stmt->execute(["%$keyword%", "%$keyword%", "%$keyword%"]);
    $search_results = $stmt->fetchAll();
} else {
    $search_results = $pdo->query("SELECT * FROM products WHERE status = 'in_stock' ORDER BY name LIMIT 12")->fetchAll();
}

$cart_total = 0;
foreach ($_SESSION['cart'] as $item) {
    $cart_total += $item['price'] * $item['quantity'];
}

$page_title = "Quầy Bán Hàng POS";
require_once __DIR__ . '/includes/header.php';
?>

<div class="pos-grid">
    <!-- Cột trái: Quét mã vạch & Giỏ hàng -->
    <div>
        <div class="card">
            <h3 class="card-title">Quét Mã Vạch / Nhập Nhanh Sản Phẩm</h3>
            <form method="POST" action="pos.php" class="search-box">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="quick_barcode">
                <input type="text" name="barcode" class="form-control" autofocus placeholder="Quét barcode hoặc nhập mã SP rồi nhấn Enter..." required autocomplete="off">
                <button type="submit" class="btn btn-primary">Thêm</button>
            </form>

            <form method="GET" action="pos.php" class="search-box" style="margin-bottom: 0;">
                <input type="text" name="q" value="<?= e($keyword) ?>" class="form-control" placeholder="Tìm theo tên sản phẩm...">
                <button type="submit" class="btn btn-secondary">Tìm</button>
                <?php if ($keyword !== ''): ?>
                    <a href="pos.php" class="btn btn-outline">Hủy</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Danh sách tìm kiếm / gợi ý mặt hàng -->
        <div class="card">
            <h3 class="card-title">Mặt Hàng Có Thể Bán (Còn Tồn Quầy)</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 0.75rem; max-height: 240px; overflow-y: auto;">
                <?php foreach ($search_results as $p): ?>
                    <form method="POST" action="pos.php" style="background: #f8fafc; border: 1px solid var(--border); padding: 0.6rem; border-radius: 6px;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add_item">
                        <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                        <input type="hidden" name="quantity" value="1">
                        <div style="font-weight: 600; font-size: 0.85rem; height: 38px; overflow: hidden;"><?= e($p['name']) ?></div>
                        <div style="color: var(--primary); font-weight: 700; font-size: 0.9rem;"><?= format_money($p['price']) ?></div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 0.4rem;">Tồn: <?= (int)$p['stock_qty'] ?> | Mã: <?= e($p['code']) ?></div>
                        <button type="submit" class="btn btn-outline btn-sm" style="width: 100%;">+ Chọn</button>
                    </form>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Giỏ hàng tại quầy -->
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                <h3 class="card-title" style="margin: 0; border: none;">Giỏ Hàng Quầy (<?= count($_SESSION['cart']) ?> món)</h3>
                <?php if (!empty($_SESSION['cart'])): ?>
                    <form method="POST" action="pos.php" onsubmit="return confirm('Xóa toàn bộ giỏ hàng?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="clear_cart">
                        <button type="submit" class="btn btn-danger btn-sm">Làm mới giỏ</button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="table-responsive cart-items">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Mã / Tên SP</th>
                            <th>Đơn giá</th>
                            <th style="width: 140px; text-align: center;">Số lượng</th>
                            <th>Thành tiền</th>
                            <th style="text-align: center;">Xóa</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($_SESSION['cart'])): ?>
                            <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 2rem;">Giỏ hàng trống. Quét mã vạch hoặc chọn sản phẩm để bắt đầu.</td></tr>
                        <?php else: ?>
                            <?php foreach ($_SESSION['cart'] as $pid => $item): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($item['name']) ?></strong><br>
                                        <small style="color: var(--text-muted);"><?= e($item['code']) ?> | Tồn kho: <?= $item['stock_qty'] ?></small>
                                    </td>
                                    <td><?= format_money($item['price']) ?></td>
                                    <td style="text-align: center;">
                                        <form method="POST" action="pos.php" style="display: flex; align-items: center; gap: 4px; justify-content: center;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="update_qty">
                                            <input type="hidden" name="product_id" value="<?= $pid ?>">
                                            <input type="number" name="quantity" value="<?= $item['quantity'] ?>" min="1" max="<?= $item['stock_qty'] ?>" class="form-control" style="width: 65px; text-align: center; padding: 0.3rem;" onchange="this.form.submit()">
                                        </form>
                                    </td>
                                    <td style="font-weight: 700; color: var(--primary);"><?= format_money($item['price'] * $item['quantity']) ?></td>
                                    <td style="text-align: center;">
                                        <form method="POST" action="pos.php">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="remove_item">
                                            <input type="hidden" name="product_id" value="<?= $pid ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">✕</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Cột phải: Thanh toán -->
    <div>
        <div class="card" style="position: sticky; top: 1rem;">
            <h3 class="card-title">Thanh Toán Đơn Hàng</h3>

            <div class="cart-total-box">
                <div class="total-line">
                    <span>Số lượng món:</span>
                    <strong><?= count($_SESSION['cart']) ?></strong>
                </div>
                <div class="total-line total-grand">
                    <span>TỔNG TIỀN:</span>
                    <span id="display-total"><?= format_money($cart_total) ?></span>
                </div>
            </div>

            <form method="POST" action="pos.php" style="margin-top: 1rem;" id="checkout-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="checkout">

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label style="font-weight: 600; display: block; margin-bottom: 0.4rem;">Phương thức thanh toán:</label>
                    <div style="display: flex; gap: 0.5rem;">
                        <label style="flex: 1; border: 1px solid var(--border); padding: 0.5rem; border-radius: 6px; text-align: center; cursor: pointer;">
                            <input type="radio" name="payment_method" value="cash" checked onchange="togglePayment(this.value)"> Tiền mặt
                        </label>
                        <label style="flex: 1; border: 1px solid var(--border); padding: 0.5rem; border-radius: 6px; text-align: center; cursor: pointer;">
                            <input type="radio" name="payment_method" value="card" onchange="togglePayment(this.value)"> Thẻ POS
                        </label>
                        <label style="flex: 1; border: 1px solid var(--border); padding: 0.5rem; border-radius: 6px; text-align: center; cursor: pointer;">
                            <input type="radio" name="payment_method" value="qr" onchange="togglePayment(this.value)"> Quét QR
                        </label>
                    </div>
                </div>

                <div id="cash-section">
                    <div class="form-group">
                        <label for="amount_paid" style="font-weight: 600;">Tiền khách đưa (VNĐ):</label>
                        <input type="number" id="amount_paid" name="amount_paid" class="form-control" value="<?= $cart_total ?>" min="<?= $cart_total ?>" step="1000" oninput="calculateChange()">
                    </div>

                    <div class="quick-amount">
                        <button type="button" class="btn btn-outline btn-sm" onclick="setPaid(<?= $cart_total ?>)">Đủ tiền</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="setPaid(50000)">50.000</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="setPaid(100000)">100.000</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="setPaid(200000)">200.000</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="setPaid(500000)">500.000</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="setPaid(1000000)">1.000.000</button>
                    </div>

                    <div style="background: #e2e8f0; border-radius: 6px; padding: 0.75rem; margin-top: 0.75rem;">
                        <div class="total-line" style="margin-bottom: 0;">
                            <span style="font-weight: 600;">Tiền thừa thối lại:</span>
                            <strong id="display-change" style="color: var(--success); font-size: 1.15rem;">0 ₫</strong>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-success" style="width: 100%; margin-top: 1.25rem; padding: 0.85rem; font-size: 1.1rem;" <?= empty($_SESSION['cart']) ? 'disabled' : '' ?>>
                    ✓ THANH TOÁN & IN HÓA ĐƠN
                </button>
            </form>
        </div>
    </div>
</div>

<script>
const grandTotal = <?= $cart_total ?>;

function setPaid(val) {
    document.getElementById('amount_paid').value = val;
    calculateChange();
}

function calculateChange() {
    const paid = parseFloat(document.getElementById('amount_paid').value) || 0;
    const change = Math.max(0, paid - grandTotal);
    document.getElementById('display-change').innerText = new Intl.NumberFormat('vi-VN').format(change) + ' ₫';
}

function togglePayment(method) {
    const cashSec = document.getElementById('cash-section');
    if (method === 'cash') {
        cashSec.style.display = 'block';
    } else {
        cashSec.style.display = 'none';
        document.getElementById('amount_paid').value = grandTotal;
        calculateChange();
    }
}
calculateChange();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
