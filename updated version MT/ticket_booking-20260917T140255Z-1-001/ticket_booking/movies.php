<?php
require_once __DIR__ . '/auth.php';

$selected_cinema = filter_input(INPUT_GET, 'cinema', FILTER_VALIDATE_INT);
$selected_cinema = in_array($selected_cinema, [1, 2, 3], true) ? $selected_cinema : null;
$selected_slot = (string) ($_GET['slot'] ?? 'all');
$slot_filters = [
    'morning' => "show_time >= '08:00:00' AND show_time < '12:00:00'",
    'midday' => "show_time >= '12:00:00' AND show_time < '16:00:00'",
    'afternoon' => "show_time >= '16:00:00' AND show_time < '20:00:00'",
    'evening' => "show_time >= '20:00:00' AND show_time <= '23:59:59'",
];
$selected_slot = array_key_exists($selected_slot, $slot_filters) ? $selected_slot : 'all';
$view_mode = ($_GET['view'] ?? 'all');
$movie_filter = $selected_cinema ? ' AND cinema_number = ' . $selected_cinema : '';
$movie_filter .= $selected_slot !== 'all' ? ' AND ' . $slot_filters[$selected_slot] : '';
$movie_columns = 'id, title, description, duration_minutes, show_date, release_status, available_from, available_until, show_time, cinema_number, price, poster_color, poster_url';
$schedule_end = date('Y-m-d', strtotime('+14 days'));
$weekly_statement = db()->prepare("SELECT $movie_columns FROM movies WHERE show_date BETWEEN CURDATE() AND ? $movie_filter ORDER BY show_date, show_time");
$weekly_statement->execute([$schedule_end]);
$weekly_movies = $weekly_statement->fetchAll();
$current_time = date('Y-m-d H:i:s');
$now_showing_statement = db()->prepare("SELECT $movie_columns FROM movies WHERE TIMESTAMP(show_date, show_time) <= ? AND DATE_ADD(TIMESTAMP(show_date, show_time), INTERVAL duration_minutes MINUTE) > ? $movie_filter ORDER BY show_date, show_time");
$now_showing_statement->execute([$current_time, $current_time]);
$now_showing = $now_showing_statement->fetchAll();
$next_day_window_statement = db()->prepare("SELECT $movie_columns FROM movies WHERE show_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 1 DAY) $movie_filter ORDER BY show_date, show_time");
$next_day_window_statement->execute();
$window_movies = $next_day_window_statement->fetchAll();
$upcoming_statement = db()->prepare("SELECT $movie_columns FROM movies WHERE show_date > ? $movie_filter ORDER BY show_date, show_time");
$upcoming_statement->execute([$schedule_end]);
$upcoming = $upcoming_statement->fetchAll();
$past_statement = db()->query("SELECT $movie_columns FROM movies WHERE show_date < CURDATE() $movie_filter ORDER BY show_date DESC, show_time DESC");
$past = $past_statement->fetchAll();
$show_simple_now_view = $view_mode === 'now';
$coming_soon_samples = [
    ['title' => 'Spider-Man: No Way Home', 'description' => 'Spider-Man faces the consequences of an unmasked identity when villains from other worlds arrive.', 'show_date' => date('Y-m-d', strtotime('+28 days')), 'show_time' => '16:00:00', 'duration_minutes' => 148, 'price' => 650, 'cinema_number' => 1, 'poster_color' => '#b51d1d', 'poster_url' => null, 'id' => 0],
    ['title' => 'Train to Busan', 'description' => 'Passengers fight to survive a fast-moving zombie outbreak aboard a train to Busan.', 'show_date' => date('Y-m-d', strtotime('+35 days')), 'show_time' => '16:00:00', 'duration_minutes' => 118, 'price' => 600, 'cinema_number' => 2, 'poster_color' => '#1e594d', 'poster_url' => null, 'id' => 0],
    ['title' => 'The Dark Knight', 'description' => 'Batman faces a criminal mastermind who throws Gotham into chaos.', 'show_date' => date('Y-m-d', strtotime('+42 days')), 'show_time' => '20:00:00', 'duration_minutes' => 152, 'price' => 700, 'cinema_number' => 3, 'poster_color' => '#242424', 'poster_url' => null, 'id' => 0],
];
$screened_samples = [
    ['title' => 'Parasite', 'description' => 'A struggling family becomes entangled with a wealthy household.', 'show_date' => date('Y-m-d', strtotime('-7 days')), 'show_time' => '16:00:00', 'duration_minutes' => 132, 'price' => 620, 'cinema_number' => 1, 'poster_color' => '#7c1111', 'poster_url' => null, 'id' => 0],
    ['title' => 'Inception', 'description' => 'A skilled extractor enters dreams to plant an idea in a target’s mind.', 'show_date' => date('Y-m-d', strtotime('-14 days')), 'show_time' => '16:00:00', 'duration_minutes' => 148, 'price' => 580, 'cinema_number' => 2, 'poster_color' => '#a61818', 'poster_url' => null, 'id' => 0],
    ['title' => 'Interstellar', 'description' => 'Explorers travel through a wormhole searching for a future for humanity.', 'show_date' => date('Y-m-d', strtotime('-21 days')), 'show_time' => '20:00:00', 'duration_minutes' => 169, 'price' => 640, 'cinema_number' => 3, 'poster_color' => '#4e0d0d', 'poster_url' => null, 'id' => 0],
];
$upcoming_is_sample = !$upcoming;
$past_is_sample = !$past;
if ($upcoming_is_sample) { $upcoming = $coming_soon_samples; }
if ($past_is_sample) { $past = $screened_samples; }
$page_title = 'Choose a movie';
require __DIR__ . '/header.php';
?>
<section class="listing-heading"><p class="eyebrow">Cinema schedule</p><h1>Pick your next story.</h1><p>Current screenings roll forward automatically, and Upcoming movies are available for advance booking.</p></section>
<?php if (!$show_simple_now_view): ?>
<?php $booking_step = 1; require __DIR__ . '/booking_progress.php'; ?>
<form class="schedule-filters" method="get" action="movies.php"><label for="cinema">Cinema</label><select id="cinema" name="cinema"><option value="">All cinemas</option><?php for ($cinema = 1; $cinema <= 3; $cinema++): ?><option value="<?= $cinema ?>" <?= $selected_cinema === $cinema ? 'selected' : '' ?>>Cinema <?= $cinema ?></option><?php endfor; ?></select><label for="slot">Time slot</label><select id="slot" name="slot"><option value="all" <?= $selected_slot === 'all' ? 'selected' : '' ?>>All times</option><option value="morning" <?= $selected_slot === 'morning' ? 'selected' : '' ?>>8:00 AM - 11:00 AM</option><option value="midday" <?= $selected_slot === 'midday' ? 'selected' : '' ?>>12:00 PM - 3:00 PM</option><option value="afternoon" <?= $selected_slot === 'afternoon' ? 'selected' : '' ?>>4:00 PM - 7:00 PM</option><option value="evening" <?= $selected_slot === 'evening' ? 'selected' : '' ?>>8:00 PM - 12:00 AM</option></select><button class="button button-primary" type="submit">Show schedule</button><?php if ($selected_cinema || $selected_slot !== 'all'): ?><a class="button button-quiet" href="movies.php">Clear filters</a><?php endif; ?></form>
<section class="time-slots" aria-label="Cinema time slots"><strong>Daily screening slots</strong><span>8:00 AM - 11:00 AM</span><span>12:00 PM - 3:00 PM</span><span>4:00 PM - 7:00 PM</span><span>8:00 PM - 12:00 AM</span></section>
<?php endif; ?>
<?php if ($show_simple_now_view): ?>
<?php $simple_now_movies = $weekly_movies; ?>
<?php if ($simple_now_movies): ?>
<section class="movie-section movie-section-simple">
    <div class="section-title"><div><p class="eyebrow">Now Showing</p><p class="section-note">Screenings scheduled for the next 15 days.</p></div><strong><?= count($simple_now_movies) ?> title<?= count($simple_now_movies) === 1 ? '' : 's' ?></strong></div>
    <div class="movie-grid movie-grid-simple">
        <?php foreach ($simple_now_movies as $movie): ?>
            <article class="movie-card movie-card-simple">
                <div class="movie-poster" style="background: <?= e($movie['poster_color'] ?: '#8d1212') ?>;"><?php if (!empty($movie['poster_url'])): ?><img src="<?= e($movie['poster_url']) ?>" alt="Cover for <?= e($movie['title']) ?>"><?php else: ?><strong><?= e(strtoupper(substr($movie['title'], 0, 1))) ?></strong><?php endif; ?></div>
                <div class="movie-info"><p class="movie-meta">Cinema <?= (int) $movie['cinema_number'] ?> &middot; <?= e(date('M j', strtotime($movie['show_date']))) ?> &middot; <?= e(date('g:i A', strtotime($movie['show_time']))) ?> &middot; <?= (int) $movie['duration_minutes'] ?> min</p><h2><?= e($movie['title']) ?></h2><p class="movie-description"><?= e($movie['description']) ?></p><div class="movie-action"><strong><?= e(peso((float) $movie['price'])) ?><small> / ticket</small></strong><a class="button button-primary" href="<?= is_logged_in() ? 'seats.php?movie_id=' . (int) $movie['id'] : 'login.php' ?>"><?= is_logged_in() ? 'Choose screening' : 'Log in to book' ?> <span aria-hidden="true">&rarr;</span></a></div></div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
<?php else: ?>
<?php foreach ([['Now Showing', 'Screenings scheduled for the next 15 days.', $weekly_movies, true, 'No movies scheduled in the next 15 days', 'New screenings may be added by the cinema.'], ['Coming soon', 'Reserve tickets up to six weeks ahead. These titles move into Now Showing as their dates approach.', $upcoming, false, 'New movies are on the way', 'Check back soon for upcoming titles and advance booking dates.'], ['Previously shown', 'A record of movies that have already screened.', $past, false, 'No previously screened movies', 'Your screening history will appear here after movies have finished their schedule.']] as [$section_title, $section_note, $section_movies, $booking_open, $empty_title, $empty_note]): ?>
        <section class="movie-section">
                        <div class="section-title"><div><p class="eyebrow"><?= e($section_title) ?></p><p class="section-note"><?= e($section_note) ?></p></div><strong><?= count($section_movies) ?> title<?= count($section_movies) === 1 ? '' : 's' ?></strong></div>
            <?php if ($section_movies): ?>
            <div class="movie-grid">
                <?php foreach ($section_movies as $movie): ?>
                    <article class="movie-card">
                        <div class="movie-poster" style="background: <?= e($movie['poster_color'] ?: '#8d1212') ?>;"><?php if (!empty($movie['poster_url'])): ?><img src="<?= e($movie['poster_url']) ?>" alt="Cover for <?= e($movie['title']) ?>"><?php else: ?><strong><?= e(strtoupper(substr($movie['title'], 0, 1))) ?></strong><?php endif; ?></div>
                        <?php $is_sample = (int) $movie['id'] === 0; $can_book = $booking_open && !$is_sample && $movie['available_until'] >= date('Y-m-d') && $movie['show_date'] >= date('Y-m-d'); ?>
                                            <div class="movie-info"><p class="movie-meta">Cinema <?= (int) $movie['cinema_number'] ?> &middot; <?= e(date('M j', strtotime($movie['show_date']))) ?> &middot; <?= e(date('g:i A', strtotime($movie['show_time']))) ?> &middot; <?= (int) $movie['duration_minutes'] ?> min</p><h2><?= e($movie['title']) ?></h2><p class="movie-description"><?= e($movie['description']) ?></p><?php if (!$is_sample): ?><p class="movie-availability">Available <?= e(date('M j', strtotime($movie['available_from']))) ?> to <?= e(date('M j, Y', strtotime($movie['available_until']))) ?></p><?php endif; ?><div class="movie-action"><strong><?= e(peso((float) $movie['price'])) ?><small> / ticket</small></strong><?php if ($can_book): ?><a class="button button-primary" href="<?= is_logged_in() ? 'seats.php?movie_id=' . (int) $movie['id'] : 'login.php' ?>"><?= is_logged_in() ? 'Choose screening' : 'Log in to book' ?> <span aria-hidden="true">&rarr;</span></a><?php else: ?><span class="booking-status status-unavailable"><?= $section_title === 'Now showing' ? 'Playing now' : ($section_title === 'Coming soon' ? ($is_sample ? 'Preview listing' : 'Booking opens later') : ($section_title === 'Previously shown' ? 'Screened' : 'Booking unavailable')) ?></span><?php endif; ?></div></div>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
                <div class="schedule-placeholder">
                    <span class="schedule-placeholder-mark" aria-hidden="true">+</span>
                    <h2><?= e($empty_title) ?></h2>
                    <p><?= e($empty_note) ?></p>
                </div>
            <?php endif; ?>
        </section>
<?php endforeach; ?>
<?php endif; ?>
<?php if (!$weekly_movies && !$now_showing && !$upcoming && !$past): ?><div class="empty-state"><h2>No movies scheduled</h2><p>Check back soon for the next screening.</p></div><?php endif; ?>
<?php require __DIR__ . '/footer.php'; ?>