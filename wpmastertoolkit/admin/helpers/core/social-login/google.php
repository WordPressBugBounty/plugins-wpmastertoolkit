<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Class to handle Google provider.
 * 
 * @since 2.21.0
 */

class WPMastertoolkit_Social_Login_Google {

	private static $class_social_login = null;
	private static $provider_name      = 'google';
	private static $settings           = array();
	private static $default_settings   = array();

	/**
	 * Check if the login button can be shown.
	 *
	 * @since 2.21.0
	 */
	public static function can_show_login_button( $params, $default_params ) {

		$client_id = $params['client_id']['value'] ?? $default_params['client_id']['value'] ?? '';
		if ( empty( $client_id ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Render the configuration settings.
	 *
	 * @since 2.21.0
	 */
	public static function render_config( $class_social_login, $is_selected = false ) {

		self::$class_social_login = $class_social_login;
		self::$settings           = self::$class_social_login->get_settings();
		self::$default_settings   = self::$class_social_login->get_default_settings();

		$icon_color             = self::$default_settings['providers'][self::$provider_name]['icon_color'] ?? '';
		$option_id              = self::$class_social_login->option_id . '[providers][' . self::$provider_name . '][params]';
		$params                 = self::$settings['providers'][self::$provider_name]['params'] ?? '';
		$default_params         = self::$default_settings['providers'][self::$provider_name]['params'] ?? '';
		$enabled                = $params['enabled']['value'] ?? $default_params['enabled']['value'] ?? '';
		$client_id              = $params['client_id']['value'] ?? '';
		$client_secret          = $params['client_secret']['value'] ?? '';
		$select_account         = $params['select_account']['value'] ?? $default_params['select_account']['value'] ?? '';
		$login_label_default    = $default_params['login_label']['value'] ?? '';
		$login_label            = $params['login_label']['value'] ?? $login_label_default;
		$register_label_default = $default_params['register_label']['value'] ?? '';
		$register_label         = $params['register_label']['value'] ?? $register_label_default;
		$button_skin_options    = $default_params['button_skin']['value']['options'] ?? '';
		$button_skin            = $params['button_skin']['value']['value'] ?? $default_params['button_skin']['value']['value'] ?? '';
		$user_prefix            = $params['user_prefix']['value'] ?? $default_params['user_prefix']['value'] ?? '';
		$wordpress              = $params['wordpress']['value'] ?? $default_params['wordpress']['value'] ?? '';
		$woocommerce            = $params['woocommerce']['value'] ?? $default_params['woocommerce']['value'] ?? '';
		$image_size_options     = $default_params['image_size']['value']['options'] ?? '';
		$image_size             = $params['image_size']['value']['value'] ?? $default_params['image_size']['value']['value'] ?? '';
		$register_role_options  = $default_params['register_role']['value']['options'] ?? '';
		$register_role		    = $params['register_role']['value']['value'] ?? $default_params['register_role']['value']['value'] ?? '';

		?>
		<div class="wp-mastertoolkit__section__body google <?php echo $is_selected ? 'active' : ''; ?>">
			<div class="wp-mastertoolkit__section__body__header">
				<span style="color: <?php echo esc_attr( $icon_color ); ?>;">
					<?php echo wp_kses( file_get_contents(WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/social-login/' . self::$provider_name . '.svg'), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
				</span>
			</div>
					
			<div class="wp-mastertoolkit__section__body__nav">
				<div class="wp-mastertoolkit__section__body__nav__item active" data-tab="0"><?php esc_html_e( 'Settings', 'wpmastertoolkit' ); ?></div>
				<div class="wp-mastertoolkit__section__body__nav__item" data-tab="1"><?php esc_html_e( 'Buttons', 'wpmastertoolkit' ); ?></div>
				<div class="wp-mastertoolkit__section__body__nav__item" data-tab="2"><?php esc_html_e( 'Advanced', 'wpmastertoolkit' ); ?></div>
			</div>

			<div class="wp-mastertoolkit__section__body__tab active" data-tab="0">
				<div class="wp-mastertoolkit__section__body__item">
					<div class="wp-mastertoolkit__section__body__item__title activable">
						<div>
							<label class="wp-mastertoolkit__toggle">
								<input type="hidden" name="<?php echo esc_attr( $option_id . '[enabled]' ); ?>" value="0">
								<input type="checkbox" name="<?php echo esc_attr( $option_id . '[enabled]' ); ?>" value="1" <?php checked( $enabled, '1' ); ?>>
								<span class="wp-mastertoolkit__toggle__slider round"></span>
							</label>
						</div>
						<div>
							<?php esc_html_e( 'Enable', 'wpmastertoolkit' ); ?>
						</div>
					</div>
				</div>
	
				<div class="wp-mastertoolkit__section__body__item">
					<div class="wp-mastertoolkit__section__body__item__content">
						<div class="wp-mastertoolkit__input-text">
							<input type="text" class="" name="<?php echo esc_attr( $option_id . '[client_id]' ); ?>" value="<?php echo esc_attr( $client_id ); ?>" placeholder="<?php esc_attr_e( 'Client ID', 'wpmastertoolkit' ); ?>">
						</div>
						<br>
						<div class="wp-mastertoolkit__input-text">
							<input type="password" class="" name="<?php echo esc_attr( $option_id . '[client_secret]' ); ?>" value="<?php echo esc_attr( $client_secret ); ?>" placeholder="<?php esc_attr_e( 'Client Secret', 'wpmastertoolkit' ); ?>">
						</div>
					</div>
				</div>
	
				<div class="wp-mastertoolkit__section__body__item">
					<div class="wp-mastertoolkit__section__body__item__title activable">
						<div>
							<label class="wp-mastertoolkit__toggle">
								<input type="hidden" name="<?php echo esc_attr( $option_id . '[select_account]' ); ?>" value="0">
								<input type="checkbox" name="<?php echo esc_attr( $option_id . '[select_account]' ); ?>" value="1" <?php checked( $select_account, '1' ); ?>>
								<span class="wp-mastertoolkit__toggle__slider round"></span>
							</label>
						</div>
						<div>
							<?php esc_html_e( 'Select account on each login', 'wpmastertoolkit' ); ?>
						</div>
					</div>
				</div>

				<div class="wp-mastertoolkit__section__body__item">
					<div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Authorized Redirect URI', 'wpmastertoolkit' ); ?></div>
					<div class="wp-mastertoolkit__section__body__item__content">
						<div class="wp-mastertoolkit__input-text slug-url">
							<div class="full-width">
								<input type="hidden" class="home-url" value="">
								<input class="slug-input" type="text" value="<?php echo esc_url( self::get_callback_url() ); ?>" readonly>
							</div>
							<button class="copy-button">
								<?php echo wp_kses( file_get_contents(WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/copy.svg'), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
							</button>
						</div>
					</div>
				</div>
			</div>

			<div class="wp-mastertoolkit__section__body__tab" data-tab="1">
				<div class="wp-mastertoolkit__section__body__item">
					<div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Login Label', 'wpmastertoolkit' ); ?></div>
					<div class="wp-mastertoolkit__section__body__item__content">
						<div class="wp-mastertoolkit__input-text">
							<input type="text" class="" name="<?php echo esc_attr( $option_id . '[login_label]' ); ?>" value="<?php echo esc_attr( $login_label ); ?>" placeholder="<?php echo esc_attr( $login_label_default ); ?>">
						</div>
					</div>
				</div>

				<div class="wp-mastertoolkit__section__body__item">
					<div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Register Label', 'wpmastertoolkit' ); ?></div>
					<div class="wp-mastertoolkit__section__body__item__content">
						<div class="wp-mastertoolkit__input-text">
							<input type="text" class="" name="<?php echo esc_attr( $option_id . '[register_label]' ); ?>" value="<?php echo esc_attr( $register_label ); ?>" placeholder="<?php echo esc_attr( $register_label_default ); ?>">
						</div>
					</div>
				</div>

				<div class="wp-mastertoolkit__section__body__item">
					<div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Button skin', 'wpmastertoolkit' ); ?></div>
					<div class="wp-mastertoolkit__section__body__item__content">
						<div class="wp-mastertoolkit__radio">
							<?php foreach ( $button_skin_options as $key => $name ) : ?>
								<label class="wp-mastertoolkit__radio__label" style="display:block; padding-left:30px; margin:0 0 10px;">
									<input type="radio" name="<?php echo esc_attr( $option_id . '[button_skin][value]' ); ?>" value="<?php echo esc_attr( $key ); ?>" <?php checked( $button_skin, $key ); ?> style="display:none;">
									<span class="mark"></span>
									<span class="wp-mastertoolkit__checkbox__label__text" style="display:block; width:fit-content;">
										<?php
											$colors = self::$default_settings['providers'][self::$provider_name]["color_$key"] ?? array();
											self::$class_social_login->render_login_register_button( self::$provider_name, '#', $login_label, $colors );
										?>
									</span>
								</label>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			</div>

			<div class="wp-mastertoolkit__section__body__tab" data-tab="2">
				<div class="wp-mastertoolkit__section__body__item">
					<div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Username prefix on register', 'wpmastertoolkit' ); ?></div>
					<div class="wp-mastertoolkit__section__body__item__content">
						<div class="wp-mastertoolkit__input-text">
							<input type="text" class="" name="<?php echo esc_attr( $option_id . '[user_prefix]' ); ?>" value="<?php echo esc_attr( $user_prefix ); ?>">
						</div>
					</div>
				</div>

				<div class="wp-mastertoolkit__section__body__item">
					<div class="wp-mastertoolkit__section__body__item__title activable">
						<div>
							<label class="wp-mastertoolkit__toggle">
								<input type="hidden" name="<?php echo esc_attr( $option_id . '[wordpress]' ); ?>" value="0">
								<input type="checkbox" name="<?php echo esc_attr( $option_id . '[wordpress]' ); ?>" value="1" <?php checked( $wordpress, '1' ); ?>>
								<span class="wp-mastertoolkit__toggle__slider round"></span>
							</label>
						</div>
						<div>
							<?php esc_html_e( 'WordPress', 'wpmastertoolkit' ); ?>
						</div>
					</div>
				</div>

				<div class="wp-mastertoolkit__section__body__item">
					<div class="wp-mastertoolkit__section__body__item__title activable">
						<div>
							<label class="wp-mastertoolkit__toggle">
								<input type="hidden" name="<?php echo esc_attr( $option_id . '[woocommerce]' ); ?>" value="0">
								<input type="checkbox" name="<?php echo esc_attr( $option_id . '[woocommerce]' ); ?>" value="1" <?php checked( $woocommerce, '1' ); ?>>
								<span class="wp-mastertoolkit__toggle__slider round"></span>
							</label>
						</div>
						<div>
							<?php esc_html_e( 'WooCommerce', 'wpmastertoolkit' ); ?>
						</div>
					</div>
				</div>

				<div class="wp-mastertoolkit__section__body__item">
					<div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Profile image size', 'wpmastertoolkit' ); ?></div>
					<div class="wp-mastertoolkit__section__body__item__content">
						<div class="wp-mastertoolkit__select">
							<select name="<?php echo esc_attr( $option_id . '[image_size][value]' ); ?>">
								<?php foreach ( $image_size_options as $key => $name ) : ?>
									<option value="<?php echo esc_attr( $key ) ?>" <?php echo selected( $image_size, $key, false ); ?>><?php echo esc_html( $name ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
				</div>

				<div class="wp-mastertoolkit__section__body__item">
					<div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Register role', 'wpmastertoolkit' ); ?></div>
					<div class="wp-mastertoolkit__section__body__item__content">
						<div class="wp-mastertoolkit__select">
							<select name="<?php echo esc_attr( $option_id . '[register_role][value]' ); ?>">
								<?php foreach ( $register_role_options as $key => $name ) : ?>
									<option value="<?php echo esc_attr( $key ) ?>" <?php echo selected( $register_role, $key, false ); ?>><?php echo esc_html( $name ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Redirect to the provider for authentication.
	 *
	 * @since 2.21.0
	 */
	public static function redirect_to_provider( $class_social_login ) {

		self::$class_social_login = $class_social_login;
		self::$settings           = self::$class_social_login->get_settings();
		self::$default_settings   = self::$class_social_login->get_default_settings();

		$params         = self::$settings['providers'][self::$provider_name]['params'] ?? '';
		$default_params = self::$default_settings['providers'][self::$provider_name]['params'] ?? '';
		$client_id      = $params['client_id']['value'] ?? '';
		$select_account = $params['select_account']['value'] ?? $default_params['select_account']['value'] ?? '';

		$query_args = array(
			'client_id'     => $client_id,
			'redirect_uri'  => self::get_callback_url(),
			'scope'         => 'openid email profile',
			'response_type' => 'code',
		);

		if ( '1' === $select_account ) {
			$query_args['prompt'] = 'select_account';
		}

		$authorization_url = add_query_arg(
			$query_args,
			'https://accounts.google.com/o/oauth2/v2/auth'
		);

		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		wp_redirect( $authorization_url );
		exit;
	}

	/**
	 * Get the user profile.
	 *
	 * @since 2.21.0
	 */
	public static function get_user_profile( $class_social_login ) {

		self::$class_social_login = $class_social_login;
		self::$settings           = self::$class_social_login->get_settings();
		self::$default_settings   = self::$class_social_login->get_default_settings();

		//phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$code = sanitize_text_field( wp_unslash( $_GET['code']  ?? '' ) );
		if ( empty( $code ) ) {
			return false;
		}

		$token = self::exchange_code_for_token( $code );
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$user = self::fetch_user_profile( $token );
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		return $user;
	}

	/**
	 * Exchange the authorization code for an access token.
	 * 
	 * @since 2.21.0
	 */
	private static function exchange_code_for_token( $code ) {

		$body = array(
			'client_id'     => self::$settings['providers'][self::$provider_name]['params']['client_id']['value'],
			'client_secret' => self::$settings['providers'][self::$provider_name]['params']['client_secret']['value'],
			'redirect_uri'  => self::get_callback_url(),
			'code'          => $code,
			'grant_type'    => 'authorization_code',
		);

		$resp = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array( 
				'body' => $body 
			) 
		);

		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$response_body = json_decode( wp_remote_retrieve_body( $resp ), true );

		if ( empty( $response_body['access_token'] ) ) {
			return new WP_Error( 'token_error', __( 'No token received.', 'wpmastertoolkit' ) );
		}

		return $response_body['access_token'];
	}

	/**
	 * Fetch the user profile using the access token.
	 * 
	 * @since 2.21.0
	 */
	private static function fetch_user_profile( $token ) {

		$headers = array( 'Authorization' => 'Bearer ' . $token );

		$resp = wp_remote_get(
			'https://www.googleapis.com/oauth2/v3/userinfo',
			array(
				'headers' => $headers
			)
		);

		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$response_body = json_decode( wp_remote_retrieve_body( $resp ), true );

		if ( empty( $response_body['sub'] ) ) {
			return new WP_Error( 'profile_error', __( 'No user profile received.', 'wpmastertoolkit' ) );
		}

		$picture_url    = '';
		$picture        = $response_body['picture'] ?? '';
		$params         = self::$settings['providers'][self::$provider_name]['params'] ?? '';
		$default_params = self::$default_settings['providers'][self::$provider_name]['params'] ?? '';
		$image_size     = $params['image_size']['value']['value'] ?? $default_params['image_size']['value']['value'] ?? '';

		if ( ! empty( $picture ) ) {
			switch ( $image_size ) {
				case 'small':
					$picture_url = str_replace( '=s96-c', '=s50-c', $picture );
				break;
				case 'medium':
					$picture_url = str_replace( '=s96-c', '=s360-c', $picture );
				break;
				case 'large':
					$picture_url = str_replace( '=s96-c', '=s480-c', $picture );
				break;
				case 'extralarge':
					$picture_url = str_replace( '=s96-c', '=s720-c', $picture );
				break;
				case 'original':
					$picture_url = str_replace( '=s96-c', '', $picture );
				break;
				default:
					$picture_url = $picture;
				break;
			}
		}

		return array(
			'id'         => $response_body['sub'] ?? '',
			'email'      => $response_body['email'] ?? '',
			'first_name' => $response_body['given_name'] ?? '',
			'last_name'  => $response_body['family_name'] ?? '',
			'picture'    => $picture_url,
		);
	}

	/**
	 * Get the callback URL for the provider.
	 * 
	 * @since 2.21.0
	 */
	private static function get_callback_url() {
		return site_url( 'wp-login.php' );
	}
}
