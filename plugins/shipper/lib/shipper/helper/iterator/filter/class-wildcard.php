<?php
/**
 * A helper filter out blacklisted files
 *
 * @package shipper
 * @since 1.2.2
 */

/**
 * Class Shipper_Helper_Iterator_Filter_Wildcard
 *
 * @since 1.2.6
 */
class Shipper_Helper_Iterator_Filter_Wildcard extends Shipper_Helper_Iterator_Filter {
	const FILE_DELIMITER       = '*.';
	const FILES_DELIMITER      = '*.*';
	const IGNORE_DIR_DELIMITER = '*/*/*.*';

	/**
	 * Will exclude all files with any extension in the directory
	 * Like `wp-content/*.*` all the files excluding any dir in the `wp-content` folder.
	 */
	const WILDCARD_REGEX_FILES = '*.*';

	/**
	 * Will exclude all files with the TXT extension at the end
	 * Like `wp-content/*.txt`
	 */
	const WILDCARD_REGEX_FILE = '/\*\.[\w]+$/';

	/**
	 * Will exclude all files inside any directory, matched with the dir name.
	 *
	 * Regex pattern for a word starts with * then end withs /*.*
	 */
	const WILDCARD_REGEX_IGNORE_DIR = '/\*\/([\w-]+)\/\*\.\*$/';

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
	 * File ignore list holder
	 *
	 * @var array
	 */
	private static $ignore_list;

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
		$this->set_ignore_list();
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
	 * Set the file ignore list
	 *
	 * @return void
	 */
	private function set_ignore_list() {
		if ( empty( static::$ignore_list ) ) {
			static::$ignore_list = $this->get_ignore_list();
		}
	}

	/**
	 * Get an array of file path to ignore with it's extension as a key
	 *
	 * @return array
	 */
	private function get_ignore_list() {
		$ignore_list = array();

		foreach ( static::$files as $file ) {
			if ( preg_match( self::WILDCARD_REGEX_IGNORE_DIR, $file, $matches ) ) {
				$ignore_list[][ self::IGNORE_DIR_DELIMITER ] = $matches[1];
				continue;
			}

			if ( false !== strpos( $file, self::WILDCARD_REGEX_FILES ) ) {
				$ignore_list[][ self::FILES_DELIMITER ] = untrailingslashit( strstr( $file, self::WILDCARD_REGEX_FILES, true ) );
				continue;
			}

			if ( preg_match( self::WILDCARD_REGEX_FILE, $file, $match ) ) {
				$ignore_list[][ $match[0] ] = untrailingslashit( strstr( $file, $match[0], true ) );
			}
		}

		return $ignore_list;
	}

	/**
	 * Accept the file we want to add to zip
	 *
	 * @return bool
	 */
	public function accept() {
		if ( empty( static::$ignore_list ) ) {
			return true;
		}

		$file = parent::current();

		if ( $file->isLink() ) {
			return false;
		}

		foreach ( static::$ignore_list as $ignore ) {
			if ( $this->matched_wildcard_all_files( $file, $ignore ) ) {
				return false;
			}

			if ( $this->matched_wildcard_specific_file( $file, $ignore ) ) {
				return false;
			}

			if ( $this->matched_wildcard_ignore_dir( $file, $ignore ) ) {
				return false;
			}
		}

		if ( apply_filters( 'shipper_path_exclude_file', false, $file->getPathname() ) ) {
			// Using this hook to filter out files, will slow down the overall migration process.
			// Instead check if you can make use of `Shipper_Model_Stored_Exclusions` class.
			return false;
		}

		return true;
	}

	/**
	 * Check whether the wildcard matched or not
	 *
	 * @param SplFileInfo $file current file object.
	 * @param array       $ignore An array with file path and `*.*`.
	 *
	 * @return bool
	 */
	private function matched_wildcard_all_files( $file, $ignore ) {
		return ! $file->isDir()
			&& in_array( $file->getPath(), $ignore, true )
			&& isset( $ignore[ self::FILES_DELIMITER ] );
	}

	/**
	 * Check whether its wildcard match for a specific file
	 *
	 * @param SplFileInfo $file current file object.
	 * @param array       $ignore An array with file path and extension.
	 *
	 * @return bool
	 */
	private function matched_wildcard_specific_file( $file, $ignore ) {
		return ! $file->isDir()
			&& in_array( $file->getPath(), $ignore, true )
			&& ! isset( $ignore[ self::FILES_DELIMITER ] )
			&& self::FILE_DELIMITER . $file->getExtension() === key( $ignore );
	}

	/**
	 * Check whether its wildcard match for a specific file
	 *
	 * @param SplFileInfo $file current file object.
	 * @param array       $ignore An array with file path and extension.
	 *
	 * @return bool
	 */
	private function matched_wildcard_ignore_dir( $file, $ignore ) {
		return ! $file->isDir()
			&& isset( $ignore[ self::IGNORE_DIR_DELIMITER ] )
			&& false !== strpos( $file->getPathname(), $ignore[ self::IGNORE_DIR_DELIMITER ] );
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