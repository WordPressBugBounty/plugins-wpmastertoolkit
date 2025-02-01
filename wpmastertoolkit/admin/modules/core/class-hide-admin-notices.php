<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Hide Admin Notices
 * Description: Hide admin notices from the admin dashboard
 * @since 1.0.0
 */
class WPMastertoolkit_Hide_Admin_Notices {

    /**
	 * Invoke Wp Hooks
	 *
	 * @since    1.0.0
	 */
    public function __construct() {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'wp_before_admin_bar_render', array( $this, 'admin_bar' ) );
        add_action( 'admin_footer', array( $this, 'render_modal' ) );        
    }

    /**
     * Enqueue the necessary scripts and styles
     */
    public function enqueue_scripts() {
        $assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/hide-admin-notices.asset.php' );
        wp_enqueue_style( 'WPMastertoolkit_hide_admin_notices', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/hide-admin-notices.css', array(), $assets['version'], 'all' );
        wp_enqueue_script( 'WPMastertoolkit_hide_admin_notices', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/hide-admin-notices.js', $assets['dependencies'], $assets['version'], true );
    }
    
    /**
     * admin_bar
     *
     * @return void
     */
    public function admin_bar() {
        if ( ! is_admin() ) return;

        global $wp_admin_bar;

        $wp_admin_bar->add_menu( array(
            'id'    => 'wpmastertoolkit-hide-admin-notices-admin-bar',
            'title' => '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="currentColor" class="bi bi-bell-fill" viewBox="0 0 16 16"><path d="M8 16a2 2 0 0 0 2-2H6a2 2 0 0 0 2 2m.995-14.901a1 1 0 1 0-1.99 0A5 5 0 0 0 3 6c0 1.098-.5 6-2 7h14c-1.5-1-2-5.902-2-7 0-2.42-1.72-4.44-4.005-4.901"/></svg><div class="wpmastertoolkit-admin-bar-notice-count">0</div>',
            'href'  => false,
            'parent' => 'top-secondary',
            'meta'  => array(
                'title' => 'Notice',
                'onclick' => 'event.preventDefault(); document.getElementById("wpmastertoolkit-notices-modal").classList.add("is-active");',
            ),
        ));
    }

    /**
     * Render the modal for admin notices
     */
    public function render_modal() {
        ?>
        <div id="wpmastertoolkit-notices-modal" class="wpmastertoolkit-modal">
            <div class="wpmastertoolkit-modal-content">
                <button class="wpmastertoolkit-modal-close" onclick="document.getElementById('wpmastertoolkit-notices-modal').classList.remove('is-active');">✖</button>
                <h2><?php esc_html_e( 'Admin Notices', 'wpmastertoolkit' ); ?></h2>
                <div class="wpmastertoolkit-modal-body">
                    <?php 
                    /**
                     * Fires in the modal body
                     *
                     * @since 2.0.0
                     */
                    do_action( 'wpmastertoolkit/hide_admin_notices/modal_body' ); 
                    ?>
                </div>
            </div>
        </div>
        <?php
    }
}