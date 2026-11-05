# Leftovers

Things known to be unfinished, untested or deliberately left out. Remove an item when it is done.

## Untested in WooCommerce

Nothing has been run in a real WooCommerce site yet (only typecheck, build, `php -l` and `php tests/core-test.php`).

- Hold mode (`OrderTracker::created()`): confirm the order lands in `on-hold` with the note on both classic and block checkout, and that a paid gateway's `payment_complete()` does not move it back to processing (filter on `woocommerce_payment_complete_order_status`).
- Settings screen: save, discard, and the block/hold exclusivity in a browser.
- Block checkout: verify billing details exist when the coupon is validated. The check fails open if they do not.
- Classic and block checkout both need a manual pass before release.

## WordPress.org submission (from CLAUDE.md)

1. Confirm `Contributors` in `readme.txt` is the real WordPress.org username, and `Tested up to` is the current WordPress major version.
2. Add `.wordpress-org/` assets: `banner-772x250.png`, `banner-1544x500.png`, `icon-128x128.png`, `icon-256x256.png`, screenshots, then a Screenshots section in the readme.
3. Run Plugin Check and PHPCS (WordPress standards) in a local WooCommerce site (Herd).

## Scale limits

- Import handles 1000 orders per request.
- The rings page loads all rows. Fine to about 50k orders, then needs server-side paging in SQL.

## Left out of the Plugin Settings design on purpose

- Fake WordPress admin chrome (sidebar, top bar) from the mockup.
- Product photos in the preview (neutral icon tiles instead). "Read our plain-language Privacy Policy" link: it belongs to the cloud lookup, which does not exist.
- The preview uses static demo data (Alex Mercer, WELCOME20 at 20%). It does not read a real coupon or the store's currency.
- The shopper-facing coupon notice text is not editable. A custom message setting could come later.

## Cloud cluster lookup

- The toggle is disabled with a "Coming soon" badge and stores nothing. No external calls exist.
- Building it needs: opt-in, a documented data flow in the readme, legal/GDPR review, a hosted backend, and a consent record. See the roadmap in `CLAUDE.md`.

## Behaviour to revisit

- Block and hold are mutually exclusive. If merchants want both (block known rings, hold the rest), the settings model needs a third state.
- Hold only applies to coupons with "New customers only (Twinspot)" ticked. Orders without such a coupon are never held.
- Admin-blocked rings refuse every coupon in both block and hold mode. Only the shield being fully off (both toggles off) disables them.
- A gateway that calls `update_status('processing')` directly (not `payment_complete()`) can still move a held order out of on-hold. Fix would be reverting non-admin status changes on orders with `_twinspot_lite_held`.
- Hold checks matches at order creation only, not when an order later reaches processing or completed.
- A held order is released when a user with `edit_shop_orders` changes its status away from on-hold; this clears `_twinspot_lite_held`, so later payment webhooks do not hold it again.

## Roadmap items not started

- Visual ring graph beyond the current connection map, auto-hold on suspicious orders by score, refund-abuse scoring.
- Optional hosted engine and Pro features, WooCommerce.com Marketplace, link to the Twinspot Shopify app.
- Device fingerprinting: out of scope until there is a consent toggle.
