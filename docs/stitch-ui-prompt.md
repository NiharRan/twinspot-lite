# Google Stitch prompt: Twinspot Lite UI

Paste the prompt below into Stitch. Generate the screens one at a time (listed in the prompt) so each stays consistent. Screens marked "later" are not built yet; generate them only for direction.

---

Design the UI for "Twinspot Lite", a WordPress/WooCommerce plugin that finds rings of linked customer accounts and blocks repeat welcome coupons at checkout. The people using it are small-shop owners, not fraud analysts, so every screen must be calm, clear and plain-spoken.

**Look and feel**
The admin screens must feel native inside the WooCommerce admin: the same structure and density as WooCommerce's own pages (white cards on a light grey page background, a top page title with a short helper sentence, clean data tables with row hover, small status pills, a primary button at top right, left WordPress admin menu visible). It should look modern and slightly more refined than stock WooCommerce: more breathing room, 12px card radius, soft 1px borders, very light shadows, no heavy gradients. Do NOT use WooCommerce purple anywhere.

**Color palette (use exactly)**
- Primary: deep teal #0F766E (buttons, links, active tabs). Hover #115E59. Tint for backgrounds #CCFBF1 / #F0FDFA.
- Accent: warm amber #F59E0B, only for "attention" highlights and the ring graph's key node.
- Danger: #DC2626 with tint #FEF2F2 (blocked, high-risk ring).
- Success: #16A34A with tint #F0FDF4.
- Page background #F6F7F7 (matches wp-admin), card white #FFFFFF, borders #E2E8F0, body text #1E293B, muted text #64748B.
- Font: system UI stack (-apple-system, "Segoe UI", Roboto), 14px body, 24px page titles, 600 weight for headings. Tabular numbers in tables.

**Screens to design (desktop 1440px wide, inside the wp-admin frame, WooCommerce > Twinspot)**
1. **Overview / Rings list (main screen).** Header: "Twinspot" title, helper line "Groups of orders linked by the same email, phone or address", buttons "Import past orders" (secondary) and an "Orders indexed: 1,284" stat chip. Row of 3 stat cards: Rings found, Orders in rings, Coupons blocked (last 30 days). Below, a table: Ring size (pill, larger rings in amber/red), Linked by (small icons for email, phone, address), Orders (comma-separated #order links), Total spent, Last order date, a "View ring" link. Include a filter bar (search by order number or email, ring size dropdown). Show 8 realistic rows.
2. **Ring detail.** Back link, title "Ring of 5 orders". Left, a node-link diagram: circles for orders, small icon nodes for the shared email/phone/address, lines connecting them, the most-connected node in amber. Right, a side card listing the orders with status pills, totals, dates and a "Shared details" list (shown as "Same phone", "Same address"; never show raw hashes). A subtle note: "Details are stored as one-way hashes." (Later screen, but design it now for direction.)
3. **Empty and first-run state.** Friendly illustration (simple line art in teal), "No rings found yet", explanation, and a primary button "Import last 12 months of orders". Also a progress/success banner variant: "Imported 1,000 orders".
4. **Coupon edit panel.** The standard WooCommerce coupon "Usage restriction" tab with one added checkbox row styled like the surrounding WooCommerce fields: label "New customers only (Twinspot)" with helper text "Block if the shopper's email, phone or address matches any earlier order, even under another account or Gmail alias." Show how it sits among the other fields.
5. **Order screen note.** A WooCommerce order-notes sidebar entry reading "Twinspot: shares email, phone or address with order(s) #1042, #1051", styled as a system note with a small teal marker.
6. **Shopper-facing checkout (frontend).** A standard WooCommerce checkout page (classic and block variants side by side) where the coupon field shows an inline error: "Sorry, this coupon is only for first-time customers." Keep it polite and neutral, not accusatory. Style the notice to be simple and accessible: red-tinted bar, clear icon, readable contrast, no mention of fraud or accounts. The checkout itself stays in the store's own theme; only the notice is ours.
7. **Settings (later).** One card: toggle "Block repeat coupons automatically", toggle "Hold suspicious orders for review", a consent toggle for the optional cloud service with a short plain-language explanation of what is sent, and a link to the privacy policy.

**Components to show once as a mini style sheet:** primary and secondary buttons, text link, status pills (Linked, Blocked, Clean), stat card, table row, inline notice (info, success, error), toggle, checkbox, ring-size badge, order link chip.

**Rules**
- Accessible: 4.5:1 text contrast, visible focus rings in teal, no color-only meaning (always pair with text or icon).
- Plain language, sentence case, no jargon like "heuristic" or "graph". Say "ring" and "linked orders".
- Never display raw emails, phones or addresses of customers beyond what WooCommerce already shows on its order screen.
- Keep every screen free of ads, upgrade banners and nags.
- Output clean, componentized layout so it can be rebuilt with WordPress admin CSS classes (wrap, widefat table, notice) and WooCommerce conventions.
