<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$movie_id = filter_input(INPUT_GET, 'movie_id', FILTER_VALIDATE_INT);
$statement = db()->prepare('SELECT title, show_date, show_time, poster_color FROM movies WHERE id = ?');
$statement->execute([$movie_id]);
$movie = $statement->fetch();

if (!$movie) {
    http_response_code(404);
    exit;
}

$title = (string) $movie['title'];
$is_spider_man = $title === 'Spider-Man: Brand New Day';
$accent = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $movie['poster_color']) ? $movie['poster_color'] : '#1769aa';
$title_lines = array_slice(explode("\n", wordwrap($title, $is_spider_man ? 14 : 16, "\n", true)), 0, 3);
$title_lines = array_map(static fn (string $line): string => htmlspecialchars(strtoupper($line), ENT_QUOTES, 'UTF-8'), $title_lines);
$date = htmlspecialchars(date('D, M j', strtotime($movie['show_date'])), ENT_QUOTES, 'UTF-8');
$time = htmlspecialchars(date('g:i A', strtotime($movie['show_time'])), ENT_QUOTES, 'UTF-8');

header('Content-Type: image/svg+xml; charset=UTF-8');
header('Cache-Control: public, max-age=3600');
?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 500 720" role="img" aria-label="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> movie cover">
    <defs>
        <linearGradient id="background" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="<?= $is_spider_man ? '#b51f2a' : $accent ?>" />
            <stop offset="1" stop-color="<?= $is_spider_man ? '#071d4c' : '#101d46' ?>" />
        </linearGradient>
        <pattern id="lines" width="52" height="52" patternUnits="userSpaceOnUse" patternTransform="rotate(20)">
            <path d="M0 26h52M26 0v52" stroke="#fff" stroke-opacity=".12" />
        </pattern>
    </defs>
    <rect width="500" height="720" fill="url(#background)" />
    <rect width="500" height="720" fill="url(#lines)" />
    <?php if ($is_spider_man): ?>
        <g fill="none" stroke="#fff" stroke-opacity=".18" stroke-width="3">
            <path d="M250 60V650M70 180L430 540M70 540L430 180M90 360H410" />
            <circle cx="250" cy="360" r="90" /><circle cx="250" cy="360" r="170" /><circle cx="250" cy="360" r="245" />
        </g>
        <circle cx="250" cy="355" r="92" fill="#101d46" fill-opacity=".4" />
        <text x="250" y="92" fill="#fff" font-family="Arial, sans-serif" font-size="18" text-anchor="middle" letter-spacing="4">MARVEL STUDIOS</text>
        <text x="250" y="420" fill="#fff" font-family="Arial, sans-serif" font-size="57" font-weight="900" text-anchor="middle">SPIDER-MAN</text>
        <text x="250" y="476" fill="#f7d35a" font-family="Arial, sans-serif" font-size="25" font-weight="700" text-anchor="middle" letter-spacing="3">BRAND NEW DAY</text>
    <?php else: ?>
        <text x="38" y="64" fill="#fff" fill-opacity=".8" font-family="Arial, sans-serif" font-size="14" letter-spacing="3">YAZAKI TICKET BOOKIN'</text>
        <text x="38" y="575" fill="#fff" font-family="Arial, sans-serif" font-size="42" font-weight="700">
            <?php foreach ($title_lines as $index => $line): ?><tspan x="38" dy="<?= $index === 0 ? 0 : 48 ?>"><?= $line ?></tspan><?php endforeach; ?>
        </text>
    <?php endif; ?>
    <text x="38" y="682" fill="#fff" fill-opacity=".82" font-family="Arial, sans-serif" font-size="14" letter-spacing="1"><?= $date ?>  |  <?= $time ?></text>
</svg>