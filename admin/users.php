<?php
$page_title = 'Users';
require_once __DIR__ . '/includes/header.php';

// ── Handle POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $user_id = (int) ($_POST['user_id'] ?? 0);

    if ($action === 'toggle_verified') {
        require_editor();
        $pdo->prepare("UPDATE users SET is_verified = NOT is_verified WHERE id=?")->execute([$user_id]);
        log_admin_action($pdo, 'toggle_verified', 'user', $user_id);
        flash('success', 'User verification toggled.');
        header('Location: /hobbyloop/admin/users.php'); exit;
    }

    if ($action === 'change_role') {
        require_editor();
        $new_role  = $_POST['role'] ?? '';
        $new_level = $_POST['admin_level'] ?? null;
        $allowed_roles = ['buyer','seller','admin'];

        // Only super admins can set role=admin
        if ($new_role === 'admin' && !is_super()) {
            flash('error', 'Only super admins can grant admin role.'); header('Location: /hobbyloop/admin/users.php'); exit;
        }
        if (!in_array($new_role, $allowed_roles)) {
            flash('error', 'Invalid role.'); header('Location: /hobbyloop/admin/users.php'); exit;
        }

        $admin_level = ($new_role === 'admin') ? ($new_level ?: 'viewer') : null;
        $pdo->prepare("UPDATE users SET role=?, admin_level=? WHERE id=?")->execute([$new_role, $admin_level, $user_id]);
        log_admin_action($pdo, 'change_role', 'user', $user_id, ['role' => $new_role, 'admin_level' => $admin_level]);
        flash('success', 'User role updated.');
        header('Location: /hobbyloop/admin/users.php'); exit;
    }
}

// ── Filters ──
$filter_role  = $_GET['role'] ?? '';
$search       = trim($_GET['q'] ?? '');
$page         = max(1, (int) ($_GET['page'] ?? 1));
$per_page     = 20;
$offset       = ($page - 1) * $per_page;

$where = []; $params = [];
if ($filter_role) { $where[] = 'role = ?'; $params[] = $filter_role; }
if ($search) {
    $where[] = '(email LIKE ? OR first_name LIKE ? OR last_name LIKE ?)';
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}
$sql_where = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = $pdo->prepare("SELECT COUNT(*) FROM users $sql_where");
$total->execute($params);
$total_count = (int) $total->fetchColumn();
$pages = max(1, (int) ceil($total_count / $per_page));

$stmt = $pdo->prepare("
    SELECT u.*,
           (SELECT COUNT(*) FROM orders WHERE user_id = u.id) AS order_count
    FROM users u
    $sql_where
    ORDER BY u.created_at DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$users = $stmt->fetchAll();

// View detail
$view_user = null;
if (!empty($_GET['id'])) {
    $vu = $pdo->prepare("SELECT * FROM users WHERE id=?");
    $vu->execute([(int)$_GET['id']]);
    $view_user = $vu->fetch();
    if ($view_user) {
        $view_user['orders'] = $pdo->prepare("SELECT order_number, total, status, created_at FROM orders WHERE user_id=? ORDER BY created_at DESC LIMIT 5");
        $view_user['orders']->execute([$view_user['id']]);
        $view_user['orders'] = $view_user['orders']->fetchAll();

        $view_user['addresses'] = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id=?");
        $view_user['addresses']->execute([$view_user['id']]);
        $view_user['addresses'] = $view_user['addresses']->fetchAll();
    }
}

require_once __DIR__ . '/includes/sidebar.php';
?>

<?php if ($view_user): ?>
<div class="gap-2" style="margin-bottom:16px">
    <a href="/hobbyloop/admin/users.php" class="btn btn-gray btn-sm">← Back to Users</a>
</div>

<div class="two-col">
    <div>
        <div class="card">
            <div class="card-header">User Profile</div>
            <div class="card-body">
                <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px">
                    <div style="width:50px;height:50px;background:<?= htmlspecialchars($view_user['avatar_color']) ?>;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:18px">
                        <?= htmlspecialchars($view_user['avatar_initials'] ?? '?') ?>
                    </div>
                    <div>
                        <div style="font-weight:600"><?= htmlspecialchars($view_user['first_name'] . ' ' . $view_user['last_name']) ?></div>
                        <div class="text-muted"><?= htmlspecialchars($view_user['email']) ?></div>
                    </div>
                </div>
                <p><strong>Phone:</strong> <?= htmlspecialchars($view_user['phone'] ?? '—') ?></p>
                <p><strong>Role:</strong> <?= htmlspecialchars($view_user['role']) ?>
                    <?php if ($view_user['admin_level']): ?><span class="badge badge-blue"><?= $view_user['admin_level'] ?></span><?php endif; ?>
                </p>
                <p><strong>Trust Badge:</strong> <?= htmlspecialchars($view_user['trust_badge'] ?? '—') ?></p>
                <p><strong>Verified:</strong> <?= $view_user['is_verified'] ? '<span class="badge badge-green">Yes</span>' : '<span class="badge badge-gray">No</span>' ?></p>
                <p><strong>Joined:</strong> <?= date('M j, Y', strtotime($view_user['created_at'])) ?></p>

                <hr style="margin:12px 0;border:none;border-top:1px solid var(--border)">

                <div class="gap-2">
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="toggle_verified">
                        <input type="hidden" name="user_id" value="<?= $view_user['id'] ?>">
                        <button class="btn btn-gray btn-sm"><?= $view_user['is_verified'] ? 'Unverify' : 'Verify' ?> User</button>
                    </form>
                </div>
            </div>
        </div>

        <?php if (is_editor()): ?>
        <div class="card">
            <div class="card-header">Change Role</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="change_role">
                    <input type="hidden" name="user_id" value="<?= $view_user['id'] ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Role</label>
                            <select name="role">
                                <?php foreach (['buyer','seller','admin'] as $r): ?>
                                <option value="<?= $r ?>" <?= $view_user['role'] === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Admin Level (if admin)</label>
                            <select name="admin_level">
                                <option value="">None</option>
                                <?php foreach (['super','editor','viewer'] as $lvl): ?>
                                <option value="<?= $lvl ?>" <?= ($view_user['admin_level'] ?? '') === $lvl ? 'selected' : '' ?>><?= ucfirst($lvl) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Update Role</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($view_user['addresses'])): ?>
        <div class="card">
            <div class="card-header">Addresses</div>
            <div class="card-body" style="font-size:13px">
                <?php foreach ($view_user['addresses'] as $addr): ?>
                <div style="margin-bottom:10px;padding-bottom:10px;border-bottom:1px solid var(--border)">
                    <strong><?= htmlspecialchars($addr['label']) ?></strong><?= $addr['is_default'] ? ' <span class="badge badge-teal">Default</span>' : '' ?><br>
                    <?= htmlspecialchars($addr['street']) ?>, <?= htmlspecialchars($addr['city']) ?>, <?= htmlspecialchars($addr['province']) ?> <?= htmlspecialchars($addr['zip']) ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div>
        <div class="card">
            <div class="card-header">Recent Orders</div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Order #</th><th>Total</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php if (empty($view_user['orders'])): ?>
                        <tr><td colspan="4" class="text-center text-muted" style="padding:16px">No orders yet.</td></tr>
                    <?php else: ?>
                    <?php foreach ($view_user['orders'] as $o): ?>
                    <?php $sc = ['pending'=>'badge-orange','processing'=>'badge-blue','shipped'=>'badge-teal','delivered'=>'badge-green','cancelled'=>'badge-red']; ?>
                    <tr>
                        <td><a href="/hobbyloop/admin/orders.php?id=<?= urlencode($o['order_number']) ?>"><?= htmlspecialchars($o['order_number']) ?></a></td>
                        <td>₱<?= number_format($o['total'], 2) ?></td>
                        <td><span class="badge <?= $sc[$o['status']] ?? 'badge-gray' ?>"><?= $o['status'] ?></span></td>
                        <td class="text-muted"><?= date('M j, Y', strtotime($o['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- User List -->
<div class="filters">
    <form method="GET" style="display:contents">
        <input type="text" name="q" placeholder="Search by name or email..." value="<?= htmlspecialchars($search) ?>">
        <select name="role">
            <option value="">All Roles</option>
            <option value="buyer"  <?= $filter_role === 'buyer'  ? 'selected' : '' ?>>Buyer</option>
            <option value="seller" <?= $filter_role === 'seller' ? 'selected' : '' ?>>Seller</option>
            <option value="admin"  <?= $filter_role === 'admin'  ? 'selected' : '' ?>>Admin</option>
        </select>
        <button type="submit" class="btn btn-gray btn-sm">Filter</button>
        <a href="/hobbyloop/admin/users.php" class="btn btn-gray btn-sm">Reset</a>
    </form>
</div>

<div class="card mb-0">
    <div class="card-header"><?= number_format($total_count) ?> Users</div>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>ID</th><th>Name</th><th>Email</th><th>Role</th>
                <th>Verified</th><th>Orders</th><th>Joined</th><th>Actions</th>
            </tr></thead>
            <tbody>
            <?php if (empty($users)): ?>
                <tr><td colspan="8" class="text-center text-muted" style="padding:24px">No users found.</td></tr>
            <?php else: ?>
            <?php foreach ($users as $u): ?>
            <tr>
                <td class="text-muted"><?= $u['id'] ?></td>
                <td>
                    <div style="display:flex;align-items:center;gap:8px">
                        <div style="width:28px;height:28px;background:<?= htmlspecialchars($u['avatar_color']) ?>;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:10px;flex-shrink:0">
                            <?= htmlspecialchars($u['avatar_initials'] ?? '?') ?>
                        </div>
                        <?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?>
                    </div>
                </td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td>
                    <span class="badge <?= $u['role'] === 'admin' ? 'badge-red' : ($u['role'] === 'seller' ? 'badge-blue' : 'badge-gray') ?>">
                        <?= htmlspecialchars($u['role']) ?>
                    </span>
                    <?php if ($u['admin_level']): ?><span class="badge badge-orange"><?= $u['admin_level'] ?></span><?php endif; ?>
                </td>
                <td><?= $u['is_verified'] ? '<span class="badge badge-green">Yes</span>' : '<span class="badge badge-gray">No</span>' ?></td>
                <td><?= $u['order_count'] ?></td>
                <td class="text-muted"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                <td><a href="?id=<?= $u['id'] ?>" class="btn btn-gray btn-xs">View</a></td>
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
    <a href="?page=<?= $i ?>&role=<?= urlencode($filter_role) ?>&q=<?= urlencode($search) ?>"
       class="<?= $i === $page ? 'current' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
