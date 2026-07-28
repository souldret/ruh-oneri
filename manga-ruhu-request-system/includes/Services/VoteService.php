<?php
/**
 * Oylama servisi — up/down, oy değiştirme desteği.
 *
 * @package MangaRuhu\RequestSystem\Services
 */

declare(strict_types=1);

namespace MangaRuhu\RequestSystem\Services;

use MangaRuhu\RequestSystem\Database\Repositories\RequestRepository;
use MangaRuhu\RequestSystem\Database\Repositories\VoteRepository;
use MangaRuhu\RequestSystem\Database\Schema;
use WP_Error;

final class VoteService {

	private const DEFAULT_RATE_LIMIT  = 20;
	private const DEFAULT_RATE_WINDOW = 3600; // 1 saat
	private const DEFAULT_COOLDOWN    = 3;

	private VoteRepository $votes;
	private RequestRepository $requests;

	public function __construct( ?VoteRepository $votes = null, ?RequestRepository $requests = null ) {
		$this->votes    = $votes    ?? new VoteRepository();
		$this->requests = $requests ?? new RequestRepository();
	}

	/**
	 * Oy kullan veya değiştir.
	 *
	 * @param string $vote_type 'up' | 'down'
	 * @return array<string, mixed>|WP_Error
	 */
	public function vote( int $request_id, string $vote_type = 'up', string $fingerprint = '' ): array|WP_Error {
		$vote_type = in_array( $vote_type, array( 'up', 'down' ), true ) ? $vote_type : 'up';

		if ( $request_id <= 0 ) {
			return new WP_Error( 'mrrs_invalid_request', __( 'Geçersiz istek.', 'manga-ruhu-request-system' ), array( 'status' => 400 ) );
		}

		$row = $this->requests->find( $request_id );
		if ( null === $row ) {
			return new WP_Error( 'mrrs_not_found', __( 'İstek bulunamadı.', 'manga-ruhu-request-system' ), array( 'status' => 404 ) );
		}

		$votable_statuses = apply_filters( 'mrrs_votable_statuses', array( 'approved', 'reviewing', 'translating' ) );
		if ( ! in_array( (string) ( $row->status ?? '' ), $votable_statuses, true ) ) {
			return new WP_Error( 'mrrs_not_votable', __( 'Bu öneri şu anda oylamaya açık değil.', 'manga-ruhu-request-system' ), array( 'status' => 403 ) );
		}

		$user_id     = get_current_user_id();
		$ip          = $this->client_ip();
		$fingerprint = $this->sanitize_fingerprint( $fingerprint );

		if ( $user_id <= 0 && '' === $fingerprint ) {
			return new WP_Error( 'mrrs_fingerprint_required', __( 'Tarayıcınız doğrulanamadı. JavaScript\'i etkinleştirip tekrar deneyin.', 'manga-ruhu-request-system' ), array( 'status' => 400 ) );
		}

		$rate = $this->check_rate_limits( $ip );
		if ( is_wp_error( $rate ) ) {
			return $rate;
		}

		$voter_key = Schema::build_voter_key( $user_id, $ip, $fingerprint );

		// Mevcut oy kontrolü
		$existing_type = $this->get_existing_vote_type( $request_id, $voter_key, $user_id, $ip );

		if ( null !== $existing_type ) {
			// Aynı oy tipine tekrar tıkladı → değişiklik yok
			if ( $existing_type === $vote_type ) {
				$fresh = $this->requests->find( $request_id );
				return $this->build_payload( $fresh ?? $row, $vote_type, false, 'unchanged' );
			}

			// Farklı oy tipine tıkladı → oyu değiştir
			$changed = $this->votes->update_vote_type( $request_id, $voter_key, $vote_type );
			if ( $changed ) {
				$this->recalculate_vote_counters( $request_id );
				$this->hit_rate_limits( $ip );
			}
			$fresh = $this->requests->find( $request_id );
			return $this->build_payload( $fresh ?? $row, $vote_type, $changed, 'changed' );
		}

		// Yeni oy
		$inserted = $this->votes->create( array(
			'request_id' => $request_id,
			'user_id'    => $user_id,
			'ip_address' => $ip,
			'voter_key'  => $voter_key,
			'vote_type'  => $vote_type,
		) );

		if ( ! $inserted ) {
			// Race condition: insert başarısız ama kayıt var (UNIQUE KEY)
			$existing_type = $this->get_existing_vote_type( $request_id, $voter_key, $user_id, $ip );
			if ( null !== $existing_type ) {
				$fresh = $this->requests->find( $request_id );
				return $this->build_payload( $fresh ?? $row, $existing_type, false, 'unchanged' );
			}
			return new WP_Error( 'mrrs_vote_failed', __( 'Oy kaydedilemedi. Lütfen tekrar deneyin.', 'manga-ruhu-request-system' ), array( 'status' => 500 ) );
		}

		$this->recalculate_vote_counters( $request_id );
		$this->hit_rate_limits( $ip );

		$fresh = $this->requests->find( $request_id );
		return $this->build_payload( $fresh ?? $row, $vote_type, true, 'new' );
	}

	/**
	 * Ziyaretçinin mevcut oy bilgisini döndür.
	 * @return array{voted: bool, vote_type: string|null}
	 */
	public function get_visitor_vote_status( int $request_id, string $fingerprint = '' ): array {
		$user_id     = get_current_user_id();
		$ip          = $this->client_ip();
		$fingerprint = $this->sanitize_fingerprint( $fingerprint );

		$voter_key = Schema::build_voter_key( $user_id, $ip, $fingerprint );
		$type      = $this->get_existing_vote_type( $request_id, $voter_key, $user_id, $ip );

		return array(
			'voted'     => null !== $type,
			'vote_type' => $type,
		);
	}

	public function sanitize_fingerprint( string $fingerprint ): string {
		$fingerprint = strtolower( trim( $fingerprint ) );
		$fingerprint = preg_replace( '/[^a-z0-9\-_]/', '', $fingerprint ) ?? '';
		if ( strlen( $fingerprint ) < 16 ) {
			return '';
		}
		return substr( $fingerprint, 0, 128 );
	}

	public function client_ip(): string {
		$candidates = array();
		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			$candidates[] = (string) wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] );
		}
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$parts        = explode( ',', (string) wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
			$candidates[] = trim( $parts[0] );
		}
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$candidates[] = (string) wp_unslash( $_SERVER['REMOTE_ADDR'] );
		}
		foreach ( $candidates as $ip ) {
			$ip = sanitize_text_field( $ip );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}
		return '0.0.0.0';
	}

	/* ── Private helpers ── */

	private function get_existing_vote_type( int $request_id, string $voter_key, int $user_id, string $ip ): ?string {
		// voter_key ile ara
		$type = $this->votes->get_vote_type( $request_id, $voter_key );
		if ( null !== $type ) {
			return $type;
		}

		// Kayıtlı kullanıcı için user_id ile ara
		if ( $user_id > 0 ) {
			return $this->votes->get_user_vote_type( $request_id, $user_id );
		}

		// Misafir için IP ile ara
		return $this->votes->get_guest_ip_vote_type( $request_id, $ip );
	}

	/**
	 * up_votes ve down_votes sayaçlarını votes tablosundan yeniden hesapla.
	 */
	private function recalculate_vote_counters( int $request_id ): void {
		global $wpdb;

		$requests = Schema::table( Schema::TABLE_REQUESTS );
		$votes    = Schema::table( Schema::TABLE_VOTES );
		$now      = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"UPDATE {$requests} SET
					up_votes   = (SELECT COUNT(*) FROM {$votes} WHERE request_id = %d AND vote_type = 'up'),
					down_votes = (SELECT COUNT(*) FROM {$votes} WHERE request_id = %d AND vote_type = 'down'),
					updated_at = %s
				WHERE id = %d",
				$request_id,
				$request_id,
				$now,
				$request_id
			)
		);
	}

	private function build_payload( object $row, string $vote_type, bool $changed, string $action ): array {
		return array(
			'success'    => $changed || 'unchanged' === $action,
			'voted'      => true,
			'vote_type'  => $vote_type,
			'action'     => $action,
			'up_votes'   => (int) ( $row->up_votes ?? 0 ),
			'down_votes' => (int) ( $row->down_votes ?? 0 ),
			'item'       => $this->requests->to_array( $row ),
		);
	}

	private function check_rate_limits( string $ip ): true|WP_Error {
		$limit    = (int) apply_filters( 'mrrs_vote_rate_limit', self::DEFAULT_RATE_LIMIT );
		$window   = (int) apply_filters( 'mrrs_vote_rate_window', self::DEFAULT_RATE_WINDOW );
		$cooldown = (int) apply_filters( 'mrrs_vote_cooldown', self::DEFAULT_COOLDOWN );
		$ip_hash  = md5( $ip . '|' . wp_salt( 'nonce' ) );

		$cd_key = 'mrrs_vote_cd_' . $ip_hash;
		$last   = get_transient( $cd_key );
		if ( false !== $last && $cooldown > 0 ) {
			$elapsed = time() - (int) $last;
			if ( $elapsed < $cooldown ) {
				return new WP_Error( 'mrrs_vote_cooldown', __( 'Tekrar oylamadan önce biraz bekleyin.', 'manga-ruhu-request-system' ), array( 'status' => 429 ) );
			}
		}

		$rl_key = 'mrrs_vote_rl_' . $ip_hash;
		$count  = (int) get_transient( $rl_key );
		if ( $limit > 0 && $count >= $limit ) {
			return new WP_Error( 'mrrs_vote_rate_limited', __( 'Çok fazla oy gönderildi. Lütfen daha sonra tekrar deneyin.', 'manga-ruhu-request-system' ), array( 'status' => 429 ) );
		}

		return true;
	}

	private function hit_rate_limits( string $ip ): void {
		$window   = (int) apply_filters( 'mrrs_vote_rate_window', self::DEFAULT_RATE_WINDOW );
		$cooldown = (int) apply_filters( 'mrrs_vote_cooldown', self::DEFAULT_COOLDOWN );
		$ip_hash  = md5( $ip . '|' . wp_salt( 'nonce' ) );

		set_transient( 'mrrs_vote_cd_' . $ip_hash, time(), max( 1, $cooldown ) );
		$rl_key = 'mrrs_vote_rl_' . $ip_hash;
		set_transient( $rl_key, (int) get_transient( $rl_key ) + 1, max( 60, $window ) );
	}
}