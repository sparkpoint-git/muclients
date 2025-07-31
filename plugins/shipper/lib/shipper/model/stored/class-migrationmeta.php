<?php
/**
 * Migration meta class
 *
 * @package Shipper
 */

/**
 * Class Shipper_Model_Stored_MigrationMeta
 */
class Shipper_Model_Stored_MigrationMeta extends Shipper_Model_Stored {
	const KEY_EXCLUSIONS_FS   = 'fs_exclusions';
	const KEY_EXCLUSIONS_DB   = 'db_exclusions';
	const KEY_EXCLUSIONS_XX   = 'xx_exclusions';
	const KEY_DBPREFIX_OPTION = 'dbprefix_option';
	const KEY_DBPREFIX_VALUE  = 'dbprefix_value';
	const NETWORK_MODE        = 'network_mode';
	const NETWORK_SUBSITE_ID  = 'network_subsite_id';
	const KEY_OTHER_TABLES    = 'db_other_table';

	const SELF_TRIGGER = 'self-trigger';

	const REMOTE_PING_ATTEMPT  = 'remote-ping-attempt';
	const REMOTE_START_ATTEMPT = 'remote-start-attempt';

	/**
	 * Shipper_Model_Stored_MigrationMeta constructor.
	 */
	public function __construct() {
		parent::__construct( 'migrationmeta' );
	}

	/**
	 * Get db prefix option
	 *
	 * @return mixed
	 */
	public function get_dbprefix_option() {
		return $this->get( self::KEY_DBPREFIX_OPTION, false );
	}

	/**
	 * Get db prefix value
	 *
	 * @return false|mixed
	 */
	public function get_dbprefix_value() {
		return $this->get( self::KEY_DBPREFIX_VALUE, false );
	}

	/**
	 * Get netowrk mode
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

	/**
	 * Get source path
	 *
	 * @return string
	 */
	public function get_source() {
		$site_id = $this->get_site_id();
		$blog    = get_blog_details( $site_id );

		return shipper_get_protocol_agnostic( $blog->siteurl, true );
	}

	/**
	 * Check if it's extract mode or not
	 *
	 * @return bool
	 */
	public function is_extract_mode() {
		return $this->get_mode() === 'subsite';
	}

	/**
	 * Check if it's self trigger or not
	 *
	 * @return bool
	 */
	public function is_self_trigger() {
		return $this->get( self::SELF_TRIGGER );
	}
}