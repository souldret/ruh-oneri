<?php
/**
 * Eklenti kaldırıldığında çalışır.
 *
 * @package MangaRuhu\RequestSystem
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Tabloları sil.
$tables = array(
	$wpdb->prefix . 'mrrs_requests',
	$wpdb->prefix . 'mrrs_votes',
	// Eski sürüm tabloları.
	$wpdb->prefix . 'mrrs_comments',
	$wpdb->prefix . 'mrrs_notifications',
	$wpdb->prefix . 'mrrs_request_status',
	$wpdb->prefix . 'mrrs_request_meta',
);

foreach ( $tables as $table ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

// Seçenekleri sil.
delete_option( 'mrrs_options' );
delete_option( 'mrrs_db_version' );

// Transient'leri temizle.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	"DELETE FROM {$wpdb->options}
	WHERE option_name LIKE '_transient_mrrs_%'
	   OR option_name LIKE '_transient_timeout_mrrs_%'"
);