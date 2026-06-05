# School subdomain login portals

Give each school a **premium branded login URL** on its own subdomain (e.g. `bsit.yoursite.com`) while the LMS itself stays on your main domain.

Each subdomain folder is a **copy of the template** with a small `config.php` — no code changes needed per school.

## Folder structure

```
school_subdomain/
  template/              ← Master files to copy for every new subdomain
    config.php.example   ← Copy to config.php and edit
    init.php             ← Loads main LMS + subdomain helpers
    index.php            ← Branded login page
    .htaccess
    assets/css/subdomain.css
  examples/demo/         ← Sample config only
  README.md
```

## Quick setup (Hostinger)

### 1. Create the subdomain

In hPanel → **Domains** → **Subdomains**, create e.g. `bsit.yoursite.com`.  
Note the document root (usually `public_html/bsit` or similar).

### 2. Copy template files

Upload **all files inside** `school_subdomain/template/` into the subdomain document root:

```
domains/bsit.yoursite.com/public_html/
  index.php
  init.php
  config.php          ← you create this from the example
  .htaccess
  assets/css/subdomain.css
```

### 3. Configure `config.php`

```bash
# On your PC before upload, or on the server:
cp config.php.example config.php
```

Edit `config.php`:

| Setting | Description |
|--------|-------------|
| `lms_root` | Absolute path to main LMS on server (folder with `index.php`) |
| `lms_url` | Public URL of main LMS, e.g. `https://yoursite.com` |
| `school_code` | The school's login code from registration |
| `session_cookie_domain` | Usually `.yoursite.com` so login works on main site after redirect |
| `primary_color` | Hex brand color for the portal |
| `tagline`, `welcome_message` | Optional marketing copy |

Find `lms_root` in Hostinger **File Manager** — open the main site folder (`kimayco.site/public_html`) and confirm `includes/bootstrap.php` exists. Copy that full path, or use `'lms_root' => 'auto'`.

**Do not leave placeholder text** like `/home/.../public_html` — that is documentation only, not a real path.

Optional: upload `probe.php`, visit `https://yoursubdomain.site/probe.php`, copy the suggested path, then delete `probe.php`.

### 4. Upload main-site handler

On the **main LMS** (`kimayco.site/public_html`), ensure `subdomain-auth.php` exists (from the repo root). The subdomain login form posts directly to this file.

### 5. Test

Visit `https://bsit.yoursite.com` — you should see the split-screen login.  
After sign-in, users are sent to the **main LMS** dashboard (`teacher/`, `student/`, etc.) on `kimayco.site`.

## How sign-in works

The subdomain shows a branded login page, but the **form submits to the main site** (`subdomain-auth.php`). The session is created on `kimayco.site`, so Hostinger does not need shared cookies or token bridges.

Required on main site: `subdomain-auth.php`  
Optional legacy files: `subdomain-login.php` (token bridge, no longer required)

## Adding another school

1. Create a new subdomain in Hostinger (e.g. `shs.yoursite.com`).
2. Copy the same `template/` files into that subdomain folder.
3. Create a new `config.php` with the **other school's** `school_code` and optional branding.
4. Done — no changes to the main LMS codebase.

## Configuration reference

```php
return [
    'lms_root' => '/home/USER/domains/yoursite.com/public_html',
    'lms_url' => 'https://yoursite.com',
    'school_code' => 'TEST-SCHOOL',       // OR use 'school_slug' => 'test-school'
    'session_cookie_domain' => '.yoursite.com',
    'portal_title' => null,               // null = school name from database
    'tagline' => 'Your dedicated learning portal',
    'welcome_message' => '...',
    'primary_color' => '#0f6cbf',
    'logo_url' => 'https://yoursite.com/uploads/logo.png',
    'platform_name' => 'EduPlatform',
    'platform_url' => 'https://yoursite.com',
    'show_platform_link' => true,
    'support_email' => 'help@school.edu',
];
```

## Security

- `config.php` is blocked by `.htaccess` from direct download.
- Do **not** commit real `config.php` files with production paths to git.
- Subdomain uses the same database, CSRF, and rate limiting as the main LMS.

## Local XAMPP testing

Point a hosts entry at your machine and set:

```php
'lms_root' => 'C:/xampp/htdocs/LMS',
'lms_url' => 'http://localhost/LMS',
'session_cookie_domain' => '',  // leave empty locally
'school_code' => 'YOUR-CODE',
```
