<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * The class responsible for handling the surecart.
 *
 * @link       https://webdeclic.com
 * @since      1.15.0
 *
 * @package           WPMastertoolkit
 * @subpackage WP-Mastertoolkit/admin
 */
class WPMastertoolkit_Surecart {

	/**
	 * The client instance.
	 */
	private $client;

	/**
	 * The settings instance.
	 */
	private $settings;

	/**
	 * The updater instance.
	 */
	private $updater;

	/**
	 * The transient id.
	 */
	private $transient_id = 'wpmastertoolkit_surecart_license';

	/**
	 * Init the surecart.
	 * 
	 * @since 1.15.0
	 */
	public function init_surecart() {
		global $wpmtk_surecart_client;

		if ( ! class_exists( 'SureCart\Licensing\Client' ) ) {
			require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'licensing/src/Client.php';
		}

		$this->client   = new \SureCart\Licensing\Client( 'WPMasterToolKit', 'pt_peLDYfw2gnkrUcoY5BzTNC89', WPMASTERTOOLKIT_PLUGIN_FILE );
		$wpmtk_surecart_client = $this->client;

		$this->settings = $this->client->settings();
		$this->updater  = $this->client->updater();

		$this->client->settings()->add_page( array(
			'type'        => 'submenu',
			'parent_slug' => 'wp-mastertoolkit-settings',
			'page_title'  => esc_html__( 'Manage License', 'wpmastertoolkit' ),
			'menu_title'  => esc_html__( 'Manage License', 'wpmastertoolkit' ),
			'capability'  => 'manage_options',
			'menu_slug'   => $this->client->slug . '-manage-license',
			'icon_url'    => '',
			'position'    => null,
		));

		$this->maybe_auto_activate_from_constant();

		if ( $this->license_activated() ) {
			$site_transient_prefix = 'site_transient_';//phpcs:ignore prefix to ignore the error
			add_filter( $site_transient_prefix . 'update_plugins', array( $this, 'force_surecart_updates' ) );
		} else {
			$site_transient_prefix = 'pre_set_site_transient_';//phpcs:ignore prefix to ignore the error
			remove_filter(  $site_transient_prefix . 'update_plugins', array( $this->updater, 'check_plugin_update' ) );
			remove_filter( 'plugins_api', array( $this->updater, 'plugins_api_filter' ), 10, 3 );
		}
	}

	/**
	 * Enqueue scripts and styles
	 * 
	 * @since 1.15.0
	 */
	public function enqueue_scripts_styles() {
		$surecart_license_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/surecart-license.asset.php' );
		wp_enqueue_style( 'WPMastertoolkit_surecart_license', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/surecart-license.css', array(), $surecart_license_assets['version'], 'all' );
		wp_enqueue_script( 'WPMastertoolkit_surecart_license', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/surecart-license.js', $surecart_license_assets['dependencies'], $surecart_license_assets['version'], true );
		$upgrade_label = wpmastertoolkit_is_pro() ? __( 'Activate License', 'wpmastertoolkit' ) : __( 'Upgrade Pro', 'wpmastertoolkit' );
		wp_localize_script( 'WPMastertoolkit_surecart_license', 'WPMastertoolkit_surecart_license', array(
			'activated' => $this->license_activated(),
			'i18n'      => array(
				'upgrade' => esc_js( '★ ' . esc_html( $upgrade_label ) ),
			),
		));
	}

	/**
	 * Show warning in llicence page if new version is available
	 * 
	 * @since 1.15.0
	 */
	public function show_warning_if_new_version( $action ) {
		if ( ! wpmastertoolkit_is_pro() && 'activate' === $action ) {
			$version_info = $this->updater->get_version_info();
			if ( is_object( $version_info ) ) {
				$new_version = $version_info->new_version ?? '';
				if ( version_compare( $new_version, WPMASTERTOOLKIT_VERSION, '>' ) ) {
					?>
						<div class="wpmastertoolkit-update-notice orange">
							<b>
								<?php esc_html_e( 'Note:', 'wpmastertoolkit' ); ?>
							</b>
							<?php 
							echo wp_kses_post( sprintf(
								/* translators: %s: new version */
								__( 'A new version %s is available. If you activate your license, you will get the latest version automatically.', 'wpmastertoolkit' ),
								$new_version
							) );
							?>
						</div>
					<?php
				}
			}
		}
	}

	/**
	 * After license is activated
	 * 
	 * @since 1.15.0
	 */
	public function after_activated() {
		if ( ! wpmastertoolkit_is_pro() ) {
			$this->updater->delete_cached_version_info();
			$version_info = $this->updater->get_version_info();
			if ( is_object( $version_info ) ) {
				include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
				include_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/class-surecart-update.php';

				$upgrader = new WPMastertoolkit_Surecart_Update();
				$upgrader->update_from_surecart( $version_info->package );
			}
		}
		delete_transient( $this->transient_id );
		$site_transient_prefix = '_site_transient_';//phpcs:ignore prefix to ignore the error
		delete_option( $site_transient_prefix . 'update_plugins' );
	}

	/**
	 * After license is deactivated
	 * 
	 * @since 1.15.0
	 */
	public function after_deactivated() {
		delete_transient( $this->transient_id );
		$site_transient_prefix = '_site_transient_';//phpcs:ignore prefix to ignore the error
		delete_option( $site_transient_prefix . 'update_plugins' );
	}

	/**
	 * Force the surecart update if the license is active.
	 * 
	 * @since 1.15.0
	 */
	public function force_surecart_updates( $value ) {
		if ( is_object( $value ) ) {
			if ( isset( $value->response[ WPMASTERTOOLKIT_BASENAME ] ) ) {
				$version_info = $this->updater->get_version_info();

				if ( is_object( $version_info ) ) {
					$value->response[ WPMASTERTOOLKIT_BASENAME ]->new_version  = $version_info->new_version;
					$value->response[ WPMASTERTOOLKIT_BASENAME ]->package      = $version_info->package;
					$value->response[ WPMASTERTOOLKIT_BASENAME ]->requires     = $version_info->requires;
					$value->response[ WPMASTERTOOLKIT_BASENAME ]->tested       = $version_info->tested;
					$value->response[ WPMASTERTOOLKIT_BASENAME ]->requires_php = $version_info->requires_php;
				}
			}
		}

		return $value;
	}

	/**
	 * Auto-activate from the WPMASTERTOOLKIT_LICENSE_KEY constant when defined in wp-config.php.
	 * Handles key rotation by deactivating the old activation and re-activating with the new key.
	 *
	 * @since 1.15.0
	 */
	private function maybe_auto_activate_from_constant() {
		/**
		 * To auto-activate using a constant, define WPMASTERTOOLKIT_LICENSE_KEY in your wp-config.php with your license key as the value. Example:
		 * define( 'WPMASTERTOOLKIT_LICENSE_KEY', 'your_license_key_here' );
		 */
		if ( ! defined( 'WPMASTERTOOLKIT_LICENSE_KEY' ) ) {
			return;
		}

		$constant_key  = constant( 'WPMASTERTOOLKIT_LICENSE_KEY' );
		$stored_key    = $this->settings->get_option( 'sc_license_key' );
		$activation_id = $this->settings->get_option( 'sc_activation_id' );

		// Key rotation: constant differs from stored key → deactivate old, clear options, re-activate.
		if ( ! empty( $stored_key ) && $stored_key !== $constant_key ) {
			if ( ! empty( $activation_id ) ) {
				$this->client->license()->deactivate( $activation_id ); // best-effort, ignore errors
			}
			$this->settings->clear_options();
			delete_transient( $this->transient_id );
			$activation_id = null;
		}

		// Already activated with this key — nothing to do.
		if ( ! empty( $activation_id ) ) {
			return;
		}

		// Throttle: avoid hammering the API on repeated failures.
		$retry_transient = $this->transient_id . '_constant_retry';
		if ( get_transient( $retry_transient ) ) {
			return;
		}

		$activated = $this->client->license()->activate( $constant_key );
		if ( is_wp_error( $activated ) ) {
			set_transient( $retry_transient, true, 5 * MINUTE_IN_SECONDS );
			return;
		}

		$this->after_activated();
	}

	/**
	 * License activated
	 * 
	 * @since 1.15.0
	 */
	public function license_activated() {
		$activation = get_transient( $this->transient_id );

		if ( false === $activation ) {
			$activation = $this->settings->get_activation();
			if ( ! empty( $activation->id ) ) {
				set_transient( $this->transient_id, true, HOUR_IN_SECONDS * 2 );
			}
		}

		return $activation;
	}
}
