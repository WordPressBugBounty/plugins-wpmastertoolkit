<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

?>
<hr><br>
<header class="wp-mastertoolkit__header">
    
    <div class="wp-mastertoolkit__header__center">
        <div class="wp-mastertoolkit__header__left__title">
            <?php esc_html_e( 'Choice the content type to create', 'wpmastertoolkit' ); ?>
        </div>
    </div>

    <div class="wp-mastertoolkit__header__right__save">
        <input type="hidden" name="post_status" value="draft">
        <?php submit_button( esc_html__('Continue', 'wpmastertoolkit') ); ?>
    </div>
    
</header>

<div class="wp-mastertoolkit__sections-grid cols-3">
    <label class="wp-mastertoolkit__section select-content-type">
        <input type="radio" name="content_type" id="" value="cpt" checked>
        <div>
            <h2>
                <?php esc_html_e( 'Custom Post Type', 'wpmastertoolkit' ); ?>
            </h2>
            <p>
                <?php esc_html_e( 'Create a custom post type based on the WordPress post type. Example: Portfolio, Testimonials, etc.', 'wpmastertoolkit' ); ?>
            </p>
        </div>
    </label>
    <label class="wp-mastertoolkit__section select-content-type" style="background-color: #f5f5f5; color: #999;">
        <input type="radio" name="content_type" id="" value="taxonomy" disabled>
        <div>
            <h2 style="color: #999;">
                <?php esc_html_e( 'Custom Taxonomy', 'wpmastertoolkit' ); ?> (<?php esc_html_e( 'Coming soon', 'wpmastertoolkit' ); ?>)
            </h2>
            <p>
                <?php esc_html_e( 'Create a custom taxonomy based on the WordPress taxonomy. Example: Category, Tags, etc.', 'wpmastertoolkit' ); ?>
            </p>
        </div>
    </label>
    <label class="wp-mastertoolkit__section select-content-type" style="background-color: #f5f5f5; color: #999;">
        <input type="radio" name="content_type" id="" value="option_page" disabled>
        <div>
            <h2 style="color: #999;">
                <?php esc_html_e( 'Option Page', 'wpmastertoolkit' ); ?> (<?php esc_html_e( 'Coming soon', 'wpmastertoolkit' ); ?>)
            </h2>
            <p>
                <?php esc_html_e( 'Create a custom option page based on the WordPress option page. Example: Settings, etc.', 'wpmastertoolkit' ); ?>
            </p>
        </div>
    </label>
</div>
