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
class Shipper_Controller_Admin_Dashboard extends Shipper_Controller_Admin {

	/**
	 * Sets up menu items
	 *
	 * Also sets up front-end dependencies loading on page load.
	 */
	public function add_menu() {
		$capability = $this->get_capability();

		if ( ! $this->can_user_access_shipper_pages() ) {
			return false;
		}

		$dashboard = add_submenu_page(
			'shipper',
			_x( 'Dashboard', 'page label', 'shipper' ),
			_x( 'Dashboard', 'menu label', 'shipper' ),
			$capability,
			'shipper',
			array( $this, 'render_dashboard' )
		);

		add_action( "load-{$dashboard}", array( $this, 'add_packages_dependencies' ) );
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
	public function render_dashboard() {
		if ( ! $this->can_user_access_shipper_pages() ) {
			wp_die( esc_html( __( 'Nope.', 'shipper' ) ) );
		}

		$tpl       = new Shipper_Helper_Template();
		$dashboard = new Shipper_Model_Stored_Dashboard();
		$tutorials = new Shipper_Model_Stored_Tutorials();

		$args = array(
			'is_package_migration'          => $dashboard->is_package_migration(),
			'has_package'                   => $dashboard->has_package(),
			'package_size'                  => $dashboard->get_package_size(),
			'package_size_text'             => $dashboard->get_package_size( true ),
			'formatted_package_size'        => $dashboard->get_formatted_package_size(),
			'last_migration'                => $dashboard->get_migration_date(),
			'migration_method'              => $dashboard->get_migration_method(),
			'package_name'                  => $dashboard->get_package_name(),
			'migration_in_progress'         => $dashboard->is_migration_in_progress( 'api' ),
			'package_migration_in_progress' => $dashboard->is_migration_in_progress( 'package' ),
			'tutorials'                     => $tutorials->is_hidden() ? array() : $tutorials->all( 3 ),
		);

		$tpl->render( 'pages/dashboard/main', $args );
	}
}