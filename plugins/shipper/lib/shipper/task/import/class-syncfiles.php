<?php
/**
 * Shipper tasks: import, non-config files copier
 *
 * This task iterates over all extracted files and classifies them to
 * config/non-config ones. The non-config files will be copied to their
 * respective new locations, while the config ones will be recorded for
 * processing in subsequent steps.
 *
 * @package shipper
 */

/**
 * Files copying class
 */
class Shipper_Task_Import_Syncfiles extends Shipper_Task_Import {
	const HAS_INIT = 'sync_is_init', WHITELIST = 'sync_whitelist', FILES_NEED_CLEANUP = 'sync_files_need_cleanup';

	/**
	 * Gets import task label
	 *
	 * @return string
	 */
	public function get_work_description() {
		return __( 'Syncing files', 'shipper' );
	}

	/**
	 * Task runner method
	 *
	 * Returns (bool)true on completion.
	 *
	 * @param array $args Not used.
	 *
	 * @return bool
	 */
	public function apply( $args = array() ) {
		/**
		 * Sync files inside wp-content folder only.
		 */
		$files = $this->get_files_need_deleted();
		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				@unlink( $file );
				$tmp = dirname( $file );
				// If the folder contains the file is empty, clear it too.
				if ( $this->dir_is_empty( $tmp ) ) {
					@rmdir( $tmp );
				}
			} elseif ( is_dir( $file ) && $this->dir_is_empty( $file ) ) {
				@rmdir( $file );
			}
		}
		// we will need to check if the /uploads/sites is empty, if yes, remove.
		$sites_path = WP_CONTENT_DIR . '/uploads/sites';
		if ( $this->dir_is_empty( $sites_path ) ) {
			@rmdir( $sites_path );
		}
		Shipper_Helper_Log::debug( 'sync complete' );

		return true;
	}

	/**
	 * Check if dir is empty
	 *
	 * @param string $dir directory name.
	 *
	 * @return bool
	 */
	private function dir_is_empty( $dir ) {
		if ( glob( $dir . '/*' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get content files
	 *
	 * @return array
	 */
	private function get_content_files() {
		$fs_dirstruct  = new Shipper_Helper_Fs_DirStructure(
			WP_CONTENT_DIR,
			true,
			true,
			array(),
			array(
				// whitelist those first.
				'dir' => array(
					Shipper_Helper_Fs_Path::get_working_dir(),
					Shipper_Helper_Fs_Path::get_log_dir(),
					WP_CONTENT_DIR . '/plugins/shipper',
					WP_CONTENT_DIR . '/plugins/wpmudev-updates',
				),
			),
			true,
			true
		);
		$content_files = $fs_dirstruct->get_dir_tree();

		return $content_files;
	}

	/**
	 * Get files and check if it's need to be deleted
	 *
	 * @return array
	 */
	private function get_files_need_deleted() {
		$sources = $this->get_source_files();
		$w_files = array();
		$w_dirs  = array(
			untrailingslashit( Shipper_Helper_Fs_Path::get_working_dir() ),
			untrailingslashit( Shipper_Helper_Fs_Path::get_log_dir() ),
			WP_CONTENT_DIR . '/plugins/shipper',
			WP_CONTENT_DIR . '/plugins/wpmudev-updates',
			WP_CONTENT_DIR . '/themes/' . get_option( 'template' ),
		);
		foreach ( $sources as $file ) {
			if ( is_dir( $file ) ) {
				$w_dirs[] = $file;
			} elseif ( is_file( $file ) ) {
				$w_files[] = $file;
			}
		}
		$w_dirs  = array_filter( $w_dirs );
		$w_files = array_filter( $w_files );

		$exclude = array(
			'dir'  => array_unique( $w_dirs ),
			'path' => array_unique( $w_files ),
		);
		$tree    = new Shipper_Helper_Fs_DirStructure( WP_CONTENT_DIR, true, true, array(), $exclude, true, true );
		$dirs    = $tree->get_dir_tree();
		$dirs    = array_filter( $dirs );
		rsort( $dirs );

		return $dirs;
	}

	/**
	 * Get source files.
	 *
	 * @return array
	 */
	private function get_source_files() {
		$fileslist = new Shipper_Model_Dumped_Filelist();
		$result    = array();
		foreach ( $fileslist->get_statements( 0, false, PHP_INT_MAX ) as $file ) {
			if ( strpos( $file['destination'], 'files/wp-content/' ) === 0 ) {
				/**
				 * There may have `files` directory inside a theme or plugin. So replace the first `files` occurrence only.
				 *
				 * @see https://incsub.atlassian.net/browse/SHI-157
				 */
				$result[] = preg_replace( '/files/', ABSPATH, $file['destination'], 1 );
			}
		}

		return $result;
	}
}