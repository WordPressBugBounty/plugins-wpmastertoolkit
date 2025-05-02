<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

$labels = $this->get_taxonomy_labels();
unset( $labels['taxonomy'] );
unset( $labels['description'] );

$text_domain = !empty($settings['text_domain']) ? $settings['text_domain'] : 'wpmastertoolkit';
// return; // TODO: Vérifier à partir de là (en cours)
$permalink_rewrite = $settings['permalink_rewrite'] ?? 'taxonomy_key';
$rewrite_enabled   = $permalink_rewrite === 'no_permalink' ? false : true;
$rewrite           = $this->text_to_boolean( $rewrite_enabled );
$rewrite_args      = array();

if ( ! empty( $settings['slug'] ) && $settings['slug'] !== $settings['taxonomy'] && 'custom_permalink' === $settings['permalink_rewrite'] ) {
    $rewrite_args['slug'] = (string) $settings['slug'];
}

if ( isset( $settings['with_front'] ) && empty( $settings['with_front'] ) && $rewrite_enabled ) {
    $rewrite_args['with_front'] = false;
}

if ( !empty($settings['rewrite_hierarchical']) && $rewrite_enabled ) {
    $rewrite_args['rewrite_hierarchical'] = true;
}

if( !empty($rewrite_args) ) {
    $rewrite = 'array( ' . implode( ', ', array_map( function ( $key, $value ) {
        return "'" . $key . "' => " . ( is_bool( $value ) ? ( $value ? 'true' : 'false' ) : "'" . $value . "'" );
    }, array_keys( $rewrite_args ), $rewrite_args ) ) . ' )';
}

$capabilities = array();

if ( ! empty( $settings['manage_terms'] ) && 'manage_categories' !== $settings['manage_terms'] ) {
    $capabilities['manage_terms'] = (string) $settings['manage_terms'];
}

if ( ! empty( $settings['edit_terms'] ) && 'manage_categories' !== $settings['edit_terms'] ) {
    $capabilities['edit_terms'] = (string) $settings['edit_terms'];
}

if ( ! empty( $settings['delete_terms'] ) && 'manage_categories' !== $settings['delete_terms'] ) {
    $capabilities['delete_terms'] = (string) $settings['delete_terms'];
}

if ( ! empty( $settings['assign_terms'] ) && 'edit_posts' !== $settings['assign_terms'] ) {
    $capabilities['assign_terms'] = (string) $settings['assign_terms'];
}

$default_term = array();
if ( !empty( $settings['default_term_enabled'] ) ) {
    
    if ( !empty( $settings['default_term_name'] ) ) {
        $default_term['name'] = (string) $settings['default_term_name'];
    }
    
    if ( !empty( $settings['default_term_slug'] ) ) {
        $default_term['slug'] = (string) $settings['default_term_slug'];
    }
    
    if ( !empty( $settings['default_term_description'] ) ) {
        $default_term['description'] = (string) $settings['default_term_description'];
    }
}

$query_var = 'custom_query_var' === $settings['query_var'] && !empty( $settings['query_var_name'] ) && $settings['query_var_name'] !== $settings['taxonomy'] ? "'" . $settings['query_var_name'] . "'" : 'false';

if ( !empty( $settings['rename_capabilities'] ) ) {
    $singular_capability_name = (string) $settings['singular_capability_name'];
    $plural_capability_name   = (string) $settings['plural_capability_name'];
    $capability_type          = 'post';

    if ( ! empty( $singular_capability_name ) && ! empty( $plural_capability_name ) ) {
        $capability_type = array( $singular_capability_name, $plural_capability_name );
    } elseif ( ! empty( $singular_capability_name ) ) {
        $capability_type = $singular_capability_name;
    }
}
//phpcs:disable
?>
add_action( 'init', function(){
    $labels = array(
<?php foreach( $labels  as $name => $label_data ) : ?>
<?php if( !empty($label_data['required']) || !empty($settings['manage_optional_labels']) ) : ?>
<?php if( !empty($settings[$name]) ) : ?>
        '<?php echo esc_html( $name ); ?>'<?php echo str_repeat( ' ', 19 - strlen( $name ) ); ?>=> __( "<?php echo esc_html( $settings[$name] ); ?>", '<?php echo esc_html( $text_domain ); ?>' ),
<?php endif; ?>
<?php endif; ?>
<?php endforeach; ?>
    );

    $args = array(
        'labels'                => $labels,
<?php if( !empty($settings['description']) && !empty($settings['manage_optional_labels']) ) : ?>
        'description'           => __( "<?php echo esc_html( $settings['description'] ); ?>", '<?php echo esc_html( $text_domain ); ?>' ),
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
        'rest_base'             => "<?php echo esc_html( $settings['rest_base'] ); ?>",
<?php endif; ?>
<?php if( !empty( $settings['show_in_rest'] ) && !empty( $settings['rest_namespace'] ) && $settings['rest_namespace'] !== 'wp/v2' ) : ?>
        'rest_namespace'        => "<?php echo esc_html( $settings['rest_namespace'] ); ?>",
<?php endif; ?>
<?php if( !empty( $settings['hierarchical'] ) ) : ?>
        'hierarchical'          => <?php echo esc_html( $this->text_to_boolean( $settings['hierarchical'] ) ); ?>,
<?php endif; ?>
<?php if( !empty( $settings['show_in_rest'] ) && !empty( $settings['rest_controller_class'] ) && $settings['rest_controller_class'] !== 'WP_REST_Terms_Controller' ) : ?>
        'rest_controller_class' => "<?php echo esc_html( $settings['rest_controller_class'] ); ?>",
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
<?php if( !empty( $capabilities ) ) : ?>
        'capabilities'          => array(
<?php foreach( $capabilities as $name => $capability ) : ?>
            '<?php echo esc_html( $name ); ?>'<?php echo str_repeat( ' ', 19 - strlen( $name ) ); ?>=> "<?php echo esc_html( $capability ); ?>",
<?php endforeach; ?>
        ),
<?php endif; ?>
        'rewrite'               => <?php echo $rewrite; ?>,
<?php if( $settings['query_var'] !== 'taxonomy_key' && !empty( $settings['publicly_queryable'] ) ) : ?>
        'query_var'             => <?php echo $query_var; ?>,
<?php endif; ?>
<?php if( !empty( $default_term ) ) : ?>
        'default_term'          => array(
<?php foreach( $default_term as $name => $value ) : ?>
            '<?php echo esc_html( $name ); ?>'<?php echo str_repeat( ' ', 19 - strlen( $name ) ); ?>=> "<?php echo esc_html( $value ); ?>",
<?php endforeach; ?>
        ),
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
    $args = apply_filters( 'wpmastertoolkit/register_custom_content_types/taxonomy/<?php echo esc_html( $settings['taxonomy'] ); ?>', $args );

    register_taxonomy( '<?php echo esc_html( $settings['taxonomy'] ); ?>', array( <?php echo implode( ', ', array_map( function ( $item ) {
        return "'" . $item . "'";
    }, $settings['object_type'] ) ); ?> ), $args );
});