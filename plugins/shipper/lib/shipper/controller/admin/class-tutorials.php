<?php
/**
 * Shipper controllers: admin dashboard page
 *
 * @since v1.1.4
 * @package shipper
 */

/**
 * Admin pages controller, dashboard page
 */
class Shipper_Controller_Admin_Tutorials extends Shipper_Controller_Admin {

	/**
	 * Gets order in which menu registration takes place
	 *
	 * @return int Page order
	 */
	public function get_page_order() {
		return parent::get_page_order() + 5;
	}

	/**
	 * Sets up menu items
	 *
	 * Also sets up front-end dependencies loading on page load.
	 */
	public function add_menu() {
		if ( ! $this->can_user_access_shipper_pages() ) {
			return false;
		}

		if ( ( new Shipper_Model_Stored_Tutorials() )->should_be_hidden() ) {
			return false;
		}

		$tutorials = add_submenu_page(
			'shipper',
			_x( 'Tutorials', 'page label', 'shipper' ),
			_x( 'Tutorials', 'menu label', 'shipper' ),
			$this->get_capability(),
			'shipper-tutorials',
			array( $this, 'render_tutorials' )
		);

		add_action( "load-{$tutorials}", array( $this, 'add_packages_dependencies' ) );
	}

	/**
	 * Adds front-end dependencies specific for the packages page
	 */
	public function add_packages_dependencies() {
		if ( ! shipper_user_can_ship() ) {
			return false;
		}
		$this->add_shared_dependencies();
	}

	/**
	 * Dispatches migrate page states.
	 *
	 * @return void
	 */
	public function render_tutorials() {
		if ( ! $this->can_user_access_shipper_pages() ) {
			wp_die( esc_html( __( 'Nope.', 'shipper' ) ) );
		}

		$tpl       = new Shipper_Helper_Template();
		$tutorials = ( new Shipper_Model_Stored_Tutorials() )->all();

		$tpl->render(
			'pages/tutorials/main',
			array(
				'tutorials' => $tutorials,
				'tpl'       => $tpl,
			)
		);
	}
}