<?php
/**
 * Shipper PHP Dumper
 *
 * @package shipper
 */

/**
 * Class Shipper_Helper_Dumper_Php
 */
class Shipper_Helper_Dumper_Php {

	const MAX_LINE_SIZE_IN_BYTES  = 1000000;
	const DEFINER_RE              = 'DEFINER=`(?:[^`]|``)*`@`(?:[^`]|``)*`';
	const SHIPPER_JSON_START      = '{{SHIPPER_JSON_START}}';
	const SHIPPER_JSON_END        = '{{SHIPPER_JSON_END}}';
	const SHIPPER_SERIALIZE_START = '{{SHIPPER_SERIALIZE_START}}';
	const SHIPPER_SERIALIZE_END   = '{{SHIPPER_SERIALIZE_END}}';

	/**
	 * Database username.
	 *
	 * @var string
	 */
	public $user;

	/**
	 * Database password.
	 *
	 * @var string
	 */
	public $pass;

	/**
	 * Connection string for PDO.
	 *
	 * @var string
	 */
	public $dsn;

	/**
	 * Destination filename
	 *
	 * @var string
	 */
	public $file_name;

	/**
	 * Numerical Mysql types
	 *
	 * @var array
	 */
	public $mysql_types = array(
		'numerical' => array(
			'bit',
			'tinyint',
			'smallint',
			'mediumint',
			'int',
			'integer',
			'bigint',
			'real',
			'double',
			'float',
			'decimal',
			'numeric',
		),
		'blob'      => array(
			'tinyblob',
			'blob',
			'mediumblob',
			'longblob',
			'binary',
			'varbinary',
			'bit',
			'geometry',
			'point',
			'linestring',
			'polygon',
			'multipoint',
			'multilinestring',
			'multipolygon',
			'geometrycollection',
		),
	);

	/**
	 * Tables holder
	 *
	 * @var array
	 */
	private $tables = array();

	/**
	 * Views holder
	 *
	 * @var array
	 */
	private $views = array();

	/**
	 * DB instance holder
	 *
	 * @var null
	 */
	private $db_handler = null;

	/**
	 * DB type holder
	 *
	 * @var string
	 */
	private $db_type = '';

	/**
	 * DUMP settings holder
	 *
	 * @var array
	 */
	private $dump_settings = array();

	/**
	 * PDO settings holder
	 *
	 * @var array
	 */
	private $pdo_settings = array();

	/**
	 * Mysql version holder.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Column types holder
	 *
	 * @var array
	 */
	private $table_column_types = array();

	/**
	 * Callable func holder
	 *
	 * @var string
	 */
	private $transform_table_row_callable;

	/**
	 * Callable func holder
	 *
	 * @var string
	 */
	private $info_callable;

	/**
	 * File writer instance holder
	 *
	 * @var string
	 */
	private $writer;

	/**
	 * Database name, parsed from dsn.
	 *
	 * @var string
	 */
	private $db_name;

	/**
	 * Host name, parsed from dsn.
	 *
	 * @var string
	 */
	private $host;

	/**
	 * Dsn string parsed as an array.
	 *
	 * @var array
	 */
	private $dsn_array = array();

	/**
	 * Keyed on table name, with the value as the conditions.
	 * e.g. - 'users' => 'date_registered > NOW() - INTERVAL 6 MONTH'
	 *
	 * @var array
	 */
	private $table_wheres = array();

	/**
	 * Table limits holder
	 *
	 * @var array
	 */
	private $table_limits = array();


	/**
	 * Constructor of Mysqldump. Note that in the case of an SQLite database.
	 * connection, the filename must be in the $db parameter.
	 *
	 * @param string $dsn        PDO DSN connection string.
	 * @param string $user       SQL account username.
	 * @param string $pass       SQL account password.
	 * @param array  $dump_settings SQL database settings.
	 * @param array  $pdo_settings  PDO configured attributes.
	 *
	 * @throws \Exception Throws exception.
	 */
	public function __construct(
		$dsn = '',
		$user = '',
		$pass = '',
		$dump_settings = array(),
		$pdo_settings = array()
	) {
		$dump_settings_default = array(
			'include-tables'       => array(),
			'exclude-tables'       => array(),
			'include-views'        => array(),
			'exclude-transient'    => true,
			'init_commands'        => array(),
			'no-data'              => array(),
			'if-not-exists'        => true,
			'reset-auto-increment' => false,
			'add-drop-database'    => false,
			'add-drop-table'       => true,
			'add-drop-trigger'     => true,
			'add-locks'            => false,
			'complete-insert'      => false,
			'databases'            => false,
			'disable-keys'         => true,
			'extended-insert'      => false,
			'hex-blob'             => true, /* faster than escaped content */
			'insert-ignore'        => false,
			'buffer-length'        => self::MAX_LINE_SIZE_IN_BYTES,
			'no-autocommit'        => false,
			'no-create-info'       => false,
			'lock-tables'          => true,
			'routines'             => false,
			'single-transaction'   => true,
			'skip-triggers'        => false,
			'skip-tz-utc'          => false,
			'skip-comments'        => true,
			'skip-dump-date'       => true,
			'skip-definer'         => true,
			'where'                => '',
		);

		// phpcs:disable
		$pdo_settings_default = array(
			PDO::ATTR_PERSISTENT               => true,
			PDO::ATTR_ERRMODE                  => PDO::ERRMODE_EXCEPTION,
			PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
		);
		// phpcs:enable

		$this->user = $user;
		$this->pass = $pass;
		$this->parse_dsn( $dsn );

		$this->pdo_settings  = array_replace_recursive( $pdo_settings_default, $pdo_settings );
		$this->dump_settings = array_replace_recursive( $dump_settings_default, $dump_settings );

		$diff = array_diff( array_keys( $this->dump_settings ), array_keys( $dump_settings_default ) );
		if ( count( $diff ) > 0 ) {
			throw new Exception( 'Unexpected value in dump_settings: (' . implode( ',', $diff ) . ')' );
		}

		if ( ! is_array( $this->dump_settings['include-tables'] ) ||
			! is_array( $this->dump_settings['exclude-tables'] ) ) {
			throw new Exception( 'Include-tables and exclude-tables should be arrays' );
		}

		// If no include-views is passed in, dump the same views as tables, mimic mysqldump behaviour.
		if ( ! isset( $dump_settings['include-views'] ) ) {
			$this->dump_settings['include-views'] = $this->dump_settings['include-tables'];
		}
	}

	/**
	 * Destructor of Mysqldump. Unsets db_handlers and database objects.
	 */
	public function __destruct() {
		$this->db_handler = null;
	}

	/**
	 * Keyed by table name, with the value as the conditions:
	 * e.g. 'users' => 'date_registered > NOW() - INTERVAL 6 MONTH AND deleted=0'
	 *
	 * @param array $table_wheres where clause.
	 */
	public function set_table_wheres( array $table_wheres ) {
		$this->table_wheres = $table_wheres;
	}

	/**
	 * Get table where
	 *
	 * @param string $table_name name of the table.
	 *
	 * @return boolean|mixed
	 */
	public function get_table_where( $table_name ) {
		if ( ! empty( $this->table_wheres[ $table_name ] ) ) {
			return $this->table_wheres[ $table_name ];
		} elseif ( $this->dump_settings['where'] ) {
			return $this->dump_settings['where'];
		}

		return false;
	}

	/**
	 * Keyed by table name, with the value as the numeric limit:
	 * e.g. 'users' => 3000
	 *
	 * @param array $table_limits table limits.
	 */
	public function set_table_limits( array $table_limits ) {
		$this->table_limits = $table_limits;
	}

	/**
	 * Returns the LIMIT for the table.  Must be numeric to be returned.
	 *
	 * @param string $table_name name of the table.
	 * @return boolean
	 */
	public function get_table_limit( $table_name ) {
		if ( ! isset( $this->table_limits[ $table_name ] ) ) {
			return false;
		}

		$limit = $this->table_limits[ $table_name ];
		if ( ! is_numeric( $limit ) ) {
			return false;
		}

		return $limit;
	}

	/**
	 * Parse DSN string and extract dbname value
	 * Several examples of a DSN string
	 *   mysql:host=localhost;dbname=testdb
	 *   mysql:host=localhost;port=3307;dbname=testdb
	 *   mysql:unix_socket=/tmp/mysql.sock;dbname=testdb
	 *
	 * @param string $dsn dsn string to parse.
	 * @return boolean
	 *
	 * @throws \Exception Throws Exception.
	 */
	private function parse_dsn( $dsn ) {
		if ( empty( $dsn ) ) {
			throw new Exception( 'Empty DSN string' );
		}

		$pos = strpos( $dsn, ':' );

		if ( false === $pos ) {
			throw new Exception( 'Empty DSN string' );
		}

		$this->dsn     = $dsn;
		$this->db_type = strtolower( substr( $dsn, 0, $pos ) ); // always returns a string.

		if ( empty( $this->db_type ) ) {
			throw new Exception( 'Missing database type from DSN string' );
		}

		$dsn = substr( $dsn, $pos + 1 );

		foreach ( explode( ';', $dsn ) as $key ) {
			$key_arr                                      = explode( '=', $key );
			$this->dsn_array[ strtolower( $key_arr[0] ) ] = $key_arr[1];
		}

		if ( empty( $this->dsn_array['host'] ) &&
			empty( $this->dsn_array['unix_socket'] ) ) {
			throw new Exception( 'Missing host from DSN string' );
		}
		$this->host = ( ! empty( $this->dsn_array['host'] ) ) ?
			$this->dsn_array['host'] : $this->dsn_array['unix_socket'];

		if ( empty( $this->dsn_array['dbname'] ) ) {
			throw new Exception( 'Missing database name from DSN string' );
		}

		$this->db_name = $this->dsn_array['dbname'];

		return true;
	}

	/**
	 * Connect with PDO.
	 *
	 * @return void
	 *
	 * @throws \Exception Throws exception.
	 */
	private function connect() {
		// phpcs:disable
		try {
			$this->db_handler = @new PDO(
				$this->dsn,
				$this->user,
				$this->pass,
				$this->pdo_settings
			);
			// Execute init commands once connected
			foreach ( $this->dump_settings['init_commands'] as $stmt ) {
				$this->db_handler->exec( $stmt );
			}
			// Store server version
			$this->version = $this->db_handler->getAttribute( PDO::ATTR_SERVER_VERSION );

		} catch ( PDOException $e ) {
			throw new Exception(
				'Connection to ' . $this->db_type . ' failed with message: ' .
				$e->getMessage()
			);
		}

		if ( is_null( $this->db_handler ) ) {
			throw new Exception( 'Connection to ' . $this->db_type . 'failed' );
		}

		$this->db_handler->setAttribute( PDO::ATTR_ORACLE_NULLS, PDO::NULL_NATURAL );
		// phpcs:enable
	}

	/**
	 * Primary function, triggers dumping.
	 *
	 * @param string $filename  Name of file to write sql dump to.
	 * @return bool
	 * @throws \Exception Throws exception.
	 */
	public function start( $filename = '' ) {
		// Output file can be redefined here.
		if ( ! empty( $filename ) ) {
			$this->file_name = $filename;
		}

		$this->connect();

		$this->writer = Shipper_Helper_Fs_File::open( $this->file_name, 'wb' );
		$this->writer->fwrite( $this->get_file_header() );

		if ( $this->dump_settings['databases'] ) {
			$this->writer->fwrite(
				$this->get_database_header( $this->db_name )
			);
			if ( $this->dump_settings['add-drop-database'] ) {
				$this->writer->fwrite(
					$this->add_drop_database( $this->db_name )
				);
			}
		}

		// Get table, view, trigger, procedures, functions and events structures from databse.
		$this->get_database_structure_tables();
		$this->get_database_structure_views();

		if ( $this->dump_settings['databases'] ) {
			$this->writer->fwrite(
				$this->databases( $this->db_name )
			);
		}

		// If there still are some tables/views in include-tables array,
		// that means that some tables or views weren't found.
		// Give proper error and exit.
		// This check will be removed once include-tables supports regexps.
		if ( 0 < count( $this->dump_settings['include-tables'] ) ) {
			$name = implode( ',', $this->dump_settings['include-tables'] );
			throw new Exception( 'Table (' . $name . ') not found in database' );
		}

		$is_done = $this->export_tables();

		if ( ! $is_done ) {
			return false;
		}

		$this->export_views();

		$this->writer->fwrite( $this->get_dump_file_footer() );

		return true;
	}

	/**
	 * Returns header for dump file.
	 *
	 * @return string
	 */
	private function get_file_header() {
		$header = '';
		if ( ! $this->dump_settings['skip-comments'] ) {
			if ( ! empty( $this->version ) ) {
				$header .= "-- Server version \t" . $this->version . PHP_EOL;
			}

			if ( ! $this->dump_settings['skip-dump-date'] ) {
				$header .= '-- Date: ' . gmdate( 'r' ) . PHP_EOL . PHP_EOL;
			}
		}
		return $header;
	}

	/**
	 * Returns footer for dump file.
	 *
	 * @return string
	 */
	private function get_dump_file_footer() {
		$footer = '';
		if ( ! $this->dump_settings['skip-comments'] ) {
			$footer .= '-- Dump completed';
			if ( ! $this->dump_settings['skip-dump-date'] ) {
				$footer .= ' on: ' . gmdate( 'r' );
			}
			$footer .= PHP_EOL;
		}

		return $footer;
	}

	/**
	 * Reads table names from database.
	 * Fills $this->tables array so they will be dumped later.
	 */
	private function get_database_structure_tables() {
		if ( empty( $this->dump_settings['include-tables'] ) ) {
			// include all tables for now, blacklisting happens later.
			foreach ( $this->db_handler->query( $this->show_tables( $this->db_name ) ) as $row ) {
				array_push( $this->tables, current( $row ) );
			}
		} else {
			// include only the tables mentioned in include-tables.
			foreach ( $this->db_handler->query( $this->show_tables( $this->db_name ) ) as $row ) {
				if ( in_array( current( $row ), $this->dump_settings['include-tables'], true ) ) {
					array_push( $this->tables, current( $row ) );
					$elem = array_search(
						current( $row ),
						$this->dump_settings['include-tables'],
						true
					);
					unset( $this->dump_settings['include-tables'][ $elem ] );
				}
			}
		}
	}

	/**
	 * Reads view names from database.
	 * Fills $this->tables array so they will be dumped later.
	 */
	private function get_database_structure_views() {
		if ( empty( $this->dump_settings['include-views'] ) ) {
			// include all views for now, blacklisting happens later.
			foreach ( $this->db_handler->query( $this->show_views( $this->db_name ) ) as $row ) {
				array_push( $this->views, current( $row ) );
			}
		} else {
			// include only the tables mentioned in include-tables.
			foreach ( $this->db_handler->query( $this->show_views( $this->db_name ) ) as $row ) {
				if ( in_array( current( $row ), $this->dump_settings['include-views'], true ) ) {
					array_push( $this->views, current( $row ) );
					$elem = array_search(
						current( $row ),
						$this->dump_settings['include-views'],
						true
					);
					unset( $this->dump_settings['include-views'][ $elem ] );
				}
			}
		}
	}

	/**
	 * Compare if $table name matches with a definition inside $arr
	 *
	 * @param string $table table name.
	 * @param array  $arr array with strings or patterns.
	 *
	 * @return bool
	 */
	private function matches( $table, $arr ) {
		$match = false;

		foreach ( $arr as $pattern ) {
			if ( '/' !== $pattern[0] ) {
				continue;
			}
			if ( 1 === preg_match( $pattern, $table ) ) {
				$match = true;
			}
		}

		return in_array( $table, $arr, true ) || $match;
	}

	/**
	 * Exports all the tables selected from database
	 *
	 * @return bool
	 */
	private function export_tables() {
		$model = new Shipper_Model_Stored_Dump();

		// Exporting tables one by one.
		foreach ( $this->tables as $table ) {
			if ( $this->matches( $table, $model->get( 'processed_tables', array() ) ) ) {
				continue;
			}

			if ( $this->matches( $table, $this->dump_settings['exclude-tables'] ) ) {
				continue;
			}
			$this->get_table_structure( $table, $model );
			if ( ! $this->dump_settings['no-data'] ) {
				$data = $this->list_values( $table );

				if ( empty( $data['is_done'] ) ) {
					return false;
				}
			} elseif ( $this->dump_settings['no-data'] || $this->matches( $table, $this->dump_settings['no-data'] ) ) {
				continue;
			} else {
				$data = $this->list_values( $table );

				if ( empty( $data['is_done'] ) ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Exports all the views found in database
	 */
	private function export_views() {
		if ( false === $this->dump_settings['no-create-info'] ) {
			// Exporting views one by one.
			foreach ( $this->views as $view ) {
				if ( $this->matches( $view, $this->dump_settings['exclude-tables'] ) ) {
					continue;
				}
				$this->table_column_types[ $view ] = $this->get_table_column_types( $view );
				$this->get_view_structure_table( $view );
			}
			foreach ( $this->views as $view ) {
				if ( $this->matches( $view, $this->dump_settings['exclude-tables'] ) ) {
					continue;
				}
				$this->get_view_structure_view( $view );
			}
		}
	}

	/**
	 * Table structure extractor
	 *
	 * @param string $table_name name of the table.
	 * @param string $model model object.
	 */
	private function get_table_structure( $table_name, $model ) {
		$processing_table = $model->get( 'processing_table' );

		if ( $table_name === $processing_table ) {
			$this->table_column_types[ $table_name ] = $this->get_table_column_types( $table_name );
			return;
		}

		if ( ! $this->dump_settings['no-create-info'] ) {
			$ret  = '';
			$stmt = $this->show_create_table( $table_name );
			foreach ( $this->db_handler->query( $stmt ) as $r ) {
				$this->writer->fwrite( $ret );
				if ( $this->dump_settings['add-drop-table'] ) {
					$this->writer->fwrite(
						$this->drop_table( $table_name )
					);
				}
				$this->writer->fwrite(
					$this->create_table( $r )
				);
				break;
			}
		}

		$this->table_column_types[ $table_name ] = $this->get_table_column_types( $table_name );
	}

	/**
	 * Store column types to create data dumps and for Stand-In tables
	 *
	 * @param string $table_name  Name of table to export.
	 * @return array type column types detailed.
	 */
	private function get_table_column_types( $table_name ) {
		$column_types = array();
		$columns      = $this->db_handler->query(
			$this->show_columns( $table_name )
		);
		$columns->setFetchMode( PDO::FETCH_ASSOC ); // phpcs:ignore

		foreach ( $columns as $key => $col ) {
			$types                         = $this->parse_column_type( $col );
			$column_types[ $col['Field'] ] = array(
				'is_numeric' => $types['is_numeric'],
				'is_blob'    => $types['is_blob'],
				'type'       => $types['type'],
				'type_sql'   => $col['Type'],
				'is_virtual' => $types['is_virtual'],
			);
		}

		return $column_types;
	}

	/**
	 * View structure extractor, create table (avoids cyclic references)
	 *
	 * @param string $view_name view name.
	 */
	private function get_view_structure_table( $view_name ) {
		$stmt = $this->show_create_view( $view_name );

		// create views as tables, to resolve dependencies.
		foreach ( $this->db_handler->query( $stmt ) as $r ) {
			if ( $this->dump_settings['add-drop-table'] ) {
				$this->writer->fwrite(
					$this->drop_view( $view_name )
				);
			}

			$this->writer->fwrite(
				$this->create_stand_in_table( $view_name )
			);
			break;
		}
	}

	/**
	 * Write a create table statement for the table Stand-In, show create
	 * table would return a create algorithm when used on a view
	 *
	 * @param string $view_name  Name of view to export.
	 * @return string create statement.
	 */
	public function create_stand_in_table( $view_name ) {
		$ret = array();
		foreach ( $this->table_column_types[ $view_name ] as $k => $v ) {
			$ret[] = "`${k}` ${v['type_sql']}";
		}
		$ret = implode( PHP_EOL . ',', $ret );

		$ret = "CREATE TABLE IF NOT EXISTS `$view_name` (" . PHP_EOL . $ret . PHP_EOL . ');' . PHP_EOL;

		return $ret;
	}

	/**
	 * View structure extractor, create view
	 *
	 * @param string $view_name view name.
	 */
	private function get_view_structure_view( $view_name ) {

		$stmt = $this->show_create_view( $view_name );

		// create views, to resolve dependencies.
		// replacing tables with views.
		foreach ( $this->db_handler->query( $stmt ) as $r ) {
			// because we must replace table with view, we should delete it.
			$this->writer->fwrite(
				$this->drop_view( $view_name )
			);
			$this->writer->fwrite(
				$this->create_view( $r )
			);
			break;
		}
	}

	/**
	 * Prepare values for output
	 *
	 * @param string $table_name Name of table which contains rows.
	 * @param array  $row Associative array of column names and values to be quoted.
	 *
	 * @return array
	 */
	private function prepare_column_values( $table_name, array $row ) {
		$ret          = array();
		$column_types = $this->table_column_types[ $table_name ];

		if ( $this->transform_table_row_callable ) {
			$row = call_user_func( $this->transform_table_row_callable, $table_name, $row );
		}

		foreach ( $row as $col_name => $col_value ) {
			$ret[] = $this->escape( $col_value, $column_types[ $col_name ] );
		}

		return $ret;
	}

	/**
	 * Escape values with quotes when needed
	 *
	 * @param array $col_value Name of table which contains rows.
	 * @param array $col_type Associative array of column names and values to be quoted.
	 *
	 * @return string
	 */
	private function escape( $col_value, $col_type ) {
		if ( is_null( $col_value ) ) {
			return 'NULL';
		} elseif ( $this->dump_settings['hex-blob'] && $col_type['is_blob'] ) {
			if ( 'bit' === $col_type['type'] || ! empty( $col_value ) ) {
				return "0x${col_value}";
			} else {
				return "''";
			}
		} elseif ( $col_type['is_numeric'] ) {
			return $col_value;
		}

		return $this->db_handler->quote( $col_value );
	}

	/**
	 * Set a callable that will be used to transform table rows
	 *
	 * @param callable $callable callable function.
	 *
	 * @return void
	 */
	public function set_transform_table_row_hook( $callable ) {
		$this->transform_table_row_callable = $callable;
	}

	/**
	 * Set a callable that will be used to report dump information
	 *
	 * @param callable $callable callable function.
	 *
	 * @return void
	 */
	public function set_info_hook( $callable ) {
		$this->info_callable = $callable;
	}

	/**
	 * Table rows extractor
	 *
	 * @param string $table_name  Name of table to export.
	 *
	 * @return null
	 */
	private function list_values( $table_name ) {
		$this->prepare_list_values( $table_name );

		$only_once = true;
		$line_size = 0;

		$model = new Shipper_Model_Stored_Dump();

		// colStmt is used to form a query to obtain row values.
		$col_stmt = $this->get_column_stmt( $table_name );
		// colNames is used to get the name of the columns when using complete-insert.
		if ( $this->dump_settings['complete-insert'] ) {
			$col_names = $this->get_column_names( $table_name );
		}

		$stmt = 'SELECT ' . implode( ',', $col_stmt ) . " FROM `$table_name`";
		// Table specific conditions override the default 'where'.
		$condition = $this->get_table_where( $table_name );

		if ( $condition ) {
			$stmt .= " WHERE {$condition}";
		}

		$limit = $this->get_table_limit( $table_name );

		if ( false !== $limit ) {
			$stmt .= " LIMIT {$limit}";
		}

		$processed_row    = $model->get( 'processed_row', 0 );
		$processing_table = $model->get( 'processing_table' );
		$processed_tables = $model->get( 'processed_tables', array() );

		$settings     = new Shipper_Model_Stored_Options();
		$limit        = $settings->get( $settings::KEY_PACKAGE_DB_LIMIT, 10000 );
		$is_safe_mode = apply_filters( 'shipper_is_safe_mode', $settings->get( $settings::KEY_PACKAGE_SAFE_MODE, false ) );

		if ( $is_safe_mode ) {
			/**
			 * Lets play safe. Some cheap hosts can't handle large files and we can't even manipulate max_execution_time.
			 * That's why lets make sure, we don't cross the max execution time set by the hosting provider.
			 * Yeah, we know it's slow though but better than failing :D
			 *
			 * @since 1.2.4
			 */
			$meta = new Shipper_Model_Stored_PackageMeta();

			if ( $meta->is_extract_mode() && $meta->get_site_id() !== 1 ) {
				$limit = 200;
			}

			$limit = 500;
		}

		$stmt .= " LIMIT {$limit}";

		$offset = 0;
		if ( $table_name === $processing_table ) {
			$offset = $processed_row ? $processed_row : 0;
			$stmt  .= " OFFSET {$offset}";
		}

		$result_set = $this->db_handler->query( $stmt );
		$result_set->setFetchMode( PDO::FETCH_ASSOC ); // phpcs:ignore

		$ignore    = $this->dump_settings['insert-ignore'] ? '  IGNORE' : '';
		$row_count = 0;

		foreach ( $result_set as $row ) {
			$row_count++;
			if ( apply_filters( 'shipper_export_table_exclude_row', false, $row, $table_name ) ) {
				continue;
			}

			if ( $this->dump_settings['exclude-transient'] && $this->is_transient( $row, $table_name ) ) {
				continue;
			}

			$this->convert_serialized_to_json( $row, $table_name );

			$values              = $this->prepare_column_values( $table_name, $row );
			$modified_table_name = apply_filters( 'shipper_get_modified_table_name', $table_name );

			if ( $only_once || ! $this->dump_settings['extended-insert'] ) {
				if ( $this->dump_settings['complete-insert'] ) {
					$line_size += $this->writer->fwrite(
						"INSERT$ignore INTO `$modified_table_name` (" .
						implode( ', ', $col_names ) .
						') VALUES (' . implode( ',', $values ) . ')'
					);
				} else {
					$line_size += $this->writer->fwrite(
						"INSERT$ignore INTO `$modified_table_name` VALUES (" . implode( ',', $values ) . ')'
					);
				}
				$only_once = false;
			} else {
				$line_size += $this->writer->fwrite( ',(' . implode( ',', $values ) . ')' );
			}
			if ( ( $line_size > $this->dump_settings['buffer-length'] ) || ! $this->dump_settings['extended-insert'] ) {
				$only_once = true;
				$line_size = $this->writer->fwrite( ';' . PHP_EOL );
			}
		}

		$model->set( 'processing_table', $table_name );
		$model->set( 'processed_row', $offset + $limit );

		$is_done = $limit > $row_count;

		if ( $is_done ) {
			$model->set( 'processed_tables', array_unique( array_merge( $processed_tables, array( $table_name ) ) ) );
		}

		$result_set->closeCursor();
		$model->save();

		if ( $this->info_callable ) {
			call_user_func(
				$this->info_callable,
				'table',
				array(
					'name'      => $table_name,
					'row_count' => $row_count,
				)
			);
		}

		if ( ! $is_done ) {
			return array(
				'is_done'          => $is_done,
				'processed_row'    => $offset + $limit,
				'processing_table' => $table_name,
				'processed_tables' => $model->get( 'processed_tables', array() ),
			);
		}

		if ( ! $only_once ) {
			$this->writer->fwrite( ';' . PHP_EOL );
		}

		$this->end_list_values( $table_name, $row_count );

		return array( 'is_done' => $is_done );
	}

	/**
	 * Convert serialized value to JSON
	 *
	 * @since 1.2.1
	 *
	 * @param array  $row row name.
	 * @param string $table_name name of the table.
	 */
	private function convert_serialized_to_json( &$row, $table_name ) {
		$replacer = new Shipper_Helper_Replacer_String( Shipper_Helper_Codec::ENCODE );
		$database = new Shipper_Model_Database();

		foreach ( $row as $column => $value ) {
			if ( ! is_serialized( $value ) ) {
				continue;
			}

			if ( $this->is_serialized_object( $value ) && $database->is_options_table_row( $row, $table_name ) ) {
				/**
				 * An object found in the wp_options table and `wp_json_encode` won't be able to contain that object.
				 * There is no `replaceable value`, so keep it as it is.
				 *
				 * If we find any object that need to encoded anyway, just include that in the `Shipper_Helper_Dumper_Php::should_encode method`.
				 *
				 * @since 1.2.8
				 */
				continue;
			}

			if ( $this->is_serialized_object( $value ) && $this->should_encode( $row ) ) {
				/**
				 * An object found in the serialized data and `wp_json_encode` won't be able to contain that object.
				 * But we need to keep that object anyhow. As it contains `replaceable value` like website url, let's encode it.
				 *
				 * @since 1.2.6
				 */
				$row[ $column ] = self::SHIPPER_SERIALIZE_START . $replacer->transform( $value ) . self::SHIPPER_SERIALIZE_END;
				continue;
			}

			$row[ $column ] = self::SHIPPER_JSON_START . wp_json_encode( unserialize( $value ) ) . self::SHIPPER_JSON_END; // phpcs:ignore
		}
	}

	/**
	 * Check whether we should encode the serialized object or not.
	 *
	 * @since 1.2.8
	 *
	 * @param array $row current row.
	 *
	 * @return bool|void
	 */
	private function should_encode( $row ) {
		$rows = apply_filters(
			'shipper_should_encode_serialized_object',
			array(
				'meta_key' => array( // phpcs:ignore
					'_fl_builder_data', // Beaver builder.
				),
			)
		);

		foreach ( $rows as $key => $value ) {
			if ( isset( $row[ $key ] ) ) {
				return in_array( $row[ $key ], $value, true );
			}
		}
	}

	/**
	 * Check whether the serialized string contains any object or not
	 *
	 * @since 1.2.8
	 *
	 * @param string $serialized_string serialized string.
	 *
	 * @return bool
	 */
	private function is_serialized_object( $serialized_string ) {
		return (bool) preg_match( '/O:[0-9]+:\"[a-zA-Z_]+\"/m', $serialized_string );
	}

	/**
	 * Is it transient
	 *
	 * @param string $row row name.
	 * @param string $table table name.
	 *
	 * @return bool
	 */
	public function is_transient( $row, $table ) {
		$model = new Shipper_Model_Database();
		$name  = $model->get_table_row_name( $row, $table );

		return $name && preg_match( '/^(_site)?_transient/', $name );
	}

	/**
	 * Table rows extractor, append information prior to dump
	 *
	 * @param string $table_name Name of table to export.
	 */
	public function prepare_list_values( $table_name ) {
		if ( $this->dump_settings['single-transaction'] ) {
			$this->db_handler->exec( $this->setup_transaction() );
			$this->db_handler->exec( $this->start_transaction() );
		}

		if ( $this->dump_settings['lock-tables'] && ! $this->dump_settings['single-transaction'] ) {
			$this->lock_table( $table_name );
		}

		if ( $this->dump_settings['add-locks'] ) {
			$this->writer->fwrite(
				$this->start_add_lock_table( $table_name )
			);
		}

		if ( $this->dump_settings['disable-keys'] ) {
			$this->writer->fwrite(
				$this->start_add_disable_keys( $table_name )
			);
		}

		// Disable autocommit for faster reload.
		if ( $this->dump_settings['no-autocommit'] ) {
			$this->writer->fwrite(
				$this->start_disable_autocommit()
			);
		}
	}

	/**
	 * Table rows extractor, close locks and commits after dump
	 *
	 * @param string  $table_name Name of table to export.
	 * @param integer $count     Number of rows inserted.
	 *
	 * @return void
	 */
	public function end_list_values( $table_name, $count = 0 ) {
		if ( $this->dump_settings['disable-keys'] ) {
			$this->writer->fwrite(
				$this->end_add_disable_keys( $table_name )
			);
		}

		if ( $this->dump_settings['add-locks'] ) {
			$this->writer->fwrite(
				$this->end_add_lock_table( $table_name )
			);
		}

		if ( $this->dump_settings['single-transaction'] ) {
			$this->db_handler->exec( $this->commit_transaction() );
		}

		if ( $this->dump_settings['lock-tables'] && ! $this->dump_settings['single-transaction'] ) {
			$this->unlock_table( $table_name );
		}

		// Commit to enable autocommit.
		if ( $this->dump_settings['no-autocommit'] ) {
			$this->writer->fwrite(
				$this->end_disable_autocommit()
			);
		}

		$this->writer->fwrite( PHP_EOL );

		if ( ! $this->dump_settings['skip-comments'] ) {
			$this->writer->fwrite(
				'-- Dumped table `' . $table_name . "` with $count row(s)" . PHP_EOL .
				'--' . PHP_EOL . PHP_EOL
			);
		}
	}

	/**
	 * Build SQL List of all columns on current table which will be used for selecting
	 *
	 * @param string $table_name  Name of table to get columns.
	 *
	 * @return array SQL sentence with columns for select
	 */
	public function get_column_stmt( $table_name ) {
		$col_stmt = array();

		foreach ( $this->table_column_types[ $table_name ] as $col_name => $col_type ) {
			if ( 'bit' === $col_type['type'] && $this->dump_settings['hex-blob'] ) {
				$col_stmt[] = "LPAD(HEX(`${col_name}`),2,'0') AS `${col_name}`";
			} elseif ( $col_type['is_blob'] && $this->dump_settings['hex-blob'] ) {
				$col_stmt[] = "HEX(`${col_name}`) AS `${col_name}`";
			} elseif ( $col_type['is_virtual'] ) {
				$this->dump_settings['complete-insert'] = true;
				continue;
			} else {
				$col_stmt[] = "`${col_name}`";
			}
		}

		return $col_stmt;
	}

	/**
	 * Build SQL List of all columns on current table which will be used for inserting
	 *
	 * @param string $table_name  Name of table to get columns.
	 *
	 * @return array columns for sql sentence for insert.
	 */
	public function get_column_names( $table_name ) {
		$col_names = array();

		foreach ( $this->table_column_types[ $table_name ] as $col_name => $col_type ) {
			if ( $col_type['is_virtual'] ) {
				$this->dump_settings['complete-insert'] = true;
				continue;
			} else {
				$col_names[] = "`${col_name}`";
			}
		}

		return $col_names;
	}

	/**
	 * Database
	 *
	 * @return string
	 *
	 * @throws \Exception Throws exception.
	 */
	public function databases() {
		$this->check_parameters( func_num_args(), $expected_num_args = 1, __METHOD__ );
		$args          = func_get_args();
		$database_name = $args[0];

		$result_set    = $this->db_handler->query( "SHOW VARIABLES LIKE 'character_set_database';" );
		$character_set = $result_set->fetchColumn( 1 );
		$result_set->closeCursor();

		$result_set   = $this->db_handler->query( "SHOW VARIABLES LIKE 'collation_database';" );
		$collation_db = $result_set->fetchColumn( 1 );
		$result_set->closeCursor();
		$ret = '';

		$ret .= "CREATE DATABASE /*!32312 IF NOT EXISTS*/ `${database_name}`" .
			" /*!40100 DEFAULT CHARACTER SET ${character_set} " .
			" COLLATE ${collation_db} */;" . PHP_EOL . PHP_EOL .
			"USE `${database_name}`;" . PHP_EOL . PHP_EOL;

		return $ret;
	}

	/**
	 * Show create table
	 *
	 * @param string $table_name table name.
	 *
	 * @return string
	 */
	public function show_create_table( $table_name ) {
		return "SHOW CREATE TABLE `$table_name`";
	}

	/**
	 * Show create view
	 *
	 * @param string $view_name view name.
	 *
	 * @return string
	 */
	public function show_create_view( $view_name ) {
		return "SHOW CREATE VIEW `$view_name`";
	}

	/**
	 * Create table
	 *
	 * @param string $row row name.
	 *
	 * @return string
	 * @throws \Exception Throws Exception.
	 */
	public function create_table( $row ) {
		if ( ! isset( $row['Create Table'] ) ) {
			throw new Exception( 'Error getting table code, unknown output' );
		}

		$create_table = apply_filters( 'shipper_get_create_table_statement', $row['Create Table'] );

		if ( $this->dump_settings['reset-auto-increment'] ) {
			$match        = '/AUTO_INCREMENT=[0-9]+/s';
			$replace      = '';
			$create_table = preg_replace( $match, $replace, $create_table );
		}

		if ( $this->dump_settings['if-not-exists'] ) {
			$create_table = preg_replace( '/^CREATE TABLE/', 'CREATE TABLE IF NOT EXISTS', $create_table );
		}

		return $create_table . ';' . PHP_EOL;
	}

	/**
	 * Create view
	 *
	 * @param string $row name of the row.
	 *
	 * @return string
	 * @throws \Exception Throws exception.
	 */
	public function create_view( $row ) {
		$ret = '';
		if ( ! isset( $row['Create View'] ) ) {
			throw new Exception( 'Error getting view structure, unknown output' );
		}

		$view_stmt = $row['Create View'];

		$definer_str = $this->dump_settings['skip-definer'] ? '' : '/*!50013 \2 */' . PHP_EOL;

		$view_stmt_replaced = preg_replace(
			'/^(CREATE(?:\s+ALGORITHM=(?:UNDEFINED|MERGE|TEMPTABLE))?)\s+('
			. self::DEFINER_RE . '(?:\s+SQL SECURITY DEFINER|INVOKER)?)?\s+(VIEW .+)$/',
			'/*!50001 \1 */' . PHP_EOL . $definer_str . '/*!50001 \3 */',
			$view_stmt,
			1
		);

		if ( $view_stmt_replaced ) {
			$view_stmt = $view_stmt_replaced;
		};

		$ret .= $view_stmt . ';' . PHP_EOL . PHP_EOL;
		return $ret;
	}

	/**
	 * Show tables
	 *
	 * @return string
	 * @throws \Exception Throws exception.
	 */
	public function show_tables() {
		$this->check_parameters( func_num_args(), $expected_num_args = 1, __METHOD__ );
		$args = func_get_args();

		return 'SELECT TABLE_NAME AS tbl_name ' .
			'FROM INFORMATION_SCHEMA.TABLES ' .
			"WHERE TABLE_TYPE='BASE TABLE' AND TABLE_SCHEMA='${args[0]}'";
	}

	/**
	 * Show views
	 *
	 * @return string
	 * @throws \Exception Throws exception.
	 */
	public function show_views() {
		$this->check_parameters( func_num_args(), $expected_num_args = 1, __METHOD__ );
		$args = func_get_args();

		return 'SELECT TABLE_NAME AS tbl_name ' .
			'FROM INFORMATION_SCHEMA.TABLES ' .
			"WHERE TABLE_TYPE='VIEW' AND TABLE_SCHEMA='${args[0]}'";
	}

	/**
	 * Show columns
	 *
	 * @return string
	 * @throws \Exception Throws exception.
	 */
	public function show_columns() {
		$this->check_parameters( func_num_args(), $expected_num_args = 1, __METHOD__ );
		$args = func_get_args();

		return "SHOW COLUMNS FROM `${args[0]}`;";
	}

	/**
	 * Setup transaction
	 *
	 * @return string
	 */
	public function setup_transaction() {
		return 'SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ';
	}

	/**
	 * Start transaction
	 *
	 * @return string
	 */
	public function start_transaction() {
		return 'START TRANSACTION /*!40100 WITH CONSISTENT SNAPSHOT */';
	}

	/**
	 * Commit transaction
	 *
	 * @return string
	 */
	public function commit_transaction() {
		return 'COMMIT';
	}

	/**
	 * Lock table
	 *
	 * @return mixed
	 * @throws \Exception Throws exception.
	 */
	public function lock_table() {
		$this->check_parameters( func_num_args(), $expected_num_args = 1, __METHOD__ );
		$args = func_get_args();

		return $this->db_handler->exec( "LOCK TABLES `${args[0]}` READ LOCAL" );
	}

	/**
	 * Unlock table
	 *
	 * @return mixed
	 */
	public function unlock_table() {
		return $this->db_handler->exec( 'UNLOCK TABLES' );
	}

	/**
	 * Start add lock table.
	 *
	 * @return string
	 * @throws \Exception Throws exception.
	 */
	public function start_add_lock_table() {
		$this->check_parameters( func_num_args(), $expected_num_args = 1, __METHOD__ );
		$args = func_get_args();

		return "LOCK TABLES `${args[0]}` WRITE;" . PHP_EOL;
	}

	/**
	 * End lock table.
	 *
	 * @return string
	 */
	public function end_add_lock_table() {
		return 'UNLOCK TABLES;' . PHP_EOL;
	}

	/**
	 * Start add disable keys
	 *
	 * @return string
	 * @throws \Exception Throws Exception.
	 */
	public function start_add_disable_keys() {
		$this->check_parameters( func_num_args(), $expected_num_args = 1, __METHOD__ );
		$args = func_get_args();

		return "ALTER TABLE `${args[0]}` DISABLE KEYS;" . PHP_EOL;
	}

	/**
	 * End add disable keys
	 *
	 * @return string
	 * @throws \Exception Throws Exception.
	 */
	public function end_add_disable_keys() {
		$this->check_parameters( func_num_args(), $expected_num_args = 1, __METHOD__ );
		$args = func_get_args();

		return "ALTER TABLE `${args[0]}` ENABLE KEYS;" . PHP_EOL;
	}

	/**
	 * Start disable autocommit
	 *
	 * @return string
	 */
	public function start_disable_autocommit() {
		return 'SET autocommit=0;' . PHP_EOL;
	}

	/**
	 * End disable autocommit
	 *
	 * @return string
	 */
	public function end_disable_autocommit() {
		return 'COMMIT;' . PHP_EOL;
	}

	/**
	 * Add drop database
	 *
	 * @return string
	 * @throws \Exception Throws Exception.
	 */
	public function add_drop_database() {
		$this->check_parameters( func_num_args(), $expected_num_args = 1, __METHOD__ );
		$args = func_get_args();

		return "/*!40000 DROP DATABASE IF EXISTS `${args[0]}`*/;" . PHP_EOL . PHP_EOL;
	}

	/**
	 * Add drop trigger
	 *
	 * @return string
	 * @throws \Exception Throws Exception.
	 */
	public function add_drop_trigger() {
		$this->check_parameters( func_num_args(), $expected_num_args = 1, __METHOD__ );
		$args = func_get_args();

		return "DROP TRIGGER IF EXISTS `${args[0]}`;" . PHP_EOL;
	}

	/**
	 * Drop tables.
	 *
	 * @param string $table_name name of the table.
	 *
	 * @return string
	 */
	public function drop_table( $table_name ) {
		$table_name = apply_filters( 'shipper_get_drop_table_name', $table_name );

		return "DROP TABLE IF EXISTS `$table_name` ;" . PHP_EOL;
	}

	/**
	 * Drop views
	 *
	 * @return string
	 * @throws \Exception Throws Exception.
	 */
	public function drop_view() {
		$this->check_parameters( func_num_args(), $expected_num_args = 1, __METHOD__ );
		$args = func_get_args();

		return "DROP TABLE IF EXISTS `${args[0]}`;" . PHP_EOL . "/*!50001 DROP VIEW IF EXISTS `${args[0]}`*/;" . PHP_EOL;
	}

	/**
	 * Get database header
	 *
	 * @return string
	 * @throws \Exception Throws Exception.
	 */
	public function get_database_header() {
		$this->check_parameters( func_num_args(), $expected_num_args = 1, __METHOD__ );
		$args = func_get_args();

		return '--' . PHP_EOL .
			"-- Current Database: `${args[0]}`" . PHP_EOL .
			'--' . PHP_EOL . PHP_EOL;
	}

	/**
	 * Decode column metadata and fill info structure.
	 * type, is_numeric and is_blob will always be available.
	 *
	 * @param array $col_type Array returned from "SHOW COLUMNS FROM table_name".
	 * @return array
	 */
	public function parse_column_type( $col_type ) {
		$col_info  = array();
		$col_parts = explode( ' ', $col_type['Type'] );

		$fparen = strpos( $col_parts[0], '(' );

		if ( $fparen ) {
			$col_info['type']       = substr( $col_parts[0], 0, $fparen );
			$col_info['length']     = str_replace( ')', '', substr( $col_parts[0], $fparen + 1 ) );
			$col_info['attributes'] = isset( $col_parts[1] ) ? $col_parts[1] : null;
		} else {
			$col_info['type'] = $col_parts[0];
		}

		$col_info['is_numeric'] = in_array( $col_info['type'], $this->mysql_types['numerical'], true );
		$col_info['is_blob']    = in_array( $col_info['type'], $this->mysql_types['blob'], true );

		// for virtual columns that are of type 'Extra', column type.
		// could by "STORED GENERATED" or "VIRTUAL GENERATED".
		// MySQL reference: https://dev.mysql.com/doc/refman/5.7/en/create-table-generated-columns.html.
		$col_info['is_virtual'] = strpos( $col_type['Extra'], 'VIRTUAL GENERATED' ) !== false || strpos( $col_type['Extra'], 'STORED GENERATED' ) !== false;

		return $col_info;
	}

	/**
	 * Check number of parameters passed to function, useful when inheriting.
	 * Raise exception if unexpected.
	 *
	 * @param integer $num_args number of args.
	 * @param integer $expected_num_args expected nargs.
	 * @param string  $method_name method name.
	 *
	 * @throws \Exception Throws exception.
	 */
	private function check_parameters( $num_args, $expected_num_args, $method_name ) {
		if ( $num_args !== $expected_num_args ) {
			throw new Exception( "Unexpected parameter passed to $method_name" );
		}
	}
}