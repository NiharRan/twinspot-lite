# WordPress.org plugin rules (summary)

Source: developer.wordpress.org/plugins, read 2026-11-05. Recheck the guidelines before each submission.

- GPLv2-or-later; license in both header and readme and they must match. No obfuscated code. Ship readable source.
- Main file name = folder = slug (`twinspot-lite`). `Stable tag` in readme.txt must equal `Version` in the main file; never `trunk`. Bump the version every release.
- Prefix every function, class, constant, option, meta key and global with 4+ characters. No names starting with `__`, `wp_` or a single `_`.
- Security: sanitize input, validate, escape late (`esc_html`, `esc_attr`, `esc_url`), nonces and `current_user_can` on admin actions, `$wpdb->prepare()` on all queries. Never process whole `$_POST`. `isset` plus `sanitize_text_field( wp_unslash( ... ) )` before use. No HEREDOC or short tags. Direct-access guard in every PHP file that runs code.
- No locked features or trialware in the plugin code (guideline 5). Paid value must be real server-side functionality or a separate add-on hosted outside WordPress.org.
- SaaS is allowed (guideline 6) only if it is a real service. Any call to our server needs explicit opt-in, and the readme must document what is sent, why, and link the service's terms and privacy policy (guidelines 6, 7). No update checkers, no remote JS/CSS, no CDNs except fonts (guideline 8). Use the WordPress HTTP API, not curl.
- No dashboard nags: upgrade prompts only on our own settings page, notices dismissible (guideline 11). Credit links must be opt-in (guideline 10).
- Do not bundle libraries WordPress already ships. Use `wp_enqueue_script` / `wp_enqueue_style` only.
- Do not change other plugins' activation state. WooCommerce dependency uses the `Requires Plugins: woocommerce` header.
- Uninstall: cleanup in `uninstall.php` (guarded by `WP_UNINSTALL_PLUGIN`), not on deactivation. Also remove post meta we add (`_ts_noted`, coupon meta) once renamed.
- readme.txt: up to 5 tags, no competitor names; short description 150 characters max, no markup; must pass the readme validator. Tested-up-to is the major version only.
- Assets live in the SVN `assets/` folder (we keep them in `.wordpress-org/`): `banner-772x250.png`, `banner-1544x500.png`, `icon-128x128.png`, `icon-256x256.png`, `screenshot-N.png` matching the readme screenshot list.
- Names must not start with a trademark ("WooCommerce ..." is not allowed as a first term).

