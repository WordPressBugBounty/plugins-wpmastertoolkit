<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Hide PHP Versions
 * Description: 
 * @since 1.3.0
 */
class WPMastertoolkit_Hide_PHP_Versions {

    const MODULE_ID = 'Hide PHP Versions';

    /**
     * Invoke the hooks
     * 
     */
    public function __construct() {

        if ( ! headers_sent() ) {
            header_remove( 'X-Powered-By' );
        }

        add_filter( 'wpmastertoolkit_nginx_code_snippets', array( $this, 'nginx_code_snippets' ) );
    }

    /**
     * activate
     *
     * @return void
     */
    public static function activate(){
        global $is_apache;

        if ( $is_apache ) {

            require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/class-htaccess.php';
            WPMastertoolkit_Htaccess::add( self::get_raw_content_htaccess(), self::MODULE_ID );
        }
    }

    /**
     * deactivate
     *
     * @return void
     */
    public static function deactivate(){
        global $is_apache;

        if ( $is_apache ) {

            require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/class-htaccess.php';
            WPMastertoolkit_Htaccess::remove( self::MODULE_ID );
        }
    }

    /**
     * nginx_code_snippets
     *
     * @param  mixed $code_snippets
     * @return array
     */
    public function nginx_code_snippets( $code_snippets ) {
        global $is_nginx;

        if ( $is_nginx ) {
            $code_snippets[self::MODULE_ID] = self::get_raw_content_nginx();
        }

        return $code_snippets;
    }

    /**
     * Content of the .htaccess file
     */
    private static function get_raw_content_htaccess() {

        $content  = "<IfModule mod_headers.c>";
        $content .= "\n\tHeader unset X-Powered-By";
        $content .= "\n</IfModule>";

        return trim( $content );
    }

    /**
     * Content of the nginx.conf file
     */
    private function get_raw_content_nginx() {

        $content    = "server {";
        $content    .= "\n\tserver_tokens off;";
        $content   .= "\n\tproxy_hide_header X-Powered-By;";
        $content   .= "\n\tfastcgi_hide_header X-Powered-By;";
        $content   .= "\n}";

        return trim( $content );
    }
}
