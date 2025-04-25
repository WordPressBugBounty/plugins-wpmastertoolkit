<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Wider Admin Menu
 * Description: 
 * @since 1.3.0
 */
class WPMastertoolkit_Wider_Admin_Menu {

    private $option_id;
    private $header_title;
    private $nonce_action;
    private $settings;
    private $default_settings;

    /**
     * Invoke the hooks
     * 
     */
    public function __construct() {

        $this->option_id        = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_wider_admin_menu';
        $this->nonce_action     = $this->option_id . '_action';

        add_action( 'init', array( $this, 'class_init' ) );
        add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
        add_action( 'admin_init', array( $this, 'save_submenu' ) );
        add_action( 'admin_head', array( $this, 'set_menu_width' ), 999 );
    }

    /**
     * Initialize the class
     */
    public function class_init() {
        $this->header_title = esc_html__( 'Wider Admin Menu', 'wpmastertoolkit' );
    }

    /**
     * Set the admi menu width
     */
    public function set_menu_width() {

        $default_settings = $this->get_default_settings();
        $settings   = $this->get_settings();
        $width      = $settings['width']['value'] ?? $default_settings['width']['value'];

        if ( ! is_rtl() ) {
            $margin   = 'margin-left';
            $position = 'left';
        } else {
            $margin   = 'margin-right';
            $position = 'right';
        }

		$styles = "
            #wpcontent,
			#wpfooter {
                {$margin}: {$width}px;
            }
			#adminmenu,
			#adminmenu .wp-submenu,
            #adminmenuback,
			#adminmenuwrap {
                width: {$width}px;
            }
            #adminmenu .wp-submenu {
                {$position}: {$width}px;
            }
            #adminmenu .wp-not-current-submenu .wp-submenu,
			.folded #adminmenu .wp-has-current-submenu .wp-submenu {
                min-width: {$width}px;
            }
			@media (min-width: 783px) {
				.interface-interface-skeleton {
					{$position}: {$width}px;
				}
            }
			@media (min-width: 961px) {
				.interface-interface-skeleton {
					{$position}: {$width}px;
				}
				.auto-fold:not(.folded) .interface-interface-skeleton {
					{$position}: {$width}px;
				}
				.woocommerce-layout__header {
                	width: calc(100% - {$width}px);
                }
            }
        ";

        ?>
            <style>
                <?php echo esc_html( $styles ); ?>
            </style>
        <?php        
    }

    /**
     * get_settings
     *
     * @return void
     */
    public function get_settings(){
        $this->default_settings = $this->get_default_settings();
        return get_option( $this->option_id, $this->default_settings );
    }

    /**
     * Save settings
     */
    public function save_settings( $new_settings ) {

		update_option( $this->option_id, $new_settings );
    }

    /**
     * Add a submenu
     *
     */
    public function add_submenu(){

        add_submenu_page(
            'wp-mastertoolkit-settings',
            $this->header_title,
            $this->header_title,
            'manage_options',
            'wp-mastertoolkit-settings-wider-admin-menu',
            array( $this, 'render_submenu'),
            null
        );
    }

    /**
     * Render the submenu
     * 
     */
    public function render_submenu() {

        $submenu_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/wider-admin-menu.asset.php' );
        wp_enqueue_style( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/wider-admin-menu.css', array(), $submenu_assets['version'], 'all' );
        wp_enqueue_script( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/wider-admin-menu.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );

        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
        $this->submenu_content();
        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/footer.php';
    }

    /**
     * Save the submenu option
     *
     */
    public function save_submenu() {

		$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
		
		if ( wp_verify_nonce($nonce, $this->nonce_action) ) {
			//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $new_settings = $this->sanitize_settings( wp_unslash( $_POST[$this->option_id] ?? array() ) );
            
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
    public function sanitize_settings($new_settings){

        $this->default_settings = $this->get_default_settings();
        $sanitized_settings = array();

        foreach ( $this->default_settings as $settings_key => $settings_value ) {
            
            switch ($settings_key) {
                case 'width':
                    $sanitized_settings[$settings_key]['value'] = sanitize_text_field( $new_settings[$settings_key]['value'] ?? $this->default_settings[$settings_key]['value'] );
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
            'width' => array(
                'value'   => '180',
                'options' => array(
                    '180' => '180px',
                    '200' => '200px',
                    '220' => '220px',
                    '240' => '240px',
                    '260' => '260px',
                    '280' => '280px',
                    '300' => '300px',
                ),
            ),
        );
    }

    /**
     * Add the submenu content
     *
     */
    private function submenu_content() {
        $this->settings   = $this->get_settings();
        $default_settings = $this->get_default_settings();
        $width            = $this->settings['width']['value'] ?? '';
        $options          = $default_settings['width']['options'] ?? array();

        ?>
            <div class="wp-mastertoolkit__section">
                <div class="wp-mastertoolkit__section__desc"><?php esc_html_e('Give the admin menu more room to better accommodate wider items.', 'wpmastertoolkit'); ?></div>
                <div class="wp-mastertoolkit__section__body">
                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Set width to', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
                            <div class="wp-mastertoolkit__select">
                                <select name="<?php echo esc_attr( $this->option_id . '[width][value]' ); ?>">
                                    <?php foreach ( $options as $key => $name ) : ?>
                                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $width, $key ); ?>><?php echo esc_html( $name ); ?></option>
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
