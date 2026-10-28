# Security status

SecureVault is experimental and has not had an independent security audit. Use sample credentials while evaluating it.

Saved account passwords use browser-side AES-256-GCM with PBKDF2/SHA-256 (100,000 iterations). However:

- Authentication still sends the master password to PHP, so the app is not fully zero-knowledge.
- Master-key changes require an online connection, working browser storage, and Web Locks support. The complete current vault is re-encrypted in the browser and saved with the sign-in hash in one InnoDB transaction. Missing or stale entries are rejected, and all sessions are signed out afterward.
- Legacy MyISAM installations must complete the [transactional-table upgrade](migrations/transactional-vault-tables.md) first. Master-key changes fail closed while `users`, `accounts`, or `sessions` is nontransactional. The upgrade also converts `categories`, preserving existing columns, character sets, salts, hashes, and ciphertexts.
- Existing offline copies on other devices do not update until those devices sign in online again. Interrupted or unconfirmed changes block this browser's stale offline copy until an online sign-in and sync.
- Service names, usernames, URLs, notes, and categories are not encrypted.
- This browser keeps one offline vault copy, bound to its account and sign-in. Switching accounts replaces it and closes older tabs. Explicit **Sign out** removes the encrypted copy, metadata, and local key check; the inactivity lock retains the encrypted copy for offline access. Cached dashboard HTML contains no personal fields or session tokens.
- CSRF coverage on login and registration still needs work. Vault writes, logout, and master-key changes require a session CSRF token.

Online sign-in has database-backed throttling: by default, five failed guesses within 15 minutes start a 15-minute account cooldown. Username/email aliases and database-equivalent spelling share the same account counter. Unknown identifiers receive the same generic responses and threshold. A source address also gets a cooldown after 25 failed attempts across accounts, including attempts against an already-cooled-down account. HTTP 429 includes `Retry-After`; blocked retries do not extend the account cooldown. A successful sign-in resets the account counter but retains source-wide failure history. Existing sessions and encrypted offline access are unaffected.

The limiter uses InnoDB row locks and hashed bucket identifiers, storing no passwords or raw usernames/IP addresses. Hashes are pseudonymous, not anonymous. Up to 100 expired counters are removed per sign-in request. The source is the server's `REMOTE_ADDR`; client-provided proxy headers are ignored. Confirm that your hosting exposes the real client address there before changing proxy configuration. Shared networks can share a source cooldown, and attackers can deliberately trigger an account cooldown. Rate limiting reduces online guessing; it does not stop distributed abuse, protect offline stolen hashes/ciphertexts, or replace MFA and infrastructure limits.

Existing installations must import [the additive login-counter migration](migrations/2026-10-27-login-throttles.sql) into the selected app database **before deploying this code**. Fresh installs use `database.example.sql`. If the counter table is missing or unavailable, sign-in fails closed with HTTP 503. No account, salt, or ciphertext migration is involved. Existing private configurations keep working: `MAX_LOGIN_ATTEMPTS` and `LOCKOUT_DURATION` are reused, and the new optional `LOGIN_IP_MAX_ATTEMPTS` defaults to 25.

The browser verifies master-key confirmations before adding, updating, or deleting entries. After a successful online sign-in, it saves an AES-GCM-encrypted check in local storage, bound to that sign-in's random context and existing salt. It stores neither the password nor the derived key. This also protects the first entry in an empty vault. Missing, damaged, or stale checks require another online sign-in; offline reveal still works from the existing encrypted cache.

After installing the offline-isolation update, close older SecureVault tabs and sign in online once to replace the old device copy. Offline sign-out clears this browser immediately; server-session termination requires a connection, or its next request with the sign-out cookie. It cannot revoke copies or sessions on other devices. If cleanup fails, the vault closes and asks you to clear this site's storage before sharing the browser. These safeguards are application checks, not protection against malicious same-origin scripts, browser extensions, or someone with access to the browser profile. Separate browser profiles remain preferable on shared devices.

These checks prevent incorrect keys in the normal browser interface; they are not server-enforced re-authentication. The API continues to authorize writes with the active session and CSRF token. A modified client or same-origin script can bypass browser checks. Existing sessions must sign in again after updating to create their check. bcrypt's legacy 72-byte authentication limit also remains relevant to older, unusually long master keys.

Changing the master key cannot repair entries already encrypted with a different or lost key. Keep any original keys needed for older encrypted backups.

Authentication request bodies must never be logged. Removing old logs or rewriting Git history does not invalidate captured passwords; replace any real credentials that were recorded or reused.

## Reporting a problem

Do not put passwords, vault contents, or exploitation details in public issues. If GitHub shows **Security → Report a vulnerability**, use that private reporting option. Otherwise, open an issue asking the maintainer to arrange a private reporting channel, without describing the vulnerability.

Report ordinary bugs through [GitHub Issues](https://github.com/NnaroTaruo/SecureVault/issues) with sample data and steps to reproduce them.
