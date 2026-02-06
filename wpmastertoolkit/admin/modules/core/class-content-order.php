<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Content Order
 * Description: Enable custom ordering of various "hierarchical" content types or those supporting "page attributes". A new 'Order' sub-menu will appear for enabled content type(s).
 * @since 1.4.0
 */
class WPMastertoolkit_Content_Order {

    private $option_id;
    private $header_title;
    private $nonce_action;
    private $settings;
    private $default_settings;

    /**
     * Invoke the hooks
     * 
     * @since    1.4.0
     */
    public function __construct() {
        $this->option_id    = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_content_order';
        $this->nonce_action = $this->option_id . '_action';

        add_action( 'init', array( $this, 'class_init' ) );
        add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
        add_action( 'admin_init', array( $this, 'save_submenu' ) );
        add_action( 'admin_menu', array( $this, 'add_content_order_submenu' ) );
    }

    /**
     * Initialize the class
     * 
     * @since    1.4.0
     * @return   void
     */
    public function class_init() {
        $this->header_title = esc_html__( 'Content Order', 'wpmastertoolkit' );
    }

    /**
     * Add custom "Order" sub-menu for post types
     * 
     * @since 1.4.0
     */
    public function add_content_order_submenu( $context ) {

        $settings            = $this->get_settings();
        $settings_post_types = $settings['post_types'];

        if ( empty( $settings_post_types ) || ! is_array( $settings_post_types ) ) {
            return;
        }

        foreach ( $settings_post_types as $post_type => $enabled ) {

            if ( ! $enabled ) {
                continue;
            }

            $post_type_object = get_post_type_object( $post_type );
            if ( is_object( $post_type_object ) && property_exists( $post_type_object, 'labels' ) ) {
                $post_type_name_plural = $post_type_object->labels->name;
                if ( 'post' == $post_type ) {
                    $hook_suffix = add_posts_page(
                        $post_type_name_plural . ' Order',
                        'Order',
                        'edit_pages',
                        'wpmastertoolkit-custom-order-posts',
                        array( $this, 'custom_order_page_output' )
                    );
                } else {
                    $hook_suffix = add_submenu_page(
                        'edit.php?post_type=' . $post_type,
                        $post_type_name_plural . ' Order',
                        'Order',
                        'edit_pages',
                        'wpmastertoolkit-custom-order-' . $post_type,
                        array( $this, 'custom_order_page_output' ),
                        9999
                    );
                }
            }
        }
    }

    /**
     * Output content for the custom order page for each enabled post types
     * Not using settings API because all done via AJAX
     * 
     * @since 1.4.0
     */
    public function custom_order_page_output() {
        $post_status = array( 'publish', 'future', 'draft', 'pending', 'private' );
        $parent_slug = get_admin_page_parent();

        if ( 'edit.php' == $parent_slug ) {
            $post_type_slug = 'post';
        } elseif ( 'upload.php' == $parent_slug ) {
            $post_type_slug = 'attachment';
            $post_status = array( 'inherit', 'private' );
        } else {
            $post_type_slug = str_replace( 'edit.php?post_type=', '', $parent_slug );
        }

        $args = array(
            'post_type'      => $post_type_slug,
            'posts_per_page' => -1,
            'orderby'        => 'menu_order title',
            'order'          => 'ASC',
            'post_status'    => $post_status,
        );

        if ( 'attachment' != $post_type_slug ) {
            $args['post_parent'] = 0;
        }

        $posts = get_posts( $args );

        $page_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/content-order-page.asset.php' );
		wp_enqueue_style( 'WPMastertoolkit_content_order_page', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/content-order-page.css', array(), $page_assets['version'], 'all' );
		wp_enqueue_script( 'WPMastertoolkit_nested_sortable', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/lib/core/jquery.mjs.nestedSortable.js', array( 'jquery-ui-sortable' ), '2.0.0', true );
		$dependencies = array_merge( $page_assets['dependencies'], array( 'WPMastertoolkit_nested_sortable' ) );
		wp_enqueue_script( 'WPMastertoolkit_content_order_page', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/content-order-page.js', $dependencies, $page_assets['version'], true );

        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
        $this->render_order_page( $posts, $post_type_slug );
        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/footer.php';
    }

    /**
     * Render the order page
     * 
     * @since   1.4.0
     */
    public function render_order_page( $posts, $slug ) {

        ?>
            <div class="wp-mastertoolkit__section">
                <div class="wp-mastertoolkit__section__body">
                    <ul class="wp-mastertoolkit__sortable" id="JS-wp-mastertoolkit-sortable">
                        <?php
                            foreach ( $posts as $post ) {
                                $this->render_order_page_item( $post );
                            }
                        ?>
                    </ul>
                </div>
            </div>
        <?php
    }

    /**
     * Render the order page item
     * 
     * @since   1.4.0
     */
    private function render_order_page_item( $post ) {
        
        $post_type_object = get_post_type_object( $post->post_type );
        $has_child        = 'false';

        if ( is_post_type_hierarchical( $post->post_type ) ) {
            $args = array(
                'post_type' => $post->post_type,
                'child_of'  => $post->ID,
            );
            if ( ! empty( get_pages( $args ) ) ) {
                $has_child = 'true';
            }
        }
        
        ?>
            <li class="wp-mastertoolkit__sortable__item">
                <div class="wp-mastertoolkit__sortable__item__container">
                	<div class="wp-mastertoolkit__sortable__item__container__header">
                		<div class="wp-mastertoolkit__sortable__item__container__header__left">
							<div class="wp-mastertoolkit__sortable__item__handle">
								<?php
									//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									echo file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/drag.svg' );
								?>
							</div>
							<a class="wp-mastertoolkit__sortable__item__title link" href="<?php echo esc_attr( get_edit_post_link( $post->ID ) ); ?>"><?php echo esc_html( $post->post_title ); ?></a>
							<?php if ( $post->post_status != 'publish' && 'attachment' != $post->post_type ):
							$post_status_object = get_post_status_object( $post->post_status );
							$post_status_label  = ' — ' . $post_status_object->label;
							?>
								<div class="wp-mastertoolkit__sortable__item__status"><?php echo esc_html( $post_status_label ); ?></div>
							<?php endif; ?>
							<?php if ( $has_child == 'true' ) : ?>
								<div class="wp-mastertoolkit__sortable__item__haschild">
									<?php 
									echo esc_html( sprintf( 
										/* translators: %s: post type label */
										__( 'Has child %s', 'wpmastertoolkit' ), 
										strtolower( $post_type_object->label ) 
									) ); 
									?>
								</div>
							<?php endif; ?>
						</div>
                		<div class="wp-mastertoolkit__sortable__item__container__header__right">
							<div class="wp-mastertoolkit__sortable__item__actions">
								<div class="wp-mastertoolkit__button">
									<a class="wpmtk-button secondary" href="<?php echo esc_attr( get_the_permalink( $post->ID ) ); ?>" target="_blank"><?php echo esc_html__( 'View', 'wpmastertoolkit' ); ?></a>
								</div>
							</div>
						</div>
					</div>

					<input type="hidden" class="wp-mastertoolkit__sortable__item__order" name="WPMastertoolkit_sortable[<?php echo esc_attr( $post->ID ); ?>]" value="<?php echo esc_attr( $post->menu_order ); ?>">
                </div>
            </li>
        <?php
    }

    /**
     * Save sortable
     * 
     * @since   1.4.0
     */
    private function save_sortable_page() {

		//phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $items = wpmastertoolkit_clean( wp_unslash( $_POST['WPMastertoolkit_sortable'] ?? array() ) );

        if ( empty( $items ) ) {
            return;
        }

        foreach ( $items as $id => $order ) {
            wp_update_post( array(
                'ID'         => $id,
                'menu_order' => $order,
            ) );
        }
    }

    /**
     * Add a submenu
     * 
     * @since   1.4.0
     */
    public function add_submenu(){
        WPMastertoolkit_Settings::add_submenu_page(
            'wp-mastertoolkit-settings',
            $this->header_title,
            $this->header_title,
            'manage_options',
            'wp-mastertoolkit-settings-content-order', 
            array( $this, 'render_submenu'),
            null
        );
    }

    /**
     * Render the submenu
     * 
     * @since   1.4.0
     */
    public function render_submenu() {
        $submenu_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/content-order.asset.php' );
        wp_enqueue_style( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/content-order.css', array(), $submenu_assets['version'], 'all' );
        wp_enqueue_script( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/content-order.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );

        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
        $this->submenu_content();
        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/footer.php';
    }

    /**
     * Save the submenu option
     * 
     * @since   1.4.0
     */
    public function save_submenu() {
		$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );

		if ( wp_verify_nonce( $nonce, $this->nonce_action ) ) {
            
            if ( isset( $_POST['WPMastertoolkit_sortable'] ) ) {
                $this->save_sortable_page();
                return;
            }

			//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $new_settings = $this->sanitize_settings( wp_unslash( $_POST[ $this->option_id ] ?? array() ) );
            $this->save_settings( $new_settings );
            wp_safe_redirect( sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) );
			exit;
		}
    }

    /**
     * sanitize_settings
     * 
     * @since   1.4.0
     * @return array
     */
    public function sanitize_settings( $new_settings ){
        $this->default_settings = $this->get_default_settings();
        $sanitized_settings     = array();

        foreach ( $this->default_settings as $settings_key => $settings_value ) {
            
            switch ($settings_key) {
                case 'post_types':
                    foreach ( $settings_value as $post_type => $post_status ) {
                        $sanitized_settings[ $settings_key ][ $post_type ] = sanitize_text_field( $new_settings[ $settings_key ][ $post_type ] ?? '0' );
                    }
                break;
            }
        }

        return $sanitized_settings;
    }

    /**
     * get_settings
     *
     * @since   1.4.0
     * @return void
     */
    private function get_settings(){
        $this->default_settings = $this->get_default_settings();
        return get_option( $this->option_id, $this->default_settings );
    }

    /**
     * Save settings
     * 
     * @since   1.4.0
     */
    private function save_settings( $new_settings ) {
		update_option( $this->option_id, $new_settings );
    }

    /**
     * get_default_settings
     *
     * @since   1.4.0
     * @return array
     */
    private function get_default_settings(){
        if( $this->default_settings !== null ) return $this->default_settings;

        return array(
            'post_types' => $this->get_post_types_settings(),
        );
    }

    /**
     * Add the submenu content
     * 
     * @since   1.4.0
     * @return void
     */
    private function submenu_content() {
        $this->settings = $this->get_settings();
        $post_types     = $this->get_post_types( false );

        ?>
            <div class="wp-mastertoolkit__section">
                <div class="wp-mastertoolkit__section__desc"><?php esc_html_e( "Enable custom ordering of various \"hierarchical\" content types or those supporting \"page attributes\". A new 'Order' sub-menu will appear for enabled content type(s).", 'wpmastertoolkit'); ?></div>
                <div class="wp-mastertoolkit__section__body">
                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__content flex flex-wrap">
                            <?php foreach ( $post_types as $post_type ): ?>
                                <div class="wp-mastertoolkit__checkbox">
                                    <label class="wp-mastertoolkit__checkbox__label">
                                        <input type="hidden" name="<?php echo esc_attr( $this->option_id . '[post_types]['.$post_type->name .']' ); ?>" value="0">
                                        <input type="checkbox" name="<?php echo esc_attr( $this->option_id . '[post_types]['.$post_type->name .']' ); ?>" value="1"<?php checked( $this->settings['post_types'][$post_type->name]??'', '1' ); ?>>
                                        <span class="mark"></span>
                                        <span class="wp-mastertoolkit__checkbox__label__text"><?php echo esc_html($post_type->label); ?> <span>(<?php echo esc_html($post_type->name); ?>)</span></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php
    }

    /**
     * Get the post types with default value
     * 
     * @return  array
     */
    private function get_post_types() {
        $result = array();

        foreach ( get_post_types( array( 'public' => true ), 'names' ) as $post_type ) {
            if ( ( post_type_supports( $post_type, 'page-attributes' ) || is_post_type_hierarchical( $post_type )) ) {
                $result[] = get_post_type_object( $post_type );
            }
        }

        return $result;
    }

    /**
     * Get the post types settings
     * 
     * @since   1.4.0
     * @return  array
     */
    private function get_post_types_settings() {
        $result = array();

        foreach ( $this->get_post_types() as $post_type ) {
            $slug = $post_type->name;
            $result[$slug] = '1';
        }

        return $result;
    }
}
