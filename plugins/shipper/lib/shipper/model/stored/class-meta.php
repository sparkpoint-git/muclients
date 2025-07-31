<?php
/**
 * Author: Hoang Ngo
 * Hold some pre data likes db prefix, network, etc
 *
 * @package shipper
 */

/**
 * Class Shipper_Model_Stored_Meta
 */
class Shipper_Model_Stored_Meta extends Shipper_Model_Stored {
	const NETWORK_MODE       = 'network-mode';
	const NETWORK_SUBSITE_ID = 'network-subsite-id';

	/**
	 * Shipper_Model_Stored_Meta constructor.
	 */
	public function __construct() {
		parent::__construct( 'migration-meta' );
	}

	/**
	 * Get network mode
	 *
	 * @return false|mixed
	 */
	public function get_mode() {
		return $this->get( self::NETWORK_MODE );
	}

	/**
	 * Get site id
	 *
	 * @return false|mixed
	 */
	public function get_site_id() {
		return intval( $this->get( self::NETWORK_SUBSITE_ID ) );
	}
}
