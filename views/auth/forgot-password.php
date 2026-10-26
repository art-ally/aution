<div class="max-w-xl mx-auto px-4 sm:px-8 py-20">
    <div class="bg-white border border-hairline p-8 sm:p-12 space-y-6">
        <p class="text-[11px] uppercase tracking-widest text-bronze font-semibold">Credential Recovery</p>
        <h1 class="font-serif text-4xl text-obsidian">Reset Your Password</h1>
        <p class="text-xs text-stone">Enter your registered email address to generate a secure password recovery token.</p>

        <form action="/forgot-password" method="POST" class="space-y-4 text-xs">
            <?= csrf_field() ?>
            <div>
                <label class="block uppercase tracking-widest text-stone mb-2">Registered Email Address</label>
                <input type="email" name="email" required placeholder="collector@ateliernoir.art" class="w-full bg-ivory border border-hairline px-4 py-3.5 text-sm">
            </div>
            <button type="submit" class="w-full bg-obsidian text-ivory py-3.5 text-xs uppercase tracking-widest hover:bg-bronze transition">
                Generate Recovery Link
            </button>
        </form>
    </div>
</div>
