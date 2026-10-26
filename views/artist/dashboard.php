<div class="max-w-[1440px] mx-auto px-4 sm:px-8 py-12">
    <!-- Top Studio Header -->
    <div class="border-b border-hairline pb-8 mb-10 flex flex-col lg:flex-row lg:items-end justify-between gap-6">
        <div class="flex items-center space-x-5">
            <img src="<?= e($profile['profile_photo']) ?>" alt="<?= e($profile['display_name']) ?>" class="w-20 h-20 object-cover border border-hairline">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-[11px] uppercase tracking-widest text-bronze font-semibold">Artist Studio Portal</p>
                    <?php if (($profile['approval_status'] ?? 'pending') === 'approved'): ?>
                        <span class="px-2.5 py-0.5 text-[10px] uppercase tracking-widest bg-[#1A2E22] text-ivory font-semibold">✓ Profile Approved &amp; Live</span>
                    <?php else: ?>
                        <span class="px-2.5 py-0.5 text-[10px] uppercase tracking-widest bg-bronze text-white font-semibold">◷ Profile Awaiting Admin Approval</span>
                    <?php endif; ?>
                </div>
                <h1 class="font-serif text-4xl text-obsidian mt-1"><?= e($profile['display_name']) ?></h1>
                <p class="text-xs text-stone mt-1"><?= e($profile['discipline']) ?> · <?= e($profile['country']) ?> · Registered Email: <?= e($user['email']) ?></p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <a href="/artist/submit" class="bg-obsidian text-ivory px-6 py-3.5 text-xs uppercase tracking-widest font-semibold hover:bg-bronze transition">
                + Upload &amp; Submit New Artwork
            </a>
            <a href="/artist/profile" class="border border-obsidian text-obsidian px-5 py-3.5 text-xs uppercase tracking-widest hover:bg-obsidian hover:text-ivory transition">
                Edit Studio Profile
            </a>
            <form action="/logout" method="POST" class="inline">
                <?= csrf_field() ?>
                <button type="submit" class="border border-red-800/40 bg-white text-red-800 px-5 py-3.5 text-xs uppercase tracking-widest hover:bg-red-800 hover:text-white transition">
                    Sign Out
                </button>
            </form>
        </div>
    </div>

    <!-- Live Bid Alert Banner (Displayed when Admin updates Bid Price on any Artist Artwork) -->
    <?php
    $bidAlerts = array_values(array_filter($notifications, fn($n) => ($n['type'] ?? '') === 'new_bid_alert'));
    ?>
    <?php if (!empty($bidAlerts)): ?>
        <div class="bg-[#1A2E22] text-ivory border-2 border-bronze p-6 mb-10 space-y-3 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-ivory/20 pb-3">
                <div class="flex items-center space-x-2.5">
                    <span class="bg-bronze text-white text-[10px] uppercase tracking-widest px-3 py-1 font-bold">🔔 Live Bid Alert</span>
                    <h2 class="font-serif text-2xl text-ivory">New Bid Activity on Your Artwork</h2>
                </div>
            </div>
            <div class="space-y-2 text-xs">
                <?php foreach (array_slice($bidAlerts, 0, 3) as $ba): ?>
                    <div class="bg-obsidian/80 border border-bronze/50 px-4 py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <div>
                            <p class="font-semibold text-bronzeLight text-sm"><?= e($ba['title']) ?></p>
                            <p class="text-ivory/90 mt-0.5"><?= e($ba['message']) ?></p>
                        </div>
                        <?php if (!empty($ba['bid_amount'])): ?>
                            <div class="text-right whitespace-nowrap">
                                <span class="block text-[9px] uppercase tracking-widest text-stone">Current Bid Price</span>
                                <span class="font-serif text-2xl font-bold text-bronzeLight"><?= format_money((float)$ba['bid_amount']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- 8-Metric Studio KPI Grid -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-5 mb-12">
        <div class="bg-white border border-hairline p-6">
            <p class="text-[10px] uppercase tracking-widest text-stone">Total Uploaded Works</p>
            <p class="font-serif text-4xl font-semibold text-obsidian mt-1"><?= $stats['total_submissions'] ?></p>
        </div>
        <div class="bg-white border border-hairline p-6">
            <p class="text-[10px] uppercase tracking-widest text-stone">Awaiting Admin Review</p>
            <p class="font-serif text-4xl font-semibold text-bronze mt-1"><?= $stats['awaiting_review'] ?></p>
        </div>
        <div class="bg-white border border-hairline p-6">
            <p class="text-[10px] uppercase tracking-widest text-stone">Approved / Published</p>
            <p class="font-serif text-4xl font-semibold text-obsidian mt-1"><?= $stats['approved_artworks'] ?></p>
        </div>
        <div class="bg-white border border-hairline p-6">
            <p class="text-[10px] uppercase tracking-widest text-stone">Auctions Started (Live)</p>
            <p class="font-serif text-4xl font-semibold text-obsidian mt-1"><?= $stats['active_auctions'] ?></p>
        </div>
        <div class="bg-white border border-hairline p-6">
            <p class="text-[10px] uppercase tracking-widest text-stone">Sold Artworks</p>
            <p class="font-serif text-4xl font-semibold text-obsidian mt-1"><?= $stats['sold_artworks'] ?></p>
        </div>
        <div class="bg-white border border-hairline p-6">
            <p class="text-[10px] uppercase tracking-widest text-stone">Ended Unsold Lots</p>
            <p class="font-serif text-4xl font-semibold text-stone mt-1"><?= $stats['unsold_artworks'] ?></p>
        </div>
        <div class="bg-white border border-hairline p-6">
            <p class="text-[10px] uppercase tracking-widest text-stone">Gross Hammer Sales</p>
            <p class="font-serif text-3xl font-semibold text-obsidian mt-1"><?= format_money($stats['total_sales_volume']) ?></p>
        </div>
        <div class="bg-white border border-hairline p-6">
            <p class="text-[10px] uppercase tracking-widest text-stone">Disbursed / Pending Payout</p>
            <p class="font-serif text-2xl font-semibold text-bronze mt-1">
                <?= format_money($stats['completed_payouts']) ?>
                <span class="text-xs text-stone block font-sans">Pending: <?= format_money($stats['pending_payouts']) ?></span>
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
        <!-- Left 8 Columns: Uploaded Artworks, Expected vs Agreed Prices, and Live Auction Status -->
        <div class="lg:col-span-8 space-y-10">
            <div class="bg-white border border-hairline p-8">
                <div class="flex items-center justify-between border-b border-hairline pb-4 mb-6">
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-bronze font-semibold">Your Studio Catalogue &amp; Valuation Status</p>
                        <h2 class="font-serif text-2xl text-obsidian">Uploaded Works, Agreed Prices &amp; Auction Status</h2>
                    </div>
                    <a href="/artist/submit" class="bg-obsidian text-ivory px-4 py-2 text-[10px] uppercase tracking-widest hover:bg-bronze transition">+ Upload Work</a>
                </div>

                <div class="divide-y divide-hairline">
                    <?php foreach ($submissions as $sub): ?>
                        <?php
                        $art = $sub['artwork'] ?? [];
                        $auc = $sub['auction'] ?? null;
                        $aucState = $auc['status'] ?? 'unscheduled';
                        $expectedPrice = (float)($sub['artist_expected_price'] ?? $art['artist_expected_price'] ?? $art['estimate_high'] ?? $sub['reserve_price'] ?? 0);
                        $agreedPrice = $sub['agreed_price'] ?? $art['agreed_price'] ?? ($auc ? $auc['reserve_price'] : null);
                        $currentBid = (float)($auc['current_bid'] ?? $sub['current_bid'] ?? $art['current_bid'] ?? $art['starting_bid'] ?? 0);
                        $hasBidUpdate = !empty($auc['current_bid']) || !empty($sub['current_bid']) || !empty($art['current_bid']);
                        ?>
                        <div class="py-6 space-y-4">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div class="flex items-center space-x-4">
                                    <img src="<?= e($art['primary_image'] ?? '') ?>" alt="" class="w-20 h-20 object-cover bg-alabaster border border-hairline">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="font-mono text-[11px] font-semibold text-bronze"><?= e($sub['reference_code']) ?></span>
                                            <!-- Auction Started / Ended Indicator Badge -->
                                            <?php if ($aucState === 'active'): ?>
                                                <span class="text-[10px] uppercase tracking-widest px-2.5 py-0.5 bg-[#1A2E22] text-ivory font-semibold">
                                                    ● Auction Started (Live Bidding)
                                                </span>
                                            <?php elseif ($aucState === 'upcoming'): ?>
                                                <span class="text-[10px] uppercase tracking-widest px-2.5 py-0.5 bg-bronze text-white font-semibold">
                                                    ◷ Scheduled Upcoming Auction
                                                </span>
                                            <?php elseif ($aucState === 'closed_sold'): ?>
                                                <span class="text-[10px] uppercase tracking-widest px-2.5 py-0.5 bg-obsidian text-bronzeLight font-semibold">
                                                    ✓ Auction Ended — SOLD
                                                </span>
                                            <?php elseif ($aucState === 'closed_unsold'): ?>
                                                <span class="text-[10px] uppercase tracking-widest px-2.5 py-0.5 bg-alabaster text-stone border border-hairline font-semibold">
                                                    Auction Ended — Unsold
                                                </span>
                                            <?php else: ?>
                                                <span class="text-[10px] uppercase tracking-widest px-2.5 py-0.5 bg-alabaster border border-hairline font-semibold text-charcoal">
                                                    Status: <?= e(strtoupper(str_replace('_', ' ', $sub['status']))) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <h3 class="font-serif text-2xl text-obsidian mt-1"><?= e($art['title'] ?? 'Untitled') ?></h3>
                                        <p class="text-xs text-stone"><?= e($art['medium'] ?? '') ?> · <?= e($art['dimensions'] ?? '') ?></p>
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center gap-2 self-end sm:self-center">
                                    <?php if ($aucState === 'closed_sold' || ($art['status'] ?? '') === 'sold'): ?>
                                        <a href="/certificates/<?= (int)$art['id'] ?>" class="px-4 py-2 bg-[#1A2E22] text-ivory text-[11px] uppercase tracking-widest font-semibold hover:bg-bronze transition">
                                            ⎙ Official Sale Certificate
                                        </a>
                                    <?php endif; ?>
                                    <?php if (in_array($sub['status'], ['draft', 'submitted', 'changes_requested'], true)): ?>
                                        <a href="/artist/submit?edit_id=<?= (int)$sub['id'] ?>" class="px-4 py-2 border border-obsidian text-[11px] uppercase tracking-widest hover:bg-obsidian hover:text-ivory transition">
                                            Edit Upload
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Pricing & Admin Valuation Ledger for this Upload -->
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 bg-ivory border border-hairline p-4 text-xs">
                                <div>
                                    <span class="block text-[10px] uppercase tracking-widest text-stone">Your Expected Price</span>
                                    <span class="font-serif text-lg font-semibold text-obsidian"><?= format_money($expectedPrice) ?></span>
                                </div>
                                <div>
                                    <span class="block text-[10px] uppercase tracking-widest text-stone">Agreed House Price</span>
                                    <?php if ($agreedPrice !== null): ?>
                                        <span class="font-serif text-lg font-semibold text-bronze"><?= format_money((float)$agreedPrice) ?></span>
                                        <?php if (($sub['price_agreement_status'] ?? '') === 'accepted_by_artist'): ?>
                                            <span class="block text-[10px] text-green-800 font-semibold">✓ Accepted by You</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-stone italic">Pending Curatorial Agreement</span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <span class="block text-[10px] uppercase tracking-widest text-stone">Controlled Starting Bid</span>
                                    <span class="font-serif text-lg font-semibold text-obsidian"><?= format_money($auc['starting_bid'] ?? $sub['suggested_starting_bid'] ?? 0) ?></span>
                                </div>
                                <div class="<?= $hasBidUpdate ? 'bg-[#FDFBF7] border border-bronze p-2 -m-2' : '' ?>">
                                    <span class="block text-[10px] uppercase tracking-widest <?= $hasBidUpdate ? 'text-bronze font-bold' : 'text-stone' ?>">
                                        <?= $aucState === 'closed_sold' ? 'Final Hammer Price' : ($hasBidUpdate ? '🔔 Live Bid Price' : 'Current Live Bid') ?>
                                    </span>
                                    <span class="font-serif text-xl font-bold text-obsidian">
                                        <?= format_money($currentBid) ?>
                                        <?php if ($auc): ?>
                                            <span class="font-sans text-[11px] text-stone">(<?= (int)$auc['bid_count'] ?> bids)</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Direct Price Negotiation & Message Thread with Admin -->
                            <?php $negMessages = is_array($sub['negotiation_messages'] ?? null) ? $sub['negotiation_messages'] : []; ?>
                            <div class="bg-alabaster/70 border border-hairline p-4 space-y-3 text-xs">
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] uppercase tracking-widest font-semibold text-obsidian">
                                        Curator Valuation Note &amp; Price Agreement Thread
                                    </span>
                                    <span class="text-[10px] text-stone">Ref: <?= e($sub['reference_code']) ?></span>
                                </div>
                                <p class="text-charcoal"><?= e($sub['admin_feedback'] ?? '') ?></p>

                                <?php if (!empty($negMessages)): ?>
                                    <div class="space-y-1.5 border-t border-hairline pt-2 max-h-36 overflow-y-auto">
                                        <?php foreach ($negMessages as $nm): ?>
                                            <div class="text-[11px]">
                                                <strong class="<?= ($nm['sender'] ?? '') === 'admin' ? 'text-bronze' : 'text-obsidian' ?>">
                                                    [<?= e($nm['sender_name'] ?? 'Note') ?>]:
                                                </strong>
                                                <span class="text-charcoal"><?= e($nm['text'] ?? '') ?></span>
                                                <span class="text-[10px] text-stone ml-1">(<?= e($nm['created_at'] ?? '') ?>)</span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($aucState !== 'closed_sold'): ?>
                                    <form action="/artist/submissions/negotiate" method="POST" class="pt-2 border-t border-hairline flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="submission_id" value="<?= (int)$sub['id'] ?>">

                                        <?php if ($agreedPrice !== null && ($sub['price_agreement_status'] ?? '') !== 'accepted_by_artist'): ?>
                                            <button type="submit" name="response_action" value="accept" class="bg-[#1A2E22] text-ivory px-4 py-2 text-[10px] uppercase tracking-widest font-semibold hover:bg-bronze transition whitespace-nowrap">
                                                ✓ Accept Agreed Price (<?= format_money((float)$agreedPrice) ?>)
                                            </button>
                                        <?php endif; ?>

                                        <input type="number" step="50" name="counter_price" value="<?= $expectedPrice ?>" placeholder="Counter $" class="w-full sm:w-28 bg-white border border-hairline px-2.5 py-1.5 text-xs font-semibold">
                                        <input type="text" name="artist_message" placeholder="Send a message or counter-offer note to Admin..." class="flex-1 bg-white border border-hairline px-3 py-1.5 text-xs">
                                        <button type="submit" name="response_action" value="counter" class="border border-obsidian bg-white text-obsidian px-4 py-1.5 text-[10px] uppercase tracking-widest hover:bg-obsidian hover:text-ivory transition whitespace-nowrap">
                                            Send to Admin
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Right 4 Columns: Studio Profile Preview & Notifications -->
        <div class="lg:col-span-4 space-y-8">
            <!-- How Your Artist Profile Looks Card + Instant Profile Picture Upload -->
            <div class="bg-white border border-hairline p-6 space-y-4" x-data="{
                previewPhoto: '<?= e($profile['profile_photo']) ?>',
                photoData: '',
                handleQuickPhoto(e) {
                    const file = e.target.files[0];
                    if (!file) return;
                    this.previewPhoto = URL.createObjectURL(file);
                    const reader = new FileReader();
                    reader.onload = (ev) => { this.photoData = ev.target.result; };
                    reader.readAsDataURL(file);
                }
            }">
                <div class="flex items-center justify-between border-b border-hairline pb-3">
                    <h2 class="text-xs uppercase tracking-widest font-semibold text-obsidian">Your Artist Profile &amp; Portrait</h2>
                </div>
                <div class="flex items-center space-x-3">
                    <img :src="previewPhoto" src="<?= e($profile['profile_photo']) ?>" alt="" class="w-16 h-16 object-cover border border-hairline bg-alabaster">
                    <div>
                        <h3 class="font-serif text-xl text-obsidian"><?= e($profile['display_name']) ?></h3>
                        <p class="text-[11px] text-bronze uppercase tracking-widest"><?= e($profile['country']) ?> · <?= e($profile['years_active'] ?? '') ?></p>
                    </div>
                </div>

                <!-- Direct 1-Click Artist Profile Picture Upload Form -->
                <form action="/artist/profile" method="POST" enctype="multipart/form-data" class="bg-ivory border border-hairline p-3 space-y-2 text-xs">
                    <?= csrf_field() ?>
                    <input type="hidden" name="display_name" value="<?= e($profile['display_name']) ?>">
                    <input type="hidden" name="discipline" value="<?= e($profile['discipline']) ?>">
                    <input type="hidden" name="country" value="<?= e($profile['country']) ?>">
                    <input type="hidden" name="city" value="<?= e($profile['city'] ?? '') ?>">
                    <input type="hidden" name="years_active" value="<?= e($profile['years_active'] ?? '') ?>">
                    <input type="hidden" name="biography" value="<?= e($profile['biography']) ?>">
                    <input type="hidden" name="artist_statement" value="<?= e($profile['artist_statement'] ?? '') ?>">
                    <input type="hidden" name="website" value="<?= e($profile['website'] ?? '') ?>">
                    <input type="hidden" name="social_instagram" value="<?= e($profile['social_instagram'] ?? '') ?>">
                    <input type="hidden" name="profile_photo_url" value="<?= e($profile['profile_photo']) ?>">
                    <input type="hidden" name="profile_photo_data" :value="photoData">
                    <input type="hidden" name="return_to" value="/artist/dashboard">

                    <label class="block text-[10px] uppercase tracking-widest text-obsidian font-semibold">Upload New Profile Picture</label>
                    <input type="file" name="profile_photo_file" accept="image/*" @change="handleQuickPhoto($event)" required class="w-full text-[11px]">
                    <button type="submit" class="w-full bg-obsidian text-ivory py-2 text-[10px] uppercase tracking-widest font-semibold hover:bg-bronze transition">
                        Save New Profile Picture
                    </button>
                </form>

                <p class="text-xs text-charcoal/80 line-clamp-3 leading-relaxed"><?= e($profile['biography']) ?></p>
                <div class="pt-1">
                    <a href="/artist/profile" class="block w-full text-center py-2.5 border border-obsidian text-[10px] uppercase tracking-widest hover:bg-obsidian hover:text-ivory transition">Full Profile Editor</a>
                </div>
            </div>

            <div class="bg-white border border-hairline p-6">
                <div class="flex items-center justify-between border-b border-hairline pb-3 mb-4">
                    <h2 class="text-xs uppercase tracking-widest font-semibold text-obsidian">Studio Notifications &amp; Bid Alerts</h2>
                    <form action="/buyer/notifications/read" method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="notification_id" value="-1">
                        <button type="submit" class="text-[10px] uppercase tracking-wider text-bronze hover:underline">Mark All Read</button>
                    </form>
                </div>

                <?php if (empty($notifications)): ?>
                    <p class="text-xs text-stone py-4">No studio notifications yet.</p>
                <?php else: ?>
                    <div class="divide-y divide-hairline">
                        <?php foreach ($notifications as $n): ?>
                            <?php $isBidAlert = ($n['type'] ?? '') === 'new_bid_alert'; ?>
                            <div class="py-3.5 space-y-1.5 <?= $isBidAlert ? 'bg-[#FDFBF7] border-l-4 border-bronze pl-3 pr-2 my-1' : (empty($n['read_at']) ? 'bg-ivory/70 -mx-2 px-2' : '') ?>">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-1.5">
                                        <?php if ($isBidAlert): ?>
                                            <span class="bg-bronze text-white text-[9px] uppercase tracking-widest px-2 py-0.5 font-bold">🔔 BID ALERT</span>
                                        <?php endif; ?>
                                        <p class="text-xs font-semibold text-obsidian"><?= e($n['title']) ?></p>
                                    </div>
                                    <?php if (empty($n['read_at'])): ?>
                                        <span class="w-2 h-2 rounded-full bg-bronze flex-shrink-0"></span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-xs text-charcoal/90 leading-relaxed"><?= e($n['message']) ?></p>
                                <?php if (!empty($n['bid_amount'])): ?>
                                    <p class="text-[11px] font-serif font-bold text-bronze">Updated Live Bid Price: <?= format_money((float)$n['bid_amount']) ?></p>
                                <?php endif; ?>
                                <?php if (!empty($n['action_url'])): ?>
                                    <a href="<?= e($n['action_url']) ?>" class="inline-block text-[10px] uppercase tracking-widest text-bronze font-semibold pt-1">View Record →</a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
