<?php
/**
 * A helper class to iterate over directories recursively
 *
 * @since 1.2.2
 *
 * @package shipper
 */

/**
 * Class Shipper_Helper_Fs_Iterator_Directory
 */
class Shipper_Helper_Fs_Package_Filelist {
	/**
	 * Root path
	 *
	 * @var string
	 */
	private $root_path;

	/**
	 * Iterator holder
	 *
	 * @var Generator
	 */
	private $iterator;

	/**
	 * File list path holder
	 *
	 * @var string
	 */
	private $storage;

	/**
	 * Shipper_Helper_Fs_Filelist constructor.
	 *
	 * @param Shipper_Model_Stored_Filelist $storage storage model.
	 * @param null                          $root_path root file path.
	 */
	public function __construct( Shipper_Model_Stored_Filelist $storage, $root_path = null ) {
		$this->storage = $storage;
		$this->set_root_path( $root_path );
		$this->set_iterator();
		$this->clear_cached_iterators();
	}

	/**
	 * Set root path
	 *
	 * @param string $root_path root file path.
	 */
	private function set_root_path( $root_path ) {
		if ( empty( $root_path ) ) {
			$root_path = ABSPATH;

			if ( Shipper_Model_Env::is_flywheel() ) {
				// Flywheel has separate dirs.
				$root_path = WP_CONTENT_DIR;
			}
		}

		$this->root_path = wp_normalize_path( $root_path );
	}

	/**
	 * Set iterators
	 *
	 * @return void
	 */
	private function set_iterator() {
		$blacklisted_files = new Shipper_Model_Stored_Exclusions();
		$directory         = new RecursiveDirectoryIterator( $this->root_path, FilesystemIterator::SKIP_DOTS );
		$iterator          = new Shipper_Helper_Iterator_Filter_Blacklist( $directory, $blacklisted_files );
		$iterator          = new Shipper_Helper_Iterator_Filter_Wildcard( $iterator, $blacklisted_files );
		$iterator          = new Shipper_Helper_Iterator_Filter_Media( $iterator );
		$iterator          = new Shipper_Helper_Iterator_Filter_Plugins( $iterator );
		$this->iterator    = new RecursiveIteratorIterator( $iterator, RecursiveIteratorIterator::LEAVES_ONLY, RecursiveIteratorIterator::CATCH_GET_CHILD );
	}

	/**
	 * Get all the iterators
	 *
	 * @return Generator
	 */
	public function get_files() {
		foreach ( $this->get_iterator() as $iterator ) {
			yield array(
				$iterator->getPathname(),
				$this->get_destination( $iterator->getPathname() ),
				$iterator->getSize(),
			);
		}
	}

	/**
	 * Get the iterator object
	 *
	 * @return mixed
	 */
	private function get_iterator() {
		return $this->iterator;
	}

	/**
	 * Get destination path from source
	 *
	 * @param string $source source file.
	 *
	 * @return string
	 */
	private function get_destination( $source ) {
		$abspath = ABSPATH;

		if ( shipper_is_windows() ) {
			$abspath = wp_normalize_path( $abspath );
			$source  = wp_normalize_path( $source );
		}

		$destination = explode( $abspath, $source );
		$destination = ! empty( $destination[1] ) ? $destination[1] : '';

		return 'files/' . wp_normalize_path( $destination );
	}

	/**
	 * Write files path to a file
	 *
	 * @return void
	 */
	public function create() {
		$fs = Shipper_Helper_Fs_File::open( $this->storage->get_path(), 'w' );

		foreach ( $this->get_files() as $file ) {
			list( $src, $dest ) = $file;
			$fs->fwrite( $src . $this->storage->get_separator() . $dest . PHP_EOL );
		}
	}

	/**
	 * Clear cached iterators
	 *
	 * @since 1.2.4
	 */
	private function clear_cached_iterators() {
		( new Shipper_Model_Stored_Iterators() )->clear()->save();
	}
}