<?php
$page_title = 'Analytics';
require_once __DIR__ . '/includes/header.php';

// ── Top viewed products ──
$top_views = $pdo->query("
    SELECT p.id, p.name, p.price, c.label AS cat_label,
           COUNT(ua.id) AS view_count
    FROM products p
    LEFT JOIN user_activity ua ON ua.target_id = p.id AND ua.action = 'product_view'
    JOIN categories c ON c.id = p.category_id
    GROUP BY p.id
    ORDER BY view_count DESC
    LIMIT 10
")->fetchAll();

// ── Top search queries ──
$top_searches = $pdo->query("
    SELECT search_query, COUNT(*) AS cnt
    FROM user_activity
    WHERE action = 'search' AND search_query IS NOT NULL AND search_query != ''
    GROUP BY search_query
    ORDER BY cnt DESC
    LIMIT 15
")->fetchAll();

// ── Revenue by category ──
$cat_revenue = $pdo->query("
    SELECT c.label, COUNT(DISTINCT o.id) AS order_count,
           COALESCE(SUM(oi.price * oi.qty), 0) AS revenue
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id
    LEFT JOIN order_items oi ON oi.product_id = p.id
    LEFT JOIN orders o ON o.id = oi.order_id AND o.status != 'cancelled'
    GROUP BY c.id
    ORDER BY revenue DESC
")->fetchAll();

// ── Revenue trend (last 8 weeks) ──
$weekly_revenue = $pdo->query("
    SELECT YEARWEEK(created_at, 1) AS yw,
           MIN(DATE(created_at)) AS week_start,
           COUNT(*) AS orders,
           COALESCE(SUM(total), 0) AS revenue
    FROM orders
    WHERE status != 'cancelled' AND created_at >= DATE_SUB(NOW(), INTERVAL 8 WEEK)
    GROUP BY yw
    ORDER BY yw ASC
")->fetchAll();

// ── Promo campaign performance ──
$promo_perf = $pdo->query("
    SELECT pc.code, pc.discount_type, pc.discount_value,
           pc.max_uses, pc.used_count, pc.expires_at, pc.is_active,
           COUNT(pu.id) AS usage_count,
           COALESCE(SUM(pu.discount_applied), 0) AS total_discount,
           COALESCE(SUM(o.total), 0) AS total_revenue
    FROM promo_codes pc
    LEFT JOIN promo_usage pu ON pu.promo_id = pc.id
    LEFT JOIN orders o ON o.id = pu.order_id AND o.status != 'cancelled'
    GROUP BY pc.id
    ORDER BY usage_count DESC
")->fetchAll();

// ── Review trends (by month) ──
$review_trends = $pdo->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS month,
           COUNT(*) AS total,
           SUM(CASE WHEN is_approved = 1 THEN 1 ELSE 0 END) AS approved,
           ROUND(AVG(rating), 2) AS avg_rating
    FROM reviews
    GROUP BY month
    ORDER BY month DESC
    LIMIT 6
")->fetchAll();

// ── Activity summary ──
$activity_summary = $pdo->query("
    SELECT action, COUNT(*) AS cnt
    FROM user_activity
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY action
    ORDER BY cnt DESC
")->fetchAll();

require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="three-col" style="margin-bottom:20px">
    <!-- Top Products -->
    <div class="card mb-0">
        <div class="card-header">Top Products by Views</div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>#</th><th>Product</th><th>Category</th><th>Views</th></tr></thead>
                <tbody>
                <?php if (empty($top_views)): ?>
                    <tr><td colspan="4" class="text-center text-muted" style="padding:16px">No data yet.</td></tr>
                <?php else: ?>
                <?php foreach ($top_views as $i => $p): ?>
                <tr>
                    <td class="text-muted"><?= $i+1 ?></td>
                    <td><?= htmlspecialchars($p['name']) ?></td>
                    <td class="text-muted"><?= htmlspecialchars($p['cat_label']) ?></td>
                    <td><?= number_format($p['view_count']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Top Searches -->
    <div class="card mb-0">
        <div class="card-header">Top Search Queries (30d)</div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Query</th><th>Count</th></tr></thead>
                <tbody>
                <?php if (empty($top_searches)): ?>
                    <tr><td colspan="2" class="text-center text-muted" style="padding:16px">No search data yet.</td></tr>
                <?php else: ?>
                <?php foreach ($top_searches as $s): ?>
                <tr>
                    <td><?= htmlspecialchars($s['search_query']) ?></td>
                    <td><?= $s['cnt'] ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Activity Summary -->
    <div class="card mb-0">
        <div class="card-header">Activity (Last 30 Days)</div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Action</th><th>Count</th></tr></thead>
                <tbody>
                <?php if (empty($activity_summary)): ?>
                    <tr><td colspan="2" class="text-center text-muted" style="padding:16px">No activity data yet.</td></tr>
                <?php else: ?>
                <?php foreach ($activity_summary as $a): ?>
                <tr>
                    <td><?= htmlspecialchars(str_replace('_', ' ', $a['action'])) ?></td>
                    <td><?= number_format($a['cnt']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="two-col">
    <!-- Revenue by Category -->
    <div class="card">
        <div class="card-header">Revenue by Category</div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Category</th><th>Orders</th><th>Revenue</th></tr></thead>
                <tbody>
                <?php foreach ($cat_revenue as $c): ?>
                <tr>
                    <td><?= htmlspecialchars($c['label']) ?></td>
                    <td><?= number_format($c['order_count']) ?></td>
                    <td>₱<?= number_format($c['revenue'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Weekly Revenue Trend -->
    <div class="card">
        <div class="card-header">Weekly Revenue Trend (Last 8 Weeks)</div>
        <?php if (empty($weekly_revenue)): ?>
            <div class="card-body text-muted">No revenue data yet.</div>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Week</th><th>Orders</th><th>Revenue</th></tr></thead>
                <tbody>
                <?php foreach ($weekly_revenue as $w): ?>
                <tr>
                    <td><?= date('M j', strtotime($w['week_start'])) ?></td>
                    <td><?= $w['orders'] ?></td>
                    <td>₱<?= number_format($w['revenue'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Promo Campaign Performance -->
<div class="card">
    <div class="card-header">Promo Campaign Performance</div>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>Code</th><th>Type</th><th>Value</th><th>Usage</th>
                <th>Max Uses</th><th>Total Discount</th><th>Total Revenue</th><th>Expires</th><th>Status</th>
            </tr></thead>
            <tbody>
            <?php if (empty($promo_perf)): ?>
                <tr><td colspan="9" class="text-center text-muted" style="padding:16px">No promo data.</td></tr>
            <?php else: ?>
            <?php foreach ($promo_perf as $p): ?>
            <tr>
                <td><strong><?= htmlspecialchars($p['code']) ?></strong></td>
                <td><?= htmlspecialchars($p['discount_type']) ?></td>
                <td><?= $p['discount_type'] === 'percent' ? $p['discount_value'] . '%' : '₱' . number_format($p['discount_value'], 2) ?></td>
                <td><?= $p['usage_count'] ?></td>
                <td><?= $p['max_uses'] ?? '∞' ?></td>
                <td style="color:var(--red)">₱<?= number_format($p['total_discount'], 2) ?></td>
                <td style="color:var(--green)">₱<?= number_format($p['total_revenue'], 2) ?></td>
                <td><?= $p['expires_at'] ? date('M j, Y', strtotime($p['expires_at'])) : 'Never' ?></td>
                <td><span class="badge <?= $p['is_active'] && (!$p['expires_at'] || $p['expires_at'] >= date('Y-m-d')) ? 'badge-green' : 'badge-gray' ?>"><?= $p['is_active'] ? 'Active' : 'Inactive' ?></span></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Review Trends -->
<div class="card">
    <div class="card-header">Review Trends (by Month)</div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Month</th><th>Total Reviews</th><th>Approved</th><th>Avg Rating</th></tr></thead>
            <tbody>
            <?php if (empty($review_trends)): ?>
                <tr><td colspan="4" class="text-center text-muted" style="padding:16px">No review data yet.</td></tr>
            <?php else: ?>
            <?php foreach ($review_trends as $r): ?>
            <tr>
                <td><?= $r['month'] ?></td>
                <td><?= $r['total'] ?></td>
                <td><?= $r['approved'] ?></td>
                <td>
                    <?php $stars = round($r['avg_rating']); ?>
                    <?= str_repeat('★', $stars) ?><?= str_repeat('☆', 5 - $stars) ?>
                    (<?= $r['avg_rating'] ?>)
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
