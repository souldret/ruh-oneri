<?php
/**
 * Oylama servisi — up/down, oy değiştirme desteği.
 *
 * @package MangaRuhu\RequestSystem\Services
 */

declare(strict_types=1);

namespace MangaRuhu\RequestSystem\Services;

use MangaRuhu\RequestSystem\Admin\Settings;
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

		// Misafir oy ayarı kontrolü
		if ( $user_id <= 0 && ! Settings::get_option( 'allow_guest_votes', true ) ) {
			return new WP_Error(
				'mrrs_guest_votes_disabled',
				__( 'Oy kullanmak için giriş yapmanız gerekiyor.', 'manga-ruhu-request-system' ),
				array( 'status' => 403 )
			);
		}

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
		// Önce Cloudflare header'ı dene (güvenilir, proxy'nin kendisi ayarlar).
		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			$cf_ip = sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
			if ( filter_var( $cf_ip, FILTER_VALIDATE_IP ) ) {
				return $cf_ip;
			}
		}

		// X-Forwarded-For: yalnızca REMOTE_ADDR bilinen Cloudflare IP bloklarından geliyorsa güven.
		// Aksi halde saldırgan bu header'ı istediği gibi ayarlayabilir (IP spoofing riski).
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) && ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$remote = sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) );
			if ( $this->is_cloudflare_ip( $remote ) ) {
				$parts  = explode( ',', (string) wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
				$xff_ip = sanitize_text_field( trim( $parts[0] ) );
				if ( filter_var( $xff_ip, FILTER_VALIDATE_IP ) ) {
					return $xff_ip;
				}
			}
		}

		// Son çare: gerçek bağlantı IP'si.
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$remote_ip = sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) );
			if ( filter_var( $remote_ip, FILTER_VALIDATE_IP ) ) {
				return $remote_ip;
			}
		}

		return '0.0.0.0';
	}

	/**
	 * Verilen IP'nin bilinen Cloudflare IPv4/IPv6 CIDR bloklarından birine ait olup olmadığını kontrol et.
	 * Kaynak: https://www.cloudflare.com/ips/
	 */
	private function is_cloudflare_ip( string $ip ): bool {
		$cf_ranges = apply_filters( 'mrrs_cloudflare_ip_ranges', array(
			// IPv4
			'173.245.48.0/20',
			'103.21.244.0/22',
			'103.22.200.0/22',
			'103.31.4.0/22',
			'141.101.64.0/18',
			'108.162.192.0/18',
			'190.93.240.0/20',
			'188.114.96.0/20',
			'197.234.240.0/22',
			'198.41.128.0/17',
			'162.158.0.0/15',
			'104.16.0.0/13',
			'104.24.0.0/14',
			'172.64.0.0/13',
			'131.0.72.0/22',
			// IPv6
			'2400:cb00::/32',
			'2606:4700::/32',
			'2803:f800::/32',
			'2405:b500::/32',
			'2405:8100::/32',
			'2a06:98c0::/29',
			'2c0f:f248::/32',
		) );

		foreach ( $cf_ranges as $cidr ) {
			if ( $this->ip_in_cidr( $ip, $cidr ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * IP adresinin CIDR bloğu içinde olup olmadığını kontrol et (IPv4 + IPv6).
	 */
	private function ip_in_cidr( string $ip, string $cidr ): bool {
		list( $subnet, $bits ) = explode( '/', $cidr );
		$bits = (int) $bits;

		// IPv6 kontrolü
		if ( str_contains( $ip, ':' ) || str_contains( $subnet, ':' ) ) {
			$ip_bin     = inet_pton( $ip );
			$subnet_bin = inet_pton( $subnet );
			if ( false === $ip_bin || false === $subnet_bin ) {
				return false;
			}
			$mask_bytes = $bits >> 3;
			$mask_bits  = $bits & 7;
			for ( $i = 0; $i < $mask_bytes; $i++ ) {
				if ( $ip_bin[ $i ] !== $subnet_bin[ $i ] ) {
					return false;
				}
			}
			if ( $mask_bits > 0 && $mask_bytes < 16 ) {
				$mask = 0xFF & ( 0xFF << ( 8 - $mask_bits ) );
				if ( ( ord( $ip_bin[ $mask_bytes ] ) & $mask ) !== ( ord( $subnet_bin[ $mask_bytes ] ) & $mask ) ) {
					return false;
				}
			}
			return true;
		}

		// IPv4 kontrolü
		$ip_long     = ip2long( $ip );
		$subnet_long = ip2long( $subnet );
		if ( false === $ip_long || false === $subnet_long || $bits > 32 ) {
			return false;
		}
		$mask = $bits > 0 ? ( ~0 << ( 32 - $bits ) ) : 0;
		return ( $ip_long & $mask ) === ( $subnet_long & $mask );
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
			'item'       => $this->requests->to_public_array( $row ),
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

		$this->set_transient_no_autoload( 'mrrs_vote_cd_' . $ip_hash, time(), max( 1, $cooldown ) );
		$rl_key = 'mrrs_vote_rl_' . $ip_hash;
		$this->set_transient_no_autoload( $rl_key, (int) get_transient( $rl_key ) + 1, max( 60, $window ) );
	}

	/**
	 * Object cache varsa doğrudan object cache'e yaz (wp_options'a dokunmaz).
	 * Object cache yoksa transient'i autoload=no ile kaydet — wp_options şişmesini önler.
	 *
	 * Not: set_transient() WordPress'in kendi transient API'sini kullanır ve
	 * object cache aktifse zaten wp_options'a yazmaz. Object cache yoksa
	 * "autoload=yes" ile kaydeder; bu yüksek trafikte gereksiz yük oluşturur.
	 * Burada bu durumu doğrudan $wpdb ile ele alıyoruz.
	 *
	 * @param string $transient Transient adı.
	 * @param mixed  $value     Değer.
	 * @param int    $expiration Süre (saniye).
	 */
	private function set_transient_no_autoload( string $transient, mixed $value, int $expiration ): void {
		// Object cache etkin → normal set_transient (wp_options'a yazmaz zaten).
		if ( wp_using_ext_object_cache() ) {
			set_transient( $transient, $value, $expiration );
			return;
		}

		// Object cache yok → wp_options'a autoload=no ile yaz.
		global $wpdb;
		$option_timeout = '_transient_timeout_' . $transient;
		$option_value   = '_transient_' . $transient;
		$serialized     = maybe_serialize( $value );
		$expire_time    = time() + $expiration;

		// Önce timeout seçeneğini yaz/güncelle (autoload=no).
		$existing = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SELECT option_value FROM ' . $wpdb->options . ' WHERE option_name = %s LIMIT 1', $option_timeout ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		if ( null !== $existing ) {
			$wpdb->update( $wpdb->options, array( 'option_value' => $expire_time ), array( 'option_name' => $option_timeout ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update( $wpdb->options, array( 'option_value' => $serialized ), array( 'option_name' => $option_value ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		} else {
			$wpdb->insert( $wpdb->options, array( 'option_name' => $option_timeout, 'option_value' => $expire_time, 'autoload' => 'no' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert( $wpdb->options, array( 'option_name' => $option_value,   'option_value' => $serialized,  'autoload' => 'no' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}

		// WP internal cache'i geçersiz kıl.
		wp_cache_delete( $transient, 'transient' );
	}
}