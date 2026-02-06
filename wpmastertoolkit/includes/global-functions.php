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
 * Get the options groups
 * 
 * @since   2.8.0
 * @return  array
 */
function wpmastertoolkit_ai_modules() {
    return array(
        'openai'  => array(
            'name'    => 'OpenAI',
            'options' => array(
                'gpt-4.1' => array(
                    'name'    => 'GPT-4.1',
                    'support' => array( 'texttotext', 'imagetotext' ),
                ),
                'gpt-4.1-mini' => array(
                    'name'    => 'GPT-4.1 mini',
                    'support' => array( 'texttotext', 'imagetotext' ),
                ),
                'gpt-4.1-nano' => array(
                    'name'    => 'GPT-4.1 nano',
                    'support' => array( 'texttotext', 'imagetotext' ),
                ),
                'gpt-4' => array(
                    'name'    => 'GPT-4',
                    'support' => array( 'texttotext', 'imagetotext' ),
                ),
                'gpt-4-turbo' => array(
                    'name'    => 'GPT-4 Turbo',
                    'support' => array( 'texttotext', 'imagetotext' ),
                ),
                'gpt-4o' => array(
                    'name'    => 'GPT-4o',
                    'support' => array( 'texttotext', 'imagetotext' ),
                ),
                'gpt-4o-mini' => array(
                    'name'    => 'GPT-4o mini',
                    'support' => array( 'texttotext', 'imagetotext' ),
                ),
                'chatgpt-4o-latest' => array(
                    'name'    => 'ChatGPT-4o',
                    'support' => array( 'texttotext', 'imagetotext' ),
                ),
                'gpt-3.5-turbo' => array(
                    'name'    => 'GPT-3.5 Turbo',
                    'support' => array( 'texttotext' ),
                ),
                'o4-mini' => array(
                    'name'    => 'o4-mini',
                    'support' => array( 'texttotext', 'imagetotext' ),
                ),
                'o3' => array(
                    'name'    => 'o3',
                    'support' => array( 'texttotext', 'imagetotext' ),
                ),
				'o3-mini' => array(
                    'name'    => 'o3-mini',
                    'support' => array( 'texttotext' ),
                ),
                'o1' => array(
                    'name'    => 'o1',
                    'support' => array( 'texttotext', 'imagetotext' ),
                ),
                'o1-pro' => array(
                    'name'    => 'o1-pro',
                    'support' => array( 'texttotext', 'imagetotext' ),
                ),
            ),
        ),
        'gemini'  => array(
            'name'    => 'Gemini',
            'options' => array(
                'gemini-2.0-flash' => array(
                    'name'    => '2.0 Flash',
                    'support' => array( 'texttotext', 'imagetotext' ),
                ),
                'gemini-2.0-flash-lite' => array(
                    'name'    => '2.0 Flash Lite',
                    'support' => array( 'texttotext', 'imagetotext' ),
                ),
                'gemini-1.5-pro' => array(
                    'name'    => '1.5 Pro',
                    'support' => array( 'texttotext', 'imagetotext' ),
                ),
                'gemini-1.5-flash' => array(
                    'name'    => '1.5 Flash',
                    'support' => array( 'texttotext', 'imagetotext' ),
                ),
                'gemini-1.5-flash-8b' => array(
                    'name'    => '1.5 Flash-8b',
                    'support' => array( 'texttotext', 'imagetotext' ),
                ),
            ),
        ),
		'mistral' => array(
			'name'    => 'Mistral',
			'options' => array(
				'pixtral-12b-latest' => array(
					'name'    => 'Pixtral 12B',
					'support' => array( 'texttotext', 'imagetotext' ),
				),
				'pixtral-large-latest' => array(
					'name'    => 'Pixtral Large',
					'support' => array( 'texttotext', 'imagetotext' ),
				),
				'mistral-small-latest' => array(
					'name'    => 'Mistral Small',
					'support' => array( 'texttotext', 'imagetotext' ),
				),
			),
		),
        'claude' => array(
            'name'    => 'Claude',
            'options' => array(
                'claude-3-7-sonnet-latest' => array(
                    'name'    => '3.7 Sonnet',
                    'support' => array('texttotext', 'imagetotext'),
                ),
                'claude-3-5-sonnet-latest' => array(
                    'name'    => '3.5 Sonnet',
                    'support' => array('texttotext', 'imagetotext'),
                ),
                'claude-3-5-haiku-latest' => array(
                    'name'    => '3.5 Haiku',
                    'support' => array('texttotext', 'imagetotext'),
                ),
            ),
        ),
    );
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
            'stroke' => true,
            'stroke-width' => true,
            'stroke-dasharray' => true,
            'stroke-linecap' => true,
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

/**
 * Get languages
 * 
 * @since   2.8.0
 */
function wpmastertoolkit_languages() {
	$languages = array(
		'en' => array(
			'display_name' => __( 'English', 'wpmastertoolkit' ),
			'english_name' => 'English',
			'iso'          => 'en',
		),
		'fr' => array(
			'display_name' => __( 'French', 'wpmastertoolkit' ),
			'english_name' => 'French',
			'iso'          => 'fr',
		),
		'es' => array(
			'display_name' => __( 'Spanish', 'wpmastertoolkit' ),
			'english_name' => 'Spanish',
			'iso'          => 'es',
		),
		'de' => array(
			'display_name' => __( 'German', 'wpmastertoolkit' ),
			'english_name' => 'German',
			'iso'          => 'de',
		),
		'it' => array(
			'display_name' => __( 'Italian', 'wpmastertoolkit' ),
			'english_name' => 'Italian',
			'iso'          => 'it',
		),
		'pt' => array(
			'display_name' => __( 'Portuguese', 'wpmastertoolkit' ),
			'english_name' => 'Portuguese',
			'iso'          => 'pt',
		),
		'nl' => array(
			'display_name' => __( 'Dutch', 'wpmastertoolkit' ),
			'english_name' => 'Dutch',
			'iso'          => 'nl',
		),
		'ru' => array(
			'display_name' => __( 'Russian', 'wpmastertoolkit' ),
			'english_name' => 'Russian',
			'iso'          => 'ru',
		),
		'ja' => array(
			'display_name' => __( 'Japanese', 'wpmastertoolkit' ),
			'english_name' => 'Japanese',
			'iso'          => 'ja',
		),
		'zh' => array(
			'display_name' => __( 'Chinese', 'wpmastertoolkit' ),
			'english_name' => 'Chinese',
			'iso'          => 'zh',
		),
		'ar' => array(
			'display_name' => __( 'Arabic', 'wpmastertoolkit' ),
			'english_name' => 'Arabic',
			'iso'          => 'ar',
		),
		'ko' => array(
			'display_name' => __( 'Korean', 'wpmastertoolkit' ),
			'english_name' => 'Korean',
			'iso'          => 'ko',
		),
		'hi' => array(
			'display_name' => __( 'Hindi', 'wpmastertoolkit' ),
			'english_name' => 'Hindi',
			'iso'          => 'hi',
		),
		'tr' => array(
			'display_name' => __( 'Turkish', 'wpmastertoolkit' ),
			'english_name' => 'Turkish',
			'iso'          => 'tr',
		),
		'sv' => array(
			'display_name' => __( 'Swedish', 'wpmastertoolkit' ),
			'english_name' => 'Swedish',
			'iso'          => 'sv',
		),
		'pl' => array(
			'display_name' => __( 'Polish', 'wpmastertoolkit' ),
			'english_name' => 'Polish',
			'iso'          => 'pl',
		),
		'cs' => array(
			'display_name' => __( 'Czech', 'wpmastertoolkit' ),
			'english_name' => 'Czech',
			'iso'          => 'cs',
		),
		'el' => array(
			'display_name' => __( 'Greek', 'wpmastertoolkit' ),
			'english_name' => 'Greek',
			'iso'          => 'el',
		),
		'he' => array(
			'display_name' => __( 'Hebrew', 'wpmastertoolkit' ),
			'english_name' => 'Hebrew',
			'iso'          => 'he',
		),
	);

	return $languages;	
}

/**
 * Get current IP
 * 
 * @since 2.12.0
 */
function wpmastertoolkit_get_current_ip() {
	if ( isset( $_SERVER['HTTP_CLIENT_IP'] ) ) {
		return sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
	} elseif ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		return sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
	} elseif ( isset( $_SERVER['HTTP_X_FORWARDED'] ) ) {
		return sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED'] ) );
	} elseif ( isset( $_SERVER['HTTP_FORWARDED_FOR'] ) ) {
		return sanitize_text_field( wp_unslash( $_SERVER['HTTP_FORWARDED_FOR'] ) );
	} elseif ( isset( $_SERVER['HTTP_FORWARDED'] ) ) {
		return sanitize_text_field( wp_unslash( $_SERVER['HTTP_FORWARDED'] ) );
	} elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
		return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
	} else {
		return '';
	}
}