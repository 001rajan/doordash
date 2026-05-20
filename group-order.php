<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_login();

$uid = (int) current_user_id();

// Join via invite link
if (isset($_GET['join'])) {
    $token = trim((string) $_GET['join']);
    if ($token !== '') {
        $stmt = $mysqli->prepare('SELECT id, status FROM group_orders WHERE invite_token = ? LIMIT 1');
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $gr = $stmt->get_result()->fetch_assoc();
        if ($gr && $gr['status'] === 'open') {
            $gid = (int) $gr['id'];
            $ins = $mysqli->prepare('INSERT IGNORE INTO group_members (group_order_id, user_id) VALUES (?, ?)');
            $ins->bind_param('ii', $gid, $uid);
            $ins->execute();
            if ($ins->affected_rows > 0) {
                $nq = $mysqli->prepare('SELECT name FROM users WHERE id = ?');
                $nq->bind_param('i', $uid);
                $nq->execute();
                $nm = $nq->get_result()->fetch_assoc()['name'] ?? 'Someone';
                log_group_activity($mysqli, $gid, $uid, 'join', $nm . ' joined group', null);
            }
            flash('toast', 'You joined the group!');
            redirect('/group-order.php?gid=' . $gid);
        }
        flash('toast', 'Invalid or closed invite.');
        redirect('/group-order.php');
    }
}

// POST: create or join by code
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ptype = $_POST['form_type'] ?? '';
    if ($ptype === 'create') {
        $code = random_group_code();
        $token = random_token(24);
        $rid = (int) ($_POST['restaurant_id'] ?? 0);
        $restaurantId = $rid > 0 ? $rid : null;
        if ($restaurantId) {
            $ins = $mysqli->prepare('INSERT INTO group_orders (code, invite_token, creator_id, restaurant_id) VALUES (?, ?, ?, ?)');
            $ins->bind_param('ssii', $code, $token, $uid, $restaurantId);
        } else {
            $ins = $mysqli->prepare('INSERT INTO group_orders (code, invite_token, creator_id) VALUES (?, ?, ?)');
            $ins->bind_param('ssi', $code, $token, $uid);
        }
        try {
            $ins->execute();
        } catch (Throwable $e) {
            flash('toast', 'Could not create group. Retry.');
            redirect('/group-order.php');
        }
        $newId = (int) $mysqli->insert_id;
        $mem = $mysqli->prepare('INSERT INTO group_members (group_order_id, user_id) VALUES (?, ?)');
        $mem->bind_param('ii', $newId, $uid);
        $mem->execute();
        $nq = $mysqli->prepare('SELECT name FROM users WHERE id = ?');
        $nq->bind_param('i', $uid);
        $nq->execute();
        $nm = $nq->get_result()->fetch_assoc()['name'] ?? 'Host';
        log_group_activity($mysqli, $newId, $uid, 'create', $nm . ' started a group order', null);
        flash('toast', 'Group created! Share the code.');
        redirect('/group-order.php?gid=' . $newId);
    }
    if ($ptype === 'join_code') {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $stmt = $mysqli->prepare('SELECT id, status FROM group_orders WHERE code = ?');
        $stmt->bind_param('s', $code);
        $stmt->execute();
        $gr = $stmt->get_result()->fetch_assoc();
        if ($gr && $gr['status'] === 'open') {
            $gid = (int) $gr['id'];
            $ins = $mysqli->prepare('INSERT IGNORE INTO group_members (group_order_id, user_id) VALUES (?, ?)');
            $ins->bind_param('ii', $gid, $uid);
            $ins->execute();
            if ($ins->affected_rows > 0) {
                $nq = $mysqli->prepare('SELECT name FROM users WHERE id = ?');
                $nq->bind_param('i', $uid);
                $nq->execute();
                $nm = $nq->get_result()->fetch_assoc()['name'] ?? 'Someone';
                log_group_activity($mysqli, $gid, $uid, 'join', $nm . ' joined group', null);
            }
            flash('toast', 'Joined group ' . $code);
            redirect('/group-order.php?gid=' . $gid);
        }
        flash('toast', 'Invalid code or group closed.');
        redirect('/group-order.php');
    }
}

$gid = (int) ($_GET['gid'] ?? 0);
$currentGroup = null;
$menuPick = [];
if ($gid > 0) {
    $gq = $mysqli->prepare('SELECT g.*, u.name AS creator_name FROM group_orders g JOIN users u ON u.id = g.creator_id WHERE g.id = ?');
    $gq->bind_param('i', $gid);
    $gq->execute();
    $currentGroup = $gq->get_result()->fetch_assoc();
    if ($currentGroup) {
        $mchk = $mysqli->prepare('SELECT 1 FROM group_members WHERE group_order_id = ? AND user_id = ?');
        $mchk->bind_param('ii', $gid, $uid);
        $mchk->execute();
        if (!$mchk->get_result()->fetch_row()) {
            $currentGroup = null;
        }
    }
    if ($currentGroup && $currentGroup['restaurant_id']) {
        $rid = (int) $currentGroup['restaurant_id'];
        $mq = $mysqli->prepare('SELECT * FROM menu_items WHERE restaurant_id = ? AND is_available = 1 ORDER BY name');
        $mq->bind_param('i', $rid);
        $mq->execute();
        $menuPick = $mq->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

$restaurants = $mysqli->query('SELECT id, name FROM restaurants WHERE is_active = 1 ORDER BY name')->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Group order';
require __DIR__ . '/includes/header.php';

$inviteUrl = '';
if ($currentGroup) {
    $inviteUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
        . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
        . BASE_URL . '/group-order.php?join=' . urlencode($currentGroup['invite_token']);
}
?>

<section class="section container" style="padding-top:1.5rem">
    <h1 style="margin-top:0">Group ordering</h1>
    <p style="color:var(--text-muted);max-width:640px">Create a group, share the code or link, build a shared cart together, then checkout with split summary.</p>

    <?php if (!$currentGroup): ?>
        <div class="group-layout" style="margin-top:1.5rem">
            <div class="card" style="padding:1.25rem">
                <h2 style="margin-top:0">Start a group</h2>
                <form method="post">
                    <input type="hidden" name="form_type" value="create">
                    <div class="form-group">
                        <label for="restaurant_id">Lock menu to restaurant (optional)</label>
                        <select name="restaurant_id" id="restaurant_id">
                            <option value="0">Any — add from cart after joining</option>
                            <?php foreach ($restaurants as $r): ?>
                                <option value="<?php echo (int) $r['id']; ?>"><?php echo e($r['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Create group</button>
                </form>
            </div>
            <div class="card" style="padding:1.25rem">
                <h2 style="margin-top:0">Join with code</h2>
                <form method="post">
                    <input type="hidden" name="form_type" value="join_code">
                    <div class="form-group">
                        <label for="code">Group code</label>
                        <input type="text" id="code" name="code" maxlength="10" placeholder="e.g. ABC12X" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Join</button>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="group-layout" style="margin-top:1.5rem">
            <div>
                <div class="card" style="padding:1.25rem;margin-bottom:1rem">
                    <h2 style="margin-top:0">Group <?php echo e($currentGroup['code']); ?></h2>
                    <div class="code-box"><?php echo e($currentGroup['code']); ?></div>
                    <p style="color:var(--text-muted);font-size:0.9rem">Host: <?php echo e($currentGroup['creator_name']); ?></p>
                    <button type="button" class="btn btn-ghost btn-sm" id="btnCopyInvite" data-url="<?php echo e($inviteUrl); ?>">Copy invite link</button>
                    <a class="btn btn-primary btn-sm" style="margin-left:0.5rem" href="<?php echo e(BASE_URL); ?>/checkout.php?group=<?php echo (int) $currentGroup['id']; ?>">Group checkout</a>
                </div>

                <div class="card" style="padding:1.25rem;margin-bottom:1rem">
                    <h3 style="margin-top:0">Add to shared cart</h3>
                    <?php if ($menuPick): ?>
                        <div class="menu-grid">
                            <?php foreach ($menuPick as $it): ?>
                                <div class="menu-item" style="grid-template-columns:72px 1fr auto">
                                    <div class="menu-thumb"><img src="<?php echo e($it['image_url']); ?>" alt=""></div>
                                    <div class="menu-info">
                                        <h4 style="margin:0;font-size:0.95rem"><?php echo e($it['name']); ?></h4>
                                        <div class="menu-price">₹<?php echo e(number_format((float) $it['price'], 0)); ?></div>
                                    </div>
                                    <button type="button" class="btn btn-primary btn-sm btn-add-group" data-mid="<?php echo (int) $it['id']; ?>">Add</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="color:var(--text-muted)">No restaurant locked — use items from your personal cart flow or create a new group with a restaurant selected.</p>
                    <?php endif; ?>
                </div>

                <div class="card" style="padding:1.25rem">
                    <h3 style="margin-top:0">Group activity</h3>
                    <div class="activity-feed" id="activityFeed"></div>
                </div>
            </div>

            <div>
                <div class="card" style="padding:1.25rem;margin-bottom:1rem">
                    <h3 style="margin-top:0">Members</h3>
                    <ul id="memberList" style="margin:0;padding-left:1.1rem;color:var(--text-muted)"></ul>
                </div>
                <div class="card" style="padding:1.25rem;margin-bottom:1rem">
                    <h3 style="margin-top:0">Shared cart</h3>
                    <div id="groupCart"></div>
                    <div id="splitSummary" style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--border);color:var(--text-muted);font-size:0.9rem"></div>
                </div>
            </div>
        </div>

        <script>
        (function(){
            var gid = <?php echo (int) $currentGroup['id']; ?>;
            var base = window.APP_BASE || '';
            function render(data){
                var feed = document.getElementById('activityFeed');
                var members = document.getElementById('memberList');
                var cart = document.getElementById('groupCart');
                var split = document.getElementById('splitSummary');
                if(!feed||!members||!cart) return;
                feed.innerHTML = (data.activities||[]).map(function(a){
                    return '<div class="activity-item"><strong>'+(a.user_name||'')+'</strong> — '+escapeHtml(a.message)+' <span style="opacity:.6;font-size:.8rem">'+escapeHtml(a.created_at)+'</span></div>';
                }).join('') || '<p style="color:var(--text-muted)">No activity yet.</p>';
                members.innerHTML = (data.members||[]).map(function(m){
                    return '<li>'+escapeHtml(m.name)+'</li>';
                }).join('');
                var lines = data.cart||[];
                var sub = 0;
                cart.innerHTML = lines.map(function(l){
                    sub += parseFloat(l.price)*parseInt(l.quantity,10);
                    var mine = parseInt(l.user_id,10) === <?php echo $uid; ?>;
                    var removeBtn = mine ? '<button type="button" class="btn btn-ghost btn-sm" data-rid="'+l.id+'">Remove</button>' : '';
                    return '<div class="summary-row" style="align-items:center;flex-wrap:wrap;gap:.5rem"><span>'+escapeHtml(l.item_name)+' ×'+l.quantity+' <small style="opacity:.7">('+escapeHtml(l.added_by)+')</small></span><span>₹'+(parseFloat(l.price)*parseInt(l.quantity,10)).toFixed(2)+'</span>'+removeBtn+'</div>';
                }).join('') || '<p style="color:var(--text-muted)">Cart is empty.</p>';
                var n = (data.members||[]).length || 1;
                split.innerHTML = '<strong>Split (equal)</strong><br>Subtotal: ₹'+sub.toFixed(2)+'<br>Per person (~): ₹'+(sub/n).toFixed(2)+' <span style="opacity:.7">(+ delivery & tax at checkout)</span>';
            }
            function escapeHtml(s){
                var d=document.createElement('div'); d.textContent=s; return d.innerHTML;
            }
            function poll(){
                fetch(base+'/api/group_poll.php?gid='+gid, {headers:{'X-Requested-With':'XMLHttpRequest'}})
                    .then(function(r){ return r.json(); })
                    .then(function(d){ if(d.ok) render(d); })
                    .catch(function(){});
            }
            poll();
            setInterval(poll, 4000);

            document.querySelectorAll('.btn-add-group').forEach(function(btn){
                btn.addEventListener('click', function(){
                    var mid = parseInt(btn.getAttribute('data-mid'),10);
                    fetch(base+'/api/group_action.php', {
                        method:'POST',
                        headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
                        body: JSON.stringify({action:'add_item', group_id: gid, menu_item_id: mid, qty: 1})
                    }).then(function(r){return r.json();}).then(function(d){
                        if(d.ok){ if(window.showToast) showToast('Added','success'); poll(); }
                        else if(window.showToast) showToast(d.error||'Error','error');
                    }).catch(function(){ if(window.showToast) showToast('Network error','error'); });
                });
            });
            document.getElementById('groupCart').addEventListener('click', function(e){
                var t = e.target;
                if(t && t.getAttribute('data-rid')){
                    var id = parseInt(t.getAttribute('data-rid'),10);
                    fetch(base+'/api/group_action.php', {
                        method:'POST',
                        headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
                        body: JSON.stringify({action:'remove_item', group_id: gid, cart_row_id: id})
                    }).then(function(r){return r.json();}).then(function(d){
                        if(d.ok){ poll(); }
                        else if(window.showToast) showToast(d.error||'Error','error');
                    });
                }
            });
            var copyBtn = document.getElementById('btnCopyInvite');
            if(copyBtn){
                copyBtn.addEventListener('click', function(){
                    var u = copyBtn.getAttribute('data-url');
                    navigator.clipboard.writeText(u).then(function(){
                        if(window.showToast) showToast('Invite link copied','success');
                    });
                });
            }
        })();
        </script>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
