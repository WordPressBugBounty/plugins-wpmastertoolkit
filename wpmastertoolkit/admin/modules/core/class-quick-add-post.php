<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Quick Add Post
 * Description: 
 * @since 1.3.0
 */
class WPMastertoolkit_Quick_Add_Post {

    /**
     * Invoke the hooks
     * 
     */
    public function __construct() {

        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts_styles' ) );
    }

    /**
     * Enqueue the scripts and styles
     */
    public function enqueue_scripts_styles( $hook_suffix ) {
        global $post;

        $screen = get_current_screen();

        if ( $screen->base == 'post' && in_array( $screen->id, array('post', 'page') ) ) {

            $assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/quick-add-post.asset.php' );
            wp_enqueue_script( 'wpmastertoolkit-quick-add-post', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/quick-add-post.js', $assets['dependencies'], $assets['version'], true );
            wp_localize_script( 'wpmastertoolkit-quick-add-post', 'WPMastertoolkitQuickAddPost', array(
                'post_type' => $post ? $post->post_type : null,
            ));
        }
    }
}
