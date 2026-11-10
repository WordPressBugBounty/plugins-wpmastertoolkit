<?php
/**
 * Users observers for the activity log.
 *
 * @package WPMasterToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Observe users activity through the shared writer. */
class WPMastertoolkit_Activity_Log_Users implements WPMastertoolkit_Activity_Log_Provider {

	/**
	 * Profile snapshots scoped to blog and user ID.
	 *
	 * @var array
	 */
	private $profiles = array();

	/**
	 * Pending effective role changes scoped to blog and user ID.
	 *
	 * @var array
	 */
	private $roles = array();

	/**
	 * Safe context for accounts pending successful deletion.
	 *
	 * @var array
	 */
	private $deleting = array();

	/**
	 * Application-password names captured before metadata writes.
	 *
	 * @var array
	 */
	private $applications = array();

	/**
	 * Previously emitted password fingerprints for reset-hook deduplication.
	 *
	 * @var array
	 */
	private $passwords = array();

	/**
	 * Super Admin state before privilege changes.
	 *
	 * @var array
	 */
	private $super_admins = array();

	/** Register the existing observer hooks. */
	public function register_hooks() {
		add_action( 'wp_login', array( $this, 'log_login' ), 10, 2 );
		add_action( 'wp_logout', array( $this, 'log_logout' ) );
		add_action( 'user_register', array( $this, 'log_user_created' ) );
		add_action( 'delete_user', array( $this, 'log_user_deleted' ) );
		add_action( 'wpmu_delete_user', array( $this, 'log_user_deleted' ) );
		add_action( 'deleted_user', array( $this, 'user_deleted' ), 10, 3 );
		add_filter( 'wp_pre_insert_user_data', array( $this, 'capture_profile' ), PHP_INT_MAX, 3 );
		add_action( 'profile_update', array( $this, 'log_user_updated' ) );
		add_filter( 'update_user_metadata', array( $this, 'capture_role_metadata' ), PHP_INT_MAX, 3 );
		add_action( 'add_user_role', array( $this, 'role_added' ), 10, 2 );
		add_action( 'remove_user_role', array( $this, 'role_removed' ), 10, 2 );
		add_action( 'wp_update_user', array( $this, 'flush_user_roles' ) );
		add_action( 'shutdown', array( $this, 'flush_roles' ) );
		add_action( 'wp_set_password', array( $this, 'password_set' ), 10, 3 );
		add_action( 'after_password_reset', array( $this, 'password_reset' ) );
		add_action( 'retrieve_password', array( $this, 'reset_requested' ) );
		add_action( 'wp_create_application_password', array( $this, 'application_created' ), 10, 2 );
		add_action( 'wp_update_application_password', array( $this, 'application_updated' ), 10, 2 );
		add_action( 'wp_delete_application_password', array( $this, 'application_deleted' ), 10, 2 );
		add_filter( 'update_user_metadata', array( $this, 'capture_applications' ), PHP_INT_MAX, 3 );
		add_action( 'grant_super_admin', array( $this, 'capture_super_admin' ) );
		add_action( 'revoke_super_admin', array( $this, 'capture_super_admin' ) );
		add_action( 'granted_super_admin', array( $this, 'super_admin_granted' ) );
		add_action( 'revoked_super_admin', array( $this, 'super_admin_revoked' ) );
		add_action( 'edit_user_profile', array( $this, 'profile_viewed' ) );
		add_action( 'admin_page_access_denied', array( $this, 'access_denied' ) );
	}

	/**
	 * Describe user events without depending on presentation translations.
	 *
	 * @param bool $translate Whether to translate labels.
	 * @return array Event groups.
	 */
	public static function event_groups( $translate = false ) {
		return array(
			'users'       => array(
				'label'           => $translate ? __( 'Users', 'wpmastertoolkit' ) : '',
				'pro'             => false,
				'default_enabled' => true,
				'order'           => 0,
				'event_order'     => 0,
				'events'          => array(
					'user.login'        => $translate ? __( 'User logged in', 'wpmastertoolkit' ) : '',
					'user.logout'       => $translate ? __( 'User logged out', 'wpmastertoolkit' ) : '',
					'user.created'      => $translate ? __( 'User created', 'wpmastertoolkit' ) : '',
					'user.deleted'      => $translate ? __( 'User deleted', 'wpmastertoolkit' ) : '',
					'user.role_changed' => $translate ? __( 'User role changed', 'wpmastertoolkit' ) : '',
				) + self::change_labels( $translate ),
			),
			'user_access' => array(
				'label'           => $translate ? __( 'User access', 'wpmastertoolkit' ) : '',
				'pro'             => false,
				'default_enabled' => false,
				'order'           => 5,
				'event_order'     => 0,
				'events'          => array(
					'user.profile_viewed' => $translate ? __( 'Another user profile viewed', 'wpmastertoolkit' ) : '',
					'user.access_denied'  => $translate ? __( 'Administration access denied', 'wpmastertoolkit' ) : '',
				),
			),
		);
	}

	/**
	 * Declare independently selectable profile and account-security changes.
	 *
	 * @param bool $translate Whether to translate labels.
	 * @return array Event labels.
	 */
	private static function change_labels( $translate = false ) {
		return array(
			'user.registered'                   => $translate ? __( 'User registered', 'wpmastertoolkit' ) : '',
			'user.password_changed'             => $translate ? __( 'User password changed', 'wpmastertoolkit' ) : '',
			'user.email_changed'                => $translate ? __( 'User email address changed', 'wpmastertoolkit' ) : '',
			'user.display_name_changed'         => $translate ? __( 'User display name changed', 'wpmastertoolkit' ) : '',
			'user.website_changed'              => $translate ? __( 'User website changed', 'wpmastertoolkit' ) : '',
			'user.profile_changed'              => $translate ? __( 'User profile field changed', 'wpmastertoolkit' ) : '',
			'user.super_admin_granted'          => $translate ? __( 'Super Admin privileges granted', 'wpmastertoolkit' ) : '',
			'user.super_admin_revoked'          => $translate ? __( 'Super Admin privileges revoked', 'wpmastertoolkit' ) : '',
			'user.application_password_created' => $translate ? __( 'Application password created', 'wpmastertoolkit' ) : '',
			'user.application_password_renamed' => $translate ? __( 'Application password renamed', 'wpmastertoolkit' ) : '',
			'user.application_password_revoked' => $translate ? __( 'Application password revoked', 'wpmastertoolkit' ) : '',
			'user.password_reset_requested'     => $translate ? __( 'Password reset requested', 'wpmastertoolkit' ) : '',
		);
	}

	/**
	 * Get historical labels.
	 *
	 * @param bool $translate Whether to translate labels.
	 * @return array Labels.
	 */
	public static function historical_labels( $translate = true ) {
		return array( 'user.updated' => $translate ? __( 'User profile updated', 'wpmastertoolkit' ) : '' );
	}

	/**
	 * Get domain detail labels.
	 *
	 * @param string $event Event ID.
	 * @return array Labels.
	 */
	public static function detail_labels( $event ) {
		return array(
			'username'         => __( 'Username', 'wpmastertoolkit' ),
			'display_name'     => __( 'Display name', 'wpmastertoolkit' ),
			'roles'            => __( 'User roles', 'wpmastertoolkit' ),
			'added_roles'      => __( 'Added roles', 'wpmastertoolkit' ),
			'removed_roles'    => __( 'Removed roles', 'wpmastertoolkit' ),
			'target_relation'  => __( 'Affected account', 'wpmastertoolkit' ),
			'application_id'   => __( 'Application password ID', 'wpmastertoolkit' ),
			'application_name' => __( 'Application password name', 'wpmastertoolkit' ),
			'reassigned_to'    => __( 'Content reassigned to user ID', 'wpmastertoolkit' ),
			'admin_page'       => __( 'Administration page', 'wpmastertoolkit' ),
		);
	}

	/**
	 * Allow bounded user context without exposing credentials or contact details.
	 *
	 * @param string $event Event ID.
	 * @param mixed  $details Supplied details.
	 * @return array Safe details.
	 */
	public static function normalize_details( $event, $details ) {
		$groups = self::event_groups();
		$events = $groups['users']['events'] + $groups['user_access']['events'];
		if ( ! isset( $events[ $event ] ) || ! is_array( $details ) ) {
			return array();
		}
		$normalized = array();
		foreach ( array( 'username', 'display_name', 'application_id', 'application_name', 'admin_page' ) as $field ) {
			if ( isset( $details[ $field ] ) && is_string( $details[ $field ] ) ) {
				$normalized[ $field ] = self::bounded_text( $details[ $field ] );
			}
		}
		foreach ( array( 'roles', 'added_roles', 'removed_roles' ) as $field ) {
			if ( isset( $details[ $field ] ) && is_array( $details[ $field ] ) ) {
				$normalized[ $field ] = self::safe_roles( $details[ $field ] );
			}
		}
		if ( isset( $details['reassigned_to'] ) && is_scalar( $details['reassigned_to'] ) && ctype_digit( (string) $details['reassigned_to'] ) ) {
			$normalized['reassigned_to'] = absint( $details['reassigned_to'] );
		}
		if ( isset( $details['target_relation'] ) && in_array( $details['target_relation'], array( 'self', 'other', 'system' ), true ) ) {
			$normalized['target_relation'] = $details['target_relation'];
		}
		if ( isset( $details['source'] ) && in_array( $details['source'], array( 'user', 'system', 'cli' ), true ) ) {
			$normalized['source'] = $details['source'];
		}
		$profile_field = $details['field'] ?? '';
		if ( 'user.profile_changed' === $event ) {
			if ( ! is_string( $profile_field ) || ! in_array( $profile_field, self::profile_fields(), true ) ) {
				return $normalized;
			}
			$normalized['field'] = $profile_field;
		}
		$redacted = in_array( $event, array( 'user.password_changed', 'user.email_changed', 'user.website_changed' ), true );
		$text     = in_array( $event, array( 'user.profile_changed', 'user.display_name_changed', 'user.application_password_renamed' ), true );
		foreach ( array( 'before', 'after' ) as $field ) {
			$value = $details[ $field ] ?? null;
			if ( $redacted ) {
				$normalized[ $field ] = 'redacted';
			} elseif ( 'user.role_changed' === $event && is_array( $value ) ) {
				$normalized[ $field ] = self::safe_roles( $value );
			} elseif ( 'user.profile_changed' === $event && 'description' === $profile_field && is_scalar( $value ) && ctype_digit( (string) $value ) ) {
				$normalized[ $field ] = absint( $value );
			} elseif ( $text && 'description' !== $profile_field && is_string( $value ) ) {
				$normalized[ $field ] = self::bounded_text( $value );
			}
		}
		if ( 'user.profile_changed' === $event && 'description' === $profile_field ) {
			$normalized['unit'] = 'bytes';
		}
		return $normalized;
	}

	/**
	 * Inherit old profile and creation choices without overriding new choices.
	 *
	 * @param array $preferences Saved preferences.
	 * @return array Preferences.
	 */
	public static function migrate_preferences( $preferences ) {
		foreach ( array( 'user.password_changed', 'user.email_changed', 'user.display_name_changed', 'user.website_changed', 'user.profile_changed' ) as $event ) {
			if ( ! isset( $preferences[ $event ] ) && isset( $preferences['user.updated'] ) && in_array( $preferences['user.updated'], array( '0', '1' ), true ) ) {
				$preferences[ $event ] = $preferences['user.updated'];
			}
		}
		if ( ! isset( $preferences['user.registered'] ) && isset( $preferences['user.created'] ) && in_array( $preferences['user.created'], array( '0', '1' ), true ) ) {
			$preferences['user.registered'] = $preferences['user.created'];
		}
		unset( $preferences['user.updated'] );
		return $preferences;
	}

	/**
	 * Record a successful login.
	 *
	 * @param string  $login Username.
	 * @param WP_User $user  Authenticated user.
	 * @since 2.26.0
	 */
	public function log_login( $login, $user ) {
		$this->emit( 'user.login', 'user', (int) $user->ID, array(), (int) $user->ID );
	}

	/**
	 * Record a logout before WordPress clears the current user.
	 *
	 * @param int $user_id User logging out.
	 * @since 2.26.0
	 */
	public function log_logout( $user_id ) {
		$this->emit( 'user.logout', 'user', (int) $user_id, array(), (int) $user_id );
	}

	/**
	 * Log a new user account.
	 *
	 * @param int $user_id User ID.
	 * @since 2.26.0
	 */
	public function log_user_created( $user_id ) {
		unset( $this->roles[ get_current_blog_id() . ':' . $user_id ] );
		$this->emit( get_current_user_id() ? 'user.created' : 'user.registered', 'user', $user_id );
	}

	/**
	 * Capture account context before either deletion API removes the user.
	 *
	 * @param int $user_id User ID.
	 * @since 2.26.0
	 */
	public function log_user_deleted( $user_id ) {
		$user = get_userdata( $user_id );
		if ( $user instanceof WP_User ) {
			$this->deleting[ get_current_blog_id() . ':' . $user_id ] = array(
				'actor'   => get_current_user_id(),
				'details' => array(
					'username'     => $user->user_login,
					'display_name' => $user->display_name,
					'roles'        => array_values( $user->roles ),
				),
			);
		}
	}

	/**
	 * Emit deletion after WordPress completes it, retaining deleted context.
	 *
	 * @param int          $user_id Deleted user ID.
	 * @param int|null     $reassign Content reassignment user ID.
	 * @param WP_User|null $user Deleted user supplied by newer core versions.
	 */
	public function user_deleted( $user_id, $reassign = null, $user = null ) {
		$key      = get_current_blog_id() . ':' . $user_id;
		$snapshot = $this->deleting[ $key ] ?? null;
		unset( $this->deleting[ $key ], $this->profiles[ $key ], $this->roles[ $key ], $this->applications[ $key ], $this->passwords[ $key ] );
		if ( ! $snapshot ) {
			return;
		}
		$details = $snapshot['details'];
		if ( null !== $reassign ) {
			$details['reassigned_to'] = (int) $reassign;
		}
		$this->emit( 'user.deleted', 'user', $user_id, $details, $snapshot['actor'] );
	}

	/**
	 * Capture profile fields before WordPress writes the user and metadata.
	 *
	 * @param array $data User row data.
	 * @param bool  $update Whether this is an update.
	 * @param int   $user_id User ID.
	 * @return array Unchanged row data.
	 */
	public function capture_profile( $data, $update, $user_id ) {
		if ( $update ) {
			$this->profiles[ get_current_blog_id() . ':' . $user_id ] = $this->profile_state( $user_id );
		}
		return $data;
	}

	/**
	 * Read only profile fields, excluding roles and domain-specific metadata.
	 *
	 * @param int $user_id User ID.
	 * @return array|null Profile state.
	 */
	private function profile_state( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user instanceof WP_User ) {
			return null;
		}
		$state = array();
		foreach ( array( 'user_pass', 'user_email', 'display_name', 'user_url', 'user_login', 'user_nicename' ) as $field ) {
			$state[ $field ] = (string) $user->$field;
		}
		foreach ( array_diff( self::profile_fields(), array( 'user_login', 'user_nicename' ) ) as $field ) {
			$state[ $field ] = (string) get_user_meta( $user_id, $field, true );
		}
		return $state;
	}

	/**
	 * Log an effective profile update, not a role-only or unchanged save.
	 *
	 * @param int $user_id User ID.
	 * @since 2.26.0
	 */
	public function log_user_updated( $user_id ) {
		$key    = get_current_blog_id() . ':' . $user_id;
		$before = $this->profiles[ $key ] ?? null;
		unset( $this->profiles[ $key ] );
		$after = $this->profile_state( $user_id );
		if ( null === $before || null === $after ) {
			return;
		}
		$events = array(
			'user_pass'    => 'user.password_changed',
			'user_email'   => 'user.email_changed',
			'display_name' => 'user.display_name_changed',
			'user_url'     => 'user.website_changed',
		);
		foreach ( $after as $field => $value ) {
			if ( $before[ $field ] === $value ) {
				continue;
			}
			$event   = $events[ $field ] ?? 'user.profile_changed';
			$details = array( 'field' => $field );
			if ( in_array( $field, array( 'user_pass', 'user_email', 'user_url' ), true ) ) {
				$details['before'] = 'redacted';
				$details['after']  = 'redacted';
			} elseif ( 'description' === $field ) {
				$details['before'] = strlen( $before[ $field ] );
				$details['after']  = strlen( $value );
			} else {
				$details['before'] = $before[ $field ];
				$details['after']  = $value;
			}
			$this->emit( $event, 'user', $user_id, $details );
			if ( 'user.password_changed' === $event ) {
				$this->passwords[ $key ] = hash( 'sha256', $value );
			}
		}
	}

	/**
	 * Observe direct password changes without retaining the supplied plaintext.
	 *
	 * @param string       $password Unused plaintext supplied by core.
	 * @param int          $user_id Affected user.
	 * @param WP_User|null $old_user Previous user supplied by newer WordPress.
	 */
	public function password_set( $password, $user_id, $old_user = null ) {
		$user = get_userdata( $user_id );
		if ( $user instanceof WP_User && ( ! $old_user instanceof WP_User || $old_user->user_pass !== $user->user_pass ) ) {
			$this->password_reset( $user );
		}
	}

	/**
	 * Cover password resets on older core without duplicating direct-set hooks.
	 *
	 * @param WP_User $user Affected user.
	 */
	public function password_reset( $user ) {
		$user = $user instanceof WP_User ? get_userdata( $user->ID ) : false;
		if ( ! $user instanceof WP_User ) {
			return;
		}
		$key   = get_current_blog_id() . ':' . $user->ID;
		$token = hash( 'sha256', $user->user_pass );
		if ( ( $this->passwords[ $key ] ?? '' ) !== $token ) {
			$this->passwords[ $key ] = $token;
			$this->emit(
				'user.password_changed',
				'user',
				$user->ID,
				array(
					'before' => 'redacted',
					'after'  => 'redacted',
				)
			);
		}
	}

	/**
	 * Record a reset request, not a claim that an email was delivered.
	 *
	 * @param string $login Affected login.
	 */
	public function reset_requested( $login ) {
		$user = get_user_by( 'login', $login );
		if ( $user instanceof WP_User ) {
			$this->emit( 'user.password_reset_requested', 'user', $user->ID );
		}
	}

	/**
	 * Snapshot only credential names, never hashes, IPs or usage metadata.
	 *
	 * @param mixed  $check Filter result.
	 * @param int    $user_id Affected user.
	 * @param string $meta_key Metadata key.
	 * @return mixed Unchanged filter result.
	 */
	public function capture_applications( $check, $user_id, $meta_key ) {
		if ( '_application_passwords' === $meta_key && class_exists( 'WP_Application_Passwords' ) ) {
			$names = array();
			foreach ( WP_Application_Passwords::get_user_application_passwords( $user_id ) as $item ) {
				if ( isset( $item['uuid'], $item['name'] ) && is_string( $item['uuid'] ) && is_string( $item['name'] ) ) {
					$names[ $item['uuid'] ] = $item['name'];
				}
			}
			$this->applications[ get_current_blog_id() . ':' . $user_id ] = $names;
		}
		return $check;
	}

	/**
	 * Log a credential after successful creation through any core API.
	 *
	 * @param int   $user_id Affected user.
	 * @param array $item Created credential.
	 */
	public function application_created( $user_id, $item ) {
		$this->application_event( 'user.application_password_created', $user_id, $item );
	}

	/**
	 * Emit effective credential renames; the core update hook also fires on no-ops.
	 *
	 * @param int   $user_id Affected user.
	 * @param array $item Persisted credential.
	 */
	public function application_updated( $user_id, $item ) {
		$key   = get_current_blog_id() . ':' . $user_id;
		$names = $this->applications[ $key ] ?? array();
		unset( $this->applications[ $key ] );
		if ( isset( $item['uuid'], $item['name'], $names[ $item['uuid'] ] ) && $names[ $item['uuid'] ] !== $item['name'] ) {
			$this->application_event(
				'user.application_password_renamed',
				$user_id,
				$item,
				array(
					'before' => $names[ $item['uuid'] ],
					'after'  => $item['name'],
				)
			);
		}
	}

	/**
	 * Log each successfully revoked credential, including bulk revocation.
	 *
	 * @param int   $user_id Affected user.
	 * @param array $item Deleted credential.
	 */
	public function application_deleted( $user_id, $item ) {
		$this->application_event( 'user.application_password_revoked', $user_id, $item );
	}

	/**
	 * Select only safe fields from the credential supplied by WordPress.
	 *
	 * @param string $event Event ID.
	 * @param int    $user_id Affected user.
	 * @param array  $item Credential.
	 * @param array  $details Change details.
	 */
	private function application_event( $event, $user_id, $item, $details = array() ) {
		if ( ! is_array( $item ) || ! isset( $item['uuid'], $item['name'] ) || ! is_string( $item['uuid'] ) || ! is_string( $item['name'] ) ) {
			return;
		}
		$details['application_id']   = $item['uuid'];
		$details['application_name'] = $item['name'];
		$this->emit( $event, 'user', $user_id, $details );
	}

	/**
	 * Capture privilege state before either network operation.
	 *
	 * @param int $user_id Affected user.
	 */
	public function capture_super_admin( $user_id ) {
		if ( is_multisite() ) {
			$this->super_admins[ get_current_blog_id() . ':' . $user_id ] = is_super_admin( $user_id );
		}
	}

	/**
	 * Log verified privilege grants.
	 *
	 * @param int $user_id Affected user.
	 */
	public function super_admin_granted( $user_id ) {
		$this->super_admin_changed( $user_id, true );
	}

	/**
	 * Log verified privilege revocations.
	 *
	 * @param int $user_id Affected user.
	 */
	public function super_admin_revoked( $user_id ) {
		$this->super_admin_changed( $user_id, false );
	}

	/**
	 * Compare completed network privilege changes with their captured state.
	 *
	 * @param int  $user_id Affected user.
	 * @param bool $granted Expected privilege state.
	 */
	private function super_admin_changed( $user_id, $granted ) {
		$key    = get_current_blog_id() . ':' . $user_id;
		$before = $this->super_admins[ $key ] ?? null;
		unset( $this->super_admins[ $key ] );
		if ( is_multisite() && null !== $before && $before !== $granted && is_super_admin( $user_id ) === $granted ) {
			$this->emit( $granted ? 'user.super_admin_granted' : 'user.super_admin_revoked', 'user', $user_id );
		}
	}

	/**
	 * Observe authorized visits to another account, excluding save redirects.
	 *
	 * @param WP_User $user Viewed user.
	 */
	public function profile_viewed( $user ) {
		$actor = get_current_user_id();
		if ( $user instanceof WP_User && $actor && $actor !== (int) $user->ID && current_user_can( 'edit_user', $user->ID ) && null === filter_input( INPUT_GET, 'updated' ) ) {
			$this->emit( 'user.profile_viewed', 'user', $user->ID );
		}
	}

	/** Record denied access without storing arbitrary query strings. */
	public function access_denied() {
		$actor = get_current_user_id();
		if ( ! $actor ) {
			return;
		}
		$page = isset( $GLOBALS['pagenow'] ) && is_string( $GLOBALS['pagenow'] ) ? sanitize_file_name( $GLOBALS['pagenow'] ) : '';
		$this->emit( 'user.access_denied', 'user', $actor, array( 'admin_page' => $page ), $actor );
	}

	/**
	 * Snapshot the role set before its first addition in an operation.
	 *
	 * @param int    $user_id User ID.
	 * @param string $role Added role.
	 */
	public function role_added( $user_id, $role ) {
		$this->capture_roles( $user_id, $role, true );
	}

	/**
	 * Snapshot the role set before its first removal in an operation.
	 *
	 * @param int    $user_id User ID.
	 * @param string $role Removed role.
	 */
	public function role_removed( $user_id, $role ) {
		$this->capture_roles( $user_id, $role, false );
	}

	/**
	 * Retain the original roles before core replaces capabilities metadata.
	 *
	 * @param mixed  $check Filter result.
	 * @param int    $user_id User ID.
	 * @param string $meta_key Metadata key.
	 * @return mixed Unchanged filter result.
	 */
	public function capture_role_metadata( $check, $user_id, $meta_key ) {
		$key  = get_current_blog_id() . ':' . $user_id;
		$user = get_userdata( $user_id );
		if ( $user instanceof WP_User && $user->cap_key === $meta_key && ! isset( $this->roles[ $key ] ) ) {
			$before = array_values( $user->roles );
			sort( $before, SORT_STRING );
			$this->roles[ $key ] = array(
				'blog'     => get_current_blog_id(),
				'user'     => (int) $user_id,
				'before'   => $before,
				'actor'    => get_current_user_id(),
				'observed' => false,
			);
		}
		return $check;
	}

	/**
	 * Reconstruct the initial roles from core's completed role mutation hook.
	 *
	 * @param int    $user_id User ID.
	 * @param string $role Changed role.
	 * @param bool   $added Whether the role was added.
	 */
	private function capture_roles( $user_id, $role, $added ) {
		$key = get_current_blog_id() . ':' . $user_id;
		if ( isset( $this->roles[ $key ] ) ) {
			$this->roles[ $key ]['observed'] = true;
			return;
		}
		$user = get_userdata( $user_id );
		if ( ! $user instanceof WP_User ) {
			return;
		}
		$before = $added ? array_diff( $user->roles, array( $role ) ) : array_merge( $user->roles, array( $role ) );
		$before = array_values( array_unique( $before ) );
		sort( $before, SORT_STRING );
		$this->roles[ $key ] = array(
			'blog'     => get_current_blog_id(),
			'user'     => (int) $user_id,
			'before'   => $before,
			'actor'    => get_current_user_id(),
			'observed' => true,
		);
	}

	/**
	 * Emit one role event after all removals and additions in a user update.
	 *
	 * @param int $user_id Updated user ID.
	 */
	public function flush_user_roles( $user_id ) {
		$key      = get_current_blog_id() . ':' . $user_id;
		$snapshot = $this->roles[ $key ] ?? null;
		unset( $this->roles[ $key ] );
		$user = get_userdata( $user_id );
		if ( ! $snapshot || ! $snapshot['observed'] || ! $user instanceof WP_User ) {
			return;
		}
		$after = array_values( $user->roles );
		sort( $after, SORT_STRING );
		if ( $snapshot['before'] !== $after ) {
			$this->emit(
				'user.role_changed',
				'user',
				$user_id,
				array(
					'before'        => $snapshot['before'],
					'after'         => $after,
					'added_roles'   => array_values( array_diff( $after, $snapshot['before'] ) ),
					'removed_roles' => array_values( array_diff( $snapshot['before'], $after ) ),
				),
				$snapshot['actor']
			);
		}
	}

	/** Complete standalone role API changes on their originating blogs. */
	public function flush_roles() {
		foreach ( $this->roles as $snapshot ) {
			$switch = get_current_blog_id() !== $snapshot['blog'];
			if ( $switch ) {
				switch_to_blog( $snapshot['blog'] );
			}
			try {
				$this->flush_user_roles( $snapshot['user'] );
			} finally {
				if ( $switch ) {
					restore_current_blog();
				}
			}
		}
	}

	/**
	 * Get the core profile fields owned by this provider.
	 *
	 * @return array Field names.
	 */
	private static function profile_fields() {
		return array( 'user_login', 'user_nicename', 'first_name', 'last_name', 'nickname', 'description', 'rich_editing', 'syntax_highlighting', 'comment_shortcuts', 'admin_color', 'use_ssl', 'show_admin_bar_front', 'locale' );
	}

	/**
	 * Sanitize and bound role lists without accepting nested values.
	 *
	 * @param array $roles Role slugs.
	 * @return array Bounded role slugs.
	 */
	private static function safe_roles( $roles ) {
		$safe = array();
		foreach ( array_slice( $roles, 0, 20 ) as $role ) {
			if ( is_string( $role ) ) {
				$role = substr( sanitize_key( $role ), 0, 80 );
				if ( '' !== $role ) {
					$safe[] = $role;
				}
			}
		}
		return array_values( array_unique( $safe ) );
	}

	/**
	 * Bound user-facing context without breaking UTF-8.
	 *
	 * @param string $value Text.
	 * @return string Sanitized text.
	 */
	private static function bounded_text( $value ) {
		$value = sanitize_text_field( $value );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 160, 'UTF-8' ) : _mb_substr( $value, 0, 160, 'UTF-8' );
	}

	/**
	 * Send contextual events to the normalized writer.
	 *
	 * @param string     $event     Event identifier.
	 * @param string     $object_type Object category.
	 * @param int|string $object_id Object identifier.
	 * @param array      $details   Optional event details.
	 * @param int|null   $actor     Initiating actor ID.
	 * @since 2.26.0
	 */
	private function emit( $event, $object_type, $object_id, $details = array(), $actor = null ) {
		$actor = null === $actor ? get_current_user_id() : $actor;
		$user  = get_userdata( $object_id );
		if ( $user instanceof WP_User ) {
			$details += array(
				'username'     => $user->user_login,
				'display_name' => $user->display_name,
				'roles'        => array_values( $user->roles ),
			);
		}
		$details['target_relation'] = $actor ? ( (int) $object_id === $actor ? 'self' : 'other' ) : 'system';
		$details['source']          = defined( 'WP_CLI' ) && WP_CLI ? 'cli' : ( $actor ? 'user' : 'system' );
		do_action( 'wpmastertoolkit/activity_log/event', $event, $actor, $object_type, $object_id, $details ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Preserve the existing public event action.
	}
}
