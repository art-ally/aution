<?php
$user = current_user();
$watchedIds = user_watchlist_ids();
$isWatched = in_array((int)$artwork['id'], $watchedIds, true);
$aucStatus = $auction['status'] ?? 'unscheduled';
$currentBid = (float)($auction['current_bid'] ?? $artwork['starting_bid'] ?? 0);
$minNext = $auction ? minimum_next_bid($auction) : (float)$artwork['starting_bid'];
$increment = calculate_bid_increment($currentBid);
$buyerPremiumPercent = (float)platform_setting('buyer_premium_percent', 20.0);
$galleryList = !empty($images) ? array_map(fn($i) => $i['image_url'], $images) : [$artwork['primary_image']];
$isArtistOwner = $user && ((int)$user['id'] === (int)$artwork['artist_id']);
?>

<div class="max-w-[1440px] mx-auto px-4 sm:px-8 py-10" x-data="{ activeImage: '<?= e($galleryList[0]) ?>', zoomOpen: false, reportOpen: false, bidVal: <?= (float)$minNext ?> }">
    <!-- Breadcrumb Navigation -->
    <nav class="flex flex-wrap items-center space-x-2 text-[11px] uppercase tracking-widest text-stone mb-8 border-b border-hairline pb-4" aria-label="Breadcrumb">
        <a href="/" class="hover:text-obsidian">Home</a>
        <span>/</span>
        <a href="/auctions" class="hover:text-obsidian">Auctions</a>
        <span>/</span>
        <a href="/catalogue?category=<?= e($artwork['category_slug']) ?>" class="hover:text-obsidian"><?= e($artwork['category_name']) ?></a>
        <span>/</span>
        <span class="text-obsidian font-semibold"><?= e($auction['lot_number'] ?? 'LOT') ?> — <?= e($artwork['title']) ?></span>
    </nav>

    <!-- Primary Lot Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-12">
        <!-- Left 7 Columns: High-Resolution Gallery & Provenance Dossier -->
        <div class="lg:col-span-7 space-y-10">
            <!-- Main Image Viewer with Full-Screen Zoom -->
            <div class="bg-white border border-hairline p-4 sm:p-8">
                <div class="relative aspect-[4/3] bg-alabaster overflow-hidden cursor-zoom-in" @click="zoomOpen = true">
                    <img :src="activeImage" alt="<?= e($artwork['title']) ?>" class="w-full h-full object-contain">
                    <button type="button" @click.stop="zoomOpen = true" class="absolute bottom-4 right-4 bg-obsidian/85 text-ivory text-[10px] uppercase tracking-widest px-3.5 py-2 hover:bg-bronze transition">
                        Inspect Full Resolution [+]
                    </button>
                </div>

                <!-- Thumbnail Selector -->
                <?php if (count($galleryList) > 1): ?>
                    <div class="flex items-center space-x-3 mt-4 overflow-x-auto pb-1">
                        <?php foreach ($galleryList as $imgUrl): ?>
                            <button
                                type="button"
                                @click="activeImage = '<?= e($imgUrl) ?>'"
                                :class="activeImage === '<?= e($imgUrl) ?>' ? 'border-bronze ring-1 ring-bronze' : 'border-hairline opacity-70 hover:opacity-100'"
                                class="w-20 h-20 border overflow-hidden flex-shrink-0 transition"
                            >
                                <img src="<?= e($imgUrl) ?>" alt="Detail view" class="w-full h-full object-cover">
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Curatorial Essay, Condition & Provenance Tabs -->
            <div class="bg-white border border-hairline p-8 space-y-8">
                <div>
                    <h2 class="text-xs uppercase tracking-widest text-bronze font-semibold mb-3">Catalogue Note &amp; Essay</h2>
                    <p class="text-sm sm:text-base text-charcoal leading-relaxed font-light whitespace-pre-line"><?= e($artwork['description']) ?></p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-6 border-t border-hairline text-xs">
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-stone mb-1">Medium &amp; Support</p>
                        <p class="font-medium text-obsidian"><?= e($artwork['medium']) ?></p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-stone mb-1">Dimensions</p>
                        <p class="font-medium text-obsidian"><?= e($artwork['dimensions']) ?></p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-stone mb-1">Classification &amp; Edition</p>
                        <p class="font-medium text-obsidian"><?= e($artwork['classification'] ?? 'Original Unique Work') ?> · <?= e($artwork['edition_number'] ?? 'Unique (1/1)') ?></p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-stone mb-1">Signature &amp; Attribution</p>
                        <p class="font-medium text-obsidian"><?= e($artwork['artist_attribution'] ?? ('Signed by ' . $artwork['artist_name'])) ?></p>
                    </div>
                </div>

                <div class="pt-6 border-t border-hairline space-y-4">
                    <div>
                        <h3 class="text-xs uppercase tracking-widest text-bronze font-semibold mb-1.5">Provenance &amp; Authenticity</h3>
                        <p class="text-xs text-charcoal/90 leading-relaxed"><?= e($artwork['provenance'] ?? 'Direct from the studio of the artist.') ?></p>
                    </div>
                    <div>
                        <h3 class="text-xs uppercase tracking-widest text-bronze font-semibold mb-1.5">Specialist Condition Report</h3>
                        <p class="text-xs text-charcoal/90 leading-relaxed"><?= e($artwork['condition_report'] ?? 'In pristine original studio condition.') ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right 5 Columns: Live Transactional Bidding Chamber -->
        <div class="lg:col-span-5">
            <div class="bg-white border border-hairline p-8 sticky top-28 space-y-6">
                <!-- Sale Title & Lot Number -->
                <div class="flex items-center justify-between border-b border-hairline pb-4">
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-bronze font-semibold">
                            <?= e($auction['title'] ?? 'Curated Timed Sale') ?>
                        </p>
                        <p class="text-xs font-semibold text-obsidian mt-0.5">
                            <?= e($auction['lot_number'] ?? 'LOT 01') ?> · <?= e(strtoupper($artwork['status'])) ?>
                        </p>
                    </div>

                    <!-- Watchlist & Share -->
                    <div class="flex items-center space-x-2">
                        <form action="/buyer/watchlist/toggle" method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="artwork_id" value="<?= (int)$artwork['id'] ?>">
                            <input type="hidden" name="return_url" value="<?= e($_SERVER['REQUEST_URI'] ?? '/') ?>">
                            <button type="submit" class="px-3 py-1.5 border border-hairline text-[11px] uppercase tracking-wider hover:border-obsidian transition flex items-center space-x-1.5 <?= $isWatched ? 'bg-obsidian text-ivory' : 'text-charcoal' ?>">
                                <span><?= $isWatched ? '★ Saved' : '☆ Watch Lot' ?></span>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Artist & Artwork Title -->
                <div>
                    <p class="text-xs uppercase tracking-widest text-stone font-semibold">
                        <?= e($artwork['artist_name']) ?> (<?= e($artwork['artist_country']) ?>)
                    </p>
                    <h1 class="font-serif text-3xl sm:text-4xl italic text-obsidian mt-1 leading-tight">
                        <?= e($artwork['title']) ?>, <span class="not-italic font-normal"><?= e((string)$artwork['year_created']) ?></span>
                    </h1>
                    <p class="text-xs text-stone mt-2">
                        Estimate: <strong class="text-obsidian"><?= format_money($artwork['estimate_low']) ?> – <?= format_money($artwork['estimate_high']) ?></strong>
                    </p>
                </div>

                <!-- Live Bid Box -->
                <div class="bg-ivory border border-hairline p-6 space-y-4">
                    <div class="flex items-baseline justify-between">
                        <div>
                            <p class="text-[10px] uppercase tracking-widest text-stone">
                                <?= ($auction && (int)$auction['bid_count'] > 0) ? 'Current Highest Bid' : 'Starting Bid' ?>
                            </p>
                            <p class="font-serif text-4xl font-semibold text-obsidian mt-0.5">
                                <?= format_money($currentBid, $artwork['currency'] ?? 'USD') ?>
                            </p>
                        </div>
                        <div class="text-right">
                            <span class="inline-block text-[11px] uppercase tracking-widest px-2.5 py-1 <?= ($auction['reserve_met'] ?? false) ? 'bg-[#1A2E22] text-ivory' : 'bg-alabaster text-stone border border-hairline' ?>">
                                <?= ($auction['reserve_met'] ?? false) ? 'Reserve Met' : 'Reserve Not Yet Met' ?>
                            </span>
                            <p class="text-xs text-stone mt-1"><?= (int)($auction['bid_count'] ?? 0) ?> validated bids</p>
                        </div>
                    </div>

                    <?php if ($auction && $aucStatus === 'active'): ?>
                        <div class="border-t border-hairline pt-3 flex items-center justify-between text-xs">
                            <span class="uppercase tracking-wider text-stone text-[11px]">Lot Closes In:</span>
                            <span class="font-semibold text-bronze text-sm" data-countdown="<?= e($auction['ends_at']) ?>">
                                <?= e(time_remaining_label($auction['ends_at'])) ?>
                            </span>
                        </div>
                        <p class="text-[11px] text-stone">
                            Scheduled Close: <?= e(gmdate('d M Y, H:i', strtotime($auction['ends_at']))) ?> UTC · 5-min anti-sniping extension active.
                        </p>
                    <?php elseif ($auction && $aucStatus === 'upcoming'): ?>
                        <div class="border-t border-hairline pt-3 flex items-center justify-between text-xs">
                            <span class="uppercase tracking-wider text-stone text-[11px]">Bidding Opens:</span>
                            <span class="font-semibold text-obsidian"><?= e(gmdate('d M Y, H:i', strtotime($auction['starts_at']))) ?> UTC</span>
                        </div>
                    <?php else: ?>
                        <div class="border-t border-hairline pt-3 text-xs font-semibold text-stone uppercase tracking-widest">
                            Auction Closed (<?= e(strtoupper(str_replace('_', ' ', $aucStatus))) ?>)
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Server-Validated Bid Form -->
                <?php if ($auction && $aucStatus === 'active'): ?>
                    <?php if ($isArtistOwner): ?>
                        <div class="p-4 bg-alabaster border border-hairline text-xs text-charcoal">
                            <strong>Artist Studio Notice:</strong> You are the creator/consignor of this lot. Institutional rules prohibit bidding on your own work.
                        </div>
                    <?php elseif ($user): ?>
                        <form action="/buyer/bids/place" method="POST" class="space-y-4">
                            <?= csrf_field() ?>
                            <input type="hidden" name="auction_id" value="<?= (int)$auction['id'] ?>">

                            <div>
                                <div class="flex items-center justify-between text-xs mb-1.5">
                                    <label for="bid_amount" class="uppercase tracking-widest text-[11px] font-semibold text-obsidian">
                                        Enter Your Maximum Bid (<?= e($artwork['currency'] ?? 'USD') ?>)
                                    </label>
                                    <span class="text-bronze font-medium">Minimum: <?= format_money($minNext) ?></span>
                                </div>

                                <div class="flex gap-2">
                                    <input
                                        type="number"
                                        id="bid_amount"
                                        name="bid_amount"
                                        x-model="bidVal"
                                        min="<?= (float)$minNext ?>"
                                        step="50"
                                        required
                                        class="flex-1 bg-white border border-obsidian px-4 py-3.5 text-base font-medium focus:outline-none focus:border-bronze"
                                    >
                                    <button
                                        type="submit"
                                        class="bg-obsidian text-ivory px-7 py-3.5 text-xs uppercase tracking-widest font-semibold hover:bg-bronze transition whitespace-nowrap"
                                    >
                                        Place Verified Bid
                                    </button>
                                </div>

                                <!-- Quick Increment Pills -->
                                <div class="flex flex-wrap gap-2 mt-2.5">
                                    <button type="button" @click="bidVal = <?= (float)$minNext ?>" class="text-[11px] px-3 py-1 border border-hairline bg-ivory hover:border-bronze transition">
                                        <?= format_money($minNext) ?> (Min)
                                    </button>
                                    <button type="button" @click="bidVal = <?= (float)($minNext + $increment) ?>" class="text-[11px] px-3 py-1 border border-hairline bg-ivory hover:border-bronze transition">
                                        <?= format_money($minNext + $increment) ?>
                                    </button>
                                    <button type="button" @click="bidVal = <?= (float)($minNext + ($increment * 2)) ?>" class="text-[11px] px-3 py-1 border border-hairline bg-ivory hover:border-bronze transition">
                                        <?= format_money($minNext + ($increment * 2)) ?>
                                    </button>
                                </div>
                            </div>

                            <!-- Transparent Fee Preview -->
                            <div class="text-[11px] text-stone bg-alabaster/70 p-3.5 border border-hairline space-y-1">
                                <div class="flex justify-between">
                                    <span>Buyer’s Premium (<?= $buyerPremiumPercent ?>%):</span>
                                    <span x-text="'$' + Math.round(bidVal * <?= $buyerPremiumPercent / 100 ?>).toLocaleString()"></span>
                                </div>
                                <div class="flex justify-between font-semibold text-obsidian border-t border-hairline pt-1">
                                    <span>Estimated All-In Total (excl. shipping):</span>
                                    <span x-text="'$' + Math.round(bidVal * <?= 1 + ($buyerPremiumPercent / 100) ?>).toLocaleString()"></span>
                                </div>
                                <p class="pt-1 text-[10px]">All bids are legally binding under the ArtAlly Conditions of Sale.</p>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="p-6 bg-alabaster border border-hairline text-center space-y-3">
                            <p class="text-xs text-charcoal">Sign in or register for a verified collector paddle to bid on this lot.</p>
                            <div class="flex gap-3">
                                <a href="/login" class="flex-1 bg-obsidian text-ivory py-3 text-xs uppercase tracking-widest hover:bg-bronze transition">Sign In to Bid</a>
                                <a href="/register" class="flex-1 border border-obsidian text-obsidian py-3 text-xs uppercase tracking-widest hover:bg-obsidian hover:text-ivory transition">Register</a>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Immutable Bid History Ledger -->
                <div class="border-t border-hairline pt-6">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-xs uppercase tracking-widest font-semibold text-obsidian">Immutable Bid Ledger</h3>
                        <span class="text-[10px] uppercase tracking-wider text-stone">Anonymous Paddles</span>
                    </div>

                    <?php if (empty($bids)): ?>
                        <p class="text-xs text-stone italic py-3">No bids recorded yet. Be the first collector to open the bidding.</p>
                    <?php else: ?>
                        <div class="max-h-52 overflow-y-auto divide-y divide-hairline border border-hairline bg-ivory px-4">
                            <?php foreach ($bids as $idx => $bid): ?>
                                <div class="py-2.5 flex items-center justify-between text-xs">
                                    <div class="flex items-center space-x-2">
                                        <span class="font-mono text-[11px] font-semibold text-obsidian"><?= e($bid['paddle_number'] ?? 'PB-ANON') ?></span>
                                        <?php if ($idx === 0): ?>
                                            <span class="text-[9px] uppercase tracking-widest bg-bronze text-white px-1.5 py-0.5">Highest</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-right">
                                        <span class="font-semibold text-obsidian"><?= format_money($bid['amount'], $bid['currency'] ?? 'USD') ?></span>
                                        <span class="block text-[10px] text-stone"><?= e(gmdate('d M, H:i:s', strtotime($bid['created_at']))) ?> UTC</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Report or Inquire Control -->
                <div class="border-t border-hairline pt-4 flex items-center justify-between text-[11px] text-stone">
                    <a href="/buyer-guide" class="hover:text-obsidian underline">Auction Rules &amp; Buyer Protection</a>
                    <button type="button" @click="reportOpen = true" class="hover:text-red-700 underline">Report Provenance Concern</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Related Artworks Section -->
    <?php if (!empty($related)): ?>
        <section class="mt-24 pt-14 border-t border-hairline">
            <div class="flex items-end justify-between mb-10">
                <div>
                    <p class="text-[11px] uppercase tracking-widest text-bronze font-semibold mb-1">Curatorial Selection</p>
                    <h2 class="font-serif text-3xl sm:text-4xl text-obsidian">Related Works in Current Sales</h2>
                </div>
                <a href="/catalogue" class="text-xs uppercase tracking-widest text-obsidian hover:text-bronze">View Catalogue →</a>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <?php foreach ($related as $lot): ?>
                    <?php require VIEWS_PATH . '/partials/artwork-card.php'; ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- Full-Size Lightbox Zoom Modal -->
    <div x-cloak x-show="zoomOpen" @keydown.escape.window="zoomOpen = false" class="fixed inset-0 z-50 bg-obsidian/95 flex flex-col items-center justify-center p-4 sm:p-10">
        <button type="button" @click="zoomOpen = false" class="self-end text-ivory text-xs uppercase tracking-widest border border-stone px-4 py-2 mb-4 hover:border-ivory">
            Close Inspection [ESC]
        </button>
        <img :src="activeImage" alt="<?= e($artwork['title']) ?>" class="max-w-full max-h-[82vh] object-contain">
        <p class="text-stone text-xs mt-4 font-serif italic"><?= e($artwork['artist_name']) ?> — <?= e($artwork['title']) ?> (<?= e($artwork['dimensions']) ?>)</p>
    </div>

    <!-- Report / Compliance Modal -->
    <div x-cloak x-show="reportOpen" class="fixed inset-0 z-50 bg-obsidian/70 flex items-center justify-center p-4">
        <div @click.away="reportOpen = false" class="bg-white border border-hairline max-w-lg w-full p-8 space-y-5">
            <div class="flex items-center justify-between border-b border-hairline pb-3">
                <h3 class="font-serif text-2xl text-obsidian">Report Lot or Provenance Inquiry</h3>
                <button type="button" @click="reportOpen = false" class="text-xs uppercase tracking-widest text-stone">Close</button>
            </div>
            <form action="/artworks/report" method="POST" class="space-y-4 text-xs">
                <?= csrf_field() ?>
                <input type="hidden" name="artwork_id" value="<?= (int)$artwork['id'] ?>">
                <div>
                    <label class="block uppercase tracking-widest text-stone mb-1">Reason for Inquiry</label>
                    <select name="reason" required class="w-full border border-hairline bg-ivory px-3 py-2.5">
                        <option value="Provenance Verification Request">Provenance Verification Request</option>
                        <option value="Attribution or Copyright Concern">Attribution or Copyright Concern</option>
                        <option value="Condition Report Clarification">Condition Report Clarification</option>
                    </select>
                </div>
                <div>
                    <label class="block uppercase tracking-widest text-stone mb-1">Details for Curatorial Compliance</label>
                    <textarea name="details" rows="3" required placeholder="Provide reference details..." class="w-full border border-hairline bg-ivory p-3"></textarea>
                </div>
                <button type="submit" class="w-full bg-obsidian text-ivory py-3 uppercase tracking-widest hover:bg-bronze transition">
                    Submit to Compliance Board
                </button>
            </form>
        </div>
    </div>
</div>
