<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Temporary Login
 * Description: Create temporary login links for users.
 * @since 2.14.0
 */
class WPMastertoolkit_Temporary_Login {

    const MODULE_ID = 'Temporary Login';

    private $option_id;
    private $header_title_menu;
    private $header_title;
    public $nonce_action;
    private $settings;
    private $default_settings;
    private $submenu_page_id;
    private $temp_user_prefix = 'TempUser_';
	private $disable_save_form = false;

    /**
     * Invoke the hooks
     */
    public function __construct() {

        $this->option_id       = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_temporary_login';
        $this->nonce_action    = $this->option_id . '_action';
        $this->submenu_page_id = 'wp-mastertoolkit-settings-temporary-login';

        /**
         * Temp user prefix
         * 
         * @since 2.14.0
         * 
         * @param string $this->temp_user_prefix The temporary user prefix
         */
        $this->temp_user_prefix = apply_filters( 'wpmastertoolkit_temporary_login_user_prefix', $this->temp_user_prefix );

        add_action( 'init', array( $this, 'class_init' ) );
        add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
        add_action( 'admin_init', array( $this, 'save_submenu' ) );
        
        // Check expiration on login
        add_filter( 'wp_authenticate_user', array( $this, 'check_temporary_user_expiration' ), 10, 2 );
        
        // Handle magic login link
        add_action( 'init', array( $this, 'handle_magic_login' ) );
        
        // AJAX actions
        add_action( 'wp_ajax_wpmtk_delete_temporary_user', array( $this, 'ajax_delete_temporary_user' ) );
        add_action( 'wp_ajax_wpmtk_delete_all_temporary_users', array( $this, 'ajax_delete_all_temporary_users' ) );
        add_action( 'wp_ajax_wpmtk_copy_login_link', array( $this, 'ajax_copy_login_link' ) );
        add_action( 'wp_ajax_wpmtk_resend_email', array( $this, 'ajax_resend_email' ) );
        
        // Protect other admin users
        add_filter( 'editable_roles', array( $this, 'protect_admin_users_list' ) );
        add_filter( 'map_meta_cap', array( $this, 'protect_admin_users_edit' ), 10, 4 );
        
        // Schedule daily cron to delete expired users
        add_action( 'wpmtk_delete_expired_temp_users', array( $this, 'delete_expired_temporary_users' ) );
        
        // Register activation/deactivation hooks for cron
        register_activation_hook( WPMASTERTOOLKIT_PLUGIN_FILE, array( $this, 'schedule_cron' ) );
        register_deactivation_hook( WPMASTERTOOLKIT_PLUGIN_FILE, array( $this, 'unschedule_cron' ) );
        
        // Ensure cron is scheduled
        if ( ! wp_next_scheduled( 'wpmtk_delete_expired_temp_users' ) ) {
            $this->schedule_cron();
        }
        
        // Add shortcut button on users.php page
        add_action( 'admin_enqueue_scripts', array( $this, 'add_temp_user_button_on_users_page' ) );
    }

    /**
     * Initialize the class
     */
    public function class_init() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $action = sanitize_text_field( wp_unslash( $_GET['action'] ?? '' ) );
        
        $this->header_title_menu = $this->header_title = esc_html__( 'Temporary Login', 'wpmastertoolkit' );

        switch ( $action ) {
            case 'add_user':
                $this->header_title = esc_html__( 'Temporary Login > Create Temporary User', 'wpmastertoolkit' );
                break;
            case 'edit_user':
                $this->header_title = esc_html__( 'Temporary Login > Edit Temporary User', 'wpmastertoolkit' );
                break;
            default:
                break;
        }
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
            $this->header_title_menu,
            'manage_options',
            $this->submenu_page_id,
            array( $this, 'render_submenu' ),
            null
        );

        add_submenu_page(
            'users.php',
            $this->header_title,
            $this->header_title_menu,
            'manage_options',
            $this->submenu_page_id,
            array( $this, 'render_submenu' )
        );
    }

    /**
     * Render the submenu
     */
    public function render_submenu() {
        $this->delete_expired_temporary_users();
        $this->settings = $this->get_settings();

        $submenu_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/temporary-login.asset.php' );
        wp_enqueue_style( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/temporary-login.css', array(), $submenu_assets['version'], 'all' );
        wp_enqueue_script( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/temporary-login.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );
        
        wp_localize_script( 'WPMastertoolkit_submenu', 'wpmtkTemporaryLogin', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( $this->nonce_action ),
            'isPro'   => wpmastertoolkit_is_pro(),
            'i18n'    => array(
                'confirmDelete'         => esc_html__( 'Are you sure you want to delete this temporary user?', 'wpmastertoolkit' ),
                'confirmDeleteAll'      => esc_html__( 'Are you sure you want to delete ALL temporary users? This action cannot be undone.', 'wpmastertoolkit' ),
                'confirmResendEmail'    => esc_html__( 'Are you sure you want to resend the email to this user?', 'wpmastertoolkit' ),
                'errorCopyLink'         => esc_html__( 'Error copying login link', 'wpmastertoolkit' ),
                'errorDelete'           => esc_html__( 'Error deleting user', 'wpmastertoolkit' ),
                'errorDeleteAll'        => esc_html__( 'Error deleting all users', 'wpmastertoolkit' ),
                'errorResend'           => esc_html__( 'Error resending email', 'wpmastertoolkit' ),
                'copied'                => esc_html__( 'Copied!', 'wpmastertoolkit' ),
                'emailSent'             => esc_html__( 'Email sent!', 'wpmastertoolkit' ),
                'sending'               => esc_html__( 'Sending...', 'wpmastertoolkit' ),
                'deleting'              => esc_html__( 'Deleting...', 'wpmastertoolkit' ),
                'allUsersDeleted'       => esc_html__( 'All temporary users deleted!', 'wpmastertoolkit' ),
            )
        ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if( !isset( $_GET['action'] ) ) {
            $this->disable_save_form = true;
        }

        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
        $current_user = wp_get_current_user();
        $is_current_temp_user = get_user_meta( $current_user->ID, 'wpmastertoolkit_temporary_login', true );
        
        if ( $is_current_temp_user ) {
            ?>
            <div class="wp-mastertoolkit__section">
                <?php esc_html_e( 'You are currently logged in as a temporary user. You cannot manage temporary login settings.', 'wpmastertoolkit' ); ?>
            </div>
            <?php
        } else {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if( isset( $_GET['action'] ) && in_array( $_GET['action'], array( 'add_user', 'edit_user' ), true ) ) {
                $this->add_edit_user_content();
            } else {
                $this->submenu_content();
            }
        }
        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/footer.php';
    }

    /**
     * Save the submenu option
     */
    public function save_submenu() {

        $nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );

        if ( wp_verify_nonce( $nonce, $this->nonce_action ) && current_user_can( 'manage_options' ) ) {
            
            // Handle user creation/edition
            if ( isset( $_POST['wpmtk_temporary_login_user_id'] ) ) {
                $this->handle_user_save();
                return;
            }
            
            //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $new_settings = $this->sanitize_settings( wp_unslash( $_POST[ $this->option_id ] ?? array() ) );

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

        );
    }

    /**
     * Submenu content
     */
    private function submenu_content() {
        $this->settings = $this->get_settings();
        $is_pro = wpmastertoolkit_is_pro();
        
        // Display success message for new user
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( isset( $_GET['user_created'] ) && $_GET['user_created'] === '1' && isset( $_GET['user_id'] ) && isset( $_GET['password'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $new_user_id = absint( $_GET['user_id'] );
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $password = urldecode( base64_decode( sanitize_text_field( wp_unslash( $_GET['password']) ) ) );
            $user = get_user_by( 'id', $new_user_id );
            
            if ( $user ) {
                $expiration = get_user_meta( $new_user_id, 'wpmtk_temporary_login_expiration', true );
                $has_link = get_user_meta( $new_user_id, 'wpmtk_connexion_link', true );
                $login_token = get_user_meta( $new_user_id, 'wpmtk_login_token', true );
                $magic_link = '';
                
                if ( $has_link === '1' && $login_token ) {
                    $magic_link = add_query_arg(
                        array(
                            'wpmtk_login' => $new_user_id,
                            'token'       => $login_token,
                        ),
                        home_url()
                    );
                }
                ?>
                <div class="wpmtk-credentials-notice">
                    <h3><?php esc_html_e( 'Temporary User Created Successfully!', 'wpmastertoolkit' ); ?></h3>
                    <p><?php esc_html_e( 'Here are the login credentials. Make sure to copy them now:', 'wpmastertoolkit' ); ?></p>
                    
                    <div class="wpmtk-credentials-box">
                        <?php if ( ! empty( $magic_link ) ) : ?>
                            <div class="wpmtk-credential-item">
                                <strong><?php esc_html_e( 'Magic Login Link:', 'wpmastertoolkit' ); ?></strong>
                                <div class="wpmtk-credential-value">
                                    <input type="text" readonly value="<?php echo esc_url( $magic_link ); ?>" class="wpmtk-copy-input" id="magic-link-input" />
                                    <button type="button" class="wpmtk-button wpmtk-copy-btn" data-target="magic-link-input">
                                        <?php esc_html_e( 'Copy', 'wpmastertoolkit' ); ?>
                                    </button>
                                </div>
                            </div>
                        <?php else : ?>
                            <div class="wpmtk-credential-item">
                                <strong><?php esc_html_e( 'Username:', 'wpmastertoolkit' ); ?></strong>
                                <div class="wpmtk-credential-value">
                                    <input type="text" readonly value="<?php echo esc_attr( $user->user_login ); ?>" class="wpmtk-copy-input" id="username-input" />
                                    <button type="button" class="wpmtk-button wpmtk-copy-btn" data-target="username-input">
                                        <?php esc_html_e( 'Copy', 'wpmastertoolkit' ); ?>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="wpmtk-credential-item">
                                <strong><?php esc_html_e( 'Password:', 'wpmastertoolkit' ); ?></strong>
                                <div class="wpmtk-credential-value">
                                    <input type="text" readonly value="<?php echo esc_attr( $password ); ?>" class="wpmtk-copy-input" id="password-input" />
                                    <button type="button" class="wpmtk-button wpmtk-copy-btn" data-target="password-input">
                                        <?php esc_html_e( 'Copy', 'wpmastertoolkit' ); ?>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="wpmtk-credential-item">
                                <strong><?php esc_html_e( 'Login URL:', 'wpmastertoolkit' ); ?></strong>
                                <div class="wpmtk-credential-value">
                                    <input type="text" readonly value="<?php echo esc_url( wp_login_url() ); ?>" class="wpmtk-copy-input" id="login-url-input" />
                                    <button type="button" class="wpmtk-button wpmtk-copy-btn" data-target="login-url-input">
                                        <?php esc_html_e( 'Copy', 'wpmastertoolkit' ); ?>
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ( ! empty( $user->user_email ) ) : ?>
                            <div class="wpmtk-credential-item">
                                <strong><?php esc_html_e( 'Email:', 'wpmastertoolkit' ); ?></strong>
                                <span class="wpmtk-credential-value-plain"><?php echo esc_html( $user->user_email ); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ( ! empty( $expiration ) ) : ?>
                            <div class="wpmtk-credential-item">
                                <strong><?php esc_html_e( 'Expires:', 'wpmastertoolkit' ); ?></strong>
                                <span class="wpmtk-credential-value-plain"><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $expiration ) ); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <p class="description">
                        <strong><?php esc_html_e( 'Important:', 'wpmastertoolkit' ); ?></strong>
                        <?php esc_html_e( 'This information will only be shown once. Make sure to save it before leaving this page.', 'wpmastertoolkit' ); ?>
                    </p>
                </div>
                <?php
            }
        }
        
        // Display update success message
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( isset( $_GET['user_updated'] ) && $_GET['user_updated'] === '1' ) {
            ?>
            <div class="wpmtk-credentials-notice">
                <p><?php esc_html_e( 'Temporary user updated successfully!', 'wpmastertoolkit' ); ?></p>
            </div>
            <?php
        }
        
        // Get all temporary users
        $temporary_users = $this->get_temporary_users();

        ?>
            <div class="wp-mastertoolkit__section">
                <div class="wp-mastertoolkit__section__desc">
					<?php esc_html_e( "Manage your temporary login accounts. Create temporary users with limited access duration.", 'wpmastertoolkit' ); ?>
				</div>
                
                <div class="wp-mastertoolkit__section__body">

                    <?php if ( ! empty( $temporary_users ) ) : ?>
                        <div class="wp-mastertoolkit__section__body__item">
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e( 'Username', 'wpmastertoolkit' ); ?></th>
                                        <th><?php esc_html_e( 'Email', 'wpmastertoolkit' ); ?></th>
                                        <th><?php esc_html_e( 'Role', 'wpmastertoolkit' ); ?></th>
                                        <th><?php esc_html_e( 'Created', 'wpmastertoolkit' ); ?></th>
                                        <th><?php esc_html_e( 'Expires', 'wpmastertoolkit' ); ?></th>
                                        <th><?php esc_html_e( 'Actions', 'wpmastertoolkit' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $temporary_users as $user ) : 
                                        $expiration = get_user_meta( $user->ID, 'wpmtk_temporary_login_expiration', true );
                                        $is_expired = $expiration && time() > $expiration;
                                        $has_link = get_user_meta( $user->ID, 'wpmtk_connexion_link', true );
                                        $login_token = get_user_meta( $user->ID, 'wpmtk_login_token', true );
                                    ?>
                                        <tr class="<?php echo esc_attr( $is_expired ? 'wpmtk-expired-user' : '' ); ?>">
                                            <td><?php echo esc_html( $user->user_login ); ?></td>
                                            <td><?php echo esc_html( !empty($user->user_email) ? $user->user_email : '-' ); ?></td>
                                            <td><?php echo esc_html( implode( ', ', $user->roles ) ); ?></td>
                                            <td>
                                                <?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $user->user_registered ) ) ); ?>
                                            </td>
                                            <td>
                                                <?php if ( $expiration ) : ?>
                                                    <?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $expiration ) ); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ( $has_link && $login_token && ! $is_expired ) : ?>
                                                    <button type="button" class="wpmtk-icon-button wpmtk-copy-login-link" data-user-id="<?php echo esc_attr( $user->ID ); ?>" data-token="<?php echo esc_attr( $login_token ); ?>" title="<?php esc_attr_e( 'Copy Login Link', 'wpmastertoolkit' ); ?>">
                                                        <?php echo wp_kses( file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/copy.svg' ), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ( ! empty( $user->user_email ) && ! $is_expired ) : ?>
                                                    <button type="button" class="wpmtk-icon-button wpmtk-resend-email" data-user-id="<?php echo esc_attr( $user->ID ); ?>" title="<?php esc_attr_e( 'Resend Email', 'wpmastertoolkit' ); ?>">
                                                        <?php echo wp_kses( file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/email.svg' ), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
                                                    </button>
                                                <?php endif; ?>
                                                <a href="<?php echo esc_url( add_query_arg( array( 'page' => $this->submenu_page_id, 'action' => 'edit_user', 'user_id' => $user->ID ), admin_url( 'admin.php' ) ) ); ?>" class="wpmtk-icon-button" title="<?php esc_attr_e( 'Edit', 'wpmastertoolkit' ); ?>">
                                                    <?php echo wp_kses( file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/edit.svg' ), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
                                                </a>
                                                <button type="button" class="wpmtk-icon-button danger wpmtk-delete-user" data-user-id="<?php echo esc_attr( $user->ID ); ?>" title="<?php esc_attr_e( 'Delete', 'wpmastertoolkit' ); ?>">
                                                    <?php echo wp_kses( file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/times.svg' ), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else : ?>
                        <div class="wp-mastertoolkit__section__body__item">
                            <p><?php esc_html_e( 'No temporary users found. Click "Add New Temporary User" to create one.', 'wpmastertoolkit' ); ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__button">
                            <a href="<?php echo esc_url( add_query_arg( array( 'page' => $this->submenu_page_id, 'action' => 'add_user' ), admin_url( 'admin.php' ) ) ); ?>" class="wpmtk-button inline">
                                <?php esc_html_e( 'Add New Temporary User', 'wpmastertoolkit' ); ?>
                            </a>
                            <?php if ( ! empty( $temporary_users ) ) : ?>
                                <button type="button" class="wpmtk-button inline danger wpmtk-delete-all-users" style="margin-left: 10px;">
                                    <?php esc_html_e( 'Delete All Temporary Users', 'wpmastertoolkit' ); ?>
                                </button>
                            <?php endif; ?>
                        </div>

                    </div>


                </div>
                
            </div>
        <?php
    }
    
    /**
     * get_temporary_validity_options
     *
     * @return void
     */
    private function get_temporary_validity_options() {
        return array(
            HOUR_IN_SECONDS       => esc_html__( '1 Hour', 'wpmastertoolkit' ),
            6 * HOUR_IN_SECONDS   => esc_html__( '6 Hours', 'wpmastertoolkit' ),
            12 * HOUR_IN_SECONDS  => esc_html__( '12 Hours', 'wpmastertoolkit' ),
            DAY_IN_SECONDS        => esc_html__( '1 Day', 'wpmastertoolkit' ),
            3 * DAY_IN_SECONDS    => esc_html__( '3 Days', 'wpmastertoolkit' ),
            7 * DAY_IN_SECONDS    => esc_html__( '7 Days', 'wpmastertoolkit' ),
            30 * DAY_IN_SECONDS   => esc_html__( '30 Days', 'wpmastertoolkit' ),
            'custom'              => esc_html__( 'Custom date', 'wpmastertoolkit' ),
        );
    }
    
    /**
     * add_edit_user_content
     *
     * @return void
     */
    private function add_edit_user_content() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $user_id = isset( $_GET['user_id'] ) ? sanitize_text_field( wp_unslash( $_GET['user_id'] ) ) : 'new';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $action  = sanitize_text_field( wp_unslash( $_GET['action'] ?? '' ) );
        
        if( $user_id !== 'new' && $action === 'edit_user' ) {
            $user_id = absint( $user_id );
            $user = get_user_by( 'id', $user_id );
            if ( ! $user || ! get_user_meta( $user_id, 'wpmastertoolkit_temporary_login', true ) ) {
                echo wp_kses_post( '<p>' . esc_html__( 'The specified temporary user does not exist.', 'wpmastertoolkit' ) . '</p>' );
                return;
            }
        } else {
            $user = null;
            $user_id = 'new';
        }

        $is_pro                           = wpmastertoolkit_is_pro();
        $wpmtk_connexion_link             = $user_id != 'new' ? get_user_meta( $user_id, 'wpmtk_connexion_link', true ) : '1';
        $wpmtk_temporary_login_validity   = $user_id != 'new' ? absint( sanitize_text_field( get_user_meta( $user_id, 'wpmtk_temporary_login_validity', true ) ) ) : 7 * DAY_IN_SECONDS;
        $wpmtk_temporary_login_validity   = ! empty( $wpmtk_temporary_login_validity ) ? $wpmtk_temporary_login_validity : 7 * DAY_IN_SECONDS;
        $wpmtk_temporary_validity_options = $this->get_temporary_validity_options();
        $user_role                        = $user_id != 'new' && isset($user->roles[0]) ? $user->roles[0] : '';
        $user_role                        = ! empty( $user_role ) ? $user_role : 'administrator';
        $wp_user_roles                    = get_editable_roles();
        $wpmtk_protect_other_admin_users  = $user_id != 'new' ? get_user_meta( $user_id, 'wpmtk_protect_other_admin_users', true ) : '1';
        $expiration                       = $user_id != 'new' ? get_user_meta( $user_id, 'wpmtk_temporary_login_expiration', true ) : '';
        
        ?>
            <div class="wp-mastertoolkit__return-link">
                <div class="wp-mastertoolkit__button">
                    <a href="<?php echo esc_url( add_query_arg( array( 'page' => $this->submenu_page_id ), admin_url( 'admin.php' ) ) ); ?>" class="wpmtk-button secondary inline">
                        <?php esc_html_e( '← Back to Temporary Users List', 'wpmastertoolkit' ); ?>
                    </a>
                </div>
            </div>

            <div class="wp-mastertoolkit__section">
                <div class="wp-mastertoolkit__section__body">

                    <input type="hidden" name="wpmtk_temporary_login_user_id" value="<?php echo esc_attr( $user_id ); ?>">
                    
                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title activable">
                            <div>
                                <label class="wp-mastertoolkit__toggle">
                                    <input type="hidden" name="wpmtk_connexion_link" value="0">
									<input type="checkbox" name="wpmtk_connexion_link" value="1" <?php checked( $is_pro ? $wpmtk_connexion_link : '0', '1' ); ?> <?php echo esc_attr( $is_pro ? '' : 'disabled' ); ?>>
									<span class="wp-mastertoolkit__toggle__slider round"></span>
								</label>
							</div>
                            <div>
                                <?php esc_html_e( 'Connexion link', 'wpmastertoolkit' ); ?> <span class="wp-mastertoolkit__badge wp-mastertoolkit__badge--pro"><?php esc_html_e( 'PRO', 'wpmastertoolkit' ); ?></span>
                            </div>
						</div>
                        <div class="wp-mastertoolkit__section__body__item__content">
							<div class="description"><?php esc_html_e( 'Generate a temporary login link for the user to access the site without login and password.', 'wpmastertoolkit' ); ?></div>
                        </div>
                    </div>

                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title">
                            <div>
                                <?php esc_html_e( 'User Role', 'wpmastertoolkit' ); ?>
                            </div>
                        </div>
                        
                        <div class="wp-mastertoolkit__section__body__item__content">
                            
                            <div class="wp-mastertoolkit__select">
                                <select name="wpmtk_temporary_login_user_role" required>
                                    <?php foreach ( $wp_user_roles as $role_key => $role_info ) : ?>
                                        <option value="<?php echo esc_attr( $role_key ); ?>" <?php selected( $user_role, $role_key ); ?> <?php echo esc_attr( ! $is_pro && $role_key !== 'administrator' ? 'disabled' : '' ); ?>><?php echo esc_html( $role_info['name'] ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="description"><?php esc_html_e( 'Select the role to assign to the temporary user.', 'wpmastertoolkit' ); ?></div>
                        </div>
                    </div>

                     <div class="wp-mastertoolkit__section__body__item" data-show-if="wpmtk_temporary_login_user_role=administrator">
                        <div class="wp-mastertoolkit__section__body__item__title activable">
                            <div>
                                <label class="wp-mastertoolkit__toggle">
                                    <input type="hidden" name="wpmtk_protect_other_admin_users" value="0">
                                    <input type="checkbox" name="wpmtk_protect_other_admin_users" value="1" <?php echo esc_attr( $is_pro ? '' : 'disabled' ); ?> <?php checked( $is_pro ? $wpmtk_protect_other_admin_users : '0', '1' ); ?>>
									<span class="wp-mastertoolkit__toggle__slider round"></span>
								</label>
							</div>
                            <div>
                                <?php esc_html_e( 'Protect Other Admin Users', 'wpmastertoolkit' ); ?> <span class="wp-mastertoolkit__badge wp-mastertoolkit__badge--pro"><?php esc_html_e( 'PRO', 'wpmastertoolkit' ); ?></span>
                            </div>
						</div>
                        <div class="wp-mastertoolkit__section__body__item__content">
							<div class="description"><?php esc_html_e( 'Prevent temporary admin users from accessing other admin user accounts.', 'wpmastertoolkit' ); ?></div>
                        </div>
                    </div>

                    <?php if( $user_id != 'new' && $action === 'edit_user' ) : ?>
                        <div class="wp-mastertoolkit__section__body__item">
                            <div class="wp-mastertoolkit__section__body__item__title">
                                <div>
                                    <?php esc_html_e( 'Extend validity', 'wpmastertoolkit' ); ?> <span class="wp-mastertoolkit__badge wp-mastertoolkit__badge--pro"><?php esc_html_e( 'PRO', 'wpmastertoolkit' ); ?></span>
                                </div>
                            </div>
                            
                            <div class="wp-mastertoolkit__section__body__item__content">
                                
                                <div class="wp-mastertoolkit__select">
                                    <select name="wpmtk_extend_temporary_login_validity" id="wpmtk_extend_validity" <?php echo esc_attr( $is_pro ? '' : 'disabled' ); ?>>
                                        <option value="0"><?php esc_html_e( 'Do not extend', 'wpmastertoolkit' ); ?></option>
                                        <?php foreach ( $wpmtk_temporary_validity_options as $value => $label ) : ?>
                                            <option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="wp-mastertoolkit__input-text" id="wpmtk_extend_custom_date_wrapper" data-show-if="wpmtk_extend_temporary_login_validity=custom" style="margin-top: 10px;">
                                    <input type="datetime-local" name="wpmtk_extend_custom_date" id="wpmtk_extend_custom_date" min="<?php echo esc_attr( wp_date( 'Y-m-d\TH:i', strtotime( '+1 hour' ) ) ); ?>" <?php echo esc_attr( $is_pro ? 'required' : 'disabled' ); ?> />
                                </div>
                                
                                <div class="description">
                                    <?php esc_html_e( 'Select the duration for which the temporary login will remain valid.', 'wpmastertoolkit' ); ?>
                                    <?php echo esc_html( sprintf( 
                                        /* translators: %s: Current expiration date */
                                        __('Current expiration: %s', 'wpmastertoolkit' ), 
                                        esc_html( $expiration ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $expiration ) : esc_html__( 'Never', 'wpmastertoolkit' ) 
                                    ) ) ); ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if( $action === 'add_user' ) : ?>
                        <div class="wp-mastertoolkit__section__body__item">
                            <div class="wp-mastertoolkit__section__body__item__title">
                                <div>
                                    <?php esc_html_e( 'End validity', 'wpmastertoolkit' ); ?> <?php echo wp_kses_post( $is_pro ? '<span class="required">*</span>' : '' ); ?>
                                </div>
                            </div>
                            
                            <div class="wp-mastertoolkit__section__body__item__content">
                                
                                <div class="wp-mastertoolkit__select">
                                    <select name="wpmtk_temporary_login_validity" id="wpmtk_temporary_login_validity" required>
                                        <?php foreach ( $wpmtk_temporary_validity_options as $value => $label ) : ?>
                                            <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $wpmtk_temporary_login_validity, $value ); ?> <?php echo esc_attr( ! $is_pro && $value !== 'custom' && $value !== 7 * DAY_IN_SECONDS ? 'disabled' : '' ); ?>><?php echo esc_html( $label ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="wp-mastertoolkit__input-text" id="wpmtk_custom_date_wrapper" data-show-if="wpmtk_temporary_login_validity=custom" style="margin-top: 10px;">
                                    <input type="datetime-local" name="wpmtk_custom_expiration_date" id="wpmtk_custom_expiration_date" min="<?php echo esc_attr( wp_date( 'Y-m-d\TH:i', strtotime( '+1 hour' ) ) ); ?>" <?php echo esc_attr( $is_pro ? 'required' : 'disabled' ); ?> />
                                </div>
                                
                                <div class="description"><?php esc_html_e( 'Select the duration for which the temporary login will remain valid.', 'wpmastertoolkit' ); ?></div>
                                
                            </div>
                        </div>

                        <div class="wp-mastertoolkit__section__body__item">
                            <div class="wp-mastertoolkit__section__body__item__title activable">
                                <div>
                                    <label class="wp-mastertoolkit__toggle">
                                        <input type="hidden" name="wpmtk_send_email_to_new_user" value="0">
                                        <input type="checkbox" name="wpmtk_send_email_to_new_user" value="1" checked>
                                        <span class="wp-mastertoolkit__toggle__slider round"></span>
                                    </label>
                                </div>
                                <div>
                                    <?php esc_html_e( 'Send email to new user', 'wpmastertoolkit' ); ?>
                                </div>
                            </div>
                            <div class="wp-mastertoolkit__section__body__item__content">
                                <div class="description"><?php esc_html_e( 'Send an email notification to the temporary user with their login details.', 'wpmastertoolkit' ); ?></div>
                            </div>
                        </div>
                        
                        <div class="wp-mastertoolkit__section__body__item" data-show-if="wpmtk_send_email_to_new_user=1">
                            <div class="wp-mastertoolkit__section__body__item__title">
                                <div>
                                    <?php esc_html_e( 'Email for notification', 'wpmastertoolkit' ); ?> <span class="required">*</span>
                                </div>
                            </div>
                            
                            <div class="wp-mastertoolkit__section__body__item__content">
                                <div class="wp-mastertoolkit__input-text">
                                    <input type="email" name="wpmtk_email_notification" value="" placeholder="ex: user@example.com" required>
                                </div>
                                <div class="description"><?php esc_html_e( 'The email address where the notification will be sent.', 'wpmastertoolkit' ); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php
    }
    
    /**
     * Handle user save (create or update)
     */
    private function handle_user_save() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
        $user_id = isset( $_POST['wpmtk_temporary_login_user_id'] ) ? sanitize_text_field( wp_unslash( $_POST['wpmtk_temporary_login_user_id'] ) ) : 'new';
        $is_pro = wpmastertoolkit_is_pro();
        $is_new_user = $user_id === 'new';
        
        if ( $is_new_user ) {
            // Create new temporary user
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
            $email = sanitize_email( wp_unslash( $_POST['wpmtk_email_notification'] ?? '' ) );
            if ( !empty( $email ) && ! is_email( $email ) ) {
                wp_die( esc_html__( 'Invalid email address.', 'wpmastertoolkit' ) );
            }
            
            // Check if email already exists
            if ( !empty( $email ) && email_exists( $email ) ) {
                wp_die( esc_html__( 'This email is already registered.', 'wpmastertoolkit' ) );
            }
            
            // Generate username from email
            $username = sanitize_user( $this->temp_user_prefix . wp_generate_password( 5, false ) );
            $username_base = $username;
            $counter = 1;
            while ( username_exists( $username ) ) {
                $username = $username_base . $counter;
                $counter++;
            }
            
            // Generate random password
            $password = wp_generate_password( 20, true, true );
            
            
            // Create user
            $user_id = wp_create_user( $username, $password, $email );
            
            if ( is_wp_error( $user_id ) ) {
                wp_die( wp_kses_post( $user_id->get_error_message() ) );
            }

            update_user_meta( $user_id, 'wpmastertoolkit_temporary_login', true );
            
            // Set expiration
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
            $validity_value = sanitize_text_field( wp_unslash( $_POST['wpmtk_temporary_login_validity'] ?? '604800' ) );
            
            if ( $validity_value === 'custom' && $is_pro ) {
                // Custom date provided
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
                $custom_date = sanitize_text_field( wp_unslash( $_POST['wpmtk_custom_expiration_date'] ?? '' ) );
                if ( ! empty( $custom_date ) ) {
                    try {
                        $date = new DateTime( $custom_date, wp_timezone() );
                        $expiration = $date->getTimestamp();
                        if ( $expiration <= time() ) {
                            wp_die( esc_html__( 'Invalid custom expiration date. Please select a future date.', 'wpmastertoolkit' ) );
                        }
                    } catch ( Exception $e ) {
                        wp_die( esc_html__( 'Invalid custom expiration date format.', 'wpmastertoolkit' ) );
                    }
                } else {
                    wp_die( esc_html__( 'Please provide a custom expiration date.', 'wpmastertoolkit' ) );
                }
            } else {
                $validity = $is_pro ? absint( $validity_value ) : 7 * DAY_IN_SECONDS;
                $validity = $validity > 0 ? $validity : 7 * DAY_IN_SECONDS;
                $expiration = time() + $validity;
            }
            
            update_user_meta( $user_id, 'wpmtk_temporary_login_expiration', $expiration );
            
        }

        // Update existing user
        $user_id = absint( $user_id );
        $user = get_user_by( 'id', $user_id );
        
        if ( ! $user || ! get_user_meta( $user_id, 'wpmastertoolkit_temporary_login', true ) ) {
            wp_die( esc_html__( 'Invalid temporary user.', 'wpmastertoolkit' ) );
        }
        
        // Update role
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
        $role = sanitize_text_field( wp_unslash( $_POST['wpmtk_temporary_login_user_role'] ?? 'administrator' ) );
        if ( ! $is_pro && $role !== 'administrator' ) {
            $role = 'administrator';
        }
        $user->set_role( $role );
        
        // Update connexion link
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
        $connexion_link = !empty( $_POST['wpmtk_connexion_link'] ) ? '1' : '0';
        if ( $is_pro && $connexion_link === '1' ) {
            update_user_meta( $user_id, 'wpmtk_connexion_link', '1' );
            if ( empty( get_user_meta( $user_id, 'wpmtk_login_token', true ) ) ) {
                $token = $this->generate_login_token( $user_id );
                update_user_meta( $user_id, 'wpmtk_login_token', $token );
            }
        } else {
            update_user_meta( $user_id, 'wpmtk_connexion_link', '0' );
        }
        
        // Update protect other admin users
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( $role === 'administrator' && $is_pro && !empty( $_POST['wpmtk_protect_other_admin_users'] ) ) {
            update_user_meta( $user_id, 'wpmtk_protect_other_admin_users', '1' );
        } else {
            delete_user_meta( $user_id, 'wpmtk_protect_other_admin_users' );
        }

        if( $is_new_user ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
            if ( isset( $_POST['wpmtk_send_email_to_new_user'] ) && $_POST['wpmtk_send_email_to_new_user'] === '1' ) {
                $this->send_temporary_login_email( $user_id, $password );
            }
            $password_base64 = urlencode( base64_encode( $password ) );
            wp_safe_redirect( add_query_arg( array( 'page' => $this->submenu_page_id, 'user_created' => '1', 'user_id' => $user_id, 'password' => $password_base64 ), admin_url( 'admin.php' ) ) );
            exit;

        } else {
            // Extend validity if requested
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
            if ( isset( $_POST['wpmtk_extend_temporary_login_validity'] ) && $is_pro ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
                $extend_value = sanitize_text_field( wp_unslash( $_POST['wpmtk_extend_temporary_login_validity'] ) );
                
                if ( $extend_value === 'custom' ) {
                    // Custom date provided for extension
					// phpcs:ignore WordPress.Security.NonceVerification.Missing
                    $custom_date = sanitize_text_field( wp_unslash( $_POST['wpmtk_extend_custom_date'] ?? '' ) );
                    if ( ! empty( $custom_date ) ) {
                        try {
                            $date = new DateTime( $custom_date, wp_timezone() );
                            $new_expiration = $date->getTimestamp();
                            if ( $new_expiration <= time() ) {
                                wp_die( esc_html__( 'Invalid custom expiration date. Please select a future date.', 'wpmastertoolkit' ) );
                            }
                            update_user_meta( $user_id, 'wpmtk_temporary_login_expiration', $new_expiration );
                        } catch ( Exception $e ) {
                            wp_die( esc_html__( 'Invalid custom expiration date format.', 'wpmastertoolkit' ) );
                        }
                    }
                } elseif ( $extend_value !== '0' ) {
                    $extend_by = absint( $extend_value );
                    if ( $extend_by > 0 ) {
                        $current_expiration = get_user_meta( $user_id, 'wpmtk_temporary_login_expiration', true );
                        $new_expiration = $current_expiration + $extend_by;
                        update_user_meta( $user_id, 'wpmtk_temporary_login_expiration', $new_expiration );
                    }
                }
            }
            
            wp_safe_redirect( add_query_arg( array( 'page' => $this->submenu_page_id, 'user_updated' => '1' ), admin_url( 'admin.php' ) ) );
            exit;
        }
    }
    
    /**
     * Get all temporary users
     */
    private function get_temporary_users( $expired = false ) {
        $args = array(
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            'meta_query' => array(
                array(
                    'key'     => 'wpmastertoolkit_temporary_login',
                    'value'   => '1',
                    'compare' => '=',
                ),
            ),
            'orderby'    => 'registered',
            'order'      => 'DESC',
        );
        
        if ( $expired ) {
            $args['meta_query'][] = array(
                'key'     => 'wpmtk_temporary_login_expiration',
                'value'   => time(),
                'compare' => '<=',
                'type'    => 'NUMERIC',
            );
        }
        
        $users = get_users( $args );
        return $users;
    }
    
    /**
     * Generate login token for magic link
     */
    private function generate_login_token( $user_id ) {
        return wp_hash( $user_id . time() . wp_generate_password( 32, false ) ) . wp_hash( wp_generate_password( 32, false ) );
    }
    
    /**
     * Check if temporary user is expired
     */
    public function check_temporary_user_expiration( $user, $password ) {
        if ( is_wp_error( $user ) ) {
            return $user;
        }
        
        $is_temp_user = get_user_meta( $user->ID, 'wpmastertoolkit_temporary_login', true );
        if ( ! $is_temp_user ) {
            return $user;
        }
        
        $expiration = get_user_meta( $user->ID, 'wpmtk_temporary_login_expiration', true );
        if ( $expiration && time() > $expiration ) {
            return new WP_Error(
                'temporary_login_expired',
                esc_html__( 'This temporary login has expired.', 'wpmastertoolkit' )
            );
        }
        
        return $user;
    }
    
    /**
     * Handle magic login link
     */
    public function handle_magic_login() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( ! isset( $_GET['wpmtk_login'] ) || ! isset( $_GET['token'] ) ) {
            return;
        }
        
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $user_id = absint( $_GET['wpmtk_login'] );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $token = sanitize_text_field( wp_unslash( $_GET['token'] ) );
        
        $user = get_user_by( 'id', $user_id );
        if ( ! $user ) {
            wp_die( esc_html__( 'Invalid login link.', 'wpmastertoolkit' ) );
        }
        
        // Check if it's a temporary user
        $is_temp_user = get_user_meta( $user_id, 'wpmastertoolkit_temporary_login', true );
        if ( ! $is_temp_user ) {
            wp_die( esc_html__( 'Invalid login link.', 'wpmastertoolkit' ) );
        }
        
        // Check if connexion link is enabled
        $connexion_link = get_user_meta( $user_id, 'wpmtk_connexion_link', true );
        if ( $connexion_link !== '1' ) {
            wp_die( esc_html__( 'Invalid login link.', 'wpmastertoolkit' ) );
        }
        
        // Verify token
        $stored_token = get_user_meta( $user_id, 'wpmtk_login_token', true );
        if ( $token !== $stored_token ) {
            wp_die( esc_html__( 'Invalid login link.', 'wpmastertoolkit' ) );
        }
        
        // Check expiration
        $expiration = get_user_meta( $user_id, 'wpmtk_temporary_login_expiration', true );
        if ( $expiration && time() > $expiration ) {
            $this->delete_expired_temporary_users();
            wp_die( esc_html__( 'This temporary login has expired.', 'wpmastertoolkit' ) );
        }
        
        // Log the user in
        wp_set_current_user( $user_id );
        wp_set_auth_cookie( $user_id );
		//phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
        do_action( 'wp_login', $user->user_login, $user );
        
        // Redirect to admin
        wp_safe_redirect( admin_url() );
        exit;
    }
    
    /**
     * AJAX: Delete temporary user
     */
    public function ajax_delete_temporary_user() {
        check_ajax_referer( $this->nonce_action, 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'wpmastertoolkit' ) ) );
        }
        
        $user_id = absint( $_POST['user_id'] ?? 0 );
        if ( ! $user_id ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Invalid user ID.', 'wpmastertoolkit' ) ) );
        }
        
        $user = get_user_by( 'id', $user_id );
        if ( ! $user || ! get_user_meta( $user_id, 'wpmastertoolkit_temporary_login', true ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Invalid temporary user.', 'wpmastertoolkit' ) ) );
        }
        
        require_once( ABSPATH . 'wp-admin/includes/user.php' );
        $deleted = wp_delete_user( $user_id );
        
        if ( $deleted ) {
            wp_send_json_success( array( 'message' => esc_html__( 'User deleted successfully.', 'wpmastertoolkit' ) ) );
        } else {
            wp_send_json_error( array( 'message' => esc_html__( 'Failed to delete user.', 'wpmastertoolkit' ) ) );
        }
    }
    
    /**
     * AJAX: Copy login link
     */
    public function ajax_copy_login_link() {
        check_ajax_referer( $this->nonce_action, 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'wpmastertoolkit' ) ) );
        }
        
        $user_id = absint( $_POST['user_id'] ?? 0 );
        $token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
        
        if ( ! $user_id || ! $token ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Invalid data.', 'wpmastertoolkit' ) ) );
        }
        
        $login_url = add_query_arg(
            array(
                'wpmtk_login' => $user_id,
                'token'       => $token,
            ),
            home_url()
        );
        
        wp_send_json_success( array( 'url' => $login_url ) );
    }
    
    /**
     * AJAX: Delete all temporary users
     */
    public function ajax_delete_all_temporary_users() {
        check_ajax_referer( $this->nonce_action, 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'wpmastertoolkit' ) ) );
        }
        
        $temporary_users = $this->get_temporary_users();
        
        if ( empty( $temporary_users ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'No temporary users to delete.', 'wpmastertoolkit' ) ) );
        }
        
        require_once( ABSPATH . 'wp-admin/includes/user.php' );
        
        $deleted_count = 0;
        foreach ( $temporary_users as $user ) {
            if ( wp_delete_user( $user->ID ) ) {
                $deleted_count++;
            }
        }
        
        if ( $deleted_count > 0 ) {
            wp_send_json_success( array( 
                'message' => sprintf(
                    /* translators: %d: Number of deleted users */
                    esc_html( _n( '%d temporary user deleted successfully.', '%d temporary users deleted successfully.', $deleted_count, 'wpmastertoolkit' ) ),
                    $deleted_count
                )
            ) );
        } else {
            wp_send_json_error( array( 'message' => esc_html__( 'Failed to delete users.', 'wpmastertoolkit' ) ) );
        }
    }
    
    /**
     * AJAX: Resend email
     */
    public function ajax_resend_email() {
        check_ajax_referer( $this->nonce_action, 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'wpmastertoolkit' ) ) );
        }
        
        $user_id = absint( $_POST['user_id'] ?? 0 );
        if ( ! $user_id ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Invalid user ID.', 'wpmastertoolkit' ) ) );
        }
        
        $sent = $this->send_temporary_login_email( $user_id );
        
        if ( $sent ) {
            wp_send_json_success( array( 'message' => esc_html__( 'Email sent successfully.', 'wpmastertoolkit' ) ) );
        } else {
            wp_send_json_error( array( 'message' => esc_html__( 'Failed to send email.', 'wpmastertoolkit' ) ) );
        }
    }
    
    /**
     * Send temporary login email
     */
    private function send_temporary_login_email( $user_id, $password = null ) {
        $user = get_user_by( 'id', $user_id );
        if ( ! $user ) {
            return false;
        }
        
        $expiration = get_user_meta( $user_id, 'wpmtk_temporary_login_expiration', true );
        $connexion_link = get_user_meta( $user_id, 'wpmtk_connexion_link', true );
        $token = get_user_meta( $user_id, 'wpmtk_login_token', true );
        
        $subject = sprintf(
            /* translators: %s: Site name */
            esc_html__( 'Your temporary access to %s', 'wpmastertoolkit' ),
            get_bloginfo( 'name' )
        );
        
        $message = sprintf(
            /* translators: %s: Site name */
            esc_html__( 'Hello, you have been granted temporary access to %s', 'wpmastertoolkit' ),
            get_bloginfo( 'name' )
        ) . "\n\n";
        
        if ( $connexion_link === '1' && $token ) {
            $login_url = add_query_arg(
                array(
                    'wpmtk_login' => $user_id,
                    'token'       => $token,
                ),
                home_url()
            );
            $message .= esc_html__( 'Click the link below to login:', 'wpmastertoolkit' ) . "\n";
            $message .= $login_url . "\n\n";
        } else {
            $message .= esc_html__( 'Login credentials:', 'wpmastertoolkit' ) . "\n";
            $message .= esc_html__( 'Username:', 'wpmastertoolkit' ) . ' ' . $user->user_login . "\n";
            if ( $password ) {
                $message .= esc_html__( 'Password:', 'wpmastertoolkit' ) . ' ' . $password . "\n";
            }
            $message .= esc_html__( 'Login URL:', 'wpmastertoolkit' ) . ' ' . wp_login_url() . "\n\n";
        }
        
        if ( $expiration ) {
            $message .= sprintf(
                /* translators: %s: Expiration date */
                esc_html__( 'This access will expire on: %s', 'wpmastertoolkit' ),
                date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $expiration )
            ) . "\n";
        }
        
        return wp_mail( $user->user_email, $subject, $message );
    }
    
    /**
     * Protect admin users list from temporary admin users
     */
    public function protect_admin_users_list( $roles ) {
        $user = wp_get_current_user();
        if ( ! $user ) {
            return $roles;
        }
        
        $is_temp_user = get_user_meta( $user->ID, 'wpmastertoolkit_temporary_login', true );
        $protect      = get_user_meta( $user->ID, 'wpmtk_protect_other_admin_users', true );

        if ( $is_temp_user && $protect === '1' && in_array( 'administrator', $user->roles ) ) {
            // Temporary admin can only see non-admin roles
            foreach ( $roles as $role_key => $role_info ) {
                if ( $role_key === 'administrator' ) {
                    unset( $roles[ $role_key ] );
                }
            }
        }
        
        return $roles;
    }
    
    /**
     * Protect admin users from being edited/deleted by temporary admin users
     */
    public function protect_admin_users_edit( $caps, $cap, $user_id, $args ) {
        // Only for edit_user and delete_user capabilities
        if ( ! in_array( $cap, array( 'edit_user', 'delete_user', 'remove_user' ), true ) ) {
            return $caps;
        }
        
        $current_user = wp_get_current_user();
        if ( ! $current_user || ! $current_user->ID ) {
            return $caps;
        }
        
        $is_temp_user = get_user_meta( $current_user->ID, 'wpmastertoolkit_temporary_login', true );
        $protect      = get_user_meta( $current_user->ID, 'wpmtk_protect_other_admin_users', true );
        
        if ( ! $is_temp_user || $protect !== '1' ) {
            return $caps;
        }
        
        // Get the user being edited/deleted
        $target_user_id = isset( $args[0] ) ? absint( $args[0] ) : 0;
        if ( ! $target_user_id ) {
            return $caps;
        }
        
        // Don't block actions on self
        if ( $target_user_id === $current_user->ID ) {
            return $caps;
        }
        
        $target_user = get_user_by( 'id', $target_user_id );
        if ( ! $target_user ) {
            return $caps;
        }
        
        // If target is admin and current user is temp admin with protection, block the action
        if ( in_array( 'administrator', $target_user->roles, true ) ) {
            $caps[] = 'do_not_allow';
        }
        
        return $caps;
    }
    
    /**
     * Delete expired temporary users
     *
     * @return void
     */
    public function delete_expired_temporary_users() {
        $users = $this->get_temporary_users( true );
        
        if ( empty( $users ) ) {
            return;
        }
        
        require_once( ABSPATH . 'wp-admin/includes/user.php' );
        
        foreach ( $users as $user ) {
            wp_delete_user( $user->ID );
        }
    }
    
    /**
     * Schedule the cron job
     *
     * @return void
     */
    public function schedule_cron() {
        if ( ! wp_next_scheduled( 'wpmtk_delete_expired_temp_users' ) ) {
            wp_schedule_event( time(), 'daily', 'wpmtk_delete_expired_temp_users' );
        }
    }
    
    /**
     * Unschedule the cron job
     *
     * @return void
     */
    public function unschedule_cron() {
        $timestamp = wp_next_scheduled( 'wpmtk_delete_expired_temp_users' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'wpmtk_delete_expired_temp_users' );
        }
    }
    
    /**
     * Add a button to create temporary users on the users.php page
     *
     * @return void
     */
    public function add_temp_user_button_on_users_page( $hook ) {
        // Only show on users.php page
        if ( $hook !== 'users.php' && $hook !== 'user-edit.php' ) {
            return;
        }
        
        // Check if user has permission
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        // Check if current user is a temp user
        $current_user         = wp_get_current_user();
        $is_current_temp_user = get_user_meta( $current_user->ID, 'wpmastertoolkit_temporary_login', true );
        
        if ( $is_current_temp_user ) {
            return;
        }
        
        $add_user_url = add_query_arg(
            array(
                'page'   => $this->submenu_page_id,
                'action' => 'add_user',
            ),
            admin_url( 'admin.php' )
        );
        
        // Add inline script to insert button after "Ajouter un compte"
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var usersPageHeading = document.querySelector('.wrap h1');
            if (usersPageHeading) {
                var tempUserButton = document.createElement('a');
                tempUserButton.href = <?php echo wp_json_encode( $add_user_url ); ?>;
                tempUserButton.className = 'page-title-action';
                tempUserButton.textContent = '<?php echo esc_js( esc_html__( 'Add Temporary User', 'wpmastertoolkit' ) ); ?>';
                tempUserButton.innerHTML = '<span class="dashicons dashicons-clock" style="line-height: 1.4; margin-right: 5px;"></span>' + tempUserButton.textContent;
                
                usersPageHeading.appendChild(tempUserButton);
            }
        });
        </script>
        <?php
    }
}
