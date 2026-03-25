<?php
$page_title = 'System Logs';
require_once __DIR__ . '/includes/header.php';

// ── Filters ──
$filter_action = trim($_GET['action'] ?? '');
$filter_admin  = trim($_GET['admin'] ?? '');
$filter_date   = trim($_GET['date'] ?? '');
$page    = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 25;
$offset  = ($page - 1) * $per_page;

$where = []; $params = [];
if ($filter_action) { $where[] = 'sl.action = ?'; $params[] = $filter_action; }
if ($filter_admin)  { $where[] = '(u.first_name LIKE ? OR u.email LIKE ?)'; $params[] = "%$filter_admin%"; $params[] = "%$filter_admin%"; }
if ($filter_date)   { $where[] = 'DATE(sl.created_at) = ?'; $params[] = $filter_date; }
$sql_where = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = $pdo->prepare("
    SELECT COUNT(*) FROM system_logs sl
    LEFT JOIN users u ON u.id = sl.admin_id
    $sql_where
");
$total->execute($params);
$total_count = (int) $total->fetchColumn();
$pages = max(1, (int) ceil($total_count / $per_page));

$stmt = $pdo->prepare("
    SELECT sl.id, sl.action, sl.entity_type, sl.entity_id, sl.details,
           sl.ip_address, sl.created_at,
           u.first_name, u.last_name, u.email
    FROM system_logs sl
    LEFT JOIN users u ON u.id = sl.admin_id
    $sql_where
    ORDER BY sl.created_at DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Distinct actions for filter dropdown
$actions = $pdo->query("SELECT DISTINCT action FROM system_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);

$action_colors = [
    'login'            => 'badge-teal',
    'logout'           => 'badge-gray',
    'create'           => 'badge-green',
    'edit'             => 'badge-blue',
    'delete'           => 'badge-red',
    'toggle_active'    => 'badge-orange',
    'update_status'    => 'badge-blue',
    'approve'          => 'badge-green',
    'reject'           => 'badge-orange',
    'stock_adjustment' => 'badge-teal',
    'change_role'      => 'badge-red',
    'toggle_verified'  => 'badge-teal',
];

require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="filters">
    <form method="GET" style="display:contents">
        <select name="action">
            <option value="">All Actions</option>
            <?php foreach ($actions as $a): ?>
            <option value="<?= htmlspecialchars($a) ?>" <?= $filter_action === $a ? 'selected' : '' ?>><?= htmlspecialchars($a) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="admin" placeholder="Filter by admin..." value="<?= htmlspecialchars($filter_admin) ?>">
        <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>">
        <button type="submit" class="btn btn-gray btn-sm">Filter</button>
        <a href="/hobbyloop/admin/logs.php" class="btn btn-gray btn-sm">Reset</a>
    </form>
</div>

<div class="card mb-0">
    <div class="card-header"><?= number_format($total_count) ?> Log Entries</div>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>ID</th><th>Admin</th><th>Action</th><th>Entity</th>
                <th>Details</th><th>IP</th><th>Date</th>
            </tr></thead>
            <tbody>
            <?php if (empty($logs)): ?>
                <tr><td colspan="7" class="text-center text-muted" style="padding:24px">No logs found.</td></tr>
            <?php else: ?>
            <?php foreach ($logs as $l): ?>
            <tr>
                <td class="text-muted"><?= $l['id'] ?></td>
                <td>
                    <?php if ($l['first_name']): ?>
                    <?= htmlspecialchars($l['first_name'] . ' ' . $l['last_name']) ?><br>
                    <span class="text-muted"><?= htmlspecialchars($l['email'] ?? '') ?></span>
                    <?php else: ?>
                    <span class="text-muted">System</span>
                    <?php endif; ?>
                </td>
                <td><span class="badge <?= $action_colors[$l['action']] ?? 'badge-gray' ?>"><?= htmlspecialchars($l['action']) ?></span></td>
                <td>
                    <?= htmlspecialchars($l['entity_type']) ?>
                    <?php if ($l['entity_id']): ?><span class="text-muted">#<?= $l['entity_id'] ?></span><?php endif; ?>
                </td>
                <td style="max-width:200px;word-break:break-word;font-size:12px">
                    <?php
                    $d = json_decode($l['details'] ?? '{}', true);
                    if (!empty($d)) {
                        $parts = [];
                        foreach ($d as $k => $v) {
                            $parts[] = "$k: " . (is_array($v) ? json_encode($v) : htmlspecialchars((string)$v));
                        }
                        echo implode(', ', $parts);
                    }
                    ?>
                </td>
                <td class="text-muted"><?= htmlspecialchars($l['ip_address'] ?? '—') ?></td>
                <td class="text-muted" style="white-space:nowrap"><?= date('M j, Y g:i A', strtotime($l['created_at'])) ?></td>
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
    <a href="?page=<?= $i ?>&action=<?= urlencode($filter_action) ?>&admin=<?= urlencode($filter_admin) ?>&date=<?= urlencode($filter_date) ?>"
       class="<?= $i === $page ? 'current' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
