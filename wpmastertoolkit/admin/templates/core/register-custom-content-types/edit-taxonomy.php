<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

global $post;
$wpmtk_title        = get_the_title( $post->ID );
$wpmtk_settings     = $this->get_settings_taxonomy( $post->ID );
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
                <?php esc_html_e( 'If you want to activate this taxonomy, you need to check the status.', 'wpmastertoolkit' ); ?>
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
                <div class="description"><strong><?php esc_html_e( 'Post Types', 'wpmastertoolkit' ); ?></strong></div>
                <select class="js-multiselect" name="<?php echo esc_attr( $this->content_type_settings . '[object_type]' ); ?>[]" multiple>
                    <?php
                    $wpmtk_cpts = get_post_types( array( 'public' => true ), 'objects' );
                    foreach ( $wpmtk_cpts as $wpmtk_cpt ) {
                        ?>
                        <option value="<?php echo esc_attr( $wpmtk_cpt->name ); ?>" <?php selected( in_array( $wpmtk_cpt->name, $wpmtk_settings['object_type'] ?? array() ) ); ?>>
                            <?php echo esc_html( $wpmtk_cpt->label ); ?>
                        </option>
                        <?php
                    }
                    ?>
                </select>
            </div>
            <div class="description">
                <?php esc_html_e( 'One or many post types that can be classified with this taxonomy.', 'wpmastertoolkit' ); ?>
            </div>
        </div>

        <div class="wp-mastertoolkit__section__body__item">
            <div class="wp-mastertoolkit__section__body__item__title activable">
                <label class="wp-mastertoolkit__toggle">
                    <input type="hidden" name="<?php echo esc_attr( $this->content_type_settings . '[sort]' ); ?>" value="0">
                    <input type="checkbox" name="<?php echo esc_attr( $this->content_type_settings . '[sort]' ); ?>" value="1" <?php checked( $wpmtk_settings['sort'] ?? 0, 1 ); ?>>
                    <span class="wp-mastertoolkit__toggle__slider round"></span>
                </label>
                <div class="description"><strong><?php esc_html_e( 'Sort Terms', 'wpmastertoolkit' ); ?></strong></div>
            </div>
            <div class="description">
                <?php esc_html_e( "Whether terms in this taxonomy should be sorted in the order they are provided to `wp_set_object_terms()`.", 'wpmastertoolkit' ); ?>
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

        <?php foreach( $this->get_taxonomy_labels('required') as $wpmtk_key => $wpmtk_data ) : ?>
        <div class="wp-mastertoolkit__section__body__item">
            <div class="wp-mastertoolkit__input-text">
                <div class="description">
                    <strong><?php echo esc_html( $wpmtk_data['label'] ?? '' ); ?> <?php echo wp_kses_post( !empty( $wpmtk_data['required'] ) ? '<span class="required">*</span>' : '' ); ?></strong>
                </div>
                <input type="text" name="<?php echo esc_attr( $this->content_type_settings . '[' . $wpmtk_key . ']' ); ?>" value="<?php echo esc_attr( $wpmtk_settings[$wpmtk_key] ?? '' ); ?>" placeholder="<?php echo esc_attr( $wpmtk_data['placeholder'] ?? '' ); ?>" <?php echo esc_attr( !empty( $wpmtk_data['required'] ) ? 'required' : '' ); ?>>
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
        
        <?php foreach( $this->get_taxonomy_labels('optional') as $wpmtk_key => $wpmtk_data ) : ?>
        <div class="wp-mastertoolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[manage_optional_labels]' ); ?>=1">
            <div class="wp-mastertoolkit__input-text">
                <div class="description">
                    <strong><?php echo esc_html( $wpmtk_data['label'] ?? '' ); ?> <?php echo wp_kses_post( !empty( $wpmtk_data['required'] ) ? '<span class="required">*</span>' : '' ); ?></strong>
                </div>
                <input type="text" name="<?php echo esc_attr( $this->content_type_settings . '[' . $wpmtk_key . ']' ); ?>" value="<?php echo esc_attr( $wpmtk_settings[$wpmtk_key] ?? '' ); ?>" placeholder="<?php echo esc_attr( $wpmtk_data['placeholder'] ?? '' ); ?>" <?php echo esc_attr( !empty( $wpmtk_data['required'] ) ? 'required' : '' ); ?>>
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
                        <input type="hidden" name="<?php echo esc_attr( $this->content_type_settings . '[show_tagcloud]' ); ?>" value="0">
                        <input type="checkbox" name="<?php echo esc_attr( $this->content_type_settings . '[show_tagcloud]' ); ?>" value="1" <?php checked( $wpmtk_settings['show_tagcloud'] ?? 1, 1 ); ?>>
                        <span class="wp-mastertoolkit__toggle__slider round"></span>
                    </label>
                </div>
                <div>
                    <?php esc_html_e( "Tag Cloud", 'wpmastertoolkit' ); ?>
                </div>
            </div>
            <div class="description">
                <?php esc_html_e( 'List the taxonomy in the Tag Cloud Widget controls.', 'wpmastertoolkit' ); ?>
            </div>
        </div>

        <div class="wp-mastertoolkit__section__body__item">
            <div class="wp-mastertoolkit__section__body__item__title activable">
                
                <div>
                    <label class="wp-mastertoolkit__toggle">
                        <input type="hidden" name="<?php echo esc_attr( $this->content_type_settings . '[show_in_quick_edit]' ); ?>" value="0">
                        <input type="checkbox" name="<?php echo esc_attr( $this->content_type_settings . '[show_in_quick_edit]' ); ?>" value="1" <?php checked( $wpmtk_settings['show_in_quick_edit'] ?? 1, 1 ); ?>>
                        <span class="wp-mastertoolkit__toggle__slider round"></span>
                    </label>
                </div>
                <div>
                    <?php esc_html_e( "Quick Edit", 'wpmastertoolkit' ); ?>
                </div>
            </div>
            <div class="description">
                <?php esc_html_e( 'Show the taxonomy in the quick/bulk edit panel.', 'wpmastertoolkit' ); ?>
            </div>
        </div>

        <div class="wp-mastertoolkit__section__body__item">
            <div class="wp-mastertoolkit__section__body__item__title activable">
                
                <div>
                    <label class="wp-mastertoolkit__toggle">
                        <input type="hidden" name="<?php echo esc_attr( $this->content_type_settings . '[show_admin_column]' ); ?>" value="0">
                        <input type="checkbox" name="<?php echo esc_attr( $this->content_type_settings . '[show_admin_column]' ); ?>" value="1" <?php checked( $wpmtk_settings['show_admin_column'] ?? 0, 1 ); ?>>
                        <span class="wp-mastertoolkit__toggle__slider round"></span>
                    </label>
                </div>
                <div>
                    <?php esc_html_e( "Show Admin Column", 'wpmastertoolkit' ); ?>
                </div>
            </div>
            <div class="description">
                <?php esc_html_e( 'Display a column for the taxonomy on post type listing screens.', 'wpmastertoolkit' ); ?>
            </div>
        </div>
    </div>
</div>

<div class="wp-mastertoolkit__section">
    <div class="wp-mastertoolkit__section__body">

        <div class="wp-mastertoolkit__section__body__item">
            <h2>
                <?php esc_html_e( 'Default Term', 'wpmastertoolkit' ); ?>
            </h2>
            <hr>
        </div>

        <div class="wp-mastertoolkit__section__body__item">
            <div class="wp-mastertoolkit__section__body__item__title activable">
                <label class="wp-mastertoolkit__toggle">
                    <input type="hidden" name="<?php echo esc_attr( $this->content_type_settings . '[default_term_enabled]' ); ?>" value="0">
                    <input type="checkbox" name="<?php echo esc_attr( $this->content_type_settings . '[default_term_enabled]' ); ?>" value="1" <?php checked( $wpmtk_settings['default_term_enabled'] ?? 0, 1 ); ?>>
                    <span class="wp-mastertoolkit__toggle__slider round"></span>
                </label>
                <div class="description"><strong><?php esc_html_e( 'Default Term', 'wpmastertoolkit' ); ?></strong></div>
            </div>
            <div class="description">
                <?php esc_html_e( 'Create a term for the taxonomy that cannot be deleted. It will not be selected for posts by default.', 'wpmastertoolkit' ); ?>
            </div>
        </div>

        <div class="wp-mastertoolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[default_term_enabled]' ); ?>=1">
            <div class="wp-mastertoolkit__input-text">
                <div class="description">
                    <strong><?php esc_html_e( 'Default Term Name', 'wpmastertoolkit' ); ?></strong>
                </div>
                <input type="text" name="<?php echo esc_attr( $this->content_type_settings . '[default_term_name]' ); ?>" value="<?php echo esc_attr( $wpmtk_settings['default_term_name'] ?? '' ); ?>" placeholder="<?php esc_html_e( 'Unassigned', 'wpmastertoolkit' ); ?>">
            </div>
            <div class="description">
                <?php esc_html_e( 'Enter the default term name.', 'wpmastertoolkit' ); ?>
            </div>
        </div>

        <div class="wp-mastertoolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[default_term_enabled]' ); ?>=1">
            <div class="wp-mastertoolkit__input-text">
                <div class="description">
                    <strong><?php esc_html_e( 'Default Term Slug', 'wpmastertoolkit' ); ?></strong>
                </div>
                <input type="text" name="<?php echo esc_attr( $this->content_type_settings . '[default_term_slug]' ); ?>" value="<?php echo esc_attr( $wpmtk_settings['default_term_slug'] ?? '' ); ?>" placeholder="<?php esc_html_e( 'unassigned', 'wpmastertoolkit' ); ?>">
            </div>
            <div class="description">
                <?php esc_html_e( 'Enter the default term slug.', 'wpmastertoolkit' ); ?>
            </div>
        </div>

        <div class="wp-mastertoolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[default_term_enabled]' ); ?>=1">
            <div class="wp-mastertoolkit__input-text">
                <div class="description">
                    <strong><?php esc_html_e( 'Default Term Description', 'wpmastertoolkit' ); ?></strong>
                </div>
                <input type="text" name="<?php echo esc_attr( $this->content_type_settings . '[default_term_description]' ); ?>" value="<?php echo esc_attr( $wpmtk_settings['default_term_description'] ?? '' ); ?>" placeholder="<?php esc_html_e( 'Unassigned', 'wpmastertoolkit' ); ?>">
            </div>
            <div class="description">
                <?php esc_html_e( 'Enter the default term description.', 'wpmastertoolkit' ); ?>
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
                    <option value="taxonomy_key" <?php selected( $wpmtk_settings['permalink_rewrite'] ?? 'taxonomy_key', 'taxonomy_key' ); ?>><?php esc_html_e( 'Taxonomy Key (default)', 'wpmastertoolkit' ); ?></option>
                    <option value="custom_permalink" <?php selected( $wpmtk_settings['permalink_rewrite'] ?? 'taxonomy_key', 'custom_permalink' ); ?>><?php esc_html_e( 'Custom Permalink', 'wpmastertoolkit' ); ?></option>
                    <option value="no_permalink" <?php selected( $wpmtk_settings['permalink_rewrite'] ?? 'taxonomy_key', 'no_permalink' ); ?>><?php esc_html_e( 'No permalink (Prevent URL rewriting)', 'wpmastertoolkit' ); ?></option>
                </select>
            </div>
            <div class="description" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[permalink_rewrite]' ); ?>=taxonomy_key">
                <?php
					echo esc_html( sprintf(
						/* translators: %s: Home URL */
						__( 'Rewrite the URL using the taxonomy key as the slug. Your permalink structure will be %s/{slug}.', 'wpmastertoolkit' ),
						home_url()
					) );
				?>
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

        <div class="wp-mastertoolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[permalink_rewrite]' ); ?>!=no_permalink">
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
        
        <div class="wp-mastertoolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[permalink_rewrite]' ); ?>!=no_permalink">
            <div class="wp-mastertoolkit__section__body__item__title activable">
                
                <div>
                    <label class="wp-mastertoolkit__toggle">
                        <input type="hidden" name="<?php echo esc_attr( $this->content_type_settings . '[rewrite_hierarchical]' ); ?>" value="0">
                        <input type="checkbox" name="<?php echo esc_attr( $this->content_type_settings . '[rewrite_hierarchical]' ); ?>" value="1" <?php checked( $wpmtk_settings['rewrite_hierarchical'] ?? 0, 1 ); ?>>
                        <span class="wp-mastertoolkit__toggle__slider round"></span>
                    </label>
                </div>
                <div>
                    <?php esc_html_e( "Rewrite Hierarchical", 'wpmastertoolkit' ); ?>
                </div>
            </div>
            <div class="description">
                <?php esc_html_e( 'Parent-child terms in URLs for hierarchical taxonomies.', 'wpmastertoolkit' ); ?>
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
                    <option value="taxonomy_key" <?php selected( $wpmtk_settings['query_var'] ?? '', 'taxonomy_key' ); ?>><?php esc_html_e( 'Taxonomy Key (default)', 'wpmastertoolkit' ); ?></option>
                    <option value="custom_query_var" <?php selected( $wpmtk_settings['query_var'] ?? '', 'custom_query_var' ); ?>><?php esc_html_e( 'Custom Query Variable', 'wpmastertoolkit' ); ?></option>
                    <option value="none" <?php selected( $wpmtk_settings['query_var'] ?? '', 'none' ); ?>><?php esc_html_e( 'No Query Variable', 'wpmastertoolkit' ); ?></option>
                </select>
            </div>
            <div class="description" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[query_var]' ); ?>=taxonomy_key">
                <?php esc_html_e( 'Terms can be accessed using the non-pretty permalink, e.g., {query_var}={term_slug}.', 'wpmastertoolkit' ); ?>
            </div>
        </div>

        <div class="wp-mastertoolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[publicly_queryable]' ); ?>=1&<?php echo esc_attr( $this->content_type_settings . '[query_var]' ); ?>=custom_query_var">
            <div class="wp-mastertoolkit__input-text">
                <div class="description">
                    <strong><?php esc_html_e( 'Query Variable Name', 'wpmastertoolkit' ); ?></strong>
                </div>
                <input type="text" name="<?php echo esc_attr( $this->content_type_settings . '[query_var_name]' ); ?>" value="<?php echo esc_attr( $wpmtk_settings['query_var_name'] ?? '' ); ?>" placeholder="<?php esc_html_e( 'Enter a slug', 'wpmastertoolkit' ); ?>">
                
                <div class="description">
                    <?php esc_html_e( 'Customize the query variable name.', 'wpmastertoolkit' ); ?>
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
            <div class="wp-mastertoolkit__select">
                <div class="description">
                    <strong><?php esc_html_e( 'Manage Terms Capability', 'wpmastertoolkit' ); ?></strong>
                </div>
                <select name="<?php echo esc_attr( $this->content_type_settings . '[manage_terms]' ); ?>">
                   <?php
                    $wpmtk_all_capabilities = $this->get_all_wp_capabilities();
                    foreach( $wpmtk_all_capabilities as $wpmtk_capability => $wpmtk_label ) {
                        ?>
                        <option value="<?php echo esc_attr( $wpmtk_capability ); ?>" <?php selected( $wpmtk_settings['manage_terms'] ?? 'manage_categories', $wpmtk_capability ); ?>>
                            <?php 
                            echo esc_html( $wpmtk_label ); 
                            if( $wpmtk_capability === 'manage_categories' ) {
                                echo ' (' . esc_html__( 'default', 'wpmastertoolkit' ) . ')';
                            }
                            ?>
                        </option>
                        <?php
                    }
                    ?>
                </select>
            </div>

            <div class="description">
                <?php esc_html_e( 'The capability name for managing terms of this taxonomy.', 'wpmastertoolkit' ); ?>
            </div>
        </div>
        
        <div class="wp-mastertoolkit__section__body__item">
            <div class="wp-mastertoolkit__select">
                <div class="description">
                    <strong><?php esc_html_e( 'Edit Terms Capability', 'wpmastertoolkit' ); ?></strong>
                </div>
                <select name="<?php echo esc_attr( $this->content_type_settings . '[edit_terms]' ); ?>">
                   <?php
                    $wpmtk_all_capabilities = $this->get_all_wp_capabilities();
                    foreach( $wpmtk_all_capabilities as $wpmtk_capability => $wpmtk_label ) {
                        ?>
                        <option value="<?php echo esc_attr( $wpmtk_capability ); ?>" <?php selected( $wpmtk_settings['edit_terms'] ?? 'manage_categories', $wpmtk_capability ); ?>>
                            <?php 
                            echo esc_html( $wpmtk_label ); 
                            if( $wpmtk_capability === 'manage_categories' ) {
                                echo ' (' . esc_html__( 'default', 'wpmastertoolkit' ) . ')';
                            }
                            ?>
                        </option>
                        <?php
                    }
                    ?>
                </select>
            </div>

            <div class="description">
                <?php esc_html_e( 'The capability name for editing terms of this taxonomy.', 'wpmastertoolkit' ); ?>
            </div>
        </div>
        
        <div class="wp-mastertoolkit__section__body__item">
            <div class="wp-mastertoolkit__select">
                <div class="description">
                    <strong><?php esc_html_e( 'Delete Terms Capability', 'wpmastertoolkit' ); ?></strong>
                </div>
                <select name="<?php echo esc_attr( $this->content_type_settings . '[delete_terms]' ); ?>">
                   <?php
                    $wpmtk_all_capabilities = $this->get_all_wp_capabilities();
                    foreach( $wpmtk_all_capabilities as $wpmtk_capability => $wpmtk_label ) {
                        ?>
                        <option value="<?php echo esc_attr( $wpmtk_capability ); ?>" <?php selected( $wpmtk_settings['delete_terms'] ?? 'manage_categories', $wpmtk_capability ); ?>>
                            <?php 
                            echo esc_html( $wpmtk_label ); 
                            if( $wpmtk_capability === 'manage_categories' ) {
                                echo ' (' . esc_html__( 'default', 'wpmastertoolkit' ) . ')';
                            }
                            ?>
                        </option>
                        <?php
                    }
                    ?>
                </select>
            </div>

            <div class="description">
                <?php esc_html_e( 'The capability name for deleting terms of this taxonomy.', 'wpmastertoolkit' ); ?>
            </div>
        </div>
        
        <div class="wp-mastertoolkit__section__body__item">
            <div class="wp-mastertoolkit__select">
                <div class="description">
                    <strong><?php esc_html_e( 'Assign Terms Capability', 'wpmastertoolkit' ); ?></strong>
                </div>
                <select name="<?php echo esc_attr( $this->content_type_settings . '[assign_terms]' ); ?>">
                   <?php
                    $wpmtk_all_capabilities = $this->get_all_wp_capabilities();
                    foreach( $wpmtk_all_capabilities as $wpmtk_capability => $wpmtk_label ) {
                        ?>
                        <option value="<?php echo esc_attr( $wpmtk_capability ); ?>" <?php selected( $wpmtk_settings['assign_terms'] ?? 'edit_posts', $wpmtk_capability ); ?>>
                            <?php 
                            echo esc_html( $wpmtk_label ); 
                            if( $wpmtk_capability === 'edit_posts' ) {
                                echo ' (' . esc_html__( 'default', 'wpmastertoolkit' ) . ')';
                            }
                            ?>
                        </option>
                        <?php
                    }
                    ?>
                </select>
            </div>

            <div class="description">
                <?php esc_html_e( 'The capability name for assigning terms of this taxonomy.', 'wpmastertoolkit' ); ?>
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
                <?php esc_html_e( 'If checked, the content type will be available in the REST API.', 'wpmastertoolkit' ); ?>
            </div>
        </div>

        <div class="wp-mastertoolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[show_in_rest]' ); ?>=1">
            <div class="wp-mastertoolkit__input-text">
                <div class="description">
                    <strong><?php esc_html_e( 'Base URL', 'wpmastertoolkit' ); ?></strong>
                </div>
                <input type="text" name="<?php echo esc_attr( $this->content_type_settings . '[rest_base]' ); ?>" value="<?php echo esc_attr( $wpmtk_settings['rest_base'] ?? '' ); ?>" placeholder="<?php esc_html_e( 'Enter a slug', 'wpmastertoolkit' ); ?>">
                
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
                <input type="text" name="<?php echo esc_attr( $this->content_type_settings . '[rest_controller_class]' ); ?>" value="<?php echo esc_attr( $wpmtk_settings['rest_controller_class'] ?? 'WP_REST_Terms_Controller' ); ?>" placeholder="<?php esc_html_e( 'WP_REST_Terms_Controller', 'wpmastertoolkit' ); ?>">
                
                <div class="description">
                    <?php esc_html_e( 'Optional custom controller to use instead of `WP_REST_Terms_Controller`.', 'wpmastertoolkit' ); ?>
                </div>
            </div>
        </div>
    </div>
</div>