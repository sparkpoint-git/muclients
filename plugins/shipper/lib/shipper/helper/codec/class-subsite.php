<?php
/**
 * Subsite codec file
 *
 * @package shipper
 */

/**
 * Class Shipper_Helper_Codec_Subsite
 */
class Shipper_Helper_Codec_Subsite extends Shipper_Helper_Codec {
	/**
	 * Meta model holder.
	 *
	 * @var mixed|\Shipper_Model_Stored_MigrationMeta
	 */
	protected $meta;

	/**
	 * Gets a list of replacement pairs
	 *
	 * A single replacement pair list, with current domain as key and
	 * replacement macro as value.
	 *
	 * @return array
	 */
	public function get_replacements_list() {
		if ( ! is_multisite() ) {
			return array();
		}
		global $wpdb;
		$base_prefix = $wpdb->base_prefix;
		$lists       = array(
			$base_prefix . $this->meta->get_site_id() . '_'               => $base_prefix,
			'{{SHIPPER_TABLE_PREFIX}}' . $this->meta->get_site_id() . '_' => '{{SHIPPER_TABLE_PREFIX}}',
		);

		if ( constant( 'SUBDOMAIN_INSTALL' ) === false ) {
			$blog = get_blog_details( $this->meta->get_site_id() );
			if ( is_object( $blog ) ) {
				// get the domain only.
				$cleanpath = shipper_get_protocol_agnostic( $blog->siteurl, true );
				$site_url  = shipper_get_protocol_agnostic( network_site_url(), true );
				$path_url  = str_replace( $site_url, '', $cleanpath );
				$path_url  = ltrim( $path_url, '/' );

				$lists[ '{{SHIPPER_URL_WITH_SCHEME}}\/' . $path_url ] = '{{SHIPPER_URL_WITH_SCHEME}}';
			}
		}

		return $lists;
	}

	/**
	 * Gets expansion replacement string
	 *
	 * @param string $name Original domain.
	 * @param string $value Process-dependent domain representation.
	 *                      (macro on export, original on import).
	 *
	 * @return string
	 */
	public function get_replacement( $name, $value ) {
		return $value;
	}

	/**
	 * Gets a regex expression matcher string
	 *
	 * Purposefully single-task oriented - just process the subset of SQL
	 * statements actually used by the export process (drop|create|insert).
	 *
	 * Will match an entire line (one line per statement).
	 *
	 * @param string $string Original table name.
	 * @param string $value Optional table name with prefix replaced with a macro.
	 *
	 * @return string
	 */
	public function get_matcher( $string, $value = '' ) {
		return $string;
	}

	/**
	 * Shipper_Helper_Codec_Subsite constructor.
	 *
	 * @param null $meta Meta model instance holder.
	 */
	public function __construct( $meta = null ) {
		if ( null === $meta ) {
			$this->meta = new Shipper_Model_Stored_MigrationMeta();
		} else {
			$this->meta = $meta;
		}
	}
}