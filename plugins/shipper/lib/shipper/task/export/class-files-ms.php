<?php
/**
 * Author: Hoang Ngo
 *
 * @package shipper
 */

/**
 * Class Shipper_Task_Export_Files_MS
 */
class Shipper_Task_Export_Files_MS extends Shipper_Task_Export_Files {
	/**
	 * Files array
	 *
	 * @var array
	 */
	protected $files;

	/**
	 * Apply the task
	 *
	 * @param array $args task arguments.
	 *
	 * @return bool
	 */
	public function apply( $args = array() ) {
		$migration   = new Shipper_Model_Stored_Migration();
		$storage     = new Shipper_Model_Stored_Filelist();
		$this->files = new Shipper_Helper_Fs_List( $storage );
		$dumped      = new Shipper_Model_Dumped_Filelist();
		$large       = new Shipper_Model_Dumped_Largelist();

		if ( $this->files->is_done() ) {
			return true;
		}

		// Update status flag first.
		$this->has_done_anything = true;

		$exclusions = new Shipper_Model_Fs_Blacklist();
		$exclusions->add_directory(
			Shipper_Helper_Fs_Path::get_working_dir()
		);
		$exclusions->add_directory(
			Shipper_Helper_Fs_Path::get_log_dir()
		);

		/**
		 * We need to check if this is is subsite, then we only move files belong to that sub site (folder, themes)...
		 */

		foreach ( $this->files->get_files() as $item ) {
			if ( empty( $item['path'] ) ) {
				continue;
			}

			$source = $this->get_source_path( $item['path'], $migration );
			if ( empty( $source ) ) {
				continue;
			}

			$destination = $this->get_destination_path( $item['path'] );

			if ( ! is_readable( $source ) ) {
				$this->add_error(
					self::ERR_ACCESS,
					/* translators: %s: file path. */
					sprintf( __( 'Shipper couldn\'t read file: %s', 'shipper' ), $source )
				);
			}

			if ( $exclusions->is_excluded( $item['path'] ) ) {
				Shipper_Helper_Log::debug(
					sprintf(
						/* translators: %s: file path. */
						__( 'Skipping excluded item: %s', 'shipper' ),
						$item['path']
					)
				);
				continue;
			}

			$target_line = array(
				'source'      => $source,
				'destination' => $destination,
				'size'        => $item['size'],
			);
			if ( $item['size'] > Shipper_Model_Stored_Migration::get_file_size_threshold() ) {
				$large->add_statement( $target_line );
			} else {
				$dumped->add_statement( $target_line );
			}
		}

		$dumped->close();
		$large->close();

		return $this->files->is_done();
	}
}