#!/usr/bin/env bash
# Run the Phase 1 tax-handling matrix against the real frontend.
#
# Mutates WC + GTM Kit settings via wp-cli, fetches the rendered
# `view_cart` and `begin_checkout` pages with curl + a persistent cookie
# jar, extracts the actual `dataLayer.push(...)` payload from the HTML,
# and asserts `sum(items[].price * qty) === ecommerce.value` per cell.
#
# Mirrors the PHP-side driver at run-tax-matrix.php; runs in addition,
# not in place. The PHP driver tests the migrated callsites in
# WooCommerce.php directly; this script verifies that those same numbers
# reach the rendered HTML the browser would receive.
#
# Usage:  bash tests/manual/run-tax-matrix-frontend.sh [product_id]
# Defaults to product_id=31 (the `Cap` product on the dev store).

set -euo pipefail

PRODUCT_ID="${1:-31}"
SITE="${SITE:-https://gtmkitdev.test}"
JAR="$(mktemp -t gtmkit-jar.XXXXXX)"
trap 'rm -f "$JAR"' EXIT

CURL=(curl -sk --max-time 10)

# ----------------------------------------------------------------- helpers
_wp() { wp "$@" 2>/dev/null | grep -v 'Deprecated:' | awk 'NF' || true; }

snapshot_state() {
  PRICES_INCLUDE_TAX=$(_wp option get woocommerce_prices_include_tax)
  TAX_DISPLAY_CART=$(_wp option get woocommerce_tax_display_cart)
  TAX_DISPLAY_SHOP=$(_wp option get woocommerce_tax_display_shop)
  GTMKIT_BACKUP="$(mktemp -t gtmkit-snap.XXXXXX)"
  _wp option get gtmkit --format=json > "$GTMKIT_BACKUP"
  echo "snapshot: prices_include_tax=$PRICES_INCLUDE_TAX display=$TAX_DISPLAY_CART/$TAX_DISPLAY_SHOP (gtmkit -> $GTMKIT_BACKUP)"
}

restore_state() {
  _wp option update woocommerce_prices_include_tax "$PRICES_INCLUDE_TAX" >/dev/null
  _wp option update woocommerce_tax_display_cart   "$TAX_DISPLAY_CART"   >/dev/null
  _wp option update woocommerce_tax_display_shop   "$TAX_DISPLAY_SHOP"   >/dev/null
  _wp option update gtmkit "$(cat "$GTMKIT_BACKUP")" --format=json >/dev/null
  rm -f "$GTMKIT_BACKUP"
  _wp cache flush >/dev/null
  echo "restore: OK (cache flushed)"
}

apply_cell() {
  local prices_include_tax="$1" display="$2" toggle="$3"
  _wp option update woocommerce_prices_include_tax "$prices_include_tax" >/dev/null
  _wp option update woocommerce_tax_display_cart   "$display"            >/dev/null
  _wp option update woocommerce_tax_display_shop   "$display"            >/dev/null
  # `wp option patch update --format=json` flattens nested values
  # unreliably here; mutate the option in place via wp eval so the rest
  # of the `integrations` payload is preserved exactly.
  _wp eval "
    \$o = get_option('gtmkit', []);
    \$o['integrations']['woocommerce_exclude_tax'] = ('$toggle' === 'true');
    update_option('gtmkit', \$o);
  " >/dev/null
  # Flush the object cache. WP invalidates the changed option key on
  # update_option(), but Redis Object Cache (and any persistent cache)
  # can still hold WC tax-rate rows, product objects, and alloptions
  # snapshots from before the change. A flush guarantees the next
  # request rebuilds tax math from current settings.
  _wp cache flush >/dev/null
}

extract_datalayer() {
  # Pulls the JSON object assigned to gtmkit_dataLayer_content from the
  # page HTML. The const-assignment line is single-line in the rendered
  # output, so a greedy match between the `=` and the `;\n` terminator
  # captures the full object.
  local html_file="$1"
  python3 - "$html_file" <<'PY'
import json, re, sys
html = open(sys.argv[1]).read()
m = re.search(r'const\s+gtmkit_dataLayer_content\s*=\s*(\{.*?\});\s*\n', html)
if not m:
    print('null')
    sys.exit(0)
print(m.group(1))
PY
}

assert_cell() {
  local label="$1" expected_value="$2" expected_price="$3" event="$4" payload="$5"
  python3 - "$label" "$expected_value" "$expected_price" "$event" "$payload" <<'PY'
import json, sys
label, expected_value, expected_price, event, payload = sys.argv[1:6]
data = json.loads(payload)
ec = data.get('ecommerce', {})
value = ec.get('value')
items = ec.get('items', [])
sum_items = sum(float(it.get('price', 0)) * int(it.get('quantity', 1)) for it in items)
first_price = items[0].get('price') if items else None
ev_ok = data.get('event') == event
val_ok = value is not None and abs(float(value) - float(expected_value)) < 0.01
prc_ok = first_price is not None and abs(float(first_price) - float(expected_price)) < 0.01
inv_ok = value is not None and abs(float(value) - sum_items) < 0.01
all_ok = ev_ok and val_ok and prc_ok and inv_ok
status = 'PASS' if all_ok else 'FAIL'
print(f"  {label}  {event:<14}  value={value!s:<10} sum_items={sum_items:<10.4f} items[0].price={first_price!s:<10} {status}")
sys.exit(0 if all_ok else 1)
PY
}

run_cell() {
  local label="$1" prices="$2" display="$3" toggle="$4" expected_value="$5" expected_price="$6"
  apply_cell "$prices" "$display" "$toggle"

  # Fresh per-cell session: a new cookie jar means WC issues a new
  # session ID on the first request, so the cart starts empty and
  # `?add-to-cart=ID&quantity=1` produces exactly one line item.
  local cell_jar
  cell_jar="$(mktemp -t gtmkit-cell-jar.XXXXXX)"
  "${CURL[@]}" -c "$cell_jar" -b "$cell_jar" -L -o /dev/null "$SITE/?add-to-cart=$PRODUCT_ID&quantity=1"

  "${CURL[@]}" -b "$cell_jar" -L "$SITE/cart/"     > /tmp/gtmkit-cell.cart.html
  "${CURL[@]}" -b "$cell_jar" -L "$SITE/checkout/" > /tmp/gtmkit-cell.checkout.html
  rm -f "$cell_jar"

  local view_cart begin_checkout
  view_cart="$(extract_datalayer /tmp/gtmkit-cell.cart.html)"
  begin_checkout="$(extract_datalayer /tmp/gtmkit-cell.checkout.html)"

  local cell_pass=true
  assert_cell "$label" "$expected_value" "$expected_price" "view_cart" "$view_cart" || cell_pass=false
  assert_cell "$label" "$expected_value" "$expected_price" "begin_checkout" "$begin_checkout" || cell_pass=false

  if ! $cell_pass; then
    OVERALL_PASS=false
  fi
}

# ---------------------------------------------------------------- run
echo "Tax-handling frontend matrix: product_id=$PRODUCT_ID site=$SITE"
snapshot_state

OVERALL_PASS=true

# Cell 1: control. Prices stored inc-tax, display incl, toggle OFF -> 16 (inc-tax).
run_cell "cell-1 control      " yes incl false 16 16
# Cell 2: @andersbolander. Prices stored ex-tax, display incl, toggle OFF -> 20 (inc-tax = 16 * 1.25).
run_cell "cell-2 andersbolander" no  incl false 20 20
# Cell 3: toggle ON. Prices stored ex-tax, display incl, toggle ON -> 16 (ex-tax stored value).
run_cell "cell-3 toggle ON    " no  incl true  16 16

restore_state

if $OVERALL_PASS; then
  echo "frontend matrix: PASS"
  exit 0
fi
echo "frontend matrix: FAIL" >&2
exit 1
