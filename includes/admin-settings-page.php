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
					html, body, #wpwrap { background: #1e1e1e; }

					/* Hide the standard WP admin bar on this admin page so the
					   card extends flush to the very top of the viewport. WP's
					   default CSS pushes `<html>` down by 32px to make room for
					   the bar (via `html.wp-toolbar`); we zero that too so no
					   gap remains. The bar still shows on every other admin
					   page — this hide is scoped to the inline `<style>` block
					   that only renders on the Form controls page. (On
					   WordPress.com sites a separate Calypso chrome bar lives
					   above wp-admin and is outside our reach — that one will
					   still appear.) */
					#wpadminbar { display: none !important; }
					html.wp-toolbar { padding-top: 0 !important; }

					.ufc-admin-wrap {
						/* Override WP .wrap defaults (10px 20px 0 2px) to
						   the Fonts page's tighter edge spacing. */
						margin: 0 8px 8px 0;
						display: flex;
						flex-direction: column;
						/* No admin bar (hidden above), 8px gap to viewport edge */
						min-height: calc(100vh - 8px);
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
				</style>
				<?php
			}
		);
	}
);
