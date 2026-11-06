#!/usr/bin/env bash
# Builds two zips for Plugins > Add New > Upload Plugin. Each has one top folder: bani-honar-toolkit/
#
#   Bani-Honar-Toolkit-<version>.zip               For the WordPress.org plugin directory. It has no .po/.mo files,
#                                                  because WordPress.org delivers translations from translate.wordpress.org.
#   Bani-Honar-Toolkit-<version>-with-Persian.zip  For your own site and GitHub releases. It also has the Persian translation,
#                                                  so the Persian screens work at once.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
VERSION="$(sed -n 's/^ \* Version: *//p' "$ROOT/bani-honar-toolkit/bani-honar-toolkit.php" | head -1 | tr -d '\r')"
[ -n "$VERSION" ] || { echo "Could not read the version" >&2; exit 1; }
mkdir -p "$ROOT/dist"

WPORG="$ROOT/dist/Bani-Honar-Toolkit-$VERSION.zip"
FULL="$ROOT/dist/Bani-Honar-Toolkit-$VERSION-with-Persian.zip"
rm -f "$WPORG" "$FULL"

( cd "$ROOT" && zip -r -X -D -q "$WPORG" bani-honar-toolkit -x 'bani-honar-toolkit/languages/*.po' 'bani-honar-toolkit/languages/*.mo' )
( cd "$ROOT" && zip -r -X -D -q "$FULL" bani-honar-toolkit )

echo "$WPORG"
echo "$FULL"
