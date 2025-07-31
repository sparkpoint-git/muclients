<?php
/**
 * Need to keep the database access info instead of replace all
 * Author: Hoang Ngo
 *
 * @package shipper
 */

/**
 * Class Shipper_Helper_Codec_DbInfo
 */
class Shipper_Helper_Codec_DbInfo extends Shipper_Helper_Codec {

	/**
	 * Get replacements list
	 *
	 * @return array|string[]
	 */
	public function get_replacements_list() {
		return array(
			"^define\s*\(\s*'DB_NAME'\s*,\s*('.*?')\s*\);$"     => "define('DB_NAME', '" . DB_NAME . "');",
			"^define\s*\(\s*'DB_USER'\s*,\s*('.*?')\s*\);$"     => "define('DB_NAME', '" . DB_USER . "');",
			"^define\s*\(\s*'DB_PASSWORD'\s*,\s*('.*?')\s*\);$" => "define('DB_NAME', '" . DB_PASSWORD . "');",
			"^define\s*\(\s*'DB_HOST'\s*,\s*('.*?')\s*\);$"     => "define('DB_NAME', '" . DB_HOST . "');",
		);
	}

	/**
	 * Get matcher
	 *
	 * @param string $string string to match.
	 * @param string $value value to match.
	 *
	 * @return string
	 */
	public function get_matcher( $string, $value = '' ) {
		return $string;
	}

	/**
	 * Get replacement
	 *
	 * @param string $name get replacement string.
	 * @param string $value get replacement value.
	 *
	 * @return string
	 */
	public function get_replacement( $name, $value ) {
		return $value;
	}
}