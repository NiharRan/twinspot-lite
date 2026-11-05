=== Twinspot Lite ===
Contributors: nihar007
Tags: woocommerce, coupons, fraud, abuse, checkout
Requires at least: 6.6
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Spot rings of linked accounts and block repeat welcome coupons at WooCommerce checkout. Runs on your store; no data leaves your site.

== Description ==

Some shoppers create many accounts to claim your first-order discount again and again. Twinspot Lite links orders that share an email, phone number or address, even through other orders, and lets you stop the repeat coupon at checkout.

* **New customers only coupons.** Tick "New customers only (Twinspot)" on a coupon. If the shopper's email, phone or address matches an earlier order, the coupon is refused. Gmail dots and `+tags` are treated as the same address.
* **Rings.** WooCommerce > Twinspot lists groups of orders linked to each other. A shares a phone with B and B shares an address with C, so all three show as one ring.
* **Order notes.** An order that matches earlier orders gets a note naming them, with links to those orders and to the ring. When a coupon is refused, the order the shopper then places gets a "Twinspot System Alert" note naming the coupon.
* **Hold for review.** Under Plugin Settings, switch from refusing the coupon to holding the order. The shopper keeps the discount, but the order is set to On hold until you release it by changing its status. Rings you block by hand stay refused.
* **Import past orders.** One click indexes the last 12 months so rings show up on day one.
* Works with classic and block checkout, and with High-Performance Order Storage.
* If details are missing the coupon is allowed, so a checkout is never broken.

= Privacy =

Twinspot Lite stores one-way HMAC-SHA256 hashes of each order's billing email, phone, address and IP address in a table on your own site. The hashes cannot be reversed. Nothing is sent to any external server. Data is removed when an order is deleted or cancelled, through the WordPress personal data export and erase tools, and when the plugin is deleted. The plugin adds suggested text to your site's privacy policy page.

== Installation ==

1. Install and activate WooCommerce.
2. Upload the plugin and activate it.
3. Go to WooCommerce > Twinspot and click "Import past orders".
4. Edit a coupon, open the Usage restriction tab and tick "New customers only (Twinspot)".

== Frequently Asked Questions ==

= Does it send my customers' data anywhere? =

No. Everything stays in your own database.

= Can it block shoppers who use a different name? =

Yes, if they reuse the same email (including Gmail aliases), phone number or street address plus postcode.

= What does hold for review do? =

Instead of refusing a "new customers only" coupon, the order is placed with the discount and set to On hold, even if a payment gateway reports it paid. Change the status to release it. Blocking and holding are alternatives: only one is active at a time.

= Why is IP address not used to block? =

Many honest customers share an IP address (offices, mobile networks), so it is stored but not used for matching.

== Source code and third-party libraries ==

The admin screen is a React app. The files in `build/` are compiled from the readable source in `src/`, available at https://github.com/NiharRan/twinspot-lite. To rebuild: run `npm ci && npm run build`.

The build bundles these libraries, all GPL-compatible: @radix-ui/react-dialog, @xyflow/react, motion, clsx and tailwind-merge (MIT); d3-force and lucide-react (ISC); class-variance-authority (Apache-2.0). Their licenses are in the repository's `package-lock.json` and in each package on npm.

== Changelog ==

= 0.1.0 =
* First release.
