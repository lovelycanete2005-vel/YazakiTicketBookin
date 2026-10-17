<?php
require_once __DIR__ . '/auth.php';
$page_title = $page_title ?? "Yazaki Ticket Bookin'";
$user = current_user();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> | Yazaki Ticket Bookin'</title>
    <link rel="stylesheet" href="style.css?v=<?= (int) filemtime(__DIR__ . '/style.css') ?>">
</head>
<body>
<header class="site-header">
    <a class="brand" href="index.php"><span class="brand-mark">YTB</span> Yazaki Ticket Bookin'</a>
    <nav>
        <?php if ($user): ?>
            <details class="profile-menu">
                <summary aria-label="Open profile menu"><span class="profile-avatar"><?= e(strtoupper(substr((string) $user['name'], 0, 1))) ?></span><span><?= e($user['name']) ?></span><span aria-hidden="true">&#9662;</span></summary>
                <div class="profile-menu-content">
                    <div class="profile-email"><span>Signed in as</span><strong><?= e($user['email'] ?: 'No email added') ?></strong></div>
                    <a href="dashboard.php">Dashboard</a>
                    <?php if (is_admin()): ?><a href="admin.php">Admin console</a><?php endif; ?>
                    <a class="profile-logout" href="logout.php">Log out</a>
                </div>
            </details>
        <?php else: ?>
            <a href="login.php">Log in</a>
            <a class="nav-button" href="register.php">Create account</a>
        <?php endif; ?>
    </nav>
</header>
<main class="page-shell">
