import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const rootDir = path.resolve(__dirname, '..');

const adminDist = path.join(rootDir, 'admin', 'dist');
const publicDir = path.join(rootDir, 'public');
const publicAssets = path.join(publicDir, 'assets');

if (!fs.existsSync(publicAssets)) {
  fs.mkdirSync(publicAssets, { recursive: true });
}

// Copy manifest
const manifestSrc = path.join(adminDist, '.vite', 'manifest.json');
const manifestDest = path.join(publicDir, 'admin-manifest.json');
if (fs.existsSync(manifestSrc)) {
  fs.copyFileSync(manifestSrc, manifestDest);
  console.log('Copied admin manifest to', manifestDest);
}

// Copy assets
const assetsSrc = path.join(adminDist, 'assets');
if (fs.existsSync(assetsSrc)) {
  const files = fs.readdirSync(assetsSrc);
  for (const file of files) {
    fs.copyFileSync(path.join(assetsSrc, file), path.join(publicAssets, file));
    console.log('Copied asset:', file);
  }
}
