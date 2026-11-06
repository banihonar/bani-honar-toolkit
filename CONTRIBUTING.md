# Contributing

Thank you for helping. Suggestions, bug reports, translations and module ideas are welcome.

## Before you open an issue

- Suggestion, criticism or module request: open an issue with the matching template, or use the **Contact** tab inside the plugin.
- Security problem: do **not** open an issue. See [SECURITY.md](SECURITY.md).

## Pull requests

1. Fork, create a branch, make one focused change.
2. Follow the WordPress coding standards. Every handler must check capability and nonce; escape all output; prepare all SQL.
3. All visible text goes through `__()` / `esc_html__()` with the text domain `bani-honar-toolkit`. Add the Persian translation to `languages/*-fa_IR.po` and rebuild the `.mo`.
4. Run `bash tests/run.sh` and `php -l` on changed files. The tests use a small stand-in for WordPress (PHP + SQLite), so also try the change on a real WordPress site.
5. Describe what changed and why. Do not add obfuscated code, outside services without an off-by-default option, or tracking.

## Adding a module

Extend `BHTK_Module` and register it on the `bhtk_register_modules` action. See `bani-honar-toolkit/modules/qr-generator` for a small example.

## Release

1. Change the version in `bani-honar-toolkit/bani-honar-toolkit.php` (header and `BHTK_VERSION`) and `readme.txt` (`Stable tag`), and add the entry to `readme.txt` and `CHANGELOG.md`.
2. Run the checks and `bash bin/build-zip.sh`.
3. Tag `vX.Y.Z` and push the tag; the release workflow builds the zip and attaches it to the release.
