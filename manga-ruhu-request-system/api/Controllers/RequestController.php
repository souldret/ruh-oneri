<?php
/**
 * REST controller — istek listesi, oluşturma, oylama.
 *
 * @package MangaRuhu\RequestSystem\Api\Controllers
 */

declare(strict_types=1);

namespace MangaRuhu\RequestSystem\Api\Controllers;

use MangaRuhu\RequestSystem\Admin\Settings;
use MangaRuhu\RequestSystem\Api\RestApi;
use MangaRuhu\RequestSystem\Database\Repositories\RequestRepository;
use MangaRuhu\RequestSystem\Database\Repositories\VoteRepository;
use MangaRuhu\RequestSystem\Services\VoteService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class RequestController {

	private RequestRepository $requests;
	private VoteService $vote_service;

	public function __construct() {
		$votes              = new VoteRepository();
		$this->requests     = new RequestRepository();
		$this->vote_service = new VoteService( $votes, $this->requests );
	}

	public function register_routes(): void {
		// GET /requests — listele
		// POST /requests — yeni öneri gönder
		register_rest_route(
			RestApi::NAMESPACE,
			'/requests',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_requests' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'search'   => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'sort'     => array(
							'type'              => 'string',
							'default'           => 'most_votes',
							'sanitize_callback' => 'sanitize_key',
						),
						'page'     => array(
							'type'              => 'integer',
							'default'           => 1,
							'sanitize_callback' => 'absint',
						),
						'per_page' => array(
							'type'              => 'integer',
							'default'           => 20,
							'sanitize_callback' => 'absint',
						),
						'status'   => array(
							'type'              => 'string',
							'default'           => 'all',
							'sanitize_callback' => 'sanitize_key',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_request' ),
					'permission_callback' => array( $this, 'can_write' ),
				),
			)
		);

		// POST /requests/{id}/vote — oy ver veya değiştir
		// GET  /requests/{id}/vote — oy durumu
		register_rest_route(
			RestApi::NAMESPACE,
			'/requests/(?P<id>\d+)/vote',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'vote_request' ),
					'permission_callback' => array( $this, 'can_write' ),
					'args'                => array(
						'id'          => array( 'type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint' ),
						'vote_type'   => array( 'type' => 'string', 'default' => 'up', 'sanitize_callback' => 'sanitize_key' ),
						'fingerprint' => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'vote_status' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'id'          => array( 'type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint' ),
						'fingerprint' => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
					),
				),
			)
		);
	}

	/**
	 * Yazma izni: nonce + honeypot.
	 */
	public function can_write( WP_REST_Request $request ): bool|WP_Error {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! is_string( $nonce ) || '' === $nonce ) {
			$nonce = (string) $request->get_param( '_wpnonce' );
		}

		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'mrrs_invalid_nonce',
				__( 'Güvenlik doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.', 'manga-ruhu-request-system' ),
				array( 'status' => 403 )
			);
		}

		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}
		if ( ! empty( $params['website'] ) || ! empty( $params['hp_field'] ) ) {
			return new WP_Error( 'mrrs_spam', __( 'İstek reddedildi.', 'manga-ruhu-request-system' ), array( 'status' => 400 ) );
		}

		return true;
	}

	/**
	 * Onaylanmış önerileri listele.
	 */
	public function list_requests( WP_REST_Request $request ): WP_REST_Response {
		$allowed_per_page = array( 10, 20, 30, 50 );
		$default_per_page = \MangaRuhu\RequestSystem\Admin\Settings::get_per_page();
		$req_per_page     = (int) $request->get_param( 'per_page' );
		$per_page         = in_array( $req_per_page, $allowed_per_page, true ) ? $req_per_page : $default_per_page;

		$status = sanitize_key( (string) $request->get_param( 'status' ) );
		$allowed_statuses = array( 'all', 'approved', 'pending', 'reviewing', 'rejected', 'translating' );
		if ( '' === $status || ! in_array( $status, $allowed_statuses, true ) ) {
			$status = 'approved';
		}

		$result = $this->requests->query( array(
			'search'   => (string) $request->get_param( 'search' ),
			'status'   => $status,
			'sort'     => (string) $request->get_param( 'sort' ),
			'page'     => (int) $request->get_param( 'page' ),
			'per_page' => $per_page,
		) );

		// Performans: kullanıcı verilerini toplu cache'e al (N+1 sorgusu önler)
		if ( ! empty( $result['items'] ) ) {
			$this->requests->prime_user_cache( $result['items'] );
		}

		$response = new WP_REST_Response( array(
			'items'       => array_map( fn( object $row ): array => $this->requests->to_array( $row ), $result['items'] ),
			'total'       => $result['total'],
			'page'        => $result['page'],
			'per_page'    => $result['per_page'],
			'total_pages' => $result['total_pages'],
			'has_more'    => $result['has_more'],
			'from'        => $result['total'] > 0 ? ( ( $result['page'] - 1 ) * $result['per_page'] + 1 ) : 0,
			'to'          => min( $result['page'] * $result['per_page'], $result['total'] ),
		), 200 );

		// Performans: tarayıcı/CDN cache — sadece GET, misafir için 60sn
		if ( ! is_user_logged_in() ) {
			$response->header( 'Cache-Control', 'public, max-age=60, stale-while-revalidate=120' );
		} else {
			$response->header( 'Cache-Control', 'no-store' );
		}

		return $response;
	}

	/**
	 * Yeni öneri oluştur (admin onayına düşer).
	 */
	public function create_request( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		// Misafir öneri ayarı kontrolü
		if ( 0 === get_current_user_id() && ! Settings::get_option( 'allow_guest_submit', true ) ) {
			return new WP_Error(
				'mrrs_guest_submit_disabled',
				__( 'Öneri göndermek için giriş yapmanız gerekiyor.', 'manga-ruhu-request-system' ),
				array( 'status' => 403 )
			);
		}

		$title = sanitize_text_field( (string) ( $params['title'] ?? '' ) );
		if ( '' === $title ) {
			return new WP_Error( 'mrrs_invalid_title', __( 'Seri adı zorunludur.', 'manga-ruhu-request-system' ), array( 'status' => 400 ) );
		}

		$id = $this->requests->create( array(
			'title'        => $title,
			'source_link'  => (string) ( $params['source_link'] ?? '' ),
			'description'  => (string) ( $params['description'] ?? '' ),
			'status'       => 'pending',
			'requested_by' => get_current_user_id(),
		) );

		if ( $id <= 0 ) {
			return new WP_Error( 'mrrs_create_failed', __( 'Öneri gönderilemedi.', 'manga-ruhu-request-system' ), array( 'status' => 500 ) );
		}

		$row = $this->requests->find( $id );

		return new WP_REST_Response( array(
			'success' => true,
			'message' => __( 'Öneriniz alındı. Admin onayından sonra yayınlanacak.', 'manga-ruhu-request-system' ),
			'item'    => $row ? $this->requests->to_array( $row ) : array( 'id' => $id ),
		), 201 );
	}

	/**
	 * Oy kullan veya değiştir (up/down).
	 */
	public function vote_request( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id        = (int) $request->get_param( 'id' );
		$params    = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$vote_type   = sanitize_key( (string) ( $params['vote_type']   ?? $request->get_param( 'vote_type' )   ?? 'up' ) );
		$fingerprint = (string) ( $params['fingerprint'] ?? $request->get_param( 'fingerprint' ) ?? '' );

		$result = $this->vote_service->vote( $id, $vote_type, $fingerprint );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Ziyaretçinin oy durumunu döndür.
	 */
	public function vote_status( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id  = (int) $request->get_param( 'id' );
		$row = $this->requests->find( $id );

		if ( null === $row ) {
			return new WP_Error( 'mrrs_not_found', __( 'Öneri bulunamadı.', 'manga-ruhu-request-system' ), array( 'status' => 404 ) );
		}

		$fingerprint = (string) $request->get_param( 'fingerprint' );
		$status      = $this->vote_service->get_visitor_vote_status( $id, $fingerprint );

		return new WP_REST_Response( array(
			'voted'      => $status['voted'],
			'vote_type'  => $status['vote_type'],
			'up_votes'   => (int) ( $row->up_votes ?? 0 ),
			'down_votes' => (int) ( $row->down_votes ?? 0 ),
			'request_id' => $id,
		), 200 );
	}
}