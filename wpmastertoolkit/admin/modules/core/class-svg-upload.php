<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use enshrined\svgSanitize\Sanitizer;

/**
 * Module Name: SVG Upload
 * Description: Allow SVG upload in media library
 * @since 1.0.0
 */
class WPMastertoolkit_Svg_Upload {

    /**
	 * Invoke Wp Hooks
	 *
	 * @since    1.0.0
	 */
    public function __construct() {
        add_filter( 'wp_check_filetype_and_ext', array( $this, 'svgs_upload_check'), 10, 4 );
        add_filter( 'upload_mimes', array( $this, 'add_svg_mime_type' ) );
        add_filter( 'wp_handle_upload_prefilter', array( $this, 'sanitize_svg' ) );
    }

    /**
     * Add the filetype and extension to the SVG file
     * 
     * @since   1.0.0
     */
    public function svgs_upload_check($data, $file, $filename, $mimes) {

        global $wp_version;
        if ( $wp_version !== '4.7.1' && $wp_version !== '4.7.2' ) return $data;
      
        $filetype = wp_check_filetype( $filename, $mimes );
      
        return array(
            'ext'             => $filetype['ext'] ?? '',
            'type'            => $filetype['type'] ?? '',
            'proper_filename' => $data['proper_filename'] ?? ''
        );
      
    }

    /**
     * Add the SVG mime type to the list of allowed mime types
     * 
     * @since   1.0.0
     */
    public function add_svg_mime_type( $mimes ){

        $mimes['svg']  = 'image/svg+xml';
        $mimes['svgz'] = 'image/svg+xml';

        return $mimes;
    }

    /**
     * Sanitize the SVG file
     * 
     * @since   1.0.0
     */
    public function sanitize_svg( $file ){

        if ( 'image/svg+xml' !== $file['type'] ) return $file;

        if ( ! $this->sanitize_svg_svg_file( $file['tmp_name'] ) ) {
            $file['error'] = __( "Sorry, please check your file", 'wpmastertoolkit' );
        }

        return $file;
    }
    
    /**
     * sanitize_svg_svg_file
     *
     * @param  mixed $file
     * @return void
     */
    public function sanitize_svg_svg_file( $file ){
        $sanitizer = new Sanitizer();

        $unclean = file_get_contents( $file );
        
        $clean = $sanitizer->sanitize( $unclean );

        if( ! $clean ) return false;

        return file_put_contents( $file, $clean );
    }

}