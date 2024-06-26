<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ES_DB base class
 *
 * @since 4.0
 */
abstract class ES_DB {

	/**
	 * Table name
	 *
	 * @since 4.0.0
	 * @var $table_name
	 */
	public $table_name;

	/**
	 * Table DB version
	 *
	 * @since 4.0.0
	 * @var $version
	 */
	public $version;

	/**
	 * Table primary key column name
	 *
	 * @since 4.0.0
	 * @var $primary_key
	 */
	public $primary_key;

	/**
	 * ES_DB constructor.
	 *
	 * @since 4.0.0
	 */
	public function __construct() {
	}

	/**
	 * Get default columns
	 *
	 * @return array
	 *
	 * @since 4.0.0
	 */
	public function get_columns() {
		return array();
	}

	/**
	 * Get columns default values
	 *
	 * @return array
	 *
	 * @since 4.0.0
	 */
	public function get_column_defaults() {
		return array();
	}

	/**
	 * Retrieve a row by the primary key
	 *
	 * @param int    $row_id
	 * @param string $output
	 * @param false  $use_cache
	 *
	 * @return false|mixed
	 *
	 * @since 4.0.0
	 *
	 * @modified 4.7.3 Added support to retrieve data from cache
	 */
	public function get( $row_id = 0, $output = ARRAY_A, $use_cache = false ) {

		global $wpbd;

		$query = $wpbd->prepare( "SELECT * FROM $this->table_name WHERE $this->primary_key = %s LIMIT 1;", $row_id );

		if ( true === $use_cache ) {
			// Considering $output also while considering key generation
			$cache_key = $this->generate_cache_key( $query . $output );

			$found = false;

			$result = $this->get_cache( $cache_key, $found );
			if ( ! $found ) {
				$result = $wpbd->get_row( $query, $output );
				$this->set_cache( $cache_key, $result );
			}
		} else {
			$result = $wpbd->get_row( $query, $output );
		}

		return $result;
	}

	/**
	 * Retrieve a row by a specific column / value
	 *
	 * @param $column
	 * @param $row_id
	 * @param string $output
	 * @param false  $use_cache
	 *
	 * @return false|mixed
	 *
	 * @since 4.0.0
	 *
	 * @modified 4.7.3 Added support to retrieve data from cache
	 */
	public function get_by( $column, $row_id, $output = ARRAY_A, $use_cache = false ) {
		global $wpbd;
		$column = esc_sql( $column );

		$query = $wpbd->prepare( "SELECT * FROM $this->table_name WHERE $column = %s LIMIT 1;", $row_id );

		if ( true === $use_cache ) {

			// Consider $output also while generating cache key
			$cache_key = $this->generate_cache_key( $query . $output );

			$found = false;

			$result = $this->get_cache( $cache_key, $found );

			if ( ! $found ) {
				$result = $wpbd->get_row( $query, $output );
				$this->set_cache( $cache_key, $result );
			}
		} else {
			$result = $wpbd->get_row( $query, $output );
		}

		return $result;
	}

	/**
	 * Get rows by conditions
	 *
	 * @param string $where
	 * @param string $output
	 * @param false  $use_cache
	 *
	 * @return false|mixed
	 *
	 * @since 4.2.4
	 *
	 * @modfiy 4.7.3 Added support to retrieve data from cache
	 */
	public function get_by_conditions( $where = '', $output = ARRAY_A, $use_cache = false, $order_by_column = '', $order = '' ) {
		global $wpbd;

		$query = "SELECT * FROM $this->table_name";

		if ( ! empty( $where ) ) {
			$query .= " WHERE $where";
		}

		if ( ! empty( $order_by_column ) ) {
			$query .= ' ORDER BY ' . esc_sql( $order_by_column );
			if ( ! empty( $order ) ) {
				$query .= ' ' . esc_sql( $order );
			}
		}

		if ( true === $use_cache ) {

			// Consider $output also while generating cache key
			$cache_key = $this->generate_cache_key( $query . $output );

			$found = false;

			$result = $this->get_cache( $cache_key, $found );

			if ( ! $found ) {
				$result = $wpbd->get_results( $query, $output );

				$this->set_cache( $cache_key, $result );
			}
		} else {
			$result = $wpbd->get_results( $query, $output );
		}

		return $result;
	}

	/**
	 * Get all data from table without any condition
	 *
	 * @return array|object|null
	 *
	 * @since 4.3.1
	 */
	public function get_all() {
		return $this->get_by_conditions();
	}

	/**
	 * Retrieve a specific column's value by the primary key
	 *
	 * @param string $column
	 * @param int    $row_id
	 * @param bool   $use_cache
	 *
	 * @return null|string|array
	 *
	 * @since 4.0.0
	 *
	 * @since 4.7.3
	 */
	public function get_column( $column = '', $row_id = 0, $use_cache = false ) {
		global $wpbd;

		$column = esc_sql( $column );

		if ( $row_id ) {
			$query = $wpbd->prepare( "SELECT $column FROM $this->table_name WHERE $this->primary_key = %s LIMIT 1;", $row_id );
		} else {
			$query = "SELECT $column FROM $this->table_name";
		}

		if ( true === $use_cache ) {

			$cache_key = $this->generate_cache_key( $query );

			$found = false;

			$result = $this->get_cache( $cache_key, $found );

			if ( ! $found ) {
				if ( $row_id ) {
					$result = $wpbd->get_var( $query );
				} else {
					$result = $wpbd->get_col( $query );
				}

				$this->set_cache( $cache_key, $result );
			}
		} else {

			if ( $row_id ) {
				$result = $wpbd->get_var( $query );
			} else {
				$result = $wpbd->get_col( $query );
			}
		}

		return $result;
	}

	/**
	 * Retrieve a specific column's value by the the specified column / value
	 *
	 * @param string $column
	 * @param string $column_where
	 * @param string $column_value
	 * @param bool   $only_one
	 * @param bool   $use_cache
	 *
	 * @return array|string|null
	 *
	 * @since 4.0.0
	 *
	 * @modified  4.3.4 Added support to retrieve whole column
	 *
	 * @modified  4.7.3 Added support to retrieve data from cache
	 */
	public function get_column_by( $column = '', $column_where = '', $column_value = '', $only_one = true, $use_cache = false ) {
		global $wpbd;

		$column_where = esc_sql( $column_where );

		$column = esc_sql( $column );

		if ( $only_one ) {
			$query = $wpbd->prepare( "SELECT $column FROM $this->table_name WHERE $column_where = %s LIMIT 1;", $column_value );
		} else {
			$query = $wpbd->prepare( "SELECT $column FROM $this->table_name WHERE $column_where = %s;", $column_value );
		}

		if ( true === $use_cache ) {

			$cache_key = $this->generate_cache_key( $query );

			$found = false;

			$result = $this->get_cache( $cache_key, $found );

			if ( ! $found ) {

				if ( $only_one ) {
					$result = $wpbd->get_var( $query );
				} else {
					$result = $wpbd->get_col( $query );
				}

				$this->set_cache( $cache_key, $result );
			}
		} else {

			if ( $only_one ) {
				$result = $wpbd->get_var( $query );
			} else {
				$result = $wpbd->get_col( $query );
			}
		}

		return $result;
	}

	/**
	 * Get column based on where condition
	 *
	 * @param string $column
	 * @param string $where
	 * @param bool   $use_cache
	 *
	 * @return array
	 *
	 * @since 4.3.5
	 *
	 * @modified 4.7.3 Added support to retrieve data from cache
	 */
	public function get_column_by_condition( $column = '', $where = '', $use_cache = false ) {
		global $wpbd;

		$column = esc_sql( $column );

		$query = "SELECT $column FROM $this->table_name";
		if ( ! empty( $where ) ) {
			$query .= " WHERE $where";
		}

		if ( true === $use_cache ) {

			$cache_key = $this->generate_cache_key( $query );

			$found = false;

			$result = $this->get_cache( $cache_key, $found );

			if ( ! $found ) {
				$result = $wpbd->get_col( $query );

				$this->set_cache( $cache_key, $result );
			}
		} else {
			$result = $wpbd->get_col( $query );
		}

		return $result;
	}

	/**
	 * Select few columns based on condition
	 *
	 * @param array  $columns
	 * @param string $where
	 * @param string $output
	 * @param bool   $use_cache
	 *
	 * @return array|object|null
	 *
	 * @since 4.3.5
	 *
	 * @modified 4.7.3 Added support to retrieve data from cache
	 */
	public function get_columns_by_condition( $columns = array(), $where = '', $output = ARRAY_A, $use_cache = false ) {
		global $wpbd;

		if ( ! is_array( $columns ) ) {
			return array();
		}

		$columns = esc_sql( $columns );

		$columns = implode( ', ', $columns );

		$query = "SELECT $columns FROM $this->table_name";
		if ( ! empty( $where ) ) {
			$query .= " WHERE $where";
		}

		if ( true === $use_cache ) {
			// Consider $output also while generating cache key
			$cache_key = $this->generate_cache_key( $query . $output );

			$found = false;

			$result = $this->get_cache( $cache_key, $found );

			if ( ! $found ) {
				$result = $wpbd->get_results( $query, $output );

				$this->set_cache( $cache_key, $result );
			}
		} else {
			$result = $wpbd->get_results( $query, $output );
		}

		return $result;
	}

	/**
	 * Insert a new row
	 *
	 * @param $data
	 * @param string $type
	 *
	 * @return int
	 *
	 * @since 4.0.0
	 */
	public function insert( $data, $type = '' ) {
		global $wpdb;

		// Set default values
		$data = wp_parse_args( $data, $this->get_column_defaults() );

		do_action( 'ig_es_pre_insert_' . $type, $data );

		// Initialise column format array
		$column_formats = $this->get_columns();

		// Force fields to lower case
		$data = array_change_key_case( $data );

		// White list columns
		$data = array_intersect_key( $data, $column_formats );

		// Reorder $column_formats to match the order of columns given in $data
		$data_keys      = array_keys( $data );
		$column_formats = array_merge( array_flip( $data_keys ), $column_formats );
// phpcs:disable
		$wpdb->insert( $this->table_name, $data, $column_formats );
		// phpcs:enable
		$wpdb_insert_id = $wpdb->insert_id;

		do_action( 'ig_es_post_insert_' . $type, $wpdb_insert_id, $data );

		return $wpdb_insert_id;
	}

	/**
	 * Update a specific row
	 *
	 * @param $row_id
	 * @param array  $data
	 * @param string $where
	 *
	 * @return bool
	 *
	 * @since 4.0.0
	 */
	public function update( $row_id, $data = array(), $where = '' ) {

		global $wpdb;

		// Row ID must be positive integer
		$row_id = absint( $row_id );

		if ( empty( $row_id ) ) {
			return false;
		}

		if ( empty( $where ) ) {
			$where = $this->primary_key;
		}

		// Initialise column format array
		$column_formats = $this->get_columns();

		// Force fields to lower case
		$data = array_change_key_case( $data );

		// White list columns
		$data = array_intersect_key( $data, $column_formats );

		// Reorder $column_formats to match the order of columns given in $data
		$data_keys      = array_keys( $data );
		$column_formats = array_merge( array_flip( $data_keys ), $column_formats );
// phpcs:disable
		if ( false === $wpdb->update( $this->table_name, $data, array( $where => $row_id ), $column_formats ) ) {
			return false;
		}
// phpcs:enable
		return true;
	}

	/**
	 * Delete a row by primary key
	 *
	 * @param int $row_id
	 *
	 * @return bool
	 *
	 * @since 4.0.0
	 */
	public function delete( $row_id = 0 ) {

		global $wpbd;

		// Row ID must be positive integer
		$row_id = absint( $row_id );

		if ( empty( $row_id ) ) {
			return false;
		}

		$where = $wpbd->prepare( "$this->primary_key = %d", $row_id );

		if ( false === $this->delete_by_condition( $where ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Delete rows by primary key
	 *
	 * @param array $row_ids
	 *
	 * @return bool
	 *
	 * @since 4.3.4
	 */
	public function bulk_delete( $row_ids = array() ) {

		if ( ! is_array( $row_ids ) && empty( $row_ids ) ) {
			return false;
		}

		$row_ids_str = $this->prepare_for_in_query( $row_ids );

		$where = "$this->primary_key IN( $row_ids_str )";

		if ( false === $this->delete_by_condition( $where ) ) {
			return false;
		}

		return true;
	}


	/**
	 * Delete records based on $where
	 *
	 * @param string $where
	 *
	 * @return bool
	 *
	 * @since 4.2.4
	 */
	public function delete_by_condition( $where = '' ) {
		global $wpbd;

		if ( empty( $where ) ) {
			return false;
		}

		$query = "DELETE FROM $this->table_name WHERE $where";
		if ( false === $wpbd->query( "DELETE FROM $this->table_name WHERE $where" ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Check whether table exists or not
	 *
	 * @param $table
	 *
	 * @return bool
	 *
	 * @since 4.0.0
	 */
	public function table_exists( $table ) {
		global $wpdb;
		$table = sanitize_text_field( $table );
// phpcs:disable
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
// phpcs:enable
	}

	/**
	 * Check whether table installed
	 *
	 * @return bool
	 *
	 * @since 4.0.0
	 */
	public function installed() {
		return $this->table_exists( $this->table_name );
	}

	/**
	 * Get total count
	 *
	 * @param string $where
	 * @param bool   $use_cache
	 *
	 * @return string|null
	 *
	 * @since 4.2.1
	 *
	 * @modified 4.7.3 Added support to retrieve data from cache
	 */
	public function count( $where = '', $use_cache = false ) {
		global $wpbd;

		$query = "SELECT count(*) FROM $this->table_name";

		if ( ! empty( $where ) ) {
			$query .= " WHERE $where";
		}

		if ( true === $use_cache ) {

			$cache_key = $this->generate_cache_key( $query );

			$found = false;

			$result = $this->get_cache( $cache_key, $found );

			if ( ! $found ) {
				$result = $wpbd->get_var( $query );

				$this->set_cache( $cache_key, $result );
			}
		} else {
			$result = $wpbd->get_var( $query );
		}

		return $result;
	}

	/**
	 * Insert data into bulk
	 *
	 * @param array $values
	 * @param int   $length
	 * @param bool  $return_insert_ids
	 *
	 * @since 4.2.1
	 *
	 * @since 4.3.5 Fixed issues and started using it.
	 */
	public function bulk_insert( $values = array(), $length = 100, $return_insert_ids = false ) {
		global $wpbd;

		if ( ! is_array( $values ) ) {
			return false;
		}

		// Get the first value from an array to check data structure
		$first_value = array_slice( $values, 0, 1 );

		$data = array_shift( $first_value );

		// Set default values
		$data = wp_parse_args( $data, $this->get_column_defaults() );

		// Initialise column format array
		$column_formats = $this->get_columns();

		// Remove primary key as we don't require while inserting data
		unset( $column_formats[ $this->primary_key ] );

		// Force fields to lower case
		$data = array_change_key_case( $data );

		// White list columns
		$data = array_intersect_key( $data, $column_formats );

		// Reorder $column_formats to match the order of columns given in $data
		$data = wp_parse_args( $data, $this->get_column_defaults() );

		$data_keys = array_keys( $data );

		$fields = array_keys( array_merge( array_flip( $data_keys ), $column_formats ) );

		// Convert Batches into smaller chunk
		$batches = array_chunk( $values, $length );

		$error_flag = false;

		// Holds first and last row ids of each batch insert
		$bulk_rows_start_end_ids = [];

		foreach ( $batches as $key => $batch ) {

			$place_holders = array();
			$final_values  = array();
			$fields_str    = '';

			foreach ( $batch as $value ) {

				$formats = array();
				foreach ( $column_formats as $column => $format ) {
					$final_values[] = isset( $value[ $column ] ) ? $value[ $column ] : $data[ $column ]; // set default if we don't have
					$formats[]      = $format;
				}

				$place_holders[] = '( ' . implode( ', ', $formats ) . ' )';
				$fields_str      = '`' . implode( '`, `', $fields ) . '`';
			}

			$query  = "INSERT INTO $this->table_name ({$fields_str}) VALUES ";
			$query .= implode( ', ', $place_holders );
			$sql    = $wpbd->prepare( $query, $final_values );

			if ( ! $wpbd->query( $sql ) ) {
				$error_flag = true;
			} else {
				$start_id = $wpbd->insert_id;
				$end_id   = ( $start_id - 1 ) + count( $batch );
				array_push( $bulk_rows_start_end_ids, $start_id );
				array_push( $bulk_rows_start_end_ids, $end_id );
			}
		}

		if ( $return_insert_ids && count( $bulk_rows_start_end_ids ) > 0 ) {
			return array( min( $bulk_rows_start_end_ids ), max( $bulk_rows_start_end_ids ) );
		}

		// Check if error occured during executing the query.
		if ( $error_flag ) {
			return false;
		}

		return true;
	}

	/**
	 * Update data into bulk
	 *
	 * @param array $values
	 * @param int   $length
	 * 
	 */
	public function bulk_update( $values = array(), $length = 100 ) {
		global $wpbd;

		if ( ! is_array( $values ) ) {
			return false;
		}

		// Get the first value from an array to check data structure
		$first_value = array_slice( $values, 0, 1 );

		$data = array_shift( $first_value );

		// Set default values
		$data = wp_parse_args( $data, $this->get_column_defaults() );

		// Initialise column format array
		$column_formats = $this->get_columns();

		// Remove primary key as we don't require while inserting data
		unset( $column_formats[ $this->primary_key ] );

		// Convert Batches into smaller chunk
		$batches = array_chunk( $values, $length );

		foreach ( $batches as $key => $batch ) {
			
			$formats = array();
			$cases   = array();
			foreach ( $column_formats as $column => $format ) {

				$formats[] = $format;

				$when = array();				

				foreach ( $batch as $value ) {	
					if ( empty( $value[$column] ) ) {
						continue;
					}
					$when[] = $wpbd->prepare( ' WHEN email = "' . $value['email'] . '" THEN ' . "$format", $value[$column] );
					$emails[]    = $value['email'];		
				}

				if ( !empty( $when ) ) {
					$case = $column . '=(CASE ' . implode( ' ', $when ) . ' END)';
					$cases[] = $case;
				}
				
			}					

			$query  = "UPDATE {$wpbd->prefix}ig_contacts SET " . implode( ' , ', $cases );
			$query .= 'WHERE email IN( \'' . implode( '\',\'', $emails ) . '\')';			
			$result = $wpbd->query( $query );

			return $result;
			
		}

	}

	/**
	 * Bulk insert data into given table
	 *
	 * @param $table_name
	 * @param $fields
	 * @param $place_holders
	 * @param $values
	 *
	 * @return bool
	 */
	public static function do_insert( $table_name, $fields, $place_holders, $values ) {
		global $wpbd;

		$fields_str = '`' . implode( '`, `', $fields ) . '`';

		$query  = "INSERT INTO $table_name ({$fields_str}) VALUES ";
		$query .= implode( ', ', $place_holders );
		$sql    = $wpbd->prepare( $query, $values );

		if ( $wpbd->query( $sql ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Get ID, Name Map
	 *
	 * @param string $where
	 *
	 * @return array
	 *
	 * @since 4.2.2
	 * @since 4.3.5 Used get_columns_map method
	 */
	public function get_id_name_map( $where = '' ) {
		return $this->get_columns_map( $this->primary_key, 'name', $where );
	}

	/**
	 * Get map of two columns
	 *
	 * E.g array($column_1 => $column_2)
	 *
	 * @param string $column_1
	 * @param string $column_2
	 * @param string $where
	 *
	 * @return array
	 *
	 * @since 4.3.5
	 */
	public function get_columns_map( $column_1 = '', $column_2 = '', $where = '' ) {
		if ( empty( $column_1 ) || empty( $column_2 ) ) {
			return array();
		}

		$columns = array( $column_1, $column_2 );

		$results = $this->get_columns_by_condition( $columns, $where );

		$map = array();
		if ( count( $results ) > 0 ) {
			foreach ( $results as $result ) {
				$map[ $result[ $column_1 ] ] = $result[ $column_2 ];
			}
		}

		return $map;
	}

	public static function prepare_data( $data, $column_formats, $column_defaults, $insert = true ) {

		// Set default values
		if ( $insert ) {
			$data = wp_parse_args( $data, $column_defaults );
		}

		// Force fields to lower case
		$data = array_change_key_case( $data );

		// White list columns
		$data = array_intersect_key( $data, $column_formats );

		// Reorder $column_formats to match the order of columns given in $data
		$data_keys      = array_keys( $data );
		$column_formats = array_merge( array_flip( $data_keys ), $column_formats );

		return array(
			'data'           => $data,
			'column_formats' => $column_formats,
		);

	}

	/**
	 * Prepare string for SQL IN query
	 *
	 * @param array $array
	 *
	 * @return string
	 *
	 * @since 4.3.4
	 */
	public function prepare_for_in_query( $array = array() ) {

		$array = esc_sql( $array );

		if ( is_array( $array ) && count( $array ) > 0 ) {
			return "'" . implode( "', '", $array ) . "'";
		}

		return '';
	}

	/**
	 * Generate Cache key
	 *
	 * @param string $str
	 *
	 * @return bool|string
	 *
	 * @since 4.7.2
	 */
	public function generate_cache_key( $str = '' ) {
		return ES_Cache::generate_key( $str );
	}

	/**
	 * Get data from cache
	 *
	 * @param $key
	 * @param null $found
	 *
	 * @return false|mixed
	 *
	 * @since 4.7.2
	 */
	public function get_cache( $key, &$found = null ) {
		return ES_Cache::get( $key, 'query', false, $found );
	}

	/**
	 * Set data into cache
	 *
	 * @param string $key
	 * @param string $value
	 *
	 * @since 4.7.2
	 */
	public function set_cache( $key = '', $value = '' ) {
		if ( ! empty( $key ) ) {
			ES_Cache::set( $key, $value, 'query' );
		}
	}

	/**
	 * Check if cache exists?
	 *
	 * @param string $key
	 *
	 * @return bool
	 *
	 * @since 4.7.2
	 */
	public function cache_exists( $key = '' ) {
		return ES_Cache::is_exists( $key, 'query' );
	}

}
