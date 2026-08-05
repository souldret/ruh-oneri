<?php
/**
 * Herkese açık seri öneri listesi — pill nav filtreli.
 *
 * @package MangaRuhu\RequestSystem
 * @var array $atts Shortcode nitelikleri.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

<?php
$mrrs_banner_enabled = \MangaRuhu\RequestSystem\Admin\Settings::get_option( 'rules_banner_enabled', false );
$mrrs_banner_text    = (string) \MangaRuhu\RequestSystem\Admin\Settings::get_option( 'rules_banner_text', '' );
?>
<div class="mrrs-board" id="mrrs-board" data-mrrs-board>

	<?php if ( $mrrs_banner_enabled && '' !== trim( wp_strip_all_tags( $mrrs_banner_text ) ) ) : ?>
	<div class="mrrs-rules-banner" data-mrrs-rules>
		<div class="mrrs-rules-banner__icon" aria-hidden="true">
			<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
				<path d="M3 11l18-5v12L3 13v-2z"></path>
				<path d="M11.6 16.8a2 2 0 1 1-3.2 2.4"></path>
			</svg>
		</div>
		<div class="mrrs-rules-banner__body">
			<p class="mrrs-rules-banner__title"><?php esc_html_e( 'Öneri Göndermeden Önce', 'manga-ruhu-request-system' ); ?></p>
			<div class="mrrs-rules-banner__text">
				<?php echo wp_kses_post( wpautop( $mrrs_banner_text ) ); ?>
			</div>
		</div>
		<button type="button" class="mrrs-rules-banner__close" data-mrrs-rules-close aria-label="<?php esc_attr_e( 'Kapat', 'manga-ruhu-request-system' ); ?>">
			<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
		</button>
	</div>
	<?php endif; ?>

	<div class="mrrs-board__header">
		<div class="mrrs-board__header-top">
			<div>
				<h2 class="mrrs-board__title"><?php esc_html_e( 'Seri Önerileri', 'manga-ruhu-request-system' ); ?></h2>
				<p class="mrrs-board__subtitle"><?php esc_html_e( 'Önerileri destekleyin veya yeni bir öneri gönderin.', 'manga-ruhu-request-system' ); ?></p>
			</div>
			<span class="mrrs-total-badge" data-mrrs-total hidden></span>
		</div>
	</div>

	<div class="mrrs-board__toolbar">
		<input
			type="search"
			class="mrrs-search-input"
			data-mrrs-search
			placeholder="<?php esc_attr_e( 'Seri ara…', 'manga-ruhu-request-system' ); ?>"
			autocomplete="off"
		>
	</div>

	<!-- Sıralama pill'leri -->
	<div class="mrrs-pill-group mrrs-pill-group--sort" role="group" aria-label="<?php esc_attr_e( 'Sıralama', 'manga-ruhu-request-system' ); ?>">
		<button type="button" class="mrrs-pill is-active" data-mrrs-sort-pill="most_votes">
			<?php esc_html_e( 'En Çok Oy', 'manga-ruhu-request-system' ); ?>
		</button>
		<button type="button" class="mrrs-pill" data-mrrs-sort-pill="newest">
			<?php esc_html_e( 'En Yeni', 'manga-ruhu-request-system' ); ?>
		</button>
		<button type="button" class="mrrs-pill" data-mrrs-sort-pill="oldest">
			<?php esc_html_e( 'En Eski', 'manga-ruhu-request-system' ); ?>
		</button>
	</div>

	<!-- Durum filtre pill'leri -->
	<div class="mrrs-pill-group mrrs-pill-group--status" role="group" aria-label="<?php esc_attr_e( 'Durum Filtresi', 'manga-ruhu-request-system' ); ?>">
		<button type="button" class="mrrs-pill is-active" data-mrrs-status-pill="all">
			<?php esc_html_e( 'Tümü', 'manga-ruhu-request-system' ); ?>
		</button>
		<button type="button" class="mrrs-pill" data-mrrs-status-pill="pending">
			<?php esc_html_e( 'Beklemede', 'manga-ruhu-request-system' ); ?>
		</button>
		<button type="button" class="mrrs-pill" data-mrrs-status-pill="reviewing">
			<?php esc_html_e( 'İnceleniyor', 'manga-ruhu-request-system' ); ?>
		</button>
		<button type="button" class="mrrs-pill" data-mrrs-status-pill="approved">
			<?php esc_html_e( 'Onaylandı', 'manga-ruhu-request-system' ); ?>
		</button>
		<button type="button" class="mrrs-pill" data-mrrs-status-pill="rejected">
			<?php esc_html_e( 'Reddedildi', 'manga-ruhu-request-system' ); ?>
		</button>
		<button type="button" class="mrrs-pill" data-mrrs-status-pill="translating">
			<?php esc_html_e( 'Çeviriye Alındı', 'manga-ruhu-request-system' ); ?>
		</button>
	</div>

	<div class="mrrs-board__list" data-mrrs-list role="list"></div>

	<div class="mrrs-board__empty" data-mrrs-empty hidden>
		<p><?php esc_html_e( 'Sonuç bulunamadı.', 'manga-ruhu-request-system' ); ?></p>
	</div>

	<div class="mrrs-board__loader" data-mrrs-loader hidden>
		<div class="mrrs-skeleton-list" aria-hidden="true">
			<div class="mrrs-skeleton-card"></div>
			<div class="mrrs-skeleton-card"></div>
			<div class="mrrs-skeleton-card"></div>
			<div class="mrrs-skeleton-card"></div>
			<div class="mrrs-skeleton-card"></div>
		</div>
	</div>

	<!-- Pagination bölümü -->
	<div class="mrrs-pagination-wrap" data-mrrs-pagination hidden>
		<p class="mrrs-page-info" data-mrrs-page-info></p>
		<nav class="mrrs-pagination" aria-label="<?php esc_attr_e( 'Sayfa navigasyonu', 'manga-ruhu-request-system' ); ?>" data-mrrs-pager>
		</nav>
	</div>

</div>