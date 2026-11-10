<?php
/**
 * Admin Boost request policy.
 *
 * @package WPMastertoolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stateless URL matching and ordered decisions.
 */
class WPMastertoolkit_Admin_Boost_Policy {

	/**
	 * Validate the complete structured settings, including normalized output.
	 *
	 * @param mixed  $input Submitted settings.
	 * @param string $base Administration base path.
	 * @return array|WP_Error
	 * @throws InvalidArgumentException Internally caught and converted to WP_Error.
	 */
	public static function validate_settings( $input, $base ) {
		try {
			if ( ! is_array( $input ) || ! isset( $input['rules'], $input['pages'] ) || ! is_array( $input['rules'] ) || ! is_array( $input['pages'] ) || count( $input['rules'] ) > 100 || count( $input['pages'] ) > 50 ) {
				throw new InvalidArgumentException();
			}
			$output = array(
				'rules' => array(),
				'pages' => array(),
			);
			foreach ( $input['pages'] as $page ) {
				$output['pages'][] = self::validate_page( $page, $base );
			}
			$ids = array();
			foreach ( $input['rules'] as $rule ) {
				if ( ! is_array( $rule ) ) {
					throw new InvalidArgumentException();
				}
				foreach ( array( 'label', 'action', 'kind', 'value', 'method', 'scope' ) as $field ) {
					if ( ! isset( $rule[ $field ] ) || ! is_string( $rule[ $field ] ) ) {
						throw new InvalidArgumentException();
					}
				}
				if ( ! in_array( $rule['action'], array( 'allow', 'block' ), true ) || ! in_array( $rule['kind'], array( 'endpoint', 'prefix', 'domain', 'subdomains', 'regex' ), true ) || ! in_array( $rule['method'], array( '*', 'GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS', 'TRACE', 'CONNECT' ), true ) || ! in_array( $rule['scope'], array( 'boost', 'permanent' ), true ) ) {
					throw new InvalidArgumentException();
				}
				$value = trim( $rule['value'] );
				if ( '' === $value || strlen( $value ) > 2048 || ( 'regex' !== $rule['kind'] && preg_match( '/[\x00-\x20\x7f]/', $value ) ) ) {
					throw new InvalidArgumentException();
				}
				if ( 'regex' === $rule['kind'] ) {
					if ( false === self::regex( $value, '' ) ) {
						throw new InvalidArgumentException();
					}
				} elseif ( in_array( $rule['kind'], array( 'domain', 'subdomains' ), true ) ) {
					$value = strtolower( rtrim( $value, '.' ) );
					if ( strlen( $value ) > 253 || ! preg_match( '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $value ) ) {
						throw new InvalidArgumentException();
					}
				} else {
					$parts = wp_parse_url( $value );
					if ( ! is_array( $parts ) || empty( $parts['host'] ) || ! in_array( $parts['scheme'] ?? '', array( 'http', 'https' ), true ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) || false !== strpos( $value, '[redacted]' ) ) {
						throw new InvalidArgumentException();
					}
					$value = esc_url_raw( $value, array( 'http', 'https' ) );
				}
				$id = $rule['id'] ?? wp_generate_uuid4();
				if ( ! is_string( $id ) || ! preg_match( '/^[a-zA-Z0-9_-]{1,64}$/', $id ) || isset( $ids[ $id ] ) ) {
					throw new InvalidArgumentException();
				}
				$ids[ $id ]        = true;
				$output['rules'][] = array(
					'id'      => $id,
					'label'   => sanitize_text_field( substr( $rule['label'], 0, 80 ) ),
					'action'  => $rule['action'],
					'kind'    => $rule['kind'],
					'value'   => $value,
					'method'  => $rule['method'],
					'scope'   => $rule['scope'],
					'enabled' => in_array( $rule['enabled'] ?? false, array( true, 1, '1' ), true ),
					'page'    => empty( $rule['page'] ) ? array() : self::validate_page( $rule['page'], $base ),
				);
			}
			return $output;
		} catch ( InvalidArgumentException $exception ) {
			return new WP_Error( 'wpmtk_admin_boost_settings', __( 'Invalid settings. Check destinations, page criteria and regular expressions. Maximum: 100 rules and 50 page exclusions. Destination URLs must not contain credentials, query strings or redacted values.', 'wpmastertoolkit' ) );
		}
	}

	/**
	 * Validate a normalized page matcher.
	 *
	 * @param mixed  $page Page criteria.
	 * @param string $base Administration base path.
	 * @return array
	 * @throws InvalidArgumentException When input is malformed.
	 */
	private static function validate_page( $page, $base ) {
		if ( ! is_array( $page ) || ! isset( $page['kind'], $page['value'] ) || ! is_string( $page['value'] ) || ! in_array( $page['kind'], array( 'exact', 'prefix', 'regex' ), true ) || '' === trim( $page['value'] ) || strlen( $page['value'] ) > 2048 ) {
			throw new InvalidArgumentException();
		}
		$value = trim( $page['value'] );
		if ( 'regex' === $page['kind'] ) {
			if ( false === self::regex( $value, '' ) ) {
				throw new InvalidArgumentException();
			}
		} else {
			if ( false !== strpos( $value, '?' ) || false !== strpos( $value, '#' ) || false !== strpos( $value, '://' ) ) {
				throw new InvalidArgumentException();
			}
			$value = self::page_path( $value, $base );
			if ( '' === $value ) {
				throw new InvalidArgumentException();
			}
		}
		$query = $page['query'] ?? array();
		if ( ! is_array( $query ) || count( $query ) > 20 ) {
			throw new InvalidArgumentException();
		}
		foreach ( $query as $key => $expected ) {
			if ( ! is_string( $key ) || ! preg_match( '/^[a-zA-Z0-9_-]{1,64}$/', $key ) || ! is_string( $expected ) || strlen( $expected ) > 256 ) {
				throw new InvalidArgumentException();
			}
			$query[ $key ] = sanitize_text_field( $expected );
		}
		ksort( $query );
		$absent = $page['absent'] ?? array();
		if ( ! is_array( $absent ) || count( $absent ) > 20 ) {
			throw new InvalidArgumentException();
		}
		foreach ( $absent as $key ) {
			if ( ! is_string( $key ) || ! preg_match( '/^[a-zA-Z0-9_-]{1,64}$/', $key ) || array_key_exists( $key, $query ) ) {
				throw new InvalidArgumentException();
			}
		}
		$output = array(
			'kind'  => $page['kind'],
			'value' => $value,
			'query' => $query,
		);
		if ( $absent ) {
			$output['absent'] = array_values( array_unique( $absent ) );
			sort( $output['absent'] );
		}
		return $output;
	}

	/**
	 * Normalize an admin path relative to its actual installation.
	 *
	 * @param string $path Request path.
	 * @param string $base Administration base path.
	 * @return string
	 */
	public static function page_path( $path, $base ) {
		$path = wp_parse_url( $path, PHP_URL_PATH );
		$base = trailingslashit( $base );
		if ( ! is_string( $path ) || false !== strpos( $path, '\\' ) || preg_match( '~%(?:2f|5c|00)~i', $path ) ) {
			return '';
		}
		$path = rawurldecode( $path );
		if ( in_array( '..', explode( '/', $path ), true ) || in_array( '.', explode( '/', $path ), true ) ) {
			return '';
		}
		if ( 0 === strpos( $path, $base ) ) {
			$path = substr( $path, strlen( $base ) );
		} elseif ( 0 === strpos( $path, '/wp-admin/' ) ) {
			$path = substr( $path, 10 );
		}
		return '/' . ltrim( $path, '/' );
	}

	/**
	 * Match a bounded, delimiter-free regular expression.
	 *
	 * @param string $expression Expression without delimiters.
	 * @param string $subject Example or live URL.
	 * @return int|false
	 */
	public static function regex( $expression, $subject ) {
		if ( ! is_string( $expression ) || '' === $expression || strlen( $expression ) > 512 || ! is_string( $subject ) || strlen( $subject ) > 2048 ) {
			return false;
		}
		$pattern = '~(*LIMIT_MATCH=10000)(*LIMIT_RECURSION=100)' . str_replace( '~', '\\~', $expression ) . '~u';
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Invalid user expressions are rejected without exposing PHP warnings.
		return @preg_match( $pattern, $subject );
	}

	/**
	 * Identify same-site and explicit local destinations without DNS lookups.
	 *
	 * @param string $url Destination URL.
	 * @param array  $site_urls Site and WordPress URLs.
	 * @return bool
	 */
	public static function internal( $url, $site_urls ) {
		$host = strtolower( rtrim( (string) wp_parse_url( $url, PHP_URL_HOST ), '.' ) );
		$ip   = trim( $host, '[]' );
		if ( '' === $host || 'localhost' === $host || '.localhost' === substr( $host, -10 ) ) {
			return true;
		}
		foreach ( $site_urls as $site_url ) {
			if ( strtolower( rtrim( (string) wp_parse_url( $site_url, PHP_URL_HOST ), '.' ) ) === $host ) {
				return true;
			}
		}
		if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
		}
		return false;
	}

	/**
	 * Match an administration page, with query criteria independent of order.
	 *
	 * @param array $matcher Page criteria.
	 * @param array $page Normalized path and query.
	 * @return bool
	 */
	public static function page_matches( $matcher, $page ) {
		$kind  = $matcher['kind'] ?? 'exact';
		$value = $matcher['value'] ?? '';
		$path  = $page['path'] ?? '';
		if ( 'regex' === $kind ) {
			$query = $page['query'] ?? array();
			ksort( $query );
			if ( 1 !== self::regex( $value, $path . ( $query ? '?' . http_build_query( $query ) : '' ) ) ) {
				return false;
			}
		} elseif ( 'prefix' === $kind ? 0 !== strpos( $path, $value ) : $path !== $value ) {
			return false;
		}
		foreach ( $matcher['query'] ?? array() as $key => $expected ) {
			$actual = $page['query'][ $key ] ?? null;
			if ( ! is_scalar( $actual ) || (string) $actual !== (string) $expected ) {
				return false;
			}
		}
		foreach ( $matcher['absent'] ?? array() as $key ) {
			if ( array_key_exists( $key, $page['query'] ?? array() ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Match a remote URL, without confusing a domain with a suffix attack.
	 *
	 * @param array  $rule Request rule.
	 * @param string $url Destination URL.
	 * @return bool
	 */
	public static function destination_matches( $rule, $url ) {
		$kind  = $rule['kind'] ?? '';
		$value = $rule['value'] ?? '';
		if ( 'regex' === $kind ) {
			return 1 === self::regex( $value, $url );
		}
		$remote = wp_parse_url( $url );
		if ( ! is_array( $remote ) || empty( $remote['host'] ) ) {
			return false;
		}
		$host = strtolower( rtrim( $remote['host'], '.' ) );
		if ( in_array( $kind, array( 'domain', 'subdomains' ), true ) ) {
			$value  = strtolower( rtrim( $value, '.' ) );
			$suffix = '.' . $value;
			return $host === $value || ( 'subdomains' === $kind && strlen( $host ) > strlen( $value ) && substr( $host, -strlen( $suffix ) ) === $suffix );
		}
		$target = wp_parse_url( $value );
		if ( ! is_array( $target ) || empty( $target['host'] ) || strtolower( rtrim( $target['host'], '.' ) ) !== $host ) {
			return false;
		}
		$remote_port = $remote['port'] ?? ( 'https' === ( $remote['scheme'] ?? '' ) ? 443 : 80 );
		$target_port = $target['port'] ?? ( 'https' === ( $target['scheme'] ?? '' ) ? 443 : 80 );
		if ( $remote_port !== $target_port || ( $target['scheme'] ?? '' ) !== ( $remote['scheme'] ?? '' ) ) {
			return false;
		}
		$path = $remote['path'] ?? '/';
		$want = $target['path'] ?? '/';
		return 'prefix' === $kind ? 0 === strpos( $path, $want ) : $path === $want;
	}

	/**
	 * Evaluate page exclusions, ordered rules, then temporary boost.
	 *
	 * @param string $url Remote URL.
	 * @param string $method HTTP method.
	 * @param array  $page Normalized administration page.
	 * @param array  $settings Validated settings.
	 * @param bool   $boost Whether this session is boosted.
	 * @param bool   $pro Whether Pro is available.
	 * @return array
	 */
	public static function decide( $url, $method, $page, $settings, $boost, $pro ) {
		if ( $pro ) {
			foreach ( $settings['pages'] ?? array() as $excluded ) {
				if ( self::page_matches( $excluded, $page ) ) {
					return array(
						'decision' => 'allowed',
						'reason'   => 'page_exclusion',
						'rule'     => '',
					);
				}
			}
			foreach ( $settings['rules'] ?? array() as $rule ) {
				if ( empty( $rule['enabled'] ) || ( 'boost' === $rule['scope'] && ! $boost ) ) {
					continue;
				}
				if ( '*' !== $rule['method'] && strtoupper( $method ) !== $rule['method'] ) {
					continue;
				}
				if ( ! empty( $rule['page'] ) && ! self::page_matches( $rule['page'], $page ) ) {
					continue;
				}
				if ( self::destination_matches( $rule, $url ) ) {
					return array(
						'decision' => 'block' === $rule['action'] ? 'blocked' : 'allowed',
						'reason'   => 'rule',
						'rule'     => $rule['id'],
					);
				}
			}
		}
		return array(
			'decision' => $boost ? 'blocked' : 'allowed',
			'reason'   => $boost ? 'boost' : 'inactive',
			'rule'     => '',
		);
	}

	/**
	 * Produce a deliberately lossy journal destination, never a raw URL.
	 *
	 * @param string $url Remote URL.
	 * @return array
	 */
	public static function journal_destination( $url ) {
		$parts           = wp_parse_url( $url );
		$path            = substr( $parts['path'] ?? '/', 0, 2048 );
		$clean           = array();
		$hide            = false;
		$public_segments = array( '', 'api', 'rest', 'wp-json', 'wp', 'wordpress', 'plugins', 'plugin', 'themes', 'theme', 'update', 'updates', 'check', 'version', 'versions', 'license', 'licence', 'licenses', 'licences', 'activate', 'deactivate', 'verify', 'status', 'auth', 'oauth', 'token', 'telemetry', 'stats', 'statistics', 'events', 'event', 'track', 'collect', 'ping', 'health', 'integrations', 'library', 'templates', 'assets', 'content', 'download', 'downloads', 'index.php', 'admin-ajax.php', 'endpoint', 'public', 'service', 'services', 'products', 'product', 'wpmastertoolkit', 'webdeclic' );
		foreach ( explode( '/', $path ) as $segment ) {
			$decoded = rawurldecode( $segment );
			$safe    = ! $hide && ( in_array( $decoded, $public_segments, true ) || preg_match( '/^v[0-9]{1,2}$/', $decoded ) );
			$clean[] = $safe ? $decoded : '[redacted]';
			$hide    = $hide || (bool) preg_match( '/(?:token|secret|password|key|auth|license|licence|session|email)/i', $decoded );
		}
		$host = strtolower( (string) ( $parts['host'] ?? '' ) );
		return array(
			'host'     => sanitize_text_field( substr( $host, 0, 253 ) ),
			'path'     => substr( implode( '/', $clean ), 0, 512 ),
			'scheme'   => 'http' === ( $parts['scheme'] ?? '' ) ? 'http' : 'https',
			'port'     => isset( $parts['port'] ) ? (int) $parts['port'] : null,
			'redacted' => isset( $parts['query'] ) || isset( $parts['user'] ) || strlen( $parts['path'] ?? '' ) > 512 || false !== strpos( implode( '/', $clean ), '[redacted]' ),
		);
	}
}
