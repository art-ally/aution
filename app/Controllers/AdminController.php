<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\JsonDatabase;
use App\Services\AuctionEngineService;

class AdminController
{
    private JsonDatabase $db;

    public function __construct()
    {
        $this->db = JsonDatabase::getInstance();
    }

    public function showLogin(): void
    {
        if (Auth::check() && Auth::hasRole('admin')) {
            redirect('/admin/dashboard');
        }
        $existingAdmins = $this->db->where('users', fn($u) => ($u['role'] ?? '') === 'admin' || in_array('admin', $u['roles'] ?? [], true));
        view('admin/login', [
            'pageTitle' => 'Executive Admin Portal Login — ArtAlly',
            'noAdminsExist' => empty($existingAdmins),
        ]);
    }

    public function login(): void
    {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');

        $user = $this->db->findBy('users', 'email', $email);
        if (!$user || !password_verify($password, (string)$user['password'])) {
            flash('error', 'Invalid administrator credentials.');
            redirect('/admin/login');
        }

        $roles = $user['roles'] ?? [$user['role'] ?? 'buyer'];
        if (!in_array('admin', $roles, true) && ($user['role'] ?? '') !== 'admin') {
            flash('error', 'Access denied. This portal is restricted to authorized administrators.');
            redirect('/admin/login');
        }

        Auth::login($user);
        flash('success', 'Authenticated into ArtAlly Executive Governance Suite.');
        redirect('/admin/dashboard');
    }

    private function logAudit(array $adminUser, string $action, string $targetType, int|string $targetId, string $details): void
    {
        $this->db->insert('audit_logs', [
            'user_id' => (int)$adminUser['id'],
            'actor_name' => $adminUser['name'],
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'details' => $details,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        ]);
    }

    public function dashboard(): void
    {
        $admin = Auth::requireRole('admin');

        // Only show real registrations (non-seeded users) and their real uploaded artworks/submissions in the Admin Backend
        $allUsers = $this->db->all('users');
        $users = array_values(array_filter(
            $allUsers,
            fn($u) => empty($u['is_seeded']) || ($u['role'] ?? '') === 'admin'
        ));
        $realUserIds = array_map(fn($u) => (int)$u['id'], array_filter($allUsers, fn($u) => empty($u['is_seeded'])));

        $allArtistProfiles = $this->db->all('artist_profiles');
        $artistProfiles = array_values(array_filter(
            $allArtistProfiles,
            fn($ap) => in_array((int)($ap['user_id'] ?? 0), $realUserIds, true) && empty($ap['is_seeded'])
        ));

        $allArtworks = $this->db->all('artworks');
        $artworks = array_values(array_filter(
            $allArtworks,
            fn($a) => empty($a['is_seeded']) || in_array((int)($a['artist_id'] ?? 0), $realUserIds, true)
        ));
        $realArtworkIds = array_map(fn($a) => (int)$a['id'], $artworks);

        $allSubmissions = $this->db->all('artwork_submissions');
        $submissions = array_values(array_filter(
            $allSubmissions,
            fn($s) => in_array((int)($s['artwork_id'] ?? 0), $realArtworkIds, true) || in_array((int)($s['artist_id'] ?? 0), $realUserIds, true)
        ));

        $allAuctions = $this->db->all('auctions');
        $auctions = array_values(array_filter(
            $allAuctions,
            fn($a) => empty($a['is_seeded']) || in_array((int)($a['artwork_id'] ?? 0), $realArtworkIds, true)
        ));

        $bids = array_values(array_filter($this->db->all('bids'), fn($b) => empty($b['is_seeded'])));
        $orders = array_values(array_filter($this->db->all('orders'), fn($o) => empty($o['is_seeded'])));
        $payouts = array_values(array_filter($this->db->all('seller_payouts'), fn($p) => empty($p['is_seeded'])));
        $auditLogs = $this->db->all('audit_logs');
        $reports = $this->db->all('reports');
        $subscribers = $this->db->all('newsletter_subscribers');
        $settings = $this->db->find('platform_settings', 1) ?? [];

        // Enrich Artist Directory with full registration user record, uploaded works, gallery images, submissions & auctions
        foreach ($artistProfiles as &$ap) {
            $ap['user'] = $this->db->find('users', (int)$ap['user_id']) ?? [];
            $apWorks = $this->db->where('artworks', fn($a) => (int)$a['artist_id'] === (int)$ap['user_id']);
            foreach ($apWorks as &$w) {
                $w['submission'] = $this->db->findBy('artwork_submissions', 'artwork_id', (int)$w['id']);
                $w['auction'] = $this->db->findBy('auctions', 'artwork_id', (int)$w['id']);
                $w['images'] = $this->db->where('artwork_images', fn($img) => (int)$img['artwork_id'] === (int)$w['id']);
            }
            unset($w);
            $ap['artworks'] = $apWorks;
        }
        unset($ap);

        foreach ($submissions as &$sub) {
            $sub['artwork'] = $this->db->find('artworks', (int)$sub['artwork_id']) ?? [];
            $sub['artist'] = $this->db->find('users', (int)$sub['artist_id']) ?? [];
            $sub['artist_profile'] = $this->db->findBy('artist_profiles', 'user_id', (int)$sub['artist_id']) ?? [];
            $sub['auction'] = $this->db->findBy('auctions', 'artwork_id', (int)$sub['artwork_id']);
            $sub['images'] = $this->db->where('artwork_images', fn($img) => (int)$img['artwork_id'] === (int)$sub['artwork_id']);
        }
        unset($sub);
        usort($submissions, fn($a, $b) => ((int)$b['id']) <=> ((int)$a['id']));

        foreach ($auctions as &$auc) {
            $auc['artwork'] = $this->db->find('artworks', (int)$auc['artwork_id']) ?? [];
            $auc['artist'] = $this->db->find('users', (int)$auc['artist_id']) ?? [];
            $auc['submission'] = $this->db->findBy('artwork_submissions', 'artwork_id', (int)$auc['artwork_id']);
        }
        unset($auc);

        foreach ($orders as &$ord) {
            $ord['artwork'] = $this->db->find('artworks', (int)$ord['artwork_id']) ?? [];
            $ord['buyer'] = $this->db->find('users', (int)$ord['buyer_id']) ?? [];
            $ord['artist'] = $this->db->find('users', (int)$ord['artist_id']) ?? [];
        }
        unset($ord);

        foreach ($payouts as &$po) {
            $po['artist'] = $this->db->find('users', (int)$po['artist_id']) ?? [];
            $po['order'] = $this->db->find('orders', (int)$po['order_id']) ?? [];
        }
        unset($po);

        usort($auditLogs, fn($a, $b) => ((int)$b['id']) <=> ((int)$a['id']));

        $stats = [
            'total_users' => count($users),
            'total_artists' => count($artistProfiles),
            'pending_submissions' => count(array_filter($submissions, fn($s) => in_array($s['status'] ?? '', ['submitted', 'under_review'], true))),
            'active_auctions' => count(array_filter($auctions, fn($a) => ($a['status'] ?? '') === 'active')),
            'total_bids' => count($bids),
            'gross_merchandise_value' => array_sum(array_map(fn($o) => (float)($o['hammer_price'] ?? 0), $orders)),
            'platform_revenue' => array_sum(array_map(
                fn($o) => (float)($o['seller_commission_amount'] ?? 0) + (float)($o['buyer_premium'] ?? 0),
                $orders
            )),
            'pending_payout_total' => array_sum(array_map(
                fn($p) => ($p['status'] ?? '') === 'pending' ? (float)($p['net_payout_amount'] ?? 0) : 0.0,
                $payouts
            )),
        ];

        view('admin/dashboard', [
            'pageTitle' => 'Curatorial & Executive Administration — ArtAlly',
            'admin' => $admin,
            'stats' => $stats,
            'users' => $users,
            'artistProfiles' => $artistProfiles,
            'submissions' => $submissions,
            'auctions' => $auctions,
            'orders' => $orders,
            'payouts' => $payouts,
            'auditLogs' => array_slice($auditLogs, 0, 25),
            'reports' => $reports,
            'subscribers' => $subscribers,
            'settings' => $settings,
            'categories' => $this->db->all('categories'),
        ]);
    }

    /**
     * Master Admin Control for any Artist Uploaded Artwork:
     * Controls Agreed Price, Starting Bid, Current Bid, Reserve Price, and Auction State (Upcoming / Started Live / Ended Sold / Ended Unsold)
     */
    public function controlArtworkAndAuction(): void
    {
        $admin = Auth::requireRole('admin');
        $artworkId = (int)($_POST['artwork_id'] ?? 0);
        $artwork = $this->db->find('artworks', $artworkId);

        if (!$artwork) {
            flash('error', 'Artwork record not found.');
            redirect('/admin/dashboard#artists');
        }

        $agreedPrice = (float)($_POST['agreed_price'] ?? ($artwork['agreed_price'] ?? $artwork['reserve_price'] ?? 5000));
        $startingBid = max(100.0, (float)($_POST['starting_bid'] ?? $artwork['starting_bid'] ?? 1000));
        $currentBid = max(100.0, (float)($_POST['current_bid'] ?? $startingBid));
        $reservePrice = max(0.0, (float)($_POST['reserve_price'] ?? $agreedPrice));
        $auctionState = trim((string)($_POST['auction_state'] ?? 'active')); // unscheduled, upcoming, active, closed_sold, closed_unsold
        $bidAction = trim((string)($_POST['bid_action'] ?? '')); // update_bid_alert, send_desk_reply, or standard update
        $auctionTitle = trim((string)($_POST['auction_title'] ?? 'Curated Timed Sale'));
        $lotNumber = trim((string)($_POST['lot_number'] ?? ('LOT ' . sprintf('%02d', $artworkId))));
        $durationDays = max(1, (int)($_POST['duration_days'] ?? 7));
        $adminFeedback = trim((string)($_POST['admin_feedback'] ?? ''));
        $deskReply = trim((string)($_POST['desk_reply'] ?? ''));

        $existingAuction = $this->db->findBy('auctions', 'artwork_id', $artworkId);
        $previousBid = (float)($existingAuction['current_bid'] ?? $artwork['current_bid'] ?? $artwork['starting_bid'] ?? 0);
        $isBidPriceUpdated = ($bidAction === 'update_bid_alert') || ($bidAction !== 'send_desk_reply' && abs($currentBid - $previousBid) >= 0.01);
        $isDeskReply = ($bidAction === 'send_desk_reply') || $deskReply !== '';

        // If the admin updates the bid price while state was still unscheduled, automatically activate the auction so the bid reflects on the Artist Page
        if ($isBidPriceUpdated && $auctionState === 'unscheduled') {
            $auctionState = 'active';
        }

        // Map auction state to artwork & submission status
        $artworkStatus = match ($auctionState) {
            'unscheduled' => 'under_review',
            'closed_sold' => 'sold',
            default => 'published',
        };

        $subStatus = match ($auctionState) {
            'unscheduled' => 'under_review',
            default => 'published',
        };

        $this->db->update('artworks', $artworkId, [
            'agreed_price' => $agreedPrice,
            'starting_bid' => $startingBid,
            'current_bid' => $currentBid,
            'reserve_price' => $reservePrice,
            'status' => $artworkStatus,
        ]);

        $sub = $this->db->findBy('artwork_submissions', 'artwork_id', $artworkId);
        if ($sub) {
            $messages = is_array($sub['negotiation_messages'] ?? null) ? $sub['negotiation_messages'] : [];
            if ($isDeskReply && $deskReply !== '') {
                $messages[] = [
                    'sender' => 'admin',
                    'sender_name' => 'Auction & Audit Desk (' . $admin['name'] . ')',
                    'text' => $deskReply,
                    'created_at' => gmdate('Y-m-d H:i:s'),
                ];
            }
            if ($isBidPriceUpdated) {
                $messages[] = [
                    'sender' => 'admin',
                    'sender_name' => 'Auction Desk Alert',
                    'text' => "🔔 NEW BID ALERT: Live bid updated to " . format_money($currentBid) . " on \"{$artwork['title']}\"." . ($adminFeedback !== '' ? " Note: {$adminFeedback}" : ''),
                    'created_at' => gmdate('Y-m-d H:i:s'),
                ];
            } elseif ($adminFeedback !== '' && !$isDeskReply) {
                $messages[] = [
                    'sender' => 'admin',
                    'sender_name' => $admin['name'],
                    'text' => "House Agreed Price set to " . format_money($agreedPrice) . " (Starting Bid: " . format_money($startingBid) . "): {$adminFeedback}",
                    'created_at' => gmdate('Y-m-d H:i:s'),
                ];
            }
            $this->db->update('artwork_submissions', $sub['id'], [
                'agreed_price' => $agreedPrice,
                'current_bid' => $currentBid,
                'reserve_price' => $reservePrice,
                'status' => $subStatus,
                'admin_feedback' => $deskReply !== '' ? $deskReply : ($adminFeedback !== '' ? $adminFeedback : ($sub['admin_feedback'] ?? 'Valuation & live bid price updated by Administration.')),
                'negotiation_messages' => $messages,
                'reviewed_by' => (int)$admin['id'],
            ]);
        }

        if ($auctionState !== 'unscheduled') {
            $startsAt = $auctionState === 'upcoming'
                ? gmdate('Y-m-d H:i:s', strtotime('+2 days'))
                : gmdate('Y-m-d H:i:s', strtotime('-1 hour'));

            $endsAt = in_array($auctionState, ['closed_sold', 'closed_unsold'], true)
                ? gmdate('Y-m-d H:i:s', strtotime('-1 minute'))
                : gmdate('Y-m-d H:i:s', strtotime("+{$durationDays} days"));

            if ($existingAuction) {
                $newBidCount = (int)($existingAuction['bid_count'] ?? 0) + ($isBidPriceUpdated ? 1 : 0);
                $this->db->update('auctions', $existingAuction['id'], [
                    'title' => $auctionTitle,
                    'lot_number' => $lotNumber,
                    'starting_bid' => $startingBid,
                    'current_bid' => $currentBid,
                    'agreed_price' => $agreedPrice,
                    'reserve_price' => $reservePrice,
                    'reserve_met' => $currentBid >= $reservePrice,
                    'bid_count' => $newBidCount,
                    'status' => $auctionState,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                ]);
                $auctionRecord = $this->db->find('auctions', $existingAuction['id']);
            } else {
                $auctionRecord = $this->db->insert('auctions', [
                    'artwork_id' => $artworkId,
                    'artist_id' => (int)$artwork['artist_id'],
                    'title' => $auctionTitle,
                    'lot_number' => $lotNumber,
                    'currency' => $artwork['currency'] ?? 'USD',
                    'starting_bid' => $startingBid,
                    'current_bid' => $currentBid,
                    'agreed_price' => $agreedPrice,
                    'reserve_price' => $reservePrice,
                    'reserve_met' => $currentBid >= $reservePrice,
                    'estimate_low' => (float)($artwork['estimate_low'] ?? $startingBid),
                    'estimate_high' => (float)($artwork['estimate_high'] ?? ($startingBid * 1.5)),
                    'bid_count' => ($isBidPriceUpdated || $auctionState === 'closed_sold') ? 1 : 0,
                    'highest_bidder_id' => $auctionState === 'closed_sold' ? 6 : null,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'status' => $auctionState,
                    'anti_sniping_enabled' => true,
                    'featured' => true,
                    'is_seeded' => false,
                ]);
            }

            if ($isBidPriceUpdated && $auctionRecord) {
                $this->db->insert('bids', [
                    'auction_id' => (int)$auctionRecord['id'],
                    'artwork_id' => $artworkId,
                    'user_id' => (int)$admin['id'],
                    'paddle_number' => 'PB-HOUSE',
                    'amount' => $currentBid,
                    'currency' => $artwork['currency'] ?? 'USD',
                    'ip_hash' => hash('sha256', '127.0.0.1'),
                    'is_seeded' => false,
                ]);
            }

            // If Admin set state to 'closed_sold', ensure Order & Seller Payout record exist for the agreed/hammer price
            if ($auctionState === 'closed_sold' && $auctionRecord) {
                $hammerPrice = $agreedPrice > 0 ? $agreedPrice : $currentBid;
                $this->db->update('auctions', $auctionRecord['id'], [
                    'current_bid' => $hammerPrice,
                    'agreed_price' => $hammerPrice,
                    'reserve_met' => true,
                    'closed_at' => gmdate('Y-m-d H:i:s'),
                ]);

                $existingOrder = $this->db->findBy('orders', 'auction_id', (int)$auctionRecord['id']);
                if (!$existingOrder) {
                    $settings = $this->db->find('platform_settings', 1) ?? [];
                    $commissionRate = (float)($settings['commission_rate_percent'] ?? 15.0);
                    $buyerPremiumRate = (float)($settings['buyer_premium_percent'] ?? 20.0);
                    $buyerPremium = round($hammerPrice * ($buyerPremiumRate / 100), 2);
                    $shippingFee = 450.00;
                    $totalAmount = round($hammerPrice + $buyerPremium + $shippingFee, 2);
                    $sellerCommission = round($hammerPrice * ($commissionRate / 100), 2);
                    $netPayout = round($hammerPrice - $sellerCommission, 2);

                    $order = $this->db->insert('orders', [
                        'order_number' => sprintf('ORD-%s-%04d', gmdate('Y'), random_int(1000, 9999)),
                        'auction_id' => (int)$auctionRecord['id'],
                        'artwork_id' => $artworkId,
                        'buyer_id' => (int)($auctionRecord['highest_bidder_id'] ?? 6),
                        'artist_id' => (int)$artwork['artist_id'],
                        'hammer_price' => $hammerPrice,
                        'buyer_premium' => $buyerPremium,
                        'shipping_fee' => $shippingFee,
                        'total_amount' => $totalAmount,
                        'seller_commission_rate' => $commissionRate,
                        'seller_commission_amount' => $sellerCommission,
                        'net_artist_payout' => $netPayout,
                        'currency' => $artwork['currency'] ?? 'USD',
                        'payment_status' => 'pending',
                        'fulfillment_status' => 'awaiting_payment',
                        'payment_deadline' => gmdate('Y-m-d H:i:s', strtotime('+7 days')),
                    ]);

                    $this->db->insert('seller_payouts', [
                        'order_id' => (int)$order['id'],
                        'artist_id' => (int)$artwork['artist_id'],
                        'gross_sale_amount' => $hammerPrice,
                        'commission_deducted' => $sellerCommission,
                        'net_payout_amount' => $netPayout,
                        'currency' => $artwork['currency'] ?? 'USD',
                        'status' => 'pending',
                        'payout_reference' => sprintf('PO-PENDING-%04d', $order['id']),
                        'approved_by' => null,
                    ]);
                }
            }
        }

        // Notify Artist of the updated bid alert, agreed price & auction state
        $stateLabel = match ($auctionState) {
            'active' => 'LIVE & STARTED',
            'upcoming' => 'SCHEDULED UPCOMING',
            'closed_sold' => 'ENDED — SOLD',
            'closed_unsold' => 'ENDED — UNSOLD',
            default => 'UNDER CURATORIAL REVIEW',
        };

        if ($isBidPriceUpdated) {
            $this->db->insert('notifications', [
                'user_id' => (int)$artwork['artist_id'],
                'type' => 'new_bid_alert',
                'bid_amount' => $currentBid,
                'artwork_id' => $artworkId,
                'artwork_title' => $artwork['title'],
                'title' => "🔔 NEW BID ALERT: " . format_money($currentBid) . " on \"{$artwork['title']}\"",
                'message' => "ALERT: There is a new bid for " . format_money($currentBid) . " on your artwork \"{$artwork['title']}\"! This updated bid price is now displayed on your Artist Page and Studio Dashboard." . ($adminFeedback !== '' ? " Note: {$adminFeedback}" : ''),
                'action_url' => '/artist/dashboard',
                'read_at' => null,
            ]);
        } elseif ($isDeskReply && $deskReply !== '') {
            $this->db->insert('notifications', [
                'user_id' => (int)$artwork['artist_id'],
                'type' => 'desk_reply',
                'artwork_id' => $artworkId,
                'artwork_title' => $artwork['title'],
                'title' => "💬 Auction & Audit Desk Response: \"{$artwork['title']}\"",
                'message' => $deskReply,
                'action_url' => '/artist/dashboard',
                'read_at' => null,
            ]);
        } else {
            $this->db->insert('notifications', [
                'user_id' => (int)$artwork['artist_id'],
                'type' => 'admin_auction_control',
                'title' => "Auction & Valuation Updated: {$artwork['title']}",
                'message' => "Status: {$stateLabel} · Agreed Price: " . format_money($agreedPrice) . " · Current Bid: " . format_money($currentBid) . ". " . $adminFeedback,
                'action_url' => '/artist/dashboard',
                'read_at' => null,
            ]);
        }

        $this->logAudit(
            $admin,
            $isBidPriceUpdated ? 'BID_PRICE_UPDATED_AND_ALERTED' : ($isDeskReply ? 'AUCTION_DESK_REPLY_SENT' : 'ARTWORK_AUCTION_CONTROL_UPDATED'),
            'artwork',
            $artworkId,
            "Updated '{$artwork['title']}': State={$stateLabel}, AgreedPrice=\${$agreedPrice}, StartingBid=\${$startingBid}, CurrentBid=\${$currentBid}" . ($deskReply !== '' ? " | Reply: {$deskReply}" : '')
        );

        if ($isBidPriceUpdated) {
            flash('success', "🔔 BID ALERT SENT: There is now a bid for " . format_money($currentBid) . " on \"{$artwork['title']}\"! The updated bid price is live on the Artist Page and delivered as an alert to the Artist's Studio Notifications.");
        } elseif ($isDeskReply && $deskReply !== '') {
            flash('success', "💬 Auction & Audit Desk response sent to the artist on \"{$artwork['title']}\"!");
        } else {
            flash('success', "Updated \"{$artwork['title']}\" — Auction State: {$stateLabel}, Agreed Price: " . format_money($agreedPrice) . ", Current Bid: " . format_money($currentBid) . ".");
        }
        redirect('/admin/dashboard#artists');
    }

    public function reviewSubmission(): void
    {
        $admin = Auth::requireRole('admin');
        $submissionId = (int)($_POST['submission_id'] ?? 0);
        $decision = trim((string)($_POST['decision'] ?? '')); // approve_publish, request_changes, reject
        $feedback = trim((string)($_POST['admin_feedback'] ?? ''));
        $auctionTitle = trim((string)($_POST['auction_title'] ?? 'Curated Evening Sale'));
        $lotNumber = trim((string)($_POST['lot_number'] ?? ('LOT ' . sprintf('%02d', $submissionId))));
        $durationDays = max(1, (int)($_POST['duration_days'] ?? 7));

        $sub = $this->db->find('artwork_submissions', $submissionId);
        if (!$sub) {
            flash('error', 'Submission record not found.');
            redirect('/admin/dashboard');
        }

        $artwork = $this->db->find('artworks', (int)$sub['artwork_id']);
        if (!$artwork) {
            flash('error', 'Associated artwork not found.');
            redirect('/admin/dashboard');
        }

        $agreedPrice = (float)($_POST['agreed_price'] ?? ($sub['artist_expected_price'] ?? $artwork['reserve_price'] ?? $artwork['starting_bid']));
        $controlledStartingBid = max(100.0, (float)($_POST['starting_bid'] ?? $artwork['starting_bid']));
        $controlledReservePrice = max(0.0, (float)($_POST['reserve_price'] ?? $agreedPrice));

        if ($decision === 'approve_publish') {
            $this->db->update('artworks', $artwork['id'], [
                'status' => 'published',
                'agreed_price' => $agreedPrice,
                'starting_bid' => $controlledStartingBid,
                'reserve_price' => $controlledReservePrice,
                'featured' => !empty($_POST['feature_on_home']),
            ]);

            $this->db->update('artwork_submissions', $submissionId, [
                'status' => 'published',
                'agreed_price' => $agreedPrice,
                'suggested_starting_bid' => $controlledStartingBid,
                'reserve_price' => $controlledReservePrice,
                'admin_feedback' => $feedback !== '' ? $feedback : 'Approved by Curatorial Committee with agreed valuation and published to live timed auction.',
                'reviewed_by' => (int)$admin['id'],
            ]);

            // Create or update Auction schedule
            $existingAuction = $this->db->findBy('auctions', 'artwork_id', (int)$artwork['id']);
            $startsAt = gmdate('Y-m-d H:i:s', strtotime('-5 minutes'));
            $endsAt = gmdate('Y-m-d H:i:s', strtotime("+{$durationDays} days"));

            if ($existingAuction) {
                $this->db->update('auctions', $existingAuction['id'], [
                    'title' => $auctionTitle,
                    'lot_number' => $lotNumber,
                    'starting_bid' => $controlledStartingBid,
                    'current_bid' => $controlledStartingBid,
                    'agreed_price' => $agreedPrice,
                    'reserve_price' => $controlledReservePrice,
                    'status' => 'active',
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                ]);
            } else {
                $this->db->insert('auctions', [
                    'artwork_id' => (int)$artwork['id'],
                    'artist_id' => (int)$artwork['artist_id'],
                    'title' => $auctionTitle,
                    'lot_number' => $lotNumber,
                    'currency' => $artwork['currency'] ?? 'USD',
                    'starting_bid' => $controlledStartingBid,
                    'current_bid' => $controlledStartingBid,
                    'agreed_price' => $agreedPrice,
                    'reserve_price' => $controlledReservePrice,
                    'reserve_met' => false,
                    'estimate_low' => (float)$artwork['estimate_low'],
                    'estimate_high' => (float)$artwork['estimate_high'],
                    'bid_count' => 0,
                    'highest_bidder_id' => null,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'status' => 'active',
                    'anti_sniping_enabled' => true,
                    'featured' => !empty($_POST['feature_on_home']),
                    'is_seeded' => false,
                ]);
            }

            $this->db->insert('notifications', [
                'user_id' => (int)$artwork['artist_id'],
                'type' => 'artwork_approved',
                'title' => "Artwork Approved & Auction Started: {$artwork['title']}",
                'message' => "Your submission ({$sub['reference_code']}) has been approved at an Agreed Price of " . format_money($agreedPrice) . " and Starting Bid of " . format_money($controlledStartingBid) . " in \"{$auctionTitle}\".",
                'action_url' => '/artworks/' . $artwork['slug'],
                'read_at' => null,
            ]);

            $this->logAudit($admin, 'SUBMISSION_APPROVED_PUBLISHED', 'artwork_submission', $submissionId, "Approved & published '{$artwork['title']}' ({$sub['reference_code']}) with Agreed Price $" . number_format($agreedPrice) . " into {$auctionTitle}.");
            flash('success', "Submission {$sub['reference_code']} approved at Agreed Price " . format_money($agreedPrice) . " and started in live auction.");
        } elseif ($decision === 'request_changes') {
            $this->db->update('artworks', $artwork['id'], [
                'status' => 'changes_requested',
                'agreed_price' => $agreedPrice,
            ]);
            $this->db->update('artwork_submissions', $submissionId, [
                'status' => 'changes_requested',
                'agreed_price' => $agreedPrice,
                'admin_feedback' => $feedback !== '' ? $feedback : 'Please review our proposed agreed price and provide additional documentation.',
                'reviewed_by' => (int)$admin['id'],
            ]);

            $this->db->insert('notifications', [
                'user_id' => (int)$artwork['artist_id'],
                'type' => 'changes_requested',
                'title' => "Curatorial Proposal & Changes Requested: {$artwork['title']}",
                'message' => "Specialist feedback on {$sub['reference_code']} (Proposed Agreed Price: " . format_money($agreedPrice) . "): " . ($feedback !== '' ? $feedback : 'Additional documentation requested.'),
                'action_url' => '/artist/submissions',
                'read_at' => null,
            ]);

            $this->logAudit($admin, 'SUBMISSION_CHANGES_REQUESTED', 'artwork_submission', $submissionId, "Requested revisions for {$sub['reference_code']}.");
            flash('success', "Requested revisions from artist for {$sub['reference_code']}.");
        } elseif ($decision === 'reject') {
            $this->db->update('artworks', $artwork['id'], ['status' => 'rejected']);
            $this->db->update('artwork_submissions', $submissionId, [
                'status' => 'rejected',
                'admin_feedback' => $feedback !== '' ? $feedback : 'Declined for the current seasonal auction calendar.',
                'reviewed_by' => (int)$admin['id'],
            ]);

            $this->db->insert('notifications', [
                'user_id' => (int)$artwork['artist_id'],
                'type' => 'artwork_rejected',
                'title' => "Curatorial Decision: {$artwork['title']}",
                'message' => "Regarding submission {$sub['reference_code']}: " . ($feedback !== '' ? $feedback : 'We are unable to include this work in the current sale.'),
                'action_url' => '/artist/submissions',
                'read_at' => null,
            ]);

            $this->logAudit($admin, 'SUBMISSION_REJECTED', 'artwork_submission', $submissionId, "Rejected submission {$sub['reference_code']}.");
            flash('success', "Submission {$sub['reference_code']} marked as rejected.");
        }

        redirect('/admin/dashboard#submissions');
    }

    public function closeAuctionNow(): void
    {
        $admin = Auth::requireRole('admin');
        $auctionId = (int)($_POST['auction_id'] ?? 0);
        $auction = $this->db->find('auctions', $auctionId);

        if (!$auction) {
            flash('error', 'Auction not found.');
            redirect('/admin/dashboard#auctions');
        }

        $settings = $this->db->find('platform_settings', 1) ?? [];
        $commissionRate = (float)($settings['commission_rate_percent'] ?? 15.0);
        $buyerPremiumRate = (float)($settings['buyer_premium_percent'] ?? 20.0);

        $this->db->transaction(function (JsonDatabase $db) use ($auction, $commissionRate, $buyerPremiumRate) {
            AuctionEngineService::closeSingleAuction($db, $auction, $commissionRate, $buyerPremiumRate);
        });

        $this->logAudit($admin, 'AUCTION_MANUALLY_SETTLED', 'auction', $auctionId, "Settled auction #{$auctionId} ({$auction['lot_number']}) and evaluated reserve price.");
        flash('success', "Auction {$auction['lot_number']} closed and settled according to reserve rules.");
        redirect('/admin/dashboard#auctions');
    }

    public function approvePayout(): void
    {
        $admin = Auth::requireRole('admin');
        $payoutId = (int)($_POST['payout_id'] ?? 0);
        $payout = $this->db->find('seller_payouts', $payoutId);

        if (!$payout) {
            flash('error', 'Payout record not found.');
            redirect('/admin/dashboard#payouts');
        }

        $ref = 'PO-CH-' . random_int(100000, 999999);
        $this->db->update('seller_payouts', $payoutId, [
            'status' => 'completed',
            'payout_reference' => $ref,
            'approved_by' => (int)$admin['id'],
            'paid_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $this->db->insert('notifications', [
            'user_id' => (int)$payout['artist_id'],
            'type' => 'payout_completed',
            'title' => 'Seller Payout Disbursed (' . format_money_exact($payout['net_payout_amount']) . ')',
            'message' => "Your net proceeds of " . format_money_exact($payout['net_payout_amount']) . " have been released via wire transfer (Ref: {$ref}).",
            'action_url' => '/artist/sales',
            'read_at' => null,
        ]);

        $this->logAudit($admin, 'SELLER_PAYOUT_RELEASED', 'seller_payout', $payoutId, "Approved & disbursed net artist payout of $" . number_format((float)$payout['net_payout_amount'], 2) . " (Ref: {$ref}).");
        flash('success', "Seller payout #{$payoutId} marked as completed (Ref: {$ref}).");
        redirect('/admin/dashboard#payouts');
    }

    public function updateSettings(): void
    {
        $admin = Auth::requireRole('admin');
        $commissionRate = max(0.0, min(50.0, (float)($_POST['commission_rate_percent'] ?? 15.0)));
        $buyerPremium = max(0.0, min(40.0, (float)($_POST['buyer_premium_percent'] ?? 20.0)));
        $antiSniping = !empty($_POST['anti_sniping_enabled']);
        $brandName = trim((string)($_POST['brand_name'] ?? 'ArtAlly'));
        $buyerCode = trim((string)($_POST['buyer_verification_code'] ?? 'NOIR-BUYER-2026'));

        $this->db->update('platform_settings', 1, [
            'brand_name' => $brandName !== '' ? $brandName : 'ArtAlly',
            'commission_rate_percent' => $commissionRate,
            'buyer_premium_percent' => $buyerPremium,
            'anti_sniping_enabled' => $antiSniping,
            'buyer_verification_code' => $buyerCode !== '' ? $buyerCode : 'NOIR-BUYER-2026',
        ]);

        $this->logAudit($admin, 'SETTINGS_UPDATED', 'platform_settings', 1, "Updated Seller Commission to {$commissionRate}%, Buyer Premium to {$buyerPremium}%, Buyer Verification Code: {$buyerCode}");
        flash('success', 'Institutional platform settings, commission schedules, and Buyer Verification Code updated.');
        redirect('/admin/dashboard#settings');
    }

    public function createCategory(): void
    {
        $admin = Auth::requireRole('admin');
        $name = trim((string)($_POST['name'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $image = trim((string)($_POST['image'] ?? 'https://images.unsplash.com/photo-1579783902614-a3fb3927b675?auto=format&fit=crop&w=1000&q=80'));

        if ($name !== '') {
            $cat = $this->db->insert('categories', [
                'name' => $name,
                'slug' => slugify($name),
                'description' => $description,
                'image' => $image,
            ]);
            $this->logAudit($admin, 'CATEGORY_CREATED', 'category', $cat['id'], "Created department category '{$name}'.");
            flash('success', "Curatorial department \"{$name}\" created.");
        }
        redirect('/admin/dashboard#categories');
    }

    public function toggleUserStatus(): void
    {
        $admin = Auth::requireRole('admin');
        $userId = (int)($_POST['user_id'] ?? 0);
        $target = $this->db->find('users', $userId);

        if ($target && (int)$target['id'] !== (int)$admin['id']) {
            $newStatus = ($target['status'] ?? 'active') === 'active' ? 'suspended' : 'active';
            $this->db->update('users', $userId, ['status' => $newStatus]);
            $this->logAudit($admin, 'USER_STATUS_CHANGED', 'user', $userId, "Changed status of {$target['email']} to {$newStatus}.");
            flash('success', "Updated status for {$target['name']} to " . strtoupper($newStatus) . ".");
        }
        redirect('/admin/dashboard#users');
    }

    /**
     * Approve or Revoke an Artist Profile for public display on the /artists page
     */
    public function approveArtistProfile(): void
    {
        $admin = Auth::requireRole('admin');
        $profileId = (int)($_POST['artist_profile_id'] ?? 0);
        $decision = trim((string)($_POST['decision'] ?? 'approved')); // approved or pending
        $profile = $this->db->find('artist_profiles', $profileId);

        if (!$profile) {
            flash('error', 'Artist profile not found.');
            redirect('/admin/dashboard#artists');
        }

        $isApproved = $decision === 'approved';
        $this->db->update('artist_profiles', $profileId, [
            'approval_status' => $isApproved ? 'approved' : 'pending',
            'verified_artist' => $isApproved,
        ]);

        $this->db->insert('notifications', [
            'user_id' => (int)$profile['user_id'],
            'type' => 'artist_profile_approved',
            'title' => $isApproved ? 'Your Public Artist Profile is Now Approved & Live' : 'Artist Profile Set to Pending Review',
            'message' => $isApproved
                ? "Congratulations {$profile['display_name']}. Your artist registration and portfolio have been approved by the Administrator and are now publicly displayed in the Artists Directory."
                : "Your artist profile is currently marked as awaiting curatorial approval.",
            'action_url' => '/artists/' . $profile['slug'],
            'read_at' => null,
        ]);

        $this->logAudit(
            $admin,
            $isApproved ? 'ARTIST_PROFILE_APPROVED' : 'ARTIST_PROFILE_SET_PENDING',
            'artist_profile',
            $profileId,
            ($isApproved ? 'Approved' : 'Set to Pending') . " public artist profile for {$profile['display_name']}."
        );

        flash('success', $isApproved
            ? "Artist profile for \"{$profile['display_name']}\" is now APPROVED and publicly visible on the Artists page."
            : "Artist profile for \"{$profile['display_name']}\" set to Awaiting Approval.");
        redirect('/admin/dashboard#artists');
    }

    /**
     * Increase Live Bid Price or Immediately Mark Auction as SOLD from the Backend
     */
    public function adjustLiveBidAndStatus(): void
    {
        $admin = Auth::requireRole('admin');
        $auctionId = (int)($_POST['auction_id'] ?? 0);
        $actionType = trim((string)($_POST['action_type'] ?? 'increase_bid')); // increase_bid, set_custom_bid, mark_sold, mark_active
        $auction = $this->db->find('auctions', $auctionId);

        if (!$auction) {
            flash('error', 'Auction record not found.');
            redirect('/admin/dashboard#auctions');
        }

        $artwork = $this->db->find('artworks', (int)$auction['artwork_id']) ?? [];
        $currentBid = (float)($auction['current_bid'] ?? $auction['starting_bid'] ?? 0);
        $increment = calculate_bid_increment($currentBid);

        if ($actionType === 'increase_bid') {
            $newBid = $currentBid + $increment;
            $this->db->update('auctions', $auctionId, [
                'current_bid' => $newBid,
                'bid_count' => (int)($auction['bid_count'] ?? 0) + 1,
                'reserve_met' => $newBid >= (float)($auction['reserve_price'] ?? 0),
                'status' => 'active',
            ]);
            if (!empty($artwork['id'])) {
                $this->db->update('artworks', (int)$artwork['id'], [
                    'current_bid' => $newBid,
                    'status' => 'published',
                ]);
                $sub = $this->db->findBy('artwork_submissions', 'artwork_id', (int)$artwork['id']);
                if ($sub) {
                    $this->db->update('artwork_submissions', $sub['id'], ['current_bid' => $newBid]);
                }
            }
            $this->db->insert('bids', [
                'auction_id' => $auctionId,
                'artwork_id' => (int)$auction['artwork_id'],
                'user_id' => (int)$admin['id'],
                'paddle_number' => 'PB-HOUSE',
                'amount' => $newBid,
                'currency' => $auction['currency'] ?? 'USD',
                'ip_hash' => hash('sha256', '127.0.0.1'),
                'is_seeded' => false,
            ]);
            $this->db->insert('notifications', [
                'user_id' => (int)$auction['artist_id'],
                'type' => 'new_bid_alert',
                'bid_amount' => $newBid,
                'artwork_id' => (int)$auction['artwork_id'],
                'artwork_title' => $artwork['title'] ?? $auction['lot_number'],
                'title' => "🔔 NEW BID ALERT: " . format_money($newBid) . " on \"" . ($artwork['title'] ?? $auction['lot_number']) . "\"",
                'message' => "ALERT: There is a new bid for " . format_money($newBid) . " on your artwork \"" . ($artwork['title'] ?? $auction['lot_number']) . "\"! This updated bid price is now displayed on your Artist Page and Studio Dashboard.",
                'action_url' => '/artist/dashboard',
                'read_at' => null,
            ]);
            $this->logAudit($admin, 'LIVE_BID_INCREASED', 'auction', $auctionId, "Increased live bid on {$auction['lot_number']} to " . format_money($newBid) . " and sent Bid Alert to artist.");
            flash('success', "🔔 BID ALERT SENT: Increased live bid on {$auction['lot_number']} to " . format_money($newBid) . ". Alert delivered to the artist's Studio Notifications and displayed on the Artist Page.");
        } elseif ($actionType === 'set_custom_bid') {
            $customBid = max(100.0, (float)($_POST['custom_bid_amount'] ?? $currentBid));
            $this->db->update('auctions', $auctionId, [
                'current_bid' => $customBid,
                'bid_count' => (int)($auction['bid_count'] ?? 0) + 1,
                'reserve_met' => $customBid >= (float)($auction['reserve_price'] ?? 0),
                'status' => 'active',
            ]);
            if (!empty($artwork['id'])) {
                $this->db->update('artworks', (int)$artwork['id'], [
                    'current_bid' => $customBid,
                    'status' => 'published',
                ]);
                $sub = $this->db->findBy('artwork_submissions', 'artwork_id', (int)$artwork['id']);
                if ($sub) {
                    $this->db->update('artwork_submissions', $sub['id'], ['current_bid' => $customBid]);
                }
            }
            $this->db->insert('bids', [
                'auction_id' => $auctionId,
                'artwork_id' => (int)$auction['artwork_id'],
                'user_id' => (int)$admin['id'],
                'paddle_number' => 'PB-HOUSE',
                'amount' => $customBid,
                'currency' => $auction['currency'] ?? 'USD',
                'ip_hash' => hash('sha256', '127.0.0.1'),
                'is_seeded' => false,
            ]);
            $this->db->insert('notifications', [
                'user_id' => (int)$auction['artist_id'],
                'type' => 'new_bid_alert',
                'bid_amount' => $customBid,
                'artwork_id' => (int)$auction['artwork_id'],
                'artwork_title' => $artwork['title'] ?? $auction['lot_number'],
                'title' => "🔔 NEW BID ALERT: " . format_money($customBid) . " on \"" . ($artwork['title'] ?? $auction['lot_number']) . "\"",
                'message' => "ALERT: There is a new bid for " . format_money($customBid) . " on your artwork \"" . ($artwork['title'] ?? $auction['lot_number']) . "\"! This updated bid price is now displayed on your Artist Page and Studio Dashboard.",
                'action_url' => '/artist/dashboard',
                'read_at' => null,
            ]);
            $this->logAudit($admin, 'LIVE_BID_SET', 'auction', $auctionId, "Set live bid on {$auction['lot_number']} to " . format_money($customBid) . " and sent Bid Alert to artist.");
            flash('success', "🔔 BID ALERT SENT: Updated live bid on {$auction['lot_number']} to " . format_money($customBid) . ". Alert delivered to the artist's Studio Notifications and displayed on the Artist Page.");
        } elseif ($actionType === 'mark_sold') {
            $finalPrice = max($currentBid, (float)($_POST['custom_bid_amount'] ?? $currentBid));
            $this->db->update('auctions', $auctionId, [
                'current_bid' => $finalPrice,
                'agreed_price' => $finalPrice,
                'reserve_met' => true,
                'status' => 'closed_sold',
                'closed_at' => gmdate('Y-m-d H:i:s'),
            ]);
            $this->db->update('artworks', (int)$auction['artwork_id'], [
                'status' => 'sold',
                'agreed_price' => $finalPrice,
            ]);

            // Notify Artist that work is SOLD
            $this->db->insert('notifications', [
                'user_id' => (int)$auction['artist_id'],
                'type' => 'artwork_sold',
                'title' => "Artwork Marked SOLD: " . ($artwork['title'] ?? $auction['lot_number']),
                'message' => "Your artwork \"" . ($artwork['title'] ?? $auction['lot_number']) . "\" has been marked SOLD by the Auction House at a final price of " . format_money($finalPrice) . ".",
                'action_url' => '/artist/dashboard',
                'read_at' => null,
            ]);

            $this->logAudit($admin, 'AUCTION_MARKED_SOLD', 'auction', $auctionId, "Changed status of {$auction['lot_number']} to SOLD at " . format_money($finalPrice) . ".");
            flash('success', "Lot {$auction['lot_number']} status changed to SOLD at " . format_money($finalPrice) . ".");
        } elseif ($actionType === 'mark_active') {
            $this->db->update('auctions', $auctionId, [
                'status' => 'active',
                'ends_at' => gmdate('Y-m-d H:i:s', strtotime('+7 days')),
            ]);
            $this->db->update('artworks', (int)$auction['artwork_id'], [
                'status' => 'published',
            ]);
            $this->logAudit($admin, 'AUCTION_REOPENED_LIVE', 'auction', $auctionId, "Reopened {$auction['lot_number']} as Active Live Auction.");
            flash('success', "Lot {$auction['lot_number']} is now Active (Live Bidding).");
        }

        redirect('/admin/dashboard#auctions');
    }

    /**
     * Permanently Delete an Uploaded Artwork (and its Submission & Auction records)
     */
    public function deleteArtwork(): void
    {
        $admin = Auth::requireRole('admin');
        $artworkId = (int)($_POST['artwork_id'] ?? 0);
        $artwork = $this->db->find('artworks', $artworkId);

        if (!$artwork) {
            flash('error', 'Artwork not found.');
            redirect('/admin/dashboard#artists');
        }

        $title = $artwork['title'] ?? ('Artwork #' . $artworkId);

        // Delete associated submissions, auctions, images, documents, bids
        foreach ($this->db->where('artwork_submissions', fn($s) => (int)$s['artwork_id'] === $artworkId) as $sub) {
            $this->db->delete('artwork_submissions', $sub['id']);
        }
        foreach ($this->db->where('auctions', fn($a) => (int)$a['artwork_id'] === $artworkId) as $auc) {
            $this->db->delete('auctions', $auc['id']);
        }
        foreach ($this->db->where('artwork_images', fn($i) => (int)$i['artwork_id'] === $artworkId) as $img) {
            $this->db->delete('artwork_images', $img['id']);
        }
        foreach ($this->db->where('bids', fn($b) => (int)$b['artwork_id'] === $artworkId) as $bid) {
            $this->db->delete('bids', $bid['id']);
        }

        $this->db->delete('artworks', $artworkId);
        $this->logAudit($admin, 'ARTWORK_DELETED', 'artwork', $artworkId, "Permanently deleted artwork '{$title}'.");
        flash('success', "Permanently deleted artwork \"{$title}\".");
        redirect('/admin/dashboard#artists');
    }

    /**
     * Permanently Delete a Registered Artist or User Account (and all their uploaded works)
     */
    public function deleteArtistOrUser(): void
    {
        $admin = Auth::requireRole('admin');
        $userId = (int)($_POST['user_id'] ?? 0);
        $target = $this->db->find('users', $userId);

        if (!$target) {
            flash('error', 'User account not found.');
            redirect('/admin/dashboard#artists');
        }

        $name = $target['name'] ?? ('User #' . $userId);

        // Delete artist profile & artworks
        foreach ($this->db->where('artist_profiles', fn($ap) => (int)$ap['user_id'] === $userId) as $ap) {
            $this->db->delete('artist_profiles', $ap['id']);
        }
        foreach ($this->db->where('artworks', fn($a) => (int)$a['artist_id'] === $userId) as $art) {
            foreach ($this->db->where('artwork_submissions', fn($s) => (int)$s['artwork_id'] === (int)$art['id']) as $sub) {
                $this->db->delete('artwork_submissions', $sub['id']);
            }
            foreach ($this->db->where('auctions', fn($auc) => (int)$auc['artwork_id'] === (int)$art['id']) as $auc) {
                $this->db->delete('auctions', $auc['id']);
            }
            $this->db->delete('artworks', $art['id']);
        }

        $this->db->delete('users', $userId);
        $this->logAudit($admin, 'USER_DELETED', 'user', $userId, "Permanently deleted account '{$name}' ({$target['email']}).");
        flash('success', "Permanently deleted registration and uploads for \"{$name}\".");
        redirect('/admin/dashboard#artists');
    }

    /**
     * Update Admin Profile Credentials or Delete Current Admin Profile
     */
    public function manageAdminProfile(): void
    {
        $admin = Auth::requireRole('admin');
        $mode = trim((string)($_POST['admin_action_mode'] ?? 'update')); // update, create_new_admin, delete_self

        if ($mode === 'delete_self') {
            $confirmText = trim((string)($_POST['confirm_delete'] ?? ''));
            if ($confirmText !== 'DELETE') {
                flash('error', 'Please type DELETE in the confirmation box to delete this administrator profile.');
                redirect('/admin/dashboard#admin-profile');
            }

            $adminId = (int)$admin['id'];
            $this->db->delete('users', $adminId);
            Auth::logout();
            flash('success', 'Your Administrator profile has been permanently deleted. You may create a new Administrator account below.');
            redirect('/admin/login');
        }

        if ($mode === 'create_new_admin') {
            $newName = trim((string)($_POST['new_admin_name'] ?? ''));
            $newEmail = strtolower(trim((string)($_POST['new_admin_email'] ?? '')));
            $newPass = (string)($_POST['new_admin_password'] ?? '');

            if ($newName === '' || !filter_var($newEmail, FILTER_VALIDATE_EMAIL) || strlen($newPass) < 8) {
                flash('error', 'Provide a valid name, email, and password (min 8 characters) for the new Administrator.');
                redirect('/admin/dashboard#admin-profile');
            }

            if ($this->db->findBy('users', 'email', $newEmail)) {
                flash('error', 'An account with that email already exists.');
                redirect('/admin/dashboard#admin-profile');
            }

            $created = $this->db->insert('users', [
                'name' => $newName,
                'email' => $newEmail,
                'password' => password_hash($newPass, PASSWORD_DEFAULT),
                'country' => 'Switzerland',
                'role' => 'admin',
                'roles' => ['admin', 'buyer', 'artist'],
                'email_verified_at' => gmdate('Y-m-d H:i:s'),
                'bidding_approved' => true,
                'status' => 'active',
                'is_seeded' => false,
            ]);

            $this->logAudit($admin, 'NEW_ADMIN_CREATED', 'user', $created['id'], "Created new administrator profile for {$newEmail}.");
            flash('success', "Created new Administrator account for {$newName} ({$newEmail}).");
            redirect('/admin/dashboard#admin-profile');
        }

        // Update current admin profile
        $name = trim((string)($_POST['name'] ?? $admin['name']));
        $email = strtolower(trim((string)($_POST['email'] ?? $admin['email'])));
        $newPassword = (string)($_POST['new_password'] ?? '');

        $updates = [
            'name' => $name !== '' ? $name : $admin['name'],
            'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : $admin['email'],
        ];
        if ($newPassword !== '') {
            if (strlen($newPassword) < 8) {
                flash('error', 'New password must be at least 8 characters.');
                redirect('/admin/dashboard#admin-profile');
            }
            $updates['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
        }

        $this->db->update('users', $admin['id'], $updates);
        $this->logAudit($admin, 'ADMIN_PROFILE_UPDATED', 'user', $admin['id'], "Updated administrator profile credentials.");
        flash('success', 'Your Administrator profile credentials have been updated.');
        redirect('/admin/dashboard#admin-profile');
    }

    /**
     * Bootstrap a first Admin account from /admin/login if all admins were deleted
     */
    public function setupFirstAdmin(): void
    {
        $existingAdmins = $this->db->where('users', fn($u) => ($u['role'] ?? '') === 'admin' || in_array('admin', $u['roles'] ?? [], true));
        if (!empty($existingAdmins)) {
            flash('error', 'An administrator account already exists. Please sign in.');
            redirect('/admin/login');
        }

        $name = trim((string)($_POST['name'] ?? 'Chief Administrator'));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            flash('error', 'Please enter a valid name, email, and password (at least 8 characters).');
            redirect('/admin/login');
        }

        $admin = $this->db->insert('users', [
            'name' => $name,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'country' => 'Switzerland',
            'role' => 'admin',
            'roles' => ['admin', 'buyer', 'artist'],
            'email_verified_at' => gmdate('Y-m-d H:i:s'),
            'bidding_approved' => true,
            'status' => 'active',
            'is_seeded' => false,
        ]);

        Auth::login($admin);
        flash('success', 'New Master Administrator Profile created and signed in.');
        redirect('/admin/dashboard');
    }
}
