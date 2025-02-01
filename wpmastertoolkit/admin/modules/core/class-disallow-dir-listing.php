<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Disallow Dir Listing
 * Description: 
 * @since 1.3.0
 */
class WPMastertoolkit_Disallow_Dir_Listing {

    const MODULE_ID = 'Disallow Dir Listing';

    /**
     * Invoke the hooks
     * 
     */
    public function __construct() {

        add_filter( 'wpmastertoolkit_nginx_code_snippets', array( $this, 'nginx_code_snippets' ) );
    }

    /**
     * activate
     *
     * @return void
     */
    public static function activate(){
        global $is_apache;

        if ( $is_apache ) {

            require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/class-htaccess.php';
            WPMastertoolkit_Htaccess::add( self::get_raw_content_htaccess(), self::MODULE_ID );
        }
    }

    /**
     * deactivate
     *
     * @return void
     */
    public static function deactivate(){
        global $is_apache;

        if ( $is_apache ) {

            require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/class-htaccess.php';
            WPMastertoolkit_Htaccess::remove( self::MODULE_ID );
        }
    }

    /**
     * nginx_code_snippets
     *
     * @param  mixed $code_snippets
     * @return array
     */
    public function nginx_code_snippets( $code_snippets ) {
        global $is_nginx;

        if ( $is_nginx ) {
            $code_snippets[self::MODULE_ID] = self::get_raw_content_nginx();
        }

        return $code_snippets;
    }

    /**
     * Content of the .htaccess file
     */
    private static function get_raw_content_htaccess() {

        $content = "options -Indexes";

        return trim( $content );
    }

    /**
     * Content of the nginx.conf file
     */
    private function get_raw_content_nginx() {

        $content    = "location ~ \"^/wp-content/uploads/.*\.(\.9|73i87a|386|aaa|abc|aepl|aru|atm|aut|bat|bhx|bin|bkd|blf|bll|bmw|boo|bps|bqf|breaking_bad|btc|buk|bup|bxz|ccc|ce0|ceo|cfxxe|chm|cih|cla|cmd|com|coverton|cpl|crinf|crjoker|crypt|crypted|cryptolocker|cryptowall|ctbl|cxq|cyw|czvxce|darkness|dbd|delf|dev|dlb|dli|dll|dllx|dom|drv|dx|dxz|dyv|dyz|ecc|enciphered|encrypt|encrypted|enigma|exe1|exe_renamed|exx|ezt|ezz|fag|fjl|fnr|fuj|fun|good|gzquar|ha3|hlp|hlw|hsq|hts|iva|iws|jar|kcd|kernel_complete|kernel_pid|kernel_time|keybtc@inbox_com|kimcilware|kkk|kraken|lechiffre|let|lik|lkh|lnk|locked|locky|lok|lol\!|lpaq5|magic|mfu|micro|mjg|mjz|nls|oar|ocx|osa|ozd|p5tkjw|pcx|pdcr|pgm|php|php2|php3|pid|pif|plc|poar2w|pr|pzdc|qit|qrn|r5a|rdm|rhk|rna|rokku|rrk|rsc_tmp|s7p|scr|scr|shs|ska|smm|smtmp|sop|spam|ssy|surprise|sys|tko|tps|tsa|tti|ttt|txs|upa|uzy|vb|vba|vbe|vbs|vbx|vexe|vxd|vzr|wlpginstall|wmf|ws|wsc|wsf|wsh|wss|xdu|xir|xlm|xlv|xnt|xnxx|xtbl|xxx|xyz|zix|zvz|zzz)$\" {";
        $content   .= "\n\tbreak;";
        $content   .= "\n}";

        return trim( $content );
    }
}
