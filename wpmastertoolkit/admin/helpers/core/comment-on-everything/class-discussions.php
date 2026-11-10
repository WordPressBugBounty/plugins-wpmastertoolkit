<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- Match existing module helper filenames.
/**
 * Private collaboration persistence and conversation rules.
 *
 * @package WPMastertoolkit
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Dedicated module-owned tables require direct queries without shared caches.

defined( 'ABSPATH' ) || exit;

/** Discussions are independent of their first message and their visible pin. */
class WPMastertoolkit_Collaboration_Discussions {

	const OPTION         = 'wpmastertoolkit_comment_on_everything_collaboration';
	const SCHEMA         = '2';
	const CAPTURE_BYTES  = 1048576;
	const CAPTURE_WINDOW = 1800;

	/**
	 * WordPress connection.
	 *
	 * @var wpdb
	 */
	private $db;
	/**
	 * Context resolver.
	 *
	 * @var WPMastertoolkit_Collaboration_Contexts
	 */
	private $contexts;
	/**
	 * Threads table.
	 *
	 * @var string
	 */
	private $threads;
	/**
	 * Messages table.
	 *
	 * @var string
	 */
	private $messages;
	/**
	 * Private screenshot table.
	 *
	 * @var string
	 */
	private $captures;

	/**
	 * Accept the actual WordPress connection and context implementation.
	 *
	 * @param wpdb                                   $database WordPress connection.
	 * @param WPMastertoolkit_Collaboration_Contexts $contexts Context resolver.
	 */
	public function __construct( $database, $contexts ) {
		$this->db       = $database;
		$this->contexts = $contexts;
		$this->threads  = $database->prefix . 'wpmastertoolkit_comment_on_everything_threads';
		$this->messages = $database->prefix . 'wpmastertoolkit_comment_on_everything_messages';
		$this->captures = $database->prefix . 'wpmastertoolkit_comment_on_everything_captures';
	}

	/** Collaboration grants never imply access to a context. */
	public static function allowed() {
		$user     = wp_get_current_user();
		$settings = get_option( self::OPTION, array() );
		return $user->exists() && ( current_user_can( 'manage_options' )
			|| ( wpmastertoolkit_is_pro() && ( in_array( $user->ID, array_map( 'absint', (array) ( $settings['users'] ?? array() ) ), true )
				|| (bool) array_intersect( $user->roles, (array) ( $settings['roles'] ?? array() ) ) ) ) );
	}

	/** Whether new screenshots are enabled for the installed edition. */
	public static function screenshots_enabled() {
		$settings = get_option( self::OPTION, array() );
		return wpmastertoolkit_is_pro() && ( ! isset( $settings['screenshots_enabled'] ) || true === $settings['screenshots_enabled'] );
	}

	/**
	 * Install non-destructively; retained tables survive module disable.
	 *
	 * @throws RuntimeException When transactional tables are unavailable.
	 */
	public function install() {
		if ( self::SCHEMA === get_option( self::OPTION . '_schema' ) ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$collate = $this->db->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$this->threads} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			context_key varchar(191) NOT NULL,
			context_json text NOT NULL,
			record_type varchar(20) NOT NULL DEFAULT '',
			record_id bigint(20) unsigned NOT NULL DEFAULT 0,
			anchor text NOT NULL,
			creator bigint(20) unsigned NOT NULL DEFAULT 0,
			resolved tinyint(1) NOT NULL DEFAULT 0,
			version bigint(20) unsigned NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY context_key (context_key),
			KEY record_identity (record_type,record_id),
			KEY cleanup (resolved,updated_at)
		) ENGINE=InnoDB {$collate};"
		);
		dbDelta(
			"CREATE TABLE {$this->messages} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			thread_id bigint(20) unsigned NOT NULL,
			author bigint(20) unsigned NOT NULL DEFAULT 0,
			body text NOT NULL,
			version bigint(20) unsigned NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY conversation (thread_id,id),
			KEY author (author)
		) ENGINE=InnoDB {$collate};"
		);
		dbDelta(
			"CREATE TABLE {$this->captures} (
			thread_id bigint(20) unsigned NOT NULL,
			author bigint(20) unsigned NOT NULL,
			mime varchar(32) NOT NULL,
			width smallint unsigned NOT NULL,
			height smallint unsigned NOT NULL,
			byte_length int unsigned NOT NULL,
			sha256 char(64) NOT NULL,
			created_at datetime NOT NULL,
			data mediumblob NOT NULL,
			PRIMARY KEY  (thread_id),
			KEY author (author)
		) ENGINE=InnoDB {$collate};"
		);
		$engines = $this->db->get_results( $this->db->prepare( 'SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (%s,%s,%s)', $this->threads, $this->messages, $this->captures ) );
		if ( 3 !== count( $engines ) || array_filter(
			$engines,
			static function ( $table ) {
				return 'InnoDB' !== $table->engine; }
		) ) {
			throw new RuntimeException( 'Collaboration requires three InnoDB tables.' );
		}
		update_option( self::OPTION . '_schema', self::SCHEMA, false );
	}

	/**
	 * Run one explicit conversation operation as the authenticated actor.
	 *
	 * @param string $operation Conversation operation.
	 * @param array  $input Request envelope.
	 * @return array|WP_Error Result or error.
	 * @throws RuntimeException Internally caught on failed commit.
	 */
	public function perform( $operation, $input ) {
		if ( ! self::allowed() ) {
			return $this->error( 'forbidden', __( 'Collaboration access is required.', 'wpmastertoolkit' ), 403 );
		}
		if ( 'list' === $operation ) {
			return $this->listing( $input );
		}
		if ( 'read' === $operation ) {
			$thread = $this->find( absint( $input['thread'] ?? 0 ) );
			return is_wp_error( $thread ) ? $thread : $this->present( $thread, true );
		}
		if ( ! in_array( $operation, array( 'create', 'reply', 'edit', 'delete_message', 'resolve', 'repin', 'delete_thread' ), true ) ) {
			return $this->error( 'invalid_operation', __( 'Invalid operation.', 'wpmastertoolkit' ), 400 );
		}
		if ( false === $this->db->query( 'START TRANSACTION' ) ) {
			return $this->storage_error();
		}
		try {
			$result = 'create' === $operation ? $this->create( $input ) : $this->change( $operation, $input );
			if ( is_wp_error( $result ) ) {
				$this->db->query( 'ROLLBACK' );
				return $result;
			}
			if ( false === $this->db->query( 'COMMIT' ) ) {
				throw new RuntimeException( 'Commit failed.' );
			}
			return $result;
		} catch ( Throwable $exception ) {
			$this->db->query( 'ROLLBACK' );
			return $this->storage_error();
		}
	}

	/**
	 * Decode and normalize a bounded PNG without trusting its filename or MIME.
	 *
	 * @param string $bytes Uploaded bytes.
	 * @return array|WP_Error Trusted image or failure.
	 */
	public static function normalize_capture( $bytes ) {
		$error = new WP_Error( 'invalid_capture', __( 'The screenshot must be a valid PNG of at most 640 by 400 pixels and 1 MiB.', 'wpmastertoolkit' ), array( 'status' => 400 ) );
		if ( ! is_string( $bytes ) || strlen( $bytes ) > self::CAPTURE_BYTES || "\x89PNG\r\n\x1a\n" !== substr( $bytes, 0, 8 ) || ! function_exists( 'imagecreatefromstring' ) ) {
			return $error;
		}
		$size = @getimagesizefromstring( $bytes ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Malformed image errors must not corrupt the AJAX response.
		if ( ! $size || IMAGETYPE_PNG !== $size[2] || $size[0] < 1 || $size[0] > 640 || $size[1] < 1 || $size[1] > 400 ) {
			return $error;
		}
		$image = @imagecreatefromstring( $bytes ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Reject corrupt PNGs without leaking decoder diagnostics.
		if ( ! $image ) {
			return $error;
		}
		ob_start();
		try {
			imagesavealpha( $image, true );
			$success = imagepng( $image, null, 6 );
			$png     = ob_get_contents();
		} finally {
			ob_end_clean();
			unset( $image );
		}
		if ( ! $success || ! is_string( $png ) || strlen( $png ) > self::CAPTURE_BYTES ) {
			return $error;
		}
		return array(
			'data'   => $png,
			'width'  => $size[0],
			'height' => $size[1],
			'sha256' => hash( 'sha256', $png ),
		);
	}

	/**
	 * Attach a normalized image once, serialized with erasure and thread deletion.
	 *
	 * @param int   $id Thread identity.
	 * @param array $image Trusted normalized image.
	 * @return array|WP_Error Conversation or failure.
	 */
	public function attach_capture( $id, $image ) {
		if ( ! self::allowed() ) {
			return $this->error( 'forbidden', __( 'Collaboration access is required.', 'wpmastertoolkit' ), 403 );
		}
		if ( ! self::screenshots_enabled() ) {
			return $this->error( wpmastertoolkit_is_pro() ? 'screenshots_disabled' : 'pro_required', __( 'New screenshots require PRO and the screenshot setting enabled.', 'wpmastertoolkit' ), 403 );
		}
		if ( false === $this->db->query( 'START TRANSACTION' ) ) {
			return $this->storage_error();
		}
		try {
			$thread = $this->find( $id, true );
			if ( is_wp_error( $thread ) ) {
				$this->db->query( 'ROLLBACK' );
				return $thread;
			}
			$age = time() - strtotime( $thread['created_at'] . ' UTC' );
			if ( get_current_user_id() !== (int) $thread['creator'] || $age < 0 || $age > self::CAPTURE_WINDOW ) {
				$this->db->query( 'ROLLBACK' );
				return $this->error( 'capture_expired', __( 'The screenshot upload window has expired.', 'wpmastertoolkit' ), 409 );
			}
			$existing = $this->db->get_var( $this->db->prepare( 'SELECT sha256 FROM %i WHERE thread_id = %d', $this->captures, $id ) );
			if ( $existing && ! hash_equals( $existing, $image['sha256'] ) ) {
				$this->db->query( 'ROLLBACK' );
				return $this->conflict();
			}
			if ( ! $existing ) {
				$this->write(
					$this->db->query(
						$this->db->prepare(
							'INSERT INTO %i (thread_id,author,mime,width,height,byte_length,sha256,created_at,data) VALUES (%d,%d,%s,%d,%d,%d,%s,%s,UNHEX(%s))',
							$this->captures,
							$id,
							get_current_user_id(),
							'image/png',
							$image['width'],
							$image['height'],
							strlen( $image['data'] ),
							$image['sha256'],
							current_time( 'mysql', true ),
							bin2hex( $image['data'] )
						)
					)
				);
			}
			$result = $this->present( $thread, true );
			$this->write( $this->db->query( 'COMMIT' ) );
			return $result;
		} catch ( Throwable $exception ) {
			$this->db->query( 'ROLLBACK' );
			return $this->storage_error();
		}
	}

	/**
	 * Authorize the stored context before loading image bytes.
	 *
	 * @param int $id Thread identity.
	 * @return array|WP_Error Image or failure.
	 */
	public function read_capture( $id ) {
		if ( ! self::allowed() ) {
			return $this->error( 'forbidden', __( 'Collaboration access is required.', 'wpmastertoolkit' ), 403 );
		}
		$thread = $this->find( $id );
		if ( is_wp_error( $thread ) ) {
			return $thread;
		}
		$image = $this->db->get_row( $this->db->prepare( 'SELECT data, byte_length FROM %i WHERE thread_id = %d', $this->captures, $id ), ARRAY_A );
		return $image ? $image : $this->error( 'capture_unavailable', __( 'Screenshot unavailable.', 'wpmastertoolkit' ), 404 );
	}

	/**
	 * Authorize before exposing presentation, counts, or pagination metadata.
	 *
	 * @param array $input List filters.
	 * @return array|WP_Error Authorized page.
	 */
	private function listing( $input ) {
		$context = null;
		if ( ! empty( $input['context'] ) ) {
			$context = $this->contexts->resolve( $input['context'] );
			if ( is_wp_error( $context ) ) {
				return $context;
			}
		}
		$page    = min( 10000, max( 1, absint( $input['page'] ?? 1 ) ) );
		$skip    = ( $page - 1 ) * 25;
		$cursor  = 0;
		$visible = array();
		$status  = $input['status'] ?? 'all';
		do {
			$rows      = $this->db->get_results( $this->db->prepare( 'SELECT * FROM %i WHERE id > %d ORDER BY id ASC LIMIT 200', $this->threads, $cursor ), ARRAY_A );
			$row_count = count( $rows );
			foreach ( $rows as $row ) {
				$cursor = (int) $row['id'];
				if ( ( $context && $row['context_key'] !== $context['key'] ) || ( 'open' === $status && $row['resolved'] ) || ( 'resolved' === $status && ! $row['resolved'] ) ) {
					continue;
				}
				$resolved = $this->contexts->resolve( json_decode( $row['context_json'], true ) );
				if ( is_wp_error( $resolved ) ) {
					continue;
				}
				if ( $skip > 0 ) {
					--$skip;
					continue;
				}
				$visible[] = $this->present( $row, false, $resolved );
				if ( count( $visible ) > 25 ) {
					break 2;
				}
			}
		} while ( 200 === $row_count );
		if ( $this->db->last_error ) {
			return $this->storage_error();
		}
		$threads = $this->summaries( array_slice( $visible, 0, 25 ) );
		if ( is_wp_error( $threads ) ) {
			return $threads;
		}
		$names = array();
		foreach ( $threads as &$thread ) {
			$creator = $thread['creator'];
			if ( ! isset( $names[ $creator ] ) ) {
				$user              = $creator ? get_userdata( $creator ) : false;
				$names[ $creator ] = $user && $user->display_name ? $user->display_name : __( 'Removed user', 'wpmastertoolkit' );
			}
			$thread['creator_name'] = $names[ $creator ];
		}
		unset( $thread );
		return array(
			'threads'  => $threads,
			'has_more' => count( $visible ) > 25,
			'page'     => $page,
		);
	}

	/**
	 * Normalize read-only administrator table filters.
	 *
	 * @param array $input Untrusted filter values.
	 * @return array Validated filters.
	 */
	public static function admin_filters( $input ) {
		if ( ! wpmastertoolkit_is_pro() ) {
			$input['coe_kind']   = '';
			$input['coe_author'] = '';
			$input['coe_status'] = 'all';
		}
		$kind   = is_string( $input['coe_kind'] ?? null ) ? $input['coe_kind'] : '';
		$author = is_string( $input['coe_author'] ?? null ) ? $input['coe_author'] : '';
		$status = is_string( $input['coe_status'] ?? null ) ? $input['coe_status'] : 'all';
		$page   = is_scalar( $input['coe_page'] ?? null ) ? (string) $input['coe_page'] : '1';
		return array(
			'coe_kind'   => in_array( $kind, array( 'content', 'post-edit', 'post-list', 'dashboard', 'users', 'media', 'comments', 'home' ), true ) ? $kind : '',
			'coe_author' => ctype_digit( $author ) && strlen( $author ) < 19 ? (string) (int) $author : '',
			'coe_status' => in_array( $status, array( 'open', 'resolved' ), true ) ? $status : 'all',
			'coe_page'   => ctype_digit( $page ) ? min( 10000, max( 1, (int) $page ) ) : 1,
		);
	}

	/**
	 * List administrator-visible discussions without changing the panel API.
	 *
	 * @param array $input Table filters.
	 * @return array|WP_Error Authorized rows, authors, and pagination.
	 */
	public function admin_listing( $input ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $this->error( 'forbidden', __( 'Administrator access is required.', 'wpmastertoolkit' ), 403 );
		}
		$filters  = self::admin_filters( $input );
		$authors  = array();
		$contexts = array();
		$offset   = 0;
		do {
			$rows = $this->db->get_results( $this->db->prepare( 'SELECT DISTINCT creator, context_json FROM %i ORDER BY creator ASC, context_json ASC LIMIT 200 OFFSET %d', $this->threads, $offset ), ARRAY_A );
			if ( $this->db->last_error ) {
				return $this->storage_error();
			}
			foreach ( $rows as $row ) {
				if ( ! array_key_exists( $row['context_json'], $contexts ) ) {
					$contexts[ $row['context_json'] ] = $this->contexts->resolve( json_decode( $row['context_json'], true ) );
				}
				if ( ! is_wp_error( $contexts[ $row['context_json'] ] ) ) {
					$authors[ (int) $row['creator'] ] = (int) $row['creator'];
				}
			}
			$offset   += 200;
			$row_count = count( $rows );
		} while ( 200 === $row_count );

		$kind     = $filters['coe_kind'];
		$prefix   = in_array( $kind, array( 'content', 'post-edit', 'post-list' ), true ) ? $this->db->esc_like( $kind . ':' ) . '%' : $kind;
		$offset   = 0;
		$count    = 0;
		$selected = array();
		$last     = array();
		do {
			$rows = $this->db->get_results(
				$this->db->prepare(
					'SELECT * FROM %i WHERE (%s = \'\' OR context_key LIKE %s) AND (%s = \'\' OR creator = %d) AND (%s = \'all\' OR resolved = %d) ORDER BY updated_at DESC, id DESC LIMIT 200 OFFSET %d',
					$this->threads,
					$kind,
					$prefix,
					$filters['coe_author'],
					(int) $filters['coe_author'],
					$filters['coe_status'],
					'resolved' === $filters['coe_status'] ? 1 : 0,
					$offset
				),
				ARRAY_A
			);
			if ( $this->db->last_error ) {
				return $this->storage_error();
			}
			foreach ( $rows as $row ) {
				$context = $contexts[ $row['context_json'] ] ?? $this->contexts->resolve( json_decode( $row['context_json'], true ) );
				if ( is_wp_error( $context ) ) {
					continue;
				}
				if ( 0 === $count % 25 ) {
					$last = array();
				}
				$thread = $this->present( $row, false, $context );
				$last[] = $thread;
				if ( (int) floor( $count / 25 ) + 1 === $filters['coe_page'] ) {
					$selected[] = $thread;
				}
				++$count;
			}
			$offset   += 200;
			$row_count = count( $rows );
		} while ( 200 === $row_count );
		$pages = max( 1, (int) ceil( $count / 25 ) );
		if ( $filters['coe_page'] > $pages ) {
			$filters['coe_page'] = $pages;
			$selected            = $last;
		}
		$selected = $this->summaries( $selected );
		if ( is_wp_error( $selected ) ) {
			return $selected;
		}
		return array(
			'threads' => $selected,
			'authors' => array_values( $authors ),
			'filters' => $filters,
			'pages'   => $pages,
		);
	}

	/**
	 * Add original-message previews to an already authorized page.
	 *
	 * @param array $threads Authorized page rows.
	 * @return array|WP_Error Enriched rows or storage failure.
	 */
	private function summaries( $threads ) {
		if ( $threads ) {
			$ids          = array_column( $threads, 'id' );
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			$messages     = $this->db->get_results(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Only generated integer placeholders are interpolated; all values are prepared.
				$this->db->prepare( "SELECT thread_id, body FROM %i WHERE id IN (SELECT MIN(id) FROM %i WHERE thread_id IN ($placeholders) GROUP BY thread_id)", array_merge( array( $this->messages, $this->messages ), $ids ) ),
				ARRAY_A
			);
			if ( $this->db->last_error ) {
				return $this->storage_error();
			}
			$previews = array_column( $messages, 'body', 'thread_id' );
			foreach ( $threads as &$thread ) {
				$body              = trim( $previews[ $thread['id'] ] ?? '' );
				$thread['preview'] = '' !== $body ? wp_html_excerpt( $body, 160, '...' ) : __( 'Removed contribution', 'wpmastertoolkit' );
			}
			unset( $thread );
		}
		return $threads;
	}

	/**
	 * Read a thread, optionally acquiring its mutation lock.
	 *
	 * @param int  $id Thread identity.
	 * @param bool $lock Acquire mutation lock.
	 * @return array|WP_Error Authorized stored thread.
	 */
	private function find( $id, $lock = false ) {
		$sql = $this->db->prepare( 'SELECT * FROM %i WHERE id = %d', $this->threads, $id );
		if ( $lock ) {
			$sql .= ' FOR UPDATE';
		}
		$row = $this->db->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared above; only a constant lock clause is appended.
		if ( ! $row ) {
			return $this->error( 'thread_unavailable', __( 'This thread is no longer available. Your draft has not been discarded.', 'wpmastertoolkit' ), 404 );
		}
		$context = $this->contexts->resolve( json_decode( $row['context_json'], true ) );
		return is_wp_error( $context ) ? $this->error( 'thread_unavailable', __( 'This thread is no longer available. Your draft has not been discarded.', 'wpmastertoolkit' ), 404 ) : $row;
	}

	/**
	 * Validate the only approved anchor metadata.
	 *
	 * @param mixed $anchor Untrusted anchor.
	 * @return array|WP_Error Minimal approved anchor.
	 */
	public function anchor( $anchor ) {
		if ( is_array( $anchor ) && array_key_exists( 'version', $anchor ) ) {
			return $this->persistent_anchor( $anchor );
		}
		if ( ! is_array( $anchor ) || ! is_string( $anchor['selector'] ?? null ) || strlen( $anchor['selector'] ) > 700
			|| ! preg_match( '/^[a-zA-Z0-9_# .>:()\-]+$/D', $anchor['selector'] )
			|| ! is_string( $anchor['identity'] ?? null ) || ! preg_match( '/^(?:post-\d+|comment-\d+|user-\d+|menu-item-\d+|attachment-\d+|wpmtk-anchor-[a-zA-Z0-9_-]{1,80}|document-[a-f0-9-]{36})$/D', $anchor['identity'] )
			|| ! is_string( $anchor['tag'] ?? null ) || ! preg_match( '/^[a-z][a-z0-9]{0,15}$/D', $anchor['tag'] )
			|| in_array( $anchor['tag'], array( 'input', 'textarea', 'select', 'script', 'style', 'iframe' ), true ) ) {
			return $this->error( 'invalid_anchor', __( 'Choose a target with a stable record identity or an explicit collaboration anchor.', 'wpmastertoolkit' ), 400 );
		}
		foreach ( array( 'x', 'y' ) as $axis ) {
			if ( ! isset( $anchor[ $axis ] ) || ! is_numeric( $anchor[ $axis ] ) || ! is_finite( (float) $anchor[ $axis ] ) || $anchor[ $axis ] < 0 || $anchor[ $axis ] > 1 ) {
				return $this->error( 'invalid_anchor', __( 'Invalid pin position.', 'wpmastertoolkit' ), 400 );
			}
		}
		return array(
			'selector' => $anchor['selector'],
			'identity' => $anchor['identity'],
			'tag'      => $anchor['tag'],
			'x'        => (float) $anchor['x'],
			'y'        => (float) $anchor['y'],
		);
	}

	/**
	 * Normalize persistent identity metadata without accepting arbitrary CSS.
	 *
	 * @param array $anchor Untrusted anchor.
	 * @return array|WP_Error Approved persistent anchor.
	 */
	private function persistent_anchor( $anchor ) {
		$error = $this->error( 'invalid_anchor', __( 'Choose a distinguishable target or an element with a unique ID.', 'wpmastertoolkit' ), 400 );
		if ( 2 !== $anchor['version'] || ! in_array( $anchor['strategy'] ?? null, array( 'id', 'evidence' ), true )
			|| ! is_string( $anchor['tag'] ?? null ) || ! preg_match( '/^[a-z][a-z0-9]{0,15}$/D', $anchor['tag'] )
			|| in_array( $anchor['tag'], array( 'input', 'textarea', 'select', 'script', 'style', 'iframe' ), true )
			|| ( $anchor['selector'] ?? null ) !== $anchor['tag'] || ! array_key_exists( 'scope', $anchor ) ) {
			return $error;
		}
		$scope = null;
		if ( 'id' === $anchor['strategy'] ) {
			if ( ! $this->anchor_id( $anchor['identity'] ?? null ) || null !== $anchor['scope'] ) {
				return $error;
			}
		} else {
			if ( ! is_string( $anchor['identity'] ?? null ) || ! preg_match( '/^[a-f0-9]{64}$/D', $anchor['identity'] ) ) {
				return $error;
			}
			if ( null !== $anchor['scope'] ) {
				$input = $anchor['scope'];
				if ( ! is_array( $input ) || ! $this->anchor_id( $input['id'] ?? null ) || ! is_string( $input['tag'] ?? null ) || ! preg_match( '/^[a-z][a-z0-9]{0,15}$/D', $input['tag'] ) ) {
					return $error;
				}
				$scope = array(
					'id'  => $input['id'],
					'tag' => $input['tag'],
				);
			}
		}
		foreach ( array( 'x', 'y' ) as $axis ) {
			if ( ! isset( $anchor[ $axis ] ) || ( ! is_int( $anchor[ $axis ] ) && ! is_float( $anchor[ $axis ] ) ) || ! is_finite( (float) $anchor[ $axis ] ) || $anchor[ $axis ] < 0 || $anchor[ $axis ] > 1 ) {
				return $error;
			}
		}
		return array(
			'version'  => 2,
			'strategy' => $anchor['strategy'],
			'scope'    => $scope,
			'selector' => $anchor['tag'],
			'identity' => $anchor['identity'],
			'tag'      => $anchor['tag'],
			'x'        => (float) $anchor['x'],
			'y'        => (float) $anchor['y'],
		);
	}

	/**
	 * Check a bounded, nonempty HTML ID without interpreting it as a selector.
	 *
	 * @param mixed $identity Proposed identity.
	 * @return bool Whether the identity is valid.
	 */
	private function anchor_id( $identity ) {
		return is_string( $identity ) && '' !== $identity && strlen( $identity ) <= 191 && 0 === preg_match( '/[\s\x00-\x1f\x7f]/u', $identity );
	}

	/**
	 * Validate plain text without accepting an empty sanitized result.
	 *
	 * @param mixed $input Untrusted text.
	 * @return string|WP_Error Message or validation failure.
	 */
	private function body( $input ) {
		if ( ! is_string( $input ) || '' === trim( $input ) || strlen( $input ) > 12000 || false !== strpos( $input, "\0" ) ) {
			return $this->error( 'invalid_message', __( 'Enter a message of at most 12,000 bytes.', 'wpmastertoolkit' ), 400 );
		}
		$body = sanitize_textarea_field( $input );
		return '' !== trim( $body ) ? $body : $this->error( 'invalid_message', __( 'Enter a message.', 'wpmastertoolkit' ), 400 );
	}

	/**
	 * Create a thread and its first message atomically.
	 *
	 * @param array $input Context, anchor, and body.
	 * @return array|WP_Error Created discussion.
	 */
	private function create( $input ) {
		$context = $this->contexts->resolve( $input['context'] ?? null );
		$anchor  = $this->anchor( $input['anchor'] ?? null );
		$body    = $this->body( $input['body'] ?? null );
		foreach ( array( $context, $anchor, $body ) as $result ) {
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
		$now = current_time( 'mysql', true );
		$this->write(
			$this->db->insert(
				$this->threads,
				array(
					'context_key'  => $context['key'],
					'context_json' => wp_json_encode( $this->contexts->descriptor( $context ) ),
					'record_type'  => $context['record_type'] ?? '',
					'record_id'    => $context['record_id'] ?? 0,
					'anchor'       => wp_json_encode( $anchor ),
					'creator'      => get_current_user_id(),
					'created_at'   => $now,
					'updated_at'   => $now,
				)
			)
		);
		$id = (int) $this->db->insert_id;
		$this->insert_message( $id, $body );
		return $this->present( $this->find( $id ), true );
	}

	/**
	 * Append a message in the active transaction.
	 *
	 * @param int    $thread_id Parent identity.
	 * @param string $body Validated text.
	 */
	private function insert_message( $thread_id, $body ) {
		$now = current_time( 'mysql', true );
		$this->write(
			$this->db->insert(
				$this->messages,
				array(
					'thread_id'  => $thread_id,
					'author'     => get_current_user_id(),
					'body'       => $body,
					'created_at' => $now,
					'updated_at' => $now,
				)
			)
		);
	}

	/**
	 * Apply a permitted change to a locked discussion.
	 *
	 * @param string $operation Mutation name.
	 * @param array  $input Mutation parameters.
	 * @return array|WP_Error Updated discussion.
	 */
	private function change( $operation, $input ) {
		$thread = $this->find( absint( $input['thread'] ?? 0 ), true );
		if ( is_wp_error( $thread ) ) {
			return $thread;
		}
		$id    = (int) $thread['id'];
		$actor = get_current_user_id();
		$admin = current_user_can( 'manage_options' );
		if ( in_array( $operation, array( 'resolve', 'repin', 'delete_thread' ), true ) && (int) ( $input['version'] ?? 0 ) !== (int) $thread['version'] ) {
			return $this->conflict();
		}
		if ( 'delete_thread' === $operation ) {
			$others = $this->db->get_var( $this->db->prepare( 'SELECT id FROM %i WHERE thread_id = %d AND author <> %d AND author <> 0 LIMIT 1', $this->messages, $id, $actor ) );
			if ( ! $admin && ( (int) $thread['creator'] !== $actor || $others ) ) {
				return $this->error( 'forbidden', __( 'Only an administrator may delete a shared thread.', 'wpmastertoolkit' ), 403 );
			}
			$this->remove( $id );
			return array( 'deleted' => true );
		}
		if ( 'reply' === $operation ) {
			$body = $this->body( $input['body'] ?? null );
			if ( is_wp_error( $body ) ) {
				return $body;
			}
			$this->insert_message( $id, $body );
		} elseif ( in_array( $operation, array( 'edit', 'delete_message' ), true ) ) {
			$message = $this->db->get_row( $this->db->prepare( 'SELECT * FROM %i WHERE id = %d AND thread_id = %d FOR UPDATE', $this->messages, absint( $input['message'] ?? 0 ), $id ), ARRAY_A );
			if ( ! $message || ( ! $admin && (int) $message['author'] !== $actor ) ) {
				return $this->error( 'forbidden', __( 'You cannot change this message.', 'wpmastertoolkit' ), 403 );
			}
			if ( (int) ( $input['version'] ?? 0 ) !== (int) $message['version'] ) {
				return $this->conflict();
			}
			$body = 'edit' === $operation ? $this->body( $input['body'] ?? null ) : '';
			if ( is_wp_error( $body ) ) {
				return $body;
			}
			$this->write(
				$this->db->update(
					$this->messages,
					array(
						'body'       => $body,
						'author'     => 'delete_message' === $operation ? 0 : $message['author'],
						'version'    => (int) $message['version'] + 1,
						'updated_at' => current_time( 'mysql', true ),
					),
					array( 'id' => $message['id'] )
				)
			);
		} else {
			$changes = array();
			if ( 'repin' === $operation ) {
				if ( ! $admin && (int) $thread['creator'] !== $actor ) {
					return $this->error( 'forbidden', __( 'Only the creator or an administrator may re-pin.', 'wpmastertoolkit' ), 403 );
				}
				$anchor = $this->anchor( $input['anchor'] ?? null );
				if ( is_wp_error( $anchor ) ) {
					return $anchor;
				}
				$changes['anchor'] = wp_json_encode( $anchor );
			} else {
				$changes['resolved'] = ! empty( $input['resolved'] ) ? 1 : 0;
			}
			$this->write( $this->db->update( $this->threads, $changes, array( 'id' => $id ) ) );
		}
		$this->write(
			$this->db->update(
				$this->threads,
				array(
					'version'    => (int) $thread['version'] + 1,
					'updated_at' => current_time( 'mysql', true ),
				),
				array( 'id' => $id )
			)
		);
		return $this->present( $this->find( $id ), true );
	}

	/**
	 * Build authorized presentation without storing titles or links.
	 *
	 * @param array|WP_Error $row Stored discussion.
	 * @param bool           $include_messages Include its conversation.
	 * @param array|null     $context Authorized context if already available.
	 * @return array|WP_Error Presentation result.
	 */
	private function present( $row, $include_messages, $context = null ) {
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		$context = null !== $context ? $context : $this->contexts->resolve( json_decode( $row['context_json'], true ) );
		if ( is_wp_error( $context ) ) {
			return $context;
		}
		$result = array(
			'id'          => (int) $row['id'],
			'version'     => (int) $row['version'],
			'creator'     => (int) $row['creator'],
			'resolved'    => (bool) $row['resolved'],
			'anchor'      => json_decode( $row['anchor'], true ),
			'context'     => $this->contexts->descriptor( $context ),
			'context_key' => $context['key'],
			'title'       => $context['title'],
			'url'         => $context['url'],
			'updated_at'  => $row['updated_at'],
		);
		if ( $include_messages ) {
			$capture = self::SCHEMA === get_option( self::OPTION . '_schema' ) ? $this->db->get_row( $this->db->prepare( 'SELECT width,height,byte_length,sha256,created_at FROM %i WHERE thread_id = %d', $this->captures, $row['id'] ), ARRAY_A ) : null;
			if ( $capture ) {
				foreach ( array( 'width', 'height', 'byte_length' ) as $field ) {
					$capture[ $field ] = (int) $capture[ $field ];
				}
			}
			$result['capture']  = $capture;
			$messages           = $this->db->get_results( $this->db->prepare( 'SELECT * FROM %i WHERE thread_id = %d ORDER BY id ASC', $this->messages, $row['id'] ), ARRAY_A );
			$result['messages'] = array_map(
				static function ( $message ) {
					$user = $message['author'] ? get_userdata( $message['author'] ) : false;
					return array(
						'id'         => (int) $message['id'],
						'author'     => (int) $message['author'],
						'name'       => $user ? $user->display_name : __( 'Removed contribution', 'wpmastertoolkit' ),
						'body'       => $message['body'],
						'version'    => (int) $message['version'],
						'created_at' => $message['created_at'],
					);
				},
				$messages
			);
		}
		return $result;
	}

	/**
	 * Export contributions without requiring present collaboration grants.
	 *
	 * @param string $email Privacy request identity.
	 * @param int    $page Export page.
	 * @return array Export result.
	 */
	public function export( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );
		$data = array();
		if ( ! $user || ! get_option( self::OPTION . '_schema' ) ) {
			return array(
				'data' => $data,
				'done' => true,
			);
		}
		$rows = $this->db->get_results( $this->db->prepare( 'SELECT * FROM %i WHERE author = %d ORDER BY id LIMIT 100 OFFSET %d', $this->messages, $user->ID, ( max( 1, (int) $page ) - 1 ) * 100 ), ARRAY_A );
		foreach ( $rows as $row ) {
			$data[] = array(
				'group_id'    => 'wpmastertoolkit-collaboration',
				'group_label' => __( 'Internal collaboration', 'wpmastertoolkit' ),
				'item_id'     => 'collaboration-' . $row['id'],
				'data'        => array(
					array(
						'name'  => __( 'Message', 'wpmastertoolkit' ),
						'value' => $row['body'],
					),
					array(
						'name'  => __( 'Created', 'wpmastertoolkit' ),
						'value' => $row['created_at'],
					),
				),
			);
		}
		$captures = self::SCHEMA === get_option( self::OPTION . '_schema' ) ? $this->db->get_results( $this->db->prepare( 'SELECT thread_id,width,height,created_at FROM %i WHERE author = %d ORDER BY thread_id LIMIT 100 OFFSET %d', $this->captures, $user->ID, ( max( 1, (int) $page ) - 1 ) * 100 ), ARRAY_A ) : array();
		foreach ( $captures as $capture ) {
			$data[] = array(
				'group_id'    => 'wpmastertoolkit-collaboration-captures',
				'group_label' => __( 'Discussion screenshots', 'wpmastertoolkit' ),
				'item_id'     => 'collaboration-capture-' . $capture['thread_id'],
				'data'        => array(
					array(
						'name'  => __( 'Discussion', 'wpmastertoolkit' ),
						'value' => (string) $capture['thread_id'],
					),
					array(
						'name'  => __( 'Dimensions', 'wpmastertoolkit' ),
						'value' => $capture['width'] . ' x ' . $capture['height'],
					),
					array(
						'name'  => __( 'Created', 'wpmastertoolkit' ),
						'value' => $capture['created_at'],
					),
					array(
						'name'  => __( 'Screenshot', 'wpmastertoolkit' ),
						'value' => __( 'Image content requires manual privacy review before export because it may contain other people\'s information.', 'wpmastertoolkit' ),
					),
				),
			);
		}
		return array(
			'data' => $data,
			'done' => count( $rows ) < 100 && count( $captures ) < 100,
		);
	}

	/**
	 * Erasure and deletion preserve other collaborators' contributions.
	 *
	 * @param int $id User identity.
	 * @return bool Whether erasure succeeded.
	 */
	public function erase_user( $id ) {
		$settings = get_option( self::OPTION, array() );
		if ( isset( $settings['users'] ) && is_array( $settings['users'] ) ) {
			$settings['users'] = array_values( array_diff( array_map( 'absint', $settings['users'] ), array( (int) $id ) ) );
			update_option( self::OPTION, $settings, false );
		}
		if ( ! get_option( self::OPTION . '_schema' ) ) {
			return true;
		}
		$this->db->query( 'START TRANSACTION' );
		try {
			$this->write( $this->db->query( $this->db->prepare( 'SELECT id FROM %i WHERE creator = %d ORDER BY id FOR UPDATE', $this->threads, $id ) ) );
			if ( self::SCHEMA === get_option( self::OPTION . '_schema' ) ) {
				$this->write( $this->db->delete( $this->captures, array( 'author' => $id ), array( '%d' ) ) );
			}
			$this->write( $this->db->query( $this->db->prepare( "UPDATE %i SET body = '', author = 0, version = version + 1, created_at = '1970-01-01 00:00:00', updated_at = '1970-01-01 00:00:00' WHERE author = %d", $this->messages, $id ) ) );
			$this->write( $this->db->query( $this->db->prepare( 'UPDATE %i SET creator = 0, version = version + 1 WHERE creator = %d', $this->threads, $id ) ) );
			$this->write( $this->db->query( 'COMMIT' ) );
			return true;
		} catch ( Throwable $exception ) {
			$this->db->query( 'ROLLBACK' );
			return false;
		}
	}

	/**
	 * Remove associated contexts after permanent record deletion.
	 *
	 * @param int $id Deleted post identity.
	 */
	public function delete_record( $id ) {
		if ( ! get_option( self::OPTION . '_schema' ) ) {
			return;
		}
		$ids = $this->db->get_col( $this->db->prepare( "SELECT id FROM %i WHERE record_type = 'post' AND record_id = %d", $this->threads, $id ) );
		foreach ( $ids as $thread_id ) {
			$this->delete_retained( $thread_id );
		}
	}

	/**
	 * Administrator-only bounded cleanup, repeated for further batches.
	 *
	 * @param array $filter Confirmed filters.
	 * @return array|WP_Error Batch outcome.
	 */
	public function cleanup( $filter ) {
		if ( ! current_user_can( 'manage_options' ) || empty( $filter['confirm'] ) ) {
			return $this->error( 'forbidden', __( 'Administrator confirmation is required.', 'wpmastertoolkit' ), 403 );
		}
		$context = '';
		if ( ! empty( $filter['context'] ) ) {
			$resolved = $this->contexts->resolve( $filter['context'] );
			if ( is_wp_error( $resolved ) ) {
				return $resolved;
			}
			$context = $resolved['key'];
		}
		$status  = in_array( $filter['status'] ?? '', array( 'open', 'resolved' ), true ) ? $filter['status'] : 'all';
		$days    = min( 36500, absint( $filter['days'] ?? 0 ) );
		$cutoff  = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$ids     = $this->db->get_col(
			$this->db->prepare(
				'SELECT id FROM %i WHERE (%s = \'\' OR context_key = %s) AND (%s = \'all\' OR resolved = %d) AND (%d = 0 OR updated_at < %s) ORDER BY id LIMIT 100',
				$this->threads,
				$context,
				$context,
				$status,
				'resolved' === $status ? 1 : 0,
				$days,
				$cutoff
			)
		);
		$removed = 0;
		foreach ( $ids as $id ) {
			if ( ! $this->delete_retained( $id ) ) {
				return $this->storage_error();
			}
			++$removed;
		}
		return array(
			'removed'       => $removed,
			'more_possible' => 100 === count( $ids ),
		);
	}

	/**
	 * Serialize retained-data deletion with conversation changes.
	 *
	 * @param int $id Thread identity.
	 * @return bool Whether deletion committed.
	 */
	private function delete_retained( $id ) {
		$this->db->query( 'START TRANSACTION' );
		try {
			$this->db->get_var( $this->db->prepare( 'SELECT id FROM %i WHERE id = %d FOR UPDATE', $this->threads, $id ) );
			$this->remove( $id );
			$this->write( $this->db->query( 'COMMIT' ) );
			return true;
		} catch ( Throwable $exception ) {
			$this->db->query( 'ROLLBACK' );
			return false;
		}
	}

	/**
	 * Remove a conversation inside the caller's transaction.
	 *
	 * @param int $id Thread identity.
	 */
	private function remove( $id ) {
		if ( self::SCHEMA === get_option( self::OPTION . '_schema' ) ) {
			$this->write( $this->db->delete( $this->captures, array( 'thread_id' => $id ), array( '%d' ) ) );
		}
		$this->write( $this->db->delete( $this->messages, array( 'thread_id' => $id ) ) );
		$this->write( $this->db->delete( $this->threads, array( 'id' => $id ) ) );
	}

	/** Remove collaboration tables and settings when the plugin is uninstalled. */
	public function uninstall() {
		$this->db->query( $this->db->prepare( 'DROP TABLE IF EXISTS %i, %i, %i', $this->captures, $this->messages, $this->threads ) );
		delete_option( self::OPTION . '_schema' );
		delete_option( self::OPTION );
	}

	/**
	 * Turn a failed write into transaction rollback.
	 *
	 * @param int|bool $result Database outcome.
	 * @throws RuntimeException On failed write.
	 */
	private function write( $result ) {
		if ( false === $result ) {
			throw new RuntimeException( 'Collaboration storage failed.' );
		}
	}

	/** Return a stale-version failure. */
	private function conflict() {
		return $this->error( 'conflict', __( 'This discussion changed. Refresh it before trying again. Your draft is retained.', 'wpmastertoolkit' ), 409 );
	}

	/** Return a storage failure without database details. */
	private function storage_error() {
		return $this->error( 'storage_failed', __( 'The discussion could not be saved or loaded. Please try again.', 'wpmastertoolkit' ), 500 );
	}

	/**
	 * Build a request failure.
	 *
	 * @param string $code Stable code.
	 * @param string $message Translated description.
	 * @param int    $status HTTP status.
	 * @return WP_Error Failure result.
	 */
	private function error( $code, $message, $status ) {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}
}
