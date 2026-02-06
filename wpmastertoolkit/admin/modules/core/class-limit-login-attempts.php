<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Limit Login Attempts
 * Description: Prevent brute force attacks by limiting the number of failed login attempts allowed per IP address.
 * @since 1.5.0
 */
class WPMastertoolkit_Limit_Login_Attempts {

    private $option_id;
    private $header_title;
    private $nonce_action;
    private $settings;
    private $default_settings;

	/**
     * Invoke the hooks
     * 
     * @since    1.5.0
     */
    public function __construct() {
        $this->option_id    = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_limit_login_attempts';
        $this->nonce_action = $this->option_id . '_action';

        add_action( 'init', array( $this, 'class_init' ) );
        add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
        add_action( 'admin_init', array( $this, 'save_submenu' ) );
		add_filter( 'authenticate', array( $this, 'maybe_allow_login' ), 999 );
		add_action( 'wp_login_errors', array( $this, 'login_error_handler' ), 999 );
		add_action( 'login_enqueue_scripts', array( $this, 'maybe_hide_login_form' ) );
		add_action( 'wp_login_failed', array( $this, 'log_failed_login' ), 5 );
		add_action( 'wp_login', array( $this, 'clear_failed_login_log' ) );
    }

	/**
     * Initialize the class
     * 
     * @since    1.5.0
     * @return   void
     */
    public function class_init() {
        $this->header_title = esc_html__( 'Limit Login Attempts', 'wpmastertoolkit' );
    }

	/**
	 * Maybe allow login if not locked out.
	 * 
	 * @since   1.5.0
	 */
	public function maybe_allow_login( $user ) {
		global $wpdb, $wpmastertoolkit_limit_login;
		
		if ( ! $this->is_table_exist() ) {
			$this->create_table();
		}

		$fail_count       = 0;
		$lockout_count    = 0;
		$last_fail_on     = '';
		$settings         = $this->get_settings();
		$ip_address       = wpmastertoolkit_get_current_ip();
		$table_name       = $this->get_table_name();
		$default_settings = $this->get_default_settings();
		$fails_allowed    = $settings['fails_allowed'] ?? $default_settings['fails_allowed'];
		$lockout_maxcount = $settings['lockout_maxcount'] ?? $default_settings['lockout_maxcount'];
		$request_uri      = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result           = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i Where %i = %s", $table_name, 'ip_address', $ip_address ), ARRAY_A );
		$result_count     = count( $result );

		if ( $result_count > 0 ) {
			$fail_count    = $result[0]['fail_count'];
			$lockout_count = $result[0]['lockout_count'];
			$last_fail_on  = $result[0]['unixtime'];
		}

		// Initialize the global variable
        $wpmastertoolkit_limit_login = array (
            'ip_address'                => $ip_address,
            'request_uri'               => $request_uri,
            'ip_address_log'            => $result,
            'fail_count'                => $fail_count,
            'lockout_count'             => $lockout_count,
            'maybe_lockout'             => false,
            'extended_lockout'          => false,
            'within_lockout_period'     => false,
            'lockout_period'            => 0,
            'lockout_period_remaining'  => 0,
            'login_fails_allowed'       => $fails_allowed,
            'login_lockout_maxcount'    => $lockout_maxcount,
            'default_lockout_period'    => 60*15, // 15 minutes in seconds
            'extended_lockout_period'   => 24*60*60, // 24 hours in seconds
        );

		if ( $result_count > 0 ) {
			if ( 0 == $fail_count % $fails_allowed ) {
				$wpmastertoolkit_limit_login['maybe_lockout'] = true;

				// Has reached max / gone beyond number of lockouts allowed?
				if ( $lockout_count >= $lockout_maxcount ) {
					$wpmastertoolkit_limit_login['extended_lockout'] = true;
					$lockout_period = $wpmastertoolkit_limit_login['extended_lockout_period'];
				} else {
					$wpmastertoolkit_limit_login['extended_lockout'] = false;
					$lockout_period = $wpmastertoolkit_limit_login['default_lockout_period'];
				}

				$wpmastertoolkit_limit_login['lockout_period'] = $lockout_period;

				// User/visitor is still within the lockout period
				if ( ( time() - $last_fail_on ) <= $lockout_period ) {

					$lockout_period_remaining                                = $wpmastertoolkit_limit_login['lockout_period'] - ( time() - $last_fail_on );
					$wpmastertoolkit_limit_login['within_lockout_period']    = true;
                    $wpmastertoolkit_limit_login['lockout_period_remaining'] = $lockout_period_remaining;
					$lockout_period_remaining_to_render                      = $this->formate_seconds_to_period( $lockout_period_remaining );

					$error = new WP_Error( 
						'ip_address_blocked',
						sprintf( 
							/* translators: %s: Lockout period remaining */
							__( '<b>WARNING:</b> You\'ve been locked out. You can login again in %s.', 'wpmastertoolkit' ), 
							$lockout_period_remaining_to_render 
						) 
					);
					return $error;
				} else {
					$wpmastertoolkit_limit_login['within_lockout_period'] = false;

					if ( $lockout_count == $lockout_maxcount ) {
						// Remove the DB log entry for the current IP address. i.e. release from extended lockout
						$where        = array( 'ip_address' => $ip_address );
						$where_format = array( '%s' );

						//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
						$wpdb->delete(
							$table_name,
							$where,
							$where_format
						);
					}
				}
			}
		}

		return $user;
	}

	/**
	 * Login error handler
	 * 
	 * @since   1.5.0
	 */
	public function login_error_handler( $errors ) {
		if ( is_wp_error( $errors ) ) {
			$error_codes = $errors->get_error_codes();

			foreach ( $error_codes as $error_code ) {
				if ( $error_code == 'invalid_username' || $error_code == 'incorrect_password' ) {
					$errors->remove( 'invalid_username' );
					$errors->remove( 'incorrect_password' );
					$errors->add( 'invalid_username_or_incorrect_password', '<b>' . __( 'Error:', 'wpmastertoolkit' ) . '</b> ' . __( 'Invalid username/email or incorrect password.', 'wpmastertoolkit' ) );
				}
			}
		}

		return $errors;
	}

	/**
	 * Hide login form
	 * 
	 * @since   1.5.0
	 */
	public function maybe_hide_login_form() {
		global $wpmastertoolkit_limit_login;

		$within_lockout_period = $wpmastertoolkit_limit_login['within_lockout_period'] ?? '';
        if ( $within_lockout_period ) {
			?>
				<script>
					document.addEventListener( "DOMContentLoaded", function(event) {
						var loginForm = document.getElementById("loginform");
						loginForm.remove();
					});
            	</script>
				<style type="text/css">
					body.login {
						background:#000;
					}
					#login h1,
					#loginform,
					#login #nav,
					#backtoblog,
					.language-switcher { 
						display: none; 
					}
					@media screen and (max-height: 550px) {
						#login {
							padding: 80px 0 20px !important;
						}
					}
				</style>
			<?php
		} else {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $page_was_reloaded = 1 == sanitize_text_field( wp_unslash( $_GET['rl'] ?? '' ) ) ? true : false;
			$fail_count        = $wpmastertoolkit_limit_login['fail_count'] ?? false;
			$fails_allowed     = $wpmastertoolkit_limit_login['login_fails_allowed'] ?? '3';

			if ( false !== $fail_count ) {
				if (
					( $fails_allowed - 1 ) == intval( $fail_count ) 
                    || ( 2 * $fails_allowed - 1 ) == intval( $fail_count ) 
                    || ( 3 * $fails_allowed - 1 ) == intval( $fail_count ) 
                    || ( 4 * $fails_allowed - 1 ) == intval( $fail_count ) 
                    || ( 5 * $fails_allowed - 1 ) == intval( $fail_count ) 
                    || ( 6 * $fails_allowed - 1 ) == intval( $fail_count ) 
				) {
					if ( ! $page_was_reloaded ) {
                        ?>
                        <script>
                            let url = window.location.href;
                            if ( url.indexOf('?') > -1 ){
                               url += '&rl=1'
                            } else {
                               url += '?rl=1'
                            }
                            location.replace( url );
                        </script>
                        <?php
                    }
				}
			}
		}
	}

	/**
	 * Log failed login
	 * 
	 * @since   1.5.0
	 */
	public function log_failed_login( $username ) {
		global $wpdb, $wpmastertoolkit_limit_login;

		$table_name              = $this->get_table_name();
		$ip_address              = $wpmastertoolkit_limit_login['ip_address'] ?? '';
		$ip_address_log          = $wpmastertoolkit_limit_login['ip_address_log'] ?? array();
		$fails_allowed           = $wpmastertoolkit_limit_login['login_fails_allowed'] ?? '3';
		$lockout_maxcount        = $wpmastertoolkit_limit_login['login_lockout_maxcount'] ?? '3';
		$request_uri             = $wpmastertoolkit_limit_login['request_uri'] ?? '';
		$default_lockout_period  = $wpmastertoolkit_limit_login['default_lockout_period'] ?? '';
		$extended_lockout_period = $wpmastertoolkit_limit_login['extended_lockout_period'] ?? '';
        $result_count            = count( $ip_address_log );
		$fail_count              = intval( $ip_address_log[0]['fail_count'] ?? 0 );
		$lockout_count           = intval( $ip_address_log[0]['lockout_count'] ?? 0 );
		$last_fail_on            = intval( $ip_address_log[0]['unixtime'] ?? 0 );
		$new_fail_count          = 1;
		$new_lockout_count       = 0;

		if ( 0 < $result_count ) {
            $new_fail_count    = $fail_count + 1;
            $new_lockout_count = floor( ( $fail_count + 1 ) / $fails_allowed );
        }

		$data        = array(
            'ip_address'    => $ip_address,
            'username'      => $username,
            'fail_count'    => $new_fail_count,
            'lockout_count' => $new_lockout_count,
            'request_uri'   => $request_uri,
            'unixtime'      => time(),
        );
		$data_format = array(
            '%s', // string
            '%s', // string
            '%d', // integer
            '%d', // integer
            '%s', // string
            '%d', // integer
            '%s', // string
        );

		if ( 0 == $result_count ) {
			//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->insert(
                $table_name,
                $data,
                $data_format
            );
        } else {
			$where        = array( 'ip_address' => $ip_address );
            $where_format = array( '%s' );

            if ( 0 == $fail_count % $fails_allowed ) {
                if ( $lockout_count >= $lockout_maxcount ) {
                    $wpmastertoolkit_limit_login['extended_lockout'] = true;
                    $lockout_period                                  = $extended_lockout_period;
                } else {
                    $wpmastertoolkit_limit_login['extended_lockout'] = false;
                    $lockout_period                                  = $default_lockout_period;
                }

				$wpmastertoolkit_limit_login['lockout_period'] = $lockout_period;

				if ( ( time() - $last_fail_on ) > $lockout_period ) {
                    if ( $lockout_count < $lockout_maxcount ) {
						//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
						$wpdb->update(
                            $table_name,
                            $data,
                            $where,
                            $data_format,
                            $where_format
                        );
					}
				}
			} else {
				//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
                    $table_name,
                    $data,
                    $where,
                    $data_format,
                    $where_format
                );
			}
		}
	}

	/**
	 * Clear failed login attempts log after successful login
	 * 
	 * @since   1.5.0
	 */
	public function clear_failed_login_log() {
		global $wpdb, $wpmastertoolkit_limit_login;

		$table_name   = $this->get_table_name();
		$ip_address   = $wpmastertoolkit_limit_login['ip_address'] ?? '';
		$where        = array( 'ip_address' => $ip_address );
        $where_format = array( '%s' );

		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
            $table_name,
            $where,
            $where_format
        );
	}

	/**
     * Run on option activation.
     * 
     * @since   1.5.0
     */
    public static function activate(){
		$this_class = new WPMastertoolkit_Limit_Login_Attempts();
		$this_class->create_table();
    }

	/**
     * Add a submenu
     * 
     * @since   1.5.0
     */
    public function add_submenu(){
        WPMastertoolkit_Settings::add_submenu_page(
            'wp-mastertoolkit-settings',
            $this->header_title,
            $this->header_title,
            'manage_options',
            'wp-mastertoolkit-settings-limit-login-attempts',
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
        $submenu_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/limit-login-attempts.asset.php' );
        wp_enqueue_style( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/limit-login-attempts.css', array(), $submenu_assets['version'], 'all' );
        wp_enqueue_script( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/limit-login-attempts.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );

        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
        $this->submenu_content();
        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/footer.php';
    }

	/**
     * Save the submenu option
     * 
     * @since   1.4.0
     */
    public function save_submenu() {
		$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );

		if ( wp_verify_nonce( $nonce, $this->nonce_action ) ) {

			if ( isset( $_POST['delete'] ) ) {
				$this->delete_attempt( $_POST );
			} else {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$new_settings = $this->sanitize_settings( wp_unslash( $_POST[ $this->option_id ] ?? array() ) );
				$this->save_settings( $new_settings );
			}

            wp_safe_redirect( sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) );
			exit;
		}
    }

	/**
     * sanitize_settings
     * 
     * @since   1.4.0
     * @return array
     */
    public function sanitize_settings( $new_settings ){
        $this->default_settings = $this->get_default_settings();
        $sanitized_settings     = array();

        foreach ( $this->default_settings as $settings_key => $settings_value ) {
			$sanitized_settings[ $settings_key ] = sanitize_text_field( $new_settings[ $settings_key ] ?? $settings_value );
        }

        return $sanitized_settings;
    }

	/**
     * get_settings
     *
     * @since   1.5.0
     * @return void
     */
    private function get_settings(){
        $this->default_settings = $this->get_default_settings();
        return get_option( $this->option_id, $this->default_settings );
    }

	/**
     * Save settings
     * 
     * @since   1.5.0
     */
    private function save_settings( $new_settings ) {
		update_option( $this->option_id, $new_settings );
    }

	/**
     * get_default_settings
     *
     * @since   1.5.0
     * @return array
     */
    private function get_default_settings(){
        if( $this->default_settings !== null ) return $this->default_settings;

        return array(
			'fails_allowed'    => '3',
			'lockout_maxcount' => '3',
        );
    }

	/**
     * Add the submenu content
     * 
     * @since   1.5.0
     * @return void
     */
    private function submenu_content() {
		global $wpdb;

        $this->settings   = $this->get_settings();
		$fails_allowed    = $this->settings['fails_allowed'] ?? '3';
		$lockout_maxcount = $this->settings['lockout_maxcount'] ?? '3';
		$date_format      = get_option( 'date_format', 'Y-m-d' );
		$time_format      = get_option( 'time_format', 'H:i' );

		if ( $this->is_table_exist() ) {
			$limit   = 10;
			//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$entries = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i ORDER BY unixtime DESC LIMIT %d", $this->get_table_name(), $limit ), ARRAY_A );
		} else {
			$entries = array();
		}

        ?>
            <div class="wp-mastertoolkit__section">
                <div class="wp-mastertoolkit__section__desc">
					<?php esc_html_e( "Prevent brute force attacks by limiting the number of failed login attempts allowed per IP address.", 'wpmastertoolkit'); ?>
				</div>
                <div class="wp-mastertoolkit__section__body">

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Login attempts', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
							<div class="wp-mastertoolkit__input-text flex">
								<input type="number" name="<?php echo esc_attr( $this->option_id . '[fails_allowed]' ); ?>" value="<?php echo esc_attr( $fails_allowed ); ?>" min="1" style="width: 54px;">
								<?php esc_html_e( 'failed login attempts allowed before 15 minutes lockout.', 'wpmastertoolkit' ); ?>
							</div>
                        </div>
                    </div>

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Lockout', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
							<div class="wp-mastertoolkit__input-text flex">
								<input type="number" name="<?php echo esc_attr( $this->option_id . '[lockout_maxcount]' ); ?>" value="<?php echo esc_attr( $lockout_maxcount ); ?>" min="1" style="width: 54px;">
								<?php esc_html_e( 'lockout(s) will block further login attempts for 24 hours.', 'wpmastertoolkit' ); ?>
							</div>
                        </div>
                    </div>

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Failed login attempts', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">

							<table id="login-attempts-log" class="widefat striped">
								<thead>
									<tr>
										<th><?php esc_html_e( 'IP Address', 'wpmastertoolkit' ); ?></th>
										<th><?php esc_html_e( 'Last Username', 'wpmastertoolkit' ); ?></th>
										<th><?php esc_html_e( 'Attempts', 'wpmastertoolkit' ); ?></th>
										<th><?php esc_html_e( 'Lockouts', 'wpmastertoolkit' ); ?></th>
										<th><?php esc_html_e( 'Last Attempt On', 'wpmastertoolkit' ); ?></th>
										<th><?php esc_html_e( 'Actions', 'wpmastertoolkit' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach( $entries as $entry ) : ?>
										<tr>
											<td><?php echo esc_html( $entry['ip_address'] ?? '' ); ?></td>
											<td><?php echo esc_html( $entry['username'] ?? '' ); ?></td>
											<td><?php echo esc_html( $entry['fail_count'] ?? '' ); ?></td>
											<td><?php echo esc_html( $entry['lockout_count'] ?? '' ); ?></td>
											<td><?php echo esc_html( wp_date( $date_format . ' ' . $time_format, $entry['unixtime'] ?? null ) ); ?></td>
											<td><button onclick="return confirm('<?php esc_html_e( 'Are you sure?', 'wpmastertoolkit' ); ?>')" class="button button-link-delete" type="submit" name="delete" value="<?php echo esc_attr( $entry['ip_address'] ); ?>"><?php esc_html_e( 'Delete', 'wpmastertoolkit' ); ?></button></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
							
                        </div>
                    </div>

                </div>
            </div>
        <?php
    }

	/**
	 * Delete attempt
	 * 
	 * @since 1.11.0
	 */
	private function delete_attempt( $data ) {
		global $wpdb;

		$table_name   = $this->get_table_name();
		$ip_address   = $data['delete'] ?? '';
		$where        = array( 'ip_address' => $ip_address );
        $where_format = array( '%s' );

		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
            $table_name,
            $where,
            $where_format
        );
	}

	/**
	 * Check if table exist
	 * 
	 * @since 1.5.0
	 */
	private function is_table_exist() {
		global $wpdb;
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $this->get_table_name() ) ) ) === $this->get_table_name();
	}

	/**
	 * Get table name
	 * 
	 * @since 1.5.0
	 */
	private function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . $this->option_id;
	}

	/**
	 * Create table
	 * 
	 * @since 1.5.0
	 */
	private function create_table() {
		global $wpdb;

        if ( ! empty( $wpdb->charset ) ) {
            $charset_collation_sql = "DEFAULT CHARACTER SET $wpdb->charset";
        }

        if ( ! empty( $wpdb->collate ) ) {
            $charset_collation_sql .= " COLLATE $wpdb->collate";
        }

		$table_name = $this->get_table_name();

		// Drop table if already exists
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( $wpdb->prepare( "DROP TABLE IF EXISTS %i", $table_name ) );

		// Create database table.
		$sql = 
        "CREATE TABLE {$table_name} (
            id int(6) unsigned NOT NULL auto_increment,
            ip_address varchar(40) NOT NULL DEFAULT '',
            username varchar(24) NOT NULL DEFAULT '',
            fail_count int(10) NOT NULL DEFAULT '0',
            lockout_count int(10) NOT NULL DEFAULT '0',
            request_uri varchar(24) NOT NULL DEFAULT '',
            unixtime int(10) NOT NULL DEFAULT '0',
            UNIQUE (ip_address),
            PRIMARY KEY (id)
        ) {$charset_collation_sql}";

		require_once ABSPATH . '/wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Format seconds to period
	 * 
	 * @since 1.5.0
	 */
	private function formate_seconds_to_period( $seconds ) {
		$result       = '';
		$period_start = new DateTime('@0');
        $period_end   = new DateTime("@{$seconds}");

		if ( $seconds < 60 ) {
			$result = esc_html( sprintf(
				/* translators: %s: Seconds */
				__( '%s seconds', 'wpmastertoolkit' ), 
				$seconds 
			) );
		} elseif ( $seconds < 3600 ) {
            $result = $period_start->diff( $period_end )->format( 
				/* translators: %i: Minutes, %s: Seconds */
				esc_html__( '%i minutes and %s seconds', 'wpmastertoolkit' ) 
			);
		} elseif ( $seconds < 86400 ) {
            $result = $period_start->diff( $period_end )->format( 
				/* translators: %1$h: Hours, %2$i: Minutes, %3$s: Seconds */
				esc_html__( '%1$h hours, %2$i minutes and %3$s seconds', 'wpmastertoolkit' ) 
			);
		} else {
			$result = $period_start->diff( $period_end )->format( 
				/* translators: %1$a: Days, %2$h: Hours, %3$i: Minutes, %4$s: Seconds */
				esc_html__( '%1$a days, %2$h hours, %3$i minutes and %4$s seconds', 'wpmastertoolkit' ) 
			);
		}
		
		return $result;
	}
}
