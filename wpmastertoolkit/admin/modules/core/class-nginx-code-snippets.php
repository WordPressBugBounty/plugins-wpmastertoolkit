<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Nginx code snippets
 * Description: This module will activated only if the server is Nginx
 * @since 1.0.0
 */
class WPMastertoolkit_Nginx_Code_Snippets {

    public $option_id;
    private $header_title;

    /**
     * Invoke the hooks.
     * 
     * @since   1.0.0
     */
    public function __construct() {
        global $is_nginx;

        $this->option_id    = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_nginx_code_snippets';

        add_action( 'init', array( $this, 'class_init' ) );

        if ( $is_nginx ) {
            add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
        }
    }

    /**
     * Initialize the class
     */
    public function class_init() {
        $this->header_title = esc_html__( 'Nginx Code Snippets', 'wpmastertoolkit' );
    }

    /**
     * Add a submenu
     * 
     * @since   1.0.0
     */
    public function add_submenu(){

        /**
         * Filter the settings
         *
         * @since 1.0.0
         *
         * @param array $settings
         */
        $settings = apply_filters( 'wpmastertoolkit_nginx_code_snippets', array() );

        if ( !empty($settings) ) {

            WPMastertoolkit_Settings::add_submenu_page(
            'wp-mastertoolkit-settings',
                $this->header_title,
                $this->header_title,
                'manage_options',
                'wp-mastertoolkit-settings-nginx-code-snippets', 
                array( $this, 'render_submenu'),
                null
            );
        }
    }

    /**
     * Render the submenu
     * 
     * @since   1.0.0
     */
    public function render_submenu() {

        $submenu_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/nginx-code-snippets.asset.php' );
        wp_enqueue_style( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/nginx-code-snippets.css', array(), $submenu_assets['version'], 'all' );
        wp_enqueue_script( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/nginx-code-snippets.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );

        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
        $this->submenu_content();
        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/footer.php';
    }

    /**
     * Add the submenu content
     * 
     * @since   1.0.0
     */
    private function submenu_content() {

        /**
         * Filter the settings
         *
         * @since 1.0.0
         *
         * @param array $settings
         */
        $settings = apply_filters( 'wpmastertoolkit_nginx_code_snippets', array() );

        ?>
            <div class="wp-mastertoolkit__section">
                <div class="wp-mastertoolkit__section__desc"><?php esc_html_e( 'Past this code snippets to your .conf file.', 'wpmastertoolkit'); ?></div>
                <div class="wp-mastertoolkit__section__body">

                    <?php foreach ( $settings as $module_id => $code_snippet ) :?>

                        <div class="wp-mastertoolkit__section__body__item">
                            <div class="wp-mastertoolkit__section__body__item__title"><?php echo esc_html($module_id); ?></div>
                            <div class="wp-mastertoolkit__section__body__item__content">
                                <div class="wp-mastertoolkit__textarea">
                                    <span title="<?php esc_attr_e('Copy', 'wpmastertoolkit'); ?>" class="wp-mastertoolkit__textarea__copy">
									    <?php echo wp_kses( file_get_contents(WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/copy.svg'), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
                                    </span>
                                    <textarea class="auto-height" readonly><?php echo esc_textarea( stripslashes( $code_snippet ) ); ?></textarea>
                                </div>
                            </div>
                        </div>

                    <?php endforeach; ?>

                </div>
            </div>

        <?php
    }

}