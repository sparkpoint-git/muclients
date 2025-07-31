<?php
/**
 * Shipper Dumper Provider
 *
 * @package shipper
 */

/**
 * Class Shipper_Helper_Dumper_Provider
 */
class Shipper_Helper_Dumper {

	const PRE_DUMP_SQL = 'pre-dump.sql';
	const DUMP_SQL     = 'dump.sql';

	/**
	 * Create Dumper
	 *
	 * @param string $engine either php dumper or mysql dumper.
	 *
	 * @return Shipper_Helper_Dumper_Php|false
	 */
	public static function get_provider( $engine = 'php' ) {
		global $wpdb;
		$dsn      = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';port=' . self::get_port() . ';charset=' . $wpdb->charset;
		$username = DB_USER;
		// @RIPS\Annotation\Ignore
		$password = DB_PASSWORD;
		$settings = array(
			'exclude-tables'    => apply_filters( 'shipper_exclude_tables', array() ),
			'exclude-transient' => apply_filters( 'shipper_export_table_exclude_transient', true ),
		);

		if ( 'php' === $engine ) {
			try {
				return new Shipper_Helper_Dumper_Php( $dsn, $username, $password, $settings );
			} catch ( Exception $e ) {
				return false;
			}
		}
	}

	/**
	 * Get port from wp-config.php; If not found, get mysql default port.
	 *
	 * @since 1.2.2
	 *
	 * @return int|mixed|string
	 */
	private static function get_port() {
		$default_port = (int) ini_get( 'mysqli.default_port' );

		if ( empty( $default_port ) ) {
			$default_port = 3306;
		}

		$port = explode( ':', DB_HOST );
		$port = ! empty( $port[1] ) ? $port[1] : $default_port;

		return $port;
	}
}