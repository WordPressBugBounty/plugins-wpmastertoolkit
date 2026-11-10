<?php
/**
 * Internal activity log provider registry.
 *
 * @package WPMasterToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keep collection policy separate from translated presentation and runtime gates.
 */
class WPMastertoolkit_Activity_Log_Registry {

	/**
	 * Registered provider instances.
	 *
	 * @var array
	 */
	private $providers = array();

	/**
	 * Structural groups and event ordering.
	 *
	 * @var array
	 */
	private $groups = array();

	/**
	 * Exact active and historical event ownership.
	 *
	 * @var array
	 */
	private $events = array();

	/**
	 * Whether hooks have been registered and definitions sealed.
	 *
	 * @var bool
	 */
	private $started = false;

	/**
	 * Validate a contribution fully before committing any definitions.
	 *
	 * @param string                                $id Provider ID.
	 * @param WPMastertoolkit_Activity_Log_Provider $provider Provider instance.
	 * @return true|WP_Error Registration result.
	 */
	public function register( $id, WPMastertoolkit_Activity_Log_Provider $provider ) {
		if ( $this->started || ! $this->valid_id( $id ) || isset( $this->providers[ $id ] ) ) {
			return new WP_Error( 'activity_log_provider_conflict', 'Invalid, duplicate, or late activity log provider.' );
		}
		$groups      = $this->groups;
		$events      = $this->events;
		$definitions = $provider::event_groups();
		$historical  = $provider::historical_labels( false );
		if ( ! is_array( $definitions ) || ! is_array( $historical ) ) {
			return new WP_Error( 'activity_log_invalid_definitions', 'Invalid activity log definitions.' );
		}
		foreach ( $definitions as $group_id => $group ) {
			if ( ! $this->valid_id( $group_id ) || ! is_array( $group ) || ! isset( $group['pro'], $group['default_enabled'], $group['order'], $group['event_order'], $group['events'], $group['label'] ) || ! is_bool( $group['pro'] ) || ! is_bool( $group['default_enabled'] ) || ! is_int( $group['order'] ) || ! is_int( $group['event_order'] ) || ! is_string( $group['label'] ) || ! is_array( $group['events'] ) ) {
				return new WP_Error( 'activity_log_invalid_group', 'Invalid activity log group.' );
			}
			if ( isset( $groups[ $group_id ] ) ) {
				foreach ( array( 'pro', 'default_enabled', 'order' ) as $policy ) {
					if ( $groups[ $group_id ][ $policy ] !== $group[ $policy ] ) {
						return new WP_Error( 'activity_log_group_conflict', 'Conflicting activity log group policy.' );
					}
				}
			} else {
				$groups[ $group_id ]             = $group;
				$groups[ $group_id ]['provider'] = $id;
				$groups[ $group_id ]['events']   = array();
			}
			$position  = $group['event_order'];
			$positions = $group['event_positions'] ?? array();
			if ( ! is_array( $positions ) || array_diff_key( $positions, $group['events'] ) ) {
				return new WP_Error( 'activity_log_invalid_order', 'Invalid activity log event ordering.' );
			}
			foreach ( $group['events'] as $event => $label ) {
				$event_position = $positions[ $event ] ?? $position;
				if ( ! $this->valid_id( $event ) || ! is_string( $label ) || isset( $events[ $event ] ) || ! is_int( $event_position ) || in_array( $event_position, $groups[ $group_id ]['events'], true ) ) {
					return new WP_Error( 'activity_log_event_conflict', 'Invalid or duplicate activity log event.' );
				}
				$events[ $event ]                        = array(
					'provider'   => $id,
					'group'      => $group_id,
					'pro'        => $group['pro'],
					'recordable' => true,
				);
				$groups[ $group_id ]['events'][ $event ] = $event_position;
				++$position;
			}
		}
		foreach ( $historical as $event => $label ) {
			if ( ! $this->valid_id( $event ) || ! is_string( $label ) || isset( $events[ $event ] ) ) {
				return new WP_Error( 'activity_log_event_conflict', 'Invalid or duplicate historical activity log event.' );
			}
			$events[ $event ] = array(
				'provider'   => $id,
				'recordable' => false,
			);
		}
		uasort(
			$groups,
			static function ( $first, $second ) {
				return $first['order'] <=> $second['order'];
			}
		);
		foreach ( $groups as &$group ) {
			asort( $group['events'], SORT_NUMERIC );
		}
		unset( $group );
		$this->providers[ $id ] = $provider;
		$this->groups           = $groups;
		$this->events           = $events;
		return true;
	}

	/**
	 * Validate stable IDs using the existing writer's event syntax.
	 *
	 * @param mixed $id Candidate ID.
	 * @return bool Whether the ID is valid.
	 */
	private function valid_id( $id ) {
		return is_string( $id ) && 1 === preg_match( '/^[a-z][a-z0-9_.]{0,63}$/D', $id );
	}

	/** Register hooks once and prohibit subsequent definition changes. */
	public function register_hooks() {
		if ( $this->started ) {
			return;
		}
		$this->started = true;
		foreach ( $this->providers as $provider ) {
			$provider->register_hooks();
		}
	}

	/**
	 * Find exact ownership without inferring it from a prefix.
	 *
	 * @param string $event Event ID.
	 * @return array|null Definition, or null for unknown events.
	 */
	public function event( $event ) {
		return $this->events[ $event ] ?? null;
	}

	/**
	 * Build labels for the current locale, not for the registry's bootstrap locale.
	 *
	 * @return array Active and historical labels.
	 */
	public function labels() {
		$labels = array();
		foreach ( $this->providers as $provider ) {
			foreach ( $provider::event_groups( true ) as $group ) {
				$labels += $group['events'];
			}
			$labels += $provider::historical_labels();
		}
		return $labels;
	}

	/**
	 * Assemble ordered categories, optionally translating for settings display.
	 *
	 * @param bool $translate Whether to translate labels.
	 * @return array Groups with labels keyed by active event ID.
	 */
	public function groups( $translate = false ) {
		$groups       = $this->groups;
		$labels       = $translate ? $this->labels() : array();
		$group_labels = array();
		foreach ( $groups as $group_id => &$group ) {
			if ( $translate ) {
				$owner = $group['provider'];
				if ( ! isset( $group_labels[ $owner ] ) ) {
					$group_labels[ $owner ] = $this->providers[ $owner ]::event_groups( true );
				}
				$group['label'] = $group_labels[ $owner ][ $group_id ]['label'];
			}
			foreach ( $group['events'] as $event => &$position ) {
				$position = $labels[ $event ] ?? '';
			}
			unset( $position );
		}
		unset( $group );
		return $groups;
	}

	/**
	 * Get active defaults from declared category policy.
	 *
	 * @return array Defaults keyed by active event ID.
	 */
	public function defaults() {
		$defaults = array();
		foreach ( $this->groups as $group ) {
			$defaults += array_fill_keys( array_keys( $group['events'] ), $group['default_enabled'] ? '1' : '0' );
		}
		return $defaults;
	}

	/**
	 * Run migrations in explicit registration order.
	 *
	 * @param array $preferences Raw preferences.
	 * @return array Migrated preferences.
	 */
	public function migrate_preferences( $preferences ) {
		foreach ( $this->providers as $provider ) {
			$preferences = $provider::migrate_preferences( $preferences );
		}
		return $preferences;
	}

	/**
	 * Normalize active or historical details without collection gates.
	 *
	 * @param string $event Event ID.
	 * @param mixed  $details Supplied details.
	 * @return array Allowlisted details.
	 */
	public function normalize_details( $event, $details ) {
		$definition = $this->event( $event );
		if ( ! $definition ) {
			return array();
		}
		return $this->providers[ $definition['provider'] ]::normalize_details( $event, $details );
	}

	/**
	 * Resolve field labels through the exact owner with a shared fallback.
	 *
	 * @param string $event Event ID.
	 * @param string $field Field key.
	 * @return string Translated label or raw field key.
	 */
	public function detail_label( $event, $field ) {
		$definition = $this->event( $event );
		$labels     = $definition ? $this->providers[ $definition['provider'] ]::detail_labels( $event ) : array();
		$labels    += array(
			'before'       => __( 'Before', 'wpmastertoolkit' ),
			'after'        => __( 'After', 'wpmastertoolkit' ),
			'before_total' => __( 'Previous term count', 'wpmastertoolkit' ),
			'after_total'  => __( 'New term count', 'wpmastertoolkit' ),
			'title'        => __( 'Title or name', 'wpmastertoolkit' ),
			'taxonomy'     => __( 'Taxonomy', 'wpmastertoolkit' ),
			'unit'         => __( 'Unit', 'wpmastertoolkit' ),
			'source'       => __( 'Source', 'wpmastertoolkit' ),
		);
		return $labels[ $field ] ?? $field;
	}
}
