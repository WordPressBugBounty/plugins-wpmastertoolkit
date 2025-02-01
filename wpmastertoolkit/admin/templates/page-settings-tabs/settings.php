<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * The settings tap of the settings page.
 * 
 * @since      1.0.0
 */
?>

<div class="wp-mastertoolkit__body__sections__item hide-in-all" data-key="settings">
    <div class="wp-mastertoolkit__body__sections__item__top">
        <div class="wp-mastertoolkit__body__sections__item__title"><?php esc_html_e( 'Import settings from JSON', 'wpmastertoolkit' ); ?></div>
    </div>
    <div class="wp-mastertoolkit__body__sections__item__top">
        <div class="wp-mastertoolkit__body__sections__item__description"><?php echo esc_html_e( "You can import settings from a JSON file. This will overwrite all current settings. Please make sure to backup your current settings before importing a new file.", 'wpmastertoolkit' ); ?></div>
    </div>
    <div class="wp-mastertoolkit__body__sections__item__bottom">
        <input type="file" name="wpmastertoolkit_settings_tab_input" accept="application/JSON">
        <button class="wp-mastertoolkit__body__sections__item__btn" type="submit" name="wpmastertoolkit_settings_tab_upload_json_submit" >
			<?php echo wp_kses( file_get_contents(WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/upload.svg'), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
            <?php esc_html_e( 'Upload', 'wpmastertoolkit' ); ?>
        </button>
    </div>

    <div class="wp-mastertoolkit__body__sections__item__space"></div>

    <div class="wp-mastertoolkit__body__sections__item__top">
        <div class="wp-mastertoolkit__body__sections__item__title"><?php esc_html_e( 'Export settings to JSON', 'wpmastertoolkit' ); ?></div>
    </div>
    <div class="wp-mastertoolkit__body__sections__item__top">
        <div class="wp-mastertoolkit__body__sections__item__description"><?php echo esc_html_e( 'You can export settings to a JSON file. This will create a JSON file with all current settings.', 'wpmastertoolkit' ); ?></div>
    </div>
    <div class="wp-mastertoolkit__body__sections__item__bottom">
        <button class="wp-mastertoolkit__body__sections__item__btn" type="submit" name="wpmastertoolkit_settings_tab_download_json_submit" >
			<?php echo wp_kses( file_get_contents(WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/download.svg'), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
            <?php esc_html_e( 'Download', 'wpmastertoolkit' ); ?>
        </button>
    </div>

	<div class="wp-mastertoolkit__body__sections__item__space"></div>

	<div class="wp-mastertoolkit__body__sections__item__top">
        <div class="wp-mastertoolkit__body__sections__item__title"><?php esc_html_e( 'Help WPMasterToolkit improve with usage tracking', 'wpmastertoolkit' ); ?></div>
    </div>
	<div class="wp-mastertoolkit__body__sections__item__top">
        <div class="wp-mastertoolkit__body__sections__item__description"><?php echo esc_html_e( 'Gathering usage data allows us to improve WPMasterToolkit. Your site will be considered as we evaluate new features, judge the quality of an update, or determine if an improvement makes sense.', 'wpmastertoolkit' ); ?></div>
    </div>
	<div class="wp-mastertoolkit__body__sections__item__bottom">
		<div class="wp-mastertoolkit__body__sections__item__toggle">
			<label class="wp-mastertoolkit__toggle">
				<input type="hidden" name="<?php echo esc_attr( WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_opt_in'); ?>" value="0">
				<input type="checkbox" name="<?php echo esc_attr(WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_opt_in'); ?>" id="JS-wpmastertoolkit_settings_optin_checkbox" value="1" <?php checked( $opt_in_status, '1' ); ?> >
				<span class="wp-mastertoolkit__toggle__slider round"></span>
			</label>
		</div>
    </div>

</div>
