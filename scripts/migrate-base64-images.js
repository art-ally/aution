const fs = require('fs');
const path = require('path');

const PUBLIC_UPLOADS = path.resolve(process.cwd(), 'public/uploads');
const STORE_PATH = path.resolve(process.cwd(), 'storage/data/atelier_noir_store.json');

fs.mkdirSync(path.join(PUBLIC_UPLOADS, 'artworks'), { recursive: true });
fs.mkdirSync(path.join(PUBLIC_UPLOADS, 'profiles'), { recursive: true });

const data = JSON.parse(fs.readFileSync(STORE_PATH, 'utf8'));

function saveB64(dataUrl, subDir, fileNameBase) {
  if (!dataUrl || typeof dataUrl !== 'string' || !dataUrl.startsWith('data:image/')) {
    return dataUrl;
  }
  const match = dataUrl.match(/^data:image\/([a-zA-Z0-9+.-]+);base64,(.+)$/);
  if (!match) return dataUrl;
  let ext = match[1].toLowerCase();
  if (ext === 'jpeg' || ext === 'jfif') ext = 'jpg';
  if (!['jpg', 'png', 'webp', 'gif', 'avif', 'bmp', 'svg'].includes(ext)) ext = 'jpg';

  const buf = Buffer.from(match[2], 'base64');
  const filename = `${fileNameBase}.${ext}`;
  const diskPath = path.join(PUBLIC_UPLOADS, subDir, filename);
  fs.writeFileSync(diskPath, buf);
  console.log(`Saved ${diskPath} (${Math.round(buf.length / 1024)} KB)`);
  return `/uploads/${subDir}/${filename}`;
}

let migratedArtworks = 0;
for (const art of (data.artworks || [])) {
  if (typeof art.primary_image === 'string' && art.primary_image.startsWith('data:image/')) {
    art.primary_image = saveB64(art.primary_image, 'artworks', `art_${art.id}_main`);
    migratedArtworks++;
  }
}

let migratedImages = 0;
for (const img of (data.artwork_images || [])) {
  if (typeof img.image_url === 'string' && img.image_url.startsWith('data:image/')) {
    img.image_url = saveB64(img.image_url, 'artworks', `art_${img.artwork_id}_gallery_${img.id}`);
    migratedImages++;
  }
}

let migratedProfiles = 0;
for (const prof of (data.artist_profiles || [])) {
  if (typeof prof.profile_photo === 'string' && prof.profile_photo.startsWith('data:image/')) {
    prof.profile_photo = saveB64(prof.profile_photo, 'profiles', `artist_${prof.id}_photo`);
    migratedProfiles++;
  }
}

fs.writeFileSync(STORE_PATH, JSON.stringify(data, null, 2), 'utf8');

const newSize = fs.statSync(STORE_PATH).size;
console.log(`Migration complete!`);
console.log(`Migrated ${migratedArtworks} artworks, ${migratedImages} gallery images, ${migratedProfiles} profiles.`);
console.log(`New store size: ${Math.round(newSize / 1024)} KB (was ~21,600 KB)!`);
