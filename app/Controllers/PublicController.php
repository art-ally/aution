<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\JsonDatabase;
use App\Core\Session;

class PublicController
{
    private JsonDatabase $db;

    public function __construct()
    {
        $this->db = JsonDatabase::getInstance();
    }

    /**
     * Enrich an artwork with its artist profile, category, and active/latest auction
     */
    public static function enrichArtwork(JsonDatabase $db, array $artwork): array
    {
        $artistProfile = $db->findBy('artist_profiles', 'user_id', (int)$artwork['artist_id']);
        $artistUser = $db->find('users', (int)$artwork['artist_id']);
        $category = $db->find('categories', (int)($artwork['category_id'] ?? 1));
        $auctions = $db->where('auctions', fn($a) => (int)$a['artwork_id'] === (int)$artwork['id']);
        $auction = $auctions[0] ?? null;

        $artwork['artist_name'] = $artistProfile['display_name'] ?? $artistUser['name'] ?? 'Independent Artist';
        $artwork['artist_slug'] = $artistProfile['slug'] ?? slugify($artwork['artist_name']);
        $artwork['artist_country'] = $artistProfile['country'] ?? $artistUser['country'] ?? '';
        $artwork['artist_profile'] = $artistProfile;
        $artwork['category_name'] = $category['name'] ?? 'Fine Art';
        $artwork['category_slug'] = $category['slug'] ?? 'contemporary-art';
        $artwork['auction'] = $auction;

        return $artwork;
    }

    public function home(): void
    {
        $allArtworks = $this->db->where('artworks', fn($a) => in_array($a['status'] ?? '', ['published', 'sold'], true));
        $enriched = array_map(fn($a) => self::enrichArtwork($this->db, $a), $allArtworks);

        $featuredAuctions = array_values(array_filter(
            $enriched,
            fn($a) => ($a['auction']['status'] ?? '') === 'active'
        ));

        $upcomingAuctions = array_values(array_filter(
            $enriched,
            fn($a) => ($a['auction']['status'] ?? '') === 'upcoming'
        ));

        $categories = $this->db->all('categories');
        $artists = $this->db->where('artist_profiles', fn($ap) => ($ap['approval_status'] ?? 'approved') === 'approved');
        $articles = $this->db->all('editorial_articles');

        view('public/home', [
            'pageTitle' => 'ArtAlly — Fine Art Auctions & Curated Private Collections',
            'metaDescription' => 'Discover exceptional works from independent artists, established creators, and remarkable private collections through transparent timed auctions.',
            'featuredAuctions' => $featuredAuctions,
            'upcomingAuctions' => $upcomingAuctions,
            'categories' => $categories,
            'artists' => array_slice($artists, 0, 4),
            'articles' => array_slice($articles, 0, 3),
        ]);
    }

    public function auctions(): void
    {
        $statusFilter = $_GET['status'] ?? 'active';
        $allArtworks = $this->db->where('artworks', fn($a) => in_array($a['status'] ?? '', ['published', 'sold'], true));
        $enriched = array_map(fn($a) => self::enrichArtwork($this->db, $a), $allArtworks);

        if ($statusFilter === 'upcoming') {
            $lots = array_values(array_filter($enriched, fn($a) => ($a['auction']['status'] ?? '') === 'upcoming'));
        } elseif ($statusFilter === 'closed') {
            $lots = array_values(array_filter($enriched, fn($a) => str_starts_with((string)($a['auction']['status'] ?? ''), 'closed')));
        } else {
            $lots = array_values(array_filter($enriched, fn($a) => ($a['auction']['status'] ?? '') === 'active'));
        }

        view('public/auctions', [
            'pageTitle' => 'Timed Fine Art Auctions — ArtAlly',
            'lots' => $lots,
            'statusFilter' => $statusFilter,
        ]);
    }

    public function upcomingAuctions(): void
    {
        $_GET['status'] = 'upcoming';
        $this->auctions();
    }

    public function catalogue(): void
    {
        $q = trim((string)($_GET['q'] ?? ''));
        $categorySlug = trim((string)($_GET['category'] ?? ''));
        $artistSlug = trim((string)($_GET['artist'] ?? ''));
        $statusFilter = trim((string)($_GET['status'] ?? ''));
        $mediumFilter = trim((string)($_GET['medium'] ?? ''));
        $minPrice = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? (float)$_GET['min_price'] : null;
        $maxPrice = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (float)$_GET['max_price'] : null;
        $sort = trim((string)($_GET['sort'] ?? 'closing_soon'));
        $viewMode = ($_GET['view'] ?? 'grid') === 'list' ? 'list' : 'grid';

        // Only approved & published/sold works appear in public catalogue
        $artworks = $this->db->where('artworks', fn($a) => in_array($a['status'] ?? '', ['published', 'sold'], true));
        $enriched = array_map(fn($a) => self::enrichArtwork($this->db, $a), $artworks);

        $filtered = array_values(array_filter($enriched, function (array $item) use (
            $q,
            $categorySlug,
            $artistSlug,
            $statusFilter,
            $mediumFilter,
            $minPrice,
            $maxPrice
        ): bool {
            if ($q !== '') {
                $haystack = strtolower(
                    ($item['title'] ?? '') . ' ' .
                    ($item['artist_name'] ?? '') . ' ' .
                    ($item['medium'] ?? '') . ' ' .
                    ($item['description'] ?? '') . ' ' .
                    ($item['category_name'] ?? '')
                );
                if (!str_contains($haystack, strtolower($q))) {
                    return false;
                }
            }

            if ($categorySlug !== '' && ($item['category_slug'] ?? '') !== $categorySlug) {
                return false;
            }

            if ($artistSlug !== '' && ($item['artist_slug'] ?? '') !== $artistSlug) {
                return false;
            }

            if ($statusFilter !== '') {
                $aucStatus = $item['auction']['status'] ?? '';
                if ($statusFilter === 'active' && $aucStatus !== 'active') {
                    return false;
                }
                if ($statusFilter === 'upcoming' && $aucStatus !== 'upcoming') {
                    return false;
                }
                if ($statusFilter === 'closed' && !str_starts_with($aucStatus, 'closed')) {
                    return false;
                }
            }

            if ($mediumFilter !== '' && !str_contains(strtolower($item['medium'] ?? ''), strtolower($mediumFilter))) {
                return false;
            }

            $price = (float)($item['auction']['current_bid'] ?? $item['starting_bid'] ?? 0);
            if ($minPrice !== null && $price < $minPrice) {
                return false;
            }
            if ($maxPrice !== null && $price > $maxPrice) {
                return false;
            }

            return true;
        }));

        // Sorting
        usort($filtered, function (array $a, array $b) use ($sort): int {
            $priceA = (float)($a['auction']['current_bid'] ?? $a['starting_bid'] ?? 0);
            $priceB = (float)($b['auction']['current_bid'] ?? $b['starting_bid'] ?? 0);
            $bidsA = (int)($a['auction']['bid_count'] ?? 0);
            $bidsB = (int)($b['auction']['bid_count'] ?? 0);
            $endA = strtotime((string)($a['auction']['ends_at'] ?? '+30 days')) ?: PHP_INT_MAX;
            $endB = strtotime((string)($b['auction']['ends_at'] ?? '+30 days')) ?: PHP_INT_MAX;

            return match ($sort) {
                'price_asc' => $priceA <=> $priceB,
                'price_desc' => $priceB <=> $priceA,
                'popularity' => $bidsB <=> $bidsA,
                'newest' => ((int)$b['id']) <=> ((int)$a['id']),
                default => $endA <=> $endB, // closing_soon
            };
        });

        $categories = $this->db->all('categories');
        $artists = $this->db->all('artist_profiles');

        view('public/catalogue', [
            'pageTitle' => 'Fine Art Catalogue — ArtAlly',
            'artworks' => $filtered,
            'categories' => $categories,
            'artists' => $artists,
            'filters' => [
                'q' => $q,
                'category' => $categorySlug,
                'artist' => $artistSlug,
                'status' => $statusFilter,
                'medium' => $mediumFilter,
                'min_price' => $minPrice,
                'max_price' => $maxPrice,
                'sort' => $sort,
                'view' => $viewMode,
            ],
        ]);
    }

    public function artworkDetail(string $slug): void
    {
        $artwork = $this->db->findBy('artworks', 'slug', $slug);
        if (!$artwork) {
            http_response_code(404);
            view('pages/404', ['pageTitle' => 'Lot Not Found — ArtAlly']);
            return;
        }

        // Only allow public viewing if published/sold, OR if user is owner artist or admin
        $user = Auth::user();
        $isOwnerOrAdmin = $user && ((int)$user['id'] === (int)$artwork['artist_id'] || Auth::hasRole('admin'));
        if (!in_array($artwork['status'] ?? '', ['published', 'sold'], true) && !$isOwnerOrAdmin) {
            http_response_code(403);
            flash('error', 'This submission is currently under confidential curatorial review.');
            redirect('/catalogue');
        }

        $artwork = self::enrichArtwork($this->db, $artwork);
        $images = $this->db->where('artwork_images', fn($i) => (int)$i['artwork_id'] === (int)$artwork['id']);
        usort($images, fn($a, $b) => ((int)($a['sort_order'] ?? 1)) <=> ((int)($b['sort_order'] ?? 1)));

        $auction = $artwork['auction'];
        $bids = [];
        if ($auction) {
            $bids = $this->db->where('bids', fn($b) => (int)$b['auction_id'] === (int)$auction['id']);
            usort($bids, fn($a, $b) => ((float)$b['amount']) <=> ((float)$a['amount']));
        }

        // Related artworks
        $relatedRaw = $this->db->where(
            'artworks',
            fn($a) => (int)$a['id'] !== (int)$artwork['id'] && ($a['status'] ?? '') === 'published'
        );
        $related = array_slice(array_map(fn($a) => self::enrichArtwork($this->db, $a), $relatedRaw), 0, 3);

        view('public/artwork-detail', [
            'pageTitle' => $artwork['title'] . ' by ' . $artwork['artist_name'] . ' — ArtAlly',
            'metaDescription' => substr(strip_tags((string)$artwork['description']), 0, 155),
            'artwork' => $artwork,
            'images' => $images,
            'auction' => $auction,
            'bids' => $bids,
            'related' => $related,
        ]);
    }

    public function categories(): void
    {
        $categories = $this->db->all('categories');
        foreach ($categories as &$cat) {
            $count = count($this->db->where(
                'artworks',
                fn($a) => (int)($a['category_id'] ?? 0) === (int)$cat['id'] && in_array($a['status'] ?? '', ['published', 'sold'], true)
            ));
            $cat['artwork_count'] = $count;
        }
        unset($cat);

        view('public/categories', [
            'pageTitle' => 'Collecting Categories & Curatorial Departments — ArtAlly',
            'categories' => $categories,
        ]);
    }

    public function artists(): void
    {
        $allArtists = $this->db->all('artist_profiles');
        $artists = [];
        foreach ($allArtists as $artist) {
            $works = $this->db->where(
                'artworks',
                fn($a) => (int)$a['artist_id'] === (int)$artist['user_id'] && in_array($a['status'] ?? '', ['published', 'sold'], true)
            );
            $artist['published_count'] = count($works);
            $artist['is_approved'] = ($artist['approval_status'] ?? 'approved') === 'approved';
            $artists[] = $artist;
        }

        view('public/artists', [
            'pageTitle' => 'Represented & Independent Artists — ArtAlly',
            'artists' => $artists,
        ]);
    }

    public function artistProfile(string $slug): void
    {
        $artist = $this->db->findBy('artist_profiles', 'slug', $slug);
        if (!$artist) {
            http_response_code(404);
            view('pages/404', ['pageTitle' => 'Artist Not Found — ArtAlly']);
            return;
        }

        $isApproved = ($artist['approval_status'] ?? 'approved') === 'approved';
        $user = Auth::user();
        $isOwnerOrAdmin = $user && ((int)$user['id'] === (int)$artist['user_id'] || Auth::hasRole('admin'));

        if (!$isApproved && !$isOwnerOrAdmin) {
            view('public/artist-awaiting-approval', [
                'pageTitle' => 'Artist Profile Awaiting Curatorial Approval — ArtAlly',
                'artist' => $artist,
            ]);
            return;
        }

        $rawWorks = $this->db->where(
            'artworks',
            fn($a) => (int)$a['artist_id'] === (int)$artist['user_id'] && ($isOwnerOrAdmin || in_array($a['status'] ?? '', ['published', 'sold'], true))
        );
        $artworks = array_map(fn($a) => self::enrichArtwork($this->db, $a), $rawWorks);

        $isFollowing = false;
        if ($user) {
            $follows = $this->db->where(
                'followed_artists',
                fn($f) => (int)$f['user_id'] === (int)$user['id'] && (int)$f['artist_profile_id'] === (int)$artist['id']
            );
            $isFollowing = !empty($follows);
        }

        view('public/artist-profile', [
            'pageTitle' => $artist['display_name'] . ' — Artist Portfolio & Auctions | ArtAlly',
            'artist' => $artist,
            'artworks' => $artworks,
            'isFollowing' => $isFollowing,
            'isApproved' => $isApproved,
        ]);
    }

    public function journal(): void
    {
        $articles = $this->db->all('editorial_articles');
        view('public/journal', [
            'pageTitle' => 'Stories & Curatorial Journal — ArtAlly',
            'articles' => $articles,
        ]);
    }

    public function journalArticle(string $slug): void
    {
        $article = $this->db->findBy('editorial_articles', 'slug', $slug);
        if (!$article) {
            http_response_code(404);
            view('pages/404', ['pageTitle' => 'Article Not Found — ArtAlly']);
            return;
        }

        $moreArticles = $this->db->where('editorial_articles', fn($a) => $a['slug'] !== $slug);
        view('public/journal-article', [
            'pageTitle' => $article['title'] . ' — ArtAlly Journal',
            'article' => $article,
            'moreArticles' => array_slice($moreArticles, 0, 2),
        ]);
    }

    public function sellYourArt(): void
    {
        $settings = $this->db->find('platform_settings', 1) ?? [];
        view('public/sell-your-art', [
            'pageTitle' => 'Consign & Sell Your Artwork — ArtAlly',
            'settings' => $settings,
        ]);
    }

    public function institutionalPage(string $pageKey): void
    {
        $pages = [
            'how-it-works' => [
                'title' => 'How ArtAlly Timed Auctions Work',
                'subtitle' => 'Curatorial Rigor, Transparent Bidding, and Institutional Settlement',
            ],
            'buyer-guide' => [
                'title' => 'Collector & Buyer Guide',
                'subtitle' => 'Registration, Paddle Verification, Buyer’s Premium, and Museum Logistics',
            ],
            'seller-guide' => [
                'title' => 'Artist & Consignor Guide',
                'subtitle' => 'Multi-Step Submission, Specialist Valuation, Reserve Protection, and Artist Payouts',
            ],
            'about' => [
                'title' => 'About ArtAlly',
                'subtitle' => 'An International Digital Auction House Dedicated to Living Artists and Private Collections',
            ],
            'contact' => [
                'title' => 'Private Client & Specialist Concierge',
                'subtitle' => 'Geneva · New York · London · Paris',
            ],
            'terms' => [
                'title' => 'Conditions of Sale & Terms of Use',
                'subtitle' => 'Governing Timed Auctions, Reserve Prices, and Title Transfer',
            ],
            'privacy' => [
                'title' => 'Privacy & Collector Confidentiality Policy',
                'subtitle' => 'Protecting Anonymous Paddle Bidding, Provenance Records, and Financial Data',
            ],
        ];

        $meta = $pages[$pageKey] ?? $pages['about'];
        view('public/institutional', [
            'pageTitle' => $meta['title'] . ' — ArtAlly',
            'pageKey' => $pageKey,
            'meta' => $meta,
        ]);
    }

    public function subscribeNewsletter(): void
    {
        $email = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
        if (!$email) {
            flash('error', 'Please enter a valid email address to subscribe to the Curatorial Dispatch.');
            redirect($_SERVER['HTTP_REFERER'] ?? '/');
        }

        $existing = $this->db->findBy('newsletter_subscribers', 'email', strtolower($email));
        if (!$existing) {
            $this->db->insert('newsletter_subscribers', [
                'email' => strtolower($email),
                'status' => 'subscribed',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            ]);
        }

        flash('success', 'Thank you. You are now subscribed to the ArtAlly Curatorial Dispatch.');
        redirect($_SERVER['HTTP_REFERER'] ?? '/');
    }

    public function reportArtwork(): void
    {
        $artworkId = (int)($_POST['artwork_id'] ?? 0);
        $reason = trim((string)($_POST['reason'] ?? ''));
        $details = trim((string)($_POST['details'] ?? ''));
        $user = Auth::user();

        if ($artworkId <= 0 || $reason === '') {
            flash('error', 'Please provide a reason for the curatorial inquiry or report.');
            redirect($_SERVER['HTTP_REFERER'] ?? '/catalogue');
        }

        $this->db->insert('reports', [
            'artwork_id' => $artworkId,
            'reporter_user_id' => $user ? (int)$user['id'] : null,
            'reporter_name' => $user['name'] ?? trim((string)($_POST['reporter_name'] ?? 'Anonymous Collector')),
            'reason' => $reason,
            'details' => $details,
            'status' => 'open',
        ]);

        flash('success', 'Your report has been logged with the ArtAlly Compliance & Provenance Committee.');
        redirect($_SERVER['HTTP_REFERER'] ?? '/catalogue');
    }

    public function sitemapXml(): void
    {
        header('Content-Type: application/xml; charset=UTF-8');
        $artworks = $this->db->where('artworks', fn($a) => in_array($a['status'] ?? '', ['published', 'sold'], true));
        $artists = $this->db->all('artist_profiles');
        $host = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost:8000');

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach (['/', '/auctions', '/catalogue', '/categories', '/artists', '/journal', '/sell-your-art'] as $path) {
            echo "  <url><loc>{$host}{$path}</loc><changefreq>daily</changefreq><priority>0.9</priority></url>\n";
        }
        foreach ($artworks as $art) {
            echo "  <url><loc>{$host}/artworks/" . e($art['slug']) . "</loc><changefreq>hourly</changefreq><priority>0.8</priority></url>\n";
        }
        foreach ($artists as $artist) {
            echo "  <url><loc>{$host}/artists/" . e($artist['slug']) . "</loc><changefreq>weekly</changefreq><priority>0.7</priority></url>\n";
        }
        echo '</urlset>';
    }
}
