<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * The credientials tap of the settings page.
 * 
 * @since      2.8.0
 */

$settings = get_option( 'wpmastertoolkit_credentials_tab', array() );
?>

<div class="wp-mastertoolkit__body__sections__item hide-in-all" data-key="credentials">

	<?php foreach ( wpmastertoolkit_ai_modules() as $ai_module_key => $ai_module ) : ?>

		<div class="wp-mastertoolkit__body__sections__item__top">
			<div class="wp-mastertoolkit__body__sections__item__title"><?php echo esc_html( $ai_module['name'] ?? '' ); ?></div>
		</div>
		<div class="wp-mastertoolkit__body__sections__item__bottom">
			<input type="text" name="wpmastertoolkit_credentials_tab[<?php echo esc_attr( $ai_module_key ); ?>]" value="<?php echo esc_attr( $settings[$ai_module_key] ?? '' ); ?>">
		</div>

		<div class="wp-mastertoolkit__body__sections__item__space"></div>
	<?php endforeach; ?>

</div>
