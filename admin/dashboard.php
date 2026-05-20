<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/db.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'order_status') {
    $oid = (int) ($_POST['order_id'] ?? 0);
    $st = $_POST['status'] ?? '';
    $allowed = ['preparing', 'packed', 'out_for_delivery', 'delivered'];
    if ($oid > 0 && in_array($st, $allowed, true)) {
        $u = $mysqli->prepare('UPDATE orders SET status = ? WHERE id = ?');
        $u->bind_param('si', $st, $oid);
        $u->execute();
        flash('toast', 'Order status updated.');
        header('Location: ' . BASE_URL . '/admin/dashboard.php');
        exit;
    }
}

$stats = [
    'users' => (int) $mysqli->query('SELECT COUNT(*) c FROM users')->fetch_assoc()['c'],
    'orders' => (int) $mysqli->query('SELECT COUNT(*) c FROM orders')->fetch_assoc()['c'],
    'revenue' => (float) ($mysqli->query('SELECT COALESCE(SUM(total),0) t FROM orders')->fetch_assoc()['t'] ?? 0),
];

$popular = $mysqli->query(
    'SELECT r.name, COUNT(o.id) cnt FROM orders o JOIN restaurants r ON r.id = o.restaurant_id GROUP BY r.id ORDER BY cnt DESC LIMIT 5'
)->fetch_all(MYSQLI_ASSOC);

$orders = $mysqli->query(
    'SELECT o.id, o.total, o.status, o.created_at, u.name AS user_name, r.name AS restaurant_name
     FROM orders o JOIN users u ON u.id = o.user_id JOIN restaurants r ON r.id = o.restaurant_id
     ORDER BY o.id DESC LIMIT 25'
)->fetch_all(MYSQLI_ASSOC);

$users = $mysqli->query('SELECT id, name, email, role, created_at FROM users ORDER BY id DESC LIMIT 20')->fetch_all(MYSQLI_ASSOC);
$restaurants = $mysqli->query('SELECT * FROM restaurants ORDER BY id')->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Admin dashboard';
require dirname(__DIR__) . '/includes/header.php';
?>

<section class="section container" style="padding-top:1.5rem">
    <h1 style="margin-top:0">Admin dashboard</h1>

    <div class="stat-grid">
        <div class="stat-card"><h3><?php echo $stats['users']; ?></h3><p>Total users</p></div>
        <div class="stat-card"><h3><?php echo $stats['orders']; ?></h3><p>Total orders</p></div>
        <div class="stat-card"><h3>₹<?php echo e(number_format($stats['revenue'], 0)); ?></h3><p>Revenue (sum)</p></div>
    </div>

    <div class="card" style="padding:1.25rem;margin-bottom:1.5rem">
        <h2 style="margin-top:0">Popular restaurants</h2>
        <ul style="margin:0;color:var(--text-muted)">
            <?php foreach ($popular as $p): ?>
                <li><?php echo e($p['name']); ?> — <?php echo (int) $p['cnt']; ?> orders</li>
            <?php endforeach; ?>
            <?php if (!$popular): ?><li>No orders yet.</li><?php endif; ?>
        </ul>
    </div>

    <h2>Recent orders</h2>
    <div class="table-wrap" style="margin-bottom:2rem">
        <table class="data-table">
            <thead><tr><th>ID</th><th>User</th><th>Restaurant</th><th>Total</th><th>Status</th><th>Update</th></tr></thead>
            <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td><?php echo (int) $o['id']; ?></td>
                        <td><?php echo e($o['user_name']); ?></td>
                        <td><?php echo e($o['restaurant_name']); ?></td>
                        <td>₹<?php echo e(number_format((float) $o['total'], 2)); ?></td>
                        <td><?php echo e($o['status']); ?></td>
                        <td>
                            <form method="post" style="display:flex;gap:0.35rem;flex-wrap:wrap">
                                <input type="hidden" name="form" value="order_status">
                                <input type="hidden" name="order_id" value="<?php echo (int) $o['id']; ?>">
                                <select name="status">
                                    <?php foreach (['preparing','packed','out_for_delivery','delivered'] as $s): ?>
                                        <option value="<?php echo e($s); ?>" <?php echo $o['status'] === $s ? 'selected' : ''; ?>><?php echo e($s); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-primary btn-sm">Save</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h2>Users</h2>
    <div class="table-wrap" style="margin-bottom:2rem">
        <table class="data-table">
            <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Joined</th></tr></thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?php echo (int) $u['id']; ?></td>
                        <td><?php echo e($u['name']); ?></td>
                        <td><?php echo e($u['email']); ?></td>
                        <td><?php echo e($u['role']); ?></td>
                        <td><?php echo e($u['created_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h2>Restaurants</h2>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>ID</th><th>Name</th><th>Category</th><th>Rating</th><th>Active</th></tr></thead>
            <tbody>
                <?php foreach ($restaurants as $r): ?>
                    <tr>
                        <td><?php echo (int) $r['id']; ?></td>
                        <td><?php echo e($r['name']); ?></td>
                        <td><?php echo e($r['category']); ?></td>
                        <td><?php echo e((string) $r['rating']); ?></td>
                        <td><?php echo (int) $r['is_active'] ? 'Yes' : 'No'; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
