// PWA Dashboard Logic
const colors = {
    success: 'bg-green-900/40 border-green-500 text-green-300',
    error:   'bg-red-900/40 border-red-500 text-red-300',
    warning: 'bg-yellow-900/40 border-yellow-500 text-yellow-300',
    info:    'bg-blue-900/40 border-blue-500 text-blue-300'
};

function showAlert(message, type = 'info') {
    const alertBox = document.getElementById('alertArea');
    if (!alertBox) return;

    const el = document.createElement('div');
    el.className = `p-3.5 rounded-xl border-l-4 text-xs font-semibold
                      flex items-center gap-2 ${colors[type] || colors.info}`;
    el.style.cssText += 'animation: slideDown .25s ease-out;';

    const icons = { success:'✅', error:'❌', warning:'⚠️', info:'ℹ️' };
    el.innerHTML = `<span class="flex-shrink-0">${icons[type] || '🔔'}</span>
                    <span class="flex-1">${message}</span>
                    <button onclick="this.parentElement.remove()" class="flex-shrink-0 opacity-60 hover:opacity-100 text-lg leading-none">&times;</button>`;

    alertBox.appendChild(el);
    setTimeout(() => el.remove(), 4000);
}

// ── Search Logic (Fast & Robust) ──────────────────────────
function searchVault(query) {
    const q = query.toLowerCase().trim();
    const cards = document.querySelectorAll('.card-anim');
    
    cards.forEach(card => {
        const service = card.dataset.service || '';
        // Look for the username inside the card
        const usernameEl = card.querySelector('.account-username');
        const username = usernameEl ? usernameEl.textContent.toLowerCase() : '';
        
        if (service.includes(q) || username.includes(q)) {
            card.classList.remove('hidden');
        } else {
            card.classList.add('hidden');
        }
    });

    // Check if we should show an empty state if all cards are hidden
    const visibleCards = document.querySelectorAll('.card-anim:not(.hidden)').length;
    const emptyMsg = document.getElementById('searchEmptyState');
    if (emptyMsg) {
        emptyMsg.classList.toggle('hidden', visibleCards > 0 || q === '');
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
    if (overlay) {
        overlay.classList.add('hidden');
        // Optional: clear search when closing
        // searchVault(''); 
    }
}

// ── Add / Edit Account ────────────────────────────────────
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

function executeEditFetch(accountId, masterPassword) {
    fetch(`api/accounts.php?action=get_account&id=${accountId}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'get_account', master_password: masterPassword })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const acc = data.data.account;
            document.getElementById('accountId').value        = acc.id;
            document.getElementById('serviceName').value      = acc.service_name;
            document.getElementById('category').value         = acc.category_id || 0;
            document.getElementById('accountUsername').value  = acc.username;
            document.getElementById('accountPassword').value  = acc.password;
            document.getElementById('website').value          = acc.website_url;
            document.getElementById('notes').value            = acc.notes || '';
            document.getElementById('masterPassword').value   = masterPassword; 
            document.getElementById('modalTitle').textContent = 'Edit Account';

            openModal('accountModal');
        } else {
            showAlert(data.message || 'Failed to fetch account details', 'error');
        }
    })
    .catch(() => showAlert('Failed to connect to vault', 'error'));
}

function handleAccountSubmit(event) {
    event.preventDefault();

    const accountId    = document.getElementById('accountId').value;
    const serviceName  = document.getElementById('serviceName').value;
    const categoryId   = document.getElementById('category').value;
    const username     = document.getElementById('accountUsername').value;
    const password     = document.getElementById('accountPassword').value;
    const website      = document.getElementById('website').value;
    const notes        = document.getElementById('notes').value;
    const masterPass   = document.getElementById('masterPassword').value;

    if (!masterPass) { showAlert('Master Key is required', 'error'); return; }

    const isEdit   = !!accountId;
    const endpoint = isEdit ? 'api/accounts.php?action=update' : 'api/accounts.php?action=add_account';

    const body = new URLSearchParams({
        service_name:  serviceName,
        category_id:   categoryId,
        username:      username,
        password:      password,
        website_url:   website,
        notes:         notes,
        master_password: masterPass,
    });
    if (isEdit) body.append('id', accountId);

    fetch(endpoint, { method: 'POST', body })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showAlert(isEdit ? 'Account updated!' : 'Account added!', 'success');
                closeModal('accountModal');
                setTimeout(() => location.reload(), 900);
            } else {
                showAlert(data.message || 'Failed to save account', 'error');
            }
        })
        .catch(() => showAlert('Network error', 'error'));
}

// ── Master Key Prompt ─────────────────────────────────────
let activeMasterKeyAction  = null;
let activeMasterKeyAccountId = null;
let activeMasterKeyButton  = null;

function promptMasterKey(action, accountId, btn) {
    activeMasterKeyAction  = action;
    activeMasterKeyAccountId = accountId;
    activeMasterKeyButton  = btn;

    const modalMsg = document.getElementById('masterKeyModalMessage');
    const submitBtn = document.getElementById('masterKeySubmitBtn');
    document.getElementById('promptMasterKey').value = '';

    if (action === 'reveal') {
        modalMsg.textContent = 'Enter Master Key to decrypt password.';
        submitBtn.textContent = 'Decrypt';
        submitBtn.className    = 'flex-1 px-4 py-3 bg-primary hover:bg-primary-dark text-dark-bg font-bold rounded-xl transition-all';
    } else if (action === 'edit') {
        modalMsg.textContent = 'Enter Master Key to unlock account for editing.';
        submitBtn.textContent = 'Unlock & Edit';
        submitBtn.className    = 'flex-1 px-4 py-3 bg-primary hover:bg-primary-dark text-dark-bg font-bold rounded-xl transition-all';
    } else if (action === 'delete') {
        modalMsg.textContent = 'Are you sure? Enter Master Key to confirm deletion.';
        submitBtn.textContent = 'Confirm Delete';
        submitBtn.className    = 'flex-1 px-4 py-3 bg-red-600 hover:bg-red-700 text-white font-bold rounded-xl transition-all';
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

// ── Password Reveal + Auto-hide ───────────────────────────
const revealTimers = {};

function executeRevealPassword(accountId, btn, masterPassword) {
    const displayEl = document.querySelector(`.password-display-${accountId}`);
    if (!displayEl) return;

    if (!displayEl.classList.contains('password-masked')) {
        hidePasswordElement(accountId, displayEl);
        return;
    }

    fetch('api/accounts.php?action=reveal', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:   new URLSearchParams({ action: 'reveal', id: accountId, master_password: masterPassword }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            displayEl.textContent = data.data.password;
            displayEl.classList.remove('password-masked', 'pw-dots');
            displayEl.style.color = '#00E676';
            displayEl.style.letterSpacing = 'normal';
            displayEl.style.fontSize      = '13px';

            if (revealTimers[accountId]) clearTimeout(revealTimers[accountId]);

            revealTimers[accountId] = setTimeout(() => {
                hidePasswordElement(accountId, displayEl);
            }, 30000);
            
            showAlert('Password revealed (Auto-hiding in 30s)', 'success');
        } else {
            showAlert(data.message || 'Wrong master key', 'error');
        }
    })
    .catch(() => showAlert('Failed to decrypt password', 'error'));
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
    const card      = btn.closest('[data-account-id]');
    const accountId = card?.dataset.accountId;
    if (!accountId) return;
    promptMasterKey('reveal', accountId, btn);
}

// ── Delete Account ────────────────────────────────────────
function deleteAccount(accountId) {
    promptMasterKey('delete', accountId, null);
}

function executeDeleteAccount(accountId, masterPassword) {
    fetch('api/accounts.php?action=delete_account', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:   new URLSearchParams({ action: 'delete_account', id: accountId, master_password: masterPassword }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showAlert('Account deleted', 'success');
            const card = document.querySelector(`[data-account-id="${accountId}"]`);
            if (card) {
                card.style.transition = 'opacity .3s, transform .3s';
                card.style.opacity    = '0';
                card.style.transform  = 'scale(0.95)';
                setTimeout(() => { card.remove(); updateVaultCount(); }, 320);
            }
        } else {
            showAlert(data.message || 'Wrong master key', 'error');
        }
    })
    .catch(() => showAlert('Failed to delete account', 'error'));
}

function updateVaultCount() {
    const remaining = document.querySelectorAll('#accountsList [data-account-id]').length;
    if (remaining === 0) location.reload();
}

// ── Miscellaneous ─────────────────────────────────────────
function openModal(id) {
    document.getElementById(id).classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
    document.body.style.overflow = 'auto';
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
        try { document.execCommand('copy'); showAlert('Username copied!', 'success'); }
        catch { showAlert('Copy failed', 'error'); }
        document.body.removeChild(ta);
        return;
    }
    navigator.clipboard.writeText(text)
        .then(() => showAlert('Username copied!', 'success'))
        .catch(() => showAlert('Copy failed', 'error'));
}

function handleSettingsSubmit(event) {
    event.preventDefault();
    const oldKey = document.getElementById('oldPassword').value;
    const newKey = document.getElementById('newPassword').value;
    const confirmKey = document.getElementById('confirmNewPassword').value;
    if (newKey !== confirmKey) { showAlert('New keys do not match', 'error'); return; }

    fetch('api/auth.php?action=update_master_password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'update_master_password', old_password: oldKey, new_password: newKey })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showAlert('Master Key updated!', 'success');
            closeModal('settingsModal');
        } else {
            showAlert(data.message || 'Update failed', 'error');
        }
    })
    .catch(() => showAlert('Network error', 'error'));
}

// ── Keyboard shortcuts ────────────────────────────────────
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        const overlay = document.getElementById('searchOverlay');
        if (overlay && !overlay.classList.contains('hidden')) { hideSearchOverlay(); return; }

        ['masterKeyModal','accountModal','settingsModal','logoutModal'].forEach(id => {
            const el = document.getElementById(id);
            if (el && !el.classList.contains('hidden')) closeModal(id);
        });
    }
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        showSearchOverlay();
    }
});

// ── Dynamic CSS ───────────────────────────────────────────
const style = document.createElement('style');
style.textContent = `
    @keyframes slideDown {
        from { opacity:0; transform: translateY(-8px); }
        to   { opacity:1; transform: translateY(0);    }
    }
    .animate-fade-in { animation: slideDown .3s ease-out; }
`;
document.head.appendChild(style);
