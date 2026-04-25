// PWA Service Worker Registration
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('service-worker.js')
            .then(reg => console.log('SW registered:', reg.scope))
            .catch(err => console.log('SW registration failed:', err));
    });
}

// PWA Install Prompt
let deferredPrompt;
window.addEventListener('beforeinstallprompt', e => {
    // Prevent the default browser prompt (we show our own premium banner)
    e.preventDefault();
    deferredPrompt = e;

    // Check if user has already dismissed the banner in this session
    if (sessionStorage.getItem('pwa_dismissed') === 'true') return;

    // Show subtle install banner after 3 seconds
    setTimeout(() => {
        const banner = document.getElementById('installBanner');
        if (banner) {
            banner.classList.remove('hidden');
            banner.classList.add('flex'); // Ensure flex display if hidden was used
        }
    }, 3000);
});

function installApp() {
    if (!deferredPrompt) return;
    
    // Show the native prompt
    deferredPrompt.prompt();
    
    // Wait for the user to respond to the prompt
    deferredPrompt.userChoice.then((choiceResult) => {
        if (choiceResult.outcome === 'accepted') {
            console.log('User accepted the install prompt');
        } else {
            console.log('User dismissed the install prompt');
        }
        deferredPrompt = null;
        dismissInstallBanner();
    });
}

function dismissInstallBanner() {
    const banner = document.getElementById('installBanner');
    if (banner) {
        banner.classList.add('hidden');
        banner.classList.remove('flex');
        // Store dismissal in session storage so it doesn't show again this session
        sessionStorage.setItem('pwa_dismissed', 'true');
    }
}

// Check if app is already installed
window.addEventListener('appinstalled', (evt) => {
    console.log('SecureVault was installed.');
    dismissInstallBanner();
});

// ── App Protection ─────────────────────────────────────────
// Prevent right-click (context menu)
document.addEventListener('contextmenu', e => e.preventDefault());

// Prevent common DevTools keyboard shortcuts
document.addEventListener('keydown', e => {
    // F12, Ctrl+Shift+I, Ctrl+Shift+J, Ctrl+Shift+C, Ctrl+U
    if (e.key === 'F12' || 
       (e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'J' || e.key === 'C' || e.key === 'i' || e.key === 'j' || e.key === 'c')) || 
       (e.ctrlKey && (e.key === 'U' || e.key === 'u'))) {
        e.preventDefault();
    }
});
