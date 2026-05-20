<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_login();

$oid = (int) ($_GET['id'] ?? 0);
$order = null;
if ($oid > 0) {
    $stmt = $mysqli->prepare(
        'SELECT o.*, r.name AS restaurant_name FROM orders o JOIN restaurants r ON r.id = o.restaurant_id WHERE o.id = ? AND o.user_id = ?'
    );
    $uid = (int) $_SESSION['user_id'];
    $stmt->bind_param('ii', $oid, $uid);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
}

$pageTitle = 'Order placed';
require __DIR__ . '/includes/header.php';
?>

<section class="section container" style="padding-top:2rem;text-align:center">
    <?php if (!$order): ?>
        <h1>Order not found</h1>
        <a class="btn btn-primary" href="<?php echo e(BASE_URL); ?>/index.php">Home</a>
    <?php else: ?>
        <div class="card" style="max-width:520px;margin:0 auto;padding:2rem">
            <h1 style="margin-top:0;color:var(--success)">You’re all set!</h1>
            <p style="color:var(--text-muted)">Order #<?php echo (int) $order['id']; ?> · <?php echo e($order['restaurant_name']); ?></p>
            <p><span class="eta-pill">Estimated delivery: <?php echo (int) $order['eta_minutes']; ?> min</span></p>
            <p style="margin-top:1.5rem">Total paid: <strong>₹<?php echo e(number_format((float) $order['total'], 2)); ?></strong></p>
            <div style="margin-top:2rem;display:flex;gap:0.75rem;justify-content:center;flex-wrap:wrap">
                <a class="btn btn-primary" href="<?php echo e(BASE_URL); ?>/tracking.php?id=<?php echo (int) $order['id']; ?>">Track order</a>
                <a class="btn btn-ghost" href="<?php echo e(BASE_URL); ?>/index.php">Back home</a>
            </div>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
