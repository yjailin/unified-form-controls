<?php
/**
 * Showcase page template + AJAX save endpoint.
 *
 * Extracted verbatim from twentytwentyfive's functions.php — the
 * `template_redirect` handler that renders `/?uf_showcase=1` and the
 * companion `wp_ajax_uf_save_field_settings` endpoint the toolbar's
 * Save button calls.
 *
 * Note: a few markup snippets reference theme assets via
 * `get_stylesheet_directory_uri()` (the trailing-logos section pulls
 * card SVGs from `<theme>/assets/payment-buttons/cards/`). When the
 * active theme doesn't ship those files, the chips render as broken
 * images — that's a theme-asset dependency carried over from the
 * prototype, not new behaviour.
 *
 * @package UnifiedFormControls
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Embed mode — `/?uf_showcase=1&uf_embed=1` is what the Form Controls
 * admin page loads inside its iframe. Suppress the WordPress admin bar
 * so the iframe is a clean preview surface instead of repeating the
 * outer admin chrome the user already has around the iframe.
 *
 * Defense in depth — `show_admin_bar` alone has proven not enough on
 * some hosts (notably WordPress.com, where Calypso/proxy layers can
 * re-engage admin-bar rendering after our filter runs). All four
 * suppressions below are scoped to the embed URL only, and run on
 * `init` so they execute before any of the render hooks WordPress
 * would otherwise use to paint the bar.
 *
 *   1. `show_admin_bar` filter → stop the normal render path.
 *   2. `remove_action( wp_footer / in_admin_header / wp_body_open,
 *      wp_admin_bar_render )` → strip the actual render callbacks
 *      that fire even when `show_admin_bar` returns true elsewhere.
 *   3. Dequeue the `admin-bar` style + script handles → so the bar
 *      CSS/JS never ship to the browser at all.
 *   4. Inline `<style>` in `wp_head` → final hard fail-safe; if any
 *      of the above is bypassed and the bar somehow paints anyway,
 *      CSS hides it and zeros the toolbar's `<html>` padding so
 *      nothing else in the iframe shifts down to accommodate it.
 */
function ufc_is_showcase_embed_request() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	return isset( $_GET['uf_showcase'] ) && isset( $_GET['uf_embed'] );
}

add_filter(
	'show_admin_bar',
	function ( $show ) {
		return ufc_is_showcase_embed_request() ? false : $show;
	}
);

add_action(
	'init',
	function () {
		if ( ! ufc_is_showcase_embed_request() ) {
			return;
		}
		// Strip every action that can render the admin bar — covers
		// both frontend and admin-screen render hooks.
		remove_action( 'wp_footer', 'wp_admin_bar_render', 1000 );
		remove_action( 'in_admin_header', 'wp_admin_bar_render', 0 );
		remove_action( 'wp_body_open', 'wp_admin_bar_render', 0 );
		// Block the script/style from loading at all.
		add_action(
			'wp_enqueue_scripts',
			function () {
				wp_dequeue_style( 'admin-bar' );
				wp_dequeue_script( 'admin-bar' );
			},
			100
		);
		// CSS fail-safe — paint a hide rule directly into the iframe's
		// <head>. Wins over anything that might still try to display
		// or reserve space for the bar.
		add_action(
			'wp_head',
			function () {
				echo '<style id="ufc-iframe-no-admin-bar">'
					. '#wpadminbar{display:none!important}'
					. 'html{padding-top:0!important;margin-top:0!important}'
					. 'body{margin-top:0!important}'
					. '</style>';
			},
			999
		);
		// JS DOM-removal — last layer. CSS `display:none` only hides
		// the element; WordPress's accommodation rules (the
		// `html.wp-toolbar` padding, `body.admin-bar` class, etc.)
		// can still reserve space for a hidden bar on hosts where
		// the bar IS injected into the DOM despite all the PHP-side
		// removals above. So pull the element out of the DOM
		// entirely and strip the body/html classes that trigger any
		// further accommodation. Runs both immediately (covers the
		// case where the bar is already in the parsed HTML by the
		// time the script executes) and on DOMContentLoaded (covers
		// the case where some Calypso/WP.com script injects the bar
		// post-load). Uses a MutationObserver so any later
		// re-injection is also caught and removed.
		add_action(
			'wp_head',
			function () {
				echo '<script id="ufc-iframe-purge-admin-bar">'
					. '(function(){function purge(){var b=document.getElementById("wpadminbar");if(b&&b.parentNode)b.parentNode.removeChild(b);if(document.documentElement){document.documentElement.classList.remove("wp-toolbar");document.documentElement.style.setProperty("margin-top","0","important");document.documentElement.style.setProperty("padding-top","0","important");}if(document.body){document.body.classList.remove("admin-bar");document.body.style.setProperty("margin-top","0","important");}}purge();document.addEventListener("DOMContentLoaded",purge);if(window.MutationObserver){new MutationObserver(purge).observe(document.documentElement,{childList:true,subtree:true});}})();'
					. '</script>';
			},
			999
		);
		// Footer style block — duplicate of the `wp_head` override,
		// emitted at the very end of <body> so it appears AFTER any
		// stylesheet (including `admin-bar.min.css`) that WP.com's
		// performance optimizer relocates into the body. CSS source
		// order is the tiebreaker when specificity + !important are
		// equal, so a `wp_footer` block always wins over a `<body>`
		// stylesheet on equal-strength rules.
		add_action(
			'wp_footer',
			function () {
				echo '<style id="ufc-iframe-no-admin-bar-footer">'
					. 'html{margin-top:0!important;padding-top:0!important}'
					. 'body{margin-top:0!important}'
					. '</style>';
			},
			999
		);
	}
);

/**
 * Unified-forms showcase page.
 *
 * Renders a reference surface at /?uf_showcase=1 for iterating on the shared
 * field token spec. Every field-like control and state lives in one place so
 * visual changes to the tokens can be evaluated at a glance. This is scaffold
 * for Phase 1 only — the unified-forms plugin will own this permanently.
 */
add_action( 'template_redirect', function () {
	if ( ! isset( $_GET['uf_showcase'] ) ) {
		return;
	}
	status_header( 200 );
	nocache_headers();
	?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Forms showcase — <?php bloginfo( 'name' ); ?></title>
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'uf-showcase-body' ); ?>>
	<?php wp_body_open(); ?>
	<script>
	/**
	 * Auto-detect the active theme's background brightness and set
	 * color-scheme on <html> accordingly. The `--uf-field-error` token
	 * uses CSS `light-dark()` to pick between the on-light variant
	 * (#B03040, a dark red) and the on-dark variant (#FF8A95, a lighter
	 * pink-red). `light-dark()` reads from the computed color-scheme —
	 * so by setting it here based on the real background luminance, the
	 * error color stays readable on any theme without the theme having
	 * to declare its own color-scheme in theme.json.
	 *
	 * Runs immediately after <body> opens so the scheme is set before
	 * any error states paint — no flash of wrong color.
	 */
	(function () {
		function bg(el) {
			var c = getComputedStyle(el).backgroundColor;
			return ( c === 'rgba(0, 0, 0, 0)' || c === 'transparent' ) ? null : c;
		}
		var color = bg(document.body) || bg(document.documentElement) || 'rgb(255,255,255)';
		var m = color.match(/rgba?\((\d+)\s*,\s*(\d+)\s*,\s*(\d+)/);
		if ( ! m ) return;
		// WCAG relative luminance.
		function chan(v) {
			v = parseInt(v, 10) / 255;
			return v <= 0.03928 ? v / 12.92 : Math.pow( ( v + 0.055 ) / 1.055, 2.4 );
		}
		var L = 0.2126 * chan( m[1] ) + 0.7152 * chan( m[2] ) + 0.0722 * chan( m[3] );
		document.documentElement.style.colorScheme = L < 0.5 ? 'dark' : 'light';
	})();
	</script>

	<?php
	// Seed the showcase from the entity (settings.custom.ufFields) so the
	// page reflects current state including unsaved edits via preview_token.
	$uf_cur_e = wp_get_global_settings( array( 'custom', 'ufFields' ) );
	$uf_cur_e = is_array( $uf_cur_e ) ? $uf_cur_e : array();
	$uf_cur_r = isset( $uf_cur_e['radius'] ) ? floatval( $uf_cur_e['radius'] ) : 4.0;
	$uf_cur   = array(
		'border'  => isset( $uf_cur_e['border'] ) ? $uf_cur_e['border'] : 'outline',
		'fill'    => isset( $uf_cur_e['fill'] )   ? $uf_cur_e['fill']   : 'unfilled',
		'corners' => ( 0.0 === $uf_cur_r ) ? 'sharp' : ( $uf_cur_r >= 999.0 ? 'pill' : 'rounded' ),
		'label'   => isset( $uf_cur_e['label'] )  ? $uf_cur_e['label']  : 'inside',
	);
	?>
	<main class="uf-showcase" data-border="<?php echo esc_attr( $uf_cur['border'] ); ?>" data-fill="<?php echo esc_attr( $uf_cur['fill'] ); ?>" data-corners="<?php echo esc_attr( $uf_cur['corners'] ); ?>" data-label="<?php echo esc_attr( $uf_cur['label'] ); ?>">
		<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<?php if ( empty( $_GET['uf_embed'] ) ) : ?>
		<h1>Forms showcase</h1>
		<p class="uf-lede">
			Reference surface for the unified-forms Phase 1 token spec. Every
			field-like control appears here in each of its states so token
			changes can be evaluated at a glance.
		</p>
		<?php endif; ?>


		<script>
		(function () {
			var root = document.querySelector('.uf-showcase');
			if (!root) return;

			// Mirror the showcase's variant attrs onto <body> on first
			// render so popovers/portaled elements (e.g. Flatpickr panels
			// appended to document.body) inherit the same token values.
			// The settings-panel React component handles ongoing variant
			// changes via postMessage; this is the initial sync only.
			['border', 'fill', 'corners', 'label'].forEach(function (dim) {
				var val = root.getAttribute('data-' + dim);
				if (val) document.body.setAttribute('data-' + dim, val);
			});

			root.addEventListener('click', function (e) {
				// Clear input (X button in trailing icon slot)
				var cb = e.target.closest('[data-action="clear-input"]');
				if (cb) {
					var wrap = cb.closest('.uf-field');
					var inp = wrap ? wrap.querySelector('input') : null;
					if (inp) {
						inp.value = '';
						inp.focus();
						inp.dispatchEvent(new Event('input', { bubbles: true }));
					}
					return;
				}
				// Password toggle
				var pb = e.target.closest('[data-action="toggle-password"]');
				if (pb) {
					var pwd = pb.parentElement.querySelector('input');
					if (!pwd) return;
					var nowVisible = pwd.type === 'password';
					pwd.type = nowVisible ? 'text' : 'password';
					pb.setAttribute('data-visible', nowVisible ? 'true' : 'false');
					pb.setAttribute('aria-label', nowVisible ? 'Hide password' : 'Show password');
					return;
				}
				// Date / time picker — open the themed Flatpickr popup if an
				// instance is attached to the input; otherwise fall back to
				// the browser's native picker, or focus as last resort.
				var op = e.target.closest('[data-action="open-picker"]');
				if (op) {
					var wrap = op.closest('.uf-field');
					var inp = wrap ? wrap.querySelector('input') : null;
					if (!inp) return;
					if (inp._flatpickr) {
						inp._flatpickr.open();
					} else if (typeof inp.showPicker === 'function') {
						try { inp.showPicker(); } catch (err) { inp.focus(); }
					} else {
						inp.focus();
					}
					return;
				}
				// Quantity stepper
				var sb = e.target.closest('.uf-stepper button');
				if (sb) {
					var input = sb.parentElement.querySelector('input[type="number"]');
					if (!input) return;
					var label = (sb.getAttribute('aria-label') || '').toLowerCase();
					if (label.indexOf('increase') !== -1) input.stepUp();
					else if (label.indexOf('decrease') !== -1) input.stepDown();
					input.dispatchEvent(new Event('input', { bubbles: true }));
					input.dispatchEvent(new Event('change', { bubbles: true }));
					return;
				}
				// Number-input chevron spinner (custom in-input ▲▼ replacing
				// the browser's native spinner buttons, which we hide via CSS).
				var nsb = e.target.closest('[data-action="num-step-up"], [data-action="num-step-down"]');
				if (nsb) {
					var wrap = nsb.closest('.uf-field');
					var inp = wrap ? wrap.querySelector('input[type="number"]') : null;
					if (!inp) return;
					if (nsb.getAttribute('data-action') === 'num-step-up') inp.stepUp();
					else inp.stepDown();
					inp.dispatchEvent(new Event('input', { bubbles: true }));
					inp.dispatchEvent(new Event('change', { bubbles: true }));
				}
			});

			// ─── Numeric-only inputs ─────────────────────────────────────────
			// Any input marked `data-numeric-only` (currently the card-number
			// field) strips non-digits on every keystroke and paste. The
			// `maxlength` attribute on the input itself caps the length,
			// but we also clamp here as a defense-in-depth (and so the
			// stripping math stays in one place).
			document.addEventListener('input', function (e) {
				var t = e.target;
				if (!t || !t.matches || !t.matches('[data-numeric-only]')) return;
				var cleaned = t.value.replace(/\D+/g, '');
				var max = parseInt(t.getAttribute('maxlength'), 10);
				if (!isNaN(max) && max > 0) cleaned = cleaned.slice(0, max);
				if (cleaned !== t.value) {
					var pos = t.selectionStart;
					t.value = cleaned;
					// Try to keep the caret roughly where the user expects.
					try { t.setSelectionRange(pos, pos); } catch (_) {}
				}
			});

			// ─── Spinner vertical-centering on the input ─────────────────────
			// CSS can't reliably center the spinner stack on the underlying
			// <input> across both label modes: in inside-label mode the input
			// fills the wrapper and starts at offsetTop=0, but in above-label
			// mode the input sits below a separated label inside the same
			// wrapper, so offsetTop is non-zero. We measure those offsets and
			// position the spinner imperatively.
			function positionSpinners() {
				var fields = document.querySelectorAll('.uf-field--number-spinner');
				for (var i = 0; i < fields.length; i++) {
					var f = fields[i];
					var input = f.querySelector('input[type="number"]');
					var spinner = f.querySelector('.uf-field__spinner');
					if (!input || !spinner) continue;
					var centerY = input.offsetTop + input.offsetHeight / 2;
					spinner.style.top = centerY + 'px';
					spinner.style.transform = 'translateY(-50%)';
				}
			}
			// Initial placement (DOM is already parsed at this point since
			// this script lives just below the markup).
			positionSpinners();
			// Re-run on every layout-affecting toolbar/postMessage change.
			// data-label re-orders the wrapper's children; data-border /
			// data-fill change input padding/height. Either can shift the
			// input's offsetTop or offsetHeight.
			var showcaseRoot = document.querySelector('.uf-showcase');
			if (showcaseRoot && window.MutationObserver) {
				new MutationObserver(positionSpinners).observe(showcaseRoot, {
					attributes: true,
					attributeFilter: ['data-label', 'data-border', 'data-fill', 'data-corners']
				});
			}
			// Belt-and-suspenders: also recompute on window resize and after
			// font loading (both can change the input's measured height).
			window.addEventListener('resize', positionSpinners);
			if (document.fonts && document.fonts.ready && document.fonts.ready.then) {
				document.fonts.ready.then(positionSpinners);
			}
		})();
		</script>

		<section aria-labelledby="s-text">
			<h2 id="s-text">Text inputs</h2>
			<div class="uf-grid">
				<!-- Row 1: empty | filled -->
				<div class="uf-field uf-field--float">
					<input type="text" id="f-name-empty" placeholder=" " />
					<label for="f-name-empty">Field label</label>
				</div>
				<div class="uf-field uf-field--float">
					<input type="text" id="f-name-filled" value="Sample value" placeholder=" " />
					<label for="f-name-filled">Field label</label>
				</div>
				<!-- Row 2: optional empty | optional filled -->
				<div class="uf-field uf-field--float uf-field--optional">
					<input type="text" id="f-tel" placeholder=" " />
					<label for="f-tel">Field label</label>
				</div>
				<div class="uf-field uf-field--float uf-field--optional">
					<input type="text" id="f-optional-filled" value="Sample value" placeholder=" " />
					<label for="f-optional-filled">Field label</label>
				</div>
				<!-- Row 3: with helper text | error -->
				<div class="uf-field uf-field--float">
					<input type="text" id="h-email" placeholder=" " aria-describedby="h-email-help" />
					<label for="h-email">Field label</label>
					<span class="uf-helper" id="h-email-help">Helper text</span>
				</div>
				<div class="uf-field uf-field--float uf-has-error">
					<input type="text" id="st-error" value="Sample value" aria-invalid="true" placeholder=" " />
					<label for="st-error">Error</label>
					<span class="uf-error">Please enter a valid email address.</span>
				</div>
				<!-- Row 4: disabled | readonly -->
				<div class="uf-field uf-field--float">
					<input type="text" id="st-disabled" value="Cannot edit" disabled placeholder=" " />
					<label for="st-disabled">Disabled</label>
				</div>
				<div class="uf-field uf-field--float">
					<input type="text" id="st-readonly" value="View only" readonly placeholder=" " />
					<label for="st-readonly">Readonly</label>
				</div>
				<!-- Row 5: empty textarea | filled textarea -->
				<div class="uf-field uf-field--float">
					<textarea id="f-ta-empty" placeholder=" "></textarea>
					<label for="f-ta-empty">Textarea label</label>
				</div>
				<div class="uf-field uf-field--float">
					<textarea id="f-ta-filled" placeholder=" ">Sample value</textarea>
					<label for="f-ta-filled">Textarea label</label>
				</div>
			</div>
		</section>

		<section aria-labelledby="s-interactive">
			<h2 id="s-interactive">Inputs with controls</h2>
			<?php // Pattern: a float-label input with a static row of brand
			      // chips (or partner marks, etc.) docked inside on the right.
			      // Wrapper takes `.uf-field--trailing-logos`; the chips live
			      // in `<span class="uf-field__logos">`. Right-padding on the
			      // input is reserved automatically. ?>
			<?php $card_dir = UNIFIED_FORM_CONTROLS_URL . 'assets/payment-buttons/cards/'; ?>
			<div class="uf-grid">
				<!-- Card number (with trailing brand logos). Numeric-only and
				     capped at 16 digits — `maxlength` covers paste, and a JS
				     input-listener in the script below strips non-digits as the
				     user types. No auto-spacing or card-brand detection yet. -->
				<div class="uf-field uf-field--float uf-field--trailing-logos">
					<input type="text" id="f-card-empty" placeholder=" " inputmode="numeric" autocomplete="off" maxlength="16" pattern="[0-9]*" data-numeric-only />
					<label for="f-card-empty">Card number</label>
					<span class="uf-field__logos" aria-hidden="true">
						<?php for ( $i = 1; $i <= 4; $i++ ) : ?>
							<img src="<?php echo esc_url( $card_dir . 'card-' . $i . '.svg' ); ?>" alt="" />
						<?php endfor; ?>
						<span class="uf-field__logos-more">+0</span>
					</span>
				</div>
				<!-- Password (with eye-toggle) -->
				<div class="uf-field uf-field--float uf-field--trailing">
					<input type="password" id="f-pw" value="supersecret" placeholder=" " />
					<label for="f-pw">Password</label>
					<button type="button" class="uf-field__icon" data-action="toggle-password" aria-label="Show password">
						<svg class="uf-icon-eye" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M3.99961 13C4.67043 13.3354 4.6703 13.3357 4.67017 13.3359L4.67298 13.3305C4.67621 13.3242 4.68184 13.3135 4.68988 13.2985C4.70595 13.2686 4.7316 13.2218 4.76695 13.1608C4.8377 13.0385 4.94692 12.8592 5.09541 12.6419C5.39312 12.2062 5.84436 11.624 6.45435 11.0431C7.67308 9.88241 9.49719 8.75 11.9996 8.75C14.502 8.75 16.3261 9.88241 17.5449 11.0431C18.1549 11.624 18.6061 12.2062 18.9038 12.6419C19.0523 12.8592 19.1615 13.0385 19.2323 13.1608C19.2676 13.2218 19.2933 13.2686 19.3093 13.2985C19.3174 13.3135 19.323 13.3242 19.3262 13.3305L19.3291 13.3359C19.3289 13.3357 19.3288 13.3354 19.9996 13C20.6704 12.6646 20.6703 12.6643 20.6701 12.664L20.6697 12.6632L20.6688 12.6614L20.6662 12.6563L20.6583 12.6408C20.6517 12.6282 20.6427 12.6108 20.631 12.5892C20.6078 12.5459 20.5744 12.4852 20.5306 12.4096C20.4432 12.2584 20.3141 12.0471 20.1423 11.7956C19.7994 11.2938 19.2819 10.626 18.5794 9.9569C17.1731 8.61759 14.9972 7.25 11.9996 7.25C9.00203 7.25 6.82614 8.61759 5.41987 9.9569C4.71736 10.626 4.19984 11.2938 3.85694 11.7956C3.68511 12.0471 3.55605 12.2584 3.4686 12.4096C3.42484 12.4852 3.39142 12.5459 3.36818 12.5892C3.35656 12.6108 3.34748 12.6282 3.34092 12.6408L3.33297 12.6563L3.33041 12.6614L3.32948 12.6632L3.32911 12.664C3.32894 12.6643 3.32879 12.6646 3.99961 13ZM11.9996 16C13.9326 16 15.4996 14.433 15.4996 12.5C15.4996 10.567 13.9326 9 11.9996 9C10.0666 9 8.49961 10.567 8.49961 12.5C8.49961 14.433 10.0666 16 11.9996 16Z"/></svg>
						<svg class="uf-icon-eye-off" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M20.7 12.7s0-.1-.1-.2c0-.2-.2-.4-.4-.6-.3-.5-.9-1.2-1.6-1.8-.7-.6-1.5-1.3-2.6-1.8l-.6 1.4c.9.4 1.6 1 2.1 1.5.6.6 1.1 1.2 1.4 1.6.1.2.3.4.3.5v.1l.7-.3.7-.3Zm-5.2-9.3-1.8 4c-.5-.1-1.1-.2-1.7-.2-3 0-5.2 1.4-6.6 2.7-.7.7-1.2 1.3-1.6 1.8-.2.3-.3.5-.4.6 0 0 0 .1-.1.2s0 0 .7.3l.7.3V13c0-.1.2-.3.3-.5.3-.4.7-1 1.4-1.6 1.2-1.2 3-2.3 5.5-2.3H13v.3c-.4 0-.8-.1-1.1-.1-1.9 0-3.5 1.6-3.5 3.5s.6 2.3 1.6 2.9l-2 4.4.9.4 7.6-16.2-.9-.4Zm-3 12.6c1.7-.2 3-1.7 3-3.5s-.2-1.4-.6-1.9L12.4 16Z"/></svg>
					</button>
				</div>
				<!-- Search (with magnifier / clear-X) -->
				<div class="uf-field uf-field--float uf-field--trailing">
					<input type="search" id="f-search" placeholder=" " />
					<label for="f-search">Search</label>
					<button type="button" class="uf-field__icon uf-icon-clear" data-action="clear-input" aria-label="Clear search">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 13.06l3.712 3.713 1.061-1.06L13.061 12l3.712-3.712-1.06-1.06L12 10.938 8.288 7.227l-1.061 1.06L10.939 12l-3.712 3.712 1.06 1.061L12 13.061z"/></svg>
					</button>
					<span class="uf-field__icon uf-icon-search" aria-hidden="true">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M13 5c-3.3 0-6 2.7-6 6 0 1.4.5 2.7 1.3 3.7l-3.8 3.8 1.1 1.1 3.8-3.8c1 .8 2.3 1.3 3.7 1.3 3.3 0 6-2.7 6-6S16.3 5 13 5zm0 10.5c-2.5 0-4.5-2-4.5-4.5s2-4.5 4.5-4.5 4.5 2 4.5 4.5-2 4.5-4.5 4.5z"/></svg>
					</span>
				</div>
				<!-- Quantity (number input with custom chevron spinner) -->
				<div class="uf-field uf-field--float uf-field--number-spinner">
					<input type="number" id="f-num" value="42" placeholder=" " />
					<label for="f-num">Quantity</label>
					<span class="uf-field__spinner" aria-hidden="true">
						<button type="button" class="uf-field__spinner-btn" data-action="num-step-up" tabindex="-1" aria-label="Increment">
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M7.41 15.41L12 10.83l4.59 4.58L18 14l-6-6-6 6z"/></svg>
						</button>
						<button type="button" class="uf-field__spinner-btn" data-action="num-step-down" tabindex="-1" aria-label="Decrement">
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6z"/></svg>
						</button>
					</span>
				</div>
				<!-- Day picker -->
				<div class="uf-field uf-field--float uf-field--trailing">
					<input type="date" id="f-date" placeholder=" " />
					<label for="f-date">Day picker</label>
					<button type="button" class="uf-field__icon" data-action="open-picker" aria-label="Open date picker">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm.5 16c0 .3-.2.5-.5.5H5c-.3 0-.5-.2-.5-.5V7h15v12zM9 10H7v2h2v-2zm0 4H7v2h2v-2zm4-4h-2v2h2v-2zm4 0h-2v2h2v-2zm-4 4h-2v2h2v-2zm4 0h-2v2h2v-2z"/></svg>
					</button>
				</div>
				<!-- Time picker -->
				<div class="uf-field uf-field--float uf-field--trailing">
					<input type="time" id="f-time" placeholder=" " />
					<label for="f-time">Time picker</label>
					<button type="button" class="uf-field__icon" data-action="open-picker" aria-label="Open time picker">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 3c-5 0-9 4-9 9s4 9 9 9 9-4 9-9-4-9-9-9zm0 16.5c-4.1 0-7.5-3.4-7.5-7.5S7.9 4.5 12 4.5s7.5 3.4 7.5 7.5-3.4 7.5-7.5 7.5zM12 7l-1 5c0 .3.2.6.4.8l4.2 2.8-2.7-4.1L12 7z"/></svg>
					</button>
				</div>
				<!-- Quantity stepper (the −/+ side-button stepper) wrapped in the
				     standard .uf-field--float pattern so its label follows the same
				     above/inside positioning rules as every other field. -->
				<div class="uf-field uf-field--float">
					<div class="uf-stepper" role="group" aria-label="Stepper">
						<button type="button" aria-label="Decrease">
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6 11H17.5V12.5H6V11Z"/></svg>
						</button>
						<input id="f-stepper" type="number" value="2" min="0" />
						<button type="button" aria-label="Increase">
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M11 12.5V17.5H12.5V12.5H17.5V11H12.5V6H11V11H6V12.5H11Z"/></svg>
						</button>
					</div>
					<label for="f-stepper">Stepper</label>
				</div>
				<!-- Select -->
				<div class="uf-field uf-field--float">
					<select id="f-sort">
						<option>Option 1</option>
						<option>Option 2</option>
						<option>Option 3</option>
					</select>
					<label for="f-sort">Select an option</label>
				</div>
			</div>
		</section>

		<section>
			<div class="uf-grid">
				<div>
					<h2>Single day calendar</h2>
					<div class="uf-inline-cal-wrap">
						<input type="date" id="f-calendar-single" data-inline-calendar placeholder=" " style="display:none" />
					</div>
				</div>
				<div>
					<h2>Date range calendar</h2>
					<div class="uf-inline-cal-wrap">
						<input type="date" id="f-calendar-range" data-inline-calendar data-requires-date-range placeholder=" " style="display:none" />
					</div>
				</div>
			</div>
		</section>


		<section aria-labelledby="s-choice">
			<h2 id="s-choice">Checkbox &amp; radio</h2>
			<div class="uf-grid">
				<div>
					<label class="uf-check"><input type="checkbox" /> Option label</label>
					<label class="uf-check"><input type="checkbox" checked /> Option label (optional)</label>
					<label class="uf-check"><input type="checkbox" disabled /> Option label (unavailable)</label>
				</div>
				<div>
					<label class="uf-radio"><input type="radio" name="ship" checked /> Option label</label>
					<label class="uf-radio"><input type="radio" name="ship" /> Option label (optional)</label>
					<label class="uf-radio"><input type="radio" name="ship" disabled /> Option label (unavailable)</label>
				</div>
			</div>
		</section>

		<section aria-labelledby="s-toggle-group">
			<h2 id="s-toggle-group">Toggle group</h2>
			<div class="uf-toggle-group" role="radiogroup" aria-label="Toggle group example">
				<label class="uf-toggle-group__option">
					<input type="radio" name="tg-demo" value="delivery" checked />
					<span class="uf-toggle-group__header">
						<svg class="uf-toggle-group__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3 6.75C3 5.784 3.784 5 4.75 5H15V7.313l.05.027 5.056 2.73.394.212v3.468a1.75 1.75 0 01-1.75 1.75h-.012a2.5 2.5 0 11-4.975 0H9.737a2.5 2.5 0 11-4.975 0H3V6.75zM13.5 14V6.5H4.75a.25.25 0 00-.25.25V14h.965a2.493 2.493 0 011.785-.75c.7 0 1.332.287 1.785.75H13.5zm4.535 0h.715a.25.25 0 00.25-.25v-2.573l-4-2.16v4.568a2.487 2.487 0 011.25-.335c.7 0 1.332.287 1.785.75zM6.282 15.5a1.002 1.002 0 00.968 1.25 1 1 0 10-.968-1.25zm9 0a1 1 0 101.937.498 1 1 0 00-1.938-.498z"/></svg>
						<span class="uf-toggle-group__label">Option A</span>
					</span>
					<span class="uf-toggle-group__sublabel">Description</span>
				</label>
				<label class="uf-toggle-group__option">
					<input type="radio" name="tg-demo" value="pickup" />
					<span class="uf-toggle-group__header">
						<svg class="uf-toggle-group__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" clip-rule="evenodd" d="M19.75 11H21V8.667L19.875 4H4.125L3 8.667V11h1.25v8.75h15.5V11zm-1.5 0H5.75v7.25H10V13h4v5.25h4.25V11zm-5.5-5.5h2.067l.486 3.24.028.76H12.75v-4zm-3.567 0h2.067v4H8.669l.028-.76.486-3.24zm7.615 3.1l-.464-3.1h2.36l.806 3.345V9.5h-2.668l-.034-.9zM7.666 5.5h-2.36L4.5 8.845V9.5h2.668l.034-.9.464-3.1z"/></svg>
						<span class="uf-toggle-group__label">Option B</span>
					</span>
					<span class="uf-toggle-group__sublabel">Description</span>
				</label>
			</div>
		</section>

		<script>
		/**
		 * Trailing-logos overflow: progressive icon hiding via ResizeObserver.
		 *
		 * As each `.uf-field--trailing-logos` field gets narrower, hide brand
		 * icons one at a time from right to left and surface the hidden count
		 * in the "+N" badge that lives inside `.uf-field__logos` as the last
		 * child. The badge is sized exactly like a brand icon (30x18) so
		 * swapping icons for it doesn't shift the trailing slot's width — the
		 * field's own width is purely parent-driven and never affected by
		 * which icons are shown.
		 */
		(function () {
			var fields = document.querySelectorAll('.uf-field--trailing-logos');
			if (!fields.length || typeof ResizeObserver !== 'function') return;

			// Field-width brackets → number of brand icons to show.
			// Thresholds derived from the real geometry: each icon/badge is
			// 30px wide, the gap between items is 4px, and the value needs
			// ~170px (the long "4242 4242 4242 4242" string). With the input's
			// padding-right synced to the actual logos width, the field just
			// needs ~322px to fit all 4 icons plus the value comfortably.
			// 360px gives a ~40px safety buffer at the top.
			function visibleIconCount(fieldWidth, total) {
				if (fieldWidth >= 360) return total;
				if (fieldWidth >= 320) return Math.max(0, total - 1);
				if (fieldWidth >= 280) return Math.max(0, total - 2);
				if (fieldWidth >= 240) return Math.max(0, total - 3);
				return 0;
			}

			function apply(field) {
				var logos = field.querySelector('.uf-field__logos');
				if (!logos) return;
				var icons = logos.querySelectorAll('img');
				var badge = logos.querySelector('.uf-field__logos-more');
				if (!icons.length) return;

				var width = field.getBoundingClientRect().width;
				var visible = visibleIconCount(width, icons.length);
				var hidden = icons.length - visible;

				for (var i = 0; i < icons.length; i++) {
					icons[i].style.display = (i < visible) ? '' : 'none';
				}
				if (badge) {
					if (hidden > 0) {
						badge.textContent = '+' + hidden;
						badge.style.display = 'inline-flex';
					} else {
						badge.style.display = 'none';
					}
				}

				// After hiding icons, the .uf-field__logos span has shrunk
				// to fit only the visible items. The input's padding-right
				// is calc(--_uf-pad-x + --uf-field-logos-w) — if we don't
				// update --uf-field-logos-w here, the input keeps reserving
				// the original (e.g. 180px) width, leaving "phantom padding"
				// between the value text and the icons. Sync them up so
				// the value can use all the space the icons aren't using.
				var logosW = logos.getBoundingClientRect().width;
				field.style.setProperty('--uf-field-logos-w', Math.ceil(logosW) + 'px');
			}

			var obs = new ResizeObserver(function (entries) {
				for (var i = 0; i < entries.length; i++) {
					apply(entries[i].target);
				}
			});
			fields.forEach(function (f) { obs.observe(f); });
		})();
		</script>
	</main>
	<?php wp_footer(); ?>
</body>
</html>
	<?php
	exit;
} );

/**
 * Save the Showcase toolbar's four variant choices. Hit via wp-admin-ajax
 * from the Showcase's "Save" button. Admin-only — this controls site-wide
 * visual state.
 */
add_action( 'wp_ajax_uf_save_field_settings', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
	}
	check_ajax_referer( 'uf_save_field_settings', '_nonce' );
	$allowed = array(
		'border'  => array( 'outline', 'underline', 'none' ),
		'fill'    => array( 'filled', 'unfilled' ),
		'corners' => array( 'sharp', 'rounded', 'pill' ),
		'label'   => array( 'inside', 'above' ),
	);
	foreach ( $allowed as $key => $vals ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST[ $key ] ) && in_array( $_POST[ $key ], $vals, true ) ) {
			update_option( 'uf_field_' . $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
		}
	}
	wp_send_json_success();
} );
