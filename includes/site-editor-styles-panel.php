<?php
/**
 * Registers the Form controls settings inside the Site Editor's Styles panel.
 *
 * Additive surface — new code alongside the existing showcase settings panel
 * (showcase-settings-panel.php) and the Appearance > Form controls admin page
 * (admin-settings-page.php). Neither of those is modified.
 *
 * Enqueues two scripts into the Site Editor only:
 *   1. uf-form-controls-fields.js — the shared four-control field set, bound
 *      to `settings.custom.ufFields` on the global styles entity (the same
 *      source of truth the showcase panel uses).
 *   2. site-editor-styles-panel.js — injects the "Form controls" item into
 *      the Styles list and wires up the live showcase preview.
 *
 * @package UnifiedFormControls
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'enqueue_block_editor_assets',
	function () {
		// `enqueue_block_editor_assets` also fires in the post/page editor —
		// scope this to the Site Editor screen only.
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || 'site-editor' !== $screen->id ) {
			return;
		}

		$deps = array( 'wp-element', 'wp-components', 'wp-data', 'wp-core-data', 'wp-block-editor', 'wp-i18n' );

		$fields_path = UNIFIED_FORM_CONTROLS_PATH . 'js/uf-form-controls-fields.js';
		$panel_path  = UNIFIED_FORM_CONTROLS_PATH . 'js/site-editor-styles-panel.js';
		$css_path    = UNIFIED_FORM_CONTROLS_PATH . 'css/site-editor-styles-panel.css';

		wp_enqueue_script(
			'ufc-form-control-fields',
			UNIFIED_FORM_CONTROLS_URL . 'js/uf-form-controls-fields.js',
			$deps,
			file_exists( $fields_path ) ? filemtime( $fields_path ) : UNIFIED_FORM_CONTROLS_VERSION,
			true
		);

		wp_enqueue_script(
			'ufc-site-editor-styles-panel',
			UNIFIED_FORM_CONTROLS_URL . 'js/site-editor-styles-panel.js',
			array_merge( $deps, array( 'ufc-form-control-fields' ) ),
			file_exists( $panel_path ) ? filemtime( $panel_path ) : UNIFIED_FORM_CONTROLS_VERSION,
			true
		);

		// Theme color palette (parity with the showcase panel) + the preview
		// URL. Uses the same `?uf_showcase=1&uf_embed=1` surface the admin
		// page already embeds.
		$colors  = array();
		$palette = wp_get_global_settings( array( 'color', 'palette' ) );
		foreach ( array( 'theme', 'custom', 'default' ) as $source ) {
			if ( ! empty( $palette[ $source ] ) ) {
				$colors = $palette[ $source ];
				break;
			}
		}

		// Style variations (slug + title) so the preview can follow the
		// variation selected in Browse styles — the SAME `?uf_variation=<slug>`
		// mechanism the showcase/admin page already uses (see the
		// `wp_theme_json_data_user` filter in showcase-settings-panel.php).
		$variations = function_exists( 'ufc_showcase_get_variations' ) ? ufc_showcase_get_variations() : array();

		wp_add_inline_script(
			'ufc-site-editor-styles-panel',
			'window.__UFC_SE=' . wp_json_encode(
				array(
					'previewUrl' => home_url( '/?uf_showcase=1&uf_embed=1&uf_frame=1' ),
					'colors'     => $colors,
					'maxRadius'  => 27,
					'variations' => $variations,
				)
			) . ';',
			'before'
		);

		wp_enqueue_style(
			'ufc-site-editor-styles-panel',
			UNIFIED_FORM_CONTROLS_URL . 'css/site-editor-styles-panel.css',
			array( 'wp-components' ),
			file_exists( $css_path ) ? filemtime( $css_path ) : UNIFIED_FORM_CONTROLS_VERSION
		);
	}
);

/**
 * Preview-only override: make the showcase read like the native Style Book.
 *
 * Restyles the showcase `<h2>` section labels to match the Style Book
 * example-title treatment, so the Form controls preview belongs to the same
 * system as the Style Book (the view behind the eye icon in Styles). Values
 * were read from the live Style Book's computed styles
 * (.editor-style-book__example-title):
 *   font-size 13px, weight 400, no uppercase, text-align left, line 1;
 *   padding-top 8px; border-top 1px solid color-mix(currentColor 10%);
 *   color color-mix(currentColor 60%).
 * Side padding is left as-is: the showcase's existing centered inset already
 * lines up with the Style Book's content column (~59px from the canvas edge).
 * The Style Book bases those mixes on `currentColor`, which resolves to the
 * theme text on its native canvas but stays dark inside the showcase — so we
 * base the same 60%/10% mixes on the theme contrast token, which DOES adapt
 * per variation (verified light + dark).
 *
 * Scoped to our Site Editor preview only (the iframe adds `uf_frame=1`) so the
 * plain `?uf_showcase=1` page and the Appearance > Form controls admin iframe
 * are left exactly as they were. Emitted on `wp_footer` (after uf-forms.css)
 * so the later source order wins on the shared selectors.
 */
add_action(
	'wp_footer',
	function () {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['uf_frame'] ) ) {
			return;
		}
		$contrast = 'var(--wp--preset--color--contrast, currentColor)';
		$css      = '
			.uf-showcase h2 {
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
				font-size: 13px;
				font-weight: 400;
				line-height: normal;
				letter-spacing: normal;
				text-transform: none;
				text-align: left;
				opacity: 1;
				margin: 40px 0 16px;
				padding-top: 8px;
				color: color-mix( in srgb, ' . $contrast . ' 60%, transparent );
				/* Base the rule on currentColor (= the heading color just set,
				   contrast@60%) so 10% of it compounds to ~6% — exactly what the
				   Style Book renders (its border-top uses currentColor too). */
				border-top: 1px solid color-mix( in srgb, currentColor 10%, transparent );
			}
			.uf-showcase section:first-of-type h2 { margin-top: 0; }
			/* Remove the strong group-end rule (uf-forms.css puts a 1px
			   var(--uf-divider) border-top on every section); the faint h2
			   separators above headings stay. */
			.uf-showcase section { border-top: 0; }
		';
		echo '<style id="ufc-preview-frame-overrides">' . $css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	},
	100
);
