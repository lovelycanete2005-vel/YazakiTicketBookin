USE ticket_booking;

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
