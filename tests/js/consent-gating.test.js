// @vitest-environment jsdom
/**
 * Vitest coverage for the strong-block consent gating shim.
 *
 * Loads the actual production source file (`src/js/consent-gating.js`)
 * inside a JSDOM context so the IIFE runs against the real DOM the
 * shim sees in the browser. Each test resets `window` and `document` to
 * a known shape, then asserts on observable side effects: the cloned
 * script element, the data-gtmkit-unblocked marker, and the absence of
 * duplicate injections under the three idempotency layers.
 *
 * @module tests/js/consent-gating.test.js
 */

import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';

const SHIM_SOURCE = fs.readFileSync(
	path.resolve( __dirname, '../../src/js/consent-gating.js' ),
	'utf-8'
);

/**
 * Run the shim in the current JSDOM window. The shim is an IIFE so a
 * fresh `eval` re-installs the listeners and resets the module-scope
 * `unblocked` flag, giving each test a clean shim instance.
 */
function loadShim() {
	const fn = new vm.Script( SHIM_SOURCE );
	fn.runInThisContext();
}

/**
 * Build a masked script element matching the strong-block emission
 * (`type="text/plain"` + `data-gtmkit-gated="1"`) and append it to the
 * document head, mirroring how WordPress would render it.
 *
 * @param {string} content GTM container snippet text.
 * @returns {HTMLScriptElement}
 */
function appendMaskedScript( content = "/* gtm */ console.log('gtm');" ) {
	const masked = document.createElement( 'script' );
	masked.type = 'text/plain';
	masked.setAttribute( 'data-gtmkit-gated', '1' );
	masked.id = 'gtmkit-container-js-before';
	masked.text = content;
	document.head.appendChild( masked );
	return masked;
}

/**
 * Remove every child element from a parent. Avoids innerHTML so the
 * security hook stays happy and so we never accidentally parse HTML.
 *
 * @param {Node} parent
 */
function clearChildren( parent ) {
	while ( parent.firstChild ) {
		parent.removeChild( parent.firstChild );
	}
}

describe( 'consent-gating shim', () => {
	beforeEach( () => {
		clearChildren( document.head );
		clearChildren( document.body );
		delete window.gtmkitConsentGating;
		delete window.gtmkit;
		delete window.google_tag_manager;
	} );

	afterEach( () => {
		clearChildren( document.head );
		clearChildren( document.body );
	} );

	it( 'unblocks GTM when both required categories are granted', () => {
		appendMaskedScript();
		window.gtmkitConsentGating = {
			requiredCategories: [ 'analytics_storage', 'ad_storage' ],
			containerId: 'GTM-TEST123',
		};

		loadShim();

		window.dispatchEvent(
			new CustomEvent( 'gtmkit:consent:updated', {
				detail: {
					analytics_storage: 'granted',
					ad_storage: 'granted',
				},
			} )
		);

		const masked = document.querySelector(
			'script[data-gtmkit-gated="1"]'
		);
		expect( masked.getAttribute( 'data-gtmkit-unblocked' ) ).toBe( '1' );

		const clones = Array.from( document.querySelectorAll( 'script' ) ).filter(
			( s ) => s !== masked
		);
		expect( clones ).toHaveLength( 1 );
		expect( clones[ 0 ].type ).toBe( 'text/javascript' );
		expect( clones[ 0 ].text ).toBe( masked.text );
	} );

	it( 'does not unblock on partial consent', () => {
		appendMaskedScript();
		window.gtmkitConsentGating = {
			requiredCategories: [ 'analytics_storage', 'ad_storage' ],
			containerId: 'GTM-TEST123',
		};

		loadShim();

		window.dispatchEvent(
			new CustomEvent( 'gtmkit:consent:updated', {
				detail: { analytics_storage: 'granted' },
			} )
		);

		const masked = document.querySelector(
			'script[data-gtmkit-gated="1"]'
		);
		expect( masked.getAttribute( 'data-gtmkit-unblocked' ) ).toBeNull();

		const clones = Array.from( document.querySelectorAll( 'script' ) ).filter(
			( s ) => s !== masked
		);
		expect( clones ).toHaveLength( 0 );
	} );

	it( 'does not re-inject after a successful unblock (state flag idempotency)', () => {
		appendMaskedScript();
		window.gtmkitConsentGating = {
			requiredCategories: [ 'analytics_storage', 'ad_storage' ],
			containerId: 'GTM-TEST123',
		};

		loadShim();

		const detail = {
			analytics_storage: 'granted',
			ad_storage: 'granted',
		};
		window.dispatchEvent(
			new CustomEvent( 'gtmkit:consent:updated', { detail } )
		);
		window.dispatchEvent(
			new CustomEvent( 'gtmkit:consent:updated', { detail } )
		);
		window.dispatchEvent(
			new CustomEvent( 'gtmkit:consent:updated', { detail } )
		);

		const clones = Array.from( document.querySelectorAll( 'script' ) ).filter(
			( s ) => s.getAttribute( 'data-gtmkit-gated' ) !== '1'
		);
		expect( clones ).toHaveLength( 1 );
	} );

	it( 'skips injection when our specific GTM container is already booted', () => {
		appendMaskedScript();
		// Simulate GTM having already booted with our container id present
		// (e.g., a CMP also unblocked the script before this shim ran).
		window.google_tag_manager = { 'GTM-TEST123': { dataLayer: {} } };
		window.gtmkitConsentGating = {
			requiredCategories: [ 'analytics_storage', 'ad_storage' ],
			containerId: 'GTM-TEST123',
		};

		loadShim();

		window.dispatchEvent(
			new CustomEvent( 'gtmkit:consent:updated', {
				detail: {
					analytics_storage: 'granted',
					ad_storage: 'granted',
				},
			} )
		);

		const clones = Array.from( document.querySelectorAll( 'script' ) ).filter(
			( s ) => s.getAttribute( 'data-gtmkit-gated' ) !== '1'
		);
		expect( clones ).toHaveLength( 0 );
	} );

	it( 'still unmasks when google_tag_manager has only an unrelated debug key (gtag.js / Event Inspector)', () => {
		appendMaskedScript();
		// gtag.js (or a debug inspector) populates google_tag_manager with
		// a debugGroupId before our container has actually booted. The
		// shim must scope its check to our containerId and proceed with
		// the unmask anyway.
		window.google_tag_manager = { debugGroupId: '8085120801395561' };
		window.gtmkitConsentGating = {
			requiredCategories: [ 'analytics_storage', 'ad_storage' ],
			containerId: 'GTM-TEST123',
		};

		loadShim();

		window.dispatchEvent(
			new CustomEvent( 'gtmkit:consent:updated', {
				detail: {
					analytics_storage: 'granted',
					ad_storage: 'granted',
				},
			} )
		);

		const masked = document.querySelector(
			'script[data-gtmkit-gated="1"]'
		);
		expect( masked.getAttribute( 'data-gtmkit-unblocked' ) ).toBe( '1' );

		const clones = Array.from( document.querySelectorAll( 'script' ) ).filter(
			( s ) => s.getAttribute( 'data-gtmkit-gated' ) !== '1'
		);
		expect( clones ).toHaveLength( 1 );
	} );

	it( 'respects the data-gtmkit-unblocked DOM marker (third idempotency layer)', () => {
		const masked = appendMaskedScript();
		// Simulate a CMP that already unblocked by setting the marker.
		masked.setAttribute( 'data-gtmkit-unblocked', '1' );
		window.gtmkitConsentGating = {
			requiredCategories: [ 'analytics_storage', 'ad_storage' ],
			containerId: 'GTM-TEST123',
		};

		loadShim();

		window.dispatchEvent(
			new CustomEvent( 'gtmkit:consent:updated', {
				detail: {
					analytics_storage: 'granted',
					ad_storage: 'granted',
				},
			} )
		);

		const clones = Array.from( document.querySelectorAll( 'script' ) ).filter(
			( s ) => s.getAttribute( 'data-gtmkit-gated' ) !== '1'
		);
		expect( clones ).toHaveLength( 0 );
	} );

	it( 'reads window.gtmkit.consent.state on load and unblocks when the threshold is met', () => {
		appendMaskedScript();
		window.gtmkitConsentGating = {
			requiredCategories: [ 'analytics_storage', 'ad_storage' ],
			containerId: 'GTM-TEST123',
		};
		window.gtmkit = {
			consent: {
				state: {
					analytics_storage: 'granted',
					ad_storage: 'granted',
				},
			},
		};

		loadShim();

		const masked = document.querySelector(
			'script[data-gtmkit-gated="1"]'
		);
		expect( masked.getAttribute( 'data-gtmkit-unblocked' ) ).toBe( '1' );
	} );

	it( 'falls back to the default required-categories list when config is missing', () => {
		appendMaskedScript();
		// No window.gtmkitConsentGating — shim must default to
		// ['analytics_storage', 'ad_storage'].

		loadShim();

		// Partial: only analytics_storage. Should NOT unblock.
		window.dispatchEvent(
			new CustomEvent( 'gtmkit:consent:updated', {
				detail: { analytics_storage: 'granted' },
			} )
		);
		let masked = document.querySelector( 'script[data-gtmkit-gated="1"]' );
		expect( masked.getAttribute( 'data-gtmkit-unblocked' ) ).toBeNull();

		// Both: should unblock.
		window.dispatchEvent(
			new CustomEvent( 'gtmkit:consent:updated', {
				detail: {
					analytics_storage: 'granted',
					ad_storage: 'granted',
				},
			} )
		);
		masked = document.querySelector( 'script[data-gtmkit-gated="1"]' );
		expect( masked.getAttribute( 'data-gtmkit-unblocked' ) ).toBe( '1' );
	} );

	it( 'preserves CMP attributes on the cloned script (drops only type and data-gtmkit-gated)', () => {
		const masked = appendMaskedScript();
		masked.setAttribute( 'data-cookieconsent', 'ignore' );
		masked.setAttribute( 'data-cookieyes', 'cookieyes-analytics' );
		masked.setAttribute( 'data-cfasync', 'false' );

		window.gtmkitConsentGating = {
			requiredCategories: [ 'analytics_storage', 'ad_storage' ],
			containerId: 'GTM-TEST123',
		};

		loadShim();

		window.dispatchEvent(
			new CustomEvent( 'gtmkit:consent:updated', {
				detail: {
					analytics_storage: 'granted',
					ad_storage: 'granted',
				},
			} )
		);

		const clone = Array.from( document.querySelectorAll( 'script' ) ).find(
			( s ) => s.getAttribute( 'data-gtmkit-gated' ) !== '1'
		);
		expect( clone ).toBeDefined();
		expect( clone.type ).toBe( 'text/javascript' );
		expect( clone.getAttribute( 'data-gtmkit-gated' ) ).toBeNull();
		expect( clone.getAttribute( 'data-cookieconsent' ) ).toBe( 'ignore' );
		expect( clone.getAttribute( 'data-cookieyes' ) ).toBe(
			'cookieyes-analytics'
		);
		expect( clone.getAttribute( 'data-cfasync' ) ).toBe( 'false' );
	} );

	it( 'unblocks when window.gtmkit.consent.state has the cumulative threshold even if event detail is partial', () => {
		appendMaskedScript();
		window.gtmkitConsentGating = {
			requiredCategories: [ 'analytics_storage', 'ad_storage' ],
			containerId: 'GTM-TEST123',
		};
		window.gtmkit = {
			consent: {
				state: {
					analytics_storage: 'granted',
					ad_storage: 'denied',
				},
			},
		};

		loadShim();

		// Partial event; cumulative state reaches the threshold via the
		// authoritative window.gtmkit.consent.state surface that update()
		// would mutate before firing the event.
		window.gtmkit.consent.state.ad_storage = 'granted';
		window.dispatchEvent(
			new CustomEvent( 'gtmkit:consent:updated', {
				detail: { ad_storage: 'granted' },
			} )
		);

		const masked = document.querySelector(
			'script[data-gtmkit-gated="1"]'
		);
		expect( masked.getAttribute( 'data-gtmkit-unblocked' ) ).toBe( '1' );
	} );
} );
