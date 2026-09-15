<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Social Login
 * Description: Let users log in or register with Google, Facebook, and more.
 * @since 2.21.0
 */
class WPMastertoolkit_Social_Login {

	const PROVIDER_KEY_ARG = 'wpmtk_social_login_provider';
	const ERROR_KEY_ARG    = 'wpmtk_social_login_error';
	const REDIRECT_URL_ARG = 'wpmtk_social_login_redirect';

	public $option_id;
	public $providers = array(
		'core/social-login/google.php',
		'pro/social-login/facebook.php',
		'pro/social-login/twitter.php',
		'pro/social-login/apple.php',
		'pro/social-login/wordpress.php',
		'pro/social-login/microsoft.php',
		'pro/social-login/linkedin.php',
		'pro/social-login/github.php',
	);

    private $nonce_action;
    private $settings;
    private $default_settings;
    private $header_title;
	private $users_table;

	/**
	 * Invoke the hooks
	 * 
	 * @since	2.21.0
	 */
	public function __construct() {
        $this->option_id    = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_social_login';
        $this->nonce_action = $this->option_id . '_action';
		$this->users_table  = $this->option_id . '_users';

		$this->require_providers();

        add_action( 'init', array( $this, 'class_init' ) );
        add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
        add_action( 'admin_init', array( $this, 'save_submenu' ) );

		add_action( 'login_enqueue_scripts', array( $this, 'enqueue_styles_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_front_styles_scripts' ) );
        add_action( 'init', array( $this, 'redirect_to_provider' ) );
        add_action( 'init', array( $this, 'maybe_get_user_profile' ) );
        add_action( 'login_form',    array( $this, 'render_login_buttons' ) );
        add_action( 'register_form', array( $this, 'render_register_buttons' ) );
		add_action( 'woocommerce_login_form', array( $this, 'render_woocommerce_login_buttons' ) );
		add_action( 'woocommerce_register_form', array( $this, 'render_woocommerce_register_buttons' ) );
        add_action( 'delete_user', array( $this, 'delete_user_social_links' ) );
		add_filter( 'wp_login_errors', array( $this, 'inject_social_login_error' ), 10, 2 );
		add_filter( 'pre_get_avatar_data', array( $this, 'pre_get_avatar_data' ), 1, 2 );
		add_filter( 'post_mime_types', array( $this, 'add_post_mime_type_avatar' ) );
		add_filter( 'ajax_query_attachments_args', array( $this, 'modify_query_attachments_args' ) );
    }

	/**
	 * Require providers files
	 * 
	 * @since    2.21.0
	 */
	public function require_providers() {
		foreach ( $this->providers as $provider ) {
			$path = WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/helpers/' . $provider;
			if ( is_readable( $path ) ) {
				include_once $path;
			}
		}
	}

	/**
	 * Initialize the class
	 * 
	 * @since	2.21.0
	 */
	public function class_init() {
		$this->header_title = esc_html__( 'Social Login', 'wpmastertoolkit' );
	}

	/**
	 * Add a submenu
	 * 
	 * @since	2.21.0
	 */
	public function add_submenu() {
		WPMastertoolkit_Settings::add_submenu_page(
			'wp-mastertoolkit-settings',
			$this->header_title,
			$this->header_title,
			'manage_options',
			'wp-mastertoolkit-settings-social-login',
			array( $this, 'render_submenu' )
		);
	}

	/**
	 * Render the submenu
	 * 
	 * @since	2.21.0
	 */
	public function render_submenu() {
		$submenu_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/social-login.asset.php' );
		wp_enqueue_style( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/social-login.css', array(), $submenu_assets['version'], 'all' );
		wp_enqueue_script( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/social-login.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );

		include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
		$this->submenu_content();
		include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/footer.php';
	}

	/**
	 * Save the submenu option
	 * 
	 * @since	2.21.0
	 */
	public function save_submenu() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
		if ( wp_verify_nonce( $nonce, $this->nonce_action ) ) {
			//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$new_settings = $this->sanitize_settings( wp_unslash( $_POST[ $this->option_id ] ?? array() ) );
			$this->save_settings( $new_settings );

			$selected_provider = sanitize_text_field( wp_unslash( $_POST['wpmtk_selected_provider'] ?? '' ) );
			$redirect_args     = array(
				'page' => 'wp-mastertoolkit-settings-social-login',
			);

			if ( ! empty( $selected_provider ) ) {
				$redirect_args['provider'] = $selected_provider;
			}

			$redirect_url = add_query_arg(
				$redirect_args,
				admin_url( 'admin.php' )
			);

			wp_safe_redirect( $redirect_url );
			exit;
		}
	}

	/**
	 * sanitize_settings
	 * 
	 * @since	2.21.0
	 */
	public function sanitize_settings( $new_settings ){
		$this->settings         = $this->get_settings();
		$this->default_settings = $this->get_default_settings();
		$sanitized_settings     = array();

		foreach ( $this->default_settings as $settings_key => $settings_value ) {
			if ( 'providers' === $settings_key ) {

				foreach ( $settings_value as $provider_key => $provider ) {
					if ( ! isset( $provider['params'] ) ) {
						continue;
					}

					$params = $provider['params'];
					foreach ( $params as $param_key => $param_value ) {
						$param_type = $param_value['type'];

						switch ( $param_type ) {
							case 'db':
								$sanitized_settings[ $settings_key ][ $provider_key ]['params'][ $param_key ]['value'] = sanitize_text_field( $this->settings['providers'][ $provider_key ]['params'][ $param_key ]['value'] ?? '' );
							break;
							case 'html':
								$sanitized_settings[ $settings_key ][ $provider_key ]['params'][ $param_key ]['value'] = wp_kses_post( $new_settings[ $settings_key ][ $provider_key ]['params'][ $param_key ] ?? $param_value['value'] );
							break;
							case 'text':
							case 'password':
							case 'checkbox':
								$sanitized_settings[ $settings_key ][ $provider_key ]['params'][ $param_key ]['value'] = sanitize_text_field( $new_settings[ $settings_key ][ $provider_key ]['params'][ $param_key ] ?? $param_value['value'] );
							break;
							case 'select':
								$new_value = $new_settings[ $settings_key ][ $provider_key ]['params'][ $param_key ]['value'] ?? $param_value['value']['value'];
								if ( array_key_exists( $new_value, $param_value['value']['options'] ) ) {
									$sanitized_value = $new_value;
								} else {
									$sanitized_value = $param_value['value']['value'];
								}
								$sanitized_settings[ $settings_key ][ $provider_key ]['params'][ $param_key ]['value']['value'] = $sanitized_value;
							break;
						}
					}
				}
			} else {
				$param_type = $settings_value['type'];

				switch ( $param_type ) {
					case 'text':
					case 'password':
					case 'checkbox':
						$sanitized_settings[ $settings_key ]['value'] = sanitize_text_field( $new_settings[ $settings_key ]['value'] ?? $settings_value['value'] );
					break;
					case 'select':
						$new_value = $new_settings[ $settings_key ]['value']['value'] ?? $settings_value['value']['value'];
						if ( array_key_exists( $new_value, $settings_value['value']['options'] ) ) {
							$sanitized_value = $new_value;
						} else {
							$sanitized_value = $settings_value['value']['value'];
						}
						$sanitized_settings[ $settings_key ]['value']['value'] = $sanitized_value;
					break;
				}
			}
		}

		return $sanitized_settings;
	}

	/**
	 * Save settings
	 * 
	 * @since	2.21.0
	 */
	public function save_settings( $new_settings ) {
		update_option( $this->option_id, $new_settings );
	}

	/**
	 * Enqueue styles and scripts
	 */
	public function enqueue_styles_scripts() {
		$this->settings		    = $this->get_settings();
		$this->default_settings = $this->get_default_settings();

		$form_layout = $this->settings['form_layout']['value']['value'] ?? $this->default_settings['form_layout']['value']['value'] ?? '';

		$submenu_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/social-login-front.asset.php' );
		wp_enqueue_style( 'WPMastertoolkit_social_login', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/social-login-front.css', array(), $submenu_assets['version'], 'all' );
		wp_enqueue_script( 'WPMastertoolkit_social_login', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/social-login-front.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );
		wp_localize_script( 'WPMastertoolkit_social_login', 'wpmtk_social_login', array(
			'formLayout' => $form_layout,
		) );
	}

	/**
	 * Enqueue front assets for WooCommerce My Account forms.
	 */
	public function maybe_enqueue_front_styles_scripts() {
		if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
			return;
		}

		$this->enqueue_styles_scripts();
	}

	/**
	 * Redirect to the provider for authentication.
	 * 
	 * @since	2.21.0
	 */
	public function redirect_to_provider() {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET[ self::PROVIDER_KEY_ARG ] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$provider_key = sanitize_key( wp_unslash( $_GET[ self::PROVIDER_KEY_ARG ] ) );

		$this->settings         = $this->get_settings();
		$this->default_settings = $this->get_default_settings();

		$provider = $this->settings['providers'][$provider_key] ?? array();
		if ( empty( $provider ) ) {
			return;
		}

		$enabled = $provider['params']['enabled']['value'] ?? '';
		if ( '1' !== $enabled ) {
			return;
		}

		$class_name = 'WPMastertoolkit_Social_Login_' . ucfirst( $provider_key );
		if ( ! method_exists( $class_name, 'redirect_to_provider' ) ) {
			return;
		}

		// Before redirect save a transient to save the provider.
		$state = wp_generate_uuid4();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$redirect_url = esc_url_raw( wp_unslash( $_GET[ self::REDIRECT_URL_ARG ] ?? '' ) );
		$redirect_url = wp_validate_redirect( $redirect_url, '' );
		set_transient( 'wpmtk_oauth_provider_' . $state, $provider_key, MINUTE_IN_SECONDS * 10 );
		if ( ! empty( $redirect_url ) ) {
			set_transient( 'wpmtk_oauth_redirect_' . $state, $redirect_url, MINUTE_IN_SECONDS * 10 );
		}
		setcookie( 'wpmtk_oauth_provider', $state, time() + ( MINUTE_IN_SECONDS * 10 ), COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
			
		$redirect = call_user_func( array( $class_name, 'redirect_to_provider' ), $this );
		if ( is_wp_error( $redirect ) ){
			$this->redirect_to_login_with_error( $redirect->get_error_message() );
		}
	}

	/**
	 * Maybe get the user profile after OAuth callback
	 * 
	 * @since	2.21.0
	 */
	public function maybe_get_user_profile() {

		if ( ! is_login() ) {
			return;
		}

		$state = sanitize_key( wp_unslash( $_COOKIE['wpmtk_oauth_provider'] ?? '' ) );
		if ( empty( $state ) ) {
			return;
		}
		setcookie( 'wpmtk_oauth_provider', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );

		$provider_key = get_transient( 'wpmtk_oauth_provider_' . $state );
		$redirect_url = get_transient( 'wpmtk_oauth_redirect_' . $state );
		if ( empty( $provider_key ) ) {
			return;
		}
		delete_transient( 'wpmtk_oauth_provider_' . $state );
		delete_transient( 'wpmtk_oauth_redirect_' . $state );

		$this->settings         = $this->get_settings();
		$this->default_settings = $this->get_default_settings();

		$provider = $this->settings['providers'][$provider_key] ?? array();
		if ( empty( $provider ) ) {
			return;
		}

		$enabled = $provider['params']['enabled']['value'] ?? '';
		if ( '1' !== $enabled ) {
			return;
		}

		$class_name = 'WPMastertoolkit_Social_Login_' . ucfirst( $provider_key );
		if ( ! method_exists( $class_name, 'get_user_profile' ) ) {
			return;
		}
			
		$user_profile = call_user_func( array( $class_name, 'get_user_profile' ), $this );
		if ( $user_profile === false ) {
			return;
		}

		if ( is_wp_error( $user_profile ) ) {
			$this->redirect_to_login_with_error( $user_profile->get_error_message() );
		}

		$user_id = $this->get_or_create_user( $provider, $provider_key, $user_profile );
		if ( is_wp_error( $user_id ) ){
			$this->redirect_to_login_with_error( $user_id->get_error_message() );
		}

		$this->update_avatar( $provider, $provider_key, $user_id, $user_profile );

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );
		$user = get_userdata( $user_id );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		do_action( 'wp_login', $user->user_login, $user );

		wp_safe_redirect( $this->get_login_success_redirect_url( $user, $redirect_url ) );
		exit;
	}

	/**
	 * Render the login buttons on the login and registration forms
	 * 
	 * @since	2.21.0
	 */
	public function render_login_buttons() {
		$this->render_login_register_buttons( 'login' );
	}

	/**
	 * Render the register buttons on the registration form
	 * 
	 * @since	2.21.0
	 */
	public function render_register_buttons() {
		$this->render_login_register_buttons( 'register' );
	}

	/**
	 * Render the login buttons on WooCommerce login form.
	 */
	public function render_woocommerce_login_buttons() {
		$this->render_login_register_buttons( 'login', 'woocommerce' );
	}

	/**
	 * Render the register buttons on WooCommerce register form.
	 */
	public function render_woocommerce_register_buttons() {
		$this->render_login_register_buttons( 'register', 'woocommerce' );
	}

	/**
	 * Render the login or register buttons depending on the type.
	 *
	 * @since	2.21.0
	 */
	public function render_login_register_buttons( $type = 'login', $context = 'wordpress' ) {

		$this->settings         = $this->get_settings();
		$this->default_settings = $this->get_default_settings();

		$buttons_style = $this->settings['buttons_style']['value']['value'] ?? $this->default_settings['buttons_style']['value']['value'] ?? '';
		$buttons_align = $this->settings['buttons_align']['value']['value'] ?? $this->default_settings['buttons_align']['value']['value'] ?? '';
		$form_layout   = $this->settings['form_layout']['value']['value'] ?? $this->default_settings['form_layout']['value']['value'] ?? '';

		$providers         = $this->settings['providers'];
		$redirect_back_url = $this->get_login_redirect_back_url( $context );
		?><div class="wpmtk-social-login <?php echo esc_attr( $form_layout ); ?>"><?php
		if ( 'below-separator' === $form_layout ) {
			?>
			<div class="wpmtk-social-login__separator">
				<div class="wpmtk-social-login__separator__text"><?php esc_html_e( 'or', 'wpmastertoolkit' ); ?></div>
			</div>
			<?php
		}
		?><div class="wpmtk-social-login__items <?php echo esc_attr( $buttons_style ); ?> <?php echo esc_attr( $buttons_align ); ?>"><?php

		foreach ( $providers as $provider_key => $provider ) {

			$enabled = $provider['params']['enabled']['value'] ?? '';
			if ( '1' !== $enabled ) {
				continue;
			}

			$params         = $this->settings['providers'][$provider_key]['params'] ?? '';
			$default_params = $this->default_settings['providers'][$provider_key]['params'] ?? '';
			$login_label    = $params['login_label']['value'] ?? $default_params['login_label']['value'] ?? '';
			$register_label = $params['register_label']['value'] ?? $default_params['register_label']['value'] ?? '';
			$button_skin    = $this->settings['providers'][$provider_key]['params']['button_skin']['value']['value'] ?? $this->default_settings['providers'][$provider_key]['params']['button_skin']['value']['value'] ?? '';
			$colors         = $this->default_settings['providers'][$provider_key]["color_$button_skin"] ?? array();
			$wordpress      = $params['wordpress']['value'] ?? $default_params['wordpress']['value'] ?? '';
			$woocommerce    = $params['woocommerce']['value'] ?? $default_params['woocommerce']['value'] ?? '';

			if ( 'wordpress' === $context && '1' !== $wordpress ) {
				continue;
			}

			if ( 'woocommerce' === $context && '1' !== $woocommerce ) {
				continue;
			}

			$class_name = 'WPMastertoolkit_Social_Login_' . ucfirst( $provider_key );
			if ( ! method_exists( $class_name, 'can_show_login_button' ) ) {
				continue;
			}
				
			$can_show_login_button = call_user_func( array( $class_name, 'can_show_login_button' ), $params, $default_params );
			if ( ! $can_show_login_button ) {
				continue;
			}

			$button_text = $login_label;
			if ( 'register' === $type ) {
				$button_text = $register_label;
			}

			$redirect_args = array(
				self::PROVIDER_KEY_ARG => $provider_key,
			);
			if ( ! empty( $redirect_back_url ) ) {
				$redirect_args[ self::REDIRECT_URL_ARG ] = $redirect_back_url;
			}

			$redirect_url = add_query_arg( $redirect_args, site_url( 'wp-login.php' ) );

			$this->render_login_register_button( $provider_key, $redirect_url, $button_text, $colors );
		}
		?></div><?php
		if ( 'above-separator' === $form_layout ) {
			?>
			<div class="wpmtk-social-login__separator">
				<div class="wpmtk-social-login__separator__text"><?php esc_html_e( 'or', 'wpmastertoolkit' ); ?></div>
			</div>
			<?php
		}
		?></div><?php
	}

	/**
	 * Build the redirect URL to use after a successful social login.
	 *
	 * @param WP_User $user Logged-in user.
	 * @param string  $preferred_url Optional redirect URL coming from login context.
	 */
	private function get_login_success_redirect_url( $user, $preferred_url = '' ) {
		$validated_preferred_url = wp_validate_redirect( $preferred_url, '' );
		if ( ! empty( $validated_preferred_url ) ) {
			return $validated_preferred_url;
		}

		if ( $user instanceof WP_User && user_can( $user, 'manage_options' ) ) {
			return admin_url();
		}

		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$my_account_url = wc_get_page_permalink( 'myaccount' );
			if ( ! empty( $my_account_url ) ) {
				return $my_account_url;
			}
		}

		return home_url( '/' );
	}

	/**
	 * Get the redirect target attached to social login buttons.
	 */
	private function get_login_redirect_back_url( $context ) {
		if ( 'woocommerce' !== $context ) {
			return '';
		}

		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$my_account_url = wc_get_page_permalink( 'myaccount' );
			if ( ! empty( $my_account_url ) ) {
				return $my_account_url;
			}
		}

		return home_url( '/' );
	}

	/**
	 * Render the login or register button.
	 */
	public function render_login_register_button( $provider_key, $redirect_url, $button_text, $colors ) {
		$buttons_style = $this->settings['buttons_style']['value']['value'] ?? $this->default_settings['buttons_style']['value']['value'] ?? '';

		?>
		<div class="wpmtk-social-login__item wpmtk-<?php echo esc_attr( $provider_key ); ?>">
			<a href="<?php echo esc_attr( $redirect_url ); ?>" class="wpmtk-social-login__item__btn" style="background-color: <?php echo esc_attr( $colors['bg'] ?? '' ); ?>; color: <?php echo esc_attr( $colors['text'] ?? '' ); ?>; border-color: <?php echo esc_attr( $colors['border'] ?? '' ); ?>; <?php echo $redirect_url == '#' ? 'pointer-events: none;' : ''; ?>">
				<span class="wpmtk-social-login__item__btn__icon" style="color: <?php echo esc_attr( $colors['icon'] ?? '' ); ?>;">
					<?php echo wp_kses( file_get_contents(WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/social-login/' . $provider_key . '.svg'), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
				</span>
				<?php if ( 'icon' !== $buttons_style ) : ?>
					<span class="wpmtk-social-login__item__btn__text"><?php echo wp_kses_post( $button_text ); ?></span>
				<?php endif; ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Delete social links when a user is deleted
	 * 
	 * @since	2.21.0
	 */
	public function delete_user_social_links( $user_id ) {
		global $blog_id, $wpdb;

		$table_social_users = $wpdb->prefix . $this->users_table;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $table_social_users, array( 'user_id' => $user_id ), array( '%d' ) );

		$avatar_meta_keys      = $this->get_avatar_meta_keys( $blog_id, $wpdb );
		$avatar_attachment_id  = $this->get_user_avatar_attachment_id( $user_id, $avatar_meta_keys );
		$avatar_attachment_md5 = get_user_meta( $user_id, 'wpmtk_user_avatar_md5', true );

		if ( $avatar_attachment_id && $avatar_attachment_md5 ) {

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$provider_id = $wpdb->get_var( $wpdb->prepare( "SELECT provider FROM %i WHERE user_id = %d LIMIT 1", $table_social_users, $user_id ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$identifier  = $wpdb->get_var( $wpdb->prepare( "SELECT identifier FROM %i WHERE user_id = %d LIMIT 1", $table_social_users, $user_id ) );

			$attachment_post_meta = get_post_meta( $avatar_attachment_id, $provider_id . '_avatar', true );
            if ( $attachment_post_meta && $attachment_post_meta === $identifier ) {
                $this->delete_avatar_data( $avatar_attachment_id, $user_id );
            }
		}
	}

	/**
	 * Inject social login error into the login errors
	 *
	 * @since	2.21.0
	 */
	public function inject_social_login_error( $errors, $redirect_to ) {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$error_key = sanitize_key( wp_unslash( $_GET[self::ERROR_KEY_ARG] ?? '' ) );
		if ( empty( $error_key ) ) {
			return $errors;
		}

		$message = get_transient( $error_key );
		if ( ! empty( $message ) ) {
			delete_transient( $error_key ); // one-time display
			$errors->add( 'wpmtk_social_login', esc_html( $message ) );
		}

		return $errors;
	}

	/**
	 * Add a filter to get the avatar from the user meta if it exists
	 * 
	* @since	2.21.0
	*/
	public function pre_get_avatar_data( $args, $id_or_email ) {
		global $blog_id, $wpdb;

		$id = $this->get_user_id_by_id_or_email( $id_or_email );
		if ( $id == 0 || ( isset( $args['force_default'] ) && boolval( $args['force_default'] ) === true ) ) {
			return $args;
		}

		$avatar_meta_keys = $this->get_avatar_meta_keys( $blog_id, $wpdb );
		$attachment_id    = $this->get_user_avatar_attachment_id( $id, $avatar_meta_keys );

		if ( wp_attachment_is_image( $attachment_id ) ) {
			$image_src_array = wp_get_attachment_image_src( $attachment_id );

			if ( isset( $args['size'] ) ) {
				$get_size = is_numeric( $args['size'] ) ? array(
					$args['size'],
					$args['size']
				) : $args['size'];
				$image_src_array = wp_get_attachment_image_src( $attachment_id, $get_size );
			}

			$args['url'] = $image_src_array[0];
		}

		return $args;
	}

	/**
	 * Add the avatar post mime type
	 *
	 * @since	2.21.0
	 */
	public function add_post_mime_type_avatar( $types ) {
		$types['avatar'] = array(
			__('Avatar', 'wpmastertoolkit'),
			__('Manage Avatar', 'wpmastertoolkit'),
			// translators: %s is the number of avatars.
			_n_noop('Avatar <span class="count">(%s)</span>', 'Avatar <span class="count">(%s)</span>', 'wpmastertoolkit')
		);

		return $types;
	}

	/**
	 * Modify the query attachments args to include the avatar post mime type
	 * 
	 * @since	2.21.0
	 */
	public function modify_query_attachments_args( $query ) {

		if ( ! isset( $query['meta_query'] ) || ! is_array( $query['meta_query'] ) ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            $query['meta_query'] = array();
        }

        if ( isset( $query['post_mime_type'] ) && $query['post_mime_type'] === 'avatar') {
            $query['post_mime_type']         = 'image';
            $query['meta_query']['relation'] = 'AND';
            $query['meta_query'][]           = array(
                'key'     => '_wp_attachment_wp_user_avatar',
                'compare' => 'EXISTS'
            );
        } else {
			$query['meta_query']['relation'] = 'AND';
			$query['meta_query'][]           = array(
				'key'     => '_wp_attachment_wp_user_avatar',
				'compare' => 'NOT EXISTS'
			);
        }

        return $query;
	}

	/**
	 * Get the user id by id or email
	 * 
	 * @since	2.21.0
	 */
	public function get_user_id_by_id_or_email( $id_or_email ) {
		$id = 0;

		if ( is_numeric( $id_or_email ) ) {
			$id = $id_or_email;
		} else if ( is_string( $id_or_email ) ) {
			$user = get_user_by( 'email', $id_or_email );
			if ( $user ) {
				$id = $user->ID;
			}
		} else if ( is_object( $id_or_email ) ) {
			if ( ! empty( $id_or_email->comment_author_email ) ) {
				$user = get_user_by( 'email', $id_or_email->comment_author_email );
				if ( $user ) {
					$id = $user->ID;
				}
			} else if ( ! empty( $id_or_email->user_id ) ) {
				$id = $id_or_email->user_id;
			}
		}

		return $id;
	}

	/**
	 * Get the settings or default settings if not set
	 *
	 * @since	2.21.0
	 */
	public function get_settings(){
		if( $this->settings !== null ) return $this->settings;

		$this->default_settings = $this->get_default_settings();
		$settings = get_option( $this->option_id, $this->default_settings );

		return $settings;
	}

	/**
	 * Get the default settings
	 *
	 * @since	2.21.0
	 */
	public function get_default_settings(){
		if ( $this->default_settings !== null ) return $this->default_settings;

		$all_rolles    = function_exists( 'get_editable_roles' ) ? get_editable_roles() : array();
		$roles_options = array();
		foreach ( $all_rolles as $role_key => $role ) {
			$roles_options[ $role_key ] = $role['name'];
		}

		return array(
			'providers' => array(
				'google' => array(
					'pro'         => false,
					'color_light' => array(
						'bg'     => '#ffffff',
						'text'   => '#000000',
						'border' => '#000000',
						'icon'   => '',
					),
					'color_dark' => array(
						'bg'     => '#000000',
						'text'   => '#ffffff',
						'border' => '#000000',
						'icon'   => '',
					),
					'color_neutral' => array(
						'bg'     => '#f2f2f2',
						'text'   => '#000000',
						'border' => '#f2f2f2',
						'icon'   => '',
					),
					'params' => array(
						'enabled' => array(
							'type'  => 'checkbox',
							'value' => '0',
						),
						'client_id' => array(
							'type'  => 'text',
							'value' => '',
						),
						'client_secret' => array(
							'type'  => 'password',
							'value' => '',
						),
						'select_account' => array(
							'type'  => 'checkbox',
							'value' => '0',
						),
						'login_label' => array(
							'type'  => 'html',
							'value' => __( 'Sign in with <b>Google</b>', 'wpmastertoolkit' ),
						),
						'register_label' => array(
							'type'  => 'html',
							'value' => __( 'Sign up with <b>Google</b>', 'wpmastertoolkit' ),
						),
						'button_skin' => array(
							'type'  => 'select',
							'value' => array(
								'value'   => 'light',
								'options' => array(
									'light'   => __( 'Light', 'wpmastertoolkit' ),
									'dark'    => __( 'Dark', 'wpmastertoolkit' ),
									'neutral' => __( 'Neutral', 'wpmastertoolkit' ),
								),
							),
						),
						'user_prefix' => array(
							'type'  => 'text',
							'value' => '',
						),
						'wordpress' => array(
							'type'  => 'checkbox',
							'value' => '1',
						),
						'woocommerce' => array(
							'type'  => 'checkbox',
							'value' => '1',
						),
						'image_size' => array(
							'type'  => 'select',
							'value' => array(
								'value'   => 'default',
								'options' => array(
									'small'       => __( '50x50', 'wpmastertoolkit' ),
									'default'     => __( '96x96', 'wpmastertoolkit' ),
									'medium'      => __( '360x360', 'wpmastertoolkit' ),
									'large'       => __( '480x480', 'wpmastertoolkit' ),
									'extra_large' => __( '720x720', 'wpmastertoolkit' ),
									'original'    => __( 'Original', 'wpmastertoolkit' ),
								),
							),
						),
						'register_role' => array(
							'type'  => 'select',
							'value' => array(
								'value'   => 'subscriber',
								'options' => $roles_options,
							),
						),
					),
				),
				'facebook' => array(
					'pro'        => true,
					'icon_color' => '#1877f2',
					'color_light' => array(
						'bg'     => '#ffffff',
						'text'   => '#000000',
						'border' => '#000000',
						'icon'   => '#1877f2',
					),
					'color_dark' => array(
						'bg'     => '#000000',
						'text'   => '#ffffff',
						'border' => '#000000',
						'icon'   => '#ffffff',
					),
					'color_neutral' => array(
						'bg'     => '#1877f2',
						'text'   => '#ffffff',
						'border' => '#1877f2',
						'icon'   => '#ffffff',
					),
					'params'     => array(
						'enabled' => array(
							'type'  => 'checkbox',
							'value' => '0',
						),
						'app_id' => array(
							'type'  => 'text',
							'value' => '',
						),
						'app_secret' => array(
							'type'  => 'password',
							'value' => '',
						),
						'login_label' => array(
							'type'  => 'html',
							'value' => __( 'Sign in with <b>Facebook</b>', 'wpmastertoolkit' ),
						),
						'register_label' => array(
							'type'  => 'html',
							'value' => __( 'Sign up with <b>Facebook</b>', 'wpmastertoolkit' ),
						),
						'button_skin' => array(
							'type'  => 'select',
							'value' => array(
								'value'   => 'light',
								'options' => array(
									'light'   => __( 'Light', 'wpmastertoolkit' ),
									'dark'    => __( 'Dark', 'wpmastertoolkit' ),
									'neutral' => __( 'Neutral', 'wpmastertoolkit' ),
								),
							),
						),
						'user_prefix' => array(
							'type'  => 'text',
							'value' => '',
						),
						'wordpress' => array(
							'type'  => 'checkbox',
							'value' => '1',
						),
						'woocommerce' => array(
							'type'  => 'checkbox',
							'value' => '1',
						),
						'image_size' => array(
							'type'  => 'select',
							'value' => array(
								'value'   => 'default',
								'options' => array(
									'50'      => __( '50x50', 'wpmastertoolkit' ),
									'100'     => __( '100x100', 'wpmastertoolkit' ),
									'default' => __( 'Default', 'wpmastertoolkit' ),
									'480'     => __( '480x480', 'wpmastertoolkit' ),
									'720'     => __( '720x720', 'wpmastertoolkit' ),
								),
							),
						),
						'register_role' => array(
							'type'  => 'select',
							'value' => array(
								'value'   => 'subscriber',
								'options' => $roles_options,
							),
						),
					),
				),
				'apple' => array(
					'pro'        => true,
					'icon_color' => '#000000',
					'color_light' => array(
						'bg'     => '#ffffff',
						'text'   => '#000000',
						'border' => '#000000',
						'icon'   => '#000000',
					),
					'color_dark' => array(
						'bg'     => '#000000',
						'text'   => '#ffffff',
						'border' => '#000000',
						'icon'   => '#ffffff',
					),
					'color_neutral' => array(
						'bg'     => '#f2f2f2',
						'text'   => '#000000',
						'border' => '#f2f2f2',
						'icon'   => '#000000',
					),
					'params'     => array(
						'enabled' => array(
							'type'  => 'checkbox',
							'value' => '0',
						),
						'client_id' => array(
							'type'  => 'text',
							'value' => '',
						),
						'team_id' => array(
							'type'  => 'text',
							'value' => '',
						),
						'key_id' => array(
							'type'  => 'text',
							'value' => '',
						),
						'private_key' => array(
							'type'  => 'html',
							'value' => '',
						),
						'login_label' => array(
							'type'  => 'html',
							'value' => __( 'Sign in with <b>Apple</b>', 'wpmastertoolkit' ),
						),
						'register_label' => array(
							'type'  => 'html',
							'value' => __( 'Sign up with <b>Apple</b>', 'wpmastertoolkit' ),
						),
						'button_skin' => array(
							'type'  => 'select',
							'value' => array(
								'value'   => 'light',
								'options' => array(
									'light'   => __( 'Light', 'wpmastertoolkit' ),
									'dark'    => __( 'Dark', 'wpmastertoolkit' ),
									'neutral' => __( 'Neutral', 'wpmastertoolkit' ),
								),
							),
						),
						'user_prefix' => array(
							'type'  => 'text',
							'value' => '',
						),
						'wordpress' => array(
							'type'  => 'checkbox',
							'value' => '1',
						),
						'woocommerce' => array(
							'type'  => 'checkbox',
							'value' => '1',
						),
						'register_role' => array(
							'type'  => 'select',
							'value' => array(
								'value'   => 'subscriber',
								'options' => $roles_options,
							),
						),
					),
				),
				'wordpress' => array(
					'pro'        => true,
					'icon_color' => '#000000',
					'color_light' => array(
						'bg'     => '#ffffff',
						'text'   => '#000000',
						'border' => '#000000',
						'icon'   => '#000000',
					),
					'color_dark' => array(
						'bg'     => '#000000',
						'text'   => '#ffffff',
						'border' => '#000000',
						'icon'   => '#ffffff',
					),
					'color_neutral' => array(
						'bg'     => '#3858e9',
						'text'   => '#ffffff',
						'border' => '#3858e9',
						'icon'   => '#ffffff',
					),
					'params'     => array(
						'enabled' => array(
							'type'  => 'checkbox',
							'value' => '0',
						),
						'client_id' => array(
							'type'  => 'text',
							'value' => '',
						),
						'client_secret' => array(
							'type'  => 'password',
							'value' => '',
						),
						'login_label' => array(
							'type'  => 'html',
							'value' => __( 'Sign in with <b>WordPress</b>', 'wpmastertoolkit' ),
						),
						'register_label' => array(
							'type'  => 'html',
							'value' => __( 'Sign up with <b>WordPress</b>', 'wpmastertoolkit' ),
						),
						'button_skin' => array(
							'type'  => 'select',
							'value' => array(
								'value'   => 'light',
								'options' => array(
									'light'   => __( 'Light', 'wpmastertoolkit' ),
									'dark'    => __( 'Dark', 'wpmastertoolkit' ),
									'neutral' => __( 'Neutral', 'wpmastertoolkit' ),
								),
							),
						),
						'user_prefix' => array(
							'type'  => 'text',
							'value' => '',
						),
						'wordpress' => array(
							'type'  => 'checkbox',
							'value' => '1',
						),
						'woocommerce' => array(
							'type'  => 'checkbox',
							'value' => '1',
						),
						'image_size' => array(
							'type'  => 'select',
							'value' => array(
								'value'   => 'normal',
								'options' => array(
									'mini'     => __( '24x24', 'wpmastertoolkit' ),
									'normal'   => __( '48x48', 'wpmastertoolkit' ),
									'bigger'   => __( '96x96', 'wpmastertoolkit' ),
									'original' => __( 'Original', 'wpmastertoolkit' ),
								),
							),
						),
						'register_role' => array(
							'type'  => 'select',
							'value' => array(
								'value'   => 'subscriber',
								'options' => $roles_options,
							),
						),
					),
				),
				'twitter' => array(
					'pro'         => true,
					'icon_color' => '#000000',
					'color_light' => array(
						'bg'     => '#ffffff',
						'text'   => '#000000',
						'border' => '#000000',
						'icon'   => '#000000',
					),
					'color_dark' => array(
						'bg'     => '#141414',
						'text'   => '#ffffff',
						'border' => '#141414',
						'icon'   => '#ffffff',
					),
					'color_neutral' => array(
						'bg'     => '#f2f2f2',
						'text'   => '#000000',
						'border' => '#f2f2f2',
						'icon'   => '#000000',
					),
					'params'     => array(
						'enabled' => array(
							'type'  => 'checkbox',
							'value' => '0',
						),
						'api_version' => array(
							'type'  => 'select',
							'value' => array(
								'value'   => '1',
								'options' => array(
									'1' => __( 'API v1.1', 'wpmastertoolkit' ),
									'2' => __( 'API v2', 'wpmastertoolkit' ),
								),
							),
						),
						'consumer_key' => array(
							'type'  => 'text',
							'value' => '',
						),
						'consumer_secret' => array(
							'type'  => 'password',
							'value' => '',
						),
						'client_id' => array(
							'type'  => 'text',
							'value' => '',
						),
						'client_secret' => array(
							'type'  => 'password',
							'value' => '',
						),
						'login_label' => array(
							'type'  => 'html',
							'value' => __( 'Sign in with <b>X</b>', 'wpmastertoolkit' ),
						),
						'register_label' => array(
							'type'  => 'html',
							'value' => __( 'Sign up with <b>X</b>', 'wpmastertoolkit' ),
						),
						'button_skin' => array(
							'type'  => 'select',
							'value' => array(
								'value'   => 'light',
								'options' => array(
									'light'   => __( 'Light', 'wpmastertoolkit' ),
									'dark'    => __( 'Dark', 'wpmastertoolkit' ),
									'neutral' => __( 'Neutral', 'wpmastertoolkit' ),
								),
							),
						),
						'user_prefix' => array(
							'type'  => 'text',
							'value' => '',
						),
						'wordpress' => array(
							'type'  => 'checkbox',
							'value' => '1',
						),
						'woocommerce' => array(
							'type'  => 'checkbox',
							'value' => '1',
						),
						'image_size' => array(
							'type'  => 'select',
							'value' => array(
								'value'   => 'normal',
								'options' => array(
									'mini'     => __( '24x24', 'wpmastertoolkit' ),
									'normal'   => __( '48x48', 'wpmastertoolkit' ),
									'bigger'   => __( '73x73', 'wpmastertoolkit' ),
									'original' => __( 'Original', 'wpmastertoolkit' ),
								),
							),
						),
						'register_role' => array(
							'type'  => 'select',
							'value' => array(
								'value'   => 'subscriber',
								'options' => $roles_options,
							),
						),
					),
				),
				'microsoft' => array(
					'pro'        => true,
					'icon_color' => '#0078d4',
					'color_light' => array(
						'bg'     => '#ffffff',
						'text'   => '#000000',
						'border' => '#000000',
						'icon'   => '#0078d4',
					),
					'color_dark' => array(
						'bg'     => '#000000',
						'text'   => '#ffffff',
						'border' => '#000000',
						'icon'   => '#ffffff',
					),
					'color_neutral' => array(
						'bg'     => '#0078d4',
						'text'   => '#ffffff',
						'border' => '#0078d4',
						'icon'   => '#ffffff',
					),
					'params'     => array(
						'enabled' => array(
							'type'  => 'checkbox',
							'value' => '0',
						),
						'tenant' => array(
							'type'  => 'text',
							'value' => '',
						),
						'client_id' => array(
							'type'  => 'text',
							'value' => '',
						),
						'client_secret' => array(
							'type'  => 'password',
							'value' => '',
						),
						'login_label' => array(
							'type'  => 'html',
							'value' => __( 'Sign in with <b>Microsoft</b>', 'wpmastertoolkit' ),
						),
						'register_label' => array(
							'type'  => 'html',
							'value' => __( 'Sign up with <b>Microsoft</b>', 'wpmastertoolkit' ),
						),
						'button_skin' => array(
							'type'  => 'select',
							'value' => array(
								'value'   => 'light',
								'options' => array(
									'light'   => __( 'Light', 'wpmastertoolkit' ),
									'dark'    => __( 'Dark', 'wpmastertoolkit' ),
									'neutral' => __( 'Neutral', 'wpmastertoolkit' ),
								),
							),
						),
						'user_prefix' => array(
							'type'  => 'text',
							'value' => '',
						),
						'wordpress' => array(
							'type'  => 'checkbox',
							'value' => '1',
						),
						'woocommerce' => array(
							'type'  => 'checkbox',
							'value' => '1',
						),
						'image_size' => array(
							'type'  => 'select',
							'value' => array(
								'value'   => 'normal',
								'options' => array(
									'mini'     => __( '48x48', 'wpmastertoolkit' ),
									'normal'   => __( '64x64', 'wpmastertoolkit' ),
									'bigger'   => __( '120x120', 'wpmastertoolkit' ),
									'large'    => __( '240x240', 'wpmastertoolkit' ),
									'original' => __( 'Original', 'wpmastertoolkit' ),
								),
							),
						),
						'register_role' => array(
							'type'  => 'select',
							'value' => array(
								'value'   => 'subscriber',
								'options' => $roles_options,
							),
						),
					),
				),
				'linkedin' => array(
					'pro'        => true,
					'icon_color' => '#0077b5',
					'color_light' => array(
						'bg'     => '#ffffff',
						'text'   => '#000000',
						'border' => '#000000',
						'icon'   => '#0077b5',
					),
					'color_dark' => array(
						'bg'     => '#000000',
						'text'   => '#ffffff',
						'border' => '#000000',
						'icon'   => '#ffffff',
					),
					'color_neutral' => array(
						'bg'     => '#0a66c2',
						'text'   => '#ffffff',
						'border' => '#0a66c2',
						'icon'   => '#ffffff',
					),
					'params'     => array(
						'enabled' => array(
							'type'  => 'checkbox',
							'value' => '0',
						),
						'client_id' => array(
							'type'  => 'text',
							'value' => '',
						),
						'client_secret' => array(
							'type'  => 'password',
							'value' => '',
						),
						'login_label' => array(
							'type'  => 'html',
							'value' => __( 'Sign in with <b>LinkedIn</b>', 'wpmastertoolkit' ),
						),
						'register_label' => array(
							'type'  => 'html',
							'value' => __( 'Sign up with <b>LinkedIn</b>', 'wpmastertoolkit' ),
						),
						'button_skin' => array(
							'type'  => 'select',
							'value' => array(
								'value'   => 'light',
								'options' => array(
									'light'   => __( 'Light', 'wpmastertoolkit' ),
									'dark'    => __( 'Dark', 'wpmastertoolkit' ),
									'neutral' => __( 'Neutral', 'wpmastertoolkit' ),
								),
							),
						),
						'user_prefix' => array(
							'type'  => 'text',
							'value' => '',
						),
						'wordpress' => array(
							'type'  => 'checkbox',
							'value' => '1',
						),
						'woocommerce' => array(
							'type'  => 'checkbox',
							'value' => '1',
						),
						'register_role' => array(
							'type'  => 'select',
							'value' => array(
								'value'   => 'subscriber',
								'options' => $roles_options,
							),
						),
					),
				),
				'github' => array(
					'pro'        => true,
					'icon_color' => '#000000',
					'color_light' => array(
						'bg'     => '#ffffff',
						'text'   => '#000000',
						'border' => '#000000',
						'icon'   => '#000000',
					),
					'color_dark' => array(
						'bg'     => '#24292f',
						'text'   => '#ffffff',
						'border' => '#24292f',
						'icon'   => '#ffffff',
					),
					'color_neutral' => array(
						'bg'     => '#f6f8fa',
						'text'   => '#24292f',
						'border' => '#d0d7de',
						'icon'   => '#24292f',
					),
					'params'     => array(
						'enabled' => array(
							'type'  => 'checkbox',
							'value' => '0',
						),
						'client_id' => array(
							'type'  => 'text',
							'value' => '',
						),
						'client_secret' => array(
							'type'  => 'password',
							'value' => '',
						),
						'login_label' => array(
							'type'  => 'html',
							'value' => __( 'Sign in with <b>GitHub</b>', 'wpmastertoolkit' ),
						),
						'register_label' => array(
							'type'  => 'html',
							'value' => __( 'Sign up with <b>GitHub</b>', 'wpmastertoolkit' ),
						),
						'button_skin' => array(
							'type'  => 'select',
							'value' => array(
								'value'   => 'light',
								'options' => array(
									'light'   => __( 'Light', 'wpmastertoolkit' ),
									'dark'    => __( 'Dark', 'wpmastertoolkit' ),
									'neutral' => __( 'Neutral', 'wpmastertoolkit' ),
								),
							),
						),
						'user_prefix' => array(
							'type'  => 'text',
							'value' => '',
						),
						'wordpress' => array(
							'type'  => 'checkbox',
							'value' => '1',
						),
						'woocommerce' => array(
							'type'  => 'checkbox',
							'value' => '1',
						),
						'image_size' => array(
							'type'  => 'select',
							'value' => array(
								'value'   => 'normal',
								'options' => array(
									'mini'     => __( '24x24', 'wpmastertoolkit' ),
									'normal'   => __( '48x48', 'wpmastertoolkit' ),
									'bigger'   => __( '96x96', 'wpmastertoolkit' ),
									'large'    => __( '192x192', 'wpmastertoolkit' ),
									'original' => __( 'Original', 'wpmastertoolkit' ),
								),
							),
						),
						'register_role' => array(
							'type'  => 'select',
							'value' => array(
								'value'   => 'subscriber',
								'options' => $roles_options,
							),
						),
					),
				),
			),
			'form_layout' => array(
				'type'  => 'select',
				'value' => array(
					'value'   => 'below',
					'options' => array(
						'below'           => __( 'Below', 'wpmastertoolkit' ),
						'below-separator' => __( 'Below with Separator', 'wpmastertoolkit' ),
						'below-floating'  => __( 'Below and Floating', 'wpmastertoolkit' ),
						'above'           => __( 'Above', 'wpmastertoolkit' ),
						'above-separator' => __( 'Above with Separator', 'wpmastertoolkit' ),
						'above-floating'  => __( 'Above and Floating', 'wpmastertoolkit' ),
					),
				),
			),
			'buttons_style' => array(
				'type'  => 'select',
				'value' => array(
					'value'   => 'fullwidth',
					'options' => array(
						'fullwidth'  => __( 'Full Width', 'wpmastertoolkit' ),
						'fitcontent' => __( 'Fit Content', 'wpmastertoolkit' ),
						'icon'       => __( 'Only Icon', 'wpmastertoolkit' ),
					),
				)
			),
			'buttons_align' => array(
				'type'  => 'select',
				'value' => array(
					'value'   => 'left',
					'options' => array(
						'left'   => __( 'Left', 'wpmastertoolkit' ),
						'center' => __( 'Center', 'wpmastertoolkit' ),
						'right'  => __( 'Right', 'wpmastertoolkit' ),
					),
				)
			),
		);
	}

	/**
	 * Redirect to the login page with an error message
	 * 
	 * @since	2.21.0
	 */
	private function redirect_to_login_with_error( $message ) {
		$error_key = self::ERROR_KEY_ARG . '_' . wp_generate_uuid4();

		set_transient(
			$error_key,
			sanitize_text_field( $message ),
			5 * MINUTE_IN_SECONDS
		);

		wp_safe_redirect(
			add_query_arg(
				array( self::ERROR_KEY_ARG => $error_key ),
				wp_login_url()
			)
		);
		exit;
	}

	/**
	 * Find an existing user or create a new one based on the social profile
	 * 
	 * @since	2.21.0
	 */
	private function get_or_create_user( $provider, $provider_key, $user_profile ) {
		global $wpdb;

		$this->maybe_create_tables();

		$table_name = $wpdb->prefix . $this->users_table;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$user_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT user_id FROM %i WHERE provider = %s AND identifier = %s LIMIT 1",
			$table_name,
			$provider_key,
			$user_profile['id']
		) );

		if ( $user_id ) {
			return (int) $user_id;
		}

		// Existing WP user with same email?
		if ( ! empty( $user_profile['email'] ) ) {

			$existing = get_user_by( 'email', $user_profile['email'] );
			if ( $existing ) {

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->insert( $table_name, array(
					'user_id'    => $existing->ID,
					'provider'   => $provider_key,
					'identifier' => $user_profile['id'],
					'linked_at'  => current_time( 'mysql' ),
				) );

				return $existing->ID;
			}
		}

		// Registration allowed?
		if ( ! get_option( 'users_can_register' ) ) {
			return new WP_Error( 'registration_disabled', __( 'Registration is currently disabled.', 'wpmastertoolkit' ) );
		}

		// Create new user
		$prefix    = $provider['params']['user_prefix']['value'] ?? '';
		$user_role = $provider['params']['register_role']['value']['value'] ?? '';
		$username  = sanitize_user( $prefix . strtolower( $user_profile['first_name'] ) . strtolower( $user_profile['last_name'] ) );
		if ( username_exists( $username ) ) {
			$username .= '_' . wp_generate_password( 4, false );
		}

		$new_id = wp_create_user( $username, wp_generate_password( 24 ), $user_profile['email'] );
		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}

		$user_update_args = array(
			'ID'           => $new_id,
			'display_name' => sanitize_text_field( $user_profile['first_name'] . ' ' . $user_profile['last_name'] ),
			'first_name'   => sanitize_text_field( $user_profile['first_name'] ),
			'last_name'    => sanitize_text_field( $user_profile['last_name'] ),
		);

		if ( ! empty( $user_role ) ) {
			$user_update_args['role'] = $user_role;
		}

		wp_update_user( $user_update_args );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert( $table_name, array(
			'user_id'    => $new_id,
			'provider'   => $provider_key,
			'identifier' => $user_profile['id'],
			'linked_at'  => current_time( 'mysql' ),
		) );

		return $new_id;
	}

	/**
	 * Update the user's avatar based on the social profile.
	 * 
	 * @since	2.21.0
	 */
	private function update_avatar( $provider, $provider_key, $user_id, $user_profile ) {
		global $blog_id, $wpdb;

		$avatar_meta_keys = $this->get_avatar_meta_keys( $blog_id, $wpdb );

		$picture_url       = $user_profile['picture'] ?? '';
		$picture_temp_path = $user_profile['picture_temp_path'] ?? '';

		if ( empty( $picture_url ) && empty( $picture_temp_path ) ) {
			return;
		}

		/**
		 * $original_attachment_id is false, if the user has had avatar set but the path is not found.
		 */
		$original_attachment_id  = $this->get_user_avatar_attachment_id( $user_id, $avatar_meta_keys );
		$original_attachment_md5 = false;
		if ( $original_attachment_id ) {
			$attached_file = get_attached_file( $original_attachment_id );
			if ( ( $attached_file && ! file_exists( $attached_file ) ) || ! $attached_file ) {
				if ( $attached_file && ! file_exists( $attached_file ) ) {
					$this->delete_avatar_data( $original_attachment_id, $user_id );
				}

				$original_attachment_id = false;
			} else {
				/**
				 * We should only get the md5 value of the image, if there is an existing attachment, indeed.
				 */
				$original_attachment_md5 = get_user_meta( $user_id, 'wpmtk_user_avatar_md5', true );
			}
		}

		/**
		 * Overwrite the original attachment if avatar was set and the provider attachment exits.
		*/
		$overwrite_attachment = false;
		if ( $original_attachment_id && get_post_meta( $original_attachment_id, $provider_key . '_avatar', true ) ) {
			$overwrite_attachment = true;
		}

		if ( ! $original_attachment_id ) {
			/**
			 * If the <preffix>user_avatar user meta was deleted, but the attachment stored by the provider still exists,
			 * then we should restore the user meta and attempt to use that attachment as the original attachment.
			 */
			$args = array(
				'post_type'      => 'attachment',
				'post_status'    => array( 'inherit', 'private' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'     => array(
					array(
						'key'   => $provider_key . '_avatar',
						'value' => $user_profile['id'],
					)
				)
			);
			$posts = get_posts( $args );

			if ( ! empty( $posts ) ) {

				$original_attachment_id = $posts[0];
				$overwrite_attachment   = true;
				$this->update_user_avatar_meta( $user_id, $avatar_meta_keys, $original_attachment_id );

				$attached_file = get_attached_file( $original_attachment_id );
				if ( $attached_file && file_exists( $attached_file ) ) {
					/**
					 * The user has an avatar stored by the provider, so we should get the stored md5 value of the file, too!
					 */
					$original_attachment_md5 = get_user_meta( $user_id, 'wpmtk_user_avatar_md5', true );
				}
			}
		}

		/**
		 * If there was no original avatar or overwrite mode is on, download the avatar of the selected provider.*
		 */
		if ( ! $original_attachment_id || $overwrite_attachment === true ) {
			require_once( ABSPATH . '/wp-admin/includes/file.php' );

			if ( ! empty( $picture_temp_path ) && is_string( $picture_temp_path ) && is_readable( $picture_temp_path ) ) {
				$avatar_temp_path = $picture_temp_path;
			} else {
				$avatar_temp_path = download_url( $picture_url );
			}

			if ( ! is_wp_error( $avatar_temp_path ) ) {

				$mime        = wp_get_image_mime( $avatar_temp_path );
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
				$mime_to_ext = apply_filters( 'getimagesize_mimes_to_exts', array(
					'image/jpeg' => 'jpg',
					'image/png'  => 'png',
					'image/gif'  => 'gif',
					'image/bmp'  => 'bmp',
					'image/tiff' => 'tif',
					'image/webp' => 'webp'
				) );

				/**
				 * If the uploaded image has extension from the mime type and it is appear in the $mime_to_ext.
				 * Make a unique filename, depending on the extension.
				 * Copy the downloaded file with the new name to the uploads path.
				 * Unlink the downloaded file.
				 */
				if ( isset( $mime_to_ext[$mime] ) ) {

					$wp_upload_dir         = wp_upload_dir();
					$wpmtk_upload_dir_name = 'wpmtk_avatars';
					$wpmtk_upload_dir      = trailingslashit( $wp_upload_dir['basedir'] ) . $wpmtk_upload_dir_name;

					if ( wp_mkdir_p( $wpmtk_upload_dir ) ) {

						$filename        = wp_hash( uniqid( $user_id . '-' ) ) . '.' . $mime_to_ext[$mime];
                        $filename        = wp_unique_filename( $wpmtk_upload_dir, $filename );
                        $new_avatar_path = trailingslashit( $wpmtk_upload_dir ) . $filename;
                        $new_avatar_MD5  = md5_file( $avatar_temp_path );

						if ( $overwrite_attachment ) {
							// we got the same image, so we do not want to store it
							if ( $original_attachment_md5 === $new_avatar_MD5 ) {
								wp_delete_file( $avatar_temp_path );
							} else {
								// Store the new avatar
                                $new_file = @copy( $avatar_temp_path, $new_avatar_path );
								wp_delete_file( $avatar_temp_path );

								if ( false !== $new_file ) {
									//and remove the old one
									$original_avatar_image = get_attached_file( $original_attachment_id );
									wp_delete_file( $original_avatar_image );

									foreach ( get_intermediate_image_sizes() as $size ) {
										/**
										 * Delete the previous Avatar sub-sizes to avoid orphan images
										 */
										$original_avatar_subsize = image_get_intermediate_size( $original_attachment_id, $size );
										if ( isset( $original_avatar_subsize['path'] ) ) {
											$original_avatar_subsize_path = trailingslashit( $wp_upload_dir['basedir'] ) . $original_avatar_subsize['path'];
											if ( file_exists( $original_avatar_subsize_path ) ) {
												wp_delete_file( $original_avatar_subsize_path );
											}
										}
									}

									update_attached_file( $original_attachment_id, $new_avatar_path );

									// Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
									require_once( ABSPATH . 'wp-admin/includes/image.php' );
									wp_update_attachment_metadata( $original_attachment_id, wp_generate_attachment_metadata( $original_attachment_id, $new_avatar_path ) );

									$this->update_user_avatar_meta( $user_id, $avatar_meta_keys, $original_attachment_id );
									update_user_meta( $user_id, 'wpmtk_user_avatar_md5', $new_avatar_MD5 );
								}
							}
						} else {
							// Store the avatar
							$new_file = @copy( $avatar_temp_path, $new_avatar_path );
							wp_delete_file( $avatar_temp_path );

							if ( false !== $new_file ) {
                                $url = $wp_upload_dir['baseurl'] . '/' . $wpmtk_upload_dir_name . '/' . basename( $filename );

								$attachment = array(
									'guid'           => $url,
									'post_mime_type' => $mime,
									'post_title'     => '',
									'post_content'   => '',
									'post_status'    => 'private',
									'post_author'    => $user_id
								);

								$new_attachment_id = wp_insert_attachment( $attachment, $new_avatar_path );
								if ( ! is_wp_error( $new_attachment_id ) ) {

									// Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
									require_once( ABSPATH . 'wp-admin/includes/image.php' );
									wp_update_attachment_metadata( $new_attachment_id, wp_generate_attachment_metadata( $new_attachment_id, $new_avatar_path ) );

									update_post_meta( $new_attachment_id, $provider_key . '_avatar', $user_profile['id'] );
									update_post_meta( $new_attachment_id, '_wp_attachment_wp_user_avatar', $user_id );

									$this->update_user_avatar_meta( $user_id, $avatar_meta_keys, $new_attachment_id );
									update_user_meta( $user_id, 'wpmtk_user_avatar_md5', $new_avatar_MD5 );
								}
							}
						}
					}
				}
			}

			if ( is_string( $avatar_temp_path ) && file_exists( $avatar_temp_path ) ) {
				wp_delete_file( $avatar_temp_path );
			}
		}
	}

	/**
	 * Delete the avatar data for a user.
	 * 
	 * @since	2.21.0
	 */
	private function delete_avatar_data( $post_id, $user_id ) {
		global $blog_id, $wpdb;

		$avatar_meta_keys = $this->get_avatar_meta_keys( $blog_id, $wpdb );

        if ( wp_delete_post( $post_id, true ) ) {
            foreach ( $avatar_meta_keys as $meta_key ) {
				delete_user_meta( $user_id, $meta_key );
			}
            delete_user_meta( $user_id, 'wpmtk_user_avatar_md5' );
        }
	}

	/**
	 * Get user meta keys used for avatar attachment IDs.
	 *
	 * @since	2.21.0
	 */
	private function get_avatar_meta_keys( $blog_id, $wpdb ) {
		return array(
			$wpdb->get_blog_prefix( $blog_id ) . 'user_avatar',
			WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_local_avatars_user_avatar',
		);
	}

	/**
	 * Get the first valid avatar attachment ID from known meta keys.
	 *
	 * @since	2.21.0
	 */
	private function get_user_avatar_attachment_id( $user_id, $avatar_meta_keys ) {
		foreach ( $avatar_meta_keys as $meta_key ) {
			$avatar_id = (int) get_user_meta( $user_id, $meta_key, true );
			if ( $avatar_id > 0 ) {
				return $avatar_id;
			}
		}

		return false;
	}

	/**
	 * Keep avatar attachment ID synchronized across all supported meta keys.
	 *
	 * @since	2.21.0
	 */
	private function update_user_avatar_meta( $user_id, $avatar_meta_keys, $avatar_id ) {
		$avatar_id = (int) $avatar_id;
		if ( $avatar_id <= 0 ) {
			return;
		}

		foreach ( $avatar_meta_keys as $meta_key ) {
			update_user_meta( $user_id, $meta_key, $avatar_id );
		}
	}

	/**
	 * Maybe create database tables.
	 * 
	 * @since   2.21.0
	 */
	private function maybe_create_tables() {
		global $wpdb;

		$table_social_users = $wpdb->prefix . $this->users_table;

		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$table_social_users_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_social_users ) ) === $table_social_users;

		if ( ! $table_social_users_exists ) {
			$this->create_tables();
		}
	}

	/**
	 * Create database tables
	 * 
	 * @since   2.21.0
	 */
	private function create_tables() {
		global $wpdb;

		$table_social_users = $wpdb->prefix . $this->users_table;
		$charset_collate    = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table_social_users} (
			id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id    BIGINT UNSIGNED NOT NULL,
			provider   VARCHAR(30)     NOT NULL,
			identifier VARCHAR(255)    NOT NULL,
			linked_at  DATETIME        DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY provider_identifier (provider, identifier),
			KEY user_id (user_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Add the submenu content
	 * 
	 * @since	2.21.0
	 */
	private function submenu_content() {
		$this->settings         = $this->get_settings();
		$this->default_settings = $this->get_default_settings();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$selected_provider     = sanitize_text_field( wp_unslash( $_GET['provider'] ?? '' ) );
		$providers             = $this->default_settings['providers'];
		$form_layout_options   = $this->default_settings['form_layout']['value']['options'];
		$form_layout	       = $this->settings['form_layout']['value']['value'] ?? $this->default_settings['form_layout']['value']['value'] ?? '';
		$buttons_align_options = $this->default_settings['buttons_align']['value']['options'];
		$buttons_align         = $this->settings['buttons_align']['value']['value'] ?? $this->default_settings['buttons_align']['value']['value'] ?? '';
		$buttons_style_options = $this->default_settings['buttons_style']['value']['options'];
		$buttons_style		   = $this->settings['buttons_style']['value']['value'] ?? $this->default_settings['buttons_style']['value']['value'] ?? '';

		$first_provider_key     = array_key_first( $providers );
		$selected_provider      = empty( $selected_provider ) ? $first_provider_key : $selected_provider;
		$has_pro_subscription   = wpmastertoolkit_is_pro();
		$providers_available    = array();
		$providers_unavailable  = array();
		$providers_comming_soon = array();

		foreach ( $providers as $provider_key => $provider ) {
			if ( isset( $provider['comming_soon'] ) ) {
				$providers_comming_soon[$provider_key] = $provider;
			} elseif ( $provider['pro'] && ! $has_pro_subscription ) {
				$providers_unavailable[$provider_key] = $provider;
			} else {
				$providers_available[$provider_key] = $provider;
			}
		}

		?>
			<div class="wp-mastertoolkit__sections__wrapper">
				<div class="wp-mastertoolkit__section providers">
					<div class="wp-mastertoolkit__section__body">

						<div class="wp-mastertoolkit__section__body__item">
							<div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Providers', 'wpmastertoolkit' ); ?></div>
							<div class="wp-mastertoolkit__section__body__item__content">
								<div class="wp-mastertoolkit__providers">
								<?php
								foreach ( $providers_available as $provider_key => $provider ) {
									$checked = $provider_key === $selected_provider;
									$this->render_single_provider( $provider_key, $provider, $checked );
								}
								?>
								</div>
							</div>
						</div>

						<?php if ( ! empty( $providers_unavailable ) ): ?>
						<div class="wp-mastertoolkit__section__body__item">
							<div class="wp-mastertoolkit__section__body__item__title">
								<?php esc_html_e( 'Providers', 'wpmastertoolkit' ); ?>
								<span class="wp-mastertoolkit__section__body__item__title__tag pro"><?php esc_html_e( 'PRO', 'wpmastertoolkit' ); ?></span>
							</div>
							<div class="wp-mastertoolkit__section__body__item__content">
								<div class="wp-mastertoolkit__providers">
								<?php
								foreach ( $providers_unavailable as $provider_key => $provider ) {
									$this->render_single_provider( $provider_key, $provider, false, true );
								}
								?>
								</div>
							</div>
						</div>
						<?php endif; ?>

						<?php if ( ! empty( $providers_comming_soon ) ): ?>
						<div class="wp-mastertoolkit__section__body__item">
							<div class="wp-mastertoolkit__section__body__item__title">
								<?php esc_html_e( 'Providers', 'wpmastertoolkit' ); ?>
								<span class="wp-mastertoolkit__section__body__item__title__tag comming"><?php esc_html_e( 'Coomming Soon', 'wpmastertoolkit' ); ?></span>
							</div>
							<div class="wp-mastertoolkit__section__body__item__content">
								<div class="wp-mastertoolkit__providers">
								<?php
								foreach ( $providers_comming_soon as $provider_key => $provider ) {
									$this->render_single_provider( $provider_key, $provider, false, true );
								}
								?>
								</div>
							</div>
						</div>
						<?php endif; ?>

						<div class="wp-mastertoolkit__section__body__item">
							<div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Form Layout', 'wpmastertoolkit' ); ?></div>
							<div class="wp-mastertoolkit__section__body__item__content">
								<div class="wp-mastertoolkit__radio image-grid">
									<?php foreach ( $form_layout_options as $key => $name ) : ?>
										<label class="wp-mastertoolkit__radio__label">
											<input type="radio" name="<?php echo esc_attr( $this->option_id . '[form_layout][value][value]' ); ?>" value="<?php echo esc_attr( $key ); ?>" <?php checked( $form_layout, $key ); ?>>
											<span class="mark"></span>
											<span class="wp-mastertoolkit__checkbox__label__text">
												<span class="wp-mastertoolkit__checkbox__label__text__name"><?php echo esc_html( $name ); ?></span>
												<img src="<?php echo esc_url( WPMASTERTOOLKIT_PLUGIN_URL . 'admin/images/social-login/form-layout/' . $key . '.webp' ); ?>" alt="<?php echo esc_attr( $name ); ?>">
											</span>
										</label>
									<?php endforeach; ?>
								</div>
							</div>
						</div>
						
						<div class="wp-mastertoolkit__section__body__item">
							<div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Button style', 'wpmastertoolkit' ); ?></div>
							<div class="wp-mastertoolkit__section__body__item__content">
								<div class="wp-mastertoolkit__radio image-grid">
									<?php foreach ( $buttons_style_options as $key => $name ) : ?>
										<label class="wp-mastertoolkit__radio__label">
											<input type="radio" name="<?php echo esc_attr( $this->option_id . '[buttons_style][value][value]' ); ?>" value="<?php echo esc_attr( $key ); ?>" <?php checked( $buttons_style, $key ); ?>>
											<span class="mark"></span>
											<span class="wp-mastertoolkit__checkbox__label__text">
												<span class="wp-mastertoolkit__checkbox__label__text__name"><?php echo esc_html( $name ); ?></span>
												<img src="<?php echo esc_url( WPMASTERTOOLKIT_PLUGIN_URL . 'admin/images/social-login/buttons-style/' . $key . '.webp' ); ?>" alt="<?php echo esc_attr( $name ); ?>">
											</span>
										</label>
									<?php endforeach; ?>
								</div>
							</div>
						</div>

						<div class="wp-mastertoolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->option_id . '[buttons_style][value][value]' ); ?>=fitcontent|<?php echo esc_attr( $this->option_id . '[buttons_style][value][value]' ); ?>=icon">
							<div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Buttons alignment', 'wpmastertoolkit' ); ?></div>
							<div class="wp-mastertoolkit__section__body__item__content">
								<div class="wp-mastertoolkit__radio image-grid">
									<?php foreach ( $buttons_align_options as $key => $name ) : ?>
										<label class="wp-mastertoolkit__radio__label">
											<input type="radio" name="<?php echo esc_attr( $this->option_id . '[buttons_align][value][value]' ); ?>" value="<?php echo esc_attr( $key ); ?>" <?php checked( $buttons_align, $key ); ?>>
											<span class="mark"></span>
											<span class="wp-mastertoolkit__checkbox__label__text">
												<span class="wp-mastertoolkit__checkbox__label__text__name"><?php echo esc_html( $name ); ?></span>
												<img src="<?php echo esc_url( WPMASTERTOOLKIT_PLUGIN_URL . 'admin/images/social-login/buttons-align/' . $key . '.webp' ); ?>" alt="<?php echo esc_attr( $name ); ?>">
											</span>
										</label>
									<?php endforeach; ?>
								</div>
							</div>
						</div>
					</div>
				</div>

				<div class="wp-mastertoolkit__section config">
					<?php
					foreach ( $providers as $provider_key => $provider ) {

						$class_name = 'WPMastertoolkit_Social_Login_' . ucfirst( $provider_key );
						if ( ! method_exists( $class_name, 'render_config' ) ) {
							continue;
						}
							
						call_user_func( array( $class_name, 'render_config' ), $this, $provider_key === $selected_provider );
					}
					?>
				</div>
			</div>
		<?php
	}

	/**
	 * Render single provider
	 * 
	 * @since	2.21.0
	 */
	private function render_single_provider( $provider_key, $provider, $checked = false, $disabled = false ) {

		$this->settings         = $this->get_settings();
		$this->default_settings = $this->get_default_settings();

		$enabled    = $this->settings['providers'][$provider_key]['params']['enabled']['value'] ?? '';
		$icon_color = $this->default_settings['providers'][$provider_key]['icon_color'] ?? '';
		$classes    = '';

		if ( '1' === $enabled ) {
			$classes .= ' active';
		}

		?>
		<?php if ( ! $disabled ): ?>
		<label class="<?php echo esc_attr( $classes ); ?>">
			<input type="radio" class="wp-mastertoolkit__providers__input" name="wpmtk_selected_provider" value="<?php echo esc_attr( $provider_key ); ?>" <?php checked( $checked ); ?>>
			<div class="wp-mastertoolkit__providers__icon">
				<?php echo wp_kses( file_get_contents(WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/check-round.svg'), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
			</div>
		<?php endif; ?>
			<div class="wp-mastertoolkit__providers__provider <?php echo $disabled ? 'disabled' : ''; ?>">
				<div class="wp-mastertoolkit__providers__provider__image <?php echo esc_attr( $provider_key ); ?>">
					<span style="color: <?php echo esc_attr( $icon_color ); ?>;">
						<?php echo wp_kses( file_get_contents(WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/social-login/' . $provider_key . '.svg'), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
					</span>
				</div>
			</div>
		<?php if ( ! $disabled ): ?>
		</label>
		<?php endif; ?>
		<?php
	}
}
