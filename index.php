<?php
require_once __DIR__ . '/app/Version.php';
require_once __DIR__ . '/app/ViewBootstrap.php';

$config = require __DIR__ . '/app/Config.php';
$viewContext = \BitaxeOc\App\ViewBootstrap::forIndex($config, $_SERVER, $_GET);

$appVersion = (string)$viewContext['appVersion'];
$displayVersion = (string)$viewContext['displayVersion'];
$brandVersionLine = (string)$viewContext['brandVersionLine'];
$assetVersionToken = (string)$viewContext['assetVersionToken'];
$maxUploadFilesPerBatch = (int)$viewContext['maxUploadFilesPerBatch'];
$maxCsvFileBytes = (int)$viewContext['maxCsvFileBytes'];
$maxUploadTotalBytes = (int)$viewContext['maxUploadTotalBytes'];
$csvMaxDataRows = (int)$viewContext['csvMaxDataRows'];
$csvParseTimeBudgetMs = (int)$viewContext['csvParseTimeBudgetMs'];
$shareToken = (string)$viewContext['shareToken'];
$isShareView = (bool)$viewContext['isShareView'];
$isStaticTestShareView = (bool)$viewContext['isStaticTestShareView'];
$importToken = (string)($viewContext['importToken'] ?? '');
$isImportView = (bool)($viewContext['isImportView'] ?? false);
$basePath = (string)$viewContext['basePath'];
$appBaseUrl = (string)$viewContext['appBaseUrl'];
$assetBasePath = (string)$viewContext['assetBasePath'];
$seoCanonicalUrl = (string)$viewContext['seoCanonicalUrl'];
$seoTitle = (string)$viewContext['seoTitle'];
$seoDescription = (string)$viewContext['seoDescription'];
$seoKeywords = (string)$viewContext['seoKeywords'];
$seoRobots = (string)$viewContext['seoRobots'];
$tailwindMode = (string)$viewContext['tailwindMode'];
$tailwindUseStatic = (bool)$viewContext['tailwindUseStatic'];
$tailwindStaticCssRel = (string)$viewContext['tailwindStaticCssRel'];
$tailwindStaticCssHref = $assetBasePath . '/' . ltrim($tailwindStaticCssRel, '/')
    . '?v=' . rawurlencode($assetVersionToken);
$jsonJsFlags = JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
    | JSON_UNESCAPED_SLASHES
    | JSON_UNESCAPED_UNICODE;
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($seoTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="description" content="<?= htmlspecialchars($seoDescription, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="keywords" content="<?= htmlspecialchars($seoKeywords, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="robots" content="<?= htmlspecialchars($seoRobots, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="googlebot" content="<?= htmlspecialchars($seoRobots, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="author" content="colortr">
    <meta name="theme-color" content="#070b14">
    <link rel="canonical" href="<?= htmlspecialchars($seoCanonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="alternate" hreflang="en" href="<?= htmlspecialchars($seoCanonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="alternate" hreflang="x-default" href="<?= htmlspecialchars($seoCanonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Bitaxe OC">
    <meta property="og:url" content="<?= htmlspecialchars($seoCanonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:title" content="<?= htmlspecialchars($seoTitle, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($seoDescription, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:image" content="<?= htmlspecialchars($appBaseUrl . '/assets/favicon-192.png?v=' . $assetVersionToken, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:image:width" content="192">
    <meta property="og:image:height" content="192">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?= htmlspecialchars($seoTitle, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($seoDescription, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($appBaseUrl . '/assets/favicon-192.png?v=' . $assetVersionToken, ENT_QUOTES, 'UTF-8') ?>">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self' data: blob:; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; img-src 'self' data: blob:; font-src 'self' data: https://fonts.gstatic.com; connect-src 'self' data: blob: https://cdn.tailwindcss.com https://cdn.jsdelivr.net; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'">
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebApplication",
      "name": "Bitaxe & NerdAxe OC Stats Analyzer",
      "url": "<?= htmlspecialchars($appBaseUrl . '/', ENT_QUOTES, 'UTF-8') ?>",
      "applicationCategory": "UtilitiesApplication",
      "operatingSystem": "Web",
      "description": "Analyze Bitaxe and NerdAxe lottery mining OC stats from CSV benchmarks. Compare hashrate, efficiency (J/TH), ASIC and VRM temperatures, error rate, and power to find stable overclock profiles.",
      "keywords": "Bitaxe, NerdAxe, lottery mining, OC stats, overclock, hashrate, JTH, J/TH, ASIC miner tuning, benchmark dashboard, GT800"
    }
    </script>
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($assetBasePath, ENT_QUOTES, 'UTF-8') ?>/favicon.ico?v=<?= htmlspecialchars($assetVersionToken, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="icon" type="image/svg+xml" sizes="any" href="<?= htmlspecialchars($assetBasePath, ENT_QUOTES, 'UTF-8') ?>/assets/favicon.svg?v=<?= htmlspecialchars($assetVersionToken, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= htmlspecialchars($assetBasePath, ENT_QUOTES, 'UTF-8') ?>/assets/favicon-32.png?v=<?= htmlspecialchars($assetVersionToken, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= htmlspecialchars($assetBasePath, ENT_QUOTES, 'UTF-8') ?>/assets/favicon-192.png?v=<?= htmlspecialchars($assetVersionToken, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($assetBasePath, ENT_QUOTES, 'UTF-8') ?>/assets/favicon-192.png?v=<?= htmlspecialchars($assetVersionToken, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="manifest" href="<?= htmlspecialchars($assetBasePath, ENT_QUOTES, 'UTF-8') ?>/site.webmanifest?v=<?= htmlspecialchars($assetVersionToken, ENT_QUOTES, 'UTF-8') ?>">

    <script>
        (function bootstrapTheme() {
            const themeKey = 'bitaxeThemePreference';
            const variantKey = 'bitaxeThemeVariantPreference';
            let theme = 'dark';
            let variant = 'purple';
            try {
                const saved = localStorage.getItem(themeKey);
                if (saved === 'light' || saved === 'dark') theme = saved;
                const savedVariant = String(localStorage.getItem(variantKey) || '').toLowerCase();
                if (savedVariant === 'orange' || savedVariant === 'purple') variant = savedVariant;
            } catch (_) {}
            const root = document.documentElement;
            root.classList.remove('dark', 'light-theme', 'theme-variant-purple', 'theme-variant-orange');
            root.classList.add(theme === 'light' ? 'light-theme' : 'dark');
            root.classList.add(variant === 'orange' ? 'theme-variant-orange' : 'theme-variant-purple');
            root.setAttribute('data-theme', theme);
            root.setAttribute('data-theme-variant', variant);
            const metaTheme = document.querySelector('meta[name="theme-color"]');
            if (metaTheme) {
                const lightMeta = variant === 'orange' ? '#fff7ed' : '#f1f5f9';
                metaTheme.setAttribute('content', theme === 'light' ? lightMeta : '#070b14');
            }
        })();
    </script>
    
    <!-- Tailwind CSS -->
    <?php if ($tailwindUseStatic): ?>
    <link rel="preload" href="<?= htmlspecialchars($tailwindStaticCssHref, ENT_QUOTES, 'UTF-8') ?>" as="style">
    <link rel="stylesheet" href="<?= htmlspecialchars($tailwindStaticCssHref, ENT_QUOTES, 'UTF-8') ?>">
    <?php else: ?>
    <link rel="preload" href="<?= htmlspecialchars($assetBasePath, ENT_QUOTES, 'UTF-8') ?>/assets/vendor/tailwindcss-cdn.js?v=<?= htmlspecialchars($assetVersionToken, ENT_QUOTES, 'UTF-8') ?>" as="script">
    <script src="<?= htmlspecialchars($assetBasePath, ENT_QUOTES, 'UTF-8') ?>/assets/vendor/tailwindcss-cdn.js?v=<?= htmlspecialchars($assetVersionToken, ENT_QUOTES, 'UTF-8') ?>" onerror="this.onerror=null;this.src='https://cdn.tailwindcss.com';"></script>
    <?php endif; ?>
    
    <!-- Google Fonts -->
    <link rel="dns-prefetch" href="//fonts.googleapis.com">
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link rel="dns-prefetch" href="//cdn.jsdelivr.net">
    <link rel="dns-prefetch" href="//cdnjs.cloudflare.com">
    <?php if (!$tailwindUseStatic): ?>
    <link rel="dns-prefetch" href="//cdn.tailwindcss.com">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <?php if (!$tailwindUseStatic): ?>
    <link rel="preconnect" href="https://cdn.tailwindcss.com" crossorigin>
    <?php endif; ?>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
    
    <script>
        if (window.tailwind) {
            window.tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'], mono: ['JetBrains Mono', 'monospace'] },
                    colors: {
                        dark: { 950: '#070b14', 900: '#0b1320', 800: '#131f33', 700: '#22324a' },
                        neon: { purple: '#d946ef', amber: '#f59e0b', blue: '#06b6d4', red: '#ef4444', green: '#10b981' }
                    },
                    animation: { 'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite' }
                }
            }
            };
        }
    </script>
    <link rel="stylesheet" href="<?= htmlspecialchars($assetBasePath, ENT_QUOTES, 'UTF-8') ?>/assets/css/app-core.css?v=<?= htmlspecialchars($assetVersionToken, ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="antialiased min-h-screen flex flex-col pb-12 relative">

    <!-- Header -->
    <header id="app-header" class="bg-dark-900/80 backdrop-blur-xl border-b border-dark-700 sticky top-0 z-30">
        <div id="header-shell" class="max-w-[1920px] mx-auto px-6 py-3 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <a href="<?= htmlspecialchars($appBaseUrl . '/', ENT_QUOTES, 'UTF-8') ?>" class="brand-home-link flex items-center gap-4 md:shrink-0" aria-label="Bitaxe OC Ana Sayfa">
                <div class="brand-emblem-shell p-2.5 rounded-xl">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                </div>
                <div>
                    <h1 class="text-xl font-black text-white tracking-tighter">
                        BitAxe <span class="brand-accent-text">MASTERDATA</span>
                    </h1>
                    <p id="brand-version-line" class="text-[10px] text-slate-400 font-mono tracking-widest"><?= htmlspecialchars($brandVersionLine, ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </a>
            <button id="mobile-header-menu-btn" type="button" class="header-menu-btn md:hidden inline-flex items-center justify-center" aria-label="Menu" aria-controls="mobile-header-menu-panel" aria-expanded="false">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h16M4 12h16M4 17h16"></path>
                </svg>
            </button>
            <div id="mobile-header-menu-panel" class="hidden md:flex md:flex-1 md:items-center md:justify-end md:gap-3 w-full">
            <div id="header-actions" class="flex items-center gap-2 flex-wrap justify-center md:justify-end md:shrink-0">
                <button id="variant-toggle-btn" type="button" class="variant-toggle-btn" aria-label="Switch to orange accent theme" aria-pressed="false" title="Orange Accent">
                    <span class="variant-toggle-track" aria-hidden="true">
                        <span class="variant-swatch variant-swatch-purple"></span>
                        <span class="variant-swatch variant-swatch-orange"></span>
                        <span class="variant-toggle-thumb"></span>
                    </span>
                </button>
                <button id="theme-toggle-btn" type="button" class="theme-toggle-btn" aria-label="Switch to light theme" aria-pressed="false" title="Light Theme">
                    <span class="theme-toggle-track" aria-hidden="true">
                        <svg class="theme-toggle-icon sun" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v2m0 14v2m9-9h-2M5 12H3m15.364 6.364-1.414-1.414M7.05 7.05 5.636 5.636m12.728 0-1.414 1.414M7.05 16.95l-1.414 1.414"></path><circle cx="12" cy="12" r="3.5" stroke-width="2"></circle></svg>
                        <svg class="theme-toggle-icon moon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646a9 9 0 1011.708 11.708z"></path></svg>
                        <span class="theme-toggle-thumb"></span>
                    </span>
                </button>
                <div id="language-control" class="w-full md:w-auto flex justify-center md:justify-end">
                    <div class="language-control-wrap">
                        <div id="language-picker" class="language-select-wrap">
                            <button id="language-toggle-btn" type="button" class="language-trigger header-menu-btn" aria-haspopup="listbox" aria-expanded="false">
                                <span id="language-current-label" class="language-current-label">🇬🇧 ENGLISH</span>
                                <svg class="w-3 h-3 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            </button>
                            <div id="language-menu" class="language-menu hidden" role="listbox"></div>
                            <select id="language-selector" class="language-select" aria-label="Dil Seçimi">
                                <option value="tr">🇹🇷 Türkçe (Default)</option>
                                <option value="en">🇬🇧 English</option>
                                <option value="zh-CN">🇨🇳 简体中文</option>
                                <option value="hi">🇮🇳 हिन्दी</option>
                                <option value="es">🇪🇸 Español</option>
                                <option value="fr">🇫🇷 Français</option>
                                <option value="ar">🇸🇦 العربية</option>
                                <option value="bn">🇧🇩 বাংলা</option>
                                <option value="pt">🇵🇹 Português</option>
                                <option value="ru">🇷🇺 Русский</option>
                                <option value="ur">🇵🇰 اردو</option>
                                <option value="asm">🏁 Assembly</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="relative">
                    <button id="view-menu-btn" class="header-menu-btn hidden bg-transparent hover:bg-transparent text-white px-4 py-2 rounded-lg transition flex items-center gap-2 whitespace-nowrap">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                        <span id="lbl-view-menu" class="text-xs font-bold">GÖRÜNÜM</span>
                    </button>
                    <div id="view-menu-dropdown" class="hidden absolute right-0 mt-2 p-3 rounded-xl border border-dark-700 bg-dark-900/95 shadow-2xl z-40">
                        <div id="lbl-view-boxes" class="text-[10px] uppercase tracking-widest text-slate-500 mb-2">Kutular</div>
                        <div id="view-menu-list" class="space-y-1"></div>
                        <button id="view-show-all-btn" type="button" class="w-full mt-3 px-3 py-2 rounded-lg border border-dark-700 bg-dark-800/80 hover:bg-dark-700 text-[10px] font-bold uppercase tracking-widest text-slate-200">Tumunu Ac</button>
                    </div>
                </div>
                <button id="show-upload-btn" class="header-menu-btn hidden bg-transparent hover:bg-transparent text-white px-4 py-2 rounded-lg transition flex items-center gap-2 whitespace-nowrap">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"></path></svg>
                    <span id="lbl-show-upload" class="text-xs font-bold">DOSYA YÖNETİCİSİ</span>
                </button>
                <button id="export-jpeg-btn" class="header-menu-btn hidden bg-transparent hover:bg-transparent text-white px-4 py-2 rounded-lg transition flex items-center gap-2 whitespace-nowrap">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h4l2-2h6l2 2h4v12H3V7z"></path><circle cx="12" cy="13" r="3" stroke-width="2"></circle></svg>
                    <span id="lbl-export-jpeg" class="text-xs font-bold">JPEG EXPORT</span>
                </button>
                <button id="export-html-btn" class="header-menu-btn hidden bg-transparent hover:bg-transparent text-white px-4 py-2 rounded-lg transition flex items-center gap-2 whitespace-nowrap">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l-3 3 3 3m8-6l3 3-3 3M13 8l-2 8"></path></svg>
                    <span id="lbl-export-html" class="text-xs font-bold">HTML EXPORT</span>
                </button>
                <button id="share-btn" class="header-menu-btn hidden bg-transparent hover:bg-transparent text-neon-green px-4 py-2 rounded-lg transition flex items-center gap-2 whitespace-nowrap">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="18" cy="5" r="2.6" stroke-width="2"></circle>
                        <circle cx="6" cy="12" r="2.6" stroke-width="2"></circle>
                        <circle cx="18" cy="19" r="2.6" stroke-width="2"></circle>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.5 10.6l6.9-4.2M8.5 13.4l6.9 4.2"></path>
                    </svg>
                    <span id="lbl-share" class="text-xs font-bold">PAYLAŞ</span>
                </button>
            </div>
            </div>
        </div>
    </header>

    <main id="main-shell" class="flex-grow max-w-[1920px] mx-auto px-4 py-6 w-full relative">
        
        <!-- UPLOAD OVERLAY -->
        <section id="upload-section" class="fixed inset-0 z-50 flex flex-col items-center justify-center p-6">
            <button id="close-upload-btn" class="hidden absolute top-5 right-5 px-3 py-2 rounded-lg border border-dark-700 bg-dark-900/80 text-xs font-bold text-slate-300 hover:text-white hover:border-slate-500 transition">
                <span id="lbl-close-upload">PANELE DON</span>
            </button>
            <div id="upload-modal" class="w-full max-w-3xl transform transition-transform duration-500 hover:scale-[1.01]">
                <p id="upload-export-hint" class="mb-2 text-center text-xs text-slate-500">
                    <span id="upload-export-hint-text">Bu ekran, şu kaynaktan alınan export dosyalarıyla çalışır:</span>
                    <a id="upload-export-hint-link" href="https://github.com/swdots/bitaxe-benchmark-webgui" target="_blank" rel="noopener noreferrer" class="text-neon-purple hover:underline">bitaxe-benchmark-webgui</a>
                </p>
                <div id="drop-zone" class="file-drop-zone rounded-3xl p-10 md:p-12 text-center cursor-pointer relative overflow-hidden group border-2 border-dashed border-dark-700 hover:border-neon-purple shadow-2xl bg-dark-900/90">
                    <input type="file" id="fileInput" multiple accept=".csv" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-50">
                    <div class="pointer-events-none relative z-10 flex flex-col items-center">
                        <div class="upload-icon-shell w-24 h-24 mb-6 group-hover:scale-110 transition-transform duration-300 animate-float flex items-center justify-center">
                            <svg class="upload-icon-svg w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.95" d="M12 20V8m0 0 3.5 3.5M12 8 8.5 11.5"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="M4.5 15.5v1.5A2.5 2.5 0 0 0 7 19.5h10a2.5 2.5 0 0 0 2.5-2.5v-1.5"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M6 19.5h12"/>
                            </svg>
                        </div>
                        <h3 id="upload-title" class="text-3xl font-black text-white mb-3 tracking-tight">VERİLERİ YÜKLEYİN</h3>
                        <p id="upload-subtitle" class="text-slate-400 mb-6 max-w-md mx-auto">Tüm benchmark geçmişinizi tek seferde yükleyin.</p>
                        <div class="flex gap-3">
                            <span id="upload-badge-auto" class="px-3 py-1 bg-neon-purple/10 text-neon-purple border border-neon-purple/20 rounded text-xs font-mono">Auto Master</span>
                            <span id="upload-badge-high" class="px-3 py-1 bg-neon-green/10 text-neon-green border border-neon-green/20 rounded text-xs font-mono">High-Perf Lock</span>
                        </div>
                    </div>
                </div>
                <div id="sample-preview-row" class="mt-4 flex justify-center">
                    <button id="sample-preview-btn" type="button" class="px-4 py-2 rounded-lg border border-dark-700 bg-dark-800/90 hover:bg-dark-700 text-xs font-bold text-slate-200 hover:text-white transition tracking-wide uppercase">
                        <span id="upload-sample-preview-label">ÖRNEK DOSYAYLA GÖRÜNTÜLE</span>
                    </button>
                </div>

                <div id="file-list-wrapper" class="hidden mt-4 animate-fade-in-up">
                    <div class="bg-dark-900 border border-dark-700 rounded-2xl p-6 shadow-2xl">
                        <div class="flex justify-between items-center mb-4 pb-2 border-b border-dark-800">
                            <h4 id="upload-file-list-title" class="font-bold text-white flex items-center gap-2">Dosya Listesi</h4>
                            <span class="text-xs text-slate-500 font-mono" id="file-count-label">0 Dosya</span>
                        </div>
                        <div id="file-list" class="space-y-2 max-h-48 overflow-y-auto custom-scroll mb-6 pr-2"></div>
                        <button id="process-btn" class="w-full text-white font-black py-4 rounded-xl shadow-lg transition-all transform active:scale-[0.98] flex items-center justify-center gap-3 tracking-widest text-sm">
                            <span id="upload-process-label">ANALİZİ BAŞLAT</span>
                        </button>
                    </div>
                </div>
            </div>
        </section>
        <div id="app-toast-stack" class="app-toast-stack" aria-live="polite" aria-atomic="false"></div>
        <div id="share-modal-overlay" class="share-modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="share-modal-title">
            <div id="share-modal-card" class="share-modal-card">
                <h3 id="share-modal-title" class="share-modal-title">Paylaşım Linki Hazır</h3>
                <p id="share-modal-subtitle" class="share-modal-subtitle">Aşağıdaki bağlantı otomatik olarak panoya kopyalandı.</p>
                <div class="share-modal-link-row">
                    <input id="share-modal-link-input" class="share-modal-link-input" type="text" readonly spellcheck="false" aria-label="Paylaşım Linki">
                    <button id="share-modal-copy-btn" type="button" class="share-modal-copy-btn" aria-label="Linki Kopyala" title="Linki Kopyala">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-4 10h6a2 2 0 002-2v-6a2 2 0 00-2-2h-6a2 2 0 00-2 2v6a2 2 0 002 2z"></path></svg>
                    </button>
                </div>
                <div id="share-modal-status" class="share-modal-status" aria-live="polite"></div>
                <div class="share-modal-actions">
                    <button id="share-modal-close-btn" type="button" class="share-modal-close-btn">Kapat</button>
                </div>
            </div>
        </div>

        <!-- DASHBOARD CONTENT -->
        <div id="dashboard-content" class="opacity-30 blur-sm transition-all duration-1000">
            
            <!-- KPI CARDS -->
            <section class="flex flex-col md:flex-row justify-center gap-6 mb-10 w-full max-w-6xl mx-auto">
                <div id="kpi-master" class="glass-panel rounded-3xl p-6 flex-1 min-w-[280px] border-t-4 border-neon-purple relative overflow-hidden group hover:scale-105 transition-transform duration-300"></div>
                <div id="kpi-power" class="glass-panel rounded-3xl p-6 flex-1 min-w-[280px] border-t-4 border-neon-red relative overflow-hidden group hover:scale-105 transition-transform duration-300"></div>
                <div id="kpi-eff" class="glass-panel rounded-3xl p-6 flex-1 min-w-[280px] border-t-4 border-neon-green relative overflow-hidden group hover:scale-105 transition-transform duration-300"></div>
            </section>

            <section id="data-quality-section" data-panel-id="data-quality" data-panel-label="Veri Kalite Ozeti" data-panel-label-key="panelLabel.dataQuality" class="w-full mb-6">
                <div id="data-quality-wrapper" class="glass-panel rounded-2xl p-4 text-xs text-slate-300 relative">
                    <button id="data-quality-close-btn" data-panel-close="data-quality" class="panel-close-btn absolute top-3 right-3" title="Kapat">✕</button>
                    <div class="flex flex-col items-center justify-center gap-2 mb-3 px-10 text-center">
                        <div class="text-center inline-flex items-center justify-center gap-2">
                            <h4 id="data-quality-title" class="text-sm font-bold text-white uppercase">Veri Kalite Özeti</h4>
                            <span id="data-quality-countdown" class="data-quality-countdown hidden" aria-live="polite" aria-atomic="true"></span>
                        </div>
                    </div>
                    <div id="data-quality-summary" class="text-center">
                        Veri kalite özeti dosyalar yüklendiğinde gösterilecektir.
                    </div>
                </div>
            </section>

            <!-- DRAGGABLE GRID -->
            <div id="dashboard-grid" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                
                <!-- 1. STABILITY MATRIX (Main Scatter) -->
                <div id="panel-stability" data-panel-id="stability" data-panel-label="Stabilite ve Performans" data-panel-label-key="panelLabel.stability" class="glass-panel rounded-2xl p-5 overflow-hidden draggable-item col-span-1 md:col-span-2 xl:col-span-2 relative group h-[450px]" draggable="true">
                    <div data-drag-handle class="absolute top-3 right-12 p-1.5 bg-dark-900/80 rounded opacity-0 group-hover:opacity-100 transition-opacity cursor-grab text-slate-400 z-20"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path></svg></div>
                    <button type="button" data-panel-close="stability" class="panel-close-btn absolute top-3 right-3 z-20">✕</button>
                    <div class="flex items-center gap-3 mb-4">
                        <div class="p-2 bg-neon-purple/10 rounded-lg text-neon-purple"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg></div>
                        <div><h3 id="panel-stability-title" class="font-bold text-white text-sm uppercase tracking-wide">Stabilite & Performans</h3><p id="panel-stability-subtitle" class="text-[10px] text-slate-400">Voltaj vs Frekans vs Hata (Chart.js)</p></div>
                    </div>
                    <!-- Chart.js Container -->
                    <div class="relative w-full h-[370px]">
                        <canvas id="mainScatterChart"></canvas>
                    </div>
                </div>

                <!-- 2. ELITE PERFORMANCE -->
                <div id="panel-elite" data-panel-id="elite" data-panel-label="Elit Lig Analizi" data-panel-label-key="panelLabel.elite" class="glass-panel rounded-2xl p-5 draggable-item relative group h-[450px] flex flex-col" draggable="true">
                    <div data-drag-handle class="absolute top-3 right-12 p-1.5 bg-dark-900/80 rounded opacity-0 group-hover:opacity-100 transition-opacity cursor-grab text-slate-400"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path></svg></div>
                    <button type="button" data-panel-close="elite" class="panel-close-btn absolute top-3 right-3">✕</button>
                    <div class="flex items-center gap-3 mb-2">
                        <div class="p-2 bg-purple-600/20 rounded-lg text-purple-400"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M5 3L19 12L5 21V3Z"/></svg></div>
                        <h4 id="panel-elite-title" class="text-sm font-bold text-white uppercase">Elit Lig Analizi</h4>
                    </div>
                    <p id="panel-elite-subtitle" class="text-[10px] text-slate-400 mb-3">En yüksek Hashrate'e sahip ilk 10 sonucun verimlilik ortalaması.</p>
                    <div class="elite-radar-layout">
                        <div class="elite-radar-plot">
                            <svg id="elite-radar-svg" class="elite-radar-svg" role="img" aria-label="Elite Radar"></svg>
                        </div>
                        <div class="elite-radar-meta" id="elite-radar-meta">
                            <div class="elite-radar-row is-alert">
                                <span class="elite-radar-key">MAX ERROR</span>
                                <span class="elite-radar-value" id="elite-max-err">—</span>
                            </div>
                            <div class="elite-radar-row is-warm">
                                <span class="elite-radar-key">MAX VRM</span>
                                <span class="elite-radar-value" id="elite-max-vr">—</span>
                            </div>
                            <div class="elite-radar-row is-warm">
                                <span class="elite-radar-key">MAX ASIC</span>
                                <span class="elite-radar-value" id="elite-max-t">—</span>
                            </div>
                            <div class="elite-radar-row">
                                <span class="elite-radar-key">MAX HASH</span>
                                <span class="elite-radar-value" id="elite-max-h">—</span>
                            </div>
                            <div class="elite-radar-row">
                                <span class="elite-radar-key">MAX MV</span>
                                <span class="elite-radar-value" id="elite-max-v">—</span>
                            </div>
                            <div class="elite-radar-row">
                                <span class="elite-radar-key">MAX MHZ</span>
                                <span class="elite-radar-value" id="elite-max-f">—</span>
                            </div>
                            <div class="elite-radar-row">
                                <span class="elite-radar-key">MAX J/TH</span>
                                <span class="elite-radar-value" id="elite-max-e">—</span>
                            </div>
                            <div class="elite-radar-row">
                                <span class="elite-radar-key">MAX WATT</span>
                                <span class="elite-radar-value" id="elite-max-p">—</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. AATE CHECK -->
                <div id="panel-aate" data-panel-id="aate" data-panel-label="AATE Referans Kontrolu" data-panel-label-key="panelLabel.aate" class="glass-panel rounded-2xl p-5 draggable-item relative group h-[330px] flex flex-col" draggable="true">
                    <div data-drag-handle class="absolute top-3 right-12 p-1.5 bg-dark-900/80 rounded opacity-0 group-hover:opacity-100 transition-opacity cursor-grab text-slate-400"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path></svg></div>
                    <button type="button" data-panel-close="aate" class="panel-close-btn absolute top-3 right-3">✕</button>
                    <div class="flex items-center gap-3 mb-3 flex-shrink-0">
                        <div class="p-2 bg-neon-amber/10 rounded-lg text-neon-amber"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div>
                        <div><h4 id="panel-aate-title" class="text-sm font-bold text-white uppercase">AATE Referans Kontrolü</h4><p id="panel-aate-subtitle" class="text-[10px] text-slate-400">Master vs Golden Config</p></div>
                    </div>
                    <div class="flex-grow overflow-y-auto custom-scroll pr-1">
                        <table class="w-full text-left text-[11px]">
                            <thead><tr class="text-slate-500 border-b border-dark-700"><th id="aate-col-param" class="pb-2 font-normal">Parametre</th><th id="aate-col-ref" class="pb-2 font-normal text-right">Referans</th><th id="aate-col-your" class="pb-2 font-normal text-right">Sizin</th><th id="aate-col-status" class="pb-2 font-normal text-center">Durum</th></tr></thead>
                            <tbody class="text-slate-300 divide-y divide-dark-700/50" id="aate-table-body"></tbody>
                        </table>
                    </div>
                </div>

                <!-- 4. POWER CHART -->
                <div id="panel-power" data-panel-id="power" data-panel-label="Guc Tuketimi" data-panel-label-key="panelLabel.power" class="glass-panel rounded-2xl p-5 draggable-item relative group h-[330px]" draggable="true">
                    <div data-drag-handle class="absolute top-3 right-12 p-1.5 bg-dark-900/80 rounded opacity-0 group-hover:opacity-100 transition-opacity cursor-grab text-slate-400"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path></svg></div>
                    <button type="button" data-panel-close="power" class="panel-close-btn absolute top-3 right-3">✕</button>
                    <div class="flex items-center gap-3 mb-4"><div class="p-2 bg-neon-green/10 rounded-lg text-neon-green"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg></div><h4 id="panel-power-title" class="text-sm font-bold text-white uppercase">Güç Tüketimi (Watt)</h4></div>
                    <div class="relative h-[250px] w-full"><canvas id="powerChart"></canvas></div>
                </div>

                <!-- 5. EFFICIENCY CHART -->
                <div id="panel-eff" data-panel-id="efficiency" data-panel-label="Verimlilik" data-panel-label-key="panelLabel.efficiency" class="glass-panel rounded-2xl p-5 draggable-item relative group h-[330px]" draggable="true">
                    <div data-drag-handle class="absolute top-3 right-12 p-1.5 bg-dark-900/80 rounded opacity-0 group-hover:opacity-100 transition-opacity cursor-grab text-slate-400"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path></svg></div>
                    <button type="button" data-panel-close="efficiency" class="panel-close-btn absolute top-3 right-3">✕</button>
                    <div class="flex items-center gap-3 mb-4"><div class="p-2 bg-neon-blue/10 rounded-lg text-neon-blue"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg></div><h4 id="panel-eff-title" class="text-sm font-bold text-white uppercase">Verimlilik (J/TH)</h4></div>
                    <div class="relative h-[250px] w-full"><canvas id="effChart"></canvas></div>
                </div>

                <!-- 6. THERMAL CHART -->
                <div id="panel-temp" data-panel-id="temperature" data-panel-label="VRM Sicakligi" data-panel-label-key="panelLabel.temperature" class="glass-panel rounded-2xl p-5 draggable-item relative group h-[330px]" draggable="true">
                    <div data-drag-handle class="absolute top-3 right-12 p-1.5 bg-dark-900/80 rounded opacity-0 group-hover:opacity-100 transition-opacity cursor-grab text-slate-400"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path></svg></div>
                    <button type="button" data-panel-close="temperature" class="panel-close-btn absolute top-3 right-3">✕</button>
                    <div class="flex items-center gap-3 mb-1"><div class="p-2 bg-neon-red/10 rounded-lg text-neon-red"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"></path></svg></div><h4 id="panel-temp-title" class="text-sm font-bold text-white uppercase">VRM Sıcaklığı</h4></div>
                    <div class="relative h-[250px] w-full"><canvas id="tempChart"></canvas></div>
                </div>

                <!-- 7. FREQUENCY BAR -->
                <div id="panel-freq" data-panel-id="frequency" data-panel-label="Frekans Basarimi" data-panel-label-key="panelLabel.frequency" class="glass-panel rounded-2xl p-5 draggable-item relative group h-[330px]" draggable="true">
                    <div data-drag-handle class="absolute top-3 right-12 p-1.5 bg-dark-900/80 rounded opacity-0 group-hover:opacity-100 transition-opacity cursor-grab text-slate-400"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path></svg></div>
                    <button type="button" data-panel-close="frequency" class="panel-close-btn absolute top-3 right-3">✕</button>
                    <div class="flex items-center gap-3 mb-4"><div class="p-2 bg-neon-amber/10 rounded-lg text-neon-amber"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg></div><h4 id="panel-freq-title" class="text-sm font-bold text-white uppercase">Frekans Başarımı</h4></div>
                    <div class="relative h-[250px] w-full"><canvas id="freqBarChart"></canvas></div>
                </div>

                <!-- 8. V-F HEATMAP -->
                <div id="panel-vf" data-panel-id="vf-heatmap" data-panel-label="V-F Heatmap" data-panel-label-key="panelLabel.vf" class="glass-panel rounded-2xl p-5 draggable-item relative group h-[330px]" draggable="true">
                    <div data-drag-handle class="absolute top-3 right-12 p-1.5 bg-dark-900/80 rounded opacity-0 group-hover:opacity-100 transition-opacity cursor-grab text-slate-400"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path></svg></div>
                    <button type="button" data-panel-close="vf-heatmap" class="panel-close-btn absolute top-3 right-3">✕</button>
                    <div class="flex items-center gap-3 mb-4"><div class="p-2 bg-neon-amber/10 rounded-lg text-neon-amber"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-6m3 6v-4m3 4V7M4 5h16"></path></svg></div><h4 id="panel-vf-title" class="text-sm font-bold text-white uppercase">V-F Heatmap</h4></div>
                    <div class="relative h-[250px] w-full"><canvas id="vfHeatmapChart"></canvas></div>
                </div>
                <!-- 9. DATA TABLE -->
                <div id="panel-table" data-panel-id="table" data-panel-label="Veri Madencisi Tablosu" data-panel-label-key="panelLabel.table" class="glass-panel rounded-2xl overflow-hidden col-span-1 md:col-span-2 xl:col-span-3 relative group">
                    <button type="button" data-panel-close="table" class="panel-close-btn absolute top-3 right-3 z-20">✕</button>
                    <div id="panel-table-toolbar" class="p-5 border-b border-dark-700 bg-dark-800/40 flex flex-wrap gap-4 justify-between items-end">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-slate-700/50 rounded-lg text-white"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg></div>
                            <h3 id="panel-table-title" class="text-sm font-bold text-white uppercase tracking-wider">Veri Madencisi</h3>
                        </div>
                        <div id="table-toolbar-controls" class="flex gap-2 flex-wrap justify-end pr-10">
                            <div id="quick-sort-controls" class="flex gap-2">
                                <button id="quick-sort-score-btn" type="button" class="quick-sort-btn" data-quick-sort="score">Puan (Yüksekten Düşüğe) <span class="quick-sort-icon">↓</span></button>
                                <button id="quick-sort-hash-btn" type="button" class="quick-sort-btn" data-quick-sort="h">Hash (Yüksekten Düşüğe) <span class="quick-sort-icon">↓</span></button>
                            </div>
                            <button id="reset-filters-btn" type="button" class="px-3 bg-dark-700 hover:bg-dark-600 rounded text-[10px] text-white uppercase tracking-wider">Sıfırla</button>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="miner-data-table w-full text-xs text-left text-slate-300">
                            <colgroup>
                                <col class="col-source">
                                <col class="col-main">
                                <col class="col-main">
                                <col class="col-main">
                                <col class="col-main">
                                <col class="col-main">
                                <col class="col-main">
                                <col class="col-jth">
                                <col class="col-score">
                            </colgroup>
                            <thead id="tableHead" class="uppercase bg-dark-950 text-slate-500 sticky top-0">
                                <tr>
                                    <th class="px-4 py-3 text-center"><button type="button" class="table-sort-btn" data-sort-key="source">Kaynak <span class="table-sort-indicator">↕</span></button></th>
                                    <th class="px-6 py-3"><button type="button" class="table-sort-btn" data-sort-key="h">Hashrate <span class="table-sort-indicator">↕</span></button></th>
                                    <th class="px-6 py-3"><button type="button" class="table-sort-btn" data-sort-key="v">Voltaj <span class="table-sort-indicator">↕</span></button></th>
                                    <th class="px-6 py-3"><button type="button" class="table-sort-btn" data-sort-key="f">Frekans <span class="table-sort-indicator">↕</span></button></th>
                                    <th class="px-6 py-3"><button type="button" class="table-sort-btn" data-sort-key="vr">VRM <span class="table-sort-indicator">↕</span></button></th>
                                    <th class="px-6 py-3"><button type="button" class="table-sort-btn" data-sort-key="t">ASIC <span class="table-sort-indicator">↕</span></button></th>
                                    <th class="px-6 py-3"><button type="button" class="table-sort-btn" data-sort-key="err">Hata % <span class="table-sort-indicator">↕</span></button></th>
                                    <th class="px-4 py-3 text-center"><button type="button" class="table-sort-btn" data-sort-key="e">J/TH <span class="table-sort-indicator">↕</span></button></th>
                                    <th class="px-4 py-3 text-center"><button type="button" class="table-sort-btn" data-sort-key="score">Puan <span class="table-sort-indicator">↕</span></button></th>
                                </tr>
                                <tr class="table-filter-row">
                                    <th class="px-4 py-3"></th>
                                    <th class="px-4 py-3">
                                        <div class="miner-filter-control">
                                            <input type="number" id="f-h-min" class="miner-filter-input" placeholder="Min Hash">
                                            <input type="range" id="f-h-min-range" class="miner-filter-range" min="0" max="1" step="0.1" value="0" aria-label="Min Hash">
                                        </div>
                                    </th>
                                    <th class="px-4 py-3">
                                        <div class="miner-filter-control">
                                            <input type="number" id="f-v-min" class="miner-filter-input" placeholder="Min Voltaj">
                                            <input type="range" id="f-v-min-range" class="miner-filter-range" min="0" max="1" step="0.1" value="0" aria-label="Min Voltaj">
                                        </div>
                                    </th>
                                    <th class="px-4 py-3">
                                        <div class="miner-filter-control">
                                            <input type="number" id="f-f-min" class="miner-filter-input" placeholder="Min Frekans">
                                            <input type="range" id="f-f-min-range" class="miner-filter-range" min="0" max="1" step="0.1" value="0" aria-label="Min Frekans">
                                        </div>
                                    </th>
                                    <th class="px-4 py-3">
                                        <div class="miner-filter-control">
                                            <input type="number" id="f-vr-max" class="miner-filter-input" placeholder="Max VRM Sıcaklık °C">
                                            <input type="range" id="f-vr-max-range" class="miner-filter-range" min="0" max="1" step="0.1" value="1" aria-label="Max VRM Sıcaklık °C">
                                        </div>
                                    </th>
                                    <th class="px-4 py-3">
                                        <div class="miner-filter-control">
                                            <input type="number" id="f-t-max" class="miner-filter-input" placeholder="Max ASIC Sıcaklık °C">
                                            <input type="range" id="f-t-max-range" class="miner-filter-range" min="0" max="1" step="0.1" value="1" aria-label="Max ASIC Sıcaklık °C">
                                        </div>
                                    </th>
                                    <th class="px-4 py-3">
                                        <div class="miner-filter-control">
                                            <input type="number" id="f-e-max" class="miner-filter-input" placeholder="Max Hata %">
                                            <input type="range" id="f-e-max-range" class="miner-filter-range" min="0" max="1" step="0.1" value="1" aria-label="Max Hata %">
                                        </div>
                                    </th>
                                    <th class="px-6 py-3"></th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody id="tableBody" class="divide-y divide-dark-700"></tbody>
                        </table>
                    </div>
                    <div class="p-3 text-center border-t border-dark-700 bg-dark-800/30">
                        <button id="loadMoreBtn" class="text-neon-purple hover:text-white text-[10px] font-bold uppercase tracking-widest transition">Daha Fazla Göster ↓</button>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <script>
        function applyChartDefaults() {
            if (!window.Chart || !window.Chart.defaults) return;
            window.Chart.defaults.color = '#94a3b8';
            window.Chart.defaults.borderColor = '#22324a';
            window.Chart.defaults.font.family = "'Inter', sans-serif";
        }

        applyChartDefaults();

        // --------------------------
        // Runtime state
        // --------------------------
        let rawFilesData = [];
        let consolidatedData = [];
        let visibleRows = 15;
        let pendingFileReads = 0;
        let appendUploadMode = false;
        let tableSort = { key: 'score', dir: 'desc' };
        let panelVisibility = {};
        let chartInstances = {};
        let chartRenderJobToken = 0;
        let chartRenderFrameId = 0;
        let chartLibrariesLoadPromise = null;
        let html2canvasLoadPromise = null;
        let chartAnnotationRegistered = false;
        let chartWarmupScheduled = false;
        let draggedItem = null;
        let isJpegExporting = false;
        let dataQualityAutoCloseTimer = null;
        let dataQualityFadeOutTimer = null;
        let dataQualityCountdownInterval = null;
        let dataQualityCountdownEndsAt = 0;
        let dataQualityPinnedByUser = false;
        let isSampleCsvLoading = false;
        let tableRenderRafId = 0;
        let tableRenderDebounceTimer = 0;
        let resizeRafId = 0;
        let isShareBusy = false;
        let isReadOnlyShareView = false;
        let keepUploadOverlayClosedOnBoot = false;
        let mobileHeaderMenuOpen = false;
        let eliteRadarTooltipEl = null;
        let lastUploadAttemptStats = null;

        // --------------------------
        // App constants
        // --------------------------
        const APP_VERSION = <?= json_encode($appVersion, $jsonJsFlags) ?>;
        const APP_BASE_PATH = <?= json_encode($assetBasePath, $jsonJsFlags) ?>;
        const pathWithBase = (path) => `${APP_BASE_PATH}${path}`;
        const BRAND_VERSION_SUFFIX = 'CHART.JS';
        const DEFAULT_VISIBLE_ROWS = 15;
        const DATA_QUALITY_AUTO_CLOSE_DELAY_MS = 10000;
        const UPLOAD_OUTSIDE_CLOSE_MARGIN_RATIO = 0.05;
        const JPEG_TARGET_MAX_BYTES = 1024 * 1024;
        const MAX_UPLOAD_FILES_PER_BATCH = <?= json_encode($maxUploadFilesPerBatch, $jsonJsFlags) ?>;
        const MAX_CSV_FILE_BYTES = <?= json_encode($maxCsvFileBytes, $jsonJsFlags) ?>;
        const MAX_UPLOAD_TOTAL_BYTES = <?= json_encode($maxUploadTotalBytes, $jsonJsFlags) ?>;
        const CSV_MAX_DATA_ROWS = <?= json_encode($csvMaxDataRows, $jsonJsFlags) ?>;
        const CSV_PARSE_TIME_BUDGET_MS = <?= json_encode($csvParseTimeBudgetMs, $jsonJsFlags) ?>;
        const TABLE_RENDER_DEBOUNCE_MS = 50;
        const SAMPLE_CSV_PATH = pathWithBase('/assets/samples/bitaxe_demo_sample_v1.csv');
        const SHARE_CREATE_API_PATH = pathWithBase('/api/share.php?action=create');
        const SHARE_GET_API_PATH = pathWithBase('/api/share.php');
        const IMPORT_CONSUME_API_PATH = pathWithBase('/api/autotune/consume.php');
        const USAGE_LOG_API_PATH = pathWithBase('/api/share.php?action=usage-log');
        const USAGE_LOG_FETCH_TIMEOUT_MS = 8000;
        const SHARE_MAX_BODY_BYTES = 1200 * 1024;
        const SHARE_FETCH_TIMEOUT_MS = 15000;
        const SHARE_TOKEN_PATTERN = /^[a-f0-9]{16,80}$/;
        const IMPORT_TOKEN_PATTERN = /^[a-f0-9]{16,80}$/;
        const IMPORT_CACHE_TTL_MS = 12 * 60 * 60 * 1000;
        const SHARE_STATIC_TEST_TOKEN = 'test';
        const IMPORT_TOKEN_FROM_SERVER = <?= json_encode($importToken, $jsonJsFlags) ?>;
        const SHARE_LAYOUT_ALLOWED_PANELS = ['stability', 'elite', 'aate', 'power', 'efficiency', 'temperature', 'frequency', 'vf-heatmap', 'table'];
        const CHART_STAGED_RENDER_ROW_THRESHOLD = 350;
        const CHART_PERF_MODE_ROW_THRESHOLD = 950;
        const CHART_POINT_LIMIT_NORMAL = 1600;
        const CHART_POINT_LIMIT_PERF = 900;
        const CHART_POINT_LIMIT_SHARE = 700;
        const CHART_JS_PINNED_CDN_URL = 'https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js';
        const CHART_ANNOTATION_PINNED_CDN_URL = 'https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@2.1.0/dist/chartjs-plugin-annotation.min.js';
        const CHART_MAIN_LIB_SOURCES = [
            `${pathWithBase('/assets/vendor/chart.umd.min.js')}?v=${encodeURIComponent(APP_VERSION)}`,
            CHART_JS_PINNED_CDN_URL,
            'https://cdn.jsdelivr.net/npm/chart.js'
        ];
        const CHART_ANNOTATION_LIB_SOURCES = [
            `${pathWithBase('/assets/vendor/chartjs-plugin-annotation.min.js')}?v=${encodeURIComponent(APP_VERSION)}`,
            CHART_ANNOTATION_PINNED_CDN_URL,
            'https://cdnjs.cloudflare.com/ajax/libs/chartjs-plugin-annotation/2.1.0/chartjs-plugin-annotation.min.js'
        ];
        const HTML2CANVAS_LIB_SOURCES = [
            `${pathWithBase('/assets/vendor/html2canvas.min.js')}?v=${encodeURIComponent(APP_VERSION)}`,
            'https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js'
        ];

        const FILTER_IDS = ['f-v-min', 'f-f-min', 'f-h-min', 'f-e-max', 'f-vr-max', 'f-t-max'];
        const FILTER_REF_BY_ID = {
            'f-v-min': 'filterVMin',
            'f-f-min': 'filterFMin',
            'f-h-min': 'filterHMin',
            'f-e-max': 'filterEMax',
            'f-vr-max': 'filterVrMax',
            'f-t-max': 'filterTMax'
        };
        const FILTER_RANGE_REF_BY_ID = {
            'f-v-min': 'filterVMinRange',
            'f-f-min': 'filterFMinRange',
            'f-h-min': 'filterHMinRange',
            'f-e-max': 'filterEMaxRange',
            'f-vr-max': 'filterVrMaxRange',
            'f-t-max': 'filterTMaxRange'
        };
        const FILTER_CONTROL_CONFIG = [
            { id: 'f-v-min', rangeId: 'f-v-min-range', field: 'v', mode: 'min' },
            { id: 'f-f-min', rangeId: 'f-f-min-range', field: 'f', mode: 'min' },
            { id: 'f-h-min', rangeId: 'f-h-min-range', field: 'h', mode: 'min' },
            { id: 'f-e-max', rangeId: 'f-e-max-range', field: 'err', mode: 'max' },
            { id: 'f-vr-max', rangeId: 'f-vr-max-range', field: 'vr', mode: 'max' },
            { id: 'f-t-max', rangeId: 'f-t-max-range', field: 't', mode: 'max' }
        ];
        const LANGUAGE_STORAGE_KEY = 'bitaxe_ui_lang';
        const THEME_STORAGE_KEY = 'bitaxeThemePreference';
        const THEME_VARIANT_STORAGE_KEY = 'bitaxeThemeVariantPreference';
        const THEME_DARK = 'dark';
        const THEME_LIGHT = 'light';
        const THEME_VARIANT_PURPLE = 'purple';
        const THEME_VARIANT_ORANGE = 'orange';
        const THEME_TRANSITION_CLASS = 'theme-transition-active';
        const THEME_TRANSITION_DURATION_MS = 380;
        const EMBEDDED_STATE_NODE_ID = 'bitaxe-embedded-export-state';
        const THEME_PALETTES = {
            [THEME_VARIANT_PURPLE]: {
                accent: '#d946ef',
                accentRgb: '217,70,239',
                meta: {
                    [THEME_DARK]: '#070b14',
                    [THEME_LIGHT]: '#f1f5f9'
                },
                kpi: { accent: '#d946ef', bg: 'rgba(217,70,239,0.15)' },
                frequency: {
                    barStart: 'rgba(168,85,247,0.48)',
                    barEnd: 'rgba(91,33,182,0.14)',
                    barBorder: 'rgba(216,180,254,0.42)',
                    glow: 'rgba(217,70,239,0.34)',
                    line: '#e879f9',
                    point: '#f472b6',
                    areaStart: 'rgba(217,70,239,0.07)',
                    areaFallback: 'rgba(217,70,239,0.18)',
                    areaEnd: 'rgba(244,114,182,0.28)',
                    grid: 'rgba(217,70,239,0.14)'
                }
            },
            [THEME_VARIANT_ORANGE]: {
                accent: '#f97316',
                accentRgb: '249,115,22',
                meta: {
                    [THEME_DARK]: '#070b14',
                    [THEME_LIGHT]: '#fff7ed'
                },
                kpi: { accent: '#f97316', bg: 'rgba(249,115,22,0.16)' },
                frequency: {
                    barStart: 'rgba(251,146,60,0.48)',
                    barEnd: 'rgba(194,65,12,0.14)',
                    barBorder: 'rgba(254,215,170,0.42)',
                    glow: 'rgba(249,115,22,0.34)',
                    line: '#fb923c',
                    point: '#fdba74',
                    areaStart: 'rgba(249,115,22,0.07)',
                    areaFallback: 'rgba(249,115,22,0.18)',
                    areaEnd: 'rgba(251,191,36,0.28)',
                    grid: 'rgba(249,115,22,0.14)'
                }
            }
        };
        const DEFAULT_LANGUAGE_CODE = 'en';
        const SUPPORTED_LANGUAGE_CODES = ['tr', 'en', 'zh-CN', 'hi', 'es', 'fr', 'ar', 'bn', 'pt', 'ru', 'ur', 'asm'];
        const ELITE_RADAR_METRICS = [
            { key: 'h', axis: 'HASH', cap: 5000, digits: 0, unit: 'GH', targetId: 'elite-max-h' },
            { key: 'v', axis: 'MV', cap: 1600, digits: 0, unit: 'MV', targetId: 'elite-max-v' },
            { key: 'f', axis: 'MHZ', cap: 1300, digits: 2, unit: 'MHZ', targetId: 'elite-max-f' },
            { key: 't', axis: 'ASIC', cap: 95, digits: 0, unit: 'C', targetId: 'elite-max-t' },
            { key: 'vr', axis: 'VRM', cap: 110, digits: 0, unit: 'C', targetId: 'elite-max-vr' },
            { key: 'p', axis: 'W', cap: 120, digits: 1, unit: 'W', targetId: 'elite-max-p' },
            { key: 'e', axis: 'JTH', cap: 25, digits: 2, unit: 'J/TH', targetId: 'elite-max-e' },
            { key: 'err', axis: 'ERROR', cap: 5, digits: 2, unit: '%', targetId: 'elite-max-err' }
        ];
        const ELITE_RADAR_META_ORDER = ['err', 'vr', 't', 'h', 'v', 'f', 'e', 'p'];
        const ELITE_RADAR_METRIC_BY_KEY = ELITE_RADAR_METRICS.reduce((acc, metric) => {
            acc[metric.key] = metric;
            return acc;
        }, {});
        const EMBEDDED_EXPORT_STATE = (() => {
            if (window.__EXPORT_STATE__ && typeof window.__EXPORT_STATE__ === 'object') {
                return window.__EXPORT_STATE__;
            }
            const node = document.getElementById(EMBEDDED_STATE_NODE_ID);
            if (!node) return null;
            try {
                const parsed = JSON.parse(String(node.textContent || 'null'));
                return (parsed && typeof parsed === 'object') ? parsed : null;
            } catch (_) {
                return null;
            }
        })();
        const EMBEDDED_STATE_MODE = (
            window.__STATE_MODE__ ||
            (document.getElementById(EMBEDDED_STATE_NODE_ID) ? 'snapshot' : (window.__EXPORT_MODE__ ? 'snapshot' : null))
        );
        const IS_EMBEDDED_STATE = Boolean(EMBEDDED_EXPORT_STATE);
        const IS_SNAPSHOT_VIEW = IS_EMBEDDED_STATE && EMBEDDED_STATE_MODE === 'snapshot';
        const SNAPSHOT_THEME = (() => {
            if (!IS_SNAPSHOT_VIEW) return null;
            const theme = String(EMBEDDED_EXPORT_STATE?.meta?.selectedTheme || '').toLowerCase();
            return (theme === THEME_LIGHT || theme === THEME_DARK) ? theme : null;
        })();
        const SNAPSHOT_THEME_VARIANT = (() => {
            if (!IS_SNAPSHOT_VIEW) return null;
            const variant = String(EMBEDDED_EXPORT_STATE?.meta?.selectedThemeVariant || '').toLowerCase();
            return (variant === THEME_VARIANT_ORANGE || variant === THEME_VARIANT_PURPLE) ? variant : null;
        })();
        const IS_WEBDRIVER_SESSION = Boolean(
            typeof navigator !== 'undefined' &&
            navigator &&
            navigator.webdriver
        );

        // Cache DOM references once to avoid repeated selector cost/noise.
        const refs = {
            grid: document.getElementById('dashboard-grid'),
            dropZone: document.getElementById('drop-zone'),
            fileInput: document.getElementById('fileInput'),
            uploadSection: document.getElementById('upload-section'),
            uploadModal: document.getElementById('upload-modal'),
            dashboardContent: document.getElementById('dashboard-content'),
            fileListWrapper: document.getElementById('file-list-wrapper'),
            fileList: document.getElementById('file-list'),
            fileCountLabel: document.getElementById('file-count-label'),
            processBtn: document.getElementById('process-btn'),
            samplePreviewRow: document.getElementById('sample-preview-row'),
            samplePreviewBtn: document.getElementById('sample-preview-btn'),
            toastStack: document.getElementById('app-toast-stack'),
            shareModalOverlay: document.getElementById('share-modal-overlay'),
            shareModalCard: document.getElementById('share-modal-card'),
            shareModalLinkInput: document.getElementById('share-modal-link-input'),
            shareModalCopyBtn: document.getElementById('share-modal-copy-btn'),
            shareModalStatus: document.getElementById('share-modal-status'),
            shareModalCloseBtn: document.getElementById('share-modal-close-btn'),
            showUploadBtn: document.getElementById('show-upload-btn'),
            shareBtn: document.getElementById('share-btn'),
            closeUploadBtn: document.getElementById('close-upload-btn'),
            exportHtmlBtn: document.getElementById('export-html-btn'),
            exportJpegBtn: document.getElementById('export-jpeg-btn'),
            viewMenuBtn: document.getElementById('view-menu-btn'),
            viewMenuDropdown: document.getElementById('view-menu-dropdown'),
            viewMenuList: document.getElementById('view-menu-list'),
            viewShowAllBtn: document.getElementById('view-show-all-btn'),
            appHeader: document.getElementById('app-header'),
            headerShell: document.getElementById('header-shell'),
            mobileHeaderMenuBtn: document.getElementById('mobile-header-menu-btn'),
            mobileHeaderMenuPanel: document.getElementById('mobile-header-menu-panel'),
            languageControl: document.getElementById('language-control'),
            languageSelector: document.getElementById('language-selector'),
            languagePicker: document.getElementById('language-picker'),
            languageToggleBtn: document.getElementById('language-toggle-btn'),
            variantToggleBtn: document.getElementById('variant-toggle-btn'),
            themeToggleBtn: document.getElementById('theme-toggle-btn'),
            languageMenu: document.getElementById('language-menu'),
            languageCurrentLabel: document.getElementById('language-current-label'),
            dataQualitySection: document.getElementById('data-quality-section'),
            dataQualityCloseBtn: document.getElementById('data-quality-close-btn'),
            dataQualitySummary: document.getElementById('data-quality-summary'),
            dataQualityCountdown: document.getElementById('data-quality-countdown'),
            quickSortControls: document.getElementById('quick-sort-controls'),
            resetFiltersBtn: document.getElementById('reset-filters-btn'),
            tableHead: document.getElementById('tableHead'),
            loadMoreBtn: document.getElementById('loadMoreBtn'),
            tableBody: document.getElementById('tableBody'),
            filterVMin: document.getElementById('f-v-min'),
            filterFMin: document.getElementById('f-f-min'),
            filterHMin: document.getElementById('f-h-min'),
            filterEMax: document.getElementById('f-e-max'),
            filterVrMax: document.getElementById('f-vr-max'),
            filterTMax: document.getElementById('f-t-max'),
            filterVMinRange: document.getElementById('f-v-min-range'),
            filterFMinRange: document.getElementById('f-f-min-range'),
            filterHMinRange: document.getElementById('f-h-min-range'),
            filterEMaxRange: document.getElementById('f-e-max-range'),
            filterVrMaxRange: document.getElementById('f-vr-max-range'),
            filterTMaxRange: document.getElementById('f-t-max-range')
        };

        function setHidden(el, hidden) {
            if (!el) return;
            el.classList.toggle('hidden', Boolean(hidden));
        }

        function appendExternalScript(src, timeoutMs = 12000) {
            return new Promise((resolve, reject) => {
                const targetSrc = String(src || '').trim();
                if (!targetSrc) {
                    reject(new Error('empty_script_src'));
                    return;
                }

                const cleanTargetSrc = targetSrc.split('?')[0];
                const existing = Array.from(document.querySelectorAll('script[src]')).find((node) => (
                    String(node.getAttribute('src') || '').split('?')[0] === cleanTargetSrc
                ));

                if (existing) {
                    const readyState = String(existing.dataset.loadState || '');
                    if (readyState === 'ready') {
                        resolve();
                        return;
                    }
                    const onLoad = () => {
                        cleanup();
                        existing.dataset.loadState = 'ready';
                        resolve();
                    };
                    const onError = () => {
                        cleanup();
                        existing.dataset.loadState = 'error';
                        reject(new Error('script_load_failed'));
                    };
                    const timeoutId = window.setTimeout(() => {
                        cleanup();
                        reject(new Error('script_load_timeout'));
                    }, Math.max(3000, timeoutMs));
                    const cleanup = () => {
                        existing.removeEventListener('load', onLoad);
                        existing.removeEventListener('error', onError);
                        window.clearTimeout(timeoutId);
                    };
                    existing.addEventListener('load', onLoad, { once: true });
                    existing.addEventListener('error', onError, { once: true });
                    return;
                }

                const script = document.createElement('script');
                script.src = targetSrc;
                script.async = true;
                script.defer = true;
                script.crossOrigin = 'anonymous';
                script.referrerPolicy = 'no-referrer';
                script.dataset.loadState = 'pending';

                const cleanup = () => {
                    script.onload = null;
                    script.onerror = null;
                    window.clearTimeout(timeoutId);
                };

                const timeoutId = window.setTimeout(() => {
                    cleanup();
                    script.dataset.loadState = 'error';
                    script.remove();
                    reject(new Error('script_load_timeout'));
                }, Math.max(3000, timeoutMs));

                script.onload = () => {
                    cleanup();
                    script.dataset.loadState = 'ready';
                    resolve();
                };

                script.onerror = () => {
                    cleanup();
                    script.dataset.loadState = 'error';
                    script.remove();
                    reject(new Error('script_load_failed'));
                };

                document.head.appendChild(script);
            });
        }

        async function loadScriptWithFallback(sources, isReady) {
            if (typeof isReady === 'function' && isReady()) return true;
            const list = Array.from(new Set(
                (Array.isArray(sources) ? sources : [sources])
                    .map((src) => String(src || '').trim())
                    .filter((src) => src !== '')
            ));
            for (let i = 0; i < list.length; i += 1) {
                try {
                    await appendExternalScript(list[i]);
                    if (typeof isReady !== 'function' || isReady()) return true;
                } catch (_) {
                    // Try next fallback source.
                }
            }
            return typeof isReady === 'function' ? Boolean(isReady()) : false;
        }

        async function fetchTextWithTimeout(url, timeoutMs = 12000) {
            const controller = new AbortController();
            const timer = window.setTimeout(() => controller.abort(), Math.max(2500, timeoutMs));
            try {
                const response = await fetch(url, {
                    method: 'GET',
                    credentials: 'omit',
                    cache: 'no-store',
                    signal: controller.signal
                });
                if (!response.ok) throw new Error(`http_${response.status}`);
                return await response.text();
            } finally {
                window.clearTimeout(timer);
            }
        }

        async function fetchTextWithFallback(sources, timeoutMs = 12000) {
            const list = Array.from(new Set(
                (Array.isArray(sources) ? sources : [sources])
                    .map((src) => String(src || '').trim())
                    .filter((src) => src !== '')
            ));
            for (let i = 0; i < list.length; i += 1) {
                try {
                    const text = await fetchTextWithTimeout(list[i], timeoutMs);
                    if (text && text.trim()) return text;
                } catch (_) {
                    // Try next source.
                }
            }
            return '';
        }

        function registerChartAnnotationPlugin() {
            if (chartAnnotationRegistered) return;
            if (typeof window.Chart !== 'function') return;
            const plugin = window['chartjs-plugin-annotation'];
            if (!plugin) return;
            try {
                window.Chart.register(plugin);
                chartAnnotationRegistered = true;
            } catch (_) {
                chartAnnotationRegistered = true;
            }
        }

        async function ensureChartLibrariesLoaded() {
            if (typeof window.Chart === 'function') {
                applyChartDefaults();
                registerChartAnnotationPlugin();
                if (window['chartjs-plugin-annotation']) return true;
            }

            if (!chartLibrariesLoadPromise) {
                chartLibrariesLoadPromise = (async () => {
                    const chartReady = await loadScriptWithFallback(
                        CHART_MAIN_LIB_SOURCES,
                        () => (typeof window.Chart === 'function')
                    );
                    if (!chartReady) return false;
                    applyChartDefaults();

                    await loadScriptWithFallback(
                        CHART_ANNOTATION_LIB_SOURCES,
                        () => Boolean(window['chartjs-plugin-annotation'])
                    );
                    registerChartAnnotationPlugin();
                    return (typeof window.Chart === 'function');
                })();
            }

            try {
                const loaded = await chartLibrariesLoadPromise;
                if (!loaded) chartLibrariesLoadPromise = null;
                return Boolean(loaded);
            } catch (_) {
                chartLibrariesLoadPromise = null;
                return false;
            }
        }

        function scheduleChartLibrariesWarmup() {
            if (chartWarmupScheduled) return;
            chartWarmupScheduled = true;

            const warmup = () => {
                ensureChartLibrariesLoaded().catch(() => {});
            };

            const runWhenIdle = () => {
                if (typeof window.requestIdleCallback === 'function') {
                    window.requestIdleCallback(() => warmup(), { timeout: 2000 });
                } else {
                    window.setTimeout(warmup, 320);
                }
            };

            const scheduleAfterFirstPaint = () => {
                window.requestAnimationFrame(() => {
                    window.requestAnimationFrame(runWhenIdle);
                });
            };

            if (document.readyState === 'complete') {
                scheduleAfterFirstPaint();
                return;
            }

            window.addEventListener('load', scheduleAfterFirstPaint, { once: true });
        }

        async function ensureHtml2canvasLoaded() {
            if (typeof window.html2canvas === 'function') return true;
            if (!html2canvasLoadPromise) {
                html2canvasLoadPromise = loadScriptWithFallback(
                    HTML2CANVAS_LIB_SOURCES,
                    () => (typeof window.html2canvas === 'function')
                );
            }
            try {
                const loaded = await html2canvasLoadPromise;
                if (!loaded) html2canvasLoadPromise = null;
                return Boolean(loaded);
            } catch (_) {
                html2canvasLoadPromise = null;
                return false;
            }
        }

        function cancelScheduledChartRender() {
            chartRenderJobToken += 1;
            if (chartRenderFrameId) {
                window.cancelAnimationFrame(chartRenderFrameId);
                chartRenderFrameId = 0;
            }
        }

        function clearChartInstances() {
            Object.values(chartInstances).forEach((chart) => {
                if (chart && typeof chart.destroy === 'function') chart.destroy();
            });
            chartInstances = {};
        }

        function isChartPerfModeEnabled() {
            return isReadOnlyShareView || consolidatedData.length >= CHART_PERF_MODE_ROW_THRESHOLD;
        }

        function getChartPointLimit() {
            if (isReadOnlyShareView) return CHART_POINT_LIMIT_SHARE;
            if (isChartPerfModeEnabled()) return CHART_POINT_LIMIT_PERF;
            return CHART_POINT_LIMIT_NORMAL;
        }

        function downsampleRows(rows, maxPoints) {
            const list = Array.isArray(rows) ? rows : [];
            const limit = Number.isFinite(maxPoints) ? Math.max(1, Math.floor(maxPoints)) : 0;
            if (!limit || list.length <= limit) return list;
            if (limit === 1) return [list[0]];
            const sampled = [];
            const step = (list.length - 1) / (limit - 1);
            for (let i = 0; i < limit; i += 1) {
                sampled.push(list[Math.round(i * step)]);
            }
            return sampled;
        }

        function withChartPerformanceOptions(config) {
            const perfMode = isChartPerfModeEnabled();
            const nextConfig = { ...config, options: { ...(config.options || {}) } };
            if (!perfMode) return nextConfig;

            if (!Object.prototype.hasOwnProperty.call(nextConfig.options, 'animation')) {
                nextConfig.options.animation = false;
            }
            nextConfig.options.normalized = true;

            if (nextConfig.type === 'line') {
                const plugins = { ...(nextConfig.options.plugins || {}) };
                if (!plugins.decimation) {
                    plugins.decimation = {
                        enabled: true,
                        algorithm: 'lttb',
                        samples: 180
                    };
                }
                nextConfig.options.plugins = plugins;
            }

            return nextConfig;
        }

        const nativeAlert = (typeof window.alert === 'function') ? window.alert.bind(window) : null;
        let inAppAlertBridgeInstalled = false;

        function removeToast(toast) {
            if (!toast) return;
            toast.classList.remove('is-visible');
            setTimeout(() => toast.remove(), 220);
        }

        function showInAppToast(message, level = 'warn', durationMs = 5200) {
            const text = String(message || '').trim();
            if (!text) return;
            const stack = refs.toastStack;
            if (!stack) {
                if (nativeAlert) nativeAlert(text);
                return;
            }

            const toast = document.createElement('div');
            toast.className = 'app-toast';
            toast.dataset.level = (level === 'error') ? 'error' : 'warn';

            const icon = document.createElement('span');
            icon.className = 'app-toast-icon';
            icon.textContent = (toast.dataset.level === 'error') ? '!' : 'i';

            const msg = document.createElement('div');
            msg.className = 'app-toast-message';
            msg.textContent = text;

            const close = document.createElement('button');
            close.type = 'button';
            close.className = 'app-toast-close';
            close.setAttribute('aria-label', t('action.close'));
            close.textContent = '✕';

            close.addEventListener('click', () => removeToast(toast));
            toast.append(icon, msg, close);
            stack.appendChild(toast);

            while (stack.children.length > 5) {
                const oldest = stack.firstElementChild;
                removeToast(oldest);
            }

            requestAnimationFrame(() => toast.classList.add('is-visible'));
            setTimeout(() => removeToast(toast), Math.max(1800, durationMs));
        }

        function installInAppAlertBridge() {
            if (inAppAlertBridgeInstalled) return;
            inAppAlertBridgeInstalled = true;
            window.alert = (msg) => showInAppToast(String(msg || ''), 'warn');
        }

        function generateRequestNonce() {
            try {
                if (window.crypto && typeof window.crypto.getRandomValues === 'function') {
                    const bytes = new Uint8Array(16);
                    window.crypto.getRandomValues(bytes);
                    return Array.from(bytes).map((b) => b.toString(16).padStart(2, '0')).join('');
                }
            } catch (_) {
                // Fallback below.
            }
            return `${Date.now().toString(16)}_${Math.random().toString(16).slice(2, 18)}`;
        }

        function createEmptyUploadAttemptStats() {
            return {
                attemptedFiles: 0,
                attemptedCsv: 0,
                acceptedFiles: 0,
                acceptedBytes: 0,
                largestUploadBytes: 0,
                nonCsv: 0,
                tooLarge: 0,
                totalOverflow: 0,
                uploadError: 0,
                countOverflow: 0
            };
        }

        lastUploadAttemptStats = createEmptyUploadAttemptStats();

        async function fetchJsonWithTimeout(url, options = {}, timeoutMs = SHARE_FETCH_TIMEOUT_MS) {
            const controller = (typeof AbortController === 'function') ? new AbortController() : null;
            const timeoutId = controller ? window.setTimeout(() => controller.abort(), Math.max(3000, timeoutMs)) : 0;
            try {
                const response = await fetch(url, {
                    ...options,
                    signal: controller ? controller.signal : undefined
                });
                let payload = null;
                try {
                    payload = await response.json();
                } catch (_) {
                    payload = null;
                }
                return { response, payload };
            } finally {
                if (timeoutId) window.clearTimeout(timeoutId);
            }
        }

        async function copyTextToClipboard(text) {
            const value = String(text || '');
            if (!value) return false;
            if (!navigator.clipboard || typeof navigator.clipboard.writeText !== 'function') {
                return false;
            }
            try {
                await navigator.clipboard.writeText(value);
                return true;
            } catch (_) {
                return false;
            }
        }

        function setShareModalStatus(message, level = 'warn') {
            if (!refs.shareModalStatus) return;
            refs.shareModalStatus.textContent = String(message || '');
            refs.shareModalStatus.dataset.level = String(level || 'warn');
        }

        function closeShareModal() {
            setHidden(refs.shareModalOverlay, true);
        }

        function openShareModal(url, autoCopied) {
            const shareUrl = String(url || '').trim();
            if (!shareUrl || !refs.shareModalOverlay || !refs.shareModalLinkInput) {
                if (shareUrl) {
                    showInAppToast(t('alert.shareCopyFallback', { url: shareUrl }), 'warn', 9000);
                }
                return;
            }

            refs.shareModalLinkInput.value = shareUrl;
            setShareModalStatus(
                autoCopied ? t('share.modal.autoCopied') : t('share.modal.autoCopyFailed'),
                autoCopied ? 'success' : 'warn'
            );
            setHidden(refs.shareModalOverlay, false);

            window.requestAnimationFrame(() => {
                refs.shareModalLinkInput?.focus();
                refs.shareModalLinkInput?.select();
            });
        }

        function setDraggablePanelsEnabled(enabled) {
            document.querySelectorAll('.draggable-item').forEach((panel) => {
                panel.draggable = Boolean(enabled);
                if (enabled) {
                    panel.setAttribute('draggable', 'true');
                } else {
                    panel.removeAttribute('draggable');
                }
            });
        }

        function clearPanelPressedState() {
            document.querySelectorAll('.draggable-item.is-panel-pressed').forEach((panel) => {
                panel.classList.remove('is-panel-pressed');
            });
        }

        function setShareReadOnlyMode(enabled) {
            isReadOnlyShareView = Boolean(enabled);
            if (document.body) {
                document.body.classList.toggle('share-readonly', isReadOnlyShareView);
            }
            clearPanelPressedState();
            setDraggablePanelsEnabled(!isReadOnlyShareView);
            if (!isReadOnlyShareView) return;

            dataQualityPinnedByUser = false;
            stopDataQualityCountdown();
            if (dataQualityAutoCloseTimer) {
                clearTimeout(dataQualityAutoCloseTimer);
                dataQualityAutoCloseTimer = null;
            }
            setPanelVisibility('data-quality', false);
            setHidden(refs.viewMenuDropdown, true);
            setMobileHeaderMenuOpen(false, { force: true });
            closeShareModal();
        }

        function getPanelLayoutSnapshot({ includeDataQuality = false } = {}) {
            const snapshot = {
                order: [],
                visibility: {}
            };

            if (refs.grid) {
                snapshot.order = Array.from(refs.grid.querySelectorAll('[data-panel-id]'))
                    .map((panel) => panel.dataset.panelId)
                    .filter((panelId) => SHARE_LAYOUT_ALLOWED_PANELS.includes(panelId));
                const tableAtAnyIndex = snapshot.order.includes('table');
                snapshot.order = snapshot.order.filter((panelId) => panelId !== 'table');
                if (tableAtAnyIndex) snapshot.order.push('table');
            }

            getPanelElements().forEach((panel) => {
                const panelId = String(panel.dataset.panelId || '');
                if (!panelId) return;
                if (!includeDataQuality && panelId === 'data-quality') return;
                if (!SHARE_LAYOUT_ALLOWED_PANELS.includes(panelId) && panelId !== 'data-quality') return;
                const visible = panelVisibility[panelId] !== false && !panel.classList.contains('hidden');
                snapshot.visibility[panelId] = Boolean(visible);
            });

            return snapshot;
        }

        function applyPanelLayoutSnapshot(layout, { includeDataQuality = false } = {}) {
            if (!layout || typeof layout !== 'object') return;

            const visibility = (layout.visibility && typeof layout.visibility === 'object') ? layout.visibility : {};
            const rawOrder = Array.isArray(layout.order) ? layout.order : [];
            if (refs.grid) {
                const seen = new Set();
                const byId = new Map(
                    Array.from(refs.grid.querySelectorAll('[data-panel-id]'))
                        .map((panel) => [panel.dataset.panelId, panel])
                );
                const normalizedOrder = [];

                rawOrder.forEach((panelIdRaw) => {
                    const panelId = String(panelIdRaw || '');
                    if (!panelId || seen.has(panelId)) return;
                    if (!SHARE_LAYOUT_ALLOWED_PANELS.includes(panelId)) return;
                    if (panelId === 'table') return;
                    const panel = byId.get(panelId);
                    if (!panel) return;
                    normalizedOrder.push(panelId);
                    seen.add(panelId);
                });

                SHARE_LAYOUT_ALLOWED_PANELS.forEach((panelId) => {
                    if (panelId === 'table') return;
                    if (seen.has(panelId)) return;
                    if (!byId.has(panelId)) return;
                    normalizedOrder.push(panelId);
                    seen.add(panelId);
                });

                if (byId.has('table')) {
                    normalizedOrder.push('table');
                    seen.add('table');
                }

                normalizedOrder.forEach((panelId) => {
                    const panel = byId.get(panelId);
                    if (panel) refs.grid.appendChild(panel);
                });
                ensureTablePanelAtBottom();
            }

            Object.entries(visibility).forEach(([panelIdRaw, visibleRaw]) => {
                const panelId = String(panelIdRaw || '');
                if (!panelId) return;
                if (!includeDataQuality && panelId === 'data-quality') return;
                if (!SHARE_LAYOUT_ALLOWED_PANELS.includes(panelId) && panelId !== 'data-quality') return;
                setPanelVisibility(panelId, Boolean(visibleRaw));
            });
        }

        function getShareTokenFromUrl() {
            const params = new URLSearchParams(window.location.search || '');
            const raw = String(params.get('share') || params.get('s') || '').trim().toLowerCase();
            if (!raw) return '';
            if (raw === SHARE_STATIC_TEST_TOKEN) return raw;
            return SHARE_TOKEN_PATTERN.test(raw) ? raw : '';
        }

        function getImportTokenFromUrl() {
            const params = new URLSearchParams(window.location.search || '');
            const queryToken = String(params.get('import') || params.get('i') || '').trim().toLowerCase();
            if (queryToken && IMPORT_TOKEN_PATTERN.test(queryToken)) {
                return queryToken;
            }

            const pathname = String(window.location.pathname || '');
            const pathMatch = pathname.match(/\/import\/([a-f0-9]{16,80})\/?$/i);
            if (pathMatch) {
                const token = String(pathMatch[1] || '').trim().toLowerCase();
                if (IMPORT_TOKEN_PATTERN.test(token)) return token;
            }

            const serverToken = String(IMPORT_TOKEN_FROM_SERVER || '').trim().toLowerCase();
            if (serverToken && IMPORT_TOKEN_PATTERN.test(serverToken)) {
                return serverToken;
            }

            return '';
        }

        function clearImportTokenFromUrl() {
            try {
                const url = new URL(window.location.href);
                let changed = false;

                if (url.searchParams.has('import')) {
                    url.searchParams.delete('import');
                    changed = true;
                }
                if (url.searchParams.has('i')) {
                    url.searchParams.delete('i');
                    changed = true;
                }

                const importPathMatch = String(url.pathname || '').match(/^(.*)\/import\/[a-f0-9]{16,80}\/?$/i);
                if (importPathMatch) {
                    let nextPath = String(importPathMatch[1] || '');
                    nextPath = nextPath.replace(/\/+$/, '');
                    url.pathname = nextPath ? `${nextPath}/` : '/';
                    changed = true;
                }

                if (!changed) return;
                window.history.replaceState({}, '', url.toString());
            } catch (_) {
                // no-op
            }
        }

        function buildShortShareUrl(token) {
            const normalizedToken = String(token || '').trim().toLowerCase();
            if (!normalizedToken) return '';
            const url = new URL(pathWithBase('/i'), window.location.origin);
            url.searchParams.set('share', normalizedToken);
            url.searchParams.delete('s');
            url.hash = '';
            return url.toString();
        }

        function getImportSessionCacheKey(token) {
            return `bitaxe_import_cache_${String(token || '').trim().toLowerCase()}`;
        }

        function readImportPayloadFromSessionCache(token) {
            try {
                const key = getImportSessionCacheKey(token);
                const raw = window.sessionStorage.getItem(key);
                if (!raw) return null;
                const parsed = JSON.parse(raw);
                if (!parsed || typeof parsed !== 'object') return null;
                const csv = String(parsed.csv || '');
                const filename = String(parsed.filename || 'autotune_report.csv').trim() || 'autotune_report.csv';
                const savedAt = Number(parsed.savedAt || 0);
                if (!csv) return null;
                if (Number.isFinite(savedAt) && savedAt > 0) {
                    const age = Date.now() - savedAt;
                    if (age > IMPORT_CACHE_TTL_MS) {
                        window.sessionStorage.removeItem(key);
                        return null;
                    }
                }
                return { csv, filename, savedAt };
            } catch (_) {
                return null;
            }
        }

        function writeImportPayloadToSessionCache(token, importedPayload) {
            try {
                if (!IMPORT_TOKEN_PATTERN.test(String(token || '').trim().toLowerCase())) return;
                const csv = String(importedPayload?.csv || '');
                if (!csv) return;
                const filename = String(importedPayload?.filename || 'autotune_report.csv').trim() || 'autotune_report.csv';
                const key = getImportSessionCacheKey(token);
                window.sessionStorage.setItem(key, JSON.stringify({
                    csv,
                    filename,
                    savedAt: Date.now()
                }));
            } catch (_) {
                // no-op
            }
        }

        function applyImportedCsvPayload(token, importedPayload) {
            const csvText = String(importedPayload?.csv || '');
            const parsed = parseCSV(csvText);
            if (!Array.isArray(parsed.data) || parsed.data.length === 0) {
                return false;
            }

            const importedFilename = String(importedPayload?.filename || 'autotune_report.csv').trim() || 'autotune_report.csv';
            const importedRecord = buildFileRecordFromUpload({
                name: importedFilename,
                lastModified: Date.now()
            }, parsed);
            importedRecord.id = `autotune_import_${String(token || '').trim().toLowerCase()}`;
            importedRecord.isMaster = true;
            importedRecord.enabled = true;

            rawFilesData = [importedRecord];
            consolidatedData = [];
            visibleRows = DEFAULT_VISIBLE_ROWS;
            pendingFileReads = 0;
            appendUploadMode = false;
            keepUploadOverlayClosedOnBoot = false;
            dataQualityPinnedByUser = false;
            if (refs.fileInput) refs.fileInput.value = '';

            applyFilterState({});
            recomputeAndRender(true);
            scheduleDataQualityAutoClose(DATA_QUALITY_AUTO_CLOSE_DELAY_MS);
            setShareReadOnlyMode(false);
            activateDashboardView({ allowUpload: true });
            syncControlVisibility();
            return true;
        }

        function buildSharePayload() {
            const selectedTheme = getCurrentThemeMode();
            const selectedThemeVariant = getCurrentThemeVariant();
            return {
                meta: {
                    exportedAt: new Date().toISOString(),
                    appVersion: APP_VERSION,
                    mode: 'share',
                    selectedLanguage: normalizeLanguageCode(currentLanguage),
                    selectedTheme,
                    selectedThemeVariant
                },
                visibleRows: toSafeNumber(visibleRows, DEFAULT_VISIBLE_ROWS),
                filters: getFilterState(),
                layout: getPanelLayoutSnapshot({ includeDataQuality: false }),
                sourceFiles: rawFilesData.map((file) => ({
                    id: String(file.id || ''),
                    name: String(file.name || ''),
                    lastModified: toSafeNumber(file.lastModified, Date.now()),
                    isMaster: Boolean(file.isMaster),
                    enabled: file.enabled !== false,
                    stats: normalizeStats(file.stats)
                })),
                consolidatedData: consolidatedData.map((row) => ({
                    source: row.source,
                    sourceFileId: row.sourceFileId,
                    sourceFileName: row.sourceFileName,
                    v: row.v,
                    f: row.f,
                    h: row.h,
                    t: row.t,
                    vr: row.vr,
                    e: row.e,
                    err: row.err,
                    p: row.p,
                    score: row.score
                }))
            };
        }

        async function createShareLink() {
            if (isReadOnlyShareView) {
                showInAppToast(t('alert.shareReadOnly'), 'warn', 4500);
                return;
            }
            if (isShareBusy) {
                showInAppToast(t('alert.shareBusy'), 'warn', 3400);
                return;
            }
            if (!consolidatedData.length) {
                alert(t('alert.needAnalysis'));
                return;
            }
            const hasUserFiles = getUserFileRecords().length > 0;
            const sampleOnly = isSampleOnlyProject();
            if (!hasUserFiles && !sampleOnly) {
                showInAppToast(t('alert.shareNeedsUserCsv'), 'warn', 7000);
                return;
            }

            if (sampleOnly) {
                const shareUrlText = buildShortShareUrl(SHARE_STATIC_TEST_TOKEN);
                const copied = await copyTextToClipboard(shareUrlText);
                openShareModal(shareUrlText, copied);
                return;
            }

            isShareBusy = true;
            syncControlVisibility();

            try {
                const requestBody = {
                    request_ts: String(Math.floor(Date.now() / 1000)),
                    request_nonce: generateRequestNonce(),
                    payload: buildSharePayload()
                };
                const bodyJson = JSON.stringify(requestBody);
                if (!bodyJson || bodyJson.length > SHARE_MAX_BODY_BYTES) {
                    showInAppToast(t('alert.shareTooLarge'), 'error', 8000);
                    return;
                }

                const { response, payload } = await fetchJsonWithTimeout(
                    SHARE_CREATE_API_PATH,
                    {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: bodyJson
                    }
                );

                if (!response.ok || !payload?.ok || !payload?.share?.token) {
                    const serverError = String(payload?.error || '').trim();
                    throw new Error(serverError || 'share_create_failed');
                }

                const token = String(payload.share.token || '').trim().toLowerCase();
                if (!SHARE_TOKEN_PATTERN.test(token)) {
                    throw new Error('invalid_share_token');
                }

                const shareUrlText = buildShortShareUrl(token);
                const copied = await copyTextToClipboard(shareUrlText);
                openShareModal(shareUrlText, copied);
            } catch (error) {
                const message = String(error?.message || '').trim();
                if (message && message !== 'share_create_failed' && message !== 'invalid_share_token') {
                    showInAppToast(message, 'error', 7000);
                } else {
                    showInAppToast(t('alert.shareCreateFailed'), 'error', 7000);
                }
            } finally {
                isShareBusy = false;
                syncControlVisibility();
            }
        }

        async function loadSharedReportFromUrl(tokenCandidate = '', options = {}) {
            const token = String(tokenCandidate || getShareTokenFromUrl()).trim().toLowerCase();
            if (!token) return false;
            const silent = Boolean(options?.silent);

            try {
                if (token === SHARE_STATIC_TEST_TOKEN) {
                    const response = await fetch(`${SAMPLE_CSV_PATH}?v=${encodeURIComponent(APP_VERSION)}`, {
                        cache: 'no-store',
                        credentials: 'same-origin'
                    });
                    if (!response.ok) {
                        throw new Error('sample_share_unavailable');
                    }
                    const csvText = await response.text();
                    const parsed = parseCSV(csvText);
                    if (!Array.isArray(parsed.data) || parsed.data.length === 0) {
                        throw new Error('sample_share_empty');
                    }

                    const sampleRecord = buildFileRecordFromUpload({
                        name: 'bitaxe_demo_sample_v1.csv',
                        lastModified: 0
                    }, parsed);
                    sampleRecord.id = 'sample_shared_test';
                    sampleRecord.isSample = true;

                    rawFilesData = [sampleRecord];
                    consolidatedData = [];
                    visibleRows = DEFAULT_VISIBLE_ROWS;
                    pendingFileReads = 0;
                    appendUploadMode = false;
                    dataQualityPinnedByUser = false;
                    if (refs.fileInput) refs.fileInput.value = '';

                    applyFilterState({});
                    recomputeAndRender(true);
                    setShareReadOnlyMode(true);
                    activateDashboardView({ allowUpload: false });
                    syncControlVisibility();
                    return true;
                }

                const { response, payload } = await fetchJsonWithTimeout(
                    `${SHARE_GET_API_PATH}?s=${encodeURIComponent(token)}`,
                    {
                        method: 'GET',
                        credentials: 'same-origin',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    }
                );

                if (response.status === 404) {
                    if (!silent) {
                        showInAppToast(t('alert.shareNotFound'), 'error', 7000);
                    }
                    return false;
                }

                if (!response.ok || !payload?.ok || !payload?.share?.payload) {
                    throw new Error(String(payload?.error || 'share_load_failed'));
                }

                const sharedPayload = payload.share.payload;
                const selectedLanguage = normalizeLanguageCode(sharedPayload?.meta?.selectedLanguage);
                applyLanguage(selectedLanguage, { rerender: false });
                applyTheme(sharedPayload?.meta?.selectedTheme, { persist: false, rerender: false });
                applyThemeVariant(sharedPayload?.meta?.selectedThemeVariant, { persist: false, rerender: false });

                const loaded = loadProjectState(sharedPayload, {
                    sourceName: 'shared_report',
                    silentLog: true,
                    lockDataQuality: true,
                    layout: sharedPayload?.layout
                });
                if (!loaded) {
                    throw new Error('share_payload_invalid');
                }

                setShareReadOnlyMode(true);
                activateDashboardView({ allowUpload: false });
                syncControlVisibility();
                return true;
            } catch (error) {
                if (silent) return false;
                const message = String(error?.message || '').trim();
                if (message && !['share_load_failed', 'share_payload_invalid'].includes(message)) {
                    showInAppToast(message, 'error', 7000);
                } else {
                    showInAppToast(t('alert.shareLoadFailed'), 'error', 7000);
                }
                return false;
            }
        }

        async function loadAutotuneImportFromUrl(tokenCandidate = '') {
            const token = String(tokenCandidate || getImportTokenFromUrl()).trim().toLowerCase();
            if (!token || !IMPORT_TOKEN_PATTERN.test(token)) return false;
            const cachedPayload = readImportPayloadFromSessionCache(token);

            try {
                const { response, payload } = await fetchJsonWithTimeout(
                    `${IMPORT_CONSUME_API_PATH}?id=${encodeURIComponent(token)}`,
                    {
                        method: 'GET',
                        credentials: 'same-origin',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    }
                );

                if (response.status === 404) {
                    if (cachedPayload && applyImportedCsvPayload(token, cachedPayload)) {
                        showInAppToast(t('alert.importLoaded'), 'success', 4200);
                        return true;
                    }
                    showInAppToast(t('alert.importNotFound'), 'error', 7000);
                    return false;
                }
                if (response.status === 410) {
                    const state = String(payload?.state || '').toLowerCase();
                    if (state === 'consumed' && cachedPayload && applyImportedCsvPayload(token, cachedPayload)) {
                        showInAppToast(t('alert.importLoaded'), 'success', 4200);
                        return true;
                    }
                    if (state === 'consumed') {
                        showInAppToast(t('alert.importConsumed'), 'warn', 7000);
                    } else {
                        showInAppToast(t('alert.importExpired'), 'warn', 7000);
                    }
                    return false;
                }
                if (!response.ok || !payload?.ok || !payload?.import?.csv) {
                    throw new Error(String(payload?.error || 'import_load_failed'));
                }

                const importedPayload = payload.import || {};
                if (!applyImportedCsvPayload(token, importedPayload)) {
                    throw new Error('import_payload_invalid');
                }
                writeImportPayloadToSessionCache(token, importedPayload);
                showInAppToast(t('alert.importLoaded'), 'success', 4200);
                return true;
            } catch (error) {
                const message = String(error?.message || '').trim();
                if (message && !['import_load_failed', 'import_payload_invalid'].includes(message)) {
                    showInAppToast(message, 'error', 7000);
                } else {
                    showInAppToast(t('alert.importLoadFailed'), 'error', 7000);
                }
                return false;
            }
        }

        // Detect whether a click was made outside upload modal bounds (+optional margin).
        function isOverlayClickFarOutsideModal(event, modalEl, marginRatio = UPLOAD_OUTSIDE_CLOSE_MARGIN_RATIO) {
            if (!event || !modalEl) return false;
            const rect = modalEl.getBoundingClientRect();
            const marginX = rect.width * marginRatio;
            const marginY = rect.height * marginRatio;
            const x = event.clientX;
            const y = event.clientY;
            return (
                x < (rect.left - marginX) ||
                x > (rect.right + marginX) ||
                y < (rect.top - marginY) ||
                y > (rect.bottom + marginY)
            );
        }

        // Translation dictionary.
        // Key strategy:
        // 1) Selected language
        // 2) English fallback
        // 3) Turkish fallback
        const I18N = {
            tr: {
                'lang.selectorAria': 'Dil Seçimi',
                'menu.view': 'GÖRÜNÜM',
                'menu.fileManager': 'DOSYA YÖNETİCİSİ',
                'menu.share': 'PAYLAŞ',
                'menu.jpegExport': 'JPEG EXPORT',
                'menu.htmlExport': 'HTML EXPORT',
                'view.boxes': 'Kutular',
                'view.openAll': 'Tümünü Aç',
                'upload.back': 'PANELE DÖN',
                'upload.title': 'VERİLERİ YÜKLEYİN',
                'upload.subtitle': 'Tüm benchmark geçmişinizi tek seferde yükleyin.',
                'upload.exportHint': 'Bu ekran, şu kaynaktan alınan export dosyalarıyla çalışır:',
                'upload.badgeAuto': 'Auto Master',
                'upload.badgeHigh': 'High-Perf Lock',
                'upload.fileList': 'Dosya Listesi',
                'upload.samplePreview': 'Örnek Dosyayla Görüntüle',
                'upload.start': 'ANALİZİ BAŞLAT',
                'upload.recalculate': 'ANALİZİ TEKRAR HESAPLA',
                'dataQuality.title': 'Veri Kalite Özeti',
                'dataQuality.placeholder': 'Veri kalite özeti dosyalar yüklendiğinde gösterilecektir.',
                'panel.stability.title': 'Stabilite & Performans',
                'panel.stability.subtitle': 'Voltaj vs Frekans vs Hata (Chart.js)',
                'panel.elite.title': 'Elit Lig Analizi',
                'panel.elite.subtitle': 'En yüksek Hashrate\'e sahip ilk 10 sonucun verimlilik ortalaması.',
                'panel.elite.avgHash': 'Top 10 Ort. Hash',
                'panel.elite.avgEff': 'Top 10 Ort. Verim',
                'panel.aate.title': 'AATE Referans Kontrolü',
                'panel.aate.subtitle': 'Master vs Golden Config',
                'aate.col.param': 'Parametre',
                'aate.col.ref': 'Referans',
                'aate.col.yours': 'Sizin',
                'aate.col.status': 'Durum',
                'panel.power.title': 'Güç Tüketimi (Watt)',
                'panel.eff.title': 'Verimlilik (J/TH)',
                'panel.temp.title': 'VRM Sıcaklığı',
                'panel.freq.title': 'Frekans Başarımı',
                'panel.vf.title': 'V-F Heatmap',
                'panel.table.title': 'Veri Madencisi',
                'table.quickScore': 'Puan (Yüksekten Düşüğe)',
                'table.quickHash': 'Hash (Yüksekten Düşüğe)',
                'table.reset': 'Sıfırla',
                'filter.minVoltage': 'Min Voltaj',
                'filter.minFreq': 'Min Frekans',
                'filter.minHash': 'Min Hash',
                'filter.maxError': 'Max Hata %',
                'filter.maxVrmTemp': 'Max VRM Sıcaklık °C',
                'filter.maxAsicTemp': 'Max ASIC Sıcaklık °C',
                'table.col.source': 'Kaynak',
                'table.col.voltage': 'Voltaj',
                'table.col.frequency': 'Frekans',
                'table.col.hashrate': 'Hashrate',
                'table.col.vrm': 'VRM',
                'table.col.asic': 'ASIC',
                'table.col.error': 'Hata %',
                'table.col.jth': 'J/TH',
                'table.col.score': 'Puan',
                'table.loadMore': 'Daha Fazla Göster ↓',
                'panelLabel.dataQuality': 'Veri Kalite Özeti',
                'panelLabel.stability': 'Stabilite ve Performans',
                'panelLabel.elite': 'Elit Lig Analizi',
                'panelLabel.aate': 'AATE Referans Kontrolü',
                'panelLabel.power': 'Güç Tüketimi',
                'panelLabel.efficiency': 'Verimlilik',
                'panelLabel.temperature': 'VRM Sıcaklığı',
                'panelLabel.frequency': 'Frekans Başarımı',
                'panelLabel.vf': 'V-F Heatmap',
                'panelLabel.table': 'Veri Madencisi Tablosu',
                'summary.file': 'Dosya: {count}',
                'summary.active': 'Aktif: {count}',
                'summary.totalRows': 'Toplam Satır: {count}',
                'summary.processed': 'İşlenen: {count}',
                'summary.skipped': 'Atlanan: {count}',
                'summary.vrMissing': 'VR Eksik: {count}',
                'summary.hashDerived': 'Türetilen Hash: {count}',
                'summary.effDerived': 'Türetilen Verim: {count}',
                'summary.powerDerived': 'Türetilen Güç: {count}',
                'summary.errMissing': 'Hata Kolonu Eksik: {count}',
                'summary.partial': 'Kısmi Satır: {count}',
                'summary.truncated': 'Sınır Nedeniyle Atlanan: {count}',
                'summary.timeoutFiles': 'Parse Süresi Aşılan Dosya: {count}',
                'summary.merged': 'Birleşik Kayıt: {count}',
                'issue.missingColumns': 'Eksik kolonlar: {columns}',
                'issue.rowsSkipped': '{count} satır atlandı',
                'issue.vrFallback': 'VR bulunamadı, Temp fallback kullanıldı',
                'issue.noVrTemp': '{count} satırda VR/Temp yok',
                'issue.hashDerived': '{count} satırda hash türetildi',
                'issue.effDerived': '{count} satırda verim türetildi',
                'issue.powerDerived': '{count} satırda güç türetildi',
                'issue.errDefault': '{count} satırda hata kolonu yok -> 0 kabul edildi',
                'issue.partialRows': '{count} satır kısmi veriyle alındı',
                'issue.truncatedRows': '{count} satır güvenlik limitleri nedeniyle atlandı',
                'issue.parseTimeout': 'CSV parse zaman limiti ({seconds}s) aşıldı',
                'issue.criticalNone': 'Kritik veri sorunu tespit edilmedi.',
                'file.rows': '{count} satır',
                'file.skipped': '{count} atlandı',
                'file.vrFallbackShort': 'VR=Temp fallback',
                'file.hashDerivedShort': 'H:{count} türetim',
                'file.effDerivedShort': 'E:{count} türetim',
                'file.partialShort': 'Kısmi:{count}',
                'file.truncatedShort': 'Limit:{count}',
                'file.timeoutShort': 'Parse Timeout',
                'file.missingColumns': 'Eksik kolonlar: {columns} | {details}',
                'file.removeTitle': 'Dosyayı kaldır',
                'file.master': 'MASTER',
                'file.countLabel': '{count} Dosya',
                'file.untitled': 'adsız.csv',
                'kpi.dataWaiting': 'Veri bekleniyor...',
                'kpi.masterSelection': 'Master Seçimi',
                'kpi.maxHash': 'Maksimum Hash',
                'kpi.bestEfficiency': 'En Verimli',
                'kpi.voltage': 'VOLTAJ',
                'kpi.frequency': 'FREKANS',
                'kpi.efficiency': 'VERİM',
                'aate.waiting': 'Master veri bekleniyor',
                'aate.target.eff': 'Verimlilik (J/TH)',
                'aate.target.hash': 'Hashrate (GH/s)',
                'aate.target.voltage': 'Voltaj (mV)',
                'aate.target.freq': 'Frekans (MHz)',
                'aate.target.vrm': 'VRM Isısı',
                'aate.target.err': 'Hata Oranı',
                'aate.status.na': 'N/A',
                'aate.status.ok': 'UYGUN',
                'aate.status.deviation': 'SAPMA',
                'chart.master': 'Master',
                'chart.highPerf': 'High Perf',
                'chart.archive': 'Arşiv',
                'chart.freqAxis': 'Frekans (MHz)',
                'chart.voltAxis': 'Voltaj (mV)',
                'chart.hashTooltip': 'Hash',
                'chart.power': 'Güç (W)',
                'chart.others': 'Diğer',
                'chart.hashrateAxis': 'Hashrate (GH/s)',
                'chart.tempAxis': 'VRM / Temp (°C)',
                'chart.avgHash': 'Ort. Hash (GH/s)',
                'chart.vfHeatmap': 'V-F Isı Haritası',
                'badge.master': '★ MASTER',
                'badge.high': '● HIGH',
                'badge.archive': 'Arşiv',
                'action.close': 'Kapat',
                'alert.waitFiles': 'Dosyalar hâlâ okunuyor. Lütfen biraz bekleyin.',
                'alert.needCsv': 'Lütfen en az bir CSV dosyası yükleyin.',
                'alert.sampleLoadFailed': 'Örnek CSV yüklenemedi. Lütfen tekrar deneyin.',
                'alert.nonCsvSkipped': '{count} dosya CSV olmadığı için atlandı.',
                'alert.fileCountExceeded': 'Tek seferde en fazla {limit} dosya işlenir. {count} dosya atlandı.',
                'alert.fileTooLarge': '{count} dosya çok büyük olduğu için atlandı (dosya limiti: {limitMb} MB).',
                'alert.totalSizeExceeded': 'Toplam yükleme limiti ({limitMb} MB) aşıldı. {count} dosya atlandı.',
                'alert.needAnalysis': 'Export için önce analiz oluşturun.',
                'alert.jpegLib': 'JPEG export kütüphanesi yüklenemedi.',
                'alert.jpegFail': 'JPEG export oluşturulurken hata oluştu.',
                'alert.shareBusy': 'Paylaşım bağlantısı hazırlanıyor. Lütfen bekleyin.',
                'alert.shareTooLarge': 'Bu rapor paylaşım limiti için çok büyük. Filtrelerle sonucu daraltıp tekrar deneyin.',
                'alert.shareCreateFailed': 'Paylaşım bağlantısı oluşturulamadı. Lütfen tekrar deneyin.',
                'alert.shareCopied': 'Paylaşım bağlantısı oluşturuldu ve panoya kopyalandı.',
                'alert.shareCopyFallback': 'Panoya kopyalama başarısız oldu. Bağlantıyı elle kopyalayın: {url}',
                'alert.shareNotFound': 'Paylaşım bağlantısı bulunamadı veya süresi doldu.',
                'alert.shareLoadFailed': 'Paylaşım raporu yüklenemedi.',
                'alert.importNotFound': 'Import kaydı bulunamadı.',
                'alert.importExpired': 'Import kaydının süresi dolmuş.',
                'alert.importConsumed': 'Import kaydı daha önce kullanılmış.',
                'alert.importLoadFailed': 'Autotune import verisi yüklenemedi.',
                'alert.importLoaded': 'Autotune verisi yüklendi, analiz başlatıldı.',
                'alert.shareNeedsUserCsv': 'Paylaşım için önce kendi CSV dosyanızı yükleyip analiz edin.',
                'alert.shareReadOnly': 'Paylaşılan bağlantı görünümünde tekrar paylaşım kapalıdır.',
                'share.modal.title': 'Paylaşım Linki Hazır',
                'share.modal.subtitle': 'Aşağıdaki bağlantı otomatik olarak panoya kopyalandı.',
                'share.modal.autoCopied': 'Bağlantı otomatik olarak panoya kopyalandı.',
                'share.modal.autoCopyFailed': 'Otomatik kopyalama başarısız. Kopyala ikonuna tıklayın.',
                'share.modal.manualCopied': 'Bağlantı panoya kopyalandı.',
                'share.modal.copy': 'Linki Kopyala',
                'share.modal.close': 'Kapat',
                'alert.invalidProject': 'Geçersiz proje formatı.',
                'alert.noUsableData': 'Proje içinde kullanılabilir veri bulunamadı.'
            },
            en: {
                'lang.selectorAria': 'Language Selection',
                'menu.view': 'VIEW',
                'menu.fileManager': 'FILE MANAGER',
                'menu.share': 'SHARE',
                'menu.jpegExport': 'JPEG EXPORT',
                'menu.htmlExport': 'HTML EXPORT',
                'view.boxes': 'Panels',
                'view.openAll': 'Show All',
                'upload.back': 'BACK TO DASHBOARD',
                'upload.title': 'UPLOAD YOUR DATA',
                'upload.subtitle': 'Upload your full benchmark history (old, new, test) in one pass.',
                'upload.exportHint': 'This screen works with exported files from:',
                'upload.badgeAuto': 'Auto Master',
                'upload.badgeHigh': 'High-Perf Lock',
                'upload.fileList': 'File List',
                'upload.samplePreview': 'Preview with Sample CSV',
                'upload.start': 'START ANALYSIS',
                'upload.recalculate': 'RECALCULATE ANALYSIS',
                'dataQuality.title': 'Data Quality Summary',
                'dataQuality.placeholder': 'Data quality summary will appear after files are loaded.',
                'panel.stability.title': 'Stability & Performance',
                'panel.stability.subtitle': 'Voltage vs Frequency vs Error (Chart.js)',
                'panel.elite.title': 'Elite League Analysis',
                'panel.elite.subtitle': 'Average efficiency of the top 10 highest hashrate results.',
                'panel.elite.avgHash': 'Top 10 Avg. Hash',
                'panel.elite.avgEff': 'Top 10 Avg. Efficiency',
                'panel.aate.title': 'AATE Reference Check',
                'panel.aate.subtitle': 'Master vs Golden Config',
                'aate.col.param': 'Parameter',
                'aate.col.ref': 'Reference',
                'aate.col.yours': 'Yours',
                'aate.col.status': 'Status',
                'panel.power.title': 'Power Consumption (Watt)',
                'panel.eff.title': 'Efficiency (J/TH)',
                'panel.temp.title': 'VRM Temperature',
                'panel.freq.title': 'Frequency Performance',
                'panel.vf.title': 'V-F Heatmap',
                'panel.table.title': 'Data Miner',
                'table.quickScore': 'Score (High to Low)',
                'table.quickHash': 'Hash (High to Low)',
                'table.reset': 'Reset',
                'filter.minVoltage': 'Min Voltage',
                'filter.minFreq': 'Min Frequency',
                'filter.minHash': 'Min Hash',
                'filter.maxError': 'Max Error %',
                'filter.maxVrmTemp': 'Max VRM Temp °C',
                'filter.maxAsicTemp': 'Max ASIC Temp °C',
                'table.col.source': 'Source',
                'table.col.voltage': 'Voltage',
                'table.col.frequency': 'Frequency',
                'table.col.hashrate': 'Hashrate',
                'table.col.vrm': 'VRM',
                'table.col.asic': 'ASIC',
                'table.col.error': 'Error %',
                'table.col.jth': 'J/TH',
                'table.col.score': 'Score',
                'table.loadMore': 'Show More ↓',
                'panelLabel.dataQuality': 'Data Quality Summary',
                'panelLabel.stability': 'Stability & Performance',
                'panelLabel.elite': 'Elite League Analysis',
                'panelLabel.aate': 'AATE Reference Check',
                'panelLabel.power': 'Power Consumption',
                'panelLabel.efficiency': 'Efficiency',
                'panelLabel.temperature': 'VRM Temperature',
                'panelLabel.frequency': 'Frequency Performance',
                'panelLabel.vf': 'V-F Heatmap',
                'panelLabel.table': 'Data Miner Table',
                'summary.file': 'Files: {count}',
                'summary.active': 'Active: {count}',
                'summary.totalRows': 'Total Rows: {count}',
                'summary.processed': 'Processed: {count}',
                'summary.skipped': 'Skipped: {count}',
                'summary.vrMissing': 'VR Missing: {count}',
                'summary.hashDerived': 'Derived Hash: {count}',
                'summary.effDerived': 'Derived Efficiency: {count}',
                'summary.powerDerived': 'Derived Power: {count}',
                'summary.errMissing': 'Missing Error Column: {count}',
                'summary.partial': 'Partial Rows: {count}',
                'summary.truncated': 'Truncated by Limits: {count}',
                'summary.timeoutFiles': 'Parse Timeout Files: {count}',
                'summary.merged': 'Merged Records: {count}',
                'issue.missingColumns': 'Missing columns: {columns}',
                'issue.rowsSkipped': '{count} rows skipped',
                'issue.vrFallback': 'VR not found, temperature fallback used',
                'issue.noVrTemp': '{count} rows missing VR/Temp',
                'issue.hashDerived': '{count} rows with derived hash',
                'issue.effDerived': '{count} rows with derived efficiency',
                'issue.powerDerived': '{count} rows with derived power',
                'issue.errDefault': '{count} rows missing error column -> defaulted to 0',
                'issue.partialRows': '{count} rows accepted with partial data',
                'issue.truncatedRows': '{count} rows were skipped by safety limits',
                'issue.parseTimeout': 'CSV parse time budget exceeded ({seconds}s)',
                'issue.criticalNone': 'No critical data issue detected.',
                'file.rows': '{count} rows',
                'file.skipped': '{count} skipped',
                'file.vrFallbackShort': 'VR=Temp fallback',
                'file.hashDerivedShort': 'H:{count} derived',
                'file.effDerivedShort': 'E:{count} derived',
                'file.partialShort': 'Partial:{count}',
                'file.truncatedShort': 'Limit:{count}',
                'file.timeoutShort': 'Parse Timeout',
                'file.missingColumns': 'Missing columns: {columns} | {details}',
                'file.removeTitle': 'Remove file',
                'file.master': 'MASTER',
                'file.countLabel': '{count} Files',
                'file.untitled': 'untitled.csv',
                'kpi.dataWaiting': 'Waiting for data...',
                'kpi.masterSelection': 'Master Selection',
                'kpi.maxHash': 'Maximum Hash',
                'kpi.bestEfficiency': 'Best Efficiency',
                'kpi.voltage': 'VOLTAGE',
                'kpi.frequency': 'FREQUENCY',
                'kpi.efficiency': 'EFFICIENCY',
                'aate.waiting': 'Waiting for master data',
                'aate.target.eff': 'Efficiency (J/TH)',
                'aate.target.hash': 'Hashrate (GH/s)',
                'aate.target.voltage': 'Voltage (mV)',
                'aate.target.freq': 'Frequency (MHz)',
                'aate.target.vrm': 'VRM Temperature',
                'aate.target.err': 'Error Rate',
                'aate.status.na': 'N/A',
                'aate.status.ok': 'OK',
                'aate.status.deviation': 'DEVIATION',
                'chart.master': 'Master',
                'chart.highPerf': 'High Perf',
                'chart.archive': 'Archive',
                'chart.freqAxis': 'Frequency (MHz)',
                'chart.voltAxis': 'Voltage (mV)',
                'chart.hashTooltip': 'Hash',
                'chart.power': 'Power (W)',
                'chart.others': 'Others',
                'chart.hashrateAxis': 'Hashrate (GH/s)',
                'chart.tempAxis': 'VRM / Temp (°C)',
                'chart.avgHash': 'Avg. Hash (GH/s)',
                'chart.vfHeatmap': 'V-F Heatmap',
                'badge.master': '★ MASTER',
                'badge.high': '● HIGH',
                'badge.archive': 'Archive',
                'action.close': 'Close',
                'alert.waitFiles': 'Files are still loading. Please wait a bit.',
                'alert.needCsv': 'Please upload at least one CSV file.',
                'alert.sampleLoadFailed': 'Sample CSV could not be loaded. Please try again.',
                'alert.nonCsvSkipped': '{count} files were skipped because they are not CSV.',
                'alert.fileCountExceeded': 'Only {limit} files can be processed per batch. {count} files were skipped.',
                'alert.fileTooLarge': '{count} files were skipped because they are too large (per-file limit: {limitMb} MB).',
                'alert.totalSizeExceeded': 'Total upload limit ({limitMb} MB) exceeded. {count} files were skipped.',
                'alert.needAnalysis': 'Please run analysis before export.',
                'alert.jpegLib': 'JPEG export library could not be loaded.',
                'alert.jpegFail': 'An error occurred while generating JPEG export.',
                'alert.shareBusy': 'Preparing share link. Please wait.',
                'alert.shareTooLarge': 'This report is too large for sharing. Narrow the result with filters and try again.',
                'alert.shareCreateFailed': 'Share link could not be created. Please try again.',
                'alert.shareCopied': 'Share link created and copied to clipboard.',
                'alert.shareCopyFallback': 'Clipboard copy failed. Copy this link manually: {url}',
                'alert.shareNotFound': 'Share link was not found or has expired.',
                'alert.shareLoadFailed': 'Shared report could not be loaded.',
                'alert.importNotFound': 'Import record was not found.',
                'alert.importExpired': 'Import record has expired.',
                'alert.importConsumed': 'Import record was already consumed.',
                'alert.importLoadFailed': 'Autotune import data could not be loaded.',
                'alert.importLoaded': 'Autotune data loaded and analysis started.',
                'alert.shareNeedsUserCsv': 'Upload your own CSV files and run analysis before sharing.',
                'alert.shareReadOnly': 'Re-sharing is disabled on shared-link view.',
                'share.modal.title': 'Share Link Ready',
                'share.modal.subtitle': 'The link below has been copied to your clipboard automatically.',
                'share.modal.autoCopied': 'Link copied to clipboard automatically.',
                'share.modal.autoCopyFailed': 'Auto-copy failed. Use the copy icon.',
                'share.modal.manualCopied': 'Link copied to clipboard.',
                'share.modal.copy': 'Copy Link',
                'share.modal.close': 'Close',
                'alert.invalidProject': 'Invalid project format.',
                'alert.noUsableData': 'No usable data found inside the project.'
            },
            'zh-CN': {
                'lang.selectorAria': '语言选择',
                'menu.view': '视图',
                'menu.fileManager': '文件管理',
                'menu.jpegExport': 'JPEG 导出',
                'menu.htmlExport': 'HTML 导出',
                'view.boxes': '面板',
                'view.openAll': '全部显示',
                'upload.back': '返回面板',
                'upload.title': '上传数据',
                'upload.subtitle': '一次性上传全部基准测试记录（旧/新/测试）。',
                'upload.exportHint': '该界面支持来自以下来源的导出文件：',
                'upload.fileList': '文件列表',
                'upload.start': '开始分析',
                'dataQuality.title': '数据质量摘要',
                'panel.stability.title': '稳定性与性能',
                'panel.stability.subtitle': '电压 vs 频率 vs 错误 (Chart.js)',
                'panel.elite.title': '精英分析',
                'panel.elite.subtitle': '哈希率最高的前 10 个结果的平均效率。',
                'panel.elite.avgHash': '前10平均哈希',
                'panel.elite.avgEff': '前10平均效率',
                'panel.aate.title': 'AATE 参考检查',
                'panel.aate.subtitle': '主配置 vs 黄金配置',
                'panel.power.title': '功耗 (W)',
                'panel.eff.title': '效率 (J/TH)',
                'panel.temp.title': 'VRM 温度',
                'panel.freq.title': '频率表现',
                'panel.vf.title': 'V-F 热图',
                'panel.table.title': '数据表',
                'table.quickScore': '评分（高到低）',
                'table.quickHash': '哈希（高到低）',
                'table.reset': '重置',
                'filter.minVoltage': '最小电压',
                'filter.minFreq': '最小频率',
                'filter.minHash': '最小哈希',
                'filter.maxError': '最大错误 %',
                'table.col.source': '来源',
                'table.col.voltage': '电压',
                'table.col.frequency': '频率',
                'table.col.hashrate': '哈希率',
                'table.col.vrm': 'VRM',
                'table.col.error': '错误 %',
                'table.col.score': '评分',
                'table.loadMore': '显示更多 ↓',
                'kpi.masterSelection': '主配置选择',
                'kpi.maxHash': '最大哈希',
                'kpi.bestEfficiency': '最佳效率',
                'kpi.voltage': '电压',
                'kpi.frequency': '频率',
                'kpi.efficiency': '效率',
                'badge.archive': '存档',
                'issue.criticalNone': '未检测到关键数据问题。'
            },
            hi: {
                'lang.selectorAria': 'भाषा चयन',
                'menu.view': 'दृश्य',
                'menu.fileManager': 'फाइल प्रबंधक',
                'menu.jpegExport': 'JPEG एक्सपोर्ट',
                'menu.htmlExport': 'HTML एक्सपोर्ट',
                'view.boxes': 'पैनल',
                'view.openAll': 'सभी दिखाएँ',
                'upload.back': 'पैनल पर लौटें',
                'upload.title': 'डेटा अपलोड करें',
                'upload.subtitle': 'अपना पूरा बेंचमार्क इतिहास एक साथ अपलोड करें।',
                'upload.exportHint': 'यह स्क्रीन इस स्रोत से निर्यात की गई फ़ाइलों के साथ काम करती है:',
                'upload.fileList': 'फाइल सूची',
                'upload.start': 'विश्लेषण शुरू करें',
                'dataQuality.title': 'डेटा गुणवत्ता सारांश',
                'panel.stability.title': 'स्थिरता और प्रदर्शन',
                'panel.stability.subtitle': 'वोल्टेज बनाम फ्रीक्वेंसी बनाम त्रुटि (Chart.js)',
                'panel.elite.title': 'एलीट विश्लेषण',
                'panel.elite.subtitle': 'सबसे अधिक हैशरेट वाले शीर्ष 10 परिणामों की औसत दक्षता।',
                'panel.elite.avgHash': 'शीर्ष 10 औसत हैश',
                'panel.elite.avgEff': 'शीर्ष 10 औसत दक्षता',
                'panel.aate.title': 'AATE संदर्भ जांच',
                'panel.aate.subtitle': 'मास्टर बनाम गोल्डन कॉन्फिग',
                'panel.power.title': 'पावर खपत (W)',
                'panel.eff.title': 'दक्षता (J/TH)',
                'panel.temp.title': 'VRM तापमान',
                'panel.freq.title': 'फ्रीक्वेंसी प्रदर्शन',
                'panel.vf.title': 'V-F हीटमैप',
                'panel.table.title': 'डेटा तालिका',
                'table.quickScore': 'स्कोर (उच्च से निम्न)',
                'table.quickHash': 'हैश (उच्च से निम्न)',
                'table.reset': 'रीसेट',
                'filter.minVoltage': 'न्यूनतम वोल्टेज',
                'filter.minFreq': 'न्यूनतम फ्रीक्वेंसी',
                'filter.minHash': 'न्यूनतम हैश',
                'filter.maxError': 'अधिकतम त्रुटि %',
                'table.col.source': 'स्रोत',
                'table.col.score': 'स्कोर',
                'table.loadMore': 'और दिखाएँ ↓',
                'kpi.masterSelection': 'मास्टर चयन',
                'kpi.maxHash': 'अधिकतम हैश',
                'kpi.bestEfficiency': 'सर्वोत्तम दक्षता',
                'kpi.voltage': 'वोल्टेज',
                'kpi.frequency': 'फ्रीक्वेंसी',
                'kpi.efficiency': 'दक्षता',
                'issue.criticalNone': 'कोई गंभीर डेटा समस्या नहीं मिली।'
            },
            es: {
                'lang.selectorAria': 'Selección de idioma',
                'menu.view': 'VISTA',
                'menu.fileManager': 'GESTOR DE ARCHIVOS',
                'menu.jpegExport': 'EXPORTAR JPEG',
                'menu.htmlExport': 'EXPORTAR HTML',
                'view.boxes': 'Paneles',
                'view.openAll': 'Mostrar todo',
                'upload.back': 'VOLVER AL PANEL',
                'upload.title': 'SUBE TUS DATOS',
                'upload.subtitle': 'Sube todo tu historial de benchmark de una vez.',
                'upload.exportHint': 'Esta pantalla funciona con archivos exportados desde:',
                'upload.fileList': 'Lista de archivos',
                'upload.start': 'INICIAR ANALISIS',
                'dataQuality.title': 'Resumen de calidad de datos',
                'panel.stability.title': 'Estabilidad y rendimiento',
                'panel.stability.subtitle': 'Voltaje vs Frecuencia vs Error (Chart.js)',
                'panel.elite.title': 'Analisis elite',
                'panel.elite.subtitle': 'Eficiencia media de los 10 resultados con mayor hashrate.',
                'panel.elite.avgHash': 'Promedio Hash Top 10',
                'panel.elite.avgEff': 'Promedio Eficiencia Top 10',
                'panel.aate.title': 'Control de referencia AATE',
                'panel.aate.subtitle': 'Master vs Configuracion Dorada',
                'panel.power.title': 'Consumo de energia (W)',
                'panel.eff.title': 'Eficiencia (J/TH)',
                'panel.temp.title': 'Temperatura VRM',
                'panel.freq.title': 'Rendimiento de frecuencia',
                'panel.vf.title': 'Mapa de calor V-F',
                'panel.table.title': 'Tabla de datos',
                'table.quickScore': 'Puntuacion (de mayor a menor)',
                'table.quickHash': 'Hash (de mayor a menor)',
                'table.reset': 'Restablecer',
                'table.loadMore': 'Mostrar mas ↓',
                'kpi.masterSelection': 'Seleccion Master',
                'kpi.maxHash': 'Hash Maximo',
                'kpi.bestEfficiency': 'Mejor Eficiencia',
                'kpi.voltage': 'VOLTAJE',
                'kpi.frequency': 'FRECUENCIA',
                'kpi.efficiency': 'EFICIENCIA',
                'issue.criticalNone': 'No se detectaron problemas criticos de datos.'
            },
            fr: {
                'lang.selectorAria': 'Selection de langue',
                'menu.view': 'VUE',
                'menu.fileManager': 'GESTIONNAIRE DE FICHIERS',
                'menu.jpegExport': 'EXPORT JPEG',
                'menu.htmlExport': 'EXPORT HTML',
                'view.boxes': 'Panneaux',
                'view.openAll': 'Tout afficher',
                'upload.back': 'RETOUR AU PANNEAU',
                'upload.title': 'CHARGER LES DONNEES',
                'upload.subtitle': 'Chargez tout votre historique de benchmark en une fois.',
                'upload.exportHint': 'Cet écran fonctionne avec des fichiers exportés depuis :',
                'upload.fileList': 'Liste des fichiers',
                'upload.start': 'DEMARRER L\'ANALYSE',
                'dataQuality.title': 'Resume qualite des donnees',
                'panel.stability.title': 'Stabilite et performance',
                'panel.stability.subtitle': 'Tension vs Frequence vs Erreur (Chart.js)',
                'panel.elite.title': 'Analyse elite',
                'panel.elite.subtitle': 'Efficacite moyenne des 10 meilleurs resultats en hashrate.',
                'panel.elite.avgHash': 'Hash moyen Top 10',
                'panel.elite.avgEff': 'Efficacite moyenne Top 10',
                'panel.aate.title': 'Controle de reference AATE',
                'panel.aate.subtitle': 'Master vs Configuration Golden',
                'panel.power.title': 'Consommation (W)',
                'panel.eff.title': 'Efficacite (J/TH)',
                'panel.temp.title': 'Temperature VRM',
                'panel.freq.title': 'Performance frequence',
                'panel.vf.title': 'Heatmap V-F',
                'panel.table.title': 'Table de donnees',
                'table.quickScore': 'Score (du plus eleve au plus bas)',
                'table.quickHash': 'Hash (du plus eleve au plus bas)',
                'table.reset': 'Reinitialiser',
                'table.loadMore': 'Afficher plus ↓',
                'kpi.masterSelection': 'Selection Master',
                'kpi.maxHash': 'Hash Maximum',
                'kpi.bestEfficiency': 'Meilleure Efficacite',
                'kpi.voltage': 'TENSION',
                'kpi.frequency': 'FREQUENCE',
                'kpi.efficiency': 'EFFICACITE',
                'issue.criticalNone': 'Aucun probleme critique de donnees detecte.'
            },
            ar: {
                'lang.selectorAria': 'اختيار اللغة',
                'menu.view': 'العرض',
                'menu.fileManager': 'مدير الملفات',
                'menu.jpegExport': 'تصدير JPEG',
                'menu.htmlExport': 'تصدير HTML',
                'view.boxes': 'الصناديق',
                'view.openAll': 'إظهار الكل',
                'upload.back': 'العودة إلى اللوحة',
                'upload.title': 'حمّل البيانات',
                'upload.subtitle': 'حمّل سجل الاختبارات بالكامل دفعة واحدة.',
                'upload.exportHint': 'تعمل هذه الشاشة مع ملفات التصدير القادمة من:',
                'upload.fileList': 'قائمة الملفات',
                'upload.start': 'ابدأ التحليل',
                'dataQuality.title': 'ملخص جودة البيانات',
                'panel.stability.title': 'الاستقرار والأداء',
                'panel.stability.subtitle': 'الجهد مقابل التردد مقابل الخطأ (Chart.js)',
                'panel.elite.title': 'تحليل النخبة',
                'panel.elite.subtitle': 'متوسط الكفاءة لأعلى 10 نتائج من حيث الهاشريت.',
                'panel.elite.avgHash': 'متوسط هاش أعلى 10',
                'panel.elite.avgEff': 'متوسط كفاءة أعلى 10',
                'panel.aate.title': 'فحص مرجع AATE',
                'panel.aate.subtitle': 'Master مقابل الإعداد الذهبي',
                'panel.power.title': 'استهلاك الطاقة (W)',
                'panel.eff.title': 'الكفاءة (J/TH)',
                'panel.temp.title': 'حرارة VRM',
                'panel.freq.title': 'أداء التردد',
                'panel.vf.title': 'خريطة حرارة V-F',
                'panel.table.title': 'جدول البيانات',
                'table.reset': 'إعادة ضبط',
                'table.loadMore': 'عرض المزيد ↓',
                'kpi.masterSelection': 'اختيار Master',
                'kpi.maxHash': 'أعلى هاش',
                'kpi.bestEfficiency': 'أفضل كفاءة',
                'kpi.voltage': 'الجهد',
                'kpi.frequency': 'التردد',
                'kpi.efficiency': 'الكفاءة',
                'issue.criticalNone': 'لم يتم اكتشاف مشكلة حرجة في البيانات.'
            },
            bn: {
                'lang.selectorAria': 'ভাষা নির্বাচন',
                'menu.view': 'ভিউ',
                'menu.fileManager': 'ফাইল ম্যানেজার',
                'menu.jpegExport': 'JPEG এক্সপোর্ট',
                'menu.htmlExport': 'HTML এক্সপোর্ট',
                'view.boxes': 'প্যানেল',
                'view.openAll': 'সব দেখাও',
                'upload.back': 'প্যানেলে ফিরুন',
                'upload.title': 'ডেটা আপলোড করুন',
                'upload.subtitle': 'সব বেঞ্চমার্ক ইতিহাস একবারে আপলোড করুন।',
                'upload.exportHint': 'এই স্ক্রিন এই উৎস থেকে এক্সপোর্ট করা ফাইলের সাথে কাজ করে:',
                'upload.fileList': 'ফাইল তালিকা',
                'upload.start': 'বিশ্লেষণ শুরু করুন',
                'dataQuality.title': 'ডেটা কোয়ালিটি সারাংশ',
                'panel.stability.title': 'স্থিতিশীলতা ও পারফরম্যান্স',
                'panel.stability.subtitle': 'ভোল্টেজ বনাম ফ্রিকোয়েন্সি বনাম ত্রুটি (Chart.js)',
                'panel.elite.title': 'এলিট বিশ্লেষণ',
                'panel.elite.subtitle': 'সর্বোচ্চ হ্যাশরেটের শীর্ষ ১০ ফলের গড় দক্ষতা।',
                'panel.elite.avgHash': 'টপ ১০ গড় হ্যাশ',
                'panel.elite.avgEff': 'টপ ১০ গড় দক্ষতা',
                'panel.aate.title': 'AATE রেফারেন্স চেক',
                'panel.aate.subtitle': 'মাস্টার বনাম গোল্ডেন কনফিগ',
                'panel.table.title': 'ডেটা টেবিল',
                'table.reset': 'রিসেট',
                'table.loadMore': 'আরও দেখুন ↓',
                'kpi.masterSelection': 'মাস্টার নির্বাচন',
                'kpi.maxHash': 'সর্বোচ্চ হ্যাশ',
                'kpi.bestEfficiency': 'সেরা দক্ষতা',
                'kpi.voltage': 'ভোল্টেজ',
                'kpi.frequency': 'ফ্রিকোয়েন্সি',
                'kpi.efficiency': 'দক্ষতা',
                'issue.criticalNone': 'কোনো গুরুতর ডেটা সমস্যা শনাক্ত হয়নি।'
            },
            pt: {
                'lang.selectorAria': 'Selecao de idioma',
                'menu.view': 'VISAO',
                'menu.fileManager': 'GERENCIADOR DE ARQUIVOS',
                'menu.jpegExport': 'EXPORTAR JPEG',
                'menu.htmlExport': 'EXPORTAR HTML',
                'view.boxes': 'Painéis',
                'view.openAll': 'Mostrar tudo',
                'upload.back': 'VOLTAR AO PAINEL',
                'upload.title': 'ENVIAR DADOS',
                'upload.subtitle': 'Envie todo o historico de benchmark de uma vez.',
                'upload.exportHint': 'Esta tela funciona com arquivos exportados de:',
                'upload.fileList': 'Lista de arquivos',
                'upload.start': 'INICIAR ANALISE',
                'dataQuality.title': 'Resumo de qualidade dos dados',
                'panel.stability.title': 'Estabilidade e desempenho',
                'panel.stability.subtitle': 'Tensao vs Frequencia vs Erro (Chart.js)',
                'panel.elite.title': 'Analise Elite',
                'panel.elite.subtitle': 'Eficiencia media dos 10 resultados com maior hashrate.',
                'panel.elite.avgHash': 'Media Hash Top 10',
                'panel.elite.avgEff': 'Media Eficiencia Top 10',
                'panel.aate.title': 'Verificacao de Referencia AATE',
                'panel.aate.subtitle': 'Master vs Configuracao Golden',
                'panel.table.title': 'Tabela de dados',
                'table.reset': 'Redefinir',
                'table.loadMore': 'Mostrar mais ↓',
                'kpi.masterSelection': 'Selecao Master',
                'kpi.maxHash': 'Hash Maximo',
                'kpi.bestEfficiency': 'Melhor Eficiencia',
                'kpi.voltage': 'TENSAO',
                'kpi.frequency': 'FREQUENCIA',
                'kpi.efficiency': 'EFICIENCIA',
                'issue.criticalNone': 'Nenhum problema critico de dados foi detectado.'
            },
            ru: {
                'lang.selectorAria': 'Выбор языка',
                'menu.view': 'ВИД',
                'menu.fileManager': 'МЕНЕДЖЕР ФАЙЛОВ',
                'menu.jpegExport': 'ЭКСПОРТ JPEG',
                'menu.htmlExport': 'ЭКСПОРТ HTML',
                'view.boxes': 'Панели',
                'view.openAll': 'Показать все',
                'upload.back': 'НАЗАД К ПАНЕЛИ',
                'upload.title': 'ЗАГРУЗИТЕ ДАННЫЕ',
                'upload.subtitle': 'Загрузите всю историю бенчмарков за один раз.',
                'upload.exportHint': 'Этот экран работает с экспортированными файлами из:',
                'upload.fileList': 'Список файлов',
                'upload.start': 'ЗАПУСТИТЬ АНАЛИЗ',
                'dataQuality.title': 'Сводка качества данных',
                'panel.stability.title': 'Стабильность и производительность',
                'panel.stability.subtitle': 'Напряжение vs Частота vs Ошибка (Chart.js)',
                'panel.elite.title': 'Элитный анализ',
                'panel.elite.subtitle': 'Средняя эффективность топ-10 результатов с наибольшим хешрейтом.',
                'panel.elite.avgHash': 'Средний хеш топ-10',
                'panel.elite.avgEff': 'Средняя эффективность топ-10',
                'panel.aate.title': 'Проверка эталона AATE',
                'panel.aate.subtitle': 'Master vs Golden Config',
                'panel.table.title': 'Таблица данных',
                'table.reset': 'Сброс',
                'table.loadMore': 'Показать больше ↓',
                'kpi.masterSelection': 'Выбор Master',
                'kpi.maxHash': 'Максимальный хеш',
                'kpi.bestEfficiency': 'Лучшая эффективность',
                'kpi.voltage': 'НАПРЯЖЕНИЕ',
                'kpi.frequency': 'ЧАСТОТА',
                'kpi.efficiency': 'ЭФФЕКТИВНОСТЬ',
                'issue.criticalNone': 'Критических проблем с данными не обнаружено.'
            },
            ur: {
                'lang.selectorAria': 'زبان کا انتخاب',
                'menu.view': 'ویو',
                'menu.fileManager': 'فائل مینیجر',
                'menu.jpegExport': 'JPEG ایکسپورٹ',
                'menu.htmlExport': 'HTML ایکسپورٹ',
                'view.boxes': 'پینلز',
                'view.openAll': 'سب دکھائیں',
                'upload.back': 'پینل پر واپس جائیں',
                'upload.title': 'ڈیٹا اپ لوڈ کریں',
                'upload.subtitle': 'پورا بینچ مارک ہسٹری ایک بار میں اپ لوڈ کریں۔',
                'upload.exportHint': 'یہ اسکرین اس سورس سے ایکسپورٹ کی گئی فائلوں کے ساتھ کام کرتی ہے:',
                'upload.fileList': 'فائل فہرست',
                'upload.start': 'تجزیہ شروع کریں',
                'dataQuality.title': 'ڈیٹا کوالٹی خلاصہ',
                'panel.stability.title': 'استحکام اور کارکردگی',
                'panel.stability.subtitle': 'وولٹیج بمقابلہ فریکوئنسی بمقابلہ ایرر (Chart.js)',
                'panel.elite.title': 'ایلیٹ تجزیہ',
                'panel.elite.subtitle': 'سب سے زیادہ ہیش ریٹ والے ٹاپ 10 نتائج کی اوسط ایفیشنسی۔',
                'panel.elite.avgHash': 'ٹاپ 10 اوسط ہیش',
                'panel.elite.avgEff': 'ٹاپ 10 اوسط ایفیشنسی',
                'panel.aate.title': 'AATE ریفرنس چیک',
                'panel.aate.subtitle': 'ماسٹر بمقابلہ گولڈن کنفیگ',
                'panel.table.title': 'ڈیٹا ٹیبل',
                'table.reset': 'ری سیٹ',
                'table.loadMore': 'مزید دکھائیں ↓',
                'kpi.masterSelection': 'ماسٹر انتخاب',
                'kpi.maxHash': 'زیادہ سے زیادہ ہیش',
                'kpi.bestEfficiency': 'بہترین ایفیشنسی',
                'kpi.voltage': 'وولٹیج',
                'kpi.frequency': 'فریکوئنسی',
                'kpi.efficiency': 'ایفیشنسی',
                'issue.criticalNone': 'کوئی سنگین ڈیٹا مسئلہ شناخت نہیں ہوا۔'
            },
            asm: {
                'lang.selectorAria': 'INT 0x10 ; LANG SELECT',
                'menu.view': 'MOV UI, VIEW',
                'menu.fileManager': 'PUSH FILES',
                'menu.jpegExport': 'INT 0x13 ; JPEG',
                'menu.htmlExport': 'CALL EXPORT_HTML',
                'view.boxes': 'SEGMENT PANELS',
                'view.openAll': 'JMP SHOW_ALL',
                'upload.back': 'RET TO DASHBOARD',
                'upload.title': 'LOAD CSV INTO MEMORY',
                'upload.subtitle': 'SCAN, PARSE, MERGE, BENCHMARK.',
                'upload.exportHint': 'LOAD EXPORT FILES FROM:',
                'upload.badgeAuto': 'AUTO MASTER PTR',
                'upload.badgeHigh': 'HIGH PERF LOCK BIT',
                'upload.fileList': 'FILE TABLE',
                'upload.start': 'EXEC ANALYSIS',
                'dataQuality.title': 'DATA QUALITY STATUS',
                'dataQuality.placeholder': 'WAITING FOR INPUT BUFFER...',
                'panel.stability.title': 'STABILITY + PERF MAP',
                'panel.stability.subtitle': 'Voltage vs Frequency vs Error (Chart.js)',
                'panel.elite.title': 'ELITE LEAGUE REGISTER',
                'panel.elite.subtitle': 'AVG EFFICIENCY OF TOP 10 HASH RESULTS.',
                'panel.elite.avgHash': 'TOP10 AVG HASH',
                'panel.elite.avgEff': 'TOP10 AVG EFF',
                'panel.aate.title': 'AATE REF CHECK',
                'panel.aate.subtitle': 'MASTER vs GOLDEN CFG',
                'aate.col.param': 'REGISTER',
                'aate.col.ref': 'REF',
                'aate.col.yours': 'LIVE',
                'aate.col.status': 'FLAG',
                'panel.power.title': 'POWER DRAW (W)',
                'panel.eff.title': 'EFFICIENCY (J/TH)',
                'panel.temp.title': 'VRM TEMP',
                'panel.freq.title': 'FREQ PERFORMANCE',
                'panel.vf.title': 'V-F HEATMAP',
                'panel.table.title': 'DATA MINER TABLE',
                'table.quickScore': 'SORT SCORE DESC',
                'table.quickHash': 'SORT HASH DESC',
                'table.reset': 'CLR FILTERS',
                'filter.minVoltage': 'MIN VOLT',
                'filter.minFreq': 'MIN FREQ',
                'filter.minHash': 'MIN HASH',
                'filter.maxError': 'MAX ERR %',
                'filter.maxVrmTemp': 'MAX VRM TEMP C',
                'filter.maxAsicTemp': 'MAX ASIC TEMP C',
                'table.col.source': 'SRC',
                'table.col.voltage': 'VOLT',
                'table.col.frequency': 'FREQ',
                'table.col.hashrate': 'HASH',
                'table.col.vrm': 'VRM',
                'table.col.asic': 'ASIC',
                'table.col.error': 'ERR %',
                'table.col.jth': 'J/TH',
                'table.col.score': 'SCORE',
                'table.loadMore': 'LOAD MORE ROWS',
                'panelLabel.dataQuality': 'DATA QUALITY STATUS',
                'panelLabel.stability': 'STABILITY + PERF MAP',
                'panelLabel.elite': 'ELITE LEAGUE REGISTER',
                'panelLabel.aate': 'AATE REF CHECK',
                'panelLabel.power': 'POWER DRAW',
                'panelLabel.efficiency': 'EFFICIENCY',
                'panelLabel.temperature': 'VRM TEMP',
                'panelLabel.frequency': 'FREQ PERFORMANCE',
                'panelLabel.vf': 'V-F HEATMAP',
                'panelLabel.table': 'DATA MINER TABLE',
                'kpi.dataWaiting': 'NOP ; WAIT DATA',
                'kpi.masterSelection': 'MASTER CANDIDATE',
                'kpi.maxHash': 'MAX HASH',
                'kpi.bestEfficiency': 'BEST EFF',
                'kpi.voltage': 'VOLTAGE',
                'kpi.frequency': 'FREQUENCY',
                'kpi.efficiency': 'EFFICIENCY',
                'aate.waiting': 'WAIT MASTER ROW',
                'aate.status.na': 'N/A',
                'aate.status.ok': 'OK',
                'aate.status.deviation': 'DEV',
                'chart.master': 'MASTER',
                'chart.highPerf': 'HIGH PERF',
                'chart.archive': 'ARCHIVE',
                'chart.freqAxis': 'FREQUENCY (MHz)',
                'chart.voltAxis': 'VOLTAGE (mV)',
                'chart.hashTooltip': 'HASH',
                'chart.power': 'POWER (W)',
                'chart.others': 'OTHERS',
                'chart.hashrateAxis': 'HASHRATE (GH/s)',
                'chart.tempAxis': 'VRM / TEMP (C)',
                'chart.avgHash': 'AVG HASH (GH/s)',
                'chart.vfHeatmap': 'V-F HEATMAP',
                'badge.master': '[MASTER]',
                'badge.high': '[HIGH]',
                'badge.archive': '[ARCHIVE]',
                'action.close': 'X ; CLOSE',
                'summary.file': 'FILES: {count}',
                'summary.active': 'ACTIVE: {count}',
                'summary.totalRows': 'ROWS: {count}',
                'summary.processed': 'PARSED: {count}',
                'summary.skipped': 'SKIPPED: {count}',
                'summary.vrMissing': 'VR MISSING: {count}',
                'summary.hashDerived': 'HASH DERIVED: {count}',
                'summary.effDerived': 'EFF DERIVED: {count}',
                'summary.powerDerived': 'POWER DERIVED: {count}',
                'summary.errMissing': 'ERR COL MISSING: {count}',
                'summary.partial': 'PARTIAL: {count}',
                'summary.truncated': 'TRUNCATED: {count}',
                'summary.timeoutFiles': 'TIMEOUT FILES: {count}',
                'summary.merged': 'MERGED: {count}',
                'issue.truncatedRows': 'ROWS CUT BY SAFETY LIMIT: {count}',
                'issue.parseTimeout': 'CSV PARSE TIME BUDGET HIT ({seconds}s)',
                'issue.criticalNone': 'STATUS: NO CRITICAL DATA FAULT',
                'file.rows': '{count} rows',
                'file.skipped': '{count} skipped',
                'file.truncatedShort': 'CUT:{count}',
                'file.timeoutShort': 'TIMEOUT',
                'file.missingColumns': 'MISSING: {columns} | {details}',
                'file.removeTitle': 'drop file',
                'file.master': 'MASTER',
                'file.countLabel': '{count} Files',
                'alert.waitFiles': 'BUSY: FILE IO IN PROGRESS.',
                'alert.needCsv': 'UPLOAD AT LEAST ONE CSV.',
                'alert.nonCsvSkipped': 'NON CSV SKIPPED: {count}',
                'alert.fileCountExceeded': 'BATCH LIMIT {limit}; SKIPPED {count}',
                'alert.fileTooLarge': 'FILE TOO LARGE; SKIPPED {count}; LIMIT {limitMb} MB',
                'alert.totalSizeExceeded': 'TOTAL LIMIT {limitMb} MB EXCEEDED; SKIPPED {count}',
                'alert.needAnalysis': 'RUN ANALYSIS BEFORE EXPORT.',
                'alert.jpegLib': 'JPEG LIB NOT READY.',
                'alert.jpegFail': 'JPEG EXPORT FAILED.',
                'alert.invalidProject': 'INVALID PROJECT FORMAT.',
                'alert.noUsableData': 'NO USABLE DATA FOUND.'
            }
        };

        const PANEL_LABEL_KEY_BY_ID = {
            'data-quality': 'panelLabel.dataQuality',
            stability: 'panelLabel.stability',
            elite: 'panelLabel.elite',
            aate: 'panelLabel.aate',
            power: 'panelLabel.power',
            efficiency: 'panelLabel.efficiency',
            temperature: 'panelLabel.temperature',
            frequency: 'panelLabel.frequency',
            'vf-heatmap': 'panelLabel.vf',
            table: 'panelLabel.table'
        };

        let currentLanguage = DEFAULT_LANGUAGE_CODE;

        // For non-Turkish languages force dotted uppercase-I (İ) to plain I.
        // This protects menu labels or translated text from locale edge-cases.
        function normalizeUppercaseIForLanguage(text, languageCode = currentLanguage) {
            const value = String(text || '');
            return languageCode === 'tr' ? value : value.replace(/İ/g, 'I');
        }

        // Resolve browser language variants (e.g. fr-CA) to supported app locales.
        function resolveSupportedLanguageCode(code) {
            const value = String(code || '').trim();
            if (!value) return null;

            const lowered = value.toLowerCase();
            const exactMatch = SUPPORTED_LANGUAGE_CODES.find((lang) => lang.toLowerCase() === lowered);
            if (exactMatch) return exactMatch;

            const base = lowered.split(/[-_]/)[0];
            if (!base) return null;
            if (base === 'zh') return 'zh-CN';

            const baseMatch = SUPPORTED_LANGUAGE_CODES.find((lang) => lang.toLowerCase() === base);
            return baseMatch || null;
        }

        function normalizeLanguageCode(code) {
            return resolveSupportedLanguageCode(code) || DEFAULT_LANGUAGE_CODE;
        }

        function getDocumentLangAttribute(languageCode = currentLanguage) {
            const normalized = normalizeLanguageCode(languageCode);
            return normalized === 'asm' ? 'en' : normalized;
        }

        function applyDocumentLanguageContext(languageCode = currentLanguage) {
            const normalized = normalizeLanguageCode(languageCode);
            const langAttr = getDocumentLangAttribute(normalized);
            if (document.documentElement) {
                document.documentElement.setAttribute('lang', langAttr);
            }
            if (document.body) {
                document.body.dataset.uiLang = normalized;
            }
        }

        function normalizeDottedUppercaseIInDom(root = document.body, languageCode = currentLanguage) {
            if (!root || languageCode === 'tr') return;
            const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
            let node = walker.nextNode();
            while (node) {
                if (node.nodeValue && node.nodeValue.includes('İ')) {
                    node.nodeValue = node.nodeValue.replace(/İ/g, 'I');
                }
                node = walker.nextNode();
            }
        }

        function detectPreferredLanguageCode() {
            const nav = typeof navigator !== 'undefined' ? navigator : null;
            const preferred = [];
            if (nav && Array.isArray(nav.languages)) preferred.push(...nav.languages);
            if (nav && typeof nav.language === 'string') preferred.push(nav.language);

            for (const candidate of preferred) {
                const resolved = resolveSupportedLanguageCode(candidate);
                if (resolved) return resolved;
            }
            return DEFAULT_LANGUAGE_CODE;
        }

        function getBrowserLanguageTag() {
            const nav = typeof navigator !== 'undefined' ? navigator : null;
            const candidates = [];
            if (nav && Array.isArray(nav.languages)) candidates.push(...nav.languages);
            if (nav && typeof nav.language === 'string') candidates.push(nav.language);

            for (const candidate of candidates) {
                const value = String(candidate || '').trim();
                if (value) return value.slice(0, 24);
            }
            return '';
        }

        function extractRegionFromLocaleTag(localeTag) {
            const raw = String(localeTag || '').trim();
            if (!raw) return '';

            try {
                if (typeof Intl !== 'undefined' && typeof Intl.Locale === 'function') {
                    const region = String(new Intl.Locale(raw).region || '').toUpperCase();
                    if (/^[A-Z]{2}$/.test(region) && region !== 'ZZ' && region !== 'XX') {
                        return region;
                    }
                }
            } catch (_) { /* ignore */ }

            const parts = raw.replace(/_/g, '-').split('-').filter(Boolean);
            for (let i = parts.length - 1; i >= 0; i -= 1) {
                const token = String(parts[i] || '').toUpperCase();
                if (/^[A-Z]{2}$/.test(token) && token !== 'ZZ' && token !== 'XX') {
                    return token;
                }
            }

            return '';
        }

        function detectBrowserCountryHint() {
            const nav = typeof navigator !== 'undefined' ? navigator : null;
            const candidates = [];
            if (nav && Array.isArray(nav.languages)) candidates.push(...nav.languages);
            if (nav && typeof nav.language === 'string') candidates.push(nav.language);
            if (typeof document !== 'undefined' && document.documentElement) {
                candidates.push(String(document.documentElement.getAttribute('lang') || ''));
            }

            for (const candidate of candidates) {
                const region = extractRegionFromLocaleTag(candidate);
                if (region) return region;
            }
            return '';
        }

        function getBrowserTimezoneName() {
            try {
                const tz = String(Intl.DateTimeFormat().resolvedOptions().timeZone || '').trim();
                return tz.slice(0, 64);
            } catch (_) {
                return '';
            }
        }

        function getBrowserTimezoneOffsetMinutes() {
            const now = new Date();
            const offset = -now.getTimezoneOffset();
            if (!Number.isFinite(offset)) return 0;
            return Math.max(-900, Math.min(900, Math.round(offset)));
        }

        function getStoredLanguageCode() {
            try {
                return normalizeLanguageCode(localStorage.getItem(LANGUAGE_STORAGE_KEY));
            } catch (_) {
                return DEFAULT_LANGUAGE_CODE;
            }
        }

        function setStoredLanguageCode(code) {
            const normalized = normalizeLanguageCode(code);
            try {
                localStorage.setItem(LANGUAGE_STORAGE_KEY, normalized);
            } catch (_) { /* ignore */ }
            return normalized;
        }

        function normalizeThemeVariant(value) {
            return String(value || '').toLowerCase() === THEME_VARIANT_ORANGE ? THEME_VARIANT_ORANGE : THEME_VARIANT_PURPLE;
        }

        function getCurrentThemeMode() {
            return document.documentElement.classList.contains('light-theme') ? THEME_LIGHT : THEME_DARK;
        }

        function getCurrentThemeVariant() {
            return normalizeThemeVariant(document.documentElement.getAttribute('data-theme-variant'));
        }

        function getCurrentThemePalette() {
            const variant = getCurrentThemeVariant();
            return THEME_PALETTES[variant] || THEME_PALETTES[THEME_VARIANT_PURPLE];
        }

        function readThemePreference() {
            if (IS_SNAPSHOT_VIEW && SNAPSHOT_THEME) return SNAPSHOT_THEME;
            try {
                const saved = String(localStorage.getItem(THEME_STORAGE_KEY) || '').toLowerCase();
                if (saved === THEME_LIGHT || saved === THEME_DARK) return saved;
            } catch (_) { /* ignore */ }
            return THEME_DARK;
        }

        function readThemeVariantPreference() {
            if (IS_SNAPSHOT_VIEW && SNAPSHOT_THEME_VARIANT) return SNAPSHOT_THEME_VARIANT;
            try {
                const saved = String(localStorage.getItem(THEME_VARIANT_STORAGE_KEY) || '').toLowerCase();
                if (saved === THEME_VARIANT_ORANGE || saved === THEME_VARIANT_PURPLE) return saved;
            } catch (_) { /* ignore */ }
            return THEME_VARIANT_PURPLE;
        }

        let themeTransitionCleanupTimer = null;

        function prefersReducedMotion() {
            try {
                return Boolean(window.matchMedia?.('(prefers-reduced-motion: reduce)').matches);
            } catch (_) {
                return false;
            }
        }

        function markThemeTransitionActive() {
            if (prefersReducedMotion()) return;
            const root = document.documentElement;
            root.classList.add(THEME_TRANSITION_CLASS);
            if (themeTransitionCleanupTimer) {
                clearTimeout(themeTransitionCleanupTimer);
            }
            themeTransitionCleanupTimer = window.setTimeout(() => {
                root.classList.remove(THEME_TRANSITION_CLASS);
                themeTransitionCleanupTimer = null;
            }, THEME_TRANSITION_DURATION_MS);
        }

        function applyThemeAdaptive(theme, options = {}) {
            const run = () => applyTheme(theme, options);
            if (!prefersReducedMotion() && typeof document.startViewTransition === 'function') {
                try {
                    document.startViewTransition(run);
                    return;
                } catch (_) {
                    // Fall through to class-based transition if API throws.
                }
            }
            markThemeTransitionActive();
            run();
        }

        function syncMetaThemeColor(themeMode = getCurrentThemeMode(), variant = getCurrentThemeVariant()) {
            const metaThemeColor = document.querySelector('meta[name="theme-color"]');
            if (!metaThemeColor) return;
            const palette = THEME_PALETTES[normalizeThemeVariant(variant)] || THEME_PALETTES[THEME_VARIANT_PURPLE];
            const normalizedTheme = themeMode === THEME_LIGHT ? THEME_LIGHT : THEME_DARK;
            metaThemeColor.setAttribute('content', palette.meta?.[normalizedTheme] || palette.meta?.[THEME_DARK] || '#070b14');
        }

        function refreshThemeSensitiveVisuals() {
            if (!consolidatedData.length) return;
            renderKPI();
            renderEliteStats();
            void renderChartsFromCurrentData();
        }

        function applyTheme(theme, options = {}) {
            const normalized = theme === THEME_LIGHT ? THEME_LIGHT : THEME_DARK;
            const persist = options.persist !== false;
            const rerender = options.rerender !== false;
            const root = document.documentElement;
            root.classList.remove(THEME_DARK, 'light-theme');
            root.classList.add(normalized === THEME_LIGHT ? 'light-theme' : THEME_DARK);
            root.setAttribute('data-theme', normalized);

            if (refs.themeToggleBtn) {
                const isLight = normalized === THEME_LIGHT;
                refs.themeToggleBtn.classList.toggle('is-light', isLight);
                refs.themeToggleBtn.setAttribute('aria-pressed', isLight ? 'true' : 'false');
                refs.themeToggleBtn.setAttribute('aria-label', isLight ? 'Switch to dark theme' : 'Switch to light theme');
                refs.themeToggleBtn.setAttribute('title', isLight ? 'Dark Theme' : 'Light Theme');
            }

            syncMetaThemeColor(normalized, getCurrentThemeVariant());

            if (persist) {
                try {
                    localStorage.setItem(THEME_STORAGE_KEY, normalized);
                } catch (_) { /* ignore */ }
            }

            if (rerender) {
                refreshThemeSensitiveVisuals();
            }
        }

        function applyThemeVariant(variant, options = {}) {
            const normalized = normalizeThemeVariant(variant);
            const persist = options.persist !== false;
            const rerender = options.rerender !== false;
            const root = document.documentElement;
            const isOrange = normalized === THEME_VARIANT_ORANGE;

            root.classList.remove('theme-variant-purple', 'theme-variant-orange');
            root.classList.add(isOrange ? 'theme-variant-orange' : 'theme-variant-purple');
            root.setAttribute('data-theme-variant', normalized);

            if (refs.variantToggleBtn) {
                refs.variantToggleBtn.classList.toggle('is-orange', isOrange);
                refs.variantToggleBtn.setAttribute('aria-pressed', isOrange ? 'true' : 'false');
                refs.variantToggleBtn.setAttribute('aria-label', isOrange ? 'Switch to purple accent theme' : 'Switch to orange accent theme');
                refs.variantToggleBtn.setAttribute('title', isOrange ? 'Purple Accent' : 'Orange Accent');
            }

            syncMetaThemeColor(getCurrentThemeMode(), normalized);

            if (persist) {
                try {
                    localStorage.setItem(THEME_VARIANT_STORAGE_KEY, normalized);
                } catch (_) { /* ignore */ }
            }

            if (rerender) {
                refreshThemeSensitiveVisuals();
            }
        }

        function initThemeToggle() {
            applyTheme(readThemePreference(), { persist: false, rerender: false });
            applyThemeVariant(readThemeVariantPreference(), { persist: false, rerender: false });
            refreshThemeSensitiveVisuals();

            refs.themeToggleBtn?.addEventListener('click', () => {
                const current = getCurrentThemeMode();
                const next = current === THEME_LIGHT ? THEME_DARK : THEME_LIGHT;
                applyThemeAdaptive(next, { persist: true, rerender: true });
            });

            refs.variantToggleBtn?.addEventListener('click', () => {
                const current = getCurrentThemeVariant();
                const next = current === THEME_VARIANT_ORANGE ? THEME_VARIANT_PURPLE : THEME_VARIANT_ORANGE;
                applyThemeVariant(next, { persist: true, rerender: true });
            });
        }

        function formatDisplayVersionFromAppVersion(versionValue) {
            const raw = String(versionValue || '').trim();
            const match = raw.match(/^v?(\d+)$/i);
            if (!match) return raw || 'v0.0';
            const numeric = Number(match[1]);
            if (!Number.isFinite(numeric)) return raw;
            const major = Math.floor(numeric / 10);
            const minor = numeric % 10;
            return `v${major}.${minor}`;
        }

        function syncBrandVersionLine() {
            const brandVersionLine = document.getElementById('brand-version-line');
            if (!brandVersionLine) return;
            brandVersionLine.textContent = `${formatDisplayVersionFromAppVersion(APP_VERSION)} | ${BRAND_VERSION_SUFFIX}`;
        }

        function interpolate(template, vars = {}) {
            return String(template).replace(/\{(\w+)\}/g, (_, key) => (vars[key] !== undefined ? String(vars[key]) : ''));
        }

        function toAsmBinaryText(input) {
            const text = String(input || '');
            return text.split(/\s+/).map((token) => {
                if (!token) return '';
                if (/^[0-9]+([.,][0-9]+)?%?$/.test(token)) return token;
                if (/^[()\[\]{}:;,.\/+-]+$/.test(token)) return token;

                let hash = 2166136261;
                for (let i = 0; i < token.length; i += 1) {
                    hash ^= token.charCodeAt(i);
                    hash = Math.imul(hash, 16777619);
                }
                const bits = (hash >>> 0).toString(2).padStart(32, '0');
                const outLen = Math.min(18, Math.max(8, Math.round(token.length * 1.6)));
                return bits.slice(0, outLen);
            }).join(' ');
        }

        function toLanguageMenuLabel(input) {
            return normalizeUppercaseIForLanguage(String(input || '').toUpperCase(), currentLanguage);
        }

        function t(key, vars = {}) {
            const langPack = I18N[currentLanguage] || {};
            const template = (
                langPack[key] ??
                (I18N.en && I18N.en[key]) ??
                (I18N.tr && I18N.tr[key]) ??
                key
            );
            const resolved = interpolate(template, vars);
            if (currentLanguage === 'asm') return toAsmBinaryText(resolved);
            return normalizeUppercaseIForLanguage(resolved, currentLanguage);
        }

        function setText(selector, key, vars = {}) {
            const el = document.querySelector(selector);
            if (!el) return;
            el.textContent = t(key, vars);
        }

        function setButtonLabelWithIcon(button, labelText, iconClass = 'quick-sort-icon', iconText = '↓') {
            if (!button) return;
            button.textContent = '';
            const textNode = document.createTextNode(`${labelText} `);
            const icon = document.createElement('span');
            icon.className = iconClass;
            icon.textContent = iconText;
            button.append(textNode, icon);
        }

        function setTableSortButtonLabel(button, labelText) {
            if (!button) return;
            const indicator = button.querySelector('.table-sort-indicator');
            button.textContent = '';
            button.append(document.createTextNode(`${labelText} `));
            if (indicator) {
                button.append(indicator);
            } else {
                const nextIndicator = document.createElement('span');
                nextIndicator.className = 'table-sort-indicator';
                nextIndicator.textContent = '↕';
                button.append(nextIndicator);
            }
        }

        function applyQuickSortButtonLabelsForViewport() {
            const scoreBtn = document.getElementById('quick-sort-score-btn');
            const hashBtn = document.getElementById('quick-sort-hash-btn');
            const compactMode = isMobileHeaderBehaviorEnabled();
            const scoreLabel = compactMode ? t('table.col.score') : t('table.quickScore');
            const hashLabel = compactMode ? t('table.col.hashrate') : t('table.quickHash');
            setButtonLabelWithIcon(scoreBtn, scoreLabel);
            setButtonLabelWithIcon(hashBtn, hashLabel);
        }

        function applyPanelLabelTranslations() {
            getPanelElements().forEach((panel) => {
                const id = panel.dataset.panelId;
                const mappedKey = panel.dataset.panelLabelKey || PANEL_LABEL_KEY_BY_ID[id];
                if (!mappedKey) return;
                panel.dataset.panelLabel = t(mappedKey);
            });
        }

        // Apply static labels/placeholders that are not part of chart/table data rows.
        function applyStaticTranslations() {
            if (refs.languageSelector) refs.languageSelector.setAttribute('aria-label', t('lang.selectorAria'));
            if (refs.languageToggleBtn) refs.languageToggleBtn.setAttribute('aria-label', t('lang.selectorAria'));
            document.querySelectorAll('[data-panel-close]').forEach((btn) => {
                btn.title = t('action.close');
            });
            if (refs.fileCountLabel) refs.fileCountLabel.textContent = t('file.countLabel', { count: rawFilesData.length });

            setText('#lbl-view-menu', 'menu.view');
            setText('#lbl-show-upload', 'menu.fileManager');
            setText('#lbl-share', 'menu.share');
            setText('#lbl-export-jpeg', 'menu.jpegExport');
            setText('#lbl-export-html', 'menu.htmlExport');
            setText('#lbl-view-boxes', 'view.boxes');
            setText('#view-show-all-btn', 'view.openAll');
            setText('#lbl-close-upload', 'upload.back');
            setText('#upload-title', 'upload.title');
            setText('#upload-subtitle', 'upload.subtitle');
            setText('#upload-export-hint-text', 'upload.exportHint');
            setText('#upload-badge-auto', 'upload.badgeAuto');
            setText('#upload-badge-high', 'upload.badgeHigh');
            setText('#upload-sample-preview-label', 'upload.samplePreview');
            setText('#upload-file-list-title', 'upload.fileList');
            setText('#share-modal-title', 'share.modal.title');
            setText('#share-modal-subtitle', 'share.modal.subtitle');
            setText('#share-modal-close-btn', 'share.modal.close');
            if (refs.shareModalCopyBtn) {
                refs.shareModalCopyBtn.setAttribute('aria-label', t('share.modal.copy'));
                refs.shareModalCopyBtn.setAttribute('title', t('share.modal.copy'));
            }
            updateUploadProcessLabel();
            setText('#data-quality-title', 'dataQuality.title');
            setText('#panel-stability-title', 'panel.stability.title');
            setText('#panel-stability-subtitle', 'panel.stability.subtitle');
            setText('#panel-elite-title', 'panel.elite.title');
            setText('#panel-elite-subtitle', 'panel.elite.subtitle');
            setText('#panel-aate-title', 'panel.aate.title');
            setText('#panel-aate-subtitle', 'panel.aate.subtitle');
            setText('#aate-col-param', 'aate.col.param');
            setText('#aate-col-ref', 'aate.col.ref');
            setText('#aate-col-your', 'aate.col.yours');
            setText('#aate-col-status', 'aate.col.status');
            setText('#panel-power-title', 'panel.power.title');
            setText('#panel-eff-title', 'panel.eff.title');
            setText('#panel-temp-title', 'panel.temp.title');
            setText('#panel-freq-title', 'panel.freq.title');
            setText('#panel-vf-title', 'panel.vf.title');
            setText('#panel-table-title', 'panel.table.title');
            applyQuickSortButtonLabelsForViewport();

            setText('#reset-filters-btn', 'table.reset');
            setText('#loadMoreBtn', 'table.loadMore');

            if (refs.filterVMin) refs.filterVMin.placeholder = t('filter.minVoltage');
            if (refs.filterFMin) refs.filterFMin.placeholder = t('filter.minFreq');
            if (refs.filterHMin) refs.filterHMin.placeholder = t('filter.minHash');
            if (refs.filterEMax) refs.filterEMax.placeholder = t('filter.maxError');
            if (refs.filterVrMax) refs.filterVrMax.placeholder = t('filter.maxVrmTemp');
            if (refs.filterTMax) refs.filterTMax.placeholder = t('filter.maxAsicTemp');
            if (refs.filterVMinRange) refs.filterVMinRange.setAttribute('aria-label', t('filter.minVoltage'));
            if (refs.filterFMinRange) refs.filterFMinRange.setAttribute('aria-label', t('filter.minFreq'));
            if (refs.filterHMinRange) refs.filterHMinRange.setAttribute('aria-label', t('filter.minHash'));
            if (refs.filterEMaxRange) refs.filterEMaxRange.setAttribute('aria-label', t('filter.maxError'));
            if (refs.filterVrMaxRange) refs.filterVrMaxRange.setAttribute('aria-label', t('filter.maxVrmTemp'));
            if (refs.filterTMaxRange) refs.filterTMaxRange.setAttribute('aria-label', t('filter.maxAsicTemp'));

            const sortMap = {
                source: 'table.col.source',
                v: 'table.col.voltage',
                f: 'table.col.frequency',
                h: 'table.col.hashrate',
                vr: 'table.col.vrm',
                t: 'table.col.asic',
                err: 'table.col.error',
                e: 'table.col.jth',
                score: 'table.col.score'
            };
            Object.entries(sortMap).forEach(([sortKey, translationKey]) => {
                const btn = document.querySelector(`.table-sort-btn[data-sort-key="${sortKey}"]`);
                if (!btn) return;
                setTableSortButtonLabel(btn, t(translationKey));
            });

            applyPanelLabelTranslations();
        }

        function applyLanguage(code, options = {}) {
            const normalized = setStoredLanguageCode(code);
            currentLanguage = normalized;
            applyDocumentLanguageContext(normalized);
            if (refs.languageSelector && refs.languageSelector.value !== normalized) {
                refs.languageSelector.value = normalized;
            }
            updateLanguageCurrentLabel();
            renderLanguageMenuOptions();
            applyStaticTranslations();
            renderViewMenu();

            const shouldRerender = options.rerender !== false;
            if (!shouldRerender) {
                normalizeDottedUppercaseIInDom(document.body, currentLanguage);
                return;
            }

            if (consolidatedData.length) {
                recomputeAndRender(false);
            } else {
                renderDataQualitySummary(false);
                renderFileList();
                renderTable();
                updateTableSortIndicators();
                syncControlVisibility();
            }
            normalizeDottedUppercaseIInDom(document.body, currentLanguage);
        }

        function setLanguageMenuOpen(open) {
            if (!refs.languageMenu || !refs.languageToggleBtn) return;
            setHidden(refs.languageMenu, !open);
            refs.languageToggleBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        function isViewportInMobileHeaderRange() {
            if (typeof window.matchMedia === 'function') {
                return window.matchMedia('(max-width: 767px)').matches;
            }
            return window.innerWidth <= 767;
        }

        function getGapPixels(style) {
            if (!style) return 0;
            const raw = style.columnGap || style.gap || '0';
            const parsed = parseFloat(raw);
            return Number.isFinite(parsed) ? parsed : 0;
        }

        function getElementOuterWidth(element) {
            if (!element) return 0;
            const rect = element.getBoundingClientRect();
            if (!rect.width) return 0;
            const style = window.getComputedStyle(element);
            const marginLeft = parseFloat(style.marginLeft || '0') || 0;
            const marginRight = parseFloat(style.marginRight || '0') || 0;
            return rect.width + marginLeft + marginRight;
        }

        function shouldUseCompactHeaderByOverflow(currentState = false) {
            if (!refs.appHeader || !refs.headerShell || !refs.mobileHeaderMenuPanel) return false;
            const brandLink = refs.headerShell.querySelector('.brand-home-link');
            const headerActions = document.getElementById('header-actions');
            if (!brandLink || !headerActions) return false;

            const appHeader = refs.appHeader;
            const menuPanel = refs.mobileHeaderMenuPanel;
            const wasCompact = appHeader.classList.contains('is-compact');
            const wasMenuHidden = menuPanel.classList.contains('hidden');

            if (wasCompact) appHeader.classList.remove('is-compact');
            if (wasMenuHidden) menuPanel.classList.remove('hidden');

            const shellWidth = refs.headerShell.clientWidth;
            const shellGap = getGapPixels(window.getComputedStyle(refs.headerShell));
            const actionGap = getGapPixels(window.getComputedStyle(headerActions));
            const actionChildren = Array.from(headerActions.children || []);

            let actionsWidth = 0;
            let visibleActionCount = 0;
            actionChildren.forEach((child) => {
                const width = getElementOuterWidth(child);
                if (width <= 0.5) return;
                actionsWidth += width;
                visibleActionCount += 1;
            });
            if (visibleActionCount > 1) {
                actionsWidth += actionGap * (visibleActionCount - 1);
            }

            const brandWidth = getElementOuterWidth(brandLink);
            const requiredWidth = brandWidth + actionsWidth + shellGap + 20;
            const compactDecision = currentState
                ? requiredWidth > (shellWidth - 20)
                : requiredWidth > shellWidth;

            if (wasCompact) appHeader.classList.add('is-compact');
            if (wasMenuHidden) menuPanel.classList.add('hidden');

            return compactDecision;
        }

        function updateHeaderCompactMode() {
            const viewportCompact = isViewportInMobileHeaderRange();
            const overflowCompact = viewportCompact ? false : shouldUseCompactHeaderByOverflow(refs.appHeader?.classList.contains('is-compact'));
            const shouldCompact = viewportCompact || overflowCompact;
            if (refs.appHeader) {
                refs.appHeader.classList.toggle('is-compact', shouldCompact);
            }
            return shouldCompact;
        }

        function isMobileHeaderBehaviorEnabled() {
            return updateHeaderCompactMode();
        }

        function setMobileHeaderMenuOpen(open, options = {}) {
            const force = Boolean(options.force);
            if (!refs.mobileHeaderMenuPanel || !refs.mobileHeaderMenuBtn) return;

            if (!isMobileHeaderBehaviorEnabled()) {
                mobileHeaderMenuOpen = false;
                refs.mobileHeaderMenuBtn.classList.add('hidden');
                refs.mobileHeaderMenuBtn.setAttribute('aria-expanded', 'false');
                refs.mobileHeaderMenuPanel.classList.remove('hidden');
                return;
            }

            refs.mobileHeaderMenuBtn.classList.remove('hidden');
            const shouldOpen = Boolean(open);
            if (!force && mobileHeaderMenuOpen === shouldOpen) return;
            mobileHeaderMenuOpen = shouldOpen;
            setHidden(refs.mobileHeaderMenuPanel, !mobileHeaderMenuOpen);
            refs.mobileHeaderMenuBtn.setAttribute('aria-expanded', mobileHeaderMenuOpen ? 'true' : 'false');

            if (!mobileHeaderMenuOpen) {
                setLanguageMenuOpen(false);
                setHidden(refs.viewMenuDropdown, true);
            }
        }

        function syncMobileHeaderMenuLayout() {
            if (!refs.mobileHeaderMenuPanel || !refs.mobileHeaderMenuBtn) return;
            if (isMobileHeaderBehaviorEnabled()) {
                refs.mobileHeaderMenuBtn.classList.remove('hidden');
                setHidden(refs.mobileHeaderMenuPanel, !mobileHeaderMenuOpen);
                refs.mobileHeaderMenuBtn.setAttribute('aria-expanded', mobileHeaderMenuOpen ? 'true' : 'false');
                return;
            }

            mobileHeaderMenuOpen = false;
            refs.mobileHeaderMenuBtn.classList.add('hidden');
            refs.mobileHeaderMenuBtn.setAttribute('aria-expanded', 'false');
            refs.mobileHeaderMenuPanel.classList.remove('hidden');
        }

        function updateLanguageCurrentLabel() {
            if (!refs.languageCurrentLabel || !refs.languageSelector) return;
            const selectedOption = refs.languageSelector.options[refs.languageSelector.selectedIndex];
            const label = selectedOption ? String(selectedOption.textContent || '') : '';
            refs.languageCurrentLabel.textContent = toLanguageMenuLabel(label);
        }

        function renderLanguageMenuOptions() {
            if (!refs.languageMenu || !refs.languageSelector) return;
            const options = Array.from(refs.languageSelector.options || []);
            refs.languageMenu.textContent = '';
            const fragment = document.createDocumentFragment();
            options.forEach((option) => {
                const code = String(option.value || '');
                const selected = code === currentLanguage;
                const label = toLanguageMenuLabel(String(option.textContent || code));
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'language-menu-item';
                if (selected) button.classList.add('is-selected');
                button.dataset.langOption = code;
                button.setAttribute('role', 'option');
                button.setAttribute('aria-selected', selected ? 'true' : 'false');
                button.textContent = label;
                fragment.append(button);
            });
            refs.languageMenu.append(fragment);
        }

        function initLanguageSelectorControl() {
            if (!refs.languageSelector) return;
            const embeddedLanguage = normalizeLanguageCode(EMBEDDED_EXPORT_STATE?.meta?.selectedLanguage);
            const detected = IS_EMBEDDED_STATE ? embeddedLanguage : detectPreferredLanguageCode();
            refs.languageSelector.value = detected;
            refs.languageSelector.addEventListener('change', (event) => {
                applyLanguage(event.target.value, { rerender: true });
            });
            refs.languageToggleBtn?.addEventListener('click', (event) => {
                event.stopPropagation();
                const isClosed = refs.languageMenu?.classList.contains('hidden');
                setLanguageMenuOpen(Boolean(isClosed));
            });
            refs.languageMenu?.addEventListener('click', (event) => {
                const trigger = event.target?.closest ? event.target.closest('[data-lang-option]') : null;
                if (!trigger) return;
                const langCode = trigger.dataset.langOption;
                if (!langCode) return;
                applyLanguage(langCode, { rerender: true });
                setLanguageMenuOpen(false);
            });
            applyLanguage(detected, { rerender: false });
            setLanguageMenuOpen(false);
        }

        function getPanelElements() {
            return Array.from(document.querySelectorAll('[data-panel-id]'));
        }

        function ensureTablePanelAtBottom() {
            if (!refs.grid) return;
            const tablePanel = refs.grid.querySelector('#panel-table[data-panel-id="table"]')
                || refs.grid.querySelector('[data-panel-id="table"]');
            if (!tablePanel) return;
            refs.grid.appendChild(tablePanel);
        }

        function initPanelState() {
            panelVisibility = {};
            dataQualityPinnedByUser = false;
            getPanelElements().forEach((panel) => {
                panelVisibility[panel.dataset.panelId] = !panel.classList.contains('hidden');
            });
            ensureTablePanelAtBottom();
            renderViewMenu();
        }

        function setPanelVisibility(panelId, visible) {
            if (!panelId) return;
            const panel = document.querySelector(`[data-panel-id="${panelId}"]`);
            if (!panel) return;
            const nextVisible = (isReadOnlyShareView && panelId === 'data-quality') ? false : Boolean(visible);
            if (panelId === 'data-quality') {
                if (dataQualityFadeOutTimer) {
                    clearTimeout(dataQualityFadeOutTimer);
                    dataQualityFadeOutTimer = null;
                }
                panel.classList.remove('is-fading-out');
                if (!nextVisible) {
                    if (dataQualityAutoCloseTimer) {
                        clearTimeout(dataQualityAutoCloseTimer);
                        dataQualityAutoCloseTimer = null;
                    }
                    stopDataQualityCountdown();
                }
            }
            panel.classList.toggle('hidden', !nextVisible);
            panelVisibility[panelId] = nextVisible;
            renderViewMenu();
        }

        function stopDataQualityCountdown() {
            if (dataQualityCountdownInterval) {
                clearInterval(dataQualityCountdownInterval);
                dataQualityCountdownInterval = null;
            }
            dataQualityCountdownEndsAt = 0;
            if (refs.dataQualityCountdown) {
                refs.dataQualityCountdown.textContent = '';
                refs.dataQualityCountdown.classList.add('hidden');
            }
        }

        function updateDataQualityCountdownDisplay() {
            if (!refs.dataQualityCountdown) return;
            const remainingMs = Math.max(0, dataQualityCountdownEndsAt - Date.now());
            const remainingSeconds = Math.ceil(remainingMs / 1000);
            if (remainingSeconds <= 0) {
                refs.dataQualityCountdown.textContent = '';
                refs.dataQualityCountdown.classList.add('hidden');
                return;
            }
            refs.dataQualityCountdown.textContent = `${remainingSeconds}s`;
            refs.dataQualityCountdown.classList.remove('hidden');
        }

        function startDataQualityCountdown(delayMs = DATA_QUALITY_AUTO_CLOSE_DELAY_MS) {
            if (IS_SNAPSHOT_VIEW) return;
            const safeDelay = Number.isFinite(delayMs) ? Math.max(0, delayMs) : DATA_QUALITY_AUTO_CLOSE_DELAY_MS;
            stopDataQualityCountdown();
            if (safeDelay <= 0) return;
            dataQualityCountdownEndsAt = Date.now() + safeDelay;
            updateDataQualityCountdownDisplay();
            dataQualityCountdownInterval = setInterval(() => {
                if (panelVisibility['data-quality'] === false) {
                    stopDataQualityCountdown();
                    return;
                }
                updateDataQualityCountdownDisplay();
                if (Date.now() >= dataQualityCountdownEndsAt) {
                    stopDataQualityCountdown();
                }
            }, 250);
        }

        function closeDataQualityWithFade(durationMs = 350) {
            const panel = refs.dataQualitySection || document.querySelector('[data-panel-id="data-quality"]');
            if (!panel || panel.classList.contains('hidden')) {
                setPanelVisibility('data-quality', false);
                return;
            }
            stopDataQualityCountdown();
            panel.classList.add('is-fading-out');
            if (dataQualityFadeOutTimer) {
                clearTimeout(dataQualityFadeOutTimer);
            }
            dataQualityFadeOutTimer = setTimeout(() => {
                setPanelVisibility('data-quality', false);
                dataQualityFadeOutTimer = null;
            }, Math.max(120, durationMs));
        }

        function scheduleDataQualityAutoClose(delayMs = DATA_QUALITY_AUTO_CLOSE_DELAY_MS) {
            if (IS_SNAPSHOT_VIEW || isReadOnlyShareView) return;
            if (dataQualityPinnedByUser) {
                if (dataQualityAutoCloseTimer) {
                    clearTimeout(dataQualityAutoCloseTimer);
                    dataQualityAutoCloseTimer = null;
                }
                stopDataQualityCountdown();
                return;
            }
            const safeDelay = Number.isFinite(delayMs) ? Math.max(0, delayMs) : DATA_QUALITY_AUTO_CLOSE_DELAY_MS;
            if (dataQualityAutoCloseTimer) {
                clearTimeout(dataQualityAutoCloseTimer);
            }
            startDataQualityCountdown(safeDelay);
            dataQualityAutoCloseTimer = setTimeout(() => {
                if (panelVisibility['data-quality'] === false) return;
                closeDataQualityWithFade(380);
                dataQualityAutoCloseTimer = null;
            }, safeDelay);
        }

        function renderViewMenu() {
            if (!refs.viewMenuList) return;
            if (isReadOnlyShareView) {
                refs.viewMenuList.textContent = '';
                setHidden(refs.viewMenuDropdown, true);
                return;
            }
            const panels = getPanelElements();
            refs.viewMenuList.textContent = '';
            const fragment = document.createDocumentFragment();
            panels.forEach((panel) => {
                const id = panel.dataset.panelId;
                const label = panel.dataset.panelLabel || id;
                const checked = panelVisibility[id] !== false;
                const row = document.createElement('label');
                row.className = 'view-item';
                const input = document.createElement('input');
                input.type = 'checkbox';
                input.dataset.panelToggle = id;
                input.checked = checked;
                const text = document.createElement('span');
                text.textContent = label;
                row.append(input, text);
                fragment.append(row);
            });
            refs.viewMenuList.append(fragment);
        }

        function updateTableSortIndicators() {
            if (!refs.tableHead) return;
            refs.tableHead.querySelectorAll('.table-sort-btn').forEach((button) => {
                const indicator = button.querySelector('.table-sort-indicator');
                const active = button.dataset.sortKey === tableSort.key;
                button.classList.toggle('active', active);
                if (!indicator) return;
                indicator.textContent = active ? (tableSort.dir === 'asc' ? '↑' : '↓') : '↕';
            });
            if (refs.quickSortControls) {
                refs.quickSortControls.querySelectorAll('.quick-sort-btn').forEach((button) => {
                    const targetKey = button.dataset.quickSort;
                    const isActive = tableSort.dir === 'desc' && (
                        (targetKey === 'score' && tableSort.key === 'score') ||
                        (targetKey === 'h' && tableSort.key === 'h')
                    );
                    button.classList.toggle('active', isActive);
                });
            }
        }

        function setTableSort(nextKey) {
            if (!nextKey) return;
            cancelScheduledTableRender();
            if (tableSort.key === nextKey) {
                tableSort.dir = tableSort.dir === 'asc' ? 'desc' : 'asc';
            } else {
                tableSort.key = nextKey;
                tableSort.dir = nextKey === 'source' ? 'asc' : 'desc';
            }
            updateTableSortIndicators();
            visibleRows = DEFAULT_VISIBLE_ROWS;
            renderTable();
        }

        function applyQuickDescendingSort(key) {
            if (!key) return;
            cancelScheduledTableRender();
            tableSort = { key, dir: 'desc' };
            updateTableSortIndicators();
            visibleRows = DEFAULT_VISIBLE_ROWS;
            renderTable();
        }

        function cancelScheduledTableRender() {
            if (tableRenderDebounceTimer) {
                clearTimeout(tableRenderDebounceTimer);
                tableRenderDebounceTimer = 0;
            }
            if (tableRenderRafId) {
                cancelAnimationFrame(tableRenderRafId);
                tableRenderRafId = 0;
            }
        }

        function scheduleTableRender({ resetRows = false } = {}) {
            if (resetRows) visibleRows = DEFAULT_VISIBLE_ROWS;
            cancelScheduledTableRender();
            tableRenderDebounceTimer = setTimeout(() => {
                tableRenderDebounceTimer = 0;
                tableRenderRafId = requestAnimationFrame(() => {
                    tableRenderRafId = 0;
                    renderTable();
                });
            }, TABLE_RENDER_DEBOUNCE_MS);
        }

        function scheduleResizeUiSync() {
            if (resizeRafId) cancelAnimationFrame(resizeRafId);
            resizeRafId = requestAnimationFrame(() => {
                resizeRafId = 0;
                syncMobileHeaderMenuLayout();
                applyQuickSortButtonLabelsForViewport();
            });
        }

        // Locale-tolerant number parser for mixed CSV formats:
        // accepts "1,23", "1.23", "1.234,56", "1,234.56" and strips unit noise.
        function parseNumber(value) {
            if (value === undefined || value === null) return NaN;
            if (typeof value === 'number') return Number.isFinite(value) ? value : NaN;

            const normalized = String(value).trim().replace(/\u00a0/g, '').replace(/\s+/g, '');
            if (!normalized) return NaN;

            const match = normalized.match(/[-+]?[0-9][0-9.,]*/);
            if (!match) return NaN;

            let token = match[0];
            const hasComma = token.includes(',');
            const hasDot = token.includes('.');

            if (hasComma && hasDot) {
                if (token.lastIndexOf(',') > token.lastIndexOf('.')) token = token.replace(/\./g, '').replace(',', '.');
                else token = token.replace(/,/g, '');
            } else if (hasComma) {
                const parts = token.split(',');
                if (parts.length === 2 && parts[1].length <= 4) token = `${parts[0].replace(/,/g, '')}.${parts[1]}`;
                else token = token.replace(/,/g, '');
            } else if (hasDot) {
                const parts = token.split('.');
                if (parts.length > 2) {
                    const decimals = parts.pop();
                    token = `${parts.join('')}.${decimals}`;
                }
            }

            const parsed = Number.parseFloat(token);
            return Number.isFinite(parsed) ? parsed : NaN;
        }

        function getStepPrecision(step) {
            if (!Number.isFinite(step) || step <= 0) return 0;
            const token = String(step);
            if (token.includes('e-')) {
                const exp = Number.parseInt(token.split('e-')[1], 10);
                return Number.isFinite(exp) ? exp : 0;
            }
            const dotIndex = token.indexOf('.');
            return dotIndex === -1 ? 0 : (token.length - dotIndex - 1);
        }

        function formatFilterNumber(value, precision = 0) {
            if (!Number.isFinite(value)) return '';
            let fixed = Number(value).toFixed(Math.min(6, Math.max(0, precision)));
            if (fixed.includes('.')) {
                fixed = fixed.replace(/0+$/, '').replace(/\.$/, '');
            }
            return fixed;
        }

        function clampNumber(value, min, max) {
            if (!Number.isFinite(value)) return NaN;
            if (!Number.isFinite(min) || !Number.isFinite(max)) return value;
            return Math.min(max, Math.max(min, value));
        }

        function snapToStep(value, step, origin = 0) {
            if (!Number.isFinite(value)) return NaN;
            if (!Number.isFinite(step) || step <= 0) return value;
            const precision = Math.min(8, getStepPrecision(step) + 2);
            const snapped = origin + (Math.round((value - origin) / step) * step);
            return Number(snapped.toFixed(precision));
        }

        function resolveFilterStep(min, max) {
            const span = Math.abs(max - min);
            if (!Number.isFinite(span) || span <= 0) return 1;
            if (span <= 2) return 0.01;
            if (span <= 20) return 0.05;
            if (span <= 100) return 0.1;
            if (span <= 400) return 0.5;
            if (span <= 2000) return 1;
            return 5;
        }

        function getFilterControlBindings() {
            return FILTER_CONTROL_CONFIG.map((cfg) => ({
                ...cfg,
                input: refs[FILTER_REF_BY_ID[cfg.id]] || null,
                range: refs[FILTER_RANGE_REF_BY_ID[cfg.id]] || null
            }));
        }

        function collectFilterRanges() {
            const ranges = {};
            const fields = [];
            FILTER_CONTROL_CONFIG.forEach((cfg) => {
                if (!ranges[cfg.field]) {
                    ranges[cfg.field] = {
                        min: Number.POSITIVE_INFINITY,
                        max: Number.NEGATIVE_INFINITY,
                        hasValue: false
                    };
                    fields.push(cfg.field);
                }
            });

            consolidatedData.forEach((row) => {
                fields.forEach((field) => {
                    const value = Number(row[field]);
                    if (!Number.isFinite(value)) return;
                    const target = ranges[field];
                    if (value < target.min) target.min = value;
                    if (value > target.max) target.max = value;
                    target.hasValue = true;
                });
            });

            return ranges;
        }

        function syncFilterRangeFromInput(binding, { sanitizeInput = false } = {}) {
            if (!binding?.input || !binding.range) return;
            if (binding.range.disabled) return;
            const rangeMin = Number.parseFloat(binding.range.min);
            const rangeMax = Number.parseFloat(binding.range.max);
            const rawStep = Number.parseFloat(binding.range.step);
            const step = Number.isFinite(rawStep) && rawStep > 0 ? rawStep : 1;
            const precision = getStepPrecision(step);

            if (!Number.isFinite(rangeMin) || !Number.isFinite(rangeMax)) return;

            const parsedInput = parseNumber(binding.input.value);
            if (!Number.isFinite(parsedInput)) {
                const fallback = binding.mode === 'max' ? rangeMax : rangeMin;
                binding.range.value = formatFilterNumber(snapToStep(fallback, step, rangeMin), precision);
                return;
            }

            const clamped = clampNumber(parsedInput, rangeMin, rangeMax);
            const snapped = snapToStep(clamped, step, rangeMin);
            binding.range.value = formatFilterNumber(snapped, precision);

            if (sanitizeInput) {
                binding.input.value = formatFilterNumber(snapped, precision);
            }
        }

        function syncFilterInputFromRange(binding) {
            if (!binding?.input || !binding.range) return;
            if (binding.range.disabled) return;
            const step = Number.parseFloat(binding.range.step);
            const precision = getStepPrecision(step);
            const value = parseNumber(binding.range.value);
            if (!Number.isFinite(value)) return;
            binding.input.value = formatFilterNumber(value, precision);
        }

        function syncAllFilterRangesFromInputs(options = {}) {
            getFilterControlBindings().forEach((binding) => {
                syncFilterRangeFromInput(binding, options);
            });
        }

        function refreshFilterControlsFromData() {
            const ranges = collectFilterRanges();
            getFilterControlBindings().forEach((binding) => {
                const { input, range, field, mode } = binding;
                if (!input || !range) return;
                const stats = ranges[field];
                if (!stats?.hasValue) {
                    range.disabled = true;
                    range.min = '0';
                    range.max = '1';
                    range.step = '1';
                    range.value = mode === 'max' ? '1' : '0';
                    input.removeAttribute('min');
                    input.removeAttribute('max');
                    input.removeAttribute('step');
                    return;
                }

                const min = stats.min;
                const max = stats.max;
                const step = resolveFilterStep(min, max);
                const precision = getStepPrecision(step);
                const minText = formatFilterNumber(min, precision);
                const maxText = formatFilterNumber(max, precision);
                const stepText = formatFilterNumber(step, precision);

                range.disabled = false;
                range.min = minText;
                range.max = maxText;
                range.step = (min === max) ? '1' : stepText;

                input.min = minText;
                input.max = maxText;
                input.step = (min === max) ? '1' : stepText;
            });
            syncAllFilterRangesFromInputs({ sanitizeInput: true });
        }

        function asNumber(value) {
            const num = Number(value);
            return Number.isFinite(num) ? num : NaN;
        }

        function toSafeNumber(value, fallback = 0) {
            return Number.isFinite(Number(value)) ? Number(value) : fallback;
        }

        function escapeHtml(text) {
            return String(text || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function escapeForInlineScript(json) {
            return json.replace(/</g, '\\u003c').replace(/>/g, '\\u003e').replace(/&/g, '\\u0026');
        }

        function splitCSVLine(line, delimiter = ',') {
            const out = [];
            let cur = '';
            let inQuotes = false;
            for (let i = 0; i < line.length; i++) {
                const ch = line[i];
                if (ch === '"') {
                    if (inQuotes && line[i + 1] === '"') {
                        cur += '"';
                        i += 1;
                    } else {
                        inQuotes = !inQuotes;
                    }
                    continue;
                }
                if (ch === delimiter && !inQuotes) {
                    out.push(cur);
                    cur = '';
                    continue;
                }
                cur += ch;
            }
            out.push(cur);
            return out;
        }

        function detectCSVDelimiter(headerLine) {
            const line = String(headerLine || '');
            const candidates = [',', ';', '\t'];
            let best = ',';
            let bestCount = -1;

            candidates.forEach((delimiter) => {
                let count = 0;
                let inQuotes = false;
                for (let i = 0; i < line.length; i++) {
                    const ch = line[i];
                    if (ch === '"') {
                        if (inQuotes && line[i + 1] === '"') i += 1;
                        else inQuotes = !inQuotes;
                        continue;
                    }
                    if (ch === delimiter && !inQuotes) count += 1;
                }
                if (count > bestCount) {
                    bestCount = count;
                    best = delimiter;
                }
            });
            return best;
        }

        function normalizeHeader(value) {
            return String(value || '').toLowerCase().replace(/[^a-z0-9]/g, '');
        }

        function findHeaderIndex(normalizedHeaders, aliases) {
            return normalizedHeaders.findIndex((header) => aliases.some((alias) => header.includes(alias)));
        }

        function findHeaderIndexWithExclude(normalizedHeaders, includeAliases, excludeAliases = []) {
            return normalizedHeaders.findIndex((header) => (
                includeAliases.some((alias) => header.includes(alias)) &&
                !excludeAliases.some((alias) => header.includes(alias))
            ));
        }

        function pickFinite(...values) {
            for (const value of values) {
                if (Number.isFinite(value)) return value;
            }
            return NaN;
        }

        function convertHashToGh(value, rawHeader) {
            if (!Number.isFinite(value)) return NaN;
            const header = normalizeHeader(rawHeader);
            if (header.includes('ths') || header.includes('thash')) return value * 1000;
            if (header.includes('mhs') || header.includes('mhash')) return value / 1000;
            if (header.includes('khs') || header.includes('khash')) return value / 1000000;
            return value;
        }

        function convertEfficiencyToJTh(value, rawHeader) {
            if (!Number.isFinite(value)) return NaN;
            const header = normalizeHeader(rawHeader);
            if (header.includes('jgh') || header.includes('wgh')) return value * 1000;
            return value;
        }

        function convertPowerToW(value, rawHeader) {
            if (!Number.isFinite(value)) return NaN;
            const header = normalizeHeader(rawHeader);
            if (header.includes('kw')) return value * 1000;
            return value;
        }

        function deriveHashFromPowerAndEfficiency(powerW, efficiencyJTh) {
            if (!Number.isFinite(powerW) || !Number.isFinite(efficiencyJTh) || efficiencyJTh <= 0) return NaN;
            return (powerW * 1000) / efficiencyJTh;
        }

        function deriveEfficiencyFromPowerAndHash(powerW, hashGh) {
            if (!Number.isFinite(powerW) || !Number.isFinite(hashGh) || hashGh <= 0) return NaN;
            return (powerW * 1000) / hashGh;
        }

        function derivePowerFromHashAndEfficiency(hashGh, efficiencyJTh) {
            if (!Number.isFinite(hashGh) || !Number.isFinite(efficiencyJTh) || hashGh <= 0) return NaN;
            return (hashGh * efficiencyJTh) / 1000;
        }

        function formatValue(value, digits = 2) {
            return Number.isFinite(value) ? Number(value).toFixed(digits) : '—';
        }

        function formatCompact(value, digits = 2) {
            if (!Number.isFinite(value)) return '—';
            return Number.isInteger(value) ? String(value) : Number(value).toFixed(digits);
        }

        function formatTrunc(value) {
            if (!Number.isFinite(value)) return '—';
            return String(Math.trunc(value));
        }

        function formatPercent(value, digits = 1) {
            return `${formatValue(value, digits)}%`;
        }

        function formatTimestamp(ts) {
            if (!Number.isFinite(ts)) return '—';
            const date = new Date(ts);
            const locale = currentLanguage === 'zh-CN' ? 'zh-CN' : currentLanguage;
            return date.toLocaleString(locale, { hour12: false });
        }

        function averageBy(rows, getter) {
            const values = (Array.isArray(rows) ? rows : [])
                .map((row) => getter(row))
                .filter((value) => Number.isFinite(value));
            if (!values.length) return NaN;
            return values.reduce((acc, value) => acc + value, 0) / values.length;
        }

        function makeVFKey(v, f) {
            return `${Number(v).toFixed(3)}-${Number(f).toFixed(3)}`;
        }

        function generateFileId(name, lastModified) {
            const safeName = String(name || 'file').replace(/[^a-zA-Z0-9_.-]/g, '_');
            const safeTime = Number.isFinite(lastModified) ? String(lastModified) : String(Date.now());
            return `${safeName}__${safeTime}`;
        }

        function normalizeStats(stats = {}) {
            return {
                totalRows: toSafeNumber(stats.totalRows, 0),
                parsedRows: toSafeNumber(stats.parsedRows, 0),
                skippedRows: toSafeNumber(stats.skippedRows, 0),
                missingVrRows: toSafeNumber(stats.missingVrRows, 0),
                usedTempAsVr: Boolean(stats.usedTempAsVr),
                missingRequiredColumns: Array.isArray(stats.missingRequiredColumns) ? stats.missingRequiredColumns.map((v) => String(v)) : [],
                derivedHashRows: toSafeNumber(stats.derivedHashRows, 0),
                derivedEffRows: toSafeNumber(stats.derivedEffRows, 0),
                derivedPowerRows: toSafeNumber(stats.derivedPowerRows, 0),
                missingErrRows: toSafeNumber(stats.missingErrRows, 0),
                partialRows: toSafeNumber(stats.partialRows, 0),
                truncatedRows: toSafeNumber(stats.truncatedRows, 0),
                parseTimedOut: Boolean(stats.parseTimedOut)
            };
        }

        function clamp01(value) {
            if (!Number.isFinite(value)) return 0;
            return Math.min(1, Math.max(0, value));
        }

        function higherBetterScore(value, low, high) {
            if (!Number.isFinite(value) || !Number.isFinite(low) || !Number.isFinite(high) || high <= low) return NaN;
            if (value <= low) return 0;
            if (value >= high) return 1;
            return (value - low) / (high - low);
        }

        function lowerBetterScore(value, good, bad) {
            if (!Number.isFinite(value) || !Number.isFinite(good) || !Number.isFinite(bad) || bad <= good) return NaN;
            if (value <= good) return 1;
            if (value >= bad) return 0;
            return (bad - value) / (bad - good);
        }

        function bandScore(value, min, ideal, max) {
            if (!Number.isFinite(value) || !Number.isFinite(min) || !Number.isFinite(ideal) || !Number.isFinite(max)) return NaN;
            if (ideal <= min || max <= ideal) return NaN;
            if (value <= min || value >= max) return 0;
            if (value === ideal) return 1;
            if (value < ideal) return (value - min) / (ideal - min);
            return (max - value) / (max - ideal);
        }

        function averageScores(values, fallback = 0) {
            const safeValues = (Array.isArray(values) ? values : []).filter((value) => Number.isFinite(value));
            if (!safeValues.length) return fallback;
            return safeValues.reduce((acc, value) => acc + value, 0) / safeValues.length;
        }

        function rowVFKey(row) {
            if (!row || !Number.isFinite(row.v) || !Number.isFinite(row.f)) return '';
            return makeVFKey(row.v, row.f);
        }

        // Convert raw row metrics into normalized scoring dimensions for BM1370 profiles.
        function evaluateBm1370Row(row = {}) {
            const h = asNumber(row.h);
            const e = asNumber(row.e);
            const vr = asNumber(row.vr);
            const err = asNumber(row.err);
            const v = asNumber(row.v);
            const f = asNumber(row.f);
            const p = asNumber(row.p);

            const hashBalanced = clamp01(bandScore(h, 2600, 3450, 4100));
            const hashPush = clamp01(higherBetterScore(h, 3600, 4700));
            const eff = Number.isFinite(e) ? clamp01(lowerBetterScore(e, 15.8, 21.5)) : 0.45;
            const temp = Number.isFinite(vr) ? clamp01(lowerBetterScore(vr, 55, 72)) : 0.45;
            const error = Number.isFinite(err) ? clamp01(lowerBetterScore(err, 0.35, 2.5)) : 0.4;
            const voltage = Number.isFinite(v) ? clamp01(bandScore(v, 1220, 1310, 1390)) : 0.45;
            const freq = Number.isFinite(f) ? clamp01(bandScore(f, 720, 860, 1020)) : 0.45;
            const power = Number.isFinite(p) ? clamp01(bandScore(p, 45, 60, 78)) : 0.45;
            const electrical = averageScores([voltage, freq, power], 0.45);

            const ocLevel = averageScores([
                higherBetterScore(f, 980, 1120),
                higherBetterScore(v, 1360, 1450),
                higherBetterScore(h, 4000, 4800)
            ], 0);
            const riskLevel = averageScores([
                higherBetterScore(vr, 65, 82),
                higherBetterScore(err, 1.2, 6)
            ], 0);

            return {
                hashBalanced,
                hashPush,
                eff,
                temp,
                error,
                voltage,
                freq,
                power,
                electrical,
                ocLevel,
                riskLevel
            };
        }

        function calculateScore(row) {
            const hash = asNumber(row.h);
            if (!Number.isFinite(hash)) return -9999;
            const metrics = evaluateBm1370Row(row);

            let score = 0;
            score += metrics.hashBalanced * 26;
            score += metrics.eff * 26;
            score += metrics.temp * 21;
            score += metrics.error * 17;
            score += metrics.electrical * 10;

            score -= metrics.ocLevel * 12;
            score -= metrics.riskLevel * 18;

            if (row.source === 'master') score += 2;
            if (row.source === 'legacy_high') score -= 3;

            return Number.isFinite(score) ? Math.round(score) : -9999;
        }

        function calculateMergePriorityScore(row) {
            const metrics = evaluateBm1370Row(row);
            let score = 0;
            score += metrics.eff * 28;
            score += metrics.temp * 24;
            score += metrics.error * 24;
            score += metrics.hashBalanced * 16;
            score += metrics.electrical * 8;
            score += metrics.hashPush * 4;
            score -= metrics.riskLevel * 8;
            return Number.isFinite(score) ? score : -9999;
        }

        function getMasterSelectionRow() {
            const pool = consolidatedData;
            if (!pool.length) return null;

            const ranked = [...pool].sort((a, b) => calculateScore(b) - calculateScore(a));
            const maxHash = [...pool].sort((a, b) => b.h - a.h)[0];
            if (!maxHash) return ranked[0] || null;

            const maxHashKey = rowVFKey(maxHash);
            const first = ranked[0];
            if (!first) return null;
            if (rowVFKey(first) !== maxHashKey) return first;

            const alternative = ranked.find((row) => rowVFKey(row) !== maxHashKey);
            return alternative || first;
        }

        function calculateSafeProfileScore(row) {
            const metrics = evaluateBm1370Row(row);
            let score = 0;
            score += metrics.error * 38;
            score += metrics.temp * 28;
            score += metrics.eff * 22;
            score += metrics.hashBalanced * 12;
            score -= metrics.ocLevel * 10;
            score -= metrics.riskLevel * 24;
            return Number.isFinite(score) ? score : -9999;
        }

        function calculateAggressiveProfileScore(row) {
            const metrics = evaluateBm1370Row(row);
            const vr = asNumber(row.vr);
            const err = asNumber(row.err);

            const aggressiveTempBand = Number.isFinite(vr) ? clamp01(bandScore(vr, 52, 66, 78)) : 0.3;
            const aggressiveErrBand = Number.isFinite(err) ? clamp01(bandScore(err, 0, 1.5, 4.5)) : 0.25;

            let score = 0;
            score += metrics.hashPush * 46;
            score += clamp01(higherBetterScore(row.f, 920, 1120)) * 20;
            score += clamp01(higherBetterScore(row.v, 1330, 1430)) * 14;
            score += aggressiveTempBand * 10;
            score += aggressiveErrBand * 10;

            if (Number.isFinite(vr) && vr > 82) score -= (vr - 82) * 4;
            if (Number.isFinite(err) && err > 6) score -= (err - 6) * 20;

            return Number.isFinite(score) ? score : -9999;
        }

        function calculateEfficiencyProfileScore(row) {
            const metrics = evaluateBm1370Row(row);
            let score = 0;
            score += metrics.eff * 55;
            score += metrics.error * 22;
            score += metrics.temp * 13;
            score += metrics.hashBalanced * 10;
            score -= metrics.riskLevel * 12;
            return Number.isFinite(score) ? score : -9999;
        }

        function normalizeDataRow(row = {}) {
            const normalized = {
                v: asNumber(row.v),
                f: asNumber(row.f),
                h: asNumber(row.h),
                t: asNumber(row.t),
                vr: asNumber(row.vr),
                e: asNumber(row.e),
                err: asNumber(row.err),
                p: asNumber(row.p)
            };

            if (!Number.isFinite(normalized.h)) normalized.h = deriveHashFromPowerAndEfficiency(normalized.p, normalized.e);
            if (!Number.isFinite(normalized.e)) normalized.e = deriveEfficiencyFromPowerAndHash(normalized.p, normalized.h);
            if (!Number.isFinite(normalized.p)) normalized.p = derivePowerFromHashAndEfficiency(normalized.h, normalized.e);

            if (!Number.isFinite(normalized.err)) normalized.err = 0;
            normalized.vr = asNumber(normalized.vr);
            normalized.t = asNumber(normalized.t);

            if (!Number.isFinite(normalized.v) || !Number.isFinite(normalized.f) || !Number.isFinite(normalized.h)) return null;
            return normalized;
        }

        function normalizeTimePoint(point = {}) {
            const ts = asNumber(point.ts);
            if (!Number.isFinite(ts)) return null;
            return {
                ts,
                action: String(point.action || ''),
                h: asNumber(point.h),
                err: asNumber(point.err),
                temp: asNumber(point.temp),
                p: asNumber(point.p)
            };
        }

        function normalizeFileRecord(file = {}) {
            const normalized = {
                id: String(file.id || generateFileId(file.name, file.lastModified)),
                name: String(file.name || t('file.untitled')),
                lastModified: toSafeNumber(file.lastModified, Date.now()),
                sizeBytes: toSafeNumber(file.sizeBytes, toSafeNumber(file.size, 0)),
                isMaster: Boolean(file.isMaster),
                enabled: file.enabled !== false,
                stats: normalizeStats(file.stats),
                data: Array.isArray(file.data) ? file.data.map(normalizeDataRow).filter(Boolean) : [],
                timeSeries: Array.isArray(file.timeSeries) ? file.timeSeries.map(normalizeTimePoint).filter(Boolean) : []
            };
            if (normalized.stats.totalRows === 0) normalized.stats.totalRows = normalized.data.length;
            if (normalized.stats.parsedRows === 0) normalized.stats.parsedRows = normalized.data.length;
            return normalized;
        }

        function formatMissingColumnLabel(columnCode) {
            if (columnCode === 'missing_voltage' || columnCode === 'Voltaj') return t('table.col.voltage');
            if (columnCode === 'missing_frequency' || columnCode === 'Frekans') return t('table.col.frequency');
            if (columnCode === 'missing_hash_or_pow_eff' || columnCode === 'Hashrate (veya Power+Verimlilik)') return `${t('table.col.hashrate')} (${t('panel.power.title')} + ${t('panel.eff.title')})`;
            return String(columnCode || '');
        }

        function getEnabledFiles() {
            return rawFilesData.filter((file) => file.enabled);
        }

        function getMasterFile() {
            return getEnabledFiles().find((file) => file.isMaster) || null;
        }

        function ensureMasterConsistency() {
            const enabledFiles = getEnabledFiles();
            if (!enabledFiles.length) {
                rawFilesData.forEach((file) => { file.isMaster = false; });
                return;
            }
            const currentMaster = enabledFiles.find((file) => file.isMaster);
            if (currentMaster) return;
            rawFilesData.forEach((file) => { file.isMaster = false; });
            enabledFiles.sort((a, b) => b.lastModified - a.lastModified);
            enabledFiles[0].isMaster = true;
        }

        function destroyAllCharts() {
            cancelScheduledChartRender();
            clearChartInstances();
        }

        function createChart(id, config) {
            if (typeof window.Chart !== 'function') return;
            const canvas = document.getElementById(id);
            if (!canvas) return;
            if (chartInstances[id]) {
                chartInstances[id].destroy();
            }
            chartInstances[id] = new window.Chart(canvas, withChartPerformanceOptions(config));
        }

        // Parse arbitrary benchmark/autotune CSV rows into normalized internal schema.
        // Missing hash/eff/power are derived when possible to keep compatible rows usable.
        function parseCSV(txt) {
            const cleaned = String(txt || '').replace(/^\uFEFF/, '').trim();
            const stats = {
                totalRows: 0,
                parsedRows: 0,
                skippedRows: 0,
                missingVrRows: 0,
                usedTempAsVr: false,
                missingRequiredColumns: [],
                derivedHashRows: 0,
                derivedEffRows: 0,
                derivedPowerRows: 0,
                missingErrRows: 0,
                partialRows: 0,
                truncatedRows: 0,
                parseTimedOut: false
            };
            const timeSeries = [];
            if (!cleaned) return { data: [], stats, timeSeries };

            const lines = cleaned.split(/\r?\n/).filter((line) => line.trim() !== '');
            if (lines.length < 2) return { data: [], stats, timeSeries };

            const delimiter = detectCSVDelimiter(lines[0]);
            const headers = splitCSVLine(lines[0], delimiter).map((h) => h.trim());
            const normalizedHeaders = headers.map(normalizeHeader);
            const tempExcludesForAsic = ['averagevrtemp', 'vrmtemp', 'vrtemp', 'vrmtemperature', 'vrm', 'mosfettemp'];
            const map = {
                v: findHeaderIndex(normalizedHeaders, ['corevoltage', 'voltage', 'voltaj', 'vcore', 'mv', 'mvolt', 'vdd']),
                f: findHeaderIndex(normalizedHeaders, ['frequency', 'freq', 'clock', 'mhz', 'pll']),
                h: findHeaderIndex(normalizedHeaders, ['averagehashrate', 'hashrate', 'hash', 'ghs', 'ths', 'throughput']),
                // VRM must come from explicit VR/VRM-marked columns.
                vr: findHeaderIndex(normalizedHeaders, ['averagevrtemp', 'vrmtemp', 'vrtemp', 'vrmtemperature', 'vrm', 'mosfettemp']),
                // Any non-VR temperature column is treated as ASIC/chip temperature.
                t: findHeaderIndexWithExclude(normalizedHeaders, ['tempc', 'temperature', 'temp', 'asictemp', 'chiptemp', 'averagetemperature', 'avgtemperature'], tempExcludesForAsic),
                e: findHeaderIndex(normalizedHeaders, ['efficiencyjth', 'efficiency', 'verim', 'jth', 'jgh', 'wth', 'wgh', 'eff']),
                err: findHeaderIndex(normalizedHeaders, ['errorpercentage', 'errorrate', 'error', 'hata', 'hwerror', 'rejectrate', 'err']),
                p: findHeaderIndex(normalizedHeaders, ['averagepower', 'power', 'watt', 'watts', 'pow', 'consumption', 'guc']),
                ts: findHeaderIndex(normalizedHeaders, ['timestamp', 'datetime', 'time', 'date', 'createdat']),
                action: findHeaderIndex(normalizedHeaders, ['action', 'event', 'state', 'status', 'reason'])
            };

            if (map.v < 0) stats.missingRequiredColumns.push('missing_voltage');
            if (map.f < 0) stats.missingRequiredColumns.push('missing_frequency');
            if (map.h < 0 && (map.p < 0 || map.e < 0)) stats.missingRequiredColumns.push('missing_hash_or_pow_eff');

            stats.totalRows = Math.max(0, lines.length - 1);
            const data = [];
            const hashHeader = map.h > -1 ? headers[map.h] : '';
            const efficiencyHeader = map.e > -1 ? headers[map.e] : '';
            const powerHeader = map.p > -1 ? headers[map.p] : '';
            const maxLineIndex = Math.min(lines.length - 1, CSV_MAX_DATA_ROWS);
            if (stats.totalRows > CSV_MAX_DATA_ROWS) {
                stats.truncatedRows += (stats.totalRows - CSV_MAX_DATA_ROWS);
            }
            const parseStartTs = (typeof performance !== 'undefined' && typeof performance.now === 'function')
                ? performance.now()
                : Date.now();
            const getNowTs = () => ((typeof performance !== 'undefined' && typeof performance.now === 'function')
                ? performance.now()
                : Date.now());

            for (let i = 1; i <= maxLineIndex; i++) {
                if ((i & 1023) === 0) {
                    const elapsed = getNowTs() - parseStartTs;
                    if (elapsed > CSV_PARSE_TIME_BUDGET_MS) {
                        stats.parseTimedOut = true;
                        const remainingRows = maxLineIndex - i + 1;
                        if (remainingRows > 0) stats.truncatedRows += remainingRows;
                        break;
                    }
                }
                const cells = splitCSVLine(lines[i], delimiter);

                const v = map.v > -1 ? parseNumber(cells[map.v]) : NaN;
                const f = map.f > -1 ? parseNumber(cells[map.f]) : NaN;
                let h = map.h > -1 ? convertHashToGh(parseNumber(cells[map.h]), hashHeader) : NaN;
                let e = map.e > -1 ? convertEfficiencyToJTh(parseNumber(cells[map.e]), efficiencyHeader) : NaN;
                let p = map.p > -1 ? convertPowerToW(parseNumber(cells[map.p]), powerHeader) : NaN;
                const rawErr = map.err > -1 ? parseNumber(cells[map.err]) : NaN;
                let err = rawErr;
                let t = map.t > -1 ? parseNumber(cells[map.t]) : NaN;
                const rawVr = map.vr > -1 ? parseNumber(cells[map.vr]) : NaN;
                let vr = rawVr;

                let derivedHash = false;
                let derivedEff = false;
                let derivedPower = false;

                if (!Number.isFinite(h)) {
                    const inferredHash = deriveHashFromPowerAndEfficiency(p, e);
                    if (Number.isFinite(inferredHash)) {
                        h = inferredHash;
                        derivedHash = true;
                    }
                }
                if (!Number.isFinite(e)) {
                    const inferredEff = deriveEfficiencyFromPowerAndHash(p, h);
                    if (Number.isFinite(inferredEff)) {
                        e = inferredEff;
                        derivedEff = true;
                    }
                }
                if (!Number.isFinite(p)) {
                    const inferredPower = derivePowerFromHashAndEfficiency(h, e);
                    if (Number.isFinite(inferredPower)) {
                        p = inferredPower;
                        derivedPower = true;
                    }
                }

                if (!Number.isFinite(err)) err = 0;
                const hasCoreRow = Number.isFinite(v) && Number.isFinite(f) && Number.isFinite(h);
                if (hasCoreRow) {
                    if (!Number.isFinite(rawErr)) stats.missingErrRows += 1;
                    if (derivedHash) stats.derivedHashRows += 1;
                    if (derivedEff) stats.derivedEffRows += 1;
                    if (derivedPower) stats.derivedPowerRows += 1;
                    if (!Number.isFinite(vr)) stats.missingVrRows += 1;
                    if (!Number.isFinite(e) || !Number.isFinite(p)) stats.partialRows += 1;

                    data.push({
                        v,
                        f,
                        h,
                        t,
                        vr,
                        e,
                        err,
                        p
                    });
                    stats.parsedRows += 1;
                } else {
                    stats.skippedRows += 1;
                }

                const tsCell = map.ts > -1 ? cells[map.ts] : null;
                const ts = Date.parse(String(tsCell || ''));
                if (Number.isFinite(ts)) {
                    const timePoint = {
                        ts,
                        action: map.action > -1 ? String(cells[map.action] || '').trim() : '',
                        h: Number.isFinite(h) ? h : NaN,
                        err: Number.isFinite(rawErr) ? rawErr : NaN,
                        temp: pickFinite(vr, t),
                        p: Number.isFinite(p) ? p : NaN
                    };
                    const hasAnyMetric = ['h', 'err', 'temp', 'p'].some((key) => Number.isFinite(timePoint[key]));
                    if (hasAnyMetric) timeSeries.push(timePoint);
                }
            }
            return { data, stats, timeSeries };
        }

        function buildFileRecordFromUpload(file, parsed) {
            return normalizeFileRecord({
                id: generateFileId(file.name, file.lastModified),
                name: file.name,
                lastModified: file.lastModified,
                sizeBytes: toSafeNumber(file.size, 0),
                isMaster: false,
                enabled: true,
                stats: parsed.stats,
                data: parsed.data,
                timeSeries: parsed.timeSeries
            });
        }

        function isSampleFileRecord(file) {
            return Boolean(file && file.isSample);
        }

        function getUserFileRecords() {
            return rawFilesData.filter((file) => !isSampleFileRecord(file));
        }

        function removeSampleFileRecords({ clearMerged = false } = {}) {
            const hasSample = rawFilesData.some((file) => isSampleFileRecord(file));
            if (!hasSample) return false;
            rawFilesData = rawFilesData.filter((file) => !isSampleFileRecord(file));
            if (clearMerged) {
                consolidatedData = [];
                visibleRows = DEFAULT_VISIBLE_ROWS;
            }
            return true;
        }

        function isSampleOnlyProject() {
            return rawFilesData.length > 0 && rawFilesData.every((file) => isSampleFileRecord(file));
        }

        function mergeTemperatureFields(primaryRow, secondaryRow) {
            const primary = primaryRow ? { ...primaryRow } : null;
            if (!primary) return primary;
            const secondary = secondaryRow || null;
            if (!Number.isFinite(primary.vr) && Number.isFinite(secondary?.vr)) primary.vr = secondary.vr;
            if (!Number.isFinite(primary.t) && Number.isFinite(secondary?.t)) primary.t = secondary.t;
            return primary;
        }

        // Merge all enabled files by (voltage, frequency) key.
        // Rule: at identical V/F points the selected master file always overrides others.
        function mergeData() {
            const map = new Map();
            const enabledFiles = getEnabledFiles();
            const masterFile = enabledFiles.find((file) => file.isMaster) || null;

            enabledFiles.filter((file) => !file.isMaster).forEach((file) => {
                file.data.forEach((row) => {
                    const key = makeVFKey(row.v, row.f);
                    const existing = map.get(key);
                    const candidate = {
                        ...row,
                        source: row.h > 4000 ? 'legacy_high' : 'archive',
                        sourceFileId: file.id,
                        sourceFileName: file.name
                    };
                    if (!existing) {
                        map.set(key, candidate);
                        return;
                    }

                    const candidateScore = calculateMergePriorityScore(candidate);
                    const existingScore = calculateMergePriorityScore(existing);
                    const preferCandidate = (
                        candidateScore > existingScore ||
                        (candidateScore === existingScore && candidate.h > existing.h)
                    );
                    if (preferCandidate) map.set(key, mergeTemperatureFields(candidate, existing));
                    else map.set(key, mergeTemperatureFields(existing, candidate));
                });
            });

            if (masterFile) {
                // Same V/F noktasinda master dosyasi daima son sozu soyler.
                masterFile.data.forEach((row) => {
                    const key = makeVFKey(row.v, row.f);
                    const existing = map.get(key);
                    const masterCandidate = {
                        ...row,
                        source: 'master',
                        sourceFileId: masterFile.id,
                        sourceFileName: masterFile.name
                    };
                    map.set(key, mergeTemperatureFields(masterCandidate, existing));
                });
            }

            consolidatedData = Array.from(map.values()).map((row) => ({
                ...row,
                score: calculateScore(row)
            })).sort((a, b) => b.score - a.score);
        }

        function collectRawFileTotals(files = rawFilesData) {
            return (Array.isArray(files) ? files : []).reduce((acc, file) => {
                const stats = (file && typeof file === 'object') ? (file.stats || {}) : {};
                acc.totalRows += toSafeNumber(stats.totalRows, 0);
                acc.parsedRows += toSafeNumber(stats.parsedRows, 0);
                acc.skippedRows += toSafeNumber(stats.skippedRows, 0);
                acc.missingVrRows += toSafeNumber(stats.missingVrRows, 0);
                acc.derivedHashRows += toSafeNumber(stats.derivedHashRows, 0);
                acc.derivedEffRows += toSafeNumber(stats.derivedEffRows, 0);
                acc.derivedPowerRows += toSafeNumber(stats.derivedPowerRows, 0);
                acc.missingErrRows += toSafeNumber(stats.missingErrRows, 0);
                acc.partialRows += toSafeNumber(stats.partialRows, 0);
                acc.truncatedRows += toSafeNumber(stats.truncatedRows, 0);
                if (Boolean(stats.parseTimedOut)) acc.parseTimedOutFiles += 1;
                return acc;
            }, {
                totalRows: 0,
                parsedRows: 0,
                skippedRows: 0,
                missingVrRows: 0,
                derivedHashRows: 0,
                derivedEffRows: 0,
                derivedPowerRows: 0,
                missingErrRows: 0,
                partialRows: 0,
                truncatedRows: 0,
                parseTimedOutFiles: 0
            });
        }

        function renderDataQualitySummary(processed = false) {
            if (!refs.dataQualitySummary) return;
            if (!rawFilesData.length) {
                refs.dataQualitySummary.innerHTML = `<div class="text-center">${escapeHtml(t('dataQuality.placeholder'))}</div>`;
                return;
            }
            const totals = collectRawFileTotals(rawFilesData);

            const enabledCount = rawFilesData.filter((file) => file.enabled).length;
            const warnings = rawFilesData.filter((file) => (
                file.stats.missingRequiredColumns.length > 0 ||
                file.stats.skippedRows > 0 ||
                file.stats.usedTempAsVr ||
                file.stats.missingVrRows > 0 ||
                file.stats.derivedHashRows > 0 ||
                file.stats.derivedEffRows > 0 ||
                file.stats.derivedPowerRows > 0 ||
                file.stats.partialRows > 0 ||
                file.stats.missingErrRows > 0 ||
                file.stats.truncatedRows > 0 ||
                file.stats.parseTimedOut
            ));

            refs.dataQualitySummary.innerHTML = `
                <div class="data-quality-pill-grid text-center">
                    <span class="dq-chip">${escapeHtml(t('summary.file', { count: rawFilesData.length }))}</span>
                    <span class="dq-chip">${escapeHtml(t('summary.active', { count: enabledCount }))}</span>
                    <span class="dq-chip">${escapeHtml(t('summary.totalRows', { count: totals.totalRows }))}</span>
                    <span class="dq-chip">${escapeHtml(t('summary.processed', { count: totals.parsedRows }))}</span>
                    <span class="dq-chip">${escapeHtml(t('summary.skipped', { count: totals.skippedRows }))}</span>
                    <span class="dq-chip">${escapeHtml(t('summary.vrMissing', { count: totals.missingVrRows }))}</span>
                    <span class="dq-chip">${escapeHtml(t('summary.hashDerived', { count: totals.derivedHashRows }))}</span>
                    <span class="dq-chip">${escapeHtml(t('summary.effDerived', { count: totals.derivedEffRows }))}</span>
                    <span class="dq-chip">${escapeHtml(t('summary.powerDerived', { count: totals.derivedPowerRows }))}</span>
                    <span class="dq-chip">${escapeHtml(t('summary.errMissing', { count: totals.missingErrRows }))}</span>
                    <span class="dq-chip">${escapeHtml(t('summary.partial', { count: totals.partialRows }))}</span>
                    <span class="dq-chip">${escapeHtml(t('summary.truncated', { count: totals.truncatedRows }))}</span>
                    <span class="dq-chip">${escapeHtml(t('summary.timeoutFiles', { count: totals.parseTimedOutFiles }))}</span>
                    ${processed ? `<span class="dq-chip">${escapeHtml(t('summary.merged', { count: consolidatedData.length }))}</span>` : ''}
                </div>
                ${warnings.length ? `
                    <ul class="dq-issues space-y-1 list-none text-center">
                        ${warnings.map((file) => {
                            const issues = [];
                            if (file.stats.missingRequiredColumns.length > 0) issues.push(t('issue.missingColumns', { columns: file.stats.missingRequiredColumns.map(formatMissingColumnLabel).join(', ') }));
                            if (file.stats.skippedRows > 0) issues.push(t('issue.rowsSkipped', { count: file.stats.skippedRows }));
                            if (file.stats.usedTempAsVr) issues.push(t('issue.vrFallback'));
                            if (file.stats.missingVrRows > 0 && !file.stats.usedTempAsVr) issues.push(t('issue.noVrTemp', { count: file.stats.missingVrRows }));
                            if (file.stats.derivedHashRows > 0) issues.push(t('issue.hashDerived', { count: file.stats.derivedHashRows }));
                            if (file.stats.derivedEffRows > 0) issues.push(t('issue.effDerived', { count: file.stats.derivedEffRows }));
                            if (file.stats.derivedPowerRows > 0) issues.push(t('issue.powerDerived', { count: file.stats.derivedPowerRows }));
                            if (file.stats.missingErrRows > 0) issues.push(t('issue.errDefault', { count: file.stats.missingErrRows }));
                            if (file.stats.partialRows > 0) issues.push(t('issue.partialRows', { count: file.stats.partialRows }));
                            if (file.stats.truncatedRows > 0) issues.push(t('issue.truncatedRows', { count: file.stats.truncatedRows }));
                            if (file.stats.parseTimedOut) issues.push(t('issue.parseTimeout', { seconds: Math.round(CSV_PARSE_TIME_BUDGET_MS / 1000) }));
                            return `<li class="dq-issue-item"><span class="dq-issue-file">${escapeHtml(file.name)}</span> - ${issues.join(' | ')}</li>`;
                        }).join('')}
                    </ul>
                ` : `<div class="dq-status-ok">${escapeHtml(t('issue.criticalNone'))}</div>`}
            `;
        }

        function renderFileList() {
            if (!refs.fileList || !refs.fileCountLabel) return;
            const files = [...getUserFileRecords()].sort((a, b) => b.lastModified - a.lastModified);
            refs.fileCountLabel.innerText = t('file.countLabel', { count: files.length });
            if (!files.length) {
                refs.fileList.innerHTML = '';
                return;
            }
            const rowsHtml = files.map((file) => {
                const hasMissingCols = file.stats.missingRequiredColumns.length > 0;
                const infoBits = [
                    t('file.rows', { count: file.stats.parsedRows }),
                    t('file.skipped', { count: file.stats.skippedRows })
                ];
                if (file.stats.usedTempAsVr) infoBits.push(t('file.vrFallbackShort'));
                if (file.stats.derivedHashRows > 0) infoBits.push(t('file.hashDerivedShort', { count: file.stats.derivedHashRows }));
                if (file.stats.derivedEffRows > 0) infoBits.push(t('file.effDerivedShort', { count: file.stats.derivedEffRows }));
                if (file.stats.partialRows > 0) infoBits.push(t('file.partialShort', { count: file.stats.partialRows }));
                if (file.stats.truncatedRows > 0) infoBits.push(t('file.truncatedShort', { count: file.stats.truncatedRows }));
                if (file.stats.parseTimedOut) infoBits.push(t('file.timeoutShort'));
                const info = hasMissingCols
                    ? t('file.missingColumns', { columns: file.stats.missingRequiredColumns.map(formatMissingColumnLabel).join(', '), details: infoBits.join(' • ') })
                    : infoBits.join(' • ');
                return `
                    <div class="upload-file-row flex items-center bg-dark-950 p-3 rounded-lg border border-dark-800 gap-2 ${file.enabled ? '' : 'opacity-50'}">
                    <button type="button" data-action="remove-file" data-file-id="${escapeHtml(file.id)}" class="shrink-0 w-7 h-7 rounded-full border border-dark-700 text-slate-400 hover:text-neon-red hover:border-neon-red/70 transition flex items-center justify-center" title="${escapeHtml(t('file.removeTitle'))}">✕</button>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <div class="w-2 h-2 rounded-full ${file.isMaster ? 'bg-neon-purple' : 'bg-slate-600'}"></div>
                            <div class="text-xs text-white truncate max-w-[220px]">${escapeHtml(file.name)}</div>
                        </div>
                        <div class="text-[10px] ${hasMissingCols ? 'text-neon-red' : 'text-slate-500'} mt-1">${escapeHtml(info)}</div>
                    </div>
                    <label class="file-master-toggle ${file.isMaster ? 'is-selected' : ''} flex items-center justify-center gap-2 cursor-pointer shrink-0 ml-auto">
                        <input type="radio" name="masterFile" value="${escapeHtml(file.id)}" ${file.isMaster ? 'checked' : ''} class="w-4 h-4 text-neon-purple bg-dark-800 border-gray-600" ${file.enabled ? '' : 'disabled'}>
                        <span class="text-[11px] text-slate-300 font-bold tracking-wide">${escapeHtml(t('file.master'))}</span>
                    </label>
                    </div>
                `;
            });
            refs.fileList.innerHTML = rowsHtml.join('');
        }

        // Render top KPI cards (master candidate / maximum hash / best efficiency).
        function renderKPI() {
            const containerIds = ['kpi-master', 'kpi-power', 'kpi-eff'];
            if (!consolidatedData.length) {
                containerIds.forEach((id) => {
                    const el = document.getElementById(id);
                    if (el) el.innerHTML = `<div class="text-sm text-slate-500">${escapeHtml(t('kpi.dataWaiting'))}</div>`;
                });
                return;
            }
            const maxHash = [...consolidatedData].sort((a, b) => b.h - a.h)[0] || consolidatedData[0];
            const masterCandidate = getMasterSelectionRow() || consolidatedData[0];
            const bestEff = consolidatedData
                .filter((row) => row.h > 2000 && Number.isFinite(row.e))
                .sort((a, b) => a.e - b.e)[0] || masterCandidate;
            const currentPalette = getCurrentThemePalette();

            const card = (id, title, row, palette, icon) => {
                const el = document.getElementById(id);
                if (!el) return;
                const errorClass = Number.isFinite(row.err)
                    ? (row.err <= 0.5 ? 'text-neon-green' : (row.err <= 1.5 ? 'text-neon-amber' : 'text-neon-red'))
                    : 'text-slate-400';
                el.innerHTML = `
                    <div class="absolute right-0 top-0 p-4 opacity-10">${icon}</div>
                    <div class="flex items-center gap-3 mb-4">
                        <div class="p-2 rounded-lg" style="background:${palette.bg};color:${palette.accent};">${icon}</div>
                        <div class="text-[15px] font-bold uppercase tracking-wide leading-tight" style="color:${palette.accent};">${title}</div>
                    </div>
                    <div class="text-4xl font-black text-white mb-2">${formatValue(row.h, 0)} <span class="text-sm text-slate-500">GH/s</span></div>
                    <div class="flex flex-nowrap items-start justify-start gap-x-3 text-xs text-slate-400 pt-2 border-t border-white/5">
                        <div class="shrink-0 text-left space-y-0.5">
                            <span class="block text-[9px] text-slate-500 uppercase whitespace-nowrap">${escapeHtml(t('kpi.voltage'))}</span>
                            <div class="text-white whitespace-nowrap">${formatCompact(row.v)}mV</div>
                        </div>
                        <div class="shrink-0 text-left space-y-0.5">
                            <span class="block text-[9px] text-slate-500 uppercase whitespace-nowrap">${escapeHtml(t('kpi.frequency'))}</span>
                            <div class="text-white whitespace-nowrap">${formatTrunc(row.f)}MHz</div>
                        </div>
                        <div class="shrink-0 text-left space-y-0.5">
                            <span class="block text-[9px] text-slate-500 uppercase whitespace-nowrap">${escapeHtml(t('kpi.efficiency'))}</span>
                            <div class="text-neon-blue whitespace-nowrap">${formatValue(row.e, 2)}</div>
                        </div>
                        <div class="shrink-0 text-left space-y-0.5">
                            <span class="block text-[9px] text-slate-500 uppercase whitespace-nowrap">${escapeHtml(t('table.col.error'))}</span>
                            <div class="${errorClass} whitespace-nowrap">${formatValue(row.err, 2)}%</div>
                        </div>
                    </div>
                `;
            };
            const iStar = '<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path></svg>';
            const iBolt = '<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>';
            const iLeaf = '<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path></svg>';
            card('kpi-master', t('kpi.masterSelection'), masterCandidate, currentPalette.kpi, iStar);
            card('kpi-power', t('kpi.maxHash'), maxHash, { accent: '#ef4444', bg: 'rgba(239,68,68,0.15)' }, iBolt);
            card('kpi-eff', t('kpi.bestEfficiency'), bestEff, { accent: '#10b981', bg: 'rgba(16,185,129,0.15)' }, iLeaf);
        }

        function renderAATE() {
            const tbody = document.getElementById('aate-table-body');
            if (!tbody) return;
            const best = getMasterSelectionRow();
            if (!best) {
                tbody.innerHTML = `<tr><td colspan="4" class="py-3 text-center text-slate-500">${escapeHtml(t('aate.waiting'))}</td></tr>`;
                return;
            }
            const targets = [
                { p: t('aate.target.eff'), ref: '< 17.00', val: formatValue(best.e, 2), good: best.e < 17 },
                { p: t('aate.target.hash'), ref: '3000 - 3800', val: formatValue(best.h, 0), good: best.h >= 3000 && best.h <= 3800 },
                { p: t('aate.target.voltage'), ref: '1260 - 1340', val: formatCompact(best.v), good: best.v >= 1260 && best.v <= 1340 },
                { p: t('aate.target.freq'), ref: '760 - 930', val: formatCompact(best.f), good: best.f >= 760 && best.f <= 930 },
                { p: t('aate.target.vrm'), ref: '< 60°C', val: Number.isFinite(best.vr) ? `${formatCompact(best.vr)}°C` : '—', good: Number.isFinite(best.vr) ? best.vr < 60 : null },
                { p: t('aate.target.err'), ref: '< 0.5%', val: `${formatValue(best.err, 2)}%`, good: best.err < 0.5 }
            ];
            tbody.innerHTML = targets.map((item) => {
                const cls = item.good === null ? 'bg-slate-700/40 text-slate-300' : (item.good ? 'bg-neon-green/20 text-neon-green' : 'bg-neon-red/20 text-neon-red');
                const txt = item.good === null ? t('aate.status.na') : (item.good ? t('aate.status.ok') : t('aate.status.deviation'));
                return `
                    <tr>
                        <td class="py-2 border-b border-dark-700/50">${item.p}</td>
                        <td class="py-2 border-b border-dark-700/50 text-right font-mono text-slate-400">${item.ref}</td>
                        <td class="py-2 border-b border-dark-700/50 text-right font-mono text-white font-bold">${item.val}</td>
                        <td class="py-2 border-b border-dark-700/50 text-center"><span class="px-2 py-0.5 rounded text-[10px] ${cls}">${txt}</span></td>
                    </tr>
                `;
            }).join('');
        }

        function formatEliteMetricValue(value, digits = 0) {
            if (!Number.isFinite(value)) return '—';
            if (digits <= 0) return formatValue(value, 0);
            return formatCompact(value, digits).replace(/\.0+$/, '');
        }

        function setEliteMetricValue(metric, value) {
            if (!metric || !metric.targetId) return;
            const el = document.getElementById(metric.targetId);
            if (!el) return;
            if (!Number.isFinite(value)) {
                el.textContent = '—';
                return;
            }
            el.innerHTML = `${escapeHtml(formatEliteMetricValue(value, metric.digits))}<span class="elite-radar-unit">${escapeHtml(metric.unit)}</span>`;
        }

        function ensureEliteRadarTooltip() {
            if (eliteRadarTooltipEl && document.body.contains(eliteRadarTooltipEl)) return eliteRadarTooltipEl;
            const el = document.createElement('div');
            el.className = 'elite-radar-tooltip';
            el.setAttribute('aria-hidden', 'true');
            document.body.appendChild(el);
            eliteRadarTooltipEl = el;
            return el;
        }

        function positionEliteRadarTooltip(clientX, clientY) {
            const el = ensureEliteRadarTooltip();
            const margin = 10;
            const offset = 14;
            const width = el.offsetWidth || 160;
            const height = el.offsetHeight || 30;
            let left = clientX + offset;
            let top = clientY + offset;
            if (left + width + margin > window.innerWidth) left = clientX - width - offset;
            if (top + height + margin > window.innerHeight) top = clientY - height - offset;
            if (left < margin) left = margin;
            if (top < margin) top = margin;
            el.style.left = `${Math.round(left)}px`;
            el.style.top = `${Math.round(top)}px`;
        }

        function showEliteRadarTooltip(text, clientX, clientY) {
            const el = ensureEliteRadarTooltip();
            el.textContent = String(text || '');
            positionEliteRadarTooltip(clientX, clientY);
            el.classList.add('is-visible');
        }

        function hideEliteRadarTooltip() {
            if (!eliteRadarTooltipEl) return;
            eliteRadarTooltipEl.classList.remove('is-visible');
        }

        function getEliteRadarPoint(cx, cy, radius, index, total) {
            const angle = (-Math.PI / 2) + ((Math.PI * 2) * (index / total));
            return {
                x: cx + (Math.cos(angle) * radius),
                y: cy + (Math.sin(angle) * radius)
            };
        }

        function buildEliteRadarPath(points) {
            if (!Array.isArray(points) || !points.length) return '';
            return `${points.map((point, index) => (
                `${index === 0 ? 'M' : 'L'} ${point.x.toFixed(2)} ${point.y.toFixed(2)}`
            )).join(' ')} Z`;
        }

        function drawEliteRadar(maxValues = {}) {
            const svg = document.getElementById('elite-radar-svg');
            if (!svg) return;
            const isLightTheme = document.documentElement.classList.contains('light-theme');
            const rect = svg.getBoundingClientRect();
            const width = Math.max(300, Math.round(rect.width || 360));
            const height = Math.max(260, Math.round(rect.height || 320));
            const cx = (width * 0.40) - 20;
            const cy = height * 0.495;
            const outer = Math.max(90, Math.min(width * 0.405, height * 0.395));
            const labelOffset = 10;
            const ringCount = 6;
            const ns = 'http://www.w3.org/2000/svg';
            const gradientId = 'elite-radar-gradient';
            const glowId = 'elite-radar-glow';
            const axisLabels = ELITE_RADAR_METRICS.map((metric) => metric.axis);
            const ringFillEven = isLightTheme ? 'rgba(255,255,255,0.56)' : 'rgba(11,19,32,0.24)';
            const ringFillOdd = isLightTheme ? 'rgba(248,250,252,0.42)' : 'rgba(11,19,32,0.40)';
            const ringStroke = isLightTheme ? 'rgba(100,116,139,0.3)' : 'rgba(148,163,184,0.22)';
            const axisStroke = isLightTheme ? 'rgba(100,116,139,0.32)' : 'rgba(148,163,184,0.22)';
            const labelColor = isLightTheme ? '#475569' : '#94a3b8';
            const areaFill = isLightTheme ? 'rgba(59,130,246,0.1)' : 'rgba(255,255,255,0.06)';
            const normalized = ELITE_RADAR_METRICS.map((metric) => {
                const raw = maxValues[metric.key];
                if (!Number.isFinite(raw) || !Number.isFinite(metric.cap) || metric.cap <= 0) return 0;
                return clamp01(raw / metric.cap);
            });
            hideEliteRadarTooltip();

            svg.innerHTML = '';
            svg.setAttribute('viewBox', `0 0 ${width} ${height}`);

            const defs = document.createElementNS(ns, 'defs');
            const gradient = document.createElementNS(ns, 'linearGradient');
            gradient.setAttribute('id', gradientId);
            gradient.setAttribute('x1', '0%');
            gradient.setAttribute('y1', '0%');
            gradient.setAttribute('x2', '100%');
            gradient.setAttribute('y2', '100%');
            [
                { offset: '0%', color: '#ef4444' },
                { offset: '48%', color: '#f97316' },
                { offset: '100%', color: '#eab308' }
            ].forEach((item) => {
                const stop = document.createElementNS(ns, 'stop');
                stop.setAttribute('offset', item.offset);
                stop.setAttribute('stop-color', item.color);
                gradient.appendChild(stop);
            });
            defs.appendChild(gradient);

            const filter = document.createElementNS(ns, 'filter');
            filter.setAttribute('id', glowId);
            const blur = document.createElementNS(ns, 'feGaussianBlur');
            blur.setAttribute('stdDeviation', '3.2');
            blur.setAttribute('result', 'blurred');
            filter.appendChild(blur);
            const merge = document.createElementNS(ns, 'feMerge');
            const mergeBlur = document.createElementNS(ns, 'feMergeNode');
            mergeBlur.setAttribute('in', 'blurred');
            const mergeSource = document.createElementNS(ns, 'feMergeNode');
            mergeSource.setAttribute('in', 'SourceGraphic');
            merge.appendChild(mergeBlur);
            merge.appendChild(mergeSource);
            filter.appendChild(merge);
            defs.appendChild(filter);
            svg.appendChild(defs);

            for (let ring = ringCount; ring >= 1; ring -= 1) {
                const radius = outer * (ring / ringCount);
                const ringPoints = axisLabels.map((_, index) => getEliteRadarPoint(cx, cy, radius, index, axisLabels.length));
                const ringPath = document.createElementNS(ns, 'path');
                ringPath.setAttribute('d', buildEliteRadarPath(ringPoints));
                ringPath.setAttribute('fill', ring % 2 === 0 ? ringFillEven : ringFillOdd);
                ringPath.setAttribute('stroke', ringStroke);
                ringPath.setAttribute('stroke-width', '1');
                svg.appendChild(ringPath);
            }

            axisLabels.forEach((label, index) => {
                const point = getEliteRadarPoint(cx, cy, outer, index, axisLabels.length);
                const axis = document.createElementNS(ns, 'line');
                axis.setAttribute('x1', cx);
                axis.setAttribute('y1', cy);
                axis.setAttribute('x2', point.x);
                axis.setAttribute('y2', point.y);
                axis.setAttribute('stroke', axisStroke);
                axis.setAttribute('stroke-width', '1');
                svg.appendChild(axis);

                const labelPoint = getEliteRadarPoint(cx, cy, outer + labelOffset, index, axisLabels.length);
                const text = document.createElementNS(ns, 'text');
                text.setAttribute('x', labelPoint.x.toFixed(2));
                text.setAttribute('y', labelPoint.y.toFixed(2));
                text.setAttribute('fill', labelColor);
                text.setAttribute('font-size', '10');
                text.setAttribute('font-weight', '800');
                text.setAttribute('letter-spacing', '.04em');
                text.setAttribute('text-anchor', labelPoint.x < cx - 6 ? 'end' : (labelPoint.x > cx + 6 ? 'start' : 'middle'));
                text.setAttribute('dominant-baseline', labelPoint.y < cy ? 'auto' : 'hanging');
                text.textContent = label;
                svg.appendChild(text);
            });

            const valuePoints = normalized.map((value, index) => (
                getEliteRadarPoint(cx, cy, outer * value, index, normalized.length)
            ));
            const valuePath = buildEliteRadarPath(valuePoints);

            const glowPath = document.createElementNS(ns, 'path');
            glowPath.setAttribute('d', valuePath);
            glowPath.setAttribute('fill', 'none');
            glowPath.setAttribute('stroke', `url(#${gradientId})`);
            glowPath.setAttribute('stroke-width', '3.8');
            glowPath.setAttribute('opacity', '0.58');
            glowPath.setAttribute('filter', `url(#${glowId})`);
            svg.appendChild(glowPath);

            const areaPath = document.createElementNS(ns, 'path');
            areaPath.setAttribute('d', valuePath);
            areaPath.setAttribute('fill', areaFill);
            areaPath.setAttribute('stroke', `url(#${gradientId})`);
            areaPath.setAttribute('stroke-width', '2.8');
            areaPath.setAttribute('stroke-linejoin', 'round');
            svg.appendChild(areaPath);

            valuePoints.forEach((point, index) => {
                const metric = ELITE_RADAR_METRICS[index];
                const rawValue = maxValues[metric.key];
                const tooltipValue = formatEliteMetricValue(rawValue, metric.digits);
                const tooltipText = `MAX ${metric.axis}: ${tooltipValue} ${metric.unit}`;
                const toScreenPoint = () => {
                    const svgRect = svg.getBoundingClientRect();
                    const ratioX = width > 0 ? (point.x / width) : 0;
                    const ratioY = height > 0 ? (point.y / height) : 0;
                    return {
                        x: svgRect.left + (svgRect.width * ratioX),
                        y: svgRect.top + (svgRect.height * ratioY)
                    };
                };
                const onEnter = (event) => {
                    showEliteRadarTooltip(tooltipText, event.clientX, event.clientY);
                };
                const onMove = (event) => {
                    positionEliteRadarTooltip(event.clientX, event.clientY);
                };
                const onFocus = () => {
                    const p = toScreenPoint();
                    showEliteRadarTooltip(tooltipText, p.x, p.y);
                };
                const onTouchStart = (event) => {
                    const touch = event.touches && event.touches[0];
                    if (!touch) return;
                    showEliteRadarTooltip(tooltipText, touch.clientX, touch.clientY);
                };
                const onTouchMove = (event) => {
                    const touch = event.touches && event.touches[0];
                    if (!touch) return;
                    positionEliteRadarTooltip(touch.clientX, touch.clientY);
                };

                const hitCircle = document.createElementNS(ns, 'circle');
                hitCircle.setAttribute('cx', point.x.toFixed(2));
                hitCircle.setAttribute('cy', point.y.toFixed(2));
                hitCircle.setAttribute('r', '14');
                hitCircle.setAttribute('fill', 'rgba(255,255,255,0.001)');
                hitCircle.setAttribute('stroke', 'none');
                hitCircle.setAttribute('pointer-events', 'all');
                hitCircle.style.cursor = 'pointer';
                hitCircle.setAttribute('tabindex', '0');
                hitCircle.addEventListener('mouseenter', onEnter);
                hitCircle.addEventListener('mousemove', onMove);
                hitCircle.addEventListener('mouseleave', hideEliteRadarTooltip);
                hitCircle.addEventListener('focus', onFocus);
                hitCircle.addEventListener('blur', hideEliteRadarTooltip);
                hitCircle.addEventListener('touchstart', onTouchStart, { passive: true });
                hitCircle.addEventListener('touchmove', onTouchMove, { passive: true });
                hitCircle.addEventListener('touchend', hideEliteRadarTooltip, { passive: true });
                svg.appendChild(hitCircle);

                const marker = document.createElementNS(ns, 'circle');
                marker.setAttribute('cx', point.x.toFixed(2));
                marker.setAttribute('cy', point.y.toFixed(2));
                marker.setAttribute('r', '3.4');
                marker.setAttribute('fill', '#f8fafc');
                marker.setAttribute('stroke', `url(#${gradientId})`);
                marker.setAttribute('stroke-width', '1.25');
                marker.style.cursor = 'pointer';
                marker.setAttribute('pointer-events', 'none');
                svg.appendChild(marker);
            });
        }

        function renderEliteStats() {
            const radarSvg = document.getElementById('elite-radar-svg');
            if (!radarSvg) return;
            const maxValues = ELITE_RADAR_METRICS.reduce((acc, metric) => {
                acc[metric.key] = NaN;
                return acc;
            }, {});

            consolidatedData.forEach((row) => {
                ELITE_RADAR_METRICS.forEach((metric) => {
                    const value = asNumber(row[metric.key]);
                    if (!Number.isFinite(value)) return;
                    if (!Number.isFinite(maxValues[metric.key]) || value > maxValues[metric.key]) {
                        maxValues[metric.key] = value;
                    }
                });
            });

            ELITE_RADAR_META_ORDER.forEach((key) => {
                setEliteMetricValue(ELITE_RADAR_METRIC_BY_KEY[key], maxValues[key]);
            });
            drawEliteRadar(maxValues);
        }

        function getHeatColor(value, min, max) {
            if (!Number.isFinite(value) || !Number.isFinite(min) || !Number.isFinite(max)) return 'rgba(148,163,184,0.6)';
            const ratio = max === min ? 0.5 : (value - min) / (max - min);
            const clamped = Math.min(1, Math.max(0, ratio));
            const hue = (1 - clamped) * 210;
            return `hsla(${hue}, 90%, 55%, 0.85)`;
        }

        // Build all dashboard charts from current consolidated dataset.
        function initCharts() {
            if (typeof window.Chart !== 'function') return;
            cancelScheduledChartRender();
            clearChartInstances();

            const pointLimit = getChartPointLimit();
            const perfMode = isChartPerfModeEnabled();
            const masterAll = consolidatedData.filter((row) => row.source === 'master');
            const highAll = consolidatedData.filter((row) => row.source === 'legacy_high');
            const otherAll = consolidatedData.filter((row) => !['master', 'legacy_high'].includes(row.source));

            const master = downsampleRows(masterAll, pointLimit);
            const high = downsampleRows(highAll, pointLimit);
            const other = downsampleRows(otherAll, pointLimit);
            const allEffRows = downsampleRows(
                consolidatedData
                    .filter((row) => Number.isFinite(row.e) && Number.isFinite(row.h))
                    .map((row) => ({
                        x: row.h,
                        y: row.e,
                        hash: row.h,
                        err: Number.isFinite(row.err) ? row.err : 0
                    })),
                Math.max(420, pointLimit * 2)
            );
            const allVrRows = downsampleRows(
                consolidatedData
                    .filter((row) => Number.isFinite(row.vr) && Number.isFinite(row.v))
                    .map((row) => ({
                        x: row.v,
                        y: row.vr,
                        hash: row.h,
                        err: Number.isFinite(row.err) ? row.err : 0
                    })),
                Math.max(420, pointLimit * 2)
            );
            const vrLineXMin = allVrRows.length ? Math.min(...allVrRows.map((row) => row.x)) : 1050;
            const vrLineXMax = allVrRows.length ? Math.max(...allVrRows.map((row) => row.x)) : 1450;
            const bubbleSize = (hash) => Math.max(4.2, Math.min(8.4, hash / 867));
            const sortedByHash = downsampleRows(
                [...consolidatedData].sort((a, b) => a.h - b.h),
                Math.max(220, pointLimit * 2)
            );

            const bucketRows = perfMode
                ? downsampleRows(consolidatedData, Math.max(600, pointLimit * 2))
                : consolidatedData;
            const buckets = {};
            bucketRows.forEach((row) => {
                const bucket = Math.floor(row.f / 50) * 50;
                if (!buckets[bucket]) buckets[bucket] = { sum: 0, count: 0 };
                buckets[bucket].sum += row.h;
                buckets[bucket].count += 1;
            });
            const labels = Object.keys(buckets).map(Number).sort((a, b) => a - b);

            const heatRows = perfMode
                ? downsampleRows(consolidatedData, Math.max(700, pointLimit * 2))
                : consolidatedData;
            const vfMap = new Map();
            heatRows.forEach((row) => {
                const v = Math.round(row.v);
                const f = Math.round(row.f);
                const key = `${v}-${f}`;
                if (!vfMap.has(key)) vfMap.set(key, { v, f, sum: 0, count: 0 });
                const bucket = vfMap.get(key);
                bucket.sum += row.h;
                bucket.count += 1;
            });
            const heatPoints = downsampleRows(
                Array.from(vfMap.values()).map((bucket) => ({
                    x: bucket.f,
                    y: bucket.v,
                    r: 4,
                    hash: bucket.sum / bucket.count
                })),
                Math.max(320, pointLimit)
            );
            const hashes = heatPoints.map((point) => point.hash);
            const minHash = hashes.length ? Math.min(...hashes) : 0;
            const maxHash = hashes.length ? Math.max(...hashes) : 1;
            const routeRows = perfMode
                ? downsampleRows(consolidatedData, Math.max(pointLimit * 2, 900))
                : consolidatedData;
            const routeByFreqBucket = new Map();
            routeRows.forEach((row) => {
                if (!Number.isFinite(row.f) || !Number.isFinite(row.v)) return;
                if (!Number.isFinite(row.err)) return;
                const bucket = Math.round(row.f / 20) * 20;
                const prev = routeByFreqBucket.get(bucket);
                if (!prev || row.err < prev.err || (row.err === prev.err && row.h > prev.h)) {
                    routeByFreqBucket.set(bucket, row);
                }
            });
            const lowErrorRoute = Array.from(routeByFreqBucket.entries())
                .sort((a, b) => a[0] - b[0])
                .map(([, row]) => ({
                    x: row.f,
                    y: row.v,
                    err: row.err,
                    hash: row.h
                }));
            const themePalette = getCurrentThemePalette();
            const freqPalette = themePalette.frequency;
            const mainPointColor = (err, alpha = 0.82) => {
                const safeErr = Number.isFinite(err) ? Math.min(6, Math.max(0, err)) : 0;
                const hue = (1 - (safeErr / 6)) * 140;
                return `hsla(${hue}, 90%, 56%, ${alpha})`;
            };
            const toMainPoint = (row, sourceLabel) => ({
                x: row.f,
                y: row.v,
                r: bubbleSize(row.h),
                hash: row.h,
                err: Number.isFinite(row.err) ? row.err : 0,
                sourceLabel
            });

            const chartTasks = [
                () => createChart('mainScatterChart', {
                    type: 'bubble',
                    data: {
                        datasets: [
                            {
                                label: t('chart.master'),
                                data: master.map((row) => toMainPoint(row, t('chart.master'))),
                                backgroundColor: (ctx) => mainPointColor(ctx.raw?.err, 0.92),
                                borderColor: 'rgba(255,255,255,0.9)',
                                borderWidth: 1.05,
                                pointHitRadius: 16
                            },
                            {
                                label: t('chart.highPerf'),
                                data: high.map((row) => toMainPoint(row, t('chart.highPerf'))),
                                backgroundColor: (ctx) => mainPointColor(ctx.raw?.err, 0.84),
                                borderColor: 'rgba(255,255,255,0.72)',
                                borderWidth: 0.95,
                                pointHitRadius: 15
                            },
                            {
                                label: t('chart.archive'),
                                data: other.map((row) => toMainPoint(row, t('chart.archive'))),
                                backgroundColor: (ctx) => mainPointColor(ctx.raw?.err, 0.7),
                                borderColor: 'rgba(255,255,255,0.5)',
                                borderWidth: 0.9,
                                pointHitRadius: 14
                            },
                            {
                                type: 'line',
                                label: 'Low-Error Route',
                                data: lowErrorRoute,
                                borderColor: '#22d3ee',
                                backgroundColor: 'rgba(34,211,238,0.22)',
                                borderWidth: 2.2,
                                pointRadius: 0,
                                pointHoverRadius: 0,
                                tension: 0.28,
                                fill: false
                            },
                            {
                                type: 'scatter',
                                label: 'Route Nodes',
                                data: lowErrorRoute,
                                pointRadius: 6.3,
                                pointHoverRadius: 6.3,
                                pointHitRadius: 18,
                                backgroundColor: '#22d3ee',
                                borderColor: '#ecfeff',
                                borderWidth: 1.1
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        elements: {
                            point: {
                                hitRadius: 14,
                                hoverRadius: 0
                            }
                        },
                        scales: {
                            x: { title: { display: true, text: t('chart.freqAxis') } },
                            y: { title: { display: true, text: t('chart.voltAxis') } }
                        },
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: (ctx) => {
                                        const raw = ctx.raw || {};
                                        const parsedX = Number.isFinite(ctx.parsed?.x) ? ctx.parsed.x : raw.x;
                                        const parsedY = Number.isFinite(ctx.parsed?.y) ? ctx.parsed.y : raw.y;
                                        const parts = [`${t('chart.hashTooltip')}: ${formatValue(raw.hash, 0)} GH/s`];
                                        if (Number.isFinite(raw.err)) parts.push(`Err: ${formatValue(raw.err, 2)}%`);
                                        if (Number.isFinite(parsedX) && Number.isFinite(parsedY)) {
                                            parts.push(`${formatValue(parsedX, 0)} MHz / ${formatValue(parsedY, 0)} mV`);
                                        }
                                        if (raw.sourceLabel) parts.push(raw.sourceLabel);
                                        return parts.join(' | ');
                                    }
                                }
                            }
                        }
                    }
                }),
                () => createChart('powerChart', {
                    type: 'line',
                    data: {
                        labels: sortedByHash.map((row) => formatValue(row.h, 0)),
                        datasets: [{
                            label: t('chart.power'),
                            data: sortedByHash.map((row) => row.p || 0),
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16,185,129,0.25)',
                            fill: true,
                            pointRadius: 0,
                            tension: 0.35
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, scales: { x: { display: false } }, plugins: { legend: { display: false } } }
                }),
                () => createChart('effChart', {
                    type: 'scatter',
                    data: {
                        datasets: [{
                            label: t('chart.efficiency'),
                            data: allEffRows,
                            backgroundColor: (ctx) => mainPointColor(ctx.raw?.err, 0.86),
                            borderColor: 'rgba(255,255,255,0)',
                            borderWidth: 0,
                            pointRadius: 3.1,
                            pointHoverRadius: 3.6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            x: { title: { display: true, text: t('chart.hashrateAxis') } },
                            y: { title: { display: true, text: 'J/TH' }, reverse: true }
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: (ctx) => {
                                        const raw = ctx.raw || {};
                                        const px = Number.isFinite(ctx.parsed?.x) ? ctx.parsed.x : raw.x;
                                        const py = Number.isFinite(ctx.parsed?.y) ? ctx.parsed.y : raw.y;
                                        const parts = [];
                                        if (Number.isFinite(raw.hash)) parts.push(`${t('chart.hashTooltip')}: ${formatValue(raw.hash, 0)} GH/s`);
                                        if (Number.isFinite(raw.err)) parts.push(`Err: ${formatValue(raw.err, 2)}%`);
                                        if (Number.isFinite(px) && Number.isFinite(py)) parts.push(`${formatValue(px, 0)} GH/s / ${formatValue(py, 2)} J/TH`);
                                        return parts.join(' | ');
                                    }
                                }
                            }
                        }
                    }
                }),
                () => createChart('tempChart', {
                    type: 'scatter',
                    data: {
                        datasets: [
                            {
                                label: t('chart.tempAxis'),
                                data: allVrRows,
                                backgroundColor: (ctx) => {
                                    const y = Number(ctx.raw?.y);
                                    if (!Number.isFinite(y)) return '#94a3b8';
                                    if (y > 70) return '#ef4444';
                                    if (y > 60) return '#f59e0b';
                                    return '#10b981';
                                },
                                pointRadius: 4.1,
                                pointHoverRadius: 4.9
                            },
                            {
                                type: 'line',
                                label: '60C',
                                data: [{ x: vrLineXMin, y: 60 }, { x: vrLineXMax, y: 60 }],
                                borderColor: 'rgba(163,230,53,0.34)',
                                borderDash: [6, 4],
                                borderWidth: 1.2,
                                pointRadius: 0,
                                fill: false
                            },
                            {
                                type: 'line',
                                label: '70C',
                                data: [{ x: vrLineXMin, y: 70 }, { x: vrLineXMax, y: 70 }],
                                borderColor: 'rgba(239,68,68,0.38)',
                                borderDash: [6, 4],
                                borderWidth: 1.2,
                                pointRadius: 0,
                                fill: false
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: {
                            padding: {
                                top: -7
                            }
                        },
                        scales: {
                            x: { title: { display: true, text: t('chart.voltAxis') } },
                            y: { title: { display: true, text: t('chart.tempAxis') } }
                        }
                    }
                }),
                () => createChart('freqBarChart', {
                    type: 'bar',
                    data: (() => {
                        const freqLabels = labels.map((label) => `${label}-${label + 50}`);
                        const freqValues = labels.map((key) => buckets[key].sum / buckets[key].count);
                        const freqCounts = labels.map((key) => buckets[key].count);
                        const freqMoving = freqValues.map((_, index) => {
                            const slice = freqValues.slice(Math.max(0, index - 1), Math.min(freqValues.length, index + 2));
                            return slice.reduce((sum, value) => sum + value, 0) / Math.max(1, slice.length);
                        });
                        const peakPoints = freqValues
                            .map((value, index) => ({ index, value }))
                            .sort((a, b) => b.value - a.value)
                            .slice(0, 3)
                            .map((item) => ({
                                x: freqLabels[item.index],
                                y: item.value,
                                hash: item.value
                            }));
                        return {
                            labels: freqLabels,
                            datasets: [
                                {
                                    type: 'bar',
                                    label: 'Samples',
                                    data: freqCounts,
                                    yAxisID: 'y1',
                                    backgroundColor: (ctx) => {
                                        const chart = ctx.chart;
                                        const area = chart?.chartArea;
                                        if (!area) return freqPalette.barStart;
                                        const grad = chart.ctx.createLinearGradient(0, area.bottom, 0, area.top);
                                        grad.addColorStop(0, freqPalette.barEnd);
                                        grad.addColorStop(1, freqPalette.barStart);
                                        return grad;
                                    },
                                    borderColor: freqPalette.barBorder,
                                    borderWidth: 0.55,
                                    borderRadius: 7,
                                    barPercentage: 0.72,
                                    categoryPercentage: 0.82
                                },
                                {
                                type: 'line',
                                label: 'Pulse Glow',
                                data: freqValues,
                                yAxisID: 'y',
                                borderColor: freqPalette.glow,
                                borderWidth: 9,
                                pointRadius: 0,
                                pointHoverRadius: 0,
                                tension: 0.36,
                                fill: false
                                },
                                {
                                type: 'line',
                                label: t('chart.avgHash'),
                                data: freqValues,
                                yAxisID: 'y',
                                borderColor: freqPalette.line,
                                borderWidth: 2.5,
                                pointRadius: 2.4,
                                pointHoverRadius: 4.4,
                                pointBackgroundColor: freqPalette.point,
                                pointBorderColor: '#fdf2f8',
                                pointBorderWidth: 0.7,
                                tension: 0.36,
                                fill: true,
                                backgroundColor: (ctx) => {
                                    const chart = ctx.chart;
                                    const area = chart?.chartArea;
                                    if (!area) return freqPalette.areaFallback;
                                    const grad = chart.ctx.createLinearGradient(0, area.bottom, 0, area.top);
                                    grad.addColorStop(0, freqPalette.areaStart);
                                    grad.addColorStop(1, freqPalette.areaEnd);
                                    return grad;
                                }
                            },
                                {
                                    type: 'line',
                                    label: 'Trend',
                                    data: freqMoving,
                                    yAxisID: 'y',
                                    borderColor: 'rgba(245,158,11,0.9)',
                                    borderWidth: 1.8,
                                    borderDash: [4, 4],
                                    pointRadius: 0,
                                    pointHoverRadius: 0,
                                    tension: 0.24,
                                    fill: false
                                },
                                {
                                    type: 'scatter',
                                    label: 'Peaks',
                                    data: peakPoints,
                                    yAxisID: 'y',
                                    pointRadius: 5.1,
                                    pointHoverRadius: 6.2,
                                    pointHitRadius: 12,
                                    backgroundColor: '#fb7185',
                                    borderColor: '#ffe4e6',
                                    borderWidth: 1
                                }
                            ]
                        };
                    })(),
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false
                        },
                        scales: {
                            x: {
                                title: { display: true, text: t('chart.freqAxis') },
                                ticks: { color: '#a5b4fc', maxRotation: 0, minRotation: 0 },
                                grid: { color: 'rgba(148,163,184,0.16)' }
                            },
                            y: {
                                title: { display: true, text: t('chart.hashrateAxis') },
                                ticks: { color: '#cbd5e1' },
                                grid: { color: freqPalette.grid }
                            },
                            y1: {
                                position: 'right',
                                title: { display: true, text: 'Samples' },
                                ticks: { color: '#a1a1aa' },
                                grid: { drawOnChartArea: false, color: 'rgba(148,163,184,0.12)' }
                            }
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: (ctx) => {
                                        const yAxisId = String(ctx.dataset?.yAxisID || 'y');
                                        const value = formatValue(ctx.parsed?.y, 0);
                                        if (yAxisId === 'y1') {
                                            return `${ctx.dataset.label || 'Samples'}: ${value}`;
                                        }
                                        return `${ctx.dataset.label || t('chart.avgHash')}: ${value} GH/s`;
                                    }
                                }
                            }
                        }
                    }
                }),
                () => createChart('vfHeatmapChart', {
                    type: 'bubble',
                    data: {
                        datasets: [{
                            label: t('chart.vfHeatmap'),
                            data: heatPoints,
                            backgroundColor: (ctx) => getHeatColor(ctx.raw?.hash, minHash, maxHash),
                            borderColor: 'rgba(11,19,32,0.8)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            x: { title: { display: true, text: t('chart.freqAxis') } },
                            y: { title: { display: true, text: t('chart.voltAxis') } }
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: (ctx) => `${ctx.raw.x}MHz / ${ctx.raw.y}mV -> ${formatValue(ctx.raw.hash, 0)} GH/s`
                                }
                            }
                        }
                    }
                })
            ];

            const staged = isReadOnlyShareView || consolidatedData.length >= CHART_STAGED_RENDER_ROW_THRESHOLD;
            const runToken = chartRenderJobToken;

            const runTask = (index) => {
                if (runToken !== chartRenderJobToken) return;
                const task = chartTasks[index];
                if (!task) return;
                task();
                if (index >= (chartTasks.length - 1)) return;
                if (staged) {
                    chartRenderFrameId = window.requestAnimationFrame(() => {
                        chartRenderFrameId = 0;
                        runTask(index + 1);
                    });
                    return;
                }
                runTask(index + 1);
            };

            runTask(0);
        }

        function getFilterState() {
            const values = {};
            FILTER_IDS.forEach((id) => {
                const input = refs[FILTER_REF_BY_ID[id]];
                values[id] = input ? input.value : '';
            });
            values.sortKey = tableSort.key;
            values.sortDir = tableSort.dir;
            return values;
        }

        function applyFilterState(state = {}) {
            FILTER_IDS.forEach((id) => {
                const input = refs[FILTER_REF_BY_ID[id]];
                if (!input) return;
                if (Object.prototype.hasOwnProperty.call(state, id)) input.value = state[id];
            });
            if (state.sortKey && state.sortDir) {
                tableSort = {
                    key: state.sortKey,
                    dir: state.sortDir === 'asc' ? 'asc' : 'desc'
                };
            } else if (state.sort === 'score' || state.sort === 'hash') {
                tableSort = { key: state.sort, dir: 'desc' };
            } else {
                tableSort = { key: 'score', dir: 'desc' };
            }
            updateTableSortIndicators();
        }

        function getSortValue(row, key) {
            if (key === 'source') {
                if (row.source === 'master') return 0;
                if (row.source === 'legacy_high') return 1;
                return 2;
            }
            return row[key];
        }

        function compareRowsByTableSort(a, b) {
            const { key, dir } = tableSort;
            const aVal = getSortValue(a, key);
            const bVal = getSortValue(b, key);

            if (key === 'source') {
                const delta = aVal - bVal;
                if (delta !== 0) return dir === 'asc' ? delta : -delta;
                return (a.sourceFileName || '').localeCompare(b.sourceFileName || '');
            }

            const aNum = Number(aVal);
            const bNum = Number(bVal);
            const aMissing = !Number.isFinite(aNum);
            const bMissing = !Number.isFinite(bNum);

            if (aMissing && bMissing) return 0;
            if (aMissing) return 1;
            if (bMissing) return -1;

            if (aNum === bNum) return 0;
            return dir === 'asc' ? aNum - bNum : bNum - aNum;
        }

        // Apply user filters + current sort and paint table rows.
        function renderTable() {
            if (!refs.tableBody) return;
            const vMin = parseNumber(refs.filterVMin?.value);
            const fMin = parseNumber(refs.filterFMin?.value);
            const hMin = parseNumber(refs.filterHMin?.value);
            const errMax = parseNumber(refs.filterEMax?.value);
            const vrMax = parseNumber(refs.filterVrMax?.value);
            const tMax = parseNumber(refs.filterTMax?.value);

            const filtered = [...consolidatedData].filter((row) => (
                (!Number.isFinite(vMin) || row.v >= vMin) &&
                (!Number.isFinite(fMin) || row.f >= fMin) &&
                (!Number.isFinite(hMin) || row.h >= hMin) &&
                (!Number.isFinite(errMax) || row.err <= errMax) &&
                (!Number.isFinite(vrMax) || (Number.isFinite(row.vr) && row.vr <= vrMax)) &&
                (!Number.isFinite(tMax) || (Number.isFinite(row.t) && row.t <= tMax))
            )).sort(compareRowsByTableSort);

            const rowsHtml = filtered.slice(0, visibleRows).map((row) => {
                const trClass = row.source === 'master'
                    ? 'row-master row-hoverable'
                    : (row.source === 'legacy_high' ? 'row-legacy-high row-hoverable' : 'row-archive row-hoverable');
                const sourceTextClass = row.source === 'master'
                    ? 'text-neon-purple font-bold'
                    : (row.source === 'legacy_high' ? 'text-neon-red font-bold' : 'text-slate-400');
                const sourceDotClass = row.source === 'master' ? 'source-dot-master' : 'source-dot-empty';
                const badgeLabel = row.source === 'master'
                    ? t('badge.master')
                    : (row.source === 'legacy_high' ? t('badge.high') : t('badge.archive'));
                const vrClass = !Number.isFinite(row.vr)
                    ? 'text-slate-500'
                    : (row.vr <= 62 ? 'text-neon-green' : (row.vr <= 70 ? 'text-neon-amber' : 'text-neon-red'));
                const asicClass = !Number.isFinite(row.t)
                    ? 'text-slate-500'
                    : (row.t <= 55 ? 'text-neon-green' : (row.t <= 64 ? 'text-neon-amber' : 'text-neon-red'));
                return `
                    <tr class="${trClass}">
                    <td class="px-4 py-3 font-mono text-[10px] text-center">
                        <span class="source-cell-badge">
                            <span class="source-dot ${sourceDotClass}" aria-hidden="true"></span>
                            <span class="source-badge-text ${sourceTextClass}">${escapeHtml(badgeLabel)}</span>
                        </span>
                    </td>
                    <td class="px-6 py-3 font-bold text-white">${formatValue(row.h, 0)}</td>
                    <td class="px-6 py-3 text-white">${formatCompact(row.v)}</td>
                    <td class="px-6 py-3 text-slate-400">${formatCompact(row.f)}</td>
                    <td class="px-6 py-3 ${vrClass}">${formatCompact(row.vr)}</td>
                    <td class="px-6 py-3 ${asicClass}">${formatCompact(row.t)}</td>
                    <td class="px-6 py-3 ${row.err > 1 ? 'text-neon-red' : 'text-slate-400'}">${formatValue(row.err, 2)}%</td>
                    <td class="px-4 py-3 text-neon-blue text-center">${formatValue(row.e, 2)}</td>
                    <td class="px-4 py-3 text-center text-[10px] font-mono">${row.score}</td>
                    </tr>
                `;
            });
            refs.tableBody.innerHTML = rowsHtml.join('');
            if (refs.loadMoreBtn) refs.loadMoreBtn.style.display = visibleRows >= filtered.length ? 'none' : 'inline-block';
        }

        function resetFilters() {
            [refs.filterVMin, refs.filterFMin, refs.filterHMin, refs.filterEMax, refs.filterVrMax, refs.filterTMax].forEach((input) => {
                if (input) input.value = '';
            });
            syncAllFilterRangesFromInputs();
            tableSort = { key: 'score', dir: 'desc' };
            updateTableSortIndicators();
            visibleRows = DEFAULT_VISIBLE_ROWS;
            renderTable();
        }

        function removeFileById(fileId) {
            if (!fileId) return;
            const before = rawFilesData.length;
            rawFilesData = rawFilesData.filter((item) => item.id !== fileId);
            if (rawFilesData.length === before) return;
            if (!rawFilesData.length) {
                clearProjectState();
                return;
            }
            ensureMasterConsistency();
            if (isUploadOverlayVisible()) {
                renderFileList();
                renderDataQualitySummary(false);
                syncControlVisibility();
                return;
            }
            if (consolidatedData.length) recomputeAndRender(false);
            else {
                renderFileList();
                renderDataQualitySummary(false);
                syncControlVisibility();
            }
        }

        function updateUploadProcessLabel() {
            const labelEl = document.getElementById('upload-process-label');
            if (!labelEl) return;
            const langPack = I18N[currentLanguage] || {};
            const enPack = I18N.en || {};
            const startLabel = String(langPack['upload.start'] ?? enPack['upload.start'] ?? 'START ANALYSIS');
            const recalcLabel = String(
                langPack['upload.recalculate'] ??
                langPack['upload.start'] ??
                enPack['upload.recalculate'] ??
                enPack['upload.start'] ??
                'RECALCULATE ANALYSIS'
            );
            const text = consolidatedData.length > 0 ? recalcLabel : startLabel;
            labelEl.textContent = normalizeUppercaseIForLanguage(text, currentLanguage);
        }

        function syncControlVisibility() {
            const hasFiles = rawFilesData.length > 0;
            const hasData = consolidatedData.length > 0;
            const hasUserFiles = getUserFileRecords().length > 0;
            const hasSampleOnlyData = hasData && isSampleOnlyProject();
            const uploadLocked = IS_SNAPSHOT_VIEW || isReadOnlyShareView;
            const canShare = hasData && !IS_EMBEDDED_STATE && !isReadOnlyShareView && (hasUserFiles || hasSampleOnlyData);
            const canOpenUploadManager = !uploadLocked && (hasFiles || keepUploadOverlayClosedOnBoot);
            // Keep sample preview action reachable whenever upload overlay is open.
            // Users may want to compare their current dataset against sample data.
            const showSamplePreview = isUploadOverlayVisible() && !uploadLocked;
            setSamplePreviewVisibility(showSamplePreview);
            updateUploadProcessLabel();
            setHidden(refs.showUploadBtn, !canOpenUploadManager);
            setHidden(refs.shareBtn, !canShare);
            setHidden(refs.exportHtmlBtn, IS_EMBEDDED_STATE || !hasData || isReadOnlyShareView);
            setHidden(refs.exportJpegBtn, IS_EMBEDDED_STATE || !hasData || isReadOnlyShareView);
            setHidden(refs.viewMenuBtn, !hasData || isReadOnlyShareView);
            if (!hasData || isReadOnlyShareView) setHidden(refs.viewMenuDropdown, true);
            if (refs.shareBtn) {
                refs.shareBtn.disabled = isShareBusy;
                refs.shareBtn.style.opacity = isShareBusy ? '0.68' : '';
                refs.shareBtn.style.pointerEvents = isShareBusy ? 'none' : '';
            }
            syncMobileHeaderMenuLayout();
        }

        function setSamplePreviewVisibility(visible) {
            const shouldHide = !visible || IS_SNAPSHOT_VIEW || isReadOnlyShareView;
            setHidden(refs.samplePreviewRow, shouldHide);
            setHidden(refs.samplePreviewBtn, shouldHide);
        }

        function setUploadOverlayShareLoadingHidden(hidden) {
            if (!refs.uploadSection) return;
            const shouldHide = Boolean(hidden);
            refs.uploadSection.classList.toggle('share-loading-hidden', shouldHide);
            if (shouldHide) {
                refs.uploadSection.classList.remove('slide-up-hidden');
                refs.uploadSection.style.display = 'flex';
            }
        }

        function isUploadOverlayVisible() {
            if (!refs.uploadSection) return false;
            return refs.uploadSection.style.display !== 'none' && !refs.uploadSection.classList.contains('slide-up-hidden');
        }

        function activateDashboardView({ allowUpload = true } = {}) {
            setUploadOverlayShareLoadingHidden(false);
            refs.uploadSection.classList.add('slide-up-hidden');
            refs.uploadSection.style.display = 'none';
            refs.dashboardContent.classList.remove('opacity-30', 'blur-sm');
            refs.dashboardContent.classList.add('opacity-100', 'blur-0');
            setSamplePreviewVisibility(!IS_SNAPSHOT_VIEW && !isReadOnlyShareView);
            if (refs.closeUploadBtn) refs.closeUploadBtn.classList.add('hidden');
            if (allowUpload && !IS_SNAPSHOT_VIEW && !isReadOnlyShareView && rawFilesData.length > 0) setHidden(refs.showUploadBtn, false);
            else setHidden(refs.showUploadBtn, true);
            syncControlVisibility();
        }

        function showInitialUploadView() {
            keepUploadOverlayClosedOnBoot = false;
            if ((IS_SNAPSHOT_VIEW || isReadOnlyShareView) && consolidatedData.length > 0) {
                activateDashboardView({ allowUpload: false });
                return;
            }
            setUploadOverlayShareLoadingHidden(false);
            refs.uploadSection.classList.remove('slide-up-hidden');
            refs.uploadSection.style.display = 'flex';
            refs.dashboardContent.classList.remove('opacity-100', 'blur-0');
            refs.dashboardContent.classList.add('opacity-30', 'blur-sm');
            setSamplePreviewVisibility(true);
            if (refs.closeUploadBtn) refs.closeUploadBtn.classList.add('hidden');
        }

        function openUploadOverlay({ append = true } = {}) {
            if (IS_SNAPSHOT_VIEW || isReadOnlyShareView) return;
            setUploadOverlayShareLoadingHidden(false);
            appendUploadMode = append;
            if (refs.fileInput) refs.fileInput.value = '';
            refs.uploadSection.classList.remove('slide-up-hidden');
            refs.uploadSection.style.display = 'flex';
            setTimeout(() => {
                refs.uploadSection.style.transform = 'translateY(0)';
                refs.uploadSection.style.opacity = '1';
            }, 10);
            refs.dashboardContent.classList.remove('opacity-100', 'blur-0');
            refs.dashboardContent.classList.add('opacity-30', 'blur-sm');
            setSamplePreviewVisibility(!IS_SNAPSHOT_VIEW && !isReadOnlyShareView);
            if (refs.closeUploadBtn) refs.closeUploadBtn.classList.toggle('hidden', consolidatedData.length === 0);
            syncControlVisibility();
        }

        async function loadSampleCsvPreview() {
            if (IS_SNAPSHOT_VIEW || isReadOnlyShareView || isSampleCsvLoading) return;

            const button = refs.samplePreviewBtn || null;
            const sampleUrl = `${SAMPLE_CSV_PATH}?v=${encodeURIComponent(APP_VERSION)}`;
            isSampleCsvLoading = true;

            if (button) {
                button.disabled = true;
                button.classList.add('opacity-60', 'cursor-wait');
            }

            try {
                const response = await fetch(sampleUrl, { cache: 'no-store', credentials: 'same-origin' });
                if (!response.ok) throw new Error(`HTTP_${response.status}`);

                const csvText = await response.text();
                const parsed = parseCSV(csvText);
                if (!Array.isArray(parsed.data) || parsed.data.length === 0) throw new Error('EMPTY_SAMPLE_DATA');

                const sampleRecord = buildFileRecordFromUpload({
                    name: 'bitaxe_demo_sample_v1.csv',
                    lastModified: Date.now()
                }, parsed);
                sampleRecord.isSample = true;
                rawFilesData = [sampleRecord];
                consolidatedData = [];
                visibleRows = DEFAULT_VISIBLE_ROWS;
                pendingFileReads = 0;
                appendUploadMode = false;
                keepUploadOverlayClosedOnBoot = false;
                dataQualityPinnedByUser = false;
                if (refs.fileInput) refs.fileInput.value = '';

                recomputeAndRender(true);
                scheduleDataQualityAutoClose(DATA_QUALITY_AUTO_CLOSE_DELAY_MS);
            } catch (_) {
                alert(t('alert.sampleLoadFailed'));
            } finally {
                if (button) {
                    button.disabled = false;
                    button.classList.remove('opacity-60', 'cursor-wait');
                }
                isSampleCsvLoading = false;
            }
        }

        async function renderChartsFromCurrentData() {
            cancelScheduledChartRender();
            const runToken = chartRenderJobToken;
            if (!consolidatedData.length) {
                clearChartInstances();
                return;
            }

            const chartReady = await ensureChartLibrariesLoaded();
            if (!chartReady) return;
            if (runToken !== chartRenderJobToken) return;
            if (!consolidatedData.length) return;
            initCharts();
        }

        // Full recalculation pipeline after upload/master/visibility changes.
        function recomputeAndRender(activateDashboard = true) {
            ensureMasterConsistency();
            mergeData();
            refreshFilterControlsFromData();
            renderDataQualitySummary(true);
            renderKPI();
            renderAATE();
            renderEliteStats();
            void renderChartsFromCurrentData();
            renderTable();
            renderFileList();
            if (activateDashboard) activateDashboardView({ allowUpload: !IS_SNAPSHOT_VIEW });
            syncControlVisibility();
        }

        function bytesToRoundedMb(bytes) {
            const mb = Number(bytes || 0) / (1024 * 1024);
            if (!Number.isFinite(mb) || mb <= 0) return '0';
            if (mb < 1) return mb.toFixed(1);
            if (mb < 10) return mb.toFixed(1).replace(/\.0$/, '');
            return String(Math.round(mb));
        }

        function isCsvLikeFile(file) {
            const name = String(file?.name || '').toLowerCase();
            const mime = String(file?.type || '').toLowerCase();
            if (name.endsWith('.csv')) return true;
            if (mime.includes('csv')) return true;
            if (mime === 'text/plain' || mime === 'application/vnd.ms-excel') return true;
            return false;
        }

        // Read selected files asynchronously and normalize each payload.
        function handleFiles(files) {
            const allFiles = Array.from(files || []);
            if (!allFiles.length) return;
            const uploadAttemptStats = createEmptyUploadAttemptStats();
            uploadAttemptStats.attemptedFiles = allFiles.length;

            const csvCandidates = allFiles.filter((file) => isCsvLikeFile(file));
            const nonCsvCount = allFiles.length - csvCandidates.length;
            uploadAttemptStats.attemptedCsv = csvCandidates.length;
            uploadAttemptStats.nonCsv = nonCsvCount;
            if (nonCsvCount > 0) {
                alert(t('alert.nonCsvSkipped', { count: nonCsvCount }));
            }

            if (!csvCandidates.length) {
                lastUploadAttemptStats = uploadAttemptStats;
                return;
            }

            const maxByCount = csvCandidates.slice(0, MAX_UPLOAD_FILES_PER_BATCH);
            const countOverflow = csvCandidates.length - maxByCount.length;
            uploadAttemptStats.countOverflow = countOverflow;
            if (countOverflow > 0) {
                alert(t('alert.fileCountExceeded', { limit: MAX_UPLOAD_FILES_PER_BATCH, count: countOverflow }));
            }

            const acceptedFiles = [];
            let tooLargeCount = 0;
            let totalOverflowCount = 0;
            let totalBytes = 0;
            maxByCount.forEach((file) => {
                const size = Math.max(0, Number(file.size || 0));
                if (size > MAX_CSV_FILE_BYTES) {
                    tooLargeCount += 1;
                    return;
                }
                if ((totalBytes + size) > MAX_UPLOAD_TOTAL_BYTES) {
                    totalOverflowCount += 1;
                    return;
                }
                totalBytes += size;
                acceptedFiles.push(file);
                uploadAttemptStats.acceptedFiles += 1;
                uploadAttemptStats.acceptedBytes += size;
                if (size > uploadAttemptStats.largestUploadBytes) {
                    uploadAttemptStats.largestUploadBytes = size;
                }
            });
            uploadAttemptStats.tooLarge = tooLargeCount;
            uploadAttemptStats.totalOverflow = totalOverflowCount;
            lastUploadAttemptStats = uploadAttemptStats;

            if (tooLargeCount > 0) {
                alert(t('alert.fileTooLarge', {
                    count: tooLargeCount,
                    limitMb: bytesToRoundedMb(MAX_CSV_FILE_BYTES)
                }));
            }
            if (totalOverflowCount > 0) {
                alert(t('alert.totalSizeExceeded', {
                    count: totalOverflowCount,
                    limitMb: bytesToRoundedMb(MAX_UPLOAD_TOTAL_BYTES)
                }));
            }

            const list = acceptedFiles;
            if (!list.length) return;

            if (removeSampleFileRecords({ clearMerged: true })) {
                appendUploadMode = false;
            }

            if (!appendUploadMode) {
                rawFilesData = [];
                consolidatedData = [];
                visibleRows = DEFAULT_VISIBLE_ROWS;
            }
            keepUploadOverlayClosedOnBoot = false;
            if (refs.dataQualitySection && panelVisibility['data-quality'] !== false) {
                refs.dataQualitySection.classList.remove('hidden');
            }

            pendingFileReads = list.length;
            if (refs.fileListWrapper) refs.fileListWrapper.classList.remove('hidden');
            if (refs.fileCountLabel) refs.fileCountLabel.innerText = t('file.countLabel', { count: list.length });

            let readCount = 0;
            list.forEach((file) => {
                const reader = new FileReader();
                reader.onload = (event) => {
                    const parsed = parseCSV(event.target.result);
                    const record = buildFileRecordFromUpload(file, parsed);
                    const existingIdx = rawFilesData.findIndex((item) => item.id === record.id);
                    if (existingIdx > -1) rawFilesData[existingIdx] = record;
                    else rawFilesData.push(record);
                    readCount += 1;
                    pendingFileReads = Math.max(0, pendingFileReads - 1);
                    if (readCount === list.length) finalizeFileRead();
                };
                reader.onerror = () => {
                    uploadAttemptStats.uploadError += 1;
                    readCount += 1;
                    pendingFileReads = Math.max(0, pendingFileReads - 1);
                    if (readCount === list.length) finalizeFileRead();
                };
                reader.readAsText(file);
            });
        }

        function finalizeFileRead() {
            ensureMasterConsistency();
            renderFileList();
            renderDataQualitySummary(false);
            syncControlVisibility();
            appendUploadMode = false;
        }

        function buildUsageLogPayload(analysisMs = 0) {
            const userFiles = getUserFileRecords();
            if (!userFiles.length) return null;

            const asCount = (value) => Math.max(0, Math.round(toSafeNumber(value, 0)));
            const uploadAttempt = (lastUploadAttemptStats && typeof lastUploadAttemptStats === 'object')
                ? lastUploadAttemptStats
                : createEmptyUploadAttemptStats();
            const totals = collectRawFileTotals(userFiles);

            let bytesFromFiles = 0;
            let largestUploadFromFiles = 0;
            userFiles.forEach((file) => {
                const size = Math.max(0, toSafeNumber(file?.sizeBytes, 0));
                bytesFromFiles += size;
                if (size > largestUploadFromFiles) largestUploadFromFiles = size;
            });

            const filesAttempted = Math.max(userFiles.length, asCount(uploadAttempt.attemptedCsv));
            const filesProcessed = userFiles.length;
            const bytesAttempted = Math.max(bytesFromFiles, asCount(uploadAttempt.acceptedBytes));
            const bytesProcessed = bytesFromFiles > 0 ? bytesFromFiles : bytesAttempted;
            const largestUploadBytes = Math.max(largestUploadFromFiles, asCount(uploadAttempt.largestUploadBytes));
            const selectedLanguage = normalizeLanguageCode(currentLanguage || DEFAULT_LANGUAGE_CODE);
            const browserLanguage = getBrowserLanguageTag();
            const countryHint = detectBrowserCountryHint();
            const timezoneName = getBrowserTimezoneName();
            const timezoneOffsetMin = getBrowserTimezoneOffsetMinutes();

            return {
                app_version: APP_VERSION,
                source_api: 'share_usage_log',
                request_status: 'ok',
                http_status: 200,
                analysis_ms: asCount(analysisMs),
                selected_language: selectedLanguage,
                browser_language: browserLanguage,
                selected_theme: getCurrentThemeMode(),
                selected_theme_variant: getCurrentThemeVariant(),
                timezone_name: timezoneName,
                timezone_offset_min: timezoneOffsetMin,
                country_hint: countryHint,
                files_attempted: filesAttempted,
                files_processed: filesProcessed,
                bytes_attempted: bytesAttempted,
                bytes_processed: bytesProcessed,
                largest_upload_bytes: largestUploadBytes,
                total_rows: asCount(totals.totalRows),
                parsed_rows: asCount(totals.parsedRows),
                skipped_rows: asCount(totals.skippedRows),
                merged_records: asCount(consolidatedData.length),
                upload_skipped: {
                    nonCsv: asCount(uploadAttempt.nonCsv),
                    tooLarge: asCount(uploadAttempt.tooLarge),
                    totalOverflow: asCount(uploadAttempt.totalOverflow),
                    uploadError: asCount(uploadAttempt.uploadError),
                    countOverflow: asCount(uploadAttempt.countOverflow)
                }
            };
        }

        async function sendUsageLogEvent(analysisMs = 0) {
            if (IS_SNAPSHOT_VIEW || isReadOnlyShareView) return;

            const payload = buildUsageLogPayload(analysisMs);
            if (!payload) return;

            try {
                const body = JSON.stringify({
                    request_ts: String(Math.floor(Date.now() / 1000)),
                    request_nonce: generateRequestNonce(),
                    payload
                });
                if (!body || body.length > (64 * 1024)) return;

                await fetchJsonWithTimeout(
                    USAGE_LOG_API_PATH,
                    {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body
                    },
                    USAGE_LOG_FETCH_TIMEOUT_MS
                );
            } catch (_) {
                // Logging is best-effort; dashboard flow should stay uninterrupted.
            }
        }

        // Validate upload state, lock selected master and trigger full analysis.
        function processSelectedFiles() {
            if (pendingFileReads > 0) {
                alert(t('alert.waitFiles'));
                return;
            }
            const userFiles = getUserFileRecords();
            if (!userFiles.length) {
                alert(t('alert.needCsv'));
                return;
            }
            if (userFiles.length !== rawFilesData.length) {
                rawFilesData = userFiles;
                consolidatedData = [];
                visibleRows = DEFAULT_VISIBLE_ROWS;
            }
            const selectedMaster = Array.from(document.getElementsByName('masterFile')).find((radio) => radio.checked);
            if (selectedMaster) {
                rawFilesData.forEach((file) => { file.isMaster = file.id === selectedMaster.value; });
            }
            ensureMasterConsistency();
            const analysisStartedAt = (typeof performance !== 'undefined' && typeof performance.now === 'function')
                ? performance.now()
                : Date.now();
            recomputeAndRender(true);
            const analysisEndedAt = (typeof performance !== 'undefined' && typeof performance.now === 'function')
                ? performance.now()
                : Date.now();
            const analysisMs = Math.max(0, Math.round(analysisEndedAt - analysisStartedAt));
            void sendUsageLogEvent(analysisMs);
            scheduleDataQualityAutoClose(DATA_QUALITY_AUTO_CLOSE_DELAY_MS);
        }

        function clearProjectState() {
            rawFilesData = [];
            consolidatedData = [];
            visibleRows = DEFAULT_VISIBLE_ROWS;
            pendingFileReads = 0;
            lastUploadAttemptStats = createEmptyUploadAttemptStats();
            dataQualityPinnedByUser = false;
            stopDataQualityCountdown();
            destroyAllCharts();
            renderDataQualitySummary(false);
            refreshFilterControlsFromData();
            renderTable();
            showInitialUploadView();
            syncControlVisibility();
        }

        function buildTimestampFileName(prefix) {
            const now = new Date();
            const pad = (n) => String(n).padStart(2, '0');
            return `${prefix}_${now.getFullYear()}${pad(now.getMonth() + 1)}${pad(now.getDate())}_${pad(now.getHours())}${pad(now.getMinutes())}${pad(now.getSeconds())}.html`;
        }

        function buildProjectPayload(mode = 'snapshot') {
            const selectedTheme = getCurrentThemeMode();
            const selectedThemeVariant = getCurrentThemeVariant();
            return {
                meta: {
                    exportedAt: new Date().toISOString(),
                    appVersion: APP_VERSION,
                    mode,
                    selectedLanguage: normalizeLanguageCode(currentLanguage),
                    selectedTheme,
                    selectedThemeVariant
                },
                visibleRows: toSafeNumber(visibleRows, DEFAULT_VISIBLE_ROWS),
                filters: getFilterState(),
                layout: getPanelLayoutSnapshot({ includeDataQuality: true }),
                rawFilesData: rawFilesData.map((file) => ({
                    id: file.id,
                    name: file.name,
                    lastModified: file.lastModified,
                    isMaster: file.isMaster,
                    enabled: file.enabled,
                    stats: normalizeStats(file.stats),
                    data: file.data.map((row) => ({
                        v: row.v, f: row.f, h: row.h, t: row.t, vr: row.vr, e: row.e, err: row.err, p: row.p
                    })),
                    timeSeries: file.timeSeries.map((point) => ({
                        ts: point.ts, action: point.action, h: point.h, err: point.err, temp: point.temp, p: point.p
                    }))
                })),
                consolidatedData: consolidatedData.map((row) => ({
                    source: row.source,
                    sourceFileId: row.sourceFileId,
                    sourceFileName: row.sourceFileName,
                    v: row.v,
                    f: row.f,
                    h: row.h,
                    t: row.t,
                    vr: row.vr,
                    e: row.e,
                    err: row.err,
                    p: row.p,
                    score: row.score
                }))
            };
        }

        // Export interactive snapshot: drag/drop, chart hover, filters and panel close stay active.
        async function buildStandaloneHtml() {
            const exportPayload = buildProjectPayload('snapshot');
            const exportRoot = document.documentElement.cloneNode(true);
            const exportHead = exportRoot.querySelector('head');
            if (!exportHead) return '';

            exportHead.querySelectorAll(
                `[data-embedded-export-state], [data-embedded-export-bootstrap], [data-export-mode-style], #${EMBEDDED_STATE_NODE_ID}`
            ).forEach((el) => el.remove());

            // Remove strict CSP for exported standalone file to avoid file:// script blocking.
            exportHead.querySelectorAll('meta[http-equiv="Content-Security-Policy"]').forEach((el) => el.remove());
            // Remove default theme bootstrap; exported snapshot enforces captured theme.
            exportHead.querySelectorAll('script').forEach((scriptEl) => {
                const text = String(scriptEl.textContent || '');
                if (text.includes('bootstrapTheme')) scriptEl.remove();
            });

            const exportTheme = (exportPayload.meta?.selectedTheme === THEME_LIGHT) ? THEME_LIGHT : THEME_DARK;
            const exportVariant = normalizeThemeVariant(exportPayload.meta?.selectedThemeVariant);
            const exportMetaColor = THEME_PALETTES[exportVariant]?.meta?.[exportTheme]
                || THEME_PALETTES[THEME_VARIANT_PURPLE]?.meta?.[exportTheme]
                || '#070b14';
            const themeInitScript = document.createElement('script');
            themeInitScript.setAttribute('data-export-theme-init', '1');
            themeInitScript.textContent = `(function(){var t='${exportTheme}';var v='${exportVariant}';var r=document.documentElement;if(!r)return;r.classList.remove('dark','light-theme','theme-variant-purple','theme-variant-orange');r.classList.add(t==='light'?'light-theme':'dark');r.classList.add(v==='orange'?'theme-variant-orange':'theme-variant-purple');r.setAttribute('data-theme',t);r.setAttribute('data-theme-variant',v);var m=document.querySelector('meta[name=\\"theme-color\\"]');if(m)m.setAttribute('content','${exportMetaColor}');})();`;
            exportHead.prepend(themeInitScript);

            // Force vendor libs to public CDNs for exported files.
            const vendorCdnMap = new Map([
                [pathWithBase('/assets/vendor/tailwindcss-cdn.js'), 'https://cdn.tailwindcss.com'],
                [pathWithBase('/assets/vendor/chart.umd.min.js'), CHART_JS_PINNED_CDN_URL],
                [pathWithBase('/assets/vendor/chartjs-plugin-annotation.min.js'), CHART_ANNOTATION_PINNED_CDN_URL],
                [pathWithBase('/assets/vendor/html2canvas.min.js'), 'https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js']
            ]);

            exportRoot.querySelectorAll('script[src]').forEach((scriptEl) => {
                const rawSrc = String(scriptEl.getAttribute('src') || '').trim();
                if (!rawSrc) return;
                const cleanSrc = rawSrc.split('?')[0];
                const cdnUrl = vendorCdnMap.get(cleanSrc);
                if (!cdnUrl) return;
                scriptEl.setAttribute('src', cdnUrl);
                scriptEl.removeAttribute('onerror');
            });

            // Make root-relative asset URLs absolute so exported file can still load css/images/icons.
            exportRoot.querySelectorAll('script[src], link[href], img[src]').forEach((el) => {
                const attr = el.hasAttribute('src') ? 'src' : (el.hasAttribute('href') ? 'href' : null);
                if (!attr) return;
                const raw = String(el.getAttribute(attr) || '').trim();
                if (!raw || !raw.startsWith('/')) return;
                try {
                    el.setAttribute(attr, new URL(raw, window.location.origin).toString());
                } catch (_) {
                    // Keep original path if URL conversion fails.
                }
            });

            // Embed chart libs directly in exported HTML to keep charts interactive
            // even when runtime CDN loading is flaky or blocked.
            const chartLocalUrl = new URL(`${pathWithBase('/assets/vendor/chart.umd.min.js')}?v=${encodeURIComponent(APP_VERSION)}`, window.location.origin).toString();
            const annotationLocalUrl = new URL(`${pathWithBase('/assets/vendor/chartjs-plugin-annotation.min.js')}?v=${encodeURIComponent(APP_VERSION)}`, window.location.origin).toString();
            const chartScriptText = await fetchTextWithFallback(
                [chartLocalUrl, CHART_JS_PINNED_CDN_URL, 'https://cdn.jsdelivr.net/npm/chart.js'],
                14000
            );
            const annotationScriptText = await fetchTextWithFallback(
                [annotationLocalUrl, CHART_ANNOTATION_PINNED_CDN_URL, 'https://cdnjs.cloudflare.com/ajax/libs/chartjs-plugin-annotation/2.1.0/chartjs-plugin-annotation.min.js'],
                14000
            );
            const canInlineChartMain = Boolean(chartScriptText && chartScriptText.trim());
            const canInlineAnnotation = Boolean(annotationScriptText && annotationScriptText.trim());

            if (canInlineChartMain) {
                const removableScriptSources = new Set([
                    pathWithBase('/assets/vendor/chart.umd.min.js'),
                    CHART_JS_PINNED_CDN_URL,
                    'https://cdn.jsdelivr.net/npm/chart.js'
                ]);
                if (canInlineAnnotation) {
                    removableScriptSources.add(pathWithBase('/assets/vendor/chartjs-plugin-annotation.min.js'));
                    removableScriptSources.add(CHART_ANNOTATION_PINNED_CDN_URL);
                    removableScriptSources.add('https://cdnjs.cloudflare.com/ajax/libs/chartjs-plugin-annotation/2.1.0/chartjs-plugin-annotation.min.js');
                }
                exportRoot.querySelectorAll('script[src]').forEach((scriptEl) => {
                    const rawSrc = String(scriptEl.getAttribute('src') || '').trim();
                    if (!rawSrc) return;
                    const cleanSrc = rawSrc.split('?')[0];
                    if (removableScriptSources.has(cleanSrc)) {
                        scriptEl.remove();
                    }
                });

                const chartInlineScript = document.createElement('script');
                chartInlineScript.setAttribute('data-export-lib', 'chartjs');
                chartInlineScript.textContent = chartScriptText;
                exportHead.appendChild(chartInlineScript);

                if (canInlineAnnotation) {
                    const annotationInlineScript = document.createElement('script');
                    annotationInlineScript.setAttribute('data-export-lib', 'chartjs-annotation');
                    annotationInlineScript.textContent = annotationScriptText;
                    exportHead.appendChild(annotationInlineScript);
                }
            }

            // Keep these nodes in DOM to prevent null-reference crashes in init logic.
            // They are hidden via export-mode CSS below.

            const dashboardContent = exportRoot.querySelector('#dashboard-content');
            if (dashboardContent) {
                dashboardContent.classList.remove('opacity-30', 'blur-sm');
                dashboardContent.classList.add('opacity-100', 'blur-0');
                dashboardContent.style.opacity = '1';
                dashboardContent.style.filter = 'none';
            }

            const style = document.createElement('style');
            style.setAttribute('data-export-mode-style', '1');
            style.textContent = `
                #upload-section, #show-upload-btn, #close-upload-btn, #language-control, #theme-toggle-btn, #variant-toggle-btn, #data-quality-section, #share-modal-overlay { display: none !important; }
                #dashboard-content { opacity: 1 !important; filter: none !important; }
                #header-shell { flex-direction: row !important; align-items: center !important; justify-content: space-between !important; gap: 12px !important; }
                #mobile-header-menu-btn { display: none !important; }
                #mobile-header-menu-panel { display: flex !important; width: auto !important; flex: 1 1 auto !important; justify-content: flex-end !important; align-items: center !important; gap: 8px !important; }
                #header-actions { margin-left: auto !important; width: auto !important; flex-wrap: nowrap !important; justify-content: flex-end !important; align-items: center !important; }
                #header-actions > .relative { margin-left: auto !important; }
                #view-menu-btn { margin-left: auto !important; }
                @supports (content-visibility: auto) {
                    .draggable-item, #panel-table, #data-quality-section { content-visibility: visible !important; contain-intrinsic-size: auto !important; }
                }
            `;
            exportHead.appendChild(style);

            const embeddedStateNode = document.createElement('script');
            embeddedStateNode.id = EMBEDDED_STATE_NODE_ID;
            embeddedStateNode.type = 'application/json';
            embeddedStateNode.setAttribute('data-embedded-export-state', '1');
            embeddedStateNode.textContent = escapeForInlineScript(JSON.stringify(exportPayload));
            exportHead.appendChild(embeddedStateNode);

            const stateBootstrapScript = document.createElement('script');
            stateBootstrapScript.setAttribute('data-embedded-export-bootstrap', '1');
            stateBootstrapScript.textContent = `(function(){window.__STATE_MODE__='snapshot';try{var n=document.getElementById('${EMBEDDED_STATE_NODE_ID}');window.__EXPORT_STATE__=n?JSON.parse(String(n.textContent||'null')):null;}catch(_){window.__EXPORT_STATE__=null;}})();`;
            exportHead.appendChild(stateBootstrapScript);

            return `<!DOCTYPE html>\n${exportRoot.outerHTML}`;
        }

        function downloadHtmlFile(content, fileName) {
            const blob = new Blob([content], { type: 'text/html;charset=utf-8' });
            downloadBlobFile(blob, fileName);
        }

        function downloadBlobFile(blob, fileName) {
            if (!(blob instanceof Blob)) return;
            const url = URL.createObjectURL(blob);
            const anchor = document.createElement('a');
            anchor.href = url;
            anchor.download = fileName;
            document.body.appendChild(anchor);
            anchor.click();
            anchor.remove();
            setTimeout(() => URL.revokeObjectURL(url), 1200);
        }

        function dataUrlToBlob(dataUrl, mimeType = 'image/jpeg') {
            const parts = String(dataUrl || '').split(',');
            const b64 = parts[1] || '';
            const binary = atob(b64);
            const len = binary.length;
            const bytes = new Uint8Array(len);
            for (let i = 0; i < len; i += 1) {
                bytes[i] = binary.charCodeAt(i);
            }
            return new Blob([bytes], { type: mimeType });
        }

        function disposeCanvas(canvas) {
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            if (ctx) ctx.clearRect(0, 0, canvas.width, canvas.height);
            canvas.width = 1;
            canvas.height = 1;
        }

        function estimateDataUrlBytes(dataUrl) {
            const b64 = (dataUrl.split(',')[1] || '');
            return Math.ceil((b64.length * 3) / 4);
        }

        function createResizedCanvas(sourceCanvas, scale) {
            const canvas = document.createElement('canvas');
            canvas.width = Math.max(1, Math.floor(sourceCanvas.width * scale));
            canvas.height = Math.max(1, Math.floor(sourceCanvas.height * scale));
            const ctx = canvas.getContext('2d', { alpha: false });
            if (ctx) {
                ctx.imageSmoothingEnabled = true;
                ctx.imageSmoothingQuality = 'high';
                ctx.drawImage(sourceCanvas, 0, 0, canvas.width, canvas.height);
            }
            return canvas;
        }

        function exportCanvasAsOptimizedJpeg(canvas, maxBytes = JPEG_TARGET_MAX_BYTES) {
            let workCanvas = canvas;
            let quality = 0.88;
            let dataUrl = workCanvas.toDataURL('image/jpeg', quality);
            let bytes = estimateDataUrlBytes(dataUrl);
            const generatedCanvases = [];

            while (bytes > maxBytes && quality > 0.56) {
                quality -= 0.06;
                dataUrl = workCanvas.toDataURL('image/jpeg', quality);
                bytes = estimateDataUrlBytes(dataUrl);
            }

            while (bytes > maxBytes && workCanvas.width > 900) {
                const ratio = Math.sqrt(maxBytes / bytes);
                const scale = Math.min(0.92, Math.max(0.72, ratio));
                const nextCanvas = createResizedCanvas(workCanvas, scale);
                if (workCanvas !== canvas) generatedCanvases.push(workCanvas);
                workCanvas = nextCanvas;
                quality = Math.min(quality, 0.78);
                dataUrl = workCanvas.toDataURL('image/jpeg', quality);
                bytes = estimateDataUrlBytes(dataUrl);
                while (bytes > maxBytes && quality > 0.5) {
                    quality -= 0.05;
                    dataUrl = workCanvas.toDataURL('image/jpeg', quality);
                    bytes = estimateDataUrlBytes(dataUrl);
                }
            }

            if (workCanvas !== canvas) generatedCanvases.push(workCanvas);
            Array.from(new Set(generatedCanvases)).forEach((item) => disposeCanvas(item));
            return dataUrl;
        }

        function cropCanvasHorizontal(canvas, leftPx, rightPx, padPx = 0) {
            if (!canvas) return canvas;
            const width = canvas.width;
            const height = canvas.height;
            if (width < 2 || height < 2) return canvas;

            let left = Math.floor(leftPx - padPx);
            let right = Math.ceil(rightPx + padPx);
            left = Math.max(0, Math.min(width - 1, left));
            right = Math.max(left + 1, Math.min(width, right));

            const outWidth = right - left;
            const out = document.createElement('canvas');
            out.width = outWidth;
            out.height = height;
            const outCtx = out.getContext('2d', { alpha: false });
            if (!outCtx) return canvas;
            outCtx.fillStyle = '#070b14';
            outCtx.fillRect(0, 0, out.width, out.height);
            outCtx.drawImage(canvas, left, 0, outWidth, height, 0, 0, outWidth, height);
            return out;
        }

        function hasContentInLowerRegion(canvas, background = { r: 7, g: 11, b: 20 }, tolerance = 16) {
            if (!canvas) return false;
            const width = canvas.width;
            const height = canvas.height;
            if (width < 40 || height < 1200) return true;

            const ctx = canvas.getContext('2d', { willReadFrequently: true });
            if (!ctx) return true;

            const startY = Math.floor(height * 0.62);
            const endY = Math.floor(height * 0.98);
            const regionH = Math.max(1, endY - startY);
            const data = ctx.getImageData(0, startY, width, regionH).data;
            const stepX = Math.max(1, Math.floor(width / 180));
            const stepY = Math.max(1, Math.floor(regionH / 70));

            let nonBg = 0;
            let total = 0;

            for (let y = 0; y < regionH; y += stepY) {
                for (let x = 0; x < width; x += stepX) {
                    const idx = (y * width + x) * 4;
                    const a = data[idx + 3];
                    if (a < 8) continue;
                    const r = data[idx];
                    const g = data[idx + 1];
                    const b = data[idx + 2];
                    const isBg = (
                        Math.abs(r - background.r) <= tolerance &&
                        Math.abs(g - background.g) <= tolerance &&
                        Math.abs(b - background.b) <= tolerance
                    );
                    if (!isBg) nonBg += 1;
                    total += 1;
                }
            }

            if (!total) return true;
            return (nonBg / total) > 0.015;
        }

        async function exportCurrentViewAsHtml() {
            if (!consolidatedData.length) {
                alert(t('alert.needAnalysis'));
                return;
            }
            try {
                await renderChartsFromCurrentData();
            } catch (_) {
                // Keep export flow alive even if a runtime chart refresh fails.
            }
            await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
            const html = await buildStandaloneHtml();
            if (!html) return;
            downloadHtmlFile(html, buildTimestampFileName('bitaxe_snapshot'));
        }

        // High quality/low size JPEG export pipeline with deterministic layout capture.
        async function exportCurrentViewAsJpeg() {
            if (!consolidatedData.length) {
                alert(t('alert.needAnalysis'));
                return;
            }
            if (isJpegExporting) return;

            const html2canvasReady = await ensureHtml2canvasLoaded();
            if (!html2canvasReady || typeof window.html2canvas !== 'function') {
                alert(t('alert.jpegLib'));
                return;
            }

            isJpegExporting = true;
            const jpegBtn = refs.exportJpegBtn || null;
            const prevJpegBtnDisabled = jpegBtn ? jpegBtn.disabled : false;
            if (jpegBtn) {
                jpegBtn.disabled = true;
                jpegBtn.classList.add('opacity-60', 'cursor-wait');
            }

            const previousMenuState = refs.viewMenuDropdown && !refs.viewMenuDropdown.classList.contains('hidden');
            if (refs.viewMenuDropdown) refs.viewMenuDropdown.classList.add('hidden');

            const oldX = window.scrollX;
            const oldY = window.scrollY;
            const captureScale = Math.min(1.2, window.devicePixelRatio || 1);
            window.scrollTo(0, 0);
            let capturedCanvas = null;
            let croppedCanvas = null;

            try {
                if (document.fonts && document.fonts.ready) {
                    try { await document.fonts.ready; } catch (_) { /* ignore */ }
                }
                const waitForStableLayout = async (delayMs = 0, frames = 2) => {
                    if (delayMs > 0) {
                        await new Promise((resolve) => setTimeout(resolve, delayMs));
                    }
                    for (let i = 0; i < frames; i += 1) {
                        await new Promise((resolve) => requestAnimationFrame(resolve));
                    }
                };

                await waitForStableLayout(60, 3);
                const headerShell = document.getElementById('header-shell');
                const mainShell = document.getElementById('main-shell');
                const shellRects = [headerShell, mainShell]
                    .filter(Boolean)
                    .map((el) => el.getBoundingClientRect())
                    .filter((rect) => rect.width > 0);
                const currentScrollY = window.scrollY || 0;
                const shellBottom = shellRects.length
                    ? Math.max(...shellRects.map((rect) => rect.bottom + currentScrollY))
                    : 0;
                const docEl = document.documentElement;
                const body = document.body;
                const captureHeight = Math.ceil(Math.max(
                    docEl ? docEl.scrollHeight : 0,
                    docEl ? docEl.offsetHeight : 0,
                    docEl ? docEl.clientHeight : 0,
                    body ? body.scrollHeight : 0,
                    body ? body.offsetHeight : 0,
                    body ? body.clientHeight : 0,
                    shellBottom,
                    window.innerHeight || 0
                ));
                const contentLeft = shellRects.length
                    ? Math.min(...shellRects.map((rect) => rect.left))
                    : 0;
                const contentRight = shellRects.length
                    ? Math.max(...shellRects.map((rect) => rect.right))
                    : Math.max(window.innerWidth || 0, document.documentElement.clientWidth || 0);

                const baseOptions = {
                    backgroundColor: '#070b14',
                    useCORS: true,
                    logging: false,
                    imageTimeout: 0,
                    scale: captureScale,
                    scrollX: 0,
                    scrollY: 0,
                    windowWidth: Math.max(window.innerWidth || 0, document.documentElement.clientWidth || 0),
                    windowHeight: captureHeight,
                    height: captureHeight,
                    onclone: (clonedDoc) => {
                        const stabilizeStyle = clonedDoc.createElement('style');
                        stabilizeStyle.textContent = `
                            * { animation: none !important; transition: none !important; caret-color: transparent !important; }
                            #dashboard-content { opacity: 1 !important; filter: none !important; }
                            .draggable-item { transform: none !important; }
                            #view-menu-dropdown { display: none !important; }
                            html, body { text-rendering: geometricPrecision !important; -webkit-font-smoothing: antialiased !important; }
                            #export-html-btn, #export-jpeg-btn, #show-upload-btn, #view-menu-btn { white-space: nowrap !important; min-width: max-content !important; }
                            #export-html-btn span, #export-jpeg-btn span, #show-upload-btn span, #view-menu-btn span { white-space: nowrap !important; line-height: 1 !important; display: inline-flex !important; align-items: center !important; }
                        `;
                        clonedDoc.head.appendChild(stabilizeStyle);
                    }
                };

                const captureOnce = async (preferForeignObject) => {
                    try {
                        return await window.html2canvas(document.body, {
                            ...baseOptions,
                            foreignObjectRendering: preferForeignObject
                        });
                    } catch (err) {
                        if (preferForeignObject) {
                            return await window.html2canvas(document.body, {
                                ...baseOptions,
                                foreignObjectRendering: false
                            });
                        }
                        throw err;
                    }
                };

                capturedCanvas = await captureOnce(true);
                if (!hasContentInLowerRegion(capturedCanvas)) {
                    await waitForStableLayout(140, 2);
                    disposeCanvas(capturedCanvas);
                    capturedCanvas = await captureOnce(false);
                    if (!hasContentInLowerRegion(capturedCanvas)) {
                        await waitForStableLayout(160, 2);
                        disposeCanvas(capturedCanvas);
                        capturedCanvas = await captureOnce(false);
                    }
                }
                const cropPadPx = Math.max(4, Math.round(6 * captureScale));
                croppedCanvas = cropCanvasHorizontal(
                    capturedCanvas,
                    contentLeft * captureScale,
                    contentRight * captureScale,
                    cropPadPx
                );
                const jpegData = exportCanvasAsOptimizedJpeg(croppedCanvas, JPEG_TARGET_MAX_BYTES);
                const jpegBlob = dataUrlToBlob(jpegData, 'image/jpeg');
                const fileName = buildTimestampFileName('bitaxe_snapshot').replace(/\.html$/i, '.jpg');
                downloadBlobFile(jpegBlob, fileName);
            } catch (error) {
                alert(t('alert.jpegFail'));
            } finally {
                if (croppedCanvas && croppedCanvas !== capturedCanvas) disposeCanvas(croppedCanvas);
                if (capturedCanvas) disposeCanvas(capturedCanvas);
                window.scrollTo(oldX, oldY);
                if (previousMenuState && refs.viewMenuDropdown) refs.viewMenuDropdown.classList.remove('hidden');
                if (jpegBtn) {
                    jpegBtn.disabled = prevJpegBtnDisabled;
                    jpegBtn.classList.remove('opacity-60', 'cursor-wait');
                }
                isJpegExporting = false;
            }
        }

        // Restore embedded/exported state back into runtime structures.
        function loadProjectState(payload, options = {}) {
            if (!payload || (typeof payload !== 'object')) {
                alert(t('alert.invalidProject'));
                return false;
            }
            const lockDataQuality = options.lockDataQuality === true;
            const layoutPayload = (
                options.layout && typeof options.layout === 'object'
                    ? options.layout
                    : (payload.layout && typeof payload.layout === 'object' ? payload.layout : null)
            );

            const incomingFiles = Array.isArray(payload.rawFilesData)
                ? payload.rawFilesData.map(normalizeFileRecord).filter((file) => file.data.length > 0 || file.timeSeries.length > 0)
                : [];

            if (!incomingFiles.length && Array.isArray(payload.consolidatedData) && payload.consolidatedData.length) {
                const syntheticFile = normalizeFileRecord({
                    id: generateFileId('snapshot_data.csv', Date.now()),
                    name: 'snapshot_data.csv',
                    lastModified: Date.now(),
                    isMaster: true,
                    enabled: true,
                    stats: { totalRows: payload.consolidatedData.length, parsedRows: payload.consolidatedData.length, skippedRows: 0, missingVrRows: 0, usedTempAsVr: false, missingRequiredColumns: [] },
                    data: payload.consolidatedData
                });
                incomingFiles.push(syntheticFile);
            }

            if (!incomingFiles.length) {
                alert(t('alert.noUsableData'));
                return false;
            }

            rawFilesData = incomingFiles;
            ensureMasterConsistency();
            visibleRows = toSafeNumber(payload.visibleRows, DEFAULT_VISIBLE_ROWS);
            applyFilterState(payload.filters || {});
            recomputeAndRender(true);
            if (layoutPayload) {
                applyPanelLayoutSnapshot(layoutPayload, { includeDataQuality: !lockDataQuality });
            }
            if (lockDataQuality) {
                setPanelVisibility('data-quality', false);
            }
            renderViewMenu();

            return true;
        }

        // Drag & drop ordering for dashboard panels.
        function setupGridDrag() {
            if (!refs.grid) return;
            let dragSrcEl = null;

            const getDragPanel = (target) => (
                target?.closest ? target.closest('.draggable-item') : null
            );

            const finishDrag = () => {
                if (dragSrcEl) {
                    dragSrcEl.classList.remove('dragging', 'drag-hidden', 'opacity-50');
                }
                dragSrcEl = null;
                draggedItem = null;
                ensureTablePanelAtBottom();
            };

            refs.grid.addEventListener('dragstart', (event) => {
                if (isReadOnlyShareView) {
                    event.preventDefault();
                    return;
                }

                const target = getDragPanel(event.target);
                if (!target || !refs.grid.contains(target)) return;

                dragSrcEl = target;
                draggedItem = target;
                clearPanelPressedState();

                if (event.dataTransfer) {
                    event.dataTransfer.effectAllowed = 'move';
                    event.dataTransfer.dropEffect = 'move';
                    event.dataTransfer.setData('text/plain', dragSrcEl.dataset.panelId || 'panel');
                }

                setTimeout(() => {
                    if (dragSrcEl) dragSrcEl.classList.add('dragging');
                }, 0);
            });

            refs.grid.addEventListener('dragover', (event) => {
                if (isReadOnlyShareView || !dragSrcEl) return;
                event.preventDefault();
                if (event.dataTransfer) event.dataTransfer.dropEffect = 'move';

                const targetPanel = getDragPanel(event.target);
                if (!targetPanel || targetPanel === dragSrcEl || !refs.grid.contains(targetPanel)) return;

                const rect = targetPanel.getBoundingClientRect();
                const middleX = rect.left + (rect.width / 2);
                const middleY = rect.top + (rect.height / 2);
                const xRatio = (event.clientX - rect.left) / Math.max(rect.width, 1);
                const yRatio = (event.clientY - rect.top) / Math.max(rect.height, 1);
                let insertBefore;

                if (xRatio <= 0.38) {
                    insertBefore = true;
                } else if (xRatio >= 0.62) {
                    insertBefore = false;
                } else {
                    const verticalDominant = Math.abs(event.clientY - middleY) > Math.abs(event.clientX - middleX);
                    insertBefore = verticalDominant ? (yRatio < 0.5) : (event.clientX < middleX);
                }

                const anchor = insertBefore ? targetPanel : targetPanel.nextElementSibling;
                if (anchor !== dragSrcEl) {
                    refs.grid.insertBefore(dragSrcEl, anchor || null);
                }
                ensureTablePanelAtBottom();
            });

            refs.grid.addEventListener('drop', (event) => {
                if (dragSrcEl) event.preventDefault();
                finishDrag();
            });

            refs.grid.addEventListener('dragend', () => {
                finishDrag();
            });
        }

        // Register all UI events in one place for easier maintenance.
        function bindEvents() {
            document.addEventListener('pointerdown', (event) => {
                if (isReadOnlyShareView) return;
                if (event.target?.closest && event.target.closest('[data-drag-handle]')) return;
                if (event.target?.closest && event.target.closest('button, a, input, select, textarea, label, [data-panel-close], [role="button"]')) return;
                const panel = event.target?.closest ? event.target.closest('.draggable-item') : null;
                if (!panel) return;
                panel.classList.add('is-panel-pressed');
            });
            document.addEventListener('pointerup', clearPanelPressedState);
            document.addEventListener('pointercancel', clearPanelPressedState);
            window.addEventListener('blur', clearPanelPressedState);
            window.addEventListener('resize', scheduleResizeUiSync, { passive: true });
            refs.mobileHeaderMenuBtn?.addEventListener('click', (event) => {
                event.stopPropagation();
                if (!isMobileHeaderBehaviorEnabled()) return;
                setMobileHeaderMenuOpen(!mobileHeaderMenuOpen);
            });
            refs.dropZone?.addEventListener('dragover', (event) => { event.preventDefault(); refs.dropZone.classList.add('dragover'); });
            refs.dropZone?.addEventListener('dragleave', () => refs.dropZone.classList.remove('dragover'));
            refs.dropZone?.addEventListener('drop', (event) => {
                event.preventDefault();
                refs.dropZone.classList.remove('dragover');
                appendUploadMode = rawFilesData.length > 0;
                handleFiles(event.dataTransfer.files);
            });
            refs.fileInput?.addEventListener('click', () => { refs.fileInput.value = ''; });
            refs.fileInput?.addEventListener('change', (event) => handleFiles(event.target.files));
            refs.processBtn?.addEventListener('click', processSelectedFiles);
            refs.samplePreviewBtn?.addEventListener('click', loadSampleCsvPreview);
            refs.showUploadBtn?.addEventListener('click', () => openUploadOverlay({ append: true }));
            refs.closeUploadBtn?.addEventListener('click', () => activateDashboardView({ allowUpload: !IS_SNAPSHOT_VIEW }));
            refs.uploadSection?.addEventListener('click', (event) => {
                if (IS_SNAPSHOT_VIEW) return;
                if (!rawFilesData.length) return;
                if (!refs.uploadModal) return;
                const target = event.target;
                if (target?.closest && target.closest('#upload-modal')) return;
                if (isOverlayClickFarOutsideModal(event, refs.uploadModal)) {
                    activateDashboardView({ allowUpload: true });
                }
            });
            document.addEventListener('keydown', (event) => {
                if (event.key !== 'Escape') return;
                if (refs.shareModalOverlay && !refs.shareModalOverlay.classList.contains('hidden')) {
                    closeShareModal();
                }
                if (mobileHeaderMenuOpen) {
                    setMobileHeaderMenuOpen(false);
                }
                if (refs.languageMenu && !refs.languageMenu.classList.contains('hidden')) {
                    setLanguageMenuOpen(false);
                }
                if (IS_SNAPSHOT_VIEW) return;
                if (!refs.uploadSection) return;
                const overlayVisible = refs.uploadSection.style.display !== 'none' && !refs.uploadSection.classList.contains('slide-up-hidden');
                if (!overlayVisible) return;
                if (!rawFilesData.length) return;
                activateDashboardView({ allowUpload: true });
            });
            refs.fileList?.addEventListener('change', (event) => {
                const target = event.target;
                if (!target || target.name !== 'masterFile') return;
                rawFilesData.forEach((file) => { file.isMaster = file.id === target.value; });
                ensureMasterConsistency();
                if (isUploadOverlayVisible()) {
                    renderFileList();
                    return;
                }
                if (consolidatedData.length) recomputeAndRender(false);
                else renderFileList();
            });
            refs.fileList?.addEventListener('click', (event) => {
                const trigger = event.target?.closest ? event.target.closest('[data-action="remove-file"]') : null;
                if (!trigger) return;
                const fileId = trigger.dataset.fileId;
                removeFileById(fileId);
            });
            refs.tableHead?.addEventListener('click', (event) => {
                const button = event.target?.closest ? event.target.closest('.table-sort-btn') : null;
                if (!button) return;
                setTableSort(button.dataset.sortKey);
            });
            refs.quickSortControls?.addEventListener('click', (event) => {
                const button = event.target?.closest ? event.target.closest('.quick-sort-btn') : null;
                if (!button) return;
                const key = button.dataset.quickSort;
                if (key !== 'score' && key !== 'h') return;
                applyQuickDescendingSort(key);
            });
            refs.viewMenuBtn?.addEventListener('click', (event) => {
                if (isReadOnlyShareView) return;
                event.stopPropagation();
                if (!refs.viewMenuDropdown) return;
                const shouldShow = refs.viewMenuDropdown.classList.contains('hidden');
                setHidden(refs.viewMenuDropdown, !shouldShow);
            });
            refs.viewMenuDropdown?.addEventListener('click', (event) => event.stopPropagation());
            refs.viewMenuList?.addEventListener('change', (event) => {
                if (isReadOnlyShareView) return;
                const input = event.target;
                const panelId = input?.dataset?.panelToggle;
                if (!panelId) return;
                if (panelId === 'data-quality') {
                    dataQualityPinnedByUser = Boolean(input.checked);
                    if (dataQualityPinnedByUser) {
                        if (dataQualityAutoCloseTimer) {
                            clearTimeout(dataQualityAutoCloseTimer);
                            dataQualityAutoCloseTimer = null;
                        }
                        stopDataQualityCountdown();
                    }
                }
                setPanelVisibility(panelId, Boolean(input.checked));
            });
            refs.viewShowAllBtn?.addEventListener('click', () => {
                if (isReadOnlyShareView) return;
                dataQualityPinnedByUser = true;
                if (dataQualityAutoCloseTimer) {
                    clearTimeout(dataQualityAutoCloseTimer);
                    dataQualityAutoCloseTimer = null;
                }
                stopDataQualityCountdown();
                getPanelElements().forEach((panel) => setPanelVisibility(panel.dataset.panelId, true));
            });
            document.addEventListener('click', (event) => {
                const closeBtn = event.target?.closest ? event.target.closest('[data-panel-close]') : null;
                if (closeBtn) {
                    if (isReadOnlyShareView) return;
                    if (closeBtn.dataset.panelClose === 'data-quality') {
                        dataQualityPinnedByUser = false;
                    }
                    setPanelVisibility(closeBtn.dataset.panelClose, false);
                    return;
                }
                if (isMobileHeaderBehaviorEnabled() && mobileHeaderMenuOpen) {
                    const clickedInsideMobileMenu = Boolean(
                        (event.target?.closest && event.target.closest('#mobile-header-menu-panel')) ||
                        (event.target?.closest && event.target.closest('#mobile-header-menu-btn'))
                    );
                    if (!clickedInsideMobileMenu) {
                        setMobileHeaderMenuOpen(false);
                    }
                }
                if (refs.languageMenu && refs.languageToggleBtn && !refs.languageMenu.classList.contains('hidden')) {
                    const clickedInsideLanguagePicker = event.target?.closest && event.target.closest('#language-picker');
                    if (!clickedInsideLanguagePicker) setLanguageMenuOpen(false);
                }
                if (!refs.viewMenuDropdown || !refs.viewMenuBtn) return;
                if (refs.viewMenuDropdown.classList.contains('hidden')) return;
                if (event.target?.closest && event.target.closest('#view-menu-dropdown')) return;
                if (event.target?.closest && event.target.closest('#view-menu-btn')) return;
                setHidden(refs.viewMenuDropdown, true);
            });
            refs.shareModalCopyBtn?.addEventListener('click', async () => {
                const link = String(refs.shareModalLinkInput?.value || '').trim();
                if (!link) return;
                const copied = await copyTextToClipboard(link);
                setShareModalStatus(
                    copied ? t('share.modal.manualCopied') : t('alert.shareCopyFallback', { url: link }),
                    copied ? 'success' : 'warn'
                );
                if (copied) refs.shareModalLinkInput?.select();
            });
            refs.shareModalCloseBtn?.addEventListener('click', closeShareModal);
            refs.shareModalOverlay?.addEventListener('click', (event) => {
                const clickedInsideCard = event.target?.closest && event.target.closest('#share-modal-card');
                if (clickedInsideCard) return;
                closeShareModal();
            });
            refs.shareBtn?.addEventListener('click', createShareLink);
            refs.exportHtmlBtn?.addEventListener('click', exportCurrentViewAsHtml);
            refs.exportJpegBtn?.addEventListener('click', exportCurrentViewAsJpeg);
            refs.resetFiltersBtn?.addEventListener('click', resetFilters);
            refs.loadMoreBtn?.addEventListener('click', () => {
                visibleRows += 20;
                scheduleTableRender();
            });
            getFilterControlBindings().forEach((binding) => {
                if (binding.input) {
                    binding.input.addEventListener('input', () => {
                        syncFilterRangeFromInput(binding);
                        scheduleTableRender({ resetRows: true });
                    });
                    binding.input.addEventListener('blur', () => {
                        syncFilterRangeFromInput(binding, { sanitizeInput: true });
                        scheduleTableRender({ resetRows: true });
                    });
                    binding.input.addEventListener('keydown', (event) => {
                        if (event.key !== 'Enter') return;
                        event.preventDefault();
                        binding.input.blur();
                    });
                }
                if (binding.range) {
                    binding.range.addEventListener('input', () => {
                        syncFilterInputFromRange(binding);
                        scheduleTableRender({ resetRows: true });
                    });
                }
            });
        }

        // App boot sequence.
        async function initApp() {
            installInAppAlertBridge();
            syncBrandVersionLine();
            initThemeToggle();
            if (IS_WEBDRIVER_SESSION) {
                // Stabilize automated Safari flows by preloading chart libs up front.
                ensureChartLibrariesLoaded().catch(() => {});
            } else {
                // Do not compete with first paint; warm up chart libs during idle time.
                scheduleChartLibrariesWarmup();
            }
            setupGridDrag();
            bindEvents();
            syncMobileHeaderMenuLayout();
            initLanguageSelectorControl();
            initPanelState();
            setShareReadOnlyMode(false);
            updateTableSortIndicators();
            renderDataQualitySummary(false);
            refreshFilterControlsFromData();
            syncControlVisibility();
            const requestedShareToken = getShareTokenFromUrl();
            const requestedImportToken = getImportTokenFromUrl();
            if (requestedImportToken || requestedShareToken) {
                setUploadOverlayShareLoadingHidden(true);
                if (refs.uploadSection) {
                    refs.uploadSection.classList.remove('slide-up-hidden');
                    refs.uploadSection.style.display = 'flex';
                }
                if (refs.dashboardContent) {
                    refs.dashboardContent.classList.remove('opacity-30', 'blur-sm');
                    refs.dashboardContent.classList.add('opacity-100', 'blur-0');
                }
                const hideSamplePreviewDuringPrefetch = Boolean(requestedShareToken) && !requestedImportToken;
                setSamplePreviewVisibility(!hideSamplePreviewDuringPrefetch);
                if (refs.closeUploadBtn) refs.closeUploadBtn.classList.add('hidden');
            }

            if (IS_EMBEDDED_STATE) {
                const loaded = loadProjectState(EMBEDDED_EXPORT_STATE, { sourceName: 'gomulu proje', silentLog: true });
                if (loaded) {
                    if (IS_SNAPSHOT_VIEW) activateDashboardView({ allowUpload: false });
                    else activateDashboardView({ allowUpload: true });
                    return;
                }
            }

            if (requestedShareToken) {
                const sharedLoaded = await loadSharedReportFromUrl(requestedShareToken);
                if (sharedLoaded) return;
            }

            if (requestedImportToken) {
                const importedLoaded = await loadAutotuneImportFromUrl(requestedImportToken);
                if (importedLoaded) return;
            }

            if (requestedImportToken && !requestedShareToken) {
                keepUploadOverlayClosedOnBoot = true;
                setShareReadOnlyMode(false);
                activateDashboardView({ allowUpload: true });
                return;
            }
            if (requestedImportToken) setShareReadOnlyMode(false);
            if (requestedShareToken) setShareReadOnlyMode(false);
            showInitialUploadView();
        }

        initApp();
    </script>
</body>
</html>
