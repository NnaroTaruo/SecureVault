# AGENTS.md — SecureVault v1.3 System & Architecture Guide

> **Notice for AI Coding Agents**: This document outlines the purpose, architectural invariants, offline PWA workflows, and developer guidelines for **SecureVault v1.3**. Always read and respect this context before modifying code.

---

## 1. Project Identity & Purpose
* **Project Name**: SecureVault (v1.3 PWA Edition)
* **Founder & Developer**: Ronan Antoque ([@RonanStack24](https://github.com/RonanStack24))
* **Live Deployment**: `https://securevault.great-site.net/` (InfinityFree)
* **Core Philosophy**: A personal, zero-knowledge, client-side encrypted password manager and installable Progressive Web App (PWA).

---

## 2. Ponytail Rules (Lazy Senior Dev Mode)

Before writing any code, stop at the first rung that holds:
1. **Does this need to exist?** $\rightarrow$ If NO, skip it (YAGNI).
2. **Already in this codebase?** $\rightarrow$ Reuse it, don't rewrite or duplicate.
3. **Stdlib does it?** $\rightarrow$ Use standard library functions.
4. **Native platform feature?** $\rightarrow$ Use native HTML/CSS/browser capabilities (WebCrypto, IndexedDB, Service Workers).
5. **Installed dependency?** $\rightarrow$ Use existing packages / Tailwind CDN.
6. **One line?** $\rightarrow$ Write one clean line.
7. **Only then** $\rightarrow$ Write the absolute minimum code that works.

### Critical Invariants:
* Trace the existing codebase and real execution flow before picking a rung.
* **Never compromise security or zero-knowledge invariants** (see below).
* Preserve comments and error-handling integrity.

---

## 3. The Zero-Knowledge Cryptographic Invariant (NON-NEGOTIABLE)

1. **The Server & Database Are Untrusted Storage**:
   * PHP and MySQL **NEVER** receive, process, or store plaintext passwords or master keys.
   * `users.master_key_salt` is stored on the server to ensure deterministic client-side key derivation.
   * `accounts.password_encrypted` in MySQL holds **strictly AES-256-GCM ciphertexts** (IV + encrypted data + auth tag encoded in Base64).
2. **Client-Side Cryptography (`js/dashboard.js` & Web Crypto API)**:
   * **Key Derivation**: `crypto.subtle.deriveKey` using **PBKDF2 with 100,000 rounds of SHA-256**.
   * **Encryption**: Authenticated `AES-256-GCM` with dynamic **12-byte cryptographically secure random IVs** generated in the browser via `crypto.getRandomValues`.
   * **In-Memory Decryption**: Plaintext passwords exist **only temporarily in browser memory** during user-initiated decryption (auto-masked after 30 seconds).

---

## 4. Routing & Application Structure

```
SecureVault/
├── index.php                 # Official Product Landing Page, Features & Live WebCrypto Sandbox
├── login.php                 # Master Gateway (Sign In & Account Registration with Strength Meter)
├── dashboard.php             # Main Vault App (Account list, modals, offline status, categories)
├── auth.php                  # Authentication session verification & master key verification
├── accounts.php              # Vault database logic & helpers
├── functions.php             # Core utility & cryptographic helper functions
├── db.php                    # MySQLi singleton database connection
├── config.php                # Environment config (DB credentials, SITE_URL, MAINTENANCE_MODE) [GIT IGNORED]
├── config.example.php        # Blueprint for local/production configuration
├── database.sql              # Database schema & default categories [GIT IGNORED]
├── service-worker.js         # PWA Service Worker (Cache v9: offline shell & API fallback)
├── manifest.json             # PWA Web App Manifest
├── api/
│   ├── accounts.php          # REST/AJAX endpoints (sync_vault, get_account, reveal, add, update, delete)
│   └── auth.php              # Auth endpoints (login, register, update_master_password, logout)
├── css/style.css             # Custom utility animations and scrollbar rules
└── js/
    ├── dashboard.js          # Core WebCrypto engine, IndexedDB (VaultDB), CRUD AJAX, UI logic
    └── pwa.js                # Service Worker registration & PWA installation prompts
```

---

## 5. Offline PWA & IndexedDB Workflow (v1.3 Engine)

SecureVault allows users to open their vault and decrypt passwords with **0 network connectivity**:

1. **Sync Cycle (When Online)**:
   * Upon opening `dashboard.php`, `syncVaultFromNetwork()` fetches `api/accounts.php?action=sync_vault`.
   * The encrypted payload (all `password_encrypted` ciphertexts, `master_key_salt`, categories) is saved to the browser's native IndexedDB (`SecureVaultDB` $\rightarrow$ `vault_items` and `vault_meta`).
2. **Offline Fallback (When Disconnected / Airplane Mode)**:
   * `service-worker.js` (Cache `v9`) intercepts page navigation and serves the cached `dashboard.php` app shell.
   * `js/dashboard.js` detects offline state and displays the `🟡 Offline Mode` header pill.
   * If the HTML account list is empty, `renderOfflineVaultCards()` reads the encrypted items directly from `VaultDB`.
3. **Offline Decryption**:
   * When clicking "Pass" (Reveal) or "Edit" offline, `executeRevealPassword()` retrieves the ciphertext from IndexedDB, retrieves the salt from `vault_meta`, prompts for the Master Key, and executes `decryptPasswordLocal()` in-memory.

---

## 6. Deployment & Environment Rules

* **Shared Host Target**: InfinityFree (`htdocs/` folder).
* **HTTPS Requirement**: The WebCrypto API (`window.crypto.subtle`) **requires a Secure Context (HTTPS or localhost)**.
* **`.htaccess` Configuration**:
  * Forces `https://` in production.
  * Exempts `localhost` and `127.0.0.1` so local development in XAMPP works smoothly without SSL certificates.
  * Blocks sensitive files (`.htaccess`, `.gitignore`, `config.php`, `database.sql`, `*.log`).
* **Maintenance Mode**:
  * Toggled via `define('MAINTENANCE_MODE', true/false)` in `config.php`.
  * Checked by `index.php`, `login.php`, `dashboard.php`, and `api/*.php` (returns HTTP 503).

---

## 7. Rules for Future AI Agents

1. **Never commit secrets**: `config.php` and `database.sql` are git-ignored. Never stage them or print plain database passwords.
2. **Never break offline capability**: Ensure changes to `dashboard.php` or `js/dashboard.js` don't break the IndexedDB sync or Service Worker caching.
3. **Always verify PHP syntax**: Run `php -l <file.php>` before committing changes.
4. **Preserve Master Key Salt**: Do not change the salt hashing mechanism or PBKDF2 iteration count without a planned database migration path, as existing encrypted vault items depend on it.