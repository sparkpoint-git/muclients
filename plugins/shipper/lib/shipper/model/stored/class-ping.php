<?php
/**
 * Ping Failure Detector
 *
 * @package shipper
 */

/**
 * Class Shipper_Model_Stored_Ping
 *
 * @since 1.2.6
 */
class Shipper_Model_Stored_Ping extends Shipper_Model_Stored {

	const ACTION_FIRED         = 'action_fired';
	const ACTION_REGISTER_TIME = 'when';

	/**
	 * TTL holder
	 *
	 * @var int
	 */
	public $ttl = MINUTE_IN_SECONDS * 2;

	/**
	 * Shipper_Model_Stored_Ping constructor.
	 */
	public function __construct() {
		parent::__construct( 'shipper-ping' );
	}

	/**
	 * Log the action
	 *
	 * @return void
	 */
	public function log_action() {
		if ( $this->action_fired() ) {
			return;
		}

		$this
			->set( self::ACTION_FIRED, true )
			->set( self::ACTION_REGISTER_TIME, strtotime( current_time( 'mysql' ) ) )
			->save();
	}

	/**
	 * Clear the action
	 *
	 * @return void
	 */
	public function clear_action() {
		$this
			->set( self::ACTION_FIRED, false )
			->set( self::ACTION_REGISTER_TIME, 0 )
			->save();
	}

	/**
	 * Check whether the action is fired or not
	 *
	 * @return bool
	 */
	public function action_fired() {
		return ! empty( $this->get( self::ACTION_FIRED ) );
	}

	/**
	 * Get TTL
	 *
	 * @return int
	 */
	public function get_ttl() {
		return $this->ttl;
	}

	/**
	 * Shipper_Controller_Runner::ping is called
	 * But Shipper_Controller_Runner::json_process_request is never fired.
	 * Seems like it's stuck in constrained server.
	 *
	 * @return bool
	 */
	public function maybe_show_package_migration_notice() {
		$current_time = strtotime( current_time( 'mysql' ) );

		return $this->action_fired() && $current_time > $this->get( self::ACTION_REGISTER_TIME ) + $this->get_ttl();
	}
}