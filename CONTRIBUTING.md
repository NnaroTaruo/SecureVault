# Contributing to SecureVault

1. Fork the repository, create a branch, and follow the local setup in [README.md](README.md).
2. Keep changes small and explain the problem they solve. Use sample data only.
3. Read [AGENTS.md](AGENTS.md) for architecture and encryption requirements. Check actual behavior against [SECURITY.md](SECURITY.md); some intended guarantees are not implemented yet.
4. Run `php -l path/to/changed.php` for each changed PHP file and `php tests/auth-privacy.php`. Check the affected flow in your browser. For vault changes, also verify offline access after an online sync.
5. Open a pull request describing the change and how you checked it.

Never commit `config.php`, real SQL dumps, passwords, authentication logs, or hosting credentials. Do not change key derivation, salts, or the encrypted data format without a migration that preserves existing vault access.

After a repository history cleanup, clone the repository again. Merging or pushing an old clone can restore the removed logs.

For password-change work, also run `node tests/master-password-change.cjs`. It starts an isolated sample MariaDB database, PHP servers, and headless Chrome; it never uses your private configuration or vault. Defaults target XAMPP and Chrome on Windows; override `SV_TEST_PHP`, `SV_TEST_MYSQL_BIN`, or `SV_TEST_CHROME` for other installation paths.

By contributing your own work, you agree that it is available under the project's [MIT license](LICENSE). Identify any third-party code or assets and preserve their license notices.
