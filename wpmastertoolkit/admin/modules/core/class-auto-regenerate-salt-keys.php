<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Auto Regenerate Salt Keys
 * Description: 
 * @since 1.3.0
 * @updated 1.7.0
 */
class WPMastertoolkit_Auto_Regenerate_Salt_Keys {

    private $option_id;
    private $nonce_action;
    private $cron_name;
    private $settings;
    private $default_settings;
    private $header_title;
    private $change_now_nonce_action;
    private $change_now_nonce_name;
    private $change_now_btn_name;

    /**
     * Invoke the hooks
     * 
     */
    public function __construct() {

        $this->option_id               = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_auto_regenerate_salt_keys';
        $this->nonce_action            = $this->option_id . '_action';
        $this->cron_name               = $this->option_id . '_cron';
        $this->change_now_nonce_action = 'wp-mastertoolkit-auto-regenerate-salt-keys';
        $this->change_now_nonce_name   = 'wp-mastertoolkit-auto-regenerate-salt-keys-name';
        $this->change_now_btn_name     = 'wp-mastertoolkit-auto-regenerate-salt-keys-change-now';

        add_action( 'init', array( $this, 'class_init' ) );
        add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
        add_action( 'admin_init', array( $this, 'save_submenu' ) );
		add_filter( 'cron_schedules', array( $this, 'crons_registrations' ) );
		add_action( 'wp', array( $this, 'cron_events' ) );
		add_action( $this->cron_name . '_hook', array( $this, 'cron_scripts' ) );
    }

    /**
     * Initialize the class
     */
    public function class_init() {
        $this->header_title = esc_html__( 'Auto Regenerate Salt Keys', 'wpmastertoolkit' );
    }

    /**
     * Register the cron events
     */
    public function crons_registrations( $schedules ) {

        $settings         = $this->get_settings();
        $default_settings = $this->get_default_settings();
        $value            = $settings['frequently']['value'] ?? $default_settings['frequently']['value'];

        switch ( $value ) {
            case 'daily':
                $interval = DAY_IN_SECONDS;
            break;
            case 'weekly':
                $interval = WEEK_IN_SECONDS;
            break;
            case 'monthly':
                $interval = MONTH_IN_SECONDS;
            break;
            case 'quarterly':
                $interval = 3 * MONTH_IN_SECONDS;
            break;
            case 'biannually':
                $interval = 6 * MONTH_IN_SECONDS;
            break;
            default:
                $interval = MONTH_IN_SECONDS;
            break;
        }

        $schedules[ $this->cron_name ] = array(
            'interval' => $interval,
            'display'  => $this->header_title,
        );

		return $schedules;
    }

    /**
	* Start the next cron event
	*/
    public function cron_events() {

        if ( ! wp_next_scheduled( $this->cron_name . '_hook', array( $this->cron_name ) ) ) {
            wp_schedule_event( time(), $this->cron_name, $this->cron_name . '_hook', array( $this->cron_name ) );
        }
    }

    /**
     * Run the cron scripts
     */
    public function cron_scripts() {
        $this->regenerate_salt_keys();
        exit;
    }

    /**
     * Run on option deactivation.
     */
    public static function deactivate(){

        $instance = new self();
        $cron_name = $instance->cron_name;
        
        wp_clear_scheduled_hook( $cron_name . '_hook' );
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
     */
    public function add_submenu(){

        add_submenu_page(
            'wp-mastertoolkit-settings',
            $this->header_title,
            $this->header_title,
            'manage_options',
            'wp-mastertoolkit-settings-auto-regenerate-salt-keys',
            array( $this, 'render_submenu'),
            null
        );
    }

    /**
     * Render the submenu
     * 
     */
    public function render_submenu() {

        $submenu_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/auto-regenerate-salt-keys.asset.php' );
        wp_enqueue_style( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/auto-regenerate-salt-keys.css', array(), $submenu_assets['version'], 'all' );
        wp_enqueue_script( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/auto-regenerate-salt-keys.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );

        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/submenu/header.php';
        $this->submenu_content();
        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/submenu/footer.php';
    }

    /**
     * Save the submenu option
     *
     */
    public function save_submenu() {

        $submit        = isset( $_POST[$this->change_now_btn_name] ) ?? false;
        $now_btn_nonce = sanitize_text_field( $_POST[$this->change_now_nonce_name] ?? '' );
		$nonce         = sanitize_text_field( $_POST['_wpnonce'] ?? '' );

		if ( $submit && wp_verify_nonce( $now_btn_nonce, $this->change_now_nonce_action ) ) {

            $this->regenerate_salt_keys();

            wp_safe_redirect( sanitize_url( $_SERVER['REQUEST_URI'] ?? '' ) );
            exit;

        } elseif ( wp_verify_nonce( $nonce, $this->nonce_action ) ) {

            $new_settings = $this->sanitize_settings( $_POST[$this->option_id] ?? array() );
            $this->save_settings( $new_settings );

            wp_safe_redirect( sanitize_url( $_SERVER['REQUEST_URI'] ?? '' ) );
			exit;
        }
    }

    /**
     * Add the submenu content
     *
     */
    private function submenu_content() {

        $this->settings   = $this->get_settings();
        $default_settings = $this->get_default_settings();
        $frequently       = $this->settings['frequently']['value'] ?? '';
        $options          = $default_settings['frequently']['options'] ?? array();

        ?>
            <div class="wp-mastertoolkit__section">
                <div class="wp-mastertoolkit__section__desc"><?php esc_html_e( 'WordPress salt keys or security keys are codes that help protect important information on your website.', 'wpmastertoolkit' ); ?></div>
                <div class="wp-mastertoolkit__section__body">
                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Scheduled Change', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
                            <p><?php esc_html_e( 'Choose when WordPress salt keys should be changed automatically', 'wpmastertoolkit' ); ?></p>
                            <div class="wp-mastertoolkit__select">
                                <select name="<?php echo esc_attr( $this->option_id . '[frequently][value]' ); ?>">
                                    <?php foreach ( $options as $key => $name ) : ?>
                                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $frequently, $key ); ?>><?php echo esc_html( $name ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Immediate Change', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
                            <p><?php esc_html_e( 'When you click the Change Now button, WordPress salt keys will change immediately. And all users will need to login again.', 'wpmastertoolkit' ); ?></p>
                            <?php
                                wp_nonce_field( $this->change_now_nonce_action, $this->change_now_nonce_name );
                                submit_button( esc_html__('Change Now', 'wpmastertoolkit'), 'primary', $this->change_now_btn_name, false );
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php
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
                case 'frequently':
                    $sanitized_settings[$settings_key]['value'] = sanitize_text_field( $new_settings[$settings_key]['value'] ?? $this->default_settings[$settings_key]['value'] );
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
            'frequently' => array(
                'value'   => 'monthly',
                'options' => array(
                    'daily'      => __( 'Daily', 'wpmastertoolkit' ),
                    'weekly'     => __( 'Weekly', 'wpmastertoolkit' ),
                    'monthly'    => __( 'Monthly', 'wpmastertoolkit' ),
                    'quarterly'  => __( 'Quarterly', 'wpmastertoolkit' ),
                    'biannually' => __( 'Biannually', 'wpmastertoolkit' ),
                ),
            ),
        );
    }

    /*
    * Regenerate the salt keys
    */
    private function regenerate_salt_keys() {

        require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/class-wp-config.php';

        $salts_const_names = array(
            'AUTH_KEY',
            'SECURE_AUTH_KEY',
            'LOGGED_IN_KEY',
            'NONCE_KEY',
            'AUTH_SALT',
            'SECURE_AUTH_SALT',
            'LOGGED_IN_SALT',
            'NONCE_SALT',
        );

        foreach ( $salts_const_names as $salt_const_name ) {
            $new_salt_value = trim(str_replace(
                array('<?', '?>', "'", '"', '\\', ';', '(', ')'), 
                array('-?', '_!', '?', '#', '=', "+" , '*', '-'),
                wp_generate_password( 64, true, true )
            ));
            if( WPMastertoolkit_WP_Config::is_constant_defined_in_wp_config( $salt_const_name ) && defined( $salt_const_name ) ){
                $wp_config_content = str_replace( 
                    array(
                        "'". constant( $salt_const_name ) ."'",
                        '"'. constant( $salt_const_name ) .'"',
                    ),
                    array(
                        "'". $new_salt_value ."'",
                    ),
                    WPMastertoolkit_WP_Config::get_wp_config_content()
                );
                file_put_contents( WPMastertoolkit_WP_Config::get_wp_config_path(), $wp_config_content );
            }
        }
    }
}
