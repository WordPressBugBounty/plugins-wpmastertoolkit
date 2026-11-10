<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Manage module activation states and lifecycle callbacks.
 *
 * @since 2.25.0
 */
class WPMastertoolkit_Module_Manager {

	/**
	 * The option used to prevent concurrent module updates.
	 *
	 * @var string
	 */
	const LOCK_OPTION = 'wpmastertoolkit_module_manager_lock';

	/**
	 * Replace the complete module status map.
	 *
	 * @param array         $new_settings     Module statuses keyed by class name.
	 * @param callable|null $prepare_callback Optional callback run under the update lock before lifecycle methods.
	 * @return array
	 */
	public static function save_all( $new_settings, $prepare_callback = null ) {
		$modules  = array_keys( wpmastertoolkit_options( 'normal' ) );
		$statuses = array();

		foreach ( $modules as $module_id ) {
			$statuses[ $module_id ] = self::normalize_status( $new_settings[ $module_id ] ?? '0' );
		}

		return self::apply( $statuses, true, array(), $prepare_callback );
	}

	/**
	 * Enable selected modules without changing any other module state.
	 *
	 * @param array $modules Module definitions keyed by class name.
	 * @return array|WP_Error
	 */
	public static function enable_modules( $modules ) {
		$statuses = array();
		$options  = array();

		foreach ( $modules as $module_id => $module ) {
			if ( is_int( $module_id ) ) {
				$module_id = $module;
				$module    = array();
			}

			$statuses[ $module_id ] = '1';

			if ( is_array( $module ) && isset( $module['options'] ) && is_array( $module['options'] ) ) {
				$options[ $module_id ] = $module['options'];
			}
		}

		return self::apply( $statuses, false, $options );
	}

	/**
	 * Apply module status transitions.
	 *
	 * Lifecycle methods can change the filesystem or database and cannot be rolled
	 * back transactionally. Failed transitions retain their previous saved state.
	 *
	 * @param array         $statuses        Desired module statuses.
	 * @param bool          $replace_all     Whether the supplied map replaces all statuses.
	 * @param array         $options         Optional module-owned preset settings.
	 * @param callable|null $prepare_callback Optional callback run before lifecycle methods.
	 * @return array|WP_Error
	 */
	private static function apply( $statuses, $replace_all, $options = array(), $prepare_callback = null ) {
		if ( ! self::acquire_lock() ) {
			return new WP_Error(
				'wpmastertoolkit_module_update_locked',
				esc_html__( 'Another module update is currently in progress. Please try again.', 'wpmastertoolkit' )
			);
		}

		$registry     = wpmastertoolkit_options( 'normal' );
		$old_settings = get_option( WPMASTERTOOLKIT_PLUGIN_SETTINGS, array() );
		$new_settings = $replace_all ? array() : $old_settings;
		$results      = array();
		$load_errors  = array();

		try {
			foreach ( $statuses as $module_id => $status ) {
				$status     = self::normalize_status( $status );
				$old_status = self::normalize_status( $old_settings[ $module_id ] ?? '0' );

				if ( ! isset( $registry[ $module_id ] ) || $old_status === $status ) {
					continue;
				}

				try {
					$loaded = self::load_module( $module_id, $registry[ $module_id ] );
					if ( is_wp_error( $loaded ) ) {
						$load_errors[ $module_id ] = $loaded->get_error_message();
					} elseif ( '1' === $status && isset( $options[ $module_id ] ) && ! is_callable( array( $module_id, 'apply_preset_settings' ) ) ) {
						$load_errors[ $module_id ] = esc_html__( 'This module does not support preset settings.', 'wpmastertoolkit' );
					}
				} catch ( Throwable $exception ) {
					$load_errors[ $module_id ] = $exception->getMessage();
				}
			}

			if ( is_callable( $prepare_callback ) ) {
				try {
					call_user_func( $prepare_callback );
				} catch ( Throwable $exception ) {
					return new WP_Error( 'wpmastertoolkit_module_prepare_failed', $exception->getMessage() );
				}
			}

			foreach ( $statuses as $module_id => $status ) {
				$status = self::normalize_status( $status );

				if ( ! isset( $registry[ $module_id ] ) ) {
					$results[ $module_id ] = self::result( 'failed', esc_html__( 'The module is not registered.', 'wpmastertoolkit' ) );
					continue;
				}

				$old_status = self::normalize_status( $old_settings[ $module_id ] ?? '0' );
				if ( $old_status === $status ) {
					$new_settings[ $module_id ] = $status;
					$results[ $module_id ]      = self::result( '1' === $status ? 'already_active' : 'already_inactive' );
					continue;
				}

				if ( isset( $load_errors[ $module_id ] ) ) {
					$new_settings[ $module_id ] = $old_status;
					$results[ $module_id ]      = self::result( 'failed', $load_errors[ $module_id ] );
					continue;
				}

				try {
					if ( '1' === $status ) {
						if ( isset( $options[ $module_id ] ) ) {
							$applied = call_user_func( array( $module_id, 'apply_preset_settings' ), $options[ $module_id ] );
							if ( is_wp_error( $applied ) ) {
								$new_settings[ $module_id ] = $old_status;
								$results[ $module_id ]      = self::result( 'failed', $applied->get_error_message() );
								continue;
							}
						}

						if ( is_callable( array( $module_id, 'activate' ) ) ) {
							call_user_func( array( $module_id, 'activate' ) );
						}

						WPMastertoolkit_Logs::add_notice( 'Module Activated: ' . $module_id );
						$results[ $module_id ] = self::result( 'activated' );
					} else {
						if ( is_callable( array( $module_id, 'deactivate' ) ) ) {
							call_user_func( array( $module_id, 'deactivate' ) );
						}

						WPMastertoolkit_Logs::add_notice( 'Module Deactivated: ' . $module_id );
						$results[ $module_id ] = self::result( 'deactivated' );
					}

					$new_settings[ $module_id ] = $status;
				} catch ( Throwable $exception ) {
					$new_settings[ $module_id ] = $old_status;
					$results[ $module_id ]      = self::result( 'failed', $exception->getMessage() );
				}
			}

			if ( $replace_all ) {
				foreach ( $registry as $module_id => $module ) {
					if ( ! isset( $new_settings[ $module_id ] ) ) {
						$new_settings[ $module_id ] = self::normalize_status( $old_settings[ $module_id ] ?? '0' );
					}
				}
			}

			if ( $new_settings !== $old_settings && ! update_option( WPMASTERTOOLKIT_PLUGIN_SETTINGS, $new_settings ) ) {
				return new WP_Error(
					'wpmastertoolkit_module_state_not_saved',
					esc_html__( 'Module lifecycle actions ran, but WordPress could not save the final module state.', 'wpmastertoolkit' )
				);
			}
		} finally {
			self::release_lock();
		}

		return $results;
	}

	/**
	 * Load a registered module class.
	 *
	 * @param string $module_id Module class name.
	 * @param array  $module    Module registry data.
	 * @return true|WP_Error
	 */
	public static function load_module( $module_id, $module ) {
		$module_path = $module['path'] ?? '';

		if ( strpos( $module_path, 'core/' ) === 0 || strpos( $module_path, 'pro/' ) === 0 ) {
			$module_path = WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/modules/' . $module_path;
		}

		if ( ! is_file( $module_path ) ) {
			return new WP_Error(
				'wpmastertoolkit_module_unavailable',
				esc_html__( 'The module is not available in this installation.', 'wpmastertoolkit' )
			);
		}

		require_once $module_path;

		if ( ! class_exists( $module_id ) ) {
			return new WP_Error(
				'wpmastertoolkit_module_class_missing',
				esc_html__( 'The module class could not be loaded.', 'wpmastertoolkit' )
			);
		}

		return true;
	}

	/**
	 * Normalize a module status for storage.
	 *
	 * @param mixed $status Raw status.
	 * @return string
	 */
	private static function normalize_status( $status ) {
		return '1' === (string) $status ? '1' : '0';
	}

	/**
	 * Build a module operation result.
	 *
	 * @param string $status  Result status.
	 * @param string $message Optional result message.
	 * @return array
	 */
	private static function result( $status, $message = '' ) {
		return array(
			'status'  => $status,
			'message' => $message,
		);
	}

	/**
	 * Acquire the module update lock.
	 *
	 * @return bool
	 */
	private static function acquire_lock() {
		$lock_time = (int) get_option( self::LOCK_OPTION, 0 );

		if ( $lock_time && ( time() - $lock_time ) < MINUTE_IN_SECONDS ) {
			return false;
		}

		delete_option( self::LOCK_OPTION );

		return add_option( self::LOCK_OPTION, time(), '', false );
	}

	/**
	 * Release the module update lock.
	 *
	 * @return void
	 */
	private static function release_lock() {
		delete_option( self::LOCK_OPTION );
	}
}