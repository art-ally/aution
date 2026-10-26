const fs = require('fs');
const path = require('path');
const ejs = require('ejs');
const helpers = require('./helpers');

const VIEWS_PATH = path.join(__dirname, '..', 'views');

/**
 * Translates a PHP template expression/block into valid JavaScript for EJS
 */
function phpCodeToJs(code, isEcho = false) {
  let s = code.trim();

  // Remove PHP docblocks / comments if entire block is comment
  s = s.replace(/\/\*[\s\S]*?\*\//g, '');

  // Handle require VIEWS_PATH . '/partials/artwork-card.php';
  s = s.replace(
    /require\s+VIEWS_PATH\s*\.\s*['"]\/partials\/artwork-card\.php['"]\s*;/g,
    '__output += __renderPartial("partials/artwork-card", { lot: typeof $lot !== "undefined" ? $lot : null, watchedIds: typeof $watchedIds !== "undefined" ? $watchedIds : undefined });'
  );

  // Replace \App\Core\Auth::hasRole(...)
  s = s.replace(/\\?App\\Core\\Auth::hasRole\(/g, 'hasRole(');

  // Replace PHP arrow functions: fn($x) => ... with ($x) => ...
  s = s.replace(/\bfn\s*\(([^)]*)\)\s*=>/g, '($1) =>');

  // Replace PHP associative array literal ['view' => 'grid']
  s = s.replace(/\[\s*(['"][^'"]+['"])\s*=>\s*([^\]]+)\]/g, '{ $1: $2 }');

  // Replace PHP constants
  s = s.replace(/\bPHP_URL_PATH\b/g, '"PHP_URL_PATH"');

  // Replace PHP Elvis operator `?:` with `||`
  s = s.replace(/\?\s*:/g, '||');

  // Replace PHP type casts: (int), (float), (string)
  s = s.replace(
    /\(\s*(?:int|float)\s*\)\s*(\([^)]+\)|[a-zA-Z0-9_$]+(?:\([^)]*\)|\[['"][^'"]+['"]\])*)/g,
    'Number($1)'
  );
  s = s.replace(
    /\(\s*string\s*\)\s*(\([^)]+\)|[a-zA-Z0-9_$]+(?:\([^)]*\)|\[['"][^'"]+['"]\])*)/g,
    'String($1 ?? "")'
  );

  // Replace isset($x)
  s = s.replace(/\bisset\s*\(\s*(\$[a-zA-Z0-9_]+)\s*\)/g, '(typeof $1 !== "undefined" && $1 !== null)');

  if (!isEcho) {
    // Control structures in <?php ... ?>
    // foreach ($arr as $k => $v):
    s = s.replace(
      /\bforeach\s*\(\s*(.+?)\s+as\s+(\$[a-zA-Z0-9_]+)\s*=>\s*(\$[a-zA-Z0-9_]+)\s*\)\s*:/g,
      'for (const [__k, $3] of Object.entries($1 || {})) { const $2 = isNaN(__k) ? __k : Number(__k);'
    );
    // foreach ($arr as $v):
    s = s.replace(
      /\bforeach\s*\(\s*(.+?)\s+as\s+(\$[a-zA-Z0-9_]+)\s*\)\s*:/g,
      'for (const $2 of Object.values($1 || [])) {'
    );
    s = s.replace(/\bendforeach\s*;/g, '}');

    // if / elseif / else / endif
    s = s.replace(/\belseif\s*\(([\s\S]+?)\)\s*:/g, '} else if ($1) {');
    s = s.replace(/\bif\s*\(([\s\S]+?)\)\s*:/g, 'if ($1) {');
    s = s.replace(/\belse\s*:/g, '} else {');
    s = s.replace(/\bendif\s*;/g, '}');
  }

  // Convert bracket property access $var['prop'] to optional chaining $var?.['prop'] on RHS
  // Wait: avoid converting LHS of assignment (there are no $a['b'] = ... in views, only $x = ...)
  s = s.replace(/(\$\$?[a-zA-Z0-9_]+|\))(\[['"][^'"]+['"]\])/g, '$1?.$2');
  // Second pass for chained brackets like $a?.['b']['c'] -> $a?.['b']?.['c']
  s = s.replace(/(\]\s*)(\[['"][^'"]+['"]\])/g, '$1?.$2');
  s = s.replace(/(\]\s*)(\[['"][^'"]+['"]\])/g, '$1?.$2');

  // Convert PHP string concatenation ` . ` (dot surrounded by whitespace) into ` + `
  s = s.replace(/\s+\.\s+/g, ' + ');

  // In non-echo blocks, prefix standalone top-level `$var = ...` with `var $var = ...` so it's declared in scope
  if (!isEcho) {
    s = s.replace(/(^|[;\n{]\s*)(\$[a-zA-Z0-9_]+)\s*=(?!=)/g, '$1var $2 =');
  }

  return s;
}

function convertPhpTemplateToEjs(phpContent) {
  return phpContent.replace(/<\?(?:php|=)([\s\S]*?)\?>/g, (fullMatch, inner) => {
    const isEcho = fullMatch.startsWith('<?=');
    const js = phpCodeToJs(inner, isEcho);
    if (isEcho) {
      return `<%- ${js} %>`;
    }
    return `<% ${js} %>`;
  });
}

function createTemplateContext(data = {}, reqContext = {}) {
  const {
    currentUser = null,
    unreadCount = 0,
    watchlistIds = [],
    csrfToken = '',
    flashMessages = {},
    requestUri = '/',
    host = 'localhost:8000',
  } = reqContext;

  const ctx = {
    ...helpers,
    current_user: () => currentUser,
    unread_notifications_count: () => unreadCount,
    user_watchlist_ids: () => watchlistIds,
    csrf_field: () => `<input type="hidden" name="_csrf_token" value="${helpers.e(csrfToken)}">`,
    flash: (key) => flashMessages[key] || null,
    hasRole: (role) => helpers.userHasRole(currentUser, role),
    empty: (val) => helpers.isEmpty(val),
    is_array: (val) => Array.isArray(val),
    in_array: (needle, haystack) => {
      if (!Array.isArray(haystack)) return false;
      return haystack.some((item) => item === needle || Number(item) === Number(needle));
    },
    count: (arr) => (Array.isArray(arr) ? arr.length : arr && typeof arr === 'object' ? Object.keys(arr).length : 0),
    array_slice: (arr, offset, len) => {
      const list = Array.isArray(arr) ? arr : Object.values(arr || {});
      return len !== undefined ? list.slice(offset, offset + len) : list.slice(offset);
    },
    array_filter: (arr, fn) => {
      const list = Array.isArray(arr) ? arr : Object.values(arr || {});
      return fn ? list.filter(fn) : list.filter(Boolean);
    },
    array_map: (fn, arr) => {
      const list = Array.isArray(arr) ? arr : Object.values(arr || {});
      return list.map(fn);
    },
    array_values: (arr) => (Array.isArray(arr) ? arr : Object.values(arr || {})),
    array_merge: (...objs) => Object.assign({}, ...objs),
    http_build_query: (params) =>
      new URLSearchParams(
        Object.entries(params || {}).filter(([, v]) => v !== null && v !== undefined && v !== '')
      ).toString(),
    round: (val, precision = 0) => {
      const f = Math.pow(10, precision);
      return Math.round(Number(val || 0) * f) / f;
    },
    abs: (n) => Math.abs(Number(n || 0)),
    max: (...args) => Math.max(...args.map(Number)),
    min: (...args) => Math.min(...args.map(Number)),
    nl2br: (str) => String(str ?? '').replace(/\r?\n/g, '<br>'),
    substr: (str, start, len) =>
      len !== undefined
        ? String(str ?? '').substring(start, start + len)
        : String(str ?? '').substring(start),
    trim: (str) => String(str ?? '').trim(),
    ucfirst: (str) => {
      const s = String(str ?? '');
      return s.charAt(0).toUpperCase() + s.slice(1);
    },
    explode: (sep, str) => String(str ?? '').split(sep),
    strtoupper: (str) => String(str ?? '').toUpperCase(),
    strtolower: (str) => String(str ?? '').toLowerCase(),
    str_replace: (search, replace, subject) => String(subject ?? '').split(String(search)).join(String(replace)),
    str_starts_with: (str, prefix) => String(str ?? '').startsWith(String(prefix)),
    number_format: (num, decimals = 0) =>
      Number(num || 0).toLocaleString('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
      }),
    parse_url: (u) => {
      try {
        return new URL(u, 'http://localhost').pathname;
      } catch (_) {
        return String(u || '/').split('?')[0] || '/';
      }
    },
    $_GET: reqContext.query || {},
    $_SERVER: {
      REQUEST_URI: requestUri,
      HTTP_HOST: host,
    },
    $pageTitle: data.pageTitle ?? null,
    $metaDescription: data.metaDescription ?? null,
    $content: data.content ?? '',
    $lot: data.lot ?? null,
    $watchedIds: data.watchedIds ?? watchlistIds,
    $statusFilter: data.statusFilter ?? 'active',
    $filters: data.filters ?? {},
    $artworks: data.artworks ?? [],
    $categories: data.categories ?? [],
    $artists: data.artists ?? [],
    $articles: data.articles ?? [],
    $featuredAuctions: data.featuredAuctions ?? [],
    $upcomingAuctions: data.upcomingAuctions ?? [],
    $lots: data.lots ?? [],
    $artwork: data.artwork ?? {},
    $images: data.images ?? [],
    $auction: data.auction ?? null,
    $bids: data.bids ?? [],
    $related: data.related ?? [],
    $artist: data.artist ?? {},
    $isFollowing: data.isFollowing ?? false,
    $isApproved: data.isApproved ?? true,
    $article: data.article ?? {},
    $moreArticles: data.moreArticles ?? [],
    $settings: data.settings ?? {},
    $pageKey: data.pageKey ?? 'about',
    $meta: data.meta ?? { title: '', subtitle: '' },
    $order: data.order ?? null,
    $artistUser: data.artistUser ?? {},
    $artistProfile: data.artistProfile ?? {},
    $buyerUser: data.buyerUser ?? {},
    $selectedRole: data.selectedRole ?? 'artist',
    $buyerVerificationCode: data.buyerVerificationCode ?? 'NOIR-BUYER-2026',
    $user: data.user ?? currentUser,
    $token: data.token ?? '',
    $email: data.email ?? '',
    $profile: data.profile ?? {},
    $stats: data.stats ?? {},
    $submissions: data.submissions ?? [],
    $auctions: data.auctions ?? [],
    $orders: data.orders ?? [],
    $payouts: data.payouts ?? [],
    $notifications: data.notifications ?? [],
    $draftSubmission: data.draftSubmission ?? null,
    $draftArtwork: data.draftArtwork ?? null,
    $watchedArtworks: data.watchedArtworks ?? [],
    $myBids: data.myBids ?? [],
    $followedArtists: data.followedArtists ?? [],
    $noAdminsExist: data.noAdminsExist ?? false,
    $admin: data.admin ?? currentUser,
    $users: data.users ?? [],
    $artistProfiles: data.artistProfiles ?? [],
    $auditLogs: data.auditLogs ?? [],
    $reports: data.reports ?? [],
    $subscribers: data.subscribers ?? [],
    $authInvoices: data.authInvoices ?? [],
    $invoice: data.invoice ?? null,
    $allArtistsList: data.allArtistsList ?? [],
    $allArtworksList: data.allArtworksList ?? [],
  };

  // Also bind all passed data keys with and without leading $
  for (const [k, v] of Object.entries(data)) {
    ctx[k] = v;
    ctx[`$${k}`] = v;
  }

  ctx.__renderPartial = (partialName, partialData = {}) => {
    return renderTemplateFile(partialName, { ...data, ...partialData }, reqContext);
  };

  return ctx;
}

function renderTemplateFile(templateName, data = {}, reqContext = {}) {
  const cleanName = templateName.replace(/\./g, '/');
  const ejsFile = path.join(VIEWS_PATH, `${cleanName}.ejs`);
  const phpFile = path.join(VIEWS_PATH, `${cleanName}.php`);

  let ejsSource = '';
  if (fs.existsSync(ejsFile)) {
    ejsSource = fs.readFileSync(ejsFile, 'utf8');
  } else if (fs.existsSync(phpFile)) {
    const phpSource = fs.readFileSync(phpFile, 'utf8');
    ejsSource = convertPhpTemplateToEjs(phpSource);
  } else {
    throw new Error(`View [${templateName}] not found.`);
  }

  const ctx = createTemplateContext(data, reqContext);
  return ejs.render(ejsSource, ctx, { rmWhitespace: false });
}

function renderView(res, templateName, data = {}, layout = 'layouts/main') {
  const reqContext = res.locals.reqContext || {};
  const content = renderTemplateFile(templateName, data, reqContext);
  if (!layout) {
    res.send(content);
    return;
  }
  const fullHtml = renderTemplateFile(layout, { ...data, content }, reqContext);
  res.send(fullHtml);
}

module.exports = {
  convertPhpTemplateToEjs,
  renderTemplateFile,
  renderView,
};
