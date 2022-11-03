<?php

namespace TLA_Media\GTM_Kit;

use TLA_Media\GTM_Kit\Admin\OptionsForm;

/** @var OptionsForm $form */

if ( ! defined( 'GTMKIT_VERSION' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}
?>
<div class="gtmkit-setting-row gtmkit-setting-row-heading gtmkit-clear">
	<h2>
		<?php esc_html_e( 'Help', 'gtmkit' ); ?>
	</h2>
</div>


<div class="gtmkit_section_message">
	<div class="stuffbox">
		<h3 class="hndle"><?php esc_html_e( 'Override settings in wp-config.php', 'gtmkit' ); ?></h3>
		<div class="inside">
			<ul>
				<li><code>define( 'GTMKIT_ON', true );</code>
					// <?php esc_html_e( 'True activates constants support and false turns it off', 'gtmkit' ); ?></li>
				<li><code>define( 'GTMKIT_CONTAINER_ID', 'GTM-XXXXXXX' );</code>
					// <?php esc_html_e( 'The GTM container ID', 'gtmkit' ); ?></li>
				<li><code>define( 'GTMKIT_CONTAINER_ACTIVE', false );</code>
					// <?php esc_html_e( 'Or true, in which case the constant is ignored', 'gtmkit' ); ?></li>
			</ul>
		</div>
	</div>
</div>

<div class="gtmkit_section_message">
	<div class="stuffbox">
		<h3 class="hndle"><?php esc_html_e( 'Support', 'gtmkit' ); ?></h3>
		<div class="inside">
			<ul>
				<li><a hreflang="https://wordpress.org/support/plugin/gtm-kit/" target="_blank">Support forum</a></li>
				<li><a hreflang="https://gtmkit.com/" target="_blank">Plugin Homepage</a> (gtmkit.com)</li>
				<li><a hreflang="https://wordpress.org/plugins/gtm-kit/" target="_blank">WordPress.org Plugin Page</a></li>
			</ul>
		</div>
	</div>
</div>

<div class="gtmkit_section_message space-top">
	<div class="stuffbox">
		<h3 class="hndle"><?php esc_html_e( 'About GTM Kit', 'gtmkit' ); ?> <span
				class="version">(Version <?php echo esc_html( GTMKIT_VERSION ); ?>)</span></h3>
		<div class="inside">
			<ul>
				<li><?php esc_html_e( 'The goal of GTM Kit is to provide a flexible tool for generating the data layer for Google Tag Manager.', 'gtmkit' ); ?></li>
			</ul>
		</div>
	</div>
</div>
