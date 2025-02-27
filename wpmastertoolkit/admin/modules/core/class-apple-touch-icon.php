<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Apple Touch Icon
 * Description: Manage app icon (Apple Touch Icon) individually.
 * @since 1.11.0
 */
class WPMastertoolkit_Apple_Touch_Icon {

	/**
     * Invoke the hooks
     * 
	 * @since 1.11.0
     */
    public function __construct() {
		add_action( 'admin_init', array( $this, 'add_custom_fields' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts_styles' ) );
		add_filter( 'site_icon_meta_tags', array( $this, 'change_default_apple_touch_icon' ) );
    }

	/**
	 * Add custom fields
	 * 
	 * @since 1.11.0
	 */
	public function add_custom_fields() {
		if ( current_user_can( 'upload_files' ) ){
			add_settings_field(
				'wpmastertoolkit_apple_touch_icon',
				__( 'Apple Touch Icon', 'wpmastertoolkit' ),
				array( $this, 'render_preview_image' ),
				'general',
			);
		
			register_setting( 'general', 'wpmastertoolkit_apple_touch_icon', 'intval' );
		}
	}

	/**
	 * Render preview image
	 * 
	 * @since 1.11.0
	 */
	public function render_preview_image() {
		$icon_id                   = (int) get_option( 'wpmastertoolkit_apple_touch_icon' );
		$icon_url                  = wp_get_attachment_image_url( $icon_id, array( 512, 512 ) );
		$has_icon                  = (bool) $icon_url;
		$classes_for_upload_button = 'upload-button button-add-media button-add-site-icon';
		$classes_for_update_button = 'button';
		$classes_for_wrapper       = '';
		$app_icon_alt_value        = '';

		if ( $has_icon ) {
			$classes_for_wrapper         .= ' has-site-icon';
			$classes_for_button           = $classes_for_update_button;
			$classes_for_button_on_change = $classes_for_upload_button;
		} else {
			$classes_for_wrapper         .= ' hidden';
			$classes_for_button           = $classes_for_upload_button;
			$classes_for_button_on_change = $classes_for_update_button;
		}

		if ( $icon_id ) {
			$img_alt            = get_post_meta( $icon_id, '_wp_attachment_image_alt', true );
			$filename           = wp_basename( $icon_url );
			$app_icon_alt_value = sprintf(
				/* translators: %s: The selected image filename. */
				__( 'App icon preview: The current image has no alternative text. The file name is: %s' ),// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
				$filename
			);

			if ( $img_alt ) {
				$app_icon_alt_value = sprintf(
					/* translators: %s: The selected image alt text. */
					__( 'App icon preview: Current image: %s' ),// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
					$img_alt
				);
			}
		}

		?>
			<div class="site-icon-section">
				<div id="wpmastertoolkit-apple-touch-icon-preview" class="site-icon-preview wp-clearfix settings-page-preview <?php echo esc_attr( $classes_for_wrapper ); ?>">
					<?php //phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage ?>
					<img id="wpmastertoolkit-app-icon-preview" class="app-icon-preview" src="<?php echo esc_url( $icon_url ); ?>" alt="<?php echo esc_attr( $app_icon_alt_value ); ?>" style="display: none;">
				</div>
				<input type="hidden" name="wpmastertoolkit_apple_touch_icon" id="wpmastertoolkit_apple_touch_icon_hidden_field" value="<?php form_option( 'wpmastertoolkit_apple_touch_icon' ); ?>" />
				<div class="action-buttons">
					<button type="button"
						id="wpmastertoolkit-choose-from-library-button"
						type="button"
						class="<?php echo esc_attr( $classes_for_button ); ?>"
						data-alt-classes="<?php echo esc_attr( $classes_for_button_on_change ); ?>"
						data-size="512"
						data-choose-text="<?php esc_attr_e( 'Choose Icon', 'wpmastertoolkit' ); ?>"
						data-update-text="<?php esc_attr_e( 'Change Icon', 'wpmastertoolkit' ); ?>"
						data-update="<?php esc_attr_e( 'Set as Icon', 'wpmastertoolkit' ); ?>"
						data-state="<?php echo esc_attr( $has_icon ); ?>"
					>
						<?php if ( $has_icon ) : ?>
							<?php esc_html_e( 'Change Icon', 'wpmastertoolkit' ); ?>
						<?php else : ?>
							<?php esc_html_e( 'Choose an Icon', 'wpmastertoolkit' ); ?>
						<?php endif; ?>
					</button>
					<button
						id="wpmastertoolkit-js-remove-site-icon"
						class="button button-secondary reset remove-site-icon"
						type="button"
						<?php echo $has_icon ? 'class="button button-secondary reset"' : 'class="button button-secondary reset hidden"'; ?>
					>
						<?php esc_html_e( 'Remove Icon', 'wpmastertoolkit' ); ?>
					</button>
				</div>
			</div>
		<?php
	}

	/**
	 * Enqueue scripts and styles
	 * 
	 * @since 1.11.0
	 */
	function enqueue_scripts_styles( $hook_suffix ) {
		if ( 'options-general.php' == $hook_suffix ) {
			$options_general_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/apple-touch-icon-options-general.asset.php' );
			wp_enqueue_style( 'wpmastertoolkit-apple-touch-icon', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/apple-touch-icon-options-general.css', array(), $options_general_assets['version'], 'all' );
			wp_enqueue_script( 'wpmastertoolkit-apple-touch-icon', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/apple-touch-icon-options-general.js', $options_general_assets['dependencies'], $options_general_assets['version'], true );
			
			wp_localize_script( 'wpmastertoolkit-apple-touch-icon', 'wpmastertoolkit_apple_touch_icon', array(
				'default_icon' => includes_url('images/w-logo-blue.png'),
			) );
		}
	}

	/**
	 * Change default Apple Touch Icon
	 * 
	 * @since 1.11.0
	 */
	function change_default_apple_touch_icon( $meta_tags ) {
		$icon_id  = (int) get_option( 'wpmastertoolkit_apple_touch_icon' );
		$icon_url = wp_get_attachment_image_url( $icon_id, array( 512, 512 ) );
		$has_icon = (bool) $icon_url;

		if ( $has_icon ) {
			foreach ( $meta_tags as $key => $meta_tag ) {
				if ( strpos( $meta_tag, 'apple-touch-icon' ) !== false ) {
					$meta_tags[$key] = sprintf( '<link rel="apple-touch-icon" href="%s" />', esc_url( $icon_url ) );
				}
			}
		}

		return $meta_tags;
	}
}
