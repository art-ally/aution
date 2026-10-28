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
  'wallet_withdrawals',
];

// Ordered so parent foreign-key tables are always upserted before child tables
const ORDERED_RELATIONAL_TABLES = [
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
  'wallet_withdrawals',
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
    this.data = { _meta: { sequences: {}, version: 1, updated_at_ms: 0 } };
    this.supabase = null;
    this.pgPool = null;
    this.pgSchemaInitialized = false;
    this.pgSchemaInitPromise = null;
    this.pgTableColumns = {};
    this.pgNetworkBackoffUntil = 0;
    this.supabaseEnabled = false;
    this.lastSupabaseSync = 0;
    this.initialSyncCompleted = false;

    this._inTransaction = false;
    this._diskDirty = false;
    this.hasPendingSync = false;
    this.pendingRelationalOps = [];
    this._flushTimer = null;
    this._flushInFlight = null;

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
          connectionTimeoutMillis: 5000,
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

  normalizeDataStructure(parsed) {
    if (!parsed || typeof parsed !== 'object') {
      parsed = { _meta: { sequences: {}, version: 1, updated_at_ms: 0 } };
    }
    if (!parsed._meta) parsed._meta = { sequences: {}, version: 1, updated_at_ms: 0 };
    if (!parsed._meta.sequences) parsed._meta.sequences = {};
    if (typeof parsed._meta.version !== 'number') parsed._meta.version = 1;
    if (typeof parsed._meta.updated_at_ms !== 'number') parsed._meta.updated_at_ms = 0;

    for (const t of TABLES) {
      if (!Array.isArray(parsed[t])) {
        parsed[t] = [];
      }
      const maxId = parsed[t].reduce((max, r) => Math.max(max, Number(r?.id || 0)), 0);
      if (!parsed._meta.sequences[t] || parsed._meta.sequences[t] < maxId) {
        parsed._meta.sequences[t] = maxId;
      }
    }
    return parsed;
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
            this.data = this.normalizeDataStructure(parsed);
            return;
          }
        }
      }
    } catch (err) {
      console.error('[ArtAlly DB] Failed loading store from disk:', err.message);
    }

    this.data = this.normalizeDataStructure({});
  }

  markMutated() {
    if (!this.data._meta) this.data._meta = { sequences: {}, version: 1, updated_at_ms: 0 };
    this.data._meta.version = Number(this.data._meta.version || 0) + 1;
    this.data._meta.updated_at_ms = Date.now();
    this._diskDirty = true;
    this.hasPendingSync = true;
  }

  writeDiskNow() {
    if (!this._diskDirty) return;
    try {
      const json = JSON.stringify(this.data, null, 2);
      fs.writeFileSync(RUNTIME_STORE_PATH, json, 'utf8');
      this._diskDirty = false;
    } catch (err) {
      console.error('[ArtAlly DB] Disk write warning:', err.message);
    }
  }

  saveToDisk() {
    this.markMutated();
    if (this._inTransaction) {
      return;
    }
    this.writeDiskNow();
    if (this.supabaseEnabled) {
      this.scheduleFlush();
    }
  }

  scheduleFlush() {
    if (this._flushTimer) return;
    this._flushTimer = setTimeout(() => {
      this._flushTimer = null;
      this.flush().catch(() => {});
    }, 25);
  }

  /**
   * Flushes all pending disk writes, atomic store_data snapshot, and ordered relational
   * table changes to Supabase PostgreSQL. Safe to await before HTTP redirects.
   */
  async flush() {
    if (this._flushTimer) {
      clearTimeout(this._flushTimer);
      this._flushTimer = null;
    }
    this.writeDiskNow();

    if (!this.supabaseEnabled) {
      this.hasPendingSync = false;
      this.pendingRelationalOps = [];
      return true;
    }

    if (this._flushInFlight) {
      await this._flushInFlight;
      if (!this.hasPendingSync && this.pendingRelationalOps.length === 0) {
        return true;
      }
    }

    const runFlush = async () => {
      this.writeDiskNow();
      const opsToRun = this.pendingRelationalOps.splice(0, this.pendingRelationalOps.length);
      this.hasPendingSync = false;

      // 1. Push the authoritative JSON store snapshot first (single fast query)
      const storeOk = await this.pushToSupabaseAsync();

      // 2. Execute relational table upserts/deletes in foreign-key dependency order
      if (opsToRun.length > 0 && this.pgPool && Date.now() >= this.pgNetworkBackoffUntil) {
        try {
          const schemaOk = await this.ensurePgSchema();
          if (schemaOk) {
            const tableOrderMap = {};
            ORDERED_RELATIONAL_TABLES.forEach((t, idx) => {
              tableOrderMap[t] = idx;
            });

            // Deduplicate operations by (op, table, id) and sort parent tables first
            const dedupedMap = new Map();
            for (const item of opsToRun) {
              const key = `${item.op}:${item.table}:${item.id}`;
              dedupedMap.set(key, item);
            }
            const sortedOps = Array.from(dedupedMap.values()).sort((a, b) => {
              if (a.op === 'delete' && b.op !== 'delete') return 1;
              if (a.op !== 'delete' && b.op === 'delete') return -1;
              const orderA = tableOrderMap[a.table] ?? 999;
              const orderB = tableOrderMap[b.table] ?? 999;
              if (a.op === 'delete' && b.op === 'delete') return orderB - orderA;
              return orderA - orderB;
            });

            for (const item of sortedOps) {
              if (item.op === 'upsert' && item.row) {
                await this.upsertRelationalRowToPg(item.table, item.row);
              } else if (item.op === 'delete' && item.id !== undefined) {
                await this.deleteRelationalRowFromPg(item.table, item.id);
              }
            }
          }
        } catch (_) {}
      }

      this.lastSupabaseSync = Date.now();
      return storeOk;
    };

    this._flushInFlight = runFlush().finally(() => {
      this._flushInFlight = null;
    });

    return this._flushInFlight;
  }

  async ensurePgSchema() {
    if (!this.pgPool) return false;
    if (this.pgSchemaInitialized) return true;
    if (Date.now() < this.pgNetworkBackoffUntil) return false;
    if (this.pgSchemaInitPromise) return this.pgSchemaInitPromise;

    this.pgSchemaInitPromise = (async () => {
      try {
        // Fast path: check if schema and columns already exist in public schema (1 single query)
        const colRes = await this.pgPool.query(`
          SELECT table_name, column_name, data_type
          FROM information_schema.columns
          WHERE table_schema = 'public'
        `);

        const cols = {};
        for (const row of colRes.rows) {
          if (!cols[row.table_name]) cols[row.table_name] = {};
          cols[row.table_name][row.column_name] = row.data_type;
        }

        const hasCoreTables =
          cols.artally_store &&
          cols.users &&
          cols.artist_profiles &&
          cols.artworks &&
          cols.artwork_submissions &&
          cols.auctions &&
          cols.authentication_invoices &&
          cols.authentication_invoices.crypto_wallet_address &&
          cols.authentication_invoices.payment_bank_address &&
          cols.wallet_withdrawals &&
          cols.artist_profiles.withdrawal_unlocked;

        if (hasCoreTables) {
          this.pgTableColumns = cols;
          this.pgSchemaInitialized = true;
          return true;
        }

        // Full migration path: only executed if tables or columns are missing
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
          ALTER TABLE public.artist_profiles ADD COLUMN IF NOT EXISTS wallet_balance NUMERIC(14,2) NOT NULL DEFAULT 0.00;
          ALTER TABLE public.artist_profiles ADD COLUMN IF NOT EXISTS manual_wallet_adjustment NUMERIC(14,2) NOT NULL DEFAULT 0.00;
          ALTER TABLE public.artist_profiles ADD COLUMN IF NOT EXISTS withdrawal_unlocked BOOLEAN NOT NULL DEFAULT false;
          ALTER TABLE public.notifications ADD COLUMN IF NOT EXISTS ends_at VARCHAR(80) NULL;
          ALTER TABLE public.authentication_invoices ADD COLUMN IF NOT EXISTS payment_method VARCHAR(40) NOT NULL DEFAULT 'bank_and_crypto';
          ALTER TABLE public.authentication_invoices ADD COLUMN IF NOT EXISTS payment_bank_address TEXT NULL;
          ALTER TABLE public.authentication_invoices ADD COLUMN IF NOT EXISTS crypto_currency VARCHAR(120) NULL;
          ALTER TABLE public.authentication_invoices ADD COLUMN IF NOT EXISTS crypto_network VARCHAR(120) NULL;
          ALTER TABLE public.authentication_invoices ADD COLUMN IF NOT EXISTS crypto_wallet_address VARCHAR(255) NULL;
          ALTER TABLE public.authentication_invoices ADD COLUMN IF NOT EXISTS crypto_memo VARCHAR(255) NULL;
          ALTER TABLE public.authentication_invoices ALTER COLUMN payment_account_name DROP NOT NULL;
          ALTER TABLE public.authentication_invoices ALTER COLUMN payment_bank_name DROP NOT NULL;
          ALTER TABLE public.authentication_invoices ALTER COLUMN payment_account_number DROP NOT NULL;
        `);

        const refreshedColRes = await this.pgPool.query(`
          SELECT table_name, column_name, data_type
          FROM information_schema.columns
          WHERE table_schema = 'public'
        `);
        this.pgTableColumns = {};
        for (const row of refreshedColRes.rows) {
          if (!this.pgTableColumns[row.table_name]) {
            this.pgTableColumns[row.table_name] = {};
          }
          this.pgTableColumns[row.table_name][row.column_name] = row.data_type;
        }

        this.pgSchemaInitialized = true;
        return true;
      } catch (err) {
        console.warn('[ArtAlly DB] Schema initialization warning:', err.message);
        this.pgNetworkBackoffUntil = Date.now() + 15000;
        return false;
      } finally {
        this.pgSchemaInitPromise = null;
      }
    })();

    return this.pgSchemaInitPromise;
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

  async _reconcileRelationalTablesFromPg(storeData) {
    if (!this.pgPool) return false;
    const coreTables = [
      'users',
      'artist_profiles',
      'artworks',
      'artwork_images',
      'artwork_submissions',
      'auctions',
      'authentication_invoices',
      'wallet_withdrawals',
      'seller_payouts',
      'notifications',
    ];
    let recoveredAny = false;
    try {
      const results = await Promise.all(
        coreTables.map((t) =>
          this.pgPool
            .query(`SELECT * FROM public."${t}" ORDER BY id ASC`)
            .then((r) => ({ table: t, rows: r.rows || [] }))
            .catch(() => ({ table: t, rows: [] }))
        )
      );

      for (const { table, rows } of results) {
        if (!Array.isArray(storeData[table])) storeData[table] = [];
        const existingIds = new Set(storeData[table].map((r) => Number(r.id)));
        for (const pgRow of rows) {
          const rowId = Number(pgRow.id);
          if (rowId > 0 && !existingIds.has(rowId)) {
            const normalizedRow = { ...pgRow, id: rowId };
            for (const [k, v] of Object.entries(normalizedRow)) {
              if (v instanceof Date) {
                normalizedRow[k] = v.toISOString().replace('T', ' ').slice(0, 19);
              }
            }
            if (table === 'users' && typeof normalizedRow.roles === 'string') {
              try {
                normalizedRow.roles = JSON.parse(normalizedRow.roles);
              } catch (_) {
                normalizedRow.roles = [normalizedRow.role || 'buyer'];
              }
            }
            storeData[table].push(normalizedRow);
            existingIds.add(rowId);
            recoveredAny = true;
          }
        }
        const maxId = storeData[table].reduce((m, r) => Math.max(m, Number(r?.id || 0)), 0);
        if (!storeData._meta.sequences[table] || storeData._meta.sequences[table] < maxId) {
          storeData._meta.sequences[table] = maxId;
        }
      }
    } catch (_) {}
    return recoveredAny;
  }

  async _pullStoreFromPg() {
    if (!this.pgPool || Date.now() < this.pgNetworkBackoffUntil) return false;
    if (this.hasPendingSync || this._flushInFlight) return true;

    const startVersion = Number(this.data._meta?.version || 0);

    try {
      let res;
      try {
        res = await this.pgPool.query(
          'SELECT store_data, updated_at FROM public.artally_store WHERE id = 1 LIMIT 1'
        );
      } catch (queryErr) {
        if (queryErr.code === '42P01') {
          await this.ensurePgSchema();
          res = await this.pgPool.query(
            'SELECT store_data, updated_at FROM public.artally_store WHERE id = 1 LIMIT 1'
          );
        } else {
          throw queryErr;
        }
      }

      // Never overwrite if local mutations occurred while the query was in flight
      if (
        this.hasPendingSync ||
        this._flushInFlight ||
        Number(this.data._meta?.version || 0) !== startVersion
      ) {
        return true;
      }

      if (
        res.rows.length > 0 &&
        res.rows[0].store_data &&
        Object.keys(res.rows[0].store_data).length > 0
      ) {
        const remoteData = this.normalizeDataStructure(res.rows[0].store_data);
        const localUpdatedMs = Number(this.data._meta?.updated_at_ms || 0);
        const remoteUpdatedMs = Number(remoteData._meta?.updated_at_ms || 0);

        // If local store has a newer timestamp than remote, never overwrite local state
        if (localUpdatedMs > 0 && localUpdatedMs > remoteUpdatedMs) {
          await this.pushToSupabaseAsync();
          this.initialSyncCompleted = true;
          return true;
        }

        // If local store is already at the exact same timestamp & version after initial sync, skip heavy reconciliation
        if (this.initialSyncCompleted && localUpdatedMs > 0 && localUpdatedMs === remoteUpdatedMs) {
          return true;
        }

        const recovered = await this._reconcileRelationalTablesFromPg(remoteData);

        if (
          this.hasPendingSync ||
          this._flushInFlight ||
          Number(this.data._meta?.version || 0) !== startVersion
        ) {
          return true;
        }

        this.data = remoteData;
        this.initialSyncCompleted = true;
        try {
          fs.writeFileSync(RUNTIME_STORE_PATH, JSON.stringify(this.data, null, 2), 'utf8');
        } catch (_) {}
        if (recovered) {
          await this.pushToSupabaseAsync();
        }
        return true;
      } else {
        await this.pushToSupabaseAsync();
        this.initialSyncCompleted = true;
        return true;
      }
    } catch (err) {
      this.pgNetworkBackoffUntil = Date.now() + 15000;
      return false;
    }
  }

  async syncFromSupabaseIfConfigured(force = false) {
    if (!this.supabaseEnabled) return false;

    // If we have local mutations waiting to be pushed, flush them first so they are never lost!
    if (this.hasPendingSync || this._flushInFlight) {
      if (IS_VERCEL || force) {
        await this.flush();
      } else {
        this.flush().catch(() => {});
      }
      return true;
    }

    const now = Date.now();
    const syncIntervalMs = IS_VERCEL ? 2500 : 12000;
    if (!force && this.initialSyncCompleted && now - this.lastSupabaseSync < syncIntervalMs) {
      return true;
    }
    this.lastSupabaseSync = now;

    // On local server, refresh in the background so page navigations and button clicks are instant
    if (!IS_VERCEL && !force) {
      this.initialSyncCompleted = true;
      this._pullStoreFromPg().catch(() => {});
      return true;
    }

    const ok = await this._pullStoreFromPg();
    if (ok) return true;

    if (this.supabase) {
      try {
        const { data, error } = await this.supabase
          .from('artally_store')
          .select('store_data, updated_at')
          .eq('id', 1)
          .maybeSingle();

        if (!error && data && data.store_data && Object.keys(data.store_data).length > 0) {
          if (!this.hasPendingSync && !this._flushInFlight) {
            this.data = this.normalizeDataStructure(data.store_data);
            this.initialSyncCompleted = true;
            try {
              fs.writeFileSync(RUNTIME_STORE_PATH, JSON.stringify(this.data, null, 2), 'utf8');
            } catch (_) {}
          }
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
        const payload = JSON.stringify(this.data);
        try {
          await this.pgPool.query(
            `INSERT INTO public.artally_store (id, store_data, updated_at)
             VALUES (1, $1::jsonb, NOW())
             ON CONFLICT (id) DO UPDATE
             SET store_data = EXCLUDED.store_data, updated_at = NOW()`,
            [payload]
          );
        } catch (queryErr) {
          if (queryErr.code === '42P01') {
            await this.ensurePgSchema();
            await this.pgPool.query(
              `INSERT INTO public.artally_store (id, store_data, updated_at)
               VALUES (1, $1::jsonb, NOW())
               ON CONFLICT (id) DO UPDATE
               SET store_data = EXCLUDED.store_data, updated_at = NOW()`,
              [payload]
            );
          } else {
            throw queryErr;
          }
        }
        return true;
      } catch (err) {
        this.pgNetworkBackoffUntil = Date.now() + 15000;
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

    const tableSummary = {};
    if (schemaOk && this.pgPool) {
      for (const t of ORDERED_RELATIONAL_TABLES) {
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
    if (this._flushTimer) {
      clearTimeout(this._flushTimer);
      this._flushTimer = null;
    }
    this.pendingRelationalOps = [];
    this.hasPendingSync = false;

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

    const nextVersion = Number(this.data._meta?.version || 0) + 1;
    this.data = {
      _meta: {
        sequences: {},
        version: nextVersion,
        updated_at_ms: Date.now(),
      },
    };

    for (const t of TABLES) {
      this.data[t] = [];
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
      const json = JSON.stringify(this.data, null, 2);
      fs.writeFileSync(RUNTIME_STORE_PATH, json, 'utf8');
      if (RUNTIME_STORE_PATH !== SEED_STORE_PATH) {
        fs.writeFileSync(SEED_STORE_PATH, json, 'utf8');
      }
      this._diskDirty = false;
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
    this.lastSupabaseSync = Date.now();
    return cleanAdmin;
  }

  transaction(callback) {
    const snapshot = JSON.parse(JSON.stringify(this.data));
    const prevOpsLen = this.pendingRelationalOps.length;
    const wasInTx = this._inTransaction;
    this._inTransaction = true;
    try {
      const result = callback(this);
      this._inTransaction = wasInTx;
      if (!wasInTx) {
        this.saveToDisk();
      }
      return result;
    } catch (err) {
      this._inTransaction = wasInTx;
      this.data = snapshot;
      this.pendingRelationalOps.length = prevOpsLen;
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
   * Queries the user directly from the local store first, or from Supabase PostgreSQL (`public.users`)
   * case-insensitively by email when not yet in local cache.
   */
  async findUserByEmailFromDb(email) {
    const cleanEmail = String(email || '').trim();
    if (!cleanEmail) return null;

    // Check in-memory store first (already synced)
    const localMatch = this.findBy('users', 'email', cleanEmail);
    if (localMatch) {
      return localMatch;
    }

    if (this.pgPool && Date.now() >= this.pgNetworkBackoffUntil) {
      try {
        await this.ensurePgSchema();
        const res = await this.pgPool.query(
          'SELECT * FROM public.users WHERE LOWER(email) = LOWER($1) LIMIT 1',
          [cleanEmail]
        );
        if (res.rows && res.rows.length > 0) {
          const dbUser = res.rows[0];
          if (!Array.isArray(this.data.users)) this.data.users = [];
          const existingIdx = this.data.users.findIndex((u) => Number(u.id) === Number(dbUser.id));
          if (existingIdx >= 0) {
            this.data.users[existingIdx] = { ...this.data.users[existingIdx], ...dbUser };
            return this.data.users[existingIdx];
          } else {
            this.data.users.push(dbUser);
            const maxUserId = this.data.users.reduce((m, u) => Math.max(m, Number(u.id || 0)), 0);
            if (!this.data._meta.sequences.users || this.data._meta.sequences.users < maxUserId) {
              this.data._meta.sequences.users = maxUserId;
            }
            this.saveToDisk();
            return dbUser;
          }
        }
      } catch (_) {}
    }

    return null;
  }

  where(table, predicate) {
    const rows = this.data[table] || [];
    return rows.filter((row) => predicate(row));
  }

  insert(table, record) {
    if (!Array.isArray(this.data[table])) {
      this.data[table] = [];
    }
    if (!this.data._meta) this.data._meta = { sequences: {}, version: 1, updated_at_ms: 0 };
    if (!this.data._meta.sequences) this.data._meta.sequences = {};

    const maxExistingId = this.data[table].reduce((max, r) => Math.max(max, Number(r?.id || 0)), 0);
    let seq = Math.max(Number(this.data._meta.sequences[table] || 0), maxExistingId) + 1;
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
    if (this.pgPool) {
      this.pendingRelationalOps.push({ op: 'upsert', table, id: copy.id, row: copy });
    }
    this.saveToDisk();
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
        if (this.pgPool) {
          this.pendingRelationalOps.push({ op: 'upsert', table, id: updated.id, row: updated });
        }
        this.saveToDisk();
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
        if (this.pgPool) {
          this.pendingRelationalOps.push({ op: 'delete', table, id: row.id });
        }
        this.saveToDisk();
        return true;
      }
    }
    return false;
  }
}

module.exports = { DatabaseEngine, formatUtcNow };
