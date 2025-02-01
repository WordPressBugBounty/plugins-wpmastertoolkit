<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Child Theme Generator
 * Description: A simple tool for generate a child theme on your WordPress. You can desactivated it after generation.
 * @since 1.9.0
 */
class WPMastertoolkit_Child_Theme_Generator {

    private $option_id;
    private $header_title;
    private $nonce_action;
    private $settings;

	/**
     * Invoke the hooks
     */
    public function __construct() {
        $this->option_id    = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_child_theme_generator';
        $this->nonce_action = $this->option_id . '_action';

        add_action( 'init', array( $this, 'class_init' ) );
        add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
        add_action( 'admin_init', array( $this, 'save_submenu' ) );
    }

	/**
     * Initialize the class
     * 
     * @return   void
     */
    public function class_init() {
        $this->header_title = esc_html__( 'Child Theme Generator', 'wpmastertoolkit' );
    }
	
	/**
     * Add a submenu
     */
    public function add_submenu(){
        add_submenu_page(
            'wp-mastertoolkit-settings',
            $this->header_title,
            $this->header_title,
            'manage_options',
            'wp-mastertoolkit-settings-child-theme-generator',
            array( $this, 'render_submenu' ),
            null
        );
    }

	/**
     * Render the submenu
     */
    public function render_submenu() {
        $themes = wp_get_themes();
        $themes_folders = array();
        foreach ( $themes as $theme ) {
            $themes_folders[] = $theme->get_stylesheet();
        }

        $submenu_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/child-theme-generator.asset.php' );
        wp_enqueue_style( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/child-theme-generator.css', array(), $submenu_assets['version'], 'all' );
        wp_enqueue_script( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/child-theme-generator.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );
        wp_localize_script( 'WPMastertoolkit_submenu', 'wpmastertoolkit_child_theme_generator', array(
            'themes_folders' => $themes_folders,
            'i18n' => array(
                'folder_already_exist' => esc_js( esc_html__( 'This folder name already exists', 'wpmastertoolkit' ) ),
                'anonymous' => esc_js( esc_html__( 'Anonymous', 'wpmastertoolkit' ) ),
            ),
        ) );

        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/submenu/header.php';
        $this->submenu_content();
        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/submenu/footer.php';
    }

	/**
     * Save the submenu option
     */
    public function save_submenu() {
		$nonce = sanitize_text_field( $_POST['_wpnonce'] ?? '' );

		if ( wp_verify_nonce( $nonce, $this->nonce_action ) && isset( $_POST[ $this->option_id ] ) ) {

            $settings = array_map('sanitize_text_field', $_POST[ $this->option_id ]);

            if( !empty( $settings['action'] ) ){
				wp_filesystem();
                global $wp_filesystem;

				$child_theme_folder_name = sanitize_file_name( trim( $settings['child_theme_folder_name'] ?? '' ) );
                $child_theme_name        = trim( $settings['child_theme_name'] ?? '' );
                $child_theme_version     = trim( $settings['child_theme_version'] ?? '' );
                $child_theme_author      = trim( $settings['child_theme_author'] ?? '' );
                $child_theme_author_uri  = trim( $settings['child_theme_author_uri'] ?? '' );
                $child_theme_tags        = trim( $settings['child_theme_tags'] ?? '' );
                $child_theme_description = trim( $settings['child_theme_description'] ?? '' );
                $child_theme_uri         = trim( $settings['child_theme_uri'] ?? '' );
                $child_theme_text_domain = trim( strtolower( str_replace( array( ' ', '_' ), '-', $child_theme_name ) ) );
                $active_theme = wp_get_theme();

                if ( $settings['action'] == 'generate' || $settings['action'] == 'generate-and-activate' ){
					$themes_dir = get_theme_root();
				} else {
					$themes_dir = get_temp_dir();
					$themes_dir = $themes_dir . md5( microtime() ) . '/';
				}

                wp_mkdir_p( $themes_dir );
            
				$child_theme_folder = $themes_dir . '/' . $child_theme_folder_name;

                wp_mkdir_p( $child_theme_folder );

                $template_name = $active_theme->get_template();

                if( empty($_FILES[ 'child_theme_screenshot' ]['tmp_name']) ){
                    $theme_image = $active_theme->get_screenshot();
                    $extension = pathinfo( $theme_image, PATHINFO_EXTENSION );
                    $theme_image = str_replace( get_template_directory_uri(), get_template_directory(), $theme_image );
                    $screenshot_path = $child_theme_folder . '/screenshot.' . $extension;
                    if( file_exists( $theme_image ) ) {
                        copy( $theme_image, $screenshot_path );
                    }
                } else {
                    $screenshot = $_FILES[ 'child_theme_screenshot' ];

					if ( isset( $screenshot ) && $screenshot['error'] === UPLOAD_ERR_OK ) {
						$allowed_extensions = ['jpg', 'jpeg', 'png'];
    					$file_name          = sanitize_file_name( $screenshot['name'] );
    					$file_tmp_name      = $screenshot['tmp_name'];

						// Check file type and extension
						$file_info       = wp_check_filetype_and_ext( $file_tmp_name, $file_name );
						$extension       = $file_info['ext'];
						$type            = $file_info['type'];
						$proper_filename = $file_info['proper_filename'];

						// Validate the file
						if ( ! $extension || ! $type || ! in_array( $extension, $allowed_extensions, true ) ) {
							exit();
						}

						// Ensure the file has a proper name (use the sanitized or resolved filename)
						$screenshot_name = 'screenshot.' . $extension;
						$screenshot_path = $child_theme_folder . '/' . $screenshot_name;

						// Move the uploaded file to the correct location
						if ( ! move_uploaded_file( $file_tmp_name, $screenshot_path ) ) {
							exit();
						}

					} else {
						exit;
					}
                }

                // create CSS file
                $css_file = $child_theme_folder . '/style.css';
                
                $css_content = "/**\n";
                $css_content .= " * Theme Name: " . $child_theme_name . "\n";
                if( !empty( $child_theme_uri ) ){
                    $css_content .= " * Theme URI:  " . $child_theme_uri . "\n";
                }
                $css_content .= " * Description: " . $child_theme_description . "\n";
                if( !empty( $child_theme_author ) ){
                    $css_content .= " * Author:     " . $child_theme_author . "\n";
                }
                if( !empty( $child_theme_author_uri ) ){
                    $css_content .= " * Author URI: " . $child_theme_author_uri . "\n";
                }
                $css_content .= " * Template:   " . $template_name . "\n";
                $css_content .= " * Version:    " . $child_theme_version . "\n";
                $css_content .= " * License:    GNU General Public License v2 or later\n";
                $css_content .= " * License URI: https://www.gnu.org/licenses/gpl-2.0.html\n";
                if( !empty( $child_theme_tags ) ){
                    $css_content .= " * Tags:       " . $child_theme_tags . "\n";
                }
                $css_content .= " * Text Domain: " . $child_theme_text_domain . "\n";
                $css_content .= " * Generated by WPMasterToolKit\n";
                $css_content .= " * @link https://wordpress.org/plugins/wpmastertoolkit/\n";
                $css_content .= " */\n";
                
                $wp_filesystem->put_contents( $css_file, $css_content, FS_CHMOD_FILE );

                // create functions.php file
                $functions_file = $child_theme_folder . '/functions.php';
                $functions_content = "<?php\n";
                $functions_content .= "if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly\n";
                $functions_content .= "/**\n";
                $functions_content .= " * Theme functions and definitions\n";
                $functions_content .= " *\n";
                $functions_content .= " * Generated by WPMasterToolKit\n";
                $functions_content .= " * @link https://wordpress.org/plugins/wpmastertoolkit/\n";
                $functions_content .= " */\n";
                $functions_content .= "\n";
                $functions_content .= "/**\n";
                $functions_content .= " * Enqueue the parent theme stylesheet.\n";
                $functions_content .= " */\n";
                $functions_content .= "add_action( 'wp_enqueue_scripts', 'enqueue_parent_styles' );\n";
                $functions_content .= "function enqueue_parent_styles() {\n";
                $functions_content .= "    wp_enqueue_style( '" . $child_theme_text_domain . "-style', get_template_directory_uri() . '/style.css' );\n";
                $functions_content .= "}\n";

                $wp_filesystem->put_contents( $functions_file, $functions_content, FS_CHMOD_FILE );

                
                if( $settings['action'] == 'download' ){

					if ( empty( $child_theme_folder_name ) ) {
						exit();
					}

					$child_theme_folder = $themes_dir . '/' . $child_theme_folder_name;
					if ( ! is_dir( $child_theme_folder ) ) {
						exit();
					}

                    $zip_file = $themes_dir . $child_theme_folder_name . '.zip';
                    
                    $zip = new ZipArchive;
                    if ( $zip->open( $zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
						exit();
					}

                    $files = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator( $child_theme_folder ),
                        RecursiveIteratorIterator::LEAVES_ONLY
                    );
                    foreach ( $files as $name => $file ) {
                        if ( ! $file->isDir() ) {
                            $filePath = $file->getRealPath();
                            $relativePath = substr( $filePath, strlen( realpath( $child_theme_folder ) ) );
                            $zip->addFile( $filePath, $child_theme_folder_name . $relativePath );
                        }
                    }
                    $zip->close();

                    if( !file_exists( $zip_file ) ){
                        exit( 'Error creating ZIP file' );
                    }

                    header( 'Content-Type: application/zip' );
                    header( 'Content-Disposition: attachment; filename="' . $child_theme_folder_name . '.zip"' );
                    header( 'Content-Length: ' . filesize( $zip_file ) );
                    readfile( $zip_file );

                    unlink( $zip_file );
                    @rmdir( $themes_dir );
                    exit;
                }

                if ( $settings['action'] == 'generate' || $settings['action'] == 'generate-and-activate' ){
                    if ( $settings['action'] == 'generate-and-activate' ){
                        switch_theme( $child_theme_folder_name );
                    }

                    // redirect to the themes page
                    wp_safe_redirect( admin_url( 'themes.php' ) );
                    exit;
                }
            }

            wp_safe_redirect( sanitize_url( $_SERVER['REQUEST_URI'] ?? '' ) );
			exit;
		}
    }

	/**
     * Add the submenu content
     * 
     * @return void
     */
    private function submenu_content() {
        $active_theme = wp_get_theme();

        $themes = wp_get_themes();
        foreach ( $themes as $theme ) {
            if ( $theme->parent() && $theme->parent()->get( 'Name' ) == $active_theme->get( 'Name' ) ) {
                $child_theme_name = $theme->get( 'Name' );
                $parent_theme_name = $theme->parent()->get( 'Name' );
                ?>
                <div class="wp-mastertoolkit__section">
                    <?php printf( esc_html__( 'The child theme "%s" is already exist for the parent theme "%s".', 'wpmastertoolkit' ), $child_theme_name, $parent_theme_name ); ?>
                </div>
                <?php
                return;
            }
        }        

        $theme_name     = $active_theme->get( 'Name' );
        $theme_uri      = $active_theme->get( 'ThemeURI' );
        $theme_desc     = $active_theme->get( 'Description' );
        $theme_auth     = $active_theme->get( 'Author' );
        $theme_auth_uri = $active_theme->get( 'AuthorURI' );
        $theme_vers     = $active_theme->get( 'Version' );
        $theme_tags     = $active_theme->get( 'Tags' );
        $theme_image    = $active_theme->get_screenshot();
        ?>
            <div class="wp-mastertoolkit__section">
                <div class="wp-mastertoolkit__section__desc">
					<?php esc_html_e( "Generate a child theme for the current active theme. The child theme will be generated in a ZIP file or directly in the themes directory.", 'wpmastertoolkit' ); ?>
				</div>
                <div class="wp-mastertoolkit__section__body">

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Child Theme Name', 'wpmastertoolkit' ); ?>*</div>
                        <div class="wp-mastertoolkit__section__body__item__content">

                            <div class="wp-mastertoolkit__input-text">
								<input type="text" name="<?php echo esc_attr( $this->option_id . '[child_theme_name]' ); ?>" value="<?php echo esc_attr( $theme_name . ' Child' ?? '' ); ?>" style="width: 400px;" required>
							</div>
                            <div class="description"><?php esc_html_e( 'The name of the child theme.', 'wpmastertoolkit' ); ?></div>

                        </div>
                    </div>
					
                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Child Theme URI', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">

                            <div class="wp-mastertoolkit__input-text">
								<input type="text" name="<?php echo esc_attr( $this->option_id . '[child_theme_uri]' ); ?>" value="<?php echo esc_attr( $theme_uri ?? '' ); ?>" style="width: 400px;">
							</div>

                        </div>
                    </div>
					
                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Child Theme Version', 'wpmastertoolkit' ); ?>*</div>
                        <div class="wp-mastertoolkit__section__body__item__content">

                            <div class="wp-mastertoolkit__input-text">
								<input type="text" name="<?php echo esc_attr( $this->option_id . '[child_theme_version]' ); ?>" value="<?php echo esc_attr( '1.0.0' ?? '' ); ?>" style="width: 400px;" required pattern="^\d+\.\d+\.\d+$" title="<?php esc_html_e( 'The version must be in the format of x.x.x.', 'wpmastertoolkit' ); ?>">
							</div>
                            <div class="description"><?php printf( esc_html__( 'The version of the parent theme is %s.', 'wpmastertoolkit' ), $theme_vers ); ?></div>

                        </div>
                    </div>
                    
                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Author', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">

                            <div class="wp-mastertoolkit__input-text">
								<input type="text" name="<?php echo esc_attr( $this->option_id . '[child_theme_author]' ); ?>" value="<?php echo esc_attr( $theme_auth ?? '' ); ?>" style="width: 400px;">
                            </div>
                            <div class="description"><?php esc_html_e( '"Anonymous" by default if empty.', 'wpmastertoolkit' ); ?></div>

                        </div>
                    </div>
                   
                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Author URI', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">

                            <div class="wp-mastertoolkit__input-text">
								<input type="text" name="<?php echo esc_attr( $this->option_id . '[child_theme_author_uri]' ); ?>" value="<?php echo esc_attr( $theme_auth_uri ?? '' ); ?>" style="width: 400px;">
                            </div>

                        </div>
                    </div>
                    
                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Tags', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">

                            <div class="wp-mastertoolkit__input-text">
								<input type="text" name="<?php echo esc_attr( $this->option_id . '[child_theme_tags]' ); ?>" value="<?php echo esc_attr( implode( ', ', $theme_tags ) ?? '' ); ?>" style="width: 400px;">
                            </div>

                        </div>
                    </div>
                    
                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Folder Name', 'wpmastertoolkit' ); ?>*</div>
                        <div class="wp-mastertoolkit__section__body__item__content">

                            <div class="wp-mastertoolkit__input-text">
								<input type="text" name="<?php echo esc_attr( $this->option_id . '[child_theme_folder_name]' ); ?>" value="<?php echo esc_attr( strtolower( str_replace( ' ', '-', $theme_name ) ) . '-child' ?? '' ); ?>" style="width: 400px;"  pattern="^[a-zA-Z0-9_\-]+$" title="<?php esc_html_e( 'Only letters, numbers, underscores and hyphens are allowed.', 'wpmastertoolkit' ); ?>" required>
                            </div>

                        </div>
                    </div>

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Description', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
							<div class="wp-mastertoolkit__textarea">
                                <textarea name="<?php echo esc_attr( $this->option_id ); ?>[child_theme_description]" cols="50" rows="10" style="width: 400px;"><?php echo esc_textarea( stripslashes( $theme_desc ?? '') ); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Screenshot (1200x900)', 'wpmastertoolkit' ); ?></div>
                        <input type="file" name="<?php echo esc_attr( 'child_theme_screenshot' ); ?>" accept="image/*">
                        <div class="description"><?php esc_html_e( 'Leave empty to use the parent theme screenshot.', 'wpmastertoolkit' ); ?></div>
                    </div>

                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__content">
                            <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Preview & generation', 'wpmastertoolkit' ); ?></div>
                            <div class="wp-mastertoolkit__button">
								<button class="" id="preview-child-theme">
                                    <?php esc_html_e( 'Preview', 'wpmastertoolkit' ); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="preview-child-theme-container" class="theme-overlay" tabindex="0" role="dialog" aria-label="<?php esc_html_e( 'Theme Details', 'wpmastertoolkit' ); ?>" style="display:none;">
                <div class="theme-overlay">
                    <div class="theme-backdrop"></div>
                    <div class="theme-wrap wp-clearfix" role="document">
                        <div class="child-theme-folder-name-container">
                            <span class="dashicons dashicons-open-folder"></span> <span id="child-theme-folder-name"></span>
                        </div>
                        <div class="theme-header">
                            <button class="close dashicons dashicons-no" id="close-preview-child-theme">
                            <span class="screen-reader-text"> <?php esc_html_e( 'Close preview', 'wpmastertoolkit' ); ?> </span>
                            </button>
                        </div>
                        <div class="theme-about wp-clearfix">
                            <div class="theme-screenshots">
                                <div class="screenshot">
                                    <img id="child-theme-screenshot" src="" alt="" data-default-src="<?php echo esc_url( $theme_image ); ?>">
                                </div>
                            </div>
                            <div class="theme-info">
                                <h2 class="theme-name">
                                    <span id="child-theme-name"></span> <span class="theme-version"> <?php esc_html_e( 'Version :', 'wpmastertoolkit' ); ?> <span id="child-theme-version"></span></span>
                                </h2>
                                <p class="theme-author">
                                    <?php esc_html_e('By', 'wpmastertoolkit' ); ?> <a id="child-theme-author" href="#" target="_blank"></a>
                                </p>
                                <div class="theme-autoupdate">
                                    <div class="notice notice-error notice-alt inline hidden">
                                    <p></p>
                                    </div>
                                </div>
                                <p class="theme-description" id="child-theme-description"></p>
                                <p class="parent-theme"> 
                                    <?php printf( wp_kses_post( 'This theme is a child theme of <strong>%s</strong>.', 'wpmastertoolkit' ), $theme_name ); ?>
                                </p>
                                <p class="theme-tags">
                                    <span><?php esc_html_e('Tags:', 'wpmastertoolkit' ); ?></span> <span style="font-weight: normal;" id="child-theme-tags"></span>
                                </p>
                            </div>
                        </div>
                        <div class="theme-actions">
                            <div>
                                <button id="child-theme-download-zip" class="button"><?php esc_html_e( 'Download as ZIP', 'wpmastertoolkit' ); ?></button>
                                <button id="child-theme-generate" class="button"><?php esc_html_e( 'Generate', 'wpmastertoolkit' ); ?></button>
                                <button id="child-theme-generate-and-activate" class="button button-primary"><?php esc_html_e( 'Generate & Activate', 'wpmastertoolkit' ); ?></button>
                            </div>
                        </div>
                    </div>
                </div>
                <input type="hidden" name="<?php echo esc_attr( $this->option_id . '[action]' ); ?>">
            </div>
        <?php
    }
}
