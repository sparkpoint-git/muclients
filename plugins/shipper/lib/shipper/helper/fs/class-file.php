<?php
/**
 * A helper class to read and write to files
 *
 * @package shipper
 */

/**
 * Class Shipper_Helper_Fs_File
 */
class Shipper_Helper_Fs_File {

	/**
	 * Open the file.
	 *
	 * @param string $file_name file name.
	 * @param string $open_mode file open mode.
	 * @param false  $use_include_path use include path.
	 * @param null   $context context.
	 *
	 * @return SplFileObject|false on failure.
	 */
	public static function open( $file_name, $open_mode = 'r', $use_include_path = false, $context = null ) {
		try {
			return new Shipper_Helper_Fs_Object( $file_name, $open_mode, $use_include_path, $context );
		} catch ( Exception $e ) {
			return false;
		}
	}
}