<?php
/**
 * Shipper helpers: serialized values replacer
 *
 * Handles low level serialized values replacement transformations.
 *
 * @package shipper
 */

/**
 * String replacer class
 */
class Shipper_Helper_Serialized_Replacer {
	/**
	 * Whether to use regex replace or not
	 *
	 * @var bool
	 */
	private static $regex_replace;

	/**
	 * Shipper_Helper_Replacer_Serialized constructor.
	 *
	 * @param bool $regex_replace whether to use regex or search replace.
	 */
	public function __construct( $regex_replace = true ) {
		self::$regex_replace = $regex_replace;
	}

	/**
	 * Replace the string
	 *
	 * @param string $search string to replace.
	 * @param string $replace replacement string.
	 * @param string $string subject string.
	 * @param int    $count number of replacement.
	 *
	 * @return array|mixed|string|string[]|null
	 */
	private static function str_replace( $search, $replace, $string, &$count = 0 ) {
		if ( self::$regex_replace ) {
			return preg_replace( "/{$search}/m", $replace, $string, -1, $count );
		}

		if ( function_exists( 'mb_split' ) ) {
			return self::mb_str_replace( $search, $replace, $string, $count );
		} else {
			return str_replace( $search, $replace, $string, $count );
		}
	}

	/**
	 * Multibyte replace
	 *
	 * @param string $search string to replace.
	 * @param string $replace replacement string.
	 * @param string $subject subject string.
	 * @param int    $count number of replacement.
	 *
	 * @return array|mixed|string
	 */
	private static function mb_str_replace( $search, $replace, $subject, &$count = 0 ) {
		if ( ! is_array( $subject ) ) {
			// Normalize $search and $replace so they are both arrays of the same length.
			$searches     = is_array( $search ) ? array_values( $search ) : array( $search );
			$replacements = is_array( $replace )
				? array_values( $replace )
				: array( $replace );
			$replacements = array_pad( $replacements, count( $searches ), '' );

			foreach ( $searches as $key => $search ) {
				$parts = mb_split( preg_quote( $search, '/' ), $subject );

				if ( ! is_array( $parts ) ) {
					continue;
				}

				$count  += count( $parts ) - 1;
				$subject = implode( $replacements[ $key ], $parts );
			}
		} else {
			// Call mb_str_replace for each subject in array, recursively.
			foreach ( $subject as $key => $value ) {
				$subject[ $key ] = self::mb_str_replace( $search, $replace, $value, $count );
			}
		}

		return $subject;
	}

	/**
	 * Replace the serialized string
	 *
	 * @param string $from string to find.
	 * @param string $to string to replace.
	 * @param string $data subject string.
	 * @param bool   $serialised whether its serialized or not.
	 *
	 * @return __PHP_Incomplete_Class|array|mixed|string|string[]|null
	 */
	public function replace( $from = '', $to = '', $data = '', $serialised = false ) {
		// phpcs:disable
		if ( is_string( $data ) && is_serialized( $data ) && ( $unserialized = unserialize( $data ) ) !== false ) {
			$data = $this->replace( $from, $to, $unserialized, true );
		} elseif ( is_array( $data ) ) {
			$tmp_array = array();

			foreach ( $data as $key => $value ) {
				$tmp_array[ $key ] = $this->replace( $from, $to, $value, false );
			}

			$data = $tmp_array;
			unset( $tmp_array );
		} elseif ( is_object( $data ) && ! $data instanceof __PHP_Incomplete_Class ) {
			$tmp_object = $data;
			$props      = get_object_vars( $data );

			foreach ( $props as $key => $value ) {
				if ( is_int( $key ) ) {
					continue;
				}

				$tmp_object->$key = $this->replace( $from, $to, $value, false );
			}

			$data = $tmp_object;
			unset( $tmp_object );
		} elseif ( is_string( $data ) ) {
			$data = self::str_replace( $from, $to, $data );
		}

		if ( $serialised ) {
			return serialize( $data );
		}

		return $data;
	}
}