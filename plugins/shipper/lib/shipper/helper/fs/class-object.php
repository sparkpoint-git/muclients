<?php
/**
 * A helper class to read and write to files
 *
 * @package shipper
 */

/**
 * Class Shipper_Helper_Fs_Object
 *
 * @extends SplFileObject
 */
class Shipper_Helper_Fs_Object extends SplFileObject {

	/**
	 * File read.
	 *
	 * @param int $length number of bytes.
	 *
	 * @return false|string
	 */
	public function fread( $length ) {
		if ( $length < 1 ) {
			return false;
		}

		return parent::fread( $length );
	}

	/**
	 * Write to file.
	 *
	 * @param string $str string to write.
	 * @param null   $length number of bytes.
	 *
	 * @return false|int
	 */
	public function fwrite( $str, $length = null ) {
		try {
			if ( $length ) {
				return parent::fwrite( $str, $length );
			}
			return parent::fwrite( $str );
		} catch ( Exception $e ) {
			return false;
		}
	}
}