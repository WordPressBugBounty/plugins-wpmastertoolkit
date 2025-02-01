<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Manage ads.txt and app-ads.txt
 * Description: Easily edit and validate your ads.txt and app-ads.txt content.
 * @since 1.5.0
 */
class WPMastertoolkit_Manage_Ads_Txt {

    private $option_id;
    private $header_title;
    private $nonce_action;
    private $settings;
    private $default_settings;

	/**
     * Invoke the hooks
     * 
     * @since    1.5.0
     */
    public function __construct() {
        $this->option_id    = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_manage_ads_txt';
        $this->nonce_action = $this->option_id . '_action';

        add_action( 'init', array( $this, 'class_init' ) );
        add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
        add_action( 'admin_init', array( $this, 'save_submenu' ) );
    }

	/**
     * Initialize the class
     * 
     * @since    1.5.0
     * @return   void
     */
    public function class_init() {
        $this->header_title = esc_html__( 'Manage ads.txt and app-ads.txt', 'wpmastertoolkit' );

		$this->show_text();
    }

	/**
	 * Show the text
	 * 
	 * @since   1.5.0
	 */
	public function show_text() {

        $request = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : false;

		if ( '/ads.txt' === $request ) {
			$this->settings = $this->get_settings();
			$content        = $this->settings['ads']['code_snippet'] ?? '';

            header( 'Content-Type: text/plain' );
            echo esc_textarea( stripslashes( $content ) );
            die();
        }

		if ( '/app-ads.txt' === $request ) {
			$this->settings = $this->get_settings();
			$content        = $this->settings['app_ads']['code_snippet'] ?? '';

            header( 'Content-Type: text/plain' );
            echo esc_textarea( stripslashes( $content ) );
            die();
        }
	}

	/**
     * Add a submenu
     * 
     * @since   1.5.0
     */
    public function add_submenu(){
        add_submenu_page(
            'wp-mastertoolkit-settings',
            $this->header_title,
            $this->header_title,
            'manage_options',
            'wp-mastertoolkit-settings-manage-ads-txt', 
            array( $this, 'render_submenu' ),
            null
        );
    }

	/**
     * Render the submenu
     * 
     * @since   1.5.0
     */
    public function render_submenu() {
        $code_editor = wp_enqueue_code_editor( array( 
            'type' => 'css',
            'codemirror' => array(
                'mode' => array(
                    'name'      => 'markdown',
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

        $submenu_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/manage-ads-txt.asset.php' );
        wp_enqueue_style( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/manage-ads-txt.css', array(), $submenu_assets['version'], 'all' );
        wp_enqueue_script( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/manage-ads-txt.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );
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
			foreach ( $settings_value as $settings_value_key => $settings_value_value ) {
				switch ($settings_value_key) {
				    case 'code_snippet':
						$sanitized_settings[ $settings_key ][ $settings_value_key ] = !empty( $new_settings[ $settings_key ][ $settings_value_key ] ) ? wp_unslash( $new_settings[ $settings_key ][ $settings_value_key ] ) : '';
				    break;
				}
			}
        }

        return $sanitized_settings;
    }

	/**
     * get_settings
     *
     * @since   1.5.0
     * @return void
     */
    private function get_settings(){
        $this->default_settings = $this->get_default_settings();
        return get_option( $this->option_id, $this->default_settings );
    }

	/**
     * Save settings
     * 
     * @since   1.5.0
     */
    private function save_settings( $new_settings ) {
		update_option( $this->option_id, $new_settings );
    }

	/**
     * get_default_settings
     *
     * @since   1.5.0
     * @return array
     */
    private function get_default_settings(){
        if( $this->default_settings !== null ) return $this->default_settings;

        return array(
			'ads'     => array(
				'code_snippet' => '',
			),
			'app_ads' => array(
				'code_snippet' => '',
			),
        );
    }

	/**
     * Add the submenu content
     * 
     * @since   1.5.0
     * @return void
     */
    private function submenu_content() {
        $this->settings = $this->get_settings();
        $ads     = $this->settings['ads'] ?? array();
		$app_ads = $this->settings['app_ads'] ?? array();
        ?>
            <div class="wp-mastertoolkit__section">
                <div class="wp-mastertoolkit__section__desc"><?php printf( __( "Easily edit and validate your %s and %s content.", 'wpmastertoolkit' ), '<a target="_blank" href="' . site_url( 'ads.txt' ). '">ads.txt</a>', '<a target="_blank" href="' . site_url( 'app-ads.txt' ). '">app-ads.txt</a>' ); ?></div>
                <div class="wp-mastertoolkit__section__body">
					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e('ads.txt', 'wpmastertoolkit'); ?></div>
						<div class="wp-mastertoolkit__section__body__item__content flex flex-wrap">
                            <div>
                                <?php esc_html_e( 'Validate with', 'wpmastertoolkit'); ?>
                                : <a target="_blank" href="https://adstxt.guru/validator/url/?url=<?php echo urlencode( site_url( 'ads.txt' ) ); ?>">adstxt.guru</a>
                            </div>
							<textarea name="<?php echo esc_attr( $this->option_id . '[ads][code_snippet]' ); ?>" id="ads_code_snippet" class="widefat" rows="10"><?php echo esc_textarea( stripslashes( $ads['code_snippet'] ?? '' ) ); ?></textarea>
						</div>
                    </div>
					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e('app-ads.txt', 'wpmastertoolkit'); ?></div>
						<div class="wp-mastertoolkit__section__body__item__content flex flex-wrap">
                            <div>
                                <?php esc_html_e( 'Validate with', 'wpmastertoolkit'); ?>
                                : <a target="_blank" href="https://adstxt.guru/validator/url/?url=<?php echo urlencode( site_url( 'app-ads.txt' ) ); ?>">adstxt.guru</a>
                            </div>
							<textarea name="<?php echo esc_attr( $this->option_id . '[app_ads][code_snippet]' ); ?>" id="app_ads_code_snippet" class="widefat" rows="10"><?php echo esc_textarea( stripslashes( $app_ads['code_snippet'] ?? '' ) ); ?></textarea>
						</div>
                    </div>
                </div>
            </div>
        <?php
    }
}
