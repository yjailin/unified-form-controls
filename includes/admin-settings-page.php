<?php
/**
 * Admin page — Appearance > Form Controls.
 *
 * Renders the showcase preview INLINE in the admin DOM (no iframe).
 *
 * Earlier versions of this page embedded `/?uf_showcase=1&uf_embed=1` in an
 * iframe. That frontend page is subject to WordPress.com / Calypso chrome
 * injection (#wpadminbar, #atomic-proxy-bar, performance-optimizer CSS
 * reordering, etc.) which the iframe context could not fully suppress —
 * leaving visible gaps and duplicate UI elements above the preview.
 *
 * Switching to inline rendering sidesteps the entire class of problems:
 * the admin document is NOT subject to those injections. The same DOM hosts
 * the settings panel (via `showcase-settings-panel.php`'s wp_footer hook,
 * which fires on admin pages too) and the showcase preview — so the
 * panel's existing imperative DOM updates apply to the preview directly,
 * with no iframe / postMessage round-trip.
 *
 * Layout mirrors `font-library.php` (Appearance > Fonts): a white rounded
 * card with 8px gap on right + bottom, sitting under a 32px admin bar.
 *
 * @package UnifiedFormControls
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns true on the admin Form Controls page (Appearance > Form Controls).
 *
 * Used by the shared showcase-enqueue logic (panel + Flatpickr + uf-forms
 * stylesheets) so the assets that previously only loaded on the frontend
 * `/?uf_showcase=1` route also load here, where the showcase HTML is
 * rendered inline.
 */
function ufc_is_admin_showcase_page() {
	if ( ! function_exists( 'get_current_screen' ) ) {
		return false;
	}
	$screen = get_current_screen();
	if ( ! $screen ) {
		// Fallback to URL inspection when get_current_screen() isn't ready
		// yet (e.g. during admin_enqueue_scripts on some hosts).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return is_admin() && isset( $_GET['page'] ) && 'unified-form-controls' === $_GET['page'];
	}
	return 'appearance_page_unified-form-controls' === $screen->id;
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
				?>
				<div class="wrap ufc-admin-wrap">
					<div class="ufc-admin-card">
						<div class="ufc-admin-card__body">
							<div class="ufc-admin-card__pane">
								<?php
								/*
								 * Render the showcase inline. The settings panel
								 * (#gb-showcase-settings) is emitted by
								 * showcase-settings-panel.php on the `wp_footer`
								 * hook, which fires on admin pages too — so the
								 * panel + showcase live in the same admin DOM
								 * automatically, no markup needed here.
								 */
								if ( function_exists( 'ufc_render_showcase_main' ) ) {
									ufc_render_showcase_main( array( 'hide_heading' => true ) );
								}
								?>
							</div>
						</div>
					</div>
				</div>
				<style>
					/* Layout mirrors the WP Fonts page (`font-library.php`):
					   white rounded card with 8px gap on the right + bottom,
					   sitting under the 32px admin bar at the top.

					   No iframe → no chrome-suppression CSS, no recalc script,
					   no dark-surround paint (the card content sits in the
					   admin document and inherits the admin's normal grey
					   #f0f0f1 background between card and viewport edge). */
					#wpfooter { display: none; }
					#wpcontent { padding-inline-start: 0; }
					#wpbody-content { padding-bottom: 0; }

					.ufc-admin-wrap {
						/* Override WP `.wrap` defaults (10px 20px 0 2px) for the
						   Fonts-page edge spacing. */
						margin: 0 8px 8px 0;
						display: flex;
						flex-direction: column;
						/* Bound to viewport (not min-height) so the card + pane
						   stay inside the viewport and the showcase scrolls
						   internally inside its pane rather than scrolling the
						   entire admin page.
						   32px = WP admin bar reservation; 8px = bottom gap. */
						height: calc( 100vh - 32px - 8px );
					}
					.ufc-admin-card {
						flex: 1 1 auto;
						display: flex;
						flex-direction: column;
						background: #fff;
						border-radius: 8px;
						overflow: hidden;
					}
					.ufc-admin-card__body {
						flex: 1 1 auto;
						display: flex;
						min-height: 500px;
					}
					/* The showcase pane fills the right of the card. Its left
					   edge sits flush against the settings panel (which
					   pins itself to the page's left via the embed-mode CSS
					   in showcase-settings-panel.php, taking 300px). */
					.ufc-admin-card__pane {
						flex: 1 1 auto;
						min-width: 0;
						min-height: 0;
						overflow: auto;
						margin-left: 300px;
					}

					/* The settings panel (`#gb-showcase-settings`) is
					   position: fixed by default — pinning to the viewport.
					   On this admin page we want it pinned inside the card
					   instead, on the left, like the legacy embed-mode
					   layout. Override the panel's positioning here so it
					   sits inside our card body. */
					body.appearance_page_unified-form-controls #gb-showcase-settings {
						/* Pin to the card's left edge — outside the WP admin
						   bar (32px tall) and the bottom 8px gap, with the
						   same 8px margin on left as the card has on right. */
						top: calc( 32px + 0px );
						bottom: 8px;
						left: 160px;          /* admin menu width when collapsed-by-default */
						right: auto;
						width: 300px;
						background: #fff;
						border-left: none;
						border-right: 1px solid #e7e7e7;
						box-shadow: none;
						z-index: 1;
					}
					/* Auto-fold admin menu (when the viewport is narrow) collapses
					   the menu to ~36px. Re-anchor the panel accordingly. */
					body.folded.appearance_page_unified-form-controls #gb-showcase-settings {
						left: 36px;
					}

					/* The showcase template applies `body { padding-right: 300px }`
					   to keep the right-side panel from covering content. On this
					   admin page the panel is on the LEFT, AND it's inside the
					   card (not pinned to the viewport edge), so neither padding
					   makes sense. Zero both. The card's left margin already
					   reserves space for it. */
					body.appearance_page_unified-form-controls {
						padding-left: 0 !important;
						padding-right: 0 !important;
					}

					/* The showcase's own `.uf-showcase` element has its own
					   internal padding (64px 48px 96px from uf-forms.css).
					   In the card context, scale that down so the showcase
					   doesn't waste vertical room on top padding. */
					.ufc-admin-card__pane .uf-showcase {
						padding: 32px 32px 48px;
					}
				</style>
				<?php
			}
		);
	}
);
