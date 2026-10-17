USE ticket_booking;

ALTER TABLE users
    MODIFY username VARCHAR(50) NULL,
    ADD COLUMN name VARCHAR(120) NULL AFTER id,
    ADD COLUMN email VARCHAR(190) NULL AFTER name;

UPDATE users
SET name = username
WHERE name IS NULL;

ALTER TABLE users
    MODIFY name VARCHAR(120) NOT NULL,
    ADD UNIQUE KEY users_email_unique (email);