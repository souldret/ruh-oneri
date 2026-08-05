<?php
/**
 * Eklenti çekirdek sınıfı.
 *
 * @package MangaRuhu\RequestSystem
 */

declare(strict_types=1);

namespace MangaRuhu\RequestSystem;

use MangaRuhu\RequestSystem\Admin\Admin;
use MangaRuhu\RequestSystem\Api\RestApi;
use MangaRuhu\RequestSystem\Database\Migrator;
use MangaRuhu\RequestSystem\PublicFront\Frontend;
use MangaRuhu\RequestSystem\Services\CleanupService;

final class Plugin {

	private static ?self $instance = null;
	private Loader $loader;

	private function __construct() {
		$this->loader = new Loader();
		$this->maybe_upgrade_database();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_api_hooks();
		$this->define_service_hooks();
	}

	private function maybe_upgrade_database(): void {
		( new Migrator() )->migrate();
	}

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __clone(): void {}

	public function __wakeup(): void {
		throw new \RuntimeException( 'Cannot unserialize singleton.' );
	}

	private function set_locale(): void {
		$this->loader->add_action( 'init', $this, 'load_textdomain' );
	}

	public function load_textdomain(): void {
		load_plugin_textdomain(
			'manga-ruhu-request-system',
			false,
			dirname( MRRS_PLUGIN_BASENAME ) . '/languages'
		);
	}

	private function define_admin_hooks(): void {
		if ( ! is_admin() ) {
			return;
		}
		$admin = new Admin( $this->loader );
		$admin->register();
	}

	private function define_public_hooks(): void {
		$frontend = new Frontend( $this->loader );
		$frontend->register();
	}

	private function define_api_hooks(): void {
		$api = new RestApi( $this->loader );
		$api->register();
	}

	private function define_service_hooks(): void {
		( new CleanupService() )->register( $this->loader );
	}

	public function run(): void {
		$this->loader->run();
		do_action( 'mrrs_loaded', $this );
	}

	public function get_loader(): Loader {
		return $this->loader;
	}
}