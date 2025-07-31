<?php
/**
 * Author: Hoang Ngo
 *
 * @package shipper
 */

/**
 * Class Shipper_Task_Package_Wpmudev
 */
class Shipper_Task_Package_Wpmudev extends Shipper_Task_Export {

	/**
	 * Apply method
	 *
	 * @param array $args array of arguments.
	 *
	 * @return bool|mixed
	 */
	public function apply( $args = array() ) {
		if ( ! is_multisite() ) {
			return true;
		}

		$meta = new Shipper_Model_Stored_PackageMeta();

		if ( $meta->get_mode() !== 'subsite' || $meta->get_site_id() === 1 ) {
			return true;
		}

		$keys = array(
			'wdp_un_limit_to_user',
			'wdp_un_auth_user',
			'wdp_un_enable_sso',
			'wdp_un_sso_userid',
			'wpmudev_apikey',
		);

		foreach ( $keys as $key ) {
			$value = get_network_option( null, $key );

			if ( false === $value ) {
				continue;
			}

			update_blog_option( $meta->get_site_id(), $key, $value );
		}

		return true;
	}

	/**
	 * Get work description
	 *
	 * @return string|void
	 */
	public function get_work_description() {
		return __( 'Preparing WPMUDEV information', 'shipper' );
	}

	/**
	 * Get source path
	 *
	 * @param string $path file path.
	 * @param object $migration migration model.
	 *
	 * @return string|void
	 */
	public function get_source_path( $path, $migration ) {
		Shipper_Helper_Log::write( 'sp' . $path );
	}

	/**
	 * Get destination type
	 *
	 * @return string
	 */
	public function get_destination_type() {
		return Shipper_Model_Stored_Migration::COMPONENT_FS;
	}

	/**
	 * Get total steps.
	 *
	 * @return int
	 */
	public function get_total_steps() {
		return 1;
	}

	/**
	 * Get current step
	 *
	 * @return int
	 */
	public function get_current_step() {
		return 1;
	}
}