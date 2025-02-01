<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Move Login URL
 * Description: Change the login URL and prevent access to the wp-login.php page and the wp-admin directory to non-connected people.
 * @since 1.0.0
 */
class WPMastertoolkit_Move_Login_URL {

	private $option_id;
    private $header_title;
    private $nonce_action;
	private $default_settings;
    private $wp_custom_login_php;

	/**
     * Invoke the hooks.
     * 
     * @since   1.0.0
     */
    public function __construct() {

        $this->option_id   	= WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_move_login_url';
        $this->nonce_action	= $this->option_id . '_action';

		if ( is_multisite() && ! function_exists( 'is_plugin_active_for_network' ) || ! function_exists( 'is_plugin_active' ) ) {
			require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
		}

		if ( is_multisite() ) {
			add_action( 'wp_before_admin_bar_render', array( $this, 'modify_mysites_menu' ), 999 );
		}

        add_action( 'init', array( $this, 'class_init' ) );
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ), 9999 );
		add_action( 'wp_loaded', array( $this, 'wp_loaded' ) );
		add_action( 'setup_theme', array( $this, 'setup_theme' ), 1 );

		add_filter( 'site_url', array( $this, 'site_url' ), 10, 4 );
		add_filter( 'network_site_url', array( $this, 'network_site_url' ), 10, 3 );
		add_filter( 'wp_redirect', array( $this, 'wp_redirect' ), 10, 2 );

		add_action( 'template_redirect', array( $this, 'redirect_export_data' ) );
		add_filter( 'login_url', array( $this, 'login_url' ), 10, 3 );

		add_filter( 'user_request_action_email_content', array( $this, 'user_request_action_email_content' ), 999, 2 );

		add_filter( 'site_status_tests', array( $this, 'site_status_tests' ) );

		// Add compatibility with TranslatePress plugin
        add_filter( 'option_trp_settings', array( $this, 'prevent_trp_subdirectory_on_custom_login_url' ), 999 );
        
		add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
        add_action( 'admin_init', array( $this, 'save_submenu' ) );
		
    }
		
	/**
     * Initialize the class
     */
    public function class_init() {
		$this->header_title	= esc_html__( 'Move login URL', 'wpmastertoolkit' );
    }

	/**
	 * get_settings
	 * 
	 * @return array
	 */
    public function get_settings() {

		$this->default_settings = $this->get_default_settings();
		return get_option( $this->option_id, $this->default_settings );
	}

	/**
     * get_default_settings
     *
     * @return array
     */
    private function get_default_settings(){
        if( $this->default_settings !== null ) return $this->default_settings;

        return array(
            'login_slug'   	=> 'login',
            'redirect_slug'	=> '404'
        );
    }

	 /**
     * Save settings
     */
    public function save_settings( $new_settings ) {

		update_option( $this->option_id, $new_settings );
		flush_rewrite_rules( true );
    }

	/**
     * sanitize_settings
     * 
     * @return array
     */
    public function sanitize_settings($new_settings){

		$this->default_settings = $this->get_default_settings();
		$sanitized_settings = array();

		foreach ( $this->default_settings as $settings_key => $settings_value ) {
			
			switch ($settings_key) {
				case 'login_slug':
					
					$login_slug = sanitize_title_with_dashes( $new_settings[$settings_key] );

					if ( in_array( $login_slug, $this->forbidden_slugs() ) || empty( $login_slug ) ) {
						$login_slug = $settings_value;
					}

					$sanitized_settings[$settings_key] = $login_slug;
				break;
				case 'redirect_slug':
					$redirect_slug = sanitize_title_with_dashes( $new_settings[$settings_key] );
					$sanitized_settings[$settings_key] = $redirect_slug;
				break;
			}
		}

		return $sanitized_settings;
	}
	
	/**
	 * site_status_tests
	 *
	 * @param  mixed $tests
	 * @return array
	 */
	public function site_status_tests( $tests ) {
		unset( $tests['async']['loopback_requests'] );
		return $tests;
	}
	
	/**
	 * user_request_action_email_content
	 *
	 * @param  mixed $email_text
	 * @param  mixed $email_data
	 * @return string
	 */
	public function user_request_action_email_content( $email_text, $email_data ) {

		$settings = $this->get_settings();

		$email_text = str_replace( '###CONFIRM_URL###', esc_url_raw( str_replace( $settings['login_slug'] . '/', 'wp-login.php', $email_data['confirm_url'] ) ), $email_text );

		return $email_text;
	}
	
	/**
	 * use_trailing_slashes
	 *
	 * @return void
	 */
	private function use_trailing_slashes() {
		return ( '/' === substr( get_option( 'permalink_structure' ), - 1, 1 ) );
	}
	
	/**
	 * user_trailingslashit
	 *
	 * @param  mixed $string
	 * @return void
	 */
	private function user_trailingslashit( $string ) {
		return $this->use_trailing_slashes() ? trailingslashit( $string ) : untrailingslashit( $string );
	}
	
	/**
	 * wp_template_loader
	 *
	 * @return void
	 */
	private function wp_template_loader() {
		global $pagenow;

		$pagenow = 'index.php';

		if ( ! defined( 'WP_USE_THEMES' ) ) {
			define( 'WP_USE_THEMES', true );
		}

		wp();
		require_once( ABSPATH . WPINC . '/template-loader.php' );
		die;
	}
	
	/**
	 * modify_mysites_menu
	 *
	 * @return void
	 */
	public function modify_mysites_menu() {
        global $wp_admin_bar;
    
        $all_toolbar_nodes = $wp_admin_bar->get_nodes();
    
        foreach ( $all_toolbar_nodes as $node ) {
            if (preg_match('/^blog-(\d+)(.*)/', $node->id, $matches)) {
                $blog_id = $matches[1] ?? null;

				$settings = $this->get_settings();
				$login_slug = $settings['login_slug'] ?? null;
    
                if (!$login_slug) {
                    continue;
                }
    
                $suffix = $matches[2] ?? null;
                $has_admin_path = strpos($node->href, '/wp-admin/') !== false;
    
                if ( !$suffix || $suffix === '-d' ) {
                    $new_href = preg_replace('/wp-admin\/$/', "$login_slug/", $node->href);
    
                    if ($node->href !== $new_href) {
                        $node->href = $new_href;
                        $wp_admin_bar->add_node($node);
                    }
                } elseif ($has_admin_path) {
                    $wp_admin_bar->remove_node($node->id);
                }
            }
        }
    }
	
	/**
	 * new_login_url
	 *
	 * @param  mixed $scheme
	 * @return void
	 */
	public function new_login_url( $scheme = null ) {

		/**
		 * Filters the login URL.
		 *
		 * @since 1.0.0
		 *
		 * @param string $url The URL to the login page.
		 */
		$url = apply_filters( 'wpmastertoolkit/move_login_url/home_url', home_url( '/', $scheme ) );

		$settings	= $this->get_settings();
		$login_slug	= $settings['login_slug'] ?? '';

		return get_option('permalink_structure') 
			? $this->user_trailingslashit($url . $login_slug)
			: $url . '?' . $login_slug;
	}
	
	/**
	 * new_redirect_url
	 *
	 * @param  mixed $scheme
	 * @return void
	 */
	public function new_redirect_url( $scheme = null ) {
		$base_url = home_url('/', $scheme);

		$settings		= $this->get_settings();
		$redirect_slug	= $settings['redirect_slug'] ?? '';
	
		return get_option('permalink_structure') 
			? $this->user_trailingslashit($base_url . $redirect_slug)
			: $base_url . '?' . $redirect_slug;
	}

	/**
	 * Prevent bug on TranslatePress plugin if the subdirectory is added to the default language
	 *
	 * @param  mixed $settings
	 * @return void
	 */
	public function prevent_trp_subdirectory_on_custom_login_url( $settings ) {
		// check if the current URL is the custom login URL
		if ( $this->is_custom_login_uri( $_SERVER['REQUEST_URI'] ) ) {
			// set the option to 'no' to prevent TRP from adding the subdirectory
			$settings['add-subdirectory-to-default-language'] = 'no';
		}
		return $settings;
	}
	
	/**
	 * Check if the current URI is the custom login URI
	 *
	 * @param  mixed $uri
	 * @return void
	 */
	public function is_custom_login_uri($uri){
		$settings	= $this->get_settings();
		$login_slug	= $settings['login_slug'] ?? null;

		if ( preg_match( '/^\/' . preg_quote( $login_slug, '/' ) . '(\/|\?|$)/', $uri ) ) {
			return true;
		}

		return false;
	}
	
	
	/**
	 * save_submenu
	 *
	 * @return void
	 */
	public function save_submenu() {
        
		$nonce = sanitize_text_field( $_POST['_wpnonce'] ?? '' );
        
		if ( wp_verify_nonce($nonce, $this->nonce_action) ) {

			$this->default_settings = $this->get_default_settings();

			$new_settings       = $this->sanitize_settings( $_POST[$this->option_id] ?? array() );
			
			$this->save_settings( $new_settings );
            wp_safe_redirect( sanitize_url( $_SERVER['REQUEST_URI'] ?? '' ) );
			exit;
		}
    }
	
	/**
	 * redirect_export_data
	 *
	 * @return void
	 */
	public function redirect_export_data() {
		if ( ! empty( $_GET ) && isset( $_GET['action'] ) && 'confirmaction' === sanitize_text_field($_GET['action']) && isset( $_GET['request_id'] ) && isset( $_GET['confirm_key'] ) ) {
			$request_id = (int) sanitize_text_field($_GET['request_id']);
			$key        = wp_unslash( sanitize_text_field( $_GET['confirm_key'] ) );
			$result     = wp_validate_user_request_key( $request_id, $key );
			if ( ! is_wp_error( $result ) ) {
				wp_redirect( add_query_arg( array(
					'action'      => 'confirmaction',
					'request_id'  => $request_id,
					'confirm_key' => $key
				), $this->new_login_url()
				) );
				exit();
			}
		}
	}
	
	/**
	 * plugins_loaded
	 *
	 * @return void
	 */
	public function plugins_loaded() {

		global $pagenow;

		/**
		 * wpmastertoolkit/move_login_url/signup_enable use this filter to enable or disable the signup feature.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $enable_signup Enable or disable the signup feature.
		 */
		if ( ! is_multisite()
		     && ( strpos( rawurldecode( $_SERVER['REQUEST_URI'] ), 'wp-signup' ) !== false
		          || strpos( rawurldecode( $_SERVER['REQUEST_URI'] ), 'wp-activate' ) !== false ) && apply_filters( 'wpmastertoolkit/move_login_url/signup_enable', false ) === false ) {

			wp_die( esc_html__( 'This feature is not enabled.', 'wpmastertoolkit' ), 403 );

		}

		$request = parse_url( rawurldecode( $_SERVER['REQUEST_URI'] ) );

		$settings	= $this->get_settings();
		$login_slug	= $settings['login_slug'] ?? null;

		if ( ( strpos( rawurldecode( $_SERVER['REQUEST_URI'] ), 'wp-login.php' ) !== false
		       || ( isset( $request['path'] ) && untrailingslashit( $request['path'] ) === site_url( 'wp-login', 'relative' ) ) )
		     && ! is_admin() ) {

			$this->wp_custom_login_php = true;

			$_SERVER['REQUEST_URI'] = $this->user_trailingslashit( '/' . str_repeat( '-/', 10 ) );

			$pagenow = 'index.php';

		} elseif ( ( isset( $request['path'] ) && untrailingslashit( $request['path'] ) === home_url( $login_slug, 'relative' ) )
		           || ( ! get_option( 'permalink_structure' )
		                && isset( $_GET[ $login_slug ] )
		                && empty( $_GET[ $login_slug ] ) ) ) {

			$_SERVER['SCRIPT_NAME'] = $login_slug;

			$pagenow = 'wp-login.php';

		} elseif ( ( strpos( rawurldecode( $_SERVER['REQUEST_URI'] ), 'wp-register.php' ) !== false
		             || ( isset( $request['path'] ) && untrailingslashit( $request['path'] ) === site_url( 'wp-register', 'relative' ) ) )
		           && ! is_admin() ) {

			$this->wp_custom_login_php = true;

			$_SERVER['REQUEST_URI'] = $this->user_trailingslashit( '/' . str_repeat( '-/', 10 ) );

			$pagenow = 'index.php';
		}

	}
	
	/**
	 * setup_theme
	 *
	 * @return void
	 */
	public function setup_theme() {
		global $pagenow;

		if ( ! is_user_logged_in() && 'customize.php' === $pagenow ) {
			wp_die( esc_html__( 'This has been disabled', 'wpmastertoolkit' ), 403 );
		}
	}
	
	/**
	 * wp_loaded
	 *
	 * @return void
	 */
	public function wp_loaded() {

		global $pagenow;

		$request = parse_url( rawurldecode( $_SERVER['REQUEST_URI'] ) );

		/**
		 * Fires before redirecting the user to the new login URL.
		 *
		 * @since 1.0.0
		 *
		 * @param array $request The request URL.
		 */
		do_action( 'wpmastertoolkit/move_login_url/before_redirect', $request );

		if ( ! ( isset( $_GET['action'] ) && $_GET['action'] === 'postpass' && isset( $_POST['post_password'] ) ) ) {

			if ( is_admin() && ! is_user_logged_in() && ! defined( 'WP_CLI' ) && ! defined( 'DOING_AJAX' ) && ! defined( 'DOING_CRON' ) && $pagenow !== 'admin-post.php' && $request['path'] !== '/wp-admin/options.php' ) {
				wp_safe_redirect( $this->new_redirect_url() );
				die();
			}

			if ( ! is_user_logged_in() && isset( $_GET['wc-ajax'] ) && $pagenow === 'profile.php' ) {
				wp_safe_redirect( $this->new_redirect_url() );
				die();
			}

			if ( ! is_user_logged_in() && isset( $request['path'] ) && $request['path'] === '/wp-admin/options.php' ) {
				header('Location: ' . $this->new_redirect_url() );
				die;
			}

			if ( $pagenow === 'wp-login.php' && isset( $request['path'] ) && $request['path'] !== $this->user_trailingslashit( $request['path'] ) && get_option( 'permalink_structure' ) ) {
				wp_safe_redirect( $this->user_trailingslashit( $this->new_login_url() )
				                  . ( ! empty( $_SERVER['QUERY_STRING'] ) ? '?' . $_SERVER['QUERY_STRING'] : '' ) );

				die;

			} elseif ( $this->wp_custom_login_php ) {

				if ( ( $referer = wp_get_referer() )
				     && strpos( $referer, 'wp-activate.php' ) !== false
				     && ( $referer = parse_url( $referer ) )
				     && ! empty( $referer['query'] ) ) {

					parse_str( $referer['query'], $referer );

					@require_once WPINC . '/ms-functions.php';

					if ( ! empty( $referer['key'] )
					     && ( $result = wpmu_activate_signup( $referer['key'] ) )
					     && is_wp_error( $result )
					     && ( $result->get_error_code() === 'already_active'
					          || $result->get_error_code() === 'blog_taken' ) ) {

						wp_safe_redirect( $this->new_login_url()
						                  . ( ! empty( $_SERVER['QUERY_STRING'] ) ? '?' . $_SERVER['QUERY_STRING'] : '' ) );

						die;

					}

				}

				$this->wp_template_loader();

			} elseif ( $pagenow === 'wp-login.php' ) {
				global $error, $interim_login, $action, $user_login;

				$redirect_to = admin_url();

				$requested_redirect_to = '';
				if ( isset( $_REQUEST['redirect_to'] ) ) {
					$requested_redirect_to = sanitize_url( $_REQUEST['redirect_to'] );
				}

				if ( is_user_logged_in() ) {
					$user = wp_get_current_user();
					if ( ! isset( $_REQUEST['action'] ) ) {
						/**
						 * Fires after a user has successfully logged in.
						 *
						 * @since 1.0.0
						 *
						 * @param string $redirect_to           The redirect destination URL.
						 * @param string $requested_redirect_to The requested redirect destination URL passed as a parameter.
						 * @param WP_User $user                 The user object.
						 */
						$logged_in_redirect = apply_filters( 'wpmastertoolkit/move_login_url/logged_in_redirect', $redirect_to, $requested_redirect_to, $user );
						wp_safe_redirect( $logged_in_redirect );
						die();
					}
				}

				@require_once ABSPATH . 'wp-login.php';

				die;

			}

		}

	}
	
	/**
	 * site_url
	 *
	 * @param  mixed $url
	 * @param  mixed $path
	 * @param  mixed $scheme
	 * @param  mixed $blog_id
	 * @return void
	 */
	public function site_url( $url, $path, $scheme, $blog_id ) {

		return $this->filter_wp_custom_login_php( $url, $scheme );

	}
	
	/**
	 * network_site_url
	 *
	 * @param  mixed $url
	 * @param  mixed $path
	 * @param  mixed $scheme
	 * @return void
	 */
	public function network_site_url( $url, $path, $scheme ) {

		return $this->filter_wp_custom_login_php( $url, $scheme );

	}
	
	/**
	 * wp_redirect
	 *
	 * @param  mixed $location
	 * @param  mixed $status
	 * @return void
	 */
	public function wp_redirect( $location, $status ) {

		if ( strpos( $location, 'https://wordpress.com/wp-login.php' ) !== false ) {
			return $location;
		}

		return $this->filter_wp_custom_login_php( $location );

	}
	
	/**
	 * filter_wp_custom_login_php
	 *
	 * @param  mixed $url
	 * @param  mixed $scheme
	 * @return void
	 */
	public function filter_wp_custom_login_php( $url, $scheme = null ) {

		if ( strpos( $url, 'wp-login.php?action=postpass' ) !== false ) {
			return $url;
		}

		if ( strpos( $url, 'wp-login.php' ) !== false && strpos( wp_get_referer(), 'wp-login.php' ) === false ) {

			if ( is_ssl() ) {

				$scheme = 'https';

			}

			$args = explode( '?', $url );

			if ( isset( $args[1] ) ) {

				parse_str( $args[1], $args );

				if ( isset( $args['login'] ) ) {
					$args['login'] = rawurlencode( $args['login'] );
				}

				$url = add_query_arg( $args, $this->new_login_url( $scheme ) );

			} else {

				$url = $this->new_login_url( $scheme );

			}

		}

		return $url;

	}
	
	/**
	 * forbidden_slugs
	 *
	 * @return array
	 */
	public function forbidden_slugs() {
		$wp = new \WP;
		return array_merge( $wp->public_query_vars, $wp->private_query_vars );
	}

	/**
	 *
	 * Update url redirect : wp-admin/options.php
	 *
	 * @param $login_url
	 * @param $redirect
	 * @param $force_reauth
	 *
	 * @return string
	 */
	public function login_url( $login_url, $redirect, $force_reauth ) {
		$redirect_404 = $this->new_redirect_url();
		if ( is_404() ) return $redirect_404;
		
		if ( $force_reauth === false ) return $login_url;

		if ( empty( $redirect ) ) return $login_url;

		$redirect = explode( '?', $redirect );

		if ( $redirect[0] === admin_url( 'options.php' ) ) {
			$login_url = admin_url();
		}

		return $login_url;
	}
    
    /**
     * Add a submenu
     * 
     * @since   1.0.0
     */
    public function add_submenu(){

        add_submenu_page(
            'wp-mastertoolkit-settings',
            $this->header_title,
            $this->header_title,
            'manage_options',
            'wp-mastertoolkit-settings-move-login-url', 
            array( $this, 'render_submenu'),
            null
        );
    }

    /**
     * Render the submenu
     * 
     * @since   1.0.0
     */
    public function render_submenu() {

        $submenu_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/move-login-url.asset.php' );
        wp_enqueue_style( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/move-login-url.css', array(), $submenu_assets['version'], 'all' );
        wp_enqueue_script( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/move-login-url.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );
        wp_localize_script( 'WPMastertoolkit_submenu', 'wpmastertoolkit', array(
            'home_url' => esc_url( home_url() . "/" ),
        ) );

        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/submenu/header.php';
        $this->submenu_content();
        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/submenu/footer.php';
    }
    
    /**
     * submenu_content
     *
     * @return void
     */
    private function submenu_content() {

		$settings		= $this->get_settings();
		$login_slug		= $settings['login_slug'] ?? null;
		$redirect_slug	= $settings['redirect_slug'] ?? null;

        ?>
            <div class="wp-mastertoolkit__section">
                <div class="wp-mastertoolkit__section__desc"><?php esc_html_e( 'Change the login URL and prevent access to the wp-login.php page and the wp-admin directory to non-connected people.', 'wpmastertoolkit' ); ?></div>
                <div class="wp-mastertoolkit__section__body">
                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Login URL', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
                            <div class="wp-mastertoolkit__input-text slug-url">
                                <div>
									<code>
                                    	<?php echo esc_url( home_url() . "/" ); ?>
									</code>
                                </div>
                                <div>
									<input type="hidden" class="home-url" value="<?php echo esc_url( home_url() . "/" ); ?>">
                                    <input class="slug-input" type="text" name="<?php echo esc_attr( $this->option_id . '[login_slug]' ); ?>" value="<?php echo esc_attr( $login_slug ); ?>" id="login-slug">
                                </div>
                                <button class="copy-button">
									<?php echo wp_kses( file_get_contents(WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/copy.svg'), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Redirection URL', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
                            <div class="wp-mastertoolkit__input-text slug-url">
                                <div>
									<code>
										<?php echo esc_url( home_url() . "/" ); ?>
									</code>
                                </div>
                                <div>
                                    <input type="text" name="<?php echo esc_attr( $this->option_id . '[redirect_slug]' ); ?>" value="<?php echo esc_attr( $redirect_slug ); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php
    }
}
