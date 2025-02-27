<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Export Users
 * Description: 
 * @since 1.3.0
 */
class WPMastertoolkit_Export_Users {

    const ACTION = 'wpmastertoolkit_export_users';
    const NONCE  = 'wpmastertoolkit_export_users_nonce';

    /**
     * Invoke the hooks
     * 
     */
    public function __construct() {

        add_filter( 'user_row_actions', array( $this, 'add_export_action' ), 10, 2 );
        add_filter( 'bulk_actions-users', array( $this, 'add_bulk_action' ), 10, 1 );
        add_filter( 'handle_bulk_actions-users', array( $this, 'download_users' ), 10, 3 );
        add_action( 'wp_ajax_' . self::ACTION, array( $this, 'download_user' ) );
    }

    /**
     * Add export action
     */
    public function add_export_action( $actions, $user_object ) {

        $url = add_query_arg(
            array(
                'action' => self::ACTION,
                'user'   => $user_object->ID,
                'nonce'  => wp_create_nonce( self::NONCE ),
            ),
            admin_url( 'admin-ajax.php' )
        );

        $actions[ self::ACTION ] = sprintf( '<a href="%s" target="_blank">%s</a>', esc_url( $url ), esc_html__( 'Download csv', 'wpmastertoolkit' ) );

        return $actions;
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
     * Do bulk action
     */
    public function download_users( $sendback, $doaction, $ids ) {

        if ( self::ACTION !== $doaction ) {
            return $sendback;
        }

        $users = $this->get_users_array( $ids );

        if ( ! $users || empty( $users ) ) {
            return $sendback;
        }

        $this->generate_csv( $users );

        return $sendback;
    }

    /**
     * Download user
     */
    public function download_user() {

        $nonce = isset( $_GET[ 'nonce' ] ) ? sanitize_text_field( wp_unslash( $_GET[ 'nonce' ] ) ) : '';

        if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
            wp_die();
        }

        $user_id = isset( $_GET[ 'user' ] ) ? sanitize_text_field( wp_unslash( $_GET[ 'user' ] ) ) : '';

        if ( empty( $user_id ) ) {
            wp_die();
        }

        $users = $this->get_users_array( array( $user_id ) );

        if ( ! $users || empty( $users ) ) {
            wp_die();
        }

        $this->generate_csv( $users, 'user-' . $user_id );
        wp_die();
    }

    /**
     * Get users as an array
     */
    private function get_users_array( $ids ) {

        if ( empty( $ids ) ) {
            return null;
        }

        $args = array(
            'include' => $ids,
            'number'  => -1,
        );
        $users = get_users( $args );

        if ( empty( $users ) ) {
            return null;
        }

        $users_arr = array();

        foreach ( $users as $user ) {
            $users_arr[] = $user->to_array();
        }

        return $users_arr;
    }

    /**
     * Generate CSV
     */
    private function generate_csv( $users, $filename = 'users' ) {

        $filename  = $filename . '.csv';
        $delimiter = ',';
        $enclosure = '"';
        $handle    = fopen( 'php://output', 'w' );

        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

        fputcsv( $handle, array_keys( $users[0] ), $delimiter, $enclosure );

        foreach ( $users as $user ) {
            fputcsv( $handle, $user, $delimiter, $enclosure );
        }

		//phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        fclose( $handle );
        exit;
    }
}
