<?php
/**
 * Local CAPTCHA protection for native authentication forms.
 *
 * @package WPMastertoolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module Name: Captcha on login form
 * Description: Add a local arithmetic challenge to native login, recovery and registration forms.
 *
 * @since 2.26.0
 */
class WPMastertoolkit_Captcha_Login_Form {

	/**
	 * Settings option.
	 *
	 * @var string
	 */
	private $option_id;

	/**
	 * Settings nonce.
	 *
	 * @var string
	 */
	private $nonce_action;

	/**
	 * Settings heading.
	 *
	 * @var string
	 */
	private $header_title;

	/**
	 * Native controller context.
	 *
	 * @var string
	 */
	private $context = '';

	/**
	 * Browser binding, never an authorization token.
	 *
	 * @var string
	 */
	private $browser = '';

	/**
	 * Memoized final login validation.
	 *
	 * @var true|WP_Error|null
	 */
	private $login_result;

	/**
	 * Safe presentation error.
	 *
	 * @var WP_Error|null
	 */
	private $display_error;

	/**
	 * Assurance established by the current request, never client input.
	 *
	 * @var string
	 */
	private $assurance = '';

	/**
	 * Avoid recording a repeated failure within one native submission.
	 *
	 * @var bool
	 */
	private $failure_recorded = false;

	/**
	 * Whether a consumed, browser-owned state may receive a replacement.
	 *
	 * @var bool
	 */
	private $replacement_allowed = false;

	/**
	 * Register native adapters and optional security integrations.
	 */
	public function __construct() {
		$this->option_id    = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_captcha_login_form';
		$this->nonce_action = $this->option_id . '_action';

		add_action( 'init', array( $this, 'class_init' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
		add_action( 'login_init', array( $this, 'login_init' ) );
		add_action( 'login_form', array( $this, 'render_challenge' ), 20 );
		add_action( 'lostpassword_form', array( $this, 'render_challenge' ), 20 );
		add_action( 'register_form', array( $this, 'render_challenge' ), 20 );
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue_public' ), 5 );
		add_filter( 'authenticate', array( $this, 'prepare_login' ), 10 );
		add_filter( 'wp_authenticate_user', array( $this, 'guard_user' ), 1 );
		add_filter( 'authenticate', array( $this, 'guard_login' ), 1000 );
		add_action( 'wp_login_failed', array( $this, 'record_auth_failure' ), 10, 2 );
		add_filter( 'lostpassword_errors', array( $this, 'guard_recovery' ), 20 );
		add_filter( 'registration_errors', array( $this, 'guard_registration' ), 20 );
		add_filter( 'login_errors', array( $this, 'safe_login_error' ), 1000 );
		add_filter( 'wpmastertoolkit_count_failed_login', array( $this, 'count_failed_login' ), 10, 2 );
		add_filter( 'wpmastertoolkit_tfa_generate_code_permission', array( $this, 'guard_code_send' ), 10, 2 );
		add_filter( 'wpmastertoolkit_tfa_public_dependencies', array( $this, 'tfa_dependencies' ) );
		add_action( 'wpmastertoolkit_captcha_cleanup', array( __CLASS__, 'prune' ) );
		foreach ( array( 'refresh', 'prepare', 'cancel' ) as $operation ) {
			add_action( 'wp_ajax_wpmastertoolkit_captcha_' . $operation, array( $this, 'ajax_' . $operation ) );
			add_action( 'wp_ajax_nopriv_wpmastertoolkit_captcha_' . $operation, array( $this, 'ajax_' . $operation ) );
		}
	}

	/**
	 * Translate settings text after WordPress initializes text domains.
	 */
	public function class_init() {
		$this->header_title = __( 'Captcha on login form', 'wpmastertoolkit' );
	}

	/**
	 * Get per-site table name.
	 *
	 * @return string
	 */
	private static function table() {
		global $wpdb;
		return $wpdb->prefix . WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_captcha_login_form_state';
	}

	/**
	 * Refuse activation until the current site's storage is operational.
	 *
	 * @throws RuntimeException When the module manager must retain the inactive state.
	 */
	public static function activate() {
		if ( is_wp_error( self::initialize_storage() ) ) {
			throw new RuntimeException( esc_html__( 'CAPTCHA could not be enabled because its storage could not be verified. Check database permissions and named-lock support, then try again.', 'wpmastertoolkit' ) );
		}
		$site_id = get_current_blog_id();
		add_action(
			'shutdown',
			static function () use ( $site_id ) {
				self::schedule_cleanup( $site_id );
			}
		);
	}

	/**
	 * Schedule only for a successfully persisted module state on the original site.
	 *
	 * @param int $site_id Site whose storage was initialized.
	 */
	private static function schedule_cleanup( $site_id ) {
		$switched = get_current_blog_id() !== $site_id;
		if ( $switched ) {
			switch_to_blog( $site_id );
		}
		try {
			$settings = get_option( WPMASTERTOOLKIT_PLUGIN_SETTINGS, array() );
			if ( ( defined( 'WPMASTERTOOLKIT_SAFE_MODE' ) && WPMASTERTOOLKIT_SAFE_MODE ) || ! is_array( $settings ) || ! in_array( $settings[ __CLASS__ ] ?? null, array( '1', 1 ), true ) ) {
				return;
			}
			if ( '1' === get_option( WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_captcha_login_form_schema' ) && ! wp_next_scheduled( 'wpmastertoolkit_captcha_cleanup' ) ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'wpmastertoolkit_captcha_cleanup' );
			}
		} finally {
			if ( $switched ) {
				restore_current_blog();
			}
		}
	}

	/**
	 * Initialize storage only in an authorized administrative request.
	 */
	public function admin_init() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( is_wp_error( self::initialize_storage() ) ) {
			add_action( 'admin_notices', array( $this, 'storage_notice' ) );
			return;
		}
		self::schedule_cleanup( get_current_blog_id() );
		if ( $this->post( '_wpnonce' ) && wp_verify_nonce( $this->post( '_wpnonce' ), $this->nonce_action ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Allowlisted values are validated by sanitize_settings below.
			$posted   = isset( $_POST[ $this->option_id ] ) && is_array( $_POST[ $this->option_id ] ) ? wp_unslash( $_POST[ $this->option_id ] ) : array();
			$settings = $this->sanitize_settings( $posted );
			if ( is_wp_error( $settings ) ) {
				add_settings_error( $this->option_id, 'invalid', $settings->get_error_message() );
				return;
			}
			update_option( $this->option_id, $settings, false );
			self::invalidate();
			wp_safe_redirect( admin_url( 'admin.php?page=wp-mastertoolkit-settings-captcha-login-form' ) );
			exit;
		}
	}

	/**
	 * Verify storage before publishing readiness, without registering public hooks.
	 *
	 * @return true|WP_Error
	 */
	private static function initialize_storage() {
		global $wpdb;
		$suppressed = $wpdb->suppress_errors( true );
		$lock       = self::acquire_state_lock();
		if ( is_wp_error( $lock ) ) {
			$wpdb->suppress_errors( $suppressed );
			return $lock;
		}
		try {
			$result = self::prepare_storage();
		} finally {
			try {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Verify release of the acquired readiness lock on every setup path.
				$released = $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
			} finally {
				$wpdb->suppress_errors( $suppressed );
			}
		}
		if ( is_wp_error( $result ) || '1' !== (string) $released ) {
			return is_wp_error( $result ) ? $result : self::error( 'storage' );
		}
		$option = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_captcha_login_form';
		$epoch  = get_option( $option . '_epoch', '' );
		if ( 'adaptive-1' !== get_option( $option . '_policy_version' ) || ! is_string( $epoch ) || ! preg_match( '/^[a-zA-Z0-9]{32}$/D', $epoch ) ) {
			$epoch = wp_generate_password( 32, false, false );
			update_option( $option . '_epoch', $epoch, false );
			if ( get_option( $option . '_epoch' ) !== $epoch ) {
				return self::error( 'storage' );
			}
			update_option( $option . '_policy_version', 'adaptive-1', false );
			if ( 'adaptive-1' !== get_option( $option . '_policy_version' ) ) {
				return self::error( 'storage' );
			}
		}
		update_option( $option . '_schema', '1', false );
		return '1' === get_option( $option . '_schema' ) ? true : self::error( 'storage' );
	}

	/**
	 * Repair and exercise module-owned storage while admission is locked.
	 *
	 * @return true|WP_Error
	 */
	private static function prepare_storage() {
		global $wpdb;
		$table = self::table();
		if ( ! self::valid_schema() ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			$collate = $wpdb->get_charset_collate();
			dbDelta(
				"CREATE TABLE $table (
				record_key varchar(64) NOT NULL,
				kind varchar(12) NOT NULL,
				binding varchar(64) NOT NULL DEFAULT '',
				purpose varchar(16) NOT NULL DEFAULT '',
				identifier varchar(64) NOT NULL DEFAULT '',
				verifier varchar(64) NOT NULL DEFAULT '',
				expires bigint(20) unsigned NOT NULL,
				counter int(11) unsigned NOT NULL DEFAULT 0,
				consumed tinyint(1) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (record_key),
				KEY expires (expires)
			) $collate;"
			);
			if ( ! self::valid_schema() ) {
				return self::error( 'storage' );
			}
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reclaim expired records without evicting live security state during readiness admission.
		$pruned = $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE expires <= %d LIMIT 1000', $table, time() ) );
		if ( false === $pruned ) {
			return self::error( 'storage' );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admit the expiring probe under the same capacity lock as security records.
		$count = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
		if ( null === $count || $wpdb->last_error || 10000 <= (int) $count ) {
			return self::error( null === $count || $wpdb->last_error ? 'storage' : 'limit' );
		}
		$key = hash( 'sha256', wp_generate_password( 64, false, false ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- This random, short-lived probe contains no visitor data.
		$inserted = $wpdb->insert(
			$table,
			array(
				'record_key' => $key,
				'kind'       => 'probe',
				'expires'    => time() + MINUTE_IN_SECONDS,
			)
		);
		if ( 1 !== $inserted ) {
			return self::error( 'storage' );
		}
		try {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Verify that state can be read before testing mutation.
			$before = $wpdb->get_var( $wpdb->prepare( 'SELECT counter FROM %i WHERE record_key = %s', $table, $key ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Only mutate this initializer's own probe.
			$updated = $wpdb->update( $table, array( 'counter' => 1 ), array( 'record_key' => $key ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Check the stored mutation, not just the affected-row result.
			$after = $wpdb->get_var( $wpdb->prepare( 'SELECT counter FROM %i WHERE record_key = %s', $table, $key ) );
		} finally {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Remove only the successfully inserted probe, even after a failed verification.
			$deleted = $wpdb->delete( $table, array( 'record_key' => $key ) );
		}
		return '0' === (string) $before && 1 === $updated && '1' === (string) $after && 1 === $deleted ? true : self::error( 'storage' );
	}

	/**
	 * Check the columns and indexes required by the authentication queries.
	 *
	 * @return bool
	 */
	private static function valid_schema() {
		global $wpdb;
		$expected = array(
			'record_key' => '/^varchar\(64\)$/i',
			'kind'       => '/^varchar\(12\)$/i',
			'binding'    => '/^varchar\(64\)$/i',
			'purpose'    => '/^varchar\(16\)$/i',
			'identifier' => '/^varchar\(64\)$/i',
			'verifier'   => '/^varchar\(64\)$/i',
			'expires'    => '/^bigint(?:\([0-9]+\))? unsigned$/i',
			'counter'    => '/^int(?:\([0-9]+\))? unsigned$/i',
			'consumed'   => '/^tinyint(?:\([0-9]+\))? unsigned$/i',
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Do not trust a schema option when the actual table may be missing or incomplete.
		$columns = $wpdb->get_results( $wpdb->prepare( 'SHOW COLUMNS FROM %i', self::table() ), ARRAY_A );
		if ( ! $columns || $wpdb->last_error ) {
			return false;
		}
		foreach ( $columns as $column ) {
			if ( isset( $expected[ $column['Field'] ] ) && 'NO' === $column['Null'] && preg_match( $expected[ $column['Field'] ], $column['Type'] ) ) {
				unset( $expected[ $column['Field'] ] );
			}
		}
		if ( $expected ) {
			return false;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Single-use keys and indexed expiry are required storage invariants.
		$indexes = $wpdb->get_results( $wpdb->prepare( 'SHOW INDEX FROM %i', self::table() ), ARRAY_A );
		if ( ! $indexes || $wpdb->last_error ) {
			return false;
		}
		$primary = array();
		$expires = false;
		foreach ( $indexes as $index ) {
			if ( 'PRIMARY' === $index['Key_name'] ) {
				$primary[ (int) $index['Seq_in_index'] ] = $index['Column_name'];
			}
			if ( 1 === (int) $index['Seq_in_index'] && 'expires' === $index['Column_name'] ) {
				$expires = true;
			}
		}
		return array( 1 => 'record_key' ) === $primary && $expires;
	}

	/**
	 * Default policy follows the available edition; form selections stay independent.
	 *
	 * @return array
	 */
	private function get_default_settings() {
		return array(
			'login'                      => '1',
			'lostpassword'               => '1',
			'register'                   => '1',
			'mode'                       => wpmastertoolkit_is_pro() ? 'adaptive' : 'always',
			'timing_enabled'             => '1',
			'observation_window_minutes' => 5,
			'challenge_period_minutes'   => 15,
			'browser_auth_failures'      => 3,
			'ip_auth_failures'           => 5,
			'login_attempts'             => 10,
			'recovery_attempts'          => 3,
			'registration_attempts'      => 3,
			'tfa_send_attempts'          => 3,
			'fast_submission_ms'         => 1500,
		);
	}

	/**
	 * Numeric policy bounds for both stored and submitted settings.
	 *
	 * @return array
	 */
	private function setting_bounds() {
		return array(
			'observation_window_minutes' => array( 1, 60 ),
			'challenge_period_minutes'   => array( 1, 60 ),
			'browser_auth_failures'      => array( 1, 100 ),
			'ip_auth_failures'           => array( 1, 100 ),
			'login_attempts'             => array( 1, 100 ),
			'recovery_attempts'          => array( 1, 100 ),
			'registration_attempts'      => array( 1, 100 ),
			'tfa_send_attempts'          => array( 1, 100 ),
			'fast_submission_ms'         => array( 100, 5000 ),
		);
	}

	/**
	 * Read settings with safe defaults for malformed stored values.
	 *
	 * @return array
	 */
	private function get_settings() {
		$settings = $this->get_default_settings();
		$stored   = get_option( $this->option_id, array() );
		if ( ! is_array( $stored ) ) {
			return $settings;
		}
		foreach ( array( 'login', 'lostpassword', 'register', 'timing_enabled' ) as $key ) {
			if ( isset( $stored[ $key ] ) && in_array( $stored[ $key ], array( '0', '1' ), true ) ) {
				$settings[ $key ] = $stored[ $key ];
			}
		}
		if ( isset( $stored['mode'] ) && in_array( $stored['mode'], array( 'always', 'adaptive' ), true ) ) {
			$settings['mode'] = wpmastertoolkit_is_pro() ? $stored['mode'] : 'always';
		}
		foreach ( $this->setting_bounds() as $key => $bounds ) {
			if ( isset( $stored[ $key ] ) && ( is_int( $stored[ $key ] ) || is_string( $stored[ $key ] ) ) && preg_match( '/^[0-9]{1,4}$/D', (string) $stored[ $key ] ) && (int) $stored[ $key ] >= $bounds[0] && (int) $stored[ $key ] <= $bounds[1] ) {
				$settings[ $key ] = (int) $stored[ $key ];
			}
		}
		return $settings;
	}

	/**
	 * Validate admin inputs instead of silently accepting unsafe thresholds.
	 *
	 * @param array $posted Submitted settings.
	 * @return array|WP_Error
	 */
	private function sanitize_settings( $posted ) {
		$settings = $this->get_settings();
		foreach ( array( 'login', 'lostpassword', 'register' ) as $key ) {
			if ( isset( $posted[ $key ] ) && ! in_array( $posted[ $key ], array( '0', '1' ), true ) ) {
				return new WP_Error( 'invalid', __( 'Select valid CAPTCHA options.', 'wpmastertoolkit' ) );
			}
			$settings[ $key ] = $posted[ $key ] ?? '0';
		}
		if ( isset( $posted['mode'] ) ) {
			if ( ! in_array( $posted['mode'], array( 'always', 'adaptive' ), true ) ) {
				return new WP_Error( 'invalid', __( 'Select a valid CAPTCHA mode.', 'wpmastertoolkit' ) );
			}
			if ( 'adaptive' === $posted['mode'] && ! wpmastertoolkit_is_pro() ) {
				return new WP_Error( 'invalid', __( 'Adaptive CAPTCHA requires WPMasterToolKit Pro.', 'wpmastertoolkit' ) );
			}
			$settings['mode'] = $posted['mode'];
		}
		if ( 'adaptive' !== $settings['mode'] || ! wpmastertoolkit_is_pro() ) {
			return $settings;
		}
		if ( isset( $posted['timing_enabled'] ) ) {
			if ( ! in_array( $posted['timing_enabled'], array( '0', '1' ), true ) ) {
				return new WP_Error( 'invalid', __( 'Select valid CAPTCHA options.', 'wpmastertoolkit' ) );
			}
			$settings['timing_enabled'] = $posted['timing_enabled'];
		}
		foreach ( $this->setting_bounds() as $key => $bounds ) {
			if ( ! isset( $posted[ $key ] ) ) {
				continue;
			}
			$value = $posted[ $key ];
			if ( ! is_string( $value ) || ! preg_match( '/^[0-9]{1,4}$/D', $value ) || (int) $value < $bounds[0] || (int) $value > $bounds[1] ) {
				return new WP_Error( 'invalid', __( 'Enter CAPTCHA thresholds within the displayed limits.', 'wpmastertoolkit' ) );
			}
			$settings[ $key ] = (int) $value;
		}
		return $settings;
	}

	/**
	 * Report provisioning failure without silently opening authentication.
	 */
	public function storage_notice() {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'CAPTCHA storage is unavailable or busy. Check database permissions and support for named locks, or disable this module before signing out.', 'wpmastertoolkit' ) . '</p></div>';
	}

	/**
	 * Remove expired state in bounded indexed batches.
	 */
	public static function prune() {
		global $wpdb;
		if ( '1' !== get_option( WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_captcha_login_form_schema' ) ) {
			return;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Expiring security state must bypass caches.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE expires <= %d LIMIT 1000', self::table(), time() ) );
	}

	/**
	 * Invalidate outstanding proofs after a policy change.
	 */
	public static function invalidate() {
		update_option( WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_captcha_login_form_epoch', wp_generate_password( 32, false, false ), false );
	}

	/**
	 * Clean ephemeral storage on the current site.
	 */
	public static function cleanup() {
		global $wpdb;
		wp_clear_scheduled_hook( 'wpmastertoolkit_captcha_cleanup' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Remove only this module's ephemeral table.
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', self::table() ) );
		delete_option( WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_captcha_login_form_schema' );
		delete_option( WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_captcha_login_form_epoch' );
	}

	/**
	 * Clean affected sites without provisioning untouched sites.
	 *
	 * @param bool $network_wide Whether the plugin is network-deactivated.
	 * @param bool $uninstall Whether saved settings should also be removed.
	 */
	public static function cleanup_sites( $network_wide = false, $uninstall = false ) {
		if ( is_multisite() && $network_wide ) {
			$offset = 0;
			do {
				$sites = get_sites(
					array(
						'fields' => 'ids',
						'number' => 100,
						'offset' => $offset,
					)
				);
				$count = count( $sites );
				foreach ( $sites as $site_id ) {
					switch_to_blog( $site_id );
					self::cleanup_sites( false, $uninstall );
					restore_current_blog();
				}
				$offset += 100;
			} while ( 100 === $count );
			return;
		}
		self::cleanup();
		if ( $uninstall ) {
			delete_option( WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_captcha_login_form' );
		}
	}

	/**
	 * Read one bounded scalar POST value.
	 *
	 * @param string $key Input key.
	 * @return string
	 */
	private function post( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Native forms are authenticated by a single-use challenge; AJAX has an additional nonce check.
		if ( ! isset( $_POST[ $key ] ) || ! is_string( $_POST[ $key ] ) ) {
			return '';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Scalar input is sanitized here, then validated by purpose.
		$value = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
		return strlen( $value ) <= 200 ? $value : '';
	}

	/**
	 * Determine enabled form without enabling registration itself.
	 *
	 * @param string $purpose Native action.
	 * @return bool
	 */
	private function enabled( $purpose ) {
		$settings = $this->get_settings();
		return in_array( $purpose, array( 'login', 'lostpassword', 'register' ), true ) && '1' === $settings[ $purpose ];
	}

	/**
	 * Bind the native controller, including renamed and interim login routes.
	 */
	public function login_init() {
		global $action;
		$this->context = 'retrievepassword' === $action ? 'lostpassword' : (string) $action;
		if ( $this->enabled( $this->context ) ) {
			nocache_headers();
			$this->bind_browser( true );
		}
	}

	/**
	 * Read or issue an HttpOnly first-party binding cookie.
	 *
	 * @param bool $create Whether an initial page can create a cookie.
	 * @return string
	 */
	private function bind_browser( $create = false ) {
		if ( $this->browser ) {
			return $this->browser;
		}
		$name  = 'wpmtk_captcha_' . get_current_blog_id();
		$value = isset( $_COOKIE[ $name ] ) && is_string( $_COOKIE[ $name ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ $name ] ) ) : '';
		if ( ! preg_match( '/^[a-zA-Z0-9]{48}$/D', $value ) ) {
			if ( ! $create || headers_sent() ) {
				return '';
			}
			$value = wp_generate_password( 48, false, false );
			setcookie(
				$name,
				$value,
				array(
					'expires'  => 0,
					'path'     => '/',
					'secure'   => is_ssl(),
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);
		}
		$this->browser = $this->digest( $value );
		return $this->browser;
	}

	/**
	 * Compute a site- and policy-specific keyed digest.
	 *
	 * @param string $value Value to bind.
	 * @return string
	 */
	private function digest( $value ) {
		return hash_hmac( 'sha256', get_current_blog_id() . '|' . get_option( $this->option_id . '_epoch', '' ) . '|' . $value, wp_salt( 'auth' ) );
	}

	/**
	 * Generic errors contain no account information.
	 *
	 * @param string $type Error category.
	 * @return WP_Error
	 */
	private static function error( $type = 'invalid' ) {
		$messages = array(
			'invalid'  => __( 'The CAPTCHA is incorrect or expired. Please solve a new question.', 'wpmastertoolkit' ),
			'storage'  => __( 'CAPTCHA is temporarily unavailable. Please try again later.', 'wpmastertoolkit' ),
			'limit'    => __( 'Too many CAPTCHA requests. Please wait five minutes and try again.', 'wpmastertoolkit' ),
			'cookie'   => __( 'Please enable cookies and reload the page to complete the CAPTCHA.', 'wpmastertoolkit' ),
			'required' => __( 'Please complete the security check to continue.', 'wpmastertoolkit' ),
			'context'  => __( 'The security check session is invalid. Please reload the page.', 'wpmastertoolkit' ),
		);
		return new WP_Error( 'wpmastertoolkit_captcha_' . $type, $messages[ $type ] );
	}

	/**
	 * Atomically reserve one operation in a non-sliding fixed window.
	 *
	 * @param string $bucket Operation and actor digest.
	 * @param int    $limit Maximum operations per five minutes.
	 * @return true|WP_Error
	 */
	private function rate( $bucket, $limit ) {
		global $wpdb;
		if ( '1' !== get_option( $this->option_id . '_schema' ) ) {
			return $this->error( 'storage' );
		}
		$window  = (int) floor( time() / ( 5 * MINUTE_IN_SECONDS ) );
		$key     = $this->digest( 'rate|' . $bucket . '|' . $window );
		$expires = ( $window + 1 ) * 5 * MINUTE_IN_SECONDS;
		$created = $this->store_state(
			array(
				'record_key' => $key,
				'kind'       => 'rate',
				'expires'    => $expires,
			)
		);
		if ( is_wp_error( $created ) ) {
			return $created;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The affected row is the operation reservation.
		$claimed = $wpdb->query( $wpdb->prepare( 'UPDATE %i SET counter = counter + 1 WHERE record_key = %s AND counter < %d AND expires > %d', self::table(), $key, $limit, time() ) );
		return 1 === $claimed ? true : $this->error( false === $claimed ? 'storage' : 'limit' );
	}

	/**
	 * Reject exhausted actors before reserving shared capacity.
	 *
	 * @param string $operation Rate category.
	 * @param int    $limit Actor limit.
	 * @return true|WP_Error
	 */
	private function throttle( $operation, $limit ) {
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) && is_string( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$actor = $this->rate( $operation . '|' . $this->digest( $ip ), max( 1, min( 1000, (int) apply_filters( 'wpmastertoolkit_captcha_' . $operation . '_limit', $limit ) ) ) );
		if ( is_wp_error( $actor ) ) {
			return $actor;
		}
		if ( 'issue' === $operation ) {
			$browser = $this->rate( 'browser|' . $this->browser, 60 );
			if ( is_wp_error( $browser ) ) {
				return $browser;
			}
		}
		return $this->rate( 'site|' . $operation, 'issue' === $operation ? 1000 : 3000 );
	}

	/**
	 * Acquire a bounded per-site admission lock on the current connection.
	 *
	 * @return string|WP_Error Lock name or a safe storage error.
	 */
	private static function acquire_state_lock() {
		global $wpdb;
		$lock = 'wpmtk_captcha_' . substr( hash( 'sha256', DB_NAME . '|' . self::table() ), 0, 48 );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Serialize admission across database connections with a bounded one-second wait.
		$acquired = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 1)', $lock ) );
		if ( '1' !== (string) $acquired ) {
			return self::error( 'storage' );
		}
		return $lock;
	}

	/**
	 * Serialize new state admission without evicting live security records.
	 *
	 * @param array $record Module-owned state to insert or update.
	 * @return int|WP_Error Affected rows or a safe storage error.
	 */
	private function store_state( $record ) {
		global $wpdb;
		$lock = $this->acquire_state_lock();
		if ( is_wp_error( $lock ) ) {
			return $lock;
		}
		try {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Check admission while holding the site-specific lock.
			$existing = $wpdb->get_var( $wpdb->prepare( 'SELECT record_key FROM %i WHERE record_key = %s', self::table(), $record['record_key'] ) );
			if ( $wpdb->last_error ) {
				return $this->error( 'storage' );
			}
			if ( null === $existing ) {
				self::prune();
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- All insert paths share the same locked capacity check.
				$count = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', self::table() ) );
				if ( null === $count || $wpdb->last_error || 10000 <= (int) $count ) {
					return $this->error( null === $count || $wpdb->last_error ? 'storage' : 'limit' );
				}
			}
			if ( 'rate' === $record['kind'] ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Preserve existing fixed-window counters.
				$result = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO %i (record_key,kind,expires) VALUES (%s,'rate',%d)", self::table(), $record['record_key'], $record['expires'] ) );
			} elseif ( 'risk' === $record['kind'] ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reserve bounded security activity atomically.
				$result = $wpdb->query( $wpdb->prepare( "INSERT INTO %i (record_key,kind,expires,counter) VALUES (%s,'risk',%d,1) ON DUPLICATE KEY UPDATE counter = LEAST(counter + 1,1000)", self::table(), $record['record_key'], $record['expires'] ) );
			} elseif ( 'required' === $record['kind'] ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Retrying never slides a live marker's expiry.
				$result = $wpdb->query( $wpdb->prepare( "INSERT INTO %i (record_key,kind,expires) VALUES (%s,'required',%d) ON DUPLICATE KEY UPDATE expires = IF(expires <= %d,VALUES(expires),expires)", self::table(), $record['record_key'], $record['expires'], time() ) );
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Store module-owned single-use state only after capacity admission.
				$result = $wpdb->insert( self::table(), $record );
			}
			return false === $result ? $this->error( 'storage' ) : $result;
		} finally {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Release on success, capacity rejection, and database failure alike.
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
	}

	/**
	 * Risk actors are browser and server-observed IP hashes, never account names.
	 *
	 * @return array
	 */
	private function risk_actors() {
		$ip     = isset( $_SERVER['REMOTE_ADDR'] ) && is_string( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$actors = array( 'ip' => $this->digest( 'ip|' . $ip ) );
		if ( $this->bind_browser() ) {
			$actors['browser'] = $this->browser;
		}
		return $actors;
	}

	/**
	 * Compute a fixed-window risk key.
	 *
	 * @param string $event Event category.
	 * @param string $actor Actor hash.
	 * @return string
	 */
	private function risk_key( $event, $actor ) {
		$duration = $this->get_settings()['observation_window_minutes'] * MINUTE_IN_SECONDS;
		return $this->digest( 'risk|' . $event . '|' . $actor . '|' . (int) floor( time() / $duration ) );
	}

	/**
	 * Reserve actual activity atomically, independently of credential lockouts.
	 *
	 * @param string $event Activity category.
	 * @return true|WP_Error
	 */
	private function record_risk_event( $event ) {
		$budget = $this->throttle( 'activity', 200 );
		if ( is_wp_error( $budget ) ) {
			return $budget;
		}
		$duration = $this->get_settings()['observation_window_minutes'] * MINUTE_IN_SECONDS;
		$expires  = ( (int) floor( time() / $duration ) + 1 ) * $duration;
		foreach ( $this->risk_actors() as $actor ) {
			$result = $this->store_state(
				array(
					'record_key' => $this->risk_key( $event, $actor ),
					'kind'       => 'risk',
					'expires'    => $expires,
				)
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
		return true;
	}

	/**
	 * Evaluate current server activity without incrementing it on reads.
	 *
	 * @param string $purpose Native form context.
	 * @param bool   $fast Coherent optional fast-submission hint.
	 * @return string|WP_Error Allow or challenge, or a hard rejection.
	 */
	private function assess_risk( $purpose, $fast = false ) {
		global $wpdb;
		if ( '1' !== get_option( $this->option_id . '_schema' ) ) {
			return $this->error( 'storage' );
		}
		if ( ! $this->bind_browser() ) {
			return $this->error( 'cookie' );
		}
		$settings = $this->get_settings();
		if ( 'always' === $settings['mode'] ) {
			return 'challenge';
		}
		$events = array(
			$purpose => $settings[ array(
				'login'        => 'login_attempts',
				'lostpassword' => 'recovery_attempts',
				'register'     => 'registration_attempts',
			)[ $purpose ] ],
		);
		foreach ( $this->risk_actors() as $kind => $actor ) {
			$marker = $this->digest( 'required|' . $purpose . '|' . $actor );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Current security marker must bypass caches.
			$required = $wpdb->get_var( $wpdb->prepare( "SELECT expires FROM %i WHERE record_key = %s AND kind = 'required' AND expires > %d", self::table(), $marker, time() ) );
			if ( $wpdb->last_error ) {
				return $this->error( 'storage' );
			}
			if ( $required ) {
				return 'challenge';
			}
			$actor_events = $events;
			if ( 'login' === $purpose ) {
				$actor_events['auth'] = $settings[ 'browser' === $kind ? 'browser_auth_failures' : 'ip_auth_failures' ];
				$actor_events['send'] = $settings['tfa_send_attempts'];
			}
			foreach ( $actor_events as $event => $threshold ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read reserved activity, never manufacture a risk event.
				$count = $wpdb->get_var( $wpdb->prepare( "SELECT counter FROM %i WHERE record_key = %s AND kind = 'risk' AND expires > %d", self::table(), $this->risk_key( $event, $actor ), time() ) );
				if ( $wpdb->last_error ) {
					return $this->error( 'storage' );
				}
				if ( (int) $count >= $threshold || ( $fast && 'send' !== $event && (int) $count >= max( 2, (int) ceil( $threshold / 2 ) ) ) ) {
					$now    = time();
					$result = $this->store_state(
						array(
							'record_key' => $marker,
							'kind'       => 'required',
							'expires'    => $now + $settings['challenge_period_minutes'] * MINUTE_IN_SECONDS,
						)
					);
					return is_wp_error( $result ) ? $result : 'challenge';
				}
			}
		}
		return 'allow';
	}

	/**
	 * Observe only real native authentication failures after an allowed gate.
	 *
	 * @param string   $username Entered identifier, deliberately not retained.
	 * @param WP_Error $error Final authentication error.
	 */
	public function record_auth_failure( $username, $error ) {
		if ( $this->failure_recorded || ! $this->protected_post( 'login' ) || true !== $this->login_result || ! is_wp_error( $error ) ) {
			return;
		}
		$codes = $error->get_error_codes();
		if ( array_intersect( $codes, array( 'password_expired', 'temporary_login_expired', 'ip_address_blocked' ) ) || ! array_intersect( $codes, array( 'invalid_username', 'invalid_email', 'incorrect_password', 'wpmastertoolkit_two_factor_authentication' ) ) ) {
			return;
		}
		$this->failure_recorded = true;
		$result                 = $this->record_risk_event( 'auth' );
		if ( ! is_wp_error( $result ) ) {
			$this->assess_risk( 'login' );
		}
	}

	/**
	 * Create a server-backed addition challenge.
	 *
	 * @param string $purpose Form action.
	 * @param bool   $force Require a question after a rejected attempt.
	 * @return array|WP_Error
	 */
	private function issue( $purpose, $force = false ) {
		if ( ! $this->enabled( $purpose ) ) {
			return $this->error( 'context' );
		}
		if ( ! $this->bind_browser() ) {
			return $this->error( 'cookie' );
		}
		$allowed = $this->throttle( 'issue', 60 );
		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}
		$decision = $this->assess_risk( $purpose );
		if ( is_wp_error( $decision ) ) {
			return $decision;
		}
		$challenge = $force || 'challenge' === $decision;
		$token     = wp_generate_password( 48, false, false );
		$left      = wp_rand( 1, 9 );
		$right     = wp_rand( 1, 9 );
		$stored    = $this->store_state(
			array(
				'record_key' => $this->digest( $token ),
				'kind'       => $challenge ? 'challenge' : 'flow',
				'binding'    => $this->browser,
				'purpose'    => $purpose,
				'verifier'   => $challenge ? $this->digest( $token . '|' . ( $left + $right ) ) : '',
				'expires'    => time() + 10 * MINUTE_IN_SECONDS,
			)
		);
		if ( is_wp_error( $stored ) ) {
			return $stored;
		}
		return array(
			'state'    => $challenge ? 'challenge' : 'allow',
			'id'       => $token,
			'question' => $challenge ? sprintf( /* translators: 1: first number, 2: second number. */ __( 'What is %1$d + %2$d?', 'wpmastertoolkit' ), $left, $right ) : '',
		);
	}

	/**
	 * Validate and atomically consume a challenge, even on a wrong answer.
	 *
	 * @param string $purpose Native form action.
	 * @return true|WP_Error
	 */
	private function solve( $purpose ) {
		global $wpdb;
		$this->replacement_allowed = false;
		$allowed                   = $this->throttle( 'validate', 100 );
		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}
		$activity = $this->record_risk_event( $purpose );
		if ( is_wp_error( $activity ) ) {
			return $activity;
		}
		$allowed = $this->failure_cooldown();
		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}
		if ( ! $this->bind_browser() ) {
			return $this->error( 'cookie' );
		}
		$token  = $this->post( 'wpmtk_captcha_id' );
		$answer = $this->post( 'wpmtk_captcha_answer' );
		if ( ! preg_match( '/^[a-zA-Z0-9]{48}$/D', $token ) ) {
			return $this->failed_solve();
		}
		$key = $this->digest( $token );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Validate against current single-use state.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE record_key = %s AND kind IN ('challenge','flow') AND binding = %s AND purpose = %s AND expires > %d", self::table(), $key, $this->browser, $purpose, time() ), ARRAY_A );
		if ( ! $row ) {
			return $wpdb->last_error ? $this->error( 'storage' ) : $this->failed_solve();
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Only the request deleting this row may use its answer.
		$claimed = $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE record_key = %s AND expires > %d', self::table(), $key, time() ) );
		if ( false === $claimed ) {
			return $this->error( 'storage' );
		}
		if ( 1 !== $claimed ) {
			return $this->failed_solve();
		}
		if ( 'flow' === $row['kind'] ) {
			$decision = $this->assess_risk( $purpose, $this->fast_submission( $row ) );
			if ( is_wp_error( $decision ) ) {
				return $decision;
			}
			if ( 'challenge' === $decision ) {
				$this->replacement_allowed = true;
				return $this->error( 'required' );
			}
			$this->assurance = 'low-risk';
		} else {
			if ( ! preg_match( '/^[0-9]{1,2}$/D', $answer ) || ! hash_equals( $row['verifier'], $this->digest( $token . '|' . (int) $answer ) ) ) {
				$this->replacement_allowed = true;
				return $this->failed_solve();
			}
			$this->assurance = 'solved';
		}
		return true;
	}

	/**
	 * Timing is optional and only strengthens an existing server-side suspicion.
	 *
	 * @param array $row Consumed form state.
	 * @return bool
	 */
	private function fast_submission( $row ) {
		$settings = $this->get_settings();
		$elapsed  = $this->post( 'wpmtk_captcha_elapsed_ms' );
		$age      = time() - ( (int) $row['expires'] - 10 * MINUTE_IN_SECONDS );
		return '1' === $settings['timing_enabled'] && preg_match( '/^[0-9]{1,7}$/D', $elapsed ) && (int) $elapsed < $settings['fast_submission_ms'] && $age >= 0 && $age <= (int) ceil( $settings['fast_submission_ms'] / 1000 ) + 5;
	}

	/**
	 * Track rejected challenges separately from credentials.
	 *
	 * @return WP_Error
	 */
	private function failed_solve() {
		$result = $this->throttle( 'failure', 20 );
		return is_wp_error( $result ) ? $result : $this->error();
	}

	/**
	 * Enforce a previously exhausted rejection budget before further validation.
	 *
	 * @return true|WP_Error
	 */
	private function failure_cooldown() {
		global $wpdb;
		$ip     = isset( $_SERVER['REMOTE_ADDR'] ) && is_string( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$window = (int) floor( time() / ( 5 * MINUTE_IN_SECONDS ) );
		$key    = $this->digest( 'rate|failure|' . $this->digest( $ip ) . '|' . $window );
		$limit  = max( 1, min( 1000, (int) apply_filters( 'wpmastertoolkit_captcha_failure_limit', 20 ) ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read the current fixed-window rejection budget.
		$count = $wpdb->get_var( $wpdb->prepare( 'SELECT counter FROM %i WHERE record_key = %s AND expires > %d', self::table(), $key, time() ) );
		if ( $wpdb->last_error ) {
			return $this->error( 'storage' );
		}
		return null !== $count && (int) $count >= $limit ? $this->error( 'limit' ) : true;
	}

	/**
	 * Determine whether this is a protected native POST.
	 *
	 * @param string $purpose Form context.
	 * @return bool
	 */
	private function protected_post( $purpose ) {
		return $this->context === $purpose && $this->enabled( $purpose ) && isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'];
	}

	/**
	 * Prepare authoritative result before core credential callbacks.
	 *
	 * @param mixed $user Authentication result.
	 * @return mixed
	 */
	public function prepare_login( $user ) {
		if ( $this->protected_post( 'login' ) && null === $this->login_result ) {
			$this->login_result = $this->post( 'wpmtk_captcha_proof' ) ? $this->use_proof( 'final', $this->post( 'log' ) ) : $this->solve( 'login' );
		}
		return $user;
	}

	/**
	 * Reject before another module can consume a successful two-factor code.
	 *
	 * @param mixed $user Authentication result.
	 * @return mixed
	 */
	public function guard_user( $user ) {
		return $this->protected_post( 'login' ) && is_wp_error( $this->login_result ) ? $this->login_result : $user;
	}

	/**
	 * Reapply failure after core callbacks; retain existing IP lockouts.
	 *
	 * @param mixed $user Authentication result.
	 * @return mixed
	 */
	public function guard_login( $user ) {
		if ( $this->protected_post( 'login' ) && is_wp_error( $this->login_result ) && ! ( is_wp_error( $user ) && in_array( 'ip_address_blocked', $user->get_error_codes(), true ) ) ) {
			$this->display_error = $this->login_result;
			return $this->login_result;
		}
		return $user;
	}

	/**
	 * Preserve recovery errors and block reset key/mail side effects.
	 *
	 * @param WP_Error $errors Current errors.
	 * @return WP_Error
	 */
	public function guard_recovery( $errors ) {
		return $this->guard_form( $errors, 'lostpassword' );
	}

	/**
	 * Preserve strong-password and registration errors.
	 *
	 * @param WP_Error $errors Current errors.
	 * @return WP_Error
	 */
	public function guard_registration( $errors ) {
		return $this->guard_form( $errors, 'register' );
	}

	/**
	 * Add errors without replacing other form validation.
	 *
	 * @param WP_Error $errors Existing errors.
	 * @param string   $purpose Form context.
	 * @return WP_Error
	 */
	private function guard_form( $errors, $purpose ) {
		if ( $this->protected_post( $purpose ) ) {
			$result = $this->solve( $purpose );
			if ( is_wp_error( $result ) ) {
				$errors->add( $result->get_error_code(), $result->get_error_message() );
				$this->display_error = $result;
			}
		}
		return $errors;
	}

	/**
	 * Keep safe CAPTCHA guidance visible while leaving credential masking intact.
	 *
	 * @param string $message Existing presentation.
	 * @return string
	 */
	public function safe_login_error( $message ) {
		return $this->display_error ? esc_html( $this->display_error->get_error_message() ) : $message;
	}

	/**
	 * Exclude only CAPTCHA rejection from credential counters.
	 *
	 * @param bool     $count Existing decision.
	 * @param WP_Error $error Authentication error.
	 * @return bool
	 */
	public function count_failed_login( $count, $error ) {
		if ( is_wp_error( $error ) ) {
			foreach ( $error->get_error_codes() as $code ) {
				if ( 0 === strpos( $code, 'wpmastertoolkit_captcha_' ) ) {
					return false;
				}
			}
		}
		return $count;
	}

	/**
	 * Render only in a real protected controller, never customizer samples.
	 */
	public function render_challenge() {
		if ( ! $this->enabled( $this->context ) ) {
			return;
		}
		$challenge = $this->issue( $this->context, null !== $this->display_error );
		if ( is_wp_error( $challenge ) ) {
			echo '<p role="alert">' . esc_html( $challenge->get_error_message() ) . '</p>';
			return;
		}
		$id = wp_unique_id( 'wpmtk-captcha-' );
		?>
		<div class="wpmtk-captcha" data-purpose="<?php echo esc_attr( $this->context ); ?>" data-state="<?php echo esc_attr( $challenge['state'] ); ?>">
			<div class="wpmtk-captcha__question" <?php echo 'allow' === $challenge['state'] ? 'hidden' : ''; ?>>
			<label class="wpmtk-captcha__label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $challenge['question'] ); ?></label>
			<input id="<?php echo esc_attr( $id ); ?>" class="input wpmtk_captcha_answer" type="text" name="wpmtk_captcha_answer" inputmode="numeric" pattern="[0-9]{1,2}" maxlength="2" <?php echo 'challenge' === $challenge['state'] ? 'required' : 'disabled'; ?> autocomplete="off" aria-describedby="<?php echo esc_attr( $id . '-status' ); ?>" style="margin-bottom: 0;">
			<noscript><a href="<?php echo esc_url( add_query_arg( 'action', $this->context, wp_login_url() ) ); ?>"><?php esc_html_e( 'New question', 'wpmastertoolkit' ); ?></a></noscript>
			</div>
			<button type="button" class="wpmtk-captcha__refresh" hidden title="<?php esc_attr_e( 'New CAPTCHA question', 'wpmastertoolkit' ); ?>"><span class="dashicons dashicons-update" aria-hidden="true"></span> <?php esc_html_e( 'New question', 'wpmastertoolkit' ); ?></button>
			<input type="hidden" name="wpmtk_captcha_id" value="<?php echo esc_attr( $challenge['id'] ); ?>">
			<input type="hidden" name="wpmtk_captcha_proof" value="">
			<input type="hidden" name="wpmtk_captcha_elapsed_ms" value="">
			<p id="<?php echo esc_attr( $id . '-status' ); ?>" class="wpmtk-captcha__status" role="status" aria-live="polite"></p>
		</div>
		<?php
	}

	/**
	 * Enqueue self-contained public helpers before two-factor assets.
	 */
	public function enqueue_public() {
		if ( ! $this->enabled( $this->context ) ) {
			return;
		}
		$asset = include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/captcha-login-form-public.asset.php';
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( 'wpmastertoolkit-captcha-public', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/captcha-login-form-public.css', array(), $asset['version'] );
		wp_enqueue_script( 'wpmastertoolkit-captcha-public', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/captcha-login-form-public.js', $asset['dependencies'], $asset['version'], true );
		wp_localize_script(
			'wpmastertoolkit-captcha-public',
			'wpmastertoolkitCaptchaConfig',
			array(
				'url'     => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wpmastertoolkit-captcha' ),
				'timing'  => 'adaptive' === $this->get_settings()['mode'] && '1' === $this->get_settings()['timing_enabled'],
				'error'   => $this->error()->get_error_message(),
				'network' => __( 'Unable to verify CAPTCHA. Please refresh the question or reload the page.', 'wpmastertoolkit' ),
			)
		);
	}

	/**
	 * Declare ordering without referencing Pro code from the free module.
	 *
	 * @param array $dependencies Existing dependencies.
	 * @return array
	 */
	public function tfa_dependencies( $dependencies ) {
		if ( 'login' === $this->context && $this->enabled( 'login' ) ) {
			$dependencies[] = 'wpmastertoolkit-captcha-public';
		}
		return $dependencies;
	}

	/**
	 * Verify an AJAX action's supplementary nonce and form scope.
	 *
	 * @param string $expected Optional required form purpose.
	 * @return string
	 */
	private function ajax_context( $expected = '' ) {
		nocache_headers();
		$purpose = $this->post( 'purpose' );
		if ( ! wp_verify_nonce( $this->post( 'nonce' ), 'wpmastertoolkit-captcha' ) || ! $this->enabled( $purpose ) || ( $expected && $expected !== $purpose ) ) {
			$this->json_error( $this->error( 'context' ) );
		}
		if ( ! $this->bind_browser() ) {
			$this->json_error( $this->error( 'cookie' ) );
		}
		return $purpose;
	}

	/**
	 * Return safe machine-readable errors.
	 *
	 * @param WP_Error $error Safe error.
	 * @param array    $form Optional replacement created by the validated caller.
	 */
	private function json_error( $error, $form = array() ) {
		$data = array(
			'code'    => $error->get_error_code(),
			'message' => $error->get_error_message(),
		);
		if ( $form ) {
			$data['form'] = $form;
		}
		wp_send_json_error(
			$data,
			400
		);
	}

	/**
	 * Refresh only the challenge/proof belonging to the current browser.
	 */
	public function ajax_refresh() {
		$purpose = $this->ajax_context();
		$this->delete_posted_state();
		$challenge = $this->issue( $purpose, '1' === $this->post( 'force_challenge' ) );
		if ( is_wp_error( $challenge ) ) {
			$this->json_error( $challenge );
		}
		wp_send_json_success( $challenge );
	}

	/**
	 * Create a bounded continuation for the existing two-factor submit flow.
	 */
	public function ajax_prepare() {
		$this->ajax_context( 'login' );
		$result = $this->solve( 'login' );
		if ( is_wp_error( $result ) ) {
			$form = array();
			if ( $this->replacement_allowed && in_array( $result->get_error_code(), array( 'wpmastertoolkit_captcha_invalid', 'wpmastertoolkit_captcha_required' ), true ) ) {
				$form = $this->issue( 'login', true );
				if ( is_wp_error( $form ) ) {
					$this->json_error( $form );
				}
			}
			$this->json_error( $result, $form );
		}
		$token  = wp_generate_password( 48, false, false );
		$stored = $this->store_state(
			array(
				'record_key' => $this->digest( $token ),
				'kind'       => 'proof',
				'binding'    => $this->browser,
				'purpose'    => 'login',
				'identifier' => $this->identifier( $this->post( 'log' ) ),
				'verifier'   => $this->assurance,
				'expires'    => time() + 15 * MINUTE_IN_SECONDS,
			)
		);
		if ( is_wp_error( $stored ) ) {
			$this->json_error( $stored );
		}
		wp_send_json_success(
			array(
				'proof'   => $token,
				'expires' => time() + 15 * MINUTE_IN_SECONDS,
			)
		);
	}

	/**
	 * Revoke a continuation when the account or modal flow changes.
	 */
	public function ajax_cancel() {
		$this->ajax_context();
		$this->delete_posted_state();
		wp_send_json_success();
	}

	/**
	 * Delete only state whose opaque token and browser binding match.
	 */
	private function delete_posted_state() {
		global $wpdb;
		$allowed = $this->throttle( 'cancel', 100 );
		if ( is_wp_error( $allowed ) ) {
			$this->json_error( $allowed );
		}
		foreach ( array( 'wpmtk_captcha_id', 'wpmtk_captcha_proof' ) as $field ) {
			$token = $this->post( $field );
			if ( preg_match( '/^[a-zA-Z0-9]{48}$/D', $token ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Revoke only browser-owned ephemeral state.
				$deleted = $wpdb->delete(
					self::table(),
					array(
						'record_key' => $this->digest( $token ),
						'binding'    => $this->browser,
					)
				);
				if ( false === $deleted ) {
					$this->json_error( $this->error( 'storage' ) );
				}
			}
		}
	}

	/**
	 * Normalize entered identifiers identically for AJAX and final native POST.
	 *
	 * @param string $login Entered identifier, not resolved account information.
	 * @return string
	 */
	private function identifier( $login ) {
		return $this->digest( is_email( $login ) ? sanitize_email( $login ) : sanitize_user( $login ) );
	}

	/**
	 * Atomically reserve a code-send permission or the one final login attempt.
	 *
	 * @param string $operation Send or final.
	 * @param string $login Entered identifier.
	 * @return true|WP_Error
	 */
	private function use_proof( $operation, $login ) {
		global $wpdb;
		$allowed = $this->throttle( 'validate', 100 );
		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}
		$allowed = $this->failure_cooldown();
		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}
		$token = $this->post( 'wpmtk_captcha_proof' );
		if ( ! $this->bind_browser() || ! preg_match( '/^[a-zA-Z0-9]{48}$/D', $token ) ) {
			return $this->failed_solve();
		}
		$key = $this->digest( $token );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Assurance comes from the server-side continuation, never posted flags.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT verifier FROM %i WHERE record_key = %s AND kind = 'proof' AND binding = %s AND identifier = %s AND purpose = 'login' AND expires > %d AND consumed = 0", self::table(), $key, $this->browser, $this->identifier( $login ), time() ), ARRAY_A );
		if ( ! $row || ! in_array( $row['verifier'], array( 'low-risk', 'solved' ), true ) ) {
			return $wpdb->last_error ? $this->error( 'storage' ) : $this->failed_solve();
		}
		if ( 'final' === $operation ) {
			$sql = "UPDATE %i SET consumed = 1 WHERE record_key = %s AND kind = 'proof' AND binding = %s AND identifier = %s AND purpose = 'login' AND expires > %d AND consumed = 0";
		} else {
			$sql = "UPDATE %i SET counter = counter + 1 WHERE record_key = %s AND kind = 'proof' AND binding = %s AND identifier = %s AND purpose = 'login' AND expires > %d AND consumed = 0 AND counter < 3";
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Both constant query templates use the identical placeholder contract.
		$claimed = $wpdb->query( $wpdb->prepare( $sql, self::table(), $this->digest( $token ), $this->browser, $this->identifier( $login ), time() ) );
		if ( 1 !== $claimed ) {
			return false === $claimed ? $this->error( 'storage' ) : $this->failed_solve();
		}
		if ( 'send' === $operation ) {
			$activity = $this->record_risk_event( 'send' );
			if ( is_wp_error( $activity ) ) {
				return $activity;
			}
		}
		$decision = $this->assess_risk( 'login' );
		if ( is_wp_error( $decision ) || ( 'low-risk' === $row['verifier'] && 'challenge' === $decision ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Revoke an escalated continuation before any downstream side effects.
			$revoked = $wpdb->delete(
				self::table(),
				array(
					'record_key' => $key,
					'binding'    => $this->browser,
				)
			);
			return false === $revoked ? $this->error( 'storage' ) : ( is_wp_error( $decision ) ? $decision : $this->error( 'required' ) );
		}
		$this->assurance = $row['verifier'];
		return true;
	}

	/**
	 * Guard the email side-effect endpoint, not just the browser UI.
	 *
	 * @param true|WP_Error $permission Prior decision.
	 * @param string        $login Entered identifier.
	 * @return true|WP_Error
	 */
	public function guard_code_send( $permission, $login ) {
		return ! $this->enabled( 'login' ) || is_wp_error( $permission ) ? $permission : $this->use_proof( 'send', $login );
	}

	/**
	 * Register the existing settings wrapper.
	 */
	public function add_submenu() {
		WPMastertoolkit_Settings::add_submenu_page( 'wp-mastertoolkit-settings', $this->header_title, $this->header_title, 'manage_options', 'wp-mastertoolkit-settings-captcha-login-form', array( $this, 'render_submenu' ) );
	}

	/**
	 * Render grouped settings using the shared module form shell.
	 */
	public function render_submenu() {
		$asset = include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/captcha-login-form.asset.php';
		wp_enqueue_style( 'wpmastertoolkit-captcha-settings', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/captcha-login-form.css', array(), $asset['version'] );
		wp_enqueue_script( 'wpmastertoolkit-captcha-settings', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/captcha-login-form.js', $asset['dependencies'], $asset['version'], true );
		include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
		settings_errors( $this->option_id );
		$settings = $this->get_settings();
		$pro      = wpmastertoolkit_is_pro();
		$adaptive = $pro && 'adaptive' === $settings['mode'];
		$bounds   = $this->setting_bounds();
		$sections = array(
			array(
				'title'  => __( 'Protection', 'wpmastertoolkit' ),
				'fields' => array(
					'mode'         => array(
						'label' => __( 'Protection mode', 'wpmastertoolkit' ),
						'type'  => 'radio',
						'help'  => __( 'Always require shows a question on every protected form. Adaptive, available with WPMasterToolKit Pro, only shows a question when recent activity looks suspicious. This fully local arithmetic check provides basic friction, not reliable human verification. Keep independent login-attempt limits enabled. Visitors sharing an IP address share request limits; use server or edge rate limiting for additional protection. Only native WordPress forms are covered. Behind a reverse proxy, configure the web server to provide the real client IP address.', 'wpmastertoolkit' ),
					),
					'login'        => array(
						'label' => __( 'Login form', 'wpmastertoolkit' ),
						'type'  => 'checkbox',
						'help'  => __( 'Protect the standard WordPress username and password login form.', 'wpmastertoolkit' ),
					),
					'lostpassword' => array(
						'label' => __( 'Lost-password form', 'wpmastertoolkit' ),
						'type'  => 'checkbox',
						'help'  => __( 'Protect requests to send a password-reset email.', 'wpmastertoolkit' ),
					),
					'register'     => array(
						'label' => __( 'Registration form', 'wpmastertoolkit' ),
						'type'  => 'checkbox',
						'help'  => __( 'Protect account creation through the standard WordPress registration form.', 'wpmastertoolkit' ),
					),
				),
			),
			array(
				'title'  => __( 'Time windows', 'wpmastertoolkit' ),
				'fields' => array(
					'observation_window_minutes' => array(
						'label' => __( 'Activity counting period', 'wpmastertoolkit' ),
						'unit'  => __( 'minutes', 'wpmastertoolkit' ),
						'help'  => __( 'Count failures and requests within fixed periods of this length. For example, with 5 minutes, each request limit below applies to one five-minute period. A longer period can cause more visitors to receive a question.', 'wpmastertoolkit' ),
					),
					'challenge_period_minutes'   => array(
						'label' => __( 'Require questions for', 'wpmastertoolkit' ),
						'unit'  => __( 'minutes', 'wpmastertoolkit' ),
						'help'  => __( 'Once suspicious activity is detected, require questions for this long. Retrying does not extend the current period. Solving a question authorizes only that form or two-factor flow; it does not remove the requirement for later attempts. Continued suspicious activity can start another period.', 'wpmastertoolkit' ),
					),
				),
			),
			array(
				'title'  => __( 'Failed logins', 'wpmastertoolkit' ),
				'fields' => array(
					'browser_auth_failures' => array(
						'label' => __( 'Failed logins from one browser', 'wpmastertoolkit' ),
						'unit'  => __( 'failures', 'wpmastertoolkit' ),
						'help'  => __( 'After this many failed logins in the activity counting period, ask for a question on the next attempt from that browser. The default of 3 allows three failures before the next attempt needs a question. Incorrect CAPTCHA answers are not counted here.', 'wpmastertoolkit' ),
					),
					'ip_auth_failures'      => array(
						'label' => __( 'Failed logins from one IP address', 'wpmastertoolkit' ),
						'unit'  => __( 'failures', 'wpmastertoolkit' ),
						'help'  => __( 'After this many failed logins from the same server-observed IP address, later attempts from that address need a question.', 'wpmastertoolkit' ),
					),
				),
			),
			array(
				'title'  => __( 'Request limits', 'wpmastertoolkit' ),
				'fields' => array(
					'login_attempts'        => array(
						'label' => __( 'Login submissions', 'wpmastertoolkit' ),
						'unit'  => __( 'submissions', 'wpmastertoolkit' ),
						'help'  => __( 'Require a question when this many login submissions are reached. With the default of 10, the tenth submission needs a question.', 'wpmastertoolkit' ),
					),
					'recovery_attempts'     => array(
						'label' => __( 'Password-reset email requests', 'wpmastertoolkit' ),
						'unit'  => __( 'requests', 'wpmastertoolkit' ),
						'help'  => __( 'Require a question when this many lost-password submissions are reached, before a reset email can be sent.', 'wpmastertoolkit' ),
					),
					'registration_attempts' => array(
						'label' => __( 'Registration submissions', 'wpmastertoolkit' ),
						'unit'  => __( 'submissions', 'wpmastertoolkit' ),
						'help'  => __( 'Require a question when this many registration submissions are reached, before an account can be created.', 'wpmastertoolkit' ),
					),
					'tfa_send_attempts'     => array(
						'label' => __( 'Two-factor code requests', 'wpmastertoolkit' ),
						'unit'  => __( 'requests', 'wpmastertoolkit' ),
						'help'  => __( 'Require a question when this many requests to generate a two-factor code are reached.', 'wpmastertoolkit' ),
					),
				),
			),
			array(
				'title'  => __( 'Form timing', 'wpmastertoolkit' ),
				'fields' => array(
					'timing_enabled'     => array(
						'label' => __( 'Use form timing', 'wpmastertoolkit' ),
						'type'  => 'checkbox',
						'help'  => __( 'Send only the elapsed time before form submission, not keystrokes, passwords, or mouse movements. Missing timing data or JavaScript does not cause a penalty. Disable this if you prefer decisions based only on server activity.', 'wpmastertoolkit' ),
					),
					'fast_submission_ms' => array(
						'label' => __( 'Consider a submission fast below', 'wpmastertoolkit' ),
						'unit'  => __( 'milliseconds', 'wpmastertoolkit' ),
						'help'  => __( '1,000 milliseconds equals one second; the default of 1,500 is 1.5 seconds. A faster submission can require a question only when at least half a relevant server activity threshold has already been reached, with a minimum of two events. Autofill or a password manager alone does not trigger a question. Only applies when Use form timing is enabled.', 'wpmastertoolkit' ),
					),
				),
			),
		);
		?>
		<div class="wpmtk-captcha-settings">
			<?php foreach ( $sections as $section_index => $section ) : ?>
				<?php $adaptive_section = 0 !== $section_index; ?>
				<section class="wp-mastertoolkit__section" aria-labelledby="<?php echo esc_attr( 'wpmtk-captcha-section-' . $section_index ); ?>"
				<?php
				if ( $adaptive_section ) :
					?>
					data-wpmtk-captcha-adaptive <?php echo ! $adaptive ? 'hidden' : ''; ?><?php endif; ?>>
					<h2 id="<?php echo esc_attr( 'wpmtk-captcha-section-' . $section_index ); ?>"><?php echo esc_html( $section['title'] ); ?></h2>
					<div class="wp-mastertoolkit__section__body">
						<fieldset class="wpmtk-captcha-settings__fields" aria-labelledby="<?php echo esc_attr( 'wpmtk-captcha-section-' . $section_index ); ?>" <?php disabled( $adaptive_section && ! $adaptive ); ?>>
						<?php foreach ( $section['fields'] as $key => $field ) : ?>
							<?php
							$field_id = 'wpmtk-captcha-' . $key;
							$type     = $field['type'] ?? 'number';
							?>
							<?php if ( 'radio' === $type ) : ?>
								<fieldset id="<?php echo esc_attr( $field_id ); ?>" class="wp-mastertoolkit__section__body__item" aria-describedby="<?php echo esc_attr( $field_id . '-help' ); ?>">
									<legend class="wp-mastertoolkit__section__body__item__title"><?php echo esc_html( $field['label'] ); ?></legend>
									<div class="wp-mastertoolkit__section__body__item__content wpmtk-captcha-settings__modes">
										<label for="wpmtk-captcha-mode-always">
											<input id="wpmtk-captcha-mode-always" type="radio" name="<?php echo esc_attr( $this->option_id . '[mode]' ); ?>" value="always" <?php checked( $settings['mode'], 'always' ); ?>>
											<span><?php esc_html_e( 'Always require', 'wpmastertoolkit' ); ?></span>
										</label>
										<label for="wpmtk-captcha-mode-adaptive">
											<input id="wpmtk-captcha-mode-adaptive" type="radio" name="<?php echo esc_attr( $this->option_id . '[mode]' ); ?>" value="adaptive" <?php checked( $settings['mode'], 'adaptive' ); ?> <?php disabled( ! $pro ); ?>>
											<span><?php esc_html_e( 'Adaptive', 'wpmastertoolkit' ); ?></span>
											<?php
											if ( ! $pro ) :
												?>
												<span class="wp-mastertoolkit__section__body__item__title__tag pro"><?php esc_html_e( 'PRO', 'wpmastertoolkit' ); ?></span><?php endif; ?>
										</label>
									</div>
									<p id="<?php echo esc_attr( $field_id . '-help' ); ?>" class="description"><?php echo esc_html( $field['help'] ); ?></p>
								</fieldset>
								<?php continue; ?>
							<?php endif; ?>
							<div class="wp-mastertoolkit__section__body__item">
								<div class="wp-mastertoolkit__section__body__item__title"><label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $field['label'] ); ?></label></div>
								<div class="wp-mastertoolkit__section__body__item__content">
									<?php if ( 'checkbox' === $type ) : ?>
										<input type="hidden" name="<?php echo esc_attr( $this->option_id . '[' . $key . ']' ); ?>" value="0">
										<div class="wp-mastertoolkit__checkbox">
											<label class="wp-mastertoolkit__checkbox__label">
												<input id="<?php echo esc_attr( $field_id ); ?>" type="checkbox" name="<?php echo esc_attr( $this->option_id . '[' . $key . ']' ); ?>" value="1" <?php checked( $settings[ $key ], '1' ); ?> aria-describedby="<?php echo esc_attr( $field_id . '-help' ); ?>">
												<span class="mark" aria-hidden="true"></span>
												<span class="wp-mastertoolkit__checkbox__label__text"><?php esc_html_e( 'Enable', 'wpmastertoolkit' ); ?></span>
											</label>
										</div>
									<?php else : ?>
										<div class="wp-mastertoolkit__input-text flex">
											<input id="<?php echo esc_attr( $field_id ); ?>" type="number" name="<?php echo esc_attr( $this->option_id . '[' . $key . ']' ); ?>" min="<?php echo esc_attr( $bounds[ $key ][0] ); ?>" max="<?php echo esc_attr( $bounds[ $key ][1] ); ?>" value="<?php echo esc_attr( $settings[ $key ] ); ?>" aria-describedby="<?php echo esc_attr( $field_id . '-help' ); ?>" required>
											<span><?php echo esc_html( $field['unit'] ); ?></span>
										</div>
									<?php endif; ?>
									<p id="<?php echo esc_attr( $field_id . '-help' ); ?>" class="description"><?php echo esc_html( $field['help'] ); ?></p>
								</div>
							</div>
						<?php endforeach; ?>
						</fieldset>
					</div>
				</section>
			<?php endforeach; ?>
		</div>
		<?php
		include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/footer.php';
	}
}
