<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>

<div class="path">
	<div class="row">
		<div class="col-xs-12 col-sm-5 col-lg-6 pt-1">
			<div class="btn-toolbar" role="toolbar">
				<div class="btn-group js-ace-toolbar">
					<button data-cmd="none" data-option="fullscreen" class="btn btn-sm btn-outline-secondary" id="js-ace-fullscreen" title="<?php esc_attr_e( 'Fullscreen', 'wpmastertoolkit' ); ?>"><i class="fa fa-expand" title="<?php esc_attr_e( 'Fullscreen', 'wpmastertoolkit' ); ?>"></i></button>
					<button data-cmd="find" class="btn btn-sm btn-outline-secondary" id="js-ace-search" title="<?php esc_attr_e( 'Search', 'wpmastertoolkit' ); ?>"><i class="fa fa-search" title="<?php esc_attr_e( 'Search', 'wpmastertoolkit' ); ?>"></i></button>
					<button data-cmd="undo" class="btn btn-sm btn-outline-secondary" id="js-ace-undo" title="<?php esc_attr_e( 'Undo', 'wpmastertoolkit' ); ?>"><i class="fa fa-undo" title="<?php esc_attr_e( 'Undo', 'wpmastertoolkit' ); ?>"></i></button>
					<button data-cmd="redo" class="btn btn-sm btn-outline-secondary" id="js-ace-redo" title="<?php esc_attr_e( 'Redo', 'wpmastertoolkit' ); ?>"><i class="fa fa-repeat" title="<?php esc_attr_e( 'Redo', 'wpmastertoolkit' ); ?>"></i></button>
					<button data-cmd="none" data-option="wrap" class="btn btn-sm btn-outline-secondary" id="js-ace-wordWrap" title="<?php esc_attr_e( 'Word Wrap', 'wpmastertoolkit' ); ?>"><i class="fa fa-text-width" title="<?php esc_attr_e( 'Word Wrap', 'wpmastertoolkit' ); ?>"></i></button>
					<select id="js-ace-mode" data-type="mode" title="<?php esc_attr_e( 'Select Document Type', 'wpmastertoolkit' ); ?>" class="btn-outline-secondary border-start-0 d-none d-md-block"><option>-- <?php esc_html_e( 'Select Mode', 'wpmastertoolkit' ); ?> --</option></select>
					<select id="js-ace-theme" data-type="theme" title="<?php esc_attr_e( 'Select Theme', 'wpmastertoolkit' ); ?>" class="btn-outline-secondary border-start-0 d-none d-lg-block"><option>-- <?php esc_html_e( 'Select Theme', 'wpmastertoolkit' ); ?> --</option></select>
					<select id="js-ace-fontSize" data-type="fontSize" title="<?php esc_attr_e( 'Select Font Size', 'wpmastertoolkit' ); ?>" class="btn-outline-secondary border-start-0 d-none d-lg-block"><option>-- <?php esc_html_e( 'Select Font Size', 'wpmastertoolkit' ); ?> --</option></select>
				</div>
			</div>
		</div>
		<div class="edit-file-actions col-xs-12 col-sm-7 col-lg-6 text-end pt-1">
			<a title="<?php esc_attr_e( 'Back', 'wpmastertoolkit' ); ?>" class="btn btn-sm btn-outline-primary" href="?page=wp-mastertoolkit-settings-file-manager&p=<?php echo esc_attr( urlencode( trim( $this->FM_PATH ) ) ); ?>&amp;view=<?php echo esc_attr( urlencode( $file ) ); ?>"><i class="fa fa-reply-all"></i> <?php esc_html_e( 'Back', 'wpmastertoolkit' ); ?></a>
			<a title="<?php esc_attr_e( 'BackUp', 'wpmastertoolkit' ); ?>" class="btn btn-sm btn-outline-primary" href="javascript:void(0);" onclick="backup('<?php echo esc_attr( urlencode( trim( $this->FM_PATH ) ) ); ?>','<?php echo esc_attr( urlencode( $file ) ); ?>')"><i class="fa fa-database"></i> <?php esc_html_e( 'BackUp', 'wpmastertoolkit' ); ?></a>
			<?php if ( $is_text ): ?>
				<button type="button" class="btn btn-sm btn-success" id="js-ace-save" name="<?php esc_attr_e( 'Save', 'wpmastertoolkit' ); ?>" data-url="<?php echo esc_attr( $this->fm_enc( $file_url ) ); ?>"><i class="fa fa-floppy-o"></i> <?php esc_html_e( 'Save', 'wpmastertoolkit' ); ?></button>
			<?php endif; ?>
		</div>
	</div>
	<?php if ( $is_text ): ?>
		<div id="editor" contenteditable="true"><?php echo esc_html( htmlspecialchars($content) ); ?></div>
	<?php endif; ?>
</div>
