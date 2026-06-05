# EduPlatform — Pure PHP SaaS

Multi-tenant Learning Management System with a Moodle Boost-inspired interface.

## Requirements

- PHP 8.1+
- MySQL 5.7+ / MariaDB
- Apache with `mod_rewrite` (optional)

## Setup

### 1. Environment file

```bash
copy .env.example .env
```

Edit [`.env`](.env):

| Variable | Description |
|----------|-------------|
| `APP_NAME` | Platform display name |
| `APP_ENV` | `local` or `production` |
| `APP_DEBUG` | `true` or `false` |
| `BASE_URL` | `/LMS` for XAMPP subfolder, empty for Hostinger root |
| `DB_*` | MySQL credentials |
| `SUPER_ADMIN_*` | First super admin (used by installer only) |

**Never commit `.env` to version control.**

### 2. Database

Import schema and migrations:

```bash
mysql -u root < sql/schema.sql
mysql -u root < sql/migrations/002_security.sql
mysql -u root < sql/migrations/003_indexes.sql
```

### 3. Install super admin

Visit `http://localhost/LMS/install.php` once, then **delete `install.php`**.

The super admin is created from `SUPER_ADMIN_EMAIL` and `SUPER_ADMIN_PASSWORD` in `.env` — not from SQL seeds.

### 4. Permissions

Ensure `uploads/` and `storage/logs/` are writable by the web server.

## Hostinger Deployment

1. Upload all files to `public_html/` (`index.php` at root).
2. Copy `.env.example` to `.env` and set production values (`BASE_URL=` empty, `APP_ENV=production`, `APP_DEBUG=false`).
3. Import SQL files via phpMyAdmin.
4. Run `install.php` once, then delete it.
5. Confirm `.env` is blocked (included `.htaccess` rule).

For per-school login subdomains (e.g. `school.yoursite.com`), copy `school_subdomain/template/` to the subdomain folder, set `config.php`, and upload `subdomain-auth.php` to the main site. The portal form posts sign-in directly to the main domain so sessions work on Hostinger.

See [`school_subdomain/README.md`](school_subdomain/README.md).

## Features

- **Super Admin** — Approve schools, manage platform
- **School Admin** — Teachers, students, classes, enrollments
- **Teachers** — Materials, assignments, quizzes, grading
- **Students** — Course dashboard, submissions, timed quizzes

## Security

- Credentials in `.env` only
- CSRF protection on all forms
- Login rate limiting
- Hardened sessions and security headers
- Authenticated file downloads via `download.php`
- Tenant isolation by `school_id`

## Default URLs

| Page | URL |
|------|-----|
| Home | `/index.php` |
| School login | `/login.php` |
| Super admin | `/superadmin/login.php` |
| School registration | `/register/school.php` |

## Project structure

```
index.php           Entry point
.env                Environment config (not in git)
config/             App settings (reads .env)
includes/           Auth, security, repositories, layouts
sql/                Schema and migrations
assets/css/         Moodle-inspired styles
assets/js/          UI interactions (drawer, toasts)
uploads/            User uploads (PHP execution blocked)
storage/logs/       Application logs
subdomain-login.php Subdomain token bridge (legacy, optional)
subdomain-auth.php   School subdomain form POST handler (required)
```
