const http = require('http');
const app = require('../src/app');

function request(server, method, path, body = null, cookie = '') {
  return new Promise((resolve, reject) => {
    const addr = server.address();
    const headers = {};
    if (cookie) headers.Cookie = cookie;
    let payload = null;
    if (body) {
      payload = new URLSearchParams(body).toString();
      headers['Content-Type'] = 'application/x-www-form-urlencoded';
      headers['Content-Length'] = Buffer.byteLength(payload);
    }

    const req = http.request(
      {
        hostname: '127.0.0.1',
        port: addr.port,
        path,
        method,
        headers,
      },
      (res) => {
        let data = '';
        res.on('data', (chunk) => (data += chunk));
        res.on('end', () => {
          const setCookies = res.headers['set-cookie'] || [];
          const newCookie = setCookies.map((c) => c.split(';')[0]).join('; ');
          resolve({
            status: res.statusCode,
            headers: res.headers,
            body: data,
            cookie: newCookie || cookie,
          });
        });
      }
    );
    req.on('error', reject);
    if (payload) req.write(payload);
    req.end();
  });
}

(async () => {
  const server = app.listen(0);
  try {
    const publicRoutes = [
      '/',
      '/auctions',
      '/upcoming-auctions',
      '/catalogue',
      '/categories',
      '/artists',
      '/journal',
      '/sell-your-art',
      '/about',
      '/login',
      '/register',
      '/admin/login',
      '/sitemap.xml',
    ];

    for (const r of publicRoutes) {
      const res = await request(server, 'GET', r);
      if (res.status !== 200) {
        throw new Error(`Expected 200 on ${r}, got ${res.status}`);
      }
      console.log(`PASS GET ${r} -> ${res.status} (${res.body.length} bytes)`);
    }

    // Test Admin Login & Dashboard
    const adminLogin = await request(server, 'POST', '/login/demo', { demo_role: 'admin' });
    if (adminLogin.status !== 302) {
      throw new Error(`Expected 302 on /login/demo (admin), got ${adminLogin.status}`);
    }
    const adminDash = await request(server, 'GET', '/admin/dashboard', null, adminLogin.cookie);
    if (adminDash.status !== 200 || !adminDash.body.includes('/admin/database/clear-all')) {
      throw new Error(`Expected 200 and Delete All Backend Data button on /admin/dashboard`);
    }
    console.log(`PASS ADMIN Dashboard /admin/dashboard -> ${adminDash.status} (${adminDash.body.length} bytes)`);

    // Since admin has roles ['admin', 'buyer', 'artist'], verify Artist & Buyer dashboards also render cleanly
    for (const dashUrl of ['/artist/dashboard', '/buyer/dashboard']) {
      const dashRes = await request(server, 'GET', dashUrl, null, adminLogin.cookie);
      if (dashRes.status !== 200) {
        throw new Error(`Expected 200 on ${dashUrl}, got ${dashRes.status}`);
      }
      console.log(`PASS Dashboard ${dashUrl} -> ${dashRes.status} (${dashRes.body.length} bytes)`);
    }

    // Test Authentication Invoice Generator -> Print View -> Download -> Artist Page Display
    const genRes = await request(
      server,
      'POST',
      '/admin/invoices/generate',
      {
        artist_id: '1',
        artist_name: 'Margaux Vance (Chief Curator)',
        artist_email: 'admin@ateliernoir.art',
        artist_details: 'Geneva Studio · Contemporary Oil Painting',
        artwork_title: 'Nocturne in Obsidian No. 4',
        artwork_details: 'Oil on Belgian Linen · 160 × 120 cm',
        reference_code: 'AN-2026-1001',
        service_type: 'Independent Curatorial Connoisseurship & AI-Powered Authentication Evaluation',
        fee_amount: '450.00',
        currency: 'USD',
        due_date: '2026-11-01',
        status: 'unpaid',
        payment_method: 'bank_and_crypto',
        payment_account_name: 'ArtAlly Curatorial Escrow Treasury',
        payment_bank_name: 'Lombard Odier Private Bank',
        payment_account_number: 'CH93 0000 0000 1234 5678 9',
        payment_routing_details: 'SWIFT: LOCYCHGG',
        crypto_currency: 'USDT (Tether)',
        crypto_network: 'ERC-20 (Ethereum)',
        crypto_wallet_address: '0x71C9A8B400F9E1234567890ABCDEF1234567890A',
        crypto_memo: 'Send only USDT on ERC-20',
        payment_instructions: 'Quote Invoice Number in wire transfer memo or send TXID.',
      },
      adminLogin.cookie
    );

    if (genRes.status !== 302 || !String(genRes.headers.location || '').includes('?print=1')) {
      throw new Error(`Expected 302 redirect to /invoices/:id?print=1, got ${genRes.status} (${genRes.headers.location})`);
    }
    const invoicePrintUrl = genRes.headers.location;
    const invoiceIdMatch = invoicePrintUrl.match(/\/invoices\/(\d+)/);
    const invoiceId = invoiceIdMatch ? invoiceIdMatch[1] : '1';
    console.log(`PASS POST /admin/invoices/generate -> Redirected to ${invoicePrintUrl}`);

    const printViewRes = await request(server, 'GET', invoicePrintUrl, null, adminLogin.cookie);
    if (
      printViewRes.status !== 200 ||
      !printViewRes.body.includes('AUTHENTICATION INVOICE') ||
      !printViewRes.body.includes('0x71C9A8B400F9E1234567890ABCDEF1234567890A')
    ) {
      throw new Error(`Expected 200 and crypto wallet on ${invoicePrintUrl}`);
    }
    console.log(`PASS GET ${invoicePrintUrl} (Bank + Crypto) -> ${printViewRes.status} (${printViewRes.body.length} bytes)`);

    const downloadRes = await request(server, 'GET', `/invoices/${invoiceId}/download`, null, adminLogin.cookie);
    if (
      downloadRes.status !== 200 ||
      !String(downloadRes.headers['content-disposition'] || '').includes('attachment;')
    ) {
      throw new Error(`Expected downloadable attachment on /invoices/${invoiceId}/download`);
    }
    console.log(`PASS GET /invoices/${invoiceId}/download -> ${downloadRes.status} (${downloadRes.headers['content-disposition']})`);

    // Verify invoice appears on Artist Studio Dashboard
    const artistDash = await request(server, 'GET', '/artist/dashboard', null, adminLogin.cookie);
    if (!artistDash.body.includes(`/invoices/${invoiceId}/download`)) {
      throw new Error('Expected downloadable invoice link on /artist/dashboard');
    }
    console.log(`PASS Artist Dashboard displays Download Invoice Copy button for Invoice #${invoiceId}`);

    // Test Delete All Backend & Demo Data Button (POST /admin/database/clear-all)
    const clearRes = await request(server, 'POST', '/admin/database/clear-all', {}, adminLogin.cookie);
    if (clearRes.status !== 302) {
      throw new Error(`Expected 302 on /admin/database/clear-all, got ${clearRes.status}`);
    }
    console.log('PASS POST /admin/database/clear-all -> Wiped all backend & demo data for fresh entries!');

    console.log('ALL NODE.JS APP TESTS PASSED!');
  } finally {
    server.close();
    const { DatabaseEngine } = require('../src/db');
    const db = DatabaseEngine.getInstance();
    if (db.pgPool) {
      await db.pgPool.end().catch(() => {});
    }
  }
})();
