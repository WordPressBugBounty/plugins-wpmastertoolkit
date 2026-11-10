<?php
/**
 * Internal activity log provider contract.
 *
 * @package WPMasterToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register a provider once in the module's explicit provider map.
 *
 * Constructors must be side-effect-free. Groups declare boolean pro/default_enabled
 * policies, integer order/event_order positions, and events keyed by stable IDs.
 * Optional event_positions reserve gaps for providers sharing a UI category.
 * Structural calls use untranslated labels; translations are resolved for display.
 * Historical events have an owner but cannot be recorded or selected in settings.
 */
interface WPMastertoolkit_Activity_Log_Provider {

	/** Register observer hooks after the shared writer is attached. */
	public function register_hooks();

	/**
	 * Get active groups and event labels.
	 *
	 * @param bool $translate Whether to translate presentation labels.
	 * @return array Groups keyed by stable category ID.
	 */
	public static function event_groups( $translate = false );

	/**
	 * Get retired event labels without making those events recordable.
	 *
	 * @param bool $translate Whether to translate presentation labels.
	 * @return array Labels keyed by retired event ID.
	 */
	public static function historical_labels( $translate = true );

	/**
	 * Get domain-specific detail labels.
	 *
	 * @param string $event Event ID.
	 * @return array Labels keyed by detail field.
	 */
	public static function detail_labels( $event );

	/**
	 * Allowlist and bound event details, including historical reads.
	 *
	 * @param string $event Event ID.
	 * @param mixed  $details Supplied details.
	 * @return array Safe details.
	 */
	public static function normalize_details( $event, $details );

	/**
	 * Migrate raw saved preferences before merging defaults.
	 *
	 * @param array $preferences Saved preferences.
	 * @return array Preferences preserving explicit granular choices.
	 */
	public static function migrate_preferences( $preferences );
}
