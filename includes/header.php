<?php
declare(strict_types=1);
$pageTitle = $pageTitle ?? SITE_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle); ?> — <?php echo e(SITE_NAME); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo e(BASE_URL); ?>/assets/css/style.css">
</head>
<body>
<div class="page-loader" id="pageLoader" aria-hidden="true"><div class="loader-spin"></div></div>
<div id="toastStack" class="toast-stack" aria-live="polite"></div>
<header class="site-header">
    <div class="container header-inner">
        <a class="logo" href="<?php echo e(BASE_URL); ?>/index.php">
            <span class="logo-mark">⚡</span> <?php echo e(SITE_NAME); ?>
        </a>
        <button class="nav-toggle" type="button" aria-label="Menu" id="navToggle">☰</button>
        <nav class="main-nav" id="mainNav">
            <a href="<?php echo e(BASE_URL); ?>/index.php">Home</a>
            <a href="<?php echo e(BASE_URL); ?>/restaurants.php">Restaurants</a>
            <?php if (is_logged_in()): ?>
                <a href="<?php echo e(BASE_URL); ?>/group-order.php">Group Order</a>
                <a href="<?php echo e(BASE_URL); ?>/cart.php">Cart</a>
                <a href="<?php echo e(BASE_URL); ?>/tracking.php">Track</a>
                <?php if (is_admin()): ?>
                    <a href="<?php echo e(BASE_URL); ?>/admin/dashboard.php">Admin</a>
                <?php endif; ?>
                <span class="nav-user"><?php echo e((string) current_user_name()); ?></span>
                <a class="btn btn-ghost btn-sm" href="<?php echo e(BASE_URL); ?>/logout.php">Logout</a>
            <?php else: ?>
                <a href="<?php echo e(BASE_URL); ?>/login.php">Login</a>
                <a class="btn btn-primary btn-sm" href="<?php echo e(BASE_URL); ?>/register.php">Sign up</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="main-content">
