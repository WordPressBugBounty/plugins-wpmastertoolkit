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
        add_filter( 'wp_handle_upload_prefilter', array( $this, 'sanitize_svg' ), PHP_INT_MAX );
        add_filter( 'wp_handle_sideload_prefilter', array( $this, 'sanitize_svg' ), PHP_INT_MAX );
        add_filter( 'wp_upload_bits', array( $this, 'reject_svg_upload_bits' ), PHP_INT_MAX );
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

        unset( $mimes['svgz'] );

        if ( is_multisite() ) {
            $network_filetypes = preg_split( '/\s+/', strtolower( trim( (string) get_site_option( 'upload_filetypes', '' ) ) ) );

            if ( ! in_array( 'svg', $network_filetypes, true ) ) return $mimes;
        }

        $mimes['svg']  = 'image/svg+xml';

        return $mimes;
    }

    /**
     * Sanitize the SVG file
     * 
     * @since   1.0.0
     */
    public function sanitize_svg( $file ){

        if ( ! empty( $file['error'] ) ) return $file;

        $filename  = isset( $file['name'] ) ? $file['name'] : '';
        $filetype  = wp_check_filetype( $filename, array( 'svg' => 'image/svg+xml' ) );
        $extension = strtolower( (string) $filetype['ext'] );

        if ( 'svg' !== $extension ) return $file;

        $temporary_file = isset( $file['tmp_name'] ) ? $file['tmp_name'] : '';

		//phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
        if ( ! is_file( $temporary_file ) || ! is_readable( $temporary_file ) || ! is_writable( $temporary_file ) ) {
            $file['error'] = __( 'Sorry, this SVG file could not be sanitized.', 'wpmastertoolkit' );
            return $file;
        }

        if ( ! $this->sanitize_svg_svg_file( $temporary_file ) ) {
            $file['error'] = __( 'Sorry, this SVG file could not be sanitized.', 'wpmastertoolkit' );
        }

        return $file;
    }

    /**
     * Reject SVG uploads that bypass the mutable upload prefilters.
     *
        * @since   2.22.0
     */
    public function reject_svg_upload_bits( $upload_bits ) {
        if ( ! is_array( $upload_bits ) ) return $upload_bits;

        $filename = isset( $upload_bits['name'] ) ? $upload_bits['name'] : '';
        $filetype = wp_check_filetype(
            $filename,
            array(
                'svg'  => 'image/svg+xml',
                'svgz' => 'image/svg+xml',
            )
        );
        $extension = strtolower( (string) $filetype['ext'] );

        if ( in_array( $extension, array( 'svg', 'svgz' ), true ) ) {
            return __( 'Sorry, SVG files cannot be uploaded through this method.', 'wpmastertoolkit' );
        }

        return $upload_bits;
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

        if ( false === $unclean ) return false;
        
        $clean = $sanitizer->sanitize( $unclean );

        if ( ! is_string( $clean ) || '' === trim( $clean ) ) return false;

        $written_bytes = file_put_contents( $file, $clean, LOCK_EX );

        return strlen( $clean ) === $written_bytes;
    }
}
