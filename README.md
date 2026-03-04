# BitAxe OC - oc.colortr.com Ready

Bu paket tek HTML mimarisini backend destekli yapiya tasir:

- Frontend: `/index.php` + `/assets/*`
- Backend API: `/api/analyze.php`
- Share API: `/api/share.php` (POST=create, GET=read)
- Autotune Import API:
  - `/api/autotune/import.php` (POST=create importId, OPTIONS=preflight)
  - `/api/autotune/consume.php` (GET/POST=consume importId, one-time)
  - `/import/<importId>` (auto-import landing route)
- Core analiz mantigi: `/app/Analyzer.php` (tarayiciya acik degil)
- Share depolama/limit: `/app/ShareStore.php` (`file` veya `db` backend)
- Autotune import depolama/limit: `/app/AutotuneImportStore.php` (`file` veya `db` backend)
- Upload loglama: `/app/UsageLogger.php`
- Gizli admin panel: `/ops-panel.php`
- Guvenlik: CSRF, replay korumasi (timestamp+nonce), same-origin kontrolu, rate limit, upload limitleri, `.htaccess` hardening

## Neden daha guvenli?

- Asil CSV parse / merge / score algoritmasi frontend JavaScript'ten backend PHP'ye tasindi.
- Kullanici sadece sonuc verisini gorur; cekirdek mantik sunucuda kalir.
- `app/`, `storage/`, `tmp/` dizinlerine web erisimi kapatilmistir.

## Gizli panel ne gosterir?

- Toplam kac run calisti
- Tahmini tekil ziyaretci sayisi (hashlenmis)
- Kac dosya yuklenmis / islenmis
- Kac MB yukleme yapilmis (toplam + ortalama)
- Kac satir yuklenmis / parse edilmis
- P95 analiz suresi, hata orani, en buyuk upload
- Her run icin detayli satir logu

## Gereksinimler

- PHP 8.5+
- Apache + `.htaccess` aktif
- HTTPS (onerilir, uretimde zorunlu)

## Deploy Notu (Mevcut Workspace Yapisi)

Bu workspace app-odakli (`bitaxe-oc`) duzende tutuluyor.
Root altindaki eski deploy scriptleri (`/scripts/deploy_*.sh`) olmayabilir.

`./scripts/deploy-now.sh` su sekilde calisir:
- Root deploy wrapper scripti varsa onu cagirir
- Yoksa acik bir hata mesaji vererek durur

Aktif/guvenli release akisinda deploy islemleri VPS tarafinda (`/opt/oc`) yurutulmelidir.

## Share backend (DB)

- `app/Config.php` varsayilaninda `sharing.driver=file` gelir.
- DB baglanti ayarlari `app/Config.secret.php` ve/veya ortam degiskenleri ile verilir.
- `Config.secret.php` varsa `Config.php` uzerine otomatik merge edilir.
- DB modunda yeni paylasimlar veritabanina yazilir.
- `file_fallback_read=true` sayesinde eski `storage/shares` tokenlari okunmaya devam eder.

## Ops panel log backend (DB)

- `logging.driver=db` ayarlandiginda ops panel loglari `usage_events` tablosuna yazilir.
- Onerilen: share DB ile ayni MySQL instance kullan, ayri tablo (`usage_events`).
- Dil/tema/timezone metrikleri bu tabloda tutulur ve ops panelde raporlanir.
- Eski file loglari tasimak icin:

```bash
php /opt/oc/scripts/migrate-usage-logs-to-db.php
```

- DB yazimi aktifken file loguna geri dusus sadece `logging.file_fallback_read=true` ise olur.

## Varsayilan limitler

`app/Config.php`:

- Max dosya: `30`
- Tek dosya boyutu: `350 KB`
- Toplam upload boyutu: `6 MB`
- Dosya basi parse satir limiti: `7000`
- Parse time budget: `8 sn`
- API response max row: `8000` (buyuk birlesik datada UI performansi icin)
- `response_include_file_rows`: `false` (varsayilan, payload'i ciddi kucultur)
- `collect_time_series`: `false` (UI kullanmiyorsa kapali tutmak daha hizli)
- `logging.summary_cache_file`: ops panel genel ozeti cache dosyasi (buyuk log arsivinde paneli hizlandirir)
- `logging.rotation`: `daily` (arsiv dosyalari gunluk döner)
- `logging.compress_archives`: `true` (`.gz` arsiv ile disk kullanimini azaltir)

Not: API'yi custom istemci ile cagiranlar `csrf_token` ile birlikte
`request_ts` (unix saniye) ve tek-kullanimlik `request_nonce` da gondermelidir.

Ihtiyac halinde bu limitleri `app/Config.php` dosyasindan degistirebilirsin.

## Kritik ayarlar (deploy sonrasi degistir)

`app/Config.php`:

- `admin.username`
- `admin.password_hash` (onerilen)
- `logging.visitor_salt`
- `security.trust_proxy_headers` (reverse proxy varsa true, yoksa false)
- `security.transient_store` (`file` veya `db`) ve `security.db.*` (rate-limit/replay state backend)
- `sharing.driver` ve `sharing.db.*` (`Config.secret.php` uzerinden)

Env override (onerilen, source icine secret koymadan):

- `BITAXE_CONFIG_FILE` (harici PHP config dosyasi; app dizini disinda tutulabilir)
- `BITAXE_DB_*` (global DB ayarlari: `ENGINE/HOST/PORT/NAME/USER/PASSWORD/DSN/CHARSET`)
- `BITAXE_SHARING_DB_*`, `BITAXE_LOGGING_DB_*`, `BITAXE_SECURITY_DB_*` (servis-bazli DB override)
- `BITAXE_IMPORT_DB_*` (autotune import DB override)
- `BITAXE_SHARING_DRIVER`, `BITAXE_LOGGING_DRIVER`, `BITAXE_SECURITY_TRANSIENT_STORE`
- `BITAXE_IMPORT_DRIVER`, `BITAXE_IMPORT_ALLOWED_ORIGINS`, `BITAXE_IMPORT_ALLOW_ANY_ORIGIN`
- `BITAXE_LOG_VISITOR_SALT` / `BITAXE_VISITOR_SALT`
- `BITAXE_ADMIN_USERNAME`, `BITAXE_ADMIN_PASSWORD_HASH`, `BITAXE_ADMIN_ACCESS_KEY`, `BITAXE_ADMIN_ALLOWED_IPS`

Opsiyonel ek sertlestirme:

- `admin.access_key`: ops panel URL'ine ikinci gizli anahtar katmani ekler (`/ops-panel.php?k=...`)
- `admin.allowed_ips`: ops panele IP/CIDR allowlist uygular
- `admin.session_timeout_sec`: panel idle timeout

Plain-text sifre kullanmak yerine `admin.password_hash` kullan:

```bash
php -r "echo password_hash('GUCLU_BIR_SIFRE', PASSWORD_DEFAULT), PHP_EOL;"
```

Uretimde `admin.password` degerini bos birakip sadece hash ile ilerle.

## Canli E2E Test (Safari)

Canli URL'i tek komutla otomatik test etmek icin script:

- `/scripts/live-e2e-safari.js`
- `/scripts/run-live-test.sh`

Hazirlik:

```bash
safaridriver --enable
safaridriver -p 4444
```

Calistirma:

```bash
cd /Users/colortr/Downloads/aaa_fork/bitaxe-oc
./scripts/run-live-test.sh
```

Opsiyonel hedef URL:

```bash
./scripts/run-live-test.sh https://oc.colortr.com/
```

Opsiyonel WebDriver endpoint:

```bash
SAFARI_WEBDRIVER_URL=http://127.0.0.1:4444 ./scripts/run-live-test.sh
```

Not: `run-live-test.sh` WebDriver ayakta degilse `:4444` icin otomatik baslatmayi dener.

## Master Test Paketi (Arada Bir Tam Kontrol)

Tum kritik kontrolleri tek komutta calistirir:

- Backend Unit (core guard + parser + dedupe + logger akisi)
- HTTP Audit (SEO + performance + security + API negatif senaryolar)
- Live E2E (UI + security + share API DB path + dedupe + ETag 304)
- Live Bench (max limit / overflow / truncation / upload behavior)

Guncel kapsam (master test): 129 kontrol

- HTTP Audit: 78
- Live E2E: 41
- Live Bench: 10

Script:

- `/scripts/run-master-test.sh`

Calistirma:

```bash
cd /Users/colortr/Downloads/aaa_fork/bitaxe-oc
./scripts/run-master-test.sh
```

Opsiyonel hedef URL + bench dizini:

```bash
./scripts/run-master-test.sh https://oc.colortr.com/ /Users/colortr/Downloads/aaa_fork/bitaxe-oc/bench
```
