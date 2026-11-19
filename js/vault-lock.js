// ==============================================================================
// SecureVault — Inactivity Auto-Lock & Privacy Blur Engine
// ==============================================================================

const INACTIVITY_TIMEOUT_MS = 5 * 60 * 1000; // 5 minutes
const INACTIVITY_WARNING_MS = 30 * 1000;     // 30 seconds warning before lock
let inactivityTimer = null;
let inactivityWarningTimer = null;
let lastActivityReset = 0;
let isVaultLocked = false;

function initInactivityTracker() {
    const resetTimer = () => {
        if (isVaultLocked) return;
        const now = Date.now();
        // Throttle activity resets to once per 1000ms
        if (now - lastActivityReset < 1000) return;
        lastActivityReset = now;

        if (inactivityTimer) clearTimeout(inactivityTimer);
        if (inactivityWarningTimer) clearTimeout(inactivityWarningTimer);

        // Warning timer at 4.5 minutes (30s before lock)
        inactivityWarningTimer = setTimeout(() => {
            if (!isVaultLocked) {
                showAlert('⚠️ Inactivity Warning: Vault auto-locking in 30s. Move mouse or press any key.', 'warning');
            }
        }, INACTIVITY_TIMEOUT_MS - INACTIVITY_WARNING_MS);

        // Lock timer at 5 minutes
        inactivityTimer = setTimeout(() => {
            triggerInactivityLock();
        }, INACTIVITY_TIMEOUT_MS);
    };

    const events = ['mousemove', 'mousedown', 'keydown', 'touchstart', 'scroll', 'click'];
    events.forEach(ev => window.addEventListener(ev, resetTimer, { passive: true }));
    resetTimer();
}

function triggerInactivityLock() {
    if (isVaultLocked) return;
    if (!VaultDevice.matches(VAULT_OWNER)) { discardVaultView(); return; }
    isVaultLocked = true;
    clearVaultKeyCheck();

    if (inactivityTimer) clearTimeout(inactivityTimer);
    if (inactivityWarningTimer) clearTimeout(inactivityWarningTimer);

    // Immediately blur all background elements to protect from prying eyes
    document.body.classList.add('vault-blurred');

    // Clear all visible plaintext passwords on cards immediately
    document.querySelectorAll('[class*="password-display-"]').forEach(el => {
        el.textContent = '••••••••••••';
        el.classList.add('password-masked', 'pw-dots');
        el.style.color = '';
        el.style.letterSpacing = '';
        el.style.fontSize = '';
    });

    // Clear active password reveal timeouts
    if (typeof revealTimers === 'object' && revealTimers) {
        Object.keys(revealTimers).forEach(id => {
            clearTimeout(revealTimers[id]);
            delete revealTimers[id];
        });
    }

    // Close any open dialogs and wipe in-memory sensitive inputs
    ['masterKeyModal', 'accountModal', 'settingsModal', 'logoutModal', 'aboutModal', 'updateNoticeModal'].forEach(id => {
        document.getElementById(id)?.classList.add('hidden');
    });

    const masterPassPrompt = document.getElementById('promptMasterKey');
    if (masterPassPrompt) masterPassPrompt.value = '';
    const masterPassModal = document.getElementById('masterPassword');
    if (masterPassModal) masterPassModal.value = '';
    const accountPassModal = document.getElementById('accountPassword');
    if (accountPassModal) accountPassModal.value = '';
    document.getElementById('settingsForm')?.reset();

    // Display the unblurred Vault Lock Screen
    document.getElementById('vaultLockOverlay')?.classList.remove('hidden');

    // Asynchronously terminate server session (retains encrypted offline copy)
    try {
        if (VaultDevice.matches(VAULT_OWNER)) {
            fetch('api/auth.php?action=logout', {
                method: 'POST',
                keepalive: true,
                headers: { 'X-CSRF-Token': CSRF_TOKEN }
            }).catch(() => {});
        }
    } catch (e) {
        console.warn('Inactivity logout request error:', e);
    }
}

// Developer / Automated testing hook
window.__triggerInactivityLock = triggerInactivityLock;
