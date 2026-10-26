<div class="max-w-4xl mx-auto px-4 sm:px-8 py-12" x-data="{
    previewPhoto: '<?= e($profile['profile_photo']) ?>',
    photoData: '',
    handleProfilePhoto(e) {
        const file = e.target.files[0];
        if (!file) return;
        this.previewPhoto = URL.createObjectURL(file);
        const reader = new FileReader();
        reader.onload = (ev) => {
            this.photoData = ev.target.result;
        };
        reader.readAsDataURL(file);
    }
}">
    <div class="border-b border-hairline pb-6 mb-8 flex items-end justify-between">
        <div>
            <p class="text-[11px] uppercase tracking-widest text-bronze font-semibold mb-1">Public Catalogue Dossier</p>
            <h1 class="font-serif text-4xl text-obsidian">Edit Artist Profile, Portrait &amp; Biography</h1>
        </div>
        <a href="/artist/dashboard" class="text-xs uppercase tracking-widest text-stone hover:text-obsidian">← Studio Dashboard</a>
    </div>

    <form action="/artist/profile" method="POST" enctype="multipart/form-data" class="bg-white border border-hairline p-8 sm:p-12 space-y-6 text-xs">
        <?= csrf_field() ?>
        <input type="hidden" name="profile_photo_data" :value="photoData">

        <!-- Artist Portrait Upload & Live Preview Box -->
        <div class="bg-ivory border-2 border-bronze p-6 flex flex-col sm:flex-row items-start sm:items-center gap-6">
            <div class="w-28 h-28 bg-alabaster border border-hairline overflow-hidden flex-shrink-0">
                <img :src="previewPhoto" src="<?= e($profile['profile_photo']) ?>" alt="<?= e($profile['display_name']) ?>" class="w-full h-full object-cover">
            </div>
            <div class="flex-1 space-y-3">
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-bronze font-semibold">Studio Portrait Photograph</p>
                    <h2 class="font-serif text-2xl text-obsidian">Upload Artist Profile Picture</h2>
                    <p class="text-[11px] text-stone mt-0.5">Select an image file from your device (JPG, PNG, WEBP) to update your portrait across your Studio Dashboard, Public Artist Page, and Admin Backend.</p>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-center">
                    <div>
                        <label class="block text-[10px] uppercase tracking-widest text-obsidian font-semibold mb-1">Choose Image File from Device</label>
                        <input type="file" name="profile_photo_file" accept="image/*" @change="handleProfilePhoto($event)" class="w-full text-xs">
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase tracking-widest text-stone mb-1">Or Portrait Image URL</label>
                        <input type="text" name="profile_photo_url" x-model="previewPhoto" value="<?= e($profile['profile_photo']) ?>" class="w-full bg-white border border-hairline px-3 py-2 text-xs">
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block uppercase tracking-widest text-stone mb-2">Studio Display Name *</label>
                <input type="text" name="display_name" value="<?= e($profile['display_name']) ?>" required class="w-full bg-ivory border border-hairline px-4 py-3 text-sm">
            </div>
            <div>
                <label class="block uppercase tracking-widest text-stone mb-2">Artistic Discipline *</label>
                <input type="text" name="discipline" value="<?= e($profile['discipline']) ?>" required class="w-full bg-ivory border border-hairline px-4 py-3 text-sm">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <label class="block uppercase tracking-widest text-stone mb-2">Country</label>
                <input type="text" name="country" value="<?= e($profile['country']) ?>" class="w-full bg-ivory border border-hairline px-4 py-3 text-sm">
            </div>
            <div>
                <label class="block uppercase tracking-widest text-stone mb-2">City / Studio Location</label>
                <input type="text" name="city" value="<?= e($profile['city'] ?? '') ?>" class="w-full bg-ivory border border-hairline px-4 py-3 text-sm">
            </div>
            <div>
                <label class="block uppercase tracking-widest text-stone mb-2">Years Active</label>
                <input type="text" name="years_active" value="<?= e($profile['years_active'] ?? '') ?>" class="w-full bg-ivory border border-hairline px-4 py-3 text-sm">
            </div>
        </div>

        <div>
            <label class="block uppercase tracking-widest text-stone mb-2">Biography *</label>
            <textarea name="biography" rows="4" required class="w-full bg-ivory border border-hairline p-4 text-sm"><?= e($profile['biography']) ?></textarea>
        </div>

        <div>
            <label class="block uppercase tracking-widest text-stone mb-2">Artist Statement</label>
            <textarea name="artist_statement" rows="3" class="w-full bg-ivory border border-hairline p-4 text-sm"><?= e($profile['artist_statement'] ?? '') ?></textarea>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block uppercase tracking-widest text-stone mb-2">Studio Website</label>
                <input type="text" name="website" value="<?= e($profile['website'] ?? '') ?>" class="w-full bg-ivory border border-hairline px-4 py-3 text-sm">
            </div>
            <div>
                <label class="block uppercase tracking-widest text-stone mb-2">Instagram / Social Handle</label>
                <input type="text" name="social_instagram" value="<?= e($profile['social_instagram'] ?? '') ?>" class="w-full bg-ivory border border-hairline px-4 py-3 text-sm">
            </div>
        </div>

        <button type="submit" class="bg-obsidian text-ivory px-8 py-4 text-xs uppercase tracking-widest font-semibold hover:bg-bronze transition">
            Save Public Artist Profile &amp; Portrait
        </button>
    </form>
</div>
