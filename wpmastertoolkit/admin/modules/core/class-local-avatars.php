<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Local avatars
 * Description: Replaces GRAVATAR management with media management.
 * @since 1.11.0
 */
class WPMastertoolkit_Local_Avatars {
	private $option_id;
    private $user_nonce;
	private $user_avatar_meta_key;

	/**
     * Invoke the hooks
     * 
	 * @since 1.11.0
     */
    public function __construct() {
		$this->option_id            = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_local_avatars';
        $this->user_nonce           = $this->option_id . '_action';
		$this->user_avatar_meta_key = $this->option_id . '_user_avatar';

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts_styles' ) );
		add_action( 'show_user_profile', array( $this, 'render_user_meta_box' ) );
		add_action( 'edit_user_profile', array( $this, 'render_user_meta_box' ) );
		add_action( 'personal_options_update', array( $this, 'save_user_meta_box' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_user_meta_box' ) );
		add_filter( 'get_avatar_data', array( $this, 'change_avatar_data' ), 10, 2 );
		add_action( 'after_setup_theme',  array( $this, 'add_custom_header_support_on_ajax' ) );
    }

	/**
	 * Enqueue scripts and styles
	 * 
	 * @since 1.11.0
	 */
	function enqueue_scripts_styles( $hook_suffix ) {
		if ( 'profile.php' == $hook_suffix || 'user-edit.php' == $hook_suffix ) {
			wp_enqueue_media();
			$metabox_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/local-avatars-metabox.asset.php' );
			wp_enqueue_style( 'WPMastertoolkit_local_avatars_metabox', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/local-avatars-metabox.css', array(), $metabox_assets['version'], 'all' );
			wp_enqueue_script( 'WPMastertoolkit_local_avatars_metabox', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/local-avatars-metabox.js', $metabox_assets['dependencies'], $metabox_assets['version'], true );
		}
	}

	/**
	 * Render user meta box
	 * 
	 * @since 1.10.0
	 */
	public function render_user_meta_box( $user ) {
		$avatar_id                 = (int) get_user_meta( $user->ID, $this->user_avatar_meta_key, true );
		$avatar_url                = wp_get_attachment_image_url( $avatar_id );
		$has_avatar                = (bool) $avatar_url;
		$classes_for_upload_button = 'upload-button button-add-media button-add-site-icon';
		$classes_for_update_button = 'button';
		$classes_for_wrapper       = '';

		if ( $has_avatar ) {
			$classes_for_wrapper         .= ' has-site-icon';
			$classes_for_button           = $classes_for_update_button;
			$classes_for_button_on_change = $classes_for_upload_button;
		} else {
			$classes_for_wrapper         .= ' hidden';
			$classes_for_button           = $classes_for_upload_button;
			$classes_for_button_on_change = $classes_for_update_button;
		}

		?>
		<div class="wpmastertoolkit-local-avatars">
			<table class="form-table">
				<tr>
					<th><label><?php esc_html_e( 'Profile Picture', 'wpmastertoolkit' ); ?></label></th>
					<td>
						<div class="site-icon-section">
							<div id="wpmastertoolkit-local-avatar-icon-preview-container" >
								<img id="wpmastertoolkit-local-avatar-icon-preview" class="app-icon-preview" src="<?php echo esc_url( $avatar_url ); ?>">
							</div>
							<input type="hidden" name="wpmastertoolkit_local_avatar_icon" id="wpmastertoolkit_local_avatar_icon_hidden_field" value="<?php esc_attr( $avatar_id ); ?>" />
							<div class="action-buttons">
								<button type="button"
									id="wpmastertoolkit-local-avatar-choose-from-library-button"
									type="button"
									class="<?php echo esc_attr( $classes_for_button ); ?>"
									data-alt-classes="<?php echo esc_attr( $classes_for_button_on_change ); ?>"
									data-size="100"
									data-choose-text="<?php esc_attr_e( 'Choose Image', 'wpmastertoolkit' ); ?>"
									data-update-text="<?php esc_attr_e( 'Change Image', 'wpmastertoolkit' ); ?>"
									data-update="<?php esc_attr_e( 'Set as Image', 'wpmastertoolkit' ); ?>"
									data-state="<?php echo esc_attr( $has_avatar ); ?>"
								>
									<?php if ( $has_avatar ) : ?>
										<?php _e( 'Change Image', 'wpmastertoolkit' ); ?>
									<?php else : ?>
										<?php _e( 'Choose an Image', 'wpmastertoolkit' ); ?>
									<?php endif; ?>
								</button>
								<button
									id="wpmastertoolkit-js-remove-local-avatar-icon"
									type="button"
									<?php echo $has_avatar ? 'class="button button-secondary reset"' : 'class="button button-secondary reset hidden"'; ?>
								>
									<?php _e( 'Remove Image', 'wpmastertoolkit' ); ?>
								</button>
							</div>
						</div>
						<?php wp_nonce_field( $this->user_nonce, $this->option_id ); ?>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * Save user meta box
	 * 
	 * @since   1.10.0
	 */
	public function save_user_meta_box( $user_id ) {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[$this->option_id] ?? '' ) ), $this->user_nonce ) ) {
			return;
		}

		$avatar_id = sanitize_text_field( $_POST['wpmastertoolkit_local_avatar_icon'] ?? '' );
		if ( $avatar_id ) {
			update_user_meta( $user_id, $this->user_avatar_meta_key, $avatar_id );
		}
	}
	
	/**
	 * Change avatar data
	 * 
	 * @since 1.11.0
	 */
	public function change_avatar_data( $args, $id_or_email ) {
		global $current_screen;

		if ( isset( $current_screen ) ) {
			if ( 'options-discussion' == $current_screen->id ) {
				return $args;
			}
		}

		if ( is_object( $id_or_email ) && isset( $id_or_email->comment_ID ) ) {
			$id_or_email = get_comment( $id_or_email );
		}

		if ( is_numeric( $id_or_email ) ) {
			$user = get_user_by( 'id', absint( $id_or_email ) );
		} elseif ( is_string( $id_or_email ) ) {
			$user = get_user_by( 'email', $id_or_email );
		} elseif ( $id_or_email instanceof WP_User ) {
			$user = $id_or_email;
		} elseif ( $id_or_email instanceof WP_Post ) {
			$user = get_user_by( 'id', (int) $id_or_email->post_author );
		} elseif ( $id_or_email instanceof WP_Comment ) {
			if ( ! is_avatar_comment_type( get_comment_type( $id_or_email ) ) ) {
				return $args;
			}
	
			if ( ! empty( $id_or_email->user_id ) ) {
				$user = get_user_by( 'id', (int) $id_or_email->user_id );
			}
			if ( ( ! $user || is_wp_error( $user ) ) && ! empty( $id_or_email->comment_author_email ) ) {
				$user = get_user_by( 'email', $id_or_email->comment_author_email );
			}
		}

		if ( ! is_a( $user, 'WP_User' ) ) {
			return $args;
		}

		$custom_avatar_id  = get_user_meta( $user->ID, $this->user_avatar_meta_key, true );
		$has_custom_avatar = ! empty( $custom_avatar_id );

		if ( $has_custom_avatar ) {
			$custom_avatar_url = wp_get_attachment_url( $custom_avatar_id );
			if ( $custom_avatar_url ) {
				$args['url'] = $custom_avatar_url;
			}
		}

		return $args;
	}

	/**
	 * Add custom header support on AJAX if it's not already added
	 *
	 * @return void
	 */
	public function add_custom_header_support_on_ajax() {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			$theme_supports = get_theme_support( 'custom-header' );
			if ( ! $theme_supports ) {
				add_theme_support( 'custom-header', array(
					'width'         => 1200,
					'height'        => 600,
					'flex-height'   => true,
					'flex-width'    => true,
					'header-text'   => false,
				));
			}
		}
	}
}
