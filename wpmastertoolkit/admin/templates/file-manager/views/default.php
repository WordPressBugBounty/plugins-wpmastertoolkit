<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>

<form action="" method="post" class="pt-3">
	<input type="hidden" name="p" value="<?php echo esc_attr( $this->fm_enc( $this->FM_PATH ) ); ?>">
	<input type="hidden" name="group" value="1">
	<input type="hidden" name="token" value="<?php echo esc_attr( $this->TOKEN ); ?>">

	<div class="table-responsive">
		<table class="table table-bordered table-hover table-sm" id="main-table">
			<thead class="thead-white">
				<tr>
					<th style="width:3%" class="custom-checkbox-header">
						<div class="custom-control custom-checkbox">
							<input type="checkbox" class="custom-control-input" id="js-select-all-items" onclick="checkbox_toggle()">
							<label class="custom-control-label" for="js-select-all-items"></label>
						</div>
					</th>
					<th><?php esc_html_e( 'Name', 'wpmastertoolkit' ); ?></th>
					<th><?php esc_html_e( 'Size', 'wpmastertoolkit' ); ?></th>
					<th><?php esc_html_e( 'Modified', 'wpmastertoolkit' ); ?></th>
					<?php if ( ! $this->FM_IS_WIN ) : ?>
					<th><?php esc_html_e( 'Perms', 'wpmastertoolkit' ); ?></th>
					<th><?php esc_html_e( 'Owner', 'wpmastertoolkit' ); ?></th>
					<?php endif; ?>
					<th><?php esc_html_e( 'Actions', 'wpmastertoolkit' ); ?></th>
				</tr>
			</thead>
			<?php 
			if ( $this->PARENT_PATH !== false ) {
				?>
				<tr>
					<td class="nosort"></td>
					<td class="border-0" data-sort><a href="?page=wp-mastertoolkit-settings-file-manager&p=<?php echo esc_attr( urlencode( $this->PARENT_PATH ) ); ?>"><i class="fa fa-chevron-circle-left go-back"></i> ..</a></td>
					<td class="border-0" data-order></td>
					<td class="border-0" data-order></td>
					<td class="border-0"></td>
					<?php if ( ! $this->FM_IS_WIN ) : ?>
					<td class="border-0"></td>
					<td class="border-0"></td>
					<?php endif;?>
				</tr>
				<?php
			}
			$ii = 3399;
			foreach ( $folders as $f ) {
				$folder_path    = $this->PATH . '/' . $f;
				$is_link        = is_link( $folder_path );
				$img            = $is_link ? 'icon-link_folder' : 'fa fa-folder-o';
				$modif_raw      = filemtime( $folder_path );
				$modif          = wp_date( "m/d/Y g:i A", $modif_raw );
				$date_sorting   = strtotime( wp_date( "F d Y H:i:s.", $modif_raw ) );
				$filesize_raw   = "";
				$filesize       = 'Folder';
				$perms          = substr( decoct( fileperms( $folder_path ) ), -4 );
				if ( function_exists('posix_getpwuid') && function_exists('posix_getgrgid') ) {
					$owner  = posix_getpwuid( fileowner( $folder_path ) );
					$group  = posix_getgrgid( filegroup( $folder_path ) );

					if ( $owner === false ) {
						$owner = array( 'name' => '?' );
					}
					if ( $group === false ) {
						$group = array( 'name' => '?' );
					}
				} else {
					$owner = array( 'name' => '?' );
					$group = array( 'name' => '?' );
				}

				?>
				<tr>
					<td class="custom-checkbox-td">
						<div class="custom-control custom-checkbox">
							<input type="checkbox" class="custom-control-input" id="<?php echo esc_attr( $ii ); ?>" name="file[]" value="<?php echo esc_attr( $this->fm_enc( $f ) ); ?>">
							<label class="custom-control-label" for="<?php echo esc_attr( $ii ); ?>"></label>
						</div>
					</td>
					<td data-sort=<?php echo esc_attr( $this->fm_convert_win( $this->fm_enc( $f ) ) ); ?>>
						<div class="filename">
							<a href="?page=wp-mastertoolkit-settings-file-manager&p=<?php echo esc_attr( urlencode( trim( $this->FM_PATH . '/' . $f, '/' ) ) ); ?>">
								<i class="<?php echo esc_attr( $img ); ?>"></i>
								<?php echo esc_attr( $this->fm_convert_win( $this->fm_enc( $f ) ) ); ?>
							</a>
							<?php echo ($is_link ? ' &rarr; <i>' . readlink( $folder_path ) . '</i>' : '') ?>
						</div>
					</td>
					<td data-order="a-<?php echo esc_attr( str_pad( $filesize_raw, 18, "0", STR_PAD_LEFT ) );?>">
						<?php echo esc_html( $filesize ); ?>
					</td>
					<td data-order="a-<?php echo $date_sorting;?>"><?php echo esc_html( $modif ); ?></td>
					<?php if ( ! $this->FM_IS_WIN ) : ?>
						<td><a title="<?php esc_attr_e( 'Change Permissions', 'wpmastertoolkit' ); ?>" href="?page=wp-mastertoolkit-settings-file-manager&p=<?php echo esc_attr( urlencode( $this->FM_PATH ) ); ?>&amp;chmod=<?php echo urlencode($f) ?>"><?php echo esc_html( $perms ); ?></a></td>
						<td><?php echo esc_html( $owner['name'] . ':' . $group['name'] ); ?></td>
					<?php endif; ?>
					<td class="inline-actions">
						<a title="<?php esc_attr_e( 'Delete', 'wpmastertoolkit' ); ?>" href="?page=wp-mastertoolkit-settings-file-manager&p=<?php echo esc_attr( urlencode( $this->FM_PATH ) ); ?>&amp;del=<?php echo esc_attr( urlencode( $f ) ); ?>" onclick="confirmDailog(event, '1028', '<?php esc_attr_e( 'Delete Folder', 'wpmastertoolkit' ); ?>','<?php echo esc_attr( urlencode( $f ) ); ?>', this.href);"> <i class="fa fa-trash-o" aria-hidden="true"></i></a>
						<a title="<?php esc_attr_e( 'Rename', 'wpmastertoolkit' ); ?>" href="#" onclick="rename('<?php echo esc_attr( $this->fm_enc( addslashes( $this->FM_PATH ) ) ); ?>', '<?php echo esc_attr( $this->fm_enc( addslashes( $f ) ) ); ?>');return false;"><i class="fa fa-pencil-square-o" aria-hidden="true"></i></a>
						<a title="<?php esc_attr_e( 'CopyTo', 'wpmastertoolkit' ); ?>" href="?page=wp-mastertoolkit-settings-file-manager&p=&amp;copy=<?php echo esc_attr( urlencode( trim( $this->FM_PATH . '/' . $f, '/' ) ) ); ?>"><i class="fa fa-files-o" aria-hidden="true"></i></a>
						<a title="<?php esc_attr_e( 'DirectLink', 'wpmastertoolkit' ); ?>" href="<?php echo esc_attr( $this->fm_enc( $this->FM_ROOT_URL . ( $this->FM_PATH != '' ? '/' . $this->FM_PATH : '') . '/' . $f . '/') ); ?>" target="_blank"><i class="fa fa-link" aria-hidden="true"></i></a>
					</td>
				</tr>
				<?php
				flush();
				$ii++;
			}
			$ik = 6070;
			foreach ( $files as $f ) {
				$file_path      = $this->PATH . '/' . $f;
				$is_link        = is_link( $file_path );
				$img            = $is_link ? 'fa fa-file-text-o' : $this->fm_get_file_icon_class( $file_path );
				$modif_raw      = filemtime( $file_path );
				$modif          = wp_date( "m/d/Y g:i A", $modif_raw );
				$date_sorting   = strtotime( wp_date( "F d Y H:i:s.", $modif_raw ) );
				$filesize_raw   = $this->fm_get_size( $file_path );
				$filesize       = $this->fm_get_filesize( $filesize_raw );
				$filelink       = '?page=wp-mastertoolkit-settings-file-manager&p=' . urlencode( $this->FM_PATH ) . '&amp;view=' . urlencode( $f );
				$all_files_size += $filesize_raw;
				$perms          = substr( decoct( fileperms( $file_path )), -4 );
				$ext            = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
				$mime_type      = $this->fm_get_mime_type( $file_path );
				$is_text        = false;
				if ( in_array( $ext, $this->fm_get_text_exts() ) || substr( $mime_type, 0, 4 ) == 'text' || in_array( $mime_type, $this->fm_get_text_mimes() ) ) {
					$is_text = true;
				}

				if ( function_exists('posix_getpwuid') && function_exists('posix_getgrgid') ) {
					$owner = posix_getpwuid( fileowner( $file_path ) );
					$group = posix_getgrgid( filegroup( $file_path ) );
					if ( $owner === false ) {
						$owner = array( 'name' => '?' );
					}
					if ( $group === false ) {
						$group = array( 'name' => '?' );
					}
				} else {
					$owner = array( 'name' => '?' );
					$group = array( 'name' => '?' );
				}
				?>
				<tr>
					<td class="custom-checkbox-td">
						<div class="custom-control custom-checkbox">
							<input type="checkbox" class="custom-control-input" id="<?php echo esc_attr( $ik ); ?>" name="file[]" value="<?php echo esc_attr( $this->fm_enc( $f ) ); ?>">
							<label class="custom-control-label" for="<?php echo esc_attr( $ik ); ?>"></label>
						</div>
					</td>
					<td data-sort=<?php echo esc_attr( $this->fm_enc( $f ) ); ?>>
						<div class="filename">
							<?php if ( in_array( strtolower( pathinfo( $f, PATHINFO_EXTENSION ) ), array('gif', 'jpg', 'jpeg', 'png', 'bmp', 'ico', 'svg', 'webp', 'avif'))): ?>
								<?php $imagePreview = $this->fm_enc( $this->FM_ROOT_URL . ( $this->FM_PATH != '' ? '/' . $this->FM_PATH : '') . '/' . $f ); ?>
								<a href="<?php echo esc_attr( $filelink ); ?>" data-preview-image="<?php echo esc_attr( $imagePreview ); ?>" title="<?php echo esc_attr( $this->fm_enc( $f ) ); ?>">
							<?php else: ?>
								<a href="<?php echo esc_attr( $filelink ); ?>" title="<?php echo esc_attr( $f ); ?>">
							<?php endif; ?>
								<i class="<?php echo esc_attr( $img ); ?>"></i> <?php echo esc_html( $this->fm_convert_win( $this->fm_enc( $f ) ) ); ?>
							</a>
							<?php echo($is_link ? ' &rarr; <i>' . readlink( $file_path ) . '</i>' : '') ?>
						</div>
					</td>
					<td data-order="b-<?php echo esc_attr( str_pad( $filesize_raw, 18, "0", STR_PAD_LEFT ) ); ?>">
						<span title="<?php printf('%s bytes', esc_attr( $filesize_raw ) ); ?>"><?php echo esc_html( $filesize ); ?></span>
					</td>
					<td data-order="b-<?php echo esc_attr( $date_sorting );?>"><?php echo esc_html( $modif ); ?></td>
					<?php if ( ! $this->FM_IS_WIN ): ?>
						<td><a title="<?php esc_html_e( 'Change Permissions', 'wpmastertoolkit' ); ?>" href="?page=wp-mastertoolkit-settings-file-manager&p=<?php echo esc_attr( urlencode( $this->FM_PATH ) ); ?>&amp;chmod=<?php echo esc_attr( urlencode( $f ) ); ?>"><?php echo esc_html( $perms ); ?></a></td>
						<td><?php echo esc_html( $this->fm_enc( $owner['name'] . ':' . $group['name'] ) ); ?></td>
					<?php endif; ?>
					<td class="inline-actions">
						<a title="<?php esc_html_e( 'Delete', 'wpmastertoolkit' ); ?>" href="?page=wp-mastertoolkit-settings-file-manager&p=<?php echo esc_attr( urlencode( $this->FM_PATH ) ); ?>&amp;del=<?php echo esc_attr( urlencode( $f ) ); ?>" onclick="confirmDailog(event, 1209, '<?php esc_html_e( 'Delete File', 'wpmastertoolkit' ); ?>','<?php echo esc_attr( urlencode( $f ) ); ?>', this.href);"> <i class="fa fa-trash-o"></i></a>
						<?php if ( $is_text ): ?>
						<a title="<?php esc_html_e( 'Edit', 'wpmastertoolkit' ); ?>" href="?page=wp-mastertoolkit-settings-file-manager&p=<?php echo esc_attr( urlencode( trim( $this->FM_PATH ) ) ); ?>&amp;edit=<?php echo esc_attr( urlencode( $f ) ); ?>"><i class="fa fa-pencil-square-o"></i></a>
						<?php endif; ?>
						<a title="<?php esc_html_e( 'Rename', 'wpmastertoolkit' ); ?>" href="#" onclick="rename('<?php echo esc_attr( $this->fm_enc( addslashes( $this->FM_PATH ) ) ); ?>', '<?php echo esc_attr( $this->fm_enc( addslashes( $f ) ) ); ?>');return false;"><i class="fa fa-text-width"></i></a>
						<a title="<?php esc_html_e( 'CopyTo', 'wpmastertoolkit' ); ?>" href="?page=wp-mastertoolkit-settings-file-manager&p=<?php echo esc_attr( urlencode( $this->FM_PATH ) ); ?>&amp;copy=<?php echo esc_attr( urlencode( trim( $this->FM_PATH . '/' . $f, '/') ) ); ?>"><i class="fa fa-files-o"></i></a>
						<a title="<?php esc_html_e( 'DirectLink', 'wpmastertoolkit' ); ?>" href="<?php echo esc_attr( $this->fm_enc( $this->FM_ROOT_URL . ( $this->FM_PATH != '' ? '/' . $this->FM_PATH : '' ) . '/' . $f ) ); ?>" target="_blank"><i class="fa fa-link"></i></a>
						<a title="<?php esc_html_e( 'Download', 'wpmastertoolkit' ); ?>" href="?page=wp-mastertoolkit-settings-file-manager&p=<?php echo esc_attr( urlencode( $this->FM_PATH ) ); ?>&amp;dl=<?php echo esc_attr( urlencode( $f ) ); ?>" onclick="confirmDailog(event, 1211, '<?php esc_html_e( 'Download', 'wpmastertoolkit' ); ?>','<?php echo esc_attr( urlencode( $f ) ); ?>', this.href);"><i class="fa fa-download"></i></a>
					</td>
				</tr>
				<?php
				flush();
				$ik++;
			}

			if ( empty($folders) && empty($files) ) {
				?>
				<tfoot>
					<tr>
						<td></td>
						<td colspan="<?php echo ! $this->FM_IS_WIN ? '6' : '4' ?>"><em><?php esc_html_e( 'Folder is empty', 'wpmastertoolkit' ); ?></em></td>
					</tr>
				</tfoot>
				<?php
			} else {
				?>
				<tfoot>
					<tr>
						<td class="gray" colspan="<?php echo ! $this->FM_IS_WIN ? '7' : '5'; ?>">
							<?php esc_html_e( 'FullSize', 'wpmastertoolkit' ) ?>: <span class="badge text-bg-light border-radius-0"><?php echo esc_html( $this->fm_get_filesize( $all_files_size ) ); ?></span>
							<?php esc_html_e( 'File', 'wpmastertoolkit' ) ?>: <span class="badge text-bg-light border-radius-0"><?php echo esc_html( $num_files ); ?></span>
							<?php esc_html_e( 'Folder', 'wpmastertoolkit' ) ?>: <span class="badge text-bg-light border-radius-0"><?php echo esc_html( $num_folders ); ?></span>
						</td>
					</tr>
				</tfoot>
				<?php
			}
			?>
		</table>
	</div>

	<div class="row">
		<div class="col-xs-12 col-sm-9">
			<ul class="list-inline footer-action">
				<li class="list-inline-item">
					<a href="#/select-all" class="btn btn-small btn-outline-primary btn-2" onclick="select_all();return false;"><i class="fa fa-check-square"></i> <?php esc_html_e( 'Select All', 'wpmastertoolkit' ); ?></a>
				</li>
				<li class="list-inline-item">
					<a href="#/unselect-all" class="btn btn-small btn-outline-primary btn-2" onclick="unselect_all();return false;"><i class="fa fa-window-close"></i> <?php esc_html_e( 'UnSelect All', 'wpmastertoolkit' ); ?></a>
				</li>
				<li class="list-inline-item">
					<a href="#/invert-all" class="btn btn-small btn-outline-primary btn-2" onclick="invert_all();return false;"><i class="fa fa-th-list"></i> <?php esc_html_e( 'Invert Selection', 'wpmastertoolkit' ); ?></a>
				</li>
				<li class="list-inline-item">
					<input type="submit" class="hidden" name="delete" id="a-delete" value="Delete" onclick="return confirm('<?php esc_html_e( 'Delete selected files and folders?', 'wpmastertoolkit' ); ?>');">
					<a href="javascript:document.getElementById('a-delete').click();" class="btn btn-small btn-outline-primary btn-2"><i class="fa fa-trash"></i> <?php esc_html_e( 'Delete', 'wpmastertoolkit' ); ?></a>
				</li>
				<li class="list-inline-item">
					<input type="submit" class="hidden" name="zip" id="a-zip" value="zip" onclick="return confirm('<?php esc_html_e( 'Create archive?', 'wpmastertoolkit' ); ?>');">
					<a href="javascript:document.getElementById('a-zip').click();" class="btn btn-small btn-outline-primary btn-2"><i class="fa fa-file-archive-o"></i> <?php esc_html_e( 'Zip', 'wpmastertoolkit' ); ?></a>
				</li>
				<li class="list-inline-item">
					<input type="submit" class="hidden" name="tar" id="a-tar" value="tar" onclick="return confirm('<?php esc_attr_e( 'Create archive?', 'wpmastertoolkit' ); ?>');">
					<a href="javascript:document.getElementById('a-tar').click();" class="btn btn-small btn-outline-primary btn-2"><i class="fa fa-file-archive-o"></i> <?php esc_html_e( 'Tar', 'wpmastertoolkit' ); ?></a>
				</li>
				<li class="list-inline-item">
					<input type="submit" class="hidden" name="copy" id="a-copy" value="Copy">
					<a href="javascript:document.getElementById('a-copy').click();" class="btn btn-small btn-outline-primary btn-2"><i class="fa fa-files-o"></i> <?php esc_html_e( 'Copy', 'wpmastertoolkit' ); ?></a>
				</li>
			</ul>
		</div>
	</div>
</form>
