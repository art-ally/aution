<?php
$finalPrice = (float)($order['hammer_price'] ?? $auction['current_bid'] ?? $artwork['agreed_price'] ?? $artwork['starting_bid'] ?? 0);
$certNumber = sprintf('COA-AN-%s-%04d', gmdate('Y'), (int)$artwork['id']);
$commissionRate = (float)($order['seller_commission_rate'] ?? platform_setting('commission_rate_percent', 15.0));
$commissionAmt = round($finalPrice * ($commissionRate / 100), 2);
$netPayout = round($finalPrice - $commissionAmt, 2);
?>
<div class="max-w-4xl mx-auto px-4 sm:px-8 py-12">
    <div class="flex items-center justify-between mb-6 print:hidden">
        <a href="javascript:history.back()" class="text-xs uppercase tracking-widest text-stone hover:text-obsidian">← Return to Dashboard</a>
        <button type="button" onclick="window.print()" class="bg-obsidian text-ivory px-6 py-3 text-xs uppercase tracking-widest font-semibold hover:bg-bronze transition">
            ⎙ Print / Save Official PDF Certificate
        </button>
    </div>

    <!-- Printable Archival Certificate Document -->
    <div class="bg-white border-2 border-obsidian p-10 sm:p-16 shadow-lg space-y-10">
        <!-- Header -->
        <div class="border-b-2 border-obsidian pb-8 flex flex-col sm:flex-row sm:items-end justify-between gap-4">
            <div>
                <p class="text-[11px] uppercase tracking-[0.28em] text-bronze font-semibold">International Fine Art Auctioneers</p>
                <h1 class="font-serif text-4xl sm:text-5xl tracking-[0.18em] text-obsidian font-semibold mt-1">ArtAlly</h1>
                <p class="text-xs text-stone mt-1">Geneva · New York · London · Paris</p>
            </div>
            <div class="sm:text-right text-xs">
                <p class="font-mono font-semibold text-obsidian text-sm"><?= e($certNumber) ?></p>
                <p class="text-stone">Issued: <?= e(gmdate('d F Y')) ?></p>
                <span class="inline-block mt-1 px-2.5 py-0.5 bg-obsidian text-bronzeLight text-[10px] uppercase tracking-widest font-semibold">
                    Official Bill of Sale &amp; Certificate of Authenticity
                </span>
            </div>
        </div>

        <!-- Artwork & Provenance Details -->
        <div class="grid grid-cols-1 md:grid-cols-12 gap-8 items-center">
            <div class="md:col-span-4 aspect-[4/5] bg-alabaster border border-hairline overflow-hidden">
                <img src="<?= e($artwork['primary_image']) ?>" alt="<?= e($artwork['title']) ?>" class="w-full h-full object-cover">
            </div>
            <div class="md:col-span-8 space-y-4 text-xs">
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-bronze font-semibold">Certified Original Work</p>
                    <h2 class="font-serif text-3xl italic text-obsidian mt-0.5"><?= e($artwork['title']) ?>, <?= e((string)($artwork['year_created'] ?? '')) ?></h2>
                    <p class="text-sm font-medium text-charcoal mt-1">By <?= e($artistProfile['display_name'] ?? $artistUser['name'] ?? 'Represented Artist') ?></p>
                </div>

                <div class="grid grid-cols-2 gap-4 border-y border-hairline py-4">
                    <div>
                        <span class="block text-[10px] uppercase tracking-widest text-stone">Medium &amp; Support</span>
                        <span class="font-medium text-obsidian"><?= e($artwork['medium']) ?></span>
                    </div>
                    <div>
                        <span class="block text-[10px] uppercase tracking-widest text-stone">Dimensions</span>
                        <span class="font-medium text-obsidian"><?= e($artwork['dimensions']) ?></span>
                    </div>
                    <div>
                        <span class="block text-[10px] uppercase tracking-widest text-stone">Classification &amp; Edition</span>
                        <span class="font-medium text-obsidian"><?= e($artwork['classification'] ?? 'Original Unique Work') ?> · <?= e($artwork['edition_number'] ?? '1/1') ?></span>
                    </div>
                    <div>
                        <span class="block text-[10px] uppercase tracking-widest text-stone">Signature / Attribution</span>
                        <span class="font-medium text-obsidian"><?= e($artwork['artist_attribution'] ?? 'Signed by Artist') ?></span>
                    </div>
                </div>

                <div>
                    <span class="block text-[10px] uppercase tracking-widest text-stone mb-1">Provenance &amp; Condition</span>
                    <p class="text-charcoal leading-relaxed"><?= e($artwork['provenance'] ?? 'Direct from the studio of the artist.') ?> <?= e($artwork['condition_report'] ?? '') ?></p>
                </div>
            </div>
        </div>

        <!-- Financial Settlement Summary -->
        <div class="bg-ivory border border-hairline p-6 grid grid-cols-2 sm:grid-cols-4 gap-6 text-xs">
            <div>
                <span class="block text-[10px] uppercase tracking-widest text-stone">Auction Status</span>
                <span class="font-serif text-xl font-semibold text-obsidian">
                    <?= ($artwork['status'] ?? '') === 'sold' || ($auction['status'] ?? '') === 'closed_sold' ? 'SOLD' : e(strtoupper($artwork['status'] ?? 'Verified')) ?>
                </span>
            </div>
            <div>
                <span class="block text-[10px] uppercase tracking-widest text-stone">Final Agreed / Hammer Price</span>
                <span class="font-serif text-xl font-semibold text-obsidian"><?= format_money_exact($finalPrice) ?></span>
            </div>
            <div>
                <span class="block text-[10px] uppercase tracking-widest text-stone">House Commission (<?= $commissionRate ?>%)</span>
                <span class="font-serif text-xl font-semibold text-stone"><?= format_money_exact($commissionAmt) ?></span>
            </div>
            <div>
                <span class="block text-[10px] uppercase tracking-widest text-stone">Net Artist Proceeds</span>
                <span class="font-serif text-xl font-semibold text-bronze"><?= format_money_exact($netPayout) ?></span>
            </div>
        </div>

        <!-- Institutional Signatures -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-12 pt-8 border-t border-hairline text-xs">
            <div>
                <p class="font-serif italic text-2xl text-obsidian mb-1"><?= e($artistProfile['display_name'] ?? $artistUser['name'] ?? 'Artist') ?></p>
                <div class="border-t border-obsidian pt-2 text-[10px] uppercase tracking-widest text-stone">
                    Creator &amp; Consignor Signature
                </div>
            </div>
            <div>
                <p class="font-serif italic text-2xl text-obsidian mb-1">Chief Curatorial Directorate</p>
                <div class="border-t border-obsidian pt-2 text-[10px] uppercase tracking-widest text-stone">
                    ArtAlly Authenticity &amp; Compliance Seal
                </div>
            </div>
        </div>
    </div>
</div>
