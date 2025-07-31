<?php
/**
 * Author: Hoang Ngo
 *
 * @package shipper
 */

/**
 * Class Shipper_Helper_Codec_Pluginactivate
 */
class Shipper_Helper_Codec_Pluginactivate extends Shipper_Helper_Codec {

	/**
	 * Shipper_Model_Stored_MigrationMeta meta holder.
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
		$site_info = Shipper_Helper_MS::get_site_info( $this->meta->get_site_id() );
		$lookup    = array(
			'active_plugins' => serialize( $site_info['plugins'] ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
			'template'       => $site_info['template'],
			'stylesheet'     => $site_info['stylesheet'],
		);
		$lists     = array();
		foreach ( $lookup as $key => $val ) {
			$new_var           = ", '{$key}', '{$val}', 'yes');";
			$matcher           = ",\s*'{$key}'\s*,\s*('.+')\s*,\s*'yes'\);$";
			$lists[ $matcher ] = $new_var;
			Shipper_Helper_Log::debug( "$key " . $val );
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
	 * Shipper_Helper_Codec_Pluginactivate constructor.
	 *
	 * @param null $meta Shipper_Model_Stored_MigrationMeta instance holder.
	 */
	public function __construct( $meta = null ) {
		if ( null === $meta ) {
			$this->meta = new Shipper_Model_Stored_MigrationMeta();
		} else {
			$this->meta = $meta;
		}
	}

	/**
	 * Encodes the values
	 *
	 * Used in the export process.
	 * Converts all found context values into their generalized replacements.
	 *
	 * @param string $source Source string to process.
	 *
	 * @return string
	 */
	public function encode( $source = '' ) {
		$definitions = (array) apply_filters(
			'shipper_codec_' . $this->get_codec_type() . '_replacements_encode',
			$this->get_replacements_list()
		);
		foreach ( $definitions as $original => $macro ) {
			$rx     = $this->get_matcher( $original );
			$value  = $this->get_replacement( $original, $macro );
			$count  = 0;
			$source = preg_replace( "/{$rx}/m", $value, $source, - 1, $count );
		}

		return $source;
	}
}