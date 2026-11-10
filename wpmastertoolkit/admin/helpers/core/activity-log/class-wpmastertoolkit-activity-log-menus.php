<?php
/**
 * Menus observers for the activity log.
 *
 * @package WPMasterToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Observe menus activity through the shared writer. */
class WPMastertoolkit_Activity_Log_Menus implements WPMastertoolkit_Activity_Log_Provider {

	/**
	 * Pending menu and item snapshots, scoped to the current blog.
	 *
	 * @var array
	 */
	private $before = array();

	/**
	 * Pending reorder events and membership changes.
	 *
	 * @var array
	 */
	private $orders = array();

	/**
	 * Menus whose membership changed during this request.
	 *
	 * @var array
	 */
	private $membership = array();

	/** Register completed-operation observers and pass-through snapshot filters. */
	public function register_hooks() {
		add_action( 'wp_create_nav_menu', array( $this, 'created' ) );
		add_action( 'edit_terms', array( $this, 'capture_menu' ), 10, 2 );
		add_action( 'pre_delete_term', array( $this, 'capture_menu' ), 10, 2 );
		add_action( 'wp_update_nav_menu', array( $this, 'menu_saved' ) );
		add_action( 'wp_delete_nav_menu', array( $this, 'menu_deleted' ) );
		add_filter( 'wp_insert_post_data', array( $this, 'capture_post' ), PHP_INT_MAX, 2 );
		add_filter( 'update_post_metadata', array( $this, 'capture_metadata' ), PHP_INT_MAX, 3 );
		add_action( 'set_object_terms', array( $this, 'capture_membership' ), 10, 6 );
		add_action( 'wp_update_nav_menu_item', array( $this, 'item_saved' ), 10, 2 );
		add_action( 'before_delete_post', array( $this, 'capture_item' ) );
		add_action( 'deleted_post', array( $this, 'item_deleted' ) );
		add_action( 'updated_option', array( $this, 'option_updated' ), 10, 3 );
		add_action( 'added_option', array( $this, 'option_added' ), 10, 2 );
		add_action( 'delete_option', array( $this, 'capture_option' ) );
		add_action( 'deleted_option', array( $this, 'option_deleted' ) );
		add_action( 'shutdown', array( $this, 'flush_orders' ) );
	}

	/**
	 * Describe menu events without depending on presentation translations.
	 *
	 * @param bool $translate Whether to translate labels.
	 * @return array Event groups.
	 */
	public static function event_groups( $translate = false ) {
		return array(
			'content' => array(
				'label'           => $translate ? __( 'Content', 'wpmastertoolkit' ) : '',
				'pro'             => false,
				'default_enabled' => true,
				'order'           => 10,
				'event_order'     => 9,
				'event_positions' => array(
					'menu.name_changed'     => 100,
					'menu.item_added'       => 101,
					'menu.item_removed'     => 102,
					'menu.item_changed'     => 103,
					'menu.order_changed'    => 104,
					'menu.parent_changed'   => 105,
					'menu.auto_add_changed' => 106,
					'menu.location_changed' => 107,
				),
				'events'          => array(
					'menu.created'          => $translate ? __( 'Menu created', 'wpmastertoolkit' ) : '',
					'menu.deleted'          => $translate ? __( 'Menu deleted', 'wpmastertoolkit' ) : '',
					'menu.name_changed'     => $translate ? __( 'Menu renamed', 'wpmastertoolkit' ) : '',
					'menu.item_added'       => $translate ? __( 'Menu item added', 'wpmastertoolkit' ) : '',
					'menu.item_removed'     => $translate ? __( 'Menu item removed', 'wpmastertoolkit' ) : '',
					'menu.item_changed'     => $translate ? __( 'Menu item changed', 'wpmastertoolkit' ) : '',
					'menu.order_changed'    => $translate ? __( 'Menu item order changed', 'wpmastertoolkit' ) : '',
					'menu.parent_changed'   => $translate ? __( 'Menu item nesting changed', 'wpmastertoolkit' ) : '',
					'menu.auto_add_changed' => $translate ? __( 'Menu automatic page addition changed', 'wpmastertoolkit' ) : '',
					'menu.location_changed' => $translate ? __( 'Menu location assignment changed', 'wpmastertoolkit' ) : '',
				),
			),
		);
	}

	/**
	 * Get historical labels.
	 *
	 * @param bool $translate Whether to translate labels.
	 * @return array Labels.
	 */
	public static function historical_labels( $translate = true ) {
		return array( 'menu.updated' => $translate ? __( 'Menu updated', 'wpmastertoolkit' ) : '' );
	}

	/**
	 * Get domain detail labels.
	 *
	 * @param string $event Event ID.
	 * @return array Labels.
	 */
	public static function detail_labels( $event ) {
		return array(
			'menu_name'     => __( 'Menu name', 'wpmastertoolkit' ),
			'item_id'       => __( 'Menu item ID', 'wpmastertoolkit' ),
			'item_title'    => __( 'Menu item title', 'wpmastertoolkit' ),
			'content_type'  => __( 'Content type', 'wpmastertoolkit' ),
			'content_id'    => __( 'Linked content ID', 'wpmastertoolkit' ),
			'parent_id'     => __( 'Parent menu item ID', 'wpmastertoolkit' ),
			'parent_title'  => __( 'Parent menu item title', 'wpmastertoolkit' ),
			'location'      => __( 'Theme location', 'wpmastertoolkit' ),
			'location_name' => __( 'Theme location name', 'wpmastertoolkit' ),
		);
	}

	/**
	 * Allow only bounded menu context and recognized field changes.
	 *
	 * @param string $event Event ID.
	 * @param mixed  $details Supplied details.
	 * @return array Safe details.
	 */
	public static function normalize_details( $event, $details ) {
		$events = self::event_groups( false )['content']['events'];
		if ( ! isset( $events[ $event ] ) || ! is_array( $details ) ) {
			return array();
		}
		$normalized = array();
		foreach ( array( 'item_id', 'content_id', 'parent_id' ) as $field ) {
			if ( isset( $details[ $field ] ) && is_scalar( $details[ $field ] ) && ctype_digit( (string) $details[ $field ] ) ) {
				$normalized[ $field ] = absint( $details[ $field ] );
			}
		}
		foreach ( array( 'menu_name', 'item_title', 'content_type', 'parent_title', 'location', 'location_name' ) as $field ) {
			if ( isset( $details[ $field ] ) && is_string( $details[ $field ] ) ) {
				$normalized[ $field ] = self::bounded_text( $details[ $field ] );
			}
		}
		$change = $details['field'] ?? '';
		$fields = array( 'title', 'url', 'description', 'attribute_title', 'target', 'classes', 'xfn', 'type', 'object', 'object_id' );
		if ( 'menu.item_changed' === $event && ! in_array( $change, $fields, true ) ) {
			return $normalized;
		}
		if ( 'menu.item_changed' === $event ) {
			$normalized['field'] = $change;
		}
		$numeric = in_array( $event, array( 'menu.order_changed', 'menu.parent_changed', 'menu.location_changed', 'menu.auto_add_changed' ), true ) || in_array( $change, array( 'object_id', 'description' ), true );
		foreach ( array( 'before', 'after' ) as $field ) {
			$value = $details[ $field ] ?? null;
			if ( 'menu.item_changed' === $event && 'url' === $change ) {
				$normalized[ $field ] = 'redacted';
			} elseif ( $numeric && is_scalar( $value ) && ctype_digit( (string) $value ) ) {
				$normalized[ $field ] = absint( $value );
			} elseif ( ! $numeric && is_string( $value ) ) {
				$normalized[ $field ] = self::bounded_text( $value );
			}
		}
		if ( 'menu.item_changed' === $event && 'description' === $change ) {
			$normalized['unit'] = 'bytes';
		}
		if ( isset( $details['source'] ) && in_array( $details['source'], array( 'user', 'system', 'cli' ), true ) ) {
			$normalized['source'] = $details['source'];
		}
		return $normalized;
	}

	/**
	 * Inherit legacy update choices without overriding granular preferences.
	 *
	 * @param array $preferences Saved preferences.
	 * @return array Preferences.
	 */
	public static function migrate_preferences( $preferences ) {
		foreach ( array_keys( self::event_groups( false )['content']['events'] ) as $event ) {
			if ( 'menu.deleted' !== $event && ! isset( $preferences[ $event ] ) && isset( $preferences['menu.updated'] ) && in_array( $preferences['menu.updated'], array( '0', '1' ), true ) ) {
				$preferences[ $event ] = $preferences['menu.updated'];
			}
		}
		unset( $preferences['menu.updated'] );
		return $preferences;
	}

	/**
	 * Log successful menu creation.
	 *
	 * @param int $menu_id Menu ID.
	 */
	public function created( $menu_id ) {
		$this->emit( 'menu.created', $menu_id );
	}

	/**
	 * Capture menu names before a term update.
	 *
	 * @param int    $menu_id Menu ID.
	 * @param string $taxonomy Taxonomy.
	 */
	public function capture_menu( $menu_id, $taxonomy ) {
		if ( 'nav_menu' === $taxonomy ) {
			$menu = wp_get_nav_menu_object( $menu_id );
			if ( $menu instanceof WP_Term ) {
				$this->before[ $this->key( 'menu', $menu_id ) ] = $menu->name;
			}
		}
	}

	/**
	 * Compare the persisted name after the navigation-menu update completes.
	 *
	 * @param int $menu_id Menu ID.
	 */
	public function menu_saved( $menu_id ) {
		$key  = $this->key( 'menu', $menu_id );
		$old  = $this->before[ $key ] ?? null;
		$menu = wp_get_nav_menu_object( $menu_id );
		unset( $this->before[ $key ] );
		if ( null !== $old && $menu instanceof WP_Term && $old !== $menu->name ) {
			$this->emit(
				'menu.name_changed',
				$menu_id,
				array(
					'before' => $old,
					'after'  => $menu->name,
				)
			);
		}
	}

	/**
	 * Retain the captured name after successful navigation-menu deletion.
	 *
	 * @param int $menu_id Menu ID.
	 */
	public function menu_deleted( $menu_id ) {
		$key  = $this->key( 'menu', $menu_id );
		$name = $this->before[ $key ] ?? null;
		unset( $this->before[ $key ] );
		if ( null !== $name ) {
			$this->emit( 'menu.deleted', $menu_id, array( 'menu_name' => $name ) );
		}
	}

	/**
	 * Snapshot an item before its post fields change; never modify filter input.
	 *
	 * @param array $data Post data.
	 * @param array $postarr Post arguments.
	 * @return array Unchanged data.
	 */
	public function capture_post( $data, $postarr ) {
		if ( 'nav_menu_item' === ( $data['post_type'] ?? '' ) && ! empty( $postarr['ID'] ) ) {
			$this->capture_item( $postarr['ID'] );
		}
		return $data;
	}

	/**
	 * Capture before the menu API writes metadata ahead of the post update.
	 *
	 * @param mixed  $check Filter result.
	 * @param int    $item_id Post ID.
	 * @param string $meta_key Metadata key.
	 * @return mixed Unchanged filter result.
	 */
	public function capture_metadata( $check, $item_id, $meta_key ) {
		if ( 0 === strpos( $meta_key, '_menu_item_' ) ) {
			$this->capture_item( $item_id );
		}
		return $check;
	}

	/**
	 * Capture old menu membership before the menu API writes item fields.
	 *
	 * @param int    $item_id Object ID.
	 * @param array  $terms Terms.
	 * @param array  $term_taxonomy_ids New term taxonomy IDs.
	 * @param string $taxonomy Taxonomy.
	 * @param bool   $append Whether terms are appended.
	 * @param array  $old_ids Previous term taxonomy IDs.
	 */
	public function capture_membership( $item_id, $terms, $term_taxonomy_ids, $taxonomy, $append, $old_ids ) {
		if ( 'nav_menu' !== $taxonomy ) {
			return;
		}
		$this->capture_item( $item_id );
		$key = $this->key( 'item', $item_id );
		if ( isset( $this->before[ $key ] ) ) {
			$menus = array();
			foreach ( $old_ids as $old_id ) {
				$menu = get_term_by( 'term_taxonomy_id', $old_id, 'nav_menu' );
				if ( $menu instanceof WP_Term ) {
					$menus[] = (int) $menu->term_id;
				}
			}
			$this->before[ $key ]['menus'] = $menus;
		}
	}

	/**
	 * Capture an item's first pre-mutation state.
	 *
	 * @param int $item_id Item ID.
	 */
	public function capture_item( $item_id ) {
		$key = $this->key( 'item', $item_id );
		if ( ! isset( $this->before[ $key ] ) ) {
			$item = $this->item_state( $item_id );
			if ( $item ) {
				$this->before[ $key ] = $item;
			}
		}
	}

	/**
	 * Read persisted item fields, using resolved titles for linked content.
	 *
	 * @param int $item_id Item ID.
	 * @return array|null Item snapshot.
	 */
	private function item_state( $item_id ) {
		$post = get_post( $item_id );
		if ( ! $post instanceof WP_Post || 'nav_menu_item' !== $post->post_type ) {
			return null;
		}
		$item  = wp_setup_nav_menu_item( clone $post );
		$menus = wp_get_object_terms( $item_id, 'nav_menu', array( 'fields' => 'ids' ) );
		return array(
			'item_id'         => (int) $item_id,
			'title'           => $item->title,
			'url'             => (string) get_post_meta( $item_id, '_menu_item_url', true ),
			'description'     => $post->post_content,
			'attribute_title' => $post->post_excerpt,
			'target'          => $item->target,
			'classes'         => implode( ' ', (array) $item->classes ),
			'xfn'             => $item->xfn,
			'type'            => $item->type,
			'object'          => $item->object,
			'object_id'       => (int) $item->object_id,
			'parent'          => (int) $item->menu_item_parent,
			'order'           => (int) $item->menu_order,
			'menus'           => is_wp_error( $menus ) || 'publish' !== $post->post_status ? array() : array_map( 'intval', $menus ),
			'actor'           => $this->actor_id(),
		);
	}

	/**
	 * Emit successful additions, moves, and independent item field changes.
	 *
	 * @param int $menu_id Menu ID.
	 * @param int $item_id Item ID.
	 */
	public function item_saved( $menu_id, $item_id ) {
		$key  = $this->key( 'item', $item_id );
		$old  = $this->before[ $key ] ?? null;
		$item = $this->item_state( $item_id );
		unset( $this->before[ $key ] );
		if ( ! $item ) {
			return;
		}
		$old_menus = $old['menus'] ?? array();
		$actor     = $old['actor'] ?? $this->actor_id();
		foreach ( array_diff( $old_menus, $item['menus'] ) as $removed ) {
			$this->membership[ $this->key( 'menu', $removed ) ] = true;
			$this->emit( 'menu.item_removed', $removed, $this->item_details( $old ), $actor );
		}
		foreach ( $item['menus'] as $menu_id ) {
			$details = $this->item_details( $item );
			if ( ! in_array( $menu_id, $old_menus, true ) ) {
				$this->membership[ $this->key( 'menu', $menu_id ) ] = true;
				$this->emit( 'menu.item_added', $menu_id, $details, $actor );
				continue;
			}
			foreach ( array( 'title', 'url', 'description', 'attribute_title', 'target', 'classes', 'xfn', 'type', 'object', 'object_id' ) as $field ) {
				if ( $old[ $field ] !== $item[ $field ] ) {
					$before = 'url' === $field ? 'redacted' : $old[ $field ];
					$after  = 'url' === $field ? 'redacted' : $item[ $field ];
					if ( 'description' === $field ) {
						$before = strlen( $before );
						$after  = strlen( $after );
					}
					$this->emit(
						'menu.item_changed',
						$menu_id,
						$details + array(
							'field'  => $field,
							'before' => $before,
							'after'  => $after,
						),
						$actor
					);
				}
			}
			if ( $old['parent'] !== $item['parent'] ) {
				$this->emit(
					'menu.parent_changed',
					$menu_id,
					$details + array(
						'before' => $old['parent'],
						'after'  => $item['parent'],
					),
					$actor
				);
			}
			if ( $old['order'] !== $item['order'] ) {
				$order_key = $this->key( 'menu', $menu_id );
				if ( ! isset( $this->orders[ $order_key ] ) ) {
					$this->orders[ $order_key ] = array(
						'blog'   => get_current_blog_id(),
						'menu'   => $menu_id,
						'item'   => $item_id,
						'before' => $old['order'],
						'actor'  => $actor,
					);
				}
			}
		}
	}

	/**
	 * Log item removal only after successful permanent deletion.
	 *
	 * @param int $item_id Item ID.
	 */
	public function item_deleted( $item_id ) {
		$key  = $this->key( 'item', $item_id );
		$item = $this->before[ $key ] ?? null;
		unset( $this->before[ $key ] );
		if ( $item ) {
			foreach ( $item['menus'] as $menu_id ) {
				$this->membership[ $this->key( 'menu', $menu_id ) ] = true;
				$this->emit( 'menu.item_removed', $menu_id, $this->item_details( $item ), $item['actor'] );
			}
		}
	}

	/** Emit one effective reorder per menu, excluding membership-induced shifts. */
	public function flush_orders() {
		$orders       = $this->orders;
		$this->orders = array();
		foreach ( $orders as $key => $order ) {
			if ( isset( $this->membership[ $key ] ) ) {
				continue;
			}
			$switch = get_current_blog_id() !== $order['blog'];
			if ( $switch ) {
				switch_to_blog( $order['blog'] );
			}
			try {
				$item = $this->item_state( $order['item'] );
				if ( $item && in_array( $order['menu'], $item['menus'], true ) && $order['before'] !== $item['order'] ) {
					$this->emit(
						'menu.order_changed',
						$order['menu'],
						$this->item_details( $item ) + array(
							'before' => $order['before'],
							'after'  => $item['order'],
						),
						$order['actor']
					);
				}
			} finally {
				if ( $switch ) {
					restore_current_blog();
				}
			}
		}
	}

	/**
	 * Observe completed option writes for both the editor and Customizer.
	 *
	 * @param string $option Option name.
	 * @param mixed  $old Previous value.
	 * @param mixed  $value Persisted value.
	 */
	public function option_updated( $option, $old, $value ) {
		if ( 'nav_menu_options' === $option ) {
			$before = is_array( $old ) && isset( $old['auto_add'] ) && is_array( $old['auto_add'] ) ? array_map( 'absint', $old['auto_add'] ) : array();
			$after  = is_array( $value ) && isset( $value['auto_add'] ) && is_array( $value['auto_add'] ) ? array_map( 'absint', $value['auto_add'] ) : array();
			foreach ( array_unique( array_merge( $before, $after ) ) as $menu_id ) {
				$was = (int) in_array( $menu_id, $before, true );
				$now = (int) in_array( $menu_id, $after, true );
				if ( $was !== $now && wp_get_nav_menu_object( $menu_id ) ) {
					$this->emit(
						'menu.auto_add_changed',
						$menu_id,
						array(
							'before' => $was,
							'after'  => $now,
						)
					);
				}
			}
		} elseif ( 'theme_mods_' . get_option( 'stylesheet' ) === $option ) {
			$before = is_array( $old ) && isset( $old['nav_menu_locations'] ) && is_array( $old['nav_menu_locations'] ) ? $old['nav_menu_locations'] : array();
			$after  = is_array( $value ) && isset( $value['nav_menu_locations'] ) && is_array( $value['nav_menu_locations'] ) ? $value['nav_menu_locations'] : array();
			$names  = get_registered_nav_menus();
			foreach ( array_unique( array_merge( array_keys( $before ), array_keys( $after ) ) ) as $location ) {
				$was = absint( $before[ $location ] ?? 0 );
				$now = absint( $after[ $location ] ?? 0 );
				if ( $was === $now ) {
					continue;
				}
				foreach ( array_unique( array_filter( array( $was, $now ) ) ) as $menu_id ) {
					if ( wp_get_nav_menu_object( $menu_id ) ) {
						$this->emit(
							'menu.location_changed',
							$menu_id,
							array(
								'location'      => $location,
								'location_name' => $names[ $location ] ?? $location,
								'before'        => $was,
								'after'         => $now,
							)
						);
					}
				}
			}
		}
	}

	/**
	 * Compare a newly added option with an empty configuration.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value Value.
	 */
	public function option_added( $option, $value ) {
		$this->option_updated( $option, array(), $value );
	}

	/**
	 * Snapshot only menu-related options ahead of deletion.
	 *
	 * @param string $option Option name.
	 */
	public function capture_option( $option ) {
		if ( 'nav_menu_options' === $option || 'theme_mods_' . get_option( 'stylesheet' ) === $option ) {
			$this->before[ $this->key( 'option', $option ) ] = get_option( $option );
		}
	}

	/**
	 * Compare successful option removal with an empty configuration.
	 *
	 * @param string $option Option name.
	 */
	public function option_deleted( $option ) {
		$key = $this->key( 'option', $option );
		if ( isset( $this->before[ $key ] ) ) {
			$this->option_updated( $option, $this->before[ $key ], array() );
			unset( $this->before[ $key ] );
		}
	}

	/**
	 * Build readable item context without storing URLs or descriptions.
	 *
	 * @param array $item Item state.
	 * @return array Context.
	 */
	private function item_details( $item ) {
		$parent = $item['parent'] ? get_post( $item['parent'] ) : null;
		return array(
			'item_id'      => $item['item_id'],
			'item_title'   => $item['title'],
			'content_type' => 'custom' === $item['type'] ? 'custom link' : $item['object'],
			'content_id'   => $item['object_id'],
			'parent_id'    => $item['parent'],
			'parent_title' => $parent instanceof WP_Post && 'nav_menu_item' === $parent->post_type ? wp_setup_nav_menu_item( clone $parent )->title : '',
		);
	}

	/**
	 * Scope transient state to the originating site.
	 *
	 * @param string     $type Snapshot type.
	 * @param int|string $id Object identifier.
	 * @return string State key.
	 */
	private function key( $type, $id ) {
		return get_current_blog_id() . ':' . $type . ':' . $id;
	}

	/**
	 * Attribute cron changes to the system.
	 *
	 * @return int Actor ID.
	 */
	private function actor_id() {
		return wp_doing_cron() ? 0 : get_current_user_id();
	}

	/**
	 * Bound sanitized text without breaking UTF-8.
	 *
	 * @param string $value Text.
	 * @return string Safe text.
	 */
	private static function bounded_text( $value ) {
		$value = sanitize_text_field( $value );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 160, 'UTF-8' ) : _mb_substr( $value, 0, 160, 'UTF-8' );
	}

	/**
	 * Send contextual events through the existing policy-controlled writer.
	 *
	 * @param string   $event Event identifier.
	 * @param int      $menu_id Menu ID.
	 * @param array    $details Event details.
	 * @param int|null $actor Initiating actor.
	 */
	private function emit( $event, $menu_id, $details = array(), $actor = null ) {
		$menu  = wp_get_nav_menu_object( $menu_id );
		$actor = null === $actor ? $this->actor_id() : $actor;
		if ( $menu instanceof WP_Term ) {
			$details['menu_name'] = $menu->name;
		}
		$details['source'] = defined( 'WP_CLI' ) && WP_CLI ? 'cli' : ( $actor ? 'user' : 'system' );
		do_action( 'wpmastertoolkit/activity_log/event', $event, $actor, 'menu', (int) $menu_id, $details ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Preserve the existing public event action.
	}
}
