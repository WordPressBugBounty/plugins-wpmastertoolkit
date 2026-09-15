<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Meta Debugger
 * Description: Display all metadata for a post, user, term, or comment.
 * @since 1.4.0
 */
class WPMastertoolkit_Meta_Debugger {

    private $nonce  = 'wpmastertoolkit_meta_debugger_nonce';
    private $action = 'wpmastertoolkit_meta_debugger_action';

    /**
     * Invoke the hooks
     * 
     * @since    1.4.0
     */
    public function __construct() {
        add_action( 'init', array( $this, 'class_init' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
        add_action( 'show_user_profile', array( $this, 'render_user_meta_box' ) );
        add_action( 'edit_user_profile', array( $this, 'render_user_meta_box' ) );
		add_action( 'woocommerce_after_order_itemmeta', array( $this, 'render_order_meta_box' ), PHP_INT_MAX, 2 );
		add_action( 'woocommerce_after_order_fee_item_name', array( $this, 'render_order_meta_box' ), PHP_INT_MAX, 2 );
		add_action( 'woocommerce_after_order_refund_item_name', array( $this, 'render_order_meta_box_refund' ), PHP_INT_MAX );
		add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'render_variation_meta_box' ), PHP_INT_MAX, 3 );
        add_action( 'wp_ajax_' . $this->action, array( $this, 'get_meta_data' ) );
    }

    /**
     * Initialize the class
     * 
     * @since    1.4.0
     */
    public function class_init() {

        if ( ! $this->user_allowed() ) {
            return;
        }

        $all_taxonomies = get_taxonomies();
        foreach ( $all_taxonomies as $taxonomy ) {
            add_action( $taxonomy . '_edit_form_fields', array( $this, 'render_term_meta_box' ) );
        }
    }

    /**
     * Add meta boxes
     * 
     * @since   1.4.0
     */
    public function add_meta_boxes( $post_type ) {

        if ( ! $this->user_allowed() ) {
            return;
        }

        add_meta_box(
            'wpmastertoolkit-meta-debugger',
            esc_html__( 'WPMastertoolkit Meta Debugger', 'wpmastertoolkit' ),
            array( $this, 'render_post_and_comment_meta_box' ),
            $post_type,
            'normal',
            'low',
			array(
				'post_type' => $post_type
			),
        );
    }

    /**
     * Render post & comment meta box
     * 
     * @since   1.4.0
     */
    public function render_post_and_comment_meta_box( $post, $meta_box ) {

		$args      = $meta_box['args'] ?? array();
		$post_type = $args['post_type'] ?? '';

		if ( $this->is_wc_order( $post_type ) && ! wpmastertoolkit_is_pro() ) {
			$this->render_fake_meta_debugger();
			return;
		}

		$type = 'post';
        $id   = $post->ID ?? '';

        if ( ! empty( $post->comment_ID ) ) {
            $type = 'comment';
            $id   = $post->comment_ID;
        }

		if ( is_a( $post, 'Automattic\WooCommerce\Admin\Overrides\Order' ) ) {
			$type = 'order';
			$id   = $post->get_id();
		}

		if ( is_a( $post, 'WC_Subscription' ) ) {
			$type = 'subscription';
			$id   = $post->get_id();
		}

        $this->render_meta_debugger( $id, $type );
    }

    /**
     * Render user meta box
     * 
     * @since   1.4.0
     */
    public function render_user_meta_box( $user ) {

        if ( ! $this->user_allowed() ) {
            return;
        }

        echo '<h2>' . esc_html__( 'WPMastertoolkit Meta Debugger', 'wpmastertoolkit' ) . '</h2>';
        $this->render_meta_debugger( $user->ID, 'user' );
    }

    /**
     * Render term meta box
     * 
     * @since   1.4.0
     */
    public function render_term_meta_box( $term ) {
        ?>
            <tr class="form-field">
                <th scope="row"><label for="wpmastertoolkit_meta_debugger"><?php esc_html_e( 'WPMastertoolkit Meta Debugger', 'wpmastertoolkit' ); ?></label></th>
                <td><?php $this->render_meta_debugger( $term->term_id, 'term' ); ?></td>
            </tr>
        <?php
    }

	/**
	 * Render order meta box
	 * 
	 * @since   2.12.0
	 */
	public function render_order_meta_box( $item_id, $item ) {

		if ( ! $this->user_allowed() ) {
			return;
		}

		if ( ! wpmastertoolkit_is_pro() ) {
			$this->render_fake_meta_debugger();
			return;
		}

		$order_id = $item->get_order_id();

		$this->render_meta_debugger( $order_id . '_' . $item_id, 'order_item_' . $item->get_type() );
	}

	/**
	 * Render order meta box for refund
	 * 
	 * @since   2.12.0
	 */
	public function render_order_meta_box_refund( $refund ) {

		if ( ! $this->user_allowed() ) {
			return;
		}

		if ( ! wpmastertoolkit_is_pro() ) {
			$this->render_fake_meta_debugger();
			return;
		}

		$refund_id = $refund->get_id();

		$this->render_meta_debugger( $refund_id, 'order_item_refund' );
	}

    /**
	 * Render variation meta box
	 * 
	 * @since   2.22.0
	 */
	public function render_variation_meta_box( $loop, $variation_data, $variation ) {

		if ( ! $this->user_allowed() ) {
			return;
		}

		if ( ! wpmastertoolkit_is_pro() ) {
			$this->render_fake_meta_debugger();
			return;
		}

		$this->render_meta_debugger( $variation->ID, 'post' );
	}

	/**
     * 
     * @since   1.4.0
     */
    public function render_meta_debugger( $id, $type ) {

        $meta_debugger_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/meta-debugger.asset.php' );
        wp_enqueue_style( 'WPMastertoolkit_meta_debugger', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/meta-debugger.css', array(), $meta_debugger_assets['version'], 'all' );
        wp_enqueue_script( 'WPMastertoolkit_meta_debugger', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/meta-debugger.js', $meta_debugger_assets['dependencies'], $meta_debugger_assets['version'], true );
        wp_localize_script( 'WPMastertoolkit_meta_debugger', 'wpmastertoolkit_meta_debugger', array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( $this->nonce ),
            'action'  => $this->action,
        ) );

        ?>
            <div class="wpmastertoolkit-meta-debugger">
                <button class="wpmastertoolkit-meta-debugger__button button" data-id="<?php echo esc_attr( $id ); ?>" data-type="<?php echo esc_attr( $type ); ?>" type="button">
                    <?php esc_html_e( 'Show all meta data', 'wpmastertoolkit' ); ?>
                    <div class="spinner"></div>
                </button>
                <div class="wpmastertoolkit-meta-debugger__container"></div>
            </div>
        <?php
    }

	/**
	 * Render fake meta debugger
	 * 
	 * @since   2.13.0
	 */
	public function render_fake_meta_debugger() {

		$meta_debugger_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/meta-debugger.asset.php' );
        wp_enqueue_style( 'WPMastertoolkit_meta_debugger', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/meta-debugger.css', array(), $meta_debugger_assets['version'], 'all' );

		?>
            <div class="wpmastertoolkit-meta-debugger">
                <button class="wpmastertoolkit-meta-debugger__button button not-pro" type="button" disabled>
                    <?php esc_html_e( 'Show all meta data', 'wpmastertoolkit' ); ?>
					<span class="pro-tag"><?php esc_html_e( 'PRO', 'wpmastertoolkit' ); ?></span>
                </button>
            </div>
        <?php
	}

    /**
     * Get meta data
     * 
     * @since   1.4.0
     */
    public function get_meta_data() {

        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, $this->nonce ) ) {
			wp_send_json_error( __( 'Refresh the page and try again.', 'wpmastertoolkit' ), 403 );
        }

        if ( ! $this->user_allowed() ) {
			wp_send_json_error( __( 'You are not allowed to perform this action.', 'wpmastertoolkit' ), 403 );
        }

		$allowed_types = array(
			'post',
			'user',
			'term',
			'comment',
			'order',
			'subscription',
			'order_item_line_item',
			'order_item_shipping',
			'order_item_fee',
			'order_item_refund',
		);
		$pro_types     = array(
			'order',
			'subscription',
			'order_item_line_item',
			'order_item_shipping',
			'order_item_fee',
			'order_item_refund',
		);

		$id   = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
        $type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';

		if ( ! in_array( $type, $allowed_types, true ) ) {
			wp_send_json_error( __( 'Invalid meta data type.', 'wpmastertoolkit' ), 400 );
		}

		if ( in_array( $type, $pro_types, true ) && ! wpmastertoolkit_is_pro() ) {
			wp_send_json_error( __( 'This feature is only available in the PRO version.', 'wpmastertoolkit' ), 403 );
		}

		$composite_types = array( 'order_item_line_item', 'order_item_shipping', 'order_item_fee' );
		$desired_order_id = 0;
		$desired_item_id  = 0;
		if ( in_array( $type, $composite_types, true ) ) {
			if ( ! preg_match( '/^([1-9][0-9]*)_([1-9][0-9]*)$/', $id, $matches ) ) {
				wp_send_json_error( __( 'Invalid meta data ID.', 'wpmastertoolkit' ), 400 );
			}

			$desired_order_id = (int) $matches[1];
			$desired_item_id  = (int) $matches[2];
		} else {
			if ( ! ctype_digit( $id ) || 0 === (int) $id ) {
				wp_send_json_error( __( 'Invalid meta data ID.', 'wpmastertoolkit' ), 400 );
			}

			$id = (int) $id;
		}

		$data = array();

        switch ( $type ) {
            case 'post':
				$post = get_post( $id );
				if ( ! $post ) {
					wp_send_json_error( __( 'The requested object was not found.', 'wpmastertoolkit' ), 404 );
				}
				$pro_post_types = array( 'product_variation', 'shop_order', 'shop_order_refund', 'shop_subscription' );
				if ( in_array( $post->post_type, $pro_post_types, true ) && ! wpmastertoolkit_is_pro() ) {
					wp_send_json_error( __( 'This feature is only available in the PRO version.', 'wpmastertoolkit' ), 403 );
				}
				if ( ! current_user_can( 'edit_post', $id ) ) {
					wp_send_json_error( __( 'You are not allowed to access this object.', 'wpmastertoolkit' ), 403 );
				}
                $data = get_post_meta( $id );
                break;
            case 'user':
				if ( ! get_userdata( $id ) ) {
					wp_send_json_error( __( 'The requested object was not found.', 'wpmastertoolkit' ), 404 );
				}
				if ( ! current_user_can( 'edit_user', $id ) ) {
					wp_send_json_error( __( 'You are not allowed to access this object.', 'wpmastertoolkit' ), 403 );
				}
                $data = get_user_meta( $id );
                break;
            case 'term':
				$term = get_term( $id );
				if ( ! $term || is_wp_error( $term ) ) {
					wp_send_json_error( __( 'The requested object was not found.', 'wpmastertoolkit' ), 404 );
				}
				if ( ! current_user_can( 'edit_term', $id ) ) {
					wp_send_json_error( __( 'You are not allowed to access this object.', 'wpmastertoolkit' ), 403 );
				}
                $data = get_term_meta( $id );
                break;
            case 'comment':
				if ( ! get_comment( $id ) ) {
					wp_send_json_error( __( 'The requested object was not found.', 'wpmastertoolkit' ), 404 );
				}
				if ( ! current_user_can( 'edit_comment', $id ) ) {
					wp_send_json_error( __( 'You are not allowed to access this object.', 'wpmastertoolkit' ), 403 );
				}
                $data = get_comment_meta( $id );
                break;
            case 'order':
				$order = $this->get_order_for_meta_debugger( $id );
				if ( 'shop_order' !== $order->get_type() ) {
					wp_send_json_error( __( 'The requested object was not found.', 'wpmastertoolkit' ), 404 );
				}
				$order_metas = $order->get_meta_data();
				$data        = $this->format_wc_metas( $order_metas );
                break;
            case 'subscription':
				if ( ! function_exists( 'wcs_get_subscription' ) ) {
					wp_send_json_error( __( 'WooCommerce Subscriptions is not available.', 'wpmastertoolkit' ), 400 );
				}
				$subscription = wcs_get_subscription( $id );
				if ( ! $subscription ) {
					wp_send_json_error( __( 'The requested object was not found.', 'wpmastertoolkit' ), 404 );
				}
				if ( 'shop_subscription' !== $subscription->get_type() ) {
					wp_send_json_error( __( 'The requested object was not found.', 'wpmastertoolkit' ), 404 );
				}
				if ( ! $this->current_user_can_edit_order( $subscription ) ) {
					wp_send_json_error( __( 'You are not allowed to access this object.', 'wpmastertoolkit' ), 403 );
				}
				$subscription_metas = $subscription->get_meta_data();
				$data               = $this->format_wc_metas( $subscription_metas );
				break;
            case 'order_item_line_item':
				$order      = $this->get_order_for_meta_debugger( $desired_order_id );
				$order_item = $order->get_item( $desired_item_id );
				if ( ! $order_item || $desired_order_id !== $order_item->get_order_id() || 'line_item' !== $order_item->get_type() ) {
					wp_send_json_error( __( 'The requested object was not found.', 'wpmastertoolkit' ), 404 );
				}

				$custom_meta          = $order_item->get_meta_data();
				$custom_meta_formated = $this->format_wc_metas( $custom_meta );

				$original_meta        = array();
				$original_meta_values = $order_item->get_data();
				$not_original_meta    = array( 'id', 'order_id', 'name', 'meta_data' );

				foreach ( $original_meta_values as $meta_key => $meta_value ) {

					if ( in_array( $meta_key, $not_original_meta, true ) ) {
						continue;
					}

					$original_key = $meta_key;
					switch ( $meta_key ) {
							case 'product_id':
								$original_key = '_product_id';
							break;
							case 'variation_id':
								$original_key = '_variation_id';
							break;
							case 'quantity':
								$original_key = '_qty';
							break;
							case 'tax_class':
								$original_key = '_tax_class';
							break;
							case 'subtotal':
								$original_key = '_line_subtotal';
							break;
							case 'subtotal_tax':
								$original_key = '_line_subtotal_tax';
							break;
							case 'total':
								$original_key = '_line_total';
							break;
							case 'total_tax':
								$original_key = '_line_tax';
							break;
							case 'taxes':
								$original_key = '_line_tax_data';
							break;
					}

					$original_meta[ $original_key ] = array( $meta_value );
				}

				$data = array_merge( $original_meta, $custom_meta_formated );
                break;
			case 'order_item_shipping':
				$order      = $this->get_order_for_meta_debugger( $desired_order_id );
				$order_item = $order->get_item( $desired_item_id );
				if ( ! $order_item || $desired_order_id !== $order_item->get_order_id() || 'shipping' !== $order_item->get_type() ) {
					wp_send_json_error( __( 'The requested object was not found.', 'wpmastertoolkit' ), 404 );
				}

				$custom_meta          = $order_item->get_meta_data();
				$custom_meta_formated = $this->format_wc_metas( $custom_meta );

				$original_meta        = array();
				$original_meta_values = $order_item->get_data();
				$not_original_meta    = array( 'id', 'order_id', 'name', 'meta_data' );

				foreach ( $original_meta_values as $meta_key => $meta_value ) {

					if ( in_array( $meta_key, $not_original_meta, true ) ) {
						continue;
					}

					$original_key = $meta_key;
					switch ( $meta_key ) {
							case 'method_id':
								$original_key = 'method_id';
							break;
							case 'instance_id':
								$original_key = 'instance_id';
							break;
							case 'total':
								$original_key = 'cost';
							break;
							case 'total_tax':
								$original_key = 'total_tax';
							break;
							case 'taxes':
								$original_key = 'taxes';
							break;
					}

					$original_meta[ $original_key ] = array( $meta_value );
				}

				$data = array_merge( $original_meta, $custom_meta_formated );
				break;
			case 'order_item_fee':
				$order      = $this->get_order_for_meta_debugger( $desired_order_id );
				$order_item = $order->get_item( $desired_item_id );
				if ( ! $order_item || $desired_order_id !== $order_item->get_order_id() || 'fee' !== $order_item->get_type() ) {
					wp_send_json_error( __( 'The requested object was not found.', 'wpmastertoolkit' ), 404 );
				}

				$custom_meta          = $order_item->get_meta_data();
				$custom_meta_formated = $this->format_wc_metas( $custom_meta );

				$original_meta        = array();
				$original_meta_values = $order_item->get_data();
				$not_original_meta    = array( 'id', 'order_id', 'name', 'meta_data' );

				foreach ( $original_meta_values as $meta_key => $meta_value ) {

					if ( in_array( $meta_key, $not_original_meta, true ) ) {
						continue;
					}

					$original_key = $meta_key;
					switch ( $meta_key ) {
							case 'amount':
								$original_key = '_fee_amount';
							break;
							case 'tax_class':
								$original_key = '_tax_class';
							break;
							case 'tax_status':
								$original_key = '_tax_status';
							break;
							case 'total':
								$original_key = '_line_total';
							break;
							case 'total_tax':
								$original_key = '_line_tax';
							break;
							case 'taxes':
								$original_key = '_line_tax_data';
							break;
					}

					$original_meta[ $original_key ] = array( $meta_value );
				}

				$data = array_merge( $original_meta, $custom_meta_formated );

				break;
			case 'order_item_refund':
				if ( ! function_exists( 'wc_get_order' ) ) {
					wp_send_json_error( __( 'WooCommerce is not available.', 'wpmastertoolkit' ), 400 );
				}
				$order = wc_get_order( $id );
				if ( ! is_a( $order, 'WC_Order_Refund' ) ) {
					wp_send_json_error( __( 'The requested object was not found.', 'wpmastertoolkit' ), 404 );
				}
				$this->get_order_for_meta_debugger( $order->get_parent_id() );

				$custom_meta          = $order->get_meta_data();
				$custom_meta_formated = $this->format_wc_metas( $custom_meta );

				$original_meta        = array();
				$original_meta_values = $order->get_data();
				$original_meta_keys   = array( 'amount', 'reason', 'refunded_by', 'refunded_payment' );

				foreach ( $original_meta_values as $meta_key => $meta_value ) {

					if ( ! in_array( $meta_key, $original_meta_keys, true ) ) {
						continue;
					}

					$original_key = $meta_key;
					switch ( $meta_key ) {
							case 'amount':
								$original_key = '_refund_amount';
							break;
							case 'reason':
								$original_key = '_refund_reason';
							break;
							case 'refunded_by':
								$original_key = '_refunded_by';
							break;
							case 'refunded_payment':
								$original_key = '_refunded_payment';
							break;
					}

					$original_meta[ $original_key ] = array( $meta_value );
				}

				$data = array_merge( $original_meta, $custom_meta_formated );
				break;
        }

        if ( empty( $data ) ) {
			wp_send_json_error( __( 'No meta data found.', 'wpmastertoolkit' ), 404 );
        }

        $result = array();
        foreach ( $data as $meta_name => $meta_value ) {
			$result[ $meta_name ] = $this->format_meta_value( $meta_value[0] );
        }

        wp_send_json_success( $result );
    }

	/**
	 * Is WC order
	 *
	 * @since   2.13.0
	 */
	private function is_wc_order( $post_type ) {
		return in_array( $post_type, array( 'woocommerce_page_wc-orders', 'shop_order' ) );
	}

	/**
	 * Format WC metas
	 * 
	 * @since   2.12.0
	 */
	private function format_wc_metas( $wc_metas ) {
		$item_metas_data = array();

		foreach ( $wc_metas as $item_meta ) {
			$item_meta_data = $item_meta->get_data();
			$item_metas_data[ $item_meta_data['key'] ] = array( $item_meta_data['value'] );
		}

		return $item_metas_data;
	}

	/**
	 * Format a meta value for readable and safe output.
	 *
	 * @since 2.22.0
	 */
	private function format_meta_value( $meta_value ) {
		if ( ! is_string( $meta_value ) || 'a:' !== substr( $meta_value, 0, 2 ) || ! is_serialized( $meta_value ) ) {
			return $meta_value;
		}

		$decoded_value = unserialize( $meta_value, array( 'allowed_classes' => false ) );
		if ( ! is_array( $decoded_value ) || $this->meta_value_contains_object( $decoded_value ) ) {
			return $meta_value;
		}

		return $decoded_value;
	}

	/**
	 * Check a decoded meta value for objects or excessive recursion.
	 *
	 * @since 2.22.0
	 */
	private function meta_value_contains_object( $meta_value, $depth = 0 ) {
		if ( is_object( $meta_value ) || $depth > 20 ) {
			return true;
		}

		if ( ! is_array( $meta_value ) ) {
			return false;
		}

		foreach ( $meta_value as $nested_value ) {
			if ( $this->meta_value_contains_object( $nested_value, $depth + 1 ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get an authorized WooCommerce order.
	 *
	 * @since 2.22.0
	 */
	private function get_order_for_meta_debugger( $order_id ) {
		if ( ! function_exists( 'wc_get_order' ) ) {
			wp_send_json_error( __( 'WooCommerce is not available.', 'wpmastertoolkit' ), 400 );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( __( 'The requested object was not found.', 'wpmastertoolkit' ), 404 );
		}

		if ( ! $this->current_user_can_edit_order( $order ) ) {
			wp_send_json_error( __( 'You are not allowed to access this object.', 'wpmastertoolkit' ), 403 );
		}

		return $order;
	}

	/**
	 * Check whether the current user can edit a WooCommerce order object.
	 *
	 * @since 2.22.0
	 */
	private function current_user_can_edit_order( $order ) {
		$order_type = get_post_type_object( $order->get_type() );
		if ( ! $order_type || empty( $order_type->cap->edit_post ) ) {
			return current_user_can( 'manage_woocommerce' );
		}

		return current_user_can( $order_type->cap->edit_post, $order->get_id() ) || current_user_can( 'manage_woocommerce' );
	}

    /**
     * Is user allowed
     * 
     * @since   1.4.0
     */
    private function user_allowed() {
        /**
         * Filter the allowed roles to access the meta debugger
         *
         * @since 1.4.0
         *
         * @param array    $allowed_roles      Array of allowed roles.
         */
        $allowed_roles      = apply_filters( 'wpmastertoolkit/meta_debugger/allowed_roles', array( 'administrator' ) );
		/**
		 * Filter the additional capability required to access the meta debugger.
		 *
		 * @since 2.22.0
		 *
		 * @param string $required_capability Capability name. Empty to disable this check.
		 */
		$required_capability = apply_filters( 'wpmastertoolkit/meta_debugger/allowed_capability', '' );
        $current_user_roles = wp_get_current_user()->roles;
		$intersect          = is_array( $allowed_roles ) ? array_intersect( $allowed_roles, $current_user_roles ) : array();

		if ( empty( $intersect ) || ! is_string( $required_capability ) ) {
            return false;
        }

		if ( ! empty( $required_capability ) && ! current_user_can( $required_capability ) ) {
			return false;
		}

        return true;
    }
}
