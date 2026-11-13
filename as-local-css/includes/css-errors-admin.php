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
			'id'          => __( 'ID', 'custom-css-js' ),
			'severity'    => __( 'Severity', 'custom-css-js' ),
			'section'     => __( 'Section', 'custom-css-js' ),
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
		return sprintf( '<strong>%s</strong>', esc_html( $title ) );
	}

	/**
	 * Render uncustomized columns.
	 *
	 * @param array  $item        Finding row.
	 * @param string $column_name Column key.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		if ( 'id' === $column_name ) {
			return '<code>' . (int) $item['post_id'] . '</code>';
		}
		if ( 'severity' === $column_name ) {
			return esc_html( ucfirst( $item['severity'] ) );
		}
		if ( 'section' === $column_name ) {
			return ! empty( $item['section'] ) ? '<code>' . esc_html( $item['section'] ) . '</code>' : '&mdash;';
		}
		if ( 'line' === $column_name ) {
			return $item['line'] ? (string) (int) $item['line'] : '&mdash;';
		}
		if ( 'description' === $column_name ) {
			return sprintf(
				'<span class="aslc-finding-description" title="%1$s">%2$s</span>',
				esc_attr( $item['message'] ),
				esc_html( $item['message'] )
			);
		}
		if ( 'action' === $column_name ) {
			$edit_link = get_edit_post_link( $item['post_id'], '' );
			if ( $edit_link && ! empty( $item['section'] ) ) {
				$rows = function_exists( 'aslc_get_css_breakpoint_rows' ) ? aslc_get_css_breakpoint_rows() : array();
				$hash = 'aslc-css-section-base';
				foreach ( $rows as $row ) {
					if ( $row['token'] === $item['section'] ) {
						$hash = 'aslc-css-section-' . $row['id'];
						break;
					}
				}
				$edit_link .= '#' . $hash;
			}
			return $edit_link
				? sprintf( '<a class="button button-secondary button-small aslc-edit-css" href="%s">%s</a>', esc_url( $edit_link ), esc_html__( 'Edit', 'custom-css-js' ) )
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

	$hook = add_submenu_page(
		'edit.php?post_type=custom-css-js',
		__( 'CSS Errors', 'custom-css-js' ),
		$menu_title,
		'manage_options',
		'aslc-css-errors',
		'aslc_render_css_errors_page'
	);
	add_action( 'load-' . $hook, 'aslc_add_css_errors_help' );
}
add_action( 'admin_menu', 'aslc_register_css_errors_page', 21 );

/** Add native contextual guidance to the CSS Errors screen. */
function aslc_add_css_errors_help() {
	$screen = get_current_screen();
	if ( ! $screen ) {
		return;
	}

	$screen->add_help_tab(
		array(
			'id'      => 'aslc-css-errors-help',
			'title'   => __( 'CSS findings', 'custom-css-js' ),
			'content' =>
				'<p>' . esc_html__( 'Information findings are suggestions and never block compiled CSS. Errors identify CSS that is likely ineffective. Critical findings can change how later records are parsed, so generated-file delivery stays disabled until they are fixed.', 'custom-css-js' ) . '</p>' .
				'<h3>' . esc_html__( 'Common findings', 'custom-css-js' ) . '</h3>' .
				'<ul>' .
				'<li><strong>' . esc_html__( 'Media query in Base', 'custom-css-js' ) . '</strong> — ' . esc_html__( 'Move the responsive rules into the matching breakpoint accordion when practical.', 'custom-css-js' ) . '</li>' .
				'<li><strong>' . esc_html__( 'Unclosed opening brace, comment, or quoted value', 'custom-css-js' ) . '</strong> — ' . esc_html__( 'Close the structure in the reported section before rebuilding compiled CSS.', 'custom-css-js' ) . '</li>' .
				'<li><strong>' . esc_html__( 'No configured breakpoint', 'custom-css-js' ) . '</strong> — ' . esc_html__( 'Add the token under CSS Breakpoints or replace it with a valid configured token.', 'custom-css-js' ) . '</li>' .
				'<li><strong>' . esc_html__( 'Selector or value errors', 'custom-css-js' ) . '</strong> — ' . esc_html__( 'Use the reported line to correct the selector, decimal, shorthand, or missing selector.', 'custom-css-js' ) . '</li>' .
				'</ul>' .
				'<p>' . esc_html__( 'Edit opens the affected record and, when available, the exact accordion containing the finding. The scanner never changes CSS automatically.', 'custom-css-js' ) . '</p>',
		)
	);
}

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
	<div class="wrap aslc-css-errors-page">
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
			<p><?php esc_html_e( 'Open a finding to review it. See the Help tab for explanations and suggested fixes.', 'custom-css-js' ); ?></p>
		<?php endif; ?>

		<?php $table->display(); ?>

		<style>
		.aslc-css-errors-page .wp-list-table {
			border-radius: 8px;
			box-shadow: 0 1px 2px rgba(0, 0, 0, 0.035);
		}
		.aslc-css-errors-page .wp-list-table th,
		.aslc-css-errors-page .wp-list-table td {
			vertical-align: middle;
		}
		.aslc-css-errors-page .wp-list-table td {
			padding-top: 0.75rem;
			padding-bottom: 0.75rem;
		}
		.aslc-css-errors-page .column-id,
		.aslc-css-errors-page .column-line {
			font-variant-numeric: tabular-nums;
		}
		.aslc-css-errors-page .column-id code,
		.aslc-css-errors-page .column-section code {
			white-space: nowrap;
		}
		.aslc-css-errors-page .aslc-finding-description {
			display: -webkit-box;
			overflow: hidden;
			-webkit-box-orient: vertical;
			-webkit-line-clamp: 2;
			line-clamp: 2;
			line-height: 1.45;
		}
		.aslc-css-errors-page .aslc-edit-css {
			min-width: 3.5rem;
			text-align: center;
		}
		@media screen and (min-width: 1200px) {
			.aslc-css-errors-page .wp-list-table {
				table-layout: fixed;
			}
			.aslc-css-errors-page .column-record { width: 18rem; }
			.aslc-css-errors-page .column-id { width: 4.75rem; }
			.aslc-css-errors-page .column-severity { width: 7rem; }
			.aslc-css-errors-page .column-section { width: 6rem; }
			.aslc-css-errors-page .column-line { width: 3.75rem; }
			.aslc-css-errors-page .column-action { width: 5rem; }
		}
		@media screen and (max-width: 782px) {
			.aslc-css-errors-page .aslc-finding-description {
				display: block;
				overflow: visible;
			}
		}
		</style>
	</div>
	<?php
}
