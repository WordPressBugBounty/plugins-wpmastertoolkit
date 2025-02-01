<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Last Login Column
 * Description: Add a last login column to the users list table
 * @since 1.0.0
 */
class WPMastertoolkit_Last_Login_Column {

    /**
     * Invoke Wp Hooks
	 *
     * @since    1.0.0
	 */
    public function __construct() {

        add_action( 'wp_login', array( $this, 'save_login_timestamp' ), 10, 2 );
        add_filter( 'manage_users_columns', array( $this, 'add_custom_columns' ) );
        add_filter( 'manage_users_custom_column', array( $this, 'add_custom_columns_content' ), 10, 3 );
    }

    /**
     * Save the user login timestamp
     * 
     * @since   1.0.0
     */
    public function save_login_timestamp( $user_login, $user ) {

        update_user_meta( $user->ID, 'WPMastertoolkit_last_login_timestamp', time() );
    }

    /**
     * Add custom columns to the users list table
     * 
     * @since   1.0.0
     */
    public function add_custom_columns( $columns ) {

        $columns['WPMastertoolkit_last_login'] = esc_html__( 'Last Login', 'wpmastertoolkit' );

        return $columns;
    }

    /**
     * Render the content of the custom columns
     * 
     * @since   1.0.0
     */
    public function add_custom_columns_content( $output, $column_name, $user_id ) {

        if ( $column_name === 'WPMastertoolkit_last_login' ) {
            
            $last_login_timestamp = get_user_meta( $user_id, 'WPMastertoolkit_last_login_timestamp', true );

            if ( !empty( $last_login_timestamp ) ) {

                $output = wp_date( 'M j, Y H:i', $last_login_timestamp );
                
            } else {
                $output = esc_html__( 'No data yet', 'wpmastertoolkit' );
            }
        }

        return $output;
    }

}