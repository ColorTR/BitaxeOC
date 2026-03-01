# Bitaxe OC Refactor Plan (P0/P1)

Bu plan, davranis degisikligi yaratmadan kod tabanini bolerek daha guvenli gelistirme ve daha dusuk regresyon riski hedefler.

## P0 (Hemen)

### P0-1: API Bootstrap tekrarini merkezileştir
- Durum: Tamamlandi (v304)
- Kapsam:
  - `app/ApiBootstrap.php`:
    - `loadRuntimeContext()` eklendi
    - `assertPostAndSameOrigin()` eklendi
  - `api/analyze.php`, `api/share.php`, `api/log-usage.php`:
    - runtime/config/client context alma tekrarları kaldirildi
    - POST + same-origin guard tek yardimciya alindi
- Beklenen fayda:
  - 3 API endpointte tekrar eden bootstrap akisi tek noktaya indi
  - guvenlik guard davranisinda drift riski azaldi

### P0-2: Ops panel server status domain extraction
- Hedef:
  - `collectServerStatus`, DB metric sorgulari ve process cache akislarini `app/OpsServerStatusService.php` altina tasimak
  - `ops-panel.php` icinde sadece auth + routing + render kalmasi
- Durum:
  - v305: DB metric/sorgu katmani `app/OpsDbMetrics.php`'e tasindi ve panelde servis delegasyonu acildi.
  - v306: Process listesi toplama + cache katmani `app/OpsProcessMetrics.php`'e tasindi ve panelde `collectTopProcessMetrics*` servis delegasyonu acildi.
- Patch plan:
  1. Yeni servis dosyasi ekle (facade + mevcut fonksiyonlarin birebir tasinmasi)
  2. `ops-panel.php` icinde eski fonksiyon adlariyla servis delegasyonu yap
  3. Sonraki adimda eski global fonksiyonlari kaldir
- Risk:
  - DB size/CPU-RAM metrik regrese olabilir
- Acceptance:
  - `ops-panel.php?ajax=server_status` auth ile 200, anonimde 401
  - DB size panelde gorunur
  - process listesi dolu ve refresh stabil

### P0-3: ShareStore write/read yollarini test-first sabitle
- Hedef:
  - `createShareDb/getShareDb/getShareMetaDb` etrafinda regression testlerini guclendirmek
- Durum:
  - v307: DB driver + file fallback create/read/meta/dedupe/unknown-token unit testi eklendi; `getShareDb/getShareMetaDb` icindeki DB row fetch+expiry purge+touch akisinda ortak yardimci metodlara gecilerek query path sadelelestirildi.
- Patch plan:
  1. mevcut backend-unit fixture'larini DB fallback + dedupe senaryolariyla genislet
  2. davranis degismeden query path sadeleştir
- Acceptance:
  - Ayni payload/layout tekrarinda token reuse davranisi korunur
  - Bilinmeyen token fallback davranisi bozulmaz

## P1 (Sonraki Faz)

### P1-1: Security bileşenlerini parcala
- Hedef:
  - `Security.php` icindeki country lookup, transient store, rate limit, replay modullerini ayirmak
- Yol:
  - facade korunur (`Security::...` imzalari ayni kalir), alt implementasyon siniflara tasinir
- Acceptance:
  - API endpoint security davranislari birebir kalir

### P1-2: UsageLogger adapter ayrimi
- Hedef:
  - file/db path ayrimini adapter seviyesine indirmek (`UsageLoggerDbAdapter`, `UsageLoggerFileAdapter`)
- Acceptance:
  - summary/read/append sonuclari oncekiyle uyumlu
  - fallback event kaydi korunur

## Test Acceptance Kriterleri (Her Faz Sonu)

Asgari:
1. `scripts/php-lint.sh`
2. `scripts/backend-smoke.php`
3. `scripts/backend-unit.php`
4. `scripts/ops-panel-audit.js` (ops panel degisikliginde zorunlu)
5. `scripts/live-http-audit.js` (deploy oncesi)

Ek kalite kapisi:
- ilgili endpointler icin status code ve payload schema degismemeli
- `VERSION_LOG.md` tek-cumle surum notu guncellenmeli
- GitHub private repo (`ColorTR/BitaxeOC`) push zorunlu
