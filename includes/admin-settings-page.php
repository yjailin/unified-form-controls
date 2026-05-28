<?php
/**
 * Admin page — Appearance > Form Controls.
 *
 * Chrome-less card around an iframe that loads `/?uf_showcase=1&uf_embed=1`.
 * The card styling mirrors the WP Fonts page (`font-library.php`): a white
 * rounded surface with a thin header bar at the top (title only) and the
 * content area below filling the rest of the available admin viewport.
 * The showcase template itself recognizes the `uf_embed` flag and
 * suppresses the WP admin bar + its own page title / lede paragraph, so
 * the iframe is purely the form-controls preview surface.
 *
 * (A standalone React settings UI above the iframe used to live here —
 * its mount script + enqueue have been removed. The companion
 * `js/admin-settings.js` file is left in place but no longer enqueued,
 * so the controls can be brought back later without rewriting the
 * component from scratch.)
 *
 * @package UnifiedFormControls
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'admin_menu',
	function () {
		add_theme_page(
			__( 'Form controls', 'unified-form-controls' ),
			__( 'Form controls', 'unified-form-controls' ),
			'manage_options',
			'unified-form-controls',
			function () {
				$preview_url = add_query_arg(
					array(
						'uf_showcase' => '1',
						'uf_embed'    => '1',
					),
					home_url( '/' )
				);
				?>
				<div class="wrap ufc-admin-wrap">
					<div class="ufc-admin-card">
						<div class="ufc-admin-card__body">
							<iframe
								id="ufc-admin-preview-iframe"
								src="<?php echo esc_url( $preview_url ); ?>"
								title="<?php esc_attr_e( 'Form controls preview', 'unified-form-controls' ); ?>"
							></iframe>
						</div>
					</div>
				</div>
				<style>
					/* Card layout that mirrors `font-library.php` exactly:
					     - White rounded surface with 8px gap on right + bottom
					     - 65px header bar (16px top + 32px title line + 16px
					       bottom + 1px border) — matches Fonts page measurements
					     - Body fills remaining height with the iframe
					   The wrap is a flex column so the card grows to the full
					   admin viewport. The body is a nested flex container so
					   `iframe { flex: 1 }` resolves reliably (the Chrome quirk
					   where `height: 100%` on an iframe inside a flex parent
					   doesn't always work). */

					/* Match the Fonts page chrome:
					     - Hide #wpfooter so the card can extend to within 8px
					       of the viewport bottom.
					     - Zero #wpcontent's inline-start padding so the card's
					       left edge sits flush against the admin sidebar (no
					       20px gutter — WP's default).
					     - Paint #wpwrap, body, AND html with the same dark
					       surround (#1e1e1e) so the 8px gaps on the right
					       and bottom show dark, AND any browser overscroll
					       above the top of the card shows the same dark
					       color instead of the WP admin default light grey
					       (#f0f0f0) bleeding through. */
					#wpfooter { display: none; }
					#wpcontent { padding-inline-start: 0; }
					/* WP default is `#wpbody-content { padding-bottom: 65px }`
					   to leave room for the (now-hidden) #wpfooter. With it
					   on, #wpwrap ends up 65px taller than the viewport, so
					   scrolling while hovering the admin menu (which can
					   trigger the body's overflow scroll) reveals a band of
					   dark #wpwrap below the card. Zeroing it lets the card
					   sit exactly inside the viewport — same fix the Fonts
					   admin page uses. */
					#wpbody-content { padding-bottom: 0; }
					/* Paint the dark surround on every WP-admin element that
					   could ever show through behind/below the card or the
					   admin menu. Covers four cases:
					     1. The 8px gap around the card (right + bottom).
					     2. Body overflow on sites with a tall admin menu
					        (when the menu's hover-scroll propagates to the
					        body, any band that scrolls into view stays
					        dark instead of revealing WP's light defaults).
					     3. The empty space below the admin menu on short
					        menus — #adminmenuback is the element WP draws
					        behind the menu, and its default extends only
					        as far as the menu's items; below that, wpwrap
					        used to show through. Painting them both dark
					        eliminates any visible boundary.
					     4. The admin menu's own background on Calypso
					        proxied sites where it can render lighter than
					        a standard install. */
					html, body, #wpwrap, #wpcontent, #wpbody, #wpbody-content,
					#adminmenuback, #adminmenuwrap, #adminmenu,
					.wrap.ufc-admin-wrap { background: #1e1e1e !important; }

					.ufc-admin-wrap {
						/* Override WP .wrap defaults (10px 20px 0 2px) to
						   the Fonts page's tighter edge spacing. */
						margin: 0 8px 8px 0;
						display: flex;
						flex-direction: column;
						/* 32px = WP admin bar, 8px = bottom gap to viewport edge */
						min-height: calc(100vh - 32px - 8px);
					}
					.ufc-admin-card {
						flex: 1 1 auto;
						display: flex;
						flex-direction: column;
						background: #fff;
						border-radius: 8px;
						overflow: hidden;
					}
					.ufc-admin-card__header {
						padding: 16px 24px;
						border-bottom: 1px solid #e7e7e7;
					}
					/* Specificity bump (`.wrap .…__title`) so we beat the
					   default `.wrap h1` rule from wp-admin's stylesheet,
					   which would otherwise apply font-size: 23px and its
					   own padding. line-height: 32px matches the Fonts page
					   measurement (gives the header its exact 65px height). */
					.wrap .ufc-admin-card__title {
						font-size: 20px;
						font-weight: 500;
						margin: 0;
						padding: 0;
						line-height: 32px;
						color: #1e1e1e;
					}
					.ufc-admin-card__body {
						flex: 1 1 auto;
						display: flex;
						min-height: 500px;
					}
					.ufc-admin-card__body > iframe {
						flex: 1 1 auto;
						min-width: 0;
						min-height: 0;
						width: 100%;
						border: 0;
						display: block;
					}

					/* ─── WordPress.com-specific accommodation ───────────────
					   Layout bugs (empty space above the card, gap below the
					   left sidebar) appear on WP.com but not on vanilla WP.
					   The cause is elements WP.com injects into wp-admin that
					   a standard install doesn't have. Explicit suppressions
					   below; a JS measurer further down handles anything
					   else by recomputing the card height live. */

					/* WP-standard admin notices that can render ABOVE our
					   card inside #wpbody-content. On WP.com these are more
					   frequent (Jetpack / WooCommerce / Atomic banners). Hide
					   any that would push the card down on this admin page.
					   Scoped via #wpbody-content > … so we only kill notices
					   ABOVE our .wrap, not any rendered inside the iframe. */
					#wpbody-content > .notice,
					#wpbody-content > .update-nag,
					#wpbody-content > #message,
					#wpbody-content > .updated,
					#wpbody-content > .error,
					#wpbody-content > .wrap > .notice:first-child,
					#wpbody-content > .wrap > .update-nag:first-child {
						display: none !important;
					}

					/* WP.com Calypso proxy occasionally adds a `wpcom-notice`
					   block or a `.calypsoify`-prefixed bar above .wrap. Hide
					   any of those too — same intent: keep the card flush. */
					#wpbody-content > [class*="wpcom-"],
					#wpbody-content > [id^="wpcom-"],
					#wpbody-content > .calypsoify {
						display: none !important;
					}

					/* WP.com sometimes leaves stray `<br>` and whitespace
					   text nodes inside #wpbody-content above .wrap — they
					   collapse to a few pixels but stack up. Strip top
					   padding/margin to absorb. */
					#wpbody-content { padding-top: 0 !important; margin-top: 0 !important; }
					#wpbody { padding-top: 0 !important; }

					/* Belt-and-suspenders: ensure #wpwrap and #adminmenuback
					   extend to at least full viewport height, so the area
					   below the admin menu is always painted dark (no light
					   wp-admin default bleeds through if the menu is shorter
					   than the viewport). */
					#wpwrap, #adminmenuback { min-height: 100vh !important; }
				</style>
				<script>
				/* Two responsibilities for this inline script:

				   1. Reclaim the WordPress admin bar's 32px reservation
				      WHEN the bar isn't actually being rendered.

				      WordPress core sets `html.wp-toolbar { padding-top:
				      32px }` to reserve space for the fixed `#wpadminbar`
				      at the top of the viewport. On WordPress.com's
				      Calypso path (where wp-admin loads inside a Calypso
				      iframe), Calypso hides `#wpadminbar` via its own
				      CSS but doesn't undo the 32px reservation — leaving
				      an empty band above the card. On a direct wp-admin
				      URL the bar IS visible, so the 32px is correctly
				      occupied and we leave it alone.

				      Detect by measuring the bar's actual rendered
				      height: 0 = hidden = zero out the padding;
				      anything > 0 = visible = leave WP's padding in place.

				   2. Catch-all height measurer for the card.

				      After (1) settles, set `.ufc-admin-wrap.min-height`
				      to fill `window.innerHeight − wrap.getBoundingClientRect().top − 8px`
				      so the card always reaches the viewport bottom (minus
				      the intentional 8px surround). This adapts to any
				      OTHER element WP.com or a plugin might inject above
				      the card — no manual selector hunting needed. */
				(function () {
					function adminBarVisible() {
						var bar = document.getElementById('wpadminbar');
						if (!bar) return false;
						var cs = getComputedStyle(bar);
						if (cs.display === 'none' || cs.visibility === 'hidden') return false;
						return bar.getBoundingClientRect().height > 0;
					}
					function reclaimReservation() {
						/* Only override when the bar IS hidden — otherwise
						   removing the padding would push the card behind
						   the visible bar. */
						if (adminBarVisible()) {
							document.documentElement.style.removeProperty('padding-top');
						} else {
							document.documentElement.style.setProperty('padding-top', '0', 'important');
						}
					}
					function recalc() {
						reclaimReservation();
						var wrap = document.querySelector('.ufc-admin-wrap');
						if (!wrap) return;
						/* Use DOCUMENT position of the wrap, not viewport
						   position. If the page happens to be scrolled when
						   recalc() fires (e.g. the iframe's internal load
						   triggered a scrollIntoView, or the user has
						   scrolled), `getBoundingClientRect().top` returns
						   a negative number — and `innerHeight − that − 8`
						   inflates above the actual available space, making
						   the card too tall and creating more scroll. Adding
						   `scrollY` converts back to document coords so the
						   calculation is stable across any scroll state. */
						var docTop = wrap.getBoundingClientRect().top + window.scrollY;
						var avail = window.innerHeight - docTop - 8;
						if (avail > 0) {
							wrap.style.minHeight = avail + 'px';
						}
					}
					if (document.readyState === 'loading') {
						document.addEventListener('DOMContentLoaded', recalc);
					} else {
						recalc();
					}
					window.addEventListener('load', recalc);
					setTimeout(recalc, 250);
					setTimeout(recalc, 1000);
					window.addEventListener('resize', recalc);
					if (window.MutationObserver) {
						var body = document.getElementById('wpbody-content');
						if (body) new MutationObserver(recalc).observe(body, { childList: true, subtree: false });
					}
				})();
				</script>
				<?php
			}
		);
	}
);
