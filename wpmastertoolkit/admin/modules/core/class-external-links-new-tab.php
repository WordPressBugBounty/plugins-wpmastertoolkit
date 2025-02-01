<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: External Links New Tabs
 * Description: Open external links in new tabs
 * @since 1.0.0
 */
class WPMastertoolkit_External_Links_New_Tabs {

    /**
     * Invoke Wp Hooks
	 *
     * @since    1.0.0
	 */
    public function __construct() {
        
        add_filter( 'the_content', array( $this, 'filter_content' ) );
    }

    /**
     * Add target="_blank" rel="noopener noreferrer nofollow" to external links
     * 
     * @since   1.0.0
     */
    public function filter_content( $content ) {

        if ( !empty($content) ) {

            $regexp         = "<a\\s[^>]*href=(\"??)([^\" >]*?)\\1[^>]*>";
            $matches_count  = preg_match_all( "/{$regexp}/siU", $content, $matches, PREG_SET_ORDER );

            if ( $matches_count && $matches_count > 0 ) {

                if ( is_array( $matches ) ) {

                    foreach ( $matches as $match ) {

                        $original_tag   = $match[0];
                        $tag            = $match[0];
                        $url            = $match[2];

                        if ( strpos( $url, get_site_url() ) === false && strpos( $url, 'http' ) !== false ) {
                            
                            $pattern = '/target\\s*=\\s*"\\s*_(blank|parent|self|top)\\s*"/';
                            if ( preg_match( $pattern, $tag ) === 0 ) {
                                $tag = substr_replace( $tag, ' target="_blank">', -1 );
                            }

                            $pattern = '/rel\\s*=\\s*\\"[a-zA-Z0-9_\\s]*\\"/';
                            if ( preg_match( $pattern, $tag ) === 0 ) {
                                $tag = substr_replace( $tag, ' rel="noopener noreferrer nofollow">', -1 );
                            }
                            
                            $content = str_replace( $original_tag, $tag, $content );
                        }
                    }
                }
            }
        }

        return $content;
    }

}