<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Insert <head>, <body> and <footer> Code
 * Description: Easily insert <meta>, <link>, <script> and <style> tags, Google Analytics, Tag Manager, AdSense, Ads Conversion and Optimize code, Facebook, TikTok and Twitter pixels, etc.
 * @since 1.5.0
 */
class WPMastertoolkit_Insert_Head_Body_Footer_Code {

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
        $this->option_id    = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_insert_head_body_footer_code';
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
        $this->header_title = esc_html__( 'Insert <head>, <body> and <footer> Code', 'wpmastertoolkit' );
		$this->settings     = $this->get_settings();
		$head_priority      = $this->settings['head']['priority'] ?? '10';
		$body_priority      = $this->settings['body']['priority'] ?? '10';
		$footer_priority    = $this->settings['footer']['priority'] ?? '10';

		add_action( 'wp_head', array( $this, 'insert_head_code' ), $head_priority );
		add_action( 'wp_body_open', array( $this, 'insert_body_code' ), $body_priority );
		add_action( 'wp_footer', array( $this, 'insert_footer_code' ), $footer_priority );
    }

	/**
	 * Insert the head code
	 * 
	 * @since   1.5.0
	 */
	public function insert_head_code() {

		if ( is_admin() || is_feed() || is_robots() || is_trackback() ) {
            return;
        }

		$this->settings = $this->get_settings();
		$code_snippet   = $this->settings['head']['code_snippet'] ?? '';

		if ( empty( $code_snippet ) ) {
			return;
		}

		//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo wp_unslash( $code_snippet ) . PHP_EOL;
	}

	/**
	 * Insert the body code
	 * 
	 * @since   1.5.0
	 */
	public function insert_body_code() {

		if ( is_admin() || is_feed() || is_robots() || is_trackback() ) {
            return;
        }

		$this->settings = $this->get_settings();
		$code_snippet   = $this->settings['body']['code_snippet'] ?? '';

		if ( empty( $code_snippet ) ) {
			return;
		}

		//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo wp_unslash( $code_snippet ) . PHP_EOL;
	}

	/**
	 * Insert the footer code
	 * 
	 * @since   1.5.0
	 */
	public function insert_footer_code() {

		if ( is_admin() || is_feed() || is_robots() || is_trackback() ) {
            return;
        }

		$this->settings = $this->get_settings();
		$code_snippet   = $this->settings['footer']['code_snippet'] ?? '';

		if ( empty( $code_snippet ) ) {
			return;
		}

		//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo wp_unslash( $code_snippet ) . PHP_EOL;
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
            'wp-mastertoolkit-settings-insert-head-body-footer-code', 
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
                    'name'      => 'html',
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

        $submenu_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/insert-head-body-footer-code.asset.php' );
        wp_enqueue_style( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/insert-head-body-footer-code.css', array(), $submenu_assets['version'], 'all' );
        wp_enqueue_script( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/insert-head-body-footer-code.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );
        wp_localize_script( 'WPMastertoolkit_submenu', 'wpmastertoolkit_code_snippets', array(
            'code_editor' => $code_editor,
        ) );

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
			foreach ( $settings_value as $settings_value_key => $settings_value_value ) {
				switch ($settings_value_key) {
				    case 'priority':
						$sanitized_settings[ $settings_key ][ $settings_value_key ] = $new_settings[ $settings_key ][ $settings_value_key ] ?? $settings_value_value;
				    break;
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
			'head'   => array(
				'priority'     => '10',
				'code_snippet' => '',
			),
			'body'   => array(
				'priority'     => '10',
				'code_snippet' => '',
			),
			'footer' => array(
				'priority'     => '10',
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
        $head           = $this->settings['head'] ?? array();
		$body           = $this->settings['body'] ?? array();
		$footer         = $this->settings['footer'] ?? array();
        ?>
            <div class="wp-mastertoolkit__section">
                <div class="wp-mastertoolkit__section__desc"><?php esc_html_e( "Easily insert <meta>, <link>, <script> and <style> tags, Google Analytics, Tag Manager, AdSense, Ads Conversion and Optimize code, Facebook, TikTok and Twitter pixels, etc.", 'wpmastertoolkit'); ?></div>
                <div class="wp-mastertoolkit__section__body">
					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e('Code to insert before </head>', 'wpmastertoolkit'); ?></div>
						<div class="wp-mastertoolkit__section__body__item__content flex flex-wrap">
							<div class="wp-mastertoolkit__input-text flex">
								<div><?php esc_html_e( 'Priority', 'wpmastertoolkit'); ?></div>
								<div>
									<input type="number" name="<?php echo esc_attr( $this->option_id . '[head][priority]' ); ?>" id="head_priority" value="<?php echo esc_attr( $head['priority'] ?? '10' ); ?>" min="1" style="width: 60px;">
								</div>
								<div>
									<?php esc_html_e( 'Larger number insert code closer to </head>', 'wpmastertoolkit' ); ?>
								</div>
							</div>
							<textarea name="<?php echo esc_attr( $this->option_id . '[head][code_snippet]' ); ?>" id="head_code_snippet" class="widefat" rows="10"><?php echo esc_textarea( wp_unslash( $head['code_snippet'] ?? '' ) ); ?></textarea>
						</div>
                    </div>
					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e('Code to insert after <body>', 'wpmastertoolkit'); ?></div>
						<div class="wp-mastertoolkit__section__body__item__content flex flex-wrap">
							<div class="wp-mastertoolkit__input-text flex">
								<div><?php esc_html_e( 'Priority', 'wpmastertoolkit'); ?></div>
								<div>
									<input type="number" name="<?php echo esc_attr( $this->option_id . '[body][priority]' ); ?>" id="body_priority" value="<?php echo esc_attr( $body['priority'] ?? '10' ); ?>" min="1" style="width: 60px;">
								</div>
								<div>
									<?php esc_html_e( 'Smaller number insert code closer to <body>', 'wpmastertoolkit' ); ?>
								</div>
							</div>
							<textarea name="<?php echo esc_attr( $this->option_id . '[body][code_snippet]' ); ?>" id="body_code_snippet" class="widefat" rows="10"><?php echo esc_textarea( wp_unslash( $body['code_snippet'] ?? '' ) ); ?></textarea>
						</div>
                    </div>
					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e('Code to insert before </body>', 'wpmastertoolkit'); ?></div>
						<div class="wp-mastertoolkit__section__body__item__content flex flex-wrap">
							<div class="wp-mastertoolkit__input-text flex">
								<div><?php esc_html_e( 'Priority', 'wpmastertoolkit'); ?></div>
								<div>
									<input type="number" name="<?php echo esc_attr( $this->option_id . '[footer][priority]' ); ?>" id="footer_priority" value="<?php echo esc_attr( $footer['priority'] ?? '10' ); ?>" min="1" style="width: 60px;">
								</div>
								<div>
									<?php esc_html_e( 'Larger number insert code closer to </body>', 'wpmastertoolkit' ); ?>
								</div>
							</div>
							<textarea name="<?php echo esc_attr( $this->option_id . '[footer][code_snippet]' ); ?>" id="footer_code_snippet" class="widefat" rows="10"><?php echo esc_textarea( wp_unslash( $footer['code_snippet'] ?? '' ) ); ?></textarea>
						</div>
                    </div>
                </div>
            </div>
        <?php
    }
}
