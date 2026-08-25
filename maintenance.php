<?php
// SecureVault Maintenance Page
http_response_code(503);
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#080E1A">
    <title>SecureVault — Under Maintenance</title>
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
        body { font-family: 'Inter', sans-serif; background-color: #080E1A; color: white; }
        .bg-dots {
            background-image: radial-gradient(rgba(0,230,118,.08) 1px, transparent 1px);
            background-size: 28px 28px;
        }
        @keyframes pulseGlow {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.7; transform: scale(1.05); }
        }
        .pulse-anim { animation: pulseGlow 3s ease-in-out infinite; }
    </style>
</head>
<body class="bg-dark-bg bg-dots min-h-screen flex flex-col items-center justify-center px-6 text-center">

    <div class="fixed inset-0 pointer-events-none overflow-hidden flex items-center justify-center">
        <div class="w-[500px] h-[500px] rounded-full opacity-10 pulse-anim"
             style="background: radial-gradient(circle, #00E676 0%, transparent 70%);"></div>
    </div>

    <div class="relative z-10 max-w-md w-full bg-dark-card border border-dark-border rounded-3xl p-8 shadow-2xl">
        <div class="w-20 h-20 mx-auto rounded-full bg-primary/10 border border-primary/30 flex items-center justify-center mb-6 pulse-anim">
            <img src="logo.svg" alt="" class="w-10 h-10" aria-hidden="true">
        </div>
        
        <h1 class="text-2xl font-extrabold mb-2 tracking-tight">System Upgrade</h1>
        <p class="text-gray-400 text-sm mb-6 leading-relaxed">
            SecureVault is currently undergoing scheduled maintenance to upgrade security and add new features. 
            We are working to get your vault back online as quickly as possible.
        </p>

        <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-primary/10 border border-primary/20 text-primary text-xs font-bold uppercase tracking-wider mb-2">
            <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
            Maintenance in progress
        </div>
    </div>

    <p class="text-xs text-gray-600 mt-8 relative z-10">
        Your data remains fully encrypted and secure.
    </p>
</body>
</html>
