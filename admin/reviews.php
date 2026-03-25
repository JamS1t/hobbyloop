<?php
$page_title = 'Review Moderation';
require_once __DIR__ . '/includes/header.php';

// ── Handle POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_editor();
    $action    = $_POST['action'] ?? '';
    $review_id = (int) ($_POST['review_id'] ?? 0);

    if ($action === 'approve') {
        $pdo->prepare("UPDATE reviews SET is_approved=1 WHERE id=?")->execute([$review_id]);
        // Recalculate product rating
        $pid = $pdo->prepare("SELECT product_id FROM reviews WHERE id=?");
        $pid->execute([$review_id]); $pid = $pid->fetchColumn();
        if ($pid) {
            $pdo->prepare("
                UPDATE products SET
                    rating = (SELECT ROUND(AVG(rating),1) FROM reviews WHERE product_id=? AND is_approved=1),
                    review_count = (SELECT COUNT(*) FROM reviews WHERE product_id=? AND is_approved=1)
                WHERE id=?
            ")->execute([$pid, $pid, $pid]);
        }
        log_admin_action($pdo, 'approve', 'review', $review_id);
        flash('success', 'Review approved.');
        header('Location: /hobbyloop/admin/reviews.php'); exit;
    }

    if ($action === 'reject') {
        $pdo->prepare("UPDATE reviews SET is_approved=0 WHERE id=?")->execute([$review_id]);
        $pid = $pdo->prepare("SELECT product_id FROM reviews WHERE id=?");
        $pid->execute([$review_id]); $pid = $pid->fetchColumn();
        if ($pid) {
            $pdo->prepare("
                UPDATE products SET
                    rating = COALESCE((SELECT ROUND(AVG(rating),1) FROM reviews WHERE product_id=? AND is_approved=1), 0),
                    review_count = (SELECT COUNT(*) FROM reviews WHERE product_id=? AND is_approved=1)
                WHERE id=?
            ")->execute([$pid, $pid, $pid]);
        }
        log_admin_action($pdo, 'reject', 'review', $review_id);
        flash('success', 'Review rejected.');
        header('Location: /hobbyloop/admin/reviews.php'); exit;
    }

    if ($action === 'delete') {
        require_super();
        $pid = $pdo->prepare("SELECT product_id FROM reviews WHERE id=?");
        $pid->execute([$review_id]); $pid = $pid->fetchColumn();
        $pdo->prepare("DELETE FROM reviews WHERE id=?")->execute([$review_id]);
        if ($pid) {
            $pdo->prepare("
                UPDATE products SET
                    rating = COALESCE((SELECT ROUND(AVG(rating),1) FROM reviews WHERE product_id=? AND is_approved=1), 0),
                    review_count = (SELECT COUNT(*) FROM reviews WHERE product_id=? AND is_approved=1)
                WHERE id=?
            ")->execute([$pid, $pid, $pid]);
        }
        log_admin_action($pdo, 'delete', 'review', $review_id);
        flash('success', 'Review deleted.');
        header('Location: /hobbyloop/admin/reviews.php'); exit;
    }
}

// ── Filters ──
$filter_status  = $_GET['status'] ?? '';
$filter_rating  = $_GET['rating'] ?? '';
$filter_product = trim($_GET['product'] ?? '');
$page    = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 20;
$offset  = ($page - 1) * $per_page;

$where = []; $params = [];
if ($filter_status === 'approved')   { $where[] = 'r.is_approved = 1'; }
if ($filter_status === 'pending')    { $where[] = 'r.is_approved = 0'; }
if ($filter_rating)                  { $where[] = 'r.rating = ?'; $params[] = (int)$filter_rating; }
if ($filter_product)                 { $where[] = 'p.name LIKE ?'; $params[] = "%$filter_product%"; }
$sql_where = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = $pdo->prepare("
    SELECT COUNT(*) FROM reviews r
    JOIN products p ON p.id = r.product_id
    JOIN users u ON u.id = r.user_id
    $sql_where
");
$total->execute($params);
$total_count = (int) $total->fetchColumn();
$pages = max(1, (int) ceil($total_count / $per_page));

$stmt = $pdo->prepare("
    SELECT r.id, r.rating, r.comment, r.is_approved, r.created_at,
           p.id AS product_id, p.name AS product_name,
           u.first_name, u.last_name
    FROM reviews r
    JOIN products p ON p.id = r.product_id
    JOIN users u ON u.id = r.user_id
    $sql_where
    ORDER BY r.is_approved ASC, r.created_at DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$reviews = $stmt->fetchAll();

require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="filters">
    <form method="GET" style="display:contents">
        <input type="text" name="product" placeholder="Filter by product..." value="<?= htmlspecialchars($filter_product) ?>">
        <select name="status">
            <option value="">All Status</option>
            <option value="approved" <?= $filter_status === 'approved' ? 'selected' : '' ?>>Approved</option>
            <option value="pending"  <?= $filter_status === 'pending'  ? 'selected' : '' ?>>Pending</option>
        </select>
        <select name="rating">
            <option value="">All Ratings</option>
            <?php for ($i = 5; $i >= 1; $i--): ?>
            <option value="<?= $i ?>" <?= $filter_rating == $i ? 'selected' : '' ?>><?= $i ?> Stars</option>
            <?php endfor; ?>
        </select>
        <button type="submit" class="btn btn-gray btn-sm">Filter</button>
        <a href="/hobbyloop/admin/reviews.php" class="btn btn-gray btn-sm">Reset</a>
    </form>
</div>

<div class="card mb-0">
    <div class="card-header"><?= number_format($total_count) ?> Reviews</div>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>ID</th><th>Product</th><th>Reviewer</th><th>Rating</th>
                <th>Comment</th><th>Status</th><th>Date</th><th>Actions</th>
            </tr></thead>
            <tbody>
            <?php if (empty($reviews)): ?>
                <tr><td colspan="8" class="text-center text-muted" style="padding:24px">No reviews found.</td></tr>
            <?php else: ?>
            <?php foreach ($reviews as $r): ?>
            <tr>
                <td class="text-muted"><?= $r['id'] ?></td>
                <td><?= htmlspecialchars($r['product_name']) ?></td>
                <td><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></td>
                <td><?= str_repeat('★', $r['rating']) ?><?= str_repeat('☆', 5 - $r['rating']) ?></td>
                <td style="max-width:240px;word-break:break-word"><?= htmlspecialchars(mb_substr($r['comment'] ?? '', 0, 100)) ?><?= mb_strlen($r['comment'] ?? '') > 100 ? '…' : '' ?></td>
                <td><span class="badge <?= $r['is_approved'] ? 'badge-green' : 'badge-orange' ?>"><?= $r['is_approved'] ? 'Approved' : 'Pending' ?></span></td>
                <td class="text-muted"><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
                <td>
                    <div class="gap-2">
                        <?php if (!$r['is_approved']): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="review_id" value="<?= $r['id'] ?>">
                            <button class="btn btn-success btn-xs">Approve</button>
                        </form>
                        <?php else: ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="review_id" value="<?= $r['id'] ?>">
                            <button class="btn btn-warning btn-xs">Reject</button>
                        </form>
                        <?php endif; ?>
                        <?php if (is_super()): ?>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this review?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="review_id" value="<?= $r['id'] ?>">
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
    <a href="?page=<?= $i ?>&status=<?= urlencode($filter_status) ?>&rating=<?= urlencode($filter_rating) ?>&product=<?= urlencode($filter_product) ?>"
       class="<?= $i === $page ? 'current' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
