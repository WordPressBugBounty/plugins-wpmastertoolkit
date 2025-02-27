<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Multiple User Roles
 * Description: Enable assignment of multiple roles during user account creation and editing. This maybe useful for working with roles not defined in WordPress core, e.g. from e-commerce or LMS plugins.
 * @since 1.10.0
 */
class WPMastertoolkit_Multiple_User_Roles {
	private $option_id;
    private $user_nonce;

	/**
     * Invoke the hooks.
     * 
     * @since   1.10.0
     */
    public function __construct() {
		$this->option_id  = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_multiple_user_roles';
        $this->user_nonce = $this->option_id . '_action';

        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts_styles' ) );
		add_action( 'show_user_profile', array( $this, 'render_user_meta_box' ) );
		add_action( 'edit_user_profile', array( $this, 'render_user_meta_box' ) );
		add_action( 'user_new_form', array( $this, 'render_user_meta_box' ) );
		add_action( 'personal_options_update', array( $this, 'save_user_meta_box' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_user_meta_box' ) );
		add_action( 'user_register', array( $this, 'save_user_meta_box' ) );
    }

	/**
	 * Enqueue scripts and styles
	 * 
	 * @since   1.10.0
	 */
	public function enqueue_scripts_styles( $hook_suffix ) {
		if ( 'user-edit.php' == $hook_suffix || 'user-new.php' == $hook_suffix ) {
            if ( current_user_can( 'promote_users', get_current_user_id() ) ) {
				$user_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/multiple_user_roles_user.asset.php' );
        		wp_enqueue_script( 'WPMastertoolkit_multiple_user_roles_user', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/multiple_user_roles_user.js', $user_assets['dependencies'], $user_assets['version'], true );
            }
        }
	}

	/**
	 * Render user meta box
	 * 
	 * @since   1.10.0
	 */
	public function render_user_meta_box( $user ) {
		$roles      = get_editable_roles();
		$user_roles = array();

		if ( ! empty( $user->roles ) ) {
        	$user_roles = array_intersect( array_values( $user->roles ), array_keys( $roles ) );
        }

		if ( current_user_can( 'promote_users', get_current_user_id() ) ) : ?>
			<div class="wpmastertoolkit-multiple-roles">
				<table class="form-table">
					<tr>
						<th><label><?php esc_html_e( 'Roles', 'wpmastertoolkit' ); ?></label></th>
						<td>
							<?php foreach ( $roles as $role_slug => $role_info ) : ?>
								<label>
									<input type="checkbox" name="wpmastertoolkit_assigned_roles[]" value="<?php echo esc_attr( $role_slug ); ?>" <?php checked( in_array( $role_slug, $user_roles ) ); ?> style="width: 1rem;"/>
									<?php echo esc_html( translate_user_role( $role_info['name'] ) ); ?>
								</label>
								<br/>
							<?php endforeach; ?>
							<?php wp_nonce_field( $this->user_nonce, $this->option_id ); ?>
						</td>
					</tr>
				</table>
			</div>
		<?php endif;
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
		
		if ( ! current_user_can( 'promote_users', get_current_user_id() ) ) {
            return;
        }

		$roles          = get_editable_roles();
        $user           = get_user_by( 'id', (int) $user_id );
        $user_roles     = array_intersect( array_values( $user->roles ), array_keys( $roles ) );
		//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$assigned_roles = wpmastertoolkit_clean( $_POST['wpmastertoolkit_assigned_roles'] ?? '' );

        if ( ! empty( $assigned_roles ) ) {
			$roles_to_remove = array();
            $roles_to_add    = array();
            $assigned_roles  = array_intersect( $assigned_roles, array_keys( $roles ) );

			if ( empty( $assigned_roles ) ) {
                $roles_to_remove = $user_roles;
            } else {
                $roles_to_remove = array_diff( $user_roles, $assigned_roles );
				$roles_to_add    = array_diff( $assigned_roles, $user_roles );

				if ( ! empty( $roles_to_remove ) ) {
                    foreach ( $roles_to_remove as $role_to_remove ) {
                        $user->remove_role( $role_to_remove );
                    }
                }

				if ( ! empty( $roles_to_add ) ) {
                    foreach ( $roles_to_add as $role_to_add ) {
                        $user->add_role( $role_to_add );
                    }
                }
			}
		}
	}
}
