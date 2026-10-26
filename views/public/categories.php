<div class="max-w-[1440px] mx-auto px-4 sm:px-8 py-14">
    <div class="border-b border-hairline pb-8 mb-12">
        <p class="text-[11px] uppercase tracking-widest text-bronze font-semibold mb-2">Specialist Departments</p>
        <h1 class="font-serif text-4xl sm:text-5xl text-obsidian font-normal">Explore Art by Curatorial Category</h1>
        <p class="text-sm text-charcoal/80 mt-3 max-w-2xl">
            Every work offered by ArtAlly is assigned to one of ten specialist departments and inspected for provenance, attribution, and material integrity.
        </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <?php foreach ($categories as $cat): ?>
            <a href="/catalogue?category=<?= e($cat['slug']) ?>" class="group bg-white border border-hairline grid grid-cols-1 sm:grid-cols-12 overflow-hidden hover:border-bronze transition">
                <div class="sm:col-span-5 aspect-[4/3] sm:aspect-auto bg-obsidian overflow-hidden">
                    <img src="<?= e($cat['image']) ?>" alt="<?= e($cat['name']) ?>" class="w-full h-full object-cover gallery-image-zoom">
                </div>
                <div class="sm:col-span-7 p-8 flex flex-col justify-between">
                    <div>
                        <span class="text-[10px] uppercase tracking-widest text-bronze font-semibold">Department 0<?= (int)$cat['id'] ?></span>
                        <h2 class="font-serif text-3xl text-obsidian group-hover:text-bronze transition mt-1 mb-3"><?= e($cat['name']) ?></h2>
                        <p class="text-xs text-charcoal/80 leading-relaxed"><?= e($cat['description']) ?></p>
                    </div>
                    <div class="pt-6 mt-6 border-t border-hairline flex items-center justify-between text-xs">
                        <span class="text-stone uppercase tracking-wider text-[11px]"><?= (int)($cat['artwork_count'] ?? 0) ?> Published Works</span>
                        <span class="uppercase tracking-widest font-semibold text-obsidian group-hover:text-bronze">Browse Catalogue →</span>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</div>
