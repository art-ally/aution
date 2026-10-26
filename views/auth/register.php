<div class="max-w-3xl mx-auto px-4 sm:px-8 py-16" x-data="{
    role: '<?= e($selectedRole ?? 'artist') ?>',
    buyerCodeInput: '',
    expectedBuyerCode: '<?= e($buyerVerificationCode ?? 'NOIR-BUYER-2026') ?>',
    buyerUnlocked: false,
    buyerCodeError: '',
    portraitPreview: '',
    portraitData: '',
    verifyBuyerCode() {
        if (this.buyerCodeInput.trim().toUpperCase() === this.expectedBuyerCode.trim().toUpperCase() && this.buyerCodeInput.trim() !== '') {
            this.buyerUnlocked = true;
            this.buyerCodeError = '';
        } else {
            this.buyerUnlocked = false;
            this.buyerCodeError = 'Invalid Buyer Verification Code. Please obtain an official Collector Verification Code from ArtAlly Administration, or register as an Artist.';
        }
    },
    handlePortraitChange(e) {
        const file = e.target.files[0];
        if (!file) return;
        this.portraitPreview = URL.createObjectURL(file);
        const reader = new FileReader();
        reader.onload = (ev) => {
            this.portraitData = ev.target.result;
        };
        reader.readAsDataURL(file);
    }
}">
    <div class="bg-white border border-hairline p-8 sm:p-12">
        <p class="text-[11px] uppercase tracking-widest text-bronze font-semibold mb-2">Artist Registration Portal &amp; Verified Collector Access</p>
        <h1 class="font-serif text-4xl text-obsidian font-normal mb-2">Create Your ArtAlly Studio Account</h1>
        <p class="text-xs text-stone mb-6">
            Independent Artists may register directly below. Collector &amp; Buyer registration requires an official <strong>Verification Code</strong> to unlock the buyer path.
        </p>

        <!-- Role Path Selector (Artist Primary / Buyer Locked by Verification Code) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
            <button
                type="button"
                @click="role = 'artist'"
                :class="role === 'artist' ? 'bg-obsidian text-ivory border-obsidian' : 'bg-ivory text-charcoal border-hairline hover:border-obsidian'"
                class="p-5 border text-left transition"
            >
                <span class="block text-[10px] uppercase tracking-widest text-bronzeLight mb-1">Primary Registration Path (Open)</span>
                <span class="font-serif text-2xl block">Artist &amp; Consignor</span>
                <span class="text-[11px] opacity-80 block mt-1">Register your studio profile, upload your portrait &amp; artworks, and set expected prices.</span>
            </button>

            <button
                type="button"
                @click="role = 'buyer'"
                :class="role === 'buyer' ? 'bg-obsidian text-ivory border-obsidian' : 'bg-ivory text-charcoal border-hairline hover:border-obsidian'"
                class="p-5 border text-left transition"
            >
                <span class="block text-[10px] uppercase tracking-widest text-bronzeLight mb-1">🔒 Restricted Path (Verification Code Required)</span>
                <span class="font-serif text-2xl block">Collector &amp; Buyer</span>
                <span class="text-[11px] opacity-80 block mt-1">Requires an invitation Verification Code to unlock the buyer registration path.</span>
            </button>
        </div>

        <!-- BUYER VERIFICATION CODE GATE (Shown when Buyer Path is clicked and not yet unlocked) -->
        <div x-show="role === 'buyer' && !buyerUnlocked" x-cloak class="bg-ivory border-2 border-bronze p-8 space-y-5 text-xs">
            <div class="flex items-center space-x-3">
                <span class="bg-bronze text-white text-[10px] uppercase tracking-widest px-3 py-1 font-bold">🔒 Verification Code Required</span>
                <h2 class="font-serif text-2xl text-obsidian">Collector &amp; Buyer Access Verification</h2>
            </div>
            <p class="text-charcoal leading-relaxed">
                This registration page is dedicated to <strong>Artist Studio Registrations</strong>. To access the Collector &amp; Buyer registration path, please enter your official <strong>Buyer Verification Code</strong> below.
            </p>

            <template x-if="buyerCodeError">
                <div class="bg-[#FDF2F2] border border-red-800 text-red-900 px-4 py-3 text-xs font-medium" x-text="buyerCodeError"></div>
            </template>

            <div class="flex flex-col sm:flex-row gap-3">
                <input
                    type="text"
                    x-model="buyerCodeInput"
                    @keydown.enter.prevent="verifyBuyerCode()"
                    placeholder="Enter Buyer Verification Code..."
                    class="flex-1 bg-white border border-obsidian px-4 py-3.5 text-sm font-mono uppercase focus:outline-none focus:border-bronze"
                >
                <button
                    type="button"
                    @click="verifyBuyerCode()"
                    class="bg-obsidian text-ivory px-6 py-3.5 text-xs uppercase tracking-widest font-semibold hover:bg-bronze transition"
                >
                    Verify Code &amp; Unlock Buyer Path
                </button>
            </div>

            <div class="pt-2 border-t border-hairline flex items-center justify-between text-[11px] text-stone">
                <span>Are you an independent artist looking to upload and sell your work?</span>
                <button type="button" @click="role = 'artist'" class="text-obsidian font-semibold underline hover:text-bronze">
                    ← Return to Artist Registration
                </button>
            </div>
        </div>

        <!-- MAIN REGISTRATION FORM (Always shown for Artists, or for Buyers after Verification Code is unlocked) -->
        <form x-show="role === 'artist' || (role === 'buyer' && buyerUnlocked)" action="/register" method="POST" enctype="multipart/form-data" class="space-y-5 text-xs">
            <?= csrf_field() ?>
            <input type="hidden" name="account_type" :value="role">
            <input type="hidden" name="buyer_verification_code" :value="buyerCodeInput">
            <input type="hidden" name="profile_photo_data" :value="portraitData">

            <template x-if="role === 'buyer' && buyerUnlocked">
                <div class="bg-[#1A2E22] text-ivory px-4 py-3 flex items-center justify-between">
                    <span>✓ Buyer Verification Code Accepted (<strong class="font-mono" x-text="buyerCodeInput.toUpperCase()"></strong>) — Collector Registration Unlocked</span>
                    <button type="button" @click="buyerUnlocked = false; buyerCodeInput = ''" class="text-[10px] uppercase tracking-widest underline text-bronzeLight">Lock</button>
                </div>
            </template>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block uppercase tracking-widest text-stone mb-2" x-text="role === 'artist' ? 'Artist / Studio Display Name *' : 'Full Legal Collector Name *'">Artist / Studio Display Name *</label>
                    <input type="text" name="name" required placeholder="e.g. Camille Laurent" class="w-full bg-ivory border border-hairline px-4 py-3.5 text-sm focus:outline-none focus:border-bronze">
                </div>

                <div>
                    <label class="block uppercase tracking-widest text-stone mb-2">Country / Jurisdiction *</label>
                    <input type="text" name="country" required placeholder="e.g. France, Switzerland, United States" class="w-full bg-ivory border border-hairline px-4 py-3.5 text-sm focus:outline-none focus:border-bronze">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block uppercase tracking-widest text-stone mb-2">Email Address *</label>
                    <input type="email" name="email" required placeholder="name@example.com" class="w-full bg-ivory border border-hairline px-4 py-3.5 text-sm focus:outline-none focus:border-bronze">
                </div>

                <div>
                    <label class="block uppercase tracking-widest text-stone mb-2">Password (Min. 8 Characters) *</label>
                    <input type="password" name="password" required minlength="8" placeholder="••••••••••••" class="w-full bg-ivory border border-hairline px-4 py-3.5 text-sm focus:outline-none focus:border-bronze">
                </div>
            </div>

            <!-- Artist Profile Fields (Shown when Artist Path selected) -->
            <div x-show="role === 'artist'" x-transition class="space-y-4 pt-3 border-t border-hairline">
                <p class="text-[11px] uppercase tracking-widest text-bronze font-semibold">Artist Studio Registration Dossier &amp; Portrait Upload</p>

                <!-- Artist Profile Picture Upload with Live Preview -->
                <div class="bg-ivory border border-bronze p-5 flex flex-col sm:flex-row items-start sm:items-center gap-5">
                    <div class="w-20 h-20 bg-alabaster border border-hairline overflow-hidden flex-shrink-0 flex items-center justify-center">
                        <template x-if="portraitPreview">
                            <img :src="portraitPreview" alt="Artist Portrait Preview" class="w-full h-full object-cover">
                        </template>
                        <template x-if="!portraitPreview">
                            <span class="text-[10px] uppercase tracking-widest text-stone text-center px-1">Portrait Preview</span>
                        </template>
                    </div>
                    <div class="flex-1 space-y-1.5">
                        <label class="block uppercase tracking-widest text-obsidian font-semibold">Upload Artist Profile Picture *</label>
                        <input
                            type="file"
                            name="profile_photo_file"
                            accept="image/*"
                            @change="handlePortraitChange($event)"
                            class="w-full text-xs"
                        >
                        <p class="text-[11px] text-stone">Select your portrait photograph (JPG, PNG, WEBP). Your profile picture will immediately appear in your Studio Dashboard, Public Artist Page, and Admin Backend.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label class="block uppercase tracking-widest text-stone mb-2">Primary Artistic Discipline</label>
                        <input type="text" name="discipline" placeholder="e.g. Oil on Linen, Cast Bronze Sculpture" class="w-full bg-ivory border border-hairline px-4 py-3 text-sm focus:outline-none focus:border-bronze">
                    </div>

                    <div>
                        <label class="block uppercase tracking-widest text-stone mb-2">City / Studio Location</label>
                        <input type="text" name="city" placeholder="e.g. Paris, London, New York" class="w-full bg-ivory border border-hairline px-4 py-3 text-sm focus:outline-none focus:border-bronze">
                    </div>
                </div>

                <div>
                    <label class="block uppercase tracking-widest text-stone mb-2">Artist Biography / Statement</label>
                    <textarea name="biography" rows="3" placeholder="Introduce your artistic background, exhibitions, and studio practice..." class="w-full bg-ivory border border-hairline p-3.5 text-sm focus:outline-none focus:border-bronze"></textarea>
                </div>

                <p class="text-[11px] text-stone">
                    Note: Upon registration, you can immediately enter your private Artist Studio to upload artworks and expected prices. Your public artist profile will show <strong>Awaiting Approval</strong> on the Artists page until approved by the Administrator.
                </p>
            </div>

            <div class="pt-2">
                <label class="flex items-start space-x-3 cursor-pointer">
                    <input type="checkbox" name="terms_accepted" value="1" required class="mt-1 accent-obsidian">
                    <span class="text-xs text-charcoal leading-relaxed">
                        I accept the <a href="/terms" class="underline text-obsidian">Conditions of Sale</a>, <a href="/privacy" class="underline text-obsidian">Collector Confidentiality Policy</a>, and confirm that all bids and consignments are governed by ArtAlly institutional rules.
                    </span>
                </label>
            </div>

            <button type="submit" class="w-full bg-obsidian text-ivory py-4 text-xs uppercase tracking-widest font-semibold hover:bg-bronze transition">
                <span x-text="role === 'artist' ? 'Complete Artist Registration & Enter Studio' : 'Complete Verified Collector Registration'">Complete Artist Registration &amp; Enter Studio</span>
            </button>
        </form>
    </div>
</div>
