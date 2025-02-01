<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>

<script type="text/html" id="js-tpl-confirm">
	<div class="modal modal-alert confirmDailog" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" role="dialog" id="confirmDailog-<%this.id%>">
		<div class="modal-dialog" role="document">
			<form class="modal-content rounded-3 shadow" method="post" autocomplete="off" action="<%this.action%>">
				<div class="modal-body p-4 text-center">
					<h5 class="mb-2"><?php esc_html_e( 'Are you sure want to', 'wpmastertoolkit' ); ?> <%this.title%> ?</h5>
					<p class="mb-1"><%this.content%></p>
				</div>
				<div class="modal-footer flex-nowrap p-0">
					<button type="button" class="btn btn-lg btn-link fs-6 text-decoration-none col-6 m-0 rounded-0 border-end" data-bs-dismiss="modal"><?php esc_html_e( 'Cancel', 'wpmastertoolkit' ); ?></button>
					<input type="hidden" name="token" value="<?php echo esc_attr( $this->TOKEN ); ?>">
					<button type="submit" class="btn btn-lg btn-link fs-6 text-decoration-none col-6 m-0 rounded-0" data-bs-dismiss="modal"><strong><?php esc_html_e( 'Okay', 'wpmastertoolkit' ); ?></strong></button>
				</div>
			</form>
		</div>
	</div>
</script>
