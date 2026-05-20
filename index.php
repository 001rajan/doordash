<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

$pageTitle = 'Order hyperlocal, together';

$q = trim($_GET['q'] ?? '');
$sql = 'SELECT * FROM restaurants WHERE is_active = 1';
$params = [];
$types = '';
if ($q !== '') {
    $sql .= ' AND (name LIKE ? OR category LIKE ? OR description LIKE ?)';
    $like = '%' . $q . '%';
    $params = [$like, $like, $like];
    $types = 'sss';
}
$sql .= ' ORDER BY rating DESC LIMIT 12';

$stmt = $mysqli->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$restaurants = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$trending = $mysqli->query(
    'SELECT m.*, r.name AS restaurant_name, r.slug FROM menu_items m
     JOIN restaurants r ON r.id = m.restaurant_id
     WHERE m.is_available = 1 AND r.is_active = 1
     ORDER BY RAND() LIMIT 6'
)->fetch_all(MYSQLI_ASSOC);

$categories = $mysqli->query(
    'SELECT DISTINCT category FROM restaurants WHERE is_active = 1 ORDER BY category'
)->fetch_all(MYSQLI_ASSOC);

require __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <div class="container hero-inner">
        <h1>Food that finds you. <span style="color:var(--accent)">Fast.</span></h1>
        <p>Group orders for friends, smart ETAs, and restaurants within minutes — campus & neighbourhood first.</p>
        <form class="search-bar" method="get" action="<?php echo e(BASE_URL); ?>/restaurants.php" role="search">
            <input type="search" name="q" placeholder="Search restaurants, cuisine, dishes…" value="<?php echo e($q); ?>" aria-label="Search">
            <button type="submit" class="btn btn-primary">Search</button>
        </form>
    </div>
</section>

<section class="section container">
    <div class="section-head">
        <h2>Browse by mood</h2>
    </div>
    <div class="cat-scroll" id="categoryPills">
        <?php foreach ($categories as $c): ?>
            <a class="cat-pill" href="<?php echo e(BASE_URL); ?>/restaurants.php?category=<?php echo urlencode($c['category']); ?>"><?php echo e($c['category']); ?></a>
        <?php endforeach; ?>
    </div>
</section>

<section class="section container">
    <div class="section-head">
        <h2>Nearby picks</h2>
        <a class="link-more" href="<?php echo e(BASE_URL); ?>/restaurants.php">View all →</a>
    </div>
    <div class="grid-cards">
        <?php foreach ($restaurants as $r): ?>
            <article class="card">
                <a href="<?php echo e(BASE_URL); ?>/menu.php?r=<?php echo urlencode($r['slug']); ?>" class="card-img-wrap">
                    <img src="<?php echo e($r['image_url']); ?>" alt="" loading="lazy" width="400" height="250">
                </a>
                <div class="card-body">
                    <h3 class="card-title"><a href="<?php echo e(BASE_URL); ?>/menu.php?r=<?php echo urlencode($r['slug']); ?>"><?php echo e($r['name']); ?></a></h3>
                    <div class="card-meta">
                        <span class="rating">★ <?php echo e((string) $r['rating']); ?></span>
                        <span><?php echo (int) $r['delivery_time_mins']; ?> min</span>
                        <span><?php echo e((string) $r['distance_km']); ?> km</span>
                    </div>
                    <span class="badge" style="margin-top:0.5rem;display:inline-block"><?php echo e($r['category']); ?></span>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="section container">
    <div class="section-head">
        <h2>Trending plates</h2>
    </div>
    <div class="grid-cards">
        <?php foreach ($trending as $m): ?>
            <article class="card">
                <a href="<?php echo e(BASE_URL); ?>/menu.php?r=<?php echo urlencode($m['slug']); ?>" class="card-img-wrap">
                    <img src="<?php echo e($m['image_url']); ?>" alt="" loading="lazy" width="400" height="250">
                </a>
                <div class="card-body">
                    <h3 class="card-title"><?php echo e($m['name']); ?></h3>
                    <p class="card-meta"><?php echo e($m['restaurant_name']); ?> · ₹<?php echo e(number_format((float) $m['price'], 0)); ?></p>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
