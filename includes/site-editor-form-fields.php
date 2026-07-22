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
// postMessage listener on every front-end page (canvas live preview)
// ---------------------------------------------------------------------------

add_action(
	'wp_footer',
	function () {
		// Listener must be injected on every front-end page (and every
		// Site Editor iframe preview) so changes in the "Forms fields"
		// sidebar reach the canvas regardless of which template the user
		// is previewing. Gating this to `?uf_showcase=1` broke the live
		// preview on Checkout, My Account, Coming Soon, etc.
		// Read from the global styles entity. Two important details:
		//
		//  1. `wp_get_global_settings($path)` falls back to the FULL
		//     settings tree when `$path` does not exist in the cascade.
		//     A missing `custom.ufFields` therefore returns top-level
		//     setting keys like `border` (block-supports declaration:
		//     `{color: true, style: true, width: true, radius: true}`).
		//     The strict `is_string()` checks below reject that shape
		//     naturally — anything that isn't the expected string payload
		//     falls through to the hard-coded default.
		//
		//  2. Legacy `uf_field_*` options are intentionally NOT consulted.
		//     They pre-date the entity-backed save and stayed out of sync
		//     (e.g. `uf_field_fill = 'filled'` survived even after the
		//     panel default was changed to `'unfilled'`), so reading them
		//     resurfaced wrong defaults. The entity is the single source
		//     of truth now.
		$uf = wp_get_global_settings( array( 'custom', 'ufFields' ) );
		if ( ! is_array( $uf ) ) {
			$uf = array();
		}

		$border     = ( isset( $uf['border'] )    && is_string( $uf['border'] ) )    ? $uf['border']    : 'outline';
		$fill       = ( isset( $uf['fill'] )      && is_string( $uf['fill'] ) )      ? $uf['fill']      : 'unfilled';
		$radius     = ( isset( $uf['radius'] )    && is_string( $uf['radius'] ) )    ? $uf['radius']    : '4px';
		$label      = ( isset( $uf['label'] )     && is_string( $uf['label'] ) )     ? $uf['label']     : 'inside';
		$fill_color = ( isset( $uf['fillColor'] ) && is_string( $uf['fillColor'] ) ) ? $uf['fillColor'] : '';

		$r       = floatval( $radius );
		$corners = $r === 0.0 ? 'sharp' : ( $r >= 999 ? 'pill' : 'rounded' );
		?>
		<style id="uf-field-inset-rules">
		/* Dynamic inset: label left-edge and icon right-edge track border-radius.
		   textarea is included so its value's left edge lines up with the
		   text inputs (both get inset + inset-extra); without it the textarea
		   value sat ~inset-extra px to the left. */
		:is( .uf-showcase, .uf-checkout ) .uf-field > input,
		:is( .uf-showcase, .uf-checkout ) .uf-field > select,
		:is( .uf-showcase, .uf-checkout ) .uf-field > textarea {
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
				// Border stroke weight and colour. Both optional: callers that
				// omit these keys leave the tokens untouched, so the saved
				// four-setting payload behaves exactly as before.
				if ( d.borderWidth !== undefined ) {
					d.borderWidth
						? ( root.style.setProperty( '--uf-field-border-width', d.borderWidth ),
							document.body.style.setProperty( '--uf-field-border-width', d.borderWidth ) )
						: ( root.style.removeProperty( '--uf-field-border-width' ),
							document.body.style.removeProperty( '--uf-field-border-width' ) );
				}
				if ( d.borderColor !== undefined ) {
					d.borderColor
						? ( root.style.setProperty( '--uf-field-border', d.borderColor ),
							document.body.style.setProperty( '--uf-field-border', d.borderColor ) )
						: ( root.style.removeProperty( '--uf-field-border' ),
							document.body.style.removeProperty( '--uf-field-border' ) );
				}
				if ( d.textColor !== undefined ) {
					d.textColor
						? ( root.style.setProperty( '--wp--custom--field--text', d.textColor ),
							document.body.style.setProperty( '--wp--custom--field--text', d.textColor ) )
						: ( root.style.removeProperty( '--wp--custom--field--text' ),
							document.body.style.removeProperty( '--wp--custom--field--text' ) );
				}
				if ( d.shadow !== undefined ) {
					d.shadow
						? ( root.style.setProperty( '--uf-field-shadow', d.shadow ),
							document.body.style.setProperty( '--uf-field-shadow', d.shadow ) )
						: ( root.style.removeProperty( '--uf-field-shadow' ),
							document.body.style.removeProperty( '--uf-field-shadow' ) );
				}
				if ( d.fillColor !== undefined ) {
					d.fillColor
						? root.style.setProperty( '--wp--custom--field--fill', d.fillColor )
						: root.style.removeProperty( '--wp--custom--field--fill' );
				}
				// Optional marker. Only the "Form controls (proposed)" screen
				// sends `labelAlign`; when set, it flips on the proposed-only
				// preview overrides in uf-proposed-preview.css (resting-label
				// alignment). No other screen sends the key, so the guard keeps
				// this inert everywhere else.
				if ( d.labelAlign !== undefined ) {
					d.labelAlign
						? ( root.setAttribute( 'data-uf-label-align', d.labelAlign ),
							document.body.setAttribute( 'data-uf-label-align', d.labelAlign ) )
						: ( root.removeAttribute( 'data-uf-label-align' ),
							document.body.removeAttribute( 'data-uf-label-align' ) );
				}
			}

			apply( init );

			window.addEventListener( 'message', function ( e ) {
				if ( e.data && e.data.type === 'uf-field-settings' ) apply( e.data );
			} );
		} )();
		</script>
		<?php
	},
	20
);
