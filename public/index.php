<?php

declare(strict_types=1);

/**
 * ArtAlly — Front Controller
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Router;
use App\Core\Session;
use App\Services\AuctionEngineService;
use App\Controllers\PublicController;
use App\Controllers\AuthController;
use App\Controllers\ArtistController;
use App\Controllers\BuyerController;
use App\Controllers\AdminController;

// Serve static assets when running via PHP built-in development server
if (PHP_SAPI === 'cli-server') {
    $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $staticFile = __DIR__ . $uriPath;
    if ($uriPath !== '/' && is_file($staticFile)) {
        return false;
    }
}

Session::start();

// Synchronize scheduled and expired auctions on every request
AuctionEngineService::synchronizeAuctions();

$router = new Router();

// Public Routes
$router->get('/', [PublicController::class, 'home']);
$router->get('/auctions', [PublicController::class, 'auctions']);
$router->get('/upcoming-auctions', [PublicController::class, 'upcomingAuctions']);
$router->get('/catalogue', [PublicController::class, 'catalogue']);
$router->get('/artworks/{slug}', [PublicController::class, 'artworkDetail']);
$router->get('/categories', [PublicController::class, 'categories']);
$router->get('/artists', [PublicController::class, 'artists']);
$router->get('/artists/{slug}', [PublicController::class, 'artistProfile']);
$router->get('/journal', [PublicController::class, 'journal']);
$router->get('/journal/{slug}', [PublicController::class, 'journalArticle']);
$router->get('/sell-your-art', [PublicController::class, 'sellYourArt']);
$router->get('/how-it-works', fn() => (new PublicController())->institutionalPage('how-it-works'));
$router->get('/buyer-guide', fn() => (new PublicController())->institutionalPage('buyer-guide'));
$router->get('/seller-guide', fn() => (new PublicController())->institutionalPage('seller-guide'));
$router->get('/about', fn() => (new PublicController())->institutionalPage('about'));
$router->get('/contact', fn() => (new PublicController())->institutionalPage('contact'));
$router->get('/terms', fn() => (new PublicController())->institutionalPage('terms'));
$router->get('/privacy', fn() => (new PublicController())->institutionalPage('privacy'));
$router->get('/sitemap.xml', [PublicController::class, 'sitemapXml']);
$router->post('/newsletter/subscribe', [PublicController::class, 'subscribeNewsletter']);
$router->post('/artworks/report', [PublicController::class, 'reportArtwork']);

// Authentication Routes
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/login/demo', [AuthController::class, 'quickDemoLogin']);
$router->get('/register', [AuthController::class, 'showRegister']);
$router->post('/register', [AuthController::class, 'register']);
$router->get('/verify-email', [AuthController::class, 'showVerifyEmail']);
$router->post('/verify-email', [AuthController::class, 'verifyEmailAction']);
$router->get('/forgot-password', [AuthController::class, 'showForgotPassword']);
$router->post('/forgot-password', [AuthController::class, 'sendResetLink']);
$router->get('/reset-password', [AuthController::class, 'showResetPassword']);
$router->post('/reset-password', [AuthController::class, 'resetPassword']);
$router->post('/logout', [AuthController::class, 'logout']);

// Artist Studio Routes
$router->get('/artist/dashboard', [ArtistController::class, 'dashboard']);
$router->get('/artist/profile', [ArtistController::class, 'editProfile']);
$router->post('/artist/profile', [ArtistController::class, 'updateProfile']);
$router->get('/artist/submit', [ArtistController::class, 'showSubmitArtwork']);
$router->post('/artist/submit', [ArtistController::class, 'storeSubmission']);
$router->get('/artist/submissions', [ArtistController::class, 'submissions']);
$router->post('/artist/submissions/withdraw', [ArtistController::class, 'withdrawSubmission']);
$router->post('/artist/submissions/negotiate', [ArtistController::class, 'respondToNegotiation']);
$router->get('/artist/sales', [ArtistController::class, 'salesAndPayouts']);
$router->get('/certificates/{id}', [ArtistController::class, 'viewCertificate']);

// Buyer / Collector Routes
$router->get('/buyer/dashboard', [BuyerController::class, 'dashboard']);
$router->get('/buyer/watchlist', [BuyerController::class, 'watchlist']);
$router->post('/buyer/watchlist/toggle', [BuyerController::class, 'toggleWatchlist']);
$router->post('/buyer/artists/follow', [BuyerController::class, 'toggleFollowArtist']);
$router->post('/buyer/bids/place', [BuyerController::class, 'placeBid']);
$router->get('/buyer/orders', [BuyerController::class, 'orders']);
$router->post('/buyer/orders/pay', [BuyerController::class, 'payOrder']);
$router->post('/buyer/notifications/read', [BuyerController::class, 'markNotificationRead']);
$router->post('/buyer/enable-artist', [BuyerController::class, 'enableArtistRole']);

// Administrator Routes
$router->get('/admin', [AdminController::class, 'showLogin']);
$router->get('/admin/login', [AdminController::class, 'showLogin']);
$router->post('/admin/login', [AdminController::class, 'login']);
$router->post('/admin/setup-first', [AdminController::class, 'setupFirstAdmin']);
$router->get('/admin/dashboard', [AdminController::class, 'dashboard']);
$router->get('/admin/submissions', [AdminController::class, 'dashboard']);
$router->post('/admin/submissions/review', [AdminController::class, 'reviewSubmission']);
$router->post('/admin/artworks/control', [AdminController::class, 'controlArtworkAndAuction']);
$router->post('/admin/artworks/delete', [AdminController::class, 'deleteArtwork']);
$router->post('/admin/artists/approve', [AdminController::class, 'approveArtistProfile']);
$router->post('/admin/users/delete', [AdminController::class, 'deleteArtistOrUser']);
$router->post('/admin/profile/manage', [AdminController::class, 'manageAdminProfile']);
$router->post('/admin/auctions/live-control', [AdminController::class, 'adjustLiveBidAndStatus']);
$router->post('/admin/auctions/close', [AdminController::class, 'closeAuctionNow']);
$router->post('/admin/payouts/approve', [AdminController::class, 'approvePayout']);
$router->post('/admin/settings', [AdminController::class, 'updateSettings']);
$router->post('/admin/categories', [AdminController::class, 'createCategory']);
$router->post('/admin/users/toggle-status', [AdminController::class, 'toggleUserStatus']);

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
