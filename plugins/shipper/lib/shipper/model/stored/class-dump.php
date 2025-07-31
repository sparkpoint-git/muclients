<?php
/**
 * Shipper stored models: stored package meta representation
 *
 * @since v1.1
 * @package shipper
 */

/**
 * Shipper stored package meta data class
 */
class Shipper_Model_Stored_Dump extends Shipper_Model_Stored {

	/**
	 * Shipper_Model_Stored_Dump constructor.
	 */
	public function __construct() {
		parent::__construct( 'dump.sql' );
	}
}