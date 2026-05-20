<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/db.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$gid = (int) ($_GET['gid'] ?? 0);
if ($gid <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Invalid group'], JSON_THROW_ON_ERROR);
    exit;
}

$uid = (int) current_user_id();

$chk = $mysqli->prepare('SELECT 1 FROM group_members WHERE group_order_id = ? AND user_id = ?');
$chk->bind_param('ii', $gid, $uid);
$chk->execute();
if (!$chk->get_result()->fetch_row()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Not a member'], JSON_THROW_ON_ERROR);
    exit;
}

$g = $mysqli->prepare('SELECT g.*, u.name AS creator_name FROM group_orders g JOIN users u ON u.id = g.creator_id WHERE g.id = ?');
$g->bind_param('i', $gid);
$g->execute();
$group = $g->get_result()->fetch_assoc();
if (!$group) {
    echo json_encode(['ok' => false, 'error' => 'Not found'], JSON_THROW_ON_ERROR);
    exit;
}

$members = [];
$mq = $mysqli->prepare(
    'SELECT u.id, u.name, gm.joined_at FROM group_members gm JOIN users u ON u.id = gm.user_id WHERE gm.group_order_id = ? ORDER BY gm.joined_at'
);
$mq->bind_param('i', $gid);
$mq->execute();
$members = $mq->get_result()->fetch_all(MYSQLI_ASSOC);

$acts = $mysqli->prepare(
    'SELECT ga.message, ga.created_at, u.name AS user_name FROM group_activity ga
     LEFT JOIN users u ON u.id = ga.user_id
     WHERE ga.group_order_id = ?
     ORDER BY ga.id DESC LIMIT 40'
);
$acts->bind_param('i', $gid);
$acts->execute();
$activities = $acts->get_result()->fetch_all(MYSQLI_ASSOC);

$cartSql = 'SELECT gc.id, gc.quantity, gc.user_id, u.name AS added_by, m.name AS item_name, m.price, m.id AS menu_item_id
            FROM group_cart gc
            JOIN users u ON u.id = gc.user_id
            JOIN menu_items m ON m.id = gc.menu_item_id
            WHERE gc.group_order_id = ?
            ORDER BY gc.id';
$cq = $mysqli->prepare($cartSql);
$cq->bind_param('i', $gid);
$cq->execute();
$cart = $cq->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    'ok' => true,
    'group' => $group,
    'members' => $members,
    'activities' => $activities,
    'cart' => $cart,
], JSON_THROW_ON_ERROR);
