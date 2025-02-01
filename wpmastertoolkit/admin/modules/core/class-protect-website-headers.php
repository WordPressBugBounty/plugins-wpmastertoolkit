<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Protect Website Headers
 * Description: Add security headers quickly to your site to protect it from threats such as phishing attacks, data theft and more.
 * @since 1.9.0
 */
class WPMastertoolkit_Protect_Website_Headers {

    private $option_id;
    private $header_title;
    private $nonce_action;
    private $settings;
    private $default_settings;

	const MODULE_ID = 'Protect Website Headers';

	/**
     * Invoke the hooks
     * 
     * @since    1.9.0
     */
    public function __construct() {
        $this->option_id    = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_protect_website_headers';
        $this->nonce_action = $this->option_id . '_action';

        add_action( 'init', array( $this, 'class_init' ) );
        add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
        add_action( 'admin_init', array( $this, 'save_submenu' ) );
		add_filter( 'wpmastertoolkit_nginx_code_snippets', array( $this, 'nginx_code_snippets' ) );
		add_filter( 'wp_headers', array( $this, 'get_headers' ) );
    }

	/**
     * Initialize the class
     * 
     * @since    1.9.0
     * @return   void
     */
    public function class_init() {
        $this->header_title = esc_html__( 'Protect Website Headers', 'wpmastertoolkit' );
    }

	/**
     * Add the nginx code snippet
     *
	 * @since    1.9.0
     * @param  mixed $code_snippets
     * @return array
     */
    public function nginx_code_snippets( $code_snippets ) {
        global $is_nginx;

        if ( $is_nginx ) {
            $content = $this->get_raw_content_nginx();

            if ( !empty( $content ) ) {
                $code_snippets[self::MODULE_ID] = $content;
            }
        }

        return $code_snippets;
    }

	/**
	 * Get headers
	 *
	 * @since    1.9.0
	 * @param array $headers
	 * @return array
	 */
	public function get_headers( array $headers = array() ): array {
		$settings         = $this->get_settings();
		$default_settings = $this->get_default_settings();

		if ( ! $settings['disable_strict_transport_security'] ) {
			$headers['Strict-Transport-Security'] = $this->get_hsts_header( $settings );
		}
		if ( ! $settings['disable_permissions_policy'] ) {
			$headers['Permissions-Policy'] = $this->get_permissions_policy_header( $settings, $default_settings );
		}
		if ( ! $settings['disable_x_content_type_options'] ) {
			$headers['X-Content-Type-Options'] = 'nosniff';
		}
		if ( ! $settings['disable_x_frame_options'] ) {
			$headers['X-Frame-Options'] = $this->get_x_frame_options_header( $settings );
		}
		if ( ! empty( $settings['csp_report_uri'] ) ) {
			$headers['Content-Security-Policy-Report-Only'] = $this->get_csp_report_only_header( $settings );
		}

		$headers['Access-Control-Allow-Methods']             = 'GET,POST';
		$headers['Access-Control-Allow-Headers']             = 'Content-Type, Authorization';
		$headers['Content-Security-Policy']                  = $this->get_csp_header( $settings, $default_settings );
		$headers['Cross-Origin-Embedder-Policy']             = "unsafe-none; report-to='default'";
		$headers['Cross-Origin-Embedder-Policy-Report-Only'] = "unsafe-none; report-to='default'";
		$headers['Cross-Origin-Opener-Policy']               = 'unsafe-none';
		$headers['Cross-Origin-Opener-Policy-Report-Only']   = "unsafe-none; report-to='default'";
		$headers['Cross-Origin-Resource-Policy']             = 'cross-origin';
		$headers['Referrer-Policy']                          = 'strict-origin-when-cross-origin';
		$headers['X-Content-Security-Policy']                = 'default-src \'self\'; img-src *; media-src * data:;';
		$headers['X-Permitted-Cross-Domain-Policies']        = 'none';

		return $headers;
	}

	/**
	 * Get CSP header
	 *
	 * @since    1.9.0
	 * @return string
	 */
	public function get_csp_header( $settings, $default_settings ): string {
		$report_uri  = $settings['csp_report_uri'];
		$csp_content = wp_unslash( $settings['csp_header_contents'] );
	
		if ( ! empty( $report_uri ) ) {
			$report_to    = "report-to {$report_uri}";
			$report_uri   = "report-uri {$report_uri}";
			$csp_content .= " {$report_to}; {$report_uri};";
		}
	
		return empty( $csp_content ) ? $default_settings['csp_header_contents'] : $csp_content;
	}

	/**
	 * Get CSP report only header
	 *
	 * @since    1.9.0
	 * @return string
	 */
	public function get_csp_report_only_header( $settings ): string {
		$report_uri  = $settings['csp_report_uri'];
		$csp_content = wp_unslash( $settings['csp_header_contents'] );
	
		if ( ! empty( $report_uri ) ) {
			$report_to    = "report-to {$report_uri}";
			$report_uri   = "report-uri {$report_uri}";
			$csp_content .= " {$report_to}; {$report_uri};";
		}
	
		return $csp_content;
	}

	/**
	 * Get permissions policy header
	 *
	 * @since    1.9.0
	 * @return string
	 */
	public function get_permissions_policy_header( $settings, $default_settings ): string {
		$permissions_policy_contents = wp_unslash( $settings['permissions_policy_contents'] );

		return empty( $permissions_policy_contents ) ? $default_settings['permissions_policy_contents'] : $permissions_policy_contents;
	}

	/**
	 * Get HSTS header
	 *
	 * @since    1.9.0
	 * @return string
	 */
	public function get_hsts_header( $settings ): string {
		$max_age            = $settings['max_age'];
		$include_subdomains = $settings['enable_include_subdomains'];
		$preload            = $settings['enable_preload'];
		$header_tokens      = array( "max-age={$max_age}" );
	
		if ( $include_subdomains ) {
			$header_tokens[] = 'includeSubDomains';
		}
		if ( $preload ) {
			$header_tokens[] = 'preload';
		}
	
		return implode( '; ', $header_tokens );
	}

	/**
	 * Get X-Frame-Options header
	 *
	 * @since    1.9.0
	 * @return string
	 */
	public function get_x_frame_options_header( $settings ): string {
		$x_frame_options = $settings['x_frame_options']['value'];
		$allow_from_url  = $settings['x_frame_options_allow_from_url'];

		if ( $x_frame_options === 'ALLOW-FROM' ) {
			if ( ! empty( $allow_from_url ) ) {
				return "ALLOW-FROM $allow_from_url";
			}
			return "ALLOW-FROM";
		}

		return $x_frame_options ?: 'SAMEORIGIN';
	}

	/**
     * Add a submenu
     * 
     * @since   1.9.0
     */
    public function add_submenu(){
        add_submenu_page(
            'wp-mastertoolkit-settings',
            $this->header_title,
            $this->header_title,
            'manage_options',
            'wp-mastertoolkit-settings-protect-website-headers',
            array( $this, 'render_submenu' ),
            null
        );
    }

	/**
     * Render the submenu
     * 
     * @since   1.9.0
     */
    public function render_submenu() {
        $submenu_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/protect-website-headers.asset.php' );
        wp_enqueue_style( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/protect-website-headers.css', array(), $submenu_assets['version'], 'all' );
        wp_enqueue_script( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/protect-website-headers.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );

        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/submenu/header.php';
        $this->submenu_content();
        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/submenu/footer.php';
    }

	/**
     * Save the submenu option
     * 
     * @since   1.9.0
     */
    public function save_submenu() {
		$nonce = sanitize_text_field( $_POST['_wpnonce'] ?? '' );

		if ( wp_verify_nonce( $nonce, $this->nonce_action ) ) {
            $new_settings = $this->sanitize_settings( $_POST[ $this->option_id ] ?? array() );
            $this->save_settings( $new_settings );
			self::update_htaccess();
            wp_safe_redirect( sanitize_url( $_SERVER['REQUEST_URI'] ?? '' ) );
			exit;
		}
    }

	/**
     * sanitize_settings
     * 
     * @since   1.9.0
     * @return array
     */
    public function sanitize_settings( $new_settings ){
        $this->default_settings = $this->get_default_settings();
        $sanitized_settings     = array();

        foreach ( $this->default_settings as $settings_key => $settings_value ) {
			if ( 'x_frame_options' === $settings_key ) {
				$new_value = sanitize_text_field( $new_settings[ $settings_key ]['value'] ?? $settings_value['value'] );
				if ( array_key_exists( $new_value, $settings_value['options'] ) ) {
					$sanitized_value = $new_value;
				} else {
					$sanitized_value = $settings_value['value'];
				}
				$sanitized_settings[ $settings_key ]['value'] = $sanitized_value;
			} elseif ( 'csp_report_uri' === $settings_key || 'x_frame_options_allow_from_url' == $settings_key ) {
				$sanitized_settings[ $settings_key ] = sanitize_url( $new_settings[ $settings_key ] ?? $settings_value );
		 	} elseif ('csp_header_contents' === $settings_key || 'permissions_policy_contents' === $settings_key) {
				$sanitized_settings[ $settings_key ] = sanitize_textarea_field( $new_settings[ $settings_key ] ?? $settings_value );
			} else {
				$sanitized_settings[ $settings_key ] = sanitize_text_field( $new_settings[ $settings_key ] ?? $settings_value );
			}
        }
		
        return $sanitized_settings;
    }

	/**
     * activate
     *
	 * @since   1.9.0
     * @return void
     */
    public static function activate(){
        self::update_htaccess();
    }

	/**
     * deactivate
     *
	 * @since   1.9.0
     * @return void
     */
    public static function deactivate(){
		global $is_apache;

        if ( $is_apache ) {

            require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/class-htaccess.php';
            WPMastertoolkit_Htaccess::remove( self::MODULE_ID );
        }
    }

	/**
	 * Update the .htaccess file
	 * 
	 * @since   1.9.0
	 */
	private static function update_htaccess() {
		global $is_apache;

        if ( $is_apache ) {

            require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/class-htaccess.php';
            $content = self::get_raw_content_htaccess();

            if ( !empty( $content ) ) {
                WPMastertoolkit_Htaccess::add( wp_unslash( $content ), self::MODULE_ID );
            }
        }
	}

	/**
	 * Content of the .htaccess file
	 * 
	 * @since   1.9.0
	 */
    private static function get_raw_content_htaccess() {
		$this_class = __CLASS__;
		$this_class = new $this_class();

		$content = "<IfModule mod_headers.c>\n";
		foreach ( $this_class->get_headers() as $name => $header ) {
			$content .= "Header set {$name} \"{$header}\"\n";
		}
		$content .= "</IfModule>";

		return trim( $content );
	}

	/**
     * Content of the nginx.conf file
	 * 
	 * @since   1.9.0
     */
    private function get_raw_content_nginx() {
		$content    = "location / {";
		foreach ( $this->get_headers() as $name => $header ) {
			$content .= "\n\tadd_header {$name} \"{$header}\";";
		}
        $content   .= "\n}";

        return trim( $content );
    }

	/**
     * get_settings
     *
     * @since   1.9.0
     * @return void
     */
    private function get_settings(){
        $this->default_settings = $this->get_default_settings();
        return get_option( $this->option_id, $this->default_settings );
    }

	/**
     * Save settings
     * 
     * @since   1.9.0
     */
    private function save_settings( $new_settings ) {
		update_option( $this->option_id, $new_settings );
    }

	/**
     * get_default_settings
     *
     * @since   1.9.0
     * @return array
     */
    private function get_default_settings(){
        if( $this->default_settings !== null ) return $this->default_settings;

        return array(
			'disable_strict_transport_security'     => '0',
			'disable_permissions_policy'            => '0',
			'disable_x_content_type_options'        => '0',
			'disable_x_frame_options'               => '0',
			'max_age'                               => '63072000',
			'enable_include_subdomains'             => '0',
			'enable_preload'                        => '0',
			'csp_header_contents'                   => 'upgrade-insecure-requests;',
			'csp_report_uri'                        => '',
			'permissions_policy_contents'           => 'accelerometer=(), autoplay=(), camera=(), cross-origin-isolated=(), display-capture=(self), encrypted-media=(), fullscreen=*, geolocation=(self), gyroscope=(), keyboard-map=(), magnetometer=(), microphone=(), midi=(), payment=*, picture-in-picture=(), publickey-credentials-get=(), screen-wake-lock=(), sync-xhr=(), usb=(), xr-spatial-tracking=(), gamepad=(), serial=()',
			'x_frame_options'                       => array(
				'value'   => 'SAMEORIGIN',
				'options' => array(
					'DENY'       => __( 'DENY', 'wpmastertoolkit' ),
					'SAMEORIGIN' => __( 'SAMEORIGIN', 'wpmastertoolkit' ),
					'ALLOW-FROM' => __( 'ALLOW-FROM', 'wpmastertoolkit' ),
				),
			),
			'x_frame_options_allow_from_url'        => '',
        );
    }

	/**
     * Add the submenu content
     * 
     * @since   1.9.0
     * @return void
     */
    private function submenu_content() {
        $this->settings         = $this->get_settings();
		$this->default_settings = $this->get_default_settings();
		$x_frame_options        = $this->default_settings['x_frame_options']['options'];
		$x_frame_value          = $this->settings['x_frame_options']['value'] ?? $this->default_settings['x_frame_options']['value'];

        ?>
            <div class="wp-mastertoolkit__section">
                <div class="wp-mastertoolkit__section__desc">
					<?php esc_html_e( "Add security headers quickly to your site to protect it from threats such as phishing attacks, data theft and more.", 'wpmastertoolkit'); ?>
				</div>
                <div class="wp-mastertoolkit__section__body">

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title">
							<?php esc_html_e( 'Resolve duplicate site headers', 'wpmastertoolkit' ); ?>
							<div class="wp-mastertoolkit__section__body__item__title__info">
								<div class="wp-mastertoolkit__section__body__item__title__info__icon">
									<?php echo wp_kses( file_get_contents(WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/info.svg'), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
								</div>
								<div class="wp-mastertoolkit__section__body__item__title__info__popup">
									<p><?php esc_html_e( 'When a website loads, the server sends headers (small pieces of information) to the browser that tell it how to handle the content and security settings of the site. Sometimes, the same header can be sent more than once (duplicated) either due to misconfiguration or conflicts between plugins, themes, or server settings. These duplicate headers can cause issues like: Slower website loading times, Confusion in how browsers interpret security or content rules, Potential conflicts that can make certain features of the site not work properly.', 'wpmastertoolkit' ); ?></p>
								</div>
							</div>
						</div>
                        <div class="wp-mastertoolkit__section__body__item__content">
							<div class="wp-mastertoolkit__checkbox">
								<label class="wp-mastertoolkit__checkbox__label">
									<input type="hidden" name="<?php echo esc_attr( $this->option_id . '[disable_strict_transport_security]' ); ?>" value="0">
									<input type="checkbox" name="<?php echo esc_attr( $this->option_id . '[disable_strict_transport_security]' ); ?>" value="1"<?php checked( $this->settings['disable_strict_transport_security']??'', '1' ); ?>>
									<span class="mark"></span>
									<span class="wp-mastertoolkit__checkbox__label__text"><?php esc_html_e( 'Disable (Strict-Transport-Security).', 'wpmastertoolkit' ); ?></span>
								</label>
							</div>
							<div class="wp-mastertoolkit__checkbox">
								<label class="wp-mastertoolkit__checkbox__label">
									<input type="hidden" name="<?php echo esc_attr( $this->option_id . '[disable_permissions_policy]' ); ?>" value="0">
									<input type="checkbox" name="<?php echo esc_attr( $this->option_id . '[disable_permissions_policy]' ); ?>" value="1"<?php checked( $this->settings['disable_permissions_policy']??'', '1' ); ?>>
									<span class="mark"></span>
									<span class="wp-mastertoolkit__checkbox__label__text"><?php esc_html_e( 'Disable (Permissions-Policy).', 'wpmastertoolkit' ); ?></span>
								</label>
							</div>
							<div class="wp-mastertoolkit__checkbox">
								<label class="wp-mastertoolkit__checkbox__label">
									<input type="hidden" name="<?php echo esc_attr( $this->option_id . '[disable_x_content_type_options]' ); ?>" value="0">
									<input type="checkbox" name="<?php echo esc_attr( $this->option_id . '[disable_x_content_type_options]' ); ?>" value="1"<?php checked( $this->settings['disable_x_content_type_options']??'', '1' ); ?>>
									<span class="mark"></span>
									<span class="wp-mastertoolkit__checkbox__label__text"><?php esc_html_e( 'Disable (X-Content-Type-Options).', 'wpmastertoolkit' ); ?></span>
								</label>
							</div>
							<div class="wp-mastertoolkit__checkbox">
								<label class="wp-mastertoolkit__checkbox__label">
									<input type="hidden" name="<?php echo esc_attr( $this->option_id . '[disable_x_frame_options]' ); ?>" value="0">
									<input type="checkbox" name="<?php echo esc_attr( $this->option_id . '[disable_x_frame_options]' ); ?>" value="1"<?php checked( $this->settings['disable_x_frame_options']??'', '1' ); ?>>
									<span class="mark"></span>
									<span class="wp-mastertoolkit__checkbox__label__text"><?php esc_html_e( 'Disable (X-Frame-Options).', 'wpmastertoolkit' ); ?></span>
								</label>
							</div>
                        </div>
                    </div>

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title">
							<?php esc_html_e( 'Max-Age', 'wpmastertoolkit' ); ?>
							<div class="wp-mastertoolkit__section__body__item__title__info">
								<div class="wp-mastertoolkit__section__body__item__title__info__icon">
									<?php echo wp_kses( file_get_contents(WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/info.svg'), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
								</div>
								<div class="wp-mastertoolkit__section__body__item__title__info__popup">
									<p><?php esc_html_e( 'It is advisable to set "max-age" to a high value, such as a full year (31536000 seconds). This ensures that browsers continue to store security information for a long period of time, which helps protect users from man-in-the-middle attacks. However, it is important to keep in mind that setting the value too high could cause problems if you need to change your site\'s SSL configuration in the future. Therefore, it is important to carefully consider your usage and security needs before setting the value.', 'wpmastertoolkit' ); ?></p>
								</div>
							</div>
						</div>
                        <div class="wp-mastertoolkit__section__body__item__content">
							<div class="wp-mastertoolkit__input-text">
								<input type="text" name="<?php echo esc_attr( $this->option_id . '[max_age]' ); ?>" value="<?php echo esc_attr( $this->settings['max_age'] ?? '' ); ?>">
							</div>
							<div class="description"><?php esc_html_e( 'The Max-Age parameter specifies the period of time (in seconds) for which the browser should store the HSTS information.', 'wpmastertoolkit' ); ?></div>
                        </div>
                    </div>

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title">
							<?php esc_html_e( 'Include Subdomains', 'wpmastertoolkit' ); ?>
							<div class="wp-mastertoolkit__section__body__item__title__info">
								<div class="wp-mastertoolkit__section__body__item__title__info__icon">
									<?php echo wp_kses( file_get_contents(WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/info.svg'), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
								</div>
								<div class="wp-mastertoolkit__section__body__item__title__info__popup">
									<p><?php esc_html_e( 'We recommend enabling the "includeSubDomains" option in the HSTS header to ensure that all subsections of your site (subdomains) are only loaded via HTTPS. However, before enabling this flag, it is important to ensure that all subdomains, resources and web services working under your domain are available via HTTPS and that there are no compatibility issues with any external services used by your site.', 'wpmastertoolkit' ); ?></p>
								</div>
							</div>
						</div>
                        <div class="wp-mastertoolkit__section__body__item__content">
							<div class="wp-mastertoolkit__checkbox">
								<label class="wp-mastertoolkit__checkbox__label">
									<input type="hidden" name="<?php echo esc_attr( $this->option_id . '[enable_include_subdomains]' ); ?>" value="0">
									<input type="checkbox" name="<?php echo esc_attr( $this->option_id . '[enable_include_subdomains]' ); ?>" value="1"<?php checked( $this->settings['enable_include_subdomains']??'', '1' ); ?>>
									<span class="mark"></span>
									<span class="wp-mastertoolkit__checkbox__label__text"><?php esc_html_e( 'Enable include subdomains', 'wpmastertoolkit' ); ?></span>
								</label>
							</div>
							<div class="description"><?php esc_html_e( 'The "includeSubDomains" flag specifies that the effect of the header should also be applied to subdomains of the domain.', 'wpmastertoolkit' ); ?></div>
						</div>
                    </div>

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title">
							<?php esc_html_e( 'Preload', 'wpmastertoolkit' ); ?>
							<div class="wp-mastertoolkit__section__body__item__title__info">
								<div class="wp-mastertoolkit__section__body__item__title__info__icon">
									<?php echo wp_kses( file_get_contents(WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/info.svg'), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
								</div>
								<div class="wp-mastertoolkit__section__body__item__title__info__popup">
									<p><?php esc_html_e( 'Enabling "preload" further helps prevent any potential man-in-the-middle attacks, thus improving connection security as far as it concerns HSTS. Please note that even if this flag is enabled, your website still needs to be manually submitted to the list. Please also note that inclusion in the preload list has permanent consequences and is not easy to undo, so you should only enable this flag and submit your website after making sure that all of the resources and services within your domain (and its subdomains, if "includeSubDomains" is also enabled) are indeed accessible and functional via HTTPS.', 'wpmastertoolkit' ); ?></p>
								</div>
							</div>
						</div>
                        <div class="wp-mastertoolkit__section__body__item__content">
							<div class="wp-mastertoolkit__checkbox">
								<label class="wp-mastertoolkit__checkbox__label">
									<input type="hidden" name="<?php echo esc_attr( $this->option_id . '[enable_preload]' ); ?>" value="0">
									<input type="checkbox" name="<?php echo esc_attr( $this->option_id . '[enable_preload]' ); ?>" value="1"<?php checked( $this->settings['enable_preload']??'', '1' ); ?>>
									<span class="mark"></span>
									<span class="wp-mastertoolkit__checkbox__label__text"><?php esc_html_e( 'Enable preload', 'wpmastertoolkit' ); ?></span>
								</label>
							</div>
							<div class="description"><?php esc_html_e( 'The "preload" flag allows the website to be included in the HSTS preload list.', 'wpmastertoolkit' ); ?></div>
						</div>
                    </div>

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title">
							<?php esc_html_e( 'CSP Header Contents', 'wpmastertoolkit' ); ?>
							<div class="wp-mastertoolkit__section__body__item__title__info">
								<div class="wp-mastertoolkit__section__body__item__title__info__icon">
									<?php echo wp_kses( file_get_contents(WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/info.svg'), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
								</div>
								<div class="wp-mastertoolkit__section__body__item__title__info__popup">
									<p><?php esc_html_e( 'The Content Security Policy (CSP) header is a security feature used to prevent various types of attacks on websites, like cross-site scripting (XSS), clickjacking, and data injection attacks. It does this by controlling which resources (such as scripts, images, styles, or media) the browser is allowed to load and execute.', 'wpmastertoolkit' ); ?></p>
								</div>
							</div>
						</div>
                        <div class="wp-mastertoolkit__section__body__item__content">
							<div class="wp-mastertoolkit__textarea">
								<textarea name="<?php echo esc_attr( $this->option_id ); ?>[csp_header_contents]" cols="50" rows="3"><?php echo esc_textarea( wp_unslash( $this->settings['csp_header_contents'] ?? '' ) ); ?></textarea>
							</div>
							<div class="description"><?php esc_html_e( 'HTTP Content-Security-Policy header controls website resources, reducing XSS risk by specifying allowed server origins and script endpoints.', 'wpmastertoolkit' ); ?></div>
						</div>
                    </div>

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title">
							<?php esc_html_e( 'CSP Report URI', 'wpmastertoolkit' ); ?>
							<div class="wp-mastertoolkit__section__body__item__title__info">
								<div class="wp-mastertoolkit__section__body__item__title__info__icon">
									<?php echo wp_kses( file_get_contents(WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/info.svg'), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
								</div>
								<div class="wp-mastertoolkit__section__body__item__title__info__popup">
									<p><?php esc_html_e( 'The CSP Report URI is a directive within the Content Security Policy (CSP) header that tells the browser where to send reports when a violation of the CSP occurs. Instead of blocking resources outright, this directive can be used to monitor potential security issues on your site without disrupting users by reporting violations to a specified URL.', 'wpmastertoolkit' ); ?></p>
								</div>
							</div>
						</div>
                        <div class="wp-mastertoolkit__section__body__item__content">
							<div class="wp-mastertoolkit__input-text">
								<input type="text" name="<?php echo esc_attr( $this->option_id . '[csp_report_uri]' ); ?>" value="<?php echo esc_attr( $this->settings['csp_report_uri'] ?? '' ); ?>">
							</div>
							<div class="description"><?php esc_html_e( 'Enter your custom URL (Sentry, URIports, Datadog, and Report URI) for CSP violation reports.', 'wpmastertoolkit' ); ?></div>
                        </div>
                    </div>

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Permissions Policy Contents', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
							<div class="wp-mastertoolkit__textarea">
								<textarea name="<?php echo esc_attr( $this->option_id ); ?>[permissions_policy_contents]" cols="50" rows="5"><?php echo esc_textarea( wp_unslash( $this->settings['permissions_policy_contents'] ?? '' ) ); ?></textarea>
							</div>
							<div class="description"><?php esc_html_e( 'The HTTP Permissions-Policy header provides a mechanism to allow and deny the use of browser features in a document or within any <iframe> elements in the document.', 'wpmastertoolkit' ); ?></div>
						</div>
                    </div>

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'X-Frame-Options', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
							<div class="wp-mastertoolkit__select">
                                <select name="<?php echo esc_attr( $this->option_id . '[x_frame_options][value]' ); ?>" id="JS-wpmastertoolkit-x-frame-options-select">
                                    <?php foreach ( $x_frame_options as $key => $name ) : ?>
                                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $x_frame_value, $key ); ?>><?php echo esc_html( $name ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
							<div class="wp-mastertoolkit__input-text" <?php echo $x_frame_value != 'ALLOW-FROM' ? 'style="display: none;"' : ''; ?> id="JS-wpmastertoolkit-x-frame-options-input">
								<input type="text" name="<?php echo esc_attr( $this->option_id . '[x_frame_options_allow_from_url]' ); ?>" value="<?php echo esc_attr( $this->settings['x_frame_options_allow_from_url'] ?? '' ); ?>">
							</div>
							<div class="description"><?php esc_html_e( 'The X-Frame-Options HTTP response header can be used to indicate whether or not a browser should be allowed to render a page in a <frame>, <iframe>, <embed> or <object>. Sites can use this to avoid click-jacking attacks, by ensuring that their content is not embedded into other sites.', 'wpmastertoolkit' ); ?></div>
						</div>
                    </div>

                </div>
            </div>
        <?php
    }
}
