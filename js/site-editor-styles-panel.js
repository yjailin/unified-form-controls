/**
 * Site Editor integration for the Form controls settings.
 *
 * Additive: adds a "Form controls" item to the Styles panel list (below
 * Layout) and, when selected, shows the four shared token controls
 * (window.UFC.FormControlFields) in a sub-panel over the Styles column
 * while rendering the existing ?uf_showcase=1 page as a live preview over
 * the editor canvas.
 *
 * Nothing about the existing showcase, the Appearance > Form controls page,
 * or core Site Editor UI is modified — the list item and preview are
 * injected as our own nodes outside the editor's React tree, and the live
 * preview rides the postMessage listener the showcase already ships
 * (includes/site-editor-form-fields.php).
 *
 * @package UnifiedFormControls
 */
( function () {
	var CFG         = window.__UFC_SE || {};
	var PREVIEW_URL = CFG.previewUrl || '/?uf_showcase=1&uf_embed=1';
	var MAX_RADIUS  = CFG.maxRadius || 27;

	var state = { open: false, root: null, overlay: null, preview: null, iframe: null, lastPreview: null };

	// The preview loads the full ?uf_showcase=1&uf_embed=1 page, which renders
	// its own settings panel pinned to the LEFT at 300px (with body
	// padding-left:300px) and reserves 32px at the top for the (suppressed)
	// admin bar. The showcase document is served as an opaque origin, so its
	// DOM can't be reached from here to hide that panel. Instead we clip it
	// off with pure geometry: the iframe sits inside an overflow-hidden
	// wrapper, shifted left by the panel width and up by the admin-bar
	// reservation, so only the showcase content shows and it aligns flush to
	// the canvas. Live updates still flow through postMessage, which works
	// across the opaque-origin boundary.
	var PANEL_W  = 300;
	var TOPBAR_H = 32;

	// ---- boot ---------------------------------------------------------------

	function ready( cb ) {
		var tries = 0;
		var iv = setInterval( function () {
			if ( ++tries > 150 ) { clearInterval( iv ); return; }
			if (
				window.wp && wp.element && wp.components && wp.data && wp.coreData &&
				window.UFC && window.UFC.FormControlFields
			) {
				clearInterval( iv );
				cb();
			}
		}, 100 );
	}

	// ---- Styles list item injection ----------------------------------------

	function getItemGroup() {
		var layout = document.getElementById( '/layout' );
		return layout ? layout.closest( '.components-item-group' ) : null;
	}

	function injectItem() {
		if ( document.getElementById( 'ufc-se-item' ) ) { return; }
		var group  = getItemGroup();
		var layout = document.getElementById( '/layout' );
		if ( ! group || ! layout ) { return; }

		// Clone the native "Layout" button so the item matches the design
		// system exactly (icon slot, label, trailing chevron), then relabel
		// it and swap the leading icon glyph.
		var btn = document.createElement( 'button' );
		btn.type      = 'button';
		btn.id        = 'ufc-se-item';
		btn.className = layout.className;
		btn.innerHTML = layout.innerHTML;

		Array.prototype.forEach.call( btn.querySelectorAll( '*' ), function ( n ) {
			if ( n.children.length === 0 && n.textContent.trim() === 'Layout' ) {
				n.textContent = 'Form controls';
			}
		} );
		var icon = btn.querySelector( 'svg path' );
		if ( icon ) {
			// Three stacked field rows.
			icon.setAttribute( 'd', 'M4 5h16v3H4V5zm0 5h16v3H4v-3zm0 5h10v3H4v-3z' );
		}

		btn.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			openPanel();
		} );

		group.appendChild( btn );
	}

	// The root Styles screen remounts when the user navigates in/out of a
	// sub-screen, which drops our injected item. A MutationObserver plus a
	// low-frequency safety interval keep it present.
	function keepItem() {
		injectItem();
		var target = document.querySelector( '.edit-site-styles' ) || document.body;
		var obs = new MutationObserver( function () {
			if ( ! document.getElementById( 'ufc-se-item' ) ) { injectItem(); }
		} );
		obs.observe( target, { childList: true, subtree: true } );
		setInterval( injectItem, 1500 );
	}

	// ---- geometry -----------------------------------------------------------

	function stylesColumn()    { return document.querySelector( '.edit-site-styles' ); }
	function canvasContainer() { return document.querySelector( '.edit-site-layout__canvas-container' ); }

	function reposition() {
		if ( ! state.open ) { return; }
		var col = stylesColumn();
		if ( col && state.overlay ) {
			var r = col.getBoundingClientRect();
			state.overlay.style.left   = r.left + 'px';
			state.overlay.style.top    = r.top + 'px';
			state.overlay.style.width  = r.width + 'px';
			state.overlay.style.height = r.height + 'px';
		}
		var cc = canvasContainer();
		if ( cc && state.preview ) {
			var r2 = cc.getBoundingClientRect();
			state.preview.style.left   = r2.left + 'px';
			state.preview.style.top    = r2.top + 'px';
			state.preview.style.width  = r2.width + 'px';
			state.preview.style.height = r2.height + 'px';
		}
	}

	// ---- preview bridge -----------------------------------------------------

	function sendPreview( v ) {
		state.lastPreview = v;
		if ( state.iframe && state.iframe.contentWindow ) {
			state.iframe.contentWindow.postMessage(
				Object.assign( { type: 'uf-field-settings' }, v ),
				'*'
			);
		}
	}

	// ---- open / close -------------------------------------------------------

	function openPanel() {
		if ( state.open ) { return; }
		state.open = true;

		// Sub-panel over the Styles column.
		var overlay = document.createElement( 'div' );
		overlay.className = 'ufc-se-overlay';

		var header = document.createElement( 'div' );
		header.className = 'ufc-se-overlay__header';

		var back = document.createElement( 'button' );
		back.type = 'button';
		back.className = 'ufc-se-back';
		back.setAttribute( 'aria-label', 'Back to Styles' );
		back.innerHTML = '<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor" aria-hidden="true"><path d="M14.6 7.4L13.2 6l-6 6 6 6 1.4-1.4-4.6-4.6z"></path></svg>';
		back.addEventListener( 'click', closePanel );

		var title = document.createElement( 'span' );
		title.className = 'ufc-se-title';
		title.textContent = 'Form controls';

		header.appendChild( back );
		header.appendChild( title );

		var body = document.createElement( 'div' );
		body.className = 'ufc-se-overlay__body';

		overlay.appendChild( header );
		overlay.appendChild( body );
		document.body.appendChild( overlay );
		state.overlay = overlay;

		// Live preview over the canvas: an overflow-hidden wrapper that clips
		// the showcase's own left panel + top admin-bar reservation off the
		// iframe (see PANEL_W / TOPBAR_H note above).
		var preview = document.createElement( 'div' );
		preview.className = 'ufc-se-preview';

		var iframe = document.createElement( 'iframe' );
		iframe.className = 'ufc-se-iframe';
		iframe.src = PREVIEW_URL;
		iframe.style.left   = ( -PANEL_W ) + 'px';
		iframe.style.top    = ( -TOPBAR_H ) + 'px';
		iframe.style.width  = 'calc(100% + ' + PANEL_W + 'px)';
		iframe.style.height = 'calc(100% + ' + TOPBAR_H + 'px)';
		iframe.addEventListener( 'load', function () {
			// Push the current (possibly unsaved) values once the preview
			// document is ready — the mount-time onPreview may have fired
			// before this iframe existed.
			if ( state.lastPreview ) { sendPreview( state.lastPreview ); }
		} );

		preview.appendChild( iframe );
		document.body.appendChild( preview );
		state.preview = preview;
		state.iframe  = iframe;

		reposition();
		window.addEventListener( 'resize', reposition );

		// Render the shared controls into the sub-panel.
		var comp = wp.element.createElement( window.UFC.FormControlFields, {
			onPreview: sendPreview,
			showSave: true,
			maxRadius: MAX_RADIUS,
		} );
		if ( wp.element.createRoot ) {
			state.root = wp.element.createRoot( body );
			state.root.render( comp );
		} else {
			wp.element.render( comp, body );
			state.root = { unmount: function () { wp.element.unmountComponentAtNode( body ); } };
		}
	}

	function closePanel() {
		if ( ! state.open ) { return; }
		state.open = false;
		window.removeEventListener( 'resize', reposition );
		if ( state.root && state.root.unmount ) { state.root.unmount(); }
		if ( state.overlay && state.overlay.parentNode ) { state.overlay.parentNode.removeChild( state.overlay ); }
		if ( state.preview && state.preview.parentNode ) { state.preview.parentNode.removeChild( state.preview ); }
		state.root = state.overlay = state.preview = state.iframe = null;
	}

	ready( keepItem );
} )();
