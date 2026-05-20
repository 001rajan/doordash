<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/db.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed'], JSON_THROW_ON_ERROR);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '{}', true, 512, JSON_THROW_ON_ERROR);
$action = $data['action'] ?? '';
$gid = (int) ($data['group_id'] ?? 0);
$uid = (int) current_user_id();

if ($gid <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Invalid group'], JSON_THROW_ON_ERROR);
    exit;
}

$chk = $mysqli->prepare('SELECT status FROM group_orders WHERE id = ?');
$chk->bind_param('i', $gid);
$chk->execute();
$g = $chk->get_result()->fetch_assoc();
if (!$g || $g['status'] !== 'open') {
    echo json_encode(['ok' => false, 'error' => 'Group not open'], JSON_THROW_ON_ERROR);
    exit;
}

$mchk = $mysqli->prepare('SELECT 1 FROM group_members WHERE group_order_id = ? AND user_id = ?');
$mchk->bind_param('ii', $gid, $uid);
$mchk->execute();
if (!$mchk->get_result()->fetch_row()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Not a member'], JSON_THROW_ON_ERROR);
    exit;
}

$unameStmt = $mysqli->prepare('SELECT name FROM users WHERE id = ?');
$unameStmt->bind_param('i', $uid);
$unameStmt->execute();
$me = $unameStmt->get_result()->fetch_assoc();
$myName = $me['name'] ?? 'User';

if ($action === 'add_item') {
    $mid = (int) ($data['menu_item_id'] ?? 0);
    $qty = max(1, (int) ($data['qty'] ?? 1));
    if ($mid <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Bad item'], JSON_THROW_ON_ERROR);
        exit;
    }
    $it = $mysqli->prepare('SELECT name FROM menu_items WHERE id = ? AND is_available = 1');
    $it->bind_param('i', $mid);
    $it->execute();
    $item = $it->get_result()->fetch_assoc();
    if (!$item) {
        echo json_encode(['ok' => false, 'error' => 'Item unavailable'], JSON_THROW_ON_ERROR);
        exit;
    }

    $ex = $mysqli->prepare('SELECT id, quantity FROM group_cart WHERE group_order_id = ? AND menu_item_id = ? AND user_id = ?');
    $ex->bind_param('iii', $gid, $mid, $uid);
    $ex->execute();
    $row = $ex->get_result()->fetch_assoc();
    if ($row) {
        $nq = (int) $row['quantity'] + $qty;
        $cid = (int) $row['id'];
        $up = $mysqli->prepare('UPDATE group_cart SET quantity = ? WHERE id = ?');
        $up->bind_param('ii', $nq, $cid);
        $up->execute();
    } else {
        $ins = $mysqli->prepare('INSERT INTO group_cart (group_order_id, user_id, menu_item_id, quantity) VALUES (?, ?, ?, ?)');
        $ins->bind_param('iiii', $gid, $uid, $mid, $qty);
        $ins->execute();
    }
    $msg = $myName . ' added ' . $item['name'];
    log_group_activity($mysqli, $gid, $uid, 'add', $msg, (string) $mid);
    echo json_encode(['ok' => true], JSON_THROW_ON_ERROR);
    exit;
}

if ($action === 'remove_item') {
    $cartRowId = (int) ($data['cart_row_id'] ?? 0);
    $sel = $mysqli->prepare(
        'SELECT gc.id, m.name FROM group_cart gc JOIN menu_items m ON m.id = gc.menu_item_id
         WHERE gc.id = ? AND gc.group_order_id = ? AND gc.user_id = ?'
    );
    $sel->bind_param('iii', $cartRowId, $gid, $uid);
    $sel->execute();
    $r = $sel->get_result()->fetch_assoc();
    if (!$r) {
        echo json_encode(['ok' => false, 'error' => 'Row not found'], JSON_THROW_ON_ERROR);
        exit;
    }
    $del = $mysqli->prepare('DELETE FROM group_cart WHERE id = ?');
    $del->bind_param('i', $cartRowId);
    $del->execute();
    $msg = $myName . ' removed ' . $r['name'];
    log_group_activity($mysqli, $gid, $uid, 'remove', $msg, null);
    echo json_encode(['ok' => true], JSON_THROW_ON_ERROR);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Unknown action'], JSON_THROW_ON_ERROR);
