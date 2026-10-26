<?php
$watchedIds = user_watchlist_ids();
?>
<div class="max-w-[1440px] mx-auto px-4 sm:px-8 py-14">
    <!-- Header -->
    <div class="border-b border-hairline pb-8 mb-10 flex flex-col lg:flex-row lg:items-end justify-between gap-6">
        <div>
            <p class="text-[11px] uppercase tracking-widest text-bronze font-semibold mb-2">International Sale Calendar</p>
            <h1 class="font-serif text-4xl sm:text-5xl text-obsidian font-normal">
                <?php if ($statusFilter === 'upcoming'): ?>
                    Upcoming Timed Auctions
                <?php elseif ($statusFilter === 'closed'): ?>
                    Past Auction Results &amp; Archive
                <?php else: ?>
                    Current Live Timed Auctions
                <?php endif; ?>
            </h1>
        </div>

        <!-- Status Tabs -->
        <div class="flex items-center space-x-2 border border-hairline bg-white p-1 self-start">
            <a href="/auctions?status=active" class="px-5 py-2.5 text-xs uppercase tracking-widest transition <?= $statusFilter === 'active' ? 'bg-obsidian text-ivory' : 'text-charcoal hover:text-bronze' ?>">
                Live Bidding
            </a>
            <a href="/auctions?status=upcoming" class="px-5 py-2.5 text-xs uppercase tracking-widest transition <?= $statusFilter === 'upcoming' ? 'bg-obsidian text-ivory' : 'text-charcoal hover:text-bronze' ?>">
                Upcoming Lots
            </a>
            <a href="/auctions?status=closed" class="px-5 py-2.5 text-xs uppercase tracking-widest transition <?= $statusFilter === 'closed' ? 'bg-obsidian text-ivory' : 'text-charcoal hover:text-bronze' ?>">
                Closed Results
            </a>
        </div>
    </div>

    <?php if (empty($lots)): ?>
        <div class="bg-white border border-hairline p-16 text-center">
            <h2 class="font-serif text-3xl text-obsidian mb-2">No Auction Lots Match This View</h2>
            <p class="text-xs text-stone mb-6">Explore our complete fine art catalogue or switch auction status tabs above.</p>
            <a href="/catalogue" class="inline-block bg-obsidian text-ivory px-7 py-3.5 text-xs uppercase tracking-widest hover:bg-bronze transition">
                Browse Full Catalogue
            </a>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach ($lots as $lot): ?>
                <?php require VIEWS_PATH . '/partials/artwork-card.php'; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
