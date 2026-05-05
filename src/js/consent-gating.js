/**
 * Strong-block consent gating shim for GTM Kit.
 *
 * Loaded only when the script gating mode is `strong_block`. Listens for
 * the `gtmkit:consent:updated` window event and re-injects the masked
 * GTM container as an executable `text/javascript` script once the
 * required Consent Mode v2 categories transition to `granted`. Browsers
 * do not re-execute a script when its `type` attribute changes in place,
 * so the shim clones the masked element and appends the clone.
 *
 * Idempotency is guarded by three layers:
 *   1. A module-scope `unblocked` boolean.
 *   2. A scoped `window.google_tag_manager[<container id>]` check that
 *      verifies *our* container has actually booted, so unrelated tools
 *      (gtag.js for an Ads pixel, a CMP's debug shim, an Event Inspector)
 *      that touch the global do not block our unmask path.
 *   3. A `data-gtmkit-unblocked="1"` marker on the masked element so a
 *      second shim instance in the same document never re-injects.
 *
 * Configuration (`requiredCategories`, `containerId`) is read from
 * `window.gtmkitConsentGating`, set by `wp_localize_script`. Falls back
 * to safe defaults so the shim still works if the localize step is
 * missing.
 *
 * Bundle budget: under 1 KB minified (enforced by
 * `bin/check-consent-gating-size.js`). Keep additions tight.
 */
( function ( window, document ) {
	var config = window.gtmkitConsentGating || {};
	var required =
		Array.isArray( config.requiredCategories ) && config.requiredCategories.length
			? config.requiredCategories
			: [ 'analytics_storage', 'ad_storage' ];
	var containerId =
		typeof config.containerId === 'string' && config.containerId
			? config.containerId
			: '';
	var unblocked = false;

	function shouldUnblock( state ) {
		if ( ! state ) {
			return false;
		}
		for ( var i = 0; i < required.length; i++ ) {
			if ( state[ required[ i ] ] !== 'granted' ) {
				return false;
			}
		}
		return true;
	}

	function ourContainerLoaded() {
		// Only treat google_tag_manager as a "GTM is loaded" signal when
		// our specific container id is present on it. Other tools (gtag.js
		// pixels, debug inspectors) populate google_tag_manager with just
		// a debugGroupId; without this scoping the shim would falsely
		// short-circuit and never unmask our masked container.
		var gtm = window.google_tag_manager;
		if ( ! gtm ) {
			return false;
		}
		return containerId ? !! gtm[ containerId ] : false;
	}

	function unblockGtm() {
		if ( unblocked || ourContainerLoaded() ) {
			return;
		}
		var masked = document.querySelector( 'script[data-gtmkit-gated="1"]' );
		if ( ! masked || masked.getAttribute( 'data-gtmkit-unblocked' ) === '1' ) {
			return;
		}
		var clone = document.createElement( 'script' );
		for ( var i = 0; i < masked.attributes.length; i++ ) {
			var attr = masked.attributes[ i ];
			if ( attr.name !== 'type' && attr.name !== 'data-gtmkit-gated' ) {
				clone.setAttribute( attr.name, attr.value );
			}
		}
		clone.type = 'text/javascript';
		clone.text = masked.text;
		masked.setAttribute( 'data-gtmkit-unblocked', '1' );
		if ( masked.parentNode ) {
			masked.parentNode.insertBefore( clone, masked.nextSibling );
		}
		unblocked = true;
	}

	function check( eventDetail ) {
		// Prefer the merged window.gtmkit.consent.state surface (set by the
		// in-plugin update() helper) so partial-update events still trigger
		// unblock when the cumulative state crosses the threshold. Falls
		// back to the event detail when the consent surface is absent
		// (master toggle off + an external CMP firing the event directly).
		var consent = window.gtmkit && window.gtmkit.consent;
		var state = consent && consent.state ? consent.state : eventDetail;
		if ( shouldUnblock( state ) ) {
			unblockGtm();
		}
	}

	window.addEventListener( 'gtmkit:consent:updated', function ( e ) {
		check( e && e.detail );
	} );

	// On load: if consent state already meets the threshold, unblock now.
	// Covers the race where consent was granted before this shim attached.
	check( null );
} )( window, document );
