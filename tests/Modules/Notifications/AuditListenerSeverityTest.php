<?php

declare(strict_types=1);

namespace Tests\Modules\Notifications;

use CodeIgniter\Config\Services;
use CodeIgniter\Events\Events;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Notifications\CapturingChannel;
use Tests\Support\Notifications\FakeDispatchNotifier;

/**
 * `ci4ms.audit` -> `app/Config/Events.php` listener'ının severity sözleşmesi.
 *
 * Kapsam: yalnızca `app/Config/Events.php:59-83`'teki `Events::on('ci4ms.audit', ...)`
 * dinleyicisi. Üretici modüller (Fileeditor, MigrationManager, ...) burada
 * TETİKLENMİYOR — event doğrudan `Events::trigger()` ile fırlatılıyor, üretici
 * kodun kendisi başka testlerin konusu.
 *
 * DB'siz: gerçek `Notifier::notify()` (override edilmedi — final `NotificationBuilder`
 * inşa ediyor, bu yüzden severity/hardcode iddiaları GERÇEK builder üzerinden
 * ölçülüyor) `resolveChannels()` aracılığıyla {@see FakeDispatchNotifier}'ın taktığı
 * {@see CapturingChannel}'a düşüyor; `NotificationMessage` inşası saf PHP'dir
 * (bkz. NotificationMessage.php:87-128), DB'ye hiç dokunulmuyor. Desen
 * `tests/Modules/Notifications/NotificationTargetingTest.php`'in aynısı.
 *
 * Dinleyici KAYIT YOLU: `app/Config/Events.php` `CodeIgniter\Events\Events::initialize()`
 * (vendor/codeigniter4/framework/system/Events/Events.php:72-99) tarafından, o
 * dosyadaki `Events::on('ci4ms.audit', ...)` çağrısı üzerinden GERÇEKTEN kayıt
 * ediliyor — bu test dosyası listener'ı manuel kaydetmiyor. `trigger()` ilk
 * çağrıldığında `initialize()`'ı tembel biçimde tetikler (Events.php:140-144) ve
 * kayıt PHPUnit süreci boyunca kalıcıdır; hiçbir test `ci4ms.audit`
 * dinleyicilerini kaldırmıyor (yalnız `Ci4msReferenceDataSeederTest.php:257`
 * `model:afterCreate`'i kaldırıyor, ayrı bir event adı).
 *
 * @internal
 */
final class AuditListenerSeverityTest extends CIUnitTestCase
{
    private CapturingChannel $channel;

    private FakeDispatchNotifier $notifier;

    /**
     * Gerçek `Notifier::notify()`'ı taze bir yakalayıcı kanala bağlar.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->channel  = new CapturingChannel();
        $this->notifier = new FakeDispatchNotifier($this->channel);

        Services::injectMock('notifier', $this->notifier);
    }

    /**
     * Yalnız bu testin enjekte ettiği `notifier` mock'unu söker; geniş
     * `Services::reset()` (routes servisini düşüren tuzak,
     * .ci4ms/knowledge/pitfalls.md) burada gerekmiyor çünkü hiçbir controller
     * doğrudan çağrılmıyor.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        Services::resetSingle('notifier');

        parent::tearDown();
    }

    /**
     * `severity: 'critical'` olan bir event notifier'a ULAŞIYOR.
     *
     * Regresyon değeri: eski listener kodu (`!== 'warning'` filtresi) bunu
     * SESSİZCE düşürüyordu — `rbac.delegationCeilingRejected` hiç kimseye
     * ulaşmıyordu. Bu test o filtreyi kilitler.
     *
     * @return void
     */
    public function testCriticalSeverityReachesTheNotifier(): void
    {
        Events::trigger('ci4ms.audit', [
            'severity' => 'critical',
            'action'   => 'rbac.delegationCeilingRejected',
            'message'  => 'PHPUnit: critical audit event',
            'url'      => '/backend/users',
        ]);

        $this->assertCount(
            1,
            $this->channel->messages,
            'critical severity must reach the notifier — the old listener silently dropped it'
        );
    }

    /**
     * `severity: 'warning'` olan bir event hâlâ ULAŞIYOR (regresyon koruması).
     *
     * @return void
     */
    public function testWarningSeverityStillReachesTheNotifier(): void
    {
        Events::trigger('ci4ms.audit', [
            'severity' => 'warning',
            'action'   => 'fileeditor.save',
            'message'  => 'PHPUnit: warning audit event',
            'url'      => null,
        ]);

        $this->assertCount(1, $this->channel->messages);
    }

    /**
     * Notifier'a iletilen severity, event'in KENDİ değeridir — hardcoded
     * `'warning'` DEĞİL.
     *
     * Ayrı bir iddia: filtre geçse bile eski kod `->severity('warning')`'ü
     * sabit çağırıyordu, yani bir `critical` event bile notifier'a `warning`
     * olarak düşüyordu. İki farklı severity'yi ART ARDA tetikleyip İKİSİNİN de
     * kendi değeriyle geldiğini doğrulamak, tek-değerli bir teste göre
     * hardcode'u daha sıkı kapatır: sabit bir literal (ister 'warning' ister
     * başka bir şey) iki farklı sonucu asla üretemez.
     *
     * @return void
     */
    public function testNotifierReceivesTheEventsOwnSeverityNotAHardcodedWarning(): void
    {
        Events::trigger('ci4ms.audit', [
            'severity' => 'critical',
            'action'   => 'rbac.delegationCeilingRejected',
            'message'  => 'PHPUnit: first event, critical',
        ]);
        Events::trigger('ci4ms.audit', [
            'severity' => 'warning',
            'action'   => 'methods.update',
            'message'  => 'PHPUnit: second event, warning',
        ]);

        $this->assertCount(2, $this->channel->messages);
        $this->assertSame(
            'critical',
            $this->channel->messages[0]->severity,
            'first event was critical; hardcoded warning would report warning here'
        );
        $this->assertSame('warning', $this->channel->messages[1]->severity);
    }

    /**
     * `severity: 'info'` DÜŞÜRÜLÜYOR — notifier hiç çağrılmıyor.
     *
     * Fail-closed filtrenin hâlâ dar olduğunu kanıtlar: yalnız `warning` ve
     * `critical` beyaz listede, `info` dahil değil.
     *
     * @return void
     */
    public function testInfoSeverityIsDroppedNotifierNeverInvoked(): void
    {
        Events::trigger('ci4ms.audit', [
            'severity' => 'info',
            'action'   => 'whatever',
            'message'  => 'PHPUnit: info must never reach the notifier',
        ]);

        $this->assertSame([], $this->channel->messages);
    }

    /**
     * `severity` anahtarı hiç yoksa event DÜŞÜRÜLÜYOR — notifier hiç
     * çağrılmıyor.
     *
     * @return void
     */
    public function testMissingSeverityIsDroppedNotifierNeverInvoked(): void
    {
        Events::trigger('ci4ms.audit', [
            'action'  => 'whatever',
            'message' => 'PHPUnit: no severity key at all',
        ]);

        $this->assertSame([], $this->channel->messages);
    }

    /**
     * `severity` bir dizi (skaler olmayan) gelirse `is_string()` guard'ı
     * yüzünden event DÜŞÜRÜLÜYOR — notifier hiç çağrılmıyor.
     *
     * @return void
     */
    public function testNonStringSeverityIsDroppedNotifierNeverInvoked(): void
    {
        Events::trigger('ci4ms.audit', [
            'severity' => ['critical'],
            'action'   => 'whatever',
            'message'  => 'PHPUnit: array severity must not bypass the is_string guard',
        ]);

        $this->assertSame([], $this->channel->messages);
    }
}
