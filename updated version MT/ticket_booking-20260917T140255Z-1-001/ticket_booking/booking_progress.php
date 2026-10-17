<?php
$booking_steps = [
    1 => 'Movie',
    2 => 'Seats',
    3 => 'Payment',
    4 => 'Receipt',
];
?>
<nav class="booking-progress" aria-label="Booking progress">
    <ol>
        <?php foreach ($booking_steps as $step_number => $step_label): ?>
            <li class="<?= $step_number < $booking_step ? 'is-complete' : ($step_number === $booking_step ? 'is-current' : '') ?>" <?= $step_number === $booking_step ? 'aria-current="step"' : '' ?>>
                <span class="booking-progress-number"><?= $step_number < $booking_step ? '&#10003;' : $step_number ?></span>
                <span><?= e($step_label) ?></span>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
