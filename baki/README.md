# Nazuri Collections — Online Fashion Store

An online fashion store built with PHP 8.3 + MySQL, featuring an admin panel, Gmail SMTP email notifications, AES-256 encryption for customer PII, full dark mode, and bilingual UI (English / Swahili).

## Features

### Frontend
- **Homepage** — Hero carousel with video/image sliders, promo banners, product showcase, pre-order section
- **Shop** — Product listing with category filters, search, sorting, discount badges
- **Product Details** — Image gallery, size/color selection, quantity limits (max 3), countdown timer, star ratings & reviews, "Buy with WhatsApp" button
- **Cart & Checkout** — Session-based cart, coupon codes, payment via mobile networks (Tigo/Airtel/Vodacom/M-pesa) or bank/agent
- **Order Success** — Receipt with IDOR protection, print support
- **Dark Mode** — Bootstrap 5.3 native `data-bs-theme`, localStorage persistence, admin theme saved per-user
- **Language Switcher** — Toggle English / Swahili instantly

### Admin Panel (`/admin/`)
- **Dashboard** — Visitor stats, order/pre-order counts, sales trend chart (Chart.js), recent products
- **Products** — CRUD with image upload, sizes, colors, discount/offer scheduling, WebP conversion
- **Orders** — View, filter by date, update status, email notifications to customers
- **Pre-Orders** — Manage customer pre-order requests and pre-order products
- **Reviews** — Approve/delete with bulk actions
- **Sliders** — Hero carousel CRUD with video upload (MP4/WEBM)
- **Reports** — Monthly sales, top products, PDF export (html2pdf)
- **Settings** — Shop name, phone, email, address, social links, logo, default language/theme
- **Admins** — Registration (Super Admin only), profile update, password change
- **Security** — Login rate limiting (5/5min), 2FA, CSRF tokens, bcrypt passwords, session hardening
- **System Health** — Schema verification, server environment checks
- **Backup** — Database SQL download (Super Admin only)
- **Mail Test** — SMTP configuration tester
- **Activity Logs** — Visitor tracking with pagination, top IPs

### Security
- CSRF tokens on all forms
- Rate limiting on login, password reset, and checkout
- AES-256-CBC encryption for customer PII (email, phone)
- Honeypot fields on forms
- File upload MIME/size validation
- `.env` protected via `.htaccess` (deny all)
- Security headers: CSP, X-Frame-Options, HSTS, X-XSS-Protection, Referrer-Policy
- Session hardening: custom name, httponly, samesite, secure flag, regeneration
- Custom 403/404 error pages

## Tech Stack

| Component | Detail |
|---|---|
| **PHP** | 8.3+ (PDO, OpenSSL) |
| **Database** | MySQL 5.7+ (utf8mb4, InnoDB) |
| **Bootstrap** | 5.3.8 (self-hosted) |
| **Bootstrap Icons** | Self-hosted |
| **PHPMailer** | v7.1.1 (SMTP via Gmail) |
| **Chart.js** | Self-hosted |
| **html2pdf** | Self-hosted (PDF export) |
| **Fonts** | Playfair Display + Poppins (self-hosted WOFF2) |
| **Web Server** | Apache (mod_rewrite, mod_expires, mod_headers) |

## Project Structure

```
├── .env                     # Environment config (DB, SMTP, APP_KEY)
├── .htaccess                # Apache security, caching, rewrite rules
├── index.php                # Homepage (PHP — dynamic)
├── index.html               # Homepage (static English fallback)
├── index_sw.html            # Homepage (static Swahili fallback)
├── shop.php                 # Product listing
├── product_details.php      # Product detail + reviews
├── cart.php                 # Cart + checkout
├── order_success.php        # Order receipt
├── about.php                # About Us
├── admin/                   # Admin panel (25+ PHP files)
├── assets/
│   ├── css/                 # bootstrap.min.css, style.css
│   ├── js/                  # bootstrap, chart.js, html2pdf, main.js
│   ├── fonts/               # Self-hosted Google Fonts (WOFF2)
│   ├── icons/               # Bootstrap Icons CSS + font files
│   └── img/                 # Flags, placeholder SVGs
├── config/
│   ├── db_connect.php       # PDO + auto-schema migration
│   ├── env.php              # .env loader (putenv + $_ENV + $_SERVER)
│   ├── encryption.php       # AES-256-CBC encrypt/decrypt
│   ├── mailer.php           # PHPMailer send functions (4 variants)
│   ├── schema.php           # Centralized table definitions
│   └── grant_fashions.sql   # Complete MySQL dump (14 tables)
├── includes/
│   ├── header.php           # <head>, navbar, theme toggle, visit logger
│   ├── footer.php           # Footer + scripts
│   ├── functions.php        # Admin translation, upload validation
│   ├── admin_auth.php       # Auth guard (included in every admin page)
│   └── save_theme.php       # Admin theme persistence (AJAX)
├── languages/
│   ├── en.php               # English translations (292 keys)
│   └── sw.php               # Swahili translations (294 keys)
├── uploads/                 # Product images, videos, WebP
└── vendor/                  # PHPMailer (Composer)
```

## Database (14 tables)

`admins`, `products`, `orders`, `order_items`, `pre_orders`, `reviews`, `sliders`, `site_settings`, `activity_logs`, `visits`, `password_resets`, `request_rate_limits`, `product_gallery`, `login_attempts`

Auto-created on first page load via `config/schema.php`.

## Setup

### Requirements
- PHP 8.3+
- MySQL 5.7+
- Apache with mod_rewrite
- Composer (for PHPMailer)
- Gmail account with App Password (for SMTP)

### Installation

1. **Clone or upload** files to your web root (e.g., `htdocs/` on InfinityFree, or `www/` on WAMP)

2. **Install dependencies**
   ```bash
   composer install --no-dev
   ```

3. **Configure `.env`** (copy from below — never commit to git)
   ```
   DB_HOST=localhost
   DB_USERNAME=root
   DB_PASSWORD=
   DB_DATABASE=your_db_name

   SMTP_HOST=smtp.gmail.com
   SMTP_USER=your_email@gmail.com
   SMTP_PASS=your_app_password
   SMTP_PORT=587
   MAIL_FROM=your_email@gmail.com
   MAIL_FROM_NAME=Nazuri
   MAIL_ENABLED=true

   ADMIN_EMAILS=admin1@example.com,admin2@example.com

   APP_NAME=Nazuri Collections
   APP_URL=http://localhost/your-folder
   APP_KEY=base64_32_byte_random_key
   ```

4. **Import database** (optional — schema auto-creates on first load)
   ```sql
   mysql -u root -p your_db_name < config/grant_fashions.sql
   ```

5. **Set permissions**
   ```bash
   chmod 755 uploads/
   chmod 755 vendor/
   ```

6. **Access the site** at `http://localhost/your-folder`
   - First visit auto-creates the `admins` table
   - Default admin credentials:
     - **Username**: `testadmin`
     - **Email**: `testadmin@example.local`
     - **Password**: `Test@1234`
     - **Role**: Super Admin

### For InfinityFree (or shared hosting)

1. Upload all files via FTP to `htdocs/`
2. **Upload `.env`** to `htdocs/` (it's safe — `.htaccess` blocks web access to it)
3. **Upload `vendor/`** — you cannot run `composer install` on InfinityFree
4. Create database via cPanel, edit DB values in `.env`
5. Import `config/grant_fashions.sql` via phpMyAdmin
6. Set `APP_URL` to your domain



Auto-created on first login attempt if no admins exist.

## Email (SMTP)

All transactional emails sent via Gmail SMTP using PHPMailer:
- New order confirmation (Swahili, with product codes)
- Admin notification on new order (Swahili)
- Password reset link

**Requires a Gmail App Password** (not your regular password). Generate one at:
https://myaccount.google.com/apppasswords

Test SMTP at: `http://localhost/your-folder/admin/mail_test.php`

## Encryption

Customer PII (email, phone) is encrypted at rest using AES-256-CBC with the `APP_KEY` from `.env`.

- Encryption: `config/encryption.php::encryptData($plaintext, $appKey)`
- Decryption: `config/encryption.php::decryptData($ciphertext, $appKey)`

Encrypted data cannot be searched directly — all lookups use `id` or `public_id`.

## Language System

- **English** (`languages/en.php`) — 292 translation keys
- **Swahili** (`languages/sw.php`) — 294 translation keys
- Switched via `?lang=en` or `?lang=sw` parameter
- Admin panel uses `__('key')` function
- Frontend uses `t('key')` function
- Output buffer translation maps handle hardcoded strings automatically

## Dark Mode

- Bootstrap 5.3 `data-bs-theme` attribute
- Persisted in `localStorage` (frontend) and `site_settings` table (logged-in admins)
- Respects OS preference (`prefers-color-scheme`)
- Admin theme saved per-user via AJAX (`includes/save_theme.php`)

## License

Private project — all rights reserved.
