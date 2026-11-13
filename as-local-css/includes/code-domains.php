<?php
/**
 * Native WordPress taxonomy for organising Local CSS records.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const ASLC_CODE_DOMAIN_TAXONOMY       = 'aslc_code_domain';
const ASLC_CODE_DOMAINS_SEEDED_OPTION = 'aslc_code_domains_seeded';

/** Return the initial Code Domain terms keyed by slug. */
function aslc_get_default_code_domains() {
	return array(
		'theme'     => __( 'Theme', 'custom-css-js' ),
		'block'     => __( 'Block', 'custom-css-js' ),
		'page'      => __( 'Page', 'custom-css-js' ),
		'cpt'       => __( 'CPT', 'custom-css-js' ),
		'plugin'    => __( 'Plugin', 'custom-css-js' ),
		'utility'   => __( 'Utility', 'custom-css-js' ),
		'component' => __( 'Component', 'custom-css-js' ),
		'shortcode' => __( 'Shortcode', 'custom-css-js' ),
	);
}

/** Register the hierarchical Code Domains taxonomy. */
function aslc_register_code_domain_taxonomy() {
	$labels = array(
		'name'              => _x( 'Code Domains', 'taxonomy general name', 'custom-css-js' ),
		'singular_name'     => _x( 'Code Domain', 'taxonomy singular name', 'custom-css-js' ),
		'menu_name'         => __( 'Code Domains', 'custom-css-js' ),
		'all_items'         => __( 'All Code Domains', 'custom-css-js' ),
		'edit_item'         => __( 'Edit Code Domain', 'custom-css-js' ),
		'view_item'         => __( 'View Code Domain', 'custom-css-js' ),
		'update_item'       => __( 'Update Code Domain', 'custom-css-js' ),
		'add_new_item'      => __( 'Add New Code Domain', 'custom-css-js' ),
		'new_item_name'     => __( 'New Code Domain Name', 'custom-css-js' ),
		'parent_item'       => __( 'Parent Code Domain', 'custom-css-js' ),
		'parent_item_colon' => __( 'Parent Code Domain:', 'custom-css-js' ),
		'search_items'      => __( 'Search Code Domains', 'custom-css-js' ),
		'not_found'         => __( 'No Code Domains found.', 'custom-css-js' ),
	);

	register_taxonomy(
		ASLC_CODE_DOMAIN_TAXONOMY,
		array( 'custom-css-js' ),
		array(
			'labels'            => $labels,
			'public'            => false,
			'hierarchical'      => true,
			'show_ui'           => true,
			'show_in_menu'      => true,
			'show_admin_column' => true,
			'show_in_quick_edit' => false,
			'show_in_rest'      => false,
			'query_var'         => false,
			'rewrite'           => false,
			'meta_box_cb'       => 'aslc_render_code_domain_meta_box',
			'meta_box_sanitize_cb' => 'aslc_sanitize_code_domain_meta_box',
			'capabilities'      => array(
				'manage_terms' => 'manage_categories',
				'edit_terms'   => 'manage_categories',
				'delete_terms' => 'manage_categories',
				'assign_terms' => 'edit_custom_csss',
			),
		)
	);
}
add_action( 'init', 'aslc_register_code_domain_taxonomy', 11 );

/** Keep only the first selected Code Domain from editor submissions. */
function aslc_sanitize_code_domain_meta_box( $taxonomy, $terms ) {
	foreach ( (array) $terms as $term_id ) {
		$term_id = absint( $term_id );
		if ( $term_id ) {
			return array( $term_id );
		}
	}

	return array();
}

/** Render a hierarchical, single-select Code Domain panel. */
function aslc_render_code_domain_meta_box( $post ) {
	$selected_ids = wp_get_object_terms(
		$post->ID,
		ASLC_CODE_DOMAIN_TAXONOMY,
		array( 'fields' => 'ids' )
	);
	$selected_id = ! is_wp_error( $selected_ids ) && $selected_ids ? (int) reset( $selected_ids ) : 0;
	$terms       = get_terms(
		array(
			'taxonomy'   => ASLC_CODE_DOMAIN_TAXONOMY,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		)
	);

	?>
	<div id="taxonomy-<?php echo esc_attr( ASLC_CODE_DOMAIN_TAXONOMY ); ?>" class="categorydiv">
		<div class="tabs-panel">
			<input type="hidden" name="tax_input[<?php echo esc_attr( ASLC_CODE_DOMAIN_TAXONOMY ); ?>][]" value="0">
			<ul class="categorychecklist form-no-clear">
				<li>
					<label>
						<input type="radio" name="tax_input[<?php echo esc_attr( ASLC_CODE_DOMAIN_TAXONOMY ); ?>][]" value="0" <?php checked( 0, $selected_id ); ?>>
						<?php esc_html_e( 'No domain', 'custom-css-js' ); ?>
					</label>
				</li>
				<?php
				if ( ! is_wp_error( $terms ) ) {
					aslc_render_code_domain_radio_terms( $terms, 0, $selected_id );
				}
				?>
			</ul>
		</div>
	</div>
	<?php
}

/** Render one level of the Code Domain radio hierarchy. */
function aslc_render_code_domain_radio_terms( array $terms, $parent_id, $selected_id, $depth = 0 ) {
	foreach ( $terms as $term ) {
		if ( (int) $term->parent !== (int) $parent_id ) {
			continue;
		}
		?>
		<li style="margin-left: <?php echo (int) $depth * 16; ?>px;">
			<label>
				<input type="radio" name="tax_input[<?php echo esc_attr( ASLC_CODE_DOMAIN_TAXONOMY ); ?>][]" value="<?php echo (int) $term->term_id; ?>" <?php checked( (int) $term->term_id, $selected_id ); ?>>
				<?php echo esc_html( $term->name ); ?>
			</label>
		</li>
		<?php
		aslc_render_code_domain_radio_terms( $terms, (int) $term->term_id, $selected_id, $depth + 1 );
	}
}

/** Enforce a single Code Domain even when terms are assigned outside the editor. */
function aslc_enforce_single_code_domain( $object_id, $terms, $tt_ids, $taxonomy ) {
	static $enforcing = false;

	if ( $enforcing || ASLC_CODE_DOMAIN_TAXONOMY !== $taxonomy || 'custom-css-js' !== get_post_type( $object_id ) ) {
		return;
	}

	$assigned_ids = wp_get_object_terms(
		$object_id,
		ASLC_CODE_DOMAIN_TAXONOMY,
		array(
			'fields'  => 'ids',
			'orderby' => 'term_id',
			'order'   => 'ASC',
		)
	);
	if ( is_wp_error( $assigned_ids ) || count( $assigned_ids ) <= 1 ) {
		return;
	}

	$requested_ids = array_values( array_filter( array_map( 'absint', (array) $terms ) ) );
	$keep_id       = $requested_ids ? (int) end( $requested_ids ) : (int) reset( $assigned_ids );
	if ( ! in_array( $keep_id, $assigned_ids, true ) ) {
		$keep_id = (int) reset( $assigned_ids );
	}

	$enforcing = true;
	wp_set_object_terms( $object_id, array( $keep_id ), ASLC_CODE_DOMAIN_TAXONOMY, false );
	$enforcing = false;
}
add_action( 'set_object_terms', 'aslc_enforce_single_code_domain', 10, 4 );

/** Seed the initial terms once, without recreating intentionally removed terms. */
function aslc_seed_default_code_domains() {
	if ( get_option( ASLC_CODE_DOMAINS_SEEDED_OPTION, false ) || ! taxonomy_exists( ASLC_CODE_DOMAIN_TAXONOMY ) ) {
		return;
	}

	$seeded = true;
	foreach ( aslc_get_default_code_domains() as $slug => $name ) {
		if ( term_exists( $slug, ASLC_CODE_DOMAIN_TAXONOMY ) ) {
			continue;
		}

		$result = wp_insert_term( $name, ASLC_CODE_DOMAIN_TAXONOMY, array( 'slug' => $slug ) );
		if ( is_wp_error( $result ) ) {
			$seeded = false;
		}
	}

	if ( $seeded ) {
		update_option( ASLC_CODE_DOMAINS_SEEDED_OPTION, 1, false );
	}
}
add_action( 'init', 'aslc_seed_default_code_domains', 12 );

/** Keep the native taxonomy panel on the custom code editor. */
function aslc_allow_code_domain_meta_box( array $allowed ) {
	$allowed[] = ASLC_CODE_DOMAIN_TAXONOMY . 'div';
	return array_values( array_unique( $allowed ) );
}
add_filter( 'custom-css-js-meta-boxes', 'aslc_allow_code_domain_meta_box' );
