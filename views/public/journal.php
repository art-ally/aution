<div class="max-w-[1440px] mx-auto px-4 sm:px-8 py-14">
    <div class="border-b border-hairline pb-8 mb-12">
        <p class="text-[11px] uppercase tracking-widest text-bronze font-semibold mb-2">Editorial &amp; Curatorial Perspectives</p>
        <h1 class="font-serif text-4xl sm:text-5xl text-obsidian font-normal">Stories, Studio Visits &amp; Collecting Guides</h1>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-10">
        <?php foreach ($articles as $article): ?>
            <article class="bg-white border border-hairline flex flex-col justify-between group hover:border-bronze transition">
                <div>
                    <a href="/journal/<?= e($article['slug']) ?>" class="block aspect-[16/10] bg-alabaster overflow-hidden">
                        <img src="<?= e($article['image']) ?>" alt="<?= e($article['title']) ?>" class="w-full h-full object-cover gallery-image-zoom">
                    </a>
                    <div class="p-8 space-y-3">
                        <div class="flex items-center justify-between text-[10px] uppercase tracking-widest text-bronze font-semibold">
                            <span><?= e($article['category']) ?></span>
                            <span class="text-stone"><?= e($article['reading_time'] ?? '6 min read') ?></span>
                        </div>
                        <h2 class="font-serif text-3xl text-obsidian group-hover:text-bronze transition leading-snug">
                            <a href="/journal/<?= e($article['slug']) ?>"><?= e($article['title']) ?></a>
                        </h2>
                        <p class="text-xs text-charcoal/80 leading-relaxed"><?= e($article['excerpt']) ?></p>
                    </div>
                </div>
                <div class="px-8 pb-6 pt-4 border-t border-hairline flex items-center justify-between text-xs text-stone">
                    <span><?= e($article['author']) ?></span>
                    <a href="/journal/<?= e($article['slug']) ?>" class="uppercase tracking-widest text-obsidian font-semibold group-hover:text-bronze">Read Essay →</a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</div>
