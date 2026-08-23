<?php
/**
 * GitHub release updater for AS Local CSS.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ASLC_GitHub_Updater {
	private const OWNER             = 'cchatterton';
	private const REPO              = 'as-local-css';
	private const SLUG              = 'as-local-css';
	private const ASSET_NAME        = 'as-local-css.zip';
	private const RELEASE_TRANSIENT = 'aslc_github_latest_release';
	private const ERROR_TRANSIENT   = 'aslc_github_latest_release_error';
	private const API_URL           = 'https://api.github.com/repos/cchatterton/as-local-css/releases/latest';
	private const MANIFEST_URL      = 'https://raw.githubusercontent.com/cchatterton/as-local-css/main/update.json';

	private static $forced_cache_cleared = false;

	public static function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject_update' ) );
		add_filter( 'site_transient_update_plugins', array( __CLASS__, 'inject_update' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_information' ), 10, 3 );
		add_filter( 'plugin_row_meta', array( __CLASS__, 'plugin_row_meta' ), 10, 2 );
		add_action( 'admin_init', array( __CLASS__, 'handle_manual_update_check' ) );
		add_action( 'admin_notices', array( __CLASS__, 'manual_update_check_notice' ) );
		add_action( 'network_admin_notices', array( __CLASS__, 'manual_update_check_notice' ) );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'clear_release_cache' ), 10, 2 );
	}

	public static function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$release = self::get_latest_release();
		if ( empty( $release ) ) {
			return $transient;
		}

		$plugin_file          = plugin_basename( ASLC_PLUGIN_FILE );
		$transient->response  = isset( $transient->response ) && is_array( $transient->response ) ? $transient->response : array();
		$transient->no_update = isset( $transient->no_update ) && is_array( $transient->no_update ) ? $transient->no_update : array();

		unset( $transient->response[ $plugin_file ], $transient->no_update[ $plugin_file ] );

		if ( ! self::has_newer_version( $release ) ) {
			return $transient;
		}

		$package = self::get_asset_url( $release );
		if ( '' === $package ) {
			return $transient;
		}

		$transient->response[ $plugin_file ] = (object) array(
			'id'           => self::repository_url(),
			'slug'         => self::SLUG,
			'plugin'       => $plugin_file,
			'new_version'  => self::release_version( $release ),
			'url'          => self::release_url( $release ),
			'package'      => $package,
			'requires'     => '6.0',
			'requires_php' => '7.4',
		);

		return $transient;
	}

	public static function plugin_information( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || self::SLUG !== $args->slug ) {
			return $result;
		}

		$release = self::get_latest_release();
		$package = ! empty( $release ) ? self::get_asset_url( $release ) : '';
		if ( empty( $release ) || '' === $package ) {
			return $result;
		}

		return (object) array(
			'name'          => 'AS Local CSS',
			'slug'          => self::SLUG,
			'version'       => self::release_version( $release ),
			'author'        => 'AlphaSys',
			'homepage'      => self::repository_url(),
			'requires'      => '6.0',
			'requires_php'  => '7.4',
			'download_link' => $package,
			'sections'      => array(
				'description' => 'Compatible AlphaSys-maintained release of Simple Custom CSS and JS.',
				'changelog'   => wp_kses_post( self::release_notes( $release ) ),
			),
		);
	}

	public static function plugin_row_meta( $links, $plugin_file ) {
		if ( plugin_basename( ASLC_PLUGIN_FILE ) !== $plugin_file ) {
			return $links;
		}

		$links[] = '<a href="' . esc_url( self::repository_url() ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'GitHub', 'custom-css-js' ) . '</a>';
		if ( ! current_user_can( 'update_plugins' ) ) {
			return $links;
		}

		$plugins_url = is_multisite() ? network_admin_url( 'plugins.php' ) : admin_url( 'plugins.php' );
		$check_url   = wp_nonce_url(
			add_query_arg( 'aslc_check_updates', '1', $plugins_url ),
			'aslc_check_updates'
		);

		$links[] = '<a href="' . esc_url( $check_url ) . '">' . esc_html__( 'Check for updates', 'custom-css-js' ) . '</a>';

		return $links;
	}

	public static function handle_manual_update_check() {
		if ( empty( $_GET['aslc_check_updates'] ) ) {
			return;
		}

		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( esc_html__( 'You are not allowed to check for plugin updates.', 'custom-css-js' ) );
		}

		check_admin_referer( 'aslc_check_updates' );
		self::delete_caches();
		delete_site_transient( 'update_plugins' );
		wp_update_plugins();

		$update_state = get_site_transient( 'update_plugins' );
		if ( ! is_object( $update_state ) ) {
			$update_state = (object) array();
		}

		// Persist our update entry after WordPress has finished rebuilding its own transient.
		$update_state = self::inject_update( $update_state );
		set_site_transient( 'update_plugins', $update_state );

		$check_result = self::manual_update_check_result( $update_state );
		$plugins_url = is_multisite() ? network_admin_url( 'plugins.php' ) : admin_url( 'plugins.php' );
		wp_safe_redirect( add_query_arg( 'aslc_update_check_result', $check_result, $plugins_url ) );
		exit;
	}

	public static function manual_update_check_notice() {
		if ( ! current_user_can( 'update_plugins' ) || empty( $_GET['aslc_update_check_result'] ) ) {
			return;
		}

		$result = sanitize_key( wp_unslash( $_GET['aslc_update_check_result'] ) );
		if ( 'available' === $result ) {
			$notice_class = 'notice notice-success is-dismissible';
			$message      = __( 'AS Local CSS found an update. You can install it from the plugin row below.', 'custom-css-js' );
		} elseif ( 'current' === $result ) {
			$notice_class = 'notice notice-success is-dismissible';
			$message      = __( 'AS Local CSS is up to date.', 'custom-css-js' );
		} elseif ( 'failed' === $result ) {
			$notice_class = 'notice notice-error is-dismissible';
			$message      = __( 'AS Local CSS could not complete the GitHub update check. No plugin files were changed; please try again shortly.', 'custom-css-js' );
		} else {
			return;
		}

		printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $notice_class ), esc_html( $message ) );
	}

	public static function clear_release_cache( $upgrader, $options ) {
		if ( empty( $options['type'] ) || 'plugin' !== $options['type'] || empty( $options['action'] ) || 'update' !== $options['action'] ) {
			return;
		}

		$updated_plugins = isset( $options['plugins'] ) && is_array( $options['plugins'] ) ? $options['plugins'] : array();
		if ( isset( $options['plugin'] ) ) {
			$updated_plugins[] = $options['plugin'];
		}

		if ( in_array( plugin_basename( ASLC_PLUGIN_FILE ), $updated_plugins, true ) ) {
			self::delete_caches();
		}
	}

	private static function get_latest_release() {
		if ( self::is_forced_update_check() && ! self::$forced_cache_cleared ) {
			self::delete_caches();
			self::$forced_cache_cleared = true;
		}

		$cached_release = get_site_transient( self::RELEASE_TRANSIENT );
		if ( is_array( $cached_release ) ) {
			return $cached_release;
		}

		$manifest_release = self::get_release_from_manifest();
		if ( ! empty( $manifest_release ) ) {
			self::cache_release( $manifest_release );
			return $manifest_release;
		}

		$response = wp_remote_get(
			self::API_URL,
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'AS-Local-CSS/' . ASLC_VERSION,
				),
			)
		);

		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			$release = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( self::is_valid_release( $release ) ) {
				self::cache_release( $release );
				return $release;
			}
		}

		self::store_lookup_error( $response );
		$fallback_release = self::get_release_from_redirect();
		if ( ! empty( $fallback_release ) ) {
			delete_site_transient( self::ERROR_TRANSIENT );
			self::cache_release( $fallback_release );
			return $fallback_release;
		}

		return array();
	}

	private static function get_release_from_manifest() {
		$response = wp_remote_get(
			self::MANIFEST_URL,
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'     => 'application/json',
					'User-Agent' => 'AS-Local-CSS/' . ASLC_VERSION,
				),
			)
		);
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return array();
		}

		$manifest = json_decode( wp_remote_retrieve_body( $response ), true );
		$version  = is_array( $manifest ) && isset( $manifest['version'] ) ? (string) $manifest['version'] : '';
		if ( ! self::is_valid_version( $version ) ) {
			return array();
		}

		$tag = 'v' . $version;
		return array(
			'tag_name' => $tag,
			'html_url' => self::repository_url() . '/releases/tag/' . rawurlencode( $tag ),
			'body'     => isset( $manifest['body'] ) ? (string) $manifest['body'] : 'Release details are available on GitHub.',
			'assets'   => array(
				array(
					'name'                 => self::ASSET_NAME,
					'browser_download_url' => self::repository_url() . '/releases/download/' . rawurlencode( $tag ) . '/' . self::ASSET_NAME,
				),
			),
		);
	}

	private static function get_release_from_redirect() {
		$response = wp_remote_get(
			self::repository_url() . '/releases/latest',
			array(
				'timeout'     => 10,
				'redirection' => 0,
				'headers'     => array( 'User-Agent' => 'AS-Local-CSS/' . ASLC_VERSION ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return array();
		}

		$location = wp_remote_retrieve_header( $response, 'location' );
		if ( ! is_string( $location ) || ! preg_match( '#/releases/tag/([^/?#]+)#', $location, $matches ) ) {
			return array();
		}

		$tag     = rawurldecode( $matches[1] );
		$version = ltrim( $tag, 'vV' );
		if ( '' === $version || ! preg_match( '/^[0-9]+(?:\.[0-9A-Za-z-]+)+$/', $version ) ) {
			return array();
		}

		$asset_url = self::repository_url() . '/releases/download/' . rawurlencode( $tag ) . '/' . self::ASSET_NAME;

		return array(
			'tag_name' => $tag,
			'html_url' => self::repository_url() . '/releases/tag/' . rawurlencode( $tag ),
			'body'     => 'Release details are available on GitHub.',
			'assets'   => array(
				array(
					'name'                 => self::ASSET_NAME,
					'browser_download_url' => $asset_url,
				),
			),
		);
	}

	private static function is_valid_release( $release ) {
		return is_array( $release ) && self::is_valid_version( self::release_version( $release ) ) && '' !== self::get_asset_url( $release );
	}

	private static function is_valid_version( $version ) {
		return is_string( $version ) && 1 === preg_match( '/^[0-9]+(?:\.[0-9A-Za-z-]+)+$/', $version );
	}

	private static function cache_release( array $release ) {
		$expiration = self::has_newer_version( $release ) ? 6 * HOUR_IN_SECONDS : 10 * MINUTE_IN_SECONDS;
		delete_site_transient( self::ERROR_TRANSIENT );
		set_site_transient( self::RELEASE_TRANSIENT, $release, $expiration );
	}

	private static function store_lookup_error( $response ) {
		$diagnostic = array( 'checked_at' => time() );
		if ( is_wp_error( $response ) ) {
			$diagnostic['type']    = 'wp_error';
			$diagnostic['message'] = $response->get_error_message();
		} else {
			$diagnostic['type']        = 'http_error';
			$diagnostic['status_code'] = wp_remote_retrieve_response_code( $response );
			$diagnostic['message']     = wp_remote_retrieve_response_message( $response );
			$diagnostic['body']        = substr( wp_strip_all_tags( wp_remote_retrieve_body( $response ) ), 0, 200 );
		}

		set_site_transient( self::ERROR_TRANSIENT, $diagnostic, 10 * MINUTE_IN_SECONDS );
	}

	private static function is_forced_update_check() {
		if ( ! is_admin() || ! current_user_can( 'update_plugins' ) ) {
			return false;
		}

		if ( isset( $_REQUEST['force-check'] ) || isset( $_REQUEST['aslc_check_updates'] ) ) {
			return true;
		}

		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
		return in_array( $action, array( 'update-selected', 'upgrade-plugin', 'do-plugin-upgrade' ), true );
	}

	private static function has_newer_version( array $release ) {
		return version_compare( self::release_version( $release ), ASLC_VERSION, '>' );
	}

	private static function release_version( array $release ) {
		return isset( $release['tag_name'] ) ? ltrim( (string) $release['tag_name'], 'vV' ) : '';
	}

	private static function get_asset_url( array $release ) {
		if ( empty( $release['assets'] ) || ! is_array( $release['assets'] ) ) {
			return '';
		}

		foreach ( $release['assets'] as $asset ) {
			if ( isset( $asset['name'], $asset['browser_download_url'] ) && self::ASSET_NAME === $asset['name'] ) {
				return esc_url_raw( (string) $asset['browser_download_url'] );
			}
		}

		return '';
	}

	private static function release_url( array $release ) {
		return ! empty( $release['html_url'] ) ? esc_url_raw( $release['html_url'] ) : self::repository_url();
	}

	private static function release_notes( array $release ) {
		return ! empty( $release['body'] ) ? (string) $release['body'] : 'No release notes were provided.';
	}

	private static function repository_url() {
		return 'https://github.com/' . self::OWNER . '/' . self::REPO;
	}

	private static function manual_update_check_result( $update_state ) {
		$plugin_file = plugin_basename( ASLC_PLUGIN_FILE );
		if ( is_object( $update_state ) && isset( $update_state->response ) && is_array( $update_state->response ) && isset( $update_state->response[ $plugin_file ] ) ) {
			return 'available';
		}

		return is_array( get_site_transient( self::RELEASE_TRANSIENT ) ) ? 'current' : 'failed';
	}

	private static function delete_caches() {
		delete_site_transient( self::RELEASE_TRANSIENT );
		delete_site_transient( self::ERROR_TRANSIENT );
	}
}

ASLC_GitHub_Updater::init();
