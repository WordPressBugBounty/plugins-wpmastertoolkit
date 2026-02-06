<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Advanced Debug Mode
 * Description: Enable WordPress debug mode with logging.
 * @since 2.14.0
 */
class WPMastertoolkit_Advanced_Debug_Mode {

    const MODULE_ID = 'Advanced Debug Mode';

    private $option_id;
    private $header_title;
    public $nonce_action;
    private $settings;
    private $default_settings;
    private $submenu_page_id;

    /**
     * Invoke the hooks
     */
    public function __construct() {

        $this->option_id       = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_advanced_debug_mode';
        $this->nonce_action    = $this->option_id . '_action';
        $this->submenu_page_id = 'wp-mastertoolkit-settings-advanced-debug-mode';

        add_action( 'init', array( $this, 'class_init' ) );
        add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
        add_action( 'admin_init', array( $this, 'save_submenu' ) );
        add_filter( 'wpmastertoolkit_nginx_code_snippets', array( $this, 'nginx_code_snippets' ) );
        add_action( 'wp_ajax_wpmastertoolkit_get_debug_log', array( $this, 'ajax_get_debug_log' ) );
        add_action( 'wp_ajax_wpmastertoolkit_delete_debug_log', array( $this, 'ajax_delete_debug_log' ) );
        add_filter( 'wpmastertoolkit/folders', array( $this, 'create_folders' ) );
    }

    /**
     * Initialize the class
     */
    public function class_init() {
        $this->header_title = esc_html__( 'Advanced Debug Mode', 'wpmastertoolkit' );
    }

    /**
     * activate
     *
     * @return void
     */
    public static function activate() {
        require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/class-wp-config.php';
        
        // Enable WP_DEBUG
        WPMastertoolkit_WP_Config::replace_or_add_constant( 'WP_DEBUG', true );
        
        // Enable WP_DEBUG_LOG
        WPMastertoolkit_WP_Config::replace_or_add_constant( 'WP_DEBUG_LOG', true );
        
        // Disable WP_DEBUG_DISPLAY
        WPMastertoolkit_WP_Config::replace_or_add_constant( 'WP_DEBUG_DISPLAY', false );
        
        // Initialize default settings
        $default_settings = array(
            'protect_logs'      => '0',
            'display_errors'    => '0',
            'custom_log_folder' => '0',
            'daily_logs'        => '0',
        );
        update_option( WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_advanced_debug_mode', $default_settings );
    }

    /**
     * deactivate
     *
     * @return void
     */
    public static function deactivate() {
        global $is_apache, $is_nginx;

        require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/class-wp-config.php';
        
        // Disable WP_DEBUG
        WPMastertoolkit_WP_Config::replace_or_add_constant( 'WP_DEBUG', false );
        
        // Disable WP_DEBUG_LOG
        WPMastertoolkit_WP_Config::replace_or_add_constant( 'WP_DEBUG_LOG', false );
        
        // Disable WP_DEBUG_DISPLAY
        WPMastertoolkit_WP_Config::replace_or_add_constant( 'WP_DEBUG_DISPLAY', false );

        // Remove htaccess protection if Apache
        if ( $is_apache ) {
            require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/class-htaccess.php';
            WPMastertoolkit_Htaccess::remove( self::MODULE_ID );
        }

        // Delete settings
        delete_option( WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_advanced_debug_mode' );
    }

    /**
     * create_folders
     *
     * @param  array $folders
     * @return array
     */
    public function create_folders( $folders ) {
        $folders['wpmastertoolkit']['debug-logs'] = array();
        return $folders;
    }

    /**
     * nginx_code_snippets
     *
     * @param  array $code_snippets
     * @return array
     */
    public function nginx_code_snippets( $code_snippets ) {
        global $is_nginx;

        if ( $is_nginx && wpmastertoolkit_is_pro() ) {
            $settings = $this->get_settings();
            $protect_logs = $settings['protect_logs'] ?? '0';

            if ( '1' === $protect_logs ) {
                $code_snippets[ self::MODULE_ID ] = self::get_raw_content_nginx();
            }
        }

        return $code_snippets;
    }

    /**
     * Content of the .htaccess file
     */
    private static function get_raw_content_htaccess() {
        $settings           = get_option( WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_advanced_debug_mode', array() );
        $daily_logs         = $settings['daily_logs'] ?? '0';
        $custom_log_folder  = $settings['custom_log_folder'] ?? '0';
        
        if ( wpmastertoolkit_is_pro() && '1' === $daily_logs && $custom_log_folder === '1' ) {
            $content = "<FilesMatch \"^debug-[0-9]{4}-[0-9]{2}-[0-9]{2}\\.log$\">\n";
            $content .= "\tOrder allow,deny\n";
            $content .= "\tDeny from all\n";
            $content .= "</FilesMatch>";
        } else {
            $log_path = self::get_debug_log_path();
            $log_filename = basename( $log_path );
            
            $content = "<Files " . $log_filename . ">\n";
            $content .= "\tOrder allow,deny\n";
            $content .= "\tDeny from all\n";
            $content .= "</Files>";
        }

        return trim( $content );
    }

    /**
     * Content of the nginx.conf file
     */
    private static function get_raw_content_nginx() {
        $settings           = get_option( WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_advanced_debug_mode', array() );
        $daily_logs         = $settings['daily_logs'] ?? '0';
        $custom_log_folder  = $settings['custom_log_folder'] ?? '0';
        
        // If daily logs is enabled, use regex pattern to match all dated log files
        if ( wpmastertoolkit_is_pro() && '1' === $daily_logs && $custom_log_folder === '1' ) {
            $base_path = str_replace( ABSPATH, '/', wpmastertoolkit_folders() ) . '/debug-logs';
            $base_path = str_replace( '\\', '/', $base_path );
            $escaped_path = str_replace( '/', '\\/', $base_path );
            
            $content  = "location ~* " . $escaped_path . "/debug-[0-9]{4}-[0-9]{2}-[0-9]{2}\\.log$ {";
            $content .= "\n\tdeny all;";
            $content .= "\n}";
        } else {
            $log_path = self::get_debug_log_path();
            // Convert absolute path to relative URL path
            $relative_path = str_replace( ABSPATH, '/', $log_path );
            $relative_path = str_replace( '\\', '/', $relative_path ); // Windows compatibility
            $escaped_path = preg_quote( $relative_path, '/' );
            $escaped_path = str_replace( '/', '\\/', $escaped_path );
            
            $content  = "location ~* " . $escaped_path . "$ {";
            $content .= "\n\tdeny all;";
            $content .= "\n}";
        }

        return trim( $content );
    }

    /**
     * get_settings
     */
    public function get_settings() {
        $this->default_settings = $this->get_default_settings();
        $settings = get_option( $this->option_id, $this->default_settings );
        $settings = wp_parse_args( $settings, $this->default_settings );
        return $settings;
    }

    /**
     * Save settings
     */
    public function save_settings( $new_settings ) {
        update_option( $this->option_id, $new_settings );
    }

    /**
     * Add a submenu
     */
    public function add_submenu() {
        WPMastertoolkit_Settings::add_submenu_page(
            'wp-mastertoolkit-settings',
            $this->header_title,
            $this->header_title,
            'manage_options',
            $this->submenu_page_id,
            array( $this, 'render_submenu' ),
            null
        );
    }

    /**
     * Render the submenu
     */
    public function render_submenu() {
        $this->settings = $this->get_settings();

        $submenu_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/advanced-debug-mode.asset.php' );
        wp_enqueue_style( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/advanced-debug-mode.css', array(), $submenu_assets['version'], 'all' );
        wp_enqueue_script( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/advanced-debug-mode.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );
        
        // Pass variables to JavaScript
        wp_localize_script( 'WPMastertoolkit_submenu', 'wpmtkDebugMode', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( $this->nonce_action ),
            'isPro' => wpmastertoolkit_is_pro(),
            'i18n' => array(
                'loadingLogs' => __( 'Loading logs...', 'wpmastertoolkit' ),
                'displayCleared' => __( 'Display cleared. Start streaming to view logs...', 'wpmastertoolkit' ),
                'confirmDelete' => __( 'Are you sure you want to permanently delete the debug.log file?', 'wpmastertoolkit' ),
                'logDeleted' => __( 'Log file deleted successfully.', 'wpmastertoolkit' ),
                'error' => __( 'Error: ', 'wpmastertoolkit' ),
                'unknownError' => __( 'Unknown error', 'wpmastertoolkit' ),
                'deleteFailed' => __( 'Failed to delete log file.', 'wpmastertoolkit' ),
                'fetchError' => __( '[ERROR] Failed to fetch logs', 'wpmastertoolkit' ),
            )
        ) );

        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
        $this->submenu_content();
        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/footer.php';
    }

    /**
     * Save the submenu option
     */
    public function save_submenu() {
        global $is_apache;

        $nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );

        if ( wp_verify_nonce( $nonce, $this->nonce_action ) ) {
            //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $new_settings = $this->sanitize_settings( wp_unslash( $_POST[ $this->option_id ] ?? array() ) );

            require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/class-wp-config.php';
            $display_errors = $new_settings['display_errors'] ?? '0';
            $custom_log_folder = $new_settings['custom_log_folder'] ?? '0';
            $daily_logs = $new_settings['daily_logs'] ?? '0';
            
            WPMastertoolkit_WP_Config::replace_or_add_constant( 'WP_DEBUG', true );
            
            // Handle custom log folder (PRO feature)
            if ( wpmastertoolkit_is_pro() && '1' === $custom_log_folder ) {
                $base_path = wpmastertoolkit_folders() . '/debug-logs';
                
                // Handle daily logs (only works with custom log folder)
                if ( '1' === $daily_logs ) {
                    $custom_log_path = "'" . $base_path . "/debug-' . date( 'Y-m-d' ) . '.log'";
                    WPMastertoolkit_WP_Config::replace_or_add_constant( 'WP_DEBUG_LOG', $custom_log_path, null, true );
                } else {
                    $custom_log_path = $base_path . '/debug.log';
                    WPMastertoolkit_WP_Config::replace_or_add_constant( 'WP_DEBUG_LOG', $custom_log_path );
                }
                
            } else {
                WPMastertoolkit_WP_Config::replace_or_add_constant( 'WP_DEBUG_LOG', true );
            }
            
            WPMastertoolkit_WP_Config::replace_or_add_constant( 'WP_DEBUG_DISPLAY', ( '1' === $display_errors ) );

            // Handle log protection for Apache
            if ( wpmastertoolkit_is_pro() && $is_apache ) {
                $protect_logs = $new_settings['protect_logs'] ?? '0';
                require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/class-htaccess.php';

                if ( '1' === $protect_logs ) {
                    WPMastertoolkit_Htaccess::add( self::get_raw_content_htaccess(), self::MODULE_ID );
                } else {
                    WPMastertoolkit_Htaccess::remove( self::MODULE_ID );
                }
            }

            $this->save_settings( $new_settings );
            wp_safe_redirect( sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) );
            exit;
        }
    }

    /**
     * sanitize_settings
     *
     * @return array
     */
    public function sanitize_settings( $new_settings ) {
        $this->default_settings = $this->get_default_settings();
        $sanitized_settings = array();

        $is_pro = wpmastertoolkit_is_pro();

        $sanitized_settings['protect_logs'] = $is_pro ? sanitize_text_field( $new_settings['protect_logs'] ?? '0' ) : '0';
        $sanitized_settings['display_errors'] = sanitize_text_field( $new_settings['display_errors'] ?? '0' );
        $sanitized_settings['custom_log_folder'] = $is_pro ? sanitize_text_field( $new_settings['custom_log_folder'] ?? '0' ) : '0';
        $sanitized_settings['daily_logs'] = $is_pro ? sanitize_text_field( $new_settings['daily_logs'] ?? '0' ) : '0';

        return $sanitized_settings;
    }

    /**
     * get_default_settings
     *
     * @return array
     */
    private function get_default_settings() {
        if ( $this->default_settings !== null ) {
            return $this->default_settings;
        }

        return array(
            'protect_logs'      => '0',
            'display_errors'    => '0',
            'custom_log_folder' => '0',
            'daily_logs'        => '0',
        );
    }

    /**
     * Submenu content
     */
    private function submenu_content() {
        $this->settings = $this->get_settings();
        $is_pro = wpmastertoolkit_is_pro();

        ?>
            <div class="wp-mastertoolkit__section">
                <div class="wp-mastertoolkit__section__desc">
					<?php esc_html_e( "Enable WordPress debug mode with logging. WP_DEBUG, WP_DEBUG_LOG and WP_DEBUG_DISPLAY constants are managed automatically.", 'wpmastertoolkit'); ?>
				</div>
                <div class="wp-mastertoolkit__section__body">

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Debug Mode Status', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
                                <?php
								// Get the actual log file path
								$log_file_path = self::get_debug_log_path();
								?>
								<div class="wpmtk-debug-status">
								<p class="wpmtk-debug-status__title"><?php esc_html_e( 'Current configuration:', 'wpmastertoolkit' ); ?></p>
								<ul class="wpmtk-debug-status__list">
									<li class="wpmtk-debug-status__item">
										<span class="wpmtk-debug-status__label">WP_DEBUG:</span>
										<span class="wpmtk-debug-status__value <?php echo defined( 'WP_DEBUG' ) && WP_DEBUG ? 'is-enabled' : 'is-disabled'; ?>">
											<?php echo defined( 'WP_DEBUG' ) && WP_DEBUG ? esc_html__( 'Enabled', 'wpmastertoolkit' ) : esc_html__( 'Disabled', 'wpmastertoolkit' ); ?>
										</span>
                                        <?php if ( ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) : ?>
                                            <span class="wpmtk-debug-status__note">
                                                <?php esc_html_e( 'Note: Saving settings will enable debug mode.', 'wpmastertoolkit' ); ?>
                                            </span>
                                        <?php endif; ?>
									</li>
									<li class="wpmtk-debug-status__item">
										<span class="wpmtk-debug-status__label">WP_DEBUG_LOG:</span>
										<span class="wpmtk-debug-status__value <?php echo defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ? 'is-enabled' : 'is-disabled'; ?>">
											<?php echo defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ? esc_html__( 'Enabled', 'wpmastertoolkit' ) : esc_html__( 'Disabled', 'wpmastertoolkit' ); ?>
										</span>
                                        <?php if ( ! ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) ) : ?>
                                            <span class="wpmtk-debug-status__note">
                                                <?php esc_html_e( 'Note: Saving settings will enable debug logging.', 'wpmastertoolkit' ); ?>
                                            </span>
                                        <?php endif; ?>
									</li>
									<li class="wpmtk-debug-status__item">
										<span class="wpmtk-debug-status__label">WP_DEBUG_DISPLAY:</span>
										<span class="wpmtk-debug-status__value <?php echo defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY ? 'is-enabled' : 'is-disabled'; ?>">
											<?php echo defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY ? esc_html__( 'Enabled', 'wpmastertoolkit' ) : esc_html__( 'Disabled', 'wpmastertoolkit' ); ?>
										</span>
									</li>
								</ul>
								<p class="wpmtk-debug-status__path">
									<span class="wpmtk-debug-status__label"><?php esc_html_e( 'Logs are saved to:', 'wpmastertoolkit' ); ?></span>
									<code><?php echo esc_html( str_replace( ABSPATH, '/', $log_file_path ) ); ?></code>
								</p>
							</div>
                        </div>
                    </div>

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title activable">
                            <div>
                                <label class="wp-mastertoolkit__toggle">
                                    <input type="hidden" name="<?php echo esc_attr( $this->option_id ); ?>[display_errors]" value="0">
									<input type="checkbox" name="<?php echo esc_attr( $this->option_id ); ?>[display_errors]" value="1" <?php checked( $this->settings['display_errors'] ?? '0', '1' ); ?>>
									<span class="wp-mastertoolkit__toggle__slider round"></span>
								</label>
							</div>
                            <div>
                                <?php esc_html_e( 'Display Errors', 'wpmastertoolkit' ); ?>
                            </div>
						</div>
                        <div class="wp-mastertoolkit__section__body__item__content">
							<div class="description"><?php esc_html_e( 'Enable WP_DEBUG_DISPLAY to show errors on screen. Not recommended for production sites.', 'wpmastertoolkit' ); ?></div>
                        </div>
                    </div>

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title activable">
                            <div>
                                <label class="wp-mastertoolkit__toggle">
                                    <input type="hidden" name="<?php echo esc_attr( $this->option_id ); ?>[protect_logs]" value="0">
									<input type="checkbox" name="<?php echo esc_attr( $this->option_id ); ?>[protect_logs]" value="1" <?php checked( $is_pro ? ( $this->settings['protect_logs'] ?? '0' ) : '0', '1' ); ?> <?php echo esc_attr( $is_pro ? '' : 'disabled' ); ?>>
									<span class="wp-mastertoolkit__toggle__slider round"></span>
								</label>
							</div>
                            <div>
                                <?php esc_html_e( 'Protect Debug Logs', 'wpmastertoolkit' ); ?> <span class="wp-mastertoolkit__badge wp-mastertoolkit__badge--pro"><?php esc_html_e( 'PRO', 'wpmastertoolkit' ); ?></span>
                            </div>
						</div>
                        <div class="wp-mastertoolkit__section__body__item__content">
							<div class="description"><?php esc_html_e( 'Prevent direct HTTP access to debug.log file. Adds rules to .htaccess (Apache) or provides nginx configuration snippet to block access.', 'wpmastertoolkit' ); ?></div>
                        </div>
                    </div>

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title activable">
                            <div>
                                <label class="wp-mastertoolkit__toggle">
                                    <input type="hidden" name="<?php echo esc_attr( $this->option_id ); ?>[custom_log_folder]" value="0">
									<input type="checkbox" name="<?php echo esc_attr( $this->option_id ); ?>[custom_log_folder]" value="1" <?php checked( $is_pro ? ( $this->settings['custom_log_folder'] ?? '0' ) : '0', '1' ); ?> <?php echo esc_attr( $is_pro ? '' : 'disabled' ); ?>>
									<span class="wp-mastertoolkit__toggle__slider round"></span>
								</label>
							</div>
                            <div>
                                <?php esc_html_e( 'Use Custom Log Folder', 'wpmastertoolkit' ); ?> <span class="wp-mastertoolkit__badge wp-mastertoolkit__badge--pro"><?php esc_html_e( 'PRO', 'wpmastertoolkit' ); ?></span>
                            </div>
						</div>
                        <div class="wp-mastertoolkit__section__body__item__content">
							<div class="description">
								<?php 
								$custom_folder = str_replace( ABSPATH, '/', wpmastertoolkit_folders() ) . '/debug-logs/';
								/* translators: %s: custom log folder path */
								echo sprintf( esc_html__( 'Move debug logs to a custom protected folder: %s', 'wpmastertoolkit' ), '<code>' . esc_html( $custom_folder ) . '</code>' );
								?>
							</div>
                        </div>
                    </div>

					<div class="wp-mastertoolkit__section__body__item" 
                        <?php if( $is_pro ) : ?>
                            data-show-if="<?php echo esc_attr( $this->option_id ); ?>[custom_log_folder]=1"
                        <?php endif; ?>
                        >
                        <div class="wp-mastertoolkit__section__body__item__title activable">
                            <div>
                                <label class="wp-mastertoolkit__toggle">
                                    <input type="hidden" name="<?php echo esc_attr( $this->option_id ); ?>[daily_logs]" value="0">
									<input type="checkbox" name="<?php echo esc_attr( $this->option_id ); ?>[daily_logs]" value="1" <?php checked( $is_pro ? ( $this->settings['daily_logs'] ?? '0' ) : '0', '1' ); ?> <?php echo esc_attr( $is_pro ? '' : 'disabled' ); ?>>
									<span class="wp-mastertoolkit__toggle__slider round"></span>
								</label>
							</div>
                            <div>
                                <?php esc_html_e( 'Daily Log Files', 'wpmastertoolkit' ); ?> <span class="wp-mastertoolkit__badge wp-mastertoolkit__badge--pro"><?php esc_html_e( 'PRO', 'wpmastertoolkit' ); ?></span>
                            </div>
						</div>
                        <div class="wp-mastertoolkit__section__body__item__content">
							<div class="description">
								<?php 
								esc_html_e( 'Create daily log files with date suffix (debug-YYYY-MM-DD.log). Only works with Custom Log Folder enabled.', 'wpmastertoolkit' );
								?>
							</div>
                        </div>
                    </div>

                </div>
            </div>

			<div class="wp-mastertoolkit__section pro-section <?php echo esc_attr( $is_pro ? 'is-pro' : 'is-not-pro' ); ?>">
				<div class="wp-mastertoolkit__section__pro-only">
                    <?php esc_html_e( 'This feature is only available in the Pro version.', 'wpmastertoolkit' ); ?>
                </div>
                <div class="wp-mastertoolkit__section__body">

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Live Log Viewer', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
							<div class="description"><?php esc_html_e( 'Stream debug logs in real-time:', 'wpmastertoolkit' ); ?></div>
							
                            <div class="wpmtk-log-buttons">
								<button type="button" id="wpmtk-start-log-stream" class="wpmtk-btn-start" <?php echo esc_attr( $is_pro ? '' : 'disabled' ); ?>><?php esc_html_e( 'Start Streaming', 'wpmastertoolkit' ); ?></button>
								<button type="button" id="wpmtk-stop-log-stream" class="wpmtk-btn-stop" style="display:none;" <?php echo esc_attr( $is_pro ? '' : 'disabled' ); ?>><?php esc_html_e( 'Stop Streaming', 'wpmastertoolkit' ); ?></button>
								<button type="button" id="wpmtk-clear-log-display" class="wpmtk-btn-clear" <?php echo esc_attr( $is_pro ? '' : 'disabled' ); ?>><?php esc_html_e( 'Clear Display', 'wpmastertoolkit' ); ?></button>
								<button type="button" id="wpmtk-download-log" class="wpmtk-btn-download" <?php echo esc_attr( $is_pro ? '' : 'disabled' ); ?>><?php esc_html_e( 'Download Log', 'wpmastertoolkit' ); ?></button>
								<button type="button" id="wpmtk-delete-log" class="wpmtk-btn-delete" <?php echo esc_attr( $is_pro ? '' : 'disabled' ); ?>><?php esc_html_e( 'Delete Log File', 'wpmastertoolkit' ); ?></button>
                                <div id="wpmtk-wrap-logs-container" class="wp-mastertoolkit__checkbox">
                                    <label class="wp-mastertoolkit__checkbox__label">
                                        <input type="checkbox" id="wpmtk-wrap-logs" checked <?php echo esc_attr( $is_pro ? '' : 'disabled' ); ?>>
                                        <span class="mark"></span>
                                        <span class="wp-mastertoolkit__checkbox__label__text"><?php esc_html_e( 'Wrap lines', 'wpmastertoolkit' ); ?></span>
                                    </label>
                                </div>
							</div>
							
							
                            <div id="wpmtk-log-viewer" class="wpmtk-log-wrap">
								<pre id="wpmtk-log-content"><?php esc_html_e( 'Click "Start Streaming" to view live logs...', 'wpmastertoolkit' ); ?></pre>
							</div>
                        </div>
                    </div>

                </div>
            </div>
        <?php
    }

    /**
     * Get the debug log file path
     *
     * @return string
     */
    private static function get_debug_log_path() {
        // Check if custom folder is enabled (PRO feature)
        $settings = get_option( WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_advanced_debug_mode', array() );
        $custom_log_folder = $settings['custom_log_folder'] ?? '0';
        $daily_logs = $settings['daily_logs'] ?? '0';
        
        // Check if WP_DEBUG_LOG is a custom path
        if ( defined( 'WP_DEBUG_LOG' ) && is_string( WP_DEBUG_LOG ) && WP_DEBUG_LOG !== true && WP_DEBUG_LOG !== false ) {
            // If custom log folder with daily logs is enabled
            if ( wpmastertoolkit_is_pro() && '1' === $custom_log_folder && '1' === $daily_logs ) {
                $base_path = dirname( WP_DEBUG_LOG );
                $date_suffix = wp_date( 'Y-m-d' );
                return $base_path . '/debug-' . $date_suffix . '.log';
            }
            return WP_DEBUG_LOG;
        }
        
        // Default path
        return WP_CONTENT_DIR . '/debug.log';
    }

    /**
     * AJAX handler to get debug log content
     */
    public function ajax_get_debug_log() {
        if ( ! wpmastertoolkit_is_pro() ) {
            wp_send_json_error( array( 'message' => __( 'This feature is only available in PRO version.', 'wpmastertoolkit' ) ) );
        }

        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, $this->nonce_action ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wpmastertoolkit' ) ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wpmastertoolkit' ) ) );
        }

        $log_file = self::get_debug_log_path();
        
        if ( ! file_exists( $log_file ) ) {
            wp_send_json_success( array( 
                'content' => esc_html__( 'No debug.log file found. Logs will appear here once errors are logged.', 'wpmastertoolkit' ),
                'size'    => 0
            ) );
        }

        $last_size = intval( $_POST['last_size'] ?? 0 );
        $current_size = filesize( $log_file );

        if ( $current_size === $last_size ) {
            wp_send_json_success( array( 
                'content' => '',
                'size'    => $current_size
            ) );
        }

        // Check if file was truncated or deleted during streaming
        if ( $current_size < $last_size ) {
            // File was truncated, reset and reload all content
            wp_send_json_success( array( 
                'content' => esc_html__( '[LOG FILE WAS TRUNCATED OR MODIFIED - RELOADING]', 'wpmastertoolkit' ),
                'size'    => 0,
                'reset'   => true
            ) );
        }

        // Read new content only
        $content = '';
        if ( $last_size === 0 ) {
            // First load - get last 100KB
			// phpcs:disable
            $handle = fopen( $log_file, 'r' );
            if ( $handle === false ) {
                wp_send_json_error( array( 'message' => __( 'Failed to open log file.', 'wpmastertoolkit' ) ) );
            }
            $content = '';
            if ( $current_size > 10240 ) {
                fseek( $handle, -10240, SEEK_END );
                fgets( $handle ); // Skip partial line
                $content .= "...\n";
            }
            $content .= fread( $handle, $current_size );
            fclose( $handle );
			// phpcs:enable
        } else {
            // Subsequent loads - get only new content
            $bytes_to_read = $current_size - $last_size;
            
            if ( $bytes_to_read > 0 ) {
				// phpcs:disable
                $handle = fopen( $log_file, 'r' );
                if ( $handle === false ) {
                    wp_send_json_error( array( 'message' => __( 'Failed to open log file.', 'wpmastertoolkit' ) ) );
                }
                
                fseek( $handle, $last_size );
                $content = fread( $handle, $bytes_to_read );
                fclose( $handle );
				// phpcs:enable
            }
        }

        wp_send_json_success( array( 
            'content' => $content,
            'size'    => $current_size
        ) );
    }

    /**
     * AJAX handler to delete debug log file
     */
    public function ajax_delete_debug_log() {
        if ( ! wpmastertoolkit_is_pro() ) {
            wp_send_json_error( array( 'message' => __( 'This feature is only available in PRO version.', 'wpmastertoolkit' ) ) );
        }

        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, $this->nonce_action ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wpmastertoolkit' ) ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wpmastertoolkit' ) ) );
        }

        $log_file = self::get_debug_log_path();
        
        if ( ! file_exists( $log_file ) ) {
            wp_send_json_error( array( 'message' => __( 'Log file does not exist.', 'wpmastertoolkit' ) ) );
        }

		// phpcs:disable
        if ( ! is_writable( $log_file ) ) {
            wp_send_json_error( array( 'message' => __( 'Log file is not writable.', 'wpmastertoolkit' ) ) );
        }

        if ( unlink( $log_file ) ) {
            wp_send_json_success( array( 'message' => __( 'Log file deleted successfully.', 'wpmastertoolkit' ) ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Failed to delete log file.', 'wpmastertoolkit' ) ) );
        }
		// phpcs:enable
    }
}
