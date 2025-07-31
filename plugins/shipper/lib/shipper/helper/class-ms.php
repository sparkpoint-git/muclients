<?php
/**
 * Author: Hoang Ngo
 *
 * @package shipper
 */

/**
 * Class Shipper_Helper_MS
 */
class Shipper_Helper_MS {

	/**
	 * Get all sites.
	 *
	 * @param array $args An array of arguments.
	 *
	 * @return array|int List of WP_Site objects, a list of site IDs when 'fields' is set to 'ids'.
	 */
	public static function get_all_sites( $args = array() ) {
		$defaults = array(
			'number' => 10,
		);

		if ( ! empty( $args['search'] ) ) {
			$args['search']     = trim( $args['search'] );
			$defaults['number'] = 100;

			add_filter(
				'site_search_columns',
				function() {
					return array(
						'domain',
						'path',
						'blog_id',
					);
				}
			);
		}

		$args = apply_filters(
			'shipper_get_all_sites_args',
			wp_parse_args( $defaults, $args )
		);

		return get_sites( $args );
	}

	/**
	 * Check if we can show the subsite import
	 *
	 * @return bool
	 */
	public static function can_ms_subsite_import() {
		/**
		 * We only import a subsite if current is single site.
		 */
		if ( is_multisite() ) {
			return false;
		}

		$task = new Shipper_Task_Api_Info_Get();
		// get the domain, since we mostly use this when open the modal network.
		$destination = new Shipper_Model_Stored_Destinations();
		$args        = array();
		$get         = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- already checked

		if ( isset( $get['site'] ) ) {
			$site_id        = intval( $get['site'] );
			$info           = $destination->get_by_site_id( $site_id );
			$args['domain'] = $info['domain'];
		}
		$data = $task->apply( $args );

		if ( isset( $data['wordpress'] ) && ! $data['wordpress'][ Shipper_Model_System_Wp::MULTISITE ] ) {
			// destination is not multisite.
			return false;
		}

		return true;
	}

	/**
	 * Uploading subsite info
	 *
	 * @return bool
	 */
	public static function transmit_subsite_id() {
		$remote = new Shipper_Helper_Fs_Remote();
		$meta   = new Shipper_Model_Stored_MigrationMeta();
		$path   = Shipper_Helper_Fs_Path::get_temp_dir() . 'subsite';

		@unlink( $path ); // phpcs:ignore

		$fs = Shipper_Helper_Fs_File::open( $path, 'w' );

		if ( ! $fs ) {
			return false;
		}

		$fs->fwrite( $meta->get_site_id() );

		Shipper_Helper_Log::write( 'site id ' . $meta->get_site_id() );

		try {
			$remote->upload( $path, 'files/subsite' );
		} catch ( Exception $e ) {
			Shipper_Helper_Log::write(
				sprintf(
					/* translators: %1$s %2$s: file path and message. */
					__( 'Unable to upload %1$s: %2$s', 'shipper' ),
					$path,
					$e->getMessage()
				)
			);

			return false;
		}
	}

	/**
	 * Gather infos of a subsite, like plugins using, themes etc
	 *
	 * @param int $site_id id of a site.
	 *
	 * @return array
	 */
	public static function get_site_info( $site_id ) {
		global $wpdb;

		$ret     = array();
		$site_id = intval( $site_id );
		$prefix  = ( $site_id && 1 !== $site_id )
			? $wpdb->base_prefix . $site_id . '_'
			: $wpdb->base_prefix;

		// phpcs:disable
		$network_wide = get_site_option( 'active_sitewide_plugins', array() );
		$network_wide = is_array( $network_wide ) ? $network_wide : array();
		$site_wide    = $wpdb->get_var( "SELECT option_value FROM {$prefix}options WHERE option_name='active_plugins'" );
		$site_wide    = maybe_unserialize( $site_wide );

		if ( ! is_array( $site_wide ) ) {
			$site_wide = array();
		}

		$network_wide   = array_merge( array_keys( $network_wide ), $site_wide );
		$ret['plugins'] = array_values( array_unique( $network_wide ) );

		$theme             = $wpdb->get_var( "SELECT option_value FROM {$prefix}options WHERE option_name='stylesheet'" );
		$ret['stylesheet'] = $theme;
		$template          = $wpdb->get_var( "SELECT option_value FROM {$prefix}options WHERE option_name='template'" );
		$ret['template']   = $template;

		$sql          = "SELECT meta.meta_value FROM `{$prefix}posts` posts, `{$prefix}postmeta` meta WHERE posts.ID = meta.post_id AND meta.meta_key='_wp_attached_file'";
		$media_files  = serialize( $wpdb->get_col( $sql ) );
		$ret['media'] = $media_files;
		// phpcs:enable

		return $ret;
	}
}