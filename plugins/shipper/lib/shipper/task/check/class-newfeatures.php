<?php
/**
 * Hub API readiness check
 *
 * @package shipper
 */

/**
 * Hub API check class
 */
class Shipper_Task_Check_Newfeatures extends Shipper_Task {

	/**
	 * Check whether new features should be shown or not
	 *
	 * @since 1.2
	 *
	 * @param array $args array of arguments.
	 *
	 * @return bool
	 */
	public function apply( $args = array() ) {
		$model = new Shipper_Model_Newfeatures();

		// modal has been sowed.
		if ( version_compare( SHIPPER_VERSION, $model->get_version(), '>' ) ) {
			return false;
		}

		// modal is yet to show.
		if ( version_compare( SHIPPER_VERSION, $model->get_already_showed_version(), '!=' ) ) {
			return true;
		}
	}
}