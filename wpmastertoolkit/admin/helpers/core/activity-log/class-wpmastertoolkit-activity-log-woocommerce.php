<?php
/**
 * WooCommerce observers for the activity log.
 *
 * @package WPMasterToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Observe WooCommerce APIs without depending on their storage implementation.
 */
class WPMastertoolkit_Activity_Log_WooCommerce implements WPMastertoolkit_Activity_Log_Provider {

	/**
	 * Keep commerce metadata available even without Pro or WooCommerce.
	 *
	 * @param bool $translate Whether to translate labels.
	 * @return array Event groups.
	 */
	public static function event_groups( $translate = false ) {
		return array(
			'woocommerce'        => array(
				'label'           => $translate ? __( 'WooCommerce', 'wpmastertoolkit' ) : '',
				'pro'             => true,
				'default_enabled' => true,
				'order'           => 50,
				'event_order'     => 0,
				'events'          => self::labels( $translate ),
			),
			'woocommerce_access' => array(
				'label'           => $translate ? __( 'WooCommerce access', 'wpmastertoolkit' ) : '',
				'pro'             => true,
				'default_enabled' => false,
				'order'           => 60,
				'event_order'     => 0,
				'events'          => self::access_labels( $translate ),
			),
		);
	}

	/**
	 * Commerce has no additional retired labels in the current viewer.
	 *
	 * @param bool $translate Whether to translate labels.
	 * @return array Historical labels.
	 */
	public static function historical_labels( $translate = true ) {
		return array();
	}

	/**
	 * Describe commerce-owned context fields.
	 *
	 * @param string $event Event ID.
	 * @return array Detail labels.
	 */
	public static function detail_labels( $event ) {
		return array(
			'order_id'    => __( 'Order ID', 'wpmastertoolkit' ),
			'product_id'  => __( 'Product ID', 'wpmastertoolkit' ),
			'original_id' => __( 'Original product ID', 'wpmastertoolkit' ),
			'zone_id'     => __( 'Shipping zone ID', 'wpmastertoolkit' ),
			'instance_id' => __( 'Shipping method instance ID', 'wpmastertoolkit' ),
			'item_type'   => __( 'Order item type', 'wpmastertoolkit' ),
			'object_type' => __( 'Product type', 'wpmastertoolkit' ),
			'field'       => __( 'Setting', 'wpmastertoolkit' ),
			'method_id'   => __( 'Shipping method', 'wpmastertoolkit' ),
		);
	}

	/**
	 * Pending saves keyed by blog and in-memory object identity.
	 *
	 * @var array
	 */
	private $saves = array();

	/**
	 * Last observed stock quantities keyed by blog and product.
	 *
	 * @var array
	 */
	private $stock = array();

	/**
	 * Safe pre-deletion context for orders and line items.
	 *
	 * @var array
	 */
	private $deleted = array();

	/**
	 * Access events observed in this request.
	 *
	 * @var array
	 */
	private $access = array();

	/**
	 * Raw taxonomy snapshots and direct post-edit snapshots.
	 *
	 * @var array
	 */
	private $terms = array();

	/**
	 * Direct WordPress commerce post updates pending completion.
	 *
	 * @var array
	 */
	private $posts = array();

	/**
	 * Refund deletes awaiting confirmation when the data store has no after-hook.
	 *
	 * @var array
	 */
	private $refunds = array();

	/**
	 * Register observers only for Pro, without requiring WooCommerce to load first.
	 */
	public function register_hooks() {
		if ( ! wpmastertoolkit_is_pro() ) {
			return;
		}
		foreach ( array( 'product', 'coupon', 'order', 'order_refund', 'order_item', 'shipping_zone', 'webhook', 'customer' ) as $type ) {
			add_action( 'woocommerce_before_' . $type . '_object_save', array( $this, 'before_save' ), PHP_INT_MAX );
			add_action( 'woocommerce_after_' . $type . '_object_save', array( $this, 'after_save' ), 10 );
		}
		add_action( 'woocommerce_before_data_object_save', array( $this, 'before_save' ), PHP_INT_MAX );
		add_action( 'woocommerce_after_data_object_save', array( $this, 'after_save' ) );
		foreach ( array( 'product', 'variation' ) as $type ) {
			add_action( 'woocommerce_' . $type . '_before_set_stock', array( $this, 'before_stock' ) );
			add_action( 'woocommerce_' . $type . '_set_stock', array( $this, 'after_stock' ) );
		}
		add_action( 'woocommerce_before_delete_order', array( $this, 'before_delete_order' ), 10, 2 );
		add_action( 'woocommerce_before_trash_order', array( $this, 'before_delete_order' ), 10, 2 );
		add_action( 'woocommerce_delete_order', array( $this, 'order_deleted' ) );
		add_action( 'woocommerce_trash_order', array( $this, 'order_trashed' ) );
		add_action( 'woocommerce_untrash_order', array( $this, 'order_restored' ) );
		add_action( 'deleted_post', array( $this, 'post_deleted' ), 10, 2 );
		add_action( 'trashed_post', array( $this, 'post_trashed' ) );
		add_action( 'untrashed_post', array( $this, 'post_restored' ) );
		add_action( 'woocommerce_before_delete_order_item', array( $this, 'before_delete_item' ) );
		add_action( 'woocommerce_delete_order_item', array( $this, 'item_deleted' ) );
		add_action( 'woocommerce_refund_deleted', array( $this, 'refund_deleted' ), 10, 2 );
		add_action( 'woocommerce_delete_order_refund', array( $this, 'refund_object_deleted' ) );
		add_filter( 'woocommerce_pre_delete_order_refund', array( $this, 'before_refund_delete' ), PHP_INT_MAX, 2 );
		add_action( 'shutdown', array( $this, 'flush_refunds' ) );
		add_action( 'woocommerce_order_note_added', array( $this, 'note_added' ), 10, 2 );
		add_action( 'woocommerce_order_note_deleted', array( $this, 'note_deleted' ), 10, 2 );
		add_action( 'woocommerce_product_duplicate', array( $this, 'product_duplicated' ), 10, 2 );
		add_action( 'woocommerce_webhook_deleted', array( $this, 'webhook_deleted' ), 10, 2 );
		add_action( 'woocommerce_delete_shipping_zone', array( $this, 'zone_deleted' ) );
		add_action( 'updated_option', array( $this, 'option_updated' ), 10, 3 );
		add_action( 'added_option', array( $this, 'option_added' ), 10, 2 );
		add_action( 'deleted_option', array( $this, 'option_deleted' ) );
		add_action( 'woocommerce_attribute_added', array( $this, 'attribute_added' ), 10, 2 );
		add_action( 'woocommerce_attribute_updated', array( $this, 'attribute_updated' ), 10, 2 );
		add_action( 'woocommerce_attribute_deleted', array( $this, 'attribute_deleted' ), 10, 2 );
		add_action( 'woocommerce_tax_rate_added', array( $this, 'tax_added' ), 10, 2 );
		add_action( 'woocommerce_tax_rate_updated', array( $this, 'tax_updated' ), 10, 2 );
		add_action( 'woocommerce_tax_rate_deleted', array( $this, 'tax_deleted' ) );
		add_action( 'woocommerce_shipping_zone_method_added', array( $this, 'shipping_method_added' ), 10, 3 );
		add_action( 'woocommerce_shipping_zone_method_deleted', array( $this, 'shipping_method_deleted' ), 10, 3 );
		add_action( 'wp_head', array( $this, 'product_viewed' ) );
		add_action( 'edit_form_top', array( $this, 'editor_opened' ) );
		add_action( 'woocommerce_download_product', array( $this, 'product_downloaded' ), 10, 3 );
		add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'order_editor_opened' ) );
		add_action( 'pre_post_update', array( $this, 'before_post' ) );
		add_action( 'wp_after_insert_post', array( $this, 'after_post' ), 20, 3 );
		add_action( 'created_term', array( $this, 'term_created' ), 10, 3 );
		add_action( 'edit_terms', array( $this, 'before_term' ), 10, 2 );
		add_action( 'edited_term', array( $this, 'term_saved' ), 10, 3 );
		add_action( 'delete_term', array( $this, 'term_deleted' ), 10, 4 );
	}

	/**
	 * Get explicit lifecycle labels.
	 *
	 * @param bool $translate Whether to translate labels.
	 * @return array Event labels.
	 */
	private static function lifecycle_labels( $translate = false ) {
		return array(
			'woocommerce.product_created'                  => $translate ? __( 'Product or variation created', 'wpmastertoolkit' ) : '',
			'woocommerce.product_deleted'                  => $translate ? __( 'Product or variation deleted', 'wpmastertoolkit' ) : '',
			'woocommerce.product_trashed'                  => $translate ? __( 'Product or variation trashed', 'wpmastertoolkit' ) : '',
			'woocommerce.product_restored'                 => $translate ? __( 'Product or variation restored', 'wpmastertoolkit' ) : '',
			'woocommerce.product_duplicated'               => $translate ? __( 'Product duplicated', 'wpmastertoolkit' ) : '',
			'woocommerce.product_type_changed'             => $translate ? __( 'Product type changed', 'wpmastertoolkit' ) : '',
			'woocommerce.coupon_created'                   => $translate ? __( 'Coupon created', 'wpmastertoolkit' ) : '',
			'woocommerce.coupon_deleted'                   => $translate ? __( 'Coupon deleted', 'wpmastertoolkit' ) : '',
			'woocommerce.coupon_trashed'                   => $translate ? __( 'Coupon trashed', 'wpmastertoolkit' ) : '',
			'woocommerce.coupon_restored'                  => $translate ? __( 'Coupon restored', 'wpmastertoolkit' ) : '',
			'woocommerce.order_created'                    => $translate ? __( 'Order created', 'wpmastertoolkit' ) : '',
			'woocommerce.order_deleted'                    => $translate ? __( 'Order deleted', 'wpmastertoolkit' ) : '',
			'woocommerce.order_trashed'                    => $translate ? __( 'Order trashed', 'wpmastertoolkit' ) : '',
			'woocommerce.order_restored'                   => $translate ? __( 'Order restored', 'wpmastertoolkit' ) : '',
			'woocommerce.refund_created'                   => $translate ? __( 'Order refund created', 'wpmastertoolkit' ) : '',
			'woocommerce.refund_deleted'                   => $translate ? __( 'Order refund deleted', 'wpmastertoolkit' ) : '',
			'woocommerce.order_item_created'               => $translate ? __( 'Order item added', 'wpmastertoolkit' ) : '',
			'woocommerce.order_item_deleted'               => $translate ? __( 'Order item removed', 'wpmastertoolkit' ) : '',
			'woocommerce.order_note_added'                 => $translate ? __( 'Order note added', 'wpmastertoolkit' ) : '',
			'woocommerce.order_note_deleted'               => $translate ? __( 'Order note deleted', 'wpmastertoolkit' ) : '',
			'woocommerce.webhook_created'                  => $translate ? __( 'Webhook created', 'wpmastertoolkit' ) : '',
			'woocommerce.webhook_deleted'                  => $translate ? __( 'Webhook deleted', 'wpmastertoolkit' ) : '',
			'woocommerce.shipping_zone_created'            => $translate ? __( 'Shipping zone created', 'wpmastertoolkit' ) : '',
			'woocommerce.shipping_zone_deleted'            => $translate ? __( 'Shipping zone deleted', 'wpmastertoolkit' ) : '',
			'woocommerce.shipping_method_added'            => $translate ? __( 'Shipping method added to zone', 'wpmastertoolkit' ) : '',
			'woocommerce.shipping_method_deleted'          => $translate ? __( 'Shipping method removed from zone', 'wpmastertoolkit' ) : '',
			'woocommerce.attribute_added'                  => $translate ? __( 'Product attribute added', 'wpmastertoolkit' ) : '',
			'woocommerce.attribute_updated'                => $translate ? __( 'Product attribute configuration changed', 'wpmastertoolkit' ) : '',
			'woocommerce.attribute_deleted'                => $translate ? __( 'Product attribute deleted', 'wpmastertoolkit' ) : '',
			'woocommerce.tax_rate_added'                   => $translate ? __( 'Tax rate added', 'wpmastertoolkit' ) : '',
			'woocommerce.tax_rate_updated'                 => $translate ? __( 'Tax rate configuration changed', 'wpmastertoolkit' ) : '',
			'woocommerce.tax_rate_deleted'                 => $translate ? __( 'Tax rate deleted', 'wpmastertoolkit' ) : '',
			'woocommerce.setting_changed'                  => $translate ? __( 'WooCommerce setting changed', 'wpmastertoolkit' ) : '',
			'woocommerce.payment_gateway_enabled_changed'  => $translate ? __( 'Payment gateway enabled state changed', 'wpmastertoolkit' ) : '',
			'woocommerce.payment_gateway_settings_changed' => $translate ? __( 'Payment gateway settings changed', 'wpmastertoolkit' ) : '',
			'woocommerce.taxonomy_created'                 => $translate ? __( 'Product taxonomy term created', 'wpmastertoolkit' ) : '',
			'woocommerce.taxonomy_deleted'                 => $translate ? __( 'Product taxonomy term deleted', 'wpmastertoolkit' ) : '',
		);
	}

	/**
	 * Describe supplementary CRUD objects.
	 *
	 * @param bool $translate Whether to translate labels.
	 * @return array Field schemas.
	 */
	private static function extra_schemas( $translate = false ) {
		return array(
			'order_item'    => array(
				'quantity'     => array( $translate ? __( 'Order item quantity changed', 'wpmastertoolkit' ) : '', 'text' ),
				'product_id'   => array( $translate ? __( 'Order item product changed', 'wpmastertoolkit' ) : '', 'text' ),
				'variation_id' => array( $translate ? __( 'Order item variation changed', 'wpmastertoolkit' ) : '', 'text' ),
				'total'        => array( $translate ? __( 'Order item total changed', 'wpmastertoolkit' ) : '', 'text' ),
				'subtotal'     => array( $translate ? __( 'Order item subtotal changed', 'wpmastertoolkit' ) : '', 'text' ),
				'tax_class'    => array( $translate ? __( 'Order item tax class changed', 'wpmastertoolkit' ) : '', 'text' ),
				'method_id'    => array( $translate ? __( 'Order item shipping method changed', 'wpmastertoolkit' ) : '', 'text' ),
			),
			'refund'        => array(
				'amount' => array( $translate ? __( 'Order refund amount changed', 'wpmastertoolkit' ) : '', 'text' ),
				'reason' => array( $translate ? __( 'Order refund reason changed', 'wpmastertoolkit' ) : '', 'body' ),
			),
			'webhook'       => array(
				'name'         => array( $translate ? __( 'Webhook name changed', 'wpmastertoolkit' ) : '', 'text' ),
				'status'       => array( $translate ? __( 'Webhook status changed', 'wpmastertoolkit' ) : '', 'text' ),
				'topic'        => array( $translate ? __( 'Webhook topic changed', 'wpmastertoolkit' ) : '', 'text' ),
				'delivery_url' => array( $translate ? __( 'Webhook delivery URL changed', 'wpmastertoolkit' ) : '', 'redacted' ),
			),
			'shipping_zone' => array(
				'zone_name'      => array( $translate ? __( 'Shipping zone name changed', 'wpmastertoolkit' ) : '', 'text' ),
				'zone_order'     => array( $translate ? __( 'Shipping zone order changed', 'wpmastertoolkit' ) : '', 'text' ),
				'zone_locations' => array( $translate ? __( 'Shipping zone locations changed', 'wpmastertoolkit' ) : '', 'summary' ),
			),
			'customer'      => array(
				'billing'  => array( $translate ? __( 'Customer billing details changed', 'wpmastertoolkit' ) : '', 'redacted' ),
				'shipping' => array( $translate ? __( 'Customer shipping details changed', 'wpmastertoolkit' ) : '', 'redacted' ),
			),
			'taxonomy'      => array(
				'name'        => array( $translate ? __( 'Product taxonomy term name changed', 'wpmastertoolkit' ) : '', 'text' ),
				'slug'        => array( $translate ? __( 'Product taxonomy term slug changed', 'wpmastertoolkit' ) : '', 'text' ),
				'description' => array( $translate ? __( 'Product taxonomy term description changed', 'wpmastertoolkit' ) : '', 'body' ),
				'parent'      => array( $translate ? __( 'Product taxonomy term parent changed', 'wpmastertoolkit' ) : '', 'text' ),
			),
		);
	}

	/**
	 * Get individually selectable mutation events.
	 *
	 * @param bool $translate Whether to translate labels.
	 * @return array Event labels.
	 */
	public static function labels( $translate = true ) {
		$labels = self::lifecycle_labels( $translate );
		foreach ( self::schemas( $translate ) + self::extra_schemas( $translate ) as $kind => $fields ) {
			foreach ( $fields as $field => $definition ) {
				$labels[ 'woocommerce.' . $kind . '_' . $field . '_changed' ] = $definition[0];
			}
		}
		return $labels;
	}

	/**
	 * Get opt-in commerce access events.
	 *
	 * @param bool $translate Whether to translate labels.
	 * @return array Event labels.
	 */
	public static function access_labels( $translate = true ) {
		return array(
			'woocommerce.product_viewed'        => $translate ? __( 'Product viewed', 'wpmastertoolkit' ) : '',
			'woocommerce.product_editor_opened' => $translate ? __( 'Product opened in editor', 'wpmastertoolkit' ) : '',
			'woocommerce.order_editor_opened'   => $translate ? __( 'Order opened in editor', 'wpmastertoolkit' ) : '',
			'woocommerce.product_downloaded'    => $translate ? __( 'Product file download requested', 'wpmastertoolkit' ) : '',
		);
	}

	/**
	 * Resolve the owning schema without forcing WooCommerce classes to load.
	 *
	 * @param object $wc_object WooCommerce object.
	 * @return string Object kind.
	 */
	private function kind( $wc_object ) {
		$classes = array(
			'WC_Order_Refund'  => 'refund',
			'WC_Product'       => 'product',
			'WC_Coupon'        => 'coupon',
			'WC_Order'         => 'order',
			'WC_Order_Item'    => 'order_item',
			'WC_Webhook'       => 'webhook',
			'WC_Shipping_Zone' => 'shipping_zone',
			'WC_Customer'      => 'customer',
		);
		foreach ( $classes as $class => $kind ) {
			if ( $wc_object instanceof $class ) {
				return $kind;
			}
		}
		return '';
	}

	/**
	 * Inherit disabled legacy order-event choices without overwriting granular ones.
	 *
	 * @param array $preferences Saved preferences.
	 * @return array Preferences.
	 */
	public static function migrate_preferences( $preferences ) {
		foreach ( array_keys( self::labels( false ) ) as $event ) {
			if ( 'woocommerce.order_created' === $event || isset( $preferences[ $event ] ) ) {
				continue;
			}
			if ( 0 === strpos( $event, 'woocommerce.order_' ) || 0 === strpos( $event, 'woocommerce.refund_' ) ) {
				$value = $preferences['woocommerce.order_status_changed'] ?? null;
				if ( in_array( $value, array( '0', '1' ), true ) ) {
					$preferences[ $event ] = $value;
				}
			}
		}
		return $preferences;
	}

	/**
	 * Observe allowlisted store and gateway configuration, not caches or secrets.
	 *
	 * @param string $option Option name.
	 * @param mixed  $before Previous value.
	 * @param mixed  $after Saved value.
	 */
	public function option_updated( $option, $before, $after ) {
		if ( $before === $after || 0 !== strpos( $option, 'woocommerce_' ) ) {
			return;
		}
		$settings = array( 'woocommerce_currency', 'woocommerce_currency_pos', 'woocommerce_price_thousand_sep', 'woocommerce_price_decimal_sep', 'woocommerce_price_num_decimals', 'woocommerce_default_country', 'woocommerce_store_address', 'woocommerce_store_address_2', 'woocommerce_store_city', 'woocommerce_store_postcode', 'woocommerce_allowed_countries', 'woocommerce_specific_allowed_countries', 'woocommerce_ship_to_countries', 'woocommerce_specific_ship_to_countries', 'woocommerce_default_customer_address', 'woocommerce_calc_taxes', 'woocommerce_prices_include_tax', 'woocommerce_tax_based_on', 'woocommerce_tax_display_shop', 'woocommerce_tax_display_cart', 'woocommerce_tax_round_at_subtotal', 'woocommerce_tax_classes', 'woocommerce_enable_coupons', 'woocommerce_calc_discounts_sequentially', 'woocommerce_manage_stock', 'woocommerce_hold_stock_minutes', 'woocommerce_notify_low_stock', 'woocommerce_notify_no_stock', 'woocommerce_stock_email_recipient', 'woocommerce_notify_low_stock_amount', 'woocommerce_notify_no_stock_amount', 'woocommerce_hide_out_of_stock_items', 'woocommerce_stock_format', 'woocommerce_enable_guest_checkout', 'woocommerce_enable_checkout_login_reminder', 'woocommerce_enable_signup_and_login_from_checkout', 'woocommerce_enable_myaccount_registration', 'woocommerce_registration_generate_username', 'woocommerce_registration_generate_password', 'woocommerce_downloads_grant_access_after_payment', 'woocommerce_file_download_method', 'woocommerce_downloads_require_login', 'woocommerce_shipping_cost_requires_address', 'woocommerce_ship_to_destination', 'woocommerce_cart_page_id', 'woocommerce_checkout_page_id', 'woocommerce_myaccount_page_id', 'woocommerce_terms_page_id', 'woocommerce_checkout_pay_endpoint', 'woocommerce_checkout_order_received_endpoint', 'woocommerce_myaccount_orders_endpoint', 'woocommerce_myaccount_downloads_endpoint', 'woocommerce_myaccount_edit_account_endpoint', 'woocommerce_enable_reviews', 'woocommerce_review_rating_verification_required', 'woocommerce_review_rating_verification_label', 'woocommerce_enable_review_rating', 'woocommerce_review_rating_required' );
		if ( in_array( $option, $settings, true ) ) {
			$this->emit( 'woocommerce.setting_changed', 'setting', $option, array( 'field' => $option ) );
			return;
		}
		if ( ! preg_match( '/^woocommerce_([a-z0-9_-]+)_settings$/', $option, $match ) || ! is_array( $after ) ) {
			return;
		}
		$gateways = function_exists( 'WC' ) && WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : array();
		if ( ! isset( $gateways[ $match[1] ] ) ) {
			return;
		}
		$before      = is_array( $before ) ? $before : array();
		$old_enabled = $before['enabled'] ?? 'no';
		$new_enabled = $after['enabled'] ?? 'no';
		if ( $old_enabled !== $new_enabled ) {
			$this->emit(
				'woocommerce.payment_gateway_enabled_changed',
				'payment_gateway',
				$match[1],
				array(
					'before' => (string) $old_enabled,
					'after'  => (string) $new_enabled,
				)
			);
		}
		unset( $before['enabled'], $after['enabled'] );
		if ( $before !== $after ) {
			$this->emit( 'woocommerce.payment_gateway_settings_changed', 'payment_gateway', $match[1] );
		}
	}

	/**
	 * Observe first-time configuration writes.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value New value.
	 */
	public function option_added( $option, $value ) {
		$this->option_updated( $option, null, $value );
	}

	/**
	 * Observe removed allowlisted settings without retaining their prior values.
	 *
	 * @param string $option Option name.
	 */
	public function option_deleted( $option ) {
		$this->option_updated( $option, 'deleted', null );
	}

	/**
	 * Log successful global attribute creation.
	 *
	 * @param int   $attribute_id Attribute ID.
	 * @param array $data Attribute definition.
	 */
	public function attribute_added( $attribute_id, $data ) {
		$this->emit( 'woocommerce.attribute_added', 'attribute', $attribute_id, array( 'title' => (string) ( $data['attribute_label'] ?? '' ) ) );
	}

	/**
	 * Log global attribute updates.
	 *
	 * @param int   $attribute_id Attribute ID.
	 * @param array $data Attribute definition.
	 */
	public function attribute_updated( $attribute_id, $data ) {
		$this->emit( 'woocommerce.attribute_updated', 'attribute', $attribute_id, array( 'title' => (string) ( $data['attribute_label'] ?? '' ) ) );
	}

	/**
	 * Log completed global attribute deletion.
	 *
	 * @param int    $attribute_id Attribute ID.
	 * @param string $name Attribute name.
	 */
	public function attribute_deleted( $attribute_id, $name ) {
		$this->emit( 'woocommerce.attribute_deleted', 'attribute', $attribute_id, array( 'title' => $name ) );
	}

	/**
	 * Log completed tax-rate creation.
	 *
	 * @param int   $rate_id Rate ID.
	 * @param array $data Rate data.
	 */
	public function tax_added( $rate_id, $data ) {
		$this->emit( 'woocommerce.tax_rate_added', 'tax_rate', $rate_id, array( 'title' => (string) ( $data['tax_rate_name'] ?? '' ) ) );
	}

	/**
	 * Log tax-rate updates.
	 *
	 * @param int   $rate_id Rate ID.
	 * @param array $data Rate data.
	 */
	public function tax_updated( $rate_id, $data ) {
		$this->emit( 'woocommerce.tax_rate_updated', 'tax_rate', $rate_id, array( 'title' => (string) ( $data['tax_rate_name'] ?? '' ) ) );
	}

	/**
	 * Log completed tax-rate deletion.
	 *
	 * @param int $rate_id Rate ID.
	 */
	public function tax_deleted( $rate_id ) {
		$this->emit( 'woocommerce.tax_rate_deleted', 'tax_rate', $rate_id );
	}

	/**
	 * Log shipping method additions with their zone context.
	 *
	 * @param int    $instance_id Method instance.
	 * @param string $method_id Method ID.
	 * @param int    $zone_id Zone ID.
	 */
	public function shipping_method_added( $instance_id, $method_id, $zone_id ) {
		$this->emit(
			'woocommerce.shipping_method_added',
			'shipping_method',
			$instance_id,
			array(
				'method_id' => $method_id,
				'zone_id'   => $zone_id,
			)
		);
	}

	/**
	 * Log shipping method removals with their zone context.
	 *
	 * @param int    $instance_id Method instance.
	 * @param string $method_id Method ID.
	 * @param int    $zone_id Zone ID.
	 */
	public function shipping_method_deleted( $instance_id, $method_id, $zone_id ) {
		$this->emit(
			'woocommerce.shipping_method_deleted',
			'shipping_method',
			$instance_id,
			array(
				'method_id' => $method_id,
				'zone_id'   => $zone_id,
			)
		);
	}

	/**
	 * Observe logged-in product rendering, without visitor identifiers.
	 */
	public function product_viewed() {
		if ( ! is_user_logged_in() || ! is_singular( 'product' ) || is_admin() || is_feed() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		$post = get_queried_object();
		if ( $post instanceof WP_Post && ! post_password_required( $post ) ) {
			$this->emit_access( 'woocommerce.product_viewed', 'product', $post->ID );
		}
	}

	/**
	 * Observe an authorized product editor.
	 *
	 * @param WP_Post $post Edited post.
	 */
	public function editor_opened( $post ) {
		if ( $post instanceof WP_Post && in_array( $post->post_type, array( 'product', 'product_variation' ), true ) && 'auto-draft' !== $post->post_status && current_user_can( 'edit_post', $post->ID ) ) {
			$this->emit_access( 'woocommerce.product_editor_opened', 'product', $post->ID );
		}
	}

	/**
	 * Observe authorized order editors under either storage implementation.
	 *
	 * @param WC_Order $order Order.
	 */
	public function order_editor_opened( $order ) {
		if ( $order instanceof WC_Order && current_user_can( 'edit_shop_orders' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce registers this order-management capability.
			$this->emit_access( 'woocommerce.order_editor_opened', 'order', $order->get_id() );
		}
	}

	/**
	 * Observe a download request without retaining the private first two arguments.
	 *
	 * @param string $email Customer email, not retained.
	 * @param string $order_key Private key, not retained.
	 * @param int    $product_id Product ID.
	 */
	public function product_downloaded( $email, $order_key, $product_id ) {
		if ( is_user_logged_in() && $product_id ) {
			$this->emit_access( 'woocommerce.product_downloaded', 'product', $product_id );
		}
	}

	/**
	 * Deduplicate access rendering within the request.
	 *
	 * @param string $event Event ID.
	 * @param string $kind Object category.
	 * @param int    $object_id Object ID.
	 */
	private function emit_access( $event, $kind, $object_id ) {
		$key = get_current_blog_id() . ':' . $object_id . ':' . $event;
		if ( ! isset( $this->access[ $key ] ) ) {
			$this->access[ $key ] = true;
			$this->emit( $event, $kind, $object_id );
		}
	}

	/**
	 * Read an independent persisted object through its WooCommerce data store.
	 *
	 * @param object $wc_object Object whose state is being observed.
	 * @return object|null Persisted object.
	 */
	private function persisted( $wc_object ) {
		if ( null === $wc_object->get_id() || 'new' === $wc_object->get_id() || ( ! $wc_object->get_id() && ! $wc_object instanceof WC_Shipping_Zone ) ) {
			return null;
		}
		try {
			if ( $wc_object instanceof WC_Product ) {
				return ( new WC_Product_Factory() )->get_product( $wc_object->get_id() );
			}
			$class = get_class( $wc_object );
			return new $class( $wc_object->get_id() );
		} catch ( Exception $exception ) {
			return null;
		}
	}

	/**
	 * Snapshot only fields belonging to the object's safe schema.
	 *
	 * @param object $wc_object Persisted object.
	 * @param string $kind Object kind.
	 * @return array Field snapshots.
	 */
	private function snapshot( $wc_object, $kind ) {
		$schemas  = self::schemas() + self::extra_schemas();
		$snapshot = array();
		foreach ( $schemas[ $kind ] ?? array() as $field => $definition ) {
			$method = 'get_' . $field;
			if ( in_array( $field, array( 'billing', 'shipping' ), true ) ) {
				if ( $wc_object instanceof WC_Order ) {
					$value = $wc_object->get_address( $field );
				} elseif ( $wc_object instanceof WC_Customer ) {
					$value = $wc_object->get_data()[ $field ] ?? array();
				} else {
					continue;
				}
				$snapshot[ $field ] = self::field_value( $value, $definition[1] );
				continue;
			}
			if ( ! is_callable( array( $wc_object, $method ) ) ) {
				continue;
			}
			$value = $wc_object->$method( 'edit' );
			if ( 'attributes' === $field || 'downloads' === $field ) {
				$value = array_map(
					static function ( $entry ) {
						return is_object( $entry ) && is_callable( array( $entry, 'get_data' ) ) ? $entry->get_data() : $entry;
					},
					$value
				);
			}
			$snapshot[ $field ] = self::field_value( $value, $definition[1] );
		}
		if ( 'product' === $kind ) {
			$snapshot['type'] = self::field_value( $wc_object->get_type(), 'text' );
		}
		return $snapshot;
	}

	/**
	 * Capture persisted state before a CRUD save, keyed per object and nesting level.
	 *
	 * @param object $wc_object Object being saved.
	 */
	public function before_save( $wc_object ) {
		$kind = $this->kind( $wc_object );
		if ( ! $kind ) {
			return;
		}
		$old                   = $this->persisted( $wc_object );
		$key                   = get_current_blog_id() . ':' . spl_object_hash( $wc_object );
		$this->saves[ $key ][] = array(
			'kind'   => $kind,
			'id'     => $wc_object->get_id(),
			'before' => $old ? $this->snapshot( $old, $kind ) : array(),
			'actor'  => $this->actor_id(),
			'exists' => $wc_object instanceof WC_Shipping_Zone ? null !== $wc_object->get_id() && 'new' !== $wc_object->get_id() : (bool) $wc_object->get_id(),
		);
	}

	/**
	 * Compare persisted state after save; no-op and timestamp-only updates are silent.
	 *
	 * @param object $wc_object Saved object.
	 */
	public function after_save( $wc_object ) {
		$key = get_current_blog_id() . ':' . spl_object_hash( $wc_object );
		if ( empty( $this->saves[ $key ] ) ) {
			return;
		}
		$previous = array_pop( $this->saves[ $key ] );
		if ( ! $this->saves[ $key ] ) {
			unset( $this->saves[ $key ] );
		}
		$saved = $this->persisted( $wc_object );
		if ( ! $saved || ( ! $saved->get_id() && ! $saved instanceof WC_Shipping_Zone ) ) {
			return;
		}
		$kind = $previous['kind'];
		unset( $this->posts[ get_current_blog_id() . ':' . $saved->get_id() ] );
		$after = $this->snapshot( $saved, $kind );
		if ( ! $previous['exists'] ) {
			if ( isset( self::lifecycle_labels()[ 'woocommerce.' . $kind . '_created' ] ) ) {
				$this->emit_object( 'woocommerce.' . $kind . '_created', $saved, array(), $previous['actor'] );
			}
		} else {
			foreach ( self::changes( $previous['before'], $after ) as $field => $details ) {
				if ( 'product' === $kind && 'stock_quantity' === $field && isset( $this->stock[ get_current_blog_id() . ':' . $saved->get_id() ] ) && $this->stock[ get_current_blog_id() . ':' . $saved->get_id() ] === $after[ $field ]['token'] ) {
					continue;
				}
				$event = 'woocommerce.' . $kind . '_' . $field . '_changed';
				if ( 'order' === $kind && 'status' === $field && 'trash' === $details['before'] ) {
					$event = 'woocommerce.order_restored';
					$this->deleted[ 'restored:' . get_current_blog_id() . ':' . $saved->get_id() ] = true;
				}
				$this->emit_object( $event, $saved, $details, $previous['actor'] );
				if ( 'product' === $kind && 'stock_quantity' === $field ) {
					$this->stock[ get_current_blog_id() . ':' . $saved->get_id() ] = $after[ $field ]['token'];
				}
			}
		}
	}

	/**
	 * Observe direct atomic stock changes outside the normal object-save path.
	 *
	 * @param WC_Product $product Product owning the stock.
	 */
	public function before_stock( $product ) {
		$save_key = get_current_blog_id() . ':' . spl_object_hash( $product );
		foreach ( $this->saves[ $save_key ] ?? array() as $save ) {
			if ( ! $save['exists'] ) {
				return;
			}
		}
		$key   = get_current_blog_id() . ':' . $product->get_id();
		$saved = $this->persisted( $product );
		if ( $saved ) {
			$this->deleted[ 'stock:' . $key ] = array(
				'value' => self::field_value( $saved->get_stock_quantity( 'edit' ), 'text' ),
				'actor' => $this->actor_id(),
			);
		}
	}

	/**
	 * Confirm and emit atomic stock changes without double-counting CRUD updates.
	 *
	 * @param WC_Product $product Product owning the stock.
	 */
	public function after_stock( $product ) {
		$key      = get_current_blog_id() . ':' . $product->get_id();
		$previous = $this->deleted[ 'stock:' . $key ] ?? null;
		unset( $this->deleted[ 'stock:' . $key ] );
		$saved = $this->persisted( $product );
		if ( ! $previous || ! $saved ) {
			return;
		}
		$after = self::field_value( $saved->get_stock_quantity( 'edit' ), 'text' );
		if ( $previous['value']['token'] !== $after['token'] && ( $this->stock[ $key ] ?? '' ) !== $after['token'] ) {
			$this->emit_object(
				'woocommerce.product_stock_quantity_changed',
				$saved,
				array(
					'before' => $previous['value']['value'],
					'after'  => $after['value'],
				),
				$previous['actor']
			);
		}
		$this->stock[ $key ] = $after['token'];
	}

	/**
	 * Capture context before deleting or trashing an order in either data store.
	 *
	 * @param int           $order_id Order ID.
	 * @param WC_Order|null $order Optional order supplied by WooCommerce.
	 */
	public function before_delete_order( $order_id, $order = null ) {
		$order = $order ? $order : wc_get_order( $order_id );
		if ( $order ) {
			$this->deleted[ 'order:' . get_current_blog_id() . ':' . $order_id ] = array(
				'kind'   => $this->kind( $order ),
				'actor'  => $this->actor_id(),
				'parent' => $order->get_parent_id(),
			);
		}
	}

	/**
	 * Log a completed order deletion from its captured context.
	 *
	 * @param int $order_id Order ID.
	 */
	public function order_deleted( $order_id ) {
		$key      = 'order:' . get_current_blog_id() . ':' . $order_id;
		$previous = $this->deleted[ $key ] ?? null;
		unset( $this->deleted[ $key ] );
		if ( $previous && 'order' === $previous['kind'] ) {
			$this->emit( 'woocommerce.order_deleted', 'order', $order_id, array(), $previous['actor'] );
		} elseif ( $previous && 'refund' === $previous['kind'] ) {
			$this->refund_deleted( $order_id, $previous['parent'] );
		}
	}

	/**
	 * Log completed order trashing.
	 *
	 * @param int $order_id Order ID.
	 */
	public function order_trashed( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order && 'trash' === $order->get_status() ) {
			$this->emit_object( 'woocommerce.order_trashed', $order );
		}
	}

	/**
	 * Log completed HPOS order restoration.
	 *
	 * @param int $order_id Order ID.
	 */
	public function order_restored( $order_id ) {
		$key = 'restored:' . get_current_blog_id() . ':' . $order_id;
		if ( isset( $this->deleted[ $key ] ) ) {
			unset( $this->deleted[ $key ] );
			return;
		}
		$order = new WC_Order( $order_id );
		if ( $order && 'trash' !== $order->get_status() ) {
			$this->emit_object( 'woocommerce.order_restored', $order );
		}
	}

	/**
	 * Resolve commerce post ownership for post-backed lifecycle events.
	 *
	 * @param WP_Post $post Post.
	 * @return string Object kind.
	 */
	private function post_kind( $post ) {
		$kinds = array(
			'product'           => 'product',
			'product_variation' => 'product',
			'shop_coupon'       => 'coupon',
		);
		return $post instanceof WP_Post ? ( $kinds[ $post->post_type ] ?? '' ) : '';
	}

	/**
	 * Capture direct WordPress edits not initiated through a WooCommerce save.
	 *
	 * @param int $post_id Post ID.
	 */
	public function before_post( $post_id ) {
		$kind = $this->post_kind( get_post( $post_id ) );
		if ( ! $kind ) {
			return;
		}
		$object = 'product' === $kind ? wc_get_product( $post_id ) : new WC_Coupon( $post_id );
		if ( $object ) {
			$this->posts[ get_current_blog_id() . ':' . $post_id ] = array(
				'kind'        => $kind,
				'before'      => $this->snapshot( $object, $kind ),
				'actor'       => $this->actor_id(),
				'post_status' => get_post_status( $post_id ),
			);
		}
	}

	/**
	 * Complete direct post edits while leaving in-progress CRUD saves to their hook.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Saved post.
	 * @param bool    $update Whether this is an update.
	 */
	public function after_post( $post_id, $post, $update ) {
		$key      = get_current_blog_id() . ':' . $post_id;
		$previous = $this->posts[ $key ] ?? null;
		if ( ! $update || ! $previous || 'trash' === $previous['post_status'] || in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) ) {
			unset( $this->posts[ $key ] );
			return;
		}
		foreach ( $this->saves as $stack ) {
			foreach ( $stack as $save ) {
				if ( $save['kind'] === $previous['kind'] && ( ! $save['exists'] || (int) $save['id'] === (int) $post_id ) ) {
					return;
				}
			}
		}
		unset( $this->posts[ $key ] );
		$object = 'product' === $previous['kind'] ? ( new WC_Product_Factory() )->get_product( $post_id ) : new WC_Coupon( $post_id );
		if ( $object ) {
			foreach ( self::changes( $previous['before'], $this->snapshot( $object, $previous['kind'] ) ) as $field => $details ) {
				$this->emit_object( 'woocommerce.' . $previous['kind'] . '_' . $field . '_changed', $object, $details, $previous['actor'] );
			}
		}
	}

	/**
	 * Identify taxonomy ownership rather than logging commerce terms in Free.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @return bool Whether this is a WooCommerce taxonomy.
	 */
	public static function commerce_taxonomy( $taxonomy ) {
		return in_array( $taxonomy, array( 'product_cat', 'product_tag', 'product_shipping_class', 'product_type', 'product_visibility' ), true ) || 0 === strpos( $taxonomy, 'pa_' );
	}

	/**
	 * Log newly created public product taxonomy terms.
	 *
	 * @param int    $term_id Term ID.
	 * @param int    $taxonomy_id Term taxonomy ID.
	 * @param string $taxonomy Taxonomy.
	 */
	public function term_created( $term_id, $taxonomy_id, $taxonomy ) {
		if ( self::commerce_taxonomy( $taxonomy ) && ! in_array( $taxonomy, array( 'product_type', 'product_visibility' ), true ) ) {
			$term = get_term( $term_id, $taxonomy );
			if ( $term instanceof WP_Term ) {
				$this->emit(
					'woocommerce.taxonomy_created',
					'term',
					$term_id,
					array(
						'taxonomy' => $taxonomy,
						'title'    => $term->name,
					)
				);
			}
		}
	}

	/**
	 * Capture persisted primitives before product-taxonomy edits.
	 *
	 * @param int    $term_id Term ID.
	 * @param string $taxonomy Taxonomy.
	 */
	public function before_term( $term_id, $taxonomy ) {
		if ( self::commerce_taxonomy( $taxonomy ) ) {
			$term = get_term( $term_id, $taxonomy, OBJECT, 'raw' );
			if ( $term instanceof WP_Term ) {
				$this->terms[ get_current_blog_id() . ':' . $term_id . ':' . $taxonomy ] = clone $term;
			}
		}
	}

	/**
	 * Compare product categories, tags, and global attribute term fields.
	 *
	 * @param int    $term_id Term ID.
	 * @param int    $taxonomy_id Term taxonomy ID.
	 * @param string $taxonomy Taxonomy.
	 */
	public function term_saved( $term_id, $taxonomy_id, $taxonomy ) {
		$key    = get_current_blog_id() . ':' . $term_id . ':' . $taxonomy;
		$before = $this->terms[ $key ] ?? null;
		unset( $this->terms[ $key ] );
		$after = get_term( $term_id, $taxonomy, OBJECT, 'raw' );
		if ( ! $before || ! $after instanceof WP_Term ) {
			return;
		}
		foreach ( self::extra_schemas()['taxonomy'] as $field => $definition ) {
			$changes = self::changes( array( $field => self::field_value( $before->$field, $definition[1] ) ), array( $field => self::field_value( $after->$field, $definition[1] ) ) );
			if ( isset( $changes[ $field ] ) ) {
				$this->emit(
					'woocommerce.taxonomy_' . $field . '_changed',
					'term',
					$term_id,
					$changes[ $field ] + array(
						'taxonomy' => $taxonomy,
						'title'    => $after->name,
					)
				);
			}
		}
	}

	/**
	 * Observe completed product taxonomy deletion.
	 *
	 * @param int     $term_id Term ID.
	 * @param int     $taxonomy_id Term taxonomy ID.
	 * @param string  $taxonomy Taxonomy.
	 * @param WP_Term $term Deleted term.
	 */
	public function term_deleted( $term_id, $taxonomy_id, $taxonomy, $term ) {
		if ( self::commerce_taxonomy( $taxonomy ) && $term instanceof WP_Term ) {
			$this->emit(
				'woocommerce.taxonomy_deleted',
				'term',
				$term_id,
				array(
					'taxonomy' => $taxonomy,
					'title'    => $term->name,
				)
			);
		}
	}

	/**
	 * Observe successful product/coupon deletion via the supplied post snapshot.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Deleted post.
	 */
	public function post_deleted( $post_id, $post ) {
		$kind = $this->post_kind( $post );
		if ( $kind && 'auto-draft' !== $post->post_status ) {
			$this->emit( 'woocommerce.' . $kind . '_deleted', $kind, $post_id );
		}
		if ( $post instanceof WP_Post && 'shop_order_refund' === $post->post_type ) {
			$this->refund_deleted( $post_id, $post->post_parent );
		}
	}

	/**
	 * Observe successful product/coupon trashing.
	 *
	 * @param int $post_id Post ID.
	 */
	public function post_trashed( $post_id ) {
		$kind = $this->post_kind( get_post( $post_id ) );
		if ( $kind ) {
			$this->emit( 'woocommerce.' . $kind . '_trashed', $kind, $post_id );
		}
	}

	/**
	 * Observe successful product/coupon and legacy-order restoration.
	 *
	 * @param int $post_id Post ID.
	 */
	public function post_restored( $post_id ) {
		$post = get_post( $post_id );
		$kind = $this->post_kind( $post );
		if ( $kind ) {
			$this->emit( 'woocommerce.' . $kind . '_restored', $kind, $post_id );
		} elseif ( $post && 'shop_order' === $post->post_type && ! ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) ) {
			$this->order_restored( $post_id );
		}
	}

	/**
	 * Capture an item before deletion because after-hooks supply only its ID.
	 *
	 * @param int $item_id Order item ID.
	 */
	public function before_delete_item( $item_id ) {
		$item = WC_Order_Factory::get_order_item( $item_id );
		if ( $item ) {
			$this->deleted[ 'item:' . get_current_blog_id() . ':' . $item_id ] = array(
				'order_id'  => $item->get_order_id(),
				'item_type' => $item->get_type(),
				'actor'     => $this->actor_id(),
			);
		}
	}

	/**
	 * Log a completed item deletion.
	 *
	 * @param int $item_id Order item ID.
	 */
	public function item_deleted( $item_id ) {
		$key     = 'item:' . get_current_blog_id() . ':' . $item_id;
		$context = $this->deleted[ $key ] ?? null;
		unset( $this->deleted[ $key ] );
		if ( $context ) {
			$this->emit(
				'woocommerce.order_item_deleted',
				'order_item',
				$item_id,
				array(
					'order_id'  => $context['order_id'],
					'item_type' => $context['item_type'],
				),
				$context['actor']
			);
		}
	}

	/**
	 * Log completed refund removal.
	 *
	 * @param int $refund_id Refund ID.
	 * @param int $order_id Parent order ID.
	 */
	public function refund_deleted( $refund_id, $order_id ) {
		$key = 'refund:' . get_current_blog_id() . ':' . $refund_id;
		unset( $this->refunds[ get_current_blog_id() . ':' . $refund_id ] );
		if ( ! isset( $this->access[ $key ] ) ) {
			$this->access[ $key ] = true;
			$this->emit( 'woocommerce.refund_deleted', 'refund', $refund_id, array( 'order_id' => $order_id ) );
		}
	}

	/**
	 * Cover direct refund-object deletion as well as the higher-level refund API.
	 *
	 * @param int $refund_id Refund ID.
	 */
	public function refund_object_deleted( $refund_id ) {
		$this->refund_deleted( $refund_id, 0 );
	}

	/**
	 * Observe a requested refund deletion without changing core's veto decision.
	 *
	 * @param mixed           $check Existing deletion decision.
	 * @param WC_Order_Refund $refund Refund object.
	 * @return mixed Unchanged deletion decision.
	 */
	public function before_refund_delete( $check, $refund ) {
		if ( null === $check && $refund instanceof WC_Order_Refund && $refund->get_id() ) {
			$this->refunds[ get_current_blog_id() . ':' . $refund->get_id() ] = array(
				'blog'   => get_current_blog_id(),
				'id'     => $refund->get_id(),
				'parent' => $refund->get_parent_id(),
				'actor'  => $this->actor_id(),
			);
		}
		return $check;
	}

	/**
	 * Confirm completed direct refund deletions on their original blog.
	 */
	public function flush_refunds() {
		foreach ( $this->refunds as $key => $snapshot ) {
			unset( $this->refunds[ $key ] );
			$switched = get_current_blog_id() !== $snapshot['blog'];
			if ( $switched ) {
				switch_to_blog( $snapshot['blog'] );
			}
			try {
				$store = WC_Data_Store::load( 'order-refund' );
				if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
					$exists = (bool) $store->get_order_type( $snapshot['id'] );
				} else {
					$exists = (bool) get_post( $snapshot['id'] );
				}
				if ( ! $exists ) {
					$this->emit( 'woocommerce.refund_deleted', 'refund', $snapshot['id'], array( 'order_id' => $snapshot['parent'] ), $snapshot['actor'] );
				}
			} finally {
				if ( $switched ) {
					restore_current_blog();
				}
			}
		}
	}

	/**
	 * Log a note without storing its private/customer-facing text.
	 *
	 * @param int      $note_id Note ID.
	 * @param WC_Order $order Order.
	 */
	public function note_added( $note_id, $order ) {
		$this->emit( 'woocommerce.order_note_added', 'order_note', $note_id, array( 'order_id' => $order->get_id() ) );
	}

	/**
	 * Log completed note deletion using the supplied note's order ID.
	 *
	 * @param int    $note_id Note ID.
	 * @param object $note Deleted note.
	 */
	public function note_deleted( $note_id, $note ) {
		$this->emit( 'woocommerce.order_note_deleted', 'order_note', $note_id, array( 'order_id' => $note->order_id ?? 0 ) );
	}

	/**
	 * Identify both products involved in duplication.
	 *
	 * @param WC_Product $duplicate New product.
	 * @param WC_Product $original Original product.
	 */
	public function product_duplicated( $duplicate, $original ) {
		$this->emit_object( 'woocommerce.product_duplicated', $duplicate, array( 'original_id' => $original->get_id() ) );
	}

	/**
	 * Log completed webhook deletion without secret or delivery URL data.
	 *
	 * @param int $webhook_id Webhook ID.
	 */
	public function webhook_deleted( $webhook_id ) {
		$this->emit( 'woocommerce.webhook_deleted', 'webhook', $webhook_id );
	}

	/**
	 * Log completed zone deletion.
	 *
	 * @param int $zone_id Zone ID.
	 */
	public function zone_deleted( $zone_id ) {
		$this->emit( 'woocommerce.shipping_zone_deleted', 'shipping_zone', $zone_id );
	}

	/**
	 * Capture safe owning-object context for an event.
	 *
	 * @param string   $event Event ID.
	 * @param object   $wc_object WooCommerce object.
	 * @param array    $details Safe details.
	 * @param int|null $actor Captured actor.
	 */
	private function emit_object( $event, $wc_object, $details = array(), $actor = null ) {
		$kind = $this->kind( $wc_object );
		if ( 'product' === $kind ) {
			$details['title']       = $wc_object->get_name( 'edit' );
			$details['object_type'] = $wc_object->get_type();
		} elseif ( in_array( $kind, array( 'order_item', 'refund' ), true ) ) {
			$details['order_id'] = 'refund' === $kind ? $wc_object->get_parent_id() : $wc_object->get_order_id();
			if ( 'order_item' === $kind ) {
				$details['item_type'] = $wc_object->get_type();
			}
		}
		$this->emit( $event, $kind, $wc_object->get_id(), $details, $actor );
	}

	/**
	 * Preserve actual user attribution, treating background jobs as the system.
	 *
	 * @return int Actor ID.
	 */
	private function actor_id() {
		return wp_doing_cron() || doing_action( 'wp_scheduled_delete' ) ? 0 : get_current_user_id();
	}

	/**
	 * Emit to the existing normalized writer, preserving its Pro and user gates.
	 *
	 * @param string     $event Event ID.
	 * @param string     $kind Object category.
	 * @param int|string $object_id Object ID.
	 * @param array      $details Safe details.
	 * @param int|null   $actor Captured actor ID.
	 */
	private function emit( $event, $kind, $object_id, $details = array(), $actor = null ) {
		if ( ! wpmastertoolkit_is_pro() ) {
			return;
		}
		$actor             = null === $actor ? $this->actor_id() : $actor;
		$details['source'] = defined( 'WP_CLI' ) && WP_CLI ? 'cli' : ( $actor ? 'user' : 'system' );
		do_action( 'wpmastertoolkit/activity_log/event', $event, $actor, $kind, $object_id, $details ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Preserve the established event action.
	}

	/**
	 * Allow only known event values and safe scalar/list context into records.
	 *
	 * @param string $event Event ID.
	 * @param mixed  $details Supplied details.
	 * @return array Bounded details.
	 */
	public static function normalize_details( $event, $details ) {
		if ( ! is_array( $details ) || ! isset( ( self::labels( false ) + self::access_labels( false ) )[ $event ] ) ) {
			return array();
		}
		$mode = '';
		foreach ( self::schemas() + self::extra_schemas() as $kind => $fields ) {
			foreach ( $fields as $field => $definition ) {
				if ( 'woocommerce.' . $kind . '_' . $field . '_changed' === $event ) {
					$mode = $definition[1];
				}
			}
		}
		if ( 'woocommerce.product_type_changed' === $event || 'woocommerce.payment_gateway_enabled_changed' === $event ) {
			$mode = 'text';
		}
		if ( in_array( $event, array( 'woocommerce.setting_changed', 'woocommerce.payment_gateway_settings_changed' ), true ) ) {
			$mode = 'redacted';
		}
		$result = array();
		foreach ( array( 'before', 'after' ) as $field ) {
			$value = $details[ $field ] ?? null;
			if ( 'redacted' === $mode ) {
				$result[ $field ] = 'redacted';
			} elseif ( 'ids' === $mode && is_array( $value ) ) {
				$ids                         = array_values( array_unique( array_map( 'absint', array_filter( $value, 'is_numeric' ) ) ) );
				$result[ $field ]            = array_slice( $ids, 0, 30 );
				$result[ $field . '_total' ] = isset( $details[ $field . '_total' ] ) && is_int( $details[ $field . '_total' ] ) ? max( count( $ids ), $details[ $field . '_total' ] ) : count( $ids );
			} elseif ( in_array( $mode, array( 'body', 'summary' ), true ) && is_scalar( $value ) && ctype_digit( (string) $value ) ) {
				$result[ $field ] = absint( $value );
			} elseif ( in_array( $mode, array( 'text', 'date' ), true ) && is_string( $value ) ) {
				$result[ $field ] = self::bounded_text( $value );
			}
		}
		if ( 'body' === $mode || 'summary' === $mode ) {
			$result['unit'] = 'body' === $mode ? 'bytes' : 'entries';
		}
		foreach ( array( 'order_id', 'product_id', 'original_id', 'zone_id', 'instance_id' ) as $field ) {
			if ( isset( $details[ $field ] ) && is_scalar( $details[ $field ] ) && ctype_digit( (string) $details[ $field ] ) ) {
				$result[ $field ] = absint( $details[ $field ] );
			}
		}
		foreach ( array( 'title', 'object_type', 'item_type', 'field', 'method_id', 'taxonomy' ) as $field ) {
			if ( isset( $details[ $field ] ) && is_string( $details[ $field ] ) ) {
				$result[ $field ] = self::bounded_text( $details[ $field ] );
			}
		}
		if ( isset( $details['source'] ) && in_array( $details['source'], array( 'user', 'system', 'cli' ), true ) ) {
			$result['source'] = $details['source'];
		}
		return $result;
	}

	/**
	 * Bound text without introducing an mbstring dependency.
	 *
	 * @param string $value Text.
	 * @return string Sanitized text.
	 */
	private static function bounded_text( $value ) {
		$value = sanitize_text_field( $value );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 160, 'UTF-8' ) : _mb_substr( $value, 0, 160, 'UTF-8' );
	}

	/**
	 * Describe fields with an event label and a safe value representation.
	 *
	 * @param bool $translate Whether to translate labels.
	 * @return array Field schemas keyed by object kind.
	 */
	private static function schemas( $translate = false ) {
		return array(
			'product' => array(
				'name'               => array( $translate ? __( 'Product name changed', 'wpmastertoolkit' ) : '', 'text' ),
				'slug'               => array( $translate ? __( 'Product slug changed', 'wpmastertoolkit' ) : '', 'text' ),
				'status'             => array( $translate ? __( 'Product status changed', 'wpmastertoolkit' ) : '', 'text' ),
				'description'        => array( $translate ? __( 'Product description changed', 'wpmastertoolkit' ) : '', 'body' ),
				'short_description'  => array( $translate ? __( 'Product short description changed', 'wpmastertoolkit' ) : '', 'body' ),
				'sku'                => array( $translate ? __( 'Product SKU changed', 'wpmastertoolkit' ) : '', 'text' ),
				'regular_price'      => array( $translate ? __( 'Product regular price changed', 'wpmastertoolkit' ) : '', 'text' ),
				'sale_price'         => array( $translate ? __( 'Product sale price changed', 'wpmastertoolkit' ) : '', 'text' ),
				'price'              => array( $translate ? __( 'Product price changed', 'wpmastertoolkit' ) : '', 'text' ),
				'stock_quantity'     => array( $translate ? __( 'Product stock quantity changed', 'wpmastertoolkit' ) : '', 'text' ),
				'stock_status'       => array( $translate ? __( 'Product stock status changed', 'wpmastertoolkit' ) : '', 'text' ),
				'manage_stock'       => array( $translate ? __( 'Product stock management changed', 'wpmastertoolkit' ) : '', 'text' ),
				'backorders'         => array( $translate ? __( 'Product backorder setting changed', 'wpmastertoolkit' ) : '', 'text' ),
				'catalog_visibility' => array( $translate ? __( 'Product catalog visibility changed', 'wpmastertoolkit' ) : '', 'text' ),
				'featured'           => array( $translate ? __( 'Product featured state changed', 'wpmastertoolkit' ) : '', 'text' ),
				'virtual'            => array( $translate ? __( 'Product virtual setting changed', 'wpmastertoolkit' ) : '', 'text' ),
				'downloadable'       => array( $translate ? __( 'Product downloadable setting changed', 'wpmastertoolkit' ) : '', 'text' ),
				'sold_individually'  => array( $translate ? __( 'Product individual-sale setting changed', 'wpmastertoolkit' ) : '', 'text' ),
				'tax_status'         => array( $translate ? __( 'Product tax status changed', 'wpmastertoolkit' ) : '', 'text' ),
				'tax_class'          => array( $translate ? __( 'Product tax class changed', 'wpmastertoolkit' ) : '', 'text' ),
				'weight'             => array( $translate ? __( 'Product weight changed', 'wpmastertoolkit' ) : '', 'text' ),
				'length'             => array( $translate ? __( 'Product length changed', 'wpmastertoolkit' ) : '', 'text' ),
				'width'              => array( $translate ? __( 'Product width changed', 'wpmastertoolkit' ) : '', 'text' ),
				'height'             => array( $translate ? __( 'Product height changed', 'wpmastertoolkit' ) : '', 'text' ),
				'image_id'           => array( $translate ? __( 'Product image changed', 'wpmastertoolkit' ) : '', 'text' ),
				'gallery_image_ids'  => array( $translate ? __( 'Product gallery changed', 'wpmastertoolkit' ) : '', 'ids' ),
				'category_ids'       => array( $translate ? __( 'Product categories changed', 'wpmastertoolkit' ) : '', 'ids' ),
				'tag_ids'            => array( $translate ? __( 'Product tags changed', 'wpmastertoolkit' ) : '', 'ids' ),
				'upsell_ids'         => array( $translate ? __( 'Product upsells changed', 'wpmastertoolkit' ) : '', 'ids' ),
				'cross_sell_ids'     => array( $translate ? __( 'Product cross-sells changed', 'wpmastertoolkit' ) : '', 'ids' ),
				'attributes'         => array( $translate ? __( 'Product attributes changed', 'wpmastertoolkit' ) : '', 'summary' ),
				'default_attributes' => array( $translate ? __( 'Product default attributes changed', 'wpmastertoolkit' ) : '', 'summary' ),
				'downloads'          => array( $translate ? __( 'Product downloadable files changed', 'wpmastertoolkit' ) : '', 'summary' ),
				'download_limit'     => array( $translate ? __( 'Product download limit changed', 'wpmastertoolkit' ) : '', 'text' ),
				'download_expiry'    => array( $translate ? __( 'Product download expiry changed', 'wpmastertoolkit' ) : '', 'text' ),
				'shipping_class_id'  => array( $translate ? __( 'Product shipping class changed', 'wpmastertoolkit' ) : '', 'text' ),
				'parent_id'          => array( $translate ? __( 'Product parent changed', 'wpmastertoolkit' ) : '', 'text' ),
				'purchase_note'      => array( $translate ? __( 'Product purchase note changed', 'wpmastertoolkit' ) : '', 'body' ),
				'reviews_allowed'    => array( $translate ? __( 'Product reviews setting changed', 'wpmastertoolkit' ) : '', 'text' ),
				'low_stock_amount'   => array( $translate ? __( 'Product low-stock threshold changed', 'wpmastertoolkit' ) : '', 'text' ),
				'date_on_sale_from'  => array( $translate ? __( 'Product sale start changed', 'wpmastertoolkit' ) : '', 'date' ),
				'date_on_sale_to'    => array( $translate ? __( 'Product sale end changed', 'wpmastertoolkit' ) : '', 'date' ),
				'date_created'       => array( $translate ? __( 'Product publication date changed', 'wpmastertoolkit' ) : '', 'date' ),
			),
			'coupon'  => array(
				'code'                        => array( $translate ? __( 'Coupon code changed', 'wpmastertoolkit' ) : '', 'redacted' ),
				'description'                 => array( $translate ? __( 'Coupon description changed', 'wpmastertoolkit' ) : '', 'body' ),
				'discount_type'               => array( $translate ? __( 'Coupon discount type changed', 'wpmastertoolkit' ) : '', 'text' ),
				'amount'                      => array( $translate ? __( 'Coupon amount changed', 'wpmastertoolkit' ) : '', 'text' ),
				'individual_use'              => array( $translate ? __( 'Coupon individual-use setting changed', 'wpmastertoolkit' ) : '', 'text' ),
				'product_ids'                 => array( $translate ? __( 'Coupon included products changed', 'wpmastertoolkit' ) : '', 'ids' ),
				'excluded_product_ids'        => array( $translate ? __( 'Coupon excluded products changed', 'wpmastertoolkit' ) : '', 'ids' ),
				'product_categories'          => array( $translate ? __( 'Coupon included categories changed', 'wpmastertoolkit' ) : '', 'ids' ),
				'excluded_product_categories' => array( $translate ? __( 'Coupon excluded categories changed', 'wpmastertoolkit' ) : '', 'ids' ),
				'usage_limit'                 => array( $translate ? __( 'Coupon usage limit changed', 'wpmastertoolkit' ) : '', 'text' ),
				'usage_limit_per_user'        => array( $translate ? __( 'Coupon per-user limit changed', 'wpmastertoolkit' ) : '', 'text' ),
				'limit_usage_to_x_items'      => array( $translate ? __( 'Coupon item limit changed', 'wpmastertoolkit' ) : '', 'text' ),
				'usage_count'                 => array( $translate ? __( 'Coupon usage count changed', 'wpmastertoolkit' ) : '', 'text' ),
				'free_shipping'               => array( $translate ? __( 'Coupon free-shipping setting changed', 'wpmastertoolkit' ) : '', 'text' ),
				'exclude_sale_items'          => array( $translate ? __( 'Coupon sale exclusions changed', 'wpmastertoolkit' ) : '', 'text' ),
				'minimum_amount'              => array( $translate ? __( 'Coupon minimum amount changed', 'wpmastertoolkit' ) : '', 'text' ),
				'maximum_amount'              => array( $translate ? __( 'Coupon maximum amount changed', 'wpmastertoolkit' ) : '', 'text' ),
				'email_restrictions'          => array( $translate ? __( 'Coupon email restrictions changed', 'wpmastertoolkit' ) : '', 'redacted' ),
				'date_expires'                => array( $translate ? __( 'Coupon expiry changed', 'wpmastertoolkit' ) : '', 'date' ),
			),
			'order'   => array(
				'status'         => array( $translate ? __( 'Order status changed', 'wpmastertoolkit' ) : '', 'text' ),
				'currency'       => array( $translate ? __( 'Order currency changed', 'wpmastertoolkit' ) : '', 'text' ),
				'customer_id'    => array( $translate ? __( 'Order customer changed', 'wpmastertoolkit' ) : '', 'text' ),
				'billing'        => array( $translate ? __( 'Order billing details changed', 'wpmastertoolkit' ) : '', 'redacted' ),
				'shipping'       => array( $translate ? __( 'Order shipping details changed', 'wpmastertoolkit' ) : '', 'redacted' ),
				'payment_method' => array( $translate ? __( 'Order payment method changed', 'wpmastertoolkit' ) : '', 'text' ),
				'customer_note'  => array( $translate ? __( 'Order customer note changed', 'wpmastertoolkit' ) : '', 'body' ),
				'total'          => array( $translate ? __( 'Order total changed', 'wpmastertoolkit' ) : '', 'text' ),
				'discount_total' => array( $translate ? __( 'Order discount total changed', 'wpmastertoolkit' ) : '', 'text' ),
				'shipping_total' => array( $translate ? __( 'Order shipping total changed', 'wpmastertoolkit' ) : '', 'text' ),
				'cart_tax'       => array( $translate ? __( 'Order cart tax changed', 'wpmastertoolkit' ) : '', 'text' ),
				'shipping_tax'   => array( $translate ? __( 'Order shipping tax changed', 'wpmastertoolkit' ) : '', 'text' ),
				'date_paid'      => array( $translate ? __( 'Order payment date changed', 'wpmastertoolkit' ) : '', 'date' ),
				'date_completed' => array( $translate ? __( 'Order completion date changed', 'wpmastertoolkit' ) : '', 'date' ),
			),
		);
	}

	/**
	 * Prepare a comparison token and a separate safe display value.
	 *
	 * @param mixed  $value Field value.
	 * @param string $mode Representation mode.
	 * @return array Private comparison token and safe value.
	 */
	public static function field_value( $value, $mode ) {
		if ( 'date' === $mode ) {
			$value = $value instanceof DateTimeInterface ? $value->format( DATE_ATOM ) : '';
		} elseif ( 'ids' === $mode ) {
			$value = is_array( $value ) ? array_values( array_unique( array_map( 'absint', array_filter( $value, 'is_numeric' ) ) ) ) : array();
			sort( $value, SORT_NUMERIC );
		}
		$encoded = (string) wp_json_encode( $value );
		$token   = hash( 'sha256', $encoded );
		if ( 'body' === $mode ) {
			$display = strlen( (string) $value );
		} elseif ( 'redacted' === $mode ) {
			$display = 'redacted';
		} elseif ( 'summary' === $mode ) {
			$display = is_array( $value ) ? count( $value ) : 0;
		} elseif ( 'ids' === $mode ) {
			$display = $value;
		} else {
			$display = is_bool( $value ) ? ( $value ? 'yes' : 'no' ) : ( is_scalar( $value ) ? (string) $value : '' );
		}
		return array(
			'token' => $token,
			'value' => $display,
		);
	}

	/**
	 * Compare snapshots without exposing tokens used for private-field comparison.
	 *
	 * @param array $before Previous field snapshot.
	 * @param array $after Saved field snapshot.
	 * @return array Details keyed by changed field.
	 */
	public static function changes( $before, $after ) {
		$changes = array();
		foreach ( $after as $field => $value ) {
			if ( isset( $before[ $field ] ) && $before[ $field ]['token'] !== $value['token'] ) {
				$changes[ $field ] = array(
					'before' => $before[ $field ]['value'],
					'after'  => $value['value'],
				);
			}
		}
		return $changes;
	}
}
