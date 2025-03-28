<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Force SSL
 * Description: Force all requests to HTTPS.
 * @since 2.4.0
 */
class WPMastertoolkit_Force_SSL {

    const MODULE_ID = 'Force SSL';
    
    /**
     * __construct
     *
     * @return void
     */
    public function __construct() {
        add_action( 'init', array( $this, 'redirect_to_ssl' ), 1 );
        add_filter( 'option_siteurl', array( $this, 'force_https_urls' ) );
        add_filter( 'option_home', array( $this, 'force_https_urls' ) );
        add_filter( 'wpmastertoolkit_nginx_code_snippets', array( $this, 'nginx_code_snippets' ) );
    }
    
    /**
     * redirect_to_ssl
     *
     * @return void
     */
    public function redirect_to_ssl() {

		$host = sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ?? '' ) );
		$uri  = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );

        if ( ! is_ssl() ) {
            wp_redirect( 'https://' . $host . $uri, 301 );
            exit;
        }
    }
    
    /**
     * force_https_urls
     *
     * @param  mixed $url
     * @return string
     */
    public function force_https_urls( $url ) {
        return str_replace( 'http://', 'https://', $url );
    }
    
    /**
     * activate
     *
     * @return void
     */
    public static function activate() {
        global $is_apache, $is_nginx;

        require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/class-wp-config.php';

        WPMastertoolkit_WP_Config::replace_or_add_constant( 'FORCE_SSL_ADMIN', true );
        WPMastertoolkit_WP_Config::replace_or_add_constant( 'FORCE_SSL_LOGIN', true );

        update_option( 'siteurl', str_replace( 'http://', 'https://', get_option( 'siteurl' ) ) );
        update_option( 'home', str_replace( 'http://', 'https://', get_option( 'home' ) ) );

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
    public static function deactivate() {
        global $is_apache;

        require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/class-wp-config.php';

        WPMastertoolkit_WP_Config::remove_constant( 'FORCE_SSL_ADMIN' );
        WPMastertoolkit_WP_Config::remove_constant( 'FORCE_SSL_LOGIN' );

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
     * get_raw_content_htaccess
     *
     * @return string
     */
    private static function get_raw_content_htaccess() {
        return trim("
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{HTTPS} !=on
    RewriteRule ^(.*)$ https://%{HTTP_HOST}/$1 [R=301,L]
</IfModule>
        ");
    }
    
    
    /**
     * get_raw_content_nginx
     *
     * @return string
     */
    private static function get_raw_content_nginx() {
        $domain = wp_parse_url( get_home_url(), PHP_URL_HOST );

        return trim("
server {
    listen 80;
    server_name {$domain};
    return 301 https://\$host\$request_uri;
}
        ");
    }
}
