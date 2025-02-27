<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Handle the wp-config file
 *
 * @link       https://webdeclic.com
 * @since      1.0.0
 *
 * @package           WPMastertoolkit
 * @subpackage WP-Mastertoolkit/admin
 */

class WPMastertoolkit_WP_Config {    
    /**
     * __construct
     *
     * @return void
     */
    public function __construct() {
    }
    
    /**
     * get_wp_config_path
     *
     * @return string
     */
    public static function get_wp_config_path() {
        $wp_config_path = ABSPATH . 'wp-config.php';
        return $wp_config_path;
    }
    
    /**
     * get_wp_config_content
     *
     * @return string
     */
    public static function get_wp_config_content() {
        $wp_config_path = self::get_wp_config_path();
        $wp_config_content = file_get_contents( $wp_config_path );
        return $wp_config_content;
    }
        
    /**
     * is_constant_defined_in_wp_config
     *
     * @param  mixed $constant_name
     * @return void
     */
    public static function is_constant_defined_in_wp_config( $constant_name ) {
        $wp_config_content = self::get_wp_config_content();

        // Delete comments from the wp-config file for better parsing
        $wp_config_content = preg_replace('/\/\*.*?\*\//s', '', $wp_config_content);

        // Check if the constant is defined in the wp-config file
        $pattern = '/define\s*\(\s*[\'"]' . preg_quote($constant_name, '/') . '[\'"]\s*,\s*[\s\S]*?\);/';
        preg_match_all($pattern, $wp_config_content, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as $match) {
            $const_pos = $match[1];
            // Check if the constant is commented out
            $line_start = strrpos(substr($wp_config_content, 0, $const_pos), "\n") + 1;
            $line = substr($wp_config_content, $line_start, $const_pos - $line_start);
            if (!preg_match('/^\s*\/\/|^\s*#/', $line)) {
                return true;
            }
        }
        return false;
    }

    
    /**
     * replace_or_add_constant
     *
     * @param  mixed $constant_name
     * @param  mixed $new_value
     * @param  mixed $wp_config_content
     * @return void
     */
    public static function replace_or_add_constant( $constant_name, $new_value, $type = null ) {
        $wp_config_content = self::get_wp_config_content();
        
        if( self::is_constant_defined_in_wp_config( $constant_name ) ) {
            // we replace the constant value
            return self::replace_constant_value( $constant_name, $new_value, $type );
        } else {
            // we add the constant at the start of the file just after the opening php tag
            return self::add_constant( $constant_name, $new_value );
        }
    }
    
    /**
     * add_constant
     *
     * @param  mixed $constant_name
     * @param  mixed $constant_value
     * @param  mixed $position
     * @return void
     */
    public static function add_constant( $constant_name, $constant_value ) {
        $wp_config_path    = self::get_wp_config_path();
        $wp_config_content = self::get_wp_config_content();
        
		//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
        $wp_config_content = preg_replace( '/<\?php/', "<?php\ndefine( '" . $constant_name . "', " . var_export($constant_value, true) . " );", $wp_config_content, 1 );
        return file_put_contents( $wp_config_path, $wp_config_content );
    }
    
    /**
     * replace_constant_value
     *
     * @param  mixed $constant_name
     * @param  mixed $constant_value
     * @return void
     */
    public static function replace_constant_value( $constant_name, $constant_value, $type = null ) {
        $wp_config_path    = self::get_wp_config_path();
        $wp_config_content = self::get_wp_config_content();   

        if( $type == "string") {
            $pattern_single_quote = '/define\s*\(\s*[\'"]' . preg_quote( $constant_name, '/' ) . '[\']/';
            if( preg_match( $pattern_single_quote, $wp_config_content ) ) {
                $pattern = '/define\s*\(\s*[\'"]' . preg_quote( $constant_name, '/' ) . '[\'"]\s*,\s*[\'][\s\S]*?[\']\s*\);/';
            } else {
                $pattern = '/define\s*\(\s*[\'"]' . preg_quote( $constant_name, '/' ) . '[\'"]\s*,\s*["][\s\S]*?["]\s*\);/';
            }
        } else {
            $pattern = '/define\s*\(\s*[\'"]' . preg_quote( $constant_name, '/' ) . '[\'"]\s*,\s*[\s\S]*?\);/';
        }

		//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
        $replacement = "define( '" . $constant_name . "', " . var_export($constant_value, true) . " );";

        $wp_config_content = preg_replace( $pattern, $replacement, $wp_config_content );

        return file_put_contents( $wp_config_path, $wp_config_content );
    }

    /**
     * remove_constant
     *
     * @param  mixed $constant_name
     * @return void
     */
    public static function remove_constant( $constant_name ) {
        $wp_config_path    = self::get_wp_config_path();
        $wp_config_content = self::get_wp_config_content();
        
        $pattern = '/define\s*\(\s*[\'"]' . preg_quote( $constant_name, '/' ) . '[\'"]\s*,\s*[\s\S]*?\);/';

        $wp_config_content = preg_replace( $pattern, '', $wp_config_content );

        return file_put_contents( $wp_config_path, $wp_config_content );
    }

	/**
	 * Change value of a PHP variable
	 * 
	 * @param string $variable_name
	 */
	public static function change_php_variable( $variable_name, $new_value ) {
		$wp_config_path    = self::get_wp_config_path();
		$wp_config_content = self::get_wp_config_content();

		$pattern           = '/\$' . $variable_name . '\s=(.*)\;/';
		//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
		$new_variable      = "\n\$" . $variable_name . " = " . var_export( $new_value, true ) . ";\n";
        $wp_config_content = preg_replace( $pattern, $new_variable, $wp_config_content );
		
		return file_put_contents( $wp_config_path, $wp_config_content );
	}
}
