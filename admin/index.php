<?php
$page_title = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

// ── Metrics ──
$metrics = [];

// Total users (non-admin)
$metrics['users'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role != 'admin'")->fetchColumn();

// Total orders
$metrics['orders'] = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();

// Total revenue
$metrics['revenue'] = (float) $pdo->query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE status != 'cancelled'")->fetchColumn();

// Active listings
$metrics['listings'] = $pdo->query("SELECT COUNT(*) FROM products WHERE is_active = 1")->fetchColumn();

// Pending reviews (unapproved)
$metrics['reviews'] = $pdo->query("SELECT COUNT(*) FROM reviews WHERE is_approved = 0")->fetchColumn();

// Open feedback tickets
$metrics['feedback'] = $pdo->query("SELECT COUNT(*) FROM feedback_messages WHERE status = 'open'")->fetchColumn();

// Active promo codes
$metrics['promos'] = $pdo->query("SELECT COUNT(*) FROM promo_codes WHERE is_active = 1 AND expires_at >= CURDATE()")->fetchColumn();

// Low stock alerts (stock <= 5, active)
$metrics['low_stock'] = $pdo->query("SELECT COUNT(*) FROM products WHERE stock_qty <= 5 AND is_active = 1")->fetchColumn();

// Recent orders (last 5)
$recent_orders = $pdo->query("
    SELECT o.order_number, o.total, o.status, o.created_at,
           u.first_name, u.last_name
    FROM orders o
    JOIN users u ON u.id = o.user_id
    ORDER BY o.created_at DESC
    LIMIT 5
")->fetchAll();

// Top products by views
$top_products = $pdo->query("
    SELECT p.name, p.price, p.stock_qty, p.review_count,
           COUNT(ua.id) AS views
    FROM products p
    LEFT JOIN user_activity ua ON ua.target_id = p.id AND ua.action = 'product_view'
    GROUP BY p.id
    ORDER BY views DESC
    LIMIT 5
")->fetchAll();

// Revenue by status
$status_breakdown = $pdo->query("
    SELECT status, COUNT(*) AS cnt, COALESCE(SUM(total), 0) AS rev
    FROM orders
    GROUP BY status
    ORDER BY cnt DESC
")->fetchAll();

require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="metrics-grid">
    <div class="metric-card accent">
        <div class="metric-label">Total Users</div>
        <div class="metric-value"><?= number_format($metrics['users']) ?></div>
    </div>
    <div class="metric-card accent">
        <div class="metric-label">Total Orders</div>
        <div class="metric-value"><?= number_format($metrics['orders']) ?></div>
    </div>
    <div class="metric-card accent">
        <div class="metric-label">Revenue</div>
        <div class="metric-value">₱<?= number_format($metrics['revenue'], 0) ?></div>
    </div>
    <div class="metric-card">
        <div class="metric-label">Active Listings</div>
        <div class="metric-value"><?= number_format($metrics['listings']) ?></div>
    </div>
    <div class="metric-card <?= $metrics['reviews'] > 0 ? 'warn' : '' ?>">
        <div class="metric-label">Pending Reviews</div>
        <div class="metric-value"><?= $metrics['reviews'] ?></div>
    </div>
    <div class="metric-card <?= $metrics['feedback'] > 0 ? 'warn' : '' ?>">
        <div class="metric-label">Open Feedback</div>
        <div class="metric-value"><?= $metrics['feedback'] ?></div>
    </div>
    <div class="metric-card">
        <div class="metric-label">Active Promos</div>
        <div class="metric-value"><?= $metrics['promos'] ?></div>
    </div>
    <div class="metric-card <?= $metrics['low_stock'] > 0 ? 'danger' : '' ?>">
        <div class="metric-label">Low Stock Items</div>
        <div class="metric-value"><?= $metrics['low_stock'] ?></div>
        <?php if ($metrics['low_stock'] > 0): ?>
        <div class="metric-sub"><a href="/hobbyloop/admin/inventory.php?filter=low">View →</a></div>
        <?php endif; ?>
    </div>
</div>

<div class="two-col">
    <div class="card">
        <div class="card-header">Recent Orders</div>
        <div class="table-wrap">
            <table>
                <thead><tr>
                    <th>Order #</th><th>Customer</th><th>Total</th><th>Status</th><th>Date</th>
                </tr></thead>
                <tbody>
                <?php if (empty($recent_orders)): ?>
                    <tr><td colspan="5" class="text-center text-muted" style="padding:20px">No orders yet</td></tr>
                <?php else: ?>
                <?php foreach ($recent_orders as $o): ?>
                <tr>
                    <td><a href="/hobbyloop/admin/orders.php?id=<?= $o['order_number'] ?>"><?= htmlspecialchars($o['order_number']) ?></a></td>
                    <td><?= htmlspecialchars($o['first_name'] . ' ' . $o['last_name']) ?></td>
                    <td>₱<?= number_format($o['total'], 2) ?></td>
                    <td><?php
                        $sc = ['pending'=>'badge-orange','processing'=>'badge-blue','shipped'=>'badge-teal','delivered'=>'badge-green','cancelled'=>'badge-red'];
                        echo '<span class="badge ' . ($sc[$o['status']] ?? 'badge-gray') . '">' . htmlspecialchars($o['status']) . '</span>';
                    ?></td>
                    <td class="text-muted"><?= date('M j, Y', strtotime($o['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Order Status Breakdown</div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Status</th><th>Count</th><th>Revenue</th></tr></thead>
                <tbody>
                <?php foreach ($status_breakdown as $s): ?>
                <tr>
                    <td><?= htmlspecialchars(ucfirst($s['status'])) ?></td>
                    <td><?= $s['cnt'] ?></td>
                    <td>₱<?= number_format($s['rev'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card-header" style="margin-top:1px">Top Products by Views</div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Product</th><th>Views</th><th>Stock</th></tr></thead>
                <tbody>
                <?php foreach ($top_products as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['name']) ?></td>
                    <td><?= $p['views'] ?></td>
                    <td class="<?= $p['stock_qty'] <= 5 ? 'low-stock' : '' ?>"><?= $p['stock_qty'] ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
