# Security status

SecureVault is experimental and has not had an independent security audit. Use sample credentials while evaluating it.

Saved account passwords use browser-side AES-256-GCM with PBKDF2/SHA-256 (100,000 iterations). However:

- Authentication still sends the master password to PHP, so the app is not fully zero-knowledge.
- Master-password migration is not yet reliable. Entries can still require the previous key, including when the local offline copy is incomplete or stale.
- Service names, usernames, URLs, notes, and categories are not encrypted.
- Offline browser storage and cache isolation between users need further review. Use a separate browser profile for each user.
- CSRF protection, login throttling, and verification of the key before saving need further work.

Do not change an existing vault's master password as a recovery shortcut. Keep access to the original key until a tested migration is available.

## Reporting a problem

Do not put passwords, vault contents, or exploitation details in public issues. If GitHub shows **Security → Report a vulnerability**, use that private reporting option. Otherwise, open an issue asking the maintainer to arrange a private reporting channel, without describing the vulnerability.

Report ordinary bugs through [GitHub Issues](https://github.com/NnaroTaruo/SecureVault/issues) with sample data and steps to reproduce them.
