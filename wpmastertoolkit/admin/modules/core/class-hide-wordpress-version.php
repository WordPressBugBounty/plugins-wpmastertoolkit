<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Hide WordPress Version
 * Description: Hide the WordPress version from the source code.
 * @since 1.1.0
 */
class WPMastertoolkit_Hide_WordPress_Version {
    
    const MODULE_ID = 'Hide WordPress Version';
    private $wp_version;

    /**
	 * Invoke Wp Hooks
	 *
	 * @since    1.0.0
	 */
    public function __construct() {
        $actions = array( 
            'app_head',
            'atom_head', 
            'comments_atom_head', 
            'commentsrss2_head', 
            'opml_head', 
            'rdf_header', 
            'rss_head', 
            'rss2_head', 
            'wp_head', 
        );
        foreach (  $actions as $action ) {
            remove_action( $action, 'the_generator' );
        }
        
        add_filter( 'the_generator', '__return_empty_string', PHP_INT_MAX );

        add_filter( 'script_loader_src', array( $this, 'replace_wordpress_version_in_source' ), PHP_INT_MAX );
        add_filter( 'style_loader_src',  array( $this, 'replace_wordpress_version_in_source' ), PHP_INT_MAX );

        add_filter( 'update_footer', array( $this, 'remove_wordpress_version_in_footer' ), 11 );
        
    }
    
    /**
     * get_wordpress_version
     *
     * @return void
     */
    private function get_wordpress_version(){
        if ( ! $this->wp_version ) {
            $this->wp_version = get_bloginfo( 'version' );
        }
        return $this->wp_version;
    }

    /**
     * replace_wordpress_version_in_source
     *
     * @param  mixed $source
     * @return void
     */
    public function replace_wordpress_version_in_source( $source ) {
        $version = $this->get_wordpress_version();
        $hash = substr( md5( $version ), 0, 4 );
    
        return str_replace( 'ver=' . $version, 'ver=' . $hash, $source );
    }
    
    /**
     * remove_wordpress_version_in_footer
     *
     * @param  mixed $footer
     * @return void
     */
    public function remove_wordpress_version_in_footer( $footer ) {
        $version = $this->get_wordpress_version();

        return str_replace( sprintf( __( 'Version %s' ), $version ), '', $footer );
    }
}