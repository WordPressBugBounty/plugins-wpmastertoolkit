<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: File Manager
 * Description: Browser and manage your files efficiently and easily.
 * @since 1.9.0
 */
class WPMastertoolkit_File_Manager {

	private $option_id;
    private $header_title;
    private $user_nonce;
	private $disable_form;
	private $GLOBAL_IS_HTTPS;
	private $GLOBAL_SELF_URL;
	private $ROOT_PATH;
	private $HTTP_HOST;
	private $ROOT_URL;
	private $P;
	private $MAX_UPLOAD_SIZE;
	private $UPLOAD_CHUNK_SIZE;
	private $FM_SESSION_ID;
	private $FM_IS_WIN;
	private $FM_PATH;
	private $FM_ROOT_PATH;
	private $FM_SELF_URL;
	private $FM_UPLOAD_EXTENSION;
	private $FM_FILE_EXTENSION;
	private $FM_ROOT_URL;
	private $PARENT_PATH;
	private $PATH;
	private $TEMPLATE;
	private $TOKEN;

	/**
     * Invoke the hooks
     * 
     * @since    1.9.0
     */
    public function __construct() {
		$this->option_id    = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_file_manager';
        $this->user_nonce   = $this->option_id . '_action';
		$this->disable_form = true;

        add_action( 'init', array( $this, 'class_init' ) );
        add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
		add_action( 'admin_notices', array( $this, 'show_admin_notice' ) );
    }

	/**
     * Initialize the class
     * 
     * @since    1.9.0
     * @return   void
     */
    public function class_init() {

		$this->header_title = esc_html__( 'File Manager', 'wpmastertoolkit' );

		if ( ! isset( $_GET['page'] ) || $_GET['page'] != 'wp-mastertoolkit-settings-file-manager' ) {
			return;
		}

		if ( ! headers_sent() && ! session_id() ) {
			session_start();
		}

		$this->handle_actions();
    }

	/**
     * Add a submenu
     * 
     * @since   1.9.0
     */
    public function add_submenu(){
        add_submenu_page(
            'wp-mastertoolkit-settings',
            $this->header_title,
            $this->header_title,
            'manage_options',
            'wp-mastertoolkit-settings-file-manager',
            array( $this, 'render_submenu' ),
            null
        );
    }

	/**
     * Render the submenu
     * 
     * @since   1.9.0
     */
    public function render_submenu() {

		$dependencies = array();
		wp_enqueue_script( 'WPMastertoolkit_submenu_jquery', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/lib/jquery-3.6.1.min.js', array(), '3.6.1', true );
		wp_enqueue_script( 'WPMastertoolkit_submenu_jquery_datatables', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/lib/jquery.dataTables.min.js', array(), '1.13.1', true );
		$dependencies[] = 'WPMastertoolkit_submenu_jquery';
		$dependencies[] = 'WPMastertoolkit_submenu_jquery_datatables';

		if ( 'upload' == $this->TEMPLATE ) {
			wp_enqueue_style( 'WPMastertoolkit_submenu_dropzone', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/lib/dropzone-5.9.3.min.css', array(), '5.9.3', 'all' );
			wp_enqueue_script( 'WPMastertoolkit_submenu_dropzone', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/lib/dropzone-5.9.3.min.js', array(), '5.9.3', true );
			$dependencies[] = 'WPMastertoolkit_submenu_dropzone';
		}

		if ( 'view' == $this->TEMPLATE ) {
			wp_enqueue_style( 'WPMastertoolkit_submenu_highlight', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/lib/highlight-11.6.0.css', array(), '11.6.0', 'all' );
			wp_enqueue_script( 'WPMastertoolkit_submenu_highlight', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/lib/highlight-11.6.0.js', array(), '11.6.0', true );
			$dependencies[] = 'WPMastertoolkit_submenu_highlight';
		}

		if ( 'edit' == $this->TEMPLATE ) {
			wp_enqueue_script( 'WPMastertoolkit_submenu_ace', 'https://cdnjs.cloudflare.com/ajax/libs/ace/1.13.1/ace.js', array(), '1.13.1', true );
			$dependencies[] = 'WPMastertoolkit_submenu_ace';

			$ext = pathinfo( sanitize_text_field( $_GET["edit"] ?? '' ), PATHINFO_EXTENSION );
			$ext =  $ext == "js" ? "javascript" :  $ext;
			wp_localize_script( 'WPMastertoolkit_submenu_ace', 'WPMastertoolkit_submenu_ace', array(
				'ext'  => $ext,
				'i10n' => array(
					'bright'     => __( 'Bright', 'wpmastertoolkit' ),
					'dark'       => __( 'Dark', 'wpmastertoolkit' ),
					'errTimeout' => __( 'Error: Server Timeout', 'wpmastertoolkit' ),
					'errTry'     => __( 'Error: try again', 'wpmastertoolkit' ),
					'saved'      => __( 'Saved Successfully', 'wpmastertoolkit' ),
				),
			) );
		}

        wp_enqueue_style( 'WPMastertoolkit_submenu_fontawesome', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/lib/font-awesome.min.css', array(), '4.7.0', 'all' );
        wp_enqueue_style( 'WPMastertoolkit_submenu_bootstrap', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/lib/bootstrap.min.css', array(), '5.3.0', 'all' );
        wp_enqueue_script( 'WPMastertoolkit_submenu_bootstrap', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/lib/bootstrap.bundle.min.js', array(), '5.3.0', true );
		$dependencies[] = 'WPMastertoolkit_submenu_bootstrap';

        $submenu_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/file-manager.asset.php' );
        wp_enqueue_style( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/file-manager.css', array(), $submenu_assets['version'], 'all' );
        wp_enqueue_script( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/file-manager.js', $submenu_assets['dependencies'] + $dependencies, $submenu_assets['version'], true );
		wp_localize_script( 'WPMastertoolkit_submenu', 'WPMastertoolkit_FileManager', array(
			'nonce'         => wp_create_nonce( $this->user_nonce ),
			'chunkSize'     => $this->UPLOAD_CHUNK_SIZE,
			'maxFilesize'   => $this->MAX_UPLOAD_SIZE,
			'acceptedFiles' => $this->get_upload_ext(),
			'i10n'          => array(
				'oops'        => __( 'OOPS: minimum 3 characters required!', 'wpmastertoolkit' ),
				'noResults'   => __( 'No result found!', 'wpmastertoolkit' ),
				'errTrylater' => __( 'ERROR: Try again later!', 'wpmastertoolkit' ),
			),
		) );

        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/submenu/header.php';
        $this->submenu_content();
        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/submenu/footer.php';
    }

	/**
	 * Show admin notice
	 * 
	 * @since   1.9.0
	 */
	public function show_admin_notice() {
		$notice = wpmastertoolkit_clean( $_SESSION[$this->option_id]['notice'] ?? '' );

		if ( empty( $notice ) ) {
			return;
		}

		unset( $_SESSION[$this->option_id]['notice'] );

		?>
			<div class="notice notice-<?php echo esc_attr( $notice['class'] ); ?> is-dismissible">
				<p><?php echo esc_html( $notice['msg'] ); ?></p>
			</div>
		<?php
	}

	/**
	 * Handle actions
	 * 
	 * @since   1.9.0
	 */
	private function handle_actions() {

		$this->set_global_variables();

		switch (true) {
			case isset( $_POST['ajax'], $_POST['token'] ) && isset( $_POST['type'] ):

				$nonce = sanitize_text_field( $_POST['token'] ?? '' );
				if ( ! wp_verify_nonce( $nonce, $this->user_nonce ) ) {
					header( 'HTTP/1.0 401 Unauthorized' );
                	die( __( 'Invalid token', 'wpmastertoolkit' ) );
				}

				// Search
				if ( $_POST['type'] == "search" ) {
					$dir      = sanitize_text_field( $_POST['path'] ?? '' ) == "." ? '' : sanitize_text_field( $_POST['path'] ?? '' );
					$response = $this->scan( $this->fm_clean_path( $dir ), sanitize_text_field( $_POST['content'] ?? '' ) );
					echo json_encode($response);
					exit();
				}

				// Save editor file
				if ( $_POST['type'] == "save" ) {
					if ( ! is_dir( $this->PATH ) ) {
						wp_safe_redirect( $this->FM_SELF_URL . '?page=wp-mastertoolkit-settings-file-manager&p=' );
						exit;
					}
	
					$file = sanitize_text_field( $_GET['edit'] ?? '' );
					$file = $this->fm_clean_path( $file );
					$file = str_replace( '/', '', $file );
	
					if ( $file == '' || ! is_file( $this->PATH . '/' . $file ) ) {
						$this->set_notice( __( 'File not found', 'wpmastertoolkit' ) );
						wp_safe_redirect( $this->FM_SELF_URL . '?page=wp-mastertoolkit-settings-file-manager&p=' . urlencode( $this->FM_PATH ) );
						exit;
					}
	
					header('X-XSS-Protection:0');

					$file_path     = $this->PATH . '/' . $file;
					$writedata     = wp_unslash( $_POST['content'] );
					$fd            = fopen( $file_path, "w" );
					$write_results = @fwrite( $fd, $writedata );
					fclose( $fd );
	
					if ( $write_results === false ){
						header("HTTP/1.1 500 Internal Server Error");
						die( __( 'Could Not Write File! - Check Permissions / Ownership', 'wpmastertoolkit' ) );
					}
	
					die(true);
				}

				// Backup
				if ( $_POST['type'] == "backup" && ! empty( $_POST['file'] ) ) {
					$fileName = $this->fm_clean_path( $_POST['file'] );
					$fullPath = $this->FM_ROOT_PATH . '/';
	
					if ( ! empty( $_POST['path'] ) ) {
						$relativeDirPath = $this->fm_clean_path( $_POST['path'] );
						$fullPath       .= "{$relativeDirPath}/";
					}
	
					$date                   = wp_date("dMy-His");
					$newFileName            = "{$fileName}-{$date}.bak";
					$fullyQualifiedFileName = $fullPath . $fileName;
	
					try {
						if ( ! file_exists( $fullyQualifiedFileName ) ) {
							throw new Exception( sprintf( __( "File %s not found", 'wpmastertoolkit' ), $fileName ) );
						}
						if ( copy( $fullyQualifiedFileName, $fullPath . $newFileName ) ) {
							printf( __( "Backup %s created", 'wpmastertoolkit' ), $newFileName );
						} else {
							throw new Exception( sprintf( __( "Could not copy file %s", 'wpmastertoolkit' ), $fileName ) );
						}
					} catch (Exception $e) {
						echo $e->getMessage();
					}
				}

				exit;
			break;
			case isset( $_GET['del'], $_POST['token'] ):

				$nonce = sanitize_text_field( $_POST['token'] ?? '' );
				if ( ! wp_verify_nonce( $nonce, $this->user_nonce ) ) {
					$this->set_notice( __( 'Invalid token', 'wpmastertoolkit' ) );
					wp_safe_redirect( $this->FM_SELF_URL . '?page=wp-mastertoolkit-settings-file-manager&p=' . urlencode( $this->FM_PATH ) );
					exit;
				}

				$del = str_replace( '/', '', $this->fm_clean_path( sanitize_text_field( $_GET['del'] ) ) );

				if ( $del == '' || $del == '..' || $del == '.' ) {
					$this->set_notice( __( 'Invalid file or folder name', 'wpmastertoolkit' ) );
					wp_safe_redirect( $this->FM_SELF_URL . '?page=wp-mastertoolkit-settings-file-manager&p=' . urlencode( $this->FM_PATH ) );
					exit;
				}

				$is_dir = is_dir( $this->PATH . '/' . $del );

				if ( $this->fm_rdelete( $this->PATH . '/' . $del ) ) {
					$message = $is_dir ? __( "Folder <b>%s</b> Deleted", 'wpmastertoolkit' ) : __( "File <b>%s</b> Deleted", 'wpmastertoolkit' );
					$this->set_notice( sprintf( $message, $this->fm_enc( $del ) ), 'success' );
				} else {
					$message = $is_dir ? __( "Folder <b>%s</b> not deleted", 'wpmastertoolkit' ) : __( "File <b>%s</b> not deleted", 'wpmastertoolkit' );
					$this->set_notice( sprintf( $message, $this->fm_enc( $del ) ) );
				}
				
				wp_safe_redirect( $this->FM_SELF_URL . '?page=wp-mastertoolkit-settings-file-manager&p=' . urlencode( $this->FM_PATH ) );
				exit;
			break;
			case isset( $_POST['newfilename'], $_POST['newfile'], $_POST['token'] ):

				$nonce = sanitize_text_field( $_POST['token'] ?? '' );
				if ( ! wp_verify_nonce( $nonce, $this->user_nonce ) ) {
					$this->set_notice( __( 'Invalid token', 'wpmastertoolkit' ) );
					wp_safe_redirect( $this->FM_SELF_URL . '?page=wp-mastertoolkit-settings-file-manager&p=' . urlencode( $this->FM_PATH ) );
					exit;
				}

				$type = urldecode( sanitize_text_field( $_POST['newfile'] ?? 'file' ) );
            	$new  = str_replace( '/', '', $this->fm_clean_path( strip_tags( sanitize_text_field( $_POST['newfilename'], '' ) ) ) );

				if ( ! $this->fm_isvalid_filename( $new ) || $new == '' || $new == '..' || $new == '.' ) {
					$this->set_notice( __( 'Invalid characters in file or folder name', 'wpmastertoolkit' ) );
					wp_safe_redirect( $this->FM_SELF_URL . '?page=wp-mastertoolkit-settings-file-manager&p=' . urlencode( $this->FM_PATH ) );
					exit;
				}

				if ( $type == "file" ) {
					if ( ! file_exists( $this->PATH . '/' . $new ) ) {
						if ( $this->fm_is_valid_ext( $new ) ) {
							if ( ! @fopen( $this->PATH . '/' . $new, 'w' ) ) {
								$this->set_notice( sprintf( __( 'Cannot open file: %s', 'wpmastertoolkit' ), $new ) );
								wp_safe_redirect( $this->FM_SELF_URL . '?page=wp-mastertoolkit-settings-file-manager&p=' . urlencode( $this->FM_PATH ) );
								exit;
							}
							$this->set_notice( sprintf( __( 'File <b>%s</b> Created', 'wpmastertoolkit' ), $this->fm_enc( $new ) ), 'success' );
						} else {
							$this->set_notice( __( 'File extension is not allowed', 'wpmastertoolkit' ) );
						}
					} else {
						$this->set_notice( sprintf( __( 'File <b>%s</b> already exist', 'wpmastertoolkit' ), $this->fm_enc( $new ) ) );
					}
				} else {
					if ( $this->fm_mkdir( $this->PATH . '/' . $new, false ) === true ) {
						$this->set_notice( sprintf( __( 'Folder <b>%s</b> Created', 'wpmastertoolkit' ), $new ), 'success' );
					} elseif ( $this->fm_mkdir( $this->PATH . '/' . $new, false ) === $this->PATH . '/' . $new ) {
						$this->set_notice( sprintf( __( 'Folder <b>%s</b> already exist', 'wpmastertoolkit' ), $new ) );
					} else {
						$this->set_notice( sprintf( __( 'Folder <b>%s</b> not created', 'wpmastertoolkit' ), $new ) );
					}
				}

				wp_safe_redirect( $this->FM_SELF_URL . '?page=wp-mastertoolkit-settings-file-manager&p=' . urlencode( $this->FM_PATH ) );
				exit;
			break;
			case isset( $_GET['copy'], $_GET['finish'] ):
				$copy = urldecode( sanitize_text_field( $_GET['copy'] ) );
            	$copy = $this->fm_clean_path( $copy );

				if ( $copy == '' ) {
					$this->set_notice( __( 'Source path not defined', 'wpmastertoolkit' ) );
					wp_safe_redirect( $this->FM_SELF_URL . '?page=wp-mastertoolkit-settings-file-manager&p=' . urlencode( $this->FM_PATH ) );
					exit;
				}

				$from = $this->FM_ROOT_PATH . '/' . $copy;
				$dest = $this->FM_ROOT_PATH;

				if ( $this->FM_PATH != '' ) {
					$dest .= '/' . $this->FM_PATH;
				}
				$dest .= '/' . basename( $from );
				$move = isset( $_GET['move'] ) ? sanitize_text_field( $_GET['move'] ) : '';
				$move = $this->fm_clean_path( urldecode( $move ) );

				if ( $from != $dest ) {
					$msg_from = trim( $this->FM_PATH . '/' . basename( $from ), '/' );
					if ( $move ) {
						$rename = $this->fm_rename( $from, $dest );
						if ( $rename ) {
							$message = sprintf( __( "Moved from <b>%s</b> to <b>%s</b>", 'wpmastertoolkit' ), $this->fm_enc( $copy ), $this->fm_enc( $msg_from ) );
							$this->set_notice( $message, 'success' );
						} elseif ( $rename === null ) {
							$this->set_notice( __( "File or folder with this path already exists", 'wpmastertoolkit' ) );
						} else {
							$message = sprintf( __( "Error while moving from <b>%s</b> to <b>%s</b>", 'wpmastertoolkit' ), $this->fm_enc( $copy ), $this->fm_enc( $msg_from ) );
							$this->set_notice( $message, 'success' );
						}
					} else {
						if ( $this->fm_rcopy( $from, $dest ) ) {
							$message = sprintf( __( "Copied from <b>%s</b> to <b>%s</b>", 'wpmastertoolkit' ), $this->fm_enc( $copy ), $this->fm_enc( $msg_from ) );
							$this->set_notice( $message, 'success' );
						} else {
							$message = sprintf( __( "Error while copying from <b>%s</b> to <b>%s</b>", 'wpmastertoolkit' ), $this->fm_enc( $copy ), $this->fm_enc( $msg_from ) );
							$this->set_notice( $message );
						}
					}
				} else {
					if ( ! $move ){
						$msg_from = trim( $this->FM_PATH . '/' . basename( $from ), '/' );
						$fn_parts = pathinfo( $from );
	
						$extension_suffix = '';
						if ( ! is_dir( $from ) ){
							$extension_suffix = '.' . $fn_parts['extension'];
						}
	
						//Create new name for duplicate
						$fn_duplicate = $fn_parts['dirname'] . '/' . $fn_parts['filename'] . '-' . date('YmdHis') . $extension_suffix;
						$loop_count   = 0;
						$max_loop     = 1000;
	
						// Check if a file with the duplicate name already exists, if so, make new name (edge case...)
						while( file_exists( $fn_duplicate ) & $loop_count < $max_loop ){
							$fn_parts     = pathinfo( $fn_duplicate );
							$fn_duplicate = $fn_parts['dirname'] . '/' . $fn_parts['filename'] . '-copy' . $extension_suffix;
							$loop_count++;
						}
	
						if ( $this->fm_rcopy( $from, $fn_duplicate, False ) ) {
							$message = sprintf( __( "Copied from <b>%s</b> to <b>%s</b>", 'wpmastertoolkit' ), $this->fm_enc( $copy ), $this->fm_enc( $fn_duplicate ) );
							$this->set_notice( $message, 'success' );
						} else {
							$message = sprintf( __( "Error while copying from <b>%s</b> to <b>%s</b>", 'wpmastertoolkit' ), $this->fm_enc( $copy ), $this->fm_enc( $fn_duplicate ) );
							$this->set_notice( $message );
						}
					} else {
						$this->set_notice( __( 'Source and destination paths must be not equal', 'wpmastertoolkit' ) );
					}

					wp_safe_redirect( $this->FM_SELF_URL . '?page=wp-mastertoolkit-settings-file-manager&p=' . urlencode( $this->FM_PATH ) );
					exit;
				}
			break;
			case isset( $_POST['file'], $_POST['copy_to'], $_POST['finish'], $_POST['token'] ):

				$nonce = sanitize_text_field( $_POST['token'] ?? '' );
				if ( ! wp_verify_nonce( $nonce, $this->user_nonce ) ) {
					$this->set_notice( __( 'Invalid token', 'wpmastertoolkit' ) );
					wp_safe_redirect( $this->FM_SELF_URL . '?page=wp-mastertoolkit-settings-file-manager&p=' . urlencode( $this->FM_PATH ) );
					exit;
				}

				$copy_to_path = $this->FM_ROOT_PATH;
				$copy_to      = $this->fm_clean_path( sanitize_text_field( $_POST['copy_to'] ?? '' ) );

				if ( $copy_to != '' ) {
					$copy_to_path .= '/' . $copy_to;
				}

				if ( $this->PATH == $copy_to_path ) {
					$this->set_notice( __( 'Paths must be not equal', 'wpmastertoolkit' ) );
					wp_safe_redirect( $this->FM_SELF_URL . '?page=wp-mastertoolkit-settings-file-manager&p=' . urlencode( $this->FM_PATH ) );
					exit;
				}

				if ( ! is_dir( $copy_to_path ) ) {
					if ( ! $this->fm_mkdir( $copy_to_path, true ) ) {
						$this->set_notice( __( 'Unable to create destination folder', 'wpmastertoolkit' ) );
						wp_safe_redirect( $this->FM_SELF_URL . '?page=wp-mastertoolkit-settings-file-manager&p=' . urlencode( $this->FM_PATH ) );
						exit;
					}
				}

				$move          = isset( $_POST['move'] );
				$errors        = 0;
				$files_to_copy = wpmastertoolkit_clean( $_POST['file'] ?? array() );

				if ( ! is_array( $files_to_copy ) || ! count( $files_to_copy ) ) {
					$this->set_notice( __( 'Nothing selected', 'wpmastertoolkit' ) );
					wp_safe_redirect( $this->FM_SELF_URL . '?page=wp-mastertoolkit-settings-file-manager&p=' . urlencode( $this->FM_PATH ) );
					exit;
				}

				foreach ( $files_to_copy as $f ) {
					if ( $f != '' ) {
						$f    = $this->fm_clean_path( $f );
						$from = $this->PATH . '/' . $f;
						$dest = $copy_to_path . '/' . $f;
						if ( $move ) {
							$rename = $this->fm_rename( $from, $dest );
							if ( $rename === false ) {
								$errors++;
							}
						} else {
							if ( ! $this->fm_rcopy( $from, $dest ) ) {
								$errors++;
							}
						}
					}
				}

				if ( $errors > 0 ) {
					$message = $move ? __( 'Error while moving items', 'wpmastertoolkit' ) : __( 'Error while copying items', 'wpmastertoolkit' );
					$this->set_notice( $message );
					wp_safe_redirect( $this->FM_SELF_URL . '?page=wp-mastertoolkit-settings-file-manager&p=' . urlencode( $this->FM_PATH ) );
					exit;
				}

				$message = $move ? __( 'Selected files and folders moved', 'wpmastertoolkit' ) : __( 'Selected files and folders copied', 'wpmastertoolkit' );
				$this->set_notice( $message, 'suscess' );
				wp_safe_redirect( $this->FM_SELF_URL . '?page=wp-mastertoolkit-settings-file-manager&p=' . urlencode( $this->FM_PATH ) );
				exit;
			break;
			case isset( $_POST['rename_from'], $_POST['rename_to'], $_POST['token'] ):

				$nonce = sanitize_text_field( $_POST['token'] ?? '' );
				if ( ! wp_verify_nonce( $nonce, $this->user_nonce ) ) {
					$this->set_notice( __( 'Invalid token', 'wpmastertoolkit' ) );
					wp_safe_redirect( $this->FM_SELF_URL . '?page=wp-mastertoolkit-settings-file-manager&p=' . urlencode( $this->FM_PATH ) );
					exit;
				}

				$old = urldecode( sanitize_text_field( $_POST['rename_from'] ) );
				$old = $this->fm_clean_path( $old );
				$old = str_replace( '/', '', $old );
				$new = urldecode( sanitize_text_field( $_POST['rename_to'] ) );
				$new = $this->fm_clean_path( strip_tags( $new ) );
				$new = str_replace( '/', '', $new );

				if ( ! $this->fm_isvalid_filename( $new ) || $old == '' || $new == '' ) {
					$this->set_notice( __( 'Invalid characters in file name', 'wpmastertoolkit' ) );
					wp_safe_redirect( $this->FM_SELF_URL . '?page=wp-mastertoolkit-settings-file-manager&p=' . urlencode( $this->FM_PATH ) );
					exit;
				}

				if ( $this->fm_rename( $this->PATH . '/' . $old, $this->PATH . '/' . $new ) ) {
					$message = sprintf( __( "Renamed from <b>%s</b> to <b>%s</b>", 'wpmastertoolkit' ), $this->fm_enc( $old ), $this->fm_enc( $new ) );
					$this->set_notice( $message, 'success' );
					wp_safe_redirect( $this->FM_SELF_URL . '?page=wp-mastertoolkit-settings-file-manager&p=' . urlencode( $this->FM_PATH ) );
					exit;
				} else {
					$message = sprintf( __( "Error while renaming from <b>%s</b> to <b>%s</b>", 'wpmastertoolkit' ), $this->fm_enc( $old ), $this->fm_enc( $new ) );
					$this->set_notice( $message, 'success' );
					wp_safe_redirect( $this->FM_SELF_URL . '?page=wp-mastertoolkit-settings-file-manager&p=' . urlencode( $this->FM_PATH ) );
					exit;
				}
			break;
			case isset( $_GET['dl'], $_POST['token'] ):

				$nonce = sanitize_text_field( $_POST['token'] ?? '' );
				if ( ! wp_verify_nonce( $nonce, $this->user_nonce ) ) {
					$this->set_notice( __( 'Invalid token', 'wpmastertoolkit' ) );
					wp_safe_redirect( $this->FM_SELF_URL . '?page=wp-mastertoolkit-settings-file-manager&p=' . urlencode( $this->FM_PATH ) );
					exit;
				}

				$dl = urldecode( sanitize_text_field( $_GET['dl'] ) );
				$dl = $this->fm_clean_path( $dl );
				$dl = str_replace( '/', '', $dl );

				if ( $dl != '' && is_file($this->PATH . '/' . $dl ) ) {
					$download        = $this->fm_download_file( $this->PATH . '/' . $dl, $dl, 1024 );
					$download_status = $download['status'] ?? false;
					if ( $download_status ) {
						exit;
					} else {
						$download_message = $download['msg'] ?? '';
						$this->set_notice( $download_message );
						wp_safe_redirect( $this->FM_SELF_URL . '?page=wp-mastertoolkit-settings-file-manager&p=' . urlencode( $this->FM_PATH ) );
						exit;
					}
				} else {
					$this->set_notice( __( 'File not found', 'wpmastertoolkit' ) );
					wp_safe_redirect( $this->FM_SELF_URL . '?page=wp-mastertoolkit-settings-file-manager&p=' . urlencode( $this->FM_PATH ) );
					exit;
				}
			break;
			case ! empty( $_FILES ):

				$nonce = sanitize_text_field( $_POST['token'] ?? '' );
				if ( ! wp_verify_nonce( $nonce, $this->user_nonce ) ) {
					wp_send_json( array( 'status' => 'error', 'info' => esc_html__( 'Invalid Token', 'wpmastertoolkit' ) ) );
				}

				$chunkIndex    = sanitize_text_field( $_POST['dzchunkindex'] ?? '' );
            	$chunkTotal    = sanitize_text_field( $_POST['dztotalchunkcount'] ?? '' );
				$fullPath      = sanitize_text_field( $_POST['fullpath'] ?? '' );
				$fullPathInput = $this->fm_clean_path( $fullPath );
				$f             = $_FILES;
				$ds            = DIRECTORY_SEPARATOR;
				$allowed       = ( $this->FM_UPLOAD_EXTENSION ) ? explode(',', $this->FM_UPLOAD_EXTENSION ) : false;
				$response      = array (
					'status' => 'error',
					'info'   => __( 'Oops! Try again', 'wpmastertoolkit' ),
				);
	
				$filename       = $f['file']['name'];
				$tmp_name       = $f['file']['tmp_name'];
				$ext            = pathinfo( $filename, PATHINFO_FILENAME ) != '' ? strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) ) : '';
				$isFileAllowed  = ($allowed) ? in_array($ext, $allowed) : true;
	
				if ( ! $this->fm_isvalid_filename( $filename ) && ! $this->fm_isvalid_filename( $fullPathInput ) ) {
					wp_send_json( array( 'status' => 'error', 'info' => __( 'Invalid File name!', 'wpmastertoolkit' ) ) );
				}
	
				$targetPath = $this->PATH . $ds;
				if ( ! is_writable( $targetPath ) ) {
					wp_send_json( array( 'status' => 'error', 'info' => 'The specified folder for upload isn\'t writeable' ) );
				}
	
				$fullPath      = $this->PATH . '/' . basename( $fullPathInput );
				$folder        = substr( $fullPath, 0, strrpos( $fullPath, "/" ) );
				$file_fullPath = $folder . '/' . $filename;
	
				if ( ! is_dir( $folder ) ) {
					$old = umask(0);
					mkdir($folder, 0777, true);
					umask($old);
				}
	
				if ( empty( $f['file']['error'] ) && ! empty( $tmp_name ) && $tmp_name != 'none' && $isFileAllowed ) {
					if ( $chunkTotal ){
                    	$out = @fopen("{$fullPath}.part", $chunkIndex == 0 ? "wb" : "ab");
						if ( $out ) {
							$in = @fopen($tmp_name, "rb");
							if ( $in ) {
								if ( PHP_VERSION_ID < 80009 ) {
									// workaround https://bugs.php.net/bug.php?id=81145
									do {
										for (;;) {
											$buff = fread($in, 4096);
											if ($buff === false || $buff === '') {
												break;
											}
											fwrite($out, $buff);
										}
									} while (!feof($in));
								} else {
									stream_copy_to_stream($in, $out);
								}
								$response = array (
									'status' => 'success',
									'info'   => __( 'file upload successful', 'wpmastertoolkit' ),
								);
							} else {
								$response = array (
									'status'       => 'error',
									'info'         => __( 'failed to open output stream', 'wpmastertoolkit' ),
									'errorDetails' => error_get_last(),
								);
							}
							@fclose($in);
							@fclose($out);
							@unlink($tmp_name);

							$response = array (
								'status' => 'success',
								'info'   => __( 'file upload successful', 'wpmastertoolkit' ),
							);
						} else {
							$response = array (
								'status' => 'error',
								'info'   => __( 'failed to open output stream', 'wpmastertoolkit' ),
							);
						}

						if ( $chunkIndex == $chunkTotal - 1 ) {
							if ( file_exists ( $fullPath ) ) {
								$ext_1 = $ext ? '.'.$ext : '';
								$fullPathTarget = $this->PATH . '/' . basename( $fullPathInput, $ext_1 ) .'_'. wp_date('ymdHis'). $ext_1;
							} else {
								$fullPathTarget = $fullPath;
							}
							rename("{$fullPath}.part", $fullPathTarget);
						}
                	} else if ( move_uploaded_file( $tmp_name, $file_fullPath ) ) {
						if ( file_exists( $fullPath ) ) {
							$response = array (
								'status' => 'success',
								'info'   => __( 'file upload successfully', 'wpmastertoolkit' ),
							);
						} else {
							$response = array (
								'status' => 'error',
								'info'   => __( 'Couldn\'t upload the requested file.', 'wpmastertoolkit' ),
							);
						}
					} else {
						$response = array (
							'status' => 'error',
							'info'   => __( 'Error while uploading files.', 'wpmastertoolkit' ),
						);
					}
				}
	
				wp_send_json( $response );
			break;
			case isset( $_POST['group'], $_POST['delete'], $_POST['token'] ):

				$nonce = sanitize_text_field( $_POST['token'] ?? '' );
				if ( ! wp_verify_nonce( $nonce, $this->user_nonce ) ) {
					$this->set_notice( __( 'Invalid token', 'wpmastertoolkit' ) );
					wp_safe_redirect( $this->FM_SELF_URL . '?page=wp-mastertoolkit-settings-file-manager&p=' . urlencode( $this->FM_PATH ) );
					exit;
				}

				$errors          = 0;
				$files_to_delete = wpmastertoolkit_clean( $_POST['file'] ?? array() );

				if ( ! is_array( $files_to_delete ) || ! count( $files_to_delete ) ) {
					$this->set_notice( __( 'No files selected', 'wpmastertoolkit' ) );
					wp_safe_redirect( $this->FM_SELF_URL . '?page=wp-mastertoolkit-settings-file-manager&p=' . urlencode( $this->FM_PATH ) );
					exit;
				}

				foreach ( $files_to_delete as $f ) {
					if ( $f != '' ) {
						$new_path = $this->PATH . '/' . $f;
						if ( ! $this->fm_rdelete( $new_path ) ) {
							$errors++;
						}
					}
				}

				if ( $errors == 0 ) {
					$this->set_notice( __( 'Selected files and folders deleted', 'wpmastertoolkit' ), 'success' );
					wp_safe_redirect( $this->FM_SELF_URL . '?page=wp-mastertoolkit-settings-file-manager&p=' . urlencode( $this->FM_PATH ) );
					exit;
				} else {
					$this->set_notice( __( 'Error while deleting items', 'wpmastertoolkit' ) );
					wp_safe_redirect( $this->FM_SELF_URL . '?page=wp-mastertoolkit-settings-file-manager&p=' . urlencode( $this->FM_PATH ) );
					exit;
				}
			break;
			case isset( $_POST['group'], $_POST['token'] ) && ( isset( $_POST['zip'] ) || isset( $_POST['tar'] ) ):

				$nonce = sanitize_text_field( $_POST['token'] ?? '' );
				if ( ! wp_verify_nonce( $nonce, $this->user_nonce ) ) {
					$this->set_notice( __( 'Invalid token', 'wpmastertoolkit' ) );
					wp_safe_redirect( $this->FM_SELF_URL . '?page=wp-mastertoolkit-settings-file-manager&p=' . urlencode( $this->FM_PATH ) );
					exit;
				}

            	$ext = isset( $_POST['tar'] ) ? 'tar' : 'zip';

				if ( ( $ext == "zip" && ! class_exists('ZipArchive') ) || ( $ext == "tar" && ! class_exists('PharData') ) ) {
					$this->set_notice( __( 'Operations with archives are not available', 'wpmastertoolkit' ) );
					wp_safe_redirect( $this->FM_SELF_URL . '?page=wp-mastertoolkit-settings-file-manager&p=' . urlencode( $this->FM_PATH ) );
					exit;
				}

				$files_to_zip    = wpmastertoolkit_clean( $_POST['file'] ?? array() );
				$sanitized_files = array();

				foreach( $files_to_zip as $file ){
					array_push( $sanitized_files, $this->fm_clean_path( $file ) );
				}

				$files_to_zip = $sanitized_files;

				if ( empty( $files_to_zip ) ) {
					$this->set_notice( __( 'No files selected', 'wpmastertoolkit' ) );
					wp_safe_redirect( $this->FM_SELF_URL . '?page=wp-mastertoolkit-settings-file-manager&p=' . urlencode( $this->FM_PATH ) );
					exit;
				}

				chdir( $this->PATH );

				if ( count( $files_to_zip ) == 1 ) {
					$one_file = reset( $files_to_zip );
					$one_file = basename( $one_file );
					$zipname  = $one_file . '_' . wp_date('ymd_His') . '.' . $ext;
				} else {
					$zipname = 'archive_' . wp_date('ymd_His') . '.' . $ext;
				}

				if ( $ext == 'zip' ) {
					include WPMASTERTOOLKIT_PLUGIN_PATH . '/admin/helpers/file-manager/class-fm-zipper.php';
					$zipper = new FM_Zipper();
					$res    = $zipper->create( $zipname, $files_to_zip );
				} elseif ( $ext == 'tar' ) {
					include WPMASTERTOOLKIT_PLUGIN_PATH . '/admin/helpers/file-manager/class-fm-zipper-tar.php';
					$tar = new FM_Zipper_Tar();
					$res = $tar->create( $zipname, $files_to_zip );
				}

				if ( $res ) {
					$message = sprintf( __( "Archive <b>%s</b> Created", 'wpmastertoolkit' ), $this->fm_enc( $zipname ) );
					$this->set_notice( $message, 'success' );
					wp_safe_redirect( $this->FM_SELF_URL . '?page=wp-mastertoolkit-settings-file-manager&p=' . urlencode( $this->FM_PATH ) );
					exit;
				} else {
					$this->set_notice( __( 'Archive not created', 'wpmastertoolkit' ) );
					wp_safe_redirect( $this->FM_SELF_URL . '?page=wp-mastertoolkit-settings-file-manager&p=' . urlencode( $this->FM_PATH ) );
					exit;
				}

			break;
			case isset( $_POST['unzip'], $_POST['token'] ):

				$nonce = sanitize_text_field( $_POST['token'] ?? '' );
				if ( ! wp_verify_nonce( $nonce, $this->user_nonce ) ) {
					$this->set_notice( __( 'Invalid token', 'wpmastertoolkit' ) );
					wp_safe_redirect( $this->FM_SELF_URL . '?page=wp-mastertoolkit-settings-file-manager&p=' . urlencode( $this->FM_PATH ) );
					exit;
				}

				$unzip = urldecode( sanitize_text_field( $_POST['unzip'] ?? '' ) );
				$unzip = $this->fm_clean_path( $unzip );
				$unzip = str_replace( '/', '', $unzip );


				if ( $unzip != '' && is_file( $this->PATH . '/' . $unzip ) ) {
					$zip_path = $this->PATH . '/' . $unzip;
					$ext      = pathinfo( $zip_path, PATHINFO_EXTENSION );
				} else {
					$this->set_notice( __( 'File not found', 'wpmastertoolkit' ) );
					wp_safe_redirect( $this->FM_SELF_URL . '?page=wp-mastertoolkit-settings-file-manager&p=' . urlencode( $this->FM_PATH ) );
					exit;
				}

				if ( ( $ext == "zip" && ! class_exists('ZipArchive') ) || ( $ext == "tar" && ! class_exists('PharData') ) ) {
					$this->set_notice( __( 'Operations with archives are not available', 'wpmastertoolkit' ) );
					wp_safe_redirect( $this->FM_SELF_URL . '?page=wp-mastertoolkit-settings-file-manager&p=' . urlencode( $this->FM_PATH ) );
					exit;
				}

				$path     = $this->PATH;
				$tofolder = '';
				if ( isset( $_POST['tofolder'] ) ) {
					$tofolder = pathinfo( $zip_path, PATHINFO_FILENAME );
					if ( $this->fm_mkdir( $path . '/' . $tofolder, true ) ) {
						$path .= '/' . $tofolder;
					}
				}

				if( $ext == "zip" ) {
					include WPMASTERTOOLKIT_PLUGIN_PATH . '/admin/helpers/file-manager/class-fm-zipper.php';
					$zipper = new FM_Zipper();
					$res    = $zipper->unzip( $zip_path, $path );
				} elseif ( $ext == "tar" ) {
					try {
						$gzipper = new PharData( $zip_path );
						if ( @$gzipper->extractTo( $path, null, true ) ) {
							$res = true;
						} else {
							$res = false;
						}
					} catch (Exception $e) {
						$res = true;
					}
				}

				if ( $res ) {
					$this->set_notice( __( 'Unzip successful', 'wpmastertoolkit' ), 'success' );
				} else {
					$this->set_notice( __( 'Unzip failed', 'wpmastertoolkit' ) );
				}

				wp_safe_redirect( $this->FM_SELF_URL . '?page=wp-mastertoolkit-settings-file-manager&p=' . urlencode( $this->FM_PATH ) );
				exit;
			break;
			case isset( $_POST['chmod'], $_POST['token'] ) && ! $this->FM_IS_WIN:

				$nonce = sanitize_text_field( $_POST['token'] ?? '' );
				if ( ! wp_verify_nonce( $nonce, $this->user_nonce ) ) {
					$this->set_notice( __( 'Invalid token', 'wpmastertoolkit' ) );
					wp_safe_redirect( $this->FM_SELF_URL . '?page=wp-mastertoolkit-settings-file-manager&p=' . urlencode( $this->FM_PATH ) );
					exit;
				}

				$path = $this->FM_ROOT_PATH;
				if ( $this->FM_PATH != '' ) {
					$path .= '/' . $this->FM_PATH;
				}

				$file = sanitize_text_field( $_POST['chmod'] ?? '' );
				$file = $this->fm_clean_path( $file );
				$file = str_replace( '/', '', $file );

				if ( $file == '' || ( ! is_file( $path . '/' . $file ) && ! is_dir( $path . '/' . $file ) ) ) {
					$this->set_notice( __( 'File not found', 'wpmastertoolkit' ) );
					wp_safe_redirect( $this->FM_SELF_URL . '?page=wp-mastertoolkit-settings-file-manager&p=' . urlencode( $this->FM_PATH ) );
					exit;
				}

				$mode = 0;
				if ( ! empty( $_POST['ur'] ) ) {
					$mode |= 0400;
				}
				if ( ! empty( $_POST['uw'] ) ) {
					$mode |= 0200;
				}
				if ( ! empty( $_POST['ux'] ) ) {
					$mode |= 0100;
				}
				if ( ! empty( $_POST['gr'] ) ) {
					$mode |= 0040;
				}
				if ( ! empty( $_POST['gw'] ) ) {
					$mode |= 0020;
				}
				if ( ! empty( $_POST['gx'] ) ) {
					$mode |= 0010;
				}
				if ( ! empty( $_POST['or'] ) ) {
					$mode |= 0004;
				}
				if ( ! empty( $_POST['ow'] ) ) {
					$mode |= 0002;
				}
				if ( ! empty( $_POST['ox'] ) ) {
					$mode |= 0001;
				}

				if ( @chmod( $path . '/' . $file, $mode ) ) {
					$this->set_notice( __( 'Permissions changed', 'wpmastertoolkit' ), 'success' );
				} else {
					$this->set_notice( __( 'Permissions not changed', 'wpmastertoolkit' ) );
				}

				wp_safe_redirect( $this->FM_SELF_URL . '?page=wp-mastertoolkit-settings-file-manager&p=' . urlencode( $this->FM_PATH ) );
				exit;
			break;
			case isset( $_GET['upload'] ):
				$this->TEMPLATE = 'upload';
			break;
			case isset( $_GET['copy'] ) && ! isset( $_GET['finish'] ):
				$copy = sanitize_text_field( $_GET['copy'] ?? '' );
				$copy = $this->fm_clean_path( $copy );
				
				if ( $copy == '' || ! file_exists( $this->FM_ROOT_PATH . '/' . $copy ) ) {
					$this->set_notice( __( 'File not found', 'wpmastertoolkit' ) );
					wp_safe_redirect( $this->FM_SELF_URL . '?page=wp-mastertoolkit-settings-file-manager&p=' . urlencode( $this->FM_PATH ) );
					exit;
				}
				
				$this->TEMPLATE = 'copyfinish';
			break;
			case isset( $_POST['copy'] ):
				$copy_files     = isset( $_POST['file'] ) ? wpmastertoolkit_clean( $_POST['file'] ) : null;
				$this->TEMPLATE = 'copy';

				if ( ! is_array( $copy_files ) || empty( $copy_files ) ) {
					$this->set_notice(  __( 'Nothing selected', 'wpmastertoolkit' ) );
					wp_safe_redirect( $this->FM_SELF_URL . '?page=wp-mastertoolkit-settings-file-manager&p=' . urlencode( $this->FM_PATH ) );
					exit;
				}
			break;
			case isset( $_GET['view'] ):
				$file           = sanitize_text_field( $_GET['view'] ?? '' );
				$file           = $this->fm_clean_path( $file, false );
				$file           = str_replace( '/', '', $file );
				$this->TEMPLATE = 'view';

				if ( $file == '' || ! is_file( $this->PATH . '/' . $file ) ) {
					$this->set_notice(  __( 'File not found', 'wpmastertoolkit' ) );
					wp_safe_redirect( $this->FM_SELF_URL . '?page=wp-mastertoolkit-settings-file-manager&p=' . urlencode( $this->FM_PATH ) );
					exit;
				}
			break;
			case isset( $_GET['edit'] ):
				$file           = sanitize_text_field( $_GET['edit'] ?? '' );
				$file           = $this->fm_clean_path( $file, false );
				$file           = str_replace( '/', '', $file );
				$this->TEMPLATE = 'edit';

				if ( $file == '' || ! is_file( $this->PATH . '/' . $file ) ) {
					$this->set_notice(  __( 'File not found', 'wpmastertoolkit' ) );
					wp_safe_redirect( $this->FM_SELF_URL . '?page=wp-mastertoolkit-settings-file-manager&p=' . urlencode( $this->FM_PATH ) );
					exit;
				}
				header('X-XSS-Protection:0');

				$file_path = $this->PATH . '/' . $file;
				$ext       = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
				$mime_type = $this->fm_get_mime_type( $file_path );

				if ( in_array( $ext, $this->fm_get_text_exts() ) || substr( $mime_type, 0, 4 ) == 'text' || in_array( $mime_type, $this->fm_get_text_mimes() ) ) {
				} else {
					$this->set_notice(  __( 'File extension not supported for editing', 'wpmastertoolkit' ) );
					wp_safe_redirect( $this->FM_SELF_URL . '?page=wp-mastertoolkit-settings-file-manager&p=' . urlencode( $this->FM_PATH ) );
					exit;
				}
			break;
			case isset( $_GET['chmod'] ) && ! $this->FM_IS_WIN:
				$file           = sanitize_text_field( $_GET['chmod'] ?? '' );
				$file           = $this->fm_clean_path( $file );
				$file           = str_replace( '/', '', $file );
				$this->TEMPLATE = 'chmod';

				if ( $file == '' || ( ! is_file( $this->PATH . '/' . $file ) && ! is_dir( $this->PATH . '/' . $file ) ) ) {
					$this->set_notice( __( 'File not found', 'wpmastertoolkit' ) );
					wp_safe_redirect( $this->FM_SELF_URL . '?page=wp-mastertoolkit-settings-file-manager&p=' . urlencode( $this->FM_PATH ) );
					exit;
				}
			break;
			default:
				$this->TEMPLATE = 'default';
			break;
		}
	}

	/**
     * Add the submenu content
     * 
     * @since   1.9.0
     * @return void
     */
    private function submenu_content() {
        ?>
            <div class="wp-mastertoolkit__section">
				<h1></h1>
                <div class="wp-mastertoolkit__section__body">
					<section class="px-3 py-3">
						<?php
							switch ( $this->TEMPLATE ) {
								case 'upload':
									$this->filemanger_navbar();
									include WPMASTERTOOLKIT_PLUGIN_PATH . '/admin/templates/file-manager/views/upload.php';
								break;
								case 'copyfinish':
									$copy           = sanitize_text_field( $_GET['copy'] ?? '' );
									$copy           = $this->fm_clean_path( $copy );
									$files_folders  = $this->fm_get_files_folders( $this->PATH );
									$files          = $files_folders['files'];
									$folders        = $files_folders['folders'];
									$this->filemanger_navbar();
									include WPMASTERTOOLKIT_PLUGIN_PATH . '/admin/templates/file-manager/views/copyfinish.php';
								break;
								case 'copy':
									$copy_files = isset( $_POST['file'] ) ? wpmastertoolkit_clean( $_POST['file'] ) : null;
									include WPMASTERTOOLKIT_PLUGIN_PATH . '/admin/templates/file-manager/views/copy.php';
								break;
								case 'view':
									$file            = sanitize_text_field( $_GET['view'] ?? '' );
									$file            = $this->fm_clean_path( $file, false );
									$file            = str_replace( '/', '', $file );
									$file_url        = $this->FM_ROOT_URL . $this->fm_convert_win( ( $this->FM_PATH != '' ? '/' . $this->FM_PATH : '') . '/' . $file );
									$file_path       = $this->PATH . '/' . $file;
									$ext             = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
									$mime_type       = $this->fm_get_mime_type( $file_path );
									$filesize_raw    = $this->fm_get_size( $file_path );
									$filesize        = $this->fm_get_filesize( $filesize_raw );
									$is_zip          = false;
									$is_gzip         = false;
									$is_image        = false;
									$is_audio        = false;
									$is_video        = false;
									$is_text         = false;
									$is_onlineViewer = false;
									$view_title      = __( 'File', 'wpmastertoolkit' );
									$filenames       = false; // for zip
									$content         = ''; // for text
									$online_viewer   = strtolower('google');

									if ( $online_viewer && $online_viewer !== 'false' && in_array( $ext, $this->fm_get_onlineViewer_exts() ) ) {
										$is_onlineViewer = true;
									} elseif ( $ext == 'zip' || $ext == 'tar' ) {
										$is_zip     = true;
										$view_title = __( 'Archive', 'wpmastertoolkit' );
										$filenames  = $this->fm_get_zif_info( $file_path, $ext );
									} elseif ( in_array( $ext, $this->fm_get_image_exts() ) ) {
										$is_image   = true;
										$view_title = __( 'Image', 'wpmastertoolkit' );
									} elseif ( in_array( $ext, $this->fm_get_audio_exts() ) ) {
										$is_audio   = true;
										$view_title = __( 'Audio', 'wpmastertoolkit' );
									} elseif ( in_array( $ext, $this->fm_get_video_exts() ) ) {
										$is_video   = true;
										$view_title = __( 'Video', 'wpmastertoolkit' );
									} elseif ( in_array( $ext, $this->fm_get_text_exts() ) || substr( $mime_type, 0, 4 ) == 'text' || in_array( $mime_type, $this->fm_get_text_mimes() ) ) {
										$is_text = true;
										$content = file_get_contents( $file_path );
									}
									$this->filemanger_navbar();
									include WPMASTERTOOLKIT_PLUGIN_PATH . '/admin/templates/file-manager/views/view.php';
								break;
								case 'edit':
									$file      = sanitize_text_field( $_GET['edit'] ?? '' );
									$file      = $this->fm_clean_path( $file, false );
									$file      = str_replace( '/', '', $file );
									$file_url  = $this->FM_ROOT_URL . $this->fm_convert_win( ( $this->FM_PATH != '' ? '/' . $this->FM_PATH : '' ) . '/' . $file );
									$file_path = $this->PATH . '/' . $file;
									$ext       = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
									$mime_type = $this->fm_get_mime_type( $file_path );
									$is_text   = false;
									$content   = '';

									if ( in_array( $ext, $this->fm_get_text_exts() ) || substr( $mime_type, 0, 4 ) == 'text' || in_array( $mime_type, $this->fm_get_text_mimes() ) ) {
										$is_text = true;
										$content = file_get_contents( $file_path );
									}

									$this->filemanger_navbar();
									include WPMASTERTOOLKIT_PLUGIN_PATH . '/admin/templates/file-manager/views/edit.php';
								break;
								case 'chmod':
									$file      = sanitize_text_field( $_GET['chmod'] ?? '' );
									$file      = $this->fm_clean_path( $file );
									$file      = str_replace( '/', '', $file );
									$file_url  = $this->FM_ROOT_URL . ( $this->FM_PATH != '' ? '/' . $this->FM_PATH : '') . '/' . $file;
									$file_path = $this->PATH . '/' . $file;
									$mode      = fileperms( $this->PATH . '/' . $file );

									$this->filemanger_navbar();
									include WPMASTERTOOLKIT_PLUGIN_PATH . '/admin/templates/file-manager/views/chmod.php';
								break;
								default:
									$current_path   = array_slice( explode( "/", $this->PATH ), -1 )[0];
									$files_folders  = $this->fm_get_files_folders( $this->PATH );
									$files          = $files_folders['files'];
									$folders        = $files_folders['folders'];
									$all_files_size = 0;
									$num_files      = count( $files );
									$num_folders    = count( $folders );
									$this->filemanger_navbar();
									include WPMASTERTOOLKIT_PLUGIN_PATH . '/admin/templates/file-manager/views/default.php';
								break;
							}
						?>
					</section>

					<?php
						include WPMASTERTOOLKIT_PLUGIN_PATH . '/admin/templates/file-manager/modals/confirm.php';
						include WPMASTERTOOLKIT_PLUGIN_PATH . '/admin/templates/file-manager/modals/new-item.php';
						include WPMASTERTOOLKIT_PLUGIN_PATH . '/admin/templates/file-manager/modals/rename.php';
						include WPMASTERTOOLKIT_PLUGIN_PATH . '/admin/templates/file-manager/modals/search.php';
					?>

					<div id="snackbar"></div>
                </div>
            </div>
        <?php
    }

	/**
	 * Set global variables
	 * 
	 * @since   1.9.0
	 */
	private function set_global_variables() {
		$this->GLOBAL_IS_HTTPS     = isset( $_SERVER['HTTPS'] ) && ( $_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1 ) || isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https';
    	$this->GLOBAL_SELF_URL     = ( $this->GLOBAL_IS_HTTPS ? 'https' : 'http' ) . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
		$this->ROOT_PATH           = $_SERVER['DOCUMENT_ROOT'];
		$this->ROOT_PATH           = rtrim( $this->ROOT_PATH, '\\/' );
		$this->ROOT_PATH           = str_replace( '\\', '/', $this->ROOT_PATH );
		$this->HTTP_HOST           = $_SERVER['HTTP_HOST'];
		$this->ROOT_URL            = $this->fm_clean_path( '' );
		$this->P                   = isset( $_GET['p'] ) ? $_GET['p'] : ( isset( $_POST['p'] ) ? $_POST['p'] : '' );
		$this->MAX_UPLOAD_SIZE     = 5000000000;
		$this->UPLOAD_CHUNK_SIZE   = 2000000;
		$this->FM_SESSION_ID       = 'filemanager';
		$this->FM_IS_WIN           = DIRECTORY_SEPARATOR == '\\';
		$this->FM_PATH             = $this->P;
		$this->FM_ROOT_PATH        = $this->ROOT_PATH;
		$this->FM_SELF_URL         = $this->GLOBAL_SELF_URL;
		$this->FM_UPLOAD_EXTENSION = '';
		$this->FM_FILE_EXTENSION   = '';
		$this->FM_ROOT_URL         = ( $this->GLOBAL_IS_HTTPS ? 'https' : 'http' ) . '://' . $this->HTTP_HOST . ( ! empty( $this->ROOT_URL ) ? '/' . $this->ROOT_URL : '' );
		$this->PARENT_PATH         = $this->fm_get_parent_path( $this->FM_PATH );
    	$this->PATH                = $this->FM_ROOT_PATH;
		$this->TEMPLATE            = '';
		$this->TOKEN               = wp_create_nonce( $this->user_nonce );

    	if ( $this->FM_PATH != '' ) {
        	$this->PATH .= '/' . $this->FM_PATH;
    	}
	}

	/**
	 * File manager navbar
	 * 
	 * @since   1.9.0
	 */
	private function filemanger_navbar() {
        $path       = $this->fm_clean_path( $this->FM_PATH );
        $root_url   = "<a href='?page=wp-mastertoolkit-settings-file-manager'><i class='fa fa-home' aria-hidden='true' title='" . $this->FM_ROOT_PATH . "'></i></a>";
        $sep        = '<i class="bread-crumb"> / </i>';

        if ( $path != '' ) {
            $exploded = explode( '/', $path );
            $count    = count( $exploded );
            $array    = array();
            $parent   = '';

            for ( $i = 0; $i < $count; $i++ ) {
                $parent     = trim( $parent . '/' . $exploded[$i], '/' );
                $parent_enc = urlencode( $parent );
                $array[]    = "<a href='?page=wp-mastertoolkit-settings-file-manager&p={$parent_enc}'>" . $this->fm_enc( $this->fm_convert_win( $exploded[$i] ) ) . "</a>";
            }
            $root_url .= $sep . implode( $sep, $array );
        }

		include WPMASTERTOOLKIT_PLUGIN_PATH . '/admin/templates/file-manager/views/navbar.php';
    }

	/**
     * Clean path
	 * 
	 * @since   1.9.0
     */
    private function fm_clean_path( $path, $trim = true ) {
        $path = $trim ? trim( $path ) : $path;
        $path = trim( $path, '\\/' );
        $path = str_replace( array( '../', '..\\' ), '', $path );
        $path = $this->get_absolute_path( $path );
        
        if ( $path == '..' ) {
            $path = '';
        }

        return str_replace( '\\', '/', $path );
    }

	/**
     * Path traversal prevention and clean the url
	 * 
	 * @since   1.9.0
     */
    private function get_absolute_path( $path ) {
        $path       = str_replace( array( '/', '\\' ), DIRECTORY_SEPARATOR, $path );
        $parts      = array_filter( explode( DIRECTORY_SEPARATOR, $path ), 'strlen' );
        $absolutes  = array();

        foreach ( $parts as $part ) {
            if ( '.' == $part ) continue;
            if ( '..' == $part ) {
                array_pop( $absolutes );
            } else {
                $absolutes[] = $part;
            }
        }

        return implode( DIRECTORY_SEPARATOR, $absolutes );
    }

	/**
     * Get parent path
	 * 
	 * @since   1.9.0
     */
    private function fm_get_parent_path( $path ) {
        $path = $this->fm_clean_path( $path );

        if ( $path != '' ) {
            $array = explode( '/', $path );
            if ( count( $array ) > 1 ) {
                $array = array_slice( $array, 0, -1 );
                return implode( '/', $array );
            }

            return '';
        }

        return false;
    }

	/**
     * Encode html entities
	 * 
	 * @since   1.9.0
     */
    private function fm_enc( $text ) {
        return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
    }

	/**
     * Convert file name to UTF-8 in Windows
	 * 
	 * @since   1.9.0
     */
    private function fm_convert_win( $filename ) {
        if ( $this->FM_IS_WIN && function_exists('iconv') ) {
            $filename = iconv( 'UTF-8', 'UTF-8//IGNORE', $filename );
        }
        return $filename;
    }

	/**
     * Get upload extensions
	 * 
	 * @since   1.9.0
     */
    private function get_upload_ext() {
        $extArr = explode( ',', $this->FM_UPLOAD_EXTENSION );
        if( $this->FM_UPLOAD_EXTENSION && $extArr ) {
            array_walk( $extArr, function( &$x ) { $x = ".$x"; } );
            return implode( ',', $extArr );
        }
        return '';
    }

	/**
	 * Get files and folders
	 * 
	 * @since   1.9.0
	 */
	private function fm_get_files_folders( $path ) {
		$objects = is_readable( $path ) ? scandir( $path ) : array();
        $folders = array();
        $files   = array();

        if ( is_array( $objects ) ) {
            foreach ( $objects as $file ) {
                if ( $file == '.' || $file == '..' ) continue;

                $new_path = $path . '/' . $file;
                if ( @is_file( $new_path ) ) {
                    $files[] = $file;
                } elseif ( @is_dir( $new_path ) && $file != '.' && $file != '..' ) {
                    $folders[] = $file;
                }
            }
        }

        if ( ! empty( $files ) ) {
            natcasesort( $files );
        }
    
        if ( ! empty( $folders ) ) {
            natcasesort( $folders );
        }

        return array(
            'files'   => $files,
            'folders' => $folders,
        );
	}

	/**
     * Get CSS classname for file
	 * 
	 * @since   1.9.0
     */
    private function fm_get_file_icon_class( $path ) {
        $ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

        switch ( $ext ) {
            case 'ico':
            case 'gif':
            case 'jpg':
            case 'jpeg':
            case 'jpc':
            case 'jp2':
            case 'jpx':
            case 'xbm':
            case 'wbmp':
            case 'png':
            case 'bmp':
            case 'tif':
            case 'tiff':
            case 'webp':
            case 'avif':
            case 'svg':
                $img = 'fa fa-picture-o';
                break;
            case 'passwd':
            case 'ftpquota':
            case 'sql':
            case 'js':
            case 'ts':
            case 'jsx':
            case 'tsx':
            case 'hbs':
            case 'json':
            case 'sh':
            case 'config':
            case 'twig':
            case 'tpl':
            case 'md':
            case 'gitignore':
            case 'c':
            case 'cpp':
            case 'cs':
            case 'py':
            case 'rs':
            case 'map':
            case 'lock':
            case 'dtd':
                $img = 'fa fa-file-code-o';
                break;
            case 'txt':
            case 'ini':
            case 'conf':
            case 'log':
            case 'htaccess':
            case 'yaml':
            case 'yml':
            case 'toml':
            case 'tmp':
            case 'top':
            case 'bot':
            case 'dat':
            case 'bak':
            case 'htpasswd':
            case 'pl':
                $img = 'fa fa-file-text-o';
                break;
            case 'css':
            case 'less':
            case 'sass':
            case 'scss':
                $img = 'fa fa-css3';
                break;
            case 'bz2':
            case 'zip':
            case 'rar':
            case 'gz':
            case 'tar':
            case '7z':
            case 'xz':
                $img = 'fa fa-file-archive-o';
                break;
            case 'php':
            case 'php4':
            case 'php5':
            case 'phps':
            case 'phtml':
                $img = 'fa fa-code';
                break;
            case 'htm':
            case 'html':
            case 'shtml':
            case 'xhtml':
                $img = 'fa fa-html5';
                break;
            case 'xml':
            case 'xsl':
                $img = 'fa fa-file-excel-o';
                break;
            case 'wav':
            case 'mp3':
            case 'mp2':
            case 'm4a':
            case 'aac':
            case 'ogg':
            case 'oga':
            case 'wma':
            case 'mka':
            case 'flac':
            case 'ac3':
            case 'tds':
                $img = 'fa fa-music';
                break;
            case 'm3u':
            case 'm3u8':
            case 'pls':
            case 'cue':
            case 'xspf':
                $img = 'fa fa-headphones';
                break;
            case 'avi':
            case 'mpg':
            case 'mpeg':
            case 'mp4':
            case 'm4v':
            case 'flv':
            case 'f4v':
            case 'ogm':
            case 'ogv':
            case 'mov':
            case 'mkv':
            case '3gp':
            case 'asf':
            case 'wmv':
            case 'webm':
                $img = 'fa fa-file-video-o';
                break;
            case 'eml':
            case 'msg':
                $img = 'fa fa-envelope-o';
                break;
            case 'xls':
            case 'xlsx':
            case 'ods':
                $img = 'fa fa-file-excel-o';
                break;
            case 'csv':
                $img = 'fa fa-file-text-o';
                break;
            case 'bak':
            case 'swp':
                $img = 'fa fa-clipboard';
                break;
            case 'doc':
            case 'docx':
            case 'odt':
                $img = 'fa fa-file-word-o';
                break;
            case 'ppt':
            case 'pptx':
                $img = 'fa fa-file-powerpoint-o';
                break;
            case 'ttf':
            case 'ttc':
            case 'otf':
            case 'woff':
            case 'woff2':
            case 'eot':
            case 'fon':
                $img = 'fa fa-font';
                break;
            case 'pdf':
                $img = 'fa fa-file-pdf-o';
                break;
            case 'psd':
            case 'ai':
            case 'eps':
            case 'fla':
            case 'swf':
                $img = 'fa fa-file-image-o';
                break;
            case 'exe':
            case 'msi':
                $img = 'fa fa-file-o';
                break;
            case 'bat':
                $img = 'fa fa-terminal';
                break;
            default:
                $img = 'fa fa-info-circle';
        }

        return $img;
    }

	/**
     * Recover all file sizes larger than > 2GB.
     * Works on php 32bits and 64bits and supports linux
	 * 
	 * @since   1.9.0
     */
    private function fm_get_size( $file ) {
        static $iswin;
        static $isdarwin;

        if ( ! isset( $iswin ) ) {
            $iswin = ( strtoupper( substr(PHP_OS, 0, 3) ) == 'WIN' );
        }
        if ( ! isset( $isdarwin ) ) {
            $isdarwin = ( strtoupper( substr(PHP_OS, 0)) == "DARWIN" );
        }

        static $exec_works;
        if ( ! isset( $exec_works ) ) {
            $exec_works = ( function_exists('exec') && ! ini_get('safe_mode') && @exec('echo EXEC') == 'EXEC' );
        }

        // try a shell command
        if ( $exec_works ) {
            $arg = escapeshellarg( $file );
            $cmd = ($iswin) ? "for %F in (\"$file\") do @echo %~zF" : ($isdarwin ? "stat -f%z $arg" : "stat -c%s $arg");
            @exec( $cmd, $output );
            if ( is_array( $output ) && ctype_digit( $size = trim( implode( "\n", $output ) ) ) ) {
                return $size;
            }
        }

        // try the Windows COM interface
        if ( $iswin && class_exists("COM") ) {
            try {
                $fsobj = new COM('Scripting.FileSystemObject');
                $f     = $fsobj->GetFile( realpath($file) );
                $size  = $f->Size;
            } catch (Exception $e) {
                $size = null;
            }
            if ( ctype_digit($size) ) {
                return $size;
            }
        }

        // if all else fails
        return filesize($file);
    }

	/**
     * Get nice filesize
	 * 
	 * @since   1.9.0
     */
    private function fm_get_filesize( $size ) {
        $size  = (float) $size;
        $units = array( 'B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB' );
        $power = ( $size > 0 ) ? floor( log( $size, 1024 ) ) : 0;
        $power = ( $power > ( count( $units ) - 1 ) ) ? ( count( $units ) - 1 ) : $power;

        return sprintf( '%s %s', round( $size / pow( 1024, $power ), 2 ), $units[$power] );
    }

	/**
     * Prevent XSS attacks
	 * 
	 * @since   1.9.0
     */
    private function fm_isvalid_filename( $text ) {
        return ( strpbrk( $text, '/?%*:|"<>' ) === FALSE ) ? true : false;
    }

	/**
     * Safely rename
	 * 
	 * @since   1.9.0
     */
    private function fm_rename( $old, $new ) {
        $isFileAllowed = $this->fm_is_valid_ext( $new );

        if( ! is_dir( $old ) ) {
            if ( ! $isFileAllowed ) return false;
        }

        return ( ! file_exists( $new ) && file_exists( $old ) ) ? rename( $old, $new ) : null;
    }

	/**
     * Check the file extension which is allowed or not
	 * 
	 * @since   1.9.0
     */
    private function fm_is_valid_ext( $filename ) {
        $allowed       = ( $this->FM_FILE_EXTENSION ) ? explode( ',', $this->FM_FILE_EXTENSION ) : false;
        $ext           = pathinfo( $filename, PATHINFO_EXTENSION );
        $isFileAllowed = ( $allowed ) ? in_array( $ext, $allowed ) : true;

        return ( $isFileAllowed ) ? true : false;
    }

	/**
	 * Set notice id
	 * 
	 * @since   1.9.0
	 */
	private function set_notice( $message, $class = 'error' ) {
		$notice = array(
			'msg'   => $message,
			'class' => $class,
		);
		$_SESSION[$this->option_id]['notice'] = $notice;
	}

	/**
     * Copy file or folder (recursively)
	 * 
	 * @since   1.9.0
     */
    private function fm_rcopy( $path, $dest, $upd = true, $force = true ) {
        if ( is_dir( $path ) ) {
            if ( ! $this->fm_mkdir( $dest, $force ) ) {
                return false;
            }
            $objects = scandir( $path );
            $ok      = true;
            if ( is_array( $objects ) ) {
                foreach ( $objects as $file ) {
                    if ( $file != '.' && $file != '..' ) {
                        if ( ! $this->fm_rcopy( $path . '/' . $file, $dest . '/' . $file ) ) {
                            $ok = false;
                        }
                    }
                }
            }
            return $ok;
        } elseif ( is_file( $path ) ) {
            return $this->fm_copy( $path, $dest, $upd );
        }
        return false;
    }

	/**
     * Safely create folder
	 * 
	 * @since   1.9.0
     */
    private function fm_mkdir( $dir, $force ) {
        if ( file_exists( $dir ) ) {
            if ( is_dir( $dir ) ) {
                return $dir;
            } elseif ( ! $force ) {
                return false;
            }
            unlink( $dir );
        }
        return mkdir( $dir, 0777, true );
    }

	/**
     * Safely copy file
	 * 
	 * @since   1.9.0
     */
    private function fm_copy( $f1, $f2, $upd ) {
        $time1 = filemtime( $f1 );
        if ( file_exists( $f2 ) ) {
            $time2 = filemtime( $f2 );
            if ( $time2 >= $time1 && $upd ) {
                return false;
            }
        }
        $ok = copy( $f1, $f2 );
        if ( $ok ) {
            touch( $f2, $time1 );
        }
        return $ok;
    }

	/**
     * Delete  file or folder (recursively)
	 * 
	 * @since   1.9.0
     */
    private function fm_rdelete( $path ) {
        if ( is_link( $path ) ) {
            return unlink( $path );
        } elseif ( is_dir( $path ) ) {
            $objects = scandir( $path );
            $ok      = true;
            if ( is_array( $objects ) ) {
                foreach ( $objects as $file ) {
                    if ( $file != '.' && $file != '..' ) {
                        if ( ! $this->fm_rdelete( $path . '/' . $file ) ) {
                            $ok = false;
                        }
                    }
                }
            }
            return ( $ok ) ? rmdir( $path ) : false;
        } elseif ( is_file( $path ) ) {
            return unlink( $path );
        }
        return false;
    }

	/**
    * Parameters: downloadFile(File Location, File Name,
    * max speed, is streaming
    * If streaming - videos will show as videos, images as images
    * instead of download prompt
    * https://stackoverflow.com/a/13821992/1164642
	* 
	* @since   1.9.0
    */
    private function fm_download_file( $fileLocation, $fileName, $chunkSize  = 1024 ) {
        if ( connection_status() != 0 ) {
            return (false);
		}

        $extension   = pathinfo( $fileName, PATHINFO_EXTENSION );
        $contentType = $this->fm_get_file_mimes( $extension );

        if( is_array( $contentType ) ) {
            $contentType = implode( ' ', $contentType );
        }

        $size = filesize( $fileLocation );

        if ( $size == 0 ) {
            $result = array(
                'status' => false,
                'msg'    => __( 'Zero byte file! Aborting download', 'wp-mastertoolkit' ),
            );
            return $result;
        }

        @ini_set( 'magic_quotes_runtime', 0 );
        $fp = fopen( "$fileLocation", "rb" );

        if ( $fp === false ) {
            $result = array(
                'status' => false,
                'msg'    => __( 'Cannot open file! Aborting download', 'wp-mastertoolkit' ),
            );
            return $result;
        }

        // headers
        header('Content-Description: File Transfer');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header("Content-Transfer-Encoding: binary");
        header("Content-Type: $contentType");

        $contentDisposition = 'attachment';

        if ( strstr( $_SERVER['HTTP_USER_AGENT'], "MSIE" ) ) {
            $fileName = preg_replace( '/\./', '%2e', $fileName, substr_count($fileName, '.') - 1 );
            header("Content-Disposition: $contentDisposition;filename=\"$fileName\"");
        } else {
            header("Content-Disposition: $contentDisposition;filename=\"$fileName\"");
        }

        header("Accept-Ranges: bytes");
        $range = 0;

        if ( isset( $_SERVER['HTTP_RANGE'] ) ) {
            list( $a, $range ) = explode( "=", $_SERVER['HTTP_RANGE'] );
            str_replace( $range, "-", $range );
            $size2 = $size - 1;
            $new_length = $size - $range;
            header("HTTP/1.1 206 Partial Content");
            header("Content-Length: $new_length");
            header("Content-Range: bytes $range$size2/$size");
        } else {
            $size2 = $size - 1;
            header("Content-Range: bytes 0-$size2/$size");
            header("Content-Length: " . $size);
        }
        $fileLocation = realpath( $fileLocation );
        while ( ob_get_level() ) ob_end_clean();
        readfile( $fileLocation );

        fclose( $fp );

        return ( ( connection_status() == 0 ) and ! connection_aborted() );
    }

	/**
     * It returns the mime type of a file based on its extension.
	 * 
	 * @since   1.9.0
     */
    private function fm_get_file_mimes( $extension ) {
        $fileTypes['swf']  = 'application/x-shockwave-flash';
        $fileTypes['pdf']  = 'application/pdf';
        $fileTypes['exe']  = 'application/octet-stream';
        $fileTypes['zip']  = 'application/zip';
        $fileTypes['doc']  = 'application/msword';
        $fileTypes['xls']  = 'application/vnd.ms-excel';
        $fileTypes['ppt']  = 'application/vnd.ms-powerpoint';
        $fileTypes['gif']  = 'image/gif';
        $fileTypes['png']  = 'image/png';
        $fileTypes['jpeg'] = 'image/jpg';
        $fileTypes['jpg']  = 'image/jpg';
        $fileTypes['webp'] = 'image/webp';
        $fileTypes['avif'] = 'image/avif';
        $fileTypes['rar']  = 'application/rar';

        $fileTypes['ra']   = 'audio/x-pn-realaudio';
        $fileTypes['ram']  = 'audio/x-pn-realaudio';
        $fileTypes['ogg']  = 'audio/x-pn-realaudio';

        $fileTypes['wav']  = 'video/x-msvideo';
        $fileTypes['wmv']  = 'video/x-msvideo';
        $fileTypes['avi']  = 'video/x-msvideo';
        $fileTypes['asf']  = 'video/x-msvideo';
        $fileTypes['divx'] = 'video/x-msvideo';

        $fileTypes['mp3']  = 'audio/mpeg';
        $fileTypes['mp4']  = 'audio/mpeg';
        $fileTypes['mpeg'] = 'video/mpeg';
        $fileTypes['mpg']  = 'video/mpeg';
        $fileTypes['mpe']  = 'video/mpeg';
        $fileTypes['mov']  = 'video/quicktime';
        $fileTypes['swf']  = 'video/quicktime';
        $fileTypes['3gp']  = 'video/quicktime';
        $fileTypes['m4a']  = 'video/quicktime';
        $fileTypes['aac']  = 'video/quicktime';
        $fileTypes['m3u']  = 'video/quicktime';

        $fileTypes['php']  = ['application/x-php'];
        $fileTypes['html'] = ['text/html'];
        $fileTypes['txt']  = ['text/plain'];

        // Unknown mime-types should be 'application/octet-stream'
        if( empty( $fileTypes[$extension] ) ) {
        	$fileTypes[$extension] = ['application/octet-stream'];
        }
        return $fileTypes[$extension];
    }

	/**
     * Get mime type
     */
    private function fm_get_mime_type( $file_path ) {
        if ( function_exists('finfo_open') ) {
            $finfo = finfo_open( FILEINFO_MIME_TYPE );
            $mime  = finfo_file( $finfo, $file_path );
            finfo_close( $finfo );
            return $mime;
        } elseif ( function_exists('mime_content_type') ) {
            return mime_content_type( $file_path );
        } elseif ( ! stristr( ini_get('disable_functions'), 'shell_exec' ) ) {
            $file = escapeshellarg( $file_path );
            $mime = shell_exec( 'file -bi ' . $file );
            return $mime;
        } else {
            return '--';
        }
    }

	/**
     * Get online docs viewer supported files extensions
	 * 
	 * @since   1.9.0
     */
    private function fm_get_onlineViewer_exts() {
        return array('doc', 'docx', 'xls', 'xlsx', 'pdf', 'ppt', 'pptx', 'ai', 'psd', 'dxf', 'xps', 'rar', 'odt', 'ods');
    }

	/**
     * Get info about zip archive
	 * 
	 * @since   1.9.0
     */
    private function fm_get_zif_info( $path, $ext ) {
        if ( $ext == 'zip' && function_exists('zip_open') ) {
            $arch = @zip_open( $path );
            if ( $arch ) {
                $filenames = array();
                while ( $zip_entry = @zip_read( $arch ) ) {
                    $zip_name    = @zip_entry_name( $zip_entry );
                    $zip_folder  = substr( $zip_name, -1 ) == '/';
                    $filenames[] = array(
                        'name'            => $zip_name,
                        'filesize'        => @zip_entry_filesize( $zip_entry ),
                        'compressed_size' => @zip_entry_compressedsize( $zip_entry ),
                        'folder'          => $zip_folder
                        //'compression_method' => zip_entry_compressionmethod($zip_entry),
                    );
                }
                @zip_close( $arch );
                return $filenames;
            }
        } elseif ( $ext == 'tar' && class_exists('PharData') ) {
            $archive   = new PharData( $path );
            $filenames = array();
            foreach( new RecursiveIteratorIterator( $archive ) as $file ) {
                $parent_info = $file->getPathInfo();
                $zip_name    = str_replace( "phar://".$path, '', $file->getPathName() );
                $zip_name    = substr( $zip_name, ( $pos = strpos( $zip_name, '/' ) ) !== false ? $pos + 1 : 0 );
                $zip_folder  = $parent_info->getFileName();
                $zip_info    = new SplFileInfo( $file );
                $filenames[] = array(
                    'name'            => $zip_name,
                    'filesize'        => $zip_info->getSize(),
                    'compressed_size' => $file->getCompressedSize(),
                    'folder'          => $zip_folder
                );
            }
            return $filenames;
        }

        return false;
    }

	/**
     * Get image files extensions
	 * 
	 * @since   1.9.0
     */
    private function fm_get_image_exts() {
        return array('ico', 'gif', 'jpg', 'jpeg', 'jpc', 'jp2', 'jpx', 'xbm', 'wbmp', 'png', 'bmp', 'tif', 'tiff', 'psd', 'svg', 'webp', 'avif');
    }

	/**
     * Get audio files extensions
	 * 
	 * @since   1.9.0
     */
    private function fm_get_audio_exts() {
        return array('wav', 'mp3', 'ogg', 'm4a');
    }

	/**
     * Get video files extensions
	 * 
	 * @since   1.9.0
     */
    private function fm_get_video_exts() {
        return array('avi', 'webm', 'wmv', 'mp4', 'm4v', 'ogm', 'ogv', 'mov', 'mkv');
    }

	/**
     * Get text file extensions\
	 * 
	 * @since   1.9.0
     */
    private function fm_get_text_exts() {
        return array(
            'txt', 'css', 'ini', 'conf', 'log', 'htaccess', 'passwd', 'ftpquota', 'sql', 'js', 'ts', 'jsx', 'tsx', 'mjs', 'json', 'sh', 'config',
            'php', 'php4', 'php5', 'phps', 'phtml', 'htm', 'html', 'shtml', 'xhtml', 'xml', 'xsl', 'm3u', 'm3u8', 'pls', 'cue', 'bash', 'vue',
            'eml', 'msg', 'csv', 'bat', 'twig', 'tpl', 'md', 'gitignore', 'less', 'sass', 'scss', 'c', 'cpp', 'cs', 'py', 'go', 'zsh', 'swift',
            'map', 'lock', 'dtd', 'svg', 'asp', 'aspx', 'asx', 'asmx', 'ashx', 'jsp', 'jspx', 'cgi', 'dockerfile', 'ruby', 'yml', 'yaml', 'toml',
            'vhost', 'scpt', 'applescript', 'csx', 'cshtml', 'c++', 'coffee', 'cfm', 'rb', 'graphql', 'mustache', 'jinja', 'http', 'handlebars',
            'java', 'es', 'es6', 'markdown', 'wiki', 'tmp', 'top', 'bot', 'dat', 'bak', 'htpasswd', 'pl'
        );
    }

	/**
     * Get mime types of text files
	 * 
	 * @since   1.9.0
     */
    private function fm_get_text_mimes() {
        return array(
            'application/xml',
            'application/javascript',
            'application/x-javascript',
            'image/svg+xml',
            'message/rfc822',
            'application/json',
        );
    }

	/**
     * Check if string is in UTF-8
	 * 
	 * @since   1.9.0
     */
    private function fm_is_utf8( $string ) {
        return preg_match( '//u', $string );
    }

	/**
     * Get file names of text files w/o extensions
	 * 
	 * @since   1.9.0
     */
    private function fm_get_text_names() {
        return array(
            'license',
            'readme',
            'authors',
            'contributors',
            'changelog',
        );
    }

	/**
     * This function scans the files and folder recursively, and return matching files
	 * 
	 * @since   1.9.0
     */
    private function scan( $dir = '', $filter = '' ) {
        $path = $this->FM_ROOT_PATH . '/' . $dir;

        if( $path ) {
            $ite = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path ) );
            $rii = new RegexIterator( $ite, "/(" . $filter . ")/i" );

            $files = array();
            foreach ( $rii as $file ) {
                if ( ! $file->isDir() ) {
                    $fileName = $file->getFilename();
                    $location = str_replace( $this->FM_ROOT_PATH, '', $file->getPath() );
                    $files[]  = array(
                        "name" => $fileName,
                        "type" => "file",
                        "path" => $location,
                    );
                }
            }
            return $files;
        }
    }
}
