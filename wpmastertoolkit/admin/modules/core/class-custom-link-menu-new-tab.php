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

			$options = get_post_meta( $item_id, 'WPMastertoolkit_open_new_tab_options', true );
			if ( empty( $options ) ) {

				$old_value = get_post_meta( $item_id, 'WPMastertoolkit_open_new_tab', true );
				if ( '1' == $old_value ) {
					$options = array(
						'target'     => '1',
						'noopener'   => '1',
						'noreferrer' => '1',
						'nofollow'   => '1',
					);
				}
			}

			$target     = $options['target'] ?? '0';
			$noopener   = $options['noopener'] ?? '0';
			$noreferrer = $options['noreferrer'] ?? '0';
			$nofollow   = $options['nofollow'] ?? '0';

            ?>
                <p class="description-wide">
                    <label>
                        <input type="hidden" name="WPMastertoolkit_open_new_tab_options[<?php echo esc_attr( $item_id ) ;?>][target]" value="0">
                        <input type="checkbox" name="WPMastertoolkit_open_new_tab_options[<?php echo esc_attr( $item_id ) ;?>][target]" value="1" <?php echo checked( $target, '1', false );?> />
                        <strong>WPMasterToolkit: </strong><?php esc_html_e( 'Add "target=_blank"', 'wpmastertoolkit' ); ?>
                    </label>
                </p>
                <p class="description-wide">
                    <label>
                        <input type="hidden" name="WPMastertoolkit_open_new_tab_options[<?php echo esc_attr( $item_id ) ;?>][noopener]" value="0">
                        <input type="checkbox" name="WPMastertoolkit_open_new_tab_options[<?php echo esc_attr( $item_id ) ;?>][noopener]" value="1" <?php echo checked( $noopener, '1', false );?> />
                        <strong>WPMasterToolkit: </strong><?php esc_html_e( 'Add "rel=noopener"', 'wpmastertoolkit' ); ?>
                    </label>
                </p>
                <p class="description-wide">
                    <label>
                        <input type="hidden" name="WPMastertoolkit_open_new_tab_options[<?php echo esc_attr( $item_id ) ;?>][noreferrer]" value="0">
                        <input type="checkbox" name="WPMastertoolkit_open_new_tab_options[<?php echo esc_attr( $item_id ) ;?>][noreferrer]" value="1" <?php echo checked( $noreferrer, '1', false );?> />
                        <strong>WPMasterToolkit: </strong><?php esc_html_e( 'Add "rel=noreferrer"', 'wpmastertoolkit' ); ?>
                    </label>
                </p>
                <p class="description-wide">
                    <label>
                        <input type="hidden" name="WPMastertoolkit_open_new_tab_options[<?php echo esc_attr( $item_id ) ;?>][nofollow]" value="0">
                        <input type="checkbox" name="WPMastertoolkit_open_new_tab_options[<?php echo esc_attr( $item_id ) ;?>][nofollow]" value="1" <?php echo checked( $nofollow, '1', false );?> />
                        <strong>WPMasterToolkit: </strong><?php esc_html_e( 'Add "rel=nofollow"', 'wpmastertoolkit' ); ?>
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

		//phpcs:ignore WordPress.Security.NonceVerification.Missing
        $options = array_map( 'sanitize_text_field', wp_unslash( $_POST['WPMastertoolkit_open_new_tab_options'][$menu_item_db_id] ?? [] ) );

        if ( ! empty( $options ) ) {
			$value = [
				'target'     => $options['target'] ?? '0',
				'noopener'   => $options['noopener'] ?? '0',
				'noreferrer' => $options['noreferrer'] ?? '0',
				'nofollow'   => $options['nofollow'] ?? '0',
			];

            update_post_meta( $menu_item_db_id, 'WPMastertoolkit_open_new_tab_options', $value );
        }
    }

    /**
     * Add the attributes to the menu item
     * 
     * @since   1.0.0
     */
    public function add_attributes( $atts, $menu_item, $args, $depth ) {

        if ( $menu_item->object === 'custom' ) {

			$options = get_post_meta( $menu_item->ID, 'WPMastertoolkit_open_new_tab_options', true );
			if ( empty( $options ) ) {

				$old_value = get_post_meta( $menu_item->ID, 'WPMastertoolkit_open_new_tab', true );
				if ( '1' == $old_value ) {
					$options = array(
						'target'     => '1',
						'noopener'   => '1',
						'noreferrer' => '1',
						'nofollow'   => '1',
					);
				}
			}

			$target     = $options['target'] ?? '0';
			$noopener   = $options['noopener'] ?? '0';
			$noreferrer = $options['noreferrer'] ?? '0';
			$nofollow   = $options['nofollow'] ?? '0';

			if ( '1' == $target ) {
				$atts['target'] = '_blank';
			}

			$rel = array();
			if ( '1' == $noopener ) {
				$rel[] = 'noopener';
			}
			if ( '1' == $noreferrer ) {
				$rel[] = 'noreferrer';
			}
			if ( '1' == $nofollow ) {
				$rel[] = 'nofollow';
			}
			if ( ! empty( $rel ) ) {
				$atts['rel'] = implode( ' ', $rel );
			}
        }

        return $atts;
    }
}
