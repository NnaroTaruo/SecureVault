# <img src="logo.svg" width="40" height="40" alt=""> SecureVault

A personal password manager built with PHP, MySQL, and browser-based encryption. Save your logins, organize them by category, and access previously synced passwords offline.

Built by Ronan Antoque after his mom kept forgetting her passwords.

[Live site](https://securevault.great-site.net/)

<details>
<summary>Alternative logo</summary>

<img src="assets/securevault-logo-alternative.svg" width="112" height="112" alt="Alternative SecureVault logo: a mint S inside a dark vault door">

A compact vault door built around the letter **S**. [Download the editable SVG](assets/securevault-logo-alternative.svg).

</details>

## Features

- Account passwords encrypted with AES-256-GCM and PBKDF2 through WebCrypto.
- Search, categories, and a built-in password generator.
- Installable web app with offline viewing for the current account; signing out removes this browser's vault copy. Changes require a connection.
- Revealed passwords hide after 30 seconds, with a vault lock after 5 minutes of inactivity.
- Responsive landing page with motion graphics, scroll animations, and reduced-motion support.

## Local setup

Requires **PHP 8+** with `mysqli` and `openssl`, **MySQL/MariaDB**, and **Apache**. XAMPP includes these tools.

1. Clone this repository into XAMPP’s `htdocs` folder:
   ```bash
   git clone https://github.com/NnaroTaruo/SecureVault.git
   ```
2. Create an empty database in phpMyAdmin and import [database.example.sql](database.example.sql). It contains tables only; registration creates your categories.
3. Copy [config.example.php](config.example.php) to `config.php`. Set your database credentials and `SITE_URL` for your installation.
4. Start Apache and MySQL, then open `http://localhost/SecureVault/`.

For InfinityFree, import the schema into your hosting database and upload the app to the domain’s `htdocs` folder. Update `config.php` with the hosting database settings, your HTTPS `SITE_URL`, and `ENVIRONMENT` set to `'production'`. Keep credentials, SQL dumps, and logs private.

## Security status

This is an **experimental project**. Saved account passwords are encrypted in the browser, but authentication still sends the master password to PHP. The implementation does **not yet meet its intended zero-knowledge design**.

Master-key changes require an online connection and an updated browser. They re-encrypt the complete current vault and sign out every session; other devices must reconnect to refresh their offline copies.

See [SECURITY.md](SECURITY.md) for limitations and reporting guidance, and [CONTRIBUTING.md](CONTRIBUTING.md) to contribute.

## License

[MIT](LICENSE) · Copyright 2026 Ronan Antoque. Third-party assets retain their own licenses.
