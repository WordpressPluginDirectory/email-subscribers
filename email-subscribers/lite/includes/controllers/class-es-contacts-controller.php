<?php

if ( ! class_exists( 'ES_Contacts_Controller' ) ) {

	/**
	 * Class to handle single form operation
	 * 
	 * @class ES_Contacts_Controller
	 */
	class ES_Contacts_Controller {

		// class instance
		public static $instance;

		// class constructor
		public function __construct() {
			$this->init();
		}

		public static function get_instance() {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		public function init() {
			$this->register_hooks();
		}

		public function register_hooks() {
		}

		/**
		 * Retrieve subscribers data from the database
		 *
		 * @param int $per_page
		 * @param int $page_number
		 *
		 * @return mixed
		 */
		public static function get_subscribers( $contact_args ) {

			if ( is_string( $contact_args ) ) {
				$decoded = json_decode( $contact_args, true );
				if ( $decoded ) {
					$contact_args = $decoded;
				}
			}

			$order_by     = isset( $contact_args['order_by'] ) ? sanitize_text_field( $contact_args['order_by'] ) : 'created_at';
			$order        = isset( $contact_args['order'] ) ? strtoupper( $contact_args['order'] ) : 'DESC';
			$search       = isset( $contact_args['search'] ) ? sanitize_text_field( $contact_args['search'] ) : '';
			$per_page     = isset( $contact_args['per_page'] ) ? (int) $contact_args['per_page'] : 5;
			$page_number  = isset( $contact_args['page_number'] ) ? (int) $contact_args['page_number'] : 1;
			$do_count_only = ! empty( $contact_args['do_count_only'] );
			$filter_by_list_id = isset( $contact_args['filter_by_list_id'] ) ? $contact_args['filter_by_list_id'] : '';			
			$advanced_filter = isset( $contact_args['advanced_filter'] ) ? $contact_args['advanced_filter'] : ''; 
			$all_contacts = isset( $contact_args['all_contacts'] ) ? $contact_args['all_contacts'] : false;

			if ($search === 'none') {
				$search = '';
			}
			
			$list_filters = array();
    		$other_filters = array();
    
			if ( is_array( $advanced_filter ) && ! empty( $advanced_filter ) ) {

				$advanced_filter_conditions = array();
				
				foreach ( $contact_args['advanced_filter'] as $filter ) {

					if ( $filter['field'] === 'List' ) {
						$list_filters[] = $filter;
						
					} else {
						$other_filters[] = $filter; 
					}
				}
				
			}

			// Process non-List advanced filters
			$advanced_filter_conditions = array();
			if ( ! empty( $other_filters ) ) {
				foreach ( $other_filters as $filter ) {
					$field = sanitize_text_field( $filter['field'] );
					$operator = sanitize_text_field( $filter['operator'] );
					$value = $filter['value'];
					
					// Convert frontend filters to ES advanced filter format
					$condition = self::build_es_filter_condition( $field, $operator, $value );
					if ( $condition ) {
						$advanced_filter_conditions[] = $condition; 
					}
				}
				 
			}

			// Use ES DB classes instead of direct queries
			$contacts_db = ES()->contacts_db;
			$lists_contacts_db = ES()->lists_contacts_db;

			// Build query arguments for ES DB classes
			$query_args = array(
				'order_by' => $order_by,
				'order' => $order,
				'per_page' => $per_page,
				'page_number' => $page_number,
				'search' => $search,
				'do_count_only' => $do_count_only,
				'all_contacts' => $all_contacts,
			);

			
			// Handle advanced filtering using ES Subscribers Query
			if ( ! empty( $advanced_filter ) ) {
				
				// Handle non-List advanced filtering using direct SQL
				$filtered_contact_ids = array();
				if ( ! empty( $advanced_filter_conditions ) ) {
					$filtered_contact_ids = self::get_filtered_contact_ids_direct( $advanced_filter_conditions );

					if ( empty( $filtered_contact_ids ) ) {
						return $do_count_only ? 0 : array();
					}
				}

				// Handle List filtering (from advanced_filter and filter_by_list_id)
				$all_list_ids = array();
				
				// Add List filters from advanced_filter
				if ( ! empty( $list_filters ) ) {
					foreach ( $list_filters as $list_filter ) {
						if ( is_array( $list_filter['value'] ) ) {
							$all_list_ids = array_merge( $all_list_ids, $list_filter['value'] );
						} else {
							$all_list_ids[] = $list_filter['value'];
						}
					}
				}
				
				// Add filter_by_list_id if present
				if ( ! empty( $filter_by_list_id ) && $filter_by_list_id !== 'all' ) {
					$all_list_ids[] = intval( $filter_by_list_id );
				}

				// Apply list filtering
				if ( ! empty( $all_list_ids ) ) {
					$list_ids = array_unique( array_map( 'intval', $all_list_ids ) );
					
					// Get contact IDs from lists_contacts table
					$list_filtered_contact_ids = self::get_contact_ids_by_lists( $list_ids );

					if ( ! empty( $filtered_contact_ids ) ) {
						// Intersect with existing contact IDs from advanced filters
						$before_count = count( $filtered_contact_ids );
						$filtered_contact_ids = array_intersect( $filtered_contact_ids, $list_filtered_contact_ids );
						$after_count = count( $filtered_contact_ids );
					} else {
						// Only list filtering
						$filtered_contact_ids = $list_filtered_contact_ids;
					}
					
					if ( empty( $filtered_contact_ids ) ) {
						return $do_count_only ? 0 : array();
					}
				}

				// Add filtered contact IDs to query args
				if ( ! empty( $filtered_contact_ids ) ) {
					$query_args['contact_ids'] = $filtered_contact_ids;
				}

			} else if ( ! empty( $filter_by_list_id ) ) {
				// this is for direct list filter without advanced filter
				$list_contact_args = array();
				
				if ( ! empty( $filter_by_list_id ) && $filter_by_list_id !== 'all' ) {
					$list_contact_args['list_id'] = intval( $filter_by_list_id );
				} 
			
				// Get contact IDs from lists_contacts table
				$filtered_contact_ids = $lists_contacts_db->get_contact_ids_by_criteria( $list_contact_args );
				 
				if ( isset( $query_args['contact_ids'] ) ) {
					// Intersect with existing contact IDs from advanced filter
					$query_args['contact_ids'] = array_intersect( $query_args['contact_ids'], $filtered_contact_ids );
				} else {
					$query_args['contact_ids'] = $filtered_contact_ids;
				}
				 
			}  
  
			if ( $do_count_only ) { 
				$result = $contacts_db->get_filtered_contacts_count( $query_args );
			} else { 
 
				$result = $contacts_db->get_filtered_contacts( $query_args );
    
				if ( ! empty( $result ) ) {
					foreach ( $result as &$contact ) {
						$list_statuses = $lists_contacts_db->get_list_statuses_by_contact_id( $contact['id'] );
						
						if ( ! empty( $list_statuses ) ) {
							$contact['list_statuses'] = $list_statuses;
							$contact['lists'] = array_map(
								function ( $item ) {
									return isset( $item['list_name'] ) ? $item['list_name'] : null;
								},
								$list_statuses
							);

 							$contact['lists'] = array_filter( $contact['lists'] );
						} else {
							$contact['list_statuses'] = array();
							$contact['lists'] = array();
						}
					}
				}
			}

			return $result;
		}


		/**
		 * Delete a contact by ID 
		 *
		 * @param array $args Arguments containing 'id'
		 * @return array
		 */
		public static function delete_contact( $args = array() ) {

			if ( is_string( $args ) ) {
				$decoded = json_decode( $args, true );
				if ( $decoded ) {
					$args = $decoded;
				}
			}

			$response = array( 'success' => false, 'message' => '' );

 			if ( empty( $args['id'] ) ) {
				$response['message'] = __( 'Contact ID is required.', 'email-subscribers' );
				return $response;
			}

			$contact_ids = array();

 			if ( is_numeric( $args['id'] ) ) {
				$contact_ids[] = intval( $args['id'] ); 
			} elseif ( is_array( $args['id'] ) ) {  
				$contact_ids = array_map( 'intval', $args['id'] );
			}

			if ( empty( $contact_ids ) ) {
				$response['message'] = __( 'No valid contact IDs provided.', 'email-subscribers' );
				return $response;
			} 

			$result = ES()->contacts_db->delete_contacts_by_ids(  $contact_ids  );

			if ( $result ) {
				$response['success'] = true;
				$response['message'] = __( 'Contact deleted successfully.', 'email-subscribers' );
			} else {
				$response['message'] = __( 'Failed to delete contact.', 'email-subscribers' );
			}

			return $response;
		}

		/**
		 * Update a contact
		 *
		 * @param array $args Arguments containing contact data
		 * @return array
		 */
		public static function update_contact( $args = array() ) {
			
			if ( is_string( $args ) ) {
				$decoded = json_decode( $args, true );
				if ( $decoded ) {
					$args = $decoded;
				}
			}

			$response = array( 'success' => false, 'message' => '' );

 			if ( empty( $args['id'] ) ) {
				$response['message'] = __( 'Contact ID is required.', 'email-subscribers' );
				return $response;
			}

			$contact_id = intval( $args['id'] );
			$contact_data = array();

 			if ( isset( $args['first_name'] ) ) {
				$contact_data['first_name'] = sanitize_text_field( $args['first_name'] );
			}
			if ( isset( $args['last_name'] ) ) {
				$contact_data['last_name'] = sanitize_text_field( $args['last_name'] );
			}
			if ( isset( $args['email'] ) ) {
				$contact_data['email'] = sanitize_email( $args['email'] );
			} 

			$contact_data['id'] = $contact_id;
			unset($args['lists']);

			$converted = array();
			if( isset($args['list_statuses']) && is_array($args['list_statuses']) ) {
				foreach ($args['list_statuses'] as $item) {
					$converted[$item['id']] = $item['status'];
				} 
			}
			$args['lists'] = $converted;

			try {
				  
				$updated = ES_Contact_Controller::process_contact_save( $args );
 
				if ( $updated ) {
					$response['message'] = __( 'Contact updated successfully.', 'email-subscribers' );
				} else {
					$response['message'] = __( 'Failed to update contact.', 'email-subscribers' );
				}

			} catch ( Exception $e ) {
				$response['message'] = __( 'Error updating contact: ', 'email-subscribers' ) . $e->getMessage();
			}

			return $response;
		}


 		public static function send_confirmation_email( $args = array() ) {

			if ( is_string( $args ) ) {
				$decoded = json_decode( $args, true );
				if ( $decoded ) {
					$args = $decoded;
				}
			}
			
			$contact_ids = isset( $args['contact_ids'] ) ? $args['contact_ids'] : array();

			if ( empty( $contact_ids ) || ! is_array( $contact_ids ) ) {
				$response['message'] = __( 'Contact ID is required.', 'email-subscribers' );
				return $response;
			}
			
 			$contact_ids = array_map( 'intval', $contact_ids );
			$contact_ids = array_filter( $contact_ids );
			
			if ( empty( $contact_ids ) ) {
				$response['message'] = __( 'Valid contact IDs are required.', 'email-subscribers' );
				return $response;
			}
			 
			$response = Email_Subscribers_Pro::handle_bulk_send_confirmation_email_action( $contact_ids, true );
		
			return $response;
		}

		public static function change_contact_status( $args = array() ) {

			if ( is_string( $args ) ) {
				$decoded = json_decode( $args, true );
				if ( $decoded ) {
					$args = $decoded;
				}
			}

			$contact_ids = isset( $args['contact_ids'] ) ? $args['contact_ids'] : array();

			$new_status = isset( $args['status'] ) ? $args['status'] : '';

			if ( empty( $contact_ids ) || ! is_array( $contact_ids ) ) {
				$response['message'] = __( 'Contact IDs are required', 'email-subscribers' );
				return $response;
			}
			
			if ( empty( $new_status ) ) {
				$response['message'] = __( 'Status is required', 'email-subscribers' );
				return $response;
			}
			
 			$valid_statuses = array( 'subscribed', 'unsubscribed', 'unconfirmed');
			if ( ! in_array( $new_status, $valid_statuses ) ) {
				$response['message'] = __( 'Invalid status provided', 'email-subscribers' );
				return $response;
			}
			
 			$contact_ids = array_map( 'intval', $contact_ids );
			$contact_ids = array_filter( $contact_ids );
			
			if ( empty( $contact_ids ) ) {
				$response['message'] = __( 'Valid contact IDs are required', 'email-subscribers' ); 
			}
			 
			$response = ES()->lists_contacts_db->edit_subscriber_status( $contact_ids, $new_status );

			return $response;  
		}

		
		public static function add_contacts_to_lists( $args = array() ) {

			if ( is_string( $args ) ) {
				$decoded = json_decode( $args, true );
				if ( $decoded ) {
					$args = $decoded;
				}
			}

			$contact_ids = isset( $args['contact_ids'] ) ? $args['contact_ids'] : array();
			$list_names  = isset( $args['list_names'] ) ? $args['list_names'] : array();

			if ( empty( $contact_ids ) || ! is_array( $contact_ids ) ) {
				$response['message'] = __( 'Contact IDs are required', 'email-subscribers' );
				return $response;
			}

			if ( empty( $list_names ) || ! is_array( $list_names ) ) {
				$response['message'] = __( 'List names are required', 'email-subscribers' );
				return $response;
			}
			
 			$contact_ids = array_map( 'intval', $contact_ids );
			$contact_ids = array_filter( $contact_ids );
			
			if ( empty( $contact_ids ) ) {
				$response['message'] = __( 'Valid contact IDs are required', 'email-subscribers' );
				return $response;
			}
			
 			$list_ids = array();
			foreach ( $list_names as $list_name ) {
				$list = ES()->lists_db->get_list_by_name( $list_name );
				if ( $list ) {
					$list_ids[] = $list['id'];
				}
			}
			
			if ( empty( $list_ids ) ) {
				$response['message'] = __( 'No valid lists found', 'email-subscribers' );
				return $response;
			}
			
			$updated_count = 0; 

			foreach ( $list_ids as $list_id ) {

 				$updated = ES()->lists_contacts_db->add_contacts_to_list( $contact_ids, $list_id );

				if ( $updated ) {
					$updated_count++;
				}  
			}
			 
			if ( $updated_count > 0 || empty( $errors ) ) {
				return array(
					'success' => true,
					'message' => "Contact(s) added to list successfully!",
					'updated_count' => $updated_count, 
				);
			} else {
				$response['message'] = __( 'Failed to add contacts to any lists', 'email-subscribers' );
				return $response;
			}
		}
		
		public static function get_audience_health_stats($args = array()) {

			$contacts_db = ES()->contacts_db; 

			try {

				$total_contacts = $contacts_db->count();
				
				if ($total_contacts == 0) {
					return array(
						'success' => true,
						'data' => array(
							'total_contacts' => 0,
							'verified_contacts' => 0,
							'verified_percentage' => 0
						)
					);
				}
				
				$verified_contacts = $contacts_db->count("status = 'verified'");
				$verified_percentage = $total_contacts > 0 ? round(($verified_contacts / $total_contacts) * 100) : 0;
				
				return array(
					'total_contacts' => $total_contacts,
					'verified_contacts' => $verified_contacts,
					'verified_percentage' => $verified_percentage
				);
				
			} catch (Exception $e) {
				return array(
					'success' => false,
					'message' => 'Error fetching audience health stats: ' . $e->getMessage()
				);
			}
		}

		public static function get_countries() {
			$countries = ES_Geolocation::get_countries();
			return $countries;
		}
		  
		private static function get_filtered_contact_ids_direct( $advanced_filter ) {

			global $wpdb;
			
			$contacts_table = IG_CONTACTS_TABLE;
			$where_conditions = array();
			$where_params = array();
			
			foreach ( $advanced_filter as $condition ) {
				$field = str_replace( 'subscribers.', '', $condition['field'] );
				$operator = $condition['operator'];
				$value = $condition['value'];
				 
				switch ( $operator ) {
					case 'is':
						if ( is_array( $value ) ) {
							$placeholders = implode( ',', array_fill( 0, count( $value ), '%s' ) );
							$where_conditions[] = "{$field} IN ({$placeholders})";
							$where_params = array_merge( $where_params, $value );
						} else {
							$where_conditions[] = "{$field} = %s";
							$where_params[] = $value;
						}
						break;
						
					case 'is_not':
						if ( is_array( $value ) ) {
							$placeholders = implode( ',', array_fill( 0, count( $value ), '%s' ) );
							$where_conditions[] = "{$field} NOT IN ({$placeholders})";
							$where_params = array_merge( $where_params, $value );
						} else {
							$where_conditions[] = "{$field} != %s";
							$where_params[] = $value;
						}
						break;
						
					case 'contains':
						$where_conditions[] = "{$field} LIKE %s";
						$where_params[] = '%' . $value . '%';
						break;
						
					case 'in':
						if ( is_array( $value ) ) {
							$placeholders = implode( ',', array_fill( 0, count( $value ), '%s' ) );
							$where_conditions[] = "{$field} IN ({$placeholders})";
							$where_params = array_merge( $where_params, $value );
						}
						break;
				}
			}
			
			if ( empty( $where_conditions ) ) {
				return array();
			}
			
 			$where_sql = implode( ' OR ', $where_conditions );
			$sql = "SELECT id FROM {$contacts_table} WHERE ({$where_sql})";
			 	
			if ( ! empty( $where_params ) ) {
				$results = $wpdb->get_col( $wpdb->prepare( $sql, $where_params ) );
			} else {
				$results = $wpdb->get_col( $sql );
			}
						
			return $results ? array_map( 'intval', $results ) : array();
		}

		private static function get_contact_ids_by_lists( $list_ids ) {
			global $wpdb;
			
			if ( empty( $list_ids ) ) {
				return array();
			}
			
			$lists_contacts_table = IG_LISTS_CONTACTS_TABLE;

 			if ( ! is_array( $list_ids ) ) {
				$list_ids = array( $list_ids );
			}
			
 			$list_ids = array_unique( array_map( 'intval', $list_ids ) );
			
			if ( empty( $list_ids ) ) {
				return array();
			}
			
 			$placeholders = implode( ',', array_fill( 0, count( $list_ids ), '%d' ) );
			
 			$sql = "SELECT DISTINCT contact_id FROM {$lists_contacts_table} WHERE list_id IN ({$placeholders})";
			
			$results = $wpdb->get_col( $wpdb->prepare( $sql, $list_ids ) );
			
			return $results ? array_map( 'intval', $results ) : array();
		}

		private static function build_es_filter_condition($field, $operator, $value) {
			if (empty($value) && $value !== '0' && $value !== 0) {
				return null;
			}
			
 			$db_field = self::map_field_to_db_column($field);
			if (!$db_field) {
				return null;
			}
			
 			$processed_value = self::process_field_value($field, $value);
			
 			if (is_array($processed_value) && count($processed_value) > 0) {
				if (count($processed_value) === 1) {
 					$processed_value = $processed_value[0];
				} else {
 					return array(
						'field' => $db_field,
						'operator' => 'in',
						'value' => $processed_value
					);
				}
			}
			
 			$es_operator = self::map_operator_to_es_operator($operator);
			if (!$es_operator) {
				return null;
			}
			
 			$condition = array(
				'field' => $db_field,
				'operator' => $es_operator,
				'value' => $processed_value
			);
			
			return $condition;
		}

		/**
		 * Map frontend field names to database column names
		 */
		private static function map_field_to_db_column($field) {
			$field_mapping = array(
				'List' => 'subscribers.list_id',
				'Email' => 'subscribers.email',
				'Country' => 'subscribers.country_code',
				'Bounce Status' => 'subscribers.bounce_status',
				'Subscribed' => 'subscribers.created_at',
				'Engagement Score' => 'subscribers.engagement_score', 
			);
			
			return isset($field_mapping[$field]) ? $field_mapping[$field] : null;
		}

		/**
		 * Map frontend operators to ES query operators
		 */
		private static function map_operator_to_es_operator($operator) {
			$operator_mapping = array(
				'is equal to' => 'is',
				'is not equal to' => 'is_not',
				'contains' => 'contains',
				'does not contain' => 'does_not_contain',
				'starts with' => 'starts_with',
				'ends with' => 'ends_with',
				'greater than' => 'is_greater_than',
				'less than' => 'is_less_than',
				'in' => 'in',
			);
			
			return isset($operator_mapping[$operator]) ? $operator_mapping[$operator] : null;
		}

		/**
		 * Process field values for ES query format
		 */
		private static function process_field_value($field, $value) {
			switch ($field) {

				case 'Email':
					if (is_array($value)) {
						return $value;
					}
					return $value;
					
				case 'Country':
					if (is_array($value) && isset($value['name'])) {
						return $value['name'];
					} elseif (is_object($value) && isset($value->name)) {
						return $value->name;
					}
					return $value;
					
				case 'Bounce Status':
					if (is_array($value)) {
						return array_map('intval', $value); // Convert to integers
					} else {
						return intval($value); // Convert to integer
					}
					
				case 'Subscribed':
					if (is_string($value) && strtotime($value)) {
						return date('Y-m-d', strtotime($value));
					}
					return $value;
					
				case 'has received':
					if (is_array($value) && isset($value['id'])) {
						return $value['id'];
					} elseif (is_object($value) && isset($value->id)) {
						return $value->id;
					}
					return $value;
					
				default:
					return $value;
			}
		}

	}

}

ES_Contacts_Controller::get_instance();
