<?php
/**
 * SecureVault — Official Landing Page
 * Zero-Knowledge Personal Password Vault
 */

require_once 'config.php';
require_once 'auth.php';

if (defined('MAINTENANCE_MODE') && MAINTENANCE_MODE) {
    require_once __DIR__ . '/maintenance.php';
    exit;
}

$userLoggedIn = isLoggedIn() && verifySession();
$currentUser  = $userLoggedIn ? getCurrentUser() : null;
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#080E1A">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="SecureVault">
    <meta name="description" content="SecureVault — Zero-knowledge client-side encrypted password manager. Military-grade AES-256-GCM security and installable PWA.">
    <title>SecureVault — Zero-Knowledge Encrypted Password Manager</title>
    <link rel="manifest" href="manifest.json">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
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
        body { font-family: 'Inter', sans-serif; background-color: #080E1A; color: #FFFFFF; overflow-x: hidden; }

        /* Background dots pattern */
        .bg-dots {
            background-image: radial-gradient(rgba(0, 230, 118, 0.08) 1px, transparent 1px);
            background-size: 28px 28px;
        }

        /* Glassmorphism */
        .glass-nav {
            background: rgba(8, 14, 26, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(26, 37, 64, 0.7);
        }
        .glass-card {
            background: rgba(15, 23, 41, 0.75);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(26, 37, 64, 0.9);
        }
        .glass-card:hover {
            border-color: rgba(0, 230, 118, 0.4);
            transform: translateY(-2px);
        }

        /* Gradient Text */
        .text-gradient {
            background: linear-gradient(135deg, #FFFFFF 30%, #00E676 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .text-gradient-subtle {
            background: linear-gradient(135deg, #E2E8F0 0%, #94A3B8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Subtle glow pulsing */
        @keyframes pulseGlow {
            0%, 100% { opacity: 0.15; transform: scale(1); }
            50% { opacity: 0.28; transform: scale(1.08); }
        }
        .glow-sphere { animation: pulseGlow 6s ease-in-out infinite; }

        /* Smooth accordion transitions */
        details summary::-webkit-details-marker { display: none; }
        details[open] summary svg { transform: rotate(180deg); }
    </style>
</head>
<body class="bg-dark-bg bg-dots antialiased selection:bg-primary/20 selection:text-primary">

    <!-- Ambient Glowing Spheres Background -->
    <div class="fixed inset-0 pointer-events-none overflow-hidden z-0">
        <div class="absolute -top-32 left-1/2 -translate-x-1/2 w-[700px] h-[500px] rounded-full glow-sphere"
             style="background: radial-gradient(circle, #00E676 0%, transparent 65%); filter: blur(80px);"></div>
        <div class="absolute top-[40%] -left-48 w-[500px] h-[500px] rounded-full glow-sphere"
             style="background: radial-gradient(circle, #00C65E 0%, transparent 65%); filter: blur(100px); animation-delay: 2s;"></div>
        <div class="absolute top-[75%] -right-48 w-[550px] h-[550px] rounded-full glow-sphere"
             style="background: radial-gradient(circle, #00E676 0%, transparent 65%); filter: blur(100px); animation-delay: 4s;"></div>
    </div>

    <!-- ===== NAVIGATION BAR ===== -->
    <header class="fixed top-0 left-0 right-0 z-50 glass-nav transition-all">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 h-16 sm:h-20 flex items-center justify-between">
            <!-- Brand Logo -->
            <a href="index.php" class="flex items-center gap-2.5 sm:gap-3 group">
                <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-primary/15 border border-primary/40 flex items-center justify-center shadow-lg shadow-primary/20 group-hover:scale-105 transition-transform">
                    <img src="logo.svg" alt="SecureVault Logo" class="w-5 h-5 sm:w-6 sm:h-6">
                </div>
                <div class="flex flex-col">
                    <span class="font-extrabold text-base sm:text-lg tracking-tight text-white flex items-center gap-1">
                        SECURE<span class="text-primary font-black">VAULT</span>
                    </span>
                    <span class="text-[8px] sm:text-[9px] uppercase font-bold tracking-[0.2em] text-gray-400">Zero-Knowledge</span>
                </div>
            </a>

            <!-- Desktop Nav Links -->
            <nav class="hidden lg:flex items-center gap-7 text-sm font-semibold text-gray-300">
                <a href="#features" class="hover:text-primary transition-colors">Features</a>
                <a href="#crypto-demo" class="hover:text-primary transition-colors">Live Crypto</a>
                <a href="#architecture" class="hover:text-primary transition-colors">Security Model</a>
                <a href="#comparison" class="hover:text-primary transition-colors">Comparison</a>
                <a href="#faq" class="hover:text-primary transition-colors">FAQ</a>
            </nav>

            <!-- Nav Actions & Mobile Toggle -->
            <div class="flex items-center gap-2 sm:gap-3">
                <?php if ($userLoggedIn): ?>
                    <a href="dashboard.php"
                       class="px-3.5 py-2 sm:px-4 sm:py-2.5 rounded-xl bg-primary hover:bg-primary-dark text-dark-bg font-extrabold text-xs sm:text-sm tracking-wide shadow-lg shadow-primary/25 transition-all active:scale-95 flex items-center gap-1.5 sm:gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        <span>Vault</span>
                    </a>
                <?php else: ?>
                    <a href="login.php"
                       class="px-3 py-1.5 sm:px-3.5 sm:py-2 text-xs sm:text-sm font-bold text-gray-300 hover:text-white transition-colors">
                        Sign In
                    </a>
                    <a href="login.php?register=1"
                       class="px-3.5 py-2 sm:px-4 sm:py-2.5 rounded-xl bg-primary hover:bg-primary-dark text-dark-bg font-extrabold text-xs sm:text-sm tracking-wide shadow-lg shadow-primary/25 transition-all active:scale-95 flex items-center gap-1">
                        <span>Get Started</span>
                        <svg class="w-3 h-3 sm:w-3.5 sm:h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </a>
                <?php endif; ?>

                <!-- Mobile Hamburger Button -->
                <button type="button" onclick="toggleMobileNav()" id="mobileMenuBtn" aria-label="Toggle Navigation Menu"
                        class="lg:hidden p-2 rounded-xl bg-dark-card border border-dark-border text-gray-400 hover:text-white transition-colors">
                    <svg id="menuIconOpen" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    <svg id="menuIconClose" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        <!-- Mobile Drawer Menu -->
        <div id="mobileDrawer" class="hidden lg:hidden border-t border-dark-border/80 bg-dark-bg/95 backdrop-blur-xl px-4 py-4 space-y-3">
            <a href="#features" onclick="toggleMobileNav()" class="block py-2 text-sm font-semibold text-gray-300 hover:text-primary">Features</a>
            <a href="#crypto-demo" onclick="toggleMobileNav()" class="block py-2 text-sm font-semibold text-gray-300 hover:text-primary">Live Cryptography</a>
            <a href="#architecture" onclick="toggleMobileNav()" class="block py-2 text-sm font-semibold text-gray-300 hover:text-primary">Security Model</a>
            <a href="#comparison" onclick="toggleMobileNav()" class="block py-2 text-sm font-semibold text-gray-300 hover:text-primary">Comparison</a>
            <a href="#faq" onclick="toggleMobileNav()" class="block py-2 text-sm font-semibold text-gray-300 hover:text-primary">FAQ</a>
        </div>
    </header>

    <!-- ===== MAIN CONTENT ===== -->
    <main class="relative z-10 pt-28 sm:pt-36">

        <!-- Anti-Phishing Advisory Bar -->
        <div class="max-w-4xl mx-auto px-4 mb-8">
            <div class="p-3.5 rounded-2xl bg-yellow-950/30 border border-yellow-600/40 text-yellow-400 text-xs flex items-center justify-between gap-3 shadow-lg">
                <div class="flex items-center gap-2.5">
                    <svg class="w-4 h-4 text-yellow-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <span><strong>Official Verification:</strong> Official domain is <code class="font-bold text-yellow-300 bg-yellow-900/40 px-1.5 py-0.5 rounded">securevault.great-site.net</code>. Beware of fake phishing clones.</span>
                </div>
                <a href="https://github.com/RonanStack24/SecureVault" target="_blank" rel="noopener noreferrer" class="hidden sm:inline-flex text-[11px] font-bold text-yellow-300 hover:underline shrink-0">Open Source on GitHub →</a>
            </div>
        </div>

        <!-- ===== HERO SECTION ===== -->
        <section class="max-w-6xl mx-auto px-4 sm:px-6 text-center pt-4 pb-16 sm:pb-24">
            <!-- Security Tag -->
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-primary/10 border border-primary/30 text-primary text-xs font-bold uppercase tracking-wider mb-6 shadow-sm">
                <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                <span>Zero-Knowledge Client-Side Cryptography</span>
            </div>

            <!-- Headline -->
            <h1 class="text-4xl sm:text-6xl md:text-7xl font-black tracking-tight max-w-4xl mx-auto leading-[1.1] mb-6">
                Your Encrypted <span class="text-gradient">Digital Fortress</span> for Passwords.
            </h1>

            <!-- Subtitle -->
            <p class="text-base sm:text-lg md:text-xl text-gray-400 max-w-2xl mx-auto leading-relaxed mb-10 font-normal">
                Military-grade <strong class="text-gray-200">AES-256-GCM</strong> authenticated encryption powered directly by your browser's Web Crypto API. Your master key and credentials never leave your device unencrypted.
            </p>

            <!-- Dual Action Buttons -->
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4 max-w-md mx-auto mb-14">
                <a href="<?php echo $userLoggedIn ? 'dashboard.php' : 'login.php'; ?>"
                   class="w-full sm:w-auto px-8 py-4 bg-primary hover:bg-primary-dark text-dark-bg font-extrabold text-sm rounded-2xl shadow-xl shadow-primary/30 transition-all hover:scale-[1.02] active:scale-[0.98] flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                    <span><?php echo $userLoggedIn ? 'Launch Vault' : 'Unlock Your Vault'; ?></span>
                </a>
                <a href="#crypto-demo"
                   class="w-full sm:w-auto px-7 py-4 bg-dark-card hover:bg-dark-surface text-gray-200 font-bold text-sm rounded-2xl border border-dark-border hover:border-primary/40 transition-all flex items-center justify-center gap-2">
                    <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>Test Live Crypto</span>
                </a>
            </div>

            <!-- Trust / Spec Badges -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 max-w-4xl mx-auto pt-6 border-t border-dark-border/80">
                <div class="p-4 rounded-xl bg-dark-card/50 border border-dark-border text-center">
                    <div class="text-xs font-bold text-primary mb-0.5">AES-256-GCM</div>
                    <div class="text-[11px] text-gray-400">Authenticated Encryption</div>
                </div>
                <div class="p-4 rounded-xl bg-dark-card/50 border border-dark-border text-center">
                    <div class="text-xs font-bold text-primary mb-0.5">PBKDF2 SHA-256</div>
                    <div class="text-[11px] text-gray-400">100,000 Iterations</div>
                </div>
                <div class="p-4 rounded-xl bg-dark-card/50 border border-dark-border text-center">
                    <div class="text-xs font-bold text-primary mb-0.5">Zero-Knowledge</div>
                    <div class="text-[11px] text-gray-400">Client-Side Derivation</div>
                </div>
                <div class="p-4 rounded-xl bg-dark-card/50 border border-dark-border text-center">
                    <div class="text-xs font-bold text-primary mb-0.5">Progressive Web App</div>
                    <div class="text-[11px] text-gray-400">Installable Everywhere</div>
                </div>
            </div>
        </section>

        <!-- ===== LIVE CRYPTOGRAPHY PLAYGROUND ===== -->
        <section id="crypto-demo" class="max-w-5xl mx-auto px-4 sm:px-6 py-16 scroll-mt-24">
            <div class="p-6 sm:p-10 rounded-3xl glass-card relative overflow-hidden">
                <div class="absolute top-0 right-0 w-64 h-64 bg-primary/10 rounded-full filter blur-3xl pointer-events-none"></div>

                <div class="text-center max-w-2xl mx-auto mb-8">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary/10 border border-primary/20 text-primary text-[11px] font-bold uppercase tracking-wider mb-3">
                        Interactive Live Demonstration
                    </div>
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight mb-2">
                        Inspect Real-Time WebCrypto Encryption
                    </h2>
                    <p class="text-xs sm:text-sm text-gray-400">
                        Type any sample credential below. Your browser will execute real-time PBKDF2 key derivation and AES-256-GCM encryption client-side with 0 network calls.
                    </p>
                </div>

                <!-- Interactive Demo Form -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-primary uppercase tracking-wider mb-2">
                                Plaintext Password (Simulated Input)
                            </label>
                            <input type="text" id="demoPlaintext" value="SuperSecretP@ssword2026!"
                                   class="w-full px-4 py-3 bg-dark-bg border border-dark-border rounded-xl text-white text-sm focus:outline-none focus:border-primary transition-all font-mono">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-primary uppercase tracking-wider mb-2">
                                Master Key (Your Passphrase)
                            </label>
                            <input type="text" id="demoMasterKey" value="MyMasterVaultKey#99"
                                   class="w-full px-4 py-3 bg-dark-bg border border-dark-border rounded-xl text-white text-sm focus:outline-none focus:border-primary transition-all font-mono">
                        </div>
                        <button type="button" onclick="runCryptoDemo()"
                                class="w-full py-3 bg-primary hover:bg-primary-dark text-dark-bg font-extrabold text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-primary/20 transition-all active:scale-[0.98]">
                            ⚡ Re-Encrypt with Fresh 12-byte IV
                        </button>
                    </div>

                    <!-- Live Ciphertext & Cryptographic Output -->
                    <div class="bg-dark-bg/90 border border-dark-border rounded-2xl p-5 space-y-3 font-mono text-xs">
                        <div class="flex items-center justify-between border-b border-dark-border pb-2 text-gray-400">
                            <span class="font-bold text-gray-300">Client-Side Output Inspector</span>
                            <span class="text-[10px] text-primary flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-primary animate-pulse"></span>
                                Local WebCrypto Active
                            </span>
                        </div>
                        <div>
                            <span class="text-gray-500 block text-[10px] uppercase font-bold">PBKDF2 Derived Key (AES-256):</span>
                            <div id="demoKeyHash" class="text-gray-300 break-all bg-dark-card p-2 rounded border border-dark-border/60 mt-1">Generating…</div>
                        </div>
                        <div>
                            <span class="text-gray-500 block text-[10px] uppercase font-bold">Initialization Vector (12 Bytes IV):</span>
                            <div id="demoIv" class="text-yellow-400 break-all bg-dark-card p-2 rounded border border-dark-border/60 mt-1">Generating…</div>
                        </div>
                        <div>
                            <span class="text-gray-500 block text-[10px] uppercase font-bold">Encrypted Payload Stored in DB (Base64):</span>
                            <div id="demoCiphertext" class="text-primary break-all bg-dark-card p-2 rounded border border-dark-border/60 mt-1">Generating…</div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-dark-border flex items-center justify-between text-[11px] text-gray-500">
                    <span>* Notice how the ciphertext changes on every encrypt due to unique random IV generation.</span>
                    <span class="text-primary font-semibold">Zero Plaintext Transmission Guaranteed</span>
                </div>
            </div>
        </section>

        <!-- ===== CORE SECURITY PILLARS ===== -->
        <section id="features" class="max-w-6xl mx-auto px-4 sm:px-6 py-16 scroll-mt-24">
            <div class="text-center max-w-2xl mx-auto mb-14">
                <h2 class="text-3xl sm:text-4xl font-extrabold tracking-tight mb-3">
                    Architected for <span class="text-gradient">Absolute Privacy</span>
                </h2>
                <p class="text-gray-400 text-sm sm:text-base">
                    Every design decision in SecureVault prioritizes mathematical security, privacy, and zero trust boundaries.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Pillar 1 -->
                <div class="p-7 rounded-3xl glass-card flex flex-col justify-between transition-all">
                    <div>
                        <div class="w-12 h-12 rounded-2xl bg-primary/10 border border-primary/30 flex items-center justify-center text-primary mb-6 shadow-md shadow-primary/10">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        </div>
                        <h3 class="text-lg font-bold text-white mb-2">Zero-Knowledge Architecture</h3>
                        <p class="text-gray-400 text-xs sm:text-sm leading-relaxed mb-4">
                            Your master password is never stored or transmitted. Encryption keys are generated solely inside your browser using 100,000 rounds of PBKDF2 key stretching.
                        </p>
                    </div>
                    <span class="text-xs text-primary font-bold">End-to-End Encrypted →</span>
                </div>

                <!-- Pillar 2 -->
                <div class="p-7 rounded-3xl glass-card flex flex-col justify-between transition-all">
                    <div>
                        <div class="w-12 h-12 rounded-2xl bg-primary/10 border border-primary/30 flex items-center justify-center text-primary mb-6 shadow-md shadow-primary/10">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        </div>
                        <h3 class="text-lg font-bold text-white mb-2">Installable Native PWA</h3>
                        <p class="text-gray-400 text-xs sm:text-sm leading-relaxed mb-4">
                            Install directly to your iOS, Android, macOS, or Windows home screen. Enjoy rapid load times, offline cache assets, and native app aesthetics.
                        </p>
                    </div>
                    <span class="text-xs text-primary font-bold">Cross-Platform Ready →</span>
                </div>

                <!-- Pillar 3 -->
                <div class="p-7 rounded-3xl glass-card flex flex-col justify-between transition-all">
                    <div>
                        <div class="w-12 h-12 rounded-2xl bg-primary/10 border border-primary/30 flex items-center justify-center text-primary mb-6 shadow-md shadow-primary/10">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        </div>
                        <h3 class="text-lg font-bold text-white mb-2">Anti-Phishing Verification</h3>
                        <p class="text-gray-400 text-xs sm:text-sm leading-relaxed mb-4">
                            Built-in origin security advisories protect users from spoofed domains and man-in-the-middle credential harvesting attempts.
                        </p>
                    </div>
                    <span class="text-xs text-primary font-bold">Strict Origin Guard →</span>
                </div>
            </div>
        </section>

        <!-- ===== SECURITY ARCHITECTURE & INFOGRAPHIC ===== -->
        <section id="architecture" class="max-w-6xl mx-auto px-4 sm:px-6 py-16 scroll-mt-24">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                <!-- Text Details -->
                <div class="lg:col-span-6 space-y-6">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary/10 border border-primary/20 text-primary text-xs font-bold uppercase tracking-wider">
                        Cryptographic Pipeline
                    </div>
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-white tracking-tight">
                        How SecureVault Protects Your Credentials
                    </h2>
                    <p class="text-gray-400 text-sm leading-relaxed">
                        Conventional cloud vaults often decrypt data in their cloud workers. In SecureVault, the cloud server is treated as an untrusted ciphertext storage layer.
                    </p>

                    <!-- Step-by-step -->
                    <div class="space-y-4 pt-2">
                        <div class="flex items-start gap-4 p-4 rounded-2xl bg-dark-card border border-dark-border">
                            <div class="w-8 h-8 rounded-xl bg-primary/20 text-primary font-bold text-sm flex items-center justify-center shrink-0">1</div>
                            <div>
                                <h4 class="text-sm font-bold text-white">Client Key Derivation</h4>
                                <p class="text-xs text-gray-400 mt-0.5">Master key + unique salt are hashed locally via PBKDF2 (SHA-256) into a 256-bit AES cryptographic key.</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-4 p-4 rounded-2xl bg-dark-card border border-dark-border">
                            <div class="w-8 h-8 rounded-xl bg-primary/20 text-primary font-bold text-sm flex items-center justify-center shrink-0">2</div>
                            <div>
                                <h4 class="text-sm font-bold text-white">AES-256-GCM Local Encryption</h4>
                                <p class="text-xs text-gray-400 mt-0.5">A 96-bit cryptographically secure IV is generated per secret. GCM mode ensures tamper-proof authenticity.</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-4 p-4 rounded-2xl bg-dark-card border border-dark-border">
                            <div class="w-8 h-8 rounded-xl bg-primary/20 text-primary font-bold text-sm flex items-center justify-center shrink-0">3</div>
                            <div>
                                <h4 class="text-sm font-bold text-white">Encrypted Transmission</h4>
                                <p class="text-xs text-gray-400 mt-0.5">Only IV + Ciphertext + Tag are stored in MySQL. Even with full database access, an attacker cannot decrypt credentials.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Infographic Image Card -->
                <div class="lg:col-span-6">
                    <div class="p-3 rounded-3xl bg-dark-card border border-dark-border shadow-2xl overflow-hidden group">
                        <div class="relative rounded-2xl overflow-hidden bg-dark-bg/80">
                            <img src="securevault-infographic.png" alt="SecureVault Security Infographic" class="w-full h-auto object-cover rounded-2xl group-hover:scale-[1.01] transition-transform duration-300">
                            <div class="absolute bottom-3 left-3 right-3 bg-dark-bg/90 backdrop-blur-md p-3 rounded-xl border border-dark-border/80 flex items-center justify-between text-xs">
                                <span class="text-gray-300 font-semibold">Zero-Knowledge Workflow Diagram</span>
                                <span class="text-primary font-mono text-[10px]">VERIFIED 100% CLIENT-SIDE</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ===== COMPARISON MATRIX ===== -->
        <section id="comparison" class="max-w-5xl mx-auto px-4 sm:px-6 py-16 scroll-mt-24">
            <div class="text-center max-w-2xl mx-auto mb-12">
                <h2 class="text-3xl font-extrabold tracking-tight mb-3">
                    How SecureVault Compares
                </h2>
                <p class="text-gray-400 text-sm">
                    Why privacy-conscious developers and power users choose SecureVault.
                </p>
            </div>

            <div class="overflow-x-auto rounded-3xl border border-dark-border bg-dark-card/60 backdrop-blur-md">
                <table class="w-full text-left text-xs sm:text-sm">
                    <thead>
                        <tr class="border-b border-dark-border bg-dark-surface/50 text-gray-400">
                            <th class="p-4 sm:p-5 font-bold">Feature / Security Standard</th>
                            <th class="p-4 sm:p-5 font-extrabold text-primary">SecureVault (PWA)</th>
                            <th class="p-4 sm:p-5 font-semibold text-gray-400">Generic Cloud Managers</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-dark-border text-gray-300">
                        <tr>
                            <td class="p-4 sm:p-5 font-medium text-white">Client-Side WebCrypto Derivation</td>
                            <td class="p-4 sm:p-5 text-primary font-bold">✅ 100% In-Browser</td>
                            <td class="p-4 sm:p-5 text-gray-400">⚠️ Often Cloud-Processed</td>
                        </tr>
                        <tr>
                            <td class="p-4 sm:p-5 font-medium text-white">Encryption Algorithm</td>
                            <td class="p-4 sm:p-5 text-primary font-bold">✅ AES-256-GCM + PBKDF2</td>
                            <td class="p-4 sm:p-5 text-gray-400">AES-256-CBC or proprietary</td>
                        </tr>
                        <tr>
                            <td class="p-4 sm:p-5 font-medium text-white">Open Source Codebase</td>
                            <td class="p-4 sm:p-5 text-primary font-bold">✅ 100% Open & Auditable</td>
                            <td class="p-4 sm:p-5 text-red-400">❌ Closed Proprietary</td>
                        </tr>
                        <tr>
                            <td class="p-4 sm:p-5 font-medium text-white">PWA Home Screen Install</td>
                            <td class="p-4 sm:p-5 text-primary font-bold">✅ Instant No-Store Install</td>
                            <td class="p-4 sm:p-5 text-gray-400">⚠️ Bloated App Store Downloads</td>
                        </tr>
                        <tr>
                            <td class="p-4 sm:p-5 font-medium text-white">Telemetry & Ad Trackers</td>
                            <td class="p-4 sm:p-5 text-primary font-bold">✅ Zero Tracking / No Cookies</td>
                            <td class="p-4 sm:p-5 text-red-400">❌ Heavy Analytics Trackers</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- ===== FREQUENTLY ASKED QUESTIONS ===== -->
        <section id="faq" class="max-w-4xl mx-auto px-4 sm:px-6 py-16 scroll-mt-24">
            <div class="text-center max-w-2xl mx-auto mb-12">
                <h2 class="text-3xl font-extrabold tracking-tight mb-3">
                    Frequently Asked Questions
                </h2>
                <p class="text-gray-400 text-sm">
                    Everything you need to know about our security architecture and master keys.
                </p>
            </div>

            <div class="space-y-4">
                <!-- FAQ 1 -->
                <details class="group rounded-2xl bg-dark-card border border-dark-border p-5 transition-all">
                    <summary class="flex items-center justify-between font-bold text-white text-sm cursor-pointer list-none">
                        <span>Can SecureVault or any server administrator read my stored passwords?</span>
                        <svg class="w-5 h-5 text-primary transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </summary>
                    <p class="mt-3 text-xs sm:text-sm text-gray-400 leading-relaxed">
                        No. Plaintext passwords never leave your web browser. All cryptographic operations occur on your device via the native browser WebCrypto API before any data is sent over the network. The server only receives encrypted ciphertexts and random authentication tags.
                    </p>
                </details>

                <!-- FAQ 2 -->
                <details class="group rounded-2xl bg-dark-card border border-dark-border p-5 transition-all">
                    <summary class="flex items-center justify-between font-bold text-white text-sm cursor-pointer list-none">
                        <span>What happens if I lose or forget my Master Key?</span>
                        <svg class="w-5 h-5 text-primary transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </summary>
                    <p class="mt-3 text-xs sm:text-sm text-gray-400 leading-relaxed">
                        Because SecureVault is zero-knowledge, nobody (including us) possesses a master recovery backdoor. If you lose your master key, the encrypted vault cannot be decrypted. We recommend memorizing your master passphrase or keeping a physical offline backup.
                    </p>
                </details>

                <!-- FAQ 3 -->
                <details class="group rounded-2xl bg-dark-card border border-dark-border p-5 transition-all">
                    <summary class="flex items-center justify-between font-bold text-white text-sm cursor-pointer list-none">
                        <span>How do I install SecureVault as a Progressive Web App (PWA)?</span>
                        <svg class="w-5 h-5 text-primary transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </summary>
                    <p class="mt-3 text-xs sm:text-sm text-gray-400 leading-relaxed">
                        On mobile (iOS Safari / Android Chrome), tap <strong>Share</strong> or <strong>Browser Menu (⋮)</strong> and select <strong>"Add to Home Screen"</strong>. On Desktop Chrome/Edge, click the install icon in the URL address bar to install SecureVault as a standalone desktop app.
                    </p>
                </details>

                <!-- FAQ 4 -->
                <details class="group rounded-2xl bg-dark-card border border-dark-border p-5 transition-all">
                    <summary class="flex items-center justify-between font-bold text-white text-sm cursor-pointer list-none">
                        <span>Is the source code open for public inspection?</span>
                        <svg class="w-5 h-5 text-primary transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </summary>
                    <p class="mt-3 text-xs sm:text-sm text-gray-400 leading-relaxed">
                        Yes! SecureVault is 100% open source. You can inspect all frontend scripts, encryption routines, and backend endpoints on our official <a href="https://github.com/RonanStack24/SecureVault" target="_blank" rel="noopener noreferrer" class="text-primary hover:underline font-semibold">GitHub Repository</a>.
                    </p>
                </details>
            </div>
        </section>

        <!-- ===== BOTTOM CALL TO ACTION ===== -->
        <section class="max-w-5xl mx-auto px-4 sm:px-6 py-20">
            <div class="p-8 sm:p-14 rounded-3xl bg-gradient-to-br from-dark-card via-dark-surface to-primary/10 border border-primary/30 text-center relative overflow-hidden shadow-2xl">
                <div class="relative z-10 max-w-2xl mx-auto">
                    <div class="w-14 h-14 rounded-2xl bg-primary/20 border border-primary/40 flex items-center justify-center text-primary mx-auto mb-6 shadow-lg shadow-primary/20">
                        <img src="logo.svg" alt="Logo" class="w-8 h-8">
                    </div>
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-white tracking-tight mb-4">
                        Secure Your Digital Life in Seconds.
                    </h2>
                    <p class="text-gray-300 text-sm sm:text-base leading-relaxed mb-8">
                        Experience the peace of mind that comes with zero-knowledge cryptography. Free, open source, and accessible from any device.
                    </p>
                    <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                        <a href="<?php echo $userLoggedIn ? 'dashboard.php' : 'login.php?register=1'; ?>"
                           class="w-full sm:w-auto px-8 py-4 bg-primary hover:bg-primary-dark text-dark-bg font-extrabold text-sm rounded-2xl shadow-xl shadow-primary/30 transition-all hover:scale-[1.02] active:scale-[0.98]">
                            <?php echo $userLoggedIn ? 'Go to Vault' : 'Create Free Secure Vault'; ?>
                        </a>
                        <a href="<?php echo $userLoggedIn ? 'dashboard.php' : 'login.php'; ?>"
                           class="w-full sm:w-auto px-8 py-4 bg-dark-bg text-gray-200 font-bold text-sm rounded-2xl border border-dark-border hover:border-primary/40 transition-all">
                            <?php echo $userLoggedIn ? 'Dashboard' : 'Sign In'; ?>
                        </a>
                    </div>
                </div>
            </div>
        </section>

    </main>

    <!-- ===== FOOTER ===== -->
    <footer class="border-t border-dark-border/80 bg-dark-bg py-12 text-gray-400 text-xs relative z-10">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 flex flex-col md:flex-row items-center justify-between gap-6 text-center md:text-left">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl bg-primary/10 border border-primary/30 flex items-center justify-center">
                    <img src="logo.svg" alt="Logo" class="w-5 h-5">
                </div>
                <div>
                    <span class="font-extrabold text-white tracking-tight">SECURE<span class="text-primary font-black">VAULT</span></span>
                    <p class="text-[10px] text-gray-500">Zero-Knowledge Personal Vault · v1.2 PWA Edition</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-center gap-6 font-semibold">
                <a href="#features" class="hover:text-primary transition-colors">Features</a>
                <a href="#crypto-demo" class="hover:text-primary transition-colors">Crypto Demo</a>
                <a href="#architecture" class="hover:text-primary transition-colors">Security Architecture</a>
                <a href="https://github.com/RonanStack24/SecureVault" target="_blank" rel="noopener noreferrer" class="hover:text-primary transition-colors flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.477 2 12c0 4.42 2.865 8.166 6.839 9.489.5.092.682-.217.682-.482 0-.237-.008-.866-.013-1.7-2.782.603-3.369-1.34-3.369-1.34-.454-1.156-1.11-1.463-1.11-1.463-.908-.62.069-.608.069-.608 1.003.07 1.531 1.03 1.531 1.03.892 1.529 2.341 1.087 2.91.831.092-.646.35-1.086.636-1.336-2.22-.253-4.555-1.11-4.555-4.943 0-1.091.39-1.984 1.029-2.683-.103-.253-.446-1.27.098-2.647 0 0 .84-.269 2.75 1.025A9.578 9.578 0 0112 6.836c.85.004 1.705.114 2.504.336 1.909-1.294 2.747-1.025 2.747-1.025.546 1.377.203 2.394.1 2.647.64.699 1.028 1.592 1.028 2.683 0 3.842-2.339 4.687-4.566 4.935.359.309.678.919.678 1.852 0 1.336-.012 2.415-.012 2.743 0 .267.18.578.688.48C19.138 20.161 22 16.416 22 12c0-5.523-4.477-10-10-10z"/></svg>
                    GitHub
                </a>
            </div>

            <div class="text-[11px] text-gray-500">
                Created by <strong class="text-gray-300">Ronan Antoque</strong> · Protected under MIT License
            </div>
        </div>
    </footer>

    <!-- ===== CLIENT-SIDE WEBCRYPTO DEMO ENGINE & MOBILE NAV ===== -->
    <script>
        function toggleMobileNav() {
            const drawer = document.getElementById('mobileDrawer');
            const iconOpen = document.getElementById('menuIconOpen');
            const iconClose = document.getElementById('menuIconClose');
            if (drawer) {
                const isClosed = drawer.classList.contains('hidden');
                drawer.classList.toggle('hidden', !isClosed);
                if (iconOpen) iconOpen.classList.toggle('hidden', isClosed);
                if (iconClose) iconClose.classList.toggle('hidden', !isClosed);
            }
        }

        async function deriveDemoKey(passphrase, saltHex) {
            const enc = new TextEncoder();
            const keyMaterial = await window.crypto.subtle.importKey(
                "raw", enc.encode(passphrase), { name: "PBKDF2" }, false, ["deriveBits", "deriveKey"]
            );
            const saltBytes = enc.encode(saltHex);
            return window.crypto.subtle.deriveKey(
                { name: "PBKDF2", salt: saltBytes, iterations: 100000, hash: "SHA-256" },
                keyMaterial, { name: "AES-GCM", length: 256 }, true, ["encrypt", "decrypt"]
            );
        }

        function bufToHex(buffer) {
            return Array.from(new Uint8Array(buffer)).map(b => b.toString(16).padStart(2, '0')).join('');
        }

        function bufToBase64(buffer) {
            let binary = '';
            const bytes = new Uint8Array(buffer);
            for (let i = 0; i < bytes.byteLength; i++) binary += String.fromCharCode(bytes[i]);
            return window.btoa(binary);
        }

        async function runCryptoDemo() {
            try {
                const plaintext = document.getElementById('demoPlaintext').value || 'SecureSecret!';
                const masterKey = document.getElementById('demoMasterKey').value || 'MasterVaultKey123';
                const saltHex = "a1b2c3d4e5f60718293a4b5c6d7e8f90"; // sample fixed salt for demo

                const enc = new TextEncoder();
                const key = await deriveDemoKey(masterKey, saltHex);

                // Export key bits for inspection
                const rawKeyBits = await window.crypto.subtle.exportKey("raw", key);
                document.getElementById('demoKeyHash').textContent = bufToHex(rawKeyBits).substring(0, 32) + '… (256-bit AES Key)';

                // 12-byte random IV
                const iv = window.crypto.getRandomValues(new Uint8Array(12));
                document.getElementById('demoIv').textContent = bufToHex(iv) + ' (96 bits)';

                // Authenticated AES-GCM encryption
                const encrypted = await window.crypto.subtle.encrypt(
                    { name: "AES-GCM", iv: iv }, key, enc.encode(plaintext)
                );

                const result = new Uint8Array(iv.length + encrypted.byteLength);
                result.set(iv, 0);
                result.set(new Uint8Array(encrypted), iv.length);

                document.getElementById('demoCiphertext').textContent = bufToBase64(result);
            } catch (err) {
                console.error("Crypto Demo Error:", err);
            }
        }

        // Run once on initial load
        document.addEventListener('DOMContentLoaded', runCryptoDemo);
    </script>
    <script src="js/pwa.js"></script>
</body>
</html>
