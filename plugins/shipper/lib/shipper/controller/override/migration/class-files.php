<?php
/**
 * Shipper api migration controllers: migration files overrides.
 *
 * @since 1.1.4
 * @package shipper
 */

/**
 * Migration file overrides implementation class
 */
class Shipper_Controller_Override_Migration_Files extends Shipper_Controller_Override_Migration {

	/**
	 * Get scope
	 *
	 * @since 1.1.4
	 *
	 * @return string
	 */
	public function get_scope() {
		return Shipper_Model_Stored_MigrationMeta::KEY_EXCLUSIONS_FS;
	}

	/**
	 * Apply the overrides.
	 *
	 * @since 1.1.4
	 *
	 * @return bool|null
	 */
	public function apply_overrides() {
		if ( $this->get_model()->is_extract_mode() ) {
			add_filter( 'shipper_path_include_file', array( $this, 'exclude_files_not_belong_to_site' ), 11, 2 );
		}

		$exclusions = $this->get_exclusions();

		if ( empty( $exclusions ) ) {
			return false;
		}

		add_filter( 'shipper_path_include_file', array( $this, 'maybe_include' ), 10, 2 );

		foreach ( $exclusions as $item ) {
			if ( false !== strpos( WP_CONTENT_DIR . '/uploads/', $item ) ) {
				add_filter( 'shipper_export_table_include_row', array( $this, 'exclude_media' ), 10, 3 );
				break;
			}
		}
	}

	/**
	 * We have to exclude all files in uploads doesn't belong to the subsite picked
	 *
	 * @param bool   $include whether to include or not.
	 * @param string $path path of the dirs.
	 *
	 * @return bool
	 */
	public function exclude_files_not_belong_to_site( $include, $path ) {
		$site_id = (int) $this->get_model()->get_site_id();
		if ( 1 === $site_id ) {
			// exclude everthings inside uploads/sites.
			if ( strpos( $path, WP_CONTENT_DIR . '/uploads/sites/' ) === 0 ) {
				return false;
			}
		} else {
			if (
				strpos( $path, WP_CONTENT_DIR . '/uploads/sites/' ) === 0
				&& strpos( $path, WP_CONTENT_DIR . '/uploads/sites/' . $site_id ) === false
			) {
				// match the sites folder but not match the actual folder for this site.
				return false;
			}
		}

		if ( strpos( $path, WP_CONTENT_DIR . '/plugins/' ) === 0 ) {
			$site_info = Shipper_Helper_MS::get_site_info( $site_id );
			$match     = 0;
			foreach ( $site_info['plugins'] as $plugin ) {
				$plugin_dir = dirname( WP_CONTENT_DIR . '/plugins/' . $plugin );
				if ( strpos( $path, $plugin_dir ) === 0 ) {
					$match ++;
					break;
				}
			}
			if ( $match ) {
				Shipper_Helper_Log::debug( $path );

				return $include;
			}

			return false;
		}

		return $include;
	}

	/**
	 * Excludes files according to package settings
	 *
	 * @param bool   $include Whether to include a path.
	 * @param string $path Path to check.
	 *
	 * @return bool
	 */
	public function maybe_include( $include, $path ) {
		if ( empty( $include ) ) {
			return $include;
		}

		$exclusions = array_filter( $this->get_exclusions() );

		if ( empty( $exclusions ) ) {
			return $include;
		}

		$active_theme = wp_get_theme();
		$theme_roots  = get_theme_roots();

		if ( is_array( $theme_roots ) ) {
			$theme_roots = $theme_roots[0];
		}

		$theme_path = WP_CONTENT_DIR . $theme_roots;

		if ( $theme_path === $path && stristr( $active_theme->get_template_directory(), $path ) ) {
			// for the themes folder.
			return true;
		}

		if ( strpos( $path, $active_theme->get_template_directory() ) !== false ) {
			// do nothing on this, for themes items.
			return $include;
		}

		$result = false;
		foreach ( $exclusions as $exclusion ) {
			$result = (bool) stristr( $path, $exclusion );

			if ( ! empty( $result ) ) {
				break;
			}
		}

		return ! $result;
	}

	/**
	 * Exclude media file if as needed
	 *
	 * @since 1.1.4
	 *
	 * @param bool   $include true or false.
	 * @param array  $row     the row of the table.
	 * @param string $table   the table name.
	 *
	 * @return bool
	 */
	public function exclude_media( $include, $row, $table ) {
		if ( empty( $include ) ) {
			return $include;
		}

		$is_post_row = false !== strpos( $table, 'posts' ) && shipper_array_keys_exist( array( 'ID', 'post_type' ), $row );

		if ( ! $is_post_row ) {
			return $include;
		}

		return 'attachment' !== $row['post_type'];
	}
}