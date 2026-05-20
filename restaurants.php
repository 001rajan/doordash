<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

$pageTitle = 'Restaurants';
$q = trim($_GET['q'] ?? '');
$cat = trim($_GET['category'] ?? '');

$sql = 'SELECT * FROM restaurants WHERE is_active = 1';
$types = '';
$params = [];

if ($q !== '') {
    $sql .= ' AND (name LIKE ? OR category LIKE ? OR description LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
    $types .= 'sss';
}
if ($cat !== '') {
    $sql .= ' AND category = ?';
    $params[] = $cat;
    $types .= 's';
}
$sql .= ' ORDER BY distance_km ASC, rating DESC';

$stmt = $mysqli->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

require __DIR__ . '/includes/header.php';
?>

<section class="section container" style="padding-top:2rem">
    <div class="section-head">
        <h1>Restaurants</h1>
    </div>
    <form class="search-bar" method="get" action="" style="max-width:560px;margin-bottom:1.5rem">
        <input type="search" name="q" placeholder="Search…" value="<?php echo e($q); ?>">
        <?php if ($cat): ?><input type="hidden" name="category" value="<?php echo e($cat); ?>"><?php endif; ?>
        <button type="submit" class="btn btn-primary">Go</button>
    </form>

    <?php if (!$list): ?>
        <div class="empty-state">No restaurants match your filters.</div>
    <?php else: ?>
        <div class="grid-cards">
            <?php foreach ($list as $r): ?>
                <article class="card">
                    <a href="<?php echo e(BASE_URL); ?>/menu.php?r=<?php echo urlencode($r['slug']); ?>" class="card-img-wrap">
                        <img src="<?php echo e($r['image_url']); ?>" alt="" loading="lazy">
                    </a>
                    <div class="card-body">
                        <h2 class="card-title"><a href="<?php echo e(BASE_URL); ?>/menu.php?r=<?php echo urlencode($r['slug']); ?>"><?php echo e($r['name']); ?></a></h2>
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
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
