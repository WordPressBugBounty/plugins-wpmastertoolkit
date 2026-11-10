<?php
/**
 * Site observers for the activity log.
 *
 * @package WPMasterToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Observe site activity through the shared writer. */
class WPMastertoolkit_Activity_Log_Site implements WPMastertoolkit_Activity_Log_Provider {

	/**
	 * Pre-operation snapshots and successful package results by blog and asset.
	 *
	 * @var array
	 */
	private $before = array();

	/**
	 * Package outcomes keyed by blog, type and asset.
	 *
	 * @var array
	 */
	private $packages = array();

	/**
	 * Last emitted software states for overlapping native and MainWP callbacks.
	 *
	 * @var array
	 */
	private $reported = array();

	/**
	 * Installed WordPress version at observer registration.
	 *
	 * @var string
	 */
	private $core_version = '';

	/** Register the existing observer hooks. */
	public function register_hooks() {
		$this->core_version = get_bloginfo( 'version' );
		add_action( 'activated_plugin', array( $this, 'log_plugin_activated' ), 10, 2 );
		add_action( 'deactivated_plugin', array( $this, 'log_plugin_deactivated' ), 10, 2 );
		add_action( 'switch_theme', array( $this, 'log_theme_switched' ), 10, 3 );
		add_action( 'updated_option', array( $this, 'log_setting_updated' ), 10, 3 );
		add_action( 'added_option', array( $this, 'setting_added' ), 10, 2 );
		add_action( 'delete_plugin', array( $this, 'capture_plugin' ) );
		add_action( 'deleted_plugin', array( $this, 'plugin_deleted' ), 10, 2 );
		add_action( 'delete_theme', array( $this, 'capture_theme' ) );
		add_action( 'deleted_theme', array( $this, 'theme_deleted' ), 10, 2 );
		add_filter( 'upgrader_pre_install', array( $this, 'capture_upgrade' ), PHP_INT_MAX, 2 );
		add_filter( 'upgrader_install_package_result', array( $this, 'package_result' ), PHP_INT_MAX, 2 );
		add_action( 'upgrader_process_complete', array( $this, 'log_upgrade' ), 10, 2 );
		add_action( '_core_updated_successfully', array( $this, 'core_updated' ) );
		add_action( 'mainwp_child_installPluginTheme', array( $this, 'mainwp_installed' ) );
		add_action( 'mainwp_child_plugin_action', array( $this, 'mainwp_plugin_deleted' ) );
		add_action( 'mainwp_child_theme_action', array( $this, 'mainwp_theme_deleted' ) );
	}

	/**
	 * Describe site events without depending on presentation translations.
	 *
	 * @param bool $translate Whether to translate labels.
	 * @return array Event groups.
	 */
	public static function event_groups( $translate = false ) {
		return array(
			'site' => array(
				'label'           => $translate ? __( 'Site and software', 'wpmastertoolkit' ) : '',
				'pro'             => false,
				'default_enabled' => true,
				'order'           => 40,
				'event_order'     => 0,
				'events'          => array(
					'plugin.activated'   => $translate ? __( 'Plugin activated', 'wpmastertoolkit' ) : '',
					'plugin.deactivated' => $translate ? __( 'Plugin deactivated', 'wpmastertoolkit' ) : '',
					'theme.switched'     => $translate ? __( 'Theme switched', 'wpmastertoolkit' ) : '',
				) + self::change_labels( $translate ),
			),
		);
	}

	/**
	 * Declare independently selectable software and site-setting changes.
	 *
	 * @param bool $translate Whether to translate labels.
	 * @return array Event labels.
	 */
	private static function change_labels( $translate = false ) {
		return array(
			'plugin.installed'          => $translate ? __( 'Plugin installed', 'wpmastertoolkit' ) : '',
			'plugin.updated'            => $translate ? __( 'Plugin updated', 'wpmastertoolkit' ) : '',
			'plugin.deleted'            => $translate ? __( 'Plugin uninstalled', 'wpmastertoolkit' ) : '',
			'theme.installed'           => $translate ? __( 'Theme installed', 'wpmastertoolkit' ) : '',
			'theme.updated'             => $translate ? __( 'Theme updated', 'wpmastertoolkit' ) : '',
			'theme.deleted'             => $translate ? __( 'Theme uninstalled', 'wpmastertoolkit' ) : '',
			'core.updated'              => $translate ? __( 'WordPress core updated', 'wpmastertoolkit' ) : '',
			'site.url_changed'          => $translate ? __( 'WordPress address changed', 'wpmastertoolkit' ) : '',
			'site.home_changed'         => $translate ? __( 'Site address changed', 'wpmastertoolkit' ) : '',
			'site.registration_changed' => $translate ? __( 'User registration setting changed', 'wpmastertoolkit' ) : '',
			'site.default_role_changed' => $translate ? __( 'Default user role changed', 'wpmastertoolkit' ) : '',
			'site.admin_email_changed'  => $translate ? __( 'Administration email changed', 'wpmastertoolkit' ) : '',
			'site.permalinks_changed'   => $translate ? __( 'Permalink structure changed', 'wpmastertoolkit' ) : '',
			'site.visibility_changed'   => $translate ? __( 'Search engine visibility changed', 'wpmastertoolkit' ) : '',
		);
	}

	/**
	 * Get historical labels.
	 *
	 * @param bool $translate Whether to translate labels.
	 * @return array Labels.
	 */
	public static function historical_labels( $translate = true ) {
		return array(
			'software.install' => $translate ? __( 'Software installed', 'wpmastertoolkit' ) : '',
			'software.update'  => $translate ? __( 'Software updated', 'wpmastertoolkit' ) : '',
			'setting.updated'  => $translate ? __( 'Site setting updated', 'wpmastertoolkit' ) : '',
		);
	}

	/**
	 * Get domain detail labels.
	 *
	 * @param string $event Event ID.
	 * @return array Labels.
	 */
	public static function detail_labels( $event ) {
		return array(
			'name'           => __( 'Software name', 'wpmastertoolkit' ),
			'version'        => __( 'Version', 'wpmastertoolkit' ),
			'before_version' => __( 'Previous version', 'wpmastertoolkit' ),
			'after_version'  => __( 'New version', 'wpmastertoolkit' ),
			'author'         => __( 'Author', 'wpmastertoolkit' ),
			'asset'          => __( 'Plugin file or theme slug', 'wpmastertoolkit' ),
			'network'        => __( 'Network-wide operation', 'wpmastertoolkit' ),
			'option'         => __( 'Site setting', 'wpmastertoolkit' ),
		);
	}

	/**
	 * Keep only bounded, safe software and setting details.
	 *
	 * @param string $event Event ID.
	 * @param mixed  $details Supplied details.
	 * @return array Safe details.
	 */
	public static function normalize_details( $event, $details ) {
		$events = self::event_groups()['site']['events'];
		if ( ! isset( $events[ $event ] ) || ! is_array( $details ) ) {
			return array();
		}
		$normalized = array();
		foreach ( array( 'name', 'version', 'before_version', 'after_version', 'author', 'asset', 'option' ) as $field ) {
			if ( isset( $details[ $field ] ) && is_string( $details[ $field ] ) ) {
				$normalized[ $field ] = self::bounded_text( $details[ $field ] );
			}
		}
		if ( isset( $details['network'] ) && in_array( $details['network'], array( true, false, 0, 1 ), true ) ) {
			$normalized['network'] = (int) $details['network'];
		}
		if ( isset( $details['source'] ) && in_array( $details['source'], array( 'user', 'system', 'cli', 'mainwp' ), true ) ) {
			$normalized['source'] = $details['source'];
		}
		foreach ( array( 'before', 'after' ) as $field ) {
			$value = $details[ $field ] ?? null;
			if ( 'site.admin_email_changed' === $event ) {
				$normalized[ $field ] = 'redacted';
			} elseif ( in_array( $event, array( 'site.url_changed', 'site.home_changed' ), true ) && is_string( $value ) ) {
				$parts = wp_parse_url( $value );
				if ( is_array( $parts ) && isset( $parts['scheme'], $parts['host'] ) && in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
					$normalized[ $field ] = self::bounded_text( strtolower( $parts['scheme'] ) . '://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . absint( $parts['port'] ) : '' ) . ( $parts['path'] ?? '' ) );
				}
			} elseif ( in_array( $event, array( 'site.registration_changed', 'site.visibility_changed' ), true ) && in_array( $value, array( '0', '1', 0, 1 ), true ) ) {
				$normalized[ $field ] = (int) $value;
			} elseif ( in_array( $event, array( 'site.default_role_changed', 'site.permalinks_changed', 'theme.switched' ), true ) && is_string( $value ) ) {
				$normalized[ $field ] = self::bounded_text( $value );
			}
		}
		return $normalized;
	}

	/**
	 * Preserve explicit granular choices and inherit retired generic choices.
	 *
	 * @param array $preferences Saved preferences.
	 * @return array Preferences.
	 */
	public static function migrate_preferences( $preferences ) {
		foreach ( array_keys( self::change_labels() ) as $event ) {
			$legacy = null;
			if ( false !== strpos( $event, '.installed' ) ) {
				$legacy = 'software.install';
			} elseif ( false !== strpos( $event, '.updated' ) ) {
				$legacy = 'software.update';
			} elseif ( 0 === strpos( $event, 'site.' ) ) {
				$legacy = 'setting.updated';
			}
			if ( $legacy && ! isset( $preferences[ $event ] ) && isset( $preferences[ $legacy ] ) && in_array( $preferences[ $legacy ], array( '0', '1' ), true ) ) {
				$preferences[ $event ] = $preferences[ $legacy ];
			}
		}
		unset( $preferences['software.install'], $preferences['software.update'], $preferences['setting.updated'] );
		return $preferences;
	}

	/**
	 * Sanitize and bound text without requiring mbstring.
	 *
	 * @param string $value Text.
	 * @return string Safe text.
	 */
	private static function bounded_text( $value ) {
		$value = sanitize_text_field( $value );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 160, 'UTF-8' ) : _mb_substr( $value, 0, 160, 'UTF-8' );
	}

	/**
	 * Log plugin activation.
	 *
	 * @param string $plugin Plugin file path.
	 * @param bool   $network Whether this is network-wide.
	 * @since 2.26.0
	 */
	public function log_plugin_activated( $plugin, $network = false ) {
		$details = $this->asset_details( 'plugin', $plugin );
		if ( $details ) {
			$details['network'] = (bool) $network;
			$this->software_event( 'plugin.activated', 'plugin', $plugin, $details );
		}
	}

	/**
	 * Log plugin deactivation.
	 *
	 * @param string $plugin Plugin file path.
	 * @param bool   $network Whether this is network-wide.
	 * @since 2.26.0
	 */
	public function log_plugin_deactivated( $plugin, $network = false ) {
		$details = $this->asset_details( 'plugin', $plugin );
		if ( $details ) {
			$details['network'] = (bool) $network;
			$this->software_event( 'plugin.deactivated', 'plugin', $plugin, $details );
		}
	}

	/**
	 * Log the newly selected theme.
	 *
	 * @param string        $theme_name Theme name.
	 * @param WP_Theme      $theme      Theme object.
	 * @param WP_Theme|null $old_theme Previous theme on newer WordPress.
	 * @since 2.26.0
	 */
	public function log_theme_switched( $theme_name, $theme, $old_theme = null ) {
		if ( ! $theme instanceof WP_Theme || ! $theme->exists() ) {
			return;
		}
		$asset            = $theme->get_stylesheet();
		$details          = $this->asset_details( 'theme', $asset );
		$details['after'] = $asset;
		if ( $old_theme instanceof WP_Theme ) {
			$details['before'] = $old_theme->get_stylesheet();
			if ( $details['before'] === $asset ) {
				return;
			}
		}
		$this->software_event( 'theme.switched', 'theme', $asset, $details );
	}

	/**
	 * Log changes to a fixed list of security-relevant site settings.
	 *
	 * @param string $option Option name.
	 * @param mixed  $old Previous value.
	 * @param mixed  $value Persisted value.
	 * @since 2.26.0
	 */
	public function log_setting_updated( $option, $old = null, $value = null ) {
		$events = array(
			'siteurl'             => 'site.url_changed',
			'home'                => 'site.home_changed',
			'users_can_register'  => 'site.registration_changed',
			'default_role'        => 'site.default_role_changed',
			'admin_email'         => 'site.admin_email_changed',
			'permalink_structure' => 'site.permalinks_changed',
			'blog_public'         => 'site.visibility_changed',
		);
		if ( ! isset( $events[ $option ] ) || ! is_scalar( $old ) || ! is_scalar( $value ) || (string) $old === (string) $value ) {
			return;
		}
		$event   = $events[ $option ];
		$details = array(
			'option' => $option,
			'before' => (string) $old,
			'after'  => (string) $value,
		);
		$this->emit( $event, 'setting', $option, self::normalize_details( $event, $details ) );
	}

	/**
	 * Treat initial option writes as changes from an empty value.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value Persisted value.
	 */
	public function setting_added( $option, $value ) {
		$this->log_setting_updated( $option, '', $value );
	}

	/**
	 * Capture plugin metadata before deletion removes its headers.
	 *
	 * @param string $plugin Plugin basename.
	 */
	public function capture_plugin( $plugin ) {
		$this->capture_asset( 'plugin', $plugin );
	}

	/**
	 * Capture theme metadata before deletion removes its headers.
	 *
	 * @param string $theme Theme stylesheet.
	 */
	public function capture_theme( $theme ) {
		$this->capture_asset( 'theme', $theme );
	}

	/**
	 * Retain software identity and initiating actor for a completed operation.
	 *
	 * @param string $type Asset category.
	 * @param string $asset Asset identifier.
	 */
	private function capture_asset( $type, $asset ) {
		$details = $this->asset_details( $type, $asset );
		if ( $details ) {
			$this->before[ $this->key( $type, $asset ) ] = array(
				'details' => $details,
				'actor'   => $this->actor_id(),
				'source'  => $this->source(),
			);
		}
	}

	/**
	 * Record only successful plugin removal.
	 *
	 * @param string $plugin Plugin basename.
	 * @param bool   $deleted Core deletion result.
	 */
	public function plugin_deleted( $plugin, $deleted ) {
		$this->asset_deleted( 'plugin', $plugin, $deleted );
	}

	/**
	 * Record only successful theme removal.
	 *
	 * @param string $theme Theme stylesheet.
	 * @param bool   $deleted Core deletion result.
	 */
	public function theme_deleted( $theme, $deleted ) {
		$this->asset_deleted( 'theme', $theme, $deleted );
	}

	/**
	 * Emit deleted metadata from its pre-operation snapshot.
	 *
	 * @param string $type Asset category.
	 * @param string $asset Asset identifier.
	 * @param bool   $deleted Successful deletion.
	 */
	private function asset_deleted( $type, $asset, $deleted ) {
		$key      = $this->key( $type, $asset );
		$snapshot = $this->before[ $key ] ?? null;
		unset( $this->before[ $key ] );
		if ( true === $deleted && $snapshot ) {
			$this->software_event( $type . '.deleted', $type, $asset, $snapshot['details'] + array( 'source' => $snapshot['source'] ), $snapshot['actor'] );
		}
	}

	/**
	 * Snapshot versions before update filters write new software files.
	 *
	 * @param mixed $result Filter result.
	 * @param array $extra Upgrade context.
	 * @return mixed Unchanged result.
	 */
	public function capture_upgrade( $result, $extra ) {
		if ( is_array( $extra ) && in_array( $extra['type'] ?? null, array( 'plugin', 'theme' ), true ) && isset( $extra[ $extra['type'] ] ) ) {
			$type  = $extra['type'];
			$asset = $extra[ $type ];
			if ( $this->valid_asset( $asset ) ) {
				unset( $this->packages[ $this->key( $type, $asset ) ] );
				$this->capture_asset( $type, $asset );
			}
		}
		return $result;
	}

	/**
	 * Retain individual package results rather than bulk upgrader's last result.
	 *
	 * @param mixed $result Completed package result.
	 * @param array $extra Upgrade context.
	 * @return mixed Unchanged result.
	 */
	public function package_result( $result, $extra ) {
		if ( is_array( $extra ) && in_array( $extra['type'] ?? null, array( 'plugin', 'theme' ), true ) && isset( $extra[ $extra['type'] ] ) && $this->valid_asset( $extra[ $extra['type'] ] ) ) {
			$this->packages[ $this->key( $extra['type'], $extra[ $extra['type'] ] ) ] = is_array( $result ) && ! empty( $result );
		}
		return $result;
	}

	/**
	 * Log a completed WordPress core, plugin, or theme upgrade.
	 *
	 * @param WP_Upgrader $upgrader Upgrader instance.
	 * @param array       $details  Upgrade context.
	 * @since 2.26.0
	 */
	public function log_upgrade( $upgrader, $details ) {
		if ( ! is_array( $details ) || ! in_array( $details['type'] ?? '', array( 'core', 'plugin', 'theme' ), true ) || ! in_array( $details['action'] ?? '', array( 'install', 'update' ), true ) ) {
			return;
		}
		$type = $details['type'];
		if ( 'core' === $type ) {
			return;
		}
		if ( ! is_object( $upgrader ) ) {
			return;
		}
		$assets = $details[ $type . 's' ] ?? array();
		if ( isset( $details[ $type ] ) ) {
			$assets = array( $details[ $type ] );
		}
		if ( 'install' === $details['action'] && empty( $assets ) && method_exists( $upgrader, $type . '_info' ) ) {
			$info   = $upgrader->{ $type . '_info' }();
			$assets = array( $info instanceof WP_Theme ? $info->get_stylesheet() : $info );
		}
		if ( ! is_array( $assets ) ) {
			return;
		}
		foreach ( array_unique( array_filter( $assets, 'is_string' ) ) as $asset ) {
			$key = $this->key( $type, $asset );
			$ok  = ! empty( $details['bulk'] ) ? ( $this->packages[ $key ] ?? false ) : ( isset( $upgrader->result ) && is_array( $upgrader->result ) && ! empty( $upgrader->result ) );
			if ( isset( $this->packages[ $key ] ) ) {
				$ok = $this->packages[ $key ];
			}
			if ( ! $ok ) {
				unset( $this->before[ $key ], $this->packages[ $key ] );
				continue;
			}
			$this->asset_upgraded( $type, $asset, $details['action'] );
		}
	}

	/**
	 * Observe only WordPress's confirmed successful core update.
	 *
	 * @param string $version Installed core version.
	 */
	public function core_updated( $version ) {
		if ( is_string( $version ) && '' !== $version && $this->core_version !== $version ) {
			$this->software_event(
				'core.updated',
				'core',
				'WordPress',
				array(
					'name'           => 'WordPress',
					'before_version' => $this->core_version,
					'after_version'  => $version,
				)
			);
			$this->core_version = $version;
		}
	}

	/**
	 * Emit one installed or updated event with old and new version context.
	 *
	 * @param string      $type Asset category.
	 * @param string      $asset Asset identifier.
	 * @param string      $action Completed operation.
	 * @param string|null $source Explicit integration source.
	 */
	private function asset_upgraded( $type, $asset, $action, $source = null ) {
		$key      = $this->key( $type, $asset );
		$snapshot = $this->before[ $key ] ?? null;
		unset( $this->before[ $key ], $this->packages[ $key ] );
		$details = $this->asset_details( $type, $asset );
		if ( ! $details ) {
			return;
		}
		if ( 'update' === $action ) {
			$details['after_version'] = $details['version'];
			if ( $snapshot ) {
				$details['before_version'] = $snapshot['details']['version'];
			}
		}
		$details['source'] = $source ?? ( $snapshot['source'] ?? $this->source() );
		$this->software_event( $type . ( 'install' === $action ? '.installed' : '.updated' ), $type, $asset, $details, $snapshot['actor'] ?? null );
	}

	/**
	 * Resolve metadata without exposing filesystem locations or package URLs.
	 *
	 * @param string $type Asset category.
	 * @param string $asset Asset identifier.
	 * @return array Software details or an empty array for invalid assets.
	 */
	private function asset_details( $type, $asset ) {
		if ( ! $this->valid_asset( $asset ) ) {
			return array();
		}
		if ( 'plugin' === $type ) {
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$plugins = get_plugins();
			if ( ! isset( $plugins[ $asset ] ) ) {
				return array();
			}
			$metadata = $plugins[ $asset ];
		} else {
			$theme = wp_get_theme( $asset );
			if ( ! $theme->exists() ) {
				return array();
			}
			$metadata = array(
				'Name'    => $theme->get( 'Name' ),
				'Version' => $theme->get( 'Version' ),
				'Author'  => $theme->get( 'Author' ),
			);
		}
		return array(
			'asset'   => $asset,
			'name'    => (string) ( $metadata['Name'] ?? $asset ),
			'version' => (string) ( $metadata['Version'] ?? '' ),
			'author'  => (string) ( $metadata['Author'] ?? '' ),
		);
	}

	/**
	 * Reject absolute paths, traversal and malformed integration identifiers.
	 *
	 * @param mixed $asset Asset identifier.
	 * @return bool Whether the identifier is safe to resolve.
	 */
	private function valid_asset( $asset ) {
		return is_string( $asset ) && '' !== $asset && 0 === validate_file( $asset ) && '/' !== substr( $asset, 0, 1 ) && false === strpos( $asset, '\\' ) && false === strpos( $asset, ':' );
	}

	/**
	 * MainWP's completed installation callback may overlap with core hooks.
	 *
	 * @param array $args Completed operation payload.
	 */
	public function mainwp_installed( $args ) {
		if ( ! is_array( $args ) || 'install' !== ( $args['action'] ?? '' ) || ! in_array( $args['success'] ?? null, array( true, 1, '1' ), true ) || ! in_array( $args['type'] ?? '', array( 'plugin', 'theme' ), true ) || ! $this->valid_asset( $args['slug'] ?? null ) ) {
			return;
		}
		$type  = $args['type'];
		$asset = $args['slug'];
		if ( 'plugin' === $type && ! $this->asset_details( $type, $asset ) ) {
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$matches = array();
			foreach ( array_keys( get_plugins() ) as $plugin ) {
				if ( dirname( $plugin ) === $asset ) {
					$matches[] = $plugin;
				}
			}
			if ( 1 !== count( $matches ) ) {
				return;
			}
			$asset = $matches[0];
		}
		$this->asset_upgraded( $type, $asset, 'install', 'mainwp' );
	}

	/**
	 * Accept successful MainWP plugin deletions only with native captured context.
	 *
	 * @param array $args Completed operation payload.
	 */
	public function mainwp_plugin_deleted( $args ) {
		$this->mainwp_deleted( 'plugin', $args );
	}

	/**
	 * Accept successful MainWP theme deletions only with native captured context.
	 *
	 * @param array $args Completed operation payload.
	 */
	public function mainwp_theme_deleted( $args ) {
		$this->mainwp_deleted( 'theme', $args );
	}

	/**
	 * Never infer deletion success from request parameters or the Name field.
	 *
	 * @param string $type Asset category.
	 * @param array  $args Completed operation payload.
	 */
	private function mainwp_deleted( $type, $args ) {
		if ( ! is_array( $args ) || 'delete' !== ( $args['action'] ?? '' ) || ! in_array( $args['success'] ?? null, array( true, 1, '1' ), true ) ) {
			return;
		}
		$asset = $args[ $type ] ?? ( $args['slug'] ?? null );
		if ( $this->valid_asset( $asset ) && ! $this->asset_details( $type, $asset ) && isset( $this->before[ $this->key( $type, $asset ) ] ) ) {
			$this->before[ $this->key( $type, $asset ) ]['source'] = 'mainwp';
			$this->asset_deleted( $type, $asset, true );
		}
	}

	/**
	 * Scope state to each site and software identity.
	 *
	 * @param string $type Asset category.
	 * @param string $asset Asset identifier.
	 * @return string State key.
	 */
	private function key( $type, $asset ) {
		return get_current_blog_id() . ':' . $type . ':' . $asset;
	}

	/**
	 * Identify the initiating actor without attributing cron updates to a user.
	 *
	 * @return int Actor ID.
	 */
	private function actor_id() {
		return wp_doing_cron() ? 0 : get_current_user_id();
	}

	/**
	 * Identify an integration by active hooks, not an unverified POST signature.
	 *
	 * @return string Operation source.
	 */
	private function source() {
		if ( doing_action( 'mainwp_child_installPluginTheme' ) || doing_action( 'mainwp_child_plugin_action' ) || doing_action( 'mainwp_child_theme_action' ) ) {
			return 'mainwp';
		}
		return defined( 'WP_CLI' ) && WP_CLI ? 'cli' : ( $this->actor_id() ? 'user' : 'system' );
	}

	/**
	 * Suppress overlapping reports while retaining opposite state transitions.
	 *
	 * @param string   $event Event ID.
	 * @param string   $type Asset category.
	 * @param string   $asset Asset identifier.
	 * @param array    $details Safe event context.
	 * @param int|null $actor Captured actor.
	 */
	private function software_event( $event, $type, $asset, $details, $actor = null ) {
		$asset_key = $this->key( $type, $asset );
		$key       = $asset_key . ':' . $event;
		$token     = ( $details['version'] ?? ( $details['after_version'] ?? '' ) ) . ':' . ( $details['network'] ?? '' );
		if ( 'theme.switched' === $event ) {
			$key   = get_current_blog_id() . ':theme.switched';
			$token = $asset . ':' . $token;
		}
		if ( ( $this->reported[ $key ] ?? '' ) === $token ) {
			return;
		}
		if ( 'plugin.activated' === $event ) {
			unset( $this->reported[ $asset_key . ':plugin.deactivated' ] );
		} elseif ( 'plugin.deactivated' === $event ) {
			unset( $this->reported[ $asset_key . ':plugin.activated' ] );
		} elseif ( $type . '.deleted' === $event ) {
			unset( $this->reported[ $asset_key . ':' . $type . '.installed' ], $this->reported[ $asset_key . ':' . $type . '.updated' ] );
		} elseif ( $type . '.installed' === $event ) {
			unset( $this->reported[ $asset_key . ':' . $type . '.deleted' ] );
		}
		$this->reported[ $key ] = $token;
		$this->emit( $event, $type, $asset, $details, $actor );
	}

	/**
	 * Send a user-initiated event to the normalized writer.
	 *
	 * @param string     $event     Event identifier.
	 * @param string     $object_type Object category.
	 * @param int|string $object_id Object identifier.
	 * @param array      $details   Optional event details.
	 * @param int|null   $actor     Captured initiating actor.
	 * @since 2.26.0
	 */
	private function emit( $event, $object_type, $object_id, $details = array(), $actor = null ) {
		$actor             = null === $actor ? $this->actor_id() : $actor;
		$details['source'] = $details['source'] ?? $this->source();
		do_action( 'wpmastertoolkit/activity_log/event', $event, $actor, $object_type, $object_id, self::normalize_details( $event, $details ) ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Preserve the existing public event action.
	}
}
