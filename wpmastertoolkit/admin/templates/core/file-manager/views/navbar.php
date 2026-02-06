<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>

<nav class="navbar navbar-expand mb-4 main-nav">
	<div class="collapse navbar-collapse" id="navbarSupportedContent">
		<div class="col-xs-6 col-sm-5"><?php echo wp_kses_post( $root_url ); ?></div>
		<div class="col-xs-6 col-sm-7">
			<ul class="navbar-nav justify-content-end">
				<li class="nav-item mr-2">
					<div class="input-group input-group-sm mr-1" style="margin-top:4px;">
						<input type="text" class="form-control" placeholder="<?php esc_attr_e( 'Filter', 'wpmastertoolkit' ); ?>" aria-label="Search" aria-describedby="search-addon2" id="search-addon">
						<div class="input-group-append">
							<span class="input-group-text brl-0 brr-0" id="search-addon2"><i class="fa fa-search"></i></span>
						</div>
						<div class="input-group-append btn-group">
							<span class="input-group-text dropdown-toggle brl-0" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"></span>
							<div class="dropdown-menu dropdown-menu-right">
								<a class="dropdown-item" href="<?php echo esc_attr( $path ? $path : '.' ); ?>" id="js-search-modal" data-bs-toggle="modal" data-bs-target="#searchModal"><?php esc_html_e( 'Advanced Search', 'wpmastertoolkit' ); ?></a>
							</div>
						</div>
					</div>
				</li>
				<li class="nav-item">
					<a title="<?php esc_attr_e( 'Upload', 'wpmastertoolkit' ); ?>" class="nav-link" href="?page=wp-mastertoolkit-settings-file-manager&token=<?php echo esc_attr( $this->TOKEN ); ?>&p=<?php echo esc_attr( urlencode( $path ) ) ?>&amp;upload"><i class="fa fa-cloud-upload" aria-hidden="true"></i> <?php esc_html_e( 'Upload', 'wpmastertoolkit' ); ?></a>
				</li>
				<li class="nav-item">
					<a title="<?php esc_attr_e( 'NewItem', 'wpmastertoolkit' ); ?>" class="nav-link" href="#createNewItem" data-bs-toggle="modal" data-bs-target="#createNewItem"><i class="fa fa-plus-square"></i> <?php esc_html_e( 'New Item', 'wpmastertoolkit' ); ?></a>
				</li>
			</ul>
		</div>
	</div>
</nav>
