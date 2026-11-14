# Admin UI design

How the Twinspot Lite admin screen looks, how it is built, and how it stays within WordPress.org rules. Visual source: the Stitch exports in `design/` (`screen.png`, `code.html` per screen, tokens in `design/calm_merchant_guard/DESIGN.md`). The Stitch HTML is reference only; it uses the Tailwind CDN and Google fonts, which the plugin must not ship.

## Stack

| Piece | Choice | Why |
|---|---|---|
| Language | TypeScript (strict), checked with `npm run typecheck` | Types in `src/types.ts` mirror the REST responses. `wp-scripts` compiles `.ts`/`.tsx` through Babel, so the build itself does not type check. |
| UI framework | React 18 via `@wordpress/element` | WordPress already ships React; bundling our own breaks guideline 13. `@wordpress/scripts` maps `react`, `react-dom` and `react/jsx-runtime` to WordPress's copy. |
| Components | shadcn/ui style, hand-written in `src/components/ui/` (Button, Card, Badge, Input, Select) | shadcn is copy-paste code on Tailwind, `class-variance-authority`, `clsx` and `tailwind-merge`. Only the pieces we use are copied, so no CLI and no unused Radix. Add Radix primitives only when a screen needs one (dialog, popover). |
| Styling | Tailwind CSS 3, no preflight, `important: '#twinspot-root'` | Every utility is scoped to our root, so wp-admin and other plugins are untouched. `src/style.css` adds a tiny scoped base in place of the reset. |
| Icons | `lucide-react` (tree-shaken inline SVG) | Local, no icon font, no CDN. |
| Motion | `motion/react` inside `MotionConfig reducedMotion="user"` | Short fades and staggered rows; respects the OS reduced-motion setting. |
| Data | `@wordpress/api-fetch` to our REST routes | WordPress adds the nonce and root URL. |
| Build | `npm run build` (`wp-scripts`, webpack) writes `build/` | `build/` is git-ignored and created by `scripts/build-zip.sh` for releases. |

Bundle size: about 181 KB JS and 10 KB CSS.

## Tokens

Defined in `tailwind.config.js` (names in brackets are the Tailwind class names).

- Primary verdigris `#0F6E5E` [`primary`], hover `#0A5A4D`, pressed `#084A40`, soft `#F1F6F4`, tint `#DCEBE6`. Never WooCommerce purple.
- Accent brass `#B7791F` [`accent`], text `#7A4E0A` [`accent-ink`], soft `#FBF4E4`, line `#E6D2AE`. Warnings and size-4 rings.
- Danger oxblood `#9B2C2C` [`danger`], soft `#FAEEEE`, line `#E4BDBD`. Rings of 5 or more, errors.
- Success `#1F8A4C` [`success`], soft `#EDF6F0`, line `#BFDDCB`.
- Neutrals: ink `#132120`, muted `#55605E`, line `#DCE2DF`, canvas `#F2F4F3`, quiet fill `#EAEFED` [`info`].
- Type: Schibsted Grotesk Variable (OFL, from `@fontsource-variable/schibsted-grotesk`, declared in `src/style.css` so the woff2 files ship in `build/fonts/`), system stack as fallback. 14px body, tabular numbers everywhere, 42px sentence headline on Overview, 20px section headings.
- Shape by role: 14px cards with a 1px border and no shadow, 10px buttons and inputs, 6px chips. Sections sit on the page background; only lists and forms get a card.
- Overview: the month as one sentence (numbers from the Activity endpoint), a ruled row of figures, ring rows that draw the ring (one node per order), and a timeline of the last 5 events. No stat cards.

## Screens

| Screen | Status | Notes |
|---|---|---|
| Overview: stat cards, search and filters, rings table, pagination | Built | `src/screens/overview.tsx`. Stats are rings found, orders in rings and coupons blocked in the last 30 days. Table shows ring size badge, linked-by chips, order links, total spent and last order. |
| Empty / first run, import success banner | Built | Same file. Shown when no rings exist and no filter is active. |
| Ring detail with connection map | Planned | Design in `design/twinspot_ring_detail_connection_map`. Needs a `/rings/{id}` endpoint and an SVG map (no chart library). The "View ring" link from the mockup is left out until then. |
| Coupon checkbox and order note | Stays PHP | They live inside WooCommerce's own coupon and order screens (`twinspot-lite.php`). |
| Shopper checkout notice | Stays PHP | A coupon error string handled by WooCommerce; no plugin CSS needed. |
| Settings, tabs (Rules, Activity log), "Shield Active" badge, "Test with sample data" | Not built | Not backed by any feature yet. Add them with their feature, not before. |

## REST API

Namespace `twinspot-lite/v1`, all routes require `manage_woocommerce`.

- `GET /overview?page&per_page&min_size&link&search` returns `stats`, `rings`, `total`, `total_pages`. `search` takes an order number or an email (matched through its hash). `link` is `email`, `phone` or `address`.
- `POST /sample` adds 3 fake linked orders (ids from 4,000,000,000, never real WooCommerce orders); `DELETE /sample` removes them.
- `POST /import` indexes the newest 1000 orders from the last 12 months and returns `{ imported, seconds }`.

Limits: rings are computed in PHP over every indexed order on each request. Fine to about 50k orders; move to stored ring ids before that.

## WordPress.org notes for the build

- Ship `build/` plus the readable source (`src/`, `package.json`, `tailwind.config.js`, `postcss.config.js`). The readme must link the public repository and say `npm ci && npm run build` (still to add once the repo URL exists).
- No remote scripts, styles or fonts. Do not add any.
- Requires WordPress 6.6 or later (the `react-jsx-runtime` script handle).
- Strings use `__()` from `@wordpress/i18n` with text domain `twinspot-lite`; `wp_set_script_translations` is registered.

## Conventions

- New component: copy the shadcn pattern into `src/components/ui/`, using the tokens above, never raw hex in screens.
- New screen: one `.tsx` file in `src/screens/`, text through `__()`, motion limited to opacity and small translate.
- Keep copy plain: "ring", "linked orders". Avoid fraud jargon. Never show raw customer details beyond what WooCommerce already shows on its order screen.
- No upgrade banners or nags (guideline 11).
