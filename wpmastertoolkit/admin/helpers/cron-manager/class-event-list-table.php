<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

/**
 * Cron event list table class.
 * 
 * @since 1.11.0
 */
class WPMastertoolkit_Cron_Manager_Event_List_Table extends WP_List_Table {

	/**
	 * Array of cron event hooks that are persistently added by WordPress core.
	 *
	 * @var array<int,string> Array of hook names.
	 */
	protected static $persistent_core_hooks;

	/**
	 * Whether the current user has the capability to create or edit PHP cron events.
	 *
	 * @var bool Whether the user can create or edit PHP cron events.
	 */
	protected static $can_manage_php_crons;

	/**
	 * Array of the count of each hook.
	 *
	 * @var array<string,int> Array of count of each hooked, keyed by hook name.
	 */
	protected static $count_by_hook;

	/**
	 * Array of all cron events.
	 *
	 * @var array<string,stdClass> Array of event objects.
	 */
	protected $all_events = array();

	/**
	 * Cron manager class.
	 *
	 * @var WPMastertoolkit_Cron_Manager
	 */
	protected static $class_cron_manager;

	/**
	 * Constructor.
	 */
	public function __construct() {
		self::$class_cron_manager = new WPMastertoolkit_Cron_Manager();

		parent::__construct( array(
			'singular' => 'wpmastertoolkit-event',
			'plural'   => 'wpmastertoolkit-events',
			'ajax'     => false,
			'screen'   => 'wpmastertoolkit-events',
		) );
	}

	/**
	 * Prepares the list table items and arguments.
	 *
	 * @return void
	 */
	public function prepare_items() {
		self::$persistent_core_hooks = self::$class_cron_manager->get_persistent_core_hooks();
		self::$can_manage_php_crons  = current_user_can( 'edit_files' );
		self::$count_by_hook         = self::$class_cron_manager->count_by_hook();

		$events           = self::$class_cron_manager->get_events();
		$this->all_events = $events;

		if ( ! empty( $_GET['s'] ) && is_string( $_GET['s'] ) ) {
			$s = sanitize_text_field( wp_unslash( $_GET['s'] ) );

			$events = array_filter( $events, function( $event ) use ( $s ) {
				return ( false !== strpos( $event->hook, $s ) );
			} );
		}

		if ( ! empty( $_GET['wpmastertoolkit_hooks_type'] ) && is_string( $_GET['wpmastertoolkit_hooks_type'] ) ) {
			$hooks_type = sanitize_text_field( wp_unslash( $_GET['wpmastertoolkit_hooks_type'] ) );
			$filtered   = self::get_filtered_events( $events );

			if ( isset( $filtered[ $hooks_type ] ) ) {
				$events = $filtered[ $hooks_type ];
			}
		}

		$count    = count( $events );
		$per_page = 50;
		$offset   = ( $this->get_pagenum() - 1 ) * $per_page;

		$this->items = array_slice( $events, $offset, $per_page );

		$has_integrity_failures = (bool) array_filter( array_map( array( self::$class_cron_manager, 'integrity_failed' ), $this->items ) );
		$has_late = (bool) array_filter( array_map( array( self::$class_cron_manager, 'is_late' ), $this->items ) );

		$this->set_pagination_args( array(
			'total_items' => $count,
			'per_page'    => $per_page,
			'total_pages' => (int) ceil( $count / $per_page ),
		) );
	}

	/**
	 * Returns events filtered by various parameters
	 *
	 * @param array<string,stdClass> $events The list of all events.
	 * @return array<string,array<string,stdClass>> Array of filtered events keyed by filter name.
	 */
	public static function get_filtered_events( array $events ) {
		$all_core_hooks = self::$class_cron_manager->get_all_core_hooks();
		$filtered       = array(
			'all' => $events,
		);

		$filtered['noaction'] = array_filter( $events, function( $event ) {
			$hook_callbacks = self::$class_cron_manager->get_hook_callbacks( $event->hook );
			return empty( $hook_callbacks );
		} );

		$filtered['core'] = array_filter( $events, function( $event ) use ( $all_core_hooks ) {
			return ( in_array( $event->hook, $all_core_hooks, true ) );
		} );

		$filtered['custom'] = array_filter( $events, function( $event ) use ( $all_core_hooks ) {
			return ( ! in_array( $event->hook, $all_core_hooks, true ) );
		} );

		$filtered['php'] = array_filter( $events, function( $event ) {
			return ( 'wpmastertoolkit_cron_job' === $event->hook );
		} );

		$filtered['url'] = array_filter( $events, function( $event ) {
			return ( 'wpmastertoolkit_url_cron_job' === $event->hook );
		} );

		$filtered['paused'] = array_filter( $events, function( $event ) {
			return ( self::$class_cron_manager->is_paused( $event ) );
		} );

		return $filtered;
	}

	/**
	 * Returns an array of column names for the table.
	 *
	 * @return array<string,string> Array of column names keyed by their ID.
	 */
	public function get_columns() {
		return array(
			'cb'                       => '',
			'wpmastertoolkit_hook'     => esc_html__( 'Hook', 'wpmastertoolkit' ),
			'wpmastertoolkit_next'     => esc_html(
				sprintf(
					/* translators: %s: UTC offset */
					__( 'Next Run (%s)', 'wpmastertoolkit' ),
					self::$class_cron_manager->get_utc_offset()
				),
			),
			'wpmastertoolkit_actions'  => esc_html__( 'Action', 'wpmastertoolkit' ),
			'wpmastertoolkit_schedule' => esc_html_x( 'Schedule', 'noun', 'wpmastertoolkit' ),
		);
	}

	/**
	 * Columns to make sortable.
	 *
	 * @return array<string,array<int,mixed>>
	 * @phpstan-return array<string,array{
	 *   0: string,
	 *   1: bool,
	 *   2?: ?string,
	 *   3?: ?string,
	 *   4?: 'asc'|'desc',
	 * }>
	 */
	public function get_sortable_columns() {
		return array(
			'wpmastertoolkit_hook'     => array( 'wpmastertoolkit_hook', false ),
			'wpmastertoolkit_next'     => array( 'wpmastertoolkit_next', false, null, null, 'asc' ),
			'wpmastertoolkit_schedule' => array( 'wpmastertoolkit_schedule', false ),
		);
	}

	/**
	 * Returns an array of CSS class names for the table.
	 *
	 * @return array<int,string> Array of class names.
	 */
	protected function get_table_classes() {
		return array( 'widefat', 'striped', 'table-view-list', $this->_args['plural'] );
	}

	/**
	 * Display the list of hook types.
	 *
	 * @return array<string,string>
	 */
	public function get_views() {
		$filtered   = self::get_filtered_events( $this->all_events );
		$views      = array();
		$hooks_type = ( ! empty( $_GET['wpmastertoolkit_hooks_type'] ) ? $_GET['wpmastertoolkit_hooks_type'] : 'all' );
		$url        = admin_url( 'admin.php?page=wp-mastertoolkit-settings-cron-manager' );
		$types      = array(
			'all'      => __( 'All events', 'wpmastertoolkit' ),
			'noaction' => __( 'Events with no action', 'wpmastertoolkit' ),
			'core'     => __( 'WordPress core events', 'wpmastertoolkit' ),
			'custom'   => __( 'Custom events', 'wpmastertoolkit' ),
			'php'      => __( 'PHP events', 'wpmastertoolkit' ),
			'url'      => __( 'URL events', 'wpmastertoolkit' ),
			'paused'   => __( 'Paused events', 'wpmastertoolkit' ),
		);

		/**
		 * @var array<string,string> $types
		 */
		foreach ( $types as $key => $type ) {
			if ( ! isset( $filtered[ $key ] ) ) {
				continue;
			}

			$count = count( $filtered[ $key ] );

			if ( ! $count ) {
				continue;
			}

			$link = ( 'all' === $key ) ? $url : add_query_arg( 'wpmastertoolkit_hooks_type', $key, $url );

			$views[ $key ] = sprintf(
				'<a href="%1$s"%2$s>%3$s <span class="count">(%4$s)</span></a>',
				esc_url( $link ),
				$hooks_type === $key ? ' class="current"' : '',
				esc_html( $type ),
				esc_html( number_format_i18n( $count ) )
			);
		}

		return $views;
	}

	/**
	 * Generates content for a single row of the table.
	 *
	 * @param stdClass $event The current event.
	 * @return void
	 */
	public function single_row( $event ) {
		$classes = array();

		if ( ( 'wpmastertoolkit_cron_job' === $event->hook ) && isset( $event->args[0]['syntax_error_message'] ) ) {
			$classes[] = 'wpmastertoolkit-error';
		}

		if ( self::$class_cron_manager->integrity_failed( $event ) ) {
			$classes[] = 'wpmastertoolkit-error';
		}

		$schedule_name = ( $event->interval ? self::$class_cron_manager->get_schedule_name( $event ) : false );

		if ( is_wp_error( $schedule_name ) ) {
			$classes[] = 'wpmastertoolkit-error';
		}

		$callbacks = self::$class_cron_manager->get_hook_callbacks( $event->hook );

		if ( ! $callbacks ) {
			$classes[] = 'wpmastertoolkit-no-action';
		} else {
			foreach ( $callbacks as $callback ) {
				if ( ! empty( $callback['callback']['error'] ) ) {
					$classes[] = 'wpmastertoolkit-error';
					break;
				}
			}
		}

		if ( self::$class_cron_manager->is_late( $event ) || self::$class_cron_manager->is_too_frequent( $event ) ) {
			$classes[] = 'wpmastertoolkit-warning';
		}

		if ( self::$class_cron_manager->is_paused( $event ) ) {
			$classes[] = 'wpmastertoolkit-paused';
		}

		printf(
			'<tr class="%s">',
			esc_attr( implode( ' ', $classes ) )
		);

		$this->single_row_columns( $event );
		echo '</tr>';
	}

	/**
	 * Generates and displays row action links for the table.
	 *
	 * @param stdClass $event       The cron event for the current row.
	 * @param string   $column_name Current column name.
	 * @param string   $primary     Primary column name.
	 * @return string The row actions HTML.
	 */
	protected function handle_row_actions( $event, $column_name, $primary ) {
		if ( $primary !== $column_name ) {
			return '';
		}

		$links = array();

		if ( ! self::$class_cron_manager->is_paused( $event ) && ! self::$class_cron_manager->integrity_failed( $event ) ) {
			$link = array(
				'page'                          => 'wp-mastertoolkit-settings-cron-manager',
				'wpmastertoolkit_action'        => 'wpmastertoolkit-run-cron',
				'wpmastertoolkit_id'            => rawurlencode( $event->hook ),
				'wpmastertoolkit_sig'           => rawurlencode( $event->sig ),
				'wpmastertoolkit_next_run_utc'  => rawurlencode( $event->timestamp ),
			);
			$link = add_query_arg( $link, admin_url( 'admin.php' ) );
			$link = wp_nonce_url( $link, "wpmastertoolkit-run-cron_{$event->hook}_{$event->sig}" );

			$links[] = "<a href='" . esc_url( $link ) . "'>" . esc_html__( 'Run now', 'wpmastertoolkit' ) . '</a>';
		}

		if ( self::$class_cron_manager->is_paused( $event ) ) {
			$link = array(
				'page'                   => 'wp-mastertoolkit-settings-cron-manager',
				'wpmastertoolkit_action' => 'wpmastertoolkit-resume-hook',
				'wpmastertoolkit_id'     => rawurlencode( $event->hook ),
			);
			$link = add_query_arg( $link, admin_url( 'admin.php' ) );
			$link = wp_nonce_url( $link, "wpmastertoolkit-resume-hook_{$event->hook}" );

			/* translators: Resume is a verb */
			$links[] = "<a href='" . esc_url( $link ) . "'>" . esc_html__( 'Resume this hook', 'wpmastertoolkit' ) . '</a>';
		} elseif ( 'wpmastertoolkit_cron_job' !== $event->hook && 'wpmastertoolkit_url_cron_job' !== $event->hook ) {
			$link = array(
				'page'                   => 'wp-mastertoolkit-settings-cron-manager',
				'wpmastertoolkit_action' => 'wpmastertoolkit-pause-hook',
				'wpmastertoolkit_id'     => rawurlencode( $event->hook ),
			);
			$link = add_query_arg( $link, admin_url( 'admin.php' ) );
			$link = wp_nonce_url( $link, "wpmastertoolkit-pause-hook_{$event->hook}" );

			/* translators: Pause is a verb */
			$links[] = "<a href='" . esc_url( $link ) . "'>" . esc_html__( 'Pause this hook', 'wpmastertoolkit' ) . '</a>';
		}

		if ( ! in_array( $event->hook, self::$persistent_core_hooks, true ) && ( ( 'wpmastertoolkit_cron_job' !== $event->hook ) || self::$can_manage_php_crons ) ) {
			$link = array(
				'page'                         => 'wp-mastertoolkit-settings-cron-manager',
				'wpmastertoolkit_action'       => 'wpmastertoolkit-delete-cron',
				'wpmastertoolkit_id'           => rawurlencode( $event->hook ),
				'wpmastertoolkit_sig'          => rawurlencode( $event->sig ),
				'wpmastertoolkit_next_run_utc' => rawurlencode( $event->timestamp ),
			);
			$link = add_query_arg( $link, admin_url( 'admin.php' ) );
			$link = wp_nonce_url( $link, "wpmastertoolkit-delete-cron_{$event->hook}_{$event->sig}_{$event->timestamp}" );

			$links[] = "<span class='delete'><a href='" . esc_url( $link ) . "'>" . esc_html__( 'Delete', 'wpmastertoolkit' ) . '</a></span>';
		}

		if ( 'wpmastertoolkit_cron_job' !== $event->hook && 'wpmastertoolkit_url_cron_job' !== $event->hook ) {
			if ( self::$count_by_hook[ $event->hook ] > 1 ) {
				$link = array(
					'page'                   => 'wp-mastertoolkit-settings-cron-manager',
					'wpmastertoolkit_action' => 'wpmastertoolkit-delete-hook',
					'wpmastertoolkit_id'     => rawurlencode( $event->hook ),
				);
				$link = add_query_arg( $link, admin_url( 'admin.php' ) );
				$link = wp_nonce_url(
					$link,
					sprintf(
						'wpmastertoolkit-delete-hook_%1$s',
						$event->hook
					)
				);
				$text = sprintf(
					/* translators: %s: The number of events with this hook */
					__( 'Delete all events with this hook (%s)', 'wpmastertoolkit' ),
					number_format_i18n( self::$count_by_hook[ $event->hook ] )
				);

				$links[] = sprintf(
					'<span class="delete"><a href="%1$s">%2$s</a></span>',
					esc_url( $link ),
					esc_html( $text )
				);
			}
		}

		return $this->row_actions( $links );
	}

	/**
	 * Outputs the checkbox cell of a table row.
	 *
	 * @param stdClass $event The cron event for the current row.
	 * @return string The cell output.
	 */
	protected function column_cb( $event ) {
		$id = sprintf(
			'wpmastertoolkit-delete-%1$s-%2$s-%3$s',
			$event->timestamp,
			rawurlencode( $event->hook ),
			$event->sig
		);

		if ( in_array( $event->hook, self::$persistent_core_hooks, true ) ) {
			return sprintf(
				'<span class="dashicons dashicons-wordpress" aria-hidden="true"></span>
				<span class="screen-reader-text">%s</span>',
				esc_html__( 'This is a WordPress core event and cannot be deleted', 'wpmastertoolkit' )
			);
		}

		return '';
	}

	/**
	 * Returns the output for the hook name cell of a table row.
	 *
	 * @param stdClass $event The cron event for the current row.
	 * @return string The cell output.
	 */
	protected function column_wpmastertoolkit_hook( $event ) {
		if ( 'wpmastertoolkit_cron_job' === $event->hook ) {
			if ( ! empty( $event->args[0]['name'] ) ) {
				/* translators: %s: Details about the PHP cron event. */
				$output = esc_html( sprintf( __( 'PHP cron event (%s)', 'wpmastertoolkit' ), $event->args[0]['name'] ) );
			} elseif ( ! empty( $event->args[0]['code'] ) ) {
				$lines = explode( "\n", trim( $event->args[0]['code'] ) );
				$code  = reset( $lines );
				$code  = substr( $code, 0, 50 );

				$php = sprintf(
					'<code>%s</code>&hellip;',
					esc_html( $code )
				);

				/* translators: %s: Details about the PHP cron event. */
				$output = sprintf( esc_html__( 'PHP cron event (%s)', 'wpmastertoolkit' ), $php );
			} else {
				$output = esc_html__( 'PHP cron event', 'wpmastertoolkit' );
			}

			if ( self::$class_cron_manager->integrity_failed( $event ) ) {
				$output .= sprintf(
					' &mdash; <strong class="status-wpmastertoolkit-check post-state"><span class="dashicons dashicons-warning" aria-hidden="true"></span> %s</strong>',
					esc_html__( 'Needs checking', 'wpmastertoolkit' )
				);
			}

			if ( isset( $event->args[0]['syntax_error_message'], $event->args[0]['syntax_error_line'] ) ) {
				$output .= '<br><span class="status-wpmastertoolkit-error"><span class="dashicons dashicons-warning" aria-hidden="true"></span> ';
				$output .= sprintf(
					/* translators: 1: Line number, 2: Error message text */
					esc_html__( 'Line %1$s: %2$s', 'wpmastertoolkit' ),
					esc_html( number_format_i18n( $event->args[0]['syntax_error_line'] ) ),
					esc_html( $event->args[0]['syntax_error_message'] )
				);
				$output .= '</span>';
			}

			return $output;
		}

		if ( 'wpmastertoolkit_url_cron_job' === $event->hook ) {
			if ( ! empty( $event->args[0]['name'] ) ) {
				/* translators: %s: Details about the URL cron event. */
				$output = esc_html( sprintf( __( 'URL cron event (%s)', 'wpmastertoolkit' ), $event->args[0]['name'] ) );
			} elseif ( ! empty( $event->args[0]['url'] ) ) {
				$url = sprintf(
					'<code>%s</code>',
					esc_html( $event->args[0]['url'] )
				);

				/* translators: %s: Details about the URL cron event. */
				$output = sprintf( esc_html__( 'URL cron event (%s)', 'wpmastertoolkit' ), $url );
			} else {
				$output = esc_html__( 'URL cron event', 'wpmastertoolkit' );
			}

			return $output;
		}

		$output = esc_html( $event->hook );

		if ( self::$class_cron_manager->is_paused( $event ) ) {
			$output .= sprintf(
				' &mdash; <strong class="status-wpmastertoolkit-paused post-state"><span class="dashicons dashicons-controls-pause" aria-hidden="true"></span> %s</strong>',
				/* translators: State of a cron event, adjective */
				esc_html__( 'Paused', 'wpmastertoolkit' )
			);
		}

		if ( ! empty( $event->args ) ) {
			$output .= sprintf(
				'<br><br><pre>%s</pre>',
				esc_html( self::$class_cron_manager->json_output( $event->args ) )
			);
		}

		return $output;
	}

	/**
	 * Returns the output for the actions cell of a table row.
	 *
	 * @param stdClass $event The cron event for the current row.
	 * @return string The cell output.
	 */
	protected function column_wpmastertoolkit_actions( $event ) {
		$hook_callbacks = self::$class_cron_manager->get_hook_callbacks( $event->hook );

		if ( 'wpmastertoolkit_cron_job' === $event->hook || 'wpmastertoolkit_url_cron_job' === $event->hook ) {
			return 'WPMasterToolkit';
		} elseif ( ! empty( $hook_callbacks ) ) {
			$callbacks = array();

			foreach ( $hook_callbacks as $callback ) {
				$callbacks[] = self::$class_cron_manager->output_callback( $callback );
			}

			return implode( '<br>', $callbacks ); // WPCS:: XSS ok.
		} else {
			return sprintf(
				'<span class="status-wpmastertoolkit-warning"><span class="dashicons dashicons-warning" aria-hidden="true"></span> %s</span>',
				esc_html__( 'None', 'wpmastertoolkit' )
			);
		}
	}

	/**
	 * Returns the output for the next run cell of a table row.
	 *
	 * @param stdClass $event The cron event for the current row.
	 * @return string The cell output.
	 */
	protected function column_wpmastertoolkit_next( $event ) {
		$date_local_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		$offset_site = get_date_from_gmt( 'now', 'P' );
		$offset_event = get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $event->timestamp ), 'P' );

		// If the timezone of the date of the event is different from the site timezone, add a marker.
		if ( $offset_site !== $offset_event ) {
			$date_local_format .= ' (P)';
		}

		$date_utc   = gmdate( 'c', $event->timestamp );
		$date_local = get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $event->timestamp ), $date_local_format );

		$time = sprintf(
			'<time datetime="%1$s">%2$s</time>',
			esc_attr( $date_utc ),
			esc_html( $date_local )
		);

		$until = $event->timestamp - time();
		$late  = self::$class_cron_manager->is_late( $event );

		if ( $late ) {
			// Show a warning for events that are late.
			$ago = sprintf(
				/* translators: %s: Time period, for example "8 minutes" */
				__( '%s ago', 'wpmastertoolkit' ),
				self::$class_cron_manager->interval( abs( $until ) )
			);
			return sprintf(
				'%s<br><span class="status-wpmastertoolkit-warning"><span class="dashicons dashicons-warning" aria-hidden="true"></span> %s</span>',
				$time,
				esc_html( $ago )
			);
		}

		return sprintf(
			'%s<br>%s',
			$time,
			esc_html( self::$class_cron_manager->interval( $until ) )
		);
	}

	/**
	 * Returns the output for the schedule cell of a table row.
	 *
	 * @param stdClass $event The cron event for the current row.
	 * @return string The cell output.
	 */
	protected function column_wpmastertoolkit_schedule( $event ) {
		if ( $event->schedule ) {
			$schedule_name = self::$class_cron_manager->get_schedule_name( $event );
			if ( is_wp_error( $schedule_name ) ) {
				return sprintf(
					'<span class="status-wpmastertoolkit-error"><span class="dashicons dashicons-warning" aria-hidden="true"></span> %s</span>',
					esc_html( $schedule_name->get_error_message() )
				);
			} elseif ( self::$class_cron_manager->is_too_frequent( $event ) ) {
				return sprintf(
					'%1$s<span class="status-wpmastertoolkit-warning"><br><span class="dashicons dashicons-warning" aria-hidden="true"></span> %2$s</span>',
					esc_html( $schedule_name ),
					sprintf(
						/* translators: 1: The name of the configuration constant, 2: The value of the configuration constant */
						esc_html__( 'This interval is less than the %1$s constant which is set to %2$s seconds. Events that use it may not run on time.', 'wpmastertoolkit' ),
						'<code>WP_CRON_LOCK_TIMEOUT</code>',
						intval( WP_CRON_LOCK_TIMEOUT )
					)
				);
			} else {
				return esc_html( $schedule_name );
			}
		} else {
			return esc_html__( 'Non-repeating', 'wpmastertoolkit' );
		}
	}

	/**
	 * Outputs a message when there are no items to show in the table.
	 *
	 * @return void
	 */
	public function no_items() {
		if ( empty( $_GET['s'] ) && empty( $_GET['wpmastertoolkit_hooks_type'] ) ) {
			esc_html_e( 'There are currently no scheduled cron events.', 'wpmastertoolkit' );
		} else {
			esc_html_e( 'No matching cron events.', 'wpmastertoolkit' );
		}
	}
}
