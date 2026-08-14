<?php

declare(strict_types=1);

namespace Tests\Modules\MigrationManager;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\MigrationManager\Libraries\RunLock;

/**
 * `RunLock` çalıştırma kilidinin `acquire()`/`release()` davranışı.
 *
 * Dosya sistemi tabanlı, DB gerekmez. Görev 17'nin manuel scratch-script
 * doğrulamasının (bkz. context.md "FAZ 5 — MODÜL BUILD (Görev 17)") kalıcı
 * PHPUnit hali; FAZ 8'de `RunLock` TTL/mtime tabanlı kilitten
 * `flock(LOCK_EX|LOCK_NB)` tabanlı kilide geçince buradaki testler de aynı
 * geçişi yansıtacak şekilde güncellendi (bkz. aşağıdaki "DÖNÜŞÜM NOTU"
 * işaretli docblock'lar) ve GERÇEK süreçler-arası dışlama ile SIGKILL sonrası
 * otomatik kilit bırakmayı kanıtlayan iki yeni test eklendi.
 *
 * FAZ 9 (context.md, J1/J3): FAZ 8'in `fopen(..., 'xb')` → `fopen(..., 'c')`
 * geçişi, eski moddaki `O_CREAT|O_EXCL` sembolik-bağ reddini kaldırıp yerine
 * hiçbir guard koymamıştı — `RunLock::acquire()` bir sembolik bağı takip edip
 * hedef dosyayı `ftruncate()`/`fwrite()` ile kısaltıp üzerine yazabiliyordu
 * (quality-lead FAZ8 ölçümü). `RunLock.php:129-174` `acquire()`'a fail-fast
 * `is_link()` kontrolü + `fopen()` sonrası `fstat($handle)` vs
 * `lstat($this->lockFile)` (`dev`+`ino`) karşılaştırması, `ensureDirectory()`
 * (`:236-251`) içine de dizin-düzeyi `is_link()` guard'ı eklendi. Bu dosyaya
 * bu guard'ı KALICI olarak kilitleyen iki yeni test eklendi
 * (`testAcquireOnASymlinkedLockFileFailsAndLeavesTheTargetFileUntouched`,
 * `testConstructorThrowsWhenTheLockDirectoryIsASymlink`) — birincisi
 * mutasyon-kanıtlı olarak doğrulandı (bkz. context.md FAZ 9 [ci4ms-qa]
 * kanıt kütüğü: guard geçici olarak devre dışı bırakılıp test KIRMIZI
 * olduğu, sonra guard birebir geri getirilip test tekrar YEŞİL olduğu
 * gösterildi). Ayrıca constructor imzası ikinci `$ttl` parametresini tamamen
 * kaybettiği için (`RunLock.php:76`) bu dosyadaki TÜM `new RunLock(...)`
 * çağrı yerleri tek argümanlı hale getirildi; `testStaleMtimeDoesNotLetASecondAcquireStealAGenuinelyHeldLock`
 * içindeki yerel `$ttl` değişkeni (yalnız `time() - ($ttl + 1000)` mtime
 * aritmetiğinde kullanılıyor, artık constructor'a GEÇMİYOR) kasıtlı olarak
 * korundu.
 *
 * @internal
 */
final class RunLockTest extends CIUnitTestCase
{
    private string $lockFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lockFile = sys_get_temp_dir() . '/ci4ms_runlock_test_' . bin2hex(random_bytes(6)) . '.lock';
    }

    protected function tearDown(): void
    {
        if (is_file($this->lockFile) || is_link($this->lockFile)) {
            unlink($this->lockFile);
        }

        parent::tearDown();
    }

    /**
     * İlk acquire() kilidi alır; kilit hâlâ tutulurken yapılan ikinci
     * ardışık acquire() çağrısı false döner.
     */
    public function testSecondAcquireFailsWhileLockIsStillHeld(): void
    {
        $first  = new RunLock($this->lockFile);
        $second = new RunLock($this->lockFile);

        $this->assertTrue($first->acquire(), 'First acquire() on a free lock must succeed.');
        $this->assertFalse($second->acquire(), 'A second acquire() must fail while the lock file is still fresh.');
    }

    /**
     * DÖNÜŞÜM NOTU (FAZ 8, context.md): bu test eskiden
     * `testAcquireClearsAStaleLockPastItsTtl` idi ve TTL semantiğini
     * (bayat mtime'lı kilidin otomatik temizlenip ÇALINABİLDİĞİNİ)
     * kanıtlıyordu. `RunLock` `flock(LOCK_EX|LOCK_NB)`'a geçtiğinden beri bu
     * semantik YOK — sınıf artık dosya mtime'ına hiç bakmıyor
     * (`RunLock.php:126-149`). Test SİLİNMEDİ: aynı "bayat dosya" senaryosu
     * TERSİNE çevrilip yeni davranışı kanıtlayan bir teste dönüştürüldü —
     * eski TTL dünyasında bu senaryo (bayat mtime + hâlâ AKTİF sahip)
     * ikinci `acquire()`'ın YANLIŞLIKLA başarılı olmasına (kilidi çalmasına)
     * yol açardı; `flock()` dünyasında ise ilk sahip kilidi GERÇEKTEN
     * tutmaya devam ettiği sürece mtime ne kadar geriye alınırsa alınsın
     * ikinci `acquire()` BAŞARISIZ kalmalı — tek belirleyici gerçek bir
     * flock'un tutulup tutulmadığıdır, dosya üzerindeki bir zaman damgası
     * değil.
     *
     * FAZ 9 NOTU: `$ttl` yerel değişkeni constructor'a artık GEÇMİYOR
     * (`RunLock::__construct()` tek argümanlı, `RunLock.php:76`) — burada
     * yalnız aşağıdaki `touch()` çağrısının mtime aritmetiğinde ("TTL'nin
     * çok ötesi" mesafesini ifade etmek için) kullanılan yerel bir sabit
     * olarak kaldı, testin kendi mantığında hâlâ anlamlı.
     */
    public function testStaleMtimeDoesNotLetASecondAcquireStealAGenuinelyHeldLock(): void
    {
        $ttl   = 1;
        $first = new RunLock($this->lockFile);

        $this->assertTrue($first->acquire(), 'Initial acquire() on a free lock must succeed.');
        $this->assertTrue(is_file($this->lockFile), 'acquire() must create the lock file.');

        // Eski TTL semantiğini simüle et: mtime'ı TTL'nin çok ötesine geriye
        // al. flock tabanlı dünyada bunun kilit DURUMU üzerinde hiçbir etkisi
        // olmamalı — $first hâlâ kilidi GERÇEKTEN tutuyor.
        $this->assertTrue(touch($this->lockFile, time() - ($ttl + 1000)));

        $second = new RunLock($this->lockFile);
        $this->assertFalse(
            $second->acquire(),
            'A stale file mtime must NOT let a second acquire() steal a lock that is still genuinely held by its '
                . 'owner — only a real flock release (or the owning process dying) may free it.',
        );

        $first->release();
    }

    /**
     * DÖNÜŞÜM NOTU (FAZ 8, context.md): bu test eskiden
     * `testReleaseDeletesTheLockFile` idi ve `release()`'in kilit dosyasını
     * SİLDİĞİNİ iddia ediyordu. `RunLock::release()` artık `unlink()`
     * yapmıyor (`RunLock.php:154-188` — flock'lu bir dosyayı silmek klasik
     * bir TOCTOU/inode yarışı üretir, bu yüzden boş/teşhis-içerikli kilit
     * dosyasının diskte KALMASI kasıtlı ve normal). Test SİLİNMEDİ: iddiası
     * tersine çevrildi — dosyanın diskte KALDIĞINI ve buna RAĞMEN kilidin
     * SERBEST olduğunu (ikinci bir örneğin aynı yolu acquire() edebildiğini)
     * birlikte kanıtlıyor.
     */
    public function testReleaseFreesTheLockButLeavesTheFileOnDisk(): void
    {
        $lock = new RunLock($this->lockFile);

        $this->assertTrue($lock->acquire());
        $this->assertTrue(is_file($this->lockFile));

        $lock->release();

        $this->assertFileExists(
            $this->lockFile,
            'release() must NOT delete the lock file — flock()-based release() intentionally never unlink()s it.',
        );

        $second = new RunLock($this->lockFile);
        $this->assertTrue(
            $second->acquire(),
            'A fresh RunLock on the same path must be able to acquire the lock right after release(), even though '
                . 'the lock file itself still exists — the file and the flock are decoupled.',
        );
        $second->release();
    }

    /**
     * release() kilit dosyası hiç yoksa da sessizce (hatasız) döner.
     */
    public function testReleaseIsIdempotentWhenNoLockFileExists(): void
    {
        $lock = new RunLock($this->lockFile);

        $this->assertFalse(is_file($this->lockFile));

        $lock->release();

        $this->assertFalse(is_file($this->lockFile));
    }

    /**
     * Aynı örnek üzerinde `release()` İKİNCİ kez çağrılması, arada başka bir
     * sürecin (B) ALDIĞI kilidi ETKİLEMEMELİ.
     *
     * DÖNÜŞÜM NOTU (FAZ 8, context.md): bu testin docblock'u eskiden "ikinci
     * release() B'nin kilidini SİLER" tehlikesini anlatıyordu — bu, eski
     * `unlink()` tabanlı `release()`'de gerçek bir riskti: A.acquire() →
     * A.release() (dosyayı SİLER) → B.acquire() (dosyayı yeniden YARATIR) →
     * A.release() (TEKRAR, dosyayı yine SİLER) → B'nin kilidi kaybolur → C de
     * acquire() edebilir → iki eşzamanlı koşum. `flock()`'a geçişle
     * `release()` artık HİÇ `unlink()` yapmıyor (`RunLock.php:177-188`) — bu
     * tehlike sınıfı YAPISAL OLARAK imkansız hale geldi. `$held` guard'ının
     * YENİ gerekçesi (`RunLock.php:165-173`): guard olmadan ikinci
     * `release()` zaten KAPALI bir `$handle`'da tekrar `fclose()` çağırıp PHP
     * "supplied resource is not a valid stream resource" `E_WARNING`'ini
     * tetikler — guard bunu sessizce no-op'a çevirir.
     *
     * `Ci4msMigrate::run()` aynı `$lock` örneği üzerinde iki ayrı noktadan
     * `release()` çağırıyor (closure `finally` + dış `finally`, bkz.
     * `modules/Backend/Commands/Ci4msMigrate.php`). Bu test A/B/C senaryosunu
     * reprodüksiyon edip A'nın ikinci (no-op) `release()`'inden SONRA B'nin
     * kilidinin hâlâ AKTİF olduğunu — üçüncü bir örneğin (C) onu
     * ÇALAMADIĞINI — kanıtlıyor; bu, salt dosya-varlığı kontrolünden daha
     * güçlü bir kanıttır (FAZ 5 quality-lead F1 bulgusunun FAZ 8'de
     * güncellenmiş hali).
     */
    public function testSecondReleaseOnTheSameInstanceDoesNotAffectAnotherInstancesLock(): void
    {
        $a = new RunLock($this->lockFile);
        $b = new RunLock($this->lockFile);
        $c = new RunLock($this->lockFile);

        $this->assertTrue($a->acquire(), 'A must acquire the free lock.');

        $a->release();

        $this->assertTrue($b->acquire(), "B must be able to acquire the lock after A's first release().");

        // Ci4msMigrate.php'nin ikinci `finally` bloğunu simüle eder: A aynı
        // örnek üzerinde release()'i TEKRAR çağırır.
        $a->release();

        $this->assertFileExists($this->lockFile, "A's second release() call must not delete B's lock file.");

        // Daha güçlü kanıt: B'nin kilidi A'nın ikinci (no-op) release()'inden
        // SONRA da hâlâ AKTİF — üçüncü bir örnek (C) onu acquire() EDEMEMELİ.
        $this->assertFalse(
            $c->acquire(),
            "B's lock must still be genuinely held after A's second (no-op) release() call — C must not be able "
                . 'to steal it.',
        );

        $b->release();
    }

    /**
     * GERÇEK süreçler-arası dışlama: ayrı bir PHP süreci kilidi alıp
     * beklerken, bu test süreci aynı yola `acquire()` denemeli ve `false`
     * almalı.
     *
     * Aynı-süreç içindeki iki `RunLock` örneği (yukarıdaki testler) yalnız
     * "iki ayrı `fopen()` → iki ayrı open file description → ikinci
     * `flock()` başarısız olmalı" varsayımını sınar; bu varsayımın gerçek
     * SÜREÇLER ARASI da geçerli olduğu (kullanıcının FAZ 8 talimatındaki H3
     * bulgusu) daha önce hiçbir kalıcı PHPUnit testinde ölçülmedi. Çocuk
     * süreç framework/spark boot ETMEZ — yalnızca `RunLock.php`'yi `require`
     * edip sınıfı doğrudan kullanır; kullanılan `$lockFile` her zaman açıkça
     * verildiği için (`WRITEPATH` fallback dalı hiç tetiklenmez) framework
     * boot'una ihtiyaç yok.
     */
    public function testAcquireFailsWhenALockIsGenuinelyHeldByASeparateOsProcess(): void
    {
        $lockFile   = sys_get_temp_dir() . '/ci4ms_runlock_ipc_' . bin2hex(random_bytes(6)) . '.lock';
        $readyFile  = $lockFile . '.ready';
        $scriptFile = $this->writeChildLockHolderScript();
        $process    = null;

        try {
            $process = $this->spawnChildLockHolder($scriptFile, $lockFile, $readyFile, 10);

            $this->waitForReadyFileOrFail($readyFile);
            $this->assertSame(
                'READY',
                trim((string) file_get_contents($readyFile)),
                'Child process failed to acquire its own lock — test setup invariant broken.',
            );

            $probe = new RunLock($lockFile);
            $this->assertFalse(
                $probe->acquire(),
                'A second, real OS process holding the same lock file must cause acquire() to fail — this is the '
                    . 'inter-process exclusion guarantee flock(LOCK_EX|LOCK_NB) is supposed to provide.',
            );
        } finally {
            $this->killAndReapChildProcess($process);
            @unlink($scriptFile);
            @unlink($readyFile);
            @unlink($lockFile);
        }
    }

    /**
     * Süreç ölünce kilit OTOMATİK bırakılıyor: kilit tutan alt süreç
     * SIGKILL ile öldürülür (kendi cleanup mantığı ÇALIŞMADAN, kirli/ani ölüm
     * senaryosu), sonra bu test sürecinin `acquire()`'ı BAŞARILI olmalı.
     *
     * Bu, TTL/heartbeat'in yerini neyin aldığının (OS'in open file
     * description'a bağlı `flock()`'u süreç ölünce kendiliğinden bırakması)
     * ASIL kanıtıdır — H1/kullanıcı yaklaşım önerisinin merkezi varsayımı.
     */
    public function testLockIsAutomaticallyReleasedWhenTheHoldingProcessIsKilled(): void
    {
        $lockFile   = sys_get_temp_dir() . '/ci4ms_runlock_sigkill_' . bin2hex(random_bytes(6)) . '.lock';
        $readyFile  = $lockFile . '.ready';
        $scriptFile = $this->writeChildLockHolderScript();
        $process    = null;

        try {
            $process = $this->spawnChildLockHolder($scriptFile, $lockFile, $readyFile, 60);

            $this->waitForReadyFileOrFail($readyFile);
            $this->assertSame('READY', trim((string) file_get_contents($readyFile)));

            $probeWhileAlive = new RunLock($lockFile);
            $this->assertFalse(
                $probeWhileAlive->acquire(),
                'Sanity check: the lock must still be genuinely held by the live child process before it is killed.',
            );

            $status       = $this->killAndReapChildProcess($process);
            $process      = null;
            $this->assertFalse($status['running'], 'The child process did not die within the timeout after SIGKILL.');

            $probeAfterKill = new RunLock($lockFile);
            $this->assertTrue(
                $probeAfterKill->acquire(),
                'Once the holding process is SIGKILLed, the kernel must automatically release its flock — this is '
                    . 'the exact guarantee that replaces the old TTL/heartbeat mechanism.',
            );
            $probeAfterKill->release();
        } finally {
            $this->killAndReapChildProcess($process);
            @unlink($scriptFile);
            @unlink($readyFile);
            @unlink($lockFile);
        }
    }

    /**
     * FAZ 9 J1 (context.md, HIGH regresyon kapanışı): kilit DOSYASININ
     * kendisi bir sembolik bağ olduğunda `acquire()` `false` dönmeli VE
     * sembolik bağın HEDEFİNDEKİ dosyanın içeriği (md5 ile karşılaştırılarak)
     * çağrı öncesi/sonrası BİREBİR AYNI kalmalı — `RunLock.php:129-174`'teki
     * fail-fast `is_link()` kontrolü + `fopen()` sonrası `fstat`/`lstat`
     * karşılaştırması olmasaydı (FAZ 8'in kendi ürettiği regresyon, `'xb'` →
     * `'c'` fopen modu geçişiyle) bu senaryoda `ftruncate()`/`fwrite()`
     * hedef dosyayı KISALTIP ÜZERİNE YAZARDI (quality-lead FAZ8 ölçümü,
     * context.md).
     *
     * İzole bir tmp dizininde ayrı bir "hedef" dosya (bu sınıfın normal kilit
     * dosyası DEĞİL, saldırının hedeflediği hassas içerikli dosya) ve ayrı
     * bir "sahte kilit" sembolik bağ yolu kurulur; `RunLock` bu sembolik bağ
     * yoluna karşı çalıştırılır.
     */
    public function testAcquireOnASymlinkedLockFileFailsAndLeavesTheTargetFileUntouched(): void
    {
        $dir        = sys_get_temp_dir() . '/ci4ms_runlock_symlink_file_' . bin2hex(random_bytes(6));
        $targetFile = $dir . '/sensitive-target.txt';
        $fakeLock   = $dir . '/fake-lock.lock';

        mkdir($dir, 0755, true);
        file_put_contents($targetFile, "ORIGINAL-SENSITIVE-CONTENT\n");
        $md5Before = md5_file($targetFile);

        try {
            $this->assertTrue(
                symlink($targetFile, $fakeLock),
                'Test setup failed: could not create the attack symlink.',
            );

            $lock = new RunLock($fakeLock);

            $this->assertFalse(
                $lock->acquire(),
                'acquire() must refuse to operate through a symlinked lock file — the fail-fast is_link() check '
                    . 'plus the post-fopen() fstat()/lstat() comparison must reject it before flock()/ftruncate()/'
                    . 'fwrite() are ever reached.',
            );

            $md5After = md5_file($targetFile);
            $this->assertSame(
                $md5Before,
                $md5After,
                'The symlink target file content must be byte-for-byte unchanged — a regression here means '
                    . 'acquire() followed the symlink and truncated/overwrote an arbitrary file.',
            );
        } finally {
            @unlink($fakeLock);
            @unlink($targetFile);
            @rmdir($dir);
        }
    }

    /**
     * FAZ 9 J1 (context.md): kilit DİZİNİNİN kendisi bir sembolik bağ
     * olduğunda constructor `\RuntimeException` fırlatmalı —
     * `ensureDirectory()`'nin (`RunLock.php:236-251`) yeni dizin-düzeyi
     * guard'ı. `writable/` dizini `0777` olduğundan herhangi bir yerel
     * kullanıcı `writable/locks`'u silip yerine bir sembolik bağ koyabilir;
     * bu test o senaryonun constructor seviyesinde erken ve açıkça
     * reddedildiğini kilitler.
     */
    public function testConstructorThrowsWhenTheLockDirectoryIsASymlink(): void
    {
        $base      = sys_get_temp_dir() . '/ci4ms_runlock_symlink_dir_' . bin2hex(random_bytes(6));
        $realDir   = $base . '/real-target-dir';
        $symlinkDir = $base . '/locks-symlink';
        $lockFile  = $symlinkDir . '/migration_manager.lock';

        mkdir($realDir, 0755, true);

        try {
            $this->assertTrue(
                symlink($realDir, $symlinkDir),
                'Test setup failed: could not create the attack symlink directory.',
            );

            $this->expectException(\RuntimeException::class);

            new RunLock($lockFile);
        } finally {
            @unlink($symlinkDir);
            @rmdir($realDir);
            @rmdir($base);
        }
    }

    /**
     * `RunLock.php`'yi `require` edip kilidi alan, "hazır" sinyali yazan ve
     * ardından uzun süre uyuyan minimal bir çocuk PHP script'i üretir. Hiçbir
     * framework/spark boot içermez.
     *
     * @return string Üretilen script'in geçici dosya yolu.
     */
    private function writeChildLockHolderScript(): string
    {
        $scriptPath        = sys_get_temp_dir() . '/ci4ms_runlock_child_' . bin2hex(random_bytes(6)) . '.php';
        $runLockSourcePath = ROOTPATH . 'modules/MigrationManager/Libraries/RunLock.php';

        $source = '<?php' . "\n"
            . '[$scriptSelf, $lockFile, $readyFile, $sleepSeconds] = $argv;' . "\n"
            . 'require ' . var_export($runLockSourcePath, true) . ';' . "\n"
            . '$lock = new \\Modules\\MigrationManager\\Libraries\\RunLock($lockFile);' . "\n"
            . 'if (!$lock->acquire()) {' . "\n"
            . '    file_put_contents($readyFile, \'ACQUIRE_FAILED\');' . "\n"
            . '    exit(1);' . "\n"
            . '}' . "\n"
            . 'file_put_contents($readyFile, \'READY\');' . "\n"
            . 'sleep((int) $sleepSeconds);' . "\n";

        file_put_contents($scriptPath, $source);

        return $scriptPath;
    }

    /**
     * Çocuk kilit-tutucu script'ini `proc_open()` ile başlatır. Çıktı
     * `/dev/null`'a yönlendirilir (bu test süitinin çıktısını kirletmemek
     * ve pipe-dolma engelini önlemek için) — script zaten stdout/stderr'e
     * yazmaz, tüm iletişim `$readyFile` üzerinden dosya sistemiyle yapılır.
     *
     * @return resource
     */
    private function spawnChildLockHolder(string $scriptFile, string $lockFile, string $readyFile, int $sleepSeconds)
    {
        $process = proc_open(
            [PHP_BINARY, $scriptFile, $lockFile, $readyFile, (string) $sleepSeconds],
            [
                1 => ['file', '/dev/null', 'w'],
                2 => ['file', '/dev/null', 'w'],
            ],
            $pipes,
        );

        $this->assertIsResource($process, 'Failed to spawn the child RunLock holder process.');

        return $process;
    }

    /**
     * `$readyFile`'ın belirmesini kısa aralıklarla poll eder — kör
     * `sleep()`'e karşı yarış durumuna dayanıklı bekleme.
     *
     * @return void
     */
    private function waitForReadyFileOrFail(string $readyFile, float $timeoutSeconds = 3.0): void
    {
        $deadline = microtime(true) + $timeoutSeconds;

        while (microtime(true) < $deadline) {
            if (is_file($readyFile)) {
                return;
            }

            usleep(20000);
        }

        $this->fail("Child process did not signal readiness within {$timeoutSeconds}s (ready file: {$readyFile}).");
    }

    /**
     * `SIGKILL` gönderir, süreç GERÇEKTEN ölene kadar kısa aralıklarla poll
     * eder, sonra kaynağı kapatır — hiçbir testin asılı bir alt süreç
     * bırakmamasını garanti eder. `null` veya zaten kapatılmış bir kaynakla
     * çağrılırsa no-op'tur (testlerin hem `try` gövdesinde hem `finally`
     * bloğunda güvenle çağrılabilmesi için).
     *
     * @param resource|null $process
     *
     * @return array{running: bool} `proc_close()`'dan HEMEN ÖNCEKİ son bilinen durum.
     */
    private function killAndReapChildProcess($process): array
    {
        if (!is_resource($process)) {
            return ['running' => false];
        }

        proc_terminate($process, 9);

        $deadline = microtime(true) + 3.0;
        $status   = proc_get_status($process);

        while ($status['running'] && microtime(true) < $deadline) {
            usleep(20000);
            $status = proc_get_status($process);
        }

        proc_close($process);

        return $status;
    }
}
