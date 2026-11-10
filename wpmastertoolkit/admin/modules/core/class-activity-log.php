<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module Name: Activity Log
 * Description: Track and manage user activity on your WordPress site.
 * @since 2.26.0
 */
class WPMastertoolkit_Activity_Log {

	private const RETENTION_HOOK          = 'wpmastertoolkit_activity_log_retention_cleanup';
	private const RETENTION_CONTINUE_HOOK = 'wpmastertoolkit_activity_log_retention_continue';

	private $option_id;
	private $header_title;
	private $nonce_action;
	private $settings = array();
	private $default_settings;
	private $page_slug;

	/**
	 * Sites whose orphaned access restrictions were recovered on this request.
	 *
	 * @var bool[]
	 */
	private $access_recovered = array();

	/**
	 * Exact provider definitions and retained observers.
	 *
	 * @var WPMastertoolkit_Activity_Log_Registry
	 */
	private $registry;

	/**
	 * Whether any built-in provider was rejected during registration.
	 *
	 * @var bool
	 */
	private $provider_errors = false;

	/**
	 * Constructor.
	 * 
	 * @since 2.26.0
	 */
	public function __construct() {
		require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/helpers/core/activity-log/interface-wpmastertoolkit-activity-log-provider.php';
		require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/helpers/core/activity-log/class-activity-log-storage.php';
		require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/helpers/core/activity-log/class-wpmastertoolkit-activity-log-registry.php';

		$this->option_id    = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_activity_log';
		$this->nonce_action = $this->option_id . '_action';
		$this->page_slug    = 'wp-mastertoolkit-settings-activity-log';
		$this->registry     = new WPMastertoolkit_Activity_Log_Registry();
		$providers          = array(
			'content'     => 'WPMastertoolkit_Activity_Log_Content',
			'comments'    => 'WPMastertoolkit_Activity_Log_Comments',
			'woocommerce' => 'WPMastertoolkit_Activity_Log_WooCommerce',
			'users'       => 'WPMastertoolkit_Activity_Log_Users',
			'site'        => 'WPMastertoolkit_Activity_Log_Site',
			'media'       => 'WPMastertoolkit_Activity_Log_Media',
			'menus'       => 'WPMastertoolkit_Activity_Log_Menus',
		);
		foreach ( $providers as $id => $class ) {
			require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/helpers/core/activity-log/class-wpmastertoolkit-activity-log-' . $id . '.php';
			$provider = new $class();
			$result   = $this->registry->register( $id, $provider );
			if ( is_wp_error( $result ) ) {
				$this->provider_errors = true;
				_doing_it_wrong( __METHOD__, 'Activity Log provider registration failed.', '2.26.0' );
			}
		}

		add_action( 'wpmastertoolkit/activity_log/event', array( $this, 'record_event' ), 10, 5 );
		$this->registry->register_hooks();
		add_action( 'init', array( $this, 'class_init' ) );
		add_action( 'init', array( $this, 'ensure_retention_schedule' ) );
		add_action( self::RETENTION_HOOK, array( $this, 'cleanup_retention' ) );
		add_action( self::RETENTION_CONTINUE_HOOK, array( $this, 'continue_retention' ), 10, 3 );
		add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
		add_action( 'admin_init', array( $this, 'recover_access' ), 9 );
		add_action( 'admin_init', array( $this, 'save_submenu' ) );
		add_action( 'admin_init', array( $this, 'purge_old_data' ), 11 );
		add_action( 'wp_ajax_wpmastertoolkit_activity_log_search_users', array( $this, 'ajax_search_users' ) );
		add_action( 'wp_ajax_wpmastertoolkit_activity_log_search_roles', array( $this, 'ajax_search_roles' ) );
		add_action( 'wp_ajax_wpmastertoolkit_activity_log_search_post_types', array( $this, 'ajax_search_post_types' ) );
		add_action( 'added_option', array( $this, 'invalidate_settings' ), 1 );
		add_action( 'updated_option', array( $this, 'invalidate_settings' ), 1 );
		add_action( 'deleted_option', array( $this, 'invalidate_settings' ), 1 );
		add_action( 'added_option', array( $this, 'retention_settings_changed' ), 20 );
		add_action( 'updated_option', array( $this, 'retention_settings_changed' ), 20 );
		add_action( 'deleted_option', array( $this, 'retention_settings_changed' ), 20 );

	}

	/**
     * Initialize the class
	 * 
	 * @since 1.11.0
     */
    public function class_init() {
		$this->header_title = esc_html__( 'Activity Log', 'wpmastertoolkit' );
    }

	/**
	 * Normalize and write a permitted event.
	 *
	 * @param string $event     Event identifier.
	 * @param int    $actor_id  Actor user ID, or zero for anonymous/system events.
	 * @param string $object    Object category.
	 * @param int|string $object_id Object identifier.
	 * @param array      $details   Optional event details.
	 * @since 2.26.0
	 */
	public function record_event( $event, $actor_id, $object, $object_id, $details = array() ) {
		if ( ! is_string( $event ) || ! preg_match( '/^[a-z][a-z0-9_.]{0,63}$/', $event ) || ! is_scalar( $actor_id ) || ! is_string( $object ) || ! is_scalar( $object_id ) ) {
			return;
		}
		$definition = $this->registry->event( $event );
		if ( ! $definition || ! $definition['recordable'] || ( $definition['pro'] && ! wpmastertoolkit_is_pro() ) ) {
			return;
		}
		$settings = $this->get_settings();
		if ( '1' !== ( $settings['enabled_events'][ $event ] ?? '0' ) ) {
			return;
		}
		if ( wpmastertoolkit_is_pro() && $this->event_is_excluded( $settings, $event, absint( $actor_id ), $object, $object_id, $details ) ) {
			return;
		}

		$record  = array(
			'time'      => gmdate( 'c' ),
			'site_id'   => get_current_blog_id(),
			'event'     => $event,
			'actor_id'  => absint( $actor_id ),
			'object'    => sanitize_key( $object ),
			'object_id' => is_numeric( $object_id ) ? absint( $object_id ) : sanitize_text_field( substr( (string) $object_id, 0, 120 ) ),
		);
		$details = $this->normalize_details( $event, $details );
		if ( $details ) {
			$record['details'] = $details;
			$record_size       = strlen( (string) wp_json_encode( $record, JSON_INVALID_UTF8_SUBSTITUTE ) ) + 1;
			while ( $record_size > WPMastertoolkit_Activity_Log_Storage::MAX_RECORD_SIZE && $record['details'] ) {
				array_pop( $record['details'] );
				$record_size = strlen( (string) wp_json_encode( $record, JSON_INVALID_UTF8_SUBSTITUTE ) ) + 1;
			}
			if ( ! $record['details'] ) {
				unset( $record['details'] );
			}
		}
		WPMastertoolkit_Activity_Log_Storage::append( $record );
	}

	/**
	 * Apply actor, request, and direct content exclusions before writing.
	 *
	 * @param array      $settings  Current settings.
	 * @param string     $event     Event identifier.
	 * @param int        $actor_id  Initiating user ID.
	 * @param string     $object_type Object category.
	 * @param int|string $object_id Object identifier.
	 * @param mixed      $details   Provider context.
	 * @return bool Whether the new event should be skipped.
	 */
	private function event_is_excluded( $settings, $event, $actor_id, $object_type, $object_id, $details ) {
		if ( $actor_id && in_array( $actor_id, $settings['exclude_user_ids'], true ) ) {
			return true;
		}
		if ( $actor_id && $settings['exclude_roles'] ) {
			$actor = new WP_User( $actor_id, '', get_current_blog_id() );
			if ( array_intersect( $actor->roles, $settings['exclude_roles'] ) ) {
				return true;
			}
		}
		if ( $settings['exclude_ip_addresses'] && ! ( defined( 'WP_CLI' ) && WP_CLI ) && ! wp_doing_cron() ) {
			$address = isset( $_SERVER['REMOTE_ADDR'] ) && is_string( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
			if ( filter_var( $address, FILTER_VALIDATE_IP ) ) {
				foreach ( $settings['exclude_ip_addresses'] as $value ) {
					$network = $this->parse_ip_exclusion( $value );
					$client  = $network ? $this->parse_ip_exclusion( $address . '/' . $network['prefix'] ) : false;
					if ( $client && $client['network'] === $network['network'] ) {
						return true;
					}
				}
			}
		}
		if ( ! $settings['exclude_post_types'] ) {
			return false;
		}
		$post_type = '';
		if ( 'post' === $object_type && 0 === strpos( $event, 'post.' ) ) {
			$post_type = is_array( $details ) && isset( $details['post_type'] ) && is_string( $details['post_type'] ) ? $details['post_type'] : get_post_type( absint( $object_id ) );
		} elseif ( 'attachment' === $object_type && 0 === strpos( $event, 'media.' ) ) {
			$post_type = 'attachment';
		}
		return in_array( $post_type, $settings['exclude_post_types'], true );
	}

	/**
	 * Validate and canonicalize an individual IP address or CIDR network.
	 *
	 * @param mixed $value Submitted or stored token.
	 * @return array|false Canonical value, packed network, and prefix length.
	 */
	private function parse_ip_exclusion( $value ) {
		if ( ! is_string( $value ) ) {
			return false;
		}
		$parts = explode( '/', trim( $value ) );
		if ( count( $parts ) > 2 || ! filter_var( $parts[0], FILTER_VALIDATE_IP ) ) {
			return false;
		}
		$packed = inet_pton( $parts[0] );
		$length = strlen( $packed );
		$bits   = $length * 8;
		$prefix = $bits;
		if ( isset( $parts[1] ) ) {
			if ( ! preg_match( '/^(0|[1-9][0-9]{0,2})$/D', $parts[1] ) || (int) $parts[1] > $bits ) {
				return false;
			}
			$prefix = (int) $parts[1];
		}
		$network = '';
		for ( $index = 0; $index < $length; ++$index ) {
			$remaining = max( 0, min( 8, $prefix - $index * 8 ) );
			$network  .= chr( ord( $packed[ $index ] ) & ( ( 0xff << ( 8 - $remaining ) ) & 0xff ) );
		}
		return array(
			'value'   => inet_ntop( $network ) . ( isset( $parts[1] ) ? '/' . $prefix : '' ),
			'network' => $network,
			'prefix'  => $prefix,
		);
	}

	/**
	 * Get active-site choices supported by the exclusion filters.
	 *
	 * @param string $key Exclusion setting key.
	 * @return array Choice values and translated labels.
	 */
	private function exclusion_choices( $key ) {
		$choices = array();
		if ( 'exclude_roles' === $key ) {
			foreach ( wp_roles()->roles as $slug => $role ) {
				$choices[ $slug ] = translate_user_role( $role['name'] );
			}
		} elseif ( 'exclude_post_types' === $key ) {
			foreach ( get_post_types( array(), 'objects' ) as $slug => $post_type ) {
				if ( 'attachment' === $slug || WPMastertoolkit_Activity_Log_Content::supports_post_type( $slug ) ) {
					$choices[ $slug ] = $post_type->labels->name;
				}
			}
		}
		return $choices;
	}

	/**
	 * Route details to the observer that owns their event schema.
	 *
	 * @param string $event Event identifier.
	 * @param mixed  $details Supplied details.
	 * @return array Bounded event details.
	 */
	private function normalize_details( $event, $details ) {
		return $this->registry->normalize_details( $event, $details );
	}

	/**
	 * Register the Activity Log admin page.
	 * 
	 * @since 2.26.0
	 */
	public function add_submenu() {
		$this->recover_access();
		if ( ! $this->current_user_is_allowed() ) {
			return;
		}
		WPMastertoolkit_Settings::add_submenu_page(
			'wp-mastertoolkit-settings',
			__( 'Activity Log', 'wpmastertoolkit' ),
			__( 'Activity Log', 'wpmastertoolkit' ),
			'manage_options',
			$this->page_slug,
			array( $this, 'render_submenu' )
		);
	}

	/**
	 * Save the shared submenu form.
	 *
	 * @since 2.26.0
	 */
	public function save_submenu() {
		if ( ! isset( $_POST[ $this->option_id ] ) || isset( $_POST['wpmastertoolkit_activity_purge'] ) ) {
			return;
		}
		if ( ! $this->current_user_is_allowed() ) {
			wp_die( esc_html__( 'You cannot change Activity Log settings.', 'wpmastertoolkit' ), '', array( 'response' => 403 ) );
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, $this->nonce_action ) ) {
			return;
		}

		//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$new_settings = $this->sanitize_settings( wp_unslash( $_POST[ $this->option_id ] ) );
		update_option( $this->option_id, $new_settings, false );
		$this->settings[ get_current_blog_id() ] = $new_settings;
		$this->ensure_retention_schedule();
		wp_safe_redirect( admin_url( 'admin.php?page=' . $this->page_slug . '&updated=1' ) );
		exit;
	}

	/**
	 * Validate submitted settings against the known setting keys.
	 *
	 * @param mixed $new_settings Submitted values.
	 * @return array Validated settings.
	 */
	private function sanitize_settings( $new_settings ) {
		if ( ! is_array( $new_settings ) ) {
			wp_die( esc_html__( 'Select existing users to exclude.', 'wpmastertoolkit' ), '', array( 'response' => 400 ) );
		}

		$sanitized = $this->get_default_settings();
		$current   = $this->get_settings();
		$retention = array();
		foreach ( array( 'retention_mode', 'retention_value', 'retention_unit' ) as $key ) {
			$retention[ $key ] = wpmastertoolkit_is_pro() && array_key_exists( $key, $new_settings ) ? $new_settings[ $key ] : $current[ $key ];
		}
		if ( wpmastertoolkit_is_pro() ) {
			if ( ! $this->valid_retention( $retention ) ) {
				wp_die( esc_html__( 'Select a valid retention period: 1-36500 days, 1-1200 months, or 1-100 years.', 'wpmastertoolkit' ), '', array( 'response' => 400 ) );
			}
			$retention['retention_value'] = (int) $retention['retention_value'];
		}
		$sanitized = array_merge( $sanitized, $retention );

		$sanitized['access_mode']    = $current['access_mode'];
		$sanitized['access_user_id'] = $current['access_user_id'];
		if ( wpmastertoolkit_is_pro() && array_key_exists( 'access_mode', $new_settings ) ) {
			$mode = $new_settings['access_mode'];
			if ( ! is_string( $mode ) || ! in_array( $mode, array( 'all_administrators', 'only_me' ), true ) ) {
				wp_die( esc_html__( 'Select a valid Activity Log access setting.', 'wpmastertoolkit' ), '', array( 'response' => 400 ) );
			}
			$sanitized['access_mode']    = $mode;
			$sanitized['access_user_id'] = 'only_me' === $mode
				? ( 'only_me' === $current['access_mode'] ? $current['access_user_id'] : get_current_user_id() )
				: 0;
		}
		$submitted_events = $new_settings['enabled_events'] ?? array();
		if ( ! is_array( $submitted_events ) ) {
			wp_die( esc_html__( 'Select valid activity events.', 'wpmastertoolkit' ), '', array( 'response' => 400 ) );
		}
		foreach ( $this->registry->groups() as $group ) {
			foreach ( $group['events'] as $event => $label ) {
				if ( $group['pro'] && ! wpmastertoolkit_is_pro() ) {
					$sanitized['enabled_events'][ $event ] = $current['enabled_events'][ $event ];
					continue;
				}
				$value = $submitted_events[ $event ] ?? '0';
				if ( ! is_string( $value ) || ! in_array( $value, array( '0', '1' ), true ) ) {
					wp_die( esc_html__( 'Select valid activity events.', 'wpmastertoolkit' ), '', array( 'response' => 400 ) );
				}
				$sanitized['enabled_events'][ $event ] = $value;
			}
		}

		if ( ! wpmastertoolkit_is_pro() ) {
			foreach ( array( 'exclude_user_ids', 'exclude_roles', 'exclude_ip_addresses', 'exclude_post_types' ) as $key ) {
				$sanitized[ $key ] = $current[ $key ];
			}
			return $sanitized;
		}

		$excluded = $new_settings['exclude_user_ids'] ?? array();
		if ( ! is_array( $excluded ) ) {
			wp_die( esc_html__( 'Select existing users to exclude.', 'wpmastertoolkit' ), '', array( 'response' => 400 ) );
		}
		foreach ( $excluded as $value ) {
			if ( ! is_scalar( $value ) || ! ctype_digit( (string) $value ) || ! absint( $value ) || ! get_userdata( absint( $value ) ) ) {
				wp_die( esc_html__( 'Select existing users to exclude.', 'wpmastertoolkit' ), '', array( 'response' => 400 ) );
			}
			$sanitized['exclude_user_ids'][] = absint( $value );
		}
		$sanitized['exclude_user_ids'] = array_values( array_unique( $sanitized['exclude_user_ids'] ) );

		$errors = array(
			'exclude_roles'        => __( 'Select valid roles to exclude.', 'wpmastertoolkit' ),
			'exclude_ip_addresses' => __( 'Enter valid IPv4 or IPv6 addresses or CIDR ranges to exclude.', 'wpmastertoolkit' ),
			'exclude_post_types'   => __( 'Select supported post types to exclude.', 'wpmastertoolkit' ),
		);
		foreach ( $errors as $key => $message ) {
			$values  = array_key_exists( $key, $new_settings ) ? $new_settings[ $key ] : array();
			$choices = $this->exclusion_choices( $key );
			if ( ! is_array( $values ) ) {
				wp_die( esc_html( $message ), '', array( 'response' => 400 ) );
			}
			foreach ( $values as $value ) {
				if ( 'exclude_ip_addresses' === $key ) {
					$network = $this->parse_ip_exclusion( $value );
					if ( ! $network ) {
						wp_die( esc_html( $message ), '', array( 'response' => 400 ) );
					}
					$value = $network['value'];
				} elseif ( ! is_string( $value ) || '' === $value || sanitize_key( $value ) !== $value || ( ! isset( $choices[ $value ] ) && ! in_array( $value, $current[ $key ], true ) ) ) {
					wp_die( esc_html( $message ), '', array( 'response' => 400 ) );
				}
				$sanitized[ $key ][] = $value;
			}
			$sanitized[ $key ] = array_values( array_unique( $sanitized[ $key ] ) );
		}

		return $sanitized;
	}

	/**
	 * Get saved settings with defaults for missing keys.
	 *
	 * @return array Settings.
	 */
	private function get_settings() {
		$blog_id = get_current_blog_id();
		if ( ! isset( $this->settings[ $blog_id ] ) ) {
			$saved = get_option( $this->option_id, false );
			if ( false === $saved ) {
				$saved = get_option( 'wpmastertoolkit_activity_log_settings', array() );
			}
			$saved = is_array( $saved ) ? $saved : array( 'retention_mode' => 'invalid' );
			if ( ! isset( $saved['exclude_user_ids'] ) ) {
				$saved['exclude_user_ids'] = ! empty( $saved['exclude_user'] ) ? array( absint( $saved['exclude_user'] ) ) : array();
			}
			unset( $saved['exclude_user'], $saved['viewer_user'] );
			if ( array_key_exists( 'access_mode', $saved ) && ( ! is_string( $saved['access_mode'] ) || ! in_array( $saved['access_mode'], array( 'all_administrators', 'only_me' ), true ) ) ) {
				$saved['access_mode'] = 'invalid';
			}
			$saved['access_user_id']   = isset( $saved['access_user_id'] ) && is_scalar( $saved['access_user_id'] ) && ctype_digit( (string) $saved['access_user_id'] )
				? absint( $saved['access_user_id'] )
				: 0;
			$saved['exclude_user_ids'] = isset( $saved['exclude_user_ids'] ) && is_array( $saved['exclude_user_ids'] )
				? array_values( array_unique( array_filter( array_map( 'absint', array_filter( $saved['exclude_user_ids'], 'is_numeric' ) ) ) ) )
				: array();
			foreach ( array( 'exclude_roles', 'exclude_ip_addresses', 'exclude_post_types' ) as $key ) {
				$values        = isset( $saved[ $key ] ) && is_array( $saved[ $key ] ) ? $saved[ $key ] : array();
				$saved[ $key ] = array();
				foreach ( $values as $value ) {
					if ( 'exclude_ip_addresses' === $key ) {
						$network = $this->parse_ip_exclusion( $value );
						if ( $network ) {
							$saved[ $key ][] = $network['value'];
						}
					} elseif ( is_string( $value ) && '' !== $value && sanitize_key( $value ) === $value ) {
						$saved[ $key ][] = $value;
					}
				}
				$saved[ $key ] = array_values( array_unique( $saved[ $key ] ) );
			}
			$saved_events            = isset( $saved['enabled_events'] ) && is_array( $saved['enabled_events'] ) ? $saved['enabled_events'] : array();
			$saved['enabled_events'] = $this->registry->migrate_preferences( $saved_events );

			$this->settings[ $blog_id ] = wp_parse_args( $saved, $this->get_default_settings() );

			if ( ! $this->valid_retention( $this->settings[ $blog_id ] ) ) {
				$this->settings[ $blog_id ]['retention_mode']  = 'invalid';
				$this->settings[ $blog_id ]['retention_value'] = 3;
				$this->settings[ $blog_id ]['retention_unit']  = 'months';
			}

			$defaults                                     = $this->get_default_settings();
			$this->settings[ $blog_id ]['enabled_events'] = is_array( $this->settings[ $blog_id ]['enabled_events'] )
				? array_merge( $defaults['enabled_events'], array_intersect_key( $this->settings[ $blog_id ]['enabled_events'], $defaults['enabled_events'] ) )
				: $defaults['enabled_events'];
		}
		return $this->settings[ $blog_id ];
	}

	/**
	 * Invalidate only the active site's cached settings after option changes.
	 *
	 * @param string $option Changed option name.
	 */
	public function invalidate_settings( $option ) {
		if ( $this->option_id === $option || 'wpmastertoolkit_activity_log_settings' === $option ) {
			unset( $this->settings[ get_current_blog_id() ] );
		}
	}

	/**
	 * Get the event types emitted by this module.
	 *
	 * @return array Event groups and labels.
	 */
	private function get_event_groups() {
		return $this->registry->groups( true );
	}

	/**
	 * Get default values for Activity Log settings.
	 *
	 * @return array Defaults.
	 */
	private function get_default_settings() {
		if ( null === $this->default_settings ) {
			$this->default_settings = array(
				'retention_mode'       => 'delete_older',
				'retention_value'      => 3,
				'retention_unit'       => 'months',
				'access_mode'          => 'all_administrators',
				'access_user_id'       => 0,
				'exclude_user_ids'     => array(),
				'exclude_roles'        => array(),
				'exclude_ip_addresses' => array(),
				'exclude_post_types'   => array(),
				'enabled_events'       => $this->registry->defaults(),
			);
		}
		return $this->default_settings;
	}

	/**
	 * Apply the edition's retention policy without changing saved Pro preferences.
	 *
	 * @param array $settings Saved settings.
	 * @return array Effective settings.
	 */
	private static function retention_policy( $settings ) {
		if ( ! wpmastertoolkit_is_pro() ) {
			$settings['retention_mode']  = 'delete_older';
			$settings['retention_value'] = 1;
			$settings['retention_unit']  = 'months';
		}
		return $settings;
	}

	/**
	 * Validate a retention policy without coercing malformed input.
	 *
	 * @param array $settings Settings to validate.
	 * @return bool Whether the policy is valid.
	 */
	private static function valid_retention( $settings ) {
		$limits = array(
			'days'   => 36500,
			'months' => 1200,
			'years'  => 100,
		);
		$mode   = $settings['retention_mode'] ?? null;
		$unit   = $settings['retention_unit'] ?? null;
		$value  = $settings['retention_value'] ?? null;
		return is_string( $mode ) && in_array( $mode, array( 'keep_all', 'delete_older' ), true )
			&& is_string( $unit ) && isset( $limits[ $unit ] )
			&& ( is_int( $value ) || is_string( $value ) ) && preg_match( '/^[1-9][0-9]{0,4}$/D', (string) $value )
			&& (int) $value <= $limits[ $unit ];
	}

	/**
	 * Calculate the retained boundary day in UTC, clamping calendar month ends.
	 *
	 * @param array    $settings Retention policy.
	 * @param int|null $now      Current timestamp, or null for now.
	 * @return string|false UTC date, or false when cleanup is disabled.
	 */
	private function retention_cutoff( $settings, $now = null ) {
		$settings = self::retention_policy( $settings );
		if ( ! $this->valid_retention( $settings ) || 'delete_older' !== $settings['retention_mode'] ) {
			return false;
		}
		$date  = ( new DateTimeImmutable( '@' . ( null === $now ? time() : $now ) ) )->setTimezone( new DateTimeZone( 'UTC' ) )->setTime( 0, 0 );
		$value = (int) $settings['retention_value'];
		if ( 'days' === $settings['retention_unit'] ) {
			return $date->sub( new DateInterval( 'P' . $value . 'D' ) )->format( 'Y-m-d' );
		}
		$months = 'years' === $settings['retention_unit'] ? $value * 12 : $value;
		$target = $date->setDate( (int) $date->format( 'Y' ), (int) $date->format( 'n' ), 1 )->sub( new DateInterval( 'P' . $months . 'M' ) );
		return $target->setDate( (int) $target->format( 'Y' ), (int) $target->format( 'n' ), min( (int) $date->format( 'j' ), (int) $target->format( 't' ) ) )->format( 'Y-m-d' );
	}

	/**
	 * Check persisted module state before any background or manual deletion.
	 *
	 * @return bool Whether the module is enabled outside safe mode.
	 */
	private function retention_is_enabled() {
		$options = get_option( WPMASTERTOOLKIT_PLUGIN_SETTINGS, array() );
		return ( ! defined( 'WPMASTERTOOLKIT_SAFE_MODE' ) || ! WPMASTERTOOLKIT_SAFE_MODE )
			&& is_array( $options ) && in_array( $options[ __CLASS__ ] ?? null, array( '1', 1 ), true );
	}

	/**
	 * Reconcile this site's schedule after relevant option changes.
	 *
	 * @param string $option Changed option name.
	 */
	public function retention_settings_changed( $option ) {
		if ( in_array( $option, array( $this->option_id, 'wpmastertoolkit_activity_log_settings', WPMASTERTOOLKIT_PLUGIN_SETTINGS ), true ) ) {
			$this->ensure_retention_schedule();
		}
	}

	/**
	 * Ensure one daily event and invalidate continuations when the policy changes.
	 */
	public function ensure_retention_schedule() {
		$settings = $this->get_settings();
		if ( ! $this->retention_is_enabled() || false === $this->retention_cutoff( $settings ) ) {
			self::deactivate();
			return;
		}
		$fingerprint = $this->retention_fingerprint( $settings );
		if ( get_option( $this->option_id . '_retention_schedule' ) !== $fingerprint ) {
			$cleared = wp_unschedule_hook( self::RETENTION_CONTINUE_HOOK, true );
			if ( is_wp_error( $cleared ) || false === $cleared ) {
				$this->retention_schedule_failed();
				return;
			}
			update_option( $this->option_id . '_retention_schedule', $fingerprint, false );
		}
		if ( ! wp_next_scheduled( self::RETENTION_HOOK ) ) {
			$scheduled = wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::RETENTION_HOOK, array(), true );
			if ( is_wp_error( $scheduled ) || ! $scheduled ) {
				$this->retention_schedule_failed();
			}
		}
	}

	/**
	 * Record schedule failures without changing recording health.
	 */
	private function retention_schedule_failed() {
		update_option(
			$this->option_id . '_retention_status',
			array(
				'time'   => time(),
				'errors' => array( 'schedule_failed' ),
			),
			false
		);
	}

	/**
	 * Identify the current policy independently of its rolling cutoff.
	 *
	 * @param array $settings Validated module settings.
	 * @return string Policy identity.
	 */
	private function retention_fingerprint( $settings ) {
		$settings = self::retention_policy( $settings );
		return hash( 'sha256', $settings['retention_mode'] . ':' . $settings['retention_value'] . ':' . $settings['retention_unit'] );
	}

	/**
	 * Run one daily cleanup batch without requiring an authenticated user.
	 */
	public function cleanup_retention() {
		$this->run_retention_batch();
	}

	/**
	 * Continue only if the persisted policy still matches the scheduled batch.
	 *
	 * @param string $cursor      Last processed segment.
	 * @param string $fingerprint Scheduled policy identity.
	 * @param int    $attempt     Busy-lock retry count.
	 */
	public function continue_retention( $cursor = '', $fingerprint = '', $attempt = 0 ) {
		$this->invalidate_settings( $this->option_id );
		$settings = $this->get_settings();
		if ( ! is_string( $fingerprint ) || ! hash_equals( $this->retention_fingerprint( $settings ), $fingerprint ) || ! is_int( $attempt ) || $attempt < 0 || $attempt > 3 ) {
			return;
		}
		$this->run_retention_batch( $cursor, $attempt );
	}

	/**
	 * Share the same bounded runner between cron and the manual command.
	 *
	 * @param string $cursor  Last processed segment.
	 * @param int    $attempt Busy-lock retry count.
	 * @return array Batch result, including queued continuation state.
	 */
	private function run_retention_batch( $cursor = '', $attempt = 0 ) {
		$this->invalidate_settings( $this->option_id );
		$settings = $this->get_settings();
		$cutoff   = $this->retention_cutoff( $settings );
		if ( ! $this->retention_is_enabled() || false === $cutoff ) {
			return array( 'errors' => array( 'cleanup_disabled' ) );
		}
		$result = WPMastertoolkit_Activity_Log_Storage::purge_before_day( $cutoff, $cursor );

		$result['time']   = time();
		$result['cutoff'] = $cutoff;
		$result['queued'] = false;
		if ( $result['more'] || ( $result['busy'] && $attempt < 3 ) ) {
			$args      = array( $result['cursor'], $this->retention_fingerprint( $settings ), $result['busy'] ? $attempt + 1 : 0 );
			$scheduled = wp_next_scheduled( self::RETENTION_CONTINUE_HOOK, $args );
			if ( ! $scheduled ) {
				$scheduled = wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::RETENTION_CONTINUE_HOOK, $args, true );
			}
			if ( is_wp_error( $scheduled ) || ! $scheduled ) {
				$result['errors'][] = 'schedule_failed';
			} else {
				$result['queued'] = true;
			}
		}
		update_option( $this->option_id . '_retention_status', $result, false );
		return $result;
	}

	/**
	 * Purge according to the saved policy without submitting the settings form.
	 */
	public function purge_old_data() {
		if ( ! isset( $_POST['wpmastertoolkit_activity_purge'] ) ) {
			return;
		}
		if ( ! $this->current_user_is_allowed() || ! $this->retention_is_enabled() ) {
			wp_die( esc_html__( 'You cannot purge Activity Log data.', 'wpmastertoolkit' ), '', array( 'response' => 403 ) );
		}
		$nonce = isset( $_POST['wpmastertoolkit_activity_purge_nonce'] ) && is_string( $_POST['wpmastertoolkit_activity_purge_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wpmastertoolkit_activity_purge_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, $this->nonce_action . '_purge' ) ) {
			wp_die( esc_html__( 'The purge request could not be verified. Reload the page and try again.', 'wpmastertoolkit' ), '', array( 'response' => 403 ) );
		}
		if ( false === $this->retention_cutoff( $this->get_settings() ) ) {
			wp_die( esc_html__( 'Save a valid deletion policy before purging old data.', 'wpmastertoolkit' ), '', array( 'response' => 400 ) );
		}
		$result = $this->run_retention_batch();
		set_transient( $this->option_id . '_purge_' . get_current_user_id(), $result, 5 * MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=' . $this->page_slug ) );
		exit;
	}

	/**
	 * Describe trusted cleanup results without exposing storage paths or records.
	 *
	 * @param array $status Stored cleanup result.
	 * @return string Human-readable batch outcome.
	 */
	private function retention_status_message( $status ) {
		if ( ! is_array( $status ) ) {
			return '';
		}
		$messages = array();
		if ( isset( $status['files_deleted'] ) ) {
			/* translators: 1: Number of removed log files, 2: Reclaimed storage, 3: Number of files preserved for safety. */
			$messages[] = sprintf( __( 'Log files removed: %1$d (%2$s). Files preserved for safety: %3$d.', 'wpmastertoolkit' ), absint( $status['files_deleted'] ), size_format( absint( $status['bytes_removed'] ?? 0 ) ), absint( $status['files_skipped'] ?? 0 ) );
		}
		if ( ! empty( $status['busy'] ) ) {
			$messages[] = __( 'Cleanup is waiting for the activity writer. No files were deleted in this batch.', 'wpmastertoolkit' );
		}
		if ( ! empty( $status['queued'] ) ) {
			$messages[] = __( 'Another cleanup batch is scheduled in the background.', 'wpmastertoolkit' );
		} elseif ( ! empty( $status['more'] ) ) {
			$messages[] = __( 'Cleanup is incomplete. Remaining files will be retried on a later run.', 'wpmastertoolkit' );
		}
		if ( ! empty( $status['errors'] ) ) {
			$messages[] = __( 'Some cleanup or scheduling operations failed. Check storage permissions and WordPress cron, then try again.', 'wpmastertoolkit' );
		}
		return implode( ' ', $messages );
	}

	/**
	 * Render the retention policy and a separately submitted purge command.
	 *
	 * @param array $settings Current module settings.
	 */
	private function render_retention_settings( $settings ) {
		$pro      = wpmastertoolkit_is_pro();
		$settings = self::retention_policy( $settings );
		$cutoff   = $this->retention_cutoff( $settings );
		$next     = wp_next_scheduled( self::RETENTION_HOOK );
		$status   = get_option( $this->option_id . '_retention_status', array() );
		$limits   = array(
			'days'   => 36500,
			'months' => 1200,
			'years'  => 100,
		);
		$units    = array(
			'days'   => __( 'Days', 'wpmastertoolkit' ),
			'months' => __( 'Months', 'wpmastertoolkit' ),
			'years'  => __( 'Years', 'wpmastertoolkit' ),
		);
		$confirm  = '';
		if ( false !== $cutoff ) {
			/* translators: %s: First retained UTC date. */
			$confirm = sprintf( __( 'Permanently delete eligible log files dated before %s UTC using the saved retention policy? This cannot be undone.', 'wpmastertoolkit' ), $cutoff );
		}
		?>
		<section class="wp-mastertoolkit__section" aria-labelledby="wpmastertoolkit-activity-retention-heading">
			<div class="wp-mastertoolkit__section__desc">
				<h2 id="wpmastertoolkit-activity-retention-heading"><?php esc_html_e( 'Activity log retention', 'wpmastertoolkit' ); ?></h2>
				<p id="wpmastertoolkit-activity-retention-description"><?php esc_html_e( 'Activity is stored in daily log files. Cleanup removes whole expired files and preserves the cutoff day. Files with uncertain or newer records are retained.', 'wpmastertoolkit' ); ?></p>
			</div>
			<div class="wp-mastertoolkit__section__body">
				<fieldset class="wp-mastertoolkit__section__body__item wpmastertoolkit-activity-retention" aria-describedby="wpmastertoolkit-activity-retention-description">
					<legend class="wp-mastertoolkit__section__body__item__title">
						<?php esc_html_e( 'Keep activity history', 'wpmastertoolkit' ); ?>
						<?php if ( ! $pro ) : ?>
							<span class="wp-mastertoolkit__section__body__item__title__tag pro"><?php esc_html_e( 'PRO', 'wpmastertoolkit' ); ?></span>
						<?php endif; ?>
					</legend>
					<div class="wp-mastertoolkit__section__body__item__content">
						<div class="wpmastertoolkit-activity-retention-options">
							<label class="wpmastertoolkit-activity-retention-choice" for="wpmastertoolkit-activity-retention-all">
								<input id="wpmastertoolkit-activity-retention-all" type="radio" name="<?php echo esc_attr( $this->option_id ); ?>[retention_mode]" value="keep_all" <?php checked( 'keep_all' === $settings['retention_mode'], true ); ?> <?php disabled( ! $pro ); ?>>
								<span><?php esc_html_e( 'Keep all data', 'wpmastertoolkit' ); ?></span>
							</label>
							<div class="wpmastertoolkit-activity-retention-period">
								<label class="wpmastertoolkit-activity-retention-choice" for="wpmastertoolkit-activity-retention-delete">
									<input id="wpmastertoolkit-activity-retention-delete" type="radio" name="<?php echo esc_attr( $this->option_id ); ?>[retention_mode]" value="delete_older" <?php checked( 'delete_older' === $settings['retention_mode'], true ); ?> <?php disabled( ! $pro ); ?>>
									<span><?php esc_html_e( 'Delete events older than', 'wpmastertoolkit' ); ?></span>
								</label>
								<label class="screen-reader-text" for="wpmastertoolkit-activity-retention-value"><?php esc_html_e( 'Retention period', 'wpmastertoolkit' ); ?></label>
								<input id="wpmastertoolkit-activity-retention-value" class="wpmastertoolkit-activity-retention-value" type="number" name="<?php echo esc_attr( $this->option_id ); ?>[retention_value]" value="<?php echo esc_attr( $settings['retention_value'] ); ?>" min="1" max="<?php echo esc_attr( $limits[ $settings['retention_unit'] ] ); ?>" step="1" required <?php disabled( ! $pro ); ?>>
								<label class="screen-reader-text" for="wpmastertoolkit-activity-retention-unit"><?php esc_html_e( 'Retention unit', 'wpmastertoolkit' ); ?></label>
								<select id="wpmastertoolkit-activity-retention-unit" name="<?php echo esc_attr( $this->option_id ); ?>[retention_unit]" <?php disabled( ! $pro ); ?>>
									<?php foreach ( $units as $unit => $label ) : ?>
										<option value="<?php echo esc_attr( $unit ); ?>" <?php selected( $settings['retention_unit'], $unit ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>
						<p class="wp-mastertoolkit__section__body__item__content__desc" role="status">
							<?php if ( 'invalid' === $settings['retention_mode'] ) : ?>
								<?php esc_html_e( 'The saved retention policy is invalid. Cleanup is paused until you save a valid policy.', 'wpmastertoolkit' ); ?>
							<?php elseif ( false === $cutoff ) : ?>
								<?php esc_html_e( 'Automatic cleanup is disabled. All activity log data is retained.', 'wpmastertoolkit' ); ?>
							<?php elseif ( $next ) : ?>
								<?php
								/* translators: %s: Next scheduled cleanup date in the site's timezone. */
								echo esc_html( sprintf( __( 'Next scheduled cleanup: %s.', 'wpmastertoolkit' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next ) ) );
								?>
								<?php if ( $next <= time() ) : ?>
									<?php esc_html_e( 'This run is overdue.', 'wpmastertoolkit' ); ?>
								<?php endif; ?>
								<?php esc_html_e( 'WordPress cron runs when site traffic triggers it.', 'wpmastertoolkit' ); ?>
							<?php else : ?>
								<?php esc_html_e( 'Cleanup is not scheduled. Check WordPress cron and save these settings again.', 'wpmastertoolkit' ); ?>
							<?php endif; ?>
						</p>
						<?php if ( $status ) : ?>
							<p class="wp-mastertoolkit__section__body__item__content__desc"><?php echo esc_html( $this->retention_status_message( $status ) ); ?></p>
						<?php endif; ?>
						<button type="submit" form="wpmastertoolkit-activity-purge-form" class="button button-primary" data-confirm="<?php echo esc_attr( $confirm ); ?>" <?php disabled( false === $cutoff ); ?>><?php esc_html_e( 'Purge Old Data', 'wpmastertoolkit' ); ?></button>
					</div>
				</fieldset>
			</div>
		</section>
		<?php
	}

	/**
	 * Reset stale jobs on activation; enabled-instance initialization schedules anew.
	 */
	public static function activate() {
		self::deactivate();
		add_action( 'shutdown', array( __CLASS__, 'schedule_after_activation' ) );
	}

	/**
	 * Schedule after the module manager has persisted its final enabled state.
	 */
	public static function schedule_after_activation() {
		$options = get_option( WPMASTERTOOLKIT_PLUGIN_SETTINGS, array() );
		if ( ( defined( 'WPMASTERTOOLKIT_SAFE_MODE' ) && WPMASTERTOOLKIT_SAFE_MODE ) || ! is_array( $options ) || ! in_array( $options[ __CLASS__ ] ?? null, array( '1', 1 ), true ) ) {
			return;
		}
		$saved = get_option( WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_activity_log', false );
		if ( false === $saved ) {
			$saved = get_option( 'wpmastertoolkit_activity_log_settings', array() );
		}
		$defaults = array(
			'retention_mode'  => 'delete_older',
			'retention_value' => 3,
			'retention_unit'  => 'months',
		);
		$policy   = is_array( $saved ) ? wp_parse_args( $saved, $defaults ) : array();
		$policy   = self::retention_policy( $policy );
		if ( self::valid_retention( $policy ) && 'delete_older' === $policy['retention_mode'] && ! wp_next_scheduled( self::RETENTION_HOOK ) ) {
			$scheduled = wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::RETENTION_HOOK, array(), true );
			if ( is_wp_error( $scheduled ) || ! $scheduled ) {
				update_option( WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_activity_log_retention_status', array( 'errors' => array( 'schedule_failed' ) ), false );
			}
		}
	}

	/**
	 * Stop cleanup without deleting history, including network-wide deactivation.
	 *
	 * @param bool $network_wide Whether all sites require schedule cleanup.
	 */
	public static function deactivate( $network_wide = false ) {
		if ( $network_wide && is_multisite() ) {
			$offset = 0;
			do {
				$sites = get_sites(
					array(
						'fields' => 'ids',
						'number' => 100,
						'offset' => $offset,
					)
				);
				foreach ( $sites as $site_id ) {
					switch_to_blog( $site_id );
					try {
						self::deactivate();
					} finally {
						restore_current_blog();
					}
				}
				$site_count = count( $sites );
				$offset    += $site_count;
			} while ( 100 === $site_count );
			return;
		}
		$prefix = defined( 'WPMASTERTOOLKIT_PLUGIN_SETTINGS' ) ? WPMASTERTOOLKIT_PLUGIN_SETTINGS : 'wpmastertoolkit_settings';
		foreach ( array( self::RETENTION_HOOK, self::RETENTION_CONTINUE_HOOK ) as $hook ) {
			$result = wp_unschedule_hook( $hook, true );
			if ( is_wp_error( $result ) || false === $result ) {
				update_option(
					$prefix . '_activity_log_retention_status',
					array(
						'time'   => time(),
						'errors' => array( 'schedule_failed' ),
					),
					false
				);
			}
		}
		delete_option( $prefix . '_activity_log_retention_schedule' );
	}

	/**
	 * Determine whether the current administrator may access this module.
	 *
	 * @return bool Whether access is permitted.
	 */
	private function current_user_is_allowed() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		if ( ! wpmastertoolkit_is_pro() ) {
			return true;
		}
		$settings = $this->get_settings();
		if ( 'all_administrators' === $settings['access_mode'] ) {
			return true;
		}
		return 'only_me' === $settings['access_mode']
			&& get_current_user_id() === $settings['access_user_id']
			&& $this->access_owner_is_eligible( $settings['access_user_id'] );
	}

	/**
	 * Check the owner's capabilities on the active site, not their original site.
	 *
	 * @param int $user_id Owner user ID.
	 * @return bool Whether the owner still has administrator access.
	 */
	private function access_owner_is_eligible( $user_id ) {
		if ( ! $user_id ) {
			return false;
		}
		$user = new WP_User( $user_id, '', get_current_blog_id() );
		return $user->exists() && user_can( $user, 'manage_options' );
	}

	/**
	 * Reopen an orphaned Pro restriction during an eligible module visit.
	 */
	public function recover_access() {
		if ( ! wpmastertoolkit_is_pro() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$page  = isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$nonce = isset( $_POST['_wpnonce'] ) && is_string( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( isset( $_POST['wpmastertoolkit_activity_purge'] ) ) {
			$purge_nonce = isset( $_POST['wpmastertoolkit_activity_purge_nonce'] ) && is_string( $_POST['wpmastertoolkit_activity_purge_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wpmastertoolkit_activity_purge_nonce'] ) ) : '';
			if ( ! wp_verify_nonce( $purge_nonce, $this->nonce_action . '_purge' ) ) {
				return;
			}
		} elseif ( isset( $_POST[ $this->option_id ] ) ) {
			if ( ! wp_verify_nonce( $nonce, $this->nonce_action ) ) {
				return;
			}
		} elseif ( $this->page_slug !== $page ) {
			return;
		}
		$this->invalidate_settings( $this->option_id );
		$settings = $this->get_settings();
		if ( 'only_me' !== $settings['access_mode'] || $this->access_owner_is_eligible( $settings['access_user_id'] ) ) {
			return;
		}
		$saved = get_option( $this->option_id, false );
		if ( false === $saved ) {
			$saved = get_option( 'wpmastertoolkit_activity_log_settings', array() );
		}
		if ( ! is_array( $saved ) || 'only_me' !== ( $saved['access_mode'] ?? '' ) || $this->access_owner_is_eligible( $settings['access_user_id'] ) ) {
			return;
		}
		$saved_owner = isset( $saved['access_user_id'] ) && is_scalar( $saved['access_user_id'] ) && ctype_digit( (string) $saved['access_user_id'] ) ? absint( $saved['access_user_id'] ) : 0;
		if ( $settings['access_user_id'] !== $saved_owner ) {
			return;
		}
		$saved['access_mode']    = 'all_administrators';
		$saved['access_user_id'] = 0;
		if ( update_option( $this->option_id, $saved, false ) ) {
			$this->invalidate_settings( $this->option_id );
			$this->access_recovered[ get_current_blog_id() ] = true;
		}
	}

	/**
	 * Search users available for exclusion from activity records.
	 *
	 * @since 2.26.0
	 */
	public function ajax_search_users() {
		if ( ! $this->current_user_is_allowed() || ! wpmastertoolkit_is_pro() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized', 'wpmastertoolkit' ) ), 403 );
		}
		check_ajax_referer( $this->nonce_action . '_search_users', 'nonce' );

		$search = isset( $_GET['search'] ) && is_string( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';
		if ( strlen( $search ) < 2 ) {
			wp_send_json_success( array() );
		}

		$users   = get_users(
			array(
				'search'         => '*' . substr( $search, 0, 100 ) . '*',
				'search_columns' => array( 'user_login', 'display_name' ),
				'number'         => 20,
			)
		);
		$results = array();
		foreach ( $users as $user ) {
			$results[] = array(
				'id'   => $user->ID,
				'text' => $user->user_login,
			);
		}
		wp_send_json_success( $results );
	}

	/**
	 * Search active-site roles available for exclusion.
	 */
	public function ajax_search_roles() {
		$this->ajax_search_exclusions( 'exclude_roles' );
	}

	/**
	 * Search post types supported by direct content exclusions.
	 */
	public function ajax_search_post_types() {
		$this->ajax_search_exclusions( 'exclude_post_types' );
	}

	/**
	 * Return authorized, bounded-query registry searches in Select2 format.
	 *
	 * @param string $key Exclusion setting key.
	 */
	private function ajax_search_exclusions( $key ) {
		if ( ! $this->current_user_is_allowed() || ! wpmastertoolkit_is_pro() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized', 'wpmastertoolkit' ) ), 403 );
		}
		check_ajax_referer( $this->nonce_action . '_search_exclusions', 'nonce' );
		$search  = isset( $_GET['search'] ) && is_string( $_GET['search'] ) ? substr( sanitize_text_field( wp_unslash( $_GET['search'] ) ), 0, 100 ) : '';
		$results = array();
		foreach ( $this->exclusion_choices( $key ) as $value => $label ) {
			if ( '' === $search || false !== stripos( $value, $search ) || false !== stripos( $label, $search ) ) {
				$results[] = array( 'id' => $value, 'text' => $label );
			}
		}
		wp_send_json_success( $results );
	}

	/**
	 * Render a bounded recent log and administrator-only settings.
	 * 
	 * @since 2.26.0
	 */
	public function render_submenu() {

		if ( ! $this->current_user_is_allowed() ) {
			wp_die( esc_html__( 'You cannot view the Activity Log.', 'wpmastertoolkit' ), '', array( 'response' => 403 ) );
		}

		$assets = include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/activity-log.asset.php';
		wp_enqueue_style( 'wpmastertoolkit-activity-log', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/activity-log.css', array(), $assets['version'] );
		wp_enqueue_script( 'wpmastertoolkit-activity-log', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/activity-log.js', $assets['dependencies'], $assets['version'], true );
		wp_localize_script(
			'wpmastertoolkit-activity-log',
			'WPMastertoolkitActivityLog',
			array(
				'ajax_url'        => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( $this->nonce_action . '_search_users' ),
				'exclusion_nonce' => wp_create_nonce( $this->nonce_action . '_search_exclusions' ),
				'i18n'            => array(
					'placeholder'            => esc_html__( 'Search users...', 'wpmastertoolkit' ),
					'roles_placeholder'      => esc_html__( 'Select roles...', 'wpmastertoolkit' ),
					'post_types_placeholder' => esc_html__( 'Select post types...', 'wpmastertoolkit' ),
					'ip_placeholder'         => esc_html__( 'Enter an IP address or CIDR range...', 'wpmastertoolkit' ),
					'min_chars'              => esc_html__( 'Type at least 2 characters to search', 'wpmastertoolkit' ),
					'no_results'             => esc_html__( 'No users found', 'wpmastertoolkit' ),
					'no_roles'               => esc_html__( 'No roles found', 'wpmastertoolkit' ),
					'no_post_types'          => esc_html__( 'No supported post types found', 'wpmastertoolkit' ),
					'no_ips'                 => esc_html__( 'Enter an IP address or CIDR range', 'wpmastertoolkit' ),
					'ajax_error'             => esc_html__( 'Could not load choices. Please try again.', 'wpmastertoolkit' ),
					'loading'                => esc_html__( 'Loading...', 'wpmastertoolkit' ),
				),
			)
		);

		?>
		<form id="wpmastertoolkit-activity-file-form" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="page" value="<?php echo esc_attr( $this->page_slug ); ?>">
		</form>
		<form id="wpmastertoolkit-activity-purge-form" method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->page_slug ) ); ?>">
			<input type="hidden" name="wpmastertoolkit_activity_purge" value="1">
			<?php wp_nonce_field( $this->nonce_action . '_purge', 'wpmastertoolkit_activity_purge_nonce' ); ?>
		</form>
		<dialog id="wpmastertoolkit-activity-purge-dialog" class="wpmastertoolkit-activity-purge-dialog" aria-labelledby="wpmastertoolkit-activity-purge-title" aria-describedby="wpmastertoolkit-activity-purge-description">
			<h2 id="wpmastertoolkit-activity-purge-title"><?php esc_html_e( 'Purge old activity data?', 'wpmastertoolkit' ); ?></h2>
			<p id="wpmastertoolkit-activity-purge-description"></p>
			<div class="wpmastertoolkit-activity-purge-actions">
				<button type="button" class="button" data-purge-cancel autofocus><?php esc_html_e( 'Cancel', 'wpmastertoolkit' ); ?></button>
				<button type="button" class="button button-primary" data-purge-confirm><?php esc_html_e( 'Purge Old Data', 'wpmastertoolkit' ); ?></button>
			</div>
		</dialog>
		<?php
		include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
		$this->submenu_content();
		include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/footer.php';
	}

	/**
	 * Renders the content for the submenu section.
	 * 
	 * @since 2.26.0
	 */
	private function submenu_content() {
		$settings = $this->get_settings();
		$pro      = wpmastertoolkit_is_pro();
		$health   = WPMastertoolkit_Activity_Log_Storage::health();
		$files    = WPMastertoolkit_Activity_Log_Storage::available_files();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The file filter only reads existing activity segments.
		$requested_file = isset( $_GET['wpmastertoolkit_activity_file'] ) && is_string( $_GET['wpmastertoolkit_activity_file'] ) ? sanitize_text_field( wp_unslash( $_GET['wpmastertoolkit_activity_file'] ) ) : '';
		$selected_file  = in_array( $requested_file, $files, true ) ? $requested_file : ( $files[0] ?? '' );
		$labels         = $this->registry->labels();
		?>
		<h1 class="screen-reader-text"><?php esc_html_e( 'Activity Log', 'wpmastertoolkit' ); ?></h1>
		<?php if ( $this->provider_errors ) : ?>
			<div class="notice notice-warning" role="status">
				<p><?php esc_html_e( 'Some activity categories could not be registered. Check the Activity Log provider definitions.', 'wpmastertoolkit' ); ?></p>
			</div>
		<?php endif; ?>
		<?php if ( 'ok' !== $health ) : ?>
			<div class="notice notice-warning" role="status">
				<p><?php esc_html_e( 'Activity logging is paused or not yet verified. Check that this site can reach its own URL and PHP log files are executed, not served as downloads.', 'wpmastertoolkit' ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( isset( $_GET['updated'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['updated'] ) ) ) : //phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success" role="status">
				<p><?php esc_html_e( 'Settings saved.', 'wpmastertoolkit' ); ?></p>
			</div>
		<?php endif; ?>
		<?php if ( ! empty( $this->access_recovered[ get_current_blog_id() ] ) ) : ?>
			<div class="notice notice-warning" role="status">
				<p><?php esc_html_e( 'Activity Log access was restored to all administrators because the previous owner no longer has permission to manage this site.', 'wpmastertoolkit' ); ?></p>
			</div>
		<?php endif; ?>
		<?php $purge_notice = get_transient( $this->option_id . '_purge_' . get_current_user_id() ); ?>
		<?php if ( is_array( $purge_notice ) ) : ?>
			<?php delete_transient( $this->option_id . '_purge_' . get_current_user_id() ); ?>
			<div class="notice <?php echo esc_attr( ! empty( $purge_notice['errors'] ) || ! empty( $purge_notice['files_skipped'] ) || ! empty( $purge_notice['busy'] ) ? 'notice-warning' : 'notice-success' ); ?>" role="status">
				<p><?php echo esc_html( $this->retention_status_message( $purge_notice ) ); ?></p>
			</div>
		<?php endif; ?>

		<section class="wp-mastertoolkit__section" aria-labelledby="wpmastertoolkit-activity-access">
			<div class="wp-mastertoolkit__section__desc">
				<h2 id="wpmastertoolkit-activity-access"><?php esc_html_e( 'Who can access the Activity Log?', 'wpmastertoolkit' ); ?></h2>
			</div>
			<div class="wp-mastertoolkit__section__body">
				<fieldset class="wp-mastertoolkit__section__body__item wpmastertoolkit-activity-access" aria-describedby="wpmastertoolkit-activity-access-description">
					<legend class="wp-mastertoolkit__section__body__item__title">
						<?php esc_html_e( 'Restrict module access', 'wpmastertoolkit' ); ?>
						<?php if ( ! $pro ) : ?>
							<span class="wp-mastertoolkit__section__body__item__title__tag pro"><?php esc_html_e( 'PRO', 'wpmastertoolkit' ); ?></span>
						<?php endif; ?>
					</legend>
					<div class="wp-mastertoolkit__section__body__item__content wpmastertoolkit-activity-access-options">
						<label for="wpmastertoolkit-activity-access-only-me">
							<input id="wpmastertoolkit-activity-access-only-me" type="radio" name="<?php echo esc_attr( $this->option_id ); ?>[access_mode]" value="only_me" <?php checked( $pro && 'only_me' === $settings['access_mode'], true ); ?> <?php disabled( ! $pro ); ?>>
							<span class="wpmastertoolkit-activity-access-label"><?php esc_html_e( 'Only me', 'wpmastertoolkit' ); ?></span>
						</label>
						<label for="wpmastertoolkit-activity-access-all">
							<input id="wpmastertoolkit-activity-access-all" type="radio" name="<?php echo esc_attr( $this->option_id ); ?>[access_mode]" value="all_administrators" <?php checked( ! $pro || 'all_administrators' === $settings['access_mode'], true ); ?> <?php disabled( ! $pro ); ?>>
							<span class="wpmastertoolkit-activity-access-label"><?php esc_html_e( 'All administrators', 'wpmastertoolkit' ); ?></span>
						</label>
					</div>
				</fieldset>
			</div>
		</section>

		<?php $this->render_retention_settings( $settings ); ?>

		<section class="wp-mastertoolkit__section" aria-labelledby="wpmastertoolkit-activity-settings">
			<div class="wp-mastertoolkit__section__desc">
				<h2 id="wpmastertoolkit-activity-settings"><?php esc_html_e( 'Exclusions', 'wpmastertoolkit' ); ?></h2>
			</div>
			<div class="wp-mastertoolkit__section__body">
				<div class="wp-mastertoolkit__section__body__item">
					<div class="wp-mastertoolkit__section__body__item__title">
						<label for="wpmastertoolkit-activity-exclude"><?php esc_html_e( 'Exclude events by users', 'wpmastertoolkit' ); ?></label>
						<?php if ( ! $pro ) : ?>
							<span class="wp-mastertoolkit__section__body__item__title__tag pro"><?php esc_html_e( 'PRO', 'wpmastertoolkit' ); ?></span>
						<?php endif; ?>
					</div>
					<div class="wp-mastertoolkit__section__body__item__content">
						<div class="wp-mastertoolkit__select2">
							<?php if ( $pro ) : ?>
								<input type="hidden" name="<?php echo esc_attr( $this->option_id ); ?>[_submitted]" value="1">
							<?php endif; ?>
											<select id="wpmastertoolkit-activity-exclude" class="wpmastertoolkit-activity-exclusion-select" name="<?php echo esc_attr( $this->option_id ); ?>[exclude_user_ids][]" multiple="multiple" aria-describedby="wpmastertoolkit-activity-exclude-description" <?php disabled( ! $pro ); ?>>
								<?php foreach ( $settings['exclude_user_ids'] as $user_id ) : ?>
									<?php $user = get_userdata( $user_id ); ?>
									<?php if ( $user ) : ?>
										<option value="<?php echo esc_attr( $user->ID ); ?>" selected><?php echo esc_html( $user->user_login ); ?></option>
									<?php endif; ?>
								<?php endforeach; ?>
							</select>
						</div>
						<p id="wpmastertoolkit-activity-exclude-description" class="wp-mastertoolkit__section__body__item__content__desc"><?php esc_html_e( 'New activity by selected users will not be logged. Existing entries remain unchanged. Leave empty to log everyone.', 'wpmastertoolkit' ); ?></p>
						<?php if ( ! $pro ) : ?>
							<p class="wp-mastertoolkit__section__body__item__content__desc"><?php esc_html_e( 'User exclusions are available in Pro. All user activity is logged in the free version.', 'wpmastertoolkit' ); ?></p>
						<?php endif; ?>
					</div>
				</div>
				<?php
				$exclusion_fields = array(
					'exclude_roles'        => array(
						'id'          => 'wpmastertoolkit-activity-exclude-roles',
						'label'       => __( 'Exclude roles', 'wpmastertoolkit' ),
						'description' => __( 'New activity by users with any selected role will not be logged. Existing entries remain unchanged.', 'wpmastertoolkit' ),
					),
					'exclude_ip_addresses' => array(
						'id'          => 'wpmastertoolkit-activity-exclude-ip-addresses',
						'label'       => __( 'Exclude IP addresses', 'wpmastertoolkit' ),
						'description' => __( 'Exclude new activity from individual IPv4 or IPv6 addresses or CIDR ranges, such as 192.0.2.0/24. Only the server connection address is matched; proxy forwarding headers are not used.', 'wpmastertoolkit' ),
					),
					'exclude_post_types'   => array(
						'id'          => 'wpmastertoolkit-activity-exclude-post-types',
						'label'       => __( 'Exclude post types', 'wpmastertoolkit' ),
						'description' => __( 'Exclude new direct content and media activity for selected post types. Comments, menus, taxonomy, and WooCommerce activity remain unchanged.', 'wpmastertoolkit' ),
					),
				);
				?>
				<?php foreach ( $exclusion_fields as $key => $field ) : ?>
					<?php $choices = $this->exclusion_choices( $key ); ?>
					<div class="wp-mastertoolkit__section__body__item">
						<div class="wp-mastertoolkit__section__body__item__title">
							<label for="<?php echo esc_attr( $field['id'] ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
							<?php if ( ! $pro ) : ?>
								<span class="wp-mastertoolkit__section__body__item__title__tag pro"><?php esc_html_e( 'PRO', 'wpmastertoolkit' ); ?></span>
							<?php endif; ?>
						</div>
						<div class="wp-mastertoolkit__section__body__item__content">
							<div class="wp-mastertoolkit__select2">
								<select id="<?php echo esc_attr( $field['id'] ); ?>" class="wpmastertoolkit-activity-exclusion-select" name="<?php echo esc_attr( $this->option_id . '[' . $key . '][]' ); ?>" multiple="multiple" aria-describedby="<?php echo esc_attr( $field['id'] . '-description' ); ?>" <?php disabled( ! $pro ); ?>>
									<?php foreach ( $settings[ $key ] as $value ) : ?>
										<option value="<?php echo esc_attr( $value ); ?>" selected><?php echo esc_html( $choices[ $value ] ?? $value ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<p id="<?php echo esc_attr( $field['id'] . '-description' ); ?>" class="wp-mastertoolkit__section__body__item__content__desc"><?php echo esc_html( $field['description'] ); ?></p>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</section>

		<section class="wp-mastertoolkit__section" aria-labelledby="wpmastertoolkit-activity-events">
			<div class="wp-mastertoolkit__section__desc">
				<h2 id="wpmastertoolkit-activity-events"><?php esc_html_e( 'Events to record', 'wpmastertoolkit' ); ?></h2>
			</div>
			<div class="wp-mastertoolkit__section__body">
				<?php foreach ( $this->get_event_groups() as $group_id => $group ) : ?>
					<?php
					$enabled_events = array_filter(
						array_intersect_key( $settings['enabled_events'], $group['events'] ),
						static function ( $enabled ) {
							return '1' === $enabled;
						}
					);
					$category_id    = 'wpmastertoolkit-activity-category-' . $group_id;
					?>
					<details class="wp-mastertoolkit__section__body__item wpmastertoolkit-activity-event-category">
						<summary class="wp-mastertoolkit__section__body__item__title wpmastertoolkit-activity-event-category-toggle">
							<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
							<span id="<?php echo esc_attr( $category_id ); ?>" class="wpmastertoolkit-activity-event-category-label"><?php echo esc_html( $group['label'] ); ?></span>
							<?php if ( $group['pro'] && ! $pro ) : ?>
								<span class="wp-mastertoolkit__section__body__item__title__tag pro"><?php esc_html_e( 'PRO', 'wpmastertoolkit' ); ?></span>
							<?php endif; ?>
							<label class="wpmastertoolkit-activity-event-category-select">
								<input type="checkbox" class="wpmastertoolkit-activity-event-category-checkbox" aria-labelledby="<?php echo esc_attr( $category_id . ' ' . $category_id . '-select' ); ?>" <?php checked( ( ! $group['pro'] || $pro ) && count( $enabled_events ) === count( $group['events'] ), true ); ?> <?php disabled( $group['pro'] && ! $pro ); ?>>
								<span id="<?php echo esc_attr( $category_id . '-select' ); ?>"><?php esc_html_e( 'All events', 'wpmastertoolkit' ); ?></span>
							</label>
						</summary>
						<fieldset class="wp-mastertoolkit__section__body__item__content wpmastertoolkit-activity-event-group wpmastertoolkit-activity-log-events">
							<legend class="screen-reader-text"><?php echo esc_html( $group['label'] ); ?></legend>
							<?php foreach ( $group['events'] as $event => $label ) : ?>
								<div class="wp-mastertoolkit__checkbox">
									<label class="wp-mastertoolkit__checkbox__label">
										<?php if ( ! $group['pro'] || $pro ) : ?>
											<input type="hidden" name="<?php echo esc_attr( $this->option_id . '[enabled_events][' . $event . ']' ); ?>" value="0">
										<?php endif; ?>
										<input type="checkbox" name="<?php echo esc_attr( $this->option_id . '[enabled_events][' . $event . ']' ); ?>" value="1" <?php checked( ( ! $group['pro'] || $pro ) && '1' === $settings['enabled_events'][ $event ], true ); ?> <?php disabled( $group['pro'] && ! $pro ); ?>>
										<span class="mark"></span>
										<span class="wp-mastertoolkit__checkbox__label__text"><?php echo esc_html( $label ); ?></span>
									</label>
								</div>
							<?php endforeach; ?>
						</fieldset>
					</details>
				<?php endforeach; ?>
			</div>
		</section>

		<section class="wp-mastertoolkit__section" aria-labelledby="wpmastertoolkit-activity-recent">
			<div class="wp-mastertoolkit__section__desc">
				<h2 id="wpmastertoolkit-activity-recent"><?php esc_html_e( 'Recent activity', 'wpmastertoolkit' ); ?></h2>
			</div>
			<div class="wp-mastertoolkit__section__body" id="wpmastertoolkit-activity-viewer" data-activity-filter-enabled="<?php echo $pro ? '1' : '0'; ?>">
				<div class="wp-mastertoolkit__section__body__item">
					<div class="wp-mastertoolkit__section__body__item__content">
						<select id="wpmastertoolkit-activity-file" name="wpmastertoolkit_activity_file" form="wpmastertoolkit-activity-file-form">
							<?php if ( empty( $files ) ) : ?>
								<option value=""><?php esc_html_e( 'No log files', 'wpmastertoolkit' ); ?></option>
							<?php endif; ?>
							<?php foreach ( $files as $filename ) : ?>
								<option value="<?php echo esc_attr( $filename ); ?>" <?php selected( $selected_file, $filename ); ?>><?php echo esc_html( pathinfo( $filename, PATHINFO_FILENAME ) ); ?></option>
							<?php endforeach; ?>
						</select>
						<button type="submit" form="wpmastertoolkit-activity-file-form" class="button"><?php esc_html_e( 'Apply', 'wpmastertoolkit' ); ?></button>
					</div>
				</div>
				<div class="wpmastertoolkit-activity-filters" <?php echo $pro ? 'hidden' : ''; ?>>
					<div>
						<label for="wpmastertoolkit-activity-filter-event">
							<?php esc_html_e( 'Event', 'wpmastertoolkit' ); ?>
							<?php if ( ! $pro ) : ?>
								<span class="wp-mastertoolkit__section__body__item__title__tag pro"><?php esc_html_e( 'PRO', 'wpmastertoolkit' ); ?></span>
							<?php endif; ?>
						</label>
						<select id="wpmastertoolkit-activity-filter-event" data-activity-filter="event" <?php disabled( ! $pro ); ?>>
							<option value=""><?php esc_html_e( 'All events', 'wpmastertoolkit' ); ?></option>
						</select>
					</div>
					<div>
						<label for="wpmastertoolkit-activity-filter-user"><?php esc_html_e( 'User', 'wpmastertoolkit' ); ?></label>
						<select id="wpmastertoolkit-activity-filter-user" data-activity-filter="user" <?php disabled( ! $pro ); ?>>
							<option value=""><?php esc_html_e( 'All users', 'wpmastertoolkit' ); ?></option>
						</select>
					</div>
					<div>
						<label for="wpmastertoolkit-activity-filter-object"><?php esc_html_e( 'Object', 'wpmastertoolkit' ); ?></label>
						<select id="wpmastertoolkit-activity-filter-object" data-activity-filter="object" <?php disabled( ! $pro ); ?>>
							<option value=""><?php esc_html_e( 'All objects', 'wpmastertoolkit' ); ?></option>
						</select>
					</div>
					<button type="button" class="button" data-activity-filter-reset disabled><?php esc_html_e( 'Reset filters', 'wpmastertoolkit' ); ?></button>
				</div>
				<div class="wpmastertoolkit-activity-log-table">
					<table class="widefat striped">
						<caption class="screen-reader-text"><?php esc_html_e( 'Most recent activity events', 'wpmastertoolkit' ); ?></caption>
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Time (UTC)', 'wpmastertoolkit' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Event', 'wpmastertoolkit' ); ?></th>
								<th scope="col"><?php esc_html_e( 'User', 'wpmastertoolkit' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Object', 'wpmastertoolkit' ); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ( WPMastertoolkit_Activity_Log_Storage::file_records( $selected_file ) as $record ) : ?>
							<?php
							$actor_id = isset( $record['actor_id'] ) && is_scalar( $record['actor_id'] ) ? absint( $record['actor_id'] ) : 0;
							$actor    = $actor_id ? get_userdata( $actor_id ) : false;
							$event    = isset( $record['event'] ) && is_string( $record['event'] ) ? $record['event'] : '';
							$details  = $this->normalize_details( $event, $record['details'] ?? array() );
							$object   = ( isset( $record['object'] ) && is_string( $record['object'] ) ? $record['object'] : '' ) . ' #' . ( isset( $record['object_id'] ) && is_scalar( $record['object_id'] ) ? $record['object_id'] : 0 );
							?>
							<tr data-event="<?php echo esc_attr( $event ); ?>" data-user="<?php echo esc_attr( $actor_id ); ?>" data-object="<?php echo esc_attr( $object ); ?>">
								<td><?php echo esc_html( isset( $record['time'] ) && is_string( $record['time'] ) ? $record['time'] : '' ); ?></td>
								<td>
									<span data-activity-label="event"><?php echo esc_html( $labels[ $event ] ?? $event ); ?></span>
									<?php if ( $details ) : ?>
										<details class="wpmastertoolkit-activity-details">
											<summary><?php esc_html_e( 'Details', 'wpmastertoolkit' ); ?></summary>
											<dl>
												<?php foreach ( $details as $field => $value ) : ?>
													<dt><?php echo esc_html( $this->registry->detail_label( $event, $field ) ); ?></dt>
													<dd><?php echo esc_html( is_array( $value ) ? implode( ', ', $value ) : (string) $value ); ?></dd>
												<?php endforeach; ?>
											</dl>
										</details>
									<?php endif; ?>
								</td>
								<td data-activity-label="user"><?php echo esc_html( $actor ? sprintf( '%s (#%d)', $actor->display_name ?: $actor->user_login, $actor_id ) : $actor_id ); ?></td>
								<td data-activity-label="object"><?php echo esc_html( $object ); ?></td>
							</tr>
						<?php endforeach; ?>
							<tr data-activity-filter-empty hidden>
								<td colspan="4" role="status"><?php esc_html_e( 'No activity matches the selected filters.', 'wpmastertoolkit' ); ?></td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</section>
		<?php
	}
}
