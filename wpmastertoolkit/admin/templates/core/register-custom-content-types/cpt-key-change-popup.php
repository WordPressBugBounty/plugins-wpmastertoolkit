<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

?>
<div class="wpmtk-popup">
	<div class="wpmtk-popup__overlay" id="JS-popup-overlay"></div>
	<div class="wpmtk-popup__content">
		<div class="wpmtk-popup__header">
			<div class="wpmtk-popup__header__left">
				<div class="wpmtk-popup__header__icon">
					<?php echo wp_kses( file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/square-blue.svg' ), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
				</div>
				<div class="wpmtk-popup__header__title"><?php esc_html_e( 'Method of resolution following changes', 'wpmastertoolkit' ); ?></div>
			</div>
			<div class="wpmtk-popup__header__right">
				<div class="wpmtk-popup__header__close" id="JS-close-popup">
					<?php echo wp_kses( file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/times.svg' ), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
				</div>
			</div>
		</div>
		<div class="wpmtk-popup__body">
			<div class="wpmtk-popup__body__text"><?php esc_html_e( 'Please note that you have just changed the "Post Key" of your Custom Post Type and we have detected existing content for this CPT. Choose how you would like to resolve this change:', 'wpmastertoolkit' ); ?></div>
			<div class="wpmtk-popup__body__content">
				<div class="wpmtk-popup__body__content__item <?php echo !$is_pro ? 'disabled' : ''; ?>">
					<label class="wpmtk-popup__body__content__item__label">
						<input type="radio" name="wpmtk-popup-choice" value="migrate" <?php checked( $is_pro ); ?>>
						<span class="custom-radio"></span>
						<?php esc_html_e( 'Migrate all “posts”:', 'wpmastertoolkit' ); ?>
						<span class="old"></span>→<span class="new"></span>
					</label>
					<span class="pro"><?php esc_html_e( 'PRO', 'wpmastertoolkit' ); ?></span>
				</div>
				<div class="wpmtk-popup__body__content__item <?php echo !$is_pro ? 'disabled' : ''; ?>">
					<label class="wpmtk-popup__body__content__item__label">
						<input type="radio" name="wpmtk-popup-choice" value="delete">
						<span class="custom-radio"></span>
						<?php esc_html_e( 'Delete all “posts” with old Post Key:', 'wpmastertoolkit' ); ?>
						<span class="old"></span>
					</label>
					<span class="pro"><?php esc_html_e( 'PRO', 'wpmastertoolkit' ); ?></span>
				</div>
				<div class="wpmtk-popup__body__content__item">
					<label class="wpmtk-popup__body__content__item__label">
						<input type="radio" name="wpmtk-popup-choice" value="ignore" <?php checked( ! $is_pro ); ?>>
						<span class="custom-radio"></span>
						<?php esc_html_e( 'Do nothing', 'wpmastertoolkit' ); ?>
					</label>
				</div>
			</div>
		</div>
		<div class="wpmtk-popup__footer">
			<div class="wpmtk-popup__footer__submit">
				<button type="button" id="JS-submit-popup"><?php esc_html_e( 'Save & confirm', 'wpmastertoolkit' ); ?></button>
				<div class="wpmtk-spinner"></div>
				<div class="wpmtk-message" id="JS-popup-message"></div>
			</div>
		</div>
	</div>
</div>
