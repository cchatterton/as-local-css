<?php
/**
 * WordPress admin table for CSS scan findings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Native WordPress list table for CSS errors.
 */
class ASLC_CSS_Errors_List_Table extends WP_List_Table {
	/**
	 * Configure columns and rows.
	 */
	public function prepare_items() {
		$findings = aslc_get_css_scan_findings();
		$per_page = 20;
		$page     = $this->get_pagenum();

		$this->_column_headers = array( $this->get_columns(), array(), array() );
		$this->items           = array_slice( $findings, ( $page - 1 ) * $per_page, $per_page );
		$this->set_pagination_args(
			array(
				'total_items' => count( $findings ),
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * Return table columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'record'      => __( 'CSS record', 'custom-css-js' ),
			'severity'    => __( 'Severity', 'custom-css-js' ),
			'line'        => __( 'Line', 'custom-css-js' ),
			'description' => __( 'Description', 'custom-css-js' ),
			'action'      => __( 'Action', 'custom-css-js' ),
		);
	}

	/**
	 * Render the CSS record column.
	 *
	 * @param array $item Finding row.
	 * @return string
	 */
	public function column_record( $item ) {
		$title = get_the_title( $item['post_id'] );
		if ( '' === $title ) {
			$title = __( '(no title)', 'custom-css-js' );
		}
		return sprintf( '<strong>%s</strong><br><code>ID %d</code>', esc_html( $title ), (int) $item['post_id'] );
	}

	/**
	 * Render uncustomized columns.
	 *
	 * @param array  $item        Finding row.
	 * @param string $column_name Column key.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		if ( 'severity' === $column_name ) {
			return esc_html( ucfirst( $item['severity'] ) );
		}
		if ( 'line' === $column_name ) {
			return $item['line'] ? (string) (int) $item['line'] : '&mdash;';
		}
		if ( 'description' === $column_name ) {
			return esc_html( $item['message'] );
		}
		if ( 'action' === $column_name ) {
			$edit_link = get_edit_post_link( $item['post_id'], '' );
			return $edit_link
				? sprintf( '<a class="button button-secondary" href="%s">%s</a>', esc_url( $edit_link ), esc_html__( 'Edit CSS', 'custom-css-js' ) )
				: '&mdash;';
		}
		return '';
	}
}

/**
 * Register the CSS Errors submenu and its update-style counter.
 */
function aslc_register_css_errors_page() {
	$count      = count( aslc_get_css_scan_findings() );
	$menu_title = __( 'CSS Errors', 'custom-css-js' );
	if ( $count > 0 ) {
		$menu_title .= sprintf(
			' <span class="update-plugins count-%1$d"><span class="plugin-count">%1$d</span></span>',
			$count
		);
	}

	add_submenu_page(
		'edit.php?post_type=custom-css-js',
		__( 'CSS Errors', 'custom-css-js' ),
		$menu_title,
		'manage_options',
		'aslc-css-errors',
		'aslc_render_css_errors_page'
	);
}
add_action( 'admin_menu', 'aslc_register_css_errors_page', 21 );

/**
 * Run a requested manual CSS scan.
 */
function aslc_run_manual_css_scan() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to scan CSS records.', 'custom-css-js' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'aslc_run_css_scan' );
	aslc_scan_css_records();
	wp_safe_redirect(
		add_query_arg(
			array(
				'post_type' => 'custom-css-js',
				'page'      => 'aslc-css-errors',
			),
			admin_url( 'edit.php' )
		)
	);
	exit;
}
add_action( 'admin_post_aslc_run_css_scan', 'aslc_run_manual_css_scan' );

/**
 * Render the CSS Errors screen.
 */
function aslc_render_css_errors_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$results  = get_option( ASLC_CSS_SCAN_OPTION, array() );
	$findings = aslc_get_css_scan_findings();
	$table    = new ASLC_CSS_Errors_List_Table();
	$table->prepare_items();
	?>
	<div class="wrap">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'CSS Errors', 'custom-css-js' ); ?></h1>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="display:inline-block;margin-left:8px;">
			<input type="hidden" name="action" value="aslc_run_css_scan">
			<?php wp_nonce_field( 'aslc_run_css_scan' ); ?>
			<?php submit_button( __( 'Scan CSS now', 'custom-css-js' ), 'secondary', 'submit', false ); ?>
		</form>
		<hr class="wp-header-end">

		<?php if ( ! empty( $results['scanned_at'] ) ) : ?>
			<p class="description">
				<?php
				printf(
					/* translators: %s: date and time of the latest CSS scan. */
					esc_html__( 'Last scan: %s', 'custom-css-js' ),
					esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $results['scanned_at'] ) )
				);
				?>
			</p>
		<?php endif; ?>

		<?php if ( empty( $findings ) ) : ?>
			<div class="notice notice-success inline"><p><?php esc_html_e( 'No CSS errors were found.', 'custom-css-js' ); ?></p></div>
		<?php else : ?>
			<p><?php esc_html_e( 'Review each finding and open its CSS record to make corrections. The scanner never changes CSS automatically.', 'custom-css-js' ); ?></p>
		<?php endif; ?>

		<?php $table->display(); ?>
	</div>
	<?php
}
