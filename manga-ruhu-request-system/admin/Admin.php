<?php
/**
 * Admin paneli bootstrap.
 *
 * @package MangaRuhu\RequestSystem\Admin
 */

declare(strict_types=1);

namespace MangaRuhu\RequestSystem\Admin;

use MangaRuhu\RequestSystem\Loader;

final class Admin {

	private Loader $loader;
	private Settings $settings;
	private Ajax $ajax;

	/** @var string[] Kayıtlı admin sayfa hook'ları */
	private array $page_hooks = array();

	public function __construct( Loader $loader ) {
		$this->loader   = $loader;
		$this->settings = new Settings();
		$this->ajax     = new Ajax();
	}

	public function register(): void {
		$this->loader->add_action( 'admin_menu',            $this, 'add_menus' );
		$this->loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_assets' );
		$this->loader->add_action( 'admin_notices',         $this, 'frontend_page_notices' );
		$this->settings->register();
		$this->ajax->register();
	}

	public function add_menus(): void {
		$this->page_hooks[] = (string) add_menu_page(
			__( 'Seri Önerileri', 'manga-ruhu-request-system' ),
			__( 'Seri Önerileri', 'manga-ruhu-request-system' ),
			'manage_options',
			'mrrs-dashboard',
			array( $this, 'render_dashboard' ),
			'dashicons-list-view',
			30
		);

		// İlk alt menü ana menüyle aynı callback'i kullanır.
		add_submenu_page(
			'mrrs-dashboard',
			__( 'Genel Bakış', 'manga-ruhu-request-system' ),
			__( 'Genel Bakış', 'manga-ruhu-request-system' ),
			'manage_options',
			'mrrs-dashboard',
			array( $this, 'render_dashboard' )
		);

		$this->page_hooks[] = (string) add_submenu_page(
			'mrrs-dashboard',
			__( 'Öneriler', 'manga-ruhu-request-system' ),
			__( 'Öneriler', 'manga-ruhu-request-system' ),
			'manage_options',
			'mrrs-requests',
			array( $this, 'render_requests' )
		);

		$this->page_hooks[] = (string) add_submenu_page(
			'mrrs-dashboard',
			__( 'Ayarlar', 'manga-ruhu-request-system' ),
			__( 'Ayarlar', 'manga-ruhu-request-system' ),
			'manage_options',
			'mrrs-settings',
			array( $this->settings, 'render_page' )
		);
	}

	/**
	 * Frontend sayfa uyarıları.
	 * - Sayfa seçilmemişse bilgi notu.
	 * - Seçili sayfa silinmiş/yayında değilse uyarı.
	 */
	public function frontend_page_notices(): void {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		// Ekran ID'sini slug'a göre de kontrol et (hook ID'den bağımsız).
		$page_query = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$mrrs_slugs = array( 'mrrs-dashboard', 'mrrs-requests', 'mrrs-settings' );
		$in_hooks   = in_array( $screen->id, $this->page_hooks, true );
		$in_slugs   = in_array( $page_query, $mrrs_slugs, true );

		if ( ! $in_hooks && ! $in_slugs ) {
			return;
		}

		$page_id      = Settings::get_frontend_page_id();
		$settings_url = admin_url( 'admin.php?page=mrrs-settings' );

		if ( $page_id <= 0 ) {
			printf(
				'<div class="notice notice-info"><p>%s <a href="%s">%s</a></p></div>',
				esc_html__( 'MangaRuhu: Öneri sisteminin görüneceği bir sayfa seçilmedi.', 'manga-ruhu-request-system' ),
				esc_url( $settings_url ),
				esc_html__( 'Ayarlara Git →', 'manga-ruhu-request-system' )
			);
			return;
		}

		$post_status = get_post_status( $page_id );
		if ( false === $post_status || 'publish' !== $post_status ) {
			printf(
				'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
				esc_html__( 'MangaRuhu: Seçili öneri sayfası artık mevcut değil veya yayında değil. Öneri sistemi devre dışı.', 'manga-ruhu-request-system' ),
				esc_url( $settings_url ),
				esc_html__( 'Yeni Sayfa Seç →', 'manga-ruhu-request-system' )
			);
		}
	}

	public function render_dashboard(): void {
		$this->load_template( 'admin/dashboard' );
	}

	public function render_requests(): void {
		$this->load_template( 'admin/requests' );
	}

	public function enqueue_assets( string $hook ): void {
		if ( ! in_array( $hook, $this->page_hooks, true ) ) {
			return;
		}

		$v = MRRS_VERSION;

		wp_enqueue_style(
			'mrrs-admin',
			MRRS_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			$v
		);

		wp_enqueue_script(
			'mrrs-admin',
			MRRS_PLUGIN_URL . 'assets/js/admin.js',
			array(),
			$v,
			true
		);

		wp_localize_script( 'mrrs-admin', 'mrrsAdmin', array(
			'nonce'    => wp_create_nonce( 'mrrs_admin_nonce' ),
			'ajax_url' => admin_url( 'admin-ajax.php' ),
		) );
	}

	private function load_template( string $name ): void {
		$file = MRRS_PLUGIN_DIR . 'templates/' . $name . '.php';
		if ( file_exists( $file ) ) {
			include $file;
		}
	}
}