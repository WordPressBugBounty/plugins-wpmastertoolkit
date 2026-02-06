<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Class to handle Other providers.
 * 
 * @since 2.14.0
 */

class WPMastertoolkit_SMTP_Mailer_Other {

	private static $class_smtp_mailer;
	private static $settings = array();
	private static $default_settings = array();

	/**
	 * Render config provider other
	 * 
	 * @since 2.14.0
	 */
	public static function render_config( $active_provider, $class_smtp_mailer ) {
		self::$class_smtp_mailer = $class_smtp_mailer;
		$global_settings         = self::$class_smtp_mailer->get_settings();
		$global_default_settings = self::$class_smtp_mailer->get_default_settings();
		self::$settings          = $global_settings['providers']['other']['params'] ?? '';
		self::$default_settings  = $global_default_settings['providers']['other']['params'] ?? '';

		$option_id               = self::$class_smtp_mailer->option_id . '[providers][other][params]';
		$host                    = self::$settings['host']['value'] ?? '';
		$port                    = self::$settings['port']['value'] ?? '';
		$authentication          = self::$settings['authentication']['value'] ?? self::$default_settings['authentication']['value'];
		$username                = self::$settings['username']['value'] ?? '';
		$password                = self::$settings['password']['value'] ?? '';
		$encryption_options      = self::$default_settings['encryption']['value']['options'];
		$encryption_value        = self::$settings['encryption']['value']['value'] ?? '';
		$autotls                 = self::$settings['autotls']['value'] ?? self::$default_settings['autotls']['value'];
		?>
		<div class="wp-mastertoolkit__section__body other <?php echo 'other' === $active_provider ? 'active' : ''; ?>">
			<div class="wp-mastertoolkit__section__body__item">
				<div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'SMTP Config', 'wpmastertoolkit' ); ?></div>
				<div class="wp-mastertoolkit__section__body__item__content">
					<div class="description"><?php esc_html_e( 'If set, the following SMTP service/account wil be used to deliver your emails.', 'wpmastertoolkit' ); ?></div>
				</div>
			</div>

			<div class="wp-mastertoolkit__section__body__item">
				<div class="wp-mastertoolkit__section__body__item__content">
					<div class="wp-mastertoolkit__input-text">
						<input type="text" class="" name="<?php echo esc_attr( $option_id . '[host]' ); ?>" value="<?php echo esc_attr( $host ); ?>" placeholder="<?php esc_attr_e( 'Host', 'wpmastertoolkit' ); ?>">
					</div>
				</div>
				<br>
				<div class="wp-mastertoolkit__input-text">
					<input type="text" class="" name="<?php echo esc_attr( $option_id . '[port]' ); ?>" value="<?php echo esc_attr( $port ); ?>" placeholder="<?php esc_attr_e( 'Port', 'wpmastertoolkit' ); ?>">
				</div>
			</div>

			<div class="wp-mastertoolkit__section__body__item">
				<div class="wp-mastertoolkit__section__body__item__title activable">
					<div>
						<label class="wp-mastertoolkit__toggle">
							<input type="hidden" name="<?php echo esc_attr( $option_id . '[authentication]' ); ?>" value="0">
							<input type="checkbox" name="<?php echo esc_attr( $option_id . '[authentication]' ); ?>" value="1" <?php checked( $authentication, '1' ); ?>>
							<span class="wp-mastertoolkit__toggle__slider round"></span>
						</label>
					</div>
					<div>
						<?php esc_html_e( 'Authentication', 'wpmastertoolkit' ); ?>
					</div>
				</div>
			</div>

			<div class="wp-mastertoolkit__section__body__item" data-show-if="<?php echo esc_attr( $option_id . '[authentication]' ); ?>=1">
				<div class="wp-mastertoolkit__section__body__item__content">
					<div class="wp-mastertoolkit__input-text">
						<input type="text" class="" name="<?php echo esc_attr( $option_id . '[username]' ); ?>" value="<?php echo esc_attr( $username ); ?>" placeholder="<?php esc_attr_e( 'Username', 'wpmastertoolkit' ); ?>">
					</div>
					<br>
					<div class="wp-mastertoolkit__input-text">
						<input type="password" class="" name="<?php echo esc_attr( $option_id . '[password]' ); ?>" value="<?php echo esc_attr( $password ); ?>" placeholder="<?php esc_attr_e( 'Password', 'wpmastertoolkit' ); ?>">
					</div>
				</div>
			</div>

			<div class="wp-mastertoolkit__section__body__item">
				<div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Encryption Type', 'wpmastertoolkit' ); ?></div>
				<div class="wp-mastertoolkit__section__body__item__content">
					<div class="wp-mastertoolkit__radio">
						<?php foreach ( $encryption_options as $key => $name ) : ?>
							<label class="wp-mastertoolkit__radio__label">
								<input type="radio" name="<?php echo esc_attr( $option_id . '[encryption][value]' ); ?>" value="<?php echo esc_attr( $key ); ?>" <?php checked( $encryption_value, $key ); ?>>
								<span class="mark"></span>
								<span class="wp-mastertoolkit__checkbox__label__text"><?php echo esc_html( $name ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>
			</div>

			<div class="wp-mastertoolkit__section__body__item" data-show-if="<?php echo esc_attr( $option_id . '[encryption][value]' ); ?>=none|<?php echo esc_attr( $option_id . '[encryption][value]' ); ?>=ssl">
				<div class="wp-mastertoolkit__section__body__item__title activable">
					<div>
						<label class="wp-mastertoolkit__toggle">
							<input type="hidden" name="<?php echo esc_attr( $option_id . '[autotls]' ); ?>" value="0">
							<input type="checkbox" name="<?php echo esc_attr( $option_id . '[autotls]' ); ?>" value="1" <?php checked( $autotls, '1' ); ?>>
							<span class="wp-mastertoolkit__toggle__slider round"></span>
						</label>
					</div>
					<div>
						<?php esc_html_e( 'Auto TLS', 'wpmastertoolkit' ); ?>
					</div>
				</div>
				<div class="wp-mastertoolkit__section__body__item__content">
					<div class="description"><?php esc_html_e( 'By default, TLS encryption is automatically used if the server supports it (recommended). In some cases, due to server misconfigurations, this can cause issues and may need to be disabled.', 'wpmastertoolkit' ); ?></div>
				</div>
			</div>
		</div>
		<?php
	}
}
