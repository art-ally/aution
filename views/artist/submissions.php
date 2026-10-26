<div class="max-w-[1440px] mx-auto px-4 sm:px-8 py-12">
    <div class="border-b border-hairline pb-6 mb-8 flex flex-col sm:flex-row sm:items-end justify-between gap-4">
        <div>
            <p class="text-[11px] uppercase tracking-widest text-bronze font-semibold mb-1">Artist Studio Consignment Log</p>
            <h1 class="font-serif text-4xl text-obsidian">Submission Status &amp; Curatorial Feedback</h1>
        </div>
        <div class="flex gap-3">
            <a href="/artist/submit" class="bg-obsidian text-ivory px-6 py-3 text-xs uppercase tracking-widest hover:bg-bronze transition">+ Submit New Artwork</a>
            <a href="/artist/dashboard" class="border border-obsidian px-5 py-3 text-xs uppercase tracking-widest">Studio Dashboard</a>
        </div>
    </div>

    <div class="bg-white border border-hairline divide-y divide-hairline">
        <?php foreach ($submissions as $sub): ?>
            <?php $art = $sub['artwork'] ?? []; ?>
            <div class="p-6 sm:p-8 grid grid-cols-1 lg:grid-cols-12 gap-6 items-center">
                <div class="lg:col-span-2 aspect-[4/3] bg-alabaster overflow-hidden border border-hairline">
                    <img src="<?= e($art['primary_image'] ?? '') ?>" alt="<?= e($art['title'] ?? '') ?>" class="w-full h-full object-cover">
                </div>

                <div class="lg:col-span-6 space-y-2">
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <span class="font-mono font-semibold text-bronze"><?= e($sub['reference_code']) ?></span>
                        <span class="px-2.5 py-0.5 text-[10px] uppercase tracking-widest bg-alabaster border border-hairline font-semibold">
                            <?= e(strtoupper(str_replace('_', ' ', $sub['status']))) ?>
                        </span>
                        <span class="text-stone">Submitted <?= e(gmdate('d M Y', strtotime($sub['created_at'] ?? 'now'))) ?></span>
                    </div>

                    <h2 class="font-serif text-2xl text-obsidian"><?= e($art['title'] ?? 'Untitled Work') ?> (<?= e((string)($art['year_created'] ?? '')) ?>)</h2>
                    <p class="text-xs text-stone"><?= e($art['medium'] ?? '') ?> · <?= e($art['dimensions'] ?? '') ?></p>

                    <div class="bg-ivory border border-hairline p-3 text-xs mt-2">
                        <span class="text-[10px] uppercase tracking-widest text-bronze font-semibold block">Curatorial Committee Note:</span>
                        <p class="text-charcoal mt-0.5"><?= e($sub['admin_feedback'] ?? 'Awaiting evaluation.') ?></p>
                    </div>
                </div>

                <div class="lg:col-span-4 lg:text-right space-y-3 border-t lg:border-t-0 lg:border-l border-hairline pt-4 lg:pt-0 lg:pl-6 text-xs">
                    <div>
                        <p class="text-stone">Suggested Opening Bid: <strong class="text-obsidian"><?= format_money($sub['suggested_starting_bid'] ?? 0) ?></strong></p>
                        <p class="text-stone">Confidential Reserve: <strong class="text-obsidian"><?= format_money($sub['reserve_price'] ?? 0) ?></strong></p>
                    </div>

                    <div class="flex flex-wrap lg:justify-end gap-2 pt-2">
                        <?php if (in_array($sub['status'], ['draft', 'submitted', 'changes_requested'], true)): ?>
                            <a href="/artist/submit?edit_id=<?= (int)$sub['id'] ?>" class="px-4 py-2 border border-obsidian text-[11px] uppercase tracking-widest hover:bg-obsidian hover:text-ivory transition">
                                Edit Dossier
                            </a>
                        <?php endif; ?>

                        <?php if ($sub['status'] !== 'published' && $sub['status'] !== 'withdrawn'): ?>
                            <form action="/artist/submissions/withdraw" method="POST">
                                <?= csrf_field() ?>
                                <input type="hidden" name="submission_id" value="<?= (int)$sub['id'] ?>">
                                <button type="submit" class="px-4 py-2 border border-hairline text-red-700 text-[11px] uppercase tracking-widest hover:border-red-700 transition">
                                    Withdraw
                                </button>
                            </form>
                        <?php endif; ?>

                        <?php if (!empty($art['slug'])): ?>
                            <a href="/artworks/<?= e($art['slug']) ?>" class="px-4 py-2 bg-obsidian text-ivory text-[11px] uppercase tracking-widest hover:bg-bronze transition">
                                View Lot →
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
