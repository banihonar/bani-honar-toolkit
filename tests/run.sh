#!/usr/bin/env bash
# Runs the automated checks. They use a small stand-in for WordPress (PHP + SQLite), not a real WordPress site.
# Needs PHP 7.4+ with the pdo_sqlite and mbstring extensions.
set -u
HERE="$(cd "$(dirname "$0")" && pwd)"
export PLUGIN_DIR="${PLUGIN_DIR:-$HERE/../bani-honar-toolkit}"
export T_DIR="$(mktemp -d)"
trap 'rm -rf "$T_DIR"' EXIT
fail=0
# The suites run in this order and share one temporary database.
for suite in unit_helpers s1_setup s2_hits s3_render s5_settings s7_block_user_search s9_cloner s11_contact s12_privacy s13_links s14_i18n_paths; do
  out="$(php "$HERE/$suite.php" 2>&1)"
  ok=$(printf '%s\n' "$out" | grep -c '^OK')
  bad=$(printf '%s\n' "$out" | grep -c '^FAIL \|Fatal error\|^PHP Warning\|^Warning:\|^Notice:\|^DEPRECATED')
  printf '%-24s ok=%-4s problems=%s\n' "$suite" "$ok" "$bad"
  if [ "$bad" != "0" ]; then fail=1; printf '%s\n' "$out" | grep '^FAIL \|Fatal error\|Warning\|Notice\|DEPRECATED' | head -8; fi
done
# The same checks on a copy built the way the WordPress.org zip is built: no .po/.mo files.
WPORG_DIR="$T_DIR/wporg/bani-honar-toolkit"
mkdir -p "$T_DIR/wporg" && cp -a "$PLUGIN_DIR" "$WPORG_DIR" && find "$WPORG_DIR" \( -name '*.po' -o -name '*.mo' \) -delete
out="$(PLUGIN_DIR="$WPORG_DIR" T_WPORG=1 php "$HERE/s14_i18n_paths.php" 2>&1)"
ok=$(printf '%s\n' "$out" | grep -c '^OK')
bad=$(printf '%s\n' "$out" | grep -c '^FAIL \|Fatal error\|^PHP Warning\|^Warning:\|^Notice:\|^DEPRECATED')
printf '%-24s ok=%-4s problems=%s\n' "s14 (wordpress.org build)" "$ok" "$bad"
if [ "$bad" != "0" ]; then fail=1; printf '%s\n' "$out" | grep '^FAIL \|Fatal error\|Warning\|Notice\|DEPRECATED' | head -8; fi
[ "$fail" = "0" ] && echo "ALL SUITES PASSED" || { echo "SOME CHECKS FAILED"; exit 1; }
