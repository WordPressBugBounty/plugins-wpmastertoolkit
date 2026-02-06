<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Browser Theme Color
 * Description: Select a tag color to allow seamless theme customization in all major browsers.
 * @since 2.5.0
 */
class WPMastertoolkit_Browser_Theme_Color {

	private $option_id;
    private $header_title;
    private $nonce_action;
    private $settings;
	private $default_settings;

	/**
     * Invoke the hooks
	 * 
	 * @since 2.5.0
     */
    public function __construct() {
        $this->option_id    = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_child_theme_generator';
        $this->nonce_action = $this->option_id . '_action';

        add_action( 'init', array( $this, 'class_init' ) );
        add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
        add_action( 'admin_init', array( $this, 'save_submenu' ) );
		add_action( 'wp_head', array( $this, 'add_theme_color_meta_tag' ), 99 );
    }

	/**
     * Initialize the class
     * 
     * @since 2.5.0
     */
    public function class_init() {
        $this->header_title = esc_html__( 'Browser Theme Color', 'wpmastertoolkit' );
    }

	/**
	 * Add theme color meta tag
	 * 
	 * @since 2.5.0
	 */
	public function add_theme_color_meta_tag() {
		$this->settings = $this->get_settings();
		$theme_color    = $this->settings['theme_color'] ?? '';

		if ( empty( $theme_color ) ) {
			return;
		}

		echo '<meta name="theme-color" content="' . esc_attr( $theme_color ) . '" />';
		echo '<meta name="apple-mobile-web-app-status-bar-style" content="' . esc_attr( $theme_color ) . '">';
	}

	/**
     * Add a submenu
	 * 
	 * @since 2.5.0
     */
    public function add_submenu(){
        WPMastertoolkit_Settings::add_submenu_page(
            'wp-mastertoolkit-settings',
            $this->header_title,
            $this->header_title,
            'manage_options',
            'wp-mastertoolkit-settings-browser-theme-color',
            array( $this, 'render_submenu' ),
            null
        );
    }

	/**
     * Render the submenu
	 * 
	 * @since 2.5.0
     */
    public function render_submenu() {

		wp_enqueue_script('wp-color-picker');
        wp_enqueue_style('wp-color-picker');
        
        $submenu_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/browser-theme-color.asset.php' );
        wp_enqueue_style( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/browser-theme-color.css', array(), $submenu_assets['version'], 'all' );
        wp_enqueue_script( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/browser-theme-color.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );

        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
        $this->submenu_content();
        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/footer.php';
    }

	/**
     * Save the submenu option
	 * 
	 * @since 2.5.0
     */
    public function save_submenu() {

		$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
		if ( wp_verify_nonce( $nonce, $this->nonce_action ) ) {

			//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $new_settings = $this->sanitize_settings( wp_unslash( $_POST[$this->option_id] ?? array() ) );
            
            $this->save_settings( $new_settings );
            wp_safe_redirect( sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) );
			exit;
		}
	}

	/**
     * sanitize_settings
     * 
     * @since 2.5.0
     */
    private function sanitize_settings( $new_settings ){
		$this->default_settings = $this->get_default_settings();
        $sanitized_settings = array();

		foreach ( $this->default_settings as $settings_key => $settings_value ) {   
            switch ( $settings_key ) {
				case 'theme_color':
                    $sanitized_settings[$settings_key] = sanitize_hex_color( $new_settings[$settings_key] ?? $settings_value );
                break;
			}
		}

		return $sanitized_settings;
	}

	/**
     * Save settings
	 * 
	 * @since 2.5.0
     */
    private function save_settings( $new_settings ) {
		update_option( $this->option_id, $new_settings );
    }

	/**
     * Add the submenu content
     * 
     * @since 2.5.0
     */
    private function submenu_content() {
		$this->settings = $this->get_settings();
		$theme_color    = $this->settings['theme_color'] ?? '';

		?>
            <div class="wp-mastertoolkit__section">
                <div class="wp-mastertoolkit__section__desc">
					<?php esc_html_e( "Select a tag color to allow seamless theme customization in all major browsers.", 'wpmastertoolkit' ); ?>
				</div>
                <div class="wp-mastertoolkit__section__body">

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Theme Color', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
                            <div class="wp-mastertoolkit__input-text">
                                <input id="wpmastertoolkit-browser-theme-color" type="text" name="<?php echo esc_attr( $this->option_id . '[theme_color]' ); ?>" value="<?php echo esc_attr( $theme_color ); ?>" class="wp-color-picker"/>
                            </div>
							<?php //phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage ?>
                            <img class="wp-mastertoolkit__preview-browser-theme-color black" src="<?php echo esc_url( WPMASTERTOOLKIT_PLUGIN_URL . 'admin/images/browser-theme-color-black.webp' ); ?>" alt="<?php esc_attr_e( 'Browser Theme Color Preview', 'wpmastertoolkit' ); ?>" class="wp-mastertoolkit__section__body__preview" />
							<?php //phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage ?>
                            <img class="wp-mastertoolkit__preview-browser-theme-color white" src="<?php echo esc_url( WPMASTERTOOLKIT_PLUGIN_URL . 'admin/images/browser-theme-color-white.webp' ); ?>" alt="<?php esc_attr_e( 'Browser Theme Color Preview', 'wpmastertoolkit' ); ?>" class="wp-mastertoolkit__section__body__preview" />
                        </div>
                    </div>
                </div>
            </div>
            
		<?php
	}

	/**
     * get_settings
	 * 
	 * @since 2.5.0
     */
    private function get_settings(){
		if ( $this->settings !== null ) {
			return $this->settings;
		}

        $this->default_settings = $this->get_default_settings();
        $settings               = get_option( $this->option_id, $this->default_settings );
        $settings               = wp_parse_args( $settings, $this->default_settings );

        return $settings;
    }

	/**
     * get_default_settings
     *
     * @since 2.5.0
     */
    private function get_default_settings(){
        if ( $this->default_settings !== null ) {
			return $this->default_settings;
		}

		return array(
			'theme_color' => '#000000',
		);
	}
}
