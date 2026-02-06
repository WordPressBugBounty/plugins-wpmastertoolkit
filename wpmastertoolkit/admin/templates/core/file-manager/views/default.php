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
					<td class="border-0" data-sort><a href="?page=wp-mastertoolkit-settings-file-manager&token=<?php echo esc_attr( $this->TOKEN ); ?>&p=<?php echo esc_attr( urlencode( $this->PARENT_PATH ) ); ?>"><i class="fa fa-chevron-circle-left go-back"></i> ..</a></td>
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
			$wpmtk_ii = 3399;
			foreach ( $folders as $wpmtk_f ) {
				$wpmtk_folder_path    = $this->PATH . '/' . $wpmtk_f;
				$wpmtk_is_link        = is_link( $wpmtk_folder_path );
				$wpmtk_img            = $wpmtk_is_link ? 'icon-link_folder' : 'fa fa-folder-o';
				$wpmtk_modif_raw      = filemtime( $wpmtk_folder_path );
				$wpmtk_modif          = wp_date( "m/d/Y g:i A", $wpmtk_modif_raw );
				$wpmtk_date_sorting   = strtotime( wp_date( "F d Y H:i:s.", $wpmtk_modif_raw ) );
				$wpmtk_filesize_raw   = "";
				$wpmtk_filesize       = 'Folder';
				$wpmtk_perms          = substr( decoct( fileperms( $wpmtk_folder_path ) ), -4 );
				if ( function_exists('posix_getpwuid') && function_exists('posix_getgrgid') ) {
					$wpmtk_owner  = posix_getpwuid( fileowner( $wpmtk_folder_path ) );
					$wpmtk_group  = posix_getgrgid( filegroup( $wpmtk_folder_path ) );

					if ( $wpmtk_owner === false ) {
						$wpmtk_owner = array( 'name' => '?' );
					}
					if ( $wpmtk_group === false ) {
						$wpmtk_group = array( 'name' => '?' );
					}
				} else {
					$wpmtk_owner = array( 'name' => '?' );
					$wpmtk_group = array( 'name' => '?' );
				}

				?>
				<tr>
					<td class="custom-checkbox-td">
						<div class="custom-control custom-checkbox">
							<input type="checkbox" class="custom-control-input" id="<?php echo esc_attr( $wpmtk_ii ); ?>" name="file[]" value="<?php echo esc_attr( $this->fm_enc( $wpmtk_f ) ); ?>">
							<label class="custom-control-label" for="<?php echo esc_attr( $wpmtk_ii ); ?>"></label>
						</div>
					</td>
					<td data-sort=<?php echo esc_attr( $this->fm_convert_win( $this->fm_enc( $wpmtk_f ) ) ); ?>>
						<div class="filename">
							<a href="?page=wp-mastertoolkit-settings-file-manager&token=<?php echo esc_attr( $this->TOKEN ); ?>&p=<?php echo esc_attr( urlencode( trim( $this->FM_PATH . '/' . $wpmtk_f, '/' ) ) ); ?>">
								<i class="<?php echo esc_attr( $wpmtk_img ); ?>"></i>
								<?php echo esc_attr( $this->fm_convert_win( $this->fm_enc( $wpmtk_f ) ) ); ?>
							</a>
							<?php echo ($wpmtk_is_link ? ' &rarr; <i>' . esc_html( readlink( $wpmtk_folder_path ) ) . '</i>' : '') ?>
						</div>
					</td>
					<td data-order="a-<?php echo esc_attr( str_pad( $wpmtk_filesize_raw, 18, "0", STR_PAD_LEFT ) );?>">
						<?php echo esc_html( $wpmtk_filesize ); ?>
					</td>
					<td data-order="a-<?php echo esc_attr( $wpmtk_date_sorting );?>"><?php echo esc_html( $wpmtk_modif ); ?></td>
					<?php if ( ! $this->FM_IS_WIN ) : ?>
						<td><a title="<?php esc_attr_e( 'Change Permissions', 'wpmastertoolkit' ); ?>" href="?page=wp-mastertoolkit-settings-file-manager&token=<?php echo esc_attr( $this->TOKEN ); ?>&p=<?php echo esc_attr( urlencode( $this->FM_PATH ) ); ?>&amp;chmod=<?php echo urlencode($wpmtk_f) ?>"><?php echo esc_html( $wpmtk_perms ); ?></a></td>
						<td><?php echo esc_html( $wpmtk_owner['name'] . ':' . $wpmtk_group['name'] ); ?></td>
					<?php endif; ?>
					<td class="inline-actions">
						<a title="<?php esc_attr_e( 'Delete', 'wpmastertoolkit' ); ?>" href="?page=wp-mastertoolkit-settings-file-manager&token=<?php echo esc_attr( $this->TOKEN ); ?>&p=<?php echo esc_attr( urlencode( $this->FM_PATH ) ); ?>&amp;del=<?php echo esc_attr( urlencode( $wpmtk_f ) ); ?>" onclick="confirmDailog(event, '1028', '<?php esc_attr_e( 'Delete Folder', 'wpmastertoolkit' ); ?>','<?php echo esc_attr( urlencode( $wpmtk_f ) ); ?>', this.href);"> <i class="fa fa-trash-o" aria-hidden="true"></i></a>
						<a title="<?php esc_attr_e( 'Rename', 'wpmastertoolkit' ); ?>" href="#" onclick="rename('<?php echo esc_attr( $this->fm_enc( addslashes( $this->FM_PATH ) ) ); ?>', '<?php echo esc_attr( $this->fm_enc( addslashes( $wpmtk_f ) ) ); ?>');return false;"><i class="fa fa-pencil-square-o" aria-hidden="true"></i></a>
						<a title="<?php esc_attr_e( 'CopyTo', 'wpmastertoolkit' ); ?>" href="?page=wp-mastertoolkit-settings-file-manager&token=<?php echo esc_attr( $this->TOKEN ); ?>&p=&amp;copy=<?php echo esc_attr( urlencode( trim( $this->FM_PATH . '/' . $wpmtk_f, '/' ) ) ); ?>"><i class="fa fa-files-o" aria-hidden="true"></i></a>
						<a title="<?php esc_attr_e( 'DirectLink', 'wpmastertoolkit' ); ?>" href="<?php echo esc_attr( $this->fm_enc( $this->FM_ROOT_URL . ( $this->FM_PATH != '' ? '/' . $this->FM_PATH : '') . '/' . $wpmtk_f . '/') ); ?>" target="_blank"><i class="fa fa-link" aria-hidden="true"></i></a>
					</td>
				</tr>
				<?php
				flush();
				$wpmtk_ii++;
			}
			$wpmtk_ik = 6070;
			foreach ( $files as $wpmtk_f ) {
				$wpmtk_file_path      = $this->PATH . '/' . $wpmtk_f;
				$wpmtk_is_link        = is_link( $wpmtk_file_path );
				$wpmtk_img            = $wpmtk_is_link ? 'fa fa-file-text-o' : $this->fm_get_file_icon_class( $wpmtk_file_path );
				$wpmtk_modif_raw      = filemtime( $wpmtk_file_path );
				$wpmtk_modif          = wp_date( "m/d/Y g:i A", $wpmtk_modif_raw );
				$wpmtk_date_sorting   = strtotime( wp_date( "F d Y H:i:s.", $wpmtk_modif_raw ) );
				$wpmtk_filesize_raw   = $this->fm_get_size( $wpmtk_file_path );
				$wpmtk_filesize       = $this->fm_get_filesize( $wpmtk_filesize_raw );
				$wpmtk_filelink       = '?page=wp-mastertoolkit-settings-file-manager&token='. $this->TOKEN .'&p=' . urlencode( $this->FM_PATH ) . '&amp;view=' . urlencode( $wpmtk_f );
				//phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
				$all_files_size += $wpmtk_filesize_raw;
				$wpmtk_perms          = substr( decoct( fileperms( $wpmtk_file_path )), -4 );
				$wpmtk_ext            = strtolower( pathinfo( $wpmtk_file_path, PATHINFO_EXTENSION ) );
				$wpmtk_mime_type      = $this->fm_get_mime_type( $wpmtk_file_path );
				$wpmtk_is_text        = false;
				if ( in_array( $wpmtk_ext, $this->fm_get_text_exts() ) || substr( $wpmtk_mime_type, 0, 4 ) == 'text' || in_array( $wpmtk_mime_type, $this->fm_get_text_mimes() ) ) {
					$wpmtk_is_text = true;
				}

				if ( function_exists('posix_getpwuid') && function_exists('posix_getgrgid') ) {
					$wpmtk_owner = posix_getpwuid( fileowner( $wpmtk_file_path ) );
					$wpmtk_group = posix_getgrgid( filegroup( $wpmtk_file_path ) );
					if ( $wpmtk_owner === false ) {
						$wpmtk_owner = array( 'name' => '?' );
					}
					if ( $wpmtk_group === false ) {
						$wpmtk_group = array( 'name' => '?' );
					}
				} else {
					$wpmtk_owner = array( 'name' => '?' );
					$wpmtk_group = array( 'name' => '?' );
				}
				?>
				<tr>
					<td class="custom-checkbox-td">
						<div class="custom-control custom-checkbox">
							<input type="checkbox" class="custom-control-input" id="<?php echo esc_attr( $wpmtk_ik ); ?>" name="file[]" value="<?php echo esc_attr( $this->fm_enc( $wpmtk_f ) ); ?>">
							<label class="custom-control-label" for="<?php echo esc_attr( $wpmtk_ik ); ?>"></label>
						</div>
					</td>
					<td data-sort=<?php echo esc_attr( $this->fm_enc( $wpmtk_f ) ); ?>>
						<div class="filename">
							<?php if ( in_array( strtolower( pathinfo( $wpmtk_f, PATHINFO_EXTENSION ) ), array('gif', 'jpg', 'jpeg', 'png', 'bmp', 'ico', 'svg', 'webp', 'avif'))): ?>
								<?php $wpmtk_imagePreview = $this->fm_enc( $this->FM_ROOT_URL . ( $this->FM_PATH != '' ? '/' . $this->FM_PATH : '') . '/' . $wpmtk_f ); ?>
								<a href="<?php echo esc_attr( $wpmtk_filelink ); ?>" data-preview-image="<?php echo esc_attr( $wpmtk_imagePreview ); ?>" title="<?php echo esc_attr( $this->fm_enc( $wpmtk_f ) ); ?>">
							<?php else: ?>
								<a href="<?php echo esc_attr( $wpmtk_filelink ); ?>" title="<?php echo esc_attr( $wpmtk_f ); ?>">
							<?php endif; ?>
								<i class="<?php echo esc_attr( $wpmtk_img ); ?>"></i> <?php echo esc_html( $this->fm_convert_win( $this->fm_enc( $wpmtk_f ) ) ); ?>
							</a>
							<?php echo($wpmtk_is_link ? ' &rarr; <i>' . esc_html( readlink( $wpmtk_file_path ) ) . '</i>' : '') ?>
						</div>
					</td>
					<td data-order="b-<?php echo esc_attr( str_pad( $wpmtk_filesize_raw, 18, "0", STR_PAD_LEFT ) ); ?>">
						<span title="<?php printf('%s bytes', esc_attr( $wpmtk_filesize_raw ) ); ?>"><?php echo esc_html( $wpmtk_filesize ); ?></span>
					</td>
					<td data-order="b-<?php echo esc_attr( $wpmtk_date_sorting );?>"><?php echo esc_html( $wpmtk_modif ); ?></td>
					<?php if ( ! $this->FM_IS_WIN ): ?>
						<td><a title="<?php esc_html_e( 'Change Permissions', 'wpmastertoolkit' ); ?>" href="?page=wp-mastertoolkit-settings-file-manager&token=<?php echo esc_attr( $this->TOKEN ); ?>&p=<?php echo esc_attr( urlencode( $this->FM_PATH ) ); ?>&amp;chmod=<?php echo esc_attr( urlencode( $wpmtk_f ) ); ?>"><?php echo esc_html( $wpmtk_perms ); ?></a></td>
						<td><?php echo esc_html( $this->fm_enc( $wpmtk_owner['name'] . ':' . $wpmtk_group['name'] ) ); ?></td>
					<?php endif; ?>
					<td class="inline-actions">
						<a title="<?php esc_html_e( 'Delete', 'wpmastertoolkit' ); ?>" href="?page=wp-mastertoolkit-settings-file-manager&token=<?php echo esc_attr( $this->TOKEN ); ?>&p=<?php echo esc_attr( urlencode( $this->FM_PATH ) ); ?>&amp;del=<?php echo esc_attr( urlencode( $wpmtk_f ) ); ?>" onclick="confirmDailog(event, 1209, '<?php esc_html_e( 'Delete File', 'wpmastertoolkit' ); ?>','<?php echo esc_attr( urlencode( $wpmtk_f ) ); ?>', this.href);"> <i class="fa fa-trash-o"></i></a>
						<?php if ( $wpmtk_is_text ): ?>
						<a title="<?php esc_html_e( 'Edit', 'wpmastertoolkit' ); ?>" href="?page=wp-mastertoolkit-settings-file-manager&token=<?php echo esc_attr( $this->TOKEN ); ?>&p=<?php echo esc_attr( urlencode( trim( $this->FM_PATH ) ) ); ?>&amp;edit=<?php echo esc_attr( urlencode( $wpmtk_f ) ); ?>"><i class="fa fa-pencil-square-o"></i></a>
						<?php endif; ?>
						<a title="<?php esc_html_e( 'Rename', 'wpmastertoolkit' ); ?>" href="#" onclick="rename('<?php echo esc_attr( $this->fm_enc( addslashes( $this->FM_PATH ) ) ); ?>', '<?php echo esc_attr( $this->fm_enc( addslashes( $wpmtk_f ) ) ); ?>');return false;"><i class="fa fa-text-width"></i></a>
						<a title="<?php esc_html_e( 'CopyTo', 'wpmastertoolkit' ); ?>" href="?page=wp-mastertoolkit-settings-file-manager&token=<?php echo esc_attr( $this->TOKEN ); ?>&p=<?php echo esc_attr( urlencode( $this->FM_PATH ) ); ?>&amp;copy=<?php echo esc_attr( urlencode( trim( $this->FM_PATH . '/' . $wpmtk_f, '/') ) ); ?>"><i class="fa fa-files-o"></i></a>
						<a title="<?php esc_html_e( 'DirectLink', 'wpmastertoolkit' ); ?>" href="<?php echo esc_attr( $this->fm_enc( $this->FM_ROOT_URL . ( $this->FM_PATH != '' ? '/' . $this->FM_PATH : '' ) . '/' . $wpmtk_f ) ); ?>" target="_blank"><i class="fa fa-link"></i></a>
						<a title="<?php esc_html_e( 'Download', 'wpmastertoolkit' ); ?>" href="?page=wp-mastertoolkit-settings-file-manager&token=<?php echo esc_attr( $this->TOKEN ); ?>&p=<?php echo esc_attr( urlencode( $this->FM_PATH ) ); ?>&amp;dl=<?php echo esc_attr( urlencode( $wpmtk_f ) ); ?>" onclick="confirmDailog(event, 1211, '<?php esc_html_e( 'Download', 'wpmastertoolkit' ); ?>','<?php echo esc_attr( urlencode( $wpmtk_f ) ); ?>', this.href);"><i class="fa fa-download"></i></a>
					</td>
				</tr>
				<?php
				flush();
				$wpmtk_ik++;
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
