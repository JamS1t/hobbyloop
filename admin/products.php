<?php
$page_title = 'Products';
require_once __DIR__ . '/includes/header.php';

// ── Handle POST actions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_editor();
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'edit') {
        $id            = (int) ($_POST['id'] ?? 0);
        $seller_id     = (int) ($_POST['seller_id'] ?? 0);
        $category_id   = trim($_POST['category_id'] ?? '');
        $name          = trim($_POST['name'] ?? '');
        $description   = trim($_POST['description'] ?? '');
        $price         = (float) ($_POST['price'] ?? 0);
        $original_price = ($_POST['original_price'] ?? '') !== '' ? (float) $_POST['original_price'] : null;
        $sku           = trim($_POST['sku'] ?? '');
        $stock_qty     = (int) ($_POST['stock_qty'] ?? 0);
        $condition_label = trim($_POST['condition_label'] ?? '');
        $brand         = trim($_POST['brand'] ?? '');
        $badge         = $_POST['badge'] ?? null;
        $image_url     = trim($_POST['image_url'] ?? '');
        $is_active     = isset($_POST['is_active']) ? 1 : 0;

        if (!$name || !$price || !$category_id || !$seller_id || !$condition_label) {
            flash('error', 'Name, price, category, seller, and condition are required.');
        } else {
            if ($action === 'create') {
                $stmt = $pdo->prepare("
                    INSERT INTO products (seller_id, category_id, name, description, price, original_price,
                        sku, stock_qty, condition_label, brand, badge, image_url, is_active)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
                ");
                $stmt->execute([$seller_id, $category_id, $name, $description, $price, $original_price,
                    $sku, $stock_qty, $condition_label, $brand, $badge ?: null, $image_url, $is_active]);
                $new_id = $pdo->lastInsertId();
                log_admin_action($pdo, 'create', 'product', $new_id, ['name' => $name]);
                flash('success', "Product \"$name\" created.");
            } else {
                $stmt = $pdo->prepare("
                    UPDATE products SET seller_id=?, category_id=?, name=?, description=?, price=?,
                        original_price=?, sku=?, stock_qty=?, condition_label=?, brand=?,
                        badge=?, image_url=?, is_active=?
                    WHERE id=?
                ");
                $stmt->execute([$seller_id, $category_id, $name, $description, $price, $original_price,
                    $sku, $stock_qty, $condition_label, $brand, $badge ?: null, $image_url, $is_active, $id]);
                log_admin_action($pdo, 'edit', 'product', $id, ['name' => $name]);
                flash('success', "Product updated.");
            }
        }
        header('Location: /hobbyloop/admin/products.php'); exit;
    }

    if ($action === 'toggle') {
        $id = (int) $_POST['id'];
        $pdo->prepare("UPDATE products SET is_active = NOT is_active WHERE id=?")->execute([$id]);
        log_admin_action($pdo, 'toggle_active', 'product', $id);
        flash('success', 'Product status toggled.');
        header('Location: /hobbyloop/admin/products.php'); exit;
    }

    if ($action === 'delete') {
        require_super();
        $id = (int) $_POST['id'];
        $row = $pdo->prepare("SELECT name FROM products WHERE id=?");
        $row->execute([$id]);
        $row = $row->fetch();
        $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
        log_admin_action($pdo, 'delete', 'product', $id, ['name' => $row['name'] ?? '']);
        flash('success', 'Product deleted.');
        header('Location: /hobbyloop/admin/products.php'); exit;
    }
}

// ── Filters ──
$filter_cat = $_GET['cat'] ?? '';
$filter_status = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$where = []; $params = [];
if ($filter_cat)    { $where[] = 'p.category_id = ?'; $params[] = $filter_cat; }
if ($filter_status === 'active')   { $where[] = 'p.is_active = 1'; }
if ($filter_status === 'inactive') { $where[] = 'p.is_active = 0'; }
if ($search) { $where[] = 'p.name LIKE ?'; $params[] = "%$search%"; }
$sql_where = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = $pdo->prepare("SELECT COUNT(*) FROM products p $sql_where");
$total->execute($params);
$total_count = (int) $total->fetchColumn();
$pages = max(1, (int) ceil($total_count / $per_page));

$stmt = $pdo->prepare("
    SELECT p.*, c.label AS cat_label,
           u.first_name, u.last_name
    FROM products p
    JOIN categories c ON c.id = p.category_id
    JOIN users u ON u.id = p.seller_id
    $sql_where
    ORDER BY p.created_at DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$products = $stmt->fetchAll();

// Data for form selects
$categories = $pdo->query("SELECT id, label FROM categories ORDER BY sort_order")->fetchAll();
$sellers = $pdo->query("SELECT u.id, u.first_name, u.last_name FROM users u WHERE role = 'seller' ORDER BY first_name")->fetchAll();

// Edit product (load for modal)
$edit_product = null;
if (!empty($_GET['edit'])) {
    $ep = $pdo->prepare("SELECT * FROM products WHERE id=?");
    $ep->execute([(int)$_GET['edit']]);
    $edit_product = $ep->fetch();
}

$page_actions = '<a href="?create=1" class="btn btn-primary btn-sm">+ New Product</a>';
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Filters -->
<div class="filters">
    <form method="GET" style="display:contents">
        <input type="text" name="q" placeholder="Search products..." value="<?= htmlspecialchars($search) ?>">
        <select name="cat">
            <option value="">All Categories</option>
            <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $filter_cat === $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['label']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status">
            <option value="">All Status</option>
            <option value="active" <?= $filter_status === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $filter_status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
        <button type="submit" class="btn btn-gray btn-sm">Filter</button>
        <a href="/hobbyloop/admin/products.php" class="btn btn-gray btn-sm">Reset</a>
    </form>
</div>

<div class="card mb-0">
    <div class="card-header">
        <?= number_format($total_count) ?> Products
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>ID</th><th>Name</th><th>Category</th><th>Seller</th>
                <th>Price</th><th>Stock</th><th>Rating</th><th>Status</th><th>Actions</th>
            </tr></thead>
            <tbody>
            <?php if (empty($products)): ?>
                <tr><td colspan="9" class="text-center text-muted" style="padding:24px">No products found.</td></tr>
            <?php else: ?>
            <?php foreach ($products as $p): ?>
            <tr>
                <td class="text-muted"><?= $p['id'] ?></td>
                <td><?= htmlspecialchars($p['name']) ?>
                    <?php if ($p['badge']): ?><span class="badge badge-teal"><?= $p['badge'] ?></span><?php endif; ?>
                </td>
                <td><?= htmlspecialchars($p['cat_label']) ?></td>
                <td><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?></td>
                <td>₱<?= number_format($p['price'], 2) ?></td>
                <td class="<?= $p['stock_qty'] <= 5 ? 'low-stock' : '' ?>"><?= $p['stock_qty'] ?></td>
                <td><?= $p['rating'] ?> (<?= $p['review_count'] ?>)</td>
                <td><span class="badge <?= $p['is_active'] ? 'badge-green' : 'badge-gray' ?>"><?= $p['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                <td>
                    <div class="gap-2">
                        <a href="?edit=<?= $p['id'] ?>" class="btn btn-gray btn-xs">Edit</a>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <button class="btn btn-gray btn-xs"><?= $p['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                        </form>
                        <?php if (is_super()): ?>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this product?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <button class="btn btn-danger btn-xs">Delete</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
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
    <a href="?page=<?= $i ?>&cat=<?= urlencode($filter_cat) ?>&status=<?= urlencode($filter_status) ?>&q=<?= urlencode($search) ?>"
       class="<?= $i === $page ? 'current' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>

<!-- Create / Edit Modal -->
<?php $show_form = !empty($_GET['create']) || $edit_product !== null; ?>
<div class="modal-backdrop <?= $show_form ? 'open' : '' ?>" id="productModal">
<div class="modal">
    <h3><?= $edit_product ? 'Edit Product' : 'New Product' ?></h3>
    <form method="POST">
        <input type="hidden" name="action" value="<?= $edit_product ? 'edit' : 'create' ?>">
        <?php if ($edit_product): ?><input type="hidden" name="id" value="<?= $edit_product['id'] ?>"><?php endif; ?>

        <div class="form-group">
            <label>Product Name *</label>
            <input type="text" name="name" required value="<?= htmlspecialchars($edit_product['name'] ?? '') ?>">
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Category *</label>
                <select name="category_id" required>
                    <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= ($edit_product['category_id'] ?? '') === $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Seller *</label>
                <select name="seller_id" required>
                    <?php foreach ($sellers as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= ($edit_product['seller_id'] ?? '') == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label>Description</label>
            <textarea name="description"><?= htmlspecialchars($edit_product['description'] ?? '') ?></textarea>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Price (₱) *</label>
                <input type="number" name="price" step="0.01" min="0" required value="<?= $edit_product['price'] ?? '' ?>">
            </div>
            <div class="form-group">
                <label>Original Price (₱)</label>
                <input type="number" name="original_price" step="0.01" min="0" value="<?= $edit_product['original_price'] ?? '' ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Stock Qty</label>
                <input type="number" name="stock_qty" min="0" value="<?= $edit_product['stock_qty'] ?? 1 ?>">
            </div>
            <div class="form-group">
                <label>SKU</label>
                <input type="text" name="sku" value="<?= htmlspecialchars($edit_product['sku'] ?? '') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Condition *</label>
                <select name="condition_label" required>
                    <?php foreach (['Like New','Excellent','Very Good','Good','Acceptable'] as $cond): ?>
                    <option <?= ($edit_product['condition_label'] ?? '') === $cond ? 'selected' : '' ?>><?= $cond ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Badge</label>
                <select name="badge">
                    <option value="">None</option>
                    <?php foreach (['hot','top','new'] as $b): ?>
                    <option value="<?= $b ?>" <?= ($edit_product['badge'] ?? '') === $b ? 'selected' : '' ?>><?= ucfirst($b) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Brand</label>
                <input type="text" name="brand" value="<?= htmlspecialchars($edit_product['brand'] ?? '') ?>">
            </div>
            <div class="form-group" style="align-self:end">
                <label><input type="checkbox" name="is_active" value="1" <?= ($edit_product['is_active'] ?? 1) ? 'checked' : '' ?>> Active listing</label>
            </div>
        </div>
        <div class="form-group">
            <label>Image URL</label>
            <input type="text" name="image_url" value="<?= htmlspecialchars($edit_product['image_url'] ?? '') ?>">
        </div>
        <div class="gap-2">
            <button type="submit" class="btn btn-primary"><?= $edit_product ? 'Save Changes' : 'Create Product' ?></button>
            <a href="/hobbyloop/admin/products.php" class="btn btn-gray">Cancel</a>
        </div>
    </form>
</div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
