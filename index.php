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
        .motion-card-password { fill: #C3D4CD; font-size: 15px; letter-spacing: 3px; }
        .motion-plain { opacity: 0; letter-spacing: 0.5px; }
        .motion-phase { fill: #B8D5C8; font-size: 15px; font-weight: 500; }
        .motion-phase-save, .motion-phase-locked, .motion-saved { opacity: 0; }
        .motion-core-label { fill: #A3D8C5; font-size: 11px; font-weight: 600; letter-spacing: 3px; }
        .motion-controls { display: flex; align-items: center; justify-content: flex-end; min-height: 44px; }
        figcaption { color: var(--muted); font-size: 12px; }
        .sr-only { position: absolute; width: 1px; height: 1px; margin: -1px; padding: 0; overflow: hidden; clip-path: inset(50%); white-space: nowrap; border: 0; }
        .motion-toggle { display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; width: 44px; height: 44px; padding: 12px; border: 1px solid var(--line); border-radius: 50%; background: transparent; color: var(--muted); cursor: pointer; }
        .motion-toggle:hover { color: var(--green); border-color: var(--green); }
        .motion-toggle .motion-icon { display: block; width: 18px; height: 18px; }
        .motion-toggle[hidden] { display: none; }
        .getting-started { border-top: 1px solid var(--line); padding-block: 56px 64px; }
        h2 { font-size: clamp(26px, 3vw, 32px); font-weight: 600; line-height: 1.25; letter-spacing: -0.025em; text-wrap: balance; }
        .walkthrough-intro { margin-top: 12px; color: var(--muted); font-size: 14px; }
        .walkthrough { display: grid; grid-template-columns: 1fr 1fr; align-items: start; gap: 64px; margin-top: 32px; }
        .walkthrough-preview { position: sticky; top: 24px; margin: 0; min-width: 0; }
        .walkthrough-preview svg { display: block; width: 100%; height: auto; }
        .walkthrough-preview text { font-family: 'Inter', sans-serif; }
        .demo-title { fill: var(--text); font-size: 16px; font-weight: 600; }
        .demo-text { fill: #C3D4CD; font-size: 14px; }
        .demo-search-query, .demo-password, .demo-key, .demo-saved { opacity: 0; }
        .walkthrough-preview[data-step="save"] .demo-saved,
        .walkthrough-preview:is([data-step="find"], [data-step="reveal"]) .demo-search-query { opacity: 1; }
        .walkthrough-preview:is([data-step="find"], [data-step="reveal"]) .demo-placeholder { opacity: 0; }
        .walkthrough-preview:is([data-step="find"], [data-step="reveal"]) .demo-other { opacity: 0.25; }
        .walkthrough-preview:is([data-step="find"], [data-step="reveal"]) .demo-selected { stroke: var(--green); }
        .walkthrough-preview[data-step="reveal"] .demo-mask { opacity: 0; }
        .walkthrough-preview[data-step="reveal"] .demo-password,
        .walkthrough-preview[data-step="reveal"] .demo-key { opacity: 1; }
        .steps { display: grid; gap: 0; margin: 0; padding: 0; list-style: none; counter-reset: step; }
        .steps li { counter-increment: step; min-height: 220px; padding-block: 28px; border-bottom: 1px solid var(--line); }
        .steps li:last-child { border-bottom: 0; }
        .steps li::before { content: counter(step) "."; display: block; margin-bottom: 12px; color: var(--green); font-size: 14px; font-weight: 600; font-variant-numeric: tabular-nums; }
        h3 { margin-bottom: 8px; font-size: 17px; font-weight: 600; }
        .step-trigger { min-height: 44px; padding: 0; border: 0; background: transparent; color: var(--text); font: inherit; text-align: left; cursor: pointer; text-decoration: underline; text-underline-offset: 5px; }
        .step-trigger[aria-pressed="true"] { color: var(--green); }
        .step-trigger:disabled { cursor: default; text-decoration: none; }
        .steps p { max-width: 32ch; color: var(--muted); font-size: 14px; }
        .privacy { display: grid; grid-template-columns: 1fr 1.4fr; align-items: center; gap: 24px 64px; padding-block: 56px 64px; border-top: 1px solid var(--line); }
        .privacy-copy { margin-top: 20px; max-width: 44ch; color: var(--muted); font-size: 15px; }
        .privacy-visual { width: min(100%, 420px); margin: 0 auto; }
        .privacy-visual svg { display: block; width: 100%; height: auto; }
        .privacy-visual text { font-family: 'Inter', sans-serif; }
        .privacy-status { grid-column: 1 / -1; max-width: 78ch; color: var(--muted); font-size: 14px; }
        .privacy-status strong { color: var(--text); font-weight: 600; }
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
        .footer-links { display: flex; flex-wrap: wrap; gap: 8px 24px; }
        .official-site { margin-top: 6px; }
        .install-banner { position: fixed; right: 24px; bottom: 24px; z-index: 20; width: min(380px, calc(100% - 48px)); align-items: center; gap: 16px; padding: 20px; border-radius: 12px; background: #19263B; box-shadow: 0 12px 32px #0006; }
        .install-banner p { margin-bottom: 12px; color: #DFE8F6; font-size: 14px; }
        .install-banner .button { min-height: 44px; padding: 8px 16px; font-size: 14px; }
        .dismiss-install { display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; width: 44px; height: 44px; padding: 0; border: 0; border-radius: 8px; background: transparent; color: #DFE8F6; cursor: pointer; }
        .dismiss-install:hover { background: #25364F; }
        .dismiss-install svg { width: 20px; height: 20px; }
        .hidden { display: none; }
        .flex { display: flex; }
        /* Documents: a plain index of notices that open in one shared reading panel. */
        .docs { grid-column: 1 / -1; display: grid; grid-template-columns: 1fr 1.4fr; gap: 24px 64px; margin-top: 24px; padding-top: 48px; border-top: 1px solid var(--line); }
        .docs-title { margin-bottom: 12px; font-size: 20px; font-weight: 600; line-height: 1.3; letter-spacing: -0.02em; text-wrap: balance; }
        .docs-intro p { max-width: 36ch; color: var(--muted); font-size: 14px; }
        .doc-list { margin: 0; padding: 0; list-style: none; }
        .doc-list li { border-bottom: 1px solid var(--line); }
        .doc-list li:first-child { border-top: 1px solid var(--line); }
        .doc-link { display: grid; grid-template-columns: auto 1fr auto auto; align-items: center; gap: 20px; padding: 20px 8px; }
        .doc-link:hover { background: linear-gradient(90deg, rgb(0 230 118 / 0.06), transparent 75%); }
        .doc-icon { display: block; width: 36px; height: 44px; overflow: visible; }
        .doc-icon path, .doc-icon rect { fill: var(--bg); stroke: #4F7A6A; stroke-width: 1.5; stroke-linejoin: round; }
        .doc-icon .doc-sheet path { fill: var(--surface); stroke: #86BAA1; }
        .doc-icon .doc-sheet .doc-lines { fill: none; stroke: #86BAA1; stroke-linecap: round; }
        .doc-link:is(:hover, :focus-visible) .doc-sheet { transform: translate(-2px, -4px) rotate(-3deg); }
        .doc-link:is(:hover, :focus-visible) .doc-lines { stroke: var(--green); }
        .doc-name { display: block; font-size: 15px; font-weight: 600; }
        .doc-summary { display: block; margin-top: 2px; color: var(--muted); font-size: 13px; }
        .doc-meta { color: var(--muted); font-size: 12px; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .doc-arrow { width: 16px; height: 16px; color: var(--muted); }
        .doc-link:is(:hover, :focus-visible) .doc-arrow { color: var(--green); transform: translateX(3px); }
        html:has(.doc-reader[open]) { overflow: hidden; }
        .doc-reader { --doc-enter: translateX(48px); display: none; flex-direction: column; width: min(720px, 100%); max-width: none; height: 100dvh; max-height: none; margin: 0 0 0 auto; padding: 0; border: 0; border-left: 1px solid var(--line); background: var(--surface); color: var(--text); box-shadow: -24px 0 64px rgb(0 0 0 / 0.45); overflow: hidden; opacity: 0; transform: var(--doc-enter); }
        .doc-reader[open] { display: flex; opacity: 1; transform: none; }
        .doc-reader::backdrop { background: rgb(4 8 15 / 0); }
        .doc-reader[open]::backdrop { background: rgb(4 8 15 / 0.72); backdrop-filter: blur(6px); }
        .doc-progress { position: absolute; inset: 0 0 auto; height: 2px; }
        .doc-progress span { display: block; height: 100%; background: var(--green); transform: scaleX(var(--doc-progress, 0)); transform-origin: left; }
        .doc-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 24px; padding: 32px 40px 16px; }
        .doc-header h2 { font-size: 24px; }
        .doc-updated { margin-top: 6px; color: var(--muted); font-size: 13px; font-variant-numeric: tabular-nums; }
        .doc-close { display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; width: 44px; height: 44px; padding: 0; border: 1px solid var(--line); border-radius: 50%; background: transparent; color: var(--muted); cursor: pointer; }
        .doc-close:hover { color: var(--green); border-color: var(--green); }
        .doc-close svg { width: 18px; height: 18px; }
        .doc-tabs { display: flex; gap: 28px; padding: 0 40px; border-bottom: 1px solid var(--line); }
        .doc-tabs button { min-height: 44px; padding: 0; border: 0; background: transparent; color: var(--muted); font-size: 14px; font-weight: 500; cursor: pointer; box-shadow: inset 0 -2px 0 transparent; }
        .doc-tabs button:hover { color: var(--text); }
        .doc-tabs button[aria-pressed="true"] { color: var(--text); box-shadow: inset 0 -2px 0 var(--green); }
        .doc-body { flex: 1; min-height: 0; padding: 8px 40px 48px; overflow-y: auto; overscroll-behavior: contain; scrollbar-width: thin; scrollbar-color: #34504D transparent; }
        .doc-body:focus-visible { outline-offset: -4px; }
        .doc-article { max-width: 62ch; }
        .doc-lede { margin-top: 24px; }
        .doc-article h3 { margin: 36px 0 10px; font-size: 16px; }
        .doc-article p, .doc-article li { color: #C3CDDA; font-size: 15px; line-height: 1.7; }
        .doc-article .doc-lede { color: var(--text); font-size: 16px; }
        .doc-article p + p { margin-top: 12px; }
        .doc-article ul { margin: 10px 0 0; padding-left: 20px; }
        .doc-article li + li { margin-top: 8px; }
        .doc-article li::marker { color: var(--green); }
        .doc-article strong { color: var(--text); font-weight: 600; }
        .doc-article a, .doc-footer a { color: var(--green); text-decoration: underline; }
        .doc-glance { margin: 24px 0 0; border-block: 1px solid var(--line); }
        .doc-glance div { display: grid; grid-template-columns: 12rem 1fr; gap: 16px; padding: 14px 0; }
        .doc-glance div + div { border-top: 1px solid var(--line); }
        .doc-glance dt { color: var(--text); font-size: 14px; font-weight: 600; }
        .doc-glance dd { margin: 0; color: #C3CDDA; font-size: 14px; }
        .doc-footer { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 12px 40px; border-top: 1px solid var(--line); font-size: 14px; }
        .doc-next { display: inline-flex; align-items: center; gap: 8px; min-height: 44px; padding: 0; border: 0; background: transparent; color: var(--text); font-size: 14px; font-weight: 500; cursor: pointer; }
        .doc-next:hover { color: var(--green); }
        .doc-next svg { width: 16px; height: 16px; }
        @media (prefers-reduced-motion: no-preference) {
            .doc-link { transition: background-color 200ms ease-out; }
            .doc-sheet { transition: transform 360ms cubic-bezier(0.16, 1, 0.3, 1); }
            .doc-lines, .doc-arrow { transition: stroke 200ms ease-out, color 200ms ease-out, transform 240ms cubic-bezier(0.16, 1, 0.3, 1); }
            .doc-tabs button { transition: color 200ms ease-out, box-shadow 240ms cubic-bezier(0.16, 1, 0.3, 1); }
            .doc-progress span { transition: transform 80ms linear; }
            /* Native dialog enter/exit; unsupported browsers simply open and close instantly. */
            .doc-reader { transition: transform 460ms cubic-bezier(0.16, 1, 0.3, 1), opacity 300ms ease-out, overlay 460ms allow-discrete, display 460ms allow-discrete; }
            .doc-reader::backdrop { transition: background-color 360ms ease-out, backdrop-filter 360ms ease-out, overlay 460ms allow-discrete, display 460ms allow-discrete; }
            @starting-style {
                .doc-reader[open] { opacity: 0; transform: var(--doc-enter); }
                .doc-reader[open]::backdrop { background: rgb(4 8 15 / 0); backdrop-filter: blur(0); }
            }
        }
        @media (max-width: 760px) {
            .docs { grid-template-columns: 1fr; gap: 24px; }
            .docs-intro p { max-width: none; }
            .doc-reader { --doc-enter: translateY(48px); width: 100%; height: auto; max-height: 92dvh; margin: auto 0 0; border-left: 0; border-top: 1px solid var(--line); border-radius: 16px 16px 0 0; }
            .doc-header { padding: 24px 20px 12px; }
            .doc-header h2 { font-size: 21px; }
            .doc-tabs { gap: 24px; padding: 0 20px; }
            .doc-body { padding: 4px 20px 40px; }
            .doc-footer { padding: 8px 20px; }
            .doc-glance div { grid-template-columns: 1fr; gap: 4px; }
        }
        @media (max-width: 540px) {
            .doc-link { grid-template-columns: auto 1fr auto; gap: 16px; padding-inline: 0; }
            .doc-meta { display: none; }
        }
        /* One shared timeline: mask the sample, move the card, then close the vault. */
        /* Without JavaScript or with reduced motion, the masked card and closed vault stay visible. */
        @media (prefers-reduced-motion: no-preference) {
            @keyframes vault-card-save {
                0%, 26%, 100% { transform: none; opacity: 1; }
                48% { transform: translateX(238px) scale(0.36); opacity: 1; }
                53%, 86% { transform: translateX(238px) scale(0.36); opacity: 0; }
                87% { transform: none; opacity: 0; }
                94% { transform: none; opacity: 1; }
            }
            @keyframes vault-password-plain { 0%, 9%, 100% { opacity: 1; } 18%, 94% { opacity: 0; } }
            @keyframes vault-password-masked { 0%, 9%, 100% { opacity: 0; } 18%, 94% { opacity: 1; } }
            @keyframes vault-door-close {
                0%, 42%, 100% { transform: scaleX(0.22) skewY(-9deg); }
                57%, 85% { transform: none; }
            }
            @keyframes vault-shackle-close {
                0%, 54%, 100% { transform: translateY(-8px) rotate(12deg); }
                66%, 85% { transform: none; }
            }
            @keyframes vault-saved { 0%, 62%, 91%, 100% { opacity: 0; } 68%, 84% { opacity: 1; } }
            @keyframes vault-phase-mask { 0%, 24%, 96%, 100% { opacity: 1; } 28%, 92% { opacity: 0; } }
            @keyframes vault-phase-save { 0%, 24%, 59%, 100% { opacity: 0; } 28%, 55% { opacity: 1; } }
            @keyframes vault-phase-locked { 0%, 55%, 94%, 100% { opacity: 0; } 59%, 89% { opacity: 1; } }
            .motion-enabled { --vault-cycle: 10s; }
            .motion-enabled .motion-layer { animation-duration: var(--vault-cycle); animation-iteration-count: infinite; animation-timing-function: cubic-bezier(0.16, 1, 0.3, 1); }
            .motion-enabled .motion-card { transform-origin: 122px 222px; animation-name: vault-card-save; }
            .motion-enabled .motion-plain { animation-name: vault-password-plain; }
            .motion-enabled .motion-mask { animation-name: vault-password-masked; }
            .motion-enabled .motion-door { transform-origin: 272px 240px; animation-name: vault-door-close; }
            .motion-enabled .motion-shackle { transform-origin: 382px 226px; animation-name: vault-shackle-close; }
            .motion-enabled .motion-saved { animation-name: vault-saved; }
            .motion-enabled .motion-phase-mask { animation-name: vault-phase-mask; }
            .motion-enabled .motion-phase-save { animation-name: vault-phase-save; }
            .motion-enabled .motion-phase-locked { animation-name: vault-phase-locked; }
            .vault-preview .motion-layer { animation-play-state: paused; }
            .vault-preview.motion-running .motion-layer { animation-play-state: running; }
            .walkthrough-preview.motion-running .demo-state { transition: opacity 280ms ease-out, stroke 280ms ease-out; }
            .button svg, summary svg { transition: transform 180ms cubic-bezier(0.16, 1, 0.3, 1); }
            .button:is(:hover, :focus-visible) svg { transform: translateX(3px); }
        }
        @media (max-width: 760px) {
            .hero { grid-template-columns: 1fr; gap: 40px; padding-block: 56px; }
            h1 { max-width: 12ch; }
            .hero-copy { max-width: 42ch; font-size: 17px; }
            .vault-preview { width: 100%; max-width: 480px; }
            .walkthrough { grid-template-columns: 1fr; gap: 16px; }
            .walkthrough-preview { top: 0; z-index: 2; padding-block: 12px; background: var(--bg); }
            .walkthrough-preview svg { max-height: 220px; }
            .steps li { min-height: 220px; }
            .privacy { grid-template-columns: 1fr; gap: 28px; }
            .privacy-copy { max-width: none; }
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
            .getting-started, .privacy, .faq { padding-block: 40px; }
            .walkthrough { margin-top: 24px; }
            .walkthrough-preview svg { max-height: 200px; }
            .steps li { position: relative; padding-left: 32px; }
            .steps li::before { position: absolute; top: 38px; left: 0; }
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

            <!-- SVG draws the example; the CSS keyframes above supply its movement. -->
            <figure id="vaultMotion" class="vault-preview" aria-labelledby="preview-caption">
                <svg class="vault-scene" viewBox="0 0 500 440" width="500" height="440" role="img" aria-labelledby="motion-title" aria-describedby="motion-description">
                    <title id="motion-title">A sample password moves into your vault</title>
                    <desc id="motion-description">An illustrative sequence masks a sample password, moves its login card into a vault, and closes the lock. It uses example data only.</desc>
                    <defs>
                        <radialGradient id="vault-light">
                            <stop stop-color="#17483B" stop-opacity="0.65"/>
                            <stop offset="1" stop-color="#080E1A" stop-opacity="0"/>
                        </radialGradient>
                        <linearGradient id="vault-metal" x1="0" y1="0" x2="1" y2="1">
                            <stop stop-color="#1B3D3A"/>
                            <stop offset="1" stop-color="#0F1C2A"/>
                        </linearGradient>
                    </defs>

                    <ellipse cx="345" cy="226" rx="180" ry="180" fill="url(#vault-light)"/>
                    <path d="M42 222h392" fill="none" stroke="#31584F" stroke-dasharray="3 9"/>
                    <rect x="254" y="116" width="220" height="234" rx="24" fill="#142630" stroke="#366D5C"/>
                    <rect x="270" y="132" width="188" height="202" rx="14" fill="#080E1A" stroke="#2B514A"/>
                    <g fill="#223E36">
                        <rect x="296" y="164" width="136" height="34" rx="6"/>
                        <rect x="296" y="210" width="136" height="34" rx="6"/>
                        <rect x="296" y="256" width="136" height="34" rx="6"/>
                    </g>
                    <path d="M310 181h47m-47 46h66m-66 46h35" stroke="#8CBBA7" stroke-width="3" stroke-linecap="round"/>

                    <!-- The card is a separate SVG group, so CSS can move it as one piece. -->
                    <g class="motion-card motion-layer">
                        <rect x="26" y="166" width="192" height="112" rx="14" fill="#142630" stroke="#527D6C"/>
                        <rect x="42" y="183" width="32" height="32" rx="8" fill="#244137"/>
                        <g fill="none" stroke="#96E6BE" stroke-width="1.5" stroke-linejoin="round">
                            <rect x="48" y="192" width="20" height="14" rx="2"/>
                            <path d="m49 193 9 7 9-7"/>
                        </g>
                        <text class="motion-card-title" x="86" y="204">Sample login</text>
                        <text class="motion-card-password motion-plain motion-layer" x="43" y="249">DemoPass24!</text>
                        <text class="motion-card-password motion-mask motion-layer" x="43" y="249">••••••••••</text>
                    </g>

                    <g class="motion-door motion-layer">
                        <rect x="272" y="132" width="186" height="202" rx="14" fill="url(#vault-metal)" stroke="#72B99B" stroke-width="1.5"/>
                        <rect x="285" y="145" width="160" height="176" rx="9" fill="none" stroke="#2E594D"/>
                        <path class="motion-shackle motion-layer" d="M346 228v-18a18 18 0 0 1 36 0v18" fill="none" stroke="#B0F7D3" stroke-width="5" stroke-linecap="round"/>
                        <rect x="336" y="226" width="56" height="43" rx="10" fill="#00E676"/>
                        <circle cx="364" cy="243" r="4" fill="#103428"/>
                        <path d="M364 246v7" stroke="#103428" stroke-width="3" stroke-linecap="round"/>
                        <text class="motion-core-label" x="365" y="295" text-anchor="middle">VAULT</text>
                    </g>
                    <g class="motion-saved motion-layer">
                        <circle cx="446" cy="129" r="18" fill="#00E676"/>
                        <path d="m438 129 5 5 10-11" fill="none" stroke="#103428" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </g>
                    <text class="motion-phase motion-phase-mask motion-layer" x="250" y="393" text-anchor="middle">Mask your password</text>
                    <text class="motion-phase motion-phase-save motion-layer" x="250" y="393" text-anchor="middle">Save the encrypted login</text>
                    <text class="motion-phase motion-phase-locked motion-layer" x="250" y="393" text-anchor="middle">Saved in your vault</text>
                </svg>
                <figcaption class="motion-controls">
                    <span id="preview-caption" class="sr-only">Example data · illustrative animation</span>
                    <button id="motionToggle" class="motion-toggle" type="button" aria-label="Pause animation" title="Pause animation" data-motion-toggle hidden>
                        <svg class="motion-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="currentColor"><path d="M7 5h3v14H7zM14 5h3v14h-3z"/></svg>
                    </button>
                </figcaption>
            </figure>
        </section>

        <section id="how-it-works" class="getting-started" aria-labelledby="steps-title">
            <h2 id="steps-title" data-scroll-reveal>Save it. Find it. Unlock it.</h2>
            <p class="walkthrough-intro">Scroll or select a step to see an example.</p>
            <div class="walkthrough">
                <figure id="walkthroughScene" class="walkthrough-preview" data-step="save" aria-labelledby="walkthrough-caption">
                    <svg viewBox="0 0 500 340" width="500" height="340" role="img" aria-labelledby="walkthrough-title" aria-describedby="walkthrough-description">
                        <title id="walkthrough-title">An example login in SecureVault</title>
                        <desc id="walkthrough-description">An illustrative vault shows a saved email login, a search for Email, and a sample password revealed after entering a master key. It does not use real account data.</desc>
                        <rect x="22" y="16" width="456" height="306" rx="16" fill="#0F1729" stroke="#34504D"/>
                        <text class="demo-title" x="42" y="49">My vault</text>
                        <circle cx="430" cy="43" r="3" fill="#00E676"/>
                        <circle cx="442" cy="43" r="3" fill="#527D6C"/>
                        <rect x="42" y="67" width="416" height="36" rx="8" fill="#080E1A" stroke="#34504D"/>
                        <g fill="none" stroke="#A9B5C6" stroke-width="1.5" stroke-linecap="round">
                            <circle cx="61" cy="83" r="5"/><path d="m65 87 5 5"/>
                        </g>
                        <text class="demo-text demo-placeholder demo-state" x="85" y="90">Search logins</text>
                        <text class="demo-text demo-search-query demo-state" x="85" y="90">Email</text>
                        <rect class="demo-selected demo-state" x="42" y="120" width="416" height="72" rx="10" fill="#142630" stroke="#527D6C"/>
                        <text class="demo-title" x="62" y="147">Email</text>
                        <text class="demo-text demo-mask demo-state" x="62" y="174" letter-spacing="3">••••••••••</text>
                        <text class="demo-text demo-password demo-state" x="62" y="174">DemoPass24!</text>
                        <g class="demo-saved demo-state">
                            <circle cx="428" cy="155" r="12" fill="#00E676"/>
                            <path d="m422 155 4 4 8-9" fill="none" stroke="#103428" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </g>
                        <text class="demo-key demo-state" x="62" y="209" fill="#A6E4C3" font-size="11">Master key entered · example only</text>
                        <g class="demo-other demo-state">
                            <rect x="42" y="220" width="416" height="36" rx="8" fill="#142630"/>
                            <text class="demo-text" x="62" y="243">Work</text>
                            <text class="demo-text" x="352" y="243" letter-spacing="2">••••••</text>
                            <rect x="42" y="268" width="416" height="36" rx="8" fill="#142630"/>
                            <text class="demo-text" x="62" y="291">Shopping</text>
                            <text class="demo-text" x="352" y="291" letter-spacing="2">••••••</text>
                        </g>
                    </svg>
                    <figcaption class="motion-controls">
                        <span id="walkthrough-caption" class="sr-only">Example data · interactive illustration</span>
                        <button class="motion-toggle" type="button" aria-label="Pause animation" title="Pause animation" data-motion-toggle hidden>
                            <svg class="motion-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="currentColor"><path d="M7 5h3v14H7zM14 5h3v14h-3z"/></svg>
                        </button>
                    </figcaption>
                </figure>
                <ol class="steps">
                    <li data-walkthrough-step="save">
                        <h3><button class="step-trigger" type="button" data-demo-step="save" aria-controls="walkthroughScene" aria-pressed="true" disabled>Save a login</button></h3>
                        <p>Create your account, choose a master key, and add a login. Its password is encrypted in your browser before saving.</p>
                    </li>
                    <li data-walkthrough-step="find">
                        <h3><button class="step-trigger" type="button" data-demo-step="find" aria-controls="walkthroughScene" aria-pressed="false" disabled>Find it quickly</button></h3>
                        <p>Search by service name or browse your categories to locate a saved login.</p>
                    </li>
                    <li data-walkthrough-step="reveal">
                        <h3><button class="step-trigger" type="button" data-demo-step="reveal" aria-controls="walkthroughScene" aria-pressed="false" disabled>Reveal when needed</button></h3>
                        <p>Enter your master key to reveal a password. It hides again after 30 seconds.</p>
                    </li>
                </ol>
            </div>
        </section>

        <section id="data-privacy" class="privacy" aria-labelledby="privacy-title">
            <div>
                <h2 id="privacy-title">Your data, explained.</h2>
                <p class="privacy-copy">Saved account passwords are encrypted in your browser before they are stored on the server.</p>
                <p class="privacy-copy">Your email, login names, website URLs, notes, and categories are stored without that encryption. This browser also keeps a local vault copy for offline access.</p>
            </div>
            <!-- This diagram describes saved account passwords; sign-in follows the separate path noted below. -->
            <figure class="privacy-visual" aria-label="How saved account passwords are handled">
                <svg viewBox="0 0 420 420" width="420" height="420" role="img" aria-labelledby="privacy-flow-title" aria-describedby="privacy-flow-description">
                    <title id="privacy-flow-title">Saved account password flow</title>
                    <desc id="privacy-flow-description">Your browser encrypts a saved account password before sending it to the server. The server stores the encrypted password, and this device can keep an encrypted copy for offline access. Other account details and the master password used for sign-in are handled differently, as explained in this section.</desc>
                    <g fill="none" stroke="#34504D" stroke-width="1.5">
                        <rect x="30" y="20" width="360" height="100" rx="12"/>
                        <rect x="30" y="160" width="360" height="100" rx="12"/>
                        <rect x="30" y="300" width="360" height="100" rx="12"/>
                    </g>
                    <g fill="none" stroke="#86BAA1" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="54" y="40" width="25" height="20" rx="3"/>
                        <path d="M54 46h25m-20-3h1m4 0h1"/>
                        <ellipse cx="66" cy="185" rx="12" ry="4"/>
                        <path d="M54 185v14c0 5 24 5 24 0v-14m-24 7c0 5 24 5 24 0"/>
                        <rect x="57" y="322" width="18" height="27" rx="3"/>
                        <path d="M63 326h6m-4 19h2"/>
                        <path d="M210 127v26m-6-6 6 6 6-6M210 267v26m-6-6 6 6 6-6"/>
                    </g>
                    <g fill="#F3F6FA" font-size="20" font-weight="600">
                        <text x="94" y="57">Your browser</text>
                        <text x="94" y="197">Online vault</text>
                        <text x="94" y="339">This device</text>
                    </g>
                    <g fill="#B8CFC4" font-size="16">
                        <text x="54" y="94">Encrypts the password before sending</text>
                        <text x="54" y="234">Stores the encrypted password</text>
                        <text x="54" y="374">Keeps an encrypted copy for offline use</text>
                    </g>
                </svg>
            </figure>
            <p class="privacy-status"><strong>Current limitation.</strong> The server still receives your master password when you sign in. The full zero-knowledge design is still being improved.</p>

            <!-- Each row opens the shared reader below; the #doc-* links are also shareable. -->
            <div id="documents" class="docs">
                <div class="docs-intro" data-scroll-reveal>
                    <h3 class="docs-title">The fine print, in plain language.</h3>
                    <p>What SecureVault stores, the rules for using it, and how your passwords are protected.</p>
                </div>
                <ul class="doc-list">
                    <li data-scroll-reveal="faq">
                        <a class="doc-link" href="#doc-privacy" data-doc-open="privacy">
                            <svg class="doc-icon" viewBox="0 0 36 44" aria-hidden="true" focusable="false"><rect x="10" y="2" width="24" height="32" rx="3"/><g class="doc-sheet"><path d="M5 8h15l8 8v24a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V10a2 2 0 0 1 2-2z"/><path class="doc-lines" d="M20 8v8h8M8 25h14M8 30h14M8 35h9"/></g></svg>
                            <span><span class="doc-name">Privacy Policy</span><span class="doc-summary">What is stored, where it lives, and for how long.</span></span>
                            <span class="doc-meta">4 min read</span>
                            <svg class="doc-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/></svg>
                        </a>
                    </li>
                    <li data-scroll-reveal="faq" data-reveal-delay="80">
                        <a class="doc-link" href="#doc-terms" data-doc-open="terms">
                            <svg class="doc-icon" viewBox="0 0 36 44" aria-hidden="true" focusable="false"><rect x="10" y="2" width="24" height="32" rx="3"/><g class="doc-sheet"><path d="M5 8h15l8 8v24a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V10a2 2 0 0 1 2-2z"/><path class="doc-lines" d="M20 8v8h8M8 25h14M8 30h14M8 35h9"/></g></svg>
                            <span><span class="doc-name">Terms of Use</span><span class="doc-summary">The ground rules for using a personal side project.</span></span>
                            <span class="doc-meta">3 min read</span>
                            <svg class="doc-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/></svg>
                        </a>
                    </li>
                    <li data-scroll-reveal="faq" data-reveal-delay="160">
                        <a class="doc-link" href="#doc-security" data-doc-open="security">
                            <svg class="doc-icon" viewBox="0 0 36 44" aria-hidden="true" focusable="false"><rect x="10" y="2" width="24" height="32" rx="3"/><g class="doc-sheet"><path d="M5 8h15l8 8v24a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V10a2 2 0 0 1 2-2z"/><path class="doc-lines" d="M20 8v8h8M8 25h14M8 30h14M8 35h9"/></g></svg>
                            <span><span class="doc-name">Security Overview</span><span class="doc-summary">How encryption works, and what it does not cover yet.</span></span>
                            <span class="doc-meta">3 min read</span>
                            <svg class="doc-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/></svg>
                        </a>
                    </li>
                </ul>
            </div>
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
                <p>SecureVault · A personal side project by Ronan Antoque</p>
                <p class="official-site">Before signing in, check the address: securevault.great-site.net</p>
            </div>
            <div class="footer-links">
                <a class="quiet-link" href="#how-it-works">How it works</a>
                <a class="quiet-link" href="#doc-privacy" data-doc-open="privacy">Privacy</a>
                <a class="quiet-link" href="#doc-terms" data-doc-open="terms">Terms</a>
                <a class="quiet-link" href="#doc-security" data-doc-open="security">Security</a>
            </div>
        </div>
    </footer>

    <!-- One reader for all documents. Copy must match actual behavior; update the date when it changes. -->
    <dialog id="docReader" class="doc-reader" aria-labelledby="docReaderTitle" closedby="any">
        <div class="doc-progress" aria-hidden="true"><span></span></div>
        <header class="doc-header">
            <div>
                <h2 id="docReaderTitle">Privacy Policy</h2>
                <p class="doc-updated">Updated 19 November 2026 · <span data-doc-time>4 min read</span></p>
            </div>
            <button class="doc-close" type="button" data-doc-close aria-label="Close document">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="m6 6 12 12M6 18 18 6"/></svg>
            </button>
        </header>
        <nav class="doc-tabs" aria-label="SecureVault documents">
            <button type="button" data-doc-tab="privacy" aria-pressed="true">Privacy</button>
            <button type="button" data-doc-tab="terms" aria-pressed="false">Terms</button>
            <button type="button" data-doc-tab="security" aria-pressed="false">Security</button>
        </nav>
        <div class="doc-body" tabindex="0" aria-label="Document text">
            <article class="doc-article" data-doc="privacy" data-title="Privacy Policy" data-time="4 min read">
                <p class="doc-lede">SecureVault is a personal side project built and run by Ronan Antoque. This policy explains what the service at securevault.great-site.net stores, where, and why.</p>
                <dl class="doc-glance">
                    <div><dt>Encrypted in your browser</dt><dd>The passwords you save in your vault.</dd></div>
                    <div><dt>Stored without that encryption</dt><dd>Your username and email, and each entry’s service name, login name, website, notes, and category.</dd></div>
                    <div><dt>Never</dt><dd>Sold, used for advertising, or tracked with analytics.</dd></div>
                </dl>

                <h3>What is stored on the server</h3>
                <ul>
                    <li><strong>Account details:</strong> your username, email address, a bcrypt hash of your master key (not the key itself), and a random salt used to derive your encryption key.</li>
                    <li><strong>Vault entries:</strong> the service name, login name, website, notes, and category you enter. The password itself is stored only as AES-256-GCM ciphertext created in your browser.</li>
                    <li><strong>Sessions:</strong> a random sign-in token and its expiry time, so you stay signed in.</li>
                    <li><strong>Sign-in protection:</strong> counts of failed sign-in attempts, labeled by a hash of the account or network address rather than the name or address itself. They expire after 15 minutes.</li>
                </ul>

                <h3>Your master key</h3>
                <p>Your master key is sent over HTTPS when you create an account, sign in, or change it, so the server can check it against its bcrypt hash. It is not saved in readable form or written to logs.</p>
                <p>Saved passwords are encrypted and decrypted only in your browser. Because the server receives your master key at sign-in, SecureVault is not yet fully zero-knowledge.</p>

                <h3>What stays on your device</h3>
                <p>To open your vault without a connection, this browser keeps:</p>
                <ul>
                    <li>an encrypted copy of your vault and its entry details in browser storage (IndexedDB);</li>
                    <li>an encrypted check that confirms your master key before changes, and a note of which account is signed in;</li>
                    <li>a session cookie and a sign-out cookie, needed to sign in and out;</li>
                    <li>cached public pages, so the app can start offline.</li>
                </ul>
                <p>Signing out removes the vault copy, its details, and the key check. The inactivity lock keeps the encrypted copy for offline access. There are no advertising or tracking cookies.</p>

                <h3>Services involved</h3>
                <p>SecureVault is hosted on InfinityFree. Its servers hold the database and may keep standard technical logs, such as IP addresses and request times.</p>
                <p>Pages load fonts from Google Fonts, and the sign-in and vault pages load styles from the Tailwind CSS CDN. Those providers receive your IP address and browser details when your browser requests the files. Your data is not shared with anyone else.</p>

                <h3>How long data is kept</h3>
                <p>Your account and entries stay until they are deleted. Deleting an entry removes it from the server and from this browser’s copy. Expired sign-in counters are removed automatically.</p>
                <p>There is no self-service account deletion yet. To delete your account and all of its entries, contact Ronan.</p>

                <h3>Your choices</h3>
                <p>You can view, edit, and delete your entries at any time, sign out to clear this browser’s copy, and ask Ronan about or request deletion of your account data.</p>

                <h3>Changes and contact</h3>
                <p>If this policy changes, the date at the top changes too. Questions? Contact Ronan through his <a href="https://github.com/RonanStack24" target="_blank" rel="noopener noreferrer">GitHub profile</a>.</p>
            </article>

            <article class="doc-article" data-doc="terms" data-title="Terms of Use" data-time="3 min read" hidden>
                <p class="doc-lede">SecureVault is a free, personal side project by Ronan Antoque. It is experimental and has not had an independent security audit. By creating an account or using SecureVault, you agree to these terms.</p>

                <h3>Your account and master key</h3>
                <p>Keep your master key private and memorable. There is no reset: if you forget it, your saved passwords cannot be decrypted by anyone, including the developer.</p>
                <p>You are responsible for activity under your account. Sign out when you use a shared device.</p>

                <h3>Keep your own backup</h3>
                <p>SecureVault has no export feature yet, and the service may be unavailable, changed, or discontinued at any time. Keep a separate copy of any password you cannot afford to lose.</p>

                <h3>Acceptable use</h3>
                <p>Please do not:</p>
                <ul>
                    <li>try to access other people’s accounts or data;</li>
                    <li>probe, scan, or attack the service or its hosting;</li>
                    <li>send automated or excessive requests, including repeated sign-in attempts;</li>
                    <li>use SecureVault for anything unlawful.</li>
                </ul>
                <p>Accounts that break these rules may be suspended or removed.</p>

                <h3>Ownership</h3>
                <p>SecureVault’s code, design, name, and logo belong to Ronan Antoque. SecureVault is not open source: please do not copy, modify, redistribute, or host it without written permission. Everything you save in your vault remains yours.</p>

                <h3>No warranty</h3>
                <p>SecureVault is provided “as is” and “as available”, without warranties of any kind. To the extent the law allows, Ronan Antoque is not liable for lost data, lost access, or other damages from using or being unable to use the service.</p>

                <h3>Changes</h3>
                <p>These terms may be updated; the date at the top shows the current version. Continuing to use SecureVault after a change means you accept the updated terms.</p>
            </article>

            <article class="doc-article" data-doc="security" data-title="Security Overview" data-time="3 min read" hidden>
                <p class="doc-lede">A plain summary of how SecureVault protects your saved passwords, and where those protections stop.</p>

                <h3>How saved passwords are protected</h3>
                <p>When you save a password, your browser derives a 256-bit key from your master key and your account’s salt using PBKDF2-SHA256 with 100,000 iterations. It then encrypts the password with AES-256-GCM and a fresh random 12-byte IV. Only the ciphertext leaves your device.</p>
                <p>Revealing a password reverses this in your browser. The plain text hides again after 30 seconds.</p>

                <h3>Built-in safeguards</h3>
                <ul>
                    <li>HTTPS on the live site.</li>
                    <li>Your master key is stored only as a bcrypt hash.</li>
                    <li>Sign-in pauses for 15 minutes after 5 failed attempts on an account, or 25 from one network address.</li>
                    <li>Saving or deleting entries requires your session’s anti-forgery token and a local check of your master key.</li>
                    <li>The vault locks after 5 minutes of inactivity, and signing out clears this browser’s copy.</li>
                    <li>Changing your master key re-encrypts every entry together and signs out all sessions.</li>
                </ul>

                <h3>Known limitations</h3>
                <ul>
                    <li>The server receives your master key when you sign in, so SecureVault is not yet fully zero-knowledge.</li>
                    <li>Entry details other than the password are not encrypted.</li>
                    <li>There has been no independent security audit.</li>
                    <li>Browser extensions, malicious scripts, or anyone with access to your browser profile can get around browser-side protections.</li>
                    <li>Other devices keep their offline copies until they sign in online again or sign out.</li>
                </ul>

                <h3>Reporting a problem</h3>
                <p>If you find a security issue, contact Ronan privately through his <a href="https://github.com/RonanStack24" target="_blank" rel="noopener noreferrer">GitHub profile</a>. Please do not post passwords, vault contents, or exploit details publicly.</p>
            </article>
        </div>
        <footer class="doc-footer">
            <a href="https://github.com/RonanStack24" target="_blank" rel="noopener noreferrer">Contact Ronan</a>
            <button class="doc-next" type="button" data-doc-next>
                <span>Next: <span data-doc-next-label>Terms of Use</span></span>
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/></svg>
            </button>
        </footer>
    </dialog>

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

            const walkthrough = document.getElementById('walkthroughScene');
            const scenes = [scene, walkthrough].filter(Boolean);
            const toggles = document.querySelectorAll('[data-motion-toggle]');
            const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
            const activeReveals = new Set();
            const visibleScenes = new Set();
            let paused = false;
            // Shared controls stop nonessential movement when paused, hidden, or offscreen.
            const updateMotion = () => {
                scenes.forEach(target => {
                    target.classList.toggle('motion-enabled', !reducedMotion.matches);
                    target.classList.toggle('motion-running', visibleScenes.has(target) && !document.hidden && !paused && !reducedMotion.matches);
                });
                toggles.forEach(button => {
                    button.hidden = reducedMotion.matches;
                    const label = paused ? 'Play animation' : 'Pause animation';
                    button.setAttribute('aria-label', label);
                    button.title = label;
                    button.querySelector('path').setAttribute('d', paused ? 'M8 5v14l11-7z' : 'M7 5h3v14H7zM14 5h3v14h-3z');
                });
                if (paused || reducedMotion.matches || document.hidden) {
                    activeReveals.forEach(animation => animation.finish());
                }
            };

            // Animate on entry without hiding content before JavaScript or observation succeeds.
            const observer = new IntersectionObserver(entries => {
                entries.forEach(entry => {
                    if (scenes.includes(entry.target)) {
                        if (entry.isIntersecting) visibleScenes.add(entry.target);
                        else visibleScenes.delete(entry.target);
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
            scenes.forEach(target => observer.observe(target));
            document.querySelectorAll('[data-scroll-reveal]').forEach(target => observer.observe(target));
            toggles.forEach(button => button.addEventListener('click', () => { paused = !paused; updateMotion(); }));

            // The same three states can be selected by scrolling or by the step buttons.
            if (walkthrough) {
                const stepButtons = document.querySelectorAll('[data-demo-step]');
                const showStep = step => {
                    walkthrough.dataset.step = step;
                    stepButtons.forEach(button => button.setAttribute('aria-pressed', String(button.dataset.demoStep === step)));
                };
                stepButtons.forEach(button => {
                    button.disabled = false;
                    button.addEventListener('click', () => showStep(button.dataset.demoStep));
                });
                const followScroll = entries => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) showStep(entry.target.dataset.walkthroughStep);
                    });
                };
                let stepObserver;
                const observeSteps = () => {
                    if (stepObserver) stepObserver.disconnect();
                    // Pixel margins keep the trigger below the mobile illustration at any aspect ratio.
                    stepObserver = new IntersectionObserver(followScroll, {
                        rootMargin: `-${Math.round(innerHeight * 0.55)}px 0px -${innerHeight - Math.round(innerHeight * 0.55) - 1}px 0px`,
                        threshold: 0
                    });
                    document.querySelectorAll('[data-walkthrough-step]').forEach(target => stepObserver.observe(target));
                };
                observeSteps();
                window.addEventListener('resize', observeSteps);
            }
            reducedMotion.addEventListener('change', updateMotion);
            document.addEventListener('visibilitychange', updateMotion);
            updateMotion();
        })();
    </script>
    <script>
        // Document reader: one native dialog, switchable articles, and shareable #doc-* links.
        (() => {
            const reader = document.getElementById('docReader');
            if (!reader || typeof reader.showModal !== 'function') return;
            const body = reader.querySelector('.doc-body');
            const articles = [...reader.querySelectorAll('[data-doc]')];
            const order = articles.map(article => article.dataset.doc);
            const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
            const updateProgress = () => {
                const max = body.scrollHeight - body.clientHeight;
                reader.style.setProperty('--doc-progress', max > 0 ? String(body.scrollTop / max) : '1');
            };
            const show = (key, animate) => {
                const article = articles.find(item => item.dataset.doc === key) || articles[0];
                const next = articles[(articles.indexOf(article) + 1) % articles.length];
                articles.forEach(item => { item.hidden = item !== article; });
                reader.querySelector('#docReaderTitle').textContent = article.dataset.title;
                reader.querySelector('[data-doc-time]').textContent = article.dataset.time;
                reader.querySelector('[data-doc-next-label]').textContent = next.dataset.title;
                reader.querySelector('[data-doc-next]').dataset.target = next.dataset.doc;
                reader.querySelectorAll('[data-doc-tab]').forEach(tab => tab.setAttribute('aria-pressed', String(tab.dataset.docTab === article.dataset.doc)));
                body.scrollTop = 0;
                updateProgress();
                history.replaceState(null, '', '#doc-' + article.dataset.doc);
                if (animate && !reducedMotion.matches && article.animate) {
                    article.animate([{ opacity: 0.3, transform: 'translateY(12px)' }, { opacity: 1, transform: 'none' }], { duration: 380, easing: 'cubic-bezier(0.16, 1, 0.3, 1)' });
                }
            };
            const open = key => {
                show(key, reader.open);
                if (!reader.open) reader.showModal();
            };

            document.querySelectorAll('[data-doc-open]').forEach(link => link.addEventListener('click', event => {
                event.preventDefault();
                open(link.dataset.docOpen);
            }));
            reader.querySelectorAll('[data-doc-tab]').forEach(tab => tab.addEventListener('click', () => show(tab.dataset.docTab, true)));
            reader.querySelector('[data-doc-next]').addEventListener('click', event => show(event.currentTarget.dataset.target, true));
            reader.querySelector('[data-doc-close]').addEventListener('click', () => reader.close());
            // Backdrop clicks target the dialog itself; its contents fill the panel. closedby="any" covers newer browsers.
            reader.addEventListener('click', event => { if (event.target === reader) reader.close(); });
            reader.addEventListener('close', () => history.replaceState(null, '', location.pathname + location.search));
            body.addEventListener('scroll', updateProgress, { passive: true });

            const linked = location.hash.match(/^#doc-(privacy|terms|security)$/);
            if (linked && order.includes(linked[1])) open(linked[1]);
        })();
    </script>
    <script src="js/pwa.js"></script>
</body>
</html>
