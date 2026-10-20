# SecureVault

A personal password manager built with PHP, MySQL, and browser-based encryption. Save your logins, organize them by category, and access previously synced passwords offline.

[Live site](https://securevault.great-site.net/)

## Features

- Account passwords encrypted with AES-256-GCM and PBKDF2 through WebCrypto.
- Search, categories, and a built-in password generator.
- Installable web app with offline viewing in the same browser; changes require a connection.
- Revealed passwords hide after 30 seconds, with a vault lock after 5 minutes of inactivity.
- Responsive landing page with motion graphics, scroll animations, and reduced-motion support.

## Local setup

Requires **PHP 8+** with `mysqli` and `openssl`, **MySQL/MariaDB**, and **Apache**. XAMPP includes these tools.

1. Clone this repository into XAMPP’s `htdocs` folder:
   ```bash
   git clone https://github.com/NnaroTaruo/SecureVault.git
   ```
2. Obtain `database.sql` from the maintainer and import it into a new database. The schema is not included in Git.
3. Copy [config.example.php](config.example.php) to `config.php`, enter your database settings, and add:
   ```php
   define('SITE_URL', 'http://localhost/SecureVault/');
   define('MAINTENANCE_MODE', false);
   ```
4. Start Apache and MySQL, then open `http://localhost/SecureVault/`.

For InfinityFree, import the schema into your hosting database and upload the app to the domain’s `htdocs` folder. Update `config.php` with the hosting database settings, your HTTPS `SITE_URL`, and `ENVIRONMENT` set to `'production'`. Keep credentials, SQL dumps, and logs private.

## Security status

Saved account passwords are encrypted in the browser. Authentication currently sends the master password to PHP and logs authentication request bodies, so the implementation does **not yet meet its intended zero-knowledge design**.

Changing the master password currently updates sign-in only; existing entries still require the previous key.

Developed by **Ronan Antoque**.
