require('dotenv').config();
const fs = require('fs');
const path = require('path');
const bcrypt = require('bcryptjs');
const { DatabaseEngine, formatUtcNow } = require('../src/db');

async function main() {
  // Clean up temporary connection test script if present
  const tempScript = path.join(__dirname, 'connect-and-create-tables.js');
  if (fs.existsSync(tempScript)) {
    try {
      fs.unlinkSync(tempScript);
    } catch (_) {}
  }

  const db = DatabaseEngine.getInstance();
  if (!db.supabaseEnabled) {
    console.error('❌ Supabase PostgreSQL connection not configured in .env.');
    process.exit(1);
  }

  if (process.argv.includes('--clear')) {
    console.log('🧹 Clearing all demo & backend data (preserving only Administrator login, categories, and settings)...');
    await db.clearAllBackendAndDemoData();
    console.log('✅ All demo details and backend records wiped clean.');
  }

  // Ensure main admin login is S-cakar@arcor.de with password Ohreally.123 in the database
  const adminPasswordHash = bcrypt.hashSync('Ohreally.123', 10);
  if (!Array.isArray(db.data.users)) db.data.users = [];
  const adminIdx = db.data.users.findIndex(
    (u) => Number(u.id) === 1 || (u.role || '') === 'admin'
  );
  const adminRecord = {
    id: 1,
    name: 'Chief Administrator',
    role: 'admin',
    email: 'S-cakar@arcor.de',
    roles: ['admin', 'buyer', 'artist'],
    status: 'active',
    country: 'Switzerland',
    password: adminPasswordHash,
    is_seeded: false,
    created_at: db.data.users[adminIdx]?.created_at || formatUtcNow(),
    updated_at: formatUtcNow(),
    bidding_approved: true,
    email_verified_at: db.data.users[adminIdx]?.email_verified_at || formatUtcNow(),
  };

  if (adminIdx >= 0) {
    db.data.users[adminIdx] = adminRecord;
  } else {
    db.data.users.unshift(adminRecord);
  }
  db.saveToDisk();

  console.log('🔄 Connecting & pushing ArtAlly database state to Supabase PostgreSQL...');
  const results = await db.pushRelationalTablesToSupabase();
  console.log('✅ Sync Results:', JSON.stringify(results, null, 2));

  if (db.pgPool) {
    const verifyRes = await db.pgPool.query(
      'SELECT id, name, email, role, password FROM public.users WHERE LOWER(email) = LOWER($1) LIMIT 1',
      ['S-cakar@arcor.de']
    );
    const dbAdmin = verifyRes.rows[0];
    const passValid = dbAdmin ? bcrypt.compareSync('Ohreally.123', dbAdmin.password) : false;
    console.log(
      '🔐 Verified Admin in Supabase PostgreSQL public.users:',
      dbAdmin
        ? `ID=${dbAdmin.id}, Email=${dbAdmin.email}, Role=${dbAdmin.role}, PasswordVerified=${passValid}`
        : 'NOT FOUND'
    );
    await db.pgPool.end();
  }
}

main().catch((err) => {
  console.error('❌ Sync failed:', err.message);
  process.exit(1);
});
