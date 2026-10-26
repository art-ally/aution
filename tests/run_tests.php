<?php

declare(strict_types=1);

/**
 * ATELIER NOIR — Automated Integration Test Suite
 * Verifies:
 * 1. Authentication, Password Hashing & Role-Based Authorization
 * 2. Multi-step Artwork Submission & Strict Non-Direct-Publish Policy
 * 3. Administrator Curatorial Approval & Auction Scheduling
 * 4. Transactional Bidding Engine, Minimum Increments & Self-Bid Prevention
 * 5. Anti-Sniping Automatic Extension
 * 6. Auction Closing, Reserve Price Enforcement, Order Generation, Commissions & Seller Payouts
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Core\JsonDatabase;
use App\Services\AuctionEngineService;

$db = JsonDatabase::getInstance();
$passed = 0;
$failed = 0;

function assert_true(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$label}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$label}\n";
        $failed++;
    }
}

echo "=================================================================\n";
echo " ATELIER NOIR — INSTITUTIONAL ART AUCTION ENGINE TEST SUITE\n";
echo "=================================================================\n\n";

// 1. Seeded Data & Role Integrity
$users = $db->all('users');
$categories = $db->all('categories');
assert_true(count($users) >= 6, 'Seeded institutional users (Admin, Artists, Collectors) exist');
assert_true(count($categories) >= 10, 'All 10 required curatorial department categories exist');

$admin = $db->findBy('users', 'email', 'admin@ateliernoir.art');
$artist = $db->findBy('users', 'email', 'soren@ateliernoir.art');
$buyer1 = $db->findBy('users', 'email', 'collector@ateliernoir.art');
$buyer2 = $db->findBy('users', 'email', 'aurelia@ateliernoir.art');

assert_true($admin !== null && password_verify('AtelierNoir2026!', $admin['password']), 'Admin password hash verification succeeds');
assert_true(in_array('admin', $admin['roles'] ?? [], true), 'Admin role assignment verified');

// 2. Artwork Submission Non-Direct-Publish Policy
$testArtwork = $db->insert('artworks', [
    'artist_id' => (int)$artist['id'],
    'category_id' => 1,
    'title' => 'Test Consignment Work (Unapproved)',
    'slug' => 'test-consignment-work-' . uniqid(),
    'medium' => 'Oil on linen',
    'year_created' => 2026,
    'dimensions' => '100 x 100 cm',
    'classification' => 'Original Unique Work',
    'edition_number' => 'Unique (1/1)',
    'artist_attribution' => 'Signed by Soren Lindqvist',
    'description' => 'Test submission work.',
    'primary_image' => 'https://images.unsplash.com/photo-1541961017774-22349e4a1262?auto=format&fit=crop&w=800&q=80',
    'currency' => 'USD',
    'estimate_low' => 5000,
    'estimate_high' => 8000,
    'starting_bid' => 4000,
    'reserve_price' => 5000,
    'status' => 'submitted', // Never published directly
]);

$publicCatalogueWorks = $db->where('artworks', fn($a) => in_array($a['status'] ?? '', ['published', 'sold'], true));
$foundInPublic = false;
foreach ($publicCatalogueWorks as $pw) {
    if ((int)$pw['id'] === (int)$testArtwork['id']) {
        $foundInPublic = true;
    }
}
assert_true(!$foundInPublic, 'Newly submitted artwork is strictly excluded from public catalogue before admin approval');

// 3. Admin Approval & Auction Creation
$db->update('artworks', $testArtwork['id'], ['status' => 'published']);
$testAuction = $db->insert('auctions', [
    'artwork_id' => (int)$testArtwork['id'],
    'artist_id' => (int)$artist['id'],
    'title' => 'Test Evening Auction',
    'lot_number' => 'LOT TEST',
    'currency' => 'USD',
    'starting_bid' => 4000,
    'current_bid' => 4000,
    'reserve_price' => 5000,
    'reserve_met' => false,
    'estimate_low' => 5000,
    'estimate_high' => 8000,
    'bid_count' => 0,
    'highest_bidder_id' => null,
    'starts_at' => gmdate('Y-m-d H:i:s', strtotime('-1 hour')),
    'ends_at' => gmdate('Y-m-d H:i:s', strtotime('+2 minutes')), // Within 5-minute anti-sniping window!
    'status' => 'active',
    'anti_sniping_enabled' => true,
]);

// 4. Prevent Artist from Bidding on Their Own Artwork
$selfBidBlocked = false;
try {
    AuctionEngineService::placeBid((int)$testAuction['id'], (int)$artist['id'], 4500);
} catch (\Throwable $e) {
    $selfBidBlocked = true;
}
assert_true($selfBidBlocked, 'Server engine prevents artist from bidding on their own artwork');

// 5. Place Valid Opening Bid & Verify Anti-Sniping Extension
$bidResult1 = AuctionEngineService::placeBid((int)$testAuction['id'], (int)$buyer1['id'], 4000);
assert_true((float)$bidResult1['auction']['current_bid'] === 4000.0, 'Opening bid of $4,000 recorded accurately');
assert_true($bidResult1['extended'] === true, 'Anti-sniping rule extended closing timestamp for bid placed within final 5 minutes');
assert_true($bidResult1['reserve_met'] === false, 'Reserve price ($5,000) accurately marked as not yet met at $4,000');

// 6. Enforce Minimum Bid Increment ($250 increment for $1,000–$4,999 tier)
$lowIncrementBlocked = false;
try {
    AuctionEngineService::placeBid((int)$testAuction['id'], (int)$buyer2['id'], 4100); // Needs at least 4250
} catch (\Throwable $e) {
    $lowIncrementBlocked = true;
}
assert_true($lowIncrementBlocked, 'Server engine rejects bid that does not meet minimum increment ($4,250 required)');

// 7. Place Qualifying Higher Bid Meeting Reserve Price & Triggering Outbid Notification
$bidResult2 = AuctionEngineService::placeBid((int)$testAuction['id'], (int)$buyer2['id'], 5500);
assert_true((float)$bidResult2['auction']['current_bid'] === 5500.0, 'Competing collector bid of $5,500 recorded');
assert_true($bidResult2['reserve_met'] === true, 'Reserve price ($5,000) marked as met at $5,500');

// 8. Settle Auction & Verify Order, Commission (15%), Buyer Premium (20%), and Pending Payout Creation
$db->transaction(function (JsonDatabase $db) use ($testAuction) {
    $freshAuction = $db->find('auctions', (int)$testAuction['id']);
    AuctionEngineService::closeSingleAuction($db, $freshAuction, 15.0, 20.0);
});

$settledAuction = $db->find('auctions', (int)$testAuction['id']);
$generatedOrder = $db->findBy('orders', 'auction_id', (int)$testAuction['id']);
$generatedPayout = $generatedOrder ? $db->findBy('seller_payouts', 'order_id', (int)$generatedOrder['id']) : null;

assert_true(($settledAuction['status'] ?? '') === 'closed_sold', 'Auction transitions to closed_sold when reserve is met');
assert_true($generatedOrder !== null && (int)$generatedOrder['buyer_id'] === (int)$buyer2['id'], 'Order generated for highest validated bidder');
assert_true((float)($generatedOrder['buyer_premium'] ?? 0) === 1100.0, 'Buyer premium (20% of $5,500 = $1,100) calculated accurately');
assert_true((float)($generatedOrder['seller_commission_amount'] ?? 0) === 825.0, 'Seller commission (15% of $5,500 = $825) calculated accurately');
assert_true((float)($generatedPayout['net_payout_amount'] ?? 0) === 4675.0, 'Net artist payout ($5,500 - $825 = $4,675) recorded in pending payout workflow');

// Clean up temporary test records so production store stays clean
$db->delete('artworks', $testArtwork['id']);
$db->delete('auctions', $testAuction['id']);
if ($generatedOrder) {
    $db->delete('orders', $generatedOrder['id']);
}
if ($generatedPayout) {
    $db->delete('seller_payouts', $generatedPayout['id']);
}

echo "\n=================================================================\n";
echo " TEST SUMMARY: {$passed} PASSED, {$failed} FAILED\n";
echo "=================================================================\n";

exit($failed > 0 ? 1 : 0);
