<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Get the options
 * 
 * @since   1.0.0
 * @return  array
 */
function wpmastertoolkit_options( $type = 'all' ) {

	if ( 'normal' == $type ) {
		$modules = WPMastertoolkit_Modules_Data::modules_normal_values();
	} elseif ( 'translation' == $type ) {
		$modules = WPMastertoolkit_Modules_Data::modules_translation_values();
	} else {
		$modules = WPMastertoolkit_Modules_Data::modules_values();
	}

    return $modules;
}

/**
 * Get the options groups
 * 
 * @since   1.0.0
 * @return  array
 */
function wpmastertoolkit_settings_groups() {
    $groups = WPMastertoolkit_Modules_Data::modules_groups();
    return $groups;
}

/**
 * Allowed tags for SVG files
 * 
 * @since   1.0.0
 * @return  array
 */
function wpmastertoolkit_allowed_tags_for_svg_files() {
    $allowedtags = array(
        'svg' => array(
            'class'               => true,
            'xmlns'               => true,
            'width'               => true,
            'height'              => true,
            'viewbox'             => true,
            'preserveaspectratio' => true,
            'fill'                => true,
            'aria-hidden'         => true,
            'focusable'           => true,
            'role'                => true,
        ),
        'path' => array(
            'fill'      => true,
            'fill-rule' => true,
            'd'         => true,
            'transform' => true,
        ),
        'polygon' => array(
            'fill'      => true,
            'fill-rule' => true,
            'points'    => true,
            'transform' => true,
            'focusable' => true,
        ),
        'rect' => array(
            'fill'      => true,
            'fill-rule' => true,
            'height'    => true,
            'width'     => true,
            'x'         => true,
            'y'         => true,
        ),
        'line' => array(
            'fill'         => true,
            'fill-rule'    => true,
            'x1'           => true,
            'x2'           => true,
            'y1'           => true,
            'y2'           => true,
            'stroke'       => true,
            'stroke-width' => true,
            'transform'    => true,
        ),
        'defs' => array(
            'id' => true,
        ),
        'clipPath' => array(
            'id' => true,
        ),
        'g' => array(
            'clip-path' => true,
            'mask'      => true,
        ),
        'circle' => array(
            'cx'   => true,
            'cy'   => true,
            'r'    => true,
            'fill' => true,
        ),
        'mask' => array(
            'id'        => true,
            'fill'      => true,
            'style'     => true,
            'maskUnits' => true,
            'x'         => true,
            'y'         => true,
            'width'     => true,
            'height'    => true,
        ),
        'image' => array(
            'id'      => true,
            'href'    => true,
            'x'       => true,
            'y'       => true,
            'width'   => true,
            'height'  => true,
            'clip'    => true,
            'mask'    => true,
            'opacity' => true,
            'xlink:href' => true,
        ),
        'defs' => array(
            'id' => true,
        ),
        'pattern' => array(
            'id'      => true,
            'x'       => true,
            'y'       => true,
            'width'   => true,
            'height'  => true,
            'patternUnits' => true,
            'patternContentUnits' => true,
        ),
        'use' => array(
            'id' => true,
            'x'  => true,
            'y'  => true,
            'xlink:href' => true,
            'transform' => true,
        ),
    );
    return $allowedtags;     
}

/**
 * wpmastertoolkit_kses_svg
 *
 * @param  mixed $svg
 * @return void
 */
function wpmastertoolkit_kses_svg( $svg ) {
    return wp_kses($svg, wpmastertoolkit_allowed_tags_for_svg_files());
}

/**
 * wpmastertoolkit_kses_svg_by_path
 *
 * @param  mixed $relative_path
 * @return void
 */
function wpmastertoolkit_kses_svg_by_path( $relative_path ) {
    $svg = file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . $relative_path );
    return wp_kses($svg, wpmastertoolkit_allowed_tags_for_svg_files());
}

/**
 * wpmastertoolkit_folders
 *
 * @return void
 */
function wpmastertoolkit_folders(){
    $path    = WP_CONTENT_DIR;
    $folders = array(
        'wpmastertoolkit'   => array()
    );

    /**
     * Filter the folders and subfolders to be created.
     *
     * @since 2.0.0
     *
     * @param array    $folders     Array of folders and subfolders.
     */
    $folders = apply_filters( 'wpmastertoolkit/folders', $folders );

    wpmastertoolkit_recursive_mkdir( $folders, $path );

    return WP_CONTENT_DIR . '/wpmastertoolkit';
}

/**
 * wpmastertoolkit_create_index_file
 *
 * @param  mixed $path
 * @return void
 */
function wpmastertoolkit_create_index_file($path){
    if( is_dir( $path ) ){
        $index_file = $path . '/index.php';
        if( !file_exists( $index_file ) ){
            file_put_contents( $index_file, '<?php // Silence is golden.' );
        }
    }
}

/**
 * wpmastertoolkit_get_folder_url
 *
 * @return void
 */
function wpmastertoolkit_get_folder_url(){
    return WP_CONTENT_URL . '/wpmastertoolkit';
}

/**
 * wpmastertoolkit_recursive_mkdir
 *
 * @param  mixed $folders
 * @param  mixed $root_dir_path
 * @return void
 */
function wpmastertoolkit_recursive_mkdir( $folders, $root_dir_path ){
	global $wp_filesystem;

	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}

	WP_Filesystem();

    foreach ($folders as $folder_name => $sub_folders) {
        $folder_path = $root_dir_path . '/' . $folder_name;
        if( !is_dir( $folder_path ) ){
            $wp_filesystem->mkdir( $folder_path , 0705 );
        }

        wpmastertoolkit_create_index_file($folder_path);

        if( is_array( $sub_folders ) && !empty( $sub_folders ) ){
            wpmastertoolkit_recursive_mkdir( $sub_folders, $folder_path );
        }
    }
}

/**
 * Clean variables using sanitize_text_field. Arrays are cleaned recursively.
 */
function wpmastertoolkit_clean( $var ) {

    if ( is_array( $var ) ) {
		return array_map( 'wpmastertoolkit_clean', $var );
	} else {
		return sanitize_text_field( $var );
	}
}

/**
 * wpmastertoolkit_recursive_rmdir
 *
 * @param  mixed $folders
 * @param  mixed $root_dir_path
 * @return void
 */
function wpmastertoolkit_zip_file_or_folder( $paths, $output_path ){
    $zip = new ZipArchive();
    $zip->open( $output_path, ZipArchive::CREATE | ZipArchive::OVERWRITE );
    if( is_array( $paths ) ){
        foreach ($paths as $path) {
            if( is_dir( $path ) ){
                $folderName = basename($path);
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator( $path ),
                    RecursiveIteratorIterator::LEAVES_ONLY
                );
                foreach ($files as $name => $file) {
                    if (!$file->isDir()) {
                        $filePath     = $file->getRealPath();
                        $relativePath = $folderName . '/' . substr($filePath, strlen($path) + 1);
                        $zip->addFile($filePath, $relativePath);
                    }
                }
            } else {
                $zip->addFile( $path, basename( $path ) );
            }
        }
    } else {
        $path = $paths;
        if( is_dir( $path ) ){
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator( $path ),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($files as $name => $file) {
                if (!$file->isDir()) {
                    $filePath     = $file->getRealPath();
                    $realPath     = realpath($path);
                    $relativePath = str_replace($realPath, '', $filePath);
                    $zip->addFile($filePath, $relativePath);
                }
            }
        } else {
            $zip->addFile( $path, basename( $path ) );
        }
    }
    
    $zip->close();
}

/**
 * Check if this is the pro version
 */
function wpmastertoolkit_is_pro() {
	$folder = WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/modules/pro';

	if ( is_dir( $folder ) ) {
		return true;
	}

	return false;
}
