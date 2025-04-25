<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Clean Up Admin Bar
 * Description: Remove various elements from the admin bar.
 * @since 1.4.0
 */
class WPMastertoolkit_Clean_Up_Admin_Bar {

    private $option_id;
    private $header_title;
    private $nonce_action;
    private $settings;
    private $default_settings;

    /**
     * Invoke the hooks
     * 
     * @since    1.4.0
     */
    public function __construct() {
        $this->option_id    = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_clean_up_admin_bar';
        $this->nonce_action = $this->option_id . '_action';

        add_action( 'init', array( $this, 'class_init' ) );
        add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
        add_action( 'admin_init', array( $this, 'save_submenu' ) );
        add_filter( 'admin_bar_menu', array( $this, 'clean_up_admin_bar' ), 5 );
        add_action( 'admin_head', array( $this, 'hide_help_drawer') );
    }

    /**
     * Initialize the class
     * 
     * @since    1.4.0
     * @return   void
     */
    public function class_init() {
        $this->header_title = esc_html__( 'Clean Up Admin Bar', 'wpmastertoolkit' );
    }

    /**
     * Clean up the admin bar
     * 
     * @since   1.4.0
     */
    public function clean_up_admin_bar( $wp_admin_bar ) {

        $settings = $this->get_settings();
        $elements = $settings['elements'] ?? array();

        if ( isset( $elements['logo_menu'] ) && $elements['logo_menu'] == '1' ) {
            remove_action( 'admin_bar_menu', 'wp_admin_bar_wp_menu', 10 );
        }

        if ( isset( $elements['customize_menu'] ) && $elements['customize_menu'] == '1' ) {
            remove_action( 'admin_bar_menu', 'wp_admin_bar_customize_menu', 40 );
        }

        if ( isset( $elements['updates_counter'] ) && $elements['updates_counter'] == '1' ) {
            remove_action( 'admin_bar_menu', 'wp_admin_bar_updates_menu', 50 );
        }

        if ( isset( $elements['comments_counter'] ) && $elements['comments_counter'] == '1' ) {
            remove_action( 'admin_bar_menu', 'wp_admin_bar_comments_menu', 60 );
        }

        if ( isset( $elements['new_content_menu'] ) && $elements['new_content_menu'] == '1' ) {
            remove_action( 'admin_bar_menu', 'wp_admin_bar_new_content_menu', 70 );
        }

        if ( isset( $elements['howdy'] ) && $elements['howdy'] == '1' ) {
            remove_action( 'admin_bar_menu', 'wp_admin_bar_my_account_item', 9991 );
            /**
             * Filter the logout label.
             *
             * @since 2.0.0
             *
             * @param string $title The logout label.
             */
            $title = apply_filters( 'wpmastertoolkit/clean_up_admin_bar/logout_label', '⏻' );

            $wp_admin_bar->add_menu( array(
                'id'     => 'logout',
                'parent' => 'top-secondary',
                'title'  => $title,
                'href'   => wp_logout_url(),
            ) );
        }
    }

    /**
     * Hide the Help tab
     * 
     * @since   1.4.0
     */
    public function hide_help_drawer() {

        if ( is_admin() ) {
        
            $settings = $this->get_settings();
            $elements = $settings['elements'] ?? array();

            if ( isset( $elements['help_drawer'] ) && $elements['help_drawer'] == '1' ) {
                $screen = get_current_screen();
                $screen->remove_help_tabs();
            }
        }
    }

    /**
     * Add a submenu
     * 
     * @since   1.4.0
     */
    public function add_submenu(){
        add_submenu_page(
            'wp-mastertoolkit-settings',
            $this->header_title,
            $this->header_title,
            'manage_options',
            'wp-mastertoolkit-settings-clean-up-admin-bar',
            array( $this, 'render_submenu'),
            null
        );
    }

    /**
     * Render the submenu
     * 
     * @since   1.4.0
     */
    public function render_submenu() {
        $submenu_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/clean-up-admin-bar.asset.php' );
        wp_enqueue_style( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/clean-up-admin-bar.css', array(), $submenu_assets['version'], 'all' );
        wp_enqueue_script( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/clean-up-admin-bar.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );

        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
        $this->submenu_content();
        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/footer.php';
    }

    /**
     * Save the submenu option
     * 
     * @since   1.4.0
     */
    public function save_submenu() {
		$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );

		if ( wp_verify_nonce( $nonce, $this->nonce_action ) ) {
			//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $new_settings = $this->sanitize_settings( wp_unslash( $_POST[ $this->option_id ] ?? array() ) );
            $this->save_settings( $new_settings );
            wp_safe_redirect( sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) );
			exit;
		}
    }

    /**
     * sanitize_settings
     * 
     * @since   1.4.0
     * @return array
     */
    public function sanitize_settings( $new_settings ){
        $this->default_settings = $this->get_default_settings();
        $sanitized_settings     = array();

        foreach ( $this->default_settings as $settings_key => $settings_value ) {
            switch ($settings_key) {
                case 'elements':
                    foreach ( $settings_value as $element => $element_status ) {
                        $sanitized_settings[ $settings_key ][ $element ] = sanitize_text_field( $new_settings[ $settings_key ][ $element ] ?? '0' );
                    }
                break;
            }
        }

        return $sanitized_settings;
    }

    /**
     * get_settings
     *
     * @since   1.4.0
     * @return void
     */
    private function get_settings(){
        $this->default_settings = $this->get_default_settings();
        return get_option( $this->option_id, $this->default_settings );
    }

    /**
     * Save settings
     * 
     * @since   1.4.0
     */
    private function save_settings( $new_settings ) {
		update_option( $this->option_id, $new_settings );
    }

    /**
     * get_default_settings
     *
     * @since   1.4.0
     * @return array
     */
    private function get_default_settings(){
        if( $this->default_settings !== null ) return $this->default_settings;

        return array(
            'elements' => $this->get_elements_settings(),
        );
    }

    /**
     * Add the submenu content
     * 
     * @since   1.4.0
     * @return void
     */
    private function submenu_content() {
        $this->settings = $this->get_settings();
        $elements       = $this->get_elements( false );

        ?>
            <div class="wp-mastertoolkit__section">
                <div class="wp-mastertoolkit__section__desc"><?php esc_html_e( "Remove various elements from the admin bar.", 'wpmastertoolkit'); ?></div>
                <div class="wp-mastertoolkit__section__body">
                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__content flex flex-wrap">
                            <?php foreach ( $elements as $element_id => $element_label ): ?>
                                <div class="wp-mastertoolkit__checkbox">
                                    <label class="wp-mastertoolkit__checkbox__label">
                                        <input type="hidden" name="<?php echo esc_attr( $this->option_id . '[elements][' . $element_id . ']' ); ?>" value="0">
                                        <input type="checkbox" name="<?php echo esc_attr( $this->option_id . '[elements][' . $element_id . ']' ); ?>" value="1"<?php checked( $this->settings['elements'][ $element_id ] ?? '', '1' ); ?>>
                                        <span class="mark"></span>
                                        <span class="wp-mastertoolkit__checkbox__label__text"><?php echo esc_html( $element_label ); ?></span>
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
     * Get the post types with default value
     * 
     * @return  array
     */
    private function get_elements() {
        $result = array(
            'logo_menu'        => __( 'Remove WordPress logo', 'wpmastertoolkit' ),
            'customize_menu'   => __( 'Remove customize menu', 'wpmastertoolkit' ),
            'updates_counter'  => __( 'Remove updates counter', 'wpmastertoolkit' ),
            'comments_counter' => __( 'Remove comments counter', 'wpmastertoolkit' ),
            'new_content_menu' => __( 'Remove new content menu', 'wpmastertoolkit' ),
            'howdy'            => __( 'Remove \'Howdy\'', 'wpmastertoolkit' ),
            'help_drawer'      => __( 'Remove the Help drawer', 'wpmastertoolkit' ),
        );

        return $result;
    }

    /**
     * Get the post types settings
     * 
     * @since   1.4.0
     * @return  array
     */
    private function get_elements_settings() {
        $result = array();

        foreach ( $this->get_elements() as $element_id => $element_label ) {
            $result[ $element_id ] = '1';
        }

        return $result;
    }
}
