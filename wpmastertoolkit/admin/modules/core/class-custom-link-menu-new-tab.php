<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Custom Link Menu New Tab
 * Description: Add a checkbox to the menu item settings to open the link in a new tab
 * @since 1.0.0
 */
class WPMastertoolkit_Custom_Link_Menu_New_Tab {

    /**
     * Invoke the hooks
     * 
     * @since   1.0.0
     */
    public function __construct() {

        add_filter( 'wp_nav_menu_item_custom_fields', array( $this, 'render_checkbox' ), 10, 5 );
        add_action( 'wp_update_nav_menu_item', array( $this, 'save_checkbox' ), 10, 3 );
        add_action( 'nav_menu_link_attributes', array( $this, 'add_attributes' ), 10, 4 );
    }

    /**
     * Render a checkbox in the menu item settings
     * 
     * @since   1.0.0
     */
    public function render_checkbox( $item_id, $menu_item, $depth, $args, $current_object_id ) {

        if ( $menu_item->object === 'custom' ) {

            $checked = get_post_meta( $item_id, 'WPMastertoolkit_open_new_tab', true );

            ?>
                <p class="description-wide">
                    <label>
                        <input type="hidden" name="WPMastertoolkit_open_new_tab[<?php echo esc_attr( $item_id ) ;?>]" value="0">
                        <input type="checkbox" name="WPMastertoolkit_open_new_tab[<?php echo esc_attr( $item_id ) ;?>]" value="1" <?php echo checked( $checked, '1', false );?> />
                        <strong>WPMasterToolkit: </strong><?php esc_html_e( 'Open link in new tab and add rel="noopener noreferrer nofollow" attribute.', 'wpmastertoolkit' ); ?>
                    </label>
                </p>
            <?php
        }
    }

    /**
     * Save the checkbox value
     * 
     * @since   1.0.0
     */
    public function save_checkbox( $menu_id, $menu_item_db_id, $args ) {

        $value = sanitize_text_field( $_POST['WPMastertoolkit_open_new_tab'][$menu_item_db_id] ?? false );

        if ( $value !== false ) {
            update_post_meta( $menu_item_db_id, 'WPMastertoolkit_open_new_tab', $value );
        }
    }

    /**
     * Add the attributes to the menu item
     * 
     * @since   1.0.0
     */
    public function add_attributes( $atts, $menu_item, $args, $depth ) {

        if ( $menu_item->object === 'custom' ) {

            $checked = get_post_meta( $menu_item->ID, 'WPMastertoolkit_open_new_tab', true );

            if ( $checked === '1' ) {
                $atts['target'] = '_blank';
                $atts['rel']    = 'noopener noreferrer nofollow';
            }
        }

        return $atts;
    }

}