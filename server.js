require('dotenv').config();
const app = require('./src/app');
const { DatabaseEngine } = require('./src/db');
const { AuctionEngineService } = require('./src/auctionEngine');

const PORT = Number(process.env.PORT || 8000);
const HOST = process.env.HOST || '0.0.0.0';

// Automatically close auctions as soon as their countdown completes (only after initial DB sync)
setInterval(() => {
  try {
    const db = DatabaseEngine.getInstance();
    if (db.supabaseEnabled && !db.initialSyncCompleted) return;
    AuctionEngineService.synchronizeAuctions();
  } catch (_) {}
}, 5000);

app.listen(PORT, HOST, async () => {
  const db = DatabaseEngine.getInstance();
  if (db.supabaseEnabled) {
    try {
      await db.syncFromSupabaseIfConfigured(true);
    } catch (_) {}
  }
  console.log('===============================================================');
  console.log('  ArtAlly — Node.js Fine Art Auction Platform');
  console.log(`  Local URL:     http://localhost:${PORT}`);
  console.log(`  Database Mode: ${db.supabaseEnabled ? 'Supabase Cloud + Local Sync' : 'Local Store (Supabase Ready)'}`);
  console.log('===============================================================');
});
