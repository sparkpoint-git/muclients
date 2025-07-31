<?php
/**
 * Force Failure Class
 *
 * @package shipper
 */

/**
 * Class Shipper_Model_Force
 *
 * @since 1.2.6
 */
class Shipper_Model_Force {

	/**
	 * Whether to stuck on pre-flight check for API migration or not
	 *
	 * @return bool
	 */
	public static function maybe_stuck_on_migration_preflight() {
		return defined( 'SHIPPER_FORCE_STUCK_ON_API_MIGRATION_PREFLIGHT' ) && SHIPPER_FORCE_STUCK_ON_API_MIGRATION_PREFLIGHT;
	}
}