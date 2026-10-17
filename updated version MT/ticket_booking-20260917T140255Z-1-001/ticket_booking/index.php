<?php
require_once __DIR__ . '/auth.php';

$showing_now = db()->query('SELECT id, title, description, duration_minutes, show_date, show_time, price, cinema_number, poster_color, poster_url FROM movies WHERE show_date = CURDATE() ORDER BY show_time LIMIT 8')->fetchAll();
if (!$showing_now) {
    $showing_now = [[
        'id' => 0,
        'title' => 'Spider-Man',
        'description' => 'A thrilling superhero adventure.',
        'duration_minutes' => 140,
        'show_date' => date('Y-m-d'),
        'show_time' => '16:00:00',
        'price' => 250,
        'cinema_number' => 1,
        'poster_color' => '#b61111',
        'poster_url' => 'https://th.bing.com/th/id/OIP.eoKi0Q8Q05CvT8pmIL3-GwHaFl?w=208&h=157&c=7&r=0&o=7&dpr=1.3&pid=1.7&rm=3'
    ]];
}

$coming_soon = db()->query('SELECT id, title, description, duration_minutes, show_date, show_time, price, cinema_number, poster_color, poster_url FROM movies WHERE show_date > CURDATE() ORDER BY show_date, show_time LIMIT 8')->fetchAll();
if (!$coming_soon) {
    $coming_soon = [
        [
            'id' => 0,
            'title' => 'Spider-Man: Beyond',
            'description' => 'The next web-slinging adventure.',
            'duration_minutes' => 138,
            'show_date' => date('Y-m-d', strtotime('+3 days')),
            'show_time' => '16:00:00',
            'price' => 280,
            'cinema_number' => 1,
            'poster_color' => '#a61818',
            'poster_url' => 'https://th.bing.com/th/id/OIP.eoKi0Q8Q05CvT8pmIL3-GwHaFl?w=208&h=157&c=7&r=0&o=7&dpr=1.3&pid=1.7&rm=3'
        ],
        [
            'id' => 0,
            'title' => 'Midnight Run',
            'description' => 'A suspense-filled chase through the city.',
            'duration_minutes' => 128,
            'show_date' => date('Y-m-d', strtotime('+6 days')),
            'show_time' => '20:00:00',
            'price' => 260,
            'cinema_number' => 2,
            'poster_color' => '#5d1010',
            'poster_url' => 'https://images.unsplash.com/photo-1517604931442-7e0c8ed2963c?auto=format&fit=crop&w=800&q=80'
        ],
        [
            'id' => 0,
            'title' => 'Moonlight Echo',
            'description' => 'A mystery unfolds beneath the midnight sky.',
            'duration_minutes' => 135,
            'show_date' => date('Y-m-d', strtotime('+9 days')),
            'show_time' => '20:00:00',
            'price' => 270,
            'cinema_number' => 3,
            'poster_color' => '#7c1111',
            'poster_url' => 'https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?auto=format&fit=crop&w=800&q=80'
        ]
    ];
}
$featured_movies = db()->query('SELECT id, title, description, duration_minutes, show_date, show_time, price, cinema_number, poster_color, poster_url FROM movies ORDER BY show_date, show_time, id')->fetchAll();
if (!$featured_movies) {
    $featured_movies = array_merge($showing_now, $coming_soon);
}

$hero_slides = [];
foreach ($featured_movies as $movie) {
    $hero_slides[] = [
        'id' => (int) ($movie['id'] ?? 0),
        'title' => (string) ($movie['title'] ?? 'Featured movie'),
        'rating' => 'PG-13',
        'cast' => ['Tom Holland', 'Zendaya', 'Jacob Batalon'],
        'genres' => ['Action', 'Adventure', 'Sci-Fi'],
        'synopsis' => (string) ($movie['description'] ?? 'A cinematic adventure that brings every scene to life on the big screen.'),
        'show_date' => (string) ($movie['show_date'] ?? date('Y-m-d')),
        'duration_minutes' => (int) ($movie['duration_minutes'] ?? 120),
        'backdrop_url' => !empty($movie['poster_url']) ? $movie['poster_url'] : 'https://images.unsplash.com/photo-1517604931442-7e0c8ed2963c?auto=format&fit=crop&w=1600&q=80',
    ];
}
if (!$hero_slides) {
    $hero_slides = [[
        'id' => 0,
        'title' => 'Spider-Man: Brand New Day',
        'rating' => 'PG-13',
        'cast' => ['Tom Holland', 'Zendaya', 'Jacob Batalon'],
        'genres' => ['Action', 'Fantasy'],
        'synopsis' => 'Spider-Man faces a new chapter filled with danger, responsibility, and impossible choices.',
        'show_date' => date('Y-m-d'),
        'duration_minutes' => 135,
        'backdrop_url' => 'https://images.unsplash.com/photo-1517604931442-7e0c8ed2963c?auto=format&fit=crop&w=1600&q=80',
    ]];
}

require __DIR__ . '/header.php';
?>
<section class="landing-display hero-carousel-shell" aria-label="Featured movie carousel">
    <div class="hero-carousel" data-auto-advance="5000">
        <?php foreach ($hero_slides as $index => $slide): ?>
            <article class="hero-slide <?= $index === 0 ? 'is-active' : '' ?>" data-index="<?= (int) $index ?>" aria-hidden="<?= $index === 0 ? 'false' : 'true' ?>">
                <div class="hero-backdrop" style="background-image: url('<?= e($slide['backdrop_url']) ?>');"></div>
                <div class="hero-overlay"></div>
                <div class="hero-copy">
                    <div class="hero-kicker">
                        <span class="hero-badge hero-rating"><?= e($slide['rating']) ?></span>
                        <span><?= e(date('M j', strtotime($slide['show_date']))) ?></span>
                        <span><?= (int) $slide['duration_minutes'] ?> min</span>
                    </div>
                    <h1><?= e($slide['title']) ?></h1>
                    <div class="hero-details">
                        <span><strong>Cast:</strong> <?= e(implode(', ', $slide['cast'])) ?></span>
                    </div>
                    <div class="hero-details">
                        <span><strong>Genres:</strong> <?= e(implode(' • ', $slide['genres'])) ?></span>
                    </div>
                    <p class="hero-synopsis"><?= e($slide['synopsis']) ?></p>
                    <div class="landing-actions hero-actions">
                        <a class="button button-primary" href="movies.php?view=now">Watch <span aria-hidden="true">&rarr;</span></a>
                    </div>
                    <div class="hero-pagination" aria-label="Slide navigation">
                        <?php foreach ($hero_slides as $dot_index => $dot_slide): ?>
                            <button class="hero-dot <?= $dot_index === 0 ? 'is-active' : '' ?>" type="button" data-slide-index="<?= (int) $dot_index ?>" aria-label="Show slide <?= (int) ($dot_index + 1) ?>" aria-pressed="<?= $dot_index === 0 ? 'true' : 'false' ?>"></button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const carousel = document.querySelector('.hero-carousel');
        if (!carousel) {
            return;
        }

        const slides = Array.from(carousel.querySelectorAll('.hero-slide'));
        const dots = Array.from(carousel.querySelectorAll('.hero-dot'));
        const autoAdvanceMs = Number(carousel.dataset.autoAdvance || 5000);
        let activeIndex = 0;
        let timer = null;

        function setActiveSlide(nextIndex) {
            activeIndex = (nextIndex + slides.length) % slides.length;

            slides.forEach((slide, index) => {
                const isActive = index === activeIndex;
                slide.classList.toggle('is-active', isActive);
                slide.setAttribute('aria-hidden', String(!isActive));
            });

            dots.forEach((dot) => {
                const isActive = Number(dot.dataset.slideIndex) === activeIndex;
                dot.classList.toggle('is-active', isActive);
                dot.setAttribute('aria-pressed', String(isActive));
            });
        }

        function restartAutoAdvance() {
            if (timer) {
                clearInterval(timer);
            }
            timer = setInterval(function () {
                setActiveSlide(activeIndex + 1);
            }, autoAdvanceMs);
        }

        dots.forEach(function (dot) {
            dot.addEventListener('click', function () {
                setActiveSlide(Number(dot.dataset.slideIndex));
                restartAutoAdvance();
            });
        });

        setActiveSlide(0);
        restartAutoAdvance();
    });
</script>
<?php require __DIR__ . '/footer.php'; ?>