<?php
/**
 * Health check REST controller (bootstrap endpoint).
 *
 * @package MangaRuhu\RequestSystem\Api\Controllers
 */

declare(strict_types=1);

namespace MangaRuhu\RequestSystem\Api\Controllers;

use MangaRuhu\RequestSystem\Api\RestApi;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class HealthController
 *
 * Provides a minimal route so the API layer is verifiable.
 */
final class HealthController {

	/**
	 * Register routes.
	 *
	 * @since 1.0.0
	 */
	public function register_routes(): void {
		register_rest_route(
			RestApi::NAMESPACE,
			'/health',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_health' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Health payload.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_health( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );

		return new WP_REST_Response(
			array(
				'status'  => 'ok',
				'version' => MRRS_VERSION,
				'plugin'  => 'MangaRuhu Request System',
			),
			200
		);
	}
}
