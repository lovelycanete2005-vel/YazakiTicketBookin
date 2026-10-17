<?php
require_once __DIR__ . '/auth.php';
require_login();

$code = trim((string) ($_GET['code'] ?? ''));
$statement = db()->prepare('SELECT tb.booking_code, tb.watch_date, tb.total_amount, tb.status, tb.payment_status, tb.bank_reference, tb.created_at, m.title, m.show_time, u.name, u.email FROM ticket_bookings tb JOIN movies m ON m.id = tb.movie_id JOIN users u ON u.id = tb.user_id WHERE tb.booking_code = ? AND tb.user_id = ?');
$statement->execute([$code, $_SESSION['user_id']]);
$booking = $statement->fetch();
if (!$booking) { header('Location: dashboard.php'); exit; }
$seats = db()->prepare('SELECT seat_label FROM booking_seats bs JOIN ticket_bookings tb ON tb.id = bs.booking_id WHERE tb.booking_code = ? ORDER BY seat_label');
$seats->execute([$code]);
$seat_labels = $seats->fetchAll(PDO::FETCH_COLUMN);
$qr_data = "Yazaki Ticket Bookin' booking " . $booking['booking_code'] . ' | ' . $booking['title'] . ' | Seats: ' . implode(', ', $seat_labels);
$receipt_status = $booking['status'] === 'confirmed' ? $booking['payment_status'] : $booking['status'];
$receipt_title = $booking['status'] === 'expired' ? 'Your reservation expired.' : ($booking['status'] === 'cancelled' ? 'This booking was cancelled.' : 'Your seats are saved.');
$receipt_message = $booking['status'] === 'expired' ? 'This unpaid reservation expired and its seats have been released. Please book again.' : ($booking['status'] === 'cancelled' ? 'This booking was cancelled.' : ($booking['payment_status'] === 'verified' ? 'Payment verified. Show this receipt at the cinema.' : ($booking['payment_status'] === 'submitted' ? 'Your payment reference was submitted and is awaiting verification.' : 'Complete the bank transfer to finish payment.')));
$page_title = 'Booking receipt';
require __DIR__ . '/header.php';
?>
<section class="receipt-wrap">
<?php $booking_step = 4; require __DIR__ . '/booking_progress.php'; ?>
<div class="receipt-success">
<span class="success-mark <?= in_array($booking['status'], ['expired', 'cancelled'], true) ? 'receipt-mark-inactive' : '' ?>" aria-hidden="true"><?= in_array($booking['status'], ['expired', 'cancelled'], true) ? '!' : '&#10003;' ?></span>
<p class="eyebrow">Booking status</p>
<h1><?= e($receipt_title) ?></h1>
<p><?= e($receipt_message) ?></p>
<span class="booking-status status-<?= e($receipt_status) ?>"><?= e(ucfirst($receipt_status)) ?></span>
</div>
<article class="receipt-card">
<div class="receipt-main">
<p class="receipt-label">Booking code</p>
<h2 class="code-value">
<?= e($booking['booking_code']) ?>
</h2>
<div class="receipt-details">
<div>
<span>Movie</span>
<strong>
<?= e($booking['title']) ?>
</strong>
</div>
<div>
<span>Screening</span>
<strong>
<?= e(date('D, M j, Y', strtotime($booking['watch_date']))) ?> at <?= e(date('g:i A', strtotime($booking['show_time']))) ?>
</strong>
</div>
<div>
<span>Seats</span>
<strong>
<?= e(implode(', ', $seat_labels)) ?>
</strong>
</div>
<div>
<span>Total</span>
<strong>
<?= e(peso((float) $booking['total_amount'])) ?>
</strong>
</div>
<div>
<span>Payment</span>
<strong>
<?= e(ucfirst($booking['payment_status'])) ?>
<?= $booking['bank_reference'] ? ' &middot; ' : '' ?><span class="code-value"><?= $booking['bank_reference'] ? e($booking['bank_reference']) : '' ?></span>
</strong>
</div>
</div>
</div>
<div class="receipt-qr">
<img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=<?= rawurlencode($qr_data) ?>" alt="QR code for booking <?= e($booking['booking_code']) ?>">
<small>Scan to verify</small>
</div>
</article>
<?php if ($booking['status'] === 'confirmed' && $booking['payment_status'] !== 'verified'): ?>
<a class="button button-primary" href="payment.php?code=<?= urlencode($booking['booking_code']) ?>">Pay by bank transfer</a>
<?php endif; ?> <a class="button button-quiet" href="dashboard.php">Go to dashboard</a>
</section>
<?php require __DIR__ . '/footer.php'; ?>
