<?php
/**
 * Shipper models: dynamic inclusions and exclusions list
 *
 * Holds list of files *not* to be included in a migration.
 *
 * @package shipper
 */

/**
 * Stored exclusions model class
 *
 * @since 1.2.1
 */
class Shipper_Model_Stored_ExcludeInclude extends Shipper_Model_Stored {

	const INCLUDES = 'includes';
	const EXCLUDES = 'excludes';

	/**
	 * Constructor
	 *
	 * Sets up appropriate storage namespace
	 */
	public function __construct() {
		parent::__construct( 'exclude_include' );
	}

	/**
	 * Get exclude lists
	 *
	 * @return array
	 */
	public function get_excludes() {
		return $this->get( self::EXCLUDES, array() );
	}

	/**
	 * Get exclude lists
	 *
	 * @return array
	 */
	public function get_includes() {
		return $this->get( self::INCLUDES, array() );
	}

	/**
	 * Set includes
	 *
	 * @param array $data array of data.
	 */
	public function set_includes( $data = array() ) {
		$this->set( self::INCLUDES, array_unique( array_merge( $this->get_includes(), $data ) ) )->save();
	}

	/**
	 * Set includes
	 *
	 * @param array $data array of data.
	 */
	public function set_excludes( $data = array() ) {
		$this->set( self::EXCLUDES, array_unique( array_merge( $this->get_excludes(), $data ) ) )->save();
	}
}