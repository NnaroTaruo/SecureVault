# Maintainer Notes

SecureVault is a **personal side project** by Ronan Antoque. It is **not open source** and is not accepting external pull requests or contributions.

## Maintainer Checks

1. Read [AGENTS.md](AGENTS.md) for architecture and encryption requirements, and [SECURITY.md](SECURITY.md) for current guarantees and limitations.
2. Run `php -l path/to/changed.php` for each changed PHP file and `php tests/auth-privacy.php`. Check the affected flow in the browser, including offline access after an online sync.
3. Never commit `config.php`, real SQL dumps, passwords, authentication logs, or hosting credentials. Do not change key derivation, salts, or the encrypted data format without a migration that preserves existing vault access.
4. For authentication, password-change, vault-action, offline-storage, or database-migration changes, run `node tests/master-password-change.cjs` (requires Node.js 22.4+, PHP, MySQL/MariaDB, and Chrome).

Copyright © 2026 Ronan Antoque. All rights reserved.
