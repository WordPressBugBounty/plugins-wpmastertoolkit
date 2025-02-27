<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>

<div class="modal fade" id="createNewItem" tabindex="-1" role="dialog" data-bs-backdrop="static" data-bs-keyboard="false" aria-labelledby="newItemModalLabel" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<form class="modal-content" method="post">
			<div class="modal-header">
				<h5 class="modal-title" id="newItemModalLabel"><i class="fa fa-plus-square fa-fw"></i><?php esc_html_e( 'Create New Item', 'wpmastertoolkit' ) ?></h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<p><label for="newfile"><?php esc_html_e( 'Item Type', 'wpmastertoolkit' ); ?></label></p>
				<div class="form-check form-check-inline">
					<input class="form-check-input" type="radio" name="newfile" id="customRadioInline1" name="newfile" value="file" checked>
					<label class="form-check-label" for="customRadioInline1"><?php esc_html_e( 'File', 'wpmastertoolkit' ); ?></label>
				</div>
				<div class="form-check form-check-inline">
					<input class="form-check-input" type="radio" name="newfile" id="customRadioInline2" value="folder">
					<label class="form-check-label" for="customRadioInline2"><?php esc_html_e( 'Folder', 'wpmastertoolkit' ); ?></label>
				</div>
				<p class="mt-3"><label for="newfilename"><?php esc_html_e( 'Item Name', 'wpmastertoolkit' ); ?></label></p>
				<input type="text" name="newfilename" id="newfilename" value="" class="form-control" placeholder="<?php esc_attr_e( 'Enter here...', 'wpmastertoolkit' ); ?>" required>
			</div>
			<div class="modal-footer">
				<input type="hidden" name="token" value="<?php echo esc_attr( $this->TOKEN ); ?>">
				<button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal"><i class="fa fa-times-circle"></i> <?php esc_html_e( 'Cancel', 'wpmastertoolkit' ); ?></button>
				<button type="submit" class="btn btn-success"><i class="fa fa-check-circle"></i> <?php esc_html_e( 'CreateNow', 'wpmastertoolkit' ); ?></button>
			</div>
		</form>
	</div>
</div>
