<div class="max-w-xl mx-auto px-4 sm:px-8 py-16">
    <div class="bg-white border border-hairline p-8 sm:p-12 shadow-sm">
        <p class="text-[11px] uppercase tracking-widest text-bronze font-semibold mb-2">Private Client &amp; Studio Access</p>
        <h1 class="font-serif text-4xl text-obsidian font-normal mb-6">Sign In to ArtAlly</h1>

        <form action="/login" method="POST" class="space-y-5 text-xs">
            <?= csrf_field() ?>
            <div>
                <label for="email" class="block uppercase tracking-widest text-stone mb-2">Email Address</label>
                <input type="email" id="email" name="email" required placeholder="name@example.com" class="w-full bg-ivory border border-hairline px-4 py-3.5 text-sm focus:outline-none focus:border-bronze">
            </div>

            <div>
                <div class="flex items-center justify-between mb-2">
                    <label for="password" class="uppercase tracking-widest text-stone">Password</label>
                    <a href="/forgot-password" class="text-bronze hover:underline">Forgot Password?</a>
                </div>
                <input type="password" id="password" name="password" required placeholder="••••••••••••" class="w-full bg-ivory border border-hairline px-4 py-3.5 text-sm focus:outline-none focus:border-bronze">
            </div>

            <button type="submit" class="w-full bg-obsidian text-ivory py-4 text-xs uppercase tracking-widest font-semibold hover:bg-bronze transition">
                Sign In to Account
            </button>
        </form>

        <p class="text-xs text-stone mt-8 pt-6 border-t border-hairline text-center">
            Do not have an account yet?
            <a href="/register?role=buyer" class="text-obsidian font-semibold underline ml-1">Register as Collector</a>
            or
            <a href="/register?role=artist" class="text-bronze font-semibold underline ml-1">Register as Artist</a>
        </p>
    </div>
</div>
