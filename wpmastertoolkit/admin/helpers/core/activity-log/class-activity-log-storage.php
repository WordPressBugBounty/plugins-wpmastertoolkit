<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Append-only, web-guarded activity log storage.
 * 
 * @since 2.26.0
 */
class WPMastertoolkit_Activity_Log_Storage {

	private const HEADER         = "<?php http_response_code(404); header('X-WPMTK-Activity-Log: guarded'); exit; __halt_compiler();\n";
	private const MAX_FILE_SIZE  = 10485760;
	public const MAX_RECORD_SIZE = 4096;
	private const PROBE_INTERVAL = 600;
	private const HEALTH_OPTION  = 'wpmastertoolkit_activity_log_health';

	/**
	 * Get the directory for the current site.
	 *
	 * @return string|false Directory path or false on failure.
	 * @since 2.26.0
	 */
	public static function directory() {
		global $wp_filesystem;

		$base = wpmastertoolkit_folders();
		if ( ! $wp_filesystem || 'direct' !== $wp_filesystem->method || is_link( $base ) || ! is_dir( $base ) ) {
			return false;
		}

		$parent = $base . '/activity-log';
		$path   = $parent . '/' . get_current_blog_id();
		foreach ( array( $parent, $path ) as $directory ) {
			if ( is_link( $directory ) || ( ! is_dir( $directory ) && ! wp_mkdir_p( $directory ) ) ) {
				return false;
			}
			if ( ! $wp_filesystem->chmod( $directory, 0700 ) ) {
				return false;
			}
		}

		$canonical_parent = realpath( $parent );
		$canonical_path   = realpath( $path );
		return $canonical_parent && $canonical_path === $canonical_parent . '/' . get_current_blog_id() && wp_is_writable( $canonical_path ) ? $canonical_path : false;
	}

	/**
	 * Verify that the live web server does not serve log source code.
	 *
	 * @param string $directory Site directory.
	 * @return bool Whether source protection was verified.
	 * @since 2.26.0
	 */
	private static function is_guarded( $directory ) {
		global $wp_filesystem;

		$cache_key = 'wpmastertoolkit_activity_guard_' . get_current_blog_id();
		if ( 1 === get_transient( $cache_key ) ) {
			return true;
		}
		if ( 'blocked' === get_transient( $cache_key ) ) {
			return false;
		}

		$filename = 'probe-' . wp_generate_password( 20, false, false ) . '.php';
		$marker   = wp_generate_password( 32, false, false );
		$path     = $directory . '/' . $filename;
		// phpcs:disable WordPress.WP.AlternativeFunctions -- Exclusive creation and checked writes protect the probe from races.
		$file     = @fopen( $path, 'x+b' );
		// phpcs:enable WordPress.WP.AlternativeFunctions
		if ( false === $file ) {
			return false;
		}
		if ( ! $wp_filesystem->chmod( $path, 0600 ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close the exclusive probe stream before deleting it.
			fclose( $file );
			wp_delete_file( $path );
			return false;
		}

		$contents = self::HEADER . $marker;
		// phpcs:disable WordPress.WP.AlternativeFunctions -- A partial probe write must be detected on the open stream.
		$written  = fwrite( $file, $contents );
		fflush( $file );
		fclose( $file );
		// phpcs:enable WordPress.WP.AlternativeFunctions
		if ( strlen( $contents ) !== $written ) {
			wp_delete_file( $path );
			return false;
		}

		$url      = content_url( 'wpmastertoolkit/activity-log/' . get_current_blog_id() . '/' . $filename );
		$response = wp_remote_get( add_query_arg( 'probe', $marker, $url ), array( 'timeout' => 5, 'redirection' => 0, 'limit_response_size' => 1024, 'headers' => array( 'Cache-Control' => 'no-cache' ) ) );
		wp_delete_file( $path );
		$removed = ! file_exists( $path );

		$verified = ! is_wp_error( $response )
			&& $removed
			&& 404 === wp_remote_retrieve_response_code( $response )
			&& 'guarded' === wp_remote_retrieve_header( $response, 'x-wpmtk-activity-log' )
			&& false === strpos( wp_remote_retrieve_body( $response ), $marker );
		if ( $verified ) {
			set_transient( $cache_key, 1, self::PROBE_INTERVAL );
		} else {
			set_transient( $cache_key, 'blocked', MINUTE_IN_SECONDS );
		}
		return $verified;
	}

	/**
	 * Append a record after checking the protection and header of its segment.
	 *
	 * @param array $record Normalized record.
	 * @return bool Whether the record was persisted.
	 * @since 2.26.0
	 */
	public static function append( $record ) {
		global $wp_filesystem;

		$directory = self::directory();
		if ( false === $directory || ! self::is_guarded( $directory ) ) {
			self::set_health( 'unprotected' );
			return false;
		}

		$json = wp_json_encode( $record, JSON_INVALID_UTF8_SUBSTITUTE );
		if ( ! is_string( $json ) ) {
			return false;
		}
		$line = $json . "\n";
		if ( strlen( $line ) > self::MAX_RECORD_SIZE ) {
			return false;
		}

		$lock_path = $directory . '/writer.php';
		// phpcs:disable WordPress.WP.AlternativeFunctions -- A shared stream lock is needed for atomic append and rotation.
		$lock      = is_link( $lock_path ) ? false : @fopen( $lock_path, 'c+b' );
		if ( false === $lock || ! flock( $lock, LOCK_EX ) ) {
			if ( false !== $lock ) {
				fclose( $lock );
			}
			self::set_health( 'write_failed' );
			return false;
		}

		$result = false;
		if ( self::ensure_header( $lock ) && $wp_filesystem->chmod( $lock_path, 0600 ) ) {
			$day = gmdate( 'Y-m-d' );
			for ( $segment = 1; $segment <= 999; $segment++ ) {
				$path = sprintf( '%s/activity-%s-%06d.php', $directory, $day, $segment );
				if ( is_link( $path ) ) {
					break;
				}
				$file = @fopen( $path, 'c+b' );
				if ( false === $file ) {
					break;
				}
				if ( ! self::ensure_header( $file ) ) {
					fclose( $file );
					break;
				}
				if ( ! $wp_filesystem->chmod( $path, 0600 ) ) {
					fclose( $file );
					break;
				}
				$size = fstat( $file )['size'];
				if ( $size + strlen( $line ) > self::MAX_FILE_SIZE ) {
					fclose( $file );
					continue;
				}
				fseek( $file, 0, SEEK_END );
				$result = strlen( $line ) === fwrite( $file, $line ) && fflush( $file );
				fclose( $file );
				break;
			}
		}

		flock( $lock, LOCK_UN );
		fclose( $lock );
		// phpcs:enable WordPress.WP.AlternativeFunctions
		self::set_health( $result ? 'ok' : 'write_failed' );
		return $result;
	}

	/**
	 * Initialize an empty guarded PHP file or validate an existing one.
	 *
	 * @param resource $file Open file handle.
	 * @return bool Whether the header is intact.
	 * @since 2.26.0
	 */
	private static function ensure_header( $file ) {
		$size = fstat( $file )['size'];
		rewind( $file );
		// phpcs:disable WordPress.WP.AlternativeFunctions -- Inspect and initialize the header on the locked stream.
		if ( 0 === $size ) {
			return strlen( self::HEADER ) === fwrite( $file, self::HEADER ) && fflush( $file );
		}
		return $size >= strlen( self::HEADER ) && self::HEADER === fread( $file, strlen( self::HEADER ) );
		// phpcs:enable WordPress.WP.AlternativeFunctions
	}

	/**
	 * Surface failures without writing an option for every successful event.
	 *
	 * @param string $health Current storage status.
	 * @since 2.26.0
	 */
	private static function set_health( $health ) {
		if ( $health !== get_option( self::HEALTH_OPTION ) ) {
			update_option( self::HEALTH_OPTION, $health, false );
		}
	}

	/**
	 * List activity segments belonging to the current site.
	 *
	 * @return array Available filenames, newest first.
	 * @since 2.26.0
	 */
	public static function available_files() {
		$directory = self::directory();
		if ( false === $directory ) {
			return array();
		}

		$files = glob( $directory . '/activity-????-??-??-??????.php' );
		if ( ! is_array( $files ) ) {
			return array();
		}

		$filenames = array();
		foreach ( $files as $path ) {
			if ( is_link( $path ) || ! is_file( $path ) || ! preg_match( '/^activity-(\d{4})-(\d{2})-(\d{2})-\d{6}\.php$/D', basename( $path ), $matches ) || ! checkdate( (int) $matches[2], (int) $matches[3], (int) $matches[1] ) ) {
				continue;
			}
			$filenames[] = basename( $path );
		}

		rsort( $filenames, SORT_STRING );
		return $filenames;
	}

	/**
	 * Remove a bounded batch of wholly expired, validated daily segments.
	 *
	 * @param string $cutoff UTC boundary day to retain.
	 * @param string $cursor Last processed filename, or empty for a new sweep.
	 * @return array Cleanup counts, cursor, continuation flag and failure codes.
	 */
	public static function purge_before_day( $cutoff, $cursor = '' ) {
		$result = array(
			'files_deleted' => 0,
			'bytes_removed' => 0,
			'files_skipped' => 0,
			'errors'        => array(),
			'busy'          => false,
			'more'          => false,
			'cursor'        => $cursor,
		);
		$date   = is_string( $cutoff ) && preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $cutoff ) ? DateTimeImmutable::createFromFormat( '!Y-m-d', $cutoff, new DateTimeZone( 'UTC' ) ) : false;
		if ( ! $date || $date->format( 'Y-m-d' ) !== $cutoff || $cutoff > gmdate( 'Y-m-d' ) || ! is_string( $cursor ) || ( '' !== $cursor && ! preg_match( '/^activity-\d{4}-\d{2}-\d{2}-\d{6}\.php$/D', $cursor ) ) ) {
			$result['errors'][] = 'invalid_boundary';
			return $result;
		}
		$directory = self::directory();
		if ( false === $directory ) {
			$result['errors'][] = 'storage_unavailable';
			return $result;
		}
		$lock_path = $directory . '/writer.php';
		// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors -- Locked stream checks serialize deletion; disappearing files and unavailable locks are explicitly handled.
		$lock = is_link( $lock_path ) ? false : @fopen( $lock_path, 'c+b' );
		if ( false === $lock ) {
			$result['errors'][] = 'lock_unavailable';
			return $result;
		}
		try {
			if ( ! flock( $lock, LOCK_EX | LOCK_NB ) ) {
				$result['busy'] = true;
				return $result;
			}
			global $wp_filesystem;
			if ( ! self::ensure_header( $lock ) || ! $wp_filesystem->chmod( $lock_path, 0600 ) ) {
				$result['errors'][] = 'lock_invalid';
				return $result;
			}
			$files = self::available_files();
			sort( $files, SORT_STRING );
			$processed = 0;
			$bytes     = 0;
			$deadline  = microtime( true ) + 5;
			foreach ( $files as $filename ) {
				if ( $filename <= $cursor || substr( $filename, 9, 10 ) >= $cutoff ) {
					continue;
				}
				if ( $processed >= 100 || $bytes >= 104857600 || microtime( true ) >= $deadline ) {
					$result['more'] = true;
					break;
				}
				++$processed;
				$result['cursor'] = $filename;
				$path             = $directory . '/' . $filename;
				if ( is_link( $path ) || realpath( $path ) !== $path ) {
					++$result['files_skipped'];
					continue;
				}
				$file = @fopen( $path, 'rb' );
				if ( false === $file ) {
					++$result['files_skipped'];
					continue;
				}
				try {
					$opened = fstat( $file );
					$valid  = $opened && 0100000 === ( $opened['mode'] & 0170000 ) && $opened['size'] <= self::MAX_FILE_SIZE
						&& self::expired_segment( $file, $date->getTimestamp(), $deadline );
					$bytes += $opened ? min( $opened['size'], self::MAX_FILE_SIZE ) : 0;
					clearstatcache( true, $path );
					$current = @lstat( $path );
					if ( ! $valid || ! $current || $opened['dev'] !== $current['dev'] || $opened['ino'] !== $current['ino'] || $opened['size'] !== $current['size'] || 0100000 !== ( $current['mode'] & 0170000 ) ) {
						++$result['files_skipped'];
						continue;
					}
					wp_delete_file( $path );
					clearstatcache( true, $path );
					if ( file_exists( $path ) || is_link( $path ) ) {
						$result['errors'][] = 'delete_failed';
					} else {
						++$result['files_deleted'];
						$result['bytes_removed'] += $opened['size'];
					}
				} finally {
					fclose( $file );
				}
			}
		} finally {
			flock( $lock, LOCK_UN );
			fclose( $lock );
		}
		// phpcs:enable WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors
		$result['errors'] = array_values( array_unique( $result['errors'] ) );
		return $result;
	}

	/**
	 * Verify every record before deleting a whole segment; uncertainty preserves it.
	 *
	 * @param resource $file     Read-only segment stream.
	 * @param int      $cutoff   Exclusive UTC cutoff timestamp.
	 * @param float    $deadline Batch deadline.
	 * @return bool Whether the entire segment is safe to remove.
	 */
	private static function expired_segment( $file, $cutoff, $deadline ) {
		// phpcs:disable WordPress.WP.AlternativeFunctions -- Bounded forward reads validate a locked segment without loading its full contents.
		if ( self::HEADER !== fread( $file, strlen( self::HEADER ) ) ) {
			return false;
		}
		while ( ! feof( $file ) ) {
			if ( microtime( true ) >= $deadline ) {
				return false;
			}
			$line = fgets( $file, self::MAX_RECORD_SIZE + 2 );
			if ( false === $line ) {
				return feof( $file );
			}
			if ( strlen( $line ) > self::MAX_RECORD_SIZE || "\n" !== substr( $line, -1 ) ) {
				return false;
			}
			$record = json_decode( $line, true );
			if ( ! is_array( $record ) || ! isset( $record['site_id'], $record['time'] ) || get_current_blog_id() !== $record['site_id'] || ! is_string( $record['time'] ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/D', $record['time'] ) ) {
				return false;
			}
			$time = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i:sP', $record['time'] );
			if ( ! $time || $time->format( 'c' ) !== $record['time'] || $time->getTimestamp() >= $cutoff ) {
				return false;
			}
		}
		// phpcs:enable WordPress.WP.AlternativeFunctions
		return true;
	}

	/**
	 * Iterate every valid record from a selected segment, newest first.
	 *
	 * @param string $filename Filename returned by available_files().
	 * @return iterable Activity records.
	 * @since 2.26.0
	 */
	public static function file_records( $filename ) {
		if ( ! is_string( $filename ) || ! in_array( $filename, self::available_files(), true ) ) {
			return;
		}

		$directory = self::directory();
		if ( false === $directory ) {
			return;
		}
		$path = $directory . '/' . $filename;
		if ( is_link( $path ) || ! is_file( $path ) ) {
			return;
		}

		// phpcs:disable WordPress.WP.AlternativeFunctions -- Reverse stream reads avoid loading the entire segment into PHP memory.
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A concurrent purge can remove a segment after the allowlist check; false is handled below.
		$file = @fopen( $path, 'rb' );
		if ( false === $file ) {
			return;
		}
		try {
			if ( self::HEADER !== fread( $file, strlen( self::HEADER ) ) ) {
				return;
			}
			$position = fstat( $file )['size'];
			$buffer   = '';
			while ( $position > strlen( self::HEADER ) ) {
				$read_size = min( 8192, $position - strlen( self::HEADER ) );
				$position -= $read_size;
				if ( 0 !== fseek( $file, $position ) ) {
					return;
				}
				$chunk = fread( $file, $read_size );
				if ( false === $chunk || strlen( $chunk ) !== $read_size ) {
					return;
				}
				$lines  = explode( "\n", $chunk . $buffer );
				$buffer = array_shift( $lines );
				foreach ( array_reverse( $lines ) as $line ) {
					if ( '' !== $line && strlen( $line ) <= self::MAX_RECORD_SIZE ) {
						$record = json_decode( $line, true );
						if ( is_array( $record ) ) {
							yield $record;
						}
					}
				}
				if ( strlen( $buffer ) > self::MAX_RECORD_SIZE ) {
					$buffer = substr( $buffer, 0, self::MAX_RECORD_SIZE + 1 );
				}
			}
			if ( '' !== $buffer && strlen( $buffer ) <= self::MAX_RECORD_SIZE ) {
				$record = json_decode( $buffer, true );
				if ( is_array( $record ) ) {
					yield $record;
				}
			}
		} finally {
			fclose( $file );
		}
		// phpcs:enable WordPress.WP.AlternativeFunctions
	}

	/**
	 * Return the last storage health state.
	 *
	 * @return string Status.
	 * @since 2.26.0
	 */
	public static function health() {
		return get_option( self::HEALTH_OPTION, 'pending' );
	}
}
