<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

$labels = $this->get_cpt_labels();
unset( $labels['post_type'] );
unset( $labels['description'] );

$text_domain = !empty($settings['text_domain']) ? $settings['text_domain'] : 'wpmastertoolkit';

$permalink_rewrite = $settings['permalink_rewrite'] ?? 'post_type_key';
$rewrite_enabled   = $permalink_rewrite === 'no_permalink' ? false : true;
$rewrite           = $this->text_to_boolean( $rewrite_enabled );
$rewrite_args      = array();

if ( ! empty( $settings['slug'] ) && $settings['slug'] !== $settings['post_type'] && 'custom_permalink' === $settings['permalink_rewrite'] ) {
    $rewrite_args['slug'] = (string) $settings['slug'];
}

if ( isset( $settings['with_front'] ) && empty( $settings['with_front'] ) && $rewrite_enabled ) {
    $rewrite_args['with_front'] = false;
}

if ( $settings['feeds'] !== $settings['has_archive'] && $rewrite_enabled ) {
    $rewrite_args['feeds'] = (bool) $settings['feeds'];
}

if ( empty($settings['pages']) && $rewrite_enabled ) {
    $rewrite_args['pages'] = false;
}

if( !empty($rewrite_args) ) {
    $rewrite = 'array( ' . implode( ', ', array_map( function ( $key, $value ) {
        return "'" . $key . "' => " . ( is_bool( $value ) ? ( $value ? 'true' : 'false' ) : "'" . $value . "'" );
    }, array_keys( $rewrite_args ), $rewrite_args ) ) . ' )';
}

$query_var = 'custom_query_var' === $settings['query_var'] && !empty( $settings['query_var_name'] ) && $settings['query_var_name'] !== $settings['post_type'] ? "'" . $settings['query_var_name'] . "'" : 'false';

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
        'exclude_from_search'   => <?php echo esc_html( $this->text_to_boolean( $settings['exclude_from_search'] ) ); ?>,
        'publicly_queryable'    => <?php echo esc_html( $this->text_to_boolean( $settings['publicly_queryable'] ) ); ?>,
        'show_ui'               => <?php echo esc_html( $this->text_to_boolean( $settings['show_ui'] ) ); ?>,
<?php if( $settings['show_in_menu'] !== $settings['show_ui'] || !empty($settings['admin_menu_parent']) ) : ?>
        'show_in_menu'          => <?php echo !empty($settings['admin_menu_parent']) ? "'" . esc_url($settings['admin_menu_parent']) . "'" : $this->text_to_boolean( $settings['show_in_menu'] ); ?>,
<?php endif; ?>
<?php if( $settings['show_in_nav_menus'] !== $settings['public'] ) : ?>
        'show_in_nav_menus'     => <?php echo esc_html( $this->text_to_boolean( $settings['show_in_nav_menus'] ) ); ?>,
<?php endif; ?>
<?php if( $settings['show_in_admin_bar'] !== $settings['show_in_menu'] ) : ?>
        'show_in_admin_bar'     => <?php echo esc_html( $this->text_to_boolean( $settings['show_in_admin_bar'] ) ); ?>,
<?php endif; ?>
        'show_in_rest'          => <?php echo esc_html( $this->text_to_boolean( $settings['show_in_rest'] ) ); ?>,
<?php if( !empty( $settings['show_in_rest'] ) && !empty( $settings['rest_base'] ) && $settings['rest_base'] !== $settings['post_type'] ) : ?>
        'rest_base'             => "<?php echo esc_html( $settings['rest_base'] ); ?>",
<?php endif; ?>
<?php if( !empty( $settings['show_in_rest'] ) && !empty( $settings['rest_namespace'] ) && $settings['rest_namespace'] !== 'wp/v2' ) : ?>
        'rest_namespace'        => "<?php echo esc_html( $settings['rest_namespace'] ); ?>",
<?php endif; ?>
<?php if( !empty( $settings['menu_position'] ) ) : ?>
        'menu_position'         => <?php echo esc_html( (int) $settings['menu_position'] ); ?>,
<?php endif; ?>
        'menu_icon'             => "<?php echo esc_html( !empty($settings['use_dashicon']) && !empty($settings['menu_icon']) ? $settings['menu_icon'] : $settings['custom_menu_icon'] ); ?>",
<?php if( !empty( $settings['rename_capabilities'] ) && $capability_type !== 'post' && $capability_type !== array( 'post', 'posts' ) ) : ?>
        'capability_type'       => <?php echo is_array( $capability_type ) ? 'array( "' . implode( '", "', $capability_type ) . '" )' : "'" . $capability_type . "'" ?>,
        'map_meta_cap'          => true,
<?php endif; ?>
<?php if( !empty( $settings['hierarchical'] ) ) : ?>
        'hierarchical'          => <?php echo esc_html( $this->text_to_boolean( $settings['hierarchical'] ) ); ?>,
<?php endif; ?>
        'supports'              => array( <?php echo implode( ', ', array_map( function ( $item ) {
            return '"' . $item . '"';
        }, $settings['supports'] ) ); ?> ),
<?php if( !empty( $settings['taxonomies'] ) ) : ?>
        'taxonomies'            => array( <?php echo implode( ', ', array_map( function ( $item ) {
            return '"' . $item . '"';
        }, $settings['taxonomies'] ) ); ?> ),
<?php endif; ?>
<?php if( !empty( $settings['has_archive'] ) ) : ?>
        'has_archive'           => <?php echo !empty($settings['archive_slug']) && $settings['archive_slug'] !== $settings['post_type'] ? "'" . $settings['archive_slug'] . "'" : 'true'; ?>,
<?php endif; ?>
<?php if( !empty( $settings['show_in_rest'] ) && !empty( $settings['rest_controller_class'] ) && $settings['rest_controller_class'] !== 'WP_REST_Posts_Controller' ) : ?>
        'rest_controller_class' => "<?php echo esc_html( $settings['rest_controller_class'] ); ?>",
<?php endif; ?>
        'query_var'             => <?php echo esc_html( $this->text_to_boolean( $settings['query_var'] ) ); ?>,
        'rewrite'               => <?php echo $rewrite; ?>,
<?php if( $settings['query_var'] !== 'post_type_key' && !empty( $settings['publicly_queryable'] ) ) : ?>
        'query_var'             => <?php echo $query_var; ?>,
<?php endif; ?>
<?php if( empty($settings['can_export']) ) : ?>
        'can_export'            => false,
<?php endif; ?>
        'delete_with_user'      => <?php echo esc_html( $this->text_to_boolean( $settings['delete_with_user'] ) ); ?>,
    );
    register_post_type( '<?php echo esc_html( $settings['post_type'] ); ?>', $args );
});