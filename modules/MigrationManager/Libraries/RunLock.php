<?php

declare(strict_types=1);

namespace Modules\MigrationManager\Libraries;

/**
 * Migration/seed çalıştırma kilidi.
 *
 * `flock(LOCK_EX|LOCK_NB)` tabanlıdır: kilit işletim sistemi tarafından
 * kilidi tutan **open file description**'a bağlanır — süreç fatal hata,
 * `SIGKILL` veya timeout ile ölse bile çekirdek kilidi OTOMATİK bırakır.
 * `fclose()` çağrıldığı an da flock anında serbest kalır; bu yüzden kilit
 * tutulduğu sürece dosya tanıtıcısı `release()` çağrılana kadar AÇIK
 * tutulmalıdır (bkz. `$handle`).
 *
 * Tarihçe: bu sınıf başlangıçta `modules/Settings/Libraries/UpdateService.php:871-893`
 * (`acquireLock()`/`releaseLock()`) deseninin birebir kopyasıydı —
 * `fopen(..., 'xb')` + dosya mtime'ına dayalı TTL ile "bayat" kilidi temizleyen
 * bir tasarım. O tasarımda heartbeat yoktu (TTL'yi aşan uzun bir koşumda başka
 * bir süreç kilidi bayat sanıp silebiliyordu) ve `unlink()` + `fopen('xb')`
 * arasında bir TOCTOU penceresi vardı. Bu sınıf `flock()`'a geçirilerek her
 * iki sorun da yapısal olarak ortadan kaldırıldı — TTL/heartbeat kavramı
 * `flock()` ile birlikte gereksizleşir, çünkü kilidin sahipliği artık bir
 * dosya içeriğine/zaman damgasına değil, çekirdeğin kendi izlediği open file
 * description'a bağlıdır. `UpdateService::acquireLock()`/`releaseLock()`
 * AYNI TTL/TOCTOU kusurunu hâlâ taşıyor — bu bilinçli olarak bu görevin
 * kapsamı dışında bırakıldı, ayrı bir kullanıcı kararı gerektirir.
 *
 * `app/Config/Migrations.php`'deki `$lock` bayrağının yerini TUTMAZ (o bayrak
 * kasıtlı olarak kapalı bırakılmıştır) — bu sınıf yalnızca `MigrationManager`
 * modülünün kendi çalıştırma uçları (ve `Modules\Backend\Commands\Ci4msMigrate`
 * CLI komutu) için uygulama seviyesinde eşzamanlılık kilididir.
 *
 * Platform kısıtı: `flock()` NFS gibi ağ dosya sistemlerinde GÜVENİLİR
 * DEĞİLDİR. Kilit dosyası `WRITEPATH` altında (`writable/locks/`) yerel disk
 * varsayımıyla tutulur; bu varsayım kırılırsa (`WRITEPATH` bir NFS mount'una
 * taşınırsa) bu sınıfın dışlama garantisi geçersizleşir.
 */
class RunLock
{
    private string $lockFile;

    /**
     * Kilit tutulurken açık kalan dosya tanıtıcısı.
     *
     * `flock()` bir open file description'a bağlıdır — bu handle
     * `release()` çağrılana (veya sürecin kendisi ölene) kadar AÇIK
     * kalmalıdır. `fclose()` çağrıldığı an flock anında serbest kaldığı
     * için bu alan yalnızca `release()` içinde kapatılır; `acquire()`
     * içinde asla `fclose()` edilmez (başarısız flock denemesi hariç).
     *
     * @var resource|null
     */
    private mixed $handle = null;

    /**
     * Bu örneğin kilidi GERÇEKTEN tuttuğunu izler. Yalnız `acquire()`
     * kilidi gerçekten kazandığında (`true` döndüğünde) `true` olur;
     * `release()` yalnız bu bayrak `true` iken `$handle`'ı kapatır ve
     * ardından `false`'a döner — böylece aynı örnek üzerindeki ikinci bir
     * `release()` çağrısı, zaten kapalı bir handle'da tekrar `fclose()`
     * çağırıp PHP `E_WARNING`'i tetiklemek yerine no-op olur.
     */
    private bool $held = false;

    /**
     * @param string|null $lockFile Kilit dosyasının tam yolu. `null` ise
     *                              `WRITEPATH.'locks/migration_manager.lock'`
     *                              kullanılır (asla `public/` altında değil).
     *
     * @throws \RuntimeException Kilit dizini oluşturulamıyorsa, yazılabilir
     *                            değilse veya bir sembolik bağsa (bkz.
     *                            `ensureDirectory()`).
     */
    public function __construct(?string $lockFile = null)
    {
        $this->lockFile = $lockFile ?? WRITEPATH . 'locks/migration_manager.lock';

        $this->ensureDirectory(dirname($this->lockFile));
    }

    /**
     * Çalıştırma kilidini `flock(LOCK_EX|LOCK_NB)` ile atomik olarak alır ve
     * bu örneği kilidin sahibi olarak işaretler.
     *
     * Kilit dosyası `'c'` modunda açılır (create-or-open, TRUNCATE ETMEZ) —
     * `'w'` veya `'xb'` DEĞİL: `'w'` her açılışta dosyayı sıfırlardı, bu da
     * flock'u ALAMAYAN bir sürecin bile (`fopen()` başarılı olur, yalnızca
     * sonraki `flock()` başarısız olur) kilit sahibinin yazdığı teşhis
     * içeriğini silmesine yol açardı. Bu yüzden teşhis içeriği (pid + zaman
     * damgası) yalnızca lock BAŞARIYLA alındıktan SONRA `ftruncate()` +
     * `fwrite()` ile yazılır, açılış anında değil. Bu içerik tamamen
     * opsiyoneldir ve insan operasyonu için `cat writable/locks/...` ile
     * okunabilir olması amaçlanır — bu sınıfın hiçbir mantık dalı bu içeriği
     * geri OKUMAZ.
     *
     * Sembolik bağ koruması (`'c'` modu eski `'xb'`'nin aksine sembolik bağı
     * TAKİP EDER — `O_CREAT|O_EXCL` yok): `fopen()`'dan ÖNCE `is_link()` ile
     * hızlı-başarısız (fail-fast) bir kontrol yapılır — bu yalnızca gereksiz
     * bir dosya tanıtıcısı açmaktan kaçınmak içindir, TEK BAŞINA TOCTOU'ya
     * (kontrol ile `fopen()` arasında yol değişebilir) KARŞI YETERLİ
     * DEĞİLDİR. Asıl garanti `fopen()` SONRASI gelir: `fstat($handle)`
     * (`fopen()`'ın GERÇEKTEN açtığı, sembolik bağı takip eden inode)
     * `lstat($this->lockFile)` (yolun son bileşenini TAKİP ETMEYEN, o an
     * üzerindeki inode) ile karşılaştırılır. Düz bir dosyada ikisi HER ZAMAN
     * aynı `dev`+`ino` çiftini verir; yol bir sembolik bağSA (veya kontrol
     * ile `fopen()` arasında bağa DÖNÜŞTÜYSE) farklılaşır. Eşleşmezse handle
     * hemen kapatılır ve `flock()`'a/`ftruncate()`'e/`fwrite()`'a HİÇ
     * ULAŞILMADAN `false` döner — böylece başka bir dosyanın içeriği
     * kısaltılıp üzerine yazılamaz. `clearstatcache()` her kontrolden önce
     * zorunludur (PHP'nin stat önbelleği sembolik bağ değişimini
     * gizleyebilir). Meşru (sembolik bağsız) akışta bu karşılaştırma flock
     * semantiğini DEĞİŞTİRMEZ — fstat/lstat her zaman eşleşir, akış aynen
     * devam eder. Bu kontrol yalnız kilit DOSYASININ kendisini korur; kilit
     * DİZİNİNİN bir sembolik bağ olması `ensureDirectory()`'de ayrıca
     * engellenir (ikisi birbirini tamamlar, bkz. o metodun docblock'u).
     *
     * `fopen()` kendisi başarısız olursa (izin/dizin sorunu) `false` döner —
     * önceki mtime/TTL tasarımıyla tutarlı. `flock()` `false` dönerse (kilit
     * başka bir open file description tarafından hâlâ tutuluyor) handle
     * hemen `fclose()` edilip `false` döner, sızıntı olmaz.
     *
     * @return bool Kilit bu çağrıyla alınabildiyse `true`; kilit başka bir
     *              işlem (veya bu örneğin daha önce başarıyla aldığı ve
     *              henüz `release()` etmediği kendi kilidi), veya yolun bir
     *              sembolik bağ olması/olmaya dönüşmesi nedeniyle `false`.
     */
    public function acquire(): bool
    {
        $lockDir = dirname($this->lockFile);

        clearstatcache(true, $lockDir);
        clearstatcache(true, $this->lockFile);

        if (is_link($lockDir) || is_link($this->lockFile)) {
            return false;
        }

        $handle = @fopen($this->lockFile, 'c');

        if ($handle === false) {
            return false;
        }

        $openedStat = fstat($handle);

        clearstatcache(true, $this->lockFile);
        $pathStat = @lstat($this->lockFile);

        if ($openedStat === false || $pathStat === false
            || $openedStat['dev'] !== $pathStat['dev']
            || $openedStat['ino'] !== $pathStat['ino']) {
            fclose($handle);

            return false;
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return false;
        }

        $this->handle = $handle;
        $this->held   = true;

        // Teşhis amaçlı, opsiyonel içerik — mantığın hiçbir yerinde okunmaz.
        @ftruncate($handle, 0);
        @fwrite($handle, sprintf("pid=%d acquired_at=%s\n", getmypid(), date(DATE_ATOM)));
        @fflush($handle);

        return true;
    }

    /**
     * Kilidi serbest bırakır — yalnız bu örnek kilidi GERÇEKTEN tutuyorsa.
     *
     * `flock($this->handle, LOCK_UN)` + `fclose($this->handle)` ile
     * kapatılır. **`unlink()` YAPILMAZ**: flock'lu bir dosyayı silmek klasik
     * bir yarış üretir — silinen yol disk üzerinde farklı bir inode olarak
     * yeniden oluşturulabilir ve iki süreç birbirinden habersiz iki farklı
     * inode'a kilit tutuyor sanabilir (ikisi de "kilidi ben aldım" sanır).
     * Kilit dosyasının boş/teşhis-içerikli haliyle diskte KALICI olarak
     * kalması bu tasarımda NORMAL ve BEKLENENDİR — bu bir sızıntı DEĞİLDİR.
     * ("Sızdırmayacak" kabul kriteri ASILI KALMIŞ bir `flock()`'u, yani
     * sürecin kilidi hiç bırakmamasını kastediyor; boş bir kilit dosyasının
     * diskte kalması ayrı ve kasıtlı bir davranıştır.)
     *
     * `$held` guard'ı hâlâ gereklidir — ama artık "başka bir sürecin
     * kilidini silmeme" için DEĞİL (`unlink()` hiç yapılmadığı için o risk
     * zaten YOK). Guard'ın yeni gerekçesi: aynı örnek üzerinde `release()`
     * İKİNCİ kez çağrılırsa (`Ci4msMigrate::run()` aynı `$lock` örneğinin
     * `release()`'ini iki ayrı `finally` bloğundan çağırıyor), guard
     * olmadan zaten KAPALI bir handle'da tekrar `fclose()` çağrılır ve PHP
     * "supplied resource is not a valid stream resource" `E_WARNING`'i
     * basar. Guard bunu önler; kilit hiç tutulmadıysa (`acquire()` hiç
     * çağrılmadı veya `false` döndü) de aynı sebeple no-op'tur.
     *
     * @return void
     */
    public function release(): void
    {
        if (!$this->held) {
            return;
        }

        flock($this->handle, LOCK_UN);
        fclose($this->handle);

        $this->handle = null;
        $this->held   = false;
    }

    /**
     * Kilit dosyasının bulunacağı dizini gerekirse oluşturur ve yazılabilir
     * olduğunu doğrular.
     *
     * `is_dir()` kontrolünden ÖNCE dizinin bir sembolik bağ olup olmadığı
     * denetlenir: `writable/` dizini `0777` izinlidir, dolayısıyla herhangi
     * bir yerel kullanıcı `writable/locks`'u silip yerine başka bir hedefe
     * (ör. hassas bir dosyanın bulunduğu dizine) işaret eden bir sembolik
     * bağ koyabilir — `is_dir()` bunu SESSİZCE kabul eder, çünkü bir
     * sembolik bağın hedefi bir dizinse `is_dir()` de `true` döner. Bu
     * kontrol o senaryoyu erken ve açıkça reddeder. `acquire()`'daki
     * fstat/lstat karşılaştırması yalnız kilit DOSYASININ kendisini korur,
     * bu DİZİN düzeyindeki saldırıyı KAPSAMAZ — ikisi birbirini tamamlar.
     *
     * @param string $path Oluşturulacak/doğrulanacak dizin yolu.
     *
     * @return void
     *
     * @throws \RuntimeException Dizin bir sembolik bağsa, oluşturulamıyorsa
     *                            veya yazılabilir değilse.
     */
    private function ensureDirectory(string $path): void
    {
        clearstatcache(true, $path);

        if (is_link($path)) {
            throw new \RuntimeException("Kilit dizini bir sembolik bağ: {$path}");
        }

        if (!is_dir($path) && !@mkdir($path, 0755, true) && !is_dir($path)) {
            throw new \RuntimeException("Kilit dizini oluşturulamadı: {$path}");
        }

        if (!is_writable($path)) {
            throw new \RuntimeException("Kilit dizini yazılabilir değil: {$path}");
        }
    }
}
