<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Disable emoji support
 * Description: 
 * @since 1.3.0
 */
class WPMastertoolkit_Disable_Emoji_Support {

    /**
     * Invoke the hooks
     * 
     */
    public function __construct() {

        add_action( 'init', array( $this, 'init' ) );
    }

    /**
     * Init the hooks
     */
    public function init() {

        remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
        remove_action( 'embed_head', 'print_emoji_detection_script' );
        remove_action( 'wp_print_styles', 'print_emoji_styles' );
        remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
        remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );  
        remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
        add_action( 'admin_init', array( $this, 'disable_admin_emojis' ) );
        add_filter( 'emoji_svg_url', '__return_false' );
        add_filter( 'tiny_mce_plugins', array( $this, 'disable_emoji_for_tinymce' ) );
        add_filter( 'wp_resource_hints', array( $this, 'disable_emoji_remove_dns_prefetch' ), 10, 2 );
        add_filter( 'option_use_smilies', '__return_false' );
    }

    /** 
     * Disable emojis in wp-admin
     *
     */
    public function disable_admin_emojis() {
        remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
        remove_action( 'admin_print_styles', 'print_emoji_styles' );
    }

    /**
     * Remove the tinymce emoji plugin
     *
     */
    public function disable_emoji_for_tinymce( $plugins ) {

        if ( is_array( $plugins ) ) {
            return array_diff( $plugins, array( 'wpemoji' ) );
        }

        return array();
    }

    /**
     * Remove emoji CDN hostname from DNS prefetching hints.
     *
     */
    public function disable_emoji_remove_dns_prefetch( $urls, $relation_type ) {

        if ( 'dns-prefetch' == $relation_type ) {

            $emoji_svg_url_base = 'https://s.w.org/images/core/emoji/';
            foreach ( $urls as $key => $url ) {
                if ( is_string( $url ) && false !== strpos( $url, $emoji_svg_url_base ) ) {
                    unset( $urls[$key] );
                }
            }
        }

        return $urls;
    }
}
