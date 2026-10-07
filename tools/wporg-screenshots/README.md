# WordPress.org screenshots

Rebuilds `.wordpress-org/screenshot-1.png` … `screenshot-6.png` from a local
WordPress Playground. The captions live in `readme.txt` under
`== Screenshots ==`; numbering is the contract between both.

```bash
cd tools/wporg-screenshots
# 1. The released plugin, exactly as users get it:
curl -sLo ff.zip https://downloads.wordpress.org/plugin/flinkform.<VERSION>.zip && unzip -qo ff.zip -d plugin
# 2. Playground with the two demo pages (Contact, "Start your project"):
npx -y @wp-playground/cli@latest server --port 9441 --php 8.3 --login --blueprint=$PWD/bp.json \
  --mount-before-install=$PWD/plugin/flinkform:/wordpress/wp-content/plugins/flinkform \
  --mount-before-install=$PWD/mu:/wordpress/wp-content/mu-plugins
# 3. In a second shell (English date fields need the test browser in English on macOS):
defaults write com.google.chrome.for.testing AppleLanguages -array en-US
mkdir -p out && FF_REPO=$(git rev-parse --show-toplevel) node shots.cjs
defaults delete com.google.chrome.for.testing AppleLanguages
# 4. Compress into the assets folder:
for i in 1 2 3 4 5 6; do pngquant --quality=80-95 --speed 1 --force --output ../../.wordpress-org/screenshot-$i.png out/screenshot-$i.png; done
```

`ONLY=4,5` re-takes single shots. `mu/ff-shots.php` is a test-only helper:
fake mail success (Playground cannot send mail), `?ff_reset` / `?ff_spread`
for realistic submission dates, `?ff_var=<Style>` for theme style variations.
Upload without a release: README-RELEASE.md, section 5.
