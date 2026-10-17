<?php
require_once __DIR__ . '/auth.php';
require_login();

$movie_search = trim((string) ($_GET['movie_search'] ?? ''));
$booking_count_statement = db()->prepare("SELECT COUNT(*) FROM ticket_bookings WHERE user_id = ? AND status = 'confirmed' AND watch_date >= CURDATE()");
$booking_count_statement->execute([$_SESSION['user_id']]);
$booking_count = (int) $booking_count_statement->fetchColumn();
$seat_count_statement = db()->prepare("SELECT COUNT(*) FROM booking_seats bs JOIN ticket_bookings tb ON tb.id = bs.booking_id WHERE tb.user_id = ? AND tb.status = 'confirmed'");
$seat_count_statement->execute([$_SESSION['user_id']]);
$seat_count = (int) $seat_count_statement->fetchColumn();
$recent_bookings = db()->prepare("SELECT tb.booking_code, tb.watch_date, tb.total_amount, tb.status, tb.payment_status, tb.created_at, m.title FROM ticket_bookings tb JOIN movies m ON m.id = tb.movie_id WHERE tb.user_id = ? ORDER BY tb.created_at DESC");
$recent_bookings->execute([$_SESSION['user_id']]);
$recent_bookings = $recent_bookings->fetchAll();
$upcoming_bookings = db()->prepare("SELECT tb.booking_code, tb.watch_date, tb.total_amount, tb.payment_status, m.title, m.show_time FROM ticket_bookings tb JOIN movies m ON m.id = tb.movie_id WHERE tb.user_id = ? AND tb.status = 'confirmed' AND tb.watch_date >= CURDATE() ORDER BY tb.watch_date, m.show_time");
$upcoming_bookings->execute([$_SESSION['user_id']]);
$upcoming_bookings = $upcoming_bookings->fetchAll();
$schedule_end = date('Y-m-d', strtotime('+14 days'));
$dashboard_movie_statement = db()->prepare('SELECT id, title, description, duration_minutes, show_date, show_time, price, poster_color, poster_url FROM movies WHERE show_date > ? AND title LIKE ? ORDER BY show_date, show_time LIMIT 4');
$dashboard_movie_statement->execute([$schedule_end, '%' . $movie_search . '%']);
$dashboard_movies = $dashboard_movie_statement->fetchAll();
$weekly_movie_statement = db()->prepare('SELECT id, title, description, duration_minutes, show_date, show_time, cinema_number, price, poster_color, poster_url FROM movies WHERE show_date BETWEEN CURDATE() AND ? AND title LIKE ? ORDER BY show_date, show_time');
$weekly_movie_statement->execute([$schedule_end, '%' . $movie_search . '%']);
$weekly_movies = $weekly_movie_statement->fetchAll();
$current_time = date('Y-m-d H:i:s');
$now_showing_statement = db()->prepare('SELECT id, title, description, duration_minutes, show_date, show_time, price, poster_color, poster_url FROM movies WHERE TIMESTAMP(show_date, show_time) <= ? AND DATE_ADD(TIMESTAMP(show_date, show_time), INTERVAL duration_minutes MINUTE) > ? AND title LIKE ? ORDER BY show_time');
$now_showing_statement->execute([$current_time, $current_time, '%' . $movie_search . '%']);
$now_showing_movies = $now_showing_statement->fetchAll();
$admin_movies = [];
if (is_admin()) {
    $admin_movie_statement = db()->prepare('SELECT id, title, show_date, show_time, price FROM movies WHERE title LIKE ? ORDER BY show_date, show_time');
    $admin_movie_statement->execute(['%' . $movie_search . '%']);
    $admin_movies = $admin_movie_statement->fetchAll();
}
$admin_bookings = is_admin() ? db()->query('SELECT tb.booking_code, tb.watch_date, tb.total_amount, tb.status, tb.payment_status, tb.created_at, m.title, u.name FROM ticket_bookings tb JOIN movies m ON m.id = tb.movie_id JOIN users u ON u.id = tb.user_id ORDER BY tb.created_at DESC')->fetchAll() : [];

$page_title = 'My bookings';
require __DIR__ . '/header.php';
?>
<section class="dashboard-heading">
    <p class="eyebrow">Your dashboard</p>
    <h1>Good to see you, <?= e($user['name']) ?>.</h1>
    <p>Manage your account and keep every journey in one place.</p>
</section>
<?php if (!is_admin()): ?><section class="dashboard-stats">
    <div class="stat-card"><span class="summary-label">Upcoming trips</span><strong><?= $booking_count ?></strong><p><?= $booking_count ? 'Confirmed bookings' : 'No upcoming bookings' ?></p></div>
    <div class="stat-card"><span class="summary-label">Tickets booked</span><strong><?= $seat_count ?></strong><p><?= $seat_count ? 'Seats reserved' : 'Your tickets will appear here' ?></p></div>
    <div class="stat-card stat-card-accent"><span class="summary-label">Member since</span><strong><?= date('Y') ?></strong><p>Welcome to Yazaki Ticket Bookin'</p></div>
</section><?php endif; ?>
<?php if (is_admin()): ?>
<section class="admin-dashboard-grid">
    <div class="admin-panel">
        <div class="section-title">
            <h2>All bookings</h2>
            <a class="button button-quiet" href="admin.php">Full admin</a>
        </div>

        <?php if (!$admin_bookings): ?>
            <p class="muted">No bookings yet.</p>
        <?php else: ?>
            <?php foreach ($admin_bookings as $booking): ?>
                <div class="booking-admin-row">
                    <div class="booking-info">
                        <strong><?= e($booking['booking_code']) ?> &middot; <?= e($booking['title']) ?></strong>
                        <span><?= e($booking['name']) ?> &middot; <?= e(date('M j, Y', strtotime($booking['watch_date']))) ?> &middot; <?= e(peso((float) $booking['total_amount'])) ?></span>
                    </div>
                    <span class="booking-status status-<?= e($booking['status'] === 'confirmed' ? $booking['payment_status'] : $booking['status']) ?>"><?= e(ucfirst($booking['status'] === 'confirmed' ? $booking['payment_status'] : $booking['status'])) ?></span>

                    <?php if ($booking['status'] === 'confirmed'): ?>
                        <form method="post" action="admin.php">
                            <input type="hidden" name="action" value="cancel_booking">
                            <input type="hidden" name="booking_code" value="<?= e($booking['booking_code']) ?>">
                            <button class="text-button danger-button" type="submit">Remove</button>
                        </form>
                    <?php else: ?>
                        <span class="role-badge status-removed"><?= e(ucfirst($booking['status'])) ?></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="admin-panel">
        <div class="section-title">
            <h2>Movies created</h2>
            <a class="button button-quiet" href="admin.php">Add movie</a>
        </div>
        <form class="schedule-filters" method="get" action="dashboard.php">
            <label for="dashboard_movie_search">Search movies</label>
            <input id="dashboard_movie_search" name="movie_search" type="search" value="<?= e($movie_search) ?>" placeholder="Movie title">
            <button class="button button-primary" type="submit">Search</button>
        </form>

        <div class="admin-list-scroll">
        <?php foreach ($admin_movies as $movie): ?>
            <div class="admin-row">
                <div>
                    <strong><?= e($movie['title']) ?></strong>
                    <span><?= e(date('M j, Y', strtotime($movie['show_date']))) ?> at <?= e(date('g:i A', strtotime($movie['show_time']))) ?></span>
                </div>
                <b><?= e(peso((float) $movie['price'])) ?></b>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
</section>
<?php else: ?>
<section class="booking-history upcoming-booking-list">
    <div class="section-title"><h2>Upcoming bookings</h2><a class="button button-quiet" href="movies.php">Book another</a></div>
    <?php if (!$upcoming_bookings): ?><p class="muted">No upcoming bookings yet.</p><?php else: foreach ($upcoming_bookings as $booking): ?><a class="booking-row" href="receipt.php?code=<?= urlencode($booking['booking_code']) ?>"><div><strong><?= e($booking['title']) ?></strong><span><?= e($booking['booking_code']) ?> &middot; <?= e(date('M j, Y', strtotime($booking['watch_date']))) ?> at <?= e(date('g:i A', strtotime($booking['show_time']))) ?></span></div><span class="booking-status status-<?= e($booking['payment_status']) ?>"><?= e(ucfirst($booking['payment_status'])) ?></span><b><?= e(peso((float) $booking['total_amount'])) ?></b></a><?php endforeach; endif; ?>
</section>

<section class="dashboard-movies">
    <div class="section-title">
        <h2>Ongoing Movies</h2>
    </div>
    <div class="dashboard-movie-grid">
        <?php foreach ($now_showing_movies as $movie): ?>
            <article class="dashboard-movie-card">
                <div class="dashboard-movie-poster" style="background: <?= e($movie['poster_color'] ?: '#b61111') ?>;">
                    <?php if (!empty($movie['poster_url'])): ?>
                        <img src="<?= e($movie['poster_url']) ?>" alt="Poster for <?= e($movie['title']) ?>">
                    <?php else: ?>
                        <strong><?= e(strtoupper(substr($movie['title'], 0, 1))) ?></strong>
                    <?php endif; ?>
                </div>
                <div class="dashboard-movie-info">
                    <p><?= e(date('D, M j', strtotime($movie['show_date']))) ?> at <?= e(date('g:i A', strtotime($movie['show_time']))) ?></p>
                    <h3><?= e($movie['title']) ?></h3>
                    <a class="button button-primary" href="seats.php?movie_id=<?= (int) $movie['id'] ?>">Find tickets</a>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if (!$now_showing_movies): ?><p class="muted">No movies are ongoing<?= $movie_search ? ' that match your search' : '' ?>.</p><?php endif; ?>
    </div>
</section>

<section class="dashboard-movies">
    <div class="section-title">
        <div>
            <h2>Now Showing</h2>
            <p class="section-note">Screenings scheduled for the next 15 days.</p>
        </div>
        <a class="button button-quiet" href="movies.php?view=now">Full schedule</a>
    </div>
    <div class="dashboard-movie-grid">
        <?php foreach ($weekly_movies as $movie): ?>
            <article class="dashboard-movie-card">
                <div class="dashboard-movie-poster" style="background: <?= e($movie['poster_color'] ?: '#b61111') ?>;">
                    <?php if (!empty($movie['poster_url'])): ?>
                        <img src="<?= e($movie['poster_url']) ?>" alt="Poster for <?= e($movie['title']) ?>">
                    <?php else: ?>
                        <strong><?= e(strtoupper(substr($movie['title'], 0, 1))) ?></strong>
                    <?php endif; ?>
                </div>
                <div class="dashboard-movie-info">
                    <p><?= e(date('D, M j', strtotime($movie['show_date']))) ?> at <?= e(date('g:i A', strtotime($movie['show_time']))) ?> &middot; Cinema <?= (int) $movie['cinema_number'] ?></p>
                    <h3><?= e($movie['title']) ?></h3>
                    <a class="button button-primary" href="seats.php?movie_id=<?= (int) $movie['id'] ?>">Find tickets</a>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if (!$weekly_movies): ?><p class="muted">No movies are scheduled in the next 15 days<?= $movie_search ? ' that match your search' : '' ?>.</p><?php endif; ?>
    </div>
</section>

<section class="dashboard-movies">
    <div class="section-title">
        <h2>Search Movies</h2>
    </div>
    <form class="schedule-filters" method="get" action="dashboard.php">
        <label for="customer_movie_search">Search movies</label>
        <input id="customer_movie_search" name="movie_search" type="search" value="<?= e($movie_search) ?>" placeholder="Movie title">
        <button class="button button-primary" type="submit">Search</button>
    </form>
</section>
<?php if ($recent_bookings): ?>
<section class="booking-history"><div class="section-title"><h2>Recent bookings</h2><a class="button button-quiet" href="movies.php">Book another</a></div><?php foreach ($recent_bookings as $booking): $booking_label = $booking['status'] === 'confirmed' ? $booking['payment_status'] : $booking['status']; ?><a class="booking-row" href="receipt.php?code=<?= urlencode($booking['booking_code']) ?>"><div><strong><?= e($booking['title']) ?></strong><span><?= e($booking['booking_code']) ?> &middot; Watching <?= e(date('M j, Y', strtotime($booking['watch_date']))) ?></span></div><span class="booking-status status-<?= e($booking_label) ?>"><?= e(ucfirst($booking_label)) ?></span><b><?= e(peso((float) $booking['total_amount'])) ?></b></a><?php endforeach; ?></section>
<?php else: ?>
<section class="dashboard-panel">
    <div class="panel-icon">T</div>
    <div><h2>No bookings yet</h2><p>When you book a trip, your tickets and travel details will live here.</p></div>
    <a class="button button-primary" href="movies.php">Book a ticket</a>
</section>
 <?php endif; ?>
 <?php endif; ?>
 <?php require __DIR__ . '/footer.php'; ?>
