<?php
/**
 * A helper filter out blacklisted files
 *
 * @package shipper
 * @since 1.2.2
 */

/**
 * Class Shipper_Helper_Iterator_Filter_Blacklist
 */
class Shipper_Helper_Iterator_Filter_Blacklist extends Shipper_Helper_Iterator_Filter {

	/**
	 * Blacklist model instance holder
	 *
	 * @var Shipper_Model_Stored_Exclusions
	 */
	private static $blacklist;

	/**
	 * Files holder
	 *
	 * @var array
	 */
	private static $files;

	/**
	 * File extensions holder
	 *
	 * @var array
	 */
	private static $extensions;

	/**
	 * Shipper_Helper_Iterator_Filter_Blacklist constructor.
	 *
	 * @param RecursiveIterator               $iterator An Iterator.
	 * @param Shipper_Model_Stored_Exclusions $blacklist Blacklisted files and dirs.
	 */
	public function __construct( RecursiveIterator $iterator, Shipper_Model_Stored_Exclusions $blacklist ) {
		parent::__construct( $iterator );

		if ( empty( static::$blacklist ) ) {
			static::$blacklist = $blacklist;
		}

		$this->set_files();
		$this->set_extensions();
	}

	/**
	 * Set files and dirs
	 *
	 * @return void
	 */
	private function set_files() {
		if ( empty( static::$files ) ) {
			static::$files = $this->un_trial_slash( array_keys( static::$blacklist->get_data() ) );
		}
	}

	/**
	 * Set file extensions
	 *
	 * @return void
	 */
	private function set_extensions() {
		if ( empty( static::$extensions ) ) {
			static::$extensions = shipper_get_file_extensions( static::$files, true );
		}
	}

	/**
	 * Accept the file we want to add to zip
	 *
	 * @return bool
	 */
	public function accept() {
		$file = parent::current();

		if ( $file->isLink() ) {
			return false;
		}

		if ( in_array( $file->getPathname(), static::$files, true ) ) {
			return false;
		}

		if ( ! $file->isDir() && static::$extensions && in_array( $file->getExtension(), static::$extensions, true ) ) {
			return false;
		}

		if ( apply_filters( 'shipper_path_exclude_file', false, $file->getPathname() ) ) {
			// Using this hook to filter out files, will slow down the overall migration process.
			// Instead check if you can make use of `Shipper_Model_Stored_Exclusions` class.
			return false;
		}

		return true;
	}

	/**
	 * Get children
	 *
	 * @return RecursiveFilterIterator|Shipper_Helper_Iterator_Filter_Blacklist
	 */
	public function getChildren() {
		return new static( $this->getInnerIterator()->getChildren(), static::$blacklist );
	}
}