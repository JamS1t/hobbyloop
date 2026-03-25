<?php
$page_title = 'Community Moderation';
require_once __DIR__ . '/includes/header.php';

// ── Handle delete ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_editor();
    $action  = $_POST['action'] ?? '';
    $post_id = (int) ($_POST['post_id'] ?? 0);

    if ($action === 'delete' && $post_id) {
        $post = $pdo->prepare("SELECT text FROM community_posts WHERE id=?");
        $post->execute([$post_id]);
        $post = $post->fetch();

        $pdo->prepare("DELETE FROM community_posts WHERE id=?")->execute([$post_id]);
        log_admin_action($pdo, 'delete', 'community_post', $post_id, ['text_preview' => mb_substr($post['text'] ?? '', 0, 80)]);
        flash('success', 'Post deleted.');
    }
    header('Location: /hobbyloop/admin/community.php'); exit;
}

// ── Filters ──
$search  = trim($_GET['q'] ?? '');
$page    = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 20;
$offset  = ($page - 1) * $per_page;

$where = []; $params = [];
if ($search) {
    $where[] = '(cp.text LIKE ? OR u.first_name LIKE ? OR u.email LIKE ?)';
    $params  = ["%$search%", "%$search%", "%$search%"];
}
$sql_where = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = $pdo->prepare("SELECT COUNT(*) FROM community_posts cp JOIN users u ON u.id = cp.user_id $sql_where");
$total->execute($params);
$total_count = (int) $total->fetchColumn();
$pages = max(1, (int) ceil($total_count / $per_page));

$stmt = $pdo->prepare("
    SELECT cp.id, cp.text, cp.likes_count, cp.comments_count, cp.created_at,
           u.first_name, u.last_name, u.email,
           p.name AS tagged_product
    FROM community_posts cp
    JOIN users u ON u.id = cp.user_id
    LEFT JOIN products p ON p.id = cp.tagged_product_id
    $sql_where
    ORDER BY cp.created_at DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$posts = $stmt->fetchAll();

require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="filters">
    <form method="GET" style="display:contents">
        <input type="text" name="q" placeholder="Search posts or users..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn btn-gray btn-sm">Search</button>
        <a href="/hobbyloop/admin/community.php" class="btn btn-gray btn-sm">Reset</a>
    </form>
</div>

<div class="card mb-0">
    <div class="card-header"><?= number_format($total_count) ?> Posts</div>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>ID</th><th>Author</th><th>Content</th><th>Tagged Product</th>
                <th>Likes</th><th>Date</th><th>Actions</th>
            </tr></thead>
            <tbody>
            <?php if (empty($posts)): ?>
                <tr><td colspan="7" class="text-center text-muted" style="padding:24px">No posts found.</td></tr>
            <?php else: ?>
            <?php foreach ($posts as $p): ?>
            <tr>
                <td class="text-muted"><?= $p['id'] ?></td>
                <td>
                    <?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?><br>
                    <span class="text-muted"><?= htmlspecialchars($p['email']) ?></span>
                </td>
                <td style="max-width:300px;word-break:break-word"><?= htmlspecialchars(mb_substr($p['text'], 0, 120)) ?><?= mb_strlen($p['text']) > 120 ? '…' : '' ?></td>
                <td><?= $p['tagged_product'] ? htmlspecialchars($p['tagged_product']) : '<span class="text-muted">—</span>' ?></td>
                <td><?= $p['likes_count'] ?></td>
                <td class="text-muted"><?= date('M j, Y g:i A', strtotime($p['created_at'])) ?></td>
                <td>
                    <form method="POST" onsubmit="return confirm('Delete this post?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="post_id" value="<?= $p['id'] ?>">
                        <button class="btn btn-danger btn-xs">Delete</button>
                    </form>
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
    <a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>" class="<?= $i === $page ? 'current' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
