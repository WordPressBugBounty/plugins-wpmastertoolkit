<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>

<div class="card mb-2 fm-upload-wrapper">
	<div class="card-header">
		<ul class="nav nav-tabs card-header-tabs" id="myTab" role="tablist">
			<li class="nav-item" role="presentation">
				<button class="nav-link active" id="fileUploader-tab" data-bs-toggle="tab" data-bs-target="#fileUploader" type="button" role="tab" aria-controls="fileUploader" aria-selected="true"><i class="fa fa-arrow-circle-o-up"></i> <?php esc_html_e( 'Upload Files', 'wpmastertoolkit' ); ?></button>
			</li>
		</ul>
	</div>
	<div class="card-body">
		<p class="card-text">
			<a href="?page=wp-mastertoolkit-settings-file-manager&p=<?php echo esc_attr( $this->FM_PATH ); ?>" class="float-right"><i class="fa fa-chevron-circle-left go-back"></i> <?php esc_html_e( 'Back', 'wpmastertoolkit' ); ?></a>
			<strong><?php esc_html_e( 'Destination Folder', 'wpmastertoolkit' ); ?></strong>: <?php echo esc_html( $this->fm_enc( $this->fm_convert_win( $this->FM_PATH ) ) ); ?>
		</p>
		<div class="tab-content" id="myTabContent">
			<div class="tab-pane fade show active" id="fileUploader" role="tabpanel" aria-labelledby="fileUploader-tab">
				<form action="<?php echo esc_attr( htmlspecialchars( $this->FM_SELF_URL ) ) . '?page=wp-mastertoolkit-settings-file-manager&p=' . esc_attr( $this->fm_enc( $this->FM_PATH ) ); ?>" class="dropzone card-tabs-container" id="fileUploader" enctype="multipart/form-data">
					<input type="hidden" name="p" value="<?php echo esc_attr( $this->fm_enc( $this->FM_PATH ) ); ?>">
					<input type="hidden" name="fullpath" id="fullpath" value="<?php echo esc_attr( $this->fm_enc( $this->FM_PATH ) ); ?>">
					<input type="hidden" name="token" value="<?php echo esc_attr( $this->TOKEN ); ?>">
					<div class="fallback">
						<input name="file" type="file" multiple/>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>
