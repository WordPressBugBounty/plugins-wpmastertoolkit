<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * The admin changelog of the plugin.
 * 
 * @since      2.14.0
 */

?>

<div class="wpmtk-changelog">
	<?php foreach ( $parsed as $wpmtk_item_version => $wpmtk_item_content ) : ?>
		<div class="wpmtk-changelog__item">
			<div class="wpmtk-changelog__item__version">
				<div class="wpmtk-changelog__item__version__tag"><?php echo esc_html( $wpmtk_item_version ); ?></div>
				<div class="wpmtk-changelog__item__version__line">
					<div class="wpmtk-changelog__item__version__line__dot first"></div>
					<div class="wpmtk-changelog__item__version__line__dot second"></div>
				</div>
			</div>
			<div class="wpmtk-changelog__item__content">
				<?php foreach ( $wpmtk_item_content as $wpmtk_item ) :
					$wpmtk_type_c        = strtolower( $wpmtk_item['type'] );
					$wpmtk_is_new_module = $wpmtk_type_c == 'add';
					$wpmtk_is_global     = $wpmtk_item['module'] == '';

					$wpmtk_tag_svg   = 'module';
					$wpmtk_tag_text  = $wpmtk_item['module'];
					if ( $wpmtk_is_new_module ) {
						$wpmtk_tag_svg  = 'new-module';
						$wpmtk_tag_text = __( 'New module', 'wpmastertoolkit' );
					} elseif ( $wpmtk_is_global ) {
						$wpmtk_tag_svg  = 'global';
						$wpmtk_tag_text = __( 'Global', 'wpmastertoolkit' );
					}
				?>
					<div class="wpmtk-changelog__item__content__item <?php echo esc_attr( $wpmtk_tag_svg ); ?>">
						<div class="wpmtk-changelog__item__content__item__type">
							<div class="wpmtk-changelog__item__content__item__type__dot <?php echo esc_attr( $wpmtk_type_c ); ?>"></div>
							<div class="wpmtk-changelog__item__content__item__type__icon"><?php echo wp_kses( file_get_contents(WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/changelog/type/' . $wpmtk_type_c . '.svg'), wpmastertoolkit_allowed_tags_for_svg_files() ); ?></div>
							<div class="wpmtk-changelog__item__content__item__type__text"><?php echo esc_html( $wpmtk_item['type'] ); ?></div>
						</div>

						<div class="wpmtk-changelog__item__content__item__text">
							<?php if ( $wpmtk_is_new_module && ! empty( $wpmtk_item['module'] ) ): ?>
								<?php echo esc_html( $wpmtk_item['module'] ); ?>
							<?php else: ?>
								<?php echo esc_html( $wpmtk_item['text'] ); ?>
							<?php endif; ?>
						</div>

						<div class="wpmtk-changelog__item__content__item__tag <?php echo esc_attr( $wpmtk_tag_svg ); ?>">
							<?php if ( 'new-module' == $wpmtk_tag_svg ): ?>
								<div class="wpmtk-changelog__item__content__item__tag__icon">
									<?php
										// phpcs:ignore WordPress.Security.EscapeOutput
										echo file_get_contents(WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/changelog/' . $wpmtk_tag_svg . '.svg');
									?>
								</div>
							<?php else: ?>
								<div class="wpmtk-changelog__item__content__item__tag__icon"><?php echo wp_kses( file_get_contents(WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/changelog/' . $wpmtk_tag_svg . '.svg'), wpmastertoolkit_allowed_tags_for_svg_files() ); ?></div>
							<?php endif; ?>
							<div class="wpmtk-changelog__item__content__item__tag__text"><?php echo esc_html( $wpmtk_tag_text ); ?></div>
						</div>

						<?php if ( $wpmtk_item['pro'] ): ?>
							<div class="wpmtk-changelog__item__content__item__pro"><?php esc_html_e( 'PRO', 'wpmastertoolkit' ); ?></div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endforeach; ?>
</div>
