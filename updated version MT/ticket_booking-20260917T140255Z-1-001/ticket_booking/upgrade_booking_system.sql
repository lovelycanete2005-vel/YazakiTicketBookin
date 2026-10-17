USE ticket_booking;

ALTER TABLE movies ADD COLUMN IF NOT EXISTS available_from DATE NULL AFTER show_date, ADD COLUMN IF NOT EXISTS available_until DATE NULL AFTER available_from;
ALTER TABLE movies ADD COLUMN IF NOT EXISTS release_status ENUM('now_showing', 'upcoming') NULL AFTER show_date;
UPDATE movies SET available_from = show_date, available_until = show_date WHERE available_from IS NULL OR available_until IS NULL;
UPDATE movies SET release_status = CASE WHEN show_date > DATE_ADD(CURDATE(), INTERVAL 21 DAY) THEN 'upcoming' ELSE 'now_showing' END WHERE release_status IS NULL;
ALTER TABLE movies MODIFY available_from DATE NOT NULL, MODIFY available_until DATE NOT NULL;
ALTER TABLE movies MODIFY release_status ENUM('now_showing', 'upcoming') NOT NULL DEFAULT 'now_showing';
ALTER TABLE ticket_bookings ADD COLUMN IF NOT EXISTS watch_date DATE NULL AFTER movie_id;
UPDATE ticket_bookings tb JOIN movies m ON m.id = tb.movie_id SET tb.watch_date = m.show_date WHERE tb.watch_date IS NULL;
ALTER TABLE ticket_bookings MODIFY watch_date DATE NOT NULL;
ALTER TABLE ticket_bookings ADD COLUMN IF NOT EXISTS seat_color VARCHAR(7) NULL AFTER watch_date;
UPDATE ticket_bookings SET seat_color = '#d82020' WHERE seat_color IS NULL OR seat_color = '';
ALTER TABLE ticket_bookings MODIFY seat_color VARCHAR(7) NOT NULL DEFAULT '#d82020';
ALTER TABLE ticket_bookings ADD COLUMN IF NOT EXISTS expires_at DATETIME NULL AFTER created_at;
UPDATE ticket_bookings SET expires_at = DATE_ADD(created_at, INTERVAL 15 MINUTE) WHERE expires_at IS NULL;
ALTER TABLE ticket_bookings MODIFY status ENUM('confirmed', 'cancelled', 'expired') NOT NULL DEFAULT 'confirmed';
SET @ticket_bookings_expiry_index_exists = (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'ticket_bookings' AND index_name = 'ticket_bookings_expiry_idx'
);
SET @ticket_bookings_expiry_index_sql = IF(
    @ticket_bookings_expiry_index_exists = 0,
    'CREATE INDEX ticket_bookings_expiry_idx ON ticket_bookings (status, payment_status, expires_at)',
    'SELECT 1'
);
PREPARE ticket_bookings_expiry_index_statement FROM @ticket_bookings_expiry_index_sql;
EXECUTE ticket_bookings_expiry_index_statement;
DEALLOCATE PREPARE ticket_bookings_expiry_index_statement;

UPDATE movies SET show_time = CASE
    WHEN show_time < '12:00:00' THEN '08:00:00'
    WHEN show_time < '16:00:00' THEN '12:00:00'
    WHEN show_time < '20:00:00' THEN '16:00:00'
    ELSE '20:00:00'
END;

ALTER TABLE users ADD COLUMN IF NOT EXISTS role ENUM('customer', 'admin') NOT NULL DEFAULT 'customer' AFTER username, ADD COLUMN IF NOT EXISTS account_status ENUM('active', 'removed') NOT NULL DEFAULT 'active' AFTER role;

CREATE TABLE IF NOT EXISTS movies (
    id INT NOT NULL AUTO_INCREMENT,
    title VARCHAR(180) NOT NULL,
    description TEXT NOT NULL,
    duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 120,
    show_date DATE NOT NULL,
    show_time TIME NOT NULL,
    price DECIMAL(10, 2) NOT NULL DEFAULT 12.00,
    cinema_number TINYINT UNSIGNED NOT NULL DEFAULT 1,
    poster_color VARCHAR(20) NOT NULL DEFAULT '#1e594d',
        poster_url VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ticket_bookings (
    id INT NOT NULL AUTO_INCREMENT,
    booking_code VARCHAR(30) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    movie_id INT UNSIGNED NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    status ENUM('confirmed', 'cancelled', 'expired') NOT NULL DEFAULT 'confirmed',
    payment_status ENUM('pending', 'submitted', 'verified') NOT NULL DEFAULT 'pending',
    bank_reference VARCHAR(100) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY ticket_bookings_expiry_idx (status, payment_status, expires_at),
    CONSTRAINT ticket_bookings_user_fk FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT ticket_bookings_movie_fk FOREIGN KEY (movie_id) REFERENCES movies(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS booking_seats (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    booking_id INT NOT NULL,
    seat_label VARCHAR(5) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY booking_seat_unique (booking_id, seat_label),
    CONSTRAINT booking_seats_booking_fk FOREIGN KEY (booking_id) REFERENCES ticket_bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO movies (title, description, duration_minutes, show_date, show_time, price, poster_color)
SELECT 'Spider-Man: No Way Home', 'Spider-Man faces the consequences of an unmasked identity when villains from other worlds arrive.', 148, CURDATE(), '19:30:00', 650.00, '#b51d1d'
WHERE NOT EXISTS (SELECT 1 FROM movies);

INSERT INTO movies (title, description, duration_minutes, show_date, show_time, price, poster_color)
SELECT 'Train to Busan', 'Passengers fight to survive a fast-moving zombie outbreak aboard a train to Busan.', 118, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '17:00:00', 550.00, '#1e594d'
WHERE (SELECT COUNT(*) FROM movies) = 1;

INSERT INTO movies (title, description, duration_minutes, show_date, release_status, available_from, available_until, show_time, price, cinema_number, poster_color)
SELECT requested.title, requested.description, requested.duration_minutes, requested.show_date, 'upcoming', requested.show_date, DATE_ADD(requested.show_date, INTERVAL 6 DAY), requested.show_time, requested.price, requested.cinema_number, requested.poster_color
FROM (
    SELECT 'Avengers: Endgame Encore' AS title, '[Action/Adventure] The Avengers make one final stand to restore what was lost.' AS description, 181 AS duration_minutes, DATE_ADD(CURDATE(), INTERVAL 1 DAY) AS show_date, '16:00:00' AS show_time, 700.00 AS price, 1 AS cinema_number, '#7b1e1e' AS poster_color
    UNION ALL SELECT 'Forgotten Island', '[Comedy/Family] A family holiday turns into an unexpected island adventure.', 105, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '12:00:00', 550.00, 2, '#d48732'
    UNION ALL SELECT 'Resident Evil', '[Horror/Action] Survivors battle a deadly outbreak inside a secret underground facility.', 100, DATE_ADD(CURDATE(), INTERVAL 5 DAY), '20:00:00', 600.00, 3, '#263238'
    UNION ALL SELECT 'Heart of the Beast', '[Thriller/Drama] A troubled man confronts the violence and secrets buried in his past.', 112, DATE_ADD(CURDATE(), INTERVAL 7 DAY), '16:00:00', 580.00, 1, '#6b2737'
    UNION ALL SELECT 'Uranus 2324', '[Drama/Romance] Two people separated by time and space search for one another again.', 130, DATE_ADD(CURDATE(), INTERVAL 9 DAY), '20:00:00', 650.00, 2, '#315c72'
    UNION ALL SELECT 'Spider-Man: Brand New Day', '[Action/Fantasy] Spider-Man faces a new chapter filled with danger, responsibility, and impossible choices.', 135, DATE_ADD(CURDATE(), INTERVAL 11 DAY), '16:00:00', 650.00, 3, '#b51d1d'
    UNION ALL SELECT 'Plump Blossom', '[Musical/Animation] A bright young performer discovers courage through music and friendship.', 98, DATE_ADD(CURDATE(), INTERVAL 13 DAY), '12:00:00', 520.00, 1, '#df6f91'
    UNION ALL SELECT 'Insidious: Out of the Further', '[Thriller/Horror] A family must enter a terrifying realm to bring someone home.', 110, DATE_ADD(CURDATE(), INTERVAL 15 DAY), '20:00:00', 620.00, 2, '#252b3d'
    UNION ALL SELECT 'Detective Conan: Fallen Angel of the Highway', '[Action/Animation] Conan races to solve a dangerous mystery before the next collision.', 112, DATE_ADD(CURDATE(), INTERVAL 17 DAY), '16:00:00', 580.00, 3, '#2d5573'
    UNION ALL SELECT 'The Odyssey', '[Adventure/Drama] A legendary journey home becomes a test of courage, loyalty, and endurance.', 140, DATE_ADD(CURDATE(), INTERVAL 19 DAY), '20:00:00', 700.00, 1, '#8b6f47'
    UNION ALL SELECT 'Toy Story 5', '[Animation/Family] Woody, Buzz, and the gang discover a new kind of playtime adventure.', 105, DATE_ADD(CURDATE(), INTERVAL 21 DAY), '12:00:00', 550.00, 2, '#2f72a8'
    UNION ALL SELECT 'Michael', '[Biopic/Music] A portrait of Michael Jackson and the music that shaped generations.', 135, DATE_ADD(CURDATE(), INTERVAL 23 DAY), '16:00:00', 650.00, 3, '#5b3a29'
    UNION ALL SELECT 'Project Hail Mary', '[Science Fiction/Adventure] A lone astronaut must solve an impossible problem to save Earth.', 145, DATE_ADD(CURDATE(), INTERVAL 25 DAY), '20:00:00', 700.00, 1, '#38556b'
    UNION ALL SELECT 'Dear You', '[Drama/Romance] A heartfelt story about love, distance, and the messages we leave behind.', 115, DATE_ADD(CURDATE(), INTERVAL 27 DAY), '16:00:00', 580.00, 2, '#a6536b'
    UNION ALL SELECT 'Resident Evil (2026)', '[Horror/Action] A new outbreak forces a fresh group of survivors into a fight for the future.', 118, DATE_ADD(CURDATE(), INTERVAL 29 DAY), '20:00:00', 620.00, 3, '#402c35'
) AS requested
WHERE NOT EXISTS (SELECT 1 FROM movies existing WHERE existing.title = requested.title);

UPDATE users SET role = 'admin' WHERE username = 'admin';