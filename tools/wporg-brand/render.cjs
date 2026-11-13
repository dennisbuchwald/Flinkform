// usage: node render.cjs <html> <outdir> <w> <h> <scale> <fps|0> [staticT]
const puppeteer = require('/Users/dennisbuchwald/Arbeitsplatz/01_Code/03_Spielwiese/rasierer-finder/node_modules/puppeteer');
const path = require('path'); const fs = require('fs');
(async () => {
  const [html, out, w, h, scale, fps, staticT] = process.argv.slice(2);
  fs.mkdirSync(out, { recursive: true });
  const b = await puppeteer.launch({ headless: 'shell', args: ['--force-color-profile=srgb'] });
  const p = await b.newPage();
  await p.setViewport({ width: +w, height: +h, deviceScaleFactor: +scale });
  await p.goto('file://' + path.resolve(html));
  await p.evaluate(() => document.fonts.ready);
  if (+fps === 0) {
    await p.evaluate(t => window.render && window.render(t), +(staticT ?? 3));
    await p.screenshot({ path: path.join(out, 'still.png'), omitBackground: true });
  } else {
    const dur = await p.evaluate(() => window.DURATION);
    const n = Math.round(dur * +fps);
    for (let i = 0; i < n; i++) {
      await p.evaluate(t => window.render(t), i / +fps);
      await p.screenshot({ path: path.join(out, `f${String(i).padStart(4, '0')}.png`), omitBackground: true });
    }
  }
  await b.close();
})();
