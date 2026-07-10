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

	var state = { open: false, root: null, overlay: null, panel: null, preview: null, iframe: null, animatedRoot: null, lastPreview: null, variationSlug: '', unsub: null };

	// ---- style-variation following -----------------------------------------

	// Detect the style variation currently selected in Browse styles. When a
	// variation is applied, the edited global styles `styles` object equals
	// that variation's `styles` (verified), so we match the edited styles to
	// the theme variation list, then map its title -> slug via the localized
	// list (CFG.variations). Returns '' when no known variation is active
	// (i.e. the theme default), which renders the default preview.
	function detectVariationSlug() {
		try {
			var sel = wp.data.select( 'core' );
			var id  = sel.__experimentalGetCurrentGlobalStylesId && sel.__experimentalGetCurrentGlobalStylesId();
			if ( ! id ) { return ''; }
			var edited = sel.getEditedEntityRecord( 'root', 'globalStyles', id );
			var editedStyles = JSON.stringify( ( edited && edited.styles ) || {} );

			var titleToSlug = {};
			( CFG.variations || [] ).forEach( function ( v ) { titleToSlug[ v.title ] = v.slug; } );

			var vars = ( sel.__experimentalGetCurrentThemeGlobalStylesVariations
				&& sel.__experimentalGetCurrentThemeGlobalStylesVariations() ) || [];
			for ( var i = 0; i < vars.length; i++ ) {
				if ( titleToSlug[ vars[ i ].title ]
					&& JSON.stringify( vars[ i ].styles || {} ) === editedStyles ) {
					return titleToSlug[ vars[ i ].title ];
				}
			}
		} catch ( e ) {}
		return '';
	}

	function previewUrlFor( slug ) {
		return slug ? ( PREVIEW_URL + '&uf_variation=' + encodeURIComponent( slug ) ) : PREVIEW_URL;
	}

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

		// Give the item the @wordpress/icons `settings` icon. That package
		// isn't exposed as a runtime global (`wp.icons`) in this build, so we
		// embed the icon's exact source markup (packages/icons/src/library/
		// settings.tsx, viewBox 0 0 24 24) into the cloned native <svg> — we
		// keep that svg's class + 24px sizing + fill:currentColor from the
		// Layout item, so placement, size, and color match the other Styles
		// items exactly; only the glyph paths change.
		var svg = btn.querySelector( 'svg' );
		if ( svg ) {
			svg.setAttribute( 'viewBox', '0 0 24 24' );
			svg.innerHTML =
				'<path d="m19 7.5h-7.628c-.3089-.87389-1.1423-1.5-2.122-1.5-.97966 0-1.81309.62611-2.12197 1.5h-2.12803v1.5h2.12803c.30888.87389 1.14231 1.5 2.12197 1.5.9797 0 1.8131-.62611 2.122-1.5h7.628z"></path>' +
				'<path d="m19 15h-2.128c-.3089-.8739-1.1423-1.5-2.122-1.5s-1.8131.6261-2.122 1.5h-7.628v1.5h7.628c.3089.8739 1.1423 1.5 2.122 1.5s1.8131-.6261 2.122-1.5h2.128z"></path>';
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

	// Anchor to the Navigator area (the region the native screens occupy),
	// NOT the whole Styles column — so the persistent "Styles" toolbar
	// (Style Book eye + options kebab) stays visible above our panel, exactly
	// like it does above the native Colors/Typography/Layout screens.
	function navigatorArea() {
		return document.querySelector( '.global-styles-ui-sidebar__navigator-provider' )
			|| document.querySelector( '.edit-site-styles' );
	}
	// Anchor to the editor's OWN resizable canvas frame element. Its position
	// (and the even/centered margin around it) is computed dynamically by the
	// editor from window width + max-width, so by matching its exact rect our
	// preview inherits the identical frame margin the native Styles views have
	// — instead of us hand-setting padding that drifts at different widths.
	function previewAnchor() {
		return document.querySelector( '.edit-site-resizable-frame__inner-content' )
			|| document.querySelector( '.edit-site-layout__canvas-container' );
	}

	function reposition() {
		if ( ! state.open ) { return; }
		var col = navigatorArea();
		if ( col && state.overlay ) {
			var r = col.getBoundingClientRect();
			state.overlay.style.left   = r.left + 'px';
			state.overlay.style.top    = r.top + 'px';
			state.overlay.style.width  = r.width + 'px';
			state.overlay.style.height = r.height + 'px';
		}
		var cc = previewAnchor();
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

	// ---- native-mirroring panel contents -----------------------------------

	// Header + body built from the SAME @wordpress/components primitives and
	// props the native ScreenHeader / ScreenBody use, with the same native
	// CSS class names — so spacing, header style, and back link are inherited
	// from the shared components rather than reproduced by hand:
	//   ScreenHeader: Spacer paddingX={4} paddingY={3} > VStack spacing={2}
	//                 > HStack spacing={2} > BackButton(size=small) + Heading(size=13)
	//   ScreenBody:   Spacer padding={4}  (class global-styles-ui-screen-body)
	function PanelContents() {
		var el      = wp.element.createElement;
		var C       = wp.components;
		var HStack  = C.__experimentalHStack;
		var VStack  = C.__experimentalVStack;
		var Spacer  = C.__experimentalSpacer;
		var Heading = C.__experimentalHeading;
		var Text    = C.__experimentalText;
		var Button  = C.Button;
		// The @wordpress/icons `chevronLeft` glyph, inlined so we don't add a
		// wp-icons script dependency. Button renders an element icon as-is.
		var chevron = el( 'svg', { width: 24, height: 24, viewBox: '0 0 24 24', xmlns: 'http://www.w3.org/2000/svg', 'aria-hidden': 'true', focusable: 'false' },
			el( 'path', { d: 'M14.6 7l-1.2-1L8 12l5.4 6 1.2-1-4.6-5z' } )
		);

		var header = el( Spacer, { paddingX: 4, paddingY: 3, marginBottom: 0 },
			el( VStack, { spacing: 2 },
				el( HStack, { spacing: 2, alignment: 'center', justify: 'flex-start' },
					el( Button, { icon: chevron, size: 'small', label: 'Back', onClick: closePanel } ),
					el( Heading, { level: 2, size: 13, className: 'global-styles-ui-header' }, 'Form controls' )
				),
				// One-line intro, like the native screens (same component +
				// class as ScreenHeader's description).
				el( Text, { className: 'global-styles-ui-header__description' },
					'Consistent border, fill, and label styles for every form control on your site.'
				)
			)
		);

		var body = el( Spacer, { className: 'global-styles-ui-screen-body', padding: 4 },
			el( window.UFC.FormControlFields, { onPreview: sendPreview, maxRadius: MAX_RADIUS } )
		);

		return el( wp.element.Fragment, null, header, body );
	}

	// ---- native Navigator transition ---------------------------------------

	function prefersReducedMotion() {
		try { return window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches; }
		catch ( e ) { return false; }
	}

	// The currently-mounted native root Styles screen — the element the real
	// Navigator would slide out/in during a forward/back navigation. We apply
	// the matching native animation to it so the outgoing/incoming parallax
	// looks identical (the clip container is transparent, so it shows through
	// under our sliding panel).
	function activeRootScreen() {
		var root = document.querySelector( '.global-styles-ui-screen-root' );
		return root ? ( root.closest( '.global-styles-ui-sidebar__navigator-screen' ) || root ) : null;
	}

	// ---- open / close -------------------------------------------------------

	function openPanel() {
		if ( state.open ) { return; }
		state.open = true;

		// Clip container over the Styles column (transparent — lets the
		// outgoing native root screen show through during the slide).
		var overlay = document.createElement( 'div' );
		overlay.className = 'ufc-se-overlay';

		// The animated screen itself (opaque, scrolls as one screen).
		var panel = document.createElement( 'div' );
		panel.className = 'ufc-se-panel';
		overlay.appendChild( panel );
		document.body.appendChild( overlay );
		state.overlay = overlay;
		state.panel   = panel;

		// Live preview over the canvas, wrapped in a native-style frame: a
		// dark padded surround with a rounded, clipped inner frame (matches
		// the site-editor canvas). Inside, the iframe is shifted left/up to
		// clip the showcase's own settings panel + admin-bar reservation
		// (see PANEL_W / TOPBAR_H note above).
		var preview = document.createElement( 'div' );
		preview.className = 'ufc-se-preview';

		var frame = document.createElement( 'div' );
		frame.className = 'ufc-se-preview__frame';

		// Follow the variation selected in Browse styles.
		state.variationSlug = detectVariationSlug();

		var iframe = document.createElement( 'iframe' );
		iframe.className = 'ufc-se-iframe';
		iframe.src = previewUrlFor( state.variationSlug );
		iframe.style.left   = ( -PANEL_W ) + 'px';
		iframe.style.top    = ( -TOPBAR_H ) + 'px';
		iframe.style.width  = 'calc(100% + ' + PANEL_W + 'px)';
		iframe.style.height = 'calc(100% + ' + TOPBAR_H + 'px)';
		iframe.addEventListener( 'load', function () {
			// Push the current (possibly unsaved) values once the preview
			// document is ready — the mount-time onPreview may have fired
			// before this iframe existed.
			if ( state.lastPreview ) { sendPreview( state.lastPreview ); }
			// Fade in only now that the document has painted — no white flash.
			iframe.classList.add( 'is-loaded' );
		} );

		frame.appendChild( iframe );
		preview.appendChild( frame );
		document.body.appendChild( preview );
		state.preview = preview;
		state.iframe  = iframe;

		// Re-apply when the selected variation changes while the panel is open.
		state.unsub = wp.data.subscribe( function () {
			if ( ! state.open || ! state.iframe ) { return; }
			var slug = detectVariationSlug();
			if ( slug !== state.variationSlug ) {
				state.variationSlug = slug;
				// Reset the fade so the new variation also fades in on load
				// (dark frame shows in between — no white flash).
				state.iframe.classList.remove( 'is-loaded' );
				state.iframe.src = previewUrlFor( slug );
			}
		} );

		reposition();
		window.addEventListener( 'resize', reposition );

		// Render the native-style header + body + controls into the panel.
		var comp = wp.element.createElement( PanelContents );
		if ( wp.element.createRoot ) {
			state.root = wp.element.createRoot( panel );
			state.root.render( comp );
		} else {
			wp.element.render( comp, panel );
			state.root = { unmount: function () { wp.element.unmountComponentAtNode( panel ); } };
		}

		// Forward-navigation transition (matches clicking Colors from root):
		// our panel slides in from the right + fades in; the native root
		// screen slides out to the left + fades out.
		if ( ! prefersReducedMotion() ) {
			panel.classList.add( 'ufc-anim-panel-in' );
			var rootScreen = activeRootScreen();
			if ( rootScreen ) {
				rootScreen.classList.add( 'ufc-anim-root-out' );
				state.animatedRoot = rootScreen;
			}
			setTimeout( function () {
				panel.classList.remove( 'ufc-anim-panel-in' );
				if ( state.animatedRoot ) { state.animatedRoot.classList.remove( 'ufc-anim-root-out' ); }
			}, 340 );
		}
	}

	function closePanel() {
		if ( ! state.open ) { return; }
		state.open = false;
		window.removeEventListener( 'resize', reposition );
		if ( state.unsub ) { state.unsub(); state.unsub = null; }

		var overlay = state.overlay;
		var panel   = state.panel;
		var preview = state.preview;
		var rootObj = state.root;
		var rootScreen = activeRootScreen();

		function finalize() {
			if ( rootObj && rootObj.unmount ) { rootObj.unmount(); }
			if ( overlay && overlay.parentNode ) { overlay.parentNode.removeChild( overlay ); }
			if ( preview && preview.parentNode ) { preview.parentNode.removeChild( preview ); }
			if ( rootScreen ) { rootScreen.classList.remove( 'ufc-anim-root-in' ); }
		}

		// Back-navigation transition (matches clicking Back): our panel slides
		// out to the right + fades out; the native root screen slides in from
		// the left + fades in.
		if ( prefersReducedMotion() || ! panel ) {
			finalize();
		} else {
			panel.classList.remove( 'ufc-anim-panel-in' );
			panel.classList.add( 'ufc-anim-panel-out' );
			if ( rootScreen ) { rootScreen.classList.add( 'ufc-anim-root-in' ); }
			setTimeout( finalize, 340 );
		}

		state.root = state.overlay = state.panel = state.preview = state.iframe = state.animatedRoot = null;
	}

	ready( keepItem );
} )();
