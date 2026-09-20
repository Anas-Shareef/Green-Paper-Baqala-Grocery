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
} else if (fs.existsSync(manifestDest)) {
  fs.copyFileSync(manifestDest, manifestRootDest);
  console.log('Mirrored manifest to', manifestRootDest);
}

// Copy assets
const assetsSrc = path.join(adminDist, 'assets');
const sourceDir = fs.existsSync(assetsSrc) ? assetsSrc : publicAssets;
if (fs.existsSync(sourceDir)) {
  const files = fs.readdirSync(sourceDir);
  for (const file of files) {
    const srcFile = path.join(sourceDir, file);
    if (!fs.statSync(srcFile).isFile()) continue;
    const destPublic = path.join(publicAssets, file);
    const destRoot = path.join(rootAssets, file);
    if (srcFile !== destPublic) {
      fs.copyFileSync(srcFile, destPublic);
    }
    if (srcFile !== destRoot) {
      fs.copyFileSync(srcFile, destRoot);
    }
    console.log('Synced asset:', file);
  }
}
