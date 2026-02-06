<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>

<div class="path">
	<div class="card">
		<div class="card-header">
			<h6><?php esc_html_e( 'Copying', 'wpmastertoolkit'); ?></h6>
		</div>
		<div class="card-body">
			<form action="" method="post">
				<input type="hidden" name="p" value="<?php echo esc_attr( $this->fm_enc( $this->FM_PATH ) ); ?>">
				<input type="hidden" name="finish" value="1">
				<?php foreach ( $copy_files as $wpmtk_cf ) : ?>
					<input type="hidden" name="file[]" value="<?php echo esc_attr( $this->fm_enc( $wpmtk_cf ) ); ?>"><?php echo PHP_EOL;?>
				<?php endforeach;?>
				<p class="break-word">
					<strong><?php esc_html_e( 'Files', 'wpmastertoolkit' ); ?></strong>: <b><?php echo wp_kses_post( implode( '</b>, <b>', $copy_files ) ); ?></b>
				</p>
				<p class="break-word">
					<strong><?php esc_html_e( 'SourceFolder', 'wpmastertoolkit' ); ?></strong>: <?php echo esc_html( $this->fm_enc( $this->fm_convert_win( $this->FM_ROOT_PATH . '/' . $this->FM_PATH ) ) ); ?><br>
					<label for="inp_copy_to"><strong><?php esc_html_e( 'DestinationFolder', 'wpmastertoolkit' ); ?></strong>:</label>
					<?php echo esc_html( $this->FM_ROOT_PATH ); ?>/<input type="text" name="copy_to" id="inp_copy_to" value="<?php echo esc_attr( $this->fm_enc( $this->FM_PATH ) ); ?>">
				</p>
				<p class="custom-checkbox custom-control">
					<input type="checkbox" name="move" value="1" id="js-move-files" class="custom-control-input"><label for="js-move-files" class="custom-control-label ms-2"> <?php esc_html_e( 'Move', 'wpmastertoolkit' ); ?></label>
				</p>
				<p>
					<b><a href="?page=wp-mastertoolkit-settings-file-manager&token=<?php echo esc_attr( $this->TOKEN ); ?>&p=<?php echo esc_attr( urlencode( $this->FM_PATH ) ); ?>" class="btn btn-outline-danger"><i class="fa fa-times-circle"></i> <?php esc_html_e( 'Cancel', 'wpmastertoolkit' ); ?></a></b>&nbsp;
					<input type="hidden" name="token" value="<?php echo esc_attr( $this->TOKEN ); ?>">
					<button type="submit" class="btn btn-success"><i class="fa fa-check-circle"></i> <?php esc_html_e( 'Copy', 'wpmastertoolkit' ); ?></button>
				</p>
			</form>
		</div>
	</div>
</div>
