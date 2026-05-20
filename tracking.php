<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_login();

$uid = (int) current_user_id();
$oid = (int) ($_GET['id'] ?? 0);
$order = null;
if ($oid > 0) {
    $stmt = $mysqli->prepare(
        'SELECT o.*, r.name AS restaurant_name FROM orders o JOIN restaurants r ON r.id = o.restaurant_id WHERE o.id = ? AND o.user_id = ?'
    );
    $stmt->bind_param('ii', $oid, $uid);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
}

$steps = [
    'preparing' => ['label' => 'Preparing', 'idx' => 0],
    'packed' => ['label' => 'Packed', 'idx' => 1],
    'out_for_delivery' => ['label' => 'Out for delivery', 'idx' => 2],
    'delivered' => ['label' => 'Delivered', 'idx' => 3],
];

$pageTitle = 'Track order';
require __DIR__ . '/includes/header.php';

$currentIdx = $order ? ($steps[$order['status']]['idx'] ?? 0) : -1;
?>

<section class="section container" style="padding-top:1.5rem">
    <h1 style="margin-top:0">Order tracking</h1>

    <?php if (!$order): ?>
        <div class="empty-state">
            <p>We couldn’t find that order.</p>
            <a class="btn btn-primary" href="<?php echo e(BASE_URL); ?>/index.php">Home</a>
        </div>
    <?php else: ?>
        <div class="card" style="padding:1.5rem;margin-bottom:1.5rem">
            <p style="color:var(--text-muted);margin:0">Order #<?php echo (int) $order['id']; ?> · <?php echo e($order['restaurant_name']); ?></p>
            <h2 style="margin:0.5rem 0 0"><?php echo e($order['delivery_address']); ?></h2>
            <p style="margin-top:0.75rem"><span class="eta-pill">Smart ETA at order: <?php echo (int) $order['eta_minutes']; ?> min</span></p>
        </div>

        <div class="tracker">
            <?php foreach (array_values($steps) as $i => $s): ?>
                <div class="tracker-step <?php echo $i < $currentIdx ? 'done' : ($i === $currentIdx ? 'active' : ''); ?>">
                    <div class="tracker-dot"></div>
                    <?php echo e($s['label']); ?>
                </div>
            <?php endforeach; ?>
        </div>

        <p style="text-align:center;color:var(--text-muted)">Current status: <strong style="color:var(--text)"><?php echo e(str_replace('_', ' ', $order['status'])); ?></strong></p>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
