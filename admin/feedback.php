<?php
$page_title = 'Feedback & Support';
require_once __DIR__ . '/includes/header.php';

// ── Handle POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_editor();
    $action  = $_POST['action'] ?? '';
    $fb_id   = (int) ($_POST['fb_id'] ?? 0);

    if ($action === 'update') {
        $status      = $_POST['status'] ?? 'open';
        $admin_reply = trim($_POST['admin_reply'] ?? '');
        $allowed_statuses = ['open','in_progress','resolved','closed'];

        if (!in_array($status, $allowed_statuses)) {
            flash('error', 'Invalid status.'); header('Location: /hobbyloop/admin/feedback.php'); exit;
        }

        $pdo->prepare("
            UPDATE feedback_messages
            SET status=?, admin_reply=?, updated_at=NOW()
            WHERE id=?
        ")->execute([$status, $admin_reply ?: null, $fb_id]);

        // Notify user if admin replied
        if ($admin_reply) {
            $uid = $pdo->prepare("SELECT user_id, subject FROM feedback_messages WHERE id=?");
            $uid->execute([$fb_id]);
            $fb_row = $uid->fetch();
            if ($fb_row) {
                $pdo->prepare("INSERT INTO notifications (user_id, icon, text) VALUES (?,?,?)")
                    ->execute([$fb_row['user_id'], '💬', 'Admin replied to your feedback: "' . mb_substr($fb_row['subject'], 0, 40) . '"']);
            }
        }

        log_admin_action($pdo, 'update', 'feedback', $fb_id, ['status' => $status, 'replied' => (bool)$admin_reply]);
        flash('success', 'Feedback updated.');
        header('Location: /hobbyloop/admin/feedback.php'); exit;
    }
}

// ── Filters ──
$filter_status = $_GET['status'] ?? '';
$page    = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 20;
$offset  = ($page - 1) * $per_page;

$where = []; $params = [];
if ($filter_status) { $where[] = 'fm.status = ?'; $params[] = $filter_status; }
$sql_where = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = $pdo->prepare("SELECT COUNT(*) FROM feedback_messages fm $sql_where");
$total->execute($params);
$total_count = (int) $total->fetchColumn();
$pages = max(1, (int) ceil($total_count / $per_page));

$stmt = $pdo->prepare("
    SELECT fm.*, u.first_name, u.last_name, u.email
    FROM feedback_messages fm
    JOIN users u ON u.id = fm.user_id
    $sql_where
    ORDER BY FIELD(fm.status,'open','in_progress','resolved','closed'), fm.created_at DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$tickets = $stmt->fetchAll();

// Detail view
$detail = null;
if (!empty($_GET['id'])) {
    $dq = $pdo->prepare("
        SELECT fm.*, u.first_name, u.last_name, u.email
        FROM feedback_messages fm
        JOIN users u ON u.id = fm.user_id
        WHERE fm.id=?
    ");
    $dq->execute([(int)$_GET['id']]);
    $detail = $dq->fetch();
}

$statuses = ['open','in_progress','resolved','closed'];
$status_classes = ['open'=>'badge-red','in_progress'=>'badge-orange','resolved'=>'badge-green','closed'=>'badge-gray'];
require_once __DIR__ . '/includes/sidebar.php';
?>

<?php if ($detail): ?>
<div class="gap-2" style="margin-bottom:16px">
    <a href="/hobbyloop/admin/feedback.php" class="btn btn-gray btn-sm">← Back</a>
</div>

<div class="two-col">
    <div>
        <div class="card">
            <div class="card-header">Ticket #<?= $detail['id'] ?> — <?= htmlspecialchars($detail['subject']) ?></div>
            <div class="card-body">
                <p><strong>From:</strong> <?= htmlspecialchars($detail['first_name'] . ' ' . $detail['last_name']) ?> &lt;<?= htmlspecialchars($detail['email']) ?>&gt;</p>
                <p><strong>Date:</strong> <?= date('M j, Y g:i A', strtotime($detail['created_at'])) ?></p>
                <p><strong>Status:</strong> <span class="badge <?= $status_classes[$detail['status']] ?? 'badge-gray' ?>"><?= htmlspecialchars($detail['status']) ?></span></p>
                <hr style="margin:12px 0;border:none;border-top:1px solid var(--border)">
                <div style="background:#f8fafc;padding:12px;border-radius:6px;font-size:13px;line-height:1.6;white-space:pre-wrap"><?= htmlspecialchars($detail['message']) ?></div>
                <?php if ($detail['admin_reply']): ?>
                <hr style="margin:12px 0;border:none;border-top:1px solid var(--border)">
                <p><strong>Admin Reply:</strong></p>
                <div style="background:#d1f2eb;padding:12px;border-radius:6px;font-size:13px;line-height:1.6;white-space:pre-wrap"><?= htmlspecialchars($detail['admin_reply']) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div>
        <div class="card">
            <div class="card-header">Respond</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="fb_id" value="<?= $detail['id'] ?>">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <?php foreach ($statuses as $s): ?>
                            <option value="<?= $s ?>" <?= $detail['status'] === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $s)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Admin Reply</label>
                        <textarea name="admin_reply" rows="6" placeholder="Write your response here..."><?= htmlspecialchars($detail['admin_reply'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Response</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- Ticket List -->
<div class="filters">
    <form method="GET" style="display:contents">
        <select name="status">
            <option value="">All Status</option>
            <?php foreach ($statuses as $s): ?>
            <option value="<?= $s ?>" <?= $filter_status === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-gray btn-sm">Filter</button>
        <a href="/hobbyloop/admin/feedback.php" class="btn btn-gray btn-sm">Reset</a>
    </form>
</div>

<div class="card mb-0">
    <div class="card-header"><?= number_format($total_count) ?> Tickets</div>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>ID</th><th>User</th><th>Subject</th><th>Status</th>
                <th>Replied</th><th>Date</th><th>Actions</th>
            </tr></thead>
            <tbody>
            <?php if (empty($tickets)): ?>
                <tr><td colspan="7" class="text-center text-muted" style="padding:24px">No feedback tickets.</td></tr>
            <?php else: ?>
            <?php foreach ($tickets as $t): ?>
            <tr>
                <td class="text-muted"><?= $t['id'] ?></td>
                <td>
                    <?= htmlspecialchars($t['first_name'] . ' ' . $t['last_name']) ?><br>
                    <span class="text-muted"><?= htmlspecialchars($t['email']) ?></span>
                </td>
                <td><?= htmlspecialchars($t['subject']) ?></td>
                <td><span class="badge <?= $status_classes[$t['status']] ?? 'badge-gray' ?>"><?= htmlspecialchars(str_replace('_',' ',$t['status'])) ?></span></td>
                <td><?= $t['admin_reply'] ? '<span class="badge badge-green">Yes</span>' : '<span class="badge badge-gray">No</span>' ?></td>
                <td class="text-muted"><?= date('M j, Y', strtotime($t['created_at'])) ?></td>
                <td><a href="?id=<?= $t['id'] ?>" class="btn btn-gray btn-xs">View / Reply</a></td>
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
    <a href="?page=<?= $i ?>&status=<?= urlencode($filter_status) ?>"
       class="<?= $i === $page ? 'current' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
