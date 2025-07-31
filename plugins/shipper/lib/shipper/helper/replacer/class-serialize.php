<?php
/**
 * Shipper helpers: Serialized Serialize Replacer
 *
 * Handles low level serialized values replacement transformations.
 *
 * @package shipper
 * @since 1.2.4
 */

/**
 * Class Shipper_Helper_Replacer_Serialize
 */
class Shipper_Helper_Replacer_Serialize extends Shipper_Helper_Replacer {

	/**
	 * String holder
	 *
	 * @var string
	 */
	private $string;

	const SHIPPER_SERIALIZE_START = '{{SHIPPER_SERIALIZE_START}}';
	const SHIPPER_SERIALIZE_END   = '{{SHIPPER_SERIALIZE_END}}';

	/**
	 * Shipper_Helper_Replacer_Serialize constructor.
	 *
	 * @param bool $direction Encode or decode.
	 */
	public function __construct( $direction = false ) {}

	/**
	 * Check whether it's serialized or not
	 *
	 * @return bool
	 */
	private function is_serialized() {
		return false !== strpos( $this->string, self::SHIPPER_SERIALIZE_START );
	}

	/**
	 * Get serialized starting position
	 *
	 * @return false|int
	 */
	private function get_serialized_start_pos() {
		return strpos( $this->string, self::SHIPPER_SERIALIZE_START );
	}

	/**
	 * Get serialized ending position
	 *
	 * @return false|int
	 */
	private function get_serialized_end_pos() {
		return strpos( $this->string, self::SHIPPER_SERIALIZE_END );
	}

	/**
	 * Get Serialize string
	 *
	 * @return false|string
	 */
	private function get_serialized() {
		if ( ! $this->is_serialized() ) {
			return false;
		}

		$pos                = $this->get_serialized_start_pos() + strlen( self::SHIPPER_SERIALIZE_START );
		$without_first_part = substr( $this->string, $pos );
		$serialized         = strstr( $without_first_part, self::SHIPPER_SERIALIZE_END, true );

		return stripslashes( $serialized );
	}

	/**
	 * Get Serialize last part
	 *
	 * @param bool $without_const Whether to return the string without the CONST or NOT.
	 *
	 * @return false|string
	 */
	private function get_serialized_last_part( $without_const = true ) {
		$pos = $without_const
			? $this->get_serialized_end_pos() + strlen( self::SHIPPER_SERIALIZE_END )
			: $this->get_serialized_end_pos();

		return substr( $this->string, $pos );
	}

	/**
	 * Get first part of the Serialize string
	 *
	 * @param bool $without_const Whether to return the string without the CONST or NOT.
	 *
	 * @return false|string
	 */
	private function get_serialized_first_part( $without_const = true ) {
		$pos = $without_const
			? $this->get_serialized_start_pos()
			: $this->get_serialized_start_pos() + strlen( self::SHIPPER_SERIALIZE_START );

		return substr( $this->string, 0, $pos );
	}

	/**
	 * Get serialized string
	 *
	 * @return string
	 */
	private function get_decoded_serialized() {
		$rep = new Shipper_Helper_Replacer_String( Shipper_Helper_Codec::DECODE );
		$rep->set_codec_list(
			array(
				new Shipper_Helper_Codec_Define(),
				new Shipper_Helper_Codec_Var(),
				new Shipper_Helper_Codec_Domain(),
				new Shipper_Helper_Codec_Preoptionname(),
			)
		);

		return addslashes( $rep->transform( $this->get_serialized() ) );
	}

	/**
	 * Transform the Serialize string
	 *
	 * @param string $string the string to be transformed.
	 *
	 * @return string
	 */
	public function transform( $string ) {
		$this->string = $string;

		if ( ! $this->get_serialized() ) {
			return $this->string;
		}

		return $this->get_serialized_first_part() . $this->get_decoded_serialized() . $this->get_serialized_last_part();
	}
}