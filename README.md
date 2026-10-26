# ArtAlly — International Fine Art Auction Platform (Node.js + Supabase + Vercel)

**ArtAlly** is a full-stack fine art auction and consignment platform built with **Node.js**, **Express**, **EJS**, **Supabase (PostgreSQL)**, and **Vercel Serverless**.

---

## 1. Run Locally (Node.js)

```bash
npm install
npm run dev
```

Then open **`http://localhost:8000`** in your browser.

---

## 2. Connect Supabase Database

1. Create a new project at [supabase.com](https://supabase.com).
2. In your Supabase Dashboard, open the **SQL Editor**, paste the contents of [`database/supabase_schema.sql`](database/supabase_schema.sql), and click **Run**.
   - This creates `public.artally_store` (used for real-time atomic store synchronization across Vercel serverless invocations) as well as all 22 normalized PostgreSQL tables (`users`, `artist_profiles`, `buyer_profiles`, `categories`, `artworks`, `artwork_submissions`, `auctions`, `bids`, `orders`, `seller_payouts`, `notifications`, etc.).
3. Copy `.env.example` to `.env` and fill in your Supabase credentials from **Project Settings → API**:
   ```env
   SUPABASE_URL=https://your-project-id.supabase.co
   SUPABASE_ANON_KEY=your-supabase-anon-key
   SUPABASE_SERVICE_ROLE_KEY=your-supabase-service-role-key
   SESSION_SECRET=replace-with-a-long-random-secret
   ```
4. Push existing seeded/local data to Supabase:
   ```bash
   npm run db:push-supabase
   ```

---

## 3. Deploy with GitHub & Vercel

1. Push this repository to **GitHub**:
   ```bash
   git init
   git add .
   git commit -m "Convert ArtAlly to Node.js + Supabase + Vercel"
   git branch -M main
   git remote add origin https://github.com/YOUR_USERNAME/artally.git
   git push -u origin main
   ```
2. Go to [vercel.com/new](https://vercel.com/new) and import your GitHub repository.
3. In Vercel **Environment Variables**, add:
   - `SUPABASE_URL`
   - `SUPABASE_ANON_KEY`
   - `SUPABASE_SERVICE_ROLE_KEY`
   - `SESSION_SECRET`
4. Click **Deploy**. Vercel automatically uses [`vercel.json`](vercel.json) and [`api/index.js`](api/index.js) to serve the entire application.

---

## 4. Demo Credentials

| Role | Email | Password |
| :--- | :--- | :--- |
| **Administrator** | `admin@ateliernoir.art` | `AtelierNoir2026!` |
| **Artist** | `soren@ateliernoir.art` | `AtelierNoir2026!` |
| **Collector / Buyer** | `collector@ateliernoir.art` | `AtelierNoir2026!` |
