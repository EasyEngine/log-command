<?php

use EE\Utils;

/**
 * To monitor Nginx, PHP, MySQL, WordPress logs in use.
 *
 * ## EXAMPLES
 *
 *     # Show all the available logs for all sites.
 *     $ ee log show
 *
 * @package ee-cli
 */
class Log_Command extends EE_Command {

	private $db;
	private $logger;
	private $logs;

	public function __construct() {
		$this->db     = EE::db();
		$this->logger = EE::get_file_logger()->withName( 'log_command' );
	}

	/**
	 * Shows the Nginx, PHP, MySQL, WordPress logs.
	 *
	 * ## OPTIONS
	 *
	 * [<site-name>]
	 * : Name of the website for which you want to monitor the logs.
	 *
	 * [--nginx]
	 * : To view the nginx logs.
	 *
	 * [--all]
	 * : To view all the logs.
	 */
	public function show( $args, $assoc_args ) {
		$this->get_log_files( $args, $assoc_args, 'show' );
		$this->tail();
		\EE\Utils\delem_log( 'log show end' );
	}

	/**
	 * Truncate the log files.
	 *
	 * ## OPTIONS
	 *
	 * [<site-name>]
	 * : Name of the website for which you want to reset the logs.
	 *
	 * [--nginx]
	 * : To reset the nginx logs.
	 *
	 * [--all]
	 * : To view all the logs.
	 */
	public function reset( $args, $assoc_args ) {
		$this->get_log_files( $args, $assoc_args, 'reset' );

		EE::log( 'Resetting logs...' );
		foreach ( $this->logs as $logs ) {
			EE::log( '[' . $logs['container_name'] . '] Truncating log ' . $logs['path'] );
			$truncate_command = 'docker exec ' . $logs['container_name'] . ' rm ' . $logs['path'] . ' && docker restart ' . $logs['container_name'];
			\EE\Utils\default_launch( $truncate_command );
		}
		/* Logic to truncate local files.
		foreach ( $this->logs as $log ) {
			EE::log( "Resetting $log" );
			$log_file = @fopen( $log, "r+" );
			if ( false !== $log_file ) {
				ftruncate( $log_file, 0 );
				fclose( $log_file );
			}
		}*/
		\EE\Utils\delem_log( 'log reset end' );
	}

	/**
	 * Function to parse the parameters and call the functions to fetch relevant log files.
	 *
	 * @param array  $args       Command line arguments passed, here would only be the name of the site if passed.
	 * @param array  $assoc_args Array of parameter flags passed.
	 * @param String $command    The type of command requesting the file.
	 */
	private function get_log_files( $args, $assoc_args, $command ) {
		\EE\Utils\delem_log( "log $command start" );
		$this->logger->debug( 'args:', $args );
		$this->logger->debug( 'assoc_args:', empty( $assoc_args ) ? array( 'NULL' ) : $assoc_args );
		if ( ! empty( $args[0] ) ) {
			$site_name = $args[0];
			if ( $this->db::site_in_db( $site_name ) ) {
				$sites = $this->db::select( array( 'sitename', 'site_path' ), array( 'sitename' => $site_name ) );
			} else {
				EE::error( "Site $site_name does not exist." );
			}
		} else {
			$sites = $this->db::select( array( 'sitename', 'site_path' ) );
		}
		$local = ( 'reset' === $command ) ? false : true;
		if ( empty( $assoc_args ) ) {
			$this->get_file_from_type( $sites, 'nginx', $local );
			$this->get_file_from_type( $sites, 'php', $local );
			$this->get_file_from_type( $sites, 'mysql', $local );
		} else {
			foreach ( $assoc_args as $flag => $val ) {
				$this->get_file_from_type( $sites, $flag, $local );
			}
		}
	}

	/**
	 * Get the log file according to paramteres given websites.
	 *
	 * @param array  $sites Array of websites.
	 * @param String $type  Type of parameter for the log file.
	 * @param bool   $local Local copy of the log file. If false, it will give the path of the file in container.
	 */
	private function get_file_from_type( $sites, $type, $local = true ) {
		switch ( $type ) {
			case 'nginx':
				foreach ( $sites as $site ) {
					if ( $local ) {
						$this->logs[] = $site['site_path'] . '/logs/nginx/access.log';
						$this->logs[] = $site['site_path'] . '/logs/nginx/error.log';
					} else {
						$container_name = implode( '', explode( '.', $site['sitename'] ) ) . '_nginx_1';
						$this->logs[]   = array(
							'container_name' => $container_name,
							'path'           => '/var/log/nginx/access.log',
						);
						$this->logs[]   = array(
							'container_name' => $container_name,
							'path'           => '/var/log/nginx/error.log',
						);
					}
				}
				break;
			case 'php':
				break;
			case 'mysql':
				break;
		}
	}

	private function tail() {
		if ( ! empty( $this->logs ) ) {
			$tail_command = 'tail -f ';
			foreach ( $this->logs as $log_files ) {
				$tail_command .= $log_files . ' ';
			}
			EE::log( "Use the following command to tail your logs:\n\n$tail_command" );
		} else {
			EE::log( 'No logs to show.' );
		}

		/* Can tail one file.
		$filename = $this->logs[0];
		$file = @fopen( $filename, 'r' );
		$pos = 0;
		while ( true ) {
			fseek( $file, $pos );
			while ( $line = fgets( $file ) ) {
				echo( $line );
			}
			$pos = ftell( $file );
			sleep( 2 );
		}
		fclose( $file );
		*/
	}
}
