<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * The submenu footer
 * 
 * @since      1.0.0
 */
?>

        <div class="wp-mastertoolkit__save-button">
            <button type="submit">
				<?php echo wp_kses( file_get_contents(WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/save.svg'), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
            </button>
        </div>

	<?php if ( ! isset($this->disable_form ) ) : ?>
    </form>
	<?php endif; ?>

</div>