<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Meta Debugger
 * Description: Display all metadata for a post, user, term, or comment.
 * @since 1.4.0
 */
class WPMastertoolkit_Meta_Debugger {

    private $nonce  = 'wpmastertoolkit_meta_debugger_nonce';
    private $action = 'wpmastertoolkit_meta_debugger_action';

    /**
     * Invoke the hooks
     * 
     * @since    1.4.0
     */
    public function __construct() {
        add_action( 'init', array( $this, 'class_init' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
        add_action( 'show_user_profile', array( $this, 'render_user_meta_box' ) );
        add_action( 'edit_user_profile', array( $this, 'render_user_meta_box' ) );
        add_action( 'wp_ajax_' . $this->action, array( $this, 'get_meta_data' ) );
    }

    /**
     * Initialize the class
     * 
     * @since    1.4.0
     */
    public function class_init() {

        if ( ! $this->user_allowed() ) {
            return;
        }

        $all_taxonomies = get_taxonomies();
        foreach ( $all_taxonomies as $taxonomy ) {
            add_action( $taxonomy . '_edit_form_fields', array( $this, 'render_term_meta_box' ) );
        }
    }

    /**
     * Add meta boxes
     * 
     * @since   1.4.0
     */
    public function add_meta_boxes( $post_type ) {

        if ( ! $this->user_allowed() ) {
            return;
        }

        add_meta_box(
            'wpmastertoolkit-meta-debugger',
            esc_html__( 'WPMastertoolkit Meta Debugger', 'wpmastertoolkit' ),
            array( $this, 'render_post_and_comment_meta_box' ),
            $post_type,
            'normal',
            'low'
        );
    }

    /**
     * Render post & comment meta box
     * 
     * @since   1.4.0
     */
    public function render_post_and_comment_meta_box( $post ) {

        $type = 'post';
        $id   = $post->ID;

        if ( ! empty( $post->comment_ID ) ) {
            $type = 'comment';
            $id   = $post->comment_ID;
        }

        $this->render_meta_debugger( $id, $type );
    }

    /**
     * Render user meta box
     * 
     * @since   1.4.0
     */
    public function render_user_meta_box( $user ) {

        if ( ! $this->user_allowed() ) {
            return;
        }

        echo '<h2>' . esc_html__( 'WPMastertoolkit Meta Debugger', 'wpmastertoolkit' ) . '</h2>';
        $this->render_meta_debugger( $user->ID, 'user' );
    }

    /**
     * Render term meta box
     * 
     * @since   1.4.0
     */
    public function render_term_meta_box( $term ) {
        ?>
            <tr class="form-field">
                <th scope="row"><label for="wpmastertoolkit_meta_debugger"><?php esc_html_e( 'WPMastertoolkit Meta Debugger', 'wpmastertoolkit' ); ?></label></th>
                <td><?php $this->render_meta_debugger( $term->term_id, 'term' ); ?></td>
            </tr>
        <?php
    }

    /**
     * Render meta debugger
     * 
     * @since   1.4.0
     */
    public function render_meta_debugger( $id, $type ) {

        $meta_debugger_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/meta-debugger.asset.php' );
        wp_enqueue_style( 'WPMastertoolkit_meta_debugger', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/meta-debugger.css', array(), $meta_debugger_assets['version'], 'all' );
        wp_enqueue_script( 'WPMastertoolkit_meta_debugger', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/meta-debugger.js', $meta_debugger_assets['dependencies'], $meta_debugger_assets['version'], true );
        wp_localize_script( 'WPMastertoolkit_meta_debugger', 'wpmastertoolkit_meta_debugger', array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( $this->nonce ),
            'action'  => $this->action,
        ) );

        ?>
            <div class="wpmastertoolkit-meta-debugger">
                <button class="wpmastertoolkit-meta-debugger__button button" id="JSWPMastertoolkit_meta_debugger_button" data-id="<?php echo esc_attr( $id ); ?>" data-type="<?php echo esc_attr( $type ); ?>" type="button">
                    <?php esc_html_e( 'Show all meta data', 'wpmastertoolkit' ); ?>
                    <div class="spinner"></div>
                </button>
                <div class="wpmastertoolkit-meta-debugger__container" id="JSWPMastertoolkit_meta_debugger_container"></div>
            </div>
        <?php
    }

    /**
     * Get meta data
     * 
     * @since   1.4.0
     */
    public function get_meta_data() {

        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, $this->nonce ) ) {
            wp_send_json_error( '<div><p>' . esc_html__( 'Refresh the page and try again.', 'wpmastertoolkit' ) . '</p></div>' );
        }

        if ( ! $this->user_allowed() ) {
            wp_send_json_error( '<div><p>' . esc_html__( 'You are not allowed to perform this action', 'wpmastertoolkit' ) . '</p></div>' );
        }

        $id   = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
        $type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';

        switch ( $type ) {
            case 'post':
                $data = get_post_meta( $id );
                break;
            case 'user':
                $data = get_user_meta( $id );
                break;
            case 'term':
                $data = get_term_meta( $id );
                break;
            case 'comment':
                $data = get_comment_meta( $id );
                break;
            default:
                $data = array();
                break;
        }

        if ( empty( $data ) ) {
            wp_send_json_error( '<div><p>' . esc_html__( 'No meta data found', 'wpmastertoolkit' ) . '</p></div>' );
        }

        $result = array();
        foreach ( $data as $meta_name => $meta_value ) {
            $result[ $meta_name ] = maybe_unserialize( $meta_value[0] );
        }

        wp_send_json_success( $result );
    }

    /**
     * Is user allowed
     * 
     * @since   1.4.0
     */
    private function user_allowed() {
        /**
         * Filter the allowed roles to access the meta debugger
         *
         * @since 1.4.0
         *
         * @param array    $allowed_roles      Array of allowed roles.
         */
        $allowed_roles      = apply_filters( 'wpmastertoolkit/meta_debugger/allowed_roles', array( 'administrator' ) );
        $current_user_roles = wp_get_current_user()->roles;
        $intersect          = array_intersect( $allowed_roles, $current_user_roles );

        if ( empty( $intersect ) ) {
            return false;
        }

        return true;
    }
}
