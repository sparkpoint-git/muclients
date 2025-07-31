<?php
/**
 * Author: Hoang Ngo
 *
 * @package shipper
 */

/**
 * Class Shipper_Helper_Replacer_Subsite
 */
class Shipper_Helper_Replacer_Subsite extends Shipper_Helper_Replacer {

	/**
	 * Transform a file.
	 *
	 * @param string $source file souce.
	 *
	 * @return string
	 */
	public function transform( $source ) {
		$fs = Shipper_Helper_Fs_File::open( $source );

		if ( ! $fs ) {
			return false;
		}

		$content = $fs->fread( $fs->getSize() );

		if ( ! $content ) {
			return $source;
		}

		// List to replace.
		// $prefix_id_blahlaj.
		// {{SHIPPER_TABLE_PREFIX}}3_options => {{SHIPPER_TABLE_PREFIX}}_options.
		global $wpdb;
		$base_prefix = $wpdb->base_prefix;
		$meta        = new Shipper_Model_Stored_PackageMeta();
		$search      = array(
			$base_prefix . $meta->get_site_id() . '_' => $base_prefix,
			'{{SHIPPER_TABLE_PREFIX}}' . $meta->get_site_id() . '_' => '{{SHIPPER_TABLE_PREFIX}}',
		);
		foreach ( $search as $s => $r ) {
			$content = str_replace( $s, $r, $content );
		}

		$fs = Shipper_Helper_Fs_File::open( $source, 'w' );

		if ( ! $fs ) {
			return false;
		}

		$fs->fwrite( $content );

		return $source;
	}

	/**
	 * Shipper_Helper_Replacer_Subsite constructor.
	 */
	public function __construct() {
		parent::__construct( 'package' );
	}
}