<?php

use EE\Model\Site;
use function EE\Site\Utils\auto_site_name;
use function EE\Site\Utils\get_site_info;
use EE\Utils as EE_Utils;

/**
 * Perform operations on log files.
 *
 * ## EXAMPLES
 *
 *     # Display site logs.
 *     $ ee log show example.com
 *     watching logfile example.com/logs/nginx/error.log
 *     watching logfile example.com/logs/nginx/access.log
 *     watching logfile example.com/logs/php/error.log
 *     watching logfile example.com/logs/php/access.log
 *
 */
class Log_Command extends EE_Command {


	/**
	 * @var object $site_data Object containing site related information.
	 */
	private $site_data;

	/**
	 * @var array $logs Array of all logs to be monitored.
	 */
	private $logs = [];

	/**
	 * Monitor site or global logs.
	 *
	 * ## OPTIONS
	 *
	 * [<site-name>]
	 * : Name of website.
	 *
	 * [--n=<line-count>]
	 * : Start from last number of given lines.
	 * ---
	 * default: 10
	 * ---
	 *
	 * [--global]
	 * : Displays all logs including all sites, all services and cli.
	 *
	 * [--cli]
	 * : Displays EasyEngine's own logs.
	 *
	 * [--all]
	 * : Displays all local logs, including service logs.
	 *
	 * [--nginx]
	 * : Displays nginx logs for a site.
	 *
	 * [--php]
	 * : Displays php logs for a site.
	 *
	 * [--wp]
	 * : Displays wp debug log for a site.
	 *
	 * [--access]
	 * : Displays nginx & php access logs for a site.
	 *
	 * [--error]
	 * : Displays nginx & php error logs for a site.
	 *
	 * ## EXAMPLES
	 *
	 *     # Show all logs.
	 *     $ ee log show example.com --all
	 *     watching logfile ~/easyengine/sites/example.com/logs/nginx/access.log
	 *     watching logfile ~/easyengine/sites/example.com/logs/nginx/error.log
	 *     watching logfile ~/easyengine/sites/example.com/logs/debug.log
	 *
	 *     # Show debug log for site.
	 *     $ ee log show example.com --wp
	 *     watching logfile ~/easyengine/sites/example.com/logs/debug.log
	 *
	 */
	public function show( $args, $assoc_args ) {

		$is_global = EE_Utils\get_flag_value( $assoc_args, 'global', false );
		$is_cli    = EE_Utils\get_flag_value( $assoc_args, 'cli', false );

		// Possible logs for a site.
		$allowed_types = [ 'nginx', 'php', 'wp', 'access', 'error' ];

		$lines = EE_Utils\get_flag_value( $assoc_args, 'n', 10 );
		// tail's own `+N` (start at line N) and `-N` stay allowed.
		if ( ! ( is_int( $lines ) || is_string( $lines ) ) || ! preg_match( '/^[+-]?[0-9]{1,9}\z/', (string) $lines ) ) {
			EE::error( sprintf( "Invalid --n value '%s'. It must be a line count from 0 to 999999999, e.g. 10 (or +10 to start at line 10).", is_int( $lines ) || is_string( $lines ) ? $lines : '' ) );
		}

		$passed_types = array_keys( $assoc_args );

		if ( ( $key = array_search( 'n', $passed_types ) ) !== false ) {
			unset( $passed_types[ $key ] ); // Remove line count.
		}

		if ( empty( $passed_types ) ) {
			$final_types = [ 'nginx', 'php', 'wp' ];
		} elseif ( in_array( 'all', $passed_types ) ) {
			$final_types = $allowed_types;
		} else {
			$final_types = array_intersect( $allowed_types, $passed_types );
		}

		if ( $is_global ) {

			$sites = Site::all();

			// EasyEngine logs
			$this->get_files( EE_ROOT_DIR . DIRECTORY_SEPARATOR . $this->create_directory_path( [ 'logs' ] ), 'cli' );

			foreach ( $sites as $site ) {

				$logs_path = $site->site_fs_path . DIRECTORY_SEPARATOR . 'logs';

				if ( 'wp' === $site->site_type ) {
					$wp_logs_path = $site->site_fs_path . DIRECTORY_SEPARATOR . $this->create_directory_path( [ 'app', 'htdocs', 'wp-content' ] );
				}

				foreach ( $allowed_types as $type ) {
					if ( 'wp' === $type ) {
						$log_type_path = $wp_logs_path;
					} else {
						$log_type_path = $logs_path . DIRECTORY_SEPARATOR . $type;
					}

					$this->get_files( $log_type_path, $type );
				}
			}
		} elseif ( $is_cli ) {
			$this->get_files( EE_ROOT_DIR . DIRECTORY_SEPARATOR . $this->create_directory_path( [ 'logs' ] ), 'cli' );
		} else {

			$this->populate_info( $args, __FUNCTION__ );

			$site_type    = $this->site_data->site_type;
			$logs_path    = $this->site_data->site_fs_path . DIRECTORY_SEPARATOR . 'logs';
			$nginx_path   = $logs_path . DIRECTORY_SEPARATOR . 'nginx';
			$php_path     = $logs_path . DIRECTORY_SEPARATOR . 'php';
			$wp_logs_path = $this->site_data->site_fs_path . DIRECTORY_SEPARATOR . $this->create_directory_path( [ 'app', 'htdocs', 'wp-content' ] );

			if ( 'html' === $site_type ) {
				unset( $final_types['php'], $final_types['wp'] );
			} elseif ( 'php' === $site_type ) {
				unset( $final_types['wp'] );
			}

			foreach ( $final_types as $type ) {
				if ( 'access' === $type || 'error' === $type ) {
					if ( 'html' !== $site_type ) {
						$this->get_files( $php_path . DIRECTORY_SEPARATOR, $type );
					}
					$log_type_path = $nginx_path . DIRECTORY_SEPARATOR;
				} elseif ( 'wp' === $type ) {
					$log_type_path = $wp_logs_path;
				} else {
					$log_type_path = $logs_path . DIRECTORY_SEPARATOR . $type;
				}

				$this->get_files( $log_type_path, $type );
			}
		}

		$logs = array_values( array_filter( array_unique( $this->logs ) ) );

		foreach ( $logs as $log ) {
			// Remove 'WEBROOT' path and display log path from site name.
			$log_path = str_replace( WEBROOT, '', $log );

			EE::log( "watching logfile {$log_path}" );
		}

		if ( empty( $logs ) ) {
			EE::error( 'No logs found!' );
		}

		$this->follow( $lines, $logs );
	}

	/**
	 * Follows the given log files with tail, without a shell: file names come from site directories
	 * that the site's own containers can write to.
	 *
	 * @param string|int $lines Validated line count.
	 * @param array      $logs  Log file paths.
	 *
	 * @return void
	 */
	private function follow( $lines, $logs ) {

		EE_Utils\check_proc_available( 'log show' );

		$command = array_merge( [ 'tail', '-n', (string) $lines, '-f', '--' ], $logs );
		EE::debug( 'COMMAND: ' . implode( ' ', array_map( 'escapeshellarg', $command ) ) );

		// proc_open() only takes an argv array (no shell) from PHP 7.4 on.
		if ( PHP_VERSION_ID < 70400 ) {
			$command = implode( ' ', array_map( 'escapeshellarg', $command ) );
		}

		// No descriptors: tail inherits ee's stdio (passing STDOUT clobbers earlier output when it is a file).
		$process = proc_open( $command, [], $pipes );
		if ( ! is_resource( $process ) ) {
			EE::error( 'Unable to run tail.' );
		}

		$status = proc_close( $process );
		if ( 0 !== $status ) {
			EE::error( "tail exited with status {$status}." );
		}
	}

	/**
	 * Function to populate basic info from args
	 *
	 * @param array  $args    args passed from function.
	 * @param string $command command name that is calling the function.
	 *
	 * @return void.
	 */
	private function populate_info( $args, $command ) {
		$args            = auto_site_name( $args, 'log', $command );
		$this->site_data = get_site_info( $args, true, true, false );
	}

	/**
	 * Get All files in given path recursively.
	 *
	 * @param string $path Directory Path.
	 *
	 * @return array
	 */
	private function getDirContents( $path ) {

		$recursive_iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path ) );

		$files = array();

		foreach ( $recursive_iterator as $file ) {
			if ( ! $file->isDir() ) {
				$files[] = $file->getPathname();
			}
		}

		return $files;
	}

	/**
	 * Add all valid logs to an internal array to be processed later.
	 *
	 * @param string $log_path Path to log file.
	 * @param string $type     Type of log file to be monitored.
	 *
	 * @return void
	 */
	private function get_files( $log_path, $type = '' ) {

		if ( is_dir( $log_path ) ) {

			if ( 'wp' === $type ) {
				$files = [ $log_path . 'debug.log' ];
			} elseif ( 'cli' === $type ) {
				$files = [ $log_path . 'ee.log' ];
			} else {
				$files = $this->getDirContents( $log_path );
			}

			foreach ( $files as $file ) {

				if ( ! file_exists( $file ) ) {
					EE::warning( "$type log doesn't exist!" );
					continue;
				}

				$file_info = pathinfo( $file );

				if ( 'log' === $file_info['extension'] ) {
					if ( 'access' === $type ) {
						$this->logs[] = ( 'access' === $file_info['filename'] ) ? $file : '';
					} elseif ( 'error' === $type ) {
						$this->logs[] = ( 'error' === $file_info['filename'] ) ? $file : '';
					} else {
						$this->logs[] = $file;
					}
				}
			}
		}

	}

	/**
	 * Helper function to generate path from give directories.
	 *
	 * @param array $directories Array of directories in order to create path.
	 *
	 * @return string
	 */
	private function create_directory_path( $directories ) {

		$final_string = '';

		foreach ( $directories as $directory ) {
			$final_string .= $directory . DIRECTORY_SEPARATOR;
		}

		return $final_string;
	}

}
