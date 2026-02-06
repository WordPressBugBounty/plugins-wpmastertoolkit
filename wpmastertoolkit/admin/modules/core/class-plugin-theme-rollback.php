<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Plugin & Theme Rollback
 * Description: Revert to previous versions of any theme or plugin from WordPress.org.
 * @since 1.10.0
 */
class WPMastertoolkit_Plugin_Theme_Rollback {

	private $option_id;
    private $header_title;
    private $user_nonce;
	private $disable_form;
    private $transient_name;

    /**
     * Invoke the hooks
     * 
     * @since    1.10.0
     */
    public function __construct() {
		$this->option_id      = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_plugin_theme_rollback';
        $this->user_nonce     = $this->option_id . '_action';
		$this->disable_form   = true;
		$this->transient_name = $this->option_id . '_transient';

		$set_site_transient_prefix = 'set_site_transient';//phpcs:ignore prefix to ignore the error

        add_action( 'init', array( $this, 'class_init' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts_styles' ), 110 );
		add_filter( 'plugin_action_links', array( $this, 'add_plugin_action_links' ), 1, 3 );
		add_filter( 'theme_action_links', array( $this, 'add_theme_action_links' ), 20, 2 );
		add_action( $set_site_transient_prefix . '_update_themes', array( $this, 'theme_updates_list' ) );
		add_filter( 'wp_prepare_themes_for_js', array( $this, 'prepare_themes_js' ) );
		add_action( 'admin_menu', array( $this, 'rollback_admin_menu' ), 999 );

		$this->setup_plugin_vars();
    }

	/**
     * Initialize the class
	 * 
	 * @since    1.10.0
     */
    public function class_init() {
        $this->header_title = esc_html__( 'Plugin & Theme Rollback', 'wpmastertoolkit' );
    }

	/**
	 * Enqueue admin scripts and styles
	 * 
	 * @since    1.10.0
	 */
	public function enqueue_admin_scripts_styles( $hook_suffix ) {
		if ( 'themes.php' === $hook_suffix ) {
			$themes_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/plugin-theme-rollback-themes.asset.php' );
			wp_enqueue_script( 'WPMastertoolkit_plugin_theme_rollback_themes', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/plugin-theme-rollback-themes.js', $themes_assets['dependencies'], $themes_assets['version'], true );
			wp_localize_script( 'WPMastertoolkit_plugin_theme_rollback_themes', 'WPMastertoolkitPluginThemeRollbackThemes', array(
				'ajaxUrl'  => admin_url(),
				'nonce'    => wp_create_nonce( $this->user_nonce ),
				'optionId' => $this->option_id,
				'i10n'     => array(
					'rollbackLabel'   => __( 'Rollback', 'wpmastertoolkit' ),
					'notRollbackable' => __( 'No Rollback Available: This is a non-WordPress.org theme.', 'wpmastertoolkit' ),
					'loadingRollback' => __( 'Loading...', 'wpmastertoolkit' ),
				),
			));
		}
	}

	/**
	 * Add plugin action links
	 * 
	 * @since    1.10.0
	 */
	public function add_plugin_action_links( $actions, $plugin_file, $plugin_data ) {
		// In case plugin is missing package data do not output Rollback option.
		if ( ! isset( $plugin_data['package'] ) || strpos( $plugin_data['package'], 'https://downloads.wordpress.org' ) === false ) {
			return $actions;
		}

		if ( ! isset( $plugin_data['Version'] ) ) {
			return $actions;
		}

		$rollback_url = add_query_arg( array(
			'page'            => urlencode( $this->option_id ),
			'type'            => urlencode( 'plugin' ),
			'plugin_file'     => urlencode( $plugin_file ),
			'current_version' => urlencode( $plugin_data['Version'] ),
			'rollback_name'   => urlencode( $plugin_data['Name'] ),
			'plugin_slug'     => urlencode( $plugin_data['slug'] ),
			'_wpnonce'        => wp_create_nonce( $this->user_nonce ),
		), 'admin.php' );

		$actions[$this->option_id] = '<a href="' . esc_url( $rollback_url ) . '">' . __( 'Rollback', 'wpmastertoolkit' ) . '</a>';
		
		return $actions;
	}

	/**
	 * Add theme action links
	 * 
	 * @since    1.10.0
	 */
	public function add_theme_action_links( $actions, $theme ) {
		$rollback_themes = get_site_transient( $this->transient_name );
		if ( ! is_object( $rollback_themes ) ) {
			$this->theme_updates_list();
			$rollback_themes = get_site_transient( $this->transient_name );
		}

		$theme_slug = isset( $theme->template ) ? $theme->template : '';

		if ( empty( $theme_slug ) || ! array_key_exists( $theme_slug, $rollback_themes->response ) ) {
			return $actions;
		}

		if ( ! $theme->get( 'Version' ) ) {
			return $actions;
		}

		$rollback_url = add_query_arg( array(
			'page'            => urlencode( $this->option_id ),
			'type'            => urlencode( 'theme' ),
			'theme_file'      => urlencode( $theme_slug ),
			'current_version' => urlencode( $theme->get( 'Version' ) ),
			'rollback_name'   => urlencode( $theme->get( 'Name' ) ),
			'_wpnonce'        => wp_create_nonce( $this->user_nonce ),
		), 'admin.php' );

		$actions[$this->option_id] = '<a href="' . esc_url( $rollback_url ) . '">' . __( 'Rollback', 'wpmastertoolkit' ) . '</a>';
		
		return $actions;
	}

	/**
	 * Get theme updates list
	 * 
	 * @since    1.10.0
	 */
	public function theme_updates_list() {
		include ABSPATH . WPINC . '/version.php';

		if ( defined( 'WP_INSTALLING' ) || ! is_admin() ) {
			return false;
		}

		$expiration       = 12 * HOUR_IN_SECONDS;
		$installed_themes = wp_get_themes();
		$last_update      = get_site_transient( 'update_themes' );

		if ( ! is_object( $last_update ) ) {
			set_site_transient( $this->transient_name, time(), $expiration );
		}

		$themes = $checked = $request = array();
		$request['active'] = get_option( 'stylesheet' );

		foreach ( $installed_themes as $theme ) {
			$checked[ $theme->get_stylesheet() ] = $theme->get( 'Version' );
			$themes[ $theme->get_stylesheet() ]  = array(
				'Name'       => $theme->get( 'Name' ),
				'Title'      => $theme->get( 'Name' ),
				'Version'    => '0.0.0.0.0.0',
				'Author'     => $theme->get( 'Author' ),
				'Author URI' => $theme->get( 'AuthorURI' ),
				'Template'   => $theme->get_template(),
				'Stylesheet' => $theme->get_stylesheet(),
			);
		}

		$request['themes'] = $themes;
		$timeout = 3 + (int) ( count( $themes ) / 10 );

		global $wp_version;

		$options = array(
			'timeout'    => $timeout,
			'body'       => array( 'themes' => json_encode( $request ) ),
			'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' ),
		);

		$http_url = 'http://api.wordpress.org/themes/update-check/1.1/';
		if ( $ssl = wp_http_supports( array( 'ssl' ) ) ) {
			$url = set_url_scheme( $http_url, 'https' );
		}

		$raw_response = wp_remote_post( $url, $options );
		if ( $ssl && is_wp_error( $raw_response ) ) {
			//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
			trigger_error( __( 'An unexpected error occurred. Something may be wrong with WordPress.org or this server&#8217;s configuration. If you continue to have problems, please try the <a href="https://wordpress.org/support/">support forums</a>.', 'wpmastertoolkit' ) . ' ' . __( '(WordPress could not establish a secure connection to WordPress.org. Please contact your server administrator.)', 'wpmastertoolkit' ), headers_sent() || WP_DEBUG ? E_USER_WARNING : E_USER_NOTICE );
			$raw_response = wp_remote_post( $http_url, $options );
		}

		set_site_transient( $this->transient_name, time(), $expiration );
		if ( is_wp_error( $raw_response ) || 200 != wp_remote_retrieve_response_code( $raw_response ) ) {
			return false;
		}

		$new_update               = new stdClass();
		$new_update->last_checked = time();
		$new_update->checked      = $checked;
		$response                 = json_decode( wp_remote_retrieve_body( $raw_response ), true );
		if ( is_array( $response ) && isset( $response['themes'] ) ) {
			$new_update->response = $response['themes'];
		}
		set_site_transient( $this->transient_name, $new_update );

		return true;
	}

	/**
	 * Prepare themes js
	 * 
	 * @since    1.10.0
	 */
	public function prepare_themes_js( $prepared_themes ) {
		$themes    = array();
		$rollbacks = array();
		$wp_themes = get_site_transient( $this->transient_name );

		if ( empty( $wp_themes ) || ! is_object( $wp_themes ) ) {
			$this->theme_updates_list();
			$wp_themes = get_site_transient( $this->transient_name );
		}

		if ( is_object( $wp_themes ) ) {
			$rollbacks = $wp_themes->response;
		}

		foreach ( $prepared_themes as $key => $value ) {
			$themes[ $key ]                = $prepared_themes[ $key ];
			$themes[ $key ]['hasRollback'] = isset( $rollbacks[ $key ] );
		}

		return $themes;
	}

	/**
	 * Rollback admin menu
	 * 
	 * @since    1.10.0
	 */
	public function rollback_admin_menu() {
		//phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['page'] ) && $_GET['page'] == $this->option_id ) {
			$current_url = sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );

			//phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET['type'] ) && $_GET['type'] == "plugin" || isset( $_GET['plugin_file'] ) ) {
				$current_url = home_url() . "/wp-admin/plugins.php";
				//phpcs:ignore WordPress.Security.NonceVerification.Recommended
			} else if ( isset( $_GET['type'] ) && $_GET['type'] == "theme" || isset( $_GET['theme_file'] ) ) {
				$current_url =  home_url()."/wp-admin/themes.php";
			} else {
				$current_url =  "#";
			}

			WPMastertoolkit_Settings::add_submenu_page(
            'wp-mastertoolkit-settings',
				$this->header_title,
				'<a href=' . $current_url . '>' . $this->header_title . '</a>',
				'manage_options',
				$this->option_id,
				array( $this, 'render_submenu'),
				null
			);
		}
	}

	/**
     * Render the submenu
     * 
	 * @since    1.10.0
     */
    public function render_submenu() {

        $submenu_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/plugin-theme-rollback.asset.php' );
        wp_enqueue_style( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/plugin-theme-rollback.css', array(), $submenu_assets['version'], 'all' );
        wp_enqueue_script( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/plugin-theme-rollback.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );

        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
        $this->submenu_content();
        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/footer.php';
    }

	/**
	 * Setup Variables
	 * 
	 * @since    1.10.0
	 */
	private function setup_plugin_vars() {
		$this->set_plugin_slug();

		$wpext_tags = $this->wpext_svn_tags( 'plugin', $this->set_plugin_slug() );
		$this->set_svn_versions_data( $wpext_tags );
	}

	/**
	 * Set Plugin Slug
	 *
	 * @since    1.10.0
	 */
	private function set_plugin_slug() {

		//phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['plugin_file'] ) ) {
			return false;
		}

		include_once ABSPATH . 'wp-admin/includes/plugin.php';

		//phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$plugin_file = WP_PLUGIN_DIR . '/' . sanitize_text_field( wp_unslash( $_GET['plugin_file'] ?? '' ) );

		if ( ! file_exists( $plugin_file ) ) {
			wp_die( esc_html__( 'Plugin you\'re referencing does not exist.', 'wpmastertoolkit' ) );
		}

		$plugin_slug = explode( '/', plugin_basename( $plugin_file ) );
		$plugin_slug = $plugin_slug[0];

		return $plugin_slug;
	}

	/**
	 * Construct the URL for the plugin API info
	 * 
	 * @since    1.10.0
	 */
	private function wpext_svn_tags( $type, $slug ) {
		$response = '';
		$url      = '';

		//phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['page'] ) && $_GET['page'] == $this->option_id && isset( $_GET['type'] ) ) {
			//phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( 'plugin' === $_GET['type'] && ! empty( $_GET['page'] ) && 'theme' != $_GET['type'] ) {
				$url      = 'https://api.wordpress.org/plugins/info/1.0/' . $this->set_plugin_slug() . '.json';
				$response = wp_remote_get( $url );
				//phpcs:ignore WordPress.Security.NonceVerification.Recommended
			} elseif ( 'theme' === $_GET['type'] ) {
				$url      = 'https://themes.svn.wordpress.org/' . $slug;
				$response = wp_remote_get( $url );
			}

			if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
				return null;
			}

			return wp_remote_retrieve_body( $response );
		}
	}

	/**
	 * Set Plugin and Theme Versions
	 * 
	 * @since    1.10.0
	 */
	private function set_svn_versions_data( $html ) {
		global $wpmtk_versions;

		if ( ! $html ) {
			return false;
		}

		if ( ( $json = json_decode( $html ) ) && ( $html != $json ) ) {
			$wpmtk_versions = array_keys( (array) $json->versions );
		} else {
			$obj = new DOMDocument();
			$obj->loadHTML( $html );
			$wpmtk_versions = array();
			$items    = $obj->getElementsByTagName( 'a' );

			foreach ( $items as $item ) {
				$href = str_replace( '/', '', $item->getAttribute( 'href' ) );

				if ( strpos( $href, 'http' ) === false && '..' !== $href ) {
					$wpmtk_versions[] = $href;
				}
			}
		}

		$wpmtk_versions = array_reverse( $wpmtk_versions );
		return $wpmtk_versions;
	}

	/**
     * Add the submenu content
     *
	 * @since    1.10.0
     */
    private function submenu_content() {

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), $this->user_nonce ) ) {
			return;
		}

		// Get the necessary class
		include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$defaults = array(
			'page'           => $this->option_id,
			'plugin_file'    => '',
			'action'         => '',
			'plugin_version' => '',
			'plugin'         => '',
		);

		$args      = wp_parse_args( $_GET, $defaults );
		$error_msg = '';

		if ( ! empty( $args['plugin_version'] ) ) {
			if ( empty( $args['plugin_file'] ) || ! file_exists( WP_PLUGIN_DIR . '/' . $args['plugin_file'] ) ) {
				$error_msg = __( 'Necessary parameters are missing. Please try again.', 'wpmastertoolkit' );
			} else {
				include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/helpers/core/plugin-theme-rollback/class-rollback-plugin.php';

				$title    = sanitize_text_field( $args['rollback_name'] ?? '' );
				$nonce    = 'upgrade-plugin_' . $this->set_plugin_slug();
				$url      = 'admin.php?page=' . $this->option_id . '&plugin_file=' . esc_url( $args['plugin_file'] ) . 'action=upgrade-plugin';
				$plugin   = $this->set_plugin_slug();
				$version  = sanitize_text_field( $args['plugin_version'] );
				$upgrader = new WPMastertoolkit_Plugin_Theme_Rollback_Plugin( new Plugin_Upgrader_Skin( compact( 'title', 'nonce', 'url', 'plugin', 'version' ) ) );
				$upgrader->wpmastertoolkit_rollback_module( plugin_basename( sanitize_text_field( $args['plugin_file'] ) ) );
			}
		} elseif ( ! empty( $args['theme_version'] ) ) {
			if ( empty( $args['theme_file'] ) || ! file_exists( WP_CONTENT_DIR . '/themes/' . $args['theme_file'] ) ) {
				$error_msg = __( 'Necessary parameters are missing. Please try again.', 'wpmastertoolkit' );
			} else {
				include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/helpers/core/plugin-theme-rollback/class-rollback-theme.php';

				$title    = sanitize_text_field( $args['rollback_name'] ?? '' );
				$nonce    = 'upgrade-theme_' . $args['theme_file'];
				$url      = 'admin.php?page=' . $this->option_id . '&theme_file=' . esc_url( $args['theme_file'] ) . 'action=upgrade-theme';
				$version  = $args['theme_version'];
				$theme    = $args['theme_file'];
				$upgrader = new WPMastertoolkit_Plugin_Theme_Rollback_Theme( new Theme_Upgrader_Skin( compact( 'title', 'nonce', 'url', 'theme', 'version' ) ) );
				$upgrader->wpmastertoolkit_rollback_module( $args['theme_file'] );
			}
		} else {
			if ( ( ! isset( $_GET['type'] ) && ! isset( $_GET['theme_file'] ) )
				|| ( ! isset( $_GET['type'] ) && ! isset( $_GET['plugin_file'] ) ) ) {
				$error_msg = __( 'Necessary parameters are missing. Please try again.', 'wpmastertoolkit' );
			} else {
				$this->show_menu( $args );
			}
		}

		if ( ! empty( $error_msg ) ) {
			?>
				<div class="wp-mastertoolkit__section">
					<div class="wp-mastertoolkit__section__desc"><?php echo esc_html( $error_msg ); ?></div>
				</div>
			<?php
		}
    }

	/**
	 * Show the menu
	 * 
	 * @since    1.10.0
	 */
	private function show_menu( $args ) {

		//phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$type            = sanitize_text_field( wp_unslash( $_GET['type'] ?? '' ) );
		$theme_rollback  = $type == 'theme' ? true : false;
		$plugin_rollback = $type == 'plugin' ? true : false;
		$plugins         = get_plugins();

		//phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $args['plugin_file'] ) && in_array( $args['plugin_file'], array_keys( $plugins ) ) ) {
			$wpmtk_versions = $this->versions_select( 'plugin', $args );
			//phpcs:ignore WordPress.Security.NonceVerification.Recommended
		} elseif ( $theme_rollback == true && isset( $_GET['theme_file'] ) ) {
			//phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$theme_file = sanitize_text_field( wp_unslash( $_GET['theme_file'] ) );
			$svn_tags = $this->wpext_svn_tags( 'theme', $theme_file );
			$this->set_svn_versions_data( $svn_tags );
			$wpmtk_versions = $this->versions_select( 'theme', $args );
		}

		?>
			<div class="wp-mastertoolkit__section">
				<div class="wp-mastertoolkit__section__desc"></div>
				<div class="wp-mastertoolkit__section__body">
					<form name="check_for_rollbacks" class="rollback-form" action="<?php echo esc_url( admin_url( '/admin.php' ) ); ?>">
						<input type="hidden" name="page" value="<?php echo esc_attr( $this->option_id ); ?>">
					
						<div class="wp-mastertoolkit__section__body__item">
							<div class="wp-mastertoolkit__section__body__item__title">
								<?php
								if ( $theme_rollback ) {
									esc_html_e( 'Theme Name', 'wpmastertoolkit' );
								} elseif ( $plugin_rollback ) {
									esc_html_e( 'Plugin Name', 'wpmastertoolkit' );
								}
								?>
							</div>
							<div class="wp-mastertoolkit__section__body__item__content">
								<div class="description">
									<?php echo esc_html( $args['rollback_name'] ); ?>
								</div>
							</div>
						</div>

						<div class="wp-mastertoolkit__section__body__item">
							<div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Current Version', 'wpmastertoolkit' ); ?></div>
							<div class="wp-mastertoolkit__section__body__item__content">
								<div class="description">
									<?php echo esc_html( $args['current_version'] ); ?>
								</div>
							</div>
						</div>

						<div class="wp-mastertoolkit__section__body__item">
							<div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'New Version', 'wpmastertoolkit' ); ?></div>
							<div class="wp-mastertoolkit__section__body__item__content">
								<?php
								//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo $wpmtk_versions;
								?>
							</div>
						</div>

						<div class="wp-mastertoolkit__section__body__item">
							<div class="wp-mastertoolkit__section__body__item__content">
								<div class="wp-mastertoolkit__button">
									<button type="submit"><?php esc_html_e( 'Rollback', 'wpmastertoolkit' ); ?></button>
								</div>
								<div class="description"><?php  esc_html_e( 'We strongly recommend you create a complete backup, before you take this action.', 'wpmastertoolkit' ); ?></div>

								<?php if ( $plugin_rollback == true ): ?>
									<input type="hidden" name="plugin_file" value="<?php echo esc_attr( $args['plugin_file'] ); ?>">
									 <input type="hidden" name="plugin_slug" value="<?php echo esc_attr( $args['plugin_slug'] ); ?>">
								<?php else: ?>
									<input type="hidden" name="theme_file" value="<?php echo esc_attr( $args['theme_file'] ); ?>">
								<?php endif; ?>
								<input type="hidden" name="rollback_name" value="<?php echo esc_attr( $args['rollback_name'] ); ?>">
								<input type="hidden" name="installed_version" value="<?php echo esc_attr( $args['current_version'] ); ?>">
								<?php wp_nonce_field( $this->user_nonce ); ?>
							</div>
						</div>
					</form>

				</div>
			</div>
		<?php
	}

	/**
	 * Getting the plugins theme version
	 * 
	 * @since    1.10.0
	 */
	private function versions_select( $type, $args ) {
		global $wpmtk_versions;

		if ( empty( $wpmtk_versions ) ) {
			return '<div class="description">' . sprintf(
				/* translators: %s: type */
				__( 'It appears there are no version to select. This is likely due to the %s author not using tags for their versions and only committing new releases to the repository trunk.', 'wpmastertoolkit' ), 
				$type 
			) . '</div>';
		}

		usort( $wpmtk_versions, 'version_compare' );
		$wpmtk_versions = array_reverse( $wpmtk_versions );

		$versions_html = '<div class="wp-mastertoolkit__select">';
		$versions_html .= '<select name="' . esc_attr( $type . '_version' ) .'">';
		foreach ( $wpmtk_versions as $version ) {
			$versions_html .= '<option value="' . esc_attr( $version ) . '" ' . disabled( $args['current_version'], $version, false ) . '>';
			$versions_html .= esc_html( $version );
			$versions_html .= '</option>';
		}
		$versions_html .= '</select>';
		$versions_html .= '</div>';
		$versions_html .= '<div class="description">' . sprintf( 
			/* translators: %s: type */
			__( 'Select the %s version you want to rollback to.', 'wpmastertoolkit' ), 
			$type 
		) . '</div>';
		
		return $versions_html;
	}
}
