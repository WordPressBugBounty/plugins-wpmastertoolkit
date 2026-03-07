<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Search Replace in database
 * Description: Search and replace data in your WordPress database safely and efficiently.
 * @since 2.17.0
 */
class WPMastertoolkit_Search_Replace_In_Database {

	private $option_id;
	private $option_name;
	private $transient_name;
	private $option_site_url;
    private $header_title;
    private $nonce_name;
    private $page_id;
	private $max_free;

	/**
     * Invoke Wp Hooks
	 *
     * @since    2.17.0
	 */
    public function __construct() {

		$this->option_id       = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_search_replace_in_database';
		$this->option_name     = $this->option_id . '_options';
		$this->transient_name  = $this->option_id . '_transient';
		$this->option_site_url = $this->option_id . '_option_site_url';
        $this->nonce_name      = $this->option_id . '_action';
		$this->page_id	       = 'wp-mastertoolkit-settings-search-replace-in-database';
		$this->max_free        = 10;

		add_action( 'init', array( $this, 'class_init' ) );
        add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
		add_action( 'wp_ajax_wpmtk_search_replace_in_database_start', array( $this, 'ajax_search_replace_in_database_start' ) );
	}

	/**
     * Initialize the class
	 * 
	 * @since    2.17.0
     */
    public function class_init() {
        $this->header_title = esc_html__( 'Search Replace in database', 'wpmastertoolkit' );
    }

	/**
     * Add a submenu
     * 
     * @since   2.17.0
     */
    public function add_submenu(){

        WPMastertoolkit_Settings::add_submenu_page(
            'wp-mastertoolkit-settings',
            $this->header_title,
            $this->header_title,
            'manage_options',
            $this->page_id,
            array( $this, 'render_submenu'),
            null
        );
    }

	/**
     * Render the submenu
     * 
     * @since   2.17.0
     */
    public function render_submenu() {

        $submenu_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/search-replace-in-database.asset.php' );
        wp_enqueue_style( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/search-replace-in-database.css', array(), $submenu_assets['version'], 'all' );
        wp_enqueue_script( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/search-replace-in-database.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );
		wp_localize_script( 'WPMastertoolkit_submenu', 'WPMastertoolkit_search_replace_in_database', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( $this->nonce_name ),
			'max_free' => $this->max_free,
			'i18n'     => array(
				'search_required'        => esc_html__( 'Please enter a search term.', 'wpmastertoolkit' ),
				'select_table'           => esc_html__( 'Please select at least one table.', 'wpmastertoolkit' ),
				'something_wrong'        => esc_html__( 'Something went wrong. Please try again.', 'wpmastertoolkit' ),
				'run_status_progression' => esc_html__( 'Progression', 'wpmastertoolkit' ),
				'run_status_completed'   => esc_html__( 'Done', 'wpmastertoolkit' ),
			),
		) );

        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
        $this->submenu_content();
        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/footer.php';
    }

	/**
	 * AJAX: Start Search Replace in database
	 * 
	 * @since   2.17.0
	 */
	public function ajax_search_replace_in_database_start(){
		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, $this->nonce_name ) ) {
			wp_send_json_error( array(
				'message'      => __( 'Refresh the page and try again.', 'wpmastertoolkit' ),
				'message_type' => 'danger',
			) );
		}

		$step = sanitize_text_field( wp_unslash( $_POST['step'] ?? '0' ) );
		$step = absint( $step );
		$page = sanitize_text_field( wp_unslash( $_POST['page'] ?? '0' ) );
		$page = absint( $page );

		if ( 0 === $step && 0 === $page ) {
			$args     = array();
			$raw_data = sanitize_text_field( wp_unslash( $_POST['data'] ?? '' ) );
			$args     = json_decode( $raw_data, true );

			if ( isset( $args['tables'] ) || ! is_array( $args['tables'] ) ) {
				$args['tables'] = explode( ' ', $args['tables'] );
			}

			if ( isset( $args['search_replace'] ) ) {
				$args['search_replace'] = json_decode( $args['search_replace'], true );
			}

			$args = array(
				'search_replace'  => isset( $args['search_replace'] ) ? $args['search_replace'] : array(),
				'tables'          => array_map( 'trim', $args['tables'] ),
				'replace_guids'   => isset( $args['replace_guids'] ) ? $args['replace_guids'] : '0',
				'run_dry'         => isset( $args['run_dry'] ) ? $args['run_dry'] : '0',
				'page_size'       => isset( $args['page_size'] ) ? stripslashes( $args['page_size'] ) : '20000',
				'completed_pages' => isset( $args['completed_pages'] ) ? absint( $args['completed_pages'] ) : 0,
				'cells_processed' => 0,
			);

			$args['total_pages'] = isset( $args['total_pages'] ) ? absint( $args['total_pages'] ) : $this->get_total_pages( $args );

			// Clear the results of the last run.
			delete_transient( $this->transient_name );
			delete_option( $this->option_name );
		} else {
			$args = get_option( $this->option_name, array() );
		}

		// Start processing data.
		if ( isset( $args['tables'][$step] ) ) {

			$result = $this->search_replace_db( $args['tables'][$step], $page, $args );
			$this->append_report( $args['tables'][$step], $result['table_report'], $args );

			if ( false === $result['table_complete'] ) {
				$page++;
			} else {
				$step++;
				$page = 0;
			}

			$args['completed_pages']++;
			$percentage = $args['completed_pages'] / $args['total_pages'] * 100;
		} else {
			$this->maybe_update_site_url();
			$step       = 'done';
			$percentage = 100;
		}

		$tables_sizes   = $this->get_sizes();
		$cells_changed  = isset( $result['table_report']['change'] ) ? $result['table_report']['change'] : 0;
		$cells_updated  = isset( $result['table_report']['updates'] ) ? $result['table_report']['updates'] : 0;
		$data_processed = isset( $result['data_processed'] ) ? $result['data_processed'] : array();
		$hidden_data_processed = array();
		$max_free_reached = false;

		if ( ! wpmastertoolkit_is_pro() ) {
			$old_total = $args['cells_processed'];
			$new_total = $old_total + $cells_changed;
			$max_free_reached = ( $new_total > $this->max_free );

			if ( $this->max_free < $new_total ) {

				$cells_nedded       = max( 0, $this->max_free - $old_total );
				$new_data_processed = array();

				$counter = 0;
				foreach ( $data_processed as $table => $cells ) {

					foreach ( $cells as $cell ) {

						if ( $counter < $cells_nedded ) {
							$new_data_processed[$table][] = $cell;
						} else {
							$hidden_data_processed[$table][] = $this->obfuscate_hidden_cell( $cell );
						}
						$counter++;
					}
				}

				$data_processed = $new_data_processed;
			}
		}

		$args['cells_processed'] += $cells_changed;
		update_option( $this->option_name, $args );

		// Store results in an array.
		$result = array(
			'table_name'     => is_int( $step ) && isset( $args['tables'][$step-1] ) ? $args['tables'][$step-1] : '',
			'table_size'     => is_int( $step ) && isset( $args['tables'][$step-1] ) ? $tables_sizes[$args['tables'][$step-1]] : 0,
			'cells_changed'  => $cells_changed,
			'cells_updated'  => $cells_updated,
			'step'           => $step,
			'page'           => $page,
			'percentage'     => number_format( (float) $percentage, 0 ) . '%',
			'data'           => build_query( $args ),
			'data_processed' => $data_processed,
			'hidden_data_processed' => $hidden_data_processed,
			'max_free_reached' => $max_free_reached,
		);

		wp_send_json_success( $result );
	}

	/**
	 * Obfuscate hidden cell preview for free mode.
	 *
	 * @since   2.17.0
	 * @return array
	 */
	private function obfuscate_hidden_cell( $cell ) {
		return array(
			'row_id' => $this->obfuscate_hidden_value( $cell['row_id'] ?? '' ),
			'column' => $this->obfuscate_hidden_value( $cell['column'] ?? '' ),
			'text'   => $this->obfuscate_hidden_value( $cell['text'] ?? '' ),
		);
	}

	/**
	 * Obfuscate value while preserving structure length as much as possible.
	 *
	 * @since   2.17.0
	 * @return string
	 */
	private function obfuscate_hidden_value( $value ) {
		$value = (string) $value;

		$parts = preg_split( '/(<[^>]+>)/u', $value, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( ! is_array( $parts ) ) {
			$obfuscated = preg_replace_callback( '/[\p{L}\p{N}]/u', array( $this, 'replace_with_random_alnum' ), $value );
			return null !== $obfuscated ? $obfuscated : $value;
		}

		foreach ( $parts as $index => $part ) {
			if ( '' === $part || '<' === $part[0] ) {
				continue;
			}

			$obfuscated = preg_replace_callback( '/[\p{L}\p{N}]/u', array( $this, 'replace_with_random_alnum' ), $part );
			$parts[$index] = null !== $obfuscated ? $obfuscated : $part;
		}

		return implode( '', $parts );
	}

	/**
	 * Replace matched character with random alphanumeric character.
	 *
	 * @since   2.17.0
	 * @return string
	 */
	private function replace_with_random_alnum( $matches ) {
		$char = isset( $matches[0] ) ? (string) $matches[0] : '';

		if ( '' === $char ) {
			return $char;
		}

		if ( preg_match( '/[0-9]/', $char ) ) {
			return (string) wp_rand( 0, 9 );
		}

		if ( preg_match( '/[A-Z]/', $char ) ) {
			return chr( wp_rand( 65, 90 ) );
		}

		if ( preg_match( '/[a-z]/', $char ) ) {
			return chr( wp_rand( 97, 122 ) );
		}

		$pool = 'abcdefghijklmnopqrstuvwxyz0123456789';
		return $pool[ wp_rand( 0, strlen( $pool ) - 1 ) ];
	}

	/**
	 * Perform search and replace on a database table
	 *
	 * @since   2.17.0
	 * @return array
	 */
	public function search_replace_db( $table, $page, $args ) {
		global $wpdb;

		$data_processed[$table] = array();

		// Load up the default settings for this chunk.
		$page_size    = absint( $args['page_size'] );
		$table        = esc_sql( $table );
		$current_page = absint( $page );
		$pages        = $this->get_pages_in_table( $table, $page_size );
		$done         = false;

		$table_report = array(
			'change'  => 0,
			'updates' => 0,
			'start'   => microtime( true ),
			'end'     => microtime( true ),
			'errors'  => array(),
			'skipped' => false,
		);

		// Get a list of columns in this table.
		list( $primary_key, $columns ) = $this->get_columns( $table );

		// Bail out early if there isn't a primary key.
		if ( null === $primary_key ) {
			$table_report['skipped'] = true;
			return array( 'table_complete' => true, 'table_report' => $table_report );
		}

		$current_row = 0;
		$start       = $page * $page_size;
		$end         = $page_size;

		// Grab the content of the table.
		//phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$data = $wpdb->get_results( "SELECT * FROM `$table` LIMIT $start, $end", ARRAY_A );

		// Loop through the data.
		foreach ( $data as $row ) {
			$current_row++;
			$update_sql = array();
			$where_sql  = array();
			$upd        = false;

			foreach( $columns as $column ) {

				$data_to_fix = $row[ $column ];

				if ( $column == $primary_key ) {
					$where_sql[] = $column . ' = "' .  $this->mysql_escape_mimic( $data_to_fix ) . '"';
					continue;
				}

				// Skip GUIDs by default.
				if ( '1' !== $args['replace_guids'] && 'guid' == $column ) {
					continue;
				}

				if ( $wpdb->options === $table ) {

					// Skip any WPMTK options as they may contain the search field.
					if ( isset( $should_skip ) && true === $should_skip ) {
						$should_skip = false;
						continue;
					}

					// If the Site URL needs to be updated, let's do that last.
					if ( isset( $update_later ) && true === $update_later ) {
						$update_later = false;
						$edited_data_siteurl = $data_to_fix;
					
						// Apply all search/replace operations
						if ( isset( $args['search_replace'] ) && is_array( $args['search_replace'] ) ) {
							foreach ( $args['search_replace'] as $search_replace_item ) {
								$search     = isset( $search_replace_item['search'] ) ? $search_replace_item['search'] : '';
								$replace    = isset( $search_replace_item['replace'] ) ? $search_replace_item['replace'] : '';
								$type       = isset( $search_replace_item['type'] ) ? $search_replace_item['type'] : '';
								$is_regex   = $type === 'regex' ? true : false;
								$match_case = $type === 'match_case' ? true : false;
								
								if ( empty( $search ) ) {
									continue;
								}

								if ( ! wpmastertoolkit_is_pro() ) {
									$is_regex = false;
								}
								
								$edited_data_siteurl = $this->recursive_unserialize_replace( $search, $replace, $edited_data_siteurl, false, !$match_case, $is_regex );
							}
						}

						if ( $edited_data_siteurl != $data_to_fix ) {
							$table_report['change']++;
							$table_report['updates']++;
							update_option( $this->option_site_url, $edited_data_siteurl );
							continue;
						}
					}

					if ( '_transient_' . $this->transient_name === $data_to_fix || $this->option_site_url === $data_to_fix || $this->option_name === $data_to_fix ) {
						$should_skip = true;
					}

					if ( 'siteurl' === $data_to_fix && '1' !== $args['run_dry'] ) {
						$update_later = true;
					}
				}

				// Run a search replace on the data that'll respect the serialisation.
				$edited_data = $data_to_fix;
			
				// Loop through all search/replace pairs
				if ( isset( $args['search_replace'] ) && is_array( $args['search_replace'] ) ) {
					foreach ( $args['search_replace'] as $search_replace_item ) {
						$search     = isset( $search_replace_item['search'] ) ? $search_replace_item['search'] : '';
						$replace    = isset( $search_replace_item['replace'] ) ? $search_replace_item['replace'] : '';
						$type       = isset( $search_replace_item['type'] ) ? $search_replace_item['type'] : '';
						$is_regex   = $type === 'regex' ? true : false;
						$match_case = $type === 'match_case' ? true : false;
						
						if ( empty( $search ) ) {
							continue;
						}

						if ( ! wpmastertoolkit_is_pro() ) {
							$is_regex = false;
						}
						
						$edited_data = $this->recursive_unserialize_replace( $search, $replace, $edited_data, false, !$match_case, $is_regex );
					}
				}

				// Something was changed
				if ( $edited_data != $data_to_fix ) {
					$update_sql[] = $column . ' = "' . $this->mysql_escape_mimic( $edited_data ) . '"';
					$upd = true;
					$table_report['change']++;

					$data_processed[$table][] = array(
						'column' => $column,
						'row_id' => $row[ $primary_key ],
						'text'   => $this->format_display_data( $data_to_fix, $edited_data ),
					);
				}
			}

			// Determine what to do with updates.
			if ( '1' === $args['run_dry'] ) {
				// Don't do anything if a dry run
			} elseif ( $upd && ! empty( $where_sql ) ) {
				// If there are changes to make, run the query.
				$sql    = 'UPDATE ' . $table . ' SET ' . implode( ', ', $update_sql ) . ' WHERE ' . implode( ' AND ', array_filter( $where_sql ) );
				//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
				$result = $wpdb->query( $sql );

				if ( ! $result ) {
					/* translators: %d: Row ID */
					$table_report['errors'][] = sprintf( __( 'Error updating row: %d.', 'wpmastertoolkit' ), $current_row );
				} else {
					$table_report['updates']++;
				}
			}
		} // end row loop

		if ( $current_page >= $pages - 1 ) {
			$done = true;
		}

		// Flush the results and return the report.
		$table_report['end'] = microtime( true );
		$wpdb->flush();

		return array(
			'table_complete' => $done,
			'table_report'   => $table_report,
			'data_processed' => $data_processed,
		);
	}

	/**
	 * Format display data
	 * 
	 * @since   2.17.0
	 * @return string
	 */
	public function format_display_data( $original, $edited ) {
		// Configuration for context length
		$context_chars    = 50; // Characters to show before and after changes
		$max_total_length = 200; // Maximum total length before truncating
		
		// If the original is short enough, use the full comparison
		if ( strlen( $original ) <= $max_total_length && strlen( $edited ) <= $max_total_length ) {
			return $this->format_full_comparison( $original, $edited );
		}
		
		// For longer content, find all change locations and show context
		return $this->format_multiple_changes( $original, $edited, $context_chars );
	}
	
	/**
	 * Format full comparison for shorter content
	 * 
	 * @since   2.17.0
	 * @return string
	 */
	private function format_full_comparison( $original, $edited ) {
		// Split by newlines and commas to handle multi-line email lists
		$original_parts = preg_split('/([,\n]+)/', $original, -1, PREG_SPLIT_DELIM_CAPTURE);
		$edited_parts = preg_split('/([,\n]+)/', $edited, -1, PREG_SPLIT_DELIM_CAPTURE);
		
		$result = '<div class="change-preview">';
		$max_length = max(count($original_parts), count($edited_parts));
		
		for ($i = 0; $i < $max_length; $i++) {
			$orig_part = isset($original_parts[$i]) ? $original_parts[$i] : '';
			$edit_part = isset($edited_parts[$i]) ? $edited_parts[$i] : '';
			
			// If it's a delimiter (comma or newline), just add it as-is
			if (preg_match('/^[,\n]+$/', $orig_part) || preg_match('/^[,\n]+$/', $edit_part)) {
				$result .= htmlspecialchars($orig_part);
				continue;
			}
			
			// Skip empty parts
			if (empty($orig_part) && empty($edit_part)) {
				continue;
			}
			
			// If parts are the same, no highlighting needed
			if ($orig_part === $edit_part) {
				$result .= htmlspecialchars($orig_part);
				continue;
			}
			
			// Find the common prefix
			$common_prefix = '';
			$min_len = min(strlen($orig_part), strlen($edit_part));
			for ($j = 0; $j < $min_len; $j++) {
				if ($orig_part[$j] === $edit_part[$j]) {
					$common_prefix .= $orig_part[$j];
				} else {
					break;
				}
			}
			
			// Get the different parts
			$orig_diff = substr($orig_part, strlen($common_prefix));
			$edit_diff = substr($edit_part, strlen($common_prefix));
			
			// Add common prefix without styling
			$result .= htmlspecialchars($common_prefix);
			
			// Add removed part (from original)
			if (!empty($orig_diff)) {
				$result .= '<span class="original">' . htmlspecialchars($orig_diff) . '</span>';
			}
			
			// Add added part (from edited)
			if (!empty($edit_diff)) {
				$result .= '<span class="edited">' . htmlspecialchars($edit_diff) . '</span>';
			}
		}
		
		$result .= '</div>';
		return $result;
	}
	
	/**
	 * Format multiple changes with context
	 * 
	 * @since   2.17.0
	 * @return string
	 */
	private function format_multiple_changes( $original, $edited, $context_chars ) {
		// Find all change locations
		$changes = $this->find_all_changes( $original, $edited );
		
		if ( empty( $changes ) ) {
			return '<div>' . htmlspecialchars( substr( $original, 0, 100 ), ENT_QUOTES, 'UTF-8' ) . '...</div>';
		}
		
		$result = '<div>';
		
		foreach ( $changes as $index => $change ) {
			if ( $index > 0 ) {
				$result .= '<br>';
			}
			
			$result .= $this->format_single_change( $original, $edited, $change, $context_chars );
		}
		
		$result .= '</div>';
		return $result;
	}
	
	/**
	 * Find all change locations between original and edited strings
	 * 
	 * @since   2.17.0
	 * @return array Array of change locations with start and end positions
	 */
	private function find_all_changes( $original, $edited ) {
		$changes = array();
		$orig_len = strlen( $original );
		$edit_len = strlen( $edited );
		
		$orig_pos = 0;
		$edit_pos = 0;
		
		while ( $orig_pos < $orig_len || $edit_pos < $edit_len ) {
			// Skip matching characters
			while ( $orig_pos < $orig_len && $edit_pos < $edit_len && $original[$orig_pos] === $edited[$edit_pos] ) {
				$orig_pos++;
				$edit_pos++;
			}
			
			if ( $orig_pos >= $orig_len && $edit_pos >= $edit_len ) {
				break;
			}
			
			// Found a difference, mark the start positions
			$change_orig_start = $orig_pos;
			$change_edit_start = $edit_pos;
			
			// Use a simple LCS-based approach to find where they sync again
			$sync_point = $this->find_sync_point( $original, $edited, $orig_pos, $edit_pos );
			
			if ( $sync_point !== false ) {
				$changes[] = array(
					'orig_start' => $change_orig_start,
					'edit_start' => $change_edit_start,
					'orig_end' => $sync_point['orig'],
					'edit_end' => $sync_point['edit'],
				);
				
				$orig_pos = $sync_point['orig'];
				$edit_pos = $sync_point['edit'];
			} else {
				// No sync point found, everything else is different
				$changes[] = array(
					'orig_start' => $change_orig_start,
					'edit_start' => $change_edit_start,
					'orig_end' => $orig_len,
					'edit_end' => $edit_len,
				);
				break;
			}
		}
		
		// Merge changes that are close together
		$changes = $this->merge_nearby_changes( $changes, 20 );
		
		return $changes;
	}
	
	/**
	 * Find where two strings sync up again after a difference
	 * 
	 * @since   2.17.0
	 * @return array|false Array with 'orig' and 'edit' positions, or false if no sync found
	 */
	private function find_sync_point( $original, $edited, $orig_start, $edit_start ) {
		$orig_len = strlen( $original );
		$edit_len = strlen( $edited );
		$min_match_len = 10; // Minimum matching sequence to consider synced
		$max_search = 200; // Maximum characters to search ahead
		
		// Search for a matching sequence
		for ( $orig_pos = $orig_start; $orig_pos < min( $orig_len, $orig_start + $max_search ); $orig_pos++ ) {
			for ( $edit_pos = $edit_start; $edit_pos < min( $edit_len, $edit_start + $max_search ); $edit_pos++ ) {
				// Check if we have a long enough matching sequence
				$match_len = 0;
				while ( 
					$orig_pos + $match_len < $orig_len && 
					$edit_pos + $match_len < $edit_len && 
					$original[$orig_pos + $match_len] === $edited[$edit_pos + $match_len] &&
					$match_len < $min_match_len
				) {
					$match_len++;
				}
				
				if ( $match_len >= $min_match_len ) {
					return array(
						'orig' => $orig_pos,
						'edit' => $edit_pos,
					);
				}
			}
		}
		
		return false;
	}
	
	/**
	 * Merge changes that are close together
	 * 
	 * @since   2.17.0
	 * @return array Merged changes
	 */
	private function merge_nearby_changes( $changes, $merge_distance ) {
		if ( empty( $changes ) ) {
			return $changes;
		}
		
		$merged = array();
		$current = $changes[0];
		
		for ( $i = 1; $i < count( $changes ); $i++ ) {
			$next = $changes[$i];
			
			// Check if changes are close enough to merge
			$orig_distance = $next['orig_start'] - $current['orig_end'];
			$edit_distance = $next['edit_start'] - $current['edit_end'];
			
			if ( $orig_distance <= $merge_distance && $edit_distance <= $merge_distance ) {
				// Merge with current
				$current['orig_end'] = $next['orig_end'];
				$current['edit_end'] = $next['edit_end'];
			} else {
				// Save current and start a new one
				$merged[] = $current;
				$current = $next;
			}
		}
		
		// Add the last one
		$merged[] = $current;
		
		return $merged;
	}
	
	/**
	 * Format a single change with context
	 * 
	 * @since   2.17.0
	 * @return string
	 */
	private function format_single_change( $original, $edited, $change, $context_chars ) {
		$orig_start = $change['orig_start'];
		$edit_start = $change['edit_start'];
		$orig_end = $change['orig_end'];
		$edit_end = $change['edit_end'];
		
		// Get context before the change (use original string for context positioning)
		$context_start = max( 0, $orig_start - $context_chars );
		
		// Adjust to word boundary if possible
		if ( $context_start > 0 && $orig_start > 0 ) {
			$before_text = substr( $original, $context_start, $orig_start - $context_start );
			$space_pos = strrpos( $before_text, ' ' );
			if ( $space_pos !== false ) {
				$context_start = $context_start + $space_pos + 1;
			}
		}
		
		// Get context after the change
		$context_end_orig = min( strlen( $original ), $orig_end + $context_chars );
		$context_end_edit = min( strlen( $edited ), $edit_end + $context_chars );
		
		// Adjust to word boundary for original
		if ( $context_end_orig < strlen( $original ) ) {
			$after_text = substr( $original, $context_end_orig );
			$space_pos = strpos( $after_text, ' ' );
			if ( $space_pos !== false ) {
				$context_end_orig += $space_pos;
			}
		}
		
		// Adjust to word boundary for edited
		if ( $context_end_edit < strlen( $edited ) ) {
			$after_text = substr( $edited, $context_end_edit );
			$space_pos = strpos( $after_text, ' ' );
			if ( $space_pos !== false ) {
				$context_end_edit += $space_pos;
			}
		}
		
		$result = '';
		
		// Add leading ellipsis
		if ( $context_start > 0 ) {
			$result .= '<span class="ellipsis">...</span>';
		}
		
		// Add context before change
		$before_context = substr( $original, $context_start, $orig_start - $context_start );

		// Limit to maximum 50 characters
		if ( strlen( $before_context ) > $context_chars ) {
			$before_context = substr( $before_context, -$context_chars );
			// Optionally adjust context_start for ellipsis logic
			$context_start = $orig_start - $context_chars;
		}

		if ( ! empty( $before_context ) ) {
			$result .= htmlspecialchars( $before_context, ENT_QUOTES, 'UTF-8' );
		}
		
		// Get the changed parts
		$orig_changed = substr( $original, $orig_start, $orig_end - $orig_start );
		$edit_changed = substr( $edited, $edit_start, $edit_end - $edit_start );
		
		// Find common prefix in changed parts
		$common_prefix = '';
		$min_len = min( strlen( $orig_changed ), strlen( $edit_changed ) );
		
		for ( $i = 0; $i < $min_len; $i++ ) {
			if ( $orig_changed[$i] === $edit_changed[$i] ) {
				$common_prefix .= $orig_changed[$i];
			} else {
				break;
			}
		}
		
		// Remove common prefix
		if ( ! empty( $common_prefix ) ) {
			$orig_changed = substr( $orig_changed, strlen( $common_prefix ) );
			$edit_changed = substr( $edit_changed, strlen( $common_prefix ) );
		}
		
		// Add common prefix
		if ( ! empty( $common_prefix ) ) {
			$result .= htmlspecialchars( $common_prefix, ENT_QUOTES, 'UTF-8' );
		}
		
		// Add changed parts (don't strip common suffix - keep it highlighted)
		if ( ! empty( $orig_changed ) ) {
			$result .= '<span class="original">' . htmlspecialchars( $orig_changed, ENT_QUOTES, 'UTF-8' ) . '</span>';
		}
		
		if ( ! empty( $edit_changed ) ) {
			$result .= '<span class="edited">' . htmlspecialchars( $edit_changed, ENT_QUOTES, 'UTF-8' ) . '</span>';
		}
		
		// Add context after change (use original for consistency)
		$after_context = substr( $original, $orig_end, $context_end_orig - $orig_end );

		// Limit to maximum 50 characters
		if ( strlen( $after_context ) > $context_chars ) {
			$after_context = substr( $after_context, 0, $context_chars );
			// Optionally adjust context_end_orig for ellipsis logic
			$context_end_orig = $orig_end + $context_chars;
		}
		
		if ( ! empty( $after_context ) ) {
			$result .= htmlspecialchars( $after_context, ENT_QUOTES, 'UTF-8' );
		}
		
		// Add trailing ellipsis
		if ( $context_end_orig < strlen( $original ) || $context_end_edit < strlen( $edited ) ) {
			$result .= '<span class="ellipsis">...</span>';
		}
		
		return $result;
	}

	/**
	 * Helper function for assembling the Results.
	 * 
	 * @since   2.17.0
	 * @return boolean
	 */
	public function append_report( $table, $report, $args ) {

		// Retrieve the existing transient.
		$results = get_transient( $this->transient_name ) ? get_transient( $this->transient_name ) : array();

		// Grab any values from the run args.
		$results['search_replace'] = isset( $args['search_replace'] ) ? $args['search_replace'] : array();
		$results['run_dry']        = isset( $args['run_dry'] ) ? $args['run_dry'] : '0';
		$results['replace_guids']  = isset( $args['replace_guids'] ) ? $args['replace_guids'] : '0';

		// Sum the values of the new and existing reports.
		$results['change']  = isset( $results['change'] ) ? $results['change'] + $report['change'] : $report['change'];
		$results['updates'] = isset( $results['updates'] ) ? $results['updates'] + $report['updates'] : $report['updates'];

		// Append the table report, or create a new one if necessary.
		if ( isset( $results['table_reports'] ) && isset( $results['table_reports'][$table] ) ) {
			$results['table_reports'][$table]['change']  = $results['table_reports'][$table]['change'] + $report['change'];
			$results['table_reports'][$table]['updates'] = $results['table_reports'][$table]['updates'] + $report['updates'];
			$results['table_reports'][$table]['end']     = $report['end'];
		} else {
			$results['table_reports'][$table] = $report;
		}

		// Count the number of tables.
		$results['tables'] = count( $results['table_reports'] );

		// Update the transient.
		if ( ! set_transient( $this->transient_name , $results, DAY_IN_SECONDS ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Updates the Site URL if necessary.
	 * 
	 * @since   2.17.0
	 * @return boolean
	 */
	public function maybe_update_site_url() {
		$option = get_option( $this->option_site_url );

		if ( $option ) {
			update_option( 'siteurl', $option );
			delete_option( $this->option_site_url );
			return true;
		}

		return false;
	}

	/**
	 * Gets the columns in a table.
	 * 
	 * @since   2.17.0
	 * @return array
	 */
	public function get_columns( $table ) {
		global $wpdb;

		$primary_key = null;
		$columns     = array();

		if ( false === $this->table_exists( $table ) ) {
			return array( $primary_key, $columns );
		}

		//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$fields = $wpdb->get_results( 'DESCRIBE ' . $table );

		if ( is_array( $fields ) ) {
			foreach ( $fields as $column ) {
				$columns[] = $column->Field;
				if ( $column->Key == 'PRI' ) {
					$primary_key = $column->Field;
				}
			}
		}

		return array( $primary_key, $columns );
	}

	/**
	 * Calculate total pages for search and replace operation
	 * 
	 * @since   2.17.0
	 * @param   array   $args
	 */
	public function get_total_pages( $args ) {
		$total_pages = 0;

		$tables    = $args['tables'];
		$page_size = $args['page_size'];

		foreach ( $tables as $table ) {

			// Get the number of rows & pages in the table.
			$pages = $this->get_pages_in_table( $table, $page_size );

			// Always include 1 page in case we have to create schemas, etc.
			if ( 0 == $pages ) {
				$pages = 1;
			}

			$total_pages += $pages;
		}

		return absint( $total_pages );
	}

	/**
	 * Returns the number of pages in a table.
	 * 
	 * @since   2.17.0
	 * @return int
	 */
	public function get_pages_in_table( $table, $page_size ) {
		global $wpdb;

		if ( false === $this->table_exists( $table ) ) {
			return 0;
		}

		$table = esc_sql( $table );
		//phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows  = $wpdb->get_var( "SELECT COUNT(*) FROM `$table`" );
		$pages = ceil( $rows / $page_size );

		return absint( $pages );
	}

	/**
	 * Mimics the mysql_real_escape_string function.
	 * 
	 * @since   2.17.0
	 * @return string
	 */
	public function mysql_escape_mimic( $input ) {
	    if ( is_array( $input ) ) {
	        return array_map( __METHOD__, $input );
	    }
	    if ( ! empty( $input ) && is_string( $input ) ) {
	        return str_replace( array( '\\', "\0", "\n", "\r", "'", '"', "\x1a" ), array( '\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z' ), $input );
	    }

	    return $input;
	}

	/**
	 * Take a serialised array and unserialise it replacing elements as needed and
	 * unserialising any subordinate arrays and performing the replace on those too.
	 *
	 * @since 2.17.0
	 * @return string|array	The original array with all elements replaced as needed.
	 */
	public function recursive_unserialize_replace( $from = '', $to = '', $data = '', $serialised = false, $case_insensitive = false, $is_regex = false ) {
		try {
			// Exit early if $data is a string but has no search matches.
			if ( is_string( $data ) && ! $is_regex ) {
				$has_match = $case_insensitive ? false !== stripos( $data, $from ) : false !== strpos( $data, $from );
				if ( ! $has_match ) {
					return $data;
				}
			}

			// Check if this is serialized data with references (r:) - don't unserialize it to preserve references
			if ( is_string( $data ) && is_serialized( $data ) && preg_match( '/[;}]r:\d+;/', $data ) ) {
				// Just do string replacement on the serialized data directly
				return $this->str_replace( $from, $to, $data, $case_insensitive, $is_regex );
			}

			if ( is_string( $data ) && ! is_serialized_string( $data ) && ( $unserialized = $this->unserialize( $data ) ) !== false ) {
				$data = $this->recursive_unserialize_replace( $from, $to, $unserialized, true, $case_insensitive, $is_regex );

			} elseif ( is_array( $data ) ) {
				$_tmp = array( );
				foreach ( $data as $key => $value ) {
					$_tmp[ $key ] = $this->recursive_unserialize_replace( $from, $to, $value, false, $case_insensitive, $is_regex );
				}

				$data = $_tmp;
				unset( $_tmp );

			} elseif ( 'object' == gettype( $data ) ) {

				if ( $this->is_object_cloneable( $data ) ) {

					$_tmp  = clone $data;
					$props = get_object_vars( $data );
					
					foreach ( $props as $key => $value ) {
						// Integer properties are crazy and the best thing we can do is to just ignore them.
						// see http://stackoverflow.com/a/10333200
						if ( is_int( $key ) ) {
							continue;
						}

						// Skip any representation of a protected property
						// https://github.com/deliciousbrains/better-search-replace/issues/71#issuecomment-1369195244
						if ( is_string( $key ) && 1 === preg_match( "/^(\\\\0).+/im", preg_quote( $key ) ) ) {
							continue;
						}

						$_tmp->$key = $this->recursive_unserialize_replace( $from, $to, $value, false, $case_insensitive, $is_regex );
					}

					$data = $_tmp;
					unset( $_tmp );
				}

			} elseif ( is_serialized_string( $data ) ) {
				$unserialized = $this->unserialize( $data );

				if ( $unserialized !== false ) {
					$data = $this->recursive_unserialize_replace( $from, $to, $unserialized, true, $case_insensitive, $is_regex );
				}

			} else {
				if ( is_string( $data ) ) {
					$data = $this->str_replace( $from, $to, $data, $case_insensitive, $is_regex );
				}
			}

			if ( $serialised ) {
				return serialize( $data );
			}
		} catch( Exception $error ) {

		}

		return $data;
	}

	/**
	 * Return unserialized object or array
	 *
	 * @since 2.17.0
	 * @return mixed, false on failure
	 */
	public static function unserialize( $serialized_string ) {
		if ( ! is_serialized( $serialized_string ) ) {
			return false;
		}

		$serialized_string   = trim( $serialized_string );
		$unserialized_string = @unserialize( $serialized_string, array('allowed_classes' => false ) );

		return $unserialized_string;
	}

	/**
	 * Wrapper for str_replace
	 *
	 * @since 2.17.0
	 * @return string
	 */
	public function str_replace( $from, $to, $data, $case_insensitive = false, $is_regex = false ) {
		if ( $is_regex ) {
			// Use regex replacement
			$pattern = $from;
			// Add case insensitive flag if needed
			if ( $case_insensitive ) {
				// Check if pattern has delimiters, if not add them
				if ( ! preg_match( '/^[#\/~].*[#\/~][imsxADSUXJu]*$/', $pattern ) ) {
					$pattern = '/' . $pattern . '/i';
				} elseif ( ! preg_match( '/i[msxADSUXJu]*$/', $pattern ) ) {
					$pattern = rtrim( $pattern, $pattern[0] ) . 'i' . $pattern[0];
				}
			} else {
				// Add delimiters if not present
				if ( ! preg_match( '/^[#\/~].*[#\/~][imsxADSUXJu]*$/', $pattern ) ) {
					$pattern = '/' . $pattern . '/';
				}
			}
			
			// Suppress warnings for invalid regex patterns
			$result = @preg_replace( $pattern, $to, $data );
			
			// If regex failed, return original data
			if ( $result === null ) {
				return $data;
			}
			
			$data = $result;
		} else {
			// Use regular string replacement
			if ( $case_insensitive ) {
				$data = str_ireplace( $from, $to, $data );
			} else {
				$data = str_replace( $from, $to, $data );
			}
		}

		return $data;
	}

	/**
	 * Check if a given object can be cloned.
	 *
	 * @since 2.17.0
	 * @return bool
	 */
	public function is_object_cloneable( $object ) {
		return ( new \ReflectionClass( get_class( $object ) ) )->isCloneable();
	}

	/**
	 * Checks whether a table exists in DB.
	 *
	 * @since   2.17.0
	 * @param $table
	 * @return bool
	 */
	public function table_exists( $table ) {
		return in_array( $table, $this->get_tables() );
	}

	/**
	 * Returns an array of tables in the database.
	 * 
	 * @since   2.17.0
	 * @return array
	 */
	public function get_tables() {
		global $wpdb;

		if ( function_exists( 'is_multisite' ) && is_multisite() ) {

			if ( is_main_site() ) {
				//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$tables = $wpdb->get_col( 'SHOW TABLES' );
			} else {
				$blog_id = get_current_blog_id();
				//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$tables  = $wpdb->get_col( "SHOW TABLES LIKE '" . $wpdb->base_prefix . absint( $blog_id ) . "\_%'" );
			}

		} else {
			//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$tables = $wpdb->get_col( 'SHOW TABLES' );
		}

		return $tables;
	}

	/**
	 * Returns an array containing the size of each database table.
	 * 
	 * @since   2.17.0
	 * @return array
	 */
	public function get_sizes() {
		global $wpdb;

		$sizes  = array();
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$tables = $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A );

		if ( is_array( $tables ) && ! empty( $tables ) ) {

			foreach ( $tables as $table ) {
				$size = round( $table['Data_length'] / 1024 / 1024, 2 );
				$sizes[$table['Name']] = $size;
			}
		}

		return $sizes;
	}

	/**
     * Add the submenu content
     * 
     * @since   2.17.0
     */
    private function submenu_content() {

		$tables    = $this->get_tables();
		$sizes     = $this->get_sizes();
		$is_pro    = wpmastertoolkit_is_pro();
		$dumy_text = '<div><span class="original">axdrecsdf</span><span class="edited">asgetbsff</span> fafaf advaf zcvbyry</div>';

		?>
			<div class="wp-mastertoolkit__section">
                <div class="wp-mastertoolkit__section__desc"><?php esc_html_e( "Search and replace data in your WordPress database safely and efficiently.", 'wpmastertoolkit'); ?></div>
                <div class="wp-mastertoolkit__section__body">

					<div class="wp-mastertoolkit__section__body__item">
						<div class="wp-mastertoolkit__section__body__item__content" id="JS-search-replace-items">
							<?php $this->search_replace_item( 1, true ); ?>
						</div>

						<div class="wp-mastertoolkit__button search-replace-add-row wp-mastertoolkit__tooltip__container">
							<button type="button" class="secondary wp-mastertoolkit__tooltip__trigger <?php echo esc_attr( ! $is_pro ? 'disabled' : '' ); ?>" id="JS-search-replace-add-row">
								<?php echo wp_kses( file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/plus.svg' ), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
								<?php esc_html_e( 'Add row', 'wpmastertoolkit' ); ?>
							</button>

							<?php if ( ! $is_pro ): ?>
							<div class="wp-mastertoolkit__tooltip">
								<div class="wp-mastertoolkit__tooltip__text"><?php esc_html_e( 'To go further, upgrade to the version', 'wpmastertoolkit' ); ?></div>
								<div class="wp-mastertoolkit__tooltip__pro"><?php esc_html_e('PRO', 'wpmastertoolkit'); ?></div>
								<div class="wp-mastertoolkit__tooltip__triangle"></div>
							</div>
							<?php endif;?>
						</div>

						<?php if ( $is_pro ) : ?>
						<script type="text/template" id="JS-search-replace-item-tmpl">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								$this->search_replace_item( '{{index}}' );
							?>
						</script>
						<?php endif; ?>
                    </div>

					<div class="wp-mastertoolkit__section__body__item">
						<div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( "Select Tables", 'wpmastertoolkit' ); ?></div>
						<div class="wp-mastertoolkit__section__body__item__content select-tables">
							<div class="wp-mastertoolkit__section__body__item__content__select-tables" id="JS-tables-container">
								<div class="wp-mastertoolkit__checkbox">
									<label class="wp-mastertoolkit__checkbox__label">
										<input type="checkbox" id="JS-select-all-tables">
										<span class="mark"></span>
										<span class="wp-mastertoolkit__checkbox__label__text"><?php esc_html_e( "Select All", 'wpmastertoolkit' ); ?></span>
									</label>
								</div>

								<?php foreach ( $tables as $table ):
									$table_size = $sizes[ $table ] ?? '0';
								?>
									<div class="wp-mastertoolkit__checkbox">
										<label class="wp-mastertoolkit__checkbox__label">
											<input type="checkbox" value="<?php echo esc_attr( $table ); ?>" data-size="<?php echo esc_attr( $table_size ); ?>">
											<span class="mark"></span>
											<span class="wp-mastertoolkit__checkbox__label__text">
												<?php echo esc_html($table); ?> <span>(<?php echo esc_html($table_size); ?>MB)</span>
											</span>
										</label>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					</div>

					<div class="wp-mastertoolkit__section__body__item">
						<div class="wp-mastertoolkit__section__body__item__title">
							<?php esc_html_e( 'Max Page Size', 'wpmastertoolkit' ); ?>
						</div>
						<div class="wp-mastertoolkit__section__body__item__content">
							<div class="wp-mastertoolkit__input-text">
								<input type="number" value="20000" style="width: 100px;" min="1000" max="50000" step="1000" id="JS-max-page-size">
							</div>
							<div class="description"><?php esc_html_e( 'If you notice timeouts or are unable to backup/import the database, try decreasing this value.', 'wpmastertoolkit' ); ?></div>
						</div>
					</div>

					<div class="wp-mastertoolkit__section__body__item">
						<div class="wp-mastertoolkit__section__body__item__title activable">
							<div>
								<label class="wp-mastertoolkit__toggle">
									<input class="wp-mastertoolkit__sortable__item__hide" type="checkbox" id="JS-replace-guids">
									<span class="wp-mastertoolkit__toggle__slider round"></span>
								</label>
							</div>
							<div><?php esc_html_e( "Replace GUIDs", 'wpmastertoolkit' ); ?></div>
						</div>
						<div class="wp-mastertoolkit__section__body__item__content">
							<div><?php esc_html_e( "Activate this option to include 'guid' columns in the search and replace operation.", 'wpmastertoolkit' ); ?></div>
						</div>
					</div>

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__content">
                        	<div class="wp-mastertoolkit__section__body__item__content__buttons">
								<div class="wp-mastertoolkit__button search-replace-dry-run">
									<button type="button" class="secondary" id="JS-search-replace-dry-run">
										<?php echo wp_kses( file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/flask.svg' ), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
										<?php esc_html_e( 'Run as dry run', 'wpmastertoolkit' ); ?>
										<span class="spinner"></span>
									</button>
								</div>

								<div class="wp-mastertoolkit__button search-replace-run">
									<button type="button" class="primary" id="JS-search-replace-real-run">
										<?php echo wp_kses( file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/replace.svg' ), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
										<?php esc_html_e( 'Search & Replace', 'wpmastertoolkit' ); ?>
										<span class="spinner"></span>
									</button>
								</div>
							</div>

							<div class="description" id="JS-search-replace-message"></div>
                        </div>
                    </div>

				</div>
            </div>

			<div class="wp-mastertoolkit__section search-replace-dry-run-section" id="JS-search-replace-dry-run-section">
				<div class="wp-mastertoolkit__section__body">
					<div class="wp-mastertoolkit__section__body__header">
						<div class="wp-mastertoolkit__section__body__header__title">
							<?php echo wp_kses( file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/flask.svg' ), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
							<?php esc_html_e( 'Dry run', 'wpmastertoolkit' ); ?>
						</div>
						<div class="wp-mastertoolkit__section__body__header__status" id="JS-search-replace-dry-run-status">(<?php esc_html_e( 'Progression', 'wpmastertoolkit' ); ?>)</div>
					</div>
					<div class="wp-mastertoolkit__section__body__body">
						<div class="wp-mastertoolkit__section__body__body__top">
							<div class="wp-mastertoolkit__section__body__body__data">
								<div class="wp-mastertoolkit__section__body__body__data__percentage" id="JS-search-replace-dry-run-percentage">0%</div>
								<div class="wp-mastertoolkit__section__body__body__data__size" id="JS-search-replace-dry-run-size">(0 MB / 0 MB)</div>
								<div class="wp-mastertoolkit__section__body__body__data__time" id="JS-search-replace-dry-run-time">00:00:00</div>
							</div>
							<div class="wp-mastertoolkit__section__body__body__actions">
								<span class="wp-mastertoolkit__section__body__body__actions__stop" id="JS-search-replace-dry-run-stop">
									<span class="wp-mastertoolkit__section__body__body__actions__stop__icon"></span>
									<?php esc_html_e( 'Stop', 'wpmastertoolkit' ); ?>
								</span>
							</div>
						</div>
						<div class="wp-mastertoolkit__section__body__body__bottom">
							<div class="wp-mastertoolkit__section__body__body__progress-bar">
								<div class="wp-mastertoolkit__section__body__body__progress-bar__fill" id="JS-search-replace-dry-run-progress-bar"></div>
							</div>
						</div>
					</div>
					<div class="wp-mastertoolkit__section__body__footer">
						<?php
							echo wp_kses_post( sprintf(
								/* translators: 1: tables, 2: cells, 3: changes */
								__( '%1$s tables were searched, %2$s cells were found that need to be updated, %3$s changes were made.', 'wpmastertoolkit' ),
								'<strong id="JS-search-replace-dry-run-tables">0</strong>',
								'<strong id="JS-search-replace-dry-run-cells">0</strong>',
								'<strong id="JS-search-replace-dry-run-changes">0</strong>',
							) );
						?>
					</div>
				</div>
			</div>

			<div class="wp-mastertoolkit__section search-replace-real-run-section" id="JS-search-replace-real-run-section">
				<div class="wp-mastertoolkit__section__body">
					<div class="wp-mastertoolkit__section__body__header">
						<div class="wp-mastertoolkit__section__body__header__title">
							<?php echo wp_kses( file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/replace.svg' ), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
							<?php esc_html_e( 'Search & Replace', 'wpmastertoolkit' ); ?>
						</div>
						<div class="wp-mastertoolkit__section__body__header__status" id="JS-search-replace-real-run-status">(<?php esc_html_e( 'Progression', 'wpmastertoolkit' ); ?>)</div>
					</div>
					<div class="wp-mastertoolkit__section__body__body">
						<div class="wp-mastertoolkit__section__body__body__top">
							<div class="wp-mastertoolkit__section__body__body__data">
								<div class="wp-mastertoolkit__section__body__body__data__percentage" id="JS-search-replace-real-run-percentage">0%</div>
								<div class="wp-mastertoolkit__section__body__body__data__size" id="JS-search-replace-real-run-size">(0 MB / 0 MB)</div>
								<div class="wp-mastertoolkit__section__body__body__data__time" id="JS-search-replace-real-run-time">00:00:00</div>
							</div>
							<div class="wp-mastertoolkit__section__body__body__actions">
								<span class="wp-mastertoolkit__section__body__body__actions__stop" id="JS-search-replace-real-run-stop">
									<span class="wp-mastertoolkit__section__body__body__actions__stop__icon"></span>
									<?php esc_html_e( 'Stop', 'wpmastertoolkit' ); ?>
								</span>
							</div>
						</div>
						<div class="wp-mastertoolkit__section__body__body__bottom">
							<div class="wp-mastertoolkit__section__body__body__progress-bar">
								<div class="wp-mastertoolkit__section__body__body__progress-bar__fill" id="JS-search-replace-real-run-progress-bar"></div>
							</div>
						</div>
					</div>
					<div class="wp-mastertoolkit__section__body__footer">
						<?php
							echo wp_kses_post( sprintf(
								/* translators: 1: tables, 2: cells, 3: changes */
								__( '%1$s tables were searched, %2$s cells were found that need to be updated, %3$s changes were made.', 'wpmastertoolkit' ),
								'<strong id="JS-search-replace-real-run-tables">0</strong>',
								'<strong id="JS-search-replace-real-run-cells">0</strong>',
								'<strong id="JS-search-replace-real-run-changes">0</strong>',
							) );
						?>
					</div>
				</div>
			</div>

			<div class="wp-mastertoolkit__section search-replace-results-section" id="JS-search-replace-results-section">
				<div class="wp-mastertoolkit__section__body">
					<div class="wp-mastertoolkit__section__body__item">
						<div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( "Preview change", 'wpmastertoolkit' ); ?></div>
						<div class="wp-mastertoolkit__section__body__item__content">

							<div class="wp-mastertoolkit__section__body__item__content__results" id="JS-search-replace-results"></div>

							<?php if ( ! wpmastertoolkit_is_pro() ) : ?>
							<div class="wp-mastertoolkit__section__body__item__content__dumy-pro" id="JS-search-replace-dumy-pro">
								<div class="wp-mastertoolkit__tooltip">
									<div class="wp-mastertoolkit__tooltip__text"><?php esc_html_e( 'To view all hidden results, upgrade to the version', 'wpmastertoolkit' ); ?></div>
									<div class="wp-mastertoolkit__tooltip__pro"><?php esc_html_e( 'PRO', 'wpmastertoolkit' ); ?></div>
								</div>
							</div>
							<div class="wp-mastertoolkit__section__body__item__content__dumy" id="JS-search-replace-dumy">
								<?php echo wp_kses_post( (string)$this->search_replace_replace_item( 'azxerr_asdfg', '13', 'asdfer_gbdfv', $dumy_text ) ); ?>
								<?php echo wp_kses_post( (string)$this->search_replace_replace_item( 'piieqrtu_pouqwie', '34', 'asdv_oiproytu', $dumy_text ) ); ?>
								<?php echo wp_kses_post( (string)$this->search_replace_replace_item( 'xzcvotu_asdfafg', '29', 'asdg_qwerwet', $dumy_text ) ); ?>
							</div>
							<?php endif; ?>

							<script type="text/template" id="JS-search-replace-results-tmpl">
								<?php
									// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									$this->search_replace_replace_item();
								?>
							</script>
						</div>
					</div>
				</div>
			</div>
		<?php
    }

	/**
	 * Render search place item
	 * 
	 * @since   2.17.0
	 */
	private function search_replace_item( $index, $first = false ) {
		$is_pro = wpmastertoolkit_is_pro();
		?>
		<div class="wp-mastertoolkit__input-text flex search-replace-item" data-index="<?php echo esc_attr( $index ); ?>">

			<div class="full-width">
				<div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e('Search for', 'wpmastertoolkit'); ?></div>
				<input type="text" class="JS-search-replace-item-search">
			</div>

			<div class="icon-plus"><?php echo wp_kses( file_get_contents(WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/arrow-right-long.svg'), wpmastertoolkit_allowed_tags_for_svg_files() ); ?></div>

			<div class="full-width">
				<div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e('Replace with', 'wpmastertoolkit'); ?></div>
				<input type="text" class="JS-search-replace-item-replace">
			</div>

			<div class="buttons">
				<label class="wp-mastertoolkit__tooltip__container" title="<?php esc_attr_e( 'Use regular expressions', 'wpmastertoolkit' ); ?>">
					<input type="checkbox" class="JS-search-replace-item-regex" <?php disabled( $is_pro, false ); ?>>
					<span class="wp-mastertoolkit__tooltip__trigger">.*</span>

					<?php if ( ! $is_pro ): ?>
					<div class="wp-mastertoolkit__tooltip">
						<div class="wp-mastertoolkit__tooltip__text"><?php esc_html_e( 'To go further, upgrade to the version', 'wpmastertoolkit' ); ?></div>
						<div class="wp-mastertoolkit__tooltip__pro"><?php esc_html_e('PRO', 'wpmastertoolkit'); ?></div>
						<div class="wp-mastertoolkit__tooltip__triangle"></div>
					</div>
					<?php endif;?>
				</label>
				
				<label title="<?php esc_attr_e( 'Match case', 'wpmastertoolkit' ); ?>">
					<input type="checkbox" class="JS-search-replace-item-match-case" checked="checked">
					<span>Aa</span>
				</label>
			</div>

			<div class="actions">
				<span class="JS-search-replace-item-remove remove <?php echo esc_attr( $first ? 'first' : '' ); ?>"><?php echo wp_kses( file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/times.svg' ), wpmastertoolkit_allowed_tags_for_svg_files() ); ?></span>
			</div>

		</div>
		<?php
	}

	/**
	 * Render search replace result item
	 * 
	 * @since   2.17.0
	 */
	private function search_replace_replace_item( $table = '{{table}}', $row_id = '{{row_id}}', $column = '{{column}}', $text = '{{text}}' ) {
		?>
			<div class="wp-mastertoolkit__section__body__item__content__results__item">
				<div class="wp-mastertoolkit__section__body__item__content__results__item__top">
					<div class="wp-mastertoolkit__section__body__item__content__results__item__top__table"><?php echo esc_html( $table ); ?></div>
					<div class="wp-mastertoolkit__section__body__item__content__results__item__top__row">
						<div class="wp-mastertoolkit__section__body__item__content__results__item__top__row__label"><?php esc_html_e( 'Row ID:', 'wpmastertoolkit' ); ?></div>
						<div class="wp-mastertoolkit__section__body__item__content__results__item__top__row__value"><?php echo esc_html( $row_id ); ?></div>
					</div>
					<div class="wp-mastertoolkit__section__body__item__content__results__item__top__column">
						<div class="wp-mastertoolkit__section__body__item__content__results__item__top__column__label"><?php esc_html_e( 'Column:', 'wpmastertoolkit' ); ?></div>
						<div class="wp-mastertoolkit__section__body__item__content__results__item__top__column__value"><?php echo esc_html( $column ); ?></div>
					</div>
				</div>
				<div class="wp-mastertoolkit__section__body__item__content__results__item__bottom"><?php echo wp_kses_post( $text ); ?></div>
			</div>
		<?php
	}
}
