<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Obfuscate Email Addresses
 * Description: Obfuscate email address to prevent spam bots from harvesting them, but make it readable like a regular email address for human visitors. Shortcode supports rtl="no" to disable right-to-left rendering and mailto="yes" for protected clickable links.
 * @since 1.5.0
 */
class WPMastertoolkit_Obfuscate_Email_Address {

    /**
     * Whether to print protected mailto script in footer.
     *
     * @var bool
     */
    private $print_mailto_script = false;

	/**
     * Invoke the hooks
     * 
     * @since    1.5.0
     */
    public function __construct() {
		add_shortcode( 'wpm_obfuscate', array( $this, 'obfuscate_email' ) );
		add_filter( 'widget_text', 'shortcode_unautop' );
		add_filter( 'widget_text', 'do_shortcode' );
        add_action( 'wp_footer', array( $this, 'render_mailto_script' ), 99 );
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
            'rtl'     => 'yes',
            'mailto'  => 'no',
        ), $atts );

        if ( ! is_email( $atts['email'] ) ) {
            return '';
        }

        $rtl_enabled = ! in_array( strtolower( (string) $atts['rtl'] ), array( '0', 'false', 'no', 'off' ), true );
        $mailto_enabled = in_array( strtolower( (string) $atts['mailto'] ), array( '1', 'true', 'yes', 'on' ), true );

        // Reverse email address characters if RTL is enabled and browser is not Firefox (Firefox has issues with unicode-bidi CSS property)
        if ( ! $rtl_enabled || false !== stripos( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ), 'firefox' ) ) {
            $email_reversed  = $atts['email'];
            $css_bidi_styles = '';
        } else {
            $email_reversed  = strrev( $atts['email'] );
            $css_bidi_styles = 'unicode-bidi:bidi-override;';
        }

        $email_rev_parts = explode( '@', $email_reversed );

        if ( 'newline' == $atts['display'] ) {
            $display_css = 'display:flex;';
            if ( $rtl_enabled ) {
                $display_css .= 'justify-content:flex-end;';
            }
        } else {
            $display_css = 'display:inline;';
        }

        $direction_css = $rtl_enabled ? 'direction:rtl;' : '';
        $email_html = '<span class="wpm_obfuscate" style="' . esc_attr( $display_css ) . esc_attr( $css_bidi_styles ) . esc_attr( $direction_css ) . '">'. esc_html( $email_rev_parts[0] ) .'<span style="display:none;">wpm_obfuscate</span>&#64;' . esc_html( $email_rev_parts[1] ) . '</span>';

        if ( $mailto_enabled ) {
            $this->print_mailto_script = true;

            return '<a class="wpm_obfuscate_mailto" href="mailto:not@spam.me" data-email="' . esc_attr( base64_encode( $atts['email'] ) ) . '">' . $email_html . '</a>';
        }

        return $email_html;
    }

    /**
     * Print JS for deferred mailto decoding on click.
     *
     * @since    2.17.0
     */
    public function render_mailto_script() {
        if ( ! $this->print_mailto_script ) {
            return;
        }
        ?>
        <script>
            document.addEventListener('click', function(event) {
                var link = event.target.closest('a.wpm_obfuscate_mailto[data-email]');
                if (!link) {
                    return;
                }

                event.preventDefault();

                try {
                    var email = atob(link.getAttribute('data-email'));
                    link.setAttribute('href', 'mailto:' + email);
                    link.removeAttribute('data-email');
                    link.click();
                } catch (error) {
                    return;
                }
            });
        </script>
        <?php
    }
}
