<?php
// ═══════════════════════════════════════════
// /api/community/posts.php
// GET  — list posts (with author, like status, product tag, follow status)
// POST — create new post
// ═══════════════════════════════════════════
require_once __DIR__ . '/../helpers.php';

$user = require_auth($pdo);
$method = $_SERVER['REQUEST_METHOD'];

// ── Helper: relative time ──
function relative_time(string $ts): string {
    $diff = time() - strtotime($ts);
    if ($diff < 60)         return 'Just now';
    if ($diff < 3600)       return floor($diff / 60) . 'm ago';
    if ($diff < 86400)      return floor($diff / 3600) . 'h ago';
    if ($diff < 604800)     return floor($diff / 86400) . 'd ago';
    return date('M j', strtotime($ts));
}

// ── GET — fetch posts ──
if ($method === 'GET') {
    $stmt = $pdo->prepare("
        SELECT cp.id, cp.text, cp.likes_count, cp.comments_count, cp.created_at,
               cp.tagged_product_id,
               u.id AS author_id,
               u.first_name, u.last_name,
               u.avatar_initials, u.avatar_color,
               (SELECT COUNT(*) FROM post_likes pl
                WHERE pl.post_id = cp.id AND pl.user_id = ?) AS liked_by_me
        FROM community_posts cp
        JOIN users u ON u.id = cp.user_id
        ORDER BY cp.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$user['id']]);
    $posts = $stmt->fetchAll();

    // Fetch tagged product data for posts that have one
    $tagged_ids = array_filter(array_column($posts, 'tagged_product_id'));
    $product_map = [];
    if (!empty($tagged_ids)) {
        $placeholders = implode(',', array_fill(0, count($tagged_ids), '?'));
        $stmt = $pdo->prepare("
            SELECT p.id, p.name, p.price, p.image_url AS img, p.bg_gradient AS bg,
                   p.condition_label AS cond, p.rating
            FROM products p WHERE p.id IN ($placeholders)
        ");
        $stmt->execute(array_values($tagged_ids));
        foreach ($stmt->fetchAll() as $p) {
            $product_map[$p['id']] = $p;
        }
    }

    // Fetch who current user follows
    $stmt = $pdo->prepare("SELECT following_id FROM user_follows WHERE follower_id = ?");
    $stmt->execute([$user['id']]);
    $following_ids = array_column($stmt->fetchAll(), 'following_id');
    $following_set = array_flip($following_ids);

    $result = array_map(function ($p) use ($product_map, $following_set) {
        $product = null;
        if ($p['tagged_product_id'] && isset($product_map[$p['tagged_product_id']])) {
            $raw = $product_map[$p['tagged_product_id']];
            $product = [
                'id'    => (int)$raw['id'],
                'name'  => $raw['name'],
                'price' => (float)$raw['price'],
                'img'   => $raw['img'],
                'bg'    => $raw['bg'],
                'cond'  => $raw['cond'],
                'rating'=> (float)$raw['rating'],
            ];
        }
        return [
            'id'            => (int)$p['id'],
            'author_id'     => (int)$p['author_id'],
            'author'        => $p['first_name'] . ' ' . $p['last_name'],
            'initials'      => $p['avatar_initials'] ?: strtoupper(substr($p['first_name'],0,1) . substr($p['last_name'],0,1)),
            'color'         => $p['avatar_color'] ?: '#0D7C6E',
            'handle'        => '@' . strtolower(str_replace(' ', '', $p['first_name'] . $p['last_name'])),
            'time'          => relative_time($p['created_at']),
            'text'          => $p['text'],
            'likes'         => (int)$p['likes_count'],
            'comments'      => (int)$p['comments_count'],
            'liked'         => (bool)$p['liked_by_me'],
            'product'       => $product,
            'you_follow'    => isset($following_set[$p['author_id']]),
        ];
    }, $posts);

    // Suggested sellers: sellers the current user does NOT follow yet (exclude self)
    $stmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, u.avatar_initials, u.avatar_color,
               s.badge, s.total_sales
        FROM sellers s
        JOIN users u ON u.id = s.user_id
        WHERE u.id != ?
          AND u.id NOT IN (
              SELECT following_id FROM user_follows WHERE follower_id = ?
          )
        ORDER BY s.total_sales DESC
        LIMIT 4
    ");
    $stmt->execute([$user['id'], $user['id']]);
    $suggested = array_map(function ($s) {
        return [
            'id'       => (int)$s['id'],
            'name'     => $s['first_name'] . ' ' . $s['last_name'],
            'initials' => $s['avatar_initials'] ?: strtoupper(substr($s['first_name'],0,1) . substr($s['last_name'],0,1)),
            'color'    => $s['avatar_color'] ?: '#0D7C6E',
            'badge'    => $s['badge'],
            'sales'    => (int)$s['total_sales'],
        ];
    }, $stmt->fetchAll());

    json_success(['posts' => $result, 'suggested' => $suggested]);
}

// ── POST — create post ──
if ($method === 'POST') {
    $body = get_json_body();
    $text = isset($body['text']) ? trim($body['text']) : '';
    if ($text === '') {
        json_error('Post text is required');
    }
    if (mb_strlen($text) > 500) {
        json_error('Post is too long (max 500 characters)');
    }

    $tagged_product_id = isset($body['product_id']) ? (int)$body['product_id'] : null;
    if ($tagged_product_id) {
        // Verify product exists
        $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ? AND is_active = 1");
        $stmt->execute([$tagged_product_id]);
        if (!$stmt->fetch()) $tagged_product_id = null;
    }

    $stmt = $pdo->prepare("
        INSERT INTO community_posts (user_id, text, tagged_product_id)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$user['id'], $text, $tagged_product_id]);
    $post_id = $pdo->lastInsertId();

    json_success([
        'id'         => (int)$post_id,
        'author_id'  => (int)$user['id'],
        'author'     => $user['first_name'] . ' ' . $user['last_name'],
        'initials'   => $user['avatar_initials'],
        'color'      => $user['avatar_color'],
        'handle'     => '@' . strtolower(str_replace(' ', '', $user['first_name'] . $user['last_name'])),
        'time'       => 'Just now',
        'text'       => $text,
        'likes'      => 0,
        'comments'   => 0,
        'liked'      => false,
        'product'    => null,
        'you_follow' => false,
    ], 201);
}

json_error('Method not allowed', 405);
