<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Handle the htaccess file
 *
 * @link       https://webdeclic.com
 * @since      1.0.0
 *
 * @package           WPMastertoolkit
 * @subpackage WP-Mastertoolkit/admin
 */

class WPMastertoolkit_Htaccess {

    const FILE_PATH     = ABSPATH . '.htaccess';
    const START_MARKER  = '# BEGIN WPMastertoolkit: ';
    const END_MARKER    = '# END WPMastertoolkit: ';

    /**
     * __construct
     *
     * @return void
     */
    public function __construct() {

    }

    /**
     * Add contents to the file
     */
    public static function add( $new_contents, $contents_id ) {

        $contents       = self::generate_the_new_contents( $contents_id );
        $start_marker   = self::START_MARKER . $contents_id;
		$end_marker     = self::END_MARKER . $contents_id;
        $contents       = $start_marker . "\n" . $new_contents . "\n" . $end_marker . "\n\n" . $contents;

        self::put_content( $contents );
    }

    /**
     * Remove contents from the file
     */
    public static function remove( $id ) {

        $contents = self::generate_the_new_contents( $id );

        self::put_content( $contents );
    }

    /**
     * Generate the new contents
     */
    private static function generate_the_new_contents( $contents_id ) {

        $contents = self::get_file_contents();

        if ( !$contents ) {
            return;
        }

        $start_marker   = self::START_MARKER . $contents_id;
		$end_marker     = self::END_MARKER . $contents_id;

        // Remove previous rules if exist.
        $contents = preg_replace( '/\s*?' . preg_quote( $start_marker, '/' ) . '.*' . preg_quote( $end_marker, '/' ) . '\s*?/isU', "\n\n", $contents );
		$contents = trim( $contents );

        return $contents;
    }

    /**
     * Get the file contents
     */
    private static function get_file_contents() {

        $file_exists    = file_exists( self::FILE_PATH );

        if ( !$file_exists ) {
            
            $dir_name = dirname( self::FILE_PATH );

			if ( ! file_exists( $dir_name ) ){

				mkdir( $dir_name, 0755, true );
			}

			touch( self::FILE_PATH );
        }

        $writable   = wp_is_writable( self::FILE_PATH );

        if ( is_wp_error( $writable ) ) {
			return false;
		}

        $contents = @file_get_contents( self::FILE_PATH );

        if ( false === $contents ) {
            return false;
        }
        
        return $contents;
    }

    /**
     * Write contents to the file.
     */
    private static function put_content( $contents ) {

        $fp = @fopen( self::FILE_PATH, 'wb' );

		if ( ! $fp ) {
			return false;
		}

        mbstring_binary_safe_encoding();

        $data_length = strlen( $contents );

		$bytes_written = fwrite( $fp, $contents );

		reset_mbstring_encoding();

        fclose( $fp );

        if ( $data_length !== $bytes_written ) {
			return false;
		}

        $chmod_file = fileperms( ABSPATH . 'index.php' ) & 0777 | 0644;
        chmod( self::FILE_PATH, $chmod_file );

        return true;
    }

}