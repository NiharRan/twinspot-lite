# Twinspot Lite

WooCommerce plugin that finds the one person behind many accounts ("rings") and blocks repeat welcome coupons at checkout. Goal: ship to WordPress.org as a free plugin, see if it makes money, and only then link it to the Twinspot Shopify app and a shared SaaS graph backend.

Background research: `docs/woocommerce-research.md` (competitors, pricing, GDPR, architecture).

## Current state (v0.1.0, local-only MVP)

- No external server calls. Everything runs inside the store.
- `twinspot-lite.php`: header, autoloader, activation hook, boot. Nothing else.
- PHP layout (namespace `Twinspot_Lite`, autoloaded from `includes/<Sub>/<Class>.php`): `Plugin` wires everything; `Module` interface (`register()` adds hooks); `Core/` pure logic (`Normalize`, `Rings`); `Data/` storage (`Edges` = all SQL, `Fingerprinter` = secret + hashing, `BlockLog`); `Features/` one hook module each (`OrderTracker`, `CouponGuard`, `Privacy`, `Compat`, plus `Importer` service); `Rest/` (`Routes`, `Overview`); `Admin/Page`. To add a feature: new class implementing `Module`, add it to `Plugin::modules()` (or the `twinspot_lite_modules` filter). Constructor injection, no globals except `$wpdb` inside `Data/`.
- Admin UI: React app in `src/` (shadcn-style components, Tailwind scoped to `#twinspot-root`, lucide icons, motion), built with `npm run build` into git-ignored `build/`. REST routes in `includes/Rest/`, mount point in `includes/Admin/Page.php`. Details: `docs/admin-ui-design.md`. Visual reference: `design/`.
- `includes/Core/`: pure PHP (no WordPress calls): email/phone/address normalization, HMAC hashing, ring detection (union-find over shared hashes). Tested by `php tests/core-test.php`.
- `uninstall.php`: drops table and removes the secret.
- Data: table `{prefix}twinspot_lite_edges` (order_id, email_h, phone_h, addr_h, ip_h). All values are HMAC-SHA256 hashes keyed by the `twinspot_lite_secret` option. IP is stored but never used to match (shared networks).
- Coupon rule: coupon checkbox "New customers only (Twinspot)" (meta `_twinspot_lite_first_order_only`) throws an exception in `woocommerce_coupon_is_valid` if email, phone or address matches an earlier order. Missing details match nothing, so it fails open.
- Orders are recorded on `woocommerce_checkout_order_created` (classic) and `woocommerce_store_api_checkout_order_processed` (blocks). Cancelled and failed orders are removed from the table.

## WordPress.org readiness

Run `npm ci && npm run build` (type check: `npm run typecheck`) before testing the admin screen or zipping. Rules summary: `docs/wordpress-org-rules.md`. Done in v0.1.0: `twinspot_lite_` prefix everywhere, `readme.txt`, full header, translatable strings (text domain `twinspot-lite`), `ABSPATH` guards, privacy exporter, eraser and policy text, `.distignore` plus `scripts/build-zip.sh` (writes `dist/twinspot-lite.zip`).

Still open before submitting:
1. Confirm `Contributors` in `readme.txt` is the real WordPress.org username and `Tested up to` is the current WordPress major version.
2. Add `.wordpress-org/` assets: `banner-772x250.png`, `banner-1544x500.png`, `icon-128x128.png`, `icon-256x256.png`, screenshots (then add a Screenshots section to the readme).
3. Run the Plugin Check plugin and PHPCS (WordPress standards) in a local WooCommerce site; nothing has been run in WooCommerce yet.
4. Verify block checkout provides billing details at coupon validation (the check fails open if not).
5. Import handles 1000 orders per request and the rings page loads all rows (fine to about 50k orders).

## Design decisions to keep

- Hash everything in the plugin with a per-site secret; never store raw email, phone or address in our table.
- Normalize before hashing: Gmail dots and `+tags` removed, other domains strip `+tag` only, phone is the last 10 digits, address needs both line 1 and postcode.
- Key on email hash, not WP user ID, so guests and registered users share one history.
- Checkout hooks must be fast and fail open. Never break a checkout.
- Read orders only through `WC_Order` CRUD (HPOS-safe), never post meta directly.
- Cross-store sharing is out of scope for v1 and needs opt-in plus legal review (GDPR).
- Device fingerprinting is out of scope until there is a consent toggle.

## Roadmap (only if the free plugin gets traction)

1. WordPress.org-ready v1.0 (fix the gaps above, privacy tools, readme, assets, Plugin Check clean).
2. Visual ring graph, auto-hold on suspicious orders, refund-abuse scoring.
3. Optional hosted engine (opt-in, documented in readme) and Pro features living server-side, billed outside WordPress.org (Freemius or similar).
4. WooCommerce.com Marketplace (70% to vendor) after reviews.
5. Link to the Twinspot Shopify app through a shared identity graph, per-merchant by default.

## Working rules

- Smallest change that fully works. No new dependencies without a reason.
- Keep pure logic in `includes/Core/` with a test in `tests/core-test.php`; run `php tests/core-test.php` after changing it.
- Before release: run `php -l` on every file, the official Plugin Check plugin and WordPress coding standards (PHPCS) in a local WooCommerce site (Herd), and test both classic and block checkout.
