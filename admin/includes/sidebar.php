<?php
// ── Admin Sidebar ──
// Included after header.php, before page content.

$_current = basename($_SERVER['PHP_SELF']);

$_nav = [
    ['file' => 'index.php',     'label' => 'Dashboard',  'icon' => '▣'],
    ['file' => 'products.php',  'label' => 'Products',   'icon' => '◈'],
    ['file' => 'orders.php',    'label' => 'Orders',     'icon' => '◫'],
    ['file' => 'users.php',     'label' => 'Users',      'icon' => '◑'],
    ['file' => 'inventory.php', 'label' => 'Inventory',  'icon' => '◧'],
    ['file' => 'community.php', 'label' => 'Community',  'icon' => '◎'],
    ['file' => 'analytics.php', 'label' => 'Analytics',  'icon' => '◈'],
    ['file' => 'promos.php',    'label' => 'Promos',     'icon' => '◆'],
    ['file' => 'reviews.php',   'label' => 'Reviews',    'icon' => '★'],
    ['file' => 'feedback.php',  'label' => 'Feedback',   'icon' => '◉'],
    ['file' => 'logs.php',      'label' => 'System Logs','icon' => '≡'],
];
?>
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-logo">HL</div>
        <span class="brand-text">Admin Panel</span>
    </div>
    <nav class="sidebar-nav">
        <?php foreach ($_nav as $item): ?>
        <a href="/hobbyloop/admin/<?= $item['file'] ?>"
           class="nav-item <?= $_current === $item['file'] ? 'active' : '' ?>">
            <span class="nav-icon"><?= $item['icon'] ?></span>
            <?= $item['label'] ?>
        </a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">
        <div class="admin-info">
            <div class="admin-name"><?= $_admin_name ?></div>
            <div class="admin-level"><?= $_admin_level ?></div>
        </div>
        <a href="/hobbyloop/admin/logout.php" class="logout-btn">Logout</a>
    </div>
</aside>
<main class="main-content">
    <div class="page-header">
        <h1><?= htmlspecialchars($page_title ?? 'Dashboard', ENT_QUOTES) ?></h1>
        <?php if (!empty($page_actions)): ?>
        <div class="gap-2"><?= $page_actions ?></div>
        <?php endif; ?>
    </div>
    <div class="content">
        <?php
        $flash = get_flash();
        if ($flash):
            $type = $flash['type'] === 'error' ? 'alert-error' : 'alert-success';
        ?>
        <div class="alert <?= $type ?>"><?= htmlspecialchars($flash['message'], ENT_QUOTES) ?></div>
        <?php endif; ?>
