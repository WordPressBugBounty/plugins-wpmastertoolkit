<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Password Protection
 * Description: 
 * @since 1.3.0
 */
class WPMastertoolkit_Password_Protection {

    const COOKIE_ID             = 'wpmastertoolkit_password_protection';
    const COOKIE_PASSWORD       = 'MOeldTVhGnL18VfbDtXM7znSYXIUQn3z';
    const LOGIN_HEAD_ACTION     = 'wpmastertoolkit_password_protection_login_head';
    const ERROR_MESSAGES_ACTION = 'wpmastertoolkit_password_protection_error_messages';

    private $option_id;
    private $header_title;
    private $nonce_action;
    private $settings;
    private $default_settings;

    /**
     * Invoke the hooks
     * 
     */
    public function __construct() {

        $this->option_id        = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_password_protection';
        $this->nonce_action     = $this->option_id . '_action';

        add_action( 'init', array( $this, 'class_init' ) );
        add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
        add_action( 'admin_init', array( $this, 'save_submenu' ) );

        add_action( 'wp_before_admin_bar_render', array( $this, 'remind_admin' ) );
        add_action( 'admin_head', array( $this, 'remind_admin_style' ) );
        add_action( 'wp_head', array( $this, 'remind_admin_style' ) );
        add_action( 'init', array( $this, 'disable_page_caching' ), 1 );
        add_action( 'template_redirect', array( $this, 'show_login_form' ), 0 );
        add_action( 'init', array( $this, 'process_login' ), 1 );
        add_action( self::ERROR_MESSAGES_ACTION, array( $this, 'add_login_error_messages' ) );
        if ( function_exists( 'wp_site_icon' ) ) {
            add_action( self::LOGIN_HEAD_ACTION, 'wp_site_icon' );
        }
    }

    /**
     * Initialize the class
     */
    public function class_init() {
        $this->header_title = esc_html__( 'Password Protection', 'wpmastertoolkit' );
    }

    /**
     * Add menu to admin bar to remind the admin that password protection is enabled
     */
    public function remind_admin() {
        global $wp_admin_bar;

        if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
            $wp_admin_bar->add_menu( array(
                'id'    => 'wp_mastertoolkit_password_protection',
                'title' => '',
                'href'  => admin_url( 'admin.php?page=wp-mastertoolkit-settings-password-protection' ),
                'meta'  => array(
                    'title' => __( 'Password protection is currently enabled for this site.', 'wpmastertoolkit' ),
                ),
            ));
        }
    }

    /**
     * Add CSS for admin bar item
     */
    public function remind_admin_style() {

        if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
            ?>
                <style>
                    #wpadminbar ul li#wp-admin-bar-wp_mastertoolkit_password_protection {
                        background-color: #c32121;
                    }
                    #wpadminbar ul li#wp-admin-bar-wp_mastertoolkit_password_protection > .ab-item:before {
                        content: "\f160";
                        top: 2px;
                        color: #fff;
                        margin-right: 0px;
                    }
                    #wpadminbar ul li#wp-admin-bar-wp_mastertoolkit_password_protection:hover > .ab-item {
                        background-color: #af1d1d;
                        color: #fff;
                    }
                </style>
            <?php 
        }
    }

    /**
     * Disable page caching
     */
    public function disable_page_caching() {

        if ( !defined( 'DONOTCACHEPAGE' ) ) {
			//phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
            define( 'DONOTCACHEPAGE', true );
        }
    }

    /**
     * Show login form
     */
    public function show_login_form() {
        
        if ( is_user_logged_in() ) {
            if ( current_user_can( 'manage_options' ) ) {
                return;
            }
        }

        $auth_cookie = ( isset( $_COOKIE[ self::COOKIE_ID ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_ID ] ) ) : '' );

        if ( wp_check_password( self::COOKIE_PASSWORD, $auth_cookie ) ) {
            return;
        }

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( isset( $_REQUEST['protected-page'] ) && 'view' == $_REQUEST['protected-page'] ) {
            $password_protection_template = WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/password-protection/password-protection.php';
            load_template( $password_protection_template );
            exit;

        } else {

            $current_url = ( ( is_ssl() ? 'https://' : 'http://' ) ) . sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ?? '' ) ) . sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );
            $args        = array(
                'protected-page' => 'view',
                'source'         => urlencode( $current_url ),
            );

            $pwd_protect_login_url = add_query_arg( $args, home_url() );
            nocache_headers();
            wp_safe_redirect( $pwd_protect_login_url );
            exit;
        }
    }

    /**
     * Process login to access protected page content
     */
    public function process_login() {
        global $wpmtk_password_protection_errors;
        $wpmtk_password_protection_errors = new WP_Error();

        $settings        = $this->get_settings();
        $stored_password = $settings['password'] ?? '';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( isset( $_REQUEST['protected_page_pwd'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $password_input = sanitize_text_field( wp_unslash( $_REQUEST['protected_page_pwd'] ) );

            if ( ! empty( $password_input ) ) {

                if ( $password_input == $stored_password ) {

                    $expiration          = 0;
                    $hashed_cookie_value = wp_hash_password( self::COOKIE_PASSWORD );

                    setcookie( self::COOKIE_ID, $hashed_cookie_value, $expiration, COOKIEPATH, COOKIE_DOMAIN, false, true );

					// phpcs:ignore WordPress.Security.NonceVerification.Recommended
                    $redirect_to_url = ( isset( $_REQUEST['source'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['source'] ) ) : '' );
                    wp_safe_redirect( $redirect_to_url );
                    exit;

                } else {
                    $wpmtk_password_protection_errors->add( 'incorrect_password', __( 'Incorrect password.', 'wpmastertoolkit' ) );
                }

            } else {
                $wpmtk_password_protection_errors->add( 'empty_password', __( 'Password can not be empty.', 'wpmastertoolkit' ) );
            }
        }
    }

    /**
     * Add login error messages
     */
    public function add_login_error_messages() {
        global $wpmtk_password_protection_errors;

        if ( $wpmtk_password_protection_errors->get_error_code() ) {

            $messages = '';
            $errors   = '';

            foreach ( $wpmtk_password_protection_errors->get_error_codes() as $code ) {

                $severity = $wpmtk_password_protection_errors->get_error_data( $code );
                foreach ( $wpmtk_password_protection_errors->get_error_messages( $code ) as $error ) {
                    if ( 'message' == $severity ) {
                        $messages .= $error . '<br />';
                    } else {
                        $errors .= $error . '<br />';
                    }
                }
            }

            if ( ! empty( $messages ) ) {
                echo  '<p class="message">' . wp_kses_post( $messages ) . '</p>' ;
            }

            if ( ! empty( $errors ) ) {
                echo  '<div id="login_error">' . wp_kses_post( $errors ) . '</div>' ;
            }
        }
    }

    /**
     * get_settings
     *
     * @return void
     */
    public function get_settings(){
        $this->default_settings = $this->get_default_settings();
        return get_option( $this->option_id, $this->default_settings );
    }

    /**
     * Save settings
     */
    public function save_settings( $new_settings ) {
		update_option( $this->option_id, $new_settings );
    }

    /**
     * Add a submenu
     *
     */
    public function add_submenu(){

        WPMastertoolkit_Settings::add_submenu_page(
            'wp-mastertoolkit-settings',
            $this->header_title,
            $this->header_title,
            'manage_options',
            'wp-mastertoolkit-settings-password-protection', 
            array( $this, 'render_submenu'),
            null
        );
    }

    /**
     * Render the submenu
     * 
     */
    public function render_submenu() {

        $submenu_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/password-protection.asset.php' );
        wp_enqueue_style( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/password-protection.css', array(), $submenu_assets['version'], 'all' );
        wp_enqueue_script( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/password-protection.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );

        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
        $this->submenu_content();
        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/footer.php';
    }

    /**
     * Save the submenu option
     *
     */
    public function save_submenu() {

		$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
		
		if ( wp_verify_nonce($nonce, $this->nonce_action) ) {

			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $new_settings = $this->sanitize_settings( wp_unslash( $_POST[$this->option_id] ?? array() ) );
            
            $this->save_settings( $new_settings );
            wp_safe_redirect( sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) );
			exit;
		}
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
                case 'password':
                    $sanitized_settings[$settings_key] = sanitize_text_field( $new_settings[$settings_key] ?? '' );
                break;
            }
        }

        return $sanitized_settings;
    }

    /**
     * get_default_settings
     *
     * @return array
     */
    private function get_default_settings(){
        if( $this->default_settings !== null ) return $this->default_settings;

        return array(
            'password' => 'password',
        );
    }

    /**
     * Add the submenu content
     *
     */
    private function submenu_content() {
        $this->settings = $this->get_settings();
        $password       = $this->settings['password'] ?? '';

        ?>
            <div class="wp-mastertoolkit__section">
                <div class="wp-mastertoolkit__section__desc"><?php esc_html_e('Password-protect the entire site to hide the content from public view and search engine bots / crawlers. Logged-in administrators can still access the site as usual.', 'wpmastertoolkit'); ?></div>
                <div class="wp-mastertoolkit__section__body">
                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Set the password', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
                            <div class="wp-mastertoolkit__input-password">
                                <input type="password" name="<?php echo esc_attr( $this->option_id . '[password]' ); ?>" value="<?php echo esc_attr( $password ); ?>">
                                <span class="eye-show"><?php echo file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/eye-show.svg' );// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                <span class="eye-hide"><?php echo file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/eye-hide.svg' );// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php
    }
}
