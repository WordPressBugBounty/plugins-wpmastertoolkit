<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Nav Menu Visibility
 * Description: 
 * @since 1.3.0
 */
class WPMastertoolkit_Nav_Menu_Visibility {

    const META_KEY = 'WPMastertoolkit_open_visibility';

    /**
     * Invoke the hooks
     * 
     */
    public function __construct() {

        add_action( 'wp_nav_menu_item_custom_fields', array( $this, 'render_radio_buttons' ), 10, 5 );
        add_action( 'wp_update_nav_menu_item', array( $this, 'save_radio_buttons' ), 10, 3 );
        add_filter( 'wp_get_nav_menu_items', array( $this, 'filter_menu_items_by_login' ), 10, 3 );
    }

    /**
     * Render radio buttons in the menu item settings
     */
    public function render_radio_buttons( $item_id, $menu_item, $depth, $args, $current_object_id ) {

        $visibility = get_post_meta( $item_id, self::META_KEY, true );

        ?>
            <p class="description-wide">
                <strong>WPMasterToolkit: </strong><?php esc_html_e( 'Show this menu item for:', 'wpmastertoolkit' ); ?>
                <br>
                <label>
                    <input type="radio" name="<?php echo sprintf( '%s[%s]', self::META_KEY, esc_attr( $item_id ) ); ?>" value="loggedin" <?php checked( 'loggedin', $visibility ); ?> >
                    <?php esc_html_e( 'Logged In', 'wpmastertoolkit' ); ?>
                </label>
                <label>
                    <input type="radio" name="<?php echo sprintf( '%s[%s]', self::META_KEY, esc_attr( $item_id ) ); ?>" value="loggedout" <?php checked( 'loggedout', $visibility ); ?>>
                    <?php esc_html_e( 'Logged Out', 'wpmastertoolkit' ); ?>
                </label>
                <label>
                    <input type="radio" name="<?php echo sprintf( '%s[%s]', self::META_KEY, esc_attr( $item_id ) ); ?>" value="" <?php checked( '', $visibility ); ?> >
                    <?php esc_html_e( 'Everyone', 'wpmastertoolkit' ); ?>
                </label>
            </p>
        <?php
    }

    /**
     * Save the radio buttons value
     */
    public function save_radio_buttons( $menu_id, $menu_item_db_id, $args ) {

        $value = sanitize_text_field( $_POST[ self::META_KEY ][ $menu_item_db_id ] ?? false );

        if ( $value !== false ) {
            update_post_meta( $menu_item_db_id, self::META_KEY, $value );
        }
    }

    /**
     * Filter menu items based on user login status
     */
    public function filter_menu_items_by_login( $items, $menu, $args ) {

        if ( is_admin() ) {
            return $items;
        }

        foreach ( $items as $key => $item ) {

            $visibility = get_post_meta( $item->ID, self::META_KEY, true );

            if ( $visibility === 'loggedin' && ! is_user_logged_in() ) {
                unset( $items[ $key ] );
            }

            if ( $visibility === 'loggedout' && is_user_logged_in() ) {
                unset( $items[ $key ] );
            }
        }

        return $items;
    }
}
