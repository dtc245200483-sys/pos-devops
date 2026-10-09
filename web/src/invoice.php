<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_login();

$order_code = trim($_GET['order_code'] ?? '');
if ($order_code === '') {
    set_flash('danger', 'Mã hóa đơn không hợp lệ.');
    header('Location: pos.php');
    exit;
}

$pdo = get_db_connection();
$stmt = $pdo->prepare("SELECT o.*, u.full_name as cashier_name FROM orders o JOIN users u ON o.user_id = u.id WHERE o.order_code = ?");
$stmt->execute([$order_code]);
$order = $stmt->fetch();

if (!$order) {
    set_flash('danger', "Không tìm thấy hóa đơn {$order_code}.");
    header('Location: pos.php');
    exit;
}

$det_stmt = $pdo->prepare("SELECT od.*, p.name as product_name, p.code as product_code FROM order_details od JOIN products p ON od.product_id = p.id WHERE od.order_id = ?");
$det_stmt->execute([$order['id']]);
$details = $det_stmt->fetchAll();

$page_title = "Hóa Đơn " . $order['order_code'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="invoice-card">
    <div class="invoice-header">
        <h2 style="font-size: 1.4rem; color: var(--primary);">CỬA HÀNG BÁN LẺ POS</h2>
        <p style="font-size: 0.85rem; color: var(--text-muted);">Đ/C: Quầy Thu Ngân - Trường ĐH CNTT & TT</p>
        <p style="font-size: 0.85rem; color: var(--text-muted);">Hotline: 1900-1234</p>
        <h3 style="margin-top: 0.75rem; font-size: 1.1rem; text-transform: uppercase;">HÓA ĐƠN BÁN HÀNG</h3>
        <p style="font-size: 0.85rem;"><strong>Mã HĐ:</strong> <?= e($order['order_code']) ?></p>
        <p style="font-size: 0.85rem;"><strong>Thời gian:</strong> <?= date('d/m/Y H:i:s', strtotime($order['created_at'])) ?></p>
        <p style="font-size: 0.85rem;"><strong>Thu ngân:</strong> <?= e($order['cashier_name']) ?></p>
    </div>

    <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem; margin-bottom: 1rem;">
        <thead>
            <tr style="border-bottom: 1px solid #cbd5e1; text-align: left;">
                <th style="padding: 4px 0;">Tên món</th>
                <th style="padding: 4px; text-align: center;">SL</th>
                <th style="padding: 4px; text-align: right;">Đơn giá</th>
                <th style="padding: 4px 0; text-align: right;">T.Tiền</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details as $d): ?>
                <tr style="border-bottom: 1px dotted #e2e8f0;">
                    <td style="padding: 6px 0;">
                        <?= e($d['product_name']) ?><br>
                        <small style="color: var(--text-muted);"><?= e($d['product_code']) ?></small>
                    </td>
                    <td style="padding: 6px; text-align: center;"><?= (int)$d['quantity'] ?></td>
                    <td style="padding: 6px; text-align: right;"><?= format_money($d['unit_price']) ?></td>
                    <td style="padding: 6px 0; text-align: right; font-weight: 600;"><?= format_money($d['unit_price'] * $d['quantity']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div style="border-top: 1px dashed #94a3b8; padding-top: 0.75rem; font-size: 0.9rem;">
        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
            <span>Tổng cộng:</span>
            <strong style="font-size: 1.1rem; color: var(--primary);"><?= format_money($order['total_amount']) ?></strong>
        </div>
        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
            <span>Hình thức TT:</span>
            <span><?= $order['payment_method'] === 'cash' ? 'Tiền mặt' : ($order['payment_method'] === 'card' ? 'Thẻ ngân hàng (Quẹt thẻ)' : 'Quét mã QR') ?></span>
        </div>
        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
            <span>Tiền khách đưa:</span>
            <span><?= format_money($order['amount_paid']) ?></span>
        </div>
        <div style="display: flex; justify-content: space-between;">
            <span>Tiền thừa thối:</span>
            <strong style="color: var(--success);"><?= format_money($order['change_amount']) ?></strong>
        </div>
    </div>

    <div class="invoice-footer">
        <p>Cảm ơn quý khách và hẹn gặp lại!</p>
        <p style="font-style: italic; font-size: 0.75rem;">(Hóa đơn điện tử khởi tạo từ hệ thống POS Topic 21)</p>
    </div>

    <div class="no-print" style="margin-top: 1.5rem; display: flex; gap: 0.5rem; justify-content: center;">
        <button onclick="window.print()" class="btn btn-primary">🖨️ In Hóa Đơn</button>
        <a href="pos.php" class="btn btn-secondary">← Bán đơn mới</a>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
