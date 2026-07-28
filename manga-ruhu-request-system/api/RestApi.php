<?php
/**
 * REST API bootstrap.
 *
 * @package MangaRuhu\RequestSystem\Api
 */

declare(strict_types=1);

namespace MangaRuhu\RequestSystem\Api;

use MangaRuhu\RequestSystem\Loader;

final class RestApi {

	public const NAMESPACE = 'mrrs/v1';

	private Loader $loader;

	public function __construct( Loader $loader ) {
		$this->loader = $loader;
	}

	public function register(): void {
		$this->loader->add_action( 'rest_api_init', $this, 'register_routes' );
	}

	public function register_routes(): void {
		$controllers = array(
			new Controllers\HealthController(),
			new Controllers\RequestController(),
		);

		foreach ( $controllers as $controller ) {
			if ( is_object( $controller ) && method_exists( $controller, 'register_routes' ) ) {
				$controller->register_routes();
			}
		}
	}
}