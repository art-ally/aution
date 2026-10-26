<?php
$commissionRate = (float)($settings['commission_rate_percent'] ?? 15.0);
?>
<div class="max-w-[1440px] mx-auto px-4 sm:px-8 py-16">
    <!-- Hero -->
    <div class="bg-white border border-hairline p-8 sm:p-16 grid grid-cols-1 lg:grid-cols-12 gap-12 items-center mb-16">
        <div class="lg:col-span-7 space-y-6">
            <p class="text-[11px] uppercase tracking-widest text-bronze font-semibold">Artist &amp; Private Collection Consignments</p>
            <h1 class="font-serif text-5xl sm:text-6xl text-obsidian font-normal leading-tight">
                Your Art Deserves to Be Discovered.
            </h1>
            <p class="text-base text-charcoal/80 leading-relaxed max-w-2xl font-light">
                ArtAlly provides independent visual artists and private collectors with an institutional timed-auction platform. Every submitted artwork undergoes specialist curatorial evaluation before being presented to our international collector registry.
            </p>
            <div class="flex flex-wrap gap-4 pt-2">
                <a href="/artist/submit" class="bg-obsidian text-ivory px-8 py-4 text-xs uppercase tracking-widest font-semibold hover:bg-bronze transition">
                    Submit Your Artwork Now
                </a>
                <a href="/register?role=artist" class="border border-obsidian text-obsidian px-8 py-4 text-xs uppercase tracking-widest font-semibold hover:bg-obsidian hover:text-ivory transition">
                    Create Artist Studio Account
                </a>
            </div>
        </div>
        <div class="lg:col-span-5">
            <div class="bg-ivory border border-hairline p-8 space-y-5">
                <h2 class="font-serif text-2xl text-obsidian border-b border-hairline pb-3">Transparent Consignment Terms</h2>
                <div class="space-y-3 text-xs">
                    <div class="flex justify-between py-2 border-b border-hairline">
                        <span class="text-stone uppercase tracking-wider">Artist Seller Commission</span>
                        <span class="font-semibold text-obsidian"><?= $commissionRate ?>% of Hammer Price</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-hairline">
                        <span class="text-stone uppercase tracking-wider">Upfront Submission / Listing Fee</span>
                        <span class="font-semibold text-bronze">$0 (Complimentary Evaluation)</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-hairline">
                        <span class="text-stone uppercase tracking-wider">Confidential Reserve Protection</span>
                        <span class="font-semibold text-obsidian">Supported on Every Lot</span>
                    </div>
                    <div class="flex justify-between py-2">
                        <span class="text-stone uppercase tracking-wider">Net Proceeds Disbursement</span>
                        <span class="font-semibold text-obsidian">Tracked Wire Payout Workflow</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 5-Step Submission Architecture Overview -->
    <div class="mb-12">
        <p class="text-[11px] uppercase tracking-widest text-bronze font-semibold mb-2">How Consignment Works</p>
        <h2 class="font-serif text-4xl text-obsidian mb-8">The Five-Step Curatorial Dossier Workflow</h2>
        <div class="grid grid-cols-1 md:grid-cols-5 gap-6">
            <div class="bg-white border border-hairline p-6">
                <span class="font-serif text-3xl text-bronze font-semibold">01</span>
                <h3 class="font-serif text-xl text-obsidian mt-2 mb-2">Artwork Metadata</h3>
                <p class="text-xs text-stone leading-relaxed">Provide title, medium, dimensions, year, edition classification, and curatorial statement.</p>
            </div>
            <div class="bg-white border border-hairline p-6">
                <span class="font-serif text-3xl text-bronze font-semibold">02</span>
                <h3 class="font-serif text-xl text-obsidian mt-2 mb-2">High-Res Imagery</h3>
                <p class="text-xs text-stone leading-relaxed">Upload multiple studio photographs, recto/verso signatures, and designate a primary lot image.</p>
            </div>
            <div class="bg-white border border-hairline p-6">
                <span class="font-serif text-3xl text-bronze font-semibold">03</span>
                <h3 class="font-serif text-xl text-obsidian mt-2 mb-2">Valuation &amp; Reserve</h3>
                <p class="text-xs text-stone leading-relaxed">Set your opening bid, confidential reserve minimum, estimate range, and preferred auction duration.</p>
            </div>
            <div class="bg-white border border-hairline p-6">
                <span class="font-serif text-3xl text-bronze font-semibold">04</span>
                <h3 class="font-serif text-xl text-obsidian mt-2 mb-2">Provenance &amp; COA</h3>
                <p class="text-xs text-stone leading-relaxed">Confirm legal ownership, condition report, and attach private authenticity documentation.</p>
            </div>
            <div class="bg-white border border-hairline p-6">
                <span class="font-serif text-3xl text-bronze font-semibold">05</span>
                <h3 class="font-serif text-xl text-obsidian mt-2 mb-2">Review &amp; Live Sale</h3>
                <p class="text-xs text-stone leading-relaxed">Once approved by our Chief Curator, your lot is scheduled into a timed auction with anti-sniping protection.</p>
            </div>
        </div>
    </div>
</div>
