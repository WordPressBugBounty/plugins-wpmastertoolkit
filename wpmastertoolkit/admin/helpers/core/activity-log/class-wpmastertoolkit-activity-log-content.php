<?php
/**
 * Content observers for the activity log.
 *
 * @package WPMasterToolkit
 */

// phpcs:disable WordPress.NamingConventions.ValidHookName.UseUnderscores -- Preserve the module's existing public event action.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Observe completed content changes without storing private bodies or credentials.
 */
class WPMastertoolkit_Activity_Log_Content implements WPMastertoolkit_Activity_Log_Provider {

	/**
	 * Register content observers.
	 */
	public function register_hooks() {
		add_action( 'save_post', array( $this, 'post_saved' ), 10, 3 );
		add_action( 'pre_post_update', array( $this, 'before_post' ) );
		add_action( 'transition_post_status', array( $this, 'capture_transition' ), 10, 3 );
		add_action( 'before_delete_post', array( $this, 'before_delete' ) );
		add_action( 'deleted_post', array( $this, 'post_deleted' ), 10, 2 );
		add_action( 'trashed_post', array( $this, 'post_trashed' ), 10, 2 );
		add_action( 'untrashed_post', array( $this, 'post_restored' ), 10, 2 );
		add_action( 'add_term_relationship', array( $this, 'before_relationship' ), 10, 3 );
		add_action( 'delete_term_relationships', array( $this, 'before_relationship' ), 10, 3 );
		add_action( 'set_object_terms', array( $this, 'relationships_saved' ), 10, 4 );
		add_action( 'shutdown', array( $this, 'flush_pending_relationships' ) );
		add_action( 'edit_terms', array( $this, 'before_term' ), 10, 2 );
		add_action( 'edited_term', array( $this, 'term_saved' ), 10, 3 );
		add_action( 'created_term', array( $this, 'term_created' ), 10, 3 );
		add_action( 'delete_term', array( $this, 'term_deleted' ), 10, 4 );
		add_action( 'add_post_meta', array( $this, 'before_meta_add' ), 10, 2 );
		add_action( 'update_post_meta', array( $this, 'before_meta_update' ), 10, 3 );
		add_action( 'delete_post_meta', array( $this, 'before_meta_delete' ), 10, 3 );
		add_action( 'added_post_meta', array( $this, 'meta_saved' ), 10, 3 );
		add_action( 'updated_post_meta', array( $this, 'meta_saved' ), 10, 3 );
		add_action( 'deleted_post_meta', array( $this, 'meta_saved' ), 10, 3 );
		add_action( 'updated_option', array( $this, 'sticky_changed' ), 10, 3 );
		add_action( 'added_option', array( $this, 'sticky_added' ), 10, 2 );
		add_action( 'edit_form_top', array( $this, 'editor_opened' ) );
		add_filter( 'block_editor_settings_all', array( $this, 'block_editor_opened' ), 10, 2 );
		add_action( 'wp_head', array( $this, 'post_viewed' ) );
		add_filter( 'the_password_form_incorrect_password', array( $this, 'password_rejected' ), 10, 2 );
		add_filter( 'the_password_form', array( $this, 'password_form' ), 10, 2 );
	}

	/**
	 * Declare content policies independently of translations and integrations.
	 *
	 * @param bool $translate Whether to translate labels.
	 * @return array Event groups.
	 */
	public static function event_groups( $translate = false ) {
		$changes = self::change_labels( $translate );
		return array(
			'content'        => array(
				'label'           => $translate ? __( 'Content', 'wpmastertoolkit' ) : '',
				'pro'             => false,
				'default_enabled' => true,
				'order'           => 10,
				'event_order'     => 0,
				'event_positions' => array_combine( array_keys( $changes ), range( 11, 10 + count( $changes ) ) ),
				'events'          => array(
					'post.created'  => $translate ? __( 'Post created', 'wpmastertoolkit' ) : '',
					'post.deleted'  => $translate ? __( 'Post deleted', 'wpmastertoolkit' ) : '',
					'post.trashed'  => $translate ? __( 'Post trashed', 'wpmastertoolkit' ) : '',
					'post.restored' => $translate ? __( 'Post restored', 'wpmastertoolkit' ) : '',
					'term.created'  => $translate ? __( 'Term created', 'wpmastertoolkit' ) : '',
					'term.deleted'  => $translate ? __( 'Term deleted', 'wpmastertoolkit' ) : '',
				) + $changes,
			),
			'content_access' => array(
				'label'           => $translate ? __( 'Content access', 'wpmastertoolkit' ) : '',
				'pro'             => false,
				'default_enabled' => false,
				'order'           => 20,
				'event_order'     => 0,
				'events'          => self::access_labels( $translate ),
			),
		);
	}

	/**
	 * Preserve retired update labels for historical records only.
	 *
	 * @param bool $translate Whether to translate labels.
	 * @return array Historical labels.
	 */
	public static function historical_labels( $translate = true ) {
		return array(
			'post.updated' => $translate ? __( 'Post updated', 'wpmastertoolkit' ) : '',
			'term.updated' => $translate ? __( 'Term updated', 'wpmastertoolkit' ) : '',
		);
	}

	/**
	 * Pending snapshots scoped to blog and object.
	 *
	 * @var array
	 */
	private $relationships = array();

	/**
	 * Previous term primitives.
	 *
	 * @var array
	 */
	private $terms = array();

	/**
	 * Pending metadata snapshots.
	 *
	 * @var array
	 */
	private $metadata = array();

	/**
	 * Objects undergoing deletion.
	 *
	 * @var array
	 */
	private $deleting = array();

	/**
	 * Access events already observed in this request.
	 *
	 * @var array
	 */
	private $access = array();

	/**
	 * Previous canonical permalinks.
	 *
	 * @var array
	 */
	private $permalinks = array();

	/**
	 * Pre-save post snapshots scoped to blog and post ID.
	 *
	 * @var array
	 */
	private $posts = array();

	/**
	 * Get translated mutation labels.
	 *
	 * @param bool $translate Whether to translate labels.
	 * @return array Labels keyed by event.
	 */
	public static function change_labels( $translate = true ) {
		return array(
			'post.author_changed'         => $translate ? __( 'Post author changed', 'wpmastertoolkit' ) : '',
			'post.parent_changed'         => $translate ? __( 'Post parent changed', 'wpmastertoolkit' ) : '',
			'post.title_changed'          => $translate ? __( 'Post title changed', 'wpmastertoolkit' ) : '',
			'post.content_changed'        => $translate ? __( 'Post content changed', 'wpmastertoolkit' ) : '',
			'post.excerpt_changed'        => $translate ? __( 'Post excerpt changed', 'wpmastertoolkit' ) : '',
			'post.permalink_changed'      => $translate ? __( 'Post permalink changed', 'wpmastertoolkit' ) : '',
			'post.visibility_changed'     => $translate ? __( 'Post visibility changed', 'wpmastertoolkit' ) : '',
			'post.password_changed'       => $translate ? __( 'Post password changed', 'wpmastertoolkit' ) : '',
			'post.date_changed'           => $translate ? __( 'Post publication date changed', 'wpmastertoolkit' ) : '',
			'post.comments_changed'       => $translate ? __( 'Post comments setting changed', 'wpmastertoolkit' ) : '',
			'post.pings_changed'          => $translate ? __( 'Post pingbacks setting changed', 'wpmastertoolkit' ) : '',
			'post.published'              => $translate ? __( 'Post published', 'wpmastertoolkit' ) : '',
			'post.submitted_for_review'   => $translate ? __( 'Post submitted for review', 'wpmastertoolkit' ) : '',
			'post.scheduled'              => $translate ? __( 'Post scheduled', 'wpmastertoolkit' ) : '',
			'post.status_changed'         => $translate ? __( 'Post status changed', 'wpmastertoolkit' ) : '',
			'post.categories_changed'     => $translate ? __( 'Post categories changed', 'wpmastertoolkit' ) : '',
			'post.tags_added'             => $translate ? __( 'Post tags added', 'wpmastertoolkit' ) : '',
			'post.tags_removed'           => $translate ? __( 'Post tags removed', 'wpmastertoolkit' ) : '',
			'post.terms_changed'          => $translate ? __( 'Post taxonomy terms changed', 'wpmastertoolkit' ) : '',
			'post.template_changed'       => $translate ? __( 'Post template changed', 'wpmastertoolkit' ) : '',
			'post.featured_image_changed' => $translate ? __( 'Post featured image changed', 'wpmastertoolkit' ) : '',
			'post.stuck'                  => $translate ? __( 'Post made sticky', 'wpmastertoolkit' ) : '',
			'post.unstuck'                => $translate ? __( 'Post no longer sticky', 'wpmastertoolkit' ) : '',
			'post.lock_taken_over'        => $translate ? __( 'Post editor lock taken over', 'wpmastertoolkit' ) : '',
			'term.name_changed'           => $translate ? __( 'Term name changed', 'wpmastertoolkit' ) : '',
			'term.slug_changed'           => $translate ? __( 'Term slug changed', 'wpmastertoolkit' ) : '',
			'term.description_changed'    => $translate ? __( 'Term description changed', 'wpmastertoolkit' ) : '',
			'term.parent_changed'         => $translate ? __( 'Term parent changed', 'wpmastertoolkit' ) : '',
		);
	}

	/**
	 * Get opt-in access labels.
	 *
	 * @param bool $translate Whether to translate labels.
	 * @return array Labels keyed by event.
	 */
	public static function access_labels( $translate = true ) {
		return array(
			'post.editor_opened'     => $translate ? __( 'Post opened in editor', 'wpmastertoolkit' ) : '',
			'post.viewed'            => $translate ? __( 'Post viewed', 'wpmastertoolkit' ) : '',
			'post.password_accessed' => $translate ? __( 'Password-protected post accessed', 'wpmastertoolkit' ) : '',
			'post.password_rejected' => $translate ? __( 'Password-protected post access rejected', 'wpmastertoolkit' ) : '',
		);
	}

	/**
	 * Carry legacy update preferences over only when no granular choice exists.
	 *
	 * @param array $preferences Saved event preferences.
	 * @return array Migrated preferences.
	 */
	public static function migrate_preferences( $preferences ) {
		foreach ( array_keys( self::change_labels( false ) ) as $event ) {
			$legacy = 0 === strpos( $event, 'term.' ) ? 'term.updated' : 'post.updated';
			if ( ! isset( $preferences[ $event ] ) && isset( $preferences[ $legacy ] ) && in_array( $preferences[ $legacy ], array( '0', '1' ), true ) ) {
				$preferences[ $event ] = $preferences[ $legacy ];
			}
		}
		unset( $preferences['post.updated'], $preferences['term.updated'] );
		return $preferences;
	}

	/**
	 * Normalize only known content details, never arbitrary metadata.
	 *
	 * @param string $event Event ID.
	 * @param mixed  $details Supplied details.
	 * @return array Bounded details.
	 */
	public static function normalize_details( $event, $details ) {
		$known = self::change_labels( false ) + self::access_labels( false ) + array_fill_keys( array( 'post.created', 'post.deleted', 'post.trashed', 'post.restored', 'term.created', 'term.deleted' ), '' );
		if ( ! isset( $known[ $event ] ) || ! is_array( $details ) ) {
			return array();
		}
		$normalized = array();
		$numeric    = in_array( $event, array( 'post.author_changed', 'post.parent_changed', 'post.content_changed', 'post.excerpt_changed', 'post.featured_image_changed', 'post.lock_taken_over', 'term.parent_changed', 'term.description_changed' ), true );
		$term_set   = in_array( $event, array( 'post.categories_changed', 'post.tags_added', 'post.tags_removed', 'post.terms_changed' ), true );
		$access     = isset( self::access_labels( false )[ $event ] );
		foreach ( array( 'before', 'after' ) as $field ) {
			$value = $details[ $field ] ?? null;
			if ( $access ) {
				continue;
			}
			if ( $numeric && is_scalar( $value ) && ctype_digit( (string) $value ) ) {
				$normalized[ $field ] = absint( $value );
			} elseif ( $term_set && is_array( $value ) ) {
				$ids                             = array_values( array_unique( array_map( 'absint', array_filter( $value, 'is_numeric' ) ) ) );
				$normalized[ $field ]            = array_slice( $ids, 0, 30 );
				$total                           = isset( $details[ $field . '_total' ] ) && is_int( $details[ $field . '_total' ] ) ? $details[ $field . '_total' ] : count( $ids );
				$normalized[ $field . '_total' ] = max( count( $ids ), $total );
			} elseif ( ! $numeric && ! $term_set && is_string( $value ) ) {
				$normalized[ $field ] = 'post.password_changed' === $event ? 'redacted' : self::bounded_text( $value );
			}
		}
		$context_fields = array( 'post_type', 'taxonomy', 'title' );
		if ( in_array( $event, array( 'post.author_changed', 'post.parent_changed', 'post.lock_taken_over', 'term.parent_changed' ), true ) ) {
			$context_fields = array_merge( $context_fields, array( 'before_name', 'after_name' ) );
		}
		foreach ( $context_fields as $field ) {
			if ( isset( $details[ $field ] ) && is_string( $details[ $field ] ) ) {
				$normalized[ $field ] = self::bounded_text( $details[ $field ] );
			}
		}
		if ( in_array( $event, array( 'post.content_changed', 'post.excerpt_changed', 'term.description_changed' ), true ) && isset( $details['unit'] ) && 'bytes' === $details['unit'] ) {
			$normalized['unit'] = 'bytes';
		}
		if ( isset( $details['source'] ) && in_array( $details['source'], array( 'user', 'system', 'cli' ), true ) ) {
			$normalized['source'] = $details['source'];
		}
		return $normalized;
	}

	/**
	 * Bound sanitized UTF-8 text without requiring the mbstring extension.
	 *
	 * @param string $value Text.
	 * @return string Bounded text.
	 */
	private static function bounded_text( $value ) {
		$value = sanitize_text_field( $value );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 160, 'UTF-8' ) : _mb_substr( $value, 0, 160, 'UTF-8' );
	}

	/**
	 * Translate content-owned detail keys for the viewer.
	 *
	 * @param string $event Event ID.
	 * @return array Detail labels.
	 */
	public static function detail_labels( $event ) {
		return array(
			'before'       => __( 'Before', 'wpmastertoolkit' ),
			'after'        => __( 'After', 'wpmastertoolkit' ),
			'before_total' => __( 'Previous term count', 'wpmastertoolkit' ),
			'after_total'  => __( 'New term count', 'wpmastertoolkit' ),
			'post_type'    => __( 'Post type', 'wpmastertoolkit' ),
			'taxonomy'     => __( 'Taxonomy', 'wpmastertoolkit' ),
			'title'        => __( 'Title or name', 'wpmastertoolkit' ),
			'before_name'  => __( 'Previous name', 'wpmastertoolkit' ),
			'after_name'   => __( 'New name', 'wpmastertoolkit' ),
			'unit'         => __( 'Unit', 'wpmastertoolkit' ),
			'source'       => __( 'Source', 'wpmastertoolkit' ),
		);
	}

	/**
	 * Compare the persisted post with WordPress's pre-save snapshot.
	 *
	 * @param int          $post_id Post ID.
	 * @param WP_Post      $post Saved post.
	 * @param bool         $update Whether this is an update.
	 * @param WP_Post|null $before Previous post.
	 */
	public function post_saved( $post_id, $post, $update, $before = null ) {
		$key    = $this->object_key( $post_id );
		$before = $before ?? ( $this->posts[ $key ] ?? null );
		unset( $this->posts[ $key ] );
		if ( ! $this->eligible_post( $post ) ) {
			return;
		}
		if ( ! $update || ( $before && 'auto-draft' === $before->post_status ) ) {
			$this->emit( 'post.created', $post, array( 'after' => $post->post_status ) );
			unset( $this->permalinks[ $this->object_key( $post_id ) ] );
			return;
		}
		if ( ! $before || 'trash' === $post->post_status || 'trash' === $before->post_status ) {
			unset( $this->permalinks[ $this->object_key( $post_id ) ] );
			return;
		}
		$changes = self::post_changes( $before, $post );
		$key     = $this->object_key( $post_id );
		if ( isset( $this->permalinks[ $key ], $changes['post.permalink_changed'] ) && ! in_array( $post->post_status, array( 'draft', 'pending' ), true ) ) {
			$changes['post.permalink_changed'] = array(
				'before' => $this->permalinks[ $key ],
				'after'  => $this->safe_permalink( $post_id ),
			);
		}
		unset( $this->permalinks[ $key ] );
		foreach ( $changes as $event => $details ) {
			if ( in_array( $event, array( 'post.author_changed', 'post.parent_changed' ), true ) ) {
				$details['before_name'] = $this->object_name( $event, (int) $details['before'] );
				$details['after_name']  = $this->object_name( $event, (int) $details['after'] );
			}
			$this->emit( $event, $post, $details );
		}
		$this->flush_relationships();
	}

	/**
	 * Capture the canonical URL before WordPress changes the post.
	 *
	 * @param int $post_id Post ID.
	 */
	public function before_post( $post_id ) {
		$post = get_post( $post_id );
		if ( $this->eligible_post( $post ) ) {
			$key                      = $this->object_key( $post_id );
			$this->posts[ $key ]      = clone $post;
			$this->permalinks[ $key ] = $this->safe_permalink( $post_id );
		}
	}

	/**
	 * Capture status-only publication paths that bypass pre_post_update.
	 *
	 * @param string  $new_status Persisted status.
	 * @param string  $old_status Previous status.
	 * @param WP_Post $post Persisted post.
	 */
	public function capture_transition( $new_status, $old_status, $post ) {
		if ( 'new' === $old_status || $new_status === $old_status || ! $this->eligible_post( $post ) ) {
			return;
		}
		$key = $this->object_key( $post->ID );
		if ( ! isset( $this->posts[ $key ] ) ) {
			$this->posts[ $key ]              = clone $post;
			$this->posts[ $key ]->post_status = $old_status;
		}
	}

	/**
	 * Suppress deletion-cascade metadata and relationship changes.
	 *
	 * @param int $post_id Post ID.
	 */
	public function before_delete( $post_id ) {
		$this->deleting[ $this->object_key( $post_id ) ] = true;
	}

	/**
	 * Log successful permanent deletion using the supplied deleted object.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Deleted post.
	 */
	public function post_deleted( $post_id, $post ) {
		unset( $this->deleting[ $this->object_key( $post_id ) ] );
		if ( $this->eligible_post( $post ) ) {
			$this->emit( 'post.deleted', $post, array( 'before' => $post->post_status ) );
		}
	}

	/**
	 * Log successful trash movement.
	 *
	 * @param int         $post_id Post ID.
	 * @param string|null $previous Previous status, unavailable on older WordPress.
	 */
	public function post_trashed( $post_id, $previous = null ) {
		$post = get_post( $post_id );
		if ( $this->eligible_post( $post ) && 'trash' === $post->post_status ) {
			$this->emit(
				'post.trashed',
				$post,
				array(
					'before' => $previous ?? (string) get_post_meta( $post_id, '_wp_trash_meta_status', true ),
					'after'  => 'trash',
				)
			);
		}
	}

	/**
	 * Log successful restoration.
	 *
	 * @param int $post_id Post ID.
	 */
	public function post_restored( $post_id ) {
		$post = get_post( $post_id );
		if ( $this->eligible_post( $post ) && 'trash' !== $post->post_status ) {
			$this->emit(
				'post.restored',
				$post,
				array(
					'before' => 'trash',
					'after'  => $post->post_status,
				)
			);
		}
	}

	/**
	 * Snapshot effective term IDs before the first relationship mutation.
	 *
	 * @param int       $post_id Object ID.
	 * @param int|array $term_ids Term-taxonomy IDs, not used as term IDs.
	 * @param string    $taxonomy Taxonomy.
	 */
	public function before_relationship( $post_id, $term_ids, $taxonomy ) {
		$post = get_post( $post_id );
		$key  = $this->object_key( $post_id ) . ':' . $taxonomy;
		if ( ! $this->eligible_post( $post ) || isset( $this->relationships[ $key ] ) ) {
			return;
		}
		$terms = $this->term_ids( $post_id, $taxonomy );
		if ( null !== $terms ) {
			$this->relationships[ $key ] = array(
				'blog'     => get_current_blog_id(),
				'post'     => $post_id,
				'taxonomy' => $taxonomy,
				'before'   => $terms,
				'actor'    => $this->actor_id(),
			);
		}
	}

	/**
	 * Compare a completed term assignment.
	 *
	 * @param int    $post_id Post ID.
	 * @param array  $terms Supplied terms.
	 * @param array  $term_ids Supplied term-taxonomy IDs.
	 * @param string $taxonomy Taxonomy.
	 */
	public function relationships_saved( $post_id, $terms, $term_ids, $taxonomy ) {
		$this->flush_relationships( $this->object_key( $post_id ) . ':' . $taxonomy );
	}

	/**
	 * Flush completed assignments, including standalone removals at shutdown.
	 *
	 * @param string|null $only Optional snapshot key.
	 */
	public function flush_relationships( $only = null ) {
		foreach ( $this->relationships as $key => $snapshot ) {
			if ( ( null !== $only && $key !== $only ) || get_current_blog_id() !== $snapshot['blog'] ) {
				continue;
			}
			unset( $this->relationships[ $key ] );
			$post  = get_post( $snapshot['post'] );
			$after = $this->term_ids( $snapshot['post'], $snapshot['taxonomy'] );
			if ( ! $this->eligible_post( $post ) || 'trash' === $post->post_status || null === $after || $after === $snapshot['before'] ) {
				continue;
			}
			$details = array(
				'before'   => $snapshot['before'],
				'after'    => $after,
				'taxonomy' => $snapshot['taxonomy'],
			);
			if ( 'post_tag' === $snapshot['taxonomy'] ) {
				$added   = array_values( array_diff( $after, $snapshot['before'] ) );
				$removed = array_values( array_diff( $snapshot['before'], $after ) );
				if ( $added ) {
					$this->emit(
						'post.tags_added',
						$post,
						array(
							'before'   => array(),
							'after'    => $added,
							'taxonomy' => 'post_tag',
						),
						$snapshot['actor']
					);
				}
				if ( $removed ) {
					$this->emit(
						'post.tags_removed',
						$post,
						array(
							'before'   => $removed,
							'after'    => array(),
							'taxonomy' => 'post_tag',
						),
						$snapshot['actor']
					);
				}
			} else {
				$this->emit( 'category' === $snapshot['taxonomy'] ? 'post.categories_changed' : 'post.terms_changed', $post, $details, $snapshot['actor'] );
			}
		}
	}

	/**
	 * Finish standalone removals on their original blog and restore the caller.
	 */
	public function flush_pending_relationships() {
		$blogs = array_unique( array_column( $this->relationships, 'blog' ) );
		foreach ( $blogs as $blog_id ) {
			$switched = get_current_blog_id() !== $blog_id;
			if ( $switched ) {
				switch_to_blog( $blog_id );
			}
			try {
				$this->flush_relationships();
			} finally {
				if ( $switched ) {
					restore_current_blog();
				}
			}
		}
	}

	/**
	 * Resolve normalized persisted term IDs.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $taxonomy Taxonomy.
	 * @return array|null Sorted term IDs or null on failure.
	 */
	private function term_ids( $post_id, $taxonomy ) {
		$terms = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
		if ( is_wp_error( $terms ) ) {
			return null;
		}
		$terms = array_values( array_unique( array_map( 'absint', $terms ) ) );
		sort( $terms, SORT_NUMERIC );
		return $terms;
	}

	/**
	 * Snapshot raw term fields before an edit.
	 *
	 * @param int    $term_id Term ID.
	 * @param string $taxonomy Taxonomy.
	 */
	public function before_term( $term_id, $taxonomy ) {
		if ( 'nav_menu' === $taxonomy || WPMastertoolkit_Activity_Log_WooCommerce::commerce_taxonomy( $taxonomy ) ) {
			return;
		}
		$term = get_term( $term_id, $taxonomy, OBJECT, 'raw' );
		if ( $term instanceof WP_Term ) {
			$this->terms[ $this->object_key( $term_id ) . ':' . $taxonomy ] = clone $term;
		}
	}

	/**
	 * Log independently changed term fields after cache invalidation.
	 *
	 * @param int    $term_id Term ID.
	 * @param int    $term_taxonomy_id Term-taxonomy ID.
	 * @param string $taxonomy Taxonomy.
	 */
	public function term_saved( $term_id, $term_taxonomy_id, $taxonomy ) {
		$key    = $this->object_key( $term_id ) . ':' . $taxonomy;
		$before = $this->terms[ $key ] ?? null;
		$after  = get_term( $term_id, $taxonomy, OBJECT, 'raw' );
		unset( $this->terms[ $key ] );
		if ( ! $before || ! $after instanceof WP_Term ) {
			return;
		}
		foreach ( array( 'name', 'slug', 'description', 'parent' ) as $field ) {
			if ( (string) $before->$field !== (string) $after->$field ) {
				$details = array(
					'before'   => $before->$field,
					'after'    => $after->$field,
					'taxonomy' => $taxonomy,
					'title'    => $after->name,
				);
				if ( 'description' === $field ) {
					$details['before'] = strlen( $before->$field );
					$details['after']  = strlen( $after->$field );
					$details['unit']   = 'bytes';
				}
				do_action( 'wpmastertoolkit/activity_log/event', 'term.' . $field . '_changed', $this->actor_id(), 'term', $term_id, $details );
			}
		}
	}

	/**
	 * Log a created term with taxonomy context.
	 *
	 * @param int    $term_id Term ID.
	 * @param int    $term_taxonomy_id Term-taxonomy ID.
	 * @param string $taxonomy Taxonomy.
	 */
	public function term_created( $term_id, $term_taxonomy_id, $taxonomy ) {
		if ( 'nav_menu' === $taxonomy || WPMastertoolkit_Activity_Log_WooCommerce::commerce_taxonomy( $taxonomy ) ) {
			return;
		}
		$term = get_term( $term_id, $taxonomy );
		if ( $term instanceof WP_Term ) {
			do_action(
				'wpmastertoolkit/activity_log/event',
				'term.created',
				$this->actor_id(),
				'term',
				$term_id,
				array(
					'taxonomy' => $taxonomy,
					'title'    => $term->name,
					'after'    => $term->slug,
				)
			);
		}
	}

	/**
	 * Log a successfully deleted term from the supplied snapshot.
	 *
	 * @param int     $term_id Term ID.
	 * @param int     $term_taxonomy_id Term-taxonomy ID.
	 * @param string  $taxonomy Taxonomy.
	 * @param WP_Term $term Deleted term.
	 */
	public function term_deleted( $term_id, $term_taxonomy_id, $taxonomy, $term ) {
		if ( 'nav_menu' === $taxonomy || WPMastertoolkit_Activity_Log_WooCommerce::commerce_taxonomy( $taxonomy ) ) {
			return;
		}
		if ( $term instanceof WP_Term ) {
			do_action(
				'wpmastertoolkit/activity_log/event',
				'term.deleted',
				$this->actor_id(),
				'term',
				$term_id,
				array(
					'taxonomy' => $taxonomy,
					'title'    => $term->name,
					'before'   => $term->slug,
				)
			);
		}
	}

	/**
	 * Snapshot an allowlisted metadata addition.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $meta_key Metadata key.
	 */
	public function before_meta_add( $post_id, $meta_key ) {
		$this->capture_meta( $post_id, $meta_key );
	}

	/**
	 * Snapshot metadata updates, including key renames by metadata ID.
	 *
	 * @param int    $meta_id Metadata ID.
	 * @param int    $post_id Post ID.
	 * @param string $meta_key New metadata key.
	 */
	public function before_meta_update( $meta_id, $post_id, $meta_key ) {
		$previous = get_metadata_by_mid( 'post', $meta_id );
		if ( $previous && $previous->meta_key !== $meta_key ) {
			$this->capture_meta( $post_id, $previous->meta_key, $meta_key );
		}
		$this->capture_meta( $post_id, $meta_key );
	}

	/**
	 * Snapshot deletion targets using the supplied IDs, including delete-all.
	 *
	 * @param array  $meta_ids Metadata IDs.
	 * @param int    $post_id Post ID or zero for bulk deletion.
	 * @param string $meta_key Metadata key.
	 */
	public function before_meta_delete( $meta_ids, $post_id, $meta_key ) {
		if ( $post_id ) {
			$this->capture_meta( $post_id, $meta_key );
			return;
		}
		foreach ( $meta_ids as $meta_id ) {
			$meta = get_metadata_by_mid( 'post', $meta_id );
			if ( $meta ) {
				$this->capture_meta( (int) $meta->post_id, $meta->meta_key );
			}
		}
	}

	/**
	 * Capture effective metadata only for keys owned by this observer.
	 *
	 * @param int         $post_id Post ID.
	 * @param string      $meta_key Metadata key.
	 * @param string|null $trigger Key used by the completing hook.
	 */
	private function capture_meta( $post_id, $meta_key, $trigger = null ) {
		if ( ! in_array( $meta_key, array( '_wp_page_template', '_thumbnail_id', '_edit_lock' ), true ) || ! $this->eligible_post( get_post( $post_id ) ) ) {
			return;
		}
		$key = $this->object_key( $post_id ) . ':' . $meta_key;
		if ( ! isset( $this->metadata[ $key ] ) ) {
			$this->metadata[ $key ] = array(
				'blog'    => get_current_blog_id(),
				'post'    => $post_id,
				'key'     => $meta_key,
				'trigger' => $trigger ?? $meta_key,
				'before'  => get_post_meta( $post_id, $meta_key, true ),
				'actor'   => $this->actor_id(),
			);
		}
	}

	/**
	 * Confirm effective metadata changes after a completed operation.
	 *
	 * @param int|array $meta_ids Metadata IDs.
	 * @param int       $post_id Post ID, or zero for bulk deletion.
	 * @param string    $meta_key Metadata key.
	 */
	public function meta_saved( $meta_ids, $post_id, $meta_key ) {
		foreach ( $this->metadata as $key => $snapshot ) {
			if ( get_current_blog_id() !== $snapshot['blog'] || $snapshot['trigger'] !== $meta_key || ( $post_id && (int) $post_id !== (int) $snapshot['post'] ) ) {
				continue;
			}
			unset( $this->metadata[ $key ] );
			$post   = get_post( $snapshot['post'] );
			$before = $snapshot['before'];
			$after  = get_post_meta( $snapshot['post'], $snapshot['key'], true );
			if ( ! $this->eligible_post( $post ) || 'trash' === $post->post_status ) {
				continue;
			}
			$event = '';
			if ( '_thumbnail_id' === $snapshot['key'] ) {
				$before = absint( $before );
				$after  = absint( $after );
				$event  = 'post.featured_image_changed';
			} elseif ( '_wp_page_template' === $snapshot['key'] ) {
				$before = is_string( $before ) && $before ? $before : 'default';
				$after  = is_string( $after ) && $after ? $after : 'default';
				$event  = 'post.template_changed';
			} else {
				$old_lock = is_string( $before ) ? explode( ':', $before ) : array();
				$new_lock = is_string( $after ) ? explode( ':', $after ) : array();
				//phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
				$window   = (int) apply_filters( 'wp_check_post_lock_window', 150 );
				if ( 2 !== count( $old_lock ) || 2 !== count( $new_lock ) || ! ctype_digit( $old_lock[0] ) || ! ctype_digit( $old_lock[1] ) || ! ctype_digit( $new_lock[0] ) || ! ctype_digit( $new_lock[1] )
					|| (int) $old_lock[0] <= time() - $window || get_current_user_id() !== (int) $new_lock[1] || ! current_user_can( 'edit_post', $post->ID ) || ! get_userdata( (int) $old_lock[1] ) ) {
					continue;
				}
				$before = (int) $old_lock[1];
				$after  = (int) $new_lock[1];
				$event  = 'post.lock_taken_over';
			}
			if ( $before !== $after ) {
				$this->emit(
					$event,
					$post,
					array(
						'before' => $before,
						'after'  => $after,
					),
					$snapshot['actor']
				);
			}
		}
	}

	/**
	 * Log sticky differences from completed option writes without duplicate hooks.
	 *
	 * @param string $option Option name.
	 * @param mixed  $before Previous value.
	 * @param mixed  $after New value.
	 */
	public function sticky_changed( $option, $before, $after ) {
		if ( 'sticky_posts' !== $option || ! is_array( $after ) ) {
			return;
		}
		$before = is_array( $before ) ? $before : array();
		$before = array_map( 'absint', array_filter( $before, 'is_numeric' ) );
		$after  = array_map( 'absint', array_filter( $after, 'is_numeric' ) );
		foreach ( array_unique( array_merge( array_diff( $before, $after ), array_diff( $after, $before ) ) ) as $post_id ) {
			$post = get_post( $post_id );
			if ( $this->eligible_post( $post ) ) {
				$stuck = in_array( $post_id, $after, true );
				$this->emit(
					$stuck ? 'post.stuck' : 'post.unstuck',
					$post,
					array(
						'before' => $stuck ? 'not-sticky' : 'sticky',
						'after'  => $stuck ? 'sticky' : 'not-sticky',
					)
				);
			}
		}
	}

	/**
	 * Observe the first sticky option creation as well as updates.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value New value.
	 */
	public function sticky_added( $option, $value ) {
		$this->sticky_changed( $option, array(), $value );
	}

	/**
	 * Observe a permitted editor screen, once per post and request.
	 *
	 * @param WP_Post $post Edited post.
	 */
	public function editor_opened( $post ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $this->eligible_post( $post ) && $screen && 'post' === $screen->base && is_user_logged_in() && ! wp_doing_ajax() && current_user_can( 'edit_post', $post->ID ) ) {
			$this->emit_access( 'post.editor_opened', $post );
		}
	}

	/**
	 * Observe Block editor context without changing its settings.
	 *
	 * @param array  $settings Editor settings.
	 * @param object $context Editor context.
	 * @return array Unchanged settings.
	 */
	public function block_editor_opened( $settings, $context ) {
		if ( isset( $context->post ) ) {
			$this->editor_opened( $context->post );
		}
		return $settings;
	}

	/**
	 * Observe authenticated frontend rendering, not a fresh password submission.
	 */
	public function post_viewed() {
		$post = get_queried_object();
		if ( $this->frontend_access( $post ) && ! post_password_required( $post ) ) {
			$this->emit_access( $post->post_password ? 'post.password_accessed' : 'post.viewed', $post );
		}
	}

	/**
	 * Observe rejected protected access and preserve the core message.
	 *
	 * @param string  $text Filtered message.
	 * @param WP_Post $post Protected post.
	 * @return string Unchanged message.
	 */
	public function password_rejected( $text, $post ) {
		if ( $this->frontend_access( $post ) && $post->post_password && post_password_required( $post ) ) {
			$this->emit_access( 'post.password_rejected', $post );
		}
		return $text;
	}

	/**
	 * Provide the same observation on WordPress versions without the dedicated hook.
	 *
	 * @param string  $output Core form HTML.
	 * @param WP_Post $post Protected post.
	 * @return string Unchanged form HTML.
	 */
	public function password_form( $output, $post = null ) {
		global $wp_version;
		if ( version_compare( $wp_version, '6.8', '<' ) && $post instanceof WP_Post && wp_get_raw_referer() === get_permalink( $post->ID ) && isset( $_COOKIE[ 'wp-postpass_' . COOKIEHASH ] ) ) {
			$this->password_rejected( '', $post );
		}
		return $output;
	}

	/**
	 * Check the frontend observation boundary.
	 *
	 * @param mixed $post Queried post.
	 * @return bool Whether access can be observed.
	 */
	private function frontend_access( $post ) {
		return $this->eligible_post( $post ) && is_user_logged_in() && is_singular() && ! is_admin() && ! is_feed() && ! is_404() && ! wp_doing_ajax() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST );
	}

	/**
	 * Deduplicate repeated rendering hooks in the same request.
	 *
	 * @param string  $event Event ID.
	 * @param WP_Post $post Post.
	 */
	private function emit_access( $event, $post ) {
		$key = $this->object_key( $post->ID ) . ':' . $event;
		if ( ! isset( $this->access[ $key ] ) ) {
			$this->access[ $key ] = true;
			$this->emit( $event, $post );
		}
	}

	/**
	 * Scope a snapshot to the active blog.
	 *
	 * @param int $object_id Object ID.
	 * @return string Snapshot key.
	 */
	private function object_key( $object_id ) {
		return get_current_blog_id() . ':' . $object_id;
	}

	/**
	 * Resolve a canonical URL without credentials, query strings or fragments.
	 *
	 * @param int $post_id Post ID.
	 * @return string URL path.
	 */
	private function safe_permalink( $post_id ) {
		return (string) wp_parse_url( (string) get_permalink( $post_id ), PHP_URL_PATH );
	}

	/**
	 * Resolve an author or parent display name.
	 *
	 * @param string $event Event ID.
	 * @param int    $object_id Object ID.
	 * @return string Name.
	 */
	private function object_name( $event, $object_id ) {
		if ( ! $object_id ) {
			return '';
		}
		if ( 'post.author_changed' === $event ) {
			$user = get_userdata( $object_id );
			return $user ? $user->display_name : '';
		}
		$post = get_post( $object_id );
		return $post ? $post->post_title : '';
	}

	/**
	 * Attribute scheduled maintenance to the system, not a cached current user.
	 *
	 * @return int Actor ID.
	 */
	private function actor_id() {
		return wp_doing_cron() || doing_action( 'wp_scheduled_delete' ) ? 0 : get_current_user_id();
	}

	/**
	 * Build independent field events, excluding timestamps and bookkeeping.
	 *
	 * @param WP_Post $before Previous post.
	 * @param WP_Post $after Saved post.
	 * @return array Event details keyed by event ID.
	 */
	public static function post_changes( $before, $after ) {
		$events = array();
		$fields = array(
			'post_author'    => 'post.author_changed',
			'post_parent'    => 'post.parent_changed',
			'post_title'     => 'post.title_changed',
			'post_name'      => 'post.permalink_changed',
			'post_date'      => 'post.date_changed',
			'comment_status' => 'post.comments_changed',
			'ping_status'    => 'post.pings_changed',
		);
		foreach ( $fields as $field => $event ) {
			$old_value = (string) $before->$field;
			$new_value = (string) $after->$field;
			if ( $old_value !== $new_value ) {
				$events[ $event ] = array(
					'before' => $old_value,
					'after'  => $new_value,
				);
			}
		}
		foreach ( array(
			'post_content' => 'post.content_changed',
			'post_excerpt' => 'post.excerpt_changed',
		) as $field => $event ) {
			if ( $before->$field !== $after->$field ) {
				$events[ $event ] = array(
					'before' => strlen( (string) $before->$field ),
					'after'  => strlen( (string) $after->$field ),
					'unit'   => 'bytes',
				);
			}
		}
		if ( $before->post_status !== $after->post_status ) {
			$status_events    = array(
				'publish' => 'post.published',
				'pending' => 'post.submitted_for_review',
				'future'  => 'post.scheduled',
			);
			$event            = $status_events[ $after->post_status ] ?? 'post.status_changed';
			$events[ $event ] = array(
				'before' => $before->post_status,
				'after'  => $after->post_status,
			);
		}
		$old_visibility = self::visibility( $before );
		$new_visibility = self::visibility( $after );
		if ( $old_visibility !== $new_visibility ) {
			$events['post.visibility_changed'] = array(
				'before' => $old_visibility,
				'after'  => $new_visibility,
			);
		} elseif ( $before->post_password !== $after->post_password ) {
			$events['post.password_changed'] = array(
				'before' => 'redacted',
				'after'  => 'redacted',
			);
		}
		return $events;
	}

	/**
	 * Resolve visibility without exposing the password.
	 *
	 * @param WP_Post $post Post.
	 * @return string Visibility.
	 */
	private static function visibility( $post ) {
		return '' !== (string) $post->post_password ? 'password-protected' : ( 'private' === $post->post_status ? 'private' : 'public' );
	}

	/**
	 * Exclude internal post objects.
	 *
	 * @param mixed $post Post.
	 * @return bool Whether the post can be logged.
	 */
	private function eligible_post( $post ) {
		return $post instanceof WP_Post && ! isset( $this->deleting[ $this->object_key( $post->ID ) ] ) && self::supports_post_type( $post->post_type )
			&& 'auto-draft' !== $post->post_status && ! wp_is_post_autosave( $post->ID );
	}

	/**
	 * Identify post types handled directly by this provider.
	 *
	 * @param string $post_type Registered post type name.
	 * @return bool Whether content events support the post type.
	 */
	public static function supports_post_type( $post_type ) {
		return ! in_array( $post_type, array( 'revision', 'attachment', 'nav_menu_item', 'product', 'product_variation', 'shop_coupon', 'shop_order', 'shop_order_refund', 'shop_order_placehold' ), true );
	}

	/**
	 * Send safe content context through the module's writer.
	 *
	 * @param string   $event Event ID.
	 * @param WP_Post  $post Post.
	 * @param array    $details Before/after values.
	 * @param int|null $actor Actor captured before a deferred operation.
	 */
	private function emit( $event, $post, $details = array(), $actor = null ) {
		$actor                = null === $actor ? $this->actor_id() : $actor;
		$details['post_type'] = $post->post_type;
		$details['title']     = $post->post_title;
		$details['source']    = defined( 'WP_CLI' ) && WP_CLI ? 'cli' : ( $actor ? 'user' : 'system' );
		do_action( 'wpmastertoolkit/activity_log/event', $event, $actor, 'post', $post->ID, $details );
	}
}
