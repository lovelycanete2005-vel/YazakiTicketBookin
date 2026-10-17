<?php
require_once __DIR__ . '/auth.php';
require_admin();

$movie_search = trim((string) ($_GET['movie_search'] ?? ''));
$movie_month = (string) ($_GET['movie_month'] ?? '');
if (!preg_match('/^(?!0000)\d{4}-(0[1-9]|1[0-2])$/', $movie_month)) {
    $movie_month = '';
}
$editing_movie_id = filter_input(INPUT_GET, 'edit_movie', FILTER_VALIDATE_INT) ?: 0;
$screening_times = [
    '08:00:00' => '8:00 AM - 11:00 AM',
    '12:00:00' => '12:00 PM - 3:00 PM',
    '16:00:00' => '4:00 PM - 7:00 PM',
    '20:00:00' => '8:00 PM - 12:00 AM',
];
$release_statuses = ['now_showing' => 'Now showing', 'upcoming' => 'Upcoming'];
$errors = [];
$account_notice = (string) ($_SESSION['account_notice'] ?? '');
unset($_SESSION['account_notice']);
$title = '';
$description = '';
$show_date = '';
$release_status = 'now_showing';
$available_from = '';
$available_until = '';
$show_time = '';
$duration = 120;
$price = 650.00;
$color = '#1e594d';
$poster_url = null;

if ($editing_movie_id) {
    $edit_statement = db()->prepare('SELECT title, description, show_date, release_status, available_from, available_until, show_time, duration_minutes, price, poster_color, poster_url FROM movies WHERE id = ?');
    $edit_statement->execute([$editing_movie_id]);
    $editing_movie = $edit_statement->fetch();
    if (!$editing_movie) {
        $editing_movie_id = 0;
    } else {
        $title = (string) $editing_movie['title'];
        $description = (string) $editing_movie['description'];
        $show_date = (string) $editing_movie['show_date'];
        $release_status = (string) $editing_movie['release_status'];
        $available_from = (string) $editing_movie['available_from'];
        $available_until = (string) $editing_movie['available_until'];
        $show_time = (string) $editing_movie['show_time'];
        $duration = (int) $editing_movie['duration_minutes'];
        $price = (float) $editing_movie['price'];
        $color = (string) $editing_movie['poster_color'];
        $poster_url = $editing_movie['poster_url'];
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'move_account_to_trash') {
        $user_id = (int) ($_POST['user_id'] ?? 0);
        if ($user_id !== (int) $_SESSION['user_id']) {
            $move_account = db()->prepare("UPDATE users SET account_status = 'removed' WHERE id = ? AND account_status = 'active'");
            $move_account->execute([$user_id]);
            $_SESSION['account_notice'] = $move_account->rowCount() ? 'Account moved to the trash bin.' : 'That account is not active.';
        } else {
            $_SESSION['account_notice'] = 'You cannot remove the account you are currently using.';
        }
        header('Location: admin.php#account-notice');
        exit;
    }
    if (($_POST['action'] ?? '') === 'restore_account') {
        $user_id = (int) ($_POST['user_id'] ?? 0);
        if ($user_id !== (int) $_SESSION['user_id']) {
            $restore_account = db()->prepare("UPDATE users SET account_status = 'active' WHERE id = ? AND account_status = 'removed'");
            $restore_account->execute([$user_id]);
            $_SESSION['account_notice'] = $restore_account->rowCount() ? 'Account restored.' : 'That account is no longer in the trash bin.';
        } else {
            $_SESSION['account_notice'] = 'You cannot restore the account you are currently using.';
        }
        header('Location: admin.php#account-notice');
        exit;
    }
    if (($_POST['action'] ?? '') === 'permanently_delete_account') {
        $user_id = (int) ($_POST['user_id'] ?? 0);
        $connection = db();
        try {
            $connection->beginTransaction();
            $account = $connection->prepare("SELECT id FROM users WHERE id = ? AND account_status = 'removed' AND id <> ? FOR UPDATE");
            $account->execute([$user_id, $_SESSION['user_id']]);
            if (!$account->fetch()) {
                $connection->rollBack();
                $_SESSION['account_notice'] = 'That account is not in the trash bin.';
            } else {
                $remove_seats = $connection->prepare('DELETE bs FROM booking_seats bs JOIN ticket_bookings tb ON tb.id = bs.booking_id WHERE tb.user_id = ?');
                $remove_seats->execute([$user_id]);
                $remove_bookings = $connection->prepare('DELETE FROM ticket_bookings WHERE user_id = ?');
                $remove_bookings->execute([$user_id]);
                $remove_account = $connection->prepare("DELETE FROM users WHERE id = ? AND account_status = 'removed'");
                $remove_account->execute([$user_id]);
                $connection->commit();
                $_SESSION['account_notice'] = 'Account and its booking history permanently deleted.';
            }
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) { $connection->rollBack(); }
            $_SESSION['account_notice'] = 'We could not permanently delete that account.';
        }
        header('Location: admin.php#account-notice');
        exit;
    }
    if (($_POST['action'] ?? '') === 'change_role') {
        $user_id = (int) ($_POST['user_id'] ?? 0);
        $new_role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'customer';
        if ($user_id !== (int) $_SESSION['user_id']) {
            $change_role = db()->prepare('UPDATE users SET role = ? WHERE id = ?');
            $change_role->execute([$new_role, $user_id]);
        }
        header('Location: admin.php');
        exit;
    }
    if (($_POST['action'] ?? '') === 'verify_payment') {
        $booking_code = trim((string) ($_POST['booking_code'] ?? ''));
        $verify = db()->prepare("UPDATE ticket_bookings SET payment_status = 'verified' WHERE booking_code = ? AND status = 'confirmed' AND payment_status = 'submitted'");
        $verify->execute([$booking_code]);
        header('Location: admin.php');
        exit;
    }
    if (($_POST['action'] ?? '') === 'cancel_booking') {
        $booking_code = trim((string) ($_POST['booking_code'] ?? ''));
        $cancel = db()->prepare("UPDATE ticket_bookings SET status = 'cancelled' WHERE booking_code = ? AND status = 'confirmed'");
        $cancel->execute([$booking_code]);
        header('Location: admin.php');
        exit;
    }
    if (($_POST['action'] ?? '') === 'remove_movie') {
        $movie_id = (int) ($_POST['movie_id'] ?? 0);
        $connection = db();
        try {
            $connection->beginTransaction();
            $remove_seats = $connection->prepare('DELETE bs FROM booking_seats bs JOIN ticket_bookings tb ON tb.id = bs.booking_id WHERE tb.movie_id = ?');
            $remove_seats->execute([$movie_id]);
            $remove_bookings = $connection->prepare('DELETE FROM ticket_bookings WHERE movie_id = ?');
            $remove_bookings->execute([$movie_id]);
            $remove_movie = $connection->prepare('DELETE FROM movies WHERE id = ?');
            $remove_movie->execute([$movie_id]);
            $connection->commit();
            header('Location: admin.php');
            exit;
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) { $connection->rollBack(); }
            $errors[] = 'We could not remove that movie. Please try again.';
        }
    }
    $title = trim((string) ($_POST['title'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $show_date = (string) ($_POST['show_date'] ?? '');
    $release_status = (string) ($_POST['release_status'] ?? 'now_showing');
    $available_from = (string) ($_POST['available_from'] ?? $show_date);
    $available_until = (string) ($_POST['available_until'] ?? $show_date);
    $show_time = (string) ($_POST['show_time'] ?? '');
    $duration = max(1, (int) ($_POST['duration_minutes'] ?? 120));
    $price = (float) ($_POST['price'] ?? 0);
    $color = (string) ($_POST['poster_color'] ?? '#1e594d');
    $editing_movie_id = (int) ($_POST['movie_id'] ?? 0);
    $existing_poster_url = $editing_movie_id ? (string) ($_POST['existing_poster_url'] ?? '') : '';
    $poster_url = $existing_poster_url !== '' ? $existing_poster_url : null;
    if ($title === '' || $description === '' || $show_date === '' || $available_from === '' || $available_until === '' || $show_time === '' || $price <= 0) { $errors[] = 'Please complete every movie field.'; }
    if (!array_key_exists($show_time, $screening_times)) { $errors[] = 'Please choose one of the designated screening time slots.'; }
    if (!array_key_exists($release_status, $release_statuses)) { $errors[] = 'Please choose a valid movie status.'; }
    if ($available_from > $available_until) { $errors[] = 'The available-from date must be before the available-until date.'; }
    if (isset($_FILES['poster_image']) && $_FILES['poster_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $poster_image = $_FILES['poster_image'];
        $allowed_types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        $image_info = @getimagesize($poster_image['tmp_name']);
        $image_type = $image_info['mime'] ?? '';
        if ($poster_image['error'] !== UPLOAD_ERR_OK || $poster_image['size'] > 5 * 1024 * 1024 || !isset($allowed_types[$image_type])) {
            $errors[] = 'Poster images must be JPG, PNG, WEBP, or GIF files under 5 MB.';
        } else {
            $poster_directory = __DIR__ . '/uploads/posters';
            if (!is_dir($poster_directory) && !mkdir($poster_directory, 0755, true) && !is_dir($poster_directory)) {
                $errors[] = 'The poster upload folder could not be created.';
            } else {
                $poster_filename = bin2hex(random_bytes(16)) . '.' . $allowed_types[$image_type];
                if (!move_uploaded_file($poster_image['tmp_name'], $poster_directory . '/' . $poster_filename)) {
                    $errors[] = 'The poster image could not be saved.';
                } else {
                    $poster_url = 'uploads/posters/' . $poster_filename;
                }
            }
        }
    }
    if (!$errors) {
        if (($_POST['action'] ?? '') === 'update_movie' && $editing_movie_id) {
            $statement = db()->prepare('UPDATE movies SET title = ?, description = ?, duration_minutes = ?, show_date = ?, release_status = ?, available_from = ?, available_until = ?, show_time = ?, price = ?, poster_color = ?, poster_url = ? WHERE id = ?');
            $statement->execute([$title, $description, $duration, $show_date, $release_status, $available_from, $available_until, $show_time, $price, $color, $poster_url, $editing_movie_id]);
        } else {
            $statement = db()->prepare('INSERT INTO movies (title, description, duration_minutes, show_date, release_status, available_from, available_until, show_time, price, poster_color, poster_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $statement->execute([$title, $description, $duration, $show_date, $release_status, $available_from, $available_until, $show_time, $price, $color, $poster_url]);
        }
        header('Location: admin.php');
        exit;
    }
}
$movie_query = 'SELECT id, title, description, show_date, release_status, available_from, available_until, show_time, price, poster_url FROM movies WHERE title LIKE ?';
$movie_parameters = ['%' . $movie_search . '%'];
if ($movie_month !== '') {
    $month_start = $movie_month . '-01';
    $month_end = (new DateTimeImmutable($month_start))->modify('+1 month')->format('Y-m-d');
    $movie_query .= ' AND show_date >= ? AND show_date < ?';
    $movie_parameters[] = $month_start;
    $movie_parameters[] = $month_end;
}
$movie_query .= ' ORDER BY show_date, show_time';
$movie_statement = db()->prepare($movie_query);
$movie_statement->execute($movie_parameters);
$movies = $movie_statement->fetchAll();
$recent = db()->query('SELECT tb.booking_code, tb.total_amount, tb.status, tb.payment_status, tb.created_at, m.title, u.name FROM ticket_bookings tb JOIN movies m ON m.id = tb.movie_id JOIN users u ON u.id = tb.user_id ORDER BY tb.created_at DESC LIMIT 10')->fetchAll();
$all_bookings = db()->query('SELECT tb.booking_code, tb.total_amount, tb.status, tb.payment_status, tb.created_at, m.title, u.name FROM ticket_bookings tb JOIN movies m ON m.id = tb.movie_id JOIN users u ON u.id = tb.user_id ORDER BY tb.created_at DESC')->fetchAll();
$users = db()->query("SELECT u.id, u.name, u.username, u.email, u.role, u.account_status, COUNT(tb.id) AS booking_count FROM users u LEFT JOIN ticket_bookings tb ON tb.user_id = u.id GROUP BY u.id, u.name, u.username, u.email, u.role, u.account_status ORDER BY u.id DESC")->fetchAll();
$trashed_users = array_values(array_filter($users, static fn (array $account): bool => $account['account_status'] === 'removed'));
$users = array_values(array_filter($users, static fn (array $account): bool => $account['account_status'] === 'active'));
$page_title = 'Admin';
require __DIR__ . '/header.php';
?>
<section class="listing-heading">
<p class="eyebrow">Admin console</p>
<h1>Run the screenings.</h1>
<p>Add movies and keep an eye on recent bookings.</p>
</section>
<?php if ($account_notice): ?><p class="alert alert-success" id="account-notice" role="status"><?= e($account_notice) ?></p><?php endif; ?>
<section class="admin-list admin-users" id="registered-users">
<div class="section-title"><h2>Registered users</h2></div>
<p class="muted">Manage the people who register, log in, and buy tickets.</p>
<?php if (!$users): ?><p class="muted">No active registered users.</p><?php endif; ?>
<?php foreach ($users as $account): ?>
<div class="user-row">
<div>
<strong>
<?= e($account['name']) ?>
</strong>
<span>
<?= e($account['email'] ?: ($account['username'] ?: 'No email or username')) ?> &middot; <?= (int) $account['booking_count'] ?> booking(s)</span>
</div>
<div class="user-controls">
<span class="role-badge role-<?= e($account['role']) ?>"><?= e(ucfirst($account['role'])) ?></span>
<?php if ((int) $account['id'] !== (int) $_SESSION['user_id']): ?>
<form method="post" action="admin.php">
<input type="hidden" name="action" value="change_role">
<input type="hidden" name="user_id" value="<?= (int) $account['id'] ?>">
<input type="hidden" name="role" value="<?= $account['role'] === 'admin' ? 'customer' : 'admin' ?>">
<button class="text-button" type="submit">Make <?= $account['role'] === 'admin' ? 'customer' : 'admin' ?>
</button>
</form>
<form method="post" action="admin.php">
<input type="hidden" name="action" value="move_account_to_trash">
<input type="hidden" name="user_id" value="<?= (int) $account['id'] ?>">
<button class="text-button danger-button" type="submit">Move to trash</button>
</form>
<?php else: ?>
<small>Current account</small>
<?php endif; ?>
</div>
</div>
<?php endforeach; ?>
<details class="user-trash" id="user-trash">
<summary>Trash bin <span><?= count($trashed_users) ?> account<?= count($trashed_users) === 1 ? '' : 's' ?></span></summary>
<p class="muted">Restore an account or permanently delete it with its booking history.</p>
<?php if (!$trashed_users): ?><p class="muted">The trash bin is empty.</p><?php endif; ?>
<?php foreach ($trashed_users as $account): ?>
<div class="user-row">
<div>
<strong><?= e($account['name']) ?></strong>
<span><?= e($account['email'] ?: ($account['username'] ?: 'No email or username')) ?> &middot; <?= (int) $account['booking_count'] ?> booking(s)</span>
</div>
<div class="user-controls">
<span class="role-badge role-<?= e($account['role']) ?>"><?= e(ucfirst($account['role'])) ?></span>
<form method="post" action="admin.php">
<input type="hidden" name="action" value="restore_account">
<input type="hidden" name="user_id" value="<?= (int) $account['id'] ?>">
<button class="text-button" type="submit">Restore</button>
</form>
<form method="post" action="admin.php" onsubmit="return confirm('Permanently delete this account and all of its booking history? This cannot be undone.');">
<input type="hidden" name="action" value="permanently_delete_account">
<input type="hidden" name="user_id" value="<?= (int) $account['id'] ?>">
<button class="text-button danger-button" type="submit">Delete permanently</button>
</form>
</div>
</div>
<?php endforeach; ?>
</details>
</section>
<section class="admin-list admin-bookings">
<h2>Manage bookings</h2>
<p class="muted">Remove a booking to cancel it and release its seats. The record stays saved for history.</p>
<?php if (!$all_bookings): ?>
<p class="muted">No bookings yet.</p>
<?php else: foreach ($all_bookings as $booking): ?>
<div class="booking-admin-row">
<div>
<strong>
<span class="code-value"><?= e($booking['booking_code']) ?></span> &middot; <?= e($booking['title']) ?>
</strong>
<span>
<?= e($booking['name']) ?> &middot; <?= e(peso((float) $booking['total_amount'])) ?>
</span>
</div>
<span class="booking-status status-<?= e($booking['status'] === 'confirmed' ? $booking['payment_status'] : $booking['status']) ?>"><?= e(ucfirst($booking['status'] === 'confirmed' ? $booking['payment_status'] : $booking['status'])) ?></span>
<?php if ($booking['status'] === 'confirmed'): ?>
<form method="post" action="admin.php">
<input type="hidden" name="action" value="cancel_booking">
<input type="hidden" name="booking_code" value="<?= e($booking['booking_code']) ?>">
<button class="text-button danger-button" type="submit">Remove booking</button>
</form>
<?php else: ?>
<span class="role-badge status-removed"><?= e(ucfirst($booking['status'])) ?></span>
<?php endif; ?>
</div>
<?php endforeach; endif; ?>
</section>
<div class="admin-grid">
<form class="form-card" method="post" action="admin.php" enctype="multipart/form-data">
<h2><?= $editing_movie_id ? 'Edit movie' : 'Add a movie' ?></h2>
<input type="hidden" name="action" value="<?= $editing_movie_id ? 'update_movie' : 'add_movie' ?>">
<?php if ($editing_movie_id): ?><input type="hidden" name="movie_id" value="<?= (int) $editing_movie_id ?>"><input type="hidden" name="existing_poster_url" value="<?= e((string) $poster_url) ?>"><?php endif; ?>
<?php if ($errors): ?>
<div class="alert alert-error">
<?= e($errors[0]) ?>
</div>
<?php endif; ?>
<label for="title">Movie title</label>
<input id="title" name="title" value="<?= e($title) ?>" required>
<label for="description">Description</label>
<textarea id="description" name="description" rows="4" required><?= e($description) ?></textarea>
<div class="form-two">
<div>
<label for="show_date">Display date</label>
<input id="show_date" name="show_date" type="date" value="<?= e($show_date) ?>" required>
</div>
<div>
<label for="release_status">Movie status</label>
<select id="release_status" name="release_status" required>
<?php foreach ($release_statuses as $status_value => $status_label): ?><option value="<?= e($status_value) ?>" <?= $release_status === $status_value ? 'selected' : '' ?>><?= e($status_label) ?></option><?php endforeach; ?>
</select>
</div>
<div>
<label for="show_time">Time slot</label>
<select id="show_time" name="show_time" required>
<?php foreach ($screening_times as $slot_time => $slot_label): ?><option value="<?= e($slot_time) ?>" <?= $show_time === $slot_time ? 'selected' : '' ?>><?= e($slot_label) ?></option><?php endforeach; ?>
</select>
</div>
</div>
<div class="form-two">
<div>
<label for="available_from">Available from</label>
<input id="available_from" name="available_from" type="date" value="<?= e($available_from) ?>" required>
</div>
<div>
<label for="available_until">Available until</label>
<input id="available_until" name="available_until" type="date" value="<?= e($available_until) ?>" required>
</div>
</div>
<div class="form-two">
<div>
<label for="duration_minutes">Minutes</label>
<input id="duration_minutes" name="duration_minutes" type="number" min="1" value="<?= (int) $duration ?>" required>
</div>
<div>
<label for="price">Price in pesos</label>
<input id="price" name="price" type="number" min="0.01" step="0.01" value="<?= e(number_format($price, 2, '.', '')) ?>" required>
</div>
</div>
<label for="poster_image">Poster image</label>
<input id="poster_image" name="poster_image" type="file" accept="image/jpeg,image/png,image/webp,image/gif">
<p class="form-note">Use a JPG, PNG, WEBP, or GIF image up to 5 MB.</p>
<label for="poster_color">Poster color</label>
<input id="poster_color" name="poster_color" type="color" value="<?= e($color) ?>">
<button class="button button-primary button-full" type="submit"><?= $editing_movie_id ? 'Save movie changes' : 'Add movie' ?></button>
<?php if ($editing_movie_id): ?><a class="button button-quiet button-full" href="admin.php">Cancel editing</a><?php endif; ?>
</form>
<div class="admin-side">
<section class="admin-list">
<h2>Scheduled movies</h2>
<div class="admin-list-scroll">
<?php foreach ($movies as $movie): ?>
<div class="admin-row">
<div>
<strong>
<?= e($movie['title']) ?>
</strong>
<span>
<?= e(date('M j', strtotime($movie['show_date']))) ?> at <?= e(date('g:i A', strtotime($movie['show_time']))) ?>
</span>
<span><?= e($release_statuses[$movie['release_status']] ?? 'Now showing') ?> &middot; Available <?= e(date('M j', strtotime($movie['available_from']))) ?> - <?= e(date('M j, Y', strtotime($movie['available_until']))) ?></span>
<p class="admin-movie-description"><?= e($movie['description']) ?></p>
</div>
<div class="admin-row-actions"><b><?= e(peso((float) $movie['price'])) ?></b><a class="text-button" href="admin.php?edit_movie=<?= (int) $movie['id'] ?>&movie_search=<?= urlencode($movie_search) ?>&movie_month=<?= urlencode($movie_month) ?>">Edit</a></div>
</div>
<?php endforeach; ?>
</div>
</section>
<section class="admin-list">
<h2>Recent bookings</h2>
<div class="admin-list-scroll">
<?php if (!$recent): ?>
<p class="muted">No bookings yet.</p>
<?php else: foreach ($recent as $booking): ?>
<div class="admin-row">
<div>
<strong>
<span class="code-value"><?= e($booking['booking_code']) ?></span>
</strong>
<span>
<?= e($booking['name']) ?> &middot; <?= e($booking['title']) ?>
</span>
</div>
<div class="admin-payment">
<span class="booking-status status-<?= e($booking['status'] === 'confirmed' ? $booking['payment_status'] : $booking['status']) ?>"><?= e(ucfirst($booking['status'] === 'confirmed' ? $booking['payment_status'] : $booking['status'])) ?></span>
<b>
<?= e(peso((float) $booking['total_amount'])) ?>
</b>
<?php if ($booking['payment_status'] === 'submitted'): ?>
<form method="post" action="admin.php">
<input type="hidden" name="action" value="verify_payment">
<input type="hidden" name="booking_code" value="<?= e($booking['booking_code']) ?>">
<button class="text-button" type="submit">Verify payment</button>
</form>
<?php endif; ?>
</div>
</div>
<?php endforeach; endif; ?>
</div>
</section>
</div>
</div>
<section class="admin-list admin-remove-movies">
    <h2>Remove movies</h2>
    <p class="muted">Removing a movie also removes its booking and seat records.</p>
    <form class="schedule-filters" method="get" action="admin.php">
        <label for="admin_movie_search">Search movies</label>
        <input id="admin_movie_search" name="movie_search" type="search" value="<?= e($movie_search) ?>" placeholder="Movie title">
        <label for="admin_movie_month">Month</label>
        <input id="admin_movie_month" name="movie_month" type="month" value="<?= e($movie_month) ?>">
        <button class="button button-primary" type="submit">Filter movies</button>
    </form>
    <div class="admin-list-scroll admin-remove-movies-scroll">
    <?php foreach ($movies as $movie): ?>
        <div class="admin-row">
            <div>
                <strong>
<?= e($movie['title']) ?>
</strong>
                <span>
<?= e(date('M j, Y', strtotime($movie['show_date']))) ?> at <?= e(date('g:i A', strtotime($movie['show_time']))) ?>
</span>
                                <span><?= e($release_statuses[$movie['release_status']] ?? 'Now showing') ?></span>
                <p class="admin-movie-description"><?= e($movie['description']) ?></p>
            </div>
            <div class="admin-row-actions"><a class="text-button" href="admin.php?edit_movie=<?= (int) $movie['id'] ?>&movie_search=<?= urlencode($movie_search) ?>&movie_month=<?= urlencode($movie_month) ?>">Edit</a><form method="post" action="admin.php" onsubmit="return confirm('Remove this movie and all of its booking records?');">
                <input type="hidden" name="action" value="remove_movie">
                <input type="hidden" name="movie_id" value="<?= (int) $movie['id'] ?>">
                <button class="text-button danger-button" type="submit">Remove movie</button>
            </form></div>
        </div>
    <?php endforeach; ?>
    </div>
</section>
<?php require __DIR__ . '/footer.php'; ?>
