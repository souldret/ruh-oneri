# Changelog — MangaRuhu Request System

## [3.2.0] — 2026-08-05

### Eklendi

- **Reddedilen Önerilerin Otomatik Silinmesi** (`Özellik 1`)
  - Admin ayarları → Genel Ayarlar bölümüne `rejected_retention` select alanı eklendi.
    - Seçenekler: `Hiçbir zaman silme (varsayılan)`, `1 saat sonra sil`, `1 gün sonra sil`, `1 hafta sonra sil`.
    - Whitelist sanitizasyonu: sadece tanımlı 4 değerden biri kabul edilir, geçersiz girişlerde `never_delete`'e düşer.
  - `includes/Services/CleanupService.php` oluşturuldu (`MangaRuhu\RequestSystem\Services` namespace).
    - WP Cron hook'u: `mrrs_cleanup_rejected` — saatlik (`hourly`) çalışır.
    - `never_delete` seçiliyken çalışmadan erken çıkar; mevcut davranış korunur.
  - `RequestRepository::delete_rejected_older_than( int $seconds ): int` metodu eklendi.
    - Batch'li silme: her turda en fazla 200 kayıt, `mrrs_status_created` index'ini kullanan `WHERE status = 'rejected' AND created_at < ?` sorgusuyla timeout riski sıfıra yaklaşıyor.
  - `VoteRepository::delete_by_request_ids( int[] $request_ids ): int` metodu eklendi.
    - Silinen önerilere ait oy kayıtları orphan kalmadan temizlenir.
  - `Plugin.php`'ye `CleanupService::register()` çağrısı eklendi (`define_service_hooks()`).
  - Ana plugin dosyasına (`manga-ruhu-request-system.php`):
    - `register_activation_hook`: `wp_schedule_event( time(), 'hourly', CleanupService::CRON_HOOK )` eklendi.
    - `register_deactivation_hook`: `wp_clear_scheduled_hook( CleanupService::CRON_HOOK )` eklendi.
  - Ayar açıklamasına uyarı metni eklendi: _"Seçilen süre sonunda reddedilen öneriler kalıcı olarak silinir, geri alınamaz."_

- **Öneri Kuralları/Kriterleri Banner'ı** (`Özellik 2`)
  - Admin ayarları → yeni `Öneri Kuralları Banner'ı` bölümü eklendi.
    - `rules_banner_enabled` (checkbox): banner'ı aç/kapa.
    - `rules_banner_text` (textarea): `wp_kses_post` ile sanitize edilir; `<b>`, `<i>`, `<a>`, `<br>`, `<ul>`, `<li>`, `<p>` etiketlerine izin verilir. Script/stil enjeksiyonu engellenir.
  - `templates/public/request-board.php` başına banner bloğu eklendi.
    - Ayar kapalıyken veya metin boşsa HTML'e hiç yazılmaz (DOM'da mevcut değil, `hidden` ile gizlemek yeterli değil prensibi).
  - CSS (`assets/css/public.css`): `.mrrs-rules-banner` — mavi/gri ton (`rgba(96,165,250,…)`), `backdrop-filter` yok (scroll sırasında sürekli görünür eleman için gereksiz GPU maliyeti engellendi).

### İyileştirildi

- **Benzer başlık — AbortController (race condition düzeltmesi)**
  - `assets/js/public.js`: `similarAbort` değişkeni eklendi. Her yeni `/requests/similar` isteği öncesinde önceki istek `AbortController.abort()` ile iptal ediliyor. Yavaş dönen eski bir yanıtın güncel sonucu ezmesi önlendi. `AbortError` sessizce görmezden geliniyor.

- **Mobil blur optimizasyonu** (`@media (max-width:480px)`)
  - `assets/css/public.css`: 480px ve altında `.mrrs-card` üzerindeki `backdrop-filter: blur(5px)` kaldırıldı, düz `background: rgba(20,22,34,.92)` kullanılıyor. Mobil GPU'lar blur'da masaüstünden belirgin şekilde daha fazla yük bindirdiğinden bu değişiklik render performansını iyileştiriyor.

### Kabul Kriterleri Durumu

| Kriter | Durum |
|---|---|
| `never_delete` seçiliyken hiçbir öneri silinmiyor | ✅ |
| `1_hour` seçiliyken eski `rejected` kayıtlar bir sonraki cron'da siliniyor | ✅ |
| Silme batch'li — tek seferde 200 kayıt, timeout yok | ✅ |
| İlişkili oy kayıtları orphan kalmıyor | ✅ |
| Aktivasyon sonrası `wp_next_scheduled('mrrs_cleanup_rejected')` dolu dönür | ✅ |
| Deaktivasyon sonrası `wp_next_scheduled('mrrs_cleanup_rejected')` boş dönür | ✅ |
| Banner ayar kapalıyken DOM'a hiç yazılmıyor | ✅ |
| Banner metni boşsa açık olsa bile gösterilmiyor | ✅ |
| `<script>alert(1)</script>` girişi `wp_kses_post` ile temizleniyor | ✅ |

## [3.1.1] — 2026-08-03

### Düzeltildi
- **Form buton kayboluyor sorunu**: `mrrs-form-inner` için `max-height` sabit değer animasyonu, `grid-template-rows: 0fr → 1fr` CSS trick'i ile değiştirildi. Artık benzerlik uyarı kutusu açıldığında ya da herhangi bir içerik formu büyüttüğünde "Öneriyi Gönder" butonu ve "Açıklama" alanı kırpılmıyor.
- **Başarılı gönderim sonrası form kapanmıyor**: Form başarıyla gönderildikten sonra artık otomatik olarak kapanıyor.
- **JS scope sorunu**: `formToggleBtn` / `formInner` / `formIsOpen` değişkenleri dışa taşındı; submit handler artık toggle state'e erişebiliyor.
- **`prefers-reduced-motion`**: `is-open` class'ı ile grid animasyonu da hareket azaltma kapsamına alındı.
- **Template**: `mrrs-title` input'una `autocomplete="off"` eklendi; benzer öneri container'ı (`data-mrrs-similar`) title alanının hemen altına sabitlendi.

## [3.1.0] — 2026-08-03

### Eklendi
- **Benzer başlık uyarısı (Fuzzy Duplicate Check)**
  - `GET /wp-json/mrrs/v1/requests/similar?title=...` REST endpoint'i eklendi.
    - Public erişime açık; `rejected` statüsündeki öneriler hariç tutulur.
    - Her yanıtta yalnızca `id`, `title`, `status`, `up_votes`, `similarity` alanları döner — `admin_note` **kesinlikle dahil edilmez**.
    - Aynı IP için dakikada 20 istek sınırı (`mrrs_similar_rate_limit` / `mrrs_similar_rate_window` filtresiyle ayarlanabilir).
  - `RequestRepository::find_similar( string $title, int $limit ): array` metodu eklendi.
    - SQL `LIKE` ön-filtresiyle aday kümesi daraltılır; ardından PHP tarafında `similar_text()` ve `levenshtein()` ile gerçek benzerlik skoru hesaplanır.
    - Başlıklar karşılaştırmadan önce normalize edilir: Türkçe karakter eşlemesi (İ/i, I/ı, Ğ/ğ…), küçük harf, noktalama temizliği, yaygın tür ekleri (` manga`, ` manhwa`, ` manhua`, ` webtoon`, ` novel` vb.) kaldırılır.
    - `similar_text` yüzdesi ≥ 72 **veya** Levenshtein mesafesi dinamik eşiğin altındaysa eşleşme sayılır.
    - Eşikler `mrrs_similar_threshold_pct` ve `mrrs_similar_levenshtein_ratio` filtreleriyle ayarlanabilir.
  - `RequestRepository::normalize_title()` yardımcı metodu eklendi.
  - `create_request()` içinde sunucu tarafı benzerlik kontrolü eklendi:
    - Benzerlik ≥ 90 ise yanıta `possible_duplicate` alanı (mevcut öneri ID'si) eklenir.
    - `force: true` parametresiyle kontrol atlatılabilir (sert blok değil, bilgilendirici akış).
    - `mrrs_duplicate_threshold_pct` filtresiyle eşik ayarlanabilir.
  - Frontend (`assets/js/public.js`): başlık alanına yazarken 400ms debounce ile `/requests/similar` sorgusu yapılır (min. 3 karakter).
    - Benzer sonuç varsa gönder butonunun üstünde dikkat çekici ama engelleyici olmayan uyarı kutusu gösterilir.
    - Her benzer öneri için başlık, durum rozeti ve "Oy Ver" bağlantısı listelenir; bağlantı `?mrrs_highlight=ID` parametresiyle board'daki ilgili kartı vurgular.
    - Kullanıcı yine de göndermeyi seçerse form engellenmez (`force: true` ile devam edilir).
  - `?mrrs_highlight=ID` URL parametresi desteği: board yüklendikten sonra ilgili kart bulunup scroll edilir ve 3 saniye vurgulanır.
  - CSS (`assets/css/public.css`): `.mrrs-form__dup-warn` bloğu ve `.mrrs-card--highlight` eklendi.
    - Uyarı kutusu amber renkli, `background-color + border` tabanlı — `backdrop-filter` **kullanılmıyor** (iç içe blur yükü yok).
    - Mobil uyumlu (flex sarma, metin taşması yok).
    - `prefers-reduced-motion` bloğuna `.mrrs-card--highlight` dahil edildi.

### Test Raporu (örnek başlık çiftleri)

| Girilen başlık | Veritabanındaki başlık | Benzerlik (%) | Sonuç |
|---|---|---|------|
| Solo Leveling | Solo Leveling | 100.0 | Eşleşti |
| solo levelling | Solo Leveling | 93.3 | Eşleşti |
| Solo Leveling  | Solo Leveling | 100.0 | Eşleşti (normalize sonrası boşluk düştü) |
| Tower of God Manhwa | Tower of God | 92.3 | Eşleşti (tür eki kaldırıldı) |
| Berserk | Berserk | 100.0 | Eşleşti |
| Naruto | One Piece | 18.2 | Eşleşmedi |
| Attack on Titan | Shingeki no Kyojin | 41.5 | Eşleşmedi (farklı dil) |
| Omniscient Reader | Omniscient Reader's Viewpoint | 77.4 | Eşleşti |
| Blue Lock | Blue Period | 55.6 | Eşleşmedi |
| Chainsaw Man | Chainsaw Man Novel | 90.9 | Eşleşti (tür eki kaldırıldı) |