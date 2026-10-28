<?php
$heroLot = $featuredAuctions[0] ?? null;
$watchedIds = user_watchlist_ids();
?>

<!-- Section 1: Editorial Hero -->
<section class="relative border-b border-hairline bg-ivory overflow-hidden">
    <div class="max-w-[1440px] mx-auto px-4 sm:px-8 py-12 lg:py-20">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
            <!-- Left Column: Institutional Typography -->
            <div class="lg:col-span-6 space-y-8">
                <div class="inline-flex items-center space-x-3 border-b border-bronze pb-2">
                    <span class="w-2 h-2 rounded-full bg-bronze"></span>
                    <span class="text-[11px] uppercase tracking-widest text-bronze font-semibold">
                        Autumn Curated Evening Sale · Live Timed Bidding
                    </span>
                </div>

                <h1 class="font-serif text-5xl sm:text-6xl xl:text-7xl font-normal text-obsidian leading-[1.06] tracking-tight">
                    Art Worth Discovering. <br>
                    <span class="italic text-charcoal">Stories Worth Collecting.</span>
                </h1>

                <p class="text-base sm:text-lg text-charcoal/80 leading-relaxed max-w-xl font-light">
                    Discover exceptional works from independent artists, established creators, and remarkable private collections—vetted by our curatorial board and offered through transparent timed auctions.
                </p>

                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-4 pt-2">
                    <a href="/auctions" class="bg-obsidian text-ivory px-8 py-4 text-xs uppercase tracking-widest font-medium hover:bg-bronze transition text-center">
                        Explore Current Auctions
                    </a>
                    <a href="/sell-your-art" class="border border-obsidian text-obsidian px-8 py-4 text-xs uppercase tracking-widest font-medium hover:bg-obsidian hover:text-ivory transition text-center">
                        Sell Your Artwork
                    </a>
                </div>

                <!-- Institutional Pillars -->
                <div class="grid grid-cols-3 gap-6 pt-8 border-t border-hairline text-xs">
                    <div>
                        <p class="font-serif text-2xl font-semibold text-obsidian">100%</p>
                        <p class="text-[11px] uppercase tracking-wider text-stone mt-0.5">Curatorial Vetting</p>
                    </div>
                    <div>
                        <p class="font-serif text-2xl font-semibold text-obsidian">5-Min</p>
                        <p class="text-[11px] uppercase tracking-wider text-stone mt-0.5">Anti-Sniping Rule</p>
                    </div>
                    <div>
                        <p class="font-serif text-2xl font-semibold text-obsidian">Global</p>
                        <p class="text-[11px] uppercase tracking-wider text-stone mt-0.5">Museum Logistics</p>
                    </div>
                </div>
            </div>

            <!-- Right Column: Featured Flagship Lot Presentation -->
            <div class="lg:col-span-6">
                <?php if ($heroLot): ?>
                    <div class="bg-white border border-hairline p-4 sm:p-6 shadow-sm">
                        <div class="relative aspect-[4/3] bg-alabaster overflow-hidden mb-6">
                            <a href="/artworks/<?= e($heroLot['slug']) ?>">
                                <img
                                    src="<?= e($heroLot['primary_image']) ?>"
                                    alt="<?= e($heroLot['title']) ?>"
                                    class="w-full h-full object-cover gallery-image-zoom"
                                >
                            </a>
                            <div class="absolute top-4 left-4 bg-obsidian/90 text-ivory text-[10px] uppercase tracking-widest px-3 py-1.5">
                                Flagship Lot · <?= e($heroLot['auction']['lot_number'] ?? 'LOT 01') ?>
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 border-t border-hairline pt-5">
                            <div>
                                <p class="text-[11px] uppercase tracking-widest text-bronze font-semibold mb-1">
                                    <?= e($heroLot['artist_name']) ?> (<?= e($heroLot['artist_country']) ?>)
                                </p>
                                <h2 class="font-serif text-2xl sm:text-3xl italic text-obsidian">
                                    <a href="/artworks/<?= e($heroLot['slug']) ?>" class="hover:text-bronze transition">
                                        <?= e($heroLot['title']) ?>, <?= e((string)$heroLot['year_created']) ?>
                                    </a>
                                </h2>
                                <p class="text-xs text-stone mt-1"><?= e($heroLot['medium']) ?></p>
                            </div>
                            <div class="sm:text-right">
                                <p class="text-[10px] uppercase tracking-widest text-stone">Current Bid</p>
                                <p class="font-serif text-2xl font-semibold text-obsidian">
                                    <?= format_money($heroLot['auction']['current_bid'] ?? $heroLot['starting_bid']) ?>
                                </p>
                                <p class="text-[11px] text-bronze font-medium" data-countdown="<?= e($heroLot['auction']['ends_at'] ?? '') ?>">
                                    <?= e(time_remaining_label($heroLot['auction']['ends_at'] ?? '')) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Section 2: Featured Live Auctions -->
<section class="max-w-[1440px] mx-auto px-4 sm:px-8 py-20">
    <div class="flex flex-col md:flex-row md:items-end justify-between mb-12 border-b border-hairline pb-6">
        <div>
            <p class="text-[11px] uppercase tracking-widest text-bronze font-semibold mb-2">Current Bidding</p>
            <h2 class="font-serif text-4xl sm:text-5xl font-normal text-obsidian">Featured Live Auctions</h2>
        </div>
        <a href="/auctions" class="mt-4 md:mt-0 text-xs uppercase tracking-widest text-obsidian hover:text-bronze transition font-semibold">
            View All Active Lots (<?= count($featuredAuctions) ?>) →
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        <?php foreach (array_slice($featuredAuctions, 0, 6) as $lot): ?>
            <?php require VIEWS_PATH . '/partials/artwork-card.php'; ?>
        <?php endforeach; ?>
    </div>
</section>

<!-- Section 3: Upcoming Auctions Editorial Preview -->
<?php if (!empty($upcomingAuctions)): ?>
<section class="bg-alabaster border-y border-hairline py-20">
    <div class="max-w-[1440px] mx-auto px-4 sm:px-8">
        <div class="flex flex-col md:flex-row md:items-end justify-between mb-12 border-b border-hairline pb-6">
            <div>
                <p class="text-[11px] uppercase tracking-widest text-bronze font-semibold mb-2">Advance Exhibition Catalogue</p>
                <h2 class="font-serif text-4xl sm:text-5xl font-normal text-obsidian">Upcoming Evening Sales</h2>
            </div>
            <a href="/upcoming-auctions" class="mt-4 md:mt-0 text-xs uppercase tracking-widest text-obsidian hover:text-bronze transition font-semibold">
                Explore Upcoming Calendar →
            </a>
        </div>

        <?php foreach ($upcomingAuctions as $upLot): ?>
            <div class="bg-white border border-hairline grid grid-cols-1 lg:grid-cols-12 overflow-hidden">
                <div class="lg:col-span-5 aspect-[4/3] lg:aspect-auto bg-obsidian overflow-hidden">
                    <img src="<?= e($upLot['primary_image']) ?>" alt="<?= e($upLot['title']) ?>" class="w-full h-full object-cover">
                </div>
                <div class="lg:col-span-7 p-8 sm:p-12 flex flex-col justify-between">
                    <div class="space-y-4">
                        <div class="flex flex-wrap items-center gap-3 text-[11px] uppercase tracking-widest">
                            <span class="bg-obsidian text-ivory px-3 py-1"><?= e($upLot['auction']['title'] ?? 'Upcoming Flagship Sale') ?></span>
                            <span class="text-bronze font-semibold">Opens <?= e(gmdate('d M Y · H:i', strtotime($upLot['auction']['starts_at']))) ?> UTC</span>
                        </div>
                        <h3 class="font-serif text-3xl sm:text-4xl text-obsidian">
                            <?= e($upLot['artist_name']) ?> — <span class="italic"><?= e($upLot['title']) ?></span>
                        </h3>
                        <p class="text-sm text-charcoal/80 leading-relaxed max-w-2xl">
                            <?= e($upLot['description']) ?>
                        </p>
                    </div>

                    <div class="mt-8 pt-6 border-t border-hairline flex flex-wrap items-center justify-between gap-6">
                        <div class="flex items-center space-x-8">
                            <div>
                                <p class="text-[10px] uppercase tracking-widest text-stone">Estimate Range</p>
                                <p class="font-serif text-xl font-semibold text-obsidian">
                                    <?= format_money($upLot['estimate_low']) ?> – <?= format_money($upLot['estimate_high']) ?>
                                </p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-widest text-stone">Opening Bid</p>
                                <p class="font-serif text-xl font-semibold text-obsidian">
                                    <?= format_money($upLot['starting_bid']) ?>
                                </p>
                            </div>
                        </div>
                        <a href="/artworks/<?= e($upLot['slug']) ?>" class="bg-obsidian text-ivory px-7 py-3.5 text-xs uppercase tracking-widest hover:bg-bronze transition">
                            Preview Lot &amp; Register Interest
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- Section 4: Explore by Category (10 Curatorial Departments) -->
<section class="max-w-[1440px] mx-auto px-4 sm:px-8 py-20">
    <div class="flex flex-col md:flex-row md:items-end justify-between mb-12 border-b border-hairline pb-6">
        <div>
            <p class="text-[11px] uppercase tracking-widest text-bronze font-semibold mb-2">Curatorial Departments</p>
            <h2 class="font-serif text-4xl sm:text-5xl font-normal text-obsidian">Explore by Collecting Category</h2>
        </div>
        <a href="/categories" class="mt-4 md:mt-0 text-xs uppercase tracking-widest text-obsidian hover:text-bronze transition font-semibold">
            All 10 Departments →
        </a>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-5">
        <?php foreach ($categories as $cat): ?>
            <a href="/catalogue?category=<?= e($cat['slug']) ?>" class="group relative bg-obsidian overflow-hidden aspect-[4/5] flex items-end p-5 border border-hairline">
                <img
                    src="<?= e($cat['image']) ?>"
                    alt="<?= e($cat['name']) ?>"
                    loading="lazy"
                    class="absolute inset-0 w-full h-full object-cover opacity-75 group-hover:scale-105 group-hover:opacity-60 transition duration-700"
                >
                <div class=" inset-0 bg-gradient-to-t from-obsidian/90 via-obsidian/30 to-transparent absolute"></div>
                <div class="relative z-10">
                    <p class="text-[10px] uppercase tracking-widest text-bronzeLight mb-1">Department</p>
                    <h3 class="font-serif text-xl sm:text-2xl text-ivory font-normal group-hover:text-bronzeLight transition">
                        <?= e($cat['name']) ?>
                    </h3>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- Section 6: Editorial Stories & Journal -->
<section class="max-w-[1440px] mx-auto px-4 sm:px-8 py-20">
    <div class="flex flex-col md:flex-row md:items-end justify-between mb-12 border-b border-hairline pb-6">
        <div>
            <p class="text-[11px] uppercase tracking-widest text-bronze font-semibold mb-2">Editorial &amp; Market Intelligence</p>
            <h2 class="font-serif text-4xl sm:text-5xl font-normal text-obsidian">Stories &amp; Curatorial Journal</h2>
        </div>
        <a href="/journal" class="mt-4 md:mt-0 text-xs uppercase tracking-widest text-obsidian hover:text-bronze transition font-semibold">
            Read All Stories →
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <?php foreach ($articles as $article): ?>
            <article class="group bg-white border border-hairline flex flex-col justify-between">
                <div>
                    <a href="/journal/<?= e($article['slug']) ?>" class="block aspect-[16/10] overflow-hidden bg-alabaster">
                        <img
                            src="<?= e($article['image']) ?>"
                            alt="<?= e($article['title']) ?>"
                            loading="lazy"
                            class="w-full h-full object-cover gallery-image-zoom"
                        >
                    </a>
                    <div class="p-6">
                        <div class="flex items-center justify-between text-[10px] uppercase tracking-widest text-bronze font-semibold mb-2">
                            <span><?= e($article['category']) ?></span>
                            <span class="text-stone"><?= e($article['reading_time'] ?? '5 min read') ?></span>
                        </div>
                        <h3 class="font-serif text-2xl text-obsidian group-hover:text-bronze transition leading-snug mb-3">
                            <a href="/journal/<?= e($article['slug']) ?>"><?= e($article['title']) ?></a>
                        </h3>
                        <p class="text-xs text-charcoal/80 leading-relaxed line-clamp-3">
                            <?= e($article['excerpt']) ?>
                        </p>
                    </div>
                </div>
                <div class="px-6 pb-6 pt-3 border-t border-hairline flex items-center justify-between text-[11px] text-stone">
                    <span>By <?= e($article['author']) ?></span>
                    <a href="/journal/<?= e($article['slug']) ?>" class="uppercase tracking-widest text-obsidian font-semibold group-hover:text-bronze">Read →</a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<!-- Section 7: Sell Your Art Call-To-Action -->
<section class="bg-obsidian text-ivory py-24 border-t border-charcoal">
    <div class="max-w-[1440px] mx-auto px-4 sm:px-8 grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
        <div class="lg:col-span-7 space-y-6">
            <p class="text-[11px] uppercase tracking-widest text-bronzeLight font-semibold">Consign With ArtAlly</p>
            <h2 class="font-serif text-4xl sm:text-6xl font-normal leading-tight">
                Your Art Deserves to Be Discovered.
            </h2>
            <p class="text-gray-300 text-sm sm:text-base leading-relaxed max-w-2xl font-light">
                Join an international roster of independent artists and private consignors. Submit your work through our five-step curatorial dossier workflow, set confidential reserve protection, and present your art to verified collectors worldwide.
            </p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 pt-4 text-xs border-t border-charcoal">
                <div>
                    <span class="text-bronzeLight font-serif text-xl block mb-1">01. Dossier Submission</span>
                    <p class="text-stone">Upload high-resolution imagery, provenance, and suggested reserve pricing.</p>
                </div>
                <div>
                    <span class="text-bronzeLight font-serif text-xl block mb-1">02. Specialist Review</span>
                    <p class="text-stone">Our curatorial board verifies authenticity, condition, and auction estimates.</p>
                </div>
                <div>
                    <span class="text-bronzeLight font-serif text-xl block mb-1">03. Timed Auction &amp; Payout</span>
                    <p class="text-stone">Transparent bidding with anti-sniping rules and tracked artist wire payouts.</p>
                </div>
            </div>
        </div>
        <div class="lg:col-span-5 flex flex-col items-start lg:items-end justify-center">
            <div class="bg-charcoal/60 border border-stone/30 p-8 sm:p-10 w-full max-w-md space-y-6">
                <h3 class="font-serif text-2xl text-ivory">Begin Your Consignment</h3>
                <p class="text-xs text-gray-300 leading-relaxed">
                    Transparent commission rate of <?= e((string)platform_setting('commission_rate_percent', 15)) ?>% on successful hammer sales. Zero upfront listing fees for approved works.
                </p>
                <a href="/artist/submit" class="block w-full text-center bg-bronze text-white py-4 text-xs uppercase tracking-widest font-medium hover:bg-bronzeLight transition">
                    Submit Your Artwork
                </a>
                <a href="/sell-your-art" class="block w-full text-center border border-stone/50 text-ivory py-3.5 text-xs uppercase tracking-widest hover:border-ivory transition">
                    Learn About Our Approval Process
                </a>
            </div>
        </div>
    </div>
</section>
