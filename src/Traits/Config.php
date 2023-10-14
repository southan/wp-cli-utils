<?php

namespace WP_CLI_Utils\Traits;

use WP_CLI;

trait Config {

	protected function get_config( $prop ) {
		$configs = [
			WP_CLI::get_runner()->extra_config,
			WP_CLI::get_runner()->config,
		];

		foreach ( (array) $prop as $_prop ) {
			foreach ( $configs as $config ) {
				if ( isset( $config[ $_prop ] ) ) {
					return $config[ $_prop ];
				}
			}
		}
	}

}
