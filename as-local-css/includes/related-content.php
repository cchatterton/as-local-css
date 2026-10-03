<?php
/**
 * Bidirectional relationships between custom code and content.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const ASLC_RELATED_CONTENT_META = '_aslc_related_content_ids';
const ASLC_RELATED_CODE_META    = '_aslc_related_code_ids';

add_action( 'add_meta_boxes', 'aslc_add_relationship_meta_boxes', 20 );
add_action( 'add_meta_boxes_custom-css-js', 'aslc_add_custom_code_relationship_meta_box', 20 );
add_action( 'admin_enqueue_scripts', 'aslc_enqueue_relationship_assets' );
add_action( 'save_post', 'aslc_save_relationship_meta_boxes', 20, 2 );
add_action( 'wp_ajax_aslc_search_relationship_targets', 'aslc_ajax_search_relationship_targets' );
add_action( 'admin_post_aslc_create_related_code', 'aslc_create_related_code' );
add_filter( 'custom-css-js-meta-boxes', 'aslc_allow_relationship_meta_box' );

function aslc_add_relationship_meta_boxes() {
	aslc_add_custom_code_relationship_meta_box();

	foreach ( aslc_get_supported_content_post_types() as $post_type ) {
		add_meta_box(
			'aslc-related-code',
			__( 'Related Custom Code', 'custom-css-js' ),
			'aslc_render_related_code_meta_box',
			$post_type,
			'side',
			'high'
		);
	}
}

function aslc_add_custom_code_relationship_meta_box() {
	add_meta_box(
		'aslc-related-content',
		__( 'Related Content', 'custom-css-js' ),
		'aslc_render_related_content_meta_box',
		'custom-css-js',
		'side',
		'high'
	);
}

function aslc_allow_relationship_meta_box( array $allowed ) {
	$allowed[] = 'aslc-related-content';

	return array_values( array_unique( $allowed ) );
}

function aslc_enqueue_relationship_assets( $hook ) {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen || ( 'custom-css-js' !== $screen->post_type && ! in_array( $screen->post_type, aslc_get_supported_content_post_types(), true ) ) ) {
		return;
	}

	wp_enqueue_script(
		'aslc-relationships',
		ASLC_PLUGIN_URL . 'assets/aslc-relationships.js',
		array( 'jquery', 'jquery-ui-autocomplete' ),
		ASLC_VERSION,
		true
	);

	wp_localize_script(
		'aslc-relationships',
		'ASLCRelationships',
		array(
			'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
			'nonce'         => wp_create_nonce( 'aslc_relationship_search' ),
			'searchingText' => __( 'Searching...', 'custom-css-js' ),
			'noResultsText' => __( 'No matching items found.', 'custom-css-js' ),
			'duplicateText' => __( 'That item is already related.', 'custom-css-js' ),
		)
	);

	wp_enqueue_style(
		'aslc-relationships',
		ASLC_PLUGIN_URL . 'assets/aslc-relationships.css',
		array(),
		ASLC_VERSION
	);
}

function aslc_render_related_content_meta_box( $post ) {
	$related_ids = aslc_get_related_content_ids_for_code( $post->ID );

	if ( empty( $related_ids ) && ! empty( $_GET['aslc_related_content'] ) ) {
		$related_ids = aslc_sanitize_post_ids( array( $_GET['aslc_related_content'] ) );
	}

	wp_nonce_field( 'aslc_save_relationships', 'aslc_relationships_nonce' );
	aslc_render_relationship_picker(
		array(
			'context'     => 'content',
			'field_name'  => 'aslc_related_content_ids[]',
			'placeholder' => __( 'Search content by title...', 'custom-css-js' ),
			'button'      => __( 'Add Content', 'custom-css-js' ),
			'empty'       => __( 'No related content selected.', 'custom-css-js' ),
			'related_ids' => $related_ids,
		)
	);
}

function aslc_render_related_code_meta_box( $post ) {
	$related_ids = aslc_get_related_code_ids_for_content( $post->ID );

	wp_nonce_field( 'aslc_save_relationships', 'aslc_relationships_nonce' );
	aslc_render_relationship_picker(
		array(
			'context'     => 'code',
			'field_name'  => 'aslc_related_code_ids[]',
			'placeholder' => __( 'Search custom code by title...', 'custom-css-js' ),
			'button'      => __( 'Add Code', 'custom-css-js' ),
			'empty'       => __( 'No related custom code selected.', 'custom-css-js' ),
			'related_ids' => $related_ids,
		)
	);

	aslc_render_create_code_links( $post );
}

function aslc_render_relationship_picker( array $args ) {
	?>
	<div class="aslc-relationship-picker" data-context="<?php echo esc_attr( $args['context'] ); ?>" data-field-name="<?php echo esc_attr( $args['field_name'] ); ?>">
		<div class="aslc-relationship-search">
			<label class="screen-reader-text" for="aslc-search-<?php echo esc_attr( $args['context'] ); ?>">
				<?php echo esc_html( $args['placeholder'] ); ?>
			</label>
			<input
				type="text"
				id="aslc-search-<?php echo esc_attr( $args['context'] ); ?>"
				class="widefat aslc-relationship-search-input"
				placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>"
				autocomplete="off"
			/>
			<button type="button" class="button aslc-relationship-add" disabled>
				<?php echo esc_html( $args['button'] ); ?>
			</button>
			<p class="description aslc-relationship-status" aria-live="polite"></p>
		</div>
		<ul class="aslc-relationship-list" data-empty-text="<?php echo esc_attr( $args['empty'] ); ?>">
			<?php foreach ( $args['related_ids'] as $related_id ) : ?>
				<?php aslc_render_relationship_item( $related_id, $args['field_name'] ); ?>
			<?php endforeach; ?>
		</ul>
	</div>
	<?php
}

function aslc_render_relationship_item( $post_id, $field_name ) {
	$related_post = get_post( $post_id );
	if ( ! $related_post ) {
		return;
	}

	$title = get_the_title( $related_post );
	if ( '' === $title ) {
		$title = sprintf( __( '(no title) #%d', 'custom-css-js' ), $related_post->ID );
	}

	$type_label = get_post_type_object( $related_post->post_type );
	?>
	<li class="aslc-relationship-item" data-id="<?php echo esc_attr( $related_post->ID ); ?>">
		<input type="hidden" name="<?php echo esc_attr( $field_name ); ?>" value="<?php echo esc_attr( $related_post->ID ); ?>" />
		<span class="aslc-relationship-title"><?php echo esc_html( $title ); ?></span>
		<span class="aslc-relationship-type"><?php echo esc_html( $type_label ? $type_label->labels->singular_name : $related_post->post_type ); ?></span>
		<a href="<?php echo esc_url( get_edit_post_link( $related_post->ID, 'raw' ) ); ?>" target="_blank" rel="noopener noreferrer">
			<?php esc_html_e( 'Open', 'custom-css-js' ); ?>
		</a>
		<button type="button" class="button-link-delete aslc-relationship-remove">
			<?php esc_html_e( 'Remove', 'custom-css-js' ); ?>
		</button>
	</li>
	<?php
}

function aslc_render_create_code_links( WP_Post $post ) {
	if ( ! current_user_can( 'publish_custom_csss' ) ) {
		return;
	}

	$css_url = aslc_get_create_code_url( $post->ID, 'css' );
	$js_url  = aslc_get_create_code_url( $post->ID, 'js' );
	?>
	<div class="aslc-create-code-links">
		<a class="button" href="<?php echo esc_url( $css_url ); ?>" target="_blank" rel="noopener noreferrer">
			<?php esc_html_e( 'Create CSS', 'custom-css-js' ); ?>
		</a>
		<a class="button" href="<?php echo esc_url( $js_url ); ?>" target="_blank" rel="noopener noreferrer">
			<?php esc_html_e( 'Create JS', 'custom-css-js' ); ?>
		</a>
	</div>
	<?php
}

function aslc_get_create_code_url( $content_id, $language ) {
	return wp_nonce_url(
		add_query_arg(
			array(
				'action'     => 'aslc_create_related_code',
				'content_id' => absint( $content_id ),
				'language'   => $language,
			),
			admin_url( 'admin-post.php' )
		),
		'aslc_create_related_code_' . absint( $content_id )
	);
}

function aslc_save_relationship_meta_boxes( $post_id, $post ) {
	if ( ! $post instanceof WP_Post || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}

	if ( empty( $_POST['aslc_relationships_nonce'] ) || ! wp_verify_nonce( $_POST['aslc_relationships_nonce'], 'aslc_save_relationships' ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( 'custom-css-js' === $post->post_type ) {
		$content_ids = isset( $_POST['aslc_related_content_ids'] ) ? aslc_sanitize_post_ids( wp_unslash( $_POST['aslc_related_content_ids'] ) ) : array();
		aslc_sync_code_relationships( $post_id, $content_ids );
		return;
	}

	if ( ! in_array( $post->post_type, aslc_get_supported_content_post_types(), true ) ) {
		return;
	}

	$code_ids = isset( $_POST['aslc_related_code_ids'] ) ? aslc_sanitize_post_ids( wp_unslash( $_POST['aslc_related_code_ids'] ) ) : array();
	aslc_sync_content_relationships( $post_id, $code_ids );
}

function aslc_ajax_search_relationship_targets() {
	check_ajax_referer( 'aslc_relationship_search', 'nonce' );

	$context = isset( $_GET['context'] ) ? sanitize_key( wp_unslash( $_GET['context'] ) ) : '';
	$term    = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';

	if ( ! current_user_can( 'edit_posts' ) || ! in_array( $context, array( 'content', 'code' ), true ) ) {
		wp_send_json_error();
	}

	$post_types = 'code' === $context ? array( 'custom-css-js' ) : aslc_get_supported_content_post_types();
	$query      = new WP_Query(
		array(
			'post_type'              => $post_types,
			'post_status'            => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			's'                      => $term,
			'posts_per_page'         => 20,
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);

	$results = array();
	foreach ( $query->posts as $result_post ) {
		if ( ! current_user_can( 'edit_post', $result_post->ID ) ) {
			continue;
		}

		$results[] = aslc_prepare_relationship_search_result( $result_post );
	}

	wp_send_json_success( $results );
}

function aslc_create_related_code() {
	$content_id = isset( $_GET['content_id'] ) ? absint( $_GET['content_id'] ) : 0;
	$language   = isset( $_GET['language'] ) ? sanitize_key( wp_unslash( $_GET['language'] ) ) : 'css';

	if ( ! $content_id || ! in_array( $language, array( 'css', 'js' ), true ) ) {
		wp_die( esc_html__( 'Invalid custom code request.', 'custom-css-js' ) );
	}

	check_admin_referer( 'aslc_create_related_code_' . $content_id );

	if ( ! current_user_can( 'edit_post', $content_id ) || ! current_user_can( 'publish_custom_csss' ) ) {
		wp_die( esc_html__( 'You do not have permission to create related custom code.', 'custom-css-js' ) );
	}

	$content_post = get_post( $content_id );
	if ( ! $content_post || ! in_array( $content_post->post_type, aslc_get_supported_content_post_types(), true ) ) {
		wp_die( esc_html__( 'The related content item is not available.', 'custom-css-js' ) );
	}

	$code_id = wp_insert_post(
		array(
			'post_type'    => 'custom-css-js',
			'post_status'  => 'draft',
			'post_title'   => sprintf( '%s for %s', strtoupper( $language ), get_the_title( $content_post ) ),
			'post_content' => '',
		),
		true
	);

	if ( is_wp_error( $code_id ) ) {
		wp_die( esc_html__( 'The related custom code could not be created.', 'custom-css-js' ) );
	}

	update_post_meta(
		$code_id,
		'options',
		array(
			'type'     => 'header',
			'linking'  => 'internal',
			'priority' => 5,
			'side'     => 'frontend',
			'language' => $language,
		)
	);

	aslc_sync_code_relationships( $code_id, array( $content_id ) );
	wp_safe_redirect( get_edit_post_link( $code_id, 'raw' ) );
	exit;
}

function aslc_prepare_relationship_search_result( WP_Post $post ) {
	$title = get_the_title( $post );
	if ( '' === $title ) {
		$title = sprintf( __( '(no title) #%d', 'custom-css-js' ), $post->ID );
	}

	$type = get_post_type_object( $post->post_type );

	return array(
		'id'      => $post->ID,
		'title'   => $title,
		'type'    => $type ? $type->labels->singular_name : $post->post_type,
		'editUrl' => get_edit_post_link( $post->ID, 'raw' ),
	);
}

function aslc_sync_code_relationships( $code_id, array $content_ids ) {
	$content_ids = aslc_filter_existing_related_ids( $content_ids, aslc_get_supported_content_post_types() );
	$old_ids     = aslc_get_related_content_ids_for_code( $code_id );

	update_post_meta( $code_id, ASLC_RELATED_CONTENT_META, $content_ids );

	foreach ( array_diff( $old_ids, $content_ids ) as $removed_content_id ) {
		aslc_remove_related_code_from_content( $removed_content_id, $code_id );
	}

	foreach ( $content_ids as $content_id ) {
		aslc_add_related_code_to_content( $content_id, $code_id );
	}
}

function aslc_sync_content_relationships( $content_id, array $code_ids ) {
	$code_ids = aslc_filter_existing_related_ids( $code_ids, array( 'custom-css-js' ) );
	$old_ids  = aslc_get_related_code_ids_for_content( $content_id );

	update_post_meta( $content_id, ASLC_RELATED_CODE_META, $code_ids );

	foreach ( array_diff( $old_ids, $code_ids ) as $removed_code_id ) {
		aslc_remove_related_content_from_code( $removed_code_id, $content_id );
	}

	foreach ( $code_ids as $code_id ) {
		aslc_add_related_content_to_code( $code_id, $content_id );
	}
}

function aslc_add_related_code_to_content( $content_id, $code_id ) {
	$code_ids = aslc_get_related_code_ids_for_content( $content_id );
	if ( ! in_array( $code_id, $code_ids, true ) ) {
		$code_ids[] = absint( $code_id );
		update_post_meta( $content_id, ASLC_RELATED_CODE_META, aslc_sanitize_post_ids( $code_ids ) );
	}
}

function aslc_remove_related_code_from_content( $content_id, $code_id ) {
	$code_ids = array_diff( aslc_get_related_code_ids_for_content( $content_id ), array( absint( $code_id ) ) );
	update_post_meta( $content_id, ASLC_RELATED_CODE_META, aslc_sanitize_post_ids( $code_ids ) );
}

function aslc_add_related_content_to_code( $code_id, $content_id ) {
	$content_ids = aslc_get_related_content_ids_for_code( $code_id );
	if ( ! in_array( $content_id, $content_ids, true ) ) {
		$content_ids[] = absint( $content_id );
		update_post_meta( $code_id, ASLC_RELATED_CONTENT_META, aslc_sanitize_post_ids( $content_ids ) );
	}
}

function aslc_remove_related_content_from_code( $code_id, $content_id ) {
	$content_ids = array_diff( aslc_get_related_content_ids_for_code( $code_id ), array( absint( $content_id ) ) );
	update_post_meta( $code_id, ASLC_RELATED_CONTENT_META, aslc_sanitize_post_ids( $content_ids ) );
}

function aslc_get_related_content_ids_for_code( $code_id ) {
	return aslc_sanitize_post_ids( get_post_meta( $code_id, ASLC_RELATED_CONTENT_META, true ) );
}

function aslc_get_related_code_ids_for_content( $content_id ) {
	return aslc_sanitize_post_ids( get_post_meta( $content_id, ASLC_RELATED_CODE_META, true ) );
}

function aslc_filter_existing_related_ids( array $post_ids, array $post_types ) {
	$filtered_ids = array();

	foreach ( aslc_sanitize_post_ids( $post_ids ) as $post_id ) {
		$post = get_post( $post_id );
		if ( $post && in_array( $post->post_type, $post_types, true ) ) {
			$filtered_ids[] = $post_id;
		}
	}

	return aslc_sanitize_post_ids( $filtered_ids );
}

function aslc_sanitize_post_ids( $post_ids ) {
	if ( ! is_array( $post_ids ) ) {
		return array();
	}

	$post_ids = array_map( 'absint', $post_ids );
	$post_ids = array_filter( $post_ids );

	return array_values( array_unique( $post_ids ) );
}

function aslc_get_supported_content_post_types() {
	$post_types = get_post_types( array( 'show_ui' => true ), 'names' );
	$excluded   = array( 'custom-css-js', 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation' );

	return array_values( array_diff( $post_types, $excluded ) );
}
