# Yazaki Ticket Bookin'

A small PHP/MySQL ticket-booking starter for XAMPP.

## Run it in XAMPP

1. Start Apache and MySQL in the XAMPP Control Panel.
2. Open `http://localhost/phpmyadmin` and import `database.sql` for a new installation.
3. Visit `http://localhost/ticket_booking/`.

### Windows quick start

This project needs PHP with the `pdo_mysql` extension. If the `php` command on
your PATH reports an empty PDO driver list, use the included `run-local.bat`
launcher instead. It uses the XAMPP PHP installation and serves the project at
`http://127.0.0.1:8081/`.

From PowerShell, the equivalent command is:

```powershell
& 'C:\xampp\php\php.exe' -S 127.0.0.1:8081 -t "$PWD"
```

MySQL must be running before opening the page. The XAMPP default connection is
`root` with an empty password.

The default XAMPP database connection is in `config.php`:

- Host: `127.0.0.1`
- Database: `ticket_booking`
- User: `root`
- Password: empty

Change those constants if your MySQL installation uses a password or a different database name.

## Included flow

- `register.php` creates accounts with `password_hash()`.
- `login.php` verifies passwords and starts a secure session.
- `dashboard.php` is protected and requires login.
- `logout.php` clears the session and returns to the home page.
- `movies.php` publicly lists screenings; customers must log in to choose seats.
- `seats.php` reserves available cinema seats in a transaction.
- `receipt.php` displays the booking receipt and QR code.
- `admin.php` lets the admin account add movies and view recent bookings.

## Booking setup

For an existing legacy XAMPP database, import `upgrade_legacy_users.sql` if needed, then import `upgrade_booking_system.sql`. The upgrade script is safe to run again and preserves the older `bookings` table while creating the new movie-seat booking tables. It also adds the 15-minute unpaid-reservation hold expiry. For an existing installation already using the movie-seat booking tables, import `upgrade_booking_expiry.sql` once. The existing `admin` username is promoted to admin automatically. Do not import the upgrade script after `database.sql` unless you are upgrading an older schema.

Customer flow: `movies.php` -> `seats.php` -> `receipt.php` -> `dashboard.php`.

Unpaid seat reservations expire 15 minutes after they are created. Expiration is recorded the next time an authenticated page is opened; expired reservations no longer block seat selection.

Admin flow: log in with the `admin` account, then open `admin.php` from the navigation.

The receipt QR image uses the QR Server API, so the QR image requires an internet connection. The booking data itself remains in MySQL.

Prices are displayed in Philippine pesos. Update the Philippine bank or GCash details in `config.php` before using the payment page.
