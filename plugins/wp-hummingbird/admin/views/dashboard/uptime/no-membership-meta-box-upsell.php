<?php
/**
 * Uptime upsell notice.
 *
 * @since 3.1.2
 * @package Hummingbird
 */

use Hummingbird\Core\Hub_Connector;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="sui-upsell-notice sui-padding sui-padding-top--hidden sui-padding-bottom__desktop--hidden">
	<div class="sui-upsell-notice__content">
		<div class="sui-notice sui-notice-blue">
			<div class="sui-notice-content">
				<div class="sui-notice-message">
					<span class="sui-notice-icon sui-icon-info sui-md" aria-hidden="true"></span>
					<p><?php esc_html_e( 'Performance improvements hardly matter if your website isn’t accessible. Monitor your uptime and downtime with WPMU DEV’s Uptime Monitoring website management tool.', 'wphb' ); ?></p>
					<p><a class="sui-button sui-button-blue" href="<?php echo esc_url( Hub_Connector::get_connect_site_url( 'wphb-uptime', 'hummingbird_dash_uptime_connect_link' ) ); ?>" onclick="window.wphbMixPanel.trackHBUpsell( 'uptime', 'dash_widget', 'cta_clicked', this.href, 'hb_uptime_upsell' );">
							<?php esc_html_e( 'CONNECT SITE', 'wphb' ); ?>
						</a></p>
				</div>
			</div>
		</div>
	</div>
</div>