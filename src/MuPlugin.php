<?php

namespace WP_CLI_Utils;

use WP_CLI\Utils;

class MuPlugin {

	public string $name;

	public string $filename;

	public string $description = '';

	public function __construct( string $name, string $filename = null ) {
		$this->name = $name;

		$this->filename = $filename ?? preg_replace( '/[^a-z]/', '-', strtolower( $name ) );
	}

	public function generate( string $code ) : string {
		$namespace = preg_replace( '/[^a-z]/i', '_', $this->name );

		$contents = <<<PHP
		<?php

		/**
		 * Plugin Name: $this->name
		 * Description: $this->description
		 * Author:      WP CLI Tools
		 */

		namespace WP_CLI_Utils_MU_Plugin\\$namespace;

		$code

		PHP;

		return $contents;
	}

	public function save( string $code, Target $target = null ) : string {
		$contents = $this->generate( $code );

		if ( $target ) {
			$dir = $target->wp_eval( 'echo WPMU_PLUGIN_DIR' );

			$mkdir_cmd = $target->get_ssh_cmd( Utils\esc_cmd( 'mkdir -p %s', $dir ) );

			passthru( $mkdir_cmd );

			$file = "$dir/$this->filename.php";

			$local_file = get_temp_dir() . uniqid( $this->filename );

			file_put_contents( $local_file, $contents );

			$transfer_cmd = $target->get_rsync_cmd( "$local_file $target:$file" );

			passthru( $transfer_cmd );

			unlink( $local_file );

		} else {
			wp_mkdir_p( WPMU_PLUGIN_DIR );

			file_put_contents( WPMU_PLUGIN_DIR . "/$this->filename.php", $contents );
		}

		return $contents;
	}

	public function delete( Target $target = null ) : void {
		if ( $target ) {
			$dir = $target->wp_eval( 'echo WPMU_PLUGIN_DIR' );

			$file = "$dir/$this->filename.php";

			$rm_cmd = $target->get_ssh_cmd( Utils\esc_cmd( 'rm -f %s', $file ) );

			passthru( $rm_cmd );

		} elseif ( is_file( WPMU_PLUGIN_DIR . "/$this->filename.php" ) ) {
			unlink( WPMU_PLUGIN_DIR . "/$this->filename.php" );
		}
	}

}
