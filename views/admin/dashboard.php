<div class="max-w-[1440px] mx-auto px-4 sm:px-8 py-12 space-y-14">
    <!-- Top Executive Header -->
    <div class="border-b border-hairline pb-8 flex flex-col lg:flex-row lg:items-end justify-between gap-6">
        <div>
            <span class="bg-obsidian text-bronzeLight text-[10px] uppercase tracking-widest px-3 py-1 font-semibold">Private Executive Backend (Direct Link Access Only)</span>
            <h1 class="font-serif text-4xl sm:text-5xl text-obsidian font-normal mt-2">ArtAlly Administration Backend</h1>
            <p class="text-xs text-stone mt-1">Signed in as <?= e($admin['name']) ?> (<?= e($admin['email']) ?>) · Real Registrations, Artist Profile Approval &amp; Live Bid / Sold Controls</p>
        </div>
        <div class="flex flex-wrap gap-2.5 text-xs uppercase tracking-widest">
            <a href="#artists" class="px-4 py-2.5 bg-obsidian text-ivory hover:bg-bronze transition">Real Artists &amp; Uploads (<?= $stats['total_artists'] ?>)</a>
            <a href="#submissions" class="px-4 py-2.5 border border-obsidian hover:bg-obsidian hover:text-ivory transition">Submissions (<?= $stats['pending_submissions'] ?>)</a>
            <a href="#auctions" class="px-4 py-2.5 border border-obsidian hover:bg-obsidian hover:text-ivory transition">Live Bid &amp; Sold Control (<?= $stats['active_auctions'] ?>)</a>
            <a href="#payouts" class="px-4 py-2.5 border border-obsidian hover:bg-obsidian hover:text-ivory transition">Payouts</a>
            <a href="#settings" class="px-4 py-2.5 border border-obsidian hover:bg-obsidian hover:text-ivory transition">Settings &amp; Users</a>
            <a href="#admin-profile" class="px-4 py-2.5 border border-red-800 text-red-800 hover:bg-red-800 hover:text-white transition">Admin Profile &amp; Delete</a>
        </div>
    </div>

    <!-- Executive KPI Grid (Real Registrations Only) -->
    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-4">
        <div class="bg-white border border-hairline p-5">
            <p class="text-[10px] uppercase tracking-widest text-stone">Real Accounts</p>
            <p class="font-serif text-3xl font-semibold text-obsidian mt-1"><?= $stats['total_users'] ?></p>
        </div>
        <div class="bg-white border border-hairline p-5">
            <p class="text-[10px] uppercase tracking-widest text-stone">Registered Artists</p>
            <p class="font-serif text-3xl font-semibold text-obsidian mt-1"><?= $stats['total_artists'] ?></p>
        </div>
        <div class="bg-white border border-hairline p-5">
            <p class="text-[10px] uppercase tracking-widest text-stone">Pending Uploads</p>
            <p class="font-serif text-3xl font-semibold text-bronze mt-1"><?= $stats['pending_submissions'] ?></p>
        </div>
        <div class="bg-white border border-hairline p-5">
            <p class="text-[10px] uppercase tracking-widest text-stone">Live Auctions</p>
            <p class="font-serif text-3xl font-semibold text-obsidian mt-1"><?= $stats['active_auctions'] ?></p>
        </div>
        <div class="bg-white border border-hairline p-5">
            <p class="text-[10px] uppercase tracking-widest text-stone">Real Bids</p>
            <p class="font-serif text-3xl font-semibold text-obsidian mt-1"><?= $stats['total_bids'] ?></p>
        </div>
        <div class="bg-white border border-hairline p-5">
            <p class="text-[10px] uppercase tracking-widest text-stone">Hammer GMV</p>
            <p class="font-serif text-2xl font-semibold text-obsidian mt-1"><?= format_money($stats['gross_merchandise_value']) ?></p>
        </div>
        <div class="bg-white border border-hairline p-5">
            <p class="text-[10px] uppercase tracking-widest text-stone">House Revenue</p>
            <p class="font-serif text-2xl font-semibold text-bronze mt-1"><?= format_money($stats['platform_revenue']) ?></p>
        </div>
        <div class="bg-white border border-hairline p-5">
            <p class="text-[10px] uppercase tracking-widest text-stone">Pending Payouts</p>
            <p class="font-serif text-2xl font-semibold text-obsidian mt-1"><?= format_money($stats['pending_payout_total']) ?></p>
        </div>
    </div>

    <!-- SECTION 1: Real Registered Artists, Public Profile Approval Gate & All Uploaded Artworks -->
    <section id="artists" class="bg-white border border-hairline p-8 space-y-8">
        <div class="border-b border-hairline pb-4 flex flex-col sm:flex-row sm:items-end justify-between gap-4">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-bronze font-semibold">Real Artist Registrations &amp; Profile Approval Gate</p>
                <h2 class="font-serif text-3xl text-obsidian">Registered Artists, Uploaded Images &amp; Price/Auction Controls</h2>
            </div>
            <p class="text-xs text-stone max-w-md">
                Only real registered artists appear here (all dummy/seeded records are excluded). New artists show <strong>Awaiting Approval</strong> on the public Artists page until you click <strong>Approve Artist Profile</strong> below.
            </p>
        </div>

        <?php if (empty($artistProfiles)): ?>
            <div class="bg-ivory border border-hairline p-12 text-center space-y-3">
                <p class="text-[11px] uppercase tracking-widest text-bronze font-semibold">Zero Dummy Data Policy Active</p>
                <h3 class="font-serif text-2xl text-obsidian">No Real Artists Have Registered Yet</h3>
                <p class="text-xs text-stone max-w-lg mx-auto">
                    Dummy/seeded artists are hidden from this backend. Register a new artist account at <a href="/register?role=artist" class="underline text-obsidian font-semibold">/register?role=artist</a> to see their registration info, approve their public profile, and control their uploaded works here.
                </p>
            </div>
        <?php else: ?>
            <div class="space-y-10">
                <?php foreach ($artistProfiles as $ap): ?>
                    <?php
                    $u = $ap['user'] ?? [];
                    $artistWorks = $ap['artworks'] ?? [];
                    $isProfileApproved = ($ap['approval_status'] ?? 'pending') === 'approved';
                    ?>
                    <div class="border border-hairline bg-ivory p-6 sm:p-8 space-y-6">
                        <!-- Artist Registration & Profile Approval Header -->
                        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 border-b border-hairline pb-6">
                            <div class="flex flex-col sm:flex-row sm:items-center gap-5">
                                <img src="<?= e($ap['profile_photo']) ?>" alt="<?= e($ap['display_name']) ?>" class="w-20 h-20 object-cover border border-hairline bg-white">
                                <div class="space-y-1.5">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="bg-obsidian text-ivory text-[10px] uppercase tracking-widest px-2.5 py-0.5 font-semibold">
                                            Artist ID #<?= (int)$ap['user_id'] ?>
                                        </span>
                                        <?php if ($isProfileApproved): ?>
                                            <span class="bg-[#1A2E22] text-ivory text-[10px] uppercase tracking-widest px-2.5 py-0.5 font-semibold">
                                                ✓ Public Profile Approved &amp; Visible
                                            </span>
                                        <?php else: ?>
                                            <span class="bg-bronze text-white text-[10px] uppercase tracking-widest px-2.5 py-0.5 font-semibold">
                                                ◷ Awaiting Approval (Hidden on Public Artists Page)
                                            </span>
                                        <?php endif; ?>
                                        <span class="text-[11px] text-stone">
                                            · Registered: <?= e(gmdate('d M Y H:i', strtotime($u['created_at'] ?? 'now'))) ?> UTC
                                        </span>
                                    </div>
                                    <h3 class="font-serif text-3xl text-obsidian"><?= e($ap['display_name']) ?></h3>
                                    <p class="text-xs text-charcoal">
                                        <strong>Email:</strong> <?= e($u['email'] ?? '') ?> ·
                                        <strong>Discipline:</strong> <?= e($ap['discipline']) ?> ·
                                        <strong>Location:</strong> <?= e(($ap['city'] ? $ap['city'] . ', ' : '') . ($ap['country'] ?? '')) ?>
                                    </p>
                                    <p class="text-xs text-stone max-w-3xl"><?= e($ap['biography']) ?></p>
                                </div>
                            </div>

                            <!-- Approve / Revoke / Delete Public Artist Profile Buttons -->
                            <div class="flex flex-wrap items-center gap-2 self-start lg:self-center">
                                <form action="/admin/artists/approve" method="POST" class="inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="artist_profile_id" value="<?= (int)$ap['id'] ?>">
                                    <?php if (!$isProfileApproved): ?>
                                        <input type="hidden" name="decision" value="approved">
                                        <button type="submit" class="bg-[#1A2E22] text-ivory px-5 py-2.5 text-[11px] uppercase tracking-widest font-semibold hover:bg-bronze transition">
                                            ✓ Approve Artist Profile for Public Display
                                        </button>
                                    <?php else: ?>
                                        <input type="hidden" name="decision" value="pending">
                                        <button type="submit" class="border border-bronze text-bronze bg-white px-4 py-2.5 text-[11px] uppercase tracking-widest hover:bg-bronze hover:text-white transition">
                                            Set Profile to Awaiting Approval
                                        </button>
                                    <?php endif; ?>
                                </form>

                                <a href="/artists/<?= e($ap['slug']) ?>" target="_blank" class="px-4 py-2.5 bg-white border border-obsidian text-[11px] uppercase tracking-widest hover:bg-obsidian hover:text-ivory transition">
                                    Preview Profile Page →
                                </a>

                                <form action="/admin/users/delete" method="POST" class="inline" onsubmit="return confirm('Permanently delete this artist profile and all of their uploaded works?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="user_id" value="<?= (int)$ap['user_id'] ?>">
                                    <button type="submit" class="px-3.5 py-2.5 bg-white border border-red-800 text-red-800 text-[11px] uppercase tracking-widest hover:bg-red-800 hover:text-white transition">
                                        Delete Artist
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Artist's Uploaded Artworks, Uploaded Images & Master Admin Control Panel -->
                        <div class="space-y-4">
                            <h4 class="text-xs uppercase tracking-widest font-semibold text-obsidian">
                                Uploaded Artworks by <?= e($ap['display_name']) ?> (<?= count($artistWorks) ?> Works)
                            </h4>

                            <?php if (empty($artistWorks)): ?>
                                <p class="text-xs text-stone bg-white p-4 border border-hairline">This artist has not uploaded any artworks yet.</p>
                            <?php else: ?>
                                <div class="space-y-4">
                                    <?php foreach ($artistWorks as $work): ?>
                                        <?php
                                        $sub = $work['submission'] ?? [];
                                        $auc = $work['auction'] ?? null;
                                        $workImages = $work['images'] ?? [];
                                        $aucState = $auc['status'] ?? 'unscheduled';
                                        $expectedPrice = (float)($sub['artist_expected_price'] ?? $work['artist_expected_price'] ?? $work['estimate_high'] ?? $work['reserve_price'] ?? 0);
                                        $agreedPrice = (float)($work['agreed_price'] ?? $sub['agreed_price'] ?? $auc['agreed_price'] ?? $work['reserve_price'] ?? $expectedPrice);
                                        $startingBid = (float)($auc['starting_bid'] ?? $work['starting_bid'] ?? 1000);
                                        $currentBid = (float)($auc['current_bid'] ?? $startingBid);
                                        $reservePrice = (float)($auc['reserve_price'] ?? $work['reserve_price'] ?? $agreedPrice);
                                        $priceAgreementStatus = $work['price_agreement_status'] ?? $sub['price_agreement_status'] ?? 'pending';
                                        $negotiationMessages = $sub['negotiation_messages'] ?? [];
                                        ?>
                                        <div class="bg-white border border-hairline p-5 grid grid-cols-1 xl:grid-cols-12 gap-6 items-start">
                                            <!-- Uploaded Images & Artwork Details -->
                                            <div class="xl:col-span-4 space-y-3">
                                                <div class="flex items-start space-x-4">
                                                    <a href="<?= e($work['primary_image']) ?>" target="_blank" class="block w-24 h-28 bg-alabaster border border-hairline overflow-hidden flex-shrink-0">
                                                        <img src="<?= e($work['primary_image']) ?>" alt="" class="w-full h-full object-cover">
                                                    </a>
                                                    <div class="space-y-1 text-xs">
                                                        <div class="flex flex-wrap items-center gap-1.5">
                                                            <span class="font-mono text-[10px] font-semibold text-bronze"><?= e($sub['reference_code'] ?? ('LOT-' . $work['id'])) ?></span>
                                                            <span class="px-2 py-0.5 text-[9px] uppercase tracking-widest font-semibold <?= $aucState === 'active' ? 'bg-[#1A2E22] text-ivory' : ($aucState === 'closed_sold' ? 'bg-obsidian text-bronzeLight' : 'bg-alabaster text-charcoal border border-hairline') ?>">
                                                                <?= $aucState === 'closed_sold' ? 'SOLD' : e(strtoupper(str_replace('_', ' ', $aucState))) ?>
                                                            </span>
                                                            <span class="px-2 py-0.5 text-[9px] uppercase tracking-widest font-semibold <?= $priceAgreementStatus === 'accepted_by_artist' ? 'bg-[#1A2E22] text-ivory' : ($priceAgreementStatus === 'counter_proposed_by_artist' ? 'bg-bronze text-white' : 'bg-ivory text-stone border border-hairline') ?>">
                                                                <?= $priceAgreementStatus === 'accepted_by_artist' ? '✓ Price Accepted by Artist' : ($priceAgreementStatus === 'counter_proposed_by_artist' ? '⇄ Counter-Offer by Artist' : 'Price Pending') ?>
                                                            </span>
                                                        </div>
                                                        <h5 class="font-serif text-xl text-obsidian">
                                                            <a href="/artworks/<?= e($work['slug']) ?>" target="_blank" class="hover:text-bronze"><?= e($work['title']) ?></a>
                                                        </h5>
                                                        <p class="text-[11px] text-stone"><?= e($work['medium']) ?> · <?= e($work['dimensions']) ?></p>
                                                        <p class="text-[11px] text-charcoal">
                                                            <span class="text-stone">Artist Expected Price:</span>
                                                            <strong class="text-obsidian"><?= format_money($expectedPrice) ?></strong>
                                                        </p>
                                                        <div class="flex flex-wrap items-center gap-2 pt-1">
                                                            <?php if ($aucState === 'closed_sold' || ($work['status'] ?? '') === 'sold'): ?>
                                                                <a href="/certificates/<?= (int)$work['id'] ?>" target="_blank" class="inline-block bg-obsidian text-bronzeLight px-2.5 py-1 text-[9px] uppercase tracking-widest font-semibold hover:bg-bronze hover:text-white transition">
                                                                    ★ Official Certificate &amp; Invoice →
                                                                </a>
                                                            <?php endif; ?>
                                                            <form action="/admin/artworks/delete" method="POST" class="inline" onsubmit="return confirm('Permanently delete this uploaded artwork and its auction lot?');">
                                                                <?= csrf_field() ?>
                                                                <input type="hidden" name="artwork_id" value="<?= (int)$work['id'] ?>">
                                                                <button type="submit" class="px-2.5 py-1 border border-red-800 text-red-800 text-[9px] uppercase tracking-widest hover:bg-red-800 hover:text-white transition">
                                                                    Delete Upload
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>

                                                <?php if (count($workImages) > 1): ?>
                                                    <div class="flex items-center gap-2 pt-1 overflow-x-auto">
                                                        <span class="text-[10px] uppercase tracking-wider text-stone">All Uploaded Photos:</span>
                                                        <?php foreach ($workImages as $wImg): ?>
                                                            <a href="<?= e($wImg['image_url']) ?>" target="_blank" class="w-10 h-10 border border-hairline overflow-hidden flex-shrink-0">
                                                                <img src="<?= e($wImg['image_url']) ?>" alt="" class="w-full h-full object-cover">
                                                            </a>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if (!empty($negotiationMessages)): ?>
                                                    <div class="bg-ivory border border-hairline p-2.5 space-y-1.5 text-[11px] max-h-28 overflow-y-auto">
                                                        <p class="text-[9px] uppercase tracking-widest text-bronze font-semibold">Artist ↔ Admin Negotiation Log</p>
                                                        <?php foreach (array_slice($negotiationMessages, -3) as $nm): ?>
                                                            <p class="text-charcoal">
                                                                <strong class="text-obsidian"><?= e($nm['sender_name'] ?? $nm['sender'] ?? 'Artist') ?>:</strong>
                                                                <?= e($nm['text'] ?? $nm['message'] ?? '') ?>
                                                            </p>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Master Admin Form: Control Bid Price, Agreed Price, Reserve & Auction Started / Ended / SOLD State -->
                                            <div class="xl:col-span-8 border-t xl:border-t-0 xl:border-l border-hairline pt-4 xl:pt-0 xl:pl-6">
                                                <form action="/admin/artworks/control" method="POST" class="space-y-3 text-xs">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="artwork_id" value="<?= (int)$work['id'] ?>">

                                                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                                                        <div>
                                                            <label class="block text-[10px] uppercase tracking-widest text-bronze font-semibold mb-1">Agreed Price ($)</label>
                                                            <input type="number" step="50" name="agreed_price" value="<?= $agreedPrice ?>" class="w-full bg-ivory border border-bronze px-2.5 py-2 font-semibold text-obsidian">
                                                        </div>

                                                        <div>
                                                            <label class="block text-[10px] uppercase tracking-widest text-stone mb-1">Starting Bid ($)</label>
                                                            <input type="number" step="50" name="starting_bid" value="<?= $startingBid ?>" class="w-full bg-ivory border border-hairline px-2.5 py-2 font-semibold">
                                                        </div>

                                                        <div class="bg-[#FDFBF7] border border-bronze p-1.5 -m-1.5">
                                                            <label class="block text-[10px] uppercase tracking-widest text-bronze font-bold mb-1">🔔 Bid Update Price ($)</label>
                                                            <input type="number" step="50" name="current_bid" value="<?= $currentBid ?>" class="w-full bg-white border-2 border-obsidian px-2.5 py-1.5 font-serif text-base font-bold text-obsidian">
                                                        </div>

                                                        <div>
                                                            <label class="block text-[10px] uppercase tracking-widest text-stone mb-1">Reserve ($)</label>
                                                            <input type="number" step="50" name="reserve_price" value="<?= $reservePrice ?>" class="w-full bg-ivory border border-hairline px-2.5 py-2">
                                                        </div>

                                                        <div>
                                                            <label class="block text-[10px] uppercase tracking-widest text-obsidian font-semibold mb-1">Status Changer</label>
                                                            <select name="auction_state" class="w-full bg-ivory border border-obsidian px-2 py-2 font-semibold text-xs">
                                                                <option value="unscheduled" <?= $aucState === 'unscheduled' ? 'selected' : '' ?>>Pending Review</option>
                                                                <option value="upcoming" <?= $aucState === 'upcoming' ? 'selected' : '' ?>>Upcoming (Not Started)</option>
                                                                <option value="active" <?= $aucState === 'active' ? 'selected' : '' ?>>● Auction Started (Live)</option>
                                                                <option value="closed_sold" <?= $aucState === 'closed_sold' ? 'selected' : '' ?>>✓ SOLD (Auction Ended)</option>
                                                                <option value="closed_unsold" <?= $aucState === 'closed_unsold' ? 'selected' : '' ?>>✕ Ended — Unsold</option>
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-2.5 pt-2">
                                                        <input type="text" name="admin_feedback" value="<?= e($sub['admin_feedback'] ?? '') ?>" placeholder="Note to artist regarding bid alert, agreed price & schedule..." class="flex-1 bg-ivory border border-hairline px-3 py-2 text-xs">
                                                        <button type="submit" name="bid_action" value="update_bid_alert" class="bg-bronze text-white px-4 py-2.5 text-[10px] uppercase tracking-widest font-semibold hover:bg-bronzeDark transition whitespace-nowrap">
                                                            🔔 Update Bid Price &amp; Alert Artist
                                                        </button>
                                                        <button type="submit" name="bid_action" value="standard" class="bg-obsidian text-ivory px-4 py-2.5 text-[10px] uppercase tracking-widest font-semibold hover:bg-bronze transition whitespace-nowrap">
                                                            Save All &amp; Status
                                                        </button>
                                                    </div>

                                                    <!-- Auction & Audit Desk Two-Way Thread (Artist ↔ Backend Response) -->
                                                    <div class="bg-ivory border border-bronze/50 p-3.5 mt-3 space-y-2.5">
                                                        <div class="flex items-center justify-between border-b border-hairline pb-1.5">
                                                            <span class="text-[10px] uppercase tracking-widest font-bold text-bronze">
                                                                💬 Auction &amp; Audit Desk Thread (Artist ↔ Backend)
                                                            </span>
                                                            <span class="text-[10px] text-stone">
                                                                <?= count($negotiationMessages) ?> message(s)
                                                            </span>
                                                        </div>

                                                        <?php if (empty($negotiationMessages)): ?>
                                                            <p class="text-[11px] text-stone italic">No messages in the Auction &amp; Audit Desk thread yet. You can send a message or valuation response to the artist below.</p>
                                                        <?php else: ?>
                                                            <div class="space-y-1.5 max-h-40 overflow-y-auto pr-1">
                                                                <?php foreach ($negotiationMessages as $nm): ?>
                                                                    <?php $fromArtist = ($nm['sender'] ?? '') === 'artist'; ?>
                                                                    <div class="p-2 text-[11px] border <?= $fromArtist ? 'bg-white border-obsidian/40' : 'bg-[#FDFBF7] border-bronze/40' ?>">
                                                                        <div class="flex items-center justify-between">
                                                                            <strong class="<?= $fromArtist ? 'text-obsidian' : 'text-bronze' ?>">
                                                                                <?= $fromArtist ? '👤 Artist (' . e($nm['sender_name'] ?? 'Artist') . ')' : '🏛 ' . e($nm['sender_name'] ?? 'Auction & Audit Desk') ?>:
                                                                            </strong>
                                                                            <span class="text-[9px] text-stone"><?= e($nm['created_at'] ?? '') ?> UTC</span>
                                                                        </div>
                                                                        <p class="text-charcoal mt-0.5"><?= e($nm['text'] ?? $nm['message'] ?? '') ?></p>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php endif; ?>

                                                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 pt-1">
                                                            <input
                                                                type="text"
                                                                name="desk_reply"
                                                                placeholder="Type a direct response from the Backend to the Artist's Auction & Audit Desk..."
                                                                class="flex-1 bg-white border border-obsidian px-3 py-2 text-xs"
                                                            >
                                                            <button
                                                                type="submit"
                                                                name="bid_action"
                                                                value="send_desk_reply"
                                                                class="bg-[#1A2E22] text-ivory px-4 py-2 text-[10px] uppercase tracking-widest font-semibold hover:bg-bronze transition whitespace-nowrap"
                                                            >
                                                                💬 Send Desk Reply to Artist
                                                            </button>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- SECTION 2: Live Bid Price Increaser & Instant Status Changer to SOLD -->
    <section id="auctions" class="bg-white border border-hairline p-8 space-y-6">
        <div class="border-b border-hairline pb-4 flex flex-col sm:flex-row sm:items-end justify-between gap-4">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-bronze font-semibold">Live Bidding &amp; Sold Status Controller</p>
                <h2 class="font-serif text-3xl text-obsidian">Increase Live Bid Prices &amp; Change Status to SOLD</h2>
            </div>
            <span class="text-xs text-stone">Increase the live bid price by one increment (+1 Step), set any custom bid amount, or change status directly to <strong>SOLD</strong>.</span>
        </div>

        <?php if (empty($auctions)): ?>
            <p class="text-xs text-stone py-6">No real artist auctions have been started yet. Approve an artist's uploaded work above to launch its live auction.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-hairline text-[10px] uppercase tracking-widest text-stone">
                            <th class="py-3 pr-4">Lot &amp; Artwork</th>
                            <th class="py-3 pr-4">Artist</th>
                            <th class="py-3 pr-4">Agreed Price</th>
                            <th class="py-3 pr-4">Current Live Bid</th>
                            <th class="py-3 pr-4">Current Status</th>
                            <th class="py-3 text-right">Increase Live Bid / Change Status to SOLD</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-hairline">
                        <?php foreach ($auctions as $auc): ?>
                            <?php
                            $curBid = (float)($auc['current_bid'] ?? 0);
                            $nextStep = $curBid + calculate_bid_increment($curBid);
                            ?>
                            <tr>
                                <td class="py-4 pr-4">
                                    <span class="font-mono text-[10px] font-semibold text-bronze block"><?= e($auc['lot_number']) ?></span>
                                    <a href="/artworks/<?= e($auc['artwork']['slug'] ?? '') ?>" target="_blank" class="font-serif text-lg text-obsidian hover:text-bronze">
                                        <?= e($auc['artwork']['title'] ?? '') ?>
                                    </a>
                                </td>
                                <td class="py-4 pr-4"><?= e($auc['artist']['name'] ?? '') ?></td>
                                <td class="py-4 pr-4 font-semibold text-bronze"><?= format_money($auc['agreed_price'] ?? $auc['reserve_price'] ?? 0) ?></td>
                                <td class="py-4 pr-4">
                                    <span class="font-serif text-2xl font-semibold text-obsidian"><?= format_money($curBid) ?></span>
                                    <span class="block text-[10px] text-stone"><?= (int)$auc['bid_count'] ?> bids</span>
                                </td>
                                <td class="py-4 pr-4">
                                    <span class="px-2.5 py-1 text-[10px] uppercase tracking-widest font-semibold <?= $auc['status'] === 'closed_sold' ? 'bg-obsidian text-bronzeLight' : ($auc['status'] === 'active' ? 'bg-[#1A2E22] text-ivory' : 'bg-alabaster text-charcoal border border-hairline') ?>">
                                        <?= $auc['status'] === 'closed_sold' ? '✓ SOLD' : e(strtoupper($auc['status'])) ?>
                                    </span>
                                </td>
                                <td class="py-4 text-right">
                                    <form action="/admin/auctions/live-control" method="POST" class="flex flex-wrap items-center justify-end gap-2">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="auction_id" value="<?= (int)$auc['id'] ?>">

                                        <button type="submit" name="action_type" value="increase_bid" class="bg-ivory border border-obsidian text-obsidian px-3 py-2 text-[10px] uppercase tracking-widest font-semibold hover:bg-obsidian hover:text-ivory transition" title="Increase by next increment">
                                            + Increase Bid to <?= format_money($nextStep) ?>
                                        </button>

                                        <input type="number" step="50" name="custom_bid_amount" value="<?= $curBid ?>" class="w-28 bg-ivory border border-hairline px-2.5 py-2 text-xs font-semibold">

                                        <button type="submit" name="action_type" value="set_custom_bid" class="bg-obsidian text-ivory px-3 py-2 text-[10px] uppercase tracking-widest hover:bg-bronze transition">
                                            Set Bid
                                        </button>

                                        <?php if ($auc['status'] !== 'closed_sold'): ?>
                                            <button type="submit" name="action_type" value="mark_sold" class="bg-[#1A2E22] text-ivory px-4 py-2 text-[10px] uppercase tracking-widest font-semibold hover:bg-bronze transition">
                                                ✓ Change Status to SOLD
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" name="action_type" value="mark_active" class="border border-hairline bg-alabaster text-charcoal px-3 py-2 text-[10px] uppercase tracking-widest hover:border-obsidian transition">
                                                Reopen Live
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <!-- SECTION 3: Curatorial Artwork Review & Approval Queue (Real Submissions Only) -->
    <section id="submissions" class="bg-white border border-hairline p-8 space-y-6">
        <div class="border-b border-hairline pb-4 flex items-center justify-between">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-bronze font-semibold">Specialist Review Queue</p>
                <h2 class="font-serif text-3xl text-obsidian">Real Artwork Consignment Submissions</h2>
            </div>
            <span class="text-xs text-stone">Set the Agreed Price &amp; Controlled Starting Bid when approving any artist upload.</span>
        </div>

        <?php if (empty($submissions)): ?>
            <p class="text-xs text-stone py-6">No real artwork submissions are currently in the queue.</p>
        <?php else: ?>
            <div class="divide-y divide-hairline">
                <?php foreach ($submissions as $sub): ?>
                    <?php
                    $art = $sub['artwork'] ?? [];
                    $artist = $sub['artist'] ?? [];
                    $expectedPrice = (float)($sub['artist_expected_price'] ?? $art['artist_expected_price'] ?? $art['estimate_high'] ?? $sub['reserve_price'] ?? 0);
                    $agreedPrice = (float)($sub['agreed_price'] ?? $art['agreed_price'] ?? $expectedPrice);
                    ?>
                    <div class="py-6 grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                        <div class="lg:col-span-2 aspect-[4/3] bg-alabaster overflow-hidden border border-hairline">
                            <img src="<?= e($art['primary_image'] ?? '') ?>" alt="" class="w-full h-full object-cover">
                        </div>

                        <div class="lg:col-span-5 space-y-1.5 text-xs">
                            <div class="flex items-center space-x-2">
                                <span class="font-mono font-semibold text-bronze"><?= e($sub['reference_code']) ?></span>
                                <span class="px-2 py-0.5 text-[10px] uppercase tracking-widest bg-alabaster border border-hairline font-semibold">
                                    <?= e(strtoupper($sub['status'])) ?>
                                </span>
                            </div>
                            <h3 class="font-serif text-2xl text-obsidian"><?= e($art['title'] ?? 'Untitled') ?></h3>
                            <p class="text-charcoal font-medium">Artist: <?= e($artist['name'] ?? 'Artist') ?> (<?= e($artist['email'] ?? '') ?>)</p>
                            <p class="text-stone"><?= e($art['medium'] ?? '') ?> · <?= e($art['dimensions'] ?? '') ?></p>
                            <div class="grid grid-cols-3 gap-2 bg-ivory border border-hairline p-2.5 mt-2">
                                <div>
                                    <span class="block text-[9px] uppercase text-stone">Artist Expected</span>
                                    <strong class="text-obsidian"><?= format_money($expectedPrice) ?></strong>
                                </div>
                                <div>
                                    <span class="block text-[9px] uppercase text-stone">Suggested Start</span>
                                    <strong class="text-obsidian"><?= format_money($sub['suggested_starting_bid'] ?? 0) ?></strong>
                                </div>
                                <div>
                                    <span class="block text-[9px] uppercase text-stone">Agreed Price</span>
                                    <strong class="text-bronze"><?= format_money($agreedPrice) ?></strong>
                                </div>
                            </div>
                        </div>

                        <div class="lg:col-span-5 bg-ivory border border-hairline p-5">
                            <form action="/admin/submissions/review" method="POST" class="space-y-3 text-xs">
                                <?= csrf_field() ?>
                                <input type="hidden" name="submission_id" value="<?= (int)$sub['id'] ?>">

                                <div class="grid grid-cols-3 gap-2">
                                    <div>
                                        <label class="block text-[10px] uppercase tracking-widest text-bronze font-semibold mb-1">Agreed Price ($)</label>
                                        <input type="number" step="50" name="agreed_price" value="<?= $agreedPrice ?>" class="w-full bg-white border border-bronze px-2.5 py-1.5 text-xs font-semibold">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] uppercase tracking-widest text-stone mb-1">Starting Bid ($)</label>
                                        <input type="number" step="50" name="starting_bid" value="<?= (float)($sub['suggested_starting_bid'] ?? 1000) ?>" class="w-full bg-white border border-hairline px-2.5 py-1.5 text-xs font-semibold">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] uppercase tracking-widest text-stone mb-1">Reserve ($)</label>
                                        <input type="number" step="50" name="reserve_price" value="<?= (float)($sub['reserve_price'] ?? $agreedPrice) ?>" class="w-full bg-white border border-hairline px-2.5 py-1.5 text-xs">
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-[10px] uppercase tracking-widest text-stone mb-1">Assign Sale Title</label>
                                        <input type="text" name="auction_title" value="Evening Sale: Contemporary Curated" class="w-full bg-white border border-hairline px-2.5 py-1.5 text-xs">
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <label class="block text-[10px] uppercase tracking-widest text-stone mb-1">Lot #</label>
                                            <input type="text" name="lot_number" value="LOT 0<?= (int)$sub['id'] ?>" class="w-full bg-white border border-hairline px-2.5 py-1.5 text-xs">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] uppercase tracking-widest text-stone mb-1">Days</label>
                                            <input type="number" name="duration_days" value="<?= (int)($sub['preferred_duration_days'] ?? 7) ?>" class="w-full bg-white border border-hairline px-2.5 py-1.5 text-xs">
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-[10px] uppercase tracking-widest text-stone mb-1">Curatorial Feedback to Artist</label>
                                    <input type="text" name="admin_feedback" value="<?= e($sub['admin_feedback'] ?? '') ?>" placeholder="Specialist feedback or agreed price note..." class="w-full bg-white border border-hairline px-3 py-2 text-xs">
                                </div>

                                <div class="flex flex-wrap gap-2 pt-1">
                                    <button type="submit" name="decision" value="approve_publish" class="bg-[#1A2E22] text-ivory px-4 py-2 text-[10px] uppercase tracking-widest font-semibold hover:bg-bronze transition">
                                        ✓ Approve, Set Price &amp; Start Auction
                                    </button>
                                    <button type="submit" name="decision" value="request_changes" class="border border-obsidian px-3.5 py-2 text-[10px] uppercase tracking-widest hover:bg-obsidian hover:text-ivory transition">
                                        Propose Price / Changes
                                    </button>
                                    <button type="submit" name="decision" value="reject" class="border border-red-800 text-red-800 px-3.5 py-2 text-[10px] uppercase tracking-widest hover:bg-red-800 hover:text-white transition">
                                        Reject
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- SECTION 4: Orders, Commissions & Seller Payout Approvals -->
    <section id="payouts" class="bg-white border border-hairline p-8 space-y-6">
        <div class="border-b border-hairline pb-4">
            <p class="text-[10px] uppercase tracking-widest text-bronze font-semibold">Financial Operations</p>
            <h2 class="font-serif text-3xl text-obsidian">Real Seller Commissions &amp; Artist Payout Release</h2>
        </div>

        <?php if (empty($payouts)): ?>
            <p class="text-xs text-stone py-4">No real artist payouts are pending yet.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-hairline text-[10px] uppercase tracking-widest text-stone">
                            <th class="py-3 pr-4">Reference</th>
                            <th class="py-3 pr-4">Artist</th>
                            <th class="py-3 pr-4">Gross Hammer</th>
                            <th class="py-3 pr-4">House Commission</th>
                            <th class="py-3 pr-4">Net Artist Payout</th>
                            <th class="py-3 pr-4">Status</th>
                            <th class="py-3 text-right">Authorization</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-hairline">
                        <?php foreach ($payouts as $po): ?>
                            <tr>
                                <td class="py-4 pr-4 font-mono font-semibold"><?= e($po['payout_reference']) ?></td>
                                <td class="py-4 pr-4"><?= e($po['artist']['name'] ?? 'Artist') ?></td>
                                <td class="py-4 pr-4"><?= format_money_exact($po['gross_sale_amount']) ?></td>
                                <td class="py-4 pr-4 text-stone"><?= format_money_exact($po['commission_deducted']) ?></td>
                                <td class="py-4 pr-4 font-serif text-lg font-semibold text-bronze"><?= format_money_exact($po['net_payout_amount']) ?></td>
                                <td class="py-4 pr-4">
                                    <span class="px-2 py-0.5 text-[10px] uppercase tracking-widest font-semibold <?= ($po['status'] ?? '') === 'completed' ? 'bg-[#1A2E22] text-ivory' : 'bg-alabaster text-obsidian border border-hairline' ?>">
                                        <?= e(strtoupper($po['status'])) ?>
                                    </span>
                                </td>
                                <td class="py-4 text-right">
                                    <?php if (($po['status'] ?? '') !== 'completed'): ?>
                                        <form action="/admin/payouts/approve" method="POST" class="inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="payout_id" value="<?= (int)$po['id'] ?>">
                                            <button type="submit" class="bg-bronze text-white px-4 py-2 text-[10px] uppercase tracking-widest hover:bg-bronzeDark transition">
                                                Authorize Wire Payout
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-stone text-[11px]">Disbursed <?= e($po['paid_at'] ?? '') ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <!-- SECTION 5: Platform Settings, Categories & Real User Management -->
    <div id="settings" class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <!-- Platform Settings -->
        <div class="lg:col-span-6 bg-white border border-hairline p-8 space-y-5">
            <h2 class="font-serif text-2xl text-obsidian border-b border-hairline pb-3">Institutional Settings &amp; Commission Policy</h2>
            <form action="/admin/settings" method="POST" class="space-y-4 text-xs">
                <?= csrf_field() ?>
                <div>
                    <label class="block uppercase tracking-widest text-stone mb-1">Brand Title</label>
                    <input type="text" name="brand_name" value="<?= e($settings['brand_name'] ?? 'ArtAlly') ?>" class="w-full bg-ivory border border-hairline px-3.5 py-2.5">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block uppercase tracking-widest text-stone mb-1">Seller Commission (%)</label>
                        <input type="number" step="0.5" name="commission_rate_percent" value="<?= (float)($settings['commission_rate_percent'] ?? 15.0) ?>" class="w-full bg-ivory border border-hairline px-3.5 py-2.5">
                    </div>
                    <div>
                        <label class="block uppercase tracking-widest text-stone mb-1">Buyer’s Premium (%)</label>
                        <input type="number" step="0.5" name="buyer_premium_percent" value="<?= (float)($settings['buyer_premium_percent'] ?? 20.0) ?>" class="w-full bg-ivory border border-hairline px-3.5 py-2.5">
                    </div>
                </div>
                <div class="bg-ivory border border-bronze p-3.5 space-y-1.5">
                    <label class="block text-[10px] uppercase tracking-widest text-bronze font-semibold">🔒 Buyer Path Registration Verification Code</label>
                    <input type="text" name="buyer_verification_code" value="<?= e($settings['buyer_verification_code'] ?? 'NOIR-BUYER-2026') ?>" required class="w-full bg-white border border-obsidian px-3.5 py-2 font-mono font-bold text-obsidian">
                    <p class="text-[11px] text-stone">Artists register freely on <code>/register</code>. Buyers must enter this Verification Code to access the Collector/Buyer registration path.</p>
                </div>
                <label class="flex items-center space-x-2 pt-2">
                    <input type="checkbox" name="anti_sniping_enabled" value="1" <?= !empty($settings['anti_sniping_enabled']) ? 'checked' : '' ?> class="accent-obsidian">
                    <span>Enable 5-Minute Anti-Sniping Automatic Auction Extension</span>
                </label>
                <button type="submit" class="bg-obsidian text-ivory px-6 py-3 text-xs uppercase tracking-widest hover:bg-bronze transition">
                    Save Institutional Configuration
                </button>
            </form>

            <!-- Create Department Category -->
            <div id="categories" class="pt-6 border-t border-hairline">
                <h3 class="font-serif text-xl text-obsidian mb-3">Add Curatorial Department Category (Current: <?= count($categories) ?>)</h3>
                <form action="/admin/categories" method="POST" class="flex flex-col sm:flex-row gap-2 text-xs">
                    <?= csrf_field() ?>
                    <input type="text" name="name" required placeholder="Department Name..." class="flex-1 bg-ivory border border-hairline px-3 py-2.5">
                    <input type="text" name="description" required placeholder="Short curatorial description..." class="flex-1 bg-ivory border border-hairline px-3 py-2.5">
                    <button type="submit" class="bg-obsidian text-ivory px-5 py-2.5 uppercase tracking-widest text-[10px]">Add</button>
                </form>
            </div>
        </div>

        <!-- Real Registered User Directory -->
        <div id="users" class="lg:col-span-6 bg-white border border-hairline p-8 space-y-5">
            <h2 class="font-serif text-2xl text-obsidian border-b border-hairline pb-3">Real Registered Accounts &amp; Access Control</h2>
            <div class="divide-y divide-hairline max-h-96 overflow-y-auto text-xs">
                <?php foreach ($users as $u): ?>
                    <div class="py-3 flex items-center justify-between">
                        <div>
                            <p class="font-semibold text-obsidian"><?= e($u['name']) ?> <span class="text-bronze uppercase text-[10px] ml-1">[<?= e($u['role']) ?>]</span></p>
                            <p class="text-stone text-[11px]"><?= e($u['email']) ?> · <?= e($u['country'] ?? '') ?></p>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="px-2 py-0.5 text-[10px] uppercase <?= ($u['status'] ?? 'active') === 'active' ? 'bg-ivory text-obsidian border border-hairline' : 'bg-red-100 text-red-800' ?>">
                                <?= e($u['status'] ?? 'active') ?>
                            </span>
                            <?php if ((int)$u['id'] !== (int)$admin['id']): ?>
                                <form action="/admin/users/toggle-status" method="POST">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                    <button type="submit" class="text-[10px] uppercase tracking-wider underline text-stone hover:text-obsidian">
                                        <?= ($u['status'] ?? 'active') === 'active' ? 'Suspend' : 'Activate' ?>
                                    </button>
                                </form>
                                <form action="/admin/users/delete" method="POST" onsubmit="return confirm('Permanently delete this user account and all associated artist/artwork records?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                    <button type="submit" class="text-[10px] uppercase tracking-wider underline text-red-800 hover:text-obsidian ml-2">
                                        Delete
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- SECTION 6: Administrator Profile Management & Option to Delete Admin Profile -->
    <section id="admin-profile" class="bg-white border border-hairline p-8 space-y-6">
        <div class="border-b border-hairline pb-4 flex flex-col sm:flex-row sm:items-end justify-between gap-4">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-bronze font-semibold">Executive Credential Governance</p>
                <h2 class="font-serif text-3xl text-obsidian">Administrator Profile, New Admin Creation &amp; Delete Admin Option</h2>
            </div>
            <span class="text-xs text-stone">Update your admin credentials, add another administrator, or permanently delete your admin profile.</span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 text-xs">
            <!-- 1. Update Current Admin Profile -->
            <div class="bg-ivory border border-hairline p-6 space-y-4">
                <h3 class="font-serif text-xl text-obsidian border-b border-hairline pb-2">Update Current Admin Profile</h3>
                <form action="/admin/profile/manage" method="POST" class="space-y-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action_type" value="update_profile">
                    <div>
                        <label class="block text-[10px] uppercase tracking-widest text-stone mb-1">Admin Display Name</label>
                        <input type="text" name="name" required value="<?= e($admin['name']) ?>" class="w-full bg-white border border-hairline px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase tracking-widest text-stone mb-1">Admin Email Address</label>
                        <input type="email" name="email" required value="<?= e($admin['email']) ?>" class="w-full bg-white border border-hairline px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase tracking-widest text-stone mb-1">New Password (Optional — min 8 chars)</label>
                        <input type="password" name="new_password" placeholder="Leave blank to keep current password" class="w-full bg-white border border-hairline px-3 py-2">
                    </div>
                    <button type="submit" class="w-full bg-obsidian text-ivory py-2.5 text-[10px] uppercase tracking-widest font-semibold hover:bg-bronze transition">
                        Save Admin Profile Changes
                    </button>
                </form>
            </div>

            <!-- 2. Create Additional Admin Account -->
            <div class="bg-ivory border border-hairline p-6 space-y-4">
                <h3 class="font-serif text-xl text-obsidian border-b border-hairline pb-2">Create Additional Admin Profile</h3>
                <form action="/admin/profile/manage" method="POST" class="space-y-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action_type" value="create_new_admin">
                    <div>
                        <label class="block text-[10px] uppercase tracking-widest text-stone mb-1">New Admin Full Name</label>
                        <input type="text" name="new_admin_name" required placeholder="e.g. Executive Specialist" class="w-full bg-white border border-hairline px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase tracking-widest text-stone mb-1">New Admin Email</label>
                        <input type="email" name="new_admin_email" required placeholder="director@ateliernoir.art" class="w-full bg-white border border-hairline px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase tracking-widest text-stone mb-1">Password (min 8 chars)</label>
                        <input type="password" name="new_admin_password" required minlength="8" placeholder="••••••••••••" class="w-full bg-white border border-hairline px-3 py-2">
                    </div>
                    <button type="submit" class="w-full border border-obsidian bg-white text-obsidian py-2.5 text-[10px] uppercase tracking-widest font-semibold hover:bg-obsidian hover:text-ivory transition">
                        + Create Admin Profile
                    </button>
                </form>
            </div>

            <!-- 3. Delete Current Admin Profile -->
            <div class="bg-[#FDF8F8] border border-red-800/40 p-6 space-y-4">
                <h3 class="font-serif text-xl text-red-900 border-b border-red-800/20 pb-2">Delete Admin Profile</h3>
                <p class="text-[11px] text-charcoal leading-relaxed">
                    Permanently delete your current administrator profile (<strong><?= e($admin['email']) ?></strong>). If no other admin profiles remain, visiting <code>/admin/login</code> will let you set up a brand-new Master Admin profile immediately.
                </p>
                <form action="/admin/profile/manage" method="POST" class="space-y-3" onsubmit="return confirm('Are you sure you want to permanently delete your administrator profile?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action_type" value="delete_admin_profile">
                    <div>
                        <label class="block text-[10px] uppercase tracking-widest text-red-900 font-semibold mb-1">Type DELETE to Confirm</label>
                        <input type="text" name="confirm_delete" required placeholder="DELETE" class="w-full bg-white border border-red-800 px-3 py-2 font-mono uppercase">
                    </div>
                    <button type="submit" class="w-full bg-red-800 text-white py-2.5 text-[10px] uppercase tracking-widest font-semibold hover:bg-obsidian transition">
                        ✕ Permanently Delete Admin Profile
                    </button>
                </form>
            </div>
        </div>
    </section>

    <!-- SECTION 7: Immutable Security & Curatorial Audit Log -->
    <section id="audit" class="bg-white border border-hairline p-8 space-y-4">
        <div class="border-b border-hairline pb-3 flex items-center justify-between">
            <h2 class="font-serif text-2xl text-obsidian">Immutable Governance Audit Log</h2>
            <span class="text-xs text-stone">Newsletter Subscribers: <?= count($subscribers) ?> · Compliance Reports: <?= count($reports) ?></span>
        </div>
        <div class="divide-y divide-hairline text-xs max-h-72 overflow-y-auto">
            <?php foreach ($auditLogs as $log): ?>
                <div class="py-2.5 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div>
                        <span class="font-mono text-[11px] font-semibold text-bronze">[<?= e($log['action']) ?>]</span>
                        <span class="text-obsidian font-medium ml-2"><?= e($log['details']) ?></span>
                    </div>
                    <div class="text-[11px] text-stone">
                        <?= e($log['actor_name']) ?> · <?= e($log['created_at']) ?> UTC
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</div>
