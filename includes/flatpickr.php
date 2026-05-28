<?php
/**
 * Flatpickr — themable date / time picker for the storefront.
 *
 * Extracted verbatim from twentytwentyfive's functions.php. Replaces the
 * browser's native date popup (whose appearance we can't customize) with
 * a vanilla-JS library whose markup we can style via our theme.json
 * tokens.
 *
 * Progressive enhancement: if the script fails to load, the native
 * `<input type="date">` fallback still works.
 *
 * Brand: the inline stylesheet below maps Flatpickr's class names to
 * `--wp--preset--color--*` / `--wp--preset--font-family--*` variables, so
 * the calendar follows the active style variation automatically.
 *
 * @package UnifiedFormControls
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ufc_enqueue_flatpickr() {
	// Showcase-only scope — frontend `/?uf_showcase=1` OR the Form Controls
	// admin page. The shared gate `ufc_showcase_panel_is_active()` covers
	// both. (Earlier versions bailed on `is_admin()` because the showcase
	// only lived on the frontend; now that the admin page renders the
	// showcase inline, the date / time inputs in the preview need
	// Flatpickr there too.)
	if ( ! function_exists( 'ufc_showcase_panel_is_active' ) || ! ufc_showcase_panel_is_active() ) {
		return;
	}
	wp_enqueue_style(
		'flatpickr',
		'https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css',
		array(),
		'4.6.13'
	);
	wp_enqueue_script(
		'flatpickr',
		'https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js',
		array(),
		'4.6.13',
		true
	);

	// Theme override — maps Flatpickr's own class names onto our theme.json
	// tokens so the calendar inherits the active style variation's fonts,
	// colors, and radii without hardcoded values.
	$css = <<<CSS
		.flatpickr-calendar {
			background: var(--wp--preset--color--base) !important;
			color: currentColor !important;
			font-family: inherit !important;
			/* Follow the showcase's corners variant (sharp / rounded / pill)
			   via `--uf-field-radius` — defined on `body[data-corners]`.
			   Cap at `--uf-field-height / 2` (same cap used on textareas
			   in uf-forms.css) so the pill variant doesn't turn the tall
			   popover into a stadium blob — curves match the text-input's
			   pill radius instead. */
			border-radius: min(var(--uf-field-radius, 4px), calc(var(--uf-field-height, 55px) / 2)) !important;
			box-shadow: 0 6px 24px rgba(0, 0, 0, 0.08) !important;
			border: 1px solid var(--uf-divider, rgba(0,0,0,0.12)) !important;
			/* Symmetric 12px padding on all four sides — same horizontal
			   spacing as before; top now matches bottom so the time-only
			   popover and the day grid don't sit flush against the top
			   border. The months header still has its own inner padding
			   which adds to this without making the date popover feel
			   bloated. Calendar min-width 334px = 307.875 day grid + 24px
			   horizontal padding + 2px borders. */
			box-sizing: border-box !important;
			min-width: 334px !important;
			padding: 12px !important;
		}
		/* Inline mode (showcase): the calendar sits in normal page flow,
		   not floating above content. Drop the popover-only treatments
		   (shadow, auto-centering) so the calendar's left edge aligns
		   with its section heading. */
		.flatpickr-calendar.inline {
			box-shadow: none !important;
			margin: 0 !important;
			display: block !important;
		}
		/* Hide Flatpickr's tip/caret pseudo-elements. By default Flatpickr
		   draws a small triangle on the top (or bottom) edge that points
		   back at the input — it requires TWO overlapping triangles
		   (outline + fill) and defaults to browser-native colors, which
		   produces visible artifacts when we swap the background to a
		   theme color. Calendar visually connects to the input via
		   proximity alone; the tip is cosmetic. */
		.flatpickr-calendar:before,
		.flatpickr-calendar:after {
			display: none !important;
		}
		.flatpickr-months,
		.flatpickr-month,
		.flatpickr-current-month,
		.flatpickr-monthDropdown-months,
		.flatpickr-weekdays,
		span.flatpickr-weekday {
			background: transparent !important;
			color: inherit !important;
			fill: currentColor !important;
			font-family: inherit !important;
		}
		/* Month + year use Medium — matches the form-field and time-input
		   font size, keeping typography consistent across all popovers
		   and the trigger controls. */
		.flatpickr-current-month,
		.flatpickr-current-month .flatpickr-monthDropdown-months,
		.flatpickr-current-month .cur-year,
		.flatpickr-current-month input.cur-year {
			font-size: var(--wp--preset--font-size--small, 14px) !important;
		}
		/* Space out the month dropdown caret from the year input. */
		.flatpickr-current-month .flatpickr-monthDropdown-months {
			margin-right: 10px !important;
		}
		.flatpickr-current-month .numInputWrapper span.arrowUp:after,
		.flatpickr-current-month .numInputWrapper span.arrowDown:after {
			border-top-color: currentColor !important;
			border-bottom-color: currentColor !important;
		}
		.flatpickr-prev-month,
		.flatpickr-next-month,
		.flatpickr-prev-month svg,
		.flatpickr-next-month svg {
			color: currentColor !important;
			fill: currentColor !important;
		}
		/* Vertically center the chevrons with the month/year text. By default
		   prev/next are absolute-positioned with a fixed 54px height,
		   taller than the current-month box (~41px), so flex-centering the
		   SVG inside 54px lands below the text's actual center. Stretch
		   the buttons to match the months container's height (via top:0 +
		   bottom:0) then flex-center — SVG ends up on the text center. */
		.flatpickr-months {
			display: flex !important;
			align-items: center !important;
			position: relative !important;
		}
		.flatpickr-prev-month,
		.flatpickr-next-month {
			height: auto !important;
			top: 0 !important;
			bottom: 0 !important;
			display: flex !important;
			align-items: center !important;
			justify-content: center !important;
		}
		.flatpickr-day {
			color: inherit !important;
			font-family: inherit !important;
			/* Day cell hover / selected shape follows the showcase's corners
			   variant — same `--uf-field-radius` token as form fields and
			   the popover outer. */
			border-radius: var(--uf-field-radius, 4px) !important;
			/* Expand each cell to fill its 1/7 slot so hover/selected
			   backgrounds run edge-to-edge (otherwise Flatpickr leaves
			   ~2.5px of whitespace each side, which reads as "extra left
			   padding" on the Sunday column when hovered). */
			width: 14.2857142857% !important;
			max-width: 14.2857142857% !important;
			flex-basis: 14.2857142857% !important;
			margin: 0 !important;
			box-sizing: border-box !important;
		}
		.flatpickr-day:not(.flatpickr-disabled):not(.prevMonthDay):not(.nextMonthDay):not(.selected):not(.startRange):not(.endRange):not(.inRange):hover {
			background: transparent !important;
			border-color: transparent !important;
			/* `outline` (not `border`) so rightmost-column hover can't be
			   clipped by the calendar's own right border. Outlines render
			   on top of the element without affecting its box. */
			outline: 1px solid var(--uf-site-text, currentColor) !important;
			outline-offset: -1px !important;
		}
		/* Disabled / prev-month / next-month days: no hover affordance —
		   they're not selectable. Cursor reflects it too. */
		.flatpickr-day.flatpickr-disabled,
		.flatpickr-day.prevMonthDay,
		.flatpickr-day.nextMonthDay {
			cursor: default !important;
		}
		.flatpickr-day.flatpickr-disabled:hover,
		.flatpickr-day.prevMonthDay:hover,
		.flatpickr-day.nextMonthDay:hover {
			background: transparent !important;
			outline: 0 !important;
			border-color: transparent !important;
		}
		/* Today: no border when resting — just a small centered dot under
		   the number. On hover, inherit the generic 1px hover border so
		   the day is still visibly interactive. Rendered via ::after so
		   the number's own vertical alignment is unaffected, and
		   `pointer-events: none` keeps the cell fully clickable. */
		.flatpickr-day.today:not(:hover) {
			border-color: transparent !important;
		}
		.flatpickr-day.today {
			position: relative !important;
		}
		.flatpickr-day.today::after {
			content: '';
			position: absolute;
			left: 50%;
			bottom: 6px;
			transform: translateX(-50%);
			width: 4px;
			height: 4px;
			border-radius: 50%;
			background: currentColor;
			pointer-events: none;
		}
		/* When today is selected, the number inverts to base color — the
		   dot should follow suit (otherwise it's invisible on the dark
		   selected background). */
		.flatpickr-day.today.selected::after {
			background: currentColor;
		}
		.flatpickr-day.selected,
		.flatpickr-day.startRange,
		.flatpickr-day.endRange,
		.flatpickr-day.selected:hover {
			background: var(--uf-site-text, var(--wp--preset--color--contrast)) !important;
			border-color: var(--uf-site-text, var(--wp--preset--color--contrast)) !important;
			color: var(--uf-site-bg, var(--wp--preset--color--base)) !important;
		}
		/* Range mode: paint a continuous "pill" across the picked range.
		   Middle days are square (no rounded corners) so neighboring fills
		   meet flush — no shingle / gap between cells. Start and end days
		   keep their round corners only on the outer side. Fill is derived
		   from `currentColor` (the active text color) so it tracks any
		   color scheme without needing a separate token. */
		.flatpickr-day.inRange {
			background: color-mix(in srgb, currentColor 12%, transparent) !important;
			border-color: transparent !important;
			border-radius: 0 !important;
			box-shadow: none !important;
		}
		.flatpickr-day.startRange:not(.endRange) {
			border-top-right-radius: 0 !important;
			border-bottom-right-radius: 0 !important;
		}
		.flatpickr-day.endRange:not(.startRange) {
			border-top-left-radius: 0 !important;
			border-bottom-left-radius: 0 !important;
		}
		.flatpickr-day.flatpickr-disabled,
		.flatpickr-day.prevMonthDay,
		.flatpickr-day.nextMonthDay {
			color: inherit !important;
			opacity: 0.35 !important;
		}
		.flatpickr-time input,
		.flatpickr-time .flatpickr-time-separator,
		.flatpickr-time .flatpickr-am-pm {
			color: inherit !important;
			background: transparent !important;
			font-family: inherit !important;
		}
		/* Flatpickr's default top border on `.flatpickr-time` is meant to
		   divide the time row from a calendar above it (datetime-local).
		   On a time-only popover it's an orphaned stroke at the top —
		   kill it. */
		.flatpickr-time {
			border-top: 0 !important;
		}
		/* ONE hover target per cell — no nested hover backgrounds.
		   - Hover bg uses the same `--uf-field-fill` token the form
		     fields use on hover (~7% text-color alpha — softer than
		     `--uf-divider`), so time-cell hover feels identical to
		     field hover in the rest of the storefront.
		   - Hour/minute cell: only the outer `.numInputWrapper` gets the
		     bg; input + stepper spans inside stay transparent so the cell
		     shows a single clean background.
		   - AM/PM cell: plain element, same single hover bg.
		   - Arrow hover is signaled via opacity only (no extra bg). */
		.flatpickr-time .numInputWrapper:hover,
		.flatpickr-time .flatpickr-am-pm:hover,
		.flatpickr-time .flatpickr-am-pm:focus {
			background: var(--uf-field-fill, color-mix(in oklab, currentColor 7%, transparent 93%)) !important;
		}
		.flatpickr-time input:hover,
		.flatpickr-time input:focus,
		.flatpickr-calendar .numInputWrapper span:hover {
			background: transparent !important;
		}

		/* Neutralize every font-weight Flatpickr hardcodes (bold month name,
		   bold selected day, bold year, bold AM/PM label, etc.) so all text
		   inside the date/time popovers inherits from the theme's body
		   weight — same rule we applied to the rest of the storefront. */
		.flatpickr-calendar,
		.flatpickr-calendar * {
			font-weight: inherit !important;
		}

		/* === BREATHY POPOVER LAYOUT ===
		   Match the generosity of the form controls (fields) by giving
		   every popover section visible breathing room: taller rows,
		   larger day cells, more vertical padding on header/weekdays/time. */
		/* Hide Flatpickr's native header — we render our own `.gb-cal-header`
		   below, with prev chevron + month select + year stepper + next
		   chevron, all using the same 40px-tall cell component as the
		   time picker. */
		.flatpickr-calendar.gb-has-custom-header .flatpickr-months {
			display: none !important;
		}

		/* === CUSTOM CALENDAR HEADER =============================
		   Replaces Flatpickr's default `.flatpickr-months` with a
		   purpose-built row that mirrors the time-popover's cell layout. */
		.gb-cal-header {
			display: flex !important;
			align-items: center !important;
			gap: 8px !important;
			/* Zero padding — the calendar outer's 12px padding handles
			   spacing on all four sides uniformly. */
			padding: 0 !important;
		}
		.gb-cal-header .gb-cal-nav {
			flex: 0 0 auto;
			width: 40px;
			height: 40px;
			display: flex;
			align-items: center;
			justify-content: center;
			background: transparent;
			border: 0;
			border-radius: 0;
			padding: 0;
			cursor: pointer;
			color: inherit;
		}
		.gb-cal-header .gb-cal-nav:hover { transform: scale(1.1); }
		.gb-cal-header .gb-cal-nav:focus { outline: none; box-shadow: none; }
		.gb-cal-header .gb-cal-nav:focus-visible {
			outline: 1.5px solid currentColor;
			outline-offset: 0;
		}
		.gb-cal-header .gb-cal-nav svg {
			width: 24px;
			height: 24px;
			fill: currentColor;
			display: block;
		}
		/* Month + year label — plain centred text between the nav arrows */
		.gb-cal-header .gb-cal-label {
			flex: 1 1 0;
			min-width: 0;
			text-align: center;
			font-size: var(--wp--preset--font-size--small, 14px);
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
		}
		/* Weekdays header — taller row with a light separator above the
		   day grid so the structure reads cleanly. */
		.flatpickr-weekdays {
			height: auto !important;
			padding: 8px 0 !important;
		}
		span.flatpickr-weekday {
			line-height: 28px !important;
			height: 28px !important;
			font-size: var(--wp--preset--font-size--small, 15px) !important;
		}
		/* Day cells — larger square hit target with consistent line-height. */
		.flatpickr-day {
			height: 44px !important;
			line-height: 44px !important;
			margin-top: 0 !important;
			margin-bottom: 0 !important;
			font-size: var(--wp--preset--font-size--small, 15px) !important;
		}
		/* Day container — add top breathing room between weekdays and grid. */
		.dayContainer {
			padding: 4px 0 0 !important;
		}
		/* Fluid day grid for the booking modal — fills available width */
		.gb-booking-calendar .flatpickr-days,
		.gb-booking-calendar .flatpickr-weekdaycontainer {
			width: 100% !important;
		}
		.gb-booking-calendar .dayContainer {
			min-width: 0 !important;
			max-width: 100% !important;
			width: 100% !important;
		}
		/* Time picker row — equal-width cells, centered, no arrows.
		   - Horizontal padding handled by the calendar's outer 12px (since
		     we symmetricized calendar to 12px all-around below). Row itself
		     has no extra horizontal padding, so the cells span edge-to-edge.
		   - Each cell (hour / minute / AM-PM) gets `flex: 1` so they share
		     width equally — visually a balanced 3-column row. */
		.flatpickr-time {
			height: auto !important;
			padding: 0 !important;
			gap: 8px !important;
			align-items: center !important;
		}
		.flatpickr-time .numInputWrapper,
		.flatpickr-time .flatpickr-am-pm {
			flex: 1 1 0 !important;
			min-width: 0 !important;
		}
		.flatpickr-time input,
		.flatpickr-time .flatpickr-am-pm {
			width: 100% !important;
			height: 40px !important;
			line-height: 40px !important;
			font-size: var(--wp--preset--font-size--medium, 18px) !important;
			padding: 0 8px !important;
			border-radius: min(var(--uf-field-radius, 4px), 20px) !important;
			text-align: center !important;
		}
		.flatpickr-time .flatpickr-time-separator {
			flex: 0 0 auto !important;
			line-height: 40px !important;
			height: 40px !important;
			padding: 0 2px !important;
		}
		/* Up / down steppers for ALL numeric inputs in the popover (time
		   hour/minute + calendar year). Positioned at the right edge,
		   stacked vertically, currentColor chevrons, subtle hover. */
		.flatpickr-calendar .numInputWrapper {
			position: relative !important;
		}
		.flatpickr-calendar .numInputWrapper input {
			padding-right: 26px !important;
		}
		.flatpickr-calendar .numInputWrapper span.arrowUp,
		.flatpickr-calendar .numInputWrapper span.arrowDown {
			display: flex !important;
			position: absolute !important;
			right: 6px !important;
			width: 16px !important;
			height: 16px !important;
			top: auto !important;
			bottom: auto !important;
			padding: 0 !important;
			cursor: pointer !important;
			opacity: 0.7 !important;
			border-left: 0 !important;
			border-color: transparent !important;
			align-items: center !important;
			justify-content: center !important;
		}
		.flatpickr-calendar .numInputWrapper span.arrowUp   { top: 4px !important; }
		.flatpickr-calendar .numInputWrapper span.arrowDown { bottom: 4px !important; }
		.flatpickr-calendar .numInputWrapper span.arrowUp:hover,
		.flatpickr-calendar .numInputWrapper span.arrowDown:hover {
			opacity: 1 !important;
		}
		.flatpickr-calendar .numInputWrapper span.arrowUp:after,
		.flatpickr-calendar .numInputWrapper span.arrowDown:after {
			border-top-color: currentColor !important;
			border-bottom-color: currentColor !important;
		}
		/* Year selector — same 40px-tall input cell component as hour /
		   minute in the time popover. The widened `.flatpickr-current-month`
		   above makes room for it alongside the month dropdown. Arrows
		   inherit the default 16×16 sizing from the `.flatpickr-calendar
		   .numInputWrapper` rule. */
		.flatpickr-current-month .numInputWrapper {
			min-width: 92px !important;
			height: 40px !important;
			flex: 0 0 auto !important;
		}
		.flatpickr-current-month .numInputWrapper input.cur-year {
			width: 100% !important;
			height: 40px !important;
			line-height: 40px !important;
			font-size: var(--wp--preset--font-size--medium, 18px) !important;
			padding: 0 26px 0 8px !important;
			border-radius: min(var(--uf-field-radius, 4px), 20px) !important;
			text-align: center !important;
		}

		/* === BOOKING DATEPICKER: CENTERED POPOVER + OVERLAY === */
		.gb-booking-header {
			display: flex;
			align-items: center;
			gap: 0.4em;
			margin-bottom: 0.8em;
			padding-top: 0.4em;
			padding-bottom: 0.4em;
			border-bottom: 1px solid var(--uf-divider, rgba(0,0,0,0.12));
		}
		.gb-booking-header-title {
			flex: 1 1 auto;
			/* Use the global "medium" preset so the title scales with the
			   theme's typography ramp instead of inheriting the header
			   container size (which can be ~26–32px). Fallback ~18px. */
			font-size: var(--wp--preset--font-size--medium, 1.125rem) !important;
			text-align: left !important;
			text-transform: none !important;
			letter-spacing: normal !important;
			margin: 0 !important;
			line-height: 1.2;
			opacity: 1 !important;
			/* Match the theme's heading weight (200, extra-light).
			   Needed because the booking modal sits inside a Flatpickr
			   container whose rule `.flatpickr-calendar * { font-weight:
			   inherit }` beats the global `h1..h6` weight on specificity
			   and would otherwise pull this heading down to the body
			   weight (300). */
			font-weight: 200 !important;
		}
		.gb-booking-close {
			flex: 0 0 auto;
			align-self: center;
			width: 40px;
			height: 40px;
			background: transparent;
			border: 0;
			border-radius: 0;
			color: inherit;
			opacity: 1;
			padding: 0;
			margin: 0;
			cursor: pointer;
			display: flex;
			align-items: center;
			justify-content: center;
		}
		.gb-booking-close:focus { outline: none; box-shadow: none; }
		.gb-booking-close:focus-visible {
			outline: 1.5px solid currentColor;
			outline-offset: 0;
		}
		.gb-booking-close svg { fill: currentColor; display: block; }
		.gb-booking-footer {
			display: none;
			align-items: stretch;
			gap: 8px;
			margin-top: 16px;
			padding-top: 16px;
			border-top: 1px solid var(--uf-divider, rgba(0,0,0,0.12));
		}
		.gb-booking-footer.is-visible {
			display: flex;
		}
		.gb-booking-footer.is-visible .gb-booking-action {
			flex: 1 1 0;
			min-width: 0;
			display: flex;
		}
		.gb-booking-footer.is-visible .gb-booking-action .wp-block-button__link {
			width: 100%;
			height: 100%;
			box-sizing: border-box;
			line-height: 1.3;
			outline: 0 !important;
		}
		/* Primary booking action (Pay now): use the primary box-shadow
		   ring + drop shadow set by site-editor-buttons.php on :root. */
		.gb-booking-footer.is-visible .gb-booking-action .wp-block-button__link:not(.is-style-outline) {
			box-shadow:
				0 0 0 var(--btn-primary-border-width, 0px)
				color-mix(in srgb, var(--btn-primary-border-color, currentColor) var(--btn-primary-border-opacity, 100%), transparent),
				var(--btn-primary-shadow, 0 0 0 0 transparent) !important;
		}
		/* Outline booking action (e.g. Add to cart in booking modal). */
		.gb-booking-footer.is-visible .gb-booking-action .wp-block-button__link.is-style-outline {
			box-shadow: var(--btn-outline-shadow, none) !important;
		}
		.gb-booking-footer.is-visible .gb-booking-action .wp-block-button__link:focus,
		.gb-booking-footer.is-visible .gb-booking-action .wp-block-button__link:focus-visible {
			outline: 0 !important;
			box-shadow: none !important;
		}
		.flatpickr-calendar.gb-booking-calendar {
			padding-top: 0 !important;
			position: fixed !important;
			top: 50% !important;
			left: 50% !important;
			transform: translate(-50%, -50%) !important;
			z-index: 1001 !important;
			border: none !important;
			min-width: 480px !important;
			max-height: min(575px, 85svh) !important;
			overflow-y: auto !important;
		}
		/* Mobile: bottom sheet */
		@media (max-width: 600px) {
			.flatpickr-calendar.gb-booking-calendar {
				top: auto !important;
				bottom: 0 !important;
				left: 0 !important;
				right: 0 !important;
				transform: none !important;
				width: 100% !important;
				min-width: 0 !important;
				max-width: 100% !important;
				max-height: 85svh !important;
				overflow-y: auto !important;
				border-radius: var(--uf-modal-radius-top, 0 0 0 0) !important;
				padding-bottom: max(12px, env(safe-area-inset-bottom)) !important;
				box-shadow: 0 -4px 24px rgba(0, 0, 0, 0.12) !important;
			}
			.flatpickr-calendar.gb-booking-calendar.open {
				animation: gbSlideUp 0.28s cubic-bezier(0.32, 0.72, 0, 1) !important;
			}
			.flatpickr-calendar.gb-booking-calendar.is-closing {
				animation: gbSlideDown 0.24s cubic-bezier(0.32, 0.72, 0, 1) forwards !important;
			}
			.gb-booking-handle { display: block; }
		}
		/* Keep the calendar in the DOM while the close animation plays */
		.flatpickr-calendar.gb-booking-calendar.is-closing {
			display: block !important;
		}
		@keyframes gbSlideUp {
			from { translate: 0 100%; }
			to   { translate: 0 0; }
		}
		@keyframes gbSlideDown {
			from { translate: 0 0; }
			to   { translate: 0 100%; }
		}
		.gb-booking-handle {
			display: none;
			width: 32px;
			height: 4px;
			background: currentColor;
			opacity: 0.2;
			border-radius: 2px;
			margin: 0 auto 14px;
		}
		.gb-booking-overlay {
			position: fixed;
			top: 0;
			left: 0;
			right: 0;
			bottom: 0;
			background: rgba(0, 0, 0, 0.5);
			z-index: 1000;
			opacity: 0;
			pointer-events: none;
			transition: opacity 0.2s ease-out;
		}
		.gb-booking-overlay.is-open {
			opacity: 1;
			pointer-events: auto;
		}

		/* === BOOKING DATEPICKER: RESOURCE SELECTOR === */
		.gb-booking-resource {
			margin-bottom: 16px;
		}
		/* Flatpickr sets text-align: center on .flatpickr-calendar for the day
		   grid — reset it for the resource field so the floating label and
		   select text align left like every other uf-field. */
		.gb-booking-resource label,
		.gb-booking-resource select {
			text-align: start;
		}
		/* Progressive disclosure — calendar hidden until a resource is chosen */
		.gb-booking-calendar.gb-step--resource .gb-cal-header,
		.gb-booking-calendar.gb-step--resource .flatpickr-innerContainer {
			display: none !important;
		}
		/* Smooth reveal when calendar becomes visible after resource selection */
		.gb-booking-calendar:not(.gb-step--resource) .gb-cal-header,
		.gb-booking-calendar:not(.gb-step--resource) .flatpickr-innerContainer {
			transition: opacity 0.3s ease-out, translate 0.3s ease-out;
		}
		@starting-style {
			.gb-booking-calendar:not(.gb-step--resource) .gb-cal-header,
			.gb-booking-calendar:not(.gb-step--resource) .flatpickr-innerContainer {
				opacity: 0;
				translate: 0 8px;
			}
		}

		/* === BOOKING DATEPICKER: TIME-SLOT GRID === */
		.gb-timeslots {
			display: none;
			margin-top: 16px;
		}
		.gb-timeslots.is-visible { display: block; }
		.gb-timeslots-inner {
			display: grid;
			grid-template-columns: repeat(4, 1fr);
			gap: 6px;
			padding: 2px 0;
		}
		.gb-timeslot {
			height: 36px;
			padding: 0 8px;
			background: transparent;
			border: 1px solid var(--uf-divider, rgba(0,0,0,0.12));
			border-radius: min(var(--uf-field-radius, 4px), 18px);
			font-family: inherit;
			font-size: var(--wp--preset--font-size--small, 14px);
			color: inherit;
			cursor: pointer;
			white-space: nowrap;
			text-align: center;
		}
		.gb-timeslot:hover {
			background: transparent;
			border-color: transparent;
			outline: 1px solid var(--uf-site-text, currentColor);
			outline-offset: -1px;
		}
		.gb-timeslot.is-selected {
			background: var(--uf-site-text, var(--wp--preset--color--contrast, #000));
			color: var(--uf-site-bg, var(--wp--preset--color--base, #fff));
			border-color: var(--uf-site-text, var(--wp--preset--color--contrast, #000));
		}
CSS;
	wp_add_inline_style( 'flatpickr', $css );

	// Init: auto-enhance every date-like input on the page.
	$js = <<<'JS'
		(function () {
			if (typeof flatpickr !== 'function') return;

			var MONTH_NAMES = ['January','February','March','April','May','June','July','August','September','October','November','December'];
			var ICON_PREV = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path d="M14.6 7l-1.2-1L8 12l5.4 6 1.2-1-4.6-5z" fill="currentColor"/></svg>';
			var ICON_NEXT = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path d="M10.6 6L9.4 7l4.6 5-4.6 5 1.2 1 5.4-6z" fill="currentColor"/></svg>';

			/**
			 * Build + wire our custom calendar header (prev / month select /
			 * year stepper / next). Flatpickr's native `.flatpickr-months` is
			 * hidden via `.gb-has-custom-header` class — our header replaces
			 * it visually, while delegating state changes to Flatpickr's
			 * public API (`changeMonth`, `changeYear`).
			 */
			function buildCalendarHeader(fp) {
				if (fp._gbCustomHeader) return;
				var cal = fp.calendarContainer;
				cal.classList.add('gb-has-custom-header');

				var header = document.createElement('div');
				header.className = 'gb-cal-header';
				header.innerHTML =
					'<button type="button" class="gb-cal-nav gb-cal-prev" aria-label="Previous month">' + ICON_PREV + '</button>' +
					'<span class="gb-cal-label"></span>' +
					'<button type="button" class="gb-cal-nav gb-cal-next" aria-label="Next month">' + ICON_NEXT + '</button>';

				header.querySelector('.gb-cal-prev').addEventListener('click', function () { fp.changeMonth(-1); });
				header.querySelector('.gb-cal-next').addEventListener('click', function () { fp.changeMonth(1); });

				cal.insertBefore(header, cal.firstChild);
				fp._gbCustomHeader = header;
			}

			function syncCalendarHeader(fp) {
				if (!fp._gbCustomHeader) return;
				fp._gbCustomHeader.querySelector('.gb-cal-label').textContent =
					MONTH_NAMES[fp.currentMonth] + ' ' + fp.currentYear;
			}

			function buildResourceSelector(fp, title) {
				if (fp._gbResource) return;

				var customData = fp.element && fp.element.getAttribute('data-booking-resources');
				if (!customData) return;
				var resources;
				try { resources = JSON.parse(customData); } catch(e) { return; }

				if (!resources || !resources.length) return;

				fp.calendarContainer.classList.add('gb-step--resource');
				fp.calendarContainer.classList.add('uf-showcase');
				title.textContent = 'Select a resource';

				var wrap = document.createElement('div');
				wrap.className = 'gb-booking-resource uf-field uf-field--float';

				var sel = document.createElement('select');
				sel.id = 'gb-resource-' + Date.now();

				var placeholder = document.createElement('option');
				placeholder.value = '';
				placeholder.disabled = true;
				placeholder.selected = true;
				placeholder.textContent = 'Select a resource';
				sel.appendChild(placeholder);

				resources.forEach(function (r) {
					var opt = document.createElement('option');
					opt.value = r.value;
					opt.textContent = r.label;
					sel.appendChild(opt);
				});

				var label = document.createElement('label');
				label.setAttribute('for', sel.id);
				label.textContent = 'With';

				sel.addEventListener('change', function () {
					fp.calendarContainer.classList.remove('gb-step--resource');
					title.textContent = 'Select a day and time';
					// Notify product-page shim so it can sync back to WC's select.
					if (fp.element && fp.element._flatpickrResourceCallback) {
						fp.element._flatpickrResourceCallback(this.value);
					}
				});

				wrap.appendChild(sel);
				wrap.appendChild(label);
				fp._gbResource = wrap;

				// Insert after header, before the calendar header
				var header = fp.calendarContainer.querySelector('.gb-booking-header');
				header.insertAdjacentElement('afterend', wrap);
			}

			var BOOKING_TIMES = ['9:00 AM','9:30 AM','10:00 AM','10:30 AM','11:00 AM','11:30 AM','12:00 PM','12:30 PM','1:00 PM','1:30 PM','2:00 PM','2:30 PM','3:00 PM','3:30 PM','4:00 PM','4:30 PM','5:00 PM','5:30 PM','6:00 PM','6:30 PM'];

			function buildBookingOverlay(fp) {
				if (fp._gbOverlay) return;
				var overlay = document.createElement('div');
				overlay.className = 'gb-booking-overlay';
				document.body.appendChild(overlay);
				overlay.addEventListener('click', function () { fp.close(); });
				fp._gbOverlay = overlay;
				fp.calendarContainer.classList.add('gb-booking-calendar');
				var handle = document.createElement('div');
				handle.className = 'gb-booking-handle';
				fp.calendarContainer.insertBefore(handle, fp.calendarContainer.firstChild);
				var header = document.createElement('div');
				header.className = 'gb-booking-header';
				var title = document.createElement('h2');
				title.className = 'gb-booking-header-title';
				title.textContent = 'Select a day and time';
				var closeBtn = document.createElement('button');
				closeBtn.type = 'button';
				closeBtn.className = 'gb-booking-close';
				closeBtn.setAttribute('aria-label', 'Close');
				closeBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path d="m13.06 12 6.47-6.47-1.06-1.06L12 10.94 5.53 4.47 4.47 5.53 10.94 12l-6.47 6.47 1.06 1.06L12 13.06l6.47 6.47 1.06-1.06L13.06 12Z"></path></svg>';
				closeBtn.addEventListener('click', function () { fp.close(); });
				header.appendChild(title);
				header.appendChild(closeBtn);
				fp.calendarContainer.insertBefore(header, fp.calendarContainer.firstChild);
				buildBookingFooter(fp);
				return title;
			}

			function buildBookingFooter(fp) {
				if (fp._gbFooter) return;
				function makeAction(label, isOutline, eventDetail) {
					var wrap = document.createElement('div');
					wrap.className = 'wp-block-button' + (isOutline ? ' is-style-outline' : '');
					var btn = document.createElement('a');
					btn.href = '#';
					btn.className = 'wp-block-button__link wp-element-button' + (isOutline ? ' is-style-outline' : '');
					btn.textContent = label;
					btn.addEventListener('click', function (e) {
						e.preventDefault();
						document.dispatchEvent(new CustomEvent('gb-booking:confirm', { detail: eventDetail }));
					});
					wrap.appendChild(btn);
					return wrap;
				}
				var footer = document.createElement('div');
				footer.className = 'gb-booking-footer wp-block-buttons';
				var pay = makeAction('Pay now',     false, { action: 'checkout', fp: fp });
				var add = makeAction('Add to cart', true,  { action: 'cart',     fp: fp });
				pay.classList.add('gb-booking-action', 'gb-booking-action--primary');
				add.classList.add('gb-booking-action', 'gb-booking-action--secondary');
				footer.appendChild(add);
				footer.appendChild(pay);
				fp.calendarContainer.appendChild(footer);
				fp._gbFooter = footer;
				fp._gbSetFooterEnabled = function (en) {
					footer.classList.toggle('is-visible', !!en);
					if (en && footer.parentNode) {
						footer.parentNode.appendChild(footer);
						// setTimeout(150) so WC's slot-click handlers and any
						// MutationObserver redraws settle before we scroll.
						setTimeout(function () {
							fp.calendarContainer.scrollTop = fp.calendarContainer.scrollHeight;
						}, 150);
					}
				};
			}

			function setBookingFooterEnabled(fp, enabled) {
				if (fp._gbSetFooterEnabled) fp._gbSetFooterEnabled(enabled);
			}

			function openBookingOverlay(fp) {
				if (fp._gbOverlay) fp._gbOverlay.classList.add('is-open');
			}

			function closeBookingOverlay(fp) {
				if (fp._gbOverlay) fp._gbOverlay.classList.remove('is-open');
				if (window.matchMedia('(max-width: 600px)').matches) {
					fp.calendarContainer.classList.add('is-closing');
					fp.calendarContainer.addEventListener('animationend', function () {
						fp.calendarContainer.classList.remove('is-closing');
					}, { once: true });
				}
			}

			function buildTimeslots(fp, input) {
				if (fp._gbTimeslots) return;
				var wrap = document.createElement('div');
				wrap.className = 'gb-timeslots';
				var inner = document.createElement('div');
				inner.className = 'gb-timeslots-inner';
				BOOKING_TIMES.forEach(function (t) {
					var btn = document.createElement('button');
					btn.type = 'button';
					btn.className = 'gb-timeslot';
					btn.textContent = t;
					btn.addEventListener('click', function (e) {
						e.stopPropagation();
						inner.querySelectorAll('.gb-timeslot').forEach(function (b) { b.classList.remove('is-selected'); });
						btn.classList.add('is-selected');
						var d = fp.selectedDates[0];
						if (d) {
							var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
							input.value = months[d.getMonth()] + ' ' + d.getDate() + ' · ' + t;
							input._gbSelectedTime = t;
						}
						setBookingFooterEnabled(fp, true);
					});
					inner.appendChild(btn);
				});
				wrap.appendChild(inner);
				fp.calendarContainer.appendChild(wrap);
				fp._gbTimeslots = wrap;
			}

			function showTimeslots(fp) {
				if (!fp._gbTimeslots) return;
				fp._gbTimeslots.classList.add('is-visible');
				fp._gbTimeslots.scrollLeft = 0;
				fp._gbTimeslots.querySelectorAll('.gb-timeslot').forEach(function (b) { b.classList.remove('is-selected'); });
				setTimeout(function () {
					fp._gbTimeslots.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
				}, 150);
			}

			function init() {
				document.querySelectorAll(
					'input[type="date"], input[type="time"], input[type="datetime-local"], input[type="month"]'
				).forEach(function (input) {
					if (input._flatpickr) return;
					var type = input.getAttribute('type');
					var hasCalendar = type === 'date' || type === 'datetime-local' || type === 'month';
					var isBooking = input.hasAttribute('data-booking-datepicker');
					var isRange   = input.hasAttribute('data-requires-date-range');
					var isInline  = input.hasAttribute('data-inline-calendar');
					var options = {
						allowInput: !isBooking,
						disableMobile: true,
						clickOpens: !isInline,
						closeOnSelect: !isBooking,
						// Inline calendars mount next to the input (in flow);
						// popover calendars mount to body so they can float
						// above any container's overflow / stacking context.
						appendTo: isInline ? input.parentElement : document.body,
						position: 'auto center',
						mode: isRange ? 'range' : 'single',
						inline: isInline,
					};
					if (hasCalendar) {
						options.onReady = function (dates, str, fp) {
							buildCalendarHeader(fp);
							syncCalendarHeader(fp);
							if (isBooking) {
								var bookingTitle = buildBookingOverlay(fp);
								buildTimeslots(fp, input);
								buildResourceSelector(fp, bookingTitle);
							}
						};
						options.onOpen = function (dates, str, fp) {
							syncCalendarHeader(fp);
							if (isBooking) openBookingOverlay(fp);
						};
						options.onClose = function (dates, str, fp) {
							if (isBooking) closeBookingOverlay(fp);
						};
						options.onMonthChange = function (dates, str, fp) { syncCalendarHeader(fp); };
						options.onYearChange  = function (dates, str, fp) { syncCalendarHeader(fp); };
					}
					if (isBooking) {
						options.onChange = function (dates, str, fp) {
							// In range mode, wait for BOTH start and end days
							// before revealing timeslots. In single mode, the
							// first (and only) pick is enough.
							var needed = isRange ? 2 : 1;
							if (dates.length >= needed) {
								showTimeslots(fp);
								setBookingFooterEnabled(fp, false);
							}
						};
					}
					if (type === 'time') {
						options.enableTime = true;
						options.noCalendar = true;
						options.dateFormat = 'H:i';
					} else if (type === 'datetime-local') {
						options.enableTime = true;
						options.dateFormat = 'Y-m-d H:i';
					}
					flatpickr(input, options);
				});
			}
			// Expose so dynamically-injected inputs (e.g. the WC Bookings
			// shim in this same file) can re-trigger the same init —
			// guarantees identical Flatpickr config + custom calendar
			// header, no popover divergence between contexts.
			window.gutenbergInitFlatpickr = init;
			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', init);
			} else {
				init();
			}
		})();
JS;
	wp_add_inline_script( 'flatpickr', $js );
}
add_action( 'wp_enqueue_scripts',    'ufc_enqueue_flatpickr' );
add_action( 'admin_enqueue_scripts', 'ufc_enqueue_flatpickr' );
