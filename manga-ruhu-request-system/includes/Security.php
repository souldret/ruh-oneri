<?php
/**
 * Shared security helpers.
 *
 * @package MangaRuhu\RequestSystem
 */

declare(strict_types=1);

namespace MangaRuhu\RequestSystem;

/**
 * Class Security
 *
 * Capability and nonce verification utilities.
 */
final class Security {

	/**
	 * Admin AJAX nonce action.
	 */
	public const ADMIN_NONCE = 'mrrs_admin_nonce';

	/**
	 * Whether current user can manage the plugin.
	 *
	 * @since 1.10.0
	 *
	 * @return bool
	 */
	public static function can_manage(): bool {
		return current_user_can( 'manage_mrrs' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Verify admin capability + nonce or die with JSON error.
	 *
	 * @since 1.10.0
	 *
	 * @param string $nonce_field Request field name for nonce.
	 */
	public static function require_admin_ajax( string $nonce_field = 'nonce' ): void {
		if ( ! self::can_manage() ) {
			wp_send_json_error(
				array( 'message' => __( 'Permission denied.', 'manga-ruhu-request-system' ) ),
				403
			);
		}

		$nonce = isset( $_REQUEST[ $nonce_field ] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			? sanitize_text_field( wp_unslash( (string) $_REQUEST[ $nonce_field ] ) )
			: '';

		if ( ! wp_verify_nonce( $nonce, self::ADMIN_NONCE ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Security check failed.', 'manga-ruhu-request-system' ) ),
				403
			);
		}
	}

	/**
	 * Escape HTML for attribute context.
	 *
	 * @since 1.10.0
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public static function esc_attr_value( mixed $value ): string {
		return esc_attr( (string) $value );
	}

	/**
	 * Sanitize a list of absolute integer IDs.
	 *
	 * @since 1.10.0
	 *
	 * @param mixed $ids Raw IDs.
	 * @return array<int, int>
	 */
	public static function sanitize_ids( mixed $ids ): array {
		if ( is_string( $ids ) ) {
			$ids = explode( ',', $ids );
		}
		if ( ! is_array( $ids ) ) {
			return array();
		}
		return array_values( array_filter( array_map( 'absint', $ids ) ) );
	}
}
