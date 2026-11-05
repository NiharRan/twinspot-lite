# Twinspot on WooCommerce: How to Build It and Who You're Up Against (Late 2026)

**Yes, you can solve the same problem on WooCommerce. Build a thin, free WordPress.org plugin that collects signals and enforces decisions at checkout, and have it call the same graph backend your Shopify app uses. On WooCommerce the closest competitor is TrustLens, a free plugin that already does linked-account and coupon-abuse detection, so your edge has to be real graph clustering, cross-store intelligence and polish, not "we detect linked accounts."** Webhooks or the REST API on their own can't stop a coupon before the order exists, so you need code running inside the store. On both platforms, the market for affordable promo- and refund-abuse tools is new, thin and full of small indie apps. The big names (Signifyd, NoFraud/Wyllo, SEON) are built around payment fraud and chargebacks.

## TL;DR
- **Architecture:** Use a hybrid. A free WordPress.org plugin hooks into checkout: `woocommerce_coupon_is_valid` for coupon blocking, order holds and status changes, plus a device-fingerprint script. It sends hashed identifiers to your shared SaaS graph engine. WooCommerce webhooks and the REST API handle history backfill. WordPress.org explicitly allows plugins that act as an interface to a paid external service, as long as you disclose it and the user opts in.
- **Competitors:** On WooCommerce, TrustLens (free core, Pro from $79/yr) already fingerprints shipping address, billing address, phone, IP, payment method and user agent to link accounts. DevPress's Coupon Restrictions handles Gmail-alias, address and IP limits per coupon. OPMC Anti-Fraud ($139/yr, 3.7★ from 69 reviews) and FraudLabs Pro (free up to 500 queries/mo) are payment-fraud scorers. On Shopify, CustomerGenius ($9.99/mo), RefundSentry ($49/mo) and OverlayIQ (currently unlisted) go after promo and refund abuse. Shopify's own tools don't score refunds or returns.
- **Positioning:** Sell Twinspot as "fraud-ring detection for promos and refunds," priced for small shops. Lead with multi-hop graph clustering (A shares a phone with B, B shares a card with C), a visual ring view, and real-time blocking of first-order coupons. Launch WooCommerce through a WordPress.org freemium plugin first and add the WooCommerce.com Marketplace (70% to vendor) later. Shopify's 0% revenue share on your first $1M is the better money platform.

## Key Findings

### 1. What WooCommerce gives you (data and hooks)
- **Order data over REST/webhooks.** WooCommerce orders expose billing and shipping addresses, email and phone. They also carry `customer_ip_address` and `customer_user_agent`, which the REST API marks read-only. Webhook topics include `order.created`, `order.updated` and `order.deleted`, plus customer events. That covers most of your graph edges: address, email, phone, IP and a rough device signal.
- **Webhooks are fragile.** WooCommerce's REST API docs say that "after 5 consecutive failed deliveries (as defined by a non HTTP 2xx response code), the webhook is disabled and must be edited via the REST API to re-enable." The merchant docs add that 301/302 responses count as successful deliveries and that the threshold can be changed with the `woocommerce_max_webhook_delivery_failures` filter. A SaaS-only design that depends on webhooks will silently lose data on badly configured hosts. Build a scheduled REST reconciliation job alongside the webhooks.
- **Coupon blocking needs in-store code.** The filter `woocommerce_coupon_is_valid` is the final yes/no check before a coupon applies, and `woocommerce_coupon_error` customizes the message the shopper sees. Webhooks fire after the order exists, so they can't stop the coupon, only hold or cancel the order afterwards. Test both Classic and Block (Store API) checkout. TrustLens and Coupon Restrictions both advertise support for both, and merchants will expect the same from you.
- **Payment fingerprints are the hardest signal.** Developers who answer this question on their own blogs say the official Stripe gateway gives you no hook or filter for card details. To get card brand, last4 or Stripe's card `fingerprint`, you have to call the Stripe API with the stored PaymentIntent or customer ID. That needs the merchant's Stripe key or a Stripe Connect integration. TrustLens shows it can be done: it captures card brand on Stripe and WooPayments orders and ingests disputes automatically. Treat card fingerprint as a premium signal for stores on Stripe or WooPayments, and fall back to BIN plus last4 where a gateway exposes them. FraudLabs Pro's changelog shows it retrieves BIN numbers.
- **Email aliases.** Normalize Gmail dots and `+tags` yourself before hashing. This is now table stakes: Coupon Restrictions advertises that it "recognizes that john.doe@gmail.com, johndoe@gmail.com, and john+discount@gmail.com are the same person."
- **Guest checkout.** WooCommerce's native "new customer" check looks only at customer accounts unless the merchant turns on guest-order checking. Coupon Restrictions offers that option for exactly this reason. Key every identity on a normalized email hash, not on the WP user ID. TrustLens does this with a "site-specific HMAC-SHA256 email hash" so guests and registered users share one history.
- **HPOS.** Declare High-Performance Order Storage compatibility and read orders only through the `WC_Order` CRUD API, never through post meta. Competitors already advertise it: FraudLabs Pro added HPOS support in 2.20.0, and TrustLens and Coupon Restrictions declare it.

### 2. Distribution and money: WooCommerce vs. Shopify

| | WordPress.org (free/freemium) | WooCommerce.com Marketplace | Shopify App Store |
|---|---|---|---|
| Revenue share | 0% (you bill yourself, e.g. via Freemius) | Vendor keeps 70% | 0% on first $1M lifetime gross, then 15%. Since June 16, 2025 the $1M is a lifetime total counted from Jan 1, 2025 (per Luseri's 2026 calculator, citing Shopify's terms). Separate 2.9% processing fee on all billing; $19 one-time registration |
| SaaS allowed? | Yes. Guideline 6: "Software as a Service is permitted," even for paid services, if documented in the readme | Woo publishes a SaaS Billing API for marketplace products | Native: apps are SaaS |
| Key restrictions | No locked or trialware code inside the plugin (guideline 5). No external calls without explicit opt-in consent (guideline 7). A service that only validates license keys is banned | Business, code and UX review. Must share revenue. No upsell links outside Woo | Built for Shopify badge drives discovery; billing through Shopify |
| Discovery | Plugin search inside wp-admin; huge reach, low willingness to pay | Curated, higher-intent buyers; annual subscriptions are the norm (e.g. OPMC at $139/yr) | App Store search and categories; monthly subscriptions are the norm |

**What this means:** WooCommerce merchants usually find tools through WordPress.org search and expect a useful free tier, then pay once a year for "Pro." Shopify merchants expect a monthly plan billed through Shopify. With a SaaS backend, the WordPress.org free plugin can call your service, which guideline 6 allows. Any premium features should live server-side or in a separate add-on hosted outside WordPress.org. That fits Twinspot well, because the graph engine is the product.

### 3. Shopify vs. WooCommerce merchants: what changes for one shared backend
- **Self-hosted chaos.** Every WooCommerce store has a different host, caching layer, PHP version and set of checkout plugins. A checkout hook that blocks synchronously on your API must have a strict timeout and **fail open**, or you'll break checkouts and collect 1-star reviews. OPMC's reviews show the risk: one review says the plugin "interfered with real customer attempts to buy product," and 17% of its reviews are 1-star.
- **IP accuracy varies.** Behind Cloudflare or load balancers, the IP WooCommerce records may belong to the proxy. FraudLabs Pro had to add "real IP header detection" for this reason.
- **Price sensitivity.** WooCommerce competitors sell at $79–$139 per year. Shopify promo-abuse apps charge $9.99–$49+ per month. Plan for lower average revenue per user on WooCommerce.
- **Shared backend design.** Normalize both platforms into one internal schema: identity nodes (email hash, phone hash, address hash, IP, device ID, card fingerprint) and order/coupon/refund events. Keep a platform adapter layer for each (Shopify webhooks plus Functions; WooCommerce plugin plus REST). Keep the graph per merchant by default. Make any cross-store linking opt-in (see GDPR below).

### 4. GDPR for EU merchants
- **Legal basis.** GDPR Recital 47 says "the processing of personal data strictly necessary for the purposes of preventing fraud also constitutes a legitimate interest of the data controller." The EDPB's Guidelines 1/2024 stress that this rests on **strict necessity** and still needs a careful balancing test. Promo abuse probably fits as fraud prevention, but merchants should document a legitimate-interest assessment, and you should provide a template.
- **Roles.** The merchant is the controller and Twinspot is the processor. You need a DPA, a published sub-processor list and EU hosting as an option.
- **Data minimization.** Hash identifiers in the plugin with a per-store secret (HMAC) before sending, and store hashes rather than raw emails or addresses where you can. TrustLens says it pseudonymizes identifiers with keyed HMAC-SHA256 and supports WordPress's export and erasure tools. Match that.
- **Device fingerprinting** in the EU may also fall under ePrivacy rules on storing or accessing information on the user's device. Make the fingerprint script configurable, and let merchants tie it to their consent banner.
- **Cross-store network effects** (flagging a fraudster from store A at store B) carry the most legal risk, because you become a shared fraud database. TrustLens limits its "Store Trust Network" to a merchant's *own* stores on multi-site Pro plans. Start there too.
- **WordPress.org requires** opt-in before contacting external servers, plus documentation of collected data in the readme.

## Competitor Comparison

| Tool | Platform | Target size | Pricing (late 2026) | Core approach | Linked accounts / promo / refund? | Ratings |
|---|---|---|---|---|---|---|
| **TrustLens** (Webstepper) | WooCommerce | Small–mid | Free core; Pro $79/yr (1 site), $149 (5), $299 (30) | Behavioral trust score 0–100, 8 modules; free version never auto-blocks | **Yes**: fingerprints shipping, billing, phone, IP, payment method, user agent; coupon farming; return abuse | Vendor claims 5.0 from 3 reviews, 800+ downloads (not WP.org-verified) |
| **Coupon Restrictions** (DevPress) | WooCommerce Marketplace | Small | $49/yr (1-year plan) on the WooCommerce Marketplace | Per-coupon rules | Email alias, shipping-address and IP limits; no graph | 3★ (4 reviews) on the WooCommerce Marketplace. Nov 27, 2025 1★ review: "Doesn't really work and DevPress has not responded to my support ticket" |
| **Anti-Fraud** (OPMC) | WooCommerce Marketplace | Small–mid | $139/yr | Rules-based risk score; hold/cancel | Same-IP multiple orders; email-domain flags | 3.7★ (69 reviews), 4K+ installs, "Excellence verified" |
| **FraudLabs Pro** | WooCommerce (+21 carts), Shopify | Small–mid | Free 500 queries/mo; $29.95 (1,500); $99.95 (5,000); $249.95 (25,000) | Payment-fraud scoring, IP geolocation, velocity, blacklists, merchant network | Velocity only; not promo-focused | WP.org 4.2★, 1,000+ installs |
| **CustomerGenius** | Shopify | Small | From $9.99/mo (to $29.99) | Rules; auto-cancel and refund | **Promo abuse**: multiple emails, same shipping address | 5.0★ (6) |
| **OverlayIQ** | Shopify | Small | n/a | "Identity stitching" via Shopify Functions checkout validation | **Promo abuse**: alias emails, reformatted addresses, device | Listing says "not currently available" on the App Store |
| **RefundSentry** | Shopify | Small–mid | $49/mo (1,000 orders) → $299/mo (12,000) | Post-checkout refund/chargeback scoring | **Refund abuse**; links accounts by address, phone, IP | 5.0★ (2) |
| **Blockify** | Shopify | Small | Free plan + paid tiers | IP, bot and country blocker | Blocklists, not linking | 4.9★ (1,851), Built for Shopify |
| **NoFraud → Wyllo** (rebranded March 2026 after acquiring Yofi) | Shopify, others | Mid | Free screening; guarantee tiers $250/$350/$450/mo (secondary source) | ML + human review, chargeback guarantee | Payment fraud; some "policy abuse" | 4.8★ (152 reviews), listed as Wyllo (per Chargeflow's 2026 comparison) |
| **Signifyd** | Shopify, Adobe, BigCommerce | Mid–enterprise | Free install, charges after 14-day trial; percentage of approved GMV | ML + chargeback guarantee | Payment fraud | 4.4★ (92) on Shopify; 16% 1★ |
| **SEON** | Shopify (new app June 2026), API | Mid–enterprise | Starter €699/mo (Capterra); 30-day trial | Device fingerprinting, email/phone/IP enrichment, rules + ML | Multi-account detection; return abuse claimed | Legacy app 4.7★ (41) |
| **Fingerprint** | Any (JS/SDK) | Developer building block | Free 1,000 calls/mo; Pro Plus $99/mo for 20,000 + $4/1,000 | Device identification | A signal source, not a product for merchants | n/a |
| **Shopify built-ins** (Fraud Analysis, Fraud Control, Protect) | Shopify | All | Free | Risk indicators; checkout rules; Protect reimburses fraud chargebacks on eligible **US Shop Pay** orders only | **None score refunds or returns**; "limit one per customer" keys on email/phone | n/a |

*I did not verify current pricing or ratings for Riskified, ClearSale, Sift, MaxMind minFraud, Sardine or Stytch in this pass. They are mostly enterprise or API payment-fraud tools and don't target your niche directly.*

## Gaps and Where Twinspot Can Stand Out

1. **Nobody markets true graph or ring detection to small shops.** TrustLens, RefundSentry and CustomerGenius all do *pairwise* signal matching ("these accounts share an address"). None advertises transitive clustering, multi-hop rings or a visual ring map. Make "see the whole ring" your demo moment.
2. **Promo, refund and checkout enforcement are split across tools.** On Shopify, CustomerGenius does promos, RefundSentry does refunds, and OverlayIQ (blocking at checkout) is currently unlisted. One app covering first-order coupon blocking *plus* refund-abuse scoring at roughly $15–39/mo has an open slot.
3. **The enterprise tools are mispriced for small merchants.** SEON's Starter tier is €699/mo, and Signifyd's guarantee pricing scales with GMV. Shopify's free tools stop at payment fraud. Shopify's own discount limit is per email or phone identifier, which is exactly the gap you exploit.
4. **On WooCommerce, the free incumbent sets the bar.** TrustLens's free tier is generous. Beat it on: (a) cross-platform (one dashboard for merchants running both platforms), (b) graph depth and explainability, (c) automatic real-time blocking of abusive coupons in the free tier, which TrustLens keeps for Pro, and (d) a hosted engine that doesn't add database load to cheap shared hosts.

## Recommendations

**WooCommerce architecture**
1. **Plugin (free, WordPress.org).** Responsibilities:
   - Run the HMAC hashing and email normalization locally.
   - Add a lightweight fingerprint script, behind a consent toggle.
   - Hook `woocommerce_coupon_is_valid` and set a 300–500 ms timeout, failing open.
   - Use order hooks to add notes, tags and "on-hold" status.
   - Declare HPOS compatibility and support Block checkout.
   - Ship a WP privacy exporter and eraser.
2. **SaaS backend (shared with Shopify).** Use one identity graph per merchant with platform adapters. Ingest order, refund and coupon events. Run a nightly REST reconciliation to cover dead webhooks.
3. **Payment signals.** Offer an optional Stripe or WooPayments connector to pull card fingerprint and brand for stronger edges. Don't make it a requirement.
4. **Backfill.** Import 12 months of orders at install so merchants see rings on day one. RefundSentry and TrustLens both lead with history import.

**Distribution and pricing**
- **Phase 1:** WordPress.org freemium. Free tier: graph view plus manual review up to ~200 orders/mo. Paid tier at about $9–29/mo or $79–199/yr for auto-blocking, refund-abuse scoring and higher volume. Annual plans fit WooCommerce buying habits.
- **Phase 2:** Apply to the WooCommerce.com Marketplace once you have reviews. Use Woo's SaaS Billing API. Price there to cover the 30% cut.
- **Shopify stays your revenue engine:** 0% revenue share until $1M lifetime. Use Shopify Functions for checkout validation and go for the Built for Shopify badge.

**Positioning line:** "Twinspot finds the one person behind five accounts and stops the sixth welcome discount at checkout, for the price of one abused coupon a month."

## Caveats
- Some ratings and prices come from third-party aggregators or vendor sites, not live store listings. That applies to NoFraud/Wyllo's guarantee tiers, TrustLens's review count and SEON's tiers. Check them before quoting publicly.
- OverlayIQ's listing is currently unavailable, so its status (paused or discontinued) is unknown.
- Coupon Restrictions' price and the WordPress.org metadata for TrustLens (active installs, rating) were not confirmed.
- GDPR points here are general guidance, not legal advice. Device fingerprinting and cross-merchant data sharing in particular need review by an EU privacy lawyer.
