<?php
require_once __DIR__ . '/auth.php';
require_login();

$code = trim((string) ($_GET['code'] ?? $_POST['code'] ?? ''));
$statement = db()->prepare('SELECT tb.booking_code, tb.total_amount, tb.status, tb.payment_status, tb.bank_reference, tb.expires_at, TIMESTAMPDIFF(SECOND, NOW(), tb.expires_at) AS hold_seconds_remaining, m.title FROM ticket_bookings tb JOIN movies m ON m.id = tb.movie_id WHERE tb.booking_code = ? AND tb.user_id = ?');
$statement->execute([$code, $_SESSION['user_id']]);
$booking = $statement->fetch();
if (!$booking) { header('Location: dashboard.php'); exit; }

$error = '';
$can_submit_payment = $booking['status'] === 'confirmed' && $booking['payment_status'] !== 'verified';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_submit_payment) {
    $reference = trim((string) ($_POST['bank_reference'] ?? ''));
    if ($reference === '') {
        $error = 'Enter the transfer reference from your bank receipt.';
    } else {
        $update = db()->prepare("UPDATE ticket_bookings SET payment_status = 'submitted', bank_reference = ? WHERE booking_code = ? AND user_id = ? AND status = 'confirmed' AND (payment_status <> 'pending' OR expires_at > NOW())");
        $update->execute([$reference, $code, $_SESSION['user_id']]);
        if ($update->rowCount()) {
            header('Location: receipt.php?code=' . urlencode($code));
            exit;
        }
        $current_state = db()->prepare('SELECT status, payment_status FROM ticket_bookings WHERE booking_code = ? AND user_id = ?');
        $current_state->execute([$code, $_SESSION['user_id']]);
        $current_booking = $current_state->fetch();
        if ($current_booking && $current_booking['status'] === 'confirmed' && $current_booking['payment_status'] === 'submitted') {
            header('Location: receipt.php?code=' . urlencode($code));
            exit;
        }
        $error = 'This reservation is no longer active. Please choose your seats again.';
    }
}

$page_title = 'Bank transfer payment';
require __DIR__ . '/header.php';
?>
<?php $booking_step = 3; require __DIR__ . '/booking_progress.php'; ?>
<section class="payment-layout">
<div class="payment-intro">
<p class="eyebrow">Philippine payment</p>
<h1>Pay for your seats.</h1>
<p>Transfer the exact amount using your Philippine banking app or GCash. We never ask for your online-banking password, PIN, or card security code.</p>
</div>
<form class="form-card payment-card" method="post" action="payment.php">
<input type="hidden" name="code" value="<?= e($code) ?>">
<p class="receipt-label">Booking <span class="code-value"><?= e($booking['booking_code']) ?></span>
</p>
<h2>
<?= e($booking['title']) ?>
</h2>
<div class="bank-details">
<div>
<span>Bank or e-wallet</span>
<strong>
<?= e((string) constant('BANK_NAME')) ?>
</strong>
</div>
<div>
<span>Account name</span>
<strong>
<?= e((string) constant('BANK_ACCOUNT_NAME')) ?>
</strong>
</div>
<div>
<span>Account number / mobile number</span>
<strong class="account-number">
<?= e((string) constant('BANK_ACCOUNT_NUMBER')) ?>
</strong>
</div>
<div>
<span>Amount to transfer</span>
<strong>
<?= e(peso((float) $booking['total_amount'])) ?>
</strong>
</div>
</div>
<?php if ($booking['status'] === 'expired'): ?>
<div class="alert alert-error" role="status">This unpaid reservation has expired and its seats have been released. Please choose your seats again.</div>
<?php elseif ($booking['status'] !== 'confirmed'): ?>
<div class="alert alert-error" role="status">This booking is no longer active.</div>
<?php elseif ($booking['payment_status'] === 'pending'): ?>
<p class="form-note" role="status">Your seats are held for <?= (int) BOOKING_HOLD_MINUTES ?> minutes. Time remaining: <strong id="hold-countdown"><?= max(0, (int) $booking['hold_seconds_remaining']) ?> seconds</strong>. Submit your transfer reference before the hold expires.</p>
<?php elseif ($booking['payment_status'] === 'submitted'): ?>
<p class="form-note" role="status">Your payment reference has been submitted. Your seats will remain reserved while an admin verifies the payment.</p>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-error">
<?= e($error) ?>
</div>
<?php endif; ?>
<?php if ($can_submit_payment): ?>
<label for="bank_reference">Transfer reference</label>
<input class="code-input" id="bank_reference" name="bank_reference" placeholder="Example: GCash reference number" value="<?= e($booking['bank_reference'] ?? '') ?>" required>
<p class="form-note">After transferring, enter the reference shown by your bank or GCash. Your payment will be marked submitted for admin verification.</p>
<button class="button button-primary button-full" type="submit">Submit payment reference</button>
<?php endif; ?>
</form>
</section>
<?php if ($booking['status'] === 'confirmed' && $booking['payment_status'] === 'pending'): ?>
<script>
    let holdSecondsRemaining = <?= max(0, (int) $booking['hold_seconds_remaining']) ?>;
    const holdCountdown = document.getElementById('hold-countdown');
    const referenceInput = document.getElementById('bank_reference');
    const submitButton = document.querySelector('.payment-card button[type="submit"]');
    const updateHoldCountdown = () => {
        if (holdSecondsRemaining <= 0) {
            holdCountdown.textContent = 'expired';
            referenceInput.disabled = true;
            submitButton.disabled = true;
            return;
        }
        const minutes = Math.floor(holdSecondsRemaining / 60);
        const seconds = holdSecondsRemaining % 60;
        holdCountdown.textContent = `${minutes}:${String(seconds).padStart(2, '0')}`;
        holdSecondsRemaining -= 1;
        window.setTimeout(updateHoldCountdown, 1000);
    };
    updateHoldCountdown();
</script>
<?php endif; ?>
<?php require __DIR__ . '/footer.php'; ?>