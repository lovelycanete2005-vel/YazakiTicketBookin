<?php
require_once __DIR__ . '/auth.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$email = '';
$preview_movies = db()->query('SELECT title, description, show_date, show_time, duration_minutes, cinema_number, poster_url FROM movies WHERE show_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY) ORDER BY show_date, show_time LIMIT 8')->fetchAll();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $statement = db()->prepare("SELECT id, name, email, username, password, role, account_status FROM users WHERE account_status = 'active' AND (email = ? OR username = ?) LIMIT 1");
    $statement->execute([$email, $email]);
    $user = $statement->fetch();

    $password_matches = $user && password_verify($password, $user['password']);
    $legacy_password_matches = $user && hash_equals((string) $user['password'], $password);

    if ($user && ($password_matches || $legacy_password_matches)) {
        if ($legacy_password_matches) {
            $upgrade = db()->prepare('UPDATE users SET password = ? WHERE id = ?');
            $upgrade->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_role'] = $user['role'] ?? 'customer';
        header('Location: dashboard.php');
        exit;
    }

    $error = 'The email or password is incorrect.';
}

$page_title = 'Log in';
require __DIR__ . '/header.php';
?>
<section class="auth-layout auth-layout-login">
    <div class="auth-intro">
        <div class="auth-intro-copy"><p class="eyebrow">Welcome back</p><h1>Your next journey starts here.</h1><p>Log in to see your profile and bookings.</p></div>
        <?php if ($preview_movies): ?>
            <section class="login-movie-preview" aria-label="Preview screenings" data-auto-advance="5500">
                <div class="login-preview-slides" aria-live="polite">
                    <?php foreach ($preview_movies as $index => $preview_movie): ?>
                        <article class="login-preview-slide <?= $index === 0 ? 'is-active' : '' ?>" aria-hidden="<?= $index === 0 ? 'false' : 'true' ?>" aria-label="<?= (int) ($index + 1) ?> of <?= count($preview_movies) ?>">
                            <div class="login-movie-art">
                                <?php if (!empty($preview_movie['poster_url'])): ?>
                                    <img src="<?= e($preview_movie['poster_url']) ?>" alt="Poster for <?= e($preview_movie['title']) ?>">
                                <?php else: ?>
                                    <span aria-hidden="true"><?= e(strtoupper(substr($preview_movie['title'], 0, 1))) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="login-movie-details">
                                <p class="login-preview-label">Preview screening</p>
                                <h2><?= e($preview_movie['title']) ?></h2>
                                <p class="login-preview-meta"><?= e(date('M j', strtotime($preview_movie['show_date']))) ?> &middot; <?= e(date('g:i A', strtotime($preview_movie['show_time']))) ?> &middot; Cinema <?= (int) $preview_movie['cinema_number'] ?> &middot; <?= (int) $preview_movie['duration_minutes'] ?> min</p>
                                <p class="login-preview-description"><?= e($preview_movie['description']) ?></p>
                                <a class="login-preview-link" href="movies.php?view=now">Browse screenings <span aria-hidden="true">&rarr;</span></a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <?php if (count($preview_movies) > 1): ?>
                    <div class="login-preview-pagination" aria-label="Choose a movie preview">
                        <?php foreach ($preview_movies as $index => $preview_movie): ?>
                            <button class="login-preview-dot <?= $index === 0 ? 'is-active' : '' ?>" type="button" data-slide-index="<?= (int) $index ?>" aria-label="Show preview for <?= e($preview_movie['title']) ?>" aria-pressed="<?= $index === 0 ? 'true' : 'false' ?>"></button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
    <form class="form-card" method="post" action="login.php">
        <h2>Log in</h2>
        <p class="form-note">New to Yazaki Ticket Bookin'? <a href="register.php">Create an account</a></p>
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
        <label for="email">Email address or username</label>
        <input id="email" name="email" type="text" value="<?= e($email) ?>" autocomplete="username" required>
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <button class="button button-primary button-full" type="submit">Log in</button>
    </form>
</section>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const preview = document.querySelector('.login-movie-preview');
        if (!preview) {
            return;
        }

        const slides = Array.from(preview.querySelectorAll('.login-preview-slide'));
        const dots = Array.from(preview.querySelectorAll('.login-preview-dot'));
        if (slides.length < 2) {
            return;
        }

        const autoAdvanceMs = Number(preview.dataset.autoAdvance || 5500);
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        let activeIndex = 0;
        let timer = null;

        function setActiveSlide(nextIndex) {
            activeIndex = (nextIndex + slides.length) % slides.length;
            slides.forEach((slide, index) => {
                const isActive = index === activeIndex;
                slide.classList.toggle('is-active', isActive);
                slide.setAttribute('aria-hidden', String(!isActive));
            });
            dots.forEach((dot, index) => {
                const isActive = index === activeIndex;
                dot.classList.toggle('is-active', isActive);
                dot.setAttribute('aria-pressed', String(isActive));
            });
        }

        function pauseAutoAdvance() {
            if (timer) {
                clearInterval(timer);
                timer = null;
            }
        }

        function resumeAutoAdvance() {
            pauseAutoAdvance();
            if (!reducedMotion && !document.hidden) {
                timer = setInterval(() => setActiveSlide(activeIndex + 1), autoAdvanceMs);
            }
        }

        dots.forEach((dot) => {
            dot.addEventListener('click', () => {
                setActiveSlide(Number(dot.dataset.slideIndex));
                resumeAutoAdvance();
            });
        });

        preview.addEventListener('mouseenter', pauseAutoAdvance);
        preview.addEventListener('mouseleave', resumeAutoAdvance);
        preview.addEventListener('focusin', pauseAutoAdvance);
        preview.addEventListener('focusout', (event) => {
            if (!preview.contains(event.relatedTarget)) {
                resumeAutoAdvance();
            }
        });
        document.addEventListener('visibilitychange', resumeAutoAdvance);

        setActiveSlide(0);
        resumeAutoAdvance();
    });
</script>
<?php require __DIR__ . '/footer.php'; ?>