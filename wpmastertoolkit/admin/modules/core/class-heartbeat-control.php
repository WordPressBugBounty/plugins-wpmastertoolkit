<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Heartbeat Control
 * Description: Modify the interval of the WordPress heartbeat API or disable it on admin pages, post creation/edit screens and/or the frontend. This will help reduce CPU load on the server.
 * @since 1.5.0
 */
class WPMastertoolkit_Heartbeat_Control {

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
        $this->option_id    = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_heartbeat_control';
        $this->nonce_action = $this->option_id . '_action';

        add_action( 'init', array( $this, 'class_init' ) );
        add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
        add_action( 'admin_init', array( $this, 'save_submenu' ) );
		add_filter( 'heartbeat_settings', array( $this, 'maybe_modify_heartbeat_frequency' ), 99 );
		add_action( 'admin_enqueue_scripts', array( $this, 'maybe_disable_heartbeat' ), 99 );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_disable_heartbeat' ), 99 );
    }

	/**
	 * Modify the heartbeat frequency
	 * 
	 * @since 1.5.0
	 */
	public function maybe_modify_heartbeat_frequency( $heartbeat_settings ) {
		global $pagenow;

		if ( wp_doing_cron() ) {
			return $heartbeat_settings;
		}

		$settings               = $this->get_settings();
		$admin_pages_controle   = $settings['admin_pages']['controle']['value'] ?? '';
		$post_creation_controle = $settings['post_creation']['controle']['value'] ?? '';
		$frontend_controle      = $settings['frontend']['controle']['value'] ?? '';
		$admin_pages_interval   = $settings['admin_pages']['interval']['value'] ?? '';
		$post_creation_interval = $settings['post_creation']['interval']['value'] ?? '';
		$frontend_interval      = $settings['frontend']['interval']['value'] ?? '';

		// Disable heartbeat autostart
		$heartbeat_settings['autostart'] = false;

		if ( is_admin() ) {
			if ( 'post.php' == $pagenow || 'post-new.php' == $pagenow ) {
				if ( 'modify' == $post_creation_controle ) {
					$heartbeat_settings['minimalInterval'] = absint( $post_creation_interval );
				}
			} else {
				if ( 'modify' == $admin_pages_controle ) {
					$heartbeat_settings['minimalInterval'] = absint( $admin_pages_interval );
				}
			}
		} else {
			if ( 'modify' == $frontend_controle ) {
				$heartbeat_settings['minimalInterval'] = absint( $frontend_interval );
			}
		}

		return $heartbeat_settings;
	}

	/**
	 * Disable the heartbeat
	 * 
	 * @since 1.5.0
	 */
	public function maybe_disable_heartbeat() {
		global $pagenow;

		$settings      = $this->get_settings();
		$admin_pages   = $settings['admin_pages']['controle']['value'] ?? '';
		$post_creation = $settings['post_creation']['controle']['value'] ?? '';
		$frontend      = $settings['frontend']['controle']['value'] ?? '';

		if ( is_admin() ) {
			if ( 'post.php' == $pagenow || 'post-new.php' == $pagenow ) {
				if ( 'disable' == $post_creation ) {
					wp_deregister_script( 'heartbeat' );
					return;
				}
			} else {
				if ( 'disable' == $admin_pages ) {
					wp_deregister_script( 'heartbeat' );
					return;
				}
			}
		} else {
			if ( 'disable' == $frontend ) {
				wp_deregister_script( 'heartbeat' );
				return;
			}
		}
	}

	/**
     * Initialize the class
     * 
     * @since    1.5.0
     * @return   void
     */
    public function class_init() {
        $this->header_title = esc_html__( 'Heartbeat Control', 'wpmastertoolkit' );
    }

	/**
     * Add a submenu
     * 
     * @since   1.5.0
     */
    public function add_submenu(){
        add_submenu_page(
            'wp-mastertoolkit-settings',
            $this->header_title,
            $this->header_title,
            'manage_options',
            'wp-mastertoolkit-settings-heartbeat-control',
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
        $submenu_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/heartbeat-controle.asset.php' );
        wp_enqueue_style( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/heartbeat-controle.css', array(), $submenu_assets['version'], 'all' );
        wp_enqueue_script( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/heartbeat-controle.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );

        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/submenu/header.php';
        $this->submenu_content();
        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/submenu/footer.php';
    }

	/**
     * Save the submenu option
     * 
     * @since   1.4.0
     */
    public function save_submenu() {
		$nonce = sanitize_text_field( $_POST['_wpnonce'] ?? '' );

		if ( wp_verify_nonce( $nonce, $this->nonce_action ) ) {
            $new_settings = $this->sanitize_settings( $_POST[ $this->option_id ] ?? array() );
            $this->save_settings( $new_settings );
            wp_safe_redirect( sanitize_url( $_SERVER['REQUEST_URI'] ?? '' ) );
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
        	foreach ( $settings_value as $settings_value_key => $settings_value_value ) {
				$sanitized_settings[ $settings_key ][ $settings_value_key ][ 'value' ] = sanitize_text_field( $new_settings[ $settings_key ][ $settings_value_key ][ 'value' ] ?? $settings_value_value['value'] );
			}
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
			'admin_pages'   => array(
				'controle' => array(
					'value'   => 'default',
					'options' => array(
						'default' => __( 'Keep as is', 'wpmastertoolkit' ),
						'modify'  => __( 'Modify', 'wpmastertoolkit' ),
						'disable' => __( 'Disable', 'wpmastertoolkit' ),
					),
				),
				'interval' => array(
					'value'   => '60',
					'options' => array(
						'15'  => __( '15 seconds', 'wpmastertoolkit' ),
						'30'  => __( '30 seconds', 'wpmastertoolkit' ),
						'60'  => __( '1 minute', 'wpmastertoolkit' ),
						'120' => __( '2 minutes', 'wpmastertoolkit' ),
						'180' => __( '3 minutes', 'wpmastertoolkit' ),
						'300' => __( '5 minutes', 'wpmastertoolkit' ),
						'600' => __( '10 minutes', 'wpmastertoolkit' ),
					),
				),
			),
			'post_creation'   => array(
				'controle' => array(
					'value'   => 'default',
					'options' => array(
						'default' => __( 'Keep as is', 'wpmastertoolkit' ),
						'modify'  => __( 'Modify', 'wpmastertoolkit' ),
						'disable' => __( 'Disable', 'wpmastertoolkit' ),
					),
				),
				'interval' => array(
					'value'   => '60',
					'options' => array(
						'15'  => __( '15 seconds', 'wpmastertoolkit' ),
						'30'  => __( '30 seconds', 'wpmastertoolkit' ),
						'45'  => __( '45 seconds', 'wpmastertoolkit' ),
						'60'  => __( '60 seconds', 'wpmastertoolkit' ),
						'90'  => __( '90 seconds', 'wpmastertoolkit' ),
						'120' => __( '120 seconds', 'wpmastertoolkit' ),
					),
				),
			),
			'frontend'   => array(
				'controle' => array(
					'value'   => 'default',
					'options' => array(
						'default' => __( 'Keep as is', 'wpmastertoolkit' ),
						'modify'  => __( 'Modify', 'wpmastertoolkit' ),
						'disable' => __( 'Disable', 'wpmastertoolkit' ),
					),
				),
				'interval' => array(
					'value'   => '60',
					'options' => array(
						'15'  => __( '15 seconds', 'wpmastertoolkit' ),
						'30'  => __( '30 seconds', 'wpmastertoolkit' ),
						'60'  => __( '1 minute', 'wpmastertoolkit' ),
						'120' => __( '2 minutes', 'wpmastertoolkit' ),
						'180' => __( '3 minutes', 'wpmastertoolkit' ),
						'300' => __( '5 minutes', 'wpmastertoolkit' ),
						'600' => __( '10 minutes', 'wpmastertoolkit' ),
					),
				),
			),
        );
    }

	/**
     * Add the submenu content
     * 
     * @since   1.5.0
     * @return void
     */
    private function submenu_content() {
        $this->settings   = $this->get_settings();
		$default_settings = $this->get_default_settings();

		$default_admin_pages_controle_options   = $default_settings['admin_pages']['controle']['options'];
		$default_admin_pages_interval_options   = $default_settings['admin_pages']['interval']['options'];
		$default_post_creation_controle_options = $default_settings['post_creation']['controle']['options'];
		$default_post_creation_interval_options = $default_settings['post_creation']['interval']['options'];
		$default_frontend_controle_options      = $default_settings['frontend']['controle']['options'];
		$default_frontend_interval_options      = $default_settings['frontend']['interval']['options'];

        $admin_pages_controle   = $this->settings['admin_pages']['controle']['value'] ?? $default_settings['admin_pages']['controle']['value'];
		$admin_pages_interval   = $this->settings['admin_pages']['interval']['value'] ?? $default_settings['admin_pages']['interval']['value'];
        $post_creation_controle = $this->settings['post_creation']['controle']['value'] ?? $default_settings['post_creation']['controle']['value'];
		$post_creation_interval = $this->settings['post_creation']['interval']['value'] ?? $default_settings['post_creation']['interval']['value'];
        $frontend_controle      = $this->settings['frontend']['controle']['value'] ?? $default_settings['frontend']['controle']['value'];
		$frontend_interval      = $this->settings['frontend']['interval']['value'] ?? $default_settings['frontend']['interval']['value'];
        ?>
            <div class="wp-mastertoolkit__section">
                <div class="wp-mastertoolkit__section__desc">
					<?php esc_html_e( "Modify the interval of the WordPress heartbeat API or disable it on admin pages, post creation/edit screens and/or the frontend. This will help reduce CPU load on the server.", 'wpmastertoolkit'); ?>
				</div>
                <div class="wp-mastertoolkit__section__body">

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'On admin pages', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
							<div class="wp-mastertoolkit__radio">
								<?php foreach ( $default_admin_pages_controle_options as $key => $name ) : ?>
									<label class="wp-mastertoolkit__radio__label">
										<input type="radio" name="<?php echo esc_attr( $this->option_id . '[admin_pages][controle][value]' ); ?>" value="<?php echo esc_attr( $key ); ?>" <?php checked( $admin_pages_controle, $key ); ?>>
										<span class="mark"></span>
										<span class="wp-mastertoolkit__checkbox__label__text"><?php echo esc_html( $name ); ?></span>
									</label>
								<?php endforeach; ?>
                            </div>
							<div class="wp-mastertoolkit__select" <?php echo $admin_pages_controle !== 'modify' ? 'style="display: none;"' : ''; ?>>
								<span><?php esc_html_e( 'Set interval to once every', 'wpmastertoolkit' ); ?></span>
                                <select name="<?php echo esc_attr( $this->option_id . '[admin_pages][interval][value]' ); ?>">
                                    <?php foreach ( $default_admin_pages_interval_options as $key => $name ) : ?>
                                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $admin_pages_interval, $key ); ?>><?php echo esc_html( $name ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'On post creation and edit screens', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
							<div class="wp-mastertoolkit__radio">
								<?php foreach ( $default_post_creation_controle_options as $key => $name ) : ?>
									<label class="wp-mastertoolkit__radio__label">
										<input type="radio" name="<?php echo esc_attr( $this->option_id . '[post_creation][controle][value]' ); ?>" value="<?php echo esc_attr( $key ); ?>" <?php checked( $post_creation_controle, $key ); ?>>
										<span class="mark"></span>
										<span class="wp-mastertoolkit__checkbox__label__text"><?php echo esc_html( $name ); ?></span>
									</label>
								<?php endforeach; ?>
                            </div>
							<div class="wp-mastertoolkit__select" <?php echo $post_creation_controle !== 'modify' ? 'style="display: none;"' : ''; ?>>
								<span><?php esc_html_e( 'Set interval to once every', 'wpmastertoolkit' ); ?></span>
                                <select name="<?php echo esc_attr( $this->option_id . '[post_creation][interval][value]' ); ?>">
                                    <?php foreach ( $default_post_creation_interval_options as $key => $name ) : ?>
                                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $post_creation_interval, $key ); ?>><?php echo esc_html( $name ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'On the frontend', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
							<div class="wp-mastertoolkit__radio">
								<?php foreach ( $default_frontend_controle_options as $key => $name ) : ?>
									<label class="wp-mastertoolkit__radio__label">
										<input type="radio" name="<?php echo esc_attr( $this->option_id . '[frontend][controle][value]' ); ?>" value="<?php echo esc_attr( $key ); ?>" <?php checked( $frontend_controle, $key ); ?>>
										<span class="mark"></span>
										<span class="wp-mastertoolkit__checkbox__label__text"><?php echo esc_html( $name ); ?></span>
									</label>
								<?php endforeach; ?>
                            </div>
							<div class="wp-mastertoolkit__select" <?php echo $frontend_controle !== 'modify' ? 'style="display: none;"' : ''; ?>>
								<span><?php esc_html_e( 'Set interval to once every', 'wpmastertoolkit' ); ?></span>
                                <select name="<?php echo esc_attr( $this->option_id . '[frontend][interval][value]' ); ?>">
                                    <?php foreach ( $default_frontend_interval_options as $key => $name ) : ?>
                                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $frontend_interval, $key ); ?>><?php echo esc_html( $name ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        <?php
    }
}
