<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Disallow Plugin Upload
 * Description: Disallow plugin upload in the admin area for better security
 * @since 1.0.0
 */
class WPMastertoolkit_Disallow_Plugin_Upload {

    /**
     * Invoke the hooks.
     * 
     * @since   1.0.0
     */
    public function __construct() {
        add_filter('wp_handle_upload_prefilter', [$this, 'disable_zip_uploads']);
        add_action('admin_print_styles-plugin-install.php', [$this, 'add_style']);
    }
    
    /**
     * disable_zip_uploads
     *
     * @param  mixed $file
     * @return void
     */
    public function disable_zip_uploads($file) {

		//phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (isset( $_FILES['pluginzip'] )) {
            if (pathinfo($file['name'], PATHINFO_EXTENSION) === 'zip') {
                $file['type'] = 'disallowed';
                $file['error'] = esc_html__( "Plugin upload is not allowed.", 'wpmastertoolkit' );
            }
        }
        return $file;
    }

    /**
     * add_style
     *
     * @return void
     */
    public function add_style() {
        ?>
        <style>
            .upload-view-toggle {
                display: none !important;
            }
        </style>
        <?php
    }

}