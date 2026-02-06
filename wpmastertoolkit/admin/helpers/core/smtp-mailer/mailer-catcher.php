<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

include_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/modules/core/class-smtp-mailer.php';

/**
 * Class replaces the \PHPMailer\PHPMailer\PHPMailer.
 * 
 * @since 2.14.0
 */

class WPMastertoolkit_SMTP_Mailer_Mailer_Catcher extends PHPMailer\PHPMailer\PHPMailer {

	private $debug_output_buffer        = array();
	private $debug_event_id             = false;
	private $is_test_email              = false;
	private $is_setup_wizard_test_email = false;
	protected $latest_error             = '';
	private $class_smtp_mailer          = null;
	private $settings                   = null;
	private $default_settings           = null;

	/**
	 * Modify the default send() behaviour.
	 * 
	 * @since 2.14.0
	 */
	public function send() {

		$this->class_smtp_mailer = new WPMastertoolkit_SMTP_Mailer();
		$this->settings          = $this->class_smtp_mailer->get_settings();
		$this->default_settings  = $this->class_smtp_mailer->get_default_settings();
		$active_provider         = $this->settings['active_provider'] ?? $this->default_settings['active_provider'];

		// Reset email related variables.
		$this->debug_event_id             = false;
		$this->is_test_email              = false;
		$this->is_setup_wizard_test_email = false;
		$this->latest_error               = '';
		
		if ( 'php' === $active_provider || 'other' === $active_provider ) {
			return $this->smtp_send();
		} else {
			return $this->api_send();
		}
	}

	/**
	 * Send email via SMTP.
	 * 
	 * @since 2.14.0
	 */
	private function smtp_send() {

		try {

			// Prepare all the headers.
			if ( ! $this->preSend() ) {
				$this->throw_exception( $this->ErrorInfo );
			}

			if ( ! $this->postSend() ) {
				$this->throw_exception( $this->ErrorInfo );
			}
			
			return true;
		} catch ( Exception $e ) {

			$this->mailHeader = '';

			// We need this to append SMTP error to the `PHPMailer::ErrorInfo` property.
			$this->setError( $e->getMessage() );

			if ( $this->exceptions ) {
				throw $e;
			}

			return false;
		} finally {

			// Clear debug output buffer.
			$this->debug_output_buffer = [];
		}
	}

	/**
	 * Send email via API.
	 * 
	 * @since 2.14.0
	 */
	private function api_send() {
		$this->class_smtp_mailer->require_providers_catchers();

		$active_provider = $this->settings['active_provider'] ?? $this->default_settings['active_provider'];

		try {
			// We need this so that the \PHPMailer class will correctly prepare all the headers.
			$this->Mailer = 'mail';

			// Prepare everything (including the message) for sending.
			if ( ! $this->preSend() ) {
				$this->throw_exception( $this->ErrorInfo );
			}

			// Get provider class
			$provider   = '';
			$class_name = 'WPMastertoolkit_SMTP_Mailer_Catcher_' . ucfirst( $active_provider );
			if ( class_exists( $class_name ) ) {
				$provider = new $class_name( $this );
			}

			if ( ! $provider ) {
				$this->throw_exception( esc_html__( 'The selected provider not found.', 'wpmastertoolkit' ) );
			}

			/*
			 * Send the actual email.
			 * We reuse everything, that was preprocessed for usage in \PHPMailer.
			 */
			$provider->send();

			$is_sent = $provider->is_email_sent();

			if ( $is_sent !== true ) {
				$this->throw_exception( $provider->get_response_error() );
			}
			
			return true;
		} catch ( Exception $e ) {
			// Add provider to the beginning and save to display later.
			$message = 'Provider: ' . $active_provider . "\r\n";

			$error_message        = $message . $e->getMessage();
			$this->latest_error   = $error_message;

			if ( $this->exceptions ) {
				throw $e;
			}

			return false;
		}
	}

	/**
	 * Throw PHPMailer exception.
	 *
	 * @since 2.14.0
	 */
	protected function throw_exception( $error ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		throw new Exception( $error );
	}
}
