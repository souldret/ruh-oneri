# MangaRuhu — Seri Öneri Sistemi

WordPress için hafif, hızlı ve şık bir seri öneri eklentisi.  
Ziyaretçiler seri önerir, admin onaylar, herkes oy verir.

---

## Özellikler

- Seri adı, kaynak link ve açıklama ile öneri gönderme
- Admin onay sistemi — onaylanmadan yayınlanmaz
- 👍 Destekle / 👎 Desteklemiyorum oylama sistemi
- Kullanıcı başına bir oy (değiştirilebilir)
- Durum rozeti: Beklemede · İnceleniyor · Onaylandı · Reddedildi · Çeviriye Alındı
- Sıralama: En Çok Oy · En Yeni · En Eski
- Durum filtresi (pill navigasyon)
- Arama (highlight ile)
- AJAX sayfalama (sayfa yenilenmez)
- Glassmorphism tasarım dili
- Tam mobil uyumlu
- Renk özelleştirme (accent, badge renkleri)
- WordPress sayfa template sistemi — shortcode gerektirmez

---

## Gereksinimler

| Gereksinim | Minimum Versiyon |
|---|---|
| WordPress | 6.0+ |
| PHP | 8.0+ |
| MySQL | 5.7+ |

---

## Kurulum

1. Bu repoyu `.zip` olarak indirin
2. WordPress admin → **Eklentiler → Yeni Ekle → Eklenti Yükle**
3. ZIP dosyasını seçin ve yükleyin
4. Eklentiyi **Etkinleştir**

### Sayfa Oluşturma

1. **Sayfalar → Yeni Ekle** ile boş bir sayfa oluşturun
2. Sayfa template olarak **"MangaRuhu - Seri Önerileri"** seçin  
   *(Sayfa Özellikleri → Şablon)*
3. Sayfayı yayınlayın

### Ayarlar

**Seri Önerileri → Ayarlar** menüsünden:

- Frontend sayfasını seçin
- Sayfa başına öneri sayısını belirleyin (10 / 20 / 30 / 50)
- Misafir oy ve öneri izinlerini ayarlayın
- Accent rengi, metin renkleri, kart rengi özelleştirin
- Her durum rozetinin rengini özelleştirin

---

## Kullanım

### Sayfa Template (Önerilen)

Eklenti, seçilen sayfada otomatik olarak frontend'i render eder.  
Ekstra kod veya shortcode gerekmez.

### Shortcode (İsteğe Bağlı)

Öneri listesini ve formu ayrı sayfalara yerleştirmek için:

```
[mrrs_board]   — Onaylı öneri listesi
[mrrs_form]    — Yeni öneri gönderme formu
```

---

## Admin Paneli

**Seri Önerileri → Öneriler** sayfasından:

- Önerileri listeleyin ve filtreleyin
- Durum güncelleyin (tek tek veya toplu)
- Öneri düzenleyin veya silin
- Sayfalama ile büyük listelerde gezinin

---

## Veritabanı Yapısı

Eklenti 2 tablo oluşturur:

```
{prefix}_mrrs_requests  — Öneri kayıtları
{prefix}_mrrs_votes     — Oy kayıtları
```

Eklenti kaldırıldığında tablolar otomatik temizlenir (`uninstall.php`).

---

## Geliştirici Notları

```
manga-ruhu-request-system/
├── admin/              Admin panel (Ajax, Settings, Admin)
├── api/                REST API Controllers
├── assets/             JS & CSS
├── database/           Schema, Migrator, Repositories
├── includes/           Core (Plugin, Loader, Autoloader, Security, VoteService)
├── public/             Frontend.php
└── templates/          PHP template dosyaları
```

**REST API Endpointleri:**

```
GET  /wp-json/mrrs/v1/requests         — Öneri listesi
POST /wp-json/mrrs/v1/requests         — Yeni öneri gönder
POST /wp-json/mrrs/v1/requests/{id}/vote — Oy ver
GET  /wp-json/mrrs/v1/health           — Sistem durumu
```

---

## Ekran Görüntüleri

> Glassmorphism tasarım · Pill navigasyon filtreler · Kompakt kartlar · Toast bildirimleri

---

## Destek & Bağış

Bu eklenti **MangaRuhu** ekibi tarafından geliştirilmektedir.

Projeye destek olmak, geliştirmeye katkıda bulunmak veya sunucu masraflarına yardımcı olmak istiyorsanız bağış yapabilirsiniz:

**☕ [kreosus.com/mangaruhu](https://kreosus.com/mangaruhu/)**

Her türlü destek için teşekkürler!

---

## Lisans

[GPL v2 veya üzeri](https://www.gnu.org/licenses/gpl-2.0.html)

---

*MangaRuhu Seri Öneri Sistemi — v2.7.0*