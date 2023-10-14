<?php

namespace WP_CLI_Utils;

use WP_CLI;
use WP_CLI\Utils;

/**
 * Utility for working with remote targets.
 *
 * @see https://make.wordpress.org/cli/handbook/guides/running-commands-remotely/
 */
#[AllowDynamicProperties]
class Target {

	public $name;

	public $user;

	public $host;

	public $port;

	public $key;

	public $path;

	public $home;

	/**
	 * @param array $config {
	 *     @type string $ssh e.g. user@host
	 *     @type string $name
	 *     @type string $host
	 *     @type int    $port
	 *     @type string $key
	 *     @type string $path Path to WordPress on remote target.
	 * }
	 */
	public function __construct( array $config ) {
		if ( ! empty( $config['ssh'] ) ) {
			$config += Utils\parse_ssh_url( $config['ssh'] );
		}

		$props = array_intersect_key( $config, get_object_vars( $this ) );

		foreach ( $props as $prop => $value ) {
			$this->$prop = $value;
		}
	}

	public function __toString() : string {
		return $this->user ? "$this->user@$this->host" : $this->host;
	}

	public function is_alias() : bool {
		return static::name_is_alias( $this->name );
	}

	public function get_home() : string {
		return Utils\trailingslashit( $this->home ??= shell_exec( $this->get_ssh_cmd( 'printf $HOME' ) ) );
	}

	public function get_path() : string {
		return Utils\trailingslashit( $this->path ??= $this->wp_eval( 'echo realpath( ABSPATH )' ) );
	}

	public function get_url() : string {
		return rtrim( $this->url ??= $this->wp_eval( 'echo home_url()' ), '/\\' );
	}

	public function get_ssh_args() : string {
		$args = '';

		if ( $this->key ) {
			$args .= Utils\esc_cmd( ' -i %s', $this->key );
		}

		if ( $this->port ) {
			$args .= sprintf( ' -p %d', $this->port );
		}

		return $args;
	}

	public function get_ssh_cmd( string $cmd = null ) : string {
		$ssh = "ssh $this" . $this->get_ssh_args();

		if ( $cmd ) {
			$ssh.= Utils\esc_cmd( ' %s', $cmd );
		}

		return $ssh;
	}

	public function get_rsync_cmd( string $cmd = null ) : string {
		$rsync = 'rsync';

		$ssh_args = $this->get_ssh_args();

		if ( $ssh_args ) {
			$rsync .= Utils\esc_cmd( ' -e %s', "ssh$ssh_args" );
		}

		if ( $cmd ) {
			$rsync .= " $cmd";
		}

		return $rsync;
	}

	public function get_wp_cmd( string $cmd ) : string {
		if ( $this->is_alias() ) {
			return "$this->name $cmd";
		}

		$ssh = "$this";

		if ( $this->port ) {
			$ssh .= sprintf( ':%d', $this->port );
		}

		if ( $this->path ) {
			$ssh .= $this->path;
		}

		$cmd .= Utils\esc_cmd( ' --ssh=%s', $ssh );

		return $cmd;
	}

	public function run_wp_cmd( string $cmd, array $options = [] ) {
		return WP_CLI::runcommand( $this->get_wp_cmd( $cmd ), $options );
	}

	public function wp_eval( string $code ) {
		$code = trim( $code );

		if ( substr( $code, -1 ) !== ';' ) {
			$code .= ';';
		}

		$code = str_replace( '"', '\\"', $code );

		return $this->run_wp_cmd( sprintf( 'eval "%s"', $code ), [
			'return' => true,
		]);
	}

	public static function name_is_alias( string $name ) : bool {
		return substr( $name, 0 , 1 ) === '@';
	}

	/**
	 * Get single target for given alias or SSH target.
	 *
	 * @param string|array $name Defaults to '@all' i.e. all configured aliases.
	 */
	public static function get( $name = null ) : self {
		$targets = static::get_configs( $name );

		if ( count( $targets ) !== 1 ) {
			WP_CLI::error( 'Invalid target.' );
		}

		return new self( array_shift( $targets ) );
	}

	/**
	 * Get all targets for given alias(es) or SSH target(s).
	 *
	 * @param string|array $name Defaults to '@all' i.e. all configured aliases.
	 *
	 * @return Target[]
	 */
	public static function get_all( $name = null ) : array {
		return array_map( fn ( $config ) => new self( $config ), static::get_configs( $name ) );
	}

	/**
	 * Resolve aliases/SSH target(s) to configuration values.
	 *
	 * @see https://make.wordpress.org/cli/handbook/guides/running-commands-remotely/
	 *
	 * @param string|array $name Defaults to '@all' i.e. all configured aliases.
	 *
	 * @return array[] An array of config arrays indexed by name.
	 */
	public static function get_configs( $name = null ) : array {
		$name = $name ?: '@all';

		if ( is_array( $name ) ) {
			$configs = [];

			foreach ( $name as $_name ) {
				$configs += static::get_configs( $_name );
			}

			return $configs;
		}

		if ( ! static::name_is_alias( $name ) ) {
			return [
				$name => [
					'name' => $name,
					'ssh' => $name,
				],
			];
		}

		$aliases = WP_CLI::get_runner()->aliases;

		if ( $name === '@all' ) {
			$aliases = array_diff( array_keys( $aliases ), [ $name ] );

		} elseif ( isset( $aliases[ $name ] ) && is_array( $aliases[ $name ] ) ) {
			$aliases = $aliases[ $name ];

			// See CLI_Alias_Command::is_group()
			$is_group = is_numeric( key( $aliases ) ) && static::name_is_alias( current( $aliases ) );

			if ( $aliases && ! $is_group ) {
				return [
					$name => [ 'name' => $name ] + $aliases,
				];
			}

		} else {
			$aliases = [];
		}

		$configs = [];

		foreach ( $aliases as $alias ) {
			$configs += static::get_configs( $alias );
		}

		return $configs;
	}

}
