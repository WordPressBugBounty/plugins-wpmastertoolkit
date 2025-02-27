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
	private $disable_form;
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

		if ( ! headers_sent() && ! session_id() ) {
			session_start();
		}
    }

	/**
     * Add a submenu
	 * 
	 * @since 1.11.0
     */
    public function add_submenu(){

        add_submenu_page(
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

		//phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status           = sanitize_text_field( wp_unslash( $_GET['wpmastertoolkit_status'] ?? '' ) );
		//phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$adminer_url      = sanitize_url( wp_unslash( $_GET['wpmastertoolkit_url'] ?? '' ) );
		$redirection_form = false;

		if ( 'adminer' == $status && ! empty( $adminer_url ) ) {
			$this->disable_form = true;
			$redirection_form   = true;
		}

		$submenu_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/adminer.asset.php' );
		wp_enqueue_style( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/adminer.css', array(), $submenu_assets['version'], 'all' );
		wp_enqueue_script( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/adminer.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );		
		wp_localize_script( 'WPMastertoolkit_submenu', 'wpmastertoolkit_adminer', array(
			'page_url' => get_admin_url( null, 'admin.php?page=wp-mastertoolkit-settings-adminer' ),
		) );

		include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
		if ( $redirection_form ) {
			$this->redirection_form();
		} else {
			$this->submenu_content();
		}
		include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/footer.php';
    }

	/**
	 * Save the submenu
	 * 
	 * @since 1.11.0
	 */
	public function save_submenu() {

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), $this->nonce_action ) ) {
			return;
		}

		$submit = sanitize_text_field( wp_unslash( $_POST['submit'] ?? '' ) );

		if ( 'adminer' == $submit ) {

			$this->delete_old_files();

			// $file_content = file_get_contents( 'https://github.com/vrana/adminer/releases/download/v4.8.1/adminer-4.8.1-mysql.php' );
			$file_content = wp_remote_retrieve_body( wp_remote_get( 'https://github.com/vrana/adminer/releases/download/v4.8.1/adminer-4.8.1-mysql.php' ) );
			$folder_path  = $this->get_folder_path();
			$folder_url   = $this->get_folder_url();
			$file_name    = 'wpmastertoolkit-adminer-' . md5( time() ) . '.php' ;
			$file_path    = "$folder_path/$file_name";
			$file_url     = "$folder_url/$file_name";

			$content      = '<?php session_start();';
			$content     .= 'if ( empty( $_GET["username"] ) && empty( $_GET["file"] ) ) {';
			$content     .= '$wpmastertoolkit_user_token = $_POST["token"] ?? ""; $wpmastertoolkit_saved_token = $_SESSION["wpmastertoolkit_settings_adminer"]["token"] ?? "";';
			$content     .= 'if ( empty( $wpmastertoolkit_user_token ) || empty( $wpmastertoolkit_saved_token ) || $wpmastertoolkit_user_token != $wpmastertoolkit_saved_token ) {return;} }';
			$file_content = preg_replace( '/^<\?php/', '', $file_content );
			$file_content = $content . $file_content;
			$file_created = file_put_contents( $file_path, $file_content );

			if ( $file_created ) {
				$settings = $this->get_settings();
				$settings['creationtime'] = time();

				$new_settings = $this->sanitize_settings( $settings );
            	$this->save_settings( $new_settings );

				$new_url = add_query_arg(
					array(
						'wpmastertoolkit_status' => 'adminer',
						'wpmastertoolkit_url'    => $file_url,
					),
					sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ),
				);

				wp_safe_redirect( $new_url );
				exit;
			}

			wp_safe_redirect( sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) );
			exit;

		} elseif ( 'delete' == $submit ) {
			$this->delete_old_files();

			wp_safe_redirect( sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) );
			exit;
		} else {
			//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$new_settings = $this->sanitize_settings( $_POST[$this->option_id] ?? array() );
            
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
		$all_files   = glob( "$folder_path/*" );

		foreach ( $all_files as $file ) {
			$file_name = basename( $file );

			if ( 'index.php' == $file_name ) {
				continue;
			}

			if ( is_file( $file ) ) {
				wp_delete_file( $file );
			}
		}

		$settings = $this->get_settings();
		$settings['creationtime'] = '0';

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
					$sanitized_settings[$settings_key]['value'] = sanitize_text_field( $new_settings[$settings_key]['value'] ??$this->default_settings[$settings_key]['value'] );
				break;
				case 'creationtime':
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
        );
    }

	/**
	 * Redirection form
	 * 
	 * @since 1.11.0
	 */
	private function redirection_form() {
		//phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$adminer_url    = sanitize_url( wp_unslash( $_GET['wpmastertoolkit_url'] ?? '' ) );
		$token          = md5( time() );
		$db_host        = DB_HOST;
		$db_user        = DB_USER;
		$db_password    = DB_PASSWORD;
		$db_name        = DB_NAME;

		$_SESSION[$this->option_id]['token'] = $token;
		?>
			<div class="wp-mastertoolkit__section">
				<div class="wp-mastertoolkit__section__body">
					<form id="wpmastertoolkit_adminer_form" action="<?php echo esc_url( $adminer_url ); ?>" method="post" target="_blank">
						<input type="hidden" name="auth[driver]" value="server">
						<input type="hidden" name="auth[db]" value="<?php echo esc_attr( $db_name ); ?>">
						<input type="hidden" name="auth[username]" value="<?php echo esc_attr( $db_user ); ?>">
						<input type="hidden" name="auth[password]" value="<?php echo esc_attr( $db_password ); ?>">
						<input type="hidden" name="auth[server]" value="<?php echo esc_attr( $db_host ); ?>">
						<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
						<div class="wp-mastertoolkit__section__body__item">
							<div class="wp-mastertoolkit__section__body__item__content">
								<div class="wp-mastertoolkit__button">
									<button type="submit" value="login"><?php esc_html_e( 'Connect', 'wpmastertoolkit' ); ?></button>
								</div>
								<div class="description"><?php  esc_html_e( 'Connect to your database', 'wpmastertoolkit' ); ?></div>
							</div>
						</div>
					</form>
				</div>
			</div>
		<?php
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

					<?php if ( $creationtime ): ?>
					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'File Creation', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
                            <p><?php echo esc_html( wp_date( $time_formate, $creationtime ) ); ?></p>
                        </div>
						<input type="hidden" name="<?php echo esc_attr( $this->option_id . '[creationtime]' ); ?>" value="<?php echo esc_attr( $creationtime ); ?>">
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
