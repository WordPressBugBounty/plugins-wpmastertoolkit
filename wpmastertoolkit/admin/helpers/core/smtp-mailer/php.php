<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Class to handle PHP provider.
 * 
 * @since 2.14.0
 */

class WPMastertoolkit_SMTP_Mailer_Php {

	private static $class_smtp_mailer;
	private static $settings         = array();
	private static $default_settings = array();

	/**
	 * Render config provider php
	 * 
	 * @since 2.14.0
	 */
	public static function render_config( $active_provider, $class_smtp_mailer ) {
		self::$class_smtp_mailer = $class_smtp_mailer;
		$global_settings         = self::$class_smtp_mailer->get_settings();
		$global_default_settings = self::$class_smtp_mailer->get_default_settings();
		self::$settings          = $global_settings['providers']['php']['params'] ?? '';
		self::$default_settings  = $global_default_settings['providers']['php']['params'] ?? '';

		$option_id               = self::$class_smtp_mailer->option_id . '[providers][php][params]';
		$sender_name             = self::$settings['sender_name']['value'] ?? '';
		$sender_email            = self::$settings['sender_email']['value'] ?? '';
		$force_sender            = self::$settings['force_sender']['value'] ?? self::$default_settings['force_sender']['value'];
		?>
			<div class="wp-mastertoolkit__section__body php <?php echo 'php' === $active_provider ? 'active' : ''; ?>">
				<div class="wp-mastertoolkit__section__body__item">
					<div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'PHP Config', 'wpmastertoolkit' ); ?></div>
					<div class="wp-mastertoolkit__section__body__item__desc"><?php esc_html_e( "You currently have the Default (none) mailer selected, which won't improve email deliverability.", 'wpmastertoolkit' ); ?></div>
				</div>

				<div class="wp-mastertoolkit__section__body__item">
					<div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Sender Config', 'wpmastertoolkit' ); ?></div>
					<div class="wp-mastertoolkit__section__body__item__content">
						<div class="description"><?php esc_html_e( 'If set, the following sender name/email overrides WordPress core defaults but can still be overridden by other plugins that enables custom sender name/email, e.g. form plugins.', 'wpmastertoolkit' ); ?></div>
						<br>
						<div class="wp-mastertoolkit__input-text flex">
							<div><input type="text" class="" name="<?php echo esc_attr( $option_id . '[sender_name]' ); ?>" value="<?php echo esc_attr( $sender_name ); ?>" placeholder="<?php esc_attr_e( 'Sender name', 'wpmastertoolkit' ); ?>"></div>
							<div><input type="text" class="" name="<?php echo esc_attr( $option_id . '[sender_email]' ); ?>" value="<?php echo esc_attr( $sender_email ); ?>" placeholder="<?php esc_attr_e( 'Sender email', 'wpmastertoolkit' ); ?>"></div>
						</div>
					</div>
				</div>

				<div class="wp-mastertoolkit__section__body__item">
					<div class="wp-mastertoolkit__section__body__item__content">
						<label class="wp-mastertoolkit__toggle">
							<input type="hidden" name="<?php echo esc_attr( $option_id . '[force_sender]' ); ?>" value="0">
							<input type="checkbox" name="<?php echo esc_attr( $option_id . '[force_sender]' ); ?>" value="1" <?php checked( $force_sender, '1' ); ?>>
							<span class="wp-mastertoolkit__toggle__slider round"></span>
						</label>
						<span class="wp-mastertoolkit__checkbox__label__text"><?php esc_html_e( 'Force the usage of the sender name/email defined above. It will override those set by other plugins.', 'wpmastertoolkit' ); ?></span>
					</div>
				</div>
			</div>
		<?php
	}
}
