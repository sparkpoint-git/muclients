<?php
/**
 * A helper filter out media files
 *
 * @package shipper
 * @since 1.2.4
 */

/**
 * Class Shipper_Helper_Iterator_Filter_Media
 */
class Shipper_Helper_Iterator_Filter_Plugins extends Shipper_Helper_Iterator_Filter {

	/**
	 * File holder
	 *
	 * @var array
	 */
	private static $files = array();

	/**
	 * Shipper_Helper_Iterator_Filter_Plugins constructor.
	 *
	 * @param RecursiveIterator $iterator Iterator Instance.
	 */
	public function __construct( RecursiveIterator $iterator ) {
		parent::__construct( $iterator );

		if ( empty( static::$files ) ) {
			static::$files = $this->un_trial_slash( $this->get_plugins_to_be_blocked() );
		}
	}

	/**
	 * Get a list of plugins path to be blocked.
	 *
	 * @since 1.2.4
	 *
	 * @return array
	 */
	private function get_plugins_to_be_blocked() {
		if ( ! static::is_sub_site() ) {
			return array();
		}

		$blocked_plugins_paths = static::$model->get( Shipper_Model_Stored_Iterators::BLOCKED_PLUGIN_PATHS, false );

		if ( false !== $blocked_plugins_paths ) {
			return $blocked_plugins_paths;
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins      = array_keys( get_plugins() );
		$all_plugins_path = array_map(
			function ( $path ) {
				return trailingslashit( WP_PLUGIN_DIR ) . dirname( $path );
			},
			$all_plugins
		);

		$active_plugins      = Shipper_Helper_MS::get_site_info( static::get_site_id() )['plugins'];
		$active_plugins_path = array_map(
			function ( $path ) {
				return trailingslashit( WP_PLUGIN_DIR ) . dirname( $path );
			},
			$active_plugins
		);

		// All the plugins paths of other sub-sites.
		$blocked_plugins_paths = array_diff( $all_plugins_path, $active_plugins_path );
		static::$model->set( Shipper_Model_Stored_Iterators::BLOCKED_PLUGIN_PATHS, $blocked_plugins_paths )->save();

		return $blocked_plugins_paths;
	}

	/**
	 * Accept the file we want to add to zip
	 *
	 * @return bool
	 */
	public function accept() {
		$file = parent::current();

		if ( $file->isLink() ) {
			return false;
		}

		if ( in_array( $file->getPathname(), static::$files, true ) ) {
			return false;
		}

		if ( apply_filters( 'shipper_path_exclude_file', false, $file->getPathname() ) ) {
			// Using this hook to filter out files, will slow down the overall migration process.
			// Instead check if you can make use of `Shipper_Model_Stored_Exclusions` class.
			return false;
		}

		return true;
	}

	/**
	 * Get children
	 *
	 * @return RecursiveFilterIterator
	 */
	public function getChildren() {
		return new self( $this->getInnerIterator()->getChildren() );
	}
}