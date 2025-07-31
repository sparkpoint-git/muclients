<?php
/**
 * Shipper api migration controllers: overrides abstraction
 *
 * @since 1.1.4
 * @package shipper
 */

/**
 * Migration overrides abstraction class
 */
abstract class Shipper_Controller_Override_Migration extends Shipper_Controller_Override {

	/**
	 * Model instance holder.
	 *
	 * @var object
	 */
	private $model;

	/**
	 * Exclusion holder.
	 *
	 * @var array
	 */
	private $exclusions;

	/**
	 * Actually applies controller-specific overrides.
	 */
	abstract public function apply_overrides();

	/**
	 * Gets implementation-specific exclusion scope
	 *
	 * @return string One of the Shipper_Model_Stored_MigrationMeta keys
	 */
	abstract public function get_scope();

	/**
	 * Boots the controller and sets up event listeners
	 */
	public function boot() {
		add_action( 'shipper_migration_before_process_tick', array( $this, 'apply_overrides' ) );
		add_action( 'shipper_before_process_package_or_files', array( $this, 'apply_overrides' ) );
	}

	/**
	 * Gets the model instance
	 *
	 * @return object A Shipper_Model_Stored_MigrationMeta instance
	 */
	public function get_model() {
		if ( empty( $this->model ) ) {
			$this->model = new Shipper_Model_Stored_MigrationMeta();
		}

		return $this->model;
	}

	/**
	 * Gets the exclusions to apply
	 *
	 * @return array
	 */
	public function get_exclusions() {
		if ( empty( $this->exclusions ) ) {
			$tmp              = $this->get_model()->get( $this->get_scope(), array() );
			$this->exclusions = array_unique( array_filter( array_map( 'trim', $tmp ) ) );
		}

		return $this->exclusions;
	}
}