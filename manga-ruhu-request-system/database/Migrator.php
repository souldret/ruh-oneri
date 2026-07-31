<?php
/**
 * Database migrator.
 *
 * @package MangaRuhu\RequestSystem\Database
 */

declare(strict_types=1);

namespace MangaRuhu\RequestSystem\Database;

final class Migrator {

	public function migrate( bool $force = false ): void {
		$installed = (string) get_option( 'mrrs_db_version', '0' );
		$target    = MRRS_DB_VERSION;

		$needs = $force
			|| version_compare( $installed, $target, '<' )
			|| ! $this->tables_exist();

		if ( ! $needs ) {
			return;
		}

		$this->apply_schema();
		$this->run_column_migrations( $installed );
		$this->drop_legacy_tables();

		update_option( 'mrrs_db_version', $target, false );
	}

	public function tables_exist(): bool {
		global $wpdb;
		foreach ( Schema::get_table_names() as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( $found !== $table ) {
				return false;
			}
		}
		return true;
	}

	private function apply_schema(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ( Schema::get_tables() as $sql ) {
			dbDelta( $sql );
		}
	}

	/**
	 * v1.x → v2.x kolon migrasyonları (dbDelta yeni kolon ekler ama rename/drop yapmaz).
	 */
	private function run_column_migrations( string $from_version ): void {
		global $wpdb;

		$requests = Schema::table( Schema::TABLE_REQUESTS );
		$votes    = Schema::table( Schema::TABLE_VOTES );

		// total_votes → up_votes (eski sürümden yükseltme).
		if ( version_compare( $from_version, '2.1.0', '<' ) ) {
			$has_total = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s',
					$requests,
					'total_votes'
				)
			);

			if ( $has_total ) {
				// Eski toplam oyları up_votes'a kopyala.
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( "UPDATE {$requests} SET up_votes = total_votes WHERE up_votes = 0" );
				// total_votes kolonunu bırak; dbDelta kaldırmaz, ama artık kullanılmayacak.
			}

			// votes tablosuna vote_type kolonu ekle (varsa atla).
			$has_type = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s',
					$votes,
					'vote_type'
				)
			);

			if ( ! $has_type ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( "ALTER TABLE {$votes} ADD COLUMN vote_type varchar(4) NOT NULL DEFAULT 'up' AFTER voter_key" );
			}

			// votes tablosuna updated_at kolonu ekle (varsa atla).
			$has_updated = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s',
					$votes,
					'updated_at'
				)
			);

			if ( ! $has_updated ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( "ALTER TABLE {$votes} ADD COLUMN updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at" );
			}
		}

		// v2.9.0: gereksiz/tekrarlanan index'leri temizle.
		if ( version_compare( $from_version, '2.9.0', '<' ) ) {
			$indexes = $wpdb->get_col( "SHOW INDEX FROM {$requests}", 2 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			// status tek başına gereksiz (mrrs_status_votes ve mrrs_status_created zaten status ile başlıyor).
			if ( in_array( 'status', $indexes, true ) ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( "ALTER TABLE {$requests} DROP INDEX `status`" );
			}

			// created_at ve mrrs_created_at birebir aynı sütun için çift index — birini bırak.
			if ( in_array( 'created_at', $indexes, true ) ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( "ALTER TABLE {$requests} DROP INDEX `created_at`" );
			}
			if ( in_array( 'mrrs_created_at', $indexes, true ) ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( "ALTER TABLE {$requests} DROP INDEX `mrrs_created_at`" );
			}
		}

		// v2.8.0: admin_note kolonu + performans indexleri.
		if ( version_compare( $from_version, '2.8.0', '<' ) ) {
			$has_note = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s',
					$requests,
					'admin_note'
				)
			);
			if ( ! $has_note ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( "ALTER TABLE {$requests} ADD COLUMN admin_note TEXT DEFAULT NULL AFTER description" );
			}

			// Composite index'ler.
			$indexes = $wpdb->get_col( "SHOW INDEX FROM {$requests}", 2 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( ! in_array( 'mrrs_status_votes', $indexes, true ) ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( "ALTER TABLE {$requests} ADD INDEX `mrrs_status_votes` (status, up_votes)" );
			}
			if ( ! in_array( 'mrrs_status_created', $indexes, true ) ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( "ALTER TABLE {$requests} ADD INDEX `mrrs_status_created` (status, created_at)" );
			}
		}
	}

	private function drop_legacy_tables(): void {
		global $wpdb;

		$legacy = array(
			$wpdb->prefix . 'mrrs_comments',
			$wpdb->prefix . 'mrrs_notifications',
			$wpdb->prefix . 'mrrs_request_status',
			$wpdb->prefix . 'mrrs_request_meta',
		);

		foreach ( $legacy as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}
	}

	public static function drop_all_tables(): void {
		global $wpdb;
		foreach ( Schema::get_table_names() as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}
	}
}