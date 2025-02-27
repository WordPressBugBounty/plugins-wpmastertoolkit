<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Revisions Control
 * Description: 
 * @since 1.3.0
 */
class WPMastertoolkit_Revisions_Control {

    private $option_id;
    private $header_title;
    private $nonce_action;
    private $settings;
    private $default_settings;

    /**
     * Invoke the hooks
     */
    public function __construct() {

        $this->option_id    = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_revisions_control';
        $this->nonce_action = $this->option_id . '_action';

        add_action( 'init', array( $this, 'class_init' ) );
        add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
        add_action( 'admin_init', array( $this, 'save_submenu' ) );
        add_filter( 'wp_revisions_to_keep', array( $this, 'limit_revisions' ), 10, 2 );
    }

    /**
     * Initialize the class
     */
    public function class_init() {
        $this->header_title = esc_html__( 'Revisions Control', 'wpmastertoolkit' );
    }

    /**
     * Limit the number of revisions for post types
     */
    public function limit_revisions( $num, $post ) {

        $settings            = $this->get_settings();
		$settings_global     = $settings['global'];
		$settings_post_types = $settings['post_types'];

		if ( '1' === $settings_global['enabled'] ) {
			$num = $settings_global['number'] ?? '0';
		} else {
			$post_type  = $post->post_type;
			$is_enabled = $settings_post_types[$post_type]['enabled'] ?? '0';
	
			if ( '1' === $is_enabled ) {
				$num = $settings_post_types[$post_type]['number'] ?? '0';
			}
		}

        return $num;
    }

    /**
     * Add a submenu
     */
    public function add_submenu(){

        add_submenu_page(
            'wp-mastertoolkit-settings',
            $this->header_title,
            $this->header_title,
            'manage_options',
            'wp-mastertoolkit-settings-revisions-control',
            array( $this, 'render_submenu'),
            null
        );
    }

    /**
     * Render the submenu
     */
    public function render_submenu() {

        $submenu_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/revisions-control.asset.php' );
        wp_enqueue_style( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/revisions-control.css', array(), $submenu_assets['version'], 'all' );
        wp_enqueue_script( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/revisions-control.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );

        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
        $this->submenu_content();
        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/footer.php';
    }

    /**
     * Save the submenu option
     */
    public function save_submenu() {

		$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
		
		if ( wp_verify_nonce($nonce, $this->nonce_action) ) {

			//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $new_settings = $this->sanitize_settings( $_POST[$this->option_id] ?? array() );
            
            $this->save_settings( $new_settings );
            wp_safe_redirect( sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) );
			exit;
		}
    }

    /**
     * sanitize_settings
     * 
     * @return array
     */
    public function sanitize_settings($new_settings){

        $this->default_settings = $this->get_default_settings();
        $sanitized_settings = array();

        foreach ( $this->default_settings as $settings_key => $settings_value ) {
            
            switch ($settings_key) {
				case 'global':
					$sanitized_settings[$settings_key]['enabled'] = sanitize_text_field( $new_settings[$settings_key]['enabled'] ?? $settings_value['enabled'] );
					$sanitized_settings[$settings_key]['number']  = sanitize_text_field( $new_settings[$settings_key]['number'] ?? $settings_value['number'] );
				break;
                case 'post_types':
                    foreach ( $settings_value as $post_type => $post_status ) {
                        $sanitized_settings[$settings_key][$post_type]['enabled'] = sanitize_text_field( $new_settings[$settings_key][$post_type]['enabled'] ?? '0' );
                        $sanitized_settings[$settings_key][$post_type]['number']  = sanitize_text_field( $new_settings[$settings_key][$post_type]['number'] ?? '0' );
                    }
                break;
            }
        }

        return $sanitized_settings;
    }

    /**
     * get_settings
     */
    private function get_settings(){
        $this->default_settings = $this->get_default_settings();
        return get_option( $this->option_id, $this->default_settings );
    }

    /**
     * Save settings
     */
    private function save_settings( $new_settings ) {
		update_option( $this->option_id, $new_settings );
    }

    /**
     * get_default_settings
     *
     * @return array
     */
    private function get_default_settings(){
        if( $this->default_settings !== null ) return $this->default_settings;

        return array(
			'global'     => array(
				'enabled' => '0',
				'number'  => '3',
			),
            'post_types' => $this->get_post_types_settings(),
        );
    }

    /**
     * Add the submenu content
     */
    private function submenu_content() {
        $this->settings = $this->get_settings();
        $post_types     = $this->get_post_types();

        ?>
            <div class="wp-mastertoolkit__section">
                <div class="wp-mastertoolkit__section__desc"><?php esc_html_e('Avoid overloading the database by setting a cap on the number of revisions to save for certain or all types of posts that support revisions.', 'wpmastertoolkit'); ?></div>
                <div class="wp-mastertoolkit__section__body">

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__content">
							<label class="wp-mastertoolkit__toggle">
								<input type="hidden" name="<?php echo esc_attr( $this->option_id . '[global][enabled]' ); ?>" value="0">
								<input type="checkbox" name="<?php echo esc_attr( $this->option_id . '[global][enabled]' ); ?>" value="1" <?php checked( $this->settings['global']['enabled'] ?? '', 1 ); ?> id="JS-wpmastertoolkit-revisions-control-toggle">
								<span class="wp-mastertoolkit__toggle__slider round"></span>
							</label>
							<?php esc_html_e("Manage revisions globally", 'wpmastertoolkit'); ?>
                        </div>
                    </div>

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__content" id="JS-wpmastertoolkit-revisions-control-global" style="<?php echo $this->settings['global']['enabled'] ? '' : 'display: none;'; ?>">
							<div class="wp-mastertoolkit__input-text">
								<input type="number" name="<?php echo esc_attr( $this->option_id . '[global][number]' ); ?>" value="<?php echo esc_attr( $this->settings['global']['number'] ?? '' ); ?>" style="width: 54px;">
								<span class="wp-mastertoolkit__checkbox-number__label__text"><?php esc_html_e( 'All post types', 'wpmastertoolkit' ); ?></span>
                            </div>
						</div>
					</div>

                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__content flex flex-wrap" id="JS-wpmastertoolkit-revisions-control-post-types" style="<?php echo $this->settings['global']['enabled'] ? 'display: none;' : ''; ?>">
                            <?php foreach ( $post_types as $post_type ): ?>

                                <div class="wp-mastertoolkit__checkbox-number">
                                    <label class="wp-mastertoolkit__checkbox-number__label">

                                        <div class="wp-mastertoolkit__checkbox-number__label__checkbox">
                                            <input type="hidden" name="<?php echo esc_attr( $this->option_id . '[post_types]['.$post_type->name .'][enabled]' ); ?>" value="0">
                                            <input type="checkbox" name="<?php echo esc_attr( $this->option_id . '[post_types]['.$post_type->name .'][enabled]' ); ?>" value="1"<?php checked( $this->settings['post_types'][$post_type->name]['enabled'] ?? '', '1' ); ?>>
                                            <span class="mark"></span>
                                        </div>

                                        <div class="wp-mastertoolkit__checkbox-number__label__number">
                                            <input type="number" name="<?php echo esc_attr( $this->option_id . '[post_types]['.$post_type->name .'][number]' ); ?>" value="<?php echo esc_attr( $this->settings['post_types'][$post_type->name]['number'] ?? '0' ); ?>">
                                        </div>

                                        <span class="wp-mastertoolkit__checkbox-number__label__text"><?php echo esc_html($post_type->label); ?> <span>(<?php echo esc_html($post_type->name); ?>)</span></span>
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

        foreach ( get_post_types( array(), 'names' ) as $post_type ) {

            if ( post_type_supports( $post_type, 'revisions' ) ) {
                $result[] = get_post_type_object( $post_type );
            }
        }

        return $result;
    }

    /**
     * Get the post types settings
     */
    private function get_post_types_settings() {

        $result = array();

        foreach ( $this->get_post_types() as $post_type ) {

            $slug = $post_type->name;
            $result[$slug] = array(
                'enabled' => '0',
                'number'  => '0'
            );
        }

        return $result;
    }
}
