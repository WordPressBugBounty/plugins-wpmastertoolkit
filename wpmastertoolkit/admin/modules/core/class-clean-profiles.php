<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Clean Profiles
 * Description: 
 * @since 1.3.0
 */
class WPMastertoolkit_Clean_Profiles {

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

        $this->option_id    = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_clean_profiles';
        $this->nonce_action = $this->option_id . '_action';

        add_action( 'init', array( $this, 'class_init' ) );
        add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
        add_action( 'admin_init', array( $this, 'save_submenu' ) );
        add_action( 'admin_head', array( $this, 'hide_fields' ), 999 );
    }

    /**
     * Initialize the class
     */
    public function class_init() {
        $this->header_title = esc_html__( 'Clean Profiles', 'wpmastertoolkit' );
    }

    /**
     * Hide the fields
     */
    public function hide_fields() {

        $settings = $this->get_settings();
        $fields   = $settings['fields'];
        $styles   = '';
        
        foreach ( $fields as $field => $value ) {
            
            if ( $value['enabled'] === '1' ) {
                $styles .= ".{$field} { display: none; }";
            }
        }

        ?>
            <style>
                <?php echo esc_html( $styles ); ?>
            </style>
        <?php
    }

    /**
     * get_settings
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
     */
    public function add_submenu(){

        add_submenu_page(
            'wp-mastertoolkit-settings',
            $this->header_title,
            $this->header_title,
            'manage_options',
            'wp-mastertoolkit-settings-clean-profiles', 
            array( $this, 'render_submenu'),
            null
        );
    }

    /**
     * Render the submenu
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
     */
    public function sanitize_settings($new_settings){

        $this->default_settings = $this->get_default_settings();
        $sanitized_settings = array();

        foreach ( $this->default_settings as $settings_key => $settings_value ) {
            
            switch ($settings_key) {
                case 'fields':
                    foreach ( $settings_value as $field => $value ) {
                        $sanitized_settings[$settings_key][$field]            = $value;
                        $sanitized_settings[$settings_key][$field]['enabled'] = sanitize_text_field($new_settings[$settings_key][$field]['enabled'] ?? $value['enabled'] );
                    }
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
            'fields' => $this->get_user_profile_fields(),
        );
    }

    /**
     * Add the submenu content
     *
     */
    private function submenu_content() {
        $this->settings = $this->get_settings();
        $fields = $this->settings['fields'];

        ?>
            <div class="wp-mastertoolkit__section">
                <div class="wp-mastertoolkit__section__desc"><?php esc_html_e('Tidy up user profiles by removing sections you do not utilise.', 'wpmastertoolkit'); ?></div>
                <div class="wp-mastertoolkit__section__body">

                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e('Sections to hide', 'wpmastertoolkit'); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
                            <?php foreach ( $fields as $field_id => $field_value ): ?>
                                <div class="wp-mastertoolkit__checkbox">
                                    <label class="wp-mastertoolkit__checkbox__label">
                                        <input type="hidden" name="<?php echo esc_attr( $this->option_id . '[fields]['. $field_id .'][enabled]' ); ?>" value="0">
                                        <input type="checkbox" name="<?php echo esc_attr( $this->option_id . '[fields]['. $field_id .'][enabled]' ); ?>" value="1"<?php checked( $this->settings['fields'][$field_id]['enabled'] ?? '', '1' ); ?>>
                                        <span class="mark"></span>
                                        <span class="wp-mastertoolkit__checkbox__label__text"><?php echo esc_html($field_value['name']); ?></span>
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
     * Get the user profile fields
     */
    private function get_user_profile_fields() {

        $user_profile_fields = array(
            'user-admin-color-wrap' => array(
                'enabled' => '1',
                'name'    => __('Admin Color Scheme', 'wpmastertoolkit'),
            ),
            'user-admin-bar-front-wrap' => array(
                'enabled' => '1',
                'name'    => __('Toolbar', 'wpmastertoolkit'),
            ),
            'user-description-wrap' => array(
                'enabled' => '1',
                'name'    => __('Biographical Info', 'wpmastertoolkit'),
            ),
            'user-role-wrap' => array(
                'enabled' => '1',
                'name'    => __('Role', 'wpmastertoolkit'),
            ),
            'user-email-wrap' => array(
                'enabled' => '1',
                'name'    => __('Email', 'wpmastertoolkit'),
            ),
            'user-pass1-wrap' => array(
                'enabled' => '1',
                'name'    => __('New Password', 'wpmastertoolkit'),
            ),
            'user-generate-reset-link-wrap' => array(
                'enabled' => '1',
                'name'    => __('Reset Password', 'wpmastertoolkit'),
            ),
        );

        return $user_profile_fields;
    }
}
