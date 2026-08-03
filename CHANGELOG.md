# Changelog — MangaRuhu Request System

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