<?php
/**
 * New Fewtures Modal
 *
 * Holds reference to new features data
 *
 * @package shipper
 */

/**
 * New Features model class
 */
class Shipper_Model_Newfeatures extends Shipper_Model_Stored {
	/**
	 * Version for which modal to be shown.
	 * Always set the current plugin version if you want to show the new features modal for the current version.
	 *
	 * @var string
	 */
	const VERSION = '1.2.0';

	/**
	 * Constructor method
	 *
	 * @since 1.2
	 *
	 * @return void
	 */
	public function __construct() {
		parent::__construct( 'new-feature-version' );
	}

	/**
	 * Version for which modal to be shown
	 *
	 * @since 1.2
	 *
	 * @return string
	 */
	public function get_version() {
		return self::VERSION;
	}

	/**
	 * Get the modal version of which has already been showed
	 *
	 * @since 1.2
	 *
	 * @return string
	 */
	public function get_already_showed_version() {
		return $this->get( 'new-feature-version' );
	}

	/**
	 * Get the modal title
	 *
	 * @since 1.2
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Multisite to single site migration', 'shipper' );
	}

	/**
	 * Get modal description
	 *
	 * @since 1.2
	 *
	 * @return string
	 */
	public function get_description() {
		$dock_url    = esc_url( 'https://wpmudev.com/docs/wpmu-dev-plugins/shipper/#export-multisite-to-multisite' );
		$description = __( 'You can now migrate a subsite from your multisite network to a single site installation using both API and Package Migration.', 'shipper' );

		/* translators: %s: doc url. */
		$description .= sprintf( __( ' Refer to the <a href="%s" target="_blank">documentation</a> to learn more.', 'shipper' ), $dock_url );

		return $description;
	}
	/**
	 * Get list of features
	 *
	 * @since 1.2
	 *
	 * @return array
	 */
	public function get_features() {
		return array(
			array(
				'title'       => __( '[API Migration] Export a subsite from a network to a single site', 'shipper' ),
				'description' => __( 'While using the API Export Migration method on a network, you can now select the subsite which you want to migrate and the single site on the Choose Destination modal where you want to migrate the website.', 'shipper' ),
			),
			array(
				'title'       => __( '[API Migration] Import a subsite from a network to a single site', 'shipper' ),
				'description' => __( 'When you try to import a network on a single site installation using the API Import Migration method, you can now choose one of the subsites to migrate from the source network to this single site.', 'shipper' ),
			),
			array(
				'title'       => __( '[Package Migration] Multisite to single site', 'shipper' ),
				'description' => __( 'You can now choose to create a package of the whole network or one of the subsites. If you package a subsite and then install it on a server, it will be installed as a single site.', 'shipper' ),
			),
		);
	}
}