<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: External Links New Tabs
 * Description: Open external links in new tabs
 * @since 1.0.0
 */
class WPMastertoolkit_External_Links_New_Tabs {

	private $option_id;
	private $header_title;
	private $nonce_action;
	private $settings;
	private $default_settings;

    /**
     * Invoke Wp Hooks
	 *
     * @since    1.0.0
	 */
    public function __construct() {

		$this->option_id    = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_external_links_new_tabs';
		$this->nonce_action = $this->option_id . '_action';

		add_action( 'init', array( $this, 'class_init' ) );
        add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
        add_action( 'admin_init', array( $this, 'save_submenu' ) );
        
        add_filter( 'the_content', array( $this, 'filter_content' ) );
    }

	/**
     * Initialize the class
	 * 
	 * @since   2.7.0
     */
    public function class_init() {
        $this->header_title = esc_html__( 'Open All External Links in New Tab', 'wpmastertoolkit' );
    }

	/**
     * Add a submenu
	 * 
	 * @since 2.7.0
     */
    public function add_submenu(){
        add_submenu_page(
            'wp-mastertoolkit-settings',
            $this->header_title,
            $this->header_title,
            'manage_options',
            'wp-mastertoolkit-settings-external-links-new-tab',
			array( $this, 'render_submenu'),
            null
        );
    }

	/**
     * Render the submenu
     * 
     * @since   2.7.0
     */
    public function render_submenu() {
        $submenu_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/external-links-new-tab.asset.php' );
        wp_enqueue_style( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/external-links-new-tab.css', array(), $submenu_assets['version'], 'all' );
        wp_enqueue_script( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/external-links-new-tab.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );

        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
        $this->submenu_content();
        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/footer.php';
    }

	/**
     * Save the submenu option
	 * 
	 * @since   2.7.0
     */
    public function save_submenu() {

		$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
		if ( wp_verify_nonce($nonce, $this->nonce_action) ) {

			//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $new_settings = $this->sanitize_settings( wp_unslash( $_POST[$this->option_id] ?? array() ) );
            
            $this->save_settings( $new_settings );
            wp_safe_redirect( sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) );
			exit;
		}
    }

    /**
     * Add target="_blank" rel="noopener noreferrer nofollow" to external links
     * 
     * @since   1.0.0
     */
    public function filter_content( $content ) {

        if ( ! empty( $content ) ) {
			$this->settings         = $this->get_settings();
			$this->default_settings = $this->get_default_settings();
			$target                 = $this->settings['target'] ?? $this->default_settings['target'];
			$noopener               = $this->settings['noopener'] ?? $this->default_settings['noopener'];
			$noreferrer             = $this->settings['noreferrer'] ?? $this->default_settings['noreferrer'];
			$nofollow               = $this->settings['nofollow'] ?? $this->default_settings['nofollow'];

            $regexp         = "<a\\s[^>]*href=(\"??)([^\" >]*?)\\1[^>]*>";
            $matches_count  = preg_match_all( "/{$regexp}/siU", $content, $matches, PREG_SET_ORDER );

            if ( $matches_count && $matches_count > 0 ) {

                if ( is_array( $matches ) ) {

                    foreach ( $matches as $match ) {

                        $original_tag   = $match[0];
                        $tag            = $match[0];
                        $url            = $match[2];

                        if ( strpos( $url, get_site_url() ) === false && strpos( $url, 'http' ) !== false ) {

							if ( '1' == $target ) {
								$pattern = '/target\\s*=\\s*"\\s*_(blank|parent|self|top)\\s*"/';
								if ( preg_match( $pattern, $tag ) === 0 ) {
									$tag = substr_replace( $tag, ' target="_blank">', -1 );
								}
							}

                            $pattern = '/rel\\s*=\\s*\\"[a-zA-Z0-9_\\s]*\\"/';
                            if ( preg_match( $pattern, $tag ) === 0 ) {
								$value = array();
								if ( '1' == $noopener ) {
									$value[] = 'noopener';
								}
								if ( '1' == $noreferrer ) {
									$value[] = 'noreferrer';
								}
								if ( '1' == $nofollow ) {
									$value[] = 'nofollow';
								}
								if ( ! empty( $value ) ) {
									$tag = substr_replace( $tag, ' rel="' . implode( ' ', $value ) . '">', -1 );
								}
                            }
                            
                            $content = str_replace( $original_tag, $tag, $content );
                        }
                    }
                }
            }
        }

        return $content;
    }

	/**
     * Sanitize settings
     * 
     * @isnce   2.7.0
     */
    private function sanitize_settings( $new_settings ){
        $this->default_settings = $this->get_default_settings();
        $sanitized_settings = array();

        foreach ( $this->default_settings as $settings_key => $settings_value ) {
			$sanitized_settings[$settings_key] = sanitize_text_field( $new_settings[$settings_key] ?? $settings_value );
        }

        return $sanitized_settings;
    }

	/**
     * Save settings
	 * 
	 * @since   2.7.0
     */
    private function save_settings( $new_settings ) {
		update_option( $this->option_id, $new_settings );
    }

	/**
     * Get settings
     *
     * @since   2.7.0
     */
    private function get_settings(){
		if ( $this->settings !== null ) {
			return $this->settings;
		}

		$this->default_settings = $this->get_default_settings();
		$this->settings         = get_option( $this->option_id, $this->default_settings );
		return $this->settings;
    }

	/**
     * Get default settings
     *
     * @since   2.7.0
     */
    private function get_default_settings(){
        if ( $this->default_settings !== null ) {
			return $this->default_settings;
		}

        return array(
			'target'     => '1',
			'noopener'   => '1',
            'noreferrer' => '1',
            'nofollow'   => '1',
        );
    }

	/**
     * Add the submenu content
     * 
     * @since   2.0.0
     */
    private function submenu_content() {
        $this->settings         = $this->get_settings();
		$this->default_settings = $this->get_default_settings();
		$target                 = $this->settings['target'] ?? $this->default_settings['target'];
		$noopener               = $this->settings['noopener'] ?? $this->default_settings['noopener'];
		$noreferrer             = $this->settings['noreferrer'] ?? $this->default_settings['noreferrer'];
		$nofollow               = $this->settings['nofollow'] ?? $this->default_settings['nofollow'];

        ?>
            <div class="wp-mastertoolkit__section">
                <div class="wp-mastertoolkit__section__desc"><?php esc_html_e( 'Ensure that all external links within post content open in a new browser tab by implementing the "target="_blank"" attribute. Additionally, enhance security and SEO advantages by including the "rel="noopener noreferrer nofollow"" attribute.', 'wpmastertoolkit'); ?></div>
                <div class="wp-mastertoolkit__section__body">

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( "Add \"target=_blank\"", 'wpmastertoolkit' ); ?></div>
						<div class="wp-mastertoolkit__section__body__item__content">
							<label class="wp-mastertoolkit__toggle">
								<input type="hidden" name="<?php echo esc_attr( $this->option_id . '[target]' ); ?>" value="0">
								<input type="checkbox" name="<?php echo esc_attr( $this->option_id . '[target]' ); ?>" value="1" <?php checked( $target, '1' ); ?>>
								<span class="wp-mastertoolkit__toggle__slider round"></span>
							</label>
						</div>
                    </div>

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( "Add \"rel=noopener\"", 'wpmastertoolkit' ); ?></div>
						<div class="wp-mastertoolkit__section__body__item__content">
							<label class="wp-mastertoolkit__toggle">
								<input type="hidden" name="<?php echo esc_attr( $this->option_id . '[noopener]' ); ?>" value="0">
								<input type="checkbox" name="<?php echo esc_attr( $this->option_id . '[noopener]' ); ?>" value="1" <?php checked( $noopener, '1' ); ?>>
								<span class="wp-mastertoolkit__toggle__slider round"></span>
							</label>
						</div>
                    </div>

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( "Add \"rel=noreferrer\"", 'wpmastertoolkit' ); ?></div>
						<div class="wp-mastertoolkit__section__body__item__content">
							<label class="wp-mastertoolkit__toggle">
								<input type="hidden" name="<?php echo esc_attr( $this->option_id . '[noreferrer]' ); ?>" value="0">
								<input type="checkbox" name="<?php echo esc_attr( $this->option_id . '[noreferrer]' ); ?>" value="1" <?php checked( $noreferrer, '1' ); ?>>
								<span class="wp-mastertoolkit__toggle__slider round"></span>
							</label>
						</div>
                    </div>

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( "Add \"rel=nofollow\"", 'wpmastertoolkit' ); ?></div>
						<div class="wp-mastertoolkit__section__body__item__content">
							<label class="wp-mastertoolkit__toggle">
								<input type="hidden" name="<?php echo esc_attr( $this->option_id . '[nofollow]' ); ?>" value="0">
								<input type="checkbox" name="<?php echo esc_attr( $this->option_id . '[nofollow]' ); ?>" value="1" <?php checked( $nofollow, '1' ); ?>>
								<span class="wp-mastertoolkit__toggle__slider round"></span>
							</label>
						</div>
                    </div>

                </div>
            </div>
        <?php
    }
}
