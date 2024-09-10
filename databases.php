<?php
/**
 * 
 * @author Kantari Samy
 * @version 0.0.1
 */

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

class WP_DB_Check_Command {

		/**
		 * Vérifie les posts et métadonnées récents pour du spam ou du contenu malveillant.
		 *
		 * ## OPTIONS
		 *
		 * <days>
		 * : Le nombre de jours pour vérifier les posts et métadonnées récents.
		 *
		 * ## EXAMPLES
		 *
		 *     wp db-check check_content 7
		 */
	public function check_content( $args, $assoc_args ) {
		list( $days ) = $args;

		if ( ! is_numeric( $days ) || $days <= 0 ) {
			WP_CLI::error( 'Veuillez fournir un nombre de jours valide.' );
			return;
		}

		global $wpdb;

		// Vérification des posts
		$posts_results = $wpdb->get_results(
			$wpdb->prepare(
				"
            SELECT ID, post_title, post_content, post_status, post_date
            FROM {$wpdb->posts}
            WHERE post_date > DATE_SUB(NOW(), INTERVAL %d DAY)
              AND (post_content LIKE '%<script%'
               OR post_content LIKE '%<?php%'
               OR post_content LIKE '%<iframe%')
            ORDER BY post_date DESC
        ",
				$days
			)
		);

		// Vérification des métadonnées
		$meta_results = $wpdb->get_results(
			$wpdb->prepare(
				"
            SELECT pm.post_id, pm.meta_key, pm.meta_value
            FROM {$wpdb->postmeta} pm
            JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE p.post_date > DATE_SUB(NOW(), INTERVAL %d DAY)
              AND (pm.meta_value LIKE '%<script%'
               OR pm.meta_value LIKE '%<?php%'
               OR pm.meta_value LIKE '%base64_%')
            ORDER BY p.post_date DESC
        ",
				$days
			)
		);

		if ( empty( $posts_results ) && empty( $meta_results ) ) {
			WP_CLI::success( "Aucun contenu suspect trouvé dans les $days derniers jours." );
		} else {
			WP_CLI::success( "Contenu suspect vérifié pour les $days derniers jours." );
			if ( ! empty( $posts_results ) ) {
				WP_CLI::log( 'Posts suspects :' );
				WP_CLI::print_value( $posts_results );
			}
			if ( ! empty( $meta_results ) ) {
				WP_CLI::log( 'Métadonnées suspectes :' );
				WP_CLI::print_value( $meta_results );
			}
		}
	}


	/**
	 * Vérifie les commentaires récents pour du spam ou du contenu malveillant.
	 *
	 * ## OPTIONS
	 *
	 * <days>
	 * : Le nombre de jours pour vérifier les commentaires récents.
	 *
	 * ## EXAMPLES
	 *
	 *     wp db-check check_comments 7
	 */
	public function check_comments( $args, $assoc_args ) {
		list( $days ) = $args;

		if ( ! is_numeric( $days ) || $days <= 0 ) {
			WP_CLI::error( 'Veuillez fournir un nombre de jours valide.' );
			return;
		}

		global $wpdb;
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"
            SELECT comment_ID, comment_post_ID, comment_author, comment_author_email,
                   comment_author_url, comment_content, comment_date
            FROM {$wpdb->comments}
            WHERE comment_approved = '1'
              AND comment_date > DATE_SUB(NOW(), INTERVAL %d DAY)
              AND (comment_content LIKE '%<a href=%'
               OR comment_content LIKE '%<script%'
               OR comment_author_url NOT LIKE '')
            ORDER BY comment_date DESC
        ",
				$days
			)
		);

		if ( empty( $results ) ) {
			WP_CLI::success( "Aucun commentaire suspect trouvé dans les $days derniers jours." );
		} else {
			WP_CLI::success( "Commentaires suspects vérifiés pour les $days derniers jours." );
			WP_CLI::print_value( $results );
		}
	}

	/**
	 * Vérifie les utilisateurs récents.
	 *
	 * ## OPTIONS
	 *
	 * <days>
	 * : Le nombre de jours pour vérifier les utilisateurs récents.
	 *
	 * ## EXAMPLES
	 *
	 *     wp db-check check_users 30
	 */
	public function check_users( $args, $assoc_args ) {
		list( $days ) = $args;

		if ( ! is_numeric( $days ) || $days <= 0 ) {
			WP_CLI::error( 'Veuillez fournir un nombre de jours valide.' );
			return;
		}

		global $wpdb;
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"
            SELECT ID, user_login, user_email, user_registered, user_status
            FROM {$wpdb->users}
            WHERE user_registered > DATE_SUB(NOW(), INTERVAL %d DAY)
               OR user_status != 0
            ORDER BY user_registered DESC
        ",
				$days
			)
		);

		WP_CLI::success( "Utilisateurs récents vérifiés pour les $days derniers jours." );
		WP_CLI::print_value( $results );
	}

	/**
	 * Vérifie les posts modifiés récemment.
	 *
	 * ## OPTIONS
	 *
	 * <days>
	 * : Le nombre de jours pour vérifier les posts modifiés.
	 *
	 * [<cpt>...]
	 * : Les types de contenu personnalisés à vérifier (séparés par un espace).
	 *
	 * ## EXAMPLES
	 *
	 *     wp db-check check_posts 7 post page custom_post_type
	 */
	public function check_posts( $args, $assoc_args ) {
		$days = array_shift( $args );

		if ( ! is_numeric( $days ) || $days <= 0 ) {
			WP_CLI::error( 'Veuillez fournir un nombre de jours valide.' );
			return;
		}

		$valid_cpts = array();
		foreach ( $args as $cpt ) {
			if ( post_type_exists( $cpt ) ) {
				$valid_cpts[] = esc_sql( $cpt );
			} else {
				WP_CLI::warning( "Le type de contenu '$cpt' n'existe pas." );
			}
		}

		if ( empty( $valid_cpts ) ) {
			WP_CLI::error( 'Aucun type de contenu valide fourni.' );
			return;
		}

		$cpt_list = implode( "', '", $valid_cpts );

		global $wpdb;
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"
            SELECT ID, post_title, post_type, post_status, post_modified
            FROM {$wpdb->posts}
            WHERE post_modified > DATE_SUB(NOW(), INTERVAL %d DAY)
              AND post_type IN ('$cpt_list')
            ORDER BY post_modified DESC
        ",
				$days
			)
		);

		WP_CLI::success( "Posts modifiés récemment vérifiés pour les $days derniers jours pour les types : $cpt_list." );
		WP_CLI::print_value( $results );
	}
}

WP_CLI::add_command( 'db-check', 'WP_DB_Check_Command' );
