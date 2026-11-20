<?php
/**
 * Portable exports for AS Local CSS snippets and Code Domains.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'aslc_transfer_menu' );
add_action( 'admin_post_aslc_transfer', 'aslc_transfer_request' );

function aslc_transfer_menu() {
	add_submenu_page( 'edit.php?post_type=custom-css-js', 'Import / Export', 'Import / Export', 'manage_options', 'aslc-transfer', 'aslc_transfer_page' );
}

function aslc_transfer_authorised() {
	return current_user_can( 'manage_options' ) && current_user_can( 'unfiltered_html' );
}

function aslc_transfer_page() {
	if ( ! aslc_transfer_authorised() ) {
		wp_die( 'You do not have permission to transfer executable snippets.' );
	}
	?>
	<div class="wrap">
		<h1>Import / Export</h1>
		<?php if ( isset( $_GET['aslc_imported'] ) ) : ?>
			<div class="notice notice-success"><p><?php echo esc_html( sprintf( 'Import completed. %d snippets written.', absint( $_GET['aslc_imported'] ) ) ); ?></p></div>
		<?php endif; ?>
		<h2>Export</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'aslc_transfer_export' ); ?>
			<input type="hidden" name="action" value="aslc_transfer">
			<input type="hidden" name="operation" value="export">
			<label for="aslc-export-type">Code type</label>
			<select id="aslc-export-type" name="language">
				<option value="all">All</option>
				<option value="css">CSS</option>
				<option value="js">JavaScript</option>
				<option value="html">HTML</option>
			</select>
			<?php submit_button( 'Download export', 'secondary' ); ?>
		</form>
		<h2>Import</h2>
		<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'aslc_transfer_import' ); ?>
			<input type="hidden" name="action" value="aslc_transfer">
			<input type="hidden" name="operation" value="import">
			<label for="aslc-import-file">Export file</label>
			<input id="aslc-import-file" name="export_file" type="file" accept=".json" required>
			<p><label for="aslc-import-mode">Import mode</label>
			<select id="aslc-import-mode" name="mode">
				<option value="skip">Append and skip existing</option>
				<option value="overwrite">Append and overwrite existing</option>
				<option value="replace">Replace existing snippets in the exported code types</option>
			</select></p>
			<p><label><input type="checkbox" name="confirm_replace" value="1"> Confirm replacement if Replace is selected</label></p>
			<?php submit_button( 'Import snippets' ); ?>
		</form>
	</div>
	<?php
}

function aslc_transfer_posts() {
	return get_posts( array(
		'post_type'      => 'custom-css-js',
		'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
		'posts_per_page' => -1,
		'orderby'        => 'ID',
		'order'          => 'ASC',
	) );
}

function aslc_transfer_domains() {
	$terms = get_terms( array( 'taxonomy' => ASLC_CODE_DOMAIN_TAXONOMY, 'hide_empty' => false ) );
	if ( is_wp_error( $terms ) ) {
		throw new RuntimeException( $terms->get_error_message() );
	}

	$slugs = array();
	$domains = array();
	foreach ( $terms as $term ) {
		$slugs[ $term->term_id ] = $term->slug;
	}
	foreach ( $terms as $term ) {
		if ( isset( $domains[ $term->slug ] ) ) {
			throw new RuntimeException( 'Code Domain slugs must be unique for transfer.' );
		}
		$domains[ $term->slug ] = array(
			'id'          => $term->term_id,
			'slug'        => $term->slug,
			'name'        => $term->name,
			'description' => $term->description,
			'parent'      => $slugs[ $term->parent ] ?? '',
		);
	}
	return $domains;
}

function aslc_transfer_record( $post ) {
	$options = (array) get_post_meta( $post->ID, 'options', true );
	$domains = wp_get_object_terms( $post->ID, ASLC_CODE_DOMAIN_TAXONOMY, array( 'fields' => 'slugs' ) );
	if ( is_wp_error( $domains ) ) {
		throw new RuntimeException( $domains->get_error_message() );
	}
	return array(
		'title'    => $post->post_title,
		'code'     => $post->post_content,
		'status'   => $post->post_status,
		'date'     => $post->post_date,
		'options'  => $options + array( 'language' => 'css', 'type' => 'header', 'side' => 'frontend', 'priority' => 5 ),
		'active'   => 'no' !== get_post_meta( $post->ID, '_active', true ),
		'domain'   => $domains[0] ?? '',
		'sections' => aslc_get_css_breakpoint_sections( $post->ID ),
		'target'   => get_post_meta( $post->ID, ASLC_CSS_TARGET_SCOPE_META, true ),
	);
}

function aslc_transfer_key( $record ) {
	return wp_json_encode( array( $record['options']['language'], $record['domain'], $record['title'] ) );
}

function aslc_import_snippets( $payload, $mode ) {
	if ( ! is_array( $payload ) || 'as-local-css' !== ( $payload['format'] ?? '' ) || 1 !== ( $payload['schema'] ?? 0 )
		|| ! is_array( $payload['languages'] ?? null ) || ! $payload['languages']
		|| ! is_array( $payload['domains'] ?? null ) || ! is_array( $payload['snippets'] ?? null ) ) {
		throw new RuntimeException( 'This file is not a supported AS Local CSS export.' );
	}
	foreach ( $payload['languages'] as $language ) {
		if ( ! in_array( $language, array( 'css', 'js', 'html' ), true ) ) {
			throw new RuntimeException( 'Invalid code type in export.' );
		}
	}
	$domains = aslc_transfer_domains();
	$incoming = array();
	foreach ( $payload['domains'] as $domain ) {
		if ( ! is_array( $domain ) || ! is_string( $domain['slug'] ?? null ) || '' === $domain['slug']
			|| sanitize_title( $domain['slug'] ) !== $domain['slug'] || isset( $incoming[ $domain['slug'] ] )
			|| ! is_string( $domain['name'] ?? null ) || '' === trim( $domain['name'] )
			|| ! is_string( $domain['parent'] ?? null ) || ! is_string( $domain['description'] ?? null ) ) {
			throw new RuntimeException( 'Invalid or duplicate Code Domain slug.' );
		}
		$incoming[ $domain['slug'] ] = $domain;
	}
	// Order missing terms by parent before making any changes.
	$known = $domains;
	$pending = array_diff_key( $incoming, $known );
	$ordered = array();
	while ( $pending ) {
		$before = count( $pending );
		foreach ( $pending as $slug => $domain ) {
			if ( '' === $domain['parent'] || isset( $known[ $domain['parent'] ] ) ) {
				$ordered[ $slug ] = $known[ $slug ] = $domain;
				unset( $pending[ $slug ] );
			}
		}
		if ( count( $pending ) === $before ) {
			throw new RuntimeException( 'The Code Domains contain a missing parent or a circular hierarchy.' );
		}
	}

	$records = array();
	foreach ( $payload['snippets'] as $record ) {
		$options = $record['options'] ?? null;
		if ( ! is_array( $record ) || ! is_array( $options ) || ! in_array( $options['language'] ?? '', $payload['languages'], true )
			|| ! is_string( $record['title'] ?? null ) || ! is_string( $record['code'] ?? null )
			|| ! in_array( $record['status'] ?? '', array( 'publish', 'draft', 'pending', 'private', 'future' ), true )
			|| ! is_string( $record['date'] ?? null ) || ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $record['date'] )
			|| ! is_bool( $record['active'] ?? null ) || ! is_string( $record['domain'] ?? null )
			|| ( '' !== $record['domain'] && ! isset( $incoming[ $record['domain'] ] ) )
			|| ! is_array( $record['sections'] ?? null ) || ! is_string( $record['target'] ?? null )
			|| ! in_array( $options['type'] ?? '', array( 'header', 'footer' ), true )
			|| ! is_string( $options['side'] ?? null ) || array_diff( explode( ',', $options['side'] ), array( 'frontend', 'admin', 'login' ) )
			|| ! in_array( (string) ( $options['priority'] ?? '' ), array_map( 'strval', range( 0, 10 ) ), true ) ) {
			throw new RuntimeException( 'The export contains an invalid snippet or delivery setting.' );
		}
		foreach ( $record['sections'] as $section => $css ) {
			if ( (string) $section !== sanitize_key( (string) $section ) || ! is_string( $css ) ) {
				throw new RuntimeException( 'The export contains an invalid CSS breakpoint section.' );
			}
		}
		$key = aslc_transfer_key( $record );
		if ( 'replace' !== $mode && isset( $records[ $key ] ) ) {
			throw new RuntimeException( 'The export contains snippets with the same language, domain and title. Use Replace or give them unique titles.' );
		}
		$records[ 'replace' === $mode ? count( $records ) : $key ] = $record;
	}

	$type = get_post_type_object( 'custom-css-js' );
	if ( ! $type || ! current_user_can( $type->cap->create_posts ) || ! current_user_can( $type->cap->publish_posts ) ) {
		throw new RuntimeException( 'You do not have permission to create or publish snippets.' );
	}
	if ( 'replace' === $mode && ! EMPTY_TRASH_DAYS ) {
		throw new RuntimeException( 'Enable WordPress Trash before using Replace.' );
	}
	$matches = array();
	$retire = array();
	foreach ( aslc_transfer_posts() as $post ) {
		$local = aslc_transfer_record( $post );
		if ( 'replace' === $mode && in_array( $local['options']['language'], $payload['languages'], true ) ) {
			if ( ! current_user_can( 'delete_post', $post->ID ) ) {
				throw new RuntimeException( 'You do not have permission to replace all snippets in this export scope.' );
			}
			$retire[] = $post->ID;
		} elseif ( 'replace' !== $mode && isset( $records[ aslc_transfer_key( $local ) ] ) ) {
			$key = aslc_transfer_key( $local );
			if ( 'overwrite' === $mode && ( isset( $matches[ $key ] ) || ! current_user_can( 'edit_post', $post->ID ) ) ) {
				throw new RuntimeException( 'An existing snippet match is ambiguous or cannot be edited.' );
			}
			$matches[ $key ] = $post->ID;
		}
	}

	foreach ( $ordered as $slug => $domain ) {
		$result = wp_insert_term( $domain['name'], ASLC_CODE_DOMAIN_TAXONOMY, array(
			'slug'        => $slug,
			'description' => $domain['description'],
			'parent'      => $domains[ $domain['parent'] ]['id'] ?? 0,
		) );
		if ( is_wp_error( $result ) ) {
			throw new RuntimeException( $result->get_error_message() );
		}
		$domains[ $slug ] = array( 'id' => $result['term_id'] );
	}

	$written = array();
	try {
		foreach ( $records as $key => $record ) {
			$id = $matches[ $key ] ?? 0;
			if ( $id && 'skip' === $mode ) {
				continue;
			}
			$options = array_intersect_key( $record['options'], array_flip( array( 'language', 'type', 'side', 'priority' ) ) );
			$options['linking'] = 'html' === $options['language'] ? 'both' : 'internal';
			$id = wp_insert_post( wp_slash( array(
				'ID'           => $id,
				'post_type'    => 'custom-css-js',
				'post_title'   => $record['title'],
				'post_content' => $record['code'],
				'post_status'  => $record['status'],
				'post_date'    => $record['date'],
				'meta_input'   => array(
					'options'                         => $options,
					'_active'                         => $record['active'] ? 'yes' : 'no',
					ASLC_CSS_BREAKPOINT_SECTIONS_META  => $record['sections'],
					ASLC_CSS_TARGET_SCOPE_META         => aslc_sanitize_css_target_scope( $record['target'] ),
				),
			) ), true );
			if ( is_wp_error( $id ) || ! $id ) {
				throw new RuntimeException( 'A snippet could not be saved. Check the list for records already imported.' );
			}
			$written[] = $id;
			$terms = isset( $domains[ $record['domain'] ] ) ? array( (int) $domains[ $record['domain'] ]['id'] ) : array();
			if ( is_wp_error( wp_set_object_terms( $id, $terms, ASLC_CODE_DOMAIN_TAXONOMY ) ) ) {
				throw new RuntimeException( 'A snippet was saved but its Code Domain could not be assigned.' );
			}
		}
		foreach ( $retire as $id ) {
			if ( ! wp_trash_post( $id ) ) {
				throw new RuntimeException( 'Replacement stopped because an existing snippet could not be moved to Trash.' );
			}
		}
	} finally {
		do_action( 'aslc_snippets_imported', $written );
	}
	return count( $written );
}

function aslc_transfer_request() {
	if ( 'POST' !== $_SERVER['REQUEST_METHOD'] || ! aslc_transfer_authorised() ) {
		wp_die( 'You do not have permission to transfer executable snippets.', '', array( 'response' => 403 ) );
	}
	$operation = $_POST['operation'] ?? '';
	$languages = array( 'css', 'js', 'html' );
	try {
		if ( 'export' === $operation ) {
			check_admin_referer( 'aslc_transfer_export' );
			$type = $_POST['language'] ?? '';
			if ( 'all' !== $type && ! in_array( $type, $languages, true ) ) {
				throw new RuntimeException( 'Invalid export code type.' );
			}
			$scope = 'all' === $type ? $languages : array( $type );
			$records = array();
			foreach ( aslc_transfer_posts() as $post ) {
				$record = aslc_transfer_record( $post );
				if ( in_array( $record['options']['language'], $scope, true ) ) {
					$records[] = $record;
				}
			}
			$domains = aslc_transfer_domains();
			foreach ( $domains as &$domain ) {
				unset( $domain['id'] );
			}
			unset( $domain );
			$json = wp_json_encode( array( 'format' => 'as-local-css', 'schema' => 1, 'languages' => $scope, 'domains' => array_values( $domains ), 'snippets' => $records ), JSON_PRETTY_PRINT );
			if ( false === $json ) {
				throw new RuntimeException( 'The export could not be encoded.' );
			}
			nocache_headers();
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="as-local-css-export.json"' );
			echo $json;
			exit;
		}

		if ( 'import' !== $operation ) {
			throw new RuntimeException( 'Invalid transfer operation.' );
		}
		check_admin_referer( 'aslc_transfer_import' );
		$mode = $_POST['mode'] ?? '';
		if ( ! in_array( $mode, array( 'skip', 'overwrite', 'replace' ), true ) || ( 'replace' === $mode && '1' !== ( $_POST['confirm_replace'] ?? '' ) ) ) {
			throw new RuntimeException( 'Choose an import mode and confirm replacement when selected.' );
		}
		$file = $_FILES['export_file'] ?? array();
		if ( UPLOAD_ERR_OK !== ( $file['error'] ?? null ) || ! is_uploaded_file( $file['tmp_name'] ) || $file['size'] > 16 * MB_IN_BYTES ) {
			throw new RuntimeException( 'Upload an AS Local CSS JSON export of up to 16 MB.' );
		}
		$payload = json_decode( (string) file_get_contents( $file['tmp_name'] ), true );
		$count = aslc_import_snippets( $payload, $mode );
		wp_safe_redirect( add_query_arg( array( 'post_type' => 'custom-css-js', 'page' => 'aslc-transfer', 'aslc_imported' => $count ), admin_url( 'edit.php' ) ) );
		exit;
	} catch ( RuntimeException $error ) {
		wp_die( esc_html( $error->getMessage() ), 'AS Local CSS transfer', array( 'back_link' => true ) );
	}
}
