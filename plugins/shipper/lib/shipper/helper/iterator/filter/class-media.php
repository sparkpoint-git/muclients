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
class Shipper_Helper_Iterator_Filter_Media extends Shipper_Helper_Iterator_Filter {

	/**
	 * Files holder.
	 *
	 * @var array
	 */
	private static $files = array();

	/**
	 * Shipper_Helper_Iterator_Filter_Media constructor.
	 *
	 * @param RecursiveIterator $iterator Iterator Instance.
	 */
	public function __construct( RecursiveIterator $iterator ) {
		parent::__construct( $iterator );

		if ( empty( static::$files ) ) {
			static::$files = $this->un_trial_slash( $this->get_media_path_to_be_blocked() );
		}
	}

	/**
	 * Get media files to be blocked which are on upload dirs.
	 *
	 * @since 1.2.4
	 *
	 * @return array
	 */
	private function get_media_path_to_be_blocked() {
		if ( static::is_whole_network() ) {
			// It's a whole network. So we don't need to block anything.
			return array();
		}

		$blocked_paths = static::$cached_iterators->get( Shipper_Model_Stored_Iterators::BLOCKED_MEDIA_PATHS, false );

		if ( false !== $blocked_paths ) {
			return $blocked_paths;
		}

		if ( ! is_multisite() || 1 === static::get_site_id() ) {
			// It's not a multisite, so there won't be any `/uploads/sites/` dir.
			// But if we find any, let's block them.
			// If its the main site of the network, we don't need `/uploads/sites/` too.
			$blocked_paths = array( WP_CONTENT_DIR . '/uploads/sites/' );
			static::$model->set( Shipper_Model_Stored_Iterators::BLOCKED_MEDIA_PATHS, $blocked_paths )->save();

			return $blocked_paths;
		}

		$all_sub_sites_excluding_destination_site = array_diff(
			get_sites(
				array(
					'fields' => 'ids',
					'number' => PHP_INT_MAX,
				)
			),
			array( static::get_site_id() )
		);

		$blocked_paths = array_map(
			function( $site_id ) {
				return WP_CONTENT_DIR . '/uploads/sites/' . $site_id;
			},
			$all_sub_sites_excluding_destination_site
		);

		static::$cached_iterators->set( Shipper_Model_Stored_Iterators::BLOCKED_MEDIA_PATHS, $blocked_paths )->save();

		return $blocked_paths;
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