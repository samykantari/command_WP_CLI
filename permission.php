<?php
/**
 *
 * @author Kantari Samy
 * @version 0.0.1
 */


if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

class WP_File_Permission_Check_Command {

	/**
	 * Vérifie les permissions des fichiers et répertoires.
	 *
	 * ## EXAMPLES
	 *
	 *     wp file-permission check
	 */
	public function check( $args, $assoc_args ) {
		$output = shell_exec( 'find . -type d -not -perm 755 -exec ls -ld {} \;' );
		WP_CLI::log( "Répertoires avec permissions incorrectes :\n" . $output );

		$output = shell_exec( 'find . -type f -not -perm 644 -exec ls -l {} \;' );
		WP_CLI::log( "Fichiers avec permissions incorrectes :\n" . $output );
	}
}

WP_CLI::add_command( 'file-permission', 'WP_File_Permission_Check_Command' );
