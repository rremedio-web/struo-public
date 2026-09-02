# Packaging

Release zip: `bash bin/build-zip.sh` → `dist/struo-0.3.0.zip`.

The zip contains `struo.php`, `includes/`, PHP under `src/` (not `src/work-queue` or `src/gutenberg-host`), `block-manifest.json`, docs at repo root (`README.md`, `CHANGELOG.md`, `readme.txt`, `LICENSE`), `provider-url-guard.php`, `uninstall.php`, and built `assets/` without `assets/src`.

See [development.md](development.md).
