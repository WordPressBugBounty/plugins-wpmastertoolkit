<?php
/**
 * Comment observers for the activity log.
 *
 * @package WPMasterToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Observe persisted comment changes without retaining bodies or contact details.
 */
class WPMastertoolkit_Activity_Log_Comments implements WPMastertoolkit_Activity_Log_Provider {

	/**
	 * Declare comment events and their collection policy.
	 *
	 * @param bool $translate Whether to translate labels.
	 * @return array Event groups.
	 */
	public static function event_groups( $translate = false ) {
		return array(
			'comments' => array(
				'label'           => $translate ? __( 'Comments', 'wpmastertoolkit' ) : '',
				'pro'             => false,
				'default_enabled' => true,
				'order'           => 30,
				'event_order'     => 0,
				'events'          => self::labels( $translate ),
			),
		);
	}

	/**
	 * Preserve historical generic edits without recording new ones.
	 *
	 * @param bool $translate Whether to translate labels.
	 * @return array Historical labels.
	 */
	public static function historical_labels( $translate = true ) {
		return array( 'comment.updated' => $translate ? __( 'Comment updated', 'wpmastertoolkit' ) : '' );
	}

	/**
	 * Describe comment-owned context fields.
	 *
	 * @param string $event Event ID.
	 * @return array Detail labels.
	 */
	public static function detail_labels( $event ) {
		return array(
			'post_type'        => __( 'Post type', 'wpmastertoolkit' ),
			'post_id'          => __( 'Post ID', 'wpmastertoolkit' ),
			'parent_id'        => __( 'Parent comment ID', 'wpmastertoolkit' ),
			'author_id'        => __( 'Comment author user ID', 'wpmastertoolkit' ),
			'author'           => __( 'Comment author', 'wpmastertoolkit' ),
			'date'             => __( 'Comment date', 'wpmastertoolkit' ),
			'status'           => __( 'Comment status', 'wpmastertoolkit' ),
			'comment_type'     => __( 'Comment type', 'wpmastertoolkit' ),
			'before_author_id' => __( 'Previous author user ID', 'wpmastertoolkit' ),
			'after_author_id'  => __( 'New author user ID', 'wpmastertoolkit' ),
		);
	}

	/**
	 * Pre-update snapshots keyed by blog and comment ID.
	 *
	 * @var array
	 */
	private $before = array();

	/**
	 * Register completed-operation observers.
	 */
	public function register_hooks() {
		add_action( 'wp_insert_comment', array( $this, 'created' ), 10, 2 );
		add_filter( 'wp_update_comment_data', array( $this, 'capture_update' ), PHP_INT_MAX, 2 );
		add_action( 'edit_comment', array( $this, 'edited' ) );
		add_action( 'transition_comment_status', array( $this, 'status_changed' ), 10, 3 );
		add_action( 'deleted_comment', array( $this, 'deleted' ), 10, 2 );
	}

	/**
	 * Get the independently selectable comment events.
	 *
	 * @param bool $translate Whether to translate labels.
	 * @return array Translated event labels.
	 */
	public static function labels( $translate = true ) {
		return array(
			'comment.created'              => $translate ? __( 'Comment created', 'wpmastertoolkit' ) : '',
			'comment.replied'              => $translate ? __( 'Comment reply created', 'wpmastertoolkit' ) : '',
			'comment.content_changed'      => $translate ? __( 'Comment content changed', 'wpmastertoolkit' ) : '',
			'comment.author_changed'       => $translate ? __( 'Comment author changed', 'wpmastertoolkit' ) : '',
			'comment.author_email_changed' => $translate ? __( 'Comment author email changed', 'wpmastertoolkit' ) : '',
			'comment.author_url_changed'   => $translate ? __( 'Comment author URL changed', 'wpmastertoolkit' ) : '',
			'comment.parent_changed'       => $translate ? __( 'Comment parent changed', 'wpmastertoolkit' ) : '',
			'comment.post_changed'         => $translate ? __( 'Comment associated post changed', 'wpmastertoolkit' ) : '',
			'comment.date_changed'         => $translate ? __( 'Comment date changed', 'wpmastertoolkit' ) : '',
			'comment.type_changed'         => $translate ? __( 'Comment type changed', 'wpmastertoolkit' ) : '',
			'comment.approved'             => $translate ? __( 'Comment approved', 'wpmastertoolkit' ) : '',
			'comment.unapproved'           => $translate ? __( 'Comment unapproved', 'wpmastertoolkit' ) : '',
			'comment.spammed'              => $translate ? __( 'Comment marked as spam', 'wpmastertoolkit' ) : '',
			'comment.unspammed'            => $translate ? __( 'Comment removed from spam', 'wpmastertoolkit' ) : '',
			'comment.trashed'              => $translate ? __( 'Comment moved to trash', 'wpmastertoolkit' ) : '',
			'comment.restored'             => $translate ? __( 'Comment restored from trash', 'wpmastertoolkit' ) : '',
			'comment.deleted'              => $translate ? __( 'Comment permanently deleted', 'wpmastertoolkit' ) : '',
			'comment.status_changed'       => $translate ? __( 'Comment status changed', 'wpmastertoolkit' ) : '',
		);
	}

	/**
	 * Preserve existing choices when splitting generic comment events.
	 *
	 * @param array $preferences Saved event preferences.
	 * @return array Migrated preferences.
	 */
	public static function migrate_preferences( $preferences ) {
		foreach ( array_keys( self::labels( false ) ) as $event ) {
			if ( in_array( $event, array( 'comment.created', 'comment.status_changed' ), true ) ) {
				continue;
			}
			if ( 'comment.replied' === $event ) {
				$legacy = 'comment.created';
			} else {
				$legacy = false !== strpos( $event, '_changed' ) ? 'comment.updated' : 'comment.status_changed';
			}
			if ( ! isset( $preferences[ $event ] ) && isset( $preferences[ $legacy ] ) && in_array( $preferences[ $legacy ], array( '0', '1' ), true ) ) {
				$preferences[ $event ] = $preferences[ $legacy ];
			}
		}
		unset( $preferences['comment.updated'] );
		return $preferences;
	}

	/**
	 * Build independent edit events; status changes belong to the transition hook.
	 *
	 * @param WP_Comment $before Previous comment.
	 * @param WP_Comment $after Persisted comment.
	 * @return array Event details.
	 */
	public static function changes( $before, $after ) {
		$events = array();
		$fields = array(
			'comment_content'      => 'comment.content_changed',
			'comment_author'       => 'comment.author_changed',
			'comment_author_email' => 'comment.author_email_changed',
			'comment_author_url'   => 'comment.author_url_changed',
			'comment_parent'       => 'comment.parent_changed',
			'comment_post_ID'      => 'comment.post_changed',
			'comment_date'         => 'comment.date_changed',
			'comment_type'         => 'comment.type_changed',
		);
		foreach ( $fields as $field => $event ) {
			$old = (string) $before->$field;
			$new = (string) $after->$field;
			if ( $old === $new ) {
				continue;
			}
			if ( 'comment_content' === $field ) {
				$events[ $event ] = array(
					'before' => strlen( $old ),
					'after'  => strlen( $new ),
					'unit'   => 'bytes',
				);
			} elseif ( in_array( $field, array( 'comment_author_email', 'comment_author_url' ), true ) ) {
				$events[ $event ] = array(
					'before' => 'redacted',
					'after'  => 'redacted',
				);
			} else {
				$events[ $event ] = array(
					'before' => $old,
					'after'  => $new,
				);
			}
		}
		if ( (int) $before->user_id !== (int) $after->user_id ) {
			$events['comment.author_changed']['before_author_id'] = (int) $before->user_id;
			$events['comment.author_changed']['after_author_id']  = (int) $after->user_id;
		}
		return $events;
	}

	/**
	 * Normalize core status aliases for comparisons.
	 *
	 * @param string|int $status Status.
	 * @return string Status name.
	 */
	public static function status_name( $status ) {
		$aliases = array(
			'0'       => 'unapproved',
			'hold'    => 'unapproved',
			'1'       => 'approved',
			'approve' => 'approved',
		);
		return $aliases[ (string) $status ] ?? (string) $status;
	}

	/**
	 * Select a single meaningful moderation event.
	 *
	 * @param string $before Previous status.
	 * @param string $after New status.
	 * @return string Event ID or empty for unchanged/deleted status.
	 */
	public static function status_event( $before, $after ) {
		$before = self::status_name( $before );
		$after  = self::status_name( $after );
		if ( $before === $after || in_array( $after, array( 'delete', 'deleted' ), true ) ) {
			return '';
		}
		if ( 'trash' === $after ) {
			return 'comment.trashed';
		}
		if ( 'spam' === $after ) {
			return 'comment.spammed';
		}
		if ( 'trash' === $before ) {
			return 'comment.restored';
		}
		if ( 'spam' === $before ) {
			return 'comment.unspammed';
		}
		$events = array(
			'approved'   => 'comment.approved',
			'unapproved' => 'comment.unapproved',
		);
		return $events[ $after ] ?? 'comment.status_changed';
	}

	/**
	 * Observe creation through all WordPress insertion APIs, including guests.
	 *
	 * @param int        $comment_id Comment ID.
	 * @param WP_Comment $comment Inserted comment.
	 */
	public function created( $comment_id, $comment ) {
		if ( $this->eligible( $comment ) ) {
			$this->emit( $comment->comment_parent ? 'comment.replied' : 'comment.created', $comment );
		}
	}

	/**
	 * Snapshot persisted fields without modifying the update filter input.
	 *
	 * @param array|WP_Error $data Proposed data.
	 * @param array          $old Previous data provided by core.
	 * @return array|WP_Error Unchanged data.
	 */
	public function capture_update( $data, $old ) {
		if ( ! is_array( $data ) || empty( $old['comment_ID'] ) ) {
			return $data;
		}
		$comment = get_comment( (int) $old['comment_ID'] );
		if ( $this->eligible( $comment ) ) {
			$key                  = get_current_blog_id() . ':' . $comment->comment_ID;
			$this->before[ $key ] = array(
				'comment' => clone $comment,
				'actor'   => $this->actor_id(),
			);
		}
		return $data;
	}

	/**
	 * Emit only effective edits after WordPress has invalidated the comment cache.
	 *
	 * @param int $comment_id Comment ID.
	 */
	public function edited( $comment_id ) {
		$key      = get_current_blog_id() . ':' . $comment_id;
		$snapshot = $this->before[ $key ] ?? null;
		unset( $this->before[ $key ] );
		$comment = get_comment( $comment_id );
		if ( ! $snapshot || ! $this->eligible( $comment ) ) {
			return;
		}
		foreach ( self::changes( $snapshot['comment'], $comment ) as $event => $details ) {
			$this->emit( $event, $comment, $details, $snapshot['actor'] );
		}
	}

	/**
	 * Verify the persisted transition before emitting its classification.
	 *
	 * @param string     $new_status New status.
	 * @param string     $old_status Previous status.
	 * @param WP_Comment $comment Comment supplied by core.
	 */
	public function status_changed( $new_status, $old_status, $comment ) {
		if ( ! $comment instanceof WP_Comment ) {
			return;
		}
		$persisted = get_comment( $comment->comment_ID );
		$event     = self::status_event( $old_status, $new_status );
		if ( $event && $this->eligible( $persisted ) && self::status_name( $persisted->comment_approved ) === self::status_name( $new_status ) ) {
			$this->emit(
				$event,
				$persisted,
				array(
					'before' => self::status_name( $old_status ),
					'after'  => self::status_name( $new_status ),
				)
			);
		}
	}

	/**
	 * Log permanent deletion using core's supplied object, not a missing DB row.
	 *
	 * @param int        $comment_id Deleted comment ID.
	 * @param WP_Comment $comment Deleted comment snapshot.
	 */
	public function deleted( $comment_id, $comment ) {
		unset( $this->before[ get_current_blog_id() . ':' . $comment_id ] );
		if ( $this->eligible( $comment ) && (int) $comment_id === (int) $comment->comment_ID ) {
			$this->emit(
				'comment.deleted',
				$comment,
				array(
					'before' => self::status_name( $comment->comment_approved ),
					'after'  => 'deleted',
				)
			);
		}
	}

	/**
	 * Keep WooCommerce order notes out of ordinary comment activity.
	 *
	 * @param mixed $comment Comment.
	 * @return bool Whether the comment belongs to this observer.
	 */
	private function eligible( $comment ) {
		if ( ! $comment instanceof WP_Comment || 'order_note' === $comment->comment_type ) {
			return false;
		}
		$post = get_post( $comment->comment_post_ID );
		return ! $post || ! in_array( $post->post_type, array( 'shop_order', 'shop_order_refund' ), true );
	}

	/**
	 * Attribute cron moderation and cleanup to the system.
	 *
	 * @return int Actor ID.
	 */
	private function actor_id() {
		return wp_doing_cron() || doing_action( 'wp_scheduled_delete' ) ? 0 : get_current_user_id();
	}

	/**
	 * Emit bounded comment and associated post context through the existing writer.
	 *
	 * @param string     $event Event ID.
	 * @param WP_Comment $comment Comment.
	 * @param array      $details Event-specific details.
	 * @param int|null   $actor Captured actor ID.
	 */
	private function emit( $event, $comment, $details = array(), $actor = null ) {
		$actor                   = null === $actor ? $this->actor_id() : $actor;
		$details['post_id']      = (int) $comment->comment_post_ID;
		$details['parent_id']    = (int) $comment->comment_parent;
		$details['author_id']    = (int) $comment->user_id;
		$details['author']       = $comment->comment_author;
		$details['date']         = $comment->comment_date;
		$details['status']       = self::status_name( $comment->comment_approved );
		$details['comment_type'] = $comment->comment_type;
		$details['source']       = defined( 'WP_CLI' ) && WP_CLI ? 'cli' : ( $actor ? 'user' : 'system' );
		$post                    = get_post( $comment->comment_post_ID );
		if ( $post ) {
			$details['title']     = $post->post_title;
			$details['post_type'] = $post->post_type;
		}
		do_action( 'wpmastertoolkit/activity_log/event', $event, $actor, 'comment', (int) $comment->comment_ID, $details ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Preserve the existing public event action.
	}

	/**
	 * Validate comment metadata independently of the content observer's schema.
	 *
	 * @param string $event Event ID.
	 * @param mixed  $details Supplied details.
	 * @return array Bounded allowlisted details.
	 */
	public static function normalize_details( $event, $details ) {
		if ( ! isset( self::labels( false )[ $event ] ) || ! is_array( $details ) ) {
			return array();
		}
		$normalized = array();
		$numeric    = in_array( $event, array( 'comment.content_changed', 'comment.parent_changed', 'comment.post_changed' ), true );
		foreach ( array( 'before', 'after' ) as $field ) {
			$value = $details[ $field ] ?? null;
			if ( $numeric && is_scalar( $value ) && ctype_digit( (string) $value ) ) {
				$normalized[ $field ] = absint( $value );
			} elseif ( in_array( $event, array( 'comment.author_email_changed', 'comment.author_url_changed' ), true ) ) {
				$normalized[ $field ] = 'redacted';
			} elseif ( ! $numeric && is_string( $value ) && ! in_array( $event, array( 'comment.created', 'comment.replied' ), true ) ) {
				$normalized[ $field ] = self::bounded_text( $value );
			}
		}
		$ids = array( 'post_id', 'parent_id', 'author_id' );
		if ( 'comment.author_changed' === $event ) {
			$ids = array_merge( $ids, array( 'before_author_id', 'after_author_id' ) );
		}
		foreach ( $ids as $field ) {
			if ( isset( $details[ $field ] ) && is_scalar( $details[ $field ] ) && ctype_digit( (string) $details[ $field ] ) ) {
				$normalized[ $field ] = absint( $details[ $field ] );
			}
		}
		foreach ( array( 'author', 'date', 'status', 'comment_type', 'title', 'post_type' ) as $field ) {
			if ( isset( $details[ $field ] ) && is_string( $details[ $field ] ) ) {
				$normalized[ $field ] = self::bounded_text( $details[ $field ] );
			}
		}
		if ( 'comment.content_changed' === $event ) {
			$normalized['unit'] = 'bytes';
		}
		if ( isset( $details['source'] ) && in_array( $details['source'], array( 'user', 'system', 'cli' ), true ) ) {
			$normalized['source'] = $details['source'];
		}
		return $normalized;
	}

	/**
	 * Bound text using WordPress's UTF-8 compatible fallback when necessary.
	 *
	 * @param string $value Text.
	 * @return string Sanitized text.
	 */
	private static function bounded_text( $value ) {
		$value = sanitize_text_field( $value );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 160, 'UTF-8' ) : _mb_substr( $value, 0, 160, 'UTF-8' );
	}
}
