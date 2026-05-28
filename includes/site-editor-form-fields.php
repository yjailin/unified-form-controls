<?php
/**
 * Wires the "Forms fields" screen in Site Editor → Styles into the global
 * styles entity so changes go through the standard "Review changes" flow.
 *
 * Responsibilities:
 *  1. Inject a postMessage listener into every front-end page so the canvas
 *     reflects changes in real time while the user edits in the sidebar.
 *
 * Settings are stored under settings.custom.ufFields in the global styles
 * entity (wp_global_styles CPT). On the frontend, values are read from there
 * first, falling back to the legacy uf_field_* WP options for sites that
 * previously used the old REST-based save.
 *
 * @package UnifiedFormControls
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ---------------------------------------------------------------------------
// Settings listener — applied wherever the showcase renders.
//
// On the frontend (`/?uf_showcase=1`) and inside the Site Editor's canvas
// iframe, this hooks `wp_footer`. On the Form Controls admin page (where
// the showcase renders inline in the admin DOM), it hooks `admin_footer`
// — the admin shell doesn't fire `wp_footer`, only `admin_footer`. Same
// callback, both hooks; the gate inside narrows to the actual showcase
// request via `ufc_showcase_panel_is_active()`.
// ---------------------------------------------------------------------------

function ufc_emit_showcase_settings_listener() {
		// Showcase-only scope — see plugin main file.
		if ( ! function_exists( 'ufc_showcase_panel_is_active' ) || ! ufc_showcase_panel_is_active() ) {
			return;
		}
		// Read from global styles first; fall back to legacy WP options.
		$uf         = wp_get_global_settings( array( 'custom', 'ufFields' ) ) ?: array();
		$border     = ! empty( $uf['border'] )    ? $uf['border']    : get_option( 'uf_field_border',     'outline'  );
		$fill       = ! empty( $uf['fill'] )      ? $uf['fill']      : get_option( 'uf_field_fill',       'unfilled' );
		$radius     = ! empty( $uf['radius'] )    ? $uf['radius']    : get_option( 'uf_field_radius',     '4px'      );
		$label      = ! empty( $uf['label'] )     ? $uf['label']     : get_option( 'uf_field_label',      'inside'   );
		$fill_color = isset( $uf['fillColor'] )   ? $uf['fillColor'] : get_option( 'uf_field_fill_color', ''         );

		$r       = floatval( $radius );
		$corners = $r === 0.0 ? 'sharp' : ( $r >= 999 ? 'pill' : 'rounded' );
		?>
		<style id="uf-field-inset-rules">
		/* Dynamic inset: label left-edge and icon right-edge track border-radius. */
		:is( .uf-showcase, .uf-checkout ) .uf-field > input,
		:is( .uf-showcase, .uf-checkout ) .uf-field > select {
			padding-left: calc( max( 16px, 0.875rem ) * 0.55 + var( --uf-field-inset-extra, 0px ) ) !important;
		}
		:is( .uf-showcase, .uf-checkout ) .uf-field > label {
			left: calc( max( 16px, 0.875rem ) * 0.55 + var( --uf-field-inset-extra, 0px ) ) !important;
		}
		:is( .uf-showcase, .uf-checkout ):not([data-border="underline"][data-fill="unfilled"]) .uf-field__icon,
		:is( .uf-showcase, .uf-checkout ):not([data-border="underline"][data-fill="unfilled"]) .uf-field__logos {
			right: calc( max( 16px, 0.875rem ) * 0.55 + var( --uf-field-inset-extra, 0px ) ) !important;
		}
		</style>
		<script id="uf-field-settings-listener">
		( function () {
			// Use localStorage if the sidebar wrote dirty values there (site editor
			// editing session), otherwise fall back to the PHP-saved values.
			var saved = <?php echo wp_json_encode(
				array(
					'border'    => $border,
					'fill'      => $fill,
					'corners'   => $corners,
					'label'     => $label,
					'radius'    => $radius,
					'fillColor' => $fill_color,
				)
			); ?>;
			var init = saved;
			try {
				var ls = localStorage.getItem( 'uf_field_settings' );
				if ( ls ) { init = JSON.parse( ls ); }
			} catch ( _ ) {}

			function apply( d ) {
				var root = document.querySelector( '.uf-showcase, .uf-checkout' ) || document.body;
				if ( d.border  !== undefined ) { root.setAttribute( 'data-border',  d.border );  document.body.setAttribute( 'data-border',  d.border );  }
				if ( d.fill    !== undefined ) { root.setAttribute( 'data-fill',    d.fill );    document.body.setAttribute( 'data-fill',    d.fill );    }
				if ( d.corners !== undefined ) { root.setAttribute( 'data-corners', d.corners ); document.body.setAttribute( 'data-corners', d.corners ); }
				if ( d.label   !== undefined ) { root.setAttribute( 'data-label',   d.label );   document.body.setAttribute( 'data-label',   d.label );   }
				if ( d.radius  !== undefined ) {
					var n       = parseFloat( d.radius ) || 0;
					var fieldEl = root.querySelector( '.uf-field' );
					var halfH   = fieldEl ? fieldEl.getBoundingClientRect().height / 2 : 27.5;
					if ( halfH <= 0 ) halfH = 27.5;
					var applied = Math.min( n, halfH );
					var extra   = ( applied / halfH ) * 9;
					root.style.setProperty( '--uf-field-radius', applied + 'px' );
					document.body.style.setProperty( '--uf-field-radius', applied + 'px' );
					root.style.setProperty( '--uf-field-inset-extra', extra + 'px' );
					document.body.style.setProperty( '--uf-field-inset-extra', extra + 'px' );
				}
				if ( d.fillColor !== undefined ) {
					d.fillColor
						? root.style.setProperty( '--wp--custom--field--fill', d.fillColor )
						: root.style.removeProperty( '--wp--custom--field--fill' );
				}
			}

			apply( init );

			window.addEventListener( 'message', function ( e ) {
				if ( e.data && e.data.type === 'uf-field-settings' ) apply( e.data );
			} );
		} )();
		</script>
		<?php
}
add_action( 'wp_footer',    'ufc_emit_showcase_settings_listener', 20 );
add_action( 'admin_footer', 'ufc_emit_showcase_settings_listener', 20 );
