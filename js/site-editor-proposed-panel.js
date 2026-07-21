/**
 * PROPOSED "Form controls" screen.
 *
 * The third screen in the Styles list, built to the proposed design: controls
 * grouped by the DECISION they express (Layout / Colors / Border & Shadow)
 * rather than by the CSS property they happen to write.
 *
 * Built from @wordpress/components primitives only — no private APIs, nothing
 * unlocked. Where the design calls for a row shape the public components do
 * not ship (the Colors rows, the swatch-prefixed border input), it is composed
 * from Dropdown + ColorPalette + ColorIndicator + UnitControl, which is the
 * same composition the native panels use internally.
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
	// Verified against the WordPress Design System MCP: ItemGroup/Item is the
	// canonical grouped-row container (isBordered / isSeparated / isRounded /
	// size), and InputControlPrefixWrapper is the documented way to pad an
	// input prefix. Both were previously hand-rolled in CSS here.
	var ItemGroup                = C.__experimentalItemGroup;
	var Item                     = C.__experimentalItem;
	var PrefixWrapper            = C.__experimentalInputControlPrefixWrapper;

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

	function shadowCssFor( slug ) {
		return slug ? 'var(--wp--preset--shadow--' + slug + ')' : null;
	}

	function shadowName( slug ) {
		var match = shadowPresets().filter( function ( p ) { return p.slug === slug; } )[ 0 ];
		return match ? match.name : 'Drop shadow';
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
	 * A grid of the theme's shadow presets plus a "None" cell, mirroring the
	 * native shadow picker (a swatch grid rather than a colour palette). Each
	 * swatch previews the preset by wearing it as its own box-shadow.
	 */
	function ShadowPicker( props ) {
		var cell = function ( key, label, shadowCss, active, onClick ) {
			return el( Button, {
				key: key,
				className: 'ufc-proposed__shadow-swatch' + ( active ? ' is-active' : '' ),
				onClick: onClick,
				label: label,
				showTooltip: true,
				style: shadowCss ? { boxShadow: shadowCss } : undefined,
			}, shadowCss ? null : el( ShadowIcon ) );
		};
		return el( 'div', { className: 'ufc-proposed__shadow-grid' },
			cell( 'none', 'None', null, ! props.value, function () { props.onChange( null ); } ),
			shadowPresets().map( function ( p ) {
				return cell( p.slug, p.name, p.shadow, props.value === p.slug, function () {
					props.onChange( p.slug );
				} );
			} )
		);
	}

	function ShadowIcon() {
		return el( 'svg', {
			className: 'ufc-proposed__shadow-icon',
			width: 24, height: 24, viewBox: '0 0 24 24', 'aria-hidden': 'true', focusable: 'false',
		},
			el( 'circle', { cx: 12, cy: 12, r: 4, fill: 'none', stroke: 'currentColor', strokeWidth: 1.5 } ),
			el( 'path', {
				d: 'M12 3.5v2M12 18.5v2M3.5 12h2M18.5 12h2M6 6l1.4 1.4M16.6 16.6L18 18M18 6l-1.4 1.4M7.4 16.6L6 18',
				stroke: 'currentColor', strokeWidth: 1.5, strokeLinecap: 'round',
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
		var i = useState( null );       var shadow      = i[ 0 ]; var setShadow      = i[ 1 ];

		function commitWidth( value ) {
			var n = px( value );
			if ( ! n ) { n = MIN_BORDER_WIDTH; }
			setBorderWidth( Math.min( MAX_BORDER_WIDTH, Math.max( MIN_BORDER_WIDTH, n ) ) + 'px' );
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
				fillColor:   'filled' === fill ? bgCss : null,
				textColor:   textCss,
				borderColor: borderCss,
				borderWidth: borderWidth,
				shadow:      shadowCssFor( shadow ),
			} );
		}, [ label, fill, borderStyle, textCss, bgCss, borderCss, borderWidth, radius, shadow ] );

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
					onChange: setFill,
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
			el( VStack, { spacing: 3 },
				Section( 'Colors' ),
				el( ItemGroup, { isBordered: true, isSeparated: true },
					el( ColorRow, { label: 'Text',       color: textColor, onChange: setTextColor } ),
					el( ColorRow, { label: 'Background', color: bgColor,   onChange: setBgColor } )
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
							min: MIN_BORDER_WIDTH,
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
							min: MIN_BORDER_WIDTH,
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

				// Shadow row opens a picker of the theme's shadow presets. The
				// choice drives the preview through a new `shadow` bridge key
				// (see site-editor-form-fields.php), which sets --uf-field-shadow.
				el( 'div', null,
					Label( 'Shadow' ),
					el( ItemGroup, { isBordered: true, isSeparated: true },
						el( Dropdown, {
							className: 'ufc-proposed__row-dropdown',
							popoverProps: { placement: 'left-start', offset: 36 },
							renderToggle: function ( toggle ) {
								return el( Item, {
									onClick: toggle.onToggle,
									'aria-expanded': toggle.isOpen,
								},
									el( HStack, { spacing: 3, justify: 'flex-start' },
										el( ShadowIcon ),
										el( 'span', null, shadowName( shadow ) )
									)
								);
							},
							renderContent: function () {
								return el( 'div', { className: 'ufc-proposed__palette' },
									el( ShadowPicker, { value: shadow, onChange: setShadow } )
								);
							},
						} )
					)
				)
			)
		);
	}

	window.UFC = window.UFC || {};
	window.UFC.ProposedPanel = ProposedPanel;
} )( window.wp );
