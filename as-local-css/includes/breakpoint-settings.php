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
		<h1 class="wp-heading-inline"><?php esc_html_e( 'CSS Breakpoints', 'custom-css-js' ); ?></h1>
		<a href="#aslc-breakpoint-repeater" class="page-title-action" id="aslc-add-breakpoint"><?php esc_html_e( 'Add New', 'custom-css-js' ); ?></a>
		<hr class="wp-header-end">

		<?php if ( isset( $_GET['updated'] ) && '1' === sanitize_key( wp_unslash( $_GET['updated'] ) ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'CSS breakpoints saved and generated CSS refreshed.', 'custom-css-js' ); ?></p></div>
		<?php endif; ?>

		<p><?php esc_html_e( 'Define tokens that are replaced when the combined CSS file is generated. CSS records and legacy inline delivery remain unchanged.', 'custom-css-js' ); ?></p>

		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<input type="hidden" name="action" value="aslc_save_css_breakpoints">
			<?php wp_nonce_field( 'aslc_save_css_breakpoints' ); ?>

			<table class="wp-list-table widefat fixed striped table-view-list" id="aslc-breakpoint-repeater">
				<thead>
					<tr>
						<th scope="col" class="manage-column column-token column-primary"><?php esc_html_e( 'Token', 'custom-css-js' ); ?></th>
						<th scope="col" class="manage-column column-breakpoint"><?php esc_html_e( 'CSS breakpoint', 'custom-css-js' ); ?></th>
					</tr>
				</thead>
				<tfoot>
					<tr>
						<th scope="col" class="manage-column column-token column-primary"><?php esc_html_e( 'Token', 'custom-css-js' ); ?></th>
						<th scope="col" class="manage-column column-breakpoint"><?php esc_html_e( 'CSS breakpoint', 'custom-css-js' ); ?></th>
					</tr>
				</tfoot>
				<tbody>
					<?php foreach ( $breakpoints as $token => $value ) : ?>
						<tr>
							<td class="token column-token has-row-actions column-primary" data-colname="<?php esc_attr_e( 'Token', 'custom-css-js' ); ?>">
								<input type="text" class="regular-text" name="aslc_css_breakpoint_token[]" value="<?php echo esc_attr( $token ); ?>" placeholder="%MD%" pattern="%?[A-Za-z][A-Za-z0-9_-]*%?" aria-label="<?php esc_attr_e( 'Breakpoint token', 'custom-css-js' ); ?>">
								<div class="row-actions"><span class="trash"><button type="button" class="button-link button-link-delete aslc-remove-breakpoint"><?php esc_html_e( 'Remove', 'custom-css-js' ); ?></button></span></div>
								<button type="button" class="toggle-row"><span class="screen-reader-text"><?php esc_html_e( 'Show more details', 'custom-css-js' ); ?></span></button>
							</td>
							<td class="breakpoint column-breakpoint" data-colname="<?php esc_attr_e( 'CSS breakpoint', 'custom-css-js' ); ?>"><input type="text" class="regular-text" name="aslc_css_breakpoint_value[]" value="<?php echo esc_attr( $value ); ?>" placeholder="68rem" aria-label="<?php esc_attr_e( 'CSS breakpoint value', 'custom-css-js' ); ?>"></td>
						</tr>
					<?php endforeach; ?>
					<?php if ( empty( $breakpoints ) ) : ?>
						<tr class="no-items"><td class="colspanchange" colspan="2"><?php esc_html_e( 'No breakpoints found.', 'custom-css-js' ); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table>

			<p class="description"><?php esc_html_e( 'Example: %MD% → 68rem. Tokens are normalised to uppercase and may be entered with or without percent signs.', 'custom-css-js' ); ?></p>
			<?php submit_button( __( 'Save CSS Breakpoints', 'custom-css-js' ) ); ?>
		</form>

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
				row.innerHTML = '<td class="token column-token has-row-actions column-primary" data-colname="<?php echo esc_js( __( 'Token', 'custom-css-js' ) ); ?>">' +
					'<input type="text" class="regular-text" name="aslc_css_breakpoint_token[]" placeholder="%MD%" pattern="%?[A-Za-z][A-Za-z0-9_-]*%?" aria-label="<?php echo esc_js( __( 'Breakpoint token', 'custom-css-js' ) ); ?>">' +
					'<div class="row-actions"><span class="trash"><button type="button" class="button-link button-link-delete aslc-remove-breakpoint"><?php echo esc_js( __( 'Remove', 'custom-css-js' ) ); ?></button></span></div>' +
					'<button type="button" class="toggle-row"><span class="screen-reader-text"><?php echo esc_js( __( 'Show more details', 'custom-css-js' ) ); ?></span></button></td>' +
					'<td class="breakpoint column-breakpoint" data-colname="<?php echo esc_js( __( 'CSS breakpoint', 'custom-css-js' ) ); ?>"><input type="text" class="regular-text" name="aslc_css_breakpoint_value[]" placeholder="68rem" aria-label="<?php echo esc_js( __( 'CSS breakpoint value', 'custom-css-js' ) ); ?>"></td>';
				row.querySelector('input').focus();
			});

			table.addEventListener('click', function (event) {
				if (event.target.classList.contains('aslc-remove-breakpoint')) {
					event.target.closest('tr').remove();
					if (!table.tBodies[0].rows.length) {
						var emptyRow = table.tBodies[0].insertRow();
						emptyRow.className = 'no-items';
						emptyRow.innerHTML = '<td class="colspanchange" colspan="2"><?php echo esc_js( __( 'No breakpoints found.', 'custom-css-js' ) ); ?></td>';
					}
				}
			});
		}());
		</script>
	</div>
	<?php
}
