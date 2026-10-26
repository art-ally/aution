<?php
$watchedIds = user_watchlist_ids();
?>
<div class="max-w-[1440px] mx-auto px-4 sm:px-8 py-14">
    <?php if (isset($isApproved) && !$isApproved): ?>
        <div class="bg-[#3B2A18] text-ivory px-6 py-4 mb-8 border border-bronze flex flex-col sm:flex-row sm:items-center justify-between gap-4 text-xs">
            <div>
                <span class="uppercase tracking-widest text-[10px] text-bronzeLight font-semibold block">Awaiting Curatorial Approval</span>
                <p>This artist profile is currently <strong>Awaiting Approval</strong> by the Administrator. Only you (and the Administrator) can preview this page until it is approved in the backend.</p>
            </div>
            <span class="px-3 py-1 bg-bronze text-white text-[10px] uppercase tracking-widest font-semibold whitespace-nowrap">Pending Admin Approval</span>
        </div>
    <?php endif; ?>

    <!-- Artist Dossier Header -->
    <div class="bg-white border border-hairline p-8 sm:p-12 mb-16 grid grid-cols-1 lg:grid-cols-12 gap-10 items-center">
        <div class="lg:col-span-4 aspect-square bg-alabaster overflow-hidden border border-hairline">
            <img src="<?= e($artist['profile_photo']) ?>" alt="<?= e($artist['display_name']) ?>" class="w-full h-full object-cover">
        </div>
        <div class="lg:col-span-8 space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-hairline pb-4">
                <div>
                    <span class="text-[11px] uppercase tracking-widest text-bronze font-semibold">
                        Verified Studio Artist · <?= e($artist['city'] ? ($artist['city'] . ', ' . $artist['country']) : $artist['country']) ?>
                    </span>
                    <h1 class="font-serif text-4xl sm:text-5xl text-obsidian mt-1"><?= e($artist['display_name']) ?></h1>
                </div>

                <form action="/buyer/artists/follow" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="artist_profile_id" value="<?= (int)$artist['id'] ?>">
                    <button type="submit" class="px-6 py-3 text-xs uppercase tracking-widest border border-obsidian transition <?= $isFollowing ? 'bg-obsidian text-ivory' : 'hover:bg-obsidian hover:text-ivory' ?>">
                        <?= $isFollowing ? '✓ Following Artist' : '+ Follow Artist Alerts' ?>
                    </button>
                </form>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 gap-6 text-xs">
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-stone">Artistic Discipline</p>
                    <p class="font-medium text-obsidian mt-0.5"><?= e($artist['discipline']) ?></p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-stone">Years Active</p>
                    <p class="font-medium text-obsidian mt-0.5"><?= e($artist['years_active'] ?? 'Contemporary') ?></p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-stone">Studio Links</p>
                    <p class="font-medium text-bronze mt-0.5"><?= e($artist['social_instagram'] ?: 'ArtAlly Archive') ?></p>
                </div>
            </div>

            <div>
                <h2 class="text-[11px] uppercase tracking-widest text-stone mb-2">Biography</h2>
                <p class="text-sm text-charcoal/90 leading-relaxed"><?= e($artist['biography']) ?></p>
            </div>

            <?php if (!empty($artist['artist_statement'])): ?>
                <blockquote class="border-l-2 border-bronze pl-5 italic font-serif text-xl text-charcoal">
                    “<?= e($artist['artist_statement']) ?>”
                </blockquote>
            <?php endif; ?>
        </div>
    </div>

    <!-- Approved Works & Auctions by this Artist -->
    <div>
        <div class="border-b border-hairline pb-4 mb-8 flex items-end justify-between">
            <div>
                <p class="text-[11px] uppercase tracking-widest text-bronze font-semibold">Catalogue Raisonné &amp; Sales</p>
                <h2 class="font-serif text-3xl sm:text-4xl text-obsidian">Approved Works &amp; Active Lots (<?= count($artworks) ?>)</h2>
            </div>
        </div>

        <?php
        $lotsWithBids = array_filter($artworks, fn($w) => ((int)($w['auction']['bid_count'] ?? 0) > 0) || !empty($w['current_bid']));
        ?>
        <?php if (!empty($lotsWithBids)): ?>
            <div class="bg-[#1A2E22] text-ivory border border-bronze px-6 py-4 mb-8 space-y-2">
                <p class="text-[10px] uppercase tracking-widest text-bronzeLight font-semibold">🔔 Live Auction Bid Updates for <?= e($artist['display_name']) ?></p>
                <div class="flex flex-wrap gap-4 text-xs">
                    <?php foreach ($lotsWithBids as $bLot): ?>
                        <?php $liveAmt = (float)($bLot['auction']['current_bid'] ?? $bLot['current_bid'] ?? $bLot['starting_bid'] ?? 0); ?>
                        <a href="/artworks/<?= e($bLot['slug']) ?>" class="bg-obsidian/80 border border-bronze/50 px-3.5 py-2 hover:border-ivory transition">
                            <span class="font-serif text-base text-ivory"><?= e($bLot['title']) ?>:</span>
                            <strong class="text-bronzeLight ml-1">Current Bid <?= format_money($liveAmt) ?></strong>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($artworks)): ?>
            <div class="bg-white border border-hairline p-12 text-center text-xs text-stone">
                No public lots currently scheduled for this artist. Follow the artist above to receive advance notification of upcoming consignments.
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($artworks as $lot): ?>
                    <?php require VIEWS_PATH . '/partials/artwork-card.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
