<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Admin;

use TLA_Media\GTM_Kit\Common\Conditionals\PremiumConditional;
use TLA_Media\GTM_Kit\Common\Util;
use TLA_Media\GTM_Kit\Options;

/**
 * HelpOptionsPage
 */
final class HelpOptionsPage extends AbstractOptionsPage {

	/**
	 * The option group.
	 *
	 * @var string
	 */
	protected string $option_group = 'help';

	/**
	 * Create an instance of the options page.
	 *
	 * @param Options $options The Options instance.
	 * @param Util    $util The Util instance.
	 *
	 * @return AbstractOptionsPage
	 */
	protected static function create_instance( Options $options, Util $util ): AbstractOptionsPage {
		return new self( $options, $util );
	}

	/**
	 * Configure the options page.
	 */
	public function configure(): void {
		register_setting( $this->get_menu_slug(), $this->option_name );
	}

	/**
	 * Get the options page menu slug.
	 *
	 * @return string
	 */
	protected function get_menu_slug(): string {
		return 'gtmkit_help';
	}

	/**
	 * Get the admin page menu title.
	 *
	 * @return string
	 */
	protected function get_menu_title(): string {
		return __( 'Help', 'gtm-kit' );
	}

	/**
	 * Get the options page title.
	 *
	 * @return string
	 */
	protected function get_page_title(): string {
		return __( 'Help', 'gtm-kit' );
	}

	/**
	 * Get the parent slug of the options page.
	 *
	 * @return string
	 */
	protected function get_parent_slug(): string {
		return 'gtmkit_general';
	}

	/**
	 * Enqueue admin page scripts and styles.
	 *
	 * @param mixed $hook Current hook.
	 */
	public function enqueue_page_assets( $hook ): void {
		if ( \strpos( $hook, $this->get_menu_slug() ) !== false ) {
			$this->enqueue_assets( 'help', 'settings' );
		}
	}

	/**
	 * Localize script.
	 *
	 * @param string $page_slug The page slug.
	 * @param string $script_handle The script handle.
	 */
	public function localize_script( string $page_slug, string $script_handle ): void {

		\wp_localize_script(
			'gtmkit-' . $script_handle . '-script',
			'gtmkitSettings',
			[
				'rootId'       => 'gtmkit-settings',
				'currentPage'  => $page_slug,
				'root'         => \esc_url_raw( rest_url() ),
				'nonce'        => \wp_create_nonce( 'wp_rest' ),
				'tutorials'    => $this->get_tutorials(),
				'adminPageUrl' => $this->util->get_admin_page_url(),
				'settings'     => $this->options->get_all_raw(),
				'site_data'    => [ 'gtmkit_version' => GTMKIT_VERSION ],
				'isPremium'    => ( new PremiumConditional() )->is_met(),
			]
		);
	}

	/**
	 * Get the templates
	 *
	 * @return array<string, mixed>
	 */
	private function get_tutorials(): array {
		return $this->util->get_data( '/get-tutorials', 'gtmkit_tutorials' );
	}
}
