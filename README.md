# INSOPHINIA POS v7 — PHP + MySQL Edition

[GitHub Repository](https://github.com/kavineksith/INSOPHINIA-POS-v7-PHP-MySQL-Edition) · [Clone with Git](https://github.com/kavineksith/INSOPHINIA-POS-v7-PHP-MySQL-Edition.git)

A standalone Point of Sale (POS) application built with PHP 8.2+ and MySQL 8.0+.

Built with Bootstrap 5, Bootstrap Icons, jQuery, and locally bundled Geist fonts for offline use. It includes mPDF PDF generation, PHPMailer SMTP email, JWT authentication with token blacklisting, four-role RBAC (33 permissions), audit logging, rate limiting, CSRF protection, and password policies.

> **🐳 Docker users**: skip ahead to the [Quick Start with Docker / Podman](#quick-start-with-docker--podman) section — one command brings up PHP + Apache + MySQL + phpMyAdmin.

---

## ⚠️ Disclaimer, Warranty & Usage Notice

**INSOPHINIA POS v7 is provided for educational, development, testing, and evaluation purposes.**

This project is provided **"AS IS" and "AS AVAILABLE"**, without warranties or guarantees of any kind, whether express or implied. This includes, but is not limited to, warranties of reliability, security, fitness for a particular purpose, or suitability for commercial or production use.

### Important Notices

- **Not production-certified:** This project has not been independently validated, audited, or certified for real-world production environments. Its suitability for business-critical operations has not been established.
- **Testing is your responsibility:** Review and thoroughly test the application in your own environment before using it for actual business operations.
- **Security review required:** Review the source code, dependencies, authentication, authorization, configuration, database access, and server security before deployment.
- **Protect your data:** Maintain regular backups and test restoration procedures. Do not rely on an unverified backup process.
- **Change default credentials:** Replace all default usernames, passwords, application secrets, JWT keys, and database credentials before deployment.
- **Legal compliance:** You are responsible for ensuring that your use of the application complies with applicable laws, financial regulations, privacy requirements, and business obligations.
- **Third-party components:** External libraries, packages, services, and tools are subject to their respective licenses, terms, and security policies.

### Use at Your Own Risk

You are responsible for deciding whether this software is appropriate for your use case and for any risks associated with downloading, installing, modifying, or using it. The author and contributors provide no guarantee that the software will be secure, error-free, uninterrupted, or suitable for a particular business environment.

To the extent permitted by applicable law, the warranty disclaimer and limitation of liability in the [Apache License 2.0](LICENSE) apply. This README notice supplements the license; it does not replace or modify it.

**If you plan to use INSOPHINIA POS in a live business, conduct an independent technical, security, data-protection, and operational assessment first.**

### Security & Deployment Checklist

Before deployment, review and complete the following:

- [ ] Change all default usernames and passwords.
- [ ] Replace application secrets, JWT keys, and database credentials.
- [ ] Configure HTTPS and secure cookie settings.
- [ ] Restrict database and phpMyAdmin access; do not expose administrative services publicly without appropriate access controls.
- [ ] Configure firewall rules and appropriate server/file permissions.
- [ ] Disable debugging and development error output in live environments.
- [ ] Keep PHP, MySQL, Apache, container images, and third-party dependencies updated.
- [ ] Review authentication, authorization, and role-based access controls.
- [ ] Configure automated backups and test database restoration.
- [ ] Perform security, functionality, and performance testing before live business use.

> **Database warning:** Commands such as `docker compose down -v` can permanently delete Docker volumes, including your database. Back up important data before running destructive commands.
>
> **Docker warning:** Do not expose MySQL or phpMyAdmin ports directly to the public internet. Use strong credentials, firewall rules, and restricted network access.

---

## Quick Start with Docker / Podman (recommended — easiest path)

This brings up everything (PHP 8.2 + Apache + MySQL 8 + phpMyAdmin) in 3 containers.

Works with both `docker compose` and `podman-compose`.

### Prerequisites
- Docker 24+ with the `compose` plugin  **OR**  Podman 4+ with `podman-compose`
- Linux / macOS / Windows (with WSL2 on Windows)

### Steps
```bash
# 1. Clone the repository
git clone https://github.com/kavineksith/INSOPHINIA-POS-v7-PHP-MySQL-Edition.git
cd INSOPHINIA-POS-v7-PHP-MySQL-Edition

# 2. Create your environment file and edit the secrets
cp .env.example .env
nano .env

# 3a. Start with Docker
docker compose up -d --build

# 3b. Or start with Podman
podman-compose up -d --build
```

That's it. The first build takes ~2-3 minutes (mostly PHP extensions). On subsequent runs it's ~5 seconds.

### What happens automatically on first boot
1. **MySQL container** initializes the database and runs `install.sql` to create the application schema.
2. **PHP container** entrypoint:
   - Generates `config/config.php` from your `.env` environment variables
   - Installs Composer dependencies (if `vendor/` is missing)
   - Waits for MySQL to be reachable
   - Verifies schema is loaded (imports `install.sql` if not)
   - Runs `seed.php` (creates master admin + default settings + sample categories)
   - Starts Apache

### Access your services

| Service | URL | Login |
|---|---|---|
| **POS app** | http://localhost:8080 | `master` / `Master@Admin123!` |
| **phpMyAdmin** | http://localhost:8081 | Server: `mysql` · User: `root` · Pass: **(from .env)** |
| **MySQL** (external tools) | `localhost:3306` | User: `insophinia` · Pass: **(from .env)** |

### Common commands
```bash
# View logs (all services)
docker compose logs -f
# View logs (app only)
docker compose logs -f app
# Restart the app container (after editing PHP code)
docker compose restart app
# Stop everything (data is preserved in volumes)
docker compose down
# WARNING: Stop containers AND permanently delete volumes/data
docker compose down -v
# Rebuild image after editing Dockerfile or composer.json
docker compose up -d --build
# Run an artisan-like command inside the container
docker compose exec app php seed.php
docker compose exec app composer install
# Open a shell inside the app container
docker compose exec app bash
# Open a MySQL shell
docker compose exec mysql mysql -u insophinia -p insophinia_pos
```

### Where data is stored

| What | Where |
|---|---|
| MySQL data | Docker volume `mysql-data` (lives in `/var/lib/docker/volumes/` on Linux) |
| App storage (logs, temp PDFs, cache) | Docker volume `app-storage` |
| App source code | Inside the image (rebuild needed after edits) — or bind-mount for development |

### Development: edit PHP files without rebuilding

For local development, you can bind-mount your source directory over the in-image one:
```yaml
# In docker-compose.yml, under `app:` → `volumes:`, uncomment:
volumes:
  - app-storage:/var/www/html/storage
  - ./:/var/www/html                    # bind-mount source for live editing
```

Then restart with `docker compose restart app`. PHP file changes take effect immediately (opcache is disabled in dev mode — set `APP_DEBUG=true` in `.env`).

### Production hardening for Docker
1. **Set strong secrets in `.env`**:
   - `MYSQL_ROOT_PASSWORD` — at least 20 random chars
   - `DB_PASS` — at least 20 random chars
   - `JWT_SECRET` — at least 64 random chars (generate with `openssl rand -hex 32`)
   - `SEED_ADMIN_PASSWORD` — change immediately after first login
2. **Enable HTTPS** in front of the app:
   - Use a reverse proxy (Caddy / Nginx / Traefik) with Let's Encrypt
   - Set `COOKIE_SECURE=true` and `APP_URL=https://pos.yourdomain.com` in `.env`
3. **Don't expose MySQL publicly** — in `docker-compose.yml`, remove or comment out the `ports:` section under `mysql` so it's only reachable from inside the Docker network.
4. **Set up backups** — schedule a daily cron job:
```bash
   docker compose exec mysql mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" insophinia_pos | gzip > /backups/pos-$(date +%F).sql.gz
```

5. **Resource limits** — add to `docker-compose.yml`:
```yaml
   app:
     deploy:
       resources:
         limits:
           memory: 512M
           cpus: '1.0'
```

### Troubleshooting Docker

| Symptom | Fix |
|---|---|
| `Connection refused` on port 8080 | Wait ~30s for first boot. Check `docker compose logs app` |
| `SQLSTATE[HY000] [2002] Connection refused` | MySQL is still starting. Entrypoint will retry. |
| `Unknown database 'insophinia_pos'` | `docker compose down -v && docker compose up -d --build` to start fresh |
| 404 on all routes | Apache `mod_rewrite` may not be loaded. Check `docker compose exec app apache2ctl -M \| grep rewrite`. |
| Permission denied on storage/ | `docker compose exec app chown -R www-data:www-data storage` |
| phpMyAdmin can't connect | Confirm `mysql` service is healthy: `docker compose ps` |
| Want different ports | Edit `APP_PORT`, `PMA_PORT`, `MYSQL_PORT` in `.env` |

### Podman-specific notes
- Replace `docker compose` with `podman-compose` in all commands.
- Podman runs containers rootless by default — you may need to chown storage/ to your user:
```bash
  podman unshare chown -R 1000:1000 storage
```

- If SELinux blocks volume access, append `:Z` to bind-mount paths:
```yaml
  volumes:
    - ./config:/var/www/html/config:Z
```

---

## Manual Installation (without Docker)

<details>

<summary>Click to expand manual install instructions</summary>

## Stack

| Component | Tech |
|---|---|
| Language | PHP 8.2+ (no framework) |
| Database | MySQL 8.0+ (via PDO) |
| UI | Bootstrap 5.3 + Bootstrap Icons 1.11 + jQuery 3.7 |
| Fonts | Geist Sans + Geist Mono (offline woff2) |
| Auth | firebase/php-jwt (HS256) + bcrypt + token blacklist |
| Validation | valitron/valitron + custom regexes |
| PDF | mpdf/mpdf (HTML → PDF) |
| Email | phpmailer/phpmailer (SMTP) |
| UUIDs | ramsey/uuid (v4) |
| Routing | Custom front controller (`public/index.php` + `.htaccess`) |
| Schema | Single `install.sql` + `seed.php` CLI |

---

## Requirements
- PHP 8.2 or newer
- PHP extensions: pdo_mysql, mbstring, openssl, json, zlib, gd (optional, for mPDF image handling)
- MySQL 8.0+ (or MariaDB 10.6+)
- Composer 2+
- Apache with `mod_rewrite` **or** Nginx (config sample below)

---

## Installation

### 1. Clone the repository
```bash
git clone https://github.com/kavineksith/INSOPHINIA-POS-v7-PHP-MySQL-Edition.git
cd INSOPHINIA-POS-v7-PHP-MySQL-Edition
```

### 2. Install PHP dependencies
```bash
composer install --no-dev --optimize-autoloader
```

### 3. Create the database & user (MySQL CLI)
```bash
mysql -u root -p
```
```sql
CREATE DATABASE insophinia_pos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'insophinia'@'localhost' IDENTIFIED BY 'change-this-password';
GRANT ALL PRIVILEGES ON insophinia_pos.* TO 'insophinia'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 4. Import the schema
```bash
mysql -u insophinia -p insophinia_pos < install.sql
```

### 5. Configure the app
```bash
cp config/config.example.php config/config.php
```

Edit `config/config.php` and set:
- `db.host`, `db.name`, `db.user`, `db.pass` — your MySQL credentials
- `jwt_secret` — **CRITICAL**: replace with a long random string

  (`php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`)
- `app_url` — your site URL (e.g. `https://pos.example.com`)
- `cookie_secure` — set `true` if serving over HTTPS
- `email.*` — SMTP credentials (optional, can leave disabled)

### 6. Seed the database
```bash
php seed.php
```

This creates:
- The master admin user: `master` / `Master@Admin123!`
- 24 default settings (shop profile, taxes, bill toggles, etc.)
- 5 sample categories
- The SystemState row (deadman switch)
- A default Terms & Conditions template

**Change the master admin password immediately after first login.**

### 7. Serve

#### Option A — Apache

Point your Apache vhost at the `public/` directory:
```apache
<VirtualHost *:80>
    ServerName pos.example.com
    DocumentRoot /var/www/insophinia-pos-v7-php/public
    <Directory /var/www/insophinia-pos-v7-php/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

The included `public/.htaccess` will route all requests through `index.php`.

#### Option B — Nginx + PHP-FPM
```nginx
server {
    listen 80;
    server_name pos.example.com;
    root /var/www/insophinia-pos-v7-php/public;
    index index.php;
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    location ~ \\.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
    location ~ /\\.(ht|git) { deny all; }
    location ~ ^/(config|storage|app|vendor) { deny all; }
}
```

#### Option C — PHP built-in server (development only)
```bash
php -S localhost:8080 -t public public/index.php
```

Open http://localhost:8080/ in your browser. Log in with `master` / `Master@Admin123!`.

---

</details>

## Soft Delete & Hard Delete

All major entities support both **soft delete** (sets `deleted_at` + `is_active=0`) and **hard delete** (permanent removal). Soft-deleted records are hidden from default lists but can be viewed, restored, or permanently purged.

### Permission model

| Action | Permission | Who |
|---|---|---|
| Soft delete (any soft-deletable entity) | `manage:{resource}` | admin, master_admin (supervisor for some) |
| Restore a soft-deleted record | `manage:{resource}` | Same as soft delete |
| Hard (permanent) delete | `force_delete:any` | **master_admin only** |

### Trashed filter (UI)

Every list page now has a "Show soft-deleted records" dropdown with 3 modes:

| Mode | Behavior |
|---|---|
| **Hide deleted (default)** | Only active records are shown — this is the default on every page load |
| **Show with deleted** | Active + soft-deleted records in one list (soft-deleted shown with translucent styling + "Deleted" badge + strike-through) |
| **Only deleted** | Only soft-deleted records — useful for managing the trash bin |

When "Only deleted" is selected, the filter dropdown itself gets a red highlight, and the rows show restore + force-delete buttons instead of edit + delete.

### Visual styling for soft-deleted rows

Soft-deleted rows are rendered with:
- `opacity: 0.5` (translucent)
- `background-color: var(--muted)`
- `text-decoration: line-through` with `var(--destructive)` color
- A red "Deleted" badge next to the name
- Different action buttons (Restore / Force Delete) instead of Edit / Delete

### API endpoints

Every soft-deletable resource now supports these endpoints:
```

GET    /api/{resource}?trashed=hide|with|only    # filter
DELETE /api/{resource}/{id}                       # soft delete
DELETE /api/{resource}/{id}?force=true            # hard delete (master_admin only)
PATCH  /api/{resource}/{id}/restore               # restore soft-deleted
```

### Soft-deletable resources

| Resource | Soft Delete | Hard Delete | Restore |
|---|---|---|---|
| Categories | ✓ | ✓ (master_admin) | ✓ |
| Items | ✓ | ✓ (master_admin) | ✓ |
| Customers | ✓ | ✓ (master_admin) | ✓ |
| Bills | ✓ | ✓ (master_admin) | ✓ |
| Email Templates | ✓ | ✓ (master_admin) | ✓ |
| Terms Templates | ✓ | ✓ (master_admin) | ✓ |
| Users | ✓ (schema ready — UI in progress) | ✓ (master_admin) | ✓ (schema ready) |

### Audit trail

All four actions are recorded in the audit log with distinct action codes:
- `{RESOURCE}\_DELETE` — soft delete
- `{RESOURCE}\_FORCE_DELETE` — hard delete
- `{RESOURCE}\_RESTORE` — restore

View these in the System Logs page, or filter by `action` containing `DELETE` or `RESTORE`.

---

## Default Login

| Username | Password |
|---|---|
| `master` | `Master@Admin123!` |

**Change this immediately after first login.**

---

## Project Structure
```

insophinia-pos-v7-php/
├── public/                         # Web root (point Apache/Nginx here)
│   ├── index.php                   # Front controller — all routes defined here
│   ├── .htaccess                   # mod_rewrite + security headers
│   └── assets/
│       ├── vendor/                 # Bundled offline (no CDN required)
│       │   ├── bootstrap/          # Bootstrap 5.3 CSS + JS
│       │   ├── bootstrap-icons/    # Bootstrap Icons 1.11 + woff2 fonts
│       │   ├── jquery/             # jQuery 3.7
│       │   └── google-fonts/       # Geist Sans + Geist Mono woff2
│       ├── css/
│       │   ├── app.css             # OKLCH theme + Bootstrap overrides + sidebar
│       │   └── bill-print.css      # Thermal print CSS
│       ├── js/
│       │   ├── app.js              # AJAX wrapper, toasts, auth, pagination
│       │   ├── theme.js            # Dark/light toggle
│       │   ├── billing.js          # POS checkout (most complex)
│       │   ├── items.js            # Items + variants + sub-items
│       │   ├── customers.js
│       │   ├── categories.js
│       │   ├── bills.js
│       │   ├── warranties.js
│       │   ├── repairs.js
│       │   ├── marketing.js
│       │   ├── settings.js
│       │   ├── security.js
│       │   └── logs.js
│       └── img/
│           └── logo.svg
├── config/
│   ├── config.example.php          # Template — copy to config.php
│   └── config.php                  # (gitignored — your real config)
├── app/
│   ├── Core/
│   │   ├── App.php                 # Bootstrap + dispatch
│   │   ├── Database.php            # PDO singleton
│   │   ├── Router.php              # Pattern-based router
│   │   ├── Request.php             # Request wrapper
│   │   ├── Response.php            # JSON response builder
│   │   ├── Controller.php          # Base controller
│   │   ├── HttpException.php       # Domain exceptions
│   │   └── helpers.php             # Global helpers (env, config, e, etc.)
│   ├── Middleware/
│   │   ├── AuthMiddleware.php
│   │   ├── CsrfMiddleware.php
│   │   └── BodySizeMiddleware.php
│   ├── Lib/
│   │   ├── Auth.php                # JWT + bcrypt + password policy
│   │   ├── Security.php            # Rate limit + audit log + security events
│   │   ├── Csrf.php                # CSRF token gen + verify
│   │   ├── Validation.php          # 26 schemas (Valitron + custom)
│   │   ├── Permissions.php         # RBAC matrix (4 roles × 33 perms)
│   │   ├── Email.php               # PHPMailer wrapper + template renderer
│   │   ├── WhatsApp.php            # wa.me URL builder + LK phone normalize
│   │   ├── BillTemplate.php        # mPDF A4 PDF + thermal HTML + preview
│   │   ├── TempPdfStore.php        # Filesystem-based temp PDF cache
│   │   ├── Settings.php            # Settings cache + cast helpers
│   │   └── Util.php                # UUID, sanitize, decimal, docNumber
│   ├── Controllers/                # 17 API + 1 page controller
│   │   ├── AuthController.php
│   │   ├── DashboardController.php
│   │   ├── CategoryController.php
│   │   ├── ItemController.php
│   │   ├── CustomerController.php
│   │   ├── BillingController.php   # Most complex endpoint (460 LOC equivalent)
│   │   ├── WarrantyController.php
│   │   ├── RepairController.php
│   │   ├── MarketingTemplateController.php
│   │   ├── MarketingCampaignController.php
│   │   ├── TermsController.php
│   │   ├── SettingsController.php
│   │   ├── SecurityController.php
│   │   ├── LogController.php
│   │   ├── CsrfController.php
│   │   └── PageController.php      # Renders HTML views
│   ├── Models/                     # 24 thin PDO data-access classes
│   │   ├── BaseModel.php
│   │   ├── User.php, Category.php, Item.php, ItemVariant.php, ItemSubItem.php
│   │   ├── Customer.php, Bill.php, BillItem.php, StockMovement.php
│   │   ├── Warranty.php, WarrantyClaim.php, RepairJob.php, RepairPart.php
│   │   ├── EmailTemplate.php, MarketingCampaign.php, MarketingRecipient.php
│   │   ├── TermsTemplate.php, Setting.php, LoginSession.php, TokenBlacklist.php
│   │   ├── SecurityEvent.php, AuditLog.php, SystemState.php, Backup.php
│   │   ├── Promotion.php, SystemLog.php
│   │   └── ...
│   └── Views/
│       ├── layouts/
│       │   ├── auth.php            # Login page layout
│       │   └── dashboard.php       # Sidebar + header layout
│       ├── partials/
│       │   └── sidebar.php
│       ├── login.php
│       ├── dashboard.php
│       ├── billing.php             # POS checkout page
│       ├── bills.php
│       ├── items.php
│       ├── categories.php
│       ├── customers.php
│       ├── warranties.php
│       ├── repairs.php
│       ├── marketing.php
│       ├── settings.php
│       ├── security.php
│       ├── logs.php
│       └── reports.php
├── storage/
│   ├── temp_pdfs/                  # 10-min TTL PDF cache (filesystem)
│   ├── logs/
│   └── cache/
├── composer.json
├── install.sql                     # Full MySQL schema (24 tables + 11 ENUMs)
├── seed.php                        # CLI seeder
└── README.md
```

---

## Security Features

| Feature | Implementation |
|---|---|
| **Authentication** | JWT (HS256) via `firebase/php-jwt`. HttpOnly + SameSite=Strict cookies. Token also accepted via `Authorization: Bearer` header or `?token=` URL param (for PDFs). |
| **Token blacklist** | On logout, SHA-256(token) is inserted into `token_blacklist` table; checked on every protected request. |
| **Session management** | `login_sessions` table tracks every active session with IP, UA, last activity. Token rotation after 30 min. Session revocation on logout. |
| **Password policy** | 12–30 chars, requires uppercase, lowercase, digit, special char. Stored as bcrypt (cost 12). |
| **Account lockout** | 5 failed attempts → 15-minute lockout. Reset on successful login. |
| **Rate limiting** | Per-IP, persisted in `security_events` table. Defaults: 100/min global; 5/15min for login; 3/15min for password change; 5/hour for marketing send; 10/hour for warranty claims. |
| **CSRF protection** | Per-session token in non-HttpOnly cookie (readable by JS). Client sends via `X-CSRF-Token` header. Server verifies with `hash_equals()` (timing-safe). |
| **RBAC** | 4 roles (cashier, supervisor, admin, master_admin) × 33 permissions. Filtered on both API endpoints and sidebar nav items. |
| **Audit logging** | Every state-changing API call writes to `audit_logs` with user ID, action, entity, IP, UA. |
| **Security event logging** | LOGIN_SUCCESS/FAILED, LOGOUT, SUSPICIOUS_ACTIVITY, etc. with severity. Viewable at `/security`. |
| **Body size limit** | Default 1 MB. Configurable via `max_request_body_size`. |
| **Input sanitization** | All free-text inputs HTML-escaped. Template HTML stripped of `<script>`, `on*=` handlers, `javascript:` URIs. Email subjects reject newlines (anti-header-injection). |
| **SQL injection** | All queries via PDO prepared statements. No raw SQL string concatenation. |
| **Stock concurrency** | Atomic `UPDATE items SET stock = stock - ? WHERE id = ? AND stock >= ?` — fails if insufficient, returns 0 affected rows → 409. |
| **Bill number races** | Retry loop on duplicate key (3 attempts). |
| **Loyalty redemption** | Requires supervisor+ username+password (bcrypt-verified) for points redemption. |
| **Timing attack mitigation** | Login always runs bcrypt with dummy hash if user not found. |
| **Security headers** | `X-Content-Type-Options`, `X-Frame-Options`, `X-XSS-Protection`, `Referrer-Policy`, `Permissions-Policy` set in `.htaccess`. |

---

## Color Palette

All theme tokens are CSS variables in OKLCH color space, defined in `public/assets/css/app.css`:

| Token | Light | Dark |
|---|---|---|
| `--background` | `oklch(1 0 0)` | `oklch(0.145 0 0)` |
| `--foreground` | `oklch(0.145 0 0)` | `oklch(0.985 0 0)` |
| `--primary` | `oklch(0.205 0 0)` | `oklch(0.922 0 0)` |
| `--destructive` | `oklch(0.577 0.245 27.325)` | `oklch(0.704 0.191 22.216)` |
| `--chart-1` | `oklch(0.646 0.222 41.116)` | `oklch(0.488 0.243 264.376)` |
| `--chart-2` | `oklch(0.6 0.118 184.704)` | `oklch(0.696 0.17 162.48)` |
| `--chart-3` | `oklch(0.398 0.07 227.392)` | `oklch(0.769 0.188 70.08)` |
| `--chart-4` | `oklch(0.828 0.189 84.429)` | `oklch(0.627 0.265 303.9)` |
| `--chart-5` | `oklch(0.769 0.188 70.08)` | `oklch(0.645 0.246 16.439)` |
| `--radius` | `0.625rem` | `0.625rem` |

Dark mode is toggled by adding the `.dark` class to `<html>` and is persisted in `localStorage`. The toggle button appears in the header and on the login page.

---

## API Endpoints (38 total)

All under `/api/*`. Returns JSON: `{ success, message?, data?, errors?, pagination? }`.

### Auth
- `POST /api/auth/login` — rate-limited (5/15min)
- `POST /api/auth/logout` — blacklists token, revokes session
- `GET  /api/auth/me` — current user profile

### Dashboard
- `GET /api/dashboard` — 8 KPIs

### Categories
- `GET/POST /api/categories`, `GET/PUT/DELETE /api/categories/{id}`

### Items + Variants + Sub-items
- `GET/POST /api/items`, `GET/PUT/DELETE /api/items/{id}`
- `GET/POST /api/items/{id}/variants`, `PUT/DELETE /api/items/{id}/variants/{variantId}`
- `GET/POST /api/items/{id}/sub-items`, `PUT/DELETE /api/items/{id}/sub-items/{subItemId}`

### Customers
- `GET/POST /api/customers`, `GET/PUT/DELETE /api/customers/{id}`

### Billing
- `GET/POST /api/billing`
- `GET /api/billing/{id}` — full bill with items, warranties
- `PATCH /api/billing/{id}` — mark printed
- `GET /api/billing/{id}/pdf?format=thermal|a4|a4-preview|cached&key=...`
- `POST /api/billing/{id}/send` — channel: whatsapp_text, whatsapp_pdf, email_text, email_pdf

### Warranties & Claims
- `GET /api/warranties`, `GET/PATCH /api/warranties/{id}`
- `GET/POST /api/warranties/claims`, `PATCH /api/warranties/claims/{id}/review`

### Repairs
- `GET/POST /api/repairs`, `GET/PUT /api/repairs/{id}`
- `POST /api/repairs/{id}` — add part (with optional inventory decrement)

### Marketing
- `GET/POST /api/marketing/templates`, `GET/PUT/DELETE /api/marketing/templates/{id}`
- `GET/POST /api/marketing/campaigns`, `GET/DELETE /api/marketing/campaigns/{id}`
- `GET /api/marketing/campaigns/{id}/preview`
- `POST /api/marketing/campaigns/{id}/send` — rate-limited (5/hour)

### Terms & Settings
- `GET/POST /api/terms`, `GET/PUT/DELETE /api/terms/{id}`
- `GET /api/settings`, `PUT /api/settings`
- `POST /api/settings/test-email`

### Security & Logs
- `GET /api/security/events`
- `GET /api/logs`

---

## Roles & Permissions (RBAC Matrix)

| Permission | master_admin | admin | supervisor | cashier |
|---|---|---|---|---|
| view:dashboard | ✓ | ✓ | ✓ | ✓ |
| view:items / manage:items | ✓✓ | ✓✓ | ✓✓ | ✓ |
| view:categories / manage:categories | ✓✓ | ✓✓ | ✓✓ | ✓ |
| view:customers / manage:customers | ✓✓ | ✓✓ | ✓✓ | ✓✓ |
| view:billing / manage:billing | ✓✓ | ✓✓ | ✓✓ | ✓✓ |
| view:bills | ✓ | ✓ | ✓ | ✓ |
| view:reports / export:reports | ✓✓ | ✓✓ | ✓✓ | — |
| view:users / manage:users | ✓✓ | ✓✓ | — | — |
| view:settings / manage:settings | ✓✓ | ✓✓ | — | — |
| view:security / manage:security | ✓✓ | ✓✓ | — | — |
| view:logs | ✓ | ✓ | ✓ | — |
| authorize:returns | ✓ | ✓ | ✓ | — |
| authorize:loyalty | ✓ | ✓ | ✓ | — |
| manage:system_lock | ✓ | — | — | — |
| view:warranties / manage:warranties | ✓✓ | ✓✓ | ✓✓ | ✓ |
| view:repairs / manage:repairs | ✓✓ | ✓✓ | ✓✓ | ✓ |
| view:marketing / manage:marketing | ✓✓ | ✓✓ | ✓ | — |
| view:templates / manage:templates | ✓✓ | ✓✓ | ✓ | — |

---

## Running on Different Server Setups

### Subdirectory install

If installing at `https://example.com/pos/` instead of domain root:
1. Set `app_url` in `config.php` to `https://example.com/pos`
2. Update `cookie_path` to `/pos`
3. Update the `.htaccess` RewriteBase:
```

   RewriteBase /pos/
```

### Production hardening checklist
- [ ] Set `app_env` to `production` in `config/config.php`
- [ ] Set `debug` to `false`
- [ ] Generate a new `jwt_secret` (≥ 64 chars)
- [ ] Set `cookie_secure` to `true` (HTTPS only)
- [ ] Change master admin password immediately
- [ ] Configure SMTP (`email.*` settings)
- [ ] Set up daily MySQL backups (`mysqldump`)
- [ ] Rotate `storage/logs/php-error.log` (logrotate)
- [ ] Configure HTTPS (Let's Encrypt + Apache/Nginx)
- [ ] Enable HSTS by uncommenting the `Strict-Transport-Security` header in `.htaccess`

---

## Troubleshooting

### "Database connection failed"
- Verify `db.host`, `db.user`, `db.pass` in `config/config.php`
- Confirm the MySQL user has `ALL PRIVILEGES` on `insophinia_pos.*`
- Test: `mysql -u insophinia -p insophinia_pos -e "SELECT 1"`

### "Token has been revoked. Please login again."
- Your JWT was blacklisted (logout, session revoked). Just log in again.

### "Invalid CSRF token"
- The CSRF cookie is set on every page load. If your browser blocks third-party

  cookies, ensure the app is served from the same origin.
- Hard refresh (Ctrl+F5) to fetch a fresh token.

### "Rate limit exceeded"
- Default: 100 requests/min/IP. Login: 5/15min. Wait or ask an administrator to review recent `security_events` rows for your IP.

### PDF generation fails
- mPDF needs `ext-gd` for image handling and write access to `sys_get_temp_dir()`.
- Check `storage/logs/php-error.log` for details.

### Email not sending
- `email.enabled` must be `true` in `config/config.php`.
- Test with `POST /api/settings/test-email` from the Settings page.
- Check `email.host`, `email.port`, `email.username`, `email.password`.

---

## License

Copyright (c) 2026 Kavin Eksith. All rights reserved.

Licensed under the [Apache License, Version 2.0](LICENSE)

## Credits

INSOPHINIA POS v7 is built with PHP and MySQL.

Uses Bootstrap 5, Bootstrap Icons, jQuery, Geist fonts (Vercel), mPDF, PHPMailer, `firebase/php-jwt`, `ramsey/uuid`, and `valitron`.
