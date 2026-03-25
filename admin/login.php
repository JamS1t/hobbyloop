<?php
// ── Admin Login ──
if (session_status() === PHP_SESSION_NONE) session_start();

// Already logged in
if (!empty($_SESSION['admin_id'])) {
    header('Location: /hobbyloop/admin/index.php');
    exit;
}

require_once __DIR__ . '/../api/config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Email and password are required.';
    } else {
        $stmt = $pdo->prepare("
            SELECT id, first_name, last_name, password_hash, role, admin_level
            FROM users
            WHERE email = ? AND role = 'admin'
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $error = 'Invalid credentials or insufficient permissions.';
        } else {
            $_SESSION['admin_id']    = $user['id'];
            $_SESSION['admin_name']  = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['admin_level'] = $user['admin_level'];

            // Log login action
            $pdo->prepare("
                INSERT INTO system_logs (admin_id, action, entity_type, entity_id, details, ip_address)
                VALUES (?, 'login', 'session', NULL, '{}', ?)
            ")->execute([$user['id'], $_SERVER['REMOTE_ADDR'] ?? null]);

            header('Location: /hobbyloop/admin/index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login — HobbyLoop</title>
    <link rel="stylesheet" href="/hobbyloop/admin/admin.css">
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <div class="login-logo">
            <div class="logo-mark">HL</div>
            <h2>HobbyLoop Admin</h2>
            <p>Sign in to manage the marketplace</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       placeholder="admin@hobbyloop.ph">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required placeholder="••••••••">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">
                Sign In
            </button>
        </form>
    </div>
</div>
</body>
</html>
