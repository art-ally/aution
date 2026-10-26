<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\JsonDatabase;

class SeederService
{
    public static function seed(JsonDatabase $db): void
    {
        $db->transaction(function (JsonDatabase $db) {
            // 1. Platform Settings
            $db->insert('platform_settings', [
                'id' => 1,
                'brand_name' => 'ArtAlly',
                'tagline' => 'Art Worth Discovering. Stories Worth Collecting.',
                'commission_rate_percent' => 15.0,
                'buyer_premium_percent' => 20.0,
                'anti_sniping_enabled' => true,
                'anti_sniping_window_seconds' => 300, // 5 minutes
                'anti_sniping_extension_seconds' => 300, // extends by 5 minutes
                'default_currency' => 'USD',
                'require_email_verification_for_bidding' => true,
                'buyer_verification_code' => 'NOIR-BUYER-2026',
                'contact_email' => 'concierge@ateliernoir.art',
                'headquarters' => 'Geneva · New York · London · Paris',
            ]);

            // 2. Users (Admin, Curated Artists, Verified Collectors)
            $passwordHash = password_hash('AtelierNoir2026!', PASSWORD_DEFAULT);

            $admin = $db->insert('users', [
                'id' => 1,
                'name' => 'Margaux Vance (Chief Curator)',
                'email' => 'admin@ateliernoir.art',
                'password' => $passwordHash,
                'country' => 'Switzerland',
                'role' => 'admin',
                'roles' => ['admin', 'buyer', 'artist'],
                'email_verified_at' => gmdate('Y-m-d H:i:s', strtotime('-90 days')),
                'bidding_approved' => true,
                'status' => 'active',
                'is_seeded' => true,
            ]);

            $artistsData = [
                [
                    'id' => 2,
                    'name' => 'Soren Lindqvist',
                    'email' => 'soren@ateliernoir.art',
                    'country' => 'Sweden',
                    'city' => 'Stockholm',
                    'discipline' => 'Minimalist Oil & Architectural Canvas',
                    'years_active' => '2011 – Present',
                    'website' => 'https://sorenlindqvist.example.com',
                    'instagram' => '@soren.lindqvist.studio',
                    'portrait' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=900&q=80',
                    'bio' => 'Soren Lindqvist explores the quiet tension between Nordic architectural Brutalism and atmospheric light. Working with raw Belgian linen, natural earth pigments, and beeswax encaustic, his large-scale canvases have been acquired by private foundations across Zurich, Stockholm, and Kyoto.',
                    'statement' => 'My work investigates stillness as a physical weight—layering burnt umber, bone black, and chalk until the surface holds memory like weathered stone.',
                ],
                [
                    'id' => 3,
                    'name' => 'Elena Rostova',
                    'email' => 'elena@ateliernoir.art',
                    'country' => 'France',
                    'city' => 'Paris',
                    'discipline' => 'Contemporary Sculpture & Cast Bronze',
                    'years_active' => '2014 – Present',
                    'website' => 'https://elenarostova.example.com',
                    'instagram' => '@rostova.atelier',
                    'portrait' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=900&q=80',
                    'bio' => 'Trained at the Beaux-Arts de Paris, Elena Rostova works in lost-wax patinated bronze and carved Carrara marble. Her monolithic yet fluid forms examine human equilibrium and geological time.',
                    'statement' => 'Bronze remembers every gesture of the hand before fire makes it permanent.',
                ],
                [
                    'id' => 4,
                    'name' => 'Kenji Takahashi',
                    'email' => 'kenji@ateliernoir.art',
                    'country' => 'Japan',
                    'city' => 'Kyoto',
                    'discipline' => 'Large-Format Silver Gelatin Photography & Mixed Media',
                    'years_active' => '2008 – Present',
                    'website' => 'https://kenjitakahashi.example.com',
                    'instagram' => '@takahashi.silver',
                    'portrait' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=900&q=80',
                    'bio' => 'Using an 8x10 large-format field camera and hand-coated platinum-palladium paper, Kenji Takahashi captures nocturnal seascapes and mist-shrouded cedar forests across the Japanese archipelago.',
                    'statement' => 'In long exposures of forty minutes or more, the moving water disappears and only essence remains.',
                ],
                [
                    'id' => 5,
                    'name' => 'Clara Von Berg',
                    'email' => 'clara@ateliernoir.art',
                    'country' => 'Germany',
                    'city' => 'Berlin',
                    'discipline' => 'Abstract Expressionism & Works on Paper',
                    'years_active' => '2016 – Present',
                    'website' => 'https://claravonberg.example.com',
                    'instagram' => '@claravonberg',
                    'portrait' => 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&w=900&q=80',
                    'bio' => 'Clara Von Berg constructs richly textured triptychs and works on handmade cotton rag paper using raw charcoal, gold leaf, and oil stick. Her works balance lyrical gesture with stark chromatic restraint.',
                    'statement' => 'Every mark on paper is an irreversible dialogue between impulse and structure.',
                ],
            ];

            foreach ($artistsData as $a) {
                $db->insert('users', [
                    'id' => $a['id'],
                    'name' => $a['name'],
                    'email' => $a['email'],
                    'password' => $passwordHash,
                    'country' => $a['country'],
                    'role' => 'artist',
                    'roles' => ['artist', 'buyer'],
                    'email_verified_at' => gmdate('Y-m-d H:i:s', strtotime('-60 days')),
                    'bidding_approved' => true,
                    'status' => 'active',
                    'is_seeded' => true,
                ]);

                $db->insert('artist_profiles', [
                    'id' => $a['id'],
                    'user_id' => $a['id'],
                    'display_name' => $a['name'],
                    'slug' => slugify($a['name']),
                    'biography' => $a['bio'],
                    'artist_statement' => $a['statement'],
                    'profile_photo' => $a['portrait'],
                    'country' => $a['country'],
                    'city' => $a['city'],
                    'discipline' => $a['discipline'],
                    'years_active' => $a['years_active'],
                    'website' => $a['website'],
                    'social_instagram' => $a['instagram'],
                    'featured' => true,
                    'verified_artist' => true,
                    'portfolio_images' => [
                        $a['portrait'],
                    ],
                ]);
            }

            // Collectors / Buyers
            $buyersData = [
                [
                    'id' => 6,
                    'name' => 'Julian Sterling',
                    'email' => 'collector@ateliernoir.art',
                    'country' => 'United Kingdom',
                ],
                [
                    'id' => 7,
                    'name' => 'Aurelia De Laurentiis',
                    'email' => 'aurelia@ateliernoir.art',
                    'country' => 'Italy',
                ],
            ];

            foreach ($buyersData as $b) {
                $db->insert('users', [
                    'id' => $b['id'],
                    'name' => $b['name'],
                    'email' => $b['email'],
                    'password' => $passwordHash,
                    'country' => $b['country'],
                    'role' => 'buyer',
                    'roles' => ['buyer'],
                    'email_verified_at' => gmdate('Y-m-d H:i:s', strtotime('-45 days')),
                    'bidding_approved' => true,
                    'status' => 'active',
                    'is_seeded' => true,
                ]);

                $db->insert('buyer_profiles', [
                    'user_id' => $b['id'],
                    'collecting_focus' => 'Contemporary Minimalist & Post-War European Art',
                    'verified_payment_method' => 'Visa Infinite •••• 4829',
                    'shipping_address' => '14 Mayfair Square, London W1J 8AJ, United Kingdom',
                    'identity_verified' => true,
                ]);
            }

            // 3. Categories (All 10 required categories)
            $categories = [
                ['id' => 1, 'name' => 'Contemporary Art', 'slug' => 'contemporary-art', 'description' => 'Definitive paintings, installations, and mixed-media works created by leading living practitioners.', 'image' => 'https://images.unsplash.com/photo-1579783902614-a3fb3927b675?auto=format&fit=crop&w=1000&q=80'],
                ['id' => 2, 'name' => 'Abstract Art', 'slug' => 'abstract-art', 'description' => 'Chromatic fields, architectural minimalism, and expressive non-figurative compositions.', 'image' => 'https://images.unsplash.com/photo-1541961017774-22349e4a1262?auto=format&fit=crop&w=1000&q=80'],
                ['id' => 3, 'name' => 'Modern Art', 'slug' => 'modern-art', 'description' => 'Masterworks influenced by 20th-century avant-garde movements and formal abstraction.', 'image' => 'https://images.unsplash.com/photo-1578301978693-85fa9c0320b9?auto=format&fit=crop&w=1000&q=80'],
                ['id' => 4, 'name' => 'Photography', 'slug' => 'photography', 'description' => 'Archival silver gelatin prints, platinum-palladium editions, and large-format fine art photography.', 'image' => 'https://images.unsplash.com/photo-1513519245088-0e12902e5a38?auto=format&fit=crop&w=1000&q=80'],
                ['id' => 5, 'name' => 'Sculpture', 'slug' => 'sculpture', 'description' => 'Lost-wax cast bronzes, carved Carrara marble, and architectural steel forms.', 'image' => 'https://images.unsplash.com/photo-1544967082-d9d25d867d66?auto=format&fit=crop&w=1000&q=80'],
                ['id' => 6, 'name' => 'Paintings', 'slug' => 'paintings', 'description' => 'Oil on Belgian linen, encaustic, and pigment works of museum provenance.', 'image' => 'https://images.unsplash.com/photo-1579783900882-c0d3dad7b119?auto=format&fit=crop&w=1000&q=80'],
                ['id' => 7, 'name' => 'Works on Paper', 'slug' => 'works-on-paper', 'description' => 'Intimate charcoal studies, gouache, ink drawings, and unique monotypes on archival rag paper.', 'image' => 'https://images.unsplash.com/photo-1582555172866-f73bb12a2ab3?auto=format&fit=crop&w=1000&q=80'],
                ['id' => 8, 'name' => 'Emerging Artists', 'slug' => 'emerging-artists', 'description' => 'Curated discoveries from rising studio talent selected by the ArtAlly Curatorial Board.', 'image' => 'https://images.unsplash.com/photo-1561214115-f2f134cc4912?auto=format&fit=crop&w=1000&q=80'],
                ['id' => 9, 'name' => 'Limited Editions', 'slug' => 'limited-editions', 'description' => 'Numbered, artist-signed editions and foundry-stamped sculptural multiples.', 'image' => 'https://images.unsplash.com/photo-1513364776144-60967b0f800f?auto=format&fit=crop&w=1000&q=80'],
                ['id' => 10, 'name' => 'Mixed Media', 'slug' => 'mixed-media', 'description' => 'Interdisciplinary works combining gold leaf, raw textiles, mineral pigments, and found matter.', 'image' => 'https://images.unsplash.com/photo-1549887534-1541e9326642?auto=format&fit=crop&w=1000&q=80'],
            ];

            foreach ($categories as $cat) {
                $db->insert('categories', $cat);
            }

            // 4. Artworks, Submissions & Live/Upcoming Auctions
            $artworks = [
                [
                    'id' => 1,
                    'artist_id' => 2,
                    'category_id' => 2,
                    'title' => 'Nocturne in Burnt Umber IV',
                    'slug' => 'nocturne-in-burnt-umber-iv',
                    'medium' => 'Oil, raw umber pigment, and cold wax on Belgian linen',
                    'year_created' => 2025,
                    'dimensions' => '180 × 140 × 4.5 cm (70.8 × 55.1 in)',
                    'classification' => 'Original Unique Work',
                    'edition_number' => 'Unique (1/1)',
                    'artist_attribution' => 'Signed, titled, and dated on verso by Soren Lindqvist',
                    'description' => 'Executed in the artist’s Stockholm studio during the winter solstice, "Nocturne in Burnt Umber IV" exemplifies Soren Lindqvist’s command of surface density and tonal silence. Built up over forty-two strata of natural mineral pigments suspended in cold beeswax and linseed oil, the work absorbs and reflects ambient light with architectural gravitas.',
                    'provenance' => 'Acquired directly from the studio of the artist, Stockholm, 2026. Accompanied by a hand-signed Certificate of Authenticity registered in the ArtAlly Archive.',
                    'condition_report' => 'In pristine studio condition. Stretched over heavy museum-grade cedar stretcher bars; unframed as intended by the artist with finished gallery edges.',
                    'primary_image' => 'https://images.unsplash.com/photo-1541961017774-22349e4a1262?auto=format&fit=crop&w=1400&q=85',
                    'gallery_images' => [
                        'https://images.unsplash.com/photo-1541961017774-22349e4a1262?auto=format&fit=crop&w=1400&q=85',
                        'https://images.unsplash.com/photo-1579783902614-a3fb3927b675?auto=format&fit=crop&w=1200&q=80',
                        'https://images.unsplash.com/photo-1513519245088-0e12902e5a38?auto=format&fit=crop&w=1200&q=80',
                    ],
                    'estimate_low' => 18000,
                    'estimate_high' => 26000,
                    'starting_bid' => 12000,
                    'current_bid' => 16500,
                    'reserve_price' => 15000,
                    'bid_count' => 6,
                    'status' => 'published',
                    'featured' => true,
                    'auction_status' => 'active',
                    'auction_title' => 'Evening Sale: Nordic & European Minimalism',
                    'lot_number' => 'LOT 01',
                    'starts_at' => gmdate('Y-m-d H:i:s', strtotime('-3 days')),
                    'ends_at' => gmdate('Y-m-d H:i:s', strtotime('+2 days +6 hours')),
                ],
                [
                    'id' => 2,
                    'artist_id' => 3,
                    'category_id' => 5,
                    'title' => 'Equilibrium Study No. IX (Patinated Bronze)',
                    'slug' => 'equilibrium-study-no-ix-patinated-bronze',
                    'medium' => 'Lost-wax cast bronze with deep ferric nitrate patina on Belgian black marble plinth',
                    'year_created' => 2025,
                    'dimensions' => '84 × 42 × 36 cm (33.1 × 16.5 × 14.2 in)',
                    'classification' => 'Limited Edition',
                    'edition_number' => 'Edition 2 of 5 + 1 AP',
                    'artist_attribution' => 'Incised with artist monogram, foundry mark (Fonderie Coubertin), and numbered 2/5',
                    'description' => 'Cast at the historic Fonderie de Coubertin outside Paris, Elena Rostova’s "Equilibrium Study No. IX" balances two cantilevered organic volumes along a razor-thin fulcrum. The warm, hand-rubbed bronze patina reveals subtle golden undertones beneath a velvety obsidian skin.',
                    'provenance' => 'Fonderie de Coubertin, Saint-Rémy-lès-Chevreuse; Private Studio Archive of Elena Rostova, Paris.',
                    'condition_report' => 'Mint foundry condition. Natural mineral wax coating applied; rests securely on bespoke Nero Belga marble base.',
                    'primary_image' => 'https://images.unsplash.com/photo-1544967082-d9d25d867d66?auto=format&fit=crop&w=1400&q=85',
                    'gallery_images' => [
                        'https://images.unsplash.com/photo-1544967082-d9d25d867d66?auto=format&fit=crop&w=1400&q=85',
                        'https://images.unsplash.com/photo-1578301978693-85fa9c0320b9?auto=format&fit=crop&w=1200&q=80',
                    ],
                    'estimate_low' => 24000,
                    'estimate_high' => 35000,
                    'starting_bid' => 18000,
                    'current_bid' => 24500,
                    'reserve_price' => 22000,
                    'bid_count' => 8,
                    'status' => 'published',
                    'featured' => true,
                    'auction_status' => 'active',
                    'auction_title' => 'Matter & Form: Contemporary Sculpture',
                    'lot_number' => 'LOT 02',
                    'starts_at' => gmdate('Y-m-d H:i:s', strtotime('-4 days')),
                    'ends_at' => gmdate('Y-m-d H:i:s', strtotime('+14 hours +25 minutes')),
                ],
                [
                    'id' => 3,
                    'artist_id' => 4,
                    'category_id' => 4,
                    'title' => 'Sea of Japan, Winter Solstice (42-Minute Exposure)',
                    'slug' => 'sea-of-japan-winter-solstice-42-minute-exposure',
                    'medium' => 'Hand-coated platinum-palladium print on archival Japanese Gampi paper',
                    'year_created' => 2024,
                    'dimensions' => '110 × 140 cm (43.3 × 55.1 in)',
                    'classification' => 'Limited Edition',
                    'edition_number' => 'Edition 1 of 3',
                    'artist_attribution' => 'Signed, numbered, and stamped in red cinnabar seal by Kenji Takahashi on recto margin',
                    'description' => 'Photographed along the rugged coast of Toyama Prefecture during a midnight snowstorm, this monumental contact print from an 8x10 negative reduces sea and sky into two luminous bands of silver and charcoal.',
                    'provenance' => 'Direct from the artist’s darkroom in Kyoto. Exhibited at Kyotographie International Photography Festival, 2025.',
                    'condition_report' => 'Museum mounted using conservation hinges in a custom charred cedar (Shou Sugi Ban) frame behind anti-reflective museum glass.',
                    'primary_image' => 'https://images.unsplash.com/photo-1513519245088-0e12902e5a38?auto=format&fit=crop&w=1400&q=85',
                    'gallery_images' => [
                        'https://images.unsplash.com/photo-1513519245088-0e12902e5a38?auto=format&fit=crop&w=1400&q=85',
                    ],
                    'estimate_low' => 8500,
                    'estimate_high' => 12000,
                    'starting_bid' => 6000,
                    'current_bid' => 9250,
                    'reserve_price' => 8000,
                    'bid_count' => 5,
                    'status' => 'published',
                    'featured' => true,
                    'auction_status' => 'active',
                    'auction_title' => 'Light & Shadow: Fine Art Photography',
                    'lot_number' => 'LOT 03',
                    'starts_at' => gmdate('Y-m-d H:i:s', strtotime('-2 days')),
                    'ends_at' => gmdate('Y-m-d H:i:s', strtotime('+3 days +19 hours')),
                ],
                [
                    'id' => 4,
                    'artist_id' => 5,
                    'category_id' => 1,
                    'title' => 'Cartography of Silence (Triptych)',
                    'slug' => 'cartography-of-silence-triptych',
                    'medium' => 'Charcoal, 24k gold leaf, bone pigment, and oil stick on heavy cotton canvas',
                    'year_created' => 2026,
                    'dimensions' => '160 × 240 cm overall (63 × 94.5 in)',
                    'classification' => 'Original Unique Work',
                    'edition_number' => 'Unique (1/1)',
                    'artist_attribution' => 'Signed and dated on verso of each panel by Clara Von Berg',
                    'description' => 'An expansive three-panel composition exploring architectural fractures illuminated by delicate seams of 24-karat gold leaf. Inspired by Kintsugi philosophy and mid-century European abstraction.',
                    'provenance' => 'Atelier Clara Von Berg, Berlin. Selected by the ArtAlly Curatorial Committee for the Autumn Contemporary Sale.',
                    'condition_report' => 'Excellent original condition. Framed in slim dark bronze float frames.',
                    'primary_image' => 'https://images.unsplash.com/photo-1579783902614-a3fb3927b675?auto=format&fit=crop&w=1400&q=85',
                    'gallery_images' => [
                        'https://images.unsplash.com/photo-1579783902614-a3fb3927b675?auto=format&fit=crop&w=1400&q=85',
                    ],
                    'estimate_low' => 15000,
                    'estimate_high' => 22000,
                    'starting_bid' => 10000,
                    'current_bid' => 14000,
                    'reserve_price' => 12500,
                    'bid_count' => 4,
                    'status' => 'published',
                    'featured' => true,
                    'auction_status' => 'active',
                    'auction_title' => 'Contemporary Voices: Berlin & Paris',
                    'lot_number' => 'LOT 04',
                    'starts_at' => gmdate('Y-m-d H:i:s', strtotime('-1 day')),
                    'ends_at' => gmdate('Y-m-d H:i:s', strtotime('+4 days +11 hours')),
                ],
                [
                    'id' => 5,
                    'artist_id' => 2,
                    'category_id' => 6,
                    'title' => 'Monolith Study in Raw Sienna',
                    'slug' => 'monolith-study-in-raw-sienna',
                    'medium' => 'Oil and marble dust on linen',
                    'year_created' => 2025,
                    'dimensions' => '150 × 120 cm (59 × 47.2 in)',
                    'classification' => 'Original Unique Work',
                    'edition_number' => 'Unique (1/1)',
                    'artist_attribution' => 'Signed on verso by Soren Lindqvist',
                    'description' => 'Richly textured architectural field painting incorporating finely ground Carrara marble dust and raw Italian sienna earth.',
                    'provenance' => 'Direct from the artist’s studio, Stockholm.',
                    'condition_report' => 'Pristine condition.',
                    'primary_image' => 'https://images.unsplash.com/photo-1579783900882-c0d3dad7b119?auto=format&fit=crop&w=1400&q=85',
                    'gallery_images' => [
                        'https://images.unsplash.com/photo-1579783900882-c0d3dad7b119?auto=format&fit=crop&w=1400&q=85',
                    ],
                    'estimate_low' => 14000,
                    'estimate_high' => 19000,
                    'starting_bid' => 9500,
                    'current_bid' => 11500,
                    'reserve_price' => 11000,
                    'bid_count' => 3,
                    'status' => 'published',
                    'featured' => false,
                    'auction_status' => 'active',
                    'auction_title' => 'Evening Sale: Nordic & European Minimalism',
                    'lot_number' => 'LOT 05',
                    'starts_at' => gmdate('Y-m-d H:i:s', strtotime('-2 days')),
                    'ends_at' => gmdate('Y-m-d H:i:s', strtotime('+1 day +8 hours')),
                ],
                [
                    'id' => 6,
                    'artist_id' => 5,
                    'category_id' => 7,
                    'title' => 'Etude sur Papier d’Arches No. 14',
                    'slug' => 'etude-sur-papier-darches-no-14',
                    'medium' => 'Sumi ink, raw pigment, and graphite on 640gsm Arches rag paper',
                    'year_created' => 2026,
                    'dimensions' => '76 × 56 cm (29.9 × 22 in)',
                    'classification' => 'Original Unique Work',
                    'edition_number' => 'Unique (1/1)',
                    'artist_attribution' => 'Signed lower right in pencil by Clara Von Berg',
                    'description' => 'An intimate yet commanding work on heavy deckle-edged cotton paper demonstrating remarkable restraint and calligraphic tension.',
                    'provenance' => 'Studio of the artist, Berlin.',
                    'condition_report' => 'Float-mounted in museum walnut frame.',
                    'primary_image' => 'https://images.unsplash.com/photo-1582555172866-f73bb12a2ab3?auto=format&fit=crop&w=1400&q=85',
                    'gallery_images' => [
                        'https://images.unsplash.com/photo-1582555172866-f73bb12a2ab3?auto=format&fit=crop&w=1400&q=85',
                    ],
                    'estimate_low' => 4500,
                    'estimate_high' => 6500,
                    'starting_bid' => 3200,
                    'current_bid' => 4700,
                    'reserve_price' => 4000,
                    'bid_count' => 5,
                    'status' => 'published',
                    'featured' => true,
                    'auction_status' => 'active',
                    'auction_title' => 'Works on Paper & Intimate Studies',
                    'lot_number' => 'LOT 06',
                    'starts_at' => gmdate('Y-m-d H:i:s', strtotime('-3 days')),
                    'ends_at' => gmdate('Y-m-d H:i:s', strtotime('+5 days +3 hours')),
                ],
                // Upcoming Auction Lot
                [
                    'id' => 7,
                    'artist_id' => 3,
                    'category_id' => 3,
                    'title' => 'travertine Torso II (Monumental Scale)',
                    'slug' => 'travertine-torso-ii-monumental-scale',
                    'medium' => 'Hand-carved Roman Travertine marble',
                    'year_created' => 2026,
                    'dimensions' => '145 × 58 × 48 cm (57.1 × 22.8 × 18.9 in)',
                    'classification' => 'Original Unique Work',
                    'edition_number' => 'Unique (1/1)',
                    'artist_attribution' => 'Carved monogram at base by Elena Rostova',
                    'description' => 'Carved from a single quarry block of porous Roman travertine in Pietrasanta, Italy. Reserved for our upcoming flagship Geneva Evening Auction.',
                    'provenance' => 'Quarried and carved in Pietrasanta, Italy; Studio of Elena Rostova.',
                    'condition_report' => 'Structural perfection; accompanied by custom steel seismic plinth.',
                    'primary_image' => 'https://images.unsplash.com/photo-1578301978693-85fa9c0320b9?auto=format&fit=crop&w=1400&q=85',
                    'gallery_images' => [
                        'https://images.unsplash.com/photo-1578301978693-85fa9c0320b9?auto=format&fit=crop&w=1400&q=85',
                    ],
                    'estimate_low' => 38000,
                    'estimate_high' => 55000,
                    'starting_bid' => 28000,
                    'current_bid' => 28000,
                    'reserve_price' => 35000,
                    'bid_count' => 0,
                    'status' => 'published',
                    'featured' => true,
                    'auction_status' => 'upcoming',
                    'auction_title' => 'Geneva Flagship Evening Sale: Masterworks of Form',
                    'lot_number' => 'LOT 07',
                    'starts_at' => gmdate('Y-m-d H:i:s', strtotime('+4 days')),
                    'ends_at' => gmdate('Y-m-d H:i:s', strtotime('+14 days')),
                ],
                // Pending Review Submission for Admin & Artist testing
                [
                    'id' => 8,
                    'artist_id' => 2,
                    'category_id' => 10,
                    'title' => 'Study for Winter Solstice (Encaustic Panel)',
                    'slug' => 'study-for-winter-solstice-encaustic-panel',
                    'medium' => 'Encaustic beeswax, ash, and raw linen mounted on Baltic birch panel',
                    'year_created' => 2026,
                    'dimensions' => '90 × 70 cm (35.4 × 27.5 in)',
                    'classification' => 'Original Unique Work',
                    'edition_number' => 'Unique (1/1)',
                    'artist_attribution' => 'Signed on verso by Soren Lindqvist',
                    'description' => 'Submitted for curatorial verification ahead of the Winter Contemporary sale.',
                    'provenance' => 'Direct from the artist’s studio, Stockholm.',
                    'condition_report' => 'Unblemished studio state.',
                    'primary_image' => 'https://images.unsplash.com/photo-1549887534-1541e9326642?auto=format&fit=crop&w=1400&q=85',
                    'gallery_images' => [
                        'https://images.unsplash.com/photo-1549887534-1541e9326642?auto=format&fit=crop&w=1400&q=85',
                    ],
                    'estimate_low' => 7000,
                    'estimate_high' => 10000,
                    'starting_bid' => 5000,
                    'current_bid' => 5000,
                    'reserve_price' => 6500,
                    'bid_count' => 0,
                    'status' => 'under_review',
                    'featured' => false,
                    'auction_status' => 'unscheduled',
                    'auction_title' => 'Pending Curatorial Assignment',
                    'lot_number' => 'PENDING',
                    'starts_at' => gmdate('Y-m-d H:i:s', strtotime('+7 days')),
                    'ends_at' => gmdate('Y-m-d H:i:s', strtotime('+14 days')),
                ],
            ];

            foreach ($artworks as $art) {
                $db->insert('artworks', [
                    'id' => $art['id'],
                    'artist_id' => $art['artist_id'],
                    'category_id' => $art['category_id'],
                    'title' => $art['title'],
                    'slug' => $art['slug'],
                    'medium' => $art['medium'],
                    'year_created' => $art['year_created'],
                    'dimensions' => $art['dimensions'],
                    'classification' => $art['classification'],
                    'edition_number' => $art['edition_number'],
                    'artist_attribution' => $art['artist_attribution'],
                    'description' => $art['description'],
                    'provenance' => $art['provenance'],
                    'condition_report' => $art['condition_report'],
                    'primary_image' => $art['primary_image'],
                    'currency' => 'USD',
                    'estimate_low' => $art['estimate_low'],
                    'estimate_high' => $art['estimate_high'],
                    'starting_bid' => $art['starting_bid'],
                    'reserve_price' => $art['reserve_price'],
                    'status' => $art['status'],
                    'featured' => $art['featured'],
                    'is_seeded' => true,
                ]);

                foreach ($art['gallery_images'] as $idx => $imgUrl) {
                    $db->insert('artwork_images', [
                        'artwork_id' => $art['id'],
                        'image_url' => $imgUrl,
                        'is_primary' => $idx === 0,
                        'sort_order' => $idx + 1,
                    ]);
                }

                $db->insert('artwork_submissions', [
                    'id' => $art['id'],
                    'reference_code' => sprintf('AN-2026-%04d', 1000 + $art['id']),
                    'artwork_id' => $art['id'],
                    'artist_id' => $art['artist_id'],
                    'suggested_starting_bid' => $art['starting_bid'],
                    'reserve_price' => $art['reserve_price'],
                    'preferred_duration_days' => 7,
                    'currency' => 'USD',
                    'estimate_low' => $art['estimate_low'],
                    'estimate_high' => $art['estimate_high'],
                    'seller_notes' => 'Direct from studio archive; museum crate prepared for international air freight.',
                    'ownership_declared' => true,
                    'copyright_confirmed' => true,
                    'authenticity_notes' => 'Signed Certificate of Authenticity on archival rag paper included.',
                    'status' => $art['status'] === 'published' ? 'published' : 'under_review',
                    'admin_feedback' => $art['status'] === 'published'
                        ? 'Approved by Chief Curator Margaux Vance. Assigned to flagship timed auction catalogue.'
                        : 'Under review by our European Specialist Department.',
                    'reviewed_by' => $art['status'] === 'published' ? 1 : null,
                ]);

                if ($art['status'] === 'published') {
                    $db->insert('auctions', [
                        'id' => $art['id'],
                        'artwork_id' => $art['id'],
                        'artist_id' => $art['artist_id'],
                        'title' => $art['auction_title'],
                        'lot_number' => $art['lot_number'],
                        'currency' => 'USD',
                        'starting_bid' => $art['starting_bid'],
                        'current_bid' => $art['current_bid'],
                        'reserve_price' => $art['reserve_price'],
                        'reserve_met' => $art['current_bid'] >= $art['reserve_price'],
                        'estimate_low' => $art['estimate_low'],
                        'estimate_high' => $art['estimate_high'],
                        'bid_count' => $art['bid_count'],
                        'highest_bidder_id' => $art['bid_count'] > 0 ? 6 : null,
                        'starts_at' => $art['starts_at'],
                        'ends_at' => $art['ends_at'],
                        'status' => $art['auction_status'], // active, upcoming, closed_sold, closed_unsold
                        'anti_sniping_enabled' => true,
                        'featured' => $art['featured'],
                        'is_seeded' => true,
                    ]);
                }
            }

            // 5. Seed Initial Immutable Bid History for Active Auctions
            $seedBids = [
                ['auction_id' => 1, 'artwork_id' => 1, 'user_id' => 7, 'amount' => 12000, 'paddle' => 'PB-4092', 'time_offset' => '-48 hours'],
                ['auction_id' => 1, 'artwork_id' => 1, 'user_id' => 6, 'amount' => 13000, 'paddle' => 'PB-8814', 'time_offset' => '-36 hours'],
                ['auction_id' => 1, 'artwork_id' => 1, 'user_id' => 7, 'amount' => 14000, 'paddle' => 'PB-4092', 'time_offset' => '-24 hours'],
                ['auction_id' => 1, 'artwork_id' => 1, 'user_id' => 6, 'amount' => 15000, 'paddle' => 'PB-8814', 'time_offset' => '-18 hours'],
                ['auction_id' => 1, 'artwork_id' => 1, 'user_id' => 7, 'amount' => 15500, 'paddle' => 'PB-4092', 'time_offset' => '-8 hours'],
                ['auction_id' => 1, 'artwork_id' => 1, 'user_id' => 6, 'amount' => 16500, 'paddle' => 'PB-8814', 'time_offset' => '-2 hours'],

                ['auction_id' => 2, 'artwork_id' => 2, 'user_id' => 7, 'amount' => 22000, 'paddle' => 'PB-4092', 'time_offset' => '-12 hours'],
                ['auction_id' => 2, 'artwork_id' => 2, 'user_id' => 6, 'amount' => 24500, 'paddle' => 'PB-8814', 'time_offset' => '-3 hours'],

                ['auction_id' => 3, 'artwork_id' => 3, 'user_id' => 6, 'amount' => 9250, 'paddle' => 'PB-8814', 'time_offset' => '-5 hours'],
                ['auction_id' => 4, 'artwork_id' => 4, 'user_id' => 7, 'amount' => 14000, 'paddle' => 'PB-4092', 'time_offset' => '-4 hours'],
            ];

            foreach ($seedBids as $b) {
                $db->insert('bids', [
                    'auction_id' => $b['auction_id'],
                    'artwork_id' => $b['artwork_id'],
                    'user_id' => $b['user_id'],
                    'paddle_number' => $b['paddle'],
                    'amount' => $b['amount'],
                    'currency' => 'USD',
                    'ip_hash' => hash('sha256', '127.0.0.1'),
                    'is_seeded' => true,
                    'created_at' => gmdate('Y-m-d H:i:s', strtotime($b['time_offset'])),
                ]);
            }

            // 6. Editorial Journal Articles
            $articles = [
                [
                    'id' => 1,
                    'title' => 'Inside the Stockholm Studio of Soren Lindqvist: Architecture of Stillness',
                    'slug' => 'inside-the-stockholm-studio-of-soren-lindqvist',
                    'category' => 'Studio Visit',
                    'author' => 'Margaux Vance, Chief Curator',
                    'excerpt' => 'How raw Belgian linen, volcanic earth pigments, and cold beeswax are redefining contemporary Nordic minimalism in private European collections.',
                    'image' => 'https://images.unsplash.com/photo-1513694203232-719a280e022f?auto=format&fit=crop&w=1200&q=80',
                    'reading_time' => '6 min read',
                    'published_at' => gmdate('Y-m-d H:i:s', strtotime('-5 days')),
                    'body' => "In a former 19th-century maritime rope-making warehouse overlooking Stockholm’s Saltsjön bay, Soren Lindqvist works in near-total silence. No artificial overhead lighting touches his canvases; instead, northern daylight filters through tall industrial casements, shifting subtly from cool slate at dawn to deep amber by mid-afternoon.\n\n\"When collectors live with a painting for thirty years, the work must not shout,\" Lindqvist reflects while grinding raw Italian umber into cold beeswax. \"It must hold space the way a gothic cathedral wall holds light.\"\n\nEach canvas undergoes up to forty distinct applications of mineral pigment, marble dust, and oil, cured over several months before the artist signs the verso and releases the work for curatorial inspection.",
                ],
                [
                    'id' => 2,
                    'title' => 'The Collector’s Guide to Provenance, Reserve Prices, and Timed Auctions',
                    'slug' => 'collectors-guide-to-provenance-and-timed-auctions',
                    'category' => 'Collecting Guide',
                    'author' => 'Dr. Henri De Vries, Head of Valuation',
                    'excerpt' => 'Understanding primary-market studio provenance, Certificates of Authenticity, and how ArtAlly’s anti-sniping protection ensures transparent price discovery.',
                    'image' => 'https://images.unsplash.com/photo-1577720580479-7d839d829c73?auto=format&fit=crop&w=1200&q=80',
                    'reading_time' => '8 min read',
                    'published_at' => gmdate('Y-m-d H:i:s', strtotime('-12 days')),
                    'body' => "Acquiring original fine art directly from vetted living artists or distinguished private collections requires rigorous attention to three pillars: documented provenance, physical condition, and transparent bidding mechanics.\n\nAt ArtAlly, every submitted work is first evaluated by our specialist committee prior to catalogue publication. Unlike open classified platforms, works never appear publicly without verified attribution, high-resolution condition reporting, and agreed reserve parameters.\n\nFurthermore, our server-enforced timed auction engine incorporates institutional anti-sniping rules: any qualifying bid placed within the final five minutes automatically extends the closing bell by an additional five minutes, ensuring every collector has a fair opportunity to respond.",
                ],
                [
                    'id' => 3,
                    'title' => 'Lost-Wax Bronze in the 21st Century: A Conversation with Elena Rostova',
                    'slug' => 'lost-wax-bronze-conversation-with-elena-rostova',
                    'category' => 'Artist Interview',
                    'author' => 'Claire Beaumont, Paris Correspondent',
                    'excerpt' => 'From molten bronze at 1,150°C to ferric nitrate patination—why contemporary sculptors are returning to historic French foundries.',
                    'image' => 'https://images.unsplash.com/photo-1544967082-d9d25d867d66?auto=format&fit=crop&w=1200&q=80',
                    'reading_time' => '5 min read',
                    'published_at' => gmdate('Y-m-d H:i:s', strtotime('-19 days')),
                    'body' => "Standing beside the crucible pits at Fonderie de Coubertin, Elena Rostova inspects the wax model of 'Equilibrium Study No. IX'. Each edition of five requires six weeks of investment casting, chasing, and chemical patination.\n\n\"Digital fabrication can replicate geometry, but it cannot replicate the tension of molten metal cooling against refractory clay,\" Rostova explains. That tactile permanence is precisely why sculptural editions continue to anchor serious international collections.",
                ],
            ];

            foreach ($articles as $article) {
                $db->insert('editorial_articles', $article);
            }

            // 7. Sample Completed Order, Commission & Payout Record (for realistic dashboard inspection)
            $db->insert('orders', [
                'id' => 1,
                'order_number' => 'ORD-2026-9041',
                'auction_id' => 1,
                'artwork_id' => 5,
                'buyer_id' => 6,
                'artist_id' => 2,
                'hammer_price' => 14500.00,
                'buyer_premium' => 2900.00,
                'shipping_fee' => 450.00,
                'total_amount' => 17850.00,
                'seller_commission_rate' => 15.0,
                'seller_commission_amount' => 2175.00,
                'net_artist_payout' => 12325.00,
                'currency' => 'USD',
                'payment_status' => 'paid', // pending, paid, failed, refunded, cancelled
                'fulfillment_status' => 'delivered', // awaiting_payment, preparing_crate, in_transit, delivered
                'payment_deadline' => gmdate('Y-m-d H:i:s', strtotime('+5 days')),
                'paid_at' => gmdate('Y-m-d H:i:s', strtotime('-10 days')),
                'is_seeded' => true,
            ]);

            $db->insert('payments', [
                'id' => 1,
                'order_id' => 1,
                'buyer_id' => 6,
                'provider' => 'stripe_elements',
                'transaction_reference' => 'pi_3Q9xAtelierNoirSeeded9041',
                'amount' => 17850.00,
                'currency' => 'USD',
                'status' => 'paid',
                'is_seeded' => true,
            ]);

            $db->insert('commissions', [
                'id' => 1,
                'order_id' => 1,
                'artist_id' => 2,
                'hammer_price' => 14500.00,
                'commission_rate_percent' => 15.0,
                'commission_amount' => 2175.00,
                'buyer_premium_amount' => 2900.00,
                'currency' => 'USD',
            ]);

            $db->insert('seller_payouts', [
                'id' => 1,
                'order_id' => 1,
                'artist_id' => 2,
                'gross_sale_amount' => 14500.00,
                'commission_deducted' => 2175.00,
                'net_payout_amount' => 12325.00,
                'currency' => 'USD',
                'status' => 'completed', // pending, processing, completed, on_hold
                'payout_reference' => 'PO-CH-884120',
                'approved_by' => 1,
                'paid_at' => gmdate('Y-m-d H:i:s', strtotime('-7 days')),
                'is_seeded' => true,
            ]);

            // 8. Initial Notifications
            $db->insert('notifications', [
                'user_id' => 2,
                'type' => 'auction_published',
                'title' => 'Lot Published: Nocturne in Burnt Umber IV',
                'message' => 'Your artwork "Nocturne in Burnt Umber IV" has been approved by the Curatorial Committee and is live in Evening Sale: Nordic & European Minimalism.',
                'action_url' => '/artworks/nocturne-in-burnt-umber-iv',
                'read_at' => null,
            ]);

            $db->insert('notifications', [
                'user_id' => 6,
                'type' => 'bid_confirmed',
                'title' => 'Highest Bidder: Nocturne in Burnt Umber IV',
                'message' => 'Your bid of $16,500 on Lot 01 "Nocturne in Burnt Umber IV" is currently the leading bid.',
                'action_url' => '/artworks/nocturne-in-burnt-umber-iv',
                'read_at' => null,
            ]);

            $db->insert('notifications', [
                'user_id' => 1,
                'type' => 'submission_received',
                'title' => 'New Artwork Submission Pending Review',
                'message' => 'Soren Lindqvist submitted "Study for Winter Solstice (Encaustic Panel)" (Ref: AN-2026-1008) for curatorial approval.',
                'action_url' => '/admin/submissions',
                'read_at' => null,
            ]);

            // 9. Initial Audit Log
            $db->insert('audit_logs', [
                'user_id' => 1,
                'actor_name' => 'Margaux Vance (Chief Curator)',
                'action' => 'PLATFORM_INITIALIZED',
                'target_type' => 'system',
                'target_id' => 1,
                'details' => 'Initialized ArtAlly curatorial catalogue, auction schedules, and commission policies.',
                'ip_address' => '127.0.0.1',
            ]);
        });
    }
}
