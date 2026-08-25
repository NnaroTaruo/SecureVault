// ==============================================================================
// SecureVault - Dashboard & Zero-Knowledge Offline Cryptography Engine
// ==============================================================================

const colors = {
    success: 'bg-green-900/40 border-green-500 text-green-300',
    error:   'bg-red-900/40 border-red-500 text-red-300',
    warning: 'bg-yellow-900/40 border-yellow-500 text-yellow-300',
    info:    'bg-blue-900/40 border-blue-500 text-blue-300'
};

const iconColors = ['#FF6B6B','#FF8E53','#FFC300','#2ECC71','#00E676','#3498DB','#9B59B6','#E91E63','#FF5722','#00BCD4'];

function showAlert(message, type = 'info') {
    const alertBox = document.getElementById('alertArea');
    if (!alertBox) return;

    const el = document.createElement('div');
    el.className = `p-3.5 rounded-xl border-l-4 text-xs font-semibold flex items-center gap-2 ${colors[type] || colors.info}`;
    el.style.cssText += 'animation: slideDown .25s ease-out;';

    const messageEl = document.createElement('span');
    messageEl.className = 'flex-1';
    messageEl.textContent = message;

    const closeButton = document.createElement('button');
    closeButton.type = 'button';
    closeButton.className = 'flex-shrink-0 opacity-60 hover:opacity-100 text-lg leading-none';
    closeButton.innerHTML = '&times;';
    closeButton.addEventListener('click', () => el.remove());

    el.append(messageEl, closeButton);
    alertBox.appendChild(el);
    setTimeout(() => el.remove(), 4500);
}

// ── 1. IndexedDB Engine for Encrypted Vault (Zero-Knowledge) ─
const VaultDB = {
    DB_NAME: 'SecureVaultDB',
    DB_VERSION: 1,
    db: null,

    async open() {
        if (this.db) return this.db;
        return new Promise((resolve, reject) => {
            if (!('indexedDB' in window)) {
                console.warn('IndexedDB not supported');
                return resolve(null);
            }
            const request = indexedDB.open(this.DB_NAME, this.DB_VERSION);
            request.onupgradeneeded = (e) => {
                const db = e.target.result;
                if (!db.objectStoreNames.contains('vault_items')) {
                    db.createObjectStore('vault_items', { keyPath: 'id' });
                }
                if (!db.objectStoreNames.contains('vault_meta')) {
                    db.createObjectStore('vault_meta', { keyPath: 'key' });
                }
            };
            request.onsuccess = (e) => {
                this.db = e.target.result;
                resolve(this.db);
            };
            request.onerror = (e) => reject(e.target.error);
        });
    },

    async saveAll(accounts, meta = {}) {
        const db = await this.open();
        if (!db) return false;
        return new Promise((resolve, reject) => {
            const tx = db.transaction(['vault_items', 'vault_meta'], 'readwrite');
            const itemsStore = tx.objectStore('vault_items');
            const metaStore = tx.objectStore('vault_meta');

            itemsStore.clear();
            accounts.forEach(acc => {
                // Ensure id is numeric
                const item = { ...acc, id: Number(acc.id) };
                itemsStore.put(item);
            });

            for (const [k, v] of Object.entries(meta)) {
                metaStore.put({ key: k, value: v });
            }

            tx.oncomplete = () => resolve(true);
            tx.onerror = (e) => reject(e.target.error);
        });
    },

    async getAll() {
        const db = await this.open();
        if (!db) return [];
        return new Promise((resolve, reject) => {
            const tx = db.transaction('vault_items', 'readonly');
            const store = tx.objectStore('vault_items');
            const req = store.getAll();
            req.onsuccess = () => resolve(req.result || []);
            req.onerror = (e) => reject(e.target.error);
        });
    },

    async getItem(id) {
        const db = await this.open();
        if (!db) return null;
        return new Promise((resolve, reject) => {
            const tx = db.transaction('vault_items', 'readonly');
            const store = tx.objectStore('vault_items');
            const req = store.get(Number(id));
            req.onsuccess = () => resolve(req.result || null);
            req.onerror = (e) => reject(e.target.error);
        });
    },

    async putItem(item) {
        const db = await this.open();
        if (!db) return false;
        return new Promise((resolve, reject) => {
            const tx = db.transaction('vault_items', 'readwrite');
            const store = tx.objectStore('vault_items');
            const req = store.put({ ...item, id: Number(item.id) });
            req.onsuccess = () => resolve(true);
            req.onerror = (e) => reject(e.target.error);
        });
    },

    async deleteItem(id) {
        const db = await this.open();
        if (!db) return false;
        return new Promise((resolve, reject) => {
            const tx = db.transaction('vault_items', 'readwrite');
            const store = tx.objectStore('vault_items');
            const req = store.delete(Number(id));
            req.onsuccess = () => resolve(true);
            req.onerror = (e) => reject(e.target.error);
        });
    },

    async getMeta(key) {
        const db = await this.open();
        if (!db) return null;
        return new Promise((resolve, reject) => {
            const tx = db.transaction('vault_meta', 'readonly');
            const store = tx.objectStore('vault_meta');
            const req = store.get(key);
            req.onsuccess = () => resolve(req.result ? req.result.value : null);
            req.onerror = (e) => reject(e.target.error);
        });
    }
};

// ── 2. Web Crypto API Key Derivation & Cryptography ─────────
async function deriveKey(password, saltHex) {
    const enc = new TextEncoder();
    const keyMaterial = await window.crypto.subtle.importKey(
        "raw", enc.encode(password), { name: "PBKDF2" }, false, ["deriveBits", "deriveKey"]
    );
    const saltBytes = enc.encode(saltHex);
    return window.crypto.subtle.deriveKey(
        { name: "PBKDF2", salt: saltBytes, iterations: 100000, hash: "SHA-256" },
        keyMaterial, { name: "AES-GCM", length: 256 }, true, ["encrypt", "decrypt"]
    );
}

function bufToBase64(buffer) {
    let binary = '';
    const bytes = new Uint8Array(buffer);
    for (let i = 0; i < bytes.byteLength; i++) binary += String.fromCharCode(bytes[i]);
    return window.btoa(binary);
}

function base64ToBuf(base64) {
    const binary = window.atob(base64);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
    return bytes.buffer;
}

async function encryptPasswordLocal(password, key) {
    const enc = new TextEncoder();
    const iv = window.crypto.getRandomValues(new Uint8Array(12));
    const encrypted = await window.crypto.subtle.encrypt(
        { name: "AES-GCM", iv: iv }, key, enc.encode(password)
    );
    const result = new Uint8Array(iv.length + encrypted.byteLength);
    result.set(iv, 0);
    result.set(new Uint8Array(encrypted), iv.length);
    return bufToBase64(result);
}

async function decryptPasswordLocal(encryptedBase64, key) {
    try {
        const data = base64ToBuf(encryptedBase64);
        const dataArray = new Uint8Array(data);
        if (dataArray.length < 28) throw new Error("Data too short");
        const iv = dataArray.slice(0, 12);
        const ciphertext = dataArray.slice(12);
        const decrypted = await window.crypto.subtle.decrypt(
            { name: "AES-GCM", iv: iv }, key, ciphertext
        );
        const dec = new TextDecoder();
        return dec.decode(decrypted);
    } catch (e) {
        return null;
    }
}

// ── 3. Vault Synchronization & Offline Mode ─────────────────
function updateStatusPill(isOnline) {
    const pill = document.getElementById('offlineStatusPill');
    if (pill) {
        pill.classList.toggle('hidden', isOnline);
    }
}

async function syncVaultFromNetwork() {
    if (!navigator.onLine) {
        updateStatusPill(false);
        checkRenderOfflineVault();
        return;
    }

    try {
        const response = await fetch('api/accounts.php?action=sync_vault', {
            method: 'GET',
            headers: { 'Cache-Control': 'no-cache' }
        });
        const data = await response.json();
        if (data.success && data.data) {
            await VaultDB.saveAll(data.data.accounts || [], {
                salt: data.data.salt || (typeof USER_SALT !== 'undefined' ? USER_SALT : ''),
                categories: data.data.categories || [],
                synced_at: data.data.synced_at || new Date().toISOString()
            });
            updateStatusPill(true);
        } else {
            updateStatusPill(false);
            checkRenderOfflineVault();
        }
    } catch (err) {
        console.warn('Network sync failed, using offline vault:', err);
        updateStatusPill(false);
        checkRenderOfflineVault();
    }
}

async function checkRenderOfflineVault() {
    const grid = document.getElementById('accountsList');
    // If grid is missing or empty on offline page load, render from IndexedDB
    const existingCards = document.querySelectorAll('#accountsList [data-account-id]');
    if (!existingCards.length) {
        const items = await VaultDB.getAll();
        if (items && items.length) {
            renderOfflineVaultCards(items);
        }
    }
}

function renderOfflineVaultCards(items) {
    const main = document.getElementById('mainContent');
    if (!main) return;

    let grid = document.getElementById('accountsList');
    if (!grid) {
        main.innerHTML = `<div id="accountsList" class="grid grid-cols-1 md:grid-cols-2 gap-3"></div>`;
        grid = document.getElementById('accountsList');
    }

    grid.innerHTML = '';
    items.forEach((acc, index) => {
        const letter = (acc.service_name || 'A').charAt(0).toUpperCase();
        const iconColor = iconColors[letter.charCodeAt(0) % iconColors.length];

        const card = document.createElement('div');
        card.className = 'bg-dark-card border border-dark-border rounded-2xl p-4 card-anim hover:border-primary/30 transition-all';
        card.style.animationDelay = `${Math.min(index * 0.05, 0.4)}s`;
        card.dataset.accountId = acc.id;
        card.dataset.service = (acc.service_name || '').toLowerCase();

        card.innerHTML = `
            <div class="flex items-center gap-3 mb-3">
                <div class="w-11 h-11 rounded-xl flex items-center justify-center font-bold text-lg flex-shrink-0"
                     style="background:${iconColor}18; border:1.5px solid ${iconColor}44;">
                    <span style="color:${iconColor};">${letter}</span>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="font-bold text-sm truncate">${escapeHtml(acc.service_name)}</h3>
                    <div class="cursor-pointer group flex items-center gap-1 mt-0.5 w-max max-w-full"
                         onclick="copyUserClipboard('${escapeJs(acc.username)}')"
                         title="Tap to copy username">
                        <span class="text-xs text-gray-400 group-hover:text-white transition-colors truncate account-username">
                            ${escapeHtml(acc.username)}
                        </span>
                        <span class="text-xs opacity-50 group-hover:opacity-100 transition-opacity">📋</span>
                    </div>
                </div>
                <div class="flex gap-0.5 flex-shrink-0">
                    <button onclick="editAccount(${acc.id})"
                            class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-500 hover:bg-white/5 hover:text-white transition-all text-sm">✏️</button>
                    <button onclick="deleteAccount(${acc.id})"
                            class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-500 hover:bg-red-900/20 hover:text-red-400 transition-all text-sm">🗑️</button>
                </div>
            </div>
            <div class="flex items-center gap-2 mb-3 px-3 py-2.5 bg-dark-bg/70 rounded-xl border border-dark-border/60">
                <span class="flex-1 pw-dots text-gray-500 tracking-widest password-masked password-display-${acc.id}">
                    ••••••••••••
                </span>
                <button onclick="togglePasswordVisibility(this)"
                        class="text-gray-500 hover:text-primary transition-colors flex-shrink-0 p-0.5" aria-label="Show password">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                </button>
            </div>
            <div class="flex gap-2">
                <button onclick="copyUserClipboard('${escapeJs(acc.username)}')"
                        class="tap-active flex-1 flex items-center justify-center gap-1.5 py-2.5 rounded-xl bg-dark-bg/70 border border-dark-border/60 text-gray-300 hover:border-primary/40 hover:text-white text-xs font-semibold transition-all">
                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    Copy User
                </button>
                <button onclick="triggerReveal(${acc.id}, this)"
                        class="tap-active flex-1 flex items-center justify-center gap-1.5 py-2.5 rounded-xl bg-primary/10 border border-primary/30 text-primary hover:bg-primary/20 text-xs font-bold transition-all">
                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                    Pass
                </button>
            </div>
            ${acc.website_url ? `
                <div class="mt-2.5 pt-2.5 border-t border-dark-border/40">
                    <a href="${escapeHtml(acc.website_url.startsWith('http') ? acc.website_url : 'https://' + acc.website_url)}" target="_blank" rel="noopener noreferrer"
                       class="text-[11px] text-gray-500 hover:text-primary flex items-center gap-1 truncate transition-colors">
                        <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        ${escapeHtml(acc.website_url)}
                    </a>
                </div>
            ` : ''}
        `;
        grid.appendChild(card);
    });

    const activeCountText = document.getElementById('activeCountText');
    if (activeCountText) {
        activeCountText.textContent = `${items.length} SECURE ASSET${items.length !== 1 ? 's' : ''} (LOCAL CACHE)`;
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"']/g, m => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[m]);
}

function escapeJs(str) {
    if (!str) return '';
    return String(str).replace(/['\\]/g, '\\$&');
}

// ── 4. Search Logic ───────────────────────────────────────
function searchVault(query) {
    const q = query.toLowerCase().trim();
    const cards = document.querySelectorAll('#accountsList [data-account-id]');
    let visibleCount = 0;

    cards.forEach(card => {
        const service = card.dataset.service || '';
        const usernameEl = card.querySelector('.account-username');
        const username = usernameEl ? usernameEl.textContent.toLowerCase() : '';

        if (service.includes(q) || username.includes(q)) {
            card.classList.remove('hidden');
            visibleCount++;
        } else {
            card.classList.add('hidden');
        }
    });

    const emptyMsg = document.getElementById('searchEmptyState');
    if (emptyMsg) {
        emptyMsg.classList.toggle('hidden', visibleCount > 0 || q === '');
    }
}

function showSearchOverlay() {
    const overlay = document.getElementById('searchOverlay');
    if (overlay) {
        overlay.classList.remove('hidden');
        setTimeout(() => {
            const input = document.getElementById('searchInput');
            if (input) input.focus();
        }, 100);
    }
}

function hideSearchOverlay() {
    const overlay = document.getElementById('searchOverlay');
    if (overlay) overlay.classList.add('hidden');
}

// ── 5. Add / Edit Account ─────────────────────────────────
function openAddAccountModal() {
    document.getElementById('accountId').value = '';
    document.getElementById('accountForm').reset();
    document.getElementById('accountPassword').value = '';
    document.getElementById('modalTitle').textContent = 'Add New Account';
    openModal('accountModal');
}

function openSettingsModal() {
    document.getElementById('settingsForm').reset();
    openModal('settingsModal');
}

function editAccount(accountId) {
    promptMasterKey('edit', accountId, null);
}

async function getEffectiveSalt() {
    if (typeof USER_SALT !== 'undefined' && USER_SALT) return USER_SALT;
    const cachedSalt = await VaultDB.getMeta('salt');
    return cachedSalt || '';
}

async function executeEditFetch(accountId, masterPassword) {
    let acc = null;
    const salt = await getEffectiveSalt();

    if (navigator.onLine) {
        try {
            const r = await fetch(`api/accounts.php?action=get_account&id=${accountId}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'get_account' })
            });
            const data = await r.json();
            if (data.success && data.data) {
                acc = data.data.account;
            }
        } catch (e) {
            console.warn('Online fetch failed, falling back to local DB:', e);
        }
    }

    if (!acc) {
        acc = await VaultDB.getItem(accountId);
    }

    if (!acc) {
        showAlert('Account not found in local or remote vault', 'error');
        return;
    }

    const key = await deriveKey(masterPassword, salt);
    const decryptedPassword = await decryptPasswordLocal(acc.password_encrypted, key);

    if (decryptedPassword === null) {
        showAlert('Incorrect Master Key', 'error');
        return;
    }

    document.getElementById('accountId').value        = acc.id;
    document.getElementById('serviceName').value      = acc.service_name;
    document.getElementById('category').value         = acc.category_id || 0;
    document.getElementById('accountUsername').value  = acc.username;
    document.getElementById('accountPassword').value  = decryptedPassword;
    document.getElementById('website').value          = acc.website_url || '';
    document.getElementById('notes').value            = acc.notes || '';
    document.getElementById('masterPassword').value   = masterPassword;
    document.getElementById('modalTitle').textContent = 'Edit Account';

    openModal('accountModal');
}

async function handleAccountSubmit(event) {
    event.preventDefault();

    const accountId    = document.getElementById('accountId').value;
    const serviceName  = document.getElementById('serviceName').value.trim();
    const categoryId   = document.getElementById('category').value;
    const username     = document.getElementById('accountUsername').value.trim();
    const password     = document.getElementById('accountPassword').value;
    const website      = document.getElementById('website').value.trim();
    const notes        = document.getElementById('notes').value.trim();
    const masterPass   = document.getElementById('masterPassword').value;

    if (!masterPass) { showAlert('Master Key is required', 'error'); return; }

    const isEdit   = !!accountId;
    const endpoint = isEdit ? 'api/accounts.php?action=update' : 'api/accounts.php?action=add_account';
    const salt     = await getEffectiveSalt();

    try {
        const key = await deriveKey(masterPass, salt);
        const encryptedPassword = await encryptPasswordLocal(password, key);

        if (!navigator.onLine) {
            showAlert('Cannot save new accounts while offline. Please reconnect.', 'warning');
            return;
        }

        const body = new URLSearchParams({
            service_name:  serviceName,
            category_id:   categoryId,
            username:      username,
            encrypted_password: encryptedPassword,
            website_url:   website,
            notes:         notes
        });
        if (isEdit) body.append('id', accountId);

        const response = await fetch(endpoint, { method: 'POST', body });
        const data = await response.json();

        if (data.success) {
            showAlert(isEdit ? 'Account updated!' : 'Account added!', 'success');
            closeModal('accountModal');
            // Refresh local cache and UI
            await syncVaultFromNetwork();
            setTimeout(() => location.reload(), 800);
        } else {
            showAlert(data.message || 'Failed to save account', 'error');
        }
    } catch (e) {
        showAlert('Encryption or network error', 'error');
    }
}

// ── 6. Master Key Prompt Modal Logic ──────────────────────
let activeMasterKeyAction    = null;
let activeMasterKeyAccountId = null;
let activeMasterKeyButton    = null;

function promptMasterKey(action, accountId, btn) {
    activeMasterKeyAction    = action;
    activeMasterKeyAccountId = accountId;
    activeMasterKeyButton    = btn;

    const modalMsg = document.getElementById('masterKeyModalMessage');
    const submitBtn = document.getElementById('masterKeySubmitBtn');
    document.getElementById('promptMasterKey').value = '';

    if (action === 'reveal') {
        modalMsg.textContent = 'Enter Master Key to decrypt credentials.';
        submitBtn.textContent = 'Decrypt';
        submitBtn.className   = 'flex-1 px-4 py-3 bg-primary hover:bg-primary-dark text-dark-bg font-bold rounded-xl transition-all';
    } else if (action === 'edit') {
        modalMsg.textContent = 'Enter Master Key to unlock account for editing.';
        submitBtn.textContent = 'Unlock & Edit';
        submitBtn.className   = 'flex-1 px-4 py-3 bg-primary hover:bg-primary-dark text-dark-bg font-bold rounded-xl transition-all';
    } else if (action === 'delete') {
        modalMsg.textContent = 'Confirm deletion with your Master Key.';
        submitBtn.textContent = 'Confirm Delete';
        submitBtn.className   = 'flex-1 px-4 py-3 bg-red-600 hover:bg-red-700 text-white font-bold rounded-xl transition-all';
    }

    openModal('masterKeyModal');
    setTimeout(() => document.getElementById('promptMasterKey').focus(), 120);
}

function handleMasterKeySubmit(e) {
    e.preventDefault();
    const masterPassword = document.getElementById('promptMasterKey').value;
    if (!masterPassword) return;

    closeModal('masterKeyModal');

    if (activeMasterKeyAction === 'reveal') {
        executeRevealPassword(activeMasterKeyAccountId, activeMasterKeyButton, masterPassword);
    } else if (activeMasterKeyAction === 'edit') {
        executeEditFetch(activeMasterKeyAccountId, masterPassword);
    } else if (activeMasterKeyAction === 'delete') {
        executeDeleteAccount(activeMasterKeyAccountId, masterPassword);
    }
}

// ── 7. Password Reveal + Offline Decryption ────────────────
const revealTimers = {};

async function executeRevealPassword(accountId, btn, masterPassword) {
    const displayEl = document.querySelector(`.password-display-${accountId}`);
    if (!displayEl) return;

    if (!displayEl.classList.contains('password-masked')) {
        hidePasswordElement(accountId, displayEl);
        return;
    }

    let encryptedPassword = null;
    let isOfflineDecrypted = false;
    const salt = await getEffectiveSalt();

    // 1. Try fetching live from server
    if (navigator.onLine) {
        try {
            const r = await fetch('api/accounts.php?action=reveal', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'reveal', id: accountId })
            });
            const data = await r.json();
            if (data.success && data.data) {
                encryptedPassword = data.data.password_encrypted;
            }
        } catch (e) {
            console.warn('Online reveal failed, reading from local DB:', e);
        }
    }

    // 2. Fallback to local IndexedDB
    if (!encryptedPassword) {
        const localItem = await VaultDB.getItem(accountId);
        if (localItem && localItem.password_encrypted) {
            encryptedPassword = localItem.password_encrypted;
            isOfflineDecrypted = true;
        }
    }

    if (!encryptedPassword) {
        showAlert('Could not retrieve encrypted password. Please connect online.', 'error');
        return;
    }

    try {
        const key = await deriveKey(masterPassword, salt);
        const decryptedPassword = await decryptPasswordLocal(encryptedPassword, key);

        if (decryptedPassword === null) {
            showAlert('Incorrect Master Key', 'error');
            return;
        }

        displayEl.textContent = decryptedPassword;
        displayEl.classList.remove('password-masked', 'pw-dots');
        displayEl.style.color = '#00E676';
        displayEl.style.letterSpacing = 'normal';
        displayEl.style.fontSize = '13px';

        if (revealTimers[accountId]) clearTimeout(revealTimers[accountId]);
        revealTimers[accountId] = setTimeout(() => {
            hidePasswordElement(accountId, displayEl);
        }, 30000);

        showAlert(isOfflineDecrypted ? 'Password decrypted offline 🔒 (Auto-hides in 30s)' : 'Password revealed (Auto-hides in 30s)', 'success');
    } catch (err) {
        showAlert('Decryption failed. Verify master key.', 'error');
    }
}

function hidePasswordElement(accountId, displayEl) {
    if (!displayEl) displayEl = document.querySelector(`.password-display-${accountId}`);
    if (!displayEl) return;

    displayEl.textContent = '••••••••••••';
    displayEl.classList.add('password-masked', 'pw-dots');
    displayEl.style.color = '';
    displayEl.style.letterSpacing = '';
    displayEl.style.fontSize = '';

    if (revealTimers[accountId]) {
        clearTimeout(revealTimers[accountId]);
        delete revealTimers[accountId];
    }
}

function togglePasswordVisibility(btn) {
    const card = btn.closest('[data-account-id]');
    const accountId = card?.dataset.accountId;
    if (!accountId) return;
    promptMasterKey('reveal', accountId, btn);
}

function triggerReveal(accountId, btn) {
    promptMasterKey('reveal', accountId, btn);
}

// ── 8. Delete Account ─────────────────────────────────────
function deleteAccount(accountId) {
    promptMasterKey('delete', accountId, null);
}

async function executeDeleteAccount(accountId, masterPassword) {
    if (!navigator.onLine) {
        showAlert('Cannot delete accounts in offline mode. Please reconnect.', 'warning');
        return;
    }

    try {
        const response = await fetch('api/accounts.php?action=delete_account', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'delete_account', id: accountId })
        });
        const data = await response.json();
        if (data.success) {
            await VaultDB.deleteItem(accountId);
            showAlert('Account deleted', 'success');
            const card = document.querySelector(`[data-account-id="${accountId}"]`);
            if (card) {
                card.style.transition = 'opacity .3s, transform .3s';
                card.style.opacity = '0';
                card.style.transform = 'scale(0.95)';
                setTimeout(() => { card.remove(); updateVaultCount(); }, 320);
            }
        } else {
            showAlert(data.message || 'Deletion failed', 'error');
        }
    } catch (e) {
        showAlert('Network error while deleting', 'error');
    }
}

function updateVaultCount() {
    const remaining = document.querySelectorAll('#accountsList [data-account-id]').length;
    const activeCountText = document.getElementById('activeCountText');
    if (activeCountText) {
        activeCountText.textContent = `${remaining} SECURE ASSET${remaining !== 1 ? 's' : ''} ACTIVE`;
    }
    if (remaining === 0) location.reload();
}

// ── 9. Modals & UI Helpers ────────────────────────────────
function openModal(id) {
    const el = document.getElementById(id);
    if (el) {
        el.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (el) {
        el.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

function executeLogout() {
    fetch('api/auth.php?action=logout').then(() => window.location.href = 'index.php');
}

function logout() {
    openModal('logoutModal');
}

function switchTab(tab) {
    if (tab === 'vault') window.location.href = 'dashboard.php';
    else if (tab === 'search') showSearchOverlay();
    else if (tab === 'add') openAddAccountModal();
    else if (tab === 'settings') openSettingsModal();
}

function copyUserClipboard(text) {
    if (!navigator.clipboard) {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); showAlert('Username copied to clipboard!', 'success'); }
        catch { showAlert('Copy failed', 'error'); }
        document.body.removeChild(ta);
        return;
    }
    navigator.clipboard.writeText(text)
        .then(() => showAlert('Username copied to clipboard!', 'success'))
        .catch(() => showAlert('Copy failed', 'error'));
}

function handleSettingsSubmit(event) {
    event.preventDefault();
    const oldKey = document.getElementById('oldPassword').value;
    const newKey = document.getElementById('newPassword').value;
    const confirmKey = document.getElementById('confirmNewPassword').value;
    if (newKey !== confirmKey) { showAlert('New master keys do not match', 'error'); return; }

    fetch('api/auth.php?action=update_master_password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'update_master_password', old_password: oldKey, new_password: newKey })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showAlert('Master Key updated successfully!', 'success');
            closeModal('settingsModal');
        } else {
            showAlert(data.message || 'Update failed', 'error');
        }
    })
    .catch(() => showAlert('Network error', 'error'));
}

// ── 10. Listeners & Initialization ─────────────────────────
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        const overlay = document.getElementById('searchOverlay');
        if (overlay && !overlay.classList.contains('hidden')) { hideSearchOverlay(); return; }

        ['masterKeyModal','accountModal','settingsModal','logoutModal','aboutModal'].forEach(id => {
            const el = document.getElementById(id);
            if (el && !el.classList.contains('hidden')) closeModal(id);
        });
    }
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        showSearchOverlay();
    }
});

// Network status listeners
window.addEventListener('online', () => {
    updateStatusPill(true);
    syncVaultFromNetwork();
    showAlert('🟢 Reconnected to network. Vault synced!', 'success');
});

window.addEventListener('offline', () => {
    updateStatusPill(false);
    showAlert('🟡 Offline Mode: Your encrypted local vault is active.', 'warning');
});

// On load initialization
window.addEventListener('DOMContentLoaded', () => {
    updateStatusPill(navigator.onLine);
    syncVaultFromNetwork();
});
