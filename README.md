# <img src="assets/logo.svg" width="40" height="40" alt=""> SecureVault

A personal password manager built with PHP, MySQL, and browser-based encryption. Save your logins, organize them by category, and access previously synced passwords offline.

SecureVault is a **personal side project** by Ronan Antoque, started after his mom kept forgetting her passwords. It is **not open source** and is not accepting outside contributions.

[Live site](https://securevault.great-site.net/)

<details>
<summary>Alternative logo</summary>

<img src="assets/securevault-logo-alternative.svg" width="112" height="112" alt="Alternative SecureVault logo: a mint S inside a dark vault door">

A compact vault door built around the letter **S**.

</details>

## Features

- Account passwords encrypted with AES-256-GCM and PBKDF2 through WebCrypto.
- Search, categories, and a built-in password generator.
- Installable web app with offline viewing for the current account; signing out removes this browser's vault copy. Changes require a connection.
- Revealed passwords hide after 30 seconds, with a vault lock after 5 minutes of inactivity.
- Responsive landing page with motion graphics, scroll animations, reduced-motion support, and plain-language Privacy Policy, Terms of Use, and Security Overview documents.

## Local development (maintainer only)

Requires **PHP 8+** with `mysqli` and `openssl`, **MySQL/MariaDB**, and **Apache**. XAMPP includes these tools.

1. Place the project in XAMPP’s `htdocs` folder.
2. Create an empty database in phpMyAdmin and import [database.example.sql](database.example.sql). It contains tables only; registration creates your categories.
3. Copy [config.example.php](config.example.php) to `config.php`. Set your database credentials and `SITE_URL` for your installation.
4. Start Apache and MySQL, then open `http://localhost/SecureVault/`.

## Deployment (InfinityFree)

Pushing to `master` runs [.github/workflows/deploy.yml](.github/workflows/deploy.yml). It checks PHP/JavaScript syntax and authentication privacy, then uploads only runtime files to `/htdocs` over FTP. The live `config.php`, SQL files, migrations, and tests are never uploaded or overwritten.

Required repository secrets: `FTP_USERNAME` and `FTP_PASSWORD` (from the InfinityFree client area). Optional: `FTP_SERVER` (default `ftpupload.net`) and `FTP_TARGET_DIR` (default `/htdocs`).

On the server, `config.php` must use the hosting database settings, the HTTPS `SITE_URL`, and `ENVIRONMENT` set to `'production'`. Keep credentials, SQL dumps, and logs private.

**Database changes** are not deployed automatically. Import new migrations once in InfinityFree’s phpMyAdmin before pushing code that depends on them:

- [Login counters](migrations/2026-10-27-login-throttles.sql) — required before the login-rate-limit update.
- [InnoDB upgrade](migrations/transactional-vault-tables.md) — required on older MyISAM databases for safe master-key changes.

## Security status

This is an **experimental side project** without an independent security audit. Saved account passwords are encrypted in the browser, but authentication still sends the master password to PHP, so it is **not yet fully zero-knowledge**.

Master-key changes require an online connection and an updated browser. They re-encrypt the complete current vault and sign out every session; other devices must reconnect to refresh their offline copies.

See [SECURITY.md](SECURITY.md) for details and limitations.

## Copyright

Copyright © 2026 Ronan Antoque. **All rights reserved.** SecureVault is not open source: no license is granted to copy, modify, distribute, or host this code without written permission. Third-party assets retain their own licenses.
