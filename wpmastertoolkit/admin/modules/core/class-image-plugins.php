<?php
/**
 * Module Name: Image plugins
 *
 * @package WPMastertoolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Directory icons for the Installed Plugins screen.
 */
class WPMastertoolkit_Image_Plugins {

	const ACTION = 'wpmtk_image_plugins';

	/** Register hooks only while the module is enabled. */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'fetch_icons' ) );
	}

	/**
	 * Extract an explicit directory identity, never a fuzzy name match.
	 *
	 * @param mixed $url Directory URL.
	 * @return string
	 */
	private function directory_slug( $url ) {
		$parts = is_string( $url ) ? wp_parse_url( $url ) : false;
		if ( ! is_array( $parts ) || ! in_array( $parts['scheme'] ?? '', array( 'http', 'https' ), true ) || 'wordpress.org' !== ( $parts['host'] ?? '' ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['port'] ) ) {
			return '';
		}
		return preg_match( '#^/plugins/([a-z0-9-]+)/?$#D', $parts['path'] ?? '', $matches ) ? $matches[1] : '';
	}

	/**
	 * Resolve identity using explicit headers or authoritative update metadata.
	 *
	 * @param array $plugin Installed plugin headers.
	 * @param array $record Update metadata.
	 * @return string
	 */
	private function plugin_slug( $plugin, $record ) {
		$update_uri = $plugin['UpdateURI'] ?? '';
		if ( ! empty( $update_uri ) ) {
			return $this->directory_slug( $update_uri );
		}
		$explicit = $this->directory_slug( $plugin['PluginURI'] ?? '' );
		if ( $explicit ) {
			return $explicit;
		}
		$slug = $record['slug'] ?? '';
		if ( ! is_string( $slug ) || ! preg_match( '/^[a-z0-9-]+$/D', $slug ) ) {
			return '';
		}
		foreach ( array( 'id', 'url' ) as $field ) {
			if ( $slug === $this->directory_slug( $record[ $field ] ?? '' ) ) {
				return $slug;
			}
		}
		return '';
	}

	/**
	 * Select a catalog candidate without changing the plugin's update identity.
	 *
	 * @param array  $plugin Installed headers.
	 * @param string $file Installed basename.
	 * @return string
	 */
	private function catalog_slug( $plugin, $file ) {
		if ( 'elementor-pro/elementor-pro.php' === $file ) {
			return 'elementor';
		}
		static $catalog = null;
		if ( null === $catalog ) {
			$catalog = require WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/helpers/core/image-plugins/catalog.php';
		}
		$name = $plugin['Name'] ?? '';
		foreach ( $catalog as $slug => $names ) {
			if ( in_array( $name, $names, true ) ) {
				return $slug;
			}
		}
		return '';
	}

	/**
	 * Normalize a vendor host without accepting arbitrary suffix matches.
	 *
	 * @param mixed $url Vendor URL.
	 * @return string
	 */
	private function vendor_host( $url ) {
		$parts = is_string( $url ) ? wp_parse_url( $url ) : false;
		if ( ! is_array( $parts ) || ! in_array( $parts['scheme'] ?? '', array( 'http', 'https' ), true ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['port'] ) ) {
			return '';
		}
		$host = strtolower( $parts['host'] ?? '' );
		$host = preg_replace( '/^www\./', '', $host );
		return in_array( $host, array( 'wordpress.org', 'wordpress.com' ), true ) ? '' : $host;
	}

	/**
	 * Corroborate a header match using directory-supplied vendor metadata.
	 *
	 * @param array  $plugin Installed headers.
	 * @param string $homepage Directory-supplied homepage.
	 * @return bool
	 */
	private function same_vendor( $plugin, $homepage ) {
		$host = $this->vendor_host( $homepage );
		if ( ! $host ) {
			return false;
		}
		foreach ( array( 'PluginURI', 'AuthorURI' ) as $header ) {
			if ( $host === $this->vendor_host( $plugin[ $header ] ?? '' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Match only bundled vendor exceptions with corroborated installed headers.
	 *
	 * @param array $plugin Installed plugin headers.
	 * @return string
	 */
	private function exception_icon( $plugin ) {
		static $exceptions = null;
		if ( null === $exceptions ) {
			$exceptions = require WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/helpers/core/image-plugins/exceptions.php';
		}
		$exception = $exceptions[ $plugin['Name'] ?? '' ] ?? null;
		if ( ! is_array( $exception ) || ! $this->same_vendor( $plugin, $exception['homepage'] ) ) {
			return '';
		}
		return esc_url_raw( $exception['url'], array( 'https' ) );
	}

	/**
	 * Accept only HTTPS images from this plugin's directory assets.
	 *
	 * @param mixed  $icons Candidate icons.
	 * @param string $slug Verified directory slug.
	 * @return string
	 */
	private function icon_url( $icons, $slug ) {
		if ( ! $slug || ! is_array( $icons ) ) {
			return '';
		}
		foreach ( array( '2x', '1x', 'svg' ) as $size ) {
			$url   = $icons[ $size ] ?? '';
			$parts = is_string( $url ) ? wp_parse_url( $url ) : false;
			// phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- Hostname validation only; the sniff matches s.w.org inside ps.w.org.
			if ( is_array( $parts ) && 'https' === ( $parts['scheme'] ?? '' ) && 'ps.w.org' === ( $parts['host'] ?? '' ) && ! isset( $parts['user'] ) && ! isset( $parts['pass'] ) && ! isset( $parts['port'] ) && preg_match( '#^/' . preg_quote( $slug, '#' ) . '/assets/icon[-a-zA-Z0-9_.]*\.(?:png|jpe?g|gif|svg|webp)$#D', $parts['path'] ?? '' ) ) {
				return esc_url_raw( $url, array( 'https' ) );
			}
		}
		return '';
	}

	/**
	 * Read cached icons without performing network requests.
	 *
	 * @return array
	 */
	private function entries() {
		$updates = get_site_transient( 'update_plugins' );
		$entries = array();
		foreach ( get_plugins() as $file => $plugin ) {
			$exception = WPMASTERTOOLKIT_BASENAME === $file ? '' : $this->exception_icon( $plugin );
			if ( $exception ) {
				$entries[ $file ] = array(
					'slug'    => '',
					'source'  => 'vendor',
					'url'     => $exception,
					'pending' => false,
				);
				continue;
			}
			$record     = $updates->response[ $file ] ?? $updates->no_update[ $file ] ?? array();
			$record     = is_object( $record ) ? get_object_vars( $record ) : $record;
			$record     = is_array( $record ) ? $record : array();
			$native     = WPMASTERTOOLKIT_BASENAME === $file ? 'wpmastertoolkit' : $this->plugin_slug( $plugin, $record );
			$mapped     = $native ? '' : $this->catalog_slug( $plugin, $file );
			$slug       = $native ? $native : $mapped;
			$product_id = $slug ? 0 : $this->woo_product_id( $file );
			if ( $product_id ) {
				$cached           = get_site_transient( 'wpmtk_woo_icon_' . $product_id );
				$entries[ $file ] = array(
					'slug'    => '',
					'source'  => 'woocommerce',
					'url'     => $this->woo_icon_url( is_array( $cached ) ? ( $cached['url'] ?? '' ) : '' ),
					'pending' => false === $cached,
				);
				continue;
			}
			$cached = $slug ? get_site_transient( 'wpmtk_plugin_icon_' . md5( $slug ) ) : false;
			$verify = $mapped && $mapped !== $native && 'elementor-pro/elementor-pro.php' !== $file;
			$known  = ! $verify || ( is_array( $cached ) && $this->same_vendor( $plugin, $cached['homepage'] ?? '' ) );
			$url    = $this->icon_url( $record['icons'] ?? array(), $slug );
			if ( ! $url && is_array( $cached ) ) {
				$url = $this->icon_url( array( '1x' => $cached['url'] ?? '' ), $slug );
			}
			$entries[ $file ] = array(
				'slug'    => $slug,
				'url'     => $known ? $url : '',
				'pending' => (bool) ( $slug && ( ( ! $url && false === $cached ) || ( $verify && ( ! is_array( $cached ) || ! array_key_exists( 'homepage', $cached ) ) ) ) ),
			);
		}
		return $entries;
	}

	/**
	 * Read marketplace identity independently of active plugin header filters.
	 *
	 * @param string $file Installed plugin basename.
	 * @return int
	 */
	private function woo_product_id( $file ) {
		if ( 0 !== validate_file( $file ) || ! is_file( WP_PLUGIN_DIR . '/' . $file ) ) {
			return 0;
		}
		$headers = get_file_data( WP_PLUGIN_DIR . '/' . $file, array( 'Woo' => 'Woo' ), '' );
		$value   = trim( $headers['Woo'] ?? '' );
		if ( ! preg_match( '/^([1-9][0-9]{0,9}):[^\s:]+$/D', $value, $matches ) ) {
			return 0;
		}
		return (int) $matches[1];
	}

	/**
	 * Accept only dedicated images hosted by the marketplace.
	 *
	 * @param mixed $url Candidate icon URL.
	 * @return string
	 */
	private function woo_icon_url( $url ) {
		$parts = is_string( $url ) ? wp_parse_url( $url ) : false;
		if ( ! is_array( $parts ) || 'https' !== ( $parts['scheme'] ?? '' ) || ! in_array( $parts['host'] ?? '', array( 'woocommerce.com', 'www.woocommerce.com' ), true ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['port'] ) ) {
			return '';
		}
		$path = $parts['path'] ?? '';
		if ( 0 !== strpos( $path, '/wp-content/uploads/' ) || false !== strpos( rawurldecode( $path ), '..' ) || ! preg_match( '/\.(?:png|jpe?g|gif|webp|svg)$/iD', $path ) ) {
			return '';
		}
		return esc_url_raw( $url, array( 'https' ) );
	}

	/**
	 * Fetch only public marketplace artwork, never account or license data.
	 *
	 * @param int    $product_id Marketplace ID from the Woo header.
	 * @param string $name Installed plugin name used as a search hint.
	 * @return void
	 */
	private function lookup_woo( $product_id, $name ) {
		$key = 'wpmtk_woo_icon_' . $product_id;
		if ( false !== get_site_transient( $key ) ) {
			return;
		}
		set_site_transient( $key, array( 'url' => '' ), HOUR_IN_SECONDS );
		$response = wp_safe_remote_get(
			add_query_arg( array( 'term' => $name ), 'https://woocommerce.com/wp-json/wccom-extensions/1.0/search' ),
			array(
				'timeout'             => 3,
				'redirection'         => 0,
				'limit_response_size' => 524288,
				'cookies'             => array(),
			)
		);
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return;
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || ! is_array( $data['products'] ?? null ) ) {
			return;
		}
		foreach ( $data['products'] as $product ) {
			if ( ! is_array( $product ) || ! is_scalar( $product['id'] ?? null ) || (string) ( $product['id'] ?? '' ) !== (string) $product_id ) {
				continue;
			}
			$url = $this->woo_icon_url( $product['icon'] ?? '' );
			if ( $url ) {
				set_site_transient( $key, array( 'url' => $url ), 7 * DAY_IN_SECONDS );
			}
			return;
		}
	}

	/** Enqueue only on site and network Installed Plugins screens. */
	public function enqueue_assets() {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'plugins', 'plugins-network' ), true ) || ! $this->can_view() ) {
			return;
		}
		$base  = 'admin/assets/build/core/image-plugins';
		$asset = include WPMASTERTOOLKIT_PLUGIN_PATH . $base . '.asset.php';
		wp_enqueue_script( self::ACTION, WPMASTERTOOLKIT_PLUGIN_URL . $base . '.js', $asset['dependencies'], $asset['version'], true );
		wp_enqueue_style( self::ACTION, WPMASTERTOOLKIT_PLUGIN_URL . $base . '.css', array( 'dashicons' ), $asset['version'] );
		wp_localize_script(
			self::ACTION,
			'wpmtkImagePlugins',
			array(
				'ajax'    => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::ACTION ),
				'entries' => $this->entries(),
			)
		);
	}

	/**
	 * Check access to plugin management in either administration context.
	 *
	 * @return bool
	 */
	private function can_view() {
		return current_user_can( 'activate_plugins' ) || current_user_can( 'manage_network_plugins' );
	}

	/** Fetch a bounded batch of server-verified installed plugin identities. */
	public function fetch_icons() {
		if ( ! $this->can_view() ) {
			wp_send_json_error( null, 403 );
		}
		check_ajax_referer( self::ACTION, 'nonce' );
		$files = isset( $_POST['plugins'] ) && is_array( $_POST['plugins'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['plugins'] ) ) : array();
		if ( ! $files || count( $files ) > 3 ) {
			wp_send_json_error( null, 400 );
		}
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$entries = $this->entries();
		$plugins = get_plugins();
		$result  = array();
		foreach ( array_unique( $files ) as $file ) {
			if ( ! isset( $entries[ $file ] ) ) {
				continue;
			}
			$entry = $entries[ $file ];
			if ( $entry['pending'] ) {
				if ( 'woocommerce' === ( $entry['source'] ?? '' ) ) {
					$product_id = $this->woo_product_id( $file );
					if ( $product_id ) {
						$this->lookup_woo( $product_id, $plugins[ $file ]['Name'] );
					}
				} else {
					$this->lookup( $entry['slug'] );
				}
				$refreshed = $this->entries();
				$entry     = $refreshed[ $file ];
			}
			$result[ $file ] = $entry['url'];
		}
		wp_send_json_success( $result );
	}

	/**
	 * Fetch metadata through WordPress HTTP, honoring request-blocking filters.
	 *
	 * @param string $slug Verified directory slug.
	 * @return string
	 */
	private function lookup( $slug ) {
		$key    = 'wpmtk_plugin_icon_' . md5( $slug );
		$cached = get_site_transient( $key );
		if ( is_array( $cached ) && array_key_exists( 'homepage', $cached ) ) {
			return $this->icon_url( array( '1x' => $cached['url'] ?? '' ), $slug );
		}
		set_site_transient(
			$key,
			array(
				'url'      => '',
				'homepage' => '',
			),
			HOUR_IN_SECONDS
		);
		$response = wp_safe_remote_get(
			add_query_arg(
				array(
					'action'  => 'plugin_information',
					'request' => array(
						'slug'   => $slug,
						'fields' => array(
							'icons'    => true,
							'homepage' => true,
							'sections' => false,
							'banners'  => false,
						),
					),
				),
				'https://api.wordpress.org/plugins/info/1.2/'
			),
			array(
				'timeout'             => 3,
				'redirection'         => 0,
				'limit_response_size' => 131072,
			)
		);
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return '';
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$url  = is_array( $data ) && ( $data['slug'] ?? '' ) === $slug ? $this->icon_url( $data['icons'] ?? array(), $slug ) : '';
		if ( $url ) {
			set_site_transient(
				$key,
				array(
					'url'      => $url,
					'homepage' => is_string( $data['homepage'] ?? null ) ? $data['homepage'] : '',
				),
				7 * DAY_IN_SECONDS
			);
		}
		return $url;
	}
}
