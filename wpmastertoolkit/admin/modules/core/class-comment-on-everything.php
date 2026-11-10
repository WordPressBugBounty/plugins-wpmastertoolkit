<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- Preserve the registered module filename.
/**
 * Module Name: Comment on Everything
 * Description: Private contextual collaboration on supported WordPress pages.
 *
 * @package WPMastertoolkit
 */

defined( 'ABSPATH' ) || exit;

require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/helpers/core/comment-on-everything/class-contexts.php';
require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/helpers/core/comment-on-everything/class-discussions.php';

// phpcs:disable PEAR.NamingConventions.ValidClassName.Invalid -- Settings links index get_class() using this existing registry key.
/** WordPress adapter for contextual collaboration. */
class WPMastertoolkit_Comment_on_Everything {

	const SLUG = 'wp-mastertoolkit-settings-comment-on-everything';

	/**
	 * Title consumed by the shared submenu header.
	 *
	 * @var string
	 */
	private $header_title;

	/**
	 * Shared header save nonce.
	 *
	 * @var string
	 */
	private $nonce_action = 'wpmastertoolkit_collaboration_settings';

	/**
	 * Render explicit forms instead of the templates' default form.
	 *
	 * @var bool
	 */
	private $disable_form = true;

	/**
	 * Hide saving when all settings are locked.
	 *
	 * @var bool
	 */
	private $disable_save_form = false;

	/**
	 * Context resolver.
	 *
	 * @var WPMastertoolkit_Collaboration_Contexts
	 */
	private $contexts;
	/**
	 * Conversation implementation.
	 *
	 * @var WPMastertoolkit_Collaboration_Discussions
	 */
	private $discussions;
	/**
	 * Current document context.
	 *
	 * @var array|WP_Error|null
	 */
	private $document_context;

	/** Register runtime hooks only when the module manager enables this module. */
	public function __construct() {
		global $wpdb;
		$this->contexts    = new WPMastertoolkit_Collaboration_Contexts();
		$this->discussions = new WPMastertoolkit_Collaboration_Discussions( $wpdb, $this->contexts );

		add_action( 'init', array( $this->discussions, 'install' ) );
		add_action( 'init', array( $this, 'class_init' ) );
		add_action( 'template_redirect', array( $this, 'protect_document' ), 0 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_bar_menu', array( $this, 'toolbar' ), 100 );
		add_action( 'wp_footer', array( $this, 'launcher' ) );
		add_action( 'admin_footer', array( $this, 'launcher' ) );
		add_action( 'admin_menu', array( $this, 'menu' ), 999 );
		add_action( 'wp_ajax_wpmastertoolkit_collaboration', array( $this, 'request' ) );
		add_action( 'wp_ajax_wpmastertoolkit_collaboration_capture_upload', array( $this, 'upload_capture' ) );
		add_action( 'wp_ajax_wpmastertoolkit_collaboration_capture_read', array( $this, 'read_capture' ) );
		add_action( 'wp_ajax_wpmastertoolkit_collaboration_search_users', array( $this, 'ajax_search_users' ) );
		add_action( 'wp_ajax_wpmastertoolkit_collaboration_search_roles', array( $this, 'ajax_search_roles' ) );
		add_action( 'admin_post_wpmastertoolkit_collaboration_settings', array( $this, 'save' ) );
		add_action( 'admin_post_wpmastertoolkit_collaboration_delete_threads', array( $this, 'delete_threads' ) );
	}

	/** Register retained-data hooks even while the module is disabled. */
	public static function register_lifecycle() {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'erasers' ) );
		add_action( 'deleted_user', array( __CLASS__, 'erase_account' ) );
		add_action( 'wpmu_delete_user', array( __CLASS__, 'erase_network_account' ) );
		add_action( 'deleted_post', array( __CLASS__, 'delete_record' ) );
	}

	/**
	 * Translate settings text after WordPress initializes text domains.
	 */
	public function class_init() {
		$this->header_title = __( 'Comment on Everything', 'wpmastertoolkit' );
	}

	/** Construct retained-data handling independently of module runtime state. */
	private static function retained() {
		global $wpdb;
		return new WPMastertoolkit_Collaboration_Discussions( $wpdb, new WPMastertoolkit_Collaboration_Contexts() );
	}

	/** Install module tables without removing retained discussions. */
	public static function activate() {
		self::retained()->install();
	}

	/**
	 * Register the personal-data exporter.
	 *
	 * @param array $exporters Registered exporters.
	 * @return array Updated exporters.
	 */
	public static function exporters( $exporters ) {
		$exporters['wpmastertoolkit-collaboration'] = array(
			'exporter_friendly_name' => __( 'Internal collaboration', 'wpmastertoolkit' ),
			'callback'               => array( __CLASS__, 'export' ),
		);
		return $exporters;
	}

	/**
	 * Register the personal-data eraser.
	 *
	 * @param array $erasers Registered erasers.
	 * @return array Updated erasers.
	 */
	public static function erasers( $erasers ) {
		$erasers['wpmastertoolkit-collaboration'] = array(
			'eraser_friendly_name' => __( 'Internal collaboration', 'wpmastertoolkit' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Export a page of contributions.
	 *
	 * @param string $email Request identity.
	 * @param int    $page Export page.
	 * @return array Export result.
	 */
	public static function export( $email, $page = 1 ) {
		return self::retained()->export( $email, $page );
	}

	/**
	 * Erase contributions for a confirmed privacy request.
	 *
	 * @param string $email Request identity.
	 * @return array Erasure result.
	 */
	public static function erase( $email ) {
		$user    = get_user_by( 'email', $email );
		$success = ! $user || self::retained()->erase_user( $user->ID );
		return array(
			'items_removed'  => $success && (bool) $user,
			'items_retained' => ! $success,
			'messages'       => $success ? array() : array( __( 'Collaboration erasure failed. Please retry.', 'wpmastertoolkit' ) ),
			'done'           => $success,
		);
	}

	/**
	 * Erase identifying contribution data when an account is deleted.
	 *
	 * @param int $id Deleted user.
	 */
	public static function erase_account( $id ) {
		self::retained()->erase_user( $id );
	}

	/**
	 * Erase a network account's contributions on every site.
	 *
	 * @param int $id Deleted user.
	 */
	public static function erase_network_account( $id ) {
		$offset = 0;
		do {
			$sites = get_sites(
				array(
					'fields'  => 'ids',
					'number'  => 100,
					'offset'  => $offset,
					'orderby' => 'id',
					'order'   => 'ASC',
				)
			);
			foreach ( $sites as $site_id ) {
				switch_to_blog( $site_id );
				try {
					self::retained()->erase_user( $id );
				} finally {
					restore_current_blog();
				}
			}
			$count   = count( $sites );
			$offset += $count;
		} while ( 100 === $count );
	}

	/**
	 * Remove associated discussions after permanent content deletion.
	 *
	 * @param int $id Deleted post.
	 */
	public static function delete_record( $id ) {
		self::retained()->delete_record( $id );
	}

	/** Never embed private configuration into a cacheable document. */
	public function protect_document() {
		if ( WPMastertoolkit_Collaboration_Discussions::allowed() ) {
			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Cache plugins require this exact constant name.
				define( 'DONOTCACHEPAGE', true );
			}
			nocache_headers();
		}
	}

	/** Check collaborator access and supported document eligibility. */
	private function supported() {
		if ( ! WPMastertoolkit_Collaboration_Discussions::allowed() || wp_doing_ajax() || is_feed() || is_embed() || is_trackback()
			|| ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || is_network_admin() || is_user_admin() ) {
			return false;
		}
		if ( is_admin() ) {
			$screen = get_current_screen();
			if ( $screen && $screen->is_block_editor() ) {
				return false;
			}
		}
		$this->document_context = $this->contexts->current();
		return ! is_wp_error( $this->document_context );
	}

	/** Enqueue only the small launcher entry on supported documents. */
	public function enqueue() {
		if ( ! $this->supported() ) {
			return;
		}
		$path   = 'admin/assets/build/core/comment-on-everything';
		$assets = include WPMASTERTOOLKIT_PLUGIN_PATH . $path . '.asset.php';
		wp_enqueue_script( 'wpmastertoolkit-collaboration', WPMASTERTOOLKIT_PLUGIN_URL . $path . '.js', $assets['dependencies'], $assets['version'], true );
		wp_enqueue_style( 'wpmastertoolkit-collaboration', WPMASTERTOOLKIT_PLUGIN_URL . $path . '.css', array(), $assets['version'] );
		wp_set_script_translations( 'wpmastertoolkit-collaboration', 'wpmastertoolkit', WPMASTERTOOLKIT_PLUGIN_PATH . 'languages' );
	}

	/**
	 * Register the primary launcher on supported documents.
	 *
	 * @param WP_Admin_Bar $admin_bar Toolbar instance.
	 */
	public function toolbar( $admin_bar ) {
		if ( ! is_admin_bar_showing() || ! $this->supported() ) {
			return;
		}
		$admin_bar->add_node(
			array(
				'id'    => 'wpmastertoolkit-comment-on-everything',
				'title' => '<button type="button" class="wpmastertoolkit-coe-toolbar-button" aria-expanded="false" aria-controls="wpmastertoolkit-coe-panel"><span data-coe-icon aria-hidden="true"></span><span class="wpmastertoolkit-coe-toolbar-label">' . esc_html__( 'Comment on Everything', 'wpmastertoolkit' ) . '</span></button>',
			)
		);
	}

	/** Render minimal context metadata without conversation data or a nonce. */
	public function launcher() {
		if ( ! $this->document_context || is_wp_error( $this->document_context ) ) {
			return;
		}
		?>
		<div id="wpmastertoolkit-collaboration" data-endpoint="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-context="<?php echo esc_attr( wp_json_encode( $this->contexts->descriptor( $this->document_context ) ) ); ?>">
			<button type="button" class="wpmastertoolkit-coe-launcher" aria-expanded="false" aria-controls="wpmastertoolkit-coe-panel" aria-label="<?php esc_attr_e( 'Internal discussions', 'wpmastertoolkit' ); ?>" title="<?php esc_attr_e( 'Internal discussions', 'wpmastertoolkit' ); ?>" hidden><span data-coe-icon aria-hidden="true">&#9998;</span></button>
		</div>
		<?php
	}

	/** Bootstrap is read-only; every other request requires a user-bound nonce. */
	public function request() {
		nocache_headers();
		if ( ! WPMastertoolkit_Collaboration_Discussions::allowed() ) {
			wp_send_json_error( array( 'message' => __( 'Collaboration access is required.', 'wpmastertoolkit' ) ), 403 );
		}
		$raw   = isset( $_POST['payload'] ) && is_string( $_POST['payload'] ) ? wp_unslash( $_POST['payload'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON is validated by each operation below; bootstrap is read-only.
		$input = json_decode( $raw, true );
		if ( ! is_array( $input ) || strlen( $raw ) > 20000 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid collaboration request.', 'wpmastertoolkit' ) ), 400 );
		}
		$operation = is_string( $input['operation'] ?? null ) ? $input['operation'] : '';
		if ( 'bootstrap' === $operation ) {
			$context = $this->contexts->resolve( $input['context'] ?? null );
			if ( is_wp_error( $context ) ) {
				$this->respond( $context );
			}
			wp_send_json_success(
				array(
					'nonce'               => wp_create_nonce( 'wpmastertoolkit_collaboration' ),
					'actor'               => get_current_user_id(),
					'admin'               => current_user_can( 'manage_options' ),
					'context_key'         => $context['key'],
					'screenshots_enabled' => WPMastertoolkit_Collaboration_Discussions::screenshots_enabled(),
				)
			);
		}
		if ( ! is_string( $input['nonce'] ?? null ) || ! wp_verify_nonce( $input['nonce'], 'wpmastertoolkit_collaboration' ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session expired. Reload this page; retain any unsent text first.', 'wpmastertoolkit' ) ), 403 );
		}
		$result = $this->discussions->perform( $operation, $input );
		if ( 'create' === $operation && ! is_wp_error( $result ) ) {
			$result['screenshots_enabled'] = WPMastertoolkit_Collaboration_Discussions::screenshots_enabled();
			if ( ! empty( $input['capture_pending'] ) && $result['screenshots_enabled'] ) {
				$result['capture_upload_nonce']   = wp_create_nonce( 'wpmastertoolkit_capture_' . $result['id'] );
				$result['capture_upload_expires'] = time() + WPMastertoolkit_Collaboration_Discussions::CAPTURE_WINDOW;
			}
		}
		$this->respond( $result );
	}

	/** Verify shared permissions before processing image requests. */
	private function authorize_capture() {
		nocache_headers();
		if ( ! WPMastertoolkit_Collaboration_Discussions::allowed() ) {
			wp_send_json_error(
				array(
					'code'    => 'forbidden',
					'message' => __( 'Collaboration access is required.', 'wpmastertoolkit' ),
				),
				403
			);
		}
		check_ajax_referer( 'wpmastertoolkit_collaboration', 'nonce' );
	}

	/** Accept one bounded image independently of the already-saved discussion. */
	public function upload_capture() {
		$this->authorize_capture();
		$id = isset( $_POST['thread'] ) && is_string( $_POST['thread'] ) ? absint( $_POST['thread'] ) : 0;
		check_ajax_referer( 'wpmastertoolkit_capture_' . $id, 'upload_nonce' );
		if ( ! WPMastertoolkit_Collaboration_Discussions::screenshots_enabled() ) {
			wp_send_json_error(
				array(
					'code'    => wpmastertoolkit_is_pro() ? 'screenshots_disabled' : 'pro_required',
					'message' => __( 'New screenshots require PRO and the screenshot setting enabled.', 'wpmastertoolkit' ),
				),
				403
			);
		}
		$file = $_FILES['capture'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validate provenance, dimensions and decoded bytes below.
		if ( 1 !== count( $_FILES ) || ! is_array( $file ) || ! isset( $file['error'], $file['tmp_name'], $file['size'] ) || UPLOAD_ERR_OK !== $file['error'] || ! is_string( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) || $file['size'] > WPMastertoolkit_Collaboration_Discussions::CAPTURE_BYTES ) {
			wp_send_json_error(
				array(
					'code'    => 'invalid_capture',
					'message' => __( 'The screenshot upload is invalid or too large.', 'wpmastertoolkit' ),
				),
				400
			);
		}
		$bytes = file_get_contents( $file['tmp_name'], false, null, 0, WPMastertoolkit_Collaboration_Discussions::CAPTURE_BYTES + 1 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Bounded read of a verified PHP upload; no persistent filesystem writes.
		$image = WPMastertoolkit_Collaboration_Discussions::normalize_capture( $bytes );
		$this->respond( is_wp_error( $image ) ? $image : $this->discussions->attach_capture( $id, $image ) );
	}

	/** Deliver private bytes only after current thread/context authorization. */
	public function read_capture() {
		$this->authorize_capture();
		$id    = isset( $_POST['thread'] ) && is_string( $_POST['thread'] ) ? absint( $_POST['thread'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- authorize_capture verifies the request nonce.
		$image = $this->discussions->read_capture( $id );
		if ( is_wp_error( $image ) ) {
			$this->respond( $image );
		}
		header( 'Content-Type: image/png' );
		header( 'Content-Disposition: inline; filename="discussion-snapshot.png"' );
		header( 'Content-Length: ' . strlen( $image['data'] ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0' );
		echo $image['data']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Validated PNG binary, not HTML.
		exit;
	}

	/**
	 * Send an authenticated operation result.
	 *
	 * @param array|WP_Error $result Conversation result.
	 */
	private function respond( $result ) {
		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			wp_send_json_error(
				array(
					'code'    => $result->get_error_code(),
					'message' => $result->get_error_message(),
				),
				is_array( $data ) ? ( $data['status'] ?? 400 ) : 400
			);
		}
		wp_send_json_success( $result );
	}

	/** Register administrator settings. */
	public function menu() {
		WPMastertoolkit_Settings::add_submenu_page( 'wp-mastertoolkit-settings', __( 'Comment on Everything', 'wpmastertoolkit' ), __( 'Comment on Everything', 'wpmastertoolkit' ), 'manage_options', self::SLUG, array( $this, 'settings' ) );
	}

	/** Render collaboration grants and the discussion management table. */
	public function settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$pro                     = wpmastertoolkit_is_pro();
		$this->disable_save_form = ! $pro;
		$upgrade_url             = wpmastertoolkit_get_tracked_url( 'https://wpmastertoolkit.com/en/products-4/wpmastertoolkit-pro/', 'upgrade-pro', 'comment-on-everything-settings' );
		$assets                  = include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/comment-on-everything-settings.asset.php';
		wp_enqueue_style( 'wpmastertoolkit-collaboration-settings', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/comment-on-everything-settings.css', array(), $assets['version'] );
		wp_enqueue_script( 'wpmastertoolkit-collaboration-settings', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/comment-on-everything-settings.js', $assets['dependencies'], $assets['version'], true );
		wp_set_script_translations( 'wpmastertoolkit-collaboration-settings', 'wpmastertoolkit', WPMASTERTOOLKIT_PLUGIN_PATH . 'languages' );
		wp_localize_script(
			'wpmastertoolkit-collaboration-settings',
			'WPMastertoolkitCollaborationSettings',
			array(
				'ajax_url'           => admin_url( 'admin-ajax.php' ),
				'nonce'              => wp_create_nonce( 'wpmastertoolkit_collaboration_search' ),
				'conversation_nonce' => wp_create_nonce( 'wpmastertoolkit_collaboration' ),
				'conversation_i18n'  => array(
					/* translators: %d: Discussion ID. */
					'title'         => __( 'Discussion #%d', 'wpmastertoolkit' ),
					'loading'       => __( 'Loading discussion...', 'wpmastertoolkit' ),
					'sending'       => __( 'Sending reply...', 'wpmastertoolkit' ),
					'sent'          => __( 'Reply sent.', 'wpmastertoolkit' ),
					'empty'         => __( 'No messages in this discussion.', 'wpmastertoolkit' ),
					'removed'       => __( 'Removed contribution', 'wpmastertoolkit' ),
					'load_error'    => __( 'The discussion could not be loaded. Please try refreshing.', 'wpmastertoolkit' ),
					'send_error'    => __( 'Delivery could not be confirmed. Your draft is retained. Refresh the discussion before sending again to avoid a duplicate reply.', 'wpmastertoolkit' ),
					'session'       => __( 'Your session or access changed. Retain your draft, then reload this page.', 'wpmastertoolkit' ),
					'unavailable'   => __( 'This discussion is no longer available. Your draft is retained.', 'wpmastertoolkit' ),
					'body_limit'    => __( 'Enter a reply of at most 12,000 bytes.', 'wpmastertoolkit' ),
					'request_limit' => __( 'This reply exceeds the request size limit. Shorten it before sending.', 'wpmastertoolkit' ),
				),
				'i18n'               => array(
					'users_placeholder' => __( 'Search users...', 'wpmastertoolkit' ),
					'roles_placeholder' => __( 'Search roles...', 'wpmastertoolkit' ),
					'no_users'          => __( 'No users found.', 'wpmastertoolkit' ),
					'no_roles'          => __( 'No roles found.', 'wpmastertoolkit' ),
					'min_chars'         => __( 'Please enter 2 or more characters.', 'wpmastertoolkit' ),
					'loading'           => __( 'Searching...', 'wpmastertoolkit' ),
					'ajax_error'        => __( 'The results could not be loaded. Please try again.', 'wpmastertoolkit' ),
				),
			)
		);

		$settings = get_option( WPMastertoolkit_Collaboration_Discussions::OPTION, array() );
		$notice   = get_transient( 'wpmastertoolkit_coe_deletion_' . get_current_user_id() );
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only table navigation; normalized again by the listing service.
		$filters = WPMastertoolkit_Collaboration_Discussions::admin_filters(
			array(
				'coe_kind'   => isset( $_GET['coe_kind'] ) && is_string( $_GET['coe_kind'] ) ? sanitize_key( wp_unslash( $_GET['coe_kind'] ) ) : '',
				'coe_author' => isset( $_GET['coe_author'] ) && is_string( $_GET['coe_author'] ) ? sanitize_text_field( wp_unslash( $_GET['coe_author'] ) ) : '',
				'coe_status' => isset( $_GET['coe_status'] ) && is_string( $_GET['coe_status'] ) ? sanitize_key( wp_unslash( $_GET['coe_status'] ) ) : 'all',
				'coe_page'   => isset( $_GET['coe_page'] ) && is_string( $_GET['coe_page'] ) ? sanitize_text_field( wp_unslash( $_GET['coe_page'] ) ) : '1',
			)
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$listing = $this->discussions->admin_listing( $filters );
		if ( ! is_wp_error( $listing ) ) {
			$filters = $listing['filters'];
		}
		$contexts = array(
			''          => __( 'All contexts', 'wpmastertoolkit' ),
			'content'   => __( 'Frontend content', 'wpmastertoolkit' ),
			'post-edit' => __( 'Administrative record', 'wpmastertoolkit' ),
			'post-list' => __( 'Content list', 'wpmastertoolkit' ),
			'dashboard' => __( 'Dashboard', 'wpmastertoolkit' ),
			'users'     => __( 'Users', 'wpmastertoolkit' ),
			'media'     => __( 'Media Library', 'wpmastertoolkit' ),
			'comments'  => __( 'Comments', 'wpmastertoolkit' ),
			'home'      => __( 'Home', 'wpmastertoolkit' ),
		);
		delete_transient( 'wpmastertoolkit_coe_deletion_' . get_current_user_id() );
		?>

		<form id="wpmastertoolkit-coe-settings-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="wpmastertoolkit_collaboration_settings">
			<?php include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php'; ?>
			<h1 class="screen-reader-text"><?php esc_html_e( 'Comment on Everything', 'wpmastertoolkit' ); ?></h1>
			<?php if ( is_array( $notice ) ) : ?>
				<div class="notice <?php echo ! empty( $notice['failed'] ) || ! empty( $notice['conflicts'] ) ? 'notice-warning' : 'notice-success'; ?>" role="status">
					<p>
						<?php esc_html_e( 'Threads deleted:', 'wpmastertoolkit' ); ?>
						<?php echo esc_html( (string) absint( $notice['removed'] ?? 0 ) ); ?>
						<?php if ( ! empty( $notice['conflicts'] ) ) : ?>
							<?php esc_html_e( 'Changed discussions skipped:', 'wpmastertoolkit' ); ?>
							<?php echo esc_html( (string) absint( $notice['conflicts'] ) ); ?>
						<?php endif; ?>
						<?php if ( ! empty( $notice['failed'] ) ) : ?>
							<?php esc_html_e( 'Unavailable or failed discussions:', 'wpmastertoolkit' ); ?>
							<?php echo esc_html( (string) absint( $notice['failed'] ) ); ?>
						<?php endif; ?>
						<?php if ( ! empty( $notice['failed'] ) || ! empty( $notice['conflicts'] ) ) : ?>
							<?php esc_html_e( 'Review the refreshed list before trying again.', 'wpmastertoolkit' ); ?>
						<?php endif; ?>
					</p>
				</div>
			<?php endif; ?>
			<section class="wp-mastertoolkit__section" aria-labelledby="coe-collaborators-title">
				<div class="wp-mastertoolkit__section__desc">
					<h2 id="coe-collaborators-title"><?php esc_html_e( 'Collaborators', 'wpmastertoolkit' ); ?></h2>
					<?php if ( ! $pro ) : ?>
						<span class="wp-mastertoolkit__section__body__item__title__tag pro"><?php esc_html_e( 'PRO', 'wpmastertoolkit' ); ?></span>
						<p id="coe-collaborators-locked"><?php esc_html_e( 'Only administrators can collaborate in Free. Saved collaborator selections are retained for PRO.', 'wpmastertoolkit' ); ?></p>
					<?php endif; ?>
				</div>
				<div class="wp-mastertoolkit__section__body">
					<div class="wp-mastertoolkit__section__body__item">
						<div class="wp-mastertoolkit__section__body__item__title"><label for="wpmastertoolkit-coe-roles"><?php esc_html_e( 'Roles', 'wpmastertoolkit' ); ?></label></div>
						<div class="wp-mastertoolkit__section__body__item__content">
							<div class="wp-mastertoolkit__select2">
								<select id="wpmastertoolkit-coe-roles" name="roles[]" multiple <?php disabled( ! $pro ); ?>
									<?php if ( ! $pro ) : ?>
										aria-describedby="coe-collaborators-locked"
									<?php endif; ?>
								>
									<?php foreach ( wp_roles()->roles as $role => $definition ) : ?>
										<option value="<?php echo esc_attr( $role ); ?>" <?php selected( in_array( $role, (array) ( $settings['roles'] ?? array() ), true ) ); ?>><?php echo esc_html( translate_user_role( $definition['name'] ) ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>
					</div>
					<div class="wp-mastertoolkit__section__body__item">
						<div class="wp-mastertoolkit__section__body__item__title"><label for="wpmastertoolkit-coe-users"><?php esc_html_e( 'Additional users', 'wpmastertoolkit' ); ?></label></div>
						<div class="wp-mastertoolkit__section__body__item__content">
							<div class="wp-mastertoolkit__select2">
								<select id="wpmastertoolkit-coe-users" name="users[]" multiple <?php disabled( ! $pro ); ?>
									<?php if ( ! $pro ) : ?>
										aria-describedby="coe-collaborators-locked"
									<?php endif; ?>
								>
									<?php foreach ( (array) ( $settings['users'] ?? array() ) as $user_id ) : ?>
										<?php $user = is_scalar( $user_id ) && absint( $user_id ) ? get_userdata( absint( $user_id ) ) : false; ?>
										<?php if ( $user ) : ?>
											<option value="<?php echo esc_attr( $user->ID ); ?>" selected><?php echo esc_html( $user->user_login ); ?></option>
										<?php endif; ?>
									<?php endforeach; ?>
								</select>
							</div>
						</div>
					</div>
				</div>
			</section>
			<section class="wp-mastertoolkit__section" aria-labelledby="coe-screenshots-title">
				<div class="wp-mastertoolkit__section__desc">
					<h2 id="coe-screenshots-title"><?php esc_html_e( 'Screenshots', 'wpmastertoolkit' ); ?></h2>
				</div>
				<div class="wp-mastertoolkit__section__body">
					<div class="wp-mastertoolkit__section__body__item">
						<div class="wp-mastertoolkit__section__body__item__title">
							<label for="coe-screenshots-enabled"><?php esc_html_e( 'Enable screenshots', 'wpmastertoolkit' ); ?></label>
							<?php if ( ! $pro ) : ?>
								<span class="wp-mastertoolkit__section__body__item__title__tag pro"><?php esc_html_e( 'PRO', 'wpmastertoolkit' ); ?></span>
							<?php endif; ?>
						</div>
						<div class="wp-mastertoolkit__section__body__item__content">
							<label class="wp-mastertoolkit__toggle" for="coe-screenshots-enabled">
								<input id="coe-screenshots-enabled" type="checkbox" name="screenshots_enabled" value="1" aria-describedby="coe-screenshots-description" <?php checked( WPMastertoolkit_Collaboration_Discussions::screenshots_enabled() ); ?> <?php disabled( ! $pro ); ?>>
								<span class="wp-mastertoolkit__toggle__slider round" aria-hidden="true"></span>
							</label>
							<p id="coe-screenshots-description">
								<?php esc_html_e( 'Attach a screenshot to new discussions. Existing screenshots remain available when disabled.', 'wpmastertoolkit' ); ?>
							</p>
						</div>
					</div>
				</div>
			</section>
			<?php $this->discussion_table( $listing, $contexts, $filters ); ?>
			<?php if ( $pro ) : ?>
				<?php include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/footer.php'; ?>
			<?php else : ?>
				</div>
			<?php endif; ?>
		</form>

		<form id="wpmastertoolkit-coe-filter-form" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>">
		</form>
		<form id="wpmastertoolkit-coe-delete-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="wpmastertoolkit_collaboration_delete_threads">
			<?php wp_nonce_field( 'wpmastertoolkit_collaboration_delete_threads', '_wpnonce', false ); ?>
			<?php foreach ( $filters as $key => $value ) : ?>
				<input type="hidden" name="return_filters[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>">
			<?php endforeach; ?>
		</form>
		<dialog id="wpmastertoolkit-coe-conversation" aria-labelledby="coe-conversation-title">
			<div class="wpmastertoolkit-coe-conversation-layout">
				<header class="wpmastertoolkit-coe-conversation-header">
					<h2 id="coe-conversation-title" tabindex="-1"><?php esc_html_e( 'Discussion', 'wpmastertoolkit' ); ?></h2>
					<div class="wpmastertoolkit-coe-conversation-tools">
						<button type="button" class="button" data-coe-refresh title="<?php esc_attr_e( 'Refresh discussion', 'wpmastertoolkit' ); ?>" aria-label="<?php esc_attr_e( 'Refresh discussion', 'wpmastertoolkit' ); ?>"><span class="dashicons dashicons-update" aria-hidden="true"></span></button>
						<button type="button" class="button" data-coe-close title="<?php esc_attr_e( 'Close discussion', 'wpmastertoolkit' ); ?>" aria-label="<?php esc_attr_e( 'Close discussion', 'wpmastertoolkit' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
					</div>
				</header>
				<p data-coe-notice role="status" aria-live="polite" aria-atomic="true"></p>
				<div class="wpmastertoolkit-coe-conversation-messages" data-coe-messages tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Discussion messages', 'wpmastertoolkit' ); ?>"></div>
				<form id="wpmastertoolkit-coe-reply-form">
					<label for="coe-conversation-reply"><?php esc_html_e( 'Reply', 'wpmastertoolkit' ); ?></label>
					<textarea id="coe-conversation-reply" name="body" rows="4" required disabled></textarea>
					<button type="submit" class="button button-primary" data-coe-send disabled><?php esc_html_e( 'Send reply', 'wpmastertoolkit' ); ?></button>
				</form>
			</div>
		</dialog>
		<?php
	}

	/**
	 * Render the independently submitted discussion controls.
	 *
	 * @param array|WP_Error $listing Authorized table data.
	 * @param array          $contexts Context labels.
	 * @param array          $filters Normalized filters.
	 */
	private function discussion_table( $listing, $contexts, $filters ) {
		$url         = admin_url( 'admin.php?page=' . self::SLUG );
		$pro         = wpmastertoolkit_is_pro();
		$statuses    = array(
			'all'      => __( 'All statuses', 'wpmastertoolkit' ),
			'open'     => __( 'Open', 'wpmastertoolkit' ),
			'resolved' => __( 'Resolved', 'wpmastertoolkit' ),
		);
		?>
		<section class="wp-mastertoolkit__section" aria-labelledby="coe-discussions-title">
			<div class="wp-mastertoolkit__section__desc"><h2 id="coe-discussions-title"><?php esc_html_e( 'Discussions', 'wpmastertoolkit' ); ?></h2></div>
			<div class="wp-mastertoolkit__section__body" id="wpmastertoolkit-coe-discussions">
				<?php if ( is_wp_error( $listing ) ) : ?>
					<div class="notice notice-error" role="alert"><p><?php echo esc_html( $listing->get_error_message() ); ?></p></div>
				<?php else : ?>
					<?php if ( ! $pro ) : ?>
						<p id="coe-filters-locked"><span class="wp-mastertoolkit__section__body__item__title__tag pro"><?php esc_html_e( 'PRO', 'wpmastertoolkit' ); ?></span> <?php esc_html_e( 'Discussion filters require PRO.', 'wpmastertoolkit' ); ?></p>
					<?php endif; ?>
					<fieldset class="wpmastertoolkit-coe-filters" <?php disabled( ! $pro ); ?>
						<?php if ( ! $pro ) : ?>
							aria-describedby="coe-filters-locked"
						<?php endif; ?>
					>
						<legend class="screen-reader-text"><?php esc_html_e( 'Discussion filters', 'wpmastertoolkit' ); ?></legend>
						<div>
							<label for="coe-filter-context"><?php esc_html_e( 'Context', 'wpmastertoolkit' ); ?></label>
							<select id="coe-filter-context" name="coe_kind" form="wpmastertoolkit-coe-filter-form">
								<?php foreach ( $contexts as $kind => $label ) : ?>
									<option value="<?php echo esc_attr( $kind ); ?>" <?php selected( $filters['coe_kind'], $kind ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div>
							<label for="coe-filter-author"><?php esc_html_e( 'Author', 'wpmastertoolkit' ); ?></label>
							<select id="coe-filter-author" name="coe_author" form="wpmastertoolkit-coe-filter-form">
								<option value=""><?php esc_html_e( 'All authors', 'wpmastertoolkit' ); ?></option>
								<?php foreach ( $pro ? $listing['authors'] : array() as $author ) : ?>
									<option value="<?php echo esc_attr( $author ); ?>" <?php selected( $filters['coe_author'], (string) $author ); ?>><?php echo esc_html( $this->discussion_author( $author ) ); ?></option>
								<?php endforeach; ?>
								<?php if ( '' !== $filters['coe_author'] && ! in_array( (int) $filters['coe_author'], $listing['authors'], true ) ) : ?>
									<option value="<?php echo esc_attr( $filters['coe_author'] ); ?>" selected><?php esc_html_e( 'Unavailable author', 'wpmastertoolkit' ); ?></option>
								<?php endif; ?>
							</select>
						</div>
						<div>
							<label for="coe-filter-status"><?php esc_html_e( 'Status', 'wpmastertoolkit' ); ?></label>
							<select id="coe-filter-status" name="coe_status" form="wpmastertoolkit-coe-filter-form">
								<?php foreach ( $statuses as $status => $label ) : ?>
									<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $filters['coe_status'], $status ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<button type="submit" class="button" form="wpmastertoolkit-coe-filter-form"><?php esc_html_e( 'Apply', 'wpmastertoolkit' ); ?></button>
						<?php if ( $pro ) : ?>
							<a class="button" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Reset filters', 'wpmastertoolkit' ); ?></a>
						<?php else : ?>
							<button type="button" class="button" disabled><?php esc_html_e( 'Reset filters', 'wpmastertoolkit' ); ?></button>
						<?php endif; ?>
					</fieldset>
					<div class="wpmastertoolkit-coe-table" tabindex="0" role="region" aria-labelledby="coe-discussions-title">
						<table class="widefat striped">
							<caption class="screen-reader-text"><?php esc_html_e( 'Discussions, most recently updated first', 'wpmastertoolkit' ); ?></caption>
							<thead><tr>
								<td class="check-column"><label data-coe-select-all-label hidden><input type="checkbox" data-coe-select-all form="wpmastertoolkit-coe-delete-form"><span class="screen-reader-text"><?php esc_html_e( 'Select all discussions on this page', 'wpmastertoolkit' ); ?></span></label></td>
								<th scope="col"><?php esc_html_e( 'Updated (UTC)', 'wpmastertoolkit' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Discussion', 'wpmastertoolkit' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Context', 'wpmastertoolkit' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Author', 'wpmastertoolkit' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Status', 'wpmastertoolkit' ); ?></th>
								<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'wpmastertoolkit' ); ?></span></th>
							</tr></thead>
							<tbody>
								<?php foreach ( $listing['threads'] as $thread ) : ?>
									<?php $selection = $thread['id'] . ':' . $thread['version']; ?>
									<tr>
										<th scope="row" class="check-column"><label><input type="checkbox" name="threads[]" value="<?php echo esc_attr( $selection ); ?>" data-coe-select form="wpmastertoolkit-coe-delete-form"><span class="screen-reader-text"><?php /* translators: %d: Discussion ID. */ echo esc_html( sprintf( __( 'Select discussion #%d', 'wpmastertoolkit' ), $thread['id'] ) ); ?></span></label></th>
										<td><time datetime="<?php echo esc_attr( str_replace( ' ', 'T', $thread['updated_at'] ) . 'Z' ); ?>"><?php echo esc_html( $thread['updated_at'] ); ?></time></td>
										<td class="wpmastertoolkit-coe-preview">
											<strong data-coe-title-fallback><?php /* translators: %d: Discussion ID. */ echo esc_html( sprintf( __( 'Discussion #%d', 'wpmastertoolkit' ), $thread['id'] ) ); ?></strong>
											<button type="button" class="wpmastertoolkit-coe-open" data-coe-open="<?php echo esc_attr( $thread['id'] ); ?>" aria-haspopup="dialog" aria-controls="wpmastertoolkit-coe-conversation" aria-label="<?php /* translators: %d: Discussion ID. */ echo esc_attr( sprintf( __( 'Open discussion #%d', 'wpmastertoolkit' ), $thread['id'] ) ); ?>" hidden><?php /* translators: %d: Discussion ID. */ echo esc_html( sprintf( __( 'Discussion #%d', 'wpmastertoolkit' ), $thread['id'] ) ); ?></button>
											<p><?php echo esc_html( $thread['preview'] ); ?></p>
										</td>
										<td><a href="<?php echo esc_url( $thread['url'] ); ?>"><?php echo esc_html( $thread['title'] ); ?></a><br><span><?php echo esc_html( $contexts[ $thread['context']['kind'] ] ?? '' ); ?></span></td>
										<td><?php echo esc_html( $this->discussion_author( $thread['creator'] ) ); ?></td>
										<td><?php echo $thread['resolved'] ? esc_html__( 'Resolved', 'wpmastertoolkit' ) : esc_html__( 'Open', 'wpmastertoolkit' ); ?></td>
										<td><button type="submit" name="delete_thread" value="<?php echo esc_attr( $selection ); ?>" class="button wpmastertoolkit-coe-delete" form="wpmastertoolkit-coe-delete-form" title="<?php esc_attr_e( 'Delete discussion', 'wpmastertoolkit' ); ?>" data-confirm-message="<?php /* translators: %d: Discussion ID. */ echo esc_attr( sprintf( __( 'Permanently delete discussion #%d and all its messages? This cannot be undone.', 'wpmastertoolkit' ), $thread['id'] ) ); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span><span class="screen-reader-text"><?php /* translators: %d: Discussion ID. */ echo esc_html( sprintf( __( 'Delete discussion #%d', 'wpmastertoolkit' ), $thread['id'] ) ); ?></span></button></td>
									</tr>
								<?php endforeach; ?>
								<?php if ( ! $listing['threads'] ) : ?>
									<tr><td colspan="7" role="status"><?php echo $filters['coe_kind'] || '' !== $filters['coe_author'] || 'all' !== $filters['coe_status'] ? esc_html__( 'No discussions match the selected filters.', 'wpmastertoolkit' ) : esc_html__( 'No discussions yet.', 'wpmastertoolkit' ); ?></td></tr>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
					<div class="wpmastertoolkit-coe-table-footer">
						<?php if ( $listing['threads'] ) : ?>
							<div class="wpmastertoolkit-coe-bulk">
								<label><input type="checkbox" name="confirm" value="1" required form="wpmastertoolkit-coe-delete-form" autocomplete="off"> <?php esc_html_e( 'Permanently delete chosen discussions and all their messages', 'wpmastertoolkit' ); ?></label>
								<button type="submit" class="button" name="delete_selected" value="1" data-coe-delete-selected form="wpmastertoolkit-coe-delete-form"><?php esc_html_e( 'Delete selected', 'wpmastertoolkit' ); ?></button>
							</div>
						<?php endif; ?>
						<nav class="wpmastertoolkit-coe-pagination" aria-label="<?php esc_attr_e( 'Discussion pages', 'wpmastertoolkit' ); ?>">
							<?php if ( $filters['coe_page'] > 1 ) : ?>
								<a class="button" href="<?php echo esc_url( add_query_arg( array_merge( $filters, array( 'coe_page' => $filters['coe_page'] - 1 ) ), $url ) ); ?>" aria-label="<?php esc_attr_e( 'Previous page', 'wpmastertoolkit' ); ?>"><span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span></a>
							<?php endif; ?>
							<span><?php /* translators: 1: Current page, 2: Total pages. */ echo esc_html( sprintf( __( 'Page %1$d of %2$d', 'wpmastertoolkit' ), $filters['coe_page'], $listing['pages'] ) ); ?></span>
							<?php if ( $filters['coe_page'] < $listing['pages'] ) : ?>
								<a class="button" href="<?php echo esc_url( add_query_arg( array_merge( $filters, array( 'coe_page' => $filters['coe_page'] + 1 ) ), $url ) ); ?>" aria-label="<?php esc_attr_e( 'Next page', 'wpmastertoolkit' ); ?>"><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></a>
							<?php endif; ?>
						</nav>
					</div>
				<?php endif; ?>
			</div>
		</section>
		<?php
	}

	/**
	 * Label a creator without restoring erased identity.
	 *
	 * @param int $id Creator identity.
	 * @return string Display label.
	 */
	private function discussion_author( $id ) {
		$user = $id ? get_userdata( $id ) : false;
		/* translators: 1: User display name, 2: User ID. */
		return $user ? sprintf( __( '%1$s (#%2$d)', 'wpmastertoolkit' ), $user->display_name ? $user->display_name : $user->user_login, $id ) : __( 'Removed user', 'wpmastertoolkit' );
	}

	/** Delete only explicitly submitted discussions, with per-thread outcomes. */
	public function delete_threads() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Administrator access is required.', 'wpmastertoolkit' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'wpmastertoolkit_collaboration_delete_threads' );
		$confirm = isset( $_POST['confirm'] ) && is_string( $_POST['confirm'] ) ? sanitize_text_field( wp_unslash( $_POST['confirm'] ) ) : '';
		$single  = isset( $_POST['delete_thread'] ) && is_string( $_POST['delete_thread'] ) ? sanitize_text_field( wp_unslash( $_POST['delete_thread'] ) ) : '';
		$bulk    = isset( $_POST['delete_selected'] ) && is_string( $_POST['delete_selected'] ) ? sanitize_text_field( wp_unslash( $_POST['delete_selected'] ) ) : '';
		$items   = $single ? array( $single ) : ( '1' === $bulk && isset( $_POST['threads'] ) && is_array( $_POST['threads'] ) ? map_deep( wp_unslash( $_POST['threads'] ), 'sanitize_text_field' ) : array() );
		if ( '1' !== $confirm || ! $items || count( $items ) > 25 || ( isset( $_POST['delete_thread'] ) && ! $single ) ) {
			wp_die( esc_html__( 'Select discussions and confirm permanent deletion.', 'wpmastertoolkit' ), '', array( 'response' => 400 ) );
		}
		$threads = array();
		foreach ( $items as $item ) {
			$matches = array();
			if ( ! is_string( $item ) || ! preg_match( '/^([1-9][0-9]{0,17}):([1-9][0-9]{0,17})$/D', $item, $matches ) || ( isset( $threads[ $matches[1] ] ) && $threads[ $matches[1] ] !== (int) $matches[2] ) ) {
				wp_die( esc_html__( 'Invalid discussion selection.', 'wpmastertoolkit' ), '', array( 'response' => 400 ) );
			}
			$threads[ $matches[1] ] = (int) $matches[2];
		}
		$notice = array(
			'removed'   => 0,
			'conflicts' => 0,
			'failed'    => 0,
		);
		foreach ( $threads as $id => $version ) {
			$result = $this->discussions->perform(
				'delete_thread',
				array(
					'thread'  => (int) $id,
					'version' => $version,
				)
			);
			if ( is_wp_error( $result ) ) {
				++$notice[ 'conflict' === $result->get_error_code() ? 'conflicts' : 'failed' ];
			} else {
				++$notice['removed'];
			}
		}
		$filters = isset( $_POST['return_filters'] ) && is_array( $_POST['return_filters'] ) ? map_deep( wp_unslash( $_POST['return_filters'] ), 'sanitize_text_field' ) : array();
		set_transient( 'wpmastertoolkit_coe_deletion_' . get_current_user_id(), $notice, MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( WPMastertoolkit_Collaboration_Discussions::admin_filters( $filters ), admin_url( 'admin.php?page=' . self::SLUG ) ) );
		exit;
	}

	/** Find users for the administrator's collaborator selector. */
	public function ajax_search_users() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}
		check_ajax_referer( 'wpmastertoolkit_collaboration_search', 'nonce' );
		if ( ! wpmastertoolkit_is_pro() ) {
			wp_send_json_error( array( 'code' => 'pro_required' ), 403 );
		}
		$search = isset( $_GET['search'] ) && is_string( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';
		if ( strlen( $search ) < 2 ) {
			wp_send_json_success( array() );
		}
		$users   = get_users(
			array(
				'search'         => '*' . substr( $search, 0, 100 ) . '*',
				'search_columns' => array( 'user_login', 'display_name' ),
				'number'         => 20,
				'orderby'        => 'login',
				'order'          => 'ASC',
				'fields'         => array( 'ID', 'user_login' ),
			)
		);
		$results = array();
		foreach ( $users as $user ) {
			$results[] = array(
				'id'   => (int) $user->ID,
				'text' => $user->user_login,
			);
		}
		wp_send_json_success( $results );
	}

	/** Find active-site roles for the administrator's collaborator selector. */
	public function ajax_search_roles() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}
		check_ajax_referer( 'wpmastertoolkit_collaboration_search', 'nonce' );
		if ( ! wpmastertoolkit_is_pro() ) {
			wp_send_json_error( array( 'code' => 'pro_required' ), 403 );
		}
		$search  = isset( $_GET['search'] ) && is_string( $_GET['search'] ) ? substr( sanitize_text_field( wp_unslash( $_GET['search'] ) ), 0, 100 ) : '';
		$results = array();
		foreach ( wp_roles()->roles as $role => $definition ) {
			$label = translate_user_role( $definition['name'] );
			if ( '' !== $search && false === stripos( $role, $search ) && false === stripos( $label, $search ) ) {
				continue;
			}
			$results[] = array(
				'id'   => $role,
				'text' => $label,
			);
		}
		wp_send_json_success( $results );
	}

	/** Handle a nonce-protected collaborator settings submission. */
	public function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Administrator access is required.', 'wpmastertoolkit' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'wpmastertoolkit_collaboration_settings' );
		if ( ! wpmastertoolkit_is_pro() ) {
			wp_die( esc_html__( 'These settings require PRO.', 'wpmastertoolkit' ), '', array( 'response' => 403 ) );
		}
		if ( isset( $_POST['cleanup'] ) ) {
			wp_die( esc_html__( 'Use the discussion table to select discussions for deletion.', 'wpmastertoolkit' ), '', array( 'response' => 400 ) );
		}
		$roles = isset( $_POST['roles'] ) && is_array( $_POST['roles'] ) ? array_filter( map_deep( wp_unslash( $_POST['roles'] ), 'sanitize_key' ), 'is_string' ) : array();
		$users = array();
		if ( isset( $_POST['users'] ) && is_array( $_POST['users'] ) ) {
			$users = array_filter( map_deep( wp_unslash( $_POST['users'] ), 'sanitize_text_field' ), 'is_string' );
		} elseif ( isset( $_POST['users'] ) && is_string( $_POST['users'] ) ) {
			$users = preg_split( '/[\s,]+/', sanitize_text_field( wp_unslash( $_POST['users'] ) ), -1, PREG_SPLIT_NO_EMPTY );
		}
		$settings = get_option( WPMastertoolkit_Collaboration_Discussions::OPTION, array() );
		$settings = is_array( $settings ) ? $settings : array();
		update_option(
			WPMastertoolkit_Collaboration_Discussions::OPTION,
			array_merge(
				$settings,
				array(
					'roles'               => array_values( array_intersect( array_keys( wp_roles()->roles ), $roles ) ),
					'users'               => array_values( array_filter( array_unique( array_map( 'absint', $users ) ), 'get_userdata' ) ),
					'screenshots_enabled' => isset( $_POST['screenshots_enabled'] ) && is_string( $_POST['screenshots_enabled'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['screenshots_enabled'] ) ),
				)
			),
			false
		);
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG ) );
		exit;
	}
}
