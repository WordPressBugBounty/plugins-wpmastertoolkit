<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Redirect After Login
 * Description: 
 * @since 1.3.0
 */
class WPMastertoolkit_Redirect_After_Login {

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

        $this->option_id        = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_redirect_after_login';
        $this->nonce_action     = $this->option_id . '_action';

        add_action( 'init', array( $this, 'class_init' ) );
        add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
        add_action( 'admin_init', array( $this, 'save_submenu' ) );
        add_filter( 'wp_login', array( $this, 'redirect_after_login' ), 5, 2 );
    }

    /**
     * Initialize the class
     */
    public function class_init() {
        $this->header_title = esc_html__( 'Redirect After Login', 'wpmastertoolkit' );
    }

    /**
     * Redirect after login
     */
    public function redirect_after_login( $username, $user ) {

        $settings    = $this->get_settings();
        $roles       = $settings['roles'];
        $redirect_to = trim( trim( $settings['redirect_to'], '/' ) );

        if ( empty( $redirect_to ) ) {
            return;
        }

        if ( ! isset( $user->roles ) || ! is_array( $user->roles ) ) {
            return;
        }

        $user_roles = array_flip( $user->roles );
        $intersect  = array_intersect_key( $roles, $user_roles );

        foreach ( $intersect as $role => $value ) {

            if ( '1' === $value ) {
                wp_safe_redirect( home_url( $redirect_to ) );
                exit();
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

        add_submenu_page(
            'wp-mastertoolkit-settings',
            $this->header_title,
            $this->header_title,
            'manage_options',
            'wp-mastertoolkit-settings-redirect-after-login', 
            array( $this, 'render_submenu'),
            null
        );
    }

    /**
     * Render the submenu
     * 
     */
    public function render_submenu() {

        $submenu_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/disable-comments.asset.php' );
        wp_enqueue_style( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/disable-comments.css', array(), $submenu_assets['version'], 'all' );
        wp_enqueue_script( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/disable-comments.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );

        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/submenu/header.php';
        $this->submenu_content();
        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/submenu/footer.php';
    }

    /**
     * Save the submenu option
     *
     */
    public function save_submenu() {

		$nonce = sanitize_text_field( $_POST['_wpnonce'] ?? '' );
		
		if ( wp_verify_nonce($nonce, $this->nonce_action) ) {

            $new_settings = $this->sanitize_settings( $_POST[$this->option_id] ?? array() );
            
            $this->save_settings( $new_settings );
            wp_safe_redirect( sanitize_url( $_SERVER['REQUEST_URI'] ?? '' ) );
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
                case 'roles':
                    foreach ( $settings_value as $role => $value ) {
                        $sanitized_settings[$settings_key][$role] = sanitize_text_field($new_settings[$settings_key][$role] ?? '0');
                    }
                break;
                case 'redirect_to':
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
            'roles'       => $this->get_roles_settings(),
            'redirect_to' => '',
        );
    }

    /**
     * Add the submenu content
     *
     */
    private function submenu_content() {
        $this->settings = $this->get_settings();
        $redirect_to    = $this->settings['redirect_to'] ?? '';
        $roles          = $this->get_roles();

        ?>
            <div class="wp-mastertoolkit__section">
                <div class="wp-mastertoolkit__section__desc"><?php esc_html_e('Set custom redirect URL for all or some user roles after login.', 'wpmastertoolkit'); ?></div>
                <div class="wp-mastertoolkit__section__body">

                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Redirect to', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
                            <div class="wp-mastertoolkit__input-text slug-url">
                                <div>
                                    <code>
                                        <?php echo get_site_url() . '/'; ?>
                                    </code>
                                </div>
                                <div>
                                    <input type="text" name="<?php echo esc_attr( $this->option_id . '[redirect_to]' ); ?>" value="<?php echo esc_attr( $redirect_to ); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e('Redirected roles', 'wpmastertoolkit'); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
                            <?php foreach ( $roles as $role_slug => $role_name ): ?>
                                <div class="wp-mastertoolkit__checkbox">
                                    <label class="wp-mastertoolkit__checkbox__label">
                                        <input type="hidden" name="<?php echo esc_attr( $this->option_id . '[roles]['. $role_slug .']' ); ?>" value="0">
                                        <input type="checkbox" name="<?php echo esc_attr( $this->option_id . '[roles]['. $role_slug .']' ); ?>" value="1"<?php checked( $this->settings['roles'][$role_slug]??'', '1' ); ?>>
                                        <span class="mark"></span>
                                        <span class="wp-mastertoolkit__checkbox__label__text"><?php echo esc_html($role_name); ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php
    }

    /**
     * Get the wp roles
     * 
     */
    private function get_roles() {
        global $wp_roles;

        $roles = $wp_roles->get_names();

        return $roles;
    }

    /**
     * Get the roles settings
     */
    private function get_roles_settings() {

        $result = array();

        foreach ( $this->get_roles() as $role_slug => $role_name ) {

            $result[$role_slug] = '0';
        }

        return $result;
    }
}
