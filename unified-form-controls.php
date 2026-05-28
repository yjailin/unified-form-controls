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

/**
 * Showcase stylesheets. Two files, loaded in cascade order:
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
 * Both gated to `?uf_showcase=1` to match the rest of the plugin's scope.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! function_exists( 'ufc_showcase_panel_is_active' ) || ! ufc_showcase_panel_is_active() ) {
			return;
		}

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
	}
);
