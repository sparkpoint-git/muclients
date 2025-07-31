<?php
/**
 * Shipper tasks: Confirm password
 *
 * @package shipper
 */

/**
 * Shipper confirm password class
 */
class Shipper_Task_Api_Authentication_Check extends Shipper_Task_Api {

	/**
	 * Dashboard API
	 *
	 * @var WPMUDEV_Dashboard_Api|null
	 */
	private $dashboard_api = null;

	/**
	 * Shipper_Task_Api_Authentication_Check constructor.
	 *
	 * @param WPMUDEV_Dashboard_Api|null $dashboard_api Dashboard API instance $dashboard_api.
	 *
	 * @since 1.2.4
	 */
	public function __construct( WPMUDEV_Dashboard_Api $dashboard_api = null ) {
		if ( $dashboard_api ) {
			$this->dashboard_api = $dashboard_api;
		} elseif ( class_exists( 'WPMUDEV_Dashboard_Api' ) ) {
			$this->dashboard_api = new WPMUDEV_Dashboard_Api();
		}
	}

	/**
	 * Check dashboard authentication
	 *
	 * @since 1.1.4
	 *
	 * @param array $request request args.
	 *
	 * @return bool
	 */
	public function apply( $request = array() ) {
		$auth_key = ! empty( $request['auth_key'] ) ? trim( $request['auth_key'] ) : null;

		return $auth_key && $this->dashboard_api->get_key() === $auth_key;
	}
}