# Security status

SecureVault is experimental and has not had an independent security audit. Use sample credentials while evaluating it.

Saved account passwords use browser-side AES-256-GCM with PBKDF2/SHA-256 (100,000 iterations). However:

- Authentication still sends the master password to PHP, so the app is not fully zero-knowledge.
- Master-key changes require an online connection, working browser storage, and Web Locks support. The complete current vault is re-encrypted in the browser and saved with the sign-in hash in one InnoDB transaction. Missing or stale entries are rejected, and all sessions are signed out afterward.
- Existing offline copies on other devices do not update until those devices sign in online again. Interrupted or unconfirmed changes block this browser's stale offline copy until an online sign-in and sync.
- Service names, usernames, URLs, notes, and categories are not encrypted.
- Offline browser storage and cache isolation between users need further review. Use a separate browser profile for each user.
- CSRF coverage on other authentication actions, login throttling, and verification of the key before ordinary account saves need further work. Vault writes and master-key changes require a session CSRF token.

Changing the master key cannot repair entries already encrypted with a different or lost key. Keep any original keys needed for older encrypted backups.

Authentication request bodies must never be logged. Removing old logs or rewriting Git history does not invalidate captured passwords; replace any real credentials that were recorded or reused.

## Reporting a problem

Do not put passwords, vault contents, or exploitation details in public issues. If GitHub shows **Security → Report a vulnerability**, use that private reporting option. Otherwise, open an issue asking the maintainer to arrange a private reporting channel, without describing the vulnerability.

Report ordinary bugs through [GitHub Issues](https://github.com/NnaroTaruo/SecureVault/issues) with sample data and steps to reproduce them.
