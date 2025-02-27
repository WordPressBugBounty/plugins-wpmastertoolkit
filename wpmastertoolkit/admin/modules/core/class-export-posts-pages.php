<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Export Posts & Pages
 * Description: 
 * @since 1.3.0
 */
class WPMastertoolkit_Export_Posts_Pages {

    const ACTION = 'wpmastertoolkit_export_posts_pages';
    const NONCE  = 'wpmastertoolkit_export_posts_pages_nonce';

    /**
     * Invoke the hooks
     * 
     */
    public function __construct() {

        add_filter( 'post_row_actions', array( $this, 'add_export_action' ), 10, 2 );
        add_filter( 'page_row_actions', array( $this, 'add_export_action' ), 10, 2 );
        add_action( 'wp_ajax_' . self::ACTION, array( $this, 'download_post' ) );
        add_filter( 'bulk_actions-edit-post', array( $this, 'add_bulk_action'), 10, 1 );
        add_filter( 'bulk_actions-edit-page', array( $this, 'add_bulk_action'), 10, 1 );
        add_filter( 'handle_bulk_actions-edit-post', array( $this, 'download_posts'), 10, 3 );
        add_filter( 'handle_bulk_actions-edit-page', array( $this, 'download_pages'), 10, 3 );
    }

    /**
     * Add export action
     */
    public function add_export_action( $actions, $post ) {

        $url = add_query_arg(
            array(
                'action'    => self::ACTION,
                'post'      => $post->ID,
                'nonce'     => wp_create_nonce( self::NONCE ),
                'post_type' => $post->post_type,
            ),
            admin_url( 'admin-ajax.php' )
        );

        $actions[ self::ACTION ] = sprintf( '<a href="%s" target="_blank">%s</a>', esc_url( $url ), esc_html__( 'Download csv', 'wpmastertoolkit' ) );

        return $actions;
    }

    /**
     * Download post
     */
    public function download_post() {

        $nonce = isset( $_GET[ 'nonce' ] ) ? sanitize_text_field( wp_unslash( $_GET[ 'nonce' ] ) ) : '';

        if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
            wp_die();
        }

        $post_id   = isset( $_GET[ 'post' ] ) ? sanitize_text_field( wp_unslash( $_GET[ 'post' ] ) ) : '';
        $post_type = isset( $_GET[ 'post_type' ] ) ? sanitize_text_field( wp_unslash( $_GET[ 'post_type' ] ) ) : '';

        if ( empty( $post_id ) || empty( $post_type ) ) {
            wp_die();
        }

        $posts = $this->get_posts_array( array( $post_id ) );

        if ( ! $posts || empty( $posts ) ) {
            return;
        }

        $this->generate_csv( $posts, $post_type );
        wp_die();
    }

    /**
     * Add bulk action
     */
    public function add_bulk_action( $actions ) {

        if ( ! isset( $actions[ self::ACTION ] ) ) {

            $actions[ self::ACTION ] = __( 'Download csv', 'wpmastertoolkit' );
        }

        return $actions;
    }

    /**
     * Download posts
     */
    public function download_posts( $sendback, $doaction, $ids, $filename = 'posts' ) {

        if ( self::ACTION !== $doaction ) {
            return $sendback;
        }

        $posts = $this->get_posts_array( $ids );

        if ( ! $posts || empty( $posts ) ) {
            return $sendback;
        }

        $this->generate_csv( $posts, $filename );

        return $sendback;
    }

    /**
     * Download pages
     */
    public function download_pages( $sendback, $doaction, $ids ) {

        $this->download_posts( $sendback, $doaction, $ids, 'pages' );
    }

    /**
     * Get posts as an array
     */
    private function get_posts_array( $ids ) {

        if ( empty( $ids ) ) {
            return null;
        }

        $params = array( 
            'include'     => $ids,
            'numberposts' => -1,
            'post_type'   => 'any',
            'post_status' => 'any',
        );
        $posts = get_posts( $params );

        if ( empty( $posts ) ) {
            return null;
        }

        $posts_array = array();

        foreach ( $posts as $post ) {
            $post_array = $post->to_array();

            $categories       = get_the_category( $post->ID );
            $categories_names = array();
            foreach ( $categories as $category ) {
                $categories_names[] = $category->name;
            }
            $post_array['post_category'] = implode( ',', $categories_names );

            $tags      = wp_get_post_tags( $post->ID );
            $tag_names = array();
            foreach ( $tags as $tag ) {
                $tag_names[] = $tag->name;
            }
            $post_array['tags_input'] = implode( ',', $tag_names );

            $post_array['post_mime_type'] = $post->post_mime_type;

            $ancestors = get_post_ancestors( $post->ID );
            $ancestors = array_reverse( $ancestors );
            $post_array['ancestors'] = implode( ',', $ancestors );

            $posts_array[] = $post_array;
        }

        return $posts_array;
    }

    /**
     * Generate CSV
     */
    private function generate_csv( $posts, $filename ) {

        $filename  = $filename . '.csv';
        $delimiter = ',';
        $enclosure = '"';
        $handle    = fopen( 'php://output', 'w' );

        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

        fputcsv( $handle, array_keys( $posts[0] ), $delimiter, $enclosure );

        foreach ( $posts as $post ) {
            fputcsv( $handle, $post, $delimiter, $enclosure );
        }

		//phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        fclose( $handle );
        exit;
    }
}
