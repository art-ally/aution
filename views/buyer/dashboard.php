<?php
$paddle = sprintf('PB-%04d', 1000 + (((int)$user['id'] * 791) % 8999));
$watchedIds = user_watchlist_ids();
?>
<div class="max-w-[1440px] mx-auto px-4 sm:px-8 py-12 space-y-12">
    <!-- Top Collector Header -->
    <div class="border-b border-hairline pb-8 flex flex-col lg:flex-row lg:items-end justify-between gap-6">
        <div>
            <div class="flex items-center space-x-3 mb-2">
                <span class="bg-obsidian text-ivory text-[10px] uppercase tracking-widest px-2.5 py-1 font-mono">Paddle <?= e($paddle) ?></span>
                <span class="text-[11px] uppercase tracking-widest text-bronze font-semibold">Verified Private Collector Account</span>
            </div>
            <h1 class="font-serif text-4xl sm:text-5xl text-obsidian font-normal"><?= e($user['name']) ?></h1>
            <p class="text-xs text-stone mt-1"><?= e($user['email']) ?> · <?= e($user['country']) ?></p>
        </div>

        <div class="flex flex-wrap gap-3">
            <a href="/auctions" class="bg-obsidian text-ivory px-6 py-3.5 text-xs uppercase tracking-widest hover:bg-bronze transition">Browse Live Auctions</a>
            <a href="/buyer/orders" class="border border-obsidian text-obsidian px-6 py-3.5 text-xs uppercase tracking-widest hover:bg-obsidian hover:text-ivory transition">
                Acquisitions &amp; Invoices (<?= count($orders) ?>)
            </a>
            <?php if (!\App\Core\Auth::hasRole('artist')): ?>
                <form action="/buyer/enable-artist" method="POST">
                    <?= csrf_field() ?>
                    <button type="submit" class="border border-bronze text-bronze px-5 py-3.5 text-xs uppercase tracking-widest hover:bg-bronze hover:text-white transition">
                        Enable Artist Consignor Studio
                    </button>
                </form>
            <?php else: ?>
                <a href="/artist/dashboard" class="border border-bronze text-bronze px-5 py-3.5 text-xs uppercase tracking-widest hover:bg-bronze hover:text-white transition">
                    Switch to Artist Studio →
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
        <!-- Left 8 Columns: Active Bid History & Watchlist -->
        <div class="lg:col-span-8 space-y-10">
            <!-- Bid History Ledger -->
            <div class="bg-white border border-hairline p-8">
                <div class="flex items-center justify-between border-b border-hairline pb-4 mb-6">
                    <h2 class="font-serif text-2xl text-obsidian">Your Validated Bid History</h2>
                    <span class="text-xs text-stone"><?= count($myBids) ?> Bids Placed</span>
                </div>

                <?php if (empty($myBids)): ?>
                    <p class="text-xs text-stone py-6">You have not placed any bids yet. Explore our live timed auctions to place your first bid.</p>
                <?php else: ?>
                    <div class="divide-y divide-hairline">
                        <?php foreach ($myBids as $b): ?>
                            <?php $art = $b['artwork'] ?? []; ?>
                            <div class="py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 text-xs">
                                <div class="flex items-center space-x-4">
                                    <img src="<?= e($art['primary_image'] ?? '') ?>" alt="" class="w-14 h-14 object-cover bg-alabaster border border-hairline">
                                    <div>
                                        <span class="font-mono text-[10px] text-stone"><?= e($b['paddle_number'] ?? $paddle) ?> · <?= e(gmdate('d M Y H:i', strtotime($b['created_at']))) ?> UTC</span>
                                        <h3 class="font-serif text-xl text-obsidian">
                                            <a href="/artworks/<?= e($art['slug'] ?? '') ?>" class="hover:text-bronze transition"><?= e($art['title'] ?? 'Auction Lot') ?></a>
                                        </h3>
                                        <span class="inline-block mt-1 px-2 py-0.5 text-[10px] uppercase tracking-widest <?= $b['is_leading'] ? 'bg-[#1A2E22] text-ivory' : 'bg-alabaster text-stone border border-hairline' ?>">
                                            <?= $b['is_leading'] ? 'Leading Highest Bid' : 'Outbid / Historical Bid' ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="sm:text-right">
                                    <p class="text-[10px] uppercase tracking-widest text-stone">Your Bid Amount</p>
                                    <p class="font-serif text-2xl font-semibold text-obsidian"><?= format_money($b['amount']) ?></p>
                                    <?php if (!empty($art['slug'])): ?>
                                        <a href="/artworks/<?= e($art['slug']) ?>" class="text-[11px] uppercase tracking-widest text-bronze font-semibold hover:underline">View Lot →</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Watched Lots -->
            <div class="bg-white border border-hairline p-8">
                <div class="flex items-center justify-between border-b border-hairline pb-4 mb-6">
                    <h2 class="font-serif text-2xl text-obsidian">Saved Watchlist Lots (<?= count($watchedArtworks) ?>)</h2>
                    <a href="/buyer/watchlist" class="text-xs uppercase tracking-widest text-bronze hover:underline">Full Watchlist →</a>
                </div>

                <?php if (empty($watchedArtworks)): ?>
                    <p class="text-xs text-stone py-4">No artworks saved in your watchlist yet.</p>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php foreach (array_slice($watchedArtworks, 0, 4) as $lot): ?>
                            <?php require VIEWS_PATH . '/partials/artwork-card.php'; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right 4 Columns: In-App Notification Center & Followed Artists -->
        <div class="lg:col-span-4 space-y-8">
            <div class="bg-white border border-hairline p-6">
                <div class="flex items-center justify-between border-b border-hairline pb-3 mb-4">
                    <h2 class="text-xs uppercase tracking-widest font-semibold text-obsidian">Notification Center</h2>
                    <form action="/buyer/notifications/read" method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="notification_id" value="-1">
                        <button type="submit" class="text-[10px] uppercase tracking-wider text-bronze hover:underline">Mark All Read</button>
                    </form>
                </div>

                <?php if (empty($notifications)): ?>
                    <p class="text-xs text-stone py-4">No notifications yet.</p>
                <?php else: ?>
                    <div class="divide-y divide-hairline max-h-96 overflow-y-auto">
                        <?php foreach ($notifications as $n): ?>
                            <div class="py-3.5 space-y-1 <?= empty($n['read_at']) ? 'bg-ivory/80 px-2 -mx-2' : '' ?>">
                                <div class="flex items-center justify-between">
                                    <p class="text-xs font-semibold text-obsidian"><?= e($n['title']) ?></p>
                                    <?php if (empty($n['read_at'])): ?>
                                        <span class="w-2 h-2 rounded-full bg-bronze"></span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-xs text-charcoal/80 leading-relaxed"><?= e($n['message']) ?></p>
                                <?php if (!empty($n['action_url'])): ?>
                                    <a href="<?= e($n['action_url']) ?>" class="inline-block text-[10px] uppercase tracking-widest text-bronze font-semibold pt-1">Inspect →</a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Followed Artists -->
            <div class="bg-white border border-hairline p-6">
                <h2 class="text-xs uppercase tracking-widest font-semibold text-obsidian border-b border-hairline pb-3 mb-4">Followed Artists</h2>
                <?php if (empty($followedArtists)): ?>
                    <p class="text-xs text-stone">You are not following any artists yet.</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($followedArtists as $fa): ?>
                            <a href="/artists/<?= e($fa['slug']) ?>" class="flex items-center space-x-3 hover:bg-ivory p-2 transition">
                                <img src="<?= e($fa['profile_photo']) ?>" alt="" class="w-10 h-10 object-cover">
                                <div>
                                    <p class="font-serif text-lg text-obsidian leading-none"><?= e($fa['display_name']) ?></p>
                                    <p class="text-[11px] text-stone mt-0.5"><?= e($fa['country']) ?></p>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
