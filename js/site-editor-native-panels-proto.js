/**
 * PROTOTYPE — Form controls built from the shared block-editor panels.
 *
 * Renders the SAME components/panels an individual block's inspector and the
 * native Global Styles screens use: `BorderPanel` (BorderBoxControl +
 * BorderRadiusControl) and `ColorPanel`, each inside its own ToolsPanel with
 * the standard reset menu. Values are a theme.json-shaped style object.
 *
 * ⚠️ PRIVATE API — READ BEFORE SHIPPING
 * These panels are only reachable through `unlock( wp.blockEditor.privateApis )`,
 * and `@wordpress/private-apis` gates that behind a hardcoded allowlist of CORE
 * module names (packages/private-apis/src/implementation.ts). A plugin is not on
 * that list, so the call below has to claim to be `@wordpress/block-editor`.
 * That works today but is exactly what the API forbids: the consent string may
 * change in ANY release without notice, and the panels carry no back-compat
 * guarantee. This file is a prototype for evaluation, not a shipping path.
 *
 * Scope notes:
 *  - State is LOCAL (useState), deliberately not persisted, so the prototype
 *    cannot clobber the real `settings.custom.ufFields`.
 *  - It still drives the existing live preview through the same onPreview
 *    bridge, so the canvas responds exactly like the current panel.
 *
 * Exposed as `window.UFC.NativePanelsPrototype`.
 *
 * @package UnifiedFormControls
 */
( function ( wp ) {
	if ( ! wp || ! wp.element || ! wp.components || ! wp.data ) {
		return;
	}

	// ---- unlock the shared panels ------------------------------------------
	var shared = null;
	try {
		var consent = 'I acknowledge private features are not for use in themes or plugins and doing so will break in the next version of WordPress.';
		var api = wp.privateApis.__dangerousOptInToUnstableAPIsOnlyForCoreModules(
			consent,
			'@wordpress/block-editor'
		);
		shared = api.unlock( wp.blockEditor.privateApis );
	} catch ( e ) {
		shared = null;
	}
	// No panels → no prototype item. The existing panel is unaffected.
	if ( ! shared || typeof shared.BorderPanel !== 'function' ) {
		return;
	}

	var BorderPanel = shared.BorderPanel;
	// NOTE: in Gutenberg 23.5.1 the background COLOUR row lives in
	// `BackgroundPanel`, not `ColorPanel` — `ColorPanel` is now Elements-only
	// (Captions / Button / Heading / Link). Using ColorPanel here renders an
	// empty "Elements" group. This split differs between Gutenberg revisions,
	// which is itself a data point about depending on these panels.
	var BackgroundPanel = shared.BackgroundPanel;

	var el        = wp.element.createElement;
	var useState  = wp.element.useState;
	var useMemo   = wp.element.useMemo;
	var useEffect = wp.element.useEffect;
	var useSelect = wp.data.useSelect;
	var C         = wp.components;

	var ToggleGroupControl       = C.ToggleGroupControl       || C.__experimentalToggleGroupControl;
	var ToggleGroupControlOption = C.ToggleGroupControlOption || C.__experimentalToggleGroupControlOption;
	var VStack                   = C.__experimentalVStack;

	// ---- preset <-> standard border value mapping ---------------------------
	// The whole point: "Underline" is not a bespoke concept, it's a standard
	// SPLIT border with only the bottom side visible. BorderPanel/BorderBoxControl
	// already read and write this shape (it branches on hasSplitBorders()).

	// Build the standard border value for a preset, carrying over whatever width
	// and colour the user has already chosen (which may live on the uniform
	// border or on the bottom side of a split one).
	function presetToBorder( preset, current ) {
		var radius = current && current.radius;
		var src    = ( current && ( current.bottom || current ) ) || {};
		var color  = src.color;
		var width  = src.width && ( parseFloat( src.width ) || 0 ) > 0 ? src.width : '1px';
		var side   = function ( on ) {
			return { style: on ? 'solid' : 'none', width: on ? width : '0px', color: color };
		};

		if ( preset === 'underline' ) {
			return {
				top: side( false ), right: side( false ),
				bottom: side( true ), left: side( false ),
				radius: radius,
			};
		}
		if ( preset === 'none' ) {
			return { style: 'none', width: '0px', color: color, radius: radius };
		}
		return { style: 'solid', width: width, color: color, radius: radius };
	}

	// BorderRadiusControl writes EITHER a string ("4px") or, once the per-corner
	// control is engaged, an object of four corners. The preview bridge only
	// carries a single radius, so collapse the object — largest corner wins.
	function radiusToPx( radius ) {
		if ( ! radius ) { return 0; }
		if ( typeof radius === 'object' ) {
			return [ 'topLeft', 'topRight', 'bottomLeft', 'bottomRight' ].reduce(
				function ( max, k ) { return Math.max( max, parseFloat( radius[ k ] ) || 0 ); },
				0
			);
		}
		return parseFloat( radius ) || 0;
	}

	// Colour values come back in Gutenberg's internal preset encoding
	// ("var:preset|color|base-2"), which is not valid CSS. Translate to the
	// custom property the theme actually emits.
	function toCssColor( value ) {
		if ( ! value ) { return null; }
		var match = /^var:preset\|color\|(.+)$/.exec( value );
		return match ? 'var(--wp--preset--color--' + match[ 1 ] + ')' : value;
	}

	function NativePanelsPrototype( props ) {
		var onPreview = props.onPreview || function () {};
		var maxRadius = props.maxRadius || 27;

		// Local prototype state, theme.json style shape.
		var st       = useState( { border: { style: 'solid', width: '1px', radius: '4px' } } );
		var style    = st[ 0 ];
		var setStyle = st[ 1 ];

		// The preset is held explicitly rather than derived from the border value.
		// BorderBoxControl flattens a split border back to a uniform one as soon
		// as you touch width or colour, so deriving the preset made editing the
		// width silently turn Underline into Outline.
		var ps        = useState( 'outline' );
		var preset    = ps[ 0 ];
		var setPreset = ps[ 1 ];

		// Label position has no standard Global Styles control — kept custom.
		var lb       = useState( 'inside' );
		var label    = lb[ 0 ];
		var setLabel = lb[ 1 ];

		// Real theme.json settings (palette, supports) straight from the editor,
		// so the panels show the site's actual colors like a block inspector does.
		var rawSettings = useSelect( function ( select ) {
			var store = select( 'core/block-editor' );
			var s = store && store.getSettings ? store.getSettings() : null;
			return ( s && s.__experimentalFeatures ) || {};
		}, [] );

		// Constrain the shared panels to just what a form control actually has.
		// Each row is gated by its own flag, so turning a flag off removes the
		// row (and its group) — no CSS hiding, no forking the panels:
		//
		//   Shadow   — useHasShadowControl() = `!! settings.shadow && presets`.
		//              Unset `shadow` and the panel title reverts from
		//              "Border & Shadow" to plain "Border".
		//   Elements — the Captions / Button / Heading / Link rows are each
		//              gated on settings.color.{caption,button,heading,link},
		//              so switching those off drops the whole "Elements" group
		//              and leaves ColorPanel rendering only Background.
		//
		// Gradients are left as the theme provides them, so Background offers
		// colour + gradient exactly like the native Background panel.
		var settings = useMemo( function () {
			// NOTE: our panel renders in a detached React root, so the
			// block-editor store's `__experimentalFeatures` is not populated in
			// the registry we see — `getSettings()` comes back bare. Fall back
			// to the theme palette we already localise so the colour controls
			// still offer the site's real palette.
			var localized = ( window.__UFC_SE && window.__UFC_SE.colors ) || [];
			var palette   = ( rawSettings.color && rawSettings.color.palette )
				|| { theme: localized };

			return {
				// Border + radius only. No `shadow` key at all, so
				// useHasShadowControl() is false and the panel title stays
				// "Border" instead of "Border & Shadow".
				border: { color: true, radius: true, style: true, width: true },
				// Background panel, colour row only: image and gradient are each
				// gated on their own flag, so switching them off leaves a single
				// plain "Color" row under a "Background" heading.
				background: { backgroundImage: false, gradient: false },
				color: {
					background: true,
					custom: true,
					customGradient: false,
					palette: palette,
				},
			};
		}, [ rawSettings ] );

		var radiusPx = radiusToPx( style.border && style.border.radius );
		var bg       = style.color && style.color.background;
		var fillCss  = toCssColor( bg );
		var hasFill  = !! bg;

		// Drive the existing live-preview bridge (same postMessage contract).
		useEffect( function () {
			onPreview( {
				border:    preset,
				fill:      hasFill ? 'filled' : 'unfilled',
				label:     label,
				radius:    radiusPx + 'px',
				fillColor: fillCss,
				corners:   radiusPx === 0
					? 'sharp'
					: ( radiusPx >= maxRadius * 0.99 ? 'pill' : 'rounded' ),
			} );
		}, [ preset, hasFill, label, radiusPx, fillCss ] );

		return el( VStack, { spacing: 4 },

			// Preset row — writes STANDARD border values (split for Underline).
			el( ToggleGroupControl, {
				label: 'Border style',
				value: preset,
				isBlock: true,
				__nextHasNoMarginBottom: true,
				__next40pxDefaultSize: true,
				help: 'Presets write standard border values — Underline is a bottom-only split border.',
				onChange: function ( v ) {
					setPreset( v );
					setStyle( Object.assign( {}, style, { border: presetToBorder( v, style.border ) } ) );
				},
			},
				el( ToggleGroupControlOption, { value: 'outline',   label: 'Outline'   } ),
				el( ToggleGroupControlOption, { value: 'underline', label: 'Underline' } ),
				el( ToggleGroupControlOption, { value: 'none',      label: 'None'      } )
			),

			// ---- the shared block-editor panels -----------------------------
			el( BorderPanel, {
				value: style,
				// Re-assert the preset's shape after every edit. BorderBoxControl
				// collapses a split border to a uniform one the moment you change
				// width or colour, which would silently demote Underline to
				// Outline; the preset stays the owner of the border SHAPE, while
				// the panel owns width, colour and radius.
				onChange: function ( next ) {
					if ( 'outline' !== preset ) {
						next = Object.assign( {}, next, {
							border: presetToBorder( preset, next.border ),
						} );
					}
					setStyle( next );
				},
				settings: settings,
				panelId: 'uf-native-border',
			} ),

			BackgroundPanel ? el( BackgroundPanel, {
				value: style,
				onChange: setStyle,
				settings: settings,
				panelId: 'uf-native-background',
			} ) : null,

			// Label position — no standard control expresses this.
			el( ToggleGroupControl, {
				label: 'Label position',
				value: label,
				isBlock: true,
				__nextHasNoMarginBottom: true,
				__next40pxDefaultSize: true,
				help: 'No standard Global Styles control expresses this — stays custom.',
				onChange: function ( v ) { setLabel( v ); },
			},
				el( ToggleGroupControlOption, { value: 'inside', label: 'Inside' } ),
				el( ToggleGroupControlOption, { value: 'above',  label: 'Above'  } )
			)
		);
	}

	window.UFC = window.UFC || {};
	window.UFC.NativePanelsPrototype = NativePanelsPrototype;
} )( window.wp );
