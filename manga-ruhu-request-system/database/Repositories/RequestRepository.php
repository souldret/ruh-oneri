<?php
/**
 * Request veri erişim katmanı.
 *
 * @package MangaRuhu\RequestSystem\Database\Repositories
 */

declare(strict_types=1);

namespace MangaRuhu\RequestSystem\Database\Repositories;

use MangaRuhu\RequestSystem\Database\Schema;

final class RequestRepository {

	public const SORTS = array( 'most_votes', 'newest', 'oldest' );

	private string $table;
	private \wpdb $db;

	public function __construct() {
		global $wpdb;
		$this->db    = $wpdb;
		$this->table = Schema::table( Schema::TABLE_REQUESTS );
	}

	public function get_table(): string {
		return $this->table;
	}

	public function find( int $id ): ?object {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $this->db->get_row(
			$this->db->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$this->table} WHERE id = %d LIMIT 1",
				$id
			)
		);
		return $row ?: null;
	}

	/** @return array{items: object[], total: int, page: int, per_page: int, total_pages: int, has_more: bool} */
	public function query( array $args = array() ): array {
		$defaults = array(
			'search'   => '',
			'status'   => 'approved',
			'sort'     => 'most_votes',
			'page'     => 1,
			'per_page' => 20,
		);

		$args     = array_merge( $defaults, $args );
		$page     = max( 1, (int) $args['page'] );
		$per_page = max( 1, min( 50, (int) $args['per_page'] ) );
		$offset   = ( $page - 1 ) * $per_page;
		$sort     = in_array( $args['sort'], self::SORTS, true ) ? $args['sort'] : 'most_votes';
		$search   = trim( (string) $args['search'] );
		$status   = sanitize_key( (string) $args['status'] );

		$where  = array( '1=1' );
		$params = array();

		if ( '' !== $status && 'all' !== $status ) {
			$where[]  = 'status = %s';
			$params[] = $status;
		}

		if ( '' !== $search ) {
			$like     = '%' . $this->db->esc_like( $search ) . '%';
			$where[]  = '(title LIKE %s OR description LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}

		$where_sql = implode( ' AND ', $where );
		$order_sql = $this->order_clause( $sort );

		$count_sql = "SELECT COUNT(*) FROM {$this->table} WHERE {$where_sql}";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$total = (int) ( $params
			? $this->db->get_var( $this->db->prepare( $count_sql, $params ) )
			: $this->db->get_var( $count_sql )
		);

		$list_sql    = "SELECT * FROM {$this->table} WHERE {$where_sql} ORDER BY {$order_sql} LIMIT %d OFFSET %d";
		$list_params = array_merge( $params, array( $per_page, $offset ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$items = $this->db->get_results( $this->db->prepare( $list_sql, $list_params ) );
		if ( ! is_array( $items ) ) {
			$items = array();
		}

		$total_pages = (int) max( 1, ceil( $total / $per_page ) );

		return array(
			'items'       => $items,
			'total'       => $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => $total_pages,
			'has_more'    => $page < $total_pages,
		);
	}

	public function count( ?string $status = null ): int {
		if ( null === $status || '' === $status ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return (int) $this->db->get_var( "SELECT COUNT(*) FROM {$this->table}" );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $this->db->get_var(
			$this->db->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT COUNT(*) FROM {$this->table} WHERE status = %s",
				$status
			)
		);
	}

	public function create( array $data ): int {
		$now = current_time( 'mysql', true );

		$row = array(
			'title'        => sanitize_text_field( (string) ( $data['title'] ?? '' ) ),
			'source_link'  => esc_url_raw( (string) ( $data['source_link'] ?? '' ) ),
			'description'  => wp_kses_post( (string) ( $data['description'] ?? '' ) ),
			'status'       => (string) ( $data['status'] ?? 'pending' ),
			'up_votes'     => (int) ( $data['up_votes'] ?? 0 ),
			'down_votes'   => (int) ( $data['down_votes'] ?? 0 ),
			'requested_by' => (int) ( $data['requested_by'] ?? get_current_user_id() ),
			'created_at'   => $now,
			'updated_at'   => $now,
		);

		$formats = array( '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s' );
		$result  = $this->db->insert( $this->table, $row, $formats );

		return false === $result ? 0 : (int) $this->db->insert_id;
	}

	public function update( int $id, array $data ): bool {
		if ( $id <= 0 ) {
			return false;
		}

		$allowed = array( 'title', 'source_link', 'description', 'admin_note', 'status', 'up_votes', 'down_votes' );
		$row     = array();
		$formats = array();

		foreach ( $allowed as $key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}
			switch ( $key ) {
				case 'up_votes':
				case 'down_votes':
					$row[ $key ] = (int) $data[ $key ];
					$formats[]   = '%d';
					break;
				case 'source_link':
					$row[ $key ] = esc_url_raw( (string) $data[ $key ] );
					$formats[]   = '%s';
					break;
				case 'description':
					$row[ $key ] = wp_kses_post( (string) $data[ $key ] );
					$formats[]   = '%s';
					break;
				default:
					$row[ $key ] = sanitize_text_field( (string) $data[ $key ] );
					$formats[]   = '%s';
					break;
			}
		}

		if ( array() === $row ) {
			return false;
		}

		$row['updated_at'] = current_time( 'mysql', true );
		$formats[]         = '%s';

		$result = $this->db->update(
			$this->table,
			$row,
			array( 'id' => $id ),
			$formats,
			array( '%d' )
		);

		return false !== $result;
	}

	public function delete( int $id ): bool {
		if ( $id <= 0 ) {
			return false;
		}

		$votes = Schema::table( Schema::TABLE_VOTES );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$this->db->query( $this->db->prepare( "DELETE FROM {$votes} WHERE request_id = %d", $id ) );

		$result = $this->db->delete( $this->table, array( 'id' => $id ), array( '%d' ) );
		return false !== $result;
	}

	public function delete_many( array $ids ): int {
		$deleted = 0;
		foreach ( array_unique( array_map( 'intval', $ids ) ) as $id ) {
			if ( $id > 0 && $this->delete( $id ) ) {
				++$deleted;
			}
		}
		return $deleted;
	}

	public function bulk_status( array $ids, string $status ): int {
		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
		if ( array() === $ids || '' === $status ) {
			return 0;
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$params       = array_merge( array( $status, current_time( 'mysql', true ) ), $ids );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$result = $this->db->query(
			$this->db->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"UPDATE {$this->table} SET status = %s, updated_at = %s WHERE id IN ({$placeholders})",
				$params
			)
		);

		return false === $result ? 0 : (int) $result;
	}

	public function counts_by_status(): array {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $this->db->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT status, COUNT(*) AS total FROM {$this->table} GROUP BY status"
		);

		$out = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$out[ (string) $row->status ] = (int) $row->total;
			}
		}
		return $out;
	}

	public function to_array( object $row ): array {
		$user_id        = (int) ( $row->requested_by ?? 0 );
		$submitter_name = $this->get_submitter_name( $user_id );

		return array(
			'id'             => (int) ( $row->id ?? 0 ),
			'title'          => (string) ( $row->title ?? '' ),
			'source_link'    => (string) ( $row->source_link ?? '' ),
			'description'    => (string) ( $row->description ?? '' ),
			'status'         => (string) ( $row->status ?? 'pending' ),
			'up_votes'       => (int) ( $row->up_votes ?? 0 ),
			'down_votes'     => (int) ( $row->down_votes ?? 0 ),
			'submitter_name' => $submitter_name,
			'created_at'     => (string) ( $row->created_at ?? '' ),
			'updated_at'     => (string) ( $row->updated_at ?? '' ),
		);
	}

	private function get_submitter_name( int $user_id ): string {
		if ( $user_id <= 0 ) {
			return __( 'Misafir', 'manga-ruhu-request-system' );
		}
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return __( 'Misafir', 'manga-ruhu-request-system' );
		}
		return $user->display_name ?: $user->user_login;
	}

	private function order_clause( string $sort ): string {
		return match ( $sort ) {
			'newest' => 'created_at DESC, id DESC',
			'oldest' => 'created_at ASC, id ASC',
			default  => 'up_votes DESC, id DESC', // most_votes
		};
	}

	/**
	 * Sorgu sonucundaki user ID'leri toplu cache'e yukler (N+1 DB sorgusu onler).
	 *
	 * @param object[] $items
	 */
	public function prime_user_cache( array $items ): void {
		$ids = array();
		foreach ( $items as $item ) {
			$uid = (int) ( $item->requested_by ?? 0 );
			if ( $uid > 0 ) {
				$ids[] = $uid;
			}
		}
		$ids = array_unique( $ids );
		if ( empty( $ids ) ) {
			return;
		}
		// cache_users() WP 6.0+ icin toplu user cache
		if ( function_exists( 'cache_users' ) ) {
			cache_users( $ids );
		} else {
			foreach ( $ids as $uid ) {
				get_userdata( (int) $uid );
			}
		}
	}
}