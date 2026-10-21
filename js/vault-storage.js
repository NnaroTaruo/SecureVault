// One device copy, bound to one sign-in. Switching accounts replaces that copy.
const VAULT_SCOPE = new URL('.', location.href).pathname;
const VAULT_CACHE_LOCK = 'securevault-vault-cache:' + VAULT_SCOPE;
const VAULT_DEVICE_KEY = 'securevault-device:' + VAULT_SCOPE;
let vaultChannel;
try { vaultChannel = new BroadcastChannel(VAULT_DEVICE_KEY); } catch {}

const VaultDevice = {
    read() {
        try { return JSON.parse(localStorage.getItem(VAULT_DEVICE_KEY)); } catch { return null; }
    },
    signedOut() { return document.cookie.split(';').some(cookie => cookie.trim() === 'sv_signed_out=1'); },
    active() {
        const state = this.read();
        return !this.signedOut() && state?.ready && Number.isInteger(state.userId) && state.userId > 0 ? state : null;
    },
    matches(owner, preparing = false) {
        const state = this.read();
        return !!(owner && !this.signedOut() && state && state.userId === owner.userId && state.context === owner.context && (state.ready || preparing));
    },
    cookie(value) {
        document.cookie = 'sv_signed_out=' + value + '; Path=' + VAULT_SCOPE + '; SameSite=Strict' + (location.protocol === 'https:' ? '; Secure' : '') + (value ? '; Max-Age=31536000' : '; Max-Age=0');
    },
    announce() { vaultChannel?.postMessage('changed'); },
    subscribe(callback) {
        window.addEventListener('storage', event => { if (event.key === VAULT_DEVICE_KEY || event.key === null) callback(); });
        if (vaultChannel) vaultChannel.onmessage = callback;
    },
    lock(action) { return navigator.locks ? navigator.locks.request(VAULT_CACHE_LOCK, action) : action(); },
    async start(userId, context) {
        const owner = { userId: Number(userId), context, ready: false };
        if (!Number.isInteger(owner.userId) || owner.userId < 1 || !/^[a-f0-9]{32}$/.test(context)) throw new Error('Invalid sign-in');
        this.cookie('');
        localStorage.setItem(VAULT_DEVICE_KEY, JSON.stringify(owner));
        this.announce();
        await this.lock(async () => {
            if (!this.matches(owner, true)) throw new Error('Sign-in changed');
            await VaultDB.removeLegacy();
            await VaultDB.reset(owner, () => this.matches(owner, true));
            if (!this.matches(owner, true)) throw new Error('Sign-in changed');
            localStorage.setItem(VAULT_DEVICE_KEY, JSON.stringify({ ...owner, ready: true }));
        });
        this.announce();
    },
    async end(owner) {
        if (!this.matches(owner)) return false;
        // Revoke synchronously before waiting on requests, other tabs, or IndexedDB.
        this.cookie('1');
        const tombstone = { userId: 0, context: owner.context, ready: false };
        let stored = false;
        try { localStorage.setItem(VAULT_DEVICE_KEY, JSON.stringify(tombstone)); stored = true; } catch {}
        clearVaultKeyCheck();
        this.announce();
        await this.lock(async () => {
            const stillSignedOut = () => this.signedOut() && (!stored || (this.read()?.userId === 0 && this.read()?.context === owner.context));
            if (stillSignedOut()) await VaultDB.reset(null, stillSignedOut);
        });
        await VaultDB.removeLegacy();
        await clearLegacyVaultCaches();
        return true;
    }
};

async function clearLegacyVaultCaches() {
    if (!('caches' in window)) return;
    for (const name of await caches.keys()) {
        if (!/^securevault-v\d+$/.test(name)) continue;
        const cache = await caches.open(name);
        for (const request of await cache.keys()) {
            const url = new URL(request.url);
            if (url.origin === location.origin && url.pathname.startsWith(VAULT_SCOPE)) await cache.delete(request);
        }
        if (!(await cache.keys()).length) await caches.delete(name);
    }
}

const VaultDB = {
    DB_NAME: 'SecureVaultDB-v2:' + VAULT_SCOPE,
    DB_VERSION: 1,
    db: null,
    owner: null,
    async open() {
        if (this.db) return this.db;
        return new Promise((resolve, reject) => {
            if (!('indexedDB' in window)) return reject(new Error('Browser storage is unavailable'));
            const request = indexedDB.open(this.DB_NAME, this.DB_VERSION);
            request.onupgradeneeded = () => {
                request.result.createObjectStore('vault_items', { keyPath: 'id' });
                request.result.createObjectStore('vault_meta', { keyPath: 'key' });
            };
            request.onsuccess = () => {
                this.db = request.result;
                this.db.onversionchange = () => { this.db.close(); this.db = null; };
                resolve(this.db);
            };
            request.onerror = () => reject(request.error);
        });
    },
    async removeLegacy() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.deleteDatabase('SecureVaultDB');
            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
            request.onblocked = () => reject(new Error('Close older SecureVault tabs and sign in again'));
        });
    },
    async reset(owner, allowed) {
        const db = await this.open();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(['vault_items', 'vault_meta'], 'readwrite');
            if (!allowed()) { tx.abort(); return reject(new Error('Sign-in changed')); }
            tx.objectStore('vault_items').clear();
            tx.objectStore('vault_meta').clear();
            if (owner) tx.objectStore('vault_meta').put({ key: 'owner', value: { userId: owner.userId, context: owner.context } });
            tx.oncomplete = () => resolve(true);
            tx.onerror = tx.onabort = () => reject(tx.error || new Error('Browser storage cleanup was interrupted'));
        });
    },
    async read(store, method, argument) {
        const owner = this.owner;
        const db = await this.open();
        if (!VaultDevice.matches(owner)) throw new Error('Sign-in changed');
        return new Promise((resolve, reject) => {
            const tx = db.transaction(['vault_items', 'vault_meta'], 'readonly');
            const check = tx.objectStore('vault_meta').get('owner');
            check.onsuccess = () => {
                const saved = check.result?.value;
                if (!VaultDevice.matches(owner) || saved?.userId !== owner.userId || saved.context !== owner.context) {
                    tx.abort(); return;
                }
                const request = tx.objectStore(store)[method](argument);
                request.onsuccess = () => VaultDevice.matches(owner) ? resolve(request.result) : reject(new Error('Sign-in changed'));
            };
            tx.onerror = tx.onabort = () => reject(tx.error || new Error('Sign-in changed'));
        });
    },
    async write(change) {
        const owner = this.owner;
        const db = await this.open();
        if (!VaultDevice.matches(owner)) throw new Error('Sign-in changed');
        return new Promise((resolve, reject) => {
            const tx = db.transaction(['vault_items', 'vault_meta'], 'readwrite');
            const items = tx.objectStore('vault_items');
            const meta = tx.objectStore('vault_meta');
            const check = meta.get('owner');
            check.onsuccess = () => {
                const saved = check.result?.value;
                if (!VaultDevice.matches(owner) || saved?.userId !== owner.userId || saved.context !== owner.context) { tx.abort(); return; }
                change(items, meta);
            };
            tx.oncomplete = () => VaultDevice.matches(owner) ? resolve(true) : reject(new Error('Sign-in changed'));
            tx.onerror = tx.onabort = () => reject(tx.error || new Error('Offline storage update was interrupted'));
        });
    },
    saveAll(accounts, meta = {}) {
        return this.write((items, metadata) => {
            items.clear();
            accounts.forEach(account => items.put({ ...account, id: Number(account.id) }));
            for (const [key, value] of Object.entries(meta)) if (key !== 'owner') metadata.put({ key, value });
        });
    },
    async getAll() { return await this.read('vault_items', 'getAll') || []; },
    async getItem(id) { return await this.read('vault_items', 'get', Number(id)) || null; },
    async getMeta(key) { return (await this.read('vault_meta', 'get', key))?.value ?? null; },
    putItem(item) { return this.write(items => items.put({ ...item, id: Number(item.id) })); },
    deleteItem(id) { return this.write(items => items.delete(Number(id))); }
};
