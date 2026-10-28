const express = require('express');
const path = require('path');
const fs = require('fs');
const crypto = require('crypto');
const cookieParser = require('cookie-parser');
const bcrypt = require('bcryptjs');
const multer = require('multer');

const { DatabaseEngine, formatUtcNow } = require('./db');
const helpers = require('./helpers');
const { AuctionEngineService } = require('./auctionEngine');
const { renderView } = require('./templateEngine');

const app = express();
const IS_VERCEL = Boolean(process.env.VERCEL);
const PUBLIC_PATH = path.join(__dirname, '..', 'public');
const UPLOAD_ROOT = IS_VERCEL ? path.join('/tmp', 'uploads') : path.join(PUBLIC_PATH, 'uploads');
const PRIVATE_DOC_ROOT = IS_VERCEL
  ? path.join('/tmp', 'private_documents')
  : path.join(__dirname, '..', 'storage', 'private', 'documents');

const SESSION_SECRET = process.env.SESSION_SECRET || 'artally-institutional-secret-key-2026';
const SESSION_COOKIE = 'artally_session';

// Configure multer in-memory storage so we can validate & save uploads identically on local or Vercel
const upload = multer({
  storage: multer.memoryStorage(),
  limits: { fileSize: 50 * 1024 * 1024 },
});

app.disable('x-powered-by');
app.use((req, res, next) => {
  res.setHeader('X-Content-Type-Options', 'nosniff');
  res.setHeader('X-Frame-Options', 'SAMEORIGIN');
  res.setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
  res.setHeader('X-XSS-Protection', '1; mode=block');
  next();
});
app.use(express.urlencoded({ extended: true, limit: '50mb' }));
app.use(express.json({ limit: '50mb' }));
app.use(cookieParser());

// Serve static assets from public/ and /tmp/uploads (when on Vercel)
if (IS_VERCEL) {
  app.use('/uploads', express.static(UPLOAD_ROOT));
}
app.use(express.static(PUBLIC_PATH, { index: false }));
app.get('/favicon.ico', (req, res) => {
  res.sendFile(path.join(PUBLIC_PATH, 'favicon.svg'), {
    headers: { 'Content-Type': 'image/svg+xml' },
  });
});

// --- Session Cookie Helpers (Stateless & Serverless-ready for Vercel) ---
function decodeSessionCookie(rawCookie) {
  if (!rawCookie || typeof rawCookie !== 'string') return {};
  const parts = rawCookie.split('.');
  if (parts.length !== 2) return {};
  const [payloadB64, sig] = parts;
  const expectedSig = crypto
    .createHmac('sha256', SESSION_SECRET)
    .update(payloadB64)
    .digest('base64url');
  const sigBuf = Buffer.from(String(sig));
  const expectedBuf = Buffer.from(String(expectedSig));
  if (sigBuf.length !== expectedBuf.length || !crypto.timingSafeEqual(sigBuf, expectedBuf)) {
    return {};
  }
  try {
    return JSON.parse(Buffer.from(payloadB64, 'base64url').toString('utf8')) || {};
  } catch (_) {
    return {};
  }
}

function encodeSessionCookie(sessionObj) {
  const payloadB64 = Buffer.from(JSON.stringify(sessionObj), 'utf8').toString('base64url');
  const sig = crypto
    .createHmac('sha256', SESSION_SECRET)
    .update(payloadB64)
    .digest('base64url');
  return `${payloadB64}.${sig}`;
}

function verifyPassword(plainPassword, storedHash) {
  if (!storedHash) return false;
  // Convert PHP $2y$ bcrypt prefix to $2a$ for bcryptjs compatibility
  const normalizedHash = String(storedHash).replace(/^\$2y\$/, '$2a$');
  return bcrypt.compareSync(String(plainPassword), normalizedHash);
}

function hashPassword(plainPassword) {
  return bcrypt.hashSync(String(plainPassword), 10);
}

// --- Request Context & Session Middleware ---
app.use(async (req, res, next) => {
  const db = DatabaseEngine.getInstance();
  await db.syncFromSupabaseIfConfigured();

  try {
    AuctionEngineService.synchronizeAuctions(db);
  } catch (err) {
    console.error('[ArtAlly Auction Sync]', err.message);
  }

  const session = decodeSessionCookie(req.cookies[SESSION_COOKIE]);
  if (!session.csrf_token) {
    session.csrf_token = crypto.randomBytes(24).toString('hex');
  }
  if (!session.flash) {
    session.flash = {};
  }
  if (!Array.isArray(session.guest_watchlist)) {
    session.guest_watchlist = [];
  }

  req.sessionData = session;

  const saveSession = () => {
    const isSecureCookie =
      IS_VERCEL ||
      process.env.NODE_ENV === 'production' ||
      req.secure ||
      req.get('x-forwarded-proto') === 'https';
    res.cookie(SESSION_COOKIE, encodeSessionCookie(req.sessionData), {
      httpOnly: true,
      secure: Boolean(isSecureCookie),
      sameSite: 'lax',
      path: '/',
      maxAge: 7 * 24 * 60 * 60 * 1000,
    });
  };

  req.setFlash = (key, message) => {
    if (!req.sessionData.flash) req.sessionData.flash = {};
    req.sessionData.flash[key] = message;
    saveSession();
  };

  req.loginUser = (user) => {
    const uid = Number(user.id);
    if ((user.role || '') === 'admin' && req.path && req.path.startsWith('/admin')) {
      req.sessionData.admin_user_id = uid;
      if (!req.sessionData.user_id) {
        req.sessionData.user_id = uid;
        req.sessionData.user_role = user.role || 'admin';
      }
    } else {
      req.sessionData.user_id = uid;
      req.sessionData.user_role = user.role || 'buyer';
      if (helpers.userHasRole(user, 'admin')) {
        req.sessionData.admin_user_id = uid;
      }
    }
    saveSession();
  };

  req.logoutUser = () => {
    delete req.sessionData.user_id;
    delete req.sessionData.user_role;
    delete req.sessionData.admin_user_id;
    saveSession();
  };

  req.saveSession = saveSession;

  const resolveActiveSessionUser = () => {
    const isAdminRoute = Boolean(req.path && req.path.startsWith('/admin'));
    if (isAdminRoute && req.sessionData.admin_user_id) {
      const adminUser = db.find('users', Number(req.sessionData.admin_user_id));
      if (adminUser) return adminUser;
    }
    let u = req.sessionData.user_id ? db.find('users', Number(req.sessionData.user_id)) : null;
    // If visiting /artist/* while session user_id was overwritten by Admin (id=1),
    // automatically use the most recent registered artist so the artist studio & invoices load seamlessly
    if (
      req.path &&
      req.path.startsWith('/artist') &&
      u &&
      (u.role || '') === 'admin' &&
      db.where('artworks', (a) => Number(a.artist_id) === Number(u.id)).length === 0
    ) {
      const registeredArtists = db
        .where('users', (usr) => (usr.role || '') === 'artist' && Number(usr.id) !== Number(u.id))
        .sort((a, b) => Number(b.id) - Number(a.id));
      if (registeredArtists.length > 0) {
        req.sessionData.admin_user_id = Number(u.id);
        req.sessionData.user_id = Number(registeredArtists[0].id);
        req.sessionData.user_role = 'artist';
        saveSession();
        return registeredArtists[0];
      }
    }
    return u;
  };

  const buildReqContext = (consumeFlash = false) => {
    const currentUser = resolveActiveSessionUser();

    let unreadCount = 0;
    let watchlistIds = req.sessionData.guest_watchlist || [];

    if (currentUser) {
      unreadCount = db.where(
        'notifications',
        (n) => Number(n.user_id) === Number(currentUser.id) && !n.read_at
      ).length;
      watchlistIds = db
        .where('watchlists', (w) => Number(w.user_id) === Number(currentUser.id))
        .map((w) => Number(w.artwork_id));
    }

    const flashMessages = { ...(req.sessionData.flash || {}) };
    if (consumeFlash && Object.keys(flashMessages).length > 0) {
      req.sessionData.flash = {};
      saveSession();
    }

    return {
      currentUser,
      unreadCount,
      watchlistIds,
      csrfToken: req.sessionData.csrf_token,
      flashMessages,
      requestUri: req.originalUrl || req.url || '/',
      host: req.get('host') || 'localhost:8000',
      query: req.query || {},
    };
  };

  req.getCurrentUser = () => resolveActiveSessionUser();

  const origRedirect = res.redirect.bind(res);
  res.redirect = function (...args) {
    saveSession();
    if (IS_VERCEL && (db.hasPendingSync || db._flushInFlight)) {
      Promise.race([
        db.flush(),
        new Promise((resolve) => setTimeout(resolve, 4000)),
      ])
        .catch(() => {})
        .finally(() => {
          origRedirect(...args);
        });
      return;
    }
    if (db.hasPendingSync) {
      db.flush().catch(() => {});
    }
    return origRedirect(...args);
  };

  res.renderPage = (templateName, data = {}, statusCode = 200) => {
    res.locals.reqContext = buildReqContext(true);
    saveSession();
    const sendRendered = () => {
      res.status(statusCode);
      renderView(res, templateName, data);
    };
    if (IS_VERCEL && (db.hasPendingSync || db._flushInFlight)) {
      Promise.race([
        db.flush(),
        new Promise((resolve) => setTimeout(resolve, 4000)),
      ])
        .catch(() => {})
        .finally(sendRendered);
      return;
    }
    if (db.hasPendingSync) {
      db.flush().catch(() => {});
    }
    sendRendered();
  };

  // Validate same-origin on state-changing POST requests
  if (
    req.method === 'POST' &&
    !req.path.startsWith('/api/auctions/sync') &&
    !req.path.startsWith('/api/verify-buyer-code')
  ) {
    const originHeader = req.get('origin');
    const hostHeader = req.get('host');
    if (originHeader && hostHeader) {
      try {
        const originHost = new URL(originHeader).host;
        if (originHost && originHost.toLowerCase() !== hostHeader.toLowerCase()) {
          req.setFlash('error', 'Cross-origin form submission blocked for security.');
          return res.redirect('/');
        }
      } catch (_) {}
    }
  }

  next();
});

// --- Auth Guards ---
function requireAuth(req, res) {
  const user = req.getCurrentUser();
  if (!user) {
    req.setFlash('error', 'Please sign in to access your ArtAlly account.');
    res.redirect('/login');
    return null;
  }
  return user;
}

function requireRole(req, res, role) {
  const user = requireAuth(req, res);
  if (!user) return null;
  if (!helpers.userHasRole(user, role)) {
    if (role === 'admin') {
      req.setFlash('error', 'Administrator credentials are required to access the governance portal.');
      res.redirect('/admin/login');
      return null;
    }
    req.setFlash('error', 'You do not have permission to access that section.');
    res.redirect('/');
    return null;
  }
  return user;
}

// --- Artwork Enrichment Helper ---
function enrichArtwork(db, artwork) {
  const copy = { ...artwork };
  const artistProfile = db.findBy('artist_profiles', 'user_id', Number(copy.artist_id));
  const artistUser = db.find('users', Number(copy.artist_id));
  const category = db.find('categories', Number(copy.category_id || 1));
  const auctions = db.where('auctions', (a) => Number(a.artwork_id) === Number(copy.id));
  const auction = auctions[0] || null;

  copy.artist_name = artistProfile?.display_name || artistUser?.name || 'Independent Artist';
  copy.artist_slug = artistProfile?.slug || helpers.slugify(copy.artist_name);
  copy.artist_country = artistProfile?.country || artistUser?.country || '';
  copy.artist_profile = artistProfile;
  copy.category_name = category?.name || 'Fine Art';
  copy.category_slug = category?.slug || 'contemporary-art';
  copy.auction = auction;
  return copy;
}

// --- File Upload Helpers ---
function saveUploadedImageBuffer(fileObj, subFolder, prefix = 'an_') {
  if (!fileObj || !fileObj.buffer || fileObj.size <= 0) return null;
  const extRaw = path.extname(fileObj.originalname || '').toLowerCase().replace('.', '');
  const allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif', 'bmp', 'jfif'];
  let ext = allowed.includes(extRaw) ? extRaw : 'jpg';
  if (ext === 'jpeg' || ext === 'jfif') ext = 'jpg';

  // On Vercel serverless, return a compact data URL or write to /tmp/uploads so images persist in Supabase JSON store
  if (IS_VERCEL && fileObj.size < 4 * 1024 * 1024) {
    const mime = fileObj.mimetype || `image/${ext}`;
    return `data:${mime};base64,${fileObj.buffer.toString('base64')}`;
  }

  const filename = `${prefix}${crypto.randomBytes(8).toString('hex')}.${ext}`;
  const targetDir = path.join(UPLOAD_ROOT, subFolder);
  fs.mkdirSync(targetDir, { recursive: true });
  fs.writeFileSync(path.join(targetDir, filename), fileObj.buffer);
  return `/uploads/${subFolder}/${filename}`;
}

function saveBase64Image(dataUrl, subFolder, prefix = 'an_') {
  if (!dataUrl || typeof dataUrl !== 'string' || !dataUrl.startsWith('data:image/')) {
    return null;
  }
  if (IS_VERCEL && dataUrl.length < 5 * 1024 * 1024) {
    return dataUrl;
  }
  const match = dataUrl.match(/^data:image\/([a-zA-Z0-9+.-]+);base64,(.+)$/);
  if (!match) return null;
  let ext = match[1].toLowerCase();
  if (ext === 'jpeg' || ext === 'jfif') ext = 'jpg';
  if (!['jpg', 'png', 'webp', 'gif', 'avif', 'bmp'].includes(ext)) ext = 'jpg';
  const buf = Buffer.from(match[2], 'base64');
  const filename = `${prefix}${crypto.randomBytes(8).toString('hex')}.${ext}`;
  const targetDir = path.join(UPLOAD_ROOT, subFolder);
  fs.mkdirSync(targetDir, { recursive: true });
  fs.writeFileSync(path.join(targetDir, filename), buf);
  return `/uploads/${subFolder}/${filename}`;
}

function savePrivateDocument(fileObj, userId) {
  if (!fileObj || !fileObj.buffer || fileObj.size <= 0) return null;
  const ext = path.extname(fileObj.originalname || '').toLowerCase().replace('.', '');
  if (!['pdf', 'jpg', 'jpeg', 'png'].includes(ext)) return null;
  const filename = `coa_u${userId}_${crypto.randomBytes(8).toString('hex')}.${ext}`;
  fs.mkdirSync(PRIVATE_DOC_ROOT, { recursive: true });
  fs.writeFileSync(path.join(PRIVATE_DOC_ROOT, filename), fileObj.buffer);
  return filename;
}

// ============================================================================
// 1. PUBLIC ROUTES
// ============================================================================

app.get('/', (req, res) => {
  const db = DatabaseEngine.getInstance();
  const allArtworks = db.where('artworks', (a) => ['published', 'sold'].includes(a.status || ''));
  const enriched = allArtworks.map((a) => enrichArtwork(db, a));

  const featuredAuctions = enriched.filter((a) => (a.auction?.status || '') === 'active');
  const upcomingAuctions = enriched.filter((a) => (a.auction?.status || '') === 'upcoming');
  const categories = db.all('categories');
  const articles = db.all('editorial_articles');

  res.renderPage('public/home', {
    pageTitle: 'ArtAlly — Fine Art Auctions & Curated Private Collections',
    metaDescription:
      'Discover exceptional works from independent artists, established creators, and remarkable private collections through transparent timed auctions.',
    featuredAuctions,
    upcomingAuctions,
    categories,
    articles: articles.slice(0, 3),
  });
});

function handleAuctionsPage(req, res, forcedStatus = null) {
  const db = DatabaseEngine.getInstance();
  const statusFilter = forcedStatus || req.query.status || 'active';
  const allArtworks = db.where('artworks', (a) => ['published', 'sold'].includes(a.status || ''));
  const enriched = allArtworks.map((a) => enrichArtwork(db, a));

  let lots = [];
  if (statusFilter === 'upcoming') {
    lots = enriched.filter((a) => (a.auction?.status || '') === 'upcoming');
  } else if (statusFilter === 'closed') {
    lots = enriched.filter((a) => String(a.auction?.status || '').startsWith('closed'));
  } else {
    lots = enriched.filter((a) => (a.auction?.status || '') === 'active');
  }

  res.renderPage('public/auctions', {
    pageTitle: 'Timed Fine Art Auctions — ArtAlly',
    lots,
    statusFilter,
  });
}

app.get('/auctions', (req, res) => handleAuctionsPage(req, res));
app.get('/upcoming-auctions', (req, res) => handleAuctionsPage(req, res, 'upcoming'));

app.get('/catalogue', (req, res) => {
  const db = DatabaseEngine.getInstance();
  const q = String(req.query.q || '').trim();
  const categorySlug = String(req.query.category || '').trim();
  const artistSlug = String(req.query.artist || '').trim();
  const statusFilter = String(req.query.status || '').trim();
  const mediumFilter = String(req.query.medium || '').trim();
  const minPrice =
    req.query.min_price !== undefined && req.query.min_price !== ''
      ? Number(req.query.min_price)
      : null;
  const maxPrice =
    req.query.max_price !== undefined && req.query.max_price !== ''
      ? Number(req.query.max_price)
      : null;
  const sort = String(req.query.sort || 'closing_soon').trim();
  const viewMode = req.query.view === 'list' ? 'list' : 'grid';

  const artworks = db.where('artworks', (a) => ['published', 'sold'].includes(a.status || ''));
  const enriched = artworks.map((a) => enrichArtwork(db, a));

  const filtered = enriched.filter((item) => {
    if (q !== '') {
      const haystack = [
        item.title || '',
        item.artist_name || '',
        item.medium || '',
        item.description || '',
        item.category_name || '',
      ]
        .join(' ')
        .toLowerCase();
      if (!haystack.includes(q.toLowerCase())) return false;
    }
    if (categorySlug !== '' && (item.category_slug || '') !== categorySlug) return false;
    if (artistSlug !== '' && (item.artist_slug || '') !== artistSlug) return false;
    if (statusFilter !== '') {
      const aucStatus = item.auction?.status || '';
      if (statusFilter === 'active' && aucStatus !== 'active') return false;
      if (statusFilter === 'upcoming' && aucStatus !== 'upcoming') return false;
      if (statusFilter === 'closed' && !String(aucStatus).startsWith('closed')) return false;
    }
    if (mediumFilter !== '' && !String(item.medium || '').toLowerCase().includes(mediumFilter.toLowerCase())) {
      return false;
    }
    const price = Number(item.auction?.current_bid ?? item.starting_bid ?? 0);
    if (minPrice !== null && price < minPrice) return false;
    if (maxPrice !== null && price > maxPrice) return false;
    return true;
  });

  filtered.sort((a, b) => {
    const priceA = Number(a.auction?.current_bid ?? a.starting_bid ?? 0);
    const priceB = Number(b.auction?.current_bid ?? b.starting_bid ?? 0);
    const bidsA = Number(a.auction?.bid_count ?? 0);
    const bidsB = Number(b.auction?.bid_count ?? 0);
    const endA = helpers.strtotime(a.auction?.ends_at || '+30 days') || Number.MAX_SAFE_INTEGER;
    const endB = helpers.strtotime(b.auction?.ends_at || '+30 days') || Number.MAX_SAFE_INTEGER;

    if (sort === 'price_asc') return priceA - priceB;
    if (sort === 'price_desc') return priceB - priceA;
    if (sort === 'popularity') return bidsB - bidsA;
    if (sort === 'newest') return Number(b.id) - Number(a.id);
    return endA - endB;
  });

  res.renderPage('public/catalogue', {
    pageTitle: 'Fine Art Catalogue — ArtAlly',
    artworks: filtered,
    categories: db.all('categories'),
    artists: db.all('artist_profiles'),
    filters: {
      q,
      category: categorySlug,
      artist: artistSlug,
      status: statusFilter,
      medium: mediumFilter,
      min_price: minPrice,
      max_price: maxPrice,
      sort,
      view: viewMode,
    },
  });
});

app.get('/artworks/:slug', (req, res) => {
  const db = DatabaseEngine.getInstance();
  let artworkRaw = db.findBy('artworks', 'slug', req.params.slug);
  if (!artworkRaw && req.params.slug === 'ghost-friends-crossing') {
    const heroArtwork = {
      id: 9001,
      artist_id: 0,
      category_id: 1,
      title: 'Ghost Friends Crossing',
      slug: 'ghost-friends-crossing',
      artist_name: 'Mark Snell',
      artist_slug: 'mark-snell',
      artist_country: 'Contemporary Fine Art',
      category_name: 'Contemporary Art',
      category_slug: 'contemporary-art',
      medium: 'Acrylic on Linden',
      year_created: 2026,
      dimensions: '80 cm × 80 cm',
      classification: 'Original Unique Work',
      edition_number: 'Unique (1/1)',
      artist_attribution: 'Signed by Mark Snell',
      description:
        'An atmospheric museum-grade abstract composition in acrylic on linden panel (80 cm × 80 cm) by Mark Snell, depicting four luminous spectral silhouettes drifting together across a glowing threshold of raw umber, burnished ochre, and deep Prussian blue impasto.',
      provenance: 'Direct from the studio of Mark Snell.',
      condition_report: 'Pristine original studio condition.',
      primary_image: '/uploads/artworks/ghost-friends-crossing.jpg?v=3',
      currency: 'USD',
      estimate_low: 45000,
      estimate_high: 60000,
      starting_bid: 38000,
      agreed_price: 54850,
      reserve_price: 45000,
      status: 'sold',
      auction: {
        id: 9001,
        lot_number: 'LOT 01',
        title: 'Contemporary Evening Sale',
        status: 'closed_sold',
        starting_bid: 38000,
        current_bid: 54850,
        reserve_price: 45000,
        reserve_met: true,
        bid_count: 14,
        currency: 'USD',
      },
    };
    return res.renderPage('public/artwork-detail', {
      pageTitle: 'Ghost Friends Crossing by Mark Snell — ArtAlly',
      metaDescription: heroArtwork.description,
      artwork: heroArtwork,
      images: [
        {
          id: 1,
          artwork_id: 9001,
          image_url: '/uploads/artworks/ghost-friends-crossing.jpg?v=3',
          is_primary: true,
          sort_order: 1,
        },
      ],
      auction: heroArtwork.auction,
      bids: [],
      related: [],
    });
  }
  if (!artworkRaw) {
    return res.renderPage('pages/404', { pageTitle: 'Lot Not Found — ArtAlly' }, 404);
  }

  const user = req.getCurrentUser();
  const isOwnerOrAdmin =
    user && (Number(user.id) === Number(artworkRaw.artist_id) || helpers.userHasRole(user, 'admin'));
  if (!['published', 'sold'].includes(artworkRaw.status || '') && !isOwnerOrAdmin) {
    req.setFlash('error', 'This submission is currently under confidential curatorial review.');
    return res.redirect('/catalogue');
  }

  const artwork = enrichArtwork(db, artworkRaw);
  const images = db
    .where('artwork_images', (i) => Number(i.artwork_id) === Number(artwork.id))
    .sort((a, b) => Number(a.sort_order || 1) - Number(b.sort_order || 1));

  const auction = artwork.auction;
  let bids = [];
  if (auction) {
    bids = db
      .where('bids', (b) => Number(b.auction_id) === Number(auction.id))
      .sort((a, b) => Number(b.amount) - Number(a.amount));
  }

  const relatedRaw = db.where(
    'artworks',
    (a) => Number(a.id) !== Number(artwork.id) && (a.status || '') === 'published'
  );
  const related = relatedRaw.map((a) => enrichArtwork(db, a)).slice(0, 3);

  res.renderPage('public/artwork-detail', {
    pageTitle: `${artwork.title} by ${artwork.artist_name} — ArtAlly`,
    metaDescription: String(artwork.description || '').substring(0, 155),
    artwork,
    images,
    auction,
    bids,
    related,
  });
});

app.get('/categories', (req, res) => {
  const db = DatabaseEngine.getInstance();
  const categories = db.all('categories').map((cat) => {
    const count = db.where(
      'artworks',
      (a) => Number(a.category_id || 0) === Number(cat.id) && ['published', 'sold'].includes(a.status || '')
    ).length;
    return { ...cat, artwork_count: count };
  });

  res.renderPage('public/categories', {
    pageTitle: 'Collecting Categories & Curatorial Departments — ArtAlly',
    categories,
  });
});

app.get('/artists', (req, res) => {
  const user = req.getCurrentUser();
  if (!user || !helpers.userHasRole(user, 'admin')) {
    return res.redirect('/');
  }
  const db = DatabaseEngine.getInstance();
  const artists = db.all('artist_profiles').map((artist) => {
    const works = db.where(
      'artworks',
      (a) => Number(a.artist_id) === Number(artist.user_id) && ['published', 'sold'].includes(a.status || '')
    );
    return {
      ...artist,
      published_count: works.length,
      is_approved: (artist.approval_status || 'approved') === 'approved',
    };
  });

  res.renderPage('public/artists', {
    pageTitle: 'Represented & Independent Artists — ArtAlly',
    artists,
  });
});

app.get('/artists/:slug', (req, res) => {
  const db = DatabaseEngine.getInstance();
  const artist = db.findBy('artist_profiles', 'slug', req.params.slug);
  if (!artist) {
    return res.renderPage('pages/404', { pageTitle: 'Artist Not Found — ArtAlly' }, 404);
  }

  const isApproved = (artist.approval_status || 'approved') === 'approved';
  const user = req.getCurrentUser();
  const isOwnerOrAdmin =
    user && (Number(user.id) === Number(artist.user_id) || helpers.userHasRole(user, 'admin'));

  if (!isOwnerOrAdmin) {
    return res.redirect('/');
  }

  const rawWorks = db.where(
    'artworks',
    (a) =>
      Number(a.artist_id) === Number(artist.user_id) &&
      (isOwnerOrAdmin || ['published', 'sold'].includes(a.status || ''))
  );
  const artworks = rawWorks.map((a) => enrichArtwork(db, a));

  let isFollowing = false;
  if (user) {
    const follows = db.where(
      'followed_artists',
      (f) => Number(f.user_id) === Number(user.id) && Number(f.artist_profile_id) === Number(artist.id)
    );
    isFollowing = follows.length > 0;
  }

  const artistUserObj = db.find('users', Number(artist.user_id)) || {};
  const authInvoices = db
    .where(
      'authentication_invoices',
      (inv) =>
        Number(inv.artist_id || 0) === Number(artist.user_id) ||
        (inv.artist_email &&
          artistUserObj.email &&
          String(inv.artist_email).toLowerCase() === String(artistUserObj.email).toLowerCase()) ||
        (inv.artist_name &&
          artist.display_name &&
          String(inv.artist_name).trim().toLowerCase() ===
            String(artist.display_name).trim().toLowerCase())
    )
    .sort((a, b) => Number(b.id) - Number(a.id));

  res.renderPage('public/artist-profile', {
    pageTitle: `${artist.display_name} — Artist Portfolio & Auctions | ArtAlly`,
    artist,
    artworks,
    isFollowing,
    isApproved,
    authInvoices,
  });
});

app.get('/journal', (req, res) => {
  const db = DatabaseEngine.getInstance();
  res.renderPage('public/journal', {
    pageTitle: 'Stories & Curatorial Journal — ArtAlly',
    articles: db.all('editorial_articles'),
  });
});

app.get('/journal/:slug', (req, res) => {
  const db = DatabaseEngine.getInstance();
  const article = db.findBy('editorial_articles', 'slug', req.params.slug);
  if (!article) {
    return res.renderPage('pages/404', { pageTitle: 'Article Not Found — ArtAlly' }, 404);
  }
  const moreArticles = db.where('editorial_articles', (a) => a.slug !== req.params.slug).slice(0, 2);
  res.renderPage('public/journal-article', {
    pageTitle: `${article.title} — ArtAlly Journal`,
    article,
    moreArticles,
  });
});

app.get('/sell-your-art', (req, res) => {
  const db = DatabaseEngine.getInstance();
  const settings = db.find('platform_settings', 1) || {};
  res.renderPage('public/sell-your-art', {
    pageTitle: 'Consign & Sell Your Artwork — ArtAlly',
    settings,
  });
});

const INSTITUTIONAL_PAGES = {
  'how-it-works': {
    title: 'How ArtAlly Timed Auctions Work',
    subtitle: 'Curatorial Rigor, Transparent Bidding, and Institutional Settlement',
  },
  'buyer-guide': {
    title: 'Collector & Buyer Guide',
    subtitle: 'Registration, Paddle Verification, Buyer’s Premium, and Museum Logistics',
  },
  'seller-guide': {
    title: 'Artist & Consignor Guide',
    subtitle: 'Multi-Step Submission, Specialist Valuation, Reserve Protection, and Artist Payouts',
  },
  about: {
    title: 'About ArtAlly',
    subtitle: 'An International Digital Auction House Dedicated to Living Artists and Private Collections',
  },
  contact: {
    title: 'Private Client & Specialist Concierge',
    subtitle: 'Geneva · New York · London · Paris',
  },
  terms: {
    title: 'Conditions of Sale & Terms of Use',
    subtitle: 'Governing Timed Auctions, Reserve Prices, and Title Transfer',
  },
  privacy: {
    title: 'Privacy & Collector Confidentiality Policy',
    subtitle: 'Protecting Anonymous Paddle Bidding, Provenance Records, and Financial Data',
  },
};

for (const pageKey of Object.keys(INSTITUTIONAL_PAGES)) {
  app.get(`/${pageKey}`, (req, res) => {
    const meta = INSTITUTIONAL_PAGES[pageKey];
    res.renderPage('public/institutional', {
      pageTitle: `${meta.title} — ArtAlly`,
      pageKey,
      meta,
    });
  });
}

app.get('/sitemap.xml', (req, res) => {
  const db = DatabaseEngine.getInstance();
  const artworks = db.where('artworks', (a) => ['published', 'sold'].includes(a.status || ''));
  const artists = db.all('artist_profiles');
  const protocol = req.protocol || 'http';
  const host = req.get('host') || 'localhost:8000';
  const baseUrl = `${protocol}://${host}`;

  let xml = '<?xml version="1.0" encoding="UTF-8"?>\n';
  xml += '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n';
  for (const p of ['/', '/auctions', '/catalogue', '/categories', '/artists', '/journal', '/sell-your-art']) {
    xml += `  <url><loc>${baseUrl}${p}</loc><changefreq>daily</changefreq><priority>0.9</priority></url>\n`;
  }
  for (const art of artworks) {
    xml += `  <url><loc>${baseUrl}/artworks/${helpers.e(art.slug)}</loc><changefreq>hourly</changefreq><priority>0.8</priority></url>\n`;
  }
  for (const artist of artists) {
    xml += `  <url><loc>${baseUrl}/artists/${helpers.e(artist.slug)}</loc><changefreq>weekly</changefreq><priority>0.7</priority></url>\n`;
  }
  xml += '</urlset>';

  res.set('Content-Type', 'application/xml; charset=UTF-8');
  res.send(xml);
});

app.post('/newsletter/subscribe', (req, res) => {
  const db = DatabaseEngine.getInstance();
  const email = String(req.body.email || '').trim().toLowerCase();
  const backUrl = req.get('referer') || '/';
  if (!email || !email.includes('@')) {
    req.setFlash('error', 'Please enter a valid email address to subscribe to the Curatorial Dispatch.');
    return res.redirect(backUrl);
  }
  const existing = db.findBy('newsletter_subscribers', 'email', email);
  if (!existing) {
    db.insert('newsletter_subscribers', {
      email,
      status: 'subscribed',
      ip_address: req.ip || '127.0.0.1',
    });
  }
  req.setFlash('success', 'Thank you. You are now subscribed to the ArtAlly Curatorial Dispatch.');
  res.redirect(backUrl);
});

app.post('/artworks/report', (req, res) => {
  const db = DatabaseEngine.getInstance();
  const artworkId = Number(req.body.artwork_id || 0);
  const reason = String(req.body.reason || '').trim();
  const details = String(req.body.details || '').trim();
  const user = req.getCurrentUser();
  const backUrl = req.get('referer') || '/catalogue';

  if (artworkId <= 0 || reason === '') {
    req.setFlash('error', 'Please provide a reason for the curatorial inquiry or report.');
    return res.redirect(backUrl);
  }

  db.insert('reports', {
    artwork_id: artworkId,
    reporter_user_id: user ? Number(user.id) : null,
    reporter_name: user?.name || String(req.body.reporter_name || 'Anonymous Collector').trim(),
    reason,
    details,
    status: 'open',
  });

  req.setFlash('success', 'Your report has been logged with the ArtAlly Compliance & Provenance Committee.');
  res.redirect(backUrl);
});

// ============================================================================
// 2. AUTHENTICATION ROUTES
// ============================================================================

app.get('/login', (req, res) => {
  if (req.getCurrentUser()) return res.redirect('/');
  res.renderPage('auth/login', { pageTitle: 'Sign In — ArtAlly' });
});

app.post('/login', async (req, res) => {
  const db = DatabaseEngine.getInstance();
  const email = String(req.body.email || '').trim();
  const password = String(req.body.password || '');

  const attempts = Number(req.sessionData.login_attempts || 0);
  const lastAttempt = Number(req.sessionData.login_last_attempt || 0);
  const nowSec = Math.floor(Date.now() / 1000);
  if (attempts >= 8 && nowSec - lastAttempt < 120) {
    req.setFlash('error', 'Too many sign-in attempts. Please wait two minutes before trying again.');
    return res.redirect('/login');
  }

  const user = await db.findUserByEmailFromDb(email);
  if (!user || !verifyPassword(password, user.password)) {
    req.sessionData.login_attempts = attempts + 1;
    req.sessionData.login_last_attempt = nowSec;
    req.setFlash('error', 'Invalid email address or password.');
    return res.redirect('/login');
  }

  if ((user.status || 'active') === 'suspended') {
    req.setFlash('error', 'This account has been suspended by an administrator.');
    return res.redirect('/login');
  }

  delete req.sessionData.login_attempts;
  req.loginUser(user);
  req.setFlash('success', `Welcome back to ArtAlly, ${user.name}.`);

  if (helpers.userHasRole(user, 'admin')) return res.redirect('/admin/dashboard');
  if ((user.role || '') === 'artist') return res.redirect('/artist/dashboard');
  res.redirect('/buyer/dashboard');
});

app.post('/login/demo', (req, res) => {
  req.setFlash('error', 'Quick demo login has been disabled for platform security. Please sign in with your credentials.');
  res.redirect('/login');
});

app.post('/api/verify-buyer-code', (req, res) => {
  const db = DatabaseEngine.getInstance();
  const settings = db.find('platform_settings', 1) || {};
  const expectedCode = String(settings.buyer_verification_code || 'NOIR-BUYER-2026').trim();
  const submittedCode = String(req.body.code || '').trim();
  const valid =
    Boolean(submittedCode) &&
    submittedCode.toLowerCase() === expectedCode.toLowerCase();
  res.json({ valid });
});

app.get('/register', (req, res) => {
  if (req.getCurrentUser()) return res.redirect('/');
  const selectedRole = req.query.role === 'buyer' ? 'buyer' : 'artist';
  res.renderPage('auth/register', {
    pageTitle: 'Artist Studio & Verified Collector Registration — ArtAlly',
    selectedRole,
  });
});

app.post('/register', upload.single('profile_photo_file'), (req, res) => {
  const db = DatabaseEngine.getInstance();
  const name = String(req.body.name || '').trim();
  const email = String(req.body.email || '').trim().toLowerCase();
  const password = String(req.body.password || '');
  const passwordConfirmation = String(req.body.password_confirmation ?? password);
  const country = String(req.body.country || '').trim();
  const city = String(req.body.city || '').trim();
  const accountType = req.body.account_type === 'buyer' ? 'buyer' : 'artist';
  const discipline = String(req.body.discipline || 'Contemporary Visual Art').trim();
  const biography = String(req.body.biography || 'Independent artist registered with ArtAlly.').trim();
  const termsAccepted = Boolean(req.body.terms_accepted);

  if (accountType === 'buyer') {
    const settings = db.find('platform_settings', 1) || {};
    const expectedCode = String(settings.buyer_verification_code || 'NOIR-BUYER-2026').trim();
    const submittedCode = String(req.body.buyer_verification_code || '').trim();
    if (!submittedCode || submittedCode.toLowerCase() !== expectedCode.toLowerCase()) {
      req.setFlash(
        'error',
        'Verification Code Required: This registration portal is open for Artists only. To access the Collector/Buyer path, you must enter a valid Buyer Verification Code issued by Administration.'
      );
      return res.redirect('/register?role=buyer');
    }
  }

  if (password !== passwordConfirmation) {
    req.setFlash('error', 'Passwords do not match. Please enter the same password twice.');
    return res.redirect(`/register?role=${accountType}`);
  }

  if (!name || !email.includes('@') || password.length < 8 || !country || !termsAccepted) {
    req.setFlash(
      'error',
      'Please complete all required fields, use a password of at least 8 characters, and accept the Conditions of Sale.'
    );
    return res.redirect(`/register?role=${accountType}`);
  }

  const existingUser = db.where(
    'users',
    (u) => String(u.email || '').trim().toLowerCase() === email
  )[0];
  if (existingUser) {
    // If same user double-submitted registration or entered matching password, log them directly into their dashboard
    if (
      Number(req.sessionData?.user_id || 0) === Number(existingUser.id) ||
      verifyPassword(password, existingUser.password)
    ) {
      if (accountType === 'artist') {
        const updatedRoles = Array.isArray(existingUser.roles)
          ? Array.from(new Set([...existingUser.roles, 'artist']))
          : ['artist', 'buyer'];
        db.update('users', existingUser.id, {
          role: existingUser.role === 'admin' ? 'admin' : 'artist',
          roles: updatedRoles,
          is_seeded: false,
        });
        ensureArtistProfile(db, existingUser, true);
      }
      req.loginUser(existingUser);
      req.setFlash('success', 'Your ArtAlly Studio account is active. Welcome to your dashboard.');
      return res.redirect(
        accountType === 'artist' || helpers.userHasRole(existingUser, 'artist')
          ? '/artist/dashboard'
          : '/buyer/dashboard'
      );
    }
    req.setFlash(
      'error',
      'An account with this email address is already registered. Please sign in with your password below.'
    );
    return res.redirect('/login');
  }

  let profilePhoto =
    'https://images.unsplash.com/photo-1513364776144-60967b0f800f?auto=format&fit=crop&w=800&q=80';
  if (accountType === 'artist') {
    if (req.body.profile_photo_data) {
      const uploaded = saveBase64Image(String(req.body.profile_photo_data), 'profiles', 'artist_');
      if (uploaded) profilePhoto = uploaded;
    } else if (req.file) {
      const uploaded = saveUploadedImageBuffer(req.file, 'profiles', 'artist_');
      if (uploaded) profilePhoto = uploaded;
    }
  }

  const roles = accountType === 'artist' ? ['artist', 'buyer'] : ['buyer'];
  let user = null;
  db.transaction((txDb) => {
    user = txDb.insert('users', {
      name,
      email,
      password: hashPassword(password),
      country,
      role: accountType,
      roles,
      email_verified_at: formatUtcNow(),
      bidding_approved: true,
      status: 'active',
      is_seeded: false,
    });

    if (accountType === 'artist') {
      txDb.insert('artist_profiles', {
        user_id: Number(user.id),
        display_name: name,
        slug: helpers.slugify(`${name}-${user.id}`),
        biography: biography || 'Independent artist registered with ArtAlly.',
        artist_statement: '',
        profile_photo: profilePhoto,
        country,
        city,
        discipline: discipline || 'Contemporary Art',
        years_active: `${helpers.gmdate('Y')} – Present`,
        website: '',
        social_instagram: '',
        featured: false,
        verified_artist: false,
        approval_status: 'pending',
        portfolio_images: [],
        is_seeded: false,
      });
    }

    txDb.insert('buyer_profiles', {
      user_id: Number(user.id),
      collecting_focus: 'Contemporary & Modern Works',
      verified_payment_method: 'Private Client Account Verified',
      shipping_address: country,
      identity_verified: true,
    });

    const token = crypto.randomBytes(20).toString('hex');
    txDb.insert('email_verifications', {
      user_id: Number(user.id),
      email,
      token,
      verified_at: formatUtcNow(),
    });

    txDb.insert('notifications', {
      user_id: Number(user.id),
      type: 'account_verified',
      title: 'Welcome to ArtAlly',
      message:
        accountType === 'artist'
          ? 'Your Artist & Collector account is active. You may now complete your public portfolio or submit artwork for curatorial review.'
          : 'Your Collector account and anonymous bidding paddle are active. You may now watch lots and place verified bids.',
      action_url: accountType === 'artist' ? '/artist/dashboard' : '/buyer/dashboard',
      read_at: null,
    });
  });

  req.loginUser(user);
  req.setFlash('success', 'Your ArtAlly account has been created and verified.');
  res.redirect(accountType === 'artist' ? '/artist/dashboard' : '/buyer/dashboard');
});

app.get('/verify-email', (req, res) => {
  const user = requireAuth(req, res);
  if (!user) return;
  res.renderPage('auth/verify-email', {
    pageTitle: 'Email & Paddle Verification — ArtAlly',
    user,
  });
});

app.post('/verify-email', (req, res) => {
  const user = requireAuth(req, res);
  if (!user) return;
  const db = DatabaseEngine.getInstance();
  db.update('users', user.id, {
    email_verified_at: formatUtcNow(),
    bidding_approved: true,
  });
  req.setFlash('success', 'Your email address and collector bidding paddle have been verified.');
  res.redirect('/buyer/dashboard');
});

app.get('/forgot-password', (req, res) => {
  res.renderPage('auth/forgot-password', { pageTitle: 'Password Recovery — ArtAlly' });
});

app.post('/forgot-password', (req, res) => {
  const db = DatabaseEngine.getInstance();
  const email = String(req.body.email || '').trim().toLowerCase();
  const user = db.findBy('users', 'email', email);
  const token = crypto.randomBytes(24).toString('hex');

  if (user) {
    db.insert('password_reset_tokens', {
      email,
      token,
      expires_at: helpers.gmdate('Y-m-d H:i:s', helpers.strtotime('+1 hour')),
    });
  }

  req.setFlash(
    'success',
    'If an account is registered for that email address, a password recovery token has been issued. Please enter the recovery token sent to you or provided by ArtAlly Administration.'
  );
  res.redirect(`/reset-password?email=${encodeURIComponent(email)}`);
});

app.get('/reset-password', (req, res) => {
  const token = String(req.query.token || '').trim();
  const email = String(req.query.email || '').trim();
  res.renderPage('auth/reset-password', {
    pageTitle: 'Set New Password — ArtAlly',
    token,
    email,
  });
});

app.post('/reset-password', (req, res) => {
  const db = DatabaseEngine.getInstance();
  const email = String(req.body.email || '').trim().toLowerCase();
  const token = String(req.body.token || '').trim();
  const password = String(req.body.password || '');
  const passwordConfirmation = String(req.body.password_confirmation ?? password);

  if (password !== passwordConfirmation) {
    req.setFlash('error', 'Passwords do not match. Please enter the same password twice.');
    return res.redirect(`/reset-password?token=${encodeURIComponent(token)}&email=${encodeURIComponent(email)}`);
  }

  if (password.length < 8) {
    req.setFlash('error', 'New password must be at least 8 characters long.');
    return res.redirect(`/reset-password?token=${encodeURIComponent(token)}&email=${encodeURIComponent(email)}`);
  }

  const record = token ? db.findBy('password_reset_tokens', 'token', token) : null;
  const user = db.findBy('users', 'email', email);
  const nowStr = formatUtcNow();
  const isExpired = record?.expires_at && String(record.expires_at) < nowStr;

  if (!record || isExpired || !user || String(record.email || '').toLowerCase() !== email) {
    req.setFlash('error', 'Invalid or expired password recovery token.');
    return res.redirect('/forgot-password');
  }

  db.update('users', user.id, { password: hashPassword(password) });
  db.delete('password_reset_tokens', record.id);
  req.setFlash('success', 'Your password has been updated. Please sign in with your new credentials.');
  res.redirect('/login');
});

app.post('/logout', (req, res) => {
  req.logoutUser();
  req.setFlash('success', 'You have safely signed out of ArtAlly.');
  res.redirect('/');
});

// ============================================================================
// 3. ARTIST STUDIO ROUTES
// ============================================================================

function ensureArtistProfile(db, user, markActive = false) {
  let profile = db.findBy('artist_profiles', 'user_id', Number(user.id));
  if (!profile) {
    profile = db.insert('artist_profiles', {
      user_id: Number(user.id),
      display_name: user.name,
      slug: helpers.slugify(`${user.name}-${user.id}`),
      biography: 'Contemporary visual artist represented on ArtAlly.',
      artist_statement: '',
      profile_photo:
        'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=800&q=80',
      country: user.country || 'Switzerland',
      city: '',
      discipline: 'Contemporary Fine Art',
      years_active: `${helpers.gmdate('Y')} – Present`,
      website: '',
      social_instagram: '',
      featured: false,
      verified_artist: false,
      approval_status: 'pending',
      portfolio_images: [],
      is_seeded: false,
    });
  } else if (markActive && profile.is_seeded) {
    profile = db.update('artist_profiles', profile.id, { is_seeded: false });
  }
  if (markActive && user.is_seeded) {
    db.update('users', user.id, { is_seeded: false });
  }
  return profile;
}

function computeArtistWalletState(db, artistId, existingProfile = null) {
  const uid = Number(artistId || 0);
  const user = uid > 0 ? db.find('users', uid) : null;
  const profile = existingProfile || db.findBy('artist_profiles', 'user_id', uid) || null;
  const payouts = db.where('seller_payouts', (p) => Number(p.artist_id) === uid);
  const completedPayoutsTotal = payouts.reduce(
    (acc, p) => acc + ((p.status || '') === 'completed' ? Number(p.net_payout_amount || 0) : 0),
    0
  );
  const pendingPayoutsTotal = payouts.reduce(
    (acc, p) => acc + ((p.status || '') === 'pending' ? Number(p.net_payout_amount || 0) : 0),
    0
  );
  const manualAdjustment = Number(profile?.manual_wallet_adjustment || 0);
  const withdrawals = db
    .where('wallet_withdrawals', (w) => Number(w.artist_id) === uid)
    .sort((a, b) => Number(b.id) - Number(a.id));

  const processingWithdrawalsTotal = withdrawals.reduce(
    (acc, w) => acc + ((w.status || '') === 'processing' ? Number(w.amount || 0) : 0),
    0
  );
  const completedWithdrawalsTotal = withdrawals.reduce(
    (acc, w) => acc + ((w.status || '') === 'completed' ? Number(w.amount || 0) : 0),
    0
  );

  const totalDisbursed = Math.max(0, Math.round((completedPayoutsTotal + manualAdjustment) * 100) / 100);
  const availableBalance = Math.max(
    0,
    Math.round((totalDisbursed - processingWithdrawalsTotal - completedWithdrawalsTotal) * 100) / 100
  );
  const withdrawalUnlocked = Boolean(
    profile?.withdrawal_unlocked !== undefined
      ? profile.withdrawal_unlocked
      : user?.withdrawal_unlocked
  );

  if (profile && Number(profile.wallet_balance ?? -1) !== availableBalance) {
    db.update('artist_profiles', profile.id, { wallet_balance: availableBalance });
  }

  return {
    available_balance: availableBalance,
    total_disbursed: totalDisbursed,
    pending_payouts_total: pendingPayoutsTotal,
    processing_withdrawals_total: processingWithdrawalsTotal,
    completed_withdrawals_total: completedWithdrawalsTotal,
    withdrawal_unlocked: withdrawalUnlocked,
    withdrawals,
  };
}

app.get('/artist/dashboard', (req, res) => {
  const user = requireRole(req, res, 'artist');
  if (!user) return;
  const db = DatabaseEngine.getInstance();
  const profile = ensureArtistProfile(db, user);
  const walletState = computeArtistWalletState(db, Number(user.id), profile);

  const artworks = db.where('artworks', (a) => Number(a.artist_id) === Number(user.id));
  const artistArtworkIds = new Set(artworks.map((a) => Number(a.id)));
  const artistArtworkTitles = new Set(
    artworks.map((a) => String(a.title || '').trim().toLowerCase()).filter(Boolean)
  );
  const rawSubs = db.where('artwork_submissions', (s) => Number(s.artist_id) === Number(user.id));
  const artistRefCodes = new Set(
    rawSubs.map((s) => String(s.reference_code || '').trim().toLowerCase()).filter(Boolean)
  );

  const authInvoices = db
    .where(
      'authentication_invoices',
      (inv) =>
        Number(inv.artist_id || 0) === Number(user.id) ||
        (inv.artwork_id && artistArtworkIds.has(Number(inv.artwork_id))) ||
        (inv.reference_code &&
          artistRefCodes.has(String(inv.reference_code).trim().toLowerCase())) ||
        (inv.artwork_title &&
          artistArtworkTitles.has(String(inv.artwork_title).trim().toLowerCase())) ||
        (inv.artist_email &&
          user.email &&
          String(inv.artist_email).trim().toLowerCase() === String(user.email).trim().toLowerCase()) ||
        (inv.artist_name &&
          (String(inv.artist_name).trim().toLowerCase() === String(user.name || '').trim().toLowerCase() ||
            String(inv.artist_name).trim().toLowerCase() ===
              String(profile.display_name || '').trim().toLowerCase()))
    )
    .sort((a, b) => Number(b.id) - Number(a.id));

  const submissions = rawSubs
    .map((sub) => {
      const art = db.find('artworks', Number(sub.artwork_id)) || {};
      const matchedInvoice = authInvoices.find(
        (inv) =>
          Number(inv.artwork_id || 0) === Number(sub.artwork_id) ||
          (inv.reference_code &&
            String(inv.reference_code).trim().toLowerCase() ===
              String(sub.reference_code || '').trim().toLowerCase()) ||
          (inv.artwork_title &&
            String(inv.artwork_title).trim().toLowerCase() ===
              String(art.title || '').trim().toLowerCase())
      );
      return {
        ...sub,
        artwork: art,
        auction: db.findBy('auctions', 'artwork_id', Number(sub.artwork_id)),
        invoice: matchedInvoice || null,
      };
    })
    .sort((a, b) => Number(b.id) - Number(a.id));

  const auctions = db.where('auctions', (a) => Number(a.artist_id) === Number(user.id));
  const orders = db.where('orders', (o) => Number(o.artist_id) === Number(user.id));
  const payouts = db.where('seller_payouts', (p) => Number(p.artist_id) === Number(user.id));
  const notifications = db
    .where('notifications', (n) => Number(n.user_id) === Number(user.id))
    .sort((a, b) => Number(b.id) - Number(a.id));

  const stats = {
    total_submissions: submissions.length,
    awaiting_review: submissions.filter((s) =>
      ['submitted', 'under_review', 'changes_requested'].includes(s.status || '')
    ).length,
    approved_artworks: artworks.filter((a) => ['approved', 'published', 'sold'].includes(a.status || '')).length,
    active_auctions: auctions.filter((a) => (a.status || '') === 'active').length,
    sold_artworks: artworks.filter((a) => (a.status || '') === 'sold').length,
    unsold_artworks: auctions.filter((a) => (a.status || '') === 'closed_unsold').length,
    total_sales_volume: orders.reduce((acc, o) => acc + Number(o.hammer_price || 0), 0),
    pending_payouts: payouts.reduce(
      (acc, p) => acc + ((p.status || '') === 'pending' ? Number(p.net_payout_amount || 0) : 0),
      0
    ),
    completed_payouts: walletState.total_disbursed,
    wallet_balance: walletState.available_balance,
    processing_withdrawals: walletState.processing_withdrawals_total,
  };

  res.renderPage('artist/dashboard', {
    pageTitle: 'Artist Studio Dashboard — ArtAlly',
    user,
    profile,
    stats,
    walletState,
    walletWithdrawals: walletState.withdrawals,
    submissions,
    artworks,
    auctions,
    orders,
    payouts,
    authInvoices,
    notifications: notifications.slice(0, 8),
  });
});

app.get('/artist/profile', (req, res) => {
  const user = requireRole(req, res, 'artist');
  if (!user) return;
  const db = DatabaseEngine.getInstance();
  const profile = ensureArtistProfile(db, user);
  res.renderPage('artist/profile-edit', {
    pageTitle: 'Edit Artist Profile & Portfolio — ArtAlly',
    user,
    profile,
  });
});

app.post('/artist/profile', upload.single('profile_photo_file'), (req, res) => {
  const user = requireRole(req, res, 'artist');
  if (!user) return;
  const db = DatabaseEngine.getInstance();
  const profile = ensureArtistProfile(db, user);

  const displayName = String(req.body.display_name || user.name).trim();
  const biography = String(req.body.biography || '').trim();
  const statement = String(req.body.artist_statement || '').trim();
  const country = String(req.body.country || user.country).trim();
  const city = String(req.body.city || '').trim();
  const discipline = String(req.body.discipline || '').trim();
  const yearsActive = String(req.body.years_active || '').trim();
  const website = String(req.body.website || '').trim();
  const instagram = String(req.body.social_instagram || '').trim();
  let profilePhoto = String(req.body.profile_photo_url || profile.profile_photo).trim();
  if (profilePhoto.startsWith('blob:')) {
    profilePhoto = profile.profile_photo;
  }

  if (req.body.profile_photo_data) {
    const uploaded = saveBase64Image(String(req.body.profile_photo_data), 'profiles');
    if (uploaded) profilePhoto = uploaded;
  } else if (req.file) {
    const uploaded = saveUploadedImageBuffer(req.file, 'profiles');
    if (uploaded) profilePhoto = uploaded;
  }

  db.transaction((txDb) => {
    txDb.update('artist_profiles', profile.id, {
      display_name: displayName,
      biography,
      artist_statement: statement,
      profile_photo: profilePhoto,
      country,
      city,
      discipline,
      years_active: yearsActive,
      website,
      social_instagram: instagram,
      is_seeded: false,
    });

    txDb.update('users', user.id, {
      name: displayName,
      country,
      is_seeded: false,
    });
  });

  req.setFlash('success', 'Your artist profile picture and studio biography have been updated.');
  const returnTo = String(req.body.return_to || '/artist/dashboard').trim();
  res.redirect(returnTo.startsWith('/artist/') ? returnTo : '/artist/dashboard');
});

app.get('/artist/submit', (req, res) => {
  const user = requireRole(req, res, 'artist');
  if (!user) return;
  const db = DatabaseEngine.getInstance();
  ensureArtistProfile(db, user);
  const categories = db.all('categories');
  const settings = db.find('platform_settings', 1) || {};

  let draftSubmission = null;
  let draftArtwork = null;
  if (req.query.edit_id) {
    const sub = db.find('artwork_submissions', Number(req.query.edit_id));
    if (sub && Number(sub.artist_id) === Number(user.id)) {
      draftSubmission = sub;
      draftArtwork = db.find('artworks', Number(sub.artwork_id));
    }
  }

  res.renderPage('artist/submit-artwork', {
    pageTitle: 'Submit Artwork for Curatorial Review — ArtAlly',
    user,
    categories,
    settings,
    draftSubmission,
    draftArtwork,
  });
});

app.post(
  '/artist/submit',
  upload.fields([
    { name: 'artwork_images', maxCount: 10 },
    { name: 'artwork_images[]', maxCount: 10 },
    { name: 'authenticity_document', maxCount: 1 },
  ]),
  (req, res) => {
    const user = requireRole(req, res, 'artist');
    if (!user) return;
    const db = DatabaseEngine.getInstance();
    ensureArtistProfile(db, user, true);

    const actionMode = req.body.submission_mode === 'draft' ? 'draft' : 'submitted';
    const editSubId = Number(req.body.edit_submission_id || 0);

    const title = String(req.body.title || '').trim();
    const categoryId = Number(req.body.category_id || 1);
    const medium = String(req.body.medium || '').trim();
    const yearCreated = Number(req.body.year_created || helpers.gmdate('Y'));
    const dimensions = String(req.body.dimensions || '').trim();
    const classification = String(req.body.classification || 'Original Unique Work').trim();
    const editionNumber = String(req.body.edition_number || 'Unique (1/1)').trim();
    const attribution = String(req.body.artist_attribution || `Signed by ${user.name}`).trim();
    const description = String(req.body.description || '').trim();

    const startingBid = Math.max(100, Number(req.body.suggested_starting_bid || 1000));
    const artistExpectedPrice = Math.max(
      startingBid,
      Number(req.body.artist_expected_price || startingBid * 1.4)
    );
    const reservePrice = Number(req.body.reserve_price || startingBid);
    const durationDays = Math.max(3, Math.min(21, Number(req.body.preferred_duration_days || 7)));
    const currency = ['USD', 'EUR', 'GBP', 'CHF'].includes(req.body.currency)
      ? req.body.currency
      : 'USD';
    const estimateLow = Math.max(startingBid, Number(req.body.estimate_low || startingBid * 1.2));
    const estimateHigh = Math.max(estimateLow, Number(req.body.estimate_high || startingBid * 1.8));
    const sellerNotes = String(req.body.seller_notes || '').trim();

    const provenance = String(req.body.provenance || 'Direct from the studio of the artist.').trim();
    const conditionReport = String(
      req.body.condition_report || 'Pristine original studio condition.'
    ).trim();
    const authenticityNotes = String(
      req.body.authenticity_notes || 'Includes hand-signed Certificate of Authenticity.'
    ).trim();
    const ownershipDeclared = Boolean(req.body.ownership_declared);
    const copyrightConfirmed = Boolean(req.body.copyright_confirmed);

    if (!title || !medium || !dimensions || !description) {
      req.setFlash(
        'error',
        'Please complete all required artwork metadata fields (Title, Medium, Dimensions, and Description).'
      );
      return res.redirect('/artist/submit');
    }

    if (actionMode === 'submitted' && (!ownershipDeclared || !copyrightConfirmed)) {
      req.setFlash(
        'error',
        'You must confirm sole legal ownership/consignment authority and image copyright permissions before submitting.'
      );
      return res.redirect('/artist/submit');
    }

    const galleryImages = [];
    // Prefer client-compressed base64 images when present (handle both multer 'field[]' and urlencoded 'field')
    const rawB64Data = req.body.artwork_images_data ?? req.body['artwork_images_data[]'];
    if (rawB64Data) {
      const b64List = Array.isArray(rawB64Data) ? rawB64Data : [rawB64Data];
      for (const b64Url of b64List) {
        const url = saveBase64Image(String(b64Url), 'artworks');
        if (url) galleryImages.push(url);
      }
    }

    if (galleryImages.length === 0) {
      const uploadedArtworkFiles = [
        ...(req.files?.artwork_images || []),
        ...(req.files?.['artwork_images[]'] || []),
      ];
      for (const fileObj of uploadedArtworkFiles) {
        const url = saveUploadedImageBuffer(fileObj, 'artworks');
        if (url) galleryImages.push(url);
      }
    }

    const rawImageUrls = req.body.image_urls ?? req.body['image_urls[]'];
    const imageUrlsInput = rawImageUrls
      ? Array.isArray(rawImageUrls)
        ? rawImageUrls
        : [rawImageUrls]
      : [];
    for (const rawUrl of imageUrlsInput) {
      const u = String(rawUrl || '').trim();
      if (
        u &&
        (u.startsWith('http://') ||
          u.startsWith('https://') ||
          u.startsWith('/uploads/') ||
          u.startsWith('data:image/'))
      ) {
        galleryImages.push(u);
      }
    }

    const hasUploadedNewImages = galleryImages.length > 0;
    if (!hasUploadedNewImages) {
      galleryImages.push(
        'https://images.unsplash.com/photo-1579783902614-a3fb3927b675?auto=format&fit=crop&w=1400&q=85'
      );
    }

    const primaryIndex = Math.max(0, Number(req.body.primary_image_index || 0));
    const primaryImage = galleryImages[primaryIndex] || galleryImages[0];

    let privateDocPath = null;
    if (req.files?.authenticity_document?.[0]) {
      privateDocPath = savePrivateDocument(req.files.authenticity_document[0], Number(user.id));
    }

    const artworkStatus = actionMode === 'draft' ? 'draft' : 'submitted';

    if (editSubId > 0) {
      const existingSub = db.find('artwork_submissions', editSubId);
      if (existingSub && Number(existingSub.artist_id) === Number(user.id)) {
        const existingArtwork = db.find('artworks', Number(existingSub.artwork_id)) || {};
        const effectivePrimaryImage = hasUploadedNewImages
          ? primaryImage
          : existingArtwork.primary_image || primaryImage;

        db.transaction((txDb) => {
          txDb.update('artworks', existingSub.artwork_id, {
            category_id: categoryId,
            title,
            medium,
            year_created: yearCreated,
            dimensions,
            classification,
            edition_number: editionNumber,
            artist_attribution: attribution,
            description,
            provenance,
            condition_report: conditionReport,
            primary_image: effectivePrimaryImage,
            currency,
            estimate_low: estimateLow,
            estimate_high: estimateHigh,
            starting_bid: startingBid,
            artist_expected_price: artistExpectedPrice,
            reserve_price: reservePrice,
            status: artworkStatus,
            is_seeded: false,
          });

          if (hasUploadedNewImages) {
            const oldImages = txDb.where(
              'artwork_images',
              (img) => Number(img.artwork_id) === Number(existingSub.artwork_id)
            );
            for (const oldImg of oldImages) {
              txDb.delete('artwork_images', oldImg.id);
            }
            galleryImages.forEach((imgUrl, idx) => {
              txDb.insert('artwork_images', {
                artwork_id: Number(existingSub.artwork_id),
                image_url: imgUrl,
                is_primary: imgUrl === effectivePrimaryImage,
                sort_order: idx + 1,
              });
            });
          }

          txDb.update('artwork_submissions', existingSub.id, {
            suggested_starting_bid: startingBid,
            artist_expected_price: artistExpectedPrice,
            reserve_price: reservePrice,
            preferred_duration_days: durationDays,
            currency,
            estimate_low: estimateLow,
            estimate_high: estimateHigh,
            seller_notes: sellerNotes,
            ownership_declared: ownershipDeclared,
            copyright_confirmed: copyrightConfirmed,
            authenticity_notes: authenticityNotes,
            status: artworkStatus,
            is_seeded: false,
          });
        });

        req.setFlash(
          'success',
          `Submission ${existingSub.reference_code} has been updated (${artworkStatus.toUpperCase()}).`
        );
        return res.redirect('/artist/submissions');
      }
    }

    let refCode = '';
    db.transaction((txDb) => {
      const randomSuffix = Math.floor(100 + Math.random() * 900);
      const artwork = txDb.insert('artworks', {
        artist_id: Number(user.id),
        category_id: categoryId,
        title,
        slug: helpers.slugify(`${title}-${randomSuffix}`),
        medium,
        year_created: yearCreated,
        dimensions,
        classification,
        edition_number: editionNumber,
        artist_attribution: attribution,
        description,
        provenance,
        condition_report: conditionReport,
        primary_image: primaryImage,
        currency,
        estimate_low: estimateLow,
        estimate_high: estimateHigh,
        starting_bid: startingBid,
        artist_expected_price: artistExpectedPrice,
        agreed_price: null,
        price_agreement_status: 'pending',
        reserve_price: reservePrice,
        status: artworkStatus,
        featured: false,
        is_seeded: false,
      });

      galleryImages.forEach((imgUrl, idx) => {
        txDb.insert('artwork_images', {
          artwork_id: Number(artwork.id),
          image_url: imgUrl,
          is_primary: imgUrl === primaryImage,
          sort_order: idx + 1,
        });
      });

      if (privateDocPath) {
        txDb.insert('artwork_documents', {
          artwork_id: Number(artwork.id),
          artist_id: Number(user.id),
          private_path: privateDocPath,
          document_type: 'Certificate of Authenticity / Provenance Dossier',
        });
      }

      refCode = helpers.sprintf('AN-%s-%04d', helpers.gmdate('Y'), 1000 + Number(artwork.id));
      txDb.insert('artwork_submissions', {
        reference_code: refCode,
        artwork_id: Number(artwork.id),
        artist_id: Number(user.id),
        suggested_starting_bid: startingBid,
        artist_expected_price: artistExpectedPrice,
        agreed_price: null,
        price_agreement_status: 'pending',
        reserve_price: reservePrice,
        preferred_duration_days: durationDays,
        currency,
        estimate_low: estimateLow,
        estimate_high: estimateHigh,
        seller_notes: sellerNotes,
        ownership_declared: ownershipDeclared,
        copyright_confirmed: copyrightConfirmed,
        authenticity_notes: authenticityNotes,
        status: artworkStatus,
        admin_feedback:
          actionMode === 'draft'
            ? 'Saved as studio draft. Submit when ready for curatorial evaluation.'
            : 'Received by the ArtAlly Curatorial Review Board. Typical evaluation time is 24–48 hours.',
        reviewed_by: null,
        is_seeded: false,
      });

      if (actionMode === 'submitted') {
        txDb.insert('notifications', {
          user_id: Number(user.id),
          type: 'submission_received',
          title: `Submission Received (${refCode})`,
          message: `Your artwork "${title}" has been logged for specialist curatorial review.`,
          action_url: '/artist/submissions',
          read_at: null,
        });

        txDb.insert('notifications', {
          user_id: 1,
          type: 'submission_received',
          title: `New Consignment Submission: ${refCode}`,
          message: `${user.name} submitted "${title}" for auction evaluation.`,
          action_url: '/admin/dashboard#artists',
          read_at: null,
        });
      }
    });

    req.setFlash(
      'success',
      actionMode === 'draft'
        ? `Draft "${title}" saved (${refCode}).`
        : `Consignment submission ${refCode} received for curatorial review. Works are published only after specialist approval.`
    );
    res.redirect('/artist/dashboard');
  }
);

app.get('/artist/submissions', (req, res) => {
  const user = requireRole(req, res, 'artist');
  if (!user) return;
  const db = DatabaseEngine.getInstance();
  const submissions = db
    .where('artwork_submissions', (s) => Number(s.artist_id) === Number(user.id))
    .map((sub) => ({
      ...sub,
      artwork: db.find('artworks', Number(sub.artwork_id)) || {},
      auction: db.findBy('auctions', 'artwork_id', Number(sub.artwork_id)),
    }))
    .sort((a, b) => Number(b.id) - Number(a.id));

  res.renderPage('artist/submissions', {
    pageTitle: 'Artwork Submissions & Curatorial Status — ArtAlly',
    user,
    submissions,
  });
});

app.post('/artist/submissions/withdraw', (req, res) => {
  const user = requireRole(req, res, 'artist');
  if (!user) return;
  const db = DatabaseEngine.getInstance();
  const subId = Number(req.body.submission_id || 0);
  const sub = db.find('artwork_submissions', subId);

  if (!sub || Number(sub.artist_id) !== Number(user.id)) {
    req.setFlash('error', 'Submission not found.');
    return res.redirect('/artist/submissions');
  }
  if ((sub.status || '') === 'published') {
    req.setFlash(
      'error',
      'Published lots with active auctions cannot be withdrawn without administrator intervention.'
    );
    return res.redirect('/artist/submissions');
  }

  db.update('artwork_submissions', subId, { status: 'withdrawn' });
  db.update('artworks', Number(sub.artwork_id), { status: 'withdrawn' });
  req.setFlash('success', `Submission ${sub.reference_code} has been withdrawn.`);
  res.redirect('/artist/submissions');
});

app.post('/artist/submissions/negotiate', (req, res) => {
  const user = requireRole(req, res, 'artist');
  if (!user) return;
  const db = DatabaseEngine.getInstance();
  const subId = Number(req.body.submission_id || 0);
  const responseAction = String(req.body.response_action || req.body.response_type || 'accept').trim();
  const counterPrice = Number(req.body.counter_price || 0);
  const artistMessage = String(req.body.artist_message || '').trim();

  const sub = db.find('artwork_submissions', subId);
  if (!sub || Number(sub.artist_id) !== Number(user.id)) {
    req.setFlash('error', 'Submission record not found.');
    return res.redirect('/artist/dashboard');
  }

  const artwork = db.find('artworks', Number(sub.artwork_id)) || {};
  const messages = Array.isArray(sub.negotiation_messages) ? [...sub.negotiation_messages] : [];

  if (responseAction === 'accept') {
    const agreed = Number(sub.agreed_price ?? artwork.agreed_price ?? sub.artist_expected_price ?? 0);
    messages.push({
      sender: 'artist',
      sender_name: user.name,
      text: `✓ Artist formally ACCEPTED the House Agreed Price of ${helpers.format_money(agreed)}.${
        artistMessage ? ` Note: ${artistMessage}` : ''
      }`,
      created_at: formatUtcNow(),
    });

    db.update('artwork_submissions', subId, {
      price_agreement_status: 'accepted_by_artist',
      negotiation_messages: messages,
    });
    db.update('artworks', Number(sub.artwork_id), {
      price_agreement_status: 'accepted_by_artist',
    });

    db.insert('notifications', {
      user_id: 1,
      type: 'price_accepted',
      title: `Artist Accepted Agreed Price: ${artwork.title || sub.reference_code}`,
      message: `${user.name} accepted the agreed price of ${helpers.format_money(agreed)} for "${
        artwork.title
      }".`,
      action_url: '/admin/dashboard#artists',
      read_at: null,
    });

    req.setFlash(
      'success',
      `You have formally accepted the House Agreed Price of ${helpers.format_money(agreed)}.`
    );
  } else {
    const newExpected = counterPrice > 0 ? counterPrice : Number(sub.artist_expected_price || 0);
    messages.push({
      sender: 'artist',
      sender_name: user.name,
      text: `Artist proposed counter-valuation of ${helpers.format_money(newExpected)}: ${
        artistMessage || 'Please review my updated target price.'
      }`,
      created_at: formatUtcNow(),
    });

    db.transaction((txDb) => {
      txDb.update('artwork_submissions', subId, {
        artist_expected_price: newExpected,
        price_agreement_status: 'counter_proposed_by_artist',
        negotiation_messages: messages,
      });
      txDb.update('artworks', Number(sub.artwork_id), {
        artist_expected_price: newExpected,
        price_agreement_status: 'counter_proposed_by_artist',
      });

      txDb.insert('notifications', {
        user_id: 1,
        type: 'price_counter',
        title: `Artist Counter-Offer on ${artwork.title || sub.reference_code}`,
        message: `${user.name} sent a counter-offer of ${helpers.format_money(
          newExpected
        )}: ${artistMessage}`,
        action_url: '/admin/dashboard#artists',
        read_at: null,
      });
    });

    req.setFlash(
      'success',
      `Your counter-offer (${helpers.format_money(
        newExpected
      )}) and note have been sent to the Administrator.`
    );
  }

  res.redirect('/artist/dashboard');
});

app.get('/artist/sales', (req, res) => {
  const user = requireRole(req, res, 'artist');
  if (!user) return;
  const db = DatabaseEngine.getInstance();
  const profile = ensureArtistProfile(db, user);
  const walletState = computeArtistWalletState(db, Number(user.id), profile);
  const orders = db
    .where('orders', (o) => Number(o.artist_id) === Number(user.id))
    .map((ord) => ({
      ...ord,
      artwork: db.find('artworks', Number(ord.artwork_id)) || {},
    }));
  const payouts = db.where('seller_payouts', (p) => Number(p.artist_id) === Number(user.id));

  res.renderPage('artist/sales', {
    pageTitle: 'Sales Ledgers, Wallet & Seller Payouts — ArtAlly',
    user,
    profile,
    walletState,
    walletWithdrawals: walletState.withdrawals,
    orders,
    payouts,
  });
});

app.post('/artist/wallet/withdraw', (req, res) => {
  const user = requireRole(req, res, 'artist');
  if (!user) return;
  const db = DatabaseEngine.getInstance();
  const profile = ensureArtistProfile(db, user);
  const walletState = computeArtistWalletState(db, Number(user.id), profile);
  const returnTo = String(req.body.return_to || '/artist/dashboard#artist-wallet').trim();
  const safeReturnTo = returnTo.startsWith('/artist/') ? returnTo : '/artist/dashboard#artist-wallet';

  if (!walletState.withdrawal_unlocked) {
    req.setFlash(
      'error',
      'Withdrawal Locked: Your wallet withdrawal option is currently locked by default. Please wait for ArtAlly Administration to grant withdrawal access from the backend.'
    );
    return res.redirect(safeReturnTo);
  }

  if (walletState.available_balance <= 0) {
    req.setFlash('error', 'Insufficient available wallet balance to initiate a withdrawal.');
    return res.redirect(safeReturnTo);
  }

  const requestedAmount = Number(req.body.amount || walletState.available_balance);
  const amount = Math.round(Math.min(walletState.available_balance, Math.max(0, requestedAmount)) * 100) / 100;
  if (amount <= 0) {
    req.setFlash('error', 'Please enter a valid withdrawal amount up to your available wallet balance.');
    return res.redirect(safeReturnTo);
  }

  const payoutMethod = req.body.payout_method === 'crypto' ? 'crypto' : 'bank_wire';
  const bankAccountName = String(req.body.bank_account_name || '').trim();
  const bankName = String(req.body.bank_name || '').trim();
  const bankAddress = String(req.body.bank_address || '').trim();
  const bankAccountNumber = String(req.body.bank_account_number || '').trim();
  const bankRoutingSwift = String(req.body.bank_routing_swift || '').trim();

  const cryptoCurrency = String(req.body.crypto_currency || '').trim();
  const cryptoNetwork = String(req.body.crypto_network || '').trim();
  const cryptoWalletAddress = String(req.body.crypto_wallet_address || '').trim();
  const cryptoMemo = String(req.body.crypto_memo || '').trim();
  const artistNotes = String(req.body.artist_notes || '').trim();

  if (payoutMethod === 'bank_wire') {
    if (!bankAccountName || !bankName || !bankAccountNumber) {
      req.setFlash(
        'error',
        'Please complete all required Bank Wire fields: Beneficiary / Account Holder Name, Bank Name, and Account Number / IBAN.'
      );
      return res.redirect(safeReturnTo);
    }
  } else {
    if (!cryptoCurrency || !cryptoNetwork || !cryptoWalletAddress) {
      req.setFlash(
        'error',
        'Please complete all required Cryptocurrency fields: Cryptocurrency / Asset, Blockchain Network, and Receiving Wallet Address.'
      );
      return res.redirect(safeReturnTo);
    }
  }

  const refCode = `WD-${helpers.gmdate('Y')}-${Math.floor(10000 + Math.random() * 90000)}`;
  const withdrawal = db.insert('wallet_withdrawals', {
    reference_code: refCode,
    artist_id: Number(user.id),
    artist_name: profile.display_name || user.name,
    artist_email: user.email || '',
    amount,
    currency: 'USD',
    payout_method: payoutMethod,
    bank_account_name: bankAccountName,
    bank_name: bankName,
    bank_address: bankAddress,
    bank_account_number: bankAccountNumber,
    bank_routing_swift: bankRoutingSwift,
    crypto_currency: cryptoCurrency,
    crypto_network: cryptoNetwork,
    crypto_wallet_address: cryptoWalletAddress,
    crypto_memo: cryptoMemo,
    artist_notes: artistNotes,
    status: 'processing',
    estimated_timeframe: '2 to 7 business days',
    processed_by: null,
    processed_at: null,
  });

  computeArtistWalletState(db, Number(user.id), profile);

  const methodSummary =
    payoutMethod === 'crypto'
      ? `Cryptocurrency (${cryptoCurrency} on ${cryptoNetwork} · Wallet: ${cryptoWalletAddress})`
      : `Bank Wire (${bankName} · Account: ${bankAccountNumber}${bankAddress ? ` · Address: ${bankAddress}` : ''})`;

  db.insert('notifications', {
    user_id: Number(user.id),
    type: 'withdrawal_processing',
    title: `⟳ Withdrawal Processing: ${helpers.format_money_exact(amount)} (${refCode})`,
    message: `Your withdrawal request of ${helpers.format_money_exact(
      amount
    )} via ${methodSummary} has been received and is now processing. Please allow 2 to 7 business days for completion.`,
    action_url: '/artist/dashboard#artist-wallet',
    read_at: null,
  });

  db.insert('notifications', {
    user_id: 1,
    type: 'artist_withdrawal_submitted',
    title: `💸 Artist Withdrawal Request: ${helpers.format_money_exact(amount)} (${refCode})`,
    message: `${profile.display_name || user.name} submitted a withdrawal request of ${helpers.format_money_exact(
      amount
    )} via ${methodSummary}.`,
    action_url: '/admin/dashboard#payouts',
    read_at: null,
  });

  logAudit(
    db,
    user,
    'ARTIST_WALLET_WITHDRAWAL_REQUESTED',
    'wallet_withdrawal',
    withdrawal.id,
    `${profile.display_name || user.name} requested withdrawal ${refCode} of $${amount.toFixed(2)} via ${methodSummary}.`,
    req.ip
  );

  req.setFlash(
    'success',
    `Withdrawal Processing (${refCode}): Your withdrawal request of ${helpers.format_money_exact(
      amount
    )} has been submitted to ArtAlly Treasury. It will take 2 to 7 business days to complete.`
  );
  res.redirect(safeReturnTo);
});

app.get('/certificates/:id', (req, res) => {
  const user = requireAuth(req, res);
  if (!user) return;
  const db = DatabaseEngine.getInstance();
  const artworkId = Number(req.params.id || 0);
  const artwork = db.find('artworks', artworkId);

  if (!artwork) {
    return res.renderPage('pages/404', { pageTitle: 'Certificate Not Found' }, 404);
  }

  const auction = db.findBy('auctions', 'artwork_id', artworkId);
  const order = db.findBy('orders', 'artwork_id', artworkId);
  const artistUser = db.find('users', Number(artwork.artist_id)) || {};
  const artistProfile = db.findBy('artist_profiles', 'user_id', Number(artwork.artist_id)) || {};
  const buyerUser = order ? db.find('users', Number(order.buyer_id)) || {} : {};

  const isAllowed =
    helpers.userHasRole(user, 'admin') ||
    Number(user.id) === Number(artwork.artist_id) ||
    (order && Number(user.id) === Number(order.buyer_id));

  if (!isAllowed) {
    req.setFlash('error', 'You are not authorized to view this confidential certificate.');
    return res.redirect('/');
  }

  res.renderPage('public/certificate', {
    pageTitle: `Certificate of Sale & Authenticity — ${artwork.title}`,
    artwork,
    auction,
    order,
    artistUser,
    artistProfile,
    buyerUser,
  });
});

// ============================================================================
// 4. BUYER / COLLECTOR ROUTES
// ============================================================================

app.get('/buyer/dashboard', (req, res) => {
  const user = requireAuth(req, res);
  if (!user) return;
  const db = DatabaseEngine.getInstance();

  const watchlistRows = db.where('watchlists', (w) => Number(w.user_id) === Number(user.id));
  const watchedArtworks = [];
  for (const w of watchlistRows) {
    const art = db.find('artworks', Number(w.artwork_id));
    if (art) watchedArtworks.push(enrichArtwork(db, art));
  }

  const myBids = db
    .where('bids', (b) => Number(b.user_id) === Number(user.id))
    .sort((a, b) => Number(b.id) - Number(a.id))
    .map((b) => {
      const art = db.find('artworks', Number(b.artwork_id));
      const auc = db.find('auctions', Number(b.auction_id));
      return {
        ...b,
        artwork: art,
        auction: auc,
        is_leading: Boolean(auc && Number(auc.highest_bidder_id || 0) === Number(user.id)),
      };
    });

  const orders = db
    .where('orders', (o) => Number(o.buyer_id) === Number(user.id))
    .map((ord) => ({
      ...ord,
      artwork: db.find('artworks', Number(ord.artwork_id)),
    }));

  const notifications = db
    .where('notifications', (n) => Number(n.user_id) === Number(user.id))
    .sort((a, b) => Number(b.id) - Number(a.id));

  const followedRows = db.where('followed_artists', (f) => Number(f.user_id) === Number(user.id));
  const followedArtists = [];
  for (const fr of followedRows) {
    const ap = db.find('artist_profiles', Number(fr.artist_profile_id));
    if (ap) followedArtists.push(ap);
  }

  res.renderPage('buyer/dashboard', {
    pageTitle: 'Collector Private Client Dashboard — ArtAlly',
    user,
    watchedArtworks,
    myBids,
    orders,
    notifications,
    followedArtists,
  });
});

app.get('/buyer/watchlist', (req, res) => {
  const db = DatabaseEngine.getInstance();
  const user = req.getCurrentUser();
  const ids = user
    ? db
        .where('watchlists', (w) => Number(w.user_id) === Number(user.id))
        .map((w) => Number(w.artwork_id))
    : req.sessionData.guest_watchlist || [];

  const watchedArtworks = [];
  for (const id of ids) {
    const art = db.find('artworks', Number(id));
    if (art) watchedArtworks.push(enrichArtwork(db, art));
  }

  res.renderPage('buyer/watchlist', {
    pageTitle: 'Saved Lots & Collector Watchlist — ArtAlly',
    user,
    watchedArtworks,
  });
});

app.post('/buyer/watchlist/toggle', (req, res) => {
  const db = DatabaseEngine.getInstance();
  const artworkId = Number(req.body.artwork_id || 0);
  const returnUrl = req.body.return_url || req.get('referer') || '/catalogue';
  if (artworkId <= 0) return res.redirect(returnUrl);

  const user = req.getCurrentUser();
  if (!user) {
    const guestList = Array.isArray(req.sessionData.guest_watchlist)
      ? [...req.sessionData.guest_watchlist]
      : [];
    if (guestList.includes(artworkId)) {
      req.sessionData.guest_watchlist = guestList.filter((id) => id !== artworkId);
      req.setFlash('success', 'Removed lot from your session watchlist.');
    } else {
      guestList.push(artworkId);
      req.sessionData.guest_watchlist = Array.from(new Set(guestList));
      req.setFlash(
        'success',
        'Saved lot to your watchlist. Sign in to receive closing & outbid alerts.'
      );
    }
    return res.redirect(returnUrl);
  }

  const existing = db.where(
    'watchlists',
    (w) => Number(w.user_id) === Number(user.id) && Number(w.artwork_id) === artworkId
  );

  if (existing.length > 0) {
    for (const item of existing) {
      db.delete('watchlists', item.id);
    }
    req.setFlash('success', 'Removed artwork from your Collector Watchlist.');
  } else {
    db.insert('watchlists', {
      user_id: Number(user.id),
      artwork_id: artworkId,
    });
    req.setFlash('success', 'Added artwork to your Collector Watchlist.');
  }

  res.redirect(returnUrl);
});

app.post('/buyer/artists/follow', (req, res) => {
  const user = requireAuth(req, res);
  if (!user) return;
  const db = DatabaseEngine.getInstance();
  const artistProfileId = Number(req.body.artist_profile_id || 0);
  const returnUrl = req.get('referer') || '/artists';

  const existing = db.where(
    'followed_artists',
    (f) => Number(f.user_id) === Number(user.id) && Number(f.artist_profile_id) === artistProfileId
  );

  if (existing.length > 0) {
    for (const row of existing) {
      db.delete('followed_artists', row.id);
    }
    req.setFlash('success', 'Unfollowed artist.');
  } else {
    db.insert('followed_artists', {
      user_id: Number(user.id),
      artist_profile_id: artistProfileId,
    });
    req.setFlash(
      'success',
      'You are now following this artist. You will be alerted when new works are published.'
    );
  }

  res.redirect(returnUrl);
});

app.post('/buyer/bids/place', (req, res) => {
  const user = requireAuth(req, res);
  if (!user) return;
  const db = DatabaseEngine.getInstance();
  const auctionId = Number(req.body.auction_id || 0);
  const amount = Number(req.body.bid_amount || 0);

  const auction = db.find('auctions', auctionId);
  const artwork = auction ? db.find('artworks', Number(auction.artwork_id)) : null;
  const returnUrl = artwork ? `/artworks/${artwork.slug}` : '/auctions';

  try {
    const result = AuctionEngineService.placeBid(
      auctionId,
      Number(user.id),
      amount,
      req.ip || '127.0.0.1'
    );
    let msg = `Your bid of ${helpers.format_money(
      amount,
      auction?.currency || 'USD'
    )} has been recorded as the leading bid.`;
    if (result.extended) {
      msg += ' (Anti-sniping protection triggered: auction closing time extended by 5 minutes.)';
    }
    req.setFlash('success', msg);
  } catch (err) {
    req.setFlash('error', err.message);
  }

  res.redirect(returnUrl);
});

app.get('/buyer/orders', (req, res) => {
  const user = requireAuth(req, res);
  if (!user) return;
  const db = DatabaseEngine.getInstance();
  const orders = db
    .where('orders', (o) => Number(o.buyer_id) === Number(user.id))
    .map((ord) => ({
      ...ord,
      artwork: db.find('artworks', Number(ord.artwork_id)),
      payments: db.where('payments', (p) => Number(p.order_id) === Number(ord.id)),
    }));

  res.renderPage('buyer/orders', {
    pageTitle: 'Acquisitions, Invoices & Settlement — ArtAlly',
    user,
    orders,
  });
});

app.post('/buyer/orders/pay', (req, res) => {
  const user = requireAuth(req, res);
  if (!user) return;
  const db = DatabaseEngine.getInstance();
  const orderId = Number(req.body.order_id || 0);
  const order = db.find('orders', orderId);

  if (!order || Number(order.buyer_id) !== Number(user.id)) {
    req.setFlash('error', 'Order invoice not found.');
    return res.redirect('/buyer/orders');
  }
  if ((order.payment_status || '') === 'paid') {
    req.setFlash('success', 'This acquisition invoice has already been settled.');
    return res.redirect('/buyer/orders');
  }

  const stripeConfigured = Boolean(process.env.STRIPE_SECRET_KEY);
  const txRef = stripeConfigured
    ? `pi_live_${crypto.randomBytes(8).toString('hex')}`
    : `pi_sandbox_verified_${crypto.randomBytes(6).toString('hex')}`;

  db.update('orders', orderId, {
    payment_status: 'paid',
    fulfillment_status: 'preparing_crate',
    paid_at: formatUtcNow(),
  });

  db.insert('payments', {
    order_id: orderId,
    buyer_id: Number(user.id),
    provider: stripeConfigured ? 'stripe_live' : 'stripe_sandbox_checkout',
    transaction_reference: txRef,
    amount: Number(order.total_amount),
    currency: order.currency || 'USD',
    status: 'paid',
    is_seeded: false,
  });

  db.insert('notifications', {
    user_id: Number(user.id),
    type: 'payment_confirmed',
    title: `Invoice Settled: ${order.order_number}`,
    message: `Payment of ${helpers.format_money_exact(
      order.total_amount
    )} (Ref: ${txRef}) has been confirmed. White-glove museum crating is underway.`,
    action_url: '/buyer/orders',
    read_at: null,
  });

  db.insert('notifications', {
    user_id: Number(order.artist_id),
    type: 'payment_confirmed',
    title: `Buyer Settlement Confirmed: ${order.order_number}`,
    message: `The buyer has settled invoice ${
      order.order_number
    }. Your net artist payout of ${helpers.format_money_exact(
      order.net_artist_payout
    )} is now eligible for disbursement.`,
    action_url: '/artist/sales',
    read_at: null,
  });

  req.setFlash(
    'success',
    `Invoice ${order.order_number} marked as Paid (Ref: ${txRef}). Museum crating and dispatch initiated.`
  );
  res.redirect('/buyer/orders');
});

app.post('/buyer/notifications/read', (req, res) => {
  const user = requireAuth(req, res);
  if (!user) return;
  const db = DatabaseEngine.getInstance();
  const notifId = Number(req.body.notification_id || 0);

  if (notifId === -1) {
    const all = db.where('notifications', (n) => Number(n.user_id) === Number(user.id));
    for (const n of all) {
      db.update('notifications', n.id, { read_at: formatUtcNow() });
    }
    req.setFlash('success', 'All notifications marked as read.');
  } else {
    const n = db.find('notifications', notifId);
    if (n && Number(n.user_id) === Number(user.id)) {
      db.update('notifications', notifId, { read_at: formatUtcNow() });
    }
  }

  res.redirect(req.get('referer') || '/buyer/dashboard');
});

app.post('/buyer/enable-artist', (req, res) => {
  const user = requireAuth(req, res);
  if (!user) return;
  const db = DatabaseEngine.getInstance();
  const roles = Array.isArray(user.roles) ? [...user.roles] : [user.role || 'buyer'];
  if (!roles.includes('artist')) roles.push('artist');

  db.update('users', user.id, {
    role: 'artist',
    roles: Array.from(new Set(roles)),
  });

  ensureArtistProfile(db, user);
  req.setFlash(
    'success',
    'Artist Studio capabilities have been enabled on your account. You can now consign and submit original works.'
  );
  res.redirect('/artist/dashboard');
});

// ============================================================================
// 5. ADMINISTRATOR ROUTES
// ============================================================================

function logAudit(db, adminUser, action, targetType, targetId, details, ip = '127.0.0.1') {
  db.insert('audit_logs', {
    user_id: Number(adminUser.id),
    actor_name: adminUser.name,
    action,
    target_type: targetType,
    target_id: targetId,
    details,
    ip_address: ip,
  });
}

function handleShowAdminLogin(req, res) {
  const user = req.getCurrentUser();
  if (user && helpers.userHasRole(user, 'admin')) {
    return res.redirect('/admin/dashboard');
  }
  const db = DatabaseEngine.getInstance();
  const existingAdmins = db.where(
    'users',
    (u) => (u.role || '') === 'admin' || (Array.isArray(u.roles) && u.roles.includes('admin'))
  );
  res.renderPage('admin/login', {
    pageTitle: 'Executive Admin Portal Login — ArtAlly',
    noAdminsExist: existingAdmins.length === 0,
  });
}

app.get('/admin', handleShowAdminLogin);
app.get('/admin/login', handleShowAdminLogin);

app.post('/admin/login', async (req, res) => {
  const db = DatabaseEngine.getInstance();
  const email = String(req.body.email || '').trim();
  const password = String(req.body.password || '');

  const user = await db.findUserByEmailFromDb(email);
  if (!user || !verifyPassword(password, user.password)) {
    req.setFlash('error', 'Invalid administrator credentials.');
    return res.redirect('/admin/login');
  }

  if (!helpers.userHasRole(user, 'admin')) {
    req.setFlash('error', 'Access denied. This portal is restricted to authorized administrators.');
    return res.redirect('/admin/login');
  }

  req.loginUser(user);
  req.setFlash('success', 'Authenticated into ArtAlly Executive Governance Suite.');
  res.redirect('/admin/dashboard');
});

app.post('/admin/setup-first', (req, res) => {
  const db = DatabaseEngine.getInstance();
  const existingAdmins = db.where(
    'users',
    (u) => (u.role || '') === 'admin' || (Array.isArray(u.roles) && u.roles.includes('admin'))
  );
  if (existingAdmins.length > 0) {
    req.setFlash('error', 'An administrator account already exists. Please sign in.');
    return res.redirect('/admin/login');
  }

  const name = String(req.body.name || 'Chief Administrator').trim();
  const email = String(req.body.email || '').trim().toLowerCase();
  const password = String(req.body.password || '');

  if (!name || !email.includes('@') || password.length < 8) {
    req.setFlash('error', 'Please enter a valid name, email, and password (at least 8 characters).');
    return res.redirect('/admin/login');
  }

  const admin = db.insert('users', {
    name,
    email,
    password: hashPassword(password),
    country: 'Switzerland',
    role: 'admin',
    roles: ['admin', 'buyer', 'artist'],
    email_verified_at: formatUtcNow(),
    bidding_approved: true,
    status: 'active',
    is_seeded: false,
  });

  req.loginUser(admin);
  req.setFlash('success', 'New Master Administrator Profile created and signed in.');
  res.redirect('/admin/dashboard');
});

function handleAdminDashboard(req, res) {
  const admin = requireRole(req, res, 'admin');
  if (!admin) return;
  const db = DatabaseEngine.getInstance();

  const allUsers = db.all('users');
  const allArtworks = db.all('artworks');
  const allSubmissions = db.all('artwork_submissions');

  // Include any user who is not seeded OR who has uploaded a non-seeded artwork/submission
  const activeArtistUserIds = new Set();
  for (const a of allArtworks) {
    if (!a.is_seeded && Number(a.artist_id || 0) > 0) {
      activeArtistUserIds.add(Number(a.artist_id));
    }
  }
  for (const s of allSubmissions) {
    if (!s.is_seeded && Number(s.artist_id || 0) > 0) {
      activeArtistUserIds.add(Number(s.artist_id));
    }
  }

  // Ensure every active artist who uploaded work has an artist_profile row
  for (const artistUserId of activeArtistUserIds) {
    const artistUser = db.find('users', artistUserId);
    if (artistUser) {
      ensureArtistProfile(db, artistUser, true);
    }
  }

  const users = allUsers.filter(
    (u) => !u.is_seeded || (u.role || '') === 'admin' || activeArtistUserIds.has(Number(u.id))
  );
  const realUserIds = allUsers
    .filter((u) => !u.is_seeded || activeArtistUserIds.has(Number(u.id)))
    .map((u) => Number(u.id));

  const artworks = allArtworks.filter(
    (a) => !a.is_seeded || realUserIds.includes(Number(a.artist_id || 0))
  );
  const realArtworkIds = artworks.map((a) => Number(a.id));

  // Ensure every real artwork has a corresponding submission record so it is manageable in Admin
  for (const w of artworks) {
    if (!w.is_seeded) {
      const existingSub = db.findBy('artwork_submissions', 'artwork_id', Number(w.id));
      if (!existingSub) {
        const refCode = helpers.sprintf('AN-%s-%04d', helpers.gmdate('Y'), 1000 + Number(w.id));
        db.insert('artwork_submissions', {
          reference_code: refCode,
          artwork_id: Number(w.id),
          artist_id: Number(w.artist_id),
          suggested_starting_bid: Number(w.starting_bid || 1000),
          artist_expected_price: Number(w.artist_expected_price || w.starting_bid || 1000),
          agreed_price: w.agreed_price || null,
          price_agreement_status: w.price_agreement_status || 'pending',
          reserve_price: Number(w.reserve_price || w.starting_bid || 1000),
          preferred_duration_days: 7,
          currency: w.currency || 'USD',
          estimate_low: Number(w.estimate_low || 1200),
          estimate_high: Number(w.estimate_high || 1800),
          seller_notes: '',
          ownership_declared: true,
          copyright_confirmed: true,
          authenticity_notes: 'Includes Certificate of Authenticity.',
          status: w.status || 'submitted',
          admin_feedback: 'Received by the ArtAlly Curatorial Review Board.',
          reviewed_by: null,
          is_seeded: false,
        });
      }
    }
  }

  const allArtistProfiles = db.all('artist_profiles');
  const artistProfiles = allArtistProfiles
    .filter((ap) => {
      const apUser = db.find('users', Number(ap.user_id));
      if (
        apUser &&
        (apUser.role || '') === 'admin' &&
        !activeArtistUserIds.has(Number(ap.user_id || 0))
      ) {
        return false;
      }
      return (
        (realUserIds.includes(Number(ap.user_id || 0)) && !ap.is_seeded) ||
        activeArtistUserIds.has(Number(ap.user_id || 0))
      );
    })
    .map((ap) => {
      const apWorks = db
        .where('artworks', (a) => Number(a.artist_id) === Number(ap.user_id) && (!a.is_seeded || !ap.is_seeded))
        .sort((a, b) => Number(b.id) - Number(a.id))
        .map((w) => ({
          ...w,
          submission: db.findBy('artwork_submissions', 'artwork_id', Number(w.id)),
          auction: db.findBy('auctions', 'artwork_id', Number(w.id)),
          images: db.where('artwork_images', (img) => Number(img.artwork_id) === Number(w.id)),
        }));
      const walletState = computeArtistWalletState(db, Number(ap.user_id), ap);
      return {
        ...ap,
        user: db.find('users', Number(ap.user_id)) || {},
        artworks: apWorks,
        wallet_state: walletState,
        withdrawals: walletState.withdrawals,
      };
    });

  const submissions = db
    .all('artwork_submissions')
    .filter(
      (s) =>
        !s.is_seeded ||
        realArtworkIds.includes(Number(s.artwork_id || 0)) ||
        realUserIds.includes(Number(s.artist_id || 0))
    )
    .map((sub) => ({
      ...sub,
      artwork: db.find('artworks', Number(sub.artwork_id)) || {},
      artist: db.find('users', Number(sub.artist_id)) || {},
      artist_profile: db.findBy('artist_profiles', 'user_id', Number(sub.artist_id)) || {},
      auction: db.findBy('auctions', 'artwork_id', Number(sub.artwork_id)),
      images: db.where('artwork_images', (img) => Number(img.artwork_id) === Number(sub.artwork_id)),
    }))
    .sort((a, b) => Number(b.id) - Number(a.id));

  const auctions = db
    .all('auctions')
    .filter((a) => !a.is_seeded || realArtworkIds.includes(Number(a.artwork_id || 0)))
    .map((auc) => ({
      ...auc,
      artwork: db.find('artworks', Number(auc.artwork_id)) || {},
      artist: db.find('users', Number(auc.artist_id)) || {},
      submission: db.findBy('artwork_submissions', 'artwork_id', Number(auc.artwork_id)),
    }));

  const bids = db.all('bids').filter((b) => !b.is_seeded);
  const orders = db
    .all('orders')
    .filter((o) => !o.is_seeded)
    .map((ord) => ({
      ...ord,
      artwork: db.find('artworks', Number(ord.artwork_id)) || {},
      buyer: db.find('users', Number(ord.buyer_id)) || {},
      artist: db.find('users', Number(ord.artist_id)) || {},
    }));

  const payouts = db
    .all('seller_payouts')
    .filter((p) => !p.is_seeded)
    .map((po) => {
      const artistProfile = db.findBy('artist_profiles', 'user_id', Number(po.artist_id)) || {};
      return {
        ...po,
        artist: db.find('users', Number(po.artist_id)) || {},
        artist_profile: artistProfile,
        wallet_state: computeArtistWalletState(db, Number(po.artist_id), artistProfile),
        order: db.find('orders', Number(po.order_id)) || {},
      };
    });

  const walletWithdrawals = db
    .all('wallet_withdrawals')
    .sort((a, b) => Number(b.id) - Number(a.id));

  const auditLogs = db.all('audit_logs').sort((a, b) => Number(b.id) - Number(a.id));
  const reports = db.all('reports');
  const subscribers = db.all('newsletter_subscribers');
  const settings = db.find('platform_settings', 1) || {};
  const authInvoices = db
    .all('authentication_invoices')
    .sort((a, b) => Number(b.id) - Number(a.id));
  const allArtistsList = allArtistProfiles.map((ap) => ({
    ...ap,
    user: db.find('users', Number(ap.user_id)) || {},
  }));
  const allArtworksList = allArtworks.map((art) => ({
    ...art,
    artist_user: db.find('users', Number(art.artist_id)) || {},
    submission: db.findBy('artwork_submissions', 'artwork_id', Number(art.id)) || {},
  }));

  const stats = {
    total_users: users.length,
    total_artists: artistProfiles.length,
    pending_submissions: submissions.filter((s) =>
      ['draft', 'submitted', 'under_review', 'changes_requested'].includes(s.status || '')
    ).length,
    active_auctions: auctions.filter((a) => (a.status || '') === 'active').length,
    total_bids: bids.length,
    gross_merchandise_value: orders.reduce((acc, o) => acc + Number(o.hammer_price || 0), 0),
    platform_revenue: orders.reduce(
      (acc, o) => acc + Number(o.seller_commission_amount || 0) + Number(o.buyer_premium || 0),
      0
    ),
    pending_payout_total: payouts.reduce(
      (acc, p) => acc + ((p.status || '') === 'pending' ? Number(p.net_payout_amount || 0) : 0),
      0
    ),
  };

  res.renderPage('admin/dashboard', {
    pageTitle: 'Curatorial & Executive Administration — ArtAlly',
    admin,
    stats,
    users,
    artistProfiles,
    submissions,
    auctions,
    orders,
    payouts,
    walletWithdrawals,
    authInvoices,
    allArtistsList,
    allArtworksList,
    auditLogs: auditLogs.slice(0, 25),
    reports,
    subscribers,
    settings,
    categories: db.all('categories'),
    supabaseConnected: Boolean(db.supabaseEnabled),
  });
}

app.get('/admin/dashboard', handleAdminDashboard);
app.get('/admin/submissions', handleAdminDashboard);

app.post('/admin/submissions/review', (req, res) => {
  const admin = requireRole(req, res, 'admin');
  if (!admin) return;
  const db = DatabaseEngine.getInstance();

  const submissionId = Number(req.body.submission_id || 0);
  const decision = String(req.body.decision || '').trim();
  const feedback = String(req.body.admin_feedback || '').trim();
  const auctionTitle = String(req.body.auction_title || 'Curated Evening Sale').trim();
  const lotNumber = String(
    req.body.lot_number || `LOT ${helpers.sprintf('%02d', submissionId)}`
  ).trim();
  const durationDays = Math.max(1, Number(req.body.duration_days || 7));

  const sub = db.find('artwork_submissions', submissionId);
  if (!sub) {
    req.setFlash('error', 'Submission record not found.');
    return res.redirect('/admin/dashboard');
  }

  const artwork = db.find('artworks', Number(sub.artwork_id));
  if (!artwork) {
    req.setFlash('error', 'Associated artwork not found.');
    return res.redirect('/admin/dashboard');
  }

  const agreedPrice = Number(
    req.body.agreed_price ?? sub.artist_expected_price ?? artwork.reserve_price ?? artwork.starting_bid
  );
  const controlledStartingBid = Math.max(100, Number(req.body.starting_bid ?? artwork.starting_bid));
  const controlledReservePrice = Math.max(0, Number(req.body.reserve_price ?? agreedPrice));

  if (decision === 'approve_publish') {
    db.update('artworks', artwork.id, {
      status: 'published',
      agreed_price: agreedPrice,
      starting_bid: controlledStartingBid,
      reserve_price: controlledReservePrice,
      featured: Boolean(req.body.feature_on_home),
    });

    db.update('artwork_submissions', submissionId, {
      status: 'published',
      agreed_price: agreedPrice,
      suggested_starting_bid: controlledStartingBid,
      reserve_price: controlledReservePrice,
      admin_feedback:
        feedback ||
        'Approved by Curatorial Committee with agreed valuation and published to live timed auction.',
      reviewed_by: Number(admin.id),
    });

    const existingAuction = db.findBy('auctions', 'artwork_id', Number(artwork.id));
    const startsAt = helpers.gmdate('Y-m-d H:i:s', helpers.strtotime('-5 minutes'));
    const endsAt = helpers.gmdate('Y-m-d H:i:s', helpers.strtotime(`+${durationDays} days`));

    if (existingAuction) {
      db.update('auctions', existingAuction.id, {
        title: auctionTitle,
        lot_number: lotNumber,
        starting_bid: controlledStartingBid,
        current_bid: controlledStartingBid,
        agreed_price: agreedPrice,
        reserve_price: controlledReservePrice,
        status: 'active',
        starts_at: startsAt,
        ends_at: endsAt,
      });
    } else {
      db.insert('auctions', {
        artwork_id: Number(artwork.id),
        artist_id: Number(artwork.artist_id),
        title: auctionTitle,
        lot_number: lotNumber,
        currency: artwork.currency || 'USD',
        starting_bid: controlledStartingBid,
        current_bid: controlledStartingBid,
        agreed_price: agreedPrice,
        reserve_price: controlledReservePrice,
        reserve_met: false,
        estimate_low: Number(artwork.estimate_low),
        estimate_high: Number(artwork.estimate_high),
        bid_count: 0,
        highest_bidder_id: null,
        starts_at: startsAt,
        ends_at: endsAt,
        status: 'active',
        anti_sniping_enabled: true,
        featured: Boolean(req.body.feature_on_home),
        is_seeded: false,
      });
    }

    db.insert('notifications', {
      user_id: Number(artwork.artist_id),
      type: 'artwork_approved',
      title: `Artwork Approved & Auction Started: ${artwork.title}`,
      message: `Your submission (${sub.reference_code}) has been approved at an Agreed Price of ${helpers.format_money(
        agreedPrice
      )} and Starting Bid of ${helpers.format_money(controlledStartingBid)} in "${auctionTitle}".`,
      action_url: `/artworks/${artwork.slug}`,
      read_at: null,
    });

    logAudit(
      db,
      admin,
      'SUBMISSION_APPROVED_PUBLISHED',
      'artwork_submission',
      submissionId,
      `Approved & published '${artwork.title}' (${sub.reference_code}) with Agreed Price $${agreedPrice} into ${auctionTitle}.`,
      req.ip
    );
    req.setFlash(
      'success',
      `Submission ${sub.reference_code} approved at Agreed Price ${helpers.format_money(
        agreedPrice
      )} and started in live auction.`
    );
  } else if (decision === 'request_changes') {
    db.update('artworks', artwork.id, {
      status: 'changes_requested',
      agreed_price: agreedPrice,
    });
    db.update('artwork_submissions', submissionId, {
      status: 'changes_requested',
      agreed_price: agreedPrice,
      admin_feedback:
        feedback || 'Please review our proposed agreed price and provide additional documentation.',
      reviewed_by: Number(admin.id),
    });

    db.insert('notifications', {
      user_id: Number(artwork.artist_id),
      type: 'changes_requested',
      title: `Curatorial Proposal & Changes Requested: ${artwork.title}`,
      message: `Specialist feedback on ${sub.reference_code} (Proposed Agreed Price: ${helpers.format_money(
        agreedPrice
      )}): ${feedback || 'Additional documentation requested.'}`,
      action_url: '/artist/submissions',
      read_at: null,
    });

    logAudit(
      db,
      admin,
      'SUBMISSION_CHANGES_REQUESTED',
      'artwork_submission',
      submissionId,
      `Requested revisions for ${sub.reference_code}.`,
      req.ip
    );
    req.setFlash('success', `Requested revisions from artist for ${sub.reference_code}.`);
  } else if (decision === 'reject') {
    db.update('artworks', artwork.id, { status: 'rejected' });
    db.update('artwork_submissions', submissionId, {
      status: 'rejected',
      admin_feedback: feedback || 'Declined for the current seasonal auction calendar.',
      reviewed_by: Number(admin.id),
    });

    db.insert('notifications', {
      user_id: Number(artwork.artist_id),
      type: 'artwork_rejected',
      title: `Curatorial Decision: ${artwork.title}`,
      message: `Regarding submission ${sub.reference_code}: ${
        feedback || 'We are unable to include this work in the current sale.'
      }`,
      action_url: '/artist/submissions',
      read_at: null,
    });

    logAudit(
      db,
      admin,
      'SUBMISSION_REJECTED',
      'artwork_submission',
      submissionId,
      `Rejected submission ${sub.reference_code}.`,
      req.ip
    );
    req.setFlash('success', `Submission ${sub.reference_code} marked as rejected.`);
  }

  res.redirect('/admin/dashboard#submissions');
});

app.post('/admin/artworks/control', (req, res) => {
  const admin = requireRole(req, res, 'admin');
  if (!admin) return;
  const db = DatabaseEngine.getInstance();

  const artworkId = Number(req.body.artwork_id || 0);
  const artwork = db.find('artworks', artworkId);
  if (!artwork) {
    req.setFlash('error', 'Artwork record not found.');
    return res.redirect('/admin/dashboard#artists');
  }

  const agreedPrice = Number(
    req.body.agreed_price ?? artwork.agreed_price ?? artwork.reserve_price ?? 5000
  );
  const startingBid = Math.max(100, Number(req.body.starting_bid ?? artwork.starting_bid ?? 1000));
  const currentBid = Math.max(100, Number(req.body.current_bid ?? startingBid));
  const reservePrice = Math.max(0, Number(req.body.reserve_price ?? agreedPrice));
  let auctionState = String(req.body.auction_state || 'active').trim();
  const bidAction = String(req.body.bid_action || '').trim();
  const auctionTitle = String(req.body.auction_title || 'Curated Timed Sale').trim();
  const lotNumber = String(
    req.body.lot_number || `LOT ${helpers.sprintf('%02d', artworkId)}`
  ).trim();
  const durationDays = Math.max(1, Number(req.body.duration_days || 7));
  const adminFeedback = String(req.body.admin_feedback || '').trim();
  const deskReply = String(req.body.desk_reply || '').trim();

  const existingAuction = db.findBy('auctions', 'artwork_id', artworkId);
  const previousBid = Number(
    existingAuction?.current_bid ?? artwork.current_bid ?? artwork.starting_bid ?? 0
  );
  const isBidPriceUpdated =
    bidAction === 'update_bid_alert' ||
    (bidAction !== 'send_desk_reply' && Math.abs(currentBid - previousBid) >= 0.01);
  const isDeskReply = bidAction === 'send_desk_reply' || deskReply !== '';

  if (isBidPriceUpdated && auctionState === 'unscheduled') {
    auctionState = 'active';
  }

  const artworkStatus =
    auctionState === 'unscheduled'
      ? 'under_review'
      : auctionState === 'closed_sold'
      ? 'sold'
      : 'published';
  const subStatus = auctionState === 'unscheduled' ? 'under_review' : 'published';

  db.update('artworks', artworkId, {
    agreed_price: agreedPrice,
    starting_bid: startingBid,
    current_bid: currentBid,
    reserve_price: reservePrice,
    status: artworkStatus,
  });

  const sub = db.findBy('artwork_submissions', 'artwork_id', artworkId);
  if (sub) {
    const messages = Array.isArray(sub.negotiation_messages) ? [...sub.negotiation_messages] : [];
    if (isDeskReply && deskReply) {
      messages.push({
        sender: 'admin',
        sender_name: `Auction & Audit Desk (${admin.name})`,
        text: deskReply,
        created_at: formatUtcNow(),
      });
    }
    if (isBidPriceUpdated) {
      messages.push({
        sender: 'admin',
        sender_name: 'Auction Desk Alert',
        text: `🔔 NEW BID ALERT: Live bid updated to ${helpers.format_money(currentBid)} on "${
          artwork.title
        }".${adminFeedback ? ` Note: ${adminFeedback}` : ''}`,
        created_at: formatUtcNow(),
      });
    } else if (adminFeedback && !isDeskReply) {
      messages.push({
        sender: 'admin',
        sender_name: admin.name,
        text: `House Agreed Price set to ${helpers.format_money(
          agreedPrice
        )} (Starting Bid: ${helpers.format_money(startingBid)}): ${adminFeedback}`,
        created_at: formatUtcNow(),
      });
    }

    db.update('artwork_submissions', sub.id, {
      agreed_price: agreedPrice,
      current_bid: currentBid,
      reserve_price: reservePrice,
      status: subStatus,
      admin_feedback:
        deskReply ||
        adminFeedback ||
        sub.admin_feedback ||
        'Valuation & live bid price updated by Administration.',
      negotiation_messages: messages,
      reviewed_by: Number(admin.id),
    });
  }

  if (auctionState !== 'unscheduled') {
    const startsAt =
      auctionState === 'upcoming'
        ? helpers.gmdate('Y-m-d H:i:s', helpers.strtotime('+2 days'))
        : helpers.gmdate('Y-m-d H:i:s', helpers.strtotime('-1 hour'));
    const endsAt = ['closed_sold', 'closed_unsold'].includes(auctionState)
      ? helpers.gmdate('Y-m-d H:i:s', helpers.strtotime('-1 minute'))
      : helpers.gmdate('Y-m-d H:i:s', helpers.strtotime(`+${durationDays} days`));

    let auctionRecord;
    if (existingAuction) {
      const newBidCount = Number(existingAuction.bid_count || 0) + (isBidPriceUpdated ? 1 : 0);
      auctionRecord = db.update('auctions', existingAuction.id, {
        title: auctionTitle,
        lot_number: lotNumber,
        starting_bid: startingBid,
        current_bid: currentBid,
        agreed_price: agreedPrice,
        reserve_price: reservePrice,
        reserve_met: currentBid >= reservePrice,
        bid_count: newBidCount,
        status: auctionState,
        starts_at: startsAt,
        ends_at: endsAt,
      });
    } else {
      auctionRecord = db.insert('auctions', {
        artwork_id: artworkId,
        artist_id: Number(artwork.artist_id),
        title: auctionTitle,
        lot_number: lotNumber,
        currency: artwork.currency || 'USD',
        starting_bid: startingBid,
        current_bid: currentBid,
        agreed_price: agreedPrice,
        reserve_price: reservePrice,
        reserve_met: currentBid >= reservePrice,
        estimate_low: Number(artwork.estimate_low || startingBid),
        estimate_high: Number(artwork.estimate_high || startingBid * 1.5),
        bid_count: isBidPriceUpdated || auctionState === 'closed_sold' ? 1 : 0,
        highest_bidder_id: auctionState === 'closed_sold' ? 6 : null,
        starts_at: startsAt,
        ends_at: endsAt,
        status: auctionState,
        anti_sniping_enabled: true,
        featured: true,
        is_seeded: false,
      });
    }

    if (isBidPriceUpdated && auctionRecord) {
      db.insert('bids', {
        auction_id: Number(auctionRecord.id),
        artwork_id: artworkId,
        user_id: Number(admin.id),
        paddle_number: 'PB-HOUSE',
        amount: currentBid,
        currency: artwork.currency || 'USD',
        ip_hash: crypto.createHash('sha256').update('127.0.0.1').digest('hex'),
        is_seeded: false,
      });
    }

    if (auctionState === 'closed_sold' && auctionRecord) {
      const hammerPrice = agreedPrice > 0 ? agreedPrice : currentBid;
      db.update('auctions', auctionRecord.id, {
        current_bid: hammerPrice,
        agreed_price: hammerPrice,
        reserve_met: true,
        closed_at: formatUtcNow(),
      });

      const existingOrder = db.findBy('orders', 'auction_id', Number(auctionRecord.id));
      if (!existingOrder) {
        const settings = db.find('platform_settings', 1) || {};
        const commissionRate = Number(settings.commission_rate_percent ?? 15.0);
        const buyerPremiumRate = Number(settings.buyer_premium_percent ?? 20.0);
        const buyerPremium = Math.round(hammerPrice * (buyerPremiumRate / 100) * 100) / 100;
        const shippingFee = 450.0;
        const totalAmount = Math.round((hammerPrice + buyerPremium + shippingFee) * 100) / 100;
        const sellerCommission = Math.round(hammerPrice * (commissionRate / 100) * 100) / 100;
        const netPayout = Math.round((hammerPrice - sellerCommission) * 100) / 100;

        const order = db.insert('orders', {
          order_number: helpers.sprintf(
            'ORD-%s-%04d',
            helpers.gmdate('Y'),
            Math.floor(1000 + Math.random() * 9000)
          ),
          auction_id: Number(auctionRecord.id),
          artwork_id: artworkId,
          buyer_id: Number(auctionRecord.highest_bidder_id || 6),
          artist_id: Number(artwork.artist_id),
          hammer_price: hammerPrice,
          buyer_premium: buyerPremium,
          shipping_fee: shippingFee,
          total_amount: totalAmount,
          seller_commission_rate: commissionRate,
          seller_commission_amount: sellerCommission,
          net_artist_payout: netPayout,
          currency: artwork.currency || 'USD',
          payment_status: 'pending',
          fulfillment_status: 'awaiting_payment',
          payment_deadline: helpers.gmdate('Y-m-d H:i:s', helpers.strtotime('+7 days')),
        });

        db.insert('seller_payouts', {
          order_id: Number(order.id),
          artist_id: Number(artwork.artist_id),
          gross_sale_amount: hammerPrice,
          commission_deducted: sellerCommission,
          net_payout_amount: netPayout,
          currency: artwork.currency || 'USD',
          status: 'pending',
          payout_reference: helpers.sprintf('PO-PENDING-%04d', order.id),
          approved_by: null,
        });
      }
    }
  }

  const stateLabel =
    auctionState === 'active'
      ? 'LIVE & STARTED'
      : auctionState === 'upcoming'
      ? 'SCHEDULED UPCOMING'
      : auctionState === 'closed_sold'
      ? 'ENDED — SOLD'
      : auctionState === 'closed_unsold'
      ? 'ENDED — UNSOLD'
      : 'UNDER CURATORIAL REVIEW';

  if (isBidPriceUpdated) {
    const activeEndsAt =
      existingAuction?.ends_at || helpers.gmdate('Y-m-d H:i:s', helpers.strtotime(`+${durationDays} days`));
    db.insert('notifications', {
      user_id: Number(artwork.artist_id),
      type: 'new_bid_alert',
      bid_amount: currentBid,
      artwork_id: artworkId,
      artwork_title: artwork.title,
      ends_at: activeEndsAt,
      title: `🔔 NEW BID ALERT: ${helpers.format_money(currentBid)} on "${artwork.title}"`,
      message: `ALERT: There is a new bid for ${helpers.format_money(currentBid)} on your artwork "${
        artwork.title
      }"! This updated bid price and live countdown timer are now displayed on your Artist Page and Studio Dashboard.${
        adminFeedback ? ` Note: ${adminFeedback}` : ''
      }`,
      action_url: '/artist/dashboard',
      read_at: null,
    });
  } else if (isDeskReply && deskReply) {
    db.insert('notifications', {
      user_id: Number(artwork.artist_id),
      type: 'desk_reply',
      artwork_id: artworkId,
      artwork_title: artwork.title,
      title: `💬 Auction & Audit Desk Response: "${artwork.title}"`,
      message: deskReply,
      action_url: '/artist/dashboard',
      read_at: null,
    });
  } else {
    db.insert('notifications', {
      user_id: Number(artwork.artist_id),
      type: 'admin_auction_control',
      title: `Auction & Valuation Updated: ${artwork.title}`,
      message: `Status: ${stateLabel} · Agreed Price: ${helpers.format_money(
        agreedPrice
      )} · Current Bid: ${helpers.format_money(currentBid)}. ${adminFeedback}`,
      action_url: '/artist/dashboard',
      read_at: null,
    });
  }

  logAudit(
    db,
    admin,
    isBidPriceUpdated
      ? 'BID_PRICE_UPDATED_AND_ALERTED'
      : isDeskReply
      ? 'AUCTION_DESK_REPLY_SENT'
      : 'ARTWORK_AUCTION_CONTROL_UPDATED',
    'artwork',
    artworkId,
    `Updated '${artwork.title}': State=${stateLabel}, AgreedPrice=$${agreedPrice}, StartingBid=$${startingBid}, CurrentBid=$${currentBid}`,
    req.ip
  );

  if (isBidPriceUpdated) {
    req.setFlash(
      'success',
      `🔔 BID ALERT SENT: There is now a bid for ${helpers.format_money(currentBid)} on "${
        artwork.title
      }"! The updated bid price is live on the Artist Page and delivered as an alert to the Artist's Studio Notifications.`
    );
  } else if (isDeskReply && deskReply) {
    req.setFlash('success', `💬 Auction & Audit Desk response sent to the artist on "${artwork.title}"!`);
  } else {
    req.setFlash(
      'success',
      `Updated "${artwork.title}" — Auction State: ${stateLabel}, Agreed Price: ${helpers.format_money(
        agreedPrice
      )}, Current Bid: ${helpers.format_money(currentBid)}.`
    );
  }

  res.redirect('/admin/dashboard#artists');
});

app.post('/admin/artworks/delete', (req, res) => {
  const admin = requireRole(req, res, 'admin');
  if (!admin) return;
  const db = DatabaseEngine.getInstance();
  const artworkId = Number(req.body.artwork_id || 0);
  const artwork = db.find('artworks', artworkId);
  if (!artwork) {
    req.setFlash('error', 'Artwork not found.');
    return res.redirect('/admin/dashboard#artists');
  }

  const title = artwork.title || `Artwork #${artworkId}`;
  for (const sub of db.where('artwork_submissions', (s) => Number(s.artwork_id) === artworkId)) {
    db.delete('artwork_submissions', sub.id);
  }
  for (const auc of db.where('auctions', (a) => Number(a.artwork_id) === artworkId)) {
    db.delete('auctions', auc.id);
  }
  for (const img of db.where('artwork_images', (i) => Number(i.artwork_id) === artworkId)) {
    db.delete('artwork_images', img.id);
  }
  for (const bid of db.where('bids', (b) => Number(b.artwork_id) === artworkId)) {
    db.delete('bids', bid.id);
  }

  db.delete('artworks', artworkId);
  logAudit(db, admin, 'ARTWORK_DELETED', 'artwork', artworkId, `Permanently deleted artwork '${title}'.`, req.ip);
  req.setFlash('success', `Permanently deleted artwork "${title}".`);
  res.redirect('/admin/dashboard#artists');
});

app.post('/admin/artists/approve', (req, res) => {
  const admin = requireRole(req, res, 'admin');
  if (!admin) return;
  const db = DatabaseEngine.getInstance();
  const profileId = Number(req.body.artist_profile_id || 0);
  const decision = String(req.body.decision || 'approved').trim();
  const profile = db.find('artist_profiles', profileId);

  if (!profile) {
    req.setFlash('error', 'Artist profile not found.');
    return res.redirect('/admin/dashboard#artists');
  }

  const isApproved = decision === 'approved';
  db.update('artist_profiles', profileId, {
    approval_status: isApproved ? 'approved' : 'pending',
    verified_artist: isApproved,
  });

  db.insert('notifications', {
    user_id: Number(profile.user_id),
    type: 'artist_profile_approved',
    title: isApproved
      ? 'Your Public Artist Profile is Now Approved & Live'
      : 'Artist Profile Set to Pending Review',
    message: isApproved
      ? `Congratulations ${profile.display_name}. Your artist registration and portfolio have been approved by the Administrator and are now publicly displayed in the Artists Directory.`
      : 'Your artist profile is currently marked as awaiting curatorial approval.',
    action_url: `/artists/${profile.slug}`,
    read_at: null,
  });

  logAudit(
    db,
    admin,
    isApproved ? 'ARTIST_PROFILE_APPROVED' : 'ARTIST_PROFILE_SET_PENDING',
    'artist_profile',
    profileId,
    `${isApproved ? 'Approved' : 'Set to Pending'} public artist profile for ${profile.display_name}.`,
    req.ip
  );

  req.setFlash(
    'success',
    isApproved
      ? `Artist profile for "${profile.display_name}" is now APPROVED and publicly visible on the Artists page.`
      : `Artist profile for "${profile.display_name}" set to Awaiting Approval.`
  );
  res.redirect('/admin/dashboard#artists');
});

app.post('/admin/users/delete', (req, res) => {
  const admin = requireRole(req, res, 'admin');
  if (!admin) return;
  const db = DatabaseEngine.getInstance();
  const userId = Number(req.body.user_id || 0);
  const target = db.find('users', userId);

  if (!target) {
    req.setFlash('error', 'User account not found.');
    return res.redirect('/admin/dashboard#artists');
  }

  const name = target.name || `User #${userId}`;
  for (const ap of db.where('artist_profiles', (p) => Number(p.user_id) === userId)) {
    db.delete('artist_profiles', ap.id);
  }
  for (const art of db.where('artworks', (a) => Number(a.artist_id) === userId)) {
    for (const sub of db.where('artwork_submissions', (s) => Number(s.artwork_id) === Number(art.id))) {
      db.delete('artwork_submissions', sub.id);
    }
    for (const auc of db.where('auctions', (a) => Number(a.artwork_id) === Number(art.id))) {
      db.delete('auctions', auc.id);
    }
    db.delete('artworks', art.id);
  }

  db.delete('users', userId);
  logAudit(
    db,
    admin,
    'USER_DELETED',
    'user',
    userId,
    `Permanently deleted account '${name}' (${target.email}).`,
    req.ip
  );
  req.setFlash('success', `Permanently deleted registration and uploads for "${name}".`);
  res.redirect('/admin/dashboard#artists');
});

app.post('/admin/profile/manage', (req, res) => {
  const admin = requireRole(req, res, 'admin');
  if (!admin) return;
  const db = DatabaseEngine.getInstance();
  const mode = String(req.body.admin_action_mode || req.body.action_type || 'update').trim();

  if (mode === 'delete_self' || mode === 'delete_admin_profile') {
    const confirmText = String(req.body.confirm_delete || '').trim();
    if (confirmText !== 'DELETE') {
      req.setFlash(
        'error',
        'Please type DELETE in the confirmation box to delete this administrator profile.'
      );
      return res.redirect('/admin/dashboard#admin-profile');
    }
    db.delete('users', Number(admin.id));
    req.logoutUser();
    req.setFlash(
      'success',
      'Your Administrator profile has been permanently deleted. You may create a new Administrator account below.'
    );
    return res.redirect('/admin/login');
  }

  if (mode === 'create_new_admin') {
    const newName = String(req.body.new_admin_name || '').trim();
    const newEmail = String(req.body.new_admin_email || '').trim();
    const newPass = String(req.body.new_admin_password || '');

    if (!newName || !newEmail.includes('@') || newPass.length < 8) {
      req.setFlash(
        'error',
        'Provide a valid name, email, and password (min 8 characters) for the new Administrator.'
      );
      return res.redirect('/admin/dashboard#admin-profile');
    }
    if (db.findBy('users', 'email', newEmail)) {
      req.setFlash('error', 'An account with that email already exists.');
      return res.redirect('/admin/dashboard#admin-profile');
    }

    const created = db.insert('users', {
      name: newName,
      email: newEmail,
      password: hashPassword(newPass),
      country: 'Switzerland',
      role: 'admin',
      roles: ['admin', 'buyer', 'artist'],
      email_verified_at: formatUtcNow(),
      bidding_approved: true,
      status: 'active',
      is_seeded: false,
    });

    logAudit(
      db,
      admin,
      'NEW_ADMIN_CREATED',
      'user',
      created.id,
      `Created new administrator profile for ${newEmail}.`,
      req.ip
    );
    req.setFlash('success', `Created new Administrator account for ${newName} (${newEmail}).`);
    return res.redirect('/admin/dashboard#admin-profile');
  }

  const name = String(req.body.name || admin.name).trim();
  const email = String(req.body.email || admin.email).trim();
  const newPassword = String(req.body.new_password || '');

  const updates = {
    name: name || admin.name,
    email: email.includes('@') ? email : admin.email,
  };
  if (newPassword) {
    if (newPassword.length < 8) {
      req.setFlash('error', 'New password must be at least 8 characters.');
      return res.redirect('/admin/dashboard#admin-profile');
    }
    updates.password = hashPassword(newPassword);
  }

  db.update('users', admin.id, updates);
  logAudit(
    db,
    admin,
    'ADMIN_PROFILE_UPDATED',
    'user',
    admin.id,
    'Updated administrator profile credentials.',
    req.ip
  );
  req.setFlash('success', 'Your Administrator profile credentials have been updated.');
  res.redirect('/admin/dashboard#admin-profile');
});

app.post('/admin/auctions/live-control', (req, res) => {
  const admin = requireRole(req, res, 'admin');
  if (!admin) return;
  const db = DatabaseEngine.getInstance();
  const auctionId = Number(req.body.auction_id || 0);
  const actionType = String(req.body.action_type || 'increase_bid').trim();
  const auction = db.find('auctions', auctionId);

  if (!auction) {
    req.setFlash('error', 'Auction record not found.');
    return res.redirect('/admin/dashboard#auctions');
  }

  const artwork = db.find('artworks', Number(auction.artwork_id)) || {};
  const currentBid = Number(auction.current_bid ?? auction.starting_bid ?? 0);
  const increment = helpers.calculate_bid_increment(currentBid);

  if (actionType === 'increase_bid' || actionType === 'set_custom_bid') {
    const targetBid =
      actionType === 'increase_bid'
        ? currentBid + increment
        : Math.max(100, Number(req.body.custom_bid_amount || currentBid));

    const nowSec = Math.floor(Date.now() / 1000);
    const existingEndSec = helpers.strtotime(auction.ends_at || '');
    const liveEndsAt =
      existingEndSec && existingEndSec > nowSec
        ? auction.ends_at
        : helpers.gmdate('Y-m-d H:i:s', helpers.strtotime('+3 days'));

    db.update('auctions', auctionId, {
      current_bid: targetBid,
      bid_count: Number(auction.bid_count || 0) + 1,
      reserve_met: targetBid >= Number(auction.reserve_price || 0),
      status: 'active',
      ends_at: liveEndsAt,
    });
    if (artwork.id) {
      db.update('artworks', Number(artwork.id), {
        current_bid: targetBid,
        status: 'published',
      });
      const sub = db.findBy('artwork_submissions', 'artwork_id', Number(artwork.id));
      if (sub) db.update('artwork_submissions', sub.id, { current_bid: targetBid });
    }
    db.insert('bids', {
      auction_id: auctionId,
      artwork_id: Number(auction.artwork_id),
      user_id: Number(admin.id),
      paddle_number: 'PB-HOUSE',
      amount: targetBid,
      currency: auction.currency || 'USD',
      ip_hash: crypto.createHash('sha256').update('127.0.0.1').digest('hex'),
      is_seeded: false,
    });
    db.insert('notifications', {
      user_id: Number(auction.artist_id),
      type: 'new_bid_alert',
      bid_amount: targetBid,
      artwork_id: Number(auction.artwork_id),
      artwork_title: artwork.title || auction.lot_number,
      ends_at: liveEndsAt,
      title: `🔔 NEW BID ALERT: ${helpers.format_money(targetBid)} on "${
        artwork.title || auction.lot_number
      }"`,
      message: `ALERT: There is a new bid for ${helpers.format_money(targetBid)} on your artwork "${
        artwork.title || auction.lot_number
      }"! This updated bid price and live countdown timer are now displayed on your Artist Page and Studio Dashboard.`,
      action_url: '/artist/dashboard',
      read_at: null,
    });
    logAudit(
      db,
      admin,
      actionType === 'increase_bid' ? 'LIVE_BID_INCREASED' : 'LIVE_BID_SET',
      'auction',
      auctionId,
      `Updated live bid on ${auction.lot_number} to ${helpers.format_money(targetBid)}.`,
      req.ip
    );
    req.setFlash(
      'success',
      `🔔 BID ALERT SENT: Updated live bid on ${auction.lot_number} to ${helpers.format_money(
        targetBid
      )}. Alert delivered to the artist's Studio Notifications and displayed on the Artist Page.`
    );
  } else if (actionType === 'mark_sold') {
    const finalPrice = Math.max(currentBid, Number(req.body.custom_bid_amount || currentBid));
    db.transaction((txDb) => {
      txDb.update('auctions', auctionId, {
        current_bid: finalPrice,
        agreed_price: finalPrice,
        reserve_met: true,
        status: 'closed_sold',
        closed_at: formatUtcNow(),
      });
      txDb.update('artworks', Number(auction.artwork_id), {
        status: 'sold',
        agreed_price: finalPrice,
      });
      const sub = txDb.findBy('artwork_submissions', 'artwork_id', Number(auction.artwork_id));
      if (sub) {
        txDb.update('artwork_submissions', sub.id, {
          status: 'closed_sold',
          agreed_price: finalPrice,
        });
      }

      const existingOrder = txDb.findBy('orders', 'auction_id', auctionId);
      if (!existingOrder) {
        const settings = txDb.find('platform_settings', 1) || {};
        const commissionRate = Number(settings.commission_rate_percent || 15);
        const buyerPremiumRate = Number(settings.buyer_premium_percent || 20);
        const buyerPremium = Math.round(finalPrice * (buyerPremiumRate / 100));
        const sellerCommission = Math.round(finalPrice * (commissionRate / 100));
        const netArtistPayout = finalPrice - sellerCommission;

        const order = txDb.insert('orders', {
          order_number: `ORD-${helpers.gmdate('Y')}-${1000 + txDb.all('orders').length + 1}`,
          auction_id: auctionId,
          artwork_id: Number(auction.artwork_id),
          buyer_id: Number(auction.highest_bidder_id || admin.id),
          artist_id: Number(auction.artist_id),
          hammer_price: finalPrice,
          buyer_premium: buyerPremium,
          shipping_fee: 450,
          total_amount: finalPrice + buyerPremium + 450,
          seller_commission_rate: commissionRate,
          seller_commission_amount: sellerCommission,
          net_artist_payout: netArtistPayout,
          currency: auction.currency || 'USD',
          payment_status: 'paid',
          fulfillment_status: 'preparing_shipment',
          paid_at: formatUtcNow(),
          is_seeded: false,
        });

        txDb.insert('commissions', {
          order_id: Number(order.id),
          artwork_id: Number(auction.artwork_id),
          artist_id: Number(auction.artist_id),
          commission_rate: commissionRate,
          seller_commission_amount: sellerCommission,
          buyer_premium_amount: buyerPremium,
          total_platform_revenue: sellerCommission + buyerPremium,
          is_seeded: false,
        });

        txDb.insert('seller_payouts', {
          order_id: Number(order.id),
          artist_id: Number(auction.artist_id),
          gross_amount: finalPrice,
          commission_deducted: sellerCommission,
          net_payout_amount: netArtistPayout,
          currency: auction.currency || 'USD',
          status: 'pending',
          payout_reference: `PO-${helpers.gmdate('Y')}-${200 + txDb.all('seller_payouts').length + 1}`,
          scheduled_date: helpers.gmdate('Y-m-d', helpers.strtotime('+5 days')),
          is_seeded: false,
        });
      }

      txDb.insert('notifications', {
        user_id: Number(auction.artist_id),
        type: 'artwork_sold',
        title: `Artwork Marked SOLD: ${artwork.title || auction.lot_number}`,
        message: `Your artwork "${
          artwork.title || auction.lot_number
        }" has been marked SOLD by the Auction House at a final price of ${helpers.format_money(
          finalPrice
        )}.`,
        action_url: '/artist/dashboard',
        read_at: null,
      });
      logAudit(
        txDb,
        admin,
        'AUCTION_MARKED_SOLD',
        'auction',
        auctionId,
        `Changed status of ${auction.lot_number} to SOLD at ${helpers.format_money(finalPrice)}.`,
        req.ip
      );
    });
    req.setFlash(
      'success',
      `Lot ${auction.lot_number} status changed to SOLD at ${helpers.format_money(finalPrice)}.`
    );
  } else if (actionType === 'mark_active') {
    db.update('auctions', auctionId, {
      status: 'active',
      ends_at: helpers.gmdate('Y-m-d H:i:s', helpers.strtotime('+7 days')),
    });
    db.update('artworks', Number(auction.artwork_id), {
      status: 'published',
    });
    logAudit(
      db,
      admin,
      'AUCTION_REOPENED_LIVE',
      'auction',
      auctionId,
      `Reopened ${auction.lot_number} as Active Live Auction.`,
      req.ip
    );
    req.setFlash('success', `Lot ${auction.lot_number} is now Active (Live Bidding).`);
  }

  res.redirect('/admin/dashboard#auctions');
});

app.post('/admin/auctions/close', (req, res) => {
  const admin = requireRole(req, res, 'admin');
  if (!admin) return;
  const db = DatabaseEngine.getInstance();
  const auctionId = Number(req.body.auction_id || 0);
  const auction = db.find('auctions', auctionId);

  if (!auction) {
    req.setFlash('error', 'Auction not found.');
    return res.redirect('/admin/dashboard#auctions');
  }

  const settings = db.find('platform_settings', 1) || {};
  const commissionRate = Number(settings.commission_rate_percent ?? 15.0);
  const buyerPremiumRate = Number(settings.buyer_premium_percent ?? 20.0);

  db.transaction((engine) => {
    AuctionEngineService.closeSingleAuction(engine, auction, commissionRate, buyerPremiumRate);
  });

  logAudit(
    db,
    admin,
    'AUCTION_MANUALLY_SETTLED',
    'auction',
    auctionId,
    `Settled auction #${auctionId} (${auction.lot_number}) and evaluated reserve price.`,
    req.ip
  );
  req.setFlash('success', `Auction ${auction.lot_number} closed and settled according to reserve rules.`);
  res.redirect('/admin/dashboard#auctions');
});

app.post('/admin/payouts/approve', (req, res) => {
  const admin = requireRole(req, res, 'admin');
  if (!admin) return;
  const db = DatabaseEngine.getInstance();
  const payoutId = Number(req.body.payout_id || 0);
  const payout = db.find('seller_payouts', payoutId);

  if (!payout) {
    req.setFlash('error', 'Payout record not found.');
    return res.redirect('/admin/dashboard#payouts');
  }

  const ref = `PO-CH-${Math.floor(100000 + Math.random() * 900000)}`;
  db.update('seller_payouts', payoutId, {
    status: 'completed',
    payout_reference: ref,
    approved_by: Number(admin.id),
    paid_at: formatUtcNow(),
  });

  const artistProfile = db.findBy('artist_profiles', 'user_id', Number(payout.artist_id));
  const updatedWallet = computeArtistWalletState(db, Number(payout.artist_id), artistProfile);

  db.insert('notifications', {
    user_id: Number(payout.artist_id),
    type: 'payout_completed',
    title: `💰 Payout Disbursed to Your Studio Wallet (${helpers.format_money_exact(
      payout.net_payout_amount
    )})`,
    message: `Your net proceeds of ${helpers.format_money_exact(
      payout.net_payout_amount
    )} have been authorized and credited directly to your ArtAlly Studio Wallet (Ref: ${ref}). Available Wallet Balance: ${helpers.format_money_exact(
      updatedWallet.available_balance
    )}.`,
    action_url: '/artist/dashboard#artist-wallet',
    read_at: null,
  });

  logAudit(
    db,
    admin,
    'SELLER_PAYOUT_RELEASED',
    'seller_payout',
    payoutId,
    `Authorized wire payout & credited $${Number(payout.net_payout_amount).toFixed(
      2
    )} to Artist #${payout.artist_id} Studio Wallet (Ref: ${ref}).`,
    req.ip
  );
  req.setFlash(
    'success',
    `Authorized Wire Payout #${payoutId} (${helpers.format_money_exact(
      payout.net_payout_amount
    )}) — Disbursed amount is now live in the Artist's Studio Wallet (Ref: ${ref}).`
  );
  res.redirect('/admin/dashboard#payouts');
});

app.post('/admin/artists/wallet-access', (req, res) => {
  const admin = requireRole(req, res, 'admin');
  if (!admin) return;
  const db = DatabaseEngine.getInstance();

  const actionType = String(req.body.action_type || 'toggle_access').trim();
  const returnAnchor = String(req.body.return_anchor || '#payouts').trim();
  const applyToAll = String(req.body.apply_to_all || '') === '1';

  const artistUserId = Number(req.body.artist_id || 0);
  const profileId = Number(req.body.artist_profile_id || 0);
  let profile =
    (profileId > 0 ? db.find('artist_profiles', profileId) : null) ||
    (artistUserId > 0 ? db.findBy('artist_profiles', 'user_id', artistUserId) : null);

  // If artistUserId was passed for an artist who didn't have a profile row yet, create one
  if (!profile && artistUserId > 0) {
    const targetUser = db.find('users', artistUserId);
    if (targetUser) {
      profile = ensureArtistProfile(db, targetUser);
    }
  }

  // Fallback: if a stale form submitted the deleted admin profile_id or if only one real artist exists
  const realArtistProfiles = db.where('artist_profiles', (ap) => {
    const u = db.find('users', Number(ap.user_id));
    return !ap.is_seeded && (!u || (u.role || '') !== 'admin');
  });

  if (
    (!profile || Number(profile.user_id) === Number(admin.id)) &&
    realArtistProfiles.length > 0
  ) {
    profile = realArtistProfiles[0];
  }

  if (!profile && !applyToAll) {
    req.setFlash('error', 'Artist profile not found.');
    return res.redirect('/admin/dashboard#payouts');
  }

  if (actionType === 'unlock' || actionType === 'lock' || actionType === 'toggle_access') {
    const nextUnlocked =
      actionType === 'unlock'
        ? true
        : actionType === 'lock'
        ? false
        : !Boolean(profile?.withdrawal_unlocked);

    const targetProfiles =
      applyToAll && realArtistProfiles.length > 0
        ? realArtistProfiles
        : profile
        ? db.where('artist_profiles', (ap) => Number(ap.user_id) === Number(profile.user_id) || Number(ap.id) === Number(profile.id))
        : [];

    for (const tp of targetProfiles) {
      db.update('artist_profiles', tp.id, {
        withdrawal_unlocked: nextUnlocked,
      });
      if (tp.user_id) {
        db.update('users', Number(tp.user_id), {
          withdrawal_unlocked: nextUnlocked,
        });
      }
      db.insert('notifications', {
        user_id: Number(tp.user_id),
        type: 'wallet_access_updated',
        title: nextUnlocked
          ? '🔓 Studio Wallet Withdrawal Access Granted'
          : '🔒 Studio Wallet Withdrawal Access Locked',
        message: nextUnlocked
          ? 'ArtAlly Administration has unlocked your Studio Wallet withdrawal option. You may now click Withdraw on your Artist Page and submit your Crypto or Bank Wire details.'
          : 'Your Studio Wallet withdrawal button has been locked by ArtAlly Administration.',
        action_url: '/artist/dashboard#artist-wallet',
        read_at: null,
      });
    }

    const targetNames = targetProfiles.map((tp) => tp.display_name).join(', ') || profile?.display_name || 'Artist';

    logAudit(
      db,
      admin,
      nextUnlocked ? 'ARTIST_WITHDRAWAL_UNLOCKED' : 'ARTIST_WITHDRAWAL_LOCKED',
      'artist_profile',
      profile ? profile.id : 0,
      `${nextUnlocked ? 'Unlocked' : 'Locked'} wallet withdrawal button for ${targetNames}.`,
      req.ip
    );

    req.setFlash(
      'success',
      nextUnlocked
        ? `🔓 Withdrawal access GRANTED for ${targetNames}. The artist can now click Withdraw and submit Crypto or Bank Wire details.`
        : `🔒 Withdrawal access LOCKED for ${targetNames}.`
    );
    return res.redirect(`/admin/dashboard${returnAnchor}`);
  }

  if (actionType === 'credit_wallet' || actionType === 'set_balance') {
    const currentWallet = computeArtistWalletState(db, Number(profile.user_id), profile);
    const inputAmount = Math.max(0, Number(req.body.wallet_amount || 0));
    const currentManual = Number(profile.manual_wallet_adjustment || 0);
    const newManual =
      actionType === 'credit_wallet'
        ? currentManual + inputAmount
        : currentManual + (inputAmount - currentWallet.available_balance);

    const updatedProfile = db.update('artist_profiles', profile.id, {
      manual_wallet_adjustment: Math.round(newManual * 100) / 100,
    });
    const newWalletState = computeArtistWalletState(db, Number(profile.user_id), updatedProfile);

    if (inputAmount > 0) {
      db.insert('notifications', {
        user_id: Number(profile.user_id),
        type: 'payout_completed',
        title: `💰 Funds Disbursed to Studio Wallet (${helpers.format_money_exact(
          newWalletState.available_balance
        )})`,
        message: `ArtAlly Treasury has updated your Studio Wallet balance to ${helpers.format_money_exact(
          newWalletState.available_balance
        )}.`,
        action_url: '/artist/dashboard#artist-wallet',
        read_at: null,
      });
    }

    logAudit(
      db,
      admin,
      'ARTIST_WALLET_BALANCE_UPDATED',
      'artist_profile',
      profile.id,
      `Updated wallet balance for ${profile.display_name} to $${newWalletState.available_balance.toFixed(2)}.`,
      req.ip
    );
    req.setFlash(
      'success',
      `Updated Studio Wallet balance for ${profile.display_name} to ${helpers.format_money_exact(
        newWalletState.available_balance
      )}.`
    );
    return res.redirect(`/admin/dashboard${returnAnchor}`);
  }

  res.redirect(`/admin/dashboard${returnAnchor}`);
});

app.post('/admin/withdrawals/status', (req, res) => {
  const admin = requireRole(req, res, 'admin');
  if (!admin) return;
  const db = DatabaseEngine.getInstance();
  const withdrawalId = Number(req.body.withdrawal_id || 0);
  const newStatus = String(req.body.status || 'processing').trim();
  const wd = db.find('wallet_withdrawals', withdrawalId);

  if (!wd) {
    req.setFlash('error', 'Withdrawal request not found.');
    return res.redirect('/admin/dashboard#payouts');
  }

  if (!['processing', 'completed', 'rejected'].includes(newStatus)) {
    return res.redirect('/admin/dashboard#payouts');
  }

  db.update('wallet_withdrawals', withdrawalId, {
    status: newStatus,
    processed_by: Number(admin.id),
    processed_at: formatUtcNow(),
  });

  const artistProfile = db.findBy('artist_profiles', 'user_id', Number(wd.artist_id));
  computeArtistWalletState(db, Number(wd.artist_id), artistProfile);

  if (newStatus === 'completed') {
    db.insert('notifications', {
      user_id: Number(wd.artist_id),
      type: 'withdrawal_completed',
      title: `✓ Withdrawal Completed: ${helpers.format_money_exact(wd.amount)} (${wd.reference_code})`,
      message: `Your withdrawal of ${helpers.format_money_exact(wd.amount)} (${
        wd.reference_code
      }) has been finalized and settled by ArtAlly Treasury.`,
      action_url: '/artist/dashboard#artist-wallet',
      read_at: null,
    });
  } else if (newStatus === 'rejected') {
    db.insert('notifications', {
      user_id: Number(wd.artist_id),
      type: 'withdrawal_returned',
      title: `↺ Withdrawal Returned to Studio Wallet (${wd.reference_code})`,
      message: `Your withdrawal request (${wd.reference_code}) of ${helpers.format_money_exact(
        wd.amount
      )} was returned to your available Studio Wallet balance.`,
      action_url: '/artist/dashboard#artist-wallet',
      read_at: null,
    });
  }

  logAudit(
    db,
    admin,
    'ARTIST_WITHDRAWAL_STATUS_UPDATED',
    'wallet_withdrawal',
    withdrawalId,
    `Updated withdrawal ${wd.reference_code} status to ${newStatus.toUpperCase()}.`,
    req.ip
  );
  req.setFlash(
    'success',
    `Withdrawal ${wd.reference_code} marked as ${newStatus.toUpperCase()}.`
  );
  res.redirect('/admin/dashboard#payouts');
});

app.post('/admin/settings', (req, res) => {
  const admin = requireRole(req, res, 'admin');
  if (!admin) return;
  const db = DatabaseEngine.getInstance();
  const commissionRate = Math.max(0, Math.min(50, Number(req.body.commission_rate_percent ?? 15.0)));
  const buyerPremium = Math.max(0, Math.min(40, Number(req.body.buyer_premium_percent ?? 20.0)));
  const antiSniping = Boolean(req.body.anti_sniping_enabled);
  const brandName = String(req.body.brand_name || 'ArtAlly').trim() || 'ArtAlly';
  const buyerCode =
    String(req.body.buyer_verification_code || 'NOIR-BUYER-2026').trim() || 'NOIR-BUYER-2026';

  db.update('platform_settings', 1, {
    brand_name: brandName,
    commission_rate_percent: commissionRate,
    buyer_premium_percent: buyerPremium,
    anti_sniping_enabled: antiSniping,
    buyer_verification_code: buyerCode,
    contact_email: String(req.body.contact_email || 'Art-Ally@consultant.com').trim() || 'Art-Ally@consultant.com',
  });

  logAudit(
    db,
    admin,
    'SETTINGS_UPDATED',
    'platform_settings',
    1,
    `Updated Seller Commission to ${commissionRate}%, Buyer Premium to ${buyerPremium}%, Buyer Verification Code: ${buyerCode}`,
    req.ip
  );
  req.setFlash(
    'success',
    'Institutional platform settings, commission schedules, and Buyer Verification Code updated.'
  );
  res.redirect('/admin/dashboard#settings');
});

app.post('/admin/categories', (req, res) => {
  const admin = requireRole(req, res, 'admin');
  if (!admin) return;
  const db = DatabaseEngine.getInstance();
  const name = String(req.body.name || '').trim();
  const description = String(req.body.description || '').trim();
  const image =
    String(req.body.image || '').trim() ||
    'https://images.unsplash.com/photo-1579783902614-a3fb3927b675?auto=format&fit=crop&w=1000&q=80';

  if (name) {
    const cat = db.insert('categories', {
      name,
      slug: helpers.slugify(name),
      description,
      image,
    });
    logAudit(
      db,
      admin,
      'CATEGORY_CREATED',
      'category',
      cat.id,
      `Created department category '${name}'.`,
      req.ip
    );
    req.setFlash('success', `Curatorial department "${name}" created.`);
  }
  res.redirect('/admin/dashboard#categories');
});

app.post('/admin/users/toggle-status', (req, res) => {
  const admin = requireRole(req, res, 'admin');
  if (!admin) return;
  const db = DatabaseEngine.getInstance();
  const userId = Number(req.body.user_id || 0);
  const target = db.find('users', userId);

  if (target && Number(target.id) !== Number(admin.id)) {
    const newStatus = (target.status || 'active') === 'active' ? 'suspended' : 'active';
    db.update('users', userId, { status: newStatus });
    logAudit(
      db,
      admin,
      'USER_STATUS_CHANGED',
      'user',
      userId,
      `Changed status of ${target.email} to ${newStatus}.`,
      req.ip
    );
    req.setFlash('success', `Updated status for ${target.name} to ${newStatus.toUpperCase()}.`);
  }
  res.redirect('/admin/dashboard#users');
});

// ============================================================================
// 6. CURATORIAL & AI AUTHENTICATION INVOICE GENERATOR & DOWNLOAD ROUTES
// ============================================================================

app.post('/admin/invoices/generate', (req, res) => {
  const admin = requireRole(req, res, 'admin');
  if (!admin) return;
  const db = DatabaseEngine.getInstance();

  const artistIdInput = Number(req.body.artist_id || 0);
  const artworkIdInput = Number(req.body.artwork_id || 0);
  const artistName = String(req.body.artist_name || '').trim();
  const artistEmail = String(req.body.artist_email || '').trim();
  const artistDetails = String(req.body.artist_details || '').trim();
  const artworkTitle = String(req.body.artwork_title || '').trim();
  const artworkDetails = String(req.body.artwork_details || '').trim();
  const referenceCode = String(req.body.reference_code || '').trim();
  const serviceType =
    String(req.body.service_type || '').trim() ||
    'Independent Curatorial Connoisseurship & AI-Powered Authentication Evaluation';
  const feeAmount = Math.max(0, Number(req.body.fee_amount || 0));
  const currency = ['USD', 'EUR', 'GBP', 'CHF'].includes(req.body.currency)
    ? req.body.currency
    : 'USD';
  const dueDate =
    String(req.body.due_date || '').trim() ||
    helpers.gmdate('Y-m-d', helpers.strtotime('+3 days'));
  const status = req.body.status === 'paid' ? 'paid' : 'unpaid';

  const paymentMethod = ['bank', 'crypto', 'bank_and_crypto'].includes(req.body.payment_method)
    ? req.body.payment_method
    : 'bank_and_crypto';
  const paymentAccountName = String(req.body.payment_account_name || '').trim();
  const paymentBankName = String(req.body.payment_bank_name || '').trim();
  const paymentAccountNumber = String(req.body.payment_account_number || '').trim();
  const paymentRoutingDetails = String(req.body.payment_routing_details || '').trim();
  const paymentBankAddress = String(req.body.payment_bank_address || '').trim();

  const cryptoCurrency = String(req.body.crypto_currency || '').trim();
  const cryptoNetwork = String(req.body.crypto_network || '').trim();
  const cryptoWalletAddress = String(req.body.crypto_wallet_address || '').trim();
  const cryptoMemo = String(req.body.crypto_memo || '').trim();

  const paymentInstructions = String(req.body.payment_instructions || '').trim();

  if (
    !artistName ||
    !artworkTitle ||
    feeAmount <= 0 ||
    (!paymentAccountNumber && !cryptoWalletAddress)
  ) {
    req.setFlash(
      'error',
      'Please enter the Artist Name, Artwork Being Verified, Fee to be Paid, and at least one Receiving Payment Method (Bank Account Number or Cryptocurrency Wallet Address).'
    );
    return res.redirect('/admin/dashboard#invoice-generator');
  }

  // Resolve artist_id if not explicitly selected from dropdown
  let resolvedArtistId = artistIdInput > 0 ? artistIdInput : null;
  if (!resolvedArtistId && artistEmail) {
    const matchedUser = db.findBy('users', 'email', artistEmail.toLowerCase());
    if (matchedUser) resolvedArtistId = Number(matchedUser.id);
  }
  if (!resolvedArtistId && artistName) {
    const matchedProfile = db
      .all('artist_profiles')
      .find(
        (ap) =>
          String(ap.display_name || '').trim().toLowerCase() === artistName.toLowerCase()
      );
    if (matchedProfile) resolvedArtistId = Number(matchedProfile.user_id);
  }

  const randomNum = Math.floor(1000 + Math.random() * 9000);
  const invoiceNumber = `INV-AUTH-${helpers.gmdate('Y')}-${randomNum}`;

  const invoice = db.insert('authentication_invoices', {
    invoice_number: invoiceNumber,
    artist_id: resolvedArtistId,
    artwork_id: artworkIdInput > 0 ? artworkIdInput : null,
    artist_name: artistName,
    artist_email: artistEmail,
    artist_details: artistDetails,
    artwork_title: artworkTitle,
    artwork_details: artworkDetails,
    reference_code: referenceCode,
    service_type: serviceType,
    fee_amount: feeAmount,
    currency,
    due_date: dueDate,
    status,
    payment_method: paymentMethod,
    payment_account_name: paymentAccountName,
    payment_bank_name: paymentBankName,
    payment_account_number: paymentAccountNumber,
    payment_routing_details: paymentRoutingDetails,
    payment_bank_address: paymentBankAddress,
    crypto_currency: cryptoCurrency,
    crypto_network: cryptoNetwork,
    crypto_wallet_address: cryptoWalletAddress,
    crypto_memo: cryptoMemo,
    payment_instructions: paymentInstructions,
    created_by: Number(admin.id),
  });

  if (resolvedArtistId) {
    db.insert('notifications', {
      user_id: resolvedArtistId,
      type: 'authentication_invoice_issued',
      title: `📄 Authentication Invoice Issued: ${invoiceNumber} (${helpers.format_money_exact(
        feeAmount,
        currency
      )})`,
      message: `An official Curatorial & AI-Powered Authentication Payment Invoice (${invoiceNumber}) for "${artworkTitle}" has been generated. A copy is now available to view and download on your Artist Page and Studio Dashboard.`,
      action_url: `/invoices/${invoice.id}`,
      read_at: null,
    });
  }

  logAudit(
    db,
    admin,
    'AUTHENTICATION_INVOICE_GENERATED',
    'authentication_invoice',
    invoice.id,
    `Generated authentication invoice ${invoiceNumber} for ${artistName} ("${artworkTitle}") — Fee: ${currency} ${feeAmount}`,
    req.ip
  );

  req.setFlash(
    'success',
    `Authentication Invoice ${invoiceNumber} generated for ${artistName} ("${artworkTitle}"). Opening print dialog and published to the Artist Page for download.`
  );
  res.redirect(`/invoices/${invoice.id}?print=1`);
});

app.post('/admin/invoices/status', (req, res) => {
  const admin = requireRole(req, res, 'admin');
  if (!admin) return;
  const db = DatabaseEngine.getInstance();
  const invoiceId = Number(req.body.invoice_id || 0);
  const inv = db.find('authentication_invoices', invoiceId);
  if (!inv) {
    req.setFlash('error', 'Invoice not found.');
    return res.redirect('/admin/dashboard#invoice-generator');
  }
  const newStatus = (inv.status || 'unpaid') === 'paid' ? 'unpaid' : 'paid';
  db.update('authentication_invoices', invoiceId, {
    status: newStatus,
    paid_at: newStatus === 'paid' ? formatUtcNow() : null,
  });

  if (newStatus === 'paid' && inv.artist_id) {
    db.insert('notifications', {
      user_id: Number(inv.artist_id),
      type: 'authentication_invoice_paid',
      title: `✓ Authentication Payment Confirmed: ${inv.invoice_number}`,
      message: `Your payment of ${helpers.format_money_exact(
        inv.fee_amount,
        inv.currency || 'USD'
      )} for "${inv.artwork_title}" has been verified. Curatorial & AI authentication is now active.`,
      action_url: `/invoices/${inv.id}`,
      read_at: null,
    });
  }

  logAudit(
    db,
    admin,
    'AUTHENTICATION_INVOICE_STATUS_UPDATED',
    'authentication_invoice',
    invoiceId,
    `Updated invoice ${inv.invoice_number} status to ${newStatus.toUpperCase()}.`,
    req.ip
  );
  req.setFlash(
    'success',
    `Invoice ${inv.invoice_number} marked as ${newStatus.toUpperCase()}.`
  );
  res.redirect('/admin/dashboard#invoice-generator');
});

app.post('/admin/invoices/delete', (req, res) => {
  const admin = requireRole(req, res, 'admin');
  if (!admin) return;
  const db = DatabaseEngine.getInstance();
  const invoiceId = Number(req.body.invoice_id || 0);
  const inv = db.find('authentication_invoices', invoiceId);
  if (inv) {
    db.delete('authentication_invoices', invoiceId);
    logAudit(
      db,
      admin,
      'AUTHENTICATION_INVOICE_DELETED',
      'authentication_invoice',
      invoiceId,
      `Deleted invoice ${inv.invoice_number}.`,
      req.ip
    );
    req.setFlash('success', `Deleted invoice ${inv.invoice_number}.`);
  }
  res.redirect('/admin/dashboard#invoice-generator');
});

app.post('/admin/database/clear-all', async (req, res) => {
  const admin = requireRole(req, res, 'admin');
  if (!admin) return;
  const db = DatabaseEngine.getInstance();

  const preservedAdmin = await db.clearAllBackendAndDemoData(admin);
  req.loginUser(preservedAdmin);

  req.setFlash(
    'success',
    '✓ All backend data and demo details have been permanently wiped from both the local store and Supabase PostgreSQL. Your platform is now 100% clean for fresh new entries.'
  );
  res.redirect('/admin/dashboard');
});

function canAccessAuthenticationInvoice(req, db, invoice) {
  const user = req.getCurrentUser();
  const adminUser = req.sessionData?.admin_user_id
    ? db.find('users', Number(req.sessionData.admin_user_id))
    : null;
  if (adminUser && helpers.userHasRole(adminUser, 'admin')) return true;
  if (!user) return false;
  if (helpers.userHasRole(user, 'admin')) return true;
  if (invoice.artist_id && Number(invoice.artist_id) === Number(user.id)) return true;
  if (
    invoice.artist_email &&
    String(invoice.artist_email).trim().toLowerCase() === String(user.email || '').trim().toLowerCase()
  ) {
    return true;
  }
  const artistProfile = db.findBy('artist_profiles', 'user_id', Number(user.id));
  const artistNameLower = String(invoice.artist_name || '').trim().toLowerCase();
  if (
    artistNameLower &&
    (artistNameLower === String(user.name || '').trim().toLowerCase() ||
      artistNameLower === String(artistProfile?.display_name || '').trim().toLowerCase())
  ) {
    return true;
  }
  return false;
}

app.get('/invoices/:id', (req, res) => {
  const user = requireAuth(req, res);
  if (!user) return;
  const db = DatabaseEngine.getInstance();
  const invoiceId = Number(req.params.id || 0);
  const invoice = db.find('authentication_invoices', invoiceId);
  if (!invoice) {
    return res.renderPage('pages/404', { pageTitle: 'Invoice Not Found — ArtAlly' }, 404);
  }
  if (!canAccessAuthenticationInvoice(req, db, invoice)) {
    req.setFlash('error', 'Access denied. Authentication invoices are private to the issuing administrator and the designated artist.');
    return res.redirect('/');
  }

  res.renderPage('public/authentication-invoice', {
    pageTitle: `Authentication Invoice ${invoice.invoice_number} — ${invoice.artwork_title} | ArtAlly`,
    invoice,
  });
});

app.get('/invoices/:id/download', (req, res) => {
  const user = requireAuth(req, res);
  if (!user) return;
  const db = DatabaseEngine.getInstance();
  const invoiceId = Number(req.params.id || 0);
  const invoice = db.find('authentication_invoices', invoiceId);
  if (!invoice) {
    return res.renderPage('pages/404', { pageTitle: 'Invoice Not Found — ArtAlly' }, 404);
  }
  if (!canAccessAuthenticationInvoice(req, db, invoice)) {
    req.setFlash('error', 'Access denied. Authentication invoices are private to the issuing administrator and the designated artist.');
    return res.redirect('/');
  }

  const safeNum = String(invoice.invoice_number || `INV-${invoiceId}`).replace(/[^a-zA-Z0-9_-]/g, '-');
  const currency = invoice.currency || 'USD';
  const isPaid = (invoice.status || 'unpaid') === 'paid';
  const feeFormatted = helpers.format_money_exact(Number(invoice.fee_amount || 0), currency);

  const standaloneHtml = `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ArtAlly Authentication Invoice — ${helpers.e(invoice.invoice_number)}</title>
<style>
  body { font-family: Georgia, 'Times New Roman', serif; background: #F4EFE6; color: #161514; margin: 0; padding: 32px 16px; }
  .sheet { max-width: 840px; margin: 0 auto; background: #FBF9F5; border: 2px solid #B8965A; padding: 42px; box-sizing: border-box; }
  .inner { border: 1px solid rgba(184,150,90,0.45); padding: 32px; }
  .top-actions { max-width: 840px; margin: 0 auto 16px; display: flex; justify-content: flex-end; gap: 12px; font-family: sans-serif; }
  .btn { background: #161514; color: #C6A664; border: 1px solid #C6A664; padding: 10px 18px; cursor: pointer; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; font-size: 12px; text-decoration: none; }
  .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #161514; padding-bottom: 20px; margin-bottom: 24px; gap: 16px; flex-wrap: wrap; }
  .eyebrow { font-family: sans-serif; font-size: 11px; letter-spacing: 0.24em; text-transform: uppercase; color: #8C6D32; font-weight: 700; margin: 0 0 6px; }
  h1 { font-size: 32px; margin: 0 0 6px; font-weight: 500; letter-spacing: 0.03em; }
  .badge { display: inline-block; font-family: sans-serif; font-size: 11px; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; padding: 5px 12px; border: 1px solid ${isPaid ? '#2D6A4F' : '#8C6D32'}; background: ${isPaid ? '#E6F4EA' : '#F7EFE0'}; color: ${isPaid ? '#1B4332' : '#705522'}; margin-bottom: 8px; }
  .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; }
  .box { background: rgba(22,21,20,0.03); border: 1px solid #DCD5C9; padding: 18px; }
  .label { font-family: sans-serif; font-size: 11px; text-transform: uppercase; letter-spacing: 0.15em; color: #8C6D32; font-weight: 700; margin: 0 0 8px; }
  table { width: 100%; border-collapse: collapse; border: 1px solid #161514; margin-bottom: 24px; font-family: sans-serif; }
  th { background: #161514; color: #FBF9F5; text-align: left; padding: 12px 14px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.12em; }
  td { padding: 16px 14px; border-bottom: 1px solid #DCD5C9; font-size: 14px; vertical-align: top; }
  .bank-box { background: #161514; color: #F4EFE6; padding: 24px; border: 1px solid #B8965A; margin-bottom: 24px; font-family: sans-serif; }
  .bank-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 14px; }
  .bank-item-label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.12em; color: #A69F93; margin: 0 0 4px; }
  .bank-item-val { font-family: monospace; font-size: 15px; font-weight: 700; color: #FFFFFF; margin: 0; word-break: break-all; }
  @media print { .top-actions { display: none !important; } body { background: #fff; padding: 0; } .sheet { border: 2px solid #000; } }
</style>
</head>
<body>
  <div class="top-actions">
    <button class="btn" onclick="window.print()">🖨 Print / Save as PDF</button>
  </div>
  <div class="sheet">
    <div class="inner">
      <div class="header">
        <div>
          <p class="eyebrow">ARTALLY · CURATORIAL &amp; AI AUTHENTICATION DIVISION</p>
          <h1>AUTHENTICATION INVOICE</h1>
          <p style="margin:0;font-size:13px;color:#57524C;font-family:sans-serif;">Independent Connoisseurship &amp; AI-Powered Provenance Verification Protocol · Contact: <strong>Art-Ally@consultant.com</strong></p>
        </div>
        <div style="text-align:right;font-family:sans-serif;">
          <div class="badge">${isPaid ? '✓ PAID & VERIFIED' : '● UNPAID — AWAITING PAYMENT'}</div>
          <p style="margin:0;font-family:monospace;font-size:15px;font-weight:700;">Invoice No: ${helpers.e(invoice.invoice_number)}</p>
          <p style="margin:4px 0 0;font-size:12px;color:#57524C;">Issued: ${helpers.e(String(invoice.created_at || '').substring(0, 10))}</p>
          ${invoice.due_date ? `<p style="margin:3px 0 0;font-size:12px;color:#8C2D2D;font-weight:700;">Due Date: ${helpers.e(invoice.due_date)}</p>` : ''}
        </div>
      </div>

      <div class="grid">
        <div class="box">
          <p class="label">Billed To (Artist &amp; Studio Details)</p>
          <p style="font-size:20px;font-weight:700;margin:0 0 6px;">${helpers.e(invoice.artist_name)}</p>
          ${invoice.artist_email ? `<p style="font-family:sans-serif;font-size:13px;margin:0 0 6px;"><strong>Email:</strong> ${helpers.e(invoice.artist_email)}</p>` : ''}
          ${invoice.artist_details ? `<div style="font-family:sans-serif;font-size:13px;color:#57524C;line-height:1.5;margin-top:8px;padding-top:8px;border-top:1px dashed #DCD5C9;">${String(helpers.e(invoice.artist_details)).replace(/\r?\n/g, '<br>')}</div>` : ''}
        </div>
        <div class="box">
          <p class="label">Masterpiece Subject to Verification</p>
          <p style="font-size:20px;font-weight:700;font-style:italic;margin:0 0 6px;">&ldquo;${helpers.e(invoice.artwork_title)}&rdquo;</p>
          <p style="font-family:sans-serif;font-size:13px;color:#8C6D32;font-weight:700;margin:0 0 6px;">${helpers.e(invoice.service_type)}</p>
          ${invoice.artwork_details ? `<div style="font-family:sans-serif;font-size:13px;color:#57524C;line-height:1.5;margin-top:8px;padding-top:8px;border-top:1px dashed #DCD5C9;">${String(helpers.e(invoice.artwork_details)).replace(/\r?\n/g, '<br>')}</div>` : ''}
        </div>
      </div>

      <table>
        <thead>
          <tr>
            <th>Verification &amp; Evaluation Description</th>
            <th>Artwork Reference</th>
            <th style="text-align:right;">Fee Amount (${helpers.e(currency)})</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>
              <strong style="display:block;margin-bottom:4px;">${helpers.e(invoice.service_type)}</strong>
              <span style="font-size:12px;color:#57524C;line-height:1.5;">Mandatory pre-auction authentication and evaluation through ArtAlly’s independent connoisseurship experts and AI-powered forensic authentication suite.</span>
            </td>
            <td>
              <strong>${helpers.e(invoice.artwork_title)}</strong>
              ${invoice.reference_code ? `<br><span style="font-family:monospace;font-size:12px;color:#6E6A63;">Ref: ${helpers.e(invoice.reference_code)}</span>` : ''}
            </td>
            <td style="text-align:right;font-family:monospace;font-size:16px;font-weight:700;">${feeFormatted}</td>
          </tr>
        </tbody>
        <tfoot>
          <tr style="background:#F3E9D2;">
            <td colspan="2" style="text-align:right;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;font-size:12px;">Total Authentication Fee To Be Paid</td>
            <td style="text-align:right;font-size:20px;font-weight:700;color:#8C6D32;font-family:Georgia,serif;">${feeFormatted} ${helpers.e(currency)}</td>
          </tr>
        </tfoot>
      </table>

      <div class="bank-box">
        <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid rgba(184,150,90,0.35);padding-bottom:10px;flex-wrap:wrap;gap:8px;">
          <div>
            <p style="margin:0;font-size:11px;text-transform:uppercase;letter-spacing:0.18em;color:#C6A664;font-weight:700;">Official Payment Remittance Instructions</p>
            <h3 style="margin:4px 0 0;font-family:Georgia,serif;font-size:19px;color:#F4EFE6;">Designated Bank &amp; Cryptocurrency Payment Details</h3>
          </div>
          <span style="font-family:monospace;font-size:12px;padding:4px 10px;border:1px solid #C6A664;color:#C6A664;">QUOTE INVOICE NO: ${helpers.e(invoice.invoice_number)}</span>
        </div>

        ${
          invoice.payment_account_number || invoice.payment_bank_name || invoice.payment_account_name
            ? `<div style="margin-top:14px;padding-bottom:14px;border-bottom:1px solid rgba(255,255,255,0.12);">
                <p style="margin:0 0 10px;font-size:11px;text-transform:uppercase;letter-spacing:0.15em;color:#C6A664;font-weight:700;">Option A — Bank Wire / Financial Institution Transfer</p>
                <div class="bank-grid" style="margin-top:0;">
                  ${invoice.payment_account_name ? `<div><p class="bank-item-label">Beneficiary / Account Name</p><p class="bank-item-val">${helpers.e(invoice.payment_account_name)}</p></div>` : ''}
                  ${invoice.payment_bank_name ? `<div><p class="bank-item-label">Bank / Financial Institution</p><p class="bank-item-val">${helpers.e(invoice.payment_bank_name)}</p></div>` : ''}
                  ${invoice.payment_account_number ? `<div><p class="bank-item-label">Account Number / IBAN</p><p class="bank-item-val" style="color:#C6A664;">${helpers.e(invoice.payment_account_number)}</p></div>` : ''}
                  ${invoice.payment_routing_details ? `<div><p class="bank-item-label">Routing / SWIFT / BIC / Sort Code</p><p class="bank-item-val">${helpers.e(invoice.payment_routing_details)}</p></div>` : ''}
                  ${invoice.payment_bank_address ? `<div style="grid-column: 1 / -1;"><p class="bank-item-label">Bank Address / Branch Location</p><p class="bank-item-val">${helpers.e(invoice.payment_bank_address)}</p></div>` : ''}
                </div>
              </div>`
            : ''
        }

        ${
          invoice.crypto_wallet_address || invoice.crypto_currency
            ? `<div style="margin-top:14px;padding-bottom:8px;">
                <p style="margin:0 0 10px;font-size:11px;text-transform:uppercase;letter-spacing:0.15em;color:#C6A664;font-weight:700;">${
                  invoice.payment_account_number ? 'Option B — ' : ''
                }Cryptocurrency / Digital Asset Remittance</p>
                <div class="bank-grid" style="margin-top:0;">
                  ${invoice.crypto_currency ? `<div><p class="bank-item-label">Accepted Cryptocurrency / Asset</p><p class="bank-item-val">${helpers.e(invoice.crypto_currency)}</p></div>` : ''}
                  ${invoice.crypto_network ? `<div><p class="bank-item-label">Blockchain Network / Protocol</p><p class="bank-item-val">${helpers.e(invoice.crypto_network)}</p></div>` : ''}
                  ${invoice.crypto_wallet_address ? `<div style="grid-column: 1 / -1;"><p class="bank-item-label">Receiving Crypto Wallet Address</p><p class="bank-item-val" style="color:#C6A664;background:rgba(255,255,255,0.06);padding:8px 10px;border:1px solid rgba(198,166,100,0.4);">${helpers.e(invoice.crypto_wallet_address)}</p></div>` : ''}
                  ${invoice.crypto_memo ? `<div style="grid-column: 1 / -1;"><p class="bank-item-label">Destination Tag / Memo / Network Note</p><p class="bank-item-val">${helpers.e(invoice.crypto_memo)}</p></div>` : ''}
                </div>
              </div>`
            : ''
        }

        ${invoice.payment_instructions ? `<div style="margin-top:14px;padding-top:12px;border-top:1px solid rgba(255,255,255,0.14);font-size:13px;color:#D8D1C5;line-height:1.5;"><strong style="color:#C6A664;">Payment Instructions:</strong><br>${String(helpers.e(invoice.payment_instructions)).replace(/\r?\n/g, '<br>')}</div>` : ''}
      </div>

      <div style="display:flex;justify-content:space-between;align-items:flex-end;border-top:1px solid #DCD5C9;padding-top:16px;font-family:sans-serif;flex-wrap:wrap;gap:16px;">
        <p style="margin:0;font-size:12px;color:#57524C;max-width:460px;line-height:1.5;">
          Once authentication fee remittance is verified, independent connoisseurship &amp; AI-powered authentication completes and your masterpiece is scheduled for live bidding after the 72-hour waiting period.
        </p>
        <div style="text-align:right;">
          <p style="margin:0 0 4px;font-family:Georgia,serif;font-size:18px;font-style:italic;">ArtAlly Curatorial &amp; AI Board</p>
          <p style="margin:0;font-size:10px;text-transform:uppercase;letter-spacing:0.15em;color:#8C6D32;font-weight:700;">Authorized Provenance &amp; Billing Seal</p>
        </div>
      </div>
    </div>
  </div>
</body>
</html>`;

  res.set('Content-Type', 'text/html; charset=UTF-8');
  res.set('Content-Disposition', `attachment; filename="ArtAlly-${safeNum}.html"`);
  res.send(standaloneHtml);
});

// Automatic Auction Countdown Expiration & Sync Endpoint
app.all('/api/auctions/sync', (req, res) => {
  try {
    const closedCount = AuctionEngineService.synchronizeAuctions();
    res.json({ ok: true, closedCount });
  } catch (err) {
    res.status(500).json({ ok: false, error: err.message });
  }
});

// 404 Handler
app.use((req, res) => {
  res.renderPage('pages/404', { pageTitle: 'Page Not Found — ArtAlly' }, 404);
});

module.exports = app;
