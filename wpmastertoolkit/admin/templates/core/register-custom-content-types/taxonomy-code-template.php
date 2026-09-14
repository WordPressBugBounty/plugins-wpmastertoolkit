<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/** @var array $settings */

$wpmtk_labels = $this->get_taxonomy_labels();
unset( $wpmtk_labels['taxonomy'] );
unset( $wpmtk_labels['description'] );

$wpmtk_text_domain = !empty($settings['text_domain']) ? $settings['text_domain'] : 'wpmastertoolkit';
// return; // TODO: Vérifier à partir de là (en cours)
$wpmtk_permalink_rewrite = $settings['permalink_rewrite'] ?? 'taxonomy_key';
$wpmtk_rewrite_enabled   = $wpmtk_permalink_rewrite === 'no_permalink' ? false : true;
$wpmtk_rewrite           = $wpmtk_rewrite_enabled;
$wpmtk_rewrite_args      = array();

if ( ! empty( $settings['slug'] ) && $settings['slug'] !== $settings['taxonomy'] && 'custom_permalink' === $settings['permalink_rewrite'] ) {
    $wpmtk_rewrite_args['slug'] = (string) $settings['slug'];
}

if ( isset( $settings['with_front'] ) && empty( $settings['with_front'] ) && $wpmtk_rewrite_enabled ) {
    $wpmtk_rewrite_args['with_front'] = false;
}

if ( !empty($settings['rewrite_hierarchical']) && $wpmtk_rewrite_enabled ) {
    $wpmtk_rewrite_args['rewrite_hierarchical'] = true;
}

if( !empty($wpmtk_rewrite_args) ) {
        $wpmtk_rewrite = $wpmtk_rewrite_args;
}

$wpmtk_capabilities = array();

if ( ! empty( $settings['manage_terms'] ) && 'manage_categories' !== $settings['manage_terms'] ) {
    $wpmtk_capabilities['manage_terms'] = (string) $settings['manage_terms'];
}

if ( ! empty( $settings['edit_terms'] ) && 'manage_categories' !== $settings['edit_terms'] ) {
    $wpmtk_capabilities['edit_terms'] = (string) $settings['edit_terms'];
}

if ( ! empty( $settings['delete_terms'] ) && 'manage_categories' !== $settings['delete_terms'] ) {
    $wpmtk_capabilities['delete_terms'] = (string) $settings['delete_terms'];
}

if ( ! empty( $settings['assign_terms'] ) && 'edit_posts' !== $settings['assign_terms'] ) {
    $wpmtk_capabilities['assign_terms'] = (string) $settings['assign_terms'];
}

$wpmtk_default_term = array();
if ( !empty( $settings['default_term_enabled'] ) ) {
    
    if ( !empty( $settings['default_term_name'] ) ) {
        $wpmtk_default_term['name'] = (string) $settings['default_term_name'];
    }
    
    if ( !empty( $settings['default_term_slug'] ) ) {
        $wpmtk_default_term['slug'] = (string) $settings['default_term_slug'];
    }
    
    if ( !empty( $settings['default_term_description'] ) ) {
        $wpmtk_default_term['description'] = (string) $settings['default_term_description'];
    }
}

$wpmtk_query_var = 'custom_query_var' === $settings['query_var'] && !empty( $settings['query_var_name'] ) && $settings['query_var_name'] !== $settings['taxonomy'] ? $settings['query_var_name'] : false;

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
        'publicly_queryable'    => <?php echo esc_html( $this->text_to_boolean( $settings['publicly_queryable'] ) ); ?>,
        'show_ui'               => <?php echo esc_html( $this->text_to_boolean( $settings['show_ui'] ) ); ?>,
<?php if( $settings['show_ui'] !== $settings['public'] ) : ?>
        'show_in_menu'          => <?php echo esc_html( $this->text_to_boolean( $settings['show_in_menu'] ) ); ?>,
<?php endif; ?>
<?php if( $settings['show_in_nav_menus'] !== $settings['public'] ) : ?>
        'show_in_nav_menus'     => <?php echo esc_html( $this->text_to_boolean( $settings['show_in_nav_menus'] ) ); ?>,
<?php endif; ?>
        'show_in_rest'          => <?php echo esc_html( $this->text_to_boolean( $settings['show_in_rest'] ) ); ?>,
<?php if( !empty( $settings['show_in_rest'] ) && !empty( $settings['rest_base'] ) && $settings['rest_base'] !== $settings['taxonomy'] ) : ?>
        'rest_base'             => <?php echo var_export( $settings['rest_base'], true ); ?>,
<?php endif; ?>
<?php if( !empty( $settings['show_in_rest'] ) && !empty( $settings['rest_namespace'] ) && $settings['rest_namespace'] !== 'wp/v2' ) : ?>
        'rest_namespace'        => <?php echo var_export( $settings['rest_namespace'], true ); ?>,
<?php endif; ?>
<?php if( !empty( $settings['hierarchical'] ) ) : ?>
        'hierarchical'          => <?php echo esc_html( $this->text_to_boolean( $settings['hierarchical'] ) ); ?>,
<?php endif; ?>
<?php if( !empty( $settings['show_in_rest'] ) && !empty( $settings['rest_controller_class'] ) && $settings['rest_controller_class'] !== 'WP_REST_Terms_Controller' ) : ?>
        'rest_controller_class' => <?php echo var_export( $settings['rest_controller_class'], true ); ?>,
<?php endif; ?>
<?php if( $settings['show_ui'] !== $settings['show_tagcloud'] ) : ?>
        'show_tagcloud'         => <?php echo esc_html( $this->text_to_boolean( $settings['show_tagcloud'] ) ); ?>,
<?php endif; ?>
<?php if( $settings['show_ui'] !== $settings['show_in_quick_edit'] ) : ?>
        'show_in_quick_edit'    => <?php echo esc_html( $this->text_to_boolean( $settings['show_in_quick_edit'] ) ); ?>,
<?php endif; ?>
<?php if( !empty( $settings['show_admin_column'] ) ) : ?>
        'show_admin_column'     => true,
<?php endif; ?>
<?php if( !empty( $wpmtk_capabilities ) ) : ?>
        'capabilities'          => <?php echo var_export( $wpmtk_capabilities, true ); ?>,
<?php endif; ?>
        'rewrite'               => <?php echo var_export( $wpmtk_rewrite, true ); ?>,
<?php if( $settings['query_var'] !== 'taxonomy_key' && !empty( $settings['publicly_queryable'] ) ) : ?>
        'query_var'             => <?php echo var_export( $wpmtk_query_var, true ); ?>,
<?php endif; ?>
<?php if( !empty( $wpmtk_default_term ) ) : ?>
        'default_term'          => <?php echo var_export( $wpmtk_default_term, true ); ?>,
<?php endif; ?>
<?php if( !empty($settings['sort']) ) : ?>
        'sort'                  => true,
<?php endif; ?>
    );
    
    /**
    * Filter the arguments for the custom taxonomy.
    *
    * @param array    $args The arguments for the custom taxonomy.
    */
        $args = apply_filters( <?php echo var_export( 'wpmastertoolkit/register_custom_content_types/taxonomy/' . $settings['taxonomy'], true ); ?>, $args );

        register_taxonomy( <?php echo var_export( $settings['taxonomy'], true ); ?>, <?php echo var_export( $settings['object_type'], true ); ?>, $args );
};

if ( did_action( 'init' ) ) {
        $wpmtk_register_content_type();
} else {
        add_action( 'init', $wpmtk_register_content_type );
}