# VPS Kurulum (oc.colortr.com)

## 1) Dosyalari hazirla

Yerel klasor: `/Users/colortr/Downloads/aaa_fork/bitaxe-oc`

Istersen zip olustur:

```bash
cd /Users/colortr/Downloads/aaa_fork
zip -r bitaxe-oc_v61_backend.zip bitaxe-oc
```

## 2) Sunucuya yukle

1. Dosyalari sunucuda `/opt/oc` altina koy
2. PM2 ile `php -S 127.0.0.1:3001 -t /opt/oc` calistir
3. Nginx ile `oc.colortr.com` -> `127.0.0.1:3001` proxy et

URL:

- [https://oc.colortr.com](https://oc.colortr.com)

## 3) PHP surumu

- VPS icin **PHP 8.0+** (tercihen 8.3)

## 4) Dosya izinleri

- Dizinler: `755`
- Dosyalar: `644`
- `tmp` ve `storage` yazilabilir olmali (genelde 755 yeterli)

## 5) HTTPS

- SSL/TLS aktif olsun
- `Force HTTPS Redirect` acik olsun

## 6) Kritik config degisikligi

`/opt/oc/app/Config.php` icinde su alanlari degistir:

- `admin.username`
- `admin.password_hash` (onerilen)
- `logging.visitor_salt`
- `security.trust_proxy_headers` (Cloudflare/reverse proxy varsa true)
- `admin.access_key` (ops panel icin ikinci gizli katman, opsiyonel)
- `admin.allowed_ips` (ops panel IP/CIDR allowlist, opsiyonel)
- `limits.response_max_rows` (buyuk datasetlerde UI yanit boyutu limiti)
- `limits.collect_time_series` (gerekmiyorsa false tut)
- `logging.summary_cache_file` (ops panel ozet cache dosyasi)
- `logging.rotation` ve `logging.compress_archives` (disk ve panel performansi icin)
- `security.replay_window_sec` ve `security.replay_nonce_ttl_sec` (API replay korumasi)

DB backend notu:

- Bu yapida deploy VPS odaklidir (`/opt/oc`).
- DB baglanti parametreleri `app/Config.secret.php` ve/veya ortam degiskenlerinden okunur.
- Bu dosya varsa `Config.php` uzerine merge edilir ve `sharing.driver=db` aktif olur.
- Eski dosya-tabanli paylasim tokenlari `file_fallback_read=true` ile okunmaya devam eder.

Log DB notu:

- Ops panel loglarini MySQL'e almak icin `app/Config.secret.php` icinde:
  - `logging.driver = db`
  - `logging.db.*` (host, db, user, password, table=`usage_events`)
- Eski `storage/usage_logs*.ndjson` arsivini DB'ye tasimak icin:

```bash
php /opt/oc/scripts/migrate-usage-logs-to-db.php
```

Sifre hash uretimi:

```bash
php -r "echo password_hash('GUCLU_BIR_SIFRE', PASSWORD_DEFAULT), PHP_EOL;"
```

Hash kullaniyorsan `admin.password` alanini bos birakabilirsin.

## 7) Kontrol listesi

- Ana sayfa aciliyor mu?
- CSV secip "Analizi Baslat" dediginde sonuc geliyor mu?
- Tarayici network tab'inda `api/analyze.php` 200 donuyor mu?
- Gizli panel aciliyor mu? (`/ops-panel.php`)
- Panel sifresi calisiyor mu?
- 500 hata varsa `error_log` kontrol et

Not: API'yi harici script ile cagiriyorsan `csrf_token` yaninda
`request_ts` (unix saniye) ve `request_nonce` alanlarini da gonder.

## 8) Opsiyonel sertlestirme

- Nginx seviyesinde hotlink/abuse korumalari aktif edebilirsin
- WAF (Imunify360/ModSecurity) aktif olsun
- Sadece gerekli PHP extension'lari acik birak

## Not

Bu yapi DB ile de calisir. Paylasimlar `sharing.driver=db` ise MySQL uzerine yazilir, loglar `storage/usage_logs.ndjson` altinda tutulur.

## 9) Canli otomatik test (opsiyonel)

Yerel makineden Safari WebDriver ile canli URL'i otomatik test etmek icin:

```bash
cd /Users/colortr/Downloads/aaa_fork/bitaxe-oc
./scripts/run-live-test.sh https://oc.colortr.com/
```

Script raporu PASS/FAIL olarak terminale yazar.
