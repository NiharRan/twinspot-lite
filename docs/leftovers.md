# Leftovers

Things known to be unfinished, untested or deliberately left out. Remove an item when it is done.

## Untested

Tested so far: `php tests/core-test.php`, `tests/wc-test.php` (WP-CLI, real WooCommerce), and by hand in a browser on classic and block checkout (block mode refusal, hold mode, gateway revert to on-hold, new shopper unaffected). Only COD was tried as a gateway.

- Settings screen and the other React screens: save, discard, and the block/hold exclusivity in a browser.
- Other plugins: Subscriptions, coupon plugins (Advanced Coupons, Smart Coupons), Stripe and PayPal, caching plugins.
- Block checkout: only the billing email was confirmed at coupon validation. Phone and address were not checked.
- Stale "Coupon Blocked" order note: if a shopper is refused, changes their details and checks out without the coupon, the order still gets the note (a session value). Fixed only for the case where the coupon was applied in the end.

## WordPress.org submission (from CLAUDE.md)

1. Confirm `Contributors` in `readme.txt` is the real WordPress.org username, and `Tested up to` is the current WordPress major version.
2. Add `.wordpress-org/` assets: `banner-772x250.png`, `banner-1544x500.png`, `icon-128x128.png`, `icon-256x256.png`, screenshots, then a Screenshots section in the readme.
3. Done: Plugin Check on the built zip and PHPCS report no errors.

## Scale limits

- Import handles 1000 orders per request.
- The rings page loads all rows. Fine to about 50k orders, then needs server-side paging in SQL.

## Left out of the Plugin Settings design on purpose

- Fake WordPress admin chrome (sidebar, top bar) from the mockup.
- Product photos in the preview (neutral icon tiles instead). "Read our plain-language Privacy Policy" link: it belongs to the cloud lookup, which does not exist.
- The preview uses static demo data (Alex Mercer, WELCOME20 at 20%). It does not read a real coupon or the store's currency.
- The shopper-facing coupon notice text is not editable. A custom message setting could come later.

## Cloud cluster lookup

- No toggle in Settings (removed until the feature exists). No external calls exist.
- Building it needs: opt-in, a documented data flow in the readme, legal/GDPR review, a hosted backend, and a consent record. See the roadmap in `CLAUDE.md`.

## Behaviour to revisit

- Block and hold are mutually exclusive. If merchants want both (block known rings, hold the rest), the settings model needs a third state.
- Hold only applies to coupons with "New customers only (Twinspot)" ticked. Orders without such a coupon are never held.
- Admin-blocked rings refuse every coupon in both block and hold mode. Only the shield being fully off (both toggles off) disables them.
- A gateway that calls `update_status('processing')` directly is reverted to on-hold (non-admin move to processing or completed on a `_twinspot_lite_held` order). Verified in a browser with COD (gateway completes it, the plugin puts it back on hold). Cancelled, failed and refunded moves are allowed. Auto-complete plugins are reverted too; integrators can opt out with the `twinspot_lite_keep_held` filter.
- Hold checks matches at order creation only, not when an order later reaches processing or completed.
- A held order is released when a user with `edit_shop_orders` changes its status away from on-hold; this clears `_twinspot_lite_held`, so later payment webhooks do not hold it again.

## Roadmap items not started

- Visual ring graph beyond the current connection map, auto-hold on suspicious orders by score, refund-abuse scoring.
- Optional hosted engine and Pro features, WooCommerce.com Marketplace, link to the Twinspot Shopify app.
- Device fingerprinting: out of scope until there is a consent toggle.
