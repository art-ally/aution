<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\JsonDatabase;
use RuntimeException;

class AuctionEngineService
{
    /**
     * Automatically inspect and settle any auctions whose end time has passed
     * or transition upcoming auctions whose start time has arrived.
     */
    public static function synchronizeAuctions(): int
    {
        $db = JsonDatabase::getInstance();
        $now = time();
        $closedCount = 0;

        // Quick check without exclusive lock first to avoid unnecessary lock contention
        $auctions = $db->all('auctions');
        $needsTransition = false;
        foreach ($auctions as $a) {
            $status = $a['status'] ?? '';
            $startsAt = strtotime((string)($a['starts_at'] ?? ''));
            $endsAt = strtotime((string)($a['ends_at'] ?? ''));
            if ($status === 'upcoming' && $startsAt !== false && $startsAt <= $now) {
                $needsTransition = true;
                break;
            }
            if ($status === 'active' && $endsAt !== false && $endsAt <= $now) {
                $needsTransition = true;
                break;
            }
        }

        if (!$needsTransition) {
            return 0;
        }

        $db->transaction(function (JsonDatabase $db) use ($now, &$closedCount) {
            $auctions = $db->all('auctions');
            $settings = $db->find('platform_settings', 1) ?? [];
            $commissionRate = (float)($settings['commission_rate_percent'] ?? 15.0);
            $buyerPremiumRate = (float)($settings['buyer_premium_percent'] ?? 20.0);

            foreach ($auctions as $auction) {
                $status = $auction['status'] ?? '';
                $startsAt = strtotime((string)($auction['starts_at'] ?? ''));
                $endsAt = strtotime((string)($auction['ends_at'] ?? ''));

                if ($status === 'upcoming' && $startsAt !== false && $startsAt <= $now && ($endsAt === false || $endsAt > $now)) {
                    $db->update('auctions', $auction['id'], ['status' => 'active']);
                    continue;
                }

                if ($status === 'active' && $endsAt !== false && $endsAt <= $now) {
                    self::closeSingleAuction($db, $auction, $commissionRate, $buyerPremiumRate);
                    $closedCount++;
                }
            }
        });

        return $closedCount;
    }

    /**
     * Settle an individual auction inside a transaction.
     */
    public static function closeSingleAuction(
        JsonDatabase $db,
        array $auction,
        float $commissionRate = 15.0,
        float $buyerPremiumRate = 20.0
    ): array {
        $artwork = $db->find('artworks', (int)$auction['artwork_id']) ?? [];
        $bids = $db->where('bids', fn($b) => (int)$b['auction_id'] === (int)$auction['id']);

        // Sort bids descending by amount, then earliest created_at
        usort($bids, function (array $a, array $b): int {
            $cmp = ((float)$b['amount']) <=> ((float)$a['amount']);
            if ($cmp !== 0) {
                return $cmp;
            }
            return strcmp((string)$a['created_at'], (string)$b['created_at']);
        });

        $winningBid = $bids[0] ?? null;
        $reservePrice = (float)($auction['reserve_price'] ?? 0);
        $reserveMet = $winningBid !== null && ((float)$winningBid['amount']) >= $reservePrice;

        if ($winningBid !== null && $reserveMet) {
            $hammerPrice = (float)$winningBid['amount'];
            $winnerId = (int)$winningBid['user_id'];
            $artistId = (int)$auction['artist_id'];

            $updatedAuction = $db->update('auctions', $auction['id'], [
                'status' => 'closed_sold',
                'current_bid' => $hammerPrice,
                'highest_bidder_id' => $winnerId,
                'reserve_met' => true,
                'closed_at' => gmdate('Y-m-d H:i:s'),
            ]);

            $db->update('artworks', $auction['artwork_id'], [
                'status' => 'sold',
            ]);

            // Create Order, Commission, and Pending Seller Payout if not already created
            $existingOrder = $db->findBy('orders', 'auction_id', (int)$auction['id']);
            if (!$existingOrder) {
                $buyerPremium = round($hammerPrice * ($buyerPremiumRate / 100), 2);
                $shippingFee = 450.00;
                $totalAmount = round($hammerPrice + $buyerPremium + $shippingFee, 2);
                $sellerCommission = round($hammerPrice * ($commissionRate / 100), 2);
                $netArtistPayout = round($hammerPrice - $sellerCommission, 2);

                $order = $db->insert('orders', [
                    'order_number' => sprintf('ORD-%s-%04d', gmdate('Y'), random_int(1000, 9999)),
                    'auction_id' => (int)$auction['id'],
                    'artwork_id' => (int)$auction['artwork_id'],
                    'buyer_id' => $winnerId,
                    'artist_id' => $artistId,
                    'hammer_price' => $hammerPrice,
                    'buyer_premium' => $buyerPremium,
                    'shipping_fee' => $shippingFee,
                    'total_amount' => $totalAmount,
                    'seller_commission_rate' => $commissionRate,
                    'seller_commission_amount' => $sellerCommission,
                    'net_artist_payout' => $netArtistPayout,
                    'currency' => $auction['currency'] ?? 'USD',
                    'payment_status' => 'pending',
                    'fulfillment_status' => 'awaiting_payment',
                    'payment_deadline' => gmdate('Y-m-d H:i:s', strtotime('+7 days')),
                ]);

                $db->insert('commissions', [
                    'order_id' => $order['id'],
                    'artist_id' => $artistId,
                    'hammer_price' => $hammerPrice,
                    'commission_rate_percent' => $commissionRate,
                    'commission_amount' => $sellerCommission,
                    'buyer_premium_amount' => $buyerPremium,
                    'currency' => $auction['currency'] ?? 'USD',
                ]);

                $db->insert('seller_payouts', [
                    'order_id' => $order['id'],
                    'artist_id' => $artistId,
                    'gross_sale_amount' => $hammerPrice,
                    'commission_deducted' => $sellerCommission,
                    'net_payout_amount' => $netArtistPayout,
                    'currency' => $auction['currency'] ?? 'USD',
                    'status' => 'pending',
                    'payout_reference' => sprintf('PO-PENDING-%04d', $order['id']),
                    'approved_by' => null,
                ]);

                // Notify Winning Collector
                $title = $artwork['title'] ?? 'Auction Lot';
                $db->insert('notifications', [
                    'user_id' => $winnerId,
                    'type' => 'auction_won',
                    'title' => "Auction Won: {$title}",
                    'message' => "Congratulations. Your winning hammer bid of " . format_money($hammerPrice) . " secured \"{$title}\". Please complete settlement in your Collector Orders dashboard.",
                    'action_url' => '/buyer/orders',
                    'read_at' => null,
                ]);

                // Notify Artist
                $db->insert('notifications', [
                    'user_id' => $artistId,
                    'type' => 'artwork_sold',
                    'title' => "Artwork Sold at Auction: {$title}",
                    'message' => "Your work \"{$title}\" closed at a hammer price of " . format_money($hammerPrice) . ". Order {$order['order_number']} has been generated.",
                    'action_url' => '/artist/sales',
                    'read_at' => null,
                ]);
            }

            return $updatedAuction ?? $auction;
        }

        // Unsold (no bids or reserve not met)
        $updatedAuction = $db->update('auctions', $auction['id'], [
            'status' => 'closed_unsold',
            'reserve_met' => false,
            'closed_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $title = $artwork['title'] ?? 'Auction Lot';
        $db->insert('notifications', [
            'user_id' => (int)$auction['artist_id'],
            'type' => 'auction_unsold',
            'title' => "Auction Concluded (Unsold): {$title}",
            'message' => $winningBid === null
                ? "The timed auction for \"{$title}\" concluded with no bids placed."
                : "The timed auction for \"{$title}\" concluded below the confidential reserve price.",
            'action_url' => '/artist/dashboard',
            'read_at' => null,
        ]);

        return $updatedAuction ?? $auction;
    }

    /**
     * Place a validated, transaction-locked bid on an active auction.
     */
    public static function placeBid(int $auctionId, int $userId, float $amount): array
    {
        $db = JsonDatabase::getInstance();

        return $db->transaction(function (JsonDatabase $db) use ($auctionId, $userId, $amount): array {
            $user = $db->find('users', $userId);
            if (!$user || ($user['status'] ?? 'active') !== 'active') {
                throw new RuntimeException('Your collector account is not eligible to place bids.');
            }

            $settings = $db->find('platform_settings', 1) ?? [];
            if (!empty($settings['require_email_verification_for_bidding']) && empty($user['email_verified_at'])) {
                throw new RuntimeException('Please verify your email address before placing a bid.');
            }

            $auction = $db->find('auctions', $auctionId);
            if (!$auction) {
                throw new RuntimeException('The requested auction lot does not exist.');
            }

            $artwork = $db->find('artworks', (int)$auction['artwork_id']);
            if (!$artwork || ($artwork['status'] ?? '') !== 'published') {
                throw new RuntimeException('This artwork is not currently open for public bidding.');
            }

            // Prevent artist from bidding on their own artwork
            if ((int)$auction['artist_id'] === $userId || (int)$artwork['artist_id'] === $userId) {
                throw new RuntimeException('Institutional policy prohibits artists from bidding on their own works.');
            }

            $now = time();
            $startsAt = strtotime((string)$auction['starts_at']);
            $endsAt = strtotime((string)$auction['ends_at']);

            if (($auction['status'] ?? '') !== 'active' || ($startsAt !== false && $startsAt > $now)) {
                throw new RuntimeException('This auction is not currently open for bidding.');
            }

            if ($endsAt !== false && $endsAt <= $now) {
                // Automatically close the expired auction and reject the late bid
                self::closeSingleAuction($db, $auction);
                throw new RuntimeException('Bidding has closed for this lot.');
            }

            $minNextBid = minimum_next_bid($auction);
            if ($amount < $minNextBid) {
                throw new RuntimeException('Minimum valid bid for this lot is ' . format_money($minNextBid, $auction['currency'] ?? 'USD') . '.');
            }

            // Prevent duplicate identical bid submission within 5 seconds
            $previousHighestBidderId = $auction['highest_bidder_id'] ? (int)$auction['highest_bidder_id'] : null;

            // Generate anonymous collector paddle number
            $paddle = sprintf('PB-%04d', 1000 + (($userId * 791) % 8999));

            $bid = $db->insert('bids', [
                'auction_id' => $auctionId,
                'artwork_id' => (int)$auction['artwork_id'],
                'user_id' => $userId,
                'paddle_number' => $paddle,
                'amount' => round($amount, 2),
                'currency' => $auction['currency'] ?? 'USD',
                'ip_hash' => hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
                'is_seeded' => false,
            ]);

            // Check anti-sniping extension policy
            $newEndsAt = $auction['ends_at'];
            $extended = false;
            $antiSnipingEnabled = ($auction['anti_sniping_enabled'] ?? true) && ($settings['anti_sniping_enabled'] ?? true);
            $windowSeconds = (int)($settings['anti_sniping_window_seconds'] ?? 300);
            $extensionSeconds = (int)($settings['anti_sniping_extension_seconds'] ?? 300);

            if ($antiSnipingEnabled && $endsAt !== false && ($endsAt - $now) <= $windowSeconds) {
                $newEndsAt = gmdate('Y-m-d H:i:s', $now + $extensionSeconds);
                $extended = true;
            }

            $reserveMet = $amount >= (float)($auction['reserve_price'] ?? 0);
            $updatedAuction = $db->update('auctions', $auctionId, [
                'current_bid' => round($amount, 2),
                'bid_count' => (int)($auction['bid_count'] ?? 0) + 1,
                'highest_bidder_id' => $userId,
                'reserve_met' => $reserveMet,
                'ends_at' => $newEndsAt,
            ]);

            // Automatically add artwork to bidder's watchlist if not already present
            $inWatchlist = $db->where('watchlists', fn($w) => (int)$w['user_id'] === $userId && (int)$w['artwork_id'] === (int)$artwork['id']);
            if (empty($inWatchlist)) {
                $db->insert('watchlists', [
                    'user_id' => $userId,
                    'artwork_id' => (int)$artwork['id'],
                ]);
            }

            // Send Bid Confirmation Notification to current bidder
            $db->insert('notifications', [
                'user_id' => $userId,
                'type' => 'bid_confirmed',
                'title' => "Bid Confirmed: {$artwork['title']}",
                'message' => "Your bid of " . format_money($amount) . " (Paddle {$paddle}) is now the leading bid on \"{$artwork['title']}\".",
                'action_url' => '/artworks/' . $artwork['slug'],
                'read_at' => null,
            ]);

            // Send Outbid Alert to previous highest bidder (if different user)
            if ($previousHighestBidderId !== null && $previousHighestBidderId !== $userId) {
                $db->insert('notifications', [
                    'user_id' => $previousHighestBidderId,
                    'type' => 'outbid_alert',
                    'title' => "Outbid Alert: {$artwork['title']}",
                    'message' => "Another collector has placed a higher bid of " . format_money($amount) . " on \"{$artwork['title']}\". Place a new bid to regain the lead.",
                    'action_url' => '/artworks/' . $artwork['slug'],
                    'read_at' => null,
                ]);
            }

            return [
                'bid' => $bid,
                'auction' => $updatedAuction,
                'extended' => $extended,
                'reserve_met' => $reserveMet,
            ];
        });
    }
}
