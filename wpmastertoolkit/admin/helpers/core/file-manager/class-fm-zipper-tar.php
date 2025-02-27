<?php

/**
 * Class to work with Tar files (using PharData)
 * 
 * @since   1.9.0
 */
class FM_Zipper_Tar {
	private $tar;

	public function __construct() {
		$this->tar = null;
	}

	/**
	 * Create archive with name $filename and files $files (RELATIVE PATHS!)
	 * 
	 * @since   1.9.0
	 */
	public function create( $filename, $files ) {
		$this->tar = new PharData( $filename );
		if ( is_array( $files ) ) {
			foreach ( $files as $f ) {
				$f = $this->fm_clean_path( $f );
				if ( ! $this->addFileOrDir( $f ) ) {
					return false;
				}
			}
			return true;
		} else {
			if ( $this->addFileOrDir( $files ) ) {
				return true;
			}
			return false;
		}
	}

	/**
	 * Extract archive $filename to folder $path (RELATIVE OR ABSOLUTE PATHS)
	 * 
	 * @since   1.9.0
	 */
	public function unzip( $filename, $path ) {
		$res = $this->tar->open( $filename );
		if ( $res !== true ) {
			return false;
		}
		if ( $this->tar->extractTo( $path ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Add file/folder to archive
	 */
	private function addFileOrDir($filename) {
		if ( is_file( $filename ) ) {
			try {
				$this->tar->addFile( $filename );
				return true;
			} catch (Exception $e) {
				return false;
			}
		} elseif ( is_dir( $filename ) ) {
			return $this->addDir( $filename );
		}
		return false;
	}

	/**
	 * Add folder recursively
	 */
	private function addDir( $path ) {
		$objects = scandir( $path );
		if ( is_array( $objects ) ) {
			foreach ( $objects as $file ) {
				if ( $file != '.' && $file != '..' ) {
					if ( is_dir( $path . '/' . $file ) ) {
						if ( ! $this->addDir( $path . '/' . $file ) ) {
							return false;
						}
					} elseif ( is_file( $path . '/' . $file ) ) {
						try {
							$this->tar->addFile( $path . '/' . $file );
						} catch (Exception $e) {
							return false;
						}
					}
				}
			}
			return true;
		}
		return false;
	}

	/**
     * Clean path
	 * 
	 * @since   1.9.0
     */
    private function fm_clean_path( $path, $trim = true ) {
        $path = $trim ? trim( $path ) : $path;
        $path = trim( $path, '\\/' );
        $path = str_replace( array( '../', '..\\' ), '', $path );
        $path = $this->get_absolute_path( $path );
        
        if ( $path == '..' ) {
            $path = '';
        }

        return str_replace( '\\', '/', $path );
    }

	/**
     * Path traversal prevention and clean the url
	 * 
	 * @since   1.9.0
     */
    private function get_absolute_path( $path ) {
        $path       = str_replace( array( '/', '\\' ), DIRECTORY_SEPARATOR, $path );
        $parts      = array_filter( explode( DIRECTORY_SEPARATOR, $path ), 'strlen' );
        $absolutes  = array();

        foreach ( $parts as $part ) {
            if ( '.' == $part ) continue;
            if ( '..' == $part ) {
                array_pop( $absolutes );
            } else {
                $absolutes[] = $part;
            }
        }

        return implode( DIRECTORY_SEPARATOR, $absolutes );
    }
}
