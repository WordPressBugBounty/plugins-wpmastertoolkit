<?php

/**
 * Class to work with zip files (using ZipArchive)
 * 
 * @since   1.9.0
 */
class WPMastertoolkit_FM_Zipper {
	private $zip;

	public function __construct() {
		$this->zip = new ZipArchive();
	}

	/**
	 * Create archive with name $filename and files $files (RELATIVE PATHS!)
	 * 
	 * @since   1.9.0
	 */
	public function create( $filename, $files ) {
		$res = $this->zip->open( $filename, ZipArchive::CREATE );
		if ( $res !== true ) {
			return false;
		}
		if ( is_array( $files ) ) {
			foreach ( $files as $f ) {
				$f = $this->fm_clean_path( $f );
				if ( ! $this->addFileOrDir( $f ) ) {
					$this->zip->close();
					return false;
				}
			}
			$this->zip->close();
			return true;
		} else {
			if ( $this->addFileOrDir( $files ) ) {
				$this->zip->close();
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
		$res = $this->zip->open( $filename );
		if ( $res !== true ) {
			return false;
		}
		if ( $this->zip->extractTo( $path ) ) {
			$this->zip->close();
			return true;
		}
		return false;
	}

	/**
	 * Add file/folder to archive
	 * 
	 * @since   1.9.0
	 */
	private function addFileOrDir( $filename ) {
		if ( is_file( $filename ) ) {
			return $this->zip->addFile( $filename );
		} elseif ( is_dir( $filename ) ) {
			return $this->addDir( $filename );
		}
		return false;
	}

	/**
	 * Add folder recursively
	 * 
	 * @since   1.9.0
	 */
	private function addDir( $path ) {
		if ( ! $this->zip->addEmptyDir( $path ) ) {
			return false;
		}
		$objects = scandir( $path );
		if ( is_array( $objects ) ) {
			foreach ( $objects as $file ) {
				if ( $file != '.' && $file != '..' ) {
					if ( is_dir( $path . '/' . $file ) ) {
						if ( ! $this->addDir( $path . '/' . $file ) ) {
							return false;
						}
					} elseif ( is_file( $path . '/' . $file ) ) {
						if ( ! $this->zip->addFile( $path . '/' . $file ) ) {
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
