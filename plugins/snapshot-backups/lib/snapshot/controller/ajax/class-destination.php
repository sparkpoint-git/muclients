<?php // phpcs:ignore
/**
 * Snapshot controllers: Destination AJAX controller class
 *
 * @package snapshot
 */

namespace WPMUDEV\Snapshot4\Controller\Ajax;

use WPMUDEV\Snapshot4\Controller;
use WPMUDEV\Snapshot4\Task;
use WPMUDEV\Snapshot4\Model;
use WPMUDEV\Snapshot4\Helper;
use WPMUDEV\Snapshot4\Helper\Datetime;
use WPMUDEV\Snapshot4\Helper\Log;

/**
 * Destination AJAX controller class
 */
/**
 * Destination AJAX controller class.
 *
 * Boots the controller and sets up event listeners.
 * Handles AJAX requests for managing destinations.
 * Provides methods for getting, deleting, updating, and activating destinations.
 * Also provides a method for removing the Google Drive reconnect notice.
 */
class Destination extends Controller\Ajax {

	/**
	 * List of third party destination that needs reauthentication.
	 *
	 * @var array
	 */
	protected $providers = array(
		'google_drive',
		'onedrive',
		'dropbox',
	);

	/**
	 * Boots the controller and sets up event listeners.
	 */
	public function boot() {
		if ( ! is_admin() ) {
			return false;
		}

		// Request the service actions regarding destinations.
		add_action( 'wp_ajax_snapshot-get_destinations', array( $this, 'json_get_destinations' ) );
		add_action( 'wp_ajax_snapshot-delete_destination', array( $this, 'json_delete_destination' ) );
		add_action( 'wp_ajax_snapshot-update_destination', array( $this, 'json_update_destination' ) );
		add_action( 'wp_ajax_snapshot-activate_destination', array( $this, 'json_activate_destination' ) );
		add_action( 'wp_ajax_snapshot-remove_destination_nag', array( $this, 'json_remove_destination_nag' ) );
	}

	/**
	 * Handles requesting the service for a destination list.
	 */
	public function json_get_destinations() {
		$this->do_request_sanity_check( 'snapshot_get_destinations', self::TYPE_GET );

		$is_destination_page = isset( $_GET['destination_page'] ) ? intval( $_GET['destination_page'] ) : 0; // phpcs:ignore

		$data = array(
			'tpd_action' => 'get_destinations',
		);

		$task = new Task\Request\Destination( $data['tpd_action'] );

		$validated_data = $task->validate_request_data( $data );
		if ( is_wp_error( $validated_data ) ) {
			wp_send_json_error( $validated_data );
		}

		$args                  = $validated_data;
		$args['request_model'] = new Model\Request\Destination();
		$result                = $task->apply( $args );

		if ( $task->has_errors() ) {
			foreach ( $task->get_errors() as $error ) {
				Log::error( $error->get_error_message() );
			}
			wp_send_json_error();
		}

		if ( $is_destination_page ) {
			$template = new Helper\Template();

			foreach ( $result as $key => $item ) {
				$item['is_suspended'] = false;
				$item['tpd_type']     = isset( $item['tpd_type'] ) ? $item['tpd_type'] : null;

				switch ( $item['tpd_type'] ) {
					case 'backblaze':
						$item['view_url'] = 'https://secure.backblaze.com/b2_buckets.htm';
						break;
					case 'digitalocean':
						$full_path        = explode( '/', $item['tpd_path'], 2 );
						$bucket           = $full_path[0];
						$item['view_url'] = 'https://cloud.digitalocean.com/spaces/' . $bucket . '/';
						break;
					case 'googlecloud':
						$item['view_url'] = 'https://console.cloud.google.com/storage/browser';
						break;
					case 'wasabi':
						$item['view_url'] = 'https://console.wasabisys.com';
						break;
					case 'gdrive':
						$item['view_url'] = 'https://drive.google.com/drive/u/1/folders/' . $item['tpd_path'] . '/';
						break;
					case 'dropbox':
						$dropbox_view_url = SNAPSHOT_DROPBOX_VIEW_URL;
						$subdir_path      = ltrim( strval( $item['tpd_path'] ), '/' );

						if ( SNAPSHOT_DROPBOX_VIEW_URL !== SNAPSHOT_DROPBOX_VIEW_BASE_URL && '' !== $subdir_path ) {
							$dropbox_view_url = trailingslashit( $dropbox_view_url ) .
								implode( '/', array_map( 'rawurlencode', explode( '/', $subdir_path ) ) );
						}

						$item['view_url']     = $dropbox_view_url;
						$item['is_suspended'] = Datetime::is_older_than( $item['created_at'], '2025-04-08 00:00:00' );

						break;
					case 'ftp':
					case 'sftp':
						$item['view_url'] = '#';
						break;
					case 'onedrive':
						$item['is_suspended'] = Datetime::is_older_than( $item['created_at'] );
						break;
					default:
						$item['view_url'] = 'https://console.aws.amazon.com/s3/buckets/' . $item['tpd_path'] . '/';
						break;
				}//end switch
				ob_start();
				$template->render( 'pages/destinations/row', $item );
				$result[ $key ]['html_row'] = ob_get_clean();
			}//end foreach
		}//end if

		wp_send_json_success(
			array(
				'destinations' => $result,
			)
		);
	}

	/**
	 * Handles requesting the service for a destination delete.
	 */
	public function json_delete_destination() {
		$this->do_request_sanity_check( 'snapshot_delete_destination', self::TYPE_POST );

		$data = array(
			'tpd_action' => 'delete_destination',
			'tpd_id'     => isset( $_POST['tpd_id'] ) ? $_POST['tpd_id'] : null, // phpcs:ignore
		);

		$task = new Task\Request\Destination( $data['tpd_action'] );

		$validated_data = $task->validate_request_data( $data );
		if ( is_wp_error( $validated_data ) ) {
			wp_send_json_error( $validated_data );
		}

		$args                  = $validated_data;
		$args['request_model'] = new Model\Request\Destination();
		$task->apply( $args );

		if ( $task->has_errors() ) {
			foreach ( $task->get_errors() as $error ) {
				Log::error( $error->get_error_message() );
			}
			wp_send_json_error();
		}

		wp_send_json_success();
	}

	/**
	 * Handles requesting the service for a destination update.
	 */
	public function json_update_destination() {
		$this->do_request_sanity_check( 'snapshot_update_destination', self::TYPE_POST );

		$data = array(
			'tpd_action'    => 'update_destination',
			'tpd_id'        => isset( $_POST['tpd_id'] ) ? $_POST['tpd_id'] : null, // phpcs:ignore
			'tpd_name'      => isset( $_POST['tpd_name'] ) ? $_POST['tpd_name'] : null, // phpcs:ignore
			'tpd_region'    => isset( $_POST['tpd_region'] ) ? $_POST['tpd_region'] : null, // phpcs:ignore
			'tpd_bucket'    => isset( $_POST['tpd_bucket'] ) ? $_POST['tpd_bucket'] : null, // phpcs:ignore
			'tpd_path'      => isset( $_POST['tpd_path'] ) ? $_POST['tpd_path'] : null, // phpcs:ignore
			'tpd_limit'     => isset( $_POST['tpd_limit'] ) ? $_POST['tpd_limit'] : null, // phpcs:ignore
			'tpd_type'      => isset( $_POST['tpd_type'] ) ? $_POST['tpd_type'] : null, // phpcs:ignore
			'ftp_timeout'   => isset( $_POST['ftp-timeout'] ) ? $_POST['ftp-timeout'] : 90, // phpcs:ignore
			'ftp_mode'      => ( isset( $_POST['ftp-passive-mode'] ) && 'on' === $_POST['ftp-passive-mode'] ) ? 1 : 0, // phpcs:ignore
			'ftp_port'      => isset( $_POST['ftp-port'] ) ? $_POST['ftp-port'] : null, // phpcs:ignore
		);

		$is_od_reauth = isset( $_POST['onedrive_reauth'] ) && 'yes' === $_POST['onedrive_reauth']; // phpcs:ignore
		$is_db_reauth = isset( $_POST['dropbox_reauth'] ) && 'yes' === $_POST['dropbox_reauth']; // phpcs:ignore

		if ( 's3_other' === $data['tpd_type'] || 'linode' === $data['tpd_type'] ) {
			$data['tpd_endpoint'] = isset( $_POST['tpd_endpoint'] ) ? $_POST['tpd_endpoint'] : null; // phpcs:ignore
		}

		if ( 'ftp' === $data['tpd_type'] || 'sftp' === $data['tpd_type'] ) {
			$data['tpd_accesskey'] = isset( $_POST['ftp_username'] ) ? $_POST['ftp_username'] : null; // phpcs:ignore
			$data['tpd_secretkey'] = isset( $_POST['ftp_password'] ) ? $_POST['ftp_password'] : null; // phpcs:ignore
		}  else if ( 'azure' === $data['tpd_type']){
			$data['tpd_secretkey'] = isset( $_POST['tpd_accountname'] ) ? $_POST['tpd_accountname'] : null; // phpcs:ignore
			$data['tpd_accesskey'] = isset( $_POST['tpd_accesskey'] ) ? $_POST['tpd_accesskey'] : null; // phpcs:ignore
		} else {
			$data['tpd_accesskey'] = isset( $_POST['tpd_accesskey'] ) ? $_POST['tpd_accesskey'] : null; // phpcs:ignore
			$data['tpd_secretkey'] = isset( $_POST['tpd_secretkey'] ) ? $_POST['tpd_secretkey'] : null; // phpcs:ignore
		}

		// Update destination details.
		$task = new Task\Request\Destination( $data['tpd_action'], $data['tpd_type'] );

		$validated_data = $task->validate_request_data( $data );
		if ( is_wp_error( $validated_data ) ) {
			wp_send_json_error( $validated_data );
		}

		$args                  = $validated_data;
		$args['request_model'] = new Model\Request\Destination();
		$result                = $task->apply( $args );

		$onedrive_ids = get_site_option( 'snapshot_onedrive_destination_suspended', array() );
		$dropbox_ids  = get_site_option( 'snapshot_dropbox_destination_suspended', array() );

		$reauthorized = isset( $onedrive_ids['reauthorized'] ) ? $onedrive_ids['reauthorized'] : array();
		$unauthorized = isset( $onedrive_ids['unauthorized'] ) ? $onedrive_ids['unauthorized'] : array();

		$dropbox_reauthorized = isset( $dropbox_ids['reauthorized'] ) ? $dropbox_ids['reauthorized'] : array();
		$dropbox_unauthorized = isset( $dropbox_ids['unauthorized'] ) ? $dropbox_ids['unauthorized'] : array();

		if ( $task->has_errors() ) {
			foreach ( $task->get_errors() as $error ) {
				Log::error( $error->get_error_message() );
			}

			if ( isset( $result['Message'] ) && $result['Message'] === 'Same destination already exists' ) {
				if ( $is_od_reauth &&
					in_array( $data['tpd_id'], $unauthorized, true ) &&
					! in_array( $data['tpd_id'], $reauthorized, true )
				) {
					$unauthorized = array_diff( $unauthorized, array( $data['tpd_id'] ) );

					$onedrive_ids['reauthorized'] = array_push( $reauthorized, $data['tpd_id'] );
					$onedrive_ids['unauthorized'] = $unauthorized;

					update_site_option( 'snapshot_onedrive_destination_suspended', $onedrive_ids );
				}

				if (
					$is_db_reauth &&
					in_array( $data['tpd_id'], $dropbox_unauthorized, true ) &&
					! in_array( $data['tpd_id'], $dropbox_reauthorized, true )
				) {
					$dropbox_unauthorized = array_diff( $dropbox_unauthorized, array( $data['tpd_id'] ) );

					array_push( $dropbox_reauthorized, $data['tpd_id'] );
					$dropbox_ids['reauthorized'] = $dropbox_reauthorized;
					$dropbox_ids['unauthorized'] = $dropbox_unauthorized;

					update_site_option( 'snapshot_dropbox_destination_suspended', $dropbox_ids );
				}
			}//end if

			wp_send_json_error(
				array(
					'api_response' => $result,
				)
			);
		}//end if

		$exports_failed = get_site_option( 'snapshot_failed_third_party_destination_exports', array() );
		$google_drive   = isset( $exports_failed['google_drive'] ) ? $exports_failed['google_drive'] : array();
		$onedrive       = isset( $exports_failed['onedrive'] ) ? $exports_failed['onedrive'] : array();
		$dropbox        = isset( $exports_failed['dropbox'] ) ? $exports_failed['dropbox'] : array();

		$update = false;
		if ( in_array( $data['tpd_id'], $google_drive, true ) ) {
			$google_drive = array_diff( $google_drive, array( $data['tpd_id'] ) );
			$update       = true;
		}

		if ( in_array( $data['tpd_id'], $onedrive, true ) ) {
			$onedrive = array_diff( $onedrive, array( $data['tpd_id'] ) );
			$update   = true;
		}

		if ( in_array( $data['tpd_id'], $dropbox, true ) ) {
			$dropbox = array_diff( $dropbox, array( $data['tpd_id'] ) );
			$update  = true;
		}

		if (
			$is_od_reauth &&
			in_array( $data['tpd_id'], $unauthorized, true ) &&
			! in_array( $data['tpd_id'], $reauthorized, true )
		) {
			$unauthorized = array_diff( $unauthorized, array( $data['tpd_id'] ) );

			$onedrive_ids['reauthorized'] = array_push( $reauthorized, $data['tpd_id'] );
			$onedrive_ids['unauthorized'] = $unauthorized;

			update_site_option( 'snapshot_onedrive_destination_suspended', $onedrive_ids );
		}

		if (
			$is_db_reauth &&
			in_array( $data['tpd_id'], $dropbox_unauthorized, true ) &&
			! in_array( $data['tpd_id'], $dropbox_reauthorized, true )
		) {
			$dropbox_unauthorized = array_diff( $dropbox_unauthorized, array( $data['tpd_id'] ) );

			array_push( $dropbox_reauthorized, $data['tpd_id'] );
			$dropbox_ids['reauthorized'] = $dropbox_reauthorized;
			$dropbox_ids['unauthorized'] = $dropbox_unauthorized;

			update_site_option( 'snapshot_dropbox_destination_suspended', $dropbox_ids );
		}

		if ( $update ) {
			update_site_option(
				'snapshot_failed_third_party_destination_exports',
				array(
					'google_drive' => $google_drive,
					'onedrive'     => $onedrive,
					'dropbox'      => $dropbox,
				)
			);
		}

		wp_send_json_success();
	}

	/**
	 * Handles requesting the service for a destination activation.
	 */
	public function json_activate_destination() {
		$this->do_request_sanity_check( 'snapshot_update_destination', self::TYPE_POST );

		$data = array(
			'tpd_action'  => 'activate_destination',
			'tpd_id'      => isset( $_POST['tpd_id'] ) ? $_POST['tpd_id'] : null, // phpcs:ignore
			'aws_storage' => isset( $_POST['aws_storage'] ) ? intval( $_POST['aws_storage'] ) : null, // phpcs:ignore
		);

		$type = '';

		if ( isset( $_POST['tpd_type'] ) && 'linode' === $_POST['tpd_type'] ) { // phpcs:ignore
			$type = 'linode'; // phpcs:ignore
			$data['tpd_type'] = $type;
			$data['ftp_host'] = 'linode';
		}

		$task = new Task\Request\Destination( $data['tpd_action'], $type );

		$validated_data = $task->validate_request_data( $data );
		if ( is_wp_error( $validated_data ) ) {
			wp_send_json_error( $validated_data );
		}

		$args                  = $validated_data;
		$args['request_model'] = new Model\Request\Destination();
		$result                = $task->apply( $args );

		if ( $task->has_errors() ) {
			foreach ( $task->get_errors() as $error ) {
				Log::error( $error->get_error_message() );
			}
			wp_send_json_error();
		}

		wp_send_json_success(
			array(
				'api_response' => $result,
			)
		);
	}

	/**
	 * Removes the Google Drive reconnect notice nag.
	 *
	 * Updates the 'snapshot_reconnect_{type}_notice_status' option to 'dismissed'.
	 * Sends a JSON success response.
	 */
	public function json_remove_destination_nag() {
		$this->do_request_sanity_check( 'snapshot_gdrive_reconnect_nag', self::TYPE_GET );

		$type = isset( $_REQUEST['type'] ) && ! empty( $_REQUEST['type'] ) ? sanitize_text_field( $_REQUEST['type'] ) : null;

		if ( is_null( $type ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid type provided.', 'snapshot' ),
				)
			);
		}

		if ( ! in_array( $type, $this->providers, true ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid type provided.', 'snapshot' ),
				)
			);
		}

		update_site_option( "snapshot_reconnect_{$type}_notice_status", 'dismissed' );

		wp_send_json_success();
	}
}