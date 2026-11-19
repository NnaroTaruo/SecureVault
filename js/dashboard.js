// ==============================================================================
// SecureVault — Vault Sync, CRUD Actions, Reveal, Key Rotation & Boot
// ==============================================================================

let masterPasswordChangeInProgress = false;
let accountSaveInProgress = false;
let activeMasterKeyAction = null;
let activeMasterKeyAccountId = null;
let activeMasterKeyButton = null;
const revealTimers = {};

const VAULT_OWNER = Object.freeze(USER_ID ? { userId: USER_ID, context: KEY_CHECK_CONTEXT } : VaultDevice.active());
VaultDB.owner = VAULT_OWNER;
const EMPTY_VAULT_HTML = document.getElementById('mainContent').innerHTML;

function vaultHeaders(extra = {}) {
    return { ...extra, 'X-Vault-Context': VAULT_OWNER?.context || '' };
}

// ── 1. Vault Synchronization & Offline Fallback ─────────────

async function syncVaultFromNetwork() {
    if (masterPasswordChangeInProgress || isVaultLocked || !VaultDevice.matches(VAULT_OWNER)) return false;
    const synchronize = async () => {
        if (masterPasswordChangeInProgress || isVaultLocked || !VaultDevice.matches(VAULT_OWNER)) return false;
        if (!navigator.onLine) {
            updateStatusPill(false);
            await checkRenderOfflineVault();
            return false;
        }
        try {
            const response = await fetch('api/accounts.php?action=sync_vault', {
                method: 'GET', cache: 'no-store', headers: vaultHeaders({ 'Cache-Control': 'no-cache' })
            });
            const data = await response.json();
            if (!VaultDevice.matches(VAULT_OWNER) || isVaultLocked) return false;
            if (response.status === 401 || response.status === 403) { discardVaultView(); return false; }
            if (response.ok && data.success && Array.isArray(data.data?.accounts) && data.data.salt &&
                data.data.user_id === VAULT_OWNER.userId && data.data.context === VAULT_OWNER.context) {
                if (!await VaultDB.saveAll(data.data.accounts, {
                    salt: data.data.salt,
                    categories: data.data.categories || [],
                    user: data.data.user || {},
                    synced_at: data.data.synced_at || new Date().toISOString(),
                    rotation_pending: false
                })) throw new Error('Offline storage is unavailable');
                updateStatusPill(true);
                renderVaultSnapshot(data.data.accounts, data.data.categories || [], data.data.user);
                return true;
            }
        } catch (err) {
            console.warn('Network sync failed, using offline vault:', err);
        }
        updateStatusPill(false);
        await checkRenderOfflineVault();
        return false;
    };
    // Serialize cache replacement across tabs, including an entire key change.
    return navigator.locks ? navigator.locks.request(VAULT_CACHE_LOCK, synchronize) : synchronize();
}

async function checkRenderOfflineVault() {
    if (!VaultDevice.matches(VAULT_OWNER) || isVaultLocked) return;
    try {
        renderVaultSnapshot(
            await VaultDB.getAll(),
            await VaultDB.getMeta('categories') || [],
            await VaultDB.getMeta('user') || {}
        );
    } catch {
        discardVaultView();
    }
}

// ── 2. Key Verification Helpers ─────────────────────────────

async function getEffectiveSalt() {
    if (!VaultDevice.matches(VAULT_OWNER) || isVaultLocked) return null;
    if (await VaultDB.getMeta('rotation_pending')) {
        showAlert('Reconnect and sign in online to refresh your vault before revealing or saving passwords.', 'warning');
        return null;
    }
    if (typeof USER_SALT !== 'undefined' && USER_SALT) return USER_SALT;
    const cachedSalt = await VaultDB.getMeta('salt');
    return cachedSalt || '';
}

async function verifyVaultActionKey(password) {
    if (isVaultLocked || masterPasswordChangeInProgress) return null;
    try {
        const salt = await getEffectiveSalt();
        if (salt === null) return null;
        const key = await getVerifiedVaultKey(password, salt, VAULT_OWNER?.context || '');
        return isVaultLocked || masterPasswordChangeInProgress || !VaultDevice.matches(VAULT_OWNER) ? null : key;
    } catch (error) {
        showAlert(error.message || 'Could not verify your master key. Sign in online again.', 'error');
        return null;
    }
}

// ── 3. Add / Edit / Delete Account Actions ──────────────────

function editAccount(accountId) {
    promptMasterKey('edit', accountId, null);
}

async function executeEditFetch(accountId, masterPassword) {
    if (isVaultLocked || !VaultDevice.matches(VAULT_OWNER)) return;
    let acc = null;
    const salt = await getEffectiveSalt();
    if (salt === null) return;

    if (navigator.onLine) {
        try {
            const r = await fetch(`api/accounts.php?action=get_account&id=${accountId}`, {
                method: 'POST',
                headers: vaultHeaders({ 'Content-Type': 'application/x-www-form-urlencoded' }),
                body: new URLSearchParams({ action: 'get_account' })
            });
            const data = await r.json();
            if (data.success && data.data) acc = data.data.account;
        } catch (e) {
            console.warn('Online fetch failed, falling back to local DB:', e);
        }
    }

    if (!acc) {
        try { acc = await VaultDB.getItem(accountId); } catch { return; }
    }
    if (!acc) {
        showAlert('Account not found in local or remote vault', 'error');
        return;
    }

    const key = await deriveKey(masterPassword, salt);
    const decryptedPassword = await decryptPasswordLocal(acc.password_encrypted, key);
    if (isVaultLocked || !VaultDevice.matches(VAULT_OWNER)) return;

    if (decryptedPassword === null) {
        showAlert('Incorrect Master Key', 'error');
        return;
    }

    document.getElementById('accountId').value        = acc.id;
    document.getElementById('serviceName').value      = acc.service_name;
    document.getElementById('category').value         = acc.category_id || 0;
    document.getElementById('accountUsername').value  = acc.username;

    const pwInput = document.getElementById('accountPassword');
    if (pwInput) {
        pwInput.value = decryptedPassword;
        pwInput.type = 'password';
    }

    document.getElementById('website').value          = acc.website_url || '';
    document.getElementById('notes').value            = acc.notes || '';
    document.getElementById('masterPassword').value   = masterPassword;
    document.getElementById('modalTitle').textContent = 'Edit Account';

    document.getElementById('passwordGeneratorPanel')?.classList.add('hidden');
    const toggleBtn = document.getElementById('toggleGenBtn');
    if (toggleBtn) {
        toggleBtn.classList.remove('bg-primary', 'text-dark-bg');
        toggleBtn.classList.add('text-primary', 'bg-primary/10');
    }
    currentGeneratedPassword = '';
    openModal('accountModal');
}

async function handleAccountSubmit(event) {
    event.preventDefault();
    if (accountSaveInProgress || isVaultLocked || masterPasswordChangeInProgress) return;
    if (!navigator.onLine) {
        showAlert('Cannot save accounts while offline. Please reconnect.', 'warning');
        return;
    }

    const accountId   = document.getElementById('accountId').value;
    const serviceName = document.getElementById('serviceName').value.trim();
    const categoryId  = document.getElementById('category').value;
    const username    = document.getElementById('accountUsername').value.trim();
    const password    = document.getElementById('accountPassword').value;
    const website     = document.getElementById('website').value.trim();
    const notes       = document.getElementById('notes').value.trim();
    const masterPass  = document.getElementById('masterPassword').value;

    if (!masterPass) { showAlert('Master Key is required', 'error'); return; }

    const isEdit   = !!accountId;
    const endpoint = isEdit ? 'api/accounts.php?action=update' : 'api/accounts.php?action=add_account';
    const form     = document.getElementById('accountForm');
    const button   = form.querySelector('button[type=submit]');
    accountSaveInProgress = true;
    button.disabled = true;
    form.setAttribute('aria-busy', 'true');

    try {
        const key = await verifyVaultActionKey(masterPass);
        if (!key) return;
        const encryptedPassword = await encryptPasswordLocal(password, key);
        if (isVaultLocked || masterPasswordChangeInProgress || !VaultDevice.matches(VAULT_OWNER)) return;

        const body = new URLSearchParams({
            service_name: serviceName,
            category_id: categoryId,
            username: username,
            encrypted_password: encryptedPassword,
            website_url: website,
            notes: notes
        });
        if (isEdit) body.append('id', accountId);

        const response = await fetch(endpoint, { method: 'POST', headers: vaultHeaders({ 'X-CSRF-Token': CSRF_TOKEN }), body });
        const data = await response.json();
        if (!VaultDevice.matches(VAULT_OWNER) || isVaultLocked) return;

        if (data.success) {
            showAlert(isEdit ? 'Account updated!' : 'Account added!', 'success');
            closeModal('accountModal');
            await syncVaultFromNetwork();
            setTimeout(() => { if (VaultDevice.matches(VAULT_OWNER) && !isVaultLocked) location.reload(); }, 800);
        } else {
            showAlert(data.message || 'Failed to save account', 'error');
        }
    } catch {
        showAlert('Encryption or network error', 'error');
    } finally {
        accountSaveInProgress = false;
        button.disabled = false;
        form.removeAttribute('aria-busy');
    }
}

function deleteAccount(accountId) {
    promptMasterKey('delete', accountId, null);
}

async function executeDeleteAccount(accountId, masterPassword) {
    if (!navigator.onLine) {
        showAlert('Cannot delete accounts in offline mode. Please reconnect.', 'warning');
        return false;
    }

    try {
        if (!await verifyVaultActionKey(masterPassword)) return false;
        const response = await fetch('api/accounts.php?action=delete_account', {
            method: 'POST',
            headers: vaultHeaders({ 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': CSRF_TOKEN }),
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
            return true;
        }
        showAlert(data.message || 'Deletion failed', 'error');
    } catch {
        showAlert('Network error while deleting', 'error');
    }
    return false;
}

// ── 4. Master Key Prompt & Password Reveal ──────────────────

function promptMasterKey(action, accountId, btn) {
    activeMasterKeyAction    = action;
    activeMasterKeyAccountId = accountId;
    activeMasterKeyButton    = btn;

    const modalMsg = document.getElementById('masterKeyModalMessage');
    const submitBtn = document.getElementById('masterKeySubmitBtn');
    document.getElementById('promptMasterKey').value = '';
    document.getElementById('promptMasterKey').type = 'password';

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

async function handleMasterKeySubmit(e) {
    e.preventDefault();
    const masterPassword = document.getElementById('promptMasterKey').value;
    if (!masterPassword) return;

    if (activeMasterKeyAction === 'delete') {
        const button = document.getElementById('masterKeySubmitBtn');
        if (button.disabled) return;
        button.disabled = true;
        try {
            if (await executeDeleteAccount(activeMasterKeyAccountId, masterPassword)) closeModal('masterKeyModal');
            else {
                document.getElementById('promptMasterKey').value = '';
                document.getElementById('promptMasterKey').focus();
            }
        } finally {
            button.disabled = false;
        }
        return;
    }

    closeModal('masterKeyModal');
    if (activeMasterKeyAction === 'reveal') {
        executeRevealPassword(activeMasterKeyAccountId, activeMasterKeyButton, masterPassword);
    } else if (activeMasterKeyAction === 'edit') {
        executeEditFetch(activeMasterKeyAccountId, masterPassword);
    }
}

async function executeRevealPassword(accountId, btn, masterPassword) {
    if (isVaultLocked || !VaultDevice.matches(VAULT_OWNER)) return;
    const displayEl = document.querySelector(`.password-display-${accountId}`);
    if (!displayEl) return;

    if (!displayEl.classList.contains('password-masked')) {
        hidePasswordElement(accountId, displayEl);
        return;
    }

    let encryptedPassword = null;
    let isOfflineDecrypted = false;
    const salt = await getEffectiveSalt();
    if (salt === null) return;

    if (navigator.onLine) {
        try {
            const r = await fetch('api/accounts.php?action=reveal', {
                method: 'POST',
                headers: vaultHeaders({ 'Content-Type': 'application/x-www-form-urlencoded' }),
                body: new URLSearchParams({ action: 'reveal', id: accountId })
            });
            const data = await r.json();
            if (data.success && data.data) encryptedPassword = data.data.password_encrypted;
        } catch (e) {
            console.warn('Online reveal failed, reading from local DB:', e);
        }
    }

    if (!encryptedPassword) {
        let localItem;
        try { localItem = await VaultDB.getItem(accountId); } catch { return; }
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
        if (isVaultLocked || !VaultDevice.matches(VAULT_OWNER)) return;

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
        revealTimers[accountId] = setTimeout(() => hidePasswordElement(accountId, displayEl), 30000);

        showAlert(isOfflineDecrypted ? 'Password decrypted offline 🔒 (Auto-hides in 30s)' : 'Password revealed (Auto-hides in 30s)', 'success');
    } catch {
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
    const accountId = btn.closest('[data-account-id]')?.dataset.accountId;
    if (accountId) promptMasterKey('reveal', accountId, btn);
}

function triggerReveal(accountId, btn) {
    promptMasterKey('reveal', accountId, btn);
}

// ── 5. Logout & Transactional Master Key Rotation ───────────

async function executeLogout() {
    if (!VaultDevice.matches(VAULT_OWNER)) { discardVaultView(); return false; }
    const cleanup = VaultDevice.end(VAULT_OWNER);
    discardVaultView('Signing out and removing this device’s vault copy…');
    // Offline logout remains effective: the local revocation cookie blocks the old session on reconnect.
    const request = fetch('api/auth.php?action=logout', {
        method: 'POST', cache: 'no-store', keepalive: true,
        headers: { 'X-CSRF-Token': CSRF_TOKEN },
        signal: AbortSignal.timeout(10000)
    }).catch(() => {});

    try {
        await cleanup;
        await request;
        window.location.replace('index.php');
        return true;
    } catch {
        discardVaultView('Signed out, but browser cleanup could not finish. Close older SecureVault tabs and clear this site’s storage before sharing this browser.');
        return false;
    }
}

async function handleSettingsSubmit(event) {
    event.preventDefault();
    if (masterPasswordChangeInProgress || isVaultLocked || !VaultDevice.matches(VAULT_OWNER)) return;
    const oldKey = document.getElementById('oldPassword').value;
    const newKey = document.getElementById('newPassword').value;
    const confirmKey = document.getElementById('confirmNewPassword').value;
    if (newKey !== confirmKey) { showAlert('New master keys do not match', 'error'); return; }
    if (oldKey === newKey) { showAlert('Choose a different master key', 'error'); return; }
    if (new TextEncoder().encode(newKey).length > 72) { showAlert('Your new master key is too long. Try a shorter phrase.', 'error'); return; }
    if (!navigator.onLine) { showAlert('Cannot update master password while offline.', 'warning'); return; }
    if (!navigator.locks) { showAlert('Please use an updated browser to change your master key safely.', 'warning'); return; }

    const form     = document.getElementById('settingsForm');
    const status   = document.getElementById('keyChangeStatus');
    const recovery = document.getElementById('keyChangeRecovery');
    const button   = document.getElementById('settingsSubmitBtn');
    const controls = document.querySelectorAll('#settingsModal button, #settingsForm input');
    let requestStarted = false;
    let changed = false;

    masterPasswordChangeInProgress = true;
    controls.forEach(control => { control.disabled = true; });
    form.setAttribute('aria-busy', 'true');
    button.textContent = 'Updating saved passwords…';
    status.textContent = 'Updating your saved passwords. Keep this page open.';
    recovery.classList.add('hidden');

    try {
        await navigator.locks.request(VAULT_CACHE_LOCK, async () => {
            // Never rotate from a possibly incomplete or stale IndexedDB copy.
            const fresh = await fetch('api/accounts.php?action=sync_vault', {
                cache: 'no-store', redirect: 'error', signal: AbortSignal.timeout(30000), headers: vaultHeaders()
            });
            const snapshot = await fresh.json();
            if (!fresh.ok || !snapshot.success || !Array.isArray(snapshot.data?.accounts) || !snapshot.data.salt) {
                throw new Error('Could not load your current vault. Sign in online and try again.');
            }
            const vault = snapshot.data;
            if (!VaultDevice.matches(VAULT_OWNER) || vault.user_id !== VAULT_OWNER.userId || vault.context !== VAULT_OWNER.context) {
                throw new Error('Your sign-in changed. Nothing was changed.');
            }

            const oldCryptoKey = await deriveKey(oldKey, vault.salt);
            const newCryptoKey = await deriveKey(newKey, vault.salt);
            const updatedAccounts = [];
            for (const acc of vault.accounts) {
                const plaintext = await decryptPasswordLocal(acc.password_encrypted, oldCryptoKey);
                if (plaintext === null) {
                    throw new Error('Your current key could not open every entry. Nothing was changed.');
                }
                const newEncrypted = await encryptPasswordLocal(plaintext, newCryptoKey);
                updatedAccounts.push({
                    id: Number(acc.id),
                    previous_password_encrypted: acc.password_encrypted,
                    password_encrypted: newEncrypted
                });
            }

            const meta = { salt: vault.salt, categories: vault.categories || [], synced_at: vault.synced_at };
            // Persist a barrier before sending: a lost response must not leave a usable stale offline copy.
            try {
                if (!await VaultDB.saveAll(vault.accounts, { ...meta, rotation_pending: true })) throw new Error();
            } catch {
                throw new Error('Could not prepare offline storage. Nothing was changed. Enable browser storage and try again.');
            }

            requestStarted = true;
            const response = await fetch('api/auth.php?action=update_master_password', {
                method: 'POST', cache: 'no-store', redirect: 'error', signal: AbortSignal.timeout(30000),
                headers: vaultHeaders({ 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': CSRF_TOKEN }),
                body: new URLSearchParams({
                    old_password: oldKey,
                    new_password: newKey,
                    accounts: JSON.stringify(updatedAccounts)
                })
            });
            const data = await response.json();
            if (!response.ok || !data.success) {
                if (response.status >= 400 && response.status < 500 && data.success === false) {
                    requestStarted = false;
                    await VaultDB.saveAll(vault.accounts, { ...meta, rotation_pending: false });
                    throw new Error(data.message || 'The key change was rejected. Nothing was changed.');
                }
                throw new Error('The server could not confirm the key change.');
            }

            changed = true;
            if (!VaultDevice.matches(VAULT_OWNER)) return;
            clearVaultKeyCheck();
            let offlineRefreshFailed = false;
            try {
                const accounts = vault.accounts.map((acc, index) => ({
                    ...acc,
                    password_encrypted: updatedAccounts[index].password_encrypted
                }));
                if (!await VaultDB.saveAll(accounts, { ...meta, synced_at: new Date().toISOString(), rotation_pending: false })) {
                    throw new Error('Offline storage is unavailable');
                }
            } catch {
                offlineRefreshFailed = true;
            }
            form.reset();
            status.textContent = 'Master key changed. Sign in with your new key.';
            window.location.replace('login.php?key_changed=1' + (offlineRefreshFailed ? '&offline_refresh=1' : ''));
        });
    } catch (e) {
        status.textContent = requestStarted
            ? 'The result could not be confirmed. Sign in online with your new key, or your previous key if the change was not saved, to refresh offline access.'
            : (e.message || 'Could not start the key change. Nothing was changed.');
        recovery.classList.remove('hidden');
        showAlert(status.textContent, requestStarted ? 'warning' : 'error');
    } finally {
        if (!changed) {
            masterPasswordChangeInProgress = false;
            controls.forEach(control => { control.disabled = false; });
            form.removeAttribute('aria-busy');
            button.textContent = 'Update Key';
        }
    }
}

// ── 6. Listeners & Boot ─────────────────────────────────────

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        const overlay = document.getElementById('searchOverlay');
        if (overlay && !overlay.classList.contains('hidden')) { hideSearchOverlay(); return; }
        ['masterKeyModal', 'accountModal', 'settingsModal', 'logoutModal', 'aboutModal', 'updateNoticeModal'].forEach(id => {
            const el = document.getElementById(id);
            if (el && !el.classList.contains('hidden')) closeModal(id);
        });
    }
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        showSearchOverlay();
    }
});

VaultDevice.subscribe(() => { if (!VaultDevice.matches(VAULT_OWNER)) discardVaultView(); });
window.addEventListener('focus', () => { if (!VaultDevice.matches(VAULT_OWNER)) discardVaultView(); });
window.addEventListener('pagehide', () => { discardVaultView(); });
window.addEventListener('pageshow', event => { if (event.persisted) location.reload(); });

window.addEventListener('online', async () => {
    if (!VaultDevice.matches(VAULT_OWNER)) { discardVaultView(); return; }
    if (OFFLINE_SHELL) { location.replace('dashboard.php'); return; }
    updateStatusPill(true);
    const synced = await syncVaultFromNetwork();
    showAlert(synced ? 'Reconnected. Vault synced!' : 'Reconnected. Sign in online to refresh your vault.', synced ? 'success' : 'warning');
});

window.addEventListener('offline', () => {
    updateStatusPill(false);
    showAlert('🟡 Offline Mode: Your encrypted local vault is active.', 'warning');
});

window.addEventListener('DOMContentLoaded', () => {
    if (!VaultDevice.matches(VAULT_OWNER)) { discardVaultView(); return; }
    updateStatusPill(navigator.onLine);
    checkRenderOfflineVault();
    syncVaultFromNetwork();
    initInactivityTracker();

    if (!localStorage.getItem('seen_update_v1_3_enhanced')) {
        setTimeout(() => openModal('updateNoticeModal'), 500);
    }
});
