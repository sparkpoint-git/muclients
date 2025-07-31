<?php
/**
 * Shipper helpers: Serialized JSON Replacer
 *
 * Handles low level serialized values replacement transformations.
 *
 * @package shipper
 * @since 1.2.4
 */

/**
 * Class Shipper_Helper_Replacer_JSON
 */
class Shipper_Helper_Replacer_JSON extends Shipper_Helper_Replacer {

	/**
	 * String holder
	 *
	 * @var string
	 */
	private $string;

	const SHIPPER_JSON_START = '{{SHIPPER_JSON_START}}';
	const SHIPPER_JSON_END   = '{{SHIPPER_JSON_END}}';

	/**
	 * Shipper_Helper_Replacer_JSON constructor.
	 *
	 * @param bool $direction Encode or decode.
	 */
	public function __construct( $direction = false ) {}

	/**
	 * Check whether it's json or not
	 *
	 * @return bool
	 */
	private function is_json() {
		return false !== strpos( $this->string, self::SHIPPER_JSON_START );
	}

	/**
	 * Get json starting position
	 *
	 * @return false|int
	 */
	private function get_json_start_pos() {
		return strpos( $this->string, self::SHIPPER_JSON_START );
	}

	/**
	 * Get json ending position
	 *
	 * @return false|int
	 */
	private function get_json_end_pos() {
		return strpos( $this->string, self::SHIPPER_JSON_END );
	}

	/**
	 * Get JSON string
	 *
	 * @return false|string
	 */
	private function get_json() {
		if ( ! $this->is_json() ) {
			return false;
		}

		$pos                = $this->get_json_start_pos() + strlen( self::SHIPPER_JSON_START );
		$without_first_part = substr( $this->string, $pos );
		$json               = strstr( $without_first_part, self::SHIPPER_JSON_END, true );

		return stripslashes( $json );
	}

	/**
	 * Get JSON last part
	 *
	 * @param bool $without_const Whether to return the string without the CONST or NOT.
	 *
	 * @return false|string
	 */
	private function get_json_last_part( $without_const = true ) {
		$pos = $without_const
			? $this->get_json_end_pos() + strlen( self::SHIPPER_JSON_END )
			: $this->get_json_end_pos();

		return substr( $this->string, $pos );
	}

	/**
	 * Get first part of the JSON string
	 *
	 * @param bool $without_const Whether to return the string without the CONST or NOT.
	 *
	 * @return false|string
	 */
	private function get_json_first_part( $without_const = true ) {
		$pos = $without_const
			? $this->get_json_start_pos()
			: $this->get_json_start_pos() + strlen( self::SHIPPER_JSON_START );

		return substr( $this->string, 0, $pos );
	}

	/**
	 * Get serialized string
	 *
	 * @return string
	 */
	private function get_serialize() {
		return addslashes( serialize( json_decode( $this->get_json(), true ) ) ); // phpcs:ignore
	}

	/**
	 * Transform the JSON string
	 *
	 * @param string $string the string to be transformed.
	 *
	 * @return string
	 */
	public function transform( $string ) {
		$this->string = $string;

		if ( ! $this->get_json() ) {
			return $this->string;
		}

		return $this->get_json_first_part() . $this->get_serialize() . $this->get_json_last_part();
	}
}