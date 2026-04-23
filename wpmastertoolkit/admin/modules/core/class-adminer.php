<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Adminer
 * Description: A full-featured database management tool.
 * @since 1.11.0
 */
class WPMastertoolkit_Adminer {

	private $option_id;
	private $header_title;
	private $nonce_action;
	private $cron_name;
	private $settings;
    private $default_settings;
	private $folder_path;
	private $folder_url;

	/**
     * Invoke the hooks
     * 
	 * @since 1.11.0
     */
    public function __construct() {
        $this->option_id    = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_adminer';
		$this->nonce_action = $this->option_id . '_action';
		$this->cron_name    = $this->option_id . '_cron';

        add_action( 'init', array( $this, 'class_init' ) );
        add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
        add_action( 'admin_init', array( $this, 'save_submenu' ) );
		add_filter( 'cron_schedules', array( $this, 'crons_registrations' ) );
		add_action( 'wp', array( $this, 'cron_events' ) );
		add_action( $this->cron_name . '_hook', array( $this, 'cron_scripts' ) );

		add_filter( 'wpmastertoolkit/folders', array( $this, 'create_folders' ) );
    }

	/**
     * create_folders
     *
     * @param  mixed $folders
     * @return void
     */
    public function create_folders( $folders ) {
        $folders['wpmastertoolkit']['adminer'] = array();
        return $folders;
    }

	/**
     * Initialize the class
	 * 
	 * @since 1.11.0
     */
    public function class_init() {
        $this->header_title = esc_html__( 'Adminer', 'wpmastertoolkit' );
    }

	/**
     * Add a submenu
	 * 
	 * @since 1.11.0
     */
    public function add_submenu(){

        WPMastertoolkit_Settings::add_submenu_page(
            'wp-mastertoolkit-settings',
            $this->header_title,
            $this->header_title,
            'manage_options',
            'wp-mastertoolkit-settings-adminer',
            array( $this, 'render_submenu'),
            null
        );
    }

	/**
     * Render the submenu
     * 
	 * @since 1.11.0
     */
    public function render_submenu() {

		$submenu_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/adminer.asset.php' );
		wp_enqueue_style( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/adminer.css', array(), $submenu_assets['version'], 'all' );
		wp_enqueue_script( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/adminer.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );
		wp_localize_script( 'WPMastertoolkit_submenu', 'wpmastertoolkit_adminer', array(
			'page_url' => get_admin_url( null, 'admin.php?page=wp-mastertoolkit-settings-adminer' ),
		) );

		include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
		$this->submenu_content();
		include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/footer.php';
    }

	/**
	 * Save the submenu
	 * 
	 * @since 1.11.0
	 */
	public function save_submenu() {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), $this->nonce_action ) ) {
			return;
		}

		$submit = sanitize_text_field( wp_unslash( $_POST['submit'] ?? '' ) );

		if ( 'adminer' == $submit ) {

			$this->delete_old_files();

			$tmp_file = download_url( 'https://www.adminer.org/latest-mysql-en.php', 30 );

			if ( is_wp_error( $tmp_file ) ) {
				wp_safe_redirect( sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) );
				exit;
			}

			$file_content = file_get_contents( $tmp_file );
			wp_delete_file( $tmp_file );

			if ( empty( $file_content ) ) {
				wp_safe_redirect( sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) );
				exit;
			}
			$folder_path  = $this->get_folder_path();
			$file_hash    = bin2hex( random_bytes( 16 ) );

			// Save the raw Adminer engine file (dot prefix to restrict direct access)
			$engine_filename  = '.wpmastertoolkit-engine-' . $file_hash . '.php';
			$engine_path      = "$folder_path/$engine_filename";

			// Save the wrapper file (secure entry point)
			$wrapper_filename = 'wpmastertoolkit-adminer-' . $file_hash . '.php';
			$wrapper_path     = "$folder_path/$wrapper_filename";

			$engine_created = file_put_contents( $engine_path, $file_content );

			if ( $engine_created ) {
				$settings  = $this->get_settings();
				$lifetime  = $settings['lifetime']['value'] ?? '3600';

				$wrapper_content = $this->generate_wrapper_content( $engine_filename, time(), $lifetime );
				$wrapper_created = file_put_contents( $wrapper_path, $wrapper_content );

				$this->create_htaccess();

				if ( $wrapper_created ) {
					$settings                 = $this->get_settings();
					$settings['creationtime'] = time();
					$settings['file_hash']    = $file_hash;

					$new_settings = $this->sanitize_settings( $settings );
					$this->save_settings( $new_settings );
				}
			}

			wp_safe_redirect( sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) );
			exit;

		} elseif ( 'connect' == $submit ) {

			$settings  = $this->get_settings();
			$file_hash = $settings['file_hash'] ?? '';

			if ( empty( $file_hash ) || ! preg_match( '/^[a-f0-9]{32}$/', $file_hash ) ) {
				wp_safe_redirect( sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) );
				exit;
			}

			$folder_path      = $this->get_folder_path();
			$folder_url       = $this->get_folder_url();
			$wrapper_filename = 'wpmastertoolkit-adminer-' . $file_hash . '.php';
			$wrapper_path     = "$folder_path/$wrapper_filename";
			$wrapper_url      = "$folder_url/$wrapper_filename";

			if ( ! file_exists( $wrapper_path ) ) {
				wp_safe_redirect( sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) );
				exit;
			}

			// Store credentials in session (server-side only, never exposed in HTML)
			if ( session_status() === PHP_SESSION_NONE ) {
				session_start();
			}

			$_SESSION['wpmastertoolkit_adminer_credentials'] = array(
				'server'   => DB_HOST,
				'username' => DB_USER,
				'password' => DB_PASSWORD,
				'db'       => DB_NAME,
				'expires'  => time() + 30,
				'ip'       => sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ),
			);

			// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
			wp_redirect( $wrapper_url );
			exit;

		} elseif ( 'delete' == $submit ) {
			$this->delete_old_files();

			wp_safe_redirect( sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) );
			exit;
		} else {
			//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$post_settings = wp_unslash( $_POST[$this->option_id] ?? array() );

			// Preserve server-managed values from DB (never trust from POST)
			$existing_settings              = $this->get_settings();
			$post_settings['creationtime']  = $existing_settings['creationtime'] ?? '0';
			$post_settings['file_hash']     = $existing_settings['file_hash'] ?? '';

			$new_settings = $this->sanitize_settings( $post_settings );
            
            $this->save_settings( $new_settings );
            wp_safe_redirect( sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) );
			exit;
		}
	}

	/**
     * Register the cron events
	 * 
	 * @since 1.11.0
     */
    public function crons_registrations( $schedules ) {
        $schedules[ $this->cron_name ] = array(
            'interval' => MINUTE_IN_SECONDS * 5,
            'display'  => $this->header_title,
        );

		return $schedules;
    }

	/**
	 * Start the next cron event
	 * 
	 * @since 1.11.0
	 */
    public function cron_events() {
        if ( ! wp_next_scheduled( $this->cron_name . '_hook', array( $this->cron_name ) ) ) {
            wp_schedule_event( time(), $this->cron_name, $this->cron_name . '_hook', array( $this->cron_name ) );
        }
    }

	/**
     * Run the cron scripts
	 * 
	 * @since 1.11.0
     */
    public function cron_scripts() {
		$settings     = $this->get_settings();
		$lifetime     = $settings['lifetime']['value'] ?? '';
		$creationtime = $settings['creationtime'] ?? '0';

		if ( $creationtime ) {
			$deletiontime = $creationtime + $lifetime;

			if ( time() > $deletiontime ) {
				$this->delete_old_files();
			}
		}

        exit;
    }

	/**
	 * Delete old files
	 * 
	 * @since 1.11.0
	 */
	private function delete_old_files() {
		$folder_path = $this->get_folder_path();
		$all_files   = array_merge(
			glob( "$folder_path/*" ) ?: array(),
			glob( "$folder_path/.*" ) ?: array()
		);

		foreach ( $all_files as $file ) {
			$file_name = basename( $file );

			if ( in_array( $file_name, array( '.', '..', 'index.php', '.htaccess' ), true ) ) {
				continue;
			}

			if ( is_file( $file ) ) {
				wp_delete_file( $file );
			}
		}

		$settings                 = $this->get_settings();
		$settings['creationtime'] = '0';
		$settings['file_hash']    = '';

		$new_settings = $this->sanitize_settings( $settings );
		$this->save_settings( $new_settings );
	}

	/**
	 * Get folder path
	 * 
	 * @since 1.11.0
	 */
	private function get_folder_path() {
		if ( empty( $this->folder_path ) ) {
			$upload_folder     = wpmastertoolkit_folders();
			$this->folder_path = "$upload_folder/adminer";
		}

		return $this->folder_path;
	}

	/**
	 * Get folder url
	 * 
	 * @since 1.11.0
	 */
	private function get_folder_url() {
		if ( empty( $this->folder_url ) ) {
			$upload_url = wpmastertoolkit_get_folder_url();
			$this->folder_url = "$upload_url/adminer";
		}

		return $this->folder_url;
	}

	/**
     * sanitize_settings
     * 
	 * @since 1.11.0
     */
    private function sanitize_settings( $new_settings ){
        $this->default_settings = $this->get_default_settings();
        $sanitized_settings     = array();

        foreach ( $this->default_settings as $settings_key => $settings_value ) {
            switch ($settings_key) {
				case 'lifetime':
					$allowed_lifetimes = array_keys( $this->default_settings['lifetime']['options'] );
					$lifetime_value    = sanitize_text_field( $new_settings[$settings_key]['value'] ?? $this->default_settings[$settings_key]['value'] );
					$sanitized_settings[$settings_key]['value'] = in_array( $lifetime_value, $allowed_lifetimes, true ) ? $lifetime_value : $this->default_settings[$settings_key]['value'];
				break;
				case 'creationtime':
				case 'file_hash':
					$sanitized_settings[$settings_key] = sanitize_text_field( $new_settings[$settings_key] ?? $settings_value );
				break;
            }
        }

        return $sanitized_settings;
    }

	/**
     * get_settings
	 * 
	 * @since 1.11.0
     */
    private function get_settings(){
        $this->default_settings = $this->get_default_settings();
        return get_option( $this->option_id, $this->default_settings );
    }

	/**
     * Save settings
	 * 
	 * @since 1.11.0
     */
    private function save_settings( $new_settings ) {
		update_option( $this->option_id, $new_settings );
    }

	/**
     * get_default_settings
     *
	 * @since 1.11.0
     */
    private function get_default_settings(){
        if ( $this->default_settings !== null ) return $this->default_settings;

        return array(
			'lifetime' => array(
                'value'   => '3600',
                'options' => array(
                    '1800'  => __( '30 Min', 'wpmastertoolkit' ),
                    '3600'  => __( '1 Hour', 'wpmastertoolkit' ),
                    '7200'  => __( '2 Hours', 'wpmastertoolkit' ),
                    '10800' => __( '3 Hours', 'wpmastertoolkit' ),
                    '14400' => __( '4 Hours', 'wpmastertoolkit' ),
                ),
            ),
			'creationtime' => '0',
			'file_hash'    => '',
        );
    }

	/**
	 * Generate the secure wrapper file content
	 *
	 * @since 1.11.0
	 */
	private function generate_wrapper_content( $engine_filename, $creationtime, $lifetime ) {
		$template = <<<'WRAPPER'
<?php
/**
 * WP MasterToolkit - Adminer Secure Wrapper
 * Auto-generated file. Do not edit.
 */

// Phase 0: Self-deletion if file lifetime has expired
$wpmastertoolkit_creationtime = {{CREATIONTIME}};
$wpmastertoolkit_lifetime     = {{LIFETIME}};
if ( time() > ( $wpmastertoolkit_creationtime + $wpmastertoolkit_lifetime ) ) {
	$wpmastertoolkit_engine = __DIR__ . '/{{ENGINE_FILENAME}}';
	if ( is_file( $wpmastertoolkit_engine ) ) {
		@unlink( $wpmastertoolkit_engine );
	}
	@unlink( __FILE__ );
	http_response_code( 410 );
	exit( 'This Adminer instance has expired and has been deleted.' );
}

if ( session_status() === PHP_SESSION_NONE ) {
	session_start();
}

$wpmastertoolkit_auth_key   = 'wpmastertoolkit_adminer_credentials';
$wpmastertoolkit_access_key = 'wpmastertoolkit_adminer_active';

// Phase 1: Auto-login using credentials stored in session by WordPress admin
if ( ! empty( $_SESSION[ $wpmastertoolkit_auth_key ] ) ) {
	$wpmastertoolkit_auth = $_SESSION[ $wpmastertoolkit_auth_key ];

	// Validate expiry (30-second window)
	if ( ! isset( $wpmastertoolkit_auth['expires'] ) || time() > $wpmastertoolkit_auth['expires'] ) {
		unset( $_SESSION[ $wpmastertoolkit_auth_key ] );
		http_response_code( 403 );
		exit( 'Session expired. Please reconnect from WordPress admin.' );
	}

	// Validate IP consistency
	$wpmastertoolkit_ip = $_SERVER['REMOTE_ADDR'] ?? '';
	if ( isset( $wpmastertoolkit_auth['ip'] ) && $wpmastertoolkit_auth['ip'] !== $wpmastertoolkit_ip ) {
		unset( $_SESSION[ $wpmastertoolkit_auth_key ] );
		http_response_code( 403 );
		exit( 'IP mismatch. Please reconnect from WordPress admin.' );
	}

	// Prepare Adminer auto-login via POST simulation
	$_POST['auth'] = array(
		'driver'   => 'server',
		'server'   => $wpmastertoolkit_auth['server'],
		'username' => $wpmastertoolkit_auth['username'],
		'password' => $wpmastertoolkit_auth['password'],
		'db'       => $wpmastertoolkit_auth['db'],
	);

	// Set matching CSRF token for Adminer internal validation
	$wpmastertoolkit_csrf        = bin2hex( random_bytes( 16 ) );
	$_SESSION['token']           = $wpmastertoolkit_csrf;
	$_POST['token']              = $wpmastertoolkit_csrf;
	$_SERVER['REQUEST_METHOD']   = 'POST';

	// Mark session as having active Adminer access
	$_SESSION[ $wpmastertoolkit_access_key ] = time();

	// Clear credentials from session (one-time use)
	unset( $_SESSION[ $wpmastertoolkit_auth_key ] );
}

// Phase 2: Access control - session timeout check (max 4 hours)
if ( ! empty( $_SESSION[ $wpmastertoolkit_access_key ] ) ) {
	if ( time() - (int) $_SESSION[ $wpmastertoolkit_access_key ] > 14400 ) {
		unset( $_SESSION[ $wpmastertoolkit_access_key ] );
	}
}

// Phase 3: Deny access if no valid session
if ( empty( $_POST['auth'] ) && empty( $_SESSION[ $wpmastertoolkit_access_key ] ) ) {
	http_response_code( 403 );
	exit( 'Access denied. Please connect from WordPress admin.' );
}

// Phase 4: Include the Adminer engine
require_once __DIR__ . '/{{ENGINE_FILENAME}}';
WRAPPER;

		return str_replace(
			array( '{{ENGINE_FILENAME}}', '{{CREATIONTIME}}', '{{LIFETIME}}' ),
			array( $engine_filename, (string) $creationtime, (string) $lifetime ),
			$template
		);
	}

	/**
	 * Create .htaccess to protect engine file from direct access
	 *
	 * @since 1.11.0
	 */
	private function create_htaccess() {
		$folder_path   = $this->get_folder_path();
		$htaccess_path = "$folder_path/.htaccess";

		$content  = "# WP MasterToolkit - Protect Adminer engine files from direct access\n";
		$content .= "<FilesMatch \"^\\.wpmastertoolkit-engine-\">\n";
		$content .= "    <IfModule mod_authz_core.c>\n";
		$content .= "        Require all denied\n";
		$content .= "    </IfModule>\n";
		$content .= "    <IfModule !mod_authz_core.c>\n";
		$content .= "        Order allow,deny\n";
		$content .= "        Deny from all\n";
		$content .= "    </IfModule>\n";
		$content .= "</FilesMatch>\n";

		file_put_contents( $htaccess_path, $content );
	}

	/**
     * Add the submenu content
     *
	 * @since 1.11.0
     */
    private function submenu_content() {
		$this->settings   = $this->get_settings();
        $default_settings = $this->get_default_settings();
		$lifetime         = $this->settings['lifetime']['value'] ?? '';
        $options          = $default_settings['lifetime']['options'] ?? array();
		$creationtime     = $this->settings['creationtime'] ?? '0';
		$file_hash        = $this->settings['file_hash'] ?? '';
		$deletiontime     = $creationtime + $lifetime;
		$time_formate     = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

        ?>
            <div class="wp-mastertoolkit__section">
                <div class="wp-mastertoolkit__section__desc"><?php esc_html_e( 'A full-featured database management tool.', 'wpmastertoolkit' ); ?></div>
                <div class="wp-mastertoolkit__section__body">

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'File lifetime', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
                            <div class="wp-mastertoolkit__select">
                                <select name="<?php echo esc_attr( $this->option_id . '[lifetime][value]' ); ?>">
                                    <?php foreach ( $options as $key => $name ) : ?>
                                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $lifetime, $key ); ?>><?php echo esc_html( $name ); ?></option>
                                    <?php endforeach; ?>
                                </select>
								<p><?php esc_html_e( 'Choose the lifetime of the database file', 'wpmastertoolkit' ); ?></p>
                            </div>
                        </div>
                    </div>

					<?php if ( $creationtime && $file_hash ): ?>
					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'File Creation', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
                            <p><?php echo esc_html( wp_date( $time_formate, $creationtime ) ); ?></p>
                        </div>
                    </div>

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'File Deletion', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
                            <p><?php echo esc_html( wp_date( $time_formate, $deletiontime ) ); ?></p>
							<div class="wp-mastertoolkit__button">
								<button class="secondary" type="submit" name="submit" value="delete"><?php esc_html_e( 'Delete Now', 'wpmastertoolkit' ); ?></button>
							</div>
                        </div>
                    </div>

					<div class="wp-mastertoolkit__section__body__item">
						<div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Connect to database', 'wpmastertoolkit' ); ?></div>
						<div class="wp-mastertoolkit__section__body__item__content">
							<div class="wp-mastertoolkit__button">
								<button type="submit" name="submit" value="connect" formtarget="_blank"><?php esc_html_e( 'Connect', 'wpmastertoolkit' ); ?></button>
							</div>
							<div class="description"><?php esc_html_e( 'Securely connect to your database via Adminer', 'wpmastertoolkit' ); ?></div>
						</div>
					</div>
					<?php endif; ?>

					<div class="wp-mastertoolkit__section__body__item">
						<div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Download adminer', 'wpmastertoolkit' ); ?></div>
						<div class="wp-mastertoolkit__section__body__item__content">
							<div class="wp-mastertoolkit__button">
								<button type="submit" name="submit" value="adminer"><?php esc_html_e( 'Download', 'wpmastertoolkit' ); ?></button>
							</div>
						</div>
					</div>
					
                </div>
            </div>
        <?php
    }

    /**
     * deactivate
     *
     * @since 1.11.0
     */
    public static function deactivate(){
		$this_class = new WPMastertoolkit_Adminer();
		$this_class->delete_old_files();
    }
}
