# Security status

SecureVault is experimental and has not had an independent security audit. Use sample credentials while evaluating it.

Saved account passwords use browser-side AES-256-GCM with PBKDF2/SHA-256 (100,000 iterations). However:

- Authentication still sends the master password to PHP, so the app is not fully zero-knowledge.
- Master-key changes require an online connection, working browser storage, and Web Locks support. The complete current vault is re-encrypted in the browser and saved with the sign-in hash in one InnoDB transaction. Missing or stale entries are rejected, and all sessions are signed out afterward.
- Existing offline copies on other devices do not update until those devices sign in online again. Interrupted or unconfirmed changes block this browser's stale offline copy until an online sign-in and sync.
- Service names, usernames, URLs, notes, and categories are not encrypted.
- Offline browser storage and cache isolation between users need further review. Use a separate browser profile for each user.
- CSRF coverage on other authentication actions and login throttling need further work. Vault writes and master-key changes require a session CSRF token.

The browser verifies master-key confirmations before adding, updating, or deleting entries. After a successful online sign-in, it saves an AES-GCM-encrypted check in local storage, bound to that sign-in's random context and existing salt. It stores neither the password nor the derived key. This also protects the first entry in an empty vault. Missing, damaged, or stale checks require another online sign-in; offline reveal still works from the existing encrypted cache.

These checks prevent incorrect keys in the normal browser interface; they are not server-enforced re-authentication. The API continues to authorize writes with the active session and CSRF token. A modified client or same-origin script can bypass browser checks. Existing sessions must sign in again after updating to create their check. bcrypt's legacy 72-byte authentication limit also remains relevant to older, unusually long master keys.

Changing the master key cannot repair entries already encrypted with a different or lost key. Keep any original keys needed for older encrypted backups.

Authentication request bodies must never be logged. Removing old logs or rewriting Git history does not invalidate captured passwords; replace any real credentials that were recorded or reused.

## Reporting a problem

Do not put passwords, vault contents, or exploitation details in public issues. If GitHub shows **Security → Report a vulnerability**, use that private reporting option. Otherwise, open an issue asking the maintainer to arrange a private reporting channel, without describing the vulnerability.

Report ordinary bugs through [GitHub Issues](https://github.com/NnaroTaruo/SecureVault/issues) with sample data and steps to reproduce them.
