<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_login();

$uid = (int) current_user_id();
$groupId = (int) ($_GET['group'] ?? 0);

$lines = [];
$mode = $groupId > 0 ? 'group' : 'cart';

if ($mode === 'group') {
    $mchk = $mysqli->prepare('SELECT 1 FROM group_members WHERE group_order_id = ? AND user_id = ?');
    $mchk->bind_param('ii', $groupId, $uid);
    $mchk->execute();
    if (!$mchk->get_result()->fetch_row()) {
        redirect('/group-order.php');
    }
    $sql = 'SELECT gc.id AS cart_row_id, gc.quantity, gc.user_id, m.id AS menu_item_id, m.name, m.price,
            r.id AS restaurant_id, r.name AS restaurant_name, r.slug, r.prep_time_mins, r.distance_km, r.traffic_delay_mins
            FROM group_cart gc
            JOIN menu_items m ON m.id = gc.menu_item_id
            JOIN restaurants r ON r.id = m.restaurant_id
            WHERE gc.group_order_id = ?';
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $groupId);
    $stmt->execute();
    $lines = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $sql = 'SELECT c.id AS cart_row_id, c.quantity, c.user_id, m.id AS menu_item_id, m.name, m.price,
            r.id AS restaurant_id, r.name AS restaurant_name, r.slug, r.prep_time_mins, r.distance_km, r.traffic_delay_mins
            FROM cart c
            JOIN menu_items m ON m.id = c.menu_item_id
            JOIN restaurants r ON r.id = m.restaurant_id
            WHERE c.user_id = ?';
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $lines = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$error = '';
$restaurant = null;

if ($lines) {
    $rid0 = (int) $lines[0]['restaurant_id'];
    foreach ($lines as $ln) {
        if ((int) $ln['restaurant_id'] !== $rid0) {
            $error = 'Items must be from one restaurant only.';
            break;
        }
    }
    if (!$error) {
        $stmtR = $mysqli->prepare('SELECT * FROM restaurants WHERE id = ?');
        $stmtR->bind_param('i', $rid0);
        $stmtR->execute();
        $restaurant = $stmtR->get_result()->fetch_assoc();
    }
}

$subtotal = 0.0;
foreach ($lines as $ln) {
    $subtotal += (float) $ln['price'] * (int) $ln['quantity'];
}
$delivery = DELIVERY_FEE_FLAT;
$tax = round($subtotal * TAX_RATE, 2);
$total = $subtotal + $delivery + $tax;

$eta = $restaurant
    ? calculate_smart_eta(
        (int) $restaurant['prep_time_mins'],
        (float) $restaurant['distance_km'],
        (int) $restaurant['traffic_delay_mins']
    )
    : 30;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error && $lines && $restaurant) {
    $address = trim($_POST['address'] ?? '');
    $payment = trim($_POST['payment'] ?? 'card');
    if ($address === '') {
        $error = 'Please enter delivery address.';
    } else {
        $rid = (int) $restaurant['id'];
        $mysqli->begin_transaction();
        try {
            if ($mode === 'group') {
                $ins = $mysqli->prepare(
                    'INSERT INTO orders (user_id, restaurant_id, group_order_id, subtotal, delivery_fee, tax, total, eta_minutes, delivery_address, payment_method)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $ins->bind_param(
                    'iiidddddiss',
                    $uid,
                    $rid,
                    $groupId,
                    $subtotal,
                    $delivery,
                    $tax,
                    $total,
                    $eta,
                    $address,
                    $payment
                );
            } else {
                $ins = $mysqli->prepare(
                    'INSERT INTO orders (user_id, restaurant_id, subtotal, delivery_fee, tax, total, eta_minutes, delivery_address, payment_method)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $ins->bind_param(
                    'iidddddiss',
                    $uid,
                    $rid,
                    $subtotal,
                    $delivery,
                    $tax,
                    $total,
                    $eta,
                    $address,
                    $payment
                );
            }
            $ins->execute();
            $orderId = (int) $mysqli->insert_id;

            $oi = $mysqli->prepare(
                'INSERT INTO order_items (order_id, menu_item_id, quantity, price_each, added_by_user_id) VALUES (?, ?, ?, ?, ?)'
            );
            foreach ($lines as $ln) {
                $mid = (int) $ln['menu_item_id'];
                $q = (int) $ln['quantity'];
                $pe = (float) $ln['price'];
                $adder = (int) $ln['user_id'];
                $oi->bind_param('iiidi', $orderId, $mid, $q, $pe, $adder);
                $oi->execute();
            }

            if ($mode === 'group') {
                $del = $mysqli->prepare('DELETE FROM group_cart WHERE group_order_id = ?');
                $del->bind_param('i', $groupId);
                $del->execute();
                $up = $mysqli->prepare("UPDATE group_orders SET status = 'closed' WHERE id = ?");
                $up->bind_param('i', $groupId);
                $up->execute();
            } else {
                $del = $mysqli->prepare('DELETE FROM cart WHERE user_id = ?');
                $del->bind_param('i', $uid);
                $del->execute();
            }

            $mysqli->commit();
            redirect('/order-success.php?id=' . $orderId);
        } catch (Throwable $e) {
            $mysqli->rollback();
            $error = 'Could not place order. Please try again.';
        }
    }
}

$pageTitle = 'Checkout';
require __DIR__ . '/includes/header.php';
?>

<section class="section container" style="padding-top:1.5rem">
    <h1 style="margin-top:0">Checkout</h1>
    <?php if ($error): ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>

    <?php if (!$lines): ?>
        <div class="empty-state">
            <p>Nothing to checkout.</p>
            <a class="btn btn-primary" href="<?php echo e(BASE_URL); ?>/cart.php">Go to cart</a>
        </div>
    <?php elseif (!$restaurant): ?>
        <div class="alert alert-error">Invalid cart state.</div>
    <?php else: ?>
        <div class="cart-layout">
            <div class="card" style="padding:1.25rem">
                <h2 style="margin-top:0">Delivery</h2>
                <form method="post" data-loading>
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" rows="3" required placeholder="Flat, street, landmark"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="payment">Payment</label>
                        <select id="payment" name="payment">
                            <option value="card">Card</option>
                            <option value="upi">UPI</option>
                            <option value="cod">Cash on delivery</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Place order</button>
                </form>
            </div>
            <div class="card" style="padding:1.25rem;position:sticky;top:5rem">
                <h2 style="margin-top:0">Summary</h2>
                <p style="color:var(--text-muted);margin-top:0"><?php echo e($restaurant['name']); ?></p>
                <?php foreach ($lines as $ln): ?>
                    <div class="summary-row">
                        <span><?php echo e($ln['name']); ?> × <?php echo (int) $ln['quantity']; ?></span>
                        <span>₹<?php echo e(number_format((float) $ln['price'] * (int) $ln['quantity'], 2)); ?></span>
                    </div>
                <?php endforeach; ?>
                <div class="summary-row"><span>Subtotal</span><span>₹<?php echo e(number_format($subtotal, 2)); ?></span></div>
                <div class="summary-row"><span>Delivery</span><span>₹<?php echo e(number_format($delivery, 2)); ?></span></div>
                <div class="summary-row"><span>Tax</span><span>₹<?php echo e(number_format($tax, 2)); ?></span></div>
                <p style="margin:1rem 0"><span class="eta-pill">Estimated delivery: <?php echo $eta; ?> min</span></p>
                <div class="summary-row total"><span>Total</span><span>₹<?php echo e(number_format($total, 2)); ?></span></div>
                <?php if ($mode === 'group'): ?>
                    <?php
                    $mc = $mysqli->prepare('SELECT COUNT(*) AS c FROM group_members WHERE group_order_id = ?');
                    $mc->bind_param('i', $groupId);
                    $mc->execute();
                    $cnt = (int) ($mc->get_result()->fetch_assoc()['c'] ?? 1);
                    ?>
                    <p style="color:var(--text-muted);font-size:0.9rem;margin-top:1rem">Equal split estimate: ₹<?php echo e(number_format($total / max(1, $cnt), 2)); ?> / person</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
