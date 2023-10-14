<?php

namespace WP_CLI_Utils\Traits;

use WP_CLI;
use WP_CLI\Utils;

trait Commander {

	protected function get_cmd( string $cmd, array $assoc_args ) : string {
		$assoc_args = array_filter( $assoc_args, fn ( $arg ) => $arg !== false );

		$assoc_args = Utils\assoc_args_to_str( $assoc_args );

		return $cmd . $assoc_args;
	}

	protected function run_cmd( string $cmd, array $assoc_args = [] ) : void {
		$cmd = $this->get_cmd( $cmd, $assoc_args );

		WP_CLI::log( "\n> $cmd" );

		passthru( $cmd, $return_code );

		if ( $return_code > 0 ) {
			WP_CLI::halt( $return_code );
		}
	}

	protected function run_wp( string $cmd, array $assoc_args = [], array $options = [] ) {
		$cmd = $this->get_cmd( $cmd, $assoc_args );

		WP_CLI::log( "\n> wp $cmd" );

		return WP_CLI::runcommand( $cmd, $options );
	}

}
