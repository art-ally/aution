<article class="max-w-4xl mx-auto px-4 sm:px-8 py-14">
    <div class="mb-8">
        <a href="/journal" class="text-[11px] uppercase tracking-widest text-bronze hover:underline">← Back to Stories &amp; Journal</a>
        <div class="flex items-center space-x-3 text-xs uppercase tracking-widest text-stone mt-4">
            <span class="text-bronze font-semibold"><?= e($article['category']) ?></span>
            <span>·</span>
            <span><?= e($article['reading_time'] ?? '6 min read') ?></span>
            <span>·</span>
            <span>By <?= e($article['author']) ?></span>
        </div>
        <h1 class="font-serif text-4xl sm:text-6xl text-obsidian mt-3 leading-tight"><?= e($article['title']) ?></h1>
    </div>

    <div class="aspect-[16/9] bg-alabaster overflow-hidden border border-hairline mb-10">
        <img src="<?= e($article['image']) ?>" alt="<?= e($article['title']) ?>" class="w-full h-full object-cover">
    </div>

    <div class="bg-white border border-hairline p-8 sm:p-12 space-y-6 text-base text-charcoal leading-relaxed font-light whitespace-pre-line">
        <?= e($article['body']) ?>
    </div>
</article>
