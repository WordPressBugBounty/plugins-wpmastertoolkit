<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Content Duplication
 * Description: Enable one-click duplication of pages, posts and custom posts. The corresponding taxonomy terms and post meta will also be duplicated.
 * @since 1.4.0
 */
class WPMastertoolkit_Content_Duplication {

    private $excluded_posts = array( 'attachment', 'elementor_library', 'e-landing-page', 'product' );

    /**
     * Invoke the hooks
     * 
     */
    public function __construct() {
        add_filter( 'page_row_actions', array( $this, 'add_duplication_action_link' ), 10, 2 );
        add_filter( 'post_row_actions', array( $this, 'add_duplication_action_link' ), 10, 2 );
        add_action( 'admin_action_wpmastertoolkit_content_duplication', array( $this, 'wpmastertoolkit_content_duplication' ) );
    }

    /** 
     * Add row action link to perform duplication in page/post list tables
     *
     * @since 1.4.0
     */
    public function add_duplication_action_link( $actions, $post ) {

        if ( ! current_user_can( 'edit_posts' ) ) {
            return $actions;
        }

        if ( in_array( $post->post_type, $this->excluded_posts ) ) {
            return $actions;
        }

        $link = 'admin.php?action=wpmastertoolkit_content_duplication&amp;post=' . $post->ID . '&amp;nonce=' . wp_create_nonce( 'wpmastertoolkit_content_duplication_' . $post->ID ) . '';
        $actions['wpmastertoolkit-duplicate'] = sprintf( '<a href="%s">%s</a>', $link, __( 'Duplicate', 'wpmastertoolkit' ) );

        return $actions;
    }

    /**
     * Enable duplication of pages, posts and custom posts
     * 
     * @since 1.4.0
     */
    public function wpmastertoolkit_content_duplication() {

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'You do not have permission to perform this action.', 'wpmastertoolkit' ) );
        }

        $nonce            = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';
		//phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $original_post_id = isset( $_GET['post'] ) ? intval( sanitize_text_field( wp_unslash( $_GET['post'] ) ) ) : 0;

        if ( ! wp_verify_nonce( $nonce, 'wpmastertoolkit_content_duplication_' . $original_post_id ) ) {
            wp_die( esc_html__( 'Invalid nonce!', 'wpmastertoolkit' ) );
        }

        $original_post = get_post( $original_post_id );

        if ( ! $original_post ) {
            wp_die( esc_html__( 'Invalid post ID.', 'wpmastertoolkit' ) );
        }

        if ( in_array( $original_post->post_type, $this->excluded_posts ) ) {
            wp_die( esc_html__( 'You cannot duplicate this post type.', 'wpmastertoolkit' ) );
        }

        // Duplicate the post and insert it as a draft
        $args = array(
            'comment_status' => $original_post->comment_status,
            'ping_status'    => $original_post->ping_status,
            'post_author'    => get_current_user_id(),
            'post_content'   => str_replace( '\\', "\\\\", $original_post->post_content ),
            'post_excerpt'   => $original_post->post_excerpt,
            'post_parent'    => $original_post->post_parent,
            'post_password'  => $original_post->post_password,
            'post_status'    => 'draft',
            'post_title'     => $original_post->post_title . ' (' . __( 'Duplicate', 'wpmastertoolkit' ) . ')',
            'post_type'      => $original_post->post_type,
            'to_ping'        => $original_post->to_ping,
            'menu_order'     => $original_post->menu_order,
        );
        $new_post_id = wp_insert_post( $args );

        // Add taxonomies
        $original_taxonomies = get_object_taxonomies( $original_post->post_type );
        if ( ! empty( $original_taxonomies ) && is_array( $original_taxonomies ) ) {
            foreach ( $original_taxonomies as $taxonomy ) {
                $original_post_terms = wp_get_object_terms( $original_post_id, $taxonomy, array( 'fields' => 'slugs' ) );
                wp_set_object_terms( $new_post_id, $original_post_terms, $taxonomy, false );    
            }
        }

        // Add post meta
        $original_post_metas = get_post_meta( $original_post_id );
        if ( ! empty( $original_post_metas ) && is_array( $original_post_metas ) ) {
            foreach ( $original_post_metas as $meta_key => $meta_values ) {
                foreach ( $meta_values as $meta_value ) {
                    update_post_meta( $new_post_id, $meta_key, wp_slash( maybe_unserialize( $meta_value ) ) );
                }
            }
        }

        wp_redirect( admin_url( 'post.php?action=edit&post=' . $new_post_id ) );
        exit;
    }
}
