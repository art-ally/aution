const crypto = require('crypto');
const { DatabaseEngine, formatUtcNow } = require('./db');

function e(value) {
  if (value === null || value === undefined) return '';
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function format_money(amount, currency = 'USD') {
  const num = Number(amount || 0);
  const symbols = {
    USD: '$',
    EUR: '€',
    GBP: '£',
    CHF: 'CHF ',
  };
  const symbol = symbols[currency] !== undefined ? symbols[currency] : `${currency} `;
  const formatted = Math.round(num).toLocaleString('en-US');
  return `${symbol}${formatted}`;
}

function format_money_exact(amount, currency = 'USD') {
  const num = Number(amount || 0);
  const symbols = {
    USD: '$',
    EUR: '€',
    GBP: '£',
    CHF: 'CHF ',
  };
  const symbol = symbols[currency] !== undefined ? symbols[currency] : `${currency} `;
  const formatted = num.toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
  return `${symbol}${formatted}`;
}

function url(pathStr = '') {
  const clean = '/' + String(pathStr || '').replace(/^\/+/, '');
  return clean === '/' ? '/' : clean.replace(/\/+$/, '');
}

function slugify(text) {
  const clean = String(text || '')
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9-]+/g, '-')
    .replace(/^-+|-+$/g, '');
  return clean !== '' ? clean : 'item-' + crypto.randomBytes(4).toString('hex');
}

function calculate_bid_increment(currentBid) {
  const bid = Number(currentBid || 0);
  if (bid < 1000) return 100.0;
  if (bid < 5000) return 250.0;
  if (bid < 10000) return 500.0;
  if (bid < 25000) return 1000.0;
  if (bid < 100000) return 2500.0;
  return 5000.0;
}

function minimum_next_bid(auction) {
  if (!auction) return 0;
  const bidCount = Number(auction.bid_count || 0);
  const startingBid = Number(auction.starting_bid || 0);
  const currentBid = Number(auction.current_bid || 0);

  if (bidCount === 0 || currentBid < startingBid) {
    return startingBid;
  }
  return currentBid + calculate_bid_increment(currentBid);
}

function parseDateTimestamp(dateStr) {
  if (!dateStr) return null;
  if (typeof dateStr === 'number') return dateStr;
  const normalized = String(dateStr).trim().replace(' ', 'T');
  const iso = normalized.endsWith('Z') || normalized.includes('+') ? normalized : `${normalized}Z`;
  const ms = Date.parse(iso);
  return Number.isNaN(ms) ? null : Math.floor(ms / 1000);
}

function strtotime(str, baseTimestampSec = null) {
  const nowSec = baseTimestampSec !== null ? baseTimestampSec : Math.floor(Date.now() / 1000);
  if (!str) return nowSec;
  const s = String(str).trim();
  if (s === 'now') return nowSec;

  const relMatch = s.match(/^([+-]?\d+)\s*(second|seconds|minute|minutes|hour|hours|day|days)$/i);
  if (relMatch) {
    const val = parseInt(relMatch[1], 10);
    const unit = relMatch[2].toLowerCase();
    const mult = unit.startsWith('day')
      ? 86400
      : unit.startsWith('hour')
      ? 3600
      : unit.startsWith('minute')
      ? 60
      : 1;
    return nowSec + val * mult;
  }

  return parseDateTimestamp(s) || nowSec;
}

function gmdate(format, timestampSec = null) {
  const sec = timestampSec !== null && timestampSec !== undefined
    ? Number(timestampSec)
    : Math.floor(Date.now() / 1000);
  const d = new Date(sec * 1000);
  const monthsShort = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  const monthsFull = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December',
  ];

  const pad = (n) => String(n).padStart(2, '0');
  const tokens = {
    Y: String(d.getUTCFullYear()),
    m: pad(d.getUTCMonth() + 1),
    d: pad(d.getUTCDate()),
    H: pad(d.getUTCHours()),
    i: pad(d.getUTCMinutes()),
    s: pad(d.getUTCSeconds()),
    M: monthsShort[d.getUTCMonth()],
    F: monthsFull[d.getUTCMonth()],
  };

  return String(format).replace(/Y|m|d|H|i|s|M|F/g, (match) => tokens[match] ?? match);
}

function time_remaining_label(endTime) {
  const now = Math.floor(Date.now() / 1000);
  const end = parseDateTimestamp(endTime);
  if (!end || end <= now) {
    return 'Auction Closed';
  }
  const diff = end - now;
  const days = Math.floor(diff / 86400);
  const hours = Math.floor((diff % 86400) / 3600);
  const minutes = Math.floor((diff % 3600) / 60);
  const seconds = diff % 60;

  if (days > 0) {
    return `${days}d ${hours}h remaining`;
  }
  if (hours > 0) {
    return `${hours}h ${minutes}m remaining`;
  }
  return `${minutes}m ${seconds}s remaining`;
}

function platform_setting(key, defaultVal = null) {
  const db = DatabaseEngine.getInstance();
  const settings = db.find('platform_settings', 1) || {};
  return settings[key] !== undefined && settings[key] !== null ? settings[key] : defaultVal;
}

function sprintf(format, ...args) {
  let i = 0;
  return String(format).replace(/%0?(\d*)([sd])/g, (match, width, type) => {
    const val = args[i++];
    if (type === 'd') {
      const numStr = String(Math.trunc(Number(val || 0)));
      return width ? numStr.padStart(Number(width), '0') : numStr;
    }
    return String(val ?? '');
  });
}

function http_build_query(obj) {
  const params = new URLSearchParams();
  for (const [k, v] of Object.entries(obj || {})) {
    if (v !== null && v !== undefined && v !== '') {
      params.set(k, String(v));
    }
  }
  return params.toString();
}

function isEmpty(val) {
  if (val === null || val === undefined || val === false || val === '' || val === 0 || val === '0') {
    return true;
  }
  if (Array.isArray(val)) return val.length === 0;
  if (typeof val === 'object') return Object.keys(val).length === 0;
  return false;
}

function userHasRole(user, role) {
  if (!user) return false;
  let roles = user.roles || [user.role || 'buyer'];
  if (typeof roles === 'string') roles = [roles];
  return roles.includes(role) || roles.includes('admin');
}

module.exports = {
  e,
  format_money,
  format_money_exact,
  url,
  slugify,
  calculate_bid_increment,
  minimum_next_bid,
  parseDateTimestamp,
  strtotime,
  gmdate,
  time_remaining_label,
  platform_setting,
  sprintf,
  http_build_query,
  isEmpty,
  userHasRole,
  formatUtcNow,
};
