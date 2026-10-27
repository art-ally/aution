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

    // Test Artist Registration -> Artwork Upload -> Immediate Visibility on Admin Backend (#artists & #submissions)
    const artistEmail = `studio.${Date.now()}@artally.ch`;
    const sampleDataUrl =
      'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
    const regRes = await request(server, 'POST', '/register', {
      account_type: 'artist',
      name: 'Clara Vonnegut Studio',
      email: artistEmail,
      country: 'Switzerland',
      discipline: 'Contemporary Oil & Mixed Media',
      password: 'Password123!',
      terms_accepted: '1',
      profile_photo_data: sampleDataUrl,
    });
    if (regRes.status !== 302 || String(regRes.headers.location || '') !== '/artist/dashboard') {
      throw new Error(
        `Expected 302 redirect to /artist/dashboard on /register, got ${regRes.status} (${regRes.headers.location})`
      );
    }
    console.log(`PASS Artist Registration (${artistEmail}) -> 302 (/artist/dashboard)`);

    const uniqueWorkTitle = `Chromatic Resonance Study #${Date.now()}`;
    const submitRes = await request(
      server,
      'POST',
      '/artist/submit',
      {
        submission_mode: 'submitted',
        title: uniqueWorkTitle,
        category_id: '1',
        medium: 'Oil and cold wax on linen',
        year_created: '2026',
        dimensions: '140 × 110 cm',
        classification: 'Original Unique Work',
        edition_number: 'Unique (1/1)',
        artist_attribution: 'Signed on verso by Clara Vonnegut',
        description: 'A museum-grade exploration of impasto texture and warm ochre tonalities.',
        suggested_starting_bid: '4500',
        artist_expected_price: '7800',
        reserve_price: '6000',
        preferred_duration_days: '7',
        currency: 'USD',
        estimate_low: '6500',
        estimate_high: '9500',
        provenance: 'Direct from the Geneva studio of the artist.',
        condition_report: 'Pristine original condition.',
        ownership_declared: '1',
        copyright_confirmed: '1',
        'artwork_images_data[]': sampleDataUrl,
      },
      regRes.cookie
    );
    if (submitRes.status !== 302) {
      throw new Error(`Expected 302 on POST /artist/submit, got ${submitRes.status}`);
    }
    console.log(`PASS Artist Artwork Upload ("${uniqueWorkTitle}") -> 302`);

    // Verify uploaded artwork immediately appears in Admin Backend
    const adminDashAfterUpload = await request(server, 'GET', '/admin/dashboard', null, adminLogin.cookie);
    if (
      adminDashAfterUpload.status !== 200 ||
      !adminDashAfterUpload.body.includes(uniqueWorkTitle) ||
      !adminDashAfterUpload.body.includes('Clara Vonnegut Studio')
    ) {
      throw new Error(
        `Uploaded artwork "${uniqueWorkTitle}" or artist "Clara Vonnegut Studio" did not appear on /admin/dashboard!`
      );
    }
    console.log(`PASS Admin Backend /admin/dashboard immediately displays uploaded artwork "${uniqueWorkTitle}"`);

    // Test Artist Counter-Offer Badge in Admin Backend
    const { DatabaseEngine } = require('../src/db');
    const db = DatabaseEngine.getInstance();
    const uploadedArt = db.findBy('artworks', 'title', uniqueWorkTitle);
    const uploadedSub = db.findBy('artwork_submissions', 'artwork_id', Number(uploadedArt.id));

    const negRes = await request(
      server,
      'POST',
      '/artist/submissions/negotiate',
      {
        submission_id: String(uploadedSub.id),
        response_action: 'counter',
        counter_price: '8200',
        artist_message: 'Requesting $8,200 reserve due to recent museum exhibition.',
      },
      regRes.cookie
    );
    if (negRes.status !== 302) {
      throw new Error(`Expected 302 on /artist/submissions/negotiate, got ${negRes.status}`);
    }

    const adminDashAfterCounter = await request(server, 'GET', '/admin/dashboard', null, adminLogin.cookie);
    if (!adminDashAfterCounter.body.includes('⇄ Counter-Offer by Artist')) {
      throw new Error('Expected "⇄ Counter-Offer by Artist" badge on /admin/dashboard after artist counter-offer');
    }
    console.log('PASS Artist Counter-Offer badge ("⇄ Counter-Offer by Artist") appears on /admin/dashboard');

    // Clean up any test records created during this or prior test runs
    for (const art of db.all('artworks')) {
      if (String(art.title || '').startsWith('Chromatic Resonance Study #')) {
        await request(
          server,
          'POST',
          '/admin/artworks/delete',
          { artwork_id: String(art.id) },
          adminLogin.cookie
        );
      }
    }
    for (const u of db.all('users')) {
      if (String(u.email || '').startsWith('studio.') && String(u.email || '').endsWith('@artally.ch')) {
        await request(
          server,
          'POST',
          '/admin/users/delete',
          { user_id: String(u.id) },
          adminLogin.cookie
        );
      }
    }
    for (const inv of db.all('authentication_invoices')) {
      if (String(inv.artwork_title || '') === 'Nocturne in Obsidian No. 4') {
        await request(
          server,
          'POST',
          '/admin/invoices/delete',
          { invoice_id: String(inv.id) },
          adminLogin.cookie
        );
      }
    }
    await db.flush();
    console.log('PASS Cleaned up test artist, artwork, and invoice records.');

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
