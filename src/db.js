const fs = require('fs');
const path = require('path');
const bcrypt = require('bcryptjs');
const { Pool } = require('pg');
const { createClient } = require('@supabase/supabase-js');

const SEED_STORE_PATH = path.join(__dirname, '..', 'storage', 'data', 'atelier_noir_store.json');
const SCHEMA_SQL_PATH = path.join(__dirname, '..', 'database', 'supabase_schema.sql');
const IS_VERCEL = Boolean(process.env.VERCEL);
const RUNTIME_STORE_PATH = IS_VERCEL
  ? path.join('/tmp', 'artally_store.json')
  : SEED_STORE_PATH;

const DEFAULT_SUPABASE_PG_URL =
  'postgresql://postgres.xrncirrbxzzouwbluyso:Ohreally.12345@aws-1-eu-central-1.pooler.supabase.com:5432/postgres';

const TABLES = [
  'users',
  'roles',
  'artist_profiles',
  'buyer_profiles',
  'categories',
  'artworks',
  'artwork_images',
  'artwork_documents',
  'artwork_submissions',
  'auctions',
  'bids',
  'watchlists',
  'followed_artists',
  'orders',
  'order_items',
  'payments',
  'commissions',
  'seller_payouts',
  'notifications',
  'email_verifications',
  'password_reset_tokens',
  'newsletter_subscribers',
  'editorial_articles',
  'reports',
  'audit_logs',
  'platform_settings',
  'authentication_invoices',
];

function normalizeSupabasePgUrl(rawUrl) {
  const s = String(rawUrl || '').trim();
  if (!s) return DEFAULT_SUPABASE_PG_URL;
  // Convert IPv6-only db.xrncirrbxzzouwbluyso.supabase.co to IPv4+IPv6 Supavisor Pooler
  if (s.includes('@db.xrncirrbxzzouwbluyso.supabase.co')) {
    return s
      .replace('postgresql://postgres:', 'postgresql://postgres.xrncirrbxzzouwbluyso:')
      .replace('@db.xrncirrbxzzouwbluyso.supabase.co', '@aws-1-eu-central-1.pooler.supabase.com');
  }
  return s;
}

function formatUtcNow() {
  return new Date().toISOString().replace('T', ' ').substring(0, 19);
}

class DatabaseEngine {
  constructor() {
    this.data = { _meta: { sequences: {} } };
    this.supabase = null;
    this.pgPool = null;
    this.pgSchemaInitialized = false;
    this.pgTableColumns = {};
    this.pgNetworkBackoffUntil = 0;
    this.supabaseEnabled = false;
    this.lastSupabaseSync = 0;

    const pgConnectionString = normalizeSupabasePgUrl(
      process.env.DATABASE_URL || process.env.SUPABASE_DB_URL || DEFAULT_SUPABASE_PG_URL
    );

    if (pgConnectionString && pgConnectionString.startsWith('postgres')) {
      try {
        this.pgPool = new Pool({
          connectionString: pgConnectionString,
          ssl: { rejectUnauthorized: false },
          max: 5,
          idleTimeoutMillis: 15000,
          connectionTimeoutMillis: 8000,
        });
        this.pgPool.on('error', (err) => {
          console.warn('[ArtAlly DB] PostgreSQL background pool notice:', err.message);
        });
        this.supabaseEnabled = true;
      } catch (err) {
        console.warn('[ArtAlly DB] PostgreSQL pool init warning:', err.message);
      }
    }

    const supabaseUrl = (process.env.SUPABASE_URL || 'https://xrncirrbxzzouwbluyso.supabase.co').trim();
    const supabaseKey = (
      process.env.SUPABASE_SERVICE_ROLE_KEY ||
      process.env.SUPABASE_ANON_KEY ||
      process.env.SUPABASE_KEY ||
      ''
    ).trim();

    if (
      supabaseUrl &&
      supabaseKey &&
      supabaseUrl.startsWith('http') &&
      !supabaseUrl.includes('your-project-id')
    ) {
      try {
        this.supabase = createClient(supabaseUrl, supabaseKey, {
          auth: { persistSession: false },
        });
        this.supabaseEnabled = true;
      } catch (err) {
        console.warn('[ArtAlly DB] Supabase REST client init warning:', err.message);
      }
    }

    this.loadFromDisk();
  }

  static getInstance() {
    if (!DatabaseEngine.instance) {
      DatabaseEngine.instance = new DatabaseEngine();
    }
    return DatabaseEngine.instance;
  }

  loadFromDisk() {
    try {
      if (IS_VERCEL && !fs.existsSync(RUNTIME_STORE_PATH) && fs.existsSync(SEED_STORE_PATH)) {
        fs.copyFileSync(SEED_STORE_PATH, RUNTIME_STORE_PATH);
      }
      const target = fs.existsSync(RUNTIME_STORE_PATH) ? RUNTIME_STORE_PATH : SEED_STORE_PATH;
      if (fs.existsSync(target)) {
        const raw = fs.readFileSync(target, 'utf8');
        if (raw) {
          const parsed = JSON.parse(raw);
          if (parsed && typeof parsed === 'object') {
            this.data = parsed;
            if (!this.data._meta) this.data._meta = { sequences: {} };
            if (!this.data._meta.sequences) this.data._meta.sequences = {};
            for (const t of TABLES) {
              if (!Array.isArray(this.data[t])) this.data[t] = [];
            }
            return;
          }
        }
      }
    } catch (err) {
      console.error('[ArtAlly DB] Failed loading store from disk:', err.message);
    }

    this.data = { _meta: { sequences: {} } };
    for (const t of TABLES) {
      this.data[t] = [];
      this.data._meta.sequences[t] = 0;
    }
  }

  saveToDisk() {
    try {
      const json = JSON.stringify(this.data, null, 4);
      fs.writeFileSync(RUNTIME_STORE_PATH, json, 'utf8');
    } catch (err) {
      console.error('[ArtAlly DB] Disk write warning:', err.message);
    }
    if (this.supabaseEnabled) {
      this.pushToSupabaseAsync();
    }
  }

  async ensurePgSchema() {
    if (!this.pgPool || this.pgSchemaInitialized) return true;
    if (Date.now() < this.pgNetworkBackoffUntil) return false;
    try {
      await this.pgPool.query(`
        CREATE TABLE IF NOT EXISTS public.artally_store (
          id BIGINT PRIMARY KEY DEFAULT 1,
          store_data JSONB NOT NULL DEFAULT '{}'::jsonb,
          updated_at TIMESTAMPTZ DEFAULT NOW()
        );
      `);
      if (fs.existsSync(SCHEMA_SQL_PATH)) {
        const sql = fs.readFileSync(SCHEMA_SQL_PATH, 'utf8');
        await this.pgPool.query(sql);
      }
      await this.pgPool.query(`
        ALTER TABLE public.artist_profiles ADD COLUMN IF NOT EXISTS is_seeded BOOLEAN NOT NULL DEFAULT false;
        ALTER TABLE public.notifications ADD COLUMN IF NOT EXISTS ends_at VARCHAR(80) NULL;
        ALTER TABLE public.authentication_invoices ADD COLUMN IF NOT EXISTS payment_method VARCHAR(40) NOT NULL DEFAULT 'bank_and_crypto';
        ALTER TABLE public.authentication_invoices ADD COLUMN IF NOT EXISTS crypto_currency VARCHAR(120) NULL;
        ALTER TABLE public.authentication_invoices ADD COLUMN IF NOT EXISTS crypto_network VARCHAR(120) NULL;
        ALTER TABLE public.authentication_invoices ADD COLUMN IF NOT EXISTS crypto_wallet_address VARCHAR(255) NULL;
        ALTER TABLE public.authentication_invoices ADD COLUMN IF NOT EXISTS crypto_memo VARCHAR(255) NULL;
        ALTER TABLE public.authentication_invoices ALTER COLUMN payment_account_name DROP NOT NULL;
        ALTER TABLE public.authentication_invoices ALTER COLUMN payment_bank_name DROP NOT NULL;
        ALTER TABLE public.authentication_invoices ALTER COLUMN payment_account_number DROP NOT NULL;
      `);

      // Cache column metadata for all public tables so relational upserts match exact columns
      const colRes = await this.pgPool.query(`
        SELECT table_name, column_name, data_type
        FROM information_schema.columns
        WHERE table_schema = 'public'
      `);
      this.pgTableColumns = {};
      for (const row of colRes.rows) {
        if (!this.pgTableColumns[row.table_name]) {
          this.pgTableColumns[row.table_name] = {};
        }
        this.pgTableColumns[row.table_name][row.column_name] = row.data_type;
      }

      this.pgSchemaInitialized = true;
      return true;
    } catch (err) {
      console.warn('[ArtAlly DB] Schema initialization warning:', err.message);
      this.pgNetworkBackoffUntil = Date.now() + 30000;
      return false;
    }
  }

  async upsertRelationalRowToPg(table, row) {
    if (!this.pgPool || !row || typeof row !== 'object') return false;
    const ok = await this.ensurePgSchema();
    if (!ok) return false;
    const colsMeta = this.pgTableColumns[table];
    if (!colsMeta) return false;

    const validCols = Object.keys(row).filter((k) => colsMeta[k] !== undefined);
    if (validCols.length === 0 || !validCols.includes('id')) return false;

    const colNames = validCols.map((c) => `"${c}"`).join(', ');
    const placeholders = validCols.map((_, idx) => `$${idx + 1}`).join(', ');
    const updateSet = validCols
      .filter((c) => c !== 'id')
      .map((c) => `"${c}" = EXCLUDED."${c}"`)
      .join(', ');

    const values = validCols.map((c) => {
      const val = row[c];
      if (val === undefined) return null;
      if (colsMeta[c] === 'jsonb' && val !== null && typeof val === 'object') {
        return JSON.stringify(val);
      }
      return val;
    });

    const sql = updateSet
      ? `INSERT INTO public."${table}" (${colNames}) OVERRIDING SYSTEM VALUE VALUES (${placeholders}) ON CONFLICT (id) DO UPDATE SET ${updateSet}`
      : `INSERT INTO public."${table}" (${colNames}) OVERRIDING SYSTEM VALUE VALUES (${placeholders}) ON CONFLICT (id) DO NOTHING`;

    try {
      await this.pgPool.query(sql, values);
      return true;
    } catch (err) {
      return false;
    }
  }

  async deleteRelationalRowFromPg(table, id) {
    if (!this.pgPool) return false;
    const ok = await this.ensurePgSchema();
    if (!ok) return false;
    if (!this.pgTableColumns[table]) return false;
    try {
      await this.pgPool.query(`DELETE FROM public."${table}" WHERE id = $1`, [Number(id)]);
      return true;
    } catch (_) {
      return false;
    }
  }

  async syncFromSupabaseIfConfigured(force = false) {
    if (!this.supabaseEnabled) return false;
    const now = Date.now();
    if (!force && now - this.lastSupabaseSync < 5000) {
      return true;
    }
    this.lastSupabaseSync = now;

    if (this.pgPool && Date.now() >= this.pgNetworkBackoffUntil) {
      try {
        const schemaOk = await this.ensurePgSchema();
        if (schemaOk) {
          const res = await this.pgPool.query(
            'SELECT store_data, updated_at FROM public.artally_store WHERE id = 1 LIMIT 1'
          );
          if (res.rows.length > 0 && res.rows[0].store_data && Object.keys(res.rows[0].store_data).length > 0) {
            this.data = res.rows[0].store_data;
            if (!this.data._meta) this.data._meta = { sequences: {} };
            if (!this.data._meta.sequences) this.data._meta.sequences = {};
            for (const t of TABLES) {
              if (!Array.isArray(this.data[t])) this.data[t] = [];
            }
            try {
              fs.writeFileSync(RUNTIME_STORE_PATH, JSON.stringify(this.data, null, 4), 'utf8');
            } catch (_) {}
            return true;
          } else {
            await this.pushToSupabaseAsync();
            return true;
          }
        }
      } catch (err) {
        this.pgNetworkBackoffUntil = Date.now() + 30000;
      }
    }

    if (this.supabase) {
      try {
        const { data, error } = await this.supabase
          .from('artally_store')
          .select('store_data, updated_at')
          .eq('id', 1)
          .maybeSingle();

        if (!error && data && data.store_data && Object.keys(data.store_data).length > 0) {
          this.data = data.store_data;
          try {
            fs.writeFileSync(RUNTIME_STORE_PATH, JSON.stringify(this.data, null, 4), 'utf8');
          } catch (_) {}
          return true;
        }
      } catch (_) {}
    }

    return false;
  }

  async pushToSupabaseAsync() {
    if (!this.supabaseEnabled) return false;

    if (this.pgPool && Date.now() >= this.pgNetworkBackoffUntil) {
      try {
        const schemaOk = await this.ensurePgSchema();
        if (schemaOk) {
          await this.pgPool.query(
            `INSERT INTO public.artally_store (id, store_data, updated_at)
             VALUES (1, $1::jsonb, NOW())
             ON CONFLICT (id) DO UPDATE
             SET store_data = EXCLUDED.store_data, updated_at = NOW()`,
            [JSON.stringify(this.data)]
          );
          return true;
        }
      } catch (err) {
        this.pgNetworkBackoffUntil = Date.now() + 30000;
      }
    }

    if (this.supabase) {
      try {
        const { error } = await this.supabase.from('artally_store').upsert(
          {
            id: 1,
            store_data: this.data,
            updated_at: new Date().toISOString(),
          },
          { onConflict: 'id' }
        );
        return !error;
      } catch (_) {}
    }

    return false;
  }

  async pushRelationalTablesToSupabase() {
    if (!this.supabaseEnabled) {
      throw new Error('Supabase connection is not configured.');
    }
    const schemaOk = await this.ensurePgSchema();
    const storeOk = await this.pushToSupabaseAsync();

    // Order tables so parent foreign keys (users, categories) are inserted before child tables
    const orderedTables = [
      'platform_settings',
      'roles',
      'categories',
      'users',
      'artist_profiles',
      'buyer_profiles',
      'artworks',
      'artwork_images',
      'artwork_documents',
      'artwork_submissions',
      'auctions',
      'bids',
      'watchlists',
      'followed_artists',
      'orders',
      'order_items',
      'payments',
      'commissions',
      'seller_payouts',
      'notifications',
      'email_verifications',
      'password_reset_tokens',
      'newsletter_subscribers',
      'editorial_articles',
      'reports',
      'audit_logs',
      'authentication_invoices',
    ];

    const tableSummary = {};
    if (schemaOk && this.pgPool) {
      for (const t of orderedTables) {
        const rows = Array.isArray(this.data[t]) ? this.data[t] : [];
        let syncedCount = 0;
        for (const row of rows) {
          const ok = await this.upsertRelationalRowToPg(t, row);
          if (ok) syncedCount++;
        }
        tableSummary[t] = `${syncedCount}/${rows.length} rows synced`;
      }
    }

    return {
      artally_store: storeOk ? 'synced to Supabase PostgreSQL' : 'warning: push failed',
      schema_created: schemaOk,
      tables: tableSummary,
    };
  }

  /**
   * Clears ALL backend and demo/seeded data for fresh new entries.
   * Preserves only the active Administrator account (without demo flags),
   * default categories, roles, and platform settings.
   */
  async clearAllBackendAndDemoData(preserveAdminUser = null) {
    const existingUsers = Array.isArray(this.data.users) ? this.data.users : [];
    const existingAdmins = existingUsers.filter(
      (u) =>
        (u.role || '') === 'admin' ||
        (Array.isArray(u.roles) && u.roles.includes('admin'))
    );

    const defaultAdminPasswordHash = bcrypt.hashSync('Ohreally.123', 10);

    const adminToKeep =
      preserveAdminUser ||
      existingAdmins[0] || {
        id: 1,
        name: 'Chief Administrator',
        email: 'S-cakar@arcor.de',
        password: defaultAdminPasswordHash,
        country: 'Switzerland',
        role: 'admin',
        roles: ['admin', 'buyer', 'artist'],
        email_verified_at: formatUtcNow(),
        bidding_approved: true,
        status: 'active',
        is_seeded: false,
        created_at: formatUtcNow(),
        updated_at: formatUtcNow(),
      };

    const isLegacyDemoAdmin =
      String(adminToKeep.email || '').trim().toLowerCase() === 'admin@ateliernoir.art';

    const cleanAdmin = {
      ...adminToKeep,
      id: Number(adminToKeep.id || 1),
      name: isLegacyDemoAdmin ? 'Chief Administrator' : adminToKeep.name || 'Chief Administrator',
      email: isLegacyDemoAdmin ? 'S-cakar@arcor.de' : adminToKeep.email || 'S-cakar@arcor.de',
      password: isLegacyDemoAdmin ? defaultAdminPasswordHash : adminToKeep.password || defaultAdminPasswordHash,
      role: 'admin',
      roles: ['admin', 'buyer', 'artist'],
      is_seeded: false,
      updated_at: formatUtcNow(),
    };

    const rolesToKeep = Array.isArray(this.data.roles) ? this.data.roles : [];
    const categoriesToKeep = Array.isArray(this.data.categories) ? this.data.categories : [];
    const settingsToKeep =
      Array.isArray(this.data.platform_settings) && this.data.platform_settings.length > 0
        ? [
            {
              ...this.data.platform_settings[0],
              id: 1,
              brand_name: 'ArtAlly',
              contact_email: 'Art-Ally@consultant.com',
              updated_at: formatUtcNow(),
            },
          ]
        : [
            {
              id: 1,
              brand_name: 'ArtAlly',
              commission_rate_percent: 15.0,
              buyer_premium_percent: 20.0,
              anti_sniping_enabled: true,
              buyer_verification_code: 'NOIR-BUYER-2026',
              contact_email: 'Art-Ally@consultant.com',
              updated_at: formatUtcNow(),
            },
          ];

    for (const t of TABLES) {
      this.data[t] = [];
      if (!this.data._meta) this.data._meta = { sequences: {} };
      if (!this.data._meta.sequences) this.data._meta.sequences = {};
      this.data._meta.sequences[t] = 0;
    }

    this.data.users = [cleanAdmin];
    this.data._meta.sequences.users = Number(cleanAdmin.id || 1);

    this.data.roles = rolesToKeep;
    this.data._meta.sequences.roles = rolesToKeep.length;

    this.data.categories = categoriesToKeep;
    this.data._meta.sequences.categories = categoriesToKeep.reduce(
      (max, c) => Math.max(max, Number(c.id || 0)),
      0
    );

    this.data.platform_settings = settingsToKeep;
    this.data._meta.sequences.platform_settings = 1;

    try {
      const json = JSON.stringify(this.data, null, 4);
      fs.writeFileSync(RUNTIME_STORE_PATH, json, 'utf8');
      if (RUNTIME_STORE_PATH !== SEED_STORE_PATH) {
        fs.writeFileSync(SEED_STORE_PATH, json, 'utf8');
      }
    } catch (err) {
      console.error('[ArtAlly DB] Clear disk write warning:', err.message);
    }

    if (this.pgPool) {
      try {
        await this.ensurePgSchema();
        // Delete child tables first so foreign keys are respected
        const dataTablesToClear = [
          'authentication_invoices',
          'audit_logs',
          'reports',
          'editorial_articles',
          'newsletter_subscribers',
          'password_reset_tokens',
          'email_verifications',
          'notifications',
          'seller_payouts',
          'commissions',
          'payments',
          'order_items',
          'orders',
          'followed_artists',
          'watchlists',
          'bids',
          'auctions',
          'artwork_submissions',
          'artwork_documents',
          'artwork_images',
          'artworks',
          'buyer_profiles',
          'artist_profiles',
          'users',
        ];
        for (const table of dataTablesToClear) {
          try {
            await this.pgPool.query(`DELETE FROM public."${table}"`);
          } catch (_) {}
        }
        await this.upsertRelationalRowToPg('users', cleanAdmin);
      } catch (_) {}
    }

    await this.pushToSupabaseAsync();
    return cleanAdmin;
  }

  transaction(callback) {
    const snapshot = JSON.parse(JSON.stringify(this.data));
    try {
      const result = callback(this);
      this.saveToDisk();
      return result;
    } catch (err) {
      this.data = snapshot;
      throw err;
    }
  }

  all(table) {
    return Object.values(this.data[table] || []);
  }

  find(table, id) {
    const rows = this.data[table] || [];
    for (const row of rows) {
      if (String(row.id ?? '') === String(id)) {
        return row;
      }
    }
    return null;
  }

  findBy(table, field, value) {
    const rows = this.data[table] || [];
    const isEmailField = field === 'email' && typeof value === 'string';
    const targetLower = isEmailField ? value.trim().toLowerCase() : null;
    for (const row of rows) {
      if ((row[field] ?? null) === value) {
        return row;
      }
      if (isEmailField && typeof row[field] === 'string' && row[field].trim().toLowerCase() === targetLower) {
        return row;
      }
    }
    return null;
  }

  /**
   * Queries the user directly from the Supabase PostgreSQL database (`public.users`)
   * case-insensitively by email when connected, falling back to the local store if offline.
   */
  async findUserByEmailFromDb(email) {
    const cleanEmail = String(email || '').trim();
    if (!cleanEmail) return null;

    if (this.pgPool && Date.now() >= this.pgNetworkBackoffUntil) {
      try {
        await this.ensurePgSchema();
        const res = await this.pgPool.query(
          'SELECT * FROM public.users WHERE LOWER(email) = LOWER($1) LIMIT 1',
          [cleanEmail]
        );
        if (res.rows && res.rows.length > 0) {
          const dbUser = res.rows[0];
          // Ensure local cache stays synchronized with the database row
          if (!Array.isArray(this.data.users)) this.data.users = [];
          const existingIdx = this.data.users.findIndex((u) => Number(u.id) === Number(dbUser.id));
          if (existingIdx >= 0) {
            this.data.users[existingIdx] = { ...this.data.users[existingIdx], ...dbUser };
            return this.data.users[existingIdx];
          } else {
            this.data.users.push(dbUser);
            return dbUser;
          }
        }
      } catch (_) {}
    }

    return this.findBy('users', 'email', cleanEmail);
  }

  where(table, predicate) {
    const rows = this.data[table] || [];
    return rows.filter((row) => predicate(row));
  }

  insert(table, record) {
    if (!Array.isArray(this.data[table])) {
      this.data[table] = [];
    }
    if (!this.data._meta) this.data._meta = { sequences: {} };
    if (!this.data._meta.sequences) this.data._meta.sequences = {};

    let seq = Number(this.data._meta.sequences[table] || 0) + 1;
    const copy = { ...record };
    if (copy.id !== undefined && copy.id !== null) {
      const idNum = Number(copy.id);
      if (idNum >= seq) {
        seq = idNum;
      }
    } else {
      copy.id = seq;
    }
    this.data._meta.sequences[table] = seq;

    const now = formatUtcNow();
    copy.created_at = copy.created_at || now;
    copy.updated_at = copy.updated_at || now;

    this.data[table].push(copy);
    this.saveToDisk();
    if (this.pgPool) {
      this.upsertRelationalRowToPg(table, copy).catch(() => {});
    }
    return copy;
  }

  update(table, id, attributes) {
    if (!Array.isArray(this.data[table])) {
      return null;
    }
    for (let idx = 0; idx < this.data[table].length; idx++) {
      const row = this.data[table][idx];
      if (String(row.id ?? '') === String(id)) {
        const updated = {
          ...row,
          ...attributes,
          id: row.id,
          updated_at: formatUtcNow(),
        };
        this.data[table][idx] = updated;
        this.saveToDisk();
        if (this.pgPool) {
          this.upsertRelationalRowToPg(table, updated).catch(() => {});
        }
        return updated;
      }
    }
    return null;
  }

  delete(table, id) {
    if (!Array.isArray(this.data[table])) {
      return false;
    }
    for (let idx = 0; idx < this.data[table].length; idx++) {
      const row = this.data[table][idx];
      if (String(row.id ?? '') === String(id)) {
        this.data[table].splice(idx, 1);
        this.saveToDisk();
        if (this.pgPool) {
          this.deleteRelationalRowFromPg(table, id).catch(() => {});
        }
        return true;
      }
    }
    return false;
  }
}

module.exports = { DatabaseEngine, formatUtcNow };
