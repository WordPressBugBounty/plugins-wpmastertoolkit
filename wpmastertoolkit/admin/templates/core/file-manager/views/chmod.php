<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>

<div class="path">
	<div class="card mb-2">
		<h6 class="card-header"><?php esc_html_e( 'Change Permissions', 'wpmastertoolkit' ); ?></h6>
		<div class="card-body">
			<p class="card-text">
				<?php esc_html_e( 'Full Path', 'wpmastertoolkit' ) ?> :
				<?php echo esc_html( $this->fm_enc( $this->fm_convert_win( $file_path ) ) ); ?><br>
			</p>
			<form action="" method="post">
				<input type="hidden" name="p" value="<?php echo esc_attr( $this->fm_enc( $this->FM_PATH ) ); ?>">
				<input type="hidden" name="chmod" value="<?php echo esc_html( $this->fm_enc( $file ) ); ?>">

				<table class="table compact-table">
					<tr>
						<td></td>
						<td><b><?php esc_html_e( 'Owner', 'wpmastertoolkit' ); ?></b></td>
						<td><b><?php esc_html_e( 'Group', 'wpmastertoolkit' ); ?></b></td>
						<td><b><?php esc_html_e( 'Other', 'wpmastertoolkit' ); ?></b></td>
					</tr>
					<tr>
						<td style="text-align: right"><b><?php esc_html_e( 'Read', 'wpmastertoolkit' ); ?></b></td>
						<td><label><input type="checkbox" name="ur" value="1"<?php echo ($mode & 00400) ? ' checked' : '' ?>></label></td>
						<td><label><input type="checkbox" name="gr" value="1"<?php echo ($mode & 00040) ? ' checked' : '' ?>></label></td>
						<td><label><input type="checkbox" name="or" value="1"<?php echo ($mode & 00004) ? ' checked' : '' ?>></label></td>
					</tr>
					<tr>
						<td style="text-align: right"><b><?php esc_html_e( 'Write', 'wpmastertoolkit' ); ?></b></td>
						<td><label><input type="checkbox" name="uw" value="1"<?php echo ($mode & 00200) ? ' checked' : '' ?>></label></td>
						<td><label><input type="checkbox" name="gw" value="1"<?php echo ($mode & 00020) ? ' checked' : '' ?>></label></td>
						<td><label><input type="checkbox" name="ow" value="1"<?php echo ($mode & 00002) ? ' checked' : '' ?>></label></td>
					</tr>
					<tr>
						<td style="text-align: right"><b><?php esc_html_e( 'Execute', 'wpmastertoolkit' ); ?></b></td>
						<td><label><input type="checkbox" name="ux" value="1"<?php echo ($mode & 00100) ? ' checked' : '' ?>></label></td>
						<td><label><input type="checkbox" name="gx" value="1"<?php echo ($mode & 00010) ? ' checked' : '' ?>></label></td>
						<td><label><input type="checkbox" name="ox" value="1"<?php echo ($mode & 00001) ? ' checked' : '' ?>></label></td>
					</tr>
				</table>
				<p>
					<input type="hidden" name="token" value="<?php echo esc_attr( $this->TOKEN ); ?>">
					<b><a href="?page=wp-mastertoolkit-settings-file-manager&p=<?php echo esc_attr( urlencode( $this->FM_PATH ) ); ?>" class="btn btn-outline-primary"><i class="fa fa-times-circle"></i> <?php esc_html_e( 'Cancel', 'wpmastertoolkit' ); ?></a></b>&nbsp;
					<button type="submit" class="btn btn-success"><i class="fa fa-check-circle"></i> <?php esc_html_e( 'Change', 'wpmastertoolkit' ); ?></button>
				</p>
			</form>
		</div>
	</div>
</div>
