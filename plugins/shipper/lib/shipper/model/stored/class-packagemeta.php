<?php
/**
 * Author: Hoang Ngo
 *
 * @package shipper
 */

/**
 * Class Shipper_Model_Stored_PackageMeta
 */
class Shipper_Model_Stored_PackageMeta extends Shipper_Model_Stored {
	const NETWORK_TYPE       = 'network-type';
	const NETWORK_SUBSITE_ID = 'network-subsite-id';

	const KEY_EXCLUSIONS_FS = 'fs_exclusions';
	const KEY_EXCLUSIONS_DB = 'db_exclusions';
	const KEY_EXCLUSIONS_XX = 'xx_exclusions';
	const KEY_OTHER_TABLES  = 'db_other_table';

	const TABLES_PICKED = 'tables-picked';

	/**
	 * Shipper_Model_Stored_PackageMeta constructor.
	 */
	public function __construct() {
		parent::__construct( 'package-meta' );
	}

	/**
	 * Get network mode
	 *
	 * @return mixed
	 */
	public function get_mode() {
		return $this->get( self::NETWORK_TYPE );
	}

	/**
	 * Get site id
	 *
	 * @return mixed
	 */
	public function get_site_id() {
		return intval( $this->get( self::NETWORK_SUBSITE_ID ) );
	}

	/**
	 * Check whether it's extract mode or not
	 *
	 * @return bool
	 */
	public function is_extract_mode() {
		return $this->get_mode() === 'subsite';
	}

	/**
	 * Check whether it's a whole network or not.
	 *
	 * @return bool
	 */
	public function is_whole_network() {
		return $this->get_mode() === 'whole_network';
	}
}