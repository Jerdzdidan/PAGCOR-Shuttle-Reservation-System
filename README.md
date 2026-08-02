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
- Employee access-status management (`ACTIVE` or `INACTIVE`) without changing the permanent QR
- Permanent signed employee QR-code viewing and labeled PNG downloads
- Vehicle management using the plate number as the primary display identifier
- Driver, route, and recurring schedule management
- Vehicle capacity with an optional per-schedule capacity override
- Daily service occurrences with route, vehicle, driver, capacity, and departure snapshots
- Reserved-passenger boarding by QR scan or manual manifest
- Completed/not-operated service closeout with odometers, actual times, and notes
- Audited correction and reopen/refinalize workflows
- Successful employee QR login activity
- On-demand operational reports with charts, print, XLSX, CSV, and PDF output

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
- Recharts for administrator report visualizations
- Dompdf for generated report PDFs

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

The combined development command starts Laravel, the queue listener, the minute scheduler, the log stream, and Vite:

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
php artisan schedule:work
```

```bash
npm run dev
```

Keep the queue worker running so waitlist-promotion emails are processed. Keep the scheduler running so the current day’s service occurrences are created and departed services move to **Finished Services**.

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
- Run `php artisan schedule:work` in another terminal during local development.

## Seed data

In a non-production environment, `php artisan migrate --seed` creates a large,
current-date demonstration dataset for exercising the reservation, closeout, and
reporting workflows:

- 456 employees across 12 departments, including priority and inactive examples
- 24 drivers
- 24 active vehicles
- 24 routes originating from PAGCOR Headquarters
- 48 weekday schedules covering outbound and return trips for all 24 routes
- A rolling 90-day simulation containing roughly 3,000 service occurrences,
  70,000 reservations and attendance records, 95,000 reservation/waitlist
  events, closeout corrections, incidents, odometers, and QR login activity

The exact totals vary slightly with the current date and any existing local
records. The simulation is additive and does not create, replace, or update an
administrator account. It is skipped by `DatabaseSeeder` in production, and
`ReportSimulationSeeder` also refuses direct production execution.

The scheduler itself still materializes only real current-day services; the
historical records above are explicitly development seed data for previewing
reports.

Seeded employee addresses use the reserved `.example` domain. Replace them with deliverable addresses before testing real promotion emails.

To rebuild a development database and repopulate it:

```bash
php artisan migrate:fresh --seed
```

> This command deletes all existing database data. Use it only for a disposable local database.

## Employee import format

Employee Management accepts `.xlsx`, `.xls`, and `.csv` files up to 10 MB. The first row must contain these headings:

| Heading           | Required | Notes                                          |
| ----------------- | -------- | ---------------------------------------------- |
| `name`            | Yes      | Maximum 255 characters                         |
| `email`           | Yes      | Must be valid and unique                       |
| `contact_number`  | No       | Maximum 30 characters                          |
| `department`      | No       | Maximum 100 characters                         |
| `position`        | No       | Maximum 100 characters                         |
| `priority_status` | No       | `REGULAR` or `PRIORITY`; defaults to `REGULAR` |
| `status`          | No       | `ACTIVE` or `INACTIVE`; defaults to `ACTIVE`   |

Existing employee email addresses are not overwritten during import; duplicate rows are rejected.

## QR login on local HTTP

Each employee QR contains a permanent signed login path derived from the employee's database ID. The QR remains unchanged when employee details or priority status are edited. Employee Management can download a labeled PNG containing the QR, employee name, employee number, and current priority.

Keep the production `APP_KEY` stable and backed up. Changing it invalidates the
signatures in every permanent employee QR.

Browsers allow camera access over plain HTTP on `localhost`, but normally block it on a LAN address such as `http://192.168.x.x`. When local HTTPS is unavailable:

- Open the portal on `localhost` to use the device camera on the same computer.
- Use **Upload** to decode a saved QR image.
- Use **Scanner** with a handheld QR scanner or paste the complete QR value.

Camera scanning from another phone or computer over the intranet requires HTTPS.

The same browser security rule applies to administrator QR boarding. On local HTTP, use `localhost`, upload a QR image, or use the handheld-scanner input.

## Service occurrence and closeout workflow

The scheduler materializes only the current day’s active recurring schedules; it does not fabricate services for dates before deployment. Each occurrence keeps its own route, direction, vehicle, plate number, driver, departure, and capacity snapshots.

After departure, every occurrence—including an empty shuttle—appears under **Finished Services**:

- Use **Board passengers** to scan permanent employee QR codes or update the reserved-passenger manifest manually.
- Only an active employee reserved for that occurrence can be boarded.
- Completing a service requires opening and closing odometers. Unmarked reservations become no-shows.
- Marking a service **Not Operated** requires a reason and classifies its reservations separately from no-shows.
- Finalized records are read-only. Corrections and reopen/refinalize actions require a reason and create an audit record.

The opening odometer is suggested from the latest earlier completed service for
the vehicle. When services are closed out of order, the next completed
service's opening reading is used as the upper continuity boundary; otherwise
the suggestion falls back to the vehicle’s latest known reading.
An administrator may enter a vehicle's initial odometer while no completed
service exists for that vehicle. After the first completed service, the vehicle
reading is maintained by service closeout instead of direct vehicle editing.

An employee cannot be changed to **Inactive** while future reservations or
waitlist entries remain. Employee Management shows those conflicts and provides
an explicit resolve-and-deactivate action that cancels or withdraws the future
travel before access is disabled.

For the current travel date, the materialized service occurrence is the
authoritative route, vehicle, driver, departure, capacity, seat-allocation, and
waitlist snapshot. Later schedule edits apply to future occurrences without
rewriting today's service or historical records.

Attendance recorder, finalizer, correction administrator, employee, schedule,
route, vehicle, and driver identity snapshots are retained with their audit
records. Reservation/waitlist activity, closeout corrections, attendance, and
successful employee QR login history are retained indefinitely.

## Reports

Administrator reports default to the current month and include:

- Service Completion Register
- Fleet Utilization
- Route and Schedule Demand
- Shuttle Attendance
- Driver Utilization
- Reservation and Waitlist Activity
- Login Activity
- Incident Log

Reports are generated on demand and support applicable filters, server-side pagination, KPI summaries, charts, print-friendly output, XLSX, CSV, and PDF downloads.
Reservation cancellation entries include the recorded lead time before the
scheduled departure.

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

## Production scheduler

Run Laravel’s scheduler once per minute from the production host. Replace the example path and PHP binary with the deployment’s actual values:

```cron
* * * * * cd /path/to/PAGCOR-Shuttle-Reservation-System && php artisan schedule:run >> /dev/null 2>&1
```

Only one production host should run the cron entry unless the deployment uses a shared cache and Laravel’s single-server scheduling controls.

## Shuttle configuration

System-wide reservation defaults are stored in `config/shuttle.php`:

| Setting                  | Default       |
| ------------------------ | ------------- |
| Operating timezone       | `Asia/Manila` |
| Booking horizon          | 30 days       |
| Protected priority seats | 8             |

After changing configuration in a cached environment, refresh Laravel's configuration cache:

```bash
php artisan config:clear
php artisan config:cache
```

## Production checklist

- Set `APP_ENV=production`, `APP_DEBUG=false`, and the correct `APP_URL`.
- Preserve and securely back up the production `APP_KEY`.
- Serve only the `public/` directory through the web server.
- Configure a production database, SMTP relay, and persistent queue worker.
- Configure the once-per-minute Laravel scheduler cron.
- Use HTTPS for employee camera scanning over the intranet.
- Replace all seeded `.example` employee email addresses.
- Build frontend assets with `npm run build`.
- Cache Laravel configuration, routes, and views after deployment.
- Back up the database and `APP_KEY`.
