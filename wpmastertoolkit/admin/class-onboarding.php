<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Handle the guided plugin onboarding flow.
 *
 * @since 2.25.0
 */
class WPMastertoolkit_Onboarding {

	/**
	 * Onboarding state option name.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'wpmastertoolkit_onboarding';

	/**
	 * Onboarding page slug.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'wp-mastertoolkit-onboarding';

	/**
	 * Register the hidden onboarding page.
	 *
	 * @return void
	 */
	public function register_page() {
		add_submenu_page(
			null,
			esc_html__( 'WPMasterToolKit Guided Setup', 'wpmastertoolkit' ),
			esc_html__( 'Guided Setup', 'wpmastertoolkit' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Add a route-specific admin body class.
	 *
	 * @param string $classes Admin body classes.
	 * @return string
	 */
	public function body_class( $classes ) {
		if ( $this->is_onboarding_page() ) {
			$classes .= ' wpmtk-onboarding-page';
		}

		return trim( $classes );
	}

	/**
	 * Create a legacy marker when upgrading from a version without onboarding.
	 *
	 * @return void
	 */
	public function ensure_state() {
		if ( false !== get_option( self::OPTION_NAME, false ) ) {
			return;
		}

		add_option(
			self::OPTION_NAME,
			array(
				'status'           => 'legacy_skipped',
				'schema_version'   => WPMastertoolkit_Onboarding_Presets::SCHEMA_VERSION,
				'plugin_version'   => WPMASTERTOOLKIT_VERSION,
				'created_at'       => time(),
				'completed_at'     => time(),
				'selected_presets' => array(),
				'last_result'      => array(),
			),
			'',
			false
		);
	}

	/**
	 * Redirect the activating administrator to onboarding once.
	 *
	 * @return void
	 */
	public function maybe_redirect() {
		if ( ! $this->current_user_can_access() || $this->should_suppress_redirect() ) {
			return;
		}

		$state = $this->get_state();
		if ( 'pending' !== $state['status'] ) {
			return;
		}

		$transient = 'wpmastertoolkit_onboarding_redirect_' . get_current_user_id();
		if ( ! get_transient( $transient ) ) {
			return;
		}

		delete_transient( $transient );
		wp_safe_redirect( $this->get_url() );
		exit;
	}

	/**
	 * Show a setup notice while onboarding is pending.
	 *
	 * @return void
	 */
	public function admin_notice() {
		if ( ! $this->current_user_can_access() || $this->is_onboarding_page() ) {
			return;
		}

		$state = $this->get_state();
		if ( 'pending' !== $state['status'] ) {
			return;
		}
		?>
		<div class="notice notice-info is-dismissible">
			<p>
				<strong><?php esc_html_e( 'WPMasterToolKit is ready to configure.', 'wpmastertoolkit' ); ?></strong>
				<?php esc_html_e( 'Choose your goals and review the recommended modules before anything is activated.', 'wpmastertoolkit' ); ?>
			</p>
			<p><a class="button button-primary" href="<?php echo esc_url( $this->get_url() ); ?>"><?php esc_html_e( 'Start guided setup', 'wpmastertoolkit' ); ?></a></p>
		</div>
		<?php
	}

	/**
	 * Enqueue onboarding assets only on the onboarding page.
	 *
	 * @param string $hook_suffix Current admin hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( 'admin_page_' . self::PAGE_SLUG !== $hook_suffix || ! $this->current_user_can_access() ) {
			return;
		}

		$asset_file = WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/onboarding.asset.php';
		if ( ! is_file( $asset_file ) ) {
			return;
		}

		$assets = include $asset_file;
		wp_enqueue_style( 'wpmastertoolkit-onboarding', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/onboarding.css', array(), $assets['version'], 'all' );
		wp_enqueue_script( 'wpmastertoolkit-onboarding', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/onboarding.js', $assets['dependencies'], $assets['version'], true );
	}

	/**
	 * Render the onboarding page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! $this->current_user_can_access() ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'wpmastertoolkit' ) );
		}

		$presets        = WPMastertoolkit_Onboarding_Presets::get_presets();
		$active_modules = get_option( WPMASTERTOOLKIT_PLUGIN_SETTINGS, array() );
		$onboarding     = $this->get_state();
		$form_state     = get_transient( 'wpmastertoolkit_onboarding_form_' . get_current_user_id() );
		delete_transient( 'wpmastertoolkit_onboarding_form_' . get_current_user_id() );
		$form_state   = is_array( $form_state ) ? $form_state : array();
		$saved_fields = array();
		$registry     = wpmastertoolkit_options();
		foreach ( $presets as $preset ) {
			foreach ( $preset['modules'] as $module ) {
				if ( empty( $module['fields'] ) || ! $module['available'] || isset( $saved_fields[ $module['id'] ] ) ) {
					continue;
				}

				$loaded   = WPMastertoolkit_Module_Manager::load_module( $module['id'], $registry[ $module['id'] ] );
				$settings = ! is_wp_error( $loaded ) && is_callable( array( $module['id'], 'get_preset_settings' ) )
					? call_user_func( array( $module['id'], 'get_preset_settings' ) ) : array();
				$saved_fields[ $module['id'] ] = is_array( $settings ) ? $settings : array();
			}
		}
		$upgrade_url = wpmastertoolkit_get_tracked_url(
			__( 'https://wpmastertoolkit.com/en/products-2/wpmastertoolkit-pro/', 'wpmastertoolkit' ),
			'upgrade-pro',
			'onboarding-pro-module'
		);

		require WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/page-onboarding.php';
	}

	/**
	 * Apply selected onboarding modules.
	 *
	 * @return void
	 */
	public function handle_apply() {
		$request_method = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) );
		if ( 'POST' !== $request_method || ! $this->current_user_can_access() ) {
			wp_die( esc_html__( 'You are not allowed to perform this action.', 'wpmastertoolkit' ) );
		}

		check_admin_referer( 'wpmastertoolkit_apply_onboarding' );

		$presets = WPMastertoolkit_Onboarding_Presets::get_presets();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$requested_presets = $this->sanitize_request_array( wp_unslash( $_POST['wpmastertoolkit_onboarding_presets'] ?? array() ), 'sanitize_key' );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$requested_modules = $this->sanitize_request_array( wp_unslash( $_POST['wpmastertoolkit_onboarding_modules'] ?? array() ), 'sanitize_text_field' );
		$selected_presets  = array_values( array_intersect( array_keys( $presets ), $requested_presets ) );
		$allowed_modules   = array();
		$module_presets    = array();
		$unavailable       = array();

		foreach ( $selected_presets as $preset_id ) {
			foreach ( $presets[ $preset_id ]['modules'] as $module ) {
				if ( $module['available'] ) {
					if ( ! isset( $allowed_modules[ $module['id'] ] ) ) {
						$allowed_modules[ $module['id'] ] = $module;
						$module_presets[ $module['id'] ]  = $preset_id;
					}
				} elseif ( $module['recommended'] ) {
					$unavailable[ $module['id'] ] = array(
						'status'  => 'unavailable',
						'message' => esc_html__( 'This recommended module is not available in the current installation.', 'wpmastertoolkit' ),
					);
				}
			}
		}

		$selected_modules = array_values( array_intersect( array_keys( $allowed_modules ), $requested_modules ) );
		$modules          = array();
		$errors           = array();
		$draft_values     = array();
		$active_modules   = get_option( WPMASTERTOOLKIT_PLUGIN_SETTINGS, array() );
		$registry         = wpmastertoolkit_options();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$submitted_fields = wp_unslash( $_POST['wpmastertoolkit_onboarding_fields'] ?? array() );
		$submitted_fields = is_array( $submitted_fields ) ? $submitted_fields : array();

		foreach ( $selected_modules as $module_id ) {
			$modules[ $module_id ] = array();
			if ( ! empty( $allowed_modules[ $module_id ]['options'] ) ) {
				$modules[ $module_id ]['options'] = $allowed_modules[ $module_id ]['options'];
			}

			if ( '1' === ( $active_modules[ $module_id ] ?? '0' ) || empty( $allowed_modules[ $module_id ]['fields'] ) ) {
				continue;
			}

			$fields = $allowed_modules[ $module_id ]['fields'];
			$values = $submitted_fields[ $module_presets[ $module_id ] ][ $module_id ] ?? array();
			$values = is_array( $values ) ? $values : array();
			foreach ( $fields as $key => $field ) {
				$raw_value = $values[ $key ] ?? ( 'checkbox' === $field['type'] ? '0' : ( $field['required'] ? null : $field['default'] ) );
				$value     = $this->validate_field( $raw_value, $field );
				if ( is_wp_error( $value ) ) {
					$errors[ $module_id ][ $key ] = $value->get_error_message();
					if ( is_scalar( $raw_value ) ) {
						$draft_values[ $module_id ][ $key ] = sanitize_text_field( (string) $raw_value );
					}
					continue;
				}

				$draft_values[ $module_id ][ $key ]      = $value;
				$modules[ $module_id ]['options'][ $key ] = $value;
			}

			if ( isset( $errors[ $module_id ] ) ) {
				continue;
			}

			$loaded   = WPMastertoolkit_Module_Manager::load_module( $module_id, $registry[ $module_id ] );
			if ( is_wp_error( $loaded ) ) {
				$errors[ $module_id ][ array_key_first( $fields ) ] = $loaded->get_error_message();
			} elseif ( ! is_callable( array( $module_id, 'apply_preset_settings' ) ) ) {
				$errors[ $module_id ][ array_key_first( $fields ) ] = esc_html__( 'This module does not support onboarding settings.', 'wpmastertoolkit' );
			} elseif ( is_callable( array( $module_id, 'validate_preset_settings' ) ) ) {
				$validation = call_user_func( array( $module_id, 'validate_preset_settings' ), $modules[ $module_id ]['options'] );
				if ( is_wp_error( $validation ) ) {
					$error_key = $validation->get_error_data();
					$errors[ $module_id ][ is_string( $error_key ) && isset( $fields[ $error_key ] ) ? $error_key : array_key_first( $fields ) ] = $validation->get_error_message();
				}
			}
		}

		if ( $errors ) {
			set_transient(
				'wpmastertoolkit_onboarding_form_' . get_current_user_id(),
				array( 'presets' => $selected_presets, 'modules' => $selected_modules, 'values' => $draft_values, 'errors' => $errors ),
				5 * MINUTE_IN_SECONDS
			);
			wp_safe_redirect( $this->get_url() );
			exit;
		}

		if ( empty( $modules ) ) {
			$this->update_state(
				array(
					'last_result' => array(
						'_error' => array(
							'status'  => 'failed',
							'message' => esc_html__( 'Select at least one available module before applying the configuration.', 'wpmastertoolkit' ),
						),
					),
				)
			);

			wp_safe_redirect( add_query_arg( 'onboarding_status', 'error', $this->get_url() ) );
			exit;
		}

		$result = WPMastertoolkit_Module_Manager::enable_modules( $modules );
		if ( is_wp_error( $result ) ) {
			$this->update_state(
				array(
					'last_result' => array(
						'_error' => array(
							'status'  => 'failed',
							'message' => $result->get_error_message(),
						),
					),
				)
			);

			wp_safe_redirect( add_query_arg( 'onboarding_status', 'error', $this->get_url() ) );
			exit;
		}

		$has_failures = false;
		$last_login_url = '';
		foreach ( $result as $module_result ) {
			if ( 'failed' === ( $module_result['status'] ?? '' ) ) {
				$has_failures = true;
				break;
			}
		}
		if ( 'activated' === ( $result['WPMastertoolkit_Move_Login_URL']['status'] ?? '' ) ) {
			$last_login_url = ( new WPMastertoolkit_Move_Login_URL() )->new_login_url();
		}

		$this->update_state(
			array(
				'status'           => 'completed',
				'completed_at'     => time(),
				'selected_presets' => $selected_presets,
				'last_result'      => array_merge( $result, $unavailable ),
				'last_login_url'   => $last_login_url,
			)
		);

		wp_safe_redirect( add_query_arg( 'onboarding_status', $has_failures ? 'issues' : 'completed', $this->get_url() ) );
		exit;
	}

	/**
	 * Skip automatic onboarding.
	 *
	 * @return void
	 */
	public function handle_skip() {
		$request_method = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) );
		if ( 'POST' !== $request_method || ! $this->current_user_can_access() ) {
			wp_die( esc_html__( 'You are not allowed to perform this action.', 'wpmastertoolkit' ) );
		}

		check_admin_referer( 'wpmastertoolkit_skip_onboarding' );
		$this->update_state(
			array(
				'status'       => 'skipped',
				'completed_at' => time(),
			)
		);

		wp_safe_redirect( admin_url( 'admin.php?page=wp-mastertoolkit-settings' ) );
		exit;
	}

	/**
	 * Return onboarding state with defaults.
	 *
	 * @return array
	 */
	private function get_state() {
		$state = get_option( self::OPTION_NAME, array() );

		return wp_parse_args(
			is_array( $state ) ? $state : array(),
			array(
				'status'           => 'legacy_skipped',
				'schema_version'   => WPMastertoolkit_Onboarding_Presets::SCHEMA_VERSION,
				'plugin_version'   => WPMASTERTOOLKIT_VERSION,
				'created_at'       => 0,
				'completed_at'     => 0,
				'selected_presets' => array(),
				'last_result'      => array(),
			)
		);
	}

	/**
	 * Merge values into onboarding state.
	 *
	 * @param array $changes State changes.
	 * @return void
	 */
	private function update_state( $changes ) {
		update_option( self::OPTION_NAME, array_merge( $this->get_state(), $changes ), false );
	}

	/**
	 * Return the onboarding page URL.
	 *
	 * @return string
	 */
	private function get_url() {
		return admin_url( 'admin.php?page=' . self::PAGE_SLUG );
	}

	/**
	 * Check whether the current request renders onboarding.
	 *
	 * @return bool
	 */
	private function is_onboarding_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return self::PAGE_SLUG === sanitize_key( wp_unslash( $_GET['page'] ?? '' ) );
	}

	/**
	 * Check whether automatic redirect should be suppressed.
	 *
	 * @return bool
	 */
	private function should_suppress_redirect() {
		if ( $this->is_onboarding_page() || wp_doing_ajax() || is_network_admin() ) {
			return true;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return true;
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET['activate-multi'] );
	}

	/**
	 * Check the current user's plugin-specific administration access.
	 *
	 * @return bool
	 */
	private function current_user_can_access() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		$restriction_active = get_option( WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_active_super_admins', '0' );
		$selected_admins    = get_option( WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_super_admins', array() );

		if ( wpmastertoolkit_is_pro() && '1' === $restriction_active && ! empty( $selected_admins ) ) {
			return in_array( get_current_user_id(), array_map( 'intval', $selected_admins ), true );
		}

		return true;
	}

	/**
	 * Sanitize a flat array from a request.
	 *
	 * @param mixed    $value    Request value.
	 * @param callable $callback Sanitization callback.
	 * @return array
	 */
	private function sanitize_request_array( $value, $callback ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $value as $item ) {
			if ( is_scalar( $item ) ) {
				$sanitized[] = call_user_func( $callback, (string) $item );
			}
		}

		return array_values( array_unique( array_filter( $sanitized ) ) );
	}

	/**
	 * Validate a scalar against a declared onboarding field.
	 *
	 * @param mixed $raw_value Submitted value.
	 * @param array $field     Normalized field definition.
	 * @return string|WP_Error
	 */
	private function validate_field( $raw_value, $field ) {
		if ( null === $raw_value || ! is_scalar( $raw_value ) ) {
			return new WP_Error( 'invalid_field', esc_html__( 'Enter a valid value for this setting.', 'wpmastertoolkit' ) );
		}

		$value = (string) $raw_value;
		if ( 'checkbox' === $field['type'] ) {
			if ( ! in_array( $value, array( '0', '1' ), true ) ) {
				return new WP_Error( 'invalid_field', esc_html__( 'Enter a valid value for this setting.', 'wpmastertoolkit' ) );
			}
		} elseif ( 'select' === $field['type'] ) {
			if ( ! array_key_exists( $value, $field['choices'] ) ) {
				return new WP_Error( 'invalid_field', esc_html__( 'Select a valid option.', 'wpmastertoolkit' ) );
			}
		} elseif ( 'number' === $field['type'] ) {
			if ( '' !== $value && ( ! is_numeric( $value ) || ! is_finite( (float) $value ) || ( isset( $field['min'] ) && (float) $value < (float) $field['min'] ) || ( isset( $field['max'] ) && (float) $value > (float) $field['max'] ) ) ) {
				return new WP_Error( 'invalid_field', esc_html__( 'Enter a number within the allowed range.', 'wpmastertoolkit' ) );
			}
		} elseif ( 'textarea' === $field['type'] ) {
			$value = sanitize_textarea_field( $value );
		} else {
			$value = sanitize_text_field( $value );
		}

		if ( $field['required'] && ( '' === $value || ( 'checkbox' === $field['type'] && '0' === $value ) ) ) {
			return new WP_Error( 'required_field', esc_html__( 'This setting is required.', 'wpmastertoolkit' ) );
		}

		return $value;
	}
}