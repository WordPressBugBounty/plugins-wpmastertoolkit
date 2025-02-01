<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Custom Admin CSS
 * Description: Add custom CSS on all admin pages for all user roles.
 * @since 1.4.0
 */
class WPMastertoolkit_Custom_Admin_CSS {

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
        $this->option_id    = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_custom_admin_css';
        $this->nonce_action = $this->option_id . '_action';

        add_action( 'init', array( $this, 'class_init' ) );
        add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
        add_action( 'admin_init', array( $this, 'save_submenu' ) );
        add_filter( 'admin_print_footer_scripts', array( $this, 'custom_admin_css' ) );
    }

    /**
     * Initialize the class
     * 
     * @since    1.4.0
     * @return   void
     */
    public function class_init() {
        $this->header_title = esc_html__( 'Custom Admin CSS', 'wpmastertoolkit' );
    }

    /**
     * Enqueue custom admin CSS
     * 
     * @since    1.4.0
     */
    public function custom_admin_css() {

        $settings = $this->get_settings();
        $code_snippet = $settings['code_snippet'] ?? '';

        ?>
        <style type="text/css">
            <?php echo wp_strip_all_tags( wp_unslash( $code_snippet ) ); ?>
        </style>
        <?php
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
            'wp-mastertoolkit-settings-custom-admin-css', 
            array( $this, 'render_submenu' ),
            null
        );
    }

    /**
     * Render the submenu
     * 
     * @since   1.4.0
     */
    public function render_submenu() {
        $code_editor = wp_enqueue_code_editor( array( 
            'type' => 'css',
            'codemirror' => array(
                'mode' => array(
                    'name'      => 'css',
                    'startOpen' => true
                ),
                'inputStyle'      => 'textarea',
                'matchBrackets'   => true,
                'extraKeys'       => array(
                    'Alt-F'      => 'findPersistent',
                    'Ctrl-Space' => 'autocomplete',
                    'Ctrl-/'     => 'toggleComment',
                    'Cmd-/'      => 'toggleComment',
                    'Alt-Up'     => 'swapLineUp',
                    'Alt-Down'   => 'swapLineDown',
                ),
                'lint'             => true,
                'direction'        => 'ltr',
                'colorpicker'      => array( 'mode' => 'edit' ),
                'foldOptions'      => array( 'widget' => '...' ),
                'theme'            => 'wpmastertoolkit',
                'continueComments' => true,
            ),
        ) );

        $submenu_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/custom-admin-css.asset.php' );
        wp_enqueue_style( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/custom-admin-css.css', array(), $submenu_assets['version'], 'all' );
        wp_enqueue_script( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/custom-admin-css.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );
        wp_localize_script( 'WPMastertoolkit_submenu', 'wpmastertoolkit_code_snippets', array(
            'code_editor' => $code_editor,
        ) );

        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/submenu/header.php';
        $this->submenu_content();
        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/submenu/footer.php';
    }

    /**
     * Save the submenu option
     * 
     * @since   1.4.0
     */
    public function save_submenu() {
		$nonce = sanitize_text_field( $_POST['_wpnonce'] ?? '' );

		if ( wp_verify_nonce( $nonce, $this->nonce_action ) ) {
            $new_settings = $this->sanitize_settings( $_POST[ $this->option_id ] ?? array() );
            $this->save_settings( $new_settings );
            wp_safe_redirect( sanitize_url( $_SERVER['REQUEST_URI'] ?? '' ) );
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
                case 'code_snippet':
                    $sanitized_settings[ $settings_key ] = sanitize_textarea_field( $new_settings[ $settings_key ] );
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
            'code_snippet' => '',
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
        $code_snippet   = $this->settings['code_snippet'] ?? '';
        ?>
            <div class="wp-mastertoolkit__section">
                <div class="wp-mastertoolkit__section__desc"><?php esc_html_e( "Add custom CSS on all admin pages for all user roles.", 'wpmastertoolkit'); ?></div>
                <div class="wp-mastertoolkit__section__body">
                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__content flex flex-wrap">
                            <textarea name="<?php echo esc_attr( $this->option_id . '[code_snippet]' ); ?>" id="code_snippet" class="widefat" rows="10"><?php echo esc_textarea( stripcslashes( $code_snippet ) ); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        <?php
    }
}
