<?php
/**
 * Replaces the showcase form-controls toolbar with a Gutenberg-style
 * side panel on the unified-forms showcase page (?uf_showcase=1).
 *
 * @package UnifiedFormControls
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the current request is the showcase page.
 */
function ufc_showcase_panel_is_active() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	return isset( $_GET['uf_showcase'] ) && '1' === $_GET['uf_showcase'];
}

/**
 * Return the list of style variations available in the active theme's
 * /styles/ directory. Each entry: [ 'slug' => '03-dusk', 'title' => 'Dusk' ].
 * Cached per-request because we read it from disk.
 */
function ufc_showcase_get_variations() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$cache = array();
	$dir   = get_template_directory() . '/styles';
	if ( ! is_dir( $dir ) ) {
		return $cache;
	}
	foreach ( glob( $dir . '/*.json' ) as $path ) {
		$slug = basename( $path, '.json' );
		$data = json_decode( file_get_contents( $path ), true );
		$cache[] = array(
			'slug'  => $slug,
			'title' => isset( $data['title'] ) ? $data['title'] : $slug,
		);
	}
	return $cache;
}

/**
 * Return the slug of the variation requested via `?uf_variation=...`,
 * or '' if none / invalid. Preview-only — does not persist to the DB.
 */
function ufc_showcase_get_active_variation() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( empty( $_GET['uf_variation'] ) ) {
		return '';
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$requested = sanitize_key( wp_unslash( $_GET['uf_variation'] ) );
	foreach ( ufc_showcase_get_variations() as $v ) {
		if ( $v['slug'] === $requested ) {
			return $requested;
		}
	}
	return '';
}

/**
 * Merge the requested variation's theme.json data on top of the USER layer
 * (highest priority) so the preview wins over anything saved in the DB.
 * Preview-only — does not persist; the actual saved variation is untouched.
 */
add_filter( 'wp_theme_json_data_user', function ( $theme_json ) {
	if ( ! ufc_showcase_panel_is_active() ) {
		return $theme_json;
	}
	$slug = ufc_showcase_get_active_variation();
	if ( '' === $slug ) {
		return $theme_json;
	}
	$path = get_template_directory() . '/styles/' . $slug . '.json';
	if ( ! is_readable( $path ) ) {
		return $theme_json;
	}
	$data = json_decode( file_get_contents( $path ), true );
	if ( is_array( $data ) && method_exists( $theme_json, 'update_with' ) ) {
		$theme_json->update_with( $data );
	}
	return $theme_json;
} );

/**
 * Load wp-components on the showcase page.
 */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! ufc_showcase_panel_is_active() ) {
		return;
	}
	wp_enqueue_script( 'wp-element' );
	wp_enqueue_script( 'wp-components' );
	wp_enqueue_script( 'wp-block-editor' );
	wp_enqueue_script( 'wp-data' );
	wp_enqueue_script( 'wp-core-data' );
	wp_enqueue_script( 'wp-i18n' );
	wp_enqueue_style( 'wp-components' );
	wp_enqueue_style( 'wp-block-editor' );

	// Pass theme color palette so ColorPalette has swatches.
	$colors  = array();
	$palette = wp_get_global_settings( array( 'color', 'palette' ) );
	foreach ( array( 'theme', 'custom', 'default' ) as $source ) {
		if ( ! empty( $palette[ $source ] ) ) {
			$colors = $palette[ $source ];
			break;
		}
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$is_embed = isset( $_GET['uf_embed'] );
	wp_add_inline_script(
		'wp-components',
		'window.__UF_SHOWCASE_COLORS=' . wp_json_encode( $colors ) . ';'
		. 'window.__UF_SHOWCASE_VARIATIONS=' . wp_json_encode( ufc_showcase_get_variations() ) . ';'
		. 'window.__UF_SHOWCASE_ACTIVE_VARIATION=' . wp_json_encode( ufc_showcase_get_active_variation() ) . ';'
		. 'window.__UF_SHOWCASE_EMBED=' . wp_json_encode( $is_embed ) . ';',
		'before'
	);
} );

/**
 * Inject the panel markup + rendering script.
 * Priority 99 ensures this runs after wp_print_footer_scripts (priority 20)
 * so wp-components is already in the DOM when our render script executes.
 */
add_action( 'wp_footer', function () {
	if ( ! ufc_showcase_panel_is_active() ) {
		return;
	}
	?>
	<style>
	/* Hide the original top toolbar. */
	.uf-controls { display: none !important; }

	/* Right-hand settings panel. */
	#gb-showcase-settings {
		position: fixed;
		right: 0;
		top: var( --wp-admin--admin-bar--height, 0px );
		bottom: 0;
		width: 300px;
		background: #fff;
		border-left: 1px solid rgba( 0, 0, 0, 0.1 );
		box-shadow: -2px 0 8px rgba( 0, 0, 0, 0.06 );
		z-index: 9999;
		overflow-y: auto;

		/* Isolate from page theme — editor design system values. */
		color: #1e1e1e;
		font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
		font-size: 13px;
		line-height: 1.4;

		/* Reset theme.json preset values so wp-components use editor sizes/colors. */
		--wp--preset--font-size--small: 11px;
		--wp--preset--font-size--medium: 13px;
		--wp--preset--font-size--large: 20px;
		--wp--preset--font-size--x-large: 42px;
		--wp--preset--color--contrast: #1e1e1e;
		--wp--preset--color--base: #f9f9f9;

		/* Match the Fonts admin page's primary-action color token.
		   The WP 7.0 design refresh ships `--wp-admin-theme-color` at
		   #3858e9 (a vibrant blue); classic admin pages still resolve
		   it to #007cba (older teal). Forcing the new value here means
		   the panel's `<Button variant="primary">` (Update) renders
		   with the same hue as the Fonts page button. */
		--wp-admin-theme-color: #3858e9;
		--wp-components-color-accent: #3858e9;
		--wp-components-color-accent-inverted: #eff0f2;
	}
	#gb-showcase-settings .components-panel { border: none; }

	/* Heading in embed mode (Form Controls admin page iframe).
	   The panel renders inside the showcase template, which loads
	   the active theme's stylesheets — so a plain <h1> would inherit
	   the theme's heading rules (font-family, size, weight, color,
	   text-transform, etc.). `all: revert` rolls back ALL author-
	   origin declarations to the user-agent default, killing any
	   theme leakage in one shot. We then re-apply the exact computed
	   values inspected on the Fonts admin page H1
	   (`Appearance > Fonts`, class `admin-ui-page__header-title`):
	     font-size  20px
	     font-weight 499
	     color      #1d2327
	     line-height 32px
	   Display + box-sizing + the remaining text properties are
	   pinned to their normal defaults so a theme can't reintroduce
	   them via inheritance after the revert. */
	#gb-showcase-settings h1 {
		all: revert;
		display: block;
		box-sizing: border-box;
		margin: 0;
		padding: 0;
		font-family: -apple-system, system-ui, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
		font-size: 20px;
		font-weight: 499;
		font-style: normal;
		color: #1d2327;
		line-height: 32px;
		letter-spacing: normal;
		text-transform: none;
		text-decoration: none;
		text-shadow: none;
	}

	/* wp-block-editor/style.css forces .components-button to 36px globally;
	   restore the 40px default for buttons that opt in via __next40pxDefaultSize. */
	#gb-showcase-settings .components-button.is-next-40px-default-size { height: 40px; }
	#gb-showcase-settings .components-tools-panel { border-top: none !important; row-gap: 0 !important; }
	#gb-showcase-settings .components-tools-panel-header { display: none !important; }
	#gb-showcase-settings .block-editor-tools-panel-color-gradient-settings__item { margin-top: 0 !important; }

	/* Keep showcase content from hiding under the panel. */
	body { padding-right: 300px !important; }

	<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
	<?php if ( isset( $_GET['uf_embed'] ) ) : ?>
	/* Embed mode (Form Controls admin page iframe): panel pinned LEFT,
	   showcase preview to the right. Reverses the default right-pinned
	   layout. Also swap the panel's visual edge: default has
	   `border-left + box-shadow going left` (separating from showcase
	   on its right). In embed mode the panel is on the LEFT side, so
	   the separator + shadow flip to the right. Without this, the
	   border-left would be invisible (touching viewport edge) but still
	   eats 1px of inner width, creating a 1px L/R asymmetry visible
	   when controls are centered inside the panel. */
	#gb-showcase-settings {
		left: 0; right: auto;
		/* No border on either side — even a 1px border-right would land
		   on the inner-right edge (or outside with content-box) and
		   create a 1px L/R asymmetry against the 16px padding. The
		   box-shadow below carries enough visual separation between
		   panel and showcase content. */
		border-left: none;
		border-right: none;
		box-shadow: 2px 0 8px rgba( 0, 0, 0, 0.06 );
	}
	body { padding-left: 300px !important; padding-right: 0 !important; }
	<?php endif; ?>

	/* Dynamic inset: shifts only horizontal positioning as corners get more rounded. */
	.uf-showcase .uf-field > input,
	.uf-showcase .uf-field > select {
		padding-left: calc( max( 16px, 0.875rem ) * 0.55 + var( --uf-field-inset-extra, 0px ) ) !important;
	}
	.uf-showcase .uf-field > label {
		left: calc( max( 16px, 0.875rem ) * 0.55 + var( --uf-field-inset-extra, 0px ) ) !important;
	}
	.uf-showcase:not([data-border="underline"][data-fill="unfilled"]) .uf-field__icon,
	.uf-showcase:not([data-border="underline"][data-fill="unfilled"]) .uf-field__logos {
		right: calc( max( 16px, 0.875rem ) * 0.55 + var( --uf-field-inset-extra, 0px ) ) !important;
	}
	</style>
	<div id="gb-showcase-settings"></div>
	<script>
	( function () {
		var container = document.getElementById( 'gb-showcase-settings' );
		if ( ! container ) return;

		var root = document.querySelector( '.uf-showcase' );
		if ( ! root ) return;

		// ---- helpers --------------------------------------------------------

		function applyDim( dim, val ) {
			root.setAttribute( 'data-' + dim, val );
			document.body.setAttribute( 'data-' + dim, val );
		}

		function applyRadius( val ) {
			var n       = parseFloat( val ) || 0;
			var applied = Math.min( n, naturalHalfH );
			root.style.setProperty( '--uf-field-radius', applied + 'px' );
			document.body.style.setProperty( '--uf-field-radius', applied + 'px' );
			var preset  = applied === 0 ? 'sharp' : applied >= naturalHalfH * 0.99 ? 'pill' : 'rounded';
			root.setAttribute( 'data-corners', preset );
			document.body.setAttribute( 'data-corners', preset );
			var extra   = naturalHalfH > 0 ? ( applied / naturalHalfH ) * 9 : 0;
			root.style.setProperty( '--uf-field-inset-extra', extra + 'px' );
			document.body.style.setProperty( '--uf-field-inset-extra', extra + 'px' );
		}

		function applyFillColor( color ) {
			color
				? root.style.setProperty( '--wp--custom--field--fill', color )
				: root.style.removeProperty( '--wp--custom--field--fill' );
		}

		function doSave( border, fill, label, status, btn ) {
			var nonceEl = document.getElementById( 'uf_save_field_nonce' );
			var body    = new URLSearchParams();
			body.set( 'action',  'uf_save_field_settings' );
			body.set( '_nonce',  nonceEl ? nonceEl.value : '' );
			body.set( 'border',  border );
			body.set( 'fill',    fill );
			body.set( 'corners', root.getAttribute( 'data-corners' ) || 'sharp' );
			body.set( 'label',   label );
			if ( status ) status( 'Saving…' );
			if ( btn ) btn( true );
			fetch( '/wp-admin/admin-ajax.php', { method: 'POST', credentials: 'same-origin', body: body } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( r ) { if ( status ) status( r && r.success ? 'Saved.' : 'Save failed.' ); } )
				.catch( function ()  { if ( status ) status( 'Save failed.' ); } )
				.finally( function () { if ( btn ) btn( false ); } );
		}

		// ---- read initial state ---------------------------------------------

		var naturalHalfH = 27; // updated to real value once wp.components are ready
		var RADIUS_MAP = { sharp: '0px', rounded: '4px', pill: '9999px' };
		var initBorder     = root.getAttribute( 'data-border' )  || 'outline';
		var initFill       = root.getAttribute( 'data-fill' )    || 'unfilled';
		var initCorners    = root.getAttribute( 'data-corners' ) || 'sharp';
		var initLabel      = root.getAttribute( 'data-label' )   || 'inside';
		var initRadius     = getComputedStyle( root ).getPropertyValue( '--uf-field-radius' ).trim()
		                     || RADIUS_MAP[ initCorners ] || '0px';
		var initFillColor  = root.style.getPropertyValue( '--wp--custom--field--fill' ) || null;

		// ---- wait for wp.components -----------------------------------------

		var attempts = 0;
		var poll = setInterval( function () {
			if ( ++attempts > 60 ) { clearInterval( poll ); return; }
			if (
				! window.wp ||
				! wp.element ||
				! wp.components ||
				! wp.blockEditor ||
				! wp.data ||
				! wp.coreData ||
				! ( wp.components.ToggleGroupControl || wp.components.__experimentalToggleGroupControl ) ||
				! ( wp.components.ToggleGroupControlOption || wp.components.__experimentalToggleGroupControlOption ) ||
				! wp.components.__experimentalToolsPanel ||
				! wp.blockEditor.__experimentalColorGradientSettingsDropdown ||
				! ( wp.components.UnitControl || wp.components.__experimentalUnitControl )
			) return;
			clearInterval( poll );

			// Measure natural field height before any radius is applied.
			var fieldEl0 = root.querySelector( '.uf-field' );
			if ( fieldEl0 ) naturalHalfH = fieldEl0.getBoundingClientRect().height / 2;

			var el                              = wp.element.createElement;
			var useState                        = wp.element.useState;
			var useEffect                       = wp.element.useEffect;
			var useSelect                       = wp.data.useSelect;
			var useEntityProp                   = wp.coreData.useEntityProp;
			var Panel                           = wp.components.Panel;
			var ToggleGroupControl              = wp.components.ToggleGroupControl       || wp.components.__experimentalToggleGroupControl;
			var ToggleGroupControlOption        = wp.components.ToggleGroupControlOption || wp.components.__experimentalToggleGroupControlOption;
			var ToolsPanel                      = wp.components.__experimentalToolsPanel;
			var Button                          = wp.components.Button;
			var UnitControl                     = wp.components.UnitControl              || wp.components.__experimentalUnitControl;
			var SelectControl                   = wp.components.SelectControl;
			var ColorGradientSettingsDropdown   = wp.blockEditor.__experimentalColorGradientSettingsDropdown;
			var colors                          = window.__UF_SHOWCASE_COLORS || [];
			var variations                      = window.__UF_SHOWCASE_VARIATIONS || [];
			var activeVariation                 = window.__UF_SHOWCASE_ACTIVE_VARIATION || '';
			var isEmbed                         = !! window.__UF_SHOWCASE_EMBED;

			// Apply initial radius so inset is correct on first load.
			applyRadius( initRadius );

			function FormSettings() {
				/*
				 * Entity-state model — same one the Fonts admin page uses.
				 *
				 * Reads `settings.custom.ufFields` directly from the global
				 * styles entity via `useEntityProp`. Edits flow through
				 * `setSettings`, which marks the entity record dirty in
				 * @wordpress/core-data's store. The Update button then
				 * derives its disabled / isBusy state from
				 * `hasEditsForEntityRecord` and `isSavingEntityRecord` —
				 * so it's disabled by default, enables only when there
				 * are unsaved changes, and shows the busy spinner while
				 * the REST PUT is in flight. Same behavior the Fonts
				 * page button has, with no private-API dependency.
				 */
				var globalStylesId = useSelect( function ( select ) {
					var core = select( 'core' );
					return core && core.__experimentalGetCurrentGlobalStylesId
						? core.__experimentalGetCurrentGlobalStylesId()
						: null;
				}, [] );

				var entity = useEntityProp(
					'root',
					'globalStyles',
					'settings',
					globalStylesId || undefined
				);
				var settings    = entity[ 0 ] || {};
				var setSettings = entity[ 1 ];

				var ufFields  = ( settings.custom && settings.custom.ufFields ) || {};
				var border    = ufFields.border    || initBorder;
				var fill      = ufFields.fill      || initFill;
				var radius    = ufFields.radius    || initRadius;
				var label     = ufFields.label     || initLabel;
				var fillColor = ufFields.fillColor || null;

				var isDirty = useSelect( function ( select ) {
					var core = select( 'core' );
					return ! globalStylesId || ! core || ! core.hasEditsForEntityRecord
						? false
						: core.hasEditsForEntityRecord( 'root', 'globalStyles', globalStylesId );
				}, [ globalStylesId ] );

				var isSaving = useSelect( function ( select ) {
					var core = select( 'core' );
					return ! globalStylesId || ! core || ! core.isSavingEntityRecord
						? false
						: core.isSavingEntityRecord( 'root', 'globalStyles', globalStylesId );
				}, [ globalStylesId ] );

				function patch( next ) {
					if ( ! setSettings ) return;
					var prevCustom = settings.custom || {};
					var prevFields = prevCustom.ufFields || {};
					setSettings( Object.assign( {}, settings, {
						custom: Object.assign( {}, prevCustom, {
							ufFields: Object.assign( {}, prevFields, next ),
						} ),
					} ) );
				}

				// Live preview: apply each setting to the showcase DOM
				// whenever the entity values change. Keeps the visual
				// state in sync with the (possibly unsaved) entity state.
				useEffect( function () {
					applyDim( 'border', border );
					applyDim( 'fill',   fill );
					applyDim( 'label',  label );
					applyRadius( radius );
					applyFillColor( fillColor );
				}, [ border, fill, radius, label, fillColor ] );

				function save() {
					if ( ! globalStylesId ) return;
					wp.data
						.dispatch( 'core' )
						.saveEditedEntityRecord( 'root', 'globalStyles', globalStylesId );
				}

				/*
				 * In embed mode (Form Controls admin page iframe), render the
				 * controls bare — no Panel wrappers, no section headers, no
				 * dividers, no Style Variation picker. Just the 4 controls +
				 * the Update button, all stacked in a single padded column.
				 * In non-embed mode (direct `/?uf_showcase=1` visit), keep
				 * the original Panel-wrapped layout with section headers and
				 * the variation picker on top.
				 */
				var variationPicker = ( ! isEmbed && variations.length > 0 ) && el( Panel, { header: 'Style Variation' },
					el( 'div', { style: { padding: '16px' } },
						el( SelectControl, {
							label: 'Theme variation',
							help: 'Preview only — does not save site-wide.',
							value: activeVariation,
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize: true,
							options: [ { label: '— Default (theme.json) —', value: '' } ].concat(
								variations.map( function ( v ) {
									return { label: v.title, value: v.slug };
								} )
							),
							onChange: function ( v ) {
								var url = new URL( window.location.href );
								if ( v ) {
									url.searchParams.set( 'uf_variation', v );
								} else {
									url.searchParams.delete( 'uf_variation' );
								}
								window.location.assign( url.toString() );
							}
						} )
					)
				);

				var controlsInner = el( 'div', { style: { padding: '16px 25px', display: 'flex', flexDirection: 'column', gap: '16px' } },

						// In embed mode (Form Controls admin page iframe), the outer
						// page H1 is removed; the panel needs its own heading. In
						// non-embed mode the Panel wrapper already provides section
						// headers so this would be redundant — skip it.
						// Styling lives in the inline `<style>` block above
						// (`#gb-showcase-settings h1` — uses `all: revert` to
						// strip any theme leakage, then re-applies the Fonts
						// admin page H1 computed values).
						isEmbed && el( 'h1', null, 'Form controls' ),

						el( ToggleGroupControl, {
							label: 'Border style',
							value: border,
							isBlock: true,
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize: true,
							onChange: function ( v ) {
								// Border = None forces Fill = Filled
								// (an unfilled borderless field would be invisible).
								if ( v === 'none' && fill !== 'filled' ) {
									patch( { border: v, fill: 'filled' } );
								} else {
									patch( { border: v } );
								}
							}
						},
							el( ToggleGroupControlOption, { value: 'outline',   label: 'Outline'   } ),
							el( ToggleGroupControlOption, { value: 'underline', label: 'Underline' } ),
							el( ToggleGroupControlOption, { value: 'none',      label: 'None'      } )
						),

						el( UnitControl, {
							label: 'Corner radius',
							value: radius,
							units: [ { value: 'px', label: 'px', default: 0 } ],
							min: 0,
							max: Math.floor( naturalHalfH ),
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize: true,
							onChange: function ( v ) {
								patch( { radius: v || '0px' } );
							}
						} ),

						el( ToggleGroupControl, {
							label: 'Background',
							value: fill,
							isBlock: true,
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize: true,
							onChange: function ( v ) {
								// Switching away from Filled also clears any custom fill color.
								if ( v !== 'filled' ) {
									patch( { fill: v, fillColor: null } );
								} else {
									patch( { fill: v } );
								}
							}
						},
							el( ToggleGroupControlOption, { value: 'unfilled', label: 'Unfilled', disabled: border === 'none' } ),
							el( ToggleGroupControlOption, { value: 'filled',   label: 'Filled'   } )
						),

						false && fill === 'filled' && el( ToolsPanel, {
							label: 'Fill Color',
							resetAll: function () { patch( { fillColor: null } ); },
							panelId: 'uf-fill-color',
							style: { padding: 0 },
						},
							el( ColorGradientSettingsDropdown, {
								settings: [ {
									colorValue: fillColor || undefined,
									onColorChange: function ( v ) { patch( { fillColor: v || null } ); },
									label: 'Fill Color',
									clearable: true,
									isShownByDefault: true,
								} ],
								colors: colors,
								gradients: [],
								disableCustomGradients: true,
								panelId: 'uf-fill-color',
							} )
						),

						el( ToggleGroupControl, {
							label: 'Label position',
							value: label,
							isBlock: true,
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize: true,
							onChange: function ( v ) { patch( { label: v } ); }
						},
							el( ToggleGroupControlOption, { value: 'inside', label: 'Inside' } ),
							el( ToggleGroupControlOption, { value: 'above',  label: 'Above'  } )
						),

						el( Button, {
							variant: 'primary',
							__next40pxDefaultSize: true,
							style: { width: '100%', justifyContent: 'center' },
							onClick: save,
							disabled: ! isDirty || isSaving || ! globalStylesId,
							isBusy: isSaving
						}, isSaving
							? 'Saving…'
							: 'Update'
						)
				);

				return el( wp.element.Fragment, null,
					variationPicker,
					isEmbed ? controlsInner : el( Panel, { header: 'Form Controls' }, controlsInner )
				);
			}

			if ( wp.element.createRoot ) {
				wp.element.createRoot( container ).render( el( FormSettings ) );
			} else {
				wp.element.render( el( FormSettings ), container );
			}
		}, 100 );
	} )();
	</script>
	<?php
}, 99 );
