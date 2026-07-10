/**
 * Shared "Form controls" field set.
 *
 * The four token controls — border style, corner radius, background/fill,
 * label position — rendered with the SAME @wordpress/components primitives
 * and reading/writing the SAME `settings.custom.ufFields` on the global
 * styles entity that the existing showcase settings panel
 * (includes/showcase-settings-panel.php) uses. Because both surfaces bind
 * to that one entity via `useEntityProp`, edits made here stay in sync with
 * the Appearance > Form controls page and the ?uf_showcase=1 page.
 *
 * This module is UI only — it never touches the DOM directly. The host tells
 * it how to preview by passing `onPreview(values)`, which fires on every
 * change (and once on mount). In the Site Editor the host relays those
 * values to the showcase preview iframe via postMessage.
 *
 * Exposed as `window.UFC.FormControlFields`.
 *
 * @package UnifiedFormControls
 */
( function ( wp ) {
	if ( ! wp || ! wp.element || ! wp.components || ! wp.data || ! wp.coreData ) {
		return;
	}

	var el            = wp.element.createElement;
	var useEffect     = wp.element.useEffect;
	var useSelect     = wp.data.useSelect;
	var useEntityProp = wp.coreData.useEntityProp;
	var C             = wp.components;

	var ToggleGroupControl       = C.ToggleGroupControl       || C.__experimentalToggleGroupControl;
	var ToggleGroupControlOption = C.ToggleGroupControlOption || C.__experimentalToggleGroupControlOption;
	var UnitControl              = C.UnitControl              || C.__experimentalUnitControl;
	var Button                   = C.Button;

	// Defaults mirror the showcase seed defaults (showcase-page.php /
	// showcase-settings-panel.php): outline / unfilled / 4px / inside.
	var DEFAULTS = { border: 'outline', fill: 'unfilled', radius: '4px', label: 'inside', fillColor: null };

	function cornersFromRadius( radius, halfH ) {
		var n = parseFloat( radius ) || 0;
		if ( n === 0 ) {
			return 'sharp';
		}
		if ( halfH && n >= halfH * 0.99 ) {
			return 'pill';
		}
		return 'rounded';
	}

	/**
	 * @param {Object}   props
	 * @param {Function} props.onPreview  Called with { border, fill, label, radius, fillColor, corners } on mount + every change.
	 * @param {boolean}  [props.showSave] Render the Update button (default true).
	 * @param {number}   [props.maxRadius] Max corner radius in px (half the field height). Default 27.
	 */
	function FormControlFields( props ) {
		var onPreview = props.onPreview || function () {};
		var showSave  = props.showSave !== false;
		var maxRadius = props.maxRadius || 27;

		// Same entity model as the showcase panel: read the current global
		// styles id, then bind to its `settings` via useEntityProp.
		var globalStylesId = useSelect( function ( select ) {
			var core = select( 'core' );
			return core && core.__experimentalGetCurrentGlobalStylesId
				? core.__experimentalGetCurrentGlobalStylesId()
				: null;
		}, [] );

		var entity      = useEntityProp( 'root', 'globalStyles', 'settings', globalStylesId || undefined );
		var settings    = entity[ 0 ] || {};
		var setSettings = entity[ 1 ];

		var uf        = ( settings.custom && settings.custom.ufFields ) || {};
		var border    = uf.border    || DEFAULTS.border;
		var fill      = uf.fill      || DEFAULTS.fill;
		var radius    = uf.radius    || DEFAULTS.radius;
		var label     = uf.label     || DEFAULTS.label;
		var fillColor = uf.fillColor || null;

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
			if ( ! setSettings ) {
				return;
			}
			var prevCustom = settings.custom || {};
			var prevFields = prevCustom.ufFields || {};
			setSettings( Object.assign( {}, settings, {
				custom: Object.assign( {}, prevCustom, {
					ufFields: Object.assign( {}, prevFields, next ),
				} ),
			} ) );
		}

		// Fire the preview callback on mount and whenever a value changes.
		useEffect( function () {
			onPreview( {
				border:    border,
				fill:      fill,
				label:     label,
				radius:    radius,
				fillColor: fillColor,
				corners:   cornersFromRadius( radius, maxRadius ),
			} );
		}, [ border, fill, label, radius, fillColor ] );

		// Direct entity save — no multi-entity "Review changes" dialog.
		function save() {
			if ( ! globalStylesId ) {
				return;
			}
			wp.data.dispatch( 'core' ).saveEditedEntityRecord( 'root', 'globalStyles', globalStylesId );
		}

		return el( 'div', { className: 'ufc-se-fields', style: { display: 'flex', flexDirection: 'column', gap: '16px' } },

			el( ToggleGroupControl, {
				label: 'Border style',
				value: border,
				isBlock: true,
				__nextHasNoMarginBottom: true,
				__next40pxDefaultSize: true,
				onChange: function ( v ) {
					// Border = None forces Fill = Filled (an unfilled
					// borderless field would be invisible) — same rule
					// the showcase panel enforces.
					if ( v === 'none' && fill !== 'filled' ) {
						patch( { border: v, fill: 'filled' } );
					} else {
						patch( { border: v } );
					}
				},
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
				max: Math.floor( maxRadius ),
				__nextHasNoMarginBottom: true,
				__next40pxDefaultSize: true,
				onChange: function ( v ) {
					patch( { radius: v || '0px' } );
				},
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
				},
			},
				el( ToggleGroupControlOption, { value: 'unfilled', label: 'Unfilled', disabled: border === 'none' } ),
				el( ToggleGroupControlOption, { value: 'filled',   label: 'Filled'   } )
			),

			el( ToggleGroupControl, {
				label: 'Label position',
				value: label,
				isBlock: true,
				__nextHasNoMarginBottom: true,
				__next40pxDefaultSize: true,
				onChange: function ( v ) {
					patch( { label: v } );
				},
			},
				el( ToggleGroupControlOption, { value: 'inside', label: 'Inside' } ),
				el( ToggleGroupControlOption, { value: 'above',  label: 'Above'  } )
			),

			showSave && el( Button, {
				variant: 'primary',
				__next40pxDefaultSize: true,
				style: { width: '100%', justifyContent: 'center' },
				onClick: save,
				disabled: ! isDirty || isSaving || ! globalStylesId,
				isBusy: isSaving,
			}, isSaving ? 'Saving…' : 'Update' )
		);
	}

	window.UFC = window.UFC || {};
	window.UFC.FormControlFields = FormControlFields;
} )( window.wp );
