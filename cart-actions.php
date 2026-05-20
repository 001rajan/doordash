<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_login();

$uid = (int) current_user_id();
$action = $_POST['action'] ?? '';
$redirect = $_POST['redirect'] ?? (BASE_URL . '/cart.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/cart.php');
}

function ensure_single_restaurant(mysqli $mysqli, int $userId, int $newMenuItemId): bool
{
    $stmt = $mysqli->prepare(
        'SELECT DISTINCT m.restaurant_id FROM cart c
         JOIN menu_items m ON m.id = c.menu_item_id
         WHERE c.user_id = ?'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    if (count($rows) === 0) {
        return true;
    }
    $stmt2 = $mysqli->prepare('SELECT restaurant_id FROM menu_items WHERE id = ?');
    $stmt2->bind_param('i', $newMenuItemId);
    $stmt2->execute();
    $nr = $stmt2->get_result()->fetch_assoc();
    if (!$nr) {
        return false;
    }
    $existingRid = (int) $rows[0]['restaurant_id'];
    return $existingRid === (int) $nr['restaurant_id'];
}

if ($action === 'add') {
    $mid = (int) ($_POST['menu_item_id'] ?? 0);
    $qty = max(1, (int) ($_POST['qty'] ?? 1));
    if ($mid <= 0) {
        flash('menu_msg', 'Invalid item.');
        header('Location: ' . $redirect);
        exit;
    }
    if (!ensure_single_restaurant($mysqli, $uid, $mid)) {
        flash('menu_msg', 'Your cart has items from another restaurant. Clear cart first or checkout.');
        header('Location: ' . $redirect);
        exit;
    }
    $stmt = $mysqli->prepare('SELECT id, quantity FROM cart WHERE user_id = ? AND menu_item_id = ?');
    $stmt->bind_param('ii', $uid, $mid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) {
        $newQ = (int) $row['quantity'] + $qty;
        $cid = (int) $row['id'];
        $u = $mysqli->prepare('UPDATE cart SET quantity = ? WHERE id = ?');
        $u->bind_param('ii', $newQ, $cid);
        $u->execute();
    } else {
        $ins = $mysqli->prepare('INSERT INTO cart (user_id, menu_item_id, quantity) VALUES (?, ?, ?)');
        $ins->bind_param('iii', $uid, $mid, $qty);
        $ins->execute();
    }
    flash('menu_msg', 'Added to cart.');
}

if ($action === 'update') {
    $cid = (int) ($_POST['cart_id'] ?? 0);
    $qty = (int) ($_POST['qty'] ?? 0);
    $chk = $mysqli->prepare('SELECT id FROM cart WHERE id = ? AND user_id = ?');
    $chk->bind_param('ii', $cid, $uid);
    $chk->execute();
    if ($chk->get_result()->fetch_assoc()) {
        if ($qty <= 0) {
            $d = $mysqli->prepare('DELETE FROM cart WHERE id = ? AND user_id = ?');
            $d->bind_param('ii', $cid, $uid);
            $d->execute();
        } else {
            $u = $mysqli->prepare('UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?');
            $u->bind_param('iii', $qty, $cid, $uid);
            $u->execute();
        }
    }
}

if ($action === 'clear') {
    $d = $mysqli->prepare('DELETE FROM cart WHERE user_id = ?');
    $d->bind_param('i', $uid);
    $d->execute();
    flash('toast', 'Cart cleared.');
}

header('Location: ' . $redirect);
exit;
