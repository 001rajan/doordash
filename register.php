<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (is_logged_in()) {
    redirect('/index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '' || $email === '' || strlen($password) < 6) {
        $error = 'Name, email, and password (min 6 chars) are required.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $mysqli->prepare('INSERT INTO users (name, email, phone, password_hash) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('ssss', $name, $email, $phone, $hash);
        try {
            $stmt->execute();
            flash('toast', 'Account created. Please sign in.');
            redirect('/login.php');
        } catch (Throwable $e) {
            if ($mysqli->errno === 1062) {
                $error = 'That email is already registered.';
            } else {
                $error = 'Could not register. Try again.';
            }
        }
    }
}

$pageTitle = 'Register';
require __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="form-card">
        <h1 style="margin-top:0;text-align:center">Create account</h1>
        <?php if ($error): ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>
        <form method="post" data-loading>
            <div class="form-group">
                <label for="name">Full name</label>
                <input type="text" id="name" name="name" required value="<?php echo e($_POST['name'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required value="<?php echo e($_POST['email'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="phone">Phone</label>
                <input type="text" id="phone" name="phone" value="<?php echo e($_POST['phone'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required minlength="6">
            </div>
            <button type="submit" class="btn btn-primary btn-block">Sign up</button>
        </form>
        <p style="text-align:center;margin-top:1rem"><a href="<?php echo e(BASE_URL); ?>/login.php">Already have an account?</a></p>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
