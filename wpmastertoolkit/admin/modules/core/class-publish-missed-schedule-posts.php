<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Publish Missed Schedule Posts
 * Description: 
 * @since 1.3.0
 */
class WPMastertoolkit_Publish_Missed_Schedule_Posts {

    const TRANSIENT_ID = 'wpmastertoolkit_missed_schedule_posts';

    /**
     * Invoke the hooks
     * 
     */
    public function __construct() {

        add_action( 'wp_head', array( $this, 'publish_missed_schedule_posts' ) );
        add_action( 'admin_head', array( $this, 'publish_missed_schedule_posts' ) );
    }

    /**
     * Publish missed schedule posts
     * 
     * @since   1.0.0
     */
    public function publish_missed_schedule_posts() {

        if ( is_front_page() || is_home() || is_page() || is_single() || is_singular() || is_archive() || is_admin() || is_blog_admin() || is_robots() || is_ssl() ) {

            $missed_schedule_posts = get_transient( self::TRANSIENT_ID );

            if ( false === $missed_schedule_posts ) {
                
                global $wpdb;

                $current_gmt_datetime = gmdate( 'Y-m-d H:i:00' );
                $args = array(
                    'public'   => true,
                    '_builtin' => false,
                );
                $custom_post_types = get_post_types( $args, 'names' );

                if ( count( $custom_post_types ) > 0 ) {
                    $custom_post_types = "'" . implode( "','", $custom_post_types ) . "'";
                    $post_types        = "'page','post'," . $custom_post_types;
                } else {
                    $post_types = "'page','post'";
                }

                /**
                 * Filter the post types to check for missed schedule posts.
                 *
                 * @since 1.3.0
                 *
                 * @param array $post_types
                 */
                $post_types = apply_filters( 'wpmastertoolkit/publish_missed_schedule_posts/post_types', $post_types );

                $sql = "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ({$post_types}) AND post_status='future' AND post_date_gmt<'{$current_gmt_datetime}'";
				//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $missed_schedule_posts = $wpdb->get_results( $sql, ARRAY_A );
                
                /**
                 * Filter the transient expiration time.
                 *
                 * @since 1.3.0
                 *
                 * @param int   $transient_expiration
                 */
                $transient_expiration = apply_filters( 'wpmastertoolkit/publish_missed_schedule_posts/transient_expiration', 30 * MINUTE_IN_SECONDS );
                set_transient( self::TRANSIENT_ID, $missed_schedule_posts, $transient_expiration );
            }

            if ( empty( $missed_schedule_posts ) || ! is_array( $missed_schedule_posts ) ) {
                return;
            }

            foreach ( $missed_schedule_posts as $post ) {
                wp_publish_post( $post['ID'] );
            }
        }
    }

}