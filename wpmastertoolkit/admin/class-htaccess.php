<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Handle the htaccess file
 *
 * @link       https://webdeclic.com
 * @since      1.0.0
 *
 * @package           WPMastertoolkit
 * @subpackage WP-Mastertoolkit/admin
 */

class WPMastertoolkit_Htaccess {

    const FILE_PATH     = ABSPATH . '.htaccess';
    const START_MARKER  = '# BEGIN WPMastertoolkit: ';
    const END_MARKER    = '# END WPMastertoolkit: ';

    /**
     * __construct
     *
     * @return void
     */
    public function __construct() {

    }

    /**
     * Add contents to the file
     */
    public static function add( $new_contents, $contents_id ) {

        $contents = self::generate_the_new_contents( $contents_id );
        if ( false === $contents ) {
            return false;
        }

        $start_marker = self::START_MARKER . $contents_id;
        $end_marker   = self::END_MARKER . $contents_id;
        $contents     = $start_marker . "\n" . $new_contents . "\n" . $end_marker . "\n\n" . $contents;

        return self::write_htaccess_content( $contents, 'add:' . $contents_id );
    }

    /**
     * Remove contents from the file
     */
    public static function remove( $id ) {

        $contents = self::generate_the_new_contents( $id );
        if ( false === $contents ) {
            return false;
        }

        return self::write_htaccess_content( $contents, 'remove:' . $id );
    }

    // -------------------------------------------------------------------------
    // Backup system
    // -------------------------------------------------------------------------

    /**
     * get_backup_folder_path
     * Get the path to .htaccess backup folder
     *
     * @return string|false
     */
    public static function get_backup_folder_path() {
        add_filter( 'wpmastertoolkit/folders', function( $folders ) {
            $folders['wpmastertoolkit']['htaccess-backup'] = array();
            return $folders;
        }, 10, 1 );

        wpmastertoolkit_folders();

        $path = WP_CONTENT_DIR . '/wpmastertoolkit/htaccess-backup';

        //phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
        if ( ! file_exists( $path ) || ! is_writable( $path ) ) {
            WPMastertoolkit_Logs::add_error( 'WPMastertoolkit: htaccess-backup folder does not exist or is not writable: ' . $path );
            return false;
        }

        return $path;
    }

    /**
     * backup_htaccess
     * Create a timestamped backup of .htaccess before modification.
     *
     * @return bool True on success, false on failure
     */
    public static function backup_htaccess() {
        $file_path = self::FILE_PATH;

        if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
            WPMastertoolkit_Logs::add_error( 'WPMastertoolkit: Cannot backup .htaccess - file does not exist or is not readable: ' . $file_path );
            return false;
        }

        $backup_folder = self::get_backup_folder_path();

        if ( $backup_folder === false ) {
            WPMastertoolkit_Logs::add_error( 'WPMastertoolkit: Cannot backup .htaccess - backup folder is not available' );
            return false;
        }

        $timestamp   = gmdate( 'Y-m-d_H-i-s' );
        $backup_path = $backup_folder . '/htaccess_' . $timestamp . '.bak';
        $result      = copy( $file_path, $backup_path );

        if ( $result ) {
            self::cleanup_old_backups( $backup_folder );
        }

        return $result;
    }

    /**
     * restore_htaccess_backup
     * Restore .htaccess from the most recent timestamped backup.
     *
     * @return bool True on success, false on failure
     */
    public static function restore_htaccess_backup() {
        $file_path = self::FILE_PATH;

        //phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
        if ( ! is_writable( $file_path ) ) {
            WPMastertoolkit_Logs::add_error( 'WPMastertoolkit: Cannot restore .htaccess - file is not writable: ' . $file_path );
            return false;
        }

        $backup_folder = self::get_backup_folder_path();

        if ( $backup_folder === false ) {
            WPMastertoolkit_Logs::add_error( 'WPMastertoolkit: Cannot restore .htaccess - backup folder is not available' );
            return false;
        }

        $backups = glob( $backup_folder . '/htaccess_*.bak' );

        if ( empty( $backups ) ) {
            WPMastertoolkit_Logs::add_error( 'WPMastertoolkit: Cannot restore .htaccess - no backup file found' );
            return false;
        }

        rsort( $backups ); // descending → most recent first
        $backup_path = $backups[0];

        if ( ! is_readable( $backup_path ) ) {
            WPMastertoolkit_Logs::add_error( 'WPMastertoolkit: Cannot restore .htaccess - backup file is not readable: ' . $backup_path );
            return false;
        }

        $restored = copy( $backup_path, $file_path );
        if ( $restored ) {
            WPMastertoolkit_Logs::add_notice( 'WPMastertoolkit: .htaccess successfully restored from backup: ' . $backup_path );
        }
        return $restored;
    }

    /**
     * cleanup_old_backups
     * Keep only the $keep most recent timestamped backups, delete older ones.
     *
     * @param string $backup_folder Path to the backup folder
     * @param int    $keep          Number of recent backups to keep (default 5)
     * @return void
     */
    public static function cleanup_old_backups( $backup_folder, $keep = 5 ) {
        $backups = glob( $backup_folder . '/htaccess_*.bak' );

        if ( ! $backups || count( $backups ) <= $keep ) {
            return;
        }

        sort( $backups ); // ascending → oldest first
        $to_delete = array_slice( $backups, 0, count( $backups ) - $keep );

        foreach ( $to_delete as $file ) {
            //phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
            unlink( $file );
        }
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    /**
     * validate_htaccess
     * Validate that .htaccess is not corrupt (exists, readable, no PHP code).
     *
     * @return bool True if valid, false otherwise
     */
    public static function validate_htaccess() {
        $file_path = self::FILE_PATH;

        if ( ! file_exists( $file_path ) ) {
            WPMastertoolkit_Logs::add_error( 'WPMastertoolkit: .htaccess validation failed - file does not exist: ' . $file_path );
            return false;
        }

        if ( ! is_readable( $file_path ) ) {
            WPMastertoolkit_Logs::add_error( 'WPMastertoolkit: .htaccess validation failed - file is not readable: ' . $file_path );
            return false;
        }

        $content = file_get_contents( $file_path );

        // A PHP opening tag in an .htaccess is a clear sign of corruption/wrong write.
        if ( strpos( $content, '<?php' ) !== false || strpos( $content, '<?=' ) !== false ) {
            WPMastertoolkit_Logs::add_error( 'WPMastertoolkit: .htaccess validation failed - file contains PHP code (corruption detected)' );
            return false;
        }

        return true;
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Generate the new contents (strips the section identified by $contents_id)
     */
    private static function generate_the_new_contents( $contents_id ) {

        $contents = self::get_file_contents();

        if ( false === $contents ) {
            return false;
        }

        $start_marker = self::START_MARKER . $contents_id;
        $end_marker   = self::END_MARKER . $contents_id;

        // Remove previous rules if exist.
        $contents = preg_replace( '/\s*?' . preg_quote( $start_marker, '/' ) . '.*' . preg_quote( $end_marker, '/' ) . '\s*?/isU', "\n\n", $contents );
        $contents = trim( $contents );

        return $contents;
    }

    /**
     * Get the file contents, creating the file if it does not exist.
     */
    private static function get_file_contents() {
        global $wp_filesystem;

        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();

        $file_path = self::FILE_PATH;
        $dir_path  = dirname( $file_path );

        if ( ! $wp_filesystem->is_dir( $dir_path ) ) {
            $wp_filesystem->mkdir( $dir_path, FS_CHMOD_DIR );
        }

        if ( ! $wp_filesystem->exists( $file_path ) ) {
            $wp_filesystem->put_contents( $file_path, '', FS_CHMOD_FILE );
        }

        if ( ! $wp_filesystem->is_writable( $file_path ) ) {
            WPMastertoolkit_Logs::add_error( 'WPMastertoolkit: .htaccess is not writable: ' . $file_path );
            return false;
        }

        $contents = $wp_filesystem->get_contents( $file_path );

        if ( $contents === false ) {
            WPMastertoolkit_Logs::add_error( 'WPMastertoolkit: Failed to read .htaccess: ' . $file_path );
            return false;
        }

        return $contents;
    }

    /**
     * write_htaccess_content
     * Centralised, safe write method: takes a backup first (aborts if it fails),
     * writes atomically via a temp file + rename, then validates the result.
     * On validation failure the backup is automatically restored.
     *
     * @param string $contents Content to write to .htaccess
     * @param string $context  Label used in log messages for easier diagnosis
     * @return bool            True on success, false on failure
     */
    private static function write_htaccess_content( $contents, $context = '' ) {
        $file_path   = self::FILE_PATH;
        $log_context = $context ? " [{$context}]" : '';

        // Backup is mandatory before touching the live file.
        // If the file doesn't exist yet there's nothing to back up – that's fine.
        if ( file_exists( $file_path ) && self::backup_htaccess() === false ) {
            WPMastertoolkit_Logs::add_error( 'WPMastertoolkit: .htaccess write cancelled - backup failed' . $log_context );
            return false;
        }

        // Atomic write: write to a temp file, then rename into place.
        $tmp_path = $file_path . '.tmp.' . uniqid( '', true );
        //phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        $written  = file_put_contents( $tmp_path, $contents, LOCK_EX );

        if ( $written === false ) {
            WPMastertoolkit_Logs::add_error( 'WPMastertoolkit: Failed to write temporary file for .htaccess' . $log_context );
            if ( file_exists( $tmp_path ) ) {
                //phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
                unlink( $tmp_path );
            }
            return false;
        }

        if ( ! rename( $tmp_path, $file_path ) ) {
            WPMastertoolkit_Logs::add_error( 'WPMastertoolkit: Failed to rename temp file to .htaccess' . $log_context );
            if ( file_exists( $tmp_path ) ) {
                //phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
                unlink( $tmp_path );
            }
            return false;
        }

        // Apply correct permissions.
        $chmod_file = fileperms( ABSPATH . 'index.php' ) & 0777 | 0644;
        chmod( $file_path, $chmod_file );

        // Post-write validation; restore from backup if the file looks corrupt.
        if ( ! self::validate_htaccess() ) {
            WPMastertoolkit_Logs::add_error( 'WPMastertoolkit: .htaccess validation failed after write' . $log_context . ' - restoring backup' );
            self::restore_htaccess_backup();
            return false;
        }

        self::maybe_sync_wordpress_rewrite_rules();

        return true;
    }

    /**
     * Ensure WordPress rewrite rules are present after .htaccess changes.
     */
    private static function maybe_sync_wordpress_rewrite_rules() {
        if ( ! is_admin() ) {
            return;
        }

        if ( ! function_exists( 'get_option' ) || ! function_exists( 'save_mod_rewrite_rules' ) ) {
            require_once ABSPATH . 'wp-admin/includes/misc.php';
        }

        if ( ! function_exists( 'get_option' ) || ! function_exists( 'save_mod_rewrite_rules' ) ) {
            return;
        }

        if ( ! function_exists( 'got_mod_rewrite' ) || ! got_mod_rewrite() ) {
            return;
        }

        $permalink_structure = (string) get_option( 'permalink_structure' );
        if ( '' === $permalink_structure ) {
            return;
        }

        save_mod_rewrite_rules();
    }
}
