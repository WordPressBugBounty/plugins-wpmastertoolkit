<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Maintenance Mode
 * Description: 
 * @since 1.3.0
 */
class WPMastertoolkit_Maintenance_Mode {

    private $option_id;
    private $header_title;
    private $nonce_action;
    private $settings;
    private $default_settings;

    /**
     * Invoke the hooks
     */
    public function __construct() {

        $this->option_id    = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_maintenance_mode';
        $this->nonce_action = $this->option_id . '_action';

        add_action( 'init', array( $this, 'class_init' ) );
        add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
        add_action( 'admin_init', array( $this, 'save_submenu' ) );
        add_action( 'template_include', array( $this, 'template_include' ), PHP_INT_MAX );
        add_action( 'admin_bar_menu', array( $this, 'render_toggle_button' ) );
        add_action( 'wp_ajax_wpmastertoolkit_maintenance_mode_adminbar_toggle', array( $this, 'change_maintenance_mode' ) );
    }

    /**
     * Initialize the class
     */
    public function class_init() {
        $this->header_title = esc_html__( 'Maintenance Mode', 'wpmastertoolkit' );
    }

    /**
     * Template include
     */
    public function template_include( $template ) {
		$this->settings = $this->get_settings();

		$enabled = $this->settings['enabled'] ?? '1';
		if ( '1' !== $enabled ) {
			return $template;
		}

        if ( ! is_user_logged_in() && ! is_admin() ) {

            if( wpmastertoolkit_is_pro() && !empty($this->settings['countdown_status']) ) {
                $countdown_end_date = $this->settings['countdown_end_date'] ?? time();
                if( time() >= $countdown_end_date ){
                    $this->settings['enabled']          = '0';
                    $this->settings['countdown_status'] = '0';
                    $this->save_settings( $this->settings );
                    return $template;
                }
            }

            if ( file_exists( WPMASTERTOOLKIT_PLUGIN_PATH . '/admin/templates/maintenance-mode/index.php' ) ) {
                $template =  WPMASTERTOOLKIT_PLUGIN_PATH . '/admin/templates/maintenance-mode/index.php';
            }
        }

        return $template;
    }

	/**
	 * Render toggle button
	 */
	public function render_toggle_button( $wp_admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$this->settings         = $this->get_settings();
		$this->default_settings = $this->get_default_settings();

		$enabled          = $this->settings['enabled'] ?? $this->default_settings['enabled'];
		$show_in_adminbar = $this->settings['show_in_adminbar']['value'] ?? $this->default_settings['show_in_adminbar']['value'];

		if ( 'hide' === $show_in_adminbar ) {
			return;
		}

		$adminbar_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/maintenance-mode-adminbar.asset.php' );
        wp_enqueue_style( 'WPMastertoolkit_maintenance_mode_adminbar', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/maintenance-mode-adminbar.css', array(), $adminbar_assets['version'], 'all' );
        wp_enqueue_script( 'WPMastertoolkit_maintenance_mode_adminbar', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/maintenance-mode-adminbar.js', $adminbar_assets['dependencies'], $adminbar_assets['version'], true );
		wp_localize_script( 'WPMastertoolkit_maintenance_mode_adminbar', 'wpmastertoolkit_maintenance_mode_adminbar', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( $this->nonce_action ),
			'i18n'    => array(
				'turnoff' => esc_html__( 'Are you sure you want to turn off maintenance mode?', 'wpmastertoolkit' ),
				'turnon'  => esc_html__( 'Are you sure you want to turn on maintenance mode?', 'wpmastertoolkit' ),
			),
		));

		$content = '';
		if ( 'show_normal' === $show_in_adminbar ) {
			$content = $this->normal_toggle( $enabled );
		} elseif ( 'show_compact' === $show_in_adminbar ) {
			$content = $this->compact_toggle( $enabled );
		}

		$wp_admin_bar->add_menu( array(
			'id'     => 'wpmtk-maintenance-mode',
			'parent' => 'top-secondary',
			'title'  => $content,
		) );
	}

	/**
	 * Change maintenance mode
	 */
	public function change_maintenance_mode() {

		$nonce = sanitize_text_field( $_POST['nonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, $this->nonce_action ) ) {
			wp_send_json_error( array( 'message' => __( 'Refresh the page and try again.', 'wpmastertoolkit' ) ) );
		}

		$status   = sanitize_text_field( $_POST['status'] ?? '0' );
		$settings = $this->get_settings();
		$settings['enabled'] = $status;
		$this->save_settings( $settings );

		wp_send_json_success( array( 'message' => __( 'Maintenance mode has been turned on.', 'wpmastertoolkit' ) ) );
	}

	/**
	 * Normal toggle HTML
	 */
	private function normal_toggle( $enabled ) {
		ob_start();

		?>
			<div class="wpmastertoolkit-maintenance-mode-normal <?php echo esc_attr( $enabled ? 'active' : '' ); ?>" data-enabled="<?php echo esc_attr( $enabled ); ?>">
				<span class="wpmastertoolkit-maintenance-mode-normal-title"><?php esc_html_e( 'Maintenance Mode', 'wpmastertoolkit' ); ?>:</span>
				<span class="wpmastertoolkit-maintenance-mode-normal-btn off"><?php esc_html_e( 'OFF', 'wpmastertoolkit' ); ?></span>
				<span class="wpmastertoolkit-maintenance-mode-normal-btn on"><?php esc_html_e( 'ON', 'wpmastertoolkit' ); ?></span>
			</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Compact toggle HTML
	 */
	private function compact_toggle( $enabled ) {
		ob_start();

		?>
			<div class="wpmastertoolkit-maintenance-mode-compact <?php echo esc_attr( $enabled ? 'active' : '' ); ?>" data-enabled="<?php echo esc_attr( $enabled ); ?>">
				<?php if ( $enabled ) : ?>
					<span class="wpmastertoolkit-maintenance-mode-compact-btn on"><?php esc_html_e( 'Maintenance', 'wpmastertoolkit' ); ?></span>
				<?php else: ?>
					<span class="wpmastertoolkit-maintenance-mode-compact-btn off"><?php esc_html_e( 'Live', 'wpmastertoolkit' ); ?></span>
				<?php endif; ?>
			</div>
		<?php

		return ob_get_clean();
	}

    /**
     * get_settings
     */
    public function get_settings(){
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
    public function add_submenu(){

        add_submenu_page(
            'wp-mastertoolkit-settings',
            $this->header_title,
            $this->header_title,
            'manage_options',
            'wp-mastertoolkit-settings-maintenance-mode', 
            array( $this, 'render_submenu'),
            null
        );
    }

    /**
     * Render the submenu
     */
    public function render_submenu() {

        wp_enqueue_media();
        wp_enqueue_script('wp-color-picker');
        wp_enqueue_style('wp-color-picker');
        
        $submenu_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/maintenance-mode.asset.php' );
        wp_enqueue_style( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/maintenance-mode.css', array(), $submenu_assets['version'], 'all' );
        wp_enqueue_script( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/maintenance-mode.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );

        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/submenu/header.php';
        $this->submenu_content();
        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/submenu/footer.php';
    }

    /**
     * Save the submenu option
     */
    public function save_submenu() {

		$nonce = sanitize_text_field( $_POST['_wpnonce'] ?? '' );
		
		if ( wp_verify_nonce($nonce, $this->nonce_action) ) {

            $new_settings = $this->sanitize_settings( $_POST[$this->option_id] ?? array() );
            
            $this->save_settings( $new_settings );
            wp_safe_redirect( sanitize_url( $_SERVER['REQUEST_URI'] ?? '' ) );
			exit;
		}
    }

    /**
     * sanitize_settings
     * 
     * @return array
     */
    public function sanitize_settings($new_settings){

        $this->default_settings = $this->get_default_settings();
        $sanitized_settings = array();

        foreach ( $this->default_settings as $settings_key => $settings_value ) {
            
            switch ($settings_key) {

                case 'enabled':
                case 'title_text':
                case 'headline_text':
                case 'footer_text':
                case 'logo_height':
                case 'logo_width':
                    $sanitized_settings[$settings_key] = wp_unslash( sanitize_text_field( $new_settings[$settings_key] ?? '' ) );
                    break;
                case 'body_text':
                    $sanitized_settings[$settings_key] = sanitize_textarea_field( $new_settings[$settings_key] ?? '' );
                    break;
                case 'background_color':
                case 'text_color':
                case 'countdown_text_color':
                case 'countdown_background_color':
                    $sanitized_settings[$settings_key] = sanitize_hex_color( $new_settings[$settings_key] ?? '' );
                    break;
                case 'logo':
                case 'background_image':
                    $sanitized_settings[$settings_key] = esc_url_raw( $new_settings[$settings_key] ?? '' );
                break;
				case 'show_in_adminbar':
					$sanitized_settings[$settings_key]['value'] = sanitize_text_field( $new_settings[$settings_key]['value'] ?? $this->default_settings[$settings_key]['value'] );
				break;
                case 'countdown_status':
                    $is_pro = wpmastertoolkit_is_pro();
                    $sanitized_settings[$settings_key] = $is_pro ? sanitize_text_field( $new_settings[$settings_key] ?? '0' ) : '0';
                break;
                case 'countdown_end_date':
                    $date_string = sanitize_text_field( $new_settings[$settings_key] ?? '' );
                    $date        = new DateTime( $date_string,  wp_timezone() );
                    $timestamp   = $date->getTimestamp() ?? '';
                    $sanitized_settings[$settings_key] = $timestamp;
                break;
            }
        }

        return $sanitized_settings;
    }

    /**
     * get_default_settings
     *
     * @return array
     */
    private function get_default_settings(){
        if( $this->default_settings !== null ) return $this->default_settings;

        return array(
			'enabled'          => '1',
			'show_in_adminbar' => array(
				'value'   => 'show_normal',
				'options' => array(
					'hide'         => __( 'Don\'t see in admin bar', 'wpmastertoolkit' ),
					'show_normal'  => __( 'Show OFF / ON in admin bar', 'wpmastertoolkit' ),
					'show_compact' => __( 'Show compact toggle in admin bar', 'wpmastertoolkit' ),
				),
			),
            'title_text'                 => __( 'Site is undergoing maintenance', 'wpmastertoolkit' ),
            'headline_text'              => __( 'Maintenance Mode', 'wpmastertoolkit' ),
            'body_text'                  => __( 'Site will be available soon. Thank you for your patience!', 'wpmastertoolkit' ),
            'footer_text'                => sprintf( '&copy; %s %s', get_bloginfo('name'), date('Y') ),
            'background_color'           => '#000000',
            'text_color'                 => '#ffffff',
            'logo'                       => '',
            'background_image'           => '',
            'logo_height'                => '180',
            'logo_width'                 => '180',
            'countdown_status'           => '0',
            'countdown_end_date'         => strtotime( '+1 hour' ),
            'countdown_text_color'       => '#000000',
            'countdown_background_color' => '#ffffff',
        );
    }

    /**
     * Add the submenu content
     */
    private function submenu_content() {
        $this->settings         = $this->get_settings();
		$this->default_settings = $this->get_default_settings();

        $is_pro = wpmastertoolkit_is_pro();

		$show_in_adminbar_options = $this->default_settings['show_in_adminbar']['options'];

        $image_placeholder          = WPMASTERTOOLKIT_PLUGIN_URL . 'admin/images/placeholder.svg';
        $enabled                    = $this->settings['enabled'] ?? $this->default_settings['enabled'];
		$show_in_adminbar           = $this->settings['show_in_adminbar']['value'] ?? $this->default_settings['show_in_adminbar']['value'];
        $title_text                 = $this->settings['title_text'] ?? '';
        $headline_text              = $this->settings['headline_text'] ?? '';
        $body_text                  = $this->settings['body_text'] ?? '';
        $footer_text                = $this->settings['footer_text'] ?? '';
        $background_color           = $this->settings['background_color'] ?? '';
        $text_color                 = $this->settings['text_color'] ?? '';
        $logo                       = $this->settings['logo'] ?? '';
        $background_image           = $this->settings['background_image'] ?? '';
        $logo_height                = $this->settings['logo_height'] ?? '';
        $logo_width                 = $this->settings['logo_width'] ?? '';
        $countdown_status           = $is_pro ? $this->settings['countdown_status'] ?? '0' : '0';
        $countdown_end_date         = !empty($this->settings['countdown_end_date']) ? wp_date( 'Y-m-d H:i', $this->settings['countdown_end_date'] ) : '';
        $countdown_min_date         = wp_date( 'Y-m-d H:i', strtotime( '+1 minute' ) );
        $countdown_text_color       = $this->settings['countdown_text_color'] ?? '';
        $countdown_background_color = $this->settings['countdown_background_color'] ?? '';
        

        ?>
            <div class="wp-mastertoolkit__section">
                <div class="wp-mastertoolkit__section__desc"><?php esc_html_e('Show a customizable maintenance page on the frontend while performing a brief maintenance to your site. Logged-in administrators can still view the site as usual.', 'wpmastertoolkit'); ?></div>
                <div class="wp-mastertoolkit__section__body">

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e("Maintenance mode status", 'wpmastertoolkit'); ?></div>
						<div class="wp-mastertoolkit__section__body__item__content">
							<label class="wp-mastertoolkit__toggle">
								<input type="hidden" name="<?php echo esc_attr( $this->option_id . '[enabled]' ); ?>" value="0">
								<input type="checkbox" name="<?php echo esc_attr( $this->option_id . '[enabled]' ); ?>" value="1" <?php checked( $enabled, '1' ); ?>>
								<span class="wp-mastertoolkit__toggle__slider round"></span>
							</label> 
						</div>
                    </div>

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e("Show in admin bar", 'wpmastertoolkit'); ?></div>
						<div class="wp-mastertoolkit__section__body__item__content">
							<div class="wp-mastertoolkit__select">
                                <select name="<?php echo esc_attr( $this->option_id . '[show_in_adminbar][value]' ); ?>">
                                    <?php foreach ( $show_in_adminbar_options as $key => $name ) : ?>
                                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $show_in_adminbar, $key ); ?>><?php echo esc_html( $name ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
						</div>
                    </div>

                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Page Title', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
                            <div class="wp-mastertoolkit__input-text">
                                <input type="text" name="<?php echo esc_attr( $this->option_id . '[title_text]' ); ?>" value="<?php echo esc_attr( $title_text ); ?>" style="width: 400px;">
                            </div>
                        </div>
                    </div>

                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Headline', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
                            <div class="wp-mastertoolkit__input-text">
                                <input type="text" name="<?php echo esc_attr( $this->option_id . '[headline_text]' ); ?>" value="<?php echo esc_attr( $headline_text ); ?>" style="width: 400px;">
                            </div>
                        </div>
                    </div>

                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Body Text', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
                            <div class="wp-mastertoolkit__textarea">
                                <textarea name="<?php echo esc_attr( $this->option_id ); ?>[body_text]" cols="50" rows="3" style="width: 400px;"><?php echo esc_textarea( wp_unslash( $body_text ) ); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Footer Text', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
                            <div class="wp-mastertoolkit__input-text">
                                <input type="text" name="<?php echo esc_attr( $this->option_id . '[footer_text]' ); ?>" value="<?php echo esc_attr( $footer_text ); ?>" style="width: 400px;">
                            </div>
                        </div>
                    </div>

                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Background Color', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
                            <div class="wp-mastertoolkit__input-text">
                                <input type="text" name="<?php echo esc_attr( $this->option_id . '[background_color]' ); ?>" value="<?php echo esc_attr( $background_color ); ?>" class="wp-color-picker"/>
                            </div>
                        </div>
                    </div>

                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Text Color', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
                            <div class="wp-mastertoolkit__input-text">
                                <input type="text" name="<?php echo esc_attr( $this->option_id . '[text_color]' ); ?>" value="<?php echo esc_attr( $text_color ); ?>" class="wp-color-picker"/>
                            </div>
                        </div>
                    </div>

                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Logo', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
                            <div class="wp-mastertoolkit__upload-image">
                                <img class="wp-mastertoolkit__upload-image__preview" src="<?php echo empty( $logo ) ? esc_url( $image_placeholder ) : esc_url( $logo ); ?>" data-default="<?php echo esc_attr( $image_placeholder ); ?>">
                                <div class="wp-mastertoolkit__upload-image__actions">
                                    <a class="wp-mastertoolkit__upload-image__upload" href="javascript:void(0);"><?php _e( "Upload", 'wc-etransactions' ); ?></a>
                                    <a class="wp-mastertoolkit__upload-image__reset <?php echo empty( $logo ) ? '' : 'show'; ?>" href="javascript:void(0);">X</a>
                                </div>
                                <input class="wp-mastertoolkit__upload-image__input" type="hidden" name="<?php echo esc_attr( $this->option_id . '[logo]' ); ?>" value="<?php echo esc_attr( $logo ); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Background Image', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
                            <div class="wp-mastertoolkit__upload-image">
                                <img class="wp-mastertoolkit__upload-image__preview" src="<?php echo empty( $background_image ) ? esc_url( $image_placeholder ) : esc_url( $background_image ); ?>" data-default="<?php echo esc_attr( $image_placeholder ); ?>">
                                <div class="wp-mastertoolkit__upload-image__actions">
                                    <a class="wp-mastertoolkit__upload-image__upload" href="javascript:void(0);"><?php _e( "Upload", 'wc-etransactions' ); ?></a>
                                    <a class="wp-mastertoolkit__upload-image__reset <?php echo empty( $background_image ) ? '' : 'show'; ?>" href="javascript:void(0);">X</a>
                                </div>
                                <input class="wp-mastertoolkit__upload-image__input" type="hidden" name="<?php echo esc_attr( $this->option_id . '[background_image]' ); ?>" value="<?php echo esc_attr( $background_image ); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Logo Size', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
                            <div class="wp-mastertoolkit__input-text">
                                <input type="number" name="<?php echo esc_attr( $this->option_id . '[logo_height]' ); ?>" value="<?php echo esc_attr( $logo_height ); ?>">
                                <input type="number" name="<?php echo esc_attr( $this->option_id . '[logo_width]' ); ?>" value="<?php echo esc_attr( $logo_width ); ?>">
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <div class="wp-mastertoolkit__section pro-section <?php echo esc_attr( $is_pro ? 'is-pro' : 'is-not-pro' ); ?>">
                <div class="wp-mastertoolkit__section__pro-only">
                    <?php esc_html_e( 'This feature is only available in the Pro version.', 'wpmastertoolkit' ); ?>
                </div>
                <div class="wp-mastertoolkit__section__desc">
                    <?php esc_html_e("Show a countdown timer on the maintenance page. When the countdown ends, the maintenance page will be automatically turned off.", 'wpmastertoolkit'); ?>
                </div>
                <div class="wp-mastertoolkit__section__body">
                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e("Countdown status", 'wpmastertoolkit'); ?></div>
						<div class="wp-mastertoolkit__section__body__item__content">
							<label class="wp-mastertoolkit__toggle">
								<input type="hidden" name="<?php echo esc_attr( $this->option_id . '[countdown_status]' ); ?>" value="0">
								<input type="checkbox" name="<?php echo esc_attr( $this->option_id . '[countdown_status]' ); ?>" value="1" <?php checked( $countdown_status, '1' ); ?> <?php echo esc_attr( $is_pro ? '' : 'disabled' ); ?>>
								<span class="wp-mastertoolkit__toggle__slider round"></span>
							</label> 
						</div>
                    </div>

                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e("End date", 'wpmastertoolkit'); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
                            <div class="wp-mastertoolkit__input-text">
                                <input type="datetime-local" name="<?php echo esc_attr( $this->option_id . '[countdown_end_date]' ); ?>" value="<?php echo esc_attr( $countdown_end_date ); ?>" min="<?php echo esc_attr( $countdown_min_date ); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Countdown Text Color', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
                            <div class="wp-mastertoolkit__input-text">
                                <input type="text" name="<?php echo esc_attr( $this->option_id . '[countdown_text_color]' ); ?>" value="<?php echo esc_attr( $countdown_text_color ); ?>" class="wp-color-picker"/>
                            </div>
                        </div>
                    </div>
                    
                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Countdown Background Color', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
                            <div class="wp-mastertoolkit__input-text">
                                <input type="text" name="<?php echo esc_attr( $this->option_id . '[countdown_background_color]' ); ?>" value="<?php echo esc_attr( $countdown_background_color ); ?>" class="wp-color-picker"/>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php
    }
}
