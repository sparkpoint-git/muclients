<?php
/**
 * Shipper codec: replace define statements
 *
 * @package shipper
 */

/**
 * Define replacer class
 *
 * Only used in sub-site to single site API migration for decoding.
 *
 * @since 1.2.8
 */
class Shipper_Helper_Codec_MsDefine extends Shipper_Helper_Codec {

	/**
	 * Gets a list of replacement pairs
	 *
	 * A replacement pair is represented like so:
	 * Define name as a key, define value as replacement macro.
	 *
	 * @return array
	 */
	public function get_replacements_list() {
		// phpcs:disable
		return array(
			"define\(\s*'SUNRISE'\s*,\s*(.*)\s*\);"                    => null,
			"define\(\s*'MULTISITE'\s*,\s*(true|false)\s*\);"          => null,
			"define\(\s*'WP_ALLOW_MULTISITE'\s*,\s*(true|false)\s*\);" => null,
			"define\(\s*'SUBDOMAIN_INSTALL'\s*,\s*(true|false)\s*\);"  => null,
			"define\s*\(\s*'DOMAIN_CURRENT_SITE'\s*,\s*'.*?'\s*\);"    => null,
			"define\s*\(\s*'PATH_CURRENT_SITE'\s*,\s*'.*?'\s*\);"      => null,
			"define\(\s*'SITE_ID_CURRENT_SITE'\s*,\s*\d+\s*\);"        => null,
			"define\(\s*'BLOG_ID_CURRENT_SITE'\s*,\s*\d+\s*\);"        => null,
		);
		// phpcs:enable
	}

	/**
	 * Checks if the original value is present
	 *
	 * Codec implementation will not substitute with a value (and will remove
	 * the entire matcher from result) if the original is not present.
	 *
	 * Needs to be overridden for more complex codec situations, where the
	 * context is not a concrete value, but rather a context pointer (name),
	 * such as with defines, variables and such.
	 *
	 * @param string $original Original value context.
	 *
	 * @return bool
	 */
	public function is_original_present( $original ) {
		return false;
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