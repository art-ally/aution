<div class="max-w-[1440px] mx-auto px-4 sm:px-8 py-14">
    <div class="border-b border-hairline pb-8 mb-12 flex flex-col md:flex-row md:items-end justify-between gap-6">
        <div>
            <p class="text-[11px] uppercase tracking-widest text-bronze font-semibold mb-2">International Studio Roster</p>
            <h1 class="font-serif text-4xl sm:text-5xl text-obsidian font-normal">Represented &amp; Vetted Artists</h1>
        </div>
        <a href="/register?role=artist" class="bg-obsidian text-ivory px-7 py-3.5 text-xs uppercase tracking-widest hover:bg-bronze transition self-start">
            Apply as an Artist
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
        <?php foreach ($artists as $artist): ?>
            <?php $isApproved = ($artist['approval_status'] ?? 'approved') === 'approved'; ?>
            <?php if ($isApproved): ?>
                <!-- Approved Artist Card: Full Portfolio Displayed -->
                <article class="bg-white border border-hairline flex flex-col justify-between group hover:border-bronze transition">
                    <div>
                        <a href="/artists/<?= e($artist['slug']) ?>" class="block aspect-square bg-alabaster overflow-hidden relative">
                            <img src="<?= e($artist['profile_photo']) ?>" alt="<?= e($artist['display_name']) ?>" class="w-full h-full object-cover gallery-image-zoom">
                            <span class="absolute top-3 left-3 bg-[#1A2E22]/90 text-ivory text-[9px] uppercase tracking-widest px-2.5 py-1 font-semibold">
                                ✓ Approved Artist
                            </span>
                        </a>
                        <div class="p-6">
                            <div class="flex items-center justify-between text-[10px] uppercase tracking-widest text-bronze font-semibold mb-1">
                                <span><?= e($artist['country']) ?></span>
                                <span><?= (int)($artist['published_count'] ?? 0) ?> Lots</span>
                            </div>
                            <h2 class="font-serif text-2xl text-obsidian group-hover:text-bronze transition mb-1">
                                <a href="/artists/<?= e($artist['slug']) ?>"><?= e($artist['display_name']) ?></a>
                            </h2>
                            <p class="text-[11px] text-stone uppercase tracking-wider mb-3"><?= e($artist['discipline']) ?></p>
                            <p class="text-xs text-charcoal/80 leading-relaxed line-clamp-3"><?= e($artist['biography']) ?></p>
                        </div>
                    </div>
                    <div class="px-6 pb-6 pt-3 border-t border-hairline">
                        <a href="/artists/<?= e($artist['slug']) ?>" class="block text-center py-2.5 border border-obsidian text-[11px] uppercase tracking-widest group-hover:bg-obsidian group-hover:text-ivory transition">
                            Explore Portfolio &amp; Auctions
                        </a>
                    </div>
                </article>
            <?php else: ?>
                <!-- Newly Registered Artist Card: Shows Awaiting Approval Until Approved From Backend -->
                <article class="bg-ivory border border-dashed border-bronze/70 flex flex-col justify-between">
                    <div>
                        <div class="aspect-square bg-alabaster flex flex-col items-center justify-center p-6 text-center relative overflow-hidden">
                            <span class="bg-bronze text-white text-[10px] uppercase tracking-widest px-3 py-1.5 font-semibold mb-3">
                                ◷ Awaiting Approval
                            </span>
                            <p class="font-serif text-xl text-obsidian italic">Artist Profile Under Curatorial Review</p>
                            <p class="text-[11px] text-stone mt-2">
                                Full portfolio &amp; biography will display publicly once approved by the Administrator.
                            </p>
                        </div>
                        <div class="p-6">
                            <div class="flex items-center justify-between text-[10px] uppercase tracking-widest text-bronze font-semibold mb-1">
                                <span><?= e($artist['country']) ?></span>
                                <span>Pending Verification</span>
                            </div>
                            <h2 class="font-serif text-2xl text-obsidian mb-1">
                                <?= e($artist['display_name']) ?>
                            </h2>
                            <p class="text-[11px] text-stone uppercase tracking-wider mb-3"><?= e($artist['discipline']) ?></p>
                            <p class="text-xs text-stone italic">
                                Registered artist dossier pending administrative approval.
                            </p>
                        </div>
                    </div>
                    <div class="px-6 pb-6 pt-3 border-t border-hairline">
                        <span class="block text-center py-2.5 bg-alabaster text-stone border border-hairline text-[10px] uppercase tracking-widest font-semibold cursor-not-allowed">
                            Awaiting Admin Approval
                        </span>
                    </div>
                </article>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>
