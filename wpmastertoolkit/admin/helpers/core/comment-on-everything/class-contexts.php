<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- Match existing module helper filenames.
/**
 * Canonical collaboration contexts and their access rules.
 *
 * @package WPMastertoolkit
 */

defined( 'ABSPATH' ) || exit;

/** Context identity is never accepted independently of context access. */
class WPMastertoolkit_Collaboration_Contexts {

	/**
	 * Resolve and authorize an approved descriptor for the current user.
	 *
	 * @param mixed $descriptor Untrusted descriptor.
	 * @return array|WP_Error Authorized context or denial.
	 */
	public function resolve( $descriptor ) {
		if ( ! is_array( $descriptor ) || ! is_string( $descriptor['kind'] ?? null )
			|| ( isset( $descriptor['id'] ) && ( ! is_scalar( $descriptor['id'] ) || ! ctype_digit( (string) $descriptor['id'] ) ) ) ) {
			return new WP_Error( 'invalid_context', __( 'Unsupported context.', 'wpmastertoolkit' ) );
		}
		$kind = $descriptor['kind'];
		$id   = absint( $descriptor['id'] ?? 0 );
		if ( in_array( $kind, array( 'content', 'post-edit' ), true ) ) {
			$post = get_post( $id );
			if ( ! $post || in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) ) {
				return $this->denied();
			}
			$allowed = 'post-edit' === $kind ? current_user_can( 'edit_post', $id ) : current_user_can( 'read_post', $id );
			if ( ! $allowed || ( 'content' === $kind && post_password_required( $post ) ) ) {
				return $this->denied();
			}
			return array(
				'key'         => $kind . ':' . $id,
				'kind'        => $kind,
				'id'          => $id,
				'record_type' => 'post',
				'record_id'   => $id,
				'title'       => get_the_title( $post ),
				'url'         => 'post-edit' === $kind ? admin_url( 'post.php?post=' . $id . '&action=edit' ) : get_permalink( $post ),
			);
		}
		if ( 'post-list' === $kind ) {
			$type   = is_string( $descriptor['type'] ?? null ) ? sanitize_key( $descriptor['type'] ) : '';
			$object = get_post_type_object( $type );
			if ( ! $object || ! $object->show_ui || ! current_user_can( $object->cap->edit_posts ) ) {
				return $this->denied();
			}
			return array(
				'key'   => 'post-list:' . $type,
				'kind'  => $kind,
				'type'  => $type,
				'title' => $object->labels->name,
				'url'   => admin_url( 'edit.php?post_type=' . $type ),
			);
		}
		$screens = array(
			'dashboard' => array( 'read', 'index.php', __( 'Dashboard', 'wpmastertoolkit' ) ),
			'users'     => array( 'list_users', 'users.php', __( 'Users', 'wpmastertoolkit' ) ),
			'media'     => array( 'upload_files', 'upload.php', __( 'Media Library', 'wpmastertoolkit' ) ),
			'comments'  => array( 'moderate_comments', 'edit-comments.php', __( 'Comments', 'wpmastertoolkit' ) ),
		);
		if ( isset( $screens[ $kind ] ) && current_user_can( $screens[ $kind ][0] ) ) {
			return array(
				'key'   => $kind,
				'kind'  => $kind,
				'title' => $screens[ $kind ][2],
				'url'   => admin_url( $screens[ $kind ][1] ),
			);
		}
		if ( 'home' === $kind ) {
			return array(
				'key'   => 'home',
				'kind'  => $kind,
				'title' => get_bloginfo( 'name' ),
				'url'   => home_url( '/' ),
			);
		}
		return $this->denied();
	}

	/** Determine the current document without retaining raw request URLs. */
	public function current() {
		if ( is_admin() ) {
			$screen = get_current_screen();
			if ( ! $screen || is_network_admin() || is_user_admin() ) {
				return $this->denied();
			}
			if ( 'post' === $screen->base ) {
				$id = isset( $_GET['post'] ) && is_scalar( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only context identity.
				return $this->resolve(
					array(
						'kind' => 'post-edit',
						'id'   => $id,
					)
				);
			}
			if ( 'edit' === $screen->base ) {
				return $this->resolve(
					array(
						'kind' => 'post-list',
						'type' => $screen->post_type,
					)
				);
			}
			$kinds = array(
				'dashboard'     => 'dashboard',
				'users'         => 'users',
				'upload'        => 'media',
				'edit-comments' => 'comments',
			);
			return $this->resolve( array( 'kind' => $kinds[ $screen->base ] ?? '' ) );
		}
		if ( is_singular() || ( is_front_page() && get_queried_object_id() ) ) {
			return $this->resolve(
				array(
					'kind' => 'content',
					'id'   => get_queried_object_id(),
				)
			);
		}
		if ( is_home() && get_option( 'page_for_posts' ) ) {
			return $this->resolve(
				array(
					'kind' => 'content',
					'id'   => (int) get_option( 'page_for_posts' ),
				)
			);
		}
		return is_front_page() || is_home() ? $this->resolve( array( 'kind' => 'home' ) ) : $this->denied();
	}

	/**
	 * Strip presentation and unapproved data before persistence or transport.
	 *
	 * @param array $context Canonical context.
	 * @return array Minimal descriptor.
	 */
	public function descriptor( $context ) {
		return array_intersect_key( $context, array_flip( array( 'kind', 'id', 'type' ) ) );
	}

	/** Return the same denial for unknown and inaccessible contexts. */
	private function denied() {
		return new WP_Error( 'context_unavailable', __( 'This context is unavailable.', 'wpmastertoolkit' ), array( 'status' => 403 ) );
	}
}
