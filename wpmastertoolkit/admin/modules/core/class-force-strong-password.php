<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Force Strong Password
 * Description: Force users to use strong passwords
 * @since 1.0.0
 */

/**
 * ZxcvbnPhp
 * ZxcvbnPhp is a password strength estimator using pattern matching and minimum entropy calculation.
 */
use ZxcvbnPhp\Zxcvbn;

class WPMastertoolkit_Force_Strong_Password {
    /**
     * __construct Invoke Wp Hooks
     *
     * @return void
     */
    public function __construct() {
        // Profile update
        add_action( 'user_profile_update_errors', array( $this, 'validate_profile_update' ), 10, 3 );

        // Registration form and admin user creation or update
        add_action( 'admin_print_styles',         array( $this, 'hide_confirm_weak_password' ), 999 );
        add_action( 'registration_errors',        array( $this, 'validate_registration' ), 10, 3 );
        add_action( 'admin_enqueue_scripts',      array( $this,'custom_password_strength_meter_localization'), 9999 );
        
        // Reset password
        add_action( 'validate_password_reset',    array( $this, 'validate_password_reset' ), 10, 2 );
        add_action( 'resetpass_form',             array( $this, 'hide_confirm_weak_password' ), 10 );
        add_action( 'resetpass_form',             array( $this, 'custom_password_strength_meter_localization' ), 9999 );
    }
    
    /**
     * hide_confirm_weak_password
     *
     * @return void
     */
    public function hide_confirm_weak_password() {
        if ( wp_script_is( 'user-profile' ) ) {
            echo '<style>.pw-weak { display: none !important; }</style>';
        }
    }
    
    /**
     * custom_password_strength_meter_localization
     *
     * @return void
     */
    public function custom_password_strength_meter_localization(){
        global $wp_scripts;
    
        if ( ! is_a( $wp_scripts, 'WP_Scripts' ) || ! wp_script_is( 'password-strength-meter', 'registered' ) ) {
            return;
        }
    
        wp_localize_script( 'password-strength-meter', 'pwsL10n', array(
            'unknown'  => esc_attr_x( 'Password strength unknown', 'password strength', 'wpmastertoolkit' ),
            'short'    => esc_attr_x( "Not authorized", 'password strength', 'wpmastertoolkit' ),
            'bad'      => esc_attr_x( 'Not authorized',  'password strength', 'wpmastertoolkit' ),
            'good'     => esc_attr_x( 'Medium', 'password strength', 'wpmastertoolkit' ),
            'strong'   => esc_attr_x( 'Strong', 'password strength', 'wpmastertoolkit' ),
            'mismatch' => esc_attr_x( 'Mismatch', 'password mismatch', 'wpmastertoolkit' ),
        ) );
    }
    
    /**
     * validate_profile_update
     *
     * @param  mixed $errors
     * @param  mixed $update
     * @param  mixed $user
     * @return void
     */
    public function validate_profile_update( WP_Error &$errors, $update, &$user ) {
        return $this->validate_complex_password( $errors );
    }
    
    /**
     * validate_registration
     *
     * @param  mixed $errors
     * @param  mixed $sanitized_user_login
     * @param  mixed $user_email
     * @return void
     */
    public function validate_registration( WP_Error &$errors, $sanitized_user_login, $user_email ) {
        return $this->validate_complex_password( $errors );
    }
    
    /**
     * validate_password_reset
     *
     * @param  mixed $errors
     * @param  mixed $userData
     * @return void
     */
    public function validate_password_reset( $errors, $userData ) {
        return $this->validate_complex_password( $errors );
    }
    
    /**
     * validate_complex_password
     *
     * @param  mixed $errors
     * @return void
     */
    private function validate_complex_password( $errors ) {
        $password = ( isset( $_POST[ 'pass1' ] ) && trim( $_POST[ 'pass1' ] ) ) ? sanitize_text_field( $_POST[ 'pass1' ] ) : null;

        if ( empty( $password ) || $errors->get_error_data( 'pass' ) ) return $errors;

        if ( ! $this->is_strong_password( $password ) ) {
            $errors->add( 
                'pass', 
                esc_html__( '<strong>ERROR</strong>: Your password is not strong enough. Please use a stronger password.', 'wpmastertoolkit' ),
                array( 'form-field' => 'pass1' ) 
            );
        }

        return $errors;
    }
    
    /**
     * is_strong_password
     *
     * @param  mixed $password
     * @return void
     */
    private function is_strong_password( $password ) {
        $zxcvbn = new Zxcvbn();
        $strength = $zxcvbn->passwordStrength( $password );
        return $strength['score'] >= 3;
    }
}