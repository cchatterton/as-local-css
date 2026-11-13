<?php
/**
 * CSS breakpoint-token settings for AS Local CSS.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Register the CSS Breakpoints submenu page. */
function aslc_register_css_breakpoints_page() {
	add_submenu_page(
		'edit.php?post_type=custom-css-js',
		__( 'CSS Breakpoints', 'custom-css-js' ),
		__( 'CSS Breakpoints', 'custom-css-js' ),
		'manage_options',
		'aslc-css-breakpoints',
		'aslc_render_css_breakpoints_page'
	);
}
add_action( 'admin_menu', 'aslc_register_css_breakpoints_page', 21 );

/** Save breakpoint-token replacements and refresh the generated CSS. */
function aslc_save_css_breakpoints() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die(
			esc_html__( 'You are not allowed to change CSS breakpoint settings.', 'custom-css-js' ),
			'',
			array( 'response' => 403 )
		);
	}

	check_admin_referer( 'aslc_save_css_breakpoints' );

	$names  = isset( $_POST['aslc_css_breakpoint_token'] ) && is_array( $_POST['aslc_css_breakpoint_token'] )
		? wp_unslash( $_POST['aslc_css_breakpoint_token'] )
		: array();
	$values = isset( $_POST['aslc_css_breakpoint_value'] ) && is_array( $_POST['aslc_css_breakpoint_value'] )
		? wp_unslash( $_POST['aslc_css_breakpoint_value'] )
		: array();
	$rows   = array();
	$count  = max( count( $names ), count( $values ) );

	for ( $index = 0; $index < $count; $index++ ) {
		$rows[] = array(
			'token' => isset( $names[ $index ] ) ? $names[ $index ] : '',
			'value' => isset( $values[ $index ] ) ? $values[ $index ] : '',
		);
	}

	update_option( ASLC_CSS_BREAKPOINTS_OPTION, aslc_sanitize_css_breakpoint_tokens( $rows ), false );
	if ( aslc_use_css_file_delivery() ) {
		aslc_rebuild_css_bundles();
	} else {
		aslc_scan_css_records();
	}

	wp_safe_redirect(
		add_query_arg(
			array(
				'post_type' => 'custom-css-js',
				'page'      => 'aslc-css-breakpoints',
				'updated'   => '1',
			),
			admin_url( 'edit.php' )
		)
	);
	exit;
}
add_action( 'admin_post_aslc_save_css_breakpoints', 'aslc_save_css_breakpoints' );

/** Render the CSS Breakpoints settings page. */
function aslc_render_css_breakpoints_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$breakpoints = aslc_get_css_breakpoint_tokens();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'CSS Breakpoints', 'custom-css-js' ); ?></h1>

		<?php if ( isset( $_GET['updated'] ) && '1' === sanitize_key( wp_unslash( $_GET['updated'] ) ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'CSS breakpoints saved and generated CSS refreshed.', 'custom-css-js' ); ?></p></div>
		<?php endif; ?>

		<form class="aslc-breakpoints-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<input type="hidden" name="action" value="aslc_save_css_breakpoints">
			<?php wp_nonce_field( 'aslc_save_css_breakpoints' ); ?>

			<div class="aslc-breakpoints-card">
				<table class="widefat aslc-breakpoints-table" id="aslc-breakpoint-repeater">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Token', 'custom-css-js' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Breakpoint', 'custom-css-js' ); ?></th>
							<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'custom-css-js' ); ?></span></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $breakpoints as $token => $value ) : ?>
							<tr>
								<td data-colname="<?php esc_attr_e( 'Token', 'custom-css-js' ); ?>"><input type="text" class="aslc-token-input" name="aslc_css_breakpoint_token[]" value="<?php echo esc_attr( $token ); ?>" placeholder="%MD%" pattern="%?[A-Za-z][A-Za-z0-9_-]*%?" aria-label="<?php esc_attr_e( 'Breakpoint token', 'custom-css-js' ); ?>"></td>
								<td data-colname="<?php esc_attr_e( 'Breakpoint', 'custom-css-js' ); ?>"><input type="text" class="aslc-value-input" name="aslc_css_breakpoint_value[]" value="<?php echo esc_attr( $value ); ?>" placeholder="68rem" aria-label="<?php esc_attr_e( 'CSS breakpoint value', 'custom-css-js' ); ?>"></td>
								<td class="aslc-breakpoint-actions"><button type="button" class="button-link aslc-remove-breakpoint" aria-label="<?php esc_attr_e( 'Remove breakpoint', 'custom-css-js' ); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Remove breakpoint', 'custom-css-js' ); ?></span></button></td>
							</tr>
						<?php endforeach; ?>
						<?php if ( empty( $breakpoints ) ) : ?>
							<tr class="no-items"><td colspan="3"><?php esc_html_e( 'No breakpoints have been added.', 'custom-css-js' ); ?></td></tr>
						<?php endif; ?>
					</tbody>
				</table>

				<div class="aslc-breakpoints-card__footer">
					<button type="button" class="button aslc-add-breakpoint" id="aslc-add-breakpoint"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span><?php esc_html_e( 'Add', 'custom-css-js' ); ?></button>
					<?php submit_button( __( 'Save', 'custom-css-js' ), 'primary', 'submit', false ); ?>
				</div>
			</div>
		</form>

		<style>
		.aslc-breakpoints-form {
			max-width: 45rem;
		}
		.aslc-breakpoints-card {
			margin-top: 1rem;
			overflow: hidden;
			background: #fff;
			border: 1px solid #dcdcde;
			border-radius: 10px;
			box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04), 0 8px 24px rgba(0, 0, 0, 0.035);
		}
		.aslc-add-breakpoint {
			display: inline-flex !important;
			align-items: center;
			gap: 0.3rem;
			min-height: 2rem !important;
			padding: 0 0.65rem !important;
			white-space: nowrap;
		}
		.aslc-add-breakpoint .dashicons {
			width: 1rem;
			height: 1rem;
			font-size: 1rem;
			line-height: 1;
		}
		.aslc-breakpoints-table {
			border: 0;
			box-shadow: none;
		}
		.aslc-breakpoints-table th {
			padding: 0.55rem 1.25rem;
			background: #fff;
			border-bottom: 1px solid #dcdcde;
			color: #646970;
			font-size: 0.6875rem;
			font-weight: 600;
			letter-spacing: 0.055em;
			text-transform: uppercase;
		}
		.aslc-breakpoints-table th:nth-child(1) { width: 42%; }
		.aslc-breakpoints-table th:nth-child(2) { width: auto; }
		.aslc-breakpoints-table th:nth-child(3) { width: 3rem; }
		.aslc-breakpoints-table td {
			padding: 0.5rem 1.25rem;
			vertical-align: middle;
			border-bottom: 1px solid #f0f0f1;
		}
		.aslc-breakpoints-table tbody tr {
			transition: background-color 120ms ease;
		}
		.aslc-breakpoints-table tbody tr:hover,
		.aslc-breakpoints-table tbody tr:focus-within {
			background: #f9f9fa;
		}
		.aslc-breakpoints-table tbody tr:last-child td {
			border-bottom: 0;
		}
		.aslc-breakpoints-table input[type="text"] {
			width: 100%;
			max-width: 13.5rem;
			min-height: 2.125rem;
			padding: 0.25rem 0.65rem;
			border-color: #c3c4c7;
			border-radius: 6px;
			box-shadow: 0 1px 1px rgba(0, 0, 0, 0.02);
		}
		.aslc-breakpoints-table .aslc-token-input {
			background: #f6f7f7;
			font-family: Consolas, Monaco, monospace;
			font-weight: 600;
			letter-spacing: 0.02em;
		}
		.aslc-breakpoints-table input[type="text"]:focus {
			background: #fff;
			border-color: var(--wp-admin-theme-color, #2271b1);
			box-shadow: 0 0 0 1px var(--wp-admin-theme-color, #2271b1);
		}
		.aslc-breakpoint-actions {
			width: 3rem;
			text-align: right;
		}
		.aslc-remove-breakpoint {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			width: 2rem;
			height: 2rem;
			padding: 0;
			border-radius: 50%;
			color: #8c8f94;
			opacity: 0.45;
			transition: color 120ms ease, background-color 120ms ease, opacity 120ms ease;
		}
		.aslc-breakpoints-table tr:hover .aslc-remove-breakpoint,
		.aslc-remove-breakpoint:focus {
			opacity: 1;
		}
		.aslc-remove-breakpoint:hover,
		.aslc-remove-breakpoint:focus {
			background: #fcf0f1;
			color: #b32d2e;
			box-shadow: none;
			outline: none;
		}
		.aslc-remove-breakpoint:focus-visible {
			box-shadow: 0 0 0 2px #fff, 0 0 0 4px var(--wp-admin-theme-color, #2271b1);
		}
		.aslc-remove-breakpoint .dashicons {
			width: 1rem;
			height: 1rem;
			font-size: 1rem;
			line-height: 1;
		}
		.aslc-breakpoints-card__footer {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 1.5rem;
			padding: 0.875rem 1.25rem;
			background: #fbfbfc;
			border-top: 1px solid #dcdcde;
		}
		.aslc-breakpoints-card__footer p {
			margin: 0;
		}
		.aslc-breakpoints-card__footer .button-primary {
			min-height: 2.25rem;
			padding: 0 1rem;
		}
		@media (hover: none) {
			.aslc-remove-breakpoint {
				opacity: 1;
			}
		}
		@media screen and (max-width: 782px) {
			.aslc-breakpoints-card__footer {
				align-items: center;
			}
			.aslc-breakpoints-table thead {
				display: none;
			}
			.aslc-breakpoints-table tbody tr {
				display: grid;
				grid-template-columns: 1fr auto;
				padding: 0.75rem 1rem;
				border-bottom: 1px solid #dcdcde;
			}
			.aslc-breakpoints-table td {
				display: block;
				padding: 0.35rem 0.5rem;
				border: 0;
			}
			.aslc-breakpoints-table td:nth-child(1),
			.aslc-breakpoints-table td:nth-child(2) {
				grid-column: 1;
			}
			.aslc-breakpoints-table td:nth-child(3) {
				grid-column: 2;
				grid-row: 1 / span 2;
			}
			.aslc-breakpoints-table input[type="text"] {
				max-width: none;
			}
		}
		</style>

		<script>
		(function () {
			'use strict';
			var table = document.getElementById('aslc-breakpoint-repeater');
			var add = document.getElementById('aslc-add-breakpoint');
			if (!table || !add) return;

			add.addEventListener('click', function (event) {
				event.preventDefault();
				var empty = table.tBodies[0].querySelector('.no-items');
				if (empty) empty.remove();
				var row = table.tBodies[0].insertRow();
				row.innerHTML = '<td data-colname="<?php echo esc_js( __( 'Token', 'custom-css-js' ) ); ?>"><input type="text" class="aslc-token-input" name="aslc_css_breakpoint_token[]" placeholder="%MD%" pattern="%?[A-Za-z][A-Za-z0-9_-]*%?" aria-label="<?php echo esc_js( __( 'Breakpoint token', 'custom-css-js' ) ); ?>"></td>' +
					'<td data-colname="<?php echo esc_js( __( 'Breakpoint', 'custom-css-js' ) ); ?>"><input type="text" class="aslc-value-input" name="aslc_css_breakpoint_value[]" placeholder="68rem" aria-label="<?php echo esc_js( __( 'CSS breakpoint value', 'custom-css-js' ) ); ?>"></td>' +
					'<td class="aslc-breakpoint-actions"><button type="button" class="button-link aslc-remove-breakpoint" aria-label="<?php echo esc_js( __( 'Remove breakpoint', 'custom-css-js' ) ); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span><span class="screen-reader-text"><?php echo esc_js( __( 'Remove breakpoint', 'custom-css-js' ) ); ?></span></button></td>';
				row.querySelector('input').focus();
			});

			table.addEventListener('click', function (event) {
				if (event.target.classList.contains('aslc-remove-breakpoint')) {
					event.target.closest('tr').remove();
					if (!table.tBodies[0].rows.length) {
						var emptyRow = table.tBodies[0].insertRow();
						emptyRow.className = 'no-items';
						emptyRow.innerHTML = '<td colspan="3"><?php echo esc_js( __( 'No breakpoints have been added.', 'custom-css-js' ) ); ?></td>';
					}
				}
			});
		}());
		</script>
	</div>
	<?php
}
