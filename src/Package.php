<?php

namespace WP_CLI_Utils;

use WP_CLI;
use WP_CLI\Utils;

class Package {

	use Traits\Commander;
	use Traits\Config;

	protected string $type;

	protected string $path;

	protected function __construct( string $type, string $path ) {
		$this->type = $type;

		$this->path = $path;
	}

	public function __get( string $prop ) {
		return $this->$prop;
	}

	public function pack() : string {
		$name = basename( $this->path );

		$file = sprintf( '%s-%s.zip', $name, date( 'YmdHis' ) );

		$config = $this->get_config([
			"pack $this->type $name",
			"pack $this->type",
			'pack'
		]);

		$build = $config['build'] ?? [];

		$exclude = $config['exclude'] ?? [];

		$exclude = array_map( fn ( $path ) => "$name/$path", (array) $exclude );

		chdir( $this->path );

		foreach ( (array) $build as $cmd ) {
			$this->run_cmd( $cmd );
		}

		chdir( '..' );

		$this->run_cmd( Utils\esc_cmd(
			'zip %s %s -r -x' . str_repeat( ' %s', count( $ignore ) ),
			$file,
			"$name/",
			...$ignore
		) );

		return $file;
	}

	public static function for_theme( string $theme = '' ) : self {
		$theme = wp_get_theme( $theme );

		if ( $theme->errors() ) {
			WP_CLI::error( $theme->errors() );
		}

		return new self( 'theme', $theme->get_stylesheet_directory() );
	}

	public static function for_plugin( string $plugin ) : self {
		$path = WP_PLUGIN_DIR . "/$plugin";

		if ( ! is_dir( $path ) ) {
			WP_CLI::error( "Plugin '$plugin' does not exist." );
		}

		return new self( 'plugin', $path );
	}

}
