<?php
/**
 * Module Name: Admin Boost
 *
 * @package WPMastertoolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/helpers/core/admin-boost/class-admin-boost-policy.php';

/**
 * Session-scoped administration HTTP controls.
 */
class WPMastertoolkit_Admin_Boost {

	const OPTION = 'wpmastertoolkit_settings_admin_boost';
	const SLUG   = 'wp-mastertoolkit-settings-admin-boost';
	const NONCE  = 'wpmtk_admin_boost';

	/**
	 * Shared submenu title.
	 *
	 * @var string
	 */
	private $header_title;

	/**
	 * Session commands and settings use independent forms.
	 *
	 * @var bool
	 */
	private $disable_form = true;

	/**
	 * Saving belongs to the rules form rather than the shared header.
	 *
	 * @var bool
	 */
	private $disable_save_form = true;

	/**
	 * Validated site settings.
	 *
	 * @var array|null
	 */
	private $settings;
	/**
	 * Number of blocked calls in this PHP request.
	 *
	 * @var int
	 */
	private $blocked = 0;
	/**
	 * Whether interception was explicitly stopped.
	 *
	 * @var bool
	 */
	private $suspended = false;
	/**
	 * Debug collector.
	 *
	 * @var WPMastertoolkit_Admin_Boost_Debug|null
	 */
	private $debug;

	/**
	 * Current temporary boost's request-local history buffer.
	 *
	 * @var WPMastertoolkit_Admin_Boost_History|null
	 */
	private $history;

	/** Register only the active module's hooks. */
	public function __construct() {
		add_filter( 'pre_http_request', array( $this, 'intercept' ), PHP_INT_MAX, 3 );
		add_filter( 'http_request_args', array( $this, 'trace_args' ), PHP_INT_MAX );
		add_action( 'http_api_debug', array( $this, 'http_result' ), PHP_INT_MAX, 4 );
		add_filter( 'http_response', array( $this, 'final_response' ), PHP_INT_MAX, 2 );
		add_action( 'shutdown', array( $this, 'flush_debug' ) );
		add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar' ), 100 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_footer', array( $this, 'render_dialog' ) );
		add_action( 'admin_post_wpmtk_admin_boost', array( $this, 'handle_action' ) );
		add_action( 'wp_ajax_wpmtk_admin_boost', array( $this, 'handle_action' ) );
		add_action( 'update_option_' . WPMASTERTOOLKIT_PLUGIN_SETTINGS, array( $this, 'module_updated' ), 10, 2 );
		add_action( 'deactivate_' . WPMASTERTOOLKIT_BASENAME, array( $this, 'suspend' ) );
	}

	/**
	 * Whether this installation includes the Pro module support.
	 *
	 * @return bool
	 */
	public function is_pro() {
		return wpmastertoolkit_is_pro() && is_file( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/helpers/pro/admin-boost/class-admin-boost-debug.php' );
	}

	/**
	 * Read settings once per PHP request.
	 *
	 * @return array
	 */
	public function get_settings() {
		if ( null === $this->settings ) {
			$stored         = get_option( self::OPTION, array() );
			$this->settings = array_merge(
				array(
					'pages' => array(),
					'rules' => array(),
				),
				is_array( $stored ) ? $stored : array()
			);
			$validated      = WPMastertoolkit_Admin_Boost_Policy::validate_settings( $this->settings, wp_parse_url( admin_url(), PHP_URL_PATH ) );
			$this->settings = is_wp_error( $validated ) ? array(
				'pages' => array(),
				'rules' => array(),
			) : $validated;
		}
		return $this->settings;
	}

	/**
	 * Access the current WordPress login session only.
	 *
	 * @param array|null $state New module state, or null for a read.
	 * @return array
	 */
	private function session( $state = null ) {
		if ( ! function_exists( 'wp_get_session_token' ) || ! current_user_can( 'manage_options' ) ) {
			return array();
		}
		$token   = wp_get_session_token();
		$manager = WP_Session_Tokens::get_instance( get_current_user_id() );
		$login   = $token ? $manager->get( $token ) : null;
		if ( ! is_array( $login ) ) {
			return array();
		}
		$site       = get_current_blog_id();
		$generation = get_option( self::OPTION . '_generation', false );
		if ( false === $generation ) {
			add_option( self::OPTION . '_generation', wp_generate_uuid4(), '', false );
			$generation = get_option( self::OPTION . '_generation' );
		}
		$generation = (string) $generation . ':' . (string) get_site_option( self::OPTION . '_network_generation', '1' );
		if ( null !== $state ) {
			$state['generation']                 = $generation;
			$login['wpmtk_admin_boost'][ $site ] = $state;
			$manager->update( $token, $login );
		}
		$stored = $login['wpmtk_admin_boost'][ $site ] ?? array();
		return is_array( $stored ) && ( $stored['generation'] ?? '' ) === $generation ? $stored : array();
	}

	/**
	 * Server-authoritative effective session state.
	 *
	 * @return array
	 */
	public function state() {
		$state   = $this->session();
		$started = (int) ( $state['started'] ?? 0 );
		$until   = (int) ( $state['until'] ?? 0 );
		if ( ! $this->is_pro() ) {
			$until = $started ? min( $until ? $until : PHP_INT_MAX, $started + 120 ) : 0;
		}
		return array(
			'active'      => ! $this->suspended && $started > 0 && ( 0 === $until || $until > time() ),
			'until'       => $until,
			'debug_until' => $this->is_pro() ? (int) ( $state['debug_until'] ?? 0 ) : 0,
			'now'         => time(),
			'blocked'     => $this->blocked,
		);
	}

	/**
	 * Build a reliable, read-only administration context.
	 *
	 * @return array|false
	 */
	private function context() {
		if ( $this->suspended || ! is_admin() || ! did_action( 'current_screen' ) || ! is_user_logged_in() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) || is_network_admin() || is_user_admin() || ( is_multisite() && ms_is_switched() ) ) {
			return false;
		}
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( 'GET' !== $method ) {
			return false;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! empty( $screen->action ) ) {
			return false;
		}
		$excluded = array( 'admin-post.php', 'admin-ajax.php', 'update.php', 'update-core.php', 'plugin-install.php', 'theme-install.php', 'plugins-network.php', 'options.php', 'customize.php', 'async-upload.php', 'import.php', 'export.php', 'plugin-editor.php', 'theme-editor.php', 'authorize-application.php', 'site-health.php', 'erase-personal-data.php', 'export-personal-data.php' );
		$pagenow  = $GLOBALS['pagenow'] ?? '';
		if ( in_array( $pagenow, $excluded, true ) || in_array( $screen->base, array( 'update', 'update-core', 'plugin-install', 'theme-install', 'customize', 'site-health' ), true ) ) {
			return false;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only eligibility; never execute the requested action.
		$query = wp_unslash( $_GET );
		foreach ( array( 'action', 'action2', 'do', 'task' ) as $key ) {
			if ( isset( $query[ $key ] ) && ! in_array( $query[ $key ], array( '', '-1' ), true ) ) {
				return false;
			}
		}
		if ( ! empty( $query['_wpnonce'] ) || ! empty( $query['_ajax_nonce'] ) ) {
			return false;
		}
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$base = wp_parse_url( admin_url(), PHP_URL_PATH );
		$path = WPMastertoolkit_Admin_Boost_Policy::page_path( $uri, $base );
		if ( '/' . $pagenow !== $path ) {
			return false;
		}
		foreach ( $query as $key => $value ) {
			if ( ! is_string( $value ) || strlen( $value ) > 2048 ) {
				unset( $query[ $key ] );
			}
		}
		return array(
			'path'   => $path,
			'query'  => $query,
			'screen' => $screen->id,
		);
	}

	/**
	 * Observe or intercept an HTTP API call without overriding earlier filters.
	 *
	 * @param mixed  $pre Existing short circuit.
	 * @param array  $args Request arguments.
	 * @param string $url Destination.
	 * @return mixed
	 */
	public function intercept( $pre, $args, $url ) {
		$page = $this->context();
		if ( ! $page ) {
			return $pre;
		}
		$decision = array(
			'decision' => 'allowed',
			'reason'   => 'internal',
			'rule'     => '',
		);
		if ( false !== $pre ) {
			$decision = array(
				'decision' => 'handled',
				'reason'   => 'previous_filter',
				'rule'     => '',
			);
		} elseif ( ! WPMastertoolkit_Admin_Boost_Policy::internal( $url, array( home_url(), site_url() ) ) ) {
			$state    = $this->state();
			$decision = WPMastertoolkit_Admin_Boost_Policy::decide( $url, $args['method'] ?? 'GET', $page, $this->get_settings(), $state['active'], $this->is_pro() );
		}
		$result = $pre;
		if ( 'blocked' === $decision['decision'] ) {
			++$this->blocked;
			$result = new WP_Error( 'wpmtk_admin_boost_blocked', __( 'Request blocked by WPMasterToolKit Admin Boost.', 'wpmastertoolkit' ) );
			if ( $this->is_pro() && current_user_can( 'manage_options' ) && $this->state()['active'] ) {
				$session = $this->session();
				if ( ! empty( $session['history_boost'] ) && empty( $session['history_failed'] ) ) {
					if ( ! $this->history ) {
						require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/helpers/pro/admin-boost/class-admin-boost-history.php';
						$this->history = new WPMastertoolkit_Admin_Boost_History( $this->journal_key(), $session['history_boost'] );
					}
					$this->history->observe( $url, $args, $page, $decision );
				}
			}
		}
		$debug = $this->collector();
		if ( $debug ) {
			$debug->observe( $url, $args, $page, $decision, $result );
		}
		return $result;
	}

	/**
	 * Add a request-local trace identifier only during an eligible debug session.
	 *
	 * @param array $args HTTP arguments.
	 * @return array
	 */
	public function trace_args( $args ) {
		if ( $this->context() && $this->collector() ) {
			$args['_wpmtk_admin_boost_trace'] = wp_generate_uuid4();
		}
		return $args;
	}

	/**
	 * Collect the final HTTP API outcome where WordPress exposes it.
	 *
	 * @param mixed  $response HTTP response.
	 * @param string $context Debug context.
	 * @param string $transport Transport class.
	 * @param array  $args Request arguments.
	 */
	public function http_result( $response, $context, $transport, $args ) {
		if ( $this->debug && ! $this->suspended && 'response' === $context ) {
			$this->debug->result( $response, $args );
		}
	}

	/**
	 * Observe the late filtered response without changing it.
	 *
	 * @param mixed $response Filtered response.
	 * @param array $args Original request arguments.
	 * @return mixed
	 */
	public function final_response( $response, $args ) {
		if ( $this->debug && ! $this->suspended ) {
			$this->debug->result( $response, $args );
		}
		return $response;
	}

	/**
	 * Lazily create Pro observation support.
	 *
	 * @return WPMastertoolkit_Admin_Boost_Debug|null
	 */
	private function collector() {
		$state = $this->state();
		if ( $this->suspended || $state['debug_until'] <= time() || ! current_user_can( 'manage_options' ) ) {
			return null;
		}
		if ( ! $this->debug ) {
			require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/helpers/pro/admin-boost/class-admin-boost-debug.php';
			$this->debug = new WPMastertoolkit_Admin_Boost_Debug( $this->journal_key() );
		}
		return $this->debug;
	}

	/**
	 * Non-reversible identifier; raw session tokens never enter the journal.
	 *
	 * @return string
	 */
	private function journal_key() {
		return hash_hmac( 'sha256', get_current_blog_id() . ':' . get_current_user_id() . ':' . wp_get_session_token(), wp_salt( 'auth' ) );
	}

	/** Flush once per page, not once per HTTP call. */
	public function flush_debug() {
		if ( $this->history && ! $this->suspended && $this->is_pro() ) {
			$this->history->flush();
		}
		if ( $this->debug && ! $this->suspended && $this->is_pro() ) {
			$this->debug->flush();
		}
	}

	/**
	 * Read only the current login session's latest temporary boost.
	 *
	 * @return array
	 */
	private function history_snapshot() {
		$session = $this->session();
		if ( ! empty( $session['history_expires'] ) && $session['history_expires'] <= time() ) {
			return array(
				'available' => true,
				'total'     => 0,
				'entries'   => array(),
				'boost'     => $session['history_boost'] ?? '',
				'expired'   => true,
			);
		}
		require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/helpers/pro/admin-boost/class-admin-boost-history.php';
		$history  = new WPMastertoolkit_Admin_Boost_History( $this->journal_key(), $session['history_boost'] ?? '' );
		$snapshot = $history->snapshot();
		if ( ! empty( $session['history_failed'] ) ) {
			$snapshot['available'] = false;
		}
		return $snapshot;
	}

	/**
	 * Invalidate session state when the module is disabled.
	 *
	 * @param array $old Previous modules.
	 * @param array $updated Updated modules.
	 */
	public function module_updated( $old, $updated ) {
		if ( ! empty( $old[ __CLASS__ ] ) && empty( $updated[ __CLASS__ ] ) ) {
			$this->suspend();
		}
	}

	/**
	 * Immediately detach interception and invalidate old boosts.
	 *
	 * @param bool $network_wide Whether the entire network is deactivated.
	 */
	public function suspend( $network_wide = false ) {
		$this->suspended = true;
		remove_filter( 'pre_http_request', array( $this, 'intercept' ), PHP_INT_MAX );
		remove_filter( 'http_request_args', array( $this, 'trace_args' ), PHP_INT_MAX );
		remove_action( 'http_api_debug', array( $this, 'http_result' ), PHP_INT_MAX );
		remove_filter( 'http_response', array( $this, 'final_response' ), PHP_INT_MAX );
		update_option( self::OPTION . '_generation', wp_generate_uuid4(), false );
		if ( $network_wide ) {
			update_site_option( self::OPTION . '_network_generation', wp_generate_uuid4() );
		}
	}

	/** Remove only this module's site-specific storage on uninstall. */
	public static function uninstall_site() {
		global $wpdb;
		delete_option( self::OPTION );
		delete_option( self::OPTION . '_generation' );
		delete_option( 'wpmtk_admin_boost_debug_schema' );
		delete_option( 'wpmtk_admin_boost_history_schema' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Remove the module-owned journal table during explicit plugin uninstall.
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'wpmtk_admin_boost_log' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Remove the module-owned history table during explicit plugin uninstall.
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'wpmtk_admin_boost_history' ) );
	}

	/** Register the module settings using the shared WPMTK menu. */
	public function add_submenu() {
		WPMastertoolkit_Settings::add_submenu_page( 'wp-mastertoolkit-settings', __( 'Admin Boost', 'wpmastertoolkit' ), __( 'Admin Boost', 'wpmastertoolkit' ), 'manage_options', self::SLUG, array( $this, 'render_submenu' ) );
	}

	/**
	 * Register the toolbar entry only for permitted site administrators.
	 *
	 * @param WP_Admin_Bar $bar Administration toolbar.
	 */
	public function admin_bar( $bar ) {
		if ( ! is_admin() || is_network_admin() || is_user_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$state = $this->state();
		if ( $this->is_pro() && $state['active'] ) {
			$bar->add_node(
				array(
					'id'     => 'wpmtk-admin-boost-history',
					'parent' => 'wpmtk-admin-boost',
					'title'  => esc_html__( 'View blocked requests', 'wpmastertoolkit' ),
					'href'   => admin_url( 'admin.php?page=' . self::SLUG . '#wpmtk-boost-history-settings' ),
				)
			);
		}
		$bar->add_node(
			array(
				'id'    => 'wpmtk-admin-boost',
				'title' => '<span class="ab-icon dashicons dashicons-performance" aria-hidden="true"></span><span id="wpmtk-boost-label">' . esc_html( $state['active'] ? __( 'Boost active', 'wpmastertoolkit' ) : __( 'Boost Admin', 'wpmastertoolkit' ) ) . '</span>',
				'href'  => admin_url( 'admin.php?page=' . self::SLUG ),
				'meta'  => array( 'class' => 'wpmtk-boost-adminbar' ),
			)
		);
		foreach ( array(
			'stop'   => __( 'Stop boost', 'wpmastertoolkit' ),
			'reload' => __( 'Reload without temporary boost', 'wpmastertoolkit' ),
		) as $command => $label ) {
			$bar->add_node(
				array(
					'id'     => 'wpmtk-admin-boost-' . $command,
					'parent' => 'wpmtk-admin-boost',
					'title'  => esc_html( $label ),
					'href'   => admin_url( 'admin.php?page=' . self::SLUG ),
				)
			);
		}
	}

	/** Enqueue only this module's independently built assets. */
	public function enqueue_assets() {
		if ( ! current_user_can( 'manage_options' ) || is_network_admin() || is_user_admin() ) {
			return;
		}
		$base = 'admin/assets/build/core/admin-boost';
		if ( ! is_file( WPMASTERTOOLKIT_PLUGIN_PATH . $base . '.asset.php' ) ) {
			return;
		}
		$asset = include WPMASTERTOOLKIT_PLUGIN_PATH . $base . '.asset.php';
		wp_enqueue_script( 'wpmtk-admin-boost', WPMASTERTOOLKIT_PLUGIN_URL . $base . '.js', $asset['dependencies'], $asset['version'], true );
		wp_enqueue_style( 'wpmtk-admin-boost', WPMASTERTOOLKIT_PLUGIN_URL . $base . '.css', array( 'dashicons' ), $asset['version'] );
		wp_localize_script(
			'wpmtk-admin-boost',
			'wpmtkAdminBoost',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( self::NONCE ),
				'state' => $this->state(),
				'pro'   => $this->is_pro(),
			)
		);
	}

	/** Render the accessible toolbar dialog and final page count. */
	public function render_dialog() {
		if ( ! current_user_can( 'manage_options' ) || is_network_admin() || is_user_admin() ) {
			return;
		}
		include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/admin-boost/dialog.php';
	}

	/** Render a nonce without duplicate IDs across independent forms. */
	private function nonce_field() {
		?>
		<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( self::NONCE ) ); ?>">
		<?php
	}

	/** Render settings only after checking permissions. */
	public function render_submenu() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$this->header_title = __( 'Admin Boost', 'wpmastertoolkit' );
		$entries            = array();
		if ( $this->is_pro() ) {
			require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/helpers/pro/admin-boost/class-admin-boost-debug.php';
			$journal = new WPMastertoolkit_Admin_Boost_Debug( $this->journal_key() );
			$entries = $journal->entries();
		}
		wp_localize_script(
			'wpmtk-admin-boost',
			'wpmtkAdminBoostSettings',
			array_merge(
				$this->settings_snapshot(),
				array(
					'entries'   => $entries,
					'page_menu' => $this->page_menu(),
				)
			)
		);
		include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/admin-boost/settings.php';
	}

	/**
	 * Collect accessible menu destinations, grouped by their visible parent.
	 *
	 * @return array
	 */
	private function page_menu() {
		global $menu, $submenu;
		$groups = array();
		$keys   = array();
		foreach ( (array) $menu as $item ) {
			if ( ! isset( $item[0], $item[1], $item[2] ) || ! current_user_can( $item[1] ) || 0 === strpos( $item[2], 'separator' ) ) {
				continue;
			}
			$children = array();
			foreach ( $submenu[ $item[2] ] ?? array() as $child ) {
				if ( ! isset( $child[0], $child[1], $child[2] ) || ! current_user_can( $child[1] ) ) {
					continue;
				}
				$destination = $this->menu_destination( $child[2], $item[2], $child[0] );
				if ( $destination ) {
					$children[ $child[2] ] = $destination;
				}
			}
			$parent = $children ? reset( $children ) : $this->menu_destination( $item[2], 'admin.php', $item[0] );
			if ( ! $parent ) {
				continue;
			}
			$destinations = array( $parent );
			foreach ( $children as $child ) {
				if ( $child['matcher'] !== $parent['matcher'] ) {
					$destinations[] = $child;
				}
			}
			foreach ( $destinations as $destination ) {
				$path          = $destination['matcher']['value'];
				$keys[ $path ] = array_unique( array_merge( $keys[ $path ] ?? array( 'page', 'post_type', 'taxonomy' ), array_keys( $destination['matcher']['query'] ) ) );
			}
			$groups[] = array(
				'label' => $this->menu_label( $item[0] ),
				'pages' => $destinations,
			);
		}
		foreach ( $groups as &$group ) {
			foreach ( $group['pages'] as &$destination ) {
				$matcher           = &$destination['matcher'];
				$matcher['absent'] = array_values( array_diff( $keys[ $matcher['value'] ], array_keys( $matcher['query'] ) ) );
				sort( $matcher['absent'] );
				$matcher['query'] = (object) $matcher['query'];
			}
			unset( $destination, $matcher );
		}
		unset( $group );
		return $groups;
	}

	/**
	 * Remove menu badge markup and decode the plain-text destination label.
	 *
	 * @param string $label Menu title.
	 * @return string
	 */
	private function menu_label( $label ) {
		$label = preg_replace( '~<span\b[^>]*>.*?</span>~is', '', $label );
		return trim( html_entity_decode( wp_strip_all_tags( $label ), ENT_QUOTES, 'UTF-8' ) );
	}

	/**
	 * Resolve an admin menu slug using the same plugin routing as the menu.
	 *
	 * @param string $slug Menu destination.
	 * @param string $parent_slug Parent menu slug.
	 * @param string $label Menu title.
	 * @return array|false
	 */
	private function menu_destination( $slug, $parent_slug, $label ) {
		$slug = html_entity_decode( $slug, ENT_QUOTES, 'UTF-8' );
		if ( get_plugin_page_hook( $slug, $parent_slug ) ) {
			$parent_path = wp_parse_url( $parent_slug, PHP_URL_PATH );
			$core_parent = is_string( $parent_path ) && preg_match( '/^[a-z-]+\.php$/', $parent_path ) && is_file( ABSPATH . 'wp-admin/' . $parent_path ) && ! get_plugin_page_hook( $parent_slug, 'admin.php' );
			$slug        = add_query_arg( 'page', $slug, $core_parent ? $parent_slug : 'admin.php' );
		}
		$url   = wp_parse_url( $slug );
		$admin = wp_parse_url( admin_url() );
		if ( ! is_array( $url ) || isset( $url['user'] ) || isset( $url['pass'] ) || ( isset( $url['host'] ) && ( strtolower( $url['host'] ) !== strtolower( $admin['host'] ) || ( $url['port'] ?? null ) !== ( $admin['port'] ?? null ) ) ) ) {
			return false;
		}
		$path = $url['path'] ?? '';
		if ( '' === $path || ( '/' === $path[0] && 0 !== strpos( $path, $admin['path'] ) ) || ( isset( $url['scheme'] ) && ! in_array( $url['scheme'], array( 'http', 'https' ), true ) ) ) {
			return false;
		}
		$path = WPMastertoolkit_Admin_Boost_Policy::page_path( $path, $admin['path'] );
		if ( ! preg_match( '~^/[a-zA-Z0-9_-]+\.php$~', $path ) ) {
			return false;
		}
		$query = array();
		wp_parse_str( $url['query'] ?? '', $query );
		if ( count( $query ) > 20 ) {
			return false;
		}
		foreach ( $query as $key => $value ) {
			if ( ! preg_match( '/^[a-zA-Z0-9_-]{1,64}$/', (string) $key ) || ! is_string( $value ) || strlen( $value ) > 256 || in_array( $key, array( '_wpnonce', '_ajax_nonce' ), true ) ) {
				return false;
			}
			$query[ $key ] = sanitize_text_field( $value );
		}
		ksort( $query );
		return array(
			'label'   => $this->menu_label( $label ),
			'matcher' => array(
				'kind'  => 'exact',
				'value' => $path,
				'query' => $query,
			),
		);
	}

	/**
	 * Read authoritative settings and independent collection revisions.
	 * Bypass option caches so conditional writes compare the actual database value.
	 *
	 * @param bool $raw Include the serialized value for conditional writes.
	 * @return array
	 */
	private function settings_snapshot( $raw = false ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read the actual value for atomic revision comparison, not a cached snapshot.
		$serialized = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::OPTION ) );
		$stored     = null === $serialized ? array() : maybe_unserialize( $serialized );
		$stored     = is_array( $stored ) ? $stored : array();
		$settings   = WPMastertoolkit_Admin_Boost_Policy::validate_settings(
			array_merge(
				array(
					'pages' => array(),
					'rules' => array(),
				),
				$stored
			),
			wp_parse_url( admin_url(), PHP_URL_PATH )
		);
		if ( is_wp_error( $settings ) ) {
			$settings = array(
				'pages' => array(),
				'rules' => array(),
			);
		}
		$result = array(
			'settings'  => $settings,
			'revisions' => array(),
		);
		foreach ( array( 'pages', 'rules' ) as $scope ) {
			$result['revisions'][ $scope ] = isset( $stored['_revisions'][ $scope ] ) && is_string( $stored['_revisions'][ $scope ] ) ? $stored['_revisions'][ $scope ] : hash( 'sha256', wp_json_encode( $settings[ $scope ] ) );
		}
		if ( $raw ) {
			$result['serialized'] = $serialized;
			$result['stored']     = $stored;
		}
		return $result;
	}

	/**
	 * Persist one collection without overwriting a concurrent unrelated write.
	 * Conditional SQL provides atomic comparison; option caches are cleared on success.
	 *
	 * @param string $scope Collection name.
	 * @param array  $input Submitted collection settings.
	 * @param string $revision Expected collection revision.
	 * @param string $operation Retry-safe operation identifier.
	 * @return array|WP_Error
	 */
	private function save_collection( $scope, $input, $revision, $operation ) {
		global $wpdb;
		if ( ! in_array( $scope, array( 'pages', 'rules' ), true ) || ! is_array( $input ) || ! preg_match( '/^[a-zA-Z0-9_-]{16,80}$/', $operation ) || ! preg_match( '/^[a-zA-Z0-9_-]{16,80}$/', $revision ) ) {
			return new WP_Error( 'settings', __( 'Invalid settings request.', 'wpmastertoolkit' ) );
		}
		$fingerprint = hash( 'sha256', wp_json_encode( array( get_current_user_id(), $scope, $revision, $input ) ) );
		for ( $attempt = 0; $attempt < 5; ++$attempt ) {
			$snapshot = $this->settings_snapshot( true );
			$receipts = $snapshot['stored']['_operations'][ $scope ] ?? array();
			$receipts = is_array( $receipts ) ? $receipts : array();
			if ( isset( $receipts[ $operation ] ) ) {
				$receipt = $receipts[ $operation ];
				if ( ! is_array( $receipt ) || ! isset( $receipt['fingerprint'], $receipt['revision'] ) || ! is_string( $receipt['fingerprint'] ) || ! hash_equals( $receipt['fingerprint'], $fingerprint ) ) {
					return new WP_Error( 'operation', __( 'This retry does not match the original save. Reload this section.', 'wpmastertoolkit' ) );
				}
				return array_merge( $this->settings_snapshot(), array( 'applied_revision' => $receipt['revision'] ) );
			}
			if ( ! hash_equals( $snapshot['revisions'][ $scope ], $revision ) ) {
				return new WP_Error( 'conflict', __( 'This section was changed elsewhere. Your edits have been kept. Reload this section before saving again.', 'wpmastertoolkit' ) );
			}
			$settings = $snapshot['settings'];
			if ( ! array_key_exists( $scope, $input ) ) {
				return new WP_Error( 'settings', __( 'Incomplete settings request.', 'wpmastertoolkit' ) );
			}
			$settings[ $scope ] = $input[ $scope ];
			$settings           = WPMastertoolkit_Admin_Boost_Policy::validate_settings( $settings, wp_parse_url( admin_url(), PHP_URL_PATH ) );
			if ( is_wp_error( $settings ) ) {
				return $settings;
			}
			$settings['_revisions']            = $snapshot['revisions'];
			$settings['_revisions'][ $scope ]  = wp_generate_uuid4();
			$settings['_operations']           = isset( $snapshot['stored']['_operations'] ) && is_array( $snapshot['stored']['_operations'] ) ? $snapshot['stored']['_operations'] : array();
			$receipts[ $operation ]            = array(
				'fingerprint' => $fingerprint,
				'revision'    => $settings['_revisions'][ $scope ],
			);
			$settings['_operations'][ $scope ] = array_slice( $receipts, -32, null, true );
			if ( null === $snapshot['serialized'] ) {
				$written = add_option( self::OPTION, $settings, '', false );
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Atomic compare-and-swap has no option API equivalent; caches are invalidated below.
				$written = $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND BINARY option_value = BINARY %s",
						maybe_serialize( $settings ),
						self::OPTION,
						$snapshot['serialized']
					)
				);
			}
			if ( false === $written && $wpdb->last_error ) {
				return new WP_Error( 'storage', __( 'Settings could not be saved. Try again.', 'wpmastertoolkit' ) );
			}
			if ( $written ) {
				wp_cache_delete( self::OPTION, 'options' );
				wp_cache_delete( 'alloptions', 'options' );
				wp_cache_delete( 'notoptions', 'options' );
				$this->settings = null;
				return array_merge( $this->settings_snapshot(), array( 'applied_revision' => $settings['_revisions'][ $scope ] ) );
			}
		}
		return new WP_Error( 'busy', __( 'Settings are busy. Your edits have been kept. Try again.', 'wpmastertoolkit' ) );
	}

	/** Process nonce-protected, POST-only session and settings commands. */
	public function handle_action() {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( ! current_user_can( 'manage_options' ) || is_network_admin() || is_user_admin() || 'POST' !== $method ) {
			wp_die( esc_html__( 'You are not allowed to perform this action.', 'wpmastertoolkit' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::NONCE );
		$command = isset( $_POST['command'] ) && is_string( $_POST['command'] ) ? sanitize_key( wp_unslash( $_POST['command'] ) ) : '';
		$state   = $this->session();
		if ( ! wp_get_session_token() || ! WP_Session_Tokens::get_instance( get_current_user_id() )->verify( wp_get_session_token() ) ) {
			$this->respond( new WP_Error( 'session', __( 'Your login session has expired.', 'wpmastertoolkit' ) ) );
		}
		if ( 'start' === $command ) {
			$duration = isset( $_POST['duration'] ) && is_scalar( $_POST['duration'] ) ? sanitize_text_field( wp_unslash( $_POST['duration'] ) ) : '120';
			$allowed  = $this->is_pro() ? array( '120', '600', '1800', '3600', '0' ) : array( '120' );
			if ( ! in_array( $duration, $allowed, true ) ) {
				$this->respond( new WP_Error( 'duration', __( 'Invalid boost duration.', 'wpmastertoolkit' ) ) );
			}
			$state['started'] = time();
			$state['until']   = '0' === $duration ? 0 : time() + (int) $duration;
			if ( $this->is_pro() ) {
				require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/helpers/pro/admin-boost/class-admin-boost-history.php';
				$state['history_boost']   = wp_generate_uuid4();
				$history                  = new WPMastertoolkit_Admin_Boost_History( $this->journal_key(), $state['history_boost'] );
				$login                    = WP_Session_Tokens::get_instance( get_current_user_id() )->get( wp_get_session_token() );
				$state['history_failed']  = ! $history->start( $state['until'] ? $state['until'] : (int) $login['expiration'] );
				$state['history_expires'] = $state['until'] ? $state['until'] + DAY_IN_SECONDS : 0;
			}
			$this->session( $state );
		} elseif ( in_array( $command, array( 'stop', 'reload' ), true ) ) {
			if ( $this->is_pro() && ! empty( $state['history_boost'] ) && empty( $state['history_failed'] ) ) {
				require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/helpers/pro/admin-boost/class-admin-boost-history.php';
				$history = new WPMastertoolkit_Admin_Boost_History( $this->journal_key(), $state['history_boost'] );
				$history->stop();
			}
			$state['started'] = 0;
			$state['until']   = 0;
			if ( ! empty( $state['history_boost'] ) ) {
				$state['history_expires'] = time() + DAY_IN_SECONDS;
			}
			$this->session( $state );
		} elseif ( $this->is_pro() && 'history' === $command ) {
			wp_send_json_success( array_merge( $this->state(), array( 'history' => $this->history_snapshot() ) ) );
		} elseif ( $this->is_pro() && in_array( $command, array( 'debug_start', 'debug_stop', 'clear', 'save_rules', 'save_pages', 'load_settings' ), true ) ) {
			require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/helpers/pro/admin-boost/class-admin-boost-debug.php';
			$journal = new WPMastertoolkit_Admin_Boost_Debug( $this->journal_key() );
			if ( 'debug_start' === $command ) {
				if ( ! $journal->install() ) {
					$this->respond( new WP_Error( 'storage', __( 'The debug journal could not be created.', 'wpmastertoolkit' ) ) );
				}
				$state['debug_until'] = time() + 600;
				$this->session( $state );
			} elseif ( 'debug_stop' === $command ) {
				$state['debug_until'] = 0;
				$this->session( $state );
			} elseif ( 'clear' === $command ) {
				$journal->clear();
			} elseif ( 'load_settings' === $command ) {
				wp_send_json_success( array_merge( $this->state(), $this->settings_snapshot() ) );
			} elseif ( in_array( $command, array( 'save_rules', 'save_pages' ), true ) ) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON is size-limited, decoded and exhaustively validated before storage.
				$json = isset( $_POST['settings'] ) && is_string( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : '';
				if ( strlen( $json ) > 131072 ) {
					$this->respond( new WP_Error( 'size', __( 'Settings are too large.', 'wpmastertoolkit' ) ) );
				}
				$revision  = isset( $_POST['revision'] ) && is_string( $_POST['revision'] ) ? sanitize_text_field( wp_unslash( $_POST['revision'] ) ) : '';
				$operation = isset( $_POST['operation'] ) && is_string( $_POST['operation'] ) ? sanitize_text_field( wp_unslash( $_POST['operation'] ) ) : '';
				$result    = $this->save_collection( 'save_pages' === $command ? 'pages' : 'rules', json_decode( $json, true ), $revision, $operation );
				if ( is_wp_error( $result ) ) {
					$this->respond( $result );
				}
				wp_send_json_success( array_merge( $this->state(), $result ) );
			}
		} else {
			$this->respond( new WP_Error( 'command', __( 'This action is unavailable.', 'wpmastertoolkit' ) ) );
		}
		$this->respond();
	}

	/**
	 * Send a JSON result or safely return to the settings page.
	 *
	 * @param WP_Error|null $error Optional validation error.
	 */
	private function respond( $error = null ) {
		if ( wp_doing_ajax() ) {
			if ( $error ) {
				wp_send_json_error(
					array(
						'message' => $error->get_error_message(),
						'code'    => $error->get_error_code(),
					),
					'conflict' === $error->get_error_code() ? 409 : 400
				);
			}
			wp_send_json_success( $this->state() );
		}
		if ( $error ) {
			wp_die(
				esc_html( $error->get_error_message() ),
				'',
				array(
					'response'  => 400,
					'back_link' => true,
				)
			);
		}
		$return = wp_get_referer();
		$base   = admin_url();
		if ( ! $return || wp_parse_url( $return, PHP_URL_HOST ) !== wp_parse_url( $base, PHP_URL_HOST ) || 0 !== strpos( (string) wp_parse_url( $return, PHP_URL_PATH ), (string) wp_parse_url( $base, PHP_URL_PATH ) ) ) {
			$return = admin_url( 'admin.php?page=' . self::SLUG );
		}
		wp_safe_redirect( $return );
		exit;
	}
}
