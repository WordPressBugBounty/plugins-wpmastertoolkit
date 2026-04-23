<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

/**
 * Redirects list table class.
 * 
 * @since 2.20.0
 */
class WPMastertoolkit_Redirect_Manager_Redirects_List_Table extends WP_List_Table {

	/**
	 * Array of all items.
	 */
	protected $all_items = array();

	/**
	 * Redirect manager class.
	 *
	 * @var WPMastertoolkit_Redirect_Manager
	 */
	protected static $class_redirect_manager;

	/**
	 * Constructor.
	 * 
	 * @since 2.20.0
	 */
	public function __construct() {
		self::$class_redirect_manager = new WPMastertoolkit_Redirect_Manager();

		parent::__construct( array(
			'singular' => 'wpmastertoolkit-redirect',
			'plural'   => 'wpmastertoolkit-redirects',
			'ajax'     => false,
			'screen'   => 'wpmastertoolkit-redirects',
		) );
	}

	/**
	 * Prepares the list table items and arguments.
	 *
	 * @since 2.20.0
	 */
	public function prepare_items() {
		$items           = self::$class_redirect_manager->get_items_redirects();
		$this->all_items = $items;

		$per_page = get_option( 'posts_per_page', 50 );
		$per_page = ( $per_page < 1 ) ? 50 : $per_page;
		$offset   = ( $this->get_pagenum() - 1 ) * $per_page;
		$count    = count( $items );

		$this->items = array_slice( $items, $offset, $per_page );

		$this->set_pagination_args( array(
			'total_items' => $count,
			'per_page'    => $per_page,
			'total_pages' => (int) ceil( $count / $per_page ),
		) );
	}

	/**
	 * Returns an associative array of bulk actions available for this table.
	 *
	 * @since 2.20.0
	 */
	public function get_bulk_actions() {
		return array(
			'bulk_redirects_delete'  => esc_html__( 'Delete', 'wpmastertoolkit' ),
			'bulk_redirects_enable'  => esc_html__( 'Enable', 'wpmastertoolkit' ),
			'bulk_redirects_disable' => esc_html__( 'Disable', 'wpmastertoolkit' ),
		);
	}

	/**
	 * Returns an array of column names for the table.
	 * 
	 * @since 2.20.0
	 */
	public function get_columns() {
		return array(
			'cb'                          => '<input type="checkbox" />',
			'wpmastertoolkit_url'         => esc_html__( 'Url', 'wpmastertoolkit' ),
			'wpmastertoolkit_status'      => esc_html__( 'Status', 'wpmastertoolkit' ),
			'wpmastertoolkit_code'        => esc_html__( 'Code', 'wpmastertoolkit' ),
			'wpmastertoolkit_model'       => esc_html__( 'Model', 'wpmastertoolkit' ),
			'wpmastertoolkit_params'      => esc_html__( 'Query Parameters', 'wpmastertoolkit' ),
			'wpmastertoolkit_logs'        => esc_html__( 'Logs', 'wpmastertoolkit' ),
		);
	}

	/**
	 * Returns the name of the primary column.
	 *
	 * @since 2.20.0
	 */
	protected function get_primary_column_name() {
		return 'wpmastertoolkit_url';
	}

	/**
	 * Generates and returns row action links for the primary column.
	 *
	 * @param array  $item        The redirect item for the current row.
	 * @param string $column_name The current column name.
	 * @param string $primary     The primary column name.
	 * @return string Row action links HTML.
	 *
	 * @since 2.20.0
	 */
	protected function handle_row_actions( $item, $column_name, $primary ) {
		if ( $primary !== $column_name ) {
			return '';
		}

		$id    = $item['id'] ?? '';
		$links = array();

		// Edit.
		$edit_link = add_query_arg(
			array(
				'page'                 => self::$class_redirect_manager->menu_slug,
				'wpmastertoolkit_view' => 'add_redirect',
				'wpmastertoolkit_id'   => rawurlencode( $id ),
			),
			admin_url( 'admin.php' )
		);
		$links[]   = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $edit_link ),
			esc_html__( 'Edit', 'wpmastertoolkit' )
		);

		// Delete.
		$delete_link = add_query_arg(
			array(
				'page'                   => self::$class_redirect_manager->menu_slug,
				'wpmastertoolkit_action' => 'delete_redirect',
				'wpmastertoolkit_id'     => rawurlencode( $id ),
			),
			admin_url( 'admin.php' )
		);
		$delete_link = wp_nonce_url( $delete_link, "wpmastertoolkit-delete-redirect" );
		$links[]     = sprintf(
			'<span class="delete"><a href="%s">%s</a></span>',
			esc_url( $delete_link ),
			esc_html__( 'Delete', 'wpmastertoolkit' )
		);

		return $this->row_actions( $links );
	}

	/**
	 * Outputs the checkbox cell of a table row.
	 *
	 * @since 2.20.0
	 */
	protected function column_cb( $item ) {
		$id = $item['id'] ?? '';

		return sprintf(
			'<input type="checkbox" name="redirect[]" value="%s" />',
			esc_attr( $id )
		);
	}

	/**
	 * Outputs the URL cell of a table row.
	 *
	 * @since 2.20.0
	 */
	protected function column_wpmastertoolkit_url( $item ) {
		$url_from      = $item['url_from'] ?? '';
		$url_from_full = $item['url_from_full'] ?? '';
		$url_to_full   = $item['url_to_full'] ?? '';
		$regex         = $item['regex'] ?? '';

		ob_start();
			?>
			<div class="wpmastertoolkit-redirect-url">
				<p>
				<?php if ( '1' === $regex ) : ?>
					<code class="wpmastertoolkit-redirect-url__from"><?php echo esc_url( $url_from ); ?></code>
				<?php else: ?>
					<a href="<?php echo esc_url( $url_from_full ); ?>"><?php echo esc_url( $url_from ); ?></a>
				<?php endif; ?>
				</p>

				<p><?php echo esc_url( $url_to_full ); ?></p>
			</div>
			<?php
		return ob_get_clean();
	}

	/**
	 * Outputs the status cell of a table row.
	 *
	 * @since 2.20.0
	 */
	protected function column_wpmastertoolkit_status( $item ) {
		$status   = $item['status'] ?? '0';
		$statuses = self::$class_redirect_manager->get_statuses();
		$class    = ( '1' === $status ) ? 'enabled' : 'disabled';

		return '<span class="wpmastertoolkit-redirect-status ' . esc_attr( $class ) . '">' . ( $statuses[ $status ] ?? esc_html__( 'Unknown', 'wpmastertoolkit' ) ) . '</span>';
	}

	/**
	 * Outputs the code cell of a table row.
	 *
	 * @since 2.20.0
	 */
	protected function column_wpmastertoolkit_code( $item ) {
		$code  = $item['code'] ?? '';
		$codes = self::$class_redirect_manager->get_codes();

		return $codes[ $code ] ?? esc_html__( 'Unknown', 'wpmastertoolkit' );
	}

	/**
	 * Outputs the model cell of a table row.
	 *
	 * @since 2.20.0
	 */
	protected function column_wpmastertoolkit_model( $item ) {
		$model  = $item['model'] ?? '';
		$models = self::$class_redirect_manager->get_models();

		return $models[ $model ] ?? esc_html__( 'Unknown', 'wpmastertoolkit' );
	}
	
	/**
	 * Outputs the params cell of a table row.
	 *
	 * @since 2.20.0
	 */
	protected function column_wpmastertoolkit_params( $item ) {
		$param  = $item['params'] ?? '';
		$params = self::$class_redirect_manager->get_params();

		return $params[ $param ] ?? esc_html__( 'Unknown', 'wpmastertoolkit' );
	}

	/**
	 * Outputs the logs cell of a table row.
	 *
	 * @since 2.20.0
	 */
	protected function column_wpmastertoolkit_logs( $item ) {
		$log  = $item['logs'] ?? '';
		$logs = self::$class_redirect_manager->get_logs();

		return $logs[ $log ] ?? esc_html__( 'Unknown', 'wpmastertoolkit' );
	}
}
