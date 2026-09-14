<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/** @var array $settings */

$wpmtk_labels = $this->get_cpt_labels();
unset( $wpmtk_labels['post_type'] );
unset( $wpmtk_labels['description'] );

$wpmtk_text_domain = !empty($settings['text_domain']) ? $settings['text_domain'] : 'wpmastertoolkit';

$wpmtk_permalink_rewrite = $settings['permalink_rewrite'] ?? 'post_type_key';
$wpmtk_rewrite_enabled   = $wpmtk_permalink_rewrite === 'no_permalink' ? false : true;
$wpmtk_rewrite           = $wpmtk_rewrite_enabled;
$wpmtk_rewrite_args      = array();

if ( ! empty( $settings['slug'] ) && $settings['slug'] !== $settings['post_type'] && 'custom_permalink' === $settings['permalink_rewrite'] ) {
    $wpmtk_rewrite_args['slug'] = (string) $settings['slug'];
}

if ( isset( $settings['with_front'] ) && empty( $settings['with_front'] ) && $wpmtk_rewrite_enabled ) {
    $wpmtk_rewrite_args['with_front'] = false;
}

if ( $settings['feeds'] !== $settings['has_archive'] && $wpmtk_rewrite_enabled ) {
    $wpmtk_rewrite_args['feeds'] = (bool) $settings['feeds'];
}

if ( empty($settings['pages']) && $wpmtk_rewrite_enabled ) {
    $wpmtk_rewrite_args['pages'] = false;
}

if( !empty($wpmtk_rewrite_args) ) {
        $wpmtk_rewrite = $wpmtk_rewrite_args;
}

$wpmtk_query_var = 'custom_query_var' === $settings['query_var'] && !empty( $settings['query_var_name'] ) && $settings['query_var_name'] !== $settings['post_type'] ? $settings['query_var_name'] : false;

if ( !empty( $settings['rename_capabilities'] ) ) {
    $wpmtk_singular_capability_name = (string) $settings['singular_capability_name'];
    $wpmtk_plural_capability_name   = (string) $settings['plural_capability_name'];
    $wpmtk_capability_type          = 'post';

    if ( ! empty( $wpmtk_singular_capability_name ) && ! empty( $wpmtk_plural_capability_name ) ) {
        $wpmtk_capability_type = array( $wpmtk_singular_capability_name, $wpmtk_plural_capability_name );
    } elseif ( ! empty( $wpmtk_singular_capability_name ) ) {
        $wpmtk_capability_type = $wpmtk_singular_capability_name;
    }
}
//phpcs:disable
?>
$wpmtk_register_content_type = function(){
    $labels = array(
<?php foreach( $wpmtk_labels  as $name => $label_data ) : ?>
<?php if( !empty($label_data['required']) || !empty($settings['manage_optional_labels']) ) : ?>
<?php if( !empty($settings[$name]) ) : ?>
        <?php echo var_export( $name, true ); ?><?php echo str_repeat( ' ', max( 1, 20 - strlen( $name ) ) ); ?>=> __( <?php echo var_export( $settings[$name], true ); ?>, <?php echo var_export( $wpmtk_text_domain, true ); ?> ),
<?php endif; ?>
<?php endif; ?>
<?php endforeach; ?>
    );

    $args = array(
        'labels'                => $labels,
<?php if( !empty($settings['description']) && !empty($settings['manage_optional_labels']) ) : ?>
        'description'           => __( <?php echo var_export( $settings['description'], true ); ?>, <?php echo var_export( $wpmtk_text_domain, true ); ?> ),
<?php endif; ?>
        'public'                => <?php echo esc_html( $this->text_to_boolean( $settings['public'] ) ); ?>,
        'exclude_from_search'   => <?php echo esc_html( $this->text_to_boolean( $settings['exclude_from_search'] ) ); ?>,
        'publicly_queryable'    => <?php echo esc_html( $this->text_to_boolean( $settings['publicly_queryable'] ) ); ?>,
        'show_ui'               => <?php echo esc_html( $this->text_to_boolean( $settings['show_ui'] ) ); ?>,
<?php if( $settings['show_in_menu'] !== $settings['show_ui'] || !empty($settings['admin_menu_parent']) ) : ?>
        'show_in_menu'          => <?php echo !empty($settings['admin_menu_parent']) ? var_export( $settings['admin_menu_parent'], true ) : $this->text_to_boolean( $settings['show_in_menu'] ); ?>,
<?php endif; ?>
<?php if( $settings['show_in_nav_menus'] !== $settings['public'] ) : ?>
        'show_in_nav_menus'     => <?php echo esc_html( $this->text_to_boolean( $settings['show_in_nav_menus'] ) ); ?>,
<?php endif; ?>
<?php if( $settings['show_in_admin_bar'] !== $settings['show_in_menu'] ) : ?>
        'show_in_admin_bar'     => <?php echo esc_html( $this->text_to_boolean( $settings['show_in_admin_bar'] ) ); ?>,
<?php endif; ?>
        'show_in_rest'          => <?php echo esc_html( $this->text_to_boolean( $settings['show_in_rest'] ) ); ?>,
<?php if( !empty( $settings['show_in_rest'] ) && !empty( $settings['rest_base'] ) && $settings['rest_base'] !== $settings['post_type'] ) : ?>
        'rest_base'             => <?php echo var_export( $settings['rest_base'], true ); ?>,
<?php endif; ?>
<?php if( !empty( $settings['show_in_rest'] ) && !empty( $settings['rest_namespace'] ) && $settings['rest_namespace'] !== 'wp/v2' ) : ?>
        'rest_namespace'        => <?php echo var_export( $settings['rest_namespace'], true ); ?>,
<?php endif; ?>
<?php if( !empty( $settings['menu_position'] ) ) : ?>
        'menu_position'         => <?php echo esc_html( (int) $settings['menu_position'] ); ?>,
<?php endif; ?>
        'menu_icon'             => <?php echo var_export( !empty($settings['use_dashicon']) && !empty($settings['menu_icon']) ? $settings['menu_icon'] : $settings['custom_menu_icon'], true ); ?>,
<?php if( !empty( $settings['rename_capabilities'] ) && $wpmtk_capability_type !== 'post' && $wpmtk_capability_type !== array( 'post', 'posts' ) ) : ?>
        'capability_type'       => <?php echo var_export( $wpmtk_capability_type, true ); ?>,
        'map_meta_cap'          => true,
<?php endif; ?>
<?php if( !empty( $settings['hierarchical'] ) ) : ?>
        'hierarchical'          => <?php echo esc_html( $this->text_to_boolean( $settings['hierarchical'] ) ); ?>,
<?php endif; ?>
        'supports'              => <?php echo var_export( $settings['supports'], true ); ?>,
<?php if( !empty( $settings['taxonomies'] ) ) : ?>
        'taxonomies'            => <?php echo var_export( $settings['taxonomies'], true ); ?>,
<?php endif; ?>
<?php if( !empty( $settings['has_archive'] ) ) : ?>
        'has_archive'           => <?php echo !empty($settings['archive_slug']) && $settings['archive_slug'] !== $settings['post_type'] ? var_export( $settings['archive_slug'], true ) : 'true'; ?>,
<?php endif; ?>
<?php if( !empty( $settings['show_in_rest'] ) && !empty( $settings['rest_controller_class'] ) && $settings['rest_controller_class'] !== 'WP_REST_Posts_Controller' ) : ?>
        'rest_controller_class' => <?php echo var_export( $settings['rest_controller_class'], true ); ?>,
<?php endif; ?>
        'rewrite'               => <?php echo var_export( $wpmtk_rewrite, true ); ?>,
<?php if( $settings['query_var'] !== 'post_type_key' && !empty( $settings['publicly_queryable'] ) ) : ?>
        'query_var'             => <?php echo var_export( $wpmtk_query_var, true ); ?>,
<?php endif; ?>
<?php if( empty($settings['can_export']) ) : ?>
        'can_export'            => false,
<?php endif; ?>
        'delete_with_user'      => <?php echo esc_html( $this->text_to_boolean( $settings['delete_with_user'] ) ); ?>,
    );
    
    /**
    * Filter the arguments for the custom post type.
    *
    * @param array    $args The arguments for the custom post type.
    */
        $args = apply_filters( <?php echo var_export( 'wpmastertoolkit/register_custom_content_types/cpt/' . $settings['post_type'], true ); ?>, $args );

        register_post_type( <?php echo var_export( $settings['post_type'], true ); ?>, $args );
};

if ( did_action( 'init' ) ) {
        $wpmtk_register_content_type();
} else {
        add_action( 'init', $wpmtk_register_content_type );
}