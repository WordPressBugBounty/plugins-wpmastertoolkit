<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: SMTP mailer
 * Description: Set custom sender name and email. Optionally use external SMTP service to ensure notification and transactional emails from your site are being delivered to inboxes.
 * @since 1.7.0
 * @updated 1.8.0
 */
class WPMastertoolkit_SMTP_Mailer {

    private $option_id;
    private $header_title;
    private $nonce_action;
    private $settings;
    private $default_settings;
	private $ajax_action;
    private $ajax_nonce;

	/**
     * Invoke the hooks
     * 
     * @since    1.7.0
     */
    public function __construct() {
        $this->option_id    = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_smtp_mailer';
        $this->nonce_action = $this->option_id . '_action';
		$this->ajax_action  = $this->option_id . '_ajax_action';
		$this->ajax_nonce   = $this->option_id . '_ajax_nonce';

        add_action( 'init', array( $this, 'class_init' ) );
        add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
        add_action( 'admin_init', array( $this, 'save_submenu' ) );
		add_action( 'phpmailer_init', array( $this, 'deliver_email_via_smtp'), 99999 );
		add_action( 'wp_ajax_' . $this->ajax_action, array( $this, 'send_test_email') );
    }

	/**
     * Initialize the class
     * 
     * @since    1.7.0
     * @return   void
     */
    public function class_init() {
        $this->header_title = esc_html__( 'SMTP mailer', 'wpmastertoolkit' );
    }

	/**
	 * Send email via SMTP
	 * 
	 * @since   1.7.0
	 */
	public function deliver_email_via_smtp( $phpmailer ) {
		$settings = $this->get_settings();

		$sender_name      = $settings['sender_name'];
		$sender_email     = $settings['sender_email'];
		$force_sender     = $settings['force_sender'];
		$host             = $settings['host'];
		$port             = $settings['port'];
		$encryption       = $settings['encryption']['value'];
		$username         = $settings['username'];
		$password         = $settings['password'];
		$ssl_verification = $settings['bypass_ssl_verification'];
		$active_debug     = $settings['active_debug'];
		$from_name        = $phpmailer->FromName;
        $from_email       = substr( $phpmailer->From, 0, 9 );

		if ( $force_sender ) {
            $phpmailer->FromName = $sender_name;
            $phpmailer->From     = $sender_email;
        } else {
            if ( 'WordPress' === $from_name && ! empty( $sender_name ) ) {
                $phpmailer->FromName = $sender_name;
            }
            if ( 'wordpress' === $from_email && !empty( $sender_email ) ) {
                $phpmailer->From = $sender_email;
            }
        }

        if ( ! empty( $host ) && ! empty( $port ) && ! empty( $encryption ) && ! empty( $username ) && !empty( $password ) ) {
			$phpmailer->isSMTP();
            $phpmailer->SMTPAuth   = true;
            $phpmailer->XMailer    = 'WPMastertoolkit v' . WPMASTERTOOLKIT_VERSION . ' - a WordPress plugin';
            $phpmailer->Host       = $host;
            $phpmailer->Port       = $port;
            $phpmailer->SMTPSecure = $encryption;
            $phpmailer->Username   = trim( $username );
            $phpmailer->Password   = trim( $password );
		}

		if ( $ssl_verification ) {
            $phpmailer->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
				),
			);
        }

		if ( $active_debug ) {
            $phpmailer->SMTPDebug   = 4;
            $phpmailer->Debugoutput = 'error_log';
        }
	}

	/**
	 * Send a test email
	 * 
	 * @since   1.7.0
	 */
	public function send_test_email() {

		$nonce = sanitize_text_field( $_POST['nonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, $this->ajax_nonce ) ) {
            wp_send_json_error( array( 'message' => __( 'Refresh the page and try again.', 'wpmastertoolkit' ) ) );
		}

		$email = sanitize_email( $_POST['testEmail'] ?? '' );
		if ( empty( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'wpmastertoolkit' ) ) );
		}

		$success = wp_mail(
			$email,
			'Testing SMTP mailer',
			'<p><strong>Looks good!</strong></p>',
			array('Content-Type: text/html; charset=UTF-8'),
		);

		if ( $success ) {
			wp_send_json_success( array( 'message' => __( 'Test email was successfully processed.', 'wpmastertoolkit' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to send test email.', 'wpmastertoolkit' ) ) );
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
            'wp-mastertoolkit-settings-smtp-mailer',
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
        $submenu_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/smtp-mailer.asset.php' );
        wp_enqueue_style( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/smtp-mailer.css', array(), $submenu_assets['version'], 'all' );
        wp_enqueue_script( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/smtp-mailer.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );
		wp_localize_script( 'WPMastertoolkit_submenu', 'wpmastertoolkit_smtp_mailer', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'action'  => $this->ajax_action,
			'nonce'   => wp_create_nonce( $this->ajax_nonce ),
		));

        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/submenu/header.php';
        $this->submenu_content();
        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/submenu/footer.php';
    }

	/**
     * Save the submenu option
     * 
     * @since   1.7.0
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
     * @since   1.7.0
     * @return array
     */
    public function sanitize_settings( $new_settings ){
        $this->default_settings = $this->get_default_settings();
        $sanitized_settings     = array();

        foreach ( $this->default_settings as $settings_key => $settings_value ) {
			if ( 'encryption' === $settings_key ) {
				$new_value = sanitize_text_field( $new_settings[ $settings_key ]['value'] ?? $settings_value['value'] );
				if ( array_key_exists( $new_value, $settings_value['options'] ) ) {
					$sanitized_value = $new_value;
				} else {
					$sanitized_value = $settings_value['value'];
				}
				$sanitized_settings[ $settings_key ]['value'] = $sanitized_value;
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
			'sender_name'             => '',
			'sender_email'            => '',
			'force_sender'            => '0',
			'host'                    => '',
			'port'                    => '',
			'encryption'              => array(
				'value'   => 'none',
				'options' => array(
					'none' => __( 'None', 'wpmastertoolkit' ),
					'ssl'  => __( 'SSL', 'wpmastertoolkit' ),
					'tls'  => __( 'TLS', 'wpmastertoolkit' ),
				),
			),
			'username'                => '',
			'password'                => '',
			'bypass_ssl_verification' => '0',
			'active_debug'            => '0',
        );
    }

	/**
     * Add the submenu content
     * 
     * @since   1.7.0
     * @return void
     */
    private function submenu_content() {
        $this->settings   = $this->get_settings();
		$default_settings = $this->get_default_settings();

		$encryption_options = $default_settings['encryption']['options'];
		$encryption_value   = $this->settings['encryption']['value'] ?? $default_settings['encryption']['value'];
        ?>
            <div class="wp-mastertoolkit__section">
                <div class="wp-mastertoolkit__section__desc">
					<?php esc_html_e( "Set custom sender name and email. Optionally use external SMTP service to ensure notification and transactional emails from your site are being delivered to inboxes.", 'wpmastertoolkit'); ?>
				</div>
                <div class="wp-mastertoolkit__section__body">

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Sender Config', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
							<div class="description"><?php esc_html_e( 'If set, the following sender name/email overrides WordPress core defaults but can still be overridden by other plugins that enables custom sender name/email, e.g. form plugins.', 'wpmastertoolkit' ); ?></div>
							<div class="wp-mastertoolkit__input-text flex">
								<div>
									<div class="description"><strong><?php esc_html_e( 'Sender name', 'wpmastertoolkit' ); ?></strong></div>
									<input type="text" name="<?php echo esc_attr( $this->option_id . '[sender_name]' ); ?>" value="<?php echo esc_attr( $this->settings['sender_name'] ?? '' ); ?>">
								</div>
								<div>
									<div class="description"><strong><?php esc_html_e( 'Sender email', 'wpmastertoolkit' ); ?></strong></div>
									<input type="text" name="<?php echo esc_attr( $this->option_id . '[sender_email]' ); ?>" value="<?php echo esc_attr( $this->settings['sender_email'] ?? '' ); ?>">
								</div>
							</div>
							<div class="wp-mastertoolkit__checkbox">
								<label class="wp-mastertoolkit__checkbox__label">
									<input type="hidden" name="<?php echo esc_attr( $this->option_id . '[force_sender]' ); ?>" value="0">
									<input type="checkbox" name="<?php echo esc_attr( $this->option_id . '[force_sender]' ); ?>" value="1"<?php checked( $this->settings['force_sender']??'', '1' ); ?>>
									<span class="mark"></span>
									<span class="wp-mastertoolkit__checkbox__label__text"><?php esc_html_e( 'Force the usage of the sender name/email defined above. It will override those set by other plugins.', 'wpmastertoolkit' ); ?></span>
								</label>
							</div>
                        </div>
                    </div>

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'SMTP Config', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
							<div class="description"><?php esc_html_e( 'If set, the following SMTP service/account wil be used to deliver your emails.', 'wpmastertoolkit' ); ?></div>
							<div class="wp-mastertoolkit__input-text flex">
								<div>
									<div class="description"><strong><?php esc_html_e( 'Host', 'wpmastertoolkit' ); ?></strong></div>
									<input type="text" name="<?php echo esc_attr( $this->option_id . '[host]' ); ?>" value="<?php echo esc_attr( $this->settings['host'] ?? '' ); ?>">
								</div>
								<div>
									<div class="description"><strong><?php esc_html_e( 'Port', 'wpmastertoolkit' ); ?></strong></div>
									<input type="text" name="<?php echo esc_attr( $this->option_id . '[port]' ); ?>" value="<?php echo esc_attr( $this->settings['port'] ?? '' ); ?>">
								</div>
							</div>
							<div class="wp-mastertoolkit__input-text flex">
								<div>
									<div class="description"><strong><?php esc_html_e( 'Username', 'wpmastertoolkit' ); ?></strong></div>
									<input type="text" name="<?php echo esc_attr( $this->option_id . '[username]' ); ?>" value="<?php echo esc_attr( $this->settings['username'] ?? '' ); ?>">
								</div>
								<div>
									<div class="description"><strong><?php esc_html_e( 'Password', 'wpmastertoolkit' ); ?></strong></div>
									<input type="password" name="<?php echo esc_attr( $this->option_id . '[password]' ); ?>" value="<?php echo esc_attr( $this->settings['password'] ?? '' ); ?>">
								</div>
							</div>
							<div class="wp-mastertoolkit__radio">
								<div class="description"><strong><?php esc_html_e( 'Encryption Type', 'wpmastertoolkit' ); ?></strong></div>
								<?php foreach ( $encryption_options as $key => $name ) : ?>
									<label class="wp-mastertoolkit__radio__label">
										<input type="radio" name="<?php echo esc_attr( $this->option_id . '[encryption][value]' ); ?>" value="<?php echo esc_attr( $key ); ?>" <?php checked( $encryption_value, $key ); ?>>
										<span class="mark"></span>
										<span class="wp-mastertoolkit__checkbox__label__text"><?php echo esc_html( $name ); ?></span>
									</label>
								<?php endforeach; ?>
                            </div>
							<div class="wp-mastertoolkit__checkbox">
								<label class="wp-mastertoolkit__checkbox__label">
									<input type="hidden" name="<?php echo esc_attr( $this->option_id . '[bypass_ssl_verification]' ); ?>" value="0">
									<input type="checkbox" name="<?php echo esc_attr( $this->option_id . '[bypass_ssl_verification]' ); ?>" value="1"<?php checked( $this->settings['bypass_ssl_verification']??'', '1' ); ?>>
									<span class="mark"></span>
									<span class="wp-mastertoolkit__checkbox__label__text"><?php esc_html_e( 'Bypass verification of SSL certificate. This would be insecure if mail is delivered across the internet but could help in certain local and/or containerized WordPress scenarios.', 'wpmastertoolkit' ); ?></span>
								</label>
							</div>
							<div class="wp-mastertoolkit__checkbox">
								<label class="wp-mastertoolkit__checkbox__label">
									<input type="hidden" name="<?php echo esc_attr( $this->option_id . '[active_debug]' ); ?>" value="0">
									<input type="checkbox" name="<?php echo esc_attr( $this->option_id . '[active_debug]' ); ?>" value="1"<?php checked( $this->settings['active_debug']??'', '1' ); ?>>
									<span class="mark"></span>
									<span class="wp-mastertoolkit__checkbox__label__text"><?php esc_html_e( 'Enable debug mode and output the debug info into WordPress debug.log file.', 'wpmastertoolkit' ); ?></span>
								</label>
							</div>
                        </div>
                    </div>

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Email test', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
							<div class="description"><?php esc_html_e( 'After saving the settings above, check if everything is configured properly below.', 'wpmastertoolkit' ); ?></div>

							<div class="wp-mastertoolkit__input-text flex">
                                <div>
                                    <input type="email" id="JS-test-input">
                                </div>
                                <button class="copy-button" id="JS-test-btn">
									<?php esc_html_e( 'Send Now', 'wpmastertoolkit' ); ?>
                                </button>
								<div class="wp-mastertoolkit__loader" id="JS-test-loader"></div>
								<div class="wp-mastertoolkit__msg" id="JS-test-msg"></div>
                            </div>

                        </div>
                    </div>

                </div>
            </div>
        <?php
    }
}
