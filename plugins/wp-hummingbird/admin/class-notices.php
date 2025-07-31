<?php
/**
 * Notices class.
 *
 * @package Hummingbird
 */

namespace Hummingbird\Admin;

use Hummingbird\Core\Hub_Connector;
use Hummingbird\Core\Settings;
use Hummingbird\Core\Utils;
use WPMUDEV_Dashboard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Notices
 */
class Notices {

	/**
	 * In order to avoid duplicated notices,
	 * we save notices IDs here
	 *
	 * @var    array $displayed_notices
	 * @access protected
	 */
	protected static $displayed_notices = array();

	/**
	 * Instance of class.
	 *
	 * @since  1.7.0
	 * @access private
	 * @var    $instance
	 */
	private static $instance = null;

	/**
	 * Return the plugin instance.
	 *
	 * @since 1.7.0
	 * @return Notices
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Notices constructor.
	 */
	public function __construct() {
		$current_page = filter_input( INPUT_GET, 'page', FILTER_UNSAFE_RAW );

		// Do not init notices on setup page.
		if ( 'wphb-setup' === sanitize_text_field( $current_page ) ) {
			return;
		}

		$dismiss = filter_input( INPUT_GET, 'wphb-dismiss', FILTER_UNSAFE_RAW );
		if ( $dismiss ) {
			$this->dismiss( sanitize_text_field( $dismiss ) );
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		add_action( 'upgrader_process_complete', array( $this, 'plugin_changed' ) );
		add_action( 'activated_plugin', array( $this, 'plugin_changed' ) );
		add_action( 'deactivated_plugin', array( $this, 'plugin_changed' ) );
		add_action( 'after_switch_theme', array( $this, 'plugin_changed' ) );

		// This will show notice on both multisite and single site.
		add_action( 'admin_notices', array( $this, 'clear_cache' ) );
		add_action( 'network_admin_notices', array( $this, 'clear_cache' ) );

		// Only show notices to users who can do something about it (update, for example).
		$cap = is_multisite() ? 'manage_network_plugins' : 'update_plugins';
		if ( ! current_user_can( $cap ) ) {
			return;
		}

		if ( is_multisite() ) {
			add_action( 'network_admin_notices', array( $this, 'upgrade_to_pro' ) );
			add_action( 'network_admin_notices', array( $this, 'free_version_deactivated' ) );
			add_action( 'network_admin_notices', array( $this, 'free_version_rate' ) );
			add_action( 'network_admin_notices', array( $this, 'site_monitoring' ) );
			add_action( 'network_admin_notices', array( $this, 'plugin_compat_check' ) );
			add_action( 'network_admin_notices', array( $this, 'legacy_critical_css_deprecation_notice' ) );
		} else {
			add_action( 'admin_notices', array( $this, 'upgrade_to_pro' ) );
			add_action( 'admin_notices', array( $this, 'free_version_deactivated' ) );
			add_action( 'admin_notices', array( $this, 'site_monitoring' ) );
			add_action( 'admin_notices', array( $this, 'free_version_rate' ) );
			add_action( 'admin_notices', array( $this, 'plugin_compat_check' ) );
			add_action( 'admin_notices', array( $this, 'legacy_critical_css_deprecation_notice' ) );
		}
	}

	/**
	 * Show notice about Redis deprecation.
	 *
	 * @since 3.9.3
	 */
	public function legacy_critical_css_deprecation_notice() {
		if ( $this->is_dismissed( 'legacy-critical-css', 'option' ) ) {
			return;
		}

		if ( ! Utils::is_admin_dashboard() && ! preg_match( '/^(toplevel|hummingbird)(-pro)*_page_wphb/', get_current_screen()->id ) ) {
			return;
		}

		$minify = Utils::get_module( 'minify' );
		if ( ! $minify->is_active() || Utils::get_module( 'critical_css' )->is_active() ) {
			return;
		}

		$critical_css_mode = Settings::get_setting( 'critical_css_mode', 'minify' );
		$css               = $minify::get_css();
		if ( ! $critical_css_mode ) {
			$critical_css_mode = ( $css ? 'manual_css' : 'critical_css' );
		}

		if ( 'manual_css' !== $critical_css_mode ) {
			return;
		}

		$heading = __( 'Important Update: CSS Optimization Changes in Hummingbird', 'wphb' );
		$message = __( '🚨 Heads Up! We’re streamlining our services and the Optimize CSS Delivery feature will soon be removed. We suggest switching to the new and improved Generate Critical CSS feature immediately for optimal performance.', 'wphb' );
		$message = '<h3>' . $heading . '</h3><p>' . $message . '</p>';

		$dismiss_url = wp_nonce_url( add_query_arg( 'wphb-dismiss', 'legacy-critical-css' ), 'wphb-dismiss-notice' );
		?>
		<div class="notice-warning notice wphb-notice">
			<?php echo wp_kses_post( $message ); ?>
			<p>
				<?php if ( ! is_multisite() ) { ?>
					<a href="javascript:void(0)" id="wphb-switch-critical-from-legacy" class="button button-primary">
						<?php esc_html_e( 'Switch To Automatic CSS Optimization', 'wphb' ); ?>
					</a>
				<?php } ?>
				<a href="<?php echo esc_url( $dismiss_url ); ?>" style="
				<?php
				if ( ! is_multisite() ) {
					echo 'margin-left: 10px;color: #888;'; }
				?>
				text-decoration: none;">
					<?php esc_html_e( 'I Understand, Remove This Notice', 'wphb' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Clear the notice blocker on plugin activate/deactivate.
	 *
	 * @since 1.7.0
	 * @used-by activated_plugin action
	 * @used-by deactivated_plugin action
	 */
	public function plugin_changed() {
		$detection = Settings::get_setting( 'detection', 'page_cache' );

		// Do nothing selected in settings.
		if ( 'none' === $detection ) {
			return;
		}

		// Show notice.
		if ( 'manual' === $detection ) {
			update_option( 'wphb-notice-cache-cleaned-show', 'yes' );
			return;
		}

		// Auto clear cache, don't show any notice.
		if ( 'auto' === $detection ) {
			$modules = array( 'page_cache', 'minify' );
			foreach ( $modules as $mod ) {
				$module = Utils::get_module( $mod );
				if ( ! $module->is_active() ) {
					continue;
				}

				// Make sure no settings are cleared during auto page cache purge.
				if ( 'minify' === $mod ) {
					$module->clear_cache( false );
				} else {
					$module->clear_cache();
				}
			}
		}
	}

	/**
	 * Display sui compatible notice with flexibility.
	 *
	 * @since 3.15.0
	 * @access private
	 * @param  string $id             Unique identifier.
	 * @param  string $message        Notice message.
	 * @param  bool   $additional     Additional content that goes after the message text.
	 * @param  bool   $only_hb_pages  Show message only on Hummingbird pages.
	 * @param  string $sui_class       SUI class for the notice.
	 */
	private function show_sui_notice( $id = '', $message = '', $additional = false, $only_hb_pages = false, $sui_class = 'blue' ) {
		// Only run on HB pages.
		if ( $only_hb_pages && ! preg_match( '/^(toplevel|hummingbird)(-pro)*_page_wphb/', get_current_screen()->id ) ) {
			return;
		}

		$dismiss_url = wp_nonce_url( add_query_arg( 'wphb-dismiss', $id ), 'wphb-dismiss-notice' );
		?>
		<div class="sui-wrap notice-<?php echo esc_attr( $id ); ?>">
			<div class="sui-notice sui-notice-<?php echo esc_attr( $sui_class ); ?>">
				<div class="sui-notice-content">
					<div class="sui-notice-message">
							<a class="wphb-dismiss" href="<?php echo esc_url( $dismiss_url ); ?>">
								<span class="sui-icon-close sui-sm"></span>
								<span class="screen-reader-text">
									<?php esc_html_e( 'Dismiss this notice.', 'wphb' ); ?>
								</span>
							</a>
							<?php echo wp_kses_post( $message ); ?>
							<?php if ( $additional ) : ?>
								<p>
									<?php echo wp_kses_post( $additional ); ?>
								</p>
							<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Display notice HTML code.
	 *
	 * @since  1.7.0
	 * @access private
	 * @param  string $id             Accepted: upgrade-to-pro, free-deactivated, free-rated, plugin-compat.
	 * @param  string $message        Notice message.
	 * @param  bool   $additional     Additional content that goes after the message text.
	 * @param  bool   $only_hb_pages  Show message only on Hummingbird pages.
	 */
	private function show_notice( $id = '', $message = '', $additional = false, $only_hb_pages = false ) {
		// Only run on HB pages.
		if ( $only_hb_pages && ! preg_match( '/^(toplevel|hummingbird)(-pro)*_page_wphb/', get_current_screen()->id ) ) {
			return;
		}

		$dismiss_url = wp_nonce_url( add_query_arg( 'wphb-dismiss', $id ), 'wphb-dismiss-notice' );
		?>
		<div class="notice-info notice wphb-notice">
			<a class="wphb-dismiss" href="<?php echo esc_url( $dismiss_url ); ?>">
				<span class="dashicons dashicons-dismiss"></span>
				<span class="screen-reader-text">
					<?php esc_html_e( 'Dismiss this notice.', 'wphb' ); ?>
				</span>
			</a>
			<?php echo wp_kses_post( $message ); ?>
			<?php if ( $additional ) : ?>
				<p>
					<?php echo wp_kses_post( $additional ); ?>
				</p>
			<?php endif; ?>
		</div>
		<style>
			.wphb-notice .wphb-dismiss {
				color: #aaaaaa;
				float: right;
				padding: 15px;
				position: absolute;
				right: 1px;
				text-decoration: none;
				top: 0;
			}
			body:not(.wpmud) .wphb-notice .wphb-dismiss {
				position: relative;
				padding: 10px 0;
			}
		</style>
		<?php
	}

	/**
	 * Check if a notice has been dismissed by the current user.
	 *
	 * Will accept: 'user' for user options, 'option' for site wide options and
	 *              'site' for sub site options.
	 *
	 * @since  1.7.0 changed to private
	 * @access private
	 * @param  string $notice  Notice.
	 * @param  string $mode    Default: 'user'.
	 * @return mixed
	 */
	private function is_dismissed( $notice, $mode = 'user' ) {
		if ( 'user' === $mode ) {
			return get_user_meta( get_current_user_id(), 'wphb-' . $notice . '-dismissed' );
		}

		if ( 'option' === $mode ) {
			return 'yes' !== get_option( 'wphb-notice-' . $notice . '-show' );
		}

		return false;
	}

	/**
	 * Dismiss a notice.
	 *
	 * @since  1.7.0 changed to private
	 * @access private
	 * @param  string $notice  Notice.
	 */
	private function dismiss( $notice ) {
		check_admin_referer( 'wphb-dismiss-notice' );

		$user_notices = array(
			'upgrade-to-pro',
			'plugin-compat',
		);

		$options_notices = array(
			'free-deactivated',
			'free-rated',
			'cache-cleaned',
			'legacy-critical-css',
			'connect-for-site-monitoring',
		);

		if ( in_array( $notice, $user_notices, true ) ) {
			update_user_meta( get_current_user_id(), 'wphb-' . $notice . '-dismissed', true );
		} elseif ( in_array( $notice, $options_notices, true ) ) {
			delete_option( 'wphb-notice-' . $notice . '-show' );
		}

		$redirect = remove_query_arg( array( 'wphb-dismiss', '_wpnonce' ) );
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Show top floating notice (SUI style).
	 *
	 * @since 2.6.0
	 *
	 * @param string $message  The notice text.
	 * @param string $type     Notice type.
	 */
	public function show_floating( $message, $type = 'success' ) {
		?>
		<script>
			document.addEventListener( 'DOMContentLoaded', function () {
				WPHB_Admin.notices.show(
					"<?php echo wp_kses_post( $message ); ?>",
					"<?php echo esc_attr( $type ); ?>"
				);
			} );
		</script>
		<?php
	}

	/**
	 * Show inline notice (SUI style).
	 *
	 * @since 2.6.0
	 *
	 * @param string $message  The notice text.
	 * @param string $class    Class for the notice wrapper.
	 * @param mixed  ...$data  Variable list of addition text.
	 */
	public function show_inline( $message, $class = 'success', ...$data ) {
		if ( 'sui-upsell-notice' === $class ) {
			$this->show_inline_upsell( $message, ...$data );
			return;
		}
		?>
		<div class="sui-notice sui-notice-<?php echo esc_attr( $class ); ?>">
			<div class="sui-notice-content">
				<div class="sui-notice-message">
					<span class="sui-notice-icon sui-icon-info sui-md" aria-hidden="true"></span>
					<p><?php echo wp_kses_post( $message ); ?></p>
					<?php foreach ( $data as $p ) : ?>
						<?php if ( ! empty( $p ) ) : ?>
							<?php echo '<p>' . $p . '</p>'; ?>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Show inline upsell notice (SUI style).
	 *
	 * This is an upsell implementation of an upsell notice to show with an image on the left side.
	 * Can be triggered by calling show_inline() with a 'sui-upsell-notice' $class as an argument.
	 *
	 * @since 2.6.0
	 *
	 * @param string $message  The notice text.
	 * @param mixed  ...$data  Variable list of addition text.
	 */
	private function show_inline_upsell( $message, ...$data ) {
		?>
		<div class="sui-upsell-notice">
			<p>
				<?php echo wp_kses_post( $message ); ?>
				<?php foreach ( $data as $p ) : ?>
					<?php echo wp_kses_post( $p ); ?>
				<?php endforeach; ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Check if the notice can be displayed.
	 *
	 * @since 2.6.0  Refactored from show().
	 *
	 * @param string $id  Unique identifier for the notice.
	 *
	 * @return bool
	 */
	public function can_show_notice( $id ) {
		// Is already dismissed ?
		if ( $this->is_dismissed( $id, 'option' ) ) {
			return false;
		}

		if ( in_array( $id, self::$displayed_notices, true ) ) {
			return false;
		}

		self::$displayed_notices[] = $id;

		return true;
	}

	/**
	 * Show inline dismissible notice (SUI style).
	 *
	 * @since 2.6.0  Refactored from show().
	 *
	 * @param string $id       Unique identifier for the notice.
	 * @param string $message  The notice text.
	 * @param string $class    Class for the notice wrapper.
	 */
	public function show_inline_dismissible( $id, $message, $class = 'sui-notice-error' ) {
		if ( ! current_user_can( Utils::get_admin_capability() ) ) {
			return;
		}

		// Is already dismissed ?
		if ( $this->is_dismissed( $id, 'option' ) ) {
			return;
		}

		if ( in_array( $id, self::$displayed_notices, true ) ) {
			return;
		}

		self::$displayed_notices[] = $id;
		?>
		<div class="sui-notice <?php echo esc_attr( $class ); ?>" id="<?php echo esc_attr( $id ); ?>" role="alert" style="display: block">
			<div class="sui-notice-content">
				<div class="sui-notice-message">
					<span class="sui-notice-icon sui-icon-info sui-md" aria-hidden="true"></span>
					<p><?php echo wp_kses_post( $message ); ?></p>
					<p>
						<a role="button" href="#" style="color: #888;text-transform: uppercase" onclick="WPHB_Admin.notices.dismiss( this )">
							<?php esc_html_e( 'Dismiss', 'wphb' ); ?>
						</a>
					</p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * *************************
	 * NOTICES
	 ***************************/

	/**
	 * Available notices.
	 *
	 * @see Notices::upgrade_to_pro()
	 * @see Notices::free_version_deactivated()
	 * @see Notices::free_version_rate()
	 */

	/**
	 * Show Upgrade to Pro notice
	 *
	 * User is authenticated into WPMU DEV but it has free version installed
	 */
	public function upgrade_to_pro() {
		if ( $this->is_dismissed( 'upgrade-to-pro' ) ) {
			return;
		}

		if ( ! class_exists( 'WPMUDEV_Dashboard' ) ) {
			return;
		}

		$dashboard = WPMUDEV_Dashboard::instance();
		if ( ! is_object( $dashboard ) ) {
			return;
		}

		if ( defined( 'WPHB_WPORG' ) && WPHB_WPORG && Utils::is_member() ) {
			$url = WPMUDEV_Dashboard::$ui->page_urls->plugins_url;
			/* translators: %s: Upgrade URL */
			$message = sprintf( __( 'Awww yeah! You’ve got access to Hummingbird Pro! Let’s upgrade your free version so you can start using premium features. <a href="%s">Upgrade</a>', 'wphb' ), esc_url( $url ) );
			$message = '<p>' . $message . '</p>';
			$this->show_notice( 'upgrade-to-pro', $message, false, true );
		}
	}

	/**
	 * Notice displayed when the free version is deactivated because the pro one was already active
	 */
	public function free_version_deactivated() {
		if ( ! file_exists( WP_PLUGIN_DIR . '/hummingbird-performance/wp-hummingbird.php' ) ) {
			return;
		}

		// If the Pro version files are not there, or plugin is not active - bail.
		if ( ! file_exists( WP_PLUGIN_DIR . '/wp-hummingbird/wp-hummingbird.php' ) || ! is_plugin_active( 'wp-hummingbird/wp-hummingbird.php' ) ) {
			// Probably a stored notice from a previous install - remove the notice.
			delete_site_option( 'wphb-notice-free-deactivated-show' );
			return;
		}

		if ( $this->is_dismissed( 'free-deactivated', 'option' ) ) {
			return;
		}

		$text = '<p>' . __( 'We noticed you’re running both the free and pro versions of Hummingbird. No biggie! We’ve deactivated the free version for you. Enjoy the pro features!', 'wphb' ) . '</p>';
		$this->show_notice(
			'free-deactivated',
			$text
		);
	}

	/**
	 * Show Site Monitoring to Performance report.
	 *
	 * User is not authenticated into WPMU DEV and it has a report.
	 */
	public function site_monitoring() {
		$page = filter_input( INPUT_GET, 'page', FILTER_UNSAFE_RAW );

		if ( 'wphb-performance' !== $page || $this->is_dismissed( 'connect-for-site-monitoring', 'option' ) ) {
			return;
		}

		if ( ! Settings::get( 'wphb-last-report' ) || Utils::has_access_to_hub() ) {
			return;
		}

		$image_part  = sprintf(
			'<img class="sui-image" aria-hidden="true" alt="" src="%1$s" srcset="%1$s 1x, %2$s 2x" />',
			esc_url( WPHB_DIR_URL . 'admin/assets/image/hb-graphic-uptime-connect@1x.png' ),
			esc_url( WPHB_DIR_URL . 'admin/assets/image/hb-graphic-uptime-connect@2x.png' )
		);
		$message     = esc_html__( 'Get notified instantly when your site is down or too slow — and act fast to keep visitors happy. All you need is a free WPMU DEV account.', 'wphb' );
		$heading     = esc_html__( 'Free 24/7 Site Monitoring', 'wphb' );
		$connect_str = esc_html__( 'Connect Site to Activate', 'wphb' );
		$connect_url = esc_url( Hub_Connector::get_connect_site_url( 'wphb-uptime', 'hummingbird_performance-test_uptime-prompt' ) );
		$link_icon   = '<span class="sui-icon-link" aria-hidden="true"></span>';

		$message  = '<div class="wphb-wrapper"> <div>' . $image_part . '</div>
		<div><h4>' . $heading . '</h4> <p>' . $message . '</p>';
		$message .= '<a class="sui-button sui-button-blue" href="' . $connect_url . '"> ' . $link_icon . $connect_str . ' </a></div></div>';

		$this->show_sui_notice( 'connect-for-site-monitoring', $message, false, true );
	}

	/**
	 * Offer the user to submit a review for the free version of the plugin.
	 *
	 * @since 1.5.4
	 */
	public function free_version_rate() {
		if ( Utils::is_member() ) {
			return;
		}

		if ( Utils::is_admin_dashboard() ) {
			return;
		}

		if ( $this->is_dismissed( 'free-rated', 'option' ) ) {
			return;
		}

		// Show only if at least 7 days have past after installation of the free version.
		$free_installation = get_site_option( 'wphb-free-install-date' );
		if ( ( time() - (int) $free_installation ) < 604800 ) {
			return;
		}

		$text            = '<p>' . esc_html__( "We've spent countless hours developing Hummingbird and making it free for you to use. We would really appreciate it if you dropped us a quick rating!", 'wphb' ) . '</p>';
		$additional_text = '<p><a href="https://wordpress.org/support/plugin/hummingbird-performance/reviews/" class="sui-button sui-button-blue" target="_blank">' . __( 'Rate Hummingbird', 'wphb' ) . '</a></p>';
		$this->show_notice(
			'free-rated',
			$text,
			$additional_text
		);
	}

	/**
	 * Show clear cache notice.
	 *
	 * @since 1.7.0
	 */
	public function clear_cache() {
		if ( $this->is_dismissed( 'cache-cleaned', 'option' ) ) {
			return;
		}

		// Only show if minification or page cache is enabled.
		$minify_active  = Utils::get_module( 'minify' )->is_active();
		$caching_active = Utils::get_module( 'page_cache' )->is_active();

		// If both modules disabled - don't show notice.
		if ( ! $minify_active && ! $caching_active ) {
			return;
		}

		$text       = __( "We've noticed you've made changes to your website. We recommend you clear Hummingbird's page cache to avoid any issues.", 'wphb' );
		$additional = '';

		if ( $minify_active ) {
			// Add new files link.
			$recheck_file_url = add_query_arg(
				array(
					'recheck-files' => 'true',
					'_wpnonce'      => wp_create_nonce( 'wphb-recheck-files' ),
				),
				Utils::get_admin_menu_url( 'minification' )
			);

			$text = __( "We've noticed you've made changes to your website. If you’ve installed new plugins or themes, we recommend you re-check Hummingbird's Asset Optimization configuration to ensure those new files are added	correctly.", 'wphb' );

			if ( ! ( is_multisite() && is_network_admin() ) ) {
				$additional .= '<a href="' . esc_url( $recheck_file_url ) . '" class="button button-primary" style="margin-right:10px">' . __( 'Re-check Asset Optimization', 'wphb' ) . '</a>';
			}
		}

		$additional .= '<a href="#" id="wp-admin-notice-wphb-clear-cache" class="button">' . __( 'Clear Cache', 'wphb' ) . '</a>';
		if ( $caching_active ) {
			$adjust_settings_url = Utils::get_admin_menu_url( 'caching' ) . '&view=settings';
			if ( ! is_multisite() || is_network_admin() ) {
				$additional .= '<a href="' . esc_url( $adjust_settings_url ) . '" style="color:#888;margin-left:10px;text-decoration:none">' . __( 'Adjust notification settings', 'wphb' ) . '</a>';
			}
		}

		$text = '<p>' . $text . '</p>';
		$this->show_notice(
			'cache-cleaned',
			$text,
			$additional
		);
	}

	/**
	 * Generates text for the admin notice with a list of incompatible plugins
	 *
	 * @param array $incompat_plugins List of incompatible plugins if any.
	 *
	 * @return string Text message to be displayed
	 */
	public static function plugin_incompat_message( $incompat_plugins ) {
		$text = '<p>' . esc_html__( 'You have multiple WordPress performance plugins installed. This may cause unpredictable behavior and can even break your site. For best results, use only one performance plugin at a time. ', 'wphb' );

		if ( count( $incompat_plugins ) > 1 ) {
			$text .= esc_html__( 'These plugins may cause issues with Hummingbird:', 'wphb' ) . '</p>';

			$text .= '<ul id="wphb-incompat-plugin-list">';

			foreach ( $incompat_plugins as $plugin ) {
				$text .= "<li><strong>$plugin</strong></li>";
			}

			$text .= '</ul>';
		} else {
			$text .= sprintf( /* translators: %s - plugin name */
				esc_html__( '%s plugin may cause issues with Hummingbird.', 'wphb' ),
				'<strong>' . $incompat_plugins[ key( $incompat_plugins ) ] . '</strong>'
			) . '</p>';
		}

		return $text;
	}

	/**
	 * Display a admin notice if any of the incompatible plugin is installed.
	 */
	public function plugin_compat_check() {
		if ( $this->is_dismissed( 'plugin-compat' ) ) {
			return;
		}

		$incompatible_plugins = Utils::get_incompat_plugin_list();

		if ( count( $incompatible_plugins ) <= 0 ) {
			return;
		}

		$text = $this->plugin_incompat_message( $incompatible_plugins );

		// CTA.
		if ( is_multisite() && current_user_can( 'manage_network_plugins' ) ) {
			$plugins_url = network_admin_url( 'plugins.php' );
		} else {
			$plugins_url = admin_url( 'plugins.php' );
		}

		$dismiss_url = wp_nonce_url( add_query_arg( 'wphb-dismiss', 'plugin-compat' ), 'wphb-dismiss-notice' );

		$additional  = '<a href="' . esc_url( $plugins_url ) . '" id="wphb-manage-plugins" class="button button-primary">' . esc_html__( 'Manage plugins', 'wphb' ) . '</a>';
		$additional .= '<a role="button" href="' . esc_url( $dismiss_url ) . '" class="wphb-dismiss-cta">' . esc_html__( 'Dismiss', 'wphb' ) . '</a>';

		$this->show_notice( 'plugin-compat', $text, $additional, true );
	}

}