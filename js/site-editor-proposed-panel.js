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
 * NOT WIRED (no pipeline support yet — see the notes on each control):
 *  - Label position "Middle": no `[data-label="middle"]` rule exists in
 *    uf-forms.css, so it currently renders as Inside.
 *  - Drop shadow: there is no field shadow token, and the fields already use
 *    box-shadow for the hover/focus ring, so this needs a design decision
 *    before it can be wired.
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

	// Fields never want a heavy stroke. Unlike BorderControl — which hardcodes
	// min 0 / max 100 and exposes no way to change them — a composed control
	// takes the range as ordinary props.
	var MIN_BORDER_WIDTH = 1;
	var MAX_BORDER_WIDTH = 4;

	function themeColors() {
		return ( window.__UFC_SE && window.__UFC_SE.colors ) || [];
	}

	function px( value ) {
		var n = parseFloat( value );
		return isNaN( n ) ? 0 : n;
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
	function ColorRow( props ) {
		return el( 'div', { className: 'ufc-proposed__row' },
			el( Dropdown, {
				className: 'ufc-proposed__row-dropdown',
				popoverProps: { placement: 'left-start', offset: 36 },
				renderToggle: function ( toggle ) {
					return el( Button, {
						className: 'ufc-proposed__row-toggle',
						onClick: toggle.onToggle,
						'aria-expanded': toggle.isOpen,
					},
						el( ColorIndicator, { colorValue: props.value || 'transparent' } ),
						el( 'span', { className: 'ufc-proposed__row-label' }, props.label )
					);
				},
				renderContent: function () {
					return el( 'div', { className: 'ufc-proposed__palette' },
						el( ColorPalette, {
							colors: themeColors(),
							value: props.value,
							onChange: props.onChange,
							clearable: true,
							__experimentalIsRenderedInSidebar: true,
						} )
					);
				},
			} )
		);
	}

	/** Colour swatch used as the prefix inside the Border width input. */
	function SwatchPrefix( props ) {
		return el( Dropdown, {
			className: 'ufc-proposed__swatch',
			popoverProps: { placement: 'left-start', offset: 36 },
			renderToggle: function ( toggle ) {
				return el( Button, {
					className: 'ufc-proposed__swatch-toggle',
					onClick: toggle.onToggle,
					'aria-expanded': toggle.isOpen,
					label: 'Border color',
				}, el( ColorIndicator, { colorValue: props.value || 'transparent' } ) );
			},
			renderContent: function () {
				return el( 'div', { className: 'ufc-proposed__palette' },
					el( ColorPalette, {
						colors: themeColors(),
						value: props.value,
						onChange: props.onChange,
						clearable: true,
						__experimentalIsRenderedInSidebar: true,
					} )
				);
			},
		} );
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
		var d = useState( null );       var textColor   = d[ 0 ]; var setTextColor   = d[ 1 ];
		var e = useState( null );       var bgColor     = e[ 0 ]; var setBgColor     = e[ 1 ];
		var f = useState( null );       var borderColor = f[ 0 ]; var setBorderColor = f[ 1 ];
		var g = useState( '1px' );      var borderWidth = g[ 0 ]; var setBorderWidth = g[ 1 ];
		var h = useState( '0px' );      var radius      = h[ 0 ]; var setRadius      = h[ 1 ];

		function commitWidth( value ) {
			var n = px( value );
			if ( ! n ) { n = MIN_BORDER_WIDTH; }
			setBorderWidth( Math.min( MAX_BORDER_WIDTH, Math.max( MIN_BORDER_WIDTH, n ) ) + 'px' );
		}

		function commitRadius( value ) {
			var n = Math.min( maxRadius, Math.max( 0, px( value ) ) );
			setRadius( n + 'px' );
		}

		// Same postMessage contract as the other two screens.
		useEffect( function () {
			var n = px( radius );
			onPreview( {
				border:      borderStyle,
				fill:        fill,
				label:       label,
				radius:      n + 'px',
				corners:     n === 0 ? 'sharp' : ( n >= maxRadius * 0.99 ? 'pill' : 'rounded' ),
				fillColor:   'filled' === fill ? bgColor : null,
				textColor:   textColor,
				borderColor: borderColor,
				borderWidth: borderWidth,
			} );
		}, [ label, fill, borderStyle, textColor, bgColor, borderColor, borderWidth, radius ] );

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
				el( 'div', { className: 'ufc-proposed__group' },
					el( ColorRow, { label: 'Text',       value: textColor, onChange: setTextColor } ),
					el( ColorRow, { label: 'Background', value: bgColor,   onChange: setBgColor } )
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
							prefix: el( SwatchPrefix, { value: borderColor, onChange: setBorderColor } ),
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

				// Shadow is in the design but has nothing to drive: there is no
				// field shadow token, and fields already use box-shadow for the
				// hover/focus ring. Rendered so the screen matches the design,
				// but it is inert until that is resolved.
				el( 'div', null,
					Label( 'Shadow' ),
					el( 'div', { className: 'ufc-proposed__group' },
						el( 'div', { className: 'ufc-proposed__row' },
							el( Button, {
								className: 'ufc-proposed__row-toggle',
								'aria-disabled': 'true',
								onClick: function () {},
							},
								el( ShadowIcon ),
								el( 'span', { className: 'ufc-proposed__row-label' }, 'Drop shadow' )
							)
						)
					)
				)
			)
		);
	}

	window.UFC = window.UFC || {};
	window.UFC.ProposedPanel = ProposedPanel;
} )( window.wp );
