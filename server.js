require('dotenv').config();
const app = require('./src/app');
const { DatabaseEngine } = require('./src/db');
const { AuctionEngineService } = require('./src/auctionEngine');

const PORT = Number(process.env.PORT || 8000);
const HOST = process.env.HOST || '0.0.0.0';

// Automatically close auctions as soon as their countdown completes
setInterval(() => {
  try {
    AuctionEngineService.synchronizeAuctions();
  } catch (_) {}
}, 5000);

app.listen(PORT, HOST, () => {
  const db = DatabaseEngine.getInstance();
  console.log('===============================================================');
  console.log('  ArtAlly — Node.js Fine Art Auction Platform');
  console.log(`  Local URL:     http://localhost:${PORT}`);
  console.log(`  Database Mode: ${db.supabaseEnabled ? 'Supabase Cloud + Local Sync' : 'Local Store (Supabase Ready)'}`);
  console.log('===============================================================');
});
