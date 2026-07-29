<?php
/**
 * Admin öneri yönetim sayfası.
 *
 * @package MangaRuhu\RequestSystem
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap mrrs-admin" id="mrrs-requests-page">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="mrrs-admin-notice" id="mrrs-notice" style="display:none"></div>

	<div class="tablenav top">
		<div class="alignleft actions">
			<select id="mrrs-filter-status">
				<option value=""><?php esc_html_e( 'Tüm Durumlar', 'manga-ruhu-request-system' ); ?></option>
				<option value="pending"><?php esc_html_e( 'Beklemede', 'manga-ruhu-request-system' ); ?></option>
				<option value="reviewing"><?php esc_html_e( 'İnceleniyor', 'manga-ruhu-request-system' ); ?></option>
				<option value="approved"><?php esc_html_e( 'Onaylandı', 'manga-ruhu-request-system' ); ?></option>
				<option value="rejected"><?php esc_html_e( 'Reddedildi', 'manga-ruhu-request-system' ); ?></option>
				<option value="translating"><?php esc_html_e( 'Çeviriye Alındı', 'manga-ruhu-request-system' ); ?></option>
			</select>
			<select id="mrrs-filter-sort">
				<option value="newest"><?php esc_html_e( 'En Yeni', 'manga-ruhu-request-system' ); ?></option>
				<option value="most_votes"><?php esc_html_e( 'En Çok Oy', 'manga-ruhu-request-system' ); ?></option>
				<option value="oldest"><?php esc_html_e( 'En Eski', 'manga-ruhu-request-system' ); ?></option>
			</select>
			<input type="search" id="mrrs-search" placeholder="<?php esc_attr_e( 'Ara…', 'manga-ruhu-request-system' ); ?>">
		</div>
		<div class="alignleft actions bulkactions">
			<select id="mrrs-bulk-action">
				<option value=""><?php esc_html_e( 'Toplu İşlem', 'manga-ruhu-request-system' ); ?></option>
				<option value="approved"><?php esc_html_e( 'Onayla', 'manga-ruhu-request-system' ); ?></option>
				<option value="reviewing"><?php esc_html_e( 'İncelemeye Al', 'manga-ruhu-request-system' ); ?></option>
				<option value="translating"><?php esc_html_e( 'Çeviriye Al', 'manga-ruhu-request-system' ); ?></option>
				<option value="rejected"><?php esc_html_e( 'Reddet', 'manga-ruhu-request-system' ); ?></option>
				<option value="pending"><?php esc_html_e( 'Bekleyene Al', 'manga-ruhu-request-system' ); ?></option>
				<option value="delete"><?php esc_html_e( 'Sil', 'manga-ruhu-request-system' ); ?></option>
			</select>
			<button type="button" class="button" id="mrrs-bulk-apply">
				<?php esc_html_e( 'Uygula', 'manga-ruhu-request-system' ); ?>
			</button>
		</div>
	</div>

	<table class="wp-list-table widefat fixed striped" id="mrrs-table">
		<thead>
			<tr>
				<th style="width:30px"><input type="checkbox" id="mrrs-check-all"></th>
				<th><?php esc_html_e( 'Seri Adı', 'manga-ruhu-request-system' ); ?></th>
				<th style="width:80px"><?php esc_html_e( 'Oy', 'manga-ruhu-request-system' ); ?></th>
				<th style="width:110px"><?php esc_html_e( 'Durum', 'manga-ruhu-request-system' ); ?></th>
				<th style="width:140px"><?php esc_html_e( 'Tarih', 'manga-ruhu-request-system' ); ?></th>
				<th style="width:160px"><?php esc_html_e( 'İşlemler', 'manga-ruhu-request-system' ); ?></th>
			</tr>
		</thead>
		<tbody id="mrrs-tbody">
			<tr><td colspan="6"><?php esc_html_e( 'Yükleniyor…', 'manga-ruhu-request-system' ); ?></td></tr>
		</tbody>
	</table>

	<div class="tablenav bottom">
		<div id="mrrs-pagination" class="tablenav-pages"></div>
	</div>

	<!-- Düzenleme modal -->
	<div id="mrrs-modal" style="display:none" class="mrrs-modal-overlay">
		<div class="mrrs-modal-box">
			<h2 id="mrrs-modal-title"><?php esc_html_e( 'Öneri Düzenle', 'manga-ruhu-request-system' ); ?></h2>
			<input type="hidden" id="mrrs-edit-id">
			<table class="form-table">
				<tr>
					<th><label for="mrrs-edit-title"><?php esc_html_e( 'Seri Adı *', 'manga-ruhu-request-system' ); ?></label></th>
					<td><input type="text" id="mrrs-edit-title" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="mrrs-edit-source"><?php esc_html_e( 'Kaynak Link', 'manga-ruhu-request-system' ); ?></label></th>
					<td><input type="url" id="mrrs-edit-source" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="mrrs-edit-desc"><?php esc_html_e( 'Açıklama', 'manga-ruhu-request-system' ); ?></label></th>
					<td><textarea id="mrrs-edit-desc" rows="4" class="large-text"></textarea></td>
				</tr>
				<tr>
				<th><label for="mrrs-edit-status"><?php esc_html_e( 'Durum', 'manga-ruhu-request-system' ); ?></label></th>
				<td>
					<select id="mrrs-edit-status">
						<option value="pending"><?php esc_html_e( 'Beklemede', 'manga-ruhu-request-system' ); ?></option>
						<option value="reviewing"><?php esc_html_e( 'İnceleniyor', 'manga-ruhu-request-system' ); ?></option>
						<option value="approved"><?php esc_html_e( 'Onaylandı', 'manga-ruhu-request-system' ); ?></option>
						<option value="rejected"><?php esc_html_e( 'Reddedildi', 'manga-ruhu-request-system' ); ?></option>
						<option value="translating"><?php esc_html_e( 'Çeviriye Alındı', 'manga-ruhu-request-system' ); ?></option>
					</select>
				</td>
			</tr>
			<tr id="mrrs-admin-note-row">
				<th><label for="mrrs-edit-admin-note"><?php esc_html_e( 'Admin Notu', 'manga-ruhu-request-system' ); ?></label></th>
				<td>
					<textarea id="mrrs-edit-admin-note" rows="3" class="large-text" placeholder="<?php esc_attr_e( 'Reddetme sebebi veya dahili not…', 'manga-ruhu-request-system' ); ?>"></textarea>
					<p class="description"><?php esc_html_e( 'Bu not sadece adminler tarafından görülür. Reddedildi durumunda otomatik gösterilir.', 'manga-ruhu-request-system' ); ?></p>
				</td>
			</tr>
		</table>
			<p class="submit">
				<button type="button" class="button button-primary" id="mrrs-modal-save">
					<?php esc_html_e( 'Kaydet', 'manga-ruhu-request-system' ); ?>
				</button>
				<button type="button" class="button" id="mrrs-modal-close">
					<?php esc_html_e( 'İptal', 'manga-ruhu-request-system' ); ?>
				</button>
			</p>
		</div>
	</div>
</div>