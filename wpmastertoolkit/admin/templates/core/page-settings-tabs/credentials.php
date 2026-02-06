<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * The credientials tap of the settings page.
 * 
 * @since      2.8.0
 */

$wpmtk_settings = get_option( 'wpmastertoolkit_credentials_tab', array() );
?>

<div class="wp-mastertoolkit__body__sections__item hide-in-all" data-key="credentials">

	<?php foreach ( wpmastertoolkit_ai_modules() as $wpmtk_ai_module_key => $wpmtk_ai_module ) : 
		$wpmtk_api_key    = $wpmtk_settings[$wpmtk_ai_module_key] ?? '';
		$wpmtk_has_key    = ! empty( $wpmtk_api_key );
		$wpmtk_masked_key = $wpmtk_has_key && strlen( $wpmtk_api_key ) > 8 ? substr( $wpmtk_api_key, 0, 4 ) . str_repeat( '•', min( strlen( $wpmtk_api_key ) - 8, 40 ) ) . substr( $wpmtk_api_key, -4 ) : $wpmtk_api_key;
	?>

		<div class="wp-mastertoolkit__body__sections__item__top">
			<div class="wp-mastertoolkit__body__sections__item__title">
				<img class="ai-logo" src="<?php echo esc_url( WPMASTERTOOLKIT_PLUGIN_URL . 'admin/images/' . $wpmtk_ai_module_key . '.png' ); ?>" alt="<?php echo esc_attr( $wpmtk_ai_module['name'] ?? '' ); ?>" height="20">
				<?php echo esc_html( $wpmtk_ai_module['name'] ?? '' ); ?>
			</div>
		</div>
		<div class="wp-mastertoolkit__body__sections__item__bottom">
			<div class="api-key-field-wrapper">
				<input 
					type="text" 
					class="api-key-input <?php echo $wpmtk_has_key ? 'has-value' : ''; ?>" 
					name="wpmastertoolkit_credentials_tab[<?php echo esc_attr( $wpmtk_ai_module_key ); ?>]" 
					value="<?php echo esc_attr( $wpmtk_api_key ); ?>"
					data-masked="<?php echo esc_attr( $wpmtk_masked_key ); ?>"
					<?php echo $wpmtk_has_key ? 'readonly' : ''; ?>
					placeholder="<?php esc_attr_e( 'Enter your API Key', 'wpmastertoolkit' ); ?>">
				<?php if ( $wpmtk_has_key ) : ?>
					<button type="button" class="edit-api-key-btn" data-key="<?php echo esc_attr( $wpmtk_ai_module_key ); ?>" title="<?php esc_attr_e( 'Modifier la clé API', 'wpmastertoolkit' ); ?>">
						<?php echo wp_kses( file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/edit.svg' ), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
					</button>
				<?php endif; ?>
			</div>
		</div>

		<div class="wp-mastertoolkit__body__sections__item__space"></div>
	<?php endforeach; ?>

</div>
