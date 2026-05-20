<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (is_logged_in()) {
    redirect('/index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter email and password.';
    } else {
        $stmt = $mysqli->prepare('SELECT id, name, password_hash, role FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['is_admin'] = ($user['role'] === 'admin');
            flash('toast', 'Welcome back, ' . $user['name'] . '!');
            redirect('/index.php');
        }
        $error = 'Invalid email or password.';
    }
}

$pageTitle = 'Login';
require __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="form-card">
        <h1 style="margin-top:0;text-align:center">Login</h1>
        <?php if ($error): ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>
        <form method="post" data-loading>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required autocomplete="email" value="<?php echo e($_POST['email'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primary btn-block">Sign in</button>
        </form>
        <p style="text-align:center;margin-top:1rem;color:var(--text-muted)">Demo: <code>rahul@demo.com</code> / <code>password</code></p>
        <p style="text-align:center"><a href="<?php echo e(BASE_URL); ?>/register.php">Create account</a></p>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
