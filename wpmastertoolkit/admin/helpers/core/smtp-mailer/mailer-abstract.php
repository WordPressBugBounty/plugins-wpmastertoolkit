<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

include_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/modules/core/class-smtp-mailer.php';

/**
 * Abstract class for mailer implementations.
 * 
 * Provides base functionality for sending emails through various providers.
 * 
 * @since 2.14.0
 */
abstract class WPMastertoolkit_SMTP_Mailer_Abstract {

	/**
	 * Which response code from HTTP provider is considered to be successful?
	 *
	 * @since 2.14.0
	 *
	 * @var int
	 */
	protected $email_sent_code = 200;

	/**
	 * Options array.
	 *
	 * @since 2.14.0
	 *
	 * @var array
	 */
	protected $options;

	/**
	 * PHPMailer instance.
	 *
	 * @since 2.14.0
	 *
	 * @var object
	 */
	protected $phpmailer;

	/**
	 * Provider name/slug.
	 *
	 * @since 2.14.0
	 *
	 * @var string
	 */
	protected $provider = '';

	/**
	 * URL to make an API request to.
	 *
	 * @since 2.14.0
	 *
	 * @var string
	 */
	protected $url = '';

	/**
	 * Email headers.
	 *
	 * @since 2.14.0
	 *
	 * @var array
	 */
	protected $headers = array();

	/**
	 * Email body parameters.
	 *
	 * @since 2.14.0
	 *
	 * @var array
	 */
	protected $body = array();

	/**
	 * Response from API.
	 *
	 * @since 2.14.0
	 *
	 * @var mixed
	 */
	protected $response = array();

	/**
	 * The error message recorded when email sending failed.
	 *
	 * @since 2.14.0
	 *
	 * @var string
	 */
	protected $error_message = '';

	/**
	 * Should the email sent by this mailer have its "sent status" verified via its API?
	 *
	 * @since 2.14.0
	 *
	 * @var bool
	 */
	protected $verify_sent_status = false;

	/**
	 * SMTP Mailer class instance.
	 *
	 * @since 2.14.0
	 *
	 * @var WPMastertoolkit_SMTP_Mailer
	 */
	protected $class_smtp_mailer = null;

	/**
	 * Current settings.
	 *
	 * @since 2.14.0
	 *
	 * @var array
	 */
	protected $settings = null;

	/**
	 * Default settings.
	 *
	 * @since 2.14.0
	 *
	 * @var array
	 */
	protected $default_settings = null;

	/**
	 * Constructor.
	 * 
	 * @since 2.14.0
	 *
	 * @param object $phpmailer PHPMailer instance.
	 */
	public function __construct( $phpmailer ) {

		$this->class_smtp_mailer = new WPMastertoolkit_SMTP_Mailer();
		$this->settings          = $this->class_smtp_mailer->get_settings();
		$this->default_settings  = $this->class_smtp_mailer->get_default_settings();
		$this->provider          = $this->settings['active_provider'] ?? $this->default_settings['active_provider'];

		$this->process_phpmailer( $phpmailer );
	}

	/**
	 * Re-use the PHPMailer class methods and properties.
	 * 
	 * @since 2.14.0
	 *
	 * @param object $phpmailer PHPMailer instance.
	 */
	public function process_phpmailer( $phpmailer ) {

		// Make sure that we have access to PHPMailer class methods.
		if ( ! is_object( $phpmailer ) || ! method_exists( $phpmailer, 'getCustomHeaders' ) ) {
			return;
		}

		$this->phpmailer = $phpmailer;

		// Set email properties from PHPMailer.
		$this->set_headers( $this->phpmailer->getCustomHeaders() );
		$this->set_from( $this->phpmailer->From, $this->phpmailer->FromName );
		$this->set_recipients(
			array(
				'to'  => $this->phpmailer->getToAddresses(),
				'cc'  => $this->phpmailer->getCcAddresses(),
				'bcc' => $this->phpmailer->getBccAddresses(),
			)
		);
		$this->set_subject( $this->phpmailer->Subject );
		
		// Set content based on content type.
		if ( $this->phpmailer->ContentType === 'text/plain' ) {
			$this->set_content( $this->phpmailer->Body );
		} else {
			$this->set_content(
				array(
					'text' => $this->phpmailer->AltBody,
					'html' => $this->phpmailer->Body,
				)
			);
		}
		
		$this->set_return_path( $this->phpmailer->From );
		$this->set_reply_to( $this->phpmailer->getReplyToAddresses() );

		/*
		 * In some cases we will need to modify the internal structure
		 * of the body content, if attachments are present.
		 * So lets make this call the last one.
		 */
		$this->set_attachments( $this->phpmailer->getAttachments() );
	}

	/**
	 * Set the email headers.
	 *
	 * @since 2.14.0
	 *
	 * @param array $headers List of key=>value pairs.
	 */
	public function set_headers( $headers ) {

		foreach ( $headers as $header ) {
			$name  = isset( $header[0] ) ? $header[0] : false;
			$value = isset( $header[1] ) ? $header[1] : false;

			if ( empty( $name ) || empty( $value ) ) {
				continue;
			}

			$this->set_header( $name, $value );
		}
	}

	/**
	 * Set individual header key=>value pair for the email.
	 *
	 * @since 2.14.0
	 *
	 * @param string $name  Header name.
	 * @param string $value Header value.
	 */
	public function set_header( $name, $value ) {

		$name = sanitize_text_field( $name );
		$this->headers[ $name ] = $this->sanitize_header_value( $name, $value );
	}

	/**
	 * Set email subject.
	 *
	 * @since 2.14.0
	 *
	 * @param string $subject Email subject.
	 */
	public function set_subject( $subject ) {

		$this->set_body_param(
			array(
				'subject' => $subject,
			)
		);
	}

	/**
	 * Set the request params, that goes to the body of the HTTP request.
	 *
	 * @since 2.14.0
	 *
	 * @param array $param Key=>value of what should be sent to a 3rd party API.
	 */
	protected function set_body_param( $param ) {

		$this->body = $this->array_merge_recursive( $this->body, $param );
	}

	/**
	 * Merge recursively, including a proper substitution of values in sub-arrays when keys are the same.
	 * It's more like array_merge() and array_merge_recursive() combined.
	 * 
	 * @since 2.16.0
	 * 
	 * @return array
	 */
	protected function array_merge_recursive() {

		$arrays = func_get_args();

		if ( count( $arrays ) < 2 ) {
			return isset( $arrays[0] ) ? $arrays[0] : [];
		}

		$merged = [];

		while ( $arrays ) {
			$array = array_shift( $arrays );

			if ( ! is_array( $array ) ) {
				return [];
			}

			if ( empty( $array ) ) {
				continue;
			}

			foreach ( $array as $key => $value ) {
				if ( is_string( $key ) ) {
					if (
						is_array( $value ) &&
						array_key_exists( $key, $merged ) &&
						is_array( $merged[ $key ] )
					) {
						$merged[ $key ] = call_user_func( __METHOD__, $merged[ $key ], $value );
					} else {
						$merged[ $key ] = $value;
					}
				} else {
					$merged[] = $value;
				}
			}
		}

		return $merged;
	}

	/**
	 * Get the email body.
	 *
	 * @since 2.14.0
	 *
	 * @return string|array
	 */
	public function get_body() {

		return apply_filters( 'wpmastertoolkit_smtp_mailer_get_body', $this->body, $this->provider );
	}

	/**
	 * Get the email headers.
	 *
	 * @since 2.14.0
	 *
	 * @return array
	 */
	public function get_headers() {

		return apply_filters( 'wpmastertoolkit_smtp_mailer_get_headers', $this->headers, $this->provider );
	}

	/**
	 * Send the email.
	 *
	 * @since 2.14.0
	 */
	public function send() {

		$timeout = (int) ini_get( 'max_execution_time' );

		$params = array_merge(
			$this->get_default_params(),
			array(
				'headers' => $this->get_headers(),
				'body'    => $this->get_body(),
				'timeout' => $timeout ? $timeout : 30,
			)
		);

		$response = wp_safe_remote_post( $this->url, $params );

		$this->process_response( $response );
	}

	/**
	 * We might need to do something after the email was sent to the API.
	 * In this method we preprocess the response from the API.
	 *
	 * @since 2.14.0
	 *
	 * @param mixed $response Response array.
	 */
	protected function process_response( $response ) {

		if ( is_wp_error( $response ) ) {
			// Save the error text.
			foreach ( $response->errors as $error_code => $error_messages ) {
				foreach ( (array) $error_messages as $error_message ) {
					$this->error_message .= sprintf( '[%s] %s', $error_code, $error_message ) . "\n";
				}
			}

			return;
		}

		if ( isset( $response['body'] ) && $this->is_json( $response['body'] ) ) {
			$response['body'] = json_decode( $response['body'] );
		}

		$this->response = $response;
	}

	/**
	 * Get the default params, required for wp_safe_remote_post().
	 *
	 * @since 2.14.0
	 *
	 * @return array
	 */
	protected function get_default_params() {

		return array(
			'timeout'     => 15,
			'httpversion' => '1.1',
			'blocking'    => true,
		);
	}

	/**
	 * Whether the email is sent or not.
	 * We basically check the response code from a request to provider.
	 *
	 * @since 2.14.0
	 *
	 * @return bool
	 */
	public function is_email_sent() {

		$is_sent = false;

		if ( wp_remote_retrieve_response_code( $this->response ) === $this->email_sent_code ) {
			$is_sent = true;
		}

		return $is_sent;
	}

	/**
	 * The error message when email sending failed.
	 *
	 * @since 2.14.0
	 *
	 * @return string
	 */
	public function get_response_error() {

		return ! empty( $this->error_message ) ? $this->error_message : '';
	}

	/**
	 * Whether the mailer supports the current PHP version or not.
	 *
	 * @since 2.14.0
	 *
	 * @return bool
	 */
	public function is_php_compatible() {

		// Default minimum PHP version requirement.
		$required_php_version = '5.6';

		return version_compare( phpversion(), $required_php_version, '>=' );
	}

	/**
	 * Get debug information.
	 *
	 * @since 2.14.0
	 *
	 * @return string
	 */
	public function get_debug_info() {

		$debug_text = array();

		if ( ! empty( $this->phpmailer->ErrorInfo ) ) {
			$debug_text[] = '<strong>ErrorInfo:</strong> ' . esc_html( $this->phpmailer->ErrorInfo );
		}

		$debug_text[] = '<br><strong>Server:</strong>';
		$debug_text[] = '<strong>PHP Version:</strong> ' . phpversion();
		$debug_text[] = '<strong>OpenSSL:</strong> ' . ( extension_loaded( 'openssl' ) && defined( 'OPENSSL_VERSION_TEXT' ) ? OPENSSL_VERSION_TEXT : 'No' );
		
		if ( function_exists( 'apache_get_modules' ) ) {
			$modules     = apache_get_modules();
			$debug_text[] = '<strong>Apache.mod_security:</strong> ' . ( in_array( 'mod_security', $modules, true ) || in_array( 'mod_security2', $modules, true ) ? 'Yes' : 'No' );
		}

		return implode( '<br>', $debug_text );
	}

	/**
	 * Get the name/slug of the current provider.
	 *
	 * @since 2.14.0
	 *
	 * @return string
	 */
	public function get_provider_name() {

		return $this->provider;
	}

	/**
	 * Get PHPMailer attachment file content.
	 *
	 * @since 2.14.0
	 *
	 * @param array $attachment PHPMailer attachment.
	 *
	 * @return string|false
	 */
	public function get_attachment_file_content( $attachment ) {

		$file = false;

		try {
			if ( isset( $attachment[5] ) && $attachment[5] === true ) {  // Whether there is string attachment.
				$file = $attachment[0];
			} elseif ( is_file( $attachment[0] ) && is_readable( $attachment[0] ) ) {
				$file = file_get_contents( $attachment[0] );
			}
		} catch ( \Exception $e ) {
			// Return default false value.
		}

		return $file;
	}

	/**
	 * Get PHPMailer attachment file size.
	 *
	 * @since 2.14.0
	 *
	 * @param array $attachment PHPMailer attachment.
	 *
	 * @return int|false
	 */
	public function get_attachment_file_size( $attachment ) {

		$size = false;

		if ( isset( $attachment[5] ) && $attachment[5] === true ) {  // Whether there is string attachment.
			$size = strlen( $attachment[0] );
		} elseif ( is_file( $attachment[0] ) && is_readable( $attachment[0] ) ) {
			$size = filesize( $attachment[0] );
		}

		return $size;
	}

	/**
	 * Get PHPMailer attachment file name.
	 *
	 * @since 2.14.0
	 *
	 * @param array $attachment PHPMailer attachment.
	 *
	 * @return string
	 */
	public function get_attachment_file_name( $attachment ) {

		$filetype = isset( $attachment[4] ) ? str_replace( ';', '', trim( $attachment[4] ) ) : '';

		return ! empty( $attachment[2] ) ? trim( $attachment[2] ) : 'file-' . wp_hash( microtime() ) . '.' . $filetype;
	}

	/**
	 * Perform remote request with merged default params.
	 *
	 * @since 2.14.0
	 *
	 * @param string $url    Request url.
	 * @param array  $params Request params.
	 *
	 * @return array
	 */
	public function remote_request( $url, $params ) {

		if ( ! isset( $params['method'] ) ) {
			$params['method'] = 'POST';
		}

		$params = array_merge_recursive( $this->get_default_params(), $params );

		return wp_safe_remote_request( $url, $params );
	}

	/**
	 * Sanitize email header values.
	 *
	 * @since 2.14.0
	 *
	 * @param string $name  Name of the header.
	 * @param string $value Value of the header.
	 *
	 * @return string
	 */
	public function sanitize_header_value( $name, $value ) {

		// Headers that should not be sanitized.
		if (
			in_array(
				strtolower( $name ),
				array(
					'cc',
					'bcc',
					'reply-to',
					'message-id',
					'list-unsubscribe',
					'references',
					'in-reply-to'
				),
				true
			)
		) {
			return $value;
		}

		return sanitize_text_field( $value );
	}

	/**
	 * Check if a string is JSON.
	 *
	 * @since 2.14.0
	 *
	 * @param string $string String to check.
	 *
	 * @return bool
	 */
	protected function is_json( $string ) {

		if ( ! is_string( $string ) ) {
			return false;
		}

		json_decode( $string );

		return json_last_error() === JSON_ERROR_NONE;
	}

	/**
	 * Abstract methods that must be implemented by child classes.
	 */

	/**
	 * Set the From information for an email.
	 *
	 * @since 2.14.0
	 *
	 * @param string $email From email address.
	 * @param string $name  From name.
	 */
	abstract public function set_from( $email, $name );

	/**
	 * Set email recipients.
	 *
	 * @since 2.14.0
	 *
	 * @param array $recipients Array of recipients (to, cc, bcc).
	 */
	abstract public function set_recipients( $recipients );

	/**
	 * Set email content.
	 *
	 * @since 2.14.0
	 *
	 * @param string|array $content Email content (text/html).
	 */
	abstract public function set_content( $content );

	/**
	 * Set the Reply To email address.
	 *
	 * @since 2.14.0
	 *
	 * @param array $reply_to Reply to addresses.
	 */
	abstract public function set_reply_to( $reply_to );

	/**
	 * Set the return path email address.
	 *
	 * @since 2.14.0
	 *
	 * @param string $email Return path email address.
	 */
	abstract public function set_return_path( $email );

	/**
	 * Set email attachments.
	 *
	 * @since 2.14.0
	 *
	 * @param array $attachments Array of attachments.
	 */
	abstract public function set_attachments( $attachments );
}
