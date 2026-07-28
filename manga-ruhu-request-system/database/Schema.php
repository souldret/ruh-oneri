<?php
/**
 * Database schema definitions.
 *
 * @package MangaRuhu\RequestSystem\Database
 */

declare(strict_types=1);

namespace MangaRuhu\RequestSystem\Database;

final class Schema {

	public const TABLE_REQUESTS = 'requests';
	public const TABLE_VOTES    = 'votes';
	public const TABLE_PREFIX   = 'mrrs_';

	/** @return array<string, string> */
	public static function get_tables(): array {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$requests        = self::table( self::TABLE_REQUESTS );
		$votes           = self::table( self::TABLE_VOTES );

		return array(
			self::TABLE_REQUESTS => "CREATE TABLE {$requests} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				title varchar(255) NOT NULL DEFAULT '',
				source_link varchar(500) NOT NULL DEFAULT '',
				description text NULL,
				status varchar(20) NOT NULL DEFAULT 'pending',
				up_votes int(11) unsigned NOT NULL DEFAULT 0,
				down_votes int(11) unsigned NOT NULL DEFAULT 0,
				requested_by bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY status (status),
				KEY up_votes (up_votes),
				KEY created_at (created_at),
				KEY title (title(191))
			) {$charset_collate};",

			self::TABLE_VOTES => "CREATE TABLE {$votes} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				request_id bigint(20) unsigned NOT NULL DEFAULT 0,
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				ip_address varchar(45) NOT NULL DEFAULT '',
				voter_key varchar(64) NOT NULL DEFAULT '',
				vote_type varchar(4) NOT NULL DEFAULT 'up',
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				UNIQUE KEY request_voter (request_id, voter_key),
				KEY request_id (request_id),
				KEY user_id (user_id),
				KEY ip_address (ip_address)
			) {$charset_collate};",
		);
	}

	public static function table( string $key ): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_PREFIX . $key;
	}

	public static function get_table_names(): array {
		global $wpdb;
		$prefix = $wpdb->prefix . self::TABLE_PREFIX;
		return array(
			$prefix . self::TABLE_REQUESTS,
			$prefix . self::TABLE_VOTES,
		);
	}

	/**
	 * Voter key oluştur.
	 * Giriş yapmış: u{user_id}
	 * Misafir: sha256(ip|fingerprint|salt)
	 */
	public static function build_voter_key( int $user_id, string $ip_address, string $fingerprint = '' ): string {
		if ( $user_id > 0 ) {
			return 'u' . $user_id;
		}

		$ip_address  = trim( $ip_address );
		$fingerprint = strtolower( trim( $fingerprint ) );
		$salt        = wp_salt( 'auth' );

		if ( '' !== $fingerprint ) {
			return hash( 'sha256', 'g|' . $ip_address . '|' . $fingerprint . '|' . $salt );
		}

		return hash( 'sha256', 'ip|' . $ip_address . '|' . $salt );
	}
}