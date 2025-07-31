<?php
/**
 * Shipper models: Dashboard Model
 *
 * Holds information for the dashboard page
 *
 * @since 1.1.4
 *
 * @package shipper
 */

/**
 * Class Shipper_Model_Stored_Dashboard
 */
class Shipper_Model_Stored_Dashboard extends Shipper_Model_Stored {

	/**
	 * Package size
	 *
	 * @since 1.1.4
	 *
	 * @const string
	 */
	const API_MIGRATION_PACKAGE_SIZE = 'package_size';

	/**
	 * API migration instance
	 *
	 * @since 1.1.4
	 *
	 * @var \Shipper_Model_Stored_Migration|null
	 */
	public $api_migration = null;

	/**
	 * Package migration instance
	 *
	 * @since 1.1.4
	 *
	 * @var \Shipper_Model_Stored_Package|null
	 */
	public $package_migration = null;

	/**
	 * Whether is package migration or not
	 *
	 * @since 1.1.4
	 *
	 * @var bool
	 */
	public $is_package_migration = true;

	/**
	 * Migration date
	 *
	 * @since 1.1.4
	 *
	 * @var null
	 */
	public $migration_date = null;

	/**
	 * Shipper_Model_Stored_Dashboard constructor
	 *
	 * @since 1.1.4
	 *
	 * @return void
	 */
	public function __construct() {
		parent::__construct( 'dashboard' );

		$this->api_migration     = new Shipper_Model_Stored_Migration();
		$this->package_migration = new Shipper_Model_Stored_Package();
		$this->set_migration_data();

		/**
		 * Set package size on migration complete.
		 *
		 * @since 1.2
		 */
		add_action( 'shipper_migration_complete', array( $this, 'on_migration_complete' ) );
	}

	/**
	 * Set migration data
	 *
	 * @since 1.1.4
	 *
	 * @return void
	 */
	public function set_migration_data() {
		$last_api_migration     = $this->api_migration->get( Shipper_Model_Stored_Migration::KEY_CREATED );
		$last_package_migration = $this->package_migration->get( Shipper_Model_Stored_Package::KEY_CREATED );

		if ( $last_api_migration > $last_package_migration ) {
			$this->is_package_migration = false;
			$this->migration_date       = $last_api_migration;
		} else {
			$this->migration_date = $last_package_migration;
		}
	}

	/**
	 * Check whether it's a package migration or not
	 *
	 * @since 1.1.4
	 *
	 * @return bool
	 */
	public function is_package_migration() {
		return $this->is_package_migration;
	}

	/**
	 * Check whether there is a package or not
	 *
	 * @since 1.1.4
	 *
	 * @return bool
	 */
	public function has_package() {
		return $this->is_package_migration()
			? $this->package_migration->has_package()
			: ! $this->api_migration->is_empty();
	}

	/**
	 * Get formatted package size, ei: 150 MB
	 *
	 * @since 1.1.4
	 *
	 * @return string|false other wise
	 */
	public function get_formatted_package_size() {
		return $this->is_package_migration()
			? size_format( $this->package_migration->get_size() )
			: size_format( $this->api_migration->get_size() );
	}

	/**
	 * Get package size in either string or number format
	 *
	 * @since 1.1.4
	 *
	 * @param  bool $prefix database prefix.
	 *
	 * @return mixed
	 */
	public function get_package_size( $prefix = false ) {
		$size = $this->get_formatted_package_size();

		$package_size      = '';
		$package_size_text = '';

		if ( preg_match( '/(\d+) (\w+$)/', $size, $matches ) ) {
			$package_size      = $matches[1];
			$package_size_text = $matches[2];
		}

		return $prefix ? $package_size_text : $package_size;
	}

	/**
	 * Get migration date
	 *
	 * @since 1.1.4
	 *
	 * @return string
	 */
	public function get_migration_date() {
		if ( ! $this->get_package_size() ) {
			return __( 'Never', 'shipper' );
		}

		return date_i18n( get_option( 'date_format' ) . ' @ ' . get_option( 'time_format' ), $this->migration_date );
	}

	/**
	 * Get migration method
	 *
	 * @since 1.1.4
	 *
	 * @return string
	 */
	public function get_migration_method() {
		if ( ! $this->get_package_size() ) {
			return __( 'None', 'shipper' );
		}

		return $this->is_package_migration()
			? __( 'Package Migration', 'shipper' )
			: ( 'export' === $this->api_migration->get_type() ? __( 'API Migration - Export' ) : __( 'API Migration - Import' ) );
	}

	/**
	 * Get package name
	 *
	 * @since 1.1.4
	 *
	 * @return string|null
	 */
	public function get_package_name() {
		if ( $this->is_package_migration() ) {
			return $this->package_migration->get_package_name();
		}
	}

	/**
	 * Check whether there is any migration in progress
	 *
	 * @since 1.1.4
	 *
	 * @param string $migration_method whether it's package or api migration.
	 *
	 * @return mixed
	 */
	public function is_migration_in_progress( $migration_method = 'api' ) {
		if ( 'package' === $migration_method ) {
			return $this->api_migration->is_package_migration() && $this->api_migration->is_active();
		}

		if ( 'api' === $migration_method && ! $this->api_migration->is_package_migration() ) {
			return array(
				'is_active' => $this->api_migration->is_active(),
				'type'      => $this->api_migration->get_type(),
			);
		}
	}

	/**
	 * Set the package size
	 *
	 * @param int    $bytes number of bytes.
	 * @param string $type migration type.
	 */
	public function set_package_size( $bytes, $type = 'api' ) {
		if ( 'api' === $type ) {
			$this->set( self::API_MIGRATION_PACKAGE_SIZE, $bytes );
		}
	}

	/**
	 * We're are storing package size of API Migration, cause it's get deleted when API migration is completed.
	 *
	 * @since 1.2
	 *
	 * @param Shipper_Model_Stored_Migration $migration migration model instance.
	 *
	 * @return void
	 */
	public function on_migration_complete( $migration ) {
		$this->set_package_size( $migration->get_size() );
		$this->save();
	}
}