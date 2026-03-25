<?php
$page_title = 'Orders';
require_once __DIR__ . '/includes/header.php';

// ── Handle POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_editor();
    $action = $_POST['action'] ?? '';
    $order_id = (int) ($_POST['order_id'] ?? 0);

    if ($action === 'update_status') {
        $new_status  = $_POST['status'] ?? '';
        $tracking    = trim($_POST['tracking_number'] ?? '');
        $allowed     = ['pending','processing','shipped','delivered','cancelled'];

        if (!in_array($new_status, $allowed)) {
            flash('error', 'Invalid status.'); header('Location: /hobbyloop/admin/orders.php'); exit;
        }

        $pdo->prepare("UPDATE orders SET status=? WHERE id=?")->execute([$new_status, $order_id]);

        if ($tracking) {
            $pdo->prepare("UPDATE shipments SET tracking_number=? WHERE order_id=?")->execute([$tracking, $order_id]);
            // Also update status on shipments if shipped/delivered
            if (in_array($new_status, ['shipped','delivered'])) {
                $ship_status = $new_status === 'delivered' ? 'delivered' : 'shipped';
                $pdo->prepare("UPDATE shipments SET status=? WHERE order_id=?")->execute([$ship_status, $order_id]);
            }
        }

        // Get order number for logging
        $on = $pdo->prepare("SELECT order_number, user_id FROM orders WHERE id=?");
        $on->execute([$order_id]); $on = $on->fetch();

        log_admin_action($pdo, 'update_status', 'order', $order_id, [
            'order_number' => $on['order_number'] ?? '',
            'new_status'   => $new_status,
            'tracking'     => $tracking,
        ]);

        // Notify the customer
        if ($on) {
            $messages = [
                'processing' => "Your order {$on['order_number']} is now being processed.",
                'shipped'    => "Your order {$on['order_number']} has been shipped!",
                'delivered'  => "Your order {$on['order_number']} has been delivered.",
                'cancelled'  => "Your order {$on['order_number']} has been cancelled.",
            ];
            if (isset($messages[$new_status])) {
                $pdo->prepare("INSERT INTO notifications (user_id, icon, text) VALUES (?,?,?)")
                    ->execute([$on['user_id'], '📦', $messages[$new_status]]);
            }
        }

        flash('success', 'Order updated.');
        header('Location: /hobbyloop/admin/orders.php'); exit;
    }
}

// ── Filters ──
$filter_status = $_GET['status'] ?? '';
$search        = trim($_GET['q'] ?? '');
$page          = max(1, (int) ($_GET['page'] ?? 1));
$per_page      = 20;
$offset        = ($page - 1) * $per_page;

// Detail view
$detail_order = null;
if (!empty($_GET['id'])) {
    $stmt = $pdo->prepare("
        SELECT o.*, u.first_name, u.last_name, u.email, u.phone,
               p.method AS pay_method, p.status AS pay_status, p.transaction_id,
               s.tracking_number, s.status AS ship_status, s.estimated_delivery, s.actual_delivery,
               s.courier, pc.code AS promo_code_label
        FROM orders o
        JOIN users u ON u.id = o.user_id
        LEFT JOIN payments p ON p.order_id = o.id
        LEFT JOIN shipments s ON s.order_id = o.id
        LEFT JOIN promo_codes pc ON pc.code = o.promo_code
        WHERE o.order_number = ?
    ");
    $stmt->execute([$_GET['id']]);
    $detail_order = $stmt->fetch();

    if ($detail_order) {
        $items = $pdo->prepare("SELECT * FROM order_items WHERE order_id=?");
        $items->execute([$detail_order['id']]);
        $detail_order['items'] = $items->fetchAll();
    }
}

$where = []; $params = [];
if ($filter_status) { $where[] = 'o.status = ?'; $params[] = $filter_status; }
if ($search) {
    $where[] = '(o.order_number LIKE ? OR u.email LIKE ? OR u.first_name LIKE ?)';
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}
$sql_where = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = $pdo->prepare("SELECT COUNT(*) FROM orders o JOIN users u ON u.id = o.user_id $sql_where");
$total->execute($params);
$total_count = (int) $total->fetchColumn();
$pages = max(1, (int) ceil($total_count / $per_page));

$stmt = $pdo->prepare("
    SELECT o.*, u.first_name, u.last_name, u.email,
           s.tracking_number,
           pu.code AS promo_code_used,
           pru.discount_applied
    FROM orders o
    JOIN users u ON u.id = o.user_id
    LEFT JOIN shipments s ON s.order_id = o.id
    LEFT JOIN promo_codes pu ON pu.code = o.promo_code
    LEFT JOIN promo_usage pru ON pru.order_id = o.id
    $sql_where
    ORDER BY o.created_at DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$orders = $stmt->fetchAll();

$statuses = ['pending','processing','shipped','delivered','cancelled'];
require_once __DIR__ . '/includes/sidebar.php';
?>

<?php if ($detail_order): ?>
<!-- Order Detail View -->
<div class="gap-2" style="margin-bottom:16px">
    <a href="/hobbyloop/admin/orders.php" class="btn btn-gray btn-sm">← Back to Orders</a>
</div>

<div class="two-col">
    <div>
        <div class="card">
            <div class="card-header">Order <?= htmlspecialchars($detail_order['order_number']) ?></div>
            <div class="card-body">
                <p><strong>Customer:</strong> <?= htmlspecialchars($detail_order['first_name'] . ' ' . $detail_order['last_name']) ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($detail_order['email']) ?></p>
                <p><strong>Phone:</strong> <?= htmlspecialchars($detail_order['phone'] ?? '—') ?></p>
                <p><strong>Date:</strong> <?= date('M j, Y g:i A', strtotime($detail_order['created_at'])) ?></p>
                <hr style="margin:12px 0;border:none;border-top:1px solid var(--border)">
                <p><strong>Shipping Address:</strong><br>
                   <?= htmlspecialchars($detail_order['shipping_street'] ?? '') ?>,<br>
                   <?= htmlspecialchars($detail_order['shipping_city'] ?? '') ?>, <?= htmlspecialchars($detail_order['shipping_province'] ?? '') ?> <?= htmlspecialchars($detail_order['shipping_zip'] ?? '') ?>
                </p>
                <hr style="margin:12px 0;border:none;border-top:1px solid var(--border)">
                <p><strong>Payment:</strong> <?= htmlspecialchars(strtoupper($detail_order['pay_method'] ?? '—')) ?>
                   <span class="badge badge-<?= $detail_order['pay_status'] === 'completed' ? 'green' : 'orange' ?>"><?= htmlspecialchars($detail_order['pay_status'] ?? '') ?></span>
                </p>
                <?php if ($detail_order['promo_code']): ?>
                <p><strong>Promo Code:</strong> <?= htmlspecialchars($detail_order['promo_code']) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Order Items</div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Product</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr></thead>
                    <tbody>
                    <?php foreach ($detail_order['items'] as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['product_name']) ?></td>
                        <td><?= $item['qty'] ?></td>
                        <td>₱<?= number_format($item['price'], 2) ?></td>
                        <td>₱<?= number_format($item['price'] * $item['qty'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-body" style="padding-top:8px;font-size:13px">
                <div style="display:flex;justify-content:flex-end;flex-direction:column;align-items:flex-end;gap:4px">
                    <div>Subtotal: ₱<?= number_format($detail_order['subtotal'], 2) ?></div>
                    <?php if ($detail_order['discount'] > 0): ?>
                    <div style="color:var(--green)">Discount: -₱<?= number_format($detail_order['discount'], 2) ?></div>
                    <?php endif; ?>
                    <div>Shipping: ₱<?= number_format($detail_order['shipping_fee'], 2) ?></div>
                    <div style="font-weight:700;font-size:15px">Total: ₱<?= number_format($detail_order['total'], 2) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div>
        <div class="card">
            <div class="card-header">Update Order</div>
            <div class="card-body">
                <?php
                $sc = ['pending'=>'badge-orange','processing'=>'badge-blue','shipped'=>'badge-teal','delivered'=>'badge-green','cancelled'=>'badge-red'];
                echo '<p style="margin-bottom:12px">Current status: <span class="badge ' . ($sc[$detail_order['status']] ?? 'badge-gray') . '">' . htmlspecialchars($detail_order['status']) . '</span></p>';
                ?>
                <form method="POST">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="order_id" value="<?= $detail_order['id'] ?>">
                    <div class="form-group">
                        <label>New Status</label>
                        <select name="status">
                            <?php foreach ($statuses as $s): ?>
                            <option value="<?= $s ?>" <?= $detail_order['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Tracking Number (J&T Express)</label>
                        <input type="text" name="tracking_number" value="<?= htmlspecialchars($detail_order['tracking_number'] ?? '') ?>" placeholder="e.g. JT123456789PH">
                    </div>
                    <button type="submit" class="btn btn-primary">Update Order</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Shipping Info</div>
            <div class="card-body">
                <p><strong>Courier:</strong> <?= htmlspecialchars($detail_order['courier'] ?? 'J&T Express') ?></p>
                <p><strong>Tracking:</strong> <?= htmlspecialchars($detail_order['tracking_number'] ?? 'Not yet assigned') ?></p>
                <p><strong>Status:</strong> <?= htmlspecialchars($detail_order['ship_status'] ?? '—') ?></p>
                <p><strong>Est. Delivery:</strong> <?= $detail_order['estimated_delivery'] ? date('M j, Y', strtotime($detail_order['estimated_delivery'])) : '—' ?></p>
                <p><strong>Actual Delivery:</strong> <?= $detail_order['actual_delivery'] ? date('M j, Y', strtotime($detail_order['actual_delivery'])) : '—' ?></p>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- Order List View -->
<div class="filters">
    <form method="GET" style="display:contents">
        <input type="text" name="q" placeholder="Search by order #, email, name..." value="<?= htmlspecialchars($search) ?>">
        <select name="status">
            <option value="">All Status</option>
            <?php foreach ($statuses as $s): ?>
            <option value="<?= $s ?>" <?= $filter_status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-gray btn-sm">Filter</button>
        <a href="/hobbyloop/admin/orders.php" class="btn btn-gray btn-sm">Reset</a>
    </form>
</div>

<div class="card mb-0">
    <div class="card-header"><?= number_format($total_count) ?> Orders</div>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>Order #</th><th>Customer</th><th>Total</th><th>Promo</th>
                <th>Tracking</th><th>Status</th><th>Date</th><th>Actions</th>
            </tr></thead>
            <tbody>
            <?php if (empty($orders)): ?>
                <tr><td colspan="8" class="text-center text-muted" style="padding:24px">No orders found.</td></tr>
            <?php else: ?>
            <?php foreach ($orders as $o): ?>
            <?php
                $sc = ['pending'=>'badge-orange','processing'=>'badge-blue','shipped'=>'badge-teal','delivered'=>'badge-green','cancelled'=>'badge-red'];
            ?>
            <tr>
                <td><a href="?id=<?= urlencode($o['order_number']) ?>"><?= htmlspecialchars($o['order_number']) ?></a></td>
                <td><?= htmlspecialchars($o['first_name'] . ' ' . $o['last_name']) ?><br><span class="text-muted"><?= htmlspecialchars($o['email']) ?></span></td>
                <td>₱<?= number_format($o['total'], 2) ?>
                    <?php if ($o['discount_applied'] > 0): ?>
                    <br><span class="text-muted" style="font-size:11px;color:var(--green)">-₱<?= number_format($o['discount_applied'], 2) ?> promo</span>
                    <?php endif; ?>
                </td>
                <td><?= $o['promo_code'] ? '<span class="badge badge-teal">' . htmlspecialchars($o['promo_code']) . '</span>' : '—' ?></td>
                <td><?= $o['tracking_number'] ? htmlspecialchars($o['tracking_number']) : '<span class="text-muted">—</span>' ?></td>
                <td><span class="badge <?= $sc[$o['status']] ?? 'badge-gray' ?>"><?= htmlspecialchars($o['status']) ?></span></td>
                <td class="text-muted"><?= date('M j, Y', strtotime($o['created_at'])) ?></td>
                <td><a href="?id=<?= urlencode($o['order_number']) ?>" class="btn btn-gray btn-xs">View</a></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($pages > 1): ?>
<div class="pagination">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
    <a href="?page=<?= $i ?>&status=<?= urlencode($filter_status) ?>&q=<?= urlencode($search) ?>"
       class="<?= $i === $page ? 'current' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
