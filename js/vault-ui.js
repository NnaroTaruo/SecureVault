// ==============================================================================
// SecureVault — Dashboard UI Helpers, Modals, Search & Card Rendering
// ==============================================================================

const alertStyles = {
    success: 'bg-green-900/40 border-green-500 text-green-300',
    error:   'bg-red-900/40 border-red-500 text-red-300',
    warning: 'bg-yellow-900/40 border-yellow-500 text-yellow-300',
    info:    'bg-blue-900/40 border-blue-500 text-blue-300'
};

const iconColors = ['#FF6B6B', '#FF8E53', '#FFC300', '#2ECC71', '#00E676', '#3498DB', '#9B59B6', '#E91E63', '#FF5722', '#00BCD4'];

function showAlert(message, type = 'info') {
    const alertBox = document.getElementById('alertArea');
    if (!alertBox) return;

    const el = document.createElement('div');
    el.setAttribute('role', 'alert');
    el.className = `p-3.5 rounded-xl border-l-4 text-xs font-semibold flex items-center gap-2 ${alertStyles[type] || alertStyles.info}`;
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

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"']/g, m => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[m]);
}

function updateStatusPill(isOnline) {
    const pill = document.getElementById('offlineStatusPill');
    if (pill) pill.classList.toggle('hidden', isOnline);
}

function discardVaultView(message = 'This sign-in ended or changed. Sign in online to open your vault.') {
    isVaultLocked = true;
    Object.keys(revealTimers).forEach(id => { clearTimeout(revealTimers[id]); delete revealTimers[id]; });
    document.querySelectorAll('form').forEach(form => form.reset());
    document.getElementById('category').replaceChildren(new Option('Select Category', ''));
    currentGeneratedPassword = '';
    activeMasterKeyAction = activeMasterKeyAccountId = activeMasterKeyButton = null;
    document.querySelectorAll('[data-vault-profile]').forEach(el => { el.textContent = ''; });
    document.getElementById('categoryChips').replaceChildren();
    document.getElementById('sidebarCategories').replaceChildren();

    const main = document.getElementById('mainContent');
    main.replaceChildren();
    const text = document.createElement('p');
    text.className = 'text-sm text-gray-300 py-6';
    text.textContent = message;
    const link = document.createElement('a');
    link.href = 'login.php';
    link.textContent = 'Sign in';
    link.className = 'text-primary underline';
    main.append(text, link);

    document.getElementById('activeCountText').textContent = 'VAULT CLOSED';
    document.querySelectorAll('[id$="Modal"], #searchOverlay').forEach(el => el.classList.add('hidden'));
    document.getElementById('vaultLockOverlay').classList.add('hidden');
    document.body.classList.remove('vault-blurred');
    document.body.style.overflow = 'auto';
}

function renderVaultSnapshot(accounts, categories, user = {}) {
    if (!VaultDevice.matches(VAULT_OWNER) || isVaultLocked) return;
    const selected = Number(new URLSearchParams(location.search).get('category')) || 0;
    const visible = selected ? accounts.filter(account => Number(account.category_id) === selected) : accounts;

    if (visible.length) renderOfflineVaultCards(visible);
    else document.getElementById('mainContent').innerHTML = EMPTY_VAULT_HTML;

    document.getElementById('activeCountText').textContent = `${accounts.length} SECURE ASSET${accounts.length !== 1 ? 's' : ''}`;
    document.querySelectorAll('[data-vault-profile]').forEach(el => {
        const field = el.dataset.vaultProfile;
        el.textContent = field === 'initial'
            ? (user.username || 'V').charAt(0).toUpperCase()
            : (user[field] || (field === 'username' ? 'Vault' : ''));
    });

    const select = document.getElementById('category');
    const previous = select.value;
    select.replaceChildren(new Option('Select Category', ''));
    for (const category of categories) select.add(new Option(category.name, category.id));
    select.value = previous;

    for (const id of ['categoryChips', 'sidebarCategories']) {
        const container = document.getElementById(id);
        container.replaceChildren();
        for (const category of [{ id: 0, name: 'All Vaults' }, ...categories]) {
            const link = document.createElement('a');
            link.href = 'dashboard.php' + (category.id ? '?category=' + category.id : '');
            link.textContent = (category.icon ? category.icon + ' ' : '') + (id === 'categoryChips' && !category.id ? 'All' : category.name);
            link.className = (id === 'categoryChips'
                ? 'flex-shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold transition-all '
                : 'flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all ')
                + (Number(category.id) === selected
                    ? (id === 'categoryChips' ? 'bg-primary text-dark-bg' : 'bg-primary/10 text-primary')
                    : 'bg-dark-card text-gray-400 hover:text-white');

            if (id === 'sidebarCategories') {
                const count = document.createElement('span');
                count.className = 'ml-auto text-xs bg-dark-border/80 px-2 py-0.5 rounded-full text-gray-500';
                count.textContent = String(category.id
                    ? accounts.filter(account => Number(account.category_id) === Number(category.id)).length
                    : accounts.length);
                link.appendChild(count);
            }
            container.appendChild(link);
        }
    }
}

function renderOfflineVaultCards(items) {
    if (!VaultDevice.matches(VAULT_OWNER) || isVaultLocked) return;
    const main = document.getElementById('mainContent');
    if (!main) return;

    let grid = document.getElementById('accountsList');
    if (!grid) {
        main.innerHTML = `<div id="accountsList" class="grid grid-cols-1 md:grid-cols-2 gap-3"></div>`;
        grid = document.getElementById('accountsList');
    }

    grid.innerHTML = '';
    items.forEach((account, index) => {
        const acc = { ...account, id: Number(account.id) };
        if (!Number.isSafeInteger(acc.id) || acc.id < 1) return;
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
                    <span style="color:${iconColor};">${escapeHtml(letter)}</span>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="font-bold text-sm truncate">${escapeHtml(acc.service_name)}</h3>
                    <div class="cursor-pointer group flex items-center gap-1 mt-0.5 w-max max-w-full"
                         data-copy-username
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
                <button data-copy-username
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
        card.querySelectorAll('[data-copy-username]').forEach(button => button.addEventListener('click', () => copyUserClipboard(acc.username)));
        grid.appendChild(card);
    });

    const activeCountText = document.getElementById('activeCountText');
    if (activeCountText) {
        activeCountText.textContent = `${items.length} SECURE ASSET${items.length !== 1 ? 's' : ''} (LOCAL CACHE)`;
    }
}

// ── Search Overlay ──────────────────────────────────────────

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
    if (emptyMsg) emptyMsg.classList.toggle('hidden', visibleCount > 0 || q === '');
}

function showSearchOverlay() {
    const overlay = document.getElementById('searchOverlay');
    if (overlay) {
        overlay.classList.remove('hidden');
        setTimeout(() => document.getElementById('searchInput')?.focus(), 100);
    }
}

function hideSearchOverlay() {
    document.getElementById('searchOverlay')?.classList.add('hidden');
}

// ── Modals, Navigation & Clipboard ──────────────────────────

function toggleModalPasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;

    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';

    if (btn) {
        btn.innerHTML = isPassword ? `
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/>
            </svg>
        ` : `
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7
                         -1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>
        `;
    }
}

function openModal(id) {
    const el = document.getElementById(id);
    if (el) {
        el.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(id) {
    if (id === 'settingsModal' && masterPasswordChangeInProgress) return;
    if (id === 'settingsModal') document.getElementById('settingsForm').reset();
    if (id === 'masterKeyModal') document.getElementById('promptMasterKey').value = '';
    if (id === 'accountModal') {
        document.getElementById('masterPassword').value = '';
        document.getElementById('accountPassword').value = '';
    }
    const el = document.getElementById(id);
    if (el) {
        el.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

function openAddAccountModal() {
    document.getElementById('accountId').value = '';
    document.getElementById('accountForm').reset();
    const pwInput = document.getElementById('accountPassword');
    if (pwInput) {
        pwInput.value = '';
        pwInput.type = 'password';
    }
    document.getElementById('modalTitle').textContent = 'Add New Account';

    document.getElementById('passwordGeneratorPanel')?.classList.add('hidden');
    const toggleBtn = document.getElementById('toggleGenBtn');
    if (toggleBtn) {
        toggleBtn.classList.remove('bg-primary', 'text-dark-bg');
        toggleBtn.classList.add('text-primary', 'bg-primary/10');
    }
    currentGeneratedPassword = '';
    openModal('accountModal');
}

function openSettingsModal() {
    if (masterPasswordChangeInProgress) return;
    document.getElementById('settingsForm').reset();
    document.getElementById('keyChangeStatus').textContent = '';
    document.getElementById('keyChangeRecovery').classList.add('hidden');
    openModal('settingsModal');
}

function closeUpdateNotice() {
    closeModal('updateNoticeModal');
    localStorage.setItem('seen_update_v1_3_enhanced', 'true');
    localStorage.setItem('seen_update_v1_3', 'true');
}

function updateVaultCount() {
    const remaining = document.querySelectorAll('#accountsList [data-account-id]').length;
    const activeCountText = document.getElementById('activeCountText');
    if (activeCountText) {
        activeCountText.textContent = `${remaining} SECURE ASSET${remaining !== 1 ? 's' : ''} ACTIVE`;
    }
    if (remaining === 0) location.reload();
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
    if (isVaultLocked || !VaultDevice.matches(VAULT_OWNER)) return;
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
