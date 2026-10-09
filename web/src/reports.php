<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_manager();

$pdo = get_db_connection();

$period = $_GET['period'] ?? 'today';
$where_clause = "";
if ($period === 'today') {
    $where_clause = "WHERE DATE(created_at) = CURDATE()";
    $period_label = "Hôm nay (" . date('d/m/Y') . ")";
} elseif ($period === 'week') {
    $where_clause = "WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)";
    $period_label = "Tuần này";
} elseif ($period === 'month') {
    $where_clause = "WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())";
    $period_label = "Tháng này (" . date('m/Y') . ")";
} else {
    $period_label = "Tất cả thời gian";
}

// 1. Thống kê tổng quan
$stats_sql = "SELECT COUNT(*) as total_orders, COALESCE(SUM(total_amount), 0) as total_revenue, COALESCE(AVG(total_amount), 0) as avg_order FROM orders $where_clause";
$stats = $pdo->query($stats_sql)->fetch();

// 2. Top sản phẩm bán chạy
$top_sql = "
    SELECT p.code, p.name, SUM(od.quantity) as sold_qty, SUM(od.quantity * od.unit_price) as item_revenue
    FROM order_details od
    JOIN orders o ON od.order_id = o.id
    JOIN products p ON od.product_id = p.id
    $where_clause
    GROUP BY p.id, p.code, p.name
    ORDER BY sold_qty DESC
    LIMIT 10
";
$top_products = $pdo->query($top_sql)->fetchAll();

// 3. Danh sách hóa đơn gần đây
$orders_sql = "
    SELECT o.*, u.full_name as cashier_name
    FROM orders o
    JOIN users u ON o.user_id = u.id
    $where_clause
    ORDER BY o.id DESC
    LIMIT 20
";
$orders = $pdo->query($orders_sql)->fetchAll();

$page_title = "Báo Cáo Doanh Thu";
require_once __DIR__ . '/includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
    <h2>Báo Cáo Doanh Thu & Hiệu Quả Bán Hàng</h2>
    <div style="display: flex; gap: 0.5rem;">
        <a href="reports.php?period=today" class="btn <?= $period === 'today' ? 'btn-primary' : 'btn-outline' ?>">Hôm nay</a>
        <a href="reports.php?period=week" class="btn <?= $period === 'week' ? 'btn-primary' : 'btn-outline' ?>">Tuần này</a>
        <a href="reports.php?period=month" class="btn <?= $period === 'month' ? 'btn-primary' : 'btn-outline' ?>">Tháng này</a>
        <a href="reports.php?period=all" class="btn <?= $period === 'all' ? 'btn-primary' : 'btn-outline' ?>">Tất cả</a>
    </div>
</div>

<!-- Stat cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
    <div class="card" style="border-left: 4px solid var(--primary);">
        <div style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">TỔNG DOANH THU (<?= e($period_label) ?>)</div>
        <div style="font-size: 1.8rem; font-weight: 800; color: var(--primary); margin-top: 0.4rem;">
            <?= format_money($stats['total_revenue']) ?>
        </div>
    </div>
    <div class="card" style="border-left: 4px solid var(--success);">
        <div style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">TỔNG SỐ HÓA ĐƠN</div>
        <div style="font-size: 1.8rem; font-weight: 800; color: var(--success); margin-top: 0.4rem;">
            <?= number_format($stats['total_orders']) ?> đơn
        </div>
    </div>
    <div class="card" style="border-left: 4px solid var(--warning);">
        <div style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">GIÁ TRỊ ĐƠN TRUNG BÌNH (AOV)</div>
        <div style="font-size: 1.8rem; font-weight: 800; color: var(--warning); margin-top: 0.4rem;">
            <?= format_money($stats['avg_order']) ?>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1.3fr; gap: 1.25rem;">
    <!-- Top mặt hàng bán chạy -->
    <div class="card">
        <h3 class="card-title">Top Mặt Hàng Bán Chạy Nhất</h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Sản phẩm</th>
                        <th style="text-align: center;">SL Đã bán</th>
                        <th style="text-align: right;">Doanh thu</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($top_products)): ?>
                        <tr><td colspan="3" style="text-align: center; color: var(--text-muted); padding: 1.5rem;">Chưa có dữ liệu bán hàng trong kỳ này.</td></tr>
                    <?php else: ?>
                        <?php foreach ($top_products as $idx => $tp): ?>
                            <tr>
                                <td>
                                    <strong>#<?= $idx + 1 ?> <?= e($tp['name']) ?></strong><br>
                                    <small style="color: var(--text-muted);"><?= e($tp['code']) ?></small>
                                </td>
                                <td style="text-align: center; font-weight: 700; color: var(--success);"><?= (int)$tp['sold_qty'] ?></td>
                                <td style="text-align: right; font-weight: 600;"><?= format_money($tp['item_revenue']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Hóa đơn gần đây -->
    <div class="card">
        <h3 class="card-title">Hóa Đơn Bán Gần Đây</h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Mã HĐ</th>
                        <th>Thu ngân</th>
                        <th>Thời gian</th>
                        <th>PT</th>
                        <th style="text-align: right;">Tổng tiền</th>
                        <th style="text-align: center;">Xem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 1.5rem;">Không có đơn hàng nào trong kỳ.</td></tr>
                    <?php else: ?>
                        <?php foreach ($orders as $o): ?>
                            <tr>
                                <td><code><?= e($o['order_code']) ?></code></td>
                                <td><?= e($o['cashier_name']) ?></td>
                                <td><?= date('d/m H:i', strtotime($o['created_at'])) ?></td>
                                <td>
                                    <span class="badge <?= $o['payment_method'] === 'cash' ? 'badge-manager' : 'badge-staff' ?>">
                                        <?= strtoupper($o['payment_method']) ?>
                                    </span>
                                </td>
                                <td style="text-align: right; font-weight: 700;"><?= format_money($o['total_amount']) ?></td>
                                <td style="text-align: center;">
                                    <a href="invoice.php?order_code=<?= urlencode($o['order_code']) ?>" class="btn btn-outline btn-sm">Chi tiết</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
