<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Disable cart fragments scripts
 * Description: 
 * @since 1.3.0
 */
class WPMastertoolkit_Disable_Cart_Fragments_Scripts {

    /**
     * Invoke the hooks
     * 
     */
    public function __construct() {

        add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_woocommerce_cart_fragments' ), PHP_INT_MAX );
        add_action( 'admin_enqueue_scripts', array( $this, 'wc_settings_page' ), PHP_INT_MAX );
    }

    /**
     * Disable Ajax Call from WooCommerce
     */
    public function dequeue_woocommerce_cart_fragments() {

        wp_deregister_script('wc-cart-fragments');
    }

    /**
     * 
     */
    public function wc_settings_page( $hook_suffix ) {

        if ( 'woocommerce_page_wc-settings' === $hook_suffix ) {

            $assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/wc-admin-add-to-cart-behaviour.asset.php' );
            wp_enqueue_script( 'wpmastertoolkit-wc-admin-add-to-cart-behaviour', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/wc-admin-add-to-cart-behaviour.js', $assets['dependencies'], $assets['version'], true );   
        }
    }

    /**
     * activate
     *
     * @return void
     */
    public static function activate(){
        
        update_option( 'woocommerce_cart_redirect_after_add', 'yes' );
        update_option( 'woocommerce_enable_ajax_add_to_cart', 'no' );
    }

    /**
     * deactivate
     *
     * @return void
     */
    public static function deactivate(){

        update_option( 'woocommerce_cart_redirect_after_add', 'no' );
        update_option( 'woocommerce_enable_ajax_add_to_cart', 'yes' );
    }
}
