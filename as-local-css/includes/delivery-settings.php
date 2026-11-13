<?php
/**
 * CSS delivery mode settings for AS Local CSS.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the CSS Delivery submenu page.
 */
function aslc_register_css_delivery_page() {
	add_submenu_page(
		'edit.php?post_type=custom-css-js',
		__( 'CSS Delivery', 'custom-css-js' ),
		__( 'CSS Delivery', 'custom-css-js' ),
		'manage_options',
		'aslc-css-delivery',
		'aslc_render_css_delivery_page'
	);
}
add_action( 'admin_menu', 'aslc_register_css_delivery_page', 20 );

/**
 * Save the selected CSS delivery mode.
 */
function aslc_save_css_delivery_mode() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die(
			esc_html__( 'You are not allowed to change CSS delivery settings.', 'custom-css-js' ),
			'',
			array( 'response' => 403 )
		);
	}

	check_admin_referer( 'aslc_save_css_delivery_mode' );

	$mode = isset( $_POST['aslc_css_delivery_mode'] )
		? sanitize_key( wp_unslash( $_POST['aslc_css_delivery_mode'] ) )
		: '';
	if ( ! in_array( $mode, array( 'inline', 'file' ), true ) ) {
		$mode = 'file';
	}
	$order = isset( $_POST['aslc_css_priority_order'] )
		? sanitize_key( wp_unslash( $_POST['aslc_css_priority_order'] ) )
		: '';
	if ( ! in_array( $order, array( '1-to-5', '5-to-1' ), true ) ) {
		$order = '5-to-1';
	}

	update_option( ASLC_CSS_DELIVERY_OPTION, $mode, false );
	update_option( ASLC_CSS_ORDER_OPTION, $order, false );
	if ( 'file' === $mode ) {
		aslc_rebuild_css_bundles();
	} else {
		aslc_scan_css_records();
	}

	wp_safe_redirect(
		add_query_arg(
			array(
				'post_type' => 'custom-css-js',
				'page'      => 'aslc-css-delivery',
				'updated'   => '1',
			),
			admin_url( 'edit.php' )
		)
	);
	exit;
}
add_action( 'admin_post_aslc_save_css_delivery_mode', 'aslc_save_css_delivery_mode' );

/**
 * Render the CSS Delivery settings page.
 */
function aslc_render_css_delivery_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$mode  = aslc_get_css_delivery_mode();
	$order = aslc_get_css_priority_order();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'CSS Delivery', 'custom-css-js' ); ?></h1>

		<?php if ( isset( $_GET['updated'] ) && '1' === sanitize_key( wp_unslash( $_GET['updated'] ) ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'CSS delivery mode saved.', 'custom-css-js' ); ?></p></div>
		<?php endif; ?>

		<p><?php esc_html_e( 'Choose how active CSS snippets are delivered. JavaScript and HTML delivery is unchanged.', 'custom-css-js' ); ?></p>

		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<input type="hidden" name="action" value="aslc_save_css_delivery_mode">
			<?php wp_nonce_field( 'aslc_save_css_delivery_mode' ); ?>

			<fieldset>
				<legend class="screen-reader-text"><?php esc_html_e( 'CSS delivery mode', 'custom-css-js' ); ?></legend>
				<p>
					<label>
						<input type="radio" name="aslc_css_delivery_mode" value="inline" <?php checked( 'inline', $mode ); ?>>
						<strong><?php esc_html_e( 'Run inline (legacy)', 'custom-css-js' ); ?></strong><br>
						<span class="description"><?php esc_html_e( 'Print each CSS record inline in the page, matching the original plugin.', 'custom-css-js' ); ?></span>
					</label>
				</p>
				<p>
					<label>
						<input type="radio" name="aslc_css_delivery_mode" value="file" <?php checked( 'file', $mode ); ?>>
						<strong><?php esc_html_e( 'Run as generated file', 'custom-css-js' ); ?></strong><br>
						<span class="description"><?php esc_html_e( 'Load the combined, cache-safe CSS bundle.', 'custom-css-js' ); ?></span>
					</label>
				</p>
			</fieldset>

			<h2><?php esc_html_e( 'CSS order', 'custom-css-js' ); ?></h2>
			<p><?php esc_html_e( 'Choose how record priorities are written from the top to the bottom of the CSS output.', 'custom-css-js' ); ?></p>
			<fieldset>
				<legend class="screen-reader-text"><?php esc_html_e( 'CSS priority order', 'custom-css-js' ); ?></legend>
				<p>
					<label>
						<input type="radio" name="aslc_css_priority_order" value="1-to-5" <?php checked( '1-to-5', $order ); ?>>
						<strong><?php esc_html_e( 'Top 1 → 5 Bottom', 'custom-css-js' ); ?></strong><br>
						<span class="description"><?php esc_html_e( 'Write priority 1 first and priority 5 last. Legacy priorities 6–10 follow the same ascending order.', 'custom-css-js' ); ?></span>
					</label>
				</p>
				<p>
					<label>
						<input type="radio" name="aslc_css_priority_order" value="5-to-1" <?php checked( '5-to-1', $order ); ?>>
						<strong><?php esc_html_e( 'Top 5 → 1 Bottom', 'custom-css-js' ); ?></strong><br>
						<span class="description"><?php esc_html_e( 'Write priority 5 first and priority 1 last. Legacy priorities 6–10 follow the same descending order.', 'custom-css-js' ); ?></span>
					</label>
				</p>
			</fieldset>

			<?php submit_button( __( 'Save CSS Delivery', 'custom-css-js' ) ); ?>
		</form>
	</div>
	<?php
}
