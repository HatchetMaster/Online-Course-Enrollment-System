* [Overview](#overview)
* [Features & API Endpoints](#features-and-api-endpoints)
* [Quick Start (XAMPP)](#quick-start-xampp)
* [File Layout](#file-layout)
* [Important Scripts & Endpoints](#important-scripts-and-endpoints)
* [Security Highlights](#security-highlights)
* [Troubleshooting Checklist](#troubleshooting-checklist)

---

## Overview

OCES is an Offline-Capable Online Course Enrollment System demo intended for classroom use and grading. It runs locally under XAMPP (Apache + PHP + MySQL/MariaDB) and demonstrates registration, authentication, course offering models, enrollment, and waitlist logic with an emphasis on reproducible local testing and basic security practices appropriate for a demo environment.

---

## Features and API Endpoints

### Features
- User registration with encrypted PII (AES) and HMAC lookup keys for searchable identifiers.
- Secure password storage using PHP's `password_hash()` / `password_verify()`.
- CSRF protection via a server-backed token endpoint and client injection.
- Enrollment and FIFO waitlist with atomic transactions and row-level locking for race-safety.
- Promotion of waitlisted users when seats open.
- Centralized bootstrap, logging and error handling.
- Flash messaging support for form-based UX flows.
- CLI and test utilities for reproducible local demos.

### API Endpoints (summary)
- `GET /OCES/backend/api/csrf.php` — Generate/store a session CSRF token; returns `{ "csrf_token": "..." }`.
- `POST /OCES/backend/api/registration.php` — Register user (encrypted PII, `password_hash`, `username_hash` via HMAC).
- `POST /OCES/backend/api/login.php` — Authenticate user and set session (handler-based, fallback logic present).
- `POST /OCES/backend/api/logout.php` — Destroy session and return JSON result.
- `GET  /OCES/backend/api/whoami.php` — Return current session debug info.
- `POST /OCES/backend/api/course_add.php` — Create a course. Accepts `course_name`, `course_code`, `description`, `capacity`, `course_start_date`, `course_end_date` (YYYY-MM-DD).
- `GET  /OCES/backend/api/courses.php` — List courses with `course_start_date`, `course_end_date`, `capacity`, `enrolled_count`, `waitlist_count`.
- `POST /OCES/backend/api/enroll.php` — Enroll/cancel/waitlist actions. Payload: `{ "course_id": <id>, "action": "enroll"|"cancel", "csrf_token": "..." }`.
- `GET  /OCES/backend/api/enrollments.php` — List current user's enrollments and waitlist positions.
- `GET  /OCES/backend/api/flash.php` — Return one-time flash messages for form UX.
- `GET  /OCES/backend/api/audit-logs.php` (admin tooling — if present) — Retrieve recent audit events.
---

## Quick Start (XAMPP)

1. Install XAMPP and start Apache + MySQL.
2. Extract the repository to your XAMPP `htdocs` folder (example target: `C:\xampp\htdocs\OCES`).
3. Create the database and load schema (PowerShell / cmd example):

```powershell
mysql -u root -e "CREATE DATABASE IF NOT EXISTS OCES CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root OCES < sql/schema.sql
```

4. **Configure**

   - Copy `backend/lib/config.example.php` to `backend/lib/config.php` and update DB credentials and any options. Do not commit secrets.
   - For local dev, keep `FORCE_HTTPS` disabled unless you have local TLS configured.
   - Provide crypto keys as environment variables or in `backend/lib/config.php` (see Security section).

5. **Access the app**

   - Registration: `http://localhost/OCES/frontend/site/registration.html`
   - Login: `http://localhost/OCES/frontend/site/login.html`

---

## File Layout

```
/OCES
  /backend
    /api
      init.php
      csrf.php
      whoami.php
      registration.php
      login.php
      logout.php
      registrationHandler.php
      loginHandler.php
    /logs
  /frontend
    /site
      registration.html
      login.html
      index.html
      csrf.js
      registration.js
      login.js  (optional; may intercept form)
  /lib
    config.example.php
  /sql
    schema.sql
  /tools
    decrypt-test.php
    print-session-csrf.php
    show-user-debug.php
    test-login-cli.php
    test-register.php
README.md
```

---

## Important Scripts and Endpoints

- `backend/api/csrf.php` — Establishes `$_SESSION['csrf_token']` and returns JSON `{ "csrf_token": "..." }`. Fetch with `credentials: 'same-origin'`.
- `frontend/site/csrf.js` — Client helper: fetches the CSRF token and injects it into forms / JSON payloads.
- `backend/api/registration.php` + `backend/api/registrationHandler.php` — Registration flow: validate, encrypt, hash, insert transactionally.
- `backend/api/login.php` + `backend/api/loginHandler.php` — Login flow: HMAC username lookup, `password_verify()`, session set.
- `backend/api/course_add.php` — Create courses with optional start/end dates (YYYY-MM-DD) and capacity.
- `backend/api/courses.php` — Course listing including enrollment/waitlist counts.
- `backend/api/enroll.php` / `backend/api/enrollments.php` — Enrollment API: enroll, cancel, waitlist, and promote logic.
- `backend/api/flash.php` — Provides transient server-side flash messages to the frontend.
- `backend/api/crypto.php` — Crypto bootstrap: loads keys, exposes `encrypt_blob()`, `decrypt_blob()`, and HMAC helpers.
- `backend/api/logging.php` — Backwards-compatible logging shim for legacy calls.
- `tools/*` — Utility scripts to seed/test data and inspect DB rows.

---

## Security Highlights

- Passwords are stored with `password_hash()` and verified with `password_verify()`.
- PII (e.g., usernames stored encrypted) is encrypted at rest with AES (`encrypt_blob()` / `decrypt_blob()` in `backend/api/crypto.php`); searchable identifiers use HMAC-SHA256 (`usernameHash()` / `hmac_lookup()`).
- Sessions are configured centrally in `backend/api/init.php` with `HttpOnly` and `SameSite` attributes. Session cookie `secure` is enabled when `FORCE_HTTPS` is set or HTTPS is detected.
- CSRF tokens are server-backed and must be included in form submissions and JSON payloads (`csrf_token`).

---

## Troubleshooting Checklist

1. Registration doesn't insert
 - Confirm the form posts to `POST /OCES/backend/api/registration.php`.
 - Ensure `csrf_token` is present in the request and the session cookie (`PHPSESSID`) is sent.
 - Check `backend/logs/app-errors.log` for errors:
   ```powershell
   Get-Content 'C:\xampp\htdocs\OCES\backend\logs\app-errors.log' -Tail 200 -Wait
   ```

2. Login reports invalid credentials
 - Ensure username normalization (lowercase / trimmed) matches between registration and login.
 - Compare the HMAC of the username (`username_hash`) stored in DB using the same HMAC key and normalization.
 - Use `tools/show-user-debug.php` or `tools/create_default_students.php` to inspect/decrypt test rows.

3. CSRF token missing or rejected
 - Make sure `frontend/site/csrf.js` runs and calls `GET /backend/api/csrf.php` before form submission.
 - The fetch must include `credentials: 'same-origin'` to associate the session cookie.
 - In development, browser tracking protections can block cookies on `localhost`. Try a different browser or adjust settings.

4. Enrollment / Waitlist issues
 - Check DB schema matches `backend/sql/schema.sql`.
 - Verify `tblCourses.capacity` values (0 means unlimited).
 - Ensure the DB supports transactions and `SELECT ... FOR UPDATE` semantics (InnoDB).

5. Error: duplicate function/class or redeclare warnings
 - Ensure duplicated compatibility wrappers (e.g., `log_entry`) are not defined twice — remove duplicates if present.
 - Confirm only a single bootstrap chain includes `backend/api/init.php` and `backend/lib/error_handler.p

---


