/**
 * Regenerate the PerForm WordPress.org icon + banner from a single source of
 * truth (the dbw brand gradient). Renders pixel-exact PNGs via headless Chrome.
 *
 * Usage (from the plugin root, Playwright + Chrome must be available):
 *   node .wordpress-org/generate-assets.mjs
 *
 * Outputs into this folder: icon-256x256.png, icon-128x128.png,
 * banner-1544x500.png, banner-772x250.png
 *
 * This file lives in .wordpress-org/ which is excluded from the distributed zip.
 */
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, '..');
const require = createRequire(path.join(ROOT, '/'));
const { chromium } = require('playwright');

const OUT = __dirname;

// dbw signature gradient, 135°.
const GRAD = 'linear-gradient(135deg,#ea2b1f 0%,#ff3c6f 25%,#ff4fdd 50%,#7e56ff 75%,#00b2ff 100%)';
const FONT = "'Inter','Helvetica Neue',Arial,sans-serif";
const INK = '#0F0F11';
const head = `
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;800&display=swap" rel="stylesheet">
<style>*{margin:0;padding:0;box-sizing:border-box}</style>`;

const gradP = (fontPx) => `
<div style="font-family:${FONT};font-weight:800;font-size:${fontPx}px;line-height:1;
  background:${GRAD};-webkit-background-clip:text;background-clip:text;
  color:transparent;-webkit-text-fill-color:transparent;">P</div>`;

const iconHTML = () => `<!doctype html><html><head>${head}</head>
<body style="width:256px;height:256px;background:${INK};display:flex;
  align-items:center;justify-content:center;overflow:hidden;">
  <div style="transform:translateY(-3px)">${gradP(200)}</div></body></html>`;

const bannerHTML = () => `<!doctype html><html><head>${head}</head>
<body style="width:1544px;height:500px;background:${INK};overflow:hidden;position:relative;">
  <div style="position:absolute;left:96px;top:140px;font-family:${FONT};font-weight:800;
    font-size:132px;color:#fff;line-height:1;">PerForm</div>
  <div style="position:absolute;left:100px;top:308px;font-family:${FONT};font-weight:500;
    font-size:40px;color:#9e9ea6;">Native WordPress forms — beautiful by default.</div>
  <div style="position:absolute;right:150px;top:50%;transform:translateY(-54%);">${gradP(470)}</div>
  <div style="position:absolute;left:0;bottom:0;width:100%;height:10px;background:${GRAD};"></div>
</body></html>`;

async function render(page, html, stageW, stageH, scale, file) {
  const w = Math.round(stageW * scale), h = Math.round(stageH * scale);
  await page.setViewportSize({ width: w, height: h });
  const wrapped = `<!doctype html><html><head>${head}</head><body style="margin:0;background:${INK}">
    <div style="width:${stageW}px;height:${stageH}px;transform:scale(${scale});transform-origin:top left;">
      <iframe srcdoc="${html.replace(/"/g, '&quot;')}" style="width:${stageW}px;height:${stageH}px;border:0;display:block"></iframe>
    </div></body></html>`;
  await page.setContent(wrapped, { waitUntil: 'networkidle' });
  await page.evaluate(async () => { try { await document.fonts.ready; } catch (e) {} });
  await page.waitForTimeout(300);
  await page.screenshot({ path: path.join(OUT, file), clip: { x: 0, y: 0, width: w, height: h } });
  console.log('  wrote', file, `${w}x${h}`);
}

const browser = await chromium.launch({ channel: 'chrome' });
const page = await browser.newPage({ deviceScaleFactor: 1 });
console.log('Rendering PerForm assets ->', OUT);
await render(page, iconHTML(), 256, 256, 1, 'icon-256x256.png');
await render(page, iconHTML(), 256, 256, 0.5, 'icon-128x128.png');
await render(page, bannerHTML(), 1544, 500, 1, 'banner-1544x500.png');
await render(page, bannerHTML(), 1544, 500, 0.5, 'banner-772x250.png');
await browser.close();
console.log('Done.');
