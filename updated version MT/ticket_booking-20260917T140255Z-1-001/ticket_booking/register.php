<?php
require_once __DIR__ . '/auth.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$name = '';
$email = '';
$role = 'customer';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $role = (string) ($_POST['role'] ?? 'customer');
    $password = (string) ($_POST['password'] ?? '');
    $confirm_password = (string) ($_POST['confirm_password'] ?? '');

    if ($name === '') {
        $errors[] = 'Please enter your name.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (!in_array($role, ['customer', 'admin'], true)) {
        $errors[] = 'Please choose a valid account type.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Your password must be at least 8 characters.';
    }
    if ($password !== $confirm_password) {
        $errors[] = 'Your passwords do not match.';
    }

    if (!$errors) {
        $existing = db()->prepare('SELECT id FROM users WHERE email = ?');
        $existing->execute([$email]);

        if ($existing->fetch()) {
            $errors[] = 'An account with that email already exists.';
        } else {
            $statement = db()->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)');
            $statement->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) db()->lastInsertId();
            $_SESSION['user_role'] = $role;
            header('Location: dashboard.php');
            exit;
        }
    }
}

$page_title = 'Create account';
require __DIR__ . '/header.php';
?>
<section class="auth-layout">
    <div class="auth-intro"><p class="eyebrow">Start here</p><h1>Make room for the good trips.</h1><p>Set up your Yazaki Ticket Bookin' account in less than a minute.</p></div>
    <form class="form-card" method="post" action="register.php">
        <h2>Create account</h2>
        <p class="form-note">Already registered? <a href="login.php">Log in</a></p>
        <?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <label for="name">Full name</label>
        <input id="name" name="name" type="text" value="<?= e($name) ?>" autocomplete="name" required>
        <label for="email">Email address</label>
        <input id="email" name="email" type="email" value="<?= e($email) ?>" autocomplete="email" required>
        <label for="role">Account type</label>
        <select id="role" name="role" required>
            <option value="customer" <?= $role === 'customer' ? 'selected' : '' ?>>Customer</option>
            <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
        </select>
        <label for="password">Password <span class="label-hint">8+ characters</span></label>
        <input id="password" name="password" type="password" autocomplete="new-password" required>
        <label for="confirm_password">Confirm password</label>
        <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" required>
        <button class="button button-primary button-full" type="submit">Create my account</button>
    </form>
</section>
<?php require __DIR__ . '/footer.php'; ?>