# Serene Smile — Admin Management

A responsive PHP 8/MySQL admin module for a dental clinic. It provides secure administrator authentication, dashboard reporting, patient profiles and appointment history, dentist/service CRUD, appointment workflows, weekly schedules, unavailable-slot management, and conflict checks.

## XAMPP setup

1. Start **Apache** and **MySQL** in XAMPP.
2. Import `database/schema.sql` in phpMyAdmin (`http://localhost/phpmyadmin`).
3. From PowerShell in this directory, create the first administrator:

   ```powershell
   C:\xampp\php\php.exe scripts\create_admin.php
   ```

4. Open `http://localhost/Serene/login.php`.

The defaults in `config.php` match a normal local XAMPP installation (`root`, no password). For another environment, set `SERENE_DB_DSN`, `SERENE_DB_USER`, and `SERENE_DB_PASSWORD` in the web server environment.

## Deploying to Vercel

This repository includes a single-function Vercel router using the community `vercel-php` runtime. Connect the GitHub repository to Vercel, then add these variables for Production and Preview deployments:

- `SERENE_DB_DSN` — for example, `mysql:host=db.example.com;port=3306;dbname=serene_smile;charset=utf8mb4`
- `SERENE_DB_USER`
- `SERENE_DB_PASSWORD`
- `SERENE_DB_SSL_CA` — optional CA path override; Vercel uses the bundled ISRG Root X1 certificate by default

Import `database/schema.sql` into the hosted MySQL-compatible database before the first login. Vercel automatically selects database-backed sessions; local XAMPP continues to use normal file sessions. You can override this with `SERENE_SESSION_DRIVER` (`database` or `files`).

## Security and behavior

- Passwords use PHP's adaptive `password_hash`; no default credential is shipped.
- All queries use PDO prepared statements. Mutations require CSRF tokens.
- Admin sessions use strict mode, HTTP-only SameSite cookies, a 30-minute idle limit, ID rotation on login, route guards, and no-store headers.
- Appointment overlap is checked against pending/approved records, weekly dentist availability, and clinic/dentist blocks.
- Service deletion is refused when historical appointments refer to it; appointment service name/price snapshots preserve history.
- Valid status paths are Pending → Approved/Rejected/Cancelled and Approved → Completed/Cancelled.

## Functional test order

Create an admin, sign in, then add a dentist, service, patient, weekly dentist schedule, and appointment. Test approval/completion and try creating an overlapping appointment to verify rejection. Test logout and use the browser Back button to confirm protected pages are not restored.
