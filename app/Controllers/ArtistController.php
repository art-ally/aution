<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\JsonDatabase;

class ArtistController
{
    private JsonDatabase $db;

    public function __construct()
    {
        $this->db = JsonDatabase::getInstance();
    }

    private function ensureArtistProfile(array $user): array
    {
        $profile = $this->db->findBy('artist_profiles', 'user_id', (int)$user['id']);
        if (!$profile) {
            $profile = $this->db->insert('artist_profiles', [
                'user_id' => (int)$user['id'],
                'display_name' => $user['name'],
                'slug' => slugify($user['name'] . '-' . $user['id']),
                'biography' => 'Contemporary visual artist represented on ArtAlly.',
                'artist_statement' => '',
                'profile_photo' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=800&q=80',
                'country' => $user['country'] ?? 'Switzerland',
                'city' => '',
                'discipline' => 'Contemporary Fine Art',
                'years_active' => gmdate('Y') . ' – Present',
                'website' => '',
                'social_instagram' => '',
                'featured' => false,
                'verified_artist' => false,
                'approval_status' => 'pending',
                'portfolio_images' => [],
            ]);
        }
        return $profile;
    }

    public function dashboard(): void
    {
        $user = Auth::requireRole('artist');
        $profile = $this->ensureArtistProfile($user);

        $artworks = $this->db->where('artworks', fn($a) => (int)$a['artist_id'] === (int)$user['id']);
        $submissions = $this->db->where('artwork_submissions', fn($s) => (int)$s['artist_id'] === (int)$user['id']);
        $auctions = $this->db->where('auctions', fn($a) => (int)$a['artist_id'] === (int)$user['id']);
        $orders = $this->db->where('orders', fn($o) => (int)$o['artist_id'] === (int)$user['id']);
        $payouts = $this->db->where('seller_payouts', fn($p) => (int)$p['artist_id'] === (int)$user['id']);
        $notifications = $this->db->where('notifications', fn($n) => (int)$n['user_id'] === (int)$user['id']);
        usort($notifications, fn($a, $b) => ((int)$b['id']) <=> ((int)$a['id']));

        // Enrich submissions with artwork and live auction details
        foreach ($submissions as &$sub) {
            $sub['artwork'] = $this->db->find('artworks', (int)$sub['artwork_id']) ?? [];
            $sub['auction'] = $this->db->findBy('auctions', 'artwork_id', (int)$sub['artwork_id']);
        }
        unset($sub);
        usort($submissions, fn($a, $b) => ((int)$b['id']) <=> ((int)$a['id']));

        $stats = [
            'total_submissions' => count($submissions),
            'awaiting_review' => count(array_filter($submissions, fn($s) => in_array($s['status'] ?? '', ['submitted', 'under_review', 'changes_requested'], true))),
            'approved_artworks' => count(array_filter($artworks, fn($a) => in_array($a['status'] ?? '', ['approved', 'published', 'sold'], true))),
            'active_auctions' => count(array_filter($auctions, fn($a) => ($a['status'] ?? '') === 'active')),
            'sold_artworks' => count(array_filter($artworks, fn($a) => ($a['status'] ?? '') === 'sold')),
            'unsold_artworks' => count(array_filter($auctions, fn($a) => ($a['status'] ?? '') === 'closed_unsold')),
            'total_sales_volume' => array_sum(array_map(fn($o) => (float)($o['hammer_price'] ?? 0), $orders)),
            'pending_payouts' => array_sum(array_map(
                fn($p) => ($p['status'] ?? '') === 'pending' ? (float)($p['net_payout_amount'] ?? 0) : 0.0,
                $payouts
            )),
            'completed_payouts' => array_sum(array_map(
                fn($p) => ($p['status'] ?? '') === 'completed' ? (float)($p['net_payout_amount'] ?? 0) : 0.0,
                $payouts
            )),
        ];

        view('artist/dashboard', [
            'pageTitle' => 'Artist Studio Dashboard — ArtAlly',
            'user' => $user,
            'profile' => $profile,
            'stats' => $stats,
            'submissions' => $submissions,
            'artworks' => $artworks,
            'auctions' => $auctions,
            'orders' => $orders,
            'payouts' => $payouts,
            'notifications' => array_slice($notifications, 0, 8),
        ]);
    }

    public function editProfile(): void
    {
        $user = Auth::requireRole('artist');
        $profile = $this->ensureArtistProfile($user);
        view('artist/profile-edit', [
            'pageTitle' => 'Edit Artist Profile & Portfolio — ArtAlly',
            'user' => $user,
            'profile' => $profile,
        ]);
    }

    public function updateProfile(): void
    {
        $user = Auth::requireRole('artist');
        $profile = $this->ensureArtistProfile($user);

        $displayName = trim((string)($_POST['display_name'] ?? $user['name']));
        $biography = trim((string)($_POST['biography'] ?? ''));
        $statement = trim((string)($_POST['artist_statement'] ?? ''));
        $country = trim((string)($_POST['country'] ?? $user['country']));
        $city = trim((string)($_POST['city'] ?? ''));
        $discipline = trim((string)($_POST['discipline'] ?? ''));
        $yearsActive = trim((string)($_POST['years_active'] ?? ''));
        $website = trim((string)($_POST['website'] ?? ''));
        $instagram = trim((string)($_POST['social_instagram'] ?? ''));
        $profilePhoto = trim((string)($_POST['profile_photo_url'] ?? $profile['profile_photo']));

        // Handle profile photo upload (file upload or base64 data-URL fallback)
        if (!empty($_FILES['profile_photo_file']['tmp_name']) && $_FILES['profile_photo_file']['error'] === UPLOAD_ERR_OK) {
            $uploaded = $this->handlePublicImageUpload($_FILES['profile_photo_file'], 'profiles');
            if ($uploaded) {
                $profilePhoto = $uploaded;
            }
        } elseif (!empty($_POST['profile_photo_data'])) {
            $uploaded = $this->handleBase64ImageUpload((string)$_POST['profile_photo_data'], 'profiles');
            if ($uploaded) {
                $profilePhoto = $uploaded;
            }
        }

        $this->db->update('artist_profiles', $profile['id'], [
            'display_name' => $displayName,
            'biography' => $biography,
            'artist_statement' => $statement,
            'profile_photo' => $profilePhoto,
            'country' => $country,
            'city' => $city,
            'discipline' => $discipline,
            'years_active' => $yearsActive,
            'website' => $website,
            'social_instagram' => $instagram,
        ]);

        $this->db->update('users', $user['id'], [
            'name' => $displayName,
            'country' => $country,
        ]);

        flash('success', 'Your artist profile picture and studio biography have been updated.');
        $returnTo = trim((string)($_POST['return_to'] ?? '/artist/dashboard'));
        redirect(str_starts_with($returnTo, '/artist/') ? $returnTo : '/artist/dashboard');
    }

    public function showSubmitArtwork(): void
    {
        $user = Auth::requireRole('artist');
        $this->ensureArtistProfile($user);
        $categories = $this->db->all('categories');
        $settings = $this->db->find('platform_settings', 1) ?? [];

        $draftSubmission = null;
        $draftArtwork = null;
        if (!empty($_GET['edit_id'])) {
            $subId = (int)$_GET['edit_id'];
            $sub = $this->db->find('artwork_submissions', $subId);
            if ($sub && (int)$sub['artist_id'] === (int)$user['id']) {
                $draftSubmission = $sub;
                $draftArtwork = $this->db->find('artworks', (int)$sub['artwork_id']);
            }
        }

        view('artist/submit-artwork', [
            'pageTitle' => 'Submit Artwork for Curatorial Review — ArtAlly',
            'user' => $user,
            'categories' => $categories,
            'settings' => $settings,
            'draftSubmission' => $draftSubmission,
            'draftArtwork' => $draftArtwork,
        ]);
    }

    public function storeSubmission(): void
    {
        $user = Auth::requireRole('artist');
        $this->ensureArtistProfile($user);

        $actionMode = ($_POST['submission_mode'] ?? 'submit') === 'draft' ? 'draft' : 'submitted';
        $editSubId = (int)($_POST['edit_submission_id'] ?? 0);

        $title = trim((string)($_POST['title'] ?? ''));
        $categoryId = (int)($_POST['category_id'] ?? 1);
        $medium = trim((string)($_POST['medium'] ?? ''));
        $yearCreated = (int)($_POST['year_created'] ?? (int)gmdate('Y'));
        $dimensions = trim((string)($_POST['dimensions'] ?? ''));
        $classification = trim((string)($_POST['classification'] ?? 'Original Unique Work'));
        $editionNumber = trim((string)($_POST['edition_number'] ?? 'Unique (1/1)'));
        $attribution = trim((string)($_POST['artist_attribution'] ?? ('Signed by ' . $user['name'])));
        $description = trim((string)($_POST['description'] ?? ''));

        $startingBid = max(100.0, (float)($_POST['suggested_starting_bid'] ?? 1000));
        $artistExpectedPrice = max($startingBid, (float)($_POST['artist_expected_price'] ?? ($startingBid * 1.4)));
        $reservePrice = (float)($_POST['reserve_price'] ?? $startingBid);
        $durationDays = max(3, min(21, (int)($_POST['preferred_duration_days'] ?? 7)));
        $currency = in_array($_POST['currency'] ?? 'USD', ['USD', 'EUR', 'GBP', 'CHF'], true) ? $_POST['currency'] : 'USD';
        $estimateLow = max($startingBid, (float)($_POST['estimate_low'] ?? ($startingBid * 1.2)));
        $estimateHigh = max($estimateLow, (float)($_POST['estimate_high'] ?? ($startingBid * 1.8)));
        $sellerNotes = trim((string)($_POST['seller_notes'] ?? ''));

        $provenance = trim((string)($_POST['provenance'] ?? 'Direct from the studio of the artist.'));
        $conditionReport = trim((string)($_POST['condition_report'] ?? 'Pristine original studio condition.'));
        $authenticityNotes = trim((string)($_POST['authenticity_notes'] ?? 'Includes hand-signed Certificate of Authenticity.'));
        $ownershipDeclared = !empty($_POST['ownership_declared']);
        $copyrightConfirmed = !empty($_POST['copyright_confirmed']);

        if ($title === '' || $medium === '' || $dimensions === '' || $description === '') {
            flash('error', 'Please complete all required artwork metadata fields (Title, Medium, Dimensions, and Description).');
            redirect('/artist/submit');
        }

        if ($actionMode === 'submitted' && (!$ownershipDeclared || !$copyrightConfirmed)) {
            flash('error', 'You must confirm sole legal ownership/consignment authority and image copyright permissions before submitting.');
            redirect('/artist/submit');
        }

        // Handle image URLs and/or uploaded files
        $galleryImages = [];
        if (!empty($_FILES['artwork_images']['name'][0])) {
            $count = count($_FILES['artwork_images']['name']);
            for ($i = 0; $i < $count; $i++) {
                if ($_FILES['artwork_images']['error'][$i] === UPLOAD_ERR_OK) {
                    $fileArr = [
                        'name' => $_FILES['artwork_images']['name'][$i],
                        'type' => $_FILES['artwork_images']['type'][$i],
                        'tmp_name' => $_FILES['artwork_images']['tmp_name'][$i],
                        'error' => $_FILES['artwork_images']['error'][$i],
                        'size' => $_FILES['artwork_images']['size'][$i],
                    ];
                    $uploadedUrl = $this->handlePublicImageUpload($fileArr, 'artworks');
                    if ($uploadedUrl) {
                        $galleryImages[] = $uploadedUrl;
                    }
                }
            }
        }

        if (empty($galleryImages) && !empty($_POST['artwork_images_data']) && is_array($_POST['artwork_images_data'])) {
            foreach ($_POST['artwork_images_data'] as $b64Url) {
                $uploadedUrl = $this->handleBase64ImageUpload((string)$b64Url, 'artworks');
                if ($uploadedUrl) {
                    $galleryImages[] = $uploadedUrl;
                }
            }
        }

        $imageUrlsInput = $_POST['image_urls'] ?? [];
        if (is_array($imageUrlsInput)) {
            foreach ($imageUrlsInput as $u) {
                $u = trim((string)$u);
                if ($u !== '' && (str_starts_with($u, 'http://') || str_starts_with($u, 'https://') || str_starts_with($u, '/uploads/'))) {
                    $galleryImages[] = $u;
                }
            }
        }

        if (empty($galleryImages)) {
            $galleryImages[] = 'https://images.unsplash.com/photo-1579783902614-a3fb3927b675?auto=format&fit=crop&w=1400&q=85';
        }

        $primaryIndex = max(0, (int)($_POST['primary_image_index'] ?? 0));
        $primaryImage = $galleryImages[$primaryIndex] ?? $galleryImages[0];

        // Handle private authenticity document upload (stored outside public web root)
        $privateDocPath = null;
        if (!empty($_FILES['authenticity_document']['tmp_name']) && $_FILES['authenticity_document']['error'] === UPLOAD_ERR_OK) {
            $privateDocPath = $this->handlePrivateDocumentUpload($_FILES['authenticity_document'], (int)$user['id']);
        }

        // CRITICAL RULE: Never publish artwork directly from an artist submission!
        // Always set status to 'draft' or 'submitted' (awaiting admin review).
        $artworkStatus = $actionMode === 'draft' ? 'draft' : 'submitted';

        if ($editSubId > 0) {
            $existingSub = $this->db->find('artwork_submissions', $editSubId);
            if ($existingSub && (int)$existingSub['artist_id'] === (int)$user['id']) {
                $this->db->update('artworks', $existingSub['artwork_id'], [
                    'category_id' => $categoryId,
                    'title' => $title,
                    'medium' => $medium,
                    'year_created' => $yearCreated,
                    'dimensions' => $dimensions,
                    'classification' => $classification,
                    'edition_number' => $editionNumber,
                    'artist_attribution' => $attribution,
                    'description' => $description,
                    'provenance' => $provenance,
                    'condition_report' => $conditionReport,
                    'primary_image' => $primaryImage,
                    'currency' => $currency,
                    'estimate_low' => $estimateLow,
                    'estimate_high' => $estimateHigh,
                    'starting_bid' => $startingBid,
                    'artist_expected_price' => $artistExpectedPrice,
                    'reserve_price' => $reservePrice,
                    'status' => $artworkStatus,
                ]);

                $this->db->update('artwork_submissions', $existingSub['id'], [
                    'suggested_starting_bid' => $startingBid,
                    'artist_expected_price' => $artistExpectedPrice,
                    'reserve_price' => $reservePrice,
                    'preferred_duration_days' => $durationDays,
                    'currency' => $currency,
                    'estimate_low' => $estimateLow,
                    'estimate_high' => $estimateHigh,
                    'seller_notes' => $sellerNotes,
                    'ownership_declared' => $ownershipDeclared,
                    'copyright_confirmed' => $copyrightConfirmed,
                    'authenticity_notes' => $authenticityNotes,
                    'status' => $artworkStatus,
                ]);

                flash('success', "Submission {$existingSub['reference_code']} has been updated (" . strtoupper($artworkStatus) . ").");
                redirect('/artist/submissions');
            }
        }

        $artwork = $this->db->insert('artworks', [
            'artist_id' => (int)$user['id'],
            'category_id' => $categoryId,
            'title' => $title,
            'slug' => slugify($title . '-' . random_int(100, 999)),
            'medium' => $medium,
            'year_created' => $yearCreated,
            'dimensions' => $dimensions,
            'classification' => $classification,
            'edition_number' => $editionNumber,
            'artist_attribution' => $attribution,
            'description' => $description,
            'provenance' => $provenance,
            'condition_report' => $conditionReport,
            'primary_image' => $primaryImage,
            'currency' => $currency,
            'estimate_low' => $estimateLow,
            'estimate_high' => $estimateHigh,
            'starting_bid' => $startingBid,
            'artist_expected_price' => $artistExpectedPrice,
            'agreed_price' => null,
            'reserve_price' => $reservePrice,
            'status' => $artworkStatus,
            'featured' => false,
            'is_seeded' => false,
        ]);

        foreach ($galleryImages as $idx => $imgUrl) {
            $this->db->insert('artwork_images', [
                'artwork_id' => (int)$artwork['id'],
                'image_url' => $imgUrl,
                'is_primary' => $imgUrl === $primaryImage,
                'sort_order' => $idx + 1,
            ]);
        }

        if ($privateDocPath) {
            $this->db->insert('artwork_documents', [
                'artwork_id' => (int)$artwork['id'],
                'artist_id' => (int)$user['id'],
                'private_path' => $privateDocPath,
                'document_type' => 'Certificate of Authenticity / Provenance Dossier',
            ]);
        }

        $refCode = sprintf('AN-%s-%04d', gmdate('Y'), 1000 + (int)$artwork['id']);
        $submission = $this->db->insert('artwork_submissions', [
            'reference_code' => $refCode,
            'artwork_id' => (int)$artwork['id'],
            'artist_id' => (int)$user['id'],
            'suggested_starting_bid' => $startingBid,
            'artist_expected_price' => $artistExpectedPrice,
            'agreed_price' => null,
            'reserve_price' => $reservePrice,
            'preferred_duration_days' => $durationDays,
            'currency' => $currency,
            'estimate_low' => $estimateLow,
            'estimate_high' => $estimateHigh,
            'seller_notes' => $sellerNotes,
            'ownership_declared' => $ownershipDeclared,
            'copyright_confirmed' => $copyrightConfirmed,
            'authenticity_notes' => $authenticityNotes,
            'status' => $artworkStatus,
            'admin_feedback' => $actionMode === 'draft'
                ? 'Saved as studio draft. Submit when ready for curatorial evaluation.'
                : 'Received by the ArtAlly Curatorial Review Board. Typical evaluation time is 24–48 hours.',
            'reviewed_by' => null,
        ]);

        // Notify Artist & Chief Curator
        if ($actionMode === 'submitted') {
            $this->db->insert('notifications', [
                'user_id' => (int)$user['id'],
                'type' => 'submission_received',
                'title' => "Submission Received ({$refCode})",
                'message' => "Your artwork \"{$title}\" has been logged for specialist curatorial review.",
                'action_url' => '/artist/submissions',
                'read_at' => null,
            ]);

            $this->db->insert('notifications', [
                'user_id' => 1,
                'type' => 'submission_received',
                'title' => "New Consignment Submission: {$refCode}",
                'message' => "{$user['name']} submitted \"{$title}\" for auction evaluation.",
                'action_url' => '/admin/submissions',
                'read_at' => null,
            ]);
        }

        flash(
            'success',
            $actionMode === 'draft'
                ? "Draft \"{$title}\" saved ({$refCode})."
                : "Consignment submission {$refCode} received for curatorial review. Works are published only after specialist approval."
        );
        redirect('/artist/dashboard');
    }

    public function submissions(): void
    {
        $user = Auth::requireRole('artist');
        $submissions = $this->db->where('artwork_submissions', fn($s) => (int)$s['artist_id'] === (int)$user['id']);
        foreach ($submissions as &$sub) {
            $sub['artwork'] = $this->db->find('artworks', (int)$sub['artwork_id']) ?? [];
            $sub['auction'] = $this->db->findBy('auctions', 'artwork_id', (int)$sub['artwork_id']);
        }
        unset($sub);
        usort($submissions, fn($a, $b) => ((int)$b['id']) <=> ((int)$a['id']));

        view('artist/submissions', [
            'pageTitle' => 'Artwork Submissions & Curatorial Status — ArtAlly',
            'user' => $user,
            'submissions' => $submissions,
        ]);
    }

    public function withdrawSubmission(): void
    {
        $user = Auth::requireRole('artist');
        $subId = (int)($_POST['submission_id'] ?? 0);
        $sub = $this->db->find('artwork_submissions', $subId);

        if (!$sub || (int)$sub['artist_id'] !== (int)$user['id']) {
            flash('error', 'Submission not found.');
            redirect('/artist/submissions');
        }

        if (($sub['status'] ?? '') === 'published') {
            flash('error', 'Published lots with active auctions cannot be withdrawn without administrator intervention.');
            redirect('/artist/submissions');
        }

        $this->db->update('artwork_submissions', $subId, ['status' => 'withdrawn']);
        $this->db->update('artworks', (int)$sub['artwork_id'], ['status' => 'withdrawn']);

        flash('success', "Submission {$sub['reference_code']} has been withdrawn.");
        redirect('/artist/submissions');
    }

    /**
     * Artist accepts the Admin's Agreed Price or sends a counter-offer/message to the Admin Backend
     */
    public function respondToNegotiation(): void
    {
        $user = Auth::requireRole('artist');
        $subId = (int)($_POST['submission_id'] ?? 0);
        $responseAction = trim((string)($_POST['response_action'] ?? 'accept')); // accept or counter
        $counterPrice = (float)($_POST['counter_price'] ?? 0);
        $artistMessage = trim((string)($_POST['artist_message'] ?? ''));

        $sub = $this->db->find('artwork_submissions', $subId);
        if (!$sub || (int)$sub['artist_id'] !== (int)$user['id']) {
            flash('error', 'Submission record not found.');
            redirect('/artist/dashboard');
        }

        $artwork = $this->db->find('artworks', (int)$sub['artwork_id']) ?? [];
        $messages = is_array($sub['negotiation_messages'] ?? null) ? $sub['negotiation_messages'] : [];

        if ($responseAction === 'accept') {
            $agreed = (float)($sub['agreed_price'] ?? $artwork['agreed_price'] ?? $sub['artist_expected_price'] ?? 0);
            $messages[] = [
                'sender' => 'artist',
                'sender_name' => $user['name'],
                'text' => "✓ Artist formally ACCEPTED the House Agreed Price of " . format_money($agreed) . "." . ($artistMessage !== '' ? " Note: {$artistMessage}" : ''),
                'created_at' => gmdate('Y-m-d H:i:s'),
            ];

            $this->db->update('artwork_submissions', $subId, [
                'price_agreement_status' => 'accepted_by_artist',
                'negotiation_messages' => $messages,
            ]);
            $this->db->update('artworks', (int)$sub['artwork_id'], [
                'price_agreement_status' => 'accepted_by_artist',
            ]);

            $this->db->insert('notifications', [
                'user_id' => 1,
                'type' => 'price_accepted',
                'title' => "Artist Accepted Agreed Price: " . ($artwork['title'] ?? $sub['reference_code']),
                'message' => "{$user['name']} accepted the agreed price of " . format_money($agreed) . " for \"{$artwork['title']}\".",
                'action_url' => '/admin/dashboard#artists',
                'read_at' => null,
            ]);

            flash('success', "You have formally accepted the House Agreed Price of " . format_money($agreed) . ".");
        } else {
            $newExpected = $counterPrice > 0 ? $counterPrice : (float)($sub['artist_expected_price'] ?? 0);
            $messages[] = [
                'sender' => 'artist',
                'sender_name' => $user['name'],
                'text' => "Artist proposed counter-valuation of " . format_money($newExpected) . ": " . ($artistMessage !== '' ? $artistMessage : 'Please review my updated target price.'),
                'created_at' => gmdate('Y-m-d H:i:s'),
            ];

            $this->db->update('artwork_submissions', $subId, [
                'artist_expected_price' => $newExpected,
                'price_agreement_status' => 'counter_offered',
                'negotiation_messages' => $messages,
            ]);
            $this->db->update('artworks', (int)$sub['artwork_id'], [
                'artist_expected_price' => $newExpected,
                'price_agreement_status' => 'counter_offered',
            ]);

            $this->db->insert('notifications', [
                'user_id' => 1,
                'type' => 'price_counter',
                'title' => "Artist Counter-Offer on " . ($artwork['title'] ?? $sub['reference_code']),
                'message' => "{$user['name']} sent a counter-offer of " . format_money($newExpected) . ": {$artistMessage}",
                'action_url' => '/admin/dashboard#artists',
                'read_at' => null,
            ]);

            flash('success', "Your counter-offer (" . format_money($newExpected) . ") and note have been sent to the Administrator.");
        }

        redirect('/artist/dashboard');
    }

    /**
     * Render Printable Official Certificate of Sale & Provenance Invoice
     */
    public function viewCertificate(string $id): void
    {
        $user = Auth::requireAuth();
        $artworkId = (int)$id;
        $artwork = $this->db->find('artworks', $artworkId);

        if (!$artwork) {
            http_response_code(404);
            view('pages/404', ['pageTitle' => 'Certificate Not Found']);
            return;
        }

        $auction = $this->db->findBy('auctions', 'artwork_id', $artworkId);
        $order = $this->db->findBy('orders', 'artwork_id', $artworkId);
        $artistUser = $this->db->find('users', (int)$artwork['artist_id']) ?? [];
        $artistProfile = $this->db->findBy('artist_profiles', 'user_id', (int)$artwork['artist_id']) ?? [];
        $buyerUser = $order ? ($this->db->find('users', (int)$order['buyer_id']) ?? []) : [];

        // Allow Admin, Artist Owner, or Winning Buyer
        $isAllowed = Auth::hasRole('admin')
            || (int)$user['id'] === (int)$artwork['artist_id']
            || ($order && (int)$user['id'] === (int)$order['buyer_id']);

        if (!$isAllowed) {
            http_response_code(403);
            flash('error', 'You are not authorized to view this confidential certificate.');
            redirect('/');
        }

        view('public/certificate', [
            'pageTitle' => 'Certificate of Sale & Authenticity — ' . $artwork['title'],
            'artwork' => $artwork,
            'auction' => $auction,
            'order' => $order,
            'artistUser' => $artistUser,
            'artistProfile' => $artistProfile,
            'buyerUser' => $buyerUser,
        ]);
    }

    public function salesAndPayouts(): void
    {
        $user = Auth::requireRole('artist');
        $orders = $this->db->where('orders', fn($o) => (int)$o['artist_id'] === (int)$user['id']);
        $payouts = $this->db->where('seller_payouts', fn($p) => (int)$p['artist_id'] === (int)$user['id']);

        foreach ($orders as &$ord) {
            $ord['artwork'] = $this->db->find('artworks', (int)$ord['artwork_id']) ?? [];
        }
        unset($ord);

        view('artist/sales', [
            'pageTitle' => 'Sales Ledgers & Seller Payouts — ArtAlly',
            'user' => $user,
            'orders' => $orders,
            'payouts' => $payouts,
        ]);
    }

    private function handlePublicImageUpload(array $file, string $subFolder): ?string
    {
        $allowedMimes = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/avif' => 'avif',
            'image/bmp' => 'bmp',
        ];
        if (($file['size'] ?? 0) > 50 * 1024 * 1024) {
            return null;
        }
        $mime = '';
        if (class_exists('\finfo') && !empty($file['tmp_name']) && is_file($file['tmp_name'])) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = (string)$finfo->file($file['tmp_name']);
        } elseif (function_exists('getimagesize') && !empty($file['tmp_name']) && is_file($file['tmp_name'])) {
            $info = @getimagesize($file['tmp_name']);
            $mime = (string)($info['mime'] ?? '');
        }
        $extGuess = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!isset($allowedMimes[$mime])) {
            $map = [
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'jfif' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                'gif' => 'image/gif',
                'avif' => 'image/avif',
                'bmp' => 'image/bmp',
            ];
            $mime = $map[$extGuess] ?? 'image/jpeg';
        }
        $ext = $allowedMimes[$mime] ?? 'jpg';
        $filename = 'an_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $targetDir = PUBLIC_PATH . '/uploads/' . $subFolder;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }
        $targetPath = $targetDir . '/' . $filename;
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            return '/uploads/' . $subFolder . '/' . $filename;
        }
        return null;
    }

    private function handleBase64ImageUpload(string $dataUrl, string $subFolder): ?string
    {
        if (!str_starts_with($dataUrl, 'data:image/')) {
            return null;
        }
        if (!preg_match('/^data:image\/([a-zA-Z0-9+.-]+);base64,(.+)$/', $dataUrl, $matches)) {
            return null;
        }
        $ext = strtolower($matches[1]);
        if ($ext === 'jpeg' || $ext === 'jfif') {
            $ext = 'jpg';
        }
        if (!in_array($ext, ['jpg', 'png', 'webp', 'gif', 'avif', 'bmp'], true)) {
            $ext = 'jpg';
        }
        $decoded = base64_decode($matches[2], true);
        if ($decoded === false) {
            return null;
        }
        $filename = 'an_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $targetDir = PUBLIC_PATH . '/uploads/' . $subFolder;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }
        $targetPath = $targetDir . '/' . $filename;
        if (file_put_contents($targetPath, $decoded) !== false) {
            return '/uploads/' . $subFolder . '/' . $filename;
        }
        return null;
    }

    private function handlePrivateDocumentUpload(array $file, int $userId): ?string
    {
        $allowedExts = ['pdf', 'jpg', 'jpeg', 'png'];
        if (($file['size'] ?? 0) > 15 * 1024 * 1024) {
            return null;
        }
        $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExts, true)) {
            return null;
        }
        $filename = sprintf('coa_u%d_%s.%s', $userId, bin2hex(random_bytes(8)), $ext);
        $targetDir = STORAGE_PATH . '/private/documents';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }
        $targetPath = $targetDir . '/' . $filename;
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            return $filename;
        }
        return null;
    }
}
