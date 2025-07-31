<?php
/**
 * Shipper models: Cached Iterators Model
 *
 * Holds information for the cached iterators
 *
 * @since 2.1.4
 *
 * @package shipper
 */

/**
 * Class Shipper_Model_Stored_Iterators
 */
class Shipper_Model_Stored_Iterators extends Shipper_Model_Stored {
	const BLOCKED_PLUGIN_PATHS = 'blocked_plugin_paths';
	const BLOCKED_MEDIA_PATHS  = 'blocked_media_paths';

	/**
	 * Shipper_Model_Stored_Iterators constructor.
	 */
	public function __construct() {
		parent::__construct( 'iterators' );
	}
}