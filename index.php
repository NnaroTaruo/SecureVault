<?php
/**
 * SecureVault - Login / Register (PWA Edition)
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

$flash       = getFlashMessage();
$message     = $flash['message'];
$messageType = $flash['type'];
$showRegister = isset($_GET['register']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#080E1A">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="SecureVault">
    <meta name="description" content="SecureVault — Your encrypted personal vault">
    <title>SecureVault — Master Gateway</title>
    <link rel="manifest" href="manifest.json">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
                    },
                    fontFamily: { sans: ['Inter', 'sans-serif'] }
                }
            }
        }
    </script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; }

        /* Animated background dots */
        .bg-dots {
            background-image: radial-gradient(rgba(0,230,118,.08) 1px, transparent 1px);
            background-size: 28px 28px;
        }

        /* High-intensity rotating 'lighting' edge */
        @keyframes rotateLighting {
            0%   { transform: rotate(0deg);   }
            100% { transform: rotate(360deg); }
        }
        .lighting-edge {
            background: conic-gradient(from 0deg at 50% 50%, #00E676, #00C65E, #080E1A, #00E676);
            animation: rotateLighting 2s linear infinite;
        }

        /* Form slide-up entrance */
        @keyframes formUp {
            from { opacity: 0; transform: translateY(22px); }
            to   { opacity: 1; transform: translateY(0);    }
        }
        .form-enter { animation: formUp .4s cubic-bezier(.22,1,.36,1) forwards; }

        /* Logo bounce */
        @keyframes logoBounce {
            0%   { transform: scale(.7) translateY(10px); opacity:0; }
            70%  { transform: scale(1.06) translateY(-3px); }
            100% { transform: scale(1) translateY(0); opacity:1; }
        }
        .logo-anim { animation: logoBounce .55s cubic-bezier(.34,1.56,.64,1) forwards; }

        /* Strength bar transition */
        #strengthBar { transition: width .35s ease, background-color .35s; }

        /* Tab selector */
        .tab-btn { transition: all .2s; }
        .tab-btn.active {
            color: #00E676;
            border-bottom-color: #00E676;
        }
    </style>
</head>
<body class="bg-dark-bg bg-dots min-h-screen flex flex-col items-center justify-center px-4 py-8">

    <!-- Radial glow background accent -->
    <div class="fixed inset-0 pointer-events-none overflow-hidden">
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2
                    w-[600px] h-[600px] rounded-full opacity-10"
             style="background: radial-gradient(circle, #00E676 0%, transparent 70%);"></div>
    </div>

    <!-- Logo + Brand (above card) -->
    <div class="text-center mb-6 logo-anim">
        <div class="w-16 h-16 rounded-2xl bg-primary/15 border border-primary/30
                    flex items-center justify-center text-primary font-black text-3xl
                    mx-auto mb-3 shadow-lg shadow-primary/20">
            🔐
        </div>
        <h1 class="text-xl font-black text-white tracking-tighter uppercase">Secure Vault</h1>
        <p class="text-[10px] text-primary font-bold uppercase tracking-[0.2em] mt-1">v1.2 PWA Edition</p>
    </div>

    <!-- Card Wrapper -->
    <div class="w-full max-w-sm relative p-[2px] rounded-2xl overflow-hidden">
        <!-- The 'Glow' Layer (Blur) -->
        <div class="lighting-edge absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[180%] h-[180%] pointer-events-none opacity-60 blur-xl"></div>
        <!-- The 'Lighting' Layer (Sharp) -->
        <div class="lighting-edge absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[200%] h-[200%] pointer-events-none opacity-100"></div>
        
        <!-- Inner Card Content -->
        <div class="relative bg-dark-card rounded-2xl shadow-2xl overflow-hidden form-enter">

            <!-- Tab selector -->
            <div class="flex border-b border-dark-border">
                <button id="tabLogin"
                        onclick="showTab('login')"
                        class="tab-btn active flex-1 py-4 text-sm font-bold border-b-2
                               <?php echo !$showRegister ? 'text-primary border-primary' : 'text-gray-500 border-transparent'; ?>">
                    Sign In
                </button>
                <button id="tabRegister"
                        onclick="showTab('register')"
                        class="tab-btn flex-1 py-4 text-sm font-bold border-b-2
                               <?php echo $showRegister ? 'text-primary border-primary' : 'text-gray-500 border-transparent'; ?>">
                    Create Account
                </button>
            </div>

            <!-- Alert area -->
            <div id="alertArea">
            <?php if ($message): ?>
            <div class="mx-5 mt-4 p-3 rounded-xl text-sm border-l-4
                        <?php echo $messageType === 'error'
                            ? 'bg-red-900/20 border-red-500 text-red-400'
                            : 'bg-green-900/20 border-green-500 text-green-400'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>
            </div>

            <!-- ── LOGIN FORM ──────────────────────────────── -->
            <div id="loginForm" style="display:<?php echo $showRegister ? 'none' : 'block'; ?>">
                <form id="loginFormElement" onsubmit="handleLoginSubmit(event)" class="p-5 space-y-4">

                    <div>
                        <label for="login-username"
                               class="block text-xs font-bold text-primary uppercase tracking-wider mb-2">
                            Username / Email
                        </label>
                        <input type="text" id="login-username" placeholder="Enter username or email"
                               autocomplete="username"
                               class="w-full px-4 py-3 bg-dark-bg border border-dark-border rounded-xl text-white
                                      placeholder-gray-500 text-sm focus:outline-none focus:border-primary transition-all" required>
                    </div>

                    <div>
                        <label for="login-password"
                               class="block text-xs font-bold text-primary uppercase tracking-wider mb-2">
                            Master Key
                        </label>
                        <div class="relative">
                            <input type="password" id="login-password" placeholder="Enter your master key"
                                   autocomplete="current-password"
                                   class="w-full px-4 py-3 pr-16 bg-dark-bg border border-dark-border rounded-xl text-white
                                          placeholder-gray-500 text-sm focus:outline-none focus:border-primary transition-all" required>
                            <button type="button" onclick="togglePassword('login-password')"
                                    class="absolute right-3 top-1/2 -translate-y-1/2
                                           text-xs font-semibold text-gray-400 hover:text-primary transition-colors">
                                Show
                            </button>
                        </div>
                    </div>

                    <button type="submit"
                            class="w-full py-3.5 bg-primary hover:bg-primary-dark text-dark-bg font-extrabold
                                   rounded-xl transition-all text-sm tracking-wide shadow-lg shadow-primary/30
                                   active:scale-[.98]">
                        🔓 Unlock Vault
                    </button>
                </form>
            </div>

            <!-- ── REGISTER FORM ───────────────────────────── -->
            <div id="registerForm" style="display:<?php echo $showRegister ? 'block' : 'none'; ?>">
                <form id="registerFormElement" onsubmit="handleRegisterSubmit(event)" class="p-5 space-y-4">

                    <div>
                        <label for="register-username"
                               class="block text-xs font-bold text-primary uppercase tracking-wider mb-2">
                            Username
                        </label>
                        <input type="text" id="register-username" placeholder="Choose a username"
                               autocomplete="username"
                               class="w-full px-4 py-3 bg-dark-bg border border-dark-border rounded-xl text-white
                                      placeholder-gray-500 text-sm focus:outline-none focus:border-primary transition-all" required>
                    </div>

                    <div>
                        <label for="register-email"
                               class="block text-xs font-bold text-primary uppercase tracking-wider mb-2">
                            Email
                        </label>
                        <input type="email" id="register-email" placeholder="your@email.com"
                               autocomplete="email"
                               class="w-full px-4 py-3 bg-dark-bg border border-dark-border rounded-xl text-white
                                      placeholder-gray-500 text-sm focus:outline-none focus:border-primary transition-all" required>
                    </div>

                    <div>
                        <label for="register-password"
                               class="block text-xs font-bold text-primary uppercase tracking-wider mb-2">
                            Master Key
                        </label>
                        <div class="relative">
                            <input type="password" id="register-password" placeholder="Create a strong master key"
                                   autocomplete="new-password"
                                   class="w-full px-4 py-3 pr-16 bg-dark-bg border border-dark-border rounded-xl text-white
                                          placeholder-gray-500 text-sm focus:outline-none focus:border-primary transition-all" required>
                            <button type="button" onclick="togglePassword('register-password')"
                                    class="absolute right-3 top-1/2 -translate-y-1/2
                                           text-xs font-semibold text-gray-400 hover:text-primary transition-colors">
                                Show
                            </button>
                        </div>
                        <!-- Strength bar -->
                        <div class="mt-2 h-1.5 bg-dark-border rounded-full overflow-hidden">
                            <div id="strengthBar" class="h-full rounded-full w-0 bg-primary"></div>
                        </div>
                        <p id="strengthText" class="text-xs text-gray-500 mt-1">Minimum 8 characters</p>
                    </div>

                    <div>
                        <label for="register-confirm"
                               class="block text-xs font-bold text-primary uppercase tracking-wider mb-2">
                            Confirm Master Key
                        </label>
                        <input type="password" id="register-confirm" placeholder="Repeat your master key"
                               autocomplete="new-password"
                               class="w-full px-4 py-3 bg-dark-bg border border-dark-border rounded-xl text-white
                                      placeholder-gray-500 text-sm focus:outline-none focus:border-primary transition-all" required>
                    </div>

                    <button type="submit"
                            class="w-full py-3.5 bg-primary hover:bg-primary-dark text-dark-bg font-extrabold
                                   rounded-xl transition-all text-sm tracking-wide shadow-lg shadow-primary/30
                                   active:scale-[.98]">
                        🛡️ Create Secure Vault
                    </button>
                </form>
            </div>

            <!-- Footer note -->
            <p class="text-center text-xs text-gray-600 pb-4">
                AES-256-GCM · Zero-Knowledge · End-to-End Encrypted
            </p>
        </div>
    </div>

    <!-- Dev credit -->
    <p class="text-xs text-gray-700 mt-6">Developed by Ronan Antoque</p>

    <script>
        // Tab switching
        function showTab(tab) {
            const isLogin = tab === 'login';
            document.getElementById('loginForm').style.display    = isLogin ? 'block' : 'none';
            document.getElementById('registerForm').style.display = isLogin ? 'none'  : 'block';

            const tl = document.getElementById('tabLogin');
            const tr = document.getElementById('tabRegister');

            tl.className = tl.className.replace(/text-primary|border-primary|text-gray-500|border-transparent/g,'').trim();
            tr.className = tr.className.replace(/text-primary|border-primary|text-gray-500|border-transparent/g,'').trim();

            tl.classList.add(isLogin ? 'text-primary' : 'text-gray-500', isLogin ? 'border-primary' : 'border-transparent');
            tr.classList.add(isLogin ? 'text-gray-500' : 'text-primary', isLogin ? 'border-transparent' : 'border-primary');
        }

        function togglePassword(id) {
            const el = document.getElementById(id);
            el.type  = el.type === 'password' ? 'text' : 'password';
        }

        function showAlert(message, type) {
            const area = document.getElementById('alertArea');
            const color = type === 'error'
                ? 'bg-red-900/20 border-red-500 text-red-400'
                : 'bg-green-900/20 border-green-500 text-green-400';
            area.innerHTML = `
                <div class="mx-5 mt-4 p-3 rounded-xl text-sm border-l-4 ${color}">
                    ${message}
                </div>`;
            setTimeout(() => area.innerHTML = '', 5000);
        }

        function handleLoginSubmit(e) {
            e.preventDefault();
            const username = document.getElementById('login-username').value;
            const password = document.getElementById('login-password').value;
            const btn      = e.target.querySelector('button[type=submit]');
            btn.disabled   = true;
            btn.textContent = 'Unlocking…';

            fetch('api/auth.php?action=login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'login', username, password })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    btn.textContent = '✅ Authenticated!';
                    window.location.href = 'dashboard.php';
                } else {
                    showAlert(data.message || 'Login failed', 'error');
                    btn.disabled = false;
                    btn.textContent = '🔓 Unlock Vault';
                }
            })
            .catch(() => {
                showAlert('Network error. Please try again.', 'error');
                btn.disabled = false;
                btn.textContent = '🔓 Unlock Vault';
            });
        }

        function handleRegisterSubmit(e) {
            e.preventDefault();
            const username        = document.getElementById('register-username').value;
            const email           = document.getElementById('register-email').value;
            const password        = document.getElementById('register-password').value;
            const confirmPassword = document.getElementById('register-confirm').value;
            const btn             = e.target.querySelector('button[type=submit]');
            btn.disabled          = true;
            btn.textContent       = 'Creating Vault…';

            fetch('api/auth.php?action=register', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'register', username, email, password, confirm_password: confirmPassword })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showAlert('Vault created! Signing you in…', 'success');
                    btn.textContent = '✅ Created!';
                    setTimeout(() => window.location.href = '?', 1400);
                } else {
                    const errors = data.errors || (data.data && data.data.errors);
                    const msg    = errors ? errors.join(', ') : (data.message || 'Registration failed');
                    showAlert(msg, 'error');
                    btn.disabled    = false;
                    btn.textContent = '🛡️ Create Secure Vault';
                }
            })
            .catch(() => {
                showAlert('Network error. Please try again.', 'error');
                btn.disabled    = false;
                btn.textContent = '🛡️ Create Secure Vault';
            });
        }

        // Password strength meter
        document.getElementById('register-password').addEventListener('input', function() {
            const pw  = this.value;
            const bar = document.getElementById('strengthBar');
            const txt = document.getElementById('strengthText');
            let score = 0;
            if (pw.length >= 8)  score += 25;
            if (pw.length >= 12) score += 15;
            if (/[A-Z]/.test(pw)) score += 20;
            if (/[0-9]/.test(pw)) score += 20;
            if (/[^A-Za-z0-9]/.test(pw)) score += 20;
            score = Math.min(score, 100);

            bar.style.width = score + '%';
            if (score < 40) {
                bar.style.background = '#FF6B6B';
                txt.textContent      = 'Weak — add numbers & symbols';
                txt.style.color      = '#FF6B6B';
            } else if (score < 70) {
                bar.style.background = '#FFC300';
                txt.textContent      = 'Moderate — keep going!';
                txt.style.color      = '#FFC300';
            } else {
                bar.style.background = '#00E676';
                txt.textContent      = 'Strong 🔒';
                txt.style.color      = '#00E676';
            }
        });
    </script>
    <script src="js/pwa.js"></script>

    <!-- PWA Install Banner (Premium) -->
    <div id="installBanner" class="hidden fixed bottom-6 left-6 right-6 z-[100] animate-bounce">
        <div class="bg-dark-card/90 backdrop-blur-xl border border-primary/30 rounded-2xl p-5 shadow-2xl shadow-primary/10 flex flex-col sm:flex-row items-center gap-4">
            <div class="w-12 h-12 bg-primary/20 rounded-xl flex items-center justify-center text-primary text-2xl">📲</div>
            <div class="flex-1 text-center sm:text-left">
                <h3 class="font-bold text-white text-sm">Install SecureVault</h3>
                <p class="text-xs text-gray-400">Add to your home screen for a premium, native experience.</p>
            </div>
            <div class="flex gap-2 w-full sm:w-auto">
                <button onclick="installApp()" class="flex-1 sm:flex-none px-5 py-2.5 bg-primary hover:bg-primary-dark text-dark-bg font-bold rounded-xl text-xs transition-all">Install</button>
                <button onclick="dismissInstallBanner()" class="flex-1 sm:flex-none px-5 py-2.5 bg-dark-border text-gray-300 rounded-xl text-xs hover:bg-dark-border/80 transition-all">Later</button>
            </div>
        </div>
    </div>
</body>
</html>
