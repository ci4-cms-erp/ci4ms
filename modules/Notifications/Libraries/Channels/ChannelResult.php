<?php

declare(strict_types=1);

namespace Modules\Notifications\Libraries\Channels;

/**
 * Bir kanal teslim denemesinin değişmez sonucu (DTO).
 *
 * `ok` teslimin gerçekleştiğini, `insertId` (varsa) yazılan satırın kimliğini,
 * `meta` ise atlama sebebi gibi ek bağlamı taşır.
 *
 * KİMLİK: kanal sonucu ÜRETİRKEN kendi slug'ını bilmez (aynı sınıf birden çok slug'a
 * bağlanabilir); etiketi, kanal haritasının tek sahibi olan
 * {@see \Modules\Notifications\Libraries\NotificationBuilder::dispatch()} takar
 * ({@see forChannel()}). `durable` bayrağı da oradan gelir ve sonucun KALICI bir satır
 * yazan bir kanaldan mı geldiğini söyler — teslim kararının ("gerçekten yazıldı mı")
 * tek dayanağı budur. `ok && insertId !== null` kestirmesi KULLANILMAZ: insertId
 * döndüren geçici bir kanal eklendiği gün o kestirme sessizce yanlış cevap verirdi.
 */
final class ChannelResult
{
    /**
     * @param bool                 $ok       Teslim başarılıysa true.
     * @param int|null             $insertId Yazılan satırın kimliği (yoksa null).
     * @param array<string, mixed> $meta     Ek bağlam (ör. atlama sebebi).
     * @param string|null          $channel  Sonucu üreten kanalın slug'ı (etiketlenmemişse null).
     * @param bool                 $durable  Sonuç KALICI yazan bir kanaldan mı geldi.
     */
    private function __construct(
        public readonly bool $ok,
        public readonly ?int $insertId = null,
        public readonly array $meta = [],
        public readonly ?string $channel = null,
        public readonly bool $durable = false
    ) {
    }

    /**
     * Başarılı teslim sonucu üretir.
     *
     * @param int|null             $insertId Yazılan satırın kimliği.
     * @param array<string, mixed> $meta     Ek bağlam.
     *
     * @return self
     */
    public static function ok(?int $insertId = null, array $meta = []): self
    {
        return new self(true, $insertId, $meta);
    }

    /**
     * Atlanmış (teslim edilmemiş) sonuç üretir.
     *
     * @param string               $reason Atlama sebebi ('not-implemented' ...).
     * @param array<string, mixed> $meta   Ek bağlam.
     *
     * @return self
     */
    public static function skipped(string $reason = '', array $meta = []): self
    {
        return new self(false, null, array_merge(['reason' => $reason], $meta));
    }

    /**
     * Sonucun, onu üreten kanalın kimliğiyle etiketlenmiş kopyasını döndürür.
     *
     * DTO değişmezdir; etiketleme mevcut örneği değiştirmez, yeni bir örnek üretir.
     * Kanalların kendi `send()` gövdesinde çağırması BEKLENMEZ — etiketi dispatch takar,
     * böylece slug ile sınıf eşlemesinin tek sahibi kanal haritası olarak kalır.
     *
     * @param string $slug    Kanalın harita anahtarı ('inapp', 'realtime' ...).
     * @param bool   $durable Kanal kalıcı satır yazıyor mu ({@see DurableChannelInterface}).
     *
     * @return self Etiketli yeni sonuç.
     */
    public function forChannel(string $slug, bool $durable): self
    {
        return new self($this->ok, $this->insertId, $this->meta, $slug, $durable);
    }
}
