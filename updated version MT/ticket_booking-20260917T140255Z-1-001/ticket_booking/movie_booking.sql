CREATE DATABASE IF NOT EXISTS ticket_booking CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ticket_booking;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    username VARCHAR(50) NULL UNIQUE,
    role ENUM('customer', 'admin') NOT NULL DEFAULT 'customer',
    account_status ENUM('active', 'removed') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS movies (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(180) NOT NULL,
    description TEXT NOT NULL,
    duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 120,
    show_date DATE NOT NULL,
    show_time TIME NOT NULL,
    price DECIMAL(10, 2) NOT NULL DEFAULT 12.00,
    poster_color VARCHAR(20) NOT NULL DEFAULT '#1e594d',
        poster_url VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ticket_bookings (
    id INT NOT NULL AUTO_INCREMENT,
    booking_code VARCHAR(30) NOT NULL UNIQUE,
    user_id INT UNSIGNED NOT NULL,
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

UPDATE users SET role = 'admin' WHERE username = 'admin';
