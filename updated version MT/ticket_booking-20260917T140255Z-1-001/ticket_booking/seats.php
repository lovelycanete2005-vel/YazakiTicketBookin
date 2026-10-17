<?php
require_once __DIR__ . '/auth.php';
require_login();

$movie_id = filter_input(INPUT_GET, 'movie_id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'movie_id', FILTER_VALIDATE_INT);
$movie_statement = db()->prepare('SELECT * FROM movies WHERE id = ? AND available_until >= CURDATE()');
$movie_statement->execute([$movie_id]);
$movie = $movie_statement->fetch();
if (!$movie) { header('Location: movies.php'); exit; }
$watch_date = (string) ($_POST['watch_date'] ?? $_GET['watch_date'] ?? $movie['available_from']);
$watch_date_object = DateTimeImmutable::createFromFormat('!Y-m-d', $watch_date);
if (!$watch_date_object || $watch_date < $movie['available_from'] || $watch_date > $movie['available_until'] || $watch_date < date('Y-m-d')) {
    $watch_date = max($movie['available_from'], date('Y-m-d'));
}

$errors = [];
$selected_seats = $_POST['seats'] ?? [];
$selected_seats = is_array($selected_seats) ? array_values(array_unique($selected_seats)) : [];
$seat_layout = [
    'Stalls' => array_combine(range('A', 'F'), array_fill(0, 6, range(1, 10))),
    'Balcony' => array_combine(range('J', 'M'), array_fill(0, 4, range(1, 10))),
];
$valid_seats = [];
foreach ($seat_layout as $section_rows) {
    foreach ($section_rows as $row => $numbers) {
        foreach ($numbers as $number) { $valid_seats[] = $row . $number; }
    }
}

$booked_statement = db()->prepare("SELECT bs.seat_label, tb.user_id FROM booking_seats bs JOIN ticket_bookings tb ON tb.id = bs.booking_id WHERE tb.movie_id = ? AND tb.watch_date = ? AND tb.status = 'confirmed' AND (tb.payment_status <> 'pending' OR tb.expires_at > NOW())");
$booked_statement->execute([$movie_id, $watch_date]);
$booked_seat_rows = $booked_statement->fetchAll();
$booked_seats = array_column($booked_seat_rows, 'seat_label');
$own_booked_seats = [];
foreach ($booked_seat_rows as $booked_seat_row) {
    if ((int) $booked_seat_row['user_id'] === (int) $_SESSION['user_id']) {
        $own_booked_seats[] = $booked_seat_row['seat_label'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_seats = array_values(array_intersect($selected_seats, $valid_seats));
    if (!$watch_date_object || $watch_date < $movie['available_from'] || $watch_date > $movie['available_until'] || $watch_date < date('Y-m-d')) { $errors[] = 'Please choose a valid available viewing date.'; }
    if (!$selected_seats) { $errors[] = 'Please choose at least one seat.'; }
    if (array_intersect($selected_seats, $booked_seats)) { $errors[] = 'One or more selected seats were just booked. Please choose again.'; }

    if (!$errors) {
        $connection = db();
        try {
            $connection->beginTransaction();
            $code = 'TK-' . strtoupper(bin2hex(random_bytes(4)));
            $booking = $connection->prepare('INSERT INTO ticket_bookings (booking_code, user_id, movie_id, watch_date, total_amount, expires_at) VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ' . BOOKING_HOLD_MINUTES . ' MINUTE))');
            $booking->execute([$code, $_SESSION['user_id'], $movie_id, $watch_date, count($selected_seats) * (float) $movie['price']]);
            $booking_id = (int) $connection->lastInsertId();
            $seat_insert = $connection->prepare('INSERT INTO booking_seats (booking_id, seat_label) VALUES (?, ?)');
            foreach ($selected_seats as $seat) { $seat_insert->execute([$booking_id, $seat]); }
            $connection->commit();
            header('Location: payment.php?code=' . urlencode($code));
            exit;
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) { $connection->rollBack(); }
            $errors[] = 'We could not reserve those seats. Please try again.';
        }
    }
}

$page_title = 'Choose seats';
require __DIR__ . '/header.php';
?>
<?php $booking_step = 2; require __DIR__ . '/booking_progress.php'; ?>
<section class="seat-heading"><a class="button button-quiet" href="movies.php">&larr; Back to movies</a><p class="eyebrow">Choose your seats</p><h1><?= e($movie['title']) ?></h1><p><?= e(date('g:i A', strtotime($movie['show_time']))) ?> &middot; <?= e(peso((float) $movie['price'])) ?> per seat</p></section>
<section class="screening-summary" aria-label="Selected screening schedule"><div><span>Cinema</span><strong>Cinema <?= (int) $movie['cinema_number'] ?></strong></div><div><span>Watch date</span><strong><?= e(date('l, F j, Y', strtotime($watch_date))) ?></strong></div><div><span>Available dates</span><strong><?= e(date('M j', strtotime($movie['available_from']))) ?> - <?= e(date('M j, Y', strtotime($movie['available_until']))) ?></strong></div><div><span>Runtime</span><strong><?= (int) $movie['duration_minutes'] ?> minutes</strong></div></section>
<form class="screening-date-picker" method="get" action="seats.php">
    <input type="hidden" name="movie_id" value="<?= (int) $movie_id ?>">
    <label for="watch_date">Choose your viewing date</label>
    <div><input id="watch_date" name="watch_date" type="date" min="<?= e(max($movie['available_from'], date('Y-m-d'))) ?>" max="<?= e($movie['available_until']) ?>" value="<?= e($watch_date) ?>" onchange="this.form.submit()" required><button class="button button-quiet" type="submit">Update seats</button></div>
</form>
<form class="seat-layout" method="post" action="seats.php?movie_id=<?= (int) $movie_id ?>">
    <input type="hidden" name="movie_id" value="<?= (int) $movie_id ?>">
    <input type="hidden" name="watch_date" value="<?= e($watch_date) ?>">
    <?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <div class="seat-controls">
        <span class="seat-legend-item"><span class="seat-legend-swatch seat-legend-available"></span>Available</span>
        <span class="seat-legend-item"><span class="seat-legend-swatch seat-legend-selected"></span>Selected</span>
        <span class="seat-legend-item"><span class="seat-legend-swatch seat-legend-own"></span>Your reservation</span>
        <span class="seat-legend-item"><span class="seat-legend-swatch"></span>Reserved</span>
    </div>
    <div class="screen">CINEMA SCREEN</div>
    <div class="seat-map" role="group" aria-label="Choose seats. Reserved seats are unavailable.">
    <?php foreach ($seat_layout as $section => $section_rows): ?>
        <section class="seat-section <?= $section === 'Balcony' ? 'seat-section-balcony' : '' ?>" aria-label="<?= e($section) ?> seats"><h2 class="seat-section-title"><?= e($section) ?> Seats</h2><div class="seat-grid">
            <?php foreach ($section_rows as $row => $numbers): ?>
                <div class="seat-row"><span class="row-label"><?= e($row) ?></span><?php foreach ($numbers as $number): $seat = $row . $number; $is_booked = in_array($seat, $booked_seats, true); $is_own_booked = in_array($seat, $own_booked_seats, true); ?><label class="seat <?= $is_booked ? ($is_own_booked ? 'seat-booked seat-booked-own' : 'seat-booked seat-booked-other') : '' ?>" title="<?= $is_booked ? ($is_own_booked ? 'Your reserved seat ' : 'Reserved seat ') . $seat : 'Available seat ' . $seat ?>"><input type="checkbox" name="seats[]" value="<?= e($seat) ?>" aria-label="Seat <?= e($seat) ?><?= $is_booked ? ($is_own_booked ? ', your reservation' : ', reserved') : ', available' ?>" <?= $is_booked ? 'disabled' : '' ?>><span><?= e($seat) ?></span></label><?php endforeach; ?></div>
            <?php endforeach; ?>
        </div></section>
    <?php endforeach; ?>
    </div>
    <section class="seat-selection-summary" aria-live="polite"><div><span>Tickets selected</span><strong id="selected-count">0</strong><span id="selected-labels">Choose your seats</span></div><div class="selection-total"><span>Total</span><strong id="selected-total"><?= e(peso(0)) ?></strong><span><?= e(peso((float) $movie['price'])) ?> per seat</span></div></section>
    <button class="button button-primary button-full" type="submit">Confirm seats &rarr;</button>
    <a class="button button-quiet button-full" href="movies.php">Cancel and return to movies</a>
</form>
<script>
    const ticketPrice = <?= json_encode((float) $movie['price']) ?>;
    const seatInputs = document.querySelectorAll('input[name="seats[]"]:not(:disabled)');
    const selectedCount = document.getElementById('selected-count');
    const selectedLabels = document.getElementById('selected-labels');
    const selectedTotal = document.getElementById('selected-total');
    const pesoFormatter = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });

    function updateSelectionSummary() {
        const selected = Array.from(seatInputs).filter((input) => input.checked).map((input) => input.value);
        selectedCount.textContent = selected.length;
        selectedLabels.textContent = selected.length ? selected.join(', ') : 'Choose your seats';
        selectedTotal.textContent = pesoFormatter.format(selected.length * ticketPrice);
        document.querySelector('.seat-layout button[type="submit"]').disabled = selected.length === 0;
    }

    seatInputs.forEach((input) => input.addEventListener('change', updateSelectionSummary));
    updateSelectionSummary();

</script>
<?php require __DIR__ . '/footer.php'; ?>