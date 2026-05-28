/*
 * Admin > Settings > Form Controls — React mount.
 *
 * Same four controls as the showcase settings panel, wired through
 * `useEntityProp` against the global styles entity. The page is
 * standalone (no editor save bar around it), so we render our own
 * Save button that calls `saveEditedEntityRecord` on the entity to
 * PUT the new settings to the REST API.
 *
 * No build step — uses `wp.element.createElement` (aliased to `el`)
 * to match the conventions used by the showcase settings panel and
 * the standalone sidebar.
 */
( function ( wp ) {
	if ( ! wp || ! wp.element || ! wp.data || ! wp.coreData ) {
		return;
	}

	var el                       = wp.element.createElement;
	var Fragment                 = wp.element.Fragment;
	var useState                 = wp.element.useState;
	var useEffect                = wp.element.useEffect;
	var useSelect                = wp.data.useSelect;
	var useEntityProp            = wp.coreData.useEntityProp;
	var Button                   = wp.components.Button;
	var ToggleGroupControl       = wp.components.ToggleGroupControl       || wp.components.__experimentalToggleGroupControl;
	var ToggleGroupControlOption = wp.components.ToggleGroupControlOption || wp.components.__experimentalToggleGroupControlOption;
	var UnitControl              = wp.components.UnitControl              || wp.components.__experimentalUnitControl;
	var __ = wp.i18n.__;

	function cornersFromRadius( r ) {
		var n = parseFloat( r ) || 0;
		if ( n === 0 ) return 'sharp';
		if ( n >= 999 ) return 'pill';
		return 'rounded';
	}

	function App() {
		// Resolve the active global styles record so we can mutate
		// `settings.custom.ufFields` on it.
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

		var ufFields = ( settings.custom && settings.custom.ufFields ) || {};
		var border   = ufFields.border || 'outline';
		var fill     = ufFields.fill   || 'unfilled';
		var radius   = ufFields.radius || '4px';
		var label    = ufFields.label  || 'inside';

		// `isDirty` and `isSaving` come from the @wordpress/core-data store
		// so we can disable the Save button while there's nothing to save
		// and surface a busy state while the PUT is in flight.
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

		var statusState = useState( '' );
		var status      = statusState[ 0 ];
		var setStatus   = statusState[ 1 ];

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
			// Editing again clears any prior saved/error status.
			if ( status ) {
				setStatus( '' );
			}
		}

		/*
		 * Live preview wiring — same postMessage channel the standalone
		 * sidebar / Site Editor preview iframe use. The showcase template
		 * (loaded inside #ufc-admin-preview-iframe) has a
		 * <script id="uf-field-settings-listener"> that listens for
		 * `message` events with type `uf-field-settings` and applies them
		 * to the showcase's data-* attrs in real time.
		 *
		 * Two delivery paths so the iframe stays in sync at all times:
		 *   1. postMessage on every state change — instant update while the
		 *      user is editing here.
		 *   2. localStorage `uf_field_settings` — the iframe's listener
		 *      reads this on initial load (and after each iframe reload),
		 *      so the preview matches the staged state even before any
		 *      postMessage arrives (the iframe may still be loading when
		 *      the React component first renders).
		 */
		useEffect( function () {
			var data = {
				type:      'uf-field-settings',
				border:    border,
				fill:      fill,
				corners:   cornersFromRadius( radius ),
				label:     label,
				radius:    radius,
				fillColor: null,
			};
			try {
				localStorage.setItem( 'uf_field_settings', JSON.stringify( data ) );
			} catch ( _ ) {}
			var iframe = document.getElementById( 'ufc-admin-preview-iframe' );
			if ( iframe && iframe.contentWindow ) {
				iframe.contentWindow.postMessage( data, '*' );
			}
		}, [ border, fill, radius, label ] );

		function save() {
			if ( ! globalStylesId ) {
				return;
			}
			wp.data
				.dispatch( 'core' )
				.saveEditedEntityRecord( 'root', 'globalStyles', globalStylesId )
				.then( function () { setStatus( 'saved' ); } )
				.catch( function () { setStatus( 'error' ); } );
		}

		// --- Controls ------------------------------------------------------

		var controls = el(
			'div',
			{ style: { display: 'flex', flexDirection: 'column', gap: '16px', maxWidth: '640px', marginTop: '24px' } },

			el(
				ToggleGroupControl,
				{
					label: __( 'Border', 'unified-form-controls' ),
					value: border,
					isBlock: true,
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
					onChange: function ( v ) { patch( { border: String( v || '' ) } ); },
				},
				el( ToggleGroupControlOption, { value: 'outline',   label: __( 'Outline',   'unified-form-controls' ) } ),
				el( ToggleGroupControlOption, { value: 'underline', label: __( 'Underline', 'unified-form-controls' ) } ),
				el( ToggleGroupControlOption, { value: 'none',      label: __( 'None',      'unified-form-controls' ) } )
			),

			el( UnitControl, {
				label: __( 'Border Radius', 'unified-form-controls' ),
				value: radius,
				units: [ { value: 'px', label: 'px', default: 0 } ],
				min: 0,
				max: 100,
				__nextHasNoMarginBottom: true,
				__next40pxDefaultSize: true,
				onChange: function ( v ) { patch( { radius: v || '0px' } ); },
			} ),

			el(
				ToggleGroupControl,
				{
					label: __( 'Fill', 'unified-form-controls' ),
					value: fill,
					isBlock: true,
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
					onChange: function ( v ) { patch( { fill: String( v || '' ) } ); },
				},
				el( ToggleGroupControlOption, {
					value: 'unfilled',
					label: __( 'Unfilled', 'unified-form-controls' ),
					disabled: border === 'none',
				} ),
				el( ToggleGroupControlOption, { value: 'filled', label: __( 'Filled', 'unified-form-controls' ) } )
			),

			el(
				ToggleGroupControl,
				{
					label: __( 'Label', 'unified-form-controls' ),
					value: label,
					isBlock: true,
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
					onChange: function ( v ) { patch( { label: String( v || '' ) } ); },
				},
				el( ToggleGroupControlOption, { value: 'inside', label: __( 'Inside', 'unified-form-controls' ) } ),
				el( ToggleGroupControlOption, { value: 'above',  label: __( 'Above',  'unified-form-controls' ) } )
			)
		);

		// --- Save button + status ------------------------------------------

		var saveRow = el(
			'div',
			{ style: { marginTop: '24px', display: 'flex', alignItems: 'center', gap: '12px' } },
			el(
				Button,
				{
					variant: 'primary',
					__next40pxDefaultSize: true,
					onClick: save,
					disabled: ! isDirty || isSaving || ! globalStylesId,
					isBusy: isSaving,
				},
				isSaving
					? __( 'Saving…', 'unified-form-controls' )
					: __( 'Save changes', 'unified-form-controls' )
			),
			status === 'saved'  && el( 'span', { style: { color: '#1a8849' } }, __( 'Saved.',       'unified-form-controls' ) ),
			status === 'error'  && el( 'span', { style: { color: '#d63638' } }, __( 'Save failed.', 'unified-form-controls' ) )
		);

		if ( ! globalStylesId ) {
			return el(
				'p',
				{ style: { marginTop: '24px', color: '#666' } },
				__( 'Loading the global styles entity…', 'unified-form-controls' )
			);
		}

		return el( Fragment, null, controls, saveRow );
	}

	// --- Mount ------------------------------------------------------------

	function mount() {
		var root = document.getElementById( 'ufc-admin-settings-root' );
		if ( ! root ) return;

		// wp.components experimental APIs sometimes arrive a tick after the
		// initial script load; poll briefly until both stable + experimental
		// component aliases are in place, then render once.
		var attempts = 0;
		var poll = setInterval( function () {
			if ( ++attempts > 60 ) { clearInterval( poll ); return; }
			if (
				wp.components &&
				wp.coreData &&
				( wp.components.ToggleGroupControl || wp.components.__experimentalToggleGroupControl ) &&
				( wp.components.UnitControl        || wp.components.__experimentalUnitControl )
			) {
				clearInterval( poll );
				if ( wp.element.createRoot ) {
					wp.element.createRoot( root ).render( el( App ) );
				} else {
					wp.element.render( el( App ), root );
				}
			}
		}, 100 );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', mount );
	} else {
		mount();
	}
} )( window.wp );
