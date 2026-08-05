<?php
/**
 * Plugin Name:       MangaRuhu Request System
 * Plugin URI:        https://mangaruhu.com
 * Description:       Seri öneri sistemi — kullanıcılar öneri gönderir, admin onaylar, ziyaretçiler oy verir.
 * Version:           3.2.0
 * Requires at least: 6.0
 * Requires PHP:      8.2
 * Author:            MangaRuhu
 * Author URI:        https://mangaruhu.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       manga-ruhu-request-system
 * Domain Path:       /languages
 *
 * @package MangaRuhu\RequestSystem
 */

declare(strict_types=1);

namespace MangaRuhu\RequestSystem;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MRRS_VERSION',         '3.2.0' );
define( 'MRRS_DB_VERSION',      '3.0.0' );
define( 'MRRS_PLUGIN_FILE',     __FILE__ );
define( 'MRRS_PLUGIN_DIR',      plugin_dir_path( __FILE__ ) );
define( 'MRRS_PLUGIN_URL',      plugin_dir_url( __FILE__ ) );
define( 'MRRS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// PHP sürüm kontrolü.
if ( version_compare( PHP_VERSION, '8.2', '<' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'MangaRuhu Request System PHP 8.2 veya üstü gerektirir.', 'manga-ruhu-request-system' )
			);
		}
	);
	return;
}

// PSR-4 Autoloader.
require_once MRRS_PLUGIN_DIR . 'includes/Autoloader.php';
Autoloader::register( 'MangaRuhu\\RequestSystem\\', MRRS_PLUGIN_DIR );

// Aktivasyon / deaktivasyon hook'ları.
register_activation_hook(
	__FILE__,
	static function (): void {
		require_once MRRS_PLUGIN_DIR . 'includes/Activator.php';
		Activator::activate();

		// Reddedilen öneri temizlik cron'unu zamanla.
		if ( ! wp_next_scheduled( \MangaRuhu\RequestSystem\Services\CleanupService::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'hourly', \MangaRuhu\RequestSystem\Services\CleanupService::CRON_HOOK );
		}
	}
);

register_deactivation_hook(
	__FILE__,
	static function (): void {
		// Temizlik cron'unu kaldır.
		wp_clear_scheduled_hook( \MangaRuhu\RequestSystem\Services\CleanupService::CRON_HOOK );
		flush_rewrite_rules();
	}
);

// Eklentiyi başlat.
add_action(
	'plugins_loaded',
	static function (): void {
		Plugin::instance()->run();
	}
);