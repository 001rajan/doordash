<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_login();

$uid = (int) current_user_id();

$sql = 'SELECT c.id AS cart_id, c.quantity, m.id AS menu_item_id, m.name, m.price, m.image_url,
        r.id AS restaurant_id, r.name AS restaurant_name, r.slug, r.prep_time_mins, r.distance_km, r.traffic_delay_mins
        FROM cart c
        JOIN menu_items m ON m.id = c.menu_item_id
        JOIN restaurants r ON r.id = m.restaurant_id
        WHERE c.user_id = ?
        ORDER BY c.id';
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('i', $uid);
$stmt->execute();
$lines = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$subtotal = 0.0;
foreach ($lines as $ln) {
    $subtotal += (float) $ln['price'] * (int) $ln['quantity'];
}
$delivery = DELIVERY_FEE_FLAT;
$tax = round($subtotal * TAX_RATE, 2);
$total = $subtotal + $delivery + $tax;

$eta = null;
if ($lines) {
    $r0 = $lines[0];
    $eta = calculate_smart_eta(
        (int) $r0['prep_time_mins'],
        (float) $r0['distance_km'],
        (int) $r0['traffic_delay_mins']
    );
}

$pageTitle = 'Your cart';
require __DIR__ . '/includes/header.php';
?>

<section class="section container" style="padding-top:1.5rem">
    <h1 style="margin-top:0">Cart</h1>

    <?php if (!$lines): ?>
        <div class="empty-state">
            <p>Your cart is empty.</p>
            <a class="btn btn-primary" href="<?php echo e(BASE_URL); ?>/restaurants.php">Browse restaurants</a>
        </div>
    <?php else: ?>
        <div class="cart-layout">
            <div>
                <?php foreach ($lines as $ln): ?>
                    <div class="menu-item" style="margin-bottom:0.75rem">
                        <div class="menu-thumb"><img src="<?php echo e($ln['image_url']); ?>" alt=""></div>
                        <div class="menu-info">
                            <h3><?php echo e($ln['name']); ?></h3>
                            <p style="color:var(--text-muted)"><?php echo e($ln['restaurant_name']); ?></p>
                            <div class="menu-price">₹<?php echo e(number_format((float) $ln['price'] * (int) $ln['quantity'], 2)); ?></div>
                        </div>
                        <form method="post" action="<?php echo e(BASE_URL); ?>/cart-actions.php" class="qty-row" style="flex-wrap:wrap">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="cart_id" value="<?php echo (int) $ln['cart_id']; ?>">
                            <input type="hidden" name="redirect" value="<?php echo e(BASE_URL . '/cart.php'); ?>">
                            <button type="submit" name="qty" value="<?php echo max(0, (int) $ln['quantity'] - 1); ?>" class="qty-btn" aria-label="Decrease">−</button>
                            <span><?php echo (int) $ln['quantity']; ?></span>
                            <button type="submit" name="qty" value="<?php echo (int) $ln['quantity'] + 1; ?>" class="qty-btn" aria-label="Increase">+</button>
                        </form>
                    </div>
                <?php endforeach; ?>

                <form method="post" action="<?php echo e(BASE_URL); ?>/cart-actions.php" onsubmit="return confirm('Clear entire cart?');">
                    <input type="hidden" name="action" value="clear">
                    <input type="hidden" name="redirect" value="<?php echo e(BASE_URL . '/cart.php'); ?>">
                    <button type="submit" class="btn btn-ghost btn-sm" style="margin-top:0.5rem">Clear cart</button>
                </form>
            </div>

            <div class="card" style="padding:1.25rem;position:sticky;top:5rem">
                <h2 style="margin-top:0;font-size:1.1rem">Bill summary</h2>
                <div class="summary-row"><span>Subtotal</span><span>₹<?php echo e(number_format($subtotal, 2)); ?></span></div>
                <div class="summary-row"><span>Delivery</span><span>₹<?php echo e(number_format($delivery, 2)); ?></span></div>
                <div class="summary-row"><span>Tax (5%)</span><span>₹<?php echo e(number_format($tax, 2)); ?></span></div>
                <?php if ($eta !== null): ?>
                    <p style="margin:1rem 0"><span class="eta-pill">Estimated delivery: <?php echo $eta; ?> min</span></p>
                <?php endif; ?>
                <div class="summary-row total"><span>Total</span><span>₹<?php echo e(number_format($total, 2)); ?></span></div>
                <a class="btn btn-primary btn-block" style="margin-top:1rem" href="<?php echo e(BASE_URL); ?>/checkout.php">Checkout</a>
            </div>
        </div>
    <?php endif; ?>
</section>


<?php require __DIR__ . '/includes/footer.php'; ?>
