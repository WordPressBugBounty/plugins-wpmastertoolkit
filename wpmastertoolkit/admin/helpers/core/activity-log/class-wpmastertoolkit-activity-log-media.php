<?php
/**
 * Media observers for the activity log.
 *
 * @package WPMasterToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Observe media activity through the shared writer. */
class WPMastertoolkit_Activity_Log_Media implements WPMastertoolkit_Activity_Log_Provider {

	/** Register the existing observer hooks. */
	public function register_hooks() {
		add_action( 'add_attachment', array( $this, 'log_media_created' ) );
		add_action( 'edit_attachment', array( $this, 'log_media_updated' ) );
		add_action( 'delete_attachment', array( $this, 'log_media_deleted' ) );
	}

	/**
	 * Describe media events without depending on presentation translations.
	 *
	 * @param bool $translate Whether to translate labels.
	 * @return array Event groups.
	 */
	public static function event_groups( $translate = false ) {
		return array(
			'content' => array(
				'label'           => $translate ? __( 'Content', 'wpmastertoolkit' ) : '',
				'pro'             => false,
				'default_enabled' => true,
				'order'           => 10,
				'event_order'     => 6,
				'events'          => array(
					'media.created' => $translate ? __( 'Media uploaded', 'wpmastertoolkit' ) : '',
					'media.updated' => $translate ? __( 'Media updated', 'wpmastertoolkit' ) : '',
					'media.deleted' => $translate ? __( 'Media deleted', 'wpmastertoolkit' ) : '',
				),
			),
		);
	}

	/**
	 * Get historical labels.
	 *
	 * @param bool $translate Whether to translate labels.
	 * @return array Labels.
	 */
	public static function historical_labels( $translate = true ) {
		return array();
	}

	/**
	 * Get domain detail labels.
	 *
	 * @param string $event Event ID.
	 * @return array Labels.
	 */
	public static function detail_labels( $event ) {
		return array();
	}

	/**
	 * Preserve existing detail-free events.
	 *
	 * @param string $event Event ID.
	 * @param mixed  $details Supplied details.
	 * @return array Safe details.
	 */
	public static function normalize_details( $event, $details ) {
		return array();
	}

	/**
	 * Preserve preferences unchanged.
	 *
	 * @param array $preferences Saved preferences.
	 * @return array Preferences.
	 */
	public static function migrate_preferences( $preferences ) {
		return $preferences;
	}

	/**
	 * Log a media upload.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @since 2.26.0
	 */
	public function log_media_created( $attachment_id ) {
		$this->emit( 'media.created', 'attachment', $attachment_id );
	}

	/**
	 * Log a media edit.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @since 2.26.0
	 */
	public function log_media_updated( $attachment_id ) {
		$this->emit( 'media.updated', 'attachment', $attachment_id );
	}

	/**
	 * Log an attachment deletion.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @since 2.26.0
	 */
	public function log_media_deleted( $attachment_id ) {
		$this->emit( 'media.deleted', 'attachment', $attachment_id );
	}

	/**
	 * Send a user-initiated event to the normalized writer.
	 *
	 * @param string     $event     Event identifier.
	 * @param string     $object_type Object category.
	 * @param int|string $object_id Object identifier.
	 * @param array      $details   Optional event details.
	 * @since 2.26.0
	 */
	private function emit( $event, $object_type, $object_id, $details = array() ) {
		do_action( 'wpmastertoolkit/activity_log/event', $event, get_current_user_id(), $object_type, $object_id, $details ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Preserve the existing public event action.
	}
}
