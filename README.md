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
- **User Registration**: encrypted PII storage (AES), HMAC lookup keys, bcrypt/password_hash, duplicate detection.
- **User Authentication**: username lookup by HMAC, password_verify checks, session establishment, optional lockout.
- **CSRF Protection**: server-backed token endpoint (`csrf.php`) and client injection (`csrf.js`).
- **Enrollment & Waitlist**: semester-aware CourseOffering model, atomic transactions, FIFO waitlist.
- **Audit & Logging**: immutable audit records and application logs.
- **Flash Messaging & UX**: server-side redirects with transient flash messages via `flash.js`.
- **Debug & Admin Utilities**: `whoami.php`, CLI debug tools, logging hooks.

### API Endpoints (summary)
- `GET /OCES/backend/api/csrf.php` → Generate/store CSRF token, returns `{ "csrf_token": "..." }`.
- `POST /OCES/backend/api/registration.php` → Register user, encrypt PII, hash password, insert transactionally.
- `POST /OCES/backend/api/login.php` → Authenticate user, verify password, set session.
- `POST /OCES/backend/api/logout.php` → Destroy session, redirect, flash message.
- `GET /OCES/backend/api/whoami.php` → Debug current session.
- `GET /OCES/backend/api/course-offerings?semester=<code>` → List offerings for a semester.
- `POST /OCES/backend/api/enroll` → Enroll/cancel/waitlist atomically.
- `GET /OCES/backend/api/audit-logs?limit=50` → Retrieve recent audit events (admin only).
---

## Quick Start (XAMPP)

1. **Install XAMPP** and start Apache + MySQL.
2. **Extract repo** to your XAMPP `htdocs` folder (example target: `C:\xampp\htdocs\OCES`).
3. **Create database** (example):

```powershell
mysql -u root -e "CREATE DATABASE IF NOT EXISTS OCES CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root OCES < sql/schema.sql
```

4. **Configure**

   * Copy `lib/config.example.php` to `lib/config.php` and update DB creds as needed. Do not commit secrets.
   * For local dev, ensure `FORCE_HTTPS` is false unless you have a local TLS setup.

5. **Access the app**

   * Registration: `http://localhost/OCES/frontend/site/registration.html`
   * Login: `http://localhost/OCES/frontend/site/login.html`

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

* `backend/api/csrf.php` — Establishes `$_SESSION['csrf_token']` and returns JSON `{csrf_token}`. Must be fetched with `credentials: 'same-origin'`.
* `frontend/site/csrf.js` — Fetches token from `csrf.php` and injects it into forms as a hidden input.
* `backend/api/registration.php` + `registrationHandler.php` — Validates, encrypts, hashes, and inserts new users in a transaction.
* `backend/api/login.php` + `loginHandler.php` — Authenticates by `username_hash`, verifies `password_hash` with `password_verify()`, and sets session.
* `tools/show-user-debug.php` — CLI helper to inspect DB rows and verify decryption and password verification.

---

## Security Highlights

* Passwords are stored using PHP's `password_hash()` and verified with `password_verify()`.
* PII is encrypted at rest using AES (see `encryptField()` in `init.php`); searchable keys use HMAC-SHA256.
* Sessions are configured centrally (`init.php`) with `HttpOnly` and `SameSite` attributes.
* CSRF tokens are server-backed and injected via same-origin fetch.
* For the local demo, some reproducibility choices (e.g., demo-mode plaintext secrets) are documented and should be replaced for production.

---

## Troubleshooting Checklist

1. **Registration doesn't insert**

   * Verify `<form method="POST" action="/OCES/backend/api/registration.php">`.
   * Confirm `csrf_token` is present in Request Payload and `Cookie: PHPSESSID` is sent.
   * Tail server logs: `Get-Content 'C:\xampp\htdocs\OCES\backend\logs\app-errors.log' -Tail 200 -Wait`.

2. **Login reports invalid credentials**

   * Ensure username normalization is identical in registration and login (`strtolower(trim())`).
   * Compute HMAC for username via CLI helper and compare to `username_hash` in DB.
   * Verify password hash length and `password_verify()` success via `tools/show-user-debug.php`.

3. **CSRF token missing**

   * Confirm `csrf.js` is included and runs before submit; fetch must use `credentials: 'same-origin'`.
   * Disable strict tracking protection for `localhost` in your browser for dev.

---


