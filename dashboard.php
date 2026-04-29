<?php
/**
 * SecureVault Dashboard - PWA Edition
 */

require_once 'config.php';
require_once 'auth.php';
require_once 'accounts.php';

if (defined('MAINTENANCE_MODE') && MAINTENANCE_MODE) {
    require_once __DIR__ . '/maintenance.php';
    exit;
}

requireLogin();

$user = getCurrentUser();
$userId = getCurrentUserId();
$categories = getUserCategories($userId);
$stats = getVaultStats($userId);

$categoryId = isset($_GET['category']) && $_GET['category'] !== 'all' ? (int)$_GET['category'] : 0;
$accounts = $categoryId ? getAccountsByCategory($userId, $categoryId) : getUserAccounts($userId);

// Icon color map by first letter
$iconColors = ['#FF6B6B','#FF8E53','#FFC300','#2ECC71','#00E676','#3498DB','#9B59B6','#E91E63','#FF5722','#00BCD4'];
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
    <meta name="description" content="Your encrypted personal vault">
    <title>SecureVault — My Vault</title>
    <link rel="manifest" href="manifest.json">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary':      '#00E676',
                        'primary-dark': '#00C65E',
                        'dark-bg':      '#080E1A',
                        'dark-card':    '#0F1729',
                        'dark-border':  '#1A2540',
                        'dark-surface': '#111827',
                    },
                    fontFamily: { sans: ['Inter', 'sans-serif'] }
                }
            }
        }
    </script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; color: #fff; background: #080E1A; }

        /* Safe area for notch phones */
        .pb-safe { padding-bottom: max(env(safe-area-inset-bottom, 0px), 8px); }

        /* Scrollbar hide */
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .hide-scrollbar::-webkit-scrollbar { display: none; }

        /* Backdrop blur nav */
        .nav-blur { backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); }

        /* Card slide-up animation */
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .card-anim { animation: slideUp 0.35s ease-out forwards; opacity: 0; }

        /* Search slide-down */
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-8px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .search-anim { animation: slideDown 0.22s ease-out forwards; }

        /* Modal slide-up (bottom sheet feel) */
        @keyframes modalUp {
            from { transform: translateY(50px); opacity: 0; }
            to   { transform: translateY(0);    opacity: 1; }
        }
        .modal-anim { animation: modalUp 0.28s cubic-bezier(0.34, 1.4, 0.64, 1) forwards; }

        /* FAB pulse */
        @keyframes fabPulse {
            0%,100% { box-shadow: 0 0 0 0   rgba(0,230,118,.45); }
            50%      { box-shadow: 0 0 0 12px rgba(0,230,118,0);   }
        }
        .fab-pulse { animation: fabPulse 2.8s infinite; }

        /* Password dots */
        .pw-dots { letter-spacing: 3px; font-size: 11px; }

        /* Active bottom tab */
        .tab-item svg   { transition: stroke .2s; }
        .tab-item span  { transition: color  .2s; }
        .tab-active svg { stroke: #00E676 !important; }
        .tab-active span{ color:  #00E676 !important; }

        /* Button tap feedback */
        .tap-active:active { transform: scale(0.95); }
    </style>
</head>
<body class="bg-dark-bg">

<!-- ===== TOP HEADER ===== -->
<header class="fixed top-0 left-0 right-0 z-20 px-4 pt-5 pb-3"
        style="background: linear-gradient(180deg,#080E1A 75%,transparent);">
    <div class="max-w-2xl mx-auto flex items-center justify-between md:ml-64">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight">My Assets</h1>
            <p class="text-xs text-primary font-semibold mt-0.5 flex items-center gap-1">
                <span class="w-1.5 h-1.5 rounded-full bg-primary animate-pulse inline-block"></span>
                <?php echo count($accounts); ?> SECURE ASSET<?php echo count($accounts) !== 1 ? 's' : ''; ?> ACTIVE
            </p>
        </div>
        <div class="flex items-center gap-2">
            <button onclick="showSearchOverlay()" id="searchBtn"
                    class="w-9 h-9 flex items-center justify-center rounded-full bg-dark-card border border-dark-border hover:border-primary/40 transition-all">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </button>
            <div class="w-9 h-9 rounded-full bg-primary/20 border border-primary/40
                        flex items-center justify-center text-primary font-bold text-sm">
                <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
            </div>
        </div>
    </div>
</header>

<!-- Alert fixed toast area -->
<div id="alertArea" class="fixed top-20 left-1/2 -translate-x-1/2 z-50 w-full max-w-sm px-4 flex flex-col gap-2 pointer-events-none child:pointer-events-auto"></div>

<!-- ===== CATEGORY CHIPS (horizontal scroll) ===== -->
<div class="fixed top-[68px] left-0 right-0 z-10 bg-dark-bg overflow-x-auto hide-scrollbar md:ml-64">
    <div class="flex gap-2 px-4 py-2 max-w-2xl">
        <a href="dashboard.php"
           class="flex-shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold transition-all
                  <?php echo !$categoryId ? 'bg-primary text-dark-bg' : 'bg-dark-card border border-dark-border text-gray-400 hover:border-primary/40'; ?>">
            All
        </a>
        <?php foreach ($categories as $cat): ?>
        <a href="?category=<?php echo $cat['id']; ?>"
           class="flex-shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold transition-all
                  <?php echo $categoryId === $cat['id'] ? 'bg-primary text-dark-bg' : 'bg-dark-card border border-dark-border text-gray-400 hover:border-primary/40'; ?>">
            <?php echo getCategoryEmoji($cat['name']); ?> <?php echo htmlspecialchars($cat['name']); ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- ===== PWA INSTALL BANNER ===== -->
<div id="installBanner"
     class="hidden fixed top-[118px] left-4 right-4 z-20 bg-dark-card border border-primary/30
            rounded-xl px-4 py-3 flex items-center gap-3 shadow-lg md:ml-64">
    <span class="text-xl">📲</span>
    <div class="flex-1">
        <p class="text-xs font-semibold">Install SecureVault</p>
        <p class="text-xs text-gray-400">Add to home screen for app experience</p>
    </div>
    <button onclick="installApp()" class="px-3 py-1.5 bg-primary text-dark-bg text-xs font-bold rounded-lg">Install</button>
    <button onclick="dismissInstallBanner()" class="text-gray-500 hover:text-white text-xl leading-none">&times;</button>
</div>

<!-- ===== SEARCH OVERLAY ===== -->
<div id="searchOverlay"
     class="hidden fixed inset-0 z-50 bg-dark-bg/97 backdrop-blur-sm pt-16 px-4">
    <div class="max-w-2xl mx-auto search-anim">
        <div class="flex items-center gap-3 mb-5">
            <div class="flex-1 flex items-center gap-2 px-4 py-3 bg-dark-card border border-dark-border rounded-xl focus-within:border-primary transition-all">
                <svg class="w-4 h-4 text-gray-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text" id="searchInput" placeholder="Search your vault..."
                       autocomplete="off" autofocus
                       class="flex-1 bg-transparent text-sm text-white placeholder-gray-500 focus:outline-none"
                       oninput="searchVault(this.value)">
                <button onclick="document.getElementById('searchInput').value=''; searchVault('');"
                        class="text-gray-500 hover:text-white text-lg leading-none">&times;</button>
            </div>
            <button onclick="hideSearchOverlay()"
                    class="text-sm text-gray-400 hover:text-white font-semibold whitespace-nowrap transition-colors">
                Cancel
            </button>
        </div>
    </div>
</div>

<!-- ===== MAIN CONTENT ===== -->
<main class="pt-[130px] pb-32 px-4 max-w-2xl mx-auto md:ml-64" id="mainContent">

    <?php if (empty($accounts)): ?>
    <!-- Empty State -->
    <div class="flex flex-col items-center justify-center min-h-[60vh] text-center">
        <div class="w-24 h-24 rounded-3xl bg-dark-card border border-dark-border
                    flex items-center justify-center text-5xl mb-6">🔒</div>
        <h2 class="text-xl font-bold mb-2">Your Vault is Empty</h2>
        <p class="text-gray-400 text-sm mb-8 max-w-xs">
            Tap the <strong class="text-primary">+</strong> button to securely store your first credential
        </p>
        <button onclick="openAddAccountModal()"
                class="tap-active px-8 py-3 bg-primary hover:bg-primary-dark text-dark-bg font-bold rounded-2xl transition-all shadow-lg shadow-primary/30">
            + Add First Account
        </button>
    </div>

    <?php else: ?>
    <!-- Accounts Grid: 1-col mobile, 2-col desktop -->
    <div id="accountsList" class="grid grid-cols-1 md:grid-cols-2 gap-3">

        <?php foreach ($accounts as $index => $account):
            $letter     = strtoupper(substr($account['service_name'], 0, 1));
            $iconColor  = $iconColors[ord($letter) % count($iconColors)];
        ?>
        <div class="bg-dark-card border border-dark-border rounded-2xl p-4 card-anim
                    hover:border-primary/30 transition-all"
             style="animation-delay: <?php echo min($index * 0.05, 0.4); ?>s;"
             data-account-id="<?php echo $account['id']; ?>"
             data-service="<?php echo strtolower(htmlspecialchars($account['service_name'])); ?>">

            <!-- Top Row: Icon + Name + Actions -->
            <div class="flex items-center gap-3 mb-3">
                <!-- Service Icon -->
                <div class="w-11 h-11 rounded-xl flex items-center justify-center font-bold text-lg flex-shrink-0"
                     style="background:<?php echo $iconColor; ?>18; border:1.5px solid <?php echo $iconColor; ?>44;">
                    <span style="color:<?php echo $iconColor; ?>;"><?php echo $letter; ?></span>
                </div>

                <!-- Name + Username -->
                <div class="flex-1 min-w-0">
                    <h3 class="font-bold text-sm truncate">
                        <?php echo htmlspecialchars($account['service_name']); ?>
                    </h3>
                    <div class="cursor-pointer group flex items-center gap-1 mt-0.5 w-max max-w-full"
                         onclick="copyUserClipboard('<?php echo htmlspecialchars(addslashes($account['username']), ENT_QUOTES); ?>')"
                         title="Tap to copy username">
                        <span class="text-xs text-gray-400 group-hover:text-white transition-colors truncate account-username">
                            <?php echo htmlspecialchars($account['username']); ?>
                        </span>
                        <span class="text-xs opacity-50 group-hover:opacity-100 transition-opacity">📋</span>
                    </div>
                </div>

                <!-- Edit / Delete -->
                <div class="flex gap-0.5 flex-shrink-0">
                    <button onclick="editAccount(<?php echo $account['id']; ?>)"
                            class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-500
                                   hover:bg-white/5 hover:text-white transition-all text-sm">✏️</button>
                    <button onclick="deleteAccount(<?php echo $account['id']; ?>)"
                            class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-500
                                   hover:bg-red-900/20 hover:text-red-400 transition-all text-sm">🗑️</button>
                </div>
            </div>

            <!-- Password Row -->
            <div class="flex items-center gap-2 mb-3 px-3 py-2.5
                        bg-dark-bg/70 rounded-xl border border-dark-border/60">
                <span class="flex-1 pw-dots text-gray-500 tracking-widest
                             password-masked password-display-<?php echo $account['id']; ?>">
                    ••••••••••••
                </span>
                <button onclick="togglePasswordVisibility(this)"
                        class="text-gray-500 hover:text-primary transition-colors flex-shrink-0 p-0.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7
                                 -1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                </button>
            </div>

            <!-- Action Buttons Row -->
            <div class="flex gap-2">
                <button onclick="copyUserClipboard('<?php echo htmlspecialchars(addslashes($account['username']), ENT_QUOTES); ?>')"
                        class="tap-active flex-1 flex items-center justify-center gap-1.5 py-2.5
                               rounded-xl bg-dark-bg/70 border border-dark-border/60
                               text-gray-300 hover:border-primary/40 hover:text-white
                               text-xs font-semibold transition-all">
                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2
                                 m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                    Copy User
                </button>

                <button onclick="triggerReveal(<?php echo $account['id']; ?>, this)"
                        class="tap-active flex-1 flex items-center justify-center gap-1.5 py-2.5
                               rounded-xl bg-primary/10 border border-primary/30
                               text-primary hover:bg-primary/20
                               text-xs font-bold transition-all">
                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1
                                 v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                    </svg>
                    Pass
                </button>
            </div>

            <?php if (!empty($account['website_url'])): ?>
            <div class="mt-2.5 pt-2.5 border-t border-dark-border/40">
                <?php
                    $url = trim($account['website_url']);
                    if (!preg_match('~^https?://~i', $url)) $url = 'https://' . ltrim($url, '/');
                    $safUrl = (stripos($url, 'javascript:') === 0 || stripos($url, 'data:') === 0) ? '#' : htmlspecialchars($url);
                ?>
                <a href="<?php echo $safUrl; ?>" target="_blank" rel="noopener"
                   class="text-xs text-primary hover:underline truncate block">
                    🔗 <?php echo htmlspecialchars($account['website_url']); ?>
                </a>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

    </div>
    <?php endif; ?>
</main>

<!-- ===== FLOATING ACTION BUTTON ===== -->
<button onclick="openAddAccountModal()" id="fabBtn"
        class="fab-pulse fixed bottom-24 right-5 z-20
               w-14 h-14 rounded-full bg-primary text-dark-bg
               shadow-xl shadow-primary/40 flex items-center justify-center
               text-2xl font-bold hover:bg-primary-dark active:scale-90
               transition-all tap-active md:bottom-8">
    +
</button>

<!-- ===== BOTTOM NAV (mobile only) ===== -->
<nav class="md:hidden fixed bottom-0 left-0 right-0 z-20 nav-blur border-t border-dark-border pb-safe"
     style="background: rgba(8,14,26,.93);">
    <div class="flex items-center justify-around px-2 pt-2">

        <button onclick="switchTab('vault')" id="tab-vault"
                class="tab-item tab-active flex flex-col items-center gap-0.5 px-5 py-1">
            <svg class="w-5 h-5" fill="none" stroke="#00E676" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
            <span class="text-[10px] font-semibold" style="color:#00E676">Vault</span>
        </button>

        <button onclick="switchTab('search')" id="tab-search"
                class="tab-item flex flex-col items-center gap-0.5 px-5 py-1 text-gray-500">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <span class="text-[10px] font-semibold">Search</span>
        </button>

        <button onclick="openAddAccountModal()" id="tab-add"
                class="tab-item flex flex-col items-center gap-0.5 px-5 py-1 text-gray-500">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            <span class="text-[10px] font-semibold">Add</span>
        </button>

        <button onclick="switchTab('settings')" id="tab-settings"
                class="tab-item flex flex-col items-center gap-0.5 px-5 py-1 text-gray-500">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066
                         c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756
                         2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724
                         1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0
                         00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0
                         00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0
                         001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                <circle cx="12" cy="12" r="3" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>
            </svg>
            <span class="text-[10px] font-semibold">Settings</span>
        </button>

    </div>
</nav>

<!-- ===== DESKTOP SIDEBAR (hidden on mobile) ===== -->
<aside class="hidden md:flex fixed left-0 top-0 h-screen w-64
              flex-col bg-dark-card border-r border-dark-border z-10 py-6 px-3">
    <!-- Logo -->
    <div class="flex items-center gap-3 px-3 mb-8">
        <div class="w-9 h-9 rounded-xl bg-primary/20 border border-primary/30
                    flex items-center justify-center text-primary font-black text-base">S</div>
        <div>
            <div class="font-bold text-sm tracking-tight text-white">SECURE VAULT</div>
            <div class="text-[10px] text-primary font-bold uppercase tracking-widest">v1.2 PWA</div>
        </div>
    </div>

    <!-- Nav -->
    <nav class="flex-1 space-y-0.5 overflow-y-auto hide-scrollbar">
        <a href="dashboard.php"
           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all
                  <?php echo !$categoryId ? 'bg-primary/10 text-primary' : 'text-gray-400 hover:text-white hover:bg-dark-border/50'; ?>">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
            </svg>
            All Vaults
            <span class="ml-auto text-xs bg-dark-border/80 px-2 py-0.5 rounded-full text-gray-500">
                <?php echo count($accounts); ?>
            </span>
        </a>

        <?php foreach ($categories as $cat):
            $count = 0;
            foreach ($stats['by_category'] as $s) {
                if ($s['name'] === $cat['name']) { $count = $s['count']; break; }
            }
        ?>
        <a href="?category=<?php echo $cat['id']; ?>"
           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all
                  <?php echo $categoryId === $cat['id'] ? 'bg-primary/10 text-primary' : 'text-gray-400 hover:text-white hover:bg-dark-border/50'; ?>">
            <span class="flex-shrink-0"><?php echo getCategoryEmoji($cat['name']); ?></span>
            <?php echo htmlspecialchars($cat['name']); ?>
            <span class="ml-auto text-xs bg-dark-border/80 px-2 py-0.5 rounded-full text-gray-500">
                <?php echo $count; ?>
            </span>
        </a>
        <?php endforeach; ?>
    </nav>

    <!-- User section -->
    <div class="border-t border-dark-border pt-4 mt-4 space-y-0.5">
        <div class="flex items-center gap-3 px-3 py-2 mb-1">
            <div class="w-8 h-8 rounded-full bg-primary/20 border border-primary/30
                        flex items-center justify-center text-primary font-bold text-xs">
                <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
            </div>
            <div class="min-w-0">
                <div class="text-sm font-semibold truncate"><?php echo htmlspecialchars($user['username']); ?></div>
                <div class="text-xs text-gray-500 truncate"><?php echo htmlspecialchars($user['email']); ?></div>
            </div>
        </div>
        <button onclick="openSettingsModal()"
                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium
                       text-gray-400 hover:text-white hover:bg-dark-border/50 transition-all">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066
                         c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924
                         0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0
                         00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066
                         c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756
                         -2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608
                         2.296.07 2.572-1.065zM15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            Settings
        </button>
        <button onclick="openModal('aboutModal')"
                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium
                       text-gray-400 hover:text-white hover:bg-dark-border/50 transition-all">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            About App
        </button>
        <button onclick="logout()"
                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium
                       text-gray-400 hover:text-red-400 hover:bg-red-900/10 transition-all">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
            </svg>
            Logout
        </button>
    </div>
</aside>

<!-- ===== MODALS ===== -->

<!-- Add/Edit Account Modal (bottom sheet on mobile) -->
<div id="accountModal"
     class="hidden fixed inset-0 bg-black/70 backdrop-blur-sm
            flex items-end md:items-center justify-center z-50 p-0 md:p-4">
    <div class="bg-dark-card border border-dark-border rounded-t-2xl md:rounded-2xl
                w-full md:max-w-lg max-h-[92vh] overflow-y-auto shadow-2xl modal-anim">
        <div class="p-5 border-b border-dark-border sticky top-0 bg-dark-card flex justify-between items-center">
            <h2 id="modalTitle" class="text-lg font-bold">Add New Account</h2>
            <button onclick="closeModal('accountModal')" class="text-2xl text-gray-400 hover:text-white">&times;</button>
        </div>
        <form id="accountForm" onsubmit="handleAccountSubmit(event)" class="p-5 space-y-4">
            <input type="hidden" id="accountId" name="id">
            <div>
                <label for="serviceName" class="block text-xs font-bold text-primary uppercase tracking-wider mb-2">Service Name *</label>
                <input type="text" id="serviceName" placeholder="e.g., Gmail, GitHub, Steam"
                       class="w-full px-4 py-3 bg-dark-bg border border-dark-border rounded-xl text-white
                              placeholder-gray-500 text-sm focus:outline-none focus:border-primary transition-all" required>
            </div>
            <div>
                <label for="category" class="block text-xs font-bold text-primary uppercase tracking-wider mb-2">Category *</label>
                <select id="category" class="w-full px-4 py-3 bg-dark-bg border border-dark-border rounded-xl
                                             text-white text-sm focus:outline-none focus:border-primary transition-all">
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>" class="bg-dark-card">
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="accountUsername" class="block text-xs font-bold text-primary uppercase tracking-wider mb-2">Username / Email *</label>
                <input type="text" id="accountUsername" placeholder="your@email.com"
                       class="w-full px-4 py-3 bg-dark-bg border border-dark-border rounded-xl text-white
                              placeholder-gray-500 text-sm focus:outline-none focus:border-primary transition-all" required>
            </div>
            <div>
                <label for="accountPassword" class="block text-xs font-bold text-primary uppercase tracking-wider mb-2">Password *</label>
                <input type="password" id="accountPassword" placeholder="••••••••"
                       class="w-full px-4 py-3 bg-dark-bg border border-dark-border rounded-xl text-white
                              placeholder-gray-500 text-sm focus:outline-none focus:border-primary transition-all" required>
            </div>
            <div>
                <label for="website" class="block text-xs font-bold text-primary uppercase tracking-wider mb-2">Website</label>
                <input type="url" id="website" placeholder="https://example.com"
                       class="w-full px-4 py-3 bg-dark-bg border border-dark-border rounded-xl text-white
                              placeholder-gray-500 text-sm focus:outline-none focus:border-primary transition-all">
            </div>
            <div>
                <label for="notes" class="block text-xs font-bold text-primary uppercase tracking-wider mb-2">Notes</label>
                <textarea id="notes" placeholder="Any extra info..." rows="2"
                          class="w-full px-4 py-3 bg-dark-bg border border-dark-border rounded-xl text-white
                                 placeholder-gray-500 text-sm focus:outline-none focus:border-primary transition-all resize-none"></textarea>
            </div>
            <div>
                <label for="masterPassword" class="block text-xs font-bold text-primary uppercase tracking-wider mb-2">Master Key *</label>
                <input type="password" id="masterPassword" placeholder="Enter your master key"
                       class="w-full px-4 py-3 bg-dark-bg border border-dark-border rounded-xl text-white
                              placeholder-gray-500 text-sm focus:outline-none focus:border-primary transition-all" required>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit"
                        class="flex-1 px-4 py-3 bg-primary hover:bg-primary-dark text-dark-bg font-bold rounded-xl transition-all">
                    Save Account
                </button>
                <button type="button" onclick="closeModal('accountModal')"
                        class="px-4 py-3 bg-dark-border text-sm rounded-xl transition-all hover:bg-dark-border/70">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Settings Modal -->
<div id="settingsModal"
     class="hidden fixed inset-0 bg-black/70 backdrop-blur-sm
            flex items-end md:items-center justify-center z-50 p-0 md:p-4">
    <div class="bg-dark-card border border-dark-border rounded-t-2xl md:rounded-2xl
                w-full md:max-w-md shadow-2xl modal-anim">
        <div class="p-5 border-b border-dark-border flex justify-between items-center">
            <h2 class="text-lg font-bold">Settings</h2>
            <button onclick="closeModal('settingsModal')" class="text-2xl text-gray-400 hover:text-white">&times;</button>
        </div>
        <!-- Profile Card -->
        <div class="p-5 border-b border-dark-border flex items-center gap-4">
            <div class="w-12 h-12 rounded-full bg-primary/20 border border-primary/30
                        flex items-center justify-center text-primary font-bold text-lg">
                <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
            </div>
            <div>
                <div class="font-bold"><?php echo htmlspecialchars($user['username']); ?></div>
                <div class="text-xs text-gray-400"><?php echo htmlspecialchars($user['email']); ?></div>
            </div>
        </div>
        <form id="settingsForm" onsubmit="handleSettingsSubmit(event)" class="p-5 space-y-4">
            <div>
                <label for="oldPassword" class="block text-xs font-bold text-primary uppercase tracking-wider mb-2">Current Master Key *</label>
                <input type="password" id="oldPassword" placeholder="••••••••"
                       class="w-full px-4 py-3 bg-dark-bg border border-dark-border rounded-xl text-white
                              placeholder-gray-500 text-sm focus:outline-none focus:border-primary transition-all" required>
            </div>
            <div>
                <label for="newPassword" class="block text-xs font-bold text-primary uppercase tracking-wider mb-2">New Master Key *</label>
                <input type="password" id="newPassword" placeholder="••••••••"
                       class="w-full px-4 py-3 bg-dark-bg border border-dark-border rounded-xl text-white
                              placeholder-gray-500 text-sm focus:outline-none focus:border-primary transition-all" required>
            </div>
            <div>
                <label for="confirmNewPassword" class="block text-xs font-bold text-primary uppercase tracking-wider mb-2">Confirm New Key *</label>
                <input type="password" id="confirmNewPassword" placeholder="••••••••"
                       class="w-full px-4 py-3 bg-dark-bg border border-dark-border rounded-xl text-white
                              placeholder-gray-500 text-sm focus:outline-none focus:border-primary transition-all" required>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit"
                        class="flex-1 px-4 py-3 bg-primary hover:bg-primary-dark text-dark-bg font-bold rounded-xl transition-all">
                    Update Key
                </button>
                <button type="button" onclick="closeModal('settingsModal')"
                        class="px-4 py-3 bg-dark-border text-sm rounded-xl transition-all">
                    Cancel
                </button>
            </div>
        </form>
        <div class="p-5 border-t border-dark-border space-y-3">
            <button onclick="closeModal('settingsModal'); openModal('aboutModal');"
                    class="w-full flex items-center justify-center gap-2 py-3 rounded-xl
                           bg-primary/10 border border-primary/30 text-primary hover:bg-primary/20
                           transition-all text-sm font-semibold">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                About App
            </button>
            <button onclick="logout()"
                    class="w-full flex items-center justify-center gap-2 py-3 rounded-xl
                           text-red-400 hover:bg-red-900/10 border border-red-900/30
                           transition-all text-sm font-semibold">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                Lock & Logout
            </button>
        </div>
    </div>
</div>

<!-- Alert Box -->
<div id="alertBox" class="fixed top-4 right-4 z-50 max-w-xs w-full pointer-events-none"></div>

<!-- Master Key Modal -->
<div id="masterKeyModal"
     class="hidden fixed inset-0 bg-black/70 backdrop-blur-sm
            flex items-end md:items-center justify-center z-50 p-0 md:p-4">
    <div class="fixed inset-0" onclick="closeModal('masterKeyModal')"></div>
    <div class="bg-dark-card border border-dark-border rounded-t-2xl md:rounded-2xl
                w-full md:max-w-sm relative z-10 shadow-2xl modal-anim">
        <div class="p-5 border-b border-dark-border flex justify-between items-center">
            <h2 id="masterKeyModalTitle" class="text-lg font-bold flex items-center gap-2">
                <span class="text-primary">🔓</span> Authenticate
            </h2>
            <button onclick="closeModal('masterKeyModal')" class="text-2xl text-gray-400 hover:text-white">&times;</button>
        </div>
        <form id="masterKeyForm" onsubmit="handleMasterKeySubmit(event)" class="p-5 space-y-4">
            <p id="masterKeyModalMessage" class="text-sm text-gray-400">Enter your Master Key to perform this action.</p>
            <div>
                <label for="promptMasterKey" class="block text-xs font-bold text-primary uppercase tracking-wider mb-2">Master Key</label>
                <div class="relative">
                    <input type="password" id="promptMasterKey" placeholder="••••••••"
                           class="w-full px-4 py-3 bg-dark-bg border border-dark-border rounded-xl text-white
                                  placeholder-gray-500 text-sm focus:outline-none focus:border-primary transition-all pr-12" required>
                    <button type="button" onclick="const input = document.getElementById('promptMasterKey'); input.type = input.type === 'password' ? 'text' : 'password';"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-primary transition-colors p-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7
                                     -1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                    </button>
                </div>
            </div>
            <div class="flex gap-3">
                <button type="submit" id="masterKeySubmitBtn"
                        class="flex-1 px-4 py-3 bg-primary hover:bg-primary-dark text-dark-bg font-bold rounded-xl transition-all">
                    Confirm
                </button>
                <button type="button" onclick="closeModal('masterKeyModal')"
                        class="px-4 py-3 bg-dark-border text-sm rounded-xl transition-all">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Logout Modal -->
<div id="logoutModal"
     class="hidden fixed inset-0 bg-black/70 backdrop-blur-sm
            flex items-end md:items-center justify-center z-50 p-0 md:p-4">
    <div class="fixed inset-0" onclick="closeModal('logoutModal')"></div>
    <div class="bg-dark-card border border-dark-border rounded-t-2xl md:rounded-2xl
                w-full md:max-w-sm relative z-10 shadow-2xl modal-anim text-center p-6">
        <div class="text-4xl mb-3">👋</div>
        <h2 class="text-xl font-bold mb-2">Secure Logout</h2>
        <p class="text-sm text-gray-400 mb-6">Lock your vault and end your secure session?</p>
        <div class="flex gap-3">
            <button onclick="executeLogout()"
                    class="flex-1 px-4 py-3 bg-red-600 hover:bg-red-700 text-white font-bold rounded-xl transition-all">
                Lock & Logout
            </button>
            <button onclick="closeModal('logoutModal')"
                    class="flex-1 px-4 py-3 bg-dark-border text-sm rounded-xl transition-all">
                Cancel
            </button>
        </div>
    </div>
</div>

<script>
    const USER_SALT = "<?php echo isset($_SESSION['master_key_salt']) ? addslashes($_SESSION['master_key_salt']) : ''; ?>";
    const APP_VERSION = "v5";
    if (localStorage.getItem('app_version') !== APP_VERSION) {
        localStorage.setItem('app_version', APP_VERSION);
        if ('caches' in window) {
            caches.keys().then(names => {
                for (let name of names) caches.delete(name);
            });
        }
        window.location.reload(true);
    }
</script>
<!-- About App Modal -->
<div id="aboutModal"
     class="hidden fixed inset-0 bg-black/70 backdrop-blur-md
            flex items-center justify-center z-50 p-4">
    <div class="fixed inset-0" onclick="closeModal('aboutModal')"></div>
    <div class="bg-dark-card border border-primary/30 rounded-3xl
                w-full max-w-sm relative z-10 shadow-[0_0_50px_rgba(0,230,118,0.15)] modal-anim overflow-hidden">
        <!-- Neon Glow Background Effect -->
        <div class="absolute -top-24 -right-24 w-48 h-48 bg-primary/20 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-24 -left-24 w-48 h-48 bg-primary/10 rounded-full blur-3xl"></div>
        
        <div class="relative p-8 text-center flex flex-col items-center">
            <button onclick="closeModal('aboutModal')" class="absolute top-4 right-5 text-2xl text-gray-400 hover:text-white transition-colors">&times;</button>
            
            <h2 class="text-xs font-bold text-gray-500 tracking-[0.3em] uppercase mb-6">About the App</h2>
            
            <!-- Animated Profile Picture -->
            <div class="relative mb-5 group animate-float">
                <!-- Rotating Neon Ring -->
                <div class="absolute -inset-1 rounded-full bg-gradient-to-r from-primary via-[#00C65E] to-[#080E1A] opacity-75 group-hover:opacity-100 blur transition-opacity duration-500" style="animation: rotateLighting 3s linear infinite;"></div>
                <div class="absolute -inset-1 rounded-full bg-gradient-to-r from-primary via-[#00C65E] to-[#080E1A]" style="animation: rotateLighting 3s linear infinite;"></div>
                <!-- Profile Image -->
                <img src="ronan.jpg" alt="Ronan - Security Developer" class="relative w-28 h-28 rounded-full object-cover border-2 border-dark-card shadow-2xl transition-transform duration-500 group-hover:scale-105">
            </div>
            
            <h3 class="text-2xl font-black text-white tracking-tight mb-1">Ronan</h3>
            <p class="text-sm text-primary font-bold tracking-widest uppercase mb-4">Security Developer</p>
            
            <p class="text-sm text-gray-400 leading-relaxed mb-6">
                Architect and visionary behind <span class="text-white font-semibold">SecureVault V1.2 PWA</span>. Engineering bank-grade security into a seamless, high-performance web experience.
            </p>
            
            <div class="w-full h-px bg-gradient-to-r from-transparent via-dark-border to-transparent mb-6"></div>
            
            <div class="flex gap-4 items-center justify-center text-xs text-gray-500">
                <span class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-primary animate-pulse"></span> Systems Active</span>
                <span>v1.2.0</span>
            </div>
        </div>
    </div>
</div>

<script src="js/dashboard.js?v=6"></script>
<script src="js/pwa.js"></script>

<!-- PWA Install Banner (Premium) -->
<div id="installBanner" class="hidden fixed bottom-24 left-6 right-6 md:bottom-10 md:right-10 md:left-auto md:max-w-sm z-[100] animate-bounce">
    <div class="bg-dark-card/95 backdrop-blur-xl border border-primary/30 rounded-2xl p-5 shadow-2xl shadow-primary/20 flex flex-col gap-4">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-primary/20 rounded-xl flex items-center justify-center text-primary text-2xl shrink-0">🔐</div>
            <div class="flex-1">
                <h3 class="font-bold text-white text-xs">Unlock Native Experience</h3>
                <p class="text-[10px] text-gray-400">Install SecureVault for a premium, fast experience.</p>
            </div>
            <button onclick="dismissInstallBanner()" class="text-gray-500 hover:text-white">&times;</button>
        </div>
        <div class="flex gap-2 w-full">
            <button onclick="installApp()" class="flex-1 px-4 py-2 bg-primary hover:bg-primary-dark text-dark-bg font-bold rounded-lg text-[11px] transition-all">Install Now</button>
            <button onclick="dismissInstallBanner()" class="flex-1 px-4 py-2 bg-dark-border text-gray-400 rounded-lg text-[11px] hover:text-white transition-all">Later</button>
        </div>
    </div>
</div>
</body>
</html>
