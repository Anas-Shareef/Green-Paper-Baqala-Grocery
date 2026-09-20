import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const rootDir = path.resolve(__dirname, '..');

const adminDist = path.join(rootDir, 'admin', 'dist');
const publicDir = path.join(rootDir, 'public');
const publicAssets = path.join(publicDir, 'assets');

const rootAssets = path.join(rootDir, 'assets');

if (!fs.existsSync(publicAssets)) {
  fs.mkdirSync(publicAssets, { recursive: true });
}
if (!fs.existsSync(rootAssets)) {
  fs.mkdirSync(rootAssets, { recursive: true });
}

// Copy manifest
const manifestSrc = path.join(adminDist, '.vite', 'manifest.json');
const manifestDest = path.join(publicDir, 'admin-manifest.json');
const manifestRootDest = path.join(rootDir, 'admin-manifest.json');
if (fs.existsSync(manifestSrc)) {
  fs.copyFileSync(manifestSrc, manifestDest);
  fs.copyFileSync(manifestSrc, manifestRootDest);
  console.log('Copied admin manifest to', manifestDest, 'and', manifestRootDest);
}

// Copy assets
const assetsSrc = path.join(adminDist, 'assets');
if (fs.existsSync(assetsSrc)) {
  const files = fs.readdirSync(assetsSrc);
  for (const file of files) {
    fs.copyFileSync(path.join(assetsSrc, file), path.join(publicAssets, file));
    fs.copyFileSync(path.join(assetsSrc, file), path.join(rootAssets, file));
    console.log('Copied asset:', file);
  }
}
