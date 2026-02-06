<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

/**
 * Logs list table class.
 * 
 * @since 2.14.0
 */
class WPMastertoolkit_Mail_Catcher_Logs_List_Table extends WP_List_Table {

	/**
	 * The mail catcher class.
	 *
	 * @since 2.14.0
	 */
	protected static $class_mail_catcher;

	/**
	 * Container for the notices.
	 *
	 * @since 2.14.0
	 */
	private $notices = [];

	/**
	 * Constructor.
	 * 
	 * @since 2.14.0
	 */
	public function __construct() {
		self::$class_mail_catcher = new WPMastertoolkit_Mail_Catcher();

		parent::__construct( array(
			'singular' => 'wpmastertoolkit-mail-catcher-log',
			'plural'   => 'wpmastertoolkit-mail-catcher-logs',
			'ajax'     => false,
			'screen'   => 'wpmastertoolkit-mail-catcher-logs',
		) );
	}

	/**
	 * Prepares the list table items and arguments.
	 *
	 * @since 2.14.0
	 */
	public function prepare_items( $search = false ) {

		$columns = $this->get_columns();
		$hidden = $this->get_hidden_columns();
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$per_page = (int) sanitize_text_field( wp_unslash( $_GET['per_page'] ?? '0' ) );
		$per_page = ( $per_page < 1 ) ? 25 : $per_page;
		$offset   = ( $this->get_pagenum() - 1 ) * $per_page;

		$this->items = self::$class_mail_catcher->get_logs_items( $per_page, $offset );

		$this->set_pagination_args( array(
			'total_items' => self::$class_mail_catcher->get_logs_items( $per_page, $offset, true ),
			'per_page'    => $per_page,
		) );
	}

	public function get_views() {
        $views    = array();
		$statuses = array(
			0 => __( 'All', 'wpmastertoolkit' ),
            1 => __( 'Successful', 'wpmastertoolkit' ),
            2 => __( 'Failed', 'wpmastertoolkit' ),
		);

        // Get base url.
        $email_log_page_url = add_query_arg( 'page', self::$class_mail_catcher->page_id, admin_url( 'admin.php' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_status     = sanitize_text_field( wp_unslash( $_GET['status'] ?? '0' ) );

        foreach ( $statuses as $status => $label ) {
            $views[ $status ] = sprintf(
                '<a href="%1$s" %2$s>%3$s <span class="count">(%4$d)</span></a>',
                esc_url( add_query_arg( 'status', $status, $email_log_page_url ) ),
                $current_status == $status ? 'class="current"' : '',
                esc_html( $label ),
                absint( self::$class_mail_catcher->get_logs_count( $status ) )
            );
        }

        return $views;
    }

	/**
     * Display the search box.
     *
     * @since 1.12.0
     *
     * @param string $text     The 'submit' button label.
     * @param string $input_id ID attribute value for the search input field.
     */
    public function search_box( $text, $input_id ) {

        if ( ! $this->has_items() ) {
            return;
        }

		// phpcs:disable
        $search_place = ! empty( $_REQUEST['search']['place'] ) ? sanitize_key( $_REQUEST['search']['place'] ) : '';
        $search_term  = ! empty( $_REQUEST['search']['term'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['search']['term'] ) ) : '';

        if ( ! empty( $_REQUEST['orderby'] ) && in_array( $_REQUEST['orderby'], [ 'timestamp', 'host', 'receiver', 'subject' ], true ) ) {
            echo '<input type="hidden" name="orderby" value="' . esc_attr( sanitize_key( $_REQUEST['orderby'] ) ) . '" />';
        }

        if ( ! empty( $_REQUEST['order'] ) ) {
            $order = strtoupper( sanitize_key( $_REQUEST['order'] ) );
            $order = $order === 'ASC' ? 'ASC' : 'DESC';
            echo '<input type="hidden" name="order" value="' . esc_attr( $order ) . '" />';
        }
		// phpcs:enable
        ?>

        <p class="search-box">
            <label class="screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_html( $text ); ?>:</label>
            <select name="search[place]">
				<option value="receiver" <?php selected( 'receiver', $search_place ); ?>><?php esc_html_e( 'Receiver', 'wpmastertoolkit' ); ?></option>
				<option value="subject" <?php selected( 'subject', $search_place ); ?>><?php esc_html_e( 'Subject', 'wpmastertoolkit' ); ?></option>
				<option value="message" <?php selected( 'message', $search_place ); ?>><?php esc_html_e( 'Message', 'wpmastertoolkit' ); ?></option>
            </select>
            <input type="search" id="<?php echo esc_attr( $input_id ); ?>" name="search[term]" value="<?php echo esc_attr( $search_term ); ?>" />
            <?php submit_button( $text, '', '', false, array( 'id' => 'search-submit' ) ); ?>
        </p>

        <?php
    }

	/**
	 * Defines the available columns.
	 *
	 * @since 2.14.0
	 */
	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox" />',
			'id'         => __( 'ID', 'wpmastertoolkit' ),
			'receiver'   => __( 'Receiver', 'wpmastertoolkit' ),
			'subject'    => __( 'Subject', 'wpmastertoolkit' ),
			'mail_error' => __( 'Error', 'wpmastertoolkit' ),
			'time'       => __( 'Time', 'wpmastertoolkit' ),
			'actions'    => '',
		);
	}

	/**
	 * Define the sortable columns
	 * 
	 * @since 2.14.0
	 */
	public function get_sortable_columns() {
		return array(
			'time' => array( 'timestamp', true ),
		);
	}

	/**
     * Define which columns are hidden
	 * 
     * @since 2.14.0
     */
    public function get_hidden_columns() {
        return array(
            'id',
        );
    }

	/**
     * Renders the cell.
	 * 
     * @since 2.14.0
     */
    public function column_default( $item, $column_name ) {
		switch  ( $column_name ) {
			case 'receiver':
				return wp_kses_post( nl2br( str_replace( '\n', "\n", $item['receiver'] ) ) );
			break;
			case 'subject':
				return esc_html( $item['subject'] );
			break;
			case 'mail_error':
				return esc_html( $item['error'] );
			break;
			case 'time':
				return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $item['unixtime'] );
			break;
			case 'actions':
				return $this->get_actions_html( $item );
			break;
		}
    }

	/**
	 * Render the cb column
	 * 
	 * @since 2.14.0
	 */
	public function column_cb( $item ) {
		$name = $this->_args['singular'];

		return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />', $name, $item['id']
		);
	}

	/**
	 * Display the notices.
	 *
	 * @since 2.14.0
	 */
	public function display_notices() {
		foreach( $this->notices as $notice ) {
			?>
			<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible inline">
				<p><?php echo esc_html( $notice['message'] ); ?></p>
				<button type="button" class="notice-dismiss">
					<span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'wpmastertoolkit' ); ?></span>
				</button>
			</div>
			<?php
		}
	}

	/**
	 * Get actions html.
	 * 
	 * @since 2.14.0
	 */
	private function get_actions_html( $item ) {
		ob_start();
		?>
		<button class="wpmtk-view" type="button" name="view" value="<?php echo esc_attr( $item['id'] ); ?>">
			<?php echo wp_kses( file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/eye.svg' ), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
		</button>
		<button onclick="return confirm('<?php esc_html_e( 'Are you sure?', 'wpmastertoolkit' ); ?>')" class="button-link-delete" type="submit" name="delete" value="<?php echo esc_attr( $item['id'] ); ?>">
			<?php echo wp_kses( file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/delete.svg' ), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
		</button>
		<?php
		return ob_get_clean();
	}

	/**
	 * Add notice to notices container.
	 *
	 * @since 2.14.0
	 */
	private function add_notice( $message, $type = 'info' ) {
		$this->notices[] = array(
			'type'    => $type,
			'message' => $message,
		);
	}
}
