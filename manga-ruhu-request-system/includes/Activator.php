<?php
/**
 * Eklenti aktivasyon/deaktivasyon işlemleri.
 *
 * @package MangaRuhu\RequestSystem
 */

declare(strict_types=1);

namespace MangaRuhu\RequestSystem;

use MangaRuhu\RequestSystem\Database\Migrator;

final class Activator {

	public static function activate(): void {
		( new Migrator() )->migrate( true );
		self::set_default_options();
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	public static function uninstall(): void {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			return;
		}

		Migrator::drop_all_tables();

		delete_option( 'mrrs_options' );
		delete_option( 'mrrs_db_version' );
	}

	private static function set_default_options(): void {
		if ( false === get_option( 'mrrs_options' ) ) {
			add_option( 'mrrs_options', array(
				'allow_guest_votes'  => true,
				'allow_guest_submit' => true,
			), '', false );
		}
	}
}