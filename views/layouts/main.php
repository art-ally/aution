<?php
$user = current_user();
$unreadCount = unread_notifications_count();
$watchlistCount = count(user_watchlist_ids());
$brandName = platform_setting('brand_name', 'ArtAlly');
$flashSuccess = flash('success');
$flashError = flash('error');
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$isArtistDashboard = ($currentPath === '/artist' || str_starts_with($currentPath, '/artist/'));
?><!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? ($brandName . ' — International Fine Art Auctions')) ?></title>
    <meta name="description" content="<?= e($metaDescription ?? 'ArtAlly is an international digital art auction house dedicated to original works from independent artists and distinguished private collections.') ?>">
    <link rel="canonical" href="http://<?= e($_SERVER['HTTP_HOST'] ?? 'localhost:8000') ?><?= e(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH)) ?>">

    <!-- Editorial Typography: Cormorant Garamond (Serif) & Plus Jakarta Sans (Sans) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Tailwind CSS & Custom Institutional Theme -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        ivory: '#FAF8F5',
                        alabaster: '#F3EFEA',
                        obsidian: '#111111',
                        charcoal: '#2B2B2B',
                        stone: '#76716B',
                        bronze: '#8C6D46',
                        bronzeLight: '#A88961',
                        bronzeDark: '#6B5132',
                        hairline: '#E5E0D8'
                    },
                    fontFamily: {
                        serif: ['"Cormorant Garamond"', 'Georgia', 'serif'],
                        sans: ['"Plus Jakarta Sans"', '-apple-system', 'BlinkMacSystemFont', 'sans-serif']
                    },
                    letterSpacing: {
                        editorial: '0.18em',
                        widest: '0.24em'
                    }
                }
            }
        }
    </script>

    <!-- Alpine.js for Interactive Modals, Dropdowns, Gallery Zoom & Multi-Step Workflows -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        [x-cloak] { display: none !important; }
        body {
            background-color: #FAF8F5;
            color: #111111;
            font-family: 'Plus Jakarta Sans', sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        h1, h2, h3, h4, .font-serif {
            font-family: 'Cormorant Garamond', Georgia, serif;
        }
        .gallery-image-zoom {
            transition: transform 0.7s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .group:hover .gallery-image-zoom {
            transform: scale(1.03);
        }
        /* Custom subtle scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #FAF8F5; }
        ::-webkit-scrollbar-thumb { background: #C8C1B6; }
        ::-webkit-scrollbar-thumb:hover { background: #8C6D46; }
    </style>
</head>
<body class="min-h-screen flex flex-col bg-ivory text-obsidian selection:bg-bronze selection:text-white" x-data="{ mobileMenuOpen: false, searchModalOpen: false }">

    <?php if (!$isArtistDashboard): ?>
    <!-- Top Institutional Announcement Bar -->
    <div class="bg-obsidian text-ivory text-[11px] tracking-editorial uppercase py-2.5 px-4 sm:px-8 border-b border-charcoal">
        <div class="max-w-[1440px] mx-auto flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center space-x-4">
                <span class="text-bronzeLight font-medium">Autumn Evening Sales</span>
                <span class="hidden md:inline text-stone">|</span>
                <span class="hidden md:inline text-gray-300">Geneva · New York · London · Paris — Timed Bidding Open</span>
            </div>
            <div class="flex items-center space-x-5">
                <a href="/how-it-works" class="hover:text-bronzeLight transition">How Bidding Works</a>
                <a href="/buyer-guide" class="hidden sm:inline hover:text-bronzeLight transition">Buyer’s Guide</a>
                <a href="/sell-your-art" class="text-bronzeLight hover:text-white transition font-medium">Consign Artwork</a>
            </div>
        </div>
    </div>

    <!-- Sticky Main Navigation Header -->
    <header class="sticky top-0 z-40 bg-ivory/95 backdrop-blur-md border-b border-hairline">
        <div class="max-w-[1440px] mx-auto px-4 sm:px-8">
            <div class="flex items-center justify-between h-20">
                <!-- Brand Logo -->
                <div class="flex items-center space-x-10">
                    <a href="/" class="group flex flex-col">
                        <span class="font-serif text-2xl sm:text-3xl tracking-[0.22em] font-semibold text-obsidian group-hover:text-bronze transition">
                            <?= e($brandName) ?>
                        </span>
                        <span class="text-[9px] tracking-[0.32em] uppercase text-stone -mt-1">
                            Fine Art &amp; Auctions
                        </span>
                    </a>

                    <!-- Desktop Primary Navigation -->
                    <nav class="hidden xl:flex items-center space-x-7 text-xs tracking-[0.14em] uppercase font-medium text-charcoal" aria-label="Main Navigation">
                        <a href="/sell-your-art" class="hover:text-bronze transition py-2">Sell Your Art</a>
                        <a href="/journal" class="hover:text-bronze transition py-2">Stories &amp; Journal</a>
                    </nav>
                </div>

                <!-- Right Utility Controls: Search, Watchlist, Account -->
                <div class="flex items-center space-x-5">
                    <!-- Search Trigger -->
                    <button @click="searchModalOpen = true" type="button" class="flex items-center space-x-2 text-xs uppercase tracking-widest text-charcoal hover:text-bronze transition py-1.5 px-2" aria-label="Search Catalogue">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-4.35-4.35m1.85-5.15a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <span class="hidden md:inline">Search</span>
                    </button>

                    <!-- Watchlist / Favorites -->
                    <a href="/buyer/watchlist" class="relative flex items-center space-x-1.5 text-xs uppercase tracking-widest text-charcoal hover:text-bronze transition py-1.5" title="Collector Watchlist">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                        </svg>
                        <span class="hidden sm:inline">Favorites</span>
                        <?php if ($watchlistCount > 0): ?>
                            <span class="inline-flex items-center justify-center px-1.5 py-0.5 text-[10px] font-semibold bg-bronze text-white">
                                <?= $watchlistCount ?>
                            </span>
                        <?php endif; ?>
                    </a>

                    <?php if ($user): ?>
                        <!-- Authenticated Account Dropdown -->
                        <div class="relative" x-data="{ acctOpen: false }">
                            <button @click="acctOpen = !acctOpen" @click.away="acctOpen = false" type="button" class="flex items-center space-x-2 text-xs uppercase tracking-widest border border-obsidian px-3.5 py-2 hover:bg-obsidian hover:text-ivory transition">
                                <span><?= e(explode(' ', $user['name'])[0]) ?></span>
                                <?php if ($unreadCount > 0): ?>
                                    <span class="w-2 h-2 rounded-full bg-bronze inline-block" title="<?= $unreadCount ?> unread notifications"></span>
                                <?php endif; ?>
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 9l-7 7-7-7"/></svg>
                            </button>

                            <div x-cloak x-show="acctOpen" x-transition.opacity class="absolute right-0 mt-2 w-64 bg-white border border-hairline shadow-xl py-3 px-5 z-50 text-xs">
                                <div class="pb-3 mb-3 border-b border-hairline">
                                    <p class="font-medium text-obsidian truncate"><?= e($user['name']) ?></p>
                                    <p class="text-[11px] text-stone truncate"><?= e($user['email']) ?></p>
                                    <span class="inline-block mt-1 text-[10px] tracking-widest uppercase px-2 py-0.5 bg-alabaster text-bronze font-semibold">
                                        <?= e(strtoupper($user['role'] ?? 'collector')) ?>
                                    </span>
                                </div>

                                <div class="space-y-2.5 uppercase tracking-wider text-[11px]">
                                    <?php if (\App\Core\Auth::hasRole('artist')): ?>
                                        <a href="/artist/dashboard" class="block hover:text-bronze transition">Artist Studio Dashboard</a>
                                        <a href="/artist/submit" class="block hover:text-bronze transition">Submit New Artwork</a>
                                        <a href="/artist/submissions" class="block hover:text-bronze transition">Submission Status</a>
                                        <a href="/artist/sales" class="block hover:text-bronze transition">Sales &amp; Payouts</a>
                                    <?php endif; ?>

                                    <a href="/buyer/dashboard" class="block hover:text-bronze transition">
                                        Collector Dashboard
                                        <?php if ($unreadCount > 0): ?>
                                            <span class="ml-1 px-1.5 py-0.5 text-[10px] bg-bronze text-white"><?= $unreadCount ?></span>
                                        <?php endif; ?>
                                    </a>
                                    <a href="/buyer/orders" class="block hover:text-bronze transition">Acquisitions &amp; Invoices</a>
                                    <a href="/buyer/watchlist" class="block hover:text-bronze transition">Saved Watchlist</a>
                                </div>

                                <form action="/logout" method="POST" class="mt-4 pt-3 border-t border-hairline">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="w-full text-left text-[11px] uppercase tracking-widest text-red-800 hover:text-red-600 transition">
                                        Sign Out
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="hidden sm:flex items-center space-x-3">
                            <a href="/login" class="text-xs uppercase tracking-widest text-obsidian hover:text-bronze transition px-2 py-2">
                                Sign In
                            </a>
                            <a href="/register" class="text-xs uppercase tracking-widest bg-obsidian text-ivory px-4 py-2.5 hover:bg-bronze transition">
                                Register
                            </a>
                        </div>
                    <?php endif; ?>

                    <!-- Mobile Menu Button -->
                    <button @click="mobileMenuOpen = !mobileMenuOpen" type="button" class="xl:hidden p-2 text-obsidian hover:text-bronze" aria-label="Toggle Menu">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path x-show="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 12h16M4 18h16"/>
                            <path x-cloak x-show="mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Responsive Mobile Navigation Drawer -->
        <div x-cloak x-show="mobileMenuOpen" x-transition class="xl:hidden bg-white border-b border-hairline px-6 py-6 space-y-4">
            <div class="grid grid-cols-1 gap-3 text-xs uppercase tracking-widest font-medium">
                <a href="/sell-your-art" class="py-1.5 border-b border-hairline text-bronze">Sell Your Art</a>
                <a href="/journal" class="py-1.5 border-b border-hairline">Stories &amp; Journal</a>
            </div>
            <?php if (!$user): ?>
                <div class="pt-2 flex space-x-3">
                    <a href="/login" class="flex-1 text-center py-2.5 border border-obsidian text-xs uppercase tracking-widest">Sign In</a>
                    <a href="/register" class="flex-1 text-center py-2.5 bg-obsidian text-ivory text-xs uppercase tracking-widest">Register</a>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <!-- Global Search Modal -->
    <div x-cloak x-show="searchModalOpen" @keydown.escape.window="searchModalOpen = false" class="fixed inset-0 z-50 flex items-start justify-center bg-obsidian/70 backdrop-blur-sm pt-20 px-4">
        <div @click.away="searchModalOpen = false" class="bg-ivory border border-hairline w-full max-w-3xl p-8 shadow-2xl">
            <div class="flex items-center justify-between mb-6">
                <span class="text-xs uppercase tracking-widest text-stone">Search the ArtAlly Archive</span>
                <button @click="searchModalOpen = false" type="button" class="text-stone hover:text-obsidian text-xs uppercase tracking-widest">Close [ESC]</button>
            </div>
            <form action="/catalogue" method="GET" class="flex flex-col sm:flex-row gap-3">
                <input type="text" name="q" placeholder="Search by artwork title, artist name, medium, or provenance..." class="flex-1 bg-white border border-hairline px-4 py-3.5 text-sm focus:outline-none focus:border-bronze" required>
                <button type="submit" class="bg-obsidian text-ivory px-8 py-3.5 text-xs uppercase tracking-widest hover:bg-bronze transition">
                    Search Catalogue
                </button>
            </form>
            <div class="mt-6 flex flex-wrap items-center gap-2 text-xs text-stone">
                <span class="uppercase tracking-wider text-[10px]">Popular Searches:</span>
                <a href="/catalogue?q=Lindqvist" class="underline hover:text-bronze">Soren Lindqvist</a>
                <span>·</span>
                <a href="/catalogue?q=Bronze" class="underline hover:text-bronze">Patinated Bronze</a>
                <span>·</span>
                <a href="/catalogue?q=Platinum" class="underline hover:text-bronze">Platinum Print</a>
                <span>·</span>
                <a href="/catalogue?q=Triptych" class="underline hover:text-bronze">Gold Leaf Triptych</a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Flash Notifications Banner -->
    <?php if ($flashSuccess): ?>
        <div class="bg-[#1A2E22] text-ivory px-4 sm:px-8 py-3.5 border-b border-bronze/40">
            <div class="max-w-[1440px] mx-auto flex items-center justify-between text-xs tracking-wide">
                <div class="flex items-center space-x-3">
                    <span class="inline-block w-2 h-2 bg-bronzeLight rounded-full"></span>
                    <span><?= e($flashSuccess) ?></span>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($flashError): ?>
        <div class="bg-[#3B1818] text-ivory px-4 sm:px-8 py-3.5 border-b border-red-400/30">
            <div class="max-w-[1440px] mx-auto flex items-center justify-between text-xs tracking-wide">
                <div class="flex items-center space-x-3">
                    <span class="inline-block w-2 h-2 bg-red-400 rounded-full"></span>
                    <span><?= e($flashError) ?></span>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Main Page Content -->
    <main class="flex-1">
        <?= $content ?>
    </main>

    <?php if (!$isArtistDashboard): ?>
    <!-- Newsletter & Institutional Footer -->
    <footer class="bg-obsidian text-ivory border-t border-charcoal mt-24">
        <!-- Curatorial Newsletter Dispatch -->
        <div class="border-b border-charcoal">
            <div class="max-w-[1440px] mx-auto px-4 sm:px-8 py-16 grid grid-cols-1 lg:grid-cols-12 gap-10 items-center">
                <div class="lg:col-span-7">
                    <p class="text-[11px] uppercase tracking-widest text-bronzeLight mb-2">The Curatorial Dispatch</p>
                    <h3 class="font-serif text-3xl sm:text-4xl font-normal text-ivory leading-tight">
                        Receive Private Catalogue Previews, Studio Interviews &amp; Closing Alerts
                    </h3>
                </div>
                <div class="lg:col-span-5">
                    <form action="/newsletter/subscribe" method="POST" class="flex flex-col sm:flex-row gap-3">
                        <?= csrf_field() ?>
                        <input type="email" name="email" placeholder="Enter your email address..." required class="flex-1 bg-charcoal border border-stone/40 text-ivory px-4 py-3.5 text-sm focus:outline-none focus:border-bronzeLight">
                        <button type="submit" class="bg-bronze text-white px-7 py-3.5 text-xs uppercase tracking-widest hover:bg-bronzeLight transition whitespace-nowrap">
                            Subscribe
                        </button>
                    </form>
                    <p class="text-[11px] text-stone mt-2.5">By subscribing, you agree to our Collector Confidentiality &amp; Privacy Policy.</p>
                </div>
            </div>
        </div>

        <!-- Footer Links -->
        <div class="max-w-[1440px] mx-auto px-4 sm:px-8 py-16 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-10 text-xs">
            <div class="lg:col-span-2 pr-8">
                <span class="font-serif text-2xl tracking-[0.22em] text-ivory font-semibold block mb-3"><?= e($brandName) ?></span>
                <p class="text-stone leading-relaxed text-xs max-w-md mb-6">
                    An international art auction house dedicated to connecting discerning collectors with vetted living artists and distinguished private collections through transparent timed auctions.
                </p>
                <p class="text-[11px] uppercase tracking-widest text-bronzeLight">
                    Geneva · New York · London · Paris
                </p>
            </div>

            <div>
                <h4 class="font-sans text-[11px] uppercase tracking-widest text-bronzeLight font-semibold mb-4">Auctions &amp; Art</h4>
                <ul class="space-y-2.5 text-gray-300">
                    <li><a href="/auctions" class="hover:text-white transition">Current Timed Auctions</a></li>
                    <li><a href="/upcoming-auctions" class="hover:text-white transition">Upcoming Evening Sales</a></li>
                    <li><a href="/catalogue" class="hover:text-white transition">Complete Artwork Catalogue</a></li>
                    <li><a href="/categories" class="hover:text-white transition">Curatorial Departments</a></li>
                    <li><a href="/journal" class="hover:text-white transition">Stories &amp; Journal</a></li>
                </ul>
            </div>

            <div>
                <h4 class="font-sans text-[11px] uppercase tracking-widest text-bronzeLight font-semibold mb-4">For Artists &amp; Sellers</h4>
                <ul class="space-y-2.5 text-gray-300">
                    <li><a href="/sell-your-art" class="hover:text-white transition">Consign &amp; Sell Your Art</a></li>
                    <li><a href="/artist/submit" class="hover:text-white transition">Submit Artwork for Review</a></li>
                    <li><a href="/seller-guide" class="hover:text-white transition">Seller &amp; Commission Guide</a></li>
                    <li><a href="/register?role=artist" class="hover:text-white transition">Artist Registration</a></li>
                    <li><a href="/artist/dashboard" class="hover:text-white transition">Artist Studio Portal</a></li>
                </ul>
            </div>

            <div>
                <h4 class="font-sans text-[11px] uppercase tracking-widest text-bronzeLight font-semibold mb-4">Client Advisory</h4>
                <ul class="space-y-2.5 text-gray-300">
                    <li><a href="/how-it-works" class="hover:text-white transition">How Timed Auctions Work</a></li>
                    <li><a href="/buyer-guide" class="hover:text-white transition">Buyer &amp; Paddle Guide</a></li>
                    <li><a href="/about" class="hover:text-white transition">About ArtAlly</a></li>
                    <li><a href="/contact" class="hover:text-white transition">Private Client Concierge</a></li>
                    <li><a href="/terms" class="hover:text-white transition">Conditions of Sale &amp; Terms</a></li>
                    <li><a href="/privacy" class="hover:text-white transition">Privacy Policy</a></li>
                </ul>
            </div>
        </div>

        <div class="border-t border-charcoal py-6 px-4 sm:px-8 text-[11px] text-stone">
            <div class="max-w-[1440px] mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">
                <p>© 2020 <?= e($brandName) ?> International Fine Art Auctioneers. All rights reserved.</p>
                <div class="flex items-center space-x-6">
                    <a href="/sitemap.xml" class="hover:text-ivory transition">XML Sitemap</a>
                    <a href="/terms" class="hover:text-ivory transition">Conditions of Business</a>
                    <a href="/privacy" class="hover:text-ivory transition">Collector Confidentiality</a>
                </div>
            </div>
        </div>
    </footer>
    <?php endif; ?>

    <!-- Live Auction Countdown Timer Script -->
    <script>
        function updateAuctionCountdowns() {
            const nodes = document.querySelectorAll('[data-countdown]');
            const now = Math.floor(Date.now() / 1000);
            nodes.forEach(el => {
                const targetStr = el.getAttribute('data-countdown');
                if (!targetStr) return;
                const targetTime = Math.floor(new Date(targetStr.replace(' ', 'T') + 'Z').getTime() / 1000);
                const diff = targetTime - now;
                if (isNaN(diff)) return;

                if (diff <= 0) {
                    el.textContent = 'Auction Closed';
                    return;
                }
                const d = Math.floor(diff / 86400);
                const h = Math.floor((diff % 86400) / 3600);
                const m = Math.floor((diff % 3600) / 60);
                const s = diff % 60;

                if (d > 0) {
                    el.textContent = `${d}d ${h}h ${m}m ${s}s`;
                } else {
                    el.textContent = `${h}h ${m}m ${s}s`;
                }
            });
        }
        document.addEventListener('DOMContentLoaded', () => {
            updateAuctionCountdowns();
            setInterval(updateAuctionCountdowns, 1000);
        });
    </script>
</body>
</html>
