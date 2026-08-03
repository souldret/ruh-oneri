<?php
/**
 * Yeni öneri gönderme formu — toggle ile açılır/kapanır.
 *
 * @package MangaRuhu\RequestSystem
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="mrrs-form-wrap">
	<button
		type="button"
		class="mrrs-btn mrrs-btn--primary mrrs-form-toggle"
		data-mrrs-form-toggle
		aria-expanded="false"
		aria-controls="mrrs-form-inner"
	>
		<span class="mrrs-toggle-icon" aria-hidden="true">+</span>
		<?php esc_html_e( 'Yeni Seri Öner', 'manga-ruhu-request-system' ); ?>
	</button>

	<div class="mrrs-form-inner" id="mrrs-form-inner" aria-hidden="true">
		<div class="mrrs-form-inner__content">

			<div class="mrrs-form-inner__header">
				<span class="mrrs-form-inner__heading"><?php esc_html_e( 'Yeni Seri Öner', 'manga-ruhu-request-system' ); ?></span>
				<button
					type="button"
					class="mrrs-form-close-btn"
					data-mrrs-form-close
					aria-label="<?php esc_attr_e( 'Formu kapat', 'manga-ruhu-request-system' ); ?>"
				>&#10005;</button>
			</div>

			<div class="mrrs-form__notice" data-mrrs-form-notice hidden></div>

			<form class="mrrs-form" data-mrrs-form novalidate>
				<!-- Honeypot spam koruması -->
				<div style="display:none" aria-hidden="true">
					<input type="text" name="website" tabindex="-1" autocomplete="off">
				</div>

				<div class="mrrs-form__field">
					<label for="mrrs-title" class="mrrs-form__label">
						<?php esc_html_e( 'Seri Adı', 'manga-ruhu-request-system' ); ?>
						<span class="mrrs-required" aria-hidden="true">*</span>
					</label>
					<input
						type="text"
						id="mrrs-title"
						name="title"
						class="mrrs-form__input"
						required
						maxlength="255"
						autocomplete="off"
						placeholder="<?php esc_attr_e( 'Seri adını yazın…', 'manga-ruhu-request-system' ); ?>"
					>
					<div class="mrrs-similar-box" data-mrrs-similar hidden aria-live="polite" role="status"></div>
				</div>

				<div class="mrrs-form__field">
					<label for="mrrs-source" class="mrrs-form__label">
						<?php esc_html_e( 'Kaynak Link', 'manga-ruhu-request-system' ); ?>
						<span class="mrrs-optional"><?php esc_html_e( '(isteğe bağlı)', 'manga-ruhu-request-system' ); ?></span>
					</label>
					<input
						type="url"
						id="mrrs-source"
						name="source_link"
						class="mrrs-form__input"
						placeholder="https://..."
					>
				</div>

				<div class="mrrs-form__field">
					<label for="mrrs-description" class="mrrs-form__label">
						<?php esc_html_e( 'Açıklama', 'manga-ruhu-request-system' ); ?>
						<span class="mrrs-optional"><?php esc_html_e( '(isteğe bağlı)', 'manga-ruhu-request-system' ); ?></span>
					</label>
					<textarea
						id="mrrs-description"
						name="description"
						class="mrrs-form__textarea"
						rows="3"
						placeholder="<?php esc_attr_e( 'Seri hakkında kısa bir bilgi…', 'manga-ruhu-request-system' ); ?>"
					></textarea>
				</div>

				<button type="submit" class="mrrs-btn mrrs-btn--primary" data-mrrs-submit>
					<?php esc_html_e( 'Öneriyi Gönder', 'manga-ruhu-request-system' ); ?>
				</button>
			</form>
		</div>
	</div>
</div>