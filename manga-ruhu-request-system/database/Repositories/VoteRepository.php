<?php
/**
 * Oy veri erişim katmanı.
 *
 * @package MangaRuhu\RequestSystem\Database\Repositories
 */

declare(strict_types=1);

namespace MangaRuhu\RequestSystem\Database\Repositories;

use MangaRuhu\RequestSystem\Database\Schema;

final class VoteRepository {

	private string $table;
	private \wpdb $db;

	public function __construct() {
		global $wpdb;
		$this->db    = $wpdb;
		$this->table = Schema::table( Schema::TABLE_VOTES );
	}

	/**
	 * Kullanıcının daha önce kullandığı oy tipini döndür ('up', 'down' veya null).
	 */
	public function get_vote_type( int $request_id, string $voter_key ): ?string {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$type = $this->db->get_var(
			$this->db->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT vote_type FROM {$this->table} WHERE request_id = %d AND voter_key = %s LIMIT 1",
				$request_id,
				$voter_key
			)
		);
		return is_string( $type ) ? $type : null;
	}

	public function has_voted( int $request_id, string $voter_key ): bool {
		return null !== $this->get_vote_type( $request_id, $voter_key );
	}

	public function has_user_voted( int $request_id, int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (bool) $this->db->get_var(
			$this->db->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT id FROM {$this->table} WHERE request_id = %d AND user_id = %d LIMIT 1",
				$request_id,
				$user_id
			)
		);
	}

	public function get_user_vote_type( int $request_id, int $user_id ): ?string {
		if ( $user_id <= 0 ) {
			return null;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$type = $this->db->get_var(
			$this->db->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT vote_type FROM {$this->table} WHERE request_id = %d AND user_id = %d LIMIT 1",
				$request_id,
				$user_id
			)
		);
		return is_string( $type ) ? $type : null;
	}

	public function has_guest_ip_voted( int $request_id, string $ip ): bool {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (bool) $this->db->get_var(
			$this->db->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT id FROM {$this->table} WHERE request_id = %d AND user_id = 0 AND ip_address = %s LIMIT 1",
				$request_id,
				$ip
			)
		);
	}

	public function get_guest_ip_vote_type( int $request_id, string $ip ): ?string {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$type = $this->db->get_var(
			$this->db->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT vote_type FROM {$this->table} WHERE request_id = %d AND user_id = 0 AND ip_address = %s LIMIT 1",
				$request_id,
				$ip
			)
		);
		return is_string( $type ) ? $type : null;
	}

	/**
	 * Yeni oy ekle.
	 */
	public function create( array $data ): bool {
		$result = $this->db->insert(
			$this->table,
			array(
				'request_id' => (int) ( $data['request_id'] ?? 0 ),
				'user_id'    => (int) ( $data['user_id'] ?? 0 ),
				'ip_address' => (string) ( $data['ip_address'] ?? '' ),
				'voter_key'  => (string) ( $data['voter_key'] ?? '' ),
				'vote_type'  => in_array( $data['vote_type'] ?? 'up', array( 'up', 'down' ), true ) ? $data['vote_type'] : 'up',
				'created_at' => current_time( 'mysql', true ),
				'updated_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		return false !== $result && (int) $this->db->insert_id > 0;
	}

	/**
	 * Mevcut oyu değiştir (vote_type güncelle).
	 */
	public function update_vote_type( int $request_id, string $voter_key, string $vote_type ): bool {
		$result = $this->db->update(
			$this->table,
			array(
				'vote_type'  => $vote_type,
				'updated_at' => current_time( 'mysql', true ),
			),
			array(
				'request_id' => $request_id,
				'voter_key'  => $voter_key,
			),
			array( '%s', '%s' ),
			array( '%d', '%s' )
		);

		return false !== $result;
	}
}