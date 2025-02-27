<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Image Upload Control
 * Description: Resize newly uploaded, large images to a smaller dimension and delete originally uploaded files. BMPs and non-transparent PNGs will be converted to JPGs and resized.
 * @since 1.5.0
 */
class WPMastertoolkit_Image_Upload_Control {

    private $option_id;
    private $header_title;
    private $nonce_action;
    private $settings;
    private $default_settings;
	private $prefix_to_ignore      = '-wpm';
	private $applicable_mime_types = array(
		'image/bmp',
		'image/x-ms-bmp',
		'image/png',
		'image/jpeg',
		'image/jpg',
		'image/webp'
	);
	private $mime_types_to_resize  = array(
		'image/jpeg',
		'image/jpg',
		'image/png',
		'image/webp'
	);

	/**
     * Invoke the hooks
     * 
     * @since    1.5.0
     */
    public function __construct() {
        $this->option_id    = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_image_upload_control';
        $this->nonce_action = $this->option_id . '_action';

        add_action( 'init', array( $this, 'class_init' ) );
        add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
        add_action( 'admin_init', array( $this, 'save_submenu' ) );
		add_filter( 'wp_handle_upload', array( $this, 'image_upload_handler' ) );
    }

	/**
     * Initialize the class
     * 
     * @since    1.5.0
     * @return   void
     */
    public function class_init() {
        $this->header_title = esc_html__( 'Image Upload Control', 'wpmastertoolkit' );
    }

	/**
	 * Handler for image upload
	 * 
	 * @since   1.5.0
	 */
	public function image_upload_handler( $upload ) {

		// Exlude from conversion and resizing images with filenames ending with prefix_to_ignore
		if ( false !== strpos( $upload['file'], $this->prefix_to_ignore . '.' ) ) {
			return $upload;
		}
		
		if ( in_array( $upload['type'], $this->applicable_mime_types ) ){
			// Convert BMP
			if ( 'image/bmp' === $upload['type'] || 'image/x-ms-bmp' === $upload['type'] ) {
				$upload = $this->maybe_convert_image( 'bmp', $upload );
			}
			// Convert PNG without transparency
            if ( 'image/png' === $upload['type'] ) {
                $upload = $this->maybe_convert_image( 'png', $upload );
            }

            if ( ! is_wp_error( $upload ) && in_array( $upload['type'], $this->mime_types_to_resize ) && filesize( $upload['file'] ) > 0 ) {
				$wp_image_editor = wp_get_image_editor( $upload['file'] );
				if ( ! is_wp_error( $wp_image_editor ) ) {
					$image_size     = $wp_image_editor->get_size();
					$this->settings = $this->get_settings();
					$max_width      = $this->settings['max_width'] ?? '1920';
					$max_height     = $this->settings['max_height'] ?? '1920';

					if ( isset( $image_size['width'] ) && $image_size['width'] > $max_width || isset( $image_size['height'] ) && $image_size['height'] > $max_height ) {
                        $wp_image_editor->resize( $max_width, $max_height, false );
                        if ( 'image/jpg' === $upload['type'] || 'image/jpeg' === $upload['type'] ) {
                            $wp_image_editor->set_quality( 90 );
                        }
                        $wp_image_editor->save( $upload['file'] );
                    }
				}
			}
		}

		return $upload;
	}

	/**
	 * Convert BMP or PNG without transparency into JPG
	 * 
	 * @since   1.5.0
	 */
	public function maybe_convert_image( $file_extension, $upload ) {
        $image_object = null;
        // Get image object from uploaded BMP/PNG
        if ( 'bmp' === $file_extension ) {
            if ( is_file( $upload['file'] ) ) {
                // Generate image object from BMP for conversion to JPG later
                if ( function_exists( 'imagecreatefrombmp' ) ) {
                    $image_object = imagecreatefrombmp( $upload['file'] );
                }
            }
        }

        if ( 'png' === $file_extension ) {
            // Detect alpha/transparency in PNG
            $png_has_transparency = false;
            if ( is_file( $upload['file'] ) ) {
                // We assume GD library is present, so 'imagecreatefrompng' function is available
                // Generate image object from PNG for potential conversion to JPG later.
                $image_object = imagecreatefrompng( $upload['file'] );
                // Get image dimension
                list( $width, $height ) = getimagesize( $upload['file'] );
                // Run through pixels until transparent pixel is found
                for ($x = 0; $x < $width; $x++) {
                    for ($y = 0; $y < $height; $y++) {
                        $pixel_color_index = imagecolorat( $image_object, $x, $y );
                        $pixel_rgba = imagecolorsforindex( $image_object, $pixel_color_index );
                        // array of red, green, blue and alpha values
                        if ( $pixel_rgba['alpha'] > 0 ) {
                            // a pixel with alpha/transparency has been found
                            // alpha value range from 0 (completely opaque) to 127 (fully transparent).
                            // Ref: https://www.php.net/manual/en/function.imagecolorallocatealpha.php
                            $png_has_transparency = true;
                            break 2;
                            // Break both 'for' loops
                        }
                    }
                }
            }
            // Do not convert PNG with alpha/transparency
            if ( $png_has_transparency ) {
                return $upload;
            }
        }

        // When conversion from BMP/PNG to JPG is successful. Last parameter is JPG quality (0-100).
        if ( is_object( $image_object ) ) {
            $wp_uploads   = wp_upload_dir();
            $old_filename = wp_basename( $upload['file'] );
            // Assign new, unique file name for the converted image
            $new_filename = str_ireplace( '.' . $file_extension, '.jpg', $old_filename );
            $new_filename = wp_unique_filename( dirname( $upload['file'] ), $new_filename );
            if ( imagejpeg( $image_object, $wp_uploads['path'] . '/' . $new_filename, 90 ) ) {
                // delete original BMP/PNG
                wp_delete_file( $upload['file'] );
                // Add converted JPG info into $upload
                $upload['file'] = $wp_uploads['path'] . '/' . $new_filename;
                $upload['url']  = $wp_uploads['url'] . '/' . $new_filename;
                $upload['type'] = 'image/jpeg';
            }
        }

        return $upload;
    }

	/**
     * Add a submenu
     * 
     * @since   1.5.0
     */
    public function add_submenu(){
        add_submenu_page(
            'wp-mastertoolkit-settings',
            $this->header_title,
            $this->header_title,
            'manage_options',
            'wp-mastertoolkit-settings-image-upload-control',
            array( $this, 'render_submenu' ),
            null
        );
    }

	/**
     * Render the submenu
     * 
     * @since   1.5.0
     */
    public function render_submenu() {
        $submenu_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/insert-head-body-footer-code.asset.php' );
        wp_enqueue_style( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/insert-head-body-footer-code.css', array(), $submenu_assets['version'], 'all' );
        wp_enqueue_script( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/insert-head-body-footer-code.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );

        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
        $this->submenu_content();
        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/footer.php';
    }

	/**
     * Save the submenu option
     * 
     * @since   1.4.0
     */
    public function save_submenu() {
		$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );

		if ( wp_verify_nonce( $nonce, $this->nonce_action ) ) {
			//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $new_settings = $this->sanitize_settings( $_POST[ $this->option_id ] ?? array() );
            $this->save_settings( $new_settings );
            wp_safe_redirect( sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) );
			exit;
		}
    }

	/**
     * sanitize_settings
     * 
     * @since   1.4.0
     * @return array
     */
    public function sanitize_settings( $new_settings ){
        $this->default_settings = $this->get_default_settings();
        $sanitized_settings     = array();

        foreach ( $this->default_settings as $settings_key => $settings_value ) {
			switch ( $settings_key ) {
				case 'max_width':
				case 'max_height':
					$new_value = isset( $new_settings[ $settings_key ] ) ? absint( sanitize_text_field( $new_settings[ $settings_key ] ) ) : 0;
					$sanitized_settings[ $settings_key ] = $new_value > 0 ? $new_value : $settings_value;
				break;
			}
        }

        return $sanitized_settings;
    }

	/**
     * get_settings
     *
     * @since   1.5.0
     * @return void
     */
    private function get_settings(){
        $this->default_settings = $this->get_default_settings();
        return get_option( $this->option_id, $this->default_settings );
    }

	/**
     * Save settings
     * 
     * @since   1.5.0
     */
    private function save_settings( $new_settings ) {
		update_option( $this->option_id, $new_settings );
    }

	/**
     * get_default_settings
     *
     * @since   1.5.0
     * @return array
     */
    private function get_default_settings(){
        if( $this->default_settings !== null ) return $this->default_settings;

        return array(
			'max_width'  => '1920',
			'max_height' => '1920',
        );
    }

	/**
     * Add the submenu content
     * 
     * @since   1.5.0
     * @return void
     */
    private function submenu_content() {
        $this->settings = $this->get_settings();
        $max_width      = $this->settings['max_width'] ?? '1920';
		$max_height     = $this->settings['max_height'] ?? '1920';
        ?>
            <div class="wp-mastertoolkit__section">
                <div class="wp-mastertoolkit__section__desc">
					<?php esc_html_e( "Resize newly uploaded, large images to a smaller dimension and delete originally uploaded files. BMPs and non-transparent PNGs will be converted to JPGs and resized.", 'wpmastertoolkit'); ?>
					</br>
					<?php 
                    echo esc_html( sprintf(
                        /* translators: %1$s: prefix_to_ignore */
                        __( 'To exclude an image from conversion and resizing, append \'%1$s\' suffix to the file name, e.g. my-image%1$s.jpg', 'wpmastertoolkit' ), 
                        $this->prefix_to_ignore 
                    )); 
                    ?>
				</div>
                <div class="wp-mastertoolkit__section__body">
					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Max Width', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
                            <div class="wp-mastertoolkit__input-text">
								<input type="number" name="<?php echo esc_attr( $this->option_id . '[max_width]' ); ?>" value="<?php echo esc_attr( $max_width ); ?>" style="width: 70px;">
								<?php esc_html_e( 'pixels', 'wpmastertoolkit' ); ?>
                            </div>
							<div class="description"><?php esc_html_e( '(Default is 1920 pixels)', 'wpmastertoolkit' ); ?></div>
                        </div>
                    </div>
					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Max Height', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
                            <div class="wp-mastertoolkit__input-text">
								<input type="number" name="<?php echo esc_attr( $this->option_id . '[max_height]' ); ?>" value="<?php echo esc_attr( $max_height ); ?>" style="width: 70px;">
								<?php esc_html_e( 'pixels', 'wpmastertoolkit' ); ?>
                            </div>
							<div class="description"><?php esc_html_e( '(Default is 1920 pixels)', 'wpmastertoolkit' ); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php
    }
}
