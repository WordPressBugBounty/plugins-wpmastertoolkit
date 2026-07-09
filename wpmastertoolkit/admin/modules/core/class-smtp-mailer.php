<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: SMTP mailer
 * Description: Set custom sender name and email. Optionally use external SMTP service to ensure notification and transactional emails from your site are being delivered to inboxes.
 * @since 1.7.0
 * @updated 1.8.0
 */
class WPMastertoolkit_SMTP_Mailer {

	public $option_id;
	public $page_id;
	public $auth_id;
	public $providers = array(
		'core/smtp-mailer/php.php',
		'core/smtp-mailer/other.php',
		'pro/smtp-mailer/gmail.php',
		'pro/smtp-mailer/outlook.php',
		'pro/smtp-mailer/sendgrid.php',
		'pro/smtp-mailer/aws.php',
		'pro/smtp-mailer/brevo.php',
		'pro/smtp-mailer/mailgun.php',
		'pro/smtp-mailer/mailjet.php',
		'pro/smtp-mailer/postmark.php',
		'pro/smtp-mailer/sparkpost.php',
		'pro/smtp-mailer/mailersend.php',
		'pro/smtp-mailer/resend.php',
		'pro/smtp-mailer/sendlayer.php',
		'pro/smtp-mailer/smtpcom.php',
		'pro/smtp-mailer/smtp2go.php',
		'pro/smtp-mailer/elasticemail.php',
		'pro/smtp-mailer/zohomail.php',
		'pro/smtp-mailer/sendpulse.php',
		'pro/smtp-mailer/mandrill.php',
		'pro/smtp-mailer/pepipost.php',
	);
	public $providers_catchers = array(
		'core/smtp-mailer/mailer-catcher.php',
		'core/smtp-mailer/mailer-abstract.php',
		'pro/smtp-mailer/gmail-catcher.php',
		'pro/smtp-mailer/outlook-catcher.php',
		'pro/smtp-mailer/sendgrid-catcher.php',
		'pro/smtp-mailer/aws-catcher.php',
		'pro/smtp-mailer/brevo-catcher.php',
		'pro/smtp-mailer/mailgun-catcher.php',
		'pro/smtp-mailer/mailjet-catcher.php',
		'pro/smtp-mailer/postmark-catcher.php',
		'pro/smtp-mailer/sparkpost-catcher.php',
		'pro/smtp-mailer/mailersend-catcher.php',
		'pro/smtp-mailer/resend-catcher.php',
		'pro/smtp-mailer/sendlayer-catcher.php',
		'pro/smtp-mailer/smtpcom-catcher.php',
		'pro/smtp-mailer/smtp2go-catcher.php',
		'pro/smtp-mailer/elasticemail-catcher.php',
		'pro/smtp-mailer/zohomail-catcher.php',
		'pro/smtp-mailer/sendpulse-catcher.php',
		'pro/smtp-mailer/mandrill-catcher.php',
		'pro/smtp-mailer/pepipost-catcher.php',
	);

	private $header_title;
	private $nonce_action;
	private $settings;
	private $default_settings;
	private $ajax_action;
	private $ajax_nonce;
	private $wp_mail_from;
	private $filtered_from_email;
	private $filtered_from_name;

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

		$this->page_id = 'wp-mastertoolkit-settings-smtp-mailer';
		$this->auth_id = 'smtp-mailer-auth';

		$this->require_providers();

		add_action( 'init', array( $this, 'class_init' ) );
		add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
		add_action( 'admin_init', array( $this, 'save_submenu' ) );

		add_action( 'init', array( $this, 'for_smtps_has_auth' ) );
		add_action( 'admin_init', array( $this, 'maybe_save_auth' ) );
		add_filter( 'wp_mail_from', array( $this, 'filter_mail_from_email' ), PHP_INT_MAX );
		add_filter( 'wp_mail_from_name', array( $this, 'filter_mail_from_name' ), PHP_INT_MAX );
		add_action( 'phpmailer_init', array( $this, 'phpmailer_init') );
		add_action( 'plugins_loaded', array( $this, 'replace_phpmailer' ) );
		add_action( 'wp_ajax_' . $this->ajax_action, array( $this, 'send_test_email') );
	}

	/**
	 * Require providers files
	 * 
	 * @since    2.14.0
	 */
	public function require_providers() {
		foreach ( $this->providers as $provider ) {
			$path = WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/helpers/' . $provider;
			if ( is_readable( $path ) ) {
				include_once $path;
			}
		}
	}

	/**
	 * Require providers catchers files
	 * 
	 * @since    2.14.0
	 */
	public function require_providers_catchers() {
		foreach ( $this->providers_catchers as $provider_catcher ) {
			$path = WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/helpers/' . $provider_catcher;
			if ( is_readable( $path ) ) {
				include_once $path;
			}
		}
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
	 * Check if SMTP has authentication
	 * 
	 * @since   2.14.0
	 */
	public function for_smtps_has_auth() {
		if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		if ( is_admin() ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			// Run provider auth refresh only on SMTP page to avoid unintended settings writes.
			//phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$current_page = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) );
			if ( $this->page_id !== $current_page ) {
				return;
			}

			$this->settings         = $this->get_settings();
			$this->default_settings = $this->get_default_settings();
			
			$result          = false;
			$active_provider = $this->settings['active_provider'] ?? $this->default_settings['active_provider'];

			if ( 'gmail' == $active_provider ) {
				if ( method_exists( 'WPMastertoolkit_SMTP_Mailer_Gmail', 'get_client_web_service' ) ) {
					$result = WPMastertoolkit_SMTP_Mailer_Gmail::get_client_web_service( $this );
				}
			} elseif ( 'outlook' == $active_provider ) {
				if ( method_exists( 'WPMastertoolkit_SMTP_Mailer_Outlook', 'get_client_web_service' ) ) {
					$result = WPMastertoolkit_SMTP_Mailer_Outlook::get_client_web_service( $this );
				}
			} elseif ( 'zohomail' == $active_provider ) {
				if ( method_exists( 'WPMastertoolkit_SMTP_Mailer_Zohomail', 'get_client_web_service' ) ) {
					$result = WPMastertoolkit_SMTP_Mailer_Zohomail::get_client_web_service( $this );
				}
			} elseif( 'sendpulse' == $active_provider ) {
				if ( method_exists( 'WPMastertoolkit_SMTP_Mailer_Sendpulse', 'get_client_web_service' ) ) {
					$result = WPMastertoolkit_SMTP_Mailer_Sendpulse::get_client_web_service( $this );
				}
			}

			if ( $result ) {
				$this->save_settings( $result );

				wp_safe_redirect( sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) );
				exit;
			}
		}
	}

	/**
	 * Save provider authorization if needed
	 * 
	 * @since   2.14.0
	 */
	public function maybe_save_auth() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$this->settings         = $this->get_settings();
		$this->default_settings = $this->get_default_settings();

		//phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$code = sanitize_text_field( wp_unslash( $_GET['code'] ?? '' ) );
		if ( ! empty( $code ) ) {
			
			$active_provider = $this->settings['active_provider'] ?? $this->default_settings['active_provider'];
			$class_name      = 'WPMastertoolkit_SMTP_Mailer_' . ucfirst( $active_provider );
			if ( method_exists( $class_name, 'save_auth' ) ) {
				$result = call_user_func( array( $class_name, 'save_auth' ), $this );
	
				if ( $result ) {
					$this->save_settings( $result );
	
					$submenu_url = add_query_arg(
						array( 'page' => $this->page_id ),
						admin_url( 'admin.php' )
					);
	
					wp_safe_redirect( $submenu_url );
					exit;
				}
			}
		}
	}

	/**
	 * Filter mail from email
	 * 
	 * @since   2.14.0
	 */
	public function filter_mail_from_email( $wp_email ) {
		$this->settings         = $this->get_settings();
		$this->default_settings = $this->get_default_settings();

		$def_email         = $this->get_default_email();
		$selected_provider = $this->settings['active_provider'] ?? $this->default_settings['active_provider'];
		$provider_params   = $this->settings['providers'][ $selected_provider ]['params'] ?? array();
		$force_sender      = $provider_params['force_sender']['value'] ?? '0';
		$sender_email      = $provider_params['sender_email']['value'] ?? '';

		// Save the original from address.
		$this->filtered_from_email = filter_var( $wp_email, FILTER_VALIDATE_EMAIL );

		// Save the "original" set WP email from address for later use.
		if ( $wp_email !== $def_email ) {
			$this->wp_mail_from = filter_var( $wp_email, FILTER_VALIDATE_EMAIL );
		}

		// Return FROM EMAIL if forced in settings.
		if ( $force_sender && ! empty( $sender_email ) ) {
			return $sender_email;
		}

		// If the FROM EMAIL is not the default, return it unchanged.
		if ( ! empty( $def_email ) && $wp_email !== $def_email ) {
			return $wp_email;
		}

		return ! empty( $sender_email ) ? $sender_email : $wp_email;
	}

	/**
	 * Filter mail from name
	 * 
	 * @since   2.14.0
	 */
	public function filter_mail_from_name( $name ) {
		$this->settings         = $this->get_settings();
		$this->default_settings = $this->get_default_settings();

		$selected_provider = $this->settings['active_provider'] ?? $this->default_settings['active_provider'];
		$provider_params   = $this->settings['providers'][ $selected_provider ]['params'] ?? array();
		$force_sender      = $provider_params['force_sender']['value'] ?? '0';
		$sender_name       = $provider_params['sender_name']['value'] ?? '';

		// Save the original from name.
		$this->filtered_from_name = $name;

		// If the FROM NAME is not the default and not forced, return it unchanged.
		if ( ! $force_sender && $name !== 'WordPress' ) {
			return $name;
		}

		return $sender_name;
	}

	/**
	 * Send email via SMTP
	 * 
	 * @since   1.7.0
	 */
	public function phpmailer_init( $phpmailer ) {
		$this->settings         = $this->get_settings();
		$this->default_settings = $this->get_default_settings();

		$active_provider = $this->settings['active_provider'] ?? $this->default_settings['active_provider'];
		$encryption      = 'none';
		$autotls         = '1';

		if ( 'other' == $active_provider ) {
			$host           = $this->settings['providers']['other']['params']['host']['value'] ?? '';
			$port           = $this->settings['providers']['other']['params']['port']['value'] ?? '';
			$authentication = $this->settings['providers']['other']['params']['authentication']['value'] ?? $this->default_settings['providers']['other']['params']['authentication']['value'];
			$username       = $this->settings['providers']['other']['params']['username']['value'] ?? '';
			$password       = $this->settings['providers']['other']['params']['password']['value'] ?? '';
			$encryption     = $this->settings['providers']['other']['params']['encryption']['value']['value'] ?? $this->default_settings['providers']['other']['params']['encryption']['value']['value'];
			$autotls        = $this->settings['providers']['other']['params']['autotls']['value'] ?? $this->default_settings['providers']['other']['params']['autotls']['value'];

			if ( empty( $host ) ) {
				return;
			}

			// Set the other options.
			$phpmailer->Host = $host;
			$phpmailer->Port = $port;

			// If we're using smtp auth, set the username & password.
			if ( '1' == $authentication ) {
				$phpmailer->SMTPAuth = true;
				$phpmailer->Username = $username;
				$phpmailer->Password = $password;
			}
		}

		// Set the mailer type as per config above, this overrides the already called isMail method.
		switch ( $active_provider ) {
			case 'php':
				$active_provider = 'mail';
			break;
			case 'other':
				$active_provider = 'smtp';
			break;
		}
		$phpmailer->Mailer = $active_provider;

		// Set the SMTPSecure value, if set to none, leave this blank. Possible values: 'ssl', 'tls', ''.
		if ( 'none' === $encryption ) {
			$phpmailer->SMTPSecure = '';
		} else {
			$phpmailer->SMTPSecure = $encryption;
		}

		// Check if user has disabled SMTPAutoTLS.
		if ( 'tls' !== $encryption && '0' == $autotls ) {
			$phpmailer->SMTPAutoTLS = false;
		}

		// Check if original WP from email can be set as the reply_to attribute.
		if ( $this->allow_setting_original_from_email_to_reply_to( $phpmailer->getReplyToAddresses(), $active_provider ) ) {
			$phpmailer->addReplyTo( $this->wp_mail_from );
		}

		$phpmailer->Timeout = 30;
	}

	/**
	 * Replace PHPMailer
	 * 
	 * @since   2.14.0
	 */
	public function replace_phpmailer() {
		if ( ! class_exists( '\PHPMailer\PHPMailer\PHPMailer', false ) ) {
			require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
		}

		if ( ! class_exists( '\PHPMailer\PHPMailer\Exception', false ) ) {
			require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
		}

		if ( ! class_exists( '\PHPMailer\PHPMailer\SMTP', false ) ) {
			require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
		}

		include_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/helpers/core/smtp-mailer/mailer-catcher.php';

		global $phpmailer;
		$phpmailer = new WPMastertoolkit_SMTP_Mailer_Mailer_Catcher();
	}

	/**
	 * Send a test email
	 * 
	 * @since   1.7.0
	 */
	public function send_test_email() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to perform this action.', 'wpmastertoolkit' ) ) );
		}

		// Verify nonce
		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, $this->ajax_nonce ) ) {
			wp_send_json_error( array( 'message' => __( 'Refresh the page and try again.', 'wpmastertoolkit' ) ) );
		}

		$email = sanitize_email( wp_unslash( $_POST['testEmail'] ?? '' ) );
		if ( empty( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'wpmastertoolkit' ) ) );
		}

		// Clear previous errors
		$GLOBALS['phpmailer']->clearAllRecipients();
		$GLOBALS['phpmailer']->clearAttachments();
		$GLOBALS['phpmailer']->clearCustomHeaders();
		$GLOBALS['phpmailer']->clearReplyTos();

		// Send test email
		$to      = $email;
		$subject = __( 'WPMasterToolkit - SMTP Test Email', 'wpmastertoolkit' );
		$message = '<p><strong>' . __( 'Congratulations! Your SMTP settings are working correctly.', 'wpmastertoolkit' ) . '</strong></p>';
		$message .= '<p>' . __( 'This is a test email sent from your WordPress site to verify your SMTP configuration.', 'wpmastertoolkit' ) . '</p>';
		/* translators: %s: The site name */
		$message .= '<p>' . sprintf( __( 'Site: %s', 'wpmastertoolkit' ), get_bloginfo( 'name' ) ) . '</p>';
		/* translators: %s: The current time */
		$message .= '<p>' . sprintf( __( 'Sent at: %s', 'wpmastertoolkit' ), current_time( 'mysql' ) ) . '</p>';
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		$success = wp_mail( $to, $subject, $message, $headers );

		if ( $success ) {
			wp_send_json_success( array( 
				'message' => __( 'Test email sent successfully! Please check your inbox.', 'wpmastertoolkit' ) 
			) );
		} else {
			// Get error details if available
			$error_message = __( 'Failed to send test email. Please check your SMTP settings.', 'wpmastertoolkit' );
			
			if ( isset( $GLOBALS['phpmailer']->ErrorInfo ) && ! empty( $GLOBALS['phpmailer']->ErrorInfo ) ) {
				$error_message .= ' ' . __( 'Error:', 'wpmastertoolkit' ) . ' ' . $GLOBALS['phpmailer']->ErrorInfo;
			}
			
			wp_send_json_error( array( 'message' => $error_message ) );
		}
	}

	/**
	 * Remove provider authorization if needed
	 * 
	 * @since   2.14.0
	 */
	public function maybe_remove_auth( $provider ) {
		$result = false;

		if ( 'gmail' == $provider ) {
			if ( method_exists( 'WPMastertoolkit_SMTP_Mailer_Gmail', 'remove_auth' ) ) {
				$result = WPMastertoolkit_SMTP_Mailer_Gmail::remove_auth( $this );
			}
		} elseif ( 'outlook' == $provider ) {
			if ( method_exists( 'WPMastertoolkit_SMTP_Mailer_Outlook', 'remove_auth' ) ) {
				$result = WPMastertoolkit_SMTP_Mailer_Outlook::remove_auth( $this );
			}
		} elseif ( 'zohomail' == $provider ) {
			if ( method_exists( 'WPMastertoolkit_SMTP_Mailer_Zohomail', 'remove_auth' ) ) {
				$result = WPMastertoolkit_SMTP_Mailer_Zohomail::remove_auth( $this );
			}
		}

		if ( $result ) {
			$this->save_settings( $result );
		}
	}

	/**
	 * Add a submenu
	 * 
	 * @since   1.7.0
	 */
	public function add_submenu(){
		WPMastertoolkit_Settings::add_submenu_page(
			'wp-mastertoolkit-settings',
			$this->header_title,
			$this->header_title,
			'manage_options',
			$this->page_id,
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
		$submenu_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/smtp-mailer.asset.php' );
		wp_enqueue_style( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/smtp-mailer.css', array(), $submenu_assets['version'], 'all' );
		wp_enqueue_script( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/smtp-mailer.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );
		wp_localize_script( 'WPMastertoolkit_submenu', 'wpmastertoolkit_smtp_mailer', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'action'  => $this->ajax_action,
			'nonce'   => wp_create_nonce( $this->ajax_nonce ),
			'i18n'    => array(
				'save_before_test'    => esc_html__( 'Please save your settings before testing the email.', 'wpmastertoolkit' ),
				'save_before_confirm' => esc_html__( 'Please save your settings before confirming the authorization.', 'wpmastertoolkit' ),
			),
		));

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
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
		if ( wp_verify_nonce( $nonce, $this->nonce_action ) ) {

			$reset_all = sanitize_text_field( wp_unslash( $_POST['reset_all_providers'] ?? '' ) );
			if ( '1' === $reset_all ) {
				$this->default_settings = $this->get_default_settings();
				$this->save_settings( $this->default_settings );
				wp_safe_redirect( sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) );
				exit;
			}

			$remove_auth = sanitize_text_field( wp_unslash( $_POST['remove_auth'] ?? '' ) );
			if ( ! empty( $remove_auth ) ) {
				$this->maybe_remove_auth( $remove_auth );
				
				wp_safe_redirect( sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) );
				exit;
			}

			//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$new_settings = $this->sanitize_settings(wp_unslash(  $_POST[ $this->option_id ] ?? array() ) );
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
		$this->settings         = $this->get_settings();
		$this->default_settings = $this->get_default_settings();
		$sanitized_settings     = $this->settings;

		foreach ( $this->default_settings as $settings_key => $settings_value ) {
			if ( 'providers' === $settings_key ) {

				$selected_provider       = sanitize_text_field( $new_settings['active_provider'] ?? $this->settings['active_provider'] ?? $this->default_settings['active_provider'] );
				$default_provider_params = $settings_value[ $selected_provider ]['params'] ?? array();

				foreach ( $default_provider_params as $param_key => $param_value ) {
					$param_type = $param_value['type'];

					switch ( $param_type ) {
						case 'db':
							$sanitized_settings[ $settings_key ][ $selected_provider ]['params'][ $param_key ]['value'] = sanitize_text_field( $this->settings['providers'][ $selected_provider ]['params'][ $param_key ]['value'] ?? '' );
						break;
						case 'text':
						case 'password':
						case 'checkbox':
							$sanitized_settings[ $settings_key ][ $selected_provider ]['params'][ $param_key ]['value'] = sanitize_text_field( $new_settings[ $settings_key ][ $selected_provider ]['params'][ $param_key ] ?? $param_value['value'] );
						break;
						case 'select':
							$new_value = $new_settings[ $settings_key ][ $selected_provider ]['params'][ $param_key ]['value'] ?? $param_value['value']['value'];
							if ( array_key_exists( $new_value, $param_value['value']['options'] ) ) {
								$sanitized_value = $new_value;
							} else {
								$sanitized_value = $param_value['value']['value'];
							}
							$sanitized_settings[ $settings_key ][ $selected_provider ]['params'][ $param_key ]['value']['value'] = $sanitized_value;
						break;
					}
				}

			} else {
				$sanitized_settings[ $settings_key ] = sanitize_text_field( $new_settings[ $settings_key ] ?? $settings_value );
			}
		}

		$old_value_to_remove = array( 'sender_name', 'sender_email', 'force_sender' );
		foreach ( $old_value_to_remove as $old_key ) {
			if ( isset( $sanitized_settings[ $old_key ] ) ) {
				unset( $sanitized_settings[ $old_key ] );
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
	public function get_settings(){
		if( $this->settings !== null ) return $this->settings;

		$this->default_settings = $this->get_default_settings();
		$settings = get_option( $this->option_id, $this->default_settings );

		// Some sites may contain a malformed option value (e.g. plain string) from legacy saves.
		if ( is_string( $settings ) ) {
			$decoded_settings = json_decode( $settings, true );
			if ( is_array( $decoded_settings ) ) {
				$settings = $decoded_settings;
			}
		}

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$settings = wp_parse_args( $settings, $this->default_settings );

		if ( ! isset( $settings['providers'] ) || ! is_array( $settings['providers'] ) ) {
			$settings['providers'] = $this->default_settings['providers'];
		}

		// Handle the conpatibility for older versions
		if ( ! empty( $settings['host'] ) && ! empty( $settings['port'] ) ) {
			$legacy_encryption = 'none';
			if ( isset( $settings['encryption'] ) ) {
				if ( is_array( $settings['encryption'] ) ) {
					$legacy_encryption = $settings['encryption']['value'] ?? 'none';
				} elseif ( is_string( $settings['encryption'] ) ) {
					$legacy_encryption = $settings['encryption'];
				}
			}

			$settings['active_provider']                                              = 'other';
			$settings['providers']['other']['params']['host']['value']                = $settings['host'];
			$settings['providers']['other']['params']['port']['value']                = $settings['port'];
			$settings['providers']['other']['params']['encryption']['value']['value'] = $legacy_encryption;

			if ( ! empty( $settings['username'] ) && ! empty( $settings['password'] ) ) {
				$settings['providers']['other']['params']['authentication']['value'] = '1';
				$settings['providers']['other']['params']['username']['value']       = $settings['username'];
				$settings['providers']['other']['params']['password']['value']       = $settings['password'];
			} else {
				$settings['providers']['other']['params']['authentication']['value'] = '0';
			}
		}

		// Handle the older versions that don't have php as a provider.
		if ( ! isset( $settings['providers']['php'] ) ) {
			$settings['providers']['php'] = $this->default_settings['providers']['php'];
		}

		// Handle the compatibility for older versions that don't have the sender_name|sender_email|force_sender in each provider params.
		$providers = $settings['providers'] ?? array();
		foreach ( $providers as $provider => $provider_data ) {
			if ( ! is_array( $provider_data ) ) {
				continue;
			}

			$sender_name = $provider_data['params']['sender_name']['value'] ?? '';
			if ( empty( $sender_name ) && ! empty( $settings['sender_name'] ) ) {
				$settings['providers'][ $provider ]['params']['sender_name']['value'] = get_bloginfo( 'name' );
			}

			$sender_email = $provider_data['params']['sender_email']['value'] ?? '';
			if ( empty( $sender_email ) && ! empty( $settings['sender_email'] ) ) {
				$settings['providers'][ $provider ]['params']['sender_email']['value'] = get_option( 'admin_email' );
			}

			$force_sender = $provider_data['params']['force_sender']['value'] ?? '';
			if ( empty( $force_sender ) && ! empty( $settings['force_sender'] ) ) {
				$settings['providers'][ $provider ]['params']['force_sender']['value'] = '1';
			}
		}

		$this->settings = $settings;

		return $this->settings;
		
	}

	/**
	 * get_default_settings
	 *
	 * @since   1.7.0
	 * @return array
	 */
	public function get_default_settings(){
		if( $this->default_settings !== null ) return $this->default_settings;

		return array(
			'active_provider' => 'php',
			'providers'       => array(
				'php' => array(
					'pro' => false,
					'params' => array(
						'sender_name' => array(
							'type'  => 'text',
							'value' => get_bloginfo( 'name' ),
						),
						'sender_email' => array(
							'type'  => 'text',
							'value' => get_option( 'admin_email' ),
						),
						'force_sender' => array(
							'type'  => 'checkbox',
							'value' => '0',
						),
					),
				),
				'other' => array(
					'pro'    => false,
					'params' => array(
						'host' => array(
							'type'  => 'text',
							'value' => '',
						),
						'port' => array(
							'type'  => 'text',
							'value' => '',
						),
						'authentication' => array(
							'type'  => 'checkbox',
							'value' => '1',
						),
						'username' => array(
							'type'  => 'text',
							'value' => '',
						),
						'password' => array(
							'type'  => 'password',
							'value' => '',
						),
						'encryption' => array(
							'type'  => 'select',
							'value' => array(
								'value'   => 'none',
								'options' => array(
									'none' => __( 'None', 'wpmastertoolkit' ),
									'ssl'  => __( 'SSL', 'wpmastertoolkit' ),
									'tls'  => __( 'TLS', 'wpmastertoolkit' ),
								),
							),
						),
						'autotls' => array(
							'type'  => 'checkbox',
							'value' => '1',
						),
						'sender_name' => array(
							'type'  => 'text',
							'value' => get_bloginfo( 'name' ),
						),
						'sender_email' => array(
							'type'  => 'text',
							'value' => get_option( 'admin_email' ),
						),
						'force_sender' => array(
							'type'  => 'checkbox',
							'value' => '0',
						),
					),
				),
				'gmail' => array(
					'pro'    => true,
					'params' => array(
						'client_id' => array(
							'type'  => 'text',
							'value' => '',
						),
						'client_secret' => array(
							'type'  => 'password',
							'value' => '',
						),
						'auth_code' => array(
							'type'  => 'db',
							'value' => '',
						),
						'access_token' => array(
							'type'  => 'db',
							'value' => '',
						),
						'user_email' => array(
							'type'  => 'db',
							'value' => '',
						),
						'sender_name' => array(
							'type'  => 'text',
							'value' => get_bloginfo( 'name' ),
						),
						'sender_email' => array(
							'type'  => 'text',
							'value' => get_option( 'admin_email' ),
						),
						'force_sender' => array(
							'type'  => 'checkbox',
							'value' => '0',
						),
					),
				),
				'outlook' => array(
					'pro'    => true,
					'params' => array(
						'client_id' => array(
							'type'  => 'text',
							'value' => '',
						),
						'client_secret' => array(
							'type'  => 'password',
							'value' => '',
						),
						'auth_code' => array(
							'type'  => 'db',
							'value' => '',
						),
						'access_token' => array(
							'type'  => 'db',
							'value' => '',
						),
						'user_email' => array(
							'type'  => 'db',
							'value' => '',
						),
						'sender_name' => array(
							'type'  => 'text',
							'value' => get_bloginfo( 'name' ),
						),
						'sender_email' => array(
							'type'  => 'text',
							'value' => get_option( 'admin_email' ),
						),
						'force_sender' => array(
							'type'  => 'checkbox',
							'value' => '0',
						),
					),
				),
				'sendgrid' => array(
					'pro'    => true,
					'params' => array(
						'api_key' => array(
							'type'  => 'password',
							'value' => '',
						),
						'sending_domain' => array(
							'type'  => 'text',
							'value' => '',
						),
						'sender_name' => array(
							'type'  => 'text',
							'value' => get_bloginfo( 'name' ),
						),
						'sender_email' => array(
							'type'  => 'text',
							'value' => get_option( 'admin_email' ),
						),
						'force_sender' => array(
							'type'  => 'checkbox',
							'value' => '1',
						),
					),
				),
				'aws' => array(
					'pro'    => true,
					'params' => array(
						'access_key_id' => array(
							'type'  => 'text',
							'value' => '',
						),
						'secret_access_key' => array(
							'type'  => 'password',
							'value' => '',
						),
						'region' => array(
							'type'  => 'text',
							'value' => 'us-east-1',
						),
						'sender_name' => array(
							'type'  => 'text',
							'value' => get_bloginfo( 'name' ),
						),
						'sender_email' => array(
							'type'  => 'text',
							'value' => get_option( 'admin_email' ),
						),
						'force_sender' => array(
							'type'  => 'checkbox',
							'value' => '0',
						),
					),
				),
				'brevo' => array(
					'pro'    => true,
					'params' => array(
						'api_key' => array(
							'type'  => 'password',
							'value' => '',
						),
						'sending_domain' => array(
							'type'  => 'text',
							'value' => '',
						),
						'sender_name' => array(
							'type'  => 'text',
							'value' => get_bloginfo( 'name' ),
						),
						'sender_email' => array(
							'type'  => 'text',
							'value' => get_option( 'admin_email' ),
						),
						'force_sender' => array(
							'type'  => 'checkbox',
							'value' => '1',
						),
					),
				),
				'mailgun' => array(
					'pro'    => true,
					'params' => array(
						'api_key' => array(
							'type'  => 'password',
							'value' => '',
						),
						'sending_domain' => array(
							'type'  => 'text',
							'value' => '',
						),
						'region' => array(
							'type'  => 'text',
							'value' => 'us',
						),
						'sender_name' => array(
							'type'  => 'text',
							'value' => get_bloginfo( 'name' ),
						),
						'sender_email' => array(
							'type'  => 'text',
							'value' => get_option( 'admin_email' ),
						),
						'force_sender' => array(
							'type'  => 'checkbox',
							'value' => '1',
						),
					),
				),
				'mailjet' => array(
					'pro'    => true,
					'params' => array(
						'api_key' => array(
							'type'  => 'password',
							'value' => '',
						),
						'secret_key' => array(
							'type'  => 'password',
							'value' => '',
						),
						'sender_name' => array(
							'type'  => 'text',
							'value' => get_bloginfo( 'name' ),
						),
						'sender_email' => array(
							'type'  => 'text',
							'value' => get_option( 'admin_email' ),
						),
						'force_sender' => array(
							'type'  => 'checkbox',
							'value' => '1',
						),
					),
				),
				'postmark' => array(//This provider not tested due to lack of account.
					'pro'    => true,
					'params' => array(
						'server_api_token' => array(
							'type'  => 'password',
							'value' => '',
						),
						'message_stream' => array(
							'type'  => 'text',
							'value' => '',
						),
						'sender_name' => array(
							'type'  => 'text',
							'value' => get_bloginfo( 'name' ),
						),
						'sender_email' => array(
							'type'  => 'text',
							'value' => get_option( 'admin_email' ),
						),
						'force_sender' => array(
							'type'  => 'checkbox',
							'value' => '1',
						),
					),
				),
				'sparkpost' => array(
					'pro'    => true,
					'params' => array(
						'api_key' => array(
							'type'  => 'password',
							'value' => '',
						),
						'region' => array(
							'type'  => 'text',
							'value' => 'us',
						),
						'sender_name' => array(
							'type'  => 'text',
							'value' => get_bloginfo( 'name' ),
						),
						'sender_email' => array(
							'type'  => 'text',
							'value' => get_option( 'admin_email' ),
						),
						'force_sender' => array(
							'type'  => 'checkbox',
							'value' => '1',
						),
					),
				),
				'mailersend' => array(
					'pro'    => true,
					'params' => array(
						'api_key' => array(
							'type'  => 'password',
							'value' => '',
						),
						'has_pro_plan' => array(
							'type'  => 'checkbox',
							'value' => '0',
						),
						'sender_name' => array(
							'type'  => 'text',
							'value' => get_bloginfo( 'name' ),
						),
						'sender_email' => array(
							'type'  => 'text',
							'value' => get_option( 'admin_email' ),
						),
						'force_sender' => array(
							'type'  => 'checkbox',
							'value' => '1',
						),
					),
				),
				'resend' => array(
					'pro'    => true,
					'params' => array(
						'api_key' => array(
							'type'  => 'password',
							'value' => '',
						),
						'sender_name' => array(
							'type'  => 'text',
							'value' => get_bloginfo( 'name' ),
						),
						'sender_email' => array(
							'type'  => 'text',
							'value' => get_option( 'admin_email' ),
						),
						'force_sender' => array(
							'type'  => 'checkbox',
							'value' => '1',
						),
					),
				),
				'sendlayer' => array(
					'pro'    => true,
					'params' => array(
						'api_key' => array(
							'type'  => 'password',
							'value' => '',
						),
						'sender_name' => array(
							'type'  => 'text',
							'value' => get_bloginfo( 'name' ),
						),
						'sender_email' => array(
							'type'  => 'text',
							'value' => get_option( 'admin_email' ),
						),
						'force_sender' => array(
							'type'  => 'checkbox',
							'value' => '1',
						),
					),
				),
				'smtpcom' => array(//This provider not tested due to lack of account.
					'pro'    => true,
					'params' => array(
						'api_key' => array(
							'type'  => 'password',
							'value' => '',
						),
						'sender_name' => array(
							'type'  => 'text',
							'value' => '',
						),
						'sender_email' => array(
							'type'  => 'text',
							'value' => get_option( 'admin_email' ),
						),
						'force_sender' => array(
							'type'  => 'checkbox',
							'value' => '0',
						),
					),
				),
				'smtp2go' => array(
					'pro'    => true,
					'params' => array(
						'api_key' => array(
							'type'  => 'password',
							'value' => '',
						),
						'sender_name' => array(
							'type'  => 'text',
							'value' => get_bloginfo( 'name' ),
						),
						'sender_email' => array(
							'type'  => 'text',
							'value' => get_option( 'admin_email' ),
						),
						'force_sender' => array(
							'type'  => 'checkbox',
							'value' => '1',
						),
					),
				),
				'elasticemail' => array(
					'pro'    => true,
					'params' => array(
						'api_key' => array(
							'type'  => 'password',
							'value' => '',
						),
						'sender_name' => array(
							'type'  => 'text',
							'value' => get_bloginfo( 'name' ),
						),
						'sender_email' => array(
							'type'  => 'text',
							'value' => get_option( 'admin_email' ),
						),
						'force_sender' => array(
							'type'  => 'checkbox',
							'value' => '1',
						),
					),
				),
				'zohomail' => array(
					'pro'    => true,
					'params' => array(
						'client_id' => array(
							'type'  => 'text',
							'value' => '',
						),
						'client_secret' => array(
							'type'  => 'password',
							'value' => '',
						),
						'region' => array(
							'type'  => 'text',
							'value' => 'zoho.com',
						),
						'auth_code' => array(
							'type'  => 'db',
							'value' => '',
						),
						'access_token' => array(
							'type'  => 'db',
							'value' => '',
						),
						'refresh_token' => array(
							'type'  => 'db',
							'value' => '',
						),
						'token_created_at' => array(
							'type'  => 'db',
							'value' => '',
						),
						'token_expires_in' => array(
							'type'  => 'db',
							'value' => '',
						),
						'user_email' => array(
							'type'  => 'db',
							'value' => '',
						),
						'account_id' => array(
							'type'  => 'db',
							'value' => '',
						),
						'sender_name' => array(
							'type'  => 'text',
							'value' => get_bloginfo( 'name' ),
						),
					),
				),
				'sendpulse' => array(
					'pro'    => true,
					'params' => array(
						'api_key' => array(
							'type'  => 'password',
							'value' => '',
						),
						'secret_key' => array(
							'type'  => 'password',
							'value' => '',
						),
						'access_token' => array(
							'type'  => 'db',
							'value' => '',
						),
						'token_created_at' => array(
							'type'  => 'db',
							'value' => '',
						),
						'token_expires_in' => array(
							'type'  => 'db',
							'value' => '',
						),
						'sender_name' => array(
							'type'  => 'text',
							'value' => get_bloginfo( 'name' ),
						),
						'sender_email' => array(
							'type'  => 'text',
							'value' => get_option( 'admin_email' ),
						),
						'force_sender' => array(
							'type'  => 'checkbox',
							'value' => '1',
						),
					),
				),
				'mandrill' => array(//This provider not tested due to lack of account.
					'pro'    => true,
					'params' => array(
						'api_key' => array(
							'type'  => 'password',
							'value' => '',
						),
						'sender_name' => array(
							'type'  => 'text',
							'value' => get_bloginfo( 'name' ),
						),
						'sender_email' => array(
							'type'  => 'text',
							'value' => get_option( 'admin_email' ),
						),
						'force_sender' => array(
							'type'  => 'checkbox',
							'value' => '1',
						),
					),
				),
				'pepipost' => array(//This provider not tested due to lack of account.
					'pro'    => true,
					'params' => array(
						'api_key' => array(
							'type'  => 'password',
							'value' => '',
						),
						'sender_name' => array(
							'type'  => 'text',
							'value' => get_bloginfo( 'name' ),
						),
						'sender_email' => array(
							'type'  => 'text',
							'value' => get_option( 'admin_email' ),
						),
						'force_sender' => array(
							'type'  => 'checkbox',
							'value' => '1',
						),
					),
				),
			),
		);
	}

	/**
	 * Check if it's allowed to set the original WP from email to the reply_to field.
	 * 
	 * @since   2.14.0
	 */
	private function allow_setting_original_from_email_to_reply_to( $reply_to, $mailer ) {
		$this->settings         = $this->get_settings();
		$this->default_settings = $this->get_default_settings();

		$selected_provider = $this->settings['active_provider'] ?? $this->default_settings['active_provider'];
		$provider_params   = $this->settings['providers'][ $selected_provider ]['params'] ?? array();
		$force_sender      = $provider_params['force_sender']['value'] ?? '0';
		$sender_email      = $provider_params['sender_email']['value'] ?? '';

		if ( ! empty( $reply_to ) || empty( $this->wp_mail_from ) ) {
			return false;
		}

		if ( in_array( $mailer, [ 'zohomail' ], true ) ) {
			$sender_email = $this->settings['providers']['zohomail']['params']['user_email']['value'] ?? '';
			$force_sender = true;
		}

		if ( $sender_email === $this->wp_mail_from || ! $force_sender ) {
			return false;
		}

		return true;
	}

	/**
	 * Get default email address
	 * 
	 * @since   2.14.0
	 */
	private function get_default_email() {
		if ( version_compare( get_bloginfo( 'version' ), '5.5-alpha', '<' ) ) {
			$sitename = ! empty( $_SERVER['SERVER_NAME'] ) ?
				strtolower( sanitize_text_field( wp_unslash( $_SERVER['SERVER_NAME'] ) ) ) :
				wp_parse_url( get_home_url( get_current_blog_id() ), PHP_URL_HOST );
		} else {
			$sitename = wp_parse_url( network_home_url(), PHP_URL_HOST );
		}

		if ( 'www.' === substr( $sitename, 0, 4 ) ) {
			$sitename = substr( $sitename, 4 );
		}

		return 'wordpress@' . $sitename;
	}

	/**
	 * Save settings
	 * 
	 * @since   1.7.0
	 */
	public function save_settings( $new_settings ) {
		if ( ! is_array( $new_settings ) ) {
			return;
		}

		$current_settings = get_option( $this->option_id, array() );
		if ( is_string( $current_settings ) ) {
			$decoded_current_settings = json_decode( $current_settings, true );
			if ( is_array( $decoded_current_settings ) ) {
				$current_settings = $decoded_current_settings;
			}
		}

		if ( ! is_array( $current_settings ) ) {
			$current_settings = array();
		}

		if ( isset( $new_settings['providers'] ) && ! is_array( $new_settings['providers'] ) ) {
			unset( $new_settings['providers'] );
		}

		$default_settings = $this->get_default_settings();
		$merged_settings  = array_replace_recursive( $default_settings, $current_settings, $new_settings );

		if ( empty( $merged_settings['active_provider'] ) || ! is_string( $merged_settings['active_provider'] ) ) {
			$merged_settings['active_provider'] = $current_settings['active_provider'] ?? $default_settings['active_provider'];
		}

		update_option( $this->option_id, $merged_settings );
	}

	/**
	 * Add the submenu content
	 * 
	 * @since   1.7.0
	 * @return void
	 */
	private function submenu_content() {
		$this->settings         = $this->get_settings();
		$this->default_settings = $this->get_default_settings();

		// General
		$current_user_email = get_option( 'admin_email' );

		// Providers
		$providers       = $this->default_settings['providers'];
		$active_provider = $this->settings['active_provider'] ?? $this->default_settings['active_provider'];

		$has_pro_subscription   = wpmastertoolkit_is_pro();
		$providers_available    = array();
		$providers_unavailable  = array();
		$providers_comming_soon = array();

		foreach ( $providers as $provider_key => $provider ) {
			if ( isset( $provider['comming_soon'] ) ) {
				$providers_comming_soon[$provider_key] = $provider;
			} elseif ( $provider['pro'] && ! $has_pro_subscription ) {
				$providers_unavailable[$provider_key] = $provider;
			} else {
				$providers_available[$provider_key] = $provider;
			}
		}
		?>
			<div class="wp-mastertoolkit__section">
				<div class="wp-mastertoolkit__section__desc">
					<?php esc_html_e( "Set custom sender name and email. Optionally use external SMTP service to ensure notification and transactional emails from your site are being delivered to inboxes.", 'wpmastertoolkit'); ?>
				</div>
			</div>

			<div class="wp-mastertoolkit__sections__wrapper">
				<div class="wp-mastertoolkit__section providers">
					<div class="wp-mastertoolkit__section__body">

						<div class="wp-mastertoolkit__section__body__item">
							<div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Providers', 'wpmastertoolkit' ); ?></div>
							<div class="wp-mastertoolkit__section__body__item__content">

								<div class="wp-mastertoolkit__button reset_all">
									<button class="danger" type="submit" name="reset_all_providers" value="1" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to reset all providers data? This action cannot be undone.', 'wpmastertoolkit' ) ); ?>')"><?php esc_html_e( 'Reset all providers', 'wpmastertoolkit' ); ?></button>
								</div>

								<div class="wp-mastertoolkit__providers">
								<?php
								foreach ( $providers_available as $provider_key => $provider ) {
									$this->render_single_provider( $provider_key, $active_provider );
								}
								?>
								</div>
							</div>
						</div>

						<?php if ( ! empty( $providers_unavailable ) ): ?>
						<div class="wp-mastertoolkit__section__body__item">
							<div class="wp-mastertoolkit__section__body__item__title">
								<?php esc_html_e( 'Providers', 'wpmastertoolkit' ); ?>
								<span class="wp-mastertoolkit__section__body__item__title__tag pro"><?php esc_html_e( 'PRO', 'wpmastertoolkit' ); ?></span>
							</div>
							<div class="wp-mastertoolkit__section__body__item__content">
								<div class="wp-mastertoolkit__providers">
								<?php
								foreach ( $providers_unavailable as $provider_key => $provider ) {
									$this->render_single_provider( $provider_key, $active_provider, true );
								}
								?>
								</div>
							</div>
						</div>
						<?php endif; ?>

						<?php if ( ! empty( $providers_comming_soon ) ): ?>
						<div class="wp-mastertoolkit__section__body__item">
							<div class="wp-mastertoolkit__section__body__item__title">
								<?php esc_html_e( 'Providers', 'wpmastertoolkit' ); ?>
								<span class="wp-mastertoolkit__section__body__item__title__tag comming"><?php esc_html_e( 'Coomming Soon', 'wpmastertoolkit' ); ?></span>
							</div>
							<div class="wp-mastertoolkit__section__body__item__content">
								<div class="wp-mastertoolkit__providers">
								<?php
								foreach ( $providers_comming_soon as $provider_key => $provider ) {
									$this->render_single_provider( $provider_key, $active_provider, true );
								}
								?>
								</div>
							</div>
						</div>
						<?php endif; ?>
						
					</div>
				</div>

				<div class="wp-mastertoolkit__section config">
					<?php
					foreach ( $providers_available as $provider_key => $provider ) {
						$class_name = 'WPMastertoolkit_SMTP_Mailer_' . ucfirst( $provider_key );
						if ( method_exists( $class_name, 'render_config' ) ) {
							call_user_func( array( $class_name, 'render_config' ), $active_provider, $this );
						}
					}
					?>
				</div>
			</div>

			<div class="wp-mastertoolkit__section">
				<div class="wp-mastertoolkit__section__body">
					<div class="wp-mastertoolkit__section__body__item">
						<div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Email test', 'wpmastertoolkit' ); ?></div>
						<div class="wp-mastertoolkit__section__body__item__content">
							<div class="description"><?php esc_html_e( 'After saving the settings above, check if everything is configured properly below.', 'wpmastertoolkit' ); ?></div>
							<br>
							<div class="wp-mastertoolkit__input-text">
								<div><input type="email" class="" id="JS-test-input" value="<?php echo esc_attr( $current_user_email ); ?>" placeholder="<?php esc_attr_e( 'Email', 'wpmastertoolkit' ); ?>"></div>
							</div>
							<br>
							<div class="wp-mastertoolkit__button">
								<button type="button" class="flex" id="JS-test-btn">
									<?php echo wp_kses( file_get_contents(WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/message-arrow.svg'), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
									<?php esc_html_e( 'Send Now', 'wpmastertoolkit' ); ?>
									<div class="wp-mastertoolkit__loader" id="JS-test-loader"></div>
								</button>
								<div class="wp-mastertoolkit__msg" id="JS-test-msg"></div>
							</div>
						</div>
					</div>
				</div>
			</div>
		<?php
	}

	/**
	 * Render single provider
	 * 
	 * @since 2.14.0
	 */
	private function render_single_provider( $provider_key, $active_provider, $disabled = false ) {
		?>
		<?php if ( ! $disabled ): ?>
		<label>
			<input type="radio" class="wp-mastertoolkit__providers__input" name="<?php echo esc_attr( $this->option_id . '[active_provider]' ); ?>" value="<?php echo esc_attr( $provider_key ); ?>" <?php checked( $provider_key, $active_provider ); ?>>
			<div class="wp-mastertoolkit__providers__icon">
				<?php echo wp_kses( file_get_contents(WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/check-round.svg'), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
			</div>
		<?php endif; ?>
			<div class="wp-mastertoolkit__providers__provider <?php echo $disabled ? 'disabled' : ''; ?>">
				<div class="wp-mastertoolkit__providers__provider__image">
					<?php //phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage ?>
					<img src="<?php echo esc_url( WPMASTERTOOLKIT_PLUGIN_URL . 'admin/images/smtp-mailer/' . $provider_key . '.webp' ); ?>" alt="<?php echo esc_attr( $provider_key ); ?>">
				</div>
			</div>
		<?php if ( ! $disabled ): ?>
		</label>
		<?php endif; ?>
		<?php
	}
}
