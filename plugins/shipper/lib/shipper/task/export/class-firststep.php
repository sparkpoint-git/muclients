<?php
/**
 * Shipper tasks: import, staging area cleanup task
 *
 * @package shipper
 */

/**
 *
 * Class Shipper_Task_Export_FirstStep
 */
class Shipper_Task_Export_FirstStep extends Shipper_Task_Export {

	/**
	 * Gets import task label
	 *
	 * @return string
	 */
	public function get_work_description() {
		return __( 'Updating information...', 'shipper' );
	}

	/**
	 * Task runner method
	 *
	 * Returns (bool)true on completion.
	 *
	 * @param array $args Not used.
	 *
	 * @return bool
	 */
	public function apply( $args = array() ) {
		if ( ! is_multisite() ) {
			// this only apply for sub-site extractor.
			return true;
		}

		// It's a sub-site, so wpmudev info won't be there in the sub-site options table.
		// So we're going to update those info in the sub-site options table.
		// Otherwise dash plugin will be broken and api migration will fail.
		$meta = new Shipper_Model_Stored_MigrationMeta();

		if ( $meta->is_extract_mode() ) {
			$keys = array(
				'wdp_un_limit_to_user',
				'wdp_un_auth_user',
				'wdp_un_enable_sso',
				'wdp_un_sso_userid',
				'wpmudev_apikey',
				'shipper_version',
			);

			foreach ( $keys as $key ) {
				$value = get_network_option( null, $key );

				if ( false === $value ) {
					continue;
				}

				update_blog_option( $meta->get_site_id(), $key, $value );
			}
		}

		$migration = new Shipper_Model_Stored_Migration();

		if ( $migration->is_from_hub() ) {
			// Admin used import migration.
			// We only allow multisite to multisite import ony.
			$meta->set( Shipper_Model_Stored_MigrationMeta::NETWORK_MODE, 'whole_network' );
			$meta->set( Shipper_Model_Stored_MigrationMeta::NETWORK_SUBSITE_ID, 1 );
			$meta->save();
		}

		return true;
	}

	/**
	 * Get source path.
	 *
	 * @param string $path file path.
	 * @param object $migration migration object instance.
	 *
	 * @return string
	 */
	public function get_source_path( $path, $migration ) {
		Shipper_Helper_Log::write( $path );

		return $path;
	}

	/**
	 * Get destination type
	 *
	 * @return string
	 */
	public function get_destination_type() {
		return '';
	}

	/**
	 * Get total setps
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