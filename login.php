<?php
/**
 * SecureVault - Login / Register Gateway (PWA Edition)
 */

require_once 'config.php';
require_once 'auth.php';

if (defined('MAINTENANCE_MODE') && MAINTENANCE_MODE) {
    require_once __DIR__ . '/maintenance.php';
    exit;
}

if (isLoggedIn() && verifySession()) {
    header('Location: dashboard.php');
    exit;
}

$flash        = getFlashMessage();
$message      = $flash['message'];
$messageType  = $flash['type'];
$showRegister = isset($_GET['register']);
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#080E1A">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="SecureVault">
    <meta name="description" content="SecureVault — Unlock or create your zero-knowledge encrypted personal vault">
    <title>SecureVault — Master Gateway</title>
    <link rel="manifest" href="manifest.json">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary':       '#00E676',
                        'primary-dark':  '#00C65E',
                        'dark-bg':       '#080E1A',
                        'dark-card':     '#0F1729',
                        'dark-border':   '#1A2540',
                        'dark-surface':  '#131D33',
                    },
                    fontFamily: { sans: ['Inter', 'sans-serif'] }
                }
            }
        }
    </script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: #080E1A; color: #FFFFFF; min-height: 100vh; }

        /* Subtle background pattern */
        .bg-dots {
            background-image: radial-gradient(rgba(0, 230, 118, 0.07) 1px, transparent 1px);
            background-size: 24px 24px;
        }

        /* Safe area support for iOS notches */
        .pb-safe { padding-bottom: max(env(safe-area-inset-bottom, 0px), 16px); }

        /* Entrance animations */
        @keyframes fadeInScale {
            from { opacity: 0; transform: scale(0.97) translateY(12px); }
            to   { opacity: 1; transform: scale(1) translateY(0); }
        }
        .animate-card { animation: fadeInScale 0.35s cubic-bezier(0.16, 1, 0.3, 1) forwards; }

        /* Active tab indicator transition */
        .tab-btn { transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); }

        /* Smooth strength bar */
        #strengthBar { transition: width 0.3s ease, background-color 0.3s ease; }
    </style>
</head>
<body class="bg-dark-bg bg-dots flex flex-col justify-between antialiased selection:bg-primary/20 selection:text-primary">

    <!-- Ambient Glowing Background Accents -->
    <div class="fixed inset-0 pointer-events-none overflow-hidden z-0">
        <div class="absolute -top-32 left-1/2 -translate-x-1/2 w-[550px] h-[400px] rounded-full opacity-15"
             style="background: radial-gradient(circle, #00E676 0%, transparent 70%); filter: blur(90px);"></div>
        <div class="absolute -bottom-32 left-1/2 -translate-x-1/2 w-[450px] h-[350px] rounded-full opacity-10"
             style="background: radial-gradient(circle, #00C65E 0%, transparent 70%); filter: blur(90px);"></div>
    </div>

    <!-- ===== TOP RESPONSIVE HEADER ===== -->
    <header class="relative z-10 w-full max-w-5xl mx-auto px-4 sm:px-6 pt-5 pb-3 flex items-center justify-between">
        <a href="index.php" class="inline-flex items-center gap-2 text-xs sm:text-sm font-semibold text-gray-400 hover:text-primary transition-colors bg-dark-card/70 backdrop-blur-md px-3.5 py-2 rounded-xl border border-dark-border hover:border-primary/40 active:scale-95">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <span>Back to Home</span>
        </a>

        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-primary/10 border border-primary/20 text-primary text-[10px] sm:text-xs font-bold uppercase tracking-wider">
                <span class="w-1.5 h-1.5 rounded-full bg-primary animate-pulse"></span>
                <span>PWA v1.3</span>
            </span>
        </div>
    </header>

    <!-- ===== MAIN CONTENT CONTAINER ===== -->
    <main class="relative z-10 flex-1 flex flex-col items-center justify-center px-4 py-6 sm:py-10">
        <div class="w-full max-w-[440px] animate-card">

            <!-- Logo + Title Header -->
            <div class="text-center mb-6">
                <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-primary/15 border border-primary/35 flex items-center justify-center mx-auto mb-3 shadow-lg shadow-primary/20">
                    <img src="logo.svg" alt="SecureVault Logo" class="w-8 h-8 sm:w-9 sm:h-9">
                </div>
                <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight uppercase">
                    Secure<span class="text-primary">Vault</span>
                </h1>
                <p class="text-[11px] text-gray-400 font-medium tracking-wide mt-0.5">
                    Zero-Knowledge Personal Vault
                </p>
            </div>

            <!-- Anti-Phishing Security Advisory -->
            <div class="mb-5 p-3 sm:p-3.5 rounded-2xl bg-yellow-950/30 border border-yellow-600/40 text-yellow-400 text-[11px] leading-relaxed flex items-start gap-2.5 shadow-md">
                <svg class="w-4 h-4 text-yellow-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <div class="flex-1">
                    <strong>Security Notice:</strong> Official domain is <code class="font-bold text-yellow-300 bg-yellow-900/40 px-1 py-0.5 rounded">securevault.great-site.net</code>. Never enter your master key on unauthorized domains.
                </div>
            </div>

            <!-- ===== AUTH CARD ===== -->
            <div class="bg-dark-card/95 backdrop-blur-xl border border-dark-border rounded-3xl shadow-2xl overflow-hidden ring-1 ring-primary/20">

                <!-- Tab Selector (Sign In vs Create Account) -->
                <div class="flex border-b border-dark-border bg-dark-surface/40">
                    <button id="tabLogin" type="button"
                            onclick="showTab('login')"
                            class="tab-btn flex-1 py-4 text-xs sm:text-sm font-bold border-b-2 transition-all
                                   <?php echo !$showRegister ? 'text-primary border-primary bg-dark-card' : 'text-gray-400 border-transparent hover:text-gray-200'; ?>">
                        Sign In
                    </button>
                    <button id="tabRegister" type="button"
                            onclick="showTab('register')"
                            class="tab-btn flex-1 py-4 text-xs sm:text-sm font-bold border-b-2 transition-all
                                   <?php echo $showRegister ? 'text-primary border-primary bg-dark-card' : 'text-gray-400 border-transparent hover:text-gray-200'; ?>">
                        Create Account
                    </button>
                </div>

                <!-- Alert Feedback Area -->
                <div id="alertArea" class="px-5 pt-4 empty:hidden">
                    <?php if ($message): ?>
                    <div class="p-3.5 rounded-xl text-xs font-semibold border-l-4
                                <?php echo $messageType === 'error'
                                    ? 'bg-red-900/30 border-red-500 text-red-300'
                                    : 'bg-green-900/30 border-green-500 text-green-300'; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- ── 1. LOGIN FORM ──────────────────────────────── -->
                <div id="loginForm" style="display:<?php echo $showRegister ? 'none' : 'block'; ?>" class="p-5 sm:p-7">
                    <form id="loginFormElement" onsubmit="handleLoginSubmit(event)" class="space-y-4">

                        <!-- Username / Email Field -->
                        <div>
                            <label for="login-username" class="block text-xs font-bold text-gray-300 uppercase tracking-wider mb-1.5">
                                Username / Email
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-500">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                </div>
                                <input type="text" id="login-username" placeholder="Enter username or email"
                                       autocomplete="username" required
                                       class="w-full pl-10 pr-4 py-3 sm:py-3.5 bg-dark-bg border border-dark-border rounded-xl text-white placeholder-gray-500 text-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/30 transition-all">
                            </div>
                        </div>

                        <!-- Master Key Field -->
                        <div>
                            <label for="login-password" class="block text-xs font-bold text-gray-300 uppercase tracking-wider mb-1.5">
                                Master Key
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-500">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                </div>
                                <input type="password" id="login-password" placeholder="Enter your master key"
                                       autocomplete="current-password" required
                                       class="w-full pl-10 pr-16 py-3 sm:py-3.5 bg-dark-bg border border-dark-border rounded-xl text-white placeholder-gray-500 text-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/30 transition-all">
                                <button type="button" onclick="togglePassword('login-password', this)"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 px-2.5 py-1 text-xs font-bold text-gray-400 hover:text-primary transition-colors rounded-lg">
                                    SHOW
                                </button>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit"
                                class="w-full py-3.5 sm:py-4 bg-primary hover:bg-primary-dark text-dark-bg font-extrabold text-sm rounded-xl shadow-lg shadow-primary/25 transition-all hover:scale-[1.01] active:scale-[0.98] flex items-center justify-center gap-2 mt-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                            <span>Unlock Vault</span>
                        </button>
                    </form>
                </div>

                <!-- ── 2. REGISTER FORM ───────────────────────────── -->
                <div id="registerForm" style="display:<?php echo $showRegister ? 'block' : 'none'; ?>" class="p-5 sm:p-7">
                    <form id="registerFormElement" onsubmit="handleRegisterSubmit(event)" class="space-y-4">

                        <!-- Username Field -->
                        <div>
                            <label for="register-username" class="block text-xs font-bold text-gray-300 uppercase tracking-wider mb-1.5">
                                Username
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-500">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                </div>
                                <input type="text" id="register-username" placeholder="Choose a username (min 3 chars)"
                                       autocomplete="username" required
                                       class="w-full pl-10 pr-4 py-3 sm:py-3.5 bg-dark-bg border border-dark-border rounded-xl text-white placeholder-gray-500 text-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/30 transition-all">
                            </div>
                        </div>

                        <!-- Email Field -->
                        <div>
                            <label for="register-email" class="block text-xs font-bold text-gray-300 uppercase tracking-wider mb-1.5">
                                Email Address
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-500">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                </div>
                                <input type="email" id="register-email" placeholder="your@email.com"
                                       autocomplete="email" required
                                       class="w-full pl-10 pr-4 py-3 sm:py-3.5 bg-dark-bg border border-dark-border rounded-xl text-white placeholder-gray-500 text-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/30 transition-all">
                            </div>
                        </div>

                        <!-- Master Key Field + Strength Meter -->
                        <div>
                            <label for="register-password" class="block text-xs font-bold text-gray-300 uppercase tracking-wider mb-1.5">
                                Master Key (Vault Passphrase)
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-500">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                </div>
                                <input type="password" id="register-password" placeholder="Create strong master key"
                                       autocomplete="new-password" required
                                       class="w-full pl-10 pr-16 py-3 sm:py-3.5 bg-dark-bg border border-dark-border rounded-xl text-white placeholder-gray-500 text-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/30 transition-all">
                                <button type="button" onclick="togglePassword('register-password', this)"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 px-2.5 py-1 text-xs font-bold text-gray-400 hover:text-primary transition-colors rounded-lg">
                                    SHOW
                                </button>
                            </div>

                            <!-- Animated Strength Bar -->
                            <div class="mt-2 h-1.5 bg-dark-border rounded-full overflow-hidden">
                                <div id="strengthBar" class="h-full rounded-full w-0 bg-primary"></div>
                            </div>
                            <div class="flex items-center justify-between text-[11px] text-gray-400 mt-1.5">
                                <span id="strengthText">Min 8 chars, uppercase, number & symbol</span>
                                <span id="strengthScore" class="font-bold text-primary">0%</span>
                            </div>
                        </div>

                        <!-- Confirm Master Key -->
                        <div>
                            <label for="register-confirm" class="block text-xs font-bold text-gray-300 uppercase tracking-wider mb-1.5">
                                Confirm Master Key
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-500">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                </div>
                                <input type="password" id="register-confirm" placeholder="Re-enter master key"
                                       autocomplete="new-password" required
                                       class="w-full pl-10 pr-4 py-3 sm:py-3.5 bg-dark-bg border border-dark-border rounded-xl text-white placeholder-gray-500 text-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/30 transition-all">
                            </div>
                        </div>

                        <!-- Create Button -->
                        <button type="submit"
                                class="w-full py-3.5 sm:py-4 bg-primary hover:bg-primary-dark text-dark-bg font-extrabold text-sm rounded-xl shadow-lg shadow-primary/25 transition-all hover:scale-[1.01] active:scale-[0.98] flex items-center justify-center gap-2 mt-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                            <span>Create Secure Vault</span>
                        </button>
                    </form>
                </div>

                <!-- Card Footer Info -->
                <div class="px-5 py-3.5 bg-dark-surface/40 border-t border-dark-border/80 text-center text-[11px] text-gray-500 font-mono flex items-center justify-center gap-2">
                    <svg class="w-3.5 h-3.5 text-primary" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    <span>AES-256-GCM · PBKDF2 (100k) · Client-Side</span>
                </div>
            </div>

            <!-- Open Source / GitHub Link -->
            <div class="mt-6 text-center">
                <a href="https://github.com/RonanStack24/SecureVault" target="_blank" rel="noopener noreferrer"
                   class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-dark-card/70 border border-dark-border text-gray-400 hover:text-white hover:border-gray-500 transition-all text-xs font-semibold">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.477 2 12c0 4.42 2.865 8.166 6.839 9.489.5.092.682-.217.682-.482 0-.237-.008-.866-.013-1.7-2.782.603-3.369-1.34-3.369-1.34-.454-1.156-1.11-1.463-1.11-1.463-.908-.62.069-.608.069-.608 1.003.07 1.531 1.03 1.531 1.03.892 1.529 2.341 1.087 2.91.831.092-.646.35-1.086.636-1.336-2.22-.253-4.555-1.11-4.555-4.943 0-1.091.39-1.984 1.029-2.683-.103-.253-.446-1.27.098-2.647 0 0 .84-.269 2.75 1.025A9.578 9.578 0 0112 6.836c.85.004 1.705.114 2.504.336 1.909-1.294 2.747-1.025 2.747-1.025.546 1.377.203 2.394.1 2.647.64.699 1.028 1.592 1.028 2.683 0 3.842-2.339 4.687-4.566 4.935.359.309.678.919.678 1.852 0 1.336-.012 2.415-.012 2.743 0 .267.18.578.688.48C19.138 20.161 22 16.416 22 12c0-5.523-4.477-10-10-10z"/></svg>
                    <span>Auditable Open Source on GitHub</span>
                </a>
            </div>

        </div>
    </main>

    <!-- ===== FOOTER ===== -->
    <footer class="relative z-10 py-4 text-center text-xs text-gray-500 pb-safe">
        <span>Developed by <strong class="text-gray-400">Ronan Antoque</strong></span>
    </footer>

    <!-- ===== JAVASCRIPT LOGIC ===== -->
    <script>
        function showTab(tab) {
            const isLogin = tab === 'login';
            document.getElementById('loginForm').style.display    = isLogin ? 'block' : 'none';
            document.getElementById('registerForm').style.display = isLogin ? 'none'  : 'block';

            const tl = document.getElementById('tabLogin');
            const tr = document.getElementById('tabRegister');

            if (isLogin) {
                tl.className = 'tab-btn flex-1 py-4 text-xs sm:text-sm font-bold border-b-2 text-primary border-primary bg-dark-card';
                tr.className = 'tab-btn flex-1 py-4 text-xs sm:text-sm font-bold border-b-2 text-gray-400 border-transparent hover:text-gray-200';
            } else {
                tr.className = 'tab-btn flex-1 py-4 text-xs sm:text-sm font-bold border-b-2 text-primary border-primary bg-dark-card';
                tl.className = 'tab-btn flex-1 py-4 text-xs sm:text-sm font-bold border-b-2 text-gray-400 border-transparent hover:text-gray-200';
            }
        }

        function togglePassword(inputId, btn) {
            const el = document.getElementById(inputId);
            if (!el) return;
            const isPassword = el.type === 'password';
            el.type = isPassword ? 'text' : 'password';
            if (btn) btn.textContent = isPassword ? 'HIDE' : 'SHOW';
        }

        function showAlert(message, type = 'info') {
            const area = document.getElementById('alertArea');
            if (!area) return;
            const color = type === 'error'
                ? 'bg-red-900/30 border-red-500 text-red-300'
                : 'bg-green-900/30 border-green-500 text-green-300';
            area.innerHTML = `
                <div class="p-3.5 rounded-xl text-xs font-semibold border-l-4 ${color} transition-all">
                    ${message}
                </div>`;
            setTimeout(() => {
                if (area.innerHTML.includes(message)) area.innerHTML = '';
            }, 6000);
        }

        function handleLoginSubmit(e) {
            e.preventDefault();
            const username = document.getElementById('login-username').value.trim();
            const password = document.getElementById('login-password').value;
            const btn      = e.target.querySelector('button[type=submit]');
            const origHtml = btn.innerHTML;

            btn.disabled  = true;
            btn.innerHTML = `<span>Unlocking Vault…</span>`;

            fetch('api/auth.php?action=login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'login', username, password })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    btn.innerHTML = `<span>Authenticated! Redirecting…</span>`;
                    window.location.href = 'dashboard.php';
                } else {
                    showAlert(data.message || 'Authentication failed. Check your username and master key.', 'error');
                    btn.disabled  = false;
                    btn.innerHTML = origHtml;
                }
            })
            .catch(() => {
                showAlert('Network communication error. Please try again.', 'error');
                btn.disabled  = false;
                btn.innerHTML = origHtml;
            });
        }

        function handleRegisterSubmit(e) {
            e.preventDefault();
            const username        = document.getElementById('register-username').value.trim();
            const email           = document.getElementById('register-email').value.trim();
            const password        = document.getElementById('register-password').value;
            const confirmPassword = document.getElementById('register-confirm').value;
            const btn             = e.target.querySelector('button[type=submit]');
            const origHtml        = btn.innerHTML;

            if (password !== confirmPassword) {
                showAlert('Master keys do not match. Please verify.', 'error');
                return;
            }

            btn.disabled  = true;
            btn.innerHTML = `<span>Creating Encrypted Vault…</span>`;

            fetch('api/auth.php?action=register', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'register', username, email, password, confirm_password: confirmPassword })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showAlert('Vault created successfully! Opening gateway…', 'success');
                    btn.innerHTML = `<span>Vault Initialized!</span>`;
                    setTimeout(() => window.location.href = 'login.php', 1200);
                } else {
                    const errors = data.errors || (data.data && data.data.errors);
                    const msg    = errors ? errors.join(', ') : (data.message || 'Registration failed');
                    showAlert(msg, 'error');
                    btn.disabled  = false;
                    btn.innerHTML = origHtml;
                }
            })
            .catch(() => {
                showAlert('Network communication error. Please try again.', 'error');
                btn.disabled  = false;
                btn.innerHTML = origHtml;
            });
        }

        // Live Password Strength Meter
        const regPw = document.getElementById('register-password');
        if (regPw) {
            regPw.addEventListener('input', function() {
                const pw  = this.value;
                const bar = document.getElementById('strengthBar');
                const txt = document.getElementById('strengthText');
                const scr = document.getElementById('strengthScore');
                let score = 0;

                if (pw.length >= 8)  score += 25;
                if (pw.length >= 12) score += 15;
                if (/[A-Z]/.test(pw)) score += 20;
                if (/[0-9]/.test(pw)) score += 20;
                if (/[^A-Za-z0-9]/.test(pw)) score += 20;
                score = Math.min(score, 100);

                bar.style.width = score + '%';
                scr.textContent = score + '%';

                if (score === 0) {
                    bar.style.background = '#FF6B6B';
                    txt.textContent = 'Min 8 chars, uppercase, number & symbol';
                    txt.className = 'text-[11px] text-gray-400';
                } else if (score < 40) {
                    bar.style.background = '#FF6B6B';
                    txt.textContent = 'Weak — add more diversity & length';
                    txt.className = 'text-[11px] text-red-400 font-semibold';
                } else if (score < 75) {
                    bar.style.background = '#FFC300';
                    txt.textContent = 'Moderate — good progress';
                    txt.className = 'text-[11px] text-yellow-400 font-semibold';
                } else {
                    bar.style.background = '#00E676';
                    txt.textContent = 'Strong Master Key 🔒';
                    txt.className = 'text-[11px] text-green-400 font-semibold';
                }
            });
        }
    </script>
    <script src="js/pwa.js"></script>

    <!-- ===== PWA INSTALL BANNER (Responsive Bottom Sheet) ===== -->
    <div id="installBanner" class="hidden fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 sm:max-w-sm z-50 transition-all">
        <div class="bg-dark-card/95 backdrop-blur-xl border border-primary/30 rounded-2xl p-4 shadow-2xl shadow-primary/10 flex items-center gap-3.5">
            <div class="w-10 h-10 rounded-xl bg-primary/20 flex items-center justify-center shrink-0">
                <img src="logo.svg" alt="App Icon" class="w-6 h-6">
            </div>
            <div class="flex-1 min-w-0">
                <h3 class="font-bold text-white text-xs truncate">Install SecureVault</h3>
                <p class="text-[11px] text-gray-400 truncate">Fast native experience on your device</p>
            </div>
            <div class="flex items-center gap-1.5 shrink-0">
                <button onclick="installApp()" class="px-3 py-1.5 bg-primary hover:bg-primary-dark text-dark-bg font-extrabold rounded-lg text-xs transition-all">Install</button>
                <button onclick="dismissInstallBanner()" class="p-1 text-gray-400 hover:text-white text-sm" aria-label="Dismiss">&times;</button>
            </div>
        </div>
    </div>
</body>
</html>
