<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>

<div class="path">
	<p><b><?php esc_html_e( 'Copying', 'wpmastertoolkit' ); ?></b></p>
	<p class="break-word">
		<strong><?php esc_html_e( 'Source path', 'wpmastertoolkit' ) ?>: </strong><?php echo esc_html( $this->fm_enc( $this->fm_convert_win( $this->FM_ROOT_PATH . '/' . $copy ) ) ); ?><br>
		<strong><?php esc_html_e( 'Destination folder', 'wpmastertoolkit' ) ?>: </strong><?php echo esc_html( $this->fm_enc( $this->fm_convert_win( $this->FM_ROOT_PATH . '/' . $this->FM_PATH ) ) ); ?>
	</p>
	<p>
		<b><a href="?page=wp-mastertoolkit-settings-file-manager&token=<?php echo esc_attr( $this->TOKEN ); ?>&p=<?php echo urlencode( $this->FM_PATH ); ?>&amp;copy=<?php echo urlencode( $copy ) ?>&amp;finish=1"><i class="fa fa-check-circle"></i> <?php esc_html_e( 'Copy', 'wpmastertoolkit' ); ?></a></b> &nbsp;
		<b><a href="?page=wp-mastertoolkit-settings-file-manager&token=<?php echo esc_attr( $this->TOKEN ); ?>&p=<?php echo urlencode( $this->FM_PATH ); ?>&amp;copy=<?php echo urlencode($copy) ?>&amp;finish=1&amp;move=1"><i class="fa fa-check-circle"></i> <?php esc_html_e( 'Move', 'wpmastertoolkit' ); ?></a></b> &nbsp;
		<b><a href="?page=wp-mastertoolkit-settings-file-manager&token=<?php echo esc_attr( $this->TOKEN ); ?>&p=<?php echo urlencode( $this->FM_PATH ); ?>" class="text-danger"><i class="fa fa-times-circle"></i> <?php esc_html_e( 'Cancel', 'wpmastertoolkit' ); ?></a></b>
	</p>
	<p><i><?php esc_html_e( 'Select folder', 'wpmastertoolkit' ); ?></i></p>
	<ul class="folders break-word">
		<?php
		if ( $this->PARENT_PATH !== false ) {
			?>
				<li><a href="?page=wp-mastertoolkit-settings-file-manager&token=<?php echo esc_attr( $this->TOKEN ); ?>&p=<?php echo urlencode( $this->PARENT_PATH ); ?>&amp;copy=<?php echo urlencode( $copy ); ?>"><i class="fa fa-chevron-circle-left"></i> ..</a></li>
			<?php
		}
		foreach ( $folders as $wpmtk_f ) {
			?>
				<li><a href="?page=wp-mastertoolkit-settings-file-manager&token=<?php echo esc_attr( $this->TOKEN ); ?>&p=<?php echo urlencode( trim( $this->FM_PATH . '/' . $wpmtk_f, '/' ) ); ?>&amp;copy=<?php echo urlencode( $copy ); ?>"><i class="fa fa-folder-o"></i> <?php echo esc_html( $this->fm_convert_win( $wpmtk_f ) ); ?></a></li>
			<?php
		}
		?>
	</ul>
</div>
