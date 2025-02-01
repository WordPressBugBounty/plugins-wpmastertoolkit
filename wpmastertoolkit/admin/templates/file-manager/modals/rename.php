<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>

<div class="modal modal-alert" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" role="dialog" id="renameDailog">
	<div class="modal-dialog" role="document">
		<form class="modal-content rounded-3 shadow" method="post" autocomplete="off">
			<div class="modal-body p-4 text-center">
				<h5 class="mb-3"><?php esc_html_e( 'Are you sure want to rename?', 'wpmastertoolkit' ); ?></h5>
				<p class="mb-1">
					<input type="text" name="rename_to" id="js-rename-to" class="form-control" placeholder="<?php esc_attr_e( 'Enter new file name', 'wpmastertoolkit' ); ?>" required>
					<input type="hidden" name="token" value="<?php echo esc_attr( $this->TOKEN ); ?>">
					<input type="hidden" name="rename_from" id="js-rename-from">
				</p>
			</div>
			<div class="modal-footer flex-nowrap p-0">
				<button type="button" class="btn btn-lg btn-link fs-6 text-decoration-none col-6 m-0 rounded-0 border-end" data-bs-dismiss="modal"><?php esc_html_e( 'Cancel', 'wpmastertoolkit' ); ?></button>
				<button type="submit" class="btn btn-lg btn-link fs-6 text-decoration-none col-6 m-0 rounded-0"><strong><?php esc_html_e( 'Okay', 'wpmastertoolkit' ); ?></strong></button>
			</div>
		</form>
	</div>
</div>