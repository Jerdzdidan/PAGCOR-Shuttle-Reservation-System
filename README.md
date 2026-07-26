<p align="center">
    <img src="public/images/pagcor-logo.png" alt="PAGCOR logo" width="120">
</p>

<h1 align="center">PAGCOR Shuttle Reservation System</h1>

<p align="center">
    An internal shuttle management and employee reservation portal built with Laravel, Inertia, React, and shadcn-style components.
</p>

## About the system

The PAGCOR Shuttle Reservation System manages recurring shuttle schedules, fleet resources, employees, seat reservations, and full-shuttle waitlists. Administrators use a standard email-and-password account, while employees enter a separate portal using their assigned signed QR code.

Seeded routes originate from **PAGCOR Headquarters** and serve selected cities in Metro Manila and nearby provinces.

## Features

### Administrator portal

- Dashboard and schedule management
- Administrator account management
- Employee CRUD with required unique email addresses
- XLSX, XLS, and CSV employee import through Maatwebsite Excel
- Employee priority-status management (`REGULAR` or `PRIORITY`)
- Signed employee QR-code viewing and regeneration
- Vehicle management using the plate number as the primary display identifier
- Driver, route, and recurring schedule management
- Vehicle capacity with an optional per-schedule capacity override

### Employee portal

- Independent employee session and QR-only authentication
- Camera scanning, QR image upload, and handheld-scanner input
- Responsive dashboard with upcoming reservations and live waitlist positions
- Date-based schedule browsing with route and direction filters
- Visual shuttle seat sheet with live seat availability
- Reservation cancellation and waitlist withdrawal before departure
- Automatic waitlist promotion with queued email notification
- Periodic Inertia polling for current availability and queue changes

## Reservation rules

- Reservations are tied to a recurring schedule and a specific travel date.
- Employees may book valid operating dates from today through 30 days ahead.
- Booking, cancellation, and waitlist changes close at the scheduled departure time in `Asia/Manila`.
- Effective capacity uses the schedule's capacity override when present; otherwise, it uses the assigned vehicle's capacity.
- An employee may have only one reservation or waitlist entry for the same schedule occurrence.
- Seats **1–8** are reserved for priority employees. Priority employees may also select general seats.
- Regular employees may join the waitlist when all general seats are occupied, even when protected priority seats remain vacant.
- Waitlists are ranked by priority status first, then queue time, then record ID for deterministic FCFS ordering.
- A released protected seat is assigned only to the earliest waiting priority employee.
- A released general seat is assigned to the earliest waiting priority employee, or the earliest regular employee when no priority employee is waiting.
- Promotion uses the exact released seat and is completed in a locked database transaction.

## Technology

- PHP 8.2 and Laravel 12
- Inertia.js 2 with React 19 and TypeScript
- Tailwind CSS 4 with shadcn-style Radix UI components
- SQLite, MySQL, or MariaDB
- Maatwebsite Excel for employee imports
- ZXing Browser for QR scanning
- Laravel database sessions, cache, and queues

## Requirements

- PHP 8.2 or newer
- Composer
- Node.js `^18.0.0`, `^20.0.0`, or `>=22.0.0`
- npm
- SQLite, MySQL, or MariaDB
- PHP extensions for PDO and the selected database driver, Mbstring, OpenSSL, Fileinfo, XML/DOM, and ZIP

## Local installation

### 1. Install dependencies

```bash
composer install
npm ci
```

### 2. Create the environment file

```bash
cp .env.example .env
php artisan key:generate
```

The sample environment uses `Asia/Manila`, database-backed sessions and queues, SQLite, and log-based email delivery.

### 3. Configure the database

#### SQLite

SQLite is the default in `.env.example`.

```bash
touch database/database.sqlite
php artisan migrate --seed
```

#### MySQL or MariaDB

Create an empty database, then update `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pagcor_shuttle
DB_USERNAME=root
DB_PASSWORD=
```

Run the migrations and seeders:

```bash
php artisan migrate --seed
```

### 4. Create the first administrator

The seeders intentionally do not create an administrator, and public registration is disabled. Create the first account with Tinker:

```bash
php artisan tinker
```

```php
\App\Models\User::create([
    'name' => 'System Administrator',
    'email' => 'admin@example.com',
    'password' => 'choose-a-strong-password',
    'user_type' => 'ADMIN',
]);
```

The `User` model hashes the password automatically. After signing in, the first administrator can create additional administrators from **User Management**.

### 5. Start the application

The combined development command starts Laravel, the queue listener, the log stream, and Vite:

```bash
composer run dev
```

The default local pages are:

- Administrator login: `http://localhost:8000/login`
- Employee QR login: `http://localhost:8000/employee/login`
- Health endpoint: `http://localhost:8000/up`

To run each process separately:

```bash
php artisan serve
```

```bash
php artisan queue:work
```

```bash
npm run dev
```

Keep the queue worker running so waitlist-promotion emails are processed.

## XAMPP on Linux

This repository can run from `/opt/lampp/htdocs`. Add XAMPP's PHP binaries to the current shell before using Composer or Artisan:

```bash
export PATH="/opt/lampp/bin:$PATH"
```

Start XAMPP when using its MySQL/MariaDB or Apache services:

```bash
sudo /opt/lampp/lampp start
```

For XAMPP Apache:

- Point the virtual host's `DocumentRoot` to this repository's `public/` directory.
- Enable `mod_rewrite` and allow `.htaccess` overrides.
- Set `APP_URL` to the exact local URL.
- Ensure `storage/` and `bootstrap/cache/` are writable by the web server.
- Run `npm run dev` during development or `npm run build` before serving production assets.
- Run `php artisan queue:work` in a separate terminal or supervised process.

## Seed data

`php artisan migrate --seed` populates every operational table except administrator users, reservations, and waitlist entries:

- 24 employees, including four priority employees
- 12 drivers
- 12 active vehicles
- 24 routes originating from PAGCOR Headquarters
- 24 weekday schedules covering outbound and return trips for 12 routes

Seeded employee addresses use the reserved `.example` domain. Replace them with deliverable addresses before testing real promotion emails.

To rebuild a development database and repopulate it:

```bash
php artisan migrate:fresh --seed
```

> This command deletes all existing database data. Use it only for a disposable local database.

## Employee import format

Employee Management accepts `.xlsx`, `.xls`, and `.csv` files up to 10 MB. The first row must contain these headings:

| Heading | Required | Notes |
| --- | --- | --- |
| `name` | Yes | Maximum 255 characters |
| `email` | Yes | Must be valid and unique |
| `contact_number` | No | Maximum 30 characters |
| `department` | No | Maximum 100 characters |
| `position` | No | Maximum 100 characters |
| `priority_status` | No | `REGULAR` or `PRIORITY`; defaults to `REGULAR` |

Existing employee email addresses are not overwritten during import; duplicate rows are rejected.

## QR login on local HTTP

Each employee QR contains a signed, versioned login path. Regenerating a QR increments its version and immediately invalidates the previous code.

Browsers allow camera access over plain HTTP on `localhost`, but normally block it on a LAN address such as `http://192.168.x.x`. When local HTTPS is unavailable:

- Open the portal on `localhost` to use the device camera on the same computer.
- Use **Upload** to decode a saved QR image.
- Use **Scanner** with a handheld QR scanner or paste the complete QR value.

Camera scanning from another phone or computer over the intranet requires HTTPS.

## Email and queue configuration

Waitlist promotion mail is queued after the seat assignment commits. A failed email does not undo the promoted reservation.

The default environment writes email to `storage/logs/laravel.log`:

```dotenv
MAIL_MAILER=log
QUEUE_CONNECTION=database
```

For real delivery, configure the `MAIL_*` values in `.env` for the available SMTP relay and keep a queue worker running:

```bash
php artisan queue:work --tries=3
```

## Shuttle configuration

System-wide reservation defaults are stored in `config/shuttle.php`:

| Setting | Default |
| --- | --- |
| Operating timezone | `Asia/Manila` |
| Booking horizon | 30 days |
| Protected priority seats | 8 |

After changing configuration in a cached environment, refresh Laravel's configuration cache:

```bash
php artisan config:clear
php artisan config:cache
```

## Production checklist

- Set `APP_ENV=production`, `APP_DEBUG=false`, and the correct `APP_URL`.
- Serve only the `public/` directory through the web server.
- Configure a production database, SMTP relay, and persistent queue worker.
- Use HTTPS for employee camera scanning over the intranet.
- Replace all seeded `.example` employee email addresses.
- Build frontend assets with `npm run build`.
- Cache Laravel configuration, routes, and views after deployment.
- Back up the database and `APP_KEY`.
