<?php
/**
 * Regression guard for translatable theme-header reads.
 *
 * `WP_Theme::get( 'Name' | 'Description' | 'Author' | 'AuthorURI' | 'ThemeURI' | 'Tags' )`
 * routes through `translate_with_gettext_context()` against the active
 * theme's text domain, which JIT-loads it on first access. WP 6.7+ logs
 * a `_doing_it_wrong` notice when JIT loading triggers before `init`,
 * which surfaces on plugintests.com against parent themes that have an
 * empty `Template:` header (e.g. twentyseventeen).
 *
 * Read the directory name with `get_template()` / `get_stylesheet()`
 * instead. Both return cached strings and never load translations.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Unit\Common;

use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Source-level guard against `wp_get_theme()->get( 'Name' | ... )` reads
 * in the production tree.
 */
final class ThemeReadJitGuardTest extends TestCase {

	/**
	 * No production source file may call a translatable `WP_Theme::get()`
	 * variant. Tests, fixtures, and vendor code are out of scope.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\Conditionals\BricksConditional::is_met
	 * @covers \TLA_Media\GTM_Kit\Admin\Suggestions::suggest_premium
	 * @covers \TLA_Media\GTM_Kit\Common\Util::get_site_data
	 */
	public function test_no_translatable_theme_header_reads_in_src(): void {
		$root = dirname( __DIR__, 4 );
		$src  = $root . '/src';

		$translatable = [ 'Name', 'Description', 'Author', 'AuthorURI', 'ThemeURI', 'Tags' ];
		$pattern      = sprintf(
			'/wp_get_theme\s*\(\s*\)\s*->\s*get\s*\(\s*[\'"](%s)[\'"]\s*\)/',
			implode( '|', $translatable )
		);

		$offenders = [];
		$iterator  = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $src ) );

		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() || $file->getExtension() !== 'php' ) {
				continue;
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- WP_Filesystem is unavailable in unit-test context; reading a known local file path with file_get_contents is the simple, correct call here.
			$contents = file_get_contents( $file->getPathname() );
			if ( preg_match_all( $pattern, $contents, $matches, PREG_OFFSET_CAPTURE ) ) {
				foreach ( $matches[0] as $hit ) {
					$line          = substr_count( substr( $contents, 0, (int) $hit[1] ), "\n" ) + 1;
					$relative_path = ltrim( str_replace( $root, '', $file->getPathname() ), '/' );
					$offenders[]   = sprintf( '%s:%d (%s)', $relative_path, $line, $hit[0] );
				}
			}
		}

		$this->assertSame(
			[],
			$offenders,
			"Found wp_get_theme()->get('Name'|'Description'|...) calls in production source. "
				. 'These trigger JIT loading of the active theme\'s text domain and produce '
				. '_doing_it_wrong notices on plugintests.com. Use get_template() or '
				. "get_stylesheet() (untranslated directory names) for slug comparisons.\n  - "
				. implode( "\n  - ", $offenders )
		);
	}
}
