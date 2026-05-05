#!/usr/bin/env node
/**
 * Bundle-size guard for the strong-block consent-gating shim.
 *
 * The shim ships in `<head>` on every page when strong-block mode is on,
 * so its size matters for first-paint. The roadmap budget is 1024 bytes
 * minified; this script enforces that hard limit and exits non-zero on
 * regression so CI fails before the shim drifts.
 */

'use strict';

const fs = require( 'node:fs' );
const path = require( 'node:path' );

const ASSET_PATH = path.resolve(
	__dirname,
	'..',
	'assets',
	'frontend',
	'consent-gating.js'
);
const LIMIT_BYTES = 1024;

if ( ! fs.existsSync( ASSET_PATH ) ) {
	console.error(
		`consent-gating.js not found at ${ ASSET_PATH }. Run \`npm run uglify:consent-gating\` first.`
	);
	process.exit( 1 );
}

const stat = fs.statSync( ASSET_PATH );
const bytes = stat.size;

if ( bytes > LIMIT_BYTES ) {
	console.error(
		`consent-gating.js is ${ bytes } bytes, exceeds the ${ LIMIT_BYTES }-byte budget.`
	);
	console.error( 'See docs/filters.md "Strong-block configuration" for the rationale.' );
	process.exit( 1 );
}

console.log( `consent-gating.js OK: ${ bytes } / ${ LIMIT_BYTES } bytes.` );
