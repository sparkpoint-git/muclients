<?php
/**
 * Other table codec.
 *
 * @package shipper
 */

/**
 * Class Shipper_Helper_Codec_OtherTable
 */
class Shipper_Helper_Codec_OtherTable extends Shipper_Helper_Codec {
	/**
	 * Meta model holder.
	 *
	 * @var mixed|\Shipper_Model_Stored_MigrationMeta
	 */
	protected $meta;

	/**
	 * Gets a list of replacement pairs
	 *
	 * A single replacement pair list, with current domain as key and
	 * replacement macro as value.
	 *
	 * @return array
	 */
	public function get_replacements_list() {
		$meta   = $this->meta;
		$tables = $meta->get( $meta::KEY_OTHER_TABLES, array() );

		if ( empty( $tables ) ) {
			return array();
		}
		$lists = array();
		foreach ( $tables as $table ) {
			$lists[ $table ] = "{{SHIPPER_TABLE_PREFIX}}$table";
		}

		return $lists;
	}

	/**
	 * Gets expansion replacement string
	 *
	 * @param string $name Original domain.
	 * @param string $value Process-dependent domain representation.
	 *                      (macro on export, original on import).
	 *
	 * @return string
	 */
	public function get_replacement( $name, $value ) {
		return '\1 ' . $value . '\3';
	}

	/**
	 * Gets a regex expression matcher string
	 *
	 * Purposefully single-task oriented - just process the subset of SQL
	 * statements actually used by the export process (drop|create|insert).
	 *
	 * Will match an entire line (one line per statement).
	 *
	 * @since 1.2.0
	 *
	 * @param string $string Original table name.
	 * @param string $value Optional table name with prefix replaced with a macro.
	 *
	 * @see https://incsub.atlassian.net/browse/SHI-143
	 * Actual Bug:
	 * Suppose there are two table. table_1 and table_1_another.
	 * You see, table_1_another contains the whole string of table_1.
	 * So when we want to change table_1_another, as table_1 matches, it changes table_1_another as well. Which is not intended.
	 *
	 * @return string
	 */
	public function get_matcher( $string, $value = '' ) {
		$value = ! empty( $value )
			? preg_quote( $value, '/' )
			: preg_quote( $string, '/' );

		return '^' . // phpcs:disable
		       '(' .
		       'DROP TABLE IF EXISTS' .
		       '|' .
		       'CREATE TABLE' .
		       '|' .
		       'INSERT INTO' .
		       ')' .
		       '\s*' .
		       '(' .
		       '`?' . $value . '`?' .
		       ')' .
		       '(.*)' .
		       '$';
		// phpcs:enable
	}

	/**
	 * Shipper_Helper_Codec_OtherTable constructor.
	 *
	 * @param null $meta Shipper_Model_Stored_MigrationMeta instance holder.
	 */
	public function __construct( $meta = null ) {
		if ( null === $meta ) {
			$this->meta = new Shipper_Model_Stored_MigrationMeta();
		} else {
			$this->meta = $meta;
		}
	}
}