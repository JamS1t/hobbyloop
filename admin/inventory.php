<?php
$page_title = 'Inventory';
require_once __DIR__ . '/includes/header.php';

// ── Handle stock adjustment ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_editor();
    $product_id = (int) ($_POST['product_id'] ?? 0);
    $change_qty = (int) ($_POST['change_qty'] ?? 0);
    $reason     = trim($_POST['reason'] ?? 'adjustment');
    $notes      = trim($_POST['notes'] ?? '');

    if (!$product_id || $change_qty === 0) {
        flash('error', 'Product and quantity change are required.');
    } else {
        // Get current stock
        $cur = $pdo->prepare("SELECT stock_qty, name FROM products WHERE id=?");
        $cur->execute([$product_id]);
        $cur = $cur->fetch();

        if (!$cur) {
            flash('error', 'Product not found.');
        } else {
            $new_qty = max(0, $cur['stock_qty'] + $change_qty);
            $pdo->prepare("UPDATE products SET stock_qty=? WHERE id=?")->execute([$new_qty, $product_id]);
            $pdo->prepare("
                INSERT INTO inventory_log (product_id, change_qty, reason, notes)
                VALUES (?,?,?,?)
            ")->execute([$product_id, $change_qty, $reason, $notes]);
            log_admin_action($pdo, 'stock_adjustment', 'product', $product_id, [
                'product' => $cur['name'], 'change' => $change_qty, 'new_qty' => $new_qty, 'reason' => $reason
            ]);
            flash('success', "Stock updated for \"{$cur['name']}\": {$cur['stock_qty']} → {$new_qty}");
        }
    }
    header('Location: /hobbyloop/admin/inventory.php'); exit;
}

// ── Filters ──
$filter = $_GET['filter'] ?? '';
$search = trim($_GET['q'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

$where = []; $params = [];
if ($filter === 'low')      { $where[] = 'p.stock_qty <= 5 AND p.is_active = 1'; }
if ($filter === 'out')      { $where[] = 'p.stock_qty = 0'; }
if ($filter === 'inactive') { $where[] = 'p.is_active = 0'; }
if ($search) { $where[] = 'p.name LIKE ?'; $params[] = "%$search%"; }
$sql_where = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = $pdo->prepare("SELECT COUNT(*) FROM products p $sql_where");
$total->execute($params);
$total_count = (int) $total->fetchColumn();
$pages = max(1, (int) ceil($total_count / $per_page));

$products = $pdo->prepare("
    SELECT p.id, p.name, p.stock_qty, p.is_active, c.label AS cat_label,
           u.first_name, u.last_name
    FROM products p
    JOIN categories c ON c.id = p.category_id
    JOIN users u ON u.id = p.seller_id
    $sql_where
    ORDER BY p.stock_qty ASC, p.name ASC
    LIMIT $per_page OFFSET $offset
");
$products->execute($params);
$products = $products->fetchAll();

// Movement log (last 30)
$log = $pdo->query("
    SELECT il.*, p.name AS product_name, p.stock_qty AS current_stock
    FROM inventory_log il
    JOIN products p ON p.id = il.product_id
    ORDER BY il.created_at DESC
    LIMIT 30
")->fetchAll();

// All products for the adjustment form
$all_products = $pdo->query("SELECT id, name, stock_qty FROM products WHERE is_active=1 ORDER BY name")->fetchAll();

require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="two-col">
    <div>
        <div class="card">
            <div class="card-header">
                Stock Adjustment
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-group">
                        <label>Product *</label>
                        <select name="product_id" required>
                            <option value="">Select product...</option>
                            <?php foreach ($all_products as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?> (Stock: <?= $p['stock_qty'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Change Qty (+ add / - remove) *</label>
                            <input type="number" name="change_qty" required placeholder="e.g. +10 or -3">
                        </div>
                        <div class="form-group">
                            <label>Reason</label>
                            <select name="reason">
                                <option value="restock">Restock</option>
                                <option value="adjustment">Adjustment</option>
                                <option value="return">Return</option>
                                <option value="damage">Damage/Loss</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <input type="text" name="notes" placeholder="Optional notes">
                    </div>
                    <button type="submit" class="btn btn-primary">Apply Adjustment</button>
                </form>
            </div>
        </div>
    </div>

    <div>
        <div class="card" style="max-height:400px;overflow:hidden;display:flex;flex-direction:column">
            <div class="card-header">Recent Movement Log</div>
            <div class="table-wrap" style="overflow-y:auto;flex:1">
                <table>
                    <thead><tr><th>Product</th><th>Change</th><th>Reason</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php if (empty($log)): ?>
                        <tr><td colspan="4" class="text-center text-muted" style="padding:16px">No movements yet.</td></tr>
                    <?php else: ?>
                    <?php foreach ($log as $l): ?>
                    <tr>
                        <td><?= htmlspecialchars($l['product_name']) ?></td>
                        <td style="color:<?= $l['change_qty'] > 0 ? 'var(--green)' : 'var(--red)' ?>;font-weight:600">
                            <?= $l['change_qty'] > 0 ? '+' : '' ?><?= $l['change_qty'] ?>
                        </td>
                        <td><?= htmlspecialchars($l['reason']) ?></td>
                        <td class="text-muted"><?= date('M j, g:i A', strtotime($l['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Stock Levels Table -->
<div class="filters">
    <form method="GET" style="display:contents">
        <input type="text" name="q" placeholder="Search products..." value="<?= htmlspecialchars($search) ?>">
        <select name="filter">
            <option value="">All Products</option>
            <option value="low"      <?= $filter === 'low'      ? 'selected' : '' ?>>Low Stock (≤5)</option>
            <option value="out"      <?= $filter === 'out'      ? 'selected' : '' ?>>Out of Stock</option>
            <option value="inactive" <?= $filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
        <button type="submit" class="btn btn-gray btn-sm">Filter</button>
        <a href="/hobbyloop/admin/inventory.php" class="btn btn-gray btn-sm">Reset</a>
    </form>
</div>

<div class="card mb-0">
    <div class="card-header"><?= number_format($total_count) ?> Products</div>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>ID</th><th>Product</th><th>Category</th><th>Seller</th>
                <th>Stock</th><th>Status</th>
            </tr></thead>
            <tbody>
            <?php if (empty($products)): ?>
                <tr><td colspan="6" class="text-center text-muted" style="padding:24px">No products found.</td></tr>
            <?php else: ?>
            <?php foreach ($products as $p): ?>
            <tr>
                <td class="text-muted"><?= $p['id'] ?></td>
                <td><?= htmlspecialchars($p['name']) ?></td>
                <td><?= htmlspecialchars($p['cat_label']) ?></td>
                <td><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?></td>
                <td>
                    <?php if ($p['stock_qty'] == 0): ?>
                        <span class="badge badge-red">Out of Stock</span>
                    <?php elseif ($p['stock_qty'] <= 5): ?>
                        <span class="low-stock"><?= $p['stock_qty'] ?></span> <span class="badge badge-orange">Low</span>
                    <?php else: ?>
                        <?= $p['stock_qty'] ?>
                    <?php endif; ?>
                </td>
                <td><span class="badge <?= $p['is_active'] ? 'badge-green' : 'badge-gray' ?>"><?= $p['is_active'] ? 'Active' : 'Inactive' ?></span></td>
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
    <a href="?page=<?= $i ?>&filter=<?= urlencode($filter) ?>&q=<?= urlencode($search) ?>"
       class="<?= $i === $page ? 'current' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
