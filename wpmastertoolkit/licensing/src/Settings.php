<?php
namespace SureCart\Licensing;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * The settings class.
 */
class Settings {
	/**
	 * SureCart\Licensing\Client
	 *
	 * @var object
	 */
	protected $client;

	/**
	 * Holds the option key
	 *
	 * @var string
	 */
	private $option_key;

	/**
	 * Holds the option name
	 *
	 * @var string
	 */
	private $name;

	/**
	 * Holds the menu arguments
	 *
	 * @var array
	 */
	private $menu_args;

	private $notices;

	/**
	 * Create the pages.
	 *
	 * @param SureCart\Licensing\Client $client The client.
	 */
	public function __construct( Client $client ) {
		$this->client     = $client;
		$this->name       = strtolower( preg_replace( '/\s+/', '', $this->client->name ) );
		$this->option_key = $this->name . '_license_options';
		$this->notices    = array();
	}

	/**
	 * Add the settings page.
	 *
	 * @param array $args Settings page args.
	 *
	 * @return void
	 */
	public function add_page( $args ) {
		// store menu args for proper menu creation.
		$this->menu_args = wp_parse_args(
			$args,
			array(
				'type'               => 'menu', // Can be: menu, options, submenu.
				'page_title'         => 'Manage License',
				'menu_title'         => 'Manage License',
				'capability'         => 'manage_options',
				'menu_slug'          => $this->client->slug . '-manage-license',
				'icon_url'           => '',
				'position'           => null,
				'activated_redirect' => null,
				'parent_slug'        => '',
			)
		);
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 9999999 );
	}

	/**
	 * Form action URL
	 */
	private function form_action_url() {
		return apply_filters( 'surecart_client_license_form_action', '' );
	}

	/**
	 * Set the option key.
	 *
	 * If someone wants to override the default generated key.
	 *
	 * @param string $key The option key.
	 */
	public function set_option_key( $key ) {
		$this->option_key = $key;
		return $this;
	}

	/**
	 * Add the admin menu
	 *
	 * @return void
	 */
	public function admin_menu() {
		switch ( $this->menu_args['type'] ) {
			case 'menu':
				$this->create_menu_page();
				break;
			case 'submenu':
				$this->create_submenu_page();
				break;
			case 'options':
				$this->create_options_page();
				break;
		}
	}

	/**
	 * Add license menu page
	 */
	private function create_menu_page() {
		call_user_func(
			'add_menu_page',
			$this->menu_args['page_title'],
			$this->menu_args['menu_title'],
			$this->menu_args['capability'],
			$this->menu_args['menu_slug'],
			array( $this, 'settings_output' ),
			$this->menu_args['icon_url'],
			$this->menu_args['position']
		);
	}

	/**
	 * Add submenu page
	 */
	private function create_submenu_page() {
		call_user_func(
			'add_submenu_page',
			$this->menu_args['parent_slug'],
			$this->menu_args['page_title'],
			$this->menu_args['menu_title'],
			$this->menu_args['capability'],
			$this->menu_args['menu_slug'],
			array( $this, 'settings_output' ),
			$this->menu_args['position']
		);
	}

	/**
	 * Add submenu page
	 */
	private function create_options_page() {
		call_user_func(
			'add_options_page',
			$this->menu_args['page_title'],
			$this->menu_args['menu_title'],
			$this->menu_args['capability'],
			$this->menu_args['menu_slug'],
			array( $this, 'settings_output' ),
			$this->menu_args['position']
		);
	}

	/**
	 * Get all options
	 *
	 * @return array
	 */
	public function get_options() {
		return (array) get_option( $this->option_key, array() );
	}

	/**
	 * Clear out the options.
	 *
	 * @return bool
	 */
	public function clear_options() {
		return update_option( $this->option_key, array() );
	}

	/**
	 * Get a specific option
	 *
	 * @param string $name Option name.
	 *
	 * @return mixed
	 */
	public function get_option( $name ) {
		$options = $this->get_options();
		return isset( $options[ $name ] ) ? $options[ $name ] : null;
	}

	/**
	 * Set the option.
	 *
	 * @param string $name The option name.
	 * @param mixed  $value The option value.
	 *
	 * @return bool
	 */
	public function set_option( $name, $value ) {
		$options          = (array) $this->get_options();
		$options[ $name ] = $value;
		return update_option( $this->option_key, $options );
	}

	/**
	 * The settings page menu output.
	 *
	 * @return void
	 */
	public function settings_output() {
		$this->license_form_submit();

		$assets = include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/licensing.asset.php';
		// wp_enqueue_script( 'wpmastertoolkit-licensing', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/licensing.js', $assets['dependencies'], $assets['version'], true );
		wp_enqueue_style( 'wpmastertoolkit-licensing', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/licensing.css', array(), $assets['version'] );

		$activation 	= $this->get_activation();
		$action     	= ! empty( $activation->id ) ? 'deactivate' : 'activate';
		?>

		<div class="wrap wpmastertoolkit-license-wrap">
			<div class="wpmastertoolkit-header-section">
				<h1><?php echo esc_html( $this->menu_args['page_title'] ); ?></h1>
				<a class="wpmastertoolkit-header-section__help" target="_blank" href="<?php echo esc_url( __( 'https://wpmastertoolkit.com/en/how-to-upgrade-to-wpmastertoolkit-pro-complete-guide/', 'wpmastertoolkit' ) ); ?>">
					<?php //phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage ?>
					<img src="<?php echo esc_url(WPMASTERTOOLKIT_PLUGIN_URL . 'admin/svg/interrogation.svg'); ?>" alt="">
					<span>
						<?php esc_html_e( 'Help', 'wpmastertoolkit' ); ?>
					</span>
				</a>
			</div>

			<?php $this->render_notices(); ?>

			<section class="wpmastertoolkit-license-main-section" style="<?php echo esc_attr( 'background-image: url(' . WPMASTERTOOLKIT_PLUGIN_URL . 'admin/svg/grey-logo-background.svg);' ); ?>">
				<?php // LEFT ?>
				<form method="post" action="<?php echo esc_attr( $this->form_action_url() ); ?>" class="wpmastertoolkit-license-main-section__left">
					<input type="hidden" name="_action" value="<?php echo esc_attr( $action ); ?>">
					<input type="hidden" name="_nonce" value="<?php echo esc_attr( wp_create_nonce( $this->client->name ) ); ?>">
					<input type="hidden" name="activation_id" value="<?php echo esc_attr( $this->activation_id ); ?>">

					<div class="wpmastertoolkit-license-main-section__left__header">
						<div>
							<?php
							if ( 'activate' === $action ) {
								echo esc_html( sprintf(
									/* translators: %s: client name */
									__( 'Enter your license key to activate %s.', 'wpmastertoolkit' ), $this->client->name )
								);
							} else {
								echo esc_html( sprintf(
									/* translators: %s: client name */
									__( 'Your license is succesfully activated for this site.', 'wpmastertoolkit' ), $this->client->name )
								);
							}
							?>
						</div>
						<div class="wpmastertoolkit-license-main-section__left__header__state">
							<div><?php echo wp_kses_post( __( 'State:', 'wpmastertoolkit' ) ); ?></div>
							<div class="wpmastertoolkit-license-main-section__left__header__state__icon <?php echo esc_attr( $action ); ?>">●</div>
						</div>
					</div>

					<?php if ( 'activate' === $action ) : ?> 
						<input class="widefat" type="password" autocomplete="off" name="license_key" id="license_key" value="<?php echo esc_attr( $this->license_key ); ?>" autofocus placeholder="<?php esc_attr_e('Enter the license...', 'wpmastertoolkit'); ?>">
					<?php else : ?>
						<div class="wpmastertoolkit-license-main-section__left__header__license">
							<?php 
							$obfuscated_license = substr( $this->license_key, 0, 8 ) . str_repeat( '*', strlen( $this->license_key ) - 8 );
							echo esc_html( $obfuscated_license ); 
							?>
						</div>
					<?php endif; ?>

					<?php if ( isset( $_GET['debug'] ) ) : // phpcs:ignore  ?>
						<label for="license_id"><?php echo esc_html( sprintf( __( 'License ID', 'wpmastertoolkit' ), $this->client->name ) ); ?></label>
						<input class="widefat" type="text" autocomplete="off" name="license_id" id="license_id" value="<?php echo esc_attr( $this->license_id ); ?>" autofocus>

						<label for="activation_id"><?php echo esc_html( sprintf( __( 'Activation ID', 'wpmastertoolkit' ), $this->client->name ) ); ?></label>
						<input class="widefat" type="text" autocomplete="off" name="activation_id" id="activation_id" value="<?php echo esc_attr( $this->activation_id ); ?>" autofocus>
					<?php endif; ?>

					<div class="wpmastertoolkit-license-main-section__left__actions">
						<?php if ( 'activate' === $action ) : ?>
							<button name="submit" type="submit" class="wpmastertoolkit-submit-license-form-button activate">
								<?php echo wp_kses( file_get_contents(WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/white-key.svg'), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
								<?php esc_html_e( 'Activate License', 'wpmastertoolkit' ); ?>
							</button>
						<?php else : ?>
							<button name="submit" type="submit" class="wpmastertoolkit-submit-license-form-button deactivate">
								<?php echo wp_kses( file_get_contents(WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/red-key.svg'), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
								<?php esc_html_e( 'Deactivate License', 'wpmastertoolkit' ); ?>
							</button>
						<?php endif; ?>
						
						<a href="<?php echo esc_url( __( 'https://wpmastertoolkit.com/en/customer-dashboard/', 'wpmastertoolkit') ); ?>" target="_blank" class="wpmastertoolkit-my-account-button">
							<?php echo wp_kses( file_get_contents(WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/grey-my-account.svg'), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
							<?php esc_html_e( 'My account', 'wpmastertoolkit' ); ?>
						</a>
					</div>

					<?php 
					/**
					 * Fires after the submit section.
					 *
					 * @param string $action The action.
					 */
					do_action( 'wpmastertoolkit_licensing/after_submit_section', $action ); 
					?>

				</form>

				<?php // RIGHT ?>
				<div class="wpmastertoolkit-license-main-section__right">
					<div class="wpmastertoolkit-license-main-section__right__title">
						<?php esc_html_e( 'With the PRO version you unlock:', 'wpmastertoolkit' ); ?>
					</div>

					<div class="wpmastertoolkit-license-main-section__right__what-you-unlock">
						<div class="wpmastertoolkit-license-main-section__right__what-you-unlock__item">
							<div class="wpmastertoolkit-license-main-section__right__what-you-unlock__item__title">
								<?php echo wp_kses( file_get_contents(WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/unlock-pro-features.svg'), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
								<div>
									<?php esc_html_e( 'Pro features', 'wpmastertoolkit' ); ?>
								</div>
							</div>
							<div class="wpmastertoolkit-license-main-section__right__what-you-unlock__item__description">
								<?php esc_html_e( 'Unlock Pro features on free modules. These features will save you even more time without overloading your site. ✨', 'wpmastertoolkit' ); ?>
							</div>
						</div>
						
						<div class="wpmastertoolkit-license-main-section__right__what-you-unlock__item">
							<div class="wpmastertoolkit-license-main-section__right__what-you-unlock__item__title">
								<?php echo wp_kses( file_get_contents(WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/modules.svg'), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
								<div>
									<?php
										echo esc_html( sprintf( 
											/* translators: %s: Number of vulnerabilities */
											__( '+ %s Pro modules', 'wpmastertoolkit' ), 
											\WPMastertoolkit_Modules_Data::count_modules( 'pro' ),
										) );
									?>
								</div>
							</div>
							<div class="wpmastertoolkit-license-main-section__right__what-you-unlock__item__description">
								<?php esc_html_e( 'Take advantage of all the pro modules. These modules were created mainly to facilitate web professionals and will save you a lot of time. ✨', 'wpmastertoolkit' ); ?>
							</div>
						</div>
					</div>
				</div>
				
			</section>

			<section class="wpmastertoolkit-marketing-section">
				<div class="wpmastertoolkit-marketing-section__left">
					<div class="wpmastertoolkit-marketing-section__left__block" style="<?php echo esc_attr( 'background-image: url(' . WPMASTERTOOLKIT_PLUGIN_URL . 'admin/images/licensing-offers.png' ); ?>">
						<div class="wpmastertoolkit-marketing-section__left__block__content">
							 <div class="wpmastertoolkit-marketing-section__left__block__content__title-top">
								 💼 <?php esc_html_e( 'Our offers', 'wpmastertoolkit' ); ?>
							 </div>
							 <div class="wpmastertoolkit-marketing-section__left__block__content__title">
								 <?php esc_html_e( 'WPMasterToolKit', 'wpmastertoolkit' ); ?>
							 </div>
							 <div class="wpmastertoolkit-marketing-section__left__block__content__description">
								 <?php esc_html_e( 'Choose from our 4 plans, the plan that best suits your needs and enjoy advanced features to optimize your WordPress site. ✨', 'wpmastertoolkit' ); ?>
							 </div>
							 <a href="<?php echo esc_url( __( 'https://wpmastertoolkit.com/en/products-2/wpmastertoolkit-pro/', 'wpmastertoolkit' ) ); ?>" target="_blank" class="wpmastertoolkit-marketing-section__left__block__content__button">
								 <?php echo wp_kses( file_get_contents(WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/white-add-to-cart.svg'), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
								 <?php esc_html_e( 'Buy', 'wpmastertoolkit' ); ?>
							 </a>
						 </div>
					</div>

					<div class="wpmastertoolkit-marketing-section__left__block" style="<?php echo esc_attr( 'background-image: url(' . WPMASTERTOOLKIT_PLUGIN_URL . 'admin/images/licensing-documentation.png' ); ?>">
						<div class="wpmastertoolkit-marketing-section__left__block__content">
							 <div class="wpmastertoolkit-marketing-section__left__block__content__title">
							 	📖 <?php esc_html_e( 'Modules documentation', 'wpmastertoolkit' ); ?>
							 </div>
							 <div class="wpmastertoolkit-marketing-section__left__block__content__description">
								 <?php esc_html_e( 'Find all the essential information to configure and use WPMasterToolKit modules effectively. 🎓', 'wpmastertoolkit' ); ?>
							 </div>
							 <a href="<?php echo esc_url( __( 'https://wpmastertoolkit.com/en/modules/', 'wpmastertoolkit' ) ); ?>" target="_blank" class="wpmastertoolkit-marketing-section__left__block__content__button">
								 <?php echo wp_kses( file_get_contents(WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/white-documentation.svg'), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
								 <?php esc_html_e( 'Discover WPMTK', 'wpmastertoolkit' ); ?>
							 </a>
						 </div>
					</div>
				</div>
				
				<!-- RIGHT -->
				<div class="wpmastertoolkit-marketing-section__right">
					<div class="wpmastertoolkit-marketing-section__right__video">
						<!-- <video src="#" autoplay loop muted controls></video> -->
						<?php //phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage ?>
						<img class="wpmastertoolkit-marketing-section__right__video__no-video-placeholder" src="<?php echo esc_url( WPMASTERTOOLKIT_PLUGIN_URL . 'admin/images/licensing-video-placeholder.png' ); ?>" alt="">
					</div>
					<div class="wpmastertoolkit-marketing-section__right__content">
						<div class="wpmastertoolkit-marketing-section__right__content__title">
							🔓 <?php esc_html_e( 'Unlock the full potential of WPMTK', 'wpmastertoolkit' ); ?>
						</div>
						<div class="wpmastertoolkit-marketing-section__right__content__description">
							<?php esc_html_e( 'Discover the power of WPMasterToolKit and unlock the full potential of your WordPress site. 🔥', 'wpmastertoolkit' ); ?>
						</div>
					</div>
				</div>
			</section>
		</div>

		<?php
	}

	/**
	 * Get the activation.
	 *
	 * @return Object|false
	 */
	public function get_activation() {
		$activation = false;
		if ( $this->activation_id ) {
			$activation = $this->client->activation()->get( $this->activation_id );
			if ( is_wp_error( $activation ) ) {
				if ( 'not_found' === $activation->get_error_code() ) {
					$this->add_error( 'deactivaed', __( 'Your license has been deactivated for this site.', 'wpmastertoolkit' ) );
					$this->clear_options();
				}
			}
		}
		return $activation;
	}

	/**
	 * License form submit
	 */
	public function license_form_submit() {
		// only if we are submitting.
		if ( ! isset( $_POST['submit'] ) ) {
			return;
		}

		// Check nonce.
		if ( ! isset( $_POST['_nonce'], $_POST['_action'] ) ) {
			$this->add_error( 'missing_info', __( 'Please add all information', 'wpmastertoolkit' ) );
			return;
		}

		// Cerify nonce.
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_nonce'] ) ), $this->client->name ) ) {
			$this->add_error( 'unauthorized', __( "You don't have permission to manage licenses.", 'wpmastertoolkit' ) );
			return;
		}

		// handle activation.
		if ( 'activate' === sanitize_text_field( wp_unslash( $_POST['_action'] ) ) ) {
			$activated = $this->client->license()->activate( sanitize_text_field( wp_unslash( $_POST['license_key'] ?? '' ) ) );
			if ( is_wp_error( $activated ) ) {
				$this->add_error( $activated->get_error_code(), $activated->get_error_message() );
				return;
			}

			do_action( 'wpmastertoolkit_licensing/license_activated' );

			if ( ! empty( $this->menu_args['activated_redirect'] ) ) {
				$this->redirect( $this->menu_args['activated_redirect'] );
				exit;
			}

			return $this->add_success( 'activated', __( 'This site was successfully activated.', 'wpmastertoolkit' ) );
		}

		// handle deactivation.
		if ( 'deactivate' === sanitize_text_field( wp_unslash( $_POST['_action'] ) ) ) {
			$deactivated = $this->client->license()->deactivate( sanitize_text_field( wp_unslash( $_POST['activation_id'] ?? '' ) ) );
			if ( is_wp_error( $deactivated ) ) {
				$this->add_error( $deactivated->get_error_code(), $deactivated->get_error_message() );
			}

			do_action( 'wpmastertoolkit_licensing/license_deactivated' );

			if ( ! empty( $this->menu_args['deactivated_redirect'] ) ) {
				$this->redirect( $this->menu_args['deactivated_redirect'] );
				exit;
			}

			return $this->add_success( 'deactivated', __( 'This site was successfully deactivated.', 'wpmastertoolkit' ) );
		}
	}

	/**
	 * Redirect to a url client-side.
	 * We need to do this to avoid "headers already sent" messages.
	 *
	 * @param string $url Url to redirect.
	 *
	 * @return void
	 */
	public function redirect( $url ) {
		?>
		<div class="spinner is-active"></div>
		<script>
			window.location.assign("<?php echo esc_url( $url ); ?>");
		</script>
		<?php
	}

	/**
	 * Add a notice.
	 *
	 * @param string $code Notice code.
	 * @param string $message Notice message.
	 * @param string $type Notice type.
	 *
	 * @return void
	 */
	public function add_notice( $code, $message, $type = 'info' ) {
		$this->notices[] = array(
			'code'    => $code,
			'message' => $message,
			'type'    => $type,
		);
	}

	/**
	 * Add an error.
	 *
	 * @param string $code Error code.
	 * @param string $message Error message.
	 *
	 * @return void
	 */
	public function add_error( $code, $message ) {
		$this->add_notice( $code, $message, 'error' );
	}
	
	/**
	 * render_notices
	 *
	 * @return void
	 */
	public function render_notices(){
		if( empty( $this->notices ) )return;
		?>
		<section class="wpmastertoolkit-notices-section">
			<?php
			foreach ( $this->notices as $notice ) {
				?>
				<div class="wpmastertoolkit-notice wpmastertoolkit-notice--<?php echo esc_attr( $notice['type'] ); ?>">
					<?php echo esc_html( $notice['message'] ); ?>
				</div>
				<?php
			}
			?>
		</section>
		<?php
	}

	/**
	 * Add an success message
	 *
	 * @param string $code Success code.
	 * @param string $message Success message.
	 *
	 * @return void
	 */
	public function add_success( $code, $message ) {
		$this->add_notice( $code, $message, 'success' );
	}

	/**
	 * Set an option.
	 *
	 * @param string $name Name of option.
	 *
	 * @return mixed
	 */
	public function __get( $name ) {
		return $this->get_option( 'sc_' . $name );
	}

	/**
	 * Set an option
	 *
	 * @param string $name Name of option.
	 * @param mixed  $value Value.
	 *
	 * @return bool
	 */
	public function __set( $name, $value ) {
		return $this->set_option( 'sc_' . $name, $value );
	}
}
