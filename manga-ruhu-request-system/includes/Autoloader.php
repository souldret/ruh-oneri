<?php
/**
 * PSR-4 compatible autoloader for the plugin.
 *
 * @package MangaRuhu\RequestSystem
 */

declare(strict_types=1);

namespace MangaRuhu\RequestSystem;

/**
 * Class Autoloader
 *
 * Maps namespaces to directories and loads classes on demand.
 */
final class Autoloader {

	/**
	 * Registered namespace prefixes and their base directories.
	 *
	 * @var array<string, string>
	 */
	private static array $prefixes = array();

	/**
	 * Register the autoloader with SPL.
	 *
	 * @since 1.0.0
	 *
	 * @param string $prefix  Namespace prefix.
	 * @param string $base_dir Base directory for class files.
	 */
	public static function register( string $prefix, string $base_dir ): void {
		self::$prefixes[ $prefix ] = trailingslashit( $base_dir );

		// Map sub-namespaces to dedicated folders for clarity.
		self::$prefixes['MangaRuhu\\RequestSystem\\Admin\\']       = trailingslashit( $base_dir ) . 'admin/';
		self::$prefixes['MangaRuhu\\RequestSystem\\PublicFront\\'] = trailingslashit( $base_dir ) . 'public/';
		self::$prefixes['MangaRuhu\\RequestSystem\\Api\\']         = trailingslashit( $base_dir ) . 'api/';
		self::$prefixes['MangaRuhu\\RequestSystem\\Database\\']    = trailingslashit( $base_dir ) . 'database/';
		self::$prefixes['MangaRuhu\\RequestSystem\\UI\\']          = trailingslashit( $base_dir ) . 'includes/UI/';
		self::$prefixes['MangaRuhu\\RequestSystem\\Services\\']    = trailingslashit( $base_dir ) . 'includes/Services/';
		self::$prefixes['MangaRuhu\\RequestSystem\\Assets\\']      = trailingslashit( $base_dir ) . 'includes/Assets/';
		self::$prefixes['MangaRuhu\\RequestSystem\\Includes\\']    = trailingslashit( $base_dir ) . 'includes/';

		spl_autoload_register( array( self::class, 'autoload' ) );
	}

	/**
	 * Autoload callback.
	 *
	 * @since 1.0.0
	 *
	 * @param string $class Fully qualified class name.
	 */
	public static function autoload( string $class ): void {
		// Longest prefix first for correct sub-namespace resolution.
		$prefixes = self::$prefixes;
		uksort(
			$prefixes,
			static function ( string $a, string $b ): int {
				return strlen( $b ) <=> strlen( $a );
			}
		);

		foreach ( $prefixes as $prefix => $base_dir ) {
			$len = strlen( $prefix );

			if ( 0 !== strncmp( $prefix, $class, $len ) ) {
				continue;
			}

			$relative_class = substr( $class, $len );
			$file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

			if ( is_readable( $file ) ) {
				require_once $file;
				return;
			}
		}

		// Fallback: root namespace maps to includes/ for core classes.
		$root = 'MangaRuhu\\RequestSystem\\';
		if ( 0 === strncmp( $root, $class, strlen( $root ) ) ) {
			$relative = substr( $class, strlen( $root ) );

			// Skip already-handled sub-namespaces.
			if ( preg_match( '/^(Admin|PublicFront|Api|Database|UI|Services|Assets|Includes)\\\/', $relative ) ) {
				return;
			}

			$candidates = array(
				MRRS_PLUGIN_DIR . 'includes/' . str_replace( '\\', '/', $relative ) . '.php',
				MRRS_PLUGIN_DIR . str_replace( '\\', '/', $relative ) . '.php',
			);

			foreach ( $candidates as $file ) {
				if ( is_readable( $file ) ) {
					require_once $file;
					return;
				}
			}
		}
	}
}
