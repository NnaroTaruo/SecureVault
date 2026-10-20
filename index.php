<?php
/**
 * SecureVault — Product Landing Page
 */

require_once 'config.php';
require_once 'auth.php';

if (defined('MAINTENANCE_MODE') && MAINTENANCE_MODE) {
    require_once __DIR__ . '/maintenance.php';
    exit;
}

$userLoggedIn = isLoggedIn() && verifySession();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#080E1A">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="SecureVault">
    <meta name="description" content="Keep your passwords in one place. Save your logins, find them quickly, and access your saved vault offline.">
    <title>SecureVault — Your passwords, in one place</title>
    <link rel="manifest" href="manifest.json">
    <link rel="icon" href="logo.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="icon-192.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { color-scheme: dark; --bg: #080E1A; --surface: #0F1729; --line: #253147; --text: #F3F6FA; --muted: #A9B5C6; --green: #00E676; }
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; scroll-padding-top: 24px; }
        body { margin: 0; background: var(--bg); color: var(--text); font-family: 'Inter', sans-serif; font-size: 16px; line-height: 1.65; }
        a { color: inherit; text-decoration: none; text-underline-offset: 4px; }
        button, summary { -webkit-tap-highlight-color: transparent; }
        button { font: inherit; }
        button, a, summary { touch-action: manipulation; }
        a:focus-visible, button:focus-visible, summary:focus-visible { outline: 2px solid var(--green); outline-offset: 5px; }
        ::selection { background: var(--green); color: var(--bg); }
        .container { width: min(1080px, calc(100% - 48px)); margin-inline: auto; }
        .skip-link { position: absolute; top: 12px; left: 12px; z-index: 10; padding: 8px 16px; background: var(--green); color: var(--bg); transform: translateY(-150%); }
        .skip-link:focus { transform: translateY(0); }
        .site-header { border-bottom: 1px solid var(--line); }
        .header-inner { min-height: 88px; display: flex; align-items: center; justify-content: space-between; gap: 24px; }
        .brand { display: inline-flex; align-items: center; gap: 10px; font-size: 20px; font-weight: 700; letter-spacing: -0.03em; white-space: nowrap; }
        .brand img { width: 34px; height: 34px; }
        .brand span { color: var(--green); }
        .header-links { display: flex; align-items: center; gap: 32px; font-size: 14px; font-weight: 500; }
        .quiet-link { color: var(--muted); }
        .quiet-link:hover, .text-link:hover { color: var(--green); }
        .header-action { min-height: 44px; display: inline-flex; align-items: center; }
        .hero { display: grid; grid-template-columns: 1.15fr 1fr; align-items: center; gap: 64px; padding-block: 88px 80px; }
        h1, h2, h3, p { margin: 0; }
        h1 { max-width: 10ch; font-size: clamp(42px, 5.2vw, 64px); font-weight: 700; line-height: 1.08; letter-spacing: -0.035em; text-wrap: balance; }
        .hero-copy { max-width: 44ch; margin-top: 24px; color: var(--muted); font-size: 18px; }
        .hero-actions { margin-top: 32px; display: flex; align-items: center; flex-wrap: wrap; gap: 24px; }
        .button { display: inline-flex; align-items: center; justify-content: center; gap: 12px; min-height: 52px; padding: 12px 24px; border: 0; border-radius: 10px; background: var(--green); color: var(--bg); font-weight: 700; cursor: pointer; transition: background-color 160ms ease-out; }
        .button:hover { background: #3BEF98; }
        .button svg { width: 18px; height: 18px; }
        .text-link { color: var(--text); font-size: 14px; font-weight: 500; text-decoration: underline; }
        .hero-note { margin-top: 18px; color: var(--muted); font-size: 13px; }
        .vault-preview { margin: 0; min-width: 0; }
        .vault-scene { display: block; width: 100%; height: auto; overflow: visible; }
        .vault-scene text { font-family: 'Inter', sans-serif; }
        .motion-card-title { fill: var(--text); font-size: 14px; font-weight: 500; }
        .motion-card-password { fill: var(--muted); font-size: 13px; letter-spacing: 3px; }
        .motion-route { fill: none; stroke: #31584F; stroke-width: 1; }
        .motion-flow { fill: none; stroke: #6CF0B1; stroke-width: 2; stroke-linecap: round; stroke-dasharray: 4 40; }
        .motion-core-label { fill: #A3D8C5; font-size: 11px; font-weight: 600; letter-spacing: 3px; }
        .motion-controls { display: flex; align-items: center; justify-content: center; gap: 16px; min-height: 44px; }
        figcaption { color: var(--muted); font-size: 12px; }
        .motion-toggle { min-height: 44px; padding: 8px 0; border: 0; background: transparent; color: var(--text); font-size: 12px; text-decoration: underline; text-underline-offset: 4px; cursor: pointer; }
        .motion-toggle:hover { color: var(--green); }
        .motion-toggle[hidden] { display: none; }
        .getting-started { border-top: 1px solid var(--line); padding-block: 56px 64px; }
        h2 { font-size: clamp(26px, 3vw, 32px); font-weight: 600; line-height: 1.25; letter-spacing: -0.025em; text-wrap: balance; }
        .steps { display: grid; grid-template-columns: repeat(3, 1fr); gap: 40px; margin: 32px 0 0; padding: 0; list-style: none; counter-reset: step; }
        .steps li { counter-increment: step; }
        .steps li::before { content: counter(step) "."; display: block; margin-bottom: 12px; color: var(--green); font-size: 14px; font-weight: 600; font-variant-numeric: tabular-nums; }
        h3 { margin-bottom: 8px; font-size: 17px; font-weight: 600; }
        .steps p { max-width: 32ch; color: var(--muted); font-size: 14px; }
        .faq { display: grid; grid-template-columns: 1fr 1.4fr; gap: 64px; padding-block: 56px 64px; border-top: 1px solid var(--line); }
        .faq-intro { margin-top: 12px; max-width: 25ch; color: var(--muted); font-size: 14px; }
        .faq-list { min-width: 0; }
        details { border-bottom: 1px solid var(--line); }
        details:first-child { border-top: 1px solid var(--line); }
        summary { display: flex; justify-content: space-between; align-items: center; gap: 20px; padding-block: 20px; list-style: none; font-size: 14px; font-weight: 500; cursor: pointer; }
        summary::-webkit-details-marker { display: none; }
        summary svg { width: 16px; height: 16px; flex-shrink: 0; color: var(--muted); }
        details[open] summary svg { transform: rotate(180deg); }
        details p { padding-bottom: 20px; color: var(--muted); font-size: 14px; }
        .site-footer { border-top: 1px solid var(--line); padding-block: 28px; color: var(--muted); font-size: 12px; }
        .footer-inner { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; }
        .footer-links { display: flex; gap: 24px; }
        .official-site { margin-top: 6px; }
        .install-banner { position: fixed; right: 24px; bottom: 24px; z-index: 20; width: min(380px, calc(100% - 48px)); align-items: center; gap: 16px; padding: 20px; border-radius: 12px; background: #19263B; box-shadow: 0 12px 32px #0006; }
        .install-banner p { margin-bottom: 12px; color: #DFE8F6; font-size: 14px; }
        .install-banner .button { min-height: 44px; padding: 8px 16px; font-size: 14px; }
        .dismiss-install { display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; width: 44px; height: 44px; padding: 0; border: 0; border-radius: 8px; background: transparent; color: #DFE8F6; cursor: pointer; }
        .dismiss-install:hover { background: #25364F; }
        .dismiss-install svg { width: 20px; height: 20px; }
        .hidden { display: none; }
        .flex { display: flex; }
        /* The illustration stays complete without JavaScript; visible motion is opt-in. */
        @media (prefers-reduced-motion: no-preference) {
            @keyframes vault-orbit {
                to { transform: rotate(360deg); }
            }
            @keyframes vault-card-float {
                0%, 100% { transform: translateY(0); }
                50% { transform: translateY(-7px); }
            }
            @keyframes vault-data-flow {
                to { stroke-dashoffset: -88; }
            }
            @keyframes vault-shackle-close {
                0%, 8%, 100% { transform: translateY(-9px) rotate(12deg); }
                22%, 86% { transform: translateY(0) rotate(0); }
            }
            @keyframes vault-seal {
                0%, 14% { stroke-dashoffset: 480; }
                44%, 86% { stroke-dashoffset: 0; }
                100% { stroke-dashoffset: -480; }
            }
            .motion-enabled .motion-orbit { transform-origin: 250px 222px; animation: vault-orbit 22s linear infinite; }
            .motion-enabled .motion-orbit-inner { animation-duration: 32s; animation-direction: reverse; }
            .motion-enabled .motion-card { animation: vault-card-float 6s cubic-bezier(0.37, 0, 0.63, 1) infinite; }
            .motion-enabled .motion-card-work { animation-delay: -2s; }
            .motion-enabled .motion-card-shopping { animation-delay: -4s; }
            .motion-enabled .motion-flow { animation: vault-data-flow 2.4s linear infinite; }
            .motion-enabled .motion-shackle { transform-origin: 268px 220px; animation: vault-shackle-close 8s cubic-bezier(0.16, 1, 0.3, 1) infinite; }
            .motion-enabled .motion-seal { stroke-dasharray: 480; animation: vault-seal 8s cubic-bezier(0.16, 1, 0.3, 1) infinite; }
            .vault-preview .motion-layer { animation-play-state: paused; }
            .vault-preview.motion-running .motion-layer { animation-play-state: running; }
            .button svg, summary svg { transition: transform 180ms cubic-bezier(0.16, 1, 0.3, 1); }
            .button:is(:hover, :focus-visible) svg { transform: translateX(3px); }
        }
        @media (max-width: 760px) {
            .hero { grid-template-columns: 1fr; gap: 40px; padding-block: 56px; }
            h1 { max-width: 12ch; }
            .hero-copy { max-width: 42ch; font-size: 17px; }
            .vault-preview { width: 100%; max-width: 480px; }
            .steps { gap: 24px; }
            .faq { grid-template-columns: 1fr; gap: 28px; }
            .faq-intro { max-width: none; }
        }
        @media (max-width: 540px) {
            .container { width: calc(100% - 40px); }
            .header-inner { min-height: 76px; gap: 16px; }
            .brand { font-size: 18px; gap: 8px; }
            .brand img { width: 28px; height: 28px; }
            .header-links { gap: 0; }
            .header-section-link { display: none; }
            .hero { padding-block: 40px; gap: 32px; }
            .hero-copy { margin-top: 20px; }
            .hero-actions { margin-top: 28px; gap: 20px; }
            .hero-actions .button { width: 100%; }
            .getting-started, .faq { padding-block: 40px; }
            .steps { grid-template-columns: 1fr; gap: 24px; margin-top: 28px; }
            .steps li { position: relative; padding-left: 32px; }
            .steps li::before { position: absolute; top: 2px; left: 0; }
            .steps p { max-width: none; }
            .footer-inner { flex-direction: column; gap: 16px; }
            .install-banner { right: 20px; bottom: 20px; width: calc(100% - 40px); }
        }
        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
            .button { transition: none; }
        }
    </style>
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to content</a>
    <header class="site-header">
        <div class="container header-inner">
            <a class="brand" href="index.php" aria-label="SecureVault home">
                <img src="logo.svg" alt="" width="34" height="34">
                <div>Secure<span>Vault</span></div>
            </a>
            <nav class="header-links" aria-label="Main navigation">
                <a class="quiet-link header-section-link" href="#how-it-works">How it works</a>
                <a class="header-action" href="<?php echo $userLoggedIn ? 'dashboard.php' : 'login.php'; ?>"><?php echo $userLoggedIn ? 'My vault' : 'Sign in'; ?></a>
            </nav>
        </div>
    </header>

    <main id="main-content" class="container">
        <section class="hero" aria-labelledby="hero-title">
            <div>
                <h1 id="hero-title"><?php echo $userLoggedIn ? 'Your vault is ready.' : 'Your passwords. One place.'; ?></h1>
                <p class="hero-copy">Keep your logins organized and find the password you need, when you need it.</p>
                <div class="hero-actions">
                    <a class="button" href="<?php echo $userLoggedIn ? 'dashboard.php' : 'login.php?register=1'; ?>">
                        <?php echo $userLoggedIn ? 'Open my vault' : 'Create account'; ?>
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/></svg>
                    </a>
                    <?php if (!$userLoggedIn): ?>
                        <a class="text-link" href="login.php">Sign in instead</a>
                    <?php endif; ?>
                </div>
                <p class="hero-note">Free to use. Works in your browser.</p>
            </div>

            <figure id="vaultMotion" class="vault-preview" aria-labelledby="preview-caption">
                <svg class="vault-scene" viewBox="0 0 500 440" width="500" height="440" role="img" aria-labelledby="motion-title" aria-describedby="motion-description">
                    <title id="motion-title">Your logins, together in a vault</title>
                    <desc id="motion-description">Three example login cards surround a central lock, connected by moving trails and orbiting rings. This is an illustration.</desc>
                    <defs>
                        <radialGradient id="vault-light">
                            <stop stop-color="#17483B" stop-opacity="0.65"/>
                            <stop offset="1" stop-color="#080E1A" stop-opacity="0"/>
                        </radialGradient>
                        <linearGradient id="vault-metal" x1="0" y1="0" x2="1" y2="1">
                            <stop stop-color="#1B3D3A"/>
                            <stop offset="1" stop-color="#0F1C2A"/>
                        </linearGradient>
                        <linearGradient id="vault-orbit-light" x1="0" y1="0" x2="1" y2="1">
                            <stop stop-color="#00E676"/>
                            <stop offset="1" stop-color="#00E676" stop-opacity="0.1"/>
                        </linearGradient>
                    </defs>

                    <ellipse cx="250" cy="222" rx="210" ry="195" fill="url(#vault-light)"/>
                    <circle cx="250" cy="222" r="166" fill="none" stroke="#233D44"/>
                    <circle cx="250" cy="222" r="130" fill="none" stroke="#1C313D" stroke-dasharray="2 10"/>
                    <g class="motion-orbit motion-layer">
                        <circle cx="250" cy="222" r="166" fill="none" stroke="url(#vault-orbit-light)" stroke-width="2" stroke-linecap="round" stroke-dasharray="100 943"/>
                        <circle cx="416" cy="222" r="4" fill="#75F4B7"/>
                        <circle cx="84" cy="222" r="2.5" fill="#427D6C"/>
                    </g>
                    <g class="motion-orbit motion-orbit-inner motion-layer">
                        <circle cx="250" cy="222" r="130" fill="none" stroke="#3B8D72" stroke-linecap="round" stroke-dasharray="48 769"/>
                        <circle cx="380" cy="222" r="3" fill="#58C99C"/>
                    </g>

                    <path class="motion-route" d="M180 136C217 136 204 194 225 211"/>
                    <path class="motion-route" d="M326 207C302 207 297 225 278 236"/>
                    <path class="motion-route" d="M221 348C265 348 269 302 259 272"/>
                    <path class="motion-flow motion-layer" d="M180 136C217 136 204 194 225 211"/>
                    <path class="motion-flow motion-layer" d="M326 207C302 207 297 225 278 236"/>
                    <path class="motion-flow motion-layer" d="M221 348C265 348 269 302 259 272"/>

                    <g>
                        <polygon points="250,143 318,182 318,260 250,300 182,260 182,182" fill="url(#vault-metal)" stroke="#366D5C"/>
                        <polygon class="motion-seal motion-layer" points="250,143 318,182 318,260 250,300 182,260 182,182" fill="none" stroke="#66E8AD" stroke-width="2" stroke-linejoin="round"/>
                        <polygon points="250,158 305,190 305,252 250,285 195,252 195,190" fill="none" stroke="#2B514A"/>
                        <path class="motion-shackle motion-layer" d="M232 222V204a18 18 0 0 1 36 0v18" fill="none" stroke="#B0F7D3" stroke-width="5" stroke-linecap="round"/>
                        <rect x="222" y="220" width="56" height="43" rx="10" fill="#00E676"/>
                        <circle cx="250" cy="237" r="4" fill="#103428"/>
                        <path d="M250 240v7" stroke="#103428" stroke-width="3" stroke-linecap="round"/>
                        <text class="motion-core-label" x="251" y="279" text-anchor="middle">VAULT</text>
                    </g>

                    <g class="motion-card motion-layer">
                        <rect x="24" y="102" width="156" height="66" rx="12" fill="#142630" stroke="#34504D"/>
                        <rect x="38" y="119" width="32" height="32" rx="8" fill="#244137"/>
                        <g fill="none" stroke="#96E6BE" stroke-width="1.5" stroke-linejoin="round">
                            <rect x="44" y="128" width="20" height="14" rx="2"/>
                            <path d="m45 129 9 7 9-7"/>
                        </g>
                        <text class="motion-card-title" x="82" y="130">Email</text>
                        <text class="motion-card-password" x="82" y="150">••••••</text>
                    </g>
                    <g class="motion-card motion-card-work motion-layer">
                        <rect x="326" y="174" width="150" height="66" rx="12" fill="#142630" stroke="#34504D"/>
                        <rect x="340" y="191" width="32" height="32" rx="8" fill="#244137"/>
                        <g fill="none" stroke="#96E6BE" stroke-width="1.5" stroke-linejoin="round">
                            <rect x="346" y="201" width="20" height="14" rx="2"/>
                            <path d="M352 201v-4h8v4m-14 7h20m-10-2v4"/>
                        </g>
                        <text class="motion-card-title" x="384" y="202">Work</text>
                        <text class="motion-card-password" x="384" y="222">••••••</text>
                    </g>
                    <g class="motion-card motion-card-shopping motion-layer">
                        <rect x="59" y="315" width="162" height="66" rx="12" fill="#142630" stroke="#34504D"/>
                        <rect x="73" y="332" width="32" height="32" rx="8" fill="#244137"/>
                        <g fill="none" stroke="#96E6BE" stroke-width="1.5" stroke-linejoin="round">
                            <path d="M80 343h18l-1 14H81l-1-14Zm4 0v-3a5 5 0 0 1 10 0v3"/>
                        </g>
                        <text class="motion-card-title" x="117" y="343">Shopping</text>
                        <text class="motion-card-password" x="117" y="363">••••••</text>
                    </g>
                </svg>
                <figcaption class="motion-controls">
                    <span id="preview-caption">Illustrative vault animation</span>
                    <button id="motionToggle" class="motion-toggle" type="button" hidden>Pause animation</button>
                </figcaption>
            </figure>
        </section>

        <section id="how-it-works" class="getting-started" aria-labelledby="steps-title">
            <h2 id="steps-title" data-scroll-reveal>Three steps to your first saved password.</h2>
            <ol class="steps">
                <li data-scroll-reveal><h3>Create your account</h3><p>Choose a master key. You’ll use it to sign in and unlock your passwords.</p></li>
                <li data-scroll-reveal data-reveal-delay="90"><h3>Add your logins</h3><p>Save a username and password. Account passwords are encrypted in your browser before saving.</p></li>
                <li data-scroll-reveal data-reveal-delay="180"><h3>Find what you need</h3><p>Search or browse by category, then enter your master key to reveal a password.</p></li>
            </ol>
        </section>

        <section class="faq" aria-labelledby="faq-title">
            <div data-scroll-reveal>
                <h2 id="faq-title">A few things to know.</h2>
                <p class="faq-intro">The essentials before you get started.</p>
            </div>
            <div class="faq-list">
                <details data-scroll-reveal="faq">
                    <summary>What is my master key?<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg></summary>
                    <p>It’s the password you choose when creating your SecureVault account. You’ll need it to sign in and reveal saved passwords. Keep it somewhere safe; there’s no forgotten-password reset.</p>
                </details>
                <details data-scroll-reveal="faq" data-reveal-delay="80">
                    <summary>Can I use my vault offline?<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg></summary>
                    <p>Open your vault online first to save an encrypted copy on this device. You can then open the saved vault and reveal passwords in the same browser while offline. Signing in, saving changes, and deleting accounts need an internet connection.</p>
                </details>
                <details data-scroll-reveal="faq" data-reveal-delay="160">
                    <summary>Do I need to install anything?<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg></summary>
                    <p>No. Use SecureVault in your browser, or choose Install app when your browser offers it for a shortcut on your home screen or desktop.</p>
                </details>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container footer-inner" data-scroll-reveal>
            <div>
                <p>SecureVault · Made by Ronan Antoque</p>
                <p class="official-site">Before signing in, check the address: securevault.great-site.net</p>
            </div>
            <div class="footer-links">
                <a class="quiet-link" href="#how-it-works">How it works</a>
                <a class="quiet-link" href="https://github.com/RonanStack24/SecureVault" target="_blank" rel="noopener noreferrer">Source code</a>
            </div>
        </div>
    </footer>

    <!-- Keep the shared PWA install hooks available when the browser offers installation. -->
    <aside id="installBanner" class="install-banner hidden" aria-label="Install SecureVault">
        <div>
            <p>Keep SecureVault a tap away.</p>
            <button type="button" class="button" onclick="installApp()">Install app</button>
        </div>
        <button type="button" class="dismiss-install" onclick="dismissInstallBanner()" aria-label="Dismiss installation suggestion">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="m6 6 12 12M6 18 18 6"/></svg>
        </button>
    </aside>
    <script>
        (() => {
            const scene = document.getElementById('vaultMotion');
            const toggle = document.getElementById('motionToggle');
            if (!scene || !toggle || !('IntersectionObserver' in window)) return;

            const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
            const activeReveals = new Set();
            let visible = false;
            let paused = false;
            const updateMotion = () => {
                scene.classList.toggle('motion-enabled', !reducedMotion.matches);
                scene.classList.toggle('motion-running', visible && !document.hidden && !paused && !reducedMotion.matches);
                toggle.hidden = reducedMotion.matches;
                toggle.textContent = paused ? 'Play animation' : 'Pause animation';
                if (paused || reducedMotion.matches || document.hidden) {
                    activeReveals.forEach(animation => animation.finish());
                }
            };

            // Animate on entry without hiding content before JavaScript or observation succeeds.
            const observer = new IntersectionObserver(entries => {
                entries.forEach(entry => {
                    if (entry.target === scene) {
                        visible = entry.isIntersecting;
                        updateMotion();
                    } else if (entry.isIntersecting) {
                        const target = entry.target;
                        observer.unobserve(target);
                        if (reducedMotion.matches || paused || document.hidden || !target.animate || target.contains(document.activeElement)) return;
                        const animation = target.animate([
                            { opacity: 0.8, transform: target.dataset.scrollReveal === 'faq' ? 'translateX(20px)' : 'translateY(28px)' },
                            { opacity: 1, transform: 'none' }
                        ], {
                            duration: 620,
                            delay: Number(target.dataset.revealDelay) || 0,
                            easing: 'cubic-bezier(0.16, 1, 0.3, 1)',
                            fill: 'backwards'
                        });
                        activeReveals.add(animation);
                        animation.onfinish = () => activeReveals.delete(animation);
                    }
                });
            }, { threshold: 0.05 });
            observer.observe(scene);
            document.querySelectorAll('[data-scroll-reveal]').forEach(target => observer.observe(target));
            toggle.addEventListener('click', () => { paused = !paused; updateMotion(); });
            reducedMotion.addEventListener('change', updateMotion);
            document.addEventListener('visibilitychange', updateMotion);
            updateMotion();
        })();
    </script>
    <script src="js/pwa.js"></script>
</body>
</html>
