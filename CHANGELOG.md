# Changelog

MangaRuhu Seri Öneri Sistemi için tüm önemli değişiklikler bu dosyada belgelenmektedir.

Format: [Keep a Changelog](https://keepachangelog.com/tr/1.0.0/)  
Versiyonlama: [Semantic Versioning](https://semver.org/)

---

## [2.8.1] — 2026-07-29

### Düzeltmeler
- `RequestRepository::to_array()` içinde `admin_note` alanı API yanıtına eklenmemişti — reddedilen önerilerde red sebebi kartlarda görünmüyordu
- `public.js`'de `&&` operatörü HTML entity olarak encode edilmişti — `buildCard` admin notu koşulu hiç çalışmıyordu
- `RequestRepository::update()` switch-case'ine `admin_note` için `sanitize_textarea_field` case'i eklendi (önceden `sanitize_text_field` ile çok satırlı metin kesiliyordu)

---

## [2.8.0] — 2026-07-29

### Eklenenler
- **Admin Notu:** Reddetme kararlarında admin not girebilme özelliği
  - Modal'da "Admin Notu" textarea'sı — yalnızca `Reddedildi` durumu seçildiğinde görünür
  - `admin_note` kolonu veritabanına eklendi
  - v2.8.0 Migrator migration bloğu (mevcut kurulumlar otomatik güncellenir)
- **Performans Optimizasyonu:**
  - Composite DB index'leri: `status+up_votes`, `status+created_at`
  - `prime_user_cache()` ile N+1 DB sorgusu önlendi (toplu kullanıcı cache)
  - REST API yanıtlarına `Cache-Control` header eklendi (misafir: 60sn, üye: no-store)
- **Admin Panel:** "Reddet" butonu artık modal açıyor — not girilebiliyor
- **Frontend:** Reddedilen kartlarda kırmızı "Red sebebi" bloğu gösteriliyor

### Değişiklikler
- `RequestRepository::update()` `allowed` listesine `admin_note` eklendi
- `RequestController::list()` artık `prime_user_cache()` çağırıyor

---

## [2.7.0] — 2026-07-28

### Eklenenler
- **Admin Panel Sayfalama:** Ellipsis destekli `◀ 1 2 3 … 12 ▶` stili
- **Form Kapatma Butonu:** Öneri formunun sağ üst köşesine `✕` butonu eklendi
- **Toplam Öneri Sayacı:** Header'da mor badge ile anlık güncelleme
- **Türkçe Düzeltmeleri:** `11 öneri`, `1–20 / 186 öneri gösteriliyor`, `Oyunuz güncellendi`

### Düzeltmeler
- Submit buton `SUBMIT_ORIG_HTML` ile DOM'dan orijinal label restore ediliyor — buton kaybolma sorunu çözüldü
- `goToPage is not defined` hatası — `buildPagination` artık callback parametre alıyor
- Plugin versiyon header tutarsızlığı düzeltildi (2.0.0 → 2.7.0)
- `Autoloader.php`'den kullanılmayan `Providers` namespace kaldırıldı

---

## [2.6.0] — 2026-07-27

### Eklenenler
- **AJAX Sayfalama:** SQL LIMIT/OFFSET tabanlı, URL state korumalı (`?mrrs_page=3`)
- **Skeleton Kartlar:** Yükleme sırasında shimmer animasyonlu placeholder'lar
- **Pill Filtre Bar:** Sıralama ve durum için pill navigasyon (glassmorphism stil)
- **Arama Highlight:** Aranan kelime sarı `<mark>` ile işaretleniyor
- **"Sonuç bulunamadı"** yalnızca arama bitince gösteriliyor

### Düzeltmeler
- Admin panel `page` parametresi WordPress admin URL ile çakışıyordu → `mrrs_page` olarak düzeltildi
- `status=all` filtresi "Sonuç bulunamadı" hatası veriyor → `'all' !== $status` bypass ile düzeltildi
- Loading spinner takılı kalma sorunu → `finally` bloğu ile kesin çözüm

---

## [2.5.0] — 2026-07-26

### Eklenenler
- **Glassmorphism UI:** `backdrop-filter: blur(10px)`, yarı-şeffaf kart arka planları, ince border'lar
- **Status Badge Yenileme:** Lucide inline SVG ikonları, renkli glow efekti, pill stili
- **Toast Bildirimleri:** `✓ Oyunuz kaydedildi`, `✓ Öneriniz gönderildi` — sağ altta 3.2sn
- **Renk Özelleştirme:** Accent rengi, metin renkleri, kart arka planı, her durum rozeti rengi

### Değişiklikler
- Badge pozisyonu: başlık solda, badge sağda (`space-between`)
- Öneren bilgisi: `Öneren: <isim>` kartlarda gösteriliyor

---

## [2.4.0] — 2026-07-25

### Eklenenler
- **Up/Down Oylama:** 👍 Destekle / 👎 Desteklemiyorum — kullanıcı başına 1 oy, değiştirilebilir
- **`vote_type` + `up_votes` + `down_votes`** kolonları veritabanına eklendi
- **Katlanabilir Form:** "+ Yeni Seri Öner" butonu ile `max-height` + `opacity` animasyonu (200ms)
- **Admin Panel Durum Güncelleme:** İnceleniyor + Çeviriye Alındı durumları eklendi

### Düzeltmeler
- `VoteService`: sadece `approved` değil, `reviewing` ve `translating` durumları da oylanabilir
- `AdminPanel::get_hook_ids()` dinamik page hook ile enqueue sorunu düzeltildi

---

## [2.0.0] — 2026-07-24

### İlk Yayın
- WordPress REST API tabanlı öneri sistemi
- Admin onay sistemi (beklemede / onaylandı / reddedildi)
- Misafir oy desteği (fingerprint tabanlı)
- WordPress sayfa template sistemi
- Honeypot spam koruması
- Plugin ayarlar sayfası (sayfa başına öneri, misafir izinleri)