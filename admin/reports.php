<?php
$page_title = 'Reports';
require_once __DIR__ . '/includes/header.php';

// ── Sales Summary ──
$sales_summary = $pdo->query("
    SELECT
        COUNT(*) AS total_orders,
        COALESCE(SUM(total), 0) AS total_revenue,
        COALESCE(AVG(total), 0) AS avg_order_value
    FROM orders
    WHERE status != 'cancelled'
")->fetch();

// ── Orders by Status ──
$orders_by_status = $pdo->query("
    SELECT status,
           COUNT(*) AS order_count,
           COALESCE(SUM(total), 0) AS status_revenue
    FROM orders
    GROUP BY status
    ORDER BY FIELD(status, 'processing', 'shipped', 'delivered', 'cancelled')
")->fetchAll();

// ── Recent Orders (last 20) ──
$recent_orders = $pdo->query("
    SELECT o.order_number, o.total, o.status, o.created_at,
           CONCAT(u.first_name, ' ', u.last_name) AS customer_name
    FROM orders o
    JOIN users u ON u.id = o.user_id
    ORDER BY o.created_at DESC
    LIMIT 20
")->fetchAll();

// ── Revenue by Category ──
$cat_revenue_total = $pdo->query("
    SELECT COALESCE(SUM(oi.price * oi.qty), 0) AS grand_total
    FROM order_items oi
    JOIN orders o ON o.id = oi.order_id AND o.status != 'cancelled'
")->fetchColumn();

$cat_revenue = $pdo->query("
    SELECT c.label,
           COALESCE(SUM(oi.qty), 0) AS items_sold,
           COALESCE(SUM(oi.price * oi.qty), 0) AS revenue
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id
    LEFT JOIN order_items oi ON oi.product_id = p.id
    LEFT JOIN orders o ON o.id = oi.order_id AND o.status != 'cancelled'
    GROUP BY c.id, c.label
    ORDER BY revenue DESC
")->fetchAll();

// ── Revenue by Seller ──
$seller_revenue = $pdo->query("
    SELECT s.shop_name,
           CONCAT(u.first_name, ' ', u.last_name) AS seller_name,
           COALESCE(SUM(oi.qty), 0) AS items_sold,
           COALESCE(SUM(oi.price * oi.qty), 0) AS revenue,
           ROUND(AVG(r.rating), 2) AS avg_rating
    FROM sellers s
    JOIN users u ON u.id = s.user_id
    LEFT JOIN products p ON p.seller_id = s.id
    LEFT JOIN order_items oi ON oi.product_id = p.id
    LEFT JOIN orders o ON o.id = oi.order_id AND o.status != 'cancelled'
    LEFT JOIN reviews r ON r.product_id = p.id AND r.is_approved = 1
    GROUP BY s.id, s.shop_name, u.first_name, u.last_name
    ORDER BY revenue DESC
")->fetchAll();

// ── Inventory: Products Below Reorder Level ──
$low_stock = $pdo->query("
    SELECT p.name AS product_name,
           p.stock_qty,
           ps.reorder_level,
           sup.name AS supplier_name,
           sup.lead_time_days
    FROM products p
    JOIN product_suppliers ps ON ps.product_id = p.id
    JOIN suppliers sup ON sup.id = ps.supplier_id
    WHERE p.stock_qty <= ps.reorder_level
    ORDER BY p.stock_qty ASC
")->fetchAll();

// ── Total Inventory Value ──
$inventory_value = $pdo->query("
    SELECT COALESCE(SUM(price * stock_qty), 0) AS total_value
    FROM products
    WHERE is_active = 1
")->fetchColumn();

// ── Promo Campaign Performance ──
$promo_perf = $pdo->query("
    SELECT pc.code, pc.discount_type, pc.discount_value,
           COUNT(pu.id) AS times_used,
           COALESCE(SUM(pu.discount_applied), 0) AS total_discount,
           COALESCE(SUM(o.total), 0) AS promo_revenue
    FROM promo_codes pc
    LEFT JOIN promo_usage pu ON pu.promo_id = pc.id
    LEFT JOIN orders o ON o.id = pu.order_id AND o.status != 'cancelled'
    GROUP BY pc.id, pc.code, pc.discount_type, pc.discount_value
    ORDER BY times_used DESC
")->fetchAll();

$status_badge = [
    'processing' => 'badge-yellow',
    'shipped'    => 'badge-blue',
    'delivered'  => 'badge-green',
    'cancelled'  => 'badge-red',
];

require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- ── Sales Summary ── -->
<div class="three-col" style="margin-bottom:20px">
    <div class="card mb-0">
        <div class="card-header">Total Orders</div>
        <div style="padding:20px 24px">
            <div style="font-size:2rem;font-weight:700;color:var(--ink)"><?= number_format($sales_summary['total_orders']) ?></div>
            <div class="text-muted" style="font-size:.875rem">Excluding cancelled</div>
        </div>
    </div>
    <div class="card mb-0">
        <div class="card-header">Total Revenue</div>
        <div style="padding:20px 24px">
            <div style="font-size:2rem;font-weight:700;color:var(--green)">₱<?= number_format($sales_summary['total_revenue'], 2) ?></div>
            <div class="text-muted" style="font-size:.875rem">Excluding cancelled orders</div>
        </div>
    </div>
    <div class="card mb-0">
        <div class="card-header">Average Order Value</div>
        <div style="padding:20px 24px">
            <div style="font-size:2rem;font-weight:700;color:var(--ink)">₱<?= number_format($sales_summary['avg_order_value'], 2) ?></div>
            <div class="text-muted" style="font-size:.875rem">Per non-cancelled order</div>
        </div>
    </div>
</div>

<!-- ── Orders by Status ── -->
<div class="two-col">
    <div class="card">
        <div class="card-header">Orders by Status</div>
        <div class="table-wrap">
            <table>
                <thead><tr>
                    <th>Status</th><th>Count</th><th>Revenue</th>
                </tr></thead>
                <tbody>
                <?php if (empty($orders_by_status)): ?>
                    <tr><td colspan="3" class="text-center text-muted" style="padding:16px">No orders yet.</td></tr>
                <?php else: ?>
                <?php foreach ($orders_by_status as $s): ?>
                <tr>
                    <td>
                        <span class="badge <?= $status_badge[$s['status']] ?? 'badge-gray' ?>">
                            <?= ucfirst(htmlspecialchars($s['status'])) ?>
                        </span>
                    </td>
                    <td><?= number_format($s['order_count']) ?></td>
                    <td><?= $s['status'] !== 'cancelled' ? '₱' . number_format($s['status_revenue'], 2) : '—' ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ── Inventory Value Summary ── -->
    <div class="card">
        <div class="card-header">Inventory Value</div>
        <div style="padding:20px 24px">
            <div style="font-size:2rem;font-weight:700;color:var(--ink)">₱<?= number_format($inventory_value, 2) ?></div>
            <div class="text-muted" style="font-size:.875rem;margin-top:4px">Total value of active product stock (price × qty)</div>
        </div>
        <?php if (!empty($low_stock)): ?>
        <div style="padding:0 24px 20px">
            <span class="badge badge-red"><?= count($low_stock) ?> product<?= count($low_stock) !== 1 ? 's' : '' ?> below reorder level</span>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── Recent Orders ── -->
<div class="card">
    <div class="card-header">Recent Orders (Last 20)</div>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>Order #</th><th>Customer</th><th>Date</th><th>Total</th><th>Status</th>
            </tr></thead>
            <tbody>
            <?php if (empty($recent_orders)): ?>
                <tr><td colspan="5" class="text-center text-muted" style="padding:16px">No orders yet.</td></tr>
            <?php else: ?>
            <?php foreach ($recent_orders as $o): ?>
            <tr>
                <td><strong><?= htmlspecialchars($o['order_number']) ?></strong></td>
                <td><?= htmlspecialchars($o['customer_name']) ?></td>
                <td class="text-muted"><?= date('M j, Y', strtotime($o['created_at'])) ?></td>
                <td>₱<?= number_format($o['total'], 2) ?></td>
                <td>
                    <span class="badge <?= $status_badge[$o['status']] ?? 'badge-gray' ?>">
                        <?= ucfirst(htmlspecialchars($o['status'])) ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Revenue by Category ── -->
<div class="card">
    <div class="card-header">Revenue by Category</div>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>Category</th><th>Items Sold</th><th>Revenue</th><th>% of Total</th>
            </tr></thead>
            <tbody>
            <?php if (empty($cat_revenue)): ?>
                <tr><td colspan="4" class="text-center text-muted" style="padding:16px">No sales data yet.</td></tr>
            <?php else: ?>
            <?php foreach ($cat_revenue as $c): ?>
            <?php $pct = $cat_revenue_total > 0 ? ($c['revenue'] / $cat_revenue_total * 100) : 0; ?>
            <tr>
                <td><?= htmlspecialchars($c['label']) ?></td>
                <td><?= number_format($c['items_sold']) ?></td>
                <td>₱<?= number_format($c['revenue'], 2) ?></td>
                <td>
                    <div style="display:flex;align-items:center;gap:8px">
                        <div style="flex:1;background:var(--border);border-radius:4px;height:6px;max-width:80px">
                            <div style="width:<?= min(100, round($pct)) ?>%;background:var(--teal);height:100%;border-radius:4px"></div>
                        </div>
                        <span class="text-muted" style="font-size:.8rem"><?= number_format($pct, 1) ?>%</span>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Revenue by Seller ── -->
<div class="card">
    <div class="card-header">Revenue by Seller</div>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>Shop</th><th>Seller</th><th>Items Sold</th><th>Revenue</th><th>Avg Rating</th>
            </tr></thead>
            <tbody>
            <?php if (empty($seller_revenue)): ?>
                <tr><td colspan="5" class="text-center text-muted" style="padding:16px">No seller data yet.</td></tr>
            <?php else: ?>
            <?php foreach ($seller_revenue as $s): ?>
            <tr>
                <td><strong><?= htmlspecialchars($s['shop_name']) ?></strong></td>
                <td class="text-muted"><?= htmlspecialchars($s['seller_name']) ?></td>
                <td><?= number_format($s['items_sold']) ?></td>
                <td>₱<?= number_format($s['revenue'], 2) ?></td>
                <td>
                    <?php if ($s['avg_rating']): ?>
                        <?php $stars = round($s['avg_rating']); ?>
                        <?= str_repeat('★', $stars) ?><?= str_repeat('☆', 5 - $stars) ?>
                        <span class="text-muted">(<?= $s['avg_rating'] ?>)</span>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Inventory: Below Reorder Level ── -->
<div class="card">
    <div class="card-header">Products Below Reorder Level</div>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>Product</th><th>Current Stock</th><th>Reorder Level</th><th>Supplier</th><th>Lead Time (days)</th>
            </tr></thead>
            <tbody>
            <?php if (empty($low_stock)): ?>
                <tr><td colspan="5" class="text-center text-muted" style="padding:16px">All products are adequately stocked.</td></tr>
            <?php else: ?>
            <?php foreach ($low_stock as $item): ?>
            <tr>
                <td><?= htmlspecialchars($item['product_name']) ?></td>
                <td style="color:var(--red);font-weight:600"><?= number_format($item['stock_qty']) ?></td>
                <td class="text-muted"><?= number_format($item['reorder_level']) ?></td>
                <td><?= htmlspecialchars($item['supplier_name']) ?></td>
                <td><?= $item['lead_time_days'] ?> day<?= $item['lead_time_days'] != 1 ? 's' : '' ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Promo Campaign Performance ── -->
<div class="card">
    <div class="card-header">Promo Campaign Performance</div>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>Code</th><th>Type</th><th>Discount Value</th><th>Times Used</th>
                <th>Total Discount Given</th><th>Revenue from Promo Orders</th>
            </tr></thead>
            <tbody>
            <?php if (empty($promo_perf)): ?>
                <tr><td colspan="6" class="text-center text-muted" style="padding:16px">No promo data yet.</td></tr>
            <?php else: ?>
            <?php foreach ($promo_perf as $p): ?>
            <tr>
                <td><strong><?= htmlspecialchars($p['code']) ?></strong></td>
                <td><?= htmlspecialchars($p['discount_type']) ?></td>
                <td><?= $p['discount_type'] === 'percent' ? $p['discount_value'] . '%' : '₱' . number_format($p['discount_value'], 2) ?></td>
                <td><?= number_format($p['times_used']) ?></td>
                <td style="color:var(--red)">₱<?= number_format($p['total_discount'], 2) ?></td>
                <td style="color:var(--green)">₱<?= number_format($p['promo_revenue'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
