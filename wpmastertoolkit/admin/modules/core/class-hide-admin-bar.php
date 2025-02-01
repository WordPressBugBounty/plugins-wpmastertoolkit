<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Hide Admin Bar
 * Description: Hide the admin bar on the front end of your website for either specific user roles or all users.
 * @since 1.0.0
 */
class WPMastertoolkit_Hide_Admin_Bar {

    private $option_id;
    private $header_title;
    private $nonce_action;
    private $settings;
    private $default_settings;
    private $wp_roles;

    /**
     * Invoke Wp Hooks
	 *
     * @since    1.0.0
	 */
    public function __construct() {

        $this->wp_roles         = new WP_Roles();
        $this->option_id        = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_hide_admin_bar';
        $this->nonce_action     = $this->option_id . '_action';

        add_action( 'init', array( $this, 'class_init' ) );
        add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
        add_action( 'admin_init', array( $this, 'save_submenu' ) );
        add_filter( 'show_admin_bar', array( $this, 'hide_admin_bar' ), 999 );
    }

    /**
     * Initialize the class
     */
    public function class_init() {
        $this->header_title = esc_html__( 'Hide Admin Bar', 'wpmastertoolkit' );
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
            'wp-mastertoolkit-settings-hide-admin-bar', 
            array( $this, 'render_submenu'),
            null
        );
    }

    /**
     * get_settings
     *
     * @return array
     */
    public function get_settings(){
        if( $this->settings !== null ) return $this->settings;
        return get_option( $this->option_id, $this->get_default_settings() );
    }
    
    /**
     * get_default_settings
     *
     * @return array
     */
    private function get_default_settings() {
        if( $this->default_settings !== null ) return $this->default_settings;
        return array(
            'user_roles' => $this->get_user_roles()
        );
    }

    /**
     * Save settings
     */
    public function save_settings( $new_settings ) {

		update_option( $this->option_id, $new_settings );
    }

    /**
     * Sanitize the settings
     * 
     * @return array
     */
    public function sanitize_settings($new_settings){

        $this->default_settings = $this->get_default_settings();
        $sanitized_settings = array();

        foreach ( $this->default_settings as $settings_key => $settings_value ) {
            
            switch ($settings_key) {
                case 'user_roles':
                    foreach ( $settings_value as $post_type => $post_status ) {
                        $sanitized_settings[$settings_key][$post_type] = sanitize_text_field($new_settings[$settings_key][$post_type] ?? '0');
                    }
                break;
            }
        }
            
        return $sanitized_settings;
    }


    /**
     * Render the submenu
     * 
     * @since   1.0.0
     */
    public function render_submenu() {

        $submenu_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/hide-admin-bar.asset.php' );
        wp_enqueue_style( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/hide-admin-bar.css', array(), $submenu_assets['version'], 'all' );
        wp_enqueue_script( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/hide-admin-bar.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );

        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/submenu/header.php';
        $this->submenu_content();
        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/submenu/footer.php';
    }

     /**
     * Save the submenu option
     * 
     * @since   1.0.0
     */
    public function save_submenu() {

		$nonce = sanitize_text_field( $_POST['_wpnonce'] ?? '' );
		
		if ( wp_verify_nonce($nonce, $this->nonce_action) ) {

            $new_settings       = $this->sanitize_settings( $_POST[$this->option_id] ?? array() );

            $this->save_settings( $new_settings );
            wp_safe_redirect( sanitize_url( $_SERVER['REQUEST_URI'] ?? '' ) );
			exit;
		}
    }

    /**
     * Hide the admin bar
     * 
     * @since   1.0.0
     */
    public function Hide_admin_bar( $show_admin_bar ) {
        $this->settings     = $this->get_settings();
        $current_user       = wp_get_current_user();
        $current_user_roles = $current_user->roles;
        $user_roles         = $this->settings['user_roles'] ?? array();
        $user_roles_blocked = array();

        if ( count( $current_user_roles ) === 0 ) {
            return false;
        }

        foreach ( $user_roles as $user_role => $user_role_value ) {

            if ( $user_role_value === '1' ) {
                $user_roles_blocked[] = $user_role;
            }
        }

        foreach ( $current_user_roles as $user_role ) {
            
            if ( in_array( $user_role, $user_roles_blocked ) ) {
                return false;
            }
        }

        return $show_admin_bar;
    }

    /**
     * Add the submenu content
     * 
     * @since   1.0.0
     */
    private function submenu_content() {

        $this->settings     = $this->get_settings();
        $user_roles         = $this->wp_roles->get_names();

        ?>
            <div class="wp-mastertoolkit__section">
                <div class="wp-mastertoolkit__section__desc"><?php esc_html_e('Hide the admin bar on the front end of your website for either specific user roles or all users.', 'wpmastertoolkit'); ?></div>
                <div class="wp-mastertoolkit__section__body">
                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__content">
                            <?php foreach ( $user_roles as $role_slug => $role_label ): ?>
                                <div class="wp-mastertoolkit__checkbox">
                                    <label class="wp-mastertoolkit__checkbox__label">
                                        <input type="hidden" name="<?php echo esc_attr( $this->option_id . '[user_roles]['.$role_slug .']' ); ?>" value="0">
                                        <input type="checkbox" name="<?php echo esc_attr( $this->option_id . '[user_roles]['.$role_slug .']' ); ?>" value="1"<?php checked( $this->settings['user_roles'][$role_slug]??'', '1' ); ?>>
                                        <span class="mark"></span>
                                        <span class="wp-mastertoolkit__checkbox__label__text"><?php echo esc_html($role_label); ?> <span>(<?php echo esc_html($role_slug); ?>)</span></span>
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
     * Get the user roles
     * 
     * @since   1.0.0
     * @return  array
     */
    private function get_user_roles() {

        $result = array();

        foreach ( $this->wp_roles->get_names() as $role_slug => $role_label ) {
            $result[$role_slug] = '1';
        }

        return $result;
    }

}