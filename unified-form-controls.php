<?php
/**
 * Plugin Name: Unified Form Controls
 * Description: A unified token-based styling system for form controls across WordPress and WooCommerce.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author:
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: unified-form-controls
 *
 * @package UnifiedFormControls
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'UNIFIED_FORM_CONTROLS_VERSION', '0.1.0' );
define( 'UNIFIED_FORM_CONTROLS_PATH', plugin_dir_path( __FILE__ ) );
define( 'UNIFIED_FORM_CONTROLS_URL', plugin_dir_url( __FILE__ ) );

/*
 * Includes are loaded unconditionally so:
 *   - the `wp_ajax_uf_save_field_settings` handler (in showcase-page.php)
 *     is registered for admin-ajax.php POSTs from the Save button;
 *   - `ufc_showcase_panel_is_active()` (defined in showcase-settings-panel.php)
 *     is callable from the gated helpers in site-editor-form-fields.php and
 *     flatpickr.php.
 *
 * Each callback inside the includes self-gates to `?uf_showcase=1`, so the
 * runtime scope is showcase-only even though the require_once calls run on
 * every request.
 */
require_once UNIFIED_FORM_CONTROLS_PATH . 'includes/showcase-settings-panel.php';
require_once UNIFIED_FORM_CONTROLS_PATH . 'includes/showcase-page.php';
require_once UNIFIED_FORM_CONTROLS_PATH . 'includes/site-editor-form-fields.php';
require_once UNIFIED_FORM_CONTROLS_PATH . 'includes/flatpickr.php';
require_once UNIFIED_FORM_CONTROLS_PATH . 'includes/admin-settings-page.php';
require_once UNIFIED_FORM_CONTROLS_PATH . 'includes/site-editor-styles-panel.php';

/**
 * Form-control stylesheets. Two files, loaded in cascade order:
 *
 *   1. uf-tokens.css — `--uf-field-*` token defaults + base consumer
 *      rules (background / color / border-radius / font-size on text
 *      inputs, selects, textareas, and their WC wrappers). Originally
 *      shipped as inline CSS in twentytwentyfive's functions.php; lifted
 *      into the plugin so it works on any theme with no dependencies.
 *
 *   2. uf-forms.css — the rest: `.uf-showcase` / `.uf-field` layout,
 *      variant rules (`[data-border]`, `[data-fill]`, `[data-corners]`,
 *      `[data-label]`), and component styling. Depends on the tokens
 *      above so cascade order matters — declared as a wp_enqueue
 *      dependency.
 *
 * Registered on `enqueue_block_assets` so the CSS reaches every
 * context that uses `.uf-field` markup: front-end page views (My
 * Account, Checkout, Coming Soon, Cart, Product), the post editor,
 * and Site Editor iframe previews. Gating these to `?uf_showcase=1`
 * would break every other template that relies on the floating-label
 * markup — those templates use `.uf-field` directly even though they
 * have nothing to do with the showcase.
 */
add_action(
	'enqueue_block_assets',
	function () {
		$tokens_path = UNIFIED_FORM_CONTROLS_PATH . 'css/uf-tokens.css';
		wp_enqueue_style(
			'uf-tokens',
			UNIFIED_FORM_CONTROLS_URL . 'css/uf-tokens.css',
			array(),
			file_exists( $tokens_path ) ? filemtime( $tokens_path ) : UNIFIED_FORM_CONTROLS_VERSION
		);

		$forms_path = UNIFIED_FORM_CONTROLS_PATH . 'css/uf-forms.css';
		wp_enqueue_style(
			'uf-forms',
			UNIFIED_FORM_CONTROLS_URL . 'css/uf-forms.css',
			array( 'uf-tokens' ),
			file_exists( $forms_path ) ? filemtime( $forms_path ) : UNIFIED_FORM_CONTROLS_VERSION
		);

		// Proposed-screen-only preview overrides. Loaded alongside uf-forms so
		// it reaches every context the fields render in (incl. the Site Editor
		// preview iframe), but every rule inside is gated behind the
		// `[data-uf-label-align="pinned"]` marker that ONLY the proposed screen
		// sets — so it stays completely inert for the other screens, the real
		// front-end forms, and Checkout. Kept out of uf-forms.css on purpose.
		$proposed_path = UNIFIED_FORM_CONTROLS_PATH . 'css/uf-proposed-preview.css';
		wp_enqueue_style(
			'uf-proposed-preview',
			UNIFIED_FORM_CONTROLS_URL . 'css/uf-proposed-preview.css',
			array( 'uf-forms' ),
			file_exists( $proposed_path ) ? filemtime( $proposed_path ) : UNIFIED_FORM_CONTROLS_VERSION
		);
	}
);
