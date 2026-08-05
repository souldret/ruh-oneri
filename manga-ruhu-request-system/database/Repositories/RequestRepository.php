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
			'description'  => sanitize_textarea_field( (string) ( $data['description'] ?? '' ) ),
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
				$row[ $key ] = sanitize_textarea_field( (string) $data[ $key ] );
				$formats[]   = '%s';
				break;
			case 'admin_note':
				$row[ $key ] = sanitize_textarea_field( (string) $data[ $key ] );
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

	/**
	 * Tam veri dizisi — yalnızca admin bağlamında kullanılmalıdır (admin_note içerir).
	 */
	public function to_array( object $row ): array {
		$user_id        = (int) ( $row->requested_by ?? 0 );
		$submitter_name = $this->get_submitter_name( $user_id );

		return array(
			'id'             => (int) ( $row->id ?? 0 ),
			'title'          => (string) ( $row->title ?? '' ),
			'source_link'    => (string) ( $row->source_link ?? '' ),
			'description'    => (string) ( $row->description ?? '' ),
			'admin_note'     => (string) ( $row->admin_note ?? '' ),
			'status'         => (string) ( $row->status ?? 'pending' ),
			'up_votes'       => (int) ( $row->up_votes ?? 0 ),
			'down_votes'     => (int) ( $row->down_votes ?? 0 ),
			'submitter_name' => $submitter_name,
			'created_at'     => (string) ( $row->created_at ?? '' ),
			'updated_at'     => (string) ( $row->updated_at ?? '' ),
		);
	}

	/**
	 * Herkese açık veri dizisi — admin_note alanı dahil EDİLMEZ.
	 * Tüm public REST API yanıtlarında bu metod kullanılmalıdır.
	 */
	public function to_public_array( object $row ): array {
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

	/* ── Benzer başlık eşleştirme sabitleri ── */

	/** similar_text() yüzde eşiği — bu değer veya üstü "benzer" sayılır. */
	public const SIMILAR_THRESHOLD_PCT = 72;

	/** Levenshtein mesafe eşiği: her karaktere bu oran uygulanır (kısa başlıklar için dinamik). */
	public const SIMILAR_LEVENSHTEIN_RATIO = 0.25;

	/** Yüksek benzerlik eşiği — olası mükerrer olarak işaretlenir (%90+). */
	public const DUPLICATE_THRESHOLD_PCT = 90;

	/** Sonuç limiti (benzer öneriler). */
	public const SIMILAR_LIMIT = 5;

	/**
	 * Verilen başlığa benzer, rejected dışındaki önerileri döndürür.
	 *
	 * Önce SQL LIKE ön-filtresiyle aday kümesini daraltır; ardından
	 * PHP tarafında similar_text() + levenshtein() ile gerçek benzerlik
	 * skoru hesaplayıp eşik üstü sonuçları skor sıralamasıyla döndürür.
	 *
	 * @param string $title Aranacak başlık.
	 * @param int    $limit Maksimum döndürülecek sonuç sayısı.
	 * @return array{ id: int, title: string, status: string, up_votes: int, similarity: float }[]
	 */
	public function find_similar( string $title, int $limit = self::SIMILAR_LIMIT ): array {
		$normalized = $this->normalize_title( $title );
		if ( mb_strlen( $normalized ) < 2 ) {
			return array();
		}

		// SQL ön-filtre: normalized başlığın ilk kelimesini LIKE ile filtrele.
		// rejected dışındaki statüler: pending, reviewing, approved, translating.
		$parts      = explode( ' ', $normalized, 2 );
		$first_word = $parts[0];
		$like       = '%' . $this->db->esc_like( $first_word ) . '%';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $this->db->get_results(
			$this->db->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT id, title, status, up_votes FROM {$this->table}
				 WHERE status != 'rejected'
				 AND LOWER(title) LIKE %s
				 ORDER BY up_votes DESC
				 LIMIT 100",
				$like
			)
		);

		// İlk kelimenin LIKE'ı çok az sonuç getirirse tam tablo taraması yap.
		if ( empty( $rows ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $this->db->get_results(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT id, title, status, up_votes FROM {$this->table}
				 WHERE status != 'rejected'
				 ORDER BY up_votes DESC
				 LIMIT 200"
			);
		}

		if ( ! is_array( $rows ) || empty( $rows ) ) {
			return array();
		}

		$threshold_pct      = (float) apply_filters( 'mrrs_similar_threshold_pct', self::SIMILAR_THRESHOLD_PCT );
		$levenshtein_ratio  = (float) apply_filters( 'mrrs_similar_levenshtein_ratio', self::SIMILAR_LEVENSHTEIN_RATIO );
		$candidates         = array();

		foreach ( $rows as $row ) {
			$norm_candidate = $this->normalize_title( (string) ( $row->title ?? '' ) );
			if ( mb_strlen( $norm_candidate ) < 1 ) {
				continue;
			}

			// similar_text() yüzde hesabı.
			similar_text( $normalized, $norm_candidate, $pct );

			// Levenshtein mesafe kontrolü (PHP sınırı: her iki string de ≤ 255 karakter olmalı).
			$max_len   = max( mb_strlen( $normalized ), mb_strlen( $norm_candidate ) );
			$lev_match = false;
			if ( strlen( $normalized ) <= 255 && strlen( $norm_candidate ) <= 255 ) {
				$lev       = levenshtein( $normalized, $norm_candidate );
				$lev_limit = (int) ceil( $max_len * $levenshtein_ratio );
				$lev_match = ( $lev <= $lev_limit );
			}

			if ( $pct >= $threshold_pct || $lev_match ) {
				$candidates[] = array(
					'id'         => (int) ( $row->id ?? 0 ),
					'title'      => (string) ( $row->title ?? '' ),
					'status'     => (string) ( $row->status ?? 'pending' ),
					'up_votes'   => (int) ( $row->up_votes ?? 0 ),
					'similarity' => round( (float) $pct, 1 ),
				);
			}
		}

		// Benzerlik skoruna göre azalan sırala.
		usort( $candidates, static function ( array $a, array $b ): int {
			return $b['similarity'] <=> $a['similarity'];
		} );

		return array_slice( $candidates, 0, max( 1, $limit ) );
	}

	/**
	 * Başlığı normalize eder: küçük harf, Türkçe karakter düzeltmesi,
	 * noktalama temizliği ve yaygın tür eklerini kaldırır.
	 */
	public function normalize_title( string $title ): string {
		// Türkçe büyük/küçük harf dönüşümü.
		$title = str_replace(
			array( 'İ', 'I', 'Ğ', 'Ü', 'Ş', 'Ö', 'Ç' ),
			array( 'i', 'ı', 'ğ', 'ü', 'ş', 'ö', 'ç' ),
			$title
		);
		$title = mb_strtolower( $title, 'UTF-8' );

		// Yaygın tür eklerini kaldır (kelime sınırında).
		$suffixes = array( ' manga', ' manhwa', ' manhua', ' webtoon', ' novel', ' light novel', ' ln', ' web novel' );
		foreach ( $suffixes as $suffix ) {
			if ( str_ends_with( $title, $suffix ) ) {
				$title = mb_substr( $title, 0, mb_strlen( $title ) - mb_strlen( $suffix ) );
			}
		}

		// Noktalama ve fazla boşlukları temizle.
		$title = preg_replace( '/[^\p{L}\p{N}\s]/u', ' ', $title ) ?? $title;
		$title = preg_replace( '/\s+/', ' ', $title ) ?? $title;

		return trim( $title );
	}

	/**
	 * Belirtilen süreden daha eski ve status=rejected olan kayıtları batch'li siler.
	 * İlişkili oy kayıtlarını da temizler.
	 *
	 * @param int $seconds Kaç saniye önceki kayıtlar silinsin.
	 * @return int Silinen kayıt sayısı.
	 */
	public function delete_rejected_older_than( int $seconds ): int {
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $seconds );
		$total  = 0;
		$votes  = new VoteRepository();

		do {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$ids = $this->db->get_col(
				$this->db->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT id FROM {$this->table}
					 WHERE status = %s AND created_at < %s
					 LIMIT 200",
					'rejected',
					$cutoff
				)
			);

			if ( empty( $ids ) ) {
				break;
			}

			$ids          = array_map( 'intval', $ids );
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

			// İlişkili oy kayıtlarını önce sil.
			$votes->delete_by_request_ids( $ids );

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$this->db->query(
				$this->db->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
					"DELETE FROM {$this->table} WHERE id IN ({$placeholders})",
					$ids
				)
			);

			$total += count( $ids );
		} while ( count( $ids ) === 200 );

		return $total;
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