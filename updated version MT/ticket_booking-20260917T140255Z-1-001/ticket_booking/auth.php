<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function peso(float $amount): string
{
    return '₱' . number_format($amount, 2);
}

function screening_slot(string $show_time): string
{
    $hour = (int) date('G', strtotime($show_time));
    $slots = [
        8 => '8:00 AM - 11:00 AM',
        12 => '12:00 PM - 3:00 PM',
        16 => '4:00 PM - 7:00 PM',
        20 => '8:00 PM - 12:00 AM',
    ];

    if ($hour >= 19) {
        return $slots[20];
    }

    foreach ($slots as $start_hour => $label) {
        if ($hour >= $start_hour && $hour < $start_hour + 4) {
            return $label;
        }
    }

    return date('g:i A', strtotime($show_time));
}

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }

    db()->exec("UPDATE ticket_bookings SET status = 'expired' WHERE status = 'confirmed' AND payment_status = 'pending' AND expires_at <= NOW()");

    $statement = db()->prepare('SELECT account_status FROM users WHERE id = ?');
    $statement->execute([$_SESSION['user_id']]);
    if ($statement->fetchColumn() !== 'active') {
        $_SESSION = [];
        session_destroy();
        header('Location: login.php');
        exit;
    }
}

function is_admin(): bool
{
    return is_logged_in() && (($_SESSION['user_role'] ?? '') === 'admin');
}

function require_admin(): void
{
    require_login();

    if (!is_admin()) {
        header('Location: dashboard.php');
        exit;
    }
}

function current_user(): ?array
{
    if (!is_logged_in()) {
        return null;
    }

    $statement = db()->prepare('SELECT id, name, email, role FROM users WHERE id = ?');
    $statement->execute([$_SESSION['user_id']]);
    $user = $statement->fetch();

    return $user ?: null;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
