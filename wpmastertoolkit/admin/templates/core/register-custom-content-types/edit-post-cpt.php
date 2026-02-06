<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

global $post;
$wpmtk_title        = get_the_title( $post->ID );
$wpmtk_settings     = $this->get_settings_cpt( $post->ID );
$wpmtk_settings     = is_array( $wpmtk_settings ) ? $wpmtk_settings : array();
$wpmtk_post_status  = get_post_status( $post->ID );
$wpmtk_post_status  = $wpmtk_post_status == 'draft' && empty( $wpmtk_settings['name'] ) ? 'publish' : $wpmtk_post_status;

?>
<hr><br>
<header class="wp-mastertoolkit__header wpmtk-edit-post-header">
    
    <div class="wp-mastertoolkit__header__center">
        <div class="wp-mastertoolkit__header__left__title">
            <input type="text" name="post_title" value="<?php echo esc_attr( $wpmtk_title ); ?>" placeholder="<?php esc_html_e( 'Enter a title', 'wpmastertoolkit' ); ?>" required>
        </div>
    </div>
    
    <a class="wp-mastertoolkit__header__submit-delete" href="<?php echo esc_url( get_delete_post_link( $post->ID ) ); ?>" onclick="return confirm('<?php esc_html_e( 'Are you sure you want to delete this content type?', 'wpmastertoolkit' ); ?>');">
        <?php esc_html_e( 'Delete', 'wpmastertoolkit' ); ?>
    </a>

    <div class="wp-mastertoolkit__header__right__save">
        <?php submit_button( esc_html__('Save', 'wpmastertoolkit') ); ?>
    </div>
    
</header>

<div class="wp-mastertoolkit__section">
    <div class="wp-mastertoolkit__section__body">
        <div class="wp-mastertoolkit__section__body__item">
            <h2>
                <?php esc_html_e( 'General', 'wpmastertoolkit' ); ?>
            </h2>
            <hr>
        </div>

        <div class="wp-mastertoolkit__section__body__item">
            <div class="wp-mastertoolkit__section__body__item__title activable">
                
                <div>
                    <label class="wp-mastertoolkit__toggle">
                        <input type="hidden" name="post_status" value="draft">
                        <input type="checkbox" name="post_status" value="publish" <?php checked( $wpmtk_post_status, 'publish' ); ?>>
                        <span class="wp-mastertoolkit__toggle__slider round"></span>
                    </label>
                </div>
                <div>
                    <?php esc_html_e("Status", 'wpmastertoolkit'); ?>
                </div>
            </div>
            <div class="description">
                <?php esc_html_e( 'If you want to activate the content type, you need to check the status.', 'wpmastertoolkit' ); ?>
            </div>
        </div>

        <div class="wp-mastertoolkit__section__body__item">
            <div class="wp-mastertoolkit__section__body__item__title activable">
                <label class="wp-mastertoolkit__toggle">
                    <input type="hidden" name="<?php echo esc_attr( $this->content_type_settings . '[public]' ); ?>" value="0">
                    <input type="checkbox" name="<?php echo esc_attr( $this->content_type_settings . '[public]' ); ?>" value="1" <?php checked( $wpmtk_settings['public'] ?? 1, 1 ); ?>>
                    <span class="wp-mastertoolkit__toggle__slider round"></span>
                </label>
                <div class="description"><strong><?php esc_html_e( 'Public', 'wpmastertoolkit' ); ?></strong></div>
            </div>
            <div class="description">
                <?php esc_html_e( 'Visible to the public in the admin menu and on the front end.', 'wpmastertoolkit' ); ?>
            </div>
        </div>

        <div class="wp-mastertoolkit__section__body__item">
            <div class="wp-mastertoolkit__section__body__item__title activable">
                <label class="wp-mastertoolkit__toggle">
                    <input type="hidden" name="<?php echo esc_attr( $this->content_type_settings . '[hierarchical]' ); ?>" value="0">
                    <input type="checkbox" name="<?php echo esc_attr( $this->content_type_settings . '[hierarchical]' ); ?>" value="1" <?php checked( $wpmtk_settings['hierarchical'] ?? 0, 1 ); ?>>
                    <span class="wp-mastertoolkit__toggle__slider round"></span>
                </label>
                <div class="description"><strong><?php esc_html_e( 'Hierarchical', 'wpmastertoolkit' ); ?></strong></div>
            </div>
            <div class="description">
                <?php esc_html_e( 'If checked, the content type will be hierarchical like pages. If unchecked, it will be flat like posts.', 'wpmastertoolkit' ); ?>
            </div>
        </div>

        <div class="wp-mastertoolkit__section__body__item">
            <div class="wp-mastertoolkit__select2">
                <div class="description"><strong><?php esc_html_e( 'Taxonomies', 'wpmastertoolkit' ); ?></strong></div>
                <select class="js-multiselect" name="<?php echo esc_attr( $this->content_type_settings . '[taxonomies]' ); ?>[]" multiple>
                    <?php
                    $wpmtk_taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
                    foreach ( $wpmtk_taxonomies as $taxonomy ) {
                        ?>
                        <option value="<?php echo esc_attr( $taxonomy->name ); ?>" <?php selected( in_array( $taxonomy->name, $wpmtk_settings['taxonomies'] ?? array() ) ); ?>>
                            <?php echo esc_html( $taxonomy->label . " (" . $taxonomy->name . ")" ); ?>
                        </option>
                        <?php
                    }
                    ?>
                </select>
            </div>
            <div class="description">
                <?php esc_html_e( 'Select existing taxonomies to associate with this content type.', 'wpmastertoolkit' ); ?>
            </div>
        </div>
        
        <div class="wp-mastertoolkit__section__body__item">
            <div class="wp-mastertoolkit__select2">
                <div class="description"><strong><?php esc_html_e( 'Supports', 'wpmastertoolkit' ); ?></strong></div>
                <select class="js-multiselect-tags" name="<?php echo esc_attr( $this->content_type_settings . '[supports]' ); ?>[]" multiple>
                    <?php
                    $wpmtk_supports = array(
                        'title'           => esc_html__( 'Title', 'wpmastertoolkit' ),
                        'editor'          => esc_html__( 'Editor', 'wpmastertoolkit' ),
                        'author'          => esc_html__( 'Author', 'wpmastertoolkit' ),
                        'thumbnail'       => esc_html__( 'Thumbnail', 'wpmastertoolkit' ),
                        'excerpt'         => esc_html__( 'Excerpt', 'wpmastertoolkit' ),
                        'comments'        => esc_html__( 'Comments', 'wpmastertoolkit' ),
                        'revisions'       => esc_html__( 'Revisions', 'wpmastertoolkit' ),
                        'page-attributes' => esc_html__( 'Page Attributes', 'wpmastertoolkit' ),
                        'custom-fields'   => esc_html__( 'Custom Fields', 'wpmastertoolkit' ),
                    );
                    $wpmtk_new_tag = $wpmtk_settings['supports'] ?? array();
                    if ( !empty( $wpmtk_new_tag ) && is_array( $wpmtk_new_tag ) ) {
                        foreach ( $wpmtk_new_tag as $tag ) {
                            if ( !array_key_exists( $tag, $wpmtk_supports ) ) {
                                $wpmtk_supports[$tag] = $tag;
                            }
                        }
                    }
                    foreach ( $wpmtk_supports as $wpmtk_key => $wpmtk_value ) {
                        ?>
                        <option value="<?php echo esc_attr( $wpmtk_key ); ?>" <?php selected( in_array( $wpmtk_key, $wpmtk_settings['supports'] ?? array() ) ); ?>>
                            <?php echo esc_html( $wpmtk_value ); ?>
                        </option>
                        <?php
                    }
                    ?>
                </select>
            </div>
            <div class="description">
                <?php esc_html_e( 'Select the features you want to enable for this content type. You can also create new features.', 'wpmastertoolkit' ); ?>
            </div>
        </div>
    </div>
</div>

<div class="wp-mastertoolkit__section">
    <div class="wp-mastertoolkit__section__body">
        <div class="wp-mastertoolkit__section__body__item">
            <h2>
                <?php esc_html_e( 'Labels', 'wpmastertoolkit' ); ?>
            </h2>
            <hr>
        </div>

        <?php foreach( $this->get_cpt_labels('required') as $wpmtk_key => $wpmtk_data ) : ?>
        <div class="wp-mastertoolkit__section__body__item">
            <div class="wp-mastertoolkit__input-text">
                <div class="description">
                    <strong><?php echo esc_html( $wpmtk_data['label'] ?? '' ); ?> <?php echo wp_kses_post( !empty( $wpmtk_data['required'] ) ? '<span class="required">*</span>' : '' ); ?></strong>
                </div>
                <input 
                    type="text" 
                    name="<?php echo esc_attr( $this->content_type_settings . '[' . $wpmtk_key . ']' ); ?>" 
                    value="<?php echo esc_attr( $wpmtk_settings[$wpmtk_key] ?? '' ); ?>" 
                    placeholder="<?php echo esc_attr( $wpmtk_data['placeholder'] ?? '' ); ?>" 
                    <?php echo esc_attr( !empty( $wpmtk_data['required'] ) ? 'required' : '' ); ?>
                    <?php
                    if ( !empty( $wpmtk_data['custom-attributes'] ) && is_array( $wpmtk_data['custom-attributes'] ) ) {
                        foreach ( $wpmtk_data['custom-attributes'] as $wpmtk_attr => $wpmtk_value ) {
                            echo esc_attr( $wpmtk_attr ) . '="' . esc_attr( $wpmtk_value ) . '" ';
                        }
                    }
                    ?>
                
                >
            </div>
            <?php if ( isset( $wpmtk_data['description'] ) ) : ?>
            <div class="description">
                <?php echo esc_html( $wpmtk_data['description'] ); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <div class="wp-mastertoolkit__section__body__item">
            <div class="wp-mastertoolkit__input-text">
                <div class="description">
                    <strong><?php esc_html_e( 'Text Domain', 'wpmastertoolkit' ); ?></strong>
                </div>
                <input type="text" name="<?php echo esc_attr( $this->content_type_settings . '[text_domain]' ); ?>" value="<?php echo esc_attr( $wpmtk_settings['text_domain'] ?? '' ); ?>" placeholder="<?php esc_html_e( 'your-text-domain', 'wpmastertoolkit' ); ?>">
            </div>
            <div class="description">
                <?php esc_html_e( "Enter the text domain for translation. Example: 'your-text-domain'.", 'wpmastertoolkit' ); ?>
            </div>
        </div>

        <div class="wp-mastertoolkit__section__body__item">
            <div class="wp-mastertoolkit__section__body__item__title activable">
                <label class="wp-mastertoolkit__toggle">
                    <input type="hidden" name="<?php echo esc_attr( $this->content_type_settings . '[manage_optional_labels]' ); ?>" value="0">
                    <input type="checkbox" name="<?php echo esc_attr( $this->content_type_settings . '[manage_optional_labels]' ); ?>" value="1" <?php checked( $wpmtk_settings['manage_optional_labels'] ?? 0, 1 ); ?>>
                    <span class="wp-mastertoolkit__toggle__slider round"></span>
                </label>
                <div class="description"><strong><?php esc_html_e( 'Manage Optional Labels', 'wpmastertoolkit' ); ?></strong></div>
            </div>
            <div class="description">
                <?php esc_html_e( 'If checked, you can manage the optional labels.', 'wpmastertoolkit' ); ?>
            </div>
        </div>
        
        <?php foreach( $this->get_cpt_labels('optional') as $wpmtk_key => $wpmtk_data ) : ?>
        <div class="wp-mastertoolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[manage_optional_labels]' ); ?>=1">
            <div class="wp-mastertoolkit__input-text">
                <div class="description">
                    <strong><?php echo esc_html( $wpmtk_data['label'] ?? '' ); ?> <?php echo wp_kses_post( !empty( $wpmtk_data['required'] ) ? '<span class="required">*</span>' : '' ); ?></strong>
                </div>
                <input 
                    type="text" 
                    name="<?php echo esc_attr( $this->content_type_settings . '[' . $wpmtk_key . ']' ); ?>" 
                    value="<?php echo esc_attr( $wpmtk_settings[$wpmtk_key] ?? '' ); ?>" 
                    placeholder="<?php echo esc_attr( $wpmtk_data['placeholder'] ?? '' ); ?>" 
                    <?php echo esc_attr( !empty( $wpmtk_data['required'] ) ? 'required' : '' ); ?>
                    <?php
                    if ( !empty( $wpmtk_data['custom-attributes'] ) && is_array( $wpmtk_data['custom-attributes'] ) ) {
                        foreach ( $wpmtk_data['custom-attributes'] as $wpmtk_attr => $wpmtk_value ) {
                            echo esc_attr( $wpmtk_attr ) . '="' . esc_attr( $wpmtk_value ) . '" ';
                        }
                    }
                    ?>
                >
            </div>
            <?php if ( isset( $wpmtk_data['description'] ) ) : ?>
            <div class="description">
                <?php echo esc_html( $wpmtk_data['description'] ); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>


<div class="wp-mastertoolkit__section">
    <div class="wp-mastertoolkit__section__body">

        <div class="wp-mastertoolkit__section__body__item">
            <h2>
                <?php esc_html_e( 'Admin Menu', 'wpmastertoolkit' ); ?>
            </h2>
            <hr>
        </div>

        <div class="wp-mastertoolkit__section__body__item">
            <div class="wp-mastertoolkit__section__body__item__title activable">
                
                <div>
                    <label class="wp-mastertoolkit__toggle">
                        <input type="hidden" name="<?php echo esc_attr( $this->content_type_settings . '[show_ui]' ); ?>" value="0">
                        <input type="checkbox" name="<?php echo esc_attr( $this->content_type_settings . '[show_ui]' ); ?>" value="1" <?php checked( $wpmtk_settings['show_ui'] ?? 1, 1 ); ?>>
                        <span class="wp-mastertoolkit__toggle__slider round"></span>
                    </label>
                </div>
                <div>
                    <?php esc_html_e( "Show In UI", 'wpmastertoolkit' ); ?>
                </div>
            </div>
            <div class="description">
                <?php esc_html_e( 'Items can be edited and managed in the admin dashboard.', 'wpmastertoolkit' ); ?>
            </div>
        </div>
        
        <div class="wp-mastertoolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[show_ui]' ); ?>=1">
            <div class="wp-mastertoolkit__section__body__item__title activable">
                
                <div>
                    <label class="wp-mastertoolkit__toggle">
                        <input type="hidden" name="<?php echo esc_attr( $this->content_type_settings . '[show_in_menu]' ); ?>" value="0">
                        <input type="checkbox" name="<?php echo esc_attr( $this->content_type_settings . '[show_in_menu]' ); ?>" value="1" <?php checked( $wpmtk_settings['show_in_menu'] ?? 1, 1 ); ?>>
                        <span class="wp-mastertoolkit__toggle__slider round"></span>
                    </label>
                </div>
                <div>
                    <?php esc_html_e( "Show In Menu", 'wpmastertoolkit' ); ?>
                </div>
            </div>
            <div class="description">
                <?php esc_html_e( 'If checked, the content type will be visible in the admin menu.', 'wpmastertoolkit' ); ?>
            </div>
        </div>

        <div class="wp-mastertoolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[show_ui]' ); ?>=1&<?php echo esc_attr( $this->content_type_settings . '[show_in_menu]' ); ?>=1">
            <div class="wp-mastertoolkit__input-text">
                <div class="description">
                    <strong><?php esc_html_e( 'Admin Menu Parent', 'wpmastertoolkit' ); ?></strong>
                </div>
                <input type="text" name="<?php echo esc_attr( $this->content_type_settings . '[admin_menu_parent]' ); ?>" value="<?php echo esc_attr( $wpmtk_settings['admin_menu_parent'] ?? '' ); ?>" placeholder="<?php esc_html_e( 'edit.php?post_type={parent_page}', 'wpmastertoolkit' ); ?>">
            </div>
            <div class="description">
                <?php esc_html_e( 'Enter the menu parent slug. Example: "edit.php?post_type=page".', 'wpmastertoolkit' ); ?>
            </div>
        </div>

        <div class="wp-mastertoolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[show_ui]' ); ?>=1&<?php echo esc_attr( $this->content_type_settings . '[show_in_menu]' ); ?>=1">
            <div class="wp-mastertoolkit__input-text">
                <div class="description">
                    <strong><?php esc_html_e( 'Admin Menu Position', 'wpmastertoolkit' ); ?></strong>
                </div>
                <input type="number" name="<?php echo esc_attr( $this->content_type_settings . '[menu_position]' ); ?>" value="<?php echo esc_attr( $wpmtk_settings['menu_position'] ?? 5 ); ?>" placeholder="<?php esc_html_e( '5', 'wpmastertoolkit' ); ?>">
            </div>
            <div class="description">
                <?php esc_html_e( 'Enter the menu position. Example: "5".', 'wpmastertoolkit' ); ?>
            </div>
        </div>

        <div class="wp-mastertoolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[show_ui]' ); ?>=1&<?php echo esc_attr( $this->content_type_settings . '[show_in_menu]' ); ?>=1">
            <div class="wp-mastertoolkit__section__body__item__title activable">
                
                <div>
                    <label class="wp-mastertoolkit__toggle">
                        <input type="hidden" name="<?php echo esc_attr( $this->content_type_settings . '[use_dashicon]' ); ?>" value="0">
                        <input type="checkbox" name="<?php echo esc_attr( $this->content_type_settings . '[use_dashicon]' ); ?>" value="1" <?php checked( $wpmtk_settings['use_dashicon'] ?? 1, 1 ); ?>>
                        <span class="wp-mastertoolkit__toggle__slider round"></span>
                    </label>
                </div>
                <div>
                    <?php esc_html_e( "Use Dashicon", 'wpmastertoolkit' ); ?>
                </div>
            </div>
            <div class="description">
                <?php esc_html_e( 'Check this if you want to use a dashicon for the menu item. If unchecked, the menu item will use custom icon URL.', 'wpmastertoolkit' ); ?>
            </div>
        </div>

        <div class="wp-mastertoolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[show_ui]' ); ?>=1&<?php echo esc_attr( $this->content_type_settings . '[show_in_menu]' ); ?>=1&<?php echo esc_attr( $this->content_type_settings . '[use_dashicon]' ); ?>=1">
            <div class="wp-mastertoolkit__select-dashicon">
                <div class="description">
                    <strong><?php esc_html_e( 'Menu Icon', 'wpmastertoolkit' ); ?></strong>
                </div>
                <select class="js-select-dashicon" name="<?php echo esc_attr( $this->content_type_settings . '[menu_icon]' ); ?>">
                    <?php foreach( WPMastertoolkit_Select_Dashicon::get_dashicons() as $wpmtk_class => $wpmtk_label ) : ?>
                        <option value="<?php echo esc_attr( $wpmtk_class ); ?>" <?php selected( $wpmtk_settings['menu_icon'] ?? 'dashicons-admin-post', $wpmtk_class ); ?>>
                            <?php echo esc_html( $wpmtk_label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="description">
                <?php esc_html_e( 'The icon for the menu item.', 'wpmastertoolkit' ); ?>
            </div>
        </div>

        <div class="wp-mastertoolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[show_ui]' ); ?>=1&<?php echo esc_attr( $this->content_type_settings . '[show_in_menu]' ); ?>=1&<?php echo esc_attr( $this->content_type_settings . '[use_dashicon]' ); ?>=0">
            <div class="wp-mastertoolkit__input-text">
                <div class="description">
                    <strong><?php esc_html_e( 'Custom Icon URL', 'wpmastertoolkit' ); ?></strong>
                </div>
                <input type="text" name="<?php echo esc_attr( $this->content_type_settings . '[custom_menu_icon]' ); ?>" value="<?php echo esc_attr( $wpmtk_settings['custom_menu_icon'] ?? '' ); ?>" placeholder="<?php esc_html_e( 'Enter a URL', 'wpmastertoolkit' ); ?>">
            </div>
            <div class="description">
                <?php esc_html_e( 'Enter the URL of the custom icon. Example: "https://example.com/icon.png". Ideally, the icon should be 20x20 pixels.', 'wpmastertoolkit' ); ?>
            </div>
        </div>

        <div class="wp-mastertoolkit__section__body__item">
            <div class="wp-mastertoolkit__section__body__item__title activable">
                
                <div>
                    <label class="wp-mastertoolkit__toggle">
                        <input type="hidden" name="<?php echo esc_attr( $this->content_type_settings . '[show_in_admin_bar]' ); ?>" value="0">
                        <input type="checkbox" name="<?php echo esc_attr( $this->content_type_settings . '[show_in_admin_bar]' ); ?>" value="1" <?php checked( $wpmtk_settings['show_in_admin_bar'] ?? 1, 1 ); ?>>
                        <span class="wp-mastertoolkit__toggle__slider round"></span>
                    </label>
                </div>
                <div>
                    <?php esc_html_e( "Show In Admin Bar", 'wpmastertoolkit' ); ?>
                </div>
            </div>
            <div class="description">
                <?php esc_html_e( 'If checked, the content type will be visible in the admin bar.', 'wpmastertoolkit' ); ?>
            </div>
        </div>

        <div class="wp-mastertoolkit__section__body__item">
            <div class="wp-mastertoolkit__section__body__item__title activable">
                
                <div>
                    <label class="wp-mastertoolkit__toggle">
                        <input type="hidden" name="<?php echo esc_attr( $this->content_type_settings . '[show_in_nav_menus]' ); ?>" value="0">
                        <input type="checkbox" name="<?php echo esc_attr( $this->content_type_settings . '[show_in_nav_menus]' ); ?>" value="1" <?php checked( $wpmtk_settings['show_in_nav_menus'] ?? 1, 1 ); ?>>
                        <span class="wp-mastertoolkit__toggle__slider round"></span>
                    </label>
                </div>
                <div>
                    <?php esc_html_e( "Appearance Menus Support", 'wpmastertoolkit' ); ?>
                </div>
            </div>
            <div class="description">
                <?php esc_html_e( "Allow items to be added to menus in the 'Appearance' > 'Menus' screen. Must be turned on in 'Screen options'.", 'wpmastertoolkit' ); ?>
            </div>
        </div>

        <div class="wp-mastertoolkit__section__body__item">
            <div class="wp-mastertoolkit__section__body__item__title activable">
                
                <div>
                    <label class="wp-mastertoolkit__toggle">
                        <input type="hidden" name="<?php echo esc_attr( $this->content_type_settings . '[exclude_from_search]' ); ?>" value="0">
                        <input type="checkbox" name="<?php echo esc_attr( $this->content_type_settings . '[exclude_from_search]' ); ?>" value="1" <?php checked( $wpmtk_settings['exclude_from_search'] ?? 0, 1 ); ?>>
                        <span class="wp-mastertoolkit__toggle__slider round"></span>
                    </label>
                </div>
                <div>
                    <?php esc_html_e( "Exclude From Search", 'wpmastertoolkit' ); ?>
                </div>
            </div>
            <div class="description">
                <?php esc_html_e( 'If checked, the content type will be excluded from search results.', 'wpmastertoolkit' ); ?>
            </div>
        </div>
    </div>
</div>


<div class="wp-mastertoolkit__section">
    <div class="wp-mastertoolkit__section__body">

        <div class="wp-mastertoolkit__section__body__item">
            <h2>
                <?php esc_html_e( 'Front end', 'wpmastertoolkit' ); ?>
            </h2>
            <hr>
        </div>
        
        <div class="wp-mastertoolkit__section__body__item">
            <div class="wp-mastertoolkit__select">
                <div class="description">
                    <strong><?php esc_html_e( 'Permalink Rewrite', 'wpmastertoolkit' ); ?></strong>
                </div>
                <select name="<?php echo esc_attr( $this->content_type_settings . '[permalink_rewrite]' ); ?>">
                    <option value="post_type_key" <?php selected( $wpmtk_settings['permalink_rewrite'] ?? 'post_type_key', 'post_type_key' ); ?>><?php esc_html_e( 'Post Type Key (default)', 'wpmastertoolkit' ); ?></option>
                    <option value="custom_permalink" <?php selected( $wpmtk_settings['permalink_rewrite'] ?? 'post_type_key', 'custom_permalink' ); ?>><?php esc_html_e( 'Custom Permalink', 'wpmastertoolkit' ); ?></option>
                    <option value="no_permalink" <?php selected( $wpmtk_settings['permalink_rewrite'] ?? 'post_type_key', 'no_permalink' ); ?>><?php esc_html_e( 'No permalink (Prevent URL rewriting)', 'wpmastertoolkit' ); ?></option>
                </select>
            </div>
            <div class="description" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[permalink_rewrite]' ); ?>=post_type_key">
                <?php esc_html_e( 'Rewrite the URL using the post type key as the slug. Your permalink structure will be {slug}.', 'wpmastertoolkit' ); ?>
            </div>
            <div class="description" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[permalink_rewrite]' ); ?>=custom_permalink">
                <?php
					echo esc_html( sprintf(
						/* translators: %s: Home URL */
						__( 'Rewrite the URL using a custom slug defined in the input below. Your permalink structure will be %s/{slug}.', 'wpmastertoolkit' ),
						home_url()
					) );
				?>
            </div>
            <div class="description" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[permalink_rewrite]' ); ?>=no_permalink">
                <?php esc_html_e( 'Permalinks for this taxonomy are disabled.', 'wpmastertoolkit' ); ?>
            </div>
        </div>

        <div class="wp-mastertoolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[permalink_rewrite]' ); ?>=custom_permalink">
            <div class="wp-mastertoolkit__input-text">
                <div class="description">
                    <strong><?php esc_html_e( 'URL Slug', 'wpmastertoolkit' ); ?></strong>
                </div>
                <input type="text" name="<?php echo esc_attr( $this->content_type_settings . '[slug]' ); ?>" value="<?php echo esc_attr( $wpmtk_settings['slug'] ?? '' ); ?>" placeholder="<?php esc_html_e( 'Enter a slug', 'wpmastertoolkit' ); ?>">
                
                <div class="description">
                    <?php esc_html_e( 'Enter the URL slug. Example: "portfolio".', 'wpmastertoolkit' ); ?>
                </div>
            </div>
        </div>

        <div class="wp-mastertoolkit__section__body__item">
            <div class="wp-mastertoolkit__section__body__item__title activable">
                
                <div>
                    <label class="wp-mastertoolkit__toggle">
                        <input type="hidden" name="<?php echo esc_attr( $this->content_type_settings . '[with_front]' ); ?>" value="0">
                        <input type="checkbox" name="<?php echo esc_attr( $this->content_type_settings . '[with_front]' ); ?>" value="1" <?php checked( $wpmtk_settings['with_front'] ?? 1, 1 ); ?>>
                        <span class="wp-mastertoolkit__toggle__slider round"></span>
                    </label>
                </div>
                <div>
                    <?php esc_html_e( "Front URL Prefix", 'wpmastertoolkit' ); ?>
                </div>
            </div>
            <div class="description">
                <?php esc_html_e( 'Alters the permalink structure to add the `WP_Rewrite::$front` prefix to URLs.', 'wpmastertoolkit' ); ?>
            </div>
        </div>
        
        <div class="wp-mastertoolkit__section__body__item">
            <div class="wp-mastertoolkit__section__body__item__title activable">
                
                <div>
                    <label class="wp-mastertoolkit__toggle">
                        <input type="hidden" name="<?php echo esc_attr( $this->content_type_settings . '[feeds]' ); ?>" value="0">
                        <input type="checkbox" name="<?php echo esc_attr( $this->content_type_settings . '[feeds]' ); ?>" value="1" <?php checked( $wpmtk_settings['feeds'] ?? 0, 1 ); ?>>
                        <span class="wp-mastertoolkit__toggle__slider round"></span>
                    </label>
                </div>
                <div>
                    <?php esc_html_e( "Feed URL", 'wpmastertoolkit' ); ?>
                </div>
            </div>
            <div class="description">
                <?php esc_html_e( 'RSS feed URL for the post type items.', 'wpmastertoolkit' ); ?>
            </div>
        </div>
        
        <div class="wp-mastertoolkit__section__body__item">
            <div class="wp-mastertoolkit__section__body__item__title activable">
                
                <div>
                    <label class="wp-mastertoolkit__toggle">
                        <input type="hidden" name="<?php echo esc_attr( $this->content_type_settings . '[pages]' ); ?>" value="0">
                        <input type="checkbox" name="<?php echo esc_attr( $this->content_type_settings . '[pages]' ); ?>" value="1" <?php checked( $wpmtk_settings['with_front'] ?? 1, 1 ); ?>>
                        <span class="wp-mastertoolkit__toggle__slider round"></span>
                    </label>
                </div>
                <div>
                    <?php esc_html_e( "Pagination", 'wpmastertoolkit' ); ?>
                </div>
            </div>
            <div class="description">
                <?php esc_html_e( 'Pagination support for the items URLs such as the archives.', 'wpmastertoolkit' ); ?>
            </div>
        </div>
        
        <div class="wp-mastertoolkit__section__body__item">
            <div class="wp-mastertoolkit__section__body__item__title activable">
                
                <div>
                    <label class="wp-mastertoolkit__toggle">
                        <input type="hidden" name="<?php echo esc_attr( $this->content_type_settings . '[has_archive]' ); ?>" value="0">
                        <input type="checkbox" name="<?php echo esc_attr( $this->content_type_settings . '[has_archive]' ); ?>" value="1" <?php checked( $wpmtk_settings['has_archive'] ?? 0, 1 ); ?>>
                        <span class="wp-mastertoolkit__toggle__slider round"></span>
                    </label>
                </div>
                <div>
                    <?php esc_html_e( "Archive", 'wpmastertoolkit' ); ?>
                </div>
            </div>
            <div class="description">
                <?php esc_html_e( 'If checked, the content type will have an archive page.', 'wpmastertoolkit' ); ?>
            </div>
        </div>

        <div class="wp-mastertoolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[has_archive]' ); ?>=1">
            <div class="wp-mastertoolkit__input-text">
                <div class="description">
                    <strong><?php esc_html_e( 'Archive Slug', 'wpmastertoolkit' ); ?></strong>
                </div>
                <input type="text" name="<?php echo esc_attr( $this->content_type_settings . '[archive_slug]' ); ?>" value="<?php echo esc_attr( $wpmtk_settings['archive_slug'] ?? '' ); ?>" placeholder="<?php esc_html_e( 'Enter a slug', 'wpmastertoolkit' ); ?>">
                
                <div class="description">
                    <?php esc_html_e( 'Enter the archive slug. Example: "portfolio".', 'wpmastertoolkit' ); ?>
                </div>
            </div>
        </div>

        <div class="wp-mastertoolkit__section__body__item">
            <div class="wp-mastertoolkit__section__body__item__title activable">
                
                <div>
                    <label class="wp-mastertoolkit__toggle">
                        <input type="hidden" name="<?php echo esc_attr( $this->content_type_settings . '[publicly_queryable]' ); ?>" value="0">
                        <input type="checkbox" name="<?php echo esc_attr( $this->content_type_settings . '[publicly_queryable]' ); ?>" value="1" <?php checked( $wpmtk_settings['publicly_queryable'] ?? 1, 1 ); ?>>
                        <span class="wp-mastertoolkit__toggle__slider round"></span>
                    </label>
                </div>
                <div>
                    <?php esc_html_e( "Publicly Queryable", 'wpmastertoolkit' ); ?>
                </div>
            </div>
            <div class="description">
                <?php esc_html_e( 'URLs for an item and items can be accessed with a query string.', 'wpmastertoolkit' ); ?>
            </div>
        </div>

        <div class="wp-mastertoolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[publicly_queryable]' ); ?>=1">
            <div class="wp-mastertoolkit__select">
                <div class="description">
                    <strong><?php esc_html_e( 'Query Variable Support', 'wpmastertoolkit' ); ?></strong>
                </div>
                <select name="<?php echo esc_attr( $this->content_type_settings . '[query_var]' ); ?>">
                    <option value="post_type_key" <?php selected( $wpmtk_settings['query_var'] ?? '', 'post_type_key' ); ?>><?php esc_html_e( 'Post Type Key (default)', 'wpmastertoolkit' ); ?></option>
                    <option value="custom_query_var" <?php selected( $wpmtk_settings['query_var'] ?? '', 'custom_query_var' ); ?>><?php esc_html_e( 'Custom Query Variable', 'wpmastertoolkit' ); ?></option>
                    <option value="none" <?php selected( $wpmtk_settings['query_var'] ?? '', 'none' ); ?>><?php esc_html_e( 'No Query Variable', 'wpmastertoolkit' ); ?></option>
                </select>
            </div>
        </div>

        <div class="wp-mastertoolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[publicly_queryable]' ); ?>=1&<?php echo esc_attr( $this->content_type_settings . '[query_var]' ); ?>=custom_query_var">
            <div class="wp-mastertoolkit__input-text">
                <div class="description">
                    <strong><?php esc_html_e( 'Query Variable Name', 'wpmastertoolkit' ); ?></strong>
                </div>
                <input type="text" name="<?php echo esc_attr( $this->content_type_settings . '[query_var_name]' ); ?>" value="<?php echo esc_attr( $wpmtk_settings['query_var_name'] ?? '' ); ?>" placeholder="<?php esc_html_e( 'Enter a slug', 'wpmastertoolkit' ); ?>">
                
                <div class="description">
                    <?php esc_html_e( 'Enter the query variable name. Example: "portfolio".', 'wpmastertoolkit' ); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="wp-mastertoolkit__section">
    <div class="wp-mastertoolkit__section__body">

        <div class="wp-mastertoolkit__section__body__item">
            <h2>
                <?php esc_html_e( 'Permissions', 'wpmastertoolkit' ); ?>
            </h2>
            <hr>
        </div>

        <div class="wp-mastertoolkit__section__body__item">
            <div class="wp-mastertoolkit__section__body__item__title activable">
                
                <div>
                    <label class="wp-mastertoolkit__toggle">
                        <input type="hidden" name="<?php echo esc_attr( $this->content_type_settings . '[rename_capabilities]' ); ?>" value="0">
                        <input type="checkbox" name="<?php echo esc_attr( $this->content_type_settings . '[rename_capabilities]' ); ?>" value="1" <?php checked( $wpmtk_settings['rename_capabilities'] ?? 0, 1 ); ?>>
                        <span class="wp-mastertoolkit__toggle__slider round"></span>
                    </label>
                </div>
                <div>
                    <?php esc_html_e( "Rename Capabilities", 'wpmastertoolkit' ); ?>
                </div>
            </div>
            <div class="description">
                <?php esc_html_e( "By default the capabilities of the post type will inherit the 'Post' capability names, eg. edit_post, delete_posts. Enable to use post type specific capabilities, eg. edit_{singular}, delete_{plural}.", 'wpmastertoolkit' ); ?>
            </div>
        </div>

        <div class="wp-mastertoolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[rename_capabilities]' ); ?>=1">
            <div class="wp-mastertoolkit__input-text">
                <div class="description">
                    <strong><?php esc_html_e( 'Singular Capability Name', 'wpmastertoolkit' ); ?></strong>
                </div>
                <input type="text" name="<?php echo esc_attr( $this->content_type_settings . '[singular_capability_name]' ); ?>" value="<?php echo esc_attr( $wpmtk_settings['singular_capability_name'] ?? '' ); ?>" placeholder="<?php esc_html_e( 'Enter a slug', 'wpmastertoolkit' ); ?>">
                
                <div class="description">
                    <?php esc_html_e( 'Enter the singular capability name. Example: "portfolio".', 'wpmastertoolkit' ); ?>
                </div>
            </div>
        </div>

        <div class="wp-mastertoolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[rename_capabilities]' ); ?>=1">
            <div class="wp-mastertoolkit__input-text">
                <div class="description">
                    <strong><?php esc_html_e( 'Plural Capability Name', 'wpmastertoolkit' ); ?></strong>
                </div>
                <input type="text" name="<?php echo esc_attr( $this->content_type_settings . '[plural_capability_name]' ); ?>" value="<?php echo esc_attr( $wpmtk_settings['plural_capability_name'] ?? '' ); ?>" placeholder="<?php esc_html_e( 'Enter a slug', 'wpmastertoolkit' ); ?>">
                
                <div class="description">
                    <?php esc_html_e( 'Enter the plural capability name. Example: "portfolios".', 'wpmastertoolkit' ); ?>
                </div>
            </div>
        </div>

        <div class="wp-mastertoolkit__section__body__item">
            <div class="wp-mastertoolkit__section__body__item__title activable">
                
                <div>
                    <label class="wp-mastertoolkit__toggle">
                        <input type="hidden" name="<?php echo esc_attr( $this->content_type_settings . '[can_export]' ); ?>" value="0">
                        <input type="checkbox" name="<?php echo esc_attr( $this->content_type_settings . '[can_export]' ); ?>" value="1" <?php checked( $wpmtk_settings['can_export'] ?? 1, 1 ); ?>>
                        <span class="wp-mastertoolkit__toggle__slider round"></span>
                    </label>
                </div>
                <div>
                    <?php esc_html_e( "Can Export", 'wpmastertoolkit' ); ?>
                </div>
            </div>
            <div class="description">
                <?php esc_html_e( "Allow the post type to be exported from 'Tools' > 'Export'.", 'wpmastertoolkit' ); ?>
            </div>
        </div>

        <div class="wp-mastertoolkit__section__body__item">
            <div class="wp-mastertoolkit__section__body__item__title activable">
                
                <div>
                    <label class="wp-mastertoolkit__toggle">
                        <input type="hidden" name="<?php echo esc_attr( $this->content_type_settings . '[delete_with_user]' ); ?>" value="0">
                        <input type="checkbox" name="<?php echo esc_attr( $this->content_type_settings . '[delete_with_user]' ); ?>" value="1" <?php checked( $wpmtk_settings['delete_with_user'] ?? 0, 1 ); ?>>
                        <span class="wp-mastertoolkit__toggle__slider round"></span>
                    </label>
                </div>
                <div>
                    <?php esc_html_e( "Delete with User", 'wpmastertoolkit' ); ?>
                </div>
            </div>
            <div class="description">
                <?php esc_html_e( 'If checked, items of this content type will be deleted when the user is deleted.', 'wpmastertoolkit' ); ?>
            </div>
        </div>
        
    </div>
</div>

<div class="wp-mastertoolkit__section">
    <div class="wp-mastertoolkit__section__body">

        <div class="wp-mastertoolkit__section__body__item">
            <h2>
                <?php esc_html_e( 'REST API', 'wpmastertoolkit' ); ?>
            </h2>
            <hr>
        </div>

        <div class="wp-mastertoolkit__section__body__item">
            <div class="wp-mastertoolkit__section__body__item__title activable">
                
                <div>
                    <label class="wp-mastertoolkit__toggle">
                        <input type="hidden" name="<?php echo esc_attr( $this->content_type_settings . '[show_in_rest]' ); ?>" value="0">
                        <input type="checkbox" name="<?php echo esc_attr( $this->content_type_settings . '[show_in_rest]' ); ?>" value="1" <?php checked( $wpmtk_settings['show_in_rest'] ?? 0, 1 ); ?>>
                        <span class="wp-mastertoolkit__toggle__slider round"></span>
                    </label>
                </div>
                <div>
                    <?php esc_html_e( "Show In REST API", 'wpmastertoolkit' ); ?>
                </div>
            </div>
            <div class="description">
                <?php esc_html_e( 'If checked, the content type will be available in the REST API. Note if you want use Gutenberg editor, you need to enable this option.', 'wpmastertoolkit' ); ?>
            </div>
        </div>

        <div class="wp-mastertoolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[show_in_rest]' ); ?>=1">
            <div class="wp-mastertoolkit__input-text">
                <div class="description">
                    <strong><?php esc_html_e( 'Base URL', 'wpmastertoolkit' ); ?></strong>
                </div>
                <input type="text" name="<?php echo esc_attr( $this->content_type_settings . '[rest_base]' ); ?>" value="<?php echo esc_attr( $wpmtk_settings['rest_base'] ?? '' ); ?>" placeholder="<?php esc_html_e( 'post', 'wpmastertoolkit' ); ?>">
                
                <div class="description">
                    <?php esc_html_e( 'The base URL for the post type REST API URLs.', 'wpmastertoolkit' ); ?>
                </div>
            </div>
        </div>

        <div class="wp-mastertoolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[show_in_rest]' ); ?>=1">
            <div class="wp-mastertoolkit__input-text">
                <div class="description">
                    <strong><?php esc_html_e( 'REST API Namespace', 'wpmastertoolkit' ); ?></strong>
                </div>
                <input type="text" name="<?php echo esc_attr( $this->content_type_settings . '[rest_namespace]' ); ?>" value="<?php echo esc_attr( $wpmtk_settings['rest_namespace'] ?? 'wp/v2' ); ?>" placeholder="<?php esc_html_e( 'wp/v2', 'wpmastertoolkit' ); ?>">
                
                <div class="description">
                    <?php esc_html_e( 'The namespace for the post type REST API URLs.', 'wpmastertoolkit' ); ?>
                </div>
            </div>
        </div>

        <div class="wp-mastertoolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[show_in_rest]' ); ?>=1">
            <div class="wp-mastertoolkit__input-text">
                <div class="description">
                    <strong><?php esc_html_e( 'REST API Controller Class', 'wpmastertoolkit' ); ?></strong>
                </div>
                <input type="text" name="<?php echo esc_attr( $this->content_type_settings . '[rest_controller_class]' ); ?>" value="<?php echo esc_attr( $wpmtk_settings['rest_controller_class'] ?? 'WP_REST_Posts_Controller' ); ?>" placeholder="<?php esc_html_e( 'WP_REST_Posts_Controller', 'wpmastertoolkit' ); ?>">
                
                <div class="description">
                    <?php esc_html_e( 'Optional custom controller to use instead of `WP_REST_Posts_Controller`.', 'wpmastertoolkit' ); ?>
                </div>
            </div>
        </div>
    </div>
</div>