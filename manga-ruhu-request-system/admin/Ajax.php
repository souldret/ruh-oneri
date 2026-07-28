<?php
/**
 * Admin AJAX endpoint'leri.
 *
 * @package MangaRuhu\RequestSystem\Admin
 */

declare(strict_types=1);

namespace MangaRuhu\RequestSystem\Admin;

use MangaRuhu\RequestSystem\Database\Repositories\RequestRepository;
use MangaRuhu\RequestSystem\Security;

final class Ajax {

	public function register(): void {
		$actions = array(
			'mrrs_admin_list_requests' => 'list_requests',
			'mrrs_admin_get_request'   => 'get_request',
			'mrrs_admin_save_request'  => 'save_request',
			'mrrs_admin_delete'        => 'delete_requests',
			'mrrs_admin_bulk_status'   => 'bulk_status',
		);

		foreach ( $actions as $action => $method ) {
			add_action( 'wp_ajax_' . $action, array( $this, $method ) );
		}
	}

	private function authorize(): void {
		Security::require_admin_ajax( 'nonce' );
	}

	/**
	 * Önerileri listele (admin paneli için).
	 */
	public function list_requests(): void {
		$this->authorize();

		$repo   = new RequestRepository();
		$result = $repo->query( array(
			'search'   => isset( $_REQUEST['search'] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST['search'] ) ) : '',
			'status'   => isset( $_REQUEST['status'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['status'] ) ) : '',
			'sort'     => isset( $_REQUEST['sort'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['sort'] ) ) : 'newest',
			'page'     => isset( $_REQUEST['mrrs_page'] ) ? absint( $_REQUEST['mrrs_page'] ) : 1,
			'per_page' => isset( $_REQUEST['per_page'] ) ? absint( $_REQUEST['per_page'] ) : 20,
		) );

		wp_send_json_success( array(
			'items'       => array_map( static fn( object $row ): array => $repo->to_array( $row ), $result['items'] ),
			'total'       => $result['total'],
			'page'        => $result['page'],
			'per_page'    => $result['per_page'],
			'total_pages' => $result['total_pages'],
			'has_more'    => $result['has_more'],
		) );
	}

	/**
	 * Tek öneri getir.
	 */
	public function get_request(): void {
		$this->authorize();

		$id   = isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : 0;
		$repo = new RequestRepository();
		$row  = $repo->find( $id );

		if ( ! $row ) {
			wp_send_json_error( array( 'message' => __( 'Öneri bulunamadı.', 'manga-ruhu-request-system' ) ), 404 );
		}

		wp_send_json_success( array( 'item' => $repo->to_array( $row ) ) );
	}

	/**
	 * Öneri oluştur veya güncelle.
	 */
	public function save_request(): void {
		$this->authorize();

		$id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$repo = new RequestRepository();

		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['title'] ) ) : '';
		if ( '' === $title ) {
			wp_send_json_error( array( 'message' => __( 'Seri adı zorunludur.', 'manga-ruhu-request-system' ) ), 400 );
			return;
		}

		$data = array(
			'title'       => $title,
			'source_link' => isset( $_POST['source_link'] ) ? esc_url_raw( wp_unslash( (string) $_POST['source_link'] ) ) : '',
			'description' => isset( $_POST['description'] ) ? wp_kses_post( wp_unslash( (string) $_POST['description'] ) ) : '',
			'status'      => isset( $_POST['status'] ) ? sanitize_key( wp_unslash( (string) $_POST['status'] ) ) : 'pending',
		);

		if ( $id > 0 ) {
			$ok = $repo->update( $id, $data );
			if ( ! $ok ) {
				wp_send_json_error( array( 'message' => __( 'Güncelleme başarısız.', 'manga-ruhu-request-system' ) ), 500 );
				return;
			}
		} else {
			$id = $repo->create( $data );
			if ( $id <= 0 ) {
				wp_send_json_error( array( 'message' => __( 'Oluşturma başarısız.', 'manga-ruhu-request-system' ) ), 500 );
				return;
			}
		}

		$row = $repo->find( $id );
		wp_send_json_success( array(
			'item'    => $row ? $repo->to_array( $row ) : array( 'id' => $id ),
			'message' => __( 'Kaydedildi.', 'manga-ruhu-request-system' ),
		) );
	}

	/**
	 * Bir veya birden fazla öneri sil.
	 */
	public function delete_requests(): void {
		$this->authorize();

		$ids  = $this->parse_ids();
		$repo = new RequestRepository();
		$n    = $repo->delete_many( $ids );

		wp_send_json_success( array(
			'deleted' => $n,
			'message' => sprintf(
				/* translators: %d: adet */
				_n( '%d öneri silindi.', '%d öneri silindi.', $n, 'manga-ruhu-request-system' ),
				$n
			),
		) );
	}

	/**
	 * Toplu durum değişikliği (örn: pending → approved).
	 */
	public function bulk_status(): void {
		$this->authorize();

		$ids    = $this->parse_ids();
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( (string) $_POST['status'] ) ) : '';

		$allowed = array( 'pending', 'reviewing', 'approved', 'rejected', 'translating' );
		if ( ! in_array( $status, $allowed, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Geçersiz durum.', 'manga-ruhu-request-system' ) ), 400 );
			return;
		}

		if ( array() === $ids ) {
			wp_send_json_error( array( 'message' => __( 'Hiç öneri seçilmedi.', 'manga-ruhu-request-system' ) ), 400 );
			return;
		}

		$n = ( new RequestRepository() )->bulk_status( $ids, $status );

		wp_send_json_success( array(
			'updated' => $n,
			'message' => sprintf(
				/* translators: %d: adet */
				_n( '%d öneri güncellendi.', '%d öneri güncellendi.', $n, 'manga-ruhu-request-system' ),
				$n
			),
		) );
	}

	private function parse_ids(): array {
		$ids = array();
		if ( isset( $_REQUEST['ids'] ) ) {
			$raw = wp_unslash( $_REQUEST['ids'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( is_array( $raw ) ) {
				$ids = array_map( 'absint', $raw );
			} else {
				$ids = array_map( 'absint', explode( ',', (string) $raw ) );
			}
		} elseif ( isset( $_REQUEST['id'] ) ) {
			$ids = array( absint( $_REQUEST['id'] ) );
		}
		return array_values( array_filter( $ids ) );
	}
}