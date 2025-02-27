<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>

<div class="modal fade" id="searchModal" tabindex="-1" role="dialog" aria-labelledby="searchModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title col-10" id="searchModalLabel">
					<div class="input-group mb-3">
						<input type="text" class="form-control" placeholder="<?php esc_attr_e( 'Search files', 'wpmastertoolkit' ); ?>" aria-label="Search" aria-describedby="search-addon3" id="advanced-search" autofocus required>
						<span class="input-group-text" id="search-addon3"><i class="fa fa-search"></i></span>
					</div>
				</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<form action="" method="post">
					<div class="lds-facebook"><div></div><div></div><div></div></div>
					<ul id="search-wrapper">
						<p class="m-2"><?php esc_html_e( 'Search file in folder and subfolders...', 'wpmastertoolkit' ); ?></p>
					</ul>
				</form>
			</div>
		</div>
	</div>
</div>
