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

	public static function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject_update' ) );
		add_filter( 'site_transient_update_plugins', array( __CLASS__, 'inject_update' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_information' ), 10, 3 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'clear_release_cache' ), 10, 2 );
	}

	public static function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$plugin_file = plugin_basename( ASLC_PLUGIN_FILE );
		$release     = self::get_latest_release();

		if ( empty( $release ) || ! self::has_newer_version( $release ) ) {
			if ( isset( $transient->response[ $plugin_file ] ) ) {
				unset( $transient->response[ $plugin_file ] );
			}

			return $transient;
		}

		$package = self::get_asset_url( $release );
		if ( empty( $package ) ) {
			return $transient;
		}

		$transient->response[ $plugin_file ] = (object) array(
			'id'          => self::SLUG,
			'slug'        => self::SLUG,
			'plugin'      => $plugin_file,
			'new_version' => self::release_version( $release ),
			'url'         => 'https://github.com/' . self::OWNER . '/' . self::REPO,
			'package'     => $package,
			'tested'      => '6.5',
		);

		return $transient;
	}

	public static function plugin_information( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || self::SLUG !== $args->slug ) {
			return $result;
		}

		$release = self::get_latest_release();
		if ( empty( $release ) ) {
			return $result;
		}

		return (object) array(
			'name'          => 'AS Local CSS',
			'slug'          => self::SLUG,
			'version'       => self::release_version( $release ),
			'author'        => '<a href="https://github.com/cchatterton">AlphaSys</a>',
			'homepage'      => 'https://github.com/' . self::OWNER . '/' . self::REPO,
			'requires'      => '6.0',
			'requires_php'  => '7.4',
			'tested'        => '6.5',
			'download_link' => self::get_asset_url( $release ),
			'sections'      => array(
				'description' => 'Compatible AlphaSys-maintained release of Simple Custom CSS and JS.',
				'changelog'   => wp_kses_post( self::release_notes( $release ) ),
			),
		);
	}

	public static function clear_release_cache( $upgrader, $options ) {
		if ( empty( $options['type'] ) || 'plugin' !== $options['type'] ) {
			return;
		}

		delete_site_transient( self::RELEASE_TRANSIENT );
		delete_site_transient( self::ERROR_TRANSIENT );
	}

	private static function get_latest_release() {
		$cached_release = get_site_transient( self::RELEASE_TRANSIENT );
		if ( is_array( $cached_release ) ) {
			return $cached_release;
		}

		$response = wp_remote_get(
			self::API_URL,
			array(
				'timeout' => 8,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'AS Local CSS WordPress updater',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			set_site_transient( self::ERROR_TRANSIENT, $response->get_error_message(), 5 * MINUTE_IN_SECONDS );
			return array();
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $status_code || ! is_array( $body ) || empty( $body['tag_name'] ) ) {
			set_site_transient( self::ERROR_TRANSIENT, 'GitHub release lookup failed.', 5 * MINUTE_IN_SECONDS );
			return array();
		}

		delete_site_transient( self::ERROR_TRANSIENT );
		set_site_transient( self::RELEASE_TRANSIENT, $body, 30 * MINUTE_IN_SECONDS );

		return $body;
	}

	private static function has_newer_version( array $release ) {
		return version_compare( self::release_version( $release ), ASLC_VERSION, '>' );
	}

	private static function release_version( array $release ) {
		return ltrim( (string) $release['tag_name'], 'vV' );
	}

	private static function get_asset_url( array $release ) {
		if ( empty( $release['assets'] ) || ! is_array( $release['assets'] ) ) {
			return '';
		}

		foreach ( $release['assets'] as $asset ) {
			if ( ! empty( $asset['name'] ) && self::ASSET_NAME === $asset['name'] && ! empty( $asset['browser_download_url'] ) ) {
				return esc_url_raw( $asset['browser_download_url'] );
			}
		}

		return '';
	}

	private static function release_notes( array $release ) {
		if ( empty( $release['body'] ) ) {
			return 'No release notes were provided.';
		}

		return (string) $release['body'];
	}
}

ASLC_GitHub_Updater::init();
