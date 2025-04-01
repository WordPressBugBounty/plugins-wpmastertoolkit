<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Disable Dashboard Widgets
 * Description: 
 * @since 1.3.0
 */
class WPMastertoolkit_Disable_Dashboard_Widgets {

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

        $this->option_id        = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_disable_dashboard_widgets';
        $this->nonce_action     = $this->option_id . '_action';

        add_action( 'init', array( $this, 'class_init' ) );
        add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
        add_action( 'admin_init', array( $this, 'save_submenu' ) );
        add_action( 'wp_dashboard_setup', array( $this, 'disable_widgets' ), 99 );
    }

    /**
     * Initialize the class
     */
    public function class_init() {
        $this->header_title = esc_html__( 'Disable Dashboard Widgets', 'wpmastertoolkit' );
    }

    /**
     * Disable dashboard widgets
     */
    public function disable_widgets() {
        global $wp_meta_boxes;

        $settings = $this->get_settings();
        $widgets  = $settings['widgets'];

        if ( ! is_array( $widgets ) || empty( $widgets ) ) {
            return;
        }

        foreach ( $widgets as $widget_id => $widget ) {
            if ( '1' === $widget['enabled'] ) {
                $context  = $widget['context'];
                $priority = $widget['priority'];
                unset( $wp_meta_boxes['dashboard'][$context][$priority][$widget_id] );
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
            'wp-mastertoolkit-settings-disable-dashboard-widgets',
            array( $this, 'render_submenu'),
            null
        );
    }

    /**
     * Render the submenu
     * 
     */
    public function render_submenu() {

        $submenu_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/disable-dashboard-widgets.asset.php' );
        wp_enqueue_style( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/disable-dashboard-widgets.css', array(), $submenu_assets['version'], 'all' );
        wp_enqueue_script( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/disable-dashboard-widgets.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );

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

			//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $new_settings = $this->sanitize_settings( $_POST[$this->option_id] ?? array() );
            
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
                case 'widgets':
                    foreach ( $settings_value as $widget => $value ) {
                        $sanitized_settings[$settings_key][$widget]            = $this->default_settings[$settings_key][$widget];
                        $sanitized_settings[$settings_key][$widget]['enabled'] = sanitize_text_field($new_settings[$settings_key][$widget]['enabled'] ?? '0');
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
            'widgets' => $this->get_widgets_settings(),
        );
    }

    /**
     * Add the submenu content
     *
     */
    private function submenu_content() {
        $this->settings = $this->get_settings();
        $widgets        = $this->get_widgets();

        ?>
            <div class="wp-mastertoolkit__section">
                <div class="wp-mastertoolkit__section__desc"><?php esc_html_e('Clean up and speed up the dashboard by completely disabling some or all widgets. Disabled widgets won\'t load any assets nor show up under Screen Options.', 'wpmastertoolkit'); ?></div>
                <div class="wp-mastertoolkit__section__body">

                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e('Available widgets', 'wpmastertoolkit'); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
                            <?php foreach ( $widgets as $widget_id => $widget ): ?>
                                <div class="wp-mastertoolkit__checkbox">
                                    <label class="wp-mastertoolkit__checkbox__label">
                                        <input type="hidden" name="<?php echo esc_attr( $this->option_id . '[widgets]['. $widget_id .'][enabled]' ); ?>" value="0">
                                        <input type="checkbox" name="<?php echo esc_attr( $this->option_id . '[widgets]['. $widget_id .'][enabled]' ); ?>" value="1" <?php checked( $this->settings['widgets'][$widget_id]['enabled'] ?? '', '1' ); ?>>
                                        <span class="mark"></span>
                                        <span class="wp-mastertoolkit__checkbox__label__text"><?php echo esc_html($widget['title']); ?></span>
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
     * Get dashboard widgets
     */
    private function get_widgets() {

        $dashboard_widgets = array();
        $widgets_from_db   = get_option( $this->option_id, array() );

        if ( ! empty( $widgets_from_db ) && isset( $widgets_from_db['widgets'] ) && is_array( $widgets_from_db['widgets'] ) && ! empty( $widgets_from_db['widgets'] ) ) {

            $dashboard_widgets = $widgets_from_db['widgets'];

        } else {

            global $wp_meta_boxes;

            if ( ! isset( $wp_meta_boxes['dashboard'] ) ) {
                require_once ABSPATH . '/wp-admin/includes/dashboard.php';
                set_current_screen( 'dashboard' );
                wp_dashboard_setup();
            }
    
            if ( isset( $wp_meta_boxes['dashboard'] ) ) {
                foreach( $wp_meta_boxes['dashboard'] as $context => $priorities ) {
                    foreach ( $priorities as $priority => $widgets ) {
                        foreach( $widgets as $widget_id => $data ) {
    
                            $dashboard_widgets[$widget_id] = array(
                                'id'        => $widget_id,
                                'title'     => isset( $data['title'] ) ? wp_strip_all_tags( preg_replace( '/ <span.*span>/im', '', $data['title'] ) ) : __( 'Widget(without name)', 'wpmastertoolkit' ),
                                'context'   => $context,
                                'priority'  => $priority,
                            );
                        }
                    }
                }
            }
        }

        return $dashboard_widgets;
    }

    /**
     * Get dashboard widgets settings
     */
    private function get_widgets_settings() {

        $result = array();

        foreach ( $this->get_widgets() as $widget_id => $widget ) {

            $result[$widget_id] = $widget;
            $result[$widget_id]['enabled'] = '0';
        }

        return $result;
    }
}
