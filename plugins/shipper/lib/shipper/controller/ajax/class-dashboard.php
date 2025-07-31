<?php
/**
 * Shipper AJAX controllers: admin controller class
 *
 * @package shipper
 */

/**
 * Class Shipper_Controller_Dashboard_Ajax
 */
class Shipper_Controller_Ajax_Dashboard extends Shipper_Controller_Ajax {

	/**
	 * Boot method
	 *
	 * @return void
	 */
	public function boot() {
		add_action( 'wp_ajax_shipper_hide_tutorials', array( $this, 'on_hide_tutorials' ) );
	}

	/**
	 * Hide tutorials
	 *
	 * @return void
	 */
	public function on_hide_tutorials() {
		$this->do_request_sanity_check( 'hide-tutorials' );

		( new Shipper_Model_Stored_Tutorials() )->hide_tutorials();
		wp_send_json_success( __( 'Tutorials are hidden', 'shipper' ) );
	}
}