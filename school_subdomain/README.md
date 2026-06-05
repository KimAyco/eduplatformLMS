# School subdomain login portals

Give each school a **premium branded login URL** on its own subdomain (e.g. `bsit.yoursite.com`) while the LMS itself stays on your main domain.

Each subdomain folder is a **copy of the template** with a small `config.php` — no code changes needed per school.

## Folder structure

```
school_subdomain/
  template/              ← Master files to copy for every new subdomain
    config.php.example   ← Copy to config.php and edit
    init.php             ← Loads main LMS (no session) + subdomain helpers
    index.php            ← Branded login page (portal-auth-v3)
    status.php           ← Deploy diagnostic (delete after use)
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
cp config.php.example config.php
```

Edit `config.php`:

| Setting | Description |
|--------|-------------|
| `lms_root` | Use `'auto'` on Hostinger (recommended) |
| `lms_url` | Public URL of main LMS, e.g. `https://kimayco.site` |
| `school_code` | The school's login code from registration |
| `primary_color` | Hex brand color for the portal |
| `tagline`, `welcome_message` | Optional marketing copy |

Optional: upload `status.php`, visit it in the browser to verify setup, then delete it.

### 4. Upload main-site files

On the **main LMS** (`kimayco.site/public_html`), upload:

- `subdomain-auth.php`
- `includes/portal_auth.php`
- `includes/subdomain_bootstrap.php`
- `includes/security.php` (updated session cookie handling)

### 5. Set production `.env` on main site

```env
APP_ENV=production
APP_DEBUG=false
BASE_URL=
SESSION_COOKIE_DOMAIN=.kimayco.site
```

### 6. Test

Visit `https://bsit.yoursite.com` — you should see the split-screen login.  
View page source and confirm:

- `<!-- portal-auth-v3 -->`
- `<form ... action="https://kimayco.site/subdomain-auth.php">`

After sign-in, users land on the **main LMS** dashboard on `kimayco.site`.

## How sign-in works (portal-auth-v3)

1. Subdomain page loads school branding **without starting an LMS session** (no CSRF cookies).
2. Form POSTs email, password, school code, and a signed `portal_sig` to `subdomain-auth.php` on the main site.
3. Main site validates the signature, authenticates the user, creates the session, and redirects to the dashboard.

Required on main site: `subdomain-auth.php`, `includes/portal_auth.php`, `includes/subdomain_bootstrap.php`  
Legacy (optional): `subdomain-login.php` token bridge

## Troubleshooting

| Symptom | Fix |
|--------|-----|
| Redirect to `login.php` after sign-in | Upload latest `subdomain-auth.php`, `includes/security.php`; set `BASE_URL=` empty in `.env` |
| `Invalid CSRF token` on subdomain | Old `index.php` still deployed — re-upload template `index.php` (v3 posts to main site, no CSRF) |
| `Sign-in request expired` | Server clock skew or page left open >5 min — refresh and try again |
| `Portal sign-in is not configured` | Main site missing DB credentials in `.env` |

## Adding another school

1. Create a new subdomain in Hostinger.
2. Copy the same `template/` files into that subdomain folder.
3. Create a new `config.php` with the other school's `school_code`.

## Configuration reference

```php
return [
    'lms_root' => 'auto',
    'lms_url' => 'https://kimayco.site',
    'school_code' => 'NC-DAVAO',
    'portal_title' => null,
    'tagline' => 'Your dedicated learning portal',
    'welcome_message' => '...',
    'primary_color' => '#0f6cbf',
    'logo_url' => null,
    'platform_name' => 'EduPlatform',
    'platform_url' => 'https://kimayco.site',
    'show_platform_link' => true,
    'support_email' => null,
];
```

## Security

- `config.php` is blocked by `.htaccess` from direct download.
- Subdomain portal does not use session CSRF — cross-origin POST is protected by signed `portal_sig` + Referer/Origin checks.
- Rate limiting and school-scoped authentication match the main LMS login.

## Local XAMPP testing

```php
'lms_root' => 'C:/xampp/htdocs/LMS',
'lms_url' => 'http://localhost/LMS',
'school_code' => 'YOUR-CODE',
```
