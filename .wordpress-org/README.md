# WordPress.org assets

These files become the plugin's **listing assets** on wordpress.org — the icon,
header banner and screenshots shown on the plugin page. They are **not** part of
the installed plugin: they live in the SVN `assets/` directory, never in `trunk/`.
That is why `.wordpress-org/` is excluded from the distributed zip (see
`.distignore`).

## Deploy path

After the plugin is approved and the SVN repo exists:

```bash
svn checkout https://plugins.svn.wordpress.org/perform-forms _wporg-svn
cp .wordpress-org/* _wporg-svn/assets/
cd _wporg-svn
svn add assets/* --force
svn commit -m "Add listing assets" --username dbwmediadennis
```

Assets update live within minutes and are independent of plugin version tags.

## Required files & exact specs

| File | Size (px) | Purpose | Status |
|------|-----------|---------|--------|
| `icon-128x128.png` | 128 × 128 | Plugin icon (directory + search) | ☐ TODO |
| `icon-256x256.png` | 256 × 256 | Retina icon | ☐ TODO |
| `banner-772x250.png` | 772 × 250 | Header banner (standard) | ☐ TODO |
| `banner-1544x500.png` | 1544 × 500 | Header banner (retina) | ☐ TODO |
| `screenshot-1.png` … `screenshot-7.png` | any (consistent aspect) | Gallery | ☐ TODO |

- PNG (or JPG). An animated `icon-256x256.gif` is allowed but optional.
- An SVG icon (`icon.svg`) is also accepted and scales best — but ship the PNGs
  too for older WP installs.
- Keep total asset weight reasonable; banners are large surfaces, optimise them.

## Screenshot captions

Captions come from the `== Screenshots ==` section of `readme.txt`, matched **by
order**. The current list (keep these in sync):

1. Block Editor — building a contact form with the PerForm blocks
2. Frontend — a styled single-step form on GeneratePress
3. Multi-step form with progress bar
4. Submissions list in wp-admin
5. Submission detail view
6. Conditional logic in the block inspector
7. Style panel — field style, label position, colours

Screenshots are produced from the live sandbox (browser-driven E2E pass), not
generated. Icon + banner are designed (Figma).

## Brand notes

- Author / studio: **dbw media** (https://dbw-media.de), Dennis Buchwald.
- Keep the icon legible at 128 px and inside the search grid (small). Favour a
  single strong mark over fine detail.
- Banner text is optional; WordPress overlays the plugin title near the lower-left,
  so keep that zone calm and put the logo/mark toward the right.
