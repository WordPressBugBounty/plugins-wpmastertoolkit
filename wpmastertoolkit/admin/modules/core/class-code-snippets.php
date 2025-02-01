<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
/**
 * Module Name: Code Snippets
 * Description: Add custom code snippets to your website.
 * 
 * For disable all snippets, add this line to your wp-config.php:
 * define('WPMASTERTOOLKIT_SNIPPETS_SAFE_MODE', true);
 * 
 * @since 1.2.0
 */
class WPMastertoolkit_Code_Snippets {

    private $code_snippets_folder_path;

    /**
     * Invoke the hooks.
     * 
     * @since   1.0.0
     */
    public function __construct() {
        add_action( 'init', array( $this, 'register_cpt_wpmastertoolkit_code_snippets' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_metaboxes' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'save_post_wpmtk_code_snippets', array( $this, 'save_code_snippet' ) );
        add_shortcode( 'wpmtk_code_snippets', array( $this, 'shortcode_wpmtk_code_snippets' ) );

        add_filter( 'manage_wpmtk_code_snippets_posts_columns', array( $this, 'add_custom_columns' ) );
        add_action( 'manage_wpmtk_code_snippets_posts_custom_column', array( $this, 'add_custom_columns_data' ), 10, 2 );

        add_action( 'admin_footer-edit.php', array( $this, 'add_button_regenerate_snippet_file' ) );
        add_action( 'admin_init', array( $this, 'regenerate_snippet_file' ) );

        add_action( 'before_delete_post', array( $this, 'delete_file_on_delete_code_snippet_post' ) );

        // filter post excerpt when saved
        add_filter( 'wp_insert_post_data', array( $this, 'filter_post_data' ), 10, 2 );

        add_filter( 'wpmastertoolkit/folders', array( $this, 'create_folders' ) );
        
        $this->snippets_loader();
    }

    /**
     * create_folders
     *
     * @param  mixed $folders
     * @return void
     */
    public function create_folders( $folders ) {
        $folders['wpmastertoolkit']['code-snippets'] = array();
        return $folders;
    }
    
    /**
     * get_code_snippet_types
     *
     * @return void
     */
    public function get_code_snippet_types(){
        return array(
            'include'   => array(
                'label'       => __( 'Include file', 'wpmastertoolkit' ),
                'icon'        => 'dashicons-media-default',
                'description' => __("The code will be included in your website. You can used add_action or add_filter in this type of snippet. The code is included before WordPress hooks.", "wpmastertoolkit" )
            ),
            'shortcode' => array(
                'label'       => __( 'Shortcode', 'wpmastertoolkit' ),
                'icon'        => 'dashicons-shortcode',
                'description' => __("The code will be used as a shortcode. Don't use WordPress hooks in this method.", "wpmastertoolkit" )
            ),
        );
    }
    
    /**
     * register_cpt_wpmastertoolkit_code_snippets
     *
     * @return void
     */
    public function register_cpt_wpmastertoolkit_code_snippets(){
        $labels = array(
            'name'                  => _x( 'Code Snippets', 'Post Type General Name', 'wpmastertoolkit' ),
            'singular_name'         => _x( 'Code Snippets', 'Post Type Singular Name', 'wpmastertoolkit' ),
            'menu_name'             => __( 'Code Snippets', 'wpmastertoolkit' ),
            'name_admin_bar'        => __( 'Code Snippets', 'wpmastertoolkit' ),
            'archives'              => __( 'Snippet Archives', 'wpmastertoolkit' ),
            'attributes'            => __( 'Snippet Attributes', 'wpmastertoolkit' ),
            'parent_item_colon'     => __( 'Parent Snippet:', 'wpmastertoolkit' ),
            'all_items'             => __( 'All Snippets', 'wpmastertoolkit' ),
            'add_new_item'          => __( 'Add New Snippet', 'wpmastertoolkit' ),
            'add_new'               => __( 'Add New', 'wpmastertoolkit' ),
            'new_item'              => __( 'New Snippet', 'wpmastertoolkit' ),
            'edit_item'             => __( 'Edit Snippet', 'wpmastertoolkit' ),
            'update_item'           => __( 'Update Snippet', 'wpmastertoolkit' ),
            'view_item'             => __( 'View Snippet', 'wpmastertoolkit' ),
            'view_items'            => __( 'View Snippets', 'wpmastertoolkit' ),
            'search_items'          => __( 'Search Snippet', 'wpmastertoolkit' ),
            'not_found'             => __( 'Not found', 'wpmastertoolkit' ),
            'not_found_in_trash'    => __( 'Not found in Trash', 'wpmastertoolkit' ),
            'featured_image'        => __( 'Featured Image', 'wpmastertoolkit' ),
            'set_featured_image'    => __( 'Set featured image', 'wpmastertoolkit' ),
            'remove_featured_image' => __( 'Remove featured image', 'wpmastertoolkit' ),
            'use_featured_image'    => __( 'Use as featured image', 'wpmastertoolkit' ),
            'insert_into_item'      => __( 'Insert into snippet', 'wpmastertoolkit' ),
            'uploaded_to_this_item' => __( 'Uploaded to this snippet', 'wpmastertoolkit' ),
            'items_list'            => __( 'Snippets list', 'wpmastertoolkit' ),
            'items_list_navigation' => __( 'Snippets list navigation', 'wpmastertoolkit' ),
            'filter_items_list'     => __( 'Filter snippet list', 'wpmastertoolkit' ),
        );

        $args = array(
            'label'                 => __( 'Code Snippets', 'wpmastertoolkit' ),
            'description'           => __( 'Add custom code snippets to your website.', 'wpmastertoolkit' ),
            'labels'                => $labels,
            'supports'              => array( 'title' ),
            'taxonomies'            => array(),
            'hierarchical'          => false,
            'public'                => false,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'menu_position'         => 101,
            'menu_icon'             => 'dashicons-editor-code',
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => false,
            'can_export'            => true,
            'has_archive'           => false,
            'exclude_from_search'   => true,
            'publicly_queryable'    => false,
            'capability_type'       => 'post',
            'show_in_rest'          => false,
            'rewrite'               => false,
        );
        register_post_type( 'wpmtk_code_snippets', $args );
    }
    
    /**
     * add_metaboxes
     *
     * @return void
     */
    public function add_metaboxes(){
        $php_logo = file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/images/php-logo.svg' );

        add_meta_box(
            'wpmtk_code_snippets_metabox',
            $php_logo . __( 'Code editor', 'wpmastertoolkit' ),
            array( $this, 'render_code_snippets_metabox' ),
            'wpmtk_code_snippets',
            'normal',
            'high'
        );
        
        add_meta_box(
            'wpmtk_code_snippets_informations_metabox',
            'ℹ️ ' . __( 'Informations', 'wpmastertoolkit' ),
            array( $this, 'render_code_snippets_informations_metabox' ),
            'wpmtk_code_snippets',
            'normal',
            'default'
        );

        remove_meta_box( 'submitdiv', 'wpmtk_code_snippets', 'side' );

        add_meta_box(
            'submitdiv',
            __( 'Settings', 'wpmastertoolkit' ),
            array( $this, 'post_submit_meta_box' ),
            'wpmtk_code_snippets',
            'side',
            'high'
        );

        // add excerpt metabox with custom title
        add_meta_box(
            'postexcerpt',
            __( 'Snippet Description', 'wpmastertoolkit' ),
            'post_excerpt_meta_box',
            'wpmtk_code_snippets',
            'side',
            'default'
        );
    }
    
    /**
     * render_code_snippets_metabox
     *
     * @param  mixed $post
     * @return void
     */
    public function render_code_snippets_metabox( $post ){
        $code_snippet       = get_post_meta( $post->ID, 'code_snippet', true );
        $code_snippet_error = get_post_meta( $post->ID, 'code_snippet_error', true );
        if( !empty($code_snippet_error) ){
            // add notice
            $line = $code_snippet_error->get_error_data()['line'] ?? 1;
            ?>
            <div class="notice notice-error is-dismissible">
                <p><?php esc_html_e( "Snippet Error:", "wpmastertoolkit" ); ?> <?php echo esc_html( $code_snippet_error->get_error_message() ); ?> <b><?php printf( esc_html( "on line %s.", "wpmastertoolkit" ), (int) $line - 1 ); ?></b></p>
            </div>
            <?php
        }
        $placeholder = "// ". __('Exemple of Include file content', "wpmastertoolkit" ) ."\n";
        $placeholder .= "add_action('wp_head', 'my_custom_function');\n";
        $placeholder .= "function my_custom_function(){\n";
        $placeholder .= "    echo 'Hello World';\n";
        $placeholder .= "}\n";
        $placeholder .= "// ". __('END Exemple of Include file content', "wpmastertoolkit" ) ."\n\n";
        $placeholder .= "// ". __('Exemple of Shortcode content', "wpmastertoolkit" ) ."\n";
        $placeholder .= "echo 'Hello World';\n";
        $placeholder .= "// ". __('END Exemple of Shortcode content', "wpmastertoolkit" );

        ?>
        <textarea name="code_snippet" id="code_snippet" placeholder="<?php echo esc_attr( $placeholder ); ?>" class="widefat" rows="10"><?php echo esc_textarea( stripslashes( $code_snippet ) ); ?></textarea>
        <?php
    }
    
    /**
     * post_submit_meta_box
     *
     * @param  mixed $post
     * @return void
     */
    public function post_submit_meta_box( $post ){
        $publish_label = 'publish' === $post->post_status ? esc_html__( 'Update' ) : esc_html__( 'Publish' );
        $snippet_type = get_post_meta( $post->ID, 'snippet_type', true );
        ?>
        <div class="wp-mastertoolkit-code-snippets__settings">
            <div class="wp-mastertoolkit-code-snippets__settings__snippet-status-container">
                <label for="snippet-status"><?php esc_html_e( 'Status', 'wpmastertoolkit' ); ?></label>
                <label class="wp-mastertoolkit__toggle">
                    <input type="hidden" name="snippet_status" value="0">
                    <input id="snippet-status" type="checkbox" name="snippet_status" value="1" <?php checked( get_post_meta( $post->ID, 'snippet_status', true ), '1' ); ?>>
                    <span class="wp-mastertoolkit__toggle__slider round"></span>
                </label> 
            </div>
            <div class="wp-mastertoolkit-code-snippets__settings__snippet-type-container">
                <label for="snippet_type"><?php esc_html_e( 'Snippet Type', 'wpmastertoolkit' ); ?></label>
                <select name="snippet_type" id="snippet_type">
                    <?php foreach( $this->get_code_snippet_types() as $type => $data ): ?>
                        <option value="<?php echo esc_attr( $type ); ?>" <?php selected( $snippet_type, $type ); ?>><?php echo esc_html( $data['label'] ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if( $snippet_type === 'shortcode' ): ?>
                <input type="text" name="" readonly value="[wpmtk_code_snippets id='<?php echo esc_attr( $post->ID ); ?>']">
            <?php endif; ?>
        </div>
        <div class="submitbox" id="submitpost">
            <div id="major-publishing-actions">
                    <div id="delete-action">
                        <a class="submitdelete deletion" href="<?php echo get_delete_post_link( $post->ID, '', true ); ?>"><?php esc_html_e( 'Delete' ); ?></a>
                    </div>

                <div id="publishing-action">
                    <span class="spinner"></span>
                    <input name="original_publish" type="hidden" id="original_publish" value="<?php esc_attr_e( 'Publish' ); ?>">
                    <input type="submit" name="publish" id="publish" class="button button-primary button-large" value="<?php echo esc_attr( $publish_label ); ?>">
                </div>
                <div class="clear"></div>
            </div>

        </div>
        <?php
    }
    
    /**
     * render_code_snippets_informations_metabox
     *
     * @param  mixed $post
     * @return void
     */
    public function render_code_snippets_informations_metabox( $post ){
        ?>
        <p><?php esc_html_e( "⚠️ Warning: Please be careful when adding custom code snippets. Incorrect code can break your website.", 'wpmastertoolkit' ); ?></p>
        <p><?php esc_html_e( 'Two types of snippets are available:', 'wpmastertoolkit' ); ?></p>
        <ul>
            <?php foreach( $this->get_code_snippet_types() as $type => $data ): ?>
                <li>
                    <span class="dashicons <?php echo esc_attr( $data['icon'] ?? '' ); ?>"></span> <?php echo esc_html( $data['label'] ?? '' ); ?> : <?php echo esc_html( $data['description'] ?? '' ); ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <p><?php esc_html_e( 'You can use JS in <script> tag or CSS in <style> tag in your snippet.', 'wpmastertoolkit' ); ?></p>
        <?php
    }

    /**
     * enqueue_scripts
     *
     * @param  mixed $hook_suffix
     * @return void
     */
    public function enqueue_scripts( $hook_suffix ){
        if ( ($hook_suffix === 'post-new.php' || $hook_suffix === 'post.php') && get_post_type() === 'wpmtk_code_snippets' ){
            
            $code_editor = wp_enqueue_code_editor( array( 
                'type' => 'php',
                'codemirror' => array( 
                    'mode'          => array(
                        "name" => 'php',
                        "startOpen" =>true
                    ),
                    'inputStyle'    => 'textarea',
                    'matchBrackets' => true,
                    'extraKeys'     => [
                        'Alt-F'      => 'findPersistent',
                        'Ctrl-Space' => 'autocomplete',
                        'Ctrl-/'     => 'toggleComment',
                        'Cmd-/'      => 'toggleComment',
                        'Alt-Up'     => 'swapLineUp',
                        'Alt-Down'   => 'swapLineDown',
                    ],
                    // 'gutters'       => [ 'CodeMirror-lint-markers', 'CodeMirror-foldgutter' ],
                    'lint'          => true,
                    'direction'     => 'ltr',
                    'colorpicker'   => [ 'mode' => 'edit' ],
                    'foldOptions'   => [ 'widget' => '...' ],
                    'theme'         => 'wpmastertoolkit',
                    'continueComments' => true,
                ),
            ) );

            $assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/code-snippets.asset.php' );
            wp_enqueue_script( 'wpmastertoolkit-code-snippets', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/code-snippets.js', $assets['dependencies'], $assets['version'], true );
            wp_enqueue_style( 'wpmastertoolkit-code-snippets', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/code-snippets.css', array(), $assets['version'], 'all' );
            wp_localize_script( 'wpmastertoolkit-code-snippets', 'wpmastertoolkit_code_snippets', array(
                'code_editor' => $code_editor,
            ) );
        }
    }
    
    /**
     * save_code_snippet
     *
     * @param  mixed $post_id
     * @return void
     */
    public function save_code_snippet( $post_id ){

        if ( ! isset( $_POST['code_snippet'], $_POST['snippet_status'], $_POST['snippet_type'] ) ) {
            return;
        }
        $status = sanitize_text_field( $_POST['snippet_status'] );
        
        update_post_meta( $post_id, 'snippet_type', sanitize_text_field( $_POST['snippet_type'] ) );

        require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/class-php-code-validator.php';

        $code_snippet = wp_unslash( $_POST['code_snippet'] );
        $code_snippet = preg_replace('/^<\?php/', '', $code_snippet);

        // remove empty line from the beginning
        $code_snippet = preg_replace('/^\s*[\r\n]/', '', $code_snippet);

        // remove empty line from the end
        $code_snippet = preg_replace('/[\r\n]\s*$/', '', $code_snippet);

        $validator  = new WPMastertoolkit_PHP_Code_Validator( '<?php' . PHP_EOL . $code_snippet );
        $validation = $validator->validate();
        
        if ( !is_wp_error($validation) ) {
            $validation = $validator->checkRunTimeError();
        }

        if ( is_wp_error($validation) ) {
            update_post_meta( $post_id, 'code_snippet_error', $validation );
        } else {
            delete_post_meta( $post_id, 'code_snippet_error' );
        }

        update_post_meta( $post_id, 'code_snippet', $code_snippet );

        if( is_wp_error($validation) ){
            $status = 0;
        }

        update_post_meta( $post_id, 'snippet_status', (int) $status );

        if( $status == 1 ){
            $this->generate_snippet_file( $post_id );
        } else {
            $this->delete_snippet_file( $post_id );
        }
        $this->delete_all_non_active_snippet_file();
    }
    
    /**
     * filter_post_data
     *
     * @param  mixed $data
     * @param  mixed $postarr
     * @return void
     */
    public function filter_post_data( $data, $postarr ){
        if( isset($data['post_type'] ) && $data['post_type'] === 'wpmtk_code_snippets' ){
            if( isset( $data['post_excerpt']) ) {
                $data['post_excerpt'] = self::remove_unauthorized_caracters( $data['post_excerpt'] );
            }

            if( isset( $data['post_title']) ) {
                $data['post_title'] = self::remove_unauthorized_caracters( $data['post_title'] );
            }
        }
        return $data;
    }
    
    /**
     * remove_unauthorized_caracters
     *
     * @param  mixed $string
     * @return string
     */
    public function remove_unauthorized_caracters( $string ):string {
        $unauthorized = array( 
            '/*', 
            '*/',
            '#',
            '//',
            '<?php', 
        );
        return trim( str_replace( $unauthorized, '', $string ) );
    }
    
    /**
     * get_code_snippets_folder_path
     *
     * @return void
     */
    function get_code_snippets_folder_path(){
        if( !empty($this->code_snippets_folder_path) ){
            return $this->code_snippets_folder_path;
        }
        return wpmastertoolkit_folders() . '/code-snippets';
    }
    
    /**
     * get_snippet_file_path
     *
     * @param  mixed $post_id
     * @return void
     */
    function get_snippet_file_path( $post_id, $type = null ) {
        $code_snippets_folder_path = $this->get_code_snippets_folder_path();
        $type                      = !empty($type) ? $type : get_post_meta( $post_id, 'snippet_type', true );
        return $code_snippets_folder_path . '/' . esc_attr( $type ) . '-' . $post_id . '.php';
    }
        
    /**
     * generate_snippet_file
     *
     * @param  mixed $post_id
     * @return void
     */
    public function generate_snippet_file( $post_id ){
        $type        = get_post_meta( $post_id, 'snippet_type', true );
        $title       = get_the_title( $post_id );
        $description = get_the_excerpt( $post_id );
        
        if( $type == 'shortcode' ){
            $usage = 'Paste this shortcode [wpmtk_code_snippets id="' . $post_id . '"]';
        } else {
            $usage = 'This snippet is included in your website. You can used add_action or add_filter in this type of snippet. The code is included before WordPress hooks.';
        }

        $code_snippet = get_post_meta( $post_id, 'code_snippet', true );
        $code_snippet = preg_replace('/^<\?php/', '', $code_snippet);
        $code_snippet = preg_replace('/^\s*[\r\n]/', '', $code_snippet);
        $code_snippet = preg_replace('/[\r\n]\s*$/', '', $code_snippet);
        $file_content = '<?php' . PHP_EOL;
        $file_content .= '/**' . PHP_EOL;
        $file_content .= !empty($title) ? ' * Snippet Title: ' . $title . PHP_EOL : '';
        $file_content .= ' * Snippet ID: ' . $post_id . PHP_EOL;
        $file_content .= !empty($description) ? ' * Snippet Description: ' . $description . PHP_EOL : '';
        $file_content .= ' * Snippet type: ' . $type . PHP_EOL;
        $file_content .= ' * Usage: ' . $usage . PHP_EOL;
        $file_content .= ' * Generated at: ' . wp_date('Y-m-d H:i:s') . PHP_EOL;
        $file_content .= ' *' . PHP_EOL;
        $file_content .= ' * @author This code snippet is generated by WPMasterToolkit' . PHP_EOL;
        $file_content .= ' * @link ' . get_edit_post_link( $post_id, '' ) . PHP_EOL;
        $file_content .= ' * @since ' . WPMASTERTOOLKIT_VERSION . PHP_EOL;
        $file_content .= ' *' . PHP_EOL;
        $file_content .= '**/' . PHP_EOL;
        $file_content .= $code_snippet;

        $file_path = $this->get_snippet_file_path( $post_id, $type );
        return file_put_contents( $file_path, $file_content );
    }
    
    /**
     * delete_snippet_file
     *
     * @param  mixed $post_id
     * @return void
     */
    public function delete_snippet_file( $post_id ){
        $file_path = $this->get_snippet_file_path( $post_id );
        if( file_exists($file_path) ){
            return unlink( $file_path );
        }
    }
    
    /**
     * generate_all_active_snippets
     *
     * @return void
     */
    public function generate_all_active_snippets() {
        $args = array(
            'post_type' => 'wpmtk_code_snippets',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'snippet_status',
                    'value' => '1',
                    'compare' => '='
                )
            ),
            'fields' => 'ids'
        );
        $snippets = get_posts( $args );
        foreach( $snippets as $snippet_id ){
            $this->generate_snippet_file( $snippet_id );
        }
    }
    
    /**
     * delete_all_files_in_code_snippets_folder
     *
     * @return void
     */
    public function delete_all_non_active_snippet_file(){
        $code_snippets_folder_path = $this->get_code_snippets_folder_path();
        $files = glob( $code_snippets_folder_path . '/*' );
        $args = array(
            'post_type' => 'wpmtk_code_snippets',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'snippet_status',
                    'value' => '1',
                    'compare' => '='
                )
            ),
            'fields' => 'ids'
        );
        $snippets = get_posts( $args );
        $active_snippet_files = array();
        foreach( $snippets as $snippet_id ){
            $active_snippet_files[] = $this->get_snippet_file_path( $snippet_id );
        }
        foreach( $files as $file ){
            if( !in_array($file, $active_snippet_files) && strpos($file, 'index.php') === false ){
                unlink( $file );
            }
        }
    }
    
    /**
     * snippets_loader
     *
     * @return void
     */
    public function snippets_loader(){
        /**
         * Prevent load on post edit action to avoid already defined function error
         */
        if( 
            isset( $_POST['post_type'], $_POST['action'] ) && 
            $_POST['post_type'] === 'wpmtk_code_snippets' &&
            $_POST['action'] === 'editpost'
        ) return;

        if( defined('WPMASTERTOOLKIT_SNIPPETS_SAFE_MODE') && WPMASTERTOOLKIT_SNIPPETS_SAFE_MODE === true ) return;
        $code_snippets_folder_path = $this->get_code_snippets_folder_path();
        $files = glob( $code_snippets_folder_path . '/include-*.php' );
        foreach( $files as $file ){
            $file_name = str_replace('.php', '', basename( $file ));
            if( preg_match('/^include-(\d+)$/', $file_name, $matches) ){
                require_once $file;
            }
        }
    }
    
    /**
     * shortcode_wpmtk_code_snippets
     *
     * @param  mixed $atts
     * @return void
     */
    public function shortcode_wpmtk_code_snippets( $atts ){
        if( defined('WPMASTERTOOLKIT_SNIPPETS_SAFE_MODE') && WPMASTERTOOLKIT_SNIPPETS_SAFE_MODE === true ) return;

        $atts = shortcode_atts( array(
            'id' => '',
        ), $atts, 'wpmtk_code_snippets' );

        if( empty($atts['id']) ) return;

        $post_id = (int) sanitize_text_field( $atts['id'] );
        $type    = get_post_meta( $post_id, 'snippet_type', true );
        $status  = get_post_meta( $post_id, 'snippet_status', true );

        if( $status !== '1' ) return;
        
        if( $type !== 'shortcode' ) return;
        
        ob_start();
        $file_path = $this->get_snippet_file_path( $post_id, $type );
        if( file_exists($file_path) ){
            include $file_path;
        } else {
            $this->generate_snippet_file( $post_id );
            include $file_path;
        }
        return ob_get_clean();
    }

    /**
     * add_custom_columns
     *
     * @param  mixed $columns
     * @return void
     */
    public function add_custom_columns( $columns ){
        // add excerpt column
        $columns['snippet_description'] = __( 'Description', 'wpmastertoolkit' );
        $columns['snippet_type']   = __( 'Type', 'wpmastertoolkit' );
        $columns['snippet_status'] = __( 'Status', 'wpmastertoolkit' );
        return $columns;
    }

    /**
     * add_custom_columns_data
     *
     * @param  mixed $column
     * @param  mixed $post_id
     * @return void
     */
    public function add_custom_columns_data( $column, $post_id ){
        switch( $column ){
            case 'snippet_description':
                echo esc_html( get_the_excerpt( $post_id ) );
                break;
            case 'snippet_status':
                $status = get_post_meta( $post_id, 'snippet_status', true );
                $dashicon = $status === '1' ? 'dashicons-yes' : 'dashicons-no';
                $color    = $status === '1' ? 'green' : 'red';
                ?>
                <div>
                    <span class="dashicons <?php echo esc_attr( $dashicon ); ?>" style="color: <?php echo esc_attr( $color ); ?>"></span>
                </div>
                <?php
                break;
            case 'snippet_type':
                $snippet_type = get_post_meta( $post_id, 'snippet_type', true );
                foreach( $this->get_code_snippet_types() as $type => $data ){
                    if( $snippet_type === $type ){
                        ?>
                        <div>
                            <span class="dashicons <?php echo esc_attr( $data['icon'] ?? '' ); ?>"></span> <?php echo esc_html( $data['label'] ); ?>
                        </div>
                        <?php
                    }
                }
                break;
            default:
                break;
        }
    }
    
    /**
     * add_button_regenerate_snippet_file
     *
     * @return void
     */
    public function add_button_regenerate_snippet_file(){
        global $post_type;
        if( $post_type === 'wpmtk_code_snippets' ){
            $button_label = __( 'Regenerate Snippets Files', 'wpmastertoolkit' );
            ?>
            <script>
                jQuery(document).ready(function($){
                    const add_new_button = $('.page-title-action');
                    const url = new URL(window.location.href);
                    url.searchParams.set('regenerate_snippet_file', 'true');
                    url.searchParams.set('_wpnonce', '<?php echo esc_attr( wp_create_nonce( 'regenerate_snippet_file' ) ); ?>');
                    const regenerate = $('<a href="'+url.href+'" class="page-title-action"><?php echo wp_kses_post( $button_label ); ?></a>');
                    add_new_button.after(regenerate);
                });
            </script>
            <?php
        }
    }

    /**
     * regenerate_snippet_file
     *
     * @return void
     */
    public function regenerate_snippet_file(){
        if( !isset($_GET['post_type']) || $_GET['post_type'] !== 'wpmtk_code_snippets' ) return;

        if( isset($_GET['regenerate_snippet_file']) && $_GET['regenerate_snippet_file'] === 'true' ){
            if( !isset($_GET['_wpnonce']) || !wp_verify_nonce( $_GET['_wpnonce'], 'regenerate_snippet_file' ) ) return;

            $this->delete_all_non_active_snippet_file();
            $this->generate_all_active_snippets();

            wp_safe_redirect( admin_url( 'edit.php?post_type=wpmtk_code_snippets&regenerated=true' ) );
            exit;
        }

        if( isset($_GET['regenerated']) && $_GET['regenerated'] === 'true' ){
            add_action( 'admin_notices', function(){
                ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e( 'All active snippets have been regenerated.', 'wpmastertoolkit' ); ?></p>
                </div>
                <?php
            });
        }
    }

    /**
     * delete_file_on_delete_code_snippet_post
     *
     * @param  mixed $post_id
     * @return void
     */
    public function delete_file_on_delete_code_snippet_post( $post_id ){
        if( get_post_type( $post_id ) === 'wpmtk_code_snippets' ){
            $this->delete_snippet_file( $post_id );
        }
    }
}
