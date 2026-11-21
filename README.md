# Velvet Vogue: HTML, CSS, JavaScript, PHP and MySQL

The runnable website is in `public/`. Its ten pages are plain `.html` files;
JavaScript calls `public/api.php`, which runs the PHP backend using PDO MySQL.
The original ASP.NET application is retained in `VelvetVogueclothingstore/` as
a reference and is not required to run the new website.

## Requirements

- PHP 8.2 or newer with `pdo_mysql` and `fileinfo` enabled (for example XAMPP).
- MySQL 8+ or MariaDB 10.6+.
- Node.js only when regenerating HTML from the original templates or running browser tests.

## Database Setup

1. Start MySQL. Import `backend/schema.sql` using phpMyAdmin's Import tab or the MySQL command line. The default database name is `velvet_vogue`.
2. Create a dedicated database user with `SELECT`, `INSERT`, `UPDATE` and `DELETE` privileges on `velvet_vogue.*`. Do not use the MySQL root account for the website.
3. Configure the following environment variables in the terminal that will run PHP:

```powershell
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '3306'
$env:DB_NAME = 'velvet_vogue'
$env:DB_USER = 'velvet_vogue'
$env:DB_PASSWORD = '<your-local-database-password>'
php backend/setup.php
php -S localhost:8080 -t public
```

Open http://localhost:8080/index.html. With XAMPP, use the full PHP executable
path if it is not on PATH, for example `C:\xampp\php\php.exe`. Serving the
whole repository is unsafe: the web document root must be `public/` only.
Apache can use `public/` as its virtual-host DocumentRoot instead; set the
same environment variables in the Apache configuration. The frontend and
PHP must be served from the same origin. Opening HTML with `file://` cannot run PHP.

## Accounts and Features

Register customers on `login.html`. New passwords must contain at least 12
characters. Products support category, gender and search filters, size-based
inventory, a saved cart, shipping addresses, and Cash on Delivery checkout.
The shipping fee is LKR 10 per order and is included in the saved order total.
Contact form messages are saved to the `contact_messages` table; email delivery
is not configured.

There is no default admin password. To enable admin registration, set
`ADMIN_REGISTRATION_KEY` to a private random value of at least 24 characters
before starting PHP. Register through `admin-login.html`, then remove that
environment variable and restart PHP to disable further admin registration.
Never commit that key or database credentials. Admins can manage products,
upload JPG/PNG/WebP images up to 5 MB, view active carts and update order statuses.
Status flow is Pending -> Processing -> Shipped -> Delivered; Pending and
Processing orders can also be Cancelled, which restores stock once.

Uploaded images are kept outside the web root in `backend/uploads/` and served
through `image.php`. Give the PHP process write access to that directory.
For deployment, enable HTTPS and configure backups, log rotation, monitoring
and appropriate PHP upload/request limits. PHP's built-in server is for local development.

## Data and Pages

`backend/setup.php` seeds the demo categories and four products only when those
tables are empty. It does not import existing SQL Server users, password hashes,
carts or orders. Existing production data requires a separate data migration
and password compatibility/reset strategy.

The runnable site is maintained directly in `public/` (HTML, CSS and JavaScript)
and `backend/` (PHP). It does not require the ASP.NET/C# project or a page
converter.

## Verification

PHP syntax checks can be run with `php -l backend/api.php` and likewise for the
other PHP files. Run the integration suite only against a separate, seeded
test database and a PHP server configured to use that database:

```powershell
$env:TEST_BASE_URL = 'http://localhost:8080/'
$env:VELVET_ALLOW_TEST_WRITES = '1'
node --test tests/store.test.mjs
```

The tests create accounts, addresses, contact messages and orders and change
stock. They deliberately do not run without the write opt-in. To include admin
checks, set `TEST_ADMIN_KEY` to the same temporary value as the test server's
`ADMIN_REGISTRATION_KEY`; otherwise that subtest is skipped. Never run these
tests against live customer data. Initial verification used PHP 8.4 and MariaDB
11.4; an external MySQL installation still needs its own configuration check.