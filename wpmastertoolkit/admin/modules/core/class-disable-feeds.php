<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Disable Feeds
 * Description: Disable the default WordPress feeds
 * @since 1.0.0
 */
class WPMastertoolkit_Disable_Feeds {

    /**
     * Invoke the hooks
     * 
     * @since    1.0.0
     */
    public function __construct() {

        add_action('do_feed', array( $this, 'redirect_feed_to_403' ), 1);
        add_action('do_feed_rdf', array( $this, 'redirect_feed_to_403' ), 1);
        add_action('do_feed_rss', array( $this, 'redirect_feed_to_403' ), 1);
        add_action('do_feed_rss2', array( $this, 'redirect_feed_to_403' ), 1);
        add_action('do_feed_atom', array( $this, 'redirect_feed_to_403' ), 1);
        add_action('do_feed_rss2_comments', array( $this, 'redirect_feed_to_403' ), 1);
        add_action('do_feed_atom_comments', array( $this, 'redirect_feed_to_403' ), 1);

        remove_action( 'wp_head', 'feed_links_extra', 3 );
        remove_action( 'wp_head', 'feed_links', 2 );
    }

    /**
     * Redirect feed to 403
     * 
     * @since   1.0.0
     */
    public function redirect_feed_to_403() {

        status_header( 403 );  
        die('403 Forbidden');  
    }

}