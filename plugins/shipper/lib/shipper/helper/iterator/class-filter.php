<?php
/**
 * Abstract Filter Iterator
 *
 * @package shipper
 * @since 1.2.4
 */

/**
 * Class Shipper_Helper_Iterator_Filter
 */
abstract class Shipper_Helper_Iterator_Filter extends RecursiveFilterIterator {

	/**
	 * Model instance
	 *
	 * @var Shipper_Model_Stored
	 */
	protected static $model;

	/**
	 * Cached iterators
	 *
	 * @var Shipper_Model_Stored_Iterators
	 */
	protected static $cached_iterators;

	/**
	 * Shipper_Helper_Iterator_Filter constructor.
	 *
	 * @param RecursiveIterator         $iterator Iterator Instance.
	 * @param Shipper_Model_Stored|null $model Model Instance.
	 */
	public function __construct( RecursiveIterator $iterator, Shipper_Model_Stored $model = null ) {
		parent::__construct( $iterator );

		if ( ! static::$model ) {
			static::$model = $model ? $model : new Shipper_Model_Stored_PackageMeta();
		}

		if ( ! static::$cached_iterators ) {
			static::$cached_iterators = new Shipper_Model_Stored_Iterators();
		}
	}

	/**
	 * Check whether it's sub-site or not
	 *
	 * @return bool
	 */
	public static function is_sub_site() {
		static $is_sub_site = null;

		if ( is_null( $is_sub_site ) ) {
			$is_sub_site = static::$model->is_extract_mode();
		}

		return $is_sub_site;
	}

	/**
	 * Check whether it's a whole network or not
	 *
	 * @return bool
	 */
	public static function is_whole_network() {
		static $is_whole_network = null;

		if ( is_null( $is_whole_network ) ) {
			$is_whole_network = static::$model->is_whole_network();
		}

		return $is_whole_network;
	}

	/**
	 * Get site id
	 *
	 * @return int
	 */
	public static function get_site_id() {
		static $site_id = null;

		if ( is_null( $site_id ) ) {
			$site_id = static::$model->get_site_id();
		}

		return $site_id;
	}

	/**
	 * Remove trial slash
	 *
	 * @param array $paths list of file path.
	 *
	 * @return array|string[]
	 */
	protected function un_trial_slash( $paths ) {
		return array_map(
			function( $path ) {
				return untrailingslashit( $path );
			},
			$paths
		);
	}
}