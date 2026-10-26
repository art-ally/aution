<div class="max-w-4xl mx-auto px-4 sm:px-8 py-16">
    <div class="bg-obsidian text-ivory border border-charcoal shadow-2xl overflow-hidden grid grid-cols-1 lg:grid-cols-12">
        <!-- Left Column: Executive Branding & Security Notice -->
        <div class="lg:col-span-5 bg-[#181818] p-8 sm:p-12 border-b lg:border-b-0 lg:border-r border-charcoal flex flex-col justify-between">
            <div class="space-y-6">
                <span class="inline-block bg-bronze text-white text-[10px] uppercase tracking-widest px-3 py-1 font-semibold">
                    Restricted Executive Access
                </span>
                <h1 class="font-serif text-3xl sm:text-4xl text-ivory font-normal leading-tight">
                    ArtAlly<br>
                    <span class="italic text-bronzeLight">Curatorial &amp; Auction Command</span>
                </h1>
                <p class="text-xs text-gray-300 leading-relaxed">
                    Private backend access for Chief Curators and Auctioneers. Manage real artist registrations, approve public artist profiles, negotiate agreed prices, increase live bids, mark works SOLD, and manage or delete admin profiles.
                </p>

                <div class="border-t border-charcoal pt-5 space-y-3 text-[11px] text-gray-300">
                    <div class="flex items-start space-x-2.5">
                        <span class="text-bronzeLight">01.</span>
                        <span>Approve registered artists before their profiles display publicly.</span>
                    </div>
                    <div class="flex items-start space-x-2.5">
                        <span class="text-bronzeLight">02.</span>
                        <span>Negotiate agreed prices with artists and inspect all uploaded artwork images.</span>
                    </div>
                    <div class="flex items-start space-x-2.5">
                        <span class="text-bronzeLight">03.</span>
                        <span>Increase live bid prices, change status to SOLD, or delete any artist/admin profile.</span>
                    </div>
                </div>
            </div>

            <div class="pt-8 mt-8 border-t border-charcoal text-[11px] text-stone">
                Private Admin URL: <code class="text-ivory">/admin/login</code>
            </div>
        </div>

        <!-- Right Column: Admin Sign-In Form OR Initial Admin Setup (if Admin Profile was deleted) -->
        <div class="lg:col-span-7 p-8 sm:p-12 bg-white text-obsidian flex flex-col justify-between">
            <?php if (!empty($noAdminsExist)): ?>
                <div>
                    <span class="inline-block bg-bronze text-white text-[10px] uppercase tracking-widest px-2.5 py-1 font-semibold mb-2">
                        Initial Administrator Setup
                    </span>
                    <h2 class="font-serif text-3xl text-obsidian mb-3">Create New Master Admin Profile</h2>
                    <p class="text-xs text-stone mb-6">
                        The previous Administrator profile was deleted. Create your new Master Administrator credentials below:
                    </p>

                    <form action="/admin/setup-first" method="POST" class="space-y-4 text-xs">
                        <?= csrf_field() ?>
                        <div>
                            <label class="block uppercase tracking-widest text-stone mb-1.5">Administrator Full Name *</label>
                            <input type="text" name="name" required placeholder="e.g. Executive Director" class="w-full bg-ivory border border-hairline px-4 py-3 text-sm text-obsidian">
                        </div>
                        <div>
                            <label class="block uppercase tracking-widest text-stone mb-1.5">Administrator Email *</label>
                            <input type="email" name="email" required placeholder="admin@ateliernoir.art" class="w-full bg-ivory border border-hairline px-4 py-3 text-sm text-obsidian">
                        </div>
                        <div>
                            <label class="block uppercase tracking-widest text-stone mb-1.5">New Password (Min. 8 Characters) *</label>
                            <input type="password" name="password" minlength="8" required placeholder="••••••••••••" class="w-full bg-ivory border border-hairline px-4 py-3 text-sm text-obsidian">
                        </div>
                        <button type="submit" class="w-full bg-obsidian text-ivory py-4 text-xs uppercase tracking-widest font-semibold hover:bg-bronze transition">
                            Create Master Admin &amp; Enter Backend →
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <div>
                    <p class="text-[11px] uppercase tracking-widest text-bronze font-semibold mb-1">Backend Administration Portal</p>
                    <h2 class="font-serif text-3xl text-obsidian mb-6">Administrator Sign In</h2>

                    <form action="/admin/login" method="POST" class="space-y-5 text-xs">
                        <?= csrf_field() ?>
                        <div>
                            <label class="block uppercase tracking-widest text-stone mb-2">Administrator Email *</label>
                            <input type="email" name="email" value="admin@ateliernoir.art" required class="w-full bg-ivory border border-hairline px-4 py-3.5 text-sm text-obsidian focus:outline-none focus:border-bronze">
                        </div>

                        <div>
                            <label class="block uppercase tracking-widest text-stone mb-2">Security Password *</label>
                            <input type="password" name="password" value="AtelierNoir2026!" required class="w-full bg-ivory border border-hairline px-4 py-3.5 text-sm text-obsidian focus:outline-none focus:border-bronze">
                        </div>

                        <button type="submit" class="w-full bg-obsidian text-ivory py-4 text-xs uppercase tracking-widest font-semibold hover:bg-bronze transition">
                            Enter Executive Admin Backend →
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
