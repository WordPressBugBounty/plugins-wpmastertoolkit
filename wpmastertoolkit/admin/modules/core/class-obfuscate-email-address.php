<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Obfuscate Email Addresses
 * Description: Obfuscate email address to prevent spam bots from harvesting them, but make it readable like a regular email address for human visitors.
 * @since 1.5.0
 */
class WPMastertoolkit_Obfuscate_Email_Address {

	/**
     * Invoke the hooks
     * 
     * @since    1.5.0
     */
    public function __construct() {
		add_shortcode( 'wpm_obfuscate', array( $this, 'obfuscate_email' ) );
		add_filter( 'widget_text', 'shortcode_unautop' );
		add_filter( 'widget_text', 'do_shortcode' );
	}

	/**
	 * Obfuscate email address
	 * 
	 * @since    1.5.0
	 */
	public function obfuscate_email( $atts ) {
        $atts = shortcode_atts( array(
            'email'   => '',
            'display' => '',
        ), $atts );

        if ( ! is_email( $atts['email'] ) ) {
            return '';
        }

        // Reverse email address characters if not in Firefox, which has bug related to unicode-bidi CSS property
        if ( false !== stripos( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ), 'firefox' ) ) {
            // Do nothing. Do not reverse characters.
            $email_reversed  = $atts['email'];
            $email_rev_parts = explode( '@', $email_reversed );
            $email_rev_parts = array( $email_rev_parts[0], $email_rev_parts[1] );
            $css_bidi_styles = '';
        } else {
            $email_reversed  = strrev( $atts['email'] );
            $email_rev_parts = explode( '@', $email_reversed );
            $css_bidi_styles = 'unicode-bidi:bidi-override;';
        }

        if ( 'newline' == $atts['display'] ) {
            $display_css = 'display:flex;justify-content:flex-end;';
        } else {
            $display_css = 'display:inline;';
        }

        return '<span class="wpm_obfuscate" style="' . esc_attr( $display_css ) . esc_attr( $css_bidi_styles ) . 'direction:rtl;">'. esc_html( $email_rev_parts[0] ) .'<span style="display:none;">wpm_obfuscate</span>&#64;' . esc_html( $email_rev_parts[1] ) . '</span>';
    }
}
