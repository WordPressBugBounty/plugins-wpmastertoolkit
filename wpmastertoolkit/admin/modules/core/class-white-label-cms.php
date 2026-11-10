<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module Name: White Label CMS
 * Description: Manage WordPress branding and client administration in one module.
 * @since 2.26.0
 */
class WPMastertoolkit_White_Label_CMS {

	const MODULE_ID = 'WPMastertoolkit_White_Label_CMS';

	private $option_id;
	private $header_title;
	private $nonce_action;
	private $notice;
	private $settings;
	private $default_settings;
	private $elementor_welcome_content;
	/**
	 * Site associated with the cached settings.
	 *
	 * @var int|null
	 */
	private $settings_blog_id;

	/**
	 * Sites whose orphaned access restrictions were recovered on this request.
	 *
	 * @var bool[]
	 */
	private $access_recovered = array();

	/**
	 * Constructor.
	 * 
	 * @since 2.26.0
	 */
	public function __construct() {
		$this->option_id    = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_white_label_cms';
		$this->nonce_action = $this->option_id . '_action';

		add_action( 'init', array( $this, 'class_init' ) );
		add_action( 'admin_init', array( $this, 'recover_access' ), 9 );
		add_action( 'admin_init', array( $this, 'save_submenu' ) );
		add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
		add_action( 'admin_bar_menu', array( $this, 'replace_admin_bar_logo' ), 999 );
		add_action( 'admin_bar_menu', array( $this, 'replace_howdy_text' ), 10000 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_side_menu_branding' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_dashboard_branding' ) );
		add_action( 'adminmenu', array( $this, 'render_side_menu_branding' ) );
		add_filter( 'admin_footer_text', array( $this, 'replace_footer_branding' ) );
		add_filter( 'update_footer', array( $this, 'hide_footer_version' ), PHP_INT_MAX );
		add_filter( 'show_admin_bar', array( $this, 'hide_frontend_admin_bar' ), PHP_INT_MAX );
		add_action( 'admin_head', array( $this, 'hide_help_box' ), PHP_INT_MAX );
		add_filter( 'screen_options_show_screen', array( $this, 'hide_screen_options' ), PHP_INT_MAX );
		add_action( 'added_option', array( $this, 'invalidate_settings' ), 1 );
		add_action( 'updated_option', array( $this, 'invalidate_settings' ), 1 );
		add_action( 'deleted_option', array( $this, 'invalidate_settings' ), 1 );
		add_action( 'wp_dashboard_setup', array( $this, 'setup_welcome_panel' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'prepare_elementor_welcome_panel' ), 20 );
		add_action( 'template_redirect', array( $this, 'prepare_welcome_page_frame' ), 11 );
	}

	/**
     * Initialize the class
	 * 
	 * @since 2.26.0
     */
	public function class_init() {
		$this->header_title = __( 'White Label CMS', 'wpmastertoolkit' );
	}

	/**
	 * Hide the toolbar on the frontend without affecting wp-admin.
	 *
	 * @param bool $show Whether the toolbar would otherwise be shown.
	 * @return bool Toolbar visibility.
	 */
	public function hide_frontend_admin_bar( $show ) {
		if ( is_admin() ) {
			return $show;
		}
		$settings = $this->get_settings()['settings'];
		if ( $settings['hide_frontend_admin_bar'] && array_intersect( $settings['frontend_admin_bar_roles'], (array) wp_get_current_user()->roles ) ) {
			return false;
		}
		return $show;
	}

	/**
	 * Remove Help tabs after the current admin screen has registered them.
	 */
	public function hide_help_box() {
		if ( ! $this->get_settings()['settings']['hide_help_box'] ) {
			return;
		}
		$screen = get_current_screen();
		if ( $screen ) {
			$screen->remove_help_tabs();
		}
	}

	/**
	 * Optionally hide the Screen Options tab.
	 *
	 * @param bool $show Whether Screen Options would otherwise be shown.
	 * @return bool Screen Options visibility.
	 */
	public function hide_screen_options( $show ) {
		return $this->get_settings()['settings']['hide_screen_options'] ? false : $show;
	}

	/**
	 * Apply the toolbar logo, accessible label, and link settings.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Toolbar instance.
	 */
	public function replace_admin_bar_logo( $wp_admin_bar ) {
		$branding = $this->get_effective_settings()['branding'];
		if ( ! $wp_admin_bar->get_node( 'wp-logo' ) ) {
			return;
		}

		$args  = array( 'id' => 'wp-logo' );
		$label = '' !== $branding['admin_bar_alt_text'] ? $branding['admin_bar_alt_text'] : __( 'About WordPress', 'wpmastertoolkit' );

		if ( '' !== $branding['admin_bar_logo'] ) {
			$args['title'] = '<img src="' . esc_url( $branding['admin_bar_logo'] ) . '" alt="' . esc_attr( $label ) . '" style="display:inline-block;vertical-align:middle;max-height:20px;max-width:100px;width:auto;height:auto;" />';
		} elseif ( '' !== $branding['admin_bar_alt_text'] ) {
			$args['title'] = '<span class="ab-icon" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html( $label ) . '</span>';
		}

		if ( '' !== $branding['admin_bar_logo'] || '' !== $branding['admin_bar_alt_text'] ) {
			$args['meta'] = array( 'menu_title' => $label );
		}

		if ( '' !== $branding['admin_bar_url'] ) {
			$args['href'] = $branding['admin_bar_url'];
			if ( $wp_admin_bar->get_node( 'wporg' ) ) {
				$wp_admin_bar->add_node( array( 'id' => 'wporg', 'href' => $branding['admin_bar_url'] ) );
			}
		}

		if ( count( $args ) > 1 ) {
			$wp_admin_bar->add_node( $args );
		}
	}

	/**
	 * Replace the localized account greeting while retaining the name and avatar.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Toolbar instance.
	 */
	public function replace_howdy_text( $wp_admin_bar ) {
		$branding = $this->get_settings()['branding'];
		$account  = $wp_admin_bar->get_node( 'my-account' );
		if ( '' === $branding['howdy_text'] || ! $account ) {
			return;
		}

		$name     = wp_get_current_user()->display_name;
		/* translators: 1: display name of the current user */
		$original = sprintf( __( 'Howdy, %s', 'wpmastertoolkit' ), '<span class="display-name">' . $name . '</span>' );
		$greeting = trim( $branding['howdy_text'] );
		$prefix   = '' !== $greeting ? $greeting . ' ' : '';
		$title    = str_replace( $original, esc_html( $prefix ) . '<span class="display-name">' . esc_html( $name ) . '</span>', $account->title );

		if ( $title !== $account->title ) {
			$wp_admin_bar->add_node(
				array(
					'id'    => 'my-account',
					'title' => $title,
					'meta'  => array( 'menu_title' => $prefix . $name ),
				)
			);
		}
	}

	/**
	 * Style and position branding above the administrator menu items.
	 */
	public function enqueue_side_menu_branding() {
		$branding = $this->get_effective_settings()['branding'];
		if ( '' === $branding['side_menu_image'] ) {
			return;
		}

		wp_add_inline_style(
			'admin-menu',
			'#adminmenu .wpmtk-side-menu-branding{margin:0 0 12px;text-align:center;}#adminmenu .wpmtk-side-menu-branding a,#adminmenu .wpmtk-side-menu-branding span{display:block;padding:0;background:transparent;}#adminmenu .wpmtk-side-menu-branding a:focus-visible{outline:2px solid #72aee6;outline-offset:-2px;}#adminmenu .wpmtk-side-menu-branding img{display:block;margin:0 auto;max-width:160px;width:auto;height:auto;}.folded #adminmenu .wpmtk-side-menu-branding{display:none;}@media(min-width:783px) and (max-width:960px){.auto-fold #adminmenu .wpmtk-side-menu-branding{display:none;}}@media(max-width:782px){.auto-fold #adminmenu .wpmtk-side-menu-branding{display:block;}}'
		);
		wp_add_inline_script( 'common', 'const wpmtkSideMenuBranding = document.getElementById("wpmtk-side-menu-branding"); if (wpmtkSideMenuBranding) { wpmtkSideMenuBranding.parentNode.prepend(wpmtkSideMenuBranding); }' );
	}

	/**
	 * Render the side-menu image.
	 */
	public function render_side_menu_branding() {
		$branding = $this->get_effective_settings()['branding'];
		if ( '' === $branding['side_menu_image'] ) {
			return;
		}

		$alt      = '' !== $branding['side_menu_alt_text'] ? $branding['side_menu_alt_text'] : get_bloginfo( 'name' );
		$url      = $branding['side_menu_url'];
		$external = '' !== $url && strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) !== strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );

		?>
		<li id="wpmtk-side-menu-branding" class="wpmtk-side-menu-branding">
			<?php if ( '' !== $url ) : ?>
				<a href="<?php echo esc_url( $url ); ?>" <?php if ( $external ) : ?>target="_blank" rel="noopener noreferrer"<?php endif; ?>>
			<?php else : ?>
				<span>
			<?php endif; ?>
				<img src="<?php echo esc_url( $branding['side_menu_image'] ); ?>" alt="<?php echo esc_attr( $alt ); ?>">
			<?php if ( '' !== $url ) : ?>
				</a>
			<?php else : ?>
				</span>
			<?php endif; ?>
		</li>
		<?php
	}

	/**
	 * Replace the administrator footer credit with custom branding.
	 *
	 * @param string $text Original footer credit.
	 * @return string Footer branding or the original credit.
	 */
	public function replace_footer_branding( $text ) {
		$branding = $this->get_effective_settings()['branding'];
		if ( '' === $branding['footer_image'] && '' === $branding['footer_text'] ) {
			return $text;
		}

		$content = '';
		if ( '' !== $branding['footer_image'] ) {
			$alt      = '' === $branding['footer_text'] ? get_bloginfo( 'name' ) : '';
			$content .= '<img src="' . esc_url( $branding['footer_image'] ) . '" alt="' . esc_attr( $alt ) . '" style="max-height:50px;max-width:100%;width:auto;height:auto;">';
		}

		if ( '' !== $branding['footer_text'] ) {
			$content .= '<span>' . esc_html( $branding['footer_text'] ) . '</span>';
		}

		$style = 'display:inline-flex;align-items:center;flex-wrap:wrap;gap:8px;max-width:100%;overflow-wrap:anywhere;';
		if ( '' !== $branding['footer_url'] ) {
			return '<a class="wpmtk-footer-branding" href="' . esc_url( $branding['footer_url'] ) . '" style="' . esc_attr( $style ) . '">' . $content . '</a>';
		}

		return '<span class="wpmtk-footer-branding" style="' . esc_attr( $style ) . '">' . $content . '</span>';
	}

	/**
	 * Optionally hide the administrator footer version and update text.
	 *
	 * @param string $text Original version footer content.
	 * @return string Filtered footer content.
	 */
	public function hide_footer_version( $text ) {
		return $this->get_settings()['branding']['hide_wp_version'] ? '' : $text;
	}

	/**
	 * Customize the site Dashboard heading and icon.
	 *
	 * @param string $hook_suffix Current administrator page.
	 */
	public function enqueue_dashboard_branding( $hook_suffix ) {
		$screen = get_current_screen();
		if ( 'index.php' !== $hook_suffix || ! $screen || 'dashboard' !== $screen->id ) {
			return;
		}

		$dashboard = $this->get_effective_settings()['dashboard'];
		if ( '' !== $dashboard['title'] ) {
			$GLOBALS['title'] = $dashboard['title'];
		}

		if ( '' === $dashboard['icon'] ) {
			return;
		}

		wp_add_inline_style( 'dashboard', '#wpbody-content .wrap > h1 .wpmtk-dashboard-icon{display:inline-block;height:40px;width:auto;max-width:100%;object-fit:contain;vertical-align:middle;margin-inline-end:12px;}' );
		$icon = wp_json_encode( $dashboard['icon'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
		wp_add_inline_script( 'common', '(function(){const heading=document.querySelector("#wpbody-content .wrap > h1");if(!heading || heading.querySelector(".wpmtk-dashboard-icon")){return;}const icon=document.createElement("img");icon.className="wpmtk-dashboard-icon";icon.src=' . $icon . ';icon.alt="";heading.prepend(icon);})();' );
	}

	/**
	 * Check the welcome-panel toggle, template, screen, and selected roles.
	 *
	 * @param bool $check_screen Whether to require the Dashboard screen.
	 * @return bool Whether the current user can see the panel.
	 */
	private function can_view_welcome_panel( $check_screen = true ) {
		if ( $check_screen ) {
			$screen = get_current_screen();
			if ( ! $screen || 'dashboard' !== $screen->id ) {
				return false;
			}
		}

		$welcome = $this->get_effective_settings()['dashboard']['welcome'];
		if ( ! $welcome['enabled'] || ! array_intersect( $welcome['roles'], (array) wp_get_current_user()->roles ) ) {
			return false;
		}

		if ( 'elementor' === $welcome['template'] ) {
			return class_exists( '\Elementor\Plugin' ) && (bool) self::get_elementor_welcome_template( $welcome['elementor_template_id'] );
		}

		if ( 'page' === $welcome['template'] ) {
			return (bool) self::get_welcome_page( $welcome['page_id'] );
		}

		return 'basic_html' === $welcome['template'];
	}

	/**
	 * Get a published page that can be embedded without a password prompt.
	 *
	 * @param int $page_id Page post ID.
	 * @return WP_Post|null Valid page or null.
	 */
	private static function get_welcome_page( $page_id ) {
		if ( ! self::is_positive_id( $page_id ) ) {
			return null;
		}

		$page = get_post( $page_id );
		if ( ! $page || 'page' !== $page->post_type || 'publish' !== $page->post_status || '' !== $page->post_password ) {
			return null;
		}

		return $page;
	}

	/**
	 * Configure the normal frontend page only for an authorized embedded request.
	 */
	public function prepare_welcome_page_frame() {
		if ( ! isset( $_GET['wpmtk_welcome_page'] ) || ! is_string( $_GET['wpmtk_welcome_page'] ) || '1' !== $_GET['wpmtk_welcome_page'] ) {
			return;
		}

		nocache_headers();
		$nonce   = isset( $_GET['_wpnonce'] ) && is_string( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		$welcome = $this->get_effective_settings()['dashboard']['welcome'];

		if ( ! wpmastertoolkit_is_pro() || ! is_user_logged_in() || ! wp_verify_nonce( $nonce, 'wpmtk_welcome_page' )
			|| 'page' !== $welcome['template'] || ! $this->can_view_welcome_panel( false )
			|| ! is_page( $welcome['page_id'] ) || get_queried_object_id() !== $welcome['page_id'] ) {
			wp_die( esc_html__( 'You are not allowed to view this welcome panel.', 'wpmastertoolkit' ), '', array( 'response' => 403 ) );
		}

		send_frame_options_header();
		show_admin_bar( false );
		add_filter( 'show_admin_bar', '__return_false', PHP_INT_MAX );
		add_filter( 'wp_robots', 'wp_robots_no_robots' );
		add_action( 'wp_head', array( $this, 'welcome_page_frame_styles' ), PHP_INT_MAX );
	}

	/**
	 * Hide site chrome while retaining the theme document, content, and assets.
	 */
	public function welcome_page_frame_styles() {
		$selectors = apply_filters(
			'wpmtk_white_label_cms_page_chrome_selectors',
			array( 'header', 'footer', '[role="banner"]', '[role="contentinfo"]', '#masthead', '#colophon', '.site-header', '.site-footer', '.elementor-location-header', '.elementor-location-footer', '.wp-block-template-part > header', '.wp-block-template-part > footer' )
		);
		$selectors = is_array( $selectors ) ? array_values( array_filter( $selectors, 'is_string' ) ) : array();
		$encoded   = wp_json_encode( $selectors, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );

		?>
		<style id="wpmtk-welcome-page-styles">html,body{min-height:0!important;height:auto!important;}html{margin-top:0!important;}#wpadminbar,[data-wpmtk-page-chrome]{display:none!important;}</style>
		<?php

		$script = '(function(){const selectors=' . $encoded . ';' . <<<'JS'
    const hideChrome=function(){
        selectors.forEach(function(selector){
            try{
                document.querySelectorAll(selector).forEach(function(element){
                    if(!element.closest('main,article,[role="main"],.entry-content,.wp-block-post-content,.elementor[data-elementor-type="wp-page"]')){
                        element.setAttribute('data-wpmtk-page-chrome','');
                    }
                });
            }catch(error){return;}
        });
    };
    const start=function(){
        hideChrome();
        let scheduled=false;
        new MutationObserver(function(){
            if(scheduled){return;}
            scheduled=true;
            requestAnimationFrame(function(){scheduled=false;hideChrome();});
        }).observe(document.body,{childList:true,subtree:true});
    };
    if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',start,{once:true});}else{start();}
})();
JS;
		wp_print_inline_script_tag( $script, array( 'id' => 'wpmtk-welcome-page-chrome' ) );
	}

	/**
	 * Get a published saved Elementor template, excluding theme headers and footers.
	 *
	 * @param int $template_id Template post ID.
	 * @return WP_Post|null Valid template or null.
	 */
	private static function get_elementor_welcome_template( $template_id ) {
		if ( ! self::is_positive_id( $template_id ) ) {
			return null;
		}

		$template = get_post( $template_id );
		if ( ! $template || 'elementor_library' !== $template->post_type || 'publish' !== $template->post_status
			|| in_array( get_post_meta( $template_id, '_elementor_template_type', true ), array( 'header', 'footer' ), true ) ) {
			return null;
		}

		return $template;
	}

	/**
	 * Prepare the template and its dependencies before admin styles are printed.
	 *
	 * @param string $hook_suffix Current administrator page.
	 */
	public function prepare_elementor_welcome_panel( $hook_suffix ) {
		if ( 'index.php' !== $hook_suffix || ! $this->can_view_welcome_panel() ) {
			return;
		}

		$welcome = $this->get_effective_settings()['dashboard']['welcome'];
		if ( 'elementor' !== $welcome['template'] || null !== $this->elementor_welcome_content ) {
			return;
		}

		$frontend = \Elementor\Plugin::instance()->frontend;
		$frontend->register_styles();
		$frontend->register_scripts();
		$this->elementor_welcome_content = $frontend->get_builder_content_for_display( $welcome['elementor_template_id'], true );
		$frontend->enqueue_styles();
		$frontend->enqueue_scripts();
		$frontend->print_fonts_links();
	}

	/**
	 * Register a role-visible welcome panel outside the dashboard widget columns.
	 */
	public function setup_welcome_panel() {
		if ( ! $this->can_view_welcome_panel() ) {
			return;
		}

		remove_action( 'welcome_panel', 'wp_welcome_panel' );
		add_action( 'admin_notices', array( $this, 'render_welcome_panel' ) );
		$padding = 'basic_html' === $this->get_effective_settings()['dashboard']['welcome']['template'] ? '24px' : '0';
		wp_add_inline_style( 'dashboard', '#wpmtk-welcome-panel{box-sizing:border-box;width:100%;margin:20px 0;padding:' . $padding . ';background:#fff;border:1px solid #c3c4c7;overflow-wrap:anywhere;}#wpmtk-welcome-panel h2{margin-top:0;}#wpmtk-welcome-panel img{max-width:100%;height:auto;}#wpmtk-welcome-panel pre{overflow:auto;}' );
		wp_add_inline_script( 'common', '(function(){const panel=document.getElementById("wpmtk-welcome-panel");const heading=document.querySelector("#wpbody-content .wrap > h1");if(panel && heading){heading.after(panel);}})();' );
		if ( 'page' === $this->get_effective_settings()['dashboard']['welcome']['template'] ) {
			$script = <<<'JS'
(function(){
	const frame=document.getElementById('wpmtk-welcome-page');
	if(!frame){return;}
	let cleanup=function(){};
	const connect=function(){
		cleanup();
		try{
			const doc=frame.contentDocument;
			if(!doc || !doc.body){return;}
			let pending=0;
			const resize=function(){
				pending=0;
				const height=Math.max(1,Math.ceil(Math.max(doc.body.scrollHeight,doc.body.offsetHeight,doc.body.getBoundingClientRect().height)));
				if(frame.style.height!==height+'px'){frame.style.height=height+'px';}
			};
			const schedule=function(){if(!pending){pending=requestAnimationFrame(resize);}};
			const click=function(event){
				const link=event.target.closest && event.target.closest('a[href]');
				if(!link || link.getAttribute('href').startsWith('#')){return;}
				if(!link.target || link.target==='_self' || link.target==='_parent'){link.target='_top';}
				if(link.target==='_blank'){link.relList.add('noopener','noreferrer');}
			};
			const observer=typeof ResizeObserver==='function'?new ResizeObserver(schedule):null;
			if(observer){observer.observe(doc.body);}
			const mutations=new MutationObserver(schedule);
			mutations.observe(doc.body,{childList:true,subtree:true,characterData:true,attributes:true});
			doc.addEventListener('load',schedule,true);
			doc.addEventListener('click',click);
			window.addEventListener('resize',schedule);
			if(doc.fonts){doc.fonts.ready.then(schedule);}
			cleanup=function(){
				if(observer){observer.disconnect();}
				mutations.disconnect();
				doc.removeEventListener('load',schedule,true);
				doc.removeEventListener('click',click);
				window.removeEventListener('resize',schedule);
				if(pending){cancelAnimationFrame(pending);}
			};
			schedule();
		}catch(error){frame.style.height='600px';}
	};
	frame.addEventListener('load',connect);
	try{if(frame.contentDocument && frame.contentDocument.readyState==='complete'){connect();}}catch(error){frame.style.height='600px';}
})();
JS;
			wp_add_inline_script( 'common', $script );
		}
	}

	/**
	 * Render the custom welcome panel using Basic HTML, Elementor, or a page.
	 */
	public function render_welcome_panel() {
		if ( ! $this->can_view_welcome_panel() ) {
			return;
		}

		$welcome   = $this->get_effective_settings()['dashboard']['welcome'];
		$elementor = 'elementor' === $welcome['template'];
		$title     = 'basic_html' === $welcome['template'] ? $welcome['title'] : '';

		?>
		<section id="wpmtk-welcome-panel" <?php if ( '' !== $title ) : ?>aria-labelledby="wpmtk-welcome-panel-title"<?php else : ?>aria-label="<?php esc_attr_e( 'Welcome panel', 'wpmastertoolkit' ); ?>"<?php endif; ?>>
			<?php if ( '' !== $title ) : ?>
				<h2 id="wpmtk-welcome-panel-title"><?php echo esc_html( $title ); ?></h2>
			<?php endif; ?>

			<?php if ( $elementor ) : ?>
				<div class="wpmtk-welcome-panel__content elementor-default">
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted Elementor renderer output includes required widget markup and styles.
					echo $this->elementor_welcome_content;
					?>
				</div>
			<?php elseif ( 'page' === $welcome['template'] ) : ?>
				<iframe id="wpmtk-welcome-page" src="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wpmtk_welcome_page', '1', get_permalink( $welcome['page_id'] ) ), 'wpmtk_welcome_page' ) ); ?>" title="<?php echo esc_attr( get_the_title( $welcome['page_id'] ) ); ?>" style="display:block;width:100%;height:150px;border:0;"></iframe>
			<?php else : ?>
				<div class="wpmtk-welcome-panel__content"><?php echo wp_kses_post( $welcome['html'] ); ?></div>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * Validate a list of registered role slugs for the welcome panel.
	 *
	 * @param mixed $roles Submitted roles.
	 * @return array|null Unique roles or null for invalid input.
	 */
	private static function sanitize_welcome_roles( $roles ) {
		if ( ! is_array( $roles ) ) {
			return null;
		}

		$registered = wp_roles()->roles;
		$validated  = array();
		foreach ( $roles as $role ) {
			if ( '' === $role ) {
				continue;
			}
			if ( ! is_string( $role ) || ! isset( $registered[ $role ] ) ) {
				return null;
			}
			$validated[] = $role;
		}

		return array_values( array_unique( $validated ) );
	}

	/**
	 * Recover an orphaned policy only during an eligible, verified module visit.
	 */
	public function recover_access() {
		if ( ! wpmastertoolkit_is_pro() ) {
			return;
		}

		$page = isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( isset( $_POST['wpmtk_white_label_cms_operation'] ) || isset( $_POST[ $this->option_id ] ) ) {

			$operation = isset( $_POST['wpmtk_white_label_cms_operation'] ) && is_string( $_POST['wpmtk_white_label_cms_operation'] ) ? sanitize_key( wp_unslash( $_POST['wpmtk_white_label_cms_operation'] ) ) : '';
			$nonce     = isset( $_POST['_wpnonce'] ) && is_string( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

			if ( 'save' !== $operation || ! wp_verify_nonce( $nonce, $this->nonce_action ) ) {
				return;
			}

		} elseif ( 'wp-mastertoolkit-settings-white-label-cms' !== $page ) {
			return;
		}

		$this->invalidate_settings( $this->option_id );
		if ( ! $this->can_manage_base() ) {
			return;
		}

		$settings = $this->get_settings();
		if ( 'only_me' !== $settings['access_mode'] || self::eligible_user( $settings['access_user_id'] ) ) {
			return;
		}

		$saved = get_option( $this->option_id, array() );
		if ( ! is_array( $saved ) || 'only_me' !== ( $saved['access_mode'] ?? '' ) ) {
			return;
		}

		$owner_id = isset( $saved['access_user_id'] ) && self::is_positive_id( $saved['access_user_id'] ) ? (int) $saved['access_user_id'] : 0;
		if ( $owner_id !== $settings['access_user_id'] || self::eligible_user( $owner_id ) ) {
			return;
		}

		$saved['access_mode']    = 'all_administrators';
		$saved['access_user_id'] = 0;
		if ( update_option( $this->option_id, $saved, false ) ) {
			$this->invalidate_settings( $this->option_id );
			$this->access_recovered[ get_current_blog_id() ] = true;
		}
	}

	/**
	 * Save the shared submenu form.
	 *
	 * @since 2.26.0
	 */
	public function save_submenu() {
		if ( ! $this->current_user_is_allowed() ) {
			return;
		}

		$operation = isset( $_POST['wpmtk_white_label_cms_operation'] ) && is_string( $_POST['wpmtk_white_label_cms_operation'] ) ? sanitize_key( wp_unslash( $_POST['wpmtk_white_label_cms_operation'] ) ) : '';
		if ( ! $operation ) {
			return;
		}

		$nonce = isset( $_POST['_wpnonce'] ) && is_string( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( 'save' !== $operation || ! wp_verify_nonce( $nonce, $this->nonce_action ) ) {
			$this->notice = new WP_Error( 'wpmtk_white_label_cms_nonce', __( 'The request could not be verified.', 'wpmastertoolkit' ) );
			return;
		}

		$option_id = $this->option_id;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Recursively validated by sanitize_settings() before persistence.
		$settings  = isset( $_POST[ $option_id ] ) && is_array( $_POST[ $option_id ] ) ? wp_unslash( $_POST[ $option_id ] ) : null;
		$validated = $this->sanitize_settings( $settings );
		if ( is_wp_error( $validated ) ) {
			$this->notice = $validated;
			return;
		}

		try {
			$this->notice = $this->save_settings( $settings );
		} catch ( Throwable $exception ) {
			$this->notice = new WP_Error( 'wpmtk_white_label_cms_save_failed', $exception->getMessage() );
			return;
		}

		if ( ! is_wp_error( $this->notice ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=wp-mastertoolkit-settings-white-label-cms' ) );
			exit;
		}
	}

	/**
	 * Register the White Label CMS admin page.
	 * 
	 * @since 2.26.0
	 */
	public function add_submenu() {
		$this->recover_access();
		if ( ! $this->current_user_is_allowed() ) {
			return;
		}

		WPMastertoolkit_Settings::add_submenu_page(
			'wp-mastertoolkit-settings',
			$this->header_title,
			$this->header_title,
			'manage_options',
			'wp-mastertoolkit-settings-white-label-cms',
			array( $this, 'render_submenu' )
		);
	}

	/**
	 * Render a bounded recent log and administrator-only settings.
	 * 
	 * @since 2.26.0
	 */
	public function render_submenu() {
		$this->recover_access();
		if ( ! $this->current_user_is_allowed() ) {
			wp_die( esc_html__( 'You are not allowed to manage White Label CMS.', 'wpmastertoolkit' ), '', array( 'response' => 403 ) );
		}
		
		wp_enqueue_media();
		$assets = include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/white-label-cms.asset.php';
		wp_enqueue_style( 'wpmtk-white-label-cms-settings', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/white-label-cms.css', array(), $assets['version'] );
		wp_enqueue_script( 'wpmtk-white-label-cms-settings', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/white-label-cms.js', array_merge( $assets['dependencies'], array( 'media-views' ) ), $assets['version'], true );

		echo '<div class="wpmtk-white-label-cms">';
		include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
		$this->submenu_content();
		include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/footer.php';
		echo '</div>';
	}

	/**
	 * Invalidate cached permissions when the module option changes.
	 *
	 * @param string $option Changed option name.
	 */
	public function invalidate_settings( $option ) {
		if ( $this->option_id === $option ) {
			$this->settings                  = null;
			$this->settings_blog_id          = null;
			$this->elementor_welcome_content = null;
		}
	}

	/**
	 * Save White Label CMS settings.
	 *
	 * @param array $settings Settings to save.
	 * @return array Saved settings.
	 * @throws RuntimeException If the current user is not allowed or the settings could not be saved.
	 * @throws InvalidArgumentException If the settings are invalid.
	 */
	public function save_settings( $settings ) {
		if ( ! $this->current_user_is_allowed() ) {
			throw new RuntimeException( esc_html__( 'You are not allowed to manage White Label CMS.', 'wpmastertoolkit' ) );
		}

		$validated = $this->sanitize_settings( $settings );
		if ( is_wp_error( $validated ) ) {
			throw new InvalidArgumentException( esc_html( $validated->get_error_message() ) );
		}

		if ( get_option( $this->option_id, null ) !== $validated && ! update_option( $this->option_id, $validated, false ) ) {
			throw new RuntimeException( esc_html__( 'White Label CMS settings could not be saved.', 'wpmastertoolkit' ) );
		}

		$this->settings = $validated;

		return $validated;
	}

	/**
	 * Sanitize settings before saving.
	 *
	 * @param array $settings Settings to sanitize.
	 * @return array Sanitized settings or WP_Error on failure.
	 */
	public function sanitize_settings( $settings ) {
		if ( ! is_array( $settings ) ) {
			return self::invalid_settings( '' );
		}

		$saved = get_option( $this->option_id, array() );
		if ( ! is_array( $saved ) ) {
			return self::invalid_settings( '' );
		}

		$current   = $this->get_settings();
		$validated = $this->validate_settings( $settings, $current, '' );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		if ( wpmastertoolkit_is_pro() && array_key_exists( 'access_mode', $settings ) ) {
			$validated['access_user_id'] = 'only_me' === $validated['access_mode']
				? ( 'only_me' === $current['access_mode'] ? $current['access_user_id'] : get_current_user_id() )
				: 0;
			if ( 'only_me' === $validated['access_mode'] && ! self::eligible_user( $validated['access_user_id'] ) ) {
				return self::invalid_settings( 'access_mode' );
			}
		}

		return $validated;
	}

	/**
	 * Get saved settings with defaults for missing keys.
	 *
	 * @return array Settings.
	 */
	public function get_settings() {
		$blog_id = get_current_blog_id();
		if ( null !== $this->settings && $blog_id === $this->settings_blog_id ) {
			return $this->settings;
		}

		if ( $blog_id !== $this->settings_blog_id ) {
			$this->elementor_welcome_content = null;
		}

		$saved                  = get_option( $this->option_id, array() );
		$this->settings         = is_array( $saved ) ? $this->recover_settings( $saved, $this->get_default_settings() ) : $this->get_default_settings();
		$this->settings_blog_id = $blog_id;

		return $this->settings;
	}

	/**
	 * Derive edition-specific behavior without changing saved configuration.
	 *
	 * @return array Effective settings.
	 */
	private function get_effective_settings() {
		$settings = $this->get_settings();
		if ( wpmastertoolkit_is_pro() ) {
			return $settings;
		}

		foreach ( array( 'admin_bar_url', 'side_menu_image', 'side_menu_url', 'side_menu_alt_text', 'footer_image', 'footer_url' ) as $key ) {
			$settings['branding'][ $key ] = '';
		}
		$settings['dashboard']['icon']  = '';
		$settings['dashboard']['title'] = '';

		$welcome = $settings['dashboard']['welcome'];
		if ( in_array( $welcome['template'], array( 'page', 'elementor' ), true ) ) {
			$settings['dashboard']['welcome']['template'] = 'basic_html';
			$settings['dashboard']['welcome']['enabled']  = $welcome['enabled'] && self::has_welcome_content( $welcome );
		}

		return $settings;
	}

	/**
	 * Check whether Basic HTML provides a meaningful fallback.
	 *
	 * @param array $welcome Recovered welcome settings.
	 * @return bool Whether a title, text, or media source exists.
	 */
	private static function has_welcome_content( $welcome ) {
		$text = html_entity_decode( $welcome['title'] . ' ' . wp_strip_all_tags( $welcome['html'] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		if ( '' !== trim( str_replace( "\xc2\xa0", ' ', $text ) ) ) {
			return true;
		}

		$processor = new WP_HTML_Tag_Processor( $welcome['html'] );
		while ( $processor->next_tag() ) {
			if ( in_array( $processor->get_tag(), array( 'IMG', 'AUDIO', 'VIDEO', 'SOURCE' ), true ) ) {
				$source = $processor->get_attribute( 'src' );
				if ( is_string( $source ) && '' !== esc_url( $source ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Get default values for White Label CMS settings.
	 *
	 * @return array Defaults.
	 */
	private function get_default_settings() {
		if ( null === $this->default_settings ) {
			$this->default_settings = array(
				'access_mode'    => 'all_administrators',
				'access_user_id' => 0,
				'branding'       => array(
					'admin_bar_logo'     => '',
					'admin_bar_alt_text' => '',
					'howdy_text'         => '',
					'admin_bar_url'      => '',
					'side_menu_image'    => '',
					'side_menu_url'      => '',
					'side_menu_alt_text' => '',
					'footer_image'       => '',
					'footer_text'        => '',
					'footer_url'         => '',
					'hide_wp_version'    => false,
				),
				'dashboard'      => array(
					'icon'    => '',
					'title'   => '',
					'welcome' => array(
						'enabled'               => false,
						'roles'                 => array_keys( wp_roles()->roles ),
						'template'              => 'basic_html',
						'title'                 => '',
						'html'                  => '',
						'elementor_template_id' => 0,
						'page_id'               => 0,
					),
				),
				'settings'       => array(
					'hide_frontend_admin_bar'  => false,
					'frontend_admin_bar_roles' => array_keys( wp_roles()->roles ),
					'hide_help_box'            => false,
					'hide_screen_options'      => false,
				),
			);
		}

		return $this->default_settings;
	}

	/**
	 * Recover settings by merging saved values with defaults.
	 *
	 * @param array $saved Saved settings.
	 * @param array $defaults Default settings.
	 * @return array Recovered settings.
	 */
	private function recover_settings( $saved, $defaults ) {
		foreach ( $defaults as $key => $default ) {
			if ( ! array_key_exists( $key, $saved ) ) {
				continue;
			}

			$value = $saved[ $key ];

			if ( 'access_mode' === $key ) {
				$defaults[ $key ] = is_string( $value ) && in_array( $value, array( 'all_administrators', 'only_me' ), true ) ? $value : 'invalid';
			} elseif ( 'access_user_id' === $key ) {
				$defaults[ $key ] = self::is_positive_id( $value ) ? (int) $value : 0;
			} elseif ( in_array( $key, array( 'roles', 'frontend_admin_bar_roles' ), true ) ) {
				$defaults[ $key ] = self::sanitize_welcome_roles( $value ) ?? array();
			} elseif ( 'template' === $key ) {
				$defaults[ $key ] = in_array( $value, array( 'basic_html', 'elementor', 'page' ), true ) ? $value : 'invalid';
			} elseif ( in_array( $key, array( 'elementor_template_id', 'page_id' ), true ) ) {
				$defaults[ $key ] = self::is_positive_id( $value ) ? (int) $value : 0;
			} elseif ( 'html' === $key && is_string( $value ) ) {
				$defaults[ $key ] = wp_kses_post( $value );
			} elseif ( in_array( $key, array( 'admin_bar_logo', 'admin_bar_url', 'side_menu_image', 'side_menu_url', 'footer_image', 'footer_url', 'icon' ), true ) ) {
				$defaults[ $key ] = self::sanitize_logo_url( $value ) ?? '';
			} elseif ( in_array( $key, array( 'admin_bar_alt_text', 'howdy_text', 'side_menu_alt_text', 'footer_text', 'title' ), true ) && is_string( $value ) ) {
				$defaults[ $key ] = 'howdy_text' === $key && '' !== $value && '' === trim( $value ) ? ' ' : sanitize_text_field( $value );
			} elseif ( is_array( $default ) && is_array( $value ) ) {
				$defaults[ $key ] = $this->recover_settings( $value, $default );
			} elseif ( is_bool( $default ) && in_array( $value, array( true, false, 1, 0, '1', '0' ), true ) ) {
				$defaults[ $key ] = in_array( $value, array( true, 1, '1' ), true );
			}
		}

		return $defaults;
	}

	/**
	 * Return a WP_Error indicating invalid settings.
	 *
	 * @param string $path Path to the invalid field.
	 * @return WP_Error Error object.
	 */
	private static function invalid_settings( $path ) {
		return new WP_Error( 'wpmtk_white_label_cms_invalid_settings', __( 'Invalid or unsupported White Label CMS settings.', 'wpmastertoolkit' ), array( 'field' => $path ) );
	}

	/**
	 * Accept an empty value or an absolute HTTP(S) branding URL.
	 *
	 * @param mixed $value Submitted logo URL.
	 * @return string|null Sanitized URL, or null when invalid.
	 */
	private static function sanitize_logo_url( $value ) {
		if ( ! is_string( $value ) ) {
			return null;
		}

		if ( '' === trim( $value ) ) {
			return '';
		}

		$value = trim( $value );
		$parts = wp_parse_url( $value );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || empty( $parts['scheme'] ) || ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
			return null;
		}

		$url = esc_url_raw( $value, array( 'http', 'https' ) );

		return '' !== $url ? $url : null;
	}

	/**
	 * Validate settings against the base structure.
	 *
	 * @param array  $input Input settings to validate.
	 * @param array  $base Base settings structure.
	 * @param string $path Path to the current field.
	 * @return array|WP_Error Validated settings or WP_Error on failure.
	 */
	private function validate_settings( $input, $base, $path ) {
		if ( ! is_array( $input ) || array_diff_key( $input, $base ) ) {
			return self::invalid_settings( $path );
		}

		foreach ( $input as $key => $value ) {

			$field = '' === $path ? $key : $path . '.' . $key;

			if ( 'access_user_id' === $field ) {
				return self::invalid_settings( $field );
			} elseif ( ! wpmastertoolkit_is_pro() && in_array( $field, array( 'branding.admin_bar_url', 'branding.side_menu_image', 'branding.side_menu_url', 'branding.side_menu_alt_text', 'branding.footer_image', 'branding.footer_url', 'dashboard.icon', 'dashboard.title', 'dashboard.welcome.template', 'dashboard.welcome.elementor_template_id', 'dashboard.welcome.page_id' ), true ) ) {
				continue;
			} elseif ( 'access_mode' === $field ) {
				if ( ! is_string( $value ) || ! in_array( $value, array( 'all_administrators', 'only_me' ), true ) ) {
					return self::invalid_settings( $field );
				}
				if ( wpmastertoolkit_is_pro() ) {
					$base[ $key ] = $value;
				}
			} elseif ( in_array( $field, array( 'dashboard.welcome.roles', 'settings.frontend_admin_bar_roles' ), true ) ) {
				$base[ $key ] = self::sanitize_welcome_roles( $value );
				if ( null === $base[ $key ] ) {
					return self::invalid_settings( $field );
				}
			} elseif ( 'dashboard.welcome.template' === $field ) {
				if ( ! in_array( $value, array( 'basic_html', 'elementor', 'page' ), true ) ) {
					return self::invalid_settings( $field );
				}
				$base[ $key ] = $value;
			} elseif ( 'dashboard.welcome.elementor_template_id' === $field ) {
				if ( in_array( $value, array( 0, '0', '' ), true ) ) {
					$base[ $key ] = 0;
				} elseif ( self::is_positive_id( $value ) && self::get_elementor_welcome_template( (int) $value ) ) {
					$base[ $key ] = (int) $value;
				} else {
					return self::invalid_settings( $field );
				}
			} elseif ( 'dashboard.welcome.page_id' === $field ) {
				if ( in_array( $value, array( 0, '0', '' ), true ) ) {
					$base[ $key ] = 0;
				} elseif ( self::is_positive_id( $value ) && self::get_welcome_page( (int) $value ) ) {
					$base[ $key ] = (int) $value;
				} else {
					return self::invalid_settings( $field );
				}
			} elseif ( 'dashboard.welcome.html' === $field ) {
				if ( ! is_string( $value ) ) {
					return self::invalid_settings( $field );
				}
				$base[ $key ] = wp_kses_post( $value );
			} elseif ( in_array( $field, array( 'branding.admin_bar_logo', 'branding.admin_bar_url', 'branding.side_menu_image', 'branding.side_menu_url', 'branding.footer_image', 'branding.footer_url', 'dashboard.icon' ), true ) ) {
				$base[ $key ] = self::sanitize_logo_url( $value );
				if ( null === $base[ $key ] ) {
					return self::invalid_settings( $field );
				}
			} elseif ( in_array( $field, array( 'branding.admin_bar_alt_text', 'branding.howdy_text', 'branding.side_menu_alt_text', 'branding.footer_text', 'dashboard.title', 'dashboard.welcome.title' ), true ) ) {
				if ( ! is_string( $value ) ) {
					return self::invalid_settings( $field );
				}
				$base[ $key ] = 'branding.howdy_text' === $field && '' !== $value && '' === trim( $value ) ? ' ' : sanitize_text_field( $value );
			} elseif ( is_array( $base[ $key ] ) ) {
				$base[ $key ] = $this->validate_settings( $value, $base[ $key ], $field );
				if ( is_wp_error( $base[ $key ] ) ) {
					return $base[ $key ];
				}
			} elseif ( is_bool( $base[ $key ] ) ) {
				if ( ! in_array( $value, array( true, false, 1, 0, '1', '0' ), true ) ) {
					return self::invalid_settings( $field );
				}
				$base[ $key ] = in_array( $value, array( true, 1, '1' ), true );
			} else {
				return self::invalid_settings( $field );
			}
		}

		return $base;
	}

	/**
	 * Checks if a user is eligible to access the White Label CMS.
	 *
	 * @param int $user_id The ID of the user to check.
	 * @return bool True if the user is eligible, false otherwise.
	 */
	private function eligible_user( $user_id ) {
		$user = get_userdata( $user_id );
		return $user && user_can( $user, 'manage_options' ) && ( is_user_member_of_blog( $user_id, get_current_blog_id() ) || ( is_multisite() && is_super_admin( $user_id ) ) );
	}

	/**
	 * Renders the content for the submenu section.
	 * 
	 * @since 2.26.0
	 */
	private function submenu_content() {
		$this->settings = $this->get_settings();
		$pro            = wpmastertoolkit_is_pro();
		$error_data     = is_wp_error( $this->notice ) ? $this->notice->get_error_data() : array();
		$error_field    = is_array( $error_data ) ? ( $error_data['field'] ?? '' ) : '';
		$template_name  = $pro ? $this->option_id . '[dashboard][welcome][template]' : 'wpmtk_white_label_cms_template_preview';
		
		?>
		<input type="hidden" name="wpmtk_white_label_cms_operation" value="save">

		<?php if ( is_wp_error( $this->notice ) ) : ?>
			<div class="notice notice-error system-notice" role="alert" tabindex="-1" data-error-field="<?php echo esc_attr( $error_field ); ?>">
				<p><?php echo esc_html( $this->notice->get_error_message() ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $this->access_recovered[ get_current_blog_id() ] ) ) : ?>
			<div class="notice notice-warning system-notice" role="status">
				<p><?php esc_html_e( 'White Label CMS access was restored to all administrators because the previous owner no longer has permission to manage this site.', 'wpmastertoolkit' ); ?></p>
			</div>
		<?php endif; ?>

		<section class="wp-mastertoolkit__section" aria-labelledby="wpmtk-cms-access">
			<div class="wp-mastertoolkit__section__desc">
				<h2 id="wpmtk-cms-access"><?php esc_html_e( 'Who can access the White Label CMS?', 'wpmastertoolkit' ); ?></h2>
			</div>
			<div class="wp-mastertoolkit__section__body">
				<fieldset class="wp-mastertoolkit__section__body__item" aria-describedby="wpmtk-cms-access-description">
					<legend class="wp-mastertoolkit__section__body__item__title">
						<?php esc_html_e( 'Restrict module access', 'wpmastertoolkit' ); ?>
						<?php if ( ! $pro ) : ?>
							<span class="wp-mastertoolkit__section__body__item__title__tag pro"><?php esc_html_e( 'PRO', 'wpmastertoolkit' ); ?></span>
						<?php endif; ?>
					</legend>
					<div class="wp-mastertoolkit__section__body__item__content wpmtk-white-label-cms__access-options">
						<label for="wpmtk-cms-access-only-me">
							<input id="wpmtk-cms-access-only-me" type="radio" name="<?php echo esc_attr( $this->option_id ); ?>[access_mode]" value="only_me" data-field="access_mode" <?php checked( $pro && 'only_me' === $this->settings['access_mode'], true ); ?> <?php disabled( ! $pro ); ?>>
							<span><?php esc_html_e( 'Only me', 'wpmastertoolkit' ); ?></span>
						</label>
						<label for="wpmtk-cms-access-all">
							<input id="wpmtk-cms-access-all" type="radio" name="<?php echo esc_attr( $this->option_id ); ?>[access_mode]" value="all_administrators" <?php checked( ! $pro || 'all_administrators' === $this->settings['access_mode'], true ); ?> <?php disabled( ! $pro ); ?>>
							<span><?php esc_html_e( 'All administrators', 'wpmastertoolkit' ); ?></span>
						</label>
					</div>
					<p id="wpmtk-cms-access-description" class="wp-mastertoolkit__section__body__item__content__desc"><?php esc_html_e( 'Only me limits this module to the administrator who saves this setting. Existing Toolkit access restrictions still apply. If that administrator loses site administrator access, an eligible administrator visiting this module can restore access.', 'wpmastertoolkit' ); ?></p>
				</fieldset>
			</div>
		</section>
		
		<section class="wp-mastertoolkit__section" aria-labelledby="wpmtk-cms-heading-branding">
			<div class="wp-mastertoolkit__section__desc">
				<h2 id="wpmtk-cms-heading-branding"><?php esc_html_e( 'Branding', 'wpmastertoolkit' ); ?></h2>
			</div>

			<div class="wp-mastertoolkit__section__body">
				<div class="wp-mastertoolkit__section__body__item">
					<div id="wpmtk-cms-admin-bar-logo-label" class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Admin Bar Logo', 'wpmastertoolkit' ); ?></div>
					<div class="wp-mastertoolkit__section__body__item__content wpmtk-white-label-cms__logo">
						<div class="wpmtk-white-label-cms__logo-preview" <?php echo '' === $this->settings['branding']['admin_bar_logo'] ? 'hidden' : ''; ?>>
							<img src="<?php echo '' !== $this->settings['branding']['admin_bar_logo'] ? esc_url( $this->settings['branding']['admin_bar_logo'] ) : 'data:,'; ?>" alt="<?php esc_attr_e( 'Admin bar logo preview', 'wpmastertoolkit' ); ?>">
							<button type="button" class="wpmtk-white-label-cms__logo-remove" aria-label="<?php esc_attr_e( 'Remove admin bar logo', 'wpmastertoolkit' ); ?>" title="<?php esc_attr_e( 'Remove admin bar logo', 'wpmastertoolkit' ); ?>"><span class="dashicons dashicons-dismiss" aria-hidden="true"></span></button>
						</div>
						<div class="wpmtk-white-label-cms__logo-controls">
							<input id="wpmtk-cms-admin-bar-logo" type="hidden" name="<?php echo esc_attr( $this->option_id . '[branding][admin_bar_logo]' ); ?>" value="<?php echo esc_attr( $this->settings['branding']['admin_bar_logo'] ); ?>">
							<button id="wpmtk-cms-admin-bar-logo-upload" type="button" class="button wpmtk-white-label-cms__logo-upload" data-field="branding.admin_bar_logo" aria-labelledby="wpmtk-cms-admin-bar-logo-upload wpmtk-cms-admin-bar-logo-label" aria-describedby="wpmtk-cms-admin-bar-logo-description" data-title="<?php esc_attr_e( 'Choose admin bar logo', 'wpmastertoolkit' ); ?>" data-button="<?php esc_attr_e( 'Use this image', 'wpmastertoolkit' ); ?>"><?php esc_html_e( 'Upload', 'wpmastertoolkit' ); ?></button>
						</div>
						<p id="wpmtk-cms-admin-bar-logo-description" class="wp-mastertoolkit__section__body__item__content__desc"><?php esc_html_e( 'Replace the WordPress logo in the admin bar. Max height 20px.', 'wpmastertoolkit' ); ?></p>
					</div>
				</div>

				<div class="wp-mastertoolkit__section__body__item">
					<label class="wp-mastertoolkit__section__body__item__title" for="wpmtk-cms-admin-bar-alt-text"><?php esc_html_e( 'Admin Bar Alt Text', 'wpmastertoolkit' ); ?></label>
					<div class="wp-mastertoolkit__section__body__item__content">
						<input id="wpmtk-cms-admin-bar-alt-text" class="regular-text" type="text" name="<?php echo esc_attr( $this->option_id . '[branding][admin_bar_alt_text]' ); ?>" value="<?php echo esc_attr( $this->settings['branding']['admin_bar_alt_text'] ); ?>" data-field="branding.admin_bar_alt_text" aria-describedby="wpmtk-cms-admin-bar-alt-text-description" style="max-width:100%;">
						<p id="wpmtk-cms-admin-bar-alt-text-description" class="wp-mastertoolkit__section__body__item__content__desc"><?php esc_html_e( 'Replace the WordPress logo alternative text. Leave empty to keep the default.', 'wpmastertoolkit' ); ?></p>
					</div>
				</div>

				<fieldset class="wp-mastertoolkit__section__body__item wpmtk-white-label-cms__pro-field" <?php disabled( ! $pro ); ?>>
					<label class="wp-mastertoolkit__section__body__item__title" for="wpmtk-cms-admin-bar-url"><?php esc_html_e( 'Admin Bar URL', 'wpmastertoolkit' ); ?> <?php $this->render_pro_badge(); ?></label>
					<div class="wp-mastertoolkit__section__body__item__content">
						<input id="wpmtk-cms-admin-bar-url" class="regular-text" type="url" name="<?php echo esc_attr( $this->option_id . '[branding][admin_bar_url]' ); ?>" value="<?php echo esc_attr( $this->settings['branding']['admin_bar_url'] ); ?>" data-field="branding.admin_bar_url" aria-describedby="wpmtk-cms-admin-bar-url-description" style="max-width:100%;">
						<p id="wpmtk-cms-admin-bar-url-description" class="wp-mastertoolkit__section__body__item__content__desc"><?php esc_html_e( 'Replace the admin bar logo link and the WordPress.org menu link. Leave empty to keep the defaults.', 'wpmastertoolkit' ); ?></p>
					</div>
				</fieldset>

				<div class="wp-mastertoolkit__section__body__item">
					<label class="wp-mastertoolkit__section__body__item__title" for="wpmtk-cms-howdy-text"><?php esc_html_e( 'Replace Howdy Text', 'wpmastertoolkit' ); ?></label>
					<div class="wp-mastertoolkit__section__body__item__content">
						<input id="wpmtk-cms-howdy-text" class="regular-text" type="text" name="<?php echo esc_attr( $this->option_id . '[branding][howdy_text]' ); ?>" value="<?php echo esc_attr( $this->settings['branding']['howdy_text'] ); ?>" data-field="branding.howdy_text" aria-describedby="wpmtk-cms-howdy-text-description" style="max-width:100%;">
						<p id="wpmtk-cms-howdy-text-description" class="wp-mastertoolkit__section__body__item__content__desc"><?php esc_html_e( 'Enter a space to remove the greeting, or replace it with something like "Hi,". Leave empty to keep the default.', 'wpmastertoolkit' ); ?></p>
					</div>
				</div>

				<fieldset class="wp-mastertoolkit__section__body__item wpmtk-white-label-cms__pro-field" <?php disabled( ! $pro ); ?>>
					<legend id="wpmtk-cms-side-menu-image-label" class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Side Menu Image', 'wpmastertoolkit' ); ?> <?php $this->render_pro_badge(); ?></legend>
					<div class="wp-mastertoolkit__section__body__item__content wpmtk-white-label-cms__logo">
						<div class="wpmtk-white-label-cms__logo-preview" <?php echo '' === $this->settings['branding']['side_menu_image'] ? 'hidden' : ''; ?>>
							<img src="<?php echo '' !== $this->settings['branding']['side_menu_image'] ? esc_url( $this->settings['branding']['side_menu_image'] ) : 'data:,'; ?>" alt="<?php esc_attr_e( 'Side Menu Image', 'wpmastertoolkit' ); ?>">
							<button type="button" class="wpmtk-white-label-cms__logo-remove" aria-label="<?php esc_attr_e( 'Remove image', 'wpmastertoolkit' ); ?>" title="<?php esc_attr_e( 'Remove image', 'wpmastertoolkit' ); ?>"><span class="dashicons dashicons-dismiss" aria-hidden="true"></span></button>
						</div>
						<div class="wpmtk-white-label-cms__logo-controls">
							<input id="wpmtk-cms-side-menu-image" type="hidden" name="<?php echo esc_attr( $this->option_id . '[branding][side_menu_image]' ); ?>" value="<?php echo esc_attr( $this->settings['branding']['side_menu_image'] ); ?>">
							<button id="wpmtk-cms-side-menu-image-upload" type="button" class="button wpmtk-white-label-cms__logo-upload" data-field="branding.side_menu_image" aria-labelledby="wpmtk-cms-side-menu-image-upload wpmtk-cms-side-menu-image-label" aria-describedby="wpmtk-cms-side-menu-image-description" data-title="<?php esc_attr_e( 'Side Menu Image', 'wpmastertoolkit' ); ?>" data-button="<?php esc_attr_e( 'Use this image', 'wpmastertoolkit' ); ?>"><?php esc_html_e( 'Upload', 'wpmastertoolkit' ); ?></button>
						</div>
						<p id="wpmtk-cms-side-menu-image-description" class="wp-mastertoolkit__section__body__item__content__desc"><?php esc_html_e( 'Image will appear at the top of the side menu. Max width 160px.', 'wpmastertoolkit' ); ?></p>
					</div>
				</fieldset>

				<fieldset class="wp-mastertoolkit__section__body__item wpmtk-white-label-cms__pro-field" <?php disabled( ! $pro ); ?>>
					<label class="wp-mastertoolkit__section__body__item__title" for="wpmtk-cms-side-menu-url"><?php esc_html_e( 'Side Menu Link URL', 'wpmastertoolkit' ); ?> <?php $this->render_pro_badge(); ?></label>
					<div class="wp-mastertoolkit__section__body__item__content">
						<input id="wpmtk-cms-side-menu-url" class="regular-text" type="url" name="<?php echo esc_attr( $this->option_id . '[branding][side_menu_url]' ); ?>" value="<?php echo esc_attr( $this->settings['branding']['side_menu_url'] ); ?>" data-field="branding.side_menu_url" aria-describedby="wpmtk-cms-side-menu-url-description" style="max-width:100%;">
						<p id="wpmtk-cms-side-menu-url-description" class="wp-mastertoolkit__section__body__item__content__desc"><?php esc_html_e( 'External links will open in a new window.', 'wpmastertoolkit' ); ?></p>
					</div>
				</fieldset>

				<fieldset class="wp-mastertoolkit__section__body__item wpmtk-white-label-cms__pro-field" <?php disabled( ! $pro ); ?>>
					<label class="wp-mastertoolkit__section__body__item__title" for="wpmtk-cms-side-menu-alt-text"><?php esc_html_e( 'Side Menu Alt Text', 'wpmastertoolkit' ); ?> <?php $this->render_pro_badge(); ?></label>
					<div class="wp-mastertoolkit__section__body__item__content">
						<input id="wpmtk-cms-side-menu-alt-text" class="regular-text" type="text" name="<?php echo esc_attr( $this->option_id . '[branding][side_menu_alt_text]' ); ?>" value="<?php echo esc_attr( $this->settings['branding']['side_menu_alt_text'] ); ?>" data-field="branding.side_menu_alt_text" aria-describedby="wpmtk-cms-side-menu-alt-text-description" style="max-width:100%;">
						<p id="wpmtk-cms-side-menu-alt-text-description" class="wp-mastertoolkit__section__body__item__content__desc"><?php esc_html_e( 'Alt text for the Side Menu Image link. Leave empty to use the site name.', 'wpmastertoolkit' ); ?></p>
					</div>
				</fieldset>
				
				<fieldset class="wp-mastertoolkit__section__body__item wpmtk-white-label-cms__pro-field" <?php disabled( ! $pro ); ?>>
					<legend id="wpmtk-cms-footer-image-label" class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Footer Image', 'wpmastertoolkit' ); ?> <?php $this->render_pro_badge(); ?></legend>
					<div class="wp-mastertoolkit__section__body__item__content wpmtk-white-label-cms__logo">
						<div class="wpmtk-white-label-cms__logo-preview" <?php echo '' === $this->settings['branding']['footer_image'] ? 'hidden' : ''; ?>>
							<img src="<?php echo '' !== $this->settings['branding']['footer_image'] ? esc_url( $this->settings['branding']['footer_image'] ) : 'data:,'; ?>" alt="<?php esc_attr_e( 'Footer Image', 'wpmastertoolkit' ); ?>">
							<button type="button" class="wpmtk-white-label-cms__logo-remove" aria-label="<?php esc_attr_e( 'Remove image', 'wpmastertoolkit' ); ?>" title="<?php esc_attr_e( 'Remove image', 'wpmastertoolkit' ); ?>"><span class="dashicons dashicons-dismiss" aria-hidden="true"></span></button>
						</div>
						<div class="wpmtk-white-label-cms__logo-controls">
							<input id="wpmtk-cms-footer-image" type="hidden" name="<?php echo esc_attr( $this->option_id . '[branding][footer_image]' ); ?>" value="<?php echo esc_attr( $this->settings['branding']['footer_image'] ); ?>">
							<button id="wpmtk-cms-footer-image-upload" type="button" class="button wpmtk-white-label-cms__logo-upload" data-field="branding.footer_image" aria-labelledby="wpmtk-cms-footer-image-upload wpmtk-cms-footer-image-label" aria-describedby="wpmtk-cms-footer-image-description" data-title="<?php esc_attr_e( 'Footer Image', 'wpmastertoolkit' ); ?>" data-button="<?php esc_attr_e( 'Use this image', 'wpmastertoolkit' ); ?>"><?php esc_html_e( 'Upload', 'wpmastertoolkit' ); ?></button>
						</div>
						<p id="wpmtk-cms-footer-image-description" class="wp-mastertoolkit__section__body__item__content__desc"><?php esc_html_e( 'Replace the WordPress footer credit with an image. Max height 50px.', 'wpmastertoolkit' ); ?></p>
					</div>
				</fieldset>

				<div class="wp-mastertoolkit__section__body__item">
					<label class="wp-mastertoolkit__section__body__item__title" for="wpmtk-cms-footer-text"><?php esc_html_e( 'Footer Text', 'wpmastertoolkit' ); ?></label>
					<div class="wp-mastertoolkit__section__body__item__content">
						<input id="wpmtk-cms-footer-text" class="regular-text" type="text" name="<?php echo esc_attr( $this->option_id . '[branding][footer_text]' ); ?>" value="<?php echo esc_attr( $this->settings['branding']['footer_text'] ); ?>" data-field="branding.footer_text" aria-describedby="wpmtk-cms-footer-text-description" style="max-width:100%;">
						<p id="wpmtk-cms-footer-text-description" class="wp-mastertoolkit__section__body__item__content__desc"><?php esc_html_e( 'Text displayed beside the footer image, or on its own when no image is selected.', 'wpmastertoolkit' ); ?></p>
					</div>
				</div>
				
				<fieldset class="wp-mastertoolkit__section__body__item wpmtk-white-label-cms__pro-field" <?php disabled( ! $pro ); ?>>
					<label class="wp-mastertoolkit__section__body__item__title" for="wpmtk-cms-footer-url"><?php esc_html_e( 'Footer URL', 'wpmastertoolkit' ); ?> <?php $this->render_pro_badge(); ?></label>
					<div class="wp-mastertoolkit__section__body__item__content">
						<input id="wpmtk-cms-footer-url" class="regular-text" type="url" name="<?php echo esc_attr( $this->option_id . '[branding][footer_url]' ); ?>" value="<?php echo esc_attr( $this->settings['branding']['footer_url'] ); ?>" data-field="branding.footer_url" aria-describedby="wpmtk-cms-footer-url-description" style="max-width:100%;">
						<p id="wpmtk-cms-footer-url-description" class="wp-mastertoolkit__section__body__item__content__desc"><?php esc_html_e( 'Link for the footer image and text. Leave empty to display them without a link.', 'wpmastertoolkit' ); ?></p>
					</div>
				</fieldset>

				<div class="wp-mastertoolkit__section__body__item">
					<div class="wp-mastertoolkit__section__body__item__title" style="display:flex;align-items:center;gap:8px;">
						<label class="wp-mastertoolkit__toggle" for="wpmtk-cms-hide-wp-version">
							<input type="hidden" name="<?php echo esc_attr( $this->option_id . '[branding][hide_wp_version]' ); ?>" value="0">
							<input id="wpmtk-cms-hide-wp-version" type="checkbox" name="<?php echo esc_attr( $this->option_id . '[branding][hide_wp_version]' ); ?>" value="1" data-field="branding.hide_wp_version" aria-describedby="wpmtk-cms-hide-wp-version-description" <?php checked( $this->settings['branding']['hide_wp_version'] ); ?>>
							<span class="wp-mastertoolkit__toggle__slider round" aria-hidden="true"></span>
							<span class="screen-reader-text"><?php esc_html_e( 'Hide WP Version', 'wpmastertoolkit' ); ?></span>
						</label>
						<label for="wpmtk-cms-hide-wp-version"><?php esc_html_e( 'Hide WP Version', 'wpmastertoolkit' ); ?></label>
					</div>
					<p id="wpmtk-cms-hide-wp-version-description" class="wp-mastertoolkit__section__body__item__content__desc"><?php esc_html_e( 'Hide the WordPress version and update text displayed in the admin footer.', 'wpmastertoolkit' ); ?></p>
				</div>
			</div>
		</section>

		<section class="wp-mastertoolkit__section" aria-labelledby="wpmtk-cms-heading-dashboard">
			<div class="wp-mastertoolkit__section__desc">
				<h2 id="wpmtk-cms-heading-dashboard"><?php esc_html_e( 'Dashboard', 'wpmastertoolkit' ); ?></h2>
			</div>
			<div class="wp-mastertoolkit__section__body">
				<fieldset class="wp-mastertoolkit__section__body__item wpmtk-white-label-cms__pro-field" <?php disabled( ! $pro ); ?>>
					<legend id="wpmtk-cms-dashboard-icon-label" class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Dashboard Icon', 'wpmastertoolkit' ); ?> <?php $this->render_pro_badge(); ?></legend>
					<div class="wp-mastertoolkit__section__body__item__content wpmtk-white-label-cms__logo">
						<div class="wpmtk-white-label-cms__logo-preview" <?php echo '' === $this->settings['dashboard']['icon'] ? 'hidden' : ''; ?>>
							<img src="<?php echo '' !== $this->settings['dashboard']['icon'] ? esc_url( $this->settings['dashboard']['icon'] ) : 'data:,'; ?>" alt="<?php esc_attr_e( 'Dashboard icon preview', 'wpmastertoolkit' ); ?>">
							<button type="button" class="wpmtk-white-label-cms__logo-remove" aria-label="<?php esc_attr_e( 'Remove dashboard icon', 'wpmastertoolkit' ); ?>" title="<?php esc_attr_e( 'Remove dashboard icon', 'wpmastertoolkit' ); ?>"><span class="dashicons dashicons-dismiss" aria-hidden="true"></span></button>
						</div>
						<div class="wpmtk-white-label-cms__logo-controls">
							<input id="wpmtk-cms-dashboard-icon" type="hidden" name="<?php echo esc_attr( $this->option_id . '[dashboard][icon]' ); ?>" value="<?php echo esc_attr( $this->settings['dashboard']['icon'] ); ?>">
							<button id="wpmtk-cms-dashboard-icon-upload" type="button" class="button wpmtk-white-label-cms__logo-upload" data-field="dashboard.icon" aria-labelledby="wpmtk-cms-dashboard-icon-upload wpmtk-cms-dashboard-icon-label" aria-describedby="wpmtk-cms-dashboard-icon-description" data-title="<?php esc_attr_e( 'Choose dashboard icon', 'wpmastertoolkit' ); ?>" data-button="<?php esc_attr_e( 'Use this image', 'wpmastertoolkit' ); ?>"><?php esc_html_e( 'Upload', 'wpmastertoolkit' ); ?></button>
						</div>
						<p id="wpmtk-cms-dashboard-icon-description" class="wp-mastertoolkit__section__body__item__content__desc"><?php esc_html_e( 'Add a logo beside the Dashboard heading. Display height 40px.', 'wpmastertoolkit' ); ?></p>
					</div>
				</fieldset>

				<fieldset class="wp-mastertoolkit__section__body__item wpmtk-white-label-cms__pro-field" <?php disabled( ! $pro ); ?>>
					<label class="wp-mastertoolkit__section__body__item__title" for="wpmtk-cms-dashboard-title"><?php esc_html_e( 'Dashboard Title', 'wpmastertoolkit' ); ?> <?php $this->render_pro_badge(); ?></label>
					<div class="wp-mastertoolkit__section__body__item__content">
						<input id="wpmtk-cms-dashboard-title" class="regular-text" type="text" name="<?php echo esc_attr( $this->option_id . '[dashboard][title]' ); ?>" value="<?php echo esc_attr( $this->settings['dashboard']['title'] ); ?>" placeholder="<?php esc_attr_e( 'Dashboard', 'wpmastertoolkit' ); ?>" data-field="dashboard.title" aria-describedby="wpmtk-cms-dashboard-title-description" style="max-width:100%;">
						<p id="wpmtk-cms-dashboard-title-description" class="wp-mastertoolkit__section__body__item__content__desc"><?php esc_html_e( 'Change the Dashboard heading. Leave empty to keep the default.', 'wpmastertoolkit' ); ?></p>
					</div>
				</fieldset>

				<div class="wp-mastertoolkit__section__body__item">
					<div class="wp-mastertoolkit__section__body__item__title wpmtk-white-label-cms__welcome-toggle">
						<label class="wp-mastertoolkit__toggle" for="wpmtk-cms-welcome-enabled">
							<input type="hidden" name="<?php echo esc_attr( $this->option_id . '[dashboard][welcome][enabled]' ); ?>" value="0">
							<input id="wpmtk-cms-welcome-enabled" type="checkbox" name="<?php echo esc_attr( $this->option_id . '[dashboard][welcome][enabled]' ); ?>" value="1" data-field="dashboard.welcome.enabled" <?php checked( $this->settings['dashboard']['welcome']['enabled'] ); ?> aria-describedby="wpmtk-cms-welcome-enabled-description">
							<span class="wp-mastertoolkit__toggle__slider" aria-hidden="true"></span>
							<span class="screen-reader-text"><?php esc_html_e( 'Add Your Own Welcome Panel', 'wpmastertoolkit' ); ?></span>
						</label>
						<label for="wpmtk-cms-welcome-enabled"><?php esc_html_e( 'Add Your Own Welcome Panel', 'wpmastertoolkit' ); ?></label>
					</div>
					<p id="wpmtk-cms-welcome-enabled-description" class="wp-mastertoolkit__section__body__item__content__desc"><?php esc_html_e( 'Add a custom welcome panel at the top of the Dashboard.', 'wpmastertoolkit' ); ?></p>
				</div>

				<div class="wpmtk-white-label-cms__welcome-options" data-show-if="<?php echo esc_attr( $this->option_id . '[dashboard][welcome][enabled]=1' ); ?>">
					<div class="wp-mastertoolkit__section__body__item">
						<label id="wpmtk-cms-welcome-roles-label" class="wp-mastertoolkit__section__body__item__title" for="wpmtk-cms-welcome-roles"><?php esc_html_e( 'Select the Roles the Welcome Panel Will Be Visible To', 'wpmastertoolkit' ); ?></label>
						<div class="wp-mastertoolkit__select2">
							<input type="hidden" name="<?php echo esc_attr( $this->option_id . '[dashboard][welcome][roles][]' ); ?>" value="">
							<select id="wpmtk-cms-welcome-roles" name="<?php echo esc_attr( $this->option_id . '[dashboard][welcome][roles][]' ); ?>" multiple data-field="dashboard.welcome.roles" aria-describedby="wpmtk-cms-welcome-roles-description">
								<?php foreach ( wp_roles()->roles as $role => $details ) : ?>
									<option value="<?php echo esc_attr( $role ); ?>" <?php selected( in_array( $role, $this->settings['dashboard']['welcome']['roles'], true ) ); ?>><?php echo esc_html( translate_user_role( $details['name'] ) ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<p id="wpmtk-cms-welcome-roles-description" class="wp-mastertoolkit__section__body__item__content__desc"><?php esc_html_e( 'Only users with a selected role will see this panel. No selected roles hides it from everyone.', 'wpmastertoolkit' ); ?></p>
					</div>

					<fieldset class="wp-mastertoolkit__section__body__item">
						<legend class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Template Type', 'wpmastertoolkit' ); ?></legend>
						<div class="wpmtk-white-label-cms__welcome-templates">
							<label><input type="radio" name="<?php echo esc_attr( $template_name ); ?>" value="basic_html" data-field="dashboard.welcome.template" <?php checked( ! $pro || 'basic_html' === $this->settings['dashboard']['welcome']['template'] ); ?>><?php esc_html_e( 'Basic HTML', 'wpmastertoolkit' ); ?></label>
							<label><input type="radio" name="<?php echo esc_attr( $template_name ); ?>" value="page" <?php checked( $pro && 'page' === $this->settings['dashboard']['welcome']['template'] ); ?> <?php disabled( ! $pro ); ?>><?php esc_html_e( 'Page', 'wpmastertoolkit' ); ?> <?php $this->render_pro_badge(); ?></label>
							<label><input type="radio" name="<?php echo esc_attr( $template_name ); ?>" value="elementor" <?php checked( $pro && 'elementor' === $this->settings['dashboard']['welcome']['template'] ); ?> <?php disabled( ! $pro || ! class_exists( '\Elementor\Plugin' ) ); ?>><?php esc_html_e( 'Elementor', 'wpmastertoolkit' ); ?> <?php $this->render_pro_badge(); ?></label>
						</div>
					</fieldset>

					<div data-show-if="<?php echo esc_attr( $template_name . '=elementor' ); ?>" <?php echo ! $pro ? 'hidden' : ''; ?>>
						<div class="wp-mastertoolkit__section__body__item">
							<label for="wpmtk-cms-elementor-template" class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Template', 'wpmastertoolkit' ); ?></label>
							<select id="wpmtk-cms-elementor-template" name="<?php echo esc_attr( $this->option_id . '[dashboard][welcome][elementor_template_id]' ); ?>" data-field="dashboard.welcome.elementor_template_id" aria-describedby="wpmtk-cms-elementor-template-description" style="width:100%;max-width:100%;" <?php disabled( ! $pro ); ?>>
								<option value="0"><?php esc_html_e( 'Select an Elementor template', 'wpmastertoolkit' ); ?></option>
								<?php
								$templates = get_posts( array( 'post_type' => 'elementor_library', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
								foreach ( $templates as $template ) :
									if ( ! self::get_elementor_welcome_template( $template->ID ) ) {
										continue;
									}
									?>
									<option value="<?php echo esc_attr( $template->ID ); ?>" <?php selected( $this->settings['dashboard']['welcome']['elementor_template_id'], $template->ID ); ?>><?php echo esc_html( $template->post_title ); ?></option>
								<?php endforeach; ?>
							</select>
							<p id="wpmtk-cms-elementor-template-description" class="wp-mastertoolkit__section__body__item__content__desc"><?php esc_html_e( 'Select a published Elementor saved template. Its content is displayed directly in the Dashboard without the theme header or footer.', 'wpmastertoolkit' ); ?></p>
						</div>
					</div>

					<div data-show-if="<?php echo esc_attr( $template_name . '=page' ); ?>" <?php echo ! $pro ? 'hidden' : ''; ?>>
						<div class="wp-mastertoolkit__section__body__item">
							<label for="wpmtk-cms-welcome-page" class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Page', 'wpmastertoolkit' ); ?></label>
							<select id="wpmtk-cms-welcome-page" name="<?php echo esc_attr( $this->option_id . '[dashboard][welcome][page_id]' ); ?>" data-field="dashboard.welcome.page_id" aria-describedby="wpmtk-cms-welcome-page-description" style="width:100%;max-width:100%;" <?php disabled( ! $pro ); ?>>
								<option value="0"><?php esc_html_e( 'Select a page', 'wpmastertoolkit' ); ?></option>
								<?php
								$pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'has_password' => false, 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
								foreach ( $pages as $page ) :
									?>
									<option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( $this->settings['dashboard']['welcome']['page_id'], $page->ID ); ?>><?php echo esc_html( $page->post_title ); ?></option>
								<?php endforeach; ?>
							</select>
							<p id="wpmtk-cms-welcome-page-description" class="wp-mastertoolkit__section__body__item__content__desc"><?php esc_html_e( 'The page opens in an automatically sized iframe with its frontend styling. The admin bar and standard site headers and footers are hidden only in this view.', 'wpmastertoolkit' ); ?></p>
						</div>
					</div>

					<div data-show-if="<?php echo esc_attr( $template_name . '=basic_html' ); ?>">
						<div class="wp-mastertoolkit__section__body__item">
							<label class="wp-mastertoolkit__section__body__item__title" for="wpmtk-cms-welcome-title"><?php esc_html_e( 'Title of Welcome Panel', 'wpmastertoolkit' ); ?></label>
							<input id="wpmtk-cms-welcome-title" class="regular-text" type="text" name="<?php echo esc_attr( $this->option_id . '[dashboard][welcome][title]' ); ?>" value="<?php echo esc_attr( $this->settings['dashboard']['welcome']['title'] ); ?>" data-field="dashboard.welcome.title">
						</div>

						<div class="wp-mastertoolkit__section__body__item">
							<label class="wp-mastertoolkit__section__body__item__title" for="wpmtk-cms-welcome-html"><?php esc_html_e( 'Welcome Panel Description HTML', 'wpmastertoolkit' ); ?></label>
							<textarea id="wpmtk-cms-welcome-html" name="<?php echo esc_attr( $this->option_id . '[dashboard][welcome][html]' ); ?>" rows="12" data-field="dashboard.welcome.html"><?php echo esc_textarea( $this->settings['dashboard']['welcome']['html'] ); ?></textarea>
						</div>
					</div>
				</div>
			</div>
		</section>

		<section class="wp-mastertoolkit__section" aria-labelledby="wpmtk-cms-heading-settings">
			<div class="wp-mastertoolkit__section__desc">
				<h2 id="wpmtk-cms-heading-settings"><?php esc_html_e( 'Settings', 'wpmastertoolkit' ); ?></h2>
			</div>
			<div class="wp-mastertoolkit__section__body">
				<div class="wp-mastertoolkit__section__body__item">
					<div class="wp-mastertoolkit__section__body__item__title wpmtk-white-label-cms__welcome-toggle">
						<label class="wp-mastertoolkit__toggle" for="wpmtk-cms-hide-frontend-admin-bar">
							<input type="hidden" name="<?php echo esc_attr( $this->option_id . '[settings][hide_frontend_admin_bar]' ); ?>" value="0">
							<input id="wpmtk-cms-hide-frontend-admin-bar" type="checkbox" name="<?php echo esc_attr( $this->option_id . '[settings][hide_frontend_admin_bar]' ); ?>" value="1" data-field="settings.hide_frontend_admin_bar" aria-describedby="wpmtk-cms-hide-frontend-admin-bar-description" <?php checked( $this->settings['settings']['hide_frontend_admin_bar'] ); ?>>
							<span class="wp-mastertoolkit__toggle__slider" aria-hidden="true"></span>
							<span class="screen-reader-text"><?php esc_html_e( 'Hide Front-end Admin Bar', 'wpmastertoolkit'); ?></span>
						</label>
						<label for="wpmtk-cms-hide-frontend-admin-bar"><?php esc_html_e( 'Hide Front-end Admin Bar', 'wpmastertoolkit'); ?></label>
					</div>
					<p id="wpmtk-cms-hide-frontend-admin-bar-description" class="wp-mastertoolkit__section__body__item__content__desc"><?php esc_html_e( 'Disable the frontend admin bar for users with a selected role. The toolbar in wp-admin is unchanged.', 'wpmastertoolkit' ); ?></p>
				</div>

				<div class="wp-mastertoolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->option_id . '[settings][hide_frontend_admin_bar]=1' ); ?>">
					<label id="wpmtk-cms-frontend-admin-bar-roles-label" class="wp-mastertoolkit__section__body__item__title" for="wpmtk-cms-frontend-admin-bar-roles"><?php esc_html_e( 'Hide the Admin Bar for These Roles', 'wpmastertoolkit' ); ?></label>
					<div class="wp-mastertoolkit__select2">
						<input type="hidden" name="<?php echo esc_attr( $this->option_id . '[settings][frontend_admin_bar_roles][]' ); ?>" value="">
						<select id="wpmtk-cms-frontend-admin-bar-roles" name="<?php echo esc_attr( $this->option_id . '[settings][frontend_admin_bar_roles][]' ); ?>" multiple data-field="settings.frontend_admin_bar_roles" aria-describedby="wpmtk-cms-frontend-admin-bar-roles-description">
							<?php foreach ( wp_roles()->roles as $role => $details ) : ?>
								<option value="<?php echo esc_attr( $role ); ?>" <?php selected( in_array( $role, $this->settings['settings']['frontend_admin_bar_roles'], true ) ); ?>><?php echo esc_html( translate_user_role( $details['name'] ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<p id="wpmtk-cms-frontend-admin-bar-roles-description" class="wp-mastertoolkit__section__body__item__content__desc"><?php esc_html_e( 'Users matching any selected role will have the frontend admin bar hidden. Select no roles to hide it for nobody.', 'wpmastertoolkit' ); ?></p>
				</div>

				<div class="wp-mastertoolkit__section__body__item">
					<div class="wp-mastertoolkit__section__body__item__title wpmtk-white-label-cms__welcome-toggle">
						<label class="wp-mastertoolkit__toggle" for="wpmtk-cms-hide-help-box">
							<input type="hidden" name="<?php echo esc_attr( $this->option_id . '[settings][hide_help_box]' ); ?>" value="0">
							<input id="wpmtk-cms-hide-help-box" type="checkbox" name="<?php echo esc_attr( $this->option_id . '[settings][hide_help_box]' ); ?>" value="1" data-field="settings.hide_help_box" aria-describedby="wpmtk-cms-hide-help-box-description" <?php checked( $this->settings['settings']['hide_help_box'] ); ?>>
							<span class="wp-mastertoolkit__toggle__slider" aria-hidden="true"></span>
							<span class="screen-reader-text"><?php esc_html_e( 'Hide Help Box', 'wpmastertoolkit'); ?></span>
						</label>
						<label for="wpmtk-cms-hide-help-box"><?php esc_html_e( 'Hide Help Box', 'wpmastertoolkit'); ?></label>
					</div>
					<p id="wpmtk-cms-hide-help-box-description" class="wp-mastertoolkit__section__body__item__content__desc"><?php esc_html_e( 'Hide the Help tab that appears at the top right of admin screens.', 'wpmastertoolkit' ); ?></p>
				</div>

				<div class="wp-mastertoolkit__section__body__item">
					<div class="wp-mastertoolkit__section__body__item__title wpmtk-white-label-cms__welcome-toggle">
						<label class="wp-mastertoolkit__toggle" for="hide-screen-options">
							<input type="hidden" name="<?php echo esc_attr( $this->option_id . '[settings][hide_screen_options]' ); ?>" value="0">
							<input id="hide-screen-options" type="checkbox" name="<?php echo esc_attr( $this->option_id . '[settings][hide_screen_options]' ); ?>" value="1" data-field="settings.hide_screen_options" aria-describedby="hide-screen-options-description" <?php checked( $this->settings['settings']['hide_screen_options'] ); ?>>
							<span class="wp-mastertoolkit__toggle__slider" aria-hidden="true"></span>
							<span class="screen-reader-text"><?php esc_html_e( 'Hide Screen Options', 'wpmastertoolkit'); ?></span>
						</label>
						<label for="hide-screen-options"><?php esc_html_e( 'Hide Screen Options', 'wpmastertoolkit'); ?></label>
					</div>
					<p id="hide-screen-options-description" class="wp-mastertoolkit__section__body__item__content__desc"><?php esc_html_e( 'Hide the Screen Options tab that appears at the top right of admin screens.', 'wpmastertoolkit' ); ?></p>
				</div>
			</div>
		</section>
		<?php
	}
	
	/**
	 * Render the existing premium badge for locked controls.
	 */
	private function render_pro_badge() {
		if ( ! wpmastertoolkit_is_pro() ) {
			?>
			<span class="wp-mastertoolkit__section__body__item__title__tag pro"><?php esc_html_e( 'PRO', 'wpmastertoolkit' ); ?></span>
			<?php
		}
	}

	/**
	 * Check if the given value is a positive integer ID.
	 *
	 * @param mixed $value The value to check.
	 *
	 * @return bool Whether the value is a positive integer ID.
	 */
	public static function is_positive_id( $value ) {
		return ( is_int( $value ) && $value > 0 ) || ( is_string( $value ) && preg_match( '/^[1-9][0-9]{0,9}$/D', $value ) );
	}

	/**
	 * Enforce the saved Pro policy in addition to existing Toolkit restrictions.
	 *
	 * @return bool Whether the current user may manage this module.
	 */
	private function current_user_is_allowed() {
		if ( ! $this->can_manage_base() ) {
			return false;
		}

		if ( ! wpmastertoolkit_is_pro() ) {
			return true;
		}

		$settings = $this->get_settings();

		return 'all_administrators' === $settings['access_mode']
			|| ( 'only_me' === $settings['access_mode'] && get_current_user_id() === $settings['access_user_id'] && self::eligible_user( $settings['access_user_id'] ) );
	}

	/**
	 * Preserve capability and Toolkit access requirements.
	 *
	 * @return bool Whether baseline module access is permitted.
	 */
	private function can_manage_base() {
		$user_id = get_current_user_id();
		if ( ! current_user_can( 'manage_options' ) || ! self::eligible_user( $user_id ) ) {
			return false;
		}

		if ( '1' === get_option( WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_active_super_admins', '0' ) ) {
			$allowed = get_option( WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_super_admins', array() );
			if ( ! is_array( $allowed ) || ( $allowed && ! self::contains_id( $allowed, $user_id ) ) ) {
				return false;
			}
		}
			
		$white_label = get_option( WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_white_label', array() );
		$allowed     = is_array( $white_label ) ? ( $white_label['allowed_user_ids'] ?? array() ) : null;
		if ( ! is_array( $allowed ) || ( $allowed && ( ! self::contains_id( $allowed, $user_id ) || ! is_user_member_of_blog( $user_id, get_current_blog_id() ) ) ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if the given user ID is contained within the provided array of values.
	 *
	 * @param array $values   The array of values to check.
	 * @param int   $user_id  The user ID to look for.
	 *
	 * @return bool Whether the user ID is found in the array.
	 */
	private static function contains_id( $values, $user_id ) {
		foreach ( $values as $value ) {
			if ( self::is_positive_id( $value ) && (int) $value === $user_id ) {
				return true;
			}
		}
		
		return false;
	}
}
