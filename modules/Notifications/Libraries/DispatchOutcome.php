<?php

declare(strict_types=1);

namespace Modules\Notifications\Libraries;

use Modules\Notifications\Libraries\Channels\ChannelResult;
use Modules\Notifications\Libraries\Channels\DurableChannelInterface;

/**
 * Bir yayının GERÇEKTEN teslim edilip edilmediğinin değişmez cevabı.
 *
 * Karar burada verilir, çağıranda değil: "gönderildi mi" sorusunun tek doğru ölçüsü
 * KALICI yazan kanalların ({@see DurableChannelInterface}) sonuçlarıdır. Geçici kanallar
 * (realtime sinyali gibi) sayıma HİÇ girmez — onların `ok`'u yalnız "istemcilere
 * yeniden oku dedim" demektir; ortada okunacak satır yoksa bu bir teslim değildir.
 *
 * ÜÇ DURUM ayrı ayrı görünür kılınır, çünkü kullanıcıya söylenecek şey farklıdır:
 *   - TAM     ({@see isComplete()}): denenen her kalıcı satır yazıldı.
 *   - KISMİ   ({@see isPartial()}):  bazıları yazıldı, bazıları REDDEDİLDİ. Yayın
 *     hedeflenen kitlenin bir bölümüne ulaşmadı; "gönderildi" demek yalan olurdu.
 *   - HİÇBİRİ ({@see storedNothing()}): tek satır bile yazılmadı.
 * Hiç kalıcı kanal çalışmadıysa (`attempted() === 0`) sonuç HİÇBİRİ'dir — bilgi
 * yokluğu başarı sayılmaz (fail-closed).
 *
 * Refüz sebepleri ({@see refusals()}) kanalın kendi log kaydını TEKRARLAMAZ; yalnız
 * çağıranın kararını ve mesajını zenginleştirmek için taşınır.
 */
final class DispatchOutcome
{
    /**
     * @param int          $attempted Kalıcı kanal üzerinden denenen satır sayısı.
     * @param int          $stored    Bunlardan gerçekten yazılanların sayısı.
     * @param list<string> $refusals  Yazılamayanların tekilleştirilmiş sebepleri.
     */
    private function __construct(
        private readonly int $attempted,
        private readonly int $stored,
        private readonly array $refusals
    ) {
    }

    /**
     * Kanal sonuçlarından teslim durumunu türetir.
     *
     * @param ChannelResult[] $results {@see NotificationBuilder::dispatch()} çıktısı
     *                                 (kanal kimliğiyle etiketlenmiş sonuçlar).
     *
     * @return self Teslim özeti.
     */
    public static function fromResults(array $results): self
    {
        $durable = array_filter($results, static fn (ChannelResult $result): bool => $result->durable);
        $stored  = array_filter($durable, static fn (ChannelResult $result): bool => $result->ok);

        $refusals = array_map(
            static fn (ChannelResult $result): string => (string) ($result->meta['reason'] ?? 'unknown'),
            array_filter($durable, static fn (ChannelResult $result): bool => ! $result->ok)
        );

        return new self(count($durable), count($stored), array_values(array_unique($refusals)));
    }

    /**
     * Kalıcı kanal üzerinden kaç satır denendi.
     *
     * @return int Denenen satır sayısı (hiç kalıcı kanal çalışmadıysa 0).
     */
    public function attempted(): int
    {
        return $this->attempted;
    }

    /**
     * Kaç satır gerçekten yazıldı.
     *
     * @return int Yazılan satır sayısı.
     */
    public function stored(): int
    {
        return $this->stored;
    }

    /**
     * Denenen her satır yazıldı mı (ve en az bir satır denendi mi).
     *
     * @return bool Yayın eksiksiz teslim edildiyse true.
     */
    public function isComplete(): bool
    {
        return $this->attempted > 0 && $this->stored === $this->attempted;
    }

    /**
     * Satırların bir bölümü yazıldı, bir bölümü reddedildi mi.
     *
     * @return bool Kısmi teslimde true.
     */
    public function isPartial(): bool
    {
        return $this->stored > 0 && $this->stored < $this->attempted;
    }

    /**
     * Hiçbir kalıcı satır yazılmadı mı (hiç denenmemiş olması dahil).
     *
     * @return bool Ortada bildirim yoksa true.
     */
    public function storedNothing(): bool
    {
        return $this->stored === 0;
    }

    /**
     * Yazılamayan satırların tekilleştirilmiş refüz sebepleri.
     *
     * @return list<string> 'exclusion-unsupported', 'insert-failed' gibi sebepler.
     */
    public function refusals(): array
    {
        return $this->refusals;
    }
}
