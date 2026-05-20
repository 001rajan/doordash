<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

$slug = trim($_GET['r'] ?? '');
if ($slug === '') {
    redirect('/restaurants.php');
}

$stmt = $mysqli->prepare('SELECT * FROM restaurants WHERE slug = ? AND is_active = 1 LIMIT 1');
$stmt->bind_param('s', $slug);
$stmt->execute();
$restaurant = $stmt->get_result()->fetch_assoc();
if (!$restaurant) {
    redirect('/restaurants.php');
}

$stmt2 = $mysqli->prepare('SELECT * FROM menu_items WHERE restaurant_id = ? AND is_available = 1 ORDER BY name');
$stmt2->bind_param('i', $restaurant['id']);
$stmt2->execute();
$items = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = $restaurant['name'];
$msg = flash('menu_msg');

require __DIR__ . '/includes/header.php';
?>

<section class="section container" style="padding-top:1.5rem">
    <div class="card" style="margin-bottom:1.5rem;display:grid;grid-template-columns:minmax(0,200px) 1fr;gap:1.25rem;align-items:center;padding:1rem">
        <div class="card-img-wrap" style="aspect-ratio:1;border-radius:12px">
            <img src="<?php echo e($restaurant['image_url']); ?>" alt="">
        </div>
        <div>
            <h1 style="margin:0 0 0.35rem"><?php echo e($restaurant['name']); ?></h1>
            <p style="color:var(--text-muted);margin:0"><?php echo e($restaurant['description'] ?? ''); ?></p>
            <p class="card-meta" style="margin-top:0.75rem">
                <span class="rating">★ <?php echo e((string) $restaurant['rating']); ?></span>
                <span><?php echo (int) $restaurant['delivery_time_mins']; ?> min delivery</span>
                <span><?php echo e((string) $restaurant['distance_km']); ?> km</span>
            </p>
            <?php
            $eta = calculate_smart_eta(
                (int) $restaurant['prep_time_mins'],
                (float) $restaurant['distance_km'],
                (int) $restaurant['traffic_delay_mins']
            );
            ?>
            <p style="margin-top:0.75rem"><span class="eta-pill">⚡ Smart ETA: ~<?php echo $eta; ?> min</span></p>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?php echo e($msg); ?></div><?php endif; ?>

    <h2 style="font-size:1.2rem;margin-bottom:1rem">Menu</h2>
    <div class="menu-grid">
        <?php foreach ($items as $it): ?>
            <div class="menu-item">
                <div class="menu-thumb"><img src="<?php echo e($it['image_url']); ?>" alt=""></div>
                <div class="menu-info">
                    <h3><?php echo e($it['name']); ?></h3>
                    <p><?php echo e($it['description'] ?? ''); ?></p>
                    <div class="menu-price" style="margin-top:0.35rem">₹<?php echo e(number_format((float) $it['price'], 2)); ?></div>
                </div>
                <div class="menu-actions">
                    <?php if (is_logged_in()): ?>
                        <form method="post" action="<?php echo e(BASE_URL); ?>/cart-actions.php" style="display:flex;flex-direction:column;gap:0.5rem;align-items:flex-end">
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="menu_item_id" value="<?php echo (int) $it['id']; ?>">
                            <input type="hidden" name="redirect" value="<?php echo e(BASE_URL . '/menu.php?r=' . urlencode($slug)); ?>">
                            <div class="qty-row">
                                <label class="visually-hidden" for="q<?php echo (int) $it['id']; ?>">Qty</label>
                                <input type="number" id="q<?php echo (int) $it['id']; ?>" name="qty" value="1" min="1" max="99" style="width:52px;padding:0.35rem;border-radius:8px;border:1px solid var(--border);background:var(--bg-deep);color:var(--text)">
                                <button type="submit" class="btn btn-primary btn-sm">Add</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <a class="btn btn-ghost btn-sm" href="<?php echo e(BASE_URL); ?>/login.php">Login to order</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<style>.visually-hidden{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);border:0}</style>

<?php require __DIR__ . '/includes/footer.php'; ?>
