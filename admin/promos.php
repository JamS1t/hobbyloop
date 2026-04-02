<?php
$page_title = 'Promo Codes';
require_once __DIR__ . '/includes/header.php';

// ── Handle POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_editor();
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'edit') {
        $id             = (int) ($_POST['id'] ?? 0);
        $code           = strtoupper(trim($_POST['code'] ?? ''));
        $discount_type  = $_POST['discount_type'] ?? 'percent';
        $discount_value = (float) ($_POST['discount_value'] ?? 0);
        $min_order      = ($_POST['min_order'] ?? '') !== '' ? (float) $_POST['min_order'] : null;
        $max_uses       = ($_POST['max_uses'] ?? '') !== '' ? (int) $_POST['max_uses'] : null;
        $valid_from     = ($_POST['valid_from'] ?? '') ?: null;
        $expires_at     = ($_POST['expires_at'] ?? '') ?: null;
        $is_active      = isset($_POST['is_active']) ? 1 : 0;

        if (!$code || !$discount_value) {
            flash('error', 'Code and discount value are required.');
        } elseif (!in_array($discount_type, ['percent','fixed'])) {
            flash('error', 'Invalid discount type.');
        } else {
            if ($action === 'create') {
                // Check duplicate
                $dup = $pdo->prepare("SELECT id FROM promo_codes WHERE code=?");
                $dup->execute([$code]);
                if ($dup->fetch()) {
                    flash('error', "Code \"$code\" already exists.");
                    header('Location: /hobbyloop/admin/promos.php'); exit;
                }
                $pdo->prepare("
                    INSERT INTO promo_codes (code, discount_type, discount_value, min_order, max_uses, valid_from, expires_at, is_active)
                    VALUES (?,?,?,?,?,?,?,?)
                ")->execute([$code, $discount_type, $discount_value, $min_order, $max_uses, $valid_from, $expires_at, $is_active]);
                log_admin_action($pdo, 'create', 'promo_code', null, ['code' => $code]);
                flash('success', "Promo code \"$code\" created.");
            } else {
                $pdo->prepare("
                    UPDATE promo_codes SET code=?, discount_type=?, discount_value=?,
                        min_order=?, max_uses=?, valid_from=?, expires_at=?, is_active=?
                    WHERE id=?
                ")->execute([$code, $discount_type, $discount_value, $min_order, $max_uses, $valid_from, $expires_at, $is_active, $id]);
                log_admin_action($pdo, 'edit', 'promo_code', $id, ['code' => $code]);
                flash('success', "Promo code updated.");
            }
        }
        header('Location: /hobbyloop/admin/promos.php'); exit;
    }

    if ($action === 'toggle') {
        $id = (int) $_POST['id'];
        $pdo->prepare("UPDATE promo_codes SET is_active = NOT is_active WHERE id=?")->execute([$id]);
        log_admin_action($pdo, 'toggle_active', 'promo_code', $id);
        flash('success', 'Promo status toggled.');
        header('Location: /hobbyloop/admin/promos.php'); exit;
    }

    if ($action === 'delete') {
        require_super();
        $id = (int) $_POST['id'];
        $row = $pdo->prepare("SELECT code FROM promo_codes WHERE id=?");
        $row->execute([$id]);
        $row = $row->fetch();
        $pdo->prepare("DELETE FROM promo_codes WHERE id=?")->execute([$id]);
        log_admin_action($pdo, 'delete', 'promo_code', $id, ['code' => $row['code'] ?? '']);
        flash('success', 'Promo code deleted.');
        header('Location: /hobbyloop/admin/promos.php'); exit;
    }
}

// ── Fetch promos with stats ──
$promos = $pdo->query("
    SELECT pc.*,
           COUNT(pu.id) AS usage_count,
           COALESCE(SUM(pu.discount_applied), 0) AS total_discount,
           COALESCE(SUM(o.total), 0) AS promo_revenue
    FROM promo_codes pc
    LEFT JOIN promo_usage pu ON pu.promo_id = pc.id
    LEFT JOIN orders o ON o.id = pu.order_id AND o.status != 'cancelled'
    GROUP BY pc.id
    ORDER BY pc.created_at DESC
")->fetchAll();

// Edit promo
$edit_promo = null;
if (!empty($_GET['edit'])) {
    $ep = $pdo->prepare("SELECT * FROM promo_codes WHERE id=?");
    $ep->execute([(int)$_GET['edit']]);
    $edit_promo = $ep->fetch();
}

$page_actions = '<a href="?create=1" class="btn btn-primary btn-sm">+ New Promo</a>';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="card mb-0">
    <div class="card-header"><?= count($promos) ?> Promo Codes</div>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>Code</th><th>Type</th><th>Value</th><th>Min Order</th>
                <th>Uses</th><th>Max Uses</th><th>Total Discount</th><th>Revenue</th>
                <th>Expires</th><th>Status</th><th>Actions</th>
            </tr></thead>
            <tbody>
            <?php if (empty($promos)): ?>
                <tr><td colspan="11" class="text-center text-muted" style="padding:24px">No promo codes yet.</td></tr>
            <?php else: ?>
            <?php foreach ($promos as $p): ?>
            <?php
                $expired = $p['expires_at'] && $p['expires_at'] < date('Y-m-d');
                $active  = $p['is_active'] && !$expired;
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($p['code']) ?></strong></td>
                <td><?= htmlspecialchars($p['discount_type']) ?></td>
                <td><?= $p['discount_type'] === 'percent' ? $p['discount_value'] . '%' : '₱' . number_format($p['discount_value'], 2) ?></td>
                <td><?= $p['min_order'] ? '₱' . number_format($p['min_order'], 2) : '—' ?></td>
                <td><?= $p['usage_count'] ?></td>
                <td><?= $p['max_uses'] ?? '∞' ?></td>
                <td style="color:var(--red)">₱<?= number_format($p['total_discount'], 2) ?></td>
                <td style="color:var(--green)">₱<?= number_format($p['promo_revenue'], 2) ?></td>
                <td><?= $p['expires_at'] ? date('M j, Y', strtotime($p['expires_at'])) : 'Never' ?><?= $expired ? ' <span class="badge badge-red">Expired</span>' : '' ?></td>
                <td><span class="badge <?= $active ? 'badge-green' : 'badge-gray' ?>"><?= $p['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                <td>
                    <div class="gap-2">
                        <a href="?edit=<?= $p['id'] ?>" class="btn btn-gray btn-xs">Edit</a>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <button class="btn btn-gray btn-xs"><?= $p['is_active'] ? 'Disable' : 'Enable' ?></button>
                        </form>
                        <?php if (is_super()): ?>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Delete promo code?')">
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

<!-- Create / Edit Modal -->
<?php $show_form = !empty($_GET['create']) || $edit_promo !== null; ?>
<div class="modal-backdrop <?= $show_form ? 'open' : '' ?>" id="promoModal">
<div class="modal">
    <h3><?= $edit_promo ? 'Edit Promo Code' : 'New Promo Code' ?></h3>
    <form method="POST">
        <input type="hidden" name="action" value="<?= $edit_promo ? 'edit' : 'create' ?>">
        <?php if ($edit_promo): ?><input type="hidden" name="id" value="<?= $edit_promo['id'] ?>"><?php endif; ?>

        <div class="form-row">
            <div class="form-group">
                <label>Code *</label>
                <input type="text" name="code" required style="text-transform:uppercase"
                       value="<?= htmlspecialchars($edit_promo['code'] ?? '') ?>"
                       placeholder="e.g. HOBBY10">
            </div>
            <div class="form-group">
                <label>Discount Type *</label>
                <select name="discount_type">
                    <option value="percent" <?= ($edit_promo['discount_type'] ?? '') === 'percent' ? 'selected' : '' ?>>Percent (%)</option>
                    <option value="fixed"   <?= ($edit_promo['discount_type'] ?? '') === 'fixed'   ? 'selected' : '' ?>>Fixed (₱)</option>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Discount Value *</label>
                <input type="number" name="discount_value" step="0.01" min="0" required
                       value="<?= $edit_promo['discount_value'] ?? '' ?>">
            </div>
            <div class="form-group">
                <label>Min Order Amount (₱)</label>
                <input type="number" name="min_order" step="0.01" min="0"
                       value="<?= $edit_promo['min_order'] ?? '' ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Max Uses</label>
                <input type="number" name="max_uses" min="1"
                       value="<?= $edit_promo['max_uses'] ?? '' ?>" placeholder="Leave blank = unlimited">
            </div>
            <div class="form-group">
                <label>Valid From</label>
                <input type="date" name="valid_from"
                       value="<?= !empty($edit_promo['valid_from']) ? date('Y-m-d', strtotime($edit_promo['valid_from'])) : '' ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Expiry Date</label>
                <input type="date" name="expires_at"
                       value="<?= $edit_promo['expires_at'] ? date('Y-m-d', strtotime($edit_promo['expires_at'])) : '' ?>">
            </div>
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="is_active" value="1" <?= ($edit_promo['is_active'] ?? 1) ? 'checked' : '' ?>> Active</label>
        </div>
        <div class="gap-2">
            <button type="submit" class="btn btn-primary"><?= $edit_promo ? 'Save Changes' : 'Create Promo' ?></button>
            <a href="/hobbyloop/admin/promos.php" class="btn btn-gray">Cancel</a>
        </div>
    </form>
</div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
