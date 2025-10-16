<?php

if ( ! class_exists( 'ES_Campaigns_Controller' ) ) {

	/**
	 * Class to handle single campaign options
	 * 
	 * @class ES_Campaigns_Controller
	 */
	class ES_Campaigns_Controller {

		// class instance
		public static $instance;

		public static function get_instance() {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		public static function get_campaigns_and_kpis( $args ) {
			$campaigns = self::get_campaigns( $args );
			$kpis      = self::get_kpis( $args );
			return array(
				'campaigns' => $campaigns,
				'kpis'      => $kpis,
			);
		}
		
		public static function get_campaigns_count( $args) {
			if ( is_string( $args ) ) {
				$args = json_decode( $args, true );
			}
			if ( ! is_array( $args ) ) {
				$args = array();
			}
			
			$per_page = ! empty( $args['per_page'] ) ? (int) $args['per_page'] : 20;
			$current_page = ! empty( $args['currentPage'] ) ? $args['currentPage'] : 1;
			
			$filter_args = array();
			
			if ( ! empty( $args['search'] ) ) {
				$filter_args['search_text'] = sanitize_text_field( $args['search'] );
			}
			
			if ( ! empty( $args['type'] ) ) {
				$filter_args['campaign_type'] = sanitize_text_field( $args['type'] );
			}
			
			if ( isset( $args['status'] ) ) {
				$filter_args['campaign_status'] = sanitize_text_field( $args['status'] );
			}
			
			$total_items = ES_DB_Campaigns::get_lists(0, 0, true, $filter_args);
			$total_campaign_pages = ceil($total_items / $per_page); 
			return [$total_items,$total_campaign_pages,$current_page,$per_page];

		}

		public static function get_campaigns( $args ) {
			if ( is_string( $args ) ) {
				$args = json_decode( $args, true );
			}
			if ( ! is_array( $args ) ) {
				$args = array();
			}
			
			$per_page = ! empty( $args['per_page'] ) ? (int) $args['per_page'] : 20;
			$current_page = ! empty( $args['currentPage'] ) ? $args['currentPage'] : 1;
			
			$filter_args = array(
				'order_by' => ! empty( $args['order_by'] ) ? sanitize_text_field( $args['order_by'] ) : 'created_at',
				'order' => ! empty( $args['order'] ) && in_array( strtoupper( $args['order'] ), array( 'ASC', 'DESC' ) ) ? strtoupper( $args['order'] ) : 'DESC'
			);
			
			if ( ! empty( $args['search'] ) ) {
				$filter_args['search_text'] = sanitize_text_field( $args['search'] );
			}
			
			if ( ! empty( $args['type'] ) ) {
				$filter_args['campaign_type'] = sanitize_text_field( $args['type'] );
			}
			
			if ( isset( $args['status'] ) ) {
				$filter_args['campaign_status'] = sanitize_text_field( $args['status'] );
			}
			
			$total_items = ES_DB_Campaigns::get_lists(0, 0, true, $filter_args);
			$campaigns = ES_DB_Campaigns::get_lists($per_page, $current_page, false, $filter_args);
			
			if (!empty($campaigns)) {
				foreach ($campaigns as $index => $campaign) {
					$formatted_campaign = self::format_campaign_data($campaign);
					$campaigns[$index] = $formatted_campaign;
				}
			}
			
		  $result = array();
		  $result['campaigns'] = $campaigns;
		  $result['currentPage'] = $current_page ? $current_page : 1;
			return $result;
			
		}

		private static function format_campaign_data( $campaign ) {
			if ( ! empty( $campaign ) ) {
				$campaign['es_admin_email'] = ES_Common::get_admin_email();
				$campaign_id = $campaign['id'];
				$campaign_status = (int) $campaign['status'];
				$campaign_type = $campaign['type'];

				$list_names_display = '-';
				
				$all_list_ids = ES()->campaigns_db->get_list_ids( $campaign_id );
				
				if ( ! empty( $all_list_ids ) ) {
					$all_list_ids = array_map( 'intval', array_filter( $all_list_ids ) );
					if ( ! empty( $all_list_ids ) ) {
						$list_names = ES()->lists_db->get_list_name_by_ids( $all_list_ids );
						if ( ! empty( $list_names ) && is_array( $list_names ) ) {
							$valid_names = array_filter( $list_names );
							if ( ! empty( $valid_names ) ) {
								$list_names_display = implode( ', ', $valid_names );
							} else {
								$list_names_display = '-';
							}
						} else {
							$list_names_display = '-';
						}
					}
				}
				
				$campaign['list_names'] = $list_names_display;

				if ( self::is_post_campaign( $campaign_type ) ) {
					$categories_data = self::format_categories( $campaign['categories'] );
					$campaign['formatted_categories'] = $categories_data['formatted_categories'];
					$campaign['category_names'] = $categories_data['category_names'];
				}
				$campaign['status'] = (int) $campaign['status'];
				$campaign['id'] = (int) $campaign['id'];
				
				$campaign['status_text'] = self::get_campaign_status_text( $campaign['status'] );
				$campaign['meta']        = ig_es_maybe_unserialize( $campaign['meta']);
				$args = array(
					'campaign_id' => $campaign_id,
					'types' => array(
						IG_MESSAGE_SENT,
						IG_MESSAGE_OPEN,
						IG_LINK_CLICK
					)
				);
				$actions_count       = ES()->actions_db->get_actions_count( $args );
				$total_email_sent    = $actions_count['sent'];
				$total_email_opened  = $actions_count['opened'];
				$total_email_clicked = $actions_count['clicked'];
				$open_rate  = ! empty( $total_email_sent ) ? number_format_i18n( ( ( $total_email_opened * 100 ) / $total_email_sent ), 2 ) : 0 ;
				$click_rate = ! empty( $total_email_sent ) ? number_format_i18n( ( ( $total_email_clicked * 100 ) / $total_email_sent ), 2 ) : 0;
				$campaign['open_rate']  = $open_rate;
				$campaign['click_rate'] = $click_rate;
				$campaign['total_sent'] = $total_email_sent;
				$campaign['meta'] = ig_es_maybe_unserialize( $campaign['meta']);
				
				$report = ES_DB_Mailing_Queue::get_notification_by_campaign_id( $campaign_id );
				
				if ( self::is_post_campaign( $campaign_type ) ) {
					if ( $report ) {
						$post_id = $report['post_id'];
						$post = get_post( $post_id );
						if ( $post ) {
							$campaign['post_title'] = $post->post_title;
							$campaign['post_date'] = $post->post_date;
						}
					}
					if ( ! empty( $report ) ) {
						$campaign['report_link'] = admin_url( 'admin.php?page=es_reports&campaign_id=' . $campaign_id );
					}
				} elseif ( IG_CAMPAIGN_TYPE_NEWSLETTER === $campaign_type ) {
					if ( $report && !empty( $report['hash'] ) ) {
						$campaign['report_link'] = admin_url( 'admin.php?page=es_reports&action=view&list=' . $report['hash'] );
					}
				} elseif ( in_array( $campaign_type, array( IG_CAMPAIGN_TYPE_SEQUENCE, IG_CAMPAIGN_TYPE_WORKFLOW ), true ) ) {
					$campaign['report_link'] = admin_url( 'admin.php?page=es_reports&campaign_id=' . $campaign_id );
				}
				
				if ( IG_CAMPAIGN_TYPE_SEQUENCE === $campaign_type ) {
					$campaign['edit_link'] = admin_url( 'admin.php?page=es_sequence&action=edit&id=' . $campaign_id );
				} elseif ( IG_CAMPAIGN_TYPE_WORKFLOW === $campaign_type ) {
					$campaign['edit_link'] = admin_url( 'admin.php?page=es_workflows&action=edit&id=' . $campaign_id );
				} else {
					$campaign['edit_link'] = admin_url( 'admin.php?page=es_campaigns#!/campaign/edit/' . $campaign_id );
				}
			}
			return $campaign;
		}

		public static function get_kpis( $args ) {
			if ( is_string( $args ) ) {
				$args = json_decode( $args, true );
			}
			if ( ! is_array( $args ) ) {
				$args = array();
			} 

			$page           = 'es_campaigns';
			$override_cache = true;
			$reports_data   = ES_Reports_Data::get_dashboard_reports_data( $page, $override_cache, $args );
			return $reports_data;
		}

		public static function delete_campaigns( $args ) {
			if ( is_string( $args ) ) {
				$args = json_decode( $args, true );
			}
			if ( ! is_array( $args ) ) {
				$args = array();
			}
			
			$campaign_ids = $args['campaign_ids'];
			if ( ! empty( $campaign_ids ) ) {
				return ES()->campaigns_db->delete_campaigns( $campaign_ids );
			}
			return false;
		}

		/**
		 * Method to Duplicate broadcast content
		 *
		 * @return void
		 *
		 * @since 4.6.3
		 */
		public static function duplicate_campaign( $args ) {
			if ( is_string( $args ) ) {
				$args = json_decode( $args, true );
			}
			if ( ! is_array( $args ) ) {
				$args = array();
			}
			
			$plan = ES()->get_plan();
			if ( 'pro' !== strtolower( $plan ) ) {
				return array( 'error' => 'Campaign duplication is available only for Pro plan users.' );
			}
			
			$campaign_id = isset( $args['campaign_id'] ) ? $args['campaign_id'] : 0;
			
			if ( empty( $campaign_id ) ) {
				return array( 'error' => 'Campaign ID is required' );
			}

			$original_campaign = ES()->campaigns_db->get( $campaign_id );
			if ( empty( $original_campaign ) ) {
				error_log( 'ES Duplicate Campaign: Original campaign not found - ID: ' . $campaign_id );
				return array( 'error' => 'Original campaign not found' );
			}

			$duplicated_campaign_id = ES()->campaigns_db->duplicate_campaign( $campaign_id );
			if ( empty( $duplicated_campaign_id ) ) {
				error_log( 'ES Duplicate Campaign: Database duplicate failed for ID: ' . $campaign_id );
				return array( 'error' => 'Failed to create duplicate campaign in database' );
			}

			$duplicated_campaign = ES()->campaigns_db->get( $duplicated_campaign_id );
			if ( empty( $duplicated_campaign ) ) {
				error_log( 'ES Duplicate Campaign: Could not retrieve duplicated campaign - ID: ' . $duplicated_campaign_id );
				return array( 'error' => 'Failed to retrieve duplicated campaign' );
			}

			$duplicated_campaign = self::format_campaign_data( $duplicated_campaign );

			return $duplicated_campaign;
		}

		public static function format_categories( $categories ) {
			$categories = explode( '##', trim( trim( $categories, '##' ) ) );
			$formatted_categories = array();
			$category_names = array();
			$has_all_categories = false;
			
			if ( ! empty( $categories ) ) {
				foreach ( $categories as $category ) {
					if ( ! empty( $category ) ) {
						$post_categories = explode( '|', $category );
						foreach ( $post_categories as $post_category ) {
							if ( empty( $post_category ) || strpos( $post_category, ':' ) === false ) {
								continue;
							}
							$parts = explode( ':', $post_category, 2 );
							if ( count( $parts ) < 2 ) {
								continue;
							}
							list( $post_type, $categories_list ) = $parts;
							if ( 'none' !== $categories_list && 'all' !== $categories_list && ! empty( $categories_list ) ) {
								$categories_list = array_map( 'absint', explode( ',', $categories_list ) );
								// Convert term IDs to names (handles both categories and custom taxonomies)
								foreach ( $categories_list as $term_id ) {
									// First try to get term from default category taxonomy
									$term = get_term( $term_id, 'category' );
									if ( ! is_wp_error( $term ) && ! empty( $term ) ) {
										$category_names[] = $term->name;
									} else {
										// If not found in categories, search across all taxonomies for this post type
										$taxonomies = get_object_taxonomies( $post_type, 'names' );
										foreach ( $taxonomies as $taxonomy ) {
											$term = get_term( $term_id, $taxonomy );
											if ( ! is_wp_error( $term ) && ! empty( $term ) ) {
												$category_names[] = $term->name;
												break; // Found it, no need to check other taxonomies
											}
										}
									}
								}
							} elseif ( 'all' === $categories_list ) {
								$has_all_categories = true;
							}
							$formatted_categories[$post_type] = $categories_list;
						}
					} 
				} 
			}
			
			if ( $has_all_categories && empty( $category_names ) ) {
				$category_names[] = '-';
			}
			
			return array(
				'formatted_categories' => $formatted_categories,
				'category_names' => array_unique( $category_names )
			);
		}

		public static function is_post_campaign( $campaign_type ) {
			return in_array( $campaign_type, array( IG_CAMPAIGN_TYPE_POST_NOTIFICATION, IG_CAMPAIGN_TYPE_POST_DIGEST ), true );
		}

		/**
		 * Get status text based on status number
		 * 
		 * @param int $status Campaign status number
		 * @return string Status text
		 */
		public static function get_campaign_status_text( $status ) {
			$status = (int) $status;
			switch ( $status ) {
				case 0:
					return 'Draft';
				case 1:
					return 'Active';
				case 2:
					return 'Scheduled';
				case 3:
					return 'Sending';
				case 4:
					return 'Paused';
				case 5:
					return 'Sent';
			}
		}

		// Note: paginate_campaigns method removed as it's not needed for React UI
		// React frontend handles pagination through get_campaigns and get_campaigns_count methods

	}

}

ES_Campaigns_Controller::get_instance();