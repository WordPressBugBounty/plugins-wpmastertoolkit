<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

/**
 * Logs list table class.
 * 
 * @since 2.20.0
 */
class WPMastertoolkit_Redirect_Manager_Logs_List_Table extends WP_List_Table {

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
		$items           = self::$class_redirect_manager->get_items_logs();
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
			'bulk_logs_delete' => esc_html__( 'Delete', 'wpmastertoolkit' ),
		);
	}

	/**
	 * Outputs extra controls in the tablenav area (beside the Apply button).
	 *
	 * @param string $which 'top' or 'bottom'.
	 * @since 2.20.0
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which || empty( $this->all_items ) ) {
			return;
		}

		$empty_link = add_query_arg(
			array(
				'page'                   => self::$class_redirect_manager->menu_slug,
				'wpmastertoolkit_view'   => 'logs',
				'wpmastertoolkit_action' => 'empty_logs',
			),
			admin_url( 'admin.php' )
		);
		$empty_link = wp_nonce_url( $empty_link, 'wpmastertoolkit-empty-logs' );

		?>
		<a href="<?php echo esc_url( $empty_link ); ?>" class="button" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to empty all logs? This action cannot be undone.', 'wpmastertoolkit' ); ?>')">
			<?php esc_html_e( 'Empty All Logs', 'wpmastertoolkit' ); ?>
		</a>
		<?php
	}

	/**
	 * Returns an array of column names for the table.
	 * 
	 * @since 2.20.0
	 */
	public function get_columns() {
		return array(
			'cb'                       => '<input type="checkbox" />',
			'wpmastertoolkit_url_from' => esc_html__( 'Source Url', 'wpmastertoolkit' ),
			'wpmastertoolkit_url_to'   => esc_html__( 'Target Url', 'wpmastertoolkit' ),
			'wpmastertoolkit_agent'    => esc_html__( 'User Agent', 'wpmastertoolkit' ),
			'wpmastertoolkit_ip'       => esc_html__( 'IP Address', 'wpmastertoolkit' ),
			'wpmastertoolkit_date'     => esc_html__( 'Time', 'wpmastertoolkit' ),
		);
	}

	/**
	 * Returns the name of the primary column.
	 *
	 * @since 2.20.0
	 */
	protected function get_primary_column_name() {
		return 'wpmastertoolkit_url_from';
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

		// Delete.
		$delete_link = add_query_arg(
			array(
				'page'                   => self::$class_redirect_manager->menu_slug,
				'wpmastertoolkit_view'   => 'logs',
				'wpmastertoolkit_action' => 'delete_log',
				'wpmastertoolkit_id'     => rawurlencode( $id ),
			),
			admin_url( 'admin.php' )
		);
		$delete_link = wp_nonce_url( $delete_link, "wpmastertoolkit-delete-log" );
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
	protected function column_wpmastertoolkit_url_from( $item ) {
		$url_from = $item['url_from'] ?? '';

		ob_start();
			?>
			<div class="wpmastertoolkit-redirect-url">
				<a href="<?php echo esc_url( $url_from ); ?>"><?php echo esc_url( $url_from ); ?></a>
			</div>
			<?php
		return ob_get_clean();
	}
	
	/**
	 * Outputs the URL cell of a table row.
	 *
	 * @since 2.20.0
	 */
	protected function column_wpmastertoolkit_url_to( $item ) {
		$url_to = $item['url_to'] ?? '';

		ob_start();
			?>
			<div class="wpmastertoolkit-redirect-url">
				<a href="<?php echo esc_url( $url_to ); ?>"><?php echo esc_url( $url_to ); ?></a>
			</div>
			<?php
		return ob_get_clean();
	}

	/**
	 * Outputs the agent cell of a table row.
	 *
	 * @since 2.20.0
	 */
	protected function column_wpmastertoolkit_agent( $item ) {
		$agent = $item['agent'] ?? '';

		return esc_html( $agent );
	}

	/**
	 * Outputs the IP cell of a table row.
	 *
	 * @since 2.20.0
	 */
	protected function column_wpmastertoolkit_ip( $item ) {
		$ip = $item['ip'] ?? '';

		return esc_html( $ip );
	}

	/**
	 * Outputs the date cell of a table row.
	 *
	 * @since 2.20.0
	 */
	protected function column_wpmastertoolkit_date( $item ) {
		$date = $item['date'] ?? '';

		return esc_html( $date );
	}
}
