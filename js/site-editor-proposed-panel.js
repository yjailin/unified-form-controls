/**
 * PROPOSED "Form controls" screen.
 *
 * The third screen in the Styles list, built to the proposed design: controls
 * grouped by the DECISION they express (Layout / Colors / Border & Shadow)
 * rather than by the CSS property they happen to write.
 *
 * Built from public @wordpress/components primitives, with ONE exception: the
 * shadow control. Where the design calls for a row shape the public components
 * do not ship (the Colors rows, the swatch-prefixed border input), it is
 * composed from Dropdown + ColorPalette + ColorIndicator + UnitControl, the
 * same composition the native panels use internally.
 *
 * The exception is Shadow. There is NO public shadow-presets control, so rather
 * than reinvent one this screen renders the block editor's own `ShadowPopover`
 * (the "Drop shadow" picker under Styles > Blocks > Button) by way of the
 * Global Styles `BorderPanel`, reached through a PRIVATE API. See the
 * BorderPanel unlock note below.
 *
 * Scope notes, matching the other prototype screen:
 *  - State is LOCAL (useState) and deliberately not persisted, so this screen
 *    cannot clobber the real `settings.custom.ufFields`.
 *  - It drives the live preview through the same postMessage bridge as the
 *    other two screens, so the canvas responds identically.
 *
 * NOT WIRED (no pipeline support yet):
 *  - Label position "Middle": no `[data-label="middle"]` rule exists in
 *    uf-forms.css, so it currently renders as Inside.
 *
 * Shadow IS wired, with one caveat: fields use box-shadow for their hover/focus
 * ring, and those rules carry `!important`, so the drop shadow is replaced while
 * a field is hovered or focused. Resolving that cleanly would mean composing the
 * two shadows in every interactive state — flagged, not done.
 *
 * Exposed as `window.UFC.ProposedPanel`.
 *
 * @package UnifiedFormControls
 */
( function ( wp ) {
	if ( ! wp || ! wp.element || ! wp.components ) {
		return;
	}

	var el        = wp.element.createElement;
	var Fragment  = wp.element.Fragment;
	var useState  = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var C         = wp.components;

	var ToggleGroupControl       = C.ToggleGroupControl       || C.__experimentalToggleGroupControl;
	var ToggleGroupControlOption = C.ToggleGroupControlOption || C.__experimentalToggleGroupControlOption;
	var UnitControl              = C.UnitControl              || C.__experimentalUnitControl;
	var RangeControl             = C.RangeControl;
	var Heading                  = C.__experimentalHeading;
	var VStack                   = C.__experimentalVStack;
	var HStack                   = C.__experimentalHStack;
	var Dropdown                 = C.Dropdown;
	var ColorPalette             = C.ColorPalette;
	var ColorIndicator           = C.ColorIndicator;
	var Button                   = C.Button;
	var BaseControl              = C.BaseControl;
	var Disabled                 = C.Disabled;
	// Verified against the WordPress Design System MCP: ItemGroup/Item is the
	// canonical grouped-row container (isBordered / isSeparated / isRounded /
	// size), and InputControlPrefixWrapper is the documented way to pad an
	// input prefix. Both were previously hand-rolled in CSS here.
	var ItemGroup                = C.__experimentalItemGroup;
	var Item                     = C.__experimentalItem;
	var PrefixWrapper            = C.__experimentalInputControlPrefixWrapper;

	// The shadow picker is NOT a public component. In the block editor it is the
	// internal `ShadowPopover` (class `block-editor-global-styles__shadow-dropdown`),
	// rendered only by the Global Styles `BorderPanel` — the same "Border & Shadow"
	// panel you see under Styles > Blocks > Button. It is not exported standalone,
	// so the only way to use the SAME component (rather than reimplement it) is to
	// render BorderPanel constrained to just its shadow row. That reaches it
	// through `unlock( wp.blockEditor.privateApis )`, a PRIVATE API — the same
	// caveat that applies to the "Form controls (native)" screen. This is a real
	// finding: there is no public shadow-presets control to compose from.
	var BorderPanel = null;
	try {
		var consent = 'I acknowledge private features are not for use in themes or plugins and doing so will break in the next version of WordPress.';
		var api = wp.privateApis.__dangerousOptInToUnstableAPIsOnlyForCoreModules(
			consent,
			'@wordpress/block-editor'
		);
		BorderPanel = api.unlock( wp.blockEditor.privateApis ).BorderPanel;
	} catch ( e ) {
		BorderPanel = null;
	}

	// Fields never want a heavy stroke, but 0 is a valid choice: this design has
	// no "None" border style, so a zero width is how you make a field borderless.
	// Unlike BorderControl — which hardcodes min 0 / max 100 and exposes no way
	// to change them — a composed control takes the range as ordinary props.
	var MIN_BORDER_WIDTH = 0;
	var MAX_BORDER_WIDTH = 4;

	function themeColors() {
		return ( window.__UFC_SE && window.__UFC_SE.colors ) || [];
	}

	function shadowPresets() {
		return ( window.__UFC_SE && window.__UFC_SE.shadows ) || [];
	}

	// Settings that make BorderPanel render ONLY its shadow row: border colour /
	// style / width / radius are all off, shadow presets are on. `defaultPresets`
	// keeps the theme presets as the palette.
	function shadowOnlySettings() {
		return {
			border: { color: false, style: false, width: false, radius: false },
			shadow: { presets: { default: shadowPresets() }, defaultPresets: true },
		};
	}

	// BorderPanel stores the chosen shadow as Gutenberg's preset encoding
	// ("var:preset|shadow|deep"), which is not valid CSS. Translate to the custom
	// property the theme actually emits, so the preview follows style variations.
	function shadowStyleToCss( shadowVal ) {
		if ( ! shadowVal ) { return null; }
		var match = /^var:preset\|shadow\|(.+)$/.exec( shadowVal );
		return match ? 'var(--wp--preset--shadow--' + match[ 1 ] + ')' : shadowVal;
	}

	function px( value ) {
		var n = parseFloat( value );
		return isNaN( n ) ? 0 : n;
	}

	// Colour state is { value, slug }. ColorPalette's onChange third argument is
	// the palette slug, which lets us emit the theme's preset custom property
	// instead of a baked hex — so the field follows a style-variation change
	// the way the rest of the site does. `selectedSlug` is also what keeps the
	// right swatch marked when two palette entries share a colour value.
	function cssFor( color ) {
		if ( ! color || ! color.value ) { return null; }
		return color.slug
			? 'var(--wp--preset--color--' + color.slug + ')'
			: color.value;
	}

	/** Small caps control label, matching the ToggleGroupControl labels. */
	function Label( text ) {
		return el( BaseControl.VisualLabel, { className: 'ufc-proposed__label' }, text );
	}

	/** Group heading — "Layout", "Colors", "Border & Shadow". */
	function Section( title ) {
		return el( Heading, { level: 3, className: 'ufc-proposed__heading' }, title );
	}

	/**
	 * One swatch + label row, as used by the Colors group. Composed the same
	 * way the native colour rows are: a Dropdown whose toggle is an indicator
	 * plus a label, and whose content is a ColorPalette.
	 */
	function Palette( props ) {
		return el( ColorPalette, {
			colors: themeColors(),
			value: props.color && props.color.value,
			selectedSlug: ( props.color && props.color.slug ) || undefined,
			onChange: function ( newColor, index, slug ) {
				props.onChange( { value: newColor || null, slug: slug || null } );
			},
			// The palette's own Clear button — the same reset affordance the
			// native colour popovers ship. Clearing sets value+slug to null,
			// which returns the toggle to the unset (slashed) swatch below.
			clearable: true,
			__experimentalIsRenderedInSidebar: true,
			'aria-label': props.label,
		} );
	}

	function ColorRow( props ) {
		return el( Dropdown, {
			className: 'ufc-proposed__row-dropdown',
			popoverProps: { placement: 'left-start', offset: 36 },
			renderToggle: function ( toggle ) {
				return el( Item, {
					onClick: toggle.onToggle,
					'aria-expanded': toggle.isOpen,
				},
					el( HStack, { spacing: 3, justify: 'flex-start' },
						// Pass undefined (not 'transparent') when unset: ColorIndicator
						// then renders its built-in "no colour" diagonal slash, which
						// is exactly what the native panels show for an unset value.
						el( ColorIndicator, {
							colorValue: ( props.color && props.color.value ) || undefined,
						} ),
						el( 'span', null, props.label )
					)
				);
			},
			renderContent: function () {
				return el( Palette, {
					label: props.label,
					color: props.color,
					onChange: props.onChange,
				} );
			},
		} );
	}

	/**
	 * Colour swatch used as the prefix inside the Border width input.
	 * InputControlPrefixWrapper supplies the size-aware padding — previously
	 * this was a hand-written margin.
	 */
	function SwatchPrefix( props ) {
		return el( PrefixWrapper, { variant: 'icon' },
			el( Dropdown, {
				popoverProps: { placement: 'left-start', offset: 36 },
				renderToggle: function ( toggle ) {
					return el( Button, {
						className: 'ufc-proposed__swatch-toggle',
						onClick: toggle.onToggle,
						'aria-expanded': toggle.isOpen,
						label: 'Border color',
					}, el( ColorIndicator, {
						colorValue: ( props.color && props.color.value ) || undefined,
					} ) );
				},
				renderContent: function () {
					return el( Palette, {
						label: 'Border color',
						color: props.color,
						onChange: props.onChange,
					} );
				},
			} )
		);
	}

	/**
	 * @param {Object}   props
	 * @param {Function} props.onPreview   Called with the bridge payload on mount + every change.
	 * @param {number}   [props.maxRadius] Max corner radius in px. Default 27.
	 */
	function ProposedPanel( props ) {
		var onPreview = props.onPreview || function () {};
		var maxRadius = props.maxRadius || 27;

		// Defaults mirror the proposed design's default state.
		var a = useState( 'inside' );   var label       = a[ 0 ]; var setLabel       = a[ 1 ];
		var b = useState( 'filled' );   var fill        = b[ 0 ]; var setFill        = b[ 1 ];
		var c = useState( 'outline' );  var borderStyle = c[ 0 ]; var setBorderStyle = c[ 1 ];
		var EMPTY = { value: null, slug: null };
		var d = useState( EMPTY );      var textColor   = d[ 0 ]; var setTextColor   = d[ 1 ];
		var e = useState( EMPTY );      var bgColor     = e[ 0 ]; var setBgColor     = e[ 1 ];
		var f = useState( EMPTY );      var borderColor = f[ 0 ]; var setBorderColor = f[ 1 ];
		var g = useState( '1px' );      var borderWidth = g[ 0 ]; var setBorderWidth = g[ 1 ];
		var h = useState( '0px' );      var radius      = h[ 0 ]; var setRadius      = h[ 1 ];
		// Shadow is a theme.json-shaped style object, because that is what
		// BorderPanel reads and writes (value.shadow = "var:preset|shadow|<slug>").
		var i = useState( {} );         var shadowStyle = i[ 0 ]; var setShadowStyle = i[ 1 ];

		// Unfilled is a real layout state, not "filled with no colour". A field
		// with no fill needs a visible edge, so the border cannot be 0 there:
		// the minimum width rises to 1 while Unfilled.
		var isFilled     = 'filled' === fill;
		var minWidth     = isFilled ? MIN_BORDER_WIDTH : 1;

		function commitWidth( value ) {
			var n = px( value );
			setBorderWidth( Math.min( MAX_BORDER_WIDTH, Math.max( minWidth, n ) ) + 'px' );
		}

		function commitFill( value ) {
			setFill( value );
			// Entering Unfilled with a 0 border would leave the field with no
			// visible edge — bump it to the new minimum.
			if ( 'unfilled' === value && px( borderWidth ) < 1 ) {
				setBorderWidth( '1px' );
			}
		}

		function commitRadius( value ) {
			var n = Math.min( maxRadius, Math.max( 0, px( value ) ) );
			setRadius( n + 'px' );
		}

		var textCss   = cssFor( textColor );
		var bgCss     = cssFor( bgColor );
		var borderCss = cssFor( borderColor );

		// Same postMessage contract as the other two screens.
		useEffect( function () {
			var n = px( radius );
			onPreview( {
				border:      borderStyle,
				fill:        fill,
				label:       label,
				radius:      n + 'px',
				corners:     n === 0 ? 'sharp' : ( n >= maxRadius * 0.99 ? 'pill' : 'rounded' ),
				fillColor:   isFilled ? bgCss : null,
				textColor:   textCss,
				borderColor: borderCss,
				borderWidth: borderWidth,
				// Shadow applies only to filled fields (an outline-only field has
				// no surface to cast from), so it is gated on the fill state, not
				// just on whether a shadow value happens to be set.
				shadow:      isFilled ? shadowStyleToCss( shadowStyle.shadow ) : null,
			} );
		}, [ label, fill, borderStyle, textCss, bgCss, borderCss, borderWidth, radius, shadowStyle.shadow ] );

		return el( VStack, { spacing: 6, className: 'ufc-proposed' },

			// ---- Layout: the decisions that change field STRUCTURE ---------
			el( VStack, { spacing: 4 },
				Section( 'Layout' ),

				el( ToggleGroupControl, {
					label: 'Label position',
					value: label,
					isBlock: true,
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
					onChange: setLabel,
				},
					el( ToggleGroupControlOption, { value: 'inside', label: 'Inside' } ),
					el( ToggleGroupControlOption, { value: 'above',  label: 'Above'  } ),
					el( ToggleGroupControlOption, { value: 'middle', label: 'Middle' } )
				),

				el( ToggleGroupControl, {
					label: 'Fields background',
					value: fill,
					isBlock: true,
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
					onChange: commitFill,
				},
					el( ToggleGroupControlOption, { value: 'filled',   label: 'Filled'   } ),
					el( ToggleGroupControlOption, { value: 'unfilled', label: 'Unfilled' } )
				),

				el( ToggleGroupControl, {
					label: 'Border style',
					value: borderStyle,
					isBlock: true,
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
					onChange: setBorderStyle,
				},
					el( ToggleGroupControlOption, { value: 'outline',   label: 'Outline'   } ),
					el( ToggleGroupControlOption, { value: 'underline', label: 'Underline' } )
				)
			),

			// ---- Colors ----------------------------------------------------
			// Background is only meaningful for a filled field, so its row is
			// hidden entirely under Unfilled — not shown as an empty swatch.
			el( VStack, { spacing: 3 },
				Section( 'Colors' ),
				el( ItemGroup, { isBordered: true, isSeparated: true },
					el( ColorRow, { label: 'Text', color: textColor, onChange: setTextColor } ),
					isFilled
						? el( ColorRow, { label: 'Background', color: bgColor, onChange: setBgColor } )
						: null
				)
			),

			// ---- Border & Shadow -------------------------------------------
			el( VStack, { spacing: 4 },
				Section( 'Border & Shadow' ),

				el( 'div', null,
					Label( 'Border' ),
					el( HStack, { spacing: 3, alignment: 'center' },
						el( UnitControl, {
							className: 'ufc-proposed__width',
							label: 'Border width',
							hideLabelFromVision: true,
							value: borderWidth,
							units: [ { value: 'px', label: 'px', default: 1 } ],
							min: minWidth,
							max: MAX_BORDER_WIDTH,
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize: true,
							prefix: el( SwatchPrefix, { color: borderColor, onChange: setBorderColor } ),
							onChange: commitWidth,
						} ),
						el( RangeControl, {
							className: 'ufc-proposed__slider',
							label: 'Border width',
							hideLabelFromVision: true,
							value: px( borderWidth ),
							min: minWidth,
							max: MAX_BORDER_WIDTH,
							step: 1,
							withInputField: false,
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize: true,
							onChange: commitWidth,
						} )
					)
				),

				el( 'div', null,
					Label( 'Radius' ),
					el( HStack, { spacing: 3, alignment: 'center' },
						el( UnitControl, {
							className: 'ufc-proposed__radius',
							label: 'Radius',
							hideLabelFromVision: true,
							value: radius,
							units: [ { value: 'px', label: 'px', default: 0 } ],
							min: 0,
							max: Math.floor( maxRadius ),
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize: true,
							onChange: commitRadius,
						} ),
						el( RangeControl, {
							className: 'ufc-proposed__slider',
							label: 'Radius',
							hideLabelFromVision: true,
							value: px( radius ),
							min: 0,
							max: Math.floor( maxRadius ),
							step: 1,
							withInputField: false,
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize: true,
							onChange: commitRadius,
						} )
					)
				),

				// Shadow: the block editor's OWN shadow control, not a bespoke
				// one. BorderPanel is constrained (via shadowOnlySettings) to
				// render just its Shadow row — the real `ShadowPopover`, the same
				// one under Styles > Blocks > Button. Its choice drives the
				// preview through the `shadow` bridge key, which sets
				// --uf-field-shadow (see site-editor-form-fields.php).
				//
				// A shadow only reads on a filled field, so under Unfilled the
				// whole control is disabled (greyed, non-interactive) via the
				// `Disabled` component rather than removed — the preview already
				// drops the shadow when unfilled (see the effect above).
				BorderPanel
					? el( Disabled, { isDisabled: ! isFilled },
						el( BorderPanel, {
							value: shadowStyle,
							onChange: setShadowStyle,
							settings: shadowOnlySettings(),
							panelId: 'uf-proposed-shadow',
						} )
					)
					: null
			)
		);
	}

	window.UFC = window.UFC || {};
	window.UFC.ProposedPanel = ProposedPanel;
} )( window.wp );
