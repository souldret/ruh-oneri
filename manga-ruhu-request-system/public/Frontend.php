<?php
/**
 * Ön yüz bootstrap: shortcode, page template ve asset kaydı.
 *
 * @package MangaRuhu\RequestSystem\PublicFront
 */

declare(strict_types=1);

namespace MangaRuhu\RequestSystem\PublicFront;

use MangaRuhu\RequestSystem\Loader;

final class Frontend {

	private Loader $loader;

	public function __construct( Loader $loader ) {
		$this->loader = $loader;
	}

	public function register(): void {
		$this->loader->add_action( 'init',               $this, 'register_shortcodes' );
		$this->loader->add_action( 'wp_enqueue_scripts', $this, 'enqueue_assets' );
		$this->loader->add_filter( 'template_include',   $this, 'maybe_load_page_template' );
	}

	/* ── Shortcode'lar ── */

	public function register_shortcodes(): void {
		add_shortcode( 'mrrs_board', array( $this, 'render_board' ) );
		add_shortcode( 'mrrs_form',  array( $this, 'render_form' ) );
	}

	public function render_board( array $atts = array() ): string {
		ob_start();
		$tpl = MRRS_PLUGIN_DIR . 'templates/public/request-board.php';
		if ( file_exists( $tpl ) ) {
			include $tpl;
		}
		return (string) ob_get_clean();
	}

	public function render_form( array $atts = array() ): string {
		ob_start();
		$tpl = MRRS_PLUGIN_DIR . 'templates/public/request-form.php';
		if ( file_exists( $tpl ) ) {
			include $tpl;
		}
		return (string) ob_get_clean();
	}

	/* ── Sayfa template filtresi ── */

	/**
	 * Seçili sayfaysa plugin template'ini kullan.
	 */
	public function maybe_load_page_template( string $template ): string {
		if ( ! $this->is_mrrs_page() ) {
			return $template;
		}

		$plugin_tpl = MRRS_PLUGIN_DIR . 'templates/page-request-center.php';
		if ( file_exists( $plugin_tpl ) ) {
			return $plugin_tpl;
		}

		return $template;
	}

	/* ── Asset yükleme ── */

	public function enqueue_assets(): void {
		if ( ! $this->is_mrrs_page() && ! $this->page_has_shortcode() ) {
			return;
		}

		$v = MRRS_VERSION;

		wp_enqueue_style(
			'mrrs-public',
			MRRS_PLUGIN_URL . 'assets/css/public.css',
			array(),
			$v
		);

		wp_enqueue_script(
			'mrrs-public',
			MRRS_PLUGIN_URL . 'assets/js/public.js',
			array(),
			$v,
			true
		);

		wp_localize_script( 'mrrs-public', 'mrrsData', array(
			'api_url'  => rest_url( 'mrrs/v1' ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'per_page' => \MangaRuhu\RequestSystem\Admin\Settings::get_per_page(),
		) );

		// Ana renk, rozet renk ve banner renk özelleştirme.
		$main_colors   = \MangaRuhu\RequestSystem\Admin\Settings::get_main_colors();
		$badge_colors  = \MangaRuhu\RequestSystem\Admin\Settings::get_badge_colors();
		$banner_colors = \MangaRuhu\RequestSystem\Admin\Settings::get_rules_banner_colors();
		$this->maybe_inline_colors( $main_colors, $badge_colors, $banner_colors );
	}

	/**
	 * Özelleştirilmiş renkleri inline CSS olarak çıktıla.
	 *
	 * @param array<string,string> $main_colors
	 * @param array<string,string> $badge_colors
	 * @param array<string,string> $banner_colors
	 */
	private function maybe_inline_colors( array $main_colors, array $badge_colors, array $banner_colors = array() ): void {
		$lines = array();

		// Ana renk → CSS custom property eşleştirmesi
		$main_props = array(
			'color_accent'       => '--mrrs-accent',
			'color_accent_light' => '--mrrs-accent-light',
			'color_text'         => '--mrrs-text',
			'color_text_muted'   => '--mrrs-text-muted',
			'color_card_bg'      => '--mrrs-glass-card',
			'color_border'       => '--mrrs-border',
		);

		foreach ( $main_props as $key => $prop ) {
			$hex = $main_colors[ $key ] ?? '';
			if ( '' === $hex ) {
				continue;
			}
			$lines[] = "{$prop}:{$hex};";

			// accent için dim + glow otomatik türet
			if ( 'color_accent' === $key ) {
				$rgb     = $this->hex_to_rgb( $hex );
				$lines[] = "--mrrs-accent-dim:rgba({$rgb},.18);";
				$lines[] = "--mrrs-accent-glow:rgba({$rgb},.28);";
			}
			// card_bg için glass-card alpha ekle
			if ( 'color_card_bg' === $key ) {
				$rgb     = $this->hex_to_rgb( $hex );
				$lines[] = "--mrrs-glass-card:rgba({$rgb},.70);";
				$lines[] = "--mrrs-glass-input:rgba({$rgb},.30);";
			}
			// border için alpha ekle
			if ( 'color_border' === $key ) {
				$rgb     = $this->hex_to_rgb( $hex );
				$lines[] = "--mrrs-border:rgba({$rgb},.08);";
				$lines[] = "--mrrs-border-hover:rgba({$rgb},.15);";
			}
		}

		// Rozet renkleri
		$badge_props = array(
			'pending'     => array( '--mrrs-badge-pending-color',      '--mrrs-badge-pending-border',      '--mrrs-badge-pending-bg' ),
			'reviewing'   => array( '--mrrs-badge-reviewing-color',    '--mrrs-badge-reviewing-border',    '--mrrs-badge-reviewing-bg' ),
			'approved'    => array( '--mrrs-badge-approved-color',     '--mrrs-badge-approved-border',     '--mrrs-badge-approved-bg' ),
			'rejected'    => array( '--mrrs-badge-rejected-color',     '--mrrs-badge-rejected-border',     '--mrrs-badge-rejected-bg' ),
			'translating' => array( '--mrrs-badge-translating-color',  '--mrrs-badge-translating-border',  '--mrrs-badge-translating-bg' ),
		);

		foreach ( $badge_colors as $slug => $hex ) {
			if ( '' === $hex || ! isset( $badge_props[ $slug ] ) ) {
				continue;
			}
			$rgb = $this->hex_to_rgb( $hex );
			list( $prop_color, $prop_border, $prop_bg ) = $badge_props[ $slug ];
			$lines[] = "{$prop_color}:{$hex};";
			$lines[] = "{$prop_border}:rgba({$rgb},.40);";
			$lines[] = "{$prop_bg}:rgba({$rgb},.10);";
		}

		// Banner renkleri
		$hex_banner = $banner_colors['rules_banner_color'] ?? '';
		if ( '' !== $hex_banner ) {
			$rgb     = $this->hex_to_rgb( $hex_banner );
			$lines[] = "--mrrs-rules-accent:{$hex_banner};";
			$lines[] = "--mrrs-rules-bg:linear-gradient(135deg, rgba({$rgb},.18), rgba(20,22,34,.55));";
			$lines[] = "--mrrs-rules-icon-bg:rgba({$rgb},.18);";
			$lines[] = "--mrrs-rules-glow:rgba({$rgb},.28);";
		}
		$hex_text = $banner_colors['rules_banner_text_color'] ?? '';
		if ( '' !== $hex_text ) {
			$lines[] = "--mrrs-rules-text:{$hex_text};";
		}

		if ( ! empty( $lines ) ) {
			wp_add_inline_style( 'mrrs-public', ':root{' . implode( '', $lines ) . '}' );
		}
	}

	/**
	 * Hex rengi "r,g,b" string'e çevir.
	 */
	private function hex_to_rgb( string $hex ): string {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		return hexdec( substr( $hex, 0, 2 ) ) . ',' . hexdec( substr( $hex, 2, 2 ) ) . ',' . hexdec( substr( $hex, 4, 2 ) );
	}

	/* ── Yardımcılar ── */

	/**
	 * Mevcut sayfa, ayarlarda seçili "frontend page" mi?
	 */
	public function is_mrrs_page(): bool {
		$page_id = (int) get_option( 'mrrs_frontend_page_id', 0 );
		if ( $page_id <= 0 ) {
			return false;
		}

		// Sayfa silinmiş/yayında değilse false döndür.
		if ( 'publish' !== get_post_status( $page_id ) ) {
			return false;
		}

		return is_page( $page_id );
	}

	private function page_has_shortcode(): bool {
		global $post;
		if ( ! ( $post instanceof \WP_Post ) ) {
			return false;
		}
		return has_shortcode( $post->post_content, 'mrrs_board' )
			|| has_shortcode( $post->post_content, 'mrrs_form' );
	}
}