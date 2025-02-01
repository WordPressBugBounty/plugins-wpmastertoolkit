<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Media Cleaner
 * Description: Automatically sanitize uploaded file names by removing special characters, and streamline media management by auto-generating key metadata fields (title, caption, alt text, and description) directly from the cleaned file name.
 * @since 1.14.0
 */
class WPMastertoolkit_Media_Cleaner {

	private $option_id;
    private $header_title;
    private $nonce_action;
	private $settings;
    private $default_settings;

	/**
     * Invoke the hooks.
     * 
     * @since   1.14.0
     */
    public function __construct() {
        $this->option_id    = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_media_cleaner';
        $this->nonce_action = $this->option_id . '_action';
	
		add_action( 'init', array( $this, 'class_init' ) );
        add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
        add_action( 'admin_init', array( $this, 'save_submenu' ) );
		add_filter( 'wp_handle_upload_prefilter', array( $this, 'sanitize_file_name' ) );
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'add_complition_from_file_name' ), 10, 3 );
	}

	/**
     * Initialize the class
	 * 
	 * @since   1.14.0
     */
    public function class_init() {
		$this->header_title	= esc_html__( 'Media Cleaner', 'wpmastertoolkit' );
    }

	/**
	 * Sanitize the file name
	 * 
	 * @since   1.14.0
	 */
	public function sanitize_file_name( $file ) {
		$this->settings = $this->get_settings();

		if ( '1' == $this->settings['remove_special_char'] ) {
			$file_name           = $file['name'];
			$sanitized_file_name = $this->sanitize_name( $file_name );

			$file['name'] = $sanitized_file_name;
			$file['original_name'] = $file_name;
		}

		return $file;
	}

	/**
	 * Add completion from file name
	 * 
	 * @since   1.14.0
	 */
	public function add_complition_from_file_name( $metadata, $attachment_id, $context ) {
		$this->settings = $this->get_settings();

		if ( $context != 'create' ) {
			return $metadata;
		}

		$original_name = isset( $_FILES['async-upload']['original_name'] ) ? sanitize_text_field( $_FILES['async-upload']['original_name'] ) : sanitize_text_field( $_FILES['async-upload']['name'] );
		if ( ! empty( $original_name ) ) {
			$original_name = pathinfo( $original_name, PATHINFO_FILENAME );
			$post_arr      = array();

			if ( '1' == $this->settings['title_completion'] ) {
				$post_arr['post_title'] = $original_name;
			}

			if ( '1' == $this->settings['alt_completion'] ) {
				$post_arr['meta_input']['_wp_attachment_image_alt'] = $original_name;
			}

			if ( '1' == $this->settings['caption_completion'] ) {
				$post_arr['post_excerpt'] = $original_name;
			}

			if ( '1' == $this->settings['description_completion'] ) {
				$post_arr['post_content'] = $original_name;
			}

			if ( ! empty( $post_arr ) ) {
				$post_arr['ID'] = $attachment_id;
				wp_update_post( $post_arr );
			}
		}

		return $metadata;
	}

	/**
     * Add a submenu
     * 
     * @since   1.14.0
     */
    public function add_submenu(){
        add_submenu_page(
            'wp-mastertoolkit-settings',
            $this->header_title,
            $this->header_title,
            'manage_options',
            'wp-mastertoolkit-settings-media-cleaner',
            array( $this, 'render_submenu' ),
            null
        );
    }

	/**
     * Save the submenu option
     * 
     * @since   1.14.0
     */
    public function save_submenu() {
		$nonce = sanitize_text_field( $_POST['_wpnonce'] ?? '' );

		if ( wp_verify_nonce( $nonce, $this->nonce_action ) ) {
            $new_settings = $this->sanitize_settings( $_POST[ $this->option_id ] ?? array() );
            $this->save_settings( $new_settings );
            wp_safe_redirect( sanitize_url( $_SERVER['REQUEST_URI'] ?? '' ) );
			exit;
		}
    }

	/**
     * Render the submenu
     * 
     * @since   1.14.0
     */
    public function render_submenu() {
        $submenu_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/media-cleaner.asset.php' );
        wp_enqueue_style( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/media-cleaner.css', array(), $submenu_assets['version'], 'all' );
        wp_enqueue_script( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/media-cleaner.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );

        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/submenu/header.php';
        $this->submenu_content();
        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/submenu/footer.php';
    }

	/**
     * sanitize_settings
     * 
     * @since   1.14.0
     * @return array
     */
    private function sanitize_settings( $new_settings ){
        $this->default_settings = $this->get_default_settings();
        $sanitized_settings     = array();

        foreach ( $this->default_settings as $settings_key => $settings_value ) {
			switch ( $settings_key ) {
				default:
					$sanitized_settings[ $settings_key ] = sanitize_text_field( $new_settings[ $settings_key ] ?? $settings_value );
				break;
			}
        }

        return $sanitized_settings;
    }

	/**
     * Save settings
     * 
     * @since   1.14.0
     */
    private function save_settings( $new_settings ) {
		update_option( $this->option_id, $new_settings );
    }

	/**
     * get_settings
	 * 
	 * @since   1.14.0
     */
    private function get_settings(){
		if ( $this->settings !== null ) {
			return $this->settings;
		}

        $this->default_settings = $this->get_default_settings();
        return get_option( $this->option_id, $this->default_settings );
    }

	/**
     * get_default_settings
     *
	 * @since   1.14.0
     * @return array
     */
    private function get_default_settings(){
        if ( $this->default_settings !== null ) {
			return $this->default_settings;
		}

        return array(
			'remove_special_char'    => '1',
			'title_completion'       => '1',
			'alt_completion'         => '1',
			'caption_completion'     => '0',
			'description_completion' => '0',
        );
    }

	/**
	 * Sanitize a string
	 * 
	 * @since   1.14.0
	 */
	private function sanitize_name( $name ) {
		$extension      = pathinfo( $name, PATHINFO_EXTENSION );
		$name           = pathinfo( $name, PATHINFO_FILENAME );
		$name           = mb_convert_encoding( $name, "UTF-8" );
		$char_not_clean = array('/\?/','/\’/','/\'/','/À/','/Á/','/Â/','/Ã/','/Ä/','/Å/','/Ç/','/È/','/É/','/Ê/','/Ë/','/Ì/','/Í/','/Î/','/Ï/','/Ò/','/Ó/','/Ô/','/Õ/','/Ö/','/Ù/','/Ú/','/Û/','/Ü/','/Ý/','/à/','/á/','/â/','/ã/','/ä/','/å/','/ç/','/è/','/é/','/ê/','/ë/','/ì/','/í/','/î/','/ï/','/ð/','/ò/','/ó/','/ô/','/õ/','/ö/','/ù/','/ú/','/û/','/ü/','/ý/','/ÿ/', '/©/');
		$clean          = array('','-','-','a','a','a','a','a','a','c','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','y','a','a','a','a','a','a','c','e','e','e','e','i','i','i','i','o','o','o','o','o','o','u','u','u','u','y','y','copy');
		$friendly_name  = preg_replace( $char_not_clean, $clean, $name );
		$friendly_name  = sanitize_title( $friendly_name );
	
		return $friendly_name . '.' . $extension;
	}

	/**
     * Add the submenu content
     * 
     * @since   1.14.0
     * @return void
     */
    private function submenu_content() {
		$this->settings         = $this->get_settings();
		$this->default_settings = $this->get_default_settings();

		$remove_special_char    = $this->settings['remove_special_char'] ?? $this->default_settings['remove_special_char'];
		$title_completion       = $this->settings['title_completion'] ?? $this->default_settings['title_completion'];
		$alt_completion         = $this->settings['alt_completion'] ?? $this->default_settings['alt_completion'];
		$caption_completion     = $this->settings['caption_completion'] ?? $this->default_settings['caption_completion'];
		$description_completion = $this->settings['description_completion'] ?? $this->default_settings['description_completion'];

		?>
			<div class="wp-mastertoolkit__section">
                <div class="wp-mastertoolkit__section__desc">
					<?php esc_html_e( "Automatically sanitize uploaded file names by removing special characters, and streamline media management by auto-generating key metadata fields (title, caption, alt text, and description) directly from the cleaned file name.", 'wpmastertoolkit'); ?>
				</div>
				<div class="wp-mastertoolkit__section__body">

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Remove special characters from file names', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
							<div class="wp-mastertoolkit__checkbox">
								<label class="wp-mastertoolkit__checkbox__label">
									<input type="hidden" name="<?php echo esc_attr( $this->option_id . '[remove_special_char]' ); ?>" value="0">
									<input type="checkbox" name="<?php echo esc_attr( $this->option_id . '[remove_special_char]' ); ?>" value="1" <?php checked( $remove_special_char, '1' ); ?>>
									<span class="mark"></span>
								</label>
							</div>
                        </div>
                    </div>

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Title completion from file name', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
							<div class="wp-mastertoolkit__checkbox">
								<label class="wp-mastertoolkit__checkbox__label">
									<input type="hidden" name="<?php echo esc_attr( $this->option_id . '[title_completion]' ); ?>" value="0">
									<input type="checkbox" name="<?php echo esc_attr( $this->option_id . '[title_completion]' ); ?>" value="1" <?php checked( $title_completion, '1' ); ?>>
									<span class="mark"></span>
								</label>
							</div>
                        </div>
                    </div>

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Alt text completion from file name.', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
							<div class="wp-mastertoolkit__checkbox">
								<label class="wp-mastertoolkit__checkbox__label">
									<input type="hidden" name="<?php echo esc_attr( $this->option_id . '[alt_completion]' ); ?>" value="0">
									<input type="checkbox" name="<?php echo esc_attr( $this->option_id . '[alt_completion]' ); ?>" value="1" <?php checked( $alt_completion, '1' ); ?>>
									<span class="mark"></span>
								</label>
							</div>
                        </div>
                    </div>

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Caption completion from file name.', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
							<div class="wp-mastertoolkit__checkbox">
								<label class="wp-mastertoolkit__checkbox__label">
									<input type="hidden" name="<?php echo esc_attr( $this->option_id . '[caption_completion]' ); ?>" value="0">
									<input type="checkbox" name="<?php echo esc_attr( $this->option_id . '[caption_completion]' ); ?>" value="1" <?php checked( $caption_completion, '1' ); ?>>
									<span class="mark"></span>
								</label>
							</div>
                        </div>
                    </div>

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Description completion from file name.', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
							<div class="wp-mastertoolkit__checkbox">
								<label class="wp-mastertoolkit__checkbox__label">
									<input type="hidden" name="<?php echo esc_attr( $this->option_id . '[description_completion]' ); ?>" value="0">
									<input type="checkbox" name="<?php echo esc_attr( $this->option_id . '[description_completion]' ); ?>" value="1" <?php checked( $description_completion, '1' ); ?>>
									<span class="mark"></span>
								</label>
							</div>
                        </div>
                    </div>

				</div>
			</div>
		<?php
	}
}
