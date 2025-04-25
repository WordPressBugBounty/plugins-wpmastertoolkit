<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Ban emails
 * Description: Ban the chosen emails.
 * @since 1.7.0
 */
class WPMastertoolkit_Ban_Emails {

    private $option_id;
    private $header_title;
    private $nonce_action;
    private $settings;
    private $default_settings;

	/**
     * Invoke the hooks
     * 
     * @since    1.7.0
     */
    public function __construct() {
        $this->option_id    = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_ban_emails';
        $this->nonce_action = $this->option_id . '_action';

        add_action( 'init', array( $this, 'class_init' ) );
        add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
        add_action( 'admin_init', array( $this, 'save_submenu' ) );
		add_action( 'register_post', array( $this, 'verify_emails' ), 1, 3 );
    }

	/**
     * Initialize the class
     * 
     * @since    1.7.0
     * @return   void
     */
    public function class_init() {
        $this->header_title = esc_html__( 'Ban emails', 'wpmastertoolkit' );
    }

	/**
	 * Verify emails
	 */
	public function verify_emails( $sanitized_user_login, $user_email, $errors ) {
		$settings           = $this->get_settings();
		$redirect           = $settings['redirect'] ?? '';
		$redirect_url       = $settings['redirect_url'] ?? '';
		$blocked_list       = stripslashes( $settings['blocked_list'] ?? '' );
		$blocked_list_array = explode( "\n", $blocked_list );
		$email_not_accepted = false;

		foreach ( $blocked_list_array as $blocked_item ) {
			$blocked_item = trim( $blocked_item );
			if ( stripos( $user_email, $blocked_item ) !== false ) {
				$email_not_accepted = true;
				break;
			}
		}

		if ( $email_not_accepted ) {

			if ( '1' === $redirect && filter_var( $redirect_url, FILTER_VALIDATE_URL ) ) {
				wp_redirect( $redirect_url );
				exit;
			}

			$message = sprintf(
                /* translators: 1: <strong>, 2: </strong> */
                __( '%1$sError:%2$s Your email is not accepted.', 'wpmastertoolkit' ), 
                '<strong>', 
                '</strong>'
            );
			$errors->add( 'invalid_email', $message );
		}
	}

	/**
     * Add a submenu
     * 
     * @since   1.7.0
     */
    public function add_submenu(){
        add_submenu_page(
            'wp-mastertoolkit-settings',
            $this->header_title,
            $this->header_title,
            'manage_options',
            'wp-mastertoolkit-settings-ban-emails',
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
        $submenu_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/ban-emails.asset.php' );
        wp_enqueue_style( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/ban-emails.css', array(), $submenu_assets['version'], 'all' );
        wp_enqueue_script( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/ban-emails.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );

        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
        $this->submenu_content();
        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/footer.php';
    }

	/**
     * Save the submenu option
     * 
     * @since   1.7.0
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
     * @since   1.7.0
     * @return array
     */
    public function sanitize_settings( $new_settings ){
        $this->default_settings = $this->get_default_settings();
        $sanitized_settings     = array();

        foreach ( $this->default_settings as $settings_key => $settings_value ) {
			if ( 'redirect_url' === $settings_key ) {
				$url = sanitize_text_field( $new_settings[ $settings_key ] ?? $settings_value );
				if ( filter_var( $url, FILTER_VALIDATE_URL ) ) {
					$sanitized_settings[ $settings_key ] = sanitize_url( $url );
				}
			} elseif ( 'blocked_list' === $settings_key ) {
				$sanitized_settings[ $settings_key ] = sanitize_textarea_field( stripslashes( $new_settings[ $settings_key ] ?? $settings_value ) );
			} else {
				$sanitized_settings[ $settings_key ] = sanitize_text_field( $new_settings[ $settings_key ] ?? $settings_value );
			}
        }
		
        return $sanitized_settings;
    }

	/**
     * get_settings
     *
     * @since   1.7.0
     * @return void
     */
    private function get_settings(){
        $this->default_settings = $this->get_default_settings();
        return get_option( $this->option_id, $this->default_settings );
    }

	/**
     * Save settings
     * 
     * @since   1.7.0
     */
    private function save_settings( $new_settings ) {
		update_option( $this->option_id, $new_settings );
    }

	/**
     * get_default_settings
     *
     * @since   1.7.0
     * @return array
     */
    private function get_default_settings(){
        if( $this->default_settings !== null ) return $this->default_settings;

        return array(
			'redirect'     => '',
			'redirect_url' => '',
			'blocked_list' => '',
        );
    }

	/**
     * Add the submenu content
     * 
     * @since   1.7.0
     * @return void
     */
    private function submenu_content() {
        $this->settings = $this->get_settings();
        ?>
            <div class="wp-mastertoolkit__section">
                <div class="wp-mastertoolkit__section__desc">
					<?php esc_html_e( "Ban the chosen emails.", 'wpmastertoolkit'); ?>
				</div>
                <div class="wp-mastertoolkit__section__body">

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Redirect Blocked Users', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">

							<div class="wp-mastertoolkit__checkbox">
								<label class="wp-mastertoolkit__checkbox__label">
									<input type="hidden" name="<?php echo esc_attr( $this->option_id . '[redirect]' ); ?>" value="0">
									<input type="checkbox" name="<?php echo esc_attr( $this->option_id . '[redirect]' ); ?>" value="1"<?php checked( $this->settings['redirect']??'', '1' ); ?>>
									<span class="mark"></span>
									<span class="wp-mastertoolkit__checkbox__label__text"><?php esc_html_e( 'Redirect failed logins to a custom URL.', 'wpmastertoolkit' ); ?></span>
								</label>
							</div>

							<div class="wp-mastertoolkit__input-text">
								<input type="text" name="<?php echo esc_attr( $this->option_id . '[redirect_url]' ); ?>" value="<?php echo esc_attr( $this->settings['redirect_url'] ?? '' ); ?>">
							</div>

							<div class="description"><?php esc_html_e( 'Set redirect URL (example: http://example.com).', 'wpmastertoolkit' ); ?></div>
                        </div>
                    </div>

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'The Blocked List', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
							<div class="wp-mastertoolkit__textarea">
                                <textarea name="<?php echo esc_attr( $this->option_id ); ?>[blocked_list]" cols="50" rows="3" style="width: 400px;"><?php echo esc_textarea( stripslashes( $this->settings['blocked_list'] ?? '') ); ?></textarea>
                            </div>
							<div class="description"><?php esc_html_e( 'Add each item on one line.', 'wpmastertoolkit' ); ?></div>
							<div class="description"><?php esc_html_e( 'The terms below will not be allowed to be used during registration. You can add in full emails (i.e. foo@domain.com) or domains (i.e. @domain.com), and partials (i.e. foo). Wildcards (i.e. *) will not work.', 'wpmastertoolkit' ); ?></div>
                        </div>
                    </div>

                </div>
            </div>
        <?php
    }
}
