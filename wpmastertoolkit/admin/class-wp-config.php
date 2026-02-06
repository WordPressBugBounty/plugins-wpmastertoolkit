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
     * @return string|false
     */
    public static function get_wp_config_content() {
        $wp_config_path = self::get_wp_config_path();
        
        if ( ! file_exists( $wp_config_path ) ) {
            error_log( 'WPMastertoolkit: wp-config.php does not exist: ' . $wp_config_path );
            return false;
        }
        
        if ( ! is_readable( $wp_config_path ) ) {
            error_log( 'WPMastertoolkit: wp-config.php is not readable: ' . $wp_config_path );
            return false;
        }
        
        $wp_config_content = file_get_contents( $wp_config_path );
        
        if ( $wp_config_content === false ) {
            error_log( 'WPMastertoolkit: Failed to read wp-config.php: ' . $wp_config_path );
            return false;
        }
        
        $wp_config_content = preg_replace("/(\r\n|\n|\r){3,}/", "\n\n", $wp_config_content);
        return $wp_config_content;
    }
    
    /**
     * get_backup_folder_path
     * Get the path to wp-config backup folder
     *
     * @return string
     */
    public static function get_backup_folder_path() {
        // Add wp-config-backup folder to wpmastertoolkit folders
        add_filter( 'wpmastertoolkit/folders', function( $folders ) {
            $folders['wpmastertoolkit']['wp-config-backup'] = array();
            return $folders;
        }, 10, 1 );
        
        // Ensure folders exist
        wpmastertoolkit_folders();

        $path = WP_CONTENT_DIR . '/wpmastertoolkit/wp-config-backup';

        if( ! file_exists( $path ) ||  ! is_writable( $path ) ) {
            error_log( 'WPMastertoolkit: wp-config-backup folder does not exist or is not writable: ' . $path );
            return false;
        }
        
        return $path;
    }
    
    /**
     * backup_wp_config
     * Create a backup of wp-config.php before modification
     *
     * @return bool True on success, false on failure
     */
    public static function backup_wp_config() {
        $wp_config_path = self::get_wp_config_path();
        
        if ( ! file_exists( $wp_config_path ) || ! is_readable( $wp_config_path ) ) {
            error_log( 'WPMastertoolkit: Cannot backup wp-config.php - file does not exist or is not readable: ' . $wp_config_path );
            return false;
        }
        
        $backup_folder = self::get_backup_folder_path();
        
        if ( $backup_folder === false ) {
            error_log( 'WPMastertoolkit: Cannot backup wp-config.php - backup folder is not available' );
            return false;
        }
        
        $backup_path = $backup_folder . '/wp-config.php.bak';
        return copy( $wp_config_path, $backup_path );
    }
    
    /**
     * restore_wp_config_backup
     * Restore wp-config.php from backup
     *
     * @return bool True on success, false on failure
     */
    public static function restore_wp_config_backup() {
        $wp_config_path = self::get_wp_config_path();
        
        if ( ! is_writable( $wp_config_path ) ) {
			//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( 'WPMastertoolkit: Cannot restore wp-config.php - file is not writable: ' . $wp_config_path );
            return false;
        }
        
        $backup_folder = self::get_backup_folder_path();
        
        if ( $backup_folder === false ) {
			//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( 'WPMastertoolkit: Cannot restore wp-config.php - backup folder is not available' );
            return false;
        }
        
        $backup_path = $backup_folder . '/wp-config.php.bak';
        
        if ( ! file_exists( $backup_path ) ) {
			//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( 'WPMastertoolkit: Cannot restore wp-config.php - backup file not found' );
            return false;
        }
        
        if ( ! is_readable( $backup_path ) ) {
			//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( 'WPMastertoolkit: Cannot restore wp-config.php - backup file is not readable: ' . $backup_path );
            return false;
        }
        
        $restored = copy( $backup_path, $wp_config_path );
        if ( $restored ) {
			//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( 'WPMastertoolkit: wp-config.php successfully restored from backup' );
            // Delete backup after successful restoration
            self::delete_backup();
        }
        return $restored;
    }
    
    /**
     * delete_backup
     * Delete the wp-config.php backup file
     *
     * @return bool True on success, false on failure
     */
    public static function delete_backup() {
        $backup_folder = self::get_backup_folder_path();
        
        if ( $backup_folder === false ) {
            return true; // Nothing to delete if backup folder doesn't exist
        }
        
        $backup_path = $backup_folder . '/wp-config.php.bak';
        
        if ( file_exists( $backup_path ) ) {
			//phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
            return unlink( $backup_path );
        }
        
        return true;
    }
    
    /**
     * validate_wp_config
     * Validate that wp-config.php contains critical constants
     *
     * @return bool True if valid, false otherwise
     */
    public static function validate_wp_config() {
        $wp_config_path = self::get_wp_config_path();
        
        if ( ! file_exists( $wp_config_path ) ) {
			//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( 'WPMastertoolkit: wp-config.php validation failed - file does not exist: ' . $wp_config_path );
            return false;
        }
        
        if ( ! is_readable( $wp_config_path ) ) {
			//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( 'WPMastertoolkit: wp-config.php validation failed - file is not readable: ' . $wp_config_path );
            return false;
        }
        
        $wp_config_content = file_get_contents( $wp_config_path );
        
        // Check file is not empty
        if ( empty( $wp_config_content ) ) {
			//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( 'WPMastertoolkit: wp-config.php validation failed - file is empty' );
            return false;
        }
        
        // Check critical constants exist
        $critical_constants = array( 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_HOST' );
        foreach ( $critical_constants as $constant ) {
            if ( strpos( $wp_config_content, $constant ) === false ) {
				//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log( 'WPMastertoolkit: wp-config.php validation failed - missing critical constant: ' . $constant );
                return false;
            }
        }
        
        return true;
    }
        
    /**
     * is_constant_defined_in_wp_config
     *
     * @param  mixed $constant_name
     * @return void
     */
    public static function is_constant_defined_in_wp_config( $constant_name ) {
        $wp_config_content = self::get_wp_config_content();
        
        if ( $wp_config_content === false ) {
            return false;
        }

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
    public static function replace_or_add_constant( $constant_name, $new_value, $type = null, $var_export_skip = false ) {
        $wp_config_content = self::get_wp_config_content();

        if ( $wp_config_content === false ) return false;
        
        if( self::is_constant_defined_in_wp_config( $constant_name ) ) {
            // we replace the constant value
            return self::replace_constant_value( $constant_name, $new_value, $type, $var_export_skip );
        } else {
            // we add the constant at the start of the file just after the opening php tag
            return self::add_constant( $constant_name, $new_value, $var_export_skip );
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
    public static function add_constant( $constant_name, $constant_value, $var_export_skip = false ) {
        $wp_config_path    = self::get_wp_config_path();
        
        if ( ! file_exists( $wp_config_path ) || ! is_writable( $wp_config_path ) ) {
			//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( 'WPMastertoolkit: Cannot add constant - wp-config.php does not exist or is not writable: ' . $wp_config_path );
            return false;
        }
        
        $wp_config_content = self::get_wp_config_content();
        
        if ( $wp_config_content === false ) {
			//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( 'WPMastertoolkit: Cannot add constant - failed to read wp-config.php' );
            return false;
        }
        
		//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
        $wp_config_content = preg_replace( '/<\?php/', "<?php\ndefine( '" . $constant_name . "', " . var_export($constant_value, true) . " );", $wp_config_content, 1 );
        
        // Validate content before writing
        if ( empty( $wp_config_content ) || $wp_config_content === null ) {
			//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( 'WPMastertoolkit: wp-config.php write cancelled - empty content after add_constant' );
            return false;
        }
        
        // Backup before writing
        self::backup_wp_config();
        
        $written = file_put_contents( $wp_config_path, $wp_config_content );
        
        // Validate after writing
        if ( !self::validate_wp_config() ) {
			//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( 'WPMastertoolkit: wp-config.php validation failed after add_constant - restoring backup' );
            self::restore_wp_config_backup();
            return false;
        }
        
        // Delete backup after successful validation
        self::delete_backup();
        
        return $written;
    }
    
    /**
     * replace_constant_value
     *
     * @param  mixed $constant_name
     * @param  mixed $constant_value
     * @return void
     */
    public static function replace_constant_value( $constant_name, $constant_value, $type = null, $var_export_skip = false ) {
        $wp_config_path    = self::get_wp_config_path();
        
        if ( ! file_exists( $wp_config_path ) || ! is_writable( $wp_config_path ) ) {
			//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( 'WPMastertoolkit: Cannot replace constant - wp-config.php does not exist or is not writable: ' . $wp_config_path );
            return false;
        }
        
        $wp_config_content = self::get_wp_config_content();
        
        if ( $wp_config_content === false ) {
			//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( 'WPMastertoolkit: Cannot replace constant - failed to read wp-config.php' );
            return false;
        }   

        if( $type == "string" ) {
            $pattern_single_quote = '/define\s*\(\s*[\'"]' . preg_quote( $constant_name, '/' ) . '[\']/';
            if( preg_match( $pattern_single_quote, $wp_config_content ) ) {
                $pattern = '/define\s*\(\s*[\'"]' . preg_quote( $constant_name, '/' ) . '[\'"]\s*,\s*[\'][\s\S]*?[\']\s*\);/';
            } else {
                $pattern = '/define\s*\(\s*[\'"]' . preg_quote( $constant_name, '/' ) . '[\'"]\s*,\s*["][\s\S]*?["]\s*\);/';
            }
        } else {
            $pattern = '/define\s*\(\s*[\'"]' . preg_quote( $constant_name, '/' ) . '[\'"]\s*,\s*[\s\S]*?\);/';
        }

        if( $var_export_skip ) {
            $replacement = "define( '" . $constant_name . "', " . $constant_value . " );";
        } else {
		    //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
            $replacement = "define( '" . $constant_name . "', " . var_export($constant_value, true) . " );";
        }

        $wp_config_content = preg_replace( $pattern, $replacement, $wp_config_content );
        
        // Validate content before writing
        if ( empty( $wp_config_content ) || $wp_config_content === null ) {
			//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( 'WPMastertoolkit: wp-config.php write cancelled - empty content after replace_constant_value for ' . $constant_name );
            return false;
        }
        
        // Backup before writing
        self::backup_wp_config();

        $written = file_put_contents( $wp_config_path, $wp_config_content );
        
        // Validate after writing
        if ( !self::validate_wp_config() ) {
			//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( 'WPMastertoolkit: wp-config.php validation failed after replace_constant_value for ' . $constant_name . ' - restoring backup' );
            self::restore_wp_config_backup();
            return false;
        }
        
        // Delete backup after successful validation
        self::delete_backup();
        
        return $written;
    }

    /**
     * remove_constant
     *
     * @param  mixed $constant_name
     * @return void
     */
    public static function remove_constant( $constant_name ) {
        $wp_config_path    = self::get_wp_config_path();
        
        if ( ! file_exists( $wp_config_path ) || ! is_writable( $wp_config_path ) ) {
			//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( 'WPMastertoolkit: Cannot remove constant - wp-config.php does not exist or is not writable: ' . $wp_config_path );
            return false;
        }
        
        $wp_config_content = self::get_wp_config_content();
        
        if ( $wp_config_content === false ) {
			//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( 'WPMastertoolkit: Cannot remove constant - failed to read wp-config.php' );
            return false;
        }
        
        $pattern = '/define\s*\(\s*[\'"]' . preg_quote( $constant_name, '/' ) . '[\'"]\s*,\s*[\s\S]*?\);/';

        $wp_config_content = preg_replace( $pattern, '', $wp_config_content );
        
        // Validate content before writing
        if ( empty( $wp_config_content ) || $wp_config_content === null ) {
			//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( 'WPMastertoolkit: wp-config.php write cancelled - empty content after remove_constant for ' . $constant_name );
            return false;
        }
        
        // Backup before writing
        self::backup_wp_config();
        
        $written = file_put_contents( $wp_config_path, $wp_config_content );
        
        // Validate after writing
        if ( !self::validate_wp_config() ) {
			//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( 'WPMastertoolkit: wp-config.php validation failed after remove_constant for ' . $constant_name . ' - restoring backup' );
            self::restore_wp_config_backup();
            return false;
        }
        
        // Delete backup after successful validation
        self::delete_backup();
        
        return $written;
    }

	/**
	 * Change value of a PHP variable
	 * 
	 * @param string $variable_name
	 */
	public static function change_php_variable( $variable_name, $new_value ) {
		$wp_config_path    = self::get_wp_config_path();
		
		if ( ! file_exists( $wp_config_path ) || ! is_writable( $wp_config_path ) ) {
			//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'WPMastertoolkit: Cannot change variable - wp-config.php does not exist or is not writable: ' . $wp_config_path );
			return false;
		}
		
		$wp_config_content = self::get_wp_config_content();
		
		if ( $wp_config_content === false ) {
			//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'WPMastertoolkit: Cannot change variable - failed to read wp-config.php' );
			return false;
		}
		//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
		$new_variable      = "\n\$" . $variable_name . " = " . var_export( $new_value, true ) . ";\n";
        $wp_config_content = preg_replace( $pattern, $new_variable, $wp_config_content );
        
        // Validate content before writing
        if ( empty( $wp_config_content ) || $wp_config_content === null ) {
			//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( 'WPMastertoolkit: wp-config.php write cancelled - empty content after change_php_variable for ' . $variable_name );
            return false;
        }
        
        // Backup before writing
        self::backup_wp_config();
		
		$written = file_put_contents( $wp_config_path, $wp_config_content );
		
		// Validate after writing
		if ( !self::validate_wp_config() ) {
			//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'WPMastertoolkit: wp-config.php validation failed after change_php_variable for ' . $variable_name . ' - restoring backup' );
			self::restore_wp_config_backup();
			return false;
		}
		
		// Delete backup after successful validation
		self::delete_backup();
		
		return $written;
	}
}
