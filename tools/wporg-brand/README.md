# Flinkform mark, icon and banner (sources)

The mark (2026-11): an "F" built from a form. Two input fields and a consent
checkbox form the letter, the round submit button with a tick sits in the
F's empty corner. Brand gradient: dbw media, 5 stops red to blue.

| File | What |
| --- | --- |
| `icon.html` | Icon, every frame is `render(t)`; `DURATION` 4 s loop, still = `render(3.0)` |
| `banner.html` | WordPress.org banner, laid out at 772 × 250 CSS px |
| `menu.svg` | Single-colour admin menu icon (same paths as `Admin\Menu::menu_icon()`) |
| `render.cjs` | Puppeteer renderer (still or frame sequence) |

The banner needs `GT-Walsheim-Pro-Bold.ttf` next to `banner.html` (licensed,
not in this public repo; copy from
`dbw-media.de-2.0-master/assets/fonts/gt-walsheim-font-family/`).

```sh
node render.cjs icon.html out/icon512 256 256 2 0 3.0     # still, 512 px
node render.cjs icon.html out/frames 256 256 2 25          # 100 frames
node render.cjs banner.html out/b2 772 250 2 0             # 1544 × 500
node render.cjs banner.html out/b1 772 250 1 0             # 772 × 250
```

Scale the 512 still down to 256 and 128 with Lanczos for `.wordpress-org/`.
GIF (white matte, under the 1 MB icon limit):

```sh
ffmpeg -framerate 25 -i out/frames/f%04d.png -f lavfi -i color=white:s=512x512 \
  -filter_complex "[1][0]overlay=shortest=1,scale=256:256:flags=lanczos,split[a][b];[a]palettegen=max_colors=96:stats_mode=full[p];[b][p]paletteuse=dither=bayer:bayer_scale=4:diff_mode=rectangle" \
  -loop 0 icon-256x256.gif
```

Animated icons on WordPress.org are a grey zone (Meta Trac #5464 proposes
blocking them, the plugin team has asked authors to remove animation). The
directory therefore gets the static PNG.
